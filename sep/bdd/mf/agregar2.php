<?php

include("../hehe.php");

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

<?php

$name = $_POST[name];
$tipo = $_POST[tipo];
$afiliacion = $_POST[afiliacion];
$coste = $_POST[coste];
$origen = $_POST[origen];
$sistema = $_POST[sistema];
$descripcion = $_POST[descripcion];

//FIN

include("../libreria.php");

mysql_select_db("$bdd", $link);

if ($name == "") {

	echo "<center>Fallo en el proceso</center>";

} else {

mysql_query(
	"INSERT INTO nuevo_mer_y_def (name, tipo, afiliacion, coste, origen, sistema, descripcion) 
	VALUES ('$name', '$tipo', '$afiliacion', '$coste', '$origen', '$sistema', '$descripcion')",$link);


}

?>

<br>

<center>

<table style="empty-cells: show;">

<tr>

<td colspan="4">

<?php

if (($name == "") OR ($tipo == "") OR ($afiliacion == "")) {

	echo "<h2> Error: El merito o defecto no tiene nombre </h2>";

} else {

	echo "<h3> ¡$tipo agregado correctamente! </h3>"; 

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