<?php
// sep/maps/maps_main.php
// Este archivo se renderiza vía /maps (router). Asume $link (mysqli) ya conectado.
if (!$link) { die("Error de conexión a la base de datos: " . mysqli_connect_error()); }

setMetaFromPage("Mapas | Heaven's Gate", "Mapas interactivos con puntos de interes, categorias y busqueda.", null, 'website');

/* ============================================================
   0) AJAX interno
   ============================================================ */

/* ---------- Búsqueda de POIs (por map_id, category_id|name, texto) ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search') {
    header('Content-Type: application/json; charset=utf-8');

    $q        = isset($_GET['q']) ? trim($_GET['q']) : '';
    $map_id   = isset($_GET['map_id']) ? (int)$_GET['map_id'] : 0;
    $cat_id   = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    $cat_name = isset($_GET['category_name']) ? trim($_GET['category_name']) : '';
    $limit    = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 200;
    $offset   = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

    $has_map_id = $link->query("SHOW COLUMNS FROM fact_map_pois LIKE 'map_id'")->num_rows > 0;
    $has_cat_id = $link->query("SHOW COLUMNS FROM fact_map_pois LIKE 'category_id'")->num_rows > 0;

    $items = [];

    if ($has_map_id && $has_cat_id) {
        // ---- Esquema NUEVO ----
        $sql = "SELECT p.id, p.name, p.description, p.thumbnail, p.latitude, p.longitude,
                       p.map_id, m.name AS map_name, m.slug AS map_slug,
                       p.category_id, c.name AS category_name, c.color_hex
                FROM fact_map_pois p
                JOIN dim_maps m ON m.id = p.map_id
                JOIN dim_map_categories c ON c.id = p.category_id
                WHERE 1=1";
        $types = ''; $params = [];

        if ($map_id > 0) { $sql .= " AND p.map_id=?"; $types.='i'; $params[]=$map_id; }
        if ($cat_id > 0) { $sql .= " AND p.category_id=?"; $types.='i'; $params[]=$cat_id; }
        elseif ($cat_name !== '') { $sql .= " AND c.name=?"; $types.='s'; $params[]=$cat_name; }

        if ($q !== '') {
            $hasFT = $link->query("SHOW INDEX FROM fact_map_pois WHERE Key_name='ft_pois'")->num_rows > 0;
            if ($hasFT) { $sql .= " AND MATCH(p.name, p.description) AGAINST (? IN NATURAL LANGUAGE MODE)"; $types.='s'; $params[]=$q; }
            else { $sql .= " AND (p.name LIKE CONCAT('%', ?, '%') OR p.description LIKE CONCAT('%', ?, '%'))"; $types.='ss'; array_push($params, $q, $q); }
        }

        $sql .= " ORDER BY p.name ASC LIMIT ? OFFSET ?"; $types.='ii'; array_push($params, $limit, $offset);

        $st = $link->prepare($sql);
        if ($types !== '') { $st->bind_param($types, ...$params); }
        $st->execute(); $r = $st->get_result();
        while ($row = $r->fetch_assoc()) {
            $row['latitude']  = (float)$row['latitude'];
            $row['longitude'] = (float)$row['longitude'];
            $items[] = $row;
        }
        $st->close();

    } else {
        // ---- Esquema ANTIGUO (map/category texto) ----
        $map_name = '';
        if ($map_id > 0) { // traducir id -> nombre de mapa
            $st = $link->prepare("SELECT name FROM dim_maps WHERE id=?");
            $st->bind_param('i', $map_id); $st->execute();
            $map_name = (string)$st->get_result()->fetch_column(); $st->close();
        }

        $sql = "SELECT p.id, p.name, p.description, p.thumbnail, p.latitude, p.longitude,
                       p.map AS map_name, p.category AS category_name
                FROM fact_map_pois p
                WHERE 1=1";
        $types=''; $params=[];

        if ($map_name !== '') { $sql .= " AND p.map=?"; $types.='s'; $params[]=$map_name; }
        if ($cat_id > 0) { // traducir id -> nombre de categoría
            $st = $link->prepare("SELECT name FROM dim_map_categories WHERE id=?");
            $st->bind_param('i', $cat_id); $st->execute();
            $cat_name = (string)$st->get_result()->fetch_column(); $st->close();
        }
        if ($cat_name !== '') { $sql .= " AND p.category=?"; $types.='s'; $params[]=$cat_name; }

        if ($q !== '') { $sql .= " AND (p.name LIKE CONCAT('%', ?, '%') OR p.description LIKE CONCAT('%', ?, '%'))"; $types.='ss'; array_push($params, $q, $q); }

        $sql .= " ORDER BY p.name ASC LIMIT ? OFFSET ?"; $types.='ii'; array_push($params, $limit, $offset);

        $st = $link->prepare($sql);
        if ($types !== '') { $st->bind_param($types, ...$params); }
        $st->execute(); $r = $st->get_result();
        while ($row = $r->fetch_assoc()) {
            $row['latitude']  = (float)$row['latitude'];
            $row['longitude'] = (float)$row['longitude'];
            $row['color_hex'] = '#95a5a6';
            $row['map_slug']  = strtolower(preg_replace('/[^a-z0-9]+/i','-', $row['map_name'] ?? 'mapa'));
            $items[] = $row;
        }
        $st->close();
    }

    echo json_encode(['ok'=>true,'count'=>count($items),'items'=>$items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------- Áreas (polígonos) por mapa/categoría ---------- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'areas') {
    header('Content-Type: application/json; charset=utf-8');

    $map_id = isset($_GET['map_id']) ? (int)$_GET['map_id'] : 0;
    $cat_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

    $sql = "SELECT a.id, a.map_id, a.category_id, c.name AS category_name,
                   COALESCE(a.color_hex, c.color_hex, '#2ecc71') AS color_hex,
                   a.name, a.description, a.geometry
            FROM fact_map_areas a
            LEFT JOIN dim_map_categories c ON c.id = a.category_id
            WHERE 1=1";
    $types=''; $params=[];
    if ($map_id > 0) { $sql .= " AND a.map_id=?"; $types.='i'; $params[]=$map_id; }
    if ($cat_id > 0) { $sql .= " AND a.category_id=?"; $types.='i'; $params[]=$cat_id; }
    $sql .= " ORDER BY a.id DESC LIMIT 1000";

    $st = $link->prepare($sql);
    if ($types!=='') { $st->bind_param($types, ...$params); }
    $st->execute();
    $res = $st->get_result();

    $items = [];
    while ($row = $res->fetch_assoc()) {
        // geometry viene como string JSON; la devolvemos como objeto JSON
        $geom = json_decode($row['geometry'], true);
        if (!$geom || !isset($geom['type'])) { continue; } // ignorar corruptos
        $row['geometry'] = $geom;
        $items[] = $row;
    }
    $st->close();

    echo json_encode(['ok'=>true,'count'=>count($items),'items'=>$items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ============================================================
   1) Lista de mapas (selector)
   ============================================================ */
