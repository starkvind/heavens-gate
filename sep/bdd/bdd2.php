<head>

<?php $pj = $_GET['pj']; ?>

<title> Heaven's Gate </title>

<link href="style.css" rel="stylesheet" type="text/css">

</head>


<body>

<div class="cuerpo">

<table class="datox">

<?php

include("libreria.php");

mysql_select_db("$bdd", $link);

$consulta ="SELECT img FROM pjs1 WHERE nombre LIKE '$pj';";
$kery = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($kery);

$ima = $yeah["img"];

$Query = "SELECT nombre,raza,manada,jugador,auspicio,totem,cronica,tribu,concepto,fuerza,destreza,resistencia,carisma,manipulacion,apariencia,percepcion,inteligencia,astucia,alerta,armascc,ciencias,atletismo,armasdefuego,enigmas,callejeo,conducir,informatica,empatia,etiqueta,investigacion,esquivar,interpretacion,leyes,expresion,liderazgo,linguistica,impulsprimario,reparaciones,medicina,intimidacion,sigilo,ocultismo,pelea,supervivencia,politica,subterfugio,tratoanimales,rituales,trasfondo1,trasfondo2,trasfondo3,trasfondo4,trasfondo5,trasfondo1valor,trasfondo2valor,trasfondo3valor,trasfondo4valor,trasfondo5valor,don1,don2,don3,don4,don5,don6,don7,don8,don9,don10,gloriat,gloriap,honort,honorp,sabiduriat,sabiduriap,rabiap,rabiag,gnosisp,gnosisg,fvp,fvg,rango,estado,img FROM pjs1 WHERE nombre LIKE '$pj';";

