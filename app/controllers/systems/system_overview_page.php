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

function so_has_column(mysqli $link, string $table, string $column): bool {
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    if (!$st = $link->prepare($sql)) return false;
    $st->bind_param('ss', $table, $column);
    $st->execute();
    $rs = $st->get_result();
    $ok = ($rs && $rs->num_rows > 0);
    $st->close();
    return $ok;
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
		setMetaFromPage($systemName . " | Sistemas | Heaven's Gate", meta_excerpt($systemDesc), $systemImg, 'article'); 
    }

    // PONER IMAGEN "NADA" SI NO TIENE IMAGEN ASIGNADA
    if (empty($systemImg)) {
        $systemImg = "img/system/nada.jpg";
    }

    // =========================================================== >
    include("app/partials/main_nav_bar.php"); // Barra Navegacion
    echo '<link rel="stylesheet" href="/assets/css/hg-systems.css">';
?>
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
        $hasFormBreedId = so_has_column($link, 'dim_forms', 'breed_id');
        $hasFormRace = so_has_column($link, 'dim_forms', 'race');
        $selectRace = $hasFormRace ? "f.race" : "''";
        if ($hasFormBreedId) {
            $selectBreed = $hasFormRace
                ? "COALESCE(NULLIF(db.name,''), NULLIF(f.race,''))"
                : "COALESCE(NULLIF(db.name,''), '')";
        } else {
            $selectBreed = $hasFormRace
                ? "COALESCE(NULLIF(db.name,''), NULLIF(f.race,''))"
                : "''";
        }
        $queryForms = "SELECT f.id, f.form, {$selectRace} AS race, {$selectBreed} AS breed_name
                       FROM dim_forms f ";
        if ($hasFormBreedId) {
            $queryForms .= "LEFT JOIN dim_breeds db ON db.id = f.breed_id ";
        } elseif ($hasFormRace) {
            $queryForms .= "LEFT JOIN dim_breeds db ON db.system_id = f.system_id AND db.name = f.race ";
        }
        $queryForms .= "WHERE f.system_id = ? ORDER BY " . ($hasFormRace ? "f.race, " : "") . "f.form";
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
                $formAffRaw = trim((string)($formQueryResult["breed_name"] ?? $formQueryResult["race"] ?? ''));
                $formAff = htmlspecialchars($formAffRaw !== '' ? $formAffRaw : '-');
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
                        $href = pretty_url($link, 'dim_forms', '/systems/form', (int)$it['id']);
                        $label = htmlspecialchars($it['name']);
                        $cardIdx = $showToggle ? $globalIdx : -1;
                        $dataIdx = $cardIdx >= 0 ? " data-idx='$cardIdx'" : "";
                        echo "<a class='syst-card'$dataIdx data-group='$groupKey' href='" . htmlspecialchars($href) . "'>$label</a>";
                        $globalIdx++;
                    }
                    echo "</div>";
                }
            } else {
                echo "<div class='syst-grid'>";
                $idx = 0;
                foreach ($formsByRace['__all'] ?? [] as $it) {
                    $href = pretty_url($link, 'dim_forms', '/systems/form', (int)$it['id']);
                    $label = htmlspecialchars($it['name']);
                    $cardIdx = $showToggle ? $idx : -1;
                    $dataIdx = $cardIdx >= 0 ? " data-idx='$cardIdx'" : "";
                    echo "<a class='syst-card'$dataIdx href='" . htmlspecialchars($href) . "'>$label</a>";
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
