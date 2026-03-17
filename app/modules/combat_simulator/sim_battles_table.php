<?php
include_once("app/helpers/character_avatar.php");

if (!function_exists('sim_btl_h')) {
    function sim_btl_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sim_btl_table_exists')) {
    function sim_btl_table_exists($link, $tableName)
    {
        $safe = mysql_real_escape_string((string)$tableName, $link);
        $rs = mysql_query("SHOW TABLES LIKE '$safe'", $link);
        return ($rs && mysql_num_rows($rs) > 0);
    }
}

if (!function_exists('sim_btl_column_exists')) {
    function sim_btl_column_exists($link, $tableName, $columnName)
    {
        $safeTable = mysql_real_escape_string((string)$tableName, $link);
        $safeCol = mysql_real_escape_string((string)$columnName, $link);
        $rs = mysql_query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeCol'", $link);
        return ($rs && mysql_num_rows($rs) > 0);
    }
}

if (!function_exists('sim_btl_character_slug_by_id')) {
    function sim_btl_character_slug_by_id($link, $characterId)
    {
        static $cache = array();
        static $hasPrettyId = null;

        $characterId = (int)$characterId;
        if ($characterId <= 0) {
            return '';
        }
        if (isset($cache[$characterId])) {
            return (string)$cache[$characterId];
        }

        if ($hasPrettyId === null) {
            $hasPrettyId = sim_btl_column_exists($link, 'fact_characters', 'pretty_id');
        }

        $slug = (string)$characterId;
        if ($hasPrettyId) {
            $query = "SELECT COALESCE(NULLIF(pretty_id, ''), CAST(id AS CHAR)) AS slug FROM fact_characters WHERE id = $characterId LIMIT 1";
            $rs = mysql_query($query, $link);
            if ($rs && mysql_num_rows($rs) > 0) {
                $row = mysql_fetch_array($rs);
                $value = trim((string)($row['slug'] ?? ''));
                if ($value !== '') {
                    $slug = $value;
                }
            }
            if ($rs) {
                mysql_free_result($rs);
            }
        }

        $cache[$characterId] = $slug;
        return $slug;
    }
}

if (!function_exists('sim_btl_character_meta_by_id')) {
    function sim_btl_character_meta_by_id($link, $characterId)
    {
        static $cache = array();

        $characterId = (int)$characterId;
        if ($characterId <= 0) {
            return null;
        }
        if (isset($cache[$characterId])) {
            return $cache[$characterId];
        }

        $query = "SELECT COALESCE(alias, '') AS alias, COALESCE(nombre, '') AS nombre, COALESCE(img, '') AS img FROM vw_sim_characters WHERE id = $characterId LIMIT 1";
        $rs = mysql_query($query, $link);
        if (!$rs || mysql_num_rows($rs) === 0) {
            $cache[$characterId] = null;
            return null;
        }

        $row = mysql_fetch_array($rs);
        $alias = trim((string)($row['alias'] ?? ''));
        $name = trim((string)($row['nombre'] ?? ''));
        $imgRaw = trim((string)($row['img'] ?? ''));
        $img = function_exists('hg_character_avatar_url') ? hg_character_avatar_url($imgRaw, '') : $imgRaw;
        $slug = sim_btl_character_slug_by_id($link, $characterId);
        $meta = array(
            'id' => $characterId,
            'alias' => $alias,
            'name' => $name,
            'img' => $img,
            'slug' => $slug
        );
        $cache[$characterId] = $meta;
        return $meta;
    }
}

if (!function_exists('sim_btl_name_snapshot')) {
    function sim_btl_name_snapshot(array $row, $aliasKey, $nameKey)
    {
        $name = trim((string)($row[$aliasKey] ?? ''));
        if ($name === '' && $nameKey !== '') {
            $name = trim((string)($row[$nameKey] ?? ''));
        }
        return $name;
    }
}

