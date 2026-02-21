<?php

// Aseguramos que el parametro GET 'b' este definido de manera segura
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

function fetch_system_detail_labels(mysqli $link, int $systemId): array {
    $labels = [];
    if ($systemId <= 0) return $labels;

    $columns = [];
    if ($stCols = $link->prepare("SHOW COLUMNS FROM bridge_systems_detail_labels")) {
        $stCols->execute();
        $rsCols = $stCols->get_result();
        if ($rsCols) {
            while ($col = $rsCols->fetch_assoc()) {
                $name = (string)($col['Field'] ?? '');
                if ($name !== '') $columns[$name] = true;
            }
            $rsCols->free();
        }
        $stCols->close();
    }
    if (empty($columns) || !isset($columns['system_id'])) return $labels;

    $candidates = ['label_auspice', 'label_tribe', 'label_misc'];
    $selectCols = [];
    foreach ($candidates as $c) {
        if (isset($columns[$c])) $selectCols[] = $c;
    }
    if (empty($selectCols)) return $labels;

    $sql = "SELECT " . implode(', ', $selectCols) . " FROM bridge_systems_detail_labels WHERE system_id = ? LIMIT 1";
    if ($st = $link->prepare($sql)) {
        $st->bind_param('i', $systemId);
        $st->execute();
        $res = $st->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            foreach ($selectCols as $c) {
                $v = trim((string)($row[$c] ?? ''));
                if ($v !== '') $labels[$c] = $v;
            }
        }
        $st->close();
    }
    return $labels;
}

