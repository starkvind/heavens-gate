<?php

include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/system_energy_resource.php');

if (!function_exists('hg_system_details_admin_has_column')) {
    function hg_system_details_admin_has_column(mysqli $link, string $table, string $column): bool
    {
        return hg_table_has_column($link, $table, $column);
    }
}

if (!function_exists('hg_system_details_admin_fetch_origins')) {
    function hg_system_details_admin_fetch_origins(mysqli $link): array
    {
        $rows = [];
        $result = $link->query("SELECT id, name FROM dim_bibliographies ORDER BY name ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) $rows[] = $row;
            $result->close();
        }
        return $rows;
    }
}

if (!function_exists('hg_system_details_admin_fetch_systems')) {
    function hg_system_details_admin_fetch_systems(mysqli $link): array
    {
        $rows = [];
        $result = $link->query("SELECT id, name FROM dim_systems ORDER BY sort_order ASC, name ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = ['id' => (int)$row['id'], 'name' => (string)$row['name']];
            }
            $result->close();
        }
        return $rows;
    }
}

if (!function_exists('hg_system_details_admin_delete')) {
    function hg_system_details_admin_delete(mysqli $link, string $table, string $pk, int $id): array
    {
        $sql = "DELETE FROM `{$table}` WHERE `{$pk}`=?";
        $stmt = $link->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Error al preparar DELETE: ' . $link->error];
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $message = $ok ? 'Eliminado correctamente.' : 'Error al borrar: ' . $stmt->error;
        $stmt->close();
        return ['ok' => $ok, 'message' => $message];
    }
}

if (!function_exists('hg_system_details_admin_create')) {
    function hg_system_details_admin_create(mysqli $link, array $meta, array $vals, array $extraWrite): array
    {
        $table = (string)$meta['table'];
        $cols = [];
        $ph = [];
        $types = '';
        $bind = [];

        foreach ($meta['fields'] as $field) {
            if (($field['db'] ?? 's') === 'x') continue;
            $isNullableSelectInt = (($field['ui'] ?? '') === 'select_int') && empty($field['req']) && (($field['db'] ?? 's') === 'i');
            $cols[] = (string)$field['k'];
            $ph[] = $isNullableSelectInt ? 'NULLIF(?, 0)' : '?';
            $types .= (($field['db'] ?? 's') === 'i') ? 'i' : 's';
            $bind[] = $vals[$field['k']];
        }
        foreach ($extraWrite as $key => $value) {
            $cols[] = (string)$key;
            $ph[] = '?';
            $types .= 's';
            $bind[] = (string)$value;
        }

        $quotedCols = implode(',', array_map(static fn($col) => "`{$col}`", $cols));
        $sql = "INSERT INTO `{$table}` ({$quotedCols}) VALUES (" . implode(',', $ph) . ")";
        if (!empty($meta['has_timestamps'])) {
            $sql = "INSERT INTO `{$table}` ({$quotedCols}, created_at, updated_at) VALUES (" . implode(',', $ph) . ", NOW(), NOW())";
        }

        $stmt = $link->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Error al preparar INSERT: ' . $link->error];
        }

        $stmt->bind_param($types, ...$bind);
        if (!$stmt->execute()) {
            $message = 'Error al crear: ' . $stmt->error;
            $stmt->close();
            return ['ok' => false, 'message' => $message];
        }

        $id = (int)$stmt->insert_id;
        $stmt->close();
        hg_update_pretty_id_if_exists($link, $table, $id, (string)($vals['name'] ?? ''));

        return ['ok' => true, 'id' => $id, 'message' => 'Creado correctamente.'];
    }
}

if (!function_exists('hg_system_details_admin_update')) {
    function hg_system_details_admin_update(mysqli $link, array $meta, array $vals, array $extraWrite, int $id): array
    {
        $table = (string)$meta['table'];
        $pk = (string)$meta['pk'];
        $sets = [];
        $types = '';
        $bind = [];

        foreach ($meta['fields'] as $field) {
            if (($field['db'] ?? 's') === 'x') continue;
            $isNullableSelectInt = (($field['ui'] ?? '') === 'select_int') && empty($field['req']) && (($field['db'] ?? 's') === 'i');
            $sets[] = "`" . $field['k'] . "`=" . ($isNullableSelectInt ? 'NULLIF(?, 0)' : '?');
            $types .= (($field['db'] ?? 's') === 'i') ? 'i' : 's';
            $bind[] = $vals[$field['k']];
        }
        foreach ($extraWrite as $key => $value) {
            $sets[] = "`{$key}`=?";
            $types .= 's';
            $bind[] = (string)$value;
        }

        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets);
        if (!empty($meta['has_timestamps'])) $sql .= ", updated_at=NOW()";
        $sql .= " WHERE `{$pk}`=?";
        $types .= 'i';
        $bind[] = $id;

        $stmt = $link->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Error al preparar UPDATE: ' . $link->error];
        }

        $stmt->bind_param($types, ...$bind);
        if (!$stmt->execute()) {
            $message = 'Error al actualizar: ' . $stmt->error;
            $stmt->close();
            return ['ok' => false, 'message' => $message];
        }

        $stmt->close();
        hg_update_pretty_id_if_exists($link, $table, $id, (string)($vals['name'] ?? ''));
        return ['ok' => true, 'message' => 'Actualizado.'];
    }
}

