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

<h2> Editar Objetos </h2>

<center>

<form action="editar2.php" method="POST">

<?php

include("../libreria.php");

mysql_select_db("$bdd", $link);
$consulta ="SELECT nombre FROM items ORDER BY tipo";
$query = mysql_query ($consulta, $link);

echo "<select name=pj>";

while ($reg = mysql_fetch_row($query)) {

foreach($reg as $cambia) {
echo "<option value='$cambia'>",$cambia,"</option>";
}
}

echo "</select>";

?>

<br><br>

<input class="boton1" type="submit" value="Editar"> 

<input class="boton1" type="button" onClick="javascript:history.go(-1)" value="Regresar">

</form>

</center>

<? }
else { 
?>

<center>ERROR: ACCESO DENEGADO</center>

<? } ?>

</body>

</html>