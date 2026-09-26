<?php

require_once __DIR__ . '/../../domains/systems/queries.php';

$systemCategory = hg_request_param($hgRequest, 'system');
$systemCategoryId = hg_systems_resolve_id($link, 'dim_systems', $systemCategory);
$ordenQueryResult = hg_systems_fetch_system($link, $systemCategoryId);

if (!$ordenQueryResult) {
    $pageSect = "Sistema";
    $pageTitle2 = "Sistema no encontrado";
    if (!defined("HG_MOBILE_DESKTOP_EMBED") || !HG_MOBILE_DESKTOP_EMBED) include("app/partials/main_nav_bar.php");
    echo "<h2>Sistema no encontrado</h2>";
    echo "<div class='renglonDatosSistema'>El sistema solicitado no existe.</div>";
} else {
    $systemNameRaw = (string)($ordenQueryResult["name"] ?? '');
    $systemName = htmlspecialchars($systemNameRaw);
    $systemImg = htmlspecialchars((string)($ordenQueryResult["image_url"] ?? ''));
    $systemDesc = (string)($ordenQueryResult["description"] ?? '');
    $systemForm = (int)($ordenQueryResult["forms"] ?? 0);
    $systemNameAlt = $systemNameRaw;
    $systemDetailLabels = hg_systems_fetch_detail_labels($link, $systemCategoryId);

    if (!empty($systemName)) {
        $pageSect = "Sistema";
        $pageTitle2 = $systemName;
        if (function_exists("setMetaFromPage")) setMetaFromPage($systemName . " | Sistemas | Heaven's Gate", meta_excerpt($systemDesc), $systemImg, 'article');
    }

    if (empty($systemImg)) {
        $systemImg = "img/system/nada.webp";
    }

    if (!defined("HG_MOBILE_DESKTOP_EMBED") || !HG_MOBILE_DESKTOP_EMBED) include("app/partials/main_nav_bar.php");
    if (function_exists('hg_page_register_stylesheet')) {
        hg_page_register_stylesheet('/assets/css/hg-systems.css');
    } else {
        echo '<link rel="stylesheet" href="/assets/css/hg-systems.css">';
    }
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
    $nameAuspice = $systemDetailLabels['label_auspice'] ?? "Auspicios";
    $nameTribe = $systemDetailLabels['label_tribe'] ?? "Tribus";
    $nameMisc = $systemDetailLabels['label_misc'] ?? "Miscelánea";

    if ($systemForm === 1) {
        $forms = hg_systems_fetch_forms($link, $systemCategoryId);
        if ($forms === false) $forms = [];
        $nRowsQueryForms = count($forms);

        if ($nRowsQueryForms > 0) {
            $showToggle = ($nRowsQueryForms > 12);
            $cls = $showToggle ? "syst-section collapsed" : "syst-section";
            $toggleBtn = $showToggle ? "<button type='button' class='syst-toggle' data-toggle='1'></button>" : "";
            echo "<fieldset class='$cls'><legend><b>Formas</b>$toggleBtn</legend>";

            $formsByRace = [];
            foreach ($forms as $formQueryResult) {
                $formId = (int)($formQueryResult["id"] ?? 0);
                $formAffRaw = trim((string)($formQueryResult["breed_name"] ?? $formQueryResult["race"] ?? ''));
                $formAff = htmlspecialchars($formAffRaw !== '' ? $formAffRaw : '-');
                $formName = htmlspecialchars((string)($formQueryResult["form"] ?? ''));
                if ($systemNameRaw === "Bastet") {
                    $formsByRace[$formAff][] = ['id' => $formId, 'name' => $formName];
                } else {
                    $formsByRace['__all'][] = ['id' => $formId, 'name' => $formName, 'aff' => $formAff];
                }
            }

            if ($systemNameRaw === "Bastet") {
                $globalIdx = 0;
                foreach ($formsByRace as $race => $items) {
                    $groupKey = 'grp_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower((string)$race));
                    echo "<div class='syst-subhead' data-group='$groupKey'>" . htmlspecialchars($race) . "</div>";
                    echo "<div class='syst-grid'>";
                    foreach ($items as $it) {
                        $href = pretty_url($link, 'dim_forms', '/systems/form', (int)$it['id']);
                        $label = htmlspecialchars((string)$it['name']);
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
                    $label = htmlspecialchars((string)$it['name']);
                    $cardIdx = $showToggle ? $idx : -1;
                    $dataIdx = $cardIdx >= 0 ? " data-idx='$cardIdx'" : "";
                    echo "<a class='syst-card'$dataIdx href='" . htmlspecialchars($href) . "'>$label</a>";
                    $idx++;
                }
                echo "</div>";
            }

            echo "</fieldset>";
        }
    }

    $sectionDefs = [
        ['label' => 'Razas', 'table' => 'dim_breeds', 'base' => '/systems/breeds'],
        ['label' => $nameAuspice, 'table' => 'dim_auspices', 'base' => '/systems/auspices'],
        ['label' => $nameTribe, 'table' => 'dim_tribes', 'base' => '/systems/tribes'],
    ];

    foreach ($sectionDefs as $sectionDef) {
        $rows = hg_systems_fetch_detail_rows($link, $sectionDef['table'], $systemCategoryId);
        if ($rows === false) $rows = [];
        $nRows = count($rows);
        if ($nRows <= 0) continue;

        $showToggle = ($nRows > 12);
        $cls = $showToggle ? "syst-section collapsed" : "syst-section";
        $toggleBtn = $showToggle ? "<button type='button' class='syst-toggle' data-toggle='1'></button>" : "";
        $label = htmlspecialchars((string)$sectionDef['label']);
        echo "<fieldset class='$cls'><legend><b>$label</b>$toggleBtn</legend><div class='syst-grid'>";
        $idx = 0;
        foreach ($rows as $row) {
            $rowId = (int)($row['id'] ?? 0);
            $rowName = htmlspecialchars((string)($row['name'] ?? ''));
            $rowHref = pretty_url($link, $sectionDef['table'], $sectionDef['base'], $rowId);
            echo "<a class='syst-card' data-idx='$idx' href='" . htmlspecialchars($rowHref) . "'>$rowName</a>";
            $idx++;
        }
        echo "</div></fieldset>";
    }

    $miscRows = hg_systems_fetch_misc($link, $systemNameRaw, $systemNameAlt);
    if ($miscRows === false) $miscRows = [];
    $nRowsQueryMisc = count($miscRows);
    if ($nRowsQueryMisc > 0) {
        $showToggle = ($nRowsQueryMisc > 12);
        $cls = $showToggle ? "syst-section collapsed" : "syst-section";
        $toggleBtn = $showToggle ? "<button type='button' class='syst-toggle' data-toggle='1'></button>" : "";
        echo "<fieldset class='$cls'><legend><b>" . htmlspecialchars($nameMisc) . "</b>$toggleBtn</legend><div class='syst-grid'>";
        $idx = 0;
        foreach ($miscRows as $miscQueryResult) {
            $miscId = (int)($miscQueryResult["id"] ?? 0);
            $miscName = htmlspecialchars((string)($miscQueryResult["name"] ?? ''));
            $miscHref = pretty_url($link, 'fact_misc_systems', '/systems/misc', $miscId);
            echo "<a class='syst-card' data-idx='$idx' href='" . htmlspecialchars($miscHref) . "'>$miscName</a>";
            $idx++;
        }
        echo "</div></fieldset>";
    }

    $resByKind = ['renombre' => [], 'estado' => []];
    $systemResources = hg_systems_fetch_resources($link, $systemCategoryId);
    if ($systemResources === false) $systemResources = [];
    foreach ($systemResources as $rowRes) {
        $kind = strtolower((string)($rowRes['kind'] ?? ''));
        if (!isset($resByKind[$kind])) continue;
        $resByKind[$kind][] = [
            'id' => (int)($rowRes['id'] ?? 0),
            'name' => (string)($rowRes['name'] ?? ''),
            'description' => (string)($rowRes['description'] ?? ''),
        ];
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
                if ($hasDesc) echo "<button type='button' class='syst-toggle syst-toggle-inline' data-toggle-item-desc='1'></button>";
                echo "</div>";
                if ($hasDesc) echo "<div class='syst-pill-desc'>" . $descHtml . "</div>";
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
                if ($hasDesc) echo "<button type='button' class='syst-toggle syst-toggle-inline' data-toggle-item-desc='1'></button>";
                echo "</div>";
                if ($hasDesc) echo "<div class='syst-pill-desc'>" . $descHtml . "</div>";
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
      var cards = fs.querySelectorAll('.syst-card[data-group="'+group+'"]').forEach ? fs.querySelectorAll('.syst-card[data-group="'+group+'"]') : [];
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