if (!function_exists('sim_btl_fight_avatar_html')) {
    function sim_btl_fight_avatar_html($link, $characterId, $fallbackName)
    {
        $characterId = (int)$characterId;
        $meta = sim_btl_character_meta_by_id($link, $characterId);
        $displayName = trim((string)$fallbackName);
        $avatarUrl = '';

        if (is_array($meta)) {
            $metaName = trim((string)($meta['alias'] ?? ''));
            if ($metaName === '') {
                $metaName = trim((string)($meta['name'] ?? ''));
            }
            if ($metaName !== '') {
                $displayName = $metaName;
            }
            $avatarUrl = trim((string)($meta['img'] ?? ''));
        }

        if ($displayName === '') {
            $displayName = ($characterId > 0) ? ('ID ' . $characterId) : 'Combatiente';
        }
        if ($avatarUrl === '') {
            $avatarUrl = function_exists('hg_character_avatar_url') ? hg_character_avatar_url('', '') : '';
        }

        $safeName = sim_btl_h($displayName);
        $safeImg = sim_btl_h($avatarUrl);
        $tipAttrs = ($characterId > 0) ? " class='sim-fight-row-avatar-wrap hg-tooltip' data-tip='character' data-id='{$characterId}'" : " class='sim-fight-row-avatar-wrap'";
        return "<span{$tipAttrs}><img class='sim-fight-row-avatar' src='{$safeImg}' alt=''></span>";
    }
}

if (!function_exists('sim_btl_tournament_by_key')) {
    function sim_btl_tournament_by_key($link, $tournamentKey)
    {
        static $cache = array();
        static $tableReady = null;

        $tournamentKey = trim((string)$tournamentKey);
        if ($tournamentKey === '') {
            return null;
        }
        if (isset($cache[$tournamentKey])) {
            return $cache[$tournamentKey];
        }

        if ($tableReady === null) {
            $tableReady = sim_btl_table_exists($link, 'fact_sim_tournaments');
        }
        if (!$tableReady) {
            $cache[$tournamentKey] = null;
            return null;
        }

        $safeKey = mysql_real_escape_string($tournamentKey, $link);
        $query = "SELECT id, COALESCE(name, '') AS name FROM fact_sim_tournaments WHERE tournament_key = '$safeKey' ORDER BY id DESC LIMIT 1";
        $rs = mysql_query($query, $link);
        if (!$rs || mysql_num_rows($rs) === 0) {
            $cache[$tournamentKey] = null;
            return null;
        }

        $row = mysql_fetch_array($rs);
        $out = array(
            'id' => (int)($row['id'] ?? 0),
            'name' => trim((string)($row['name'] ?? ''))
        );
        $cache[$tournamentKey] = $out;
        return $out;
    }
}

if (!function_exists('sim_btl_fight_cell_html')) {
    function sim_btl_fight_cell_html($link, array $row)
    {
        $kid = (int)($row['id'] ?? 0);
        $fighterOneId = (int)($row['fighter_one_character_id'] ?? 0);
        $fighterTwoId = (int)($row['fighter_two_character_id'] ?? 0);
        $name1 = sim_btl_name_snapshot($row, 'fighter_one_alias_snapshot', 'fighter_one_name_snapshot');
        $name2 = sim_btl_name_snapshot($row, 'fighter_two_alias_snapshot', 'fighter_two_name_snapshot');
        $avatar1 = sim_btl_fight_avatar_html($link, $fighterOneId, $name1);
        $avatar2 = sim_btl_fight_avatar_html($link, $fighterTwoId, $name2);
        $safe1 = sim_btl_h($name1 !== '' ? $name1 : 'P1');
        $safe2 = sim_btl_h($name2 !== '' ? $name2 : 'P2');

        return "<a class='sim-fight-row-link' href='/tools/combat-simulator/log/$kid'>"
            . "<span class='sim-fight-row-side sim-fight-row-side--p1'>{$avatar1}<span class='sim-fight-row-name'>{$safe1}</span></span>"
            . "<span class='sim-fight-row-vs'>VS</span>"
            . "<span class='sim-fight-row-side sim-fight-row-side--p2'><span class='sim-fight-row-name'>{$safe2}</span>{$avatar2}</span>"
            . "</a>";
    }
}

