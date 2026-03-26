<?php

include_once(__DIR__ . '/../../helpers/maps.php');

hg_maps_require_connection($link);

$poiId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($poiId <= 0) {
    echo "<div class='bioTextData'><fieldset class='bioSeccion'><legend>POI</legend>Id invalido.</fieldset></div>";
    return;
}

$schema = hg_maps_schema_info($link);
$poi = hg_maps_fetch_poi_detail($link, $schema, $poiId);

if (!$poi) {
    echo "<div class='bioTextData'><fieldset class='bioSeccion'><legend>POI</legend>No existe el punto solicitado.</fieldset></div>";
    return;
}

$maps = hg_maps_fetch_maps($link);
$fromMapParam = trim((string)($_GET['from_map'] ?? ''));
$fromMap = $fromMapParam !== '' ? hg_maps_find_map($maps, $fromMapParam) : null;
$fromMapIsDifferent = $fromMap && (string)$fromMap['slug'] !== (string)$poi['map_slug'];
$relatedPois = hg_maps_fetch_related_pois($link, $schema, $poi, 40);

setMetaFromPage(
    $poi['name'] . " | Mapas | Heaven's Gate",
    meta_excerpt($poi['description'] ?? ''),
    $poi['thumbnail'] ?? null,
    'article'
);

?>

<link rel="stylesheet" href="/assets/css/hg-chapters.css">
<link rel="stylesheet" href="/assets/css/hg-maps.css">

<div class="chapter-shell map-shell-root map-shell-root-detail">
  <div class="chapter-hero map-hero">
    <h2><?= htmlspecialchars((string)$poi['name'], ENT_QUOTES, 'UTF-8') ?></h2>
    <span class="chapter-code"><?= htmlspecialchars((string)$poi['map_name'], ENT_QUOTES, 'UTF-8') ?></span>
  </div>

  <section class="chapter-block map-detail-nav-block">
    <div class="map-action-cluster">
      <a class="boton2" href="/maps?map=<?= urlencode((string)$poi['map_slug']) ?>">Volver al mapa</a>
      <?php if ($fromMapIsDifferent): ?>
        <a class="boton2" href="/maps?map=<?= urlencode((string)$fromMap['slug']) ?>">Volver a <?= htmlspecialchars((string)$fromMap['name'], ENT_QUOTES, 'UTF-8') ?></a>
      <?php endif; ?>
    </div>
  </section>

  <section class="chapter-block map-detail-article">
    <h3 class="chapter-title">Ficha</h3>

    <div class="map-detail-layout<?= !empty($poi['thumbnail']) ? '' : ' is-text-only' ?>">
      <div class="map-detail-summary">
        <div class="map-detail-badges">
          <?php if (!empty($poi['category_name'])): ?>
            <span class="map-popup-pill"><?= htmlspecialchars((string)$poi['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <?php if (!empty($poi['map_name'])): ?>
            <span class="map-popup-pill map-popup-pill-map"><?= htmlspecialchars((string)$poi['map_name'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
        </div>

        <?php if (!empty($poi['description'])): ?>
          <div class="map-detail-description">
            <?= nl2br(htmlspecialchars((string)$poi['description'], ENT_QUOTES, 'UTF-8')) ?>
          </div>
        <?php else: ?>
          <p class="map-help-text">Este punto no tiene descripcion todavia.</p>
        <?php endif; ?>

        <?php if (!empty($poi['map_name']) || !empty($poi['category_name'])): ?>
          <dl class="map-detail-meta">
            <?php if (!empty($poi['map_name'])): ?>
              <div>
                <dt>Mapa</dt>
                <dd><?= htmlspecialchars((string)$poi['map_name'], ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
            <?php endif; ?>
            <?php if (!empty($poi['category_name'])): ?>
              <div>
                <dt>Categoria</dt>
                <dd><?= htmlspecialchars((string)$poi['category_name'], ENT_QUOTES, 'UTF-8') ?></dd>
              </div>
            <?php endif; ?>
          </dl>
        <?php endif; ?>
      </div>

      <?php if (!empty($poi['thumbnail'])): ?>
        <div class="map-detail-media">
          <img
            class="map-thumb map-thumb-detail"
            src="<?= htmlspecialchars((string)$poi['thumbnail'], ENT_QUOTES, 'UTF-8') ?>"
            alt="<?= htmlspecialchars((string)$poi['name'], ENT_QUOTES, 'UTF-8') ?>"
          >
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($relatedPois): ?>
    <details class="chapter-block map-collapse">
      <summary class="map-collapse-summary">Otros puntos en <?= htmlspecialchars((string)$poi['map_name'], ENT_QUOTES, 'UTF-8') ?></summary>
      <div class="map-collapse-body">
        <ul class="map-related-list">
          <?php foreach ($relatedPois as $related): ?>
            <li class="map-related-item">
              <a href="<?= htmlspecialchars((string)$related['detail_url'], ENT_QUOTES, 'UTF-8') ?>" class="infoLink">
                <?= htmlspecialchars((string)$related['name'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </details>
  <?php endif; ?>
</div>
