<?php
// Admin data ownership for Systems / Forms / system satellites.
// Controllers retain request validation, orchestration and rendering.

function hg_systems_admin_fetch_all(mysqli $link, string $sql): array {
    $rows = [];
    if ($rs = $link->query($sql)) {
        while ($row = $rs->fetch_assoc()) $rows[] = $row;
        $rs->free();
    }
    return $rows;
}

function hg_systems_admin_result(bool $ok, string $error = '', int $id = 0): array {
    return ['ok' => $ok, 'error' => $error, 'id' => $id];
}

function hg_systems_admin_origins(mysqli $link): array {
    return hg_systems_admin_fetch_all($link, 'SELECT id, name FROM dim_bibliographies ORDER BY name ASC');
}

function hg_systems_admin_system_delete(mysqli $link, int $id): array {
    if ($id <= 0) return hg_systems_admin_result(false, 'invalid_id');
    $st = $link->prepare('DELETE FROM dim_systems WHERE id=?');
    if (!$st) return hg_systems_admin_result(false, $link->error);
    $st->bind_param('i', $id);
    $ok = $st->execute();
    $error = $st->error;
    $st->close();
    return hg_systems_admin_result($ok, $error);
}

function hg_systems_admin_system_save(mysqli $link, int $id, array $d): array {
    if ($id > 0) {
        $st = $link->prepare('UPDATE dim_systems SET sort_order=?, name=?, image_url=?, forms=?, description=?, bibliography_id=? WHERE id=?');
        if (!$st) return hg_systems_admin_result(false, $link->error, $id);
        $st->bind_param('issisii', $d['sort_order'], $d['name'], $d['image_url'], $d['forms'], $d['description'], $d['bibliography_id'], $id);
        $ok = $st->execute();
        $error = $st->error;
        $st->close();
        return hg_systems_admin_result($ok, $error, $id);
    }
    $st = $link->prepare('INSERT INTO dim_systems (sort_order, name, image_url, forms, description, bibliography_id, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())');
    if (!$st) return hg_systems_admin_result(false, $link->error);
    $st->bind_param('issisi', $d['sort_order'], $d['name'], $d['image_url'], $d['forms'], $d['description'], $d['bibliography_id']);
    $ok = $st->execute();
    $error = $st->error;
    $newId = $ok ? (int)$st->insert_id : 0;
    $st->close();
    return hg_systems_admin_result($ok, $error, $newId);
}

function hg_systems_admin_system_rows(mysqli $link): array {
    return hg_systems_admin_fetch_all($link, "SELECT s.id, s.sort_order AS orden, s.name, s.image_url, s.forms AS formas, s.description AS descripcion, s.bibliography_id, COALESCE(b.name,'') AS origen_name FROM dim_systems s LEFT JOIN dim_bibliographies b ON s.bibliography_id=b.id ORDER BY s.sort_order, s.name");
}

function hg_systems_admin_forms_options(mysqli $link): array {
    $origins = hg_systems_admin_fetch_all($link, 'SELECT id, name FROM dim_bibliographies ORDER BY name ASC');
    $systems = hg_systems_admin_fetch_all($link, 'SELECT id, name FROM dim_systems ORDER BY sort_order ASC, name ASC');
    $tribesBySystem = [];
    foreach (hg_systems_admin_fetch_all($link, 'SELECT system_id, name FROM dim_tribes ORDER BY system_id ASC, name ASC') as $row) {
        $sid = (int)($row['system_id'] ?? 0);
        $name = trim((string)($row['name'] ?? ''));
        if ($sid <= 0 || $name === '') continue;
        if (!isset($tribesBySystem[$sid])) $tribesBySystem[$sid] = [];
        $tribesBySystem[$sid][] = $name;
    }
    return ['origins'=>$origins, 'systems'=>$systems, 'tribesBySystem'=>$tribesBySystem];
}

function hg_systems_admin_form_delete(mysqli $link, int $id): array {
    if ($id <= 0) return hg_systems_admin_result(false, 'invalid_id');
    $st = $link->prepare('DELETE FROM dim_forms WHERE id=?');
    if (!$st) return hg_systems_admin_result(false, $link->error);
    $st->bind_param('i', $id);
    $ok = $st->execute();
    $error = $st->error;
    $st->close();
    return hg_systems_admin_result($ok, $error);
}

