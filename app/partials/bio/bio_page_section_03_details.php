<?php
	if ($bioAlias != "") { 		// Alias del Personaje
		echo "<div class='power-stat'><div class='power-stat__label'>Alias</div><div class='power-stat__value'>" . h($bioAlias) . "</div></div>";
	}
	if ($bioPackName != "") { 	// Nombre de manada del Personaje
		echo "<div class='power-stat'><div class='power-stat__label'>" . h($titlePkName) . "</div><div class='power-stat__value'>" . h($bioPackName) . "</div></div>";
	}
	if ($bioBday != "") {		// Cumpleaños del Personaje
		echo "<div class='power-stat'><div class='power-stat__label'>Cumplea&ntilde;os</div><div class='power-stat__value'>" . h($bioBday) . "</div></div>";
	}
	if ($bioStatus != "") {		// Estado del Personaje
		echo "<div class='power-stat'><div class='power-stat__label'>Estado</div><div class='power-stat__value'>" . h($bioStatus) . "</div></div>";
	}
	if ($bioDethCaus != "") {
		echo "<div class='power-stat'><div class='power-stat__label'>Muerte</div><div class='power-stat__value'>" . h(ucfirst($bioDethCaus)) . "</div></div>";
	}
	if ($bioConcept != "") {	// Concepto del Personaje
		echo "<div class='power-stat'><div class='power-stat__label'>Concepto</div><div class='power-stat__value'>" . h($bioConcept) . "</div></div>";
	}
?>
