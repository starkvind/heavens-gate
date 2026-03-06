<?php

$pageSect = 'Resultados del combate';

include_once('sim_character_scope.php');

if (!function_exists('sim_fetch_assoc_by_id')) {
    function sim_fetch_assoc_by_id($table, $id, $link)
    {
        if ($id === '') {
            return null;
        }

        $safeId = (int)$id;
        if ($safeId <= 0) {
            return null;
        }

        $query = "SELECT * FROM {$table} WHERE id = {$safeId} LIMIT 1";
        $result = mysql_query($query, $link);
        if (!$result || mysql_num_rows($result) === 0) {
            return null;
        }

        return mysql_fetch_array($result);
    }
}

if (!function_exists('sim_hidden_input')) {
    function sim_hidden_input($name, $value)
    {
        $safeName = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
        $safeValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        return "<input type='hidden' name='{$safeName}' value='{$safeValue}'>";
    }
}

if (!function_exists('sim_pick_pool_item')) {
    function sim_pick_pool_item($pool, $fallback = '')
    {
        if (!is_array($pool) || empty($pool)) {
            return (string)$fallback;
        }
        return (string)$pool[array_rand($pool)];
    }
}

if (!function_exists('sim_combat_mode_title')) {
    function sim_combat_mode_title($combatType, $narrativeTone)
    {
        $combatType = (string)$combatType;
        $narrativeTone = strtolower((string)$narrativeTone);

        $normalTitles = array(
            'serio' => 'Duelo a muerte',
            'epico' => 'Choque de leyendas',
            'brutal' => 'Carniceria total',
            'ironico' => 'Pelea de malas ideas',
            'random' => 'Combate a muerte'
        );

        $umbralTitles = array(
            'serio' => 'Duelo umbral',
            'epico' => 'Convergencia espiritual',
            'brutal' => 'Tormenta umbral',
            'ironico' => 'Turismo umbral extremo',
            'random' => 'Combate umbral'
        );

        $pool = ($combatType === 'umbral') ? $umbralTitles : $normalTitles;
        if (!isset($pool[$narrativeTone])) {
            $narrativeTone = 'random';
        }
        return (string)$pool[$narrativeTone];
    }
}

if (!function_exists('sim_rank_stars_html')) {
    function sim_rank_stars_html($score)
    {
        $stars = (int)ceil(((int)$score) / 7);
        if ($stars < 1) {
            $stars = 1;
        }
        if ($stars > 5) {
            $stars = 5;
        }
        return str_repeat('&#9733;', $stars);
    }
}

if (!function_exists('sim_gender_suffix')) {
    function sim_gender_suffix($genderCode)
    {
        $g = strtolower(trim((string)$genderCode));
        if ($g === 'm' || $g === 'male' || $g === 'man' || $g === 'masc') {
            return 'o';
        }
        if ($g === 'f' || $g === 'female' || $g === 'woman' || $g === 'fem') {
            return 'a';
        }
        if ($g === 'i' || $g === 'x' || $g === 'nb' || $g === 'n' || $g === 'nonbinary') {
            return 'e';
        }
        return 'e';
    }
}

if (!function_exists('sim_apply_gender_aware_phrase')) {
    function sim_apply_gender_aware_phrase($phrase, $genderCode)
    {
        $suffix = sim_gender_suffix($genderCode);
        $phrase = (string)$phrase;

        $tokenMap = array(
            'agotado' => 'agotad',
            'derrotado' => 'derrotad',
            'neutralizado' => 'neutralizad',
            'superado' => 'superad',
            'doblegado' => 'doblegad',
            'derribado' => 'derribad',
            'aplastado' => 'aplastad',
            'aniquilado' => 'aniquilad',
            'aliado' => 'aliad',
            'enemigo' => 'enemig'
        );

        foreach ($tokenMap as $fullWord => $stem) {
            $phrase = preg_replace('/\\b' . preg_quote($fullWord, '/') . '\\b/ui', $stem . $suffix, $phrase);
        }

        return $phrase;
    }
}

if (!function_exists('sim_normalize_relation_token')) {
    function sim_normalize_relation_token($value)
    {
        $value = strtolower((string)$value);
        $value = strtr($value, array(
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u'
        ));
        return trim($value);
    }
}

