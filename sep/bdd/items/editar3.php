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

$pj = $_POST['pj'];

$nombre = $_POST['nombre'];
$tipo = $_POST['tipo'];
$valor = $_POST['valor'];
$bonus = $_POST['bonus'];

$poseedor = $_POST['poseedor'];
$img = $_POST['img'];
$descri = $_POST['descri'];

include("../libreria.php");

mysql_select_db("$bdd", $link);

if ($nombre == "") {

	echo "<center>Fallo en el proceso</center>";

} else {

$consulta = "UPDATE items SET 

`nombre` = '$nombre',
`tipo` = '$tipo',
`valor` = '$valor',
`bonus` = '$bonus',
`poseedor` = '$poseedor',
`img` = '$img',
`descri` = '$descri'

WHERE nombre = '$pj'";

$query = mysql_query ($consulta, $link);


}


?>

<br>

<center>

<table style="empty-cells: show;">

<tr>

<td colspan="4">

<?php

if ($nombre == "") {

	echo "<h2> Error: El objeto no tiene nombre </h2>";

} else {

	echo "<h3> ¡Objeto editado correctamente! </h3>"; 

}

?>

</td>

</tr>

<tr>

<td colspan="2" class="datos3"><a href="../../inventario.html">Regresar</a></td>
<td colspan="2" class="datos3"><a href="editar1.php">Editar m&aacute;s</a></td>

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