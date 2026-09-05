<?php

// Obtener parametros de manera segura
$systemIdDocument = isset($_GET['b']) ? (string)$_GET['b'] : '';  // ID o pretty_id
$systemTypeDocument = isset($_GET['tc']) ? (int)$_GET['tc'] : 0;  // Tipo de contenido

include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/system_energy_resource.php');

// Sanitiza "1,2, 3" -> "1,2,3" (solo ints). Si queda vacio, devuelve ""
function sanitize_int_csv($csv){
    $csv = (string)$csv;
    if (trim($csv) === '') return '';
    $parts = preg_split('/\s*,\s*/', trim($csv));
    $ints = [];
    foreach ($parts as $p) {
        if ($p === '') continue;
        if (preg_match('/^\d+$/', $p)) $ints[] = (string)(int)$p;
    }
    $ints = array_values(array_unique($ints));
    return implode(',', $ints);
}

if (!function_exists('hg_sdetail_table_exists')) {
    function hg_sdetail_table_exists(mysqli $link, string $table): bool
    {
        $table = str_replace('`', '', $table);
        $rs = $link->query("SHOW TABLES LIKE '" . $link->real_escape_string($table) . "'");
        if (!$rs) return false;
        $ok = ($rs->num_rows > 0);
        $rs->close();
        return $ok;
    }
}

if (!function_exists('hg_sdetail_col_exists')) {
    function hg_sdetail_col_exists(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . ':' . $column;
        if (isset($cache[$key])) return $cache[$key];

        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
            $st->bind_param('ss', $table, $column);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }

        $cache[$key] = $ok;
        return $ok;
    }
}

// EXCLUSIONES (si existe la variable global, la usamos; si no, mantenemos 2,7)
$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '2,7';
$whereChron = ($excludeChronicles !== '') ? "p.chronicle_id NOT IN ($excludeChronicles)" : "1=1";

// Preparar queries
switch ($systemTypeDocument) {
    case 1:
        $table = "dim_breeds";
        break;
    case 2:
        $table = "dim_auspices";
        break;
    case 3:
        $table = "dim_tribes";
        break;
    case 4:
        $table = "fact_misc_systems";
        break;
    default:
        $table = "";
        break;
}