// =========================================================== >
// Preparamos la consulta para evitar inyecciones SQL
$ordenQuery = "SELECT name, image_url, description, forms FROM dim_systems WHERE id = ? LIMIT 1";
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
    $systemImg = htmlspecialchars($ordenQueryResult["image_url"]);
    $systemDesc = ($ordenQueryResult["description"]);
    $systemForm = (int)$ordenQueryResult["forms"];
    $systemNameRaw = (string)$ordenQueryResult["name"];
    $systemNameAlt = $systemNameRaw;
    $systemDetailLabels = fetch_system_detail_labels($link, $systemCategoryId);

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
    include("app/partials/main_nav_bar.php"); // Barra Navegacion
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
.syst-toggle::after { content:"\25B2"; }
.syst-section.collapsed .syst-toggle::after { content:"\25BC"; }
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
.syst-pill { background:#00135a; border:1px solid #1b4aa0; border-radius:8px; padding:8px 10px; color:#cfe; display:block; }
.syst-pill-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.syst-pill-title { color:#9ff; font-weight:bold; margin-bottom:4px; }
.syst-pill-desc { color:#d9e7ff; font-size:12px; line-height:1.25; }
.syst-resource-list { display:flex; flex-direction:column; gap:8px; }
.syst-pill.collapsed-desc-item .syst-pill-desc { display:none; }
.syst-toggle.syst-toggle-inline { float:none; margin-left:0; font-size:12px; padding:0 4px; line-height:1; }
.syst-toggle.syst-toggle-inline[data-toggle-item-desc]::after { content:"\25BC"; }
.syst-pill:not(.collapsed-desc-item) .syst-toggle.syst-toggle-inline[data-toggle-item-desc]::after { content:"\25B2"; }
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
    $nameAuspice = $systemDetailLabels['label_auspice'] ?? "Auspicios";
    $nameTribe = $systemDetailLabels['label_tribe'] ?? "Tribus";
    $nameMisc = $systemDetailLabels['label_misc'] ?? "Miscelánea";

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
                $formAff = htmlspecialchars($formQueryResult["race"]);
                $formName = htmlspecialchars($formQueryResult["form"]);
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

    // CUADRO MISCELANEA
    $queryMisc = "SELECT id, name, kind FROM fact_misc_systems WHERE system_name = ? OR system_name = ? ORDER BY id";
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

    // CUADRO DE RECURSOS ASOCIADOS (RENOMBRE / ESTADO)
    $resByKind = ['renombre' => [], 'estado' => []];
    if ($stCols = $link->prepare("SHOW COLUMNS FROM bridge_systems_resources_to_system LIKE 'is_active'")) {
        $stCols->execute();
        $rsCols = $stCols->get_result();
        $hasActive = ($rsCols && $rsCols->num_rows > 0);
        $stCols->close();
    } else {
        $hasActive = false;
    }

    $sqlSysRes = "
        SELECT r.id, r.name, r.kind, r.description, b.sort_order
        FROM bridge_systems_resources_to_system b
        INNER JOIN dim_systems_resources r ON r.id = b.resource_id
        WHERE b.system_id = ?
          AND r.kind IN ('renombre','estado')
          " . ($hasActive ? "AND b.is_active = 1" : "") . "
        ORDER BY
            r.kind,
            COALESCE(NULLIF(CAST(b.sort_order AS SIGNED), 0), CAST(r.sort_order AS SIGNED), 9999),
            CAST(r.sort_order AS SIGNED),
            r.name
    ";
    if ($stSysRes = $link->prepare($sqlSysRes)) {
        $stSysRes->bind_param('i', $systemCategoryId);
        $stSysRes->execute();
        $rsSysRes = $stSysRes->get_result();
        while ($rowRes = $rsSysRes->fetch_assoc()) {
            $kind = strtolower((string)($rowRes['kind'] ?? ''));
            if (!isset($resByKind[$kind])) continue;
            $resByKind[$kind][] = [
                'id' => (int)($rowRes['id'] ?? 0),
                'name' => (string)($rowRes['name'] ?? ''),
                'description' => (string)($rowRes['description'] ?? ''),
            ];
        }
        $stSysRes->close();
    }

    $hasRenombre = !empty($resByKind['renombre']);
    $hasEstado = !empty($resByKind['estado']);
    if ($hasRenombre || $hasEstado) {
        echo "<fieldset class='syst-section'><legend><b>Recursos</b></legend>";
        if ($hasRenombre) {
            echo "<div class='syst-subhead'>Renombre</div>";
            echo "<div class='syst-resource-list'>";
            foreach ($resByKind['renombre'] as $resItem) {
                $descHtml = html_entity_decode((string)($resItem['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $hasDesc = (trim((string)$descHtml) !== '');
                echo "<div class='syst-pill" . ($hasDesc ? " collapsed-desc-item" : "") . "'>";
                echo "<div class='syst-pill-head'>";
                echo "<div class='syst-pill-title'>" . htmlspecialchars((string)$resItem['name']) . "</div>";
                if ($hasDesc) {
                    echo "<button type='button' class='syst-toggle syst-toggle-inline' data-toggle-item-desc='1'></button>";
                }
                echo "</div>";
                if ($hasDesc) {
                    echo "<div class='syst-pill-desc'>" . $descHtml . "</div>";
                }
                echo "</div>";
            }
            echo "</div>";
        }
        if ($hasEstado) {
            echo "<div class='syst-subhead'>Estado</div>";
            echo "<div class='syst-resource-list'>";
            foreach ($resByKind['estado'] as $resItem) {
                $descHtml = html_entity_decode((string)($resItem['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $hasDesc = (trim((string)$descHtml) !== '');
                echo "<div class='syst-pill" . ($hasDesc ? " collapsed-desc-item" : "") . "'>";
                echo "<div class='syst-pill-head'>";
                echo "<div class='syst-pill-title'>" . htmlspecialchars((string)$resItem['name']) . "</div>";
                if ($hasDesc) {
                    echo "<button type='button' class='syst-toggle syst-toggle-inline' data-toggle-item-desc='1'></button>";
                }
                echo "</div>";
                if ($hasDesc) {
                    echo "<div class='syst-pill-desc'>" . $descHtml . "</div>";
                }
                echo "</div>";
            }
            echo "</div>";
        }
        echo "</fieldset>";
    }
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
      if (btn.hasAttribute('data-toggle-item-desc')) return;
      var fs = btn.closest('.syst-section');
      if (!fs) return;
      fs.classList.toggle('collapsed');
      updateSubheads(fs);
    });
  });

  document.querySelectorAll('.syst-toggle[data-toggle-item-desc]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var pill = btn.closest('.syst-pill');
      if (!pill) return;
      pill.classList.toggle('collapsed-desc-item');
    });
  });
})();
</script>
<?php
}
?>

