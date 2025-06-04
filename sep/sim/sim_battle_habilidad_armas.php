<?php

/* PILLAMOS LAS ARMAS Y MIRAMOS QUE HABILIDAD USAN */

if ($nousesarmas1 != 1) {

	$consulta ="SELECT * FROM nuevo3_objetos WHERE name LIKE '$arma1';";
	$query = mysql_query ($consulta, $link);

	$yeah = mysql_fetch_array($query);

	$skillz1 = $yeah["habilidad"];
	$idarma1 = $yeah["id"];

	if ($skillz1 != "") {

		switch($skillz1) {
			case "Cuerpo a Cuerpo":
				$skillz1a = "armascc";
				break;
			case "Tiro con Arco":
			case "Arrojar":
			case "Atletismo":
				$skillz1a = "atletismo";
				break;
			case "Armas de Fuego":
				$skillz1a = "armasdefuego";
				break;
			case "Informática":
				$skillz1a = "informatica";
				break;
			default: 
				$skillz1a = "pelea";
				break;
		}

	} else {

		$skillz1a = "pelea";

	}

} else {

	$skillz1a = "pelea";

}

if ($nousesarmas2 != 1) {

$consulta ="SELECT * FROM nuevo3_objetos WHERE name LIKE '$arma2';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$skillz2 = $yeah["habilidad"];
$idarma2 = $yeah["id"];

if ($skillz2 != "") {

switch($skillz2) {
			case "Cuerpo a Cuerpo":
				$skillz2a = "armascc";
				break;
			case "Tiro con Arco":
			case "Arrojar":
			case "Atletismo":
				$skillz2a = "atletismo";
				break;
			case "Armas de Fuego":
				$skillz2a = "armasdefuego";
				break;
			case "Informática":
				$skillz2a = "informatica";
				break;
			default: 
				$skillz2a = "pelea";
				break;


}

} else {

$skillz2a = "pelea";

}

} else {

$skillz2a = "pelea";

}

/* COGEMOS A LOS DUEÑOS DE LAS ARMAS PARA DARLES LA HABILIDAD QUE TIENEN QUE USAR */

//$consulta ="SELECT $skillz1a FROM pjs1 WHERE nombre LIKE '$nombre1';";
$consulta ="SELECT $skillz1a FROM pjs1 WHERE id LIKE '$idPJno1';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$suma1 = $yeah[0];

//$consulta ="SELECT $skillz2a FROM pjs1 WHERE nombre LIKE '$nombre2';";
$consulta ="SELECT $skillz2a FROM pjs1 WHERE id LIKE '$idPJno2';";
$query = mysql_query ($consulta, $link);

$yeah = mysql_fetch_array($query);

$suma2 = $yeah[0];

/* AHORA SE CAMBIA EL NOMBRE PARA QUE QUEDE GUAY Y ESO */

switch($skillz1a) {
	case "armascc":
		$skillz1b = "Armas C.C";
		break;
	case "atletismo":
		$skillz1b = "Atletismo";
		break;
	case "armasdefuego":
		$skillz1b = "Armas de Fuego";
		break;
	case "informatica":
		$skillz1b = "Informática";
		break;
	case "pelea":
		$skillz1b = "Pelea";
		break;
}

switch($skillz2a) {
	case "armascc":
		$skillz2b = "Armas C.C";
		break;
	case "atletismo":
		$skillz2b = "Atletismo";
		break;
	case "armasdefuego":
		$skillz2b = "Armas de Fuego";
		break;
	case "informatica":
		$skillz2b = "Informática";
		break;
	case "pelea":
		$skillz2b = "Pelea";
		break;
}

?>