if (!function_exists('hg_system_details_admin_count')) {
    function hg_system_details_admin_count(mysqli $link, array $meta, string $q, int $systemId): array
    {
        $table = (string)$meta['table'];
        $nameCol = (string)$meta['name_col'];
        $where = "WHERE 1=1";
        $types = '';
        $params = [];

        if ($q !== '') {
            $where .= " AND t.`{$nameCol}` LIKE ?";
            $types .= 's';
            $params[] = '%' . $q . '%';
        }
        if ($systemId > 0) {
            $where .= " AND t.`system_id` = ?";
            $types .= 'i';
            $params[] = $systemId;
        }

        $energySql = hg_ser_energy_sql_parts($link, $table, 't', 'er_count');
        $from = "`{$table}` t LEFT JOIN dim_systems s ON s.id = t.system_id{$energySql['join']}";
        $stmt = $link->prepare("SELECT COUNT(*) AS c FROM {$from} {$where}");
        if (!$stmt) return ['ok' => false, 'count' => 0, 'error' => $link->error];
        if ($types !== '') $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['ok' => false, 'count' => 0, 'error' => $error];
        }
        $result = $stmt->get_result();
        $count = ($result && ($row = $result->fetch_assoc())) ? (int)$row['c'] : 0;
        $stmt->close();
        return ['ok' => true, 'count' => $count];
    }
}

if (!function_exists('hg_system_details_admin_fetch_rows')) {
    function hg_system_details_admin_fetch_rows(
        mysqli $link,
        array $meta,
        string $q,
        int $systemId,
        ?int $offset = null,
        ?int $limit = null
    ): array {
        $table = (string)$meta['table'];
        $pk = (string)$meta['pk'];
        $nameCol = (string)$meta['name_col'];
        $where = "WHERE 1=1";
        $types = '';
        $params = [];

        if ($q !== '') {
            $where .= " AND t.`{$nameCol}` LIKE ?";
            $types .= 's';
            $params[] = '%' . $q . '%';
        }
        if ($systemId > 0) {
            $where .= " AND t.`system_id` = ?";
            $types .= 'i';
            $params[] = $systemId;
        }

        $sqlFields = array_values(array_filter($meta['fields'], static fn($f) => (($f['db'] ?? 's') !== 'x')));
        $energySql = hg_ser_energy_sql_parts($link, $table, 't', 'er_admin');
        $from = "`{$table}` t LEFT JOIN dim_systems s ON s.id = t.system_id{$energySql['join']}";
        $cols = array_map(static fn($f) => "t.`" . $f['k'] . "`", $sqlFields);
        $cols[] = "t.`{$pk}`";
        $cols[] = "s.name AS system_name";
        if ($energySql['select'] !== '') {
            $cols[] = "COALESCE(er_admin.name, '') AS energy_resource_name";
        }
        $cols = array_values(array_unique($cols));

        $sql = "SELECT " . implode(',', $cols) . " FROM {$from} {$where} ORDER BY " . $meta['order_by'];
        if ($offset !== null && $limit !== null) {
            $sql .= " LIMIT ?, ?";
            $types .= 'ii';
            $params[] = $offset;
            $params[] = $limit;
        }

        $stmt = $link->prepare($sql);
        if (!$stmt) return ['ok' => false, 'rows' => [], 'error' => $link->error];
        if ($types !== '') $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return ['ok' => false, 'rows' => [], 'error' => $error];
        }

        $result = $stmt->get_result();
        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) $rows[] = $row;
        $stmt->close();

        $rows = hg_ser_attach_energy_summary($link, $table, $rows);
        return ['ok' => true, 'rows' => $rows];
    }
}
