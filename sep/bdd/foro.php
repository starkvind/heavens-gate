<html>

<head>

<title>Heaven's Gate</title>

<link href="style.css" rel="stylesheet" type="text/css">

</head>

<body>

<div class="cuerpo">

<center>

<table class=tablax>

<?php

include("libreria.php");

mysql_select_db($bdd, $link);
$consulta ="SELECT autor, mensaje , fecha FROM msg ORDER BY id DESC LIMIT 3";

echo "<tr><td colspan=4><h2> &Uacute;ltimas noticias </h2></td></tr>";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

print("<tr><td class=arrib>Autor:</td><td class=abaj>".$ResultQuery["autor"]."</td><td class=arrib>Fecha:</td><td class=abaj>".$ResultQuery["fecha"]."</td></tr>");

print("<tr><td class=arrib>T&iacute;tulo:</td><td colspan=3 class=arrib> </td></tr><tr><td colspan=4 class=abaj><p>".nl2br($ResultQuery["mensaje"])."</p>\n</td></tr>");

print("<tr><td colspan=4>&nbsp;</td></tr>");

}

?>

	<tr>

		<td colspan=4 align=center>
		<br>
		<input class="boton1" type="button" onClick="javascript: history.go(-1)" value="Regresar">
		</td>

	</tr>

</table>

</center>

</div>

</body>

</html>