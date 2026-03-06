<?php

$idDelCombate = isset($_GET['b']) ? (int)$_GET['b'] : 0;

if (!function_exists('sim_log_error_box')) {
    function sim_log_error_box($message)
    {
        echo "<div class='sim-ui'><center>" . $message . "<br/><br/>"
            . "<a class='boton1' href='/tools/combat-simulator/log'>Volver</a>"
            . "</center></div>";
    }
}

if (!function_exists('sim_log_combat_mode_title')) {
    function sim_log_combat_mode_title($combatType, $narrativeTone)
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

if (!function_exists('sim_table_exists')) {
    function sim_table_exists($link, $tableName)
    {
        $safe = mysql_real_escape_string((string)$tableName, $link);
        $rs = mysql_query("SHOW TABLES LIKE '$safe'", $link);
        return ($rs && mysql_num_rows($rs) > 0);
    }
}

if (!function_exists('sim_log_fetch_character_row')) {
    function sim_log_fetch_character_row($link, $characterId)
    {
        $characterId = (int)$characterId;
        if ($characterId <= 0) {
            return null;
        }

        $query = "SELECT * FROM vw_sim_characters WHERE id = $characterId LIMIT 1";
        $rs = mysql_query($query, $link);
        if (!$rs || mysql_num_rows($rs) === 0) {
            return null;
        }

        return mysql_fetch_array($rs);
    }
}

if (!function_exists('sim_log_fetch_character_system_id')) {
    function sim_log_fetch_character_system_id($link, $characterId)
    {
        $characterId = (int)$characterId;
        if ($characterId <= 0) {
            return 0;
        }

        $query = "SELECT system_id FROM fact_characters WHERE id = $characterId LIMIT 1";
        $rs = mysql_query($query, $link);
        if (!$rs || mysql_num_rows($rs) === 0) {
            return 0;
        }
        $row = mysql_fetch_array($rs);
        return (int)($row['system_id'] ?? 0);
    }
}

if (!function_exists('sim_log_rank_stars_html')) {
    function sim_log_rank_stars_html($row)
    {
        $score = (int)($row['fuerza'] ?? 0)
            + (int)($row['destreza'] ?? 0)
            + (int)($row['pelea'] ?? 0)
            + (int)($row['armascc'] ?? 0)
            + (int)($row['armasdefuego'] ?? 0)
            + (int)($row['esquivar'] ?? 0)
            + (int)($row['resistencia'] ?? 0);

        $stars = (int)ceil($score / 7);
        if ($stars < 1) {
            $stars = 1;
        }
        if ($stars > 5) {
            $stars = 5;
        }

        return str_repeat('&#9733;', $stars);
    }
}

if (!function_exists('sim_log_get_form_icon_map')) {
    function sim_log_get_form_icon_map($link)
    {
        $iconMap = array(1 => '&#128058;');
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

        return $iconMap;
    }
}

if (!function_exists('sim_log_form_icon_for_system')) {
    function sim_log_form_icon_for_system($systemId, $iconMap)
    {
        $systemId = (int)$systemId;
        if (isset($iconMap[$systemId])) {
            return (string)$iconMap[$systemId];
        }
        return '&#128062;';
    }
}

if (!function_exists('sim_log_lines_from_payload')) {
    function sim_log_is_continuation_line($line)
    {
        $plain = trim(strip_tags((string)$line));
        if ($plain === '') {
            return false;
        }

        $first = function_exists('mb_substr') ? mb_substr($plain, 0, 1, 'UTF-8') : substr($plain, 0, 1);
        if (in_array($first, array(',', '.', ';', ':', '!', '?', ')'), true)) {
            return true;
        }

        $lower = function_exists('mb_strtolower') ? mb_strtolower($plain, 'UTF-8') : strtolower($plain);
        $prefixes = array(
            'y ',
            'e ',
            'pero ',
            'aunque ',
            'sin embargo',
            'mientras ',
            'ademas ',
            'además ',
            'causa ',
            'provoca '
        );
        foreach ($prefixes as $prefix) {
            if (strpos($lower, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    function sim_log_is_rubber_dice_line($line)
    {
        $raw = (string)$line;
        return (strpos($raw, 'sim-rubber-dice') !== false || strpos($raw, '&#127922;') !== false);
    }

    function sim_log_lines_from_payload($html)
    {
        $html = (string)$html;
        if ($html === '') {
            return array();
        }

        $html = str_ireplace(array('<hr/>', '<hr />', '<hr>'), '<br/>', $html);
        $parts = preg_split('#<br\s*/?>#i', $html);
        if (!is_array($parts)) {
            return array();
        }

        $lines = array();
        foreach ($parts as $part) {
            $line = trim((string)$part);
            if ($line === '') {
                continue;
            }

            $lastIndex = count($lines) - 1;
            if ($lastIndex >= 0 && sim_log_is_rubber_dice_line($line)) {
                $lines[$lastIndex] .= "<br/>" . $line;
                continue;
            }

            if ($lastIndex >= 0 && sim_log_is_continuation_line($line)) {
                $lines[$lastIndex] .= " " . $line;
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }
}

if ($idDelCombate <= 0) {
    sim_log_error_box('Ha ocurrido un error.');
    return;
}

$consulta = "SELECT * FROM fact_sim_battles WHERE id = $idDelCombate LIMIT 1";
$IdConsulta = mysql_query($consulta, $link);
if (!$IdConsulta || mysql_num_rows($IdConsulta) === 0) {
    sim_log_error_box('No se ha encontrado el combate solicitado.');
    return;
}

$ResultQuery = mysql_fetch_array($IdConsulta);
$resultDataCombat = (string)($ResultQuery['turns_payload'] ?? '');
$arrayOfTurns = @unserialize($resultDataCombat);
if (!is_array($arrayOfTurns)) {
    sim_log_error_box('Los datos del combate est&aacute;n da&ntilde;ados o son incompatibles.');
    return;
}

$kid = (int)($ResultQuery['id'] ?? 0);
$kires = (string)($ResultQuery['winner_summary'] ?? '');

$idPj1 = (int)($arrayOfTurns['id1'] ?? 0);
$idPj2 = (int)($arrayOfTurns['id2'] ?? 0);

$rowP1 = sim_log_fetch_character_row($link, $idPj1);
$rowP2 = sim_log_fetch_character_row($link, $idPj2);

$nombrePj1 = (string)($rowP1['nombre'] ?? ($ResultQuery['fighter_one_alias_snapshot'] ?? 'Combatiente 1'));
$nombrePj2 = (string)($rowP2['nombre'] ?? ($ResultQuery['fighter_two_alias_snapshot'] ?? 'Combatiente 2'));
$aliasPj1 = (string)($rowP1['alias'] ?? ($ResultQuery['fighter_one_alias_snapshot'] ?? $nombrePj1));
$aliasPj2 = (string)($rowP2['alias'] ?? ($ResultQuery['fighter_two_alias_snapshot'] ?? $nombrePj2));
$imgPj1 = (string)($rowP1['img'] ?? '');
$imgPj2 = (string)($rowP2['img'] ?? '');
$systemId1 = sim_log_fetch_character_system_id($link, $idPj1);
$systemId2 = sim_log_fetch_character_system_id($link, $idPj2);

$rankStars1 = sim_log_rank_stars_html($rowP1 ?? array());
$rankStars2 = sim_log_rank_stars_html($rowP2 ?? array());

$tipoComb = (string)($arrayOfTurns['tipocombate'] ?? 'normal');
$combatModeLabel = (string)($arrayOfTurns['combat_mode_label'] ?? '');
if ($combatModeLabel === '') {
    $combatModeLabel = sim_log_combat_mode_title(
        $tipoComb,
        (string)($arrayOfTurns['narrative_tone'] ?? 'random')
    );
}

$arma1 = (string)($arrayOfTurns['arma1'] ?? '');
$arma2 = (string)($arrayOfTurns['arma2'] ?? '');
$protec1 = (string)($arrayOfTurns['prot1'] ?? '');
$protec2 = (string)($arrayOfTurns['prot2'] ?? '');
$forma1 = (string)($arrayOfTurns['forma1'] ?? 'Humano');
$forma2 = (string)($arrayOfTurns['forma2'] ?? 'Humano');

$formIconMap = sim_log_get_form_icon_map($link);
$formIconP1 = sim_log_form_icon_for_system($systemId1, $formIconMap);
$formIconP2 = sim_log_form_icon_for_system($systemId2, $formIconMap);

$loadoutP1 = "<div class='sim-loadout-summary'><span class='sim-loadout-pill'>&#9876; "
    . htmlspecialchars($arma1 !== '' ? $arma1 : 'Sin arma', ENT_QUOTES, 'UTF-8')
    . "</span><span class='sim-loadout-pill'>&#128737; "
    . htmlspecialchars($protec1 !== '' ? $protec1 : 'Ninguno', ENT_QUOTES, 'UTF-8')
    . "</span><span class='sim-loadout-pill'>$formIconP1 "
    . htmlspecialchars($forma1 !== '' ? $forma1 : 'Humano', ENT_QUOTES, 'UTF-8')
    . "</span></div>";

$loadoutP2 = "<div class='sim-loadout-summary'><span class='sim-loadout-pill'>&#9876; "
    . htmlspecialchars($arma2 !== '' ? $arma2 : 'Sin arma', ENT_QUOTES, 'UTF-8')
    . "</span><span class='sim-loadout-pill'>&#128737; "
    . htmlspecialchars($protec2 !== '' ? $protec2 : 'Ninguno', ENT_QUOTES, 'UTF-8')
    . "</span><span class='sim-loadout-pill'>$formIconP2 "
    . htmlspecialchars($forma2 !== '' ? $forma2 : 'Humano', ENT_QUOTES, 'UTF-8')
    . "</span></div>";

$pageSect = "Combate #$kid";
$pageTitle2 = "$aliasPj1 VS $aliasPj2";

include('app/partials/main_nav_bar.php');
?>
<div class="sim-ui">
    <h2>Resultados del Combate</h2>
    <table>
        <tr>
            <td class="ajustcelda" colspan="4" style="text-align:center;text-transform:uppercase;font-weight:bold;">
                <?php echo htmlspecialchars((string)$combatModeLabel, ENT_QUOTES, 'UTF-8'); ?>
            </td>
        </tr>
        <tr>
            <td class="ajustcelda" colspan="2"><?php echo "<center><a href='/characters/$idPj1' target='_blank'><img class='photobio sim-combat-avatar' src='$imgPj1' title='$nombrePj1'></a></center>"; ?></td>
            <td class="ajustcelda" colspan="2"><?php echo "<center><a href='/characters/$idPj2' target='_blank'><img class='photobio sim-combat-avatar' src='$imgPj2' title='$nombrePj2'></a></center>"; ?></td>
        </tr>
        <tr>
            <td class="ajustcelda" colspan="2"><?php echo "$nombrePj1 <hr style='border: 1px solid #009;'/> $aliasPj1<div class='sim-result-rank'>$rankStars1</div>"; ?></td>
            <td class="ajustcelda" colspan="2"><?php echo "$nombrePj2 <hr style='border: 1px solid #009;'/> $aliasPj2<div class='sim-result-rank'>$rankStars2</div>"; ?></td>
        </tr>
        <tr>
            <td class="ajustcelda" colspan="2"><?php echo $loadoutP1; ?></td>
            <td class="ajustcelda" colspan="2"><?php echo $loadoutP2; ?></td>
        </tr>

        <?php if (!empty($arrayOfTurns['fraseinicio'])) { ?>
            <tr><td colspan="4" class="ajustcelda"><?php echo $arrayOfTurns['fraseinicio']; ?></td></tr>
        <?php } ?>

        <?php if (!empty($arrayOfTurns['iniciativa'])) { ?>
            <tr><td colspan="4" class="ajustcelda"><?php echo $arrayOfTurns['iniciativa']; ?></td></tr>
        <?php } ?>

        <?php
        $numeroValoresArray = (int)($arrayOfTurns['turnos'] ?? 0);
        for ($iturnos = 1; $iturnos <= $numeroValoresArray; $iturnos++) {
            $temaAtacante = (string)($arrayOfTurns["turnoAtacante$iturnos"] ?? '');
            $temaDefensor = (string)($arrayOfTurns["turnoDefensor$iturnos"] ?? '');
            $regeAtacante = (string)($arrayOfTurns["regenAtacante$iturnos"] ?? '');
            $regeDefensor = (string)($arrayOfTurns["regenDefensor$iturnos"] ?? '');
            $ambientLate = (string)($arrayOfTurns["lateTurn$iturnos"] ?? '');
            $ambientCrowd = (string)($arrayOfTurns["crowdTurn$iturnos"] ?? '');
            $healthTurn = (string)($arrayOfTurns["healthTurn$iturnos"] ?? '');

            $attackerLines = sim_log_lines_from_payload($temaAtacante);
            $defenderLines = sim_log_lines_from_payload($temaDefensor);
            ?>
            <tr>
                <td colspan="4" class="ajustcelda">
                    <fieldset class="sim-turn-fieldset" id="sim-turn-<?php echo (int)$iturnos; ?>">
                        <legend>Turno <?php echo (int)$iturnos; ?></legend>

                        <?php if ($regeAtacante !== '') { ?>
                            <div class="sim-attack-msg from-left"><?php echo $regeAtacante; ?></div>
                        <?php } ?>

                        <?php if ($regeDefensor !== '') { ?>
                            <div class="sim-attack-msg from-right"><?php echo $regeDefensor; ?></div>
                        <?php } ?>

                        <?php
                        $lineIdx = 0;
                        foreach ($attackerLines as $line) {
                            $dir = ($lineIdx % 2 === 0) ? 'from-left' : 'from-right';
                            echo "<div class='sim-attack-msg $dir'>$line</div>";
                            $lineIdx++;
                        }

                        foreach ($defenderLines as $line) {
                            echo "<div class='sim-attack-msg from-right'>$line</div>";
                        }
                        ?>

                        <?php if ($ambientLate !== '') { ?>
                            <div class="sim-attack-msg sim-ambient-msg"><?php echo $ambientLate; ?></div>
                        <?php } ?>

                        <?php if ($ambientCrowd !== '') { ?>
                            <div class="sim-attack-msg sim-ambient-msg"><?php echo $ambientCrowd; ?></div>
                        <?php } ?>

                        <?php if ($healthTurn !== '') { ?>
                            <div class="sim-turn-health-wrap"><?php echo $healthTurn; ?></div>
                        <?php } ?>
                    </fieldset>
                </td>
            </tr>
            <?php
        }

        $ganador = (string)($arrayOfTurns['ganador'] ?? '');
        $heridas = (string)($arrayOfTurns['heridas'] ?? '');
        $resultadoFinal = (string)($arrayOfTurns['resultadofinal'] ?? '');
        $outcome = (string)($ResultQuery['outcome'] ?? '');

        $finalIcon = ($outcome === 'draw') ? '&#9878;' : '&#127942;';
        $winnerSummaryLabel = trim(strip_tags((string)($ResultQuery['winner_summary'] ?? 'Victoria')));
        if (stripos($winnerSummaryLabel, 'Ganador:') === 0) {
            $winnerSummaryLabel = trim(substr($winnerSummaryLabel, 8));
        }
        $finalTitle = ($outcome === 'draw') ? 'Empate' : htmlspecialchars(($winnerSummaryLabel !== '' ? $winnerSummaryLabel : 'Victoria'), ENT_QUOTES, 'UTF-8');
        $finalSubtitle = ($ganador !== '') ? $ganador : $kires;
        $finalVitals = htmlspecialchars(strip_tags($heridas), ENT_QUOTES, 'UTF-8');
        ?>

        <?php if ($resultadoFinal !== '') { ?>
            <tr><td colspan="4" class="ajustcelda"><?php echo $resultadoFinal; ?></td></tr>
        <?php } ?>

        <tr>
            <td colspan='4' class='ajustcelda'>
                <div class='sim-final-card'>
                    <div class='sim-final-icon'><?php echo $finalIcon; ?></div>
                    <div class='sim-final-title'><?php echo $finalTitle; ?></div>
                    <div class='sim-final-subtitle'><?php echo $finalSubtitle; ?></div>
                    <?php if ($finalVitals !== '') { ?>
                        <div class='sim-final-vitals'><?php echo $finalVitals; ?></div>
                    <?php } ?>
                </div>
            </td>
        </tr>
    </table>

    <div class="sim-rematch-row sim-final-actions">
        <form method="get" action="/tools/combat-simulator/log" class="sim-rematch-form">
            <button type="submit" class="boton1 sim-final-action-btn">Volver</button>
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
            setTimeout(showNextTurn, 450);
        }

        setTimeout(showNextTurn, 150);
    })();
    </script>
</div>
