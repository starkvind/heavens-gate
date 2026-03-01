<?php
/* ============================================================
   admin_pois.php — Gestión de Mapas, Categorías, POIs y Áreas (Heaven's Gate)
   Requisitos:
   - Conexión mysqli $link YA abierta (tu index/router la incluye)
   - Tablas: dim_maps, dim_map_categories, fact_map_pois, fact_map_areas
   ============================================================ */

// Iniciamos buffering para evitar que cualquier salida previa contamine el JSON
if (!headers_sent()) { @ob_start(); }

if (!$link) { die("Error de conexión a la base de datos: " . mysqli_connect_error()); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_pois';
$ADMIN_CSRF_TOKEN = function_exists('hg_admin_ensure_csrf_token')
  ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
  : '';

/* ---------------------------
   Helpers PHP
   --------------------------- */
function slugify($s) {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $s = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
  $s = preg_replace('/[^a-z0-9]+/i', '-', $s);
  $s = trim($s, '-');
  return $s ?: substr(md5(uniqid('', true)), 0, 8);
}
function jres($arr, $status=200){
  if (function_exists('hg_admin_json_response')) {
    hg_admin_json_response((array)$arr, (int)$status);
  }
  // Fallback si helper compartido no esta disponible
  while (ob_get_level()) { @ob_end_clean(); }
  if (function_exists('header_remove')) { @header_remove(); }
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  http_response_code((int)$status);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function ok($data=[], $message='OK'){
  $payload = [
    'ok' => true,
    'message' => $message,
    'msg' => $message,
    'error' => null,
    'data' => $data,
    'errors' => [],
    'meta' => [],
  ];
  if (is_array($data)) {
    // Compatibilidad legacy: exponer maps/cats/pois/areas en top-level
    $payload = array_merge($payload, $data);
  }
  jres($payload, 200);
}
function jerr($msg, $code=400, $errors=[], $data=null){
  $payload = [
    'ok' => false,
    'error' => $msg,
    'message' => $msg,
    'msg' => $msg,
    'data' => $data,
    'errors' => (array)$errors,
    'meta' => [],
  ];
  jres($payload, (int)$code);
}

/**
 * Normaliza una fila de fact_map_areas para el payload JS:
 * - geometry: decode -> geometry_json (string)
 * - extrae props de estilo de Feature.properties
 */
function area_row_to_payload(array $row): array {
  $g = null;
  if (isset($row['geometry'])) {
    $g = json_decode($row['geometry'], true);
    if (json_last_error() !== JSON_ERROR_NONE) { $g = null; }
  }
  $props = is_array($g) ? ($g['properties'] ?? []) : [];
  return [
    'id'             => (int)$row['id'],
    'name'           => $row['name'] ?? '',
    'map_id'         => isset($row['map_id']) ? (int)$row['map_id'] : null,
    'map_name'       => $row['map_name'] ?? '',
    'description'    => $row['description'] ?? '',
    'color_hex'      => ($row['color_hex'] ?? '#2ecc71') ?: '#2ecc71',
    'geometry_json'  => $g ? json_encode($g, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '',
    'fill_opacity'   => isset($props['fillOpacity']) ? (float)$props['fillOpacity'] : null,
    'stroke_color'   => isset($props['color']) ? (string)$props['color'] : null,
    'stroke_weight'  => isset($props['weight']) ? (int)$props['weight'] : null,
    'created_at'     => $row['created_at'] ?? null,
    'updated_at'     => $row['updated_at'] ?? null,
  ];
}

/* ---------------------------
   Endpoints AJAX (fetch desde JS)
   --------------------------- */
if (isset($_GET['ajax'])) {
  $a = $_GET['ajax'];

  if (function_exists('hg_admin_require_session')) {
    hg_admin_require_session(true);
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('hg_admin_csrf_valid')) {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $csrf = function_exists('hg_admin_extract_csrf_token')
      ? hg_admin_extract_csrf_token($payload)
      : trim((string)($_POST['csrf'] ?? ''));
    if (!hg_admin_csrf_valid($csrf, $ADMIN_CSRF_SESSION_KEY)) {
      jerr('Token CSRF invalido', 403, ['csrf' => 'invalid']);
    }
  }

  // --- Listados
  if ($a === 'list_all') {
    // MAPS
    $maps = [];
    $m = $link->query("SELECT id,name,slug,center_lat,center_lng,default_zoom,min_zoom,max_zoom,
                              bounds_sw_lat,bounds_sw_lng,bounds_ne_lat,bounds_ne_lng,default_tile,
                              created_at,updated_at
                       FROM dim_maps ORDER BY name");
    while ($row = $m->fetch_assoc()) $maps[] = $row;

    // CATS
    $cats = [];
    $c = $link->query("SELECT id,name,slug,color_hex,icon,sort_order,created_at,updated_at
                       FROM dim_map_categories ORDER BY sort_order, name");
    while ($row = $c->fetch_assoc()) $cats[] = $row;

    // POIS (incluye description)
    $pois = [];
    $p = $link->query("SELECT p.id, p.name, p.description, p.map_id, m.name AS map_name,
                              p.category_id, c.name AS category_name,
                              p.thumbnail, p.latitude, p.longitude,
                              p.created_at, p.updated_at
                       FROM fact_map_pois p
                       JOIN dim_maps m ON m.id=p.map_id
                       JOIN dim_map_categories c ON c.id=p.category_id
                       ORDER BY p.id DESC");
    while ($row = $p->fetch_assoc()) $pois[] = $row;

    // ÁREAS (usar columnas reales: geometry)
    $areas = [];
    $aqq = $link->query("SELECT a.id, a.name, a.map_id, m.name AS map_name,
                                a.description, a.color_hex,
                                a.geometry, a.created_at, a.updated_at
                         FROM fact_map_areas a
                         JOIN dim_maps m ON m.id=a.map_id
                         ORDER BY a.id DESC");
    while ($row = $aqq->fetch_assoc()) {
      $areas[] = area_row_to_payload($row);
    }

    ok(['maps'=>$maps, 'cats'=>$cats, 'pois'=>$pois, 'areas'=>$areas]);
  }

  // --- Guardar mapa (crear/editar)
  if ($a === 'save_map' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $slug = $slug !== '' ? slugify($slug) : slugify($name);
    if ($name==='') jerr("El nombre del mapa es obligatorio.");

    $center_lat = (float)($_POST['center_lat'] ?? 0);
    $center_lng = (float)($_POST['center_lng'] ?? 0);
    $default_zoom = (int)($_POST['default_zoom'] ?? 8);
    $min_zoom = isset($_POST['min_zoom']) && $_POST['min_zoom']!=='' ? (int)$_POST['min_zoom'] : null;
    $max_zoom = isset($_POST['max_zoom']) && $_POST['max_zoom']!=='' ? (int)$_POST['max_zoom'] : null;

    $bsw_lat = ($_POST['bounds_sw_lat'] ?? '')!=='' ? (float)$_POST['bounds_sw_lat'] : null;
    $bsw_lng = ($_POST['bounds_sw_lng'] ?? '')!=='' ? (float)$_POST['bounds_sw_lng'] : null;
    $bne_lat = ($_POST['bounds_ne_lat'] ?? '')!=='' ? (float)$_POST['bounds_ne_lat'] : null;
    $bne_lng = ($_POST['bounds_ne_lng'] ?? '')!=='' ? (float)$_POST['bounds_ne_lng'] : null;

    $default_tile = trim($_POST['default_tile'] ?? 'carto-dark');

    if ($id>0) {
      // UPDATE
      $st = $link->prepare("UPDATE dim_maps
        SET name=?, slug=?, center_lat=?, center_lng=?, default_zoom=?, min_zoom=?, max_zoom=?,
            bounds_sw_lat=?, bounds_sw_lng=?, bounds_ne_lat=?, bounds_ne_lng=?, default_tile=?
        WHERE id=?");
      $st->bind_param(
        'ssddiiiddddsi',
        $name, $slug, $center_lat, $center_lng, $default_zoom, $min_zoom, $max_zoom,
        $bsw_lat, $bsw_lng, $bne_lat, $bne_lng, $default_tile, $id
      );
      $st->execute(); $st->close();
      hg_update_pretty_id_if_exists($link, 'dim_maps', $id, $name);
    } else {
      // INSERT
      $st = $link->prepare("INSERT INTO dim_maps
       (name,slug,center_lat,center_lng,default_zoom,min_zoom,max_zoom,
        bounds_sw_lat,bounds_sw_lng,bounds_ne_lat,bounds_ne_lng,default_tile)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
      $st->bind_param(
        'ssddiiidddds',
        $name, $slug, $center_lat, $center_lng, $default_zoom, $min_zoom, $max_zoom,
        $bsw_lat, $bsw_lng, $bne_lat, $bne_lng, $default_tile
      );
      $st->execute(); $id = $st->insert_id; $st->close();
      hg_update_pretty_id_if_exists($link, 'dim_maps', $id, $name);
    }
    ok(['id'=>$id]);
  }

  if ($a === 'delete_map' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) jerr("ID inválido");
    // Aviso: ON DELETE CASCADE en fk_pois_map eliminará sus POIs y Áreas
    $st = $link->prepare("DELETE FROM dim_maps WHERE id=?");
    $st->bind_param('i',$id); $st->execute(); $st->close();
    ok();
  }

  // --- Guardar categoría (crear/editar)
  if ($a === 'save_cat' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    if ($name==='') jerr("El nombre es obligatorio.");
    $slug = trim($_POST['slug'] ?? '');
    $slug = $slug !== '' ? slugify($slug) : slugify($name);
    $color_hex = trim($_POST['color_hex'] ?? '#95a5a6');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color_hex)) $color_hex = '#95a5a6';
    $icon = trim($_POST['icon'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);

    if ($id>0) {
      $st = $link->prepare("UPDATE dim_map_categories SET name=?, slug=?, color_hex=?, icon=?, sort_order=? WHERE id=?");
      $st->bind_param('ssssii', $name,$slug,$color_hex,$icon,$sort_order,$id);
      $st->execute(); $st->close();
      hg_update_pretty_id_if_exists($link, 'dim_map_categories', $id, $name);
    } else {
      $st = $link->prepare("INSERT INTO dim_map_categories (name,slug,color_hex,icon,sort_order) VALUES (?,?,?,?,?)");
      $st->bind_param('ssssi', $name,$slug,$color_hex,$icon,$sort_order);
      $st->execute(); $id = $st->insert_id; $st->close();
      hg_update_pretty_id_if_exists($link, 'dim_map_categories', $id, $name);
    }
    ok(['id'=>$id]);
  }

  if ($a === 'delete_cat' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) jerr("ID inválido");

    // Bloquear borrado si está en uso por algún POI o ÁREA
    $totalInUse = 0;

    $st = $link->prepare("SELECT COUNT(*) AS n FROM fact_map_pois WHERE category_id=?");
    $st->bind_param('i',$id); $st->execute();
    $n1 = ($st->get_result()->fetch_assoc()['n'] ?? 0); $st->close();

    if ($link->query("SHOW COLUMNS FROM fact_map_areas LIKE 'category_id'")->num_rows > 0) {
      $st = $link->prepare("SELECT COUNT(*) AS n FROM fact_map_areas WHERE category_id=?");
      $st->bind_param('i',$id); $st->execute();
      $n2 = ($st->get_result()->fetch_assoc()['n'] ?? 0); $st->close();
      $totalInUse = (int)$n1 + (int)$n2;
    } else {
      $totalInUse = (int)$n1;
    }

    if ($totalInUse>0) jerr("No se puede borrar: hay $totalInUse elemento(s) usando esta categoría.");
    $st = $link->prepare("DELETE FROM dim_map_categories WHERE id=?");
    $st->bind_param('i',$id); $st->execute(); $st->close();
    ok();
  }

  // --- Guardar POI (crear/editar)
  if ($a === 'save_poi' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $map_id = (int)($_POST['map_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $thumbnail = trim($_POST['thumbnail'] ?? '');
    $latitude = (float)($_POST['latitude'] ?? 0);
    $longitude = (float)($_POST['longitude'] ?? 0);
    if ($name==='') jerr("El nombre es obligatorio.");
    if ($map_id<=0) jerr("Selecciona un mapa.");
    if ($category_id<=0) jerr("Selecciona una categoría.");

    if ($id>0) {
      $st = $link->prepare("UPDATE fact_map_pois
                            SET name=?, map_id=?, category_id=?, description=?, thumbnail=?, latitude=?, longitude=?
                            WHERE id=?");
      $st->bind_param('siissddi', $name,$map_id,$category_id,$description,$thumbnail,$latitude,$longitude,$id);
      $st->execute(); $st->close();
      hg_update_pretty_id_if_exists($link, 'fact_map_pois', $id, $name);
    } else {
      $st = $link->prepare("INSERT INTO fact_map_pois
                            (name,map_id,category_id,description,thumbnail,latitude,longitude)
                            VALUES (?,?,?,?,?,?,?)");
      $st->bind_param('siissdd', $name,$map_id,$category_id,$description,$thumbnail,$latitude,$longitude);
      $st->execute(); $id = $st->insert_id; $st->close();
      hg_update_pretty_id_if_exists($link, 'fact_map_pois', $id, $name);
    }
    ok(['id'=>$id]);
  }

  if ($a === 'delete_poi' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) jerr("ID inválido");
    $st = $link->prepare("DELETE FROM fact_map_pois WHERE id=?");
    $st->bind_param('i',$id); $st->execute(); $st->close();
    ok();
  }

  /* ----------- ÁREAS: crear/editar/borrar ----------- */
  if ($a === 'save_area' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $map_id = (int)($_POST['map_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $color_hex = trim($_POST['color_hex'] ?? '#2ecc71');
    $fill_opacity = isset($_POST['fill_opacity']) ? (float)$_POST['fill_opacity'] : 0.35;
    $stroke_color = trim($_POST['stroke_color'] ?? '#27ae60');
    $stroke_weight = isset($_POST['stroke_weight']) ? (int)$_POST['stroke_weight'] : 2;
    $geometry_json = trim($_POST['geometry_json'] ?? '');

    if ($name==='') jerr("El nombre del área es obligatorio.");
    if ($map_id<=0) jerr("Selecciona un mapa.");
    if ($geometry_json==='') jerr("Dibuja el polígono del área.");

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color_hex))    $color_hex = '#2ecc71';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $stroke_color)) $stroke_color = '#27ae60';
    $fill_opacity = max(0, min(1, $fill_opacity));
    $stroke_weight = max(0, min(10, $stroke_weight));

    // Validar que geometry_json sea JSON válido
    $gj = json_decode($geometry_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) jerr("GeoJSON inválido: ".json_last_error_msg());

    // Normalizar: asegurar Feature + properties de estilo
    // Si recibimos solo "geometry", envolver en Feature
    if (isset($gj['type']) && $gj['type']==='FeatureCollection') {
      $gj = $gj['features'][0] ?? null;
      if (!$gj) jerr("GeoJSON inválido: FeatureCollection vacía");
    }
    if (!isset($gj['type']) || $gj['type']!=='Feature') {
      // Asumimos que $gj es un objeto geometry
      $gj = [
        'type' => 'Feature',
        'properties' => [],
        'geometry' => $gj
      ];
    }
    if (!isset($gj['properties']) || !is_array($gj['properties'])) $gj['properties'] = [];
    // Estilo en properties (Leaflet friendly)
    $gj['properties']['fillColor']   = $color_hex;
    $gj['properties']['fillOpacity'] = $fill_opacity;
    $gj['properties']['color']       = $stroke_color;
    $gj['properties']['weight']      = $stroke_weight;

    $geometry_final = json_encode($gj, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if ($geometry_final === false) jerr("Error serializando GeoJSON");

    if ($id>0) {
      $st = $link->prepare("UPDATE fact_map_areas
                            SET name=?, map_id=?, description=?, color_hex=?, geometry=?
                            WHERE id=?");
      $st->bind_param('sisssi', $name,$map_id,$description,$color_hex,$geometry_final,$id);
      $st->execute(); $st->close();
      hg_update_pretty_id_if_exists($link, 'fact_map_areas', $id, $name);
    } else {
      $st = $link->prepare("INSERT INTO fact_map_areas
                            (name,map_id,description,color_hex,geometry)
                            VALUES (?,?,?,?,?)");
      $st->bind_param('sisss', $name,$map_id,$description,$color_hex,$geometry_final);
      $st->execute(); $id = $st->insert_id; $st->close();
      hg_update_pretty_id_if_exists($link, 'fact_map_areas', $id, $name);
    }
    ok(['id'=>$id]);
  }

  if ($a === 'delete_area' && $_SERVER['REQUEST_METHOD']==='POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) jerr("ID inválido");
    $st = $link->prepare("DELETE FROM fact_map_areas WHERE id=?");
    $st->bind_param('i',$id); $st->execute(); $st->close();
    ok();
  }

  jerr("Acción no válida", 404);
}

/* ---------------------------------------------------------
   Render normal (no-AJAX): cargamos datos iniciales para JS
   --------------------------------------------------------- */
$maps = [];
$m = $link->query("SELECT id,name,slug,center_lat,center_lng,default_zoom,min_zoom,max_zoom,
                          bounds_sw_lat,bounds_sw_lng,bounds_ne_lat,bounds_ne_lng,default_tile,
                          created_at,updated_at
                   FROM dim_maps ORDER BY name");
while ($row = $m->fetch_assoc()) $maps[] = $row;

$cats = [];
$c = $link->query("SELECT id,name,slug,color_hex,icon,sort_order,created_at,updated_at
                   FROM dim_map_categories ORDER BY sort_order, name");
while ($row = $c->fetch_assoc()) $cats[] = $row;

$pois = [];
$p = $link->query("SELECT p.id, p.name, p.description, p.map_id, m.name AS map_name,
                          p.category_id, c.name AS category_name,
                          p.thumbnail, p.latitude, p.longitude,
                          p.created_at, p.updated_at
                   FROM fact_map_pois p
                   JOIN dim_maps m ON m.id=p.map_id
                   JOIN dim_map_categories c ON c.id=p.category_id
                   ORDER BY p.id DESC");
while ($row = $p->fetch_assoc()) $pois[] = $row;

// Áreas para render inicial (normalizar como en list_all)
$areas = [];
$aq = $link->query("SELECT a.id, a.name, a.map_id, m.name AS map_name,
                           a.description, a.color_hex,
                           a.geometry, a.created_at, a.updated_at
                    FROM fact_map_areas a
                    JOIN dim_maps m ON m.id=a.map_id
                    ORDER BY a.id DESC");
while ($row = $aq->fetch_assoc()) {
  $areas[] = area_row_to_payload($row);
}

$pageTitle2 = "Mapas, POIs y Áreas";
?>

<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.1.9.4.css">
<script src="/assets/vendor/leaflet/leaflet.1.9.4.js"></script>
<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($ADMIN_CSRF_TOKEN, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?= htmlspecialchars($adminHttpJs, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$adminHttpJsVer ?>"></script>

<div class="adm-clear-both"></div>
<h2>🗺️ Administración de Mapas & POIs & Áreas</h2>

<div class="bioTextData">
  <fieldset class="bioSeccion">
    <legend>&nbsp;POIs&nbsp;</legend>

    <div class="adm-row-toolbar">
      <button class="boton2" id="btnNewPoi">➕ Nuevo POI</button>
      <span>Filtro:</span>
      <input type="text" id="filterText" class="adm-w-180" placeholder="buscar por nombre...">
      <span>Mapa:</span>
      <select id="filterMap"><option value="">(todos)</option></select>
      <span>Categoría:</span>
      <select id="filterCat"><option value="">(todas)</option></select>
      <button class="boton2" id="btnRefresh">⟳ Recargar</button>
    </div>

    <table class="tabla-pj" id="poisTable">
      <thead>
        <tr class="pj-row-head">
          <th>ID</th>
          <th>Nombre</th>
          <th>Mapa</th>
          <th>Categoría</th>
          <th>Miniatura</th>
          <th>Lat</th>
          <th>Lng</th>
          <th>Creado</th>
          <th>Actualizado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </fieldset>

  <!-- ==================== ÁREAS ==================== -->
  <fieldset class="bioSeccion adm-mt-12">
    <legend>&nbsp;Áreas&nbsp;</legend>

    <div class="adm-row-toolbar">
      <button class="boton2" id="btnNewArea">➕ Nueva área</button>
      <span>Mapa:</span>
      <select id="filterAreaMap"><option value="">(todos)</option></select>
      <input type="text" id="filterAreaText" class="adm-w-180" placeholder="buscar por nombre...">
    </div>

    <table class="tabla-pj" id="areasTable">
      <thead>
        <tr class="pj-row-head">
          <th>ID</th>
          <th>Nombre</th>
          <th>Mapa</th>
          <th>Color</th>
          <th>Opacidad</th>
          <th>Stroke</th>
          <th>Vértices</th>
          <th>Creado</th>
          <th>Actualizado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </fieldset>

  <fieldset class="bioSeccion adm-mt-12">
    <legend>&nbsp;Mapas&nbsp;</legend>
    <div class="adm-row-toolbar">
      <button class="boton2" id="btnNewMap">➕ Nuevo mapa</button>
    </div>
    <table class="tabla-pj" id="mapsTable">
      <thead>
        <tr class="pj-row-head">
          <th>ID</th>
          <th>Nombre</th>
          <th>Slug</th>
          <th>Centro</th>
          <th>Zoom (def/min/max)</th>
          <th>Límites</th>
          <th>Tile</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </fieldset>

  <fieldset class="bioSeccion adm-mt-12">
    <legend>&nbsp;Categorías&nbsp;</legend>
    <div class="adm-row-toolbar">
      <button class="boton2" id="btnNewCat">➕ Nueva categoría</button>
    </div>
    <table class="tabla-pj" id="catsTable">
      <thead>
        <tr class="pj-row-head">
          <th>ID</th>
          <th>Nombre</th>
          <th>Slug</th>
          <th>Color</th>
          <th>Icono</th>
          <th>Orden</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </fieldset>
</div>

<!-- ===================== MODALES ===================== -->
<!-- Modal POI -->
<div id="modalPoi" class="popup-edit adm-hidden">
  <form id="formPoi" onsubmit="return savePoi(event)">
    <input type="hidden" name="id" id="poi_id">
    <h3 class="adm-mt-0">POI</h3>

    <label>Nombre</label>
    <input type="text" name="name" id="poi_name" required>

    <div class="adm-row-gap-6">
      <div class="adm-flex-1">
        <label>Mapa</label>
        <select name="map_id" id="poi_map_id" required></select>
      </div>
      <div class="adm-flex-1">
        <label>Categoría</label>
        <select name="category_id" id="poi_category_id" required></select>
      </div>
    </div>

    <label>Descripción</label>
    <textarea name="description" id="poi_description" rows="5"></textarea>

    <label>Miniatura (URL)</label>
    <input type="text" name="thumbnail" id="poi_thumbnail" placeholder="http(s)://...">

    <div class="adm-row-gap-6">
      <div class="adm-flex-1">
        <label>Latitud</label>
        <input type="text" name="latitude" id="poi_lat" required>
      </div>
      <div class="adm-flex-1">
        <label>Longitud</label>
        <input type="text" name="longitude" id="poi_lng" required>
      </div>
    </div>

    <div class="adm-my-8">
      <div id="poiPickerMap" class="adm-box-220"></div>
      <div class="adm-row-wrap-6-mt6">
        <button type="button" class="boton2" onclick="poiMapToCenter()">➕ Usar centro del mapa</button>
        <button type="button" class="boton2" onclick="poiFitToBounds()">⬛ Ajustar a límites del mapa</button>
        <small class="adm-opacity-80">Consejo: haz clic en el mapa para fijar coordenadas del POI</small>
      </div>
    </div>

    <div class="adm-mt-10">
      <button class="boton2" type="submit">💾 Guardar</button>
      <button class="boton2" type="button" onclick="closeModal('modalPoi')">Cancelar</button>
    </div>
  </form>
</div>

<!-- Modal AREA -->
<div id="modalArea" class="popup-edit adm-hidden">
  <form id="formArea" onsubmit="return saveArea(event)">
    <input type="hidden" name="id" id="area_id">
    <h3 class="adm-mt-0">Área</h3>

    <label>Nombre</label>
    <input type="text" name="name" id="area_name" required>

    <div class="adm-row-gap-6">
      <div class="adm-flex-1">
        <label>Mapa</label>
        <select name="map_id" id="area_map_id" required></select>
      </div>
      <div class="adm-flex-1">
        <label>Color relleno</label>
        <input type="color" name="color_hex" id="area_color" value="#2ecc71">
      </div>
    </div>

    <div class="adm-row-gap-6">
      <div class="adm-flex-1">
        <label>Opacidad (0–1)</label>
        <input type="number" step="0.05" min="0" max="1" name="fill_opacity" id="area_fill_opacity" value="0.35">
      </div>
      <div class="adm-flex-1">
        <label>Stroke color</label>
        <input type="color" name="stroke_color" id="area_stroke_color" value="#27ae60">
      </div>
      <div class="adm-flex-1">
        <label>Stroke px</label>
        <input type="number" min="0" max="10" name="stroke_weight" id="area_stroke_weight" value="2">
      </div>
    </div>

    <label>Descripción</label>
    <textarea name="description" id="area_description" rows="4"></textarea>

    <div class="adm-my-8">
      <div id="areaPickerMap" class="adm-box-260"></div>
      <div class="adm-row-wrap-6-mt6-center">
        <button type="button" class="boton2" id="btnAreaStart">🖊️ Añadir vértices</button>
        <button type="button" class="boton2" id="btnAreaUndo">↩️ Deshacer vértice</button>
        <button type="button" class="boton2" id="btnAreaClose">✅ Cerrar polígono</button>
        <button type="button" class="boton2" id="btnAreaClear">🧹 Limpiar</button>
        <label class="adm-ml-8"><input type="checkbox" id="area_json_toggle"> Editar GeoJSON</label>
      </div>
    </div>

    <textarea name="geometry_json" id="area_geometry_json" rows="6" class="adm-hidden-mono"></textarea>

    <div class="adm-mt-10">
      <button class="boton2" type="submit">💾 Guardar</button>
      <button class="boton2" type="button" onclick="closeModal('modalArea')">Cancelar</button>
    </div>
  </form>
</div>

<!-- Modal MAP -->
<div id="modalMap" class="popup-edit adm-hidden">
  <form id="formMap" onsubmit="return saveMap(event)">
    <input type="hidden" name="id" id="map_id">
    <h3 class="adm-mt-0">Mapa</h3>

    <label>Nombre</label>
    <input type="text" name="name" id="map_name" required oninput="syncSlug(this,'map_slug')">

    <label>Slug</label>
    <input type="text" name="slug" id="map_slug" placeholder="(auto)">

    <div class="adm-row-gap-6">
      <div class="adm-flex-1">
        <label>Centro lat</label>
        <input type="text" name="center_lat" id="map_center_lat" required>
      </div>
      <div class="adm-flex-1">
        <label>Centro lng</label>
        <input type="text" name="center_lng" id="map_center_lng" required>
      </div>
      <div class="adm-flex-1">
        <label>Zoom por defecto</label>
        <input type="number" name="default_zoom" id="map_default_zoom" required min="1" max="22">
      </div>
    </div>

    <div class="adm-row-gap-6">
      <div class="adm-flex-1">
        <label>Min zoom</label>
        <input type="number" name="min_zoom" id="map_min_zoom" min="1" max="22">
      </div>
      <div class="adm-flex-1">
        <label>Max zoom</label>
        <input type="number" name="max_zoom" id="map_max_zoom" min="1" max="22">
      </div>
      <div class="adm-flex-1">
        <label>Tile</label>
        <select name="default_tile" id="map_default_tile">
          <option value="carto-dark">carto-dark</option>
          <option value="osm-standard">osm-standard</option>
          <option value="esri-gray">esri-gray</option>
        </select>
      </div>
    </div>

    <div class="adm-mt-6">
      <div id="mapEditMap" class="adm-box-220"></div>
      <div class="adm-row-wrap-6-mt6">
        <button type="button" class="boton2" onclick="useViewAsCenter()">🎯 Usar vista como centro</button>
        <button type="button" class="boton2" onclick="useViewAsBounds()">⬛ Usar vista como límites</button>
      </div>
    </div>

    <div class="adm-row-gap-6-mt6">
      <div class="adm-flex-1">
        <label>SW lat</label>
        <input type="text" name="bounds_sw_lat" id="map_sw_lat">
      </div>
      <div class="adm-flex-1">
        <label>SW lng</label>
        <input type="text" name="bounds_sw_lng" id="map_sw_lng">
      </div>
      <div class="adm-flex-1">
        <label>NE lat</label>
        <input type="text" name="bounds_ne_lat" id="map_ne_lat">
      </div>
      <div class="adm-flex-1">
        <label>NE lng</label>
        <input type="text" name="bounds_ne_lng" id="map_ne_lng">
      </div>
    </div>

    <div class="adm-mt-10">
      <button class="boton2" type="submit">💾 Guardar</button>
      <button class="boton2" type="button" onclick="closeModal('modalMap')">Cancelar</button>
    </div>
  </form>
</div>

<!-- Modal CAT -->
<div id="modalCat" class="popup-edit adm-hidden">
  <form id="formCat" onsubmit="return saveCat(event)">
    <input type="hidden" name="id" id="cat_id">
    <h3 class="adm-mt-0">Categoría</h3>

    <label>Nombre</label>
    <input type="text" name="name" id="cat_name" required oninput="syncSlug(this,'cat_slug')">

    <label>Slug</label>
    <input type="text" name="slug" id="cat_slug" placeholder="(auto)">

    <div class="adm-row-gap-6">
      <div class="adm-flex-1">
        <label>Color</label>
        <input type="color" name="color_hex" id="cat_color" value="#95a5a6">
      </div>
      <div class="adm-flex-2">
        <label>Icono (URL opcional)</label>
        <input type="text" name="icon" id="cat_icon" placeholder="http(s)://...">
      </div>
      <div class="adm-flex-1">
        <label>Orden</label>
        <input type="number" name="sort_order" id="cat_sort" value="0">
      </div>
    </div>

    <div class="adm-mt-10">
      <button class="boton2" type="submit">💾 Guardar</button>
      <button class="boton2" type="button" onclick="closeModal('modalCat')">Cancelar</button>
    </div>
  </form>
</div>

<!-- ===================== JS ===================== -->
<script>
// Datos iniciales desde PHP
const MAPS = <?= json_encode($maps, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
const CATS = <?= json_encode($cats, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
let POIS  = <?= json_encode($pois, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
let AREAS = <?= json_encode($areas, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;

// ------- Utils -------
function $(q){ return document.querySelector(q); }
function ce(tag, prop={}){ const el=document.createElement(tag); Object.assign(el,prop); return el; }
function esc(s){ return (s??'').toString().replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;' }[m])); }
function slugify(s){ return (s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'') }
function syncSlug(src, id){ const t = document.getElementById(id); if (t && (t.value.trim()==='' || t.dataset.autofill==='1')) { t.value = slugify(src.value); t.dataset.autofill='1'; } }
function closeModal(id){ document.getElementById(id).style.display='none'; }
function openModal(id){ document.getElementById(id).style.display='block'; }
function toInt(v){ const n = parseInt(v, 10); return Number.isFinite(n) ? n : 0; }

// Base URL (soporta /talim?s=admin_pois y ?p=admin_pois)
const QS = new URLSearchParams(location.search);
const BASE = (QS.get('s') === 'admin_pois')
  ? `?p=${QS.get('p')||'talim'}&s=admin_pois`
  : `?p=admin_pois`;

// ------- Rellenar selects de filtro / formularios -------
function fillMapSelect(sel, includeBlank=false){
  sel.innerHTML = includeBlank ? '<option value="">(todos)</option>' : '';
  MAPS.forEach(m => {
    const opt = document.createElement('option');
    opt.value = m.id; opt.textContent = m.name;
    sel.appendChild(opt);
  });
}
function fillCatSelect(sel, includeBlank=false){
  sel.innerHTML = includeBlank ? '<option value="">(todas)</option>' : '';
  CATS.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.id; opt.textContent = c.name;
    sel.appendChild(opt);
  });
}
fillMapSelect(document.getElementById('filterMap'), true);
fillCatSelect(document.getElementById('filterCat'), true);

// ------- Tabla POIs -------
function renderPois(){
  const q = $('#filterText').value.trim().toLowerCase();
  const mapId = $('#filterMap').value;
  const catId = $('#filterCat').value;

  const tbody = $('#poisTable tbody');
  tbody.innerHTML='';

  POIS
   .filter(p => (q==='' || (p.name||'').toLowerCase().includes(q)))
   .filter(p => (mapId==='' || p.map_id==parseInt(mapId)))
   .filter(p => (catId==='' || p.category_id==parseInt(catId)))
   .forEach(p => {
      const tr = ce('tr', { className:'pj-row' });
      tr.innerHTML = `
        <td>${p.id}</td>
        <td>${esc(p.name)}</td>
        <td>${esc(p.map_name)}</td>
        <td>${esc(p.category_name)}</td>
        <td>${p.thumbnail ? `<img src="${esc(p.thumbnail)}" alt="" class="adm-thumb-26">` : ''}</td>
        <td>${p.latitude}</td>
        <td>${p.longitude}</td>
        <td>${esc(p.created_at||'')}</td>
        <td>${esc(p.updated_at||'')}</td>
        <td>
          <button class="boton2" onclick="openPoi(${p.id})">✏️</button>
          <button class="boton2 adm-btn-danger" onclick="delPoi(${p.id})">🗑️</button>
        </td>`;
      tbody.appendChild(tr);
   });
  applySwatchBackgrounds(tbody);
}
renderPois();

$('#filterText').addEventListener('input', renderPois);
$('#filterMap').addEventListener('change', renderPois);
$('#filterCat').addEventListener('change', renderPois);

// ----------- Helper robusto para JSON (recorta HTML accidental) -----------
async function fetchJson(url, opts={}) {
  if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
    try {
      const requestOpts = Object.assign({ method: 'GET' }, opts || {});
      return await window.HGAdminHttp.request(url, requestOpts);
    } catch (err) {
      if (err && err.payload) return err.payload;
      throw err;
    }
  }

  if (opts && opts.method && String(opts.method).toUpperCase() === 'POST' && opts.body instanceof FormData) {
    const hasCsrf = opts.body.has('csrf');
    if (!hasCsrf && window.ADMIN_CSRF_TOKEN) opts.body.set('csrf', window.ADMIN_CSRF_TOKEN);
  }

  const res = await fetch(url, opts);
  const text = await res.text();

  try { return JSON.parse(text); } catch(_e) {}

  // Fallback legacy: intentar recortar ruido HTML/warnings alrededor del JSON
  let start = text.indexOf('{\"ok\"');
  if (start === -1) {
    const iBrace = text.indexOf('{');
    const iBracket = text.indexOf('[');
    if (iBrace !== -1 && iBracket !== -1) start = Math.min(iBrace, iBracket);
    else start = (iBrace !== -1) ? iBrace : iBracket;
  }
  if (start === -1) throw new Error('No se encontro JSON en la respuesta del servidor');

  const endObj = text.lastIndexOf('}');
  const endArr = text.lastIndexOf(']');
  const end = Math.max(endObj, endArr);
  if (end > start) {
    const slice = text.slice(start, end + 1);
    return JSON.parse(slice);
  }
  return JSON.parse(text.slice(start));
}
// Botón Recargar
$('#btnRefresh').addEventListener('click', async ()=>{
  try {
    const json = await fetchJson(`${BASE}&ajax=list_all`);
    if (json && json.ok){
      MAPS.splice(0, MAPS.length, ...json.maps);
      CATS.splice(0, CATS.length, ...json.cats);
      POIS = json.pois;
      AREAS = json.areas || [];
      fillMapSelect($('#filterMap'), true);
      fillCatSelect($('#filterCat'), true);
      fillMapSelect($('#filterAreaMap'), true);
      renderPois(); renderMaps(); renderCats(); renderAreas();
    } else {
      alert(json?.error || 'Error al recargar');
    }
  } catch(e) {
    console.error(e);
    alert('Error al recargar');
  }
});

// ------- POI: modal y Leaflet picker -------
let poiMap, poiMarker;
function ensurePoiLeaflet(){
  if (poiMap) { poiMap.invalidateSize(); return; }
  poiMap = L.map('poiPickerMap', { zoomControl:true, attributionControl:true }).setView([0,0], 2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19, subdomains:['a','b','c']
  }).addTo(poiMap);
  poiMap.on('click', e => {
    const {lat, lng} = e.latlng;
    $('#poi_lat').value = lat.toFixed(6);
    $('#poi_lng').value = lng.toFixed(6);
    if (!poiMarker) poiMarker = L.marker(e.latlng).addTo(poiMap);
    poiMarker.setLatLng(e.latlng);
  });
}

function poiFitToBounds(){
  const mid = parseInt($('#poi_map_id').value||'0',10);
  const m = MAPS.find(x=> toInt(x.id) === mid);
  if (!m) return;
  if (m.bounds_sw_lat!=null && m.bounds_ne_lat!=null && m.bounds_sw_lng!=null && m.bounds_ne_lng!=null) {
    const b = L.latLngBounds([ [parseFloat(m.bounds_sw_lat), parseFloat(m.bounds_sw_lng)],
                               [parseFloat(m.bounds_ne_lat), parseFloat(m.bounds_ne_lng)] ]);
    poiMap.fitBounds(b);
  } else {
    poiMap.setView([parseFloat(m.center_lat), parseFloat(m.center_lng)], Math.max(6, parseInt(m.default_zoom||8)));
  }
}
function poiMapToCenter(){
  const mid = parseInt($('#poi_map_id').value||'0',10);
  const m = MAPS.find(x=> toInt(x.id) === mid);
  if (!m) return;
  const lat = parseFloat(m.center_lat), lng=parseFloat(m.center_lng);
  $('#poi_lat').value = lat.toFixed(6);
  $('#poi_lng').value = lng.toFixed(6);
  if (!poiMarker) poiMarker = L.marker([lat,lng]).addTo(poiMap);
  poiMarker.setLatLng([lat,lng]); poiMap.setView([lat,lng], Math.max(10, parseInt(m.default_zoom||8)));
}

function openPoi(id){
  id = toInt(id);
  fillMapSelect($('#poi_map_id'), false);
  fillCatSelect($('#poi_category_id'), false);
  ensurePoiLeaflet();

  if (id > 0) { // editar
    const p = POIS.find(x => toInt(x.id) === id);
    if (!p) { alert(`POI ${id} no encontrado. Prueba a recargar.`); return; }

    $('#poi_id').value = p.id;
    $('#poi_name').value = p.name || '';
    $('#poi_map_id').value = p.map_id;
    $('#poi_category_id').value = p.category_id;
    $('#poi_description').value = p.description || '';
    $('#poi_thumbnail').value = p.thumbnail || '';
    $('#poi_lat').value = p.latitude;
    $('#poi_lng').value = p.longitude;

    const ll = [parseFloat(p.latitude), parseFloat(p.longitude)];
    if (!poiMarker) poiMarker = L.marker(ll).addTo(poiMap);
    poiMarker.setLatLng(ll); poiMap.setView(ll, 14);

  } else { // nuevo
    $('#poi_id').value = '';
    $('#poi_name').value = '';
    $('#poi_map_id').value = MAPS[0] ? MAPS[0].id : '';
    $('#poi_category_id').value = CATS[0] ? CATS[0].id : '';
    $('#poi_description').value = '';
    $('#poi_thumbnail').value = '';
    $('#poi_lat').value = '';
    $('#poi_lng').value = '';
    poiMarker = null;
    poiFitToBounds();
  }

  $('#poi_map_id').onchange = poiFitToBounds;

  openModal('modalPoi');
  setTimeout(()=>poiMap.invalidateSize(), 100);
}
$('#btnNewPoi').addEventListener('click', ()=>openPoi(0));

async function savePoi(ev){
  ev.preventDefault();
  const fd = new FormData($('#formPoi'));
  try {
    const json = await fetchJson(`${BASE}&ajax=save_poi`, { method:'POST', body:fd });
    if (!json.ok) { alert(json.error||'Error'); return false; }
    $('#btnRefresh').click();
    closeModal('modalPoi');
  } catch(e){
    console.error(e); alert('Error guardando el POI');
  }
  return false;
}
async function delPoi(id){
  if (!confirm('¿Eliminar este POI?')) return;
  const fd = new FormData(); fd.append('id', id);
  try {
    const json = await fetchJson(`${BASE}&ajax=delete_poi`, { method:'POST', body:fd });
    if (!json.ok) { alert(json.error||'Error'); return; }
    $('#btnRefresh').click();
  } catch(e){ console.error(e); alert('Error eliminando el POI'); }
}

// ------- ÁREAS -------
function verticesCountFromGeo(gj){
  try{
    const o = (typeof gj === 'string') ? JSON.parse(gj) : gj;
    let poly = o;
    if (o.type === 'Feature') poly = o.geometry;
    if (o.type === 'FeatureCollection') poly = o.features?.[0]?.geometry;
    const ring = (poly?.type === 'Polygon') ? poly.coordinates?.[0] : null;
    return Array.isArray(ring) ? ring.length : 0;
  } catch(e){ return 0; }
}

function applySwatchBackgrounds(root){
  const scope = root || document;
  scope.querySelectorAll('[data-bg]').forEach(el => {
    const bg = el.getAttribute('data-bg') || '';
    if (bg) el.style.background = bg;
  });
}

function renderAreas(){
  const tbody = $('#areasTable tbody'); tbody.innerHTML='';
  const q = ($('#filterAreaText').value||'').toLowerCase().trim();
  const mapId = $('#filterAreaMap').value;

  (AREAS||[])
    .filter(a => (q==='' || (a.name||'').toLowerCase().includes(q)))
    .filter(a => (mapId==='' || a.map_id==parseInt(mapId)))
    .forEach(a=>{
      const tr = ce('tr',{className:'pj-row'});
      const verts = verticesCountFromGeo(a.geometry_json);
      tr.innerHTML = `
        <td>${a.id}</td>
        <td>${esc(a.name)}</td>
        <td>${esc(a.map_name||'')}</td>
        <td><span class="adm-swatch-18" data-bg="${esc(a.color_hex||'#2ecc71')}"></span> ${esc(a.color_hex||'#2ecc71')}</td>
        <td>${a.fill_opacity ?? ''}</td>
        <td><span class="adm-line-18x2" data-bg="${esc(a.stroke_color||'#27ae60')}"></span> ${esc(a.stroke_color||'#27ae60')} × ${a.stroke_weight ?? 2}px</td>
        <td>${verts}</td>
        <td>${esc(a.created_at||'')}</td>
        <td>${esc(a.updated_at||'')}</td>
        <td>
          <button class="boton2" onclick="openArea(${a.id})">✏️</button>
          <button class="boton2 adm-btn-danger" onclick="delArea(${a.id})">🗑️</button>
        </td>`;
      tbody.appendChild(tr);
    });
  applySwatchBackgrounds(tbody);
}
fillMapSelect(document.getElementById('filterAreaMap'), true);
renderAreas();
$('#filterAreaMap').addEventListener('change', renderAreas);
$('#filterAreaText').addEventListener('input', renderAreas);

// Leaflet en modal de áreas (sin plugins externos)
let areaMap, areaPolygon, areaLatLngs = [], areaDrawing = false;
function ensureAreaLeaflet(){
  if (areaMap) { areaMap.invalidateSize(); return; }
  areaMap = L.map('areaPickerMap', { zoomControl:true }).setView([0,0], 2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19, subdomains:['a','b','c']
  }).addTo(areaMap);

  areaMap.on('click', e=>{
    if (!areaDrawing) return;
    areaLatLngs.push([e.latlng.lat, e.latlng.lng]);
    redrawAreaPolygon();
  });
}

function areaFitToSelectedMap(){
  const mid = parseInt($('#area_map_id').value||'0',10);
  const m = MAPS.find(x=> toInt(x.id) === mid);
  if (!m || !areaMap) return;
  if (m.bounds_sw_lat!=null && m.bounds_ne_lat!=null && m.bounds_sw_lng!=null && m.bounds_ne_lng!=null) {
    const b = L.latLngBounds(
      [parseFloat(m.bounds_sw_lat), parseFloat(m.bounds_sw_lng)],
      [parseFloat(m.bounds_ne_lat), parseFloat(m.bounds_ne_lng)]
    );
    areaMap.fitBounds(b);
  } else {
    areaMap.setView([parseFloat(m.center_lat), parseFloat(m.center_lng)], Math.max(6, parseInt(m.default_zoom||8)));
  }
}

function redrawAreaPolygon(){
  const color = $('#area_color').value || '#2ecc71';
  const stroke = $('#area_stroke_color').value || '#27ae60';
  const weight = parseInt($('#area_stroke_weight').value||'2', 10);
  const fillOpacity = parseFloat($('#area_fill_opacity').value||'0.35');

  if (areaPolygon) { areaMap.removeLayer(areaPolygon); areaPolygon = null; }
  if (areaLatLngs.length >= 2) {
    areaPolygon = L.polygon(areaLatLngs, { color: stroke, weight, fillColor: color, fillOpacity });
    areaPolygon.addTo(areaMap);
  }
  // Actualiza el JSON si no está en modo edición manual
  if (!$('#area_json_toggle').checked){
    const gj = latlngsToGeoJSON(areaLatLngs);
    $('#area_geometry_json').value = JSON.stringify(gj);
  }
}

function latlngsToGeoJSON(latlngs){
  // Asegura anillo cerrado y formato [lng,lat]
  const ring = latlngs.map(([lat,lng])=>[lng,lat]);
  if (ring.length >= 3) {
    const first = ring[0], last = ring[ring.length-1];
    if (first[0]!==last[0] || first[1]!==last[1]) ring.push([...first]);
  }
  return {
    type:'Feature',
    properties:{},
    geometry:{ type:'Polygon', coordinates:[ ring ] }
  };
}

function geoJSONToLatLngs(text){
  try{
    const o = (typeof text === 'string') ? JSON.parse(text) : text;
    let g = o;
    if (o.type === 'Feature') g = o.geometry;
    if (o.type === 'FeatureCollection') g = o.features?.[0]?.geometry;
    if (!g || g.type!=='Polygon') return [];
    const ring = g.coordinates?.[0] || [];
    // [lng,lat] -> [lat,lng] y quitar cierre duplicado
    const arr = ring.map(([lng,lat])=>[lat,lng]);
    if (arr.length>=2){
      const a0 = arr[0], al = arr[arr.length-1];
      if (a0[0]===al[0] && a0[1]===al[1]) arr.pop();
    }
    return arr;
  }catch(e){ return []; }
}

function openArea(id){
  id = toInt(id);
  fillMapSelect($('#area_map_id'), false);
  ensureAreaLeaflet();

  // Reset estado de dibujo
  areaDrawing = false;
  areaLatLngs = [];
  if (areaPolygon) { areaMap.removeLayer(areaPolygon); areaPolygon=null; }

  $('#area_json_toggle').checked = false;
  $('#area_geometry_json').style.display = 'none';

  if (id > 0){
    const a = AREAS.find(x=> toInt(x.id)===id);
    if (!a) { alert(`Área ${id} no encontrada. Recarga.`); return; }

    $('#area_id').value = a.id;
    $('#area_name').value = a.name || '';
    $('#area_map_id').value = a.map_id;
    $('#area_description').value = a.description || '';
    $('#area_color').value = a.color_hex || '#2ecc71';
    $('#area_fill_opacity').value = a.fill_opacity ?? 0.35;
    $('#area_stroke_color').value = a.stroke_color || '#27ae60';
    $('#area_stroke_weight').value = a.stroke_weight ?? 2;
    $('#area_geometry_json').value = a.geometry_json || '';

    // Cargar polígono en el mapa
    areaLatLngs = geoJSONToLatLngs(a.geometry_json || '[]');
    redrawAreaPolygon();
    if (areaPolygon) areaMap.fitBounds(areaPolygon.getBounds());

  } else {
    $('#area_id').value = '';
    $('#area_name').value = '';
    $('#area_map_id').value = MAPS[0] ? MAPS[0].id : '';
    $('#area_description').value = '';
    $('#area_color').value = '#2ecc71';
    $('#area_fill_opacity').value = 0.35;
    $('#area_stroke_color').value = '#27ae60';
    $('#area_stroke_weight').value = 2;
    $('#area_geometry_json').value = '';
    areaFitToSelectedMap();
  }

  $('#area_map_id').onchange = areaFitToSelectedMap;

  // Botones del editor
  $('#btnAreaStart').onclick = ()=>{ areaDrawing = true; };
  $('#btnAreaUndo').onclick = ()=>{
    if (areaLatLngs.length>0){ areaLatLngs.pop(); redrawAreaPolygon(); }
  };
  $('#btnAreaClose').onclick = ()=>{
    areaDrawing = false;
    redrawAreaPolygon(); // asegura cierre en JSON
    if (areaPolygon) areaMap.fitBounds(areaPolygon.getBounds());
  };
  $('#btnAreaClear').onclick = ()=>{
    areaDrawing = false; areaLatLngs = []; redrawAreaPolygon(); $('#area_geometry_json').value='';
  };
  $('#area_json_toggle').onchange = (e)=>{
    const on = e.target.checked;
    const ta = $('#area_geometry_json');
    ta.style.display = on ? 'block' : 'none';
    if (on){
      // Si no hay JSON, genera a partir de los vértices actuales
      if (!ta.value.trim() && areaLatLngs.length>=3) ta.value = JSON.stringify(latlngsToGeoJSON(areaLatLngs), null, 2);
    } else {
      // Al salir de modo JSON, intentar parsear y pintar
      const arr = geoJSONToLatLngs(ta.value||'[]');
      if (arr.length>=3){ areaLatLngs = arr; redrawAreaPolygon(); }
    }
  };

  openModal('modalArea');
  setTimeout(()=>areaMap.invalidateSize(), 100);
}
$('#btnNewArea').addEventListener('click', ()=>openArea(0));

async function saveArea(ev){
  ev.preventDefault();
  // Si no está en modo JSON, garantizar que hay JSON en el textarea
  if (!$('#area_json_toggle').checked){
    if (areaLatLngs.length < 3){ alert('Dibuja al menos 3 vértices.'); return false; }
    const gj = latlngsToGeoJSON(areaLatLngs);
    $('#area_geometry_json').value = JSON.stringify(gj);
  }
  const fd = new FormData($('#formArea'));
  try {
    const json = await fetchJson(`${BASE}&ajax=save_area`, { method:'POST', body:fd });
    if (!json.ok){ alert(json.error||'Error'); return false; }
    const ref = await fetchJson(`${BASE}&ajax=list_all`);
    if (ref.ok){
      AREAS = ref.areas || [];
      renderAreas();
      fillMapSelect($('#filterAreaMap'), true);
    }
    closeModal('modalArea');
  } catch(e){ console.error(e); alert('Error guardando el área'); }
  return false;
}

async function delArea(id){
  if (!confirm('¿Eliminar esta área?')) return;
  const fd = new FormData(); fd.append('id', id);
  try{
    const json = await fetchJson(`${BASE}&ajax=delete_area`, { method:'POST', body:fd });
    if (!json.ok){ alert(json.error||'Error'); return; }
    $('#btnRefresh').click();
  }catch(e){ console.error(e); alert('Error eliminando el área'); }
}

// ------- Tabla MAPS -------
function renderMaps(){
  const tbody = $('#mapsTable tbody'); tbody.innerHTML='';
  MAPS.forEach(m=>{
    const bounds = (m.bounds_sw_lat!=null && m.bounds_ne_lat!=null && m.bounds_sw_lng!=null && m.bounds_ne_lng!=null)
      ? `[${m.bounds_sw_lat}, ${m.bounds_sw_lng}] → [${m.bounds_ne_lat}, ${m.bounds_ne_lng}]` : '(sin límites)';
    const tr = ce('tr',{className:'pj-row'});
    tr.innerHTML = `
      <td>${m.id}</td>
      <td>${esc(m.name)}</td>
      <td>${esc(m.slug)}</td>
      <td>${m.center_lat}, ${m.center_lng}</td>
      <td>${m.default_zoom} / ${m.min_zoom??''} / ${m.max_zoom??''}</td>
      <td>${bounds}</td>
      <td>${esc(m.default_tile||'')}</td>
      <td>
        <button class="boton2" onclick="openMap(${m.id})">✏️</button>
        <button class="boton2 adm-btn-danger" onclick="delMap(${m.id})">🗑️</button>
      </td>`;
    tbody.appendChild(tr);
  });
}
renderMaps();

let mapEdit, mapRect;
function ensureMapLeaflet(){
  if (mapEdit) { mapEdit.invalidateSize(); return; }
  mapEdit = L.map('mapEditMap', { zoomControl:true }).setView([0,0], 2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19, subdomains:['a','b','c']
  }).addTo(mapEdit);
}

function useViewAsCenter(){
  const c = mapEdit.getCenter();
  $('#map_center_lat').value = c.lat.toFixed(6);
  $('#map_center_lng').value = c.lng.toFixed(6);
  $('#map_default_zoom').value = mapEdit.getZoom();
}
function useViewAsBounds(){
  const b = mapEdit.getBounds();
  $('#map_sw_lat').value = b.getSouth().toFixed(6);
  $('#map_sw_lng').value = b.getWest().toFixed(6);
  $('#map_ne_lat').value = b.getNorth().toFixed(6);
  $('#map_ne_lng').value = b.getEast().toFixed(6);
  if (mapRect) mapEdit.removeLayer(mapRect);
  mapRect = L.rectangle(b, {color:'#00ffcc', weight:1}); mapRect.addTo(mapEdit);
}

function openMap(id){
  id = toInt(id);
  ensureMapLeaflet();

  if (id > 0){
    const m = MAPS.find(x => toInt(x.id) === id);
    if (!m) { alert(`Mapa ${id} no encontrado. Recarga.`); return; }

    $('#map_id').value = m.id;
    $('#map_name').value = m.name || '';
    $('#map_slug').value = m.slug || '';
    $('#map_center_lat').value = m.center_lat;
    $('#map_center_lng').value = m.center_lng;
    $('#map_default_zoom').value = m.default_zoom;
    $('#map_min_zoom').value = m.min_zoom ?? '';
    $('#map_max_zoom').value = m.max_zoom ?? '';
    $('#map_default_tile').value = m.default_tile || 'carto-dark';
    $('#map_sw_lat').value = m.bounds_sw_lat ?? '';
    $('#map_sw_lng').value = m.bounds_sw_lng ?? '';
    $('#map_ne_lat').value = m.bounds_ne_lat ?? '';
    $('#map_ne_lng').value = m.bounds_ne_lng ?? '';

    mapEdit.setView([parseFloat(m.center_lat), parseFloat(m.center_lng)], Math.max(6, parseInt(m.default_zoom||8)));
    if (m.bounds_sw_lat!=null && m.bounds_ne_lat!=null && m.bounds_sw_lng!=null && m.bounds_ne_lng!=null) {
      const b = L.latLngBounds([[m.bounds_sw_lat, m.bounds_sw_lng],[m.bounds_ne_lat, m.bounds_ne_lng]]);
      if (mapRect) mapEdit.removeLayer(mapRect);
      mapRect = L.rectangle(b, {color:'#00ffcc', weight:1}); mapRect.addTo(mapEdit);
    } else if (mapRect) { mapEdit.removeLayer(mapRect); mapRect=null; }
  } else {
    $('#map_id').value = '';
    $('#map_name').value = '';
    $('#map_slug').value = '';
    $('#map_center_lat').value = '0';
    $('#map_center_lng').value = '0';
    $('#map_default_zoom').value = '6';
    $('#map_min_zoom').value = '';
    $('#map_max_zoom').value = '';
    $('#map_default_tile').value = 'carto-dark';
    $('#map_sw_lat').value = '';
    $('#map_sw_lng').value = '';
    $('#map_ne_lat').value = '';
    $('#map_ne_lng').value = '';
    if (mapRect) { mapEdit.removeLayer(mapRect); mapRect=null; }
    mapEdit.setView([0,0], 2);
  }

  openModal('modalMap');
  setTimeout(()=>mapEdit.invalidateSize(), 100);
}

$('#btnNewMap').addEventListener('click', ()=>openMap(0));

// Tras guardar MAPA
async function saveMap(ev){
  ev.preventDefault();
  const fd = new FormData($('#formMap'));
  try {
    const json = await fetchJson(`${BASE}&ajax=save_map`, { method:'POST', body:fd });
    if (!json.ok) { alert(json.error||'Error'); return false; }
    const j = await fetchJson(`${BASE}&ajax=list_all`);
    if (j.ok){
      MAPS.splice(0, MAPS.length, ...j.maps);
      renderMaps();
      fillMapSelect($('#filterMap'), true);
      fillMapSelect($('#poi_map_id'));
      fillMapSelect($('#area_map_id'));
      fillMapSelect($('#filterAreaMap'), true);
    }
    closeModal('modalMap');
  } catch(e){
    console.error(e); alert('Error guardando el mapa');
  }
  return false;
}

async function delMap(id){
  if (!confirm('⚠️ Al borrar el mapa, sus POIs y Áreas (si FK CASCADE) también se borrarán.\n¿Seguro?')) return;
  const fd = new FormData(); fd.append('id', id);
  try {
    const json = await fetchJson(`${BASE}&ajax=delete_map`, { method:'POST', body:fd });
    if (!json.ok) { alert(json.error||'Error'); return; }
    $('#btnRefresh').click();
  } catch(e){ console.error(e); alert('Error eliminando el mapa'); }
}

// ------- Tabla CATS -------
function renderCats(){
  const tbody = $('#catsTable tbody'); tbody.innerHTML='';
  CATS.forEach(c=>{
    const tr = ce('tr',{className:'pj-row'});
    tr.innerHTML = `
      <td>${c.id}</td>
      <td>${esc(c.name)}</td>
      <td>${esc(c.slug)}</td>
      <td><span class="adm-swatch-18" data-bg="${esc(c.color_hex)}"></span> ${esc(c.color_hex)}</td>
      <td>${c.icon ? `<img src="${esc(c.icon)}" class="adm-thumb-22">` : ''}</td>
      <td>${c.sort_order}</td>
      <td>
        <button class="boton2" onclick="openCat(${c.id})">✏️</button>
        <button class="boton2 adm-btn-danger" onclick="delCat(${c.id})">🗑️</button>
      </td>`;
    tbody.appendChild(tr);
  });
  applySwatchBackgrounds(tbody);
}
renderCats();

function openCat(id){
  id = toInt(id);
  if (id > 0){
    const c = CATS.find(x => toInt(x.id) === id);
    if (!c) { alert(`Categoría ${id} no encontrada. Recarga.`); return; }
    $('#cat_id').value = c.id;
    $('#cat_name').value = c.name || '';
    $('#cat_slug').value = c.slug || '';
    $('#cat_color').value = c.color_hex || '#95a5a6';
    $('#cat_icon').value = c.icon || '';
    $('#cat_sort').value = c.sort_order || 0;
  } else {
    $('#cat_id').value = '';
    $('#cat_name').value = '';
    $('#cat_slug').value = '';
    $('#cat_color').value = '#95a5a6';
    $('#cat_icon').value = '';
    $('#cat_sort').value = 0;
  }
  openModal('modalCat');
}
$('#btnNewCat').addEventListener('click', ()=>openCat(0));

// Tras guardar CATEGORÍA
async function saveCat(ev){
  ev.preventDefault();
  const fd = new FormData($('#formCat'));
  try {
    const json = await fetchJson(`${BASE}&ajax=save_cat`, { method:'POST', body:fd });
    if (!json.ok) { alert(json.error||'Error'); return false; }
    const j = await fetchJson(`${BASE}&ajax=list_all`);
    if (j.ok){
      CATS.splice(0, CATS.length, ...j.cats);
      renderCats();
      fillCatSelect($('#filterCat'), true);
      fillCatSelect($('#poi_category_id'));
    }
    closeModal('modalCat');
  } catch(e){
    console.error(e); alert('Error guardando la categoría');
  }
  return false;
}

async function delCat(id){
  if (!confirm('¿Eliminar categoría? (no debe estar en uso)')) return;
  const fd = new FormData(); fd.append('id', id);
  try {
    const json = await fetchJson(`${BASE}&ajax=delete_cat`, { method:'POST', body:fd });
    if (!json.ok) { alert(json.error||'Error'); return; }
    $('#btnRefresh').click();
  } catch(e){
    console.error(e); alert('Error eliminando la categoría');
  }
}

// -------- Init: sincroniza datos por si hubo cambios por detrás ----------
async function init() {
  try {
    const json = await fetchJson(`${BASE}&ajax=list_all`);
    if (!json.ok) throw new Error(json.error || 'Error');
    MAPS.splice(0, MAPS.length, ...json.maps);
    CATS.splice(0, CATS.length, ...json.cats);
    POIS = json.pois;
    AREAS = json.areas || [];
    fillMapSelect($('#filterMap'), true);
    fillCatSelect($('#filterCat'), true);
    fillMapSelect($('#filterAreaMap'), true);
    renderPois(); renderMaps(); renderCats(); renderAreas();
  } catch(e) {
    console.error("Error en init():", e);
  }
}
init();
</script>

