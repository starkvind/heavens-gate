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

<div class="cuerpo">

<h2> A&ntilde;adir Usuario </h2>

<center>

<form action="agregar2.php" method="POST">

<table>

<tr><td class=datos2>Usuario:</td><td><input type="text" name="usuario" width="10" maxlength="8"></td></tr>
<tr><td class=datos2>Password:</td><td><input type="password" name="password" width="10" maxlength="8"></td></tr>
<tr><td class=datos2>Repite Pass:</td><td><input type="password" name="pass2" width="10" maxlength="8"></td></tr>

</table>

<br>

<input class="boton1" type="submit" value="A&ntilde;adir">


<input class="boton1" type="button" onClick="javascript: history.go(-1)" value="Regresar">

</form>

</center>

</div>

<? }
else { 
?>

<center>ERROR: ACCESO DENEGADO</center>

<? } ?>

</body>

</html>