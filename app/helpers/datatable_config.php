<?php
// Shared read-only access to DataTables column configuration.

if (!function_exists('hg_datatable_config_load')) {
    function hg_datatable_config_load(mysqli $link): array
    {
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