<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');

$metaTitle = "Biografías | Heaven's Gate";
$metaDescription = "Listado móvil de personajes por tipo.";
$pageSect = 'Biografías';

if (!function_exists('hg_mobile_type_h')) {
    function hg_mobile_type_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_type_table_exists')) {
    function hg_mobile_type_table_exists(mysqli $link, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $exists = false;
        if ($stmt = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?")) {
            $stmt->bind_param('s', $table);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
            $exists = ((int)$count > 0);
        }
        $cache[$table] = $exists;
        return $exists;
    }
}

if (!function_exists('hg_mobile_type_url')) {
    function hg_mobile_type_url(mysqli $link, string $table, string $base, int $id): string
    {
        return $id > 0 && function_exists('pretty_url')
            ? pretty_url($link, $table, $base, $id)
            : rtrim($base, '/') . '/' . $id;
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_character_type_detail', 'missing DB connection');
    hg_public_render_error('Biografías no disponibles', 'No se pudo cargar el listado.');
    return;
}

$rawType = trim((string)($_GET['t'] ?? ''));
$typeId = 0;
if ($rawType !== '') {
    $resolved = resolve_pretty_id($link, 'dim_character_types', $rawType);
    $typeId = (int)($resolved ?? 0);
}

if ($typeId <= 0) {
    hg_public_render_not_found('Tipo no encontrado', 'No se pudo localizar el tipo de personaje solicitado.');
    return;
}

$type = null;
if ($stmt = $link->prepare('SELECT id, kind FROM dim_character_types WHERE id = ? LIMIT 1')) {
    $stmt->bind_param('i', $typeId);
    $stmt->execute();
    $res = $stmt->get_result();
    $type = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

if (!$type) {
    hg_public_render_not_found('Tipo no encontrado', 'No se pudo localizar el tipo de personaje solicitado.');
    return;
}

$typeName = trim((string)($type['kind'] ?? 'Biografías'));
$metaTitle = $typeName . " | Biografías | Heaven's Gate";
$metaDescription = "Personajes del tipo " . $typeName . " en version móvil.";
$pageSect = $typeName;

$activePackIdExpr = "
    SELECT bcg.group_id
    FROM bridge_characters_groups bcg
    WHERE bcg.character_id = p.id
      AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
    ORDER BY bcg.updated_at DESC, bcg.created_at DESC, bcg.group_id DESC
    LIMIT 1
";

$activePackOrgIdExpr = "
    SELECT bog.organization_id
    FROM bridge_organizations_groups bog
    WHERE bog.group_id = (($activePackIdExpr))
      AND (bog.is_active = 1 OR bog.is_active IS NULL)
    ORDER BY bog.updated_at DESC, bog.created_at DESC, bog.organization_id DESC
    LIMIT 1
";

$activeDirectOrgIdExpr = "
    SELECT bco.organization_id
    FROM bridge_characters_organizations bco
    WHERE bco.character_id = p.id
      AND (bco.is_active = 1 OR bco.is_active IS NULL)
    ORDER BY bco.updated_at DESC, bco.created_at DESC, bco.organization_id DESC
    LIMIT 1
";

$activePackNameExpr = "
    SELECT g.name
    FROM bridge_characters_groups bcg
    INNER JOIN dim_groups g ON g.id = bcg.group_id
    WHERE bcg.character_id = p.id
      AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
    ORDER BY bcg.updated_at DESC, bcg.created_at DESC, bcg.group_id DESC
    LIMIT 1
";

$chronicleConditionP = function_exists('hg_mobile_chronicle_exclusion_condition') ? hg_mobile_chronicle_exclusion_condition('p') : 'p.chronicle_id NOT IN (2,7)';

$characters = [];
$sql = "
    SELECT
        p.id,
        p.pretty_id,
        p.name,
        p.alias,
        p.concept,
        p.image_url,
        p.gender,
        p.character_kind,
        COALESCE(dcs.label, '') AS status_label,
        COALESCE(({$activePackNameExpr}), '') AS pack_name,
        COALESCE(({$activePackOrgIdExpr}), ({$activeDirectOrgIdExpr}), 0) AS organization_id,
        COALESCE(
            (SELECT o.name FROM dim_organizations o WHERE o.id = ({$activePackOrgIdExpr}) LIMIT 1),
            (SELECT o.name FROM dim_organizations o WHERE o.id = ({$activeDirectOrgIdExpr}) LIMIT 1),
            'Sin clan'
        ) AS organization_name,
        IFNULL(COALESCE(
            (SELECT o.sort_order FROM dim_organizations o WHERE o.id = ({$activePackOrgIdExpr}) LIMIT 1),
            (SELECT o.sort_order FROM dim_organizations o WHERE o.id = ({$activeDirectOrgIdExpr}) LIMIT 1)
        ), 999999) AS organization_sort_order
    FROM fact_characters p
    LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
    WHERE p.character_type_id = ?
      AND {$chronicleConditionP}
    ORDER BY organization_sort_order ASC, organization_name ASC, p.name ASC
";

if ($stmt = $link->prepare($sql)) {
    $stmt->bind_param('i', $typeId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $characters[] = $row;
        }
    } else {
        hg_public_log_error('mobile_character_type_detail', 'list execute failed: ' . mysqli_error($link));
    }
    $stmt->close();
} else {
    hg_public_log_error('mobile_character_type_detail', 'list prepare failed: ' . mysqli_error($link));
}

$groups = [];
foreach ($characters as $character) {
    $organizationId = (int)($character['organization_id'] ?? 0);
    $key = $organizationId > 0 ? (string)$organizationId : 'none';
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'id' => $organizationId,
            'name' => trim((string)($character['organization_name'] ?? '')) ?: 'Sin clan',
            'sort_order' => (int)($character['organization_sort_order'] ?? 999999),
            'items' => [],
        ];
    }
    $groups[$key]['items'][] = $character;
}

