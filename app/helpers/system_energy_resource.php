<?php

if (!function_exists('hg_ser_cache')) {
    function hg_ser_cache(): array
    {
        if (!isset($GLOBALS['hg_ser_cache']) || !is_array($GLOBALS['hg_ser_cache'])) {
            $GLOBALS['hg_ser_cache'] = [];
        }
        return $GLOBALS['hg_ser_cache'];
    }
}

if (!function_exists('hg_ser_table_exists')) {
    function hg_ser_table_exists(mysqli $link, string $table): bool
    {
        $table = trim($table);
        if ($table === '') return false;
        $cache = &$GLOBALS['hg_ser_cache'];
        if (!isset($cache) || !is_array($cache)) $cache = [];
        $key = 't:' . $table;
        if (isset($cache[$key])) return (bool)$cache[$key];

        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?")) {
            $st->bind_param('s', $table);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }

        $cache[$key] = $ok;
        return $ok;
    }
}

if (!function_exists('hg_ser_column_exists')) {
    function hg_ser_column_exists(mysqli $link, string $table, string $column): bool
    {
        $cache = &$GLOBALS['hg_ser_cache'];
        if (!isset($cache) || !is_array($cache)) $cache = [];
        $key = 'c:' . $table . ':' . $column;
        if (isset($cache[$key])) return (bool)$cache[$key];

        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
            $st->bind_param('ss', $table, $column);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }

        $cache[$key] = $ok;
        return $ok;
    }
}

if (!function_exists('hg_ser_index_exists')) {
    function hg_ser_index_exists(mysqli $link, string $table, string $index): bool
    {
        $cache = &$GLOBALS['hg_ser_cache'];
        if (!isset($cache) || !is_array($cache)) $cache = [];
        $key = 'i:' . $table . ':' . $index;
        if (isset($cache[$key])) return (bool)$cache[$key];

        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?")) {
            $st->bind_param('ss', $table, $index);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }

        $cache[$key] = $ok;
        return $ok;
    }
}

if (!function_exists('hg_ser_constraint_exists')) {
    function hg_ser_constraint_exists(mysqli $link, string $table, string $constraint): bool
    {
        $cache = &$GLOBALS['hg_ser_cache'];
        if (!isset($cache) || !is_array($cache)) $cache = [];
        $key = 'fk:' . $table . ':' . $constraint;
        if (isset($cache[$key])) return (bool)$cache[$key];

        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?")) {
            $st->bind_param('ss', $table, $constraint);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }

        $cache[$key] = $ok;
        return $ok;
    }
}

if (!function_exists('hg_ser_energy_tables')) {
    function hg_ser_energy_tables(): array
    {
        return [
            'dim_breeds' => [
                'default_label' => 'Gnosis',
                'legacy_fk' => 'energy_resource_id',
                'config_column' => 'energy_resources_configured',
                'legacy_name_column' => '',
                'legacy_value_column' => 'energy',
                'bridge_table' => 'bridge_breeds_energy_resources',
                'detail_fk' => 'breed_id',
                'index_name' => 'idx_dim_breeds_energy_resource_id',
                'constraint_name' => 'fk_dim_breeds_energy_resource',
                'bridge_constraint_resource' => 'fk_brer_resource',
                'bridge_constraint_detail' => 'fk_brer_breed',
            ],
            'dim_auspices' => [
                'default_label' => 'Rabia',
                'legacy_fk' => 'energy_resource_id',
                'config_column' => 'energy_resources_configured',
                'legacy_name_column' => '',
                'legacy_value_column' => 'energy',
                'bridge_table' => 'bridge_auspices_energy_resources',
                'detail_fk' => 'auspice_id',
                'index_name' => 'idx_dim_auspices_energy_resource_id',
                'constraint_name' => 'fk_dim_auspices_energy_resource',
                'bridge_constraint_resource' => 'fk_baer_resource',
                'bridge_constraint_detail' => 'fk_baer_auspice',
            ],
            'dim_tribes' => [
                'default_label' => 'Fuerza de Voluntad',
                'legacy_fk' => 'energy_resource_id',
                'config_column' => 'energy_resources_configured',
                'legacy_name_column' => '',
                'legacy_value_column' => 'energy',
                'bridge_table' => 'bridge_tribes_energy_resources',
                'detail_fk' => 'tribe_id',
                'index_name' => 'idx_dim_tribes_energy_resource_id',
                'constraint_name' => 'fk_dim_tribes_energy_resource',
                'bridge_constraint_resource' => 'fk_bter_resource',
                'bridge_constraint_detail' => 'fk_bter_tribe',
            ],
            'fact_misc_systems' => [
                'default_label' => '',
                'legacy_fk' => '',
                'config_column' => 'energy_resources_configured',
                'legacy_name_column' => 'energy_name',
                'legacy_value_column' => 'energy_value',
                'bridge_table' => 'bridge_misc_systems_energy_resources',
                'detail_fk' => 'misc_system_id',
                'index_name' => '',
                'constraint_name' => '',
                'bridge_constraint_resource' => 'fk_bmser_resource',
                'bridge_constraint_detail' => 'fk_bmser_misc_system',
            ],
        ];
    }
}

if (!function_exists('hg_ser_supports_table')) {
    function hg_ser_supports_table(string $table): bool
    {
        $tables = hg_ser_energy_tables();
        return isset($tables[$table]);
    }
}

if (!function_exists('hg_ser_energy_bridge_meta')) {
    function hg_ser_energy_bridge_meta(string $table): array
    {
        $tables = hg_ser_energy_tables();
        return $tables[$table] ?? [];
    }
}

