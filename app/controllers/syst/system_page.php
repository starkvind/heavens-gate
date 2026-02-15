<?php

// Aseguramos que el parámetro GET 'b' esté definido de manera segura
$systemCategory = isset($_GET['b']) ? (string)$_GET['b'] : '';
include_once(__DIR__ . '/../../helpers/pretty.php');

$systemCategoryId = 0;
if ($systemCategory !== '') {
    if (preg_match('/^\d+$/', $systemCategory)) {
        $systemCategoryId = (int)$systemCategory;
    } else {
        $systemCategoryId = (int)resolve_pretty_id($link, 'dim_systems', $systemCategory);
    }
}

// =========================================================== >
// Preparamos la consulta para evitar inyecciones SQL
$ordenQuery = "SELECT name, img, descripcion, formas FROM dim_systems WHERE id = ? LIMIT 1";
$stmt = $link->prepare($ordenQuery);
$stmt->bind_param('i', $systemCategoryId);
$stmt->execute();
$result = $stmt->get_result();
$ordenQueryResult = $result->fetch_assoc();

if (!$ordenQueryResult) {
    $pageSect = "Sistema";
    $pageTitle2 = "Sistema no encontrado";
    include("app/partials/main_nav_bar.php");
    echo "<h2>Sistema no encontrado</h2>";
    echo "<div class='renglonDatosSistema'>El sistema solicitado no existe.</div>";
} else {
    $systemName = htmlspecialchars($ordenQueryResult["name"]);
    $systemImg = htmlspecialchars($ordenQueryResult["img"]);
    $systemDesc = ($ordenQueryResult["descripcion"]);
    $systemForm = (int)$ordenQueryResult["formas"];
    $systemNameRaw = (string)$ordenQueryResult["name"];

    // CAMBIAR EL TITULO A LA PAGINA
    if (!empty($systemName)) { 
        $pageSect = "Sistema"; 
        $pageTitle2 = $systemName;
		setMetaFromPage("Sistemas | Heaven's Gate", meta_excerpt($systemDesc), $systemImg, 'article'); 
    }

    // PONER IMAGEN "NADA" SI NO TIENE IMAGEN ASIGNADA
    if (empty($systemImg)) {
        $systemImg = "img/system/nada.jpg";
    }

    // =========================================================== >
    include("app/partials/main_nav_bar.php"); // Barra Navegación
?>
<style>
.syst-page { display:block; }
.syst-banner { position:relative; background:#000033; border:1px solid #000088; border-radius:12px; overflow:hidden; min-height:140px; }
.syst-banner::before { content:''; display:block; padding-top:28%; }
.syst-banner img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; filter:saturate(1.05); }
.syst-banner-title { position:absolute; top:10px; right:12px; color:#33FFFF; background:rgba(0,0,0,0.55); border:1px solid #1b4aa0; padding:6px 10px; border-radius:8px; font-weight:bold; }
.syst-desc { margin-top:10px; color:#fff; }
.syst-banner { margin-top:1em; }
.syst-sections { margin-top:14px; display:grid; gap:12px; }
.syst-section { border:1px solid #000088; border-radius:12px; padding:10px; background:#05014E; }
.syst-section legend { color:#33FFFF; font-weight:bold; }
.syst-section .syst-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:8px; }
.syst-card { background:#00135a; border:1px solid #1b4aa0; border-radius:8px; padding:8px 10px; color:#cfe; text-decoration:none; display:block; }
.syst-card:hover { background:#001b7a; color:#33FFFF; }
.syst-subhead { margin:10px 0 6px; color:#9dd; font-weight:bold; }
.syst-toggle { float:right; font-size:14px; border:0; background:transparent; color:#cfe; border-radius:8px; padding:2px 6px; cursor:pointer; }
.syst-toggle::after { content:"▲"; }
.syst-section.collapsed .syst-toggle::after { content:"▼"; }
.syst-section.collapsed .syst-toggle { opacity:0.9; }
.syst-section .syst-toggle { opacity:0.9; }
.syst-section .syst-toggle { margin-left:8px; }
.syst-section legend { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.syst-section.collapsed .syst-card[data-idx] { display:none; }
.syst-section.collapsed .syst-card[data-idx="0"],
.syst-section.collapsed .syst-card[data-idx="1"],
.syst-section.collapsed .syst-card[data-idx="2"],
.syst-section.collapsed .syst-card[data-idx="3"],
.syst-section.collapsed .syst-card[data-idx="4"],
.syst-section.collapsed .syst-card[data-idx="5"],
.syst-section.collapsed .syst-card[data-idx="6"],
.syst-section.collapsed .syst-card[data-idx="7"],
.syst-section.collapsed .syst-card[data-idx="8"],
.syst-section.collapsed .syst-card[data-idx="9"],
.syst-section.collapsed .syst-card[data-idx="10"],
.syst-section.collapsed .syst-card[data-idx="11"] { display:block; }
</style>
<div class="syst-page">
  <div class="syst-banner">
    <?php if ($systemImg !== ''): ?>
      <img src="<?= htmlspecialchars($systemImg) ?>" alt="<?= htmlspecialchars($systemName) ?>">
    <?php endif; ?>
    <h2 class="syst-banner-title"><?= $systemName ?></h2>
  </div>
  <div class="syst-desc"><?= $systemDesc ?></div>

  <div class="syst-sections">
<?php
    // =========================================================== >
    switch ($systemName) {
        case "Bastet":
            $nameAuspice = "Pryios";
            $nameTribe = "Tribus";
            break;
        case "Ananasi":
            $nameAuspice = "Facciones";
            $nameTribe = "Aspectos";
            break;
        case "Mokole":
        case "Mokolé":
            $nameAuspice = "Auspicios";
            $nameTribe = "Oleadas";
            $nameMisc = "Varnas";
            break;
        case "Changelling":
            $nameAuspice = "Aspectos";
            $nameTribe = "Linajes";
            $nameMisc = "Legados";
            break;
        case "Vampiro":
            $nameTribe = "Clanes";
            break;
        default:
            $nameAuspice = "Auspicios";
            $nameTribe = "Tribus";
            $nameMisc = "";
            break;
    }

    // CUADRO DE FORMAS
    if ($systemForm === 1) {
        $queryForms = "SELECT * FROM dim_forms WHERE system_id = ?";
        $stmtForms = $link->prepare($queryForms);
        $stmtForms->bind_param('i', $systemCategoryId);
        $stmtForms->execute();
        $resultForms = $stmtForms->get_result();
        $nRowsQueryForms = $resultForms->num_rows;

        if ($nRowsQueryForms > 0) {
            $showToggle = ($nRowsQueryForms > 12);
            $cls = $showToggle ? "syst-section collapsed" : "syst-section";
            $toggleBtn = $showToggle ? "<button type='button' class='syst-toggle' data-toggle='1'></button>" : "";
            echo "<fieldset class='$cls'><legend><b>Formas</b>$toggleBtn</legend>";

            $formsByRace = [];
            while ($formQueryResult = $resultForms->fetch_assoc()) {
                $formId = (int)$formQueryResult["id"];
                $formAff = htmlspecialchars($formQueryResult["raza"]);
                $formName = htmlspecialchars($formQueryResult["forma"]);
                if ($systemName === "Bastet") {
                    $formsByRace[$formAff][] = ['id'=>$formId,'name'=>$formName];
                } else {
                    $formsByRace['__all'][] = ['id'=>$formId,'name'=>$formName, 'aff'=>$formAff];
                }
            }

            if ($systemName === "Bastet") {
                $globalIdx = 0;
                foreach ($formsByRace as $race => $items) {
                    $groupKey = 'grp_' . preg_replace('/[^a-z0-9]+/i','_', strtolower((string)$race));
                    echo "<div class='syst-subhead' data-group='$groupKey'>".htmlspecialchars($race)."</div>";
                    echo "<div class='syst-grid'>";
                    foreach ($items as $it) {
                        $href = "/systems/form/".(int)$it['id'];
                        $label = htmlspecialchars($it['name']);
                        $cardIdx = $showToggle ? $globalIdx : -1;
                        $dataIdx = $cardIdx >= 0 ? " data-idx='$cardIdx'" : "";
                        echo "<a class='syst-card'$dataIdx data-group='$groupKey' href='$href'>$label</a>";
                        $globalIdx++;
                    }
                    echo "</div>";
                }
            } else {
                echo "<div class='syst-grid'>";
                $idx = 0;
                foreach ($formsByRace['__all'] ?? [] as $it) {
                    $href = "/systems/form/".(int)$it['id'];
                    $label = htmlspecialchars($it['name']);
                    $cardIdx = $showToggle ? $idx : -1;
                    $dataIdx = $cardIdx >= 0 ? " data-idx='$cardIdx'" : "";
                    echo "<a class='syst-card'$dataIdx href='$href'>$label</a>";
                    $idx++;
                }
                echo "</div>";
            }

            echo "</fieldset>";
        }

        $stmtForms->close();
    }

    // CUADRO DE RAZAS
    $queryRaces = "SELECT * FROM dim_breeds WHERE system_id = ? ORDER BY id";
    $stmtRaces = $link->prepare($queryRaces);
    $stmtRaces->bind_param('i', $systemCategoryId);
    $stmtRaces->execute();
    $resultRaces = $stmtRaces->get_result();
    $nRowsQueryRaces = $resultRaces->num_rows;

    if ($nRowsQueryRaces > 0) {
        $showToggle = ($nRowsQueryRaces > 12);
        $cls = $showToggle ? "syst-section collapsed" : "syst-section";
        $toggleBtn = $showToggle ? "<button type='button' class='syst-toggle' data-toggle='1'></button>" : "";
        echo "<fieldset class='$cls'><legend><b>Razas</b>$toggleBtn</legend><div class='syst-grid'>";
        $idx = 0;
        while ($raceQueryResult = $resultRaces->fetch_assoc()) {
            $raceId = (int)$raceQueryResult["id"];
            $raceName = htmlspecialchars($raceQueryResult["name"]);
            $raceHref = pretty_url($link, 'dim_breeds', '/systems/breeds', (int)$raceId);
            echo "<a class='syst-card' data-idx='$idx' href='" . htmlspecialchars($raceHref) . "'>$raceName</a>";
            $idx++;
        }
        echo "</div></fieldset>";
    }
    $stmtRaces->close();

    // CUADRO DE AUSPICIOS
    $queryAuspices = "SELECT * FROM dim_auspices WHERE system_id = ? ORDER BY id";
    $stmtAuspices = $link->prepare($queryAuspices);
    $stmtAuspices->bind_param('i', $systemCategoryId);
    $stmtAuspices->execute();
    $resultAuspices = $stmtAuspices->get_result();
    $nRowsQueryAuspices = $resultAuspices->num_rows;

    if ($nRowsQueryAuspices > 0) {
        $showToggle = ($nRowsQueryAuspices > 12);
        $cls = $showToggle ? "syst-section collapsed" : "syst-section";
        $toggleBtn = $showToggle ? "<button type='button' class='syst-toggle' data-toggle='1'></button>" : "";
        echo "<fieldset class='$cls'><legend><b>$nameAuspice</b>$toggleBtn</legend><div class='syst-grid'>";
        $idx = 0;
        while ($auspiceQueryResult = $resultAuspices->fetch_assoc()) {
            $auspiceId = (int)$auspiceQueryResult["id"];
            $auspiceName = htmlspecialchars($auspiceQueryResult["name"]);
            $auspiceHref = pretty_url($link, 'dim_auspices', '/systems/auspices', (int)$auspiceId);
            echo "<a class='syst-card' data-idx='$idx' href='" . htmlspecialchars($auspiceHref) . "'>$auspiceName</a>";
            $idx++;
        }
        echo "</div></fieldset>";
    }
    $stmtAuspices->close();

    // CUADRO DE TRIBUS
    $queryTribes = "SELECT * FROM dim_tribes WHERE system_id = ? ORDER BY id";
    $stmtTribes = $link->prepare($queryTribes);
    $stmtTribes->bind_param('i', $systemCategoryId);
    $stmtTribes->execute();
    $resultTribes = $stmtTribes->get_result();
    $nRowsQueryTribes = $resultTribes->num_rows;

    if ($nRowsQueryTribes > 0) {
        $showToggle = ($nRowsQueryTribes > 12);
        $cls = $showToggle ? "syst-section collapsed" : "syst-section";
        $toggleBtn = $showToggle ? "<button type='button' class='syst-toggle' data-toggle='1'></button>" : "";
        echo "<fieldset class='$cls'><legend><b>$nameTribe</b>$toggleBtn</legend><div class='syst-grid'>";
        $idx = 0;
        while ($tribeQueryResult = $resultTribes->fetch_assoc()) {
            $tribeId = (int)$tribeQueryResult["id"];
            $tribeName = htmlspecialchars($tribeQueryResult["name"]);
            $tribeHref = pretty_url($link, 'dim_tribes', '/systems/tribes', (int)$tribeId);
            echo "<a class='syst-card' data-idx='$idx' href='" . htmlspecialchars($tribeHref) . "'>$tribeName</a>";
            $idx++;
        }
        echo "</div></fieldset>";
    }
    $stmtTribes->close();

    // CUADRO MISCELÁNEA
    $queryMisc = "SELECT id, name, type FROM fact_misc_systems WHERE sistema = ? OR sistema = ? ORDER BY id";
    $stmtMisc = $link->prepare($queryMisc);
    $stmtMisc->bind_param('ss', $systemNameRaw, $systemNameAlt);
    $stmtMisc->execute();
    $resultMisc = $stmtMisc->get_result();
    $nRowsQueryMisc = $resultMisc->num_rows;

    if ($nRowsQueryMisc > 0) {
        $showToggle = ($nRowsQueryMisc > 12);
        $cls = $showToggle ? "syst-section collapsed" : "syst-section";
        $toggleBtn = $showToggle ? "<button type='button' class='syst-toggle' data-toggle='1'></button>" : "";
        echo "<fieldset class='$cls'><legend><b>$nameMisc</b>$toggleBtn</legend><div class='syst-grid'>";
        $idx = 0;
        while ($miscQueryResult = $resultMisc->fetch_assoc()) {
            $miscId = (int)$miscQueryResult["id"];
            $miscName = htmlspecialchars($miscQueryResult["name"]);
            $miscHref = pretty_url($link, 'fact_misc_systems', '/systems/misc', (int)$miscId);
            echo "<a class='syst-card' data-idx='$idx' href='" . htmlspecialchars($miscHref) . "'>$miscName</a>";
            $idx++;
        }
        echo "</div></fieldset>";
    }
    $stmtMisc->close();
?>
  </div>
</div>
<script>
(function(){
  function updateSubheads(fs){
    if (!fs) return;
    var collapsed = fs.classList.contains('collapsed');
    fs.querySelectorAll('.syst-subhead[data-group]').forEach(function(sh){
      var group = sh.getAttribute('data-group');
      var cards = fs.querySelectorAll('.syst-card[data-group=\"'+group+'\"]').forEach ? fs.querySelectorAll('.syst-card[data-group=\"'+group+'\"]') : [];
      var hasVisible = false;
      cards.forEach(function(card){
        if (!collapsed) { hasVisible = true; return; }
        var idx = card.getAttribute('data-idx');
        if (idx === null) { hasVisible = true; return; }
        if (parseInt(idx,10) <= 11) { hasVisible = true; }
      });
      sh.style.display = hasVisible ? 'block' : 'none';
    });
  }

  document.querySelectorAll('.syst-section').forEach(function(fs){
    updateSubheads(fs);
  });

  document.querySelectorAll('.syst-toggle').forEach(function(btn){
    btn.addEventListener('click', function(){
      var fs = btn.closest('.syst-section');
      if (!fs) return;
      fs.classList.toggle('collapsed');
      updateSubheads(fs);
    });
  });
})();
</script>
<?php
}
?>