if (!function_exists('sim_relation_context_from_type')) {
    function sim_relation_context_from_type($relationType)
    {
        $type = sim_normalize_relation_token($relationType);
        if ($type === '') {
            return 'neutral';
        }

        $enemy = array('enemigo', 'asesino', 'traidor', 'extorsionador');
        $rival = array('rival');
        $ally = array('aliado', 'amigo', 'protegido', 'salvador');
        $romance = array('amante', 'pareja', 'vinculo');
        $family = array('padre', 'madre', 'hijo', 'hermano', 'abuelo', 'tio', 'primo');
        $hierarchy = array('mentor', 'superior', 'subordinado', 'amo', 'creacion');

        if (in_array($type, $enemy, true)) {
            return 'enemy';
        }
        if (in_array($type, $rival, true)) {
            return 'rival';
        }
        if (in_array($type, $ally, true)) {
            return 'ally';
        }
        if (in_array($type, $romance, true)) {
            return 'romance';
        }
        if (in_array($type, $family, true)) {
            return 'family';
        }
        if (in_array($type, $hierarchy, true)) {
            return 'hierarchy';
        }
        return 'neutral';
    }
}

if (!function_exists('sim_fetch_relation_between_characters')) {
    function sim_fetch_relation_between_characters($link, $characterOneId, $characterTwoId)
    {
        $id1 = (int)$characterOneId;
        $id2 = (int)$characterTwoId;
        if ($id1 <= 0 || $id2 <= 0) {
            return array('type' => '', 'context' => 'neutral');
        }
        if (!sim_table_exists($link, 'bridge_characters_relations')) {
            return array('type' => '', 'context' => 'neutral');
        }

        $query = "SELECT relation_type, COALESCE(importance, 0) AS importance"
            . " FROM bridge_characters_relations"
            . " WHERE (source_id = $id1 AND target_id = $id2)"
            . "    OR (source_id = $id2 AND target_id = $id1)"
            . " ORDER BY importance DESC, id DESC"
            . " LIMIT 1";

        $result = mysql_query($query, $link);
        if (!$result || mysql_num_rows($result) === 0) {
            return array('type' => '', 'context' => 'neutral');
        }

        $row = mysql_fetch_array($result);
        $relationType = (string)($row['relation_type'] ?? '');
        return array(
            'type' => $relationType,
            'context' => sim_relation_context_from_type($relationType)
        );
    }
}

if (!function_exists('sim_table_exists')) {
    function sim_table_exists($link, $tableName)
    {
        $safe = mysql_real_escape_string((string)$tableName, $link);
        $rs = mysql_query("SHOW TABLES LIKE '$safe'", $link);
        return ($rs && mysql_num_rows($rs) > 0);
    }
}

if (!function_exists('sim_pick_random_character_id')) {
    function sim_pick_random_character_id($link, $excludeId = 0)
    {
        $cronicaNotInSQL = sim_chronicle_not_in_sql('c.chronicle_id');
        $query = "SELECT v.id FROM vw_sim_characters v INNER JOIN fact_characters c ON c.id = v.id WHERE v.kes LIKE 'pj' $cronicaNotInSQL ORDER BY v.nombre";
        $result = mysql_query($query, $link);
        if (!$result) {
            return 0;
        }

        $pool = array();
        while ($row = mysql_fetch_array($result)) {
            $characterId = (int)($row['id'] ?? 0);
            if ($characterId <= 0 || $characterId === (int)$excludeId) {
                continue;
            }
            $pool[] = $characterId;
        }

        if (empty($pool)) {
            return 0;
        }

        return (int)$pool[array_rand($pool)];
    }
}

if (!function_exists('sim_fetch_character_system_id')) {
    function sim_fetch_character_system_id($link, $characterId)
    {
        $characterId = (int)$characterId;
        if ($characterId <= 0) {
            return 0;
        }

        $query = "SELECT system_id FROM fact_characters WHERE id = $characterId LIMIT 1";
        $result = mysql_query($query, $link);
        if (!$result || mysql_num_rows($result) === 0) {
            return 0;
        }

        $row = mysql_fetch_array($result);
        return (int)($row['system_id'] ?? 0);
    }
}

if (!function_exists('sim_fetch_character_gender')) {
    function sim_fetch_character_gender($link, $characterId)
    {
        $characterId = (int)$characterId;
        if ($characterId <= 0) {
            return '';
        }

        $query = "SELECT gender FROM fact_characters WHERE id = $characterId LIMIT 1";
        $result = mysql_query($query, $link);
        if (!$result || mysql_num_rows($result) === 0) {
            return '';
        }

        $row = mysql_fetch_array($result);
        return (string)($row['gender'] ?? '');
    }
}

