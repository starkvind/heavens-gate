<?php

//AQUI COMPROBAMOS SI EL COMBATE HA ACABADO EN EMPATE


//$datosDelArray = implode ( '||', $combateArray ); //serialize($combateArray);
//print_r($combateArray);
//$pruebaArray = serialize($combateArray);
$datosDelArray = serialize($combateArray);


if ($drau != 1) {

/* include("heroes.php"); */

mysql_select_db("$bdd", $link);

/*#################################################################*/

$consultaa ="SELECT nombre FROM punkte WHERE nombre like '$wina";
$querya = mysql_query ($consultaa, $link);

//COMPROBAMOS QUE LOS PERSONAJES ESTEN EN LA TABLA, SINO, LOS CREAMOS

if ($querya != $wina) {

mysql_query("INSERT INTO punkte (id,nombre,victorias,empates,derrotas,combates,puntos,danocausado,danorecibido) values ('$winaID','$wina','0','0','0','0','0','0','0')",$link);

}

$consultab ="SELECT nombre FROM punkte WHERE nombre like '$losa";
$queryb = mysql_query ($consultab, $link);

if ($queryb != $losa) {

mysql_query("INSERT INTO punkte (id,nombre,victorias,empates,derrotas,combates,puntos,danocausado,danorecibido) values ('$losaID','$losa','0','0','0','0','0','0','0')",$link);

}

/*#################################################################*/

// AHORA ACTUALIZAMOS LOS VALORES

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

$consulta ="SELECT victorias,combates,puntos,danocausado,danorecibido FROM punkte WHERE nombre LIKE '$wina';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$v = $yeah["victorias"];
$c = $yeah["combates"];
$p = $yeah["puntos"];
$dc = $yeah["danocausado"];
$dr = $yeah["danorecibido"];


$vict = $v+1;
$comb = $c+1;
$pun = $p+3;
if ($wina == $nombreCom1) { $dac = $dc+$heridas2; $dar = $dr+$heridas1; } else { $dac = $dc+$heridas1; $dar = $dr+$heridas2; }

mysql_query ("UPDATE `punkte` SET `victorias` = '$vict' WHERE nombre LIKE '$wina'");
mysql_query ("UPDATE `punkte` SET `combates` = '$comb' WHERE nombre LIKE '$wina'");
mysql_query ("UPDATE `punkte` SET `puntos` = '$pun' WHERE nombre LIKE '$wina'");
mysql_query ("UPDATE `punkte` SET `danocausado` = '$dac' WHERE nombre LIKE '$wina'");
mysql_query ("UPDATE `punkte` SET `danorecibido` = '$dar' WHERE nombre LIKE '$wina'");

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

$consulta ="SELECT derrotas,combates,danocausado,danorecibido FROM punkte WHERE nombre LIKE '$losa';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$d = $yeah["derrotas"];
$k = $yeah["combates"];
$dcc = $yeah["danocausado"];
$drr = $yeah["danorecibido"];

$derr = $d+1;
$komb = $k+1;
if ($losa == $nombreCom1) { $dacc = $dcc+$heridas2; $darr = $drr+$heridas1; } else { $dacc = $dcc+$heridas1; $darr = $drr+$heridas2; }

mysql_query ("UPDATE `punkte` SET `derrotas` = '$derr' WHERE nombre LIKE '$losa'");
mysql_query ("UPDATE `punkte` SET `combates` = '$komb' WHERE nombre LIKE '$losa'");
mysql_query ("UPDATE `punkte` SET `danocausado` = '$dacc' WHERE nombre LIKE '$losa'");
mysql_query ("UPDATE `punkte` SET `danorecibido` = '$darr' WHERE nombre LIKE '$losa'");

$ip = $_SERVER[REMOTE_ADDR]; /* $_SERVER[HTTP_X_FORWARDED_FOR]*/
$fecha = date("H:i:d:m:Y");

mysql_query("INSERT INTO ultimoscombates (luchador1,luchador2,ganador,ip,hora,turnos) values ('$nombre1','$nombre2','<b>Ganador:</b> $wina','$ip','$fecha','$datosDelArray')",$link);

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

/* ARMAS */

/*#################################################################*/

$consultaa ="SELECT id FROM objetosusados WHERE nombre like '$arma1";
$querya = mysql_query ($consultaa, $link);

//COMPROBAMOS QUE LOS OBJETOS ESTEN EN LA TABLA, SINO, LOS CREAMOS

if ($querya != $arma1) {

mysql_query("INSERT INTO objetosusados (id,nombre,veces) values ('$idarma1','$arma1','0')",$link);

}

$consultab ="SELECT id FROM objetosusados WHERE nombre like '$arma2";
$queryb = mysql_query ($consultab, $link);

//COMPROBAMOS QUE LOS OBJETOS ESTEN EN LA TABLA, SINO, LOS CREAMOS

if ($queryb != $arma2) {

mysql_query("INSERT INTO objetosusados (id,nombre,veces) values ('$idarma2','$arma2','0')",$link);

}

/*#################################################################*/

// AHORA ACTUALIZAMOS LOS VALORES

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

$consulta ="SELECT veces FROM objetosusados WHERE id LIKE '$idarma1';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$v = $yeah["veces"];

$veces = $v+1;

mysql_query ("UPDATE `objetosusados` SET `veces` = '$veces' WHERE id LIKE '$idarma1'");

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

$consulta ="SELECT veces FROM objetosusados WHERE id LIKE '$idarma2';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$v = $yeah["veces"];

$veces = $v+1;

mysql_query ("UPDATE `objetosusados` SET `veces` = '$veces' WHERE id LIKE '$idarma2'");

} 

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

