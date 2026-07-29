<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');

$metaTitle = "Grupo | Heaven's Gate";
$metaDescription = "Ficha móvil de grupo u organizacion.";
$pageSect = 'Grupos';

if (!function_exists('hg_mobile_ogd_h')) {
    function hg_mobile_ogd_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_ogd_url')) {
    function hg_mobile_ogd_url(mysqli $link, string $table, string $base, int $id): string
    {
        return $id > 0 && function_exists('pretty_url')
            ? pretty_url($link, $table, $base, $id)
            : rtrim($base, '/') . '/' . $id;
    }
}

if (!function_exists('hg_mobile_ogd_group_url')) {
    function hg_mobile_ogd_group_url(mysqli $link, int $organizationId, int $groupId): string
    {
        if ($organizationId <= 0) {
            return hg_mobile_ogd_url($link, 'dim_groups', '/groups', $groupId);
        }
        $orgPath = (string)parse_url(hg_mobile_ogd_url($link, 'dim_organizations', '/organizations', $organizationId), PHP_URL_PATH);
        $groupPath = (string)parse_url(hg_mobile_ogd_url($link, 'dim_groups', '/groups', $groupId), PHP_URL_PATH);
        return '/groups/' . rawurlencode(basename($orgPath)) . '/' . rawurlencode(basename($groupPath));
    }
}

if (!function_exists('hg_mobile_ogd_rows')) {
    function hg_mobile_ogd_rows(mysqli $link, string $sql, string $types = '', array $params = []): array
    {
        $rows = [];
        if ($types !== '') {
            $stmt = $link->prepare($sql);
            if (!$stmt) {
                hg_public_log_error('mobile_organization_group_detail', 'prepare failed: ' . mysqli_error($link));
                return [];
            }
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($res && ($row = $res->fetch_assoc())) {
                    $rows[] = $row;
                }
            } else {
                hg_public_log_error('mobile_organization_group_detail', 'execute failed: ' . mysqli_error($link));
            }
            $stmt->close();
            return $rows;
        }

        if ($res = $link->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        } else {
            hg_public_log_error('mobile_organization_group_detail', 'query failed: ' . mysqli_error($link));
        }
        return $rows;
    }
}

if (!function_exists('hg_mobile_ogd_resolve')) {
    function hg_mobile_ogd_resolve(mysqli $link, string $table, string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        if (preg_match('/^\d+$/', $value)) {
            return (int)$value;
        }
        return function_exists('resolve_pretty_id') ? (int)(resolve_pretty_id($link, $table, $value) ?? 0) : 0;
    }
}