if (!function_exists('hg_ser_has_energy_resource_column')) {
    function hg_ser_has_energy_resource_column(mysqli $link, string $table): bool
    {
        $meta = hg_ser_energy_bridge_meta($table);
        $legacyFk = (string)($meta['legacy_fk'] ?? 'energy_resource_id');
        return !empty($meta) && $legacyFk !== '' && hg_ser_column_exists($link, $table, $legacyFk);
    }
}

if (!function_exists('hg_ser_has_legacy_energy_value_column')) {
    function hg_ser_has_legacy_energy_value_column(mysqli $link, string $table): bool
    {
        $meta = hg_ser_energy_bridge_meta($table);
        $legacyValueColumn = (string)($meta['legacy_value_column'] ?? 'energy');
        return hg_ser_supports_table($table) && $legacyValueColumn !== '' && hg_ser_column_exists($link, $table, $legacyValueColumn);
    }
}

if (!function_exists('hg_ser_has_legacy_energy_name_column')) {
    function hg_ser_has_legacy_energy_name_column(mysqli $link, string $table): bool
    {
        $meta = hg_ser_energy_bridge_meta($table);
        $legacyNameColumn = (string)($meta['legacy_name_column'] ?? '');
        return hg_ser_supports_table($table) && $legacyNameColumn !== '' && hg_ser_column_exists($link, $table, $legacyNameColumn);
    }
}

if (!function_exists('hg_ser_has_energy_bridge_table')) {
    function hg_ser_has_energy_bridge_table(mysqli $link, string $table): bool
    {
        $meta = hg_ser_energy_bridge_meta($table);
        $bridgeTable = (string)($meta['bridge_table'] ?? '');
        $detailFk = (string)($meta['detail_fk'] ?? '');
        if ($bridgeTable === '' || $detailFk === '') return false;
        return hg_ser_table_exists($link, $bridgeTable)
            && hg_ser_column_exists($link, $bridgeTable, $detailFk)
            && hg_ser_column_exists($link, $bridgeTable, 'resource_id')
            && hg_ser_column_exists($link, $bridgeTable, 'energy_value');
    }
}

if (!function_exists('hg_ser_has_energy_config_column')) {
    function hg_ser_has_energy_config_column(mysqli $link, string $table): bool
    {
        $meta = hg_ser_energy_bridge_meta($table);
        $configColumn = (string)($meta['config_column'] ?? '');
        return $configColumn !== '' && hg_ser_column_exists($link, $table, $configColumn);
    }
}

if (!function_exists('hg_ser_default_energy_label')) {
    function hg_ser_default_energy_label(string $table, string $systemName = ''): string
    {
        $meta = hg_ser_energy_bridge_meta($table);
        return (string)($meta['default_label'] ?? '');
    }
}

if (!function_exists('hg_ser_energy_label_from_row')) {
    function hg_ser_energy_label_from_row(string $table, array $row, string $fallbackSystemName = ''): string
    {
        $resourceName = trim((string)($row['energy_resource_name'] ?? ''));
        if ($resourceName !== '') return $resourceName;
        return hg_ser_default_energy_label($table, (string)($row['system_name'] ?? $fallbackSystemName));
    }
}

if (!function_exists('hg_ser_energy_sql_parts')) {
    function hg_ser_energy_sql_parts(mysqli $link, string $table, string $alias = 't', string $resourceAlias = 'er'): array
    {
        if (!hg_ser_has_energy_resource_column($link, $table)) {
            return ['select' => '', 'join' => ''];
        }

        $meta = hg_ser_energy_bridge_meta($table);
        $legacyFk = (string)($meta['legacy_fk'] ?? 'energy_resource_id');
        return [
            'select' => ", COALESCE($resourceAlias.id, 0) AS energy_resource_id, COALESCE($resourceAlias.name, '') AS energy_resource_name, COALESCE($resourceAlias.pretty_id, '') AS energy_resource_pretty_id",
            'join' => " LEFT JOIN dim_systems_resources $resourceAlias ON $resourceAlias.id = $alias.`$legacyFk`",
        ];
    }
}

if (!function_exists('hg_ser_energy_value_sql_expr')) {
    function hg_ser_energy_value_sql_expr(mysqli $link, string $table, string $alias = 't'): string
    {
        $meta = hg_ser_energy_bridge_meta($table);
        $legacyValueColumn = (string)($meta['legacy_value_column'] ?? 'energy');
        return (hg_ser_has_legacy_energy_value_column($link, $table) && $legacyValueColumn !== '')
            ? "COALESCE($alias.`$legacyValueColumn`, 0)"
            : "0";
    }
}

if (!function_exists('hg_ser_fetch_state_resources_all')) {
    function hg_ser_fetch_state_resources_all(mysqli $link): array
    {
        $rows = [];
        if (!hg_ser_table_exists($link, 'dim_systems_resources')) return $rows;

        $sql = "
            SELECT id, pretty_id, name, kind, sort_order
            FROM dim_systems_resources
            WHERE kind = 'estado'
            ORDER BY sort_order ASC, name ASC
        ";
        if ($rs = $link->query($sql)) {
            while ($row = $rs->fetch_assoc()) {
                $rows[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'pretty_id' => (string)($row['pretty_id'] ?? ''),
                    'name' => (string)($row['name'] ?? ''),
                    'kind' => (string)($row['kind'] ?? ''),
                    'sort_order' => (int)($row['sort_order'] ?? 0),
                ];
            }
            $rs->close();
        }

        return $rows;
    }
}

