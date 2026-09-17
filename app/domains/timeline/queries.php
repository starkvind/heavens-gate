<?php

/**
 * Database access for public timeline/event views.
 * Presentation, filtering UI and URL formatting stay in controllers/views.
 */

if (!function_exists('hg_timeline_table_exists')) {
    function hg_timeline_table_exists(mysqli $link, string $table): bool
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') return false;
        if (array_key_exists($table, $cache)) return $cache[$table];

        $stmt = $link->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        if (!$stmt) return $cache[$table] = false;
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $cache[$table] = ((int)$count > 0);
    }
}

if (!function_exists('hg_timeline_column_exists')) {
    function hg_timeline_column_exists(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') return false;
        $key = $table . ':' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];

        $stmt = $link->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        if (!$stmt) return $cache[$key] = false;
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $cache[$key] = ((int)$count > 0);
    }
}

if (!function_exists('hg_timeline_normalize_ids')) {
    function hg_timeline_normalize_ids($ids): array
    {
        if (!is_array($ids)) {
            $ids = preg_split('/\s*,\s*/', trim((string)$ids)) ?: [];
        }
        $out = [];
        foreach ($ids as $id) {
            if (!is_scalar($id) || !preg_match('/^\d+$/', trim((string)$id))) continue;
            $id = (int)$id;
            if ($id > 0) $out[$id] = $id;
        }
        return array_values($out);
    }
}

if (!function_exists('hg_timeline_bind')) {
    function hg_timeline_bind(mysqli_stmt $stmt, string $types, array &$params): void
    {
        if ($types !== '') $stmt->bind_param($types, ...$params);
    }
}

