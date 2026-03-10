<?php

$nousesarmas1 = isset($nousesarmas1) ? (int)$nousesarmas1 : 0;
$nousesarmas2 = isset($nousesarmas2) ? (int)$nousesarmas2 : 0;

$usaArmasForma1 = 1;
$usaArmasFuegoForma1 = 1;
$bonoFormaFuerza1 = 0;
$bonoFormaDestreza1 = 0;
$bonoFormaResistencia1 = 0;
$regenYesOrNotPj1 = 0;
$cantidadRegenPj1 = 0;

$usaArmasForma2 = 1;
$usaArmasFuegoForma2 = 1;
$bonoFormaFuerza2 = 0;
$bonoFormaDestreza2 = 0;
$bonoFormaResistencia2 = 0;
$regenYesOrNotPj2 = 0;
$cantidadRegenPj2 = 0;

if ($forma1 != "Hominido") { // SI ES HOMINIDO, PASAMOS DEL TEMA
// COMPROBAMOS LA FORMA DEL PRIMER JUGADOR Y APLICAMOS LOS ATRIBUTOS
$consultaFormaPJ1 = "SELECT armas,armasfuego,bonfue,bondes,bonres,regenera,hpregen FROM vw_sim_forms WHERE forma LIKE '$forma1';";
$queryFormaPJ1 = mysql_query ($consultaFormaPJ1, $link);
$rowsFormaPJ1 = mysql_num_rows ($queryFormaPJ1);
if ($rowsFormaPJ1 != 0) {
	$resultadoFormaPJ1 = mysql_fetch_array($queryFormaPJ1);
	// USO DE ARMAS POR LA FORMA
	$usaArmasForma1			= $resultadoFormaPJ1["armas"];
	$usaArmasFuegoForma1 	= $resultadoFormaPJ1["armasfuego"];
	// BONOS DE FORMA
	$bonoFormaFuerza1 		= $resultadoFormaPJ1["bonfue"];
	$bonoFormaDestreza1 	= $resultadoFormaPJ1["bondes"];
	$bonoFormaResistencia1 	= $resultadoFormaPJ1["bonres"];
	// REGENERACION
	$regenYesOrNotPj1 = $resultadoFormaPJ1["regenera"]; // VARIABLES NECESARIAS PARA EL ARCHIVO DE REGENERACION
	$cantidadRegenPj1 = $resultadoFormaPJ1["hpregen"];	// VARIABLES NECESARIAS PARA EL ARCHIVO DE REGENERACION
}
	// APLICAMOS BONOS
	$fuerza1 = $fuerza1+$bonoFormaFuerza1;
	$destre1 = $destre1+$bonoFormaDestreza1;
	$resist1 = $resist1+$bonoFormaResistencia1;
	
	if ($fuerza1 < 0) { $fuerza1 = 0; }
	if ($destre1 < 0) { $destre1 = 0; }
	if ($resist1 < 0) { $resist1 = 0; }
	
	if ($usaArmasForma1 == 0) {
		$nousesarmas1 = 1;
		//echo "$nombre1, eres un $forma1, asi que no usas armas.<br/>";
	} elseif ($usaArmasFuegoForma1 == 0 AND ($skillCheck4Form1 == "Armas de Fuego" OR $skillCheck4Form1 == "Armas a Distancia")) {
		$nousesarmas1 = 1;
		//echo "$nombre1, eres un $forma1, asi que no usas armas de fuego.<br/>";
	} else {
		$nousesarmas1 = 0;
		//echo "$nombre1, eres un $forma1, asi que usas armas.<br/>";
	}

	if ($nousesarmas1 == 1) {
	/* LOS CRINOS Y LOS PECES DEL AMOR NO USAN ARMAS */
		$arma1 = '';
		$bonus1 = 0;
		$danyo1 = 0;
		$plata1 = 0;
	}
}

