<?php
echo "<tr><td colspan='4' class=''><br/>";
if ($iniciativa1 > $iniciativa2) {

	// ATACA PRIMERO EL JUGADOR 1

for($hpact1 >= 0;$hpact2 >= 0;) {
	/* ~~~~~~~~~~~~~~ */
	/* COMIENZO TURNO */
	/* ~~~~~~~~~~~~~~ */
	$turnos = $turnos+1;
	/* ======================================================================================================= */
	$combateArray["turnos"] = $turnos; /* Guardamos el turno en ARRAY */
	/* ======================================================================================================= */
	// REGENERACION //
	if ($turnos > 1) { include ("sim_battle_hitpoint_regeneration.php"); }
	// REGENERACION //
	echo "<fieldset id='campoTurnoComb'>";
	echo "<legend>Turno $turnos</legend>";

	/* COMIENZO ATAQUE JUGADOR 1 */
	
	include ("sim_battle_ataque_j1.php");
	
	$combateArray["turnoAtacante$turnos"] = "$pj1FraseAtaque<br/>$pj1ResultadoAtaque"; /* Guardamos datos del turno en ARRAY */
	
	/* FIN ATAQUE JUGADOR 1 */ 

	if ($hpact2 <= 0) { 
		$combateArray["resultadofinal"] = $n2cae;
		echo "<p id='fraseFinalCombate'>$n2cae</p>"; 
		break; 
	} else { //
	
	/* COMIENZO ATAQUE JUGADOR 2 */ 

	include ("sim_battle_ataque_j2.php");
	
	$combateArray["turnoDefensor$turnos"] = "$pj2FraseAtaque<br/>$pj2ResultadoAtaque"; /* Guardamos datos del turno en ARRAY */
	
	/* FIN ATAQUE JUGADOR 2 */ 

	/* ~~~~~~~~~~~~~~ */
	/* FIN DEL TURNO  */
	/* ~~~~~~~~~~~~~~ */
	if ($hpact1 <= 0) {
		$combateArray["resultadofinal"] = $n1cae;
		echo "<p id='fraseFinalCombate'>$n1cae</p>"; 
		break; 
	}

	}
	if ($turnos >= $maxturn) { 
		$combateArray["resultadofinal"] = $mensajefin;
		echo "<p id='fraseFinalCombate'>$mensajefin</p>";
		break; 
	}
	
	echo "</fieldset>";
}

} elseif ($iniciativa2 > $iniciativa1) {

// ATACA PRIMERO EL RIVAL

for($hpact1 >= 0;$hpact2 >= 0;) {
	/* ~~~~~~~~~~~~~~ */
	/* COMIENZO TURNO */
	/* ~~~~~~~~~~~~~~ */
	$turnos = $turnos+1;
	/* ======================================================================================================= */
	$combateArray["turnos"] = $turnos; /* Guardamos el turno en ARRAY */
	/* ======================================================================================================= */
	// REGENERACION //
	if ($turnos > 1) { include ("sim_battle_hitpoint_regeneration.php"); }
	// REGENERACION //	
	echo "<fieldset id='campoTurnoComb'>";
	echo "<legend>Turno $turnos</legend>";
	
	/* COMIENZO ATAQUE JUGADOR 2 */
	
	include ("sim_battle_ataque_j2.php");
	
	$combateArray["turnoAtacante$turnos"] = "$pj2FraseAtaque<br/>$pj2ResultadoAtaque"; /* Guardamos datos del turno en ARRAY */
	
	/* FIN ATAQUE JUGADOR 2 */

	if ($hpact1 <= 0) { 
		$combateArray["resultadofinal"] = $n1cae;
		echo "<p id='fraseFinalCombate'>$n1cae</p>";
		break; 
	} else {
	
	/* COMIENZO ATAQUE JUGADOR 1 */

	include ("sim_battle_ataque_j1.php");
	
	$combateArray["turnoDefensor$turnos"] = "$pj1FraseAtaque<br/>$pj1ResultadoAtaque"; /* Guardamos datos del turno en ARRAY */
	
	/* FIN ATAQUE JUGADOR 1 */

	/* ~~~~~~~~~~~~~~ */
	/* FIN DEL TURNO  */
	/* ~~~~~~~~~~~~~~ */
	if ($hpact2 <= 0) { 
		$combateArray["resultadofinal"] = $n2cae;
		echo "<p id='fraseFinalCombate'>$n2cae</p>";
		break; 
	} //

	}
	if ($turnos >= $maxturn) { 
		$combateArray["resultadofinal"] = $mensajefin;
		echo "<p id='fraseFinalCombate'>$mensajefin</p>"; 
		break; 
	} //
	echo "</fieldset>";
	
}

} else {

// ATACAN LOS DOS A LA VEZ

for($hpact1 >= 0;$hpact2 >= 0;) {
	/* ~~~~~~~~~~~~~~ */
	/* COMIENZO TURNO */
	/* ~~~~~~~~~~~~~~ */
	$turnos = $turnos+1;
	/* ======================================================================================================= */
	$combateArray["turnos"] = $turnos; /* Guardamos el turno en ARRAY */
	/* ======================================================================================================= */
	// REGENERACION //
	if ($turnos > 1) { include ("sim_battle_hitpoint_regeneration.php"); }
	// REGENERACION //
	echo "<fieldset id='campoTurnoComb'>";
	echo "<legend>Turno $turnos</legend>";
	/* ATACAN LOS DOS A LA VEZ */
	include ("sim_battle_ataque_j1yj2.php");
	/* DEJAN DE ATACARSE LOS DOS A LA VEZ */

	/* ~~~~~~~~~~~~~~ */
	/* FIN DEL TURNO  */ 
	/* ~~~~~~~~~~~~~~ */
	if ($hpact1 <= 0 AND $hpact2 <= 0) {
		$combateArray["resultadofinal"] = $doubleKO;
		echo "<p id='fraseFinalCombate'>$doubleKO</p>";	
		break; 
	} elseif ($hpact1 <= 0) { 
		$combateArray["resultadofinal"] = $n1cae;
		echo "<p id='fraseFinalCombate'>$n1cae</p>"; 
		break; 
	} elseif ($hpact2 <= 0) {
		$combateArray["resultadofinal"] = $n2cae;
		echo "<p id='fraseFinalCombate'>$n2cae</p>";
		break; 
	}
	if ($hpact2 <= 0 OR $hpact1 <= 0) {
		break; 
	} else {
		if ($turnos >= $maxturn) { 
			$combateArray["resultadofinal"] = $mensajefin;
			echo "<p id='fraseFinalCombate'>$mensajefin</p>"; 
			break; 
	}
	echo "</fieldset>";

}

}

}

echo "</td></tr>";

?>