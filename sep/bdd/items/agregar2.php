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
$clasifi = $_POST[clasifi];
$habilidad = $_POST[habilidad];
$valor = $_POST[valor];
$bonus = $_POST[bonus];
$dano = $_POST[dano];
$plata = $_POST[plata];
$afetiche = $_POST[afetiche];

if ($afetiche == "") { $afetiche = "off"; }

$poseedor = $_POST[poseedor];
$img = $_POST[img];
$descri = $_POST[descri];

$origen = $_POST[origen];


echo "$dano - $plata - $afetiche";

//FIN

include("../libreria.php");

mysql_select_db("$bdd", $link);

if ($nombre == "") {

	echo "<center>Fallo en el proceso</center>";

} else {

	switch($clasifi) {

	case "Armas":

	$tipo = "Arma";
	break;

	case "Protectores";
	
	$tipo = "Protector";
	break;

	case "Fetiches";

	$tipo = "Fetiche";
	break;

	}

	if ($clasifi == "Armas") {

		include("item_convert.php");

mysql_query("insert into items (nombre, tipo, armafeti, clasifi, habilidad, bonus, dano, plata, poseedor, img, descri, 'origen') values ('$nombre', '$tipo', '$afetiche', '$clasifi', '$habilidad', '$bonus', '$dano', '$plata', '$poseedor', '$img', '$descri', '$origen')",$link);


	} else {

		include("item_convert.php");

mysql_query("insert into items (nombre, tipo, armafeti, clasifi, valor, bonus, poseedor, img, descri) values ('$nombre', '$tipo', '$afetiche', '$clasifi', '$valor', '$bonus', '$poseedor', '$img', '$descri')",$link);

	}

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

	echo "<h3> ¡Objeto agregado correctamente! </h3>"; 

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