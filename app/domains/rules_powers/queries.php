<?php

if (!function_exists('hg_rules_powers_allowed_table')) {
    function hg_rules_powers_allowed_table(string $table): bool
    {
        return in_array($table, [
            'dim_traits', 'dim_character_conditions', 'fact_actions', 'dim_merits_flaws',
            'fact_combat_maneuvers', 'dim_archetypes', 'fact_gifts', 'fact_rites',
            'dim_totems', 'fact_discipline_powers', 'dim_systems', 'dim_gift_types',
            'dim_rite_types', 'dim_totem_types', 'dim_discipline_types', 'dim_bibliographies',
            'bridge_characters_traits', 'bridge_characters_conditions', 'bridge_characters_merits_flaws',
            'bridge_characters_powers', 'fact_characters', 'dim_character_status',
            'dim_groups', 'dim_organizations'
        ], true);
    }
}

if (!function_exists('hg_rules_powers_table_exists')) {
    function hg_rules_powers_table_exists(mysqli $link, string $table): bool
    {
        static $cache = [];
        if (!hg_rules_powers_allowed_table($table)) return false;
        if (array_key_exists($table, $cache)) return $cache[$table];
        $stmt = mysqli_prepare($link, 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        if (!$stmt) return $cache[$table] = false;
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $cache[$table] = ((int)$count > 0);
    }
}

if (!function_exists('hg_rules_powers_column_exists')) {
    function hg_rules_powers_column_exists(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        if (!hg_rules_powers_allowed_table($table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) return false;
        $key = $table . ':' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];
        $stmt = mysqli_prepare($link, 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        if (!$stmt) return $cache[$key] = false;
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $cache[$key] = ((int)$count > 0);
    }
}

if (!function_exists('hg_rules_powers_normalize_excluded_ids')) {
    function hg_rules_powers_normalize_excluded_ids($value): array
    {
        $ids = [];
        foreach (preg_split('/\s*,\s*/', trim((string)$value)) as $part) {
            if ($part !== '' && preg_match('/^\d+$/', (string)$part)) {
                $id = (int)$part;
                if ($id > 0) $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}

if (!function_exists('hg_rules_powers_append_exclusions')) {
    function hg_rules_powers_append_exclusions(string &$sql, string &$types, array &$params, string $column, array $ids): void
    {
        if (!$ids) return;
        $sql .= ' AND ' . $column . ' NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $types .= str_repeat('i', count($ids));
        foreach ($ids as $id) $params[] = $id;
    }
}

if (!function_exists('hg_rules_powers_lookup')) {
    function hg_rules_powers_lookup(mysqli $link, string $table, int $id, string $column = 'name'): string
    {
        static $cache = [];
        $key = $table . ':' . $column . ':' . $id;
        if (array_key_exists($key, $cache)) return $cache[$key];
        if ($id <= 0 || !hg_rules_powers_table_exists($link, $table) || !hg_rules_powers_column_exists($link, $table, $column)) {
            return $cache[$key] = '';
        }
        $stmt = mysqli_prepare($link, "SELECT `{$column}` FROM `{$table}` WHERE id = ? LIMIT 1");
        if (!$stmt) return $cache[$key] = '';
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $cache[$key] = trim((string)($row[$column] ?? ''));
    }
}

if (!function_exists('hg_rules_powers_resolve_fallback')) {
    function hg_rules_powers_resolve_fallback(mysqli $link, string $table, string $raw): int
    {
        if (!hg_rules_powers_table_exists($link, $table)) return 0;
        $prettyExpr = hg_rules_powers_column_exists($link, $table, 'pretty_id') ? 'pretty_id' : "'' AS pretty_id";
        $result = mysqli_query($link, "SELECT id, name, {$prettyExpr} FROM `{$table}`");
        if (!$result) return 0;
        while ($row = mysqli_fetch_assoc($result)) {
            if ((string)($row['pretty_id'] ?? '') === $raw
                || (function_exists('slugify_pretty_id') && slugify_pretty_id((string)($row['name'] ?? '')) === $raw)) {
                $id = (int)$row['id'];
                mysqli_free_result($result);
                return $id;
            }
        }
        mysqli_free_result($result);
        return 0;
    }
}

if (!function_exists('hg_rules_powers_fetch_owner_rows')) {
    function hg_rules_powers_fetch_owner_rows(mysqli $link, array $owner, int $id, $excludedChronicles = '2,7'): ?array
    {
        $table = (string)($owner['table'] ?? '');
        $where = (string)($owner['where'] ?? '');
        if ($table === '' || $where === '' || !hg_rules_powers_table_exists($link, $table) || !hg_rules_powers_table_exists($link, 'fact_characters')) return [];
        $kindSql = function_exists('hg_character_kind_select') ? hg_character_kind_select($link, 'c') : "''";
        $sql = "SELECT DISTINCT c.id, c.name, c.alias, c.image_url, c.gender,
                       COALESCE(s.label, '') AS status, c.status_id, {$kindSql} AS character_kind
                FROM `{$table}` b
                JOIN fact_characters c ON c.id = b.character_id
                LEFT JOIN dim_character_status s ON s.id = c.status_id
                WHERE {$where}";
        $types = 'i';
        $params = [$id];
        hg_rules_powers_append_exclusions($sql, $types, $params, 'c.chronicle_id', hg_rules_powers_normalize_excluded_ids($excludedChronicles));
        $sql .= ' ORDER BY c.name';
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); return null; }
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) { mysqli_stmt_close($stmt); return null; }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_rules_powers_fetch_special_owners')) {
    function hg_rules_powers_fetch_special_owners(mysqli $link, string $key, int $id, $excludedChronicles = '2,7'): ?array
    {
        if (!hg_rules_powers_table_exists($link, 'fact_characters')) return [];
        $sql = '';
        $types = '';
        $params = [];
        if ($key === 'traits' && hg_rules_powers_table_exists($link, 'bridge_characters_traits')) {
            $sql = "SELECT c.id, c.name, c.alias, c.image_url, c.gender, COALESCE(s.label, '') AS status, b.value AS trait_value
                    FROM bridge_characters_traits b
                    JOIN fact_characters c ON c.id = b.character_id
                    LEFT JOIN dim_character_status s ON s.id = c.status_id
                    WHERE b.trait_id = ? AND b.value >= 1";
            $types = 'i'; $params = [$id];
            hg_rules_powers_append_exclusions($sql, $types, $params, 'c.chronicle_id', hg_rules_powers_normalize_excluded_ids($excludedChronicles));
            $sql .= ' ORDER BY b.value ASC, c.name ASC';
        } elseif ($key === 'archetypes'
            && hg_rules_powers_column_exists($link, 'fact_characters', 'nature_id')
            && hg_rules_powers_column_exists($link, 'fact_characters', 'demeanor_id')) {
            $sql = "SELECT c.id, c.name, c.alias, c.image_url, c.gender, COALESCE(s.label, '') AS status
                    FROM fact_characters c
                    LEFT JOIN dim_character_status s ON s.id = c.status_id
                    WHERE (c.nature_id = ? OR c.demeanor_id = ?)";
            $types = 'ii'; $params = [$id, $id];
            hg_rules_powers_append_exclusions($sql, $types, $params, 'c.chronicle_id', hg_rules_powers_normalize_excluded_ids($excludedChronicles));
            $sql .= ' ORDER BY c.name';
        } elseif ($key === 'totems' && hg_rules_powers_column_exists($link, 'fact_characters', 'totem_id')) {
            $sql = "SELECT c.id, c.name, c.alias, c.image_url, c.gender, COALESCE(s.label, '') AS status
                    FROM fact_characters c
                    LEFT JOIN dim_character_status s ON s.id = c.status_id
                    WHERE c.totem_id = ?";
            $types = 'i'; $params = [$id];
            hg_rules_powers_append_exclusions($sql, $types, $params, 'c.chronicle_id', hg_rules_powers_normalize_excluded_ids($excludedChronicles));
            $sql .= ' ORDER BY c.name';
        }
        if ($sql === '') return [];
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); return null; }
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) { mysqli_stmt_close($stmt); return null; }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_rules_powers_fetch_totem_links')) {
    function hg_rules_powers_fetch_totem_links(mysqli $link, int $id): ?array
    {
        $links = [];
        foreach ([['dim_groups', 'Grupo'], ['dim_organizations', 'Organizacion']] as [$table, $kind]) {
            if (!hg_rules_powers_table_exists($link, $table) || !hg_rules_powers_column_exists($link, $table, 'totem_id')) continue;
            $stmt = mysqli_prepare($link, "SELECT id, name FROM `{$table}` WHERE totem_id = ? ORDER BY name");
            if (!$stmt) return null;
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); return null; }
            $result = mysqli_stmt_get_result($stmt);
            if (!$result) { mysqli_stmt_close($stmt); return null; }
            while ($row = mysqli_fetch_assoc($result)) {
                $row['kind'] = $kind;
                $row['table'] = $table;
                $links[] = $row;
            }
            mysqli_free_result($result);
            mysqli_stmt_close($stmt);
        }
        return $links;
    }
}

