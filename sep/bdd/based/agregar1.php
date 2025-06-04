<?php

include("../hehe.php");


?>

<html>

<head>

<title>Heaven's Gate</title>

<script languaje="Javascript">
<!--
document.write('<style type="text/css">div.ocultable{display: none;}</style>');
function MostrarOcultar(capa,enlace)
{
    if (document.getElementById)
    {
        var aux = document.getElementById(capa).style;
        aux.display = aux.display? "":"block";
    }
}

/* CODIGO OBTENIDO EN:
 http://foro.noticias3d.com/vbulletin/showthread.php?t=171184
*/


//-->
</script>

<link href="../style.css" rel="stylesheet" type="text/css">

</head>

<body>

<? if ($valido=="si")
{
?>

<div class="cuerpo">

<center>

<h2> Agregar Personajes Nuevos </h2>

<form action="agregar2.php" method="POST" enctype="multipart/form-data">

<table>

	<tr>

<td class=datos2>Nombre:</td>
<td><input type="text" name="nombre" width="20" maxlength="30"></td>

<td class=datos2>Raza:</td>
	<td>
		<select name="raza">
		<option value='' selected>- Ninguna -</option>
		<?php

		$query1 = "SELECT id, name, sistema FROM nuevo_razas ORDER BY name";
		$result1 = mysql_query($query1);
		while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[0]}\"";
			if ($rows["id"] == $rows1[0]) echo " selected";
			echo ">" .$rows1[1]. " (" .$rows1[2]. ")</option>\n";
			}
		mysql_free_result($result1);

		?>
		</select>
	</td>

<td class=datos2>Manada:</td>
	<td>
		<select name="manada">
		<option value='' selected>- Ninguna -</option>
		<?php

		$query1 = "SELECT id, name FROM nuevo2_manadas ORDER BY name";
		$result1 = mysql_query($query1);
		while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[0]}\"";
			if ($rows["id"] == $rows1[0]) echo " selected";
			echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
		//input type="text" name="manada" width="20" maxlength="30">
		?>
		</select>
	</td>
	
	</tr>

	<tr>

<td class=datos2>Jugador:</td>

<td>

<select name="jugador">

<option value='PNJ' selected>- Ninguno -</option>

<?php
	$query1 = "SELECT id,name FROM nuevo_jugadores ORDER BY name";
	$result1 = mysql_query($query1);
		while ($rows1 = mysql_fetch_array($result1)) {
			echo "<option value=\"{$rows1[0]}\"";
		if ($rows["id"] == $rows1[0]) echo " selected";
			echo ">" .$rows1[1]. "</option>\n";
		}
	mysql_free_result($result1);
?>

</select>

</td>

<td class=datos2>Auspicio:</td><td>

<select name="auspicio">

<option value="" selected>- Ninguno -</option>

<?php
	$query1 = "SELECT id, name FROM nuevo_auspicios ORDER BY name";
	$result1 = mysql_query($query1);
	while ($rows1 = mysql_fetch_array($result1)){
		echo "<option value=\"{$rows1[0]}\"";
		if ($rows["id"] == $rows1[0]) echo " selected";
		echo ">" .$rows1[1]. "</option>\n";
	}
	mysql_free_result($result1);
?>

</select></td>

<td class=datos2>T&oacute;tem:</td>

<td>

<select name="totem">

<option value='' selected>- Ninguno -</option>

<?php
	$query1 = "SELECT id, name FROM nuevo_totems ORDER BY name";
	$result1 = mysql_query($query1);
	while ($rows1 = mysql_fetch_array($result1)){
		echo "<option value=\"{$rows1[1]}\"";
		if ($rows["id"] == $rows1[0]) echo " selected";
		echo ">" .$rows1[1]. "</option>\n";
	}
	mysql_free_result($result1);
?>

</select>

</td>

	</tr>

	<tr>
<td class=datos2>Cr&oacute;nica:</td>
<td>

<select name="cronica">

<option value='Sin asignar' selected>- Ninguno -</option>

