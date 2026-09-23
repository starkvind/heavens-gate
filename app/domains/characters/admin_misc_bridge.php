<?php

function acmb_table_exists(mysqli $db, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $ok = false;
    if ($st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?")) {
        $st->bind_param('s', $table);
        $st->execute();
        $st->bind_result($count);
        $st->fetch();
        $st->close();
        $ok = ((int)$count > 0);
    }
    $cache[$table] = $ok;
    return $ok;
}

function acmb_column_exists(mysqli $db, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . ':' . $column;
    if (isset($cache[$key])) return $cache[$key];
    $ok = false;
    if ($st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
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

function acmb_bind_params(mysqli_stmt $st, string $types, array &$values): bool
{
    if ($types === '') return true;
    $refs = [];
    $refs[] = $types;
    foreach ($values as $k => $v) {
        $refs[] = &$values[$k];
    }
    return (bool)call_user_func_array([$st, 'bind_param'], $refs);
}

function acmb_character_options(mysqli $db): array
{
    $rows = [];
    $hasSystem = acmb_column_exists($db, 'fact_characters', 'system_id') && acmb_table_exists($db, 'dim_systems');
    $hasChronicle = acmb_column_exists($db, 'fact_characters', 'chronicle_id') && acmb_table_exists($db, 'dim_chronicles');
    $sql = "
        SELECT
            c.id,
            COALESCE(NULLIF(TRIM(c.name), ''), CONCAT('Personaje #', c.id)) AS name,
            " . ($hasSystem ? "COALESCE(ds.name, '')" : "''") . " AS system_name,
            " . ($hasSystem ? "COALESCE(c.system_id, 0)" : "0") . " AS system_id,
            " . ($hasChronicle ? "COALESCE(ch.name, '')" : "''") . " AS chronicle_name
        FROM fact_characters c
        " . ($hasSystem ? "LEFT JOIN dim_systems ds ON ds.id = c.system_id" : "") . "
        " . ($hasChronicle ? "LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id" : "") . "
        ORDER BY c.name ASC, c.id ASC
    ";
    if ($rs = $db->query($sql)) {
        while ($row = $rs->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            $label = (string)($row['name'] ?? ('Personaje #' . $id));
            $systemName = trim((string)($row['system_name'] ?? ''));
            $chronicleName = trim((string)($row['chronicle_name'] ?? ''));
            if ($systemName !== '') $label .= ' [Sis: ' . $systemName . ']';
            if ($chronicleName !== '') $label .= ' [Cr: ' . $chronicleName . ']';
            $rows[$id] = [
                'label' => $label,
                'system_id' => (int)($row['system_id'] ?? 0),
            ];
        }
        $rs->close();
    }
    return $rows;
}

function acmb_misc_options(mysqli $db): array
{
    $rows = [];
    $sql = "
        SELECT m.id, m.name, m.kind, COALESCE(m.system_id, 0) AS system_id, COALESCE(ds.name, m.system_name, '') AS system_name
        FROM fact_misc_systems m
        LEFT JOIN dim_systems ds ON ds.id = m.system_id
        ORDER BY system_name ASC, m.kind ASC, m.name ASC, m.id ASC
    ";
    if ($rs = $db->query($sql)) {
        while ($row = $rs->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            $systemName = trim((string)($row['system_name'] ?? ''));
            $kind = trim((string)($row['kind'] ?? ''));
            $name = trim((string)($row['name'] ?? ''));
            $label = '';
            if ($systemName !== '') $label .= '[' . $systemName . '] ';
            if ($kind !== '') $label .= $kind . ' - ';
            $label .= ($name !== '' ? $name : ('Misc #' . $id));
            $rows[$id] = [
                'label' => $label,
                'system_id' => (int)($row['system_id'] ?? 0),
            ];
        }
        $rs->close();
    }
    return $rows;
}

function acmb_fetch_assignments(mysqli $db, int $characterId, int $systemId, string $q): array
{
    $sql = "
        SELECT
            b.id,
            b.character_id,
            b.misc_system_id,
            COALESCE(b.sort_order, 0) AS sort_order,
            COALESCE(b.notes, '') AS notes,
            COALESCE(b.is_active, 1) AS is_active,
            c.name AS character_name,
            COALESCE(c.system_id, 0) AS character_system_id,
            COALESCE(ds.name, '') AS character_system_name,
            m.name AS misc_name,
            COALESCE(m.kind, '') AS misc_kind,
            COALESCE(m.system_id, 0) AS misc_system_id_value,
            COALESCE(ms.name, m.system_name, '') AS misc_system_name
        FROM bridge_characters_misc_systems b
        INNER JOIN fact_characters c ON c.id = b.character_id
        INNER JOIN fact_misc_systems m ON m.id = b.misc_system_id
        LEFT JOIN dim_systems ds ON ds.id = c.system_id
        LEFT JOIN dim_systems ms ON ms.id = m.system_id
        WHERE 1=1
    ";
    $types = '';
    $params = [];
    if ($characterId > 0) {
        $sql .= " AND b.character_id = ?";
        $types .= 'i';
        $params[] = $characterId;
    }
    if ($systemId > 0) {
        $sql .= " AND (c.system_id = ? OR m.system_id = ?)";
        $types .= 'ii';
        $params[] = $systemId;
        $params[] = $systemId;
    }
    $q = trim($q);
    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= " AND (
            c.name LIKE ?
            OR m.name LIKE ?
            OR m.kind LIKE ?
            OR COALESCE(ds.name, '') LIKE ?
            OR COALESCE(ms.name, m.system_name, '') LIKE ?
        )";
        $types .= 'sssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " ORDER BY c.name ASC, b.sort_order ASC, m.kind ASC, m.name ASC, b.id ASC";

    $rows = [];
    if ($st = $db->prepare($sql)) {
        if ($types !== '') acmb_bind_params($st, $types, $params);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $rows[] = $row;
        }
        $st->close();
    }
    return $rows;
}



