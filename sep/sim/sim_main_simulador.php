<?php
	include ("sim_script_selec_forma.php"); /* Script java para las formas de los combatientes */
	include("sep/main/main_nav_bar.php");	// Barra Navegación
	$pageSect = "Simulador de Combate"; // PARA CAMBIAR EL TITULO A LA PAGINA
?>
<h2> Simulador de Combate </h2>
<form action="index.php?p=simulador2" name="simulador" method="post">
<table>
<tr>

<td class="ajustceld"> Personaje: </td>

<td class="ajustceld"> 

<?php

echo "<select name=\"pj1\" onchange=\"selectAsociado1()\">\n";

echo "<option value='' selected>- Elegir personaje -</option>
";

$query1 = "SELECT id, alias FROM pjs1 WHERE kes LIKE 'pj' ORDER BY alias ASC";
$result1 = mysqli_query($link, $query1);
while ($rows1 = mysqli_fetch_assoc($result1)){
echo "<option value=\"{$rows1[0]}\"";
if ($rows["id"] == $rows1[0]) echo " selected";
echo "\">" .$rows1[1]. "</option>\n";
}
mysqli_free_result($result1);

?>

</select>

</td>

<td class="ajustceld"> Rival: </td>

<td class="ajustceld"> 

<?php

echo "<select name=\"pj2\" onchange=\"selectAsociado2()\">\n";

echo "<option value='' selected>- Elegir personaje -</option>
";

$query1 = "SELECT id, alias FROM pjs1 WHERE kes LIKE 'pj' ORDER BY alias DESC";
$result1 = mysqli_query($link, $query1);
while ($rows1 = mysqli_fetch_assoc($result1)){
echo "<option value=\"{$rows1[0]}\"";
if ($rows["id"] == $rows1[0]) echo " selected";
echo "\">" .$rows1[1]. "</option>\n";
}
mysqli_free_result($result1);

?>

</select>

</td>

</tr>

<tr>

<td class="ajustceld"> Arma: </td>

<td class="ajustceld"> 

<select name="arma1">

<option value=""> -Ninguna- </option>

<?php

include ("sim_script_selec_armas.php"); /* Script para incluir las armas de la BDD */

?>

</select>

</td>

<td class="ajustceld"> Arma: </td>

<td class="ajustceld"> 

<select name="arma2">

<option value=""> -Ninguna- </option>

<?php

include ("sim_script_selec_armas.php"); /* Script para incluir las armas de la BDD */

?>

</select>

</td>

</tr>

<tr>

<td class="ajustceld"> Protector: </td>

<td class="ajustceld"> 

<select name="protec1">

<option value=""> -Ninguno- </option>


<?php

include ("sim_script_selec_protec.php"); /* Script para incluir los protectores de la BDD */

?>

</select>

</td>

<td class="ajustceld"> Protector: </td>

<td class="ajustceld"> 

<select name="protec2">

<option value=""> -Ninguno- </option>

<?php

include ("sim_script_selec_protec.php"); /* Script para incluir los protectores de la BDD */

?>

</select>

</td>

</tr>

<tr>

<td class="ajustceld"> Forma: </td>

<td class="ajustceld"> 

<select name="forma1">

<option value="Homínido">Homínido</option>

</select>

</td>

<td class="ajustceld"> Forma: </td>

<td class="ajustceld"> 

<select name="forma2">

<option value="Homínido">Homínido</option>

</select>

</td>

</tr>

<tr> 

<td colspan="4">

<center>

<br/>
<fieldset style="border: 1px solid #0000CC;">
<legend style="border: 1px solid #0000CC; padding: 3px; background-color:#000066">
<a title="Ajusta las opciones del Simulador a tu gusto.">Opciones</a>
</legend>

<a class="infoLink" title="Elige el l&iacute;mite de turnos que durar&aacute; el enfrentamiento.">Turnos</a>

<select name="turnos">