if (!function_exists('hg_ser_fetch_state_resources_by_system')) {
    function hg_ser_fetch_state_resources_by_system(mysqli $link): array
    {
        $map = [];
        if (!hg_ser_table_exists($link, 'dim_systems_resources') || !hg_ser_table_exists($link, 'bridge_systems_resources_to_system')) {
            return $map;
        }

        $hasSort = hg_ser_column_exists($link, 'bridge_systems_resources_to_system', 'sort_order');
        $hasActive = hg_ser_column_exists($link, 'bridge_systems_resources_to_system', 'is_active');
        $sortSql = $hasSort ? 'b.sort_order ASC,' : '';
        $activeSql = $hasActive ? 'AND (b.is_active = 1 OR b.is_active IS NULL)' : '';

        $sql = "
            SELECT
                b.system_id,
                r.id,
                r.pretty_id,
                r.name,
                r.kind,
                r.sort_order
            FROM bridge_systems_resources_to_system b
            INNER JOIN dim_systems_resources r ON r.id = b.resource_id
            WHERE r.kind = 'estado'
              $activeSql
            ORDER BY b.system_id ASC, $sortSql r.sort_order ASC, r.name ASC
        ";

        if ($rs = $link->query($sql)) {
            while ($row = $rs->fetch_assoc()) {
                $systemId = (int)($row['system_id'] ?? 0);
                if ($systemId <= 0) continue;
                if (!isset($map[$systemId])) $map[$systemId] = [];
                $map[$systemId][] = [
                    'id' => (int)($row['id'] ?? 0),
                    'pretty_id' => (string)($row['pretty_id'] ?? ''),
                    'name' => (string)($row['name'] ?? ''),
                    'kind' => (string)($row['kind'] ?? ''),
                    'sort_order' => (int)($row['sort_order'] ?? 0),
                ];
            }
            $rs->close();
        }

        return $map;
    }
}

if (!function_exists('hg_ser_resources_for_system')) {
    function hg_ser_resources_for_system(array $resourcesBySystem, array $allResources, int $systemId, bool $allowAllStateResources = false): array
    {
        if ($allowAllStateResources) {
            return array_values($allResources);
        }
        if ($systemId > 0) {
            $rows = $resourcesBySystem[$systemId] ?? $resourcesBySystem[(string)$systemId] ?? [];
            if (!empty($rows)) return array_values($rows);
        }
        return array_values($allResources);
    }
}

if (!function_exists('hg_ser_resource_allowed_for_system')) {
    function hg_ser_resource_allowed_for_system(int $resourceId, int $systemId, array $resourcesBySystem, array $allResources, bool $allowAllStateResources = false): bool
    {
        if ($resourceId <= 0) return true;
        $allowed = hg_ser_resources_for_system($resourcesBySystem, $allResources, $systemId, $allowAllStateResources);
        foreach ($allowed as $row) {
            if ((int)($row['id'] ?? 0) === $resourceId) return true;
        }
        return false;
    }
}

if (!function_exists('hg_ser_fetch_bridge_energy_rows_for_ids')) {
    function hg_ser_fetch_bridge_energy_rows_for_ids(mysqli $link, string $table, array $detailIds): array
    {
        $result = [];
        $meta = hg_ser_energy_bridge_meta($table);
        $bridgeTable = (string)($meta['bridge_table'] ?? '');
        $detailFk = (string)($meta['detail_fk'] ?? '');
        if ($bridgeTable === '' || $detailFk === '' || empty($detailIds) || !hg_ser_has_energy_bridge_table($link, $table)) {
            return $result;
        }

        $ids = [];
        foreach ($detailIds as $detailId) {
            $detailId = (int)$detailId;
            if ($detailId > 0) $ids[$detailId] = $detailId;
        }
        if (empty($ids)) return $result;

        $hasActive = hg_ser_column_exists($link, $bridgeTable, 'is_active');
        $hasSort = hg_ser_column_exists($link, $bridgeTable, 'sort_order');
        $activeSql = $hasActive ? " AND (b.is_active = 1 OR b.is_active IS NULL)" : '';
        $sortSql = $hasSort ? 'COALESCE(b.sort_order, 0) ASC,' : '';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $sql = "
            SELECT
                b.id,
                b.`$detailFk` AS detail_id,
                b.resource_id,
                COALESCE(r.name, '') AS resource_name,
                COALESCE(r.pretty_id, '') AS resource_pretty_id,
                COALESCE(b.energy_value, 0) AS energy_value,
                " . ($hasSort ? "COALESCE(b.sort_order, 0)" : "0") . " AS sort_order,
                " . ($hasActive ? "COALESCE(b.is_active, 1)" : "1") . " AS is_active
            FROM `$bridgeTable` b
            INNER JOIN dim_systems_resources r ON r.id = b.resource_id
            WHERE b.`$detailFk` IN ($placeholders)
            $activeSql
            ORDER BY b.`$detailFk` ASC, $sortSql r.sort_order ASC, r.name ASC, b.id ASC
        ";

        if ($st = $link->prepare($sql)) {
            $bind = array_values($ids);
            $refs = [];
            foreach ($bind as $idx => $value) $refs[$idx] = &$bind[$idx];
            array_unshift($refs, $types);
            call_user_func_array([$st, 'bind_param'], $refs);
            $st->execute();
            $rs = $st->get_result();
            while ($rs && ($row = $rs->fetch_assoc())) {
                $detailId = (int)($row['detail_id'] ?? 0);
                if ($detailId <= 0) continue;
                if (!isset($result[$detailId])) $result[$detailId] = [];
                $result[$detailId][] = [
                    'bridge_id' => (int)($row['id'] ?? 0),
                    'resource_id' => (int)($row['resource_id'] ?? 0),
                    'resource_name' => (string)($row['resource_name'] ?? ''),
                    'resource_pretty_id' => (string)($row['resource_pretty_id'] ?? ''),
                    'energy_value' => (int)($row['energy_value'] ?? 0),
                    'sort_order' => (int)($row['sort_order'] ?? 0),
                    'is_active' => (int)($row['is_active'] ?? 1),
                ];
            }
            $st->close();
        }

        return $result;
    }
}