if (!function_exists('acmb_system_options')) {
    function acmb_system_options(mysqli $db): array {
        $rows=[];
        if (!acmb_table_exists($db,'dim_systems')) return $rows;
        if ($rs=$db->query("SELECT id, name FROM dim_systems ORDER BY sort_order ASC, name ASC")) {
            while($row=$rs->fetch_assoc()) $rows[(int)($row['id'] ?? 0)] = (string)($row['name'] ?? '');
            $rs->close();
        }
        return $rows;
    }
}
if (!function_exists('acmb_assignment_exists')) {
    function acmb_assignment_exists(mysqli $db, int $id): bool {
        if ($id<=0) return false;
        $st=$db->prepare("SELECT id FROM bridge_characters_misc_systems WHERE id = ? LIMIT 1");
        if(!$st) return false;
        $st->bind_param('i',$id);$st->execute();$st->bind_result($found);$st->fetch();$st->close();
        return (int)$found>0;
    }
}
if (!function_exists('acmb_duplicate_assignment')) {
    function acmb_duplicate_assignment(mysqli $db, int $characterId, int $miscId, int $id): int {
        $dup=0;
        $st=$db->prepare("SELECT id FROM bridge_characters_misc_systems WHERE character_id = ? AND misc_system_id = ? AND id <> ? LIMIT 1");
        if(!$st) return 0;
        $st->bind_param('iii',$characterId,$miscId,$id);$st->execute();$st->bind_result($dup);$st->fetch();$st->close();
        return (int)$dup;
    }
}
if (!function_exists('acmb_save_assignment')) {
    function acmb_save_assignment(mysqli $db, int $id, int $characterId, int $miscId, int $sortOrder, string $notes, int $isActive): array {
        if ($id>0) {
            $sql="UPDATE bridge_characters_misc_systems
                  SET character_id = ?, misc_system_id = ?, sort_order = ?, notes = NULLIF(?, ''), is_active = ?, updated_at = NOW()
                  WHERE id = ?";
            $st=$db->prepare($sql);
            if(!$st) return ['ok'=>false,'message'=>'No se pudo preparar la actualización.','error'=>$db->error];
            $st->bind_param('iiisii',$characterId,$miscId,$sortOrder,$notes,$isActive,$id);
            $ok=$st->execute();$err=$st->error;$st->close();
            return $ok?['ok'=>true,'id'=>$id,'message'=>'Asignación actualizada.']:['ok'=>false,'message'=>'No se pudo guardar la asignación.','error'=>$err];
        }
        $sql="INSERT INTO bridge_characters_misc_systems (character_id, misc_system_id, sort_order, notes, is_active)
              VALUES (?, ?, ?, NULLIF(?, ''), ?)";
        $st=$db->prepare($sql);
        if(!$st) return ['ok'=>false,'message'=>'No se pudo preparar el guardado.','error'=>$db->error];
        $st->bind_param('iiisi',$characterId,$miscId,$sortOrder,$notes,$isActive);
        $ok=$st->execute();$newId=(int)$st->insert_id;$err=$st->error;$st->close();
        return $ok?['ok'=>true,'id'=>$newId,'message'=>'Asignación creada.']:['ok'=>false,'message'=>'No se pudo crear la asignación.','error'=>$err];
    }
}
if (!function_exists('acmb_delete_assignment')) {
    function acmb_delete_assignment(mysqli $db, int $id): array {
        $st=$db->prepare("DELETE FROM bridge_characters_misc_systems WHERE id = ?");
        if(!$st) return ['ok'=>false,'message'=>'No se pudo preparar el borrado.','error'=>$db->error];
        $st->bind_param('i',$id);$ok=$st->execute();$err=$st->error;$st->close();
        return $ok?['ok'=>true,'id'=>$id,'message'=>'Asignación eliminada.']:['ok'=>false,'message'=>'No se pudo borrar la asignación.','error'=>$err];
    }
}
