<?php
	switch($skillz1a) {
		case "pelea":
			$typeOfSkillToPick = 1;
			break;
		case "armascc":
			$typeOfSkillToPick = 2;
			break;
		case "armasdefuego":
			$typeOfSkillToPick = 3;
			break;
		case "atletismo":
			$typeOfSkillToPick = 4;
			break;
	}
	include ("sim_text_ataques.php"); /* Archivo con las frases de ataques y fallos */

	if ($arma1 != "") { $pj1FraseAtaque = "$nombre1 $atak con $arma1"; } else { $pj1FraseAtaque = "$nombre1 $atak"; }
	
	echo "<div id='celdaAtaqueJ1'>$pj1FraseAtaque";
	
	include ("sim_battle_tirada_j1.php"); /* Datos extensos sobre la tirada del PJ 1*/

	if ($atq1 <= 0) { $atq1 = 0; }
	$dano2 = $atq1;
	
	if ($dano2 == 0) { 
		$pj1ResultadoAtaque = ", pero $fayl.";//"$nombre1 $fayl.";
		$tiradasFalliJ1 = $tiradasFalliJ1 + 1;
	} else { 
		$pj1ResultadoAtaque = " y causa <b>$dano2 puntos de da&ntilde;o</b>.";
		$tiradasExitoJ1 = $tiradasExitoJ1 + 1;
	}
	echo "$pj1ResultadoAtaque</div>"; 
		
	$hpact2 = $hpact2-$dano2;
	
?>