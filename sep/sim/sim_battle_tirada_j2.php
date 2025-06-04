<?php 

include("sim_battle_check_heridas.php");

if ($debug == 'si') { echo "<br/>
	<fieldset class='tirada'>
	<legend class='tirada'>ATAQUE de <b>$nombre2</b></legend>"; }

// TIRADA ATAQUE (DESTREZA + HAB. COMBATE)

$tiradas = 0;
$exitos = 0;
$pifias = 0;
$dados = $destre2+$suma2-$heridas2a;

		if ($dados < 0) { $dados = 0; }

	if ($debug == 'si') { echo "
	<fieldset class='tirada'>
	<legend class='tirada'>
	DESTREZA [$destre2] + HABILIDAD DE COMBATE [$suma2] - HERIDAS [$heridas2a] ($estato2) 
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

	$total_a = $exitos-$pifias;
	if ($total_a < 0) { $total_a = 0; }
	if ($debug == 'si') { echo "<br/>Pifias: $pifias<br/>";
	echo "&Eacute;xitos: $exitos<br/>";
	echo "Total Ataque: $total_a <br/><br/>"; }
	
// TIRADA ESQUIVA (DESTREZA + ESQUIVA)

$tiradas = 0;
$exitos = 0;
$pifias = 0;
$dados = $esquivar1-$heridas1a;

		if ($dados < 0) { $dados = 0; }

	if ($debug == 'si') { echo "
	<fieldset class='tirada'>
	<legend class='tirada'>
	DESTREZA [$destre1] + ESQUIVAR [$esquivar12] - HERIDAS [$heridas1a] ($estato1) 
	<br/>$nombre1 tira: <b><u>$dados</u></b> dados</legend>
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
	if ($debug == 'si') { echo 
	"<fieldset class='tirada'>
	<legend class='tirada'>ATAQUE - ESQUIVA</legend>";
	echo "Ataque: <b>$total_a</b> - Esquiva: <b>$total_b</b><br/>"; 
	echo "Suma al ataque: $total_c";
	echo "</fieldset><br/>"; }
	
	if ($total_c == 0) { $atq2 = 0; } else {
	
	// TIRADA DAÑO (FUERZA + ARMA + ATAQUE)
	
	$tiradas = 0;
	$exitos = 0;
	$pifias = 0;

	/* CHEKEAMOS QUE CLASE DE ARMA ES Y MONTAMOS LOS DADOS */ 

	if (($skillz2a == "armasdefuego") OR ($skillz2a == "atletismo")) { 

	$dados = $bonus2+$total_c; 

	if ($debug == 'si') { echo "
	<fieldset class='tirada'>
	<legend class='tirada'>
	ARMA [$bonus2] + ATAQUE [$total_c]<br/>
	$nombre2 tira: <b><u>$dados</u></b> dados</legend>
	<b>Resultado</b>:"; }

	} else { 

	$dados = $fuerza2+$bonus2+$total_c; 

	if ($debug == 'si') { echo "
	<fieldset class='tirada'>
	<legend class='tirada'>
	FUERZA [$fuerza2] + ARMA [$bonus2] + ATAQUE [$total_c]<br/>
	$nombre2 tira: <b><u>$dados</u></b> dados</legend>
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


	/* CHEKEAMOS SI EL ARMA HACE DAÑO AGRAVADO COMO ESCUCHAR REGGETON */

	if ($danger2 != "1") {	

	$resist1porn = $resist1;

	} else {

	$resist1porn = 0;

	}	
	
// RESISTENCIA + ARMADURA

	$tiradas = 0;
	$exitos = 0;
	$pifias = 0;
	$dados = $resist1porn+$armor1;

	if ($debug == 'si') { echo "
	<fieldset class='tirada'>
	<legend class='tirada'>
	RESISTENCIA [$resist1porn$aggr1] + ARMADURA [$armor1] 
	<br/>$nombre1 tira: <b><u>$dados</u></b> dados</legend>
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

	$heridas1 = $heridas1+$dano;

	$heridasm = $heridas1;
	
	if ($heridasm < 0) { $heridasm = 0; }
	
	if ($debug == 'si') { echo "Da&ntilde;o total: $dano<br/>Heridas de $nombre1: $heridasm</fieldset><br>"; }
	$atq2 = $dano;

}

if ($debug == 'si') { echo "</fieldset><br/>"; }


?>