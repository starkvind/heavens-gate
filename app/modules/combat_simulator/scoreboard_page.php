<?php
include_once("sim_character_scope.php");
include_once("sim_battles_table.php");

if (!function_exists('sim_score_h')) {
    function sim_score_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$scoreSeasonWhere = sim_active_season_where_sql($link, 'fact_sim_character_scores');
$battleSeasonWhere = sim_active_season_where_sql($link, 'fact_sim_battles');

$consulta = "SELECT MAX(wins) AS max_wins FROM fact_sim_character_scores{$scoreSeasonWhere} LIMIT 1";
$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
$maxvictor = (int)($ResultQuery['max_wins'] ?? 0);

$consulta = "SELECT MAX(draws) AS max_draws FROM fact_sim_character_scores{$scoreSeasonWhere} LIMIT 1";
$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
$maxempat = (int)($ResultQuery['max_draws'] ?? 0);

$consulta = "SELECT MAX(losses) AS max_losses FROM fact_sim_character_scores{$scoreSeasonWhere} LIMIT 1";
$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = ($IdConsulta) ? mysql_fetch_array($IdConsulta) : array();
$maxderrot = (int)($ResultQuery['max_losses'] ?? 0);

include("app/partials/main_nav_bar.php");
?>

<div class="sim-ui">
<h2>Puntuaciones</h2>
<br/>
<center>

<table class="sim-combats-table sim-score-table">
<tr>
    <td class="celdacombat sim-score-rank">#</td>
    <td class="celdacombat sim-score-character">Personaje</td>
    <td class="celdacombat sim-score-num">Victorias</td>
    <td class="celdacombat sim-score-num">Empates</td>
    <td class="celdacombat sim-score-num">Derrotas</td>
    <td class="celdacombat sim-score-num">Combates</td>
    <td class="celdacombat sim-score-num">Puntos</td>
    <td class="celdacombat sim-score-num">Eficacia</td>
</tr>

<?php
$consulta = "SELECT fact_sim_character_scores.*, vw_sim_characters.id AS sim_character_id, vw_sim_characters.nombre, vw_sim_characters.alias, COALESCE(vw_sim_characters.img, '') AS img
            FROM fact_sim_character_scores
            INNER JOIN vw_sim_characters ON fact_sim_character_scores.character_id = vw_sim_characters.id{$scoreSeasonWhere}
            ORDER BY points DESC";
$IdConsulta = mysql_query($consulta, $link);
$NFilas = $IdConsulta ? mysql_num_rows($IdConsulta) : 0;

for ($i = 0; $i < $NFilas; $i++) {
    $ResultQuery = mysql_fetch_array($IdConsulta);

    $nombre = (string)($ResultQuery["nombre"] ?? '');
    $alias = (string)($ResultQuery["alias"] ?? '');
    if ($alias === '') {
        $alias = $nombre;
    }

    $victorias = (int)($ResultQuery["wins"] ?? 0);
    $empates = (int)($ResultQuery["draws"] ?? 0);
    $derrotas = (int)($ResultQuery["losses"] ?? 0);
    $kombo = (int)($ResultQuery["battles"] ?? 0);
    $puntos = (int)($ResultQuery["points"] ?? 0);
    $characterId = (int)($ResultQuery["character_id"] ?? 0);
    $posicionEnTabla = $i + 1;

    $orden = 0;
    if ($kombo > 0) {
        $orden = ($puntos * 100) / ($kombo * 3);
    }
    $orden = round($orden, 1);

    $siesmaxvictorias = ($victorias === $maxvictor && $maxvictor > 0) ? "background:#CC0000;font-weight:bolder;border:1px solid #FFFF00;" : "";
    $siesmaxempates = ($empates === $maxempat && $maxempat > 0) ? "background:#007700;font-weight:bolder;border:1px solid #00FF00;" : "";
    $siesmaxderrotas = ($derrotas === $maxderrot && $maxderrot > 0) ? "background:#333399;font-weight:bolder;border:1px solid #00FFFF;" : "";

    $slug = sim_btl_character_slug_by_id($link, $characterId);
    $bioHref = ($slug !== '') ? ('/characters/' . rawurlencode($slug)) : ('/characters/' . $characterId);
    $avatarUrl = function_exists('hg_character_avatar_url')
        ? hg_character_avatar_url((string)($ResultQuery['img'] ?? ''), '')
        : (string)($ResultQuery['img'] ?? '');

    $safeAlias = sim_score_h($alias);
    $safeAvatar = sim_score_h($avatarUrl);
    $safeHref = sim_score_h($bioHref);

    echo "
    <tr>
        <td class='ajustcelda sim-score-rank'>$posicionEnTabla</td>
        <td class='ajustcelda sim-score-character-cell'>
            <span class='sim-score-char'>
                <a class='sim-winner-avatar-link hg-tooltip' data-tip='character' data-id='$characterId' href='$safeHref' target='_blank'>
                    <img class='sim-winner-avatar16' src='$safeAvatar' alt=''>
                </a>
                <span class='sim-score-name-wrap'>
                    <a class='hg-tooltip sim-score-name-link' data-tip='character' data-id='$characterId' href='$safeHref' target='_blank'>$safeAlias</a>
                </span>
            </span>
        </td>
        <td class='ajustcelda sim-score-num' style='$siesmaxvictorias'>$victorias</td>
        <td class='ajustcelda sim-score-num' style='$siesmaxempates'>$empates</td>
        <td class='ajustcelda sim-score-num' style='$siesmaxderrotas'>$derrotas</td>
        <td class='ajustcelda sim-score-num'>$kombo</td>
        <td class='ajustcelda sim-score-num'>$puntos</td>
        <td class='ajustcelda sim-score-num'>{$orden}%</td>
    </tr>";
}
?>

<tr>
    <td colspan="8" style="text-align:right;">
        <h4>
            <?php
            $pageSect = ":: Puntuaciones";
            $sql = "SELECT COUNT(*) AS total FROM fact_sim_battles{$battleSeasonWhere}";
            $result = mysql_query($sql, $link);
            $rowTotalCombates = ($result) ? mysql_fetch_array($result) : array();
            $numeroCombates = (int)($rowTotalCombates['total'] ?? 0);
            echo "<b>Combates totales:</b> " . (int)$numeroCombates;
            ?>
        </h4>
    </td>
</tr>

</table>

<div class="sim-actions-row">
    <a class="sim-classic-btn" href="/tools/combat-simulator">Volver</a>
</div>

</center>
</div>
