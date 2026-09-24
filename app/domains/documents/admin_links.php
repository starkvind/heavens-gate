<?php

function adl_table_exists(mysqli $db, string $table): bool
{
    static $tables = [
        'fact_docs' => true,
        'fact_characters' => true,
        'bridge_characters_docs' => true,
        'dim_chronicles' => true,
        'dim_realities' => true,
        'dim_systems' => true,
        'bridge_characters_organizations' => true,
        'dim_organizations' => true,
        'bridge_characters_groups' => true,
        'dim_groups' => true,
    ];
    return isset($tables[$table]);
}

function adl_column_exists(mysqli $db, string $table, string $column): bool
{
    static $schema = [
        'fact_characters' => ['reality_id', 'system_id'],
        'bridge_characters_organizations' => ['is_active'],
        'bridge_characters_groups' => ['is_active'],
        'bridge_characters_docs' => ['relation_label', 'sort_order'],
    ];
    return isset($schema[$table]) && in_array($column, $schema[$table], true);
}

function adl_bind_params(mysqli_stmt $st, string $types, array &$values): bool
{
    if ($types === '') return true;
    $refs = [];
    $refs[] = $types;
    foreach ($values as $k => $v) {
        $refs[] = &$values[$k];
    }
    return (bool)call_user_func_array([$st, 'bind_param'], $refs);
}

function adl_parse_int_list($value): array
{
    $raw = [];
    if (is_array($value)) {
        $raw = $value;
    } elseif (is_string($value) && trim($value) !== '') {
        $raw = explode(',', $value);
    }
    $out = [];
    foreach ($raw as $v) {
        $id = (int)$v;
        if ($id > 0) $out[$id] = $id;
    }
    return array_values($out);
}

function adl_fetch_docs(mysqli $db, string $q = ''): array
{
    $rows = [];
    $sql = "SELECT d.id, d.title, d.pretty_id, COALESCE(c.kind, '') AS category_name
            FROM fact_docs d
            LEFT JOIN dim_doc_categories c ON c.id = d.section_id
            WHERE 1=1";
    $types = '';
    $params = [];
    $q = trim($q);
    if ($q !== '') {
        $sql .= " AND (d.title LIKE ? OR d.pretty_id LIKE ?)";
        $types .= 'ss';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " ORDER BY c.sort_order ASC, d.title ASC";
    if ($st = $db->prepare($sql)) {
        if ($types !== '') adl_bind_params($st, $types, $params);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) { $rows[] = $row; }
        $st->close();
    }
    return $rows;
}

function adl_fetch_pairs(mysqli $db, string $sql): array
{
    $rows = [];
    if ($rs = $db->query($sql)) {
        while ($row = $rs->fetch_assoc()) { $rows[] = $row; }
        $rs->close();
    }
    return $rows;
}

function adl_fetch_doc_title(mysqli $db, int $docId): string
{
    if ($docId <= 0) return '';
    $title = '';
    if ($st = $db->prepare("SELECT title FROM fact_docs WHERE id = ? LIMIT 1")) {
        $st->bind_param('i', $docId);
        $st->execute();
        $st->bind_result($title);
        $st->fetch();
        $st->close();
    }
    return (string)$title;
}

