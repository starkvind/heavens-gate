<?php

$datosDelArray = serialize($combateArray);

if (!function_exists('sim_escape')) {
    function sim_escape($value, $link)
    {
        return mysql_real_escape_string((string)$value, $link);
    }
}

if (!function_exists('sim_upsert_character_score')) {
    function sim_upsert_character_score($link, $characterId, $characterName, $wins, $draws, $losses, $battles, $points, $damageDealt, $damageTaken)
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

        $query = "INSERT INTO `fact_sim_character_scores` (`character_id`, `character_name_snapshot`, `wins`, `draws`, `losses`, `battles`, `points`, `damage_dealt`, `damage_taken`) VALUES ($characterId, '$snapshotName', $wins, $draws, $losses, $battles, $points, $damageDealt, $damageTaken)"
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
    function sim_insert_battle_log($link, $fighterOneId, $fighterTwoId, $fighterOneAlias, $fighterTwoAlias, $winnerSummary, $outcome, $requestIp, $timeLabel, $turnsPayload)
    {
        $fighterOneId = (int)$fighterOneId;
        $fighterTwoId = (int)$fighterTwoId;
        $fighterOneAlias = sim_escape($fighterOneAlias, $link);
        $fighterTwoAlias = sim_escape($fighterTwoAlias, $link);
        $winnerSummary = sim_escape($winnerSummary, $link);
        $outcome = sim_escape($outcome, $link);
        $requestIp = sim_escape($requestIp, $link);
        $timeLabel = sim_escape($timeLabel, $link);
        $turnsPayload = sim_escape($turnsPayload, $link);

        $query = "INSERT INTO `fact_sim_battles` (`fighter_one_character_id`, `fighter_two_character_id`, `fighter_one_alias_snapshot`, `fighter_two_alias_snapshot`, `winner_summary`, `outcome`, `request_ip`, `request_time_label`, `turns_payload`)"
            . " VALUES ($fighterOneId, $fighterTwoId, '$fighterOneAlias', '$fighterTwoAlias', '$winnerSummary', '$outcome', '$requestIp', '$timeLabel', '$turnsPayload')";

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
$fecha = date('H:i:d:m:Y');

if ($drau != 1) {
    $winnerDamageDealt = ($wina == $nombreCom1) ? (int)$heridas2 : (int)$heridas1;
    $winnerDamageTaken = ($wina == $nombreCom1) ? (int)$heridas1 : (int)$heridas2;
    $loserDamageDealt = ($losa == $nombreCom1) ? (int)$heridas2 : (int)$heridas1;
    $loserDamageTaken = ($losa == $nombreCom1) ? (int)$heridas1 : (int)$heridas2;

    sim_upsert_character_score($link, $winaID, $wina, 1, 0, 0, 1, 3, $winnerDamageDealt, $winnerDamageTaken);
    sim_upsert_character_score($link, $losaID, $losa, 0, 0, 1, 1, 0, $loserDamageDealt, $loserDamageTaken);

    sim_insert_battle_log(
        $link,
        (int)$idPJno1,
        (int)$idPJno2,
        $nombre1,
        $nombre2,
        "<b>Ganador:</b> $wina",
        'win',
        $ip,
        $fecha,
        $datosDelArray
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
    sim_upsert_character_score($link, $winaID, $wina, 0, 1, 0, 1, 1, (int)$heridas2, (int)$heridas1);
    sim_upsert_character_score($link, $losaID, $losa, 0, 1, 0, 1, 1, (int)$heridas1, (int)$heridas2);

    sim_insert_battle_log(
        $link,
        (int)$idPJno1,
        (int)$idPJno2,
        $nombre1,
        $nombre2,
        '<b>Empate</b>',
        'draw',
        $ip,
        $fecha,
        $datosDelArray
    );

    sim_upsert_item_usage($link, $idarma1 ?? 0, $arma1 ?? '', 1);
    sim_upsert_item_usage($link, $idarma2 ?? 0, $arma2 ?? '', 1);
}