$IdConsulta = mysql_query($Query, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

//Datos del Personaje

print("<tr><td colspan=6 class=bext1><h1>".$ResultQuery["nombre"]."</h1></td></tr>");

print("<tr><td colspan=2>&nbsp;</td></tr>");

print("<tr><td class=bext1>Nombre:</td><td class=bext2>".$ResultQuery["nombre"]."</td><td class=bext1>Raza:</td><td class=bext2>".$ResultQuery["1"]."</td><td class=bext1>Manada:</td><td class=bext2>".$ResultQuery["2"]."</td></tr>");

print("<tr><td class=bext1>Jugador:</td><td class=bext2> ".$ResultQuery[3]."</td><td class=bext1>Auspicio:</td><td class=bext2> ".$ResultQuery[4]."</td><td class=bext1>T&oacute;tem:</td><td class=bext2> ".$ResultQuery[5]."</td></tr>");

print("<tr><td class=bext1>Cr&oacute;nica:</td><td class=bext2> ".$ResultQuery[6]."</td><td class=bext1>Tribu:</td><td class=bext2> ".$ResultQuery[7]."</td><td class=bext1>Concepto:</td><td class=bext2> ".$ResultQuery[8]."</td></tr>");

print("<tr><td colspan=2>&nbsp;</td></tr>");

//Atributos

print("<tr><td class=bext1>Fuerza:</td><td class=bext2> ".$ResultQuery[9]."</td><td class=bext1>Carisma:</td><td class=bext2> ".$ResultQuery[12]."</td><td class=bext1>Percepci&oacute;n:</td><td class=bext2> ".$ResultQuery[15]."</td></tr>");

print("<tr><td class=bext1>Destreza:</td><td class=bext2> ".$ResultQuery[10]."</td><td class=bext1>Manipulaci&oacute;n:</td><td class=bext2> ".$ResultQuery[13]."</td><td class=bext1>Inteligencia:</td><td class=bext2> ".$ResultQuery[16]."</td></tr>");

print("<tr><td class=bext1>Resistencia:</td><td class=bext2> ".$ResultQuery[11]."</td><td class=bext1>Apariencia:</td><td class=bext2> ".$ResultQuery[14]."</td><td class=bext1>Astucia:</td><td class=bext2> ".$ResultQuery[17]."</td></tr>");

print("<tr><td colspan=2>&nbsp;</td></tr>");

//Habilidades

print("<tr><td class=bext1>Alerta:</td><td class=bext2> ".$ResultQuery[18]."</td><td class=bext1>Armas CC:</td><td class=bext2> ".$ResultQuery[19]."</td><td class=bext1>Ciencias:</td><td class=bext2> ".$ResultQuery[20]."</td></tr>");

print("<tr><td class=bext1>Atletismo:</td><td class=bext2> ".$ResultQuery[21]."</td><td class=bext1>Armas Fuego:</td><td class=bext2> ".$ResultQuery[22]."</td><td class=bext1>Enigmas:</td><td class=bext2> ".$ResultQuery[23]."</td></tr>");

print("<tr><td class=bext1>Callejeo:</td><td class=bext2> ".$ResultQuery[24]."</td><td class=bext1>Conducir:</td><td class=bext2> ".$ResultQuery[25]."</td><td class=bext1>Inform&aacute;tica:</td><td class=bext2> ".$ResultQuery[26]."</td></tr>");

print("<tr><td class=bext1>Empat&iacute;a:</td><td class=bext2> ".$ResultQuery[27]."</td><td class=bext1>Etiqueta:</td><td class=bext2> ".$ResultQuery[28]."</td><td class=bext1>Investigaci&oacute;n:</td><td class=bext2> ".$ResultQuery[29]."</td></tr>");

print("<tr><td class=bext1>Esquivar:</td><td class=bext2> ".$ResultQuery[30]."</td><td class=bext1>Interpretaci&oacute;n:</td><td class=bext2> ".$ResultQuery[31]."</td><td class=bext1>Leyes:</td><td class=bext2> ".$ResultQuery[32]."</td></tr>");

print("<tr><td class=bext1>Expresi&oacute;n:</td><td class=bext2> ".$ResultQuery[33]."</td><td class=bext1>Liderazgo:</td><td class=bext2> ".$ResultQuery[34]."</td><td class=bext1>Ling&uuml;&iacute;stica:</td><td class=bext2> ".$ResultQuery[35]."</td></tr>");

print("<tr><td class=bext1>Imp. Primario:</td><td class=bext2> ".$ResultQuery[36]."</td><td class=bext1>Reparaciones:</td><td class=bext2> ".$ResultQuery[37]."</td><td class=bext1>Medicina:</td><td class=bext2> ".$ResultQuery[38]."</td></tr>");

print("<tr><td class=bext1>Intimidaci&oacute;n:</td><td class=bext2> ".$ResultQuery[39]."</td><td class=bext1>Sigilo:</td><td class=bext2> ".$ResultQuery[40]."</td><td class=bext1>Ocultismo:</td><td class=bext2> ".$ResultQuery[41]."</td></tr>");

print("<tr><td class=bext1>Pelea:</td><td class=bext2> ".$ResultQuery[42]."</td><td class=bext1>Supervivencia:</td><td class=bext2> ".$ResultQuery[43]."</td><td class=bext1>Pol&iacute;tica:</td><td class=bext2> ".$ResultQuery[44]."</td></tr>");

print("<tr><td class=bext1>Subterfugio:</td><td class=bext2> ".$ResultQuery[45]."</td><td class=bext1>Trato Animales:</td><td class=bext2> ".$ResultQuery[46]."</td><td class=bext1>Rituales:</td><td class=bext2> ".$ResultQuery[47]."</td></tr>");

print("<tr><td colspan=2>&nbsp;</td></tr>");

//Venpajas

print("<tr><td class=bext1>".$ResultQuery[48].":</td><td class=bext2> ".$ResultQuery[53]."</td><td colspan=2 class=bext2>".$ResultQuery['don1']."</td><td colspan=2 class=bext2> ".$ResultQuery['don2']."</td></tr>");

print("<tr><td class=bext1>".$ResultQuery[49].":</td><td class=bext2> ".$ResultQuery[54]."</td><td colspan=2 class=bext2>".$ResultQuery['don3']."</td><td colspan=2 class=bext2> ".$ResultQuery['don4']."</td></tr>");

print("<tr><td class=bext1>".$ResultQuery[50].":</td><td class=bext2> ".$ResultQuery[55]."</td><td colspan=2 class=bext2>".$ResultQuery['don5']."</td><td colspan=2 class=bext2> ".$ResultQuery['don6']."</td></tr>");

print("<tr><td class=bext1>".$ResultQuery[51].":</td><td class=bext2> ".$ResultQuery[56]."</td><td colspan=2 class=bext2>".$ResultQuery['don7']."</td><td colspan=2 class=bext2> ".$ResultQuery['don8']."</td></tr>");

print("<tr><td class=bext1>".$ResultQuery[52].":</td><td class=bext2> ".$ResultQuery[57]."</td><td colspan=2 class=bext2>".$ResultQuery['don9']."</td><td colspan=2 class=bext2> ".$ResultQuery['don10']."</td></tr>");

print("<tr><td colspan=2>&nbsp;</td></tr>");

// Cosas y eso

print("<tr><td class=bext1>Gloria <a title=Permanente/Temporal>(P/T)</a>:</td><td class=bext2> ".$ResultQuery['gloriap']." / ".$ResultQuery['gloriat']."</td><td class=bext1>Rabia <a title=Permanente/Gastada>(P/G)</a></td><td class=bext2> ".$ResultQuery['rabiap']." / ".$ResultQuery['rabiag']."</td><td colspan=2 rowspan=5 class=bext2><center><img width=100 height=100 src='$ima'></center></td></tr>");

print("<tr><td class=bext1>Honor <a title=Permanente/Temporal>(P/T)</a>:</td><td class=bext2> ".$ResultQuery['honorp']." / ".$ResultQuery['honort']."</td><td class=bext1>Gnosis <a title=Permanente/Temporal>(P/G)</a></td><td class=bext2> ".$ResultQuery['gnosisp']." / ".$ResultQuery['gnosisg']."</td></tr>");

print("<tr><td class=bext1>Sabidur&iacute;a <a title=Permanente/Temporal>(P/T)</a>:</td><td class=bext2> ".$ResultQuery['sabiduriap']." / ".$ResultQuery['sabiduriat']."</td><td class=bext1>Voluntad <a title=Permanente/Temporal>(P/G)</a></td><td class=bext2> ".$ResultQuery['fvp']." / ".$ResultQuery['fvg']."</td></tr>");

print("<tr><td colspan=2>&nbsp;</td></tr>");

print("<tr><td class=bext1>Rango:</td><td class=bext2> ".$ResultQuery['rango']."</td><td class=bext1>Estado:</td><td class=bext2> ".$ResultQuery['estado']."</td></tr>");

}

?>

</table>

<br>

<center><a href="ver.php">Volver</a></center>

</div>

</body>

</html>