function hg_systems_admin_form_save(mysqli $link, int $id, array $d): array {
    if ($id > 0) {
        $st = $link->prepare('UPDATE dim_forms SET affiliation=?, race=?, system_id=?, form=?, description=?, image_url=?, weapons=?, firearms=?, strength_bonus=?, dexterity_bonus=?, stamina_bonus=?, regeneration=?, hpregen=?, bibliography_id=?, updated_at=NOW() WHERE id=?');
        if (!$st) return hg_systems_admin_result(false, $link->error, $id);
        $st->bind_param('ssisssiisssiiii', $d['affiliation'], $d['race'], $d['system_id'], $d['form'], $d['description'], $d['image_url'], $d['weapons'], $d['firearms'], $d['strength_bonus'], $d['dexterity_bonus'], $d['stamina_bonus'], $d['regeneration'], $d['hpregen'], $d['bibliography_id'], $id);
        $ok = $st->execute();
        $error = $st->error;
        $st->close();
        return hg_systems_admin_result($ok, $error, $id);
    }
    $st = $link->prepare('INSERT INTO dim_forms (affiliation, race, system_id, form, description, image_url, weapons, firearms, strength_bonus, dexterity_bonus, stamina_bonus, regeneration, hpregen, bibliography_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
    if (!$st) return hg_systems_admin_result(false, $link->error);
    $st->bind_param('ssisssiisssiii', $d['affiliation'], $d['race'], $d['system_id'], $d['form'], $d['description'], $d['image_url'], $d['weapons'], $d['firearms'], $d['strength_bonus'], $d['dexterity_bonus'], $d['stamina_bonus'], $d['regeneration'], $d['hpregen'], $d['bibliography_id']);
    $ok = $st->execute();
    $error = $st->error;
    $newId = $ok ? (int)$st->insert_id : 0;
    $st->close();
    return hg_systems_admin_result($ok, $error, $newId);
}

function hg_systems_admin_form_rows(mysqli $link, int $systemId): array {
    $sql = "SELECT f.id, f.pretty_id, f.description, f.affiliation AS afiliacion, f.race AS raza, f.system_id, COALESCE(ds.name,'') AS system_name, f.form AS forma, f.image_url AS imagen, f.weapons AS armas, f.firearms AS armasfuego, f.strength_bonus AS bonfue, f.dexterity_bonus AS bondes, f.stamina_bonus AS bonres, f.regeneration AS regenera, f.hpregen, f.bibliography_id, COALESCE(b.name,'') AS origen_name FROM dim_forms f LEFT JOIN dim_systems ds ON ds.id=f.system_id LEFT JOIN dim_bibliographies b ON f.bibliography_id=b.id";
    if ($systemId > 0) $sql .= ' WHERE f.system_id = ?';
    $sql .= ' ORDER BY ds.sort_order, ds.name, f.affiliation, f.race, f.form';
    if ($systemId <= 0) return hg_systems_admin_fetch_all($link, $sql);
    $rows = [];
    $st = $link->prepare($sql);
    if (!$st) return $rows;
    $st->bind_param('i', $systemId);
    $st->execute();
    $rs = $st->get_result();
    while ($rs && ($row = $rs->fetch_assoc())) $rows[] = $row;
    $st->close();
    return $rows;
}

function ase_load_systems(mysqli $link): array
{
    $rows = [];
    if ($rs = $link->query("SELECT id, name FROM dim_systems ORDER BY sort_order ASC, name ASC")) {
        while ($row = $rs->fetch_assoc()) {
            $rows[] = ['id' => (int)($row['id'] ?? 0), 'name' => (string)($row['name'] ?? '')];
        }
        $rs->close();
    }
    return $rows;
}

function ase_load_rows(mysqli $link, string $tab, int $systemId, string $q): array
{
    $meta = ase_meta($tab);
    $table = $meta['table'];
    $where = 'WHERE 1=1';
    $types = '';
    $params = [];

    if ($systemId > 0) {
        $where .= ' AND t.system_id = ?';
        $types .= 'i';
        $params[] = $systemId;
    }
    if ($q !== '') {
        $where .= ' AND t.name LIKE ?';
        $types .= 's';
        $params[] = '%' . $q . '%';
    }

    $energySql = hg_ser_energy_sql_parts($link, $table, 't', 'er');
    $sql = "
        SELECT
            t.id,
            t.name,
            COALESCE(t.system_id, 0) AS system_id,
            COALESCE(s.name, t.system_name, '') AS system_name,
            " . hg_ser_energy_value_sql_expr($link, $table, 't') . " AS energy
            {$energySql['select']}
        FROM `$table` t
        LEFT JOIN dim_systems s ON s.id = t.system_id
        {$energySql['join']}
        {$where}
        ORDER BY s.name ASC, t.name ASC
    ";

    $rows = [];
    if ($st = $link->prepare($sql)) {
        ase_bind_params($st, $types, $params);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'system_id' => (int)($row['system_id'] ?? 0),
                'system_name' => (string)($row['system_name'] ?? ''),
                'energy' => (int)($row['energy'] ?? 0),
                'energy_resource_id' => (int)($row['energy_resource_id'] ?? 0),
                'energy_resource_name' => (string)($row['energy_resource_name'] ?? ''),
                'energy_resource_pretty_id' => (string)($row['energy_resource_pretty_id'] ?? ''),
            ];
        }
        $st->close();
    }

    return hg_ser_attach_energy_summary($link, $table, $rows);
}

