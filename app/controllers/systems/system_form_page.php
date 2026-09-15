<?php

require_once __DIR__ . '/../../domains/systems/queries.php';

function sf_h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$formKey = hg_request_param($hgRequest, 'form');
$formId = hg_systems_resolve_id($link, 'dim_forms', $formKey);

if ($formId <= 0) {
    if (!defined("HG_MOBILE_DESKTOP_EMBED") || !HG_MOBILE_DESKTOP_EMBED) include("app/partials/main_nav_bar.php");
    echo "<h2>Forma no encontrada</h2>";
    echo "<div class='renglonDatosSistema'>La forma solicitada no existe.</div>";
    return;
}

$row = hg_systems_fetch_form($link, $formId);
if ($row === false) {
    if (!defined("HG_MOBILE_DESKTOP_EMBED") || !HG_MOBILE_DESKTOP_EMBED) include("app/partials/main_nav_bar.php");
    echo "<h2>Error cargando forma</h2>";
    echo "<div class='renglonDatosSistema'>No se pudo preparar la consulta.</div>";
    return;
}

if (!$row) {
    if (!defined("HG_MOBILE_DESKTOP_EMBED") || !HG_MOBILE_DESKTOP_EMBED) include("app/partials/main_nav_bar.php");
    echo "<h2>Forma no encontrada</h2>";
    echo "<div class='renglonDatosSistema'>La forma solicitada no existe.</div>";
    return;
}

$systemNameRaw = trim((string)($row['system_name_resolved'] ?? ''));
$breedNameRaw = trim((string)($row['breed_name_resolved'] ?? ''));
$formNameRaw = trim((string)($row['form'] ?? ''));

$returnType = $systemNameRaw;
$formDisplayRaw = $formNameRaw;
if ($systemNameRaw === "Bastet" && $breedNameRaw !== '') {
    $formDisplayRaw = $formNameRaw . " (" . $breedNameRaw . ")";
}
$nameWereForm = sf_h($formDisplayRaw);
$infoDesc = (string)($row['description'] ?? '');
$imageWereForm = trim((string)($row['image_url'] ?? ''));
$bonusSTR = sf_h((string)($row['strength_bonus'] ?? ''));
$bonusDEX = sf_h((string)($row['dexterity_bonus'] ?? ''));
$bonusRES = sf_h((string)($row['stamina_bonus'] ?? ''));
$useMelee = (int)($row['weapons'] ?? 0);
$useGuns = (int)($row['firearms'] ?? 0);

$canUseMelee = $useMelee === 1
    ? "Esta forma es capaz de utilizar armas cuerpo a cuerpo."
    : "Esta forma <b><u>no</u></b> puede utilizar armas cuerpo a cuerpo.";

$canUseGuns = $useGuns === 1
    ? "Esta forma es capaz de utilizar armas de fuego."
    : "Esta forma <b><u>no</u></b> puede utilizar armas de fuego.";

$pageSect = "Forma";
$pageTitle2 = sf_h($formNameRaw);
if (function_exists("setMetaFromPage")) setMetaFromPage($formDisplayRaw . " | Formas | Heaven's Gate", meta_excerpt($infoDesc), $imageWereForm, 'article');

include("app/helpers/system_category_helper.php");
if (!defined("HG_MOBILE_DESKTOP_EMBED") || !HG_MOBILE_DESKTOP_EMBED) include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-systems.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-systems.css">';
}
?>
<div class="form-detail">
  <div class="form-banner">
    <?php if ($imageWereForm !== ''): ?>
      <img src="<?= sf_h($imageWereForm) ?>" alt="<?= $nameWereForm ?>">
    <?php endif; ?>
    <h2 class="form-banner-title"><?= $nameWereForm ?></h2>
  </div>

  <div class="form-box">
    <h3>Modificadores</h3>
    <div class="mod-grid">
      <div class="mod-card">
        <div class="mod-label">Fuerza</div>
        <div class="mod-value"><?= ($bonusSTR !== '' && is_numeric($bonusSTR) && $bonusSTR > 0 ? '+' : '') . $bonusSTR ?></div>
      </div>
      <div class="mod-card">
        <div class="mod-label">Destreza</div>
        <div class="mod-value"><?= ($bonusDEX !== '' && is_numeric($bonusDEX) && $bonusDEX > 0 ? '+' : '') . $bonusDEX ?></div>
      </div>
      <div class="mod-card">
        <div class="mod-label">Resistencia</div>
        <div class="mod-value"><?= ($bonusRES !== '' && is_numeric($bonusRES) && $bonusRES > 0 ? '+' : '') . $bonusRES ?></div>
      </div>
    </div>
    <div class="cap-grid">
      <div class="cap-item">
        <img class="cap-icon" src="/img/ui/icons/use_cc_weapons.webp" alt="Armas cuerpo a cuerpo">
        <div class="cap-label">Armas Cuerpo a Cuerpo</div>
        <div class="cap-value"><?= $useMelee === 1 ? 'Si' : 'No' ?></div>
      </div>
      <div class="cap-item">
        <img class="cap-icon" src="/img/ui/icons/use_firearms.webp" alt="Armas de fuego">
        <div class="cap-label">Armas de Fuego</div>
        <div class="cap-value"><?= $useGuns === 1 ? 'Si' : 'No' ?></div>
      </div>
      <div class="cap-item">
        <img class="cap-icon" src="/img/ui/icons/use_regen.webp" alt="Regeneracion">
        <div class="cap-label">Regeneracion</div>
        <div class="cap-value"><?= ((int)($row["hpregen"] ?? 0) > 0) ? ((int)$row["hpregen"]) . " / turno" : "No" ?></div>
      </div>
    </div>
  </div>

  <div class="form-box">
    <h3>Descripción</h3>
    <div><?= $infoDesc ?></div>
  </div>

  <?php
    $formSystemId = (int)($row['system_id'] ?? 0);
    $maneuvers = hg_systems_fetch_form_maneuvers($link, $formSystemId, $formNameRaw);
    if ($maneuvers === false) $maneuvers = [];
    if (!empty($maneuvers)) {
      echo "<div class='form-box form-box-maneuvers'>";
      echo "<h3>Maniobras de combate</h3>";
      echo "<div class='maneuvers-grid'>";
      foreach ($maneuvers as $m) {
        $maneId = (int)$m['id'];
        $maneName = sf_h((string)($m['name'] ?? ''));
        $maneImg = trim((string)($m['image_url'] ?? ''));
        $thumb = "img/inv/no-photo.webp";
        if ($maneImg !== '') {
          $thumb = (strpos($maneImg, '/') !== false) ? $maneImg : "img/maneuvers/" . $maneImg;
        }
        $manePretty = (string)($m['pretty_id'] ?? '');
        $href = "/rules/maneuvers/" . ($manePretty !== '' ? $manePretty : $maneId);
        echo "<a class='maneuver-item' href='" . sf_h($href) . "'>
                <img class='maneuver-icon' src='" . sf_h($thumb) . "' alt='" . $maneName . "'>
                <div class='maneuver-label'>" . $maneName . "</div>
              </a>";
      }
      echo "</div>";
      echo "</div>";
    }
  ?>
</div>
