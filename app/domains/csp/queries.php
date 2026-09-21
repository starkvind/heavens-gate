<?php

if (!function_exists('hg_csp_fetch_posts')) {
    function hg_csp_fetch_posts(mysqli $link): ?array
    {
        $stmt = mysqli_prepare(
            $link,
            "SELECT author, title, message, posted_at FROM fact_csp_posts ORDER BY id DESC"
        );
        if (!$stmt) {
            return null;
        }

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
