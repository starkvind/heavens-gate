<?php

// PILLAMOS LAS VARIABLES MEGAMOLONAS

	$nombre = "$_POST[nombre]";
	$dados = "$_POST[dados]";
	$dificultad = "$_POST[dificultad]";

if ($nombre == "") {

header("Location: ../../index.php?p=dados"); /* Retrocedemos DOS niveles */

} else {

// HACEMOS LA TIRADA, TRAIDA DIRECTAMENTE EL MEGASIMULADOR :D 

	$tiradas = 0;
	$ouch = 0;
	$exitos = 0;
	$pifias = 0;

	$frase1 = "<br/>$nombre debe tirar: <b><u>$dados</u></b> dado(s) a dificultad <b><u>$dificultad</u></b><br/><b>Resultado</b>: ";

	for ($tiradas=1;$tiradas<=$dados;$tiradas++,$ouch++) {
	$resultados[$tiradas] = rand(0,9);
	$frase2[$ouch] = $resultados[$tiradas];
	if (ereg('[1]', $resultados[$tiradas])) {
	$pifias = $pifias+1;
	}

// CHEKEAMOS LA DIFICULTAD MOLONA

if ($dificultad == "2") { 

	if (ereg('[234567890]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

} elseif ($dificultad == "3") {

	if (ereg('[34567890]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

} elseif ($dificultad == "4") {

	if (ereg('[4567890]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

} elseif ($dificultad == "5") {

	if (ereg('[567890]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

} elseif ($dificultad == "6") {

	if (ereg('[67890]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

} elseif ($dificultad == "7") {

	if (ereg('[7890]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

} elseif ($dificultad == "8") {

	if (ereg('[890]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

} elseif ($dificultad == "9") {

	if (ereg('[90]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

} else {

	if (ereg('[0]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

}

}
	$total_a = $exitos-$pifias;
	
	$frase3 = "<br/><br/>Pifias: $pifias<br/>&Eacute;xitos: $exitos<br/>Resultado Final: $total_a <br/><br/>";


	if ($total_a < 0) {

	$frase4 = "¡$nombre intenta realizar su acci&oacute;n, pero fracasa miserablemente!";

	} elseif ($total_a == "0") {

	$frase4 = "El intento de $nombre ha fallado...";

	} elseif ($total_a == "1") {
	
	$frase4 = "¡¡Dios!! ¡$nombre lo consigue por los pelos!";

	} elseif ($total_a == "2") {

	$frase4 = "$nombre obtiene un resultado aceptable.";

	} else {

	$frase4 = "¡$nombre lo ha hecho genial!";

	}

	$fecha = date("H:i:s, d-m-Y");

// Y AQUI GUARDAMOS LAS MOVIDAS

include("../heroes.php"); /* Incluimos los datos de la BDD - IMPORTANTE - */

mysql_select_db("$bdd", $link);

// VAYA LATA PARA QUE QUEDE BIEN LOS RESULTADOS DE LAS TIRADAS xDDDDD

if ($dados =="1") { $frase2 = $frase2[0]; } 
elseif ($dados =="2") { $frase2 = "$frase2[0] $frase2[1]"; }
elseif ($dados =="3") { $frase2 = "$frase2[0] $frase2[1] $frase2[2]"; }
elseif ($dados =="4") { $frase2 = "$frase2[0] $frase2[1] $frase2[2] $frase2[3]"; }
elseif ($dados =="5") { $frase2 = "$frase2[0] $frase2[1] $frase2[2] $frase2[3] $frase2[4]"; }
elseif ($dados =="6") { $frase2 = "$frase2[0] $frase2[1] $frase2[2] $frase2[3] $frase2[4] $frase2[5]"; }
elseif ($dados =="7") { $frase2 = "$frase2[0] $frase2[1] $frase2[2] $frase2[3] $frase2[4] $frase2[5] $frase2[6]"; }
elseif ($dados =="8") { $frase2 = "$frase2[0] $frase2[1] $frase2[2] $frase2[3] $frase2[4] $frase2[5] $frase2[6] $frase2[7]"; }
elseif ($dados =="9") { $frase2 = "$frase2[0] $frase2[1] $frase2[2] $frase2[3] $frase2[4] $frase2[5] $frase2[6] $frase2[7] $frase2[8]"; }
elseif ($dados =="10") { $frase2 = "$frase2[0] $frase2[1] $frase2[2] $frase2[3] $frase2[4] $frase2[5] $frase2[6] $frase2[7] $frase2[8] $frase2[9]"; }
elseif ($dados =="11") { $frase2 = "$frase2[0] $frase2[1] $frase2[2] $frase2[3] $frase2[4] $frase2[5] $frase2[6] $frase2[7] $frase2[8] $frase2[9] $frase2[10]"; } 
elseif ($dados =="12") { $frase2 = "$frase2[0] $frase2[1] $frase2[2] $frase2[3] $frase2[4] $frase2[5] $frase2[6] $frase2[7] $frase2[8] $frase2[9] $frase2[10] $frase2[11]"; } 
elseif ($dados =="13") { $frase2 = "$frase2[0] $frase2[1] $frase2[2] $frase2[3] $frase2[4] $frase2[5] $frase2[6] $frase2[7] $frase2[8] $frase2[9] $frase2[10] $frase2[11] $frase2[12]"; } 
elseif ($dados =="14") { $frase2 = "$frase2[0] $frase2[1] $frase2[2] $frase2[3] $frase2[4] $frase2[5] $frase2[6] $frase2[7] $frase2[8] $frase2[9] $frase2[10] $frase2[11] $frase2[12] $frase2[13]"; } 
elseif ($dados =="15") { $frase2 = "$frase2[0] $frase2[1] $frase2[2] $frase2[3] $frase2[4] $frase2[5] $frase2[6] $frase2[7] $frase2[8] $frase2[9] $frase2[10] $frase2[11] $frase2[12] $frase2[13] $frase2[14]"; } 

mysql_query("insert into tiradax (fecha,frase1,frase2,frase3,frase4) values ('$fecha', '$frase1', '$frase2', '$frase3', '$frase4')",$link);

header("Location: ../../index.php?p=dados"); /* Retrocedemos DOS niveles */

}


?>