<option value="1">1</option>
<option value="5" selected="selected">5</option>
<option value="10">10</option>
<option value="15">15</option>
<option value="20">20</option>
<option value="25">25</option>
<option value="30">30</option>
<option value="35">35</option>
<option value="40">40</option>
<option value="45">45</option>
<option value="50">50</option>

</select>

&nbsp;

<a class="infoLink" title="Selecciona los puntos de vida m&aacute;ximos de los dos personajes.">Salud</a>

<select name="vit">

<option value="1">1</option>
<option value="7" selected="selected">7</option>
<option value="14">14</option>
<option value="21">21</option>
<option value="28">28</option>
<option value="35">35</option>
<option value="42">42</option>
<option value="49">49</option>
<option value="56">56</option>

</select>

<select name="usarheridas">

<option value="no">Ignorar heridas</option>
<option value="yes" selected="selected">Aplicar heridas</option>

</select>

&nbsp;

<a class="infoLink" title="Al comienzo de cada turno los personajes recuperar&aacute;n Vitalidad, dependiendo de su forma.">Curaci&oacute;n</a>

<select name="regeneracion">

<option value="no">Ninguna</option>
<option value="ambos">Ambos</option>
<option value="pj1">Personaje</option>
<option value="pj2">Rival</option>

</select>

&nbsp;

Combate

<select name="combate">
	<option value="normal" selected>Normal</option>
	<option value="umbral">Umbral</option>
</select>


<?php
/* &nbsp;

<a class="infoLink" title="Los combatientes recibir&aacute;n penalizaciones por haber sufrido da&ntilde;o." class="infoLink">Heridas</a>

Ventaja:

<select name="ventaja">

<option value="no">Ninguna</option>
<option value="pj1">Personaje</option>
<option value="pj2">Rival</option>

</select>

<br/>
&nbsp;

Tiradas:

<select name="debug">

<option value="no" selected="selected">Ocultar</option>
<option value="si">Mostrar</option>

</select>

*/ ?>

</fieldset>
<br/>

</center>

</td>
</tr>

<tr> 
<td colspan="4">

<center>

<fieldset style="border: 1px solid #0000CC;">
<legend style="border: 1px solid #0000CC; padding: 3px; background-color:#000066">
<a title="Escoge las opciones del Simulador al azar entre las posibilidades disponibles.">Aleatorizar</a>
</legend>
		<input type="checkbox" name="aleatorio" value="yes">
		<a class="infoLink" title="Marcar para poner combatientes aleatorios.">Personajes</a>
		<input type="checkbox" name="armasrandom" value="yes">
		<a class="infoLink" title="Marcar para elegir las armas al azar.">Armamento</a>
		<input type="checkbox" name="protrandom" value="yes">
		<a class="infoLink" title="Marcar para seleccionar protectores al azar.">Protección</a>
		<input type="checkbox" name="formarandom" value="yes">
		<a class="infoLink" title="Marcar para elegir la forma que adoptarán los personajes al azar.">Formas</a>
		<input type="checkbox" name="turnrandom" value="yes">
		<a class="infoLink" title="Marcar para elegir un número aleatorio de turnos.">Turnos</a>
		<input type="checkbox" name="vitrandom" value="yes">
		<a class="infoLink" title="Marcar para seleccionar los puntos de vida al azar.">Vitalidad</a>
</fieldset>
<br/>

</td>
</tr>

<tr>
<td colspan="4">
<center>

<?php /*
Tiradas:

<select name="debug">

<option value="no">Ocultar</option>
<option value="si">Mostrar</option>

</select>
<br/><br/>
*/ ?>
<input class="boton1" type="submit" value="Empezar"/>
</center>
</td>

</tr>

<tr>

<td colspan="4">

<?php 
	include("sim_body_help.php"); echo "<br/>"; include("sim_body_stats.php"); 
	/* Incluimos dos archivos de datos con ayuda y los stats más importantes */ 
?>


</td>

</tr>

</table>

</form>