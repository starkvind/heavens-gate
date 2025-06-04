<head>

<style type='text/css'>

fieldset.tirada {

	background-color: #191970;
	border: 1px solid #0000CD;
	padding: 6px;
	
}

legend.tirada {

	background-color: #191970;
	border: 1px solid #0000CD;
	padding: 3px;
	
}


</style>

</head>

<?php


// PONEMOS LAS OPCIONES

$nombre1 = "$_POST[pj1]";
$nombre2 = "$_POST[pj2]";

$pjAleatorio = "$_POST[aleatorio]";
$formaAleatoria = "$_POST[formarandom]"; // NO IMPLEMENTADO
$armaAleatoria = "$_POST[armasrandom]";
$protAleatoria = "$_POST[protrandom]";

$tipoCombate = "$_POST[combate]";//

$turnAleatorio = "$_POST[turnrandom]";
$vitAleatoria = "$_POST[vitrandom]";

$aplicarHeridas = "$_POST[usarheridas]";
$usarRegen = "$_POST[regeneracion]";

if ($pjAleatorio == "yes") {
	include("sim_battle_random_characters.php");
}

$Query = "SELECT id, nombre, alias, fera, sistema FROM pjs1 WHERE id LIKE '$nombre1' LIMIT 1;";
$IdConsulta = mysql_query($Query, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

$nombre1 = $ResultQuery[alias];
$nombreCom1 = $ResultQuery[nombre];
$fera1 = $ResultQuery[fera];
$sistema1 = $ResultQuery[sistema];

$idPJno1 = $ResultQuery[id];

}

$Query = "SELECT id, nombre, alias, fera, sistema FROM pjs1 WHERE id LIKE '$nombre2' LIMIT 1;";
$IdConsulta = mysql_query($Query, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

$nombre2 = $ResultQuery[alias];
$nombreCom2 = $ResultQuery[nombre];
$fera2 = $ResultQuery[fera];
$sistema2 = $ResultQuery[sistema];

$idPJno2 = $ResultQuery[id];

}

$pageSect = "Resultados del combate"; // PARA CAMBIAR EL TITULO A LA PAGINA

$ip2 = $_SERVER[REMOTE_ADDR]; /* $_SERVER[HTTP_X_FORWARDED_FOR]; */

/* MIRAMOS LA ULTIMA HORA DE ACCESO */

$Query = "SELECT hora FROM ultimoscombates WHERE ip LIKE '$ip2' ORDER BY id DESC LIMIT 1;";
$IdConsulta = mysql_query($Query, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

$horax = $ResultQuery[hora];

}

include ("sim_script_check_time.php"); /* Script para evitar spam de POST */ 

$podesluchar = 1;