if (!function_exists('sim_get_form_icon_map')) {
    function sim_get_form_icon_map($link)
    {
        $iconMap = array(
            1 => '&#128058;' // Garou
        );

        if (!sim_table_exists($link, 'bridge_systems_form_icons')) {
            return $iconMap;
        }

        $query = "SELECT system_id, icon_html FROM bridge_systems_form_icons";
        $result = mysql_query($query, $link);
        if (!$result) {
            return $iconMap;
        }

        while ($row = mysql_fetch_array($result)) {
            $sid = (int)($row['system_id'] ?? 0);
            $icon = (string)($row['icon_html'] ?? '');
            if ($sid > 0 && $icon !== '') {
                $iconMap[$sid] = $icon;
            }
        }
        mysql_free_result($result);

        return $iconMap;
    }
}

if (!function_exists('sim_form_icon_for_system')) {
    function sim_form_icon_for_system($systemId, $iconMap)
    {
        $systemId = (int)$systemId;
        if (isset($iconMap[$systemId])) {
            return (string)$iconMap[$systemId];
        }
        return '&#128062;';
    }
}

$postedPj1 = $_POST['pj1'] ?? '';
$postedPj2 = $_POST['pj2'] ?? '';

$characterOneId = $postedPj1;
$characterTwoId = $postedPj2;

$randomCharactersToggle = $_POST['aleatorio'] ?? '';
$randomFormsToggle = $_POST['formarandom'] ?? '';
$randomWeaponsToggle = $_POST['armasrandom'] ?? '';
$randomArmorsToggle = $_POST['protrandom'] ?? '';
$combatType = $_POST['combate'] ?? 'normal';
$tipoCombate = $combatType;
$randomTurnsToggle = $_POST['turnrandom'] ?? '';
$randomHealthToggle = $_POST['vitrandom'] ?? '';

$aplicarHeridas = $_POST['usarheridas'] ?? 'yes';
$usarRegen = $_POST['regeneracion'] ?? 'no';
$simNarrativeTone = $_POST['narrative_tone'] ?? 'random';
$simAmbientMessagesEnabled = $_POST['ambient_msgs'] ?? 'yes';
$simRubberbandingEnabled = $_POST['rubberbanding'] ?? 'yes';

$allowedNarrativeTones = array('random', 'serio', 'epico', 'brutal', 'ironico');
if (!in_array($simNarrativeTone, $allowedNarrativeTones, true)) {
    $simNarrativeTone = 'random';
}
$simAmbientMessagesEnabled = ($simAmbientMessagesEnabled === 'no') ? 'no' : 'yes';
$simRubberbandingEnabled = ($simRubberbandingEnabled === 'no') ? 'no' : 'yes';
$combatModeTitle = sim_combat_mode_title($combatType, $simNarrativeTone);

$characterOneId = ($characterOneId === 'random') ? '' : $characterOneId;
$characterTwoId = ($characterTwoId === 'random') ? '' : $characterTwoId;

if ($randomCharactersToggle === 'yes' || $characterOneId === '' || $characterTwoId === '') {
    if ($characterOneId === '') {
        $characterOneId = sim_pick_random_character_id($link, 0);
    }
    if ($characterTwoId === '') {
        $characterTwoId = sim_pick_random_character_id($link, (int)$characterOneId);
    }
    if ((int)$characterOneId > 0 && (int)$characterOneId === (int)$characterTwoId) {
        $characterTwoId = sim_pick_random_character_id($link, (int)$characterOneId);
    }
}

$row1 = sim_fetch_assoc_by_id('vw_sim_characters', $characterOneId, $link);
$row2 = sim_fetch_assoc_by_id('vw_sim_characters', $characterTwoId, $link);

$nombreCom1 = $row1['nombre'] ?? '';
$nombreCom2 = $row2['nombre'] ?? '';
$nombre1 = $row1['alias'] ?? '';
$nombre2 = $row2['alias'] ?? '';
$fera1 = $row1['fera'] ?? '';
$fera2 = $row2['fera'] ?? '';
$sistema1 = $row1['sistema'] ?? '';
$sistema2 = $row2['sistema'] ?? '';
$idPJno1 = (int)($row1['id'] ?? 0);
$idPJno2 = (int)($row2['id'] ?? 0);
$systemId1 = sim_fetch_character_system_id($link, $idPJno1);
$systemId2 = sim_fetch_character_system_id($link, $idPJno2);
$gender1 = sim_fetch_character_gender($link, $idPJno1);
$gender2 = sim_fetch_character_gender($link, $idPJno2);
$simRelationInfo = sim_fetch_relation_between_characters($link, $idPJno1, $idPJno2);
$simRelationType = (string)($simRelationInfo['type'] ?? '');
$simRelationContext = (string)($simRelationInfo['context'] ?? 'neutral');

