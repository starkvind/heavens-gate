<?php
/**
 * Read-only organizational projections reused from public site relationship
 * domain. IDs and membership are returned only for existing visible
 * organizations and currently active groups, never arbitrary bridge tables.
 */
function hg_content_api_structure_handle(
    mysqli $link, string $resource, int $id, string $q,
    int $limit, int $offset, int $page, callable $send
): void {
    if (!in_array($resource, ['organizations', 'groups'], true)) {
        $send(['ok'=>false,'error'=>'unknown_resource'],404);
        return;
    }
    try {
        if ($id > 0) {
            require_once __DIR__ . '/../relationships/mobile_queries.php';
            if ($resource === 'organizations') {
                $row = hg_relationship_mobile_fetch_organization($link, $id);
                if (!$row) { $send(['ok'=>false,'error'=>'not_found'],404); return; }
                $data = array_intersect_key($row, array_flip([
                    'id', 'pretty_id', 'name', 'description', 'totem_name', 'is_npc'
                ]));
                $groups = hg_relationship_mobile_fetch_organization_groups($link, $id, true);
                $direct = hg_relationship_mobile_fetch_direct_members($link, $id, '');
                if ($groups === null || $direct === null) {
                    $send(['ok'=>false,'error'=>'query_unavailable'],503); return;
                }
                $data['active_groups'] = array_slice($groups, 0, 100);
                $data['direct_members'] = array_slice($direct, 0, 100);
                $data['groups_truncated'] = count($groups) > 100;
                $data['direct_members_truncated'] = count($direct) > 100;
            } else {
                $row = hg_relationship_mobile_fetch_group($link, $id);
                if (!$row || (int)($row['is_active'] ?? 0) !== 1) {
                    $send(['ok'=>false,'error'=>'not_found'],404); return;
                }
                $data = array_intersect_key($row, array_flip([
                    'id', 'pretty_id', 'name', 'description', 'chronicle_id',
                    'chronicle_name', 'totem_name', 'is_active'
                ]));
                $members = hg_relationship_mobile_fetch_group_members($link, $id, false, '');
                $org = hg_relationship_mobile_fetch_group_organization($link, $id);
                if ($members === null || $org === null) {
                    $send(['ok'=>false,'error'=>'query_unavailable'],503); return;
                }
                $data['organization'] = $org;
                $data['members'] = array_slice($members, 0, 100);
                $data['members_truncated'] = count($members) > 100;
            }
            $send(['ok'=>true,'resource'=>$resource,'item'=>$data]);
            return;
        }

        if ($resource === 'organizations') {
            $sql = "SELECT o.id, o.name AS title, o.description,
                           COALESCE(t.name, '') AS secondary
                    FROM dim_organizations o
                    LEFT JOIN dim_totems t ON t.id = o.totem_id";
            $filter = 'o.name';
            $order = 'o.name ASC, o.id DESC';
            $where = '';
        } else {
            $sql = "SELECT g.id, g.name AS title, g.description,
                           COALESCE(ch.name, '') AS secondary
                    FROM dim_groups g
                    LEFT JOIN dim_chronicles ch ON ch.id = g.chronicle_id";
            $filter = 'g.name';
            $order = 'g.name ASC, g.id DESC';
            $where = ' WHERE g.is_active = 1';
        }
        $parameters = [];
        if ($q !== '') {
            $where .= ($where === '' ? ' WHERE ' : ' AND ') . $filter . ' LIKE ?';
            $parameters[] = '%' . $q . '%';
        }
        $sql .= $where . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?';
        $stmt = $link->prepare($sql);
        if (!$stmt) { $send(['ok'=>false,'error'=>'query_unavailable'],503); return; }
        if ($q !== '') {
            $term = $parameters[0];
            $stmt->bind_param('sii', $term, $limit, $offset);
        } else {
            $stmt->bind_param('ii', $limit, $offset);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) { $stmt->close(); $send(['ok'=>false,'error'=>'query_unavailable'],503); return; }
        $items = [];
        while ($row = $result->fetch_assoc()) $items[] = $row;
        $result->free();
        $stmt->close();
        $send(['ok'=>true,'resource'=>$resource,'items'=>$items,'page'=>$page,
            'limit'=>$limit,'has_more'=>count($items)===$limit]);
    } catch (mysqli_sql_exception $e) {
        error_log('content_api.structure: ' . $e->getMessage());
        $send(['ok'=>false,'error'=>'query_unavailable'],503);
    }
}