if (!function_exists('hg_ser_fetch_configured_map_for_ids')) {
    function hg_ser_fetch_configured_map_for_ids(mysqli $link, string $table, array $detailIds): array
    {
        $map = [];
        $meta = hg_ser_energy_bridge_meta($table);
        $configColumn = (string)($meta['config_column'] ?? '');
        if ($configColumn === '' || !hg_ser_has_energy_config_column($link, $table) || empty($detailIds)) {
            return $map;
        }

        $ids = [];
        foreach ($detailIds as $detailId) {
            $detailId = (int)$detailId;
            if ($detailId > 0) $ids[$detailId] = $detailId;
        }
        if (empty($ids)) return $map;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $sql = "SELECT id, COALESCE(`$configColumn`, 0) AS energy_resources_configured FROM `$table` WHERE id IN ($placeholders)";
        if ($st = $link->prepare($sql)) {
            $bind = array_values($ids);
            $refs = [];
            foreach ($bind as $idx => $value) $refs[$idx] = &$bind[$idx];
            array_unshift($refs, $types);
            call_user_func_array([$st, 'bind_param'], $refs);
            $st->execute();
            $rs = $st->get_result();
            while ($rs && ($row = $rs->fetch_assoc())) {
                $map[(int)($row['id'] ?? 0)] = ((int)($row['energy_resources_configured'] ?? 0) === 1);
            }
            $st->close();
        }

        return $map;
    }
}

if (!function_exists('hg_ser_is_energy_configured')) {
    function hg_ser_is_energy_configured(mysqli $link, string $table, int $detailId, array $row = []): bool
    {
        $meta = hg_ser_energy_bridge_meta($table);
        $configColumn = (string)($meta['config_column'] ?? '');
        if ($configColumn !== '' && array_key_exists($configColumn, $row)) {
            return ((int)($row[$configColumn] ?? 0) === 1);
        }
        if ($detailId <= 0) return false;
        $map = hg_ser_fetch_configured_map_for_ids($link, $table, [$detailId]);
        return !empty($map[$detailId]);
    }
}

if (!function_exists('hg_ser_legacy_energy_entries_from_row')) {
    function hg_ser_legacy_energy_entries_from_row(mysqli $link, string $table, array $row, string $fallbackSystemName = ''): array
    {
        $meta = hg_ser_energy_bridge_meta($table);
        $legacyValueColumn = (string)($meta['legacy_value_column'] ?? 'energy');
        $legacyNameColumn = (string)($meta['legacy_name_column'] ?? '');
        $legacyFk = (string)($meta['legacy_fk'] ?? 'energy_resource_id');

        $energy = (int)($row[$legacyValueColumn] ?? 0);
        if ($energy <= 0) return [];

        $label = $legacyNameColumn !== '' && trim((string)($row[$legacyNameColumn] ?? '')) !== ''
            ? trim((string)($row[$legacyNameColumn] ?? ''))
            : hg_ser_energy_label_from_row($table, $row, $fallbackSystemName);
        $resourceId = (int)($row['energy_resource_id'] ?? 0);
        $prettyId = (string)($row['energy_resource_pretty_id'] ?? '');

        if ($resourceId <= 0 && $legacyFk !== '' && hg_ser_has_energy_resource_column($link, $table)) {
            if ($legacyFk !== 'energy_resource_id' && isset($row[$legacyFk])) {
                $resourceId = (int)$row[$legacyFk];
            }
        }

        return [[
            'bridge_id' => 0,
            'resource_id' => $resourceId,
            'resource_name' => $label,
            'resource_pretty_id' => $prettyId,
            'energy_value' => $energy,
            'sort_order' => 0,
            'is_active' => 1,
            'is_legacy' => 1,
        ]];
    }
}

if (!function_exists('hg_ser_energy_entries_for_row')) {
    function hg_ser_energy_entries_for_row(mysqli $link, string $table, int $detailId, array $row = [], string $fallbackSystemName = ''): array
    {
        $bridgeRows = hg_ser_fetch_bridge_energy_rows_for_ids($link, $table, [$detailId]);
        $entries = $bridgeRows[$detailId] ?? [];
        if (!empty($entries)) return $entries;
        if (hg_ser_is_energy_configured($link, $table, $detailId, $row)) return [];
        return hg_ser_legacy_energy_entries_from_row($link, $table, $row, $fallbackSystemName);
    }
}

if (!function_exists('hg_ser_energy_entries_summary')) {
    function hg_ser_energy_entries_summary(array $entries): string
    {
        $parts = [];
        foreach ($entries as $entry) {
            $label = trim((string)($entry['resource_name'] ?? ''));
            $value = (int)($entry['energy_value'] ?? 0);
            if ($label === '' || $value <= 0) continue;
            $parts[] = $label . ' ' . $value;
        }
        return implode(', ', $parts);
    }
}

