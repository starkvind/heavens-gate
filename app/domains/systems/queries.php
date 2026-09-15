<?php

require_once __DIR__ . '/../../helpers/pretty.php';
require_once __DIR__ . '/../../helpers/system_energy_resource.php';

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
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];

    $safeTable = str_replace('`', '', $table);
    $rs = $link->query("SHOW TABLES LIKE '" . $link->real_escape_string($safeTable) . "'");
    if (!$rs) return $cache[$table] = false;

    $exists = $rs->num_rows > 0;
    $rs->free();
    return $cache[$table] = $exists;
}

function hg_systems_column_exists(mysqli $link, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . ':' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];

    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
    if (!$stmt = $link->prepare($sql)) return $cache[$key] = false;

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $rs = $stmt->get_result();
    $exists = $rs && $rs->num_rows > 0;
    $stmt->close();

    return $cache[$key] = $exists;
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

    $hasBreedId = hg_systems_column_exists($link, 'dim_forms', 'breed_id');
    $hasRace = hg_systems_column_exists($link, 'dim_forms', 'race');
    $selectRace = $hasRace ? 'f.race' : "''";

    if ($hasBreedId) {
        $selectBreed = $hasRace
            ? "COALESCE(NULLIF(db.name,''), NULLIF(f.race,''))"
            : "COALESCE(NULLIF(db.name,''), '')";
        $join = 'LEFT JOIN dim_breeds db ON db.id = f.breed_id';
    } elseif ($hasRace) {
        $selectBreed = "COALESCE(NULLIF(db.name,''), NULLIF(f.race,''))";
        $join = 'LEFT JOIN dim_breeds db ON db.system_id = f.system_id AND db.name = f.race';
    } else {
        $selectBreed = "''";
        $join = '';
    }

    $sql = "SELECT f.id, f.form, {$selectRace} AS race, {$selectBreed} AS breed_name
            FROM dim_forms f {$join}
            WHERE f.system_id = ?
            ORDER BY " . ($hasRace ? 'f.race, ' : '') . 'f.form';

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

function hg_systems_fetch_resources(mysqli $link, int $systemId)
{
    if ($systemId <= 0 || !hg_systems_table_exists($link, 'bridge_systems_resources_to_system')) return [];

    $hasActive = hg_systems_column_exists($link, 'bridge_systems_resources_to_system', 'is_active');
    $hasBridgeSort = hg_systems_column_exists($link, 'bridge_systems_resources_to_system', 'sort_order');
    $activeSql = $hasActive ? 'AND b.is_active = 1' : '';
    $sortExpr = $hasBridgeSort ? 'COALESCE(NULLIF(CAST(b.sort_order AS SIGNED), 0), CAST(r.sort_order AS SIGNED), 9999)' : 'COALESCE(CAST(r.sort_order AS SIGNED), 9999)';

    $sql = "
        SELECT r.id, r.name, r.kind, r.description" . ($hasBridgeSort ? ', b.sort_order' : '') . "
        FROM bridge_systems_resources_to_system b
        INNER JOIN dim_systems_resources r ON r.id = b.resource_id
        WHERE b.system_id = ?
          AND r.kind IN ('renombre','estado')
          $activeSql
        ORDER BY r.kind, $sortExpr, CAST(r.sort_order AS SIGNED), r.name
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

function hg_systems_fetch_mobile_resources(mysqli $link, int $systemId)
{
    if ($systemId <= 0 || !hg_systems_table_exists($link, 'bridge_systems_resources_to_system')) return [];

    $hasActive = hg_systems_column_exists($link, 'bridge_systems_resources_to_system', 'is_active');
    $hasBridgeSort = hg_systems_column_exists($link, 'bridge_systems_resources_to_system', 'sort_order');
    $activeSql = $hasActive ? 'AND (b.is_active = 1 OR b.is_active IS NULL)' : '';
    $sortExpr = $hasBridgeSort ? 'COALESCE(b.sort_order, r.sort_order, 9999)' : 'COALESCE(r.sort_order, 9999)';

    $sql = "
        SELECT r.name, r.kind, r.description
        FROM bridge_systems_resources_to_system b
        INNER JOIN dim_systems_resources r ON r.id = b.resource_id
        WHERE b.system_id = ? $activeSql
        ORDER BY r.kind ASC, $sortExpr ASC, r.name ASC
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

function hg_systems_fetch_gifts(mysqli $link, string $groupName, int $systemId)
{
    if ($groupName === '' || $systemId <= 0) return [];

    $stmt = $link->prepare("SELECT id, name, rank FROM fact_gifts WHERE gift_group = ? AND system_id = ? ORDER BY rank ASC, name ASC");
    if (!$stmt) return false;

    $stmt->bind_param('si', $groupName, $systemId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
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
    } elseif ($type === 4 && hg_systems_table_exists($link, 'bridge_characters_misc_systems')) {
        $hasActive = hg_systems_column_exists($link, 'bridge_characters_misc_systems', 'is_active');
        $hasSort = hg_systems_column_exists($link, 'bridge_characters_misc_systems', 'sort_order');
        $activeSql = $hasActive ? 'AND (bcms.is_active = 1 OR bcms.is_active IS NULL)' : '';

        if (!$mobile && $hasSort) {
            $select .= ', MIN(COALESCE(bcms.sort_order, 0)) AS misc_sort_order';
            $order = 'misc_sort_order ASC, p.name ASC';
        }

        $sql = "SELECT $select
                FROM bridge_characters_misc_systems bcms
                INNER JOIN fact_characters p ON p.id = bcms.character_id
                $joins
                WHERE bcms.misc_system_id = ?
                  $activeSql
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

function hg_systems_fetch_form(mysqli $link, int $formId)
{
    if ($formId <= 0) return null;

    $hasBreedId = hg_systems_column_exists($link, 'dim_forms', 'breed_id');
    $hasRace = hg_systems_column_exists($link, 'dim_forms', 'race');

    $select = "f.*, COALESCE(NULLIF(ds.name, ''), '') AS system_name_resolved";
    $joins = 'LEFT JOIN dim_systems ds ON ds.id = f.system_id';

    if ($hasBreedId) {
        $select .= ', ' . ($hasRace
            ? "COALESCE(NULLIF(db.name, ''), NULLIF(f.race, ''))"
            : "COALESCE(NULLIF(db.name, ''), '')") . ' AS breed_name_resolved';
        $joins .= ' LEFT JOIN dim_breeds db ON db.id = f.breed_id';
    } elseif ($hasRace) {
        $select .= ", COALESCE(NULLIF(db.name, ''), NULLIF(f.race, '')) AS breed_name_resolved";
        $joins .= ' LEFT JOIN dim_breeds db ON db.system_id = f.system_id AND db.name = f.race';
    } else {
        $select .= ", '' AS breed_name_resolved";
    }

    $stmt = $link->prepare("SELECT $select FROM dim_forms f $joins WHERE f.id = ? LIMIT 1");
    if (!$stmt) return false;

    $stmt->bind_param('i', $formId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function hg_systems_fetch_form_maneuvers(mysqli $link, int $systemId, string $formName)
{
    if ($systemId <= 0 || $formName === '') return [];

    $likeForm = '%' . $formName . '%';
    $stmt = $link->prepare("SELECT id, pretty_id, name, image_url FROM fact_combat_maneuvers WHERE system_id = ? AND (user LIKE ? OR user LIKE '%Todas%') ORDER BY name ASC");
    if (!$stmt) return false;

    $stmt->bind_param('is', $systemId, $likeForm);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}
