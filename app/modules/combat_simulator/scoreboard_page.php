<?php

/* include("heroes.php"); */

/* SELECCIONAMOS LO MAXIMO */

$consulta ="SELECT MAX(wins) dicks FROM fact_sim_character_scores LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);
$maxvictor = $ResultQuery['dicks'];

$consulta ="SELECT MAX(draws) dicks FROM fact_sim_character_scores LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);
$maxempat = $ResultQuery['dicks'];

$consulta ="SELECT MAX(losses) dicks FROM fact_sim_character_scores LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);
$maxderrot = $ResultQuery['dicks'];

/* YA TENEMOS LO MAXIMO */

include("app/partials/main_nav_bar.php");	// Barra Navegación

?>

<div class="sim-ui">
<h2> Puntuaciones </h2>
<br/>
<center>

<table>

<tr>

<td> </td>

<td class="celdacombat" width="25%"> Nombre </td>

<td class="celdacombat"> Victorias </td>

<td class="celdacombat"> Empates </td>

<td class="celdacombat"> Derrotas </td>

<td class="celdacombat"> Combates </td>

<td class="celdacombat"> Puntos </td>

<td class="celdacombat"> Eficacia </td>

</tr>

<?php

/* ESTO ES UNA CASTAÑA, LA PROXIMA VEZ USA ID'S, HIJOPUTA */

$consulta ="SELECT * FROM fact_sim_character_scores INNER JOIN vw_sim_characters ON fact_sim_character_scores.character_id = vw_sim_characters.id ORDER BY points DESC";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

$siesmaxvictorias = "";
$siesmaxempates = "";
$siesmaxderrotas = "";
$nombre = $ResultQuery["nombre"];
$alias = $ResultQuery["alias"];

$victorias = $ResultQuery["wins"];
$empates = $ResultQuery["draws"];
$derrotas = $ResultQuery["losses"];

$kombo = $ResultQuery["battles"];

$puntos = $ResultQuery["points"];
$danoc = $ResultQuery["damage_dealt"];
$danor = $ResultQuery["damage_taken"];

$ships = $ResultQuery["character_id"];

$posicionEnTabla = $i + 1;

#$orden2 = $victorias/($victorias+$empates+$derrotas)*100;

$orden = 0;
if ($kombo > 0) {
	$orden = ($puntos * 100) / ($kombo * 3);
}

$orden = round($orden,1);

if ($victorias == $maxvictor) { $siesmaxvictorias = "background:#CC0000;font-weight:bolder;border:1px solid #FFFF00;"; }
if ($empates == $maxempat) { $siesmaxempates = "background:#007700;font-weight:bolder;border:1px solid #00FF00;"; }
if ($derrotas == $maxderrot) { $siesmaxderrotas = "background:#333399;font-weight:bolder;border:1px solid #00FFFF;"; }

print("

<tr>

<td class='ajustcelda'><center>$posicionEnTabla</center></td>
<td class='ajustcelda'>
<a href='/characters/$ships' title='|| $nombre || Da&ntilde;o causado: $danoc || Vida perdida: $danor ||' target='_blank'>
$alias
</a>
</td>

<td class='ajustcelda' style='$siesmaxvictorias'>$victorias</td>
<td class='ajustcelda' style='$siesmaxempates'>$empates</td>
<td class='ajustcelda' style='$siesmaxderrotas'>$derrotas</td>
<td class='ajustcelda'>$kombo</td>
<td class='ajustcelda'>$puntos</td>
<td class='ajustcelda'>$orden%</td>

</tr>

");

}

?>

<tr>

<td colspan="8" style="text-align:right;"> <h4> 

<?php

$pageSect = ":: Puntuaciones"; // PARA CAMBIAR EL TITULO A LA PAGINA

$sql = "SELECT * FROM fact_sim_battles";//"SELECT SUM(combates) AS suma FROM fact_sim_character_scores";
$result = mysql_query ($sql, $link);
$numeroCombates = mysql_num_rows($result);
//$row = mysql_fetch_array($result);

echo "<b>Combates totales:</b> $numeroCombates";
//$titi = $row['suma'];
//$tite = $titi/2;
//echo $tite;

?> </h4> </td> </tr>

</table>

<div class="sim-actions-row">
<a class="sim-classic-btn" href="/tools/combat-simulator">Volver</a>
</div>

</center>
</div>
