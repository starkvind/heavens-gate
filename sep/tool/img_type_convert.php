<?php

/* 
1  PJM
2  PJF
3  PNJM
4  PNJF
5  L
6  O
*/

$idTipo = $tiposDiferentes[$totalmanada];

switch ($idTipo) {
	case 1:
		$nombreTipo = "Personajes jugadores masculinos";
		break;
	case 2:
		$nombreTipo = "Personajes jugadores femeninos";
		break;
	case 3:
		$nombreTipo = "Personajes no jugadores masculinos";
		break;
	case 4:
		$nombreTipo = "Personajes no jugadores femeninos";
		break;
	case 5:
		$nombreTipo = "Lugares";
		break;
	case 6:
		$nombreTipo = "Objetos";
		break;
	default:
		$nombreTipo = "Categoría sin preparar";
		break;
}

?>