if ($forma2 != "Hominido") { // SI ES HOMINIDO, PASAMOS DEL TEMA
// COMPROBAMOS LA FORMA DEL SEGUNDO JUGADOR Y APLICAMOS LOS ATRIBUTOS
$consultaFormaPJ2 = "SELECT armas,armasfuego,bonfue,bondes,bonres,regenera,hpregen FROM vw_sim_forms WHERE forma LIKE '$forma2';";
$queryFormaPJ2 = mysql_query ($consultaFormaPJ2, $link);
$rowsFormaPJ2 = mysql_num_rows ($queryFormaPJ2);
if ($rowsFormaPJ2 != 0) {
	$resultadoFormaPJ2 = mysql_fetch_array($queryFormaPJ2);
	// USO DE ARMAS POR LA FORMA
	$usaArmasForma2			= $resultadoFormaPJ2["armas"];
	$usaArmasFuegoForma2 	= $resultadoFormaPJ2["armasfuego"];
	// BONOS DE FORMA
	$bonoFormaFuerza2 		= $resultadoFormaPJ2["bonfue"];
	$bonoFormaDestreza2 	= $resultadoFormaPJ2["bondes"];
	$bonoFormaResistencia2 	= $resultadoFormaPJ2["bonres"];
	// REGENERACION
	$regenYesOrNotPj2 = $resultadoFormaPJ2["regenera"]; // VARIABLES NECESARIAS PARA EL ARCHIVO DE REGENERACION
	$cantidadRegenPj2 = $resultadoFormaPJ2["hpregen"];	// VARIABLES NECESARIAS PARA EL ARCHIVO DE REGENERACION
}
	// APLICAMOS BONOS
	$fuerza2 = $fuerza2+$bonoFormaFuerza2;
	$destre2 = $destre2+$bonoFormaDestreza2;
	$resist2 = $resist2+$bonoFormaResistencia2;
	
	if ($fuerza2 < 0) { $fuerza2 = 0; }
	if ($destre2 < 0) { $destre2 = 0; }
	if ($resist2 < 0) { $resist2 = 0; }
	
	if ($usaArmasForma2 == 0) {
		$nousesarmas2 = 1;
		//echo "$nombre2, eres un $forma2, asi que no usas armas.<br/>";
	} elseif ($usaArmasFuegoForma2 == 0 AND ($skillCheck4Form2 == "Armas de Fuego" OR $skillCheck4Form2 == "Armas a Distancia")) {
		$nousesarmas2 = 1;
		//echo "$nombre2, eres un $forma2, asi que no usas armas de fuego.<br/>";
	} else {
		$nousesarmas2 = 0;
		//echo "$nombre2, eres un $forma2, asi que usas armas.<br/>";
	}

	if ($nousesarmas2 == 1) {
	/* LOS CRINOS Y LOS PECES DEL AMOR NO USAN ARMAS */
	$arma2 = '';
	$bonus2 = 0;
	$danyo2 = 0;
	$plata2 = 0;
	}
}

$formadanger= "Hominido";
/* CHEKEAMOS QUE SE COMAN EL AGRAVADO COMO UNOS CAMPEONES */
/* PRIMERO EL HEROE */
if ($forma1 == "$formadanger" AND $danyo2 == "Agravado") {
	$danger2 = 1;
} else { 
	$danger2 = 0; 
}

/* AHORA EL VILLANO OJO PUTA */

if ($forma2 == "$formadanger" AND $danyo1 == "Agravado") {
	$danger1 = 1;
} else { 
	$danger1 = 0; 
}

/* VAMOS A VER SI TENEMOS PLATA */

if ($forma1 != "$formadanger" AND ($plata2 == "on" OR $plata2 == 1)) {
	if (($fera1 != "Córax") OR ($fera1 != "Mokolé") OR ($fera1 != "Ananasi")) {
		$danger2 = 1;
		$bonusplata1 = 1;
	}

}

if ($forma2 != "$formadanger" AND ($plata1 == "on" OR $plata1 == 1)) {
	if (($fera2 != "Córax") OR ($fera2 != "Mokolé") OR ($fera2 != "Ananasi")) {
		$danger1 = 1;
		$bonusplata2 = 1;
	}
}

?>