<?php
	$query1 = "SELECT id, name FROM nuevo2_cronicas ORDER BY id DESC";
	$result1 = mysql_query($query1);
	while ($rows1 = mysql_fetch_array($result1)){
		echo "<option value=\"{$rows1[0]}\"";
		if ($rows["id"] == $rows1[0]) echo " selected";
		echo ">" .$rows1[1]. "</option>\n";
	}
	mysql_free_result($result1);
	
	//<input type="text" name="cronica" width="20" maxlength="20">
?>

</select>

</td>

<td class=datos2>Tribu:</td>
<td>

<select name="tribu">

<option value='' selected>- Ninguna -</option>

<?php
	$query1 = "SELECT id, name FROM nuevo_tribus ORDER BY name";
	$result1 = mysql_query($query1);
	while ($rows1 = mysql_fetch_array($result1)){
		echo "<option value=\"{$rows1[0]}\"";
		if ($rows["id"] == $rows1[0]) echo " selected";
		echo ">" .$rows1[1]. "</option>\n";
	}
	mysql_free_result($result1);
?>

</select>

</td>
<td class=datos2>Concepto:</td><td><input type="text" name="concepto" width="20" maxlength="20"></td>

	</tr>
	
	<tr>
	
<td class=datos2>Naturaleza:</td>
<td>

<select name="naturaleza">

<option value='' selected>- Ninguna -</option>

<?php
	$query1 = "SELECT id, name FROM nuevo_personalidad ORDER BY name";
	$result1 = mysql_query($query1);
	while ($rows1 = mysql_fetch_array($result1)){
		echo "<option value=\"{$rows1[0]}\"";
		if ($rows["id"] == $rows1[0]) echo " selected";
		echo ">" .$rows1[1]. "</option>\n";
	}
	mysql_free_result($result1);
?>

</select>

</td>

<td class=datos2>Conducta:</td>

<td>

<select name="conducta">

<option value='' selected>- Ninguna -</option>

<?php
	$query1 = "SELECT id, name FROM nuevo_personalidad ORDER BY name";
	$result1 = mysql_query($query1);
	while ($rows1 = mysql_fetch_array($result1)){
		echo "<option value=\"{$rows1[0]}\"";
		if ($rows["id"] == $rows1[0]) echo " selected";
		echo ">" .$rows1[1]. "</option>\n";
	}
	mysql_free_result($result1);
?>

</select>

</td>

<td class=datos2>Alias:</td>
<td><input type="text" name="alias" width="20" maxlength="15"></td>

	</tr>
	
	<tr>
	
<td class=datos2>Nombre Garou:</td>
<td><input type="text" name="nombregarou" width="20" maxlength="50"></td>

<td class=datos2>Tema Musical:</td>
<td><input type="text" name="temamusical" width="20" maxlength="200"></td>

<td class=datos2>URL Tema Musical:</td>
<td><input type="text" name="temaurl" width="20" maxlength="200"></td>
	
	</tr>

	<tr>
<td class=datos2><a href="javascript:MostrarOcultar('texto1');" id="enlace1">F&iacute;sicos</a>:</td>

<td>

<div class="ocultable" id="texto1">

<table>

<tr>

<td>Fuerza:</td>

<td>
	<select name="fuerza">
	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>
	</select>

</td>

</tr>

<tr>

<td>Destreza:</td>

<td>

	<select name="destreza">
	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>
	</select>

</td>

</tr>

<tr>

<td>Resistencia:</td>

<td>
	<select name="resistencia">
	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>
	</select>

</td></tr></table>

</div>

</td>
<td class=datos2><a href="javascript:MostrarOcultar('texto2');" id="enlace1">Sociales</a>:</td>

<td>

<div class="ocultable" id="texto2">

<table>

<tr>

<td>Carisma:</td>

<td>
	<select name="carisma">
	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>
	</select>

</td>

</tr>

<tr>

<td>Manipulaci&oacute;n:</td>

<td>

	<select name="manipulacion">
	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>
	</select>

</td>

</tr>

<tr>

<td>Apariencia:</td>

<td>
	<select name="apariencia">
	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>
	</select>

</td></tr></table>

</div>

</td>
<td class=datos2><a href="javascript:MostrarOcultar('texto3');" id="enlace1">Mentales</a>:</td>

<td>

<div class="ocultable" id="texto3">

<table>

<tr>

