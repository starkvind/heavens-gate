<?php

if (!function_exists('sim_wound_penalty')) {
    function sim_wound_penalty($wounds, $applyWounds, $maxHp = 7)
    {
        if ($applyWounds === 'no') {
            return 0;
        }
        $wounds = (int)$wounds;
        $maxHp = max(1, (int)$maxHp);

        // Keep the classic 7-HP behavior; scale proportionally for extended HP pools.
        $effectiveWounds = $wounds;
        if ($maxHp > 7) {
            $effectiveWounds = (int)floor(($wounds * 7) / $maxHp);
        }

        if ($effectiveWounds <= 1) {
            return 0;
        }
        if ($effectiveWounds <= 3) {
            return 1;
        }
        if ($effectiveWounds <= 5) {
            return 2;
        }
        return 5;
    }
}

if (!function_exists('sim_roll_successes')) {
    function sim_roll_successes($diceCount)
    {
        $diceCount = (int)$diceCount;
        if ($diceCount <= 0) {
            return 0;
        }
        $successes = 0;
        $botches = 0;
        for ($i = 0; $i < $diceCount; $i++) {
            $roll = rand(0, 9);
            if ($roll === 1) {
                $botches++;
            } elseif ($roll >= 6 || $roll === 0) {
                $successes++;
            }
        }
        $total = $successes - $botches;
        if ($total < 0) {
            return 0;
        }
        return $total;
    }
}

if (!function_exists('sim_attack_phrase_type')) {
    function sim_attack_phrase_type($skillKey, $combatType)
    {
        if ((string)$combatType === 'umbral') {
            return 5;
        }
        switch ((string)$skillKey) {
            case 'armasdefuego':
                return 3;
            case 'atletismo':
                return 4;
            default:
                return 1;
        }
    }
}

if (!function_exists('sim_pick_attack_phrase')) {
    function sim_pick_attack_phrase($phraseType)
    {
        static $lastAttackByType = array();
        switch ((int)$phraseType) {
            case 3:
                $pool = array(
                    'dispara',
                    'pega un tiro',
                    'fusila',
                    'tirotea',
                    'intenta alcanzar',
                    'apunta a la cabeza',
                    'abre fuego',
                    'hace una r&aacute;faga corta',
                    'busca un hueco para disparar',
                    'aprieta el gatillo sin titubear'
                );
                break;
            case 4:
                $pool = array(
                    'ataca',
                    'agrede',
                    'lanza un ataque r&aacute;pido',
                    'entra con fuerza',
                    'busca el impacto',
                    'carga contra su rival'
                );
                break;
            case 5:
                $pool = array(
                    'lanza una bola de energia',
                    'proyecta fuerza espiritual',
                    'asalta el espiritu',
                    'invoca una descarga umbral',
                    'desata energ&iacute;a primordial',
                    'golpea con una onda espiritual'
                );
                break;
            default:
                $pool = array(
                    'agrede',
                    'ataca',
                    'golpea',
                    'amenaza',
                    'prueba a golpear',
                    'se tira sobre su enemigo',
                    'descarga su furia',
                    'llega con un golpe seco',
                    'presiona sin descanso',
                    'entra con una combinaci&oacute;n agresiva',
                    'ataca con violencia contenida'
                );
                break;
        }
        $typeKey = (int)$phraseType;
        $candidatePool = $pool;
        $last = $lastAttackByType[$typeKey] ?? '';
        if ($last !== '' && count($candidatePool) > 1) {
            $candidatePool = array_values(array_filter($candidatePool, function ($item) use ($last) {
                return (string)$item !== (string)$last;
            }));
            if (empty($candidatePool)) {
                $candidatePool = $pool;
            }
        }

        $picked = (string)$candidatePool[array_rand($candidatePool)];
        $lastAttackByType[$typeKey] = $picked;
        return $picked;
    }
}

