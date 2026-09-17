<?php

if (!function_exists('hg_home_query_count_table')) {
    function hg_home_query_count_table(mysqli $link, string $table): ?int
    {
        static $allowed = [
            'fact_characters',
            'dim_chapters',
            'fact_timeline_events',
            'fact_docs',
            'dim_chronicles',
            'dim_seasons',
            'fact_gifts',
            'dim_organizations',
            'dim_traits',
            'dim_merits_flaws',
            'dim_character_conditions',
            'dim_archetypes',
            'fact_combat_maneuvers',
        ];

        if (!in_array($table, $allowed, true)) {
            return null;
        }

        $result = mysqli_query($link, "SELECT COUNT(*) AS total FROM `{$table}`");
        if (!$result) {
            return null;
        }

        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        return isset($row['total']) ? (int)$row['total'] : 0;
    }
}

if (!function_exists('hg_home_query_counts')) {
    function hg_home_query_counts(mysqli $link): array
    {
        $counts = [
            'characters' => hg_home_query_count_table($link, 'fact_characters'),
            'chapters' => hg_home_query_count_table($link, 'dim_chapters'),
            'events' => hg_home_query_count_table($link, 'fact_timeline_events'),
            'documents' => hg_home_query_count_table($link, 'fact_docs'),
            'chronicles' => hg_home_query_count_table($link, 'dim_chronicles'),
            'seasons' => hg_home_query_count_table($link, 'dim_seasons'),
            'powers' => hg_home_query_count_table($link, 'fact_gifts'),
            'organizations' => hg_home_query_count_table($link, 'dim_organizations'),
        ];

        $ruleCounts = [
            hg_home_query_count_table($link, 'dim_traits'),
            hg_home_query_count_table($link, 'dim_merits_flaws'),
            hg_home_query_count_table($link, 'dim_character_conditions'),
            hg_home_query_count_table($link, 'dim_archetypes'),
            hg_home_query_count_table($link, 'fact_combat_maneuvers'),
        ];

        $counts['rules'] = in_array(null, $ruleCounts, true) ? null : array_sum($ruleCounts);
        return $counts;
    }
}

if (!function_exists('hg_home_query_latest_news')) {
    function hg_home_query_latest_news(mysqli $link): ?array
    {
        $stmt = mysqli_prepare(
            $link,
            'SELECT title, message, author, posted_at FROM fact_admin_posts ORDER BY posted_at DESC, id DESC LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }

        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? (mysqli_fetch_assoc($result) ?: null) : null;
        if ($result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $row;
    }
}