<td>Percepci&oacute;n:</td>

<td>
	<select name="percepcion">
	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>
	</select>

</td>

</tr>

<tr>

<td>Inteligencia:</td>

<td>

	<select name="inteligencia">
	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>
	</select>

</td>

</tr>

<tr>

<td>Astucia:</td>

<td>
	<select name="astucia">
	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>
	</select>

</td></tr></table>

</div>


</td></tr>

<tr><td class=datos2><a href="javascript:MostrarOcultar('texto4');" id="enlace1">Talentos</a>:</td>

<td>

<div class="ocultable" id="texto4">

	<table>

	<tr>

	<td >    Alerta: </td>

	<td >  

	<select name="alerta">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Atletismo: </td>

	<td >  

	<select name="atletismo">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Callejeo: </td>

	<td >  

	<select name="callejeo">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Empat&iacute;a: </td>

	<td >  

	<select name="empatia">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Esquivar: </td>

	<td >  

	<select name="esquivar">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Expresi&oacute;n: </td>

	<td >  

	<select name="expresion">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Impulso Primario: </td>

	<td >  

	<select name="impulsoprimario">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Intimidaci&oacute;n: </td>

	<td >  

	<select name="intimidacion">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Pelea: </td>

	<td >  

	<select name="pelea">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Subterfugio: </td>

	<td >  

	<select name="subterfugio">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>
	
	<tr>

	<td >
	<select name="talento1extra">
	<option value="" selected>- Ninguno -</option>
	<?php
		//<input name="trasfondo1" type="text" size="15" maxlength="15"> 
		// <input type="text" name="tecnica2extra" width="20" maxlength="20"> 
		$query1 = "SELECT id, name FROM nuevo_habilidades WHERE tipo LIKE 'Talentos' AND clasificacion LIKE '002 Secundarias' ORDER BY name ASC";
		$result1 = mysql_query($query1);
			while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
				if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
	?>
	</select>
	</td>

	<td >  

	<select name="talento1valor">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>
	
	<tr>

	<td >    
	<select name="talento2extra">
	<option value="" selected>- Ninguno -</option>
	<?php
		//<input name="trasfondo1" type="text" size="15" maxlength="15"> 
		// <input type="text" name="tecnica2extra" width="20" maxlength="20"> 
		$query1 = "SELECT id, name FROM nuevo_habilidades WHERE tipo LIKE 'Talentos' AND clasificacion LIKE '002 Secundarias' ORDER BY name ASC";
		$result1 = mysql_query($query1);
			while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
				if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
	?>
	</select>
	
	</td>

	<td >  

	<select name="talento2valor">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	</table>

</div>

</td>
<td class=datos2><a href="javascript:MostrarOcultar('texto5');" id="enlace1">T&eacute;cnicas</a>:</td>

<td>

<div class="ocultable" id="texto5">

	<table>

	<tr>

	<td >    Armas C.C.: </td>

	<td >  

	<select name="armascc">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Armas de Fuego: </td>

	<td >  

	<select name="armasdefuego">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Conducir: </td>

	<td >  

	<select name="conducir">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Etiqueta: </td>

	<td >  

	<select name="etiqueta">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Interpretaci&oacute;n: </td>

	<td >  

	<select name="interpretacion">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Liderazgo: </td>

	<td >  

	<select name="liderazgo">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Reparaciones: </td>

	<td >  

	<select name="reparaciones">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Sigilo: </td>

	<td >  

	<select name="sigilo">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Supervivencia: </td>

	<td >  

	<select name="supervivencia">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Trato con Animales: </td>

	<td >  

	<select name="tratoanimales">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>
	
	<tr>

	<td >
	
	<select name="tecnica1extra">
	<option value="" selected>- Ninguno -</option>
	<?php
		//<input name="trasfondo1" type="text" size="15" maxlength="15"> 
		// <input type="text" name="tecnica2extra" width="20" maxlength="20"> 
		$query1 = "SELECT id, name FROM nuevo_habilidades WHERE tipo LIKE 'Técnicas' AND clasificacion LIKE '002 Secundarias' ORDER BY name ASC";
		$result1 = mysql_query($query1);
			while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
				if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
	?>
	</select>
	
	</td>

	<td >  

	<select name="tecnica1valor">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>
	
	<tr>

	<td >    
	
	<select name="tecnica2extra">
	<option value="" selected>- Ninguno -</option>
	<?php
		//<input name="trasfondo1" type="text" size="15" maxlength="15"> 
		// <input type="text" name="tecnica2extra" width="20" maxlength="20"> 
		$query1 = "SELECT id, name FROM nuevo_habilidades WHERE tipo LIKE 'Técnicas' AND clasificacion LIKE '002 Secundarias' ORDER BY name ASC";
		$result1 = mysql_query($query1);
			while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
				if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
	?>
	</select>
	
	</td>

	<td >  

	<select name="tecnica2valor">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	</table>

