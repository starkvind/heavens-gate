<?php

include("../hehe.php");

?>

<html>

<head>

<title>Heaven's Gate</title>

<link href="../style.css" rel="stylesheet" type="text/css">

</head>

<body>

<div class="cuerpo">

<center>

<h2> Agregar Personajes Nuevos </h2>

<form action="agregar2.php" method="POST" enctype="multipart/form-data">

<table>

<tr>

<td class=datos2>Juego:</td>
	<td>
		<select name="system">
		<option value='' selected>- Ninguna -</option>
		<?php

		$query1 = "SELECT id, name FROM nuevo_sistema ORDER BY id ASC";
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

<td class=datos2>Nombre:</td>
<td><input type="text" name="nombre" width="20" maxlength="20"></td>

<td class=datos2>Raza:</td>
	<td>
		<select name="raza">
		<option value='' selected>- Ninguna -</option>
		<?php

		$query1 = "SELECT id, name FROM nuevo_razas ORDER BY id ASC";
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

<td class=datos2>Manada:</td>
	<td>
	<input type="text" name="manada" width="20" maxlength="30">
	</td>

	</tr>

	<tr>

<td class=datos2>Jugador:</td><td><input type="text" name="jugador" width="20" maxlength="20"></td>

<td class=datos2>Auspicio:</td><td>

<select name="auspicio">

<option value="" selected>- Ninguno -</option>

<?php

$query1 = "SELECT id, name FROM nuevo_auspicios ORDER BY id ASC";
$result1 = mysql_query($query1);
while ($rows1 = mysql_fetch_array($result1)){
echo "<option value=\"{$rows1[0]}\"";
if ($rows["id"] == $rows1[0]) echo " selected";
echo ">" .$rows1[1]. "</option>\n";
}
mysql_free_result($result1);

?>

</select></td>

<td class=datos2>T&oacute;tem:</td><td><input type="text" name="totem" width="20" maxlength="20"></td>

	</tr>

	<tr>
<td class=datos2>Cr&oacute;nica:</td>
<td><input type="text" name="cronica" width="20" maxlength="20"></td>

<td class=datos2>Tribu:</td>
<td>

<select name="tribu">

<option value='' selected>- Ninguna -</option>

<?php

$query1 = "SELECT id, name FROM nuevo_tribus ORDER BY sistema ASC";
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
	<td class=datos2>Concepto:</td>
	<td><input type="text" name="concepto" width="20" maxlength="20"></td>
</tr>

</table>

<br/>

<input class="boton1" type="submit" value="A&ntilde;adir">
<input class="boton1" type="button" onClick="javascript: history.go(-1)" value="Regresar">

</form>

</center>

</div>

</body>

</html>