<?php

require_once __DIR__ . '/../../helpers/character_avatar.php';

function hg_powers_normalize_int_csv($csv): string
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

function hg_powers_column_exists(mysqli $link, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . ':' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];

    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') return $cache[$key] = false;

    $rs = $link->query("SHOW COLUMNS FROM `$table` LIKE '" . $link->real_escape_string($column) . "'");
    if (!$rs) return $cache[$key] = false;

    $exists = $rs->num_rows > 0;
    $rs->free();
    return $cache[$key] = $exists;
}

function hg_powers_type_definition(string $kind): ?array
{
    $map = [
        'gifts' => ['table' => 'dim_gift_types', 'order' => 'sort_order, id', 'determinant' => true],
        'rites' => ['table' => 'dim_rite_types', 'order' => 'sort_order, id', 'determinant' => true],
        'totems' => ['table' => 'dim_totem_types', 'order' => 'sort_order, id', 'determinant' => true],
        'disciplines' => ['table' => 'dim_discipline_types', 'order' => 'id', 'determinant' => false],
    ];
    return $map[$kind] ?? null;
}

function hg_powers_fetch_types(mysqli $link, string $kind)
{
    $def = hg_powers_type_definition($kind);
    if (!$def) return [];

    $select = $def['determinant'] ? 'id, name, determinant, description' : "id, name, '' AS determinant, description";
    $rs = $link->query("SELECT $select FROM `{$def['table']}` ORDER BY {$def['order']}");
    if (!$rs) return false;

    $rows = [];
    while ($row = $rs->fetch_assoc()) $rows[] = $row;
    $rs->free();
    return $rows;
}