</div>

</td><td class=datos2><a href="javascript:MostrarOcultar('texto6');" id="enlace1">Conocimientos</a>:</td>

<td>

<div class="ocultable" id="texto6">

	<table>

	<tr>

	<td >    Ciencias: </td>

	<td >  

	<select name="ciencias">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Enigmas: </td>

	<td >  

	<select name="enigmas">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Inform&aacute;tica: </td>

	<td >  

	<select name="informatica">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Investigaci&oacute;n: </td>

	<td >  

	<select name="investigacion">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Leyes: </td>

	<td >  

	<select name="leyes">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Ling&uuml;&iacute;stica: </td>

	<td >  

	<select name="linguistica">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Medicina: </td>

	<td >  

	<select name="medicina">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>
	
	<tr>

	<td >    Ocultismo: </td>

	<td >  

	<select name="ocultismo">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Pol&iacute;tica: </td>

	<td >  

	<select name="politica">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >    Rituales: </td>

	<td >  

	<select name="rituales">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>
	
	<tr>

	<td > 
	<select name="conoci1extra">
	<option value="" selected>- Ninguno -</option>
	<?php
		//<input name="trasfondo1" type="text" size="15" maxlength="15"> 
		// <input type="text" name="conoci1extra" width="20" maxlength="20"> 
		$query1 = "SELECT id, name FROM nuevo_habilidades WHERE tipo LIKE 'Conocimientos' AND clasificacion LIKE '002 Secundarias' ORDER BY name ASC";
		$result1 = mysql_query($query1);
			while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
				if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
	?>
	</select>
	</td>

	<td >  

	<select name="conoci1valor">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>
	
	<tr>

	<td>
	<select name="conoci2extra">
	<option value="" selected>- Ninguno -</option>
	<?php
		//<input name="trasfondo1" type="text" size="15" maxlength="15"> 
		// <input type="text" name="conoci1extra" width="20" maxlength="20"> 
		$query1 = "SELECT id, name FROM nuevo_habilidades WHERE tipo LIKE 'Conocimientos' AND clasificacion LIKE '002 Secundarias' ORDER BY name ASC";
		$result1 = mysql_query($query1);
			while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
				if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
	?>
	</select>
	</td>

	<td >  

	<select name="conoci2valor">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	</table>

</div>

</td></tr>

<tr><td class=datos2><a href="javascript:MostrarOcultar('texto7');" id="enlace1">Trasfondos</a>:</td>

<td>