if (!function_exists('sim_pick_fail_phrase')) {
    function sim_pick_fail_phrase($phraseType)
    {
        static $lastFailByType = array();
        switch ((int)$phraseType) {
            case 3:
                $pool = array(
                    'falla el tiro',
                    've frustrado su disparo',
                    'tiene mala punter&iacute;a',
                    'falla el disparo',
                    'el proyectil se pierde',
                    'dispara, pero no consigue impacto'
                );
                break;
            case 4:
                $pool = array(
                    'falla',
                    'no acierta',
                    'golpea el suelo',
                    'pierde el equilibrio al atacar',
                    'se queda a medias por muy poco'
                );
                break;
            case 5:
                $pool = array(
                    'no hace da&ntilde;o',
                    'no es eficaz',
                    've como su ataque es atenuado por completo',
                    'su energ&iacute;a se disipa sin efecto',
                    'su ofensiva espiritual se desvanece'
                );
                break;
            default:
                $pool = array(
                    'falla su ataque',
                    'falla estrepitosamente',
                    've frustrada su agresi&oacute;n',
                    'no consigue impactar',
                    'golpea al aire',
                    'se precipita y no conecta',
                    'se queda a las puertas del impacto',
                    'su golpe no encuentra al objetivo'
                );
                break;
        }
        $typeKey = (int)$phraseType;
        $candidatePool = $pool;
        $last = $lastFailByType[$typeKey] ?? '';
        if ($last !== '' && count($candidatePool) > 1) {
            $candidatePool = array_values(array_filter($candidatePool, function ($item) use ($last) {
                return (string)$item !== (string)$last;
            }));
            if (empty($candidatePool)) {
                $candidatePool = $pool;
            }
        }

        $picked = (string)$candidatePool[array_rand($candidatePool)];
        $lastFailByType[$typeKey] = $picked;
        return $picked;
    }
}

if (!function_exists('sim_rubber_bonus_from_streak')) {
    function sim_rubber_cfg_int($link, $configName, $defaultValue)
    {
        $defaultValue = (int)$defaultValue;
        if (!is_resource($link)) {
            return $defaultValue;
        }

        $safeName = mysql_real_escape_string((string)$configName, $link);
        $query = "SELECT config_value FROM dim_web_configuration WHERE config_name = '$safeName' ORDER BY id DESC LIMIT 1";
        $result = mysql_query($query, $link);
        if (!$result || mysql_num_rows($result) === 0) {
            return $defaultValue;
        }

        $row = mysql_fetch_array($result);
        $raw = (string)($row['config_value'] ?? $defaultValue);
        if (!is_numeric($raw)) {
            return $defaultValue;
        }

        return (int)$raw;
    }

    function sim_rubber_cfg_settings()
    {
        static $settings = null;
        if (is_array($settings)) {
            return $settings;
        }

        global $link;
        $step = sim_rubber_cfg_int($link, 'combat_simulator_rubberbanding_failures_per_bonus', 2);
        $maxDice = sim_rubber_cfg_int($link, 'combat_simulator_rubberbanding_max_bonus_dice', 3);

        if ($step < 1) {
            $step = 1;
        }
        if ($maxDice < 0) {
            $maxDice = 0;
        }
        if ($maxDice > 12) {
            $maxDice = 12;
        }

        $settings = array(
            'failures_per_bonus' => $step,
            'max_bonus_dice' => $maxDice
        );
        return $settings;
    }

    function sim_rubber_bonus_from_streak($failStreak)
    {
        $failStreak = (int)$failStreak;
        $cfg = sim_rubber_cfg_settings();
        $step = (int)$cfg['failures_per_bonus'];
        $maxDice = (int)$cfg['max_bonus_dice'];

        if ($maxDice <= 0) {
            return 0;
        }
        if ($failStreak < $step) {
            return 0;
        }

        // +1 die every N consecutive failed attacks, capped by config.
        $bonus = (int)floor($failStreak / $step);
        if ($bonus > $maxDice) {
            $bonus = $maxDice;
        }
        return $bonus;
    }
}

if (!function_exists('sim_rubber_dice_markup')) {
    function sim_rubber_dice_markup($bonusDice)
    {
        $bonusDice = (int)$bonusDice;
        if ($bonusDice <= 0) {
            return '';
        }
        if ($bonusDice > 8) {
            $bonusDice = 8;
        }

        $icons = str_repeat('&#127922;', $bonusDice);
        return "<br/><span class='sim-rubber-dice' title='Rubberbanding +{$bonusDice} dado(s)'>{$icons}</span>";
    }
}

if (!function_exists('sim_regen_step')) {
    function sim_regen_step(&$currentHp, &$wounds, $regenEnabled, $regenAmount, $maxHp)
    {
        if ((int)$regenEnabled !== 1) {
            return false;
        }
        if ((int)$currentHp <= 0) {
            return false;
        }
        $regenAmount = (int)$regenAmount;
        if ($regenAmount <= 0) {
            return false;
        }

        $nextHp = (int)$currentHp + $regenAmount;
        if ((int)$wounds > 0) {
            $wounds -= $regenAmount;
            if ((int)$wounds < 0) {
                $wounds = 0;
            }
        }

        if ($nextHp > (int)$maxHp) {
            return false;
        }

        $currentHp = $nextHp;
        return true;
    }
}

