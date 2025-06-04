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

// Comenzamos a meter todo mecagonto 

$usuario = $_POST[usuario];
$password = $_POST[password];
$pass2 = $_POST[pass2];


//FIN

include("../libreria.php");

mysql_select_db("$bdd", $link);

if ($usuario == "") {

	echo "<center>Fallo en el proceso</center>";

} elseif ($password != $pass2) { 

	echo "<center>Las contrase&ntilde;as no coinciden</center>";

} else {

mysql_query("insert into users (usuario,password) values ('$usuario', '$password')",$link);


}

?>

<center>

<table style="empty-cells: show;">

<tr>

<td colspan="4">

<?php

if ($usuario== "") {

	echo "<h2> Error: El usuario no tiene nombre </h2>";

} elseif ($password != $pass2) { 


	echo "<h2> Error: Las contrase&ntilde;as no coinciden </h2>";

} else {

	echo "<h3> ¡Usuario agregado correctamente! </h3>"; 

}

?>

</td>

</tr>

<tr>

<td class="datos3"><a href="../gestion.php">Regresar</a></td>
<td class="datos3"><a href="newuser.php">Agregar m&aacute;s</a></td>

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