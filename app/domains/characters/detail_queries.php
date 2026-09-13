<?php

require_once(__DIR__ . '/queries.php');

if (!function_exists('hg_characters_detect_death_table')) {
    function hg_characters_detect_death_table(mysqli $link): ?string
    {
        foreach (['fact_characters_deaths', 'fact_characters_death'] as $candidate) {
            if (hg_characters_table_exists($link, $candidate)) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('hg_characters_fetch_detail_row')) {
    function hg_characters_fetch_detail_row(mysqli $link, int $characterId): array
    {
        $deathTable = hg_characters_detect_death_table($link);
        if ($characterId <= 0) {
            return ['row' => null, 'death_table' => $deathTable];
        }

        $deathJoin = '';
        $deathSelect = "'' AS death_description, '' AS death_date";
        if ($deathTable !== null) {
            $deathJoin = "LEFT JOIN `{$deathTable}` fd ON fd.character_id = p.id";
            $deathSelect = "COALESCE(fd.death_description, '') AS death_description, COALESCE(fd.death_date, '') AS death_date";
        }

        $stmt = mysqli_prepare(
            $link,
            "SELECT p.*, s.name AS system_label, {$deathSelect}
             FROM fact_characters p
             LEFT JOIN dim_systems s ON p.system_id = s.id
             {$deathJoin}
             WHERE p.id = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return ['row' => null, 'death_table' => $deathTable];
        }

        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return ['row' => $row ?: null, 'death_table' => $deathTable];
    }
}

if (!function_exists('hg_characters_fetch_trait_values')) {
    function hg_characters_fetch_trait_values(mysqli $link, int $characterId): array
    {
        if ($characterId <= 0) {
            return [];
        }

        $stmt = mysqli_prepare(
            $link,
            'SELECT trait_id, value FROM bridge_characters_traits WHERE character_id = ?'
        );
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $values = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $values[(int)$row['trait_id']] = (int)$row['value'];
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $values;
    }
}

if (!function_exists('hg_characters_fetch_traits_by_type')) {
    function hg_characters_fetch_traits_by_type(
        mysqli $link,
        int $characterId,
        string $kind,
        bool $onlyNonZero = true
    ): array {
        if ($characterId <= 0) {
            return [];
        }

        $nonZero = $onlyNonZero ? 'AND f.value > 0' : '';
        $stmt = mysqli_prepare(
            $link,
            "SELECT t.id, t.name, f.value
             FROM bridge_characters_traits f
             INNER JOIN dim_traits t ON t.id = f.trait_id
             WHERE f.character_id = ? AND t.kind = ?
             {$nonZero}
             ORDER BY f.value DESC, t.name"
        );
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'is', $characterId, $kind);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'value' => (int)$row['value'],
                ];
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_traits_for_system_type')) {
    function hg_characters_fetch_traits_for_system_type(
        mysqli $link,
        int $characterId,
        int $systemId,
        string $kind,
        bool $bridgeOnly = false
    ): array {
        if ($characterId <= 0) {
            return [];
        }

        if ($bridgeOnly) {
            if ($systemId > 0) {
                $stmt = mysqli_prepare(
                    $link,
                    "SELECT t.id, t.name, f.value, s.sort_order, t.classification
                     FROM bridge_characters_traits f
                     JOIN dim_traits t ON t.id = f.trait_id AND t.kind = ?
                     LEFT JOIN fact_trait_sets s
                       ON s.trait_id = t.id
                      AND s.system_id = ?
                      AND s.is_active = 1
                     WHERE f.character_id = ?
                     ORDER BY
                       CASE WHEN s.sort_order IS NULL THEN 1 ELSE 0 END,
                       COALESCE(NULLIF(CAST(SUBSTRING_INDEX(TRIM(t.classification), ' ', 1) AS UNSIGNED), 0), 9999),
                       s.sort_order,
                       t.name"
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'sii', $kind, $systemId, $characterId);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $rows = [];
                    if ($result) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $rows[] = [
                                'id' => (int)$row['id'],
                                'name' => (string)$row['name'],
                                'value' => (int)$row['value'],
                            ];
                        }
                        mysqli_free_result($result);
                    }
                    mysqli_stmt_close($stmt);
                    if (!empty($rows)) {
                        return $rows;
                    }
                }
            }

            $stmt = mysqli_prepare(
                $link,
                "SELECT t.id, t.name, f.value
                 FROM bridge_characters_traits f
                 JOIN dim_traits t ON t.id = f.trait_id
                 WHERE f.character_id = ? AND t.kind = ?
                 ORDER BY t.name"
            );
            if (!$stmt) {
                return [];
            }
            mysqli_stmt_bind_param($stmt, 'is', $characterId, $kind);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $rows = [];
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = [
                        'id' => (int)$row['id'],
                        'name' => (string)$row['name'],
                        'value' => (int)$row['value'],
                    ];
                }
                mysqli_free_result($result);
            }
            mysqli_stmt_close($stmt);
            return $rows;
        }

        $rows = [];
        $hasSet = false;
        if ($systemId > 0) {
            $stmt = mysqli_prepare(
                $link,
                "SELECT t.id, t.name, COALESCE(f.value,0) AS value, s.sort_order, t.classification
                 FROM fact_trait_sets s
                 JOIN dim_traits t ON t.id = s.trait_id AND t.kind = ?
                 LEFT JOIN bridge_characters_traits f ON f.trait_id = t.id AND f.character_id = ?
                 WHERE s.system_id = ? AND s.is_active = 1
                 ORDER BY
                   COALESCE(NULLIF(CAST(SUBSTRING_INDEX(TRIM(t.classification), ' ', 1) AS UNSIGNED), 0), 9999),
                   s.sort_order,
                   t.name"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sii', $kind, $characterId, $systemId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $rows[] = [
                            'id' => (int)$row['id'],
                            'name' => (string)$row['name'],
                            'value' => (int)$row['value'],
                        ];
                    }
                    mysqli_free_result($result);
                }
                mysqli_stmt_close($stmt);
            }
            $hasSet = !empty($rows);
        }

        if ($hasSet && $systemId > 0) {
            $stmt = mysqli_prepare(
                $link,
                "SELECT t.id, t.name, f.value
                 FROM bridge_characters_traits f
                 JOIN dim_traits t ON t.id = f.trait_id AND t.kind = ?
                 WHERE f.character_id = ? AND f.value > 0
                   AND t.id NOT IN (
                       SELECT trait_id FROM fact_trait_sets WHERE system_id = ? AND is_active = 1
                   )
                 ORDER BY f.value DESC, t.name"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sii', $kind, $characterId, $systemId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $rows[] = [
                            'id' => (int)$row['id'],
                            'name' => (string)$row['name'],
                            'value' => (int)$row['value'],
                        ];
                    }
                    mysqli_free_result($result);
                }
                mysqli_stmt_close($stmt);
            }
            return $rows;
        }

        return hg_characters_fetch_traits_by_type($link, $characterId, $kind, false);
    }
}

