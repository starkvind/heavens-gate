<?php

require_once __DIR__ . '/../../helpers/pretty.php';
require_once __DIR__ . '/../../helpers/system_energy_resource.php';
require_once __DIR__ . '/../../helpers/schema_introspection.php';

function hg_systems_normalize_int_csv($csv): string
{
    $csv = trim((string)$csv);
    if ($csv === '') return '';

    $parts = preg_split('/\s*,\s*/', $csv);
    $ids = [];
    foreach ($parts as $part) {
        if ($part === '' || !preg_match('/^\d+$/', $part)) continue;
        $ids[] = (string)(int)$part;
    }

    return implode(',', array_values(array_unique($ids)));
}

function hg_systems_table_for_detail_type(int $type): ?array
{
    $map = [
        1 => ['table' => 'dim_breeds', 'base' => '/systems/breeds', 'character_field' => 'breed_id'],
        2 => ['table' => 'dim_auspices', 'base' => '/systems/auspices', 'character_field' => 'auspice_id'],
        3 => ['table' => 'dim_tribes', 'base' => '/systems/tribes', 'character_field' => 'tribe_id'],
        4 => ['table' => 'fact_misc_systems', 'base' => '/systems/misc', 'character_field' => ''],
    ];

    return $map[$type] ?? null;
}

function hg_systems_table_exists(mysqli $link, string $table): bool
{
    return in_array($table, [
        'bridge_systems_detail_labels',
        'bridge_systems_resources_to_system',
        'bridge_characters_misc_systems',
    ], true);
}

function hg_systems_column_exists(mysqli $link, string $table, string $column): bool
{
    static $schema = [
        'bridge_systems_detail_labels' => [
            'system_id', 'label_auspice', 'label_breed', 'label_tribe',
            'label_misc', 'label_pack', 'label_clan', 'label_pk_name', 'label_social',
        ],
        'bridge_systems_resources_to_system' => ['is_active', 'sort_order'],
        'bridge_characters_misc_systems' => ['is_active', 'sort_order'],
    ];
    return isset($schema[$table]) && in_array($column, $schema[$table], true);
}

function hg_systems_resolve_id(mysqli $link, string $table, $raw): int
{
    $raw = trim(rawurldecode((string)$raw));
    if ($raw === '') return 0;
    if (preg_match('/^\d+$/', $raw)) return (int)$raw;

    return (int)resolve_pretty_id($link, $table, $raw);
}

function hg_systems_fetch_catalog(mysqli $link)
{
    $sql = "
        SELECT
            s.id AS system_id,
            s.pretty_id,
            s.sort_order AS system_order,
            s.name AS system_name,
            s.image_url AS system_img,
            s.description AS system_description,
            s.forms AS system_forms,
            COALESCE(nb.name, '') AS system_origin
        FROM dim_systems s
        LEFT JOIN dim_bibliographies nb ON s.bibliography_id = nb.id
        ORDER BY s.sort_order, s.name, s.id
    ";

    $rs = $link->query($sql);
    if (!$rs) return false;

    $rows = [];
    while ($row = $rs->fetch_assoc()) $rows[] = $row;
    $rs->free();
    return $rows;
}