if ($idPJno1 > 0 && !sim_is_character_allowed($link, $idPJno1)) {
    echo "<center>El personaje seleccionado pertenece a una cronica retirada.<br/><br/>"
        . "<input class='boton1' type='button' onclick='history.go(-1)' value='Volver'/></center>";
    return;
}
if ($idPJno2 > 0 && !sim_is_character_allowed($link, $idPJno2)) {
    echo "<center>El rival seleccionado pertenece a una cronica retirada.<br/><br/>"
        . "<input class='boton1' type='button' onclick='history.go(-1)' value='Volver'/></center>";
    return;
}

$podesluchar = 1;
include('script_rate_limit.php');
if ($podesluchar != 1) {
    $rateLimitMessage = isset($simRateLimitMessage) && $simRateLimitMessage !== ''
        ? $simRateLimitMessage
        : 'Has alcanzado el limite de uso para esta IP. Vuelve a intentarlo mas tarde.';

    echo "<center>$rateLimitMessage<br/><br/>"
        . "<input class='boton1' type='button' onclick='history.go(-1)' value='Volver'/></center>";
    return;
}

if ($nombreCom1 === $nombreCom2 && $nombreCom1 !== '') {
    echo "<center>El combate entre dos $nombreCom2 ($nombre2) no puede ocurrir...<br/><br/>"
        . "<input class='boton1' type='button' onclick='history.go(-1)' value='Volver'/></center>";
    return;
}

if ($nombre1 === '' || $nombre2 === '') {
    echo "<center>Elige dos personajes diferentes para que puedan luchar...<br/><br/>"
        . "<input class='boton1' type='button' onclick='history.go(-1)' value='Volver'/></center>";
    return;
}

if (($sistema1 === 'Vampiro' || $sistema2 === 'Vampiro') && $combatType === 'umbral') {
    echo "<center>Uno de los personajes elegidos no puede combatir en la Umbra.<br/><br/>"
        . "<input class='boton1' type='button' onclick='history.go(-1)' value='Volver'/></center>";
    return;
}

$arma1 = $_POST['arma1'] ?? '';
$arma2 = $_POST['arma2'] ?? '';
$arma11 = $arma1;
$arma21 = $arma2;
if ($randomWeaponsToggle === 'yes') {
    include('battle_pick_random_weapons.php');
}

$protec1 = $_POST['protec1'] ?? '';
$protec2 = $_POST['protec2'] ?? '';
$protec11 = $protec1;
$protec21 = $protec2;
if ($randomArmorsToggle === 'yes') {
    include('battle_pick_random_armors.php');
}

if ($randomFormsToggle !== 'yes') {
    $forma1 = $_POST['forma1'] ?? 'Hominido';
    $forma2 = $_POST['forma2'] ?? 'Hominido';
} else {
    include('battle_pick_random_forms.php');
}

$maxturn = ($randomTurnsToggle === 'yes') ? rand(1, 99) : (int)($_POST['turnos'] ?? 5);
$vitmax = ($randomHealthToggle === 'yes') ? rand(1, 99) : (int)($_POST['vit'] ?? 7);

$debug = $_POST['debug'] ?? 'no';
$ventaja = $_POST['ventaja'] ?? 'no';

$heridas1 = 0;
$heridas2 = 0;
$drau = 0;

include('text_defeat_messages.php');
$n1cae = "&iexcl;$nombre1 " . sim_apply_gender_aware_phrase($kae, $gender1) . "!";
$n2cae = "&iexcl;$nombre2 " . sim_apply_gender_aware_phrase($kae, $gender2) . "!";
$doubleKO = sim_pick_pool_item($simDoubleKoMessages ?? array(), '&iexcl;Los dos combatientes se han matado el uno al otro!');
$mensajefin = sim_pick_pool_item($simTimeLimitMessages ?? array(), '&iexcl;Tiempo! Combate terminado.');

$weapon1 = sim_fetch_assoc_by_id('vw_sim_items', $arma11, $link);
$weapon2 = sim_fetch_assoc_by_id('vw_sim_items', $arma21, $link);
$armorRow1 = sim_fetch_assoc_by_id('vw_sim_items', $protec11, $link);
$armorRow2 = sim_fetch_assoc_by_id('vw_sim_items', $protec21, $link);

