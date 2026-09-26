<?php

if (!function_exists('hg_home_query_counts')) {
    function hg_home_query_counts(mysqli $link): array
    {
        $sql = "
            SELECT
                (SELECT COUNT(*) FROM fact_characters) AS characters,
                (SELECT COUNT(*) FROM dim_chapters) AS chapters,
                (SELECT COUNT(*) FROM fact_timeline_events) AS events,
                (SELECT COUNT(*) FROM fact_docs) AS documents,
                (SELECT COUNT(*) FROM dim_chronicles) AS chronicles,
                (SELECT COUNT(*) FROM dim_seasons) AS seasons,
                (SELECT COUNT(*) FROM fact_gifts) AS powers,
                (SELECT COUNT(*) FROM dim_organizations) AS organizations,
                (
                    (SELECT COUNT(*) FROM dim_traits)
                    + (SELECT COUNT(*) FROM dim_merits_flaws)
                    + (SELECT COUNT(*) FROM dim_character_conditions)
                    + (SELECT COUNT(*) FROM dim_archetypes)
                    + (SELECT COUNT(*) FROM fact_combat_maneuvers)
                ) AS rules
        ";

        $result = mysqli_query($link, $sql);
        if (!$result) {
            return [
                'characters' => null,
                'chapters' => null,
                'events' => null,
                'documents' => null,
                'chronicles' => null,
                'seasons' => null,
                'powers' => null,
                'organizations' => null,
                'rules' => null,
            ];
        }

        $row = mysqli_fetch_assoc($result) ?: [];
        mysqli_free_result($result);

        $keys = [
            'characters',
            'chapters',
            'events',
            'documents',
            'chronicles',
            'seasons',
            'powers',
            'organizations',
            'rules',
        ];
        $counts = [];
        foreach ($keys as $key) {
            $counts[$key] = array_key_exists($key, $row) ? (int)$row[$key] : null;
        }
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
