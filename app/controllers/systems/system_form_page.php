<?php

include_once(__DIR__ . '/../../helpers/pretty.php');

function sf_h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function sf_has_column(mysqli $link, string $table, string $column): bool {
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    if (!$st = $link->prepare($sql)) return false;
    $st->bind_param('ss', $table, $column);
    $st->execute();
    $rs = $st->get_result();
    $ok = ($rs && $rs->num_rows > 0);
    $st->close();
    return $ok;
}

$formKey = trim((string)($_GET['b'] ?? ''));
$formId = 0;
if ($formKey !== '') {
    if (preg_match('/^\d+$/', $formKey)) {
        $formId = (int)$formKey;
    } else {
        $formId = (int)resolve_pretty_id($link, 'dim_forms', $formKey);
    }
}

if ($formId <= 0) {
    include("app/partials/main_nav_bar.php");
    echo "<h2>Forma no encontrada</h2>";
    echo "<div class='renglonDatosSistema'>La forma solicitada no existe.</div>";
    return;
}

$hasBreedId = sf_has_column($link, 'dim_forms', 'breed_id');
$hasRace = sf_has_column($link, 'dim_forms', 'race');

$select = "
    f.*,
    COALESCE(NULLIF(ds.name, ''), '') AS system_name_resolved
";
$joins = " LEFT JOIN dim_systems ds ON ds.id = f.system_id ";
if ($hasBreedId) {
    $select .= ", " . ($hasRace
        ? "COALESCE(NULLIF(db.name, ''), NULLIF(f.race, ''))"
        : "COALESCE(NULLIF(db.name, ''), '')") . " AS breed_name_resolved ";
    $joins .= " LEFT JOIN dim_breeds db ON db.id = f.breed_id ";
} elseif ($hasRace) {
    $select .= ", COALESCE(NULLIF(db.name, ''), NULLIF(f.race, '')) AS breed_name_resolved ";
    $joins .= " LEFT JOIN dim_breeds db ON db.system_id = f.system_id AND db.name = f.race ";
} else {
    $select .= ", '' AS breed_name_resolved ";
}

$sql = "SELECT {$select} FROM dim_forms f {$joins} WHERE f.id = ? LIMIT 1";
$stmt = $link->prepare($sql);
if (!$stmt) {
    include("app/partials/main_nav_bar.php");
    echo "<h2>Error cargando forma</h2>";
    echo "<div class='renglonDatosSistema'>No se pudo preparar la consulta.</div>";
    return;
}

$stmt->bind_param('i', $formId);
$stmt->execute();
$result = $stmt->get_result();
$row = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    include("app/partials/main_nav_bar.php");
    echo "<h2>Forma no encontrada</h2>";
    echo "<div class='renglonDatosSistema'>La forma solicitada no existe.</div>";
    return;
}

$systemNameRaw = trim((string)($row['system_name_resolved'] ?? ''));
$breedNameRaw = trim((string)($row['breed_name_resolved'] ?? ''));
$formNameRaw = trim((string)($row['form'] ?? ''));

$returnType = $systemNameRaw; // usado por system_category_helper.php
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
setMetaFromPage($formDisplayRaw . " | Formas | Heaven's Gate", meta_excerpt($infoDesc), $imageWereForm, 'article');

include("app/helpers/system_category_helper.php");
include("app/partials/main_nav_bar.php");
echo '<link rel="stylesheet" href="/assets/css/hg-systems.css">';
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
        <img class="cap-icon" src="/img/ui/icons/use_cc_weapons.jpg" alt="Armas cuerpo a cuerpo">
        <div class="cap-label">Armas Cuerpo a Cuerpo</div>
        <div class="cap-value"><?= $useMelee === 1 ? 'Si' : 'No' ?></div>
      </div>
      <div class="cap-item">
        <img class="cap-icon" src="/img/ui/icons/use_firearms.jpg" alt="Armas de fuego">
        <div class="cap-label">Armas de Fuego</div>
        <div class="cap-value"><?= $useGuns === 1 ? 'Si' : 'No' ?></div>
      </div>
      <div class="cap-item">
        <img class="cap-icon" src="/img/ui/icons/use_regen.jpg" alt="Regeneracion">
        <div class="cap-label">Regeneracion</div>
        <div class="cap-value"><?= ((int)($row["hpregen"] ?? 0) > 0) ? ((int)$row["hpregen"]) . " / turno" : "No" ?></div>
      </div>
    </div>
  </div>

  <div class="form-box">
    <h3>Descripcion</h3>
    <div><?= $infoDesc ?></div>
  </div>

  <?php
    // Maniobras de combate para esta forma.
    $likeForm = '%' . $formNameRaw . '%';
    $formSystemId = (int)($row['system_id'] ?? 0);
    $sqlMan = "SELECT id, pretty_id, name, image_url FROM fact_combat_maneuvers WHERE system_id = ? AND (user LIKE ? OR user LIKE '%Todas%') ORDER BY name ASC";
    $stmtMan = $link->prepare($sqlMan);
    if ($stmtMan) {
      $stmtMan->bind_param('is', $formSystemId, $likeForm);
      $stmtMan->execute();
      $rsMan = $stmtMan->get_result();
      if ($rsMan && $rsMan->num_rows > 0) {
        echo "<div class='form-box form-box-maneuvers'>";
        echo "<h3>Maniobras de combate</h3>";
        echo "<div class='maneuvers-grid'>";
        while ($m = $rsMan->fetch_assoc()) {
          $maneId = (int)$m['id'];
          $maneName = sf_h((string)($m['name'] ?? ''));
          $maneImg = trim((string)($m['image_url'] ?? ''));
          $thumb = "img/inv/no-photo.gif";
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
      $stmtMan->close();
    }
  ?>
</div>