function ase_save_assignments(mysqli $link, string $tab, array $updates, array $resourcesBySystem, array $resourcesAll, bool $allowAllStateResources = false): array
{
    $meta = ase_meta($tab);
    $table = $meta['table'];
    if (!hg_ser_has_energy_bridge_table($link, $table)) {
        return ['ok' => false, 'message' => 'El schema aún no está preparado para esta tabla.'];
    }

    $rowsById = [];
    $ids = [];
    foreach ($updates as $rawId => $payload) {
        $detailId = (int)$rawId;
        if ($detailId <= 0) continue;
        $ids[$detailId] = $detailId;
        $rowsById[$detailId] = hg_ser_normalize_posted_energy_assignments($payload);
    }
    if (empty($ids)) {
        return ['ok' => true, 'message' => 'No había cambios que guardar.'];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, COALESCE(system_id, 0) AS system_id FROM `$table` WHERE id IN ($placeholders)";
    $detailSystems = [];
    if ($st = $link->prepare($sql)) {
        $params = array_values($ids);
        ase_bind_params($st, str_repeat('i', count($params)), $params);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $detailSystems[(int)$row['id']] = (int)($row['system_id'] ?? 0);
        }
        $st->close();
    }

    foreach ($rowsById as $detailId => $assignments) {
        if (!isset($detailSystems[$detailId])) {
            return ['ok' => false, 'message' => 'Hay filas que ya no existen. Recarga la página.'];
        }
        $energyError = hg_ser_validate_energy_assignments($assignments, (int)$detailSystems[$detailId], $resourcesBySystem, $resourcesAll, $allowAllStateResources);
        if ($energyError !== null) {
            return ['ok' => false, 'message' => $energyError];
        }
    }

    $link->begin_transaction();
    try {
        foreach ($rowsById as $detailId => $assignments) {
            $save = hg_ser_save_energy_assignments($link, $table, $detailId, $assignments);
            if (empty($save['ok'])) {
                throw new RuntimeException((string)($save['message'] ?? 'No se pudieron guardar los recursos de energía.'));
            }
        }
        $link->commit();
        return ['ok' => true, 'message' => 'Recursos de energía actualizados.'];
    } catch (Throwable $e) {
        $link->rollback();
        return ['ok' => false, 'message' => 'No se pudieron guardar los cambios: ' . $e->getMessage()];
    }
}

function ased_table_exists(mysqli $link, string $table): bool
{
    $st = $link->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if (!$st) return false;
    $st->bind_param('s', $table);
    $st->execute();
    $st->bind_result($count);
    $st->fetch();
    $st->close();
    return ((int)$count > 0);
}

function ased_column_exists(mysqli $link, string $table, string $column): bool
{
    $st = $link->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    if (!$st) return false;
    $st->bind_param('ss', $table, $column);
    $st->execute();
    $st->bind_result($count);
    $st->fetch();
    $st->close();
    return ((int)$count > 0);
}

function ased_systems(mysqli $link): array
{
    $rows = [];
    if ($rs = $link->query("SELECT id, name FROM dim_systems ORDER BY sort_order ASC, name ASC, id ASC")) {
        while ($row = $rs->fetch_assoc()) {
            $rows[] = ['id' => (int)$row['id'], 'name' => (string)$row['name']];
        }
        $rs->close();
    }
    return $rows;
}

function ased_catalog(mysqli $link, string $table): array
{
    $rows = [];
    $hasSystemId = ased_column_exists($link, $table, 'system_id');
    $sql = "
        SELECT
            e.id,
            e.name,
            " . ($hasSystemId ? "COALESCE(e.system_id, 0)" : "0") . " AS system_id,
            " . ($hasSystemId ? "COALESCE(ds.name, '')" : "''") . " AS system_name
        FROM `{$table}` e
        " . ($hasSystemId ? "LEFT JOIN dim_systems ds ON ds.id = e.system_id" : "") . "
        ORDER BY " . ($hasSystemId ? "ds.name ASC, " : "") . "e.name ASC, e.id ASC
    ";
    if ($rs = $link->query($sql)) {
        while ($row = $rs->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'origin_system_id' => (int)($row['system_id'] ?? 0),
                'origin_system_name' => trim((string)($row['system_name'] ?? '')),
            ];
        }
        $rs->close();
    }
    return $rows;
}