function hg_systems_fetch_system(mysqli $link, int $systemId)
{
    if ($systemId <= 0) return null;

    $stmt = $link->prepare("SELECT * FROM dim_systems WHERE id = ? LIMIT 1");
    if (!$stmt) return false;

    $stmt->bind_param('i', $systemId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function hg_systems_fetch_detail_labels(mysqli $link, int $systemId): array
{
    if ($systemId <= 0 || !hg_systems_table_exists($link, 'bridge_systems_detail_labels')) return [];

    $candidates = ['label_auspice', 'label_tribe', 'label_misc'];
    $select = [];
    foreach ($candidates as $column) {
        if (hg_systems_column_exists($link, 'bridge_systems_detail_labels', $column)) $select[] = $column;
    }
    if (!$select || !hg_systems_column_exists($link, 'bridge_systems_detail_labels', 'system_id')) return [];

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM bridge_systems_detail_labels WHERE system_id = ? LIMIT 1';
    $stmt = $link->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $systemId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();

    if (!$row) return [];

    $labels = [];
    foreach ($select as $column) {
        $value = trim((string)($row[$column] ?? ''));
        if ($value !== '') $labels[$column] = $value;
    }
    return $labels;
}

function hg_systems_fetch_forms(mysqli $link, int $systemId)
{
    if ($systemId <= 0) return [];

    $sql = "SELECT
                f.id,
                f.form,
                COALESCE(
                    GROUP_CONCAT(
                        DISTINCT COALESCE(NULLIF(db.name, ''), NULLIF(dt.name, ''))
                        ORDER BY COALESCE(NULLIF(db.name, ''), NULLIF(dt.name, ''))
                        SEPARATOR ', '
                    ),
                    ''
                ) AS applicability_name,
                COALESCE(
                    GROUP_CONCAT(
                        DISTINCT COALESCE(NULLIF(db.name, ''), NULLIF(dt.name, ''))
                        ORDER BY COALESCE(NULLIF(db.name, ''), NULLIF(dt.name, ''))
                        SEPARATOR ', '
                    ),
                    ''
                ) AS breed_name,
                COALESCE(
                    GROUP_CONCAT(
                        DISTINCT COALESCE(NULLIF(db.name, ''), NULLIF(dt.name, ''))
                        ORDER BY COALESCE(NULLIF(db.name, ''), NULLIF(dt.name, ''))
                        SEPARATOR ', '
                    ),
                    ''
                ) AS race
            FROM dim_forms f
            LEFT JOIN bridge_forms_applicability bfa
              ON bfa.form_id = f.id AND bfa.is_active = 1
            LEFT JOIN dim_breeds db ON db.id = bfa.breed_id
            LEFT JOIN dim_tribes dt ON dt.id = bfa.tribe_id
            WHERE f.system_id = ?
            GROUP BY f.id, f.form, f.sort_order
            ORDER BY f.sort_order ASC, applicability_name ASC, f.form ASC";

    $stmt = $link->prepare($sql);
    if (!$stmt) return false;

    $stmt->bind_param('i', $systemId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_systems_fetch_detail_rows(mysqli $link, string $table, int $systemId)
{
    $allowed = ['dim_breeds', 'dim_auspices', 'dim_tribes'];
    if (!in_array($table, $allowed, true) || $systemId <= 0) return [];

    $stmt = $link->prepare("SELECT * FROM `$table` WHERE system_id = ? ORDER BY id");
    if (!$stmt) return false;

    $stmt->bind_param('i', $systemId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_systems_fetch_misc(mysqli $link, string $systemName, ?string $alternateName = null)
{
    $alternateName = $alternateName ?? $systemName;
    $stmt = $link->prepare("SELECT id, name, kind FROM fact_misc_systems WHERE system_name = ? OR system_name = ? ORDER BY id");
    if (!$stmt) return false;

    $stmt->bind_param('ss', $systemName, $alternateName);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_systems_fetch_resources(mysqli $link, int $systemId): array
{
    if ($systemId <= 0) return [];

    $sql = "
        SELECT r.id, r.name, r.kind, r.description, b.sort_order
        FROM bridge_systems_resources_to_system b
        INNER JOIN dim_systems_resources r ON r.id = b.resource_id
        WHERE b.system_id = ?
          AND r.kind IN ('renombre', 'estado')
          AND b.is_active = 1
        ORDER BY
            r.kind,
            COALESCE(NULLIF(CAST(b.sort_order AS SIGNED), 0), CAST(r.sort_order AS SIGNED), 9999),
            CAST(r.sort_order AS SIGNED),
            r.name
    ";

    $stmt = $link->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param('i', $systemId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

if (!function_exists('hg_systems_fetch_resource')) {
    function hg_systems_fetch_resource(mysqli $link, int $resourceId): ?array
    {
        if ($resourceId <= 0) return null;
        $stmt = mysqli_prepare($link, 'SELECT id, name, kind, description FROM dim_systems_resources WHERE id = ? LIMIT 1');
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $resourceId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

function hg_systems_fetch_mobile_resources(mysqli $link, int $systemId)
{
    if ($systemId <= 0) return [];
    $sql = "
        SELECT r.name, r.kind, r.description
        FROM bridge_systems_resources_to_system b
        INNER JOIN dim_systems_resources r ON r.id = b.resource_id
        WHERE b.system_id = ?
          AND (b.is_active = 1 OR b.is_active IS NULL)
        ORDER BY r.kind ASC, COALESCE(b.sort_order, r.sort_order, 9999) ASC, r.name ASC
    ";
    $stmt = $link->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $systemId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_systems_fetch_form_identity(mysqli $link, int $formId): ?array
{
    if ($formId <= 0) return null;
    $stmt = $link->prepare("SELECT id, pretty_id, form FROM dim_forms WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $formId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    if ($rs) $rs->free();
    $stmt->close();
    return $row ?: null;
}

function hg_systems_fetch_detail(mysqli $link, int $type, int $detailId)
{
    $def = hg_systems_table_for_detail_type($type);
    if (!$def || $detailId <= 0) return null;
    $table = $def['table'];
    $energySql = hg_ser_energy_sql_parts($link, $table, 't');
    $sql = "SELECT t.*{$energySql['select']} FROM `$table` t{$energySql['join']} WHERE t.id = ? LIMIT 1";
    $stmt = $link->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $detailId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function hg_systems_gift_availability_table_exists(mysqli $link): bool
{
    return hg_table_exists($link, 'bridge_gifts_availability');
}

function hg_systems_gift_scope_candidates(mysqli $link, string $groupName, int $systemId): array
{
    if ($groupName === '' || $systemId <= 0) return [];

    $defs = [
        ['table' => 'dim_breeds', 'scope_type' => 'race'],
        ['table' => 'dim_auspices', 'scope_type' => 'auspice'],
        ['table' => 'dim_tribes', 'scope_type' => 'tribe'],
    ];

    $scopes = [];
    foreach ($defs as $def) {
        $table = $def['table'];
        $stmt = $link->prepare("SELECT id FROM `$table` WHERE system_id = ? AND name = ? ORDER BY id");
        if (!$stmt) continue;
        $stmt->bind_param('is', $systemId, $groupName);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $scopeId = (int)($row['id'] ?? 0);
            if ($scopeId <= 0) continue;
            $scopes[] = [
                'scope_type' => $def['scope_type'],
                'scope_id' => $scopeId,
            ];
        }
        $stmt->close();
    }

    return $scopes;
}

function hg_systems_fetch_gift_availability_rows(mysqli $link, int $systemId, string $scopeType, int $scopeId): array
{
    if ($systemId <= 0 || $scopeId <= 0) return [];
    if (!in_array($scopeType, ['system', 'race', 'auspice', 'tribe'], true)) return [];

    $sql = "SELECT
                g.id,
                g.pretty_id,
                g.name,
                g.rank AS canonical_rank,
                a.rank_override,
                a.scope_type AS availability_scope_type,
                a.scope_id AS availability_scope_id
            FROM bridge_gifts_availability a
            INNER JOIN fact_gifts g ON g.id = a.gift_id
            WHERE a.system_id = ?
              AND a.scope_type = ?
              AND a.scope_id = ?
            ORDER BY g.id";

    $stmt = $link->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('isi', $systemId, $scopeType, $scopeId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_systems_fetch_legacy_gifts(mysqli $link, string $groupName, int $systemId)
{
    if ($groupName === '' || $systemId <= 0) return [];
    $stmt = $link->prepare("SELECT id, pretty_id, name, rank AS canonical_rank FROM fact_gifts WHERE gift_group = ? AND system_id = ? ORDER BY rank ASC, name ASC");
    if (!$stmt) return false;
    $stmt->bind_param('si', $groupName, $systemId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_systems_merge_gift_row(array &$giftMap, array $row, bool $isDirect, string $source): void
{
    $giftId = (int)($row['id'] ?? 0);
    if ($giftId <= 0) return;

    $canonicalRank = trim((string)($row['canonical_rank'] ?? $row['rank'] ?? ''));
    $override = trim((string)($row['rank_override'] ?? ''));
    $existing = $giftMap[$giftId] ?? null;

    $effectiveRank = $override !== ''
        ? $override
        : ($existing !== null && trim((string)($existing['rank'] ?? '')) !== ''
            ? (string)$existing['rank']
            : $canonicalRank);

    if ($existing !== null && !$isDirect) {
        if (trim((string)($existing['rank'] ?? '')) === '' && $effectiveRank !== '') {
            $giftMap[$giftId]['rank'] = $effectiveRank;
        }
        return;
    }

    $giftMap[$giftId] = [
        'id' => $giftId,
        'pretty_id' => (string)($row['pretty_id'] ?? ''),
        'name' => (string)($row['name'] ?? ''),
        'rank' => $effectiveRank,
        'canonical_rank' => $canonicalRank,
        'rank_override' => $override,
        'availability_scope_type' => (string)($row['availability_scope_type'] ?? ($isDirect ? 'legacy' : 'system')),
        'availability_scope_id' => (int)($row['availability_scope_id'] ?? 0),
        'availability_source' => $source,
    ];
}

function hg_systems_sort_gift_rows(array &$rows): void
{
    usort($rows, static function (array $a, array $b): int {
        $rankA = trim((string)($a['rank'] ?? ''));
        $rankB = trim((string)($b['rank'] ?? ''));
        $numA = is_numeric($rankA) ? (float)$rankA : PHP_FLOAT_MAX;
        $numB = is_numeric($rankB) ? (float)$rankB : PHP_FLOAT_MAX;
        if ($numA < $numB) return -1;
        if ($numA > $numB) return 1;
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });
}

function hg_systems_fetch_gifts(mysqli $link, string $groupName, int $systemId)
{
    if ($groupName === '' || $systemId <= 0) return [];

    if (!hg_systems_gift_availability_table_exists($link)) {
        $legacy = hg_systems_fetch_legacy_gifts($link, $groupName, $systemId);
        if ($legacy === false) return false;
        foreach ($legacy as &$row) {
            $row['rank'] = (string)($row['canonical_rank'] ?? '');
            $row['rank_override'] = '';
            $row['availability_scope_type'] = 'legacy';
            $row['availability_scope_id'] = 0;
            $row['availability_source'] = 'legacy';
        }
        unset($row);
        return $legacy;
    }

    $giftMap = [];

    /* Whole-system Gifts are the common base for every Race/Auspice/Tribe page. */
    $systemRows = hg_systems_fetch_gift_availability_rows($link, $systemId, 'system', $systemId);
    foreach ($systemRows as $row) {
        hg_systems_merge_gift_row($giftMap, $row, false, 'bridge-system');
    }

    /* Resolve the canonical Werecreature axis from the detail name already used by callers. */
    $scopes = hg_systems_gift_scope_candidates($link, $groupName, $systemId);
    $directBridgeRows = 0;
    foreach ($scopes as $scope) {
        $scopeType = (string)$scope['scope_type'];
        $scopeId = (int)$scope['scope_id'];
        $rows = hg_systems_fetch_gift_availability_rows($link, $systemId, $scopeType, $scopeId);
        $directBridgeRows += count($rows);
        foreach ($rows as $row) {
            hg_systems_merge_gift_row($giftMap, $row, true, 'bridge-direct');
        }
    }

    /*
     * Gradual migration compatibility:
     * system-wide bridge rows must not make an unmigrated Race/Auspice/Tribe lose
     * its old group-based Gifts. Once direct bridge rows exist for the detail,
     * the bridge becomes authoritative and the legacy detail lookup disappears.
     */
    if ($directBridgeRows === 0) {
        $legacy = hg_systems_fetch_legacy_gifts($link, $groupName, $systemId);
        if ($legacy === false) return false;
        foreach ($legacy as $row) {
            hg_systems_merge_gift_row($giftMap, $row, true, 'legacy-fallback');
        }
    }

    $rows = array_values($giftMap);
    hg_systems_sort_gift_rows($rows);
    return $rows;
}

function hg_systems_fetch_members(mysqli $link, int $type, int $detailId, $excludedChronicles = '2,7', bool $mobile = false)
{
    $def = hg_systems_table_for_detail_type($type);
    if (!$def || $detailId <= 0) return [];

    $excluded = hg_systems_normalize_int_csv($excludedChronicles);
    $whereChron = $excluded !== '' ? "AND p.chronicle_id NOT IN ($excluded)" : '';
    $charField = $def['character_field'];

    if ($mobile) {
        $select = "p.id, p.name, p.alias, p.image_url, p.gender, COALESCE(dcs.label, '') AS status";
        $joins = 'LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id';
        $groupBy = '';
        $order = 'p.name ASC, p.id ASC';
    } else {
        $select = "p.id, p.name,
            GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS grupos,
            GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ') AS organizaciones";
        $joins = "LEFT JOIN bridge_characters_groups bcg ON bcg.character_id = p.id
            LEFT JOIN dim_groups g ON g.id = bcg.group_id
            LEFT JOIN bridge_characters_organizations bco ON bco.character_id = p.id
            LEFT JOIN dim_organizations o ON o.id = bco.organization_id";
        $groupBy = 'GROUP BY p.id';
        $order = 'p.name ASC';
    }

    if ($charField !== '') {
        $sql = "SELECT $select
                FROM fact_characters p
                $joins
                WHERE p.`$charField` = ?
                  $whereChron
                $groupBy
                ORDER BY $order";
    } elseif ($type === 4) {
        if (!$mobile) {
            $select .= ', MIN(COALESCE(bcms.sort_order, 0)) AS misc_sort_order';
            $order = 'misc_sort_order ASC, p.name ASC';
        }
        $sql = "SELECT $select
                FROM bridge_characters_misc_systems bcms
                INNER JOIN fact_characters p ON p.id = bcms.character_id
                $joins
                WHERE bcms.misc_system_id = ?
                  AND (bcms.is_active = 1 OR bcms.is_active IS NULL)
                  $whereChron
                $groupBy
                ORDER BY $order";
    } else {
        return [];
    }

    $stmt = $link->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $detailId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_systems_fetch_form_modifiers(mysqli $link, int $formId): array
{
    if ($formId <= 0) return [];

    $stmt = $link->prepare(
        "SELECT
            bft.trait_id,
            t.name,
            bft.modifier,
            bft.override_value
         FROM bridge_forms_traits bft
         INNER JOIN dim_traits t ON t.id = bft.trait_id
         WHERE bft.form_id = ?
           AND (COALESCE(bft.modifier, 0) <> 0 OR bft.override_value IS NOT NULL)
         ORDER BY
            CASE bft.trait_id
                WHEN 1 THEN 10
                WHEN 33 THEN 20
                WHEN 34 THEN 30
                WHEN 35 THEN 40
                WHEN 36 THEN 50
                WHEN 37 THEN 60
                WHEN 38 THEN 70
                WHEN 39 THEN 80
                WHEN 40 THEN 90
                ELSE 1000
            END,
            t.name ASC"
    );
    if (!$stmt) return [];

    $stmt->bind_param('i', $formId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) {
        $rows[] = [
            'trait_id' => (int)($row['trait_id'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
            'modifier' => $row['modifier'] !== null ? (int)$row['modifier'] : null,
            'override_value' => $row['override_value'] !== null ? (int)$row['override_value'] : null,
        ];
    }
    if ($rs) $rs->free();
    $stmt->close();

    return $rows;
}

function hg_systems_fetch_form(mysqli $link, int $formId)
{
    if ($formId <= 0) return null;
    $select = "f.*,
        COALESCE(str.modifier, 0) AS strength_bonus,
        COALESCE(dex.modifier, 0) AS dexterity_bonus,
        COALESCE(sta.modifier, 0) AS stamina_bonus,
        CASE WHEN f.hpregen > 0 THEN 1 ELSE 0 END AS regeneration,
        CASE
            WHEN f.hpregen > 0 THEN f.hpregen
            WHEN EXISTS (
                SELECT 1
                FROM dim_breeds regen_breed
                WHERE regen_breed.form_system_id = f.system_id
                  AND COALESCE(regen_breed.regen_normal_per_turn, 0) > 0
            )
            AND NOT EXISTS (
                SELECT 1
                FROM dim_breeds native_breed
                WHERE native_breed.form_system_id = f.system_id
                  AND native_breed.native_form_id = f.id
                  AND COALESCE(native_breed.regen_normal_per_turn, 0) > 0
                  AND COALESCE(native_breed.regen_in_native_form, 0) = 0
            )
            THEN COALESCE((
                SELECT MAX(regen_breed.regen_normal_per_turn)
                FROM dim_breeds regen_breed
                WHERE regen_breed.form_system_id = f.system_id
                  AND COALESCE(regen_breed.regen_normal_per_turn, 0) > 0
            ), 0)
            ELSE 0
        END AS public_hpregen,
        COALESCE(NULLIF(ds.name, ''), '') AS system_name_resolved,
        COALESCE(
            GROUP_CONCAT(
                DISTINCT COALESCE(NULLIF(db.name, ''), NULLIF(dt.name, ''))
                ORDER BY COALESCE(NULLIF(db.name, ''), NULLIF(dt.name, ''))
                SEPARATOR ', '
            ),
            ''
        ) AS applicability_name_resolved,
        COALESCE(
            GROUP_CONCAT(
                DISTINCT COALESCE(NULLIF(db.name, ''), NULLIF(dt.name, ''))
                ORDER BY COALESCE(NULLIF(db.name, ''), NULLIF(dt.name, ''))
                SEPARATOR ', '
            ),
            ''
        ) AS breed_name_resolved";
    $joins = "LEFT JOIN dim_systems ds ON ds.id = f.system_id
        LEFT JOIN bridge_forms_traits str ON str.form_id = f.id AND str.trait_id = 1
        LEFT JOIN bridge_forms_traits dex ON dex.form_id = f.id AND dex.trait_id = 33
        LEFT JOIN bridge_forms_traits sta ON sta.form_id = f.id AND sta.trait_id = 34
        LEFT JOIN bridge_forms_applicability bfa ON bfa.form_id = f.id AND bfa.is_active = 1
        LEFT JOIN dim_breeds db ON db.id = bfa.breed_id
        LEFT JOIN dim_tribes dt ON dt.id = bfa.tribe_id";
    $stmt = $link->prepare("SELECT $select FROM dim_forms f $joins WHERE f.id = ? GROUP BY f.id LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('i', $formId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/**
 * Fetch the title of a tribal patron totem for system detail presentations.
 * Shared by desktop and mobile so controllers do not own SQL.
 */
function hg_systems_fetch_patron_totem_name(mysqli $link, int $patronTotemId): string
{
    if ($patronTotemId <= 0) return '';
    $stmt = $link->prepare('SELECT name FROM dim_totems WHERE id = ? LIMIT 1');
    if (!$stmt) return '';
    $stmt->bind_param('i', $patronTotemId);
    if (!$stmt->execute()) {
        $stmt->close();
        return '';
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result) $result->free();
    $stmt->close();
    return trim((string)($row['name'] ?? ''));
}
