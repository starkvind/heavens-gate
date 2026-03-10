<?php
include_once('sim_character_scope.php');

$datosDelArray = serialize($combateArray);

if (!function_exists('sim_escape')) {
    function sim_escape($value, $link)
    {
        return mysql_real_escape_string((string)$value, $link);
    }
}

if (!function_exists('sim_upsert_character_score')) {
    function sim_score_columns($link)
    {
        static $columns = null;
        if (is_array($columns)) {
            return $columns;
        }

        $columns = array();
        $rs = mysql_query("SHOW COLUMNS FROM `fact_sim_character_scores`", $link);
        if ($rs) {
            while ($row = mysql_fetch_array($rs)) {
                $field = (string)($row['Field'] ?? '');
                if ($field !== '') {
                    $columns[$field] = true;
                }
            }
        }
        return $columns;
    }

    function sim_upsert_character_score($link, $characterId, $characterName, $wins, $draws, $losses, $battles, $points, $damageDealt, $damageTaken, $seasonId = 0)
    {
        $characterId = (int)$characterId;
        if ($characterId <= 0) {
            return;
        }

        $snapshotName = sim_escape($characterName, $link);
        $wins = (int)$wins;
        $draws = (int)$draws;
        $losses = (int)$losses;
        $battles = (int)$battles;
        $points = (int)$points;
        $damageDealt = (int)$damageDealt;
        $damageTaken = (int)$damageTaken;
        $seasonId = (int)$seasonId;
        if ($seasonId <= 0 && function_exists('sim_get_active_season_id')) {
            $seasonId = (int)sim_get_active_season_id($link);
        }

        $cols = sim_score_columns($link);
        $fields = array(
            '`character_id`',
            '`character_name_snapshot`',
            '`wins`',
            '`draws`',
            '`losses`',
            '`battles`',
            '`points`',
            '`damage_dealt`',
            '`damage_taken`'
        );
        $values = array(
            (string)$characterId,
            "'$snapshotName'",
            (string)$wins,
            (string)$draws,
            (string)$losses,
            (string)$battles,
            (string)$points,
            (string)$damageDealt,
            (string)$damageTaken
        );

        if (isset($cols['season_id'])) {
            if ($seasonId <= 0) {
                return;
            }
            $fields[] = '`season_id`';
            $values[] = (string)$seasonId;
        }

        $query = "INSERT INTO `fact_sim_character_scores` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")"
            . " ON DUPLICATE KEY UPDATE"
            . " `character_name_snapshot` = VALUES(`character_name_snapshot`),"
            . " `wins` = `wins` + VALUES(`wins`),"
            . " `draws` = `draws` + VALUES(`draws`),"
            . " `losses` = `losses` + VALUES(`losses`),"
            . " `battles` = `battles` + VALUES(`battles`),"
            . " `points` = `points` + VALUES(`points`),"
            . " `damage_dealt` = `damage_dealt` + VALUES(`damage_dealt`),"
            . " `damage_taken` = `damage_taken` + VALUES(`damage_taken`)";

        mysql_query($query, $link);
    }
}

if (!function_exists('sim_insert_battle_log')) {
    function sim_battle_columns($link)
    {
        static $columns = null;
        if (is_array($columns)) {
            return $columns;
        }

        $columns = array();
        $rs = mysql_query("SHOW COLUMNS FROM `fact_sim_battles`", $link);
        if ($rs) {
            while ($row = mysql_fetch_array($rs)) {
                $field = (string)($row['Field'] ?? '');
                if ($field !== '') {
                    $columns[$field] = true;
                }
            }
        }
        return $columns;
    }

    function sim_insert_battle_log($link, $fighterOneId, $fighterTwoId, $fighterOneAlias, $fighterTwoAlias, $winnerSummary, $winnerCharacterId, $outcome, $requestIp, $turnsPayload, $seasonId = 0)
    {
        $fighterOneId = (int)$fighterOneId;
        $fighterTwoId = (int)$fighterTwoId;
        $fighterOneAlias = sim_escape($fighterOneAlias, $link);
        $fighterTwoAlias = sim_escape($fighterTwoAlias, $link);
        $winnerSummary = sim_escape($winnerSummary, $link);
        $winnerCharacterId = (int)$winnerCharacterId;
        $outcome = sim_escape($outcome, $link);
        $requestIp = sim_escape($requestIp, $link);
        $turnsPayload = sim_escape($turnsPayload, $link);
        $seasonId = (int)$seasonId;

        $cols = sim_battle_columns($link);
        $fields = array(
            '`fighter_one_character_id`',
            '`fighter_two_character_id`',
            '`fighter_one_alias_snapshot`',
            '`fighter_two_alias_snapshot`'
        );
        $values = array(
            (string)$fighterOneId,
            (string)$fighterTwoId,
            "'$fighterOneAlias'",
            "'$fighterTwoAlias'"
        );

        if (isset($cols['winner_summary'])) {
            $fields[] = '`winner_summary`';
            $values[] = "'$winnerSummary'";
        }
        if (isset($cols['winner_character_id'])) {
            $fields[] = '`winner_character_id`';
            $values[] = ($winnerCharacterId > 0) ? (string)$winnerCharacterId : 'NULL';
        }
        if (isset($cols['outcome'])) {
            $fields[] = '`outcome`';
            $values[] = "'$outcome'";
        }
        if (isset($cols['request_ip'])) {
            $fields[] = '`request_ip`';
            $values[] = "'$requestIp'";
        }
        if (isset($cols['turns_payload'])) {
            $fields[] = '`turns_payload`';
            $values[] = "'$turnsPayload'";
        }
        if (isset($cols['season_id'])) {
            $fields[] = '`season_id`';
            $values[] = ($seasonId > 0) ? (string)$seasonId : 'NULL';
        }

        $query = "INSERT INTO `fact_sim_battles` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";

        mysql_query($query, $link);
    }
}