if (!function_exists('hg_timeline_fetch_all')) {
    function hg_timeline_fetch_all(mysqli_stmt $stmt): ?array
    {
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $result = $stmt->get_result();
        if (!($result instanceof mysqli_result)) {
            $stmt->close();
            return null;
        }
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_timeline_fetch_index_rows')) {
    function hg_timeline_fetch_index_rows(mysqli $link): ?array
    {
        if (!hg_timeline_table_exists($link, 'fact_timeline_events')) return null;

        $hasPretty = hg_timeline_column_exists($link, 'fact_timeline_events', 'pretty_id');
        $hasSortDate = hg_timeline_column_exists($link, 'fact_timeline_events', 'sort_date');
        $hasDatePrecision = hg_timeline_column_exists($link, 'fact_timeline_events', 'date_precision');
        $hasDateNote = hg_timeline_column_exists($link, 'fact_timeline_events', 'date_note');
        $hasLocation = hg_timeline_column_exists($link, 'fact_timeline_events', 'location');
        $hasSource = hg_timeline_column_exists($link, 'fact_timeline_events', 'source');
        $hasTimeline = hg_timeline_column_exists($link, 'fact_timeline_events', 'timeline');
        $hasActive = hg_timeline_column_exists($link, 'fact_timeline_events', 'is_active');
        $hasTypeId = hg_timeline_column_exists($link, 'fact_timeline_events', 'event_type_id');

        $hasTypes = hg_timeline_table_exists($link, 'dim_timeline_events_types');
        $hasTypeColor = $hasTypes && hg_timeline_column_exists($link, 'dim_timeline_events_types', 'color_hex');
        $hasChronBridge = hg_timeline_table_exists($link, 'bridge_timeline_events_chronicles') && hg_timeline_table_exists($link, 'dim_chronicles');
        $hasRealBridge = hg_timeline_table_exists($link, 'bridge_timeline_events_realities') && hg_timeline_table_exists($link, 'dim_realities');

        $selectPretty = $hasPretty ? 'e.pretty_id AS pretty_id' : 'CAST(e.id AS CHAR) AS pretty_id';
        $selectSortDate = $hasSortDate ? 'e.sort_date AS sort_date' : 'e.event_date AS sort_date';
        $selectDatePrecision = $hasDatePrecision ? 'e.date_precision AS date_precision' : "'day' AS date_precision";
        $selectDateNote = $hasDateNote ? 'e.date_note AS date_note' : 'NULL AS date_note';
        $selectLocation = $hasLocation ? 'e.location AS location' : 'NULL AS location';
        $selectSource = $hasSource ? 'e.source AS source' : 'NULL AS source';
        $selectTimeline = $hasTimeline ? 'e.timeline AS timeline' : 'NULL AS timeline';

        $typeJoin = '';
        if ($hasTypes && $hasTypeId) {
            $typeJoin = 'LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id';
            $typeSlugExpr = "COALESCE(t.pretty_id, 'evento')";
            $typeNameExpr = "COALESCE(t.name, 'Evento')";
            $typeColorExpr = $hasTypeColor ? "NULLIF(TRIM(t.color_hex), '')" : 'NULL';
        } else {
            $typeSlugExpr = "'evento'";
            $typeNameExpr = "'Evento'";
            $typeColorExpr = 'NULL';
        }

        $chronicleJoin = '';
        $chronicleRefsExpr = "'' AS chronicle_refs";
        $chronicleLineExpr = $hasTimeline
            ? "COALESCE(NULLIF(TRIM(e.timeline), ''), '-') AS chronicle_line"
            : "'-' AS chronicle_line";
        if ($hasChronBridge) {
            $order = [];
            if (hg_timeline_column_exists($link, 'bridge_timeline_events_chronicles', 'sort_order')) $order[] = 'bec.sort_order ASC';
            if (hg_timeline_column_exists($link, 'dim_chronicles', 'sort_order')) $order[] = 'c.sort_order ASC';
            $order[] = 'c.name ASC';
            $chronOrder = implode(', ', $order);
            $chronicleJoin = "
                LEFT JOIN (
                    SELECT bec.event_id,
                           GROUP_CONCAT(DISTINCT CONCAT(c.id, '::', c.name) ORDER BY {$chronOrder} SEPARATOR '||') AS chronicle_refs,
                           GROUP_CONCAT(DISTINCT c.name ORDER BY {$chronOrder} SEPARATOR ' | ') AS chronicle_line
                    FROM bridge_timeline_events_chronicles bec
                    INNER JOIN dim_chronicles c ON c.id = bec.chronicle_id
                    GROUP BY bec.event_id
                ) chr ON chr.event_id = e.id";
            $chronicleRefsExpr = "COALESCE(chr.chronicle_refs, '') AS chronicle_refs";
            $chronicleLineExpr = $hasTimeline
                ? "COALESCE(NULLIF(chr.chronicle_line, ''), e.timeline, '-') AS chronicle_line"
                : "COALESCE(NULLIF(chr.chronicle_line, ''), '-') AS chronicle_line";
        }

        $realityJoin = '';
        $realityRefsExpr = "'' AS reality_refs";
        $realityLineExpr = "'-' AS reality_line";
        if ($hasRealBridge) {
            $order = [];
            if (hg_timeline_column_exists($link, 'bridge_timeline_events_realities', 'sort_order')) $order[] = 'ber.sort_order ASC';
            if (hg_timeline_column_exists($link, 'dim_realities', 'sort_order')) $order[] = 'r.sort_order ASC';
            $order[] = 'r.name ASC';
            $realOrder = implode(', ', $order);
            $realityJoin = "
                LEFT JOIN (
                    SELECT ber.event_id,
                           GROUP_CONCAT(DISTINCT CONCAT(r.id, '::', r.name) ORDER BY {$realOrder} SEPARATOR '||') AS reality_refs,
                           GROUP_CONCAT(DISTINCT r.name ORDER BY {$realOrder} SEPARATOR ' | ') AS reality_line
                    FROM bridge_timeline_events_realities ber
                    INNER JOIN dim_realities r ON r.id = ber.reality_id
                    GROUP BY ber.event_id
                ) rel ON rel.event_id = e.id";
            $realityRefsExpr = "COALESCE(rel.reality_refs, '') AS reality_refs";
            $realityLineExpr = "COALESCE(NULLIF(rel.reality_line, ''), '-') AS reality_line";
        }

        $where = $hasActive ? 'WHERE e.is_active = 1' : '';
        $orderExpr = $hasSortDate ? 'COALESCE(e.sort_date, e.event_date)' : 'e.event_date';
        $sql = "SELECT e.id, {$selectPretty}, e.event_date, {$selectSortDate}, {$selectDatePrecision}, {$selectDateNote},
                       e.title, e.description, {$selectLocation}, {$selectSource}, {$selectTimeline},
                       {$typeSlugExpr} AS type_slug, {$typeNameExpr} AS type_name, {$typeColorExpr} AS type_color,
                       {$chronicleRefsExpr}, {$chronicleLineExpr}, {$realityRefsExpr}, {$realityLineExpr}
                FROM fact_timeline_events e
                {$typeJoin}
                {$chronicleJoin}
                {$realityJoin}
                {$where}
                ORDER BY {$orderExpr} ASC, e.id ASC";
        $result = $link->query($sql);
        if (!($result instanceof mysqli_result)) return [];
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        return $rows;
    }
}

if (!function_exists('hg_timeline_resolve_event_id')) {
    function hg_timeline_resolve_event_id(mysqli $link, string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '') return 0;
        if (preg_match('/^\d+$/', $raw)) return (int)$raw;
        return function_exists('resolve_pretty_id') ? (int)(resolve_pretty_id($link, 'fact_timeline_events', $raw) ?? 0) : 0;
    }
}