if ($table !== "") {
    $infoDataCheck = 0;

    $resolvedId = 0;
    if ($systemIdDocument !== '') {
        if (preg_match('/^\d+$/', $systemIdDocument)) {
            $resolvedId = (int)$systemIdDocument;
        } else {
            $resolvedId = (int)resolve_pretty_id($link, $table, $systemIdDocument);
        }
    }

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

    // Ejecutar la consulta utilizando mysqli y sentencias preparadas
    $energySql = hg_ser_energy_sql_parts($link, $table, 't');
    $sqlDetail = "SELECT t.*{$energySql['select']} FROM `$table` t{$energySql['join']} WHERE t.id = ? LIMIT 1";
    $stmt = $link->prepare($sqlDetail);
    $stmt->bind_param('i', $resolvedId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($ResultQuery = $result->fetch_assoc()) {
        // Datos del sistema
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

        // Comprobar si los datos tienen energia para mostrarla
        $checkEnergy = isset($ResultQuery["energy"]) ? (int)$ResultQuery["energy"] : 0;
        $energyEntries = [];
        if (in_array($table, ['dim_breeds', 'dim_auspices', 'dim_tribes', 'fact_misc_systems'], true)) {
            $energyEntries = hg_ser_energy_entries_for_row($link, $table, $resolvedId, $ResultQuery, $returnTypeRaw);
        }

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
            $miscInfoData = ($ResultQuery["extra_info"]);
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

        if ($metaHtml !== '') {
            echo "<div class=\"syst-box syst-meta\">$metaHtml</div>";
        }
?>

  <div class="syst-box">
    <h3>Descripci&oacute;n</h3>
    <div><?= $infoDesc ?></div>
  </div>

<?php
        // Don query para obtener dones basados en el sistema
        $donGroup = $nameSystRaw;
        $donQuery = "SELECT id, name, rank FROM fact_gifts WHERE gift_group = ? AND system_id = ? ORDER BY rank;";
        $stmtDon = $link->prepare($donQuery);
        $stmtDon->bind_param('si', $donGroup, $systemId);
        $stmtDon->execute();
        $resultDon = $stmtDon->get_result();
        $filasDon = $resultDon->num_rows;

        if ($filasDon > 0) {
            $infoDataCheck++;
            echo "<div class=\"syst-box\">";
            echo "<h3>Dones disponibles</h3>";
            echo "<div class='hg-system-power-list'>";
            while ($resultDonQuery = $resultDon->fetch_assoc()) {
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

        $stmtDon->close();
?>

<?php
        // Mostrar personajes asociados (raza / auspicio / tribu / misc system)
        $charField = '';
        if ($systemTypeDocument === 1) $charField = 'breed_id';
        elseif ($systemTypeDocument === 2) $charField = 'auspice_id';
        elseif ($systemTypeDocument === 3) $charField = 'tribe_id';

        if ($charField !== '') {
            $charsQuery = "
                SELECT
                    p.id,
                    p.name,
                    GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS grupos,
                    GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ') AS organizaciones
                FROM fact_characters p
                LEFT JOIN bridge_characters_groups bcg ON bcg.character_id = p.id
                LEFT JOIN dim_groups g ON g.id = bcg.group_id
                LEFT JOIN bridge_characters_organizations bco ON bco.character_id = p.id
                LEFT JOIN dim_organizations o ON o.id = bco.organization_id
                WHERE p.`$charField` = ?
                  AND $whereChron
                GROUP BY p.id
                ORDER BY p.name ASC
            ";
            $stmtChars = $link->prepare($charsQuery);
            $stmtChars->bind_param('i', $resolvedId);
            $stmtChars->execute();
            $resultChars = $stmtChars->get_result();
            $charsWithoutPackFilas = $resultChars->num_rows;
        } elseif ($systemTypeDocument === 4 && hg_sdetail_table_exists($link, 'bridge_characters_misc_systems')) {
            $hasMiscActive = hg_sdetail_col_exists($link, 'bridge_characters_misc_systems', 'is_active');
            $hasMiscSort = hg_sdetail_col_exists($link, 'bridge_characters_misc_systems', 'sort_order');
            $miscActiveSql = $hasMiscActive ? "AND (bcms.is_active = 1 OR bcms.is_active IS NULL)" : "";
            $miscSortSelect = $hasMiscSort ? ", MIN(COALESCE(bcms.sort_order, 0)) AS misc_sort_order" : "";
            $miscSortOrder = $hasMiscSort ? "misc_sort_order ASC, p.name ASC" : "p.name ASC";

            $charsQuery = "
                SELECT
                    p.id,
                    p.name,
                    GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS grupos,
                    GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ') AS organizaciones
                    $miscSortSelect
                FROM bridge_characters_misc_systems bcms
                INNER JOIN fact_characters p ON p.id = bcms.character_id
                LEFT JOIN bridge_characters_groups bcg ON bcg.character_id = p.id
                LEFT JOIN dim_groups g ON g.id = bcg.group_id
                LEFT JOIN bridge_characters_organizations bco ON bco.character_id = p.id
                LEFT JOIN dim_organizations o ON o.id = bco.organization_id
                WHERE bcms.misc_system_id = ?
                  $miscActiveSql
                  AND $whereChron
                GROUP BY p.id
                ORDER BY $miscSortOrder
            ";
            $stmtChars = $link->prepare($charsQuery);
            $stmtChars->bind_param('i', $resolvedId);
            $stmtChars->execute();
            $resultChars = $stmtChars->get_result();
            $charsWithoutPackFilas = $resultChars->num_rows;
        } else {
            $charsWithoutPackFilas = 0;
        }

        if ($charsWithoutPackFilas > 0) {
            $members = [];
            while ($charsWithoutPackResult = $resultChars->fetch_assoc()) {
                $members[] = [
                    'id' => (int)$charsWithoutPackResult["id"],
                    'nombre' => (string)$charsWithoutPackResult["name"],
                    'grupos' => (string)($charsWithoutPackResult["grupos"] ?? ''),
                    'organizaciones' => (string)($charsWithoutPackResult["organizaciones"] ?? ''),
                ];
            }

            $pjCount = count($members);
            echo "<div class='syst-box'>";
            echo "<h3>Miembros</h3>";
            echo "<table id='tabla-miembros' class='display syst-members-table'>";
            echo "<thead><tr><th>Nombre</th><th>Grupo</th><th>Organizaci&oacute;n</th></tr></thead><tbody>";
            foreach ($members as $m) {
                $charHref = pretty_url($link, 'fact_characters', '/characters', (int)$m['id']);
                $charName = htmlspecialchars($m['nombre']);
                $charGroup = htmlspecialchars($m['grupos'] !== '' ? $m['grupos'] : '-');
                $charOrg = htmlspecialchars($m['organizaciones'] !== '' ? $m['organizaciones'] : '-');
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

        if (isset($stmtChars) && $stmtChars) $stmtChars->close();
?>
</div>
<?php
    }
}
?>