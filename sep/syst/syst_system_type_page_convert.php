<?php

switch ($systemTypeDocument) {
	case 1:
		$query = "SELECT * FROM nuevo_razas WHERE id LIKE '$systemIdDocument';";
		$energy = "Gnosis";
		break;
	case 2:
		$query = "SELECT * FROM nuevo_auspicios WHERE id LIKE '$systemIdDocument';";
		$energy = "Rabia";
		break;
	case 3:
		$query = "SELECT * FROM nuevo_tribus WHERE id LIKE '$systemIdDocument';";
		$energy = "Fuerza de voluntad";
		break;
	case 4:
		$query = "SELECT * FROM nuevo_miscsistemas WHERE id LIKE '$systemIdDocument';";
		break;
	default:
		$query = "Nada";
		break;
}
?>