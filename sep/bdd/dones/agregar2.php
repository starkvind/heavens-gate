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

<?php

$nombre = $_POST[nombre];
$tipo = $_POST[tipo];
$grupo = $_POST[grupo];
$rango = $_POST[rango];
$atributo = $_POST[atributo];
$habilidad = $_POST[habilidad];

$descripcion = $_POST[descripcion];
$sistema = $_POST[sistema];

$ferasistema = $_POST[ferasistema];
$origen = $_POST[origen];

//FIN

include("../libreria.php");

mysql_select_db("$bdd", $link);

if ($nombre == "") {

	echo "<center>Fallo en el proceso</center>";

} else {

mysql_query("INSERT INTO dones (nombre, tipo, grupo, rango, atributo, habilidad, descripcion, sistema, ferasistema, origen) VALUES ('$nombre', '$tipo', '$grupo', '$rango', '$atributo', '$habilidad', '$descripcion', '$sistema', '$ferasistema', '$origen')",$link);


}

?>

<br>

<center>

<table style="empty-cells: show;">

<tr>

<td colspan="4">

<?php

if (($nombre == "") OR ($tipo == "") OR ($grupo == "")) {

	echo "<h2> Error: El don no tiene nombre </h2>";

} else {

	echo "<h3> ¡Don agregado correctamente! </h3>"; 

}

?>

</td>

</tr>


<tr>

<td colspan="2" class="datos3"><a href="../gestion.php">Regresar</a></td>
<td colspan="2" class="datos3"><a href="agregar1.php">Agregar m&aacute;s</a></td>

</tr>

</table>

</center>

<br>

<? }
else { 
?>

<center>ERROR: ACCESO DENEGADO</center>

<? } ?>

</body>

</html>