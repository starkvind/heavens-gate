<?php
if (!function_exists('sim_stats_h')) {
    function sim_stats_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sim_stats_winner_html')) {
    function sim_stats_winner_html($value)
    {
        $decoded = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
        $clean = strip_tags($decoded, '<b><strong>');
        $clean = preg_replace('/<\s*strong[^>]*>/i', '<b>', $clean);
        $clean = preg_replace('/<\s*\/\s*strong\s*>/i', '</b>', $clean);
        $clean = preg_replace('/<\s*b[^>]*>/i', '<b>', $clean);
        $clean = preg_replace('/<\s*\/\s*b\s*>/i', '</b>', $clean);
        return trim((string)$clean);
    }
}
?>

<fieldset>
<legend>&Uacute;ltimos combates</legend>

<?php
$consulta = "SELECT * FROM fact_sim_battles ORDER BY id DESC LIMIT 5";
$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas == "") {
    echo "A&uacute;n no se ha celebrado ning&uacute;n combate.";
} else {
    echo "<table class='sim-combats-table'>";

    for ($i = 0; $i < $NFilas; $i++) {
        $ResultQuery = mysql_fetch_array($IdConsulta);

        $kid = (int)$ResultQuery["id"];
        $ki1 = sim_stats_h($ResultQuery["fighter_one_alias_snapshot"] ?? "");
        $ki2 = sim_stats_h($ResultQuery["fighter_two_alias_snapshot"] ?? "");
        $kires = sim_stats_winner_html($ResultQuery["winner_summary"] ?? "");

        echo "
        <tr>
            <td class='sim-col-id'>#<a href='/tools/combat-simulator/log/$kid'>$kid</a></td>
            <td class='sim-col-p1'>$ki1</td>
            <td class='sim-col-vs'>VS</td>
            <td class='sim-col-p2'>$ki2</td>
            <td class='sim-col-result'>$kires</td>
        </tr>";
    }

    echo "</table>";
}
?>

</fieldset>

<?php
$combatesTotales = 0;
$consulta = "SELECT * FROM fact_sim_battles";
$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != "") {
    $combatesTotales = $NFilas;
    echo "<div class='sim-actions-row'><a class='sim-classic-btn' href='/tools/combat-simulator/log'>Mostrar todos</a></div>";
}
?>

<br/>

<fieldset>
<legend>Estad&iacute;sticas</legend>