<div class="ocultable" id="texto7">

	<table>

	<tr>

	<td > 
	<select name="trasfondo1">
	<option value="" selected>- Ninguno -</option>
	<?php
		//<input name="trasfondo1" type="text" size="15" maxlength="15"> 
		$query1 = "SELECT id, name FROM nuevo_habilidades WHERE tipo LIKE 'Trasfondos' ORDER BY name ASC";
		$result1 = mysql_query($query1);
			while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
				if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
	?>
	</select>
	
	</td>

	<td >  

	<select name="trasfondo1valor">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >
	<select name="trasfondo2">
	<option value="" selected>- Ninguno -</option>
	<?php
		//<input name="trasfondo1" type="text" size="15" maxlength="15"> 
		$query1 = "SELECT id, name FROM nuevo_habilidades WHERE tipo LIKE 'Trasfondos' ORDER BY name ASC";
		$result1 = mysql_query($query1);
			while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
				if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
	?>
	</select>
	</td>

	<td >  

	<select name="trasfondo2valor">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >
	
	<select name="trasfondo3">
	<option value="" selected>- Ninguno -</option>
	<?php
		//<input name="trasfondo1" type="text" size="15" maxlength="15"> 
		$query1 = "SELECT id, name FROM nuevo_habilidades WHERE tipo LIKE 'Trasfondos' ORDER BY name ASC";
		$result1 = mysql_query($query1);
			while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
				if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
	?>
	</select>
	
	</td>

	<td >  

	<select name="trasfondo3valor">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >
	
	<select name="trasfondo4">
	<option value="" selected>- Ninguno -</option>
	<?php
		//<input name="trasfondo1" type="text" size="15" maxlength="15"> 
		$query1 = "SELECT id, name FROM nuevo_habilidades WHERE tipo LIKE 'Trasfondos' ORDER BY name ASC";
		$result1 = mysql_query($query1);
			while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
				if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
	?>
	</select>
	
	</td>

	<td >  

	<select name="trasfondo4valor">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	<tr>

	<td >
	
	<select name="trasfondo5">
	<option value="" selected>- Ninguno -</option>
	<?php
		//<input name="trasfondo1" type="text" size="15" maxlength="15"> 
		$query1 = "SELECT id, name FROM nuevo_habilidades WHERE tipo LIKE 'Trasfondos' ORDER BY name ASC";
		$result1 = mysql_query($query1);
			while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
				if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
	?>
	</select>
	
	</td>

	<td >  

	<select name="trasfondo5valor">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>

	</select>

	</td>

	</tr>

	</table>


</div>

</td><td class=datos2><a href="javascript:MostrarOcultar('texto8');" id="enlace1">Dones</a>:</td>

<td>

<div class="ocultable" id="texto8">

	<table> 
		<tr> 
		<td>
	<?php
		$maximoDones = 11; // Poner 1 más de lo que se quiera poner
		for($donesC=1;$donesC<$maximoDones;$donesC++) {
			echo "<select name='don$donesC'>";
			echo "<option value='' selected>- Vacío -</option>";
			$query1 = "SELECT id, nombre FROM dones ORDER BY nombre ASC";
			$result1 = mysql_query($query1);
				while ($rows1 = mysql_fetch_array($result1)){
				echo "<option value=\"{$rows1[0]}\"";
					if ($rows["id"] == $rows1[0]) echo " selected";
					echo ">" .$rows1[1]. "</option>\n";
				}
			mysql_free_result($result1);
			echo "</select>";
		echo "<br/><br/>";
		}
	?>
		</td>
		</tr>
	</table>
</div>

</td>
<td class=datos2><a href="javascript:MostrarOcultar('texto9');" id="enlace1">Renombre</a>:</td>

<td>

<div class="ocultable" id="texto9">

	<table>

	<tr>

	<td > <a title="Permanente">Gloria</a>: </td>

	<td>

	<select name="gloriap">


	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>
	<option value="6"> 6 </option>
	<option value="7"> 7 </option>
	<option value="8"> 8 </option>
	<option value="9"> 9 </option>
	<option value="10"> 10 </option>

	</select>
	</td>

	</tr>

	<tr>

	<td > <a title="Permanente">Honor</a>: </td>

	<td>

	<select name="honorp">


	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>
	<option value="6"> 6 </option>
	<option value="7"> 7 </option>
	<option value="8"> 8 </option>
	<option value="9"> 9 </option>
	<option value="10"> 10 </option>

	</select>
</td>

	</tr>

	<tr>

	<td > <a title="Permanente"> Sabidur&iacute;a</a>: </td>

	<td>

	<select name="sabiduriap">

	<option value="0"> 0 </option>
	<option value="1"> 1 </option>
	<option value="2"> 2 </option>
	<option value="3"> 3 </option>
	<option value="4"> 4 </option>
	<option value="5"> 5 </option>
	<option value="6"> 6 </option>
	<option value="7"> 7 </option>
	<option value="8"> 8 </option>
	<option value="9"> 9 </option>
	<option value="10"> 10 </option>

	</select>
	</td>

	</tr>

	</table>

</div>

</td></tr>