if (!function_exists('sim_resolve_single_attack')) {
    function sim_resolve_single_attack($attackerName, $attackerWeaponName, $skillKey, $combatType, $attackerDex, $attackerSkill, $attackerWounds, $defenderDodge, $defenderWounds, $attackerStrength, $weaponBonus, $defenderResistance, $defenderArmor, $soakDisabled, $debugMode, $attackerMaxHp = 7, $defenderMaxHp = 7, $rubberBonusDice = 0)
    {
        $woundPenaltyAtk = sim_wound_penalty($attackerWounds, $GLOBALS['aplicarHeridas'] ?? 'yes', (int)$attackerMaxHp);
        $woundPenaltyDef = sim_wound_penalty($defenderWounds, $GLOBALS['aplicarHeridas'] ?? 'yes', (int)$defenderMaxHp);

        $attackDice = (int)$attackerDex + (int)$attackerSkill - $woundPenaltyAtk + (int)$rubberBonusDice;
        $dodgeDice = (int)$defenderDodge - $woundPenaltyDef;
        if ($attackDice < 0) {
            $attackDice = 0;
        }
        if ($dodgeDice < 0) {
            $dodgeDice = 0;
        }

        $attackSuccess = sim_roll_successes($attackDice);
        $dodgeSuccess = sim_roll_successes($dodgeDice);
        $netAttack = $attackSuccess - $dodgeSuccess;
        if ($netAttack < 0) {
            $netAttack = 0;
        }

        $damageDone = 0;
        if ($netAttack > 0) {
            if ($skillKey === 'armasdefuego' || $skillKey === 'atletismo') {
                $damageDice = (int)$weaponBonus + $netAttack;
            } else {
                $damageDice = (int)$attackerStrength + (int)$weaponBonus + $netAttack;
            }
            if ($damageDice < 0) {
                $damageDice = 0;
            }

            $damageSuccess = sim_roll_successes($damageDice);
            $soakBase = ((int)$soakDisabled === 1) ? 0 : (int)$defenderResistance;
            $soakDice = $soakBase + (int)$defenderArmor;
            if ($soakDice < 0) {
                $soakDice = 0;
            }
            $soakSuccess = sim_roll_successes($soakDice);
            $damageDone = $damageSuccess - $soakSuccess;
            if ($damageDone < 0) {
                $damageDone = 0;
            }
        }

        $phraseType = sim_attack_phrase_type($skillKey, $combatType);
        $attackPhrase = sim_pick_attack_phrase($phraseType);
        $failPhrase = sim_pick_fail_phrase($phraseType);

        if ($attackerWeaponName !== '' && $attackerWeaponName !== '0') {
            $attackText = $attackerName . ' ' . $attackPhrase . ' con ' . $attackerWeaponName;
        } else {
            $attackText = $attackerName . ' ' . $attackPhrase;
        }

        if ($damageDone > 0) {
            $resultText = ' y causa <b>' . $damageDone . ' puntos de da&ntilde;o</b>.';
        } else {
            $resultText = ', pero ' . $failPhrase . '.';
        }

        $debugText = '';
        if ($debugMode === 'si') {
            $debugText = "<br/><small>ATK {$attackDice}d ({$attackSuccess}) vs DODGE {$dodgeDice}d ({$dodgeSuccess}) | NET {$netAttack}</small>";
        }

        return array(
            'damage' => $damageDone,
            'attackText' => $attackText,
            'resultText' => $resultText,
            'debug' => $debugText,
            'rubberBonus' => (int)$rubberBonusDice
        );
    }
}

