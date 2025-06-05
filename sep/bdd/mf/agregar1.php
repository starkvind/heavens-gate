<?php

include("../hehe.php");
include("../libreria.php");

?>

<html>

<head>

<title>Heaven's Gate: Añadir Méritos y Defectos</title>


<link href="../style.css" rel="stylesheet" type="text/css">

</head>

<body>

<? if ($valido=="si")
{
?>


<div class="cuerpo">

<h2> Agregar Méritos o Defectos </h2>

<center>

<form action="agregar2.php" method="POST">

<table>

<tr>

<td class=datos2>Nombre:</td>
<td><input type="text" name="name" width="40" maxlength="90"></td>

</tr>

<tr><td class=datos2>Tipo:</td>
<td>

<select name="tipo">
<option value='Méritos'>Mérito</option>
<option value='Defectos'>Defecto</option>

</select>

</td>

</tr>

<tr>

<td class=datos2>Afiliación:</td>
<td>
<select name="afiliacion">
<option value=''>-Ninguno-</option>
		<?php

		$query1 = "SELECT DISTINCT afiliacion FROM nuevo_mer_y_def ORDER BY afiliacion ASC";
		$result1 = mysql_query($query1);
		while ($rows1 = mysql_fetch_array($result1)){
			echo "<option value=\"{$rows1[0]}\"";
			if ($rows["id"] == $rows1[0]) echo " selected";
			echo ">" .$rows1[0]. "</option>\n";
			}
		mysql_free_result($result1);

		?>
</select>
</td>

</tr>

<tr>

<td class=datos2>Coste:</td>
<td>
<select name="coste">

<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
<option value="5">5</option>
<option value="6">6</option>
<option value="7">7</option>
<option value="8">8</option>

</select>

</td>
</tr>

<tr><td class=datos2>Sistema:</td><td> 

<select name='sistema'>

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

		$query1 = "SELECT name, id FROM nuevo2_bibliografia ORDER BY name ASC";
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