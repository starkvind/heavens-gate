<?php

include("../hehe.php");

?>

<html>

<head>

<title>Heaven's Gate</title>

<link href="../../../../style.css" rel="stylesheet" type="text/css">

</head>

<body>

<? if ($valido=="si")
{
?>

<?php

$autor = $_POST['autor'];
$titulo = $_POST['titulo'];
$fecha = date("y-m-d");
$mensaje = $_POST['mensaje'];


include("../libreria.php");

mysql_select_db("$bdd", $link);

if ($autor == "" OR $mensaje == "") {

	echo "<center>Fallo en el proceso</center>";

} else {

mysql_query("insert into msg (autor,titulo,fecha,mensaje) values ('$autor', '$titulo', '$fecha', '$mensaje')",$link);

}

?>


<div class="cuerpo">

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

<tr><td align="center"><a href="../gestion.php">Volver</a></td></tr>

</table>

</div>

<? }
else { 
?>

<center>ERROR: ACCESO DENEGADO</center>

<? } ?>

</body>

</html>