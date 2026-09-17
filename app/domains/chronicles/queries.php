<?php

if (!function_exists('hg_chronicles_has_column')) {
    function hg_chronicles_has_column(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') return false;
        $key = $table . ':' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];

        $stmt = mysqli_prepare(
            $link,
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        if (!$stmt) return $cache[$key] = false;
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $cache[$key] = ((int)$count > 0);
    }
}

if (!function_exists('hg_chronicles_has_table')) {
    function hg_chronicles_has_table(mysqli $link, string $table): bool
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') return false;
        if (array_key_exists($table, $cache)) return $cache[$table];

        $stmt = mysqli_prepare(
            $link,
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        if (!$stmt) return $cache[$table] = false;
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $cache[$table] = ((int)$count > 0);
    }
}

if (!function_exists('hg_chronicles_normalize_ids')) {
    function hg_chronicles_normalize_ids($value): array
    {
        $ids = [];
        foreach (preg_split('/\s*,\s*/', trim((string)$value)) as $part) {
            if ($part !== '' && preg_match('/^\d+$/', (string)$part)) {
                $id = (int)$part;
                if ($id > 0) $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}

if (!function_exists('hg_chronicles_schema')) {
    function hg_chronicles_schema(mysqli $link): array
    {
        return [
            'chronicle_pretty' => hg_chronicles_has_column($link, 'dim_chronicles', 'pretty_id'),
            'chronicle_description' => hg_chronicles_has_column($link, 'dim_chronicles', 'description'),
            'chronicle_sort' => hg_chronicles_has_column($link, 'dim_chronicles', 'sort_order'),
            'chronicle_image' => hg_chronicles_has_column($link, 'dim_chronicles', 'image_url'),
            'season_chronicle' => hg_chronicles_has_column($link, 'dim_seasons', 'chronicle_id'),
            'character_chronicle' => hg_chronicles_has_column($link, 'fact_characters', 'chronicle_id'),
        ];
    }
}

if (!function_exists('hg_chronicles_fetch_catalog')) {
    function hg_chronicles_fetch_catalog(mysqli $link, array $schema, array $excludedIds = []): ?array
    {
        $prettyExpr = !empty($schema['chronicle_pretty']) ? "COALESCE(ch.pretty_id, '')" : "''";
        $descExpr = !empty($schema['chronicle_description']) ? "COALESCE(ch.description, '')" : "''";
        $sortExpr = !empty($schema['chronicle_sort']) ? 'COALESCE(ch.sort_order, 999999)' : '999999';
        $imageExpr = !empty($schema['chronicle_image']) ? "COALESCE(ch.image_url, '')" : "''";
        $seasonJoin = !empty($schema['season_chronicle']) ? 'LEFT JOIN dim_seasons s ON s.chronicle_id = ch.id' : '';
        $seasonCount = !empty($schema['season_chronicle']) ? 'COUNT(DISTINCT s.id)' : '0';
        $characterJoin = !empty($schema['character_chronicle']) ? 'LEFT JOIN fact_characters fc ON fc.chronicle_id = ch.id' : '';
        $characterCount = !empty($schema['character_chronicle']) ? 'COUNT(DISTINCT fc.id)' : '0';

        $sql = "SELECT ch.id, {$prettyExpr} AS pretty_id, ch.name, {$descExpr} AS description,
                       {$imageExpr} AS image_url, {$seasonCount} AS season_count,
                       {$characterCount} AS character_count, {$sortExpr} AS sort_order
                FROM dim_chronicles ch
                {$characterJoin}
                {$seasonJoin}
                WHERE 1=1";
        $types = '';
        $params = [];
        $ids = array_values(array_filter(array_map('intval', $excludedIds), static fn($id) => $id > 0));
        if ($ids) {
            $sql .= ' AND ch.id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $types = str_repeat('i', count($ids));
            $params = $ids;
        }
        $group = ['ch.id', 'ch.name'];
        if (!empty($schema['chronicle_pretty'])) $group[] = 'ch.pretty_id';
        if (!empty($schema['chronicle_description'])) $group[] = 'ch.description';
        if (!empty($schema['chronicle_sort'])) $group[] = 'ch.sort_order';
        if (!empty($schema['chronicle_image'])) $group[] = 'ch.image_url';
        $sql .= ' GROUP BY ' . implode(', ', $group) . ' ORDER BY sort_order ASC, ch.name ASC';

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

if (!function_exists('hg_chronicles_fetch_one')) {
    function hg_chronicles_fetch_one(mysqli $link, array $schema, int $chronicleId): ?array
    {
        if ($chronicleId <= 0) return null;
        $prettyExpr = !empty($schema['chronicle_pretty']) ? "COALESCE(pretty_id, '')" : "''";
        $descExpr = !empty($schema['chronicle_description']) ? "COALESCE(description, '')" : "''";
        $imageExpr = !empty($schema['chronicle_image']) ? "COALESCE(image_url, '')" : "''";
        $stmt = mysqli_prepare(
            $link,
            "SELECT id, {$prettyExpr} AS pretty_id, name, {$descExpr} AS description, {$imageExpr} AS image_url
             FROM dim_chronicles WHERE id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $chronicleId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? (mysqli_fetch_assoc($result) ?: null) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row;
    }
}

if (!function_exists('hg_chronicles_fetch_seasons')) {
    function hg_chronicles_fetch_seasons(mysqli $link, int $chronicleId): array
    {
        if ($chronicleId <= 0 || !hg_chronicles_has_column($link, 'dim_seasons', 'chronicle_id')) return [];

        $prettyExpr = hg_chronicles_has_column($link, 'dim_seasons', 'pretty_id') ? "COALESCE(s.pretty_id, '')" : "''";
        $descExpr = hg_chronicles_has_column($link, 'dim_seasons', 'description') ? "COALESCE(s.description, '')" : "''";
        $kindExpr = hg_chronicles_has_column($link, 'dim_seasons', 'season_kind') ? "COALESCE(s.season_kind, 'temporada')" : "'temporada'";
        $finishedExpr = hg_chronicles_has_column($link, 'dim_seasons', 'finished') ? 'COALESCE(s.finished, 0)' : '0';
        $sortExpr = hg_chronicles_has_column($link, 'dim_seasons', 'sort_order') ? 'COALESCE(s.sort_order, 999999)' : '999999';
        $chapterCountExpr = hg_chronicles_has_column($link, 'dim_chapters', 'season_id')
            ? '(SELECT COUNT(*) FROM dim_chapters c WHERE c.season_id = s.id)'
            : '0';

        $stmt = mysqli_prepare(
            $link,
            "SELECT s.id, {$prettyExpr} AS pretty_id, s.name, {$descExpr} AS description,
                    s.season_number, {$kindExpr} AS season_kind, {$finishedExpr} AS finished,
                    {$sortExpr} AS sort_order, {$chapterCountExpr} AS chapter_count
             FROM dim_seasons s
             WHERE s.chronicle_id = ?
             ORDER BY
                CASE {$kindExpr}
                    WHEN 'temporada' THEN 1
                    WHEN 'inciso' THEN 2
                    WHEN 'historia_personal' THEN 3
                    WHEN 'especial' THEN 4
                    ELSE 99
                END ASC,
                sort_order ASC, s.season_number ASC, s.name ASC"
        );
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, 'i', $chronicleId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_chronicles_fetch_members')) {
    function hg_chronicles_fetch_members(mysqli $link, int $chronicleId): array
    {
        if ($chronicleId <= 0 || !hg_chronicles_has_column($link, 'fact_characters', 'chronicle_id')) return [];
        $stmt = mysqli_prepare(
            $link,
            "SELECT p.id, p.name, COALESCE(dcs.label, '') AS status,
                    GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ') AS organizations,
                    GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS groups
             FROM fact_characters p
             LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
             LEFT JOIN bridge_characters_organizations bco
                ON bco.character_id = p.id AND (bco.is_active = 1 OR bco.is_active IS NULL)
             LEFT JOIN dim_organizations o ON o.id = bco.organization_id
             LEFT JOIN bridge_characters_groups bcg
                ON bcg.character_id = p.id AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
             LEFT JOIN dim_groups g ON g.id = bcg.group_id
             WHERE p.chronicle_id = ?
             GROUP BY p.id, p.name, COALESCE(dcs.label, '')
             ORDER BY p.name ASC"
        );
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, 'i', $chronicleId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_chronicles_fetch_mobile_members')) {
    function hg_chronicles_fetch_mobile_members(mysqli $link, int $chronicleId): array
    {
        if ($chronicleId <= 0 || !hg_chronicles_has_column($link, 'fact_characters', 'chronicle_id')) return [];
        $imageExpr = hg_chronicles_has_column($link, 'fact_characters', 'image_url') ? "COALESCE(p.image_url, '')" : "''";
        $genderExpr = hg_chronicles_has_column($link, 'fact_characters', 'gender') ? "COALESCE(p.gender, '')" : "''";
        $hasStatus = hg_chronicles_has_column($link, 'fact_characters', 'status_id') && hg_chronicles_has_table($link, 'dim_character_status');
        $statusJoin = $hasStatus ? 'LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id' : '';
        $statusExpr = $hasStatus ? "COALESCE(dcs.label, '')" : "''";

        $stmt = mysqli_prepare(
            $link,
            "SELECT p.id, p.name, {$imageExpr} AS image_url, {$genderExpr} AS gender, {$statusExpr} AS status
             FROM fact_characters p
             {$statusJoin}
             WHERE p.chronicle_id = ?
             ORDER BY p.name ASC, p.id ASC"
        );
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, 'i', $chronicleId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $row['meta'] = trim((string)($row['status'] ?? '')) ?: 'Personaje';
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_chronicles_resolve_id')) {
    function hg_chronicles_resolve_id(mysqli $link, string $raw): int
    {
        $raw = trim(rawurldecode($raw));
        if ($raw === '') return 0;
        if (preg_match('/^\d+$/', $raw)) return (int)$raw;
        if (function_exists('resolve_pretty_id')) {
            $resolved = resolve_pretty_id($link, 'dim_chronicles', $raw);
            if ((int)$resolved > 0) return (int)$resolved;
        }
        if (!function_exists('slugify_pretty_id')) return 0;

        $prettyExpr = hg_chronicles_has_column($link, 'dim_chronicles', 'pretty_id') ? "COALESCE(pretty_id, '')" : "''";
        $result = mysqli_query($link, "SELECT id, name, {$prettyExpr} AS pretty_id FROM dim_chronicles");
        if (!$result) return 0;
        $resolvedId = 0;
        while ($row = mysqli_fetch_assoc($result)) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            if (trim((string)($row['pretty_id'] ?? '')) === $raw || slugify_pretty_id((string)($row['name'] ?? '')) === $raw) {
                $resolvedId = $id;
                break;
            }
        }
        mysqli_free_result($result);
        return $resolvedId;
    }
}

if (!function_exists('hg_chronicles_fetch_image_row')) {
    function hg_chronicles_fetch_image_row(mysqli $link, int $chronicleId): ?array
    {
        if ($chronicleId <= 0) return null;
        $imageExpr = hg_chronicles_has_column($link, 'dim_chronicles', 'image_url') ? "COALESCE(image_url, '')" : "''";
        $prettyExpr = hg_chronicles_has_column($link, 'dim_chronicles', 'pretty_id') ? "COALESCE(pretty_id, '')" : "''";
        $stmt = mysqli_prepare(
            $link,
            "SELECT {$prettyExpr} AS pretty_id, {$imageExpr} AS image_url FROM dim_chronicles WHERE id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $chronicleId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? (mysqli_fetch_assoc($result) ?: null) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row;
    }
}
