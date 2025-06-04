<html>

<head>

<title>Heaven's Gate</title>

<link href="style.css" rel="stylesheet" type="text/css">

</head>

<body>

<div class="cuerpo">

<center>

<?php

include("libreria.php");

mysql_select_db($bdd, $link);
$consulta ="SELECT nombre, jugador, tribu, estado FROM pjs1 ORDER BY nombre ASC";
$query = mysql_query ($consulta, $link);

echo "<table class=datox>";

echo "<tr><td colspan=4><h2> Base de Datos </h2></td></tr>";

echo "<tr><td class=datos1>Nombre</td><td class=datos1>Jugador</td><td class=datos1>Tribu</td><td class=datos1>Estado</td></tr>";
while ($reg = mysql_fetch_row($query)) {

echo "<tr>";

foreach($reg as $cambia) {
echo "<td class=datos2>",$cambia,"</td>";
}
}

$numregistros = mysql_num_rows ($query);
print ("<tr><td colspan=4> <br>Personajes hallados:".""." $numregistros <br><br></td></tr>");

?>

<tr> 

<td colspan="4"> <center> <table> <tr>
	

<td class="datos3"><a title="Observar hojas de personaje" href="ver.php">Ver personajes</a></td>


</tr> </table> </center> </td>

</tr> 

	<tr>

		<td colspan=4 align=center>
		<br>
		<a href="gestion.php">Volver</a>
		</td>

	</tr>

</table>

</center>

</div>

</body>

</html>