if (!function_exists('sim_turn_health_markup')) {
    function sim_wound_drop_markup($penaltyDice)
    {
        $penaltyDice = (int)$penaltyDice;
        if ($penaltyDice <= 0) {
            return '';
        }

        if ($penaltyDice > 5) {
            $penaltyDice = 5;
        }

        $drops = str_repeat('&#129656;', $penaltyDice);
        return " <span class='sim-hp-penalty' title='Penalizador por heridas'>{$drops}</span>";
    }

    function sim_turn_health_markup($turnNumber, $name1, $hp1Current, $hp1Max, $name2, $hp2Current, $hp2Max, $wounds1, $wounds2, $applyWounds)
    {
        $hp1Current = max(0, (int)$hp1Current);
        $hp2Current = max(0, (int)$hp2Current);
        $hp1Max = max(1, (int)$hp1Max);
        $hp2Max = max(1, (int)$hp2Max);
        $wounds1 = max(0, (int)$wounds1);
        $wounds2 = max(0, (int)$wounds2);
        $applyWounds = (string)$applyWounds;

        $p1 = max(0, min(100, round(($hp1Current * 100) / $hp1Max, 2)));
        $p2 = max(0, min(100, round(($hp2Current * 100) / $hp2Max, 2)));
        $penalty1 = sim_wound_penalty($wounds1, $applyWounds, $hp1Max);
        $penalty2 = sim_wound_penalty($wounds2, $applyWounds, $hp2Max);
        $penaltyHtml1 = ($applyWounds === 'yes')
            ? sim_wound_drop_markup($penalty1)
            : '';
        $penaltyHtml2 = ($applyWounds === 'yes')
            ? sim_wound_drop_markup($penalty2)
            : '';

        return ""
            . "<div class='sim-turn-health-row'>"
            . "  <div class='sim-hp-card'>"
            . "    <div class='sim-hp-head'>" . htmlspecialchars((string)$name1, ENT_QUOTES, 'UTF-8') . $penaltyHtml1 . " <span>{$hp1Current}/{$hp1Max}</span></div>"
            . "    <div class='sim-hp-track'><span class='sim-hp-fill sim-hp-fill-p1' style='width: {$p1}%;'></span></div>"
            . "  </div>"
            . "  <div class='sim-hp-card'>"
            . "    <div class='sim-hp-head'>" . htmlspecialchars((string)$name2, ENT_QUOTES, 'UTF-8') . $penaltyHtml2 . " <span>{$hp2Current}/{$hp2Max}</span></div>"
            . "    <div class='sim-hp-track'><span class='sim-hp-fill sim-hp-fill-p2' style='width: {$p2}%;'></span></div>"
            . "  </div>"
            . "</div>";
    }
}

if (!function_exists('sim_emit_turn_health')) {
    function sim_emit_turn_health($turnNumber, $name1, $hp1Current, $hp1Max, $name2, $hp2Current, $hp2Max, $wounds1, $wounds2, $applyWounds, &$combateArray)
    {
        $html = sim_turn_health_markup($turnNumber, $name1, $hp1Current, $hp1Max, $name2, $hp2Current, $hp2Max, $wounds1, $wounds2, $applyWounds);
        echo "<div class='sim-turn-health-wrap'>{$html}</div>";
        $combateArray["healthTurn{$turnNumber}"] = $html;
        $combateArray["hp1Turn{$turnNumber}"] = max(0, (int)$hp1Current);
        $combateArray["hp2Turn{$turnNumber}"] = max(0, (int)$hp2Current);
    }
}

$maxturn = max(1, (int)$maxturn);
$turnos = isset($turnos) ? (int)$turnos : 0;

$iniciativa1 = isset($iniciativa1) ? (int)$iniciativa1 : 0;
$iniciativa2 = isset($iniciativa2) ? (int)$iniciativa2 : 0;

$hpact1 = isset($hpact1) ? (int)$hpact1 : 0;
$hpact2 = isset($hpact2) ? (int)$hpact2 : 0;
$heridas1 = isset($heridas1) ? (int)$heridas1 : 0;
$heridas2 = isset($heridas2) ? (int)$heridas2 : 0;

$debug = isset($debug) ? (string)$debug : 'no';
$tipoCombate = isset($tipoCombate) ? (string)$tipoCombate : 'normal';
$skillz1a = isset($skillz1a) ? (string)$skillz1a : 'pelea';
$skillz2a = isset($skillz2a) ? (string)$skillz2a : 'pelea';
$nombre1 = isset($nombre1) ? (string)$nombre1 : 'Fighter 1';
$nombre2 = isset($nombre2) ? (string)$nombre2 : 'Fighter 2';
$arma1 = isset($arma1) ? (string)$arma1 : '';
$arma2 = isset($arma2) ? (string)$arma2 : '';
$forma1 = isset($forma1) ? (string)$forma1 : 'Hominido';
$forma2 = isset($forma2) ? (string)$forma2 : 'Hominido';
$vitmax = isset($vitmax) ? (int)$vitmax : max($hpact1, $hpact2, 1);
$hp1Max = max(1, (int)($hp1 ?? $vitmax));
$hp2Max = max(1, (int)($hp2 ?? $vitmax));

$destre1 = isset($destre1) ? (int)$destre1 : 0;
$destre2 = isset($destre2) ? (int)$destre2 : 0;
$suma1 = isset($suma1) ? (int)$suma1 : 0;
$suma2 = isset($suma2) ? (int)$suma2 : 0;
$esquivar1 = isset($esquivar1) ? (int)$esquivar1 : 0;
$esquivar2 = isset($esquivar2) ? (int)$esquivar2 : 0;
$fuerza1 = isset($fuerza1) ? (int)$fuerza1 : 1;
$fuerza2 = isset($fuerza2) ? (int)$fuerza2 : 1;
$bonus1 = isset($bonus1) ? (int)$bonus1 : 0;
$bonus2 = isset($bonus2) ? (int)$bonus2 : 0;
$resist1 = isset($resist1) ? (int)$resist1 : 0;
$resist2 = isset($resist2) ? (int)$resist2 : 0;
$armor1 = isset($armor1) ? (int)$armor1 : 0;
$armor2 = isset($armor2) ? (int)$armor2 : 0;