function hg_powers_fetch_type(mysqli $link, string $kind, int $typeId)
{
    $def = hg_powers_type_definition($kind);
    if (!$def || $typeId <= 0) return null;

    $select = $def['determinant'] ? 'id, name, determinant, description' : "id, name, '' AS determinant, description";
    $stmt = $link->prepare("SELECT $select FROM `{$def['table']}` WHERE id = ? LIMIT 1");
    if (!$stmt) return false;

    $stmt->bind_param('i', $typeId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function hg_powers_fetch_gifts_for_type(mysqli $link, int $typeId)
{
    if ($typeId <= 0) return [];
    $stmt = $link->prepare('SELECT id, pretty_id, name, gift_group, rank FROM fact_gifts WHERE kind = ? ORDER BY gift_group, rank, name');
    if (!$stmt) return false;
    $stmt->bind_param('i', $typeId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_powers_fetch_rites_for_type(mysqli $link, int $typeId)
{
    if ($typeId <= 0) return [];
    $stmt = $link->prepare('SELECT id, pretty_id, name, level FROM fact_rites WHERE kind = ? ORDER BY level, name');
    if (!$stmt) return false;
    $stmt->bind_param('i', $typeId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_powers_fetch_totems_for_type(mysqli $link, int $typeId)
{
    if ($typeId <= 0) return [];
    $sql = "SELECT t.id, t.pretty_id, t.name, t.cost, t.image_url, COALESCE(b.name, '') AS origin
            FROM dim_totems t
            LEFT JOIN dim_bibliographies b ON b.id = t.bibliography_id
            WHERE t.totem_type_id = ?
            ORDER BY b.name ASC, t.name ASC";
    $stmt = $link->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $typeId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_powers_fetch_disciplines_for_type(mysqli $link, int $typeId)
{
    if ($typeId <= 0) return [];
    $stmt = $link->prepare('SELECT id, pretty_id, name, level FROM fact_discipline_powers WHERE disc = ? ORDER BY level, name');
    if (!$stmt) return false;
    $stmt->bind_param('i', $typeId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_powers_fetch_gift(mysqli $link, int $giftId)
{
    if ($giftId <= 0) return null;
    $rulesCol = hg_powers_column_exists($link, 'fact_gifts', 'mechanics_text') ? 'mechanics_text' : 'system_name';
    $legacySystemCol = hg_powers_column_exists($link, 'fact_gifts', 'shifter_system_name') ? 'shifter_system_name' : 'system_name';
    $sql = "SELECT g.*, s.name AS resolved_system_name, gt.name AS type_name, b.name AS origin_name,
                   g.`$rulesCol` AS mechanics_resolved, g.`$legacySystemCol` AS legacy_system_name
            FROM fact_gifts g
            LEFT JOIN dim_systems s ON s.id = g.system_id
            LEFT JOIN dim_gift_types gt ON gt.id = g.kind
            LEFT JOIN dim_bibliographies b ON b.id = g.bibliography_id
            WHERE g.id = ? LIMIT 1";
    $stmt = $link->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $giftId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function hg_powers_fetch_rite(mysqli $link, int $riteId)
{
    if ($riteId <= 0) return null;
    $sql = "SELECT r.*, s.name AS resolved_system_name, rt.name AS type_name, b.name AS origin_name
            FROM fact_rites r
            LEFT JOIN dim_systems s ON s.id = r.system_id
            LEFT JOIN dim_rite_types rt ON rt.id = r.kind
            LEFT JOIN dim_bibliographies b ON b.id = r.bibliography_id
            WHERE r.id = ? LIMIT 1";
    $stmt = $link->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $riteId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function hg_powers_fetch_totem(mysqli $link, int $totemId)
{
    if ($totemId <= 0) return null;
    $sql = "SELECT t.*, tt.name AS type_name, b.name AS origin_name
            FROM dim_totems t
            LEFT JOIN dim_totem_types tt ON tt.id = t.totem_type_id
            LEFT JOIN dim_bibliographies b ON b.id = t.bibliography_id
            WHERE t.id = ? LIMIT 1";
    $stmt = $link->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $totemId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function hg_powers_fetch_discipline(mysqli $link, int $powerId)
{
    if ($powerId <= 0) return null;
    $sql = "SELECT d.*, dt.name AS type_name, b.name AS origin_name
            FROM fact_discipline_powers d
            LEFT JOIN dim_discipline_types dt ON dt.id = d.disc
            LEFT JOIN dim_bibliographies b ON b.id = d.bibliography_id
            WHERE d.id = ? LIMIT 1";
    $stmt = $link->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $powerId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function hg_powers_fetch_bridge_owners(mysqli $link, string $powerKind, int $powerId, $excludedChronicles = '')
{
    if ($powerId <= 0 || !in_array($powerKind, ['dones', 'rituales'], true)) return [];

    $excluded = hg_powers_normalize_int_csv($excludedChronicles);
    $chronicleSql = $excluded !== '' ? "AND c.chronicle_id NOT IN ($excluded)" : '';
    $kindSql = hg_character_kind_select($link, 'c');
    $sql = "SELECT DISTINCT c.id, c.name AS nombre, c.alias, c.image_url, c.gender,
                   COALESCE(dcs.label, '') AS status, c.status_id, {$kindSql} AS character_kind
            FROM bridge_characters_powers b
            JOIN fact_characters c ON c.id = b.character_id
            LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id
            WHERE b.power_kind = ? AND b.power_id = ? $chronicleSql
            ORDER BY c.name";
    $stmt = $link->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('si', $powerKind, $powerId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_powers_fetch_totem_character_owners(mysqli $link, int $totemId, $excludedChronicles = '')
{
    if ($totemId <= 0) return [];
    $excluded = hg_powers_normalize_int_csv($excludedChronicles);
    $chronicleSql = $excluded !== '' ? "AND c.chronicle_id NOT IN ($excluded)" : '';
    $kindSql = hg_character_kind_select($link, 'c');
    $sql = "SELECT c.id, c.name AS nombre, c.alias, c.image_url, c.gender,
                   COALESCE(dcs.label, '') AS status, c.status_id, {$kindSql} AS character_kind
            FROM fact_characters c
            LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id
            WHERE c.totem_id = ? $chronicleSql
            ORDER BY c.name";
    $stmt = $link->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param('i', $totemId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function hg_powers_fetch_totem_links(mysqli $link, int $totemId, string $table)
{
    if ($totemId <= 0 || !in_array($table, ['dim_groups', 'dim_organizations'], true)) return [];
    $stmt = $link->prepare("SELECT id, name FROM `$table` WHERE totem_id = ? ORDER BY name");
    if (!$stmt) return false;
    $stmt->bind_param('i', $totemId);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}
