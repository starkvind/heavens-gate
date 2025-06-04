<?php

include("../hehe.php");
include("../libreria.php");

?>

<html>

<head>

<title>Heaven's Gate</title>


<link href="../style.css" rel="stylesheet" type="text/css">

</head>

<body>

<? if ($valido=="si")
{
?>


<div class="cuerpo">

<h2> Agregar Dones Nuevos </h2>

<center>

<form action="agregar2.php" method="POST">

<table>

<tr>

<td class=datos2>Nombre:</td>
<td><input type="text" name="nombre" width="40" maxlength="40"></td>

</tr>

<tr><td class=datos2>Tipo:</td>
<td>
<select name='tipo'>
<option value=''>-Ninguno-</option>
		<?php

		$query1 = "SELECT id, name FROM nuevo2_tipo_dones ORDER BY id ASC";
		$result1 = mysql_query($query1);
		while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[0]}\"";
			if ($rows["id"] == $rows1[0]) echo " selected";
			echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);
		
/* <td>
<select name="rango">

<option value="1">Cliath</option>
<option value="2">Fostern</option>
<option value="3">Adren</option>
<option value="4">Athro</option>
<option value="5">Anciano</option>

</select>

</td> */

		?>
</select>

</td>

</tr>

<tr>

<td class=datos2>Grupo:</td>
<td><input type="text" name="grupo" width="40" maxlength="40"></td>

</tr>

<tr>

<td class=datos2>Rango:</td>
<td><input type="text" name="rango" width="2" maxlength="2"></td>

</tr>

<tr><td class=datos2>Atributo</td><td> 

<select name='atributo'>

<option value=''>-Ninguna-</option>

<option value="Fuerza">Fuerza</option>
<option value="Destreza">Destreza</option>
<option value="Resistencia">Resistencia</option>
<option value="Carisma">Carisma</option>
<option value="Manipulación">Manipulación</option>
<option value="Apariencia">Apariencia</option>
<option value="Percepción">Percepción</option>
<option value="Inteligencia">Inteligencia</option>
<option value="Astucia">Astucia</option>
<option value="Fuerza de Voluntad">Fuerza de Voluntad</option>
<option value="Gnosis">Gnosis</option>
<option value="Rabia">Rabia</option>

</select>

</td></tr>

<tr><td class=datos2>Habilidad</td><td> 

<select name='habilidad'>

<option value=''>-Ninguna-</option>

<option value='Alerta'>Alerta</option>
<option value='Atletismo'>Atletismo</option>
<option value='Callejeo'>Callejeo</option>
<option value='Empatía'>Empatía</option>
<option value='Esquivar'>Esquivar</option>
<option value='Expresión'>Expresión</option>
<option value='Impulso Primario'>Impulso Primario</option>
<option value='Intimidación'>Intimidación</option>
<option value='Pelea'>Pelea</option>
<option value='Subterfugio'>Subterfugio</option>

<option value='Armas C.C'>Armas C.C</option>
<option value='Armas de Fuego'>Armas de Fuego</option>
<option value='Conducir'>Conducir</option>
<option value='Etiqueta'>Etiqueta</option>
<option value='Interpretación'>Interpretación</option>
<option value='Liderazgo'>Liderazgo</option>
<option value='Reparaciones'>Reparaciones</option>
<option value='Sigilo'>Sigilo</option>
<option value='Supervivencia'>Supervivencia</option>
<option value='Trato con Animales'>Trato con Animales</option>

<option value='Ciencias'>Ciencias</option>
<option value='Enigma'>Enigmas</option>
<option value='Informática'>Informática</option>
<option value='Investigación'>Investigación</option>
<option value='Leyes'>Leyes</option>
<option value='Lingüística'>Lingüística</option>
<option value='Medicina'>Medicina</option>
<option value='Ocultismo'>Ocultismo</option>
<option value='Política'>Política</option>
<option value='Rituales'>Rituales</option>


</select>

</td></tr>

<tr><td class=datos2>Raza</td><td> 

<select name='ferasistema'>

<option value=''>-Ninguno-</option>
		<?php

		$query1 = "SELECT id, name FROM nuevo_sistema ORDER BY id ASC";
		$result1 = mysql_query($query1);
		while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
			if ($rows["id"] == $rows1[0]) echo " selected";
			echo ">" .$rows1[1]. "</option>\n";
			}
		mysql_free_result($result1);

		?>
</select>

</td></tr>

<tr><td class=datos2>Origen:</td><td> 

<select name='origen'>

<option value=''>-Ninguno-</option>
		<?php

		$query1 = "SELECT name, id FROM nuevo2_bibliografia ORDER BY orden";
		$result1 = mysql_query($query1);
		while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[1]}\"";
			if ($rows["id"] == $rows1[0]) echo " selected";
			echo ">" .$rows1[0]. "</option>\n";
			}
		mysql_free_result($result1);

		?>
</select>

</td></tr>

</table><br>

Descripci&oacute;n: <br>

<textarea name=descripcion rows=10 cols=60></textarea>

<br><br>

Sistema: <br>

<textarea name=sistema rows=10 cols=60></textarea>

<br><br>

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