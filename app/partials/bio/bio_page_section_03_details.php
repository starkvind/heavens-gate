<?php
	if ($bioAlias != "") { 		// Alias del Personaje
		echo "<div class='power-stat'><div class='power-stat__label'>Alias</div><div class='power-stat__value'>" . h($bioAlias) . "</div></div>";
	}
	if ($bioPackName != "") { 	// Nombre de manada del Personaje
		echo "<div class='power-stat'><div class='power-stat__label'>" . h($titlePkName) . "</div><div class='power-stat__value'>" . h($bioPackName) . "</div></div>";
	}
	echo "<div class='power-stat'><div class='power-stat__label'>" . h($bioBirthLabel ?? 'Fecha de nacimiento') . "</div><div class='power-stat__value'>" . h($bioBday !== '' ? $bioBday : 'Desconocido') . "</div></div>";
	if ($bioStatus != "") {		// Estado del Personaje
		echo "<div class='power-stat'><div class='power-stat__label'>Estado</div><div class='power-stat__value'>" . h($bioStatus) . "</div></div>";
	}
	if (($bioDeathDisplay ?? '') != "") {
		echo "<div class='power-stat'><div class='power-stat__label'>Muerte</div><div class='power-stat__value'>" . h($bioDeathDisplay) . "</div></div>";
	}
	if ($bioConcept != "") {	// Concepto del Personaje
		echo "<div class='power-stat'><div class='power-stat__label'>Concepto</div><div class='power-stat__value'>" . h($bioConcept) . "</div></div>";
	}
?>
