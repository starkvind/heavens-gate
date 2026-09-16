<?php

if (!function_exists('hg_chapters_table_exists')) {
    function hg_chapters_table_exists(mysqli $link, string $table): bool
    {
        static $cache = [];
        $table = str_replace('`', '', trim($table));
        if ($table === '') return false;
        if (array_key_exists($table, $cache)) return $cache[$table];

        $stmt = mysqli_prepare(
            $link,
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        if (!$stmt) return $cache[$table] = false;

        mysqli_stmt_bind_param($stmt, 's', $table);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return $cache[$table] = false;
        }
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        return $cache[$table] = ((int)$count > 0);
    }
}

if (!function_exists('hg_chapters_column_exists')) {
    function hg_chapters_column_exists(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . ':' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];

        $stmt = mysqli_prepare(
            $link,
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        if (!$stmt) return $cache[$key] = false;

        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return $cache[$key] = false;
        }
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        return $cache[$key] = ((int)$count > 0);
    }
}

if (!function_exists('hg_chapters_fetch_season_catalog')) {
    function hg_chapters_fetch_season_catalog(mysqli $link): ?array
    {
        $hasKind = hg_chapters_column_exists($link, 'dim_seasons', 'season_kind');
        $hasImage = hg_chapters_column_exists($link, 'dim_seasons', 'image_url');
        $kindExpr = $hasKind ? "COALESCE(s.season_kind, 'temporada')" : "'temporada'";
        $imageSelect = $hasImage ? "COALESCE(s.image_url, '') AS image_url" : "'' AS image_url";
        $kindGroup = $hasKind ? ', s.season_kind' : '';
        $imageGroup = $hasImage ? ', s.image_url' : '';

        $sql = "
            SELECT
                s.id,
                s.name,
                s.pretty_id,
                s.description,
                s.season_number,
                {$kindExpr} AS season_kind,
                COALESCE(s.finished, 0) AS finished,
                COALESCE(s.sort_order, 999999) AS sort_order,
                {$imageSelect},
                COUNT(c.id) AS chapter_count
            FROM dim_seasons s
            LEFT JOIN dim_chapters c ON c.season_id = s.id
            GROUP BY
                s.id, s.name, s.pretty_id, s.description, s.season_number{$kindGroup},
                s.finished, s.sort_order{$imageGroup}
            ORDER BY
                CASE {$kindExpr}
                    WHEN 'temporada' THEN 1
                    WHEN 'inciso' THEN 2
                    WHEN 'historia_personal' THEN 3
                    WHEN 'especial' THEN 4
                    ELSE 99
                END ASC,
                COALESCE(s.sort_order, 999999) ASC,
                s.season_number ASC,
                s.name ASC
        ";

        $result = mysqli_query($link, $sql);
        if (!$result) return null;

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_chapters_fetch_table_rows')) {
    function hg_chapters_fetch_table_rows(mysqli $link): ?array
    {
        $hasChronicle = hg_chapters_column_exists($link, 'dim_seasons', 'chronicle_id');
        $hasSynopsis = hg_chapters_column_exists($link, 'dim_chapters', 'synopsis');
        $hasCharacters = hg_chapters_table_exists($link, 'bridge_chapters_characters');
        $hasKind = hg_chapters_column_exists($link, 'dim_seasons', 'season_kind');

        $kindExpr = $hasKind ? "COALESCE(s.season_kind, 'temporada')" : "'temporada'";
        $selectChronicle = $hasChronicle
            ? ", ch.id AS chronicle_id, ch.pretty_id AS chronicle_pretty_id, ch.name AS chronicle_name"
            : ", NULL AS chronicle_id, NULL AS chronicle_pretty_id, NULL AS chronicle_name";
        $joinChronicle = $hasChronicle ? ' LEFT JOIN dim_chronicles ch ON ch.id = s.chronicle_id' : '';
        $selectSynopsis = $hasSynopsis ? "COALESCE(c.synopsis, '') AS chapter_synopsis" : "'' AS chapter_synopsis";
        $selectCharacters = $hasCharacters
            ? "(SELECT COUNT(DISTINCT bcc.character_id) FROM bridge_chapters_characters bcc WHERE bcc.chapter_id = c.id) AS character_count"
            : '0 AS character_count';

        $sql = "
            SELECT
                c.id AS chapter_id,
                c.pretty_id AS chapter_pretty_id,
                c.name AS chapter_name,
                c.chapter_number,
                s.id AS season_id,
                s.pretty_id AS season_pretty_id,
                s.name AS season_name,
                s.season_number,
                {$kindExpr} AS season_kind,
                COALESCE(s.sort_order, 999999) AS season_sort_order,
                {$selectSynopsis},
                {$selectCharacters}
                {$selectChronicle}
            FROM dim_chapters c
            LEFT JOIN dim_seasons s ON s.id = c.season_id
            {$joinChronicle}
            ORDER BY
                COALESCE(s.sort_order, 999999) ASC,
                s.season_number ASC,
                c.chapter_number ASC,
                c.name ASC
        ";

        $result = mysqli_query($link, $sql);
        if (!$result) return null;
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_chapters_fetch_season_options')) {
    function hg_chapters_fetch_season_options(mysqli $link): ?array
    {
        $result = mysqli_query(
            $link,
            'SELECT id, name, season_number FROM dim_seasons ORDER BY COALESCE(sort_order, 999999), season_number, name'
        );
        if (!$result) return null;
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_chapters_fetch_mobile_list')) {
    function hg_chapters_fetch_mobile_list(mysqli $link, string $search = '', int $seasonId = 0): ?array
    {
        $hasKind = hg_chapters_column_exists($link, 'dim_seasons', 'season_kind');
        $hasSynopsis = hg_chapters_column_exists($link, 'dim_chapters', 'synopsis');
        $hasCharacters = hg_chapters_table_exists($link, 'bridge_chapters_characters');
        $kindExpr = $hasKind ? "COALESCE(s.season_kind, 'temporada')" : "'temporada'";
        $synopsisExpr = $hasSynopsis ? "COALESCE(c.synopsis, '')" : "''";
        $participantExpr = $hasCharacters
            ? '(SELECT COUNT(DISTINCT bcc.character_id) FROM bridge_chapters_characters bcc WHERE bcc.chapter_id = c.id)'
            : '0';

        $where = [];
        $types = '';
        $params = [];
        if ($search !== '') {
            $where[] = "(c.name LIKE ? OR {$synopsisExpr} LIKE ?)";
            $like = '%' . $search . '%';
            $types .= 'ss';
            $params[] = $like;
            $params[] = $like;
        }
        if ($seasonId > 0) {
            $where[] = 'c.season_id = ?';
            $types .= 'i';
            $params[] = $seasonId;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT c.id, c.name, c.chapter_number, c.played_date,
                   {$synopsisExpr} AS synopsis,
                   COALESCE(s.id, 0) AS season_id,
                   COALESCE(s.name, '') AS season_name,
                   COALESCE(s.season_number, 0) AS season_number,
                   {$kindExpr} AS season_kind,
                   COALESCE(s.sort_order, 999999) AS season_sort_order,
                   {$participantExpr} AS participant_count
            FROM dim_chapters c
            LEFT JOIN dim_seasons s ON s.id = c.season_id
            {$whereSql}
            ORDER BY COALESCE(s.sort_order, 999999), COALESCE(s.season_number, 0), c.chapter_number, c.id
        ";

        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_chapters_fetch_season_order_catalog')) {
    function hg_chapters_fetch_season_order_catalog(mysqli $link): ?array
    {
        if (!hg_chapters_table_exists($link, 'bridge_season_order_nodes')) return [];
        $sql = "
            SELECT order_key, MAX(order_label) AS order_label, COUNT(*) AS node_count, MIN(position) AS first_position
            FROM bridge_season_order_nodes
            WHERE is_active = 1
            GROUP BY order_key
            ORDER BY first_position ASC, order_key ASC
        ";
        $result = mysqli_query($link, $sql);
        if (!$result) return null;
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_chapters_fetch_season_order_nodes')) {
    function hg_chapters_fetch_season_order_nodes(mysqli $link, string $orderKey): ?array
    {
        if ($orderKey === '' || !hg_chapters_table_exists($link, 'bridge_season_order_nodes')) return [];
        $sql = "
            SELECT
                n.id,
                n.position,
                n.branch_type,
                n.parent_node_id,
                n.node_kind,
                n.season_id,
                n.episode_start,
                n.episode_end,
                n.label,
                n.description,
                s.name AS season_name
            FROM bridge_season_order_nodes n
            LEFT JOIN dim_seasons s ON s.id = n.season_id
            WHERE n.order_key = ? AND n.is_active = 1
            ORDER BY n.position ASC, n.id ASC
        ";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 's', $orderKey);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_chapters_fetch_season_attendance')) {
    function hg_chapters_fetch_season_attendance(mysqli $link, int $seasonId): ?array
    {
        if ($seasonId <= 0) return null;

        $stmt = mysqli_prepare($link, 'SELECT season_number FROM dim_seasons WHERE id = ? LIMIT 1');
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $seasonId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $season = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        if (!$season) return null;

        $stmt = mysqli_prepare($link, "SELECT COUNT(*) AS total FROM dim_chapters WHERE season_id = ? AND played_date != '0000-00-00'");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $seasonId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $totalRow = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        $total = (int)($totalRow['total'] ?? 0);

        if ($total <= 0) {
            return ['season_number' => (int)$season['season_number'], 'total' => 0, 'players' => []];
        }

        $hasRole = hg_chapters_column_exists($link, 'bridge_chapters_characters', 'participation_role');
        $filter = $hasRole
            ? " AND acp.participation_role = 'player'"
            : " AND p.character_kind = 'pj' AND p.character_type_id = 1";
        $sql = "
            SELECT p.name, p.id AS pj_id, COUNT(DISTINCT acp.chapter_id) AS played_count
            FROM bridge_chapters_characters acp
            JOIN dim_chapters c ON c.id = acp.chapter_id
            JOIN fact_characters p ON p.id = acp.character_id
            WHERE c.season_id = ? AND c.played_date != '0000-00-00'{$filter}
            GROUP BY acp.character_id, p.id, p.name
            ORDER BY played_count DESC, p.name ASC
        ";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $seasonId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $players = [];
        while ($row = mysqli_fetch_assoc($result)) $players[] = $row;
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);

        return ['season_number' => (int)$season['season_number'], 'total' => $total, 'players' => $players];
    }
}

if (!function_exists('hg_chapters_fetch_attendance_analysis')) {
    function hg_chapters_fetch_attendance_analysis(mysqli $link): ?array
    {
        $chaptersResult = mysqli_query(
            $link,
            "SELECT c.id, s.season_number AS season_number, s.name AS season_name, c.played_date
             FROM dim_chapters c
             LEFT JOIN dim_seasons s ON c.season_id = s.id
             WHERE c.played_date != '0000-00-00'"
        );
        if (!$chaptersResult) return null;
        $chapters = [];
        while ($row = mysqli_fetch_assoc($chaptersResult)) $chapters[] = $row;
        mysqli_free_result($chaptersResult);

        $appearancesResult = mysqli_query($link, 'SELECT character_id, chapter_id FROM bridge_chapters_characters');
        if (!$appearancesResult) return null;
        $appearances = [];
        while ($row = mysqli_fetch_assoc($appearancesResult)) $appearances[] = $row;
        mysqli_free_result($appearancesResult);

        $playersResult = mysqli_query(
            $link,
            "SELECT p.id, p.name
             FROM fact_characters p
             JOIN bridge_chapters_characters acp ON p.id = acp.character_id
             WHERE p.character_kind = 'pj' AND p.character_type_id = 1 AND p.player_id > 0
             GROUP BY p.id, p.name
             ORDER BY COUNT(acp.id) DESC"
        );
        if (!$playersResult) return null;
        $players = [];
        while ($row = mysqli_fetch_assoc($playersResult)) $players[] = $row;
        mysqli_free_result($playersResult);

        return ['chapters' => $chapters, 'appearances' => $appearances, 'players' => $players];
    }
}
