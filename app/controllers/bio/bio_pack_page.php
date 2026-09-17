<?php

require_once __DIR__ . '/../../helpers/public_response.php';
require_once __DIR__ . '/../../helpers/character_avatar.php';
require_once __DIR__ . '/../../domains/organizations/queries.php';

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('bio_pack_page', 'missing DB connection');
    hg_public_render_error('Biografías no disponibles', 'No se pudo cargar la ficha solicitada en este momento.');
    return;
}

if (!function_exists('hg_bio_pack_page_h')) {
    function hg_bio_pack_page_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_bio_pack_page_group_url')) {
    function hg_bio_pack_page_group_url(mysqli $link, int $organizationId, int $groupId): string
    {
        $orgPath = (string)parse_url(pretty_url($link, 'dim_organizations', '/organizations', $organizationId), PHP_URL_PATH);
        $groupPath = (string)parse_url(pretty_url($link, 'dim_groups', '/groups', $groupId), PHP_URL_PATH);
        return '/groups/' . basename($orgPath) . '/' . basename($groupPath);
    }
}

if (!function_exists('hg_bio_pack_page_render_character_tile')) {
    function hg_bio_pack_page_render_character_tile(mysqli $link, array $row): void
    {
        $characterId = (int)($row['id'] ?? 0);
        $characterName = (string)($row['name'] ?? '');
        $characterAlias = trim((string)($row['alias'] ?? '')) ?: $characterName;
        hg_render_character_avatar_tile([
            'href' => pretty_url($link, 'fact_characters', '/characters', $characterId),
            'title' => $characterName,
            'name' => $characterName,
            'alias' => $characterAlias,
            'character_id' => $characterId,
            'avatar_url' => hg_character_avatar_url((string)($row['image_url'] ?? ''), (string)($row['gender'] ?? '')),
            'status' => (string)($row['status'] ?? ''),
            'character_kind' => (string)($row['character_kind'] ?? ''),
        ]);
    }
}

$typePack = (int)hg_request_param($hgRequest, 'group_type');
$packId = $typePack === 2
    ? (int)hg_request_param($hgRequest, 'organization')
    : (int)hg_request_param($hgRequest, 'group');

if (!in_array($typePack, [1, 2], true) || $packId <= 0) {
    hg_public_render_not_found('Contenido no encontrado', 'El tipo de contenido solicitado no es válido.', true);
    return;
}

$resultQuery = hg_organizations_fetch_entity($link, $typePack, $packId);
if (!$resultQuery) {
    hg_public_render_not_found('Contenido no encontrado', 'No hay resultados para esta referencia.', true);
    return;
}

$excludeChronicles = hg_organizations_normalize_int_csv($excludeChronicles ?? '');
$namePack = (string)($resultQuery['name'] ?? '');
$infoPack = (string)($resultQuery['description'] ?? '');
$nameTypeForTitle = $typePack === 1 ? 'Grupo' : 'Organización';

$pageSect = 'Biografías';
$pageTitle2 = $namePack;
setMetaFromPage(
    $namePack . ' | ' . $nameTypeForTitle . " | Heaven's Gate",
    meta_excerpt($infoPack),
    null,
    'article'
);

$clanDataId = 0;
$clanName = '';
$clanLink = '';
if ($typePack === 1) {
    $preferredClanId = hg_organizations_resolve_id(
        $link,
        'dim_organizations',
        hg_request_param($hgRequest, 'organization')
    );
    $clanData = hg_organizations_fetch_group_organization($link, $packId, $preferredClanId);
    $clanDataId = (int)($clanData['id'] ?? 0);
    $clanName = (string)($clanData['name'] ?? '');
    if ($clanDataId > 0 && $clanName !== '') {
        $clanHref = pretty_url($link, 'dim_organizations', '/organizations', $clanDataId);
        $clanLink = "<a href='" . hg_bio_pack_page_h($clanHref) . "'>" . hg_bio_pack_page_h($clanName) . '</a>';
        $canonicalGroupPath = hg_bio_pack_page_group_url($link, $clanDataId, $packId);
        $currentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        $normalizePath = static function (string $path): string {
            $path = rtrim(rawurldecode($path), '/');
            return $path === '' ? '/' : $path;
        };
        if ($normalizePath($currentPath) !== $normalizePath($canonicalGroupPath)) {
            header('Location: ' . $canonicalGroupPath, true, 301);
            exit;
        }
    }
}

$totemLink = '';
$totemPack = (int)($resultQuery['totem_id'] ?? 0);
if ($totemPack > 0) {
    $totem = hg_organizations_fetch_totem($link, $totemPack);
    if ($totem) {
        $totemLink = "<a href='/powers/totem/" . hg_bio_pack_page_h((int)$totem['id']) . "' target='_blank'>"
            . hg_bio_pack_page_h((string)$totem['name']) . '</a>';
    }
}

$packNavLinks = ($typePack === 1 && $clanLink !== '')
    ? ($clanLink . ' &raquo;&nbsp;' . hg_bio_pack_page_h($namePack))
    : hg_bio_pack_page_h($namePack);

$activeMembers = [];
$formerMembers = [];
$activeGroups = [];
$inactiveGroups = [];
$ungroupedMembers = [];
$markdownData = [];
$orgChartAvailable = false;

if ($typePack === 1) {
    $activeMembers = hg_organizations_fetch_group_members($link, $packId, $excludeChronicles, false);
    $formerMembers = hg_organizations_fetch_group_members($link, $packId, $excludeChronicles, true);
} else {
    $activeGroups = hg_organizations_fetch_groups_for_organization($link, $packId, $excludeChronicles, true);
    $inactiveGroups = hg_organizations_fetch_groups_for_organization($link, $packId, $excludeChronicles, false);
    $ungroupedMembers = hg_organizations_fetch_ungrouped_members($link, $packId, $excludeChronicles);
    $markdownData = hg_organizations_fetch_markdown_data($link, $packId, $excludeChronicles);
    $markdownData['name'] = $namePack;
    $markdownData['description'] = $infoPack;
    $orgChartAvailable = hg_organizations_org_chart_available($link, $packId);
}

include __DIR__ . '/../../partials/main_nav_bar.php';
include __DIR__ . '/../../views/organizations/detail.php';