if (!function_exists('hg_characters_fetch_system_detail_labels')) {
    function hg_characters_fetch_system_detail_labels(mysqli $link, int $systemId): array
    {
        if ($systemId <= 0 || !hg_characters_table_exists($link, 'bridge_systems_detail_labels')) {
            return [];
        }

        $candidates = [
            'label_breed',
            'label_auspice',
            'label_pack',
            'label_tribe',
            'label_clan',
            'label_pk_name',
            'label_social',
            'label_misc',
        ];
        $selectColumns = [];
        foreach ($candidates as $column) {
            if (hg_characters_has_column($link, 'bridge_systems_detail_labels', $column)) {
                $selectColumns[] = "`{$column}`";
            }
        }
        if (empty($selectColumns)) {
            return [];
        }

        $stmt = mysqli_prepare(
            $link,
            'SELECT ' . implode(', ', $selectColumns) . ' FROM bridge_systems_detail_labels WHERE system_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $systemId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        if (!$row) {
            return [];
        }
        $labels = [];
        foreach ($candidates as $column) {
            $value = trim((string)($row[$column] ?? ''));
            if ($value !== '') {
                $labels[$column] = $value;
            }
        }
        return $labels;
    }
}

if (!function_exists('hg_characters_fetch_participation_events')) {
    function hg_characters_fetch_participation_events(mysqli $link, int $characterId, int $limit = 24): array
    {
        if ($characterId <= 0) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $stmt = mysqli_prepare(
            $link,
            "SELECT e.id, e.pretty_id, e.title, e.event_date, COALESCE(t.name, 'Evento') AS type_name
             FROM bridge_timeline_events_characters bec
             INNER JOIN fact_timeline_events e ON e.id = bec.event_id
             LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id
             WHERE bec.character_id = ?
             ORDER BY
                 CASE WHEN e.event_date = '0000-00-00' OR e.event_date IS NULL THEN 1 ELSE 0 END ASC,
                 e.event_date ASC,
                 e.id ASC
             LIMIT {$limit}"
        );
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_relations')) {
    function hg_characters_fetch_relations(mysqli $link, int $characterId): array
    {
        if ($characterId <= 0) {
            return [];
        }

        $queries = [
            "SELECT cr.*, p2.name, p2.alias, p2.image_url, p2.gender, 'outgoing' AS direction
             FROM bridge_characters_relations cr
             LEFT JOIN fact_characters p2 ON cr.target_id = p2.id
             WHERE cr.source_id = ?
             ORDER BY cr.relation_type",
            "SELECT cr.*, p2.name, p2.alias, p2.image_url, p2.gender, 'incoming' AS direction
             FROM bridge_characters_relations cr
             LEFT JOIN fact_characters p2 ON cr.source_id = p2.id
             WHERE cr.target_id = ?
             ORDER BY cr.relation_type",
        ];

        $rows = [];
        foreach ($queries as $sql) {
            $stmt = mysqli_prepare($link, $sql);
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'i', $characterId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $row;
                }
                mysqli_free_result($result);
            }
            mysqli_stmt_close($stmt);
        }

        usort($rows, static function (array $left, array $right): int {
            return strcasecmp((string)($left['relation_type'] ?? ''), (string)($right['relation_type'] ?? ''));
        });

        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_kills')) {
    function hg_characters_fetch_kills(mysqli $link, int $characterId, ?string $deathTable): array
    {
        if ($characterId <= 0 || $deathTable === null) {
            return [];
        }

        $deathTable = preg_replace('/[^a-zA-Z0-9_]/', '', $deathTable);
        if (!in_array($deathTable, ['fact_characters_deaths', 'fact_characters_death'], true)) {
            return [];
        }

        $stmt = mysqli_prepare(
            $link,
            "SELECT
                 fd.id AS death_id,
                 fd.character_id AS victim_id,
                 fd.death_type,
                 fd.death_date,
                 fd.death_description,
                 fd.death_timeline_event_id,
                 v.name AS victim_name,
                 v.alias AS victim_alias,
                 v.image_url AS victim_image,
                 v.gender AS victim_gender,
                 e.title AS event_title,
                 e.event_date AS event_date
             FROM `{$deathTable}` fd
             INNER JOIN fact_characters v ON v.id = fd.character_id
             LEFT JOIN fact_timeline_events e ON e.id = fd.death_timeline_event_id
             WHERE fd.killer_character_id = ?
               AND fd.character_id <> ?
             ORDER BY COALESCE(fd.death_date, e.event_date) DESC, fd.id DESC"
        );
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'ii', $characterId, $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_chapter_participation')) {
    function hg_characters_fetch_chapter_participation(mysqli $link, int $characterId): array
    {
        if ($characterId <= 0) {
            return [];
        }

        $seasonKindExpr = hg_characters_has_column($link, 'dim_seasons', 'season_kind')
            ? "COALESCE(at2.season_kind, 'temporada')"
            : "'temporada'";
        $stmt = mysqli_prepare(
            $link,
            "SELECT ac.id, ac.name, ac.chapter_number, at2.name AS temporada_name,
                    at2.season_number, {$seasonKindExpr} AS season_kind, ac.played_date
             FROM dim_chapters ac
             INNER JOIN bridge_chapters_characters acp ON ac.id = acp.chapter_id
             INNER JOIN dim_seasons at2 ON at2.id = ac.season_id
             WHERE acp.character_id = ?
             ORDER BY ac.played_date, ac.chapter_number"
        );
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('hg_characters_count_timeline_events')) {
    function hg_characters_count_timeline_events(mysqli $link, int $characterId): int
    {
        if ($characterId <= 0) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $link,
            'SELECT COUNT(*) AS c FROM bridge_timeline_events_characters WHERE character_id = ?'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('hg_characters_fetch_docs')) {
    function hg_characters_fetch_docs(mysqli $link, int $characterId): array
    {
        if ($characterId <= 0
            || !hg_characters_table_exists($link, 'bridge_characters_docs')
            || !hg_characters_table_exists($link, 'fact_docs')) {
            return [];
        }

        $hasRelationLabel = hg_characters_has_column($link, 'bridge_characters_docs', 'relation_label');
        $hasSortOrder = hg_characters_has_column($link, 'bridge_characters_docs', 'sort_order');
        $relationExpr = $hasRelationLabel ? 'COALESCE(b.relation_label, "")' : '""';
        $sortExpr = $hasSortOrder ? 'COALESCE(b.sort_order, 0)' : '0';
        $order = $hasSortOrder ? 'b.sort_order ASC, d.title ASC' : 'd.title ASC';

        $stmt = mysqli_prepare(
            $link,
            "SELECT CONCAT(b.character_id, ':', b.doc_id) AS bridge_id,
                    b.doc_id,
                    {$relationExpr} AS relation_label,
                    {$sortExpr} AS sort_order,
                    d.title,
                    d.pretty_id,
                    COALESCE(c.kind, '') AS section_name
             FROM bridge_characters_docs b
             INNER JOIN fact_docs d ON d.id = b.doc_id
             LEFT JOIN dim_doc_categories c ON c.id = d.section_id
             WHERE b.character_id = ?
             ORDER BY {$order}"
        );
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_external_links')) {
    function hg_characters_fetch_external_links(mysqli $link, int $characterId): array
    {
        if ($characterId <= 0
            || !hg_characters_table_exists($link, 'bridge_characters_external_links')
            || !hg_characters_table_exists($link, 'fact_external_links')) {
            return [];
        }

        $hasRelationLabel = hg_characters_has_column($link, 'bridge_characters_external_links', 'relation_label');
        $hasSortOrder = hg_characters_has_column($link, 'bridge_characters_external_links', 'sort_order');
        $hasExternalActive = hg_characters_has_column($link, 'fact_external_links', 'is_active');
        $relationExpr = $hasRelationLabel ? 'COALESCE(b.relation_label, "")' : '""';
        $sortExpr = $hasSortOrder ? 'COALESCE(b.sort_order, 0)' : '0';
        $order = $hasSortOrder ? 'b.sort_order ASC, l.title ASC' : 'l.title ASC';
        $activeExpr = $hasExternalActive ? 'COALESCE(l.is_active, 1)' : '1';

        $stmt = mysqli_prepare(
            $link,
            "SELECT CONCAT(b.character_id, ':', b.external_link_id) AS bridge_id,
                    b.external_link_id,
                    {$relationExpr} AS relation_label,
                    {$sortExpr} AS sort_order,
                    l.title,
                    l.url,
                    l.kind,
                    l.source_label,
                    COALESCE(l.description, '') AS description,
                    {$activeExpr} AS is_active
             FROM bridge_characters_external_links b
             INNER JOIN fact_external_links l ON l.id = b.external_link_id
             WHERE b.character_id = ?
             ORDER BY {$order}"
        );
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('hg_characters_fetch_comments')) {
    function hg_characters_fetch_comments(mysqli $link, int $characterId): array
    {
        if ($characterId <= 0) {
            return [];
        }
        $stmt = mysqli_prepare(
            $link,
            'SELECT id, nick, comment_time, commented_at, message, ip, created_at
             FROM fact_characters_comments
             WHERE character_id = ?
             ORDER BY commented_at DESC, comment_time DESC, id DESC'
        );
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('hg_characters_has_soundtrack')) {
    function hg_characters_has_soundtrack(mysqli $link, int $characterId): bool
    {
        if ($characterId <= 0) {
            return false;
        }
        $stmt = mysqli_prepare(
            $link,
            "SELECT 1
             FROM bridge_soundtrack_links
             WHERE object_type = 'personaje' AND object_id = ?
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = $result && mysqli_fetch_assoc($result) !== null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $exists;
    }
}
