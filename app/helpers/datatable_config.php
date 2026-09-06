<?php
// Shared read-only access to DataTables column configuration.

if (!function_exists('hg_datatable_config_table_exists')) {
    function hg_datatable_config_table_exists(mysqli $link): bool
    {
        static $cache = [];
        $dbKey = spl_object_hash($link);
        if (array_key_exists($dbKey, $cache)) {
            return $cache[$dbKey];
        }

        $sql = "SELECT COUNT(*) AS total
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'dim_datatable_columns'";
        $result = $link->query($sql);
        if (!$result) {
            return $cache[$dbKey] = false;
        }

        $row = $result->fetch_assoc();
        $result->free();
        return $cache[$dbKey] = ((int)($row['total'] ?? 0) > 0);
    }
}

if (!function_exists('hg_datatable_config_load')) {
    function hg_datatable_config_load(mysqli $link): array
    {
        if (!hg_datatable_config_table_exists($link)) {
            return [];
        }

        $sql = "SELECT datatable_id, datatable_label, column_index, column_label,
                       visible_default, is_core
                FROM dim_datatable_columns
                ORDER BY datatable_id ASC, column_index ASC";
        $result = $link->query($sql);
        if (!$result) {
            return [];
        }

        $config = [];
        while ($row = $result->fetch_assoc()) {
            $tableId = trim((string)($row['datatable_id'] ?? ''));
            if ($tableId === '') {
                continue;
            }

            if (!isset($config[$tableId])) {
                $config[$tableId] = [
                    'label' => (string)($row['datatable_label'] ?? $tableId),
                    'columns' => [],
                ];
            }

            $config[$tableId]['columns'][] = [
                'index' => (int)($row['column_index'] ?? -1),
                'label' => (string)($row['column_label'] ?? ''),
                'visible_default' => ((int)($row['visible_default'] ?? 0) === 1),
                'is_core' => ((int)($row['is_core'] ?? 0) === 1),
            ];
        }
        $result->free();

        return $config;
    }
}
?>