$maps = [];
$mres = $link->query("SELECT id, name, slug, center_lat, center_lng, default_zoom, default_tile,
                             bounds_sw_lat, bounds_sw_lng, bounds_ne_lat, bounds_ne_lng,
                             min_zoom, max_zoom
                      FROM dim_maps ORDER BY name");
while ($row = $mres->fetch_assoc()) { $maps[] = $row; }
if (!$maps) {
    echo "<div class='bioTextData'><fieldset class='bioSeccion'><legend>Mapas</legend>No hay mapas definidos en <code>dim_maps</code>.</fieldset></div>";
    exit;
}

/* ============================================================
   2) Mapa activo (?map=slug|name)
   ============================================================ */
$mapParam = isset($_GET['map']) ? trim($_GET['map']) : '';
$selectedMap = null;

// por slug
if ($mapParam !== '') {
    $stmt = $link->prepare("SELECT id, name, slug, center_lat, center_lng, default_zoom, default_tile,
                                   bounds_sw_lat, bounds_sw_lng, bounds_ne_lat, bounds_ne_lng,
                                   min_zoom, max_zoom
                            FROM dim_maps WHERE slug = ? LIMIT 1");
    $stmt->bind_param('s', $mapParam); $stmt->execute();
    $selectedMap = $stmt->get_result()->fetch_assoc(); $stmt->close();
}
// por name (arreglo: faltaba coma tras default_tile)
if (!$selectedMap && $mapParam !== '') {
    $stmt = $link->prepare("SELECT id, name, slug, center_lat, center_lng, default_zoom, default_tile,
                                   bounds_sw_lat, bounds_sw_lng, bounds_ne_lat, bounds_ne_lng,
                                   min_zoom, max_zoom
                            FROM dim_maps WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $mapParam); $stmt->execute();
    $selectedMap = $stmt->get_result()->fetch_assoc(); $stmt->close();
}
// fallback = primero
if (!$selectedMap) { $selectedMap = $maps[0]; }

$mapId    = (int)$selectedMap['id'];
$mapName  = $selectedMap['name'];
$mapSlug  = $selectedMap['slug'] ?: strtolower(preg_replace('/[^a-z0-9]+/i','-', $mapName));
$center   = [(float)$selectedMap['center_lat'], (float)$selectedMap['center_lng']];
$zoom     = (int)$selectedMap['default_zoom'];
$tilePref = $selectedMap['default_tile'] ?: 'carto-dark';

$bounds = null;
if ($selectedMap['bounds_sw_lat'] && $selectedMap['bounds_sw_lng'] && 
    $selectedMap['bounds_ne_lat'] && $selectedMap['bounds_ne_lng']) {
    $bounds = [
        [(float)$selectedMap['bounds_sw_lat'], (float)$selectedMap['bounds_sw_lng']],
        [(float)$selectedMap['bounds_ne_lat'], (float)$selectedMap['bounds_ne_lng']]
    ];
}

/* ============================================================
   3) Tiles
   ============================================================ */
$TILES = [
  'carto-dark' => [
    'url' => 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
    'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/basemaps" target="_blank" rel="noopener">CARTO</a>',
    'subdomains' => ['a','b','c','d'], 'maxZoom' => 19
  ],
  'osm-standard' => [
    'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    'attribution' => '&copy; OpenStreetMap contributors',
    'subdomains' => ['a','b','c'], 'maxZoom' => 19
  ],
  'esri-gray' => [
    'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}',
    'attribution' => 'Tiles &copy; Esri — Esri, DeLorme, NAVTEQ',
    'subdomains' => [], 'maxZoom' => 19
  ],
];
$tile = $TILES[ isset($TILES[$tilePref]) ? $tilePref : 'carto-dark' ];

/* ============================================================
   4) Categorías disponibles (POIs y/o Áreas)
   ============================================================ */
$cats = [];
$has_map_id = $link->query("SHOW COLUMNS FROM fact_map_pois LIKE 'map_id'")->num_rows > 0;
$has_cat_id = $link->query("SHOW COLUMNS FROM fact_map_pois LIKE 'category_id'")->num_rows > 0;

// Categorías desde POIs
if ($has_map_id && $has_cat_id) {
    $stmt = $link->prepare("SELECT DISTINCT c.id, c.name
                            FROM dim_map_categories c
                            JOIN fact_map_pois p ON p.category_id = c.id
                            WHERE p.map_id = ?
                            ORDER BY c.sort_order, c.name");
    $stmt->bind_param('i', $mapId); $stmt->execute();
    $r2 = $stmt->get_result();
    while ($row = $r2->fetch_assoc()) { $cats[$row['id']] = $row['name']; }
    $stmt->close();
} else {
    $stmt = $link->prepare("SELECT DISTINCT category AS name FROM fact_map_pois WHERE map=? ORDER BY category");
    $stmt->bind_param('s', $mapName); $stmt->execute();
    $r2 = $stmt->get_result();
    while ($row = $r2->fetch_assoc()) { $cats[0] = $row['name']; }
    $stmt->close();
}
// Añadimos también categorías que solo existan en Áreas (por si acaso)
$stA = $link->prepare("SELECT DISTINCT c.id, c.name
                       FROM fact_map_areas a
                       LEFT JOIN dim_map_categories c ON c.id=a.category_id
                       WHERE a.map_id = ? AND a.category_id IS NOT NULL
                       ORDER BY c.name");
$stA->bind_param('i', $mapId); $stA->execute();
$rA = $stA->get_result();
while ($r = $rA->fetch_assoc()) { if ($r['id']) $cats[$r['id']] = $r['name']; }
$stA->close();

/* ============================================================
   5) Pintado inicial de POIs y Áreas
   ============================================================ */
$initialPois = [];
if ($has_map_id && $has_cat_id) {
    $stmt = $link->prepare("
        SELECT p.id, p.name, p.description, p.thumbnail, p.latitude, p.longitude,
               p.category_id, c.name AS category_name, c.color_hex
        FROM fact_map_pois p
        JOIN dim_map_categories c ON c.id = p.category_id
        WHERE p.map_id = ?
        ORDER BY p.name
        LIMIT 500
    ");
    $stmt->bind_param('i', $mapId); $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['latitude']  = (float)$row['latitude'];
        $row['longitude'] = (float)$row['longitude'];
        if (!isset($row['color_hex'])) $row['color_hex'] = '#95a5a6';
        $initialPois[] = $row;
    }
    $stmt->close();
} else {
    $stmt = $link->prepare("
        SELECT p.id, p.name, p.description, p.thumbnail, p.latitude, p.longitude,
               p.category AS category_name
        FROM fact_map_pois p
        WHERE p.map = ?
        ORDER BY p.name
        LIMIT 500
    ");
    $stmt->bind_param('s', $mapName); $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['latitude']  = (float)$row['latitude'];
        $row['longitude'] = (float)$row['longitude'];
        $row['color_hex'] = '#95a5a6';
        $initialPois[] = $row;
    }
    $stmt->close();
}

/* Áreas iniciales del mapa activo */
$initialAreas = [];
$st = $link->prepare("SELECT a.id, a.map_id, a.category_id, c.name AS category_name,
                             COALESCE(a.color_hex, c.color_hex, '#2ecc71') AS color_hex,
                             a.name, a.description, a.geometry
                      FROM fact_map_areas a
                      LEFT JOIN dim_map_categories c ON c.id=a.category_id
                      WHERE a.map_id = ?
                      ORDER BY a.id DESC
                      LIMIT 1000");
$st->bind_param('i', $mapId); $st->execute();
$rI = $st->get_result();
while ($row = $rI->fetch_assoc()) {
    $geom = json_decode($row['geometry'], true);
    if (!$geom || !isset($geom['type'])) continue;
    $row['geometry'] = $geom;
    $initialAreas[] = $row;
}
$st->close();

// Para JS
$centerLat = $center[0]; $centerLng = $center[1];
$initialPoisJson  = json_encode($initialPois, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$initialAreasJson = json_encode($initialAreas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

<link rel="stylesheet" href="assets/vendor/leaflet/leaflet.1.9.4.css">
<script src="assets/vendor/leaflet/leaflet.1.9.4.js"></script>
<link rel="stylesheet" href="assets/vendor/leaflet/markercluster/MarkerCluster.1.5.3.css">
<link rel="stylesheet" href="assets/vendor/leaflet/markercluster/MarkerCluster.Default.1.5.3.css">
<script src="assets/vendor/leaflet/markercluster/leaflet.markercluster.1.5.3.js"></script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<style>
	#hg-map { 
		box-shadow: 0 3px 6px rgba(0,0,0,0.05); 
		height: 70vh; 
		width: 100%; 
		border: none; 
		background-color: #05014E; 
		position: relative; 
	}

	.map-toolbar { float: right; margin-bottom: 6px; }
	.map-toolbar .boton2 { margin-left: 4px; }

	.map-toolbar {
	  display: flex;
	  flex-wrap: wrap;
	  align-items: center;
	  gap: 6px;
	  margin-bottom: 6px;
	}

	.map-toolbar input[type="text"]{ 
		background:#000066; 
		color:#fff; 
		border:1px solid #000099; 
		padding:2px; 
		flex: 0 0 180px;
	}

	.map-thumb { 
		width: 200px; 
		height: auto; 
		border:1px solid #009; 
		margin: 6px 0; 
		display:block; 
		background:#000055;
	}

	.leaflet-container a { color:#66CCFF; }

	.emoji-marker { 
		font-size:16px; 
		line-height:16px; 
		width:18px; 
		height:18px; 
		display:flex; 
		align-items:center; 
		justify-content:center; 
		filter: drop-shadow(0 0 4px rgba(0,255,200,.6)); 
	}

	#btnClear { display: none; }
</style>

<h2>Mapas interactivos</h2>

<div class="bioTextData">
  <fieldset class='bioSeccion'>
    <legend>&nbsp;Navegador&nbsp;</legend>

    <div class="map-toolbar">
      <button class="boton2" id="btnFullscreen">🖥️ Aumentar</button>
      <button class="boton2" id="btnRecenter">🎯 Centrar</button>
      <button class="boton2" id="btnSearch">🔎</button>
      <input type="text" id="poiSearch" placeholder="nombre, descripción..." style="width:180px;">
      <button class="boton2" id="btnClear">♻️ Limpiar</button>
    </div>

    <div id="hg-map"></div>
	
	<h3 style="margin-top:1em;">Lista de lugares</h3>
	<div style="overflow-x:auto;">
	  <table id="tabla-pois" class="display" style="width:100%">
		<thead>
		  <tr>
			<th>Nombre</th>
			<th>Categoría</th>
			<th>&nbsp;</th>
		  </tr>
		</thead>
		<tbody></tbody>
	  </table>
	</div>

	
    <div class="map-toolbar" style="margin-top:2em; margin-bottom:6px;">
      <form method="get" style="display:inline;">
        <input type="hidden" name="p" value="maps">
        <label for="mapSel">Mapa:</label>
        <select name="map" id="mapSel">
          <?php foreach ($maps as $m): 
              $val = $m['slug'] ?: strtolower(preg_replace('/[^a-z0-9]+/i','-', $m['name']));
              $isSel = ((int)$m['id'] === (int)$mapId) ? 'selected' : '';
          ?>
            <option data-id="<?= (int)$m['id'] ?>" value="<?= htmlspecialchars($val) ?>" <?= $isSel; ?>>
              <?= htmlspecialchars($m['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button style="display: none;" type="submit" class="boton2" id="btnGo">Ir</button>

        <?php if ($cats): ?>
          &nbsp;&nbsp;<label for="catSel">Categoría:</label>
          <select id="catSel">
            <option value="">(todas)</option>
            <?php foreach ($cats as $cid => $cname): ?>
              <option data-id="<?= (int)$cid ?>" value="<?= htmlspecialchars($cname) ?>">
                <?= htmlspecialchars($cname) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </form>
    </div>
  </fieldset>
</div>

<script>
(function(){
  const center = L.latLng(<?= $centerLat ?>, <?= $centerLng ?>);
  const zoom   = <?= (int)$zoom ?>;
  const currentMapIdInitial = <?= (int)$mapId ?>;
  const SEARCH_URL = '/maps&ajax=search';
  const AREAS_URL  = '/maps&ajax=areas';
  
  let poiCache = [];

  const map = L.map('hg-map', {
    zoomControl: true,
    attributionControl: true,
    <?php if ($bounds): ?>
    maxBounds: <?= json_encode($bounds) ?>,
    maxBoundsViscosity: 1.0,
    <?php endif; ?>
    minZoom: <?= (int)$selectedMap['min_zoom'] ?>,
    maxZoom: <?= (int)$selectedMap['max_zoom'] ?>
  }).setView(center, zoom);

  L.tileLayer('<?= $tile['url'] ?>', {
    attribution: '<?= $tile['attribution'] ?>',
    subdomains: <?= json_encode($tile['subdomains']) ?>,
    maxZoom: <?= (int)$tile['maxZoom'] ?>
  }).addTo(map);

  /* ---------- Capas ---------- */
  const cluster = L.markerClusterGroup({ showCoverageOnHover:false, maxClusterRadius:0 });
  const areasLayer = L.geoJSON(null, {
    style: feature => {
      const col = (feature?.properties?.color_hex) || '#2ecc71';
      return {
        color: col,
        weight: 2,
        fillColor: col,
        fillOpacity: 0.20
      };
    },
    onEachFeature: (feature, layer) => {
      const p = feature.properties || {};
      const name = escapeHtml(p.name || 'Área');
      const cat  = escapeHtml(p.category_name || '');
      const desc = p.description ? `<div style="max-width:260px;">${escapeHtml(p.description)}</div>` : '';
      layer.bindPopup(`<b>${name}</b><br><small>${cat}</small><br>${desc}`);
      layer.on('mouseover', () => layer.setStyle({ weight:3, fillOpacity:0.28 }));
      layer.on('mouseout',  () => layer.setStyle({ weight:2, fillOpacity:0.20 }));
    }
  });

  map.addLayer(cluster);
  map.addLayer(areasLayer);
  L.control.layers(null, { 'Áreas': areasLayer, 'Lugares': cluster }, { collapsed:true }).addTo(map);

  /* ---------- Utilidades ---------- */
  function makeDivIcon(colorHex){
    const style = `background:${colorHex||'#95a5a6'}; width:14px;height:14px;border-radius:50%;
                   border:2px solid rgba(255,255,255,.75); box-shadow:0 0 8px rgba(0,255,200,.4);`;
    return L.divIcon({ className:'', html:`<div style="${style}"></div>`, iconSize:[14,14], iconAnchor:[7,7] });
  }
  function makeEmojiIcon(char='📍'){ return L.divIcon({ className:'emoji-marker', html:char, iconSize:[18,18], iconAnchor:[9,9] }); }
  function pickEmoji(cat){
    const key = (cat||'').toLowerCase();
    const map = { 'casa':'🏠','guarida':'🕳️','conflicto':'💥','templo':'⛩️','lugar sagrado':'✨','lugar_sagrado':'✨','otro':'📍' };
    return map[key] || '📍';
  }
  function escapeHtml(s){ return (s||'').toString().replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

  const mapSel = document.getElementById('mapSel');
  const catSel = document.getElementById('catSel');
  const qInp   = document.getElementById('poiSearch');
  const btnS   = document.getElementById('btnSearch');
  const btnC   = document.getElementById('btnClear');

  document.getElementById('btnGo').addEventListener('click', e => { /* submit explícito */ });
  mapSel.addEventListener('change', function(){ this.form.submit(); });

  let currentMapId = currentMapIdInitial;

  /* ---------- Pintado POIs ---------- */
	function paintPOIs(items, fit=true){
	  cluster.clearLayers();
	  const pts = [];
	  items.forEach((p, idx) => {
		const lat = parseFloat(p.latitude), lng = parseFloat(p.longitude);
		if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
		const ll = L.latLng(lat, lng);
		const icon = (p.color_hex && /^#?[0-9a-f]{6}$/i.test(p.color_hex))
		  ? makeDivIcon(p.color_hex)
		  : makeEmojiIcon(pickEmoji(p.category_name));

		const m  = L.marker(ll, { icon });
		const thumb = p.thumbnail ? `<img class="map-thumb" src="${p.thumbnail}" alt="">` : '';
		const desc  = p.description ? `<div style="max-width:260px;">${escapeHtml(p.description)}</div>` : '';
		const link  = `<div style="margin-top:6px;display:none;"><a class="infoLink" href="/maps/poi/${p.id}" target="_blank" rel="noopener">➡️ Ver detalle</a></div>`;
		m.bindPopup(`<b>${escapeHtml(p.name)}</b><br><small>${escapeHtml(p.category_name||'')}</small><br>${thumb}${desc}${link}`);
		m.on('click', () => map.flyTo(ll, Math.max(map.getZoom(), 14), { duration: 0.6 }));

		cluster.addLayer(m);
		pts.push(ll);

		// 🔑 guardar referencia al marker
		p._marker = m;
	  });
	  if (fit && pts.length) { map.fitBounds(L.latLngBounds(pts).pad(0.2)); }
	  else if (!pts.length) { map.setView(center, zoom); }
	  refreshTable(items);
	}


  /* ---------- Pintado Áreas ---------- */
  function paintAreas(areas){
    areasLayer.clearLayers();
    const features = [];
	areas.forEach(a => {
	  if (!a || !a.geometry) return;

	  let geom;
	  try {
		geom = (typeof a.geometry === 'string') ? JSON.parse(a.geometry) : a.geometry;
	  } catch(e) {
		console.error("GeoJSON inválido en área", a, e);
		return;
	  }

	  if (!geom || !geom.type) return;

	  const props = {
		id: a.id,
		name: a.name || 'Área',
		description: a.description || '',
		category_id: a.category_id || null,
		category_name: a.category_name || '',
		color_hex: a.color_hex || '#2ecc71'
	  };

	  features.push({
		type: 'Feature',
		properties: props,
		geometry: geom.geometry || geom // soporta Feature completo o solo geometry
	  });
	});
    if (features.length) {
      areasLayer.addData({ type:'FeatureCollection', features });
    }
  }

  // Pintado inicial desde PHP
  paintPOIs(<?= $initialPoisJson ?>, true);
  paintAreas(<?= $initialAreasJson ?>);

  /* ---------- Búsqueda / Filtro ---------- */
  async function fetchAreas(){
    const params = new URLSearchParams();
    if (currentMapId) params.set('map_id', currentMapId);
    if (catSel && catSel.value) {
      const opt = catSel.selectedOptions[0];
      const catId = parseInt(opt?.dataset?.id || '0', 10) || 0;
      if (catId) params.set('category_id', catId);
    }
    const res = await fetch(AREAS_URL + '&' + params.toString(), { credentials:'same-origin' });
    if (!res.ok) throw new Error('HTTP '+res.status);
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Error áreas');
    return json.items || [];
  }

  async function doSearch(){
    const params = new URLSearchParams();
    if (currentMapId) params.set('map_id', currentMapId);

    if (catSel && catSel.value) {
      const opt = catSel.selectedOptions[0];
      const catId = parseInt(opt?.dataset?.id || '0', 10) || 0;
      if (catId) params.set('category_id', catId);
      else params.set('category_name', opt.value); // soporte esquema antiguo
    }
    if (qInp && qInp.value.trim() !== '') params.set('q', qInp.value.trim());
    params.set('limit','500');

    try {
      // POIs
      const res = await fetch(SEARCH_URL + '&' + params.toString(), { credentials:'same-origin' });
      if (!res.ok) throw new Error('HTTP '+res.status);
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'Error de búsqueda');
      paintPOIs(json.items, true);
      // Áreas (en paralelo está bien, aquí secuencial por simplicidad)
      const areas = await fetchAreas();
      paintAreas(areas);
    } catch(e) { console.error('[maps] búsqueda/filtro fallido:', e); }
  }

  //const btnS = document.getElementById('btnSearch');
  //const btnC = document.getElementById('btnClear');
  //const qInp = document.getElementById('poiSearch');

  btnS.addEventListener('click', doSearch);
  qInp.addEventListener('keyup', e => { if (e.key==='Enter') doSearch(); });

  function toggleClearBtn() {
    btnC.style.display = (qInp.value.trim() !== '') ? 'inline-block' : 'none';
  }
  qInp.addEventListener('input', toggleClearBtn);
  btnC.addEventListener('click', () => { qInp.value=''; toggleClearBtn(); doSearch(); });
  if (catSel) catSel.addEventListener('change', doSearch);
  toggleClearBtn();

  /* ---------- Controles varios ---------- */
  const mapDiv = document.getElementById('hg-map');
  document.getElementById('btnFullscreen').addEventListener('click', () => {
    if (!document.fullscreenElement) mapDiv.requestFullscreen(); else document.exitFullscreen();
  });
  document.getElementById('btnRecenter').addEventListener('click', () => { map.flyTo(center, <?= (int)$zoom ?>, { duration: 0.5 }); });

  // Refresco inicial (por si hay cambios nuevos en BDD)
  //doSearch();
  
	function refreshTable(items){
	  poiCache = items;
	  const tbody = $('#tabla-pois tbody');
	  tbody.empty();

	  items.forEach((p, idx) => {
		const row = `<tr>
		  <td>${escapeHtml(p.name || '')}</td>
		  <td>${escapeHtml(p.category_name || '')}</td>
		  <td><button class="verBtn boton2" data-idx="${idx}">➡️ Localizar</button></td>
		</tr>`;
		tbody.append(row);
	  });

	  if ($.fn.DataTable.isDataTable('#tabla-pois')) {
		$('#tabla-pois').DataTable().clear().destroy();
	  }
	  $('#tabla-pois').DataTable({
		pageLength: 10,
		order: [[0,"asc"]],
		language: {
		  search: "🔍 Buscar:&nbsp;",
		  lengthMenu: "Mostrar _MENU_ lugares",
		  info: "Mostrando _START_ a _END_ de _TOTAL_ lugares",
		  infoEmpty: "No hay lugares disponibles",
		  emptyTable: "No hay datos en la tabla",
		  paginate: { first:"Primero", last:"Último", next:"▶", previous:"◀" }
		}
	  });

	// click en botón
	// delegación de eventos (funciona aunque cambies de página en DataTables)
	$('#tabla-pois').off('click', '.verBtn').on('click', '.verBtn', function(){
	  const idx = $(this).data('idx');
	  const p = poiCache[idx];
	  if (!p) return;
	  const lat = parseFloat(p.latitude), lng = parseFloat(p.longitude);
	  if (Number.isFinite(lat) && Number.isFinite(lng)) {
		map.flyTo([lat,lng], Math.max(map.getZoom(),14), {duration:0.6});
		if (p._marker) {
		  setTimeout(() => p._marker.openPopup(), 700);
		}
	  }
	});

	}

})();
</script>