<tr>
<td class=datos2><a href="javascript:MostrarOcultar('texto10');" id="enlace1">Poder</a>:</td>

<td>

<div class="ocultable" id="texto10">

	<table>

	<tr>

	<td > <a title="Irrelevante si es Vampiro">Rabia</a>: </td>
	<td >

	<select name="rabiap">
		<option value="0" selected> 0 </option>
		<option value="1"> 1 </option>
		<option value="2"> 2 </option>
		<option value="3"> 3 </option>
		<option value="4"> 4 </option>
		<option value="5"> 5 </option>
		<option value="6"> 6 </option>
		<option value="7"> 7 </option>
		<option value="8"> 8 </option>
		<option value="9"> 9 </option>
		<option value="10"> 10 </option>
	</select>
	</td>
	</tr>
	<tr>

	<td > <a title="Humanidad / Senda si es Vampiro">Gnosis</a>: </td>


	<td >
	<select name="gnosisp">
		<option value="0" selected> 0 </option>
		<option value="1"> 1 </option>
		<option value="2"> 2 </option>
		<option value="3"> 3 </option>
		<option value="4"> 4 </option>
		<option value="5"> 5 </option>
		<option value="6"> 6 </option>
		<option value="7"> 7 </option>
		<option value="8"> 8 </option>
		<option value="9"> 9 </option>
		<option value="10"> 10 </option>
	</select>
	</td>
	</tr>

	<tr>

	<td > <a title="Permanente">Voluntad</a>: </td>

	<td >

	<select name="fvp">
		<option value="1"> 1 </option>
		<option value="2"> 2 </option>
		<option value="3"> 3 </option>
		<option value="4"> 4 </option>
		<option value="5"> 5 </option>
		<option value="6"> 6 </option>
		<option value="7"> 7 </option>
		<option value="8"> 8 </option>
		<option value="9"> 9 </option>
		<option value="10"> 10 </option>
	</select>
	</td>
	</tr>
	</table>
</div>

</td>
<td class=datos2>Rango:</td><td><input type="text" name="rango" width="20" maxlength="20"></td>

<td class=datos2>Clan:</td>
	<td>
		<select name="clan">
		<option value='' selected>- Ninguno -</option>
		<?php

		$query1 = "SELECT id, name FROM nuevo2_clanes ORDER BY sistema ASC";
		$result1 = mysql_query($query1);
		while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[0]}\"";
			if ($rows["id"] == $rows1[0]) echo " selected";
			echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
		?>
		</select>
	</td>

</tr>

<tr><td class=datos2>Afiliaci&oacute;n:</td>
<td>
	<select name="tipo">
		<?php
		$query1 = "SELECT id, tipo FROM afiliacion ORDER BY id ASC";
		$result1 = mysql_query($query1);
		while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[0]}\"";
			if ($rows["id"] == $rows1[0]) echo " selected";
			echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
		?>
	</select>
</td>

<td class=datos2>Cumplea&ntilde;os:</td><td><input type="text" name="cumple" width="20" maxlength="20"></td>

<td class=datos2>Estado:</td><td><select name="estado">
<option value="En activo">Activo/a</option>
<option value="Paradero desconocido">Desaparecido/a</option>
<option value="Cad&aacute;ver">Muerto/a</option>
<option value="A&uacute;n por aparecer">A&uacute;n por aparecer</option>
</select>
</td>

