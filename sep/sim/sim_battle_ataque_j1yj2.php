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
		
	switch($skillz2a) {
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

	if ($arma2 != "") { $pj2FraseAtaque = "$nombre2 $atak con $arma2"; } else { $pj2FraseAtaque = "$nombre2 $atak"; }
	
	include ("sim_battle_tirada_j1.php"); /* Datos extensos sobre la tirada del PJ 1*/

	include ("sim_battle_tirada_j2.php"); /* Datos extensos sobre la tirada del PJ 2*/

	if ($atq1 <= 0) { $atq1 = 0; }
	if ($atq2 <= 0) { $atq2 = 0; }
	$dano2 = $atq1;
	$dano1 = $atq2;
	
	if ($dano2 == 0) { 
		$pj1ResultadoAtaque = ", pero $fayl.";
		$tiradasFalliJ1 = $tiradasFalliJ1 + 1;
	} else { 
		$pj1ResultadoAtaque = " y causa <b>$dano2 puntos de da&ntilde;o</b>.";
		$tiradasExitoJ1 = $tiradasExitoJ1 + 1;
	}
	
	if ($dano1 == 0) { 
		$pj2ResultadoAtaque = ", pero $fayl.";
		$tiradasFalliJ2 = $tiradasFalliJ2 + 1;
	} else { 
		$pj2ResultadoAtaque = " y causa <b>$dano1 puntos de da&ntilde;o</b>.";
		$tiradasExitoJ2 = $tiradasExitoJ2 + 1;
	}
		
	echo "<div id='celdaAtaqueJ1'>$pj1FraseAtaque$pj1ResultadoAtaque</div>";
	echo "<div id='celdaAtaqueJ2'>$pj2FraseAtaque$pj2ResultadoAtaque</div>";
	/* Guardamos datos del turno en ARRAY */
	$combateArray["turnoAtacante$turnos"] = "$pj1FraseAtaque<br/>$pj2FraseAtaque<br/><hr/>$pj1ResultadoAtaque<br/>$pj2ResultadoAtaque"; 
	
	$hpact2 = $hpact2-$dano2;
	$hpact1 = $hpact1-$dano1;
	
?>