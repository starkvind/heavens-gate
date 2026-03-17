<?php
include_once("sim_character_scope.php");
include_once("sim_battles_table.php");

if (!function_exists('sim_stats_h')) {
    function sim_stats_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$statsBattleWhere = sim_active_season_where_sql($link, 'fact_sim_battles');
$statsScoreWhere = sim_active_season_where_sql($link, 'fact_sim_character_scores');
$statsItemWhere = sim_active_season_where_sql($link, 'fact_sim_item_usage');
?>

<fieldset>
<legend>&Uacute;ltimos combates</legend>

<?php
$consulta = "SELECT * FROM fact_sim_battles{$statsBattleWhere} ORDER BY id DESC LIMIT 5";
$IdConsulta = mysql_query($consulta, $link);
$battleRows = array();
if ($IdConsulta) {
    while ($row = mysql_fetch_array($IdConsulta)) {
        $battleRows[] = $row;
    }
}
sim_btl_render_table($link, $battleRows, array(
    'empty_text' => "A&uacute;n no se ha celebrado ning&uacute;n combate."
));
?>

</fieldset>

<?php
$combatesTotales = 0;
$consulta = "SELECT COUNT(*) AS total FROM fact_sim_battles{$statsBattleWhere}";
$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
$combatesTotales = (int)($ResultQuery['total'] ?? 0);

if ($combatesTotales > 0) {
    echo "<div class='sim-actions-row'><a class='sim-classic-btn' href='/tools/combat-simulator/log'>Mostrar todos</a></div>";
}
?>

<br/>

<fieldset>
<legend>Estad&iacute;sticas</legend>

<?php
$consulta = "SELECT COUNT(*) AS total FROM fact_sim_character_scores{$statsScoreWhere}";
$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
$NFilas = (int)($ResultQuery['total'] ?? 0);

if ($NFilas > 0) {
    $statsRows = array();

    $statsRows[] = array(
        'label' => 'Combates disputados:',
        'value' => '<b>' . (int)$combatesTotales . '</b>'
    );

    $consulta = "SELECT character_name_snapshot, wins FROM fact_sim_character_scores{$statsScoreWhere} ORDER BY wins DESC, updated_at DESC LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['wins'] ?? 0);
    $statsRows[] = array('label' => 'Mayor n&uacute;mero de combates ganados:', 'value' => "<b>$numervict</b> victorias, por <b>$nombrevict</b>");

    $consulta = "SELECT character_name_snapshot, draws FROM fact_sim_character_scores{$statsScoreWhere} ORDER BY draws DESC, updated_at DESC LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['draws'] ?? 0);
    $statsRows[] = array('label' => 'Mayor n&uacute;mero de empates obtenidos:', 'value' => "<b>$numervict</b> empates, por <b>$nombrevict</b>");

    $consulta = "SELECT character_name_snapshot, losses FROM fact_sim_character_scores{$statsScoreWhere} ORDER BY losses DESC, updated_at DESC LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['losses'] ?? 0);
    $statsRows[] = array('label' => 'Mayor n&uacute;mero de derrotas:', 'value' => "<b>$numervict</b> derrotas, por <b>$nombrevict</b>");

    $consulta = "SELECT character_name_snapshot, damage_dealt FROM fact_sim_character_scores{$statsScoreWhere} ORDER BY damage_dealt DESC, updated_at DESC LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['damage_dealt'] ?? 0);
    $statsRows[] = array('label' => 'Mayor cantidad de da&ntilde;o provocado:', 'value' => "<b>$nombrevict</b> (<b>$numervict</b> puntos de da&ntilde;o)");

    $consulta = "SELECT character_name_snapshot, damage_dealt FROM fact_sim_character_scores{$statsScoreWhere} ORDER BY damage_dealt ASC, updated_at DESC LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['damage_dealt'] ?? 0);
    $statsRows[] = array('label' => 'Menor cantidad de da&ntilde;o provocado:', 'value' => "<b>$nombrevict</b> (<b>$numervict</b> puntos de da&ntilde;o)");

    $consulta = "SELECT character_name_snapshot, damage_taken FROM fact_sim_character_scores{$statsScoreWhere} ORDER BY damage_taken DESC, updated_at DESC LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['damage_taken'] ?? 0);
    $statsRows[] = array('label' => 'Mayor cantidad de vida perdida:', 'value' => "<b>$nombrevict</b> (<b>$numervict</b> puntos de vida)");

    $consulta = "SELECT character_name_snapshot, damage_taken FROM fact_sim_character_scores{$statsScoreWhere} ORDER BY damage_taken ASC, updated_at DESC LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
    $nombrevict = sim_stats_h($ResultQuery['character_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['damage_taken'] ?? 0);
    $statsRows[] = array('label' => 'Menor cantidad de vida perdida:', 'value' => "<b>$nombrevict</b> (<b>$numervict</b> puntos de vida)");

    $consulta = "SELECT item_name_snapshot, times_used FROM fact_sim_item_usage{$statsItemWhere} ORDER BY times_used DESC, updated_at DESC LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
    $nombrevict = sim_stats_h($ResultQuery['item_name_snapshot'] ?? '');
    $numervict = (int)($ResultQuery['times_used'] ?? 0);
    if ($nombrevict !== '') {
        $statsRows[] = array('label' => 'Arma m&aacute;s utilizada:', 'value' => "<b>$nombrevict</b>, utilizada <b>$numervict</b> veces");
    }

    $consulta = "SELECT item_name_snapshot, times_used FROM fact_sim_item_usage{$statsItemWhere} ORDER BY times_used ASC, updated_at DESC LIMIT 1";
    $IdConsulta = mysql_query($consulta, $link);
    $ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
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
$consulta = "SELECT COUNT(*) AS total FROM fact_sim_character_scores{$statsScoreWhere}";
$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
$NFilas = (int)($ResultQuery['total'] ?? 0);

if ($NFilas > 0) {
    echo "<a class='sim-classic-btn' href='/tools/combat-simulator/scores'>Puntuaciones</a>";
}

$consulta = "SELECT COUNT(*) AS total FROM fact_sim_item_usage{$statsItemWhere}";
$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
$NFilas = (int)($ResultQuery['total'] ?? 0);

if ($NFilas > 0) {
    echo " <a class='sim-classic-btn' href='/tools/combat-simulator/weapons'>Armas utilizadas</a>";
}
echo " <a class='sim-classic-btn' href='/tools/combat-simulator/tournament'>Torneo</a>";
?>
</div>
