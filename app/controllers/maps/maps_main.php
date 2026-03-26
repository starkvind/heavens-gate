<?php

include_once(__DIR__ . '/../../helpers/maps.php');

hg_maps_require_connection($link);

$schema = hg_maps_schema_info($link);
$maps = hg_maps_fetch_maps($link);

if (!$maps) {
    echo "<div class='bioTextData'><fieldset class='bioSeccion'><legend>Mapas</legend>No hay mapas definidos en <code>dim_maps</code>.</fieldset></div>";
    return;
}

$selectedMap = hg_maps_find_map($maps, (string)($_GET['map'] ?? ''));
if (!$selectedMap) {
    $selectedMap = $maps[0];
}

$mapNamesById = [];
$sourceMaps = [];
foreach ($maps as $mapItem) {
    $mapNamesById[(int)$mapItem['id']] = (string)$mapItem['name'];
    $sourceMaps[] = [
        'id' => (int)$mapItem['id'],
        'name' => (string)$mapItem['name'],
    ];
}

$allowGlobalPoiScope = hg_maps_is_global_map($selectedMap);
$defaultIncludeAllMaps = $allowGlobalPoiScope;
$defaultFilters = [
    'selected_map_id' => (int)$selectedMap['id'],
    'selected_map_name' => (string)$selectedMap['name'],
    'include_all_maps' => $defaultIncludeAllMaps,
    'source_map_id' => 0,
    'limit' => $defaultIncludeAllMaps ? 1000 : 600,
    'offset' => 0,
    'from_map_slug' => (string)$selectedMap['slug'],
];

$categories = hg_maps_fetch_categories($link, $schema, $defaultFilters, $mapNamesById);
$initialPois = hg_maps_fetch_pois($link, $schema, $defaultFilters, $mapNamesById);
$initialAreas = hg_maps_fetch_areas($link, (int)$selectedMap['id'], 0);
$tile = hg_maps_tile_for_map($selectedMap);
$bounds = hg_maps_map_bounds($selectedMap);

$metaTitle = $allowGlobalPoiScope
    ? "Gaia2 | Mapas | Heaven's Gate"
    : $selectedMap['name'] . " | Mapas | Heaven's Gate";
$metaDescription = $allowGlobalPoiScope
    ? "Mapa global de Gaia2 con lugares agregados de toda la campana."
    : "Mapa interactivo de " . $selectedMap['name'] . " con lugares, categorias y busqueda.";
setMetaFromPage($metaTitle, $metaDescription, null, 'website');

$mainConfig = [
    'apiBase' => '/maps/api',
    'selectedMap' => $selectedMap,
    'bounds' => $bounds,
    'tile' => $tile,
    'allowGlobalPoiScope' => $allowGlobalPoiScope,
    'defaultIncludeAllMaps' => $defaultIncludeAllMaps,
    'initialPois' => $initialPois,
    'initialAreas' => $initialAreas,
];
?>

<link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.1.9.4.css">
<script src="/assets/vendor/leaflet/leaflet.1.9.4.js"></script>
<link rel="stylesheet" href="/assets/vendor/leaflet/markercluster/MarkerCluster.1.5.3.css">
<link rel="stylesheet" href="/assets/vendor/leaflet/markercluster/MarkerCluster.Default.1.5.3.css">
<script src="/assets/vendor/leaflet/markercluster/leaflet.markercluster.1.5.3.js"></script>
<link rel="stylesheet" href="/assets/css/hg-chapters.css">
<link rel="stylesheet" href="/assets/css/hg-maps.css">
<?php include_once("app/partials/datatable_assets.php"); ?>
<script src="/assets/js/hg-maps.js"></script>

