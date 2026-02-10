<?php
	if ($bioAlias != "") { 		// Alias del Personaje
		echo "<div class='bioRenglonData'>";
			echo"<div class='bioDataName'>Alias:</div>";
			echo"<div class='bioDataText'>$bioAlias</div>";
		echo "</div>";
	}
	if ($bioPackName != "") { 	// Nombre de manada del Personaje
		echo "<div class='bioRenglonData'>";
			echo"<div class='bioDataName'>$titlePkName:</div>";
			echo"<div class='bioDataText'>$bioPackName</div>";
		echo "</div>";
	}
	if ($bioBday != "") {		// Cumpleaños del Personaje
		echo "<div class='bioRenglonData'>";
			echo"<div class='bioDataName'>Cumpleaños:</div>";
			echo"<div class='bioDataText'>$bioBday</div>";
		echo "</div>";
	}
	if ($bioStatus != "") {		// Estado del Personaje
		echo "<div class='bioRenglonData'>";
			echo"<div class='bioDataName'>Estado:</div>";
			echo"<div class='bioDataText'>$bioStatus</div>";
		echo "</div>";
	}
	if ($bioDethCaus != "") {
		echo "<div class='bioRenglonData'>";
			echo"<div class='bioDataName'>Muerte:</div>";
			echo"<div class='bioDataText'>".ucfirst($bioDethCaus)."</div>";
		echo "</div>";
	}
	if ($bioConcept != "") {	// Concepto del Personaje
		echo "<div class='bioRenglonData'>";
			echo"<div class='bioDataName'>Concepto:</div>";
			echo"<div class='bioDataText'>$bioConcept</div>";
		echo "</div>";
	}
?>