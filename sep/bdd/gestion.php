<?php

include ("hehe.php");

?>

<html>

<head>

<title>Heaven's Gate</title>

<link href="style.css" rel="stylesheet" type="text/css">

</head>

<body>

<h4> GESTI&Oacute;N </h4>


<? if ($valido=="si")
{
?>
<center> <table>

<tr><td colspan=3>Bienvenido, <?php echo $usuario ?></td></tr><tr><td>&nbsp;</td></tr>

<tr><td colspan=2>Noticias</td></tr>

<tr>
	
<td class=datos3><a href=foro/foromsg.php>Escribir Mensaje</a></td>
<td class=datos3><a href=foro/borrar1.php>Borrar Mensaje</a></td>

</tr>

<tr><td colspan=2>&nbsp;</td></tr>

<tr><td colspan=2>Personajes</td></tr>

<tr>

<td class=datos3><a href=based/agregar1.php>A&ntilde;adir personajes</a></td>
<td class=datos3><a href=based/editar1.php>Editar personajes</a></td>
<td class=datos3><a href=based/borrar1.php>Borrar personajes</a></td>

</tr>

<tr>

<td class=datos3><a href=bdd1.php>Ver personajes</a></td>
<td class=datos3>Añadir jugadores</td>

</tr>

<tr><td colspan=2>&nbsp;</td></tr>

<tr><td colspan=2>Inventario</td></tr>

<tr>

<td class=datos3><a href=items/agregar1.php>A&ntilde;adir objetos</a></td>

<td class=datos3><a href=items/editar1.php>Editar objetos</a></td>

<td class=datos3><a href=items/borrar1.php>Borrar objetos</a></td>

</tr>

<tr>

<td class=datos3><a href=mf/agregar1.php>A&ntilde;adir M & D</a></td>
<td class=datos3><a href=dones/agregar1.php>A&ntilde;adir dones</a></td>
<td class=datos3>Añadir totems</td>

</tr>

<tr><td colspan=2>&nbsp;</td></tr>

<tr><td colspan=2>Usuarios / Sesi&oacute;n</td></tr>

<tr> <td class=datos3><a href=add/newuser.php>Agregar Usuario</a></td>

<td class=datos3><a href=add/deluser.php>Borrar Usuario</a></td>

<td class=datos3><a href=destruir.php>Abandonar Sesi&oacute;n</a></td>
</tr>

</table>

</table> </center> 
<? }
else
{ 
// Borramos la variable
unset ( $_SESSION['usuario'] ); {
// Borramos toda la sesion
session_destroy();

}
?>
<center>ERROR AL VERIFICAR LOS DATOS: ACCESO DENEGADO</center>
<? 


} ?>

</body>

</html>