$tiradasFalliJ1 = isset($tiradasFalliJ1) ? (int)$tiradasFalliJ1 : 0;
$tiradasExitoJ1 = isset($tiradasExitoJ1) ? (int)$tiradasExitoJ1 : 0;
$tiradasFalliJ2 = isset($tiradasFalliJ2) ? (int)$tiradasFalliJ2 : 0;
$tiradasExitoJ2 = isset($tiradasExitoJ2) ? (int)$tiradasExitoJ2 : 0;

$danger1 = isset($danger1) ? (int)$danger1 : 0;
$danger2 = isset($danger2) ? (int)$danger2 : 0;

$regenYesOrNotPj1 = isset($regenYesOrNotPj1) ? (int)$regenYesOrNotPj1 : 0;
$regenYesOrNotPj2 = isset($regenYesOrNotPj2) ? (int)$regenYesOrNotPj2 : 0;
$cantidadRegenPj1 = isset($cantidadRegenPj1) ? (int)$cantidadRegenPj1 : 0;
$cantidadRegenPj2 = isset($cantidadRegenPj2) ? (int)$cantidadRegenPj2 : 0;
$usarRegen = isset($usarRegen) ? (string)$usarRegen : 'no';
$simCrowdMessages = (isset($simCrowdMessages) && is_array($simCrowdMessages)) ? $simCrowdMessages : array();
$simLateTurnMessages = (isset($simLateTurnMessages) && is_array($simLateTurnMessages)) ? $simLateTurnMessages : array();
$simAmbientMessagesEnabled = isset($simAmbientMessagesEnabled) ? (string)$simAmbientMessagesEnabled : 'yes';
$simRubberbandingEnabled = isset($simRubberbandingEnabled) ? (string)$simRubberbandingEnabled : 'yes';
$rubberFailStreakP1 = 0;
$rubberFailStreakP2 = 0;

$turnOrder = 0; // 1: primero pj1, 2: primero pj2, 0: simultaneo
if ($iniciativa1 > $iniciativa2) {
    $turnOrder = 1;
} elseif ($iniciativa2 > $iniciativa1) {
    $turnOrder = 2;
}

$finalMessagePrinted = false;
echo "<tr><td colspan='4' class=''><br/>";