<div class="chapter-shell map-shell-root">
  <div class="chapter-hero map-hero">
    <h2>Mapas interactivos</h2>
    <span class="chapter-code"><?= htmlspecialchars((string)$selectedMap['name'], ENT_QUOTES, 'UTF-8') ?></span>
  </div>

  <section class="chapter-block map-stage-block">
    <div class="map-shell" data-map-page="main">
      <form method="get" class="map-quickbar" id="mapControlsForm">
        <input type="hidden" name="p" value="maps">

        <div class="map-quick-field map-quick-field-search">
          <label class="map-sr-only" for="poiSearch">Buscar POI</label>
          <input
            type="search"
            id="poiSearch"
            class="map-search-input"
            placeholder="Buscar por nombre o descripcion"
            autocomplete="off"
          >
        </div>

        <div class="map-quick-field">
          <label for="mapSel">Mapa</label>
          <select name="map" id="mapSel">
            <?php foreach ($maps as $mapOption): ?>
              <?php $isSelected = ((int)$mapOption['id'] === (int)$selectedMap['id']) ? 'selected' : ''; ?>
              <option value="<?= htmlspecialchars((string)$mapOption['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= $isSelected ?>>
                <?= htmlspecialchars((string)$mapOption['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="map-quick-field">
          <label for="catSel">Categoria</label>
          <select id="catSel">
            <option value="">Todas</option>
            <?php foreach ($categories as $category): ?>
              <option
                data-id="<?= (int)$category['id'] ?>"
                value="<?= htmlspecialchars((string)$category['name'], ENT_QUOTES, 'UTF-8') ?>"
              >
                <?= htmlspecialchars((string)$category['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

      </form>

      <div class="map-statusbar map-statusbar-inline">
        <div id="mapStatusText" class="map-status-copy">
          <?= $allowGlobalPoiScope ? 'Gaia2 cargado como mapa global.' : 'Mapa cargado.' ?>
        </div>
      </div>

      <div class="map-map-actions" aria-label="Acciones del mapa">
        <button type="button" class="map-icon-btn" id="btnSearch" title="Buscar" aria-label="Buscar">🔎</button>
        <button type="button" class="map-icon-btn" id="btnClear" title="Limpiar busqueda" aria-label="Limpiar busqueda" hidden>✕</button>
        <button type="button" class="map-icon-btn" id="btnRecenter" title="Centrar mapa" aria-label="Centrar mapa">🎯</button>
        <button type="button" class="map-icon-btn" id="btnFullscreen" title="Pantalla completa" aria-label="Pantalla completa">⛶</button>
      </div>

      <div id="hg-map"></div>
    </div>
  </section>

  <details class="chapter-block map-collapse"<?= $allowGlobalPoiScope ? ' open' : '' ?>>
    <summary class="map-collapse-summary">Explorar</summary>
    <div class="map-collapse-body">
      <?php if ($allowGlobalPoiScope): ?>
        <label class="map-check-row" for="toggleAllMaps">
          <input type="checkbox" id="toggleAllMaps" checked>
          <span>Mostrar tambien los POIs del resto de mapas sobre Gaia2</span>
        </label>

        <div class="map-field" id="sourceMapRow">
          <label for="sourceMapSel">Mapa de origen</label>
          <select id="sourceMapSel">
            <option value="0">Todos los mapas</option>
            <?php foreach ($sourceMaps as $sourceMap): ?>
              <option value="<?= (int)$sourceMap['id'] ?>">
                <?= htmlspecialchars((string)$sourceMap['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <p class="map-help-text">
          Gaia2 funciona como visor global: puedes ver todos los POIs sobre el mapa mundial
          y luego limitar por mapa de origen cuando quieras limpiar la lectura.
        </p>
      <?php else: ?>
        <p class="map-help-text">
          Usa la busqueda y las categorias para centrarte rapido en los lugares del mapa actual.
        </p>
      <?php endif; ?>
    </div>
  </details>

  <details class="chapter-block map-collapse">
    <summary class="map-collapse-summary">Resumen</summary>
    <div class="map-collapse-body">
      <div id="mapSummary" class="map-summary"></div>
      <div id="mapLegend" class="map-legend-row"></div>
    </div>
  </details>

  <details class="chapter-block map-collapse">
    <summary class="map-collapse-summary">Lugares visibles</summary>
    <div class="map-collapse-body">
      <p class="map-help-text">Puedes localizar cada punto directamente desde la tabla.</p>

      <div class="map-table-wrap">
        <table id="tabla-pois" class="display map-poi-table">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Categoria</th>
              <th>Mapa</th>
              <th>&nbsp;</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </details>
</div>

<script>
window.HGMaps.initMain(<?= hg_maps_json($mainConfig) ?>);
</script>
