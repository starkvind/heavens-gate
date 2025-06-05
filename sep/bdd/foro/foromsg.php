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

<div class=cuerpo>


<center>

<form action=addmsg.php method=POST>

Autor: <? echo "<input type=text name=autor length=10 maxlength=20 value='$usuario'>"; ?>

&nbsp;

T&iacute;tulo: <input type=text name=titulo length=10 maxlength=50>

<br><br>

Mensaje: <br><br>

<textarea name=mensaje rows=5 cols=35></textarea>

<br><br>

<input class="boton1" type=submit value=Publicar>

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