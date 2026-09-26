<?php

if (!function_exists('hg_news_count_posts')) {
    function hg_news_count_posts(mysqli $link): ?int
    {
        $result = mysqli_query($link, "SELECT COUNT(*) AS total FROM fact_admin_posts");
        if (!$result) {
            return null;
        }

        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        return (int)($row['total'] ?? 0);
    }
}

if (!function_exists('hg_news_fetch_posts')) {
    function hg_news_fetch_posts(mysqli $link, int $offset, int $limit): ?array
    {
        $stmt = mysqli_prepare(
            $link,
            "SELECT author, title, message, posted_at FROM fact_admin_posts ORDER BY id DESC LIMIT ?, ?"
        );
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'ii', $offset, $limit);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }

        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return null;
        }

        $posts = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $posts[] = $row;
        }

        mysqli_free_result($result);
        mysqli_stmt_close($stmt);

        return $posts;
    }
}
