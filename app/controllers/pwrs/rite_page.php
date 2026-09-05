<?php
include_once(__DIR__ . '/../../helpers/character_avatar.php');
$ritePageID = isset($_GET['b']) ? $_GET['b'] : '';

$queryRite = "
    SELECT r.*, s.name AS system_name, r.kind AS tipo, r.level AS nivel, r.race AS raza, r.system_name AS sistema
    FROM fact_rites r
    LEFT JOIN dim_systems s ON r.system_id = s.id
    WHERE r.id = ? LIMIT 1
";
$stmt = $link->prepare($queryRite);
$stmt->bind_param('s', $ritePageID);
$stmt->execute();
$result = $stmt->get_result();
$rowsQueryRite = $result->num_rows;

if ($rowsQueryRite > 0) {
    $resultQueryRite = $result->fetch_assoc();

    $riteId     = htmlspecialchars($resultQueryRite["id"]);
    $riteName   = htmlspecialchars($resultQueryRite["name"]);
    $riteType   = htmlspecialchars($resultQueryRite["tipo"]);
    $riteLevel  = htmlspecialchars($resultQueryRite["nivel"]);
    $riteBreed  = htmlspecialchars($resultQueryRite["raza"]);
    $riteDesc   = $resultQueryRite["description"] ?? '';
    $riteSystemRules = $resultQueryRite["system_text"];
    $riteSystemName  = htmlspecialchars($resultQueryRite["system_name"] ?? "");
    $riteSistemaLegacy = trim((string)($resultQueryRite["sistema"] ?? ""));
    if (trim((string)$riteSystemRules) === '' && $riteSistemaLegacy !== '') { $riteSystemRules = $riteSistemaLegacy; }
    $riteOrigin = htmlspecialchars($resultQueryRite["bibliography_id"]);
    $riteImgRaw = trim((string)($resultQueryRite["image_url"] ?? ""));

    $riteOriginName = "-";

    if (!empty($riteOrigin)) {
        $queryOrigen = "SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1";
        $stmt = $link->prepare($queryOrigen);
        $stmt->bind_param('s', $riteOrigin);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($rowOrigen = $result->fetch_assoc()) {
            $riteOriginName = htmlspecialchars($rowOrigen["name"]);
        }
    }

    $nombreTipo = "Desconocido";
    $queryTipo = "SELECT name FROM dim_rite_types WHERE id = ? LIMIT 1";
    $stmt = $link->prepare($queryTipo);
    $stmt->bind_param('s', $riteType);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($rowTipo = $result->fetch_assoc()) {
        $nombreTipo = htmlspecialchars($rowTipo["name"]);
    }

    $_SESSION['punk2'] = $nombreTipo;

    if (!function_exists('sanitize_int_csv')) {
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
    }
    $excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
    $cronicaNotInSQL = ($excludeChronicles !== '') ? " AND c.chronicle_id NOT IN ($excludeChronicles) " : "";
    $riteOwners = [];
    $characterKindSql = hg_character_kind_select($link, 'c');
    if ($stOwners = $link->prepare("SELECT DISTINCT c.id, c.name AS nombre, c.alias, c.image_url, c.gender, COALESCE(dcs.label, '') AS status, c.status_id, {$characterKindSql} AS character_kind FROM bridge_characters_powers b JOIN fact_characters c ON c.id = b.character_id LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id WHERE b.power_kind='rituales' AND b.power_id = ? $cronicaNotInSQL ORDER BY c.name")) {
        $stOwners->bind_param('i', $ritePageID);
        $stOwners->execute();
        $rsOwners = $stOwners->get_result();
        while ($r = $rsOwners->fetch_assoc()) { $riteOwners[] = $r; }
        $stOwners->close();
    }
    $hasOwners = count($riteOwners) > 0;
    $useTabs = $hasOwners;

    $pageSect = "Rituales";
    $pageTitle2 = $riteName;
    setMetaFromPage($riteName . " | Rituales | Heaven's Gate", meta_excerpt($riteDesc), null, 'article');

    if (function_exists('hg_page_register_stylesheet')) {
        hg_page_register_stylesheet('/assets/css/hg-powers.css');
    } else {
        echo '<link rel="stylesheet" href="/assets/css/hg-powers.css">';
    }

    include("app/partials/main_nav_bar.php");

    ob_start();

    $itemImg = "img/inv/no-photo.webp";
    if ($riteImgRaw !== "") {
        if (strpos($riteImgRaw, "/") !== false) {
            $itemImg = $riteImgRaw;
        } else {
            $itemImg = "img/rites/" . $riteImgRaw;
        }
    }

    echo "<div class='power-card power-card--rite'>";
    echo "  <div class='power-card__banner'>";
    echo "    <span class='power-card__title'>$riteName</span>";
    echo "  </div>";

    echo "  <div class='power-card__body'>";
    echo "    <div class='power-card__media'>";
    echo "      <img class='power-card__img' style='border:1px solid #001a55; box-shadow: 0 0 0 2px #001a55, 0 0 14px rgba(0,0,0,0.5)' src='$itemImg' alt='$riteName'/>";
    echo "    </div>";

    echo "    <div class='power-card__stats'>";
    if ($riteLevel > 0) {
        echo "<div class='power-stat'><div class='power-stat__label'>Nivel</div><div class='power-stat__value'><img class='hg-powers-gem' src='img/ui/gems/attr/gem-attr-0$riteLevel.webp'/></div></div>";
    }
    if ($nombreTipo !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Tipo</div><div class='power-stat__value'>$nombreTipo</div></div>";
    }
    if (!empty($riteBreed)) {
        echo "<div class='power-stat'><div class='power-stat__label'>Raza</div><div class='power-stat__value'>$riteBreed</div></div>";
    }
    if ($riteOriginName !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>$riteOriginName</div></div>";
    }
    echo "    </div>";
    echo "  </div>";

    if (!empty($riteDesc)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Descripci&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>$riteDesc</div>";
        echo "  </div>";
    }

    if (!empty($riteSystemRules)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Sistema</div>";
        echo "    <div class='power-card__desc-body'>$riteSystemRules</div>";
        echo "  </div>";
    }

    echo "</div>";

    $infoHtml = ob_get_clean();

    if ($useTabs) {
        echo "<div class='hg-tabs'>";
        echo "<button class='boton2 hgTabBtn' data-tab='info'>Información</button>";
        if ($hasOwners) echo "<button class='boton2 hgTabBtn' data-tab='owners'>Portadores</button>";
        echo "</div>";

        echo "<section class='hg-tab-panel' data-tab='info'>$infoHtml</section>";

        if ($hasOwners) {
            echo "<section class='hg-tab-panel' data-tab='owners'>";
            echo "<div class='hg-affiliation-content hg-powers-owner-content'>";
            foreach ($riteOwners as $o) {
                $oid = (int)($o['id'] ?? 0);
                $name = (string)($o['nombre'] ?? '');
                $alias = (string)($o['alias'] ?? '');
                $href = pretty_url($link, 'fact_characters', '/characters', $oid);
                hg_render_character_avatar_tile([
                    'href' => $href,
                    'title' => $name,
                    'name' => $name,
                    'alias' => $alias,
                    'character_id' => $oid,
                    'image_url' => (string)($o['image_url'] ?? ''),
                    'gender' => (string)($o['gender'] ?? ''),
                    'status' => (string)($o['status'] ?? ''),
                    'character_kind' => hg_character_kind_from_row($o),
                    'target_blank' => true,
                ]);
            }
            echo "</div>";
            echo "<p align='right'>Personajes: " . count($riteOwners) . "</p>";
            echo "</section>";
        }

    } else {
        echo $infoHtml;
    }

} else {
    echo "<p>Error: Ritual no encontrado.</p>";
}
?>