if (!function_exists('hg_ser_attach_energy_summary')) {
    function hg_ser_attach_energy_summary(mysqli $link, string $table, array $rows): array
    {
        if (empty($rows) || !hg_ser_supports_table($table)) return $rows;

        $ids = [];
        foreach ($rows as $row) {
            $rowId = (int)($row['id'] ?? 0);
            if ($rowId > 0) $ids[] = $rowId;
        }
        $bridgeMap = hg_ser_fetch_bridge_energy_rows_for_ids($link, $table, $ids);
        $configuredMap = hg_ser_fetch_configured_map_for_ids($link, $table, $ids);

        foreach ($rows as &$row) {
            $rowId = (int)($row['id'] ?? 0);
            $entries = $bridgeMap[$rowId] ?? [];
            $isConfigured = !empty($configuredMap[$rowId]) || hg_ser_is_energy_configured($link, $table, $rowId, $row);
            if (empty($entries) && !$isConfigured) {
                $entries = hg_ser_legacy_energy_entries_from_row($link, $table, $row, (string)($row['system_name'] ?? ''));
            }
            $summary = hg_ser_energy_entries_summary($entries);
            if ($summary === '' && $isConfigured) $summary = 'Sin recurso';
            $row['energy_resources_summary'] = $summary;
            $row['energy_resources_rows'] = $entries;
            $row['energy_resources_configured'] = $isConfigured ? 1 : 0;
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('hg_ser_normalize_posted_energy_assignments')) {
    function hg_ser_normalize_posted_energy_assignments($payload): array
    {
        $rows = [];
        if (!is_array($payload)) return $rows;

        foreach ($payload as $item) {
            if (!is_array($item)) continue;
            $resourceId = (int)($item['resource_id'] ?? 0);
            $energyValue = max(0, (int)($item['energy_value'] ?? 0));
            $sortOrder = (int)($item['sort_order'] ?? 0);
            $isActive = isset($item['is_active']) ? ((int)$item['is_active'] === 1 ? 1 : 0) : 1;
            if ($resourceId <= 0 || $energyValue <= 0) continue;

            $key = $resourceId;
            $rows[$key] = [
                'resource_id' => $resourceId,
                'energy_value' => $energyValue,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = ((int)$a['sort_order']) <=> ((int)$b['sort_order']);
            if ($cmp !== 0) return $cmp;
            return ((int)$a['resource_id']) <=> ((int)$b['resource_id']);
        });

        return array_values($rows);
    }
}

if (!function_exists('hg_ser_validate_energy_assignments')) {
    function hg_ser_validate_energy_assignments(array $assignments, int $systemId, array $resourcesBySystem, array $allResources, bool $allowAllStateResources = false): ?string
    {
        foreach ($assignments as $row) {
            $resourceId = (int)($row['resource_id'] ?? 0);
            $energyValue = (int)($row['energy_value'] ?? 0);
            if ($resourceId <= 0) return 'Hay una fila de recursos sin recurso seleccionado.';
            if ($energyValue <= 0) return 'Los valores de energía deben ser mayores que cero.';
            if (!hg_ser_resource_allowed_for_system($resourceId, $systemId, $resourcesBySystem, $allResources, $allowAllStateResources)) {
                return 'Uno de los recursos no pertenece al sistema indicado.';
            }
        }
        return null;
    }
}

if (!function_exists('hg_ser_save_energy_assignments')) {
    function hg_ser_save_energy_assignments(mysqli $link, string $table, int $detailId, array $assignments): array
    {
        $meta = hg_ser_energy_bridge_meta($table);
        $bridgeTable = (string)($meta['bridge_table'] ?? '');
        $detailFk = (string)($meta['detail_fk'] ?? '');
        if ($detailId <= 0 || $bridgeTable === '' || $detailFk === '' || !hg_ser_has_energy_bridge_table($link, $table)) {
            return ['ok' => false, 'message' => 'La tabla bridge de energía no está disponible.'];
        }

        $rows = hg_ser_normalize_posted_energy_assignments($assignments);
        $configColumn = (string)($meta['config_column'] ?? '');

        $delete = $link->prepare("DELETE FROM `$bridgeTable` WHERE `$detailFk` = ?");
        if (!$delete) {
            return ['ok' => false, 'message' => 'No se pudo preparar el borrado de recursos de energía.'];
        }
        $delete->bind_param('i', $detailId);
        if (!$delete->execute()) {
            $msg = $delete->error ?: 'Error al limpiar recursos de energía.';
            $delete->close();
            return ['ok' => false, 'message' => $msg];
        }
        $delete->close();

        if ($configColumn !== '' && hg_ser_has_energy_config_column($link, $table)) {
            $setConfigured = $link->prepare("UPDATE `$table` SET `$configColumn` = 1 WHERE id = ?");
            if (!$setConfigured) {
                return ['ok' => false, 'message' => 'No se pudo marcar la configuracion de recursos de energia.'];
            }
            $setConfigured->bind_param('i', $detailId);
            if (!$setConfigured->execute()) {
                $msg = $setConfigured->error ?: 'Error al marcar la configuracion de recursos de energia.';
                $setConfigured->close();
                return ['ok' => false, 'message' => $msg];
            }
            $setConfigured->close();
        }

        if (empty($rows)) {
            return ['ok' => true, 'message' => 'Sin recursos de energía adicionales.'];
        }

        $insertSql = "
            INSERT INTO `$bridgeTable` (`$detailFk`, resource_id, energy_value, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?)
        ";
        $insert = $link->prepare($insertSql);
        if (!$insert) {
            return ['ok' => false, 'message' => 'No se pudo preparar el guardado de recursos de energía.'];
        }

        foreach ($rows as $row) {
            $resourceId = (int)$row['resource_id'];
            $energyValue = (int)$row['energy_value'];
            $sortOrder = (int)$row['sort_order'];
            $isActive = (int)$row['is_active'];
            $insert->bind_param('iiiii', $detailId, $resourceId, $energyValue, $sortOrder, $isActive);
            if (!$insert->execute()) {
                $msg = $insert->error ?: 'Error al guardar recursos de energía.';
                $insert->close();
                return ['ok' => false, 'message' => $msg];
            }
        }
        $insert->close();

        return ['ok' => true, 'message' => 'Recursos de energía guardados.'];
    }
}

if (!function_exists('hg_ser_schema_status')) {
    function hg_ser_schema_status(mysqli $link): array
    {
        $status = [];
        foreach (hg_ser_energy_tables() as $table => $meta) {
            $bridgeTable = (string)($meta['bridge_table'] ?? '');
            $detailFk = (string)($meta['detail_fk'] ?? '');
            $status[$table] = [
                'table_exists' => hg_ser_table_exists($link, $table),
                'energy_resource_id' => hg_ser_has_energy_resource_column($link, $table),
                'config_column' => hg_ser_has_energy_config_column($link, $table),
                'legacy_value_column' => hg_ser_has_legacy_energy_value_column($link, $table),
                'bridge_table' => $bridgeTable !== '' && hg_ser_table_exists($link, $bridgeTable),
                'bridge_fk_detail' => ($bridgeTable !== '' && $detailFk !== '') ? hg_ser_column_exists($link, $bridgeTable, $detailFk) : false,
                'bridge_fk_resource' => ($bridgeTable !== '') ? hg_ser_column_exists($link, $bridgeTable, 'resource_id') : false,
                'bridge_energy_value' => ($bridgeTable !== '') ? hg_ser_column_exists($link, $bridgeTable, 'energy_value') : false,
            ];
        }
        $status['dim_systems_resources'] = ['table_exists' => hg_ser_table_exists($link, 'dim_systems_resources')];
        $status['bridge_systems_resources_to_system'] = ['table_exists' => hg_ser_table_exists($link, 'bridge_systems_resources_to_system')];
        return $status;
    }
}

if (!function_exists('hg_ser_legacy_status')) {
    function hg_ser_legacy_status(mysqli $link): array
    {
        $status = [];
        foreach (hg_ser_energy_tables() as $table => $meta) {
            $configColumn = (string)($meta['config_column'] ?? '');
            $legacyFk = (string)($meta['legacy_fk'] ?? 'energy_resource_id');
            $legacyNameColumn = (string)($meta['legacy_name_column'] ?? '');
            $legacyValueColumn = (string)($meta['legacy_value_column'] ?? 'energy');
            $hasConfig = ($configColumn !== '') && hg_ser_column_exists($link, $table, $configColumn);
            $hasEnergy = hg_ser_has_legacy_energy_value_column($link, $table);
            $hasEnergyName = $legacyNameColumn !== '' && hg_ser_has_legacy_energy_name_column($link, $table);
            $hasResource = ($legacyFk !== '') && hg_ser_column_exists($link, $table, $legacyFk);
            $pending = 0;

            $pendingWhere = hg_ser_legacy_pending_where($link, $table);
            if ($pendingWhere !== '') {
                if ($rs = $link->query("SELECT COUNT(*) AS c FROM `$table` WHERE $pendingWhere")) {
                    $row = $rs->fetch_assoc();
                    $pending = (int)($row['c'] ?? 0);
                    $rs->close();
                }
            }

            $status[$table] = [
                'has_energy' => $hasEnergy,
                'has_energy_name' => $hasEnergyName,
                'has_resource' => $hasResource,
                'has_config' => $hasConfig,
                'pending_count' => $pending,
                'can_retire' => $hasConfig && $pending === 0 && ($hasEnergy || $hasResource || $hasEnergyName),
            ];
        }
        return $status;
    }
}

if (!function_exists('hg_ser_legacy_pending_where')) {
    function hg_ser_legacy_pending_where(mysqli $link, string $table): string
    {
        $meta = hg_ser_energy_bridge_meta($table);
        if (empty($meta)) return '';

        $configColumn = (string)($meta['config_column'] ?? '');
        $legacyFk = (string)($meta['legacy_fk'] ?? 'energy_resource_id');
        $legacyNameColumn = (string)($meta['legacy_name_column'] ?? '');
        $legacyValueColumn = (string)($meta['legacy_value_column'] ?? 'energy');
        $hasConfig = ($configColumn !== '') && hg_ser_column_exists($link, $table, $configColumn);
        $hasEnergy = hg_ser_has_legacy_energy_value_column($link, $table);
        $hasEnergyName = $legacyNameColumn !== '' && hg_ser_has_legacy_energy_name_column($link, $table);
        $hasResource = ($legacyFk !== '') && hg_ser_column_exists($link, $table, $legacyFk);

        if (!$hasConfig || (!$hasEnergy && !$hasResource && !$hasEnergyName)) {
            return '';
        }

        $pendingWhere = "COALESCE(`$configColumn`, 0) = 0";
        if ($hasEnergyName && $hasEnergy && $hasResource) {
            $pendingWhere .= " AND (TRIM(COALESCE(`$legacyNameColumn`, '')) <> '' OR COALESCE(`$legacyValueColumn`, 0) > 0 OR COALESCE(`$legacyFk`, 0) > 0)";
        } elseif ($hasEnergyName && $hasEnergy) {
            $pendingWhere .= " AND (TRIM(COALESCE(`$legacyNameColumn`, '')) <> '' OR COALESCE(`$legacyValueColumn`, 0) > 0)";
        } elseif ($hasEnergy && $hasResource) {
            $pendingWhere .= " AND (COALESCE(`$legacyValueColumn`, 0) > 0 OR COALESCE(`$legacyFk`, 0) > 0)";
        } elseif ($hasEnergyName) {
            $pendingWhere .= " AND TRIM(COALESCE(`$legacyNameColumn`, '')) <> ''";
        } elseif ($hasEnergy) {
            $pendingWhere .= " AND COALESCE(`$legacyValueColumn`, 0) > 0";
        } elseif ($hasResource) {
            $pendingWhere .= " AND COALESCE(`$legacyFk`, 0) > 0";
        }

        return $pendingWhere;
    }
}

if (!function_exists('hg_ser_legacy_pending_rows')) {
    function hg_ser_legacy_pending_rows(mysqli $link, string $table, int $limit = 100): array
    {
        $rows = [];
        $pendingWhere = hg_ser_legacy_pending_where($link, $table);
        if ($pendingWhere === '') return $rows;

        $limit = max(1, min(500, $limit));
        $hasSystemId = hg_ser_column_exists($link, $table, 'system_id');
        $hasSystemName = hg_ser_column_exists($link, $table, 'system_name');

        $systemSelect = $hasSystemName
            ? "COALESCE(s.name, t.system_name, '')"
            : ($hasSystemId ? "COALESCE(s.name, '')" : "''");
        $systemJoin = $hasSystemId ? "LEFT JOIN dim_systems s ON s.id = t.system_id" : '';

        $sql = "
            SELECT
                t.id,
                COALESCE(t.name, '') AS name,
                $systemSelect AS system_name
            FROM `$table` t
            $systemJoin
            WHERE $pendingWhere
            ORDER BY system_name ASC, name ASC, t.id ASC
            LIMIT $limit
        ";

        if ($rs = $link->query($sql)) {
            while ($row = $rs->fetch_assoc()) {
                $rows[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'name' => (string)($row['name'] ?? ''),
                    'system_name' => (string)($row['system_name'] ?? ''),
                ];
            }
            $rs->close();
        }

        return $rows;
    }
}

if (!function_exists('hg_ser_retire_legacy_schema')) {
    function hg_ser_retire_legacy_schema(mysqli $link): array
    {
        $result = ['ok' => true, 'messages' => [], 'errors' => []];
        $legacyStatus = hg_ser_legacy_status($link);

        foreach (hg_ser_energy_tables() as $table => $meta) {
            $legacyFk = (string)($meta['legacy_fk'] ?? 'energy_resource_id');
            $legacyNameColumn = (string)($meta['legacy_name_column'] ?? '');
            $legacyValueColumn = (string)($meta['legacy_value_column'] ?? 'energy');
            $index = (string)($meta['index_name'] ?? '');
            $constraint = (string)($meta['constraint_name'] ?? '');
            $rowStatus = $legacyStatus[$table] ?? [];
            if (empty($rowStatus['can_retire'])) {
                if (!empty($rowStatus['has_energy']) || !empty($rowStatus['has_resource']) || !empty($rowStatus['has_energy_name'])) {
                    $result['ok'] = false;
                    $result['errors'][] = [
                        'table' => $table,
                        'error' => 'Todavía hay filas pendientes de migrar o falta la columna de configuración.',
                        'pending_count' => (int)($rowStatus['pending_count'] ?? 0),
                    ];
                }
                continue;
            }

            if (!empty($rowStatus['has_resource'])) {
                if ($constraint !== '' && hg_ser_constraint_exists($link, $table, $constraint)) {
                    $sql = "ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`";
                    if (!$link->query($sql)) {
                        $result['ok'] = false;
                        $result['errors'][] = ['table' => $table, 'error' => $link->error, 'sql' => $sql];
                        continue;
                    }
                    $GLOBALS['hg_ser_cache']['fk:' . $table . ':' . $constraint] = false;
                }
                if ($index !== '' && hg_ser_index_exists($link, $table, $index)) {
                    $sql = "ALTER TABLE `$table` DROP INDEX `$index`";
                    if (!$link->query($sql)) {
                        $result['ok'] = false;
                        $result['errors'][] = ['table' => $table, 'error' => $link->error, 'sql' => $sql];
                        continue;
                    }
                    $GLOBALS['hg_ser_cache']['i:' . $table . ':' . $index] = false;
                }
                $sql = "ALTER TABLE `$table` DROP COLUMN `$legacyFk`";
                if (!$link->query($sql)) {
                    $result['ok'] = false;
                    $result['errors'][] = ['table' => $table, 'error' => $link->error, 'sql' => $sql];
                    continue;
                }
                $GLOBALS['hg_ser_cache']['c:' . $table . ':' . $legacyFk] = false;
                $result['messages'][] = $table . ': columna ' . $legacyFk . ' eliminada.';
            }

            if (!empty($rowStatus['has_energy'])) {
                $sql = "ALTER TABLE `$table` DROP COLUMN `$legacyValueColumn`";
                if (!$link->query($sql)) {
                    $result['ok'] = false;
                    $result['errors'][] = ['table' => $table, 'error' => $link->error, 'sql' => $sql];
                    continue;
                }
                $GLOBALS['hg_ser_cache']['c:' . $table . ':' . $legacyValueColumn] = false;
                $result['messages'][] = $table . ': columna ' . $legacyValueColumn . ' eliminada.';
            }

            if ($legacyNameColumn !== '' && !empty($rowStatus['has_energy_name'])) {
                $sql = "ALTER TABLE `$table` DROP COLUMN `$legacyNameColumn`";
                if (!$link->query($sql)) {
                    $result['ok'] = false;
                    $result['errors'][] = ['table' => $table, 'error' => $link->error, 'sql' => $sql];
                    continue;
                }
                $GLOBALS['hg_ser_cache']['c:' . $table . ':' . $legacyNameColumn] = false;
                $result['messages'][] = $table . ': columna ' . $legacyNameColumn . ' eliminada.';
            }
        }

        return $result;
    }
}

if (!function_exists('hg_ser_ensure_energy_schema')) {
    function hg_ser_ensure_energy_schema(mysqli $link): array
    {
        $result = ['ok' => true, 'messages' => [], 'errors' => []];
        if (!hg_ser_table_exists($link, 'dim_systems_resources')) {
            $result['ok'] = false;
            $result['errors'][] = ['table' => 'dim_systems_resources', 'error' => 'Falta dim_systems_resources.'];
            return $result;
        }

        foreach (hg_ser_energy_tables() as $table => $meta) {
            $bridgeTable = (string)($meta['bridge_table'] ?? '');
            $detailFk = (string)($meta['detail_fk'] ?? '');
            $configColumn = (string)($meta['config_column'] ?? '');
            $detailConstraint = (string)($meta['bridge_constraint_detail'] ?? '');
            $resourceConstraint = (string)($meta['bridge_constraint_resource'] ?? '');
            if ($bridgeTable === '' || $detailFk === '') continue;

            if (!hg_ser_table_exists($link, $table)) {
                $result['ok'] = false;
                $result['errors'][] = ['table' => $table, 'error' => 'La tabla detalle no existe.'];
                continue;
            }

            if (!hg_ser_table_exists($link, $bridgeTable)) {
                $sql = "
                    CREATE TABLE `$bridgeTable` (
                      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                      `$detailFk` int(10) unsigned NOT NULL,
                      `resource_id` int(10) unsigned NOT NULL,
                      `energy_value` int(11) NOT NULL DEFAULT 0,
                      `sort_order` int(11) NOT NULL DEFAULT 0,
                      `is_active` tinyint(1) NOT NULL DEFAULT 1,
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                      `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `uq_{$bridgeTable}_detail_resource` (`$detailFk`,`resource_id`),
                      KEY `idx_{$bridgeTable}_detail` (`$detailFk`),
                      KEY `idx_{$bridgeTable}_resource` (`resource_id`),
                      KEY `idx_{$bridgeTable}_active_sort` (`is_active`,`sort_order`),
                      CONSTRAINT `$detailConstraint` FOREIGN KEY (`$detailFk`) REFERENCES `$table` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                      CONSTRAINT `$resourceConstraint` FOREIGN KEY (`resource_id`) REFERENCES `dim_systems_resources` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                if ($link->query($sql)) {
                    $GLOBALS['hg_ser_cache']['t:' . $bridgeTable] = true;
                    $result['messages'][] = $bridgeTable . ': tabla bridge creada.';
                } else {
                    $result['ok'] = false;
                    $result['errors'][] = ['table' => $bridgeTable, 'error' => $link->error, 'sql' => $sql];
                    continue;
                }
            } else {
                $result['messages'][] = $bridgeTable . ': tabla bridge ya existe.';
            }

            if ($configColumn !== '' && !hg_ser_column_exists($link, $table, $configColumn)) {
                $legacyValueColumn = (string)($meta['legacy_value_column'] ?? 'energy');
                $afterColumn = ($legacyValueColumn !== '' && hg_ser_column_exists($link, $table, $legacyValueColumn)) ? $legacyValueColumn : 'system_id';
                $sql = "ALTER TABLE `$table` ADD COLUMN `$configColumn` tinyint(1) NOT NULL DEFAULT 0 AFTER `$afterColumn`";
                if ($link->query($sql)) {
                    $GLOBALS['hg_ser_cache']['c:' . $table . ':' . $configColumn] = true;
                    $result['messages'][] = $table . ': columna ' . $configColumn . ' creada.';
                } else {
                    $result['ok'] = false;
                    $result['errors'][] = ['table' => $table, 'error' => $link->error, 'sql' => $sql];
                    continue;
                }
            } elseif ($configColumn !== '') {
                $result['messages'][] = $table . ': columna ' . $configColumn . ' ya existe.';
            }

            if (hg_ser_has_energy_resource_column($link, $table)) {
                $legacyFk = (string)($meta['legacy_fk'] ?? 'energy_resource_id');
                $legacyValueColumn = (string)($meta['legacy_value_column'] ?? 'energy');
                $sqlBackfill = "
                    INSERT INTO `$bridgeTable` (`$detailFk`, resource_id, energy_value, sort_order, is_active)
                    SELECT d.id, d.`$legacyFk`, COALESCE(d.`$legacyValueColumn`, 0), 0, 1
                    FROM `$table` d
                    LEFT JOIN `$bridgeTable` b ON b.`$detailFk` = d.id AND b.resource_id = d.`$legacyFk`
                    WHERE d.`$legacyFk` IS NOT NULL
                      AND d.`$legacyFk` > 0
                      AND COALESCE(d.`$legacyValueColumn`, 0) > 0
                      AND b.id IS NULL
                ";
                if ($link->query($sqlBackfill)) {
                    $affected = (int)$link->affected_rows;
                    if ($affected > 0) {
                        $result['messages'][] = $bridgeTable . ': migradas ' . $affected . ' relaciones legacy.';
                    }
                } else {
                    $result['ok'] = false;
                    $result['errors'][] = ['table' => $bridgeTable, 'error' => $link->error, 'sql' => $sqlBackfill];
                }
            }
        }

        return $result;
    }
}