$skillCheck4Form1 = $weapon1['habilidad'] ?? '';
$bonus1 = (int)($weapon1['bonus'] ?? 0);
$danyo1 = $weapon1['dano'] ?? 0;
$plata1 = $weapon1['metal'] ?? 0;
$arma1 = $weapon1['name'] ?? '';

$skillCheck4Form2 = $weapon2['habilidad'] ?? '';
$bonus2 = (int)($weapon2['bonus'] ?? 0);
$danyo2 = $weapon2['dano'] ?? 0;
$plata2 = $weapon2['metal'] ?? 0;
$arma2 = $weapon2['name'] ?? '';

$armor1 = (int)($armorRow1['bonus'] ?? 0);
$malusArmor1 = (int)($armorRow1['destreza'] ?? 0);
$protec1 = $armorRow1['name'] ?? '';

$armor2 = (int)($armorRow2['bonus'] ?? 0);
$malusArmor2 = (int)($armorRow2['destreza'] ?? 0);
$protec2 = $armorRow2['name'] ?? '';

$char1 = sim_fetch_assoc_by_id('vw_sim_characters', $idPJno1, $link);
$char2 = sim_fetch_assoc_by_id('vw_sim_characters', $idPJno2, $link);

$fuerza1 = (int)($char1['fuerza'] ?? 0);
$destre1 = (int)($char1['destreza'] ?? 0);
$resist1 = (int)($char1['resistencia'] ?? 0);
$astuci1 = (int)($char1['astucia'] ?? 0);
$atletismo1 = (int)($char1['atletismo'] ?? 0);
$pelea1 = (int)($char1['pelea'] ?? 0);
$esquivar1 = (int)($char1['esquivar'] ?? 0);
$esquivar12 = $esquivar1;
$armascc1 = (int)($char1['armascc'] ?? 0);
$armasdefuego1 = (int)($char1['armasdefuego'] ?? 0);
$rabia1 = (int)($char1['rabiap'] ?? 0);
$gnosis1 = (int)($char1['gnosisp'] ?? 0);
$fvp1 = (int)($char1['fvp'] ?? 0);
$img1 = $char1['img'] ?? '';

$fuerza2 = (int)($char2['fuerza'] ?? 0);
$destre2 = (int)($char2['destreza'] ?? 0);
$resist2 = (int)($char2['resistencia'] ?? 0);
$astuci2 = (int)($char2['astucia'] ?? 0);
$atletismo2 = (int)($char2['atletismo'] ?? 0);
$pelea2 = (int)($char2['pelea'] ?? 0);
$esquivar2 = (int)($char2['esquivar'] ?? 0);
$esquivar22 = $esquivar2;
$armascc2 = (int)($char2['armascc'] ?? 0);
$armasdefuego2 = (int)($char2['armasdefuego'] ?? 0);
$rabia2 = (int)($char2['rabiap'] ?? 0);
$gnosis2 = (int)($char2['gnosisp'] ?? 0);
$fvp2 = (int)($char2['fvp'] ?? 0);
$img2 = $char2['img'] ?? '';

$rankScore1 = $fuerza1 + $destre1 + $pelea1 + $armascc1 + $armasdefuego1 + $esquivar1 + $resist1;
$rankScore2 = $fuerza2 + $destre2 + $pelea2 + $armascc2 + $armasdefuego2 + $esquivar2 + $resist2;
$rankStars1 = sim_rank_stars_html($rankScore1);
$rankStars2 = sim_rank_stars_html($rankScore2);

$hp1 = max(1, (int)$vitmax);
$hp2 = $hp1;

include('battle_apply_forms.php');

if ($fuerza1 <= 0) { $fuerza1 = 1; }
if ($fuerza2 <= 0) { $fuerza2 = 1; }
if ($ventaja === 'pj1') { $hp1 += 10; }
if ($ventaja === 'pj2') { $hp2 += 10; }

include('app/partials/main_nav_bar.php');
include('battle_resolve_weapon_skills.php');

