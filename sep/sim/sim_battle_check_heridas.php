<?php

/* HERIDAS PERSONAJE 1 */
if ($heridas1 <= 1) { 

/* OK Y MAGULLADO */
	$heridas1a = 0;
	$estato1 = "<i>OK</i>";

} elseif (($heridas1 >= 2) AND ($heridas1 <= 3)) {

	/* LASTIMADO Y LESIONADO */
	$heridas1a = 1;
	$estato1 = "<i>Lesionado</i>";

} elseif (($heridas1 >= 4) AND ($heridas1 <= 5)) {

	/* HERIDO Y MALHERIDO */
	$heridas1a = 2;
	$estato1 = "<i>Malherido</i>";

} elseif ($heridas1 >= 6) {

	/* TULLIDO EN ADELANTE */
	$heridas1a = 5;
	$estato1 = "<i>Tullido</i>";

}

/* HERIDAS PERSONAJE 2 */
if ($heridas2 <= 1) { 

/* OK Y MAGULLADO */
	$heridas2a = 0;
	$estato2 = "<i>OK</i>";

} elseif (($heridas2 >= 2) AND ($heridas2 <= 3)) {

	/* LASTIMADO Y LESIONADO */
	$heridas2a = 1;
	$estato2 = "<i>Lesionado</i>";

} elseif (($heridas2 >= 4) AND ($heridas2 <= 5)) {

	/* HERIDO Y MALHERIDO */
	$heridas2a = 2;
	$estato2 = "<i>Malherido</i>";

} elseif ($heridas2 >= 6) {

	/* TULLIDO EN ADELANTE */
	$heridas2a = 5;
	$estato2 = "<i>Tullido</i>";

}
/* SI HEMOS QUITADO LAS HERIDAS EN LAS OPCIONES */
if ($aplicarHeridas == "no") {
	$heridas1a = 0;
	$heridas2a = 0;
}

?>