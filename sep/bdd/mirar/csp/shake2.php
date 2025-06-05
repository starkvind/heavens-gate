<html>

<head>

<title>Heaven's Gate</title>

<link href="../../../../style.css" rel="stylesheet" type="text/css">

</head>

<body>

<?php

$autor = $_POST['autor'];
$titulo = $_POST['titulo'];
$fecha = date("H:i:s, d-m-Y");
$mensaje = $_POST['mensaje'];


include("../libreria.php");

mysql_select_db("$bdd", $link);

if ($autor == "" OR $mensaje == "") {

	echo "<center>Fallo en el proceso</center>";

} else {

mysql_query("insert into csp (autor,titulo,fecha,mensaje) values ('$autor', '$titulo', '$fecha', '$mensaje')",$link);

}

?>

<br>

<table align="center" style="empty-cells: show;">

<tr>

<td>

<?php

if ($autor == "" OR $mensaje == "") {

	echo "<h2> Error: Mensaje sin autor o texto </h2>";

} else {

	echo "<h3> ¡Mensaje agregado correctamente! </h3>";
	echo "<center>En la correspondiente fecha: $fecha</center>"; 

}

?>

</td>

</tr>

<tr><td align="center"><a href="../csp.php">Volver</a></td></tr>

</table>

</body>

</html>