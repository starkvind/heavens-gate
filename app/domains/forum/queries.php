<?php

if (!function_exists('hg_forum_normalize_int_csv')) {
    function hg_forum_normalize_int_csv($csv): string
    {
        $parts = preg_split('/\s*,\s*/', trim((string)$csv));
        $ids = [];
        foreach ($parts ?: [] as $part) {
            if ($part !== '' && preg_match('/^\d+$/', (string)$part)) {
                $ids[] = (string)(int)$part;
            }
        }
        return implode(',', array_values(array_unique($ids)));
    }
}

if (!function_exists('hg_forum_avatar_fetch_characters')) {
    function hg_forum_avatar_fetch_characters(mysqli $link, $excludedChronicles = '2,7'): array
    {
        $excluded = hg_forum_normalize_int_csv($excludedChronicles);
        $where = $excluded !== '' ? "chronicle_id NOT IN ({$excluded})" : '1=1';

        $result = mysqli_query(
            $link,
            "SELECT id, name
             FROM fact_characters
             WHERE {$where}
             ORDER BY name ASC"
        );
        if (!$result) {
            return [];
        }

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
            ];
        }
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_forum_avatar_fetch_variants')) {
    function hg_forum_avatar_fetch_variants(mysqli $link): array
    {
        if (!function_exists('hg_character_avatar_variants_table_exists')
            || !hg_character_avatar_variants_table_exists($link, true)) {
            return [];
        }

        $result = mysqli_query(
            $link,
            "SELECT character_id, variant_code
             FROM fact_character_avatar_variants
             WHERE is_active = 1
             ORDER BY character_id ASC, variant_code ASC"
        );
        if (!$result) {
            return [];
        }

        $byCharacter = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $characterId = (int)($row['character_id'] ?? 0);
            $variantCode = function_exists('hg_character_avatar_variant_code')
                ? hg_character_avatar_variant_code($row['variant_code'] ?? '')
                : trim((string)($row['variant_code'] ?? ''));
            if ($characterId <= 0 || $variantCode === '') {
                continue;
            }
            if (!isset($byCharacter[$characterId])) {
                $byCharacter[$characterId] = [];
            }
            if (!in_array($variantCode, $byCharacter[$characterId], true)) {
                $byCharacter[$characterId][] = $variantCode;
            }
        }
        mysqli_free_result($result);
        return $byCharacter;
    }
}

if (!function_exists('hg_forum_avatar_fetch_text_colors')) {
    function hg_forum_avatar_fetch_text_colors(mysqli $link, $excludedChronicles = '2,7'): array
    {
        $excluded = hg_forum_normalize_int_csv($excludedChronicles);
        $where = $excluded !== '' ? "chronicle_id NOT IN ({$excluded})" : '1=1';

        $result = mysqli_query(
            $link,
            "SELECT text_color
             FROM fact_characters
             WHERE text_color <> ''
               AND {$where}
             GROUP BY text_color
             ORDER BY text_color"
        );
        if (!$result) {
            return [];
        }

        $colors = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $value = trim((string)($row['text_color'] ?? ''));
            if ($value !== '') {
                $colors[] = $value;
            }
        }
        mysqli_free_result($result);
        return $colors;
    }
}

if (!function_exists('hg_forum_topic_fetch_metadata')) {
    function hg_forum_topic_fetch_metadata(mysqli $link, int $topicId): ?array
    {
        if ($topicId <= 0) {
            return null;
        }

        $sql = "SELECT
                    ftv.topic_name,
                    ftv.topic_description,
                    dc.name AS chapter_name,
                    dc.chapter_number,
                    ds.name AS season_name
                FROM fact_tools_topic_viewer ftv
                LEFT JOIN dim_chapters dc ON dc.id = ftv.chapter_id
                LEFT JOIN dim_seasons ds ON ds.id = dc.season_id
                WHERE ftv.topic_id = ?
                  AND ftv.is_active = 1
                LIMIT 1";
        $stmt = mysqli_prepare($link, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $topicId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? (mysqli_fetch_assoc($result) ?: null) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            mysqli_stmt_close($stmt);
            if ($row) {
                return $row;
            }
        }

        // Compatibility fallback for installations predating chapter_id.
        $fallback = mysqli_prepare(
            $link,
            "SELECT topic_name, topic_description
             FROM fact_tools_topic_viewer
             WHERE topic_id = ?
               AND is_active = 1
             LIMIT 1"
        );
        if (!$fallback) {
            return null;
        }
        mysqli_stmt_bind_param($fallback, 'i', $topicId);
        mysqli_stmt_execute($fallback);
        $result = mysqli_stmt_get_result($fallback);
        $row = $result ? (mysqli_fetch_assoc($result) ?: null) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($fallback);
        return $row;
    }
}
