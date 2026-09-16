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

if (!function_exists('hg_chapters_normalize_int_csv')) {
    function hg_chapters_normalize_int_csv(string $csv): string
    {
        $ids = [];
        foreach (preg_split('/\s*,\s*/', trim($csv)) ?: [] as $part) {
            if (preg_match('/^\d+$/', (string)$part)) $ids[] = (string)(int)$part;
        }
        return implode(',', array_values(array_unique($ids)));
    }
}

if (!function_exists('hg_chapters_fetch_season_detail')) {
    function hg_chapters_fetch_season_detail(mysqli $link, int $seasonId): ?array
    {
        if ($seasonId <= 0) return null;

        $hasKind = hg_chapters_column_exists($link, 'dim_seasons', 'season_kind');
        $hasImage = hg_chapters_column_exists($link, 'dim_seasons', 'image_url');
        $hasOpening = hg_chapters_column_exists($link, 'dim_seasons', 'opening');
        $hasMainCast = hg_chapters_column_exists($link, 'dim_seasons', 'main_cast');
        $hasChronicle = hg_chapters_column_exists($link, 'dim_seasons', 'chronicle_id');

        $kindExpr = $hasKind ? "COALESCE(s.season_kind, 'temporada')" : "'temporada'";
        $imageExpr = $hasImage ? "COALESCE(s.image_url, '')" : "''";
        $openingExpr = $hasOpening ? "COALESCE(s.opening, '')" : "''";
        $mainCastExpr = $hasMainCast ? "COALESCE(s.main_cast, '')" : "''";
        $chronicleIdExpr = $hasChronicle ? 'COALESCE(s.chronicle_id, 0)' : '0';
        $chronicleNameExpr = $hasChronicle ? "COALESCE(ch.name, '')" : "''";
        $chronicleJoin = $hasChronicle ? 'LEFT JOIN dim_chronicles ch ON ch.id = s.chronicle_id' : '';

        $sql = "
            SELECT
                s.id,
                s.name,
                s.pretty_id,
                s.description,
                s.season_number,
                COALESCE(s.finished, 0) AS finished,
                {$kindExpr} AS season_kind,
                {$imageExpr} AS image_url,
                {$openingExpr} AS opening,
                {$mainCastExpr} AS main_cast,
                {$chronicleIdExpr} AS chronicle_id,
                {$chronicleNameExpr} AS chronicle_name
            FROM dim_seasons s
            {$chronicleJoin}
            WHERE s.id = ?
            LIMIT 1
        ";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $seasonId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('hg_chapters_fetch_season_chapters')) {
    function hg_chapters_fetch_season_chapters(mysqli $link, int $seasonId): ?array
    {
        if ($seasonId <= 0) return [];
        $hasSynopsis = hg_chapters_column_exists($link, 'dim_chapters', 'synopsis');
        $synopsisExpr = $hasSynopsis ? "COALESCE(c.synopsis, '')" : "''";
        $hasBridge = hg_chapters_table_exists($link, 'bridge_chapters_characters');
        $participantExpr = $hasBridge
            ? '(SELECT COUNT(DISTINCT bcc.character_id) FROM bridge_chapters_characters bcc WHERE bcc.chapter_id = c.id)'
            : '0';
        $sql = "
            SELECT c.id, c.name, c.chapter_number, c.played_date,
                   {$synopsisExpr} AS synopsis,
                   {$participantExpr} AS participant_count
            FROM dim_chapters c
            WHERE c.season_id = ?
            ORDER BY c.chapter_number ASC, c.id ASC
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
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_chapters_fetch_characters_by_ids')) {
    function hg_chapters_fetch_characters_by_ids(mysqli $link, array $characterIds): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $characterIds), static fn($id) => $id > 0)));
        if (!$ids) return [];
        $idSql = implode(',', $ids);
        $result = mysqli_query(
            $link,
            "SELECT p.id, p.name, p.image_url, p.gender
             FROM fact_characters p
             WHERE p.id IN ({$idSql})
             ORDER BY p.name ASC, p.id ASC"
        );
        if (!$result) return null;
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_chapters_fetch_season_players')) {
    function hg_chapters_fetch_season_players(mysqli $link, int $seasonId, string $excludedChroniclesCsv = ''): ?array
    {
        if ($seasonId <= 0) return [];
        $hasRole = hg_chapters_column_exists($link, 'bridge_chapters_characters', 'participation_role');
        $participationFilter = $hasRole
            ? " AND bcc.participation_role = 'player'"
            : " AND p.character_kind = 'pj' AND p.character_type_id = 1";
        $excluded = hg_chapters_normalize_int_csv($excludedChroniclesCsv);
        $chronicleFilter = ($excluded !== '' && hg_chapters_column_exists($link, 'fact_characters', 'chronicle_id'))
            ? " AND p.chronicle_id NOT IN ({$excluded})"
            : '';

        $sql = "
            SELECT p.id, p.name, p.image_url, p.gender, COUNT(DISTINCT bcc.chapter_id) AS played_count
            FROM bridge_chapters_characters bcc
            INNER JOIN fact_characters p ON p.id = bcc.character_id
            INNER JOIN dim_chapters c ON c.id = bcc.chapter_id
            WHERE c.season_id = ?
              AND c.played_date != '0000-00-00'
              {$participationFilter}
              {$chronicleFilter}
            GROUP BY p.id, p.name, p.image_url, p.gender
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
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_chapters_fetch_neighbor_season')) {
    function hg_chapters_fetch_neighbor_season(mysqli $link, int $seasonNumber, string $direction): ?array
    {
        if ($seasonNumber <= 0 || !in_array($direction, ['prev', 'next'], true)) return null;
        $operator = $direction === 'prev' ? '<' : '>';
        $order = $direction === 'prev' ? 'DESC' : 'ASC';
        $kindFilter = hg_chapters_column_exists($link, 'dim_seasons', 'season_kind')
            ? "season_kind = 'temporada' AND "
            : '';
        $sql = "SELECT id, name, season_number FROM dim_seasons WHERE {$kindFilter}season_number {$operator} ? ORDER BY season_number {$order} LIMIT 1";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $seasonNumber);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('hg_chapters_resolve_chapter_id')) {
    function hg_chapters_resolve_chapter_id(mysqli $link, string $raw): int
    {
        $raw = trim(rawurldecode($raw));
        if ($raw === '') return 0;
        if (function_exists('resolve_pretty_id')) {
            $resolved = resolve_pretty_id($link, 'dim_chapters', $raw);
            if ($resolved !== null && (int)$resolved > 0) return (int)$resolved;
        }
        if (preg_match('/^\d+$/', $raw)) return (int)$raw;
        if (!function_exists('slugify_pretty_id')) return 0;

        $hasPretty = hg_chapters_column_exists($link, 'dim_chapters', 'pretty_id');
        $prettySelect = $hasPretty ? 'pretty_id' : "'' AS pretty_id";
        $result = mysqli_query($link, "SELECT id, name, {$prettySelect} FROM dim_chapters");
        if (!$result) return 0;
        $resolvedId = 0;
        while ($row = mysqli_fetch_assoc($result)) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            $pretty = trim((string)($row['pretty_id'] ?? ''));
            $nameSlug = slugify_pretty_id((string)($row['name'] ?? ''));
            if ($pretty === $raw || $nameSlug === $raw) {
                $resolvedId = $id;
                break;
            }
        }
        mysqli_free_result($result);
        return $resolvedId;
    }
}