if (!function_exists('sim_btl_type_html')) {
    function sim_btl_type_html($link, array $row)
    {
        $tournamentKey = trim((string)($row['tournament_key'] ?? ''));
        $isTournament = ((int)($row['is_tournament'] ?? 0) > 0) || ($tournamentKey !== '');
        if (!$isTournament) {
            return "Combate libre";
        }

        $tournamentInfo = sim_btl_tournament_by_key($link, $tournamentKey);
        if (is_array($tournamentInfo) && (int)($tournamentInfo['id'] ?? 0) > 0) {
            $tid = (int)$tournamentInfo['id'];
            $tname = trim((string)($tournamentInfo['name'] ?? ''));
            if ($tname === '') {
                $tname = 'Torneo #' . $tid;
            }
            return "<a href='/tools/combat-simulator/tournament?tid=$tid'>Combate de " . sim_btl_h($tname) . "</a>";
        }
        return "Combate de torneo";
    }
}

if (!function_exists('sim_btl_winner_avatar_html')) {
    function sim_btl_winner_avatar_html($link, $winnerCharacterId)
    {
        $winnerCharacterId = (int)$winnerCharacterId;
        if ($winnerCharacterId <= 0) {
            return '';
        }

        $meta = sim_btl_character_meta_by_id($link, $winnerCharacterId);
        if (!is_array($meta)) {
            return '';
        }

        $name = trim((string)($meta['alias'] ?? ''));
        if ($name === '') {
            $name = trim((string)($meta['name'] ?? ''));
        }
        $img = trim((string)($meta['img'] ?? ''));
        $slug = trim((string)($meta['slug'] ?? ''));
        if ($name === '' || $img === '' || $slug === '') {
            return '';
        }

        $safeName = sim_btl_h($name);
        $safeImg = sim_btl_h($img);
        $safeHref = '/characters/' . rawurlencode($slug);
        return "<a class='sim-winner-avatar-link hg-tooltip' data-tip='character' data-id='{$winnerCharacterId}' href='{$safeHref}'><img class='sim-winner-avatar16' src='{$safeImg}' alt='{$safeName}'></a>";
    }
}

if (!function_exists('sim_btl_result_text')) {
    function sim_btl_result_text(array $row)
    {
        $outcome = strtolower(trim((string)($row['outcome'] ?? '')));
        if ($outcome === 'draw') {
            return 'Empate';
        }

        $winnerName = sim_btl_name_snapshot($row, 'winner_alias_snapshot', 'winner_name_snapshot');
        return ($winnerName !== '') ? $winnerName : 'Ganador';
    }
}

if (!function_exists('sim_btl_result_html')) {
    function sim_btl_result_html($link, array $row)
    {
        $winnerCharacterId = (int)($row['winner_character_id'] ?? 0);
        $winnerAvatarHtml = sim_btl_winner_avatar_html($link, $winnerCharacterId);
        $safeText = sim_btl_h(sim_btl_result_text($row));
        if ($winnerAvatarHtml !== '') {
            return "<span class='sim-result-wrap'>{$winnerAvatarHtml}<span class='sim-result-text'>{$safeText}</span></span>";
        }
        return "<span class='sim-result-text'>{$safeText}</span>";
    }
}

if (!function_exists('sim_btl_render_table')) {
    function sim_btl_render_table($link, array $rows, array $opts = array())
    {
        $emptyText = (string)($opts['empty_text'] ?? "A&uacute;n no se ha celebrado ning&uacute;n combate.");
        if (count($rows) === 0) {
            echo $emptyText;
            return;
        }

        echo "<table class='sim-combats-table'>";
        foreach ($rows as $row) {
            $kid = (int)($row['id'] ?? 0);
            $fightHtml = sim_btl_fight_cell_html($link, $row);
            $typeHtml = sim_btl_type_html($link, $row);
            $resultHtml = sim_btl_result_html($link, $row);

            echo "<tr>"
                . "<td class='sim-col-id'>#<a href='/tools/combat-simulator/log/$kid'>$kid</a></td>"
                . "<td class='sim-col-fight'>{$fightHtml}</td>"
                . "<td class='sim-col-type'>{$typeHtml}</td>"
                . "<td class='sim-col-result'>{$resultHtml}</td>"
                . "</tr>";
        }
        echo "</table>";
    }
}
