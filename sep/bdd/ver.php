<html>

<head>

<title>Heaven's Gate</title>

<link href="style.css" rel="stylesheet" type="text/css">

</head>

<body>

<div class="cuerpo">

<center>

<table>

<tr>

<td colspan="4"> <h2> Ver </h2> </td>

</tr>

<form action="bdd2.php" method="get">

<td colspan="2" class="ajustcelda"> Personaje: </td>

<td colspan="2" class="ajustcelda">

<select name="pj" onChange="cambio()">

<?php

include("libreria.php");

mysql_select_db("$bdd", $link);
$consulta ="SELECT nombre FROM pjs1 ORDER BY nombre";
$query = mysql_query ($consulta, $link);

while ($reg = mysql_fetch_row($query)) {

foreach($reg as $cambia) {
echo "<option value='$cambia'>",$cambia,"</option>";
}
}

?>

</select>

</td>

</tr>

<tr> 

<td colspan="2" class="ajustcelda"> <center> <input class="boton1" type="submit" value="Observar"> </center> </td>

<td colspan="2" class="ajustcelda"> <center> <input class="boton1" type="button" onClick="javascript: history.go(-1)" value="Regresar"> </center> </td>

</tr>

</form>

</table>

<br/>
<a href=bdd1.php> Volver </a>

</center>

</div>

</body>

</html>