$combateArray = array();
$combateArray['id1'] = $idPJno1;
$combateArray['stats1'] = "$fuerza1 , $destre1 , $resist1";
$combateArray['umbra1'] = "$rabia1, $gnosis1, $fvp1";
$combateArray['id2'] = $idPJno2;
$combateArray['stats2'] = "$fuerza2 , $destre2 , $resist2";
$combateArray['umbra2'] = "$rabia2, $gnosis2, $fvp2";
$combateArray['arma1'] = $arma1;
$combateArray['bonusarma1'] = $bonus1;
$combateArray['prot1'] = $protec1;
$combateArray['bonusprot1'] = $armor1;
$combateArray['malusprot1'] = $malusArmor1;
$combateArray['forma1'] = $forma1;
$combateArray['arma2'] = $arma2;
$combateArray['bonusarma2'] = $bonus2;
$combateArray['prot2'] = $protec2;
$combateArray['bonusprot2'] = $armor2;
$combateArray['malusprot2'] = $malusArmor2;
$combateArray['forma2'] = $forma2;
$combateArray['skill1'] = $skillz1b;
$combateArray['skill1value'] = $suma1;
$combateArray['skill2'] = $skillz2b;
$combateArray['skill2value'] = $suma2;
$combateArray['tipocombate'] = $combatType;
$combateArray['narrative_tone'] = $simNarrativeTone;
$combateArray['rubberbanding'] = $simRubberbandingEnabled;
$combateArray['combat_mode_label'] = $combatModeTitle;
$combateArray['relation_type'] = $simRelationType;
$combateArray['relation_context'] = $simRelationContext;

$rnd1 = rand(1, 10);
$rnd2 = rand(1, 10);

if ($combatType !== 'umbral') {
    $iniciativa1 = $destre1 + $astuci1 + $rnd1;
    $iniciativa2 = $destre2 + $astuci2 + $rnd2;

    $destre1 = max(1, $destre1 - $malusArmor1);
    $destre2 = max(1, $destre2 - $malusArmor2);

    $atacar1 = $destre1 + $suma1;
    $esquivar1 = $destre1 + $esquivar1;

    $atacar2 = $destre2 + $suma2;
    $esquivar2 = $destre2 + $esquivar2;
} else {
    $iniciativa1 = $rnd1;
    $iniciativa2 = $rnd2;

    $atacar1 = $gnosis1;
    $esquivar1 = 0;
    $fuerza1 = $rabia1;
    $resist1 = $fvp1;

    $atacar2 = $gnosis2;
    $esquivar2 = 0;
    $fuerza2 = $rabia2;
    $resist2 = $fvp2;
}

$hpact1 = $hp1;
$hpact2 = $hp2;
$turnos = 0;

$combateIniciativa = "<div id='celdaInicioCombateIz'><p id='paIniCom'>Iniciativa: $iniciativa1</p><p id='paIniCom'>Puntos de salud: $hp1</p></div>"
    . "<div id='celdaInicioCombateDe'><p id='paIniCom'>Iniciativa: $iniciativa2</p><p id='paIniCom'>Puntos de salud: $hp2</p></div>";
$combateArray['iniciativa'] = $combateIniciativa;

$tiradasFalliJ1 = 0;
$tiradasExitoJ1 = 0;
$tiradasFalliJ2 = 0;
$tiradasExitoJ2 = 0;

include('text_intro_messages.php');
$combateArray['fraseinicio'] = $quote[$echo] ?? '';

