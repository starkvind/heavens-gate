<?php
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

	echo "<div id='celdaAtaqueJ2'>$pj2FraseAtaque";
	
	include ("sim_battle_tirada_j2.php"); /* Datos extensos sobre la tirada del PJ 2*/

	if ($atq2 <= 0) { $atq2 = 0; }
	$dano1 = $atq2;
	
	if ($dano1 == 0) { 
		$pj2ResultadoAtaque = ", pero $fayl.";//"$nombre2 $fayl.";
		$tiradasFalliJ2 = $tiradasFalliJ2 + 1;
	} else { 
		$pj2ResultadoAtaque = " y causa <b>$dano1 puntos de da&ntilde;o</b>.";
		$tiradasExitoJ2 = $tiradasExitoJ2 + 1;
	}
	echo "$pj2ResultadoAtaque</div>"; 
	
	$hpact1 = $hpact1-$dano1;
	
?>