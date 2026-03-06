<?php

if (!function_exists('sim_skill_name_to_column')) {
	function sim_skill_name_to_column($skillName)
	{
		$skill = (string)$skillName;
		switch ($skill) {
			case "Cuerpo a Cuerpo":
				return "armascc";
			case "Tiro con Arco":
			case "Arrojar":
			case "Atletismo":
				return "atletismo";
			case "Armas de Fuego":
				return "armasdefuego";
			case "Informatica":
			case "Informática":
				return "informatica";
			default:
				return "pelea";
		}
	}
}

if (!function_exists('sim_skill_column_to_label')) {
	function sim_skill_column_to_label($skillColumn)
	{
		switch ((string)$skillColumn) {
			case "armascc":
				return "Armas C.C";
			case "atletismo":
				return "Atletismo";
			case "armasdefuego":
				return "Armas de Fuego";
			case "informatica":
				return "Informatica";
			default:
				return "Pelea";
		}
	}
}

$nousesarmas1 = isset($nousesarmas1) ? (int)$nousesarmas1 : 0;
$nousesarmas2 = isset($nousesarmas2) ? (int)$nousesarmas2 : 0;

$idarma1 = isset($idarma1) ? (int)$idarma1 : 0;
$idarma2 = isset($idarma2) ? (int)$idarma2 : 0;

$skillz1a = "pelea";
$skillz2a = "pelea";

if ($nousesarmas1 !== 1 && !empty($arma11)) {
	$itemId1 = (int)$arma11;
	if ($itemId1 > 0) {
		$consulta = "SELECT id, habilidad FROM vw_sim_items WHERE id = {$itemId1} LIMIT 1";
		$query = mysql_query($consulta, $link);
		if ($query) {
			$yeah = mysql_fetch_array($query);
			if (is_array($yeah)) {
				$idarma1 = (int)($yeah["id"] ?? 0);
				$skillz1a = sim_skill_name_to_column($yeah["habilidad"] ?? "");
			}
		}
	}
}

if ($nousesarmas2 !== 1 && !empty($arma21)) {
	$itemId2 = (int)$arma21;
	if ($itemId2 > 0) {
		$consulta = "SELECT id, habilidad FROM vw_sim_items WHERE id = {$itemId2} LIMIT 1";
		$query = mysql_query($consulta, $link);
		if ($query) {
			$yeah = mysql_fetch_array($query);
			if (is_array($yeah)) {
				$idarma2 = (int)($yeah["id"] ?? 0);
				$skillz2a = sim_skill_name_to_column($yeah["habilidad"] ?? "");
			}
		}
	}
}

$suma1 = 0;
$suma2 = 0;

$charId1 = isset($idPJno1) ? (int)$idPJno1 : 0;
if ($charId1 > 0) {
	$consulta = "SELECT {$skillz1a} FROM vw_sim_characters WHERE id = {$charId1} LIMIT 1";
	$query = mysql_query($consulta, $link);
	if ($query) {
		$yeah = mysql_fetch_array($query);
		if (is_array($yeah) && isset($yeah[0])) {
			$suma1 = (int)$yeah[0];
		}
	}
}

$charId2 = isset($idPJno2) ? (int)$idPJno2 : 0;
if ($charId2 > 0) {
	$consulta = "SELECT {$skillz2a} FROM vw_sim_characters WHERE id = {$charId2} LIMIT 1";
	$query = mysql_query($consulta, $link);
	if ($query) {
		$yeah = mysql_fetch_array($query);
		if (is_array($yeah) && isset($yeah[0])) {
			$suma2 = (int)$yeah[0];
		}
	}
}

$skillz1b = sim_skill_column_to_label($skillz1a);
$skillz2b = sim_skill_column_to_label($skillz2a);

?>
