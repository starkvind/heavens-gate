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