function adl_fetch_characters_for_doc(
    mysqli $db,
    int $docId,
    int $chronicleId,
    int $realityId,
    int $systemId,
    int $organizationId,
    int $groupId,
    string $q,
    bool $hasChronicles,
    bool $hasRealityFilter,
    bool $hasSystems,
    bool $hasOrgFilter,
    bool $hasGroupFilter
): array {
    if ($docId <= 0) return [];

    $chronicleNameExpr = $hasChronicles ? "COALESCE(ch.name, '') AS chronicle_name" : "'' AS chronicle_name";
    $realityJoin = $hasRealityFilter ? "LEFT JOIN dim_realities r ON r.id = c.reality_id" : "";
    $realityNameExpr = $hasRealityFilter ? "COALESCE(r.name, '') AS reality_name" : "'' AS reality_name";
    $realityIdExpr = $hasRealityFilter ? "c.reality_id" : "0";
    $systemJoin = $hasSystems ? "LEFT JOIN dim_systems ds ON ds.id = c.system_id" : "";
    $systemNameExpr = $hasSystems ? "COALESCE(ds.name, '') AS system_name" : "'' AS system_name";
    $systemIdExpr = $hasSystems ? "COALESCE(c.system_id, 0)" : "0";

    $hasOrgActiveCol = $hasOrgFilter ? adl_column_exists($db, 'bridge_characters_organizations', 'is_active') : false;
    $hasGroupActiveCol = $hasGroupFilter ? adl_column_exists($db, 'bridge_characters_groups', 'is_active') : false;
    $orgActiveWhere = $hasOrgActiveCol ? "WHERE (is_active = 1 OR is_active IS NULL)" : "";
    $groupActiveWhere = $hasGroupActiveCol ? "WHERE (is_active = 1 OR is_active IS NULL)" : "";
    $orgActiveCond = $hasOrgActiveCol ? " AND (bcof.is_active = 1 OR bcof.is_active IS NULL)" : "";
    $groupActiveCond = $hasGroupActiveCol ? " AND (bcgf.is_active = 1 OR bcgf.is_active IS NULL)" : "";

    $orgBridgeJoin = $hasOrgFilter
        ? "LEFT JOIN (
                SELECT character_id, MIN(organization_id) AS organization_id
                FROM bridge_characters_organizations
                {$orgActiveWhere}
                GROUP BY character_id
            ) bo ON bo.character_id = c.id
           LEFT JOIN dim_organizations dorg ON dorg.id = bo.organization_id"
        : "";
    $orgIdExpr = $hasOrgFilter ? "COALESCE(bo.organization_id, 0)" : "0";
    $orgNameExpr = $hasOrgFilter ? "COALESCE(dorg.name, '') AS organization_name" : "'' AS organization_name";

    $groupBridgeJoin = $hasGroupFilter
        ? "LEFT JOIN (
                SELECT character_id, MIN(group_id) AS group_id
                FROM bridge_characters_groups
                {$groupActiveWhere}
                GROUP BY character_id
            ) bg ON bg.character_id = c.id
           LEFT JOIN dim_groups dg ON dg.id = bg.group_id"
        : "";
    $groupIdExpr = $hasGroupFilter ? "COALESCE(bg.group_id, 0)" : "0";
    $groupNameExpr = $hasGroupFilter ? "COALESCE(dg.name, '') AS group_name" : "'' AS group_name";

    $hasRelLabel = adl_column_exists($db, 'bridge_characters_docs', 'relation_label');
    $hasSortOrder = adl_column_exists($db, 'bridge_characters_docs', 'sort_order');
    $relExpr = $hasRelLabel ? "COALESCE(b.relation_label, '')" : "''";
    $sortExpr = $hasSortOrder ? "COALESCE(b.sort_order, 0)" : "0";

    $sql = "SELECT
                c.id,
                c.name,
                COALESCE(c.alias, '') AS alias,
                c.chronicle_id,
                {$realityIdExpr} AS reality_id,
                {$systemIdExpr} AS system_id,
                {$orgIdExpr} AS organization_id,
                {$groupIdExpr} AS group_id,
                {$chronicleNameExpr},
                {$realityNameExpr},
                {$systemNameExpr},
                {$orgNameExpr},
                {$groupNameExpr},
                CASE WHEN b.character_id IS NULL THEN 0 ELSE 1 END AS is_linked,
                {$relExpr} AS relation_label,
                {$sortExpr} AS sort_order
            FROM fact_characters c
            LEFT JOIN bridge_characters_docs b
                   ON b.character_id = c.id
                  AND b.doc_id = ?";
    if ($hasChronicles) $sql .= " LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id";
    $sql .= " {$systemJoin}";
    $sql .= " {$realityJoin}
             {$orgBridgeJoin}
             {$groupBridgeJoin}
            WHERE 1=1";

    $types = 'i';
    $params = [$docId];

    if ($chronicleId > 0) {
        $sql .= " AND c.chronicle_id = ?";
        $types .= 'i';
        $params[] = $chronicleId;
    }
    if ($realityId > 0 && $hasRealityFilter) {
        $sql .= " AND c.reality_id = ?";
        $types .= 'i';
        $params[] = $realityId;
    }
    if ($systemId > 0 && $hasSystems) {
        $sql .= " AND c.system_id = ?";
        $types .= 'i';
        $params[] = $systemId;
    }
    if ($organizationId > 0 && $hasOrgFilter) {
        $sql .= " AND EXISTS (
                    SELECT 1
                    FROM bridge_characters_organizations bcof
                    WHERE bcof.character_id = c.id
                      AND bcof.organization_id = ?
                      {$orgActiveCond}
                )";
        $types .= 'i';
        $params[] = $organizationId;
    }
    if ($groupId > 0 && $hasGroupFilter) {
        $sql .= " AND EXISTS (
                    SELECT 1
                    FROM bridge_characters_groups bcgf
                    WHERE bcgf.character_id = c.id
                      AND bcgf.group_id = ?
                      {$groupActiveCond}
                )";
        $types .= 'i';
        $params[] = $groupId;
    }

    $q = trim($q);
    if ($q !== '') {
        $sql .= " AND (
                    c.name LIKE ?
                    OR c.alias LIKE ?
                    OR CAST(c.id AS CHAR) = ?
                    OR " . ($hasSystems ? "ds.name LIKE ?" : "'' LIKE ?") . "
                    OR " . ($hasOrgFilter ? "dorg.name LIKE ?" : "'' LIKE ?") . "
                    OR " . ($hasGroupFilter ? "dg.name LIKE ?" : "'' LIKE ?") . "
                )";
        $types .= 'ssssss';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $q;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY is_linked DESC, c.name ASC";

    $rows = [];
    if ($st = $db->prepare($sql)) {
        adl_bind_params($st, $types, $params);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) { $rows[] = $row; }
        $st->close();
    } else {
        error_log('admin_doc_links: prepare failed in adl_fetch_characters_for_doc: ' . $db->error);
    }
    return $rows;
}

