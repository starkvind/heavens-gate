<fieldset style="border: 1px solid #0000CC;">

<legend style="border: 1px solid #0000CC; padding: 3px; background-color:#000066">&Uacute;ltimos combates</legend>

<?php

$consulta ="SELECT * FROM ultimoscombates ORDER BY id DESC LIMIT 5";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas == "") {

echo "A&uacute;n no se ha celebrado ning&uacute;n combate.";

} else {

for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

$kid = $ResultQuery["id"];

$ki1 = $ResultQuery["luchador1"];
$ki2 = $ResultQuery["luchador2"];
$kires = $ResultQuery["ganador"];

echo "

<table style='border-bottom:1px solid #000099;' width='100%'>

<tr>

<td style='text-align:left;width:6%;'>#<a href='index.php?p=vercombat&amp;b=$kid'>$kid</a></td>
<td style='text-align:right;width:24%;'>$ki1</td>
<td style='text-align:center;width:2%;'><b>VS</b></td>
<td style='text-align:left;width:24%;'> $ki2</td>
<td style='text-align:right;width:44%;'>$kires</td>

</tr>

</table>

";

}

}

?>

</fieldset>

<?php

$consulta ="SELECT * FROM ultimoscombates";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != "") {

$combatesTotales = $NFilas;
echo "<br/><center><a href='index.php?p=combtodo'>Mostrar todos</a></center>";

}

?>

<br/>

<fieldset style="border: 1px solid #0000CC;">

<legend style="border: 1px solid #0000CC; padding: 3px; background-color:#000066">Estad&iacute;sticas</legend>

<?php

$consulta ="SELECT * FROM punkte";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != "") {

/* AWIMBAWE */

$consulta ="SELECT SUM(combates/2) AS ducks FROM punkte";
$pena = mysql_query($consulta, $link);
$yuck = mysql_fetch_array($pena);

$kombination = $yuck['ducks'];
$kombination = round($kombination,0);

$consulta ="SELECT (SELECT MAX(victorias/(victorias+empates+derrotas)*100) LIMIT 1) AS dick FROM punkte";
$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);

$maxymo = $ResultQuery['dick'];

$consulta ="SELECT (SELECT AVG(victorias/(victorias+empates+derrotas)*100) LIMIT 1) AS dick FROM punkte";
$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);

$maxyxo = $ResultQuery['dick'];

$consulta ="SELECT (SELECT MIN(victorias/(victorias+empates+derrotas)*100) LIMIT 1) AS dick FROM punkte";
$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);

$maxyno = $ResultQuery['dick'];

$maxymo = round($maxymo,1);
$maxyno = round($maxyno,1);
$maxyxo = round($maxyxo,1); //  $kombination

echo "

<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='50%'>Combates disputados:</td>
<td width='55%' align='right'><b>$combatesTotales</b></td> 
</tr>
</table>";

/*<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='50%'>Eficacia m&aacute;xima obtenida:</td>
<td width='55%' align='right'><b>$maxymo</b>%</td>
</tr>
</table>
<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='50%'>Eficacia m&iacute;nima alcanzada:</td>
<td width='55%' align='right'><b>$maxyno</b>%</td>
</tr>
</table>
<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='50%'>Eficacia media:</td>
<td width='55%' align='right'><b>$maxyxo</b>%</td>
</tr>
</table>
";*/

/* AWIMBAWE */

$consulta ="SELECT nombre,victorias FROM punkte WHERE victorias LIKE (SELECT MAX(victorias) FROM punkte LIMIT 1) LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);

$nombrevict = $ResultQuery['nombre'];
$numervict = $ResultQuery['victorias'];

echo "
<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='50%'>Mayor n&uacute;mero de combates ganados:</td>
<td width='55%' align='right'><b>$numervict</b> victorias, por <b>$nombrevict</b></td>
</tr>
</table>
";

$consulta ="SELECT nombre,empates FROM punkte WHERE empates LIKE (SELECT MAX(empates) FROM punkte LIMIT 1) LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);

$nombrevict = $ResultQuery['nombre'];
$numervict = $ResultQuery['empates'];

echo "
<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='50%'>Mayor n&uacute;mero de empates obtenidos:</td>
<td width='55%' align='right'><b>$numervict</b> empates, por <b>$nombrevict</b></td>
</tr>
</table>
";

