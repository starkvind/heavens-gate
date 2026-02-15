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
        $returnType = htmlspecialchars($ResultQuery["afiliacion"]); // Definimos la variable para volver
        $nameWereForm = htmlspecialchars($ResultQuery["forma"]);
        $nameWereBreed = htmlspecialchars($ResultQuery["raza"]);
        $infoDesc = ($ResultQuery["desc"]);
        $imageWereForm = htmlspecialchars($ResultQuery["imagen"]);
        $bonusSTR = htmlspecialchars($ResultQuery["bonfue"]);
        $bonusDEX = htmlspecialchars($ResultQuery["bondes"]);
        $bonusRES = htmlspecialchars($ResultQuery["bonres"]);
        $useMelee = (int)$ResultQuery["armas"];
        $useGuns = (int)$ResultQuery["armasfuego"];

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
        include("app/partials/main_nav_bar.php"); // Barra Navegación

        if ($returnType === "Bastet") {
            $nameWereForm = "$nameWereForm ($nameWereBreed)";
        }
?>
<style>
.form-detail { display:grid; gap:12px; }
.form-banner { position:relative; background:#000033; border:1px solid #000088; border-radius:12px; overflow:hidden; min-height:140px; margin-top:1em; }
.form-banner::before { content:''; display:block; padding-top:28%; }
.form-banner img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
.form-banner-title { position:absolute; top:10px; right:12px; color:#33FFFF; background:rgba(0,0,0,0.55); border:1px solid #1b4aa0; padding:6px 10px; border-radius:8px; font-weight:bold; }
.form-box { background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; }
.form-box h3 { margin-top:0; color:#33FFFF; }
.mod-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:8px; }
.mod-card { background:#00135a; border:1px solid #1b4aa0; border-radius:8px; padding:8px; text-align:center; color:#cfe; }
.mod-card .mod-label{ font-size:12px; color:#9dd; margin-bottom:4px; }
.mod-card .mod-value{ font-size:16px; color:#33FFFF; font-weight:bold; }
@media (max-width:720px){ .mod-grid{ grid-template-columns:1fr; } }
.cap-grid{ display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:10px; margin-top:10px; justify-items:center; }
.cap-item{ display:flex; flex-direction:column; align-items:center; text-align:center; color:#cfe; }
.cap-icon{ width:48px; height:48px; border-radius:50%; object-fit:cover; border:1px solid #000088; background:#000033; box-shadow:0 4px 10px rgba(0,0,0,0.45); }
.cap-label{ margin-top:6px; font-size:12px; color:#9dd; }
.cap-value{ font-size:13px; color:#fff; font-weight:bold; }
</style>
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
    $formNameRaw = $ResultQuery["forma"];
    $likeForm = '%' . $formNameRaw . '%';
    $sqlMan = "SELECT id, pretty_id, name, img FROM fact_combat_maneuvers WHERE sistema = ? AND (user LIKE ? OR user LIKE '%Todas%') ORDER BY name ASC";
    $stmtMan = $link->prepare($sqlMan);
    $stmtMan->bind_param('ss', $returnType, $likeForm);
    $stmtMan->execute();
    $rsMan = $stmtMan->get_result();
    if ($rsMan && $rsMan->num_rows > 0) {
      echo "<div class='form-box' style='padding-bottom: 2.5em;'>";
      echo "<h3>Maniobras de combate</h3>";
      echo "<style>
        .maneuvers-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(96px,1fr));gap:12px;justify-items:center;padding:6px 0;}
        .maneuver-item{display:flex;flex-direction:column;align-items:center;text-decoration:none;color:#fff;min-width:120px;}
        .maneuver-icon{width:48px;height:48px;border-radius:50%;object-fit:cover;border:1px solid #000088;background:#000033;box-shadow:0 4px 10px rgba(0,0,0,0.45);}
        .maneuver-label{margin-top:6px;font-size:12px;line-height:1.1;text-align:center;max-width:140px;}
      </style>";
      echo "<div class='maneuvers-grid'>";
      while ($m = $rsMan->fetch_assoc()) {
        $maneId = (int)$m['id'];
        $maneName = htmlspecialchars($m['name']);
        $maneImg = trim((string)($m['img'] ?? ''));
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
