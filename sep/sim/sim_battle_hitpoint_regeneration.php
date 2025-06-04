<?php
/////////////////////////////////////////////////////////////////////////////////////////////
// VARIABLES DE LA REGENERACION 
/////////////////////////////////////////////////////////////////////////////////////////////
	$player1Regen = "¡$nombre1, al estar en forma $forma1, regenera <b><u>$cantidadRegenPj1</u> puntos de da&ntilde;o!</b>";
	$player2Regen = "¡$nombre2, al estar en forma $forma2, regenera <b><u>$cantidadRegenPj2</u> puntos de da&ntilde;o!</b>";
/////////////////////////////////////////////////////////////////////////////////////////////
// VARIABLES DE LA REGENERACION
/////////////////////////////////////////////////////////////////////////////////////////////

if ($usarRegen == "pj1" OR $usarRegen == "ambos") {
	if ($regenYesOrNotPj1 == 1) {
		//echo "Turno $turnos: HP de $nombre1 = $hpact1 ($heridas1 heridas) // ";
		if ($hpact1 > 0) {
			$saludNueva1 = $hpact1 + $cantidadRegenPj1;
			if ($heridas1 > 0) {
				$heridas1 = $heridas1 - $cantidadRegenPj1; 
				if ($heridas1 < 0) {
					$heridas1 = 0;
				}
			}
			if ($saludNueva1 > $vitmax) { 
				$printPJ1Regen = 0;
			} else {
				$hpact1 = $saludNueva1;
				$printPJ1Regen = 1;
			}
		}
		//echo "Despues: $hpact1 ($heridas1 heridas)<br/>";
	}
}
/////////////////////////////////////////////////////////////////////////////////////////////
if ($usarRegen == "pj2" OR $usarRegen == "ambos") {
	if ($regenYesOrNotPj2 == 1) {
		//echo "Turno $turnos: HP de $nombre2 = $hpact2 ($heridas2 heridas) // ";
		if ($hpact2 > 0) {
			$saludNueva2 = $hpact2 + $cantidadRegenPj2;
			if ($heridas2 > 0) {
				$heridas2 = $heridas2 - $cantidadRegenPj2; 
				if ($heridas2 < 0) {
					$heridas2 = 0;
				}
			}
			if ($saludNueva2 > $vitmax) {  
				$printPJ2Regen = 0;
			} else {
				$hpact2 = $saludNueva2;
				$printPJ2Regen = 1;
			}
		}
		//echo "Despues: $hpact2 ($heridas2 heridas)<br/>";
	}
}
/////////////////////////////////////////////////////////////////////////////////////////////
//echo "<hr/>";
if ($iniciativa2 > $iniciativa1) { // Ataca Jugador 2 primero
	if ($printPJ2Regen == 1) { 
		echo "<tr><td colspan='4' class='celdacombat'>$player2Regen</td></tr>"; 
		$combateArray["regenAtacante$turnos"] = $player2Regen; 
	}
	if ($printPJ1Regen == 1) { 
		echo "<tr><td colspan='4' class='celdacombat2'>$player1Regen</td></tr>"; 
		$combateArray["regenDefensor$turnos"] = $player1Regen; 
	}
} else {						   // Ataca Jugador 1 primero o EMPATE de iniciativas
	if ($printPJ1Regen == 1) { 
		echo "<tr><td colspan='4' class='celdacombat'>$player1Regen</td></tr>";
		$combateArray["regenAtacante$turnos"] = $player1Regen; 
	}
	if ($printPJ2Regen == 1) { 
		echo "<tr><td colspan='4' class='celdacombat2'>$player2Regen</td></tr>";
		$combateArray["regenDefensor$turnos"] = $player2Regen; 
	}
}
?>