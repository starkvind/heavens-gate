<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
require_once(__DIR__ . '/../../domains/relationships/mobile_queries.php');

$metaTitle = "Grupos y organizaciones | Heaven's Gate";
$metaDescription = "Listado móvil de organizaciones y grupos.";
$pageSect = 'Grupos';

if (!function_exists('hg_mobile_og_h')) {
    function hg_mobile_og_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_og_url')) {
    function hg_mobile_og_url(mysqli $link, string $table, string $base, int $id): string
    {
        return $id > 0 && function_exists('pretty_url')
            ? pretty_url($link, $table, $base, $id)
            : rtrim($base, '/') . '/' . $id;
    }
}

if (!function_exists('hg_mobile_og_group_url')) {
    function hg_mobile_og_group_url(mysqli $link, int $organizationId, int $groupId): string
    {
        if ($organizationId <= 0) {
            return hg_mobile_og_url($link, 'dim_groups', '/groups', $groupId);
        }
        $orgPath = (string)parse_url(hg_mobile_og_url($link, 'dim_organizations', '/organizations', $organizationId), PHP_URL_PATH);
        $groupPath = (string)parse_url(hg_mobile_og_url($link, 'dim_groups', '/groups', $groupId), PHP_URL_PATH);
        return '/groups/' . rawurlencode(basename($orgPath)) . '/' . rawurlencode(basename($groupPath));
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_organization_group_list', 'missing DB connection');
    hg_public_render_error('Grupos no disponibles', 'No se pudo cargar el listado.');
    return;
}

$requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$isGroupIndex = rtrim($requestPath, '/') === '/groups';
$excludedChronicles = function_exists('hg_chronicle_scope_excluded_csv')
    ? hg_chronicle_scope_excluded_csv()
    : '2,7';

$organizations = hg_relationship_mobile_fetch_organizations($link);
if ($organizations === null) {
    hg_public_log_error('mobile_organization_group_list', 'organizations query failed');
    $organizations = [];
}

$groups = hg_relationship_mobile_fetch_groups($link, $excludedChronicles);
if ($groups === null) {
    hg_public_log_error('mobile_organization_group_list', 'groups query failed');
    $groups = [];
}

$groupsByOrg = [];
foreach ($groups as $group) {
    $orgId = (int)($group['organization_id'] ?? 0);
    $key = $orgId > 0 ? (string)$orgId : 'none';
    if (!isset($groupsByOrg[$key])) $groupsByOrg[$key] = [];
    $groupsByOrg[$key][] = $group;
}

$memberCountsByOrg = hg_relationship_mobile_fetch_member_counts_by_organization($link, $excludedChronicles);
if ($memberCountsByOrg === null) {
    hg_public_log_error('mobile_organization_group_list', 'member count query failed');
    $memberCountsByOrg = [];
}

foreach ($organizations as &$organization) {
    $orgId = (int)($organization['id'] ?? 0);
    $organization['group_count'] = isset($groupsByOrg[(string)$orgId]) ? count($groupsByOrg[(string)$orgId]) : 0;
    $organization['member_count'] = $memberCountsByOrg[$orgId] ?? 0;
}
unset($organization);
?>

<section class="hg-mobile-section">
    <h1><?= $isGroupIndex ? 'Grupos' : 'Organizaciones' ?></h1>

    <div class="hg-mobile-action-row">
        <a href="/organizations?view=mobile">Organizaciones</a>
        <a href="/groups?view=mobile">Grupos</a>
    </div>
</section>

<?php if ($isGroupIndex): ?>
    <section class="hg-mobile-section">
        <p class="hg-mobile-muted"><?= number_format(count($groups), 0, ',', '.') ?> grupos</p>
        <div class="hg-mobile-card-list">
            <?php foreach ($groups as $group): ?>
                <?php
                    $groupId = (int)($group['id'] ?? 0);
                    $orgId = (int)($group['organization_id'] ?? 0);
                    $href = hg_mobile_og_group_url($link, $orgId, $groupId);
                ?>
                <a class="hg-mobile-card" href="<?= hg_mobile_og_h($href) ?>">
                    <strong><?= hg_mobile_og_h($group['name'] ?? '') ?></strong>
                    <span><?= hg_mobile_og_h($group['organization_name'] ?? '') ?><?= !empty($group['totem_name']) ? ' - Totem: ' . hg_mobile_og_h($group['totem_name']) : '' ?></span>
                    <span><?= ((int)($group['is_active'] ?? 0) === 1) ? 'Activo' : 'Antiguo' ?> - <?= (int)($group['member_count'] ?? 0) ?> miembros</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <section class="hg-mobile-section">
        <p class="hg-mobile-muted"><?= number_format(count($organizations), 0, ',', '.') ?> organizaciones</p>
        <div class="hg-mobile-card-list">
            <?php foreach ($organizations as $organization): ?>
                <?php
                    $orgId = (int)($organization['id'] ?? 0);
                    $href = hg_mobile_og_url($link, 'dim_organizations', '/organizations', $orgId);
                    $orgGroups = $groupsByOrg[(string)$orgId] ?? [];
                ?>
                <a class="hg-mobile-card" href="<?= hg_mobile_og_h($href) ?>">
                    <strong><?= hg_mobile_og_h($organization['name'] ?? '') ?></strong>
                    <span><?= hg_mobile_og_h($organization['system_name'] ?? '') ?><?= !empty($organization['totem_name']) ? ' - Totem: ' . hg_mobile_og_h($organization['totem_name']) : '' ?></span>
                    <span><?= (int)($organization['group_count'] ?? 0) ?> grupos - <?= (int)($organization['member_count'] ?? 0) ?> miembros</span>
                </a>
                <?php if (!empty($orgGroups)): ?>
                    <details class="hg-mobile-details">
                        <summary>Grupos de <?= hg_mobile_og_h($organization['name'] ?? '') ?></summary>
                        <div class="hg-mobile-list hg-mobile-linked-list">
                            <?php foreach ($orgGroups as $group): ?>
                                <?php $groupHref = hg_mobile_og_group_url($link, $orgId, (int)($group['id'] ?? 0)); ?>
                                <div>
                                    <strong><a href="<?= hg_mobile_og_h($groupHref) ?>"><?= hg_mobile_og_h($group['name'] ?? '') ?></a></strong>
                                    <span><?= ((int)($group['is_active'] ?? 0) === 1) ? 'Activo' : 'Antiguo' ?> - <?= (int)($group['member_count'] ?? 0) ?> miembros</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
