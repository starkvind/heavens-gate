<?php

include("sim_battle_check_heridas.php");

if ($debug == 'si') { echo "<br/>
	<fieldset class='tirada'>
	<legend class='tirada'>ATAQUE de <b>$nombre1</b></legend>"; }

// TIRADA ATAQUE (DESTREZA + HAB. COMBATE)

$tiradas = 0;
$exitos = 0;
$pifias = 0;
$dados = $destre1+$suma1-$heridas1a;

		if ($dados < 0) { $dados = 0; }

	if ($debug == 'si') { echo "
	<fieldset class='tirada'>
	<legend class='tirada'>
	DESTREZA [$destre1] + HABILIDAD DE COMBATE [$suma1] - HERIDAS [$heridas1a] ($estato1) <br/>
	$nombre1 tira: <b><u>$dados</u></b> dados</legend>
	<b>Resultado</b>:"; }

for ($tiradas=1;$tiradas<=$dados;$tiradas++) {
	
	$resultados[$tiradas] = rand(0,9);
	if ($debug == 'si') { echo "&nbsp;";
	echo $resultados[$tiradas]; }
	if (ereg('[1]', $resultados[$tiradas])) {
	$pifias = $pifias+1;
	}
	if (ereg('[67890]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

}
	if ($debug == 'si') { echo "</fieldset>"; }

	$total_a = $exitos-$pifias;
	if ($total_a < 0) { $total_a = 0; }
	if ($debug == 'si') { echo "<br/>Pifias: $pifias<br/>";
	echo "&Eacute;xitos: $exitos<br/>";
	echo "Total Ataque: $total_a <br/><br/>"; }
	
// TIRADA ESQUIVA (DESTREZA + ESQUIVA)

$tiradas = 0;
$exitos = 0;
$pifias = 0;
$dados = $esquivar2-$heridas2a;

		if ($dados < 0) { $dados = 0; }

	if ($debug == 'si') { echo "
	<fieldset class='tirada'>
	<legend class='tirada'>
	DESTREZA [$destre2] + ESQUIVAR [$esquivar22] - HERIDAS [$heridas2a] ($estato2) <br/>
	$nombre2 tira: <b><u>$dados</u></b> dados</legend>
	<b>Resultado</b>:"; }

for ($tiradas=1;$tiradas<=$dados;$tiradas++) {
	
	$resultados[$tiradas] = rand(0,9);
	if ($debug == 'si') { echo "&nbsp;";
	echo $resultados[$tiradas]; }
	if (ereg('[1]', $resultados[$tiradas])) {
	$pifias = $pifias+1;
	}
	if (ereg('[67890]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

}

	if ($debug == 'si') { echo "</fieldset>"; }

	$total_b = $exitos-$pifias;
	if ($total_b < 0) { $total_b = 0; }
	if ($debug == 'si') { echo "<br/>Pifias: $pifias<br/>";
	echo "&Eacute;xitos: $exitos<br/>";
	echo "Total Esquiva: $total_b <br/><br/>"; }
	
// RESTAMOS DADOS A LA ESQUIVA (ATAQUE - ESQUIVA)

	$total_c = $total_a-$total_b;
	if ($total_c < 0) { $total_c = 0; }
	if ($debug == 'si') { echo "
	<fieldset class='tirada'>
	<legend class='tirada'>ATAQUE - ESQUIVA</legend>";
	echo "Ataque: <b>$total_a</b> - Esquiva: <b>$total_b</b><br/>"; 
	echo "Suma al ataque: $total_c";
	echo "</fieldset><br/>"; }
	
	if ($total_c == 0) { $atq1 = 0; } else {
	
	// TIRADA DAÑO (FUERZA + ARMA + ATAQUE)
	
	$tiradas = 0;
	$exitos = 0;
	$pifias = 0;

	/* CHEKEAMOS QUE CLASE DE ARMA ES Y MONTAMOS LOS DADOS */ 

	if (($skillz1a == "armasdefuego") OR ($skillz1a == "atletismo")) { 

	$dados = $bonus1+$total_c; 

	if ($debug == 'si') { echo "
	<fieldset class='tirada'>
	<legend class='tirada'>
	ARMA [$bonus1] + ATAQUE [$total_c]<br/>
	$nombre1 tira: <b><u>$dados</u></b> dados</legend>
	<b>Resultado</b>:"; }

	} else { 

	$dados = $fuerza1+$bonus1+$total_c; 

	if ($debug == 'si') { echo "
	<fieldset class='tirada'>
	<legend class='tirada'>
	FUERZA [$fuerza1] + ARMA [$bonus1] + ATAQUE [$total_c] <br/>
	$nombre1 tira: <b><u>$dados</u></b> dados</legend>
	<b>Resultado</b>:"; }

	}

for ($tiradas=1;$tiradas<=$dados;$tiradas++) {
	
	$resultados[$tiradas] = rand(0,9);
	if ($debug == 'si') { echo "&nbsp;";
	echo $resultados[$tiradas]; }
	if (ereg('[1]', $resultados[$tiradas])) {
	$pifias = $pifias+1;
	}
	if (ereg('[67890]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

}

	if ($debug == 'si') { echo "</fieldset>"; }

	$total_d = $exitos-$pifias;
	if ($total_d < 0) { $total_d = 0; }
	if ($debug == 'si') { echo "<br/>Pifias: $pifias<br/>";
	echo "&Eacute;xitos: $exitos<br/>";
	echo "Total Ataque: $total_d <br/><br/>"; }

	/* SE CHEQUEA SI EL ARMA HACE DAÑO AGRAVADO */
	
	if ($danger1 != "1") {	

	$resist2porn = $resist2;

	} else {

	$resist2porn = 0;

	}

// RESISTENCIA + ARMADURA

	$tiradas = 0;
	$exitos = 0;
	$pifias = 0;
	$dados = $resist2porn+$armor2;

	if ($debug == 'si') { echo "
	<fieldset class='tirada'>
	<legend class='tirada'>
	RESISTENCIA [$resist2porn$aggr2] + ARMADURA [$armor2]
	<br/>$nombre2 tira: <b><u>$dados</u></b> dados</legend>
	<b>Resultado</b>:"; }
	for ($tiradas=1;$tiradas<=$dados;$tiradas++) {
	
	$resultados[$tiradas] = rand(0,9);
	if ($debug == 'si') { echo "&nbsp;";
	echo $resultados[$tiradas]; }
	if (ereg('[1]', $resultados[$tiradas])) {
	$pifias = $pifias+1;
	}
	if (ereg('[67890]', $resultados[$tiradas])) {
	$exitos = $exitos+1;
	}

}

	if ($debug == 'si') { echo "</fieldset>"; }

	$total_e = $exitos-$pifias;
	if ($total_e < 0) { $total_e = 0; }
	if ($debug == 'si') { echo "<br/>Pifias: $pifias<br/>";
	echo "&Eacute;xitos: $exitos<br/>";
	echo "Total Defensa: $total_e <br/><br/>"; }


// ATAQUE - DEFENSA
	
	$dano = $total_d-$total_e;
	if ($dano < 0) { $dano = 0; }

	if ($debug == 'si') { echo "
	<fieldset class='tirada'>
	<legend class='tirada'>ATAQUE - DEFENSA</legend>";
	echo "Ataque: <b>$total_d</b> - Defensa: <b>$total_e</b><br/>"; }

	/* PROVOCAMOS HERIDAS EROTICAS */

	$heridas2 = $heridas2+$dano;

	$heridasm = $heridas2;
	
	if ($heridasm < 0) { $heridasm = 0; }
	
	if ($debug == 'si') { echo "Da&ntilde;o total: $dano<br/>Heridas de $nombre2: $heridasm</fieldset><br/>"; }
	$atq1 = $dano;

}

if ($debug == 'si') { echo "</fieldset><br/>"; }


?>