if (!function_exists('hg_timeline_fetch_event')) {
    function hg_timeline_fetch_event(mysqli $link, int $eventId): ?array
    {
        if ($eventId <= 0 || !hg_timeline_table_exists($link, 'fact_timeline_events')) return null;
        $hasPretty = hg_timeline_column_exists($link, 'fact_timeline_events', 'pretty_id');
        $hasSortDate = hg_timeline_column_exists($link, 'fact_timeline_events', 'sort_date');
        $hasDatePrecision = hg_timeline_column_exists($link, 'fact_timeline_events', 'date_precision');
        $hasDateNote = hg_timeline_column_exists($link, 'fact_timeline_events', 'date_note');
        $hasLocation = hg_timeline_column_exists($link, 'fact_timeline_events', 'location');
        $hasSource = hg_timeline_column_exists($link, 'fact_timeline_events', 'source');
        $hasTimeline = hg_timeline_column_exists($link, 'fact_timeline_events', 'timeline');
        $hasActive = hg_timeline_column_exists($link, 'fact_timeline_events', 'is_active');
        $hasTypeId = hg_timeline_column_exists($link, 'fact_timeline_events', 'event_type_id');
        $hasTypes = hg_timeline_table_exists($link, 'dim_timeline_events_types');

        $selectPretty = $hasPretty ? 'e.pretty_id AS pretty_id' : 'CAST(e.id AS CHAR) AS pretty_id';
        $selectSortDate = $hasSortDate ? 'e.sort_date AS sort_date' : 'e.event_date AS sort_date';
        $selectDatePrecision = $hasDatePrecision ? 'e.date_precision AS date_precision' : "'day' AS date_precision";
        $selectDateNote = $hasDateNote ? 'e.date_note AS date_note' : 'NULL AS date_note';
        $selectLocation = $hasLocation ? 'e.location AS location' : 'NULL AS location';
        $selectSource = $hasSource ? 'e.source AS source' : 'NULL AS source';
        $selectTimeline = $hasTimeline ? 'e.timeline AS timeline' : 'NULL AS timeline';
        $selectActive = $hasActive ? 'e.is_active AS is_active' : '1 AS is_active';
        $typeJoin = '';
        if ($hasTypes && $hasTypeId) {
            $typeJoin = 'LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id';
            $typeName = "COALESCE(t.name, 'Evento')";
            $typeSlug = "COALESCE(t.pretty_id, 'evento')";
        } else {
            $typeName = "'Evento'";
            $typeSlug = "'evento'";
        }

        $sql = "SELECT e.id, {$selectPretty}, e.title, e.description, e.event_date, {$selectSortDate},
                       {$selectDatePrecision}, {$selectDateNote}, {$selectLocation}, {$selectSource}, {$selectTimeline},
                       {$selectActive}, {$typeName} AS type_name, {$typeSlug} AS type_slug
                FROM fact_timeline_events e {$typeJoin}
                WHERE e.id = ? LIMIT 1";
        $stmt = $link->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $eventId);
        $rows = hg_timeline_fetch_all($stmt);
        return $rows[0] ?? null;
    }
}

if (!function_exists('hg_timeline_fetch_event_chronicles')) {
    function hg_timeline_fetch_event_chronicles(mysqli $link, int $eventId, array $excludedChronicleIds = []): ?array
    {
        if (!hg_timeline_table_exists($link, 'bridge_timeline_events_chronicles') || !hg_timeline_table_exists($link, 'dim_chronicles')) return [];
        $order = [];
        if (hg_timeline_column_exists($link, 'bridge_timeline_events_chronicles', 'sort_order')) $order[] = 'b.sort_order ASC';
        if (hg_timeline_column_exists($link, 'dim_chronicles', 'sort_order')) $order[] = 'c.sort_order ASC';
        $order[] = 'c.name ASC';
        $sql = 'SELECT c.id, c.name, c.pretty_id FROM bridge_timeline_events_chronicles b INNER JOIN dim_chronicles c ON c.id = b.chronicle_id WHERE b.event_id = ?';
        $types = 'i';
        $params = [$eventId];
        $excludedChronicleIds = hg_timeline_normalize_ids($excludedChronicleIds);
        if ($excludedChronicleIds) {
            $sql .= ' AND c.id NOT IN (' . implode(',', array_fill(0, count($excludedChronicleIds), '?')) . ')';
            $types .= str_repeat('i', count($excludedChronicleIds));
            foreach ($excludedChronicleIds as $id) $params[] = $id;
        }
        $sql .= ' ORDER BY ' . implode(', ', $order);
        $stmt = $link->prepare($sql);
        if (!$stmt) return null;
        hg_timeline_bind($stmt, $types, $params);
        return hg_timeline_fetch_all($stmt);
    }
}

