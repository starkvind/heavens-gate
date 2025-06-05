<html>

<head>

<title> CSP </title>

<script type="text/javascript" src="../../../java2.js"></script>

<link href="../../../style.css" rel="stylesheet" type="text/css">

</head>

<body>

<h1>Correo Sincronizado</h1>

<center>

<table class=tablax>

<tr>

<td colspan="6" class="nvmsg">

<a title="Publicar un nuevo tema" href="csp/shake1.php">Nuevo Mensaje</a>

</td>

</tr>

<tr><td colspan=6>

<br>

</td></tr>

<?php

include("libreria.php");

mysql_select_db($bdd, $link);

// ORDEN GUAY

$consulta ="SELECT autor, titulo, mensaje , fecha FROM csp ORDER BY id DESC";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

print("<tr><td class=klax1>Autor:</td><td class=klax2>".$ResultQuery["autor"]."</td><td class=klax1>T&iacute;tulo:</td><td class=klax2>".$ResultQuery["titulo"]."</td><td class=klax1>Fecha:</td><td class=klax2>".$ResultQuery["fecha"]."</td></tr>");

print("<tr></tr><tr><td colspan=6 class=klax2><p>".nl2br($ResultQuery["mensaje"])."</p>\n</td></tr>");

print("<tr><td colspan=6>&nbsp;</td></tr>");

}

?>

</table>

<br>

<input class="boton1" type="button" onClick="javascript: history.go(-1)" value="Regresar">

</center>

</body>

</html>