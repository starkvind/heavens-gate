<?php

include_once(__DIR__ . '/../../helpers/public_response.php');

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
$chronicleConditionP = function_exists('hg_mobile_chronicle_exclusion_condition') ? hg_mobile_chronicle_exclusion_condition('p') : 'p.chronicle_id NOT IN (2,7)';
$chronicleConditionG = function_exists('hg_mobile_chronicle_exclusion_condition') ? hg_mobile_chronicle_exclusion_condition('g') : 'g.chronicle_id NOT IN (2,7)';

$requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$isGroupIndex = rtrim($requestPath, '/') === '/groups';

$organizations = [];
$orgSql = "
    SELECT
        o.id,
        o.name,
        '' AS system_name,
        COALESCE(t.name, '') AS totem_name,
        0 AS group_count,
        0 AS member_count
    FROM dim_organizations o
    LEFT JOIN dim_totems t ON t.id = o.totem_id
    ORDER BY o.sort_order ASC, o.name ASC
";
if ($res = $link->query($orgSql)) {
    while ($row = $res->fetch_assoc()) {
        $organizations[] = $row;
    }
    $res->free();
} else {
    hg_public_log_error('mobile_organization_group_list', 'organizations query failed: ' . mysqli_error($link));
    $fallbackSql = "SELECT id, name FROM dim_organizations ORDER BY name ASC";
    if ($res = $link->query($fallbackSql)) {
        while ($row = $res->fetch_assoc()) {
            $row['system_name'] = '';
            $row['totem_name'] = '';
            $row['group_count'] = 0;
            $row['member_count'] = 0;
            $organizations[] = $row;
        }
        $res->free();
    } else {
        hg_public_log_error('mobile_organization_group_list', 'organizations fallback failed: ' . mysqli_error($link));
    }
}

$groups = [];
$groupSql = "
    SELECT
        g.id,
        g.name,
        g.is_active,
        COALESCE(ch.name, '') AS chronicle_name,
        COALESCE(t.name, '') AS totem_name,
        COALESCE(o.id, 0) AS organization_id,
        COALESCE(o.name, 'Sin organizacion') AS organization_name,
        COALESCE(o.sort_order, 999999) AS organization_sort_order,
        (
            SELECT COUNT(DISTINCT bcg.character_id)
            FROM bridge_characters_groups bcg
            INNER JOIN fact_characters p ON p.id = bcg.character_id
            WHERE bcg.group_id = g.id
              AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
              AND {$chronicleConditionP}
        ) AS member_count
    FROM dim_groups g
    LEFT JOIN dim_chronicles ch ON ch.id = g.chronicle_id
    LEFT JOIN dim_totems t ON t.id = g.totem_id
    LEFT JOIN bridge_organizations_groups bog
        ON bog.group_id = g.id
       AND (bog.is_active = 1 OR bog.is_active IS NULL)
    LEFT JOIN dim_organizations o ON o.id = bog.organization_id
    WHERE {$chronicleConditionG}
    ORDER BY COALESCE(o.sort_order, 999999), o.name, g.is_active DESC, g.name ASC
";
if ($res = $link->query($groupSql)) {
    while ($row = $res->fetch_assoc()) {
        $groups[] = $row;
    }
    $res->free();
} else {
    hg_public_log_error('mobile_organization_group_list', 'groups query failed: ' . mysqli_error($link));
}

$groupsByOrg = [];
foreach ($groups as $group) {
    $orgId = (int)($group['organization_id'] ?? 0);
    $key = $orgId > 0 ? (string)$orgId : 'none';
    if (!isset($groupsByOrg[$key])) {
        $groupsByOrg[$key] = [];
    }
    $groupsByOrg[$key][] = $group;
}

$memberCountsByOrg = [];
$memberCountSql = "
    SELECT organization_id, COUNT(DISTINCT character_id) AS member_count
    FROM (
        SELECT bco.organization_id, bco.character_id
        FROM bridge_characters_organizations bco
        INNER JOIN fact_characters p ON p.id = bco.character_id
        WHERE (bco.is_active = 1 OR bco.is_active IS NULL)
          AND {$chronicleConditionP}
        UNION ALL
        SELECT bog.organization_id, bcg.character_id
        FROM bridge_characters_groups bcg
        INNER JOIN bridge_organizations_groups bog ON bog.group_id = bcg.group_id
        INNER JOIN fact_characters p ON p.id = bcg.character_id
        WHERE (bcg.is_active = 1 OR bcg.is_active IS NULL)
          AND (bog.is_active = 1 OR bog.is_active IS NULL)
          AND {$chronicleConditionP}
    ) x
    GROUP BY organization_id
";
if ($res = $link->query($memberCountSql)) {
    while ($row = $res->fetch_assoc()) {
        $memberCountsByOrg[(int)($row['organization_id'] ?? 0)] = (int)($row['member_count'] ?? 0);
    }
    $res->free();
} else {
    hg_public_log_error('mobile_organization_group_list', 'member count query failed: ' . mysqli_error($link));
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


