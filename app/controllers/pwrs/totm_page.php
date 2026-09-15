<?php
require_once __DIR__ . '/../../domains/powers/queries.php';

$totemPageID = (int)hg_request_param($hgRequest, 'totem');
$resultQueryTotem = hg_powers_fetch_totem($link, $totemPageID);

if ($resultQueryTotem) {
    $totemId    = htmlspecialchars($resultQueryTotem["id"]);
    $totemName  = htmlspecialchars($resultQueryTotem["name"]);
    $totemNameRaw = (string)($resultQueryTotem["name"] ?? "");
    $totemPrettyRaw = (string)($resultQueryTotem["pretty_id"] ?? "");
    $totemType  = htmlspecialchars($resultQueryTotem["totem_type_id"] ?? $resultQueryTotem["tipo"] ?? '');
    $totemCost  = htmlspecialchars($resultQueryTotem["cost"]);
    $totemDesc  = $resultQueryTotem["description"] ?? '';
    $totemAttr  = $resultQueryTotem["traits"];
    $totemBan   = $resultQueryTotem["prohibited"];
    $totemOrigin = htmlspecialchars($resultQueryTotem["bibliography_id"]);
    $totemImgRaw = trim((string)($resultQueryTotem["image_url"] ?? ""));
    $totemOriginName = htmlspecialchars((string)($resultQueryTotem['origin_name'] ?? '-'));
    if ($totemOriginName === '') $totemOriginName = '-';
    $nombreTipo = htmlspecialchars((string)($resultQueryTotem['type_name'] ?? 'Desconocido'));
    if ($nombreTipo === '') $nombreTipo = 'Desconocido';

    $_SESSION['punk2'] = $nombreTipo;

    $totemCharOwners = hg_powers_fetch_totem_character_owners(
        $link,
        $totemPageID,
        isset($excludeChronicles) ? $excludeChronicles : ''
    );
    if ($totemCharOwners === false) $totemCharOwners = [];

    $totemGroups = hg_powers_fetch_totem_links($link, $totemPageID, 'dim_groups');
    if ($totemGroups === false) $totemGroups = [];

    $totemOrgs = hg_powers_fetch_totem_links($link, $totemPageID, 'dim_organizations');
    if ($totemOrgs === false) $totemOrgs = [];

    $hasCharOwners = count($totemCharOwners) > 0;
    $hasGroupOwners = count($totemGroups) > 0;
    $hasOrgOwners = count($totemOrgs) > 0;
    $useTabs = ($hasCharOwners || $hasGroupOwners || $hasOrgOwners);

    $pageSect = "Tótems";
    $pageTitle2 = $totemName;
    setMetaFromPage($totemName . " | Tótems | Heaven's Gate", meta_excerpt($totemDesc), null, 'article');

    if (function_exists('hg_page_register_stylesheet')) {
        hg_page_register_stylesheet('/assets/css/hg-powers.css');
    } else {
        echo '<link rel="stylesheet" href="/assets/css/hg-powers.css">';
    }

    include("app/partials/main_nav_bar.php");

    ob_start();

    $itemImg = "img/inv/no-photo.webp";
    if ($totemImgRaw !== "") {
        if (strpos($totemImgRaw, "/") !== false) {
            $itemImg = $totemImgRaw;
        } else {
            $itemImg = "img/totems/" . $totemImgRaw;
        }
    }

    echo "<div class='power-card power-card--totem'>";
    echo "  <div class='power-card__banner'>";
    echo "    <span class='power-card__title'>$totemName</span>";
    echo "  </div>";

    echo "  <div class='power-card__body'>";
    echo "    <div class='power-card__media'>";
    echo "      <img class='power-card__img' style='border:1px solid #001a55; box-shadow: 0 0 0 2px #001a55, 0 0 14px rgba(0,0,0,0.5)' src='$itemImg' alt='$totemName'/>";
    echo "    </div>";

    echo "    <div class='power-card__stats'>";
    if ($totemCost > 0) {
        echo "<div class='power-stat'><div class='power-stat__label'>Coste</div><div class='power-stat__value'><img class='hg-powers-gem' src='img/ui/gems/pwr/gem-pwr-0$totemCost.webp'/></div></div>";
    }
    if ($nombreTipo !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Tipo</div><div class='power-stat__value'>$nombreTipo</div></div>";
    }
    if ($totemOriginName !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>$totemOriginName</div></div>";
    }
    echo "    </div>";
    echo "  </div>";

    if (!empty($totemDesc)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Descripci&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>$totemDesc</div>";
        echo "  </div>";
    }

    if (!empty($totemAttr)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Rasgos</div>";
        echo "    <div class='power-card__desc-body'>$totemAttr</div>";
        echo "  </div>";
    }

    if (!empty($totemBan)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Prohibici&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>$totemBan</div>";
        echo "  </div>";
    }

    echo "</div>";

    $infoHtml = ob_get_clean();

    if ($useTabs) {
        echo "<div class='hg-tabs'>";
        echo "<button class='hgTabBtn' data-tab='info'>Información</button>";
        if ($hasCharOwners) echo "<button class='hgTabBtn' data-tab='owners'>Portadores</button>";
        if ($hasGroupOwners) echo "<button class='hgTabBtn' data-tab='groups'>Grupos</button>";
        if ($hasOrgOwners) echo "<button class='hgTabBtn' data-tab='orgs'>Organizaciones</button>";
        echo "</div>";

        echo "<section class='hg-tab-panel' data-tab='info'>$infoHtml</section>";

        if ($hasCharOwners) {
            echo "<section class='hg-tab-panel' data-tab='owners'>";
            echo "<div class='hg-affiliation-content hg-powers-owner-content'>";
            foreach ($totemCharOwners as $o) {
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
            echo "<p align='right'>Personajes: " . count($totemCharOwners) . "</p>";
            echo "</section>";
        }

        if ($hasGroupOwners) {
            echo "<section class='hg-tab-panel' data-tab='groups'>";
            echo "<div class='hg-powers-related'><div class='hg-powers-related-content'>";
            foreach ($totemGroups as $g) {
                $gid = (int)($g['id'] ?? 0);
                $gname = (string)($g['name'] ?? '');
                $href = pretty_url($link, 'dim_groups', '/groups', $gid);
                echo "<a href='" . htmlspecialchars($href) . "' target='_blank'><div class='hg-powers-related-card'>" . htmlspecialchars($gname) . "</div></a>";
            }
            echo "</div></div>";
            echo "<p align='right'>Grupos: " . count($totemGroups) . "</p>";
            echo "</section>";
        }

        if ($hasOrgOwners) {
            echo "<section class='hg-tab-panel' data-tab='orgs'>";
            echo "<div class='hg-powers-related'><div class='hg-powers-related-content'>";
            foreach ($totemOrgs as $g) {
                $gid = (int)($g['id'] ?? 0);
                $gname = (string)($g['name'] ?? '');
                $href = pretty_url($link, 'dim_organizations', '/organizations', $gid);
                echo "<a href='" . htmlspecialchars($href) . "' target='_blank'><div class='hg-powers-related-card'>" . htmlspecialchars($gname) . "</div></a>";
            }
            echo "</div></div>";
            echo "<p align='right'>Organizaciones: " . count($totemOrgs) . "</p>";
            echo "</section>";
        }

    } else {
        echo $infoHtml;
    }

} else {
    echo "<p>Error: Tótem no encontrado.</p>";
}
?>