$safeForma1 = htmlspecialchars((string)$forma1, ENT_QUOTES, 'UTF-8');
$safeForma2 = htmlspecialchars((string)$forma2, ENT_QUOTES, 'UTF-8');
$safeArma1 = htmlspecialchars((string)($arma1 !== '' ? $arma1 : 'Sin arma'), ENT_QUOTES, 'UTF-8');
$safeArma2 = htmlspecialchars((string)($arma2 !== '' ? $arma2 : 'Sin arma'), ENT_QUOTES, 'UTF-8');
$safeProt1 = htmlspecialchars((string)($protec1 !== '' ? $protec1 : 'Ninguno'), ENT_QUOTES, 'UTF-8');
$safeProt2 = htmlspecialchars((string)($protec2 !== '' ? $protec2 : 'Ninguno'), ENT_QUOTES, 'UTF-8');
$formIconMap = sim_get_form_icon_map($link);
$formIconP1 = sim_form_icon_for_system($systemId1, $formIconMap);
$formIconP2 = sim_form_icon_for_system($systemId2, $formIconMap);
$loadoutP1 = "<div class='sim-loadout-summary'><span class='sim-loadout-pill'>&#9876; $safeArma1</span><span class='sim-loadout-pill'>&#128737; $safeProt1</span><span class='sim-loadout-pill'>$formIconP1 $safeForma1</span></div>";
$loadoutP2 = "<div class='sim-loadout-summary'><span class='sim-loadout-pill'>&#9876; $safeArma2</span><span class='sim-loadout-pill'>&#128737; $safeProt2</span><span class='sim-loadout-pill'>$formIconP2 $safeForma2</span></div>";
?>
<div class="sim-ui">
    <h2>Resultados del Combate</h2>
    <table>
        <tr>
            <td class="ajustcelda" colspan="4" style="text-align:center;text-transform:uppercase;font-weight:bold;">
                <?php echo htmlspecialchars((string)$combatModeTitle, ENT_QUOTES, 'UTF-8'); ?>
            </td>
        </tr>
        <tr>
            <td class="ajustcelda" colspan="2"><?php echo "<center><a href='/characters/$idPJno1' target='_blank'><img class='photobio sim-combat-avatar' src='$img1' title='$nombreCom1'></a></center>"; ?></td>
            <td class="ajustcelda" colspan="2"><?php echo "<center><a href='/characters/$idPJno2' target='_blank'><img class='photobio sim-combat-avatar' src='$img2' title='$nombreCom2'></a></center>"; ?></td>
        </tr>
        <tr>
            <td class="ajustcelda" colspan="2"><?php echo "$nombreCom1 <hr style='border: 1px solid #009;'/> $nombre1<div class='sim-result-rank'>$rankStars1</div>"; ?></td>
            <td class="ajustcelda" colspan="2"><?php echo "$nombreCom2 <hr style='border: 1px solid #009;'/> $nombre2<div class='sim-result-rank'>$rankStars2</div>"; ?></td>
        </tr>
        <tr>
            <td class="ajustcelda" colspan="2"><?php echo $loadoutP1; ?></td>
            <td class="ajustcelda" colspan="2"><?php echo $loadoutP2; ?></td>
        </tr>
        <tr><td colspan="4" class="ajustcelda"><?php echo $combateArray['fraseinicio']; ?></td></tr>
        <tr><td colspan="4" class="ajustcelda"><?php echo $combateArray['iniciativa']; ?></td></tr>
        <?php
        include('battle_turns.php');

        if ($hpact1 <= 0) { $hpact1 = 0; }
        if ($hpact2 <= 0) { $hpact2 = 0; }

        if ($hpact1 < $hpact2) {
            $wina = $nombreCom2;
            $winaID = $idPJno2;
            $losa = $nombreCom1;
            $losaID = $idPJno1;
            $winnerAlias = $nombre2;
            $winnerGender = $gender2;
            $winnerRemainingHp = (int)$hpact2;
            $loserRemainingHp = (int)$hpact1;
        } elseif ($hpact1 > $hpact2) {
            $wina = $nombreCom1;
            $winaID = $idPJno1;
            $losa = $nombreCom2;
            $losaID = $idPJno2;
            $winnerAlias = $nombre1;
            $winnerGender = $gender1;
            $winnerRemainingHp = (int)$hpact1;
            $loserRemainingHp = (int)$hpact2;
        } else {
            $drau = 1;
            $wina = $nombreCom1;
            $losa = $nombreCom2;
            $winaID = $idPJno1;
            $losaID = $idPJno2;
            $ganador = sim_pick_pool_item($simDrawMessages ?? array(), 'El combate termina en empate.');
            $winnerAlias = '';
            $winnerGender = '';
            $winnerRemainingHp = 0;
            $loserRemainingHp = 0;
        }

        if ($drau !== 1) {
            $hpDiff = abs($hpact1 - $hpact2);
            $victoryBucket = 'standard';
            if ($winnerRemainingHp <= 1) {
                $victoryBucket = 'comeback';
            } elseif ($hpDiff <= 1) {
                $victoryBucket = 'close';
            } elseif ($hpDiff >= 4) {
                $victoryBucket = 'dominant';
            }

            $victoryPool = $simVictoryMessages[$victoryBucket] ?? array();
            if (isset($simRelationVictoryMessages[$simRelationContext]) && is_array($simRelationVictoryMessages[$simRelationContext])) {
                $victoryPool = array_merge($simRelationVictoryMessages[$simRelationContext], $victoryPool);
            }
            $template = sim_pick_pool_item($victoryPool, '&iexcl;%s derrota a su rival!');
            $ganador = sprintf($template, htmlspecialchars((string)$winnerAlias, ENT_QUOTES, 'UTF-8'));
            $ganador = sim_apply_gender_aware_phrase($ganador, $winnerGender);
        }

        $heridasFinales = "Vitalidad de $nombre1: $hpact1 ($hp1 - $heridas1) | Vitalidad de $nombre2: $hpact2 ($hp2 - $heridas2)";
        $combateArray['ganador'] = $ganador;
        $combateArray['heridas'] = $heridasFinales;

        include('battle_finalize.php');

        if ($drau == 1) {
            $finalIcon = '&#9878;';
            $finalTitle = 'Empate';
            $finalSubtitle = htmlspecialchars((string)$ganador, ENT_QUOTES, 'UTF-8');
        } else {
            $finalIcon = '&#127942;';
            $finalTitle = htmlspecialchars((string)$wina, ENT_QUOTES, 'UTF-8');
            $finalSubtitle = 'Victoria';
        }
        $finalVitals = htmlspecialchars((string)$heridasFinales, ENT_QUOTES, 'UTF-8');

        echo "<tr><td colspan='4' class='ajustcelda'>"
            . "<div class='sim-final-card'>"
            . "<div class='sim-final-icon'>$finalIcon</div>"
            . "<div class='sim-final-title'>$finalTitle</div>"
            . "<div class='sim-final-subtitle'>$finalSubtitle</div>"
            . "<div class='sim-final-vitals'>$finalVitals</div>"
            . "</div>"
            . "</td></tr>";
        ?>
    </table>

    <?php
    $rematchUsesRandomCharacters = (
        $randomCharactersToggle === 'yes'
        || $postedPj1 === ''
        || $postedPj2 === ''
        || $postedPj1 === 'random'
        || $postedPj2 === 'random'
    );
    $rematchPj1Value = ($randomCharactersToggle === 'yes' || $postedPj1 === '' || $postedPj1 === 'random')
        ? 'random'
        : (string)(int)$idPJno1;
    $rematchPj2Value = ($randomCharactersToggle === 'yes' || $postedPj2 === '' || $postedPj2 === 'random')
        ? 'random'
        : (string)(int)$idPJno2;
    ?>

    <div class="sim-rematch-row sim-final-actions">
        <form method="get" action="/tools/combat-simulator" class="sim-rematch-form">
            <button type="submit" class="boton1 sim-final-action-btn" title="Simulador de Combate">Volver</button>
        </form>

        <form method="post" action="/tools/combat-simulator/result" class="sim-rematch-form">
            <?php
            if ($rematchUsesRandomCharacters) {
                if ($randomCharactersToggle === 'yes') {
                    echo sim_hidden_input('aleatorio', 'yes');
                }
                echo sim_hidden_input('pj1', $rematchPj1Value);
                echo sim_hidden_input('pj2', $rematchPj2Value);

                if ($randomWeaponsToggle === 'yes') {
                    echo sim_hidden_input('armasrandom', 'yes');
                }
                if ($randomArmorsToggle === 'yes') {
                    echo sim_hidden_input('protrandom', 'yes');
                }
                if ($randomFormsToggle === 'yes') {
                    echo sim_hidden_input('formarandom', 'yes');
                }
            } else {
                echo sim_hidden_input('pj1', (int)$idPJno1);
                echo sim_hidden_input('pj2', (int)$idPJno2);
                echo sim_hidden_input('arma1', (int)$arma11);
                echo sim_hidden_input('arma2', (int)$arma21);
                echo sim_hidden_input('protec1', (int)$protec11);
                echo sim_hidden_input('protec2', (int)$protec21);
                echo sim_hidden_input('forma1', $forma1);
                echo sim_hidden_input('forma2', $forma2);
            }

            echo sim_hidden_input('turnos', (int)$maxturn);
            echo sim_hidden_input('vit', (int)$vitmax);
            echo sim_hidden_input('usarheridas', $aplicarHeridas);
            echo sim_hidden_input('regeneracion', $usarRegen);
            echo sim_hidden_input('combate', $combatType);
            echo sim_hidden_input('narrative_tone', $simNarrativeTone);
            echo sim_hidden_input('ambient_msgs', $simAmbientMessagesEnabled);
            echo sim_hidden_input('rubberbanding', $simRubberbandingEnabled);
            ?>
            <button type="submit" class="boton1 sim-final-action-btn">Otra vez</button>
        </form>
    </div>

    <script>
    (function() {
        var turns = Array.prototype.slice.call(document.querySelectorAll('.sim-turn-fieldset'));
        if (!turns.length) { return; }

        turns.forEach(function(turn) {
            turn.classList.add('is-pending');
            turn.classList.remove('is-visible');
        });

        var idx = 0;
        function showNextTurn() {
            if (idx >= turns.length) { return; }
            turns[idx].classList.remove('is-pending');
            turns[idx].classList.add('is-visible');
            idx += 1;
            setTimeout(showNextTurn, 800);
        }

        setTimeout(showNextTurn, 220);
    })();
    </script>
</div>