function ased_existing_assignments(mysqli $link, string $bridgeTable, string $detailFk, int $systemId): array
{
    $rows = [];
    $hasActive = ased_column_exists($link, $bridgeTable, 'is_active');
    $sql = "
        SELECT {$detailFk} AS detail_id, " . ($hasActive ? 'is_active' : '1') . " AS is_active
        FROM `{$bridgeTable}`
        WHERE system_id = ?
        ORDER BY id ASC
    ";
    if ($st = $link->prepare($sql)) {
        $st->bind_param('i', $systemId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $rows[(int)$row['detail_id']] = ['is_active' => (int)($row['is_active'] ?? 1)];
        }
        $st->close();
    }
    return $rows;
}

function ased_save_group(mysqli $link, int $systemId, array $group, array $selectedIds, array $activeIds): void
{
    $bridgeTable = (string)$group['bridge_table'];
    $detailFk = (string)$group['detail_fk'];
    $hasActive = (bool)$group['has_active'];

    $delete = $link->prepare("DELETE FROM `{$bridgeTable}` WHERE system_id = ?");
    if (!$delete) {
        throw new RuntimeException('No se pudo preparar el borrado de ' . $bridgeTable . '.');
    }
    $delete->bind_param('i', $systemId);
    $delete->execute();
    $delete->close();

    $selectedMap = [];
    foreach ($selectedIds as $id) {
        $detailId = (int)$id;
        if ($detailId > 0) {
            $selectedMap[$detailId] = true;
        }
    }
    if (empty($selectedMap)) {
        return;
    }

    $activeMap = [];
    foreach ($activeIds as $id) {
        $detailId = (int)$id;
        if ($detailId > 0) {
            $activeMap[$detailId] = true;
        }
    }

    $insertSql = $hasActive
        ? "INSERT INTO `{$bridgeTable}` (system_id, `{$detailFk}`, is_active) VALUES (?, ?, ?)"
        : "INSERT INTO `{$bridgeTable}` (system_id, `{$detailFk}`) VALUES (?, ?)";
    $insert = $link->prepare($insertSql);
    if (!$insert) {
        throw new RuntimeException('No se pudo preparar el alta en ' . $bridgeTable . '.');
    }

    foreach (array_keys($selectedMap) as $detailId) {
        if ($hasActive) {
            $isActive = empty($activeMap) || isset($activeMap[$detailId]) ? 1 : 0;
            $insert->bind_param('iii', $systemId, $detailId, $isActive);
        } else {
            $insert->bind_param('ii', $systemId, $detailId);
        }
        $insert->execute();
    }

    $insert->close();
}

function asr_table_exists(mysqli $link, string $table): bool {
    $st = $link->prepare('SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if (!$st) return false;
    $st->bind_param('s', $table);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    return ((int)($row['c'] ?? 0) > 0);
}

function asr_table_columns(mysqli $link, string $table): array {
    $out = [];
    $safe = str_replace('`', '``', $table);
    $sql = "SHOW COLUMNS FROM `{$safe}`";
    if ($res = $link->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $out[(string)$r['Field']] = true;
        }
        $res->free();
    }
    return $out;
}