if (!function_exists('hg_timeline_fetch_event_participants')) {
    function hg_timeline_fetch_event_participants(mysqli $link, int $eventId, array $excludedChronicleIds = []): ?array
    {
        if (!hg_timeline_table_exists($link, 'bridge_timeline_events_characters') || !hg_timeline_table_exists($link, 'fact_characters')) return [];
        $roleExpr = hg_timeline_column_exists($link, 'bridge_timeline_events_characters', 'role_label') ? 'b.role_label' : 'NULL';
        $kindExpr = function_exists('hg_character_kind_select') ? hg_character_kind_select($link, 'c') : "''";
        $order = [];
        if (hg_timeline_column_exists($link, 'bridge_timeline_events_characters', 'sort_order')) $order[] = 'b.sort_order ASC';
        $order[] = 'c.name ASC';
        $order[] = 'c.id ASC';
        $sql = "SELECT c.id, c.name, c.pretty_id, c.alias, c.image_url, c.gender,
                       COALESCE(dcs.label, '') AS status, {$kindExpr} AS character_kind, {$roleExpr} AS role_label
                FROM bridge_timeline_events_characters b
                INNER JOIN fact_characters c ON c.id = b.character_id
                LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id
                WHERE b.event_id = ?";
        $types = 'i';
        $params = [$eventId];
        $excludedChronicleIds = hg_timeline_normalize_ids($excludedChronicleIds);
        if ($excludedChronicleIds) {
            $sql .= ' AND c.chronicle_id NOT IN (' . implode(',', array_fill(0, count($excludedChronicleIds), '?')) . ')';
            $types .= str_repeat('i', count($excludedChronicleIds));
            foreach ($excludedChronicleIds as $id) $params[] = $id;
        }
        $sql .= ' ORDER BY ' . implode(', ', $order);
        $stmt = $link->prepare($sql);
        if (!$stmt) return null;
        hg_timeline_bind($stmt, $types, $params);
        return hg_timeline_fetch_all($stmt);
    }
}

if (!function_exists('hg_timeline_fetch_event_chapters')) {
    function hg_timeline_fetch_event_chapters(mysqli $link, int $eventId, array $excludedChronicleIds = []): ?array
    {
        if (!hg_timeline_table_exists($link, 'bridge_timeline_events_chapters') || !hg_timeline_table_exists($link, 'dim_chapters')) return [];
        $hasSeasons = hg_timeline_table_exists($link, 'dim_seasons');
        $seasonJoin = $hasSeasons ? 'LEFT JOIN dim_seasons s ON s.id = c.season_id' : '';
        $seasonSelect = $hasSeasons
            ? "s.name AS season_name, s.season_number, COALESCE(s.season_kind, 'temporada') AS season_kind"
            : "NULL AS season_name, NULL AS season_number, 'temporada' AS season_kind";
        $order = [];
        if (hg_timeline_column_exists($link, 'bridge_timeline_events_chapters', 'sort_order')) $order[] = 'b.sort_order ASC';
        $order[] = $hasSeasons ? 'COALESCE(s.sort_order, 9999) ASC' : 'c.chapter_number ASC';
        $order[] = 'c.chapter_number ASC';
        $order[] = 'c.id ASC';
        $sql = "SELECT c.id, c.name, c.pretty_id, c.chapter_number, c.season_id, {$seasonSelect}
                FROM bridge_timeline_events_chapters b
                INNER JOIN dim_chapters c ON c.id = b.chapter_id
                {$seasonJoin}
                WHERE b.event_id = ?";
        $types = 'i';
        $params = [$eventId];
        $excludedChronicleIds = hg_timeline_normalize_ids($excludedChronicleIds);
        if ($hasSeasons && $excludedChronicleIds && hg_timeline_column_exists($link, 'dim_seasons', 'chronicle_id')) {
            $sql .= ' AND (s.id IS NULL OR s.chronicle_id NOT IN (' . implode(',', array_fill(0, count($excludedChronicleIds), '?')) . '))';
            $types .= str_repeat('i', count($excludedChronicleIds));
            foreach ($excludedChronicleIds as $id) $params[] = $id;
        }
        $sql .= ' ORDER BY ' . implode(', ', array_unique($order));
        $stmt = $link->prepare($sql);
        if (!$stmt) return null;
        hg_timeline_bind($stmt, $types, $params);
        return hg_timeline_fetch_all($stmt);
    }
}

