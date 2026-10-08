<?php

require_once __DIR__ . '/../../domains/systems/queries.php';
require_once __DIR__ . '/../../domains/bibliography/publication_context.php';

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
    $publication = hg_camazotz_publication_for(
        $link, $table, $resolvedId,
        hg_camazotz_publication_eligible($systemId, hg_camazotz_publication_requested($hgRequest))
    );
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
    $metaItems = [];
    if ($systemTypeDocument == 4) {
        $miscInfoData = ($ResultQuery["extra_info"] ?? '');
        if ($miscInfoData != "") {
            $metaHtml .= "<p>$miscInfoData</p>";
            $infoDataCheck++;
        }
    }

    if (!empty($energyEntries)) {
        foreach ($energyEntries as $energyEntry) {
            $energyLabel = (string)($energyEntry['resource_name'] ?? '');
            $energyValue = (int)($energyEntry['energy_value'] ?? 0);
            if ($energyLabel === '' || $energyValue <= 0) continue;
            $infoDataCheck++;
            $metaItems[] = ['label' => $energyLabel . ' inicial', 'value' => (string)$energyValue];
        }
    } elseif ($checkEnergy != 0) {
        $infoDataCheck++;
        $energyLabel = hg_ser_energy_label_from_row($table, $ResultQuery, $returnTypeRaw);
        $metaItems[] = ['label' => $energyLabel . ' inicial', 'value' => (string)$checkEnergy];
    } elseif ($systemTypeDocument == 4) {
        $miscNameEnergy = trim((string)($ResultQuery["energy_name"] ?? ''));
        $miscValuEnergy = (string)($ResultQuery["energy_value"] ?? '');
        if ($miscNameEnergy !== '') {
            $metaItems[] = ['label' => $miscNameEnergy, 'value' => $miscValuEnergy];
            $infoDataCheck++;
        }
    }

    if ($systemTypeDocument === 1) {
        $nativeFormId = (int)($ResultQuery['native_form_id'] ?? 0);
        if ($nativeFormId > 0) {
            $nativeForm = hg_systems_fetch_form_identity($link, $nativeFormId);
            if ($nativeForm) {
                $nativeFormName = trim((string)($nativeForm['form'] ?? ''));
                if ($nativeFormName !== '') {
                    $nativeFormHref = pretty_url($link, 'dim_forms', '/systems/form', $nativeFormId);
                    if ($systemId === HG_CAMAZOTZ_SYSTEM_ID) $nativeFormHref = hg_camazotz_publication_link($nativeFormHref);
                    $metaItems[] = [
                        'label' => 'Forma natal',
                        'value_html' => '<a href="' . htmlspecialchars($nativeFormHref) . '">' . htmlspecialchars($nativeFormName) . '</a>',
                    ];
                    $infoDataCheck++;
                }
            }
        }

        $regenNormal = (int)($ResultQuery['regen_normal_per_turn'] ?? 0);
        if ($regenNormal > 0) {
            $metaItems[] = ['label' => 'Regeneración fuera de la Forma natal', 'value' => $regenNormal . ' / turno'];
            $stressDifficulty = (int)($ResultQuery['regen_stress_difficulty'] ?? 0);
            if ($stressDifficulty > 0) {
                $metaItems[] = ['label' => 'Regeneración bajo estrés', 'value' => 'Resistencia · Dificultad ' . $stressDifficulty];
            }
            $metaItems[] = [
                'label' => 'Regeneración en Forma natal',
                'value' => ((int)($ResultQuery['regen_in_native_form'] ?? 0) === 1 ? 'Sí' : 'No'),
            ];
            $metaItems[] = [
                'label' => 'Regeneración de daño agravado',
                'value' => ((int)($ResultQuery['regen_aggravated_auto'] ?? 0) === 1 ? 'Automática' : 'No automática'),
            ];
            $infoDataCheck++;
        }
    }

    if ($systemTypeDocument === 3) {
        $patronTotemId = (int)($ResultQuery['patron_totem_id'] ?? 0);
        if ($patronTotemId > 0) {
            $patronName = hg_systems_fetch_patron_totem_name($link, $patronTotemId);
            if ($patronName !== '') {
                $patronHref = pretty_url($link, 'dim_totems', '/powers/totem', $patronTotemId);
                if ($systemId === HG_CAMAZOTZ_SYSTEM_ID) $patronHref = hg_camazotz_publication_link($patronHref);
                $metaItems[] = [
                    'label' => 'Patrono tribal',
                    'value_html' => '<a href="' . htmlspecialchars($patronHref) . '">' . htmlspecialchars($patronName) . '</a>',
                ];
                $infoDataCheck++;
            }
        }
    }

    if ($metaHtml !== '' || !empty($metaItems)) {
        echo '<div class="syst-box syst-meta">';
        if ($metaHtml !== '') echo '<div class="syst-meta-info">' . $metaHtml . '</div>';
        if (!empty($metaItems)) {
            echo '<div class="syst-detail-meta-grid">';
            foreach ($metaItems as $item) {
                $label = htmlspecialchars((string)($item['label'] ?? ''));
                $valueHtml = isset($item['value_html'])
                    ? (string)$item['value_html']
                    : htmlspecialchars((string)($item['value'] ?? ''));
                echo '<div class="syst-detail-meta-item"><span class="syst-detail-meta-label">' . $label . '</span><strong class="syst-detail-meta-value">' . $valueHtml . '</strong></div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
?>

  <?php if ($publication): ?>
  <div class="syst-box"><strong>Publicación / versión:</strong>
    <?= htmlspecialchars(hg_camazotz_publication_label($publication), ENT_QUOTES, 'UTF-8') ?>
  </div>
  <?php endif; ?>

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
                <a href='" . htmlspecialchars($systemId === HG_CAMAZOTZ_SYSTEM_ID
                    ? hg_camazotz_publication_link(pretty_url($link, 'fact_gifts', '/powers/gift', (int)$resultDonQuery['id']))
                    : pretty_url($link, 'fact_gifts', '/powers/gift', (int)$resultDonQuery['id'])) . "'
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