else {

// EMPATE

//$wina = $nombre1;
//$losa = $nombre2;
	$wina = $nombreCom1;//$nombre1;
	$losa = $nombreCom2;//$nombre2;
	$winaID = $idPJno1;
	$losaID = $idPJno2;

if ($nombreCom1 == $nombreCom2) {

/* AKI SE EVITA QUE SE SUMEN LOS VALORES DE EMPATE SI LOS COMBATIENTES SON EL MISMO PJ */

	$tits = 1; /* variable sin relevancia */

/* AKI SE EVITA QUE SE SUMEN LOS VALORES DE EMPATE SI LOS COMBATIENTES SON EL MISMO PJ */

} else {

/*#################################################################*/

$consultaa ="SELECT nombre FROM punkte WHERE nombre like '$wina";
$querya = mysql_query ($consultaa, $link);

//COMPROBAMOS QUE LOS PERSONAJES ESTEN EN LA TABLA, SINO, LOS CREAMOS

if ($querya != $wina) {

mysql_query("INSERT INTO punkte (id,nombre,victorias,empates,derrotas,combates,puntos,danocausado,danorecibido) values ('$winaID','$wina','0','0','0','0','0','0','0')",$link);

}

$consultab ="SELECT nombre FROM punkte WHERE nombre like '$losa";
$queryb = mysql_query ($consultab, $link);

if ($queryb != $losa) {

mysql_query("INSERT INTO punkte (id,nombre,victorias,empates,derrotas,combates,puntos,danocausado,danorecibido) values ('$losaID','$losa','0','0','0','0','0','0','0')",$link);

}

/*#################################################################*/

//AQUI AÑADIMOS LOS EMPATES

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

$consulta ="SELECT empates,combates,puntos,danocausado,danorecibido FROM punkte WHERE nombre LIKE '$wina';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$e = $yeah["empates"];
$c = $yeah["combates"];
$p = $yeah["puntos"];
$dc = $yeah["danocausado"];
$dr = $yeah["danorecibido"];

$emp = $e+1;
$comb = $c+1;
$pun = $p+1;
$dac = $dc+$heridas2;
$dar = $dr+$heridas1;

mysql_query ("UPDATE `punkte` SET `empates` = '$emp' WHERE nombre LIKE '$wina'");
mysql_query ("UPDATE `punkte` SET `combates` = '$comb' WHERE nombre LIKE '$wina'");
mysql_query ("UPDATE `punkte` SET `puntos` = '$pun' WHERE nombre LIKE '$wina'");
mysql_query ("UPDATE `punkte` SET `danocausado` = '$dac' WHERE nombre LIKE '$wina'");
mysql_query ("UPDATE `punkte` SET `danorecibido` = '$dar' WHERE nombre LIKE '$wina'");

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

$consulta ="SELECT empates,combates,puntos,danocausado,danorecibido FROM punkte WHERE nombre LIKE '$losa';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$e2 = $yeah["empates"];
$k = $yeah["combates"];
$u = $yeah["puntos"];
$dcc = $yeah["danocausado"];
$drr = $yeah["danorecibido"];

$emp2 = $e2+1;
$komb = $k+1;
$uun = $u+1;
$dacc = $dcc+$heridas1;
$darr = $drr+$heridas2;

mysql_query ("UPDATE `punkte` SET `empates` = '$emp2' WHERE nombre LIKE '$losa'");
mysql_query ("UPDATE `punkte` SET `combates` = '$komb' WHERE nombre LIKE '$losa'");
mysql_query ("UPDATE `punkte` SET `puntos` = '$uun' WHERE nombre LIKE '$losa'");
mysql_query ("UPDATE `punkte` SET `danocausado` = '$dacc' WHERE nombre LIKE '$losa'");
mysql_query ("UPDATE `punkte` SET `danorecibido` = '$darr' WHERE nombre LIKE '$losa'");

/* SE INTRODUCE EN LA TABLA DE ULTIMOS COMBATES EL RESULTADO */

$ip = $_SERVER[REMOTE_ADDR]; /* $_SERVER[HTTP_X_FORWARDED_FOR]*/
$fecha = date("H:i:d:m:Y");

mysql_query("INSERT INTO ultimoscombates (luchador1,luchador2,ganador,ip,hora,turnos) values ('$nombre1','$nombre2','<b>Empate</b>','$ip','$fecha','$datosDelArray')",$link);

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

/*#################################################################*/

$consultaa ="SELECT id FROM objetosusados WHERE nombre like '$arma1";
$querya = mysql_query ($consultaa, $link);

//COMPROBAMOS QUE LOS OBJETOS ESTEN EN LA TABLA, SINO, LOS CREAMOS

if ($querya != $arma1) {

mysql_query("INSERT INTO objetosusados (id,nombre,veces) values ('$idarma1','$arma1','0')",$link);

}

$consultab ="SELECT id FROM objetosusados WHERE nombre like '$arma2";
$queryb = mysql_query ($consultab, $link);

//COMPROBAMOS QUE LOS OBJETOS ESTEN EN LA TABLA, SINO, LOS CREAMOS

if ($queryb != $arma2) {

mysql_query("INSERT INTO objetosusados (id,nombre,veces) values ('$idarma2','$arma2','0')",$link);

}

/*#################################################################*/

// AHORA ACTUALIZAMOS LOS VALORES

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

$consulta ="SELECT veces FROM objetosusados WHERE id LIKE '$idarma1';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$v = $yeah["veces"];

$veces = $v+1;

mysql_query ("UPDATE `objetosusados` SET `veces` = '$veces' WHERE id LIKE '$idarma1'");

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

$consulta ="SELECT veces FROM objetosusados WHERE id LIKE '$idarma2';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$v = $yeah["veces"];

$veces = $v+1;

mysql_query ("UPDATE `objetosusados` SET `veces` = '$veces' WHERE id LIKE '$idarma2'");

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

}

}

?>