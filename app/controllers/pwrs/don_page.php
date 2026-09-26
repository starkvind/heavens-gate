<?php
require_once __DIR__ . '/../../domains/powers/queries.php';

$donPageID = (int)hg_request_param($hgRequest, 'gift');
$resultQueryDon = hg_powers_fetch_gift($link, $donPageID);

if ($resultQueryDon) {
    $donId     = htmlspecialchars($resultQueryDon["id"]);
    $donName   = htmlspecialchars($resultQueryDon["name"]);
    $donType   = htmlspecialchars($resultQueryDon["kind"]);
    $donGroup  = htmlspecialchars($resultQueryDon["gift_group"]);
    $donRank   = htmlspecialchars($resultQueryDon["rank"]);
    $donAttr   = htmlspecialchars($resultQueryDon["attribute_name"]);
    $donSkill  = htmlspecialchars($resultQueryDon["ability_name"]);
    $donDesc   = $resultQueryDon["description"];
    $donRules  = $resultQueryDon["mechanics_resolved"];
    $donSystemName = htmlspecialchars($resultQueryDon["resolved_system_name"] ?? "");
    $donBreedLegacy  = trim((string)($resultQueryDon["legacy_system_name"] ?? ""));
    $donSystemLabel = $donSystemName;
    $donOrigin = htmlspecialchars($resultQueryDon["bibliography_id"]);
    $donImgRaw = trim((string)($resultQueryDon["image_url"] ?? ""));
    $donOriginName = htmlspecialchars((string)($resultQueryDon['origin_name'] ?? '-'));
    if ($donOriginName === '') $donOriginName = '-';
    $nombreTipo = htmlspecialchars((string)($resultQueryDon['type_name'] ?? 'Desconocido'));
    if ($nombreTipo === '') $nombreTipo = 'Desconocido';

    $_SESSION['punk2'] = $nombreTipo;

    $donOwners = hg_powers_fetch_bridge_owners(
        $link,
        'dones',
        $donPageID,
        isset($excludeChronicles) ? $excludeChronicles : ''
    );
    if ($donOwners === false) $donOwners = [];
    $hasOwners = count($donOwners) > 0;
    $useTabs = $hasOwners;

    $pageSect = "Dones";
    $pageTitle2 = $donName;
    setMetaFromPage($donName . " | Dones | Heaven's Gate", meta_excerpt($donDesc), null, 'article');

    if (function_exists('hg_page_register_stylesheet')) {
        hg_page_register_stylesheet('/assets/css/hg-powers.css');
    } else {
        echo '<link rel="stylesheet" href="/assets/css/hg-powers.css">';
    }

    include("app/partials/main_nav_bar.php");

    ob_start();

    $itemImg = "img/inv/no-photo.webp";
    if ($donImgRaw !== "") {
        if (strpos($donImgRaw, "/") !== false) {
            $itemImg = $donImgRaw;
        } else {
            $itemImg = "img/gifts/" . $donImgRaw;
        }
    }

    echo "<div class='power-card power-card--don'>";
    echo "  <div class='power-card__banner'>";
    echo "    <span class='power-card__title'>$donName</span>";
    echo "  </div>";

    echo "  <div class='power-card__body'>";
    echo "    <div class='power-card__media'>";
    echo "      <img class='power-card__img' style='border:1px solid #001a55; box-shadow: 0 0 0 2px #001a55, 0 0 14px rgba(0,0,0,0.5)' src='$itemImg' alt='$donName'/>";
    echo "    </div>";

    echo "    <div class='power-card__stats'>";
    if ($donRank > 0) {
        echo "<div class='power-stat'><div class='power-stat__label'>Rango</div><div class='power-stat__value'><img class='hg-powers-gem' src='img/ui/gems/pwr/gem-pwr-0$donRank.webp'/></div></div>";
    }
    if (!empty($donAttr) || !empty($donSkill)) {
        $tiradaDon2 = !empty($donSkill) ? "$donAttr + $donSkill" : $donAttr;
        echo "<div class='power-stat'><div class='power-stat__label'>Tirada</div><div class='power-stat__value'>$tiradaDon2</div></div>";
    }
    if ($donGroup !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Grupo</div><div class='power-stat__value'>$donGroup</div></div>";
    }
    if ($donBreedLegacy !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Sistema</div><div class='power-stat__value'>" . htmlspecialchars($donBreedLegacy) . "</div></div>";
    }
    if ($donOriginName !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>$donOriginName</div></div>";
    }
    echo "    </div>";
    echo "  </div>";

    if (!empty($donDesc)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Descripci&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>$donDesc</div>";
        echo "  </div>";
    }

    if (!empty($donRules)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Sistema</div>";
        echo "    <div class='power-card__desc-body'>$donRules</div>";
        echo "  </div>";
    }

    echo "</div>";

    $infoHtml = ob_get_clean();

    if ($useTabs) {
        echo "<div class='hg-tabs'>";
        echo "<button class='hgTabBtn' data-tab='info'>Informaci&oacute;n</button>";
        if ($hasOwners) echo "<button class='hgTabBtn' data-tab='owners'>Portadores</button>";
        echo "</div>";

        echo "<section class='hg-tab-panel' data-tab='info'>$infoHtml</section>";

        if ($hasOwners) {
            echo "<section class='hg-tab-panel' data-tab='owners'>";
            echo "<div class='hg-affiliation-content hg-powers-owner-content'>";
            foreach ($donOwners as $o) {
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
            echo "<p align='right'>Personajes: " . count($donOwners) . "</p>";
            echo "</section>";
        }

    } else {
        echo $infoHtml;
    }

} else {
    echo "<p>Error: Don no encontrado.</p>";
}
?>
