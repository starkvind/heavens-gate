<?php
setMetaFromPage("Grupos y sociedades | Heaven's Gate", "Listado de grupos, manadas y clanes.", null, 'website');
include_once(__DIR__ . '/../../helpers/public_response.php');
require_once(__DIR__ . '/../../domains/relationships/archive_queries.php');

if (!$link) {
    hg_public_log_error('bio_pack_list', 'missing DB connection');
    hg_public_render_error('Grupos no disponibles', 'No se pudo cargar el listado de grupos y sociedades en este momento.');
    return;
}

$nameTypePack = "Grupos y sociedades";
$iconPack = "img/ui/icons/icon_person_active.webp";
$iconSept = "img/ui/icons/icon_person_dead.webp";
$pageSect = "Biografías";
$pageTitle2 = $nameTypePack;

$excludeChronicles = isset($excludeChronicles) && trim($excludeChronicles) !== '' ? $excludeChronicles : '';

if (!function_exists('hg_bio_pack_group_url')) {
    function hg_bio_pack_group_url(mysqli $link, int $organizationId, int $groupId): string
    {
        $orgPath = (string)parse_url(pretty_url($link, 'dim_organizations', '/organizations', $organizationId), PHP_URL_PATH);
        $groupPath = (string)parse_url(pretty_url($link, 'dim_groups', '/groups', $groupId), PHP_URL_PATH);
        $orgSlug = basename($orgPath);
        $groupSlug = basename($groupPath);
        return '/groups/' . $orgSlug . '/' . $groupSlug;
    }
}

include("app/partials/main_nav_bar.php");
echo "<h2>" . htmlspecialchars($nameTypePack, ENT_QUOTES, 'UTF-8') . "</h2>";
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-archive-panel.css');
    hg_page_register_stylesheet('/assets/css/pages/bio/pack-list.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-archive-panel.css">';
    echo '<link rel="stylesheet" href="/assets/css/pages/bio/pack-list.css">';
}

$clanes = hg_relationship_archive_fetch_organizations($link);
if ($clanes === null) {
    hg_public_log_error('bio_pack_list', 'organization query failed');
    hg_public_render_error('Grupos no disponibles', 'No se pudo cargar el listado de grupos y sociedades en este momento.');
    return;
}

$numeroClanesHallados = count($clanes);
$numeroDeGruposHallados = 0;

foreach ($clanes as $clan) {
    $clanId = (int)$clan['id'];
    $clanName = (string)$clan['name'];
    $groups = hg_relationship_archive_fetch_groups($link, $clanId, $excludeChronicles);
    if ($groups === null) {
        hg_public_log_error('bio_pack_list', 'group query failed for organization ' . $clanId);
        hg_public_render_error('Grupos no disponibles', 'No se pudo cargar el listado de grupos y sociedades en este momento.');
        return;
    }
    $hasOrgChart = hg_relationship_archive_org_chart_available($link, $clanId);

    if (!empty($groups) || $hasOrgChart) {
        print("<fieldset class='hg-archive-panel'>");
        print("<legend class='hg-archive-panel__legend'>");
        $hrefClan = pretty_url($link, 'dim_organizations', '/organizations', $clanId);
        print("<a href='" . htmlspecialchars($hrefClan, ENT_QUOTES, 'UTF-8') . "' title='" . htmlspecialchars($clanName, ENT_QUOTES, 'UTF-8') . "'>");
        print("&nbsp;" . htmlspecialchars($clanName, ENT_QUOTES, 'UTF-8') . "&nbsp;");
        print("</a>");
        if ($hasOrgChart) {
            print("<a class='bio-pack-org-chart-link' href='" . htmlspecialchars(rtrim($hrefClan, '/') . '/org-chart', ENT_QUOTES, 'UTF-8') . "' title='Organigrama de " . htmlspecialchars($clanName, ENT_QUOTES, 'UTF-8') . "'>Organigrama</a>");
        }
        print("</legend>");
        print("<ul class='listaManadas'>");

        if (!empty($groups)) {
            foreach ($groups as $rowGrupo) {
                $enActivo = (int)$rowGrupo['is_active'];
                $iconManada = ($enActivo === 0) ? $iconSept : $iconPack;
                $gid = (int)$rowGrupo['id'];
                $gname = (string)$rowGrupo['name'];

                print("<li class='listaManadas'>");
                $hrefGroup = hg_bio_pack_group_url($link, $clanId, $gid);
                print("<a href='" . htmlspecialchars($hrefGroup, ENT_QUOTES, 'UTF-8') . "' title='" . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . "'>");
                print("<img src='" . htmlspecialchars($iconManada, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . "' title='" . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . "' class='bio-pack-group-icon'/>");
                print(" " . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8'));
                print("</a></li>");
                $numeroDeGruposHallados++;
            }
        } else {
            print("<li class='listaManadas'>Organizacion sin grupos vinculados.</li>");
        }

        print("</ul>");
        print("</fieldset>");
    }
}

print("<p style='text-align:right;'>Organizaciones halladas: " . htmlspecialchars((string)$numeroClanesHallados, ENT_QUOTES, 'UTF-8'));
print("<br/>Grupos hallados: " . htmlspecialchars((string)$numeroDeGruposHallados, ENT_QUOTES, 'UTF-8') . "</p>");
?>
