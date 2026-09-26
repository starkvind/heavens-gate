<?php

if (!function_exists('hg_bibliography_fetch_entries')) {
    function hg_bibliography_fetch_entries(mysqli $link): ?array
    {
        $result = mysqli_query(
            $link,
            "SELECT id, name, year, description FROM dim_bibliographies ORDER BY sort_order"
        );

        if (!$result) {
            return null;
        }

        $entries = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $entries[] = $row;
        }
        mysqli_free_result($result);

        return $entries;
    }
}
