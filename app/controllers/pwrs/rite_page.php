<?php
require_once __DIR__ . '/../../domains/powers/queries.php';

$ritePageID = (int)hg_request_param($hgRequest, 'rite');
$resultQueryRite = hg_powers_fetch_rite($link, $ritePageID);

if ($resultQueryRite) {
    $riteId     = htmlspecialchars($resultQueryRite["id"]);
    $riteName   = htmlspecialchars($resultQueryRite["name"]);
    $riteType   = htmlspecialchars($resultQueryRite["kind"]);
    $riteLevel  = htmlspecialchars($resultQueryRite["level"]);
    $riteBreed  = htmlspecialchars($resultQueryRite["race"]);
    $riteDesc   = $resultQueryRite["description"] ?? '';
    $riteSystemRules = $resultQueryRite["system_text"];
    $riteSystemName  = htmlspecialchars($resultQueryRite["resolved_system_name"] ?? "");
    $riteSistemaLegacy = trim((string)($resultQueryRite["system_name"] ?? ""));
    if (trim((string)$riteSystemRules) === '' && $riteSistemaLegacy !== '') { $riteSystemRules = $riteSistemaLegacy; }
    $riteOrigin = htmlspecialchars($resultQueryRite["bibliography_id"]);
    $riteImgRaw = trim((string)($resultQueryRite["image_url"] ?? ""));
    $riteOriginName = htmlspecialchars((string)($resultQueryRite['origin_name'] ?? '-'));
    if ($riteOriginName === '') $riteOriginName = '-';
    $nombreTipo = htmlspecialchars((string)($resultQueryRite['type_name'] ?? 'Desconocido'));
    if ($nombreTipo === '') $nombreTipo = 'Desconocido';

    $_SESSION['punk2'] = $nombreTipo;

    $riteOwners = hg_powers_fetch_bridge_owners(
        $link,
        'rituales',
        $ritePageID,
        isset($excludeChronicles) ? $excludeChronicles : ''
    );
    if ($riteOwners === false) $riteOwners = [];
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
        echo "<button class='hgTabBtn' data-tab='info'>Información</button>";
        if ($hasOwners) echo "<button class='hgTabBtn' data-tab='owners'>Portadores</button>";
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