$consulta ="SELECT nombre,derrotas FROM punkte WHERE derrotas LIKE (SELECT MAX(derrotas) FROM punkte LIMIT 1) LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);

$nombrevict = $ResultQuery['nombre'];
$numervict = $ResultQuery['derrotas'];

echo "
<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='50%'>Mayor n&uacute;mero de derrotas:</td>
<td width='55%' align='right'><b>$numervict</b> derrotas, por <b>$nombrevict</b></td>
</tr>
</table>
";

$consulta ="SELECT nombre,danocausado FROM punkte WHERE danocausado LIKE (SELECT MAX(danocausado) FROM punkte LIMIT 1) LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);

$nombrevict = $ResultQuery['nombre'];
$numervict = $ResultQuery['danocausado'];

echo "
<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='45%'>Mayor cantidad de da&ntilde;o provocado:</td>
<td width='55%' align='right'><b>$nombrevict</b> (<b>$numervict</b> puntos de da&ntilde;o)</td>
</tr>
</table>
";

$consulta ="SELECT nombre,danocausado FROM punkte WHERE danocausado LIKE (SELECT MIN(danocausado) FROM punkte LIMIT 1) LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);

$nombrevict = $ResultQuery['nombre'];
$numervict = $ResultQuery['danocausado'];

echo "
<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='50%'>Menor cantidad de da&ntilde;o provocado:</td>
<td width='50%' align='right'><b>$nombrevict</b> (<b>$numervict</b> puntos de da&ntilde;o)</td>
</tr>
</table>
";


$consulta ="SELECT nombre,danorecibido FROM punkte WHERE danorecibido LIKE (SELECT MAX(danorecibido) FROM punkte LIMIT 1) LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);

$nombrevict = $ResultQuery['nombre'];
$numervict = $ResultQuery['danorecibido'];

echo "
<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='45%'>Mayor cantidad de vida perdida:</td>
<td width='55%' align='right'><b>$nombrevict</b> (<b>$numervict</b> puntos de vida)</td>
</tr>
</table>
";

$consulta ="SELECT nombre,danorecibido FROM punkte WHERE danorecibido LIKE (SELECT MIN(danorecibido) FROM punkte LIMIT 1) LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);

$nombrevict = $ResultQuery['nombre'];
$numervict = $ResultQuery['danorecibido'];

echo "
<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='45%'>Menor cantidad de vida perdida:</td>
<td width='55%' align='right'><b>$nombrevict</b> (<b>$numervict</b> puntos de vida)</td>
</tr>
</table>
";

$consulta ="SELECT nombre,veces FROM objetosusados WHERE veces LIKE (SELECT MAX(veces) FROM objetosusados LIMIT 1) LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);

$nombrevict = $ResultQuery['nombre'];
$numervict = $ResultQuery['veces'];

if ($nombrevict != "") {
echo "
<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='50%'>Arma m&aacute;s utilizada:</td>
<td width='55%' align='right'><b>$nombrevict</b>, utilizada <b>$numervict</b> veces</td>
</tr>
</table>
";
}

$consulta ="SELECT nombre,veces FROM objetosusados WHERE veces LIKE (SELECT MIN(veces) FROM objetosusados LIMIT 1) LIMIT 1";

$IdConsulta = mysql_query($consulta, $link);
$ResultQuery = mysql_fetch_array($IdConsulta);

$nombrevict = $ResultQuery['nombre'];
$numervict = $ResultQuery['veces'];

if ($nombrevict != "") {
echo "
<table style='border-bottom:1px solid #000099;width:100%;'>
<tr>
<td width='50%'>Arma menos utilizada:</td>
<td width='55%' align='right'><b>$nombrevict</b>, utilizada <b>$numervict</b> veces</td>
</tr>
</table>";
}

} else {

	echo "A&uacute;n no se ha celebrado ning&uacute;n combate.";

}


?>

</fieldset>

<br/>

<center>

<?php

$consulta ="SELECT * FROM punkte";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != "") {

echo "<a href='index.php?p=punts'>Puntuaciones</a>";

}

$consulta ="SELECT * FROM objetosusados";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas != "") {

echo " · <a href='index.php?p=arms'>Armas utilizadas</a>";

}

?>

</center>
