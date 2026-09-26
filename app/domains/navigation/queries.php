<?php

if (!function_exists('hg_navigation_fetch_parents')) {
    function hg_navigation_fetch_parents(mysqli $link): array
    {
        $rows = [];
        $sql = "SELECT id, label, icon, icon_hover, menu_key
                FROM dim_menu_items
                WHERE parent_id IS NULL AND enabled = 1
                ORDER BY sort_order, id";
        if ($result = mysqli_query($link, $sql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        return $rows;
    }
}

if (!function_exists('hg_navigation_fetch_children')) {
    function hg_navigation_fetch_children(mysqli $link, int $parentId): array
    {
        $rows = [];
        $sql = "SELECT id, label, href, target, item_type, dynamic_source, css_class
                FROM dim_menu_items
                WHERE parent_id = ? AND enabled = 1
                ORDER BY sort_order, id";
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) {
            return $rows;
        }

        mysqli_stmt_bind_param($stmt, 'i', $parentId);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $row;
                }
                mysqli_free_result($result);
            }
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_navigation_fetch_route_candidates')) {
    function hg_navigation_fetch_route_candidates(mysqli $link): array
    {
        $rows = [];
        $sql = "SELECT p.menu_key, c.href, c.item_type, c.dynamic_source
                FROM dim_menu_items c
                JOIN dim_menu_items p ON c.parent_id = p.id
                WHERE c.enabled = 1 AND p.enabled = 1";
        if ($result = mysqli_query($link, $sql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        return $rows;
    }
}

if (!function_exists('hg_navigation_fetch_seasons')) {
    function hg_navigation_fetch_seasons(mysqli $link, bool $personalStories): array
    {
        $rows = [];
        if ($personalStories) {
            $sql = "SELECT id, name, season_number, finished, season_kind
                    FROM dim_seasons
                    WHERE season_kind = 'historia_personal'
                    ORDER BY sort_order, season_number";
        } else {
            $sql = "SELECT id, name, season_number, finished, season_kind
                    FROM dim_seasons
                    WHERE season_kind IN ('temporada','inciso','especial')
                    ORDER BY FIELD(season_kind, 'temporada','inciso','especial'), sort_order, season_number";
        }

        if ($result = mysqli_query($link, $sql)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        return $rows;
    }
}

if (!function_exists('hg_navigation_season_label')) {
    function hg_navigation_season_label(array $row): string
    {
        $name = (string)($row['name'] ?? '');
        $number = (int)($row['season_number'] ?? 0);
        $kind = trim((string)($row['season_kind'] ?? 'temporada'));

        if ($kind === 'historia_personal' || $kind === 'especial') {
            return $name;
        }
        if ($kind === 'inciso') {
            $incisoNumber = ($number >= 100 && $number < 200) ? ($number - 100) : $number;
            return 'I' . $incisoNumber . ' - ' . $name;
        }
        return 'T' . $number . ' - ' . $name;
    }
}

if (!function_exists('hg_navigation_season_href')) {
    function hg_navigation_season_href(mysqli $link, array $row): string
    {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            return '/seasons';
        }
        return function_exists('pretty_url')
            ? pretty_url($link, 'dim_seasons', '/seasons', $id)
            : ('/seasons/' . $id);
    }
}

if (!function_exists('hg_navigation_season_items')) {
    function hg_navigation_season_items(mysqli $link, bool $personalStories): array
    {
        $items = [];
        foreach (hg_navigation_fetch_seasons($link, $personalStories) as $row) {
            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'label' => hg_navigation_season_label($row),
                'href' => hg_navigation_season_href($link, $row),
                'target' => '_self',
                'finished' => (int)($row['finished'] ?? 0),
                'season_kind' => (string)($row['season_kind'] ?? 'temporada'),
                'season_number' => (int)($row['season_number'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
            ];
        }
        return $items;
    }
}
