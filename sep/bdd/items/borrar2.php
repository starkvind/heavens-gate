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

include("../libreria.php");

mysql_select_db("$bdd", $link);

mysql_query("DELETE FROM items WHERE nombre = '$nombre'", $link);

?>

<br>

<center> <?php echo "Se ha borrado el objeto ' $nombre '"; ?> 

<br><br>

<a style="border: 1px solid #fff; background-color: #000066; padding: 2px;" href="../../inventario.html">Regresar</a>

<a style="border: 1px solid #fff; background-color: #000066; padding: 2px;" href="borrar1.php">Borrar m&aacute;s</a>

</center>

<? }
else { 
?>

<center>ERROR: ACCESO DENEGADO</center>

<? } ?>

</body>

</html>