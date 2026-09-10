<?php

if (!function_exists('hg_players_normalize_chronicle_csv')) {
    function hg_players_normalize_chronicle_csv($csv): string
    {
        $csv = trim((string)$csv);
        if ($csv === '' || strtoupper($csv) === 'FALSE') {
            return '';
        }

        $ids = [];
        foreach (preg_split('/\s*,\s*/', $csv) as $part) {
            if (preg_match('/^\d+$/', (string)$part)) {
                $ids[] = (string)(int)$part;
            }
        }

        return implode(',', array_values(array_unique($ids)));
    }
}

if (!function_exists('hg_players_fetch_catalog')) {
    function hg_players_fetch_catalog(mysqli $link, $excludedChronicles = '2,7', bool $stableIdOrder = false)
    {
        $excluded = hg_players_normalize_chronicle_csv($excludedChronicles);
        $chronicleJoin = $excluded !== '' ? " AND c.chronicle_id NOT IN ($excluded) " : '';
        $order = $stableIdOrder
            ? 'p.name ASC, p.surname ASC, p.id ASC'
            : 'p.name ASC, p.surname ASC';

        $sql = "
            SELECT
                p.id AS player_id,
                COALESCE(p.pretty_id, '') AS player_pretty_id,
                COALESCE(p.name, '') AS player_name,
                COALESCE(p.surname, '') AS player_surname,
                COALESCE(p.picture, '') AS player_picture,
                COALESCE(p.description, '') AS player_description,
                COUNT(DISTINCT c.id) AS player_characters
            FROM dim_players p
            LEFT JOIN fact_characters c ON c.player_id = p.id {$chronicleJoin}
            WHERE p.show_in_catalog = 1
            GROUP BY p.id, p.pretty_id, p.name, p.surname, p.picture, p.description
            ORDER BY {$order}
        ";

        $result = mysqli_query($link, $sql);
        if (!$result) {
            return false;
        }

        $players = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $players[] = $row;
        }
        mysqli_free_result($result);

        return $players;
    }
}

if (!function_exists('hg_players_fetch_player')) {
    function hg_players_fetch_player(mysqli $link, int $playerId)
    {
        $stmt = mysqli_prepare(
            $link,
            "SELECT id, pretty_id, name, surname, picture, description
             FROM dim_players
             WHERE id = ? AND show_in_catalog = 1
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $playerId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }

        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return false;
        }

        $player = mysqli_fetch_assoc($result) ?: null;
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);

        return $player;
    }
}

if (!function_exists('hg_players_fetch_characters')) {
    function hg_players_fetch_characters(
        mysqli $link,
        int $playerId,
        $excludedChronicles = '2,7',
        bool $stableIdOrder = false
    ) {
        $excluded = hg_players_normalize_chronicle_csv($excludedChronicles);
        $chronicleWhere = $excluded !== '' ? " AND c.chronicle_id NOT IN ($excluded) " : '';
        $characterKindSql = function_exists('hg_character_kind_select')
            ? hg_character_kind_select($link, 'c')
            : "''";
        $order = $stableIdOrder ? 'c.name ASC, c.id ASC' : 'c.name ASC';

        $sql = "
            SELECT
                c.id,
                c.name,
                c.alias,
                c.image_url,
                c.gender,
                COALESCE(dcs.label, '') AS status,
                c.status_id,
                {$characterKindSql} AS character_kind
            FROM fact_characters c
            LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id
            WHERE c.player_id = ? {$chronicleWhere}
            ORDER BY {$order}
        ";

        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $playerId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }

        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return false;
        }

        $characters = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $characters[] = $row;
        }

        mysqli_free_result($result);
        mysqli_stmt_close($stmt);

        return $characters;
    }
}