while ($hpact1 > 0 && $hpact2 > 0 && $turnos < $maxturn) {
    $turnos++;
    $combateArray["turnos"] = $turnos;

    if ($turnos > 1) {
        $regenMsg1 = "!$nombre1, al estar en forma $forma1, regenera <b><u>$cantidadRegenPj1</u> puntos de da&ntilde;o</b>!";
        $regenMsg2 = "!$nombre2, al estar en forma $forma2, regenera <b><u>$cantidadRegenPj2</u> puntos de da&ntilde;o</b>!";

        $didRegen1 = false;
        $didRegen2 = false;

        if ($usarRegen === 'pj1' || $usarRegen === 'ambos') {
            $didRegen1 = sim_regen_step($hpact1, $heridas1, $regenYesOrNotPj1, $cantidadRegenPj1, $vitmax);
        }
        if ($usarRegen === 'pj2' || $usarRegen === 'ambos') {
            $didRegen2 = sim_regen_step($hpact2, $heridas2, $regenYesOrNotPj2, $cantidadRegenPj2, $vitmax);
        }

        if ($turnOrder === 2) {
            if ($didRegen2) {
                echo "<tr><td colspan='4' class='celdacombat'>$regenMsg2</td></tr>";
                $combateArray["regenAtacante$turnos"] = $regenMsg2;
            }
            if ($didRegen1) {
                echo "<tr><td colspan='4' class='celdacombat2'>$regenMsg1</td></tr>";
                $combateArray["regenDefensor$turnos"] = $regenMsg1;
            }
        } else {
            if ($didRegen1) {
                echo "<tr><td colspan='4' class='celdacombat'>$regenMsg1</td></tr>";
                $combateArray["regenAtacante$turnos"] = $regenMsg1;
            }
            if ($didRegen2) {
                echo "<tr><td colspan='4' class='celdacombat2'>$regenMsg2</td></tr>";
                $combateArray["regenDefensor$turnos"] = $regenMsg2;
            }
        }
    }

    echo "<fieldset class='sim-turn-fieldset' id='sim-turn-$turnos'><legend>Turno $turnos</legend>";

    if ($turnOrder === 0) {
        $rubberBonusP1 = ($simRubberbandingEnabled === 'yes') ? sim_rubber_bonus_from_streak($rubberFailStreakP1) : 0;
        $rubberBonusP2 = ($simRubberbandingEnabled === 'yes') ? sim_rubber_bonus_from_streak($rubberFailStreakP2) : 0;
        $roll1 = sim_resolve_single_attack(
            $nombre1,
            $arma1,
            $skillz1a,
            $tipoCombate,
            $destre1,
            $suma1,
            $heridas1,
            $esquivar2,
            $heridas2,
            $fuerza1,
            $bonus1,
            $resist2,
            $armor2,
            $danger1,
            $debug,
            $hp1Max,
            $hp2Max,
            $rubberBonusP1
        );

        $roll2 = sim_resolve_single_attack(
            $nombre2,
            $arma2,
            $skillz2a,
            $tipoCombate,
            $destre2,
            $suma2,
            $heridas2,
            $esquivar1,
            $heridas1,
            $fuerza2,
            $bonus2,
            $resist1,
            $armor1,
            $danger2,
            $debug,
            $hp2Max,
            $hp1Max,
            $rubberBonusP2
        );

        $dano2 = (int)$roll1['damage'];
        $dano1 = (int)$roll2['damage'];
        $heridas2 += $dano2;
        $heridas1 += $dano1;
        $hpact2 -= $dano2;
        $hpact1 -= $dano1;

        if ($dano2 > 0) { $tiradasExitoJ1++; } else { $tiradasFalliJ1++; }
        if ($dano1 > 0) { $tiradasExitoJ2++; } else { $tiradasFalliJ2++; }
        $rubberFailStreakP1 = ($dano2 > 0) ? 0 : ($rubberFailStreakP1 + 1);
        $rubberFailStreakP2 = ($dano1 > 0) ? 0 : ($rubberFailStreakP2 + 1);

        $rubberMarkupP1 = sim_rubber_dice_markup($rubberBonusP1);
        $rubberMarkupP2 = sim_rubber_dice_markup($rubberBonusP2);
        $line1 = $roll1['attackText'] . $roll1['resultText'] . $rubberMarkupP1 . $roll1['debug'];
        $line2 = $roll2['attackText'] . $roll2['resultText'] . $rubberMarkupP2 . $roll2['debug'];

        echo "<div class='sim-attack-msg from-left'>{$line1}</div>";
        echo "<div class='sim-attack-msg from-right'>{$line2}</div>";

        $combateArray["turnoAtacante$turnos"] = $roll1['attackText'] . "<br/>" . $roll2['attackText'] . "<br/><hr/>" . ($roll1['resultText'] . $rubberMarkupP1) . "<br/>" . ($roll2['resultText'] . $rubberMarkupP2);

        if ($hpact1 <= 0 && $hpact2 <= 0) {
            sim_emit_turn_health($turnos, $nombre1, $hpact1, $hp1Max, $nombre2, $hpact2, $hp2Max, $heridas1, $heridas2, $aplicarHeridas, $combateArray);
            $combateArray['resultadofinal'] = $doubleKO;
            echo "<p id='fraseFinalCombate'>$doubleKO</p>";
            $finalMessagePrinted = true;
            echo "</fieldset>";
            break;
        }
        if ($hpact1 <= 0) {
            sim_emit_turn_health($turnos, $nombre1, $hpact1, $hp1Max, $nombre2, $hpact2, $hp2Max, $heridas1, $heridas2, $aplicarHeridas, $combateArray);
            $combateArray['resultadofinal'] = $n1cae;
            echo "<p id='fraseFinalCombate'>$n1cae</p>";
            $finalMessagePrinted = true;
            echo "</fieldset>";
            break;
        }
        if ($hpact2 <= 0) {
            sim_emit_turn_health($turnos, $nombre1, $hpact1, $hp1Max, $nombre2, $hpact2, $hp2Max, $heridas1, $heridas2, $aplicarHeridas, $combateArray);
            $combateArray['resultadofinal'] = $n2cae;
            echo "<p id='fraseFinalCombate'>$n2cae</p>";
            $finalMessagePrinted = true;
            echo "</fieldset>";
            break;
        }
    } elseif ($turnOrder === 1) {
        $rubberBonusP1 = ($simRubberbandingEnabled === 'yes') ? sim_rubber_bonus_from_streak($rubberFailStreakP1) : 0;
        $roll1 = sim_resolve_single_attack(
            $nombre1,
            $arma1,
            $skillz1a,
            $tipoCombate,
            $destre1,
            $suma1,
            $heridas1,
            $esquivar2,
            $heridas2,
            $fuerza1,
            $bonus1,
            $resist2,
            $armor2,
            $danger1,
            $debug,
            $hp1Max,
            $hp2Max,
            $rubberBonusP1
        );

        $dano2 = (int)$roll1['damage'];
        $heridas2 += $dano2;
        $hpact2 -= $dano2;
        if ($dano2 > 0) { $tiradasExitoJ1++; } else { $tiradasFalliJ1++; }
        $rubberFailStreakP1 = ($dano2 > 0) ? 0 : ($rubberFailStreakP1 + 1);

        $rubberMarkupP1 = sim_rubber_dice_markup($rubberBonusP1);
        $line1 = $roll1['attackText'] . $roll1['resultText'] . $rubberMarkupP1 . $roll1['debug'];
        echo "<div class='sim-attack-msg from-left'>{$line1}</div>";
        $combateArray["turnoAtacante$turnos"] = $roll1['attackText'] . "<br/>" . ($roll1['resultText'] . $rubberMarkupP1);

        if ($hpact2 <= 0) {
            sim_emit_turn_health($turnos, $nombre1, $hpact1, $hp1Max, $nombre2, $hpact2, $hp2Max, $heridas1, $heridas2, $aplicarHeridas, $combateArray);
            $combateArray['resultadofinal'] = $n2cae;
            echo "<p id='fraseFinalCombate'>$n2cae</p>";
            $finalMessagePrinted = true;
            echo "</fieldset>";
            break;
        }

        $rubberBonusP2 = ($simRubberbandingEnabled === 'yes') ? sim_rubber_bonus_from_streak($rubberFailStreakP2) : 0;
        $roll2 = sim_resolve_single_attack(
            $nombre2,
            $arma2,
            $skillz2a,
            $tipoCombate,
            $destre2,
            $suma2,
            $heridas2,
            $esquivar1,
            $heridas1,
            $fuerza2,
            $bonus2,
            $resist1,
            $armor1,
            $danger2,
            $debug,
            $hp2Max,
            $hp1Max,
            $rubberBonusP2
        );

        $dano1 = (int)$roll2['damage'];
        $heridas1 += $dano1;
        $hpact1 -= $dano1;
        if ($dano1 > 0) { $tiradasExitoJ2++; } else { $tiradasFalliJ2++; }
        $rubberFailStreakP2 = ($dano1 > 0) ? 0 : ($rubberFailStreakP2 + 1);

        $rubberMarkupP2 = sim_rubber_dice_markup($rubberBonusP2);
        $line2 = $roll2['attackText'] . $roll2['resultText'] . $rubberMarkupP2 . $roll2['debug'];
        echo "<div class='sim-attack-msg from-right'>{$line2}</div>";
        $combateArray["turnoDefensor$turnos"] = $roll2['attackText'] . "<br/>" . ($roll2['resultText'] . $rubberMarkupP2);

        if ($hpact1 <= 0) {
            sim_emit_turn_health($turnos, $nombre1, $hpact1, $hp1Max, $nombre2, $hpact2, $hp2Max, $heridas1, $heridas2, $aplicarHeridas, $combateArray);
            $combateArray['resultadofinal'] = $n1cae;
            echo "<p id='fraseFinalCombate'>$n1cae</p>";
            $finalMessagePrinted = true;
            echo "</fieldset>";
            break;
        }
    } else {
        $rubberBonusP2 = ($simRubberbandingEnabled === 'yes') ? sim_rubber_bonus_from_streak($rubberFailStreakP2) : 0;
        $roll2 = sim_resolve_single_attack(
            $nombre2,
            $arma2,
            $skillz2a,
            $tipoCombate,
            $destre2,
            $suma2,
            $heridas2,
            $esquivar1,
            $heridas1,
            $fuerza2,
            $bonus2,
            $resist1,
            $armor1,
            $danger2,
            $debug,
            $hp2Max,
            $hp1Max,
            $rubberBonusP2
        );

        $dano1 = (int)$roll2['damage'];
        $heridas1 += $dano1;
        $hpact1 -= $dano1;
        if ($dano1 > 0) { $tiradasExitoJ2++; } else { $tiradasFalliJ2++; }
        $rubberFailStreakP2 = ($dano1 > 0) ? 0 : ($rubberFailStreakP2 + 1);

        $rubberMarkupP2 = sim_rubber_dice_markup($rubberBonusP2);
        $line2 = $roll2['attackText'] . $roll2['resultText'] . $rubberMarkupP2 . $roll2['debug'];
        echo "<div class='sim-attack-msg from-right'>{$line2}</div>";
        $combateArray["turnoAtacante$turnos"] = $roll2['attackText'] . "<br/>" . ($roll2['resultText'] . $rubberMarkupP2);

        if ($hpact1 <= 0) {
            sim_emit_turn_health($turnos, $nombre1, $hpact1, $hp1Max, $nombre2, $hpact2, $hp2Max, $heridas1, $heridas2, $aplicarHeridas, $combateArray);
            $combateArray['resultadofinal'] = $n1cae;
            echo "<p id='fraseFinalCombate'>$n1cae</p>";
            $finalMessagePrinted = true;
            echo "</fieldset>";
            break;
        }

        $rubberBonusP1 = ($simRubberbandingEnabled === 'yes') ? sim_rubber_bonus_from_streak($rubberFailStreakP1) : 0;
        $roll1 = sim_resolve_single_attack(
            $nombre1,
            $arma1,
            $skillz1a,
            $tipoCombate,
            $destre1,
            $suma1,
            $heridas1,
            $esquivar2,
            $heridas2,
            $fuerza1,
            $bonus1,
            $resist2,
            $armor2,
            $danger1,
            $debug,
            $hp1Max,
            $hp2Max,
            $rubberBonusP1
        );

        $dano2 = (int)$roll1['damage'];
        $heridas2 += $dano2;
        $hpact2 -= $dano2;
        if ($dano2 > 0) { $tiradasExitoJ1++; } else { $tiradasFalliJ1++; }
        $rubberFailStreakP1 = ($dano2 > 0) ? 0 : ($rubberFailStreakP1 + 1);

        $rubberMarkupP1 = sim_rubber_dice_markup($rubberBonusP1);
        $line1 = $roll1['attackText'] . $roll1['resultText'] . $rubberMarkupP1 . $roll1['debug'];
        echo "<div class='sim-attack-msg from-left'>{$line1}</div>";
        $combateArray["turnoDefensor$turnos"] = $roll1['attackText'] . "<br/>" . ($roll1['resultText'] . $rubberMarkupP1);

        if ($hpact2 <= 0) {
            sim_emit_turn_health($turnos, $nombre1, $hpact1, $hp1Max, $nombre2, $hpact2, $hp2Max, $heridas1, $heridas2, $aplicarHeridas, $combateArray);
            $combateArray['resultadofinal'] = $n2cae;
            echo "<p id='fraseFinalCombate'>$n2cae</p>";
            $finalMessagePrinted = true;
            echo "</fieldset>";
            break;
        }
    }

    if ($simAmbientMessagesEnabled === 'yes') {
        $ambientPool = array();
        if ($turnos >= 4 && !empty($simLateTurnMessages) && rand(1, 100) <= 45) {
            $ambientPool[] = array(
                'kind' => 'late',
                'text' => (string)$simLateTurnMessages[array_rand($simLateTurnMessages)]
            );
        }
        if (!empty($simCrowdMessages) && rand(1, 100) <= 35) {
            $ambientPool[] = array(
                'kind' => 'crowd',
                'text' => (string)$simCrowdMessages[array_rand($simCrowdMessages)]
            );
        }

        if (!empty($ambientPool)) {
            $pickedAmbient = $ambientPool[array_rand($ambientPool)];
            $ambientText = (string)$pickedAmbient['text'];
            echo "<div class='sim-attack-msg sim-ambient-msg'>$ambientText</div>";
            if (($pickedAmbient['kind'] ?? '') === 'late') {
                $combateArray["lateTurn$turnos"] = $ambientText;
            } else {
                $combateArray["crowdTurn$turnos"] = $ambientText;
            }
        }
    }

    sim_emit_turn_health($turnos, $nombre1, $hpact1, $hp1Max, $nombre2, $hpact2, $hp2Max, $heridas1, $heridas2, $aplicarHeridas, $combateArray);

    if ($turnos >= $maxturn) {
        $combateArray['resultadofinal'] = $mensajefin;
        echo "<p id='fraseFinalCombate'>$mensajefin</p>";
        $finalMessagePrinted = true;
        echo "</fieldset>";
        break;
    }

    echo "</fieldset>";
}

if (!$finalMessagePrinted) {
    if ($hpact1 <= 0 && $hpact2 <= 0) {
        $combateArray['resultadofinal'] = $doubleKO;
        echo "<p id='fraseFinalCombate'>$doubleKO</p>";
    } elseif ($hpact1 <= 0) {
        $combateArray['resultadofinal'] = $n1cae;
        echo "<p id='fraseFinalCombate'>$n1cae</p>";
    } elseif ($hpact2 <= 0) {
        $combateArray['resultadofinal'] = $n2cae;
        echo "<p id='fraseFinalCombate'>$n2cae</p>";
    } else {
        $combateArray['resultadofinal'] = $mensajefin;
        echo "<p id='fraseFinalCombate'>$mensajefin</p>";
    }
}

echo "</td></tr>";

?>
