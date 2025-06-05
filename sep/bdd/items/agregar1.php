<?php

include("../hehe.php");

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

<h2> Agregar Objetos Nuevos </h2>

<center>

<form action="agregar2.php" method="POST">

<table>

<tr>

<td class=datos2>Nombre:</td>
<td><input type="text" name="nombre" width="20" maxlength="40"></td>

</tr>

<tr><td class=datos2>Clasificaci&oacute;n:</td><td>

<?php

include("../libreria.php");

mysql_select_db("$bdd", $link);
$consulta ="SELECT tipo FROM inventario ORDER BY id";
$query = mysql_query ($consulta, $link);

echo "<select name=clasifi>";

while ($reg = mysql_fetch_row($query)) {

foreach($reg as $cambia) {
echo "<option value='$cambia'>",$cambia,"</option>";
}
}

echo "</select>";

?></td></tr>

<tr>

<td class=datos2>Habilidad:</td>
<td>
<select name="habilidad">

<option value="Cuerpo a Cuerpo">Armas C.C.</option>
<option value="Armas a Distancia">Atletismo</option>
<option value="Armas de Fuego">Armas de Fuego</option>

</select>

</td>
</tr>

<tr>

<td class=datos2>Valor:</td>
<td>
<select name="valor">

<option value="Com&uacute;n">Com&uacute;n</option>
<option value="Poco frecuente">Poco frecuente</option><
option value="Raro">Raro</option>
<option value="&Uacute;nico">&Uacute;nico</option>

</select>

</td>
</tr>

<tr><td class=datos2>Bonificaci&oacute;n</td><td> 

<select name="bonus">

<option value=0>0</option>
<option value=1>+1</option>
<option value=2>+2</option>
<option value=3>+3</option>
<option value=4>+4</option>
<option value=5>+5</option>
<option value=6>+6</option>
<option value=7>+7</option>
<option value=8>+8</option>
<option value=9>+9</option>
<option value=10>+1</option>
<option value=11>+11</option>
<option value=12>+12</option>
<option value=13>+13</option>
<option value=14>+14</option>
<option value=15>+15</option>
<option value=16>+16</option>
<option value=17>+17</option>
<option value=18>+18</option>
<option value=19>+19</option>
<option value=20>+20</option>

</select>

</td></tr>

<tr><td class=datos2>Da&ntilde;o</td><td> 

<select name="dano">

<option value='Contundente'>Contundente</option>
<option value='Letal'>Letal</option>
<option value='Agravado'>Agravado</option>

</select>

</td></tr>

<tr><td class=datos2>Plata / A.Fetiche</td><td> 

<input type="checkbox" name="plata"> <input type="checkbox" name="afetiche">

</td></tr>

<tr><td class=datos2>Poseedor:</td><td>

<?php

echo "<select name=poseedor><option value=''>- Nadie -</option>";

include("../libreria.php");

mysql_select_db("personajes", $link);
$consulta ="SELECT nombre FROM pjs1 ORDER BY manada";
$query = mysql_query ($consulta, $link);

while ($reg = mysql_fetch_row($query)) {

foreach($reg as $cambia) {
echo "<option value='$cambia'>",$cambia,"</option>";
}
}

echo "</select>";

?>

</td></tr>

<tr><td class=datos2>Imagen:</td><td><input type="text" name="img" width="20" maxlength="1000"></td></tr>

<tr><td class=datos2>Origen:</td><td> 

<select name="origen">

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

</table>

Descripci&oacute;n: <br>

<textarea name=descri rows=5 cols=30></textarea>

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