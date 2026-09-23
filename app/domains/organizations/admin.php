<?php
include_once(__DIR__ . '/../../helpers/pretty.php');

function hg_aorg_table_exists(mysqli $link, string $table): bool
{
    return function_exists('hg_table_exists') ? hg_table_exists($link, $table) : false;
}

function hg_aorg_org_url(mysqli $link, int $orgId): string
{
    return pretty_url($link, 'dim_organizations', '/organizations', $orgId);
}

function hg_aorg_chart_url(mysqli $link, int $orgId): string
{
    return rtrim(hg_aorg_org_url($link, $orgId), '/') . '/org-chart';
}

function hg_aorg_fetch_organizations(mysqli $link): array
{
    $deptCount = hg_aorg_table_exists($link, 'dim_organization_departments')
        ? "(SELECT COUNT(*) FROM dim_organization_departments d WHERE d.organization_id = o.id AND d.is_active = 1)"
        : "0";
    $roleCount = hg_aorg_table_exists($link, 'bridge_characters_org')
        ? "(SELECT COUNT(*) FROM bridge_characters_org r WHERE r.organization_id = o.id AND r.is_active = 1)"
        : "0";

    $rows = [];
    $rs = $link->query("
        SELECT o.id, o.pretty_id, o.name, o.sort_order, o.color, o.is_npc,
               $deptCount AS chart_departments,
               $roleCount AS chart_positions
        FROM dim_organizations o
        ORDER BY o.sort_order ASC, o.name ASC
    ");
    if (!$rs) {
        return $rows;
    }
    while ($row = $rs->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['sort_order'] = (int)($row['sort_order'] ?? 0);
        $row['is_npc'] = (int)($row['is_npc'] ?? 0);
        $row['chart_departments'] = (int)($row['chart_departments'] ?? 0);
        $row['chart_positions'] = (int)($row['chart_positions'] ?? 0);
        $rows[] = $row;
    }
    $rs->close();
    return $rows;
}

function hg_aorg_fetch_organization(mysqli $link, int $orgId): ?array
{
    if ($orgId <= 0) {
        return null;
    }
    $stmt = $link->prepare("
        SELECT id, pretty_id, name, sort_order, totem_id, color, is_npc, description
        FROM dim_organizations
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $orgId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row)) {
        return null;
    }
    $row['id'] = (int)$row['id'];
    $row['sort_order'] = (int)($row['sort_order'] ?? 0);
    $row['totem_id'] = (int)($row['totem_id'] ?? 0);
    $row['is_npc'] = (int)($row['is_npc'] ?? 0);
    return $row;
}

function hg_aorg_fetch_totems(mysqli $link): array
{
    $rows = [0 => 'Sin totem'];
    $rs = $link->query("SELECT id, name FROM dim_totems ORDER BY name ASC");
    if (!$rs) {
        return $rows;
    }
    while ($row = $rs->fetch_assoc()) {
        $rows[(int)$row['id']] = (string)$row['name'];
    }
    $rs->close();
    return $rows;
}

