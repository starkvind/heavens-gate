<?php

if (!function_exists('hg_error404_count_rows')) {
    function hg_error404_count_rows(mysqli $link, string $table): int
    {
        $allowed = [
            'fact_characters' => "COALESCE(pretty_id, '') <> '' AND COALESCE(name, '') <> ''",
            'dim_chapters' => "COALESCE(pretty_id, '') <> '' AND COALESCE(name, '') <> ''",
            'fact_gifts' => "COALESCE(pretty_id, '') <> '' AND COALESCE(name, '') <> ''",
            'fact_items' => "COALESCE(pretty_id, '') <> '' AND COALESCE(name, '') <> ''",
        ];
        if (!isset($allowed[$table])) {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS total FROM `{$table}` WHERE {$allowed[$table]}";
        $result = mysqli_query($link, $sql);
        if (!$result) {
            return 0;
        }
        $row = mysqli_fetch_assoc($result) ?: [];
        mysqli_free_result($result);
        return (int)($row['total'] ?? 0);
    }
}

if (!function_exists('hg_error404_fetch_characters')) {
    function hg_error404_fetch_characters(mysqli $link, int $limit, int $offset): array
    {
        $limit = max(1, min(20, $limit));
        $offset = max(0, $offset);
        $sql = "
            SELECT id, name, alias, pretty_id
            FROM fact_characters
            WHERE COALESCE(pretty_id, '') <> ''
              AND COALESCE(name, '') <> ''
            ORDER BY name ASC, id ASC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $result = mysqli_query($link, $sql);
        if (!$result) {
            return [];
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_error404_fetch_chapters')) {
    function hg_error404_fetch_chapters(mysqli $link, int $limit, int $offset): array
    {
        $limit = max(1, min(20, $limit));
        $offset = max(0, $offset);
        $sql = "
            SELECT
                ch.id,
                ch.name,
                ch.pretty_id,
                ch.chapter_number,
                COALESCE(se.name, '') AS season_name
            FROM dim_chapters ch
            LEFT JOIN dim_seasons se ON se.id = ch.season_id
            WHERE COALESCE(ch.pretty_id, '') <> ''
              AND COALESCE(ch.name, '') <> ''
            ORDER BY COALESCE(se.season_number, 9999) ASC, ch.chapter_number ASC, ch.name ASC, ch.id ASC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $result = mysqli_query($link, $sql);
        if (!$result) {
            return [];
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_error404_fetch_gifts')) {
    function hg_error404_fetch_gifts(mysqli $link, int $limit, int $offset): array
    {
        $limit = max(1, min(20, $limit));
        $offset = max(0, $offset);
        $sql = "
            SELECT id, name, pretty_id, gift_group, rank
            FROM fact_gifts
            WHERE COALESCE(pretty_id, '') <> ''
              AND COALESCE(name, '') <> ''
            ORDER BY name ASC, id ASC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $result = mysqli_query($link, $sql);
        if (!$result) {
            return [];
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_error404_fetch_items')) {
    function hg_error404_fetch_items(mysqli $link, int $limit, int $offset): array
    {
        $limit = max(1, min(20, $limit));
        $offset = max(0, $offset);
        $sql = "
            SELECT
                i.id,
                i.name,
                i.pretty_id,
                COALESCE(t.name, '') AS type_name,
                COALESCE(t.pretty_id, t.id) AS type_slug
            FROM fact_items i
            LEFT JOIN dim_item_types t ON t.id = i.item_type_id
            WHERE COALESCE(i.pretty_id, '') <> ''
              AND COALESCE(i.name, '') <> ''
            ORDER BY i.name ASC, i.id ASC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $result = mysqli_query($link, $sql);
        if (!$result) {
            return [];
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        return $rows;
    }
}