function adl_sync_doc_characters(mysqli $db, int $docId, array $characterIds): array
{
    $characterIds = adl_parse_int_list($characterIds);
    $existing = [];
    if ($st = $db->prepare("SELECT character_id FROM bridge_characters_docs WHERE doc_id = ?")) {
        $st->bind_param('i', $docId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $cid = (int)($row['character_id'] ?? 0);
            if ($cid > 0) $existing[$cid] = $cid;
        }
        $st->close();
    }

    $selectedMap = [];
    foreach ($characterIds as $cid) { $selectedMap[(int)$cid] = (int)$cid; }

    $toDelete = [];
    foreach ($existing as $cid) {
        if (!isset($selectedMap[$cid])) $toDelete[] = $cid;
    }

    $toInsert = [];
    foreach ($selectedMap as $cid) {
        if (!isset($existing[$cid])) $toInsert[] = $cid;
    }

    $hasRelLabel = adl_column_exists($db, 'bridge_characters_docs', 'relation_label');
    $hasSortOrder = adl_column_exists($db, 'bridge_characters_docs', 'sort_order');

    $db->begin_transaction();
    try {
        foreach ($toDelete as $cid) {
            if ($st = $db->prepare("DELETE FROM bridge_characters_docs WHERE doc_id = ? AND character_id = ?")) {
                $st->bind_param('ii', $docId, $cid);
                $st->execute();
                $st->close();
            }
        }

        $sort = 0;
        foreach ($toInsert as $cid) {
            $sort++;
            if ($hasRelLabel && $hasSortOrder) {
                $sql = "INSERT IGNORE INTO bridge_characters_docs (character_id, doc_id, relation_label, sort_order) VALUES (?, ?, '', ?)";
                if ($st = $db->prepare($sql)) {
                    $st->bind_param('iii', $cid, $docId, $sort);
                    $st->execute();
                    $st->close();
                }
            } elseif ($hasRelLabel) {
                $sql = "INSERT IGNORE INTO bridge_characters_docs (character_id, doc_id, relation_label) VALUES (?, ?, '')";
                if ($st = $db->prepare($sql)) {
                    $st->bind_param('ii', $cid, $docId);
                    $st->execute();
                    $st->close();
                }
            } elseif ($hasSortOrder) {
                $sql = "INSERT IGNORE INTO bridge_characters_docs (character_id, doc_id, sort_order) VALUES (?, ?, ?)";
                if ($st = $db->prepare($sql)) {
                    $st->bind_param('iii', $cid, $docId, $sort);
                    $st->execute();
                    $st->close();
                }
            } else {
                $sql = "INSERT IGNORE INTO bridge_characters_docs (character_id, doc_id) VALUES (?, ?)";
                if ($st = $db->prepare($sql)) {
                    $st->bind_param('ii', $cid, $docId);
                    $st->execute();
                    $st->close();
                }
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }

    return [
        'linked_count' => count($selectedMap),
        'inserted' => count($toInsert),
        'deleted' => count($toDelete),
    ];
}