if (!function_exists('hg_rules_powers_fetch_list')) {
    function hg_rules_powers_fetch_list(mysqli $link, string $table, string $order, string $typeColumn = '', int $typeId = 0): ?array
    {
        if (!hg_rules_powers_table_exists($link, $table)) return null;
        $allowedOrders = [
            'name ASC', 'category ASC, name ASC', 'system_name ASC, name ASC',
            'rank ASC, name ASC', 'level ASC, name ASC', 'cost ASC, name ASC',
            'disc ASC, level ASC, name ASC'
        ];
        if (!in_array($order, $allowedOrders, true)) $order = 'name ASC';
        $sql = "SELECT * FROM `{$table}`";
        $types = '';
        $params = [];
        if ($typeColumn !== '' && $typeId > 0 && preg_match('/^[A-Za-z0-9_]+$/', $typeColumn)
            && hg_rules_powers_column_exists($link, $table, $typeColumn)) {
            $sql .= " WHERE `{$typeColumn}` = ?";
            $types = 'i';
            $params = [$typeId];
        }
        $sql .= ' ORDER BY ' . $order;
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); return null; }
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) { mysqli_stmt_close($stmt); return null; }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_rules_powers_fetch_one')) {
    function hg_rules_powers_fetch_one(mysqli $link, string $table, int $id): ?array
    {
        if ($id <= 0 || !hg_rules_powers_table_exists($link, $table)) return null;
        $stmt = mysqli_prepare($link, "SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); return null; }
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? (mysqli_fetch_assoc($result) ?: null) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row;
    }
}