if (!function_exists('hg_chapters_fetch_chapter_detail')) {
    function hg_chapters_fetch_chapter_detail(mysqli $link, int $chapterId): ?array
    {
        if ($chapterId <= 0) return null;
        $hasKind = hg_chapters_column_exists($link, 'dim_seasons', 'season_kind');
        $hasImage = hg_chapters_column_exists($link, 'dim_chapters', 'image_url');
        $hasInGame = hg_chapters_column_exists($link, 'dim_chapters', 'in_game_date');
        $kindExpr = $hasKind ? "COALESCE(s.season_kind, 'temporada')" : "'temporada'";
        $imageExpr = $hasImage ? "COALESCE(c.image_url, '')" : "''";
        $inGameExpr = $hasInGame ? "COALESCE(c.in_game_date, '')" : "''";
        $sql = "
            SELECT c.id, c.name, c.chapter_number, c.synopsis, c.played_date,
                   {$inGameExpr} AS in_game_date, {$imageExpr} AS image_url,
                   COALESCE(s.id, 0) AS season_id, COALESCE(s.name, '') AS season_name,
                   COALESCE(s.season_number, 0) AS season_number, {$kindExpr} AS season_kind
            FROM dim_chapters c
            LEFT JOIN dim_seasons s ON s.id = c.season_id
            WHERE c.id = ?
            LIMIT 1
        ";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $chapterId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('hg_chapters_fetch_chapter_participants')) {
    function hg_chapters_fetch_chapter_participants(mysqli $link, int $chapterId, string $excludedChroniclesCsv = ''): ?array
    {
        if ($chapterId <= 0) return [];
        $hasRole = hg_chapters_column_exists($link, 'bridge_chapters_characters', 'participation_role');
        $roleExpr = $hasRole
            ? "COALESCE(NULLIF(TRIM(bcc.participation_role), ''), 'npc')"
            : "CASE WHEN p.character_kind = 'pj' THEN 'player' ELSE 'npc' END";
        $excluded = hg_chapters_normalize_int_csv($excludedChroniclesCsv);
        $chronicleFilter = ($excluded !== '' && hg_chapters_column_exists($link, 'fact_characters', 'chronicle_id'))
            ? " AND p.chronicle_id NOT IN ({$excluded})"
            : '';
        $sql = "
            SELECT p.id, p.name, p.image_url, p.gender, {$roleExpr} AS participation_role
            FROM bridge_chapters_characters bcc
            INNER JOIN fact_characters p ON p.id = bcc.character_id
            WHERE bcc.chapter_id = ?{$chronicleFilter}
            ORDER BY CASE WHEN {$roleExpr} = 'player' THEN 0 ELSE 1 END, p.name ASC
        ";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $chapterId);
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

if (!function_exists('hg_chapters_fetch_neighbor_chapters')) {
    function hg_chapters_fetch_neighbor_chapters(mysqli $link, int $seasonId, int $chapterNumber): ?array
    {
        if ($seasonId <= 0 || $chapterNumber <= 0) return [];
        $prev = $chapterNumber - 1;
        $next = $chapterNumber + 1;
        $stmt = mysqli_prepare(
            $link,
            'SELECT id, name, chapter_number FROM dim_chapters WHERE season_id = ? AND chapter_number IN (?, ?) ORDER BY chapter_number ASC'
        );
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'iii', $seasonId, $prev, $next);
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
        while ($row = mysqli_fetch_assoc($result)) $rows[(int)$row['chapter_number']] = $row;
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_chapters_fetch_chapter_bounds')) {
    function hg_chapters_fetch_chapter_bounds(mysqli $link, int $seasonId): ?array
    {
        if ($seasonId <= 0) return ['min_ch' => 0, 'max_ch' => 0];
        $stmt = mysqli_prepare($link, 'SELECT MIN(chapter_number) AS min_ch, MAX(chapter_number) AS max_ch FROM dim_chapters WHERE season_id = ?');
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $seasonId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return [
            'min_ch' => (int)($row['min_ch'] ?? 0),
            'max_ch' => (int)($row['max_ch'] ?? 0),
        ];
    }
}

if (!function_exists('hg_chapters_fetch_chapter_events')) {
    function hg_chapters_fetch_chapter_events(mysqli $link, int $chapterId, int $limit = 20): ?array
    {
        if ($chapterId <= 0) return [];
        if (!hg_chapters_table_exists($link, 'bridge_timeline_events_chapters') || !hg_chapters_table_exists($link, 'fact_timeline_events')) return [];
        $limit = max(1, min(100, $limit));
        $prettyExpr = hg_chapters_column_exists($link, 'fact_timeline_events', 'pretty_id') ? 'e.pretty_id' : "''";
        $sql = "
            SELECT e.id, {$prettyExpr} AS pretty_id, e.title, e.event_date
            FROM bridge_timeline_events_chapters b
            INNER JOIN fact_timeline_events e ON e.id = b.event_id
            WHERE b.chapter_id = ?
            ORDER BY e.event_date ASC, e.id ASC
            LIMIT {$limit}
        ";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $chapterId);
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