</tr>
<tr>
<td class=datos2>Texto:</td><td><textarea name="text1"></textarea></td>
<td class=datos2>Notas:</td><td><textarea name="notas"></textarea></td>
<td class=datos2>Sistema:<br/>Raza:</td><td>

			<select name="sistema">
			<option value='' selected>- Ninguno -</option>
			<?php
			$query1 = "SELECT id, name FROM nuevo_sistema ORDER BY orden ASC";
			$result1 = mysql_query($query1);
				while ($rows1 = mysql_fetch_array($result1)){
				echo "<option value=\"{$rows1[1]}\"";
					if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
				}
			mysql_free_result($result1);
			?>
			</select>
			<br/>
			<select name="fera">
			<option value='' selected>- Ninguno -</option>
			<?php
			$query1 = "SELECT DISTINCT raza FROM nuevo_formas ORDER BY id ASC";
			$result1 = mysql_query($query1);
				while ($rows1 = mysql_fetch_array($result1)){
				echo "<option value=\"{$rows1[0]}\"";
					if ($rows["raza"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[0]. "</option>\n";
				}
			mysql_free_result($result1);
			?>
			</select>			

</td>

</tr>

<tr>
	<td class=datos2>Imagen:</td><td><input type="file" name="file"></td>
	<td class=datos2>¿PJ?:</td><td><select name="kes">
<option value="pj">Personaje Jugador</option>
<option value="pnj" selected>Personaje NO Jugador</option>
</select></td>

<td class=datos2>Experiencia:</td>
<td><input type="text" name="experiencia" width="10" maxlength="4"></td>
</tr>

<tr>
<td class=datos2 colspan="1">
	<a href="javascript:MostrarOcultar('texto11');" id="enlace1">M&eacute;ritos / Defectos:</a></td>
<td colspan="5">
<div class="ocultable" id="texto11">
			<br/>
	<?php
		$maximoMerif = 9; // Poner 1 más de lo que se quiera poner
		for($meriC=1;$meriC<$maximoMerif;$meriC++) {
			echo "<select name='merodef$meriC'>";
			echo "<option value='' selected>- Vacío -</option>";
			$query1 = "SELECT id, name, tipo, coste, afiliacion FROM nuevo_mer_y_def ORDER BY afiliacion ASC";
			$result1 = mysql_query($query1);
				while ($rows1 = mysql_fetch_array($result1)){
				echo "<option value=\"{$rows1[0]}\"";
					if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. " - " .$rows1[2]. " (" .$rows1[4]. " de " .$rows1[3]. " puntos)</option>\n";
				}
			mysql_free_result($result1);
			echo "</select>";
		echo "<br/><br/>";
		}
	?>
</div>
</td>
</tr>

<tr>
<td class=datos2 colspan="1">
	<a href="javascript:MostrarOcultar('texto13');" id="enlace1">Rituales:</a></td>
<td colspan="5">
<div class="ocultable" id="texto13">
			<br/>
	<?php
		$maximoRit = 11; // Poner 1 más de lo que se quiera poner
		for($ritC=1;$ritC<$maximoRit;$ritC++) {
			echo "<select name='ritual$ritC'>";
			echo "<option value='' selected>- Vacío -</option>";
			$query1 = "SELECT id, name, nivel FROM nuevo_rituales ORDER BY tipo";
			$result1 = mysql_query($query1);
				while ($rows1 = mysql_fetch_array($result1)){
				echo "<option value=\"{$rows1[0]}\"";
					if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. " - Nivel " .$rows1[2]. "</option>\n";
				}
			mysql_free_result($result1);
			echo "</select>";
		echo "<br/><br/>";
		}
	?>
</div>
</td>
</tr>

<tr>
<td class=datos2 colspan="1">
	<a href="javascript:MostrarOcultar('texto12');" id="enlace1">Inventario:</a></td>
<td colspan="5">
<div class="ocultable" id="texto12">
			<br/>
	<?php
		$maximoInv = 7; // Poner 1 más de lo que se quiera poner
		for($invC=1;$invC<$maximoInv;$invC++) {
			echo "<select name='objeto$invC'>";
			echo "<option value='' selected>- Vacío -</option>";
			$query1 = "SELECT id, name, nivel FROM nuevo3_objetos ORDER BY tipo";
			$result1 = mysql_query($query1);
				while ($rows1 = mysql_fetch_array($result1)){
				echo "<option value=\"{$rows1[0]}\"";
					if ($rows["id"] == $rows1[0]) echo " selected";
				echo ">" .$rows1[1]. "</option>\n";
				}
			mysql_free_result($result1);
			echo "</select>";
		echo "<br/><br/>";
		}
	?>
</div>
</td>
</tr>

</table>

<br>

<input class="boton1" type="submit" value="A&ntilde;adir">


<input class="boton1" type="button" onClick="javascript: history.go(-1)" value="Regresar">

</form>

</center>

</div>

<? }
else { 
?>

<center>ERROR: ACCESO DENEGADO</center>

<? } ?>

</body>

</html>