if (!function_exists('sim_upsert_item_usage')) {
    function sim_upsert_item_usage($link, $itemId, $itemName, $timesUsed)
    {
        $itemId = (int)$itemId;
        if ($itemId <= 0) {
            return;
        }

        $itemNameSnapshot = sim_escape($itemName, $link);
        $timesUsed = (int)$timesUsed;
        if ($timesUsed <= 0) {
            return;
        }

        $query = "INSERT INTO `fact_sim_item_usage` (`item_id`, `item_name_snapshot`, `times_used`) VALUES ($itemId, '$itemNameSnapshot', $timesUsed)"
            . " ON DUPLICATE KEY UPDATE"
            . " `item_name_snapshot` = VALUES(`item_name_snapshot`),"
            . " `times_used` = `times_used` + VALUES(`times_used`)";

        mysql_query($query, $link);
    }
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$scoreSeasonId = (int)($combateArray['season_id'] ?? 0);
if ($scoreSeasonId <= 0 && function_exists('sim_get_active_season_id')) {
    $scoreSeasonId = (int)sim_get_active_season_id($link);
}

if ($drau != 1) {
    $winnerDamageDealt = ($wina == $nombreCom1) ? (int)$heridas2 : (int)$heridas1;
    $winnerDamageTaken = ($wina == $nombreCom1) ? (int)$heridas1 : (int)$heridas2;
    $loserDamageDealt = ($losa == $nombreCom1) ? (int)$heridas2 : (int)$heridas1;
    $loserDamageTaken = ($losa == $nombreCom1) ? (int)$heridas1 : (int)$heridas2;

    sim_upsert_character_score($link, $winaID, $wina, 1, 0, 0, 1, 3, $winnerDamageDealt, $winnerDamageTaken, $scoreSeasonId);
    sim_upsert_character_score($link, $losaID, $losa, 0, 0, 1, 1, 0, $loserDamageDealt, $loserDamageTaken, $scoreSeasonId);

    sim_insert_battle_log(
        $link,
        (int)$idPJno1,
        (int)$idPJno2,
        $nombre1,
        $nombre2,
        "<b>Ganador:</b> $wina",
        (int)$winaID,
        'win',
        $ip,
        $datosDelArray,
        (int)($combateArray['season_id'] ?? 0)
    );

    sim_upsert_item_usage($link, $idarma1 ?? 0, $arma1 ?? '', 1);
    sim_upsert_item_usage($link, $idarma2 ?? 0, $arma2 ?? '', 1);

    return;
}

$wina = $nombreCom1;
$losa = $nombreCom2;
$winaID = $idPJno1;
$losaID = $idPJno2;

if ($nombreCom1 != $nombreCom2) {
    sim_upsert_character_score($link, $winaID, $wina, 0, 1, 0, 1, 1, (int)$heridas2, (int)$heridas1, $scoreSeasonId);
    sim_upsert_character_score($link, $losaID, $losa, 0, 1, 0, 1, 1, (int)$heridas1, (int)$heridas2, $scoreSeasonId);

    sim_insert_battle_log(
        $link,
        (int)$idPJno1,
        (int)$idPJno2,
        $nombre1,
        $nombre2,
        '<b>Empate</b>',
        0,
        'draw',
        $ip,
        $datosDelArray,
        (int)($combateArray['season_id'] ?? 0)
    );

    sim_upsert_item_usage($link, $idarma1 ?? 0, $arma1 ?? '', 1);
    sim_upsert_item_usage($link, $idarma2 ?? 0, $arma2 ?? '', 1);
}