if ($podesluchar == 1) {

/*  CHEKEAMOS LA HORA PARA QUE NO OCURRAN HIJOPUTECES COMO LAS DE GAGO */

if ($nombreCom1 == $nombreCom2) {

	echo "<center>El combate entre dos $nombreCom2 ($nombre2) no puede ocurrir...<br/><br/>
	<input class='boton1' type='button' onclick='history.go(-1)' value='Volver'/></center>";

} elseif (($nombre1 == '') OR ($nombre2 == '')) {

	echo "<center>Elige dos personajes diferentes para que puedan luchar...<br/><br/>
	<input class='boton1' type='button' onclick='history.go(-1)' value='Volver'/></center>";
} elseif (($sistema1 == "Vampiro" AND $tipoCombate == "umbral") OR ($sistema2 == "Vampiro" AND $tipoCombate == "umbral")) {

	echo "<center>Uno de los personajes elegidos no puede combatir en la Umbra.<br/><br/>
	<input class='boton1' type='button' onclick='history.go(-1)' value='Volver'/></center>";
} else {

$arma1 = "$_POST[arma1]";
$arma2 = "$_POST[arma2]";

$arma11 = "$_POST[arma1]";
$arma21 = "$_POST[arma2]";

if ($armaAleatoria == "yes") {
	include("sim_battle_random_weapons.php");
}
// ARMA JUGADOR 1 //
$Query = "SELECT name FROM nuevo3_objetos WHERE id LIKE '$arma1';";
$IdConsulta = mysql_query($Query, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

$arma1 = $ResultQuery[name];

}
// ARMA JUGADOR 2 //
$Query = "SELECT name FROM nuevo3_objetos WHERE id LIKE '$arma2';";
$IdConsulta = mysql_query($Query, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

$arma2 = $ResultQuery[name];

}

$protec1 = "$_POST[protec1]";
$protec2 = "$_POST[protec2]";

$protec11 = "$_POST[protec1]";
$protec21 = "$_POST[protec2]";

if ($protAleatoria == "yes") {
	include("sim_battle_random_protections.php");
}
// PROTECCION JUGADOR 1 //
$Query = "SELECT name FROM nuevo3_objetos WHERE id LIKE '$protec1';";
$IdConsulta = mysql_query($Query, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

$protec1 = $ResultQuery[name];

}
// PROTECCION JUGADOR 2 //
$Query = "SELECT name FROM nuevo3_objetos WHERE id LIKE '$protec2';";
$IdConsulta = mysql_query($Query, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

$protec2 = $ResultQuery[name];

}
// FORMAS DE LOS PERSONAJES

if ($formaAleatoria != "yes") {
	$forma1 = "$_POST[forma1]";
	$forma2 = "$_POST[forma2]";
} else {
	include ("sim_battle_random_forms.php");
	/* echo "$nombre1 + $nombre2";
	echo "<br/>";
	echo "$fera1 + $fera2";
	echo "<br/>";
	echo "$nameForma1 + $nameForma2"; */
}

if ($turnAleatorio == "yes") { $maxturn = rand(1, 99); } else { $maxturn = "$_POST[turnos]"; }
if ($vitAleatoria == "yes") { $vitmax = rand(1, 99); } else { $vitmax = "$_POST[vit]"; }

$debug = "$_POST[debug]";
$ventaja = "$_POST[ventaja]";

/* OTORGAMOS LOS OK A LA VITALIDAD DE LOS AMIGOS */

$heridas1 = 0;
$heridas2 = 0;

include ("sim_text_derrota.php"); /* Incluimos las frase de la derrota */

$n1cae = "¡$nombre1 $kae!";//"<tr><td colspan='4' class='ajustcelda'>¡$nombre1 $kae!</td></tr>";
$n2cae = "¡$nombre2 $kae!";//"<tr><td colspan='4' class='ajustcelda'>¡$nombre2 $kae!</td></tr>";
$doubleKO = "¡Los dos combatientes se han matado el uno al otro!";
$mensajefin = "¡Tiempo! Combate terminado.";

// PRIMER PJ (ARMA)

$consulta ="SELECT habilidad,bonus,dano,metal FROM nuevo3_objetos WHERE id LIKE '$arma11';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$skillCheck4Form1 = $yeah["habilidad"];
$bonus1 = $yeah["bonus"];
$danyo1 = $yeah["dano"];
$plata1 = $yeah["metal"];


// PRIMER PJ (PROTECTOR)

$consulta ="SELECT bonus, destreza FROM nuevo3_objetos WHERE id LIKE '$protec11';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$armor1 = $yeah["bonus"];
$malusArmor1 = $yeah["destreza"];

// PRIMER PJ

$consulta ="SELECT * FROM pjs1 WHERE nombre LIKE '$nombreCom1' LIMIT 1;";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$hid1 = $yeah["id"];

$fuerza1 = $yeah["fuerza"];
$destre1 = $yeah["destreza"];
$resist1 = $yeah["resistencia"];
$astuci1 = $yeah["astucia"];

$atletismo1 = $yeah["atletismo"];
$pelea1 = $yeah["pelea"];
$esquivar1 = $yeah["esquivar"];
$esquivar12 = $yeah["esquivar"];
$armascc1 = $yeah["armascc"];
$armasdefuego1 = $yeah["armasdefuego"];

$rabia1 = $yeah["rabiap"];
$gnosis1 = $yeah["gnosisp"];
$fvp1 = $yeah["fvp"];


$img1 = $yeah["img"];

// SEGUNDO PJ (ARMA)

$consulta ="SELECT habilidad,bonus,dano,metal FROM nuevo3_objetos WHERE id LIKE '$arma21';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$skillCheck4Form2 = $yeah["habilidad"];
$bonus2 = $yeah["bonus"];
$danyo2 = $yeah["dano"];
$plata2 = $yeah["metal"];

// SEGUNDO PJ (PROTECTOR)

$consulta ="SELECT bonus, destreza FROM nuevo3_objetos WHERE id LIKE '$protec21';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$armor2 = $yeah["bonus"];
$malusArmor2 = $yeah["destreza"];

// SEGUNDO PJ

$consulta ="SELECT * FROM pjs1 WHERE nombre LIKE '$nombreCom2' LIMIT 1;";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$hid2 = $yeah["id"];

$fuerza2 = $yeah["fuerza"];
$destre2 = $yeah["destreza"];
$resist2 = $yeah["resistencia"];
$astuci2 = $yeah["astucia"];

$atletismo2 = $yeah["atletismo"];
$pelea2 = $yeah["pelea"];
$esquivar2 = $yeah["esquivar"];
$esquivar22 = $yeah["esquivar"];
$armascc2 = $yeah["armascc"];
$armasdefuego2 = $yeah["armasdefuego"];

$rabia2 = $yeah["rabiap"];
$gnosis2 = $yeah["gnosisp"];
$fvp2 = $yeah["fvp"];

$img2 = $yeah["img"];

$hp1 = $vitmax;
$hp2 = $hp1;

//CHECK FORMAS

include ("sim_battle_formas.php"); /* Comprobamos las formas de los pjs y aplicamos los atributos */

if ($fuerza1 <= 0) { $fuerza1 = 1; } // Ponemos Fuerza a 1 si es 0 o menos. Para las formas que quitan Atributos
if ($fuerza2 <= 0) { $fuerza2 = 1; } // Ponemos Fuerza a 1 si es 0 o menos. Para las formas que quitan Atributos

if ($ventaja == 'pj1') { $hp1 = $hp1+10; } elseif ($ventaja == 'pj2') { $hp2 = $hp2+10; }
else { echo ""; }

//<p style="text-align: right;"><a href="#" onclick="recargar()">Otro más! </a></p>

include("sep/main/main_nav_bar.php");	// Barra Navegación

?>

<h2> Resultados del Combate </h2>

<table>

<tr>

<td class="ajustcelda" colspan="4" style="
	text-align:center;
	text-transform:uppercase;
	font-weight: bold;
">

<?php
	if ($tipoCombate != "umbral") {
		echo "Combate a muerte";
	} else {
		echo "Combate umbral";
	}
?>

</td>

</tr>

<tr>

<td class="ajustcelda" colspan="2">

<?php echo "<center>
<a href='index.php?p=muestrabio&b=$hid1' target='_blank'>
<img class='photobio' src='$img1' title='$nombreCom1'>
</a>
</center>"; ?>

</td>

<td class="ajustcelda" colspan="2">

<?php echo "<center>
<a href='index.php?p=muestrabio&b=$hid2' target='_blank'>
<img class='photobio' src='$img2' title='$nombreCom2'>
</a>
</center>"; ?>

</td>

</tr>

<td class="ajustcelda" colspan="2"> 

<?php echo"$nombreCom1 <hr style='border: 1px solid #009;'/> $nombre1"; ?>

</td>

<td class="ajustcelda" colspan="2"> 

<?php echo"$nombreCom2 <hr style='border: 1px solid #009;'/> $nombre2"; ?>

</td>

</tr>

<tr><td colspan="4">&nbsp;</td></tr>

<?php if ($tipoCombate != "umbral") { ?>

<tr>

<td class="ajustcelda"> Arma: </td>

<td class="ajustcelda"> 

<?php if ($arma1 != "") { echo "$arma1 ($bonus1)"; } else { echo "Sin arma"; } ?>

</td>

<td class="ajustcelda"> Arma: </td>

<td class="ajustcelda"> 

<?php if ($arma2 != "") { echo "$arma2 ($bonus2)"; } else { echo "Sin arma"; } ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Protector: </td>

<td class="ajustcelda"> 

<?php if ($protec1 != "") { echo "$protec1 (+$armor1)"; } else { echo "Ninguno"; } ?>

</td>

<td class="ajustcelda"> Protector: </td>

<td class="ajustcelda"> 

<?php if ($protec2 != "") { echo "$protec2 (+$armor2)"; } else { echo "Ninguno"; } ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Forma: </td>

<td class="ajustcelda"> 

<?php echo $forma1; ?>

</td>

<td class="ajustcelda"> Forma: </td>

<td class="ajustcelda"> 

<?php echo $forma2; ?>

</td>

</tr>

<tr><td colspan="4">&nbsp;</td></tr>

<tr>

<td class="ajustcelda"> Fuerza: </td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$fuerza1.png'/>"; ?>

</td>

<td class="ajustcelda"> Fuerza: </td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$fuerza2.png'/>"; ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Destreza: </td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$destre1.png'/>"; //if($malusArmor1 != 0) { echo "-$malusArmor1"; }?>

</td>

<td class="ajustcelda"> Destreza: </td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$destre2.png'/>"; //if($malusArmor2 != 0) { echo " -$malusArmor2"; }?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Resistencia: </td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$resist1.png'/>"; ?>

</td>


<td class="ajustcelda"> Resistencia: </td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$resist2.png'/>"; ?>

</td>

</tr>

<tr>

<?php 

// COMPROBAMOS QUE HABILIDAD ES LA ADECUADA

include ("sim_battle_habilidad_armas.php"); /* Archivo para comprobar la habilidad que usa el arma */

?>

<td class="ajustcelda"> 

<?php echo "$skillz1b";?>: 

</td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$suma1.png'/>"; ?>

</td>

<td class="ajustcelda">

<?php echo "$skillz2b";?>: 

</td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$suma2.png'/>"; ?>

</td>

</tr>

<?php } else { ?>

<tr>

<td class="ajustcelda"> Rabia: </td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$rabia1.png'/>"; ?>

</td>

<td class="ajustcelda"> Rabia: </td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$rabia2.png'/>"; ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Gnosis: </td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$gnosis1.png'/>"; ?>

</td>


<td class="ajustcelda"> Gnosis: </td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$gnosis2.png'/>"; ?>

</td>

</tr>

<tr>

<td class="ajustcelda"> Voluntad: </td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$fvp1.png'/>"; ?>

</td>


<td class="ajustcelda"> Voluntad: </td>

<td class="ajustcelda"> 

<?php echo"<img class='bioAttCircle' src='img/gem-pwr-0$fvp2.png'/>"; ?>

</td>

</tr>

<?php } ?>

<?php

$combateArray = array();

$combateArray["id1"] = $hid1;
$combateArray["stats1"] = "$fuerza1 , $destre1 , $resist1";
$combateArray["umbra1"] = "$rabia1, $gnosis1, $fvp1";

$combateArray["id2"] = $hid2;
$combateArray["stats2"] = "$fuerza2 , $destre2 , $resist2";
$combateArray["umbra2"] = "$rabia2, $gnosis2, $fvp2";

$combateArray["arma1"] = $arma1;
$combateArray["bonusarma1"] = $bonus1;
$combateArray["prot1"] = $protec1;
$combateArray["bonusprot1"] = $armor1;
$combateArray["malusprot1"] = $malusArmor1;
$combateArray["forma1"] = $forma1;

$combateArray["arma2"] = $arma2; 
$combateArray["bonusarma2"] = $bonus2;
$combateArray["prot2"] = $protec2;
$combateArray["bonusprot2"] = $armor2;
$combateArray["malusprot2"] = $malusArmor2;
$combateArray["forma2"] = $forma2;

$combateArray["skill1"] = $skillz1b;
$combateArray["skill1value"] = $suma1;

$combateArray["skill2"] = $skillz2b;
$combateArray["skill2value"] = $suma2;

$combateArray["tipocombate"] = $tipoCombate;

// CALCULAMOS TODAS LAS TIRADAS

$rnd1 = rand(1,10);
$rnd2 = rand(1,10);

// TIRADAS DE ATAQUE
if ($tipoCombate != "umbral") {

$iniciativa1 = $destre1+$astuci1+$rnd1;
$iniciativa2 = $destre2+$astuci2+$rnd2;


$destre1 = $destre1-$malusArmor1; // Malus de J1
if ($destre1 <= 0) { $destre1 = 1; }

$atacar1 = $destre1+$suma1;
$esquivar1 = $destre1+$esquivar1;

$defPower1 = $armor1+$resist1;

//////////////////////////////

$destre2 = $destre2-$malusArmor2; // Malus de J2
if ($destre2 <= 0) { $destre2 = 1; }

$atacar2 = $destre2+$suma2;
$esquivar2 = $destre2+$esquivar2;

$defPower2 = $armor2+$resist2;

echo "<tr><td colspan='4'>&nbsp;</td></tr>";

echo "<tr>";
	echo "<td class='ajustcelda'>Poder de Ataque:</td><td class='ajustcelda'><img class='bioAttCircle' src='img/gem-pwr-0$atacar1.png'/></td>"; //<br/>POWER: $atacar1<br/>(DEX: $destre1 | SKILL: $suma1)
	echo "<td class='ajustcelda'>Poder de Ataque:</td><td class='ajustcelda'><img class='bioAttCircle' src='img/gem-pwr-0$atacar2.png'/></td>"; //<br/>POWER: $atacar2<br/>(DEX: $destre2 | SKILL: $suma2)
echo "</tr>";
echo "<tr>";
	echo "<td class='ajustcelda'>Poder Defensivo:</td><td class='ajustcelda'><img class='bioAttCircle' src='img/gem-pwr-0$defPower1.png'/></td>"; //<br/>DEFENSE: $defPower1
	echo "<td class='ajustcelda'>Poder Defensivo:</td><td class='ajustcelda'><img class='bioAttCircle' src='img/gem-pwr-0$defPower2.png'/></td>"; //<br/>DEFENSE: $defPower2
echo "</tr>";

} elseif ($tipoCombate == "umbral") {

$iniciativa1 = $rnd1;
$iniciativa2 = $rnd2;

$atacar1 = $gnosis1;
$esquivar1 = 0;

$fuerza1 = $rabia1;
$resist1 = $fvp1;

$atacar2 = $gnosis2;
$esquivar2 = 0;

$fuerza2 = $rabia2;
$resist2 = $fvp2;

}

$hpact1 = $hp1;
$hpact2 = $hp2;

	$dexP1sinMalus = $destre1 + $malusArmor1;
	$dexP2sinMalus = $destre2 + $malusArmor2;	

echo "<tr>";
	echo "<td class='ajustcelda'>Iniciativa:</td><td class='ajustcelda'>$iniciativa1</td>"; 
	echo "<td class='ajustcelda'>Iniciativa:</td><td class='ajustcelda'>$iniciativa2</td>";
echo "</tr>";

echo "<tr>";
	echo "<td class='ajustcelda'>Puntos de salud:</td><td class='ajustcelda'>$hp1</td>"; 
	echo "<td class='ajustcelda'>Puntos de salud:</td><td class='ajustcelda'>$hp2</td>";
echo "</tr>";

	// Colocamos bien la destreza para mostrarla en la tirada de Iniciativa  ( $dexP1sinMalus + $astuci1 + $rnd1 )  ( $dexP2sinMalus + $astuci2 + $rnd2 )

	/*

	*/
	//$combateIniciativa = "
	//<div id='celdaInicioCombateIz'><p id='paIniCom'>Iniciativa: $iniciativa1<br/>($dexP1sinMalus + $astuci1 + $rnd1)</p><p id='paIniCom'>Puntos de salud: $hp1</p></div>
	//<div id='celdaInicioCombateDe'><p id='paIniCom'>Iniciativa: $iniciativa2<br/>($dexP2sinMalus + $astuci2 + $rnd2)</p><p id='paIniCom'>Puntos de salud: $hp2</p></div>
	//";
	
	//echo $combateIniciativa;

$combateArray["iniciativa"] = $combateIniciativa;

	$tiradasFalliJ1 = 0;
	$tiradasExitoJ1 = 0;
	$tiradasFalliJ2 = 0;
	$tiradasExitoJ2 = 0;

include ("sim_text_presentacion.php"); /* Archivo de presentación aleatoria */

echo "<tr><td colspan='4' class=''><p id='fraseFinalCombate'>";

echo $quote[$echo[1]];

$combateArray["fraseinicio"] = $quote[$echo[1]];

echo "</p></td></tr>";

$combateVida = "$nombre1 tiene $hp1 <b>Puntos de Vida</b>. <br/> $nombre2 tiene $hp2 <b>Puntos de Vida</b>.";
$combateArray["vitalidades"] = $combateVida;

//TURNOS

include ("sim_battle_turnos.php"); /* Archivo de los turnos de combate */

if ($hpact1 <= 0) { $hpact1 = 0; }
if ($hpact2 <= 0) { $hpact2 = 0; }

if ($hpact1 < $hpact2) {

	$ganador = "¡$nombre2 gana la pelea!";
	$wina = $nombreCom2;
	$winaID = $idPJno2;
	$losa = $nombreCom1;
	$losaID = $idPJno1;

} elseif ($hpact1 > $hpact2) {

	$ganador = "¡$nombre1 derrota a su rival!";
	$wina = $nombreCom1;;
	$winaID = $idPJno1;
	$losa = $nombreCom2;
	$losaID = $idPJno2;

} else {

	$ganador = "El combate termina en empate.";
	$drau = 1;

}

$heridasFinales = "Vitalidad de $nombre1: $hpact1 ($hp1 - $heridas1) · Vitalidad de $nombre2: $hpact2 ($hp2 - $heridas2)";

$combateArray["ganador"] = $ganador; 			/* Insertamos el ganador */
$combateArray["heridas"] = $heridasFinales; 	/* Insertamos el ganador */

include ("sim_battle_finalizar_combate.php"); 	/* Archivo para insertar los resultados en la BDD */ 

echo "<tr><td colspan='4' class=''>";
///////////////////
echo "<p id='fraseFinalCombate' style='font-size:14px;'>$ganador</p>";
/////////////////// <img class='photobio' src='$img1' title='$nombreCom1'>
/* 	$tiradasFalliJ1 = 0;
	$tiradasExitoJ1 = 0;
	$tiradasFalliJ2 = 0;
	$tiradasExitoJ2 = 0; */
echo "<div id='celdaFinCombateIz'>";
	echo"<p id='paIniCom'>
		Salud restante: $hpact1 ~ ($hp1 - $heridas1)
		<br/>Aciertos: $tiradasExitoJ1 ~ Fallos: $tiradasFalliJ1
	</p>";
	echo"<div id='renglonImgFin'><img id='imagenFinComb' src='$img1' title='$nombreCom1'/></div>";
echo "</div>";
///////////////////
echo "<div id='celdaFinCombateDe'>";
	echo"<p id='paIniCom'>
		Salud restante: $hpact2 ~ ($hp2 - $heridas2)
		<br/>Aciertos: $tiradasExitoJ2 ~ Fallos: $tiradasFalliJ2
	</p>";
	echo"<div id='renglonImgFin'><img id='imagenFinComb' src='$img2' title='$nombreCom2'/></div>";
echo "</div>";
///////////////////
echo "</td></tr></table>";

echo "<p style='text-align:center;'><a href='index.php?p=simulador' title='Simulador de Combate'>Volver</a></p>";

} } else {

	echo "<center>Has utilizado el simulador demasiado pronto. Espera unos minutos.<br/><br/>
	Hora actual: <b>$kualki</b> <br/> 
	Podr&aacute;s echar otro a partir de las: <b>$limiteg</b> <br/><br/>
	<input class='boton1' type='button' onclick='history.go(-1)' value='Volver'/></center>";
}  

?>