$groupKeys = array_keys($groups);
usort($groupKeys, static function (string $a, string $b) use ($groups): int {
    if ($a === 'none') {
        return 1;
    }
    if ($b === 'none') {
        return -1;
    }
    $sortA = (int)($groups[$a]['sort_order'] ?? 999999);
    $sortB = (int)($groups[$b]['sort_order'] ?? 999999);
    if ($sortA !== $sortB) {
        return $sortA <=> $sortB;
    }
    return strcasecmp((string)($groups[$a]['name'] ?? ''), (string)($groups[$b]['name'] ?? ''));
});
?>

<section class="hg-mobile-section">
    <nav class="hg-mobile-local-nav">
        <a href="/characters/types?view=mobile">Volver a tipos</a>
    </nav>

    <h1><?= hg_mobile_type_h($typeName) ?></h1>
    <p class="hg-mobile-muted"><?= number_format(count($characters), 0, ',', '.') ?> personajes</p>

    <?php if (empty($characters)): ?>
        <p class="hg-mobile-muted">No hay personajes para este tipo.</p>
    <?php endif; ?>

    <?php foreach ($groupKeys as $groupKey): ?>
        <?php $group = $groups[$groupKey]; ?>
        <details class="hg-mobile-details" open>
            <summary><?= hg_mobile_type_h($group['name'] ?? 'Sin clan') ?> · <?= count($group['items'] ?? []) ?></summary>
            <div class="hg-mobile-character-list">
                <?php foreach (($group['items'] ?? []) as $character): ?>
                    <?php
                        $characterId = (int)($character['id'] ?? 0);
                        $href = hg_mobile_type_url($link, 'fact_characters', '/characters', $characterId);
                        $avatar = hg_character_avatar_url((string)($character['image_url'] ?? ''), (string)($character['gender'] ?? ''));
                    ?>
                    <a class="hg-mobile-character-card" href="<?= hg_mobile_type_h($href) ?>">
                        <?php if ($avatar !== ''): ?>
                            <img src="<?= hg_mobile_type_h($avatar) ?>" alt="">
                        <?php else: ?>
                            <span class="hg-mobile-character-avatar" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="hg-mobile-character-main">
                            <strong><?= hg_mobile_type_h($character['name'] ?? '') ?></strong>
                            <?php if (!empty($character['alias'])): ?>
                                <em><?= hg_mobile_type_h($character['alias']) ?></em>
                            <?php endif; ?>
                            <?php if (!empty($character['concept'])): ?>
                                <span><?= hg_mobile_type_h($character['concept']) ?></span>
                            <?php endif; ?>
                            <small>
                                <?= hg_mobile_type_h($character['status_label'] ?? '') ?>
                                <?php if (!empty($character['pack_name'])): ?>
                                    - <?= hg_mobile_type_h($character['pack_name']) ?>
                                <?php endif; ?>
                            </small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endforeach; ?>
</section>