if (!function_exists('hg_mobile_ogd_character_card')) {
    function hg_mobile_ogd_character_card(mysqli $link, array $character): void
    {
        $id = (int)($character['id'] ?? 0);
        $href = hg_mobile_ogd_url($link, 'fact_characters', '/characters', $id);
        $avatar = hg_character_avatar_url((string)($character['image_url'] ?? ''), (string)($character['gender'] ?? ''));
        ?>
        <a class="hg-mobile-character-card" href="<?= hg_mobile_ogd_h($href) ?>">
            <?php if ($avatar !== ''): ?>
                <img src="<?= hg_mobile_ogd_h($avatar) ?>" alt="">
            <?php else: ?>
                <span class="hg-mobile-character-avatar" aria-hidden="true"></span>
            <?php endif; ?>
            <span class="hg-mobile-character-main">
                <strong><?= hg_mobile_ogd_h($character['name'] ?? '') ?></strong>
                <?php if (!empty($character['alias'])): ?>
                    <em><?= hg_mobile_ogd_h($character['alias']) ?></em>
                <?php endif; ?>
                <?php if (!empty($character['position'])): ?>
                    <span><?= hg_mobile_ogd_h($character['position']) ?></span>
                <?php elseif (!empty($character['role'])): ?>
                    <span><?= hg_mobile_ogd_h($character['role']) ?></span>
                <?php endif; ?>
                <small><?= hg_mobile_ogd_h($character['status_label'] ?? '') ?></small>
            </span>
        </a>
        <?php
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_organization_group_detail', 'missing DB connection');
    hg_public_render_error('Ficha no disponible', 'No se pudo cargar la ficha solicitada.');
    return;
}
$chronicleConditionP = function_exists('hg_mobile_chronicle_exclusion_condition') ? hg_mobile_chronicle_exclusion_condition('p') : 'p.chronicle_id NOT IN (2,7)';

$type = (int)($_GET['t'] ?? 0);
$rawId = trim((string)($_GET['b'] ?? ''));
$preferredOrgRaw = trim((string)($_GET['org'] ?? ''));

if ($type === 1) {
    $id = hg_mobile_ogd_resolve($link, 'dim_groups', $rawId);
} elseif ($type === 2) {
    $id = hg_mobile_ogd_resolve($link, 'dim_organizations', $rawId);
} else {
    hg_public_render_not_found('Ficha no encontrada', 'El tipo solicitado no existe.');
    return;
}

if ($id <= 0) {
    hg_public_render_not_found('Ficha no encontrada', 'No se pudo localizar el elemento solicitado.');
    return;
}

if ($type === 2) {
    $rows = hg_mobile_ogd_rows($link, "
        SELECT o.*, COALESCE(t.name, '') AS totem_name
        FROM dim_organizations o
        LEFT JOIN dim_totems t ON t.id = o.totem_id
        WHERE o.id = ?
        LIMIT 1
    ", 'i', [$id]);
    $item = $rows[0] ?? null;
    if (!$item) {
        hg_public_render_not_found('Organizacion no encontrada', 'No se pudo localizar la organizacion solicitada.');
        return;
    }

    $name = (string)($item['name'] ?? 'Organizacion');
    $metaTitle = $name . " | Organizaciones | Heaven's Gate";
    $metaDescription = trim(strip_tags((string)($item['description'] ?? '')));

    $activeGroups = hg_mobile_ogd_rows($link, "
        SELECT g.id, g.name, g.is_active, COALESCE(t.name, '') AS totem_name,
               COUNT(DISTINCT bcg.character_id) AS member_count
        FROM bridge_organizations_groups bog
        INNER JOIN dim_groups g ON g.id = bog.group_id
        LEFT JOIN dim_totems t ON t.id = g.totem_id
        LEFT JOIN bridge_characters_groups bcg
            ON bcg.group_id = g.id
           AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
        WHERE bog.organization_id = ?
          AND (bog.is_active = 1 OR bog.is_active IS NULL)
          AND g.is_active = 1
        GROUP BY g.id, g.name, g.is_active, t.name
        ORDER BY g.name ASC
    ", 'i', [$id]);

    $inactiveGroups = hg_mobile_ogd_rows($link, "
        SELECT g.id, g.name, g.is_active, COALESCE(t.name, '') AS totem_name,
               COUNT(DISTINCT bcg.character_id) AS member_count
        FROM bridge_organizations_groups bog
        INNER JOIN dim_groups g ON g.id = bog.group_id
        LEFT JOIN dim_totems t ON t.id = g.totem_id
        LEFT JOIN bridge_characters_groups bcg ON bcg.group_id = g.id
        WHERE bog.organization_id = ?
          AND (bog.is_active = 1 OR bog.is_active IS NULL)
          AND g.is_active = 0
        GROUP BY g.id, g.name, g.is_active, t.name
        ORDER BY g.name ASC
    ", 'i', [$id]);

    $directMembers = hg_mobile_ogd_rows($link, "
        SELECT p.id, p.name, p.alias, p.image_url, p.gender, COALESCE(dcs.label, '') AS status_label, bco.role
        FROM bridge_characters_organizations bco
        INNER JOIN fact_characters p ON p.id = bco.character_id
        LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
        LEFT JOIN bridge_characters_groups bcg
            ON bcg.character_id = p.id
           AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
        WHERE bco.organization_id = ?
          AND (bco.is_active = 1 OR bco.is_active IS NULL)
          AND bcg.character_id IS NULL
          AND {$chronicleConditionP}
        ORDER BY p.name ASC
    ", 'i', [$id]);
    ?>

    <article class="hg-mobile-bio">
        <nav class="hg-mobile-local-nav">
            <a href="/organizations?view=mobile">Volver a organizaciones</a>
        </nav>

        <section class="hg-mobile-section">
            <h1><?= hg_mobile_ogd_h($name) ?></h1>
            <div class="hg-mobile-fact-grid">
                <?php if (!empty($item['system_name'])): ?><div><span>Sistema</span><strong><?= hg_mobile_ogd_h($item['system_name']) ?></strong></div><?php endif; ?>
                <?php if (!empty($item['totem_name'])): ?><div><span>Totem</span><strong><?= hg_mobile_ogd_h($item['totem_name']) ?></strong></div><?php endif; ?>
                <div><span>Grupos activos</span><strong><?= count($activeGroups) ?></strong></div>
                <div><span>Grupos antiguos</span><strong><?= count($inactiveGroups) ?></strong></div>
            </div>
        </section>

        <?php if (trim(strip_tags((string)($item['description'] ?? ''))) !== ''): ?>
            <section class="hg-mobile-section hg-mobile-prose">
                <h2>Descripción</h2>
                <?= (string)$item['description'] ?>
            </section>
        <?php endif; ?>

        <?php foreach (['Grupos activos' => $activeGroups, 'Grupos antiguos' => $inactiveGroups] as $title => $rows): ?>
            <?php if (empty($rows)) { continue; } ?>
            <section class="hg-mobile-section">
                <h2><?= hg_mobile_ogd_h($title) ?></h2>
                <div class="hg-mobile-list hg-mobile-linked-list">
                    <?php foreach ($rows as $group): ?>
                        <?php $href = hg_mobile_ogd_group_url($link, $id, (int)($group['id'] ?? 0)); ?>
                        <div>
                            <strong><a href="<?= hg_mobile_ogd_h($href) ?>"><?= hg_mobile_ogd_h($group['name'] ?? '') ?></a></strong>
                            <span><?= (int)($group['member_count'] ?? 0) ?> miembros<?= !empty($group['totem_name']) ? ' - Totem: ' . hg_mobile_ogd_h($group['totem_name']) : '' ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if (!empty($directMembers)): ?>
            <section class="hg-mobile-section">
                <h2>Personajes sin grupo</h2>
                <div class="hg-mobile-character-list">
                    <?php foreach ($directMembers as $member): ?>
                        <?php hg_mobile_ogd_character_card($link, $member); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </article>
    <?php
    return;
}

$preferredOrgId = hg_mobile_ogd_resolve($link, 'dim_organizations', $preferredOrgRaw);
$rows = hg_mobile_ogd_rows($link, "
    SELECT g.*, COALESCE(ch.name, '') AS chronicle_name, COALESCE(t.name, '') AS totem_name
    FROM dim_groups g
    LEFT JOIN dim_chronicles ch ON ch.id = g.chronicle_id
    LEFT JOIN dim_totems t ON t.id = g.totem_id
    WHERE g.id = ?
    LIMIT 1
", 'i', [$id]);
$item = $rows[0] ?? null;
if (!$item) {
    hg_public_render_not_found('Grupo no encontrado', 'No se pudo localizar el grupo solicitado.');
    return;
}

$orgRows = [];
if ($preferredOrgId > 0) {
    $orgRows = hg_mobile_ogd_rows($link, "
        SELECT o.id, o.name
        FROM bridge_organizations_groups bog
        INNER JOIN dim_organizations o ON o.id = bog.organization_id
        WHERE bog.group_id = ?
          AND bog.organization_id = ?
          AND (bog.is_active = 1 OR bog.is_active IS NULL)
        LIMIT 1
    ", 'ii', [$id, $preferredOrgId]);
}
if (empty($orgRows)) {
    $orgRows = hg_mobile_ogd_rows($link, "
        SELECT o.id, o.name
        FROM bridge_organizations_groups bog
        INNER JOIN dim_organizations o ON o.id = bog.organization_id
        WHERE bog.group_id = ?
          AND (bog.is_active = 1 OR bog.is_active IS NULL)
        ORDER BY bog.updated_at DESC, bog.created_at DESC, bog.organization_id DESC
        LIMIT 1
    ", 'i', [$id]);
}
$organization = $orgRows[0] ?? ['id' => 0, 'name' => ''];
$organizationId = (int)($organization['id'] ?? 0);

$name = (string)($item['name'] ?? 'Grupo');
$metaTitle = $name . " | Grupos | Heaven's Gate";
$metaDescription = trim(strip_tags((string)($item['description'] ?? '')));

$activeMembers = hg_mobile_ogd_rows($link, "
    SELECT p.id, p.name, p.alias, p.image_url, p.gender, COALESCE(dcs.label, '') AS status_label, bcg.position
    FROM bridge_characters_groups bcg
    INNER JOIN fact_characters p ON p.id = bcg.character_id
    LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
    WHERE bcg.group_id = ?
      AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
      AND LOWER(TRIM(COALESCE(dcs.label, ''))) COLLATE utf8mb4_unicode_ci <> 'cadaver'
      AND {$chronicleConditionP}
    ORDER BY p.name ASC
", 'i', [$id]);

$oldMembers = hg_mobile_ogd_rows($link, "
    SELECT p.id, p.name, p.alias, p.image_url, p.gender, COALESCE(dcs.label, '') AS status_label, bcg.position
    FROM bridge_characters_groups bcg
    INNER JOIN fact_characters p ON p.id = bcg.character_id
    LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
    WHERE bcg.group_id = ?
      AND (bcg.is_active = 0 OR LOWER(TRIM(COALESCE(dcs.label, ''))) COLLATE utf8mb4_unicode_ci = 'cadaver')
      AND {$chronicleConditionP}
    ORDER BY p.name ASC
", 'i', [$id]);
?>

<article class="hg-mobile-bio">
    <nav class="hg-mobile-local-nav">
        <a href="/groups?view=mobile">Volver a grupos</a>
    </nav>

    <section class="hg-mobile-section">
        <h1><?= hg_mobile_ogd_h($name) ?></h1>
        <div class="hg-mobile-fact-grid">
            <?php if ($organizationId > 0): ?>
                <div><span>Organizacion</span><strong><a href="<?= hg_mobile_ogd_h(hg_mobile_ogd_url($link, 'dim_organizations', '/organizations', $organizationId)) ?>"><?= hg_mobile_ogd_h($organization['name'] ?? '') ?></a></strong></div>
            <?php endif; ?>
            <?php if (!empty($item['chronicle_name'])): ?><div><span>Cronica</span><strong><?= hg_mobile_ogd_h($item['chronicle_name']) ?></strong></div><?php endif; ?>
            <?php if (!empty($item['totem_name'])): ?><div><span>Totem</span><strong><?= hg_mobile_ogd_h($item['totem_name']) ?></strong></div><?php endif; ?>
            <div><span>Estado</span><strong><?= ((int)($item['is_active'] ?? 0) === 1) ? 'Activo' : 'Antiguo' ?></strong></div>
        </div>
    </section>

    <?php if (trim(strip_tags((string)($item['description'] ?? ''))) !== ''): ?>
        <section class="hg-mobile-section hg-mobile-prose">
            <h2>Descripción</h2>
            <?= (string)$item['description'] ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($activeMembers)): ?>
        <section class="hg-mobile-section">
            <h2>Miembros</h2>
            <div class="hg-mobile-character-list">
                <?php foreach ($activeMembers as $member): ?>
                    <?php hg_mobile_ogd_character_card($link, $member); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($oldMembers)): ?>
        <section class="hg-mobile-section">
            <h2>Antiguos miembros</h2>
            <div class="hg-mobile-character-list">
                <?php foreach ($oldMembers as $member): ?>
                    <?php hg_mobile_ogd_character_card($link, $member); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>