if (!function_exists('hg_timeline_fetch_event_realities')) {
    function hg_timeline_fetch_event_realities(mysqli $link, int $eventId): ?array
    {
        if (!hg_timeline_table_exists($link, 'bridge_timeline_events_realities') || !hg_timeline_table_exists($link, 'dim_realities')) return [];
        $order = [];
        if (hg_timeline_column_exists($link, 'bridge_timeline_events_realities', 'sort_order')) $order[] = 'b.sort_order ASC';
        if (hg_timeline_column_exists($link, 'dim_realities', 'sort_order')) $order[] = 'r.sort_order ASC';
        $order[] = 'r.name ASC';
        $stmt = $link->prepare('SELECT r.id, r.name, r.pretty_id FROM bridge_timeline_events_realities b INNER JOIN dim_realities r ON r.id = b.reality_id WHERE b.event_id = ? ORDER BY ' . implode(', ', $order));
        if (!$stmt) return null;
        $stmt->bind_param('i', $eventId);
        return hg_timeline_fetch_all($stmt);
    }
}

if (!function_exists('hg_timeline_fetch_neighbor')) {
    function hg_timeline_fetch_neighbor(mysqli $link, int $eventId, string $anchorDate, string $direction, array $excludedChronicleIds = []): ?array
    {
        $hasPretty = hg_timeline_column_exists($link, 'fact_timeline_events', 'pretty_id');
        $hasSortDate = hg_timeline_column_exists($link, 'fact_timeline_events', 'sort_date');
        $hasActive = hg_timeline_column_exists($link, 'fact_timeline_events', 'is_active');
        $sortExpr = $hasSortDate ? 'COALESCE(e.sort_date, e.event_date)' : 'e.event_date';
        $prettyExpr = $hasPretty ? 'e.pretty_id' : 'CAST(e.id AS CHAR)';
        $op = $direction === 'prev' ? '<' : '>';
        $order = $direction === 'prev' ? 'DESC' : 'ASC';
        $sql = "SELECT e.id, {$prettyExpr} AS pretty_id, e.title FROM fact_timeline_events e WHERE ";
        if ($hasActive) $sql .= 'e.is_active = 1 AND ';
        $sql .= "({$sortExpr} {$op} ? OR ({$sortExpr} = ? AND e.id {$op} ?))";
        $types = 'ssi';
        $params = [$anchorDate, $anchorDate, $eventId];
        $excludedChronicleIds = hg_timeline_normalize_ids($excludedChronicleIds);
        if ($excludedChronicleIds && hg_timeline_table_exists($link, 'bridge_timeline_events_chronicles')) {
            $sql .= ' AND NOT EXISTS (SELECT 1 FROM bridge_timeline_events_chronicles bx WHERE bx.event_id = e.id AND bx.chronicle_id IN (' . implode(',', array_fill(0, count($excludedChronicleIds), '?')) . '))';
            $types .= str_repeat('i', count($excludedChronicleIds));
            foreach ($excludedChronicleIds as $id) $params[] = $id;
        }
        $sql .= " ORDER BY {$sortExpr} {$order}, e.id {$order} LIMIT 1";
        $stmt = $link->prepare($sql);
        if (!$stmt) return null;
        hg_timeline_bind($stmt, $types, $params);
        $rows = hg_timeline_fetch_all($stmt);
        return $rows[0] ?? null;
    }
}

if (!function_exists('hg_timeline_event_is_excluded')) {
    function hg_timeline_event_is_excluded(mysqli $link, int $eventId, array $excludedChronicleIds): bool
    {
        $excludedChronicleIds = hg_timeline_normalize_ids($excludedChronicleIds);
        if ($eventId <= 0 || !$excludedChronicleIds || !hg_timeline_table_exists($link, 'bridge_timeline_events_chronicles')) return false;
        $sql = 'SELECT 1 FROM bridge_timeline_events_chronicles WHERE event_id = ? AND chronicle_id IN (' . implode(',', array_fill(0, count($excludedChronicleIds), '?')) . ') LIMIT 1';
        $types = 'i' . str_repeat('i', count($excludedChronicleIds));
        $params = array_merge([$eventId], $excludedChronicleIds);
        $stmt = $link->prepare($sql);
        if (!$stmt) return false;
        hg_timeline_bind($stmt, $types, $params);
        $rows = hg_timeline_fetch_all($stmt);
        return !empty($rows);
    }
}
