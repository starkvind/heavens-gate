<?php
// sep/maps/maps_detail.php
// Renderiza detalle de un POI. Asume $link (mysqli) ya conectado.
if (!$link) { die("Error de conexión a la base de datos: " . mysqli_connect_error()); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo "<div class='bioTextData'><fieldset class='bioSeccion'><legend>POI</legend>Id inválido.</fieldset></div>"; exit;
}

// ¿esquema nuevo?
$has_map_id = $link->query("SHOW COLUMNS FROM fact_map_pois LIKE 'map_id'")->num_rows > 0;

$poi = null;
if ($has_map_id) {
  $stmt = $link->prepare(
    "SELECT p.id, p.name, p.description, p.thumbnail, p.latitude, p.longitude,
            m.name AS map_name, m.slug AS map_slug,
            c.name AS category_name
     FROM fact_map_pois p
     JOIN dim_maps m ON m.id = p.map_id
     JOIN dim_map_categories c ON c.id = p.category_id
     WHERE p.id=?"
  );
  $stmt->bind_param('i', $id); $stmt->execute();
  $poi = $stmt->get_result()->fetch_assoc(); $stmt->close();
} else {
  $stmt = $link->prepare("SELECT id, name, map AS map_name, category AS category_name, description, thumbnail, latitude, longitude FROM fact_map_pois WHERE id=?");
  $stmt->bind_param('i', $id); $stmt->execute();
  $tmp = $stmt->get_result()->fetch_assoc(); $stmt->close();
  if ($tmp) { $tmp['map_slug'] = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $tmp['map_name'])); $poi = $tmp; }
}

if (!$poi) {
  echo "<div class='bioTextData'><fieldset class='bioSeccion'><legend>POI</legend>No existe el punto solicitado.</fieldset></div>";
  exit;
}

setMetaFromPage($poi['name'] . " | Mapas | Heaven's Gate", meta_excerpt($poi['description'] ?? ''), $poi['thumbnail'] ?? null, 'article');

// Tiles (coherentes con main)
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
$tile = $TILES['carto-dark'];

// Otros POIs en el mismo mapa
$others = [];
if ($has_map_id) {
  $q = $link->prepare("SELECT id, name FROM fact_map_pois WHERE map_id=(SELECT map_id FROM fact_map_pois WHERE id=?) AND id<>? ORDER BY name LIMIT 30");
  $q->bind_param('ii', $id, $id);
} else {
  $q = $link->prepare("SELECT id, name FROM fact_map_pois WHERE map=? AND id<>? ORDER BY name LIMIT 30");
  $q->bind_param('si', $poi['map_name'], $id);
}
$q->execute(); $r = $q->get_result();
while ($row = $r->fetch_assoc()) { $others[] = $row; }
$q->close();

$lat = (float)$poi['latitude']; $lng = (float)$poi['longitude'];
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
  #hg-map-detail { height: 70vh; width: 100%; background:#05014E; position:relative; }
  .map-toolbar { float: right; margin-bottom: 6px; }
  .map-toolbar .boton2 { margin-left: 4px; }
  .ping-wrap { position: relative; width: 18px; height: 18px; }
  .ping-core { width: 14px; height: 14px; border-radius:50%; background:#00d4ff; border:2px solid rgba(255,255,255,.8);
               position:absolute; top:0; left:0; box-shadow:0 0 10px rgba(0,255,200,.8); }
  .ping-wave { position:absolute; top:-5px; left:-5px; width:24px; height:24px; border-radius:50%;
               border:2px solid rgba(0,255,200,.45); animation: ping 1.8s ease-out infinite; }
  @keyframes ping { 0%{ transform:scale(0.4); opacity:.9;} 80%{ transform:scale(1.8); opacity:0;} 100%{ transform:scale(1.8); opacity:0;} }
  .map-thumb { width: 220px; border:1px solid #009; background:#000055; margin:6px 0; display:block; }
</style>

<h2>Mapa · <?= htmlspecialchars($poi['map_name']) ?></h2>

<div class="bioTextData">
  <fieldset class='bioSeccion'>
    <legend>&nbsp;<?= htmlspecialchars($poi['name']) ?>&nbsp;</legend>

    <div class="map-toolbar">
      <a class="boton2" href="/maps?map=<?= urlencode($poi['map_slug']) ?>">⬅️ Volver al mapa</a>
      <button class="boton2" id="btnFullscreen">🔍 Pantalla completa</button>
    </div>

    <div style="margin-bottom:8px;">
      <b>Categoría:</b> <?= htmlspecialchars($poi['category_name']) ?><br>
      <?php if (!empty($poi['thumbnail'])): ?>
        <img class="map-thumb" src="<?= htmlspecialchars($poi['thumbnail']) ?>" alt="">
      <?php endif; ?>
      <?php if (!empty($poi['description'])): ?>
        <div style="max-width:600px; margin-top:4px; text-align:justify;"><?= nl2br(htmlspecialchars($poi['description'])) ?></div>
      <?php endif; ?>
    </div>

    <div id="hg-map-detail"></div>

    <?php if ($others): ?>
      <div style="margin-top:8px;">
        <fieldset class="bioSeccion">
          <legend>&nbsp;Otros puntos en <?= htmlspecialchars($poi['map_name']) ?>&nbsp;</legend>
          <ul class="listaManadas" style="list-style-type:none; margin:0; padding:0;">
            <?php foreach ($others as $o): ?>
              <li class="listaManadas" style="width:100%; margin-bottom:4px;">
                <a href="/maps/poi/<?= (int)$o['id'] ?>" class="infoLink">➡️ <?= htmlspecialchars($o['name']) ?></a>
              </li>
            <?php endforeach; ?>
          </ul>
        </fieldset>
      </div>
    <?php endif; ?>
  </fieldset>
</div>

<script>
(function(){
  const lat = <?= $lat ?>, lng = <?= $lng ?>;
  const map = L.map('hg-map-detail', { zoomControl:true }).setView([lat,lng], 14);

  L.tileLayer('<?= $tile['url'] ?>', {
    attribution: '<?= $tile['attribution'] ?>',
    subdomains: <?= json_encode($tile['subdomains']) ?>,
    maxZoom: <?= (int)$tile['maxZoom'] ?>
  }).addTo(map);

  const pingIcon = L.divIcon({ className:'', html:'<div class="ping-wrap"><div class="ping-core"></div><div class="ping-wave"></div></div>', iconSize:[18,18], iconAnchor:[9,9] });
  const marker = L.marker([lat,lng], { icon: pingIcon }).addTo(map);
  marker.bindPopup(`<b><?= htmlspecialchars($poi['name']) ?></b><br><small><?= htmlspecialchars($poi['category_name']) ?></small>`).openPopup();

  document.getElementById('btnFullscreen').addEventListener('click', () => {
    const el = document.getElementById('hg-map-detail');
    if (!document.fullscreenElement) el.requestFullscreen(); else document.exitFullscreen();
  });

  window.addEventListener('resize', () => map.invalidateSize());
})();
</script>
