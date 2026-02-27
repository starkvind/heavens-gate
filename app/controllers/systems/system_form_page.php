<?php

// Aseguramos que el parámetro GET 'b' esté definido de manera segura
$systemIdWere = isset($_GET['b']) ? (string)$_GET['b'] : '';

// Preparamos la consulta para evitar inyecciones SQL
$consulta = "SELECT * FROM dim_forms WHERE id = ? LIMIT 1";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $systemIdWere);
$stmt->execute();
$result = $stmt->get_result();

// Comprueba si hay resultados
if ($result->num_rows > 0) {
    // Recupera los datos del sistema
    while ($ResultQuery = $result->fetch_assoc()) {
        $returnType = htmlspecialchars($ResultQuery["affiliation"]); // Definimos la variable para volver
        $nameWereForm = htmlspecialchars($ResultQuery["form"]);
        $nameWereBreed = htmlspecialchars($ResultQuery["race"]);
        $infoDesc = ($ResultQuery["description"] ?? $ResultQuery["description"] ?? "");
        $imageWereForm = htmlspecialchars($ResultQuery["image_url"]);
        $bonusSTR = htmlspecialchars($ResultQuery["strength_bonus"]);
        $bonusDEX = htmlspecialchars($ResultQuery["dexterity_bonus"]);
        $bonusRES = htmlspecialchars($ResultQuery["stamina_bonus"]);
        $useMelee = (int)$ResultQuery["weapons"];
        $useGuns = (int)$ResultQuery["firearms"];

        $canUseMelee = $useMelee === 1
            ? "Esta forma es capaz de utilizar armas cuerpo a cuerpo."
            : "Esta forma <b><u>no</u></b> puede utilizar armas cuerpo a cuerpo.";

        $canUseGuns = $useGuns === 1
            ? "Esta forma es capaz de utilizar armas de fuego."
            : "Esta forma <b><u>no</u></b> puede utilizar armas de fuego.";

        $pageSect = "Forma"; // PARA CAMBIAR EL TITULO A LA PAGINA
        $pageTitle2 = $nameWereForm;
        setMetaFromPage($nameWereForm . " | Formas | Heaven's Gate", meta_excerpt($infoDesc), $imageWereForm, 'article'); 

        include ("app/helpers/system_category_helper.php");
        include("app/partials/main_nav_bar.php"); // Barra Navegacion
        echo '<link rel="stylesheet" href="/assets/css/hg-systems.css">';

        if ($returnType === "Bastet") {
            $nameWereForm = "$nameWereForm ($nameWereBreed)";
        }
?>
<div class="form-detail">
  <div class="form-banner">
    <?php if ($imageWereForm !== ''): ?>
      <img src="<?= htmlspecialchars($imageWereForm) ?>" alt="<?= htmlspecialchars($nameWereForm) ?>">
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
        <div class="cap-value"><?= $useMelee === 1 ? 'Sí' : 'No' ?></div>
      </div>
      <div class="cap-item">
        <img class="cap-icon" src="/img/ui/icons/use_firearms.jpg" alt="Armas de fuego">
        <div class="cap-label">Armas de Fuego</div>
        <div class="cap-value"><?= $useGuns === 1 ? 'Sí' : 'No' ?></div>
      </div>
      <div class="cap-item">
        <img class="cap-icon" src="/img/ui/icons/use_regen.jpg" alt="Regeneración">
        <div class="cap-label">Regeneración</div>
        <div class="cap-value"><?= ($ResultQuery["hpregen"] ?? 0) > 0 ? ((int)$ResultQuery["hpregen"]) . " / turno" : "No" ?></div>
      </div>
    </div>
  </div>

  <div class="form-box">
    <h3>Descripción</h3>
    <div><?= $infoDesc ?></div>
  </div>

  <?php
    // Maniobras de combate para esta forma
    $formNameRaw = $ResultQuery["form"] ?? $ResultQuery["forma"] ?? '';
    $likeForm = '%' . $formNameRaw . '%';
    $formSystemId = (int)($ResultQuery['system_id'] ?? 0);
    $sqlMan = "SELECT id, pretty_id, name, image_url FROM fact_combat_maneuvers WHERE system_id = ? AND (user LIKE ? OR user LIKE '%Todas%') ORDER BY name ASC";
    $stmtMan = $link->prepare($sqlMan);
    $stmtMan->bind_param('is', $formSystemId, $likeForm);
    $stmtMan->execute();
    $rsMan = $stmtMan->get_result();
    if ($rsMan && $rsMan->num_rows > 0) {
      echo "<div class='form-box form-box-maneuvers'>";
      echo "<h3>Maniobras de combate</h3>";
      echo "<div class='maneuvers-grid'>";
      while ($m = $rsMan->fetch_assoc()) {
        $maneId = (int)$m['id'];
        $maneName = htmlspecialchars($m['name']);
        $maneImg = trim((string)($m['image_url'] ?? ''));
        $thumb = "img/inv/no-photo.gif";
        if ($maneImg !== '') {
          $thumb = (strpos($maneImg, '/') !== false) ? $maneImg : "img/maneuvers/" . $maneImg;
        }
        $manePretty = (string)($m['pretty_id'] ?? '');
        $href = "/rules/maneuvers/" . ($manePretty !== '' ? $manePretty : $maneId);
        echo "<a class='maneuver-item' href='" . htmlspecialchars($href) . "'>
                <img class='maneuver-icon' src='" . htmlspecialchars($thumb) . "' alt='" . htmlspecialchars($maneName) . "'>
                <div class='maneuver-label'>$maneName</div>
              </a>";
      }
      echo "</div>";
      echo "</div>";
    }
    $stmtMan->close();
  ?>
</div>
<?php
    }
}

// Cierra el statement
//$stmt->close();

?>


