<?php

require_once __DIR__ . '/../../domains/systems/queries.php';

$systemIdDocument = hg_request_param($hgRequest, 'system_detail');
$systemTypeDocument = (int)hg_request_param($hgRequest, 'detail_type');
$excludeChronicles = isset($excludeChronicles) ? hg_systems_normalize_int_csv($excludeChronicles) : '2,7';

$detailDef = hg_systems_table_for_detail_type($systemTypeDocument);
$table = $detailDef['table'] ?? '';

if ($table !== '') {
    $infoDataCheck = 0;
    $resolvedId = hg_systems_resolve_id($link, $table, $systemIdDocument);

    if ($resolvedId <= 0) {
        $pageSect = "Sistema";
        $pageTitle2 = "Elemento no encontrado";
        if (!defined("HG_MOBILE_DESKTOP_EMBED") || !HG_MOBILE_DESKTOP_EMBED) include("app/partials/main_nav_bar.php");
        if (function_exists('hg_page_register_stylesheet')) {
            hg_page_register_stylesheet('/assets/css/hg-systems.css');
        } else {
            echo '<link rel="stylesheet" href="/assets/css/hg-systems.css">';
        }
        echo "<h2>Elemento no encontrado</h2>";
        echo "<div class='hg-system-message'>El contenido solicitado no existe.</div>";
        return;
    }

    $ResultQuery = hg_systems_fetch_detail($link, $systemTypeDocument, $resolvedId);
    if (!$ResultQuery) {
        $pageSect = "Sistema";
        $pageTitle2 = "Elemento no encontrado";
        if (!defined("HG_MOBILE_DESKTOP_EMBED") || !HG_MOBILE_DESKTOP_EMBED) include("app/partials/main_nav_bar.php");
        if (function_exists('hg_page_register_stylesheet')) {
            hg_page_register_stylesheet('/assets/css/hg-systems.css');
        } else {
            echo '<link rel="stylesheet" href="/assets/css/hg-systems.css">';
        }
        echo "<h2>Elemento no encontrado</h2>";
        echo "<div class='hg-system-message'>El contenido solicitado no existe.</div>";
        return;
    }

    $returnTypeRaw = (string)($ResultQuery["system_name"] ?? '');
    $returnType = htmlspecialchars($returnTypeRaw);
    $typeOfSystem = $returnType;
    $nameSystRaw = (string)($ResultQuery["name"] ?? '');
    $nameSyst = htmlspecialchars($nameSystRaw);
    $infoDesc = ($ResultQuery["description"] ?? "");
    $systemId = (int)($ResultQuery["system_id"] ?? 0);
    $imageSyst = isset($ResultQuery["image_url"]) ? htmlspecialchars($ResultQuery["image_url"]) : "";

    $pageSect = $returnType;
    $pageTitle2 = $nameSyst;
    if (function_exists("setMetaFromPage")) setMetaFromPage($nameSyst . " | Sistemas | Heaven's Gate", meta_excerpt($infoDesc), $imageSyst, 'article');

    include("app/helpers/system_category_helper.php");
    if (!defined("HG_MOBILE_DESKTOP_EMBED") || !HG_MOBILE_DESKTOP_EMBED) include("app/partials/main_nav_bar.php");
    if (function_exists('hg_page_register_stylesheet')) {
        hg_page_register_stylesheet('/assets/css/hg-systems.css');
    } else {
        echo '<link rel="stylesheet" href="/assets/css/hg-systems.css">';
    }

    $checkEnergy = isset($ResultQuery["energy"]) ? (int)$ResultQuery["energy"] : 0;
    $energyEntries = hg_ser_energy_entries_for_row($link, $table, $resolvedId, $ResultQuery, $returnTypeRaw);

    if ($returnType === "Icaros" || $returnType === "Ícaros") {
        $energy = "Fuerza de Voluntad";
    }

    if (($returnType === "Mokole" || $returnType === "Mokolé") && $systemTypeDocument == 2) {
        $energy = "Fuerza de Voluntad";
    }
?>
<div class="syst-detail">
  <div class="syst-banner">
    <?php if ($imageSyst !== ''): ?>
      <img src="<?= htmlspecialchars($imageSyst) ?>" alt="<?= htmlspecialchars($nameSyst) ?>">
    <?php endif; ?>
    <h2 class="syst-banner-title"><?= $nameSyst ?></h2>
  </div>

<?php
    $metaHtml = '';
    if ($systemTypeDocument == 4) {
        $miscInfoData = ($ResultQuery["extra_info"] ?? '');
        if ($miscInfoData != "") {
            $metaHtml .= "<p>$miscInfoData</p>";
            $infoDataCheck++;
        }
    }

    if (!empty($energyEntries)) {
        foreach ($energyEntries as $energyEntry) {
            $energyLabel = htmlspecialchars((string)($energyEntry['resource_name'] ?? ''));
            $energyValue = (int)($energyEntry['energy_value'] ?? 0);
            if ($energyLabel === '' || $energyValue <= 0) continue;
            $infoDataCheck++;
            $metaHtml .= "<p><b>$energyLabel inicial:</b> $energyValue</p>";
        }
    } elseif ($checkEnergy != 0) {
        $infoDataCheck++;
        $energyLabel = htmlspecialchars(hg_ser_energy_label_from_row($table, $ResultQuery, $returnTypeRaw));
        $metaHtml .= "<p><b>$energyLabel inicial:</b> $checkEnergy</p>";
    } elseif ($systemTypeDocument == 4) {
        $miscNameEnergy = htmlspecialchars((string)($ResultQuery["energy_name"] ?? ''));
        $miscValuEnergy = htmlspecialchars((string)($ResultQuery["energy_value"] ?? ''));
        if ($miscNameEnergy != "") {
            $metaHtml .= "<p><b>$miscNameEnergy:</b> $miscValuEnergy</p>";
            $infoDataCheck++;
        }
    }

    if ($metaHtml !== '') echo "<div class=\"syst-box syst-meta\">$metaHtml</div>";
?>

  <div class="syst-box">
    <h3>Descripci&oacute;n</h3>
    <div><?= $infoDesc ?></div>
  </div>

<?php
    $gifts = hg_systems_fetch_gifts($link, $nameSystRaw, $systemId);
    if ($gifts === false) $gifts = [];
    if (!empty($gifts)) {
        $infoDataCheck++;
        echo "<div class=\"syst-box\">";
        echo "<h3>Dones disponibles</h3>";
        echo "<div class='hg-system-power-list'>";
        foreach ($gifts as $resultDonQuery) {
            echo "
                <a href='" . htmlspecialchars(pretty_url($link, 'fact_gifts', '/powers/gift', (int)$resultDonQuery['id'])) . "'
                    class='hg-tooltip'
                    data-tip='don'
                    data-id='" . (int)$resultDonQuery['id'] . "'
                    target='_blank'>
                    <div class='hg-system-power-card'>
                        <div class='hg-system-power-card__main'>
                            <img class='hg-system-power-icon' src='img/ui/icons/icon_claws.webp' alt=''> " . htmlspecialchars($resultDonQuery['name']) . "
                        </div>
                        <div class='hg-system-power-card__meta'>" . htmlspecialchars($resultDonQuery['rank']) . "</div>
                    </div>
                </a>
            ";
        }
        echo "</div>";
        echo "</div>";
    }

    $members = hg_systems_fetch_members($link, $systemTypeDocument, $resolvedId, $excludeChronicles, false);
    if ($members === false) $members = [];
    if (!empty($members)) {
        $pjCount = count($members);
        echo "<div class='syst-box'>";
        echo "<h3>Miembros</h3>";
        echo "<table id='tabla-miembros' class='display syst-members-table'>";
        echo "<thead><tr><th>Nombre</th><th>Grupo</th><th>Organizaci&oacute;n</th></tr></thead><tbody>";
        foreach ($members as $m) {
            $charHref = pretty_url($link, 'fact_characters', '/characters', (int)$m['id']);
            $charName = htmlspecialchars((string)$m['name']);
            $charGroup = htmlspecialchars((string)(($m['grupos'] ?? '') !== '' ? $m['grupos'] : '-'));
            $charOrg = htmlspecialchars((string)(($m['organizaciones'] ?? '') !== '' ? $m['organizaciones'] : '-'));
            echo "<tr><td><a href='" . htmlspecialchars($charHref) . "' target='_blank'>$charName</a></td><td>$charGroup</td><td>$charOrg</td></tr>";
        }
        echo "</tbody></table>";
        echo "</div>";

        include_once("app/partials/datatable_assets.php");
        echo "<script>
        (function(){
          if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.DataTable) return;
          jQuery(function($){
            $('#tabla-miembros').DataTable({
              pageLength: 10,
              lengthMenu: [10, 20, 50, 100],
              order: [[0, 'asc']],
              language: {
                search: 'Buscar:&nbsp; ',
                lengthMenu: 'Mostrar _MENU_ personajes',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ personajes',
                infoEmpty: 'No hay personajes disponibles',
                emptyTable: 'No hay datos en la tabla',
                paginate: { first: 'Primero', last: 'Ultimo', next: '&#9654;', previous: '&#9664;' }
              }
            });
          });
        })();
        </script>";
    }
?>
</div>
<?php
}
?>