<?php
$consulta = "SELECT * FROM fact_sim_character_scores";
$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != "") {
    $statsRows = array();

    $statsRows[] = array(
        'label' => 'Combates disputados:',
        'value' => '<b>' . (int)$combatesTotales . '</b>'
    );

    $consulta = "SELECT character_name_snapshot,wins FROM fact_sim_character_scores WHERE wins LIKE (SELECT MAX(wins) FROM fact_sim_character_scores LIMIT 1) LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = mysql_fetch_array($IdConsulta);
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['wins'] ?? 0);
    $statsRows[] = array('label' => 'Mayor n&uacute;mero de combates ganados:', 'value' => "<b>$numervict</b> victorias, por <b>$nombrevict</b>");

    $consulta = "SELECT character_name_snapshot,draws FROM fact_sim_character_scores WHERE draws LIKE (SELECT MAX(draws) FROM fact_sim_character_scores LIMIT 1) LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = mysql_fetch_array($IdConsulta);
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['draws'] ?? 0);
    $statsRows[] = array('label' => 'Mayor n&uacute;mero de empates obtenidos:', 'value' => "<b>$numervict</b> empates, por <b>$nombrevict</b>");

    $consulta = "SELECT character_name_snapshot,losses FROM fact_sim_character_scores WHERE losses LIKE (SELECT MAX(losses) FROM fact_sim_character_scores LIMIT 1) LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = mysql_fetch_array($IdConsulta);
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['losses'] ?? 0);
    $statsRows[] = array('label' => 'Mayor n&uacute;mero de derrotas:', 'value' => "<b>$numervict</b> derrotas, por <b>$nombrevict</b>");

    $consulta = "SELECT character_name_snapshot,damage_dealt FROM fact_sim_character_scores WHERE damage_dealt LIKE (SELECT MAX(damage_dealt) FROM fact_sim_character_scores LIMIT 1) LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = mysql_fetch_array($IdConsulta);
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['damage_dealt'] ?? 0);
    $statsRows[] = array('label' => 'Mayor cantidad de da&ntilde;o provocado:', 'value' => "<b>$nombrevict</b> (<b>$numervict</b> puntos de da&ntilde;o)");

    $consulta = "SELECT character_name_snapshot,damage_dealt FROM fact_sim_character_scores WHERE damage_dealt LIKE (SELECT MIN(damage_dealt) FROM fact_sim_character_scores LIMIT 1) LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = mysql_fetch_array($IdConsulta);
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['damage_dealt'] ?? 0);
    $statsRows[] = array('label' => 'Menor cantidad de da&ntilde;o provocado:', 'value' => "<b>$nombrevict</b> (<b>$numervict</b> puntos de da&ntilde;o)");

    $consulta = "SELECT character_name_snapshot,damage_taken FROM fact_sim_character_scores WHERE damage_taken LIKE (SELECT MAX(damage_taken) FROM fact_sim_character_scores LIMIT 1) LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = mysql_fetch_array($IdConsulta);
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['damage_taken'] ?? 0);
    $statsRows[] = array('label' => 'Mayor cantidad de vida perdida:', 'value' => "<b>$nombrevict</b> (<b>$numervict</b> puntos de vida)");

    $consulta = "SELECT character_name_snapshot,damage_taken FROM fact_sim_character_scores WHERE damage_taken LIKE (SELECT MIN(damage_taken) FROM fact_sim_character_scores LIMIT 1) LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = mysql_fetch_array($IdConsulta);
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['damage_taken'] ?? 0);
    $statsRows[] = array('label' => 'Menor cantidad de vida perdida:', 'value' => "<b>$nombrevict</b> (<b>$numervict</b> puntos de vida)");

    $consulta = "SELECT item_name_snapshot,times_used FROM fact_sim_item_usage WHERE times_used LIKE (SELECT MAX(times_used) FROM fact_sim_item_usage LIMIT 1) LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = mysql_fetch_array($IdConsulta);
    $nombrevict = sim_stats_h($ResultQuery['item_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['times_used'] ?? 0);
    if ($nombrevict !== '') {
        $statsRows[] = array('label' => 'Arma m&aacute;s utilizada:', 'value' => "<b>$nombrevict</b>, utilizada <b>$numervict</b> veces");
    }

    $consulta = "SELECT item_name_snapshot,times_used FROM fact_sim_item_usage WHERE times_used LIKE (SELECT MIN(times_used) FROM fact_sim_item_usage LIMIT 1) LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = mysql_fetch_array($IdConsulta);
    $nombrevict = sim_stats_h($ResultQuery['item_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['times_used'] ?? 0);
    if ($nombrevict !== '') {
        $statsRows[] = array('label' => 'Arma menos utilizada:', 'value' => "<b>$nombrevict</b>, utilizada <b>$numervict</b> veces");
    }

    echo "<table class='sim-stats-table'>";
    foreach ($statsRows as $row) {
        echo "<tr><td class='sim-stat-label'>{$row['label']}</td><td class='sim-stat-value'>{$row['value']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "A&uacute;n no se ha celebrado ning&uacute;n combate.";
}
?>

</fieldset>

<div class="sim-actions-row">
<?php
$consulta = "SELECT * FROM fact_sim_character_scores";
$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != "") {
    echo "<a class='sim-classic-btn' href='/tools/combat-simulator/scores'>Puntuaciones</a>";
}

$consulta = "SELECT * FROM fact_sim_item_usage";
$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != "") {
    echo " <a class='sim-classic-btn' href='/tools/combat-simulator/weapons'>Armas utilizadas</a>";
}
?>
</div>