function asr_load_state(mysqli $link, int $systemId, string $tblSystems, string $tblResources, string $tblBridge, array $bridgeCols): array {
    $systems = [];
    if ($res = $link->query("SELECT id, name FROM `{$tblSystems}` ORDER BY sort_order, name")) {
        while ($r = $res->fetch_assoc()) $systems[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];
        $res->free();
    }

    if ($systemId <= 0 && !empty($systems)) {
        $systemId = (int)$systems[0]['id'];
    }

    $resources = [];
    if ($res = $link->query("SELECT id, name, kind, sort_order, description FROM `{$tblResources}` ORDER BY kind, sort_order, name")) {
        while ($r = $res->fetch_assoc()) {
            $resources[] = [
                'id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'kind' => (string)$r['kind'],
                'sort_order' => (int)$r['sort_order'],
                'description' => (string)($r['description'] ?? ''),
            ];
        }
        $res->free();
    }

    $current = [];
    if ($systemId > 0) {
        $selCols = ['resource_id'];
        if (isset($bridgeCols['sort_order'])) $selCols[] = 'sort_order';
        if (isset($bridgeCols['is_active'])) $selCols[] = 'is_active';
        if (isset($bridgeCols['position'])) $selCols[] = 'position';

        $sqlCur = "SELECT " . implode(',', $selCols) . " FROM `{$tblBridge}` WHERE system_id = ?";
        if ($st = $link->prepare($sqlCur)) {
            $st->bind_param('i', $systemId);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) {
                $rid = (int)$r['resource_id'];
                $current[$rid] = [
                    'sort_order' => (int)($r['sort_order'] ?? 0),
                    'is_active' => (int)($r['is_active'] ?? 1),
                    'position' => (string)($r['position'] ?? ''),
                ];
            }
            $st->close();
        }
    }

    return [
        'system_id' => $systemId,
        'systems' => $systems,
        'resources' => $resources,
        'current' => $current,
        'flags' => [
            'has_sort' => isset($bridgeCols['sort_order']),
            'has_active' => isset($bridgeCols['is_active']),
            'has_position' => isset($bridgeCols['position']),
        ],
    ];
}

function asr_save(mysqli $link, int $systemId, array $rows, array $selected, string $tblBridge, array $bridgeCols): array {
    if ($systemId <= 0) {
        return ['ok' => false, 'message' => 'Sistema invalido.'];
    }

    $selectedMap = [];
    foreach ($selected as $rid) {
        $rid = (int)$rid;
        if ($rid > 0) $selectedMap[$rid] = true;
    }

    $keepRows = [];
    foreach ($rows as $rid => $row) {
        $rid = (int)$rid;
        if ($rid <= 0 || !isset($selectedMap[$rid])) continue;
        $keepRows[$rid] = [
            'sort_order' => (int)($row['sort_order'] ?? 0),
            'is_active' => isset($row['is_active']) ? 1 : 0,
            'position' => trim((string)($row['position'] ?? '')),
        ];
    }

    $link->begin_transaction();
    try {
        $del = $link->prepare("DELETE FROM `{$tblBridge}` WHERE system_id = ?");
        if (!$del) throw new RuntimeException('No se pudo preparar DELETE.');
        $del->bind_param('i', $systemId);
        $del->execute();
        $del->close();

        $insertCols = ['system_id', 'resource_id'];
        $hasSort = isset($bridgeCols['sort_order']);
        $hasActive = isset($bridgeCols['is_active']);
        $hasPosition = isset($bridgeCols['position']);
        if ($hasSort) $insertCols[] = 'sort_order';
        if ($hasActive) $insertCols[] = 'is_active';
        if ($hasPosition) $insertCols[] = 'position';

        $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
        $sqlIns = "INSERT INTO `{$tblBridge}` (" . implode(',', $insertCols) . ") VALUES ({$placeholders})";
        $ins = $link->prepare($sqlIns);
        if (!$ins) throw new RuntimeException('No se pudo preparar INSERT.');

        foreach ($keepRows as $rid => $row) {
            $sortOrder = (int)$row['sort_order'];
            $isActive = (int)$row['is_active'];
            $position = (string)$row['position'];

            if ($hasSort && $hasActive && $hasPosition) {
                $ins->bind_param('iiiis', $systemId, $rid, $sortOrder, $isActive, $position);
            } elseif ($hasSort && $hasActive && !$hasPosition) {
                $ins->bind_param('iiii', $systemId, $rid, $sortOrder, $isActive);
            } elseif ($hasSort && !$hasActive && $hasPosition) {
                $ins->bind_param('iiis', $systemId, $rid, $sortOrder, $position);
            } elseif (!$hasSort && $hasActive && $hasPosition) {
                $ins->bind_param('iiis', $systemId, $rid, $isActive, $position);
            } elseif ($hasSort && !$hasActive && !$hasPosition) {
                $ins->bind_param('iii', $systemId, $rid, $sortOrder);
            } elseif (!$hasSort && $hasActive && !$hasPosition) {
                $ins->bind_param('iii', $systemId, $rid, $isActive);
            } elseif (!$hasSort && !$hasActive && $hasPosition) {
                $ins->bind_param('iis', $systemId, $rid, $position);
            } else {
                $ins->bind_param('ii', $systemId, $rid);
            }
            $ins->execute();
        }
        $ins->close();

        $link->commit();
        return ['ok' => true, 'message' => 'Guardado correctamente.'];
    } catch (Throwable $e) {
        $link->rollback();
        return ['ok' => false, 'message' => 'Error al guardar: ' . $e->getMessage()];
    }
}