function hg_aorg_fetch_departments(mysqli $link, int $orgId, bool $activeOnly = false): array
{
    if ($orgId <= 0 || !hg_aorg_table_exists($link, 'dim_organization_departments')) {
        return [];
    }
    $activeSql = $activeOnly ? "AND d.is_active = 1" : "";
    $stmt = $link->prepare("
        SELECT d.id, d.parent_department_id, d.pretty_id, d.name, d.department_type,
               d.hierarchy_level, d.color, d.description, d.sort_order, d.is_active,
               COALESCE(p.name, '') AS parent_name
        FROM dim_organization_departments d
            LEFT JOIN dim_organization_departments p ON p.id = d.parent_department_id
        WHERE d.organization_id = ?
          $activeSql
        ORDER BY d.hierarchy_level ASC, d.sort_order ASC, d.name ASC
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $orgId);
    $stmt->execute();
    $rows = [];
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['parent_department_id'] = (int)($row['parent_department_id'] ?? 0);
        $row['hierarchy_level'] = (int)($row['hierarchy_level'] ?? 0);
        $row['sort_order'] = (int)($row['sort_order'] ?? 0);
        $row['is_active'] = (int)($row['is_active'] ?? 0);
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function hg_aorg_fetch_positions(mysqli $link, int $orgId): array
{
    if ($orgId <= 0 || !hg_aorg_table_exists($link, 'bridge_characters_org')) {
        return [];
    }
    $stmt = $link->prepare("
        SELECT r.id, r.character_id, r.department_id, r.parent_bridge_id, r.hierarchy_level,
               r.position_name, r.position_code, r.scope_label, r.responsibility,
               r.is_head, r.is_primary, r.is_active, r.sort_order,
               COALESCE(c.name, '') AS character_name,
               COALESCE(c.alias, '') AS character_alias,
               COALESCE(d.name, '') AS department_name,
               COALESCE(p.name, '') AS parent_character_name,
               COALESCE(pr.position_name, '') AS parent_position_name
        FROM bridge_characters_org r
            LEFT JOIN fact_characters c ON c.id = r.character_id
            LEFT JOIN dim_organization_departments d ON d.id = r.department_id
            LEFT JOIN bridge_characters_org pr ON pr.id = r.parent_bridge_id
            LEFT JOIN fact_characters p ON p.id = pr.character_id
        WHERE r.organization_id = ?
        ORDER BY r.is_active DESC, r.hierarchy_level ASC, r.sort_order ASC, r.position_name ASC, r.id ASC
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $orgId);
    $stmt->execute();
    $rows = [];
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['character_id'] = (int)($row['character_id'] ?? 0);
        $row['department_id'] = (int)($row['department_id'] ?? 0);
        $row['parent_bridge_id'] = (int)($row['parent_bridge_id'] ?? 0);
        $row['hierarchy_level'] = (int)($row['hierarchy_level'] ?? 0);
        $row['is_head'] = (int)($row['is_head'] ?? 0);
        $row['is_primary'] = (int)($row['is_primary'] ?? 0);
        $row['is_active'] = (int)($row['is_active'] ?? 0);
        $row['sort_order'] = (int)($row['sort_order'] ?? 0);
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function hg_aorg_fetch_characters(mysqli $link, int $orgId): array
{
    if ($orgId <= 0) {
        return [];
    }

    $sources = [];
    $params = [];
    $types = '';

    if (hg_aorg_table_exists($link, 'bridge_characters_organizations')) {
        $sources[] = "
            SELECT bco.character_id, 'Organizacion' AS source_label
            FROM bridge_characters_organizations bco
            WHERE bco.organization_id = ?
              AND (bco.is_active = 1 OR bco.is_active IS NULL)
        ";
        $params[] = $orgId;
        $types .= 'i';
    }

    if (hg_aorg_table_exists($link, 'bridge_characters_groups') && hg_aorg_table_exists($link, 'bridge_organizations_groups')) {
        $sources[] = "
            SELECT bcg.character_id, 'Grupo' AS source_label
            FROM bridge_characters_groups bcg
                INNER JOIN bridge_organizations_groups bog
                    ON bog.group_id = bcg.group_id
                   AND bog.organization_id = ?
                   AND (bog.is_active = 1 OR bog.is_active IS NULL)
            WHERE (bcg.is_active = 1 OR bcg.is_active IS NULL)
        ";
        $params[] = $orgId;
        $types .= 'i';
    }

    if (empty($sources)) {
        return [];
    }

    $sourceSql = implode("\nUNION ALL\n", $sources);
    $rows = [];
    $stmt = $link->prepare("
        SELECT c.id, c.name, c.alias, c.garou_name,
               GROUP_CONCAT(DISTINCT src.source_label ORDER BY src.source_label SEPARATOR ', ') AS org_source
        FROM fact_characters c
            INNER JOIN ($sourceSql) src ON src.character_id = c.id
        GROUP BY c.id, c.name, c.alias, c.garou_name
        ORDER BY c.name ASC
    ");
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function hg_aorg_character_belongs(mysqli $link, int $orgId, int $characterId): bool
{
    if ($characterId <= 0) {
        return true;
    }
    if ($orgId <= 0) {
        return false;
    }

    if (hg_aorg_table_exists($link, 'bridge_characters_organizations')) {
        $stmt = $link->prepare("
            SELECT 1
            FROM bridge_characters_organizations
            WHERE organization_id = ?
              AND character_id = ?
              AND (is_active = 1 OR is_active IS NULL)
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('ii', $orgId, $characterId);
            $stmt->execute();
            $ok = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($ok) {
                return true;
            }
        }
    }

    if (hg_aorg_table_exists($link, 'bridge_characters_groups') && hg_aorg_table_exists($link, 'bridge_organizations_groups')) {
        $stmt = $link->prepare("
            SELECT 1
            FROM bridge_characters_groups bcg
                INNER JOIN bridge_organizations_groups bog
                    ON bog.group_id = bcg.group_id
                   AND bog.organization_id = ?
                   AND (bog.is_active = 1 OR bog.is_active IS NULL)
            WHERE bcg.character_id = ?
              AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('ii', $orgId, $characterId);
            $stmt->execute();
            $ok = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($ok) {
                return true;
            }
        }
    }

    return false;
}

function hg_aorg_department_belongs(mysqli $link, int $orgId, int $departmentId): bool
{
    if ($departmentId <= 0) {
        return true;
    }
    $stmt = $link->prepare("SELECT id FROM dim_organization_departments WHERE organization_id = ? AND id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $orgId, $departmentId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

function hg_aorg_position_belongs(mysqli $link, int $orgId, int $positionId): bool
{
    if ($positionId <= 0) {
        return true;
    }
    $stmt = $link->prepare("SELECT id FROM bridge_characters_org WHERE organization_id = ? AND id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $orgId, $positionId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

function hg_aorg_department_parent_allowed(mysqli $link, int $orgId, int $departmentId, int $parentId): bool
{
    if ($parentId <= 0) {
        return true;
    }
    if ($departmentId > 0 && $departmentId === $parentId) {
        return false;
    }
    if (!hg_aorg_department_belongs($link, $orgId, $parentId)) {
        return false;
    }
    $seen = [];
    $current = $parentId;
    while ($current > 0 && !isset($seen[$current])) {
        if ($current === $departmentId) {
            return false;
        }
        $seen[$current] = true;
        $stmt = $link->prepare("SELECT parent_department_id FROM dim_organization_departments WHERE organization_id = ? AND id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $orgId, $current);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $current = (int)($row['parent_department_id'] ?? 0);
    }
    return true;
}

function hg_aorg_position_parent_allowed(mysqli $link, int $orgId, int $positionId, int $parentId): bool
{
    if ($parentId <= 0) {
        return true;
    }
    if ($positionId > 0 && $positionId === $parentId) {
        return false;
    }
    if (!hg_aorg_position_belongs($link, $orgId, $parentId)) {
        return false;
    }
    $seen = [];
    $current = $parentId;
    while ($current > 0 && !isset($seen[$current])) {
        if ($current === $positionId) {
            return false;
        }
        $seen[$current] = true;
        $stmt = $link->prepare("SELECT parent_bridge_id FROM bridge_characters_org WHERE organization_id = ? AND id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $orgId, $current);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $current = (int)($row['parent_bridge_id'] ?? 0);
    }
    return true;
}

function hg_aorg_next_sort(mysqli $link, string $table, int $orgId, string $scopeColumn, ?int $scopeId): int
{
    $safeTable = str_replace('`', '``', $table);
    if ($scopeId === null) {
        $sql = "SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_sort FROM `$safeTable` WHERE organization_id = ? AND `$scopeColumn` IS NULL";
        $stmt = $link->prepare($sql);
        if (!$stmt) {
            return 10;
        }
        $stmt->bind_param('i', $orgId);
    } else {
        $sql = "SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_sort FROM `$safeTable` WHERE organization_id = ? AND `$scopeColumn` = ?";
        $stmt = $link->prepare($sql);
        if (!$stmt) {
            return 10;
        }
        $stmt->bind_param('ii', $orgId, $scopeId);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return max(10, (int)($row['next_sort'] ?? 10));
}

function hg_aorg_unique_department_pretty(mysqli $link, int $orgId, string $name, int $departmentId = 0): string
{
    $base = function_exists('slugify_pretty_id') ? slugify_pretty_id($name) : strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
    if ($base === '') {
        $base = 'categoria';
    }
    $pretty = $base;
    $suffix = 2;
    while (true) {
        $stmt = $link->prepare("SELECT id FROM dim_organization_departments WHERE organization_id = ? AND pretty_id = ? AND id <> ? LIMIT 1");
        if (!$stmt) {
            return $pretty;
        }
        $stmt->bind_param('isi', $orgId, $pretty, $departmentId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            return $pretty;
        }
        $pretty = $base . '-' . $suffix;
        $suffix++;
    }
}



if (!function_exists('hg_aorg_update_org')) {
    function hg_aorg_update_org(mysqli $link, int $orgId, string $name, int $sortOrder, int $totemId, string $color, int $isNpc, string $description): array {
        $stmt=$link->prepare("UPDATE dim_organizations
            SET name = ?, sort_order = ?, totem_id = NULLIF(?, 0), color = ?, is_npc = ?, description = ?
            WHERE id = ?");
        if(!$stmt) return ['ok'=>false,'message'=>'No se pudo preparar la actualizacion.','error'=>$link->error];
        $stmt->bind_param('siisisi',$name,$sortOrder,$totemId,$color,$isNpc,$description,$orgId);
        $ok=$stmt->execute();$err=$stmt->error;$stmt->close();
        if($ok) hg_update_pretty_id_if_exists($link,'dim_organizations',$orgId,$name);
        return $ok?['ok'=>true]:['ok'=>false,'message'=>'No se pudo actualizar la organizacion.','error'=>$err];
    }
}
if (!function_exists('hg_aorg_save_department')) {
    function hg_aorg_save_department(mysqli $link, int $orgId, int $departmentId, ?int $parentId, string $pretty, string $name, string $type, int $level, string $color, string $description, int $sortOrder, int $isActive): array {
        if($departmentId>0){
            $stmt=$link->prepare("UPDATE dim_organization_departments
                SET parent_department_id = ?, pretty_id = ?, name = ?, department_type = ?, hierarchy_level = ?,
                    color = ?, description = ?, sort_order = ?, is_active = ?
                WHERE id = ? AND organization_id = ?");
            if(!$stmt)return ['ok'=>false,'message'=>'No se pudo preparar la categoria.','error'=>$link->error];
            $stmt->bind_param('isssissiiii',$parentId,$pretty,$name,$type,$level,$color,$description,$sortOrder,$isActive,$departmentId,$orgId);
        } else {
            $stmt=$link->prepare("INSERT INTO dim_organization_departments
                (organization_id, parent_department_id, pretty_id, name, department_type, hierarchy_level, color, description, sort_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if(!$stmt)return ['ok'=>false,'message'=>'No se pudo preparar la categoria.','error'=>$link->error];
            $stmt->bind_param('iisssissii',$orgId,$parentId,$pretty,$name,$type,$level,$color,$description,$sortOrder,$isActive);
        }
        $ok=$stmt->execute();$err=$stmt->error;$newId=$departmentId>0?$departmentId:(int)$stmt->insert_id;$stmt->close();
        return $ok?['ok'=>true,'id'=>$newId]:['ok'=>false,'message'=>'No se pudo guardar la categoria.','error'=>$err];
    }
}
if (!function_exists('hg_aorg_assign_position_character')) {
    function hg_aorg_assign_position_character(mysqli $link, int $orgId, int $positionId, ?int $characterId, int $isActive): array {
        $stmt=$link->prepare("UPDATE bridge_characters_org SET character_id = ?, is_active = ? WHERE id = ? AND organization_id = ?");
        if(!$stmt)return ['ok'=>false,'message'=>'No se pudo preparar la asignacion.','error'=>$link->error];
        $stmt->bind_param('iiii',$characterId,$isActive,$positionId,$orgId);
        $ok=$stmt->execute();$err=$stmt->error;$stmt->close();
        return $ok?['ok'=>true]:['ok'=>false,'message'=>'No se pudo asignar el personaje. Revisa si bridge_characters_org.character_id permite NULL.','error'=>$err];
    }
}
if (!function_exists('hg_aorg_save_position')) {
    function hg_aorg_save_position(mysqli $link, int $orgId, int $positionId, ?int $characterId, int $departmentId, ?int $parentId, int $level, string $positionName, string $positionCode, string $scopeLabel, string $responsibility, int $isHead, int $isPrimary, int $isActive, int $sortOrder): array {
        if($positionId>0){
            $stmt=$link->prepare("UPDATE bridge_characters_org
                SET department_id = ?, parent_bridge_id = ?, hierarchy_level = ?, position_name = ?, position_code = ?,
                    scope_label = ?, responsibility = ?, is_head = ?, is_primary = ?, is_active = ?, sort_order = ?
                WHERE id = ? AND organization_id = ?");
            if(!$stmt)return ['ok'=>false,'message'=>'No se pudo preparar el cargo.','error'=>$link->error];
            $stmt->bind_param('iiissssiiiiii',$departmentId,$parentId,$level,$positionName,$positionCode,$scopeLabel,$responsibility,$isHead,$isPrimary,$isActive,$sortOrder,$positionId,$orgId);
        } else {
            $stmt=$link->prepare("INSERT INTO bridge_characters_org
                (character_id, organization_id, department_id, parent_bridge_id, hierarchy_level, position_name,
                 position_code, scope_label, responsibility, is_head, is_primary, is_active, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if(!$stmt)return ['ok'=>false,'message'=>'No se pudo preparar el cargo.','error'=>$link->error];
            $stmt->bind_param('iiiiissssiiii',$characterId,$orgId,$departmentId,$parentId,$level,$positionName,$positionCode,$scopeLabel,$responsibility,$isHead,$isPrimary,$isActive,$sortOrder);
        }
        $ok=$stmt->execute();$err=$stmt->error;$newId=$positionId>0?$positionId:(int)$stmt->insert_id;$stmt->close();
        return $ok?['ok'=>true,'id'=>$newId]:['ok'=>false,'message'=>'No se pudo guardar el cargo. Revisa si bridge_characters_org.character_id permite NULL.','error'=>$err];
    }
}
