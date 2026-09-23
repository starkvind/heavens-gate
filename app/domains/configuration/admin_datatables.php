<?php
// Admin data ownership for configurable public DataTable columns.

if (!function_exists('hg_configuration_admin_datatable_update')) {
    function hg_configuration_admin_datatable_update(mysqli $link, int $id, int $visibleDefault, int $isCore): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];
        try {
            $stmt = $link->prepare('UPDATE dim_datatable_columns SET visible_default = ?, is_core = ? WHERE id = ?');
            if (!$stmt) return ['ok' => false, 'error' => $link->error];
            $stmt->bind_param('iii', $visibleDefault, $isCore, $id);
            $ok = $stmt->execute();
            $error = $stmt->error;
            $stmt->close();
            return ['ok' => $ok, 'error' => $error];
        } catch (mysqli_sql_exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('hg_configuration_admin_datatable_insert')) {
    function hg_configuration_admin_datatable_insert(
        mysqli $link,
        string $datatableId,
        string $datatableLabel,
        int $columnIndex,
        string $columnLabel,
        int $visibleDefault,
        int $isCore
    ): array {
        try {
            $sql = 'INSERT INTO dim_datatable_columns '
                . '(datatable_id, datatable_label, column_index, column_label, visible_default, is_core) '
                . 'VALUES (?, ?, ?, ?, ?, ?)';
            $stmt = $link->prepare($sql);
            if (!$stmt) return ['ok' => false, 'error' => $link->error, 'duplicate' => false];
            $stmt->bind_param('ssisii', $datatableId, $datatableLabel, $columnIndex, $columnLabel, $visibleDefault, $isCore);
            $ok = $stmt->execute();
            $duplicate = ((int)$stmt->errno === 1062);
            $error = $stmt->error;
            $stmt->close();
            return ['ok' => $ok, 'error' => $error, 'duplicate' => $duplicate];
        } catch (mysqli_sql_exception $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'duplicate' => ((int)$e->getCode() === 1062)];
        }
    }
}

if (!function_exists('hg_configuration_admin_datatable_delete')) {
    function hg_configuration_admin_datatable_delete(mysqli $link, int $id): array
    {
        if ($id <= 0) return ['ok' => false, 'error' => 'invalid_id'];
        try {
            $stmt = $link->prepare('DELETE FROM dim_datatable_columns WHERE id = ?');
            if (!$stmt) return ['ok' => false, 'error' => $link->error];
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $error = $stmt->error;
            $stmt->close();
            return ['ok' => $ok, 'error' => $error];
        } catch (mysqli_sql_exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('hg_configuration_admin_datatable_rows')) {
    function hg_configuration_admin_datatable_rows(mysqli $link): array
    {
        $rows = [];
        try {
            $sql = 'SELECT id, datatable_id, datatable_label, column_index, column_label, visible_default, is_core '
                . 'FROM dim_datatable_columns ORDER BY datatable_label, datatable_id, column_index';
            $result = $link->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) $rows[] = $row;
                $result->free();
            }
        } catch (mysqli_sql_exception $e) {
            return [];
        }
        return $rows;
    }
}
