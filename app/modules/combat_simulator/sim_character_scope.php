<?php

if (!function_exists('sim_sanitize_int_csv')) {
	function sim_sanitize_int_csv($csv)
	{
		$csv = (string)$csv;
		if (trim($csv) === '') {
			return '';
		}

		$parts = preg_split('/\s*,\s*/', trim($csv));
		$ints = [];
		foreach ($parts as $part) {
			if ($part === '') {
				continue;
			}
			if (preg_match('/^\d+$/', $part)) {
				$ints[] = (string)(int)$part;
			}
		}

		if (empty($ints)) {
			return '';
		}

		return implode(',', array_values(array_unique($ints)));
	}
}

if (!function_exists('sim_chronicle_not_in_sql')) {
	function sim_chronicle_not_in_sql($columnName = 'chronicle_id')
	{
		$excludeChronicles = isset($GLOBALS['excludeChronicles']) ? sim_sanitize_int_csv($GLOBALS['excludeChronicles']) : '';
		if ($excludeChronicles === '') {
			return '';
		}

		return " AND {$columnName} NOT IN ({$excludeChronicles}) ";
	}
}

if (!function_exists('sim_is_character_allowed')) {
	function sim_is_character_allowed($link, $characterId)
	{
		$characterId = (int)$characterId;
		if ($characterId <= 0) {
			return false;
		}

		$whereChron = sim_chronicle_not_in_sql('chronicle_id');
		$query = "SELECT id FROM fact_characters WHERE id = {$characterId} {$whereChron} LIMIT 1";
		$result = mysql_query($query, $link);
		if (!$result) {
			return false;
		}

		return mysql_num_rows($result) > 0;
	}
}

?>
