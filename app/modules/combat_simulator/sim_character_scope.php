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

if (!function_exists('sim_table_exists')) {
	function sim_table_exists($link, $tableName)
	{
		$safeTable = mysql_real_escape_string((string)$tableName, $link);
		$rs = mysql_query("SHOW TABLES LIKE '$safeTable'", $link);
		return ($rs && mysql_num_rows($rs) > 0);
	}
}

if (!function_exists('sim_column_exists')) {
	function sim_column_exists($link, $tableName, $columnName)
	{
		$safeTable = mysql_real_escape_string((string)$tableName, $link);
		$safeCol = mysql_real_escape_string((string)$columnName, $link);
		$rs = mysql_query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeCol'", $link);
		return ($rs && mysql_num_rows($rs) > 0);
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

if (!function_exists('sim_get_active_season_id')) {
	function sim_get_active_season_id($link)
	{
		if (!sim_table_exists($link, 'fact_sim_seasons')) {
			return 0;
		}

		$activeSeasonId = 0;
		$queryActive = "SELECT id FROM fact_sim_seasons WHERE is_active = 1 ORDER BY updated_at DESC, id DESC LIMIT 1";
		$rsActive = mysql_query($queryActive, $link);
		if ($rsActive && mysql_num_rows($rsActive) > 0) {
			$rowActive = mysql_fetch_array($rsActive);
			$activeSeasonId = (int)($rowActive['id'] ?? 0);
		}

		if ($activeSeasonId > 0) {
			return $activeSeasonId;
		}

		$queryFallback = "SELECT id FROM fact_sim_seasons ORDER BY updated_at DESC, id DESC LIMIT 1";
		$rsFallback = mysql_query($queryFallback, $link);
		if ($rsFallback && mysql_num_rows($rsFallback) > 0) {
			$rowFallback = mysql_fetch_array($rsFallback);
			return (int)($rowFallback['id'] ?? 0);
		}

		return 0;
	}
}

if (!function_exists('sim_active_season_condition')) {
	function sim_active_season_condition($link, $tableName, $columnName = 'season_id', $alias = '')
	{
		if (!sim_table_exists($link, (string)$tableName) || !sim_column_exists($link, (string)$tableName, (string)$columnName)) {
			return '';
		}

		$activeSeasonId = sim_get_active_season_id($link);
		if ($activeSeasonId <= 0) {
			return '';
		}

		$alias = trim((string)$alias);
		$field = ($alias !== '') ? ($alias . '.' . $columnName) : $columnName;
		return $field . ' = ' . (int)$activeSeasonId;
	}
}

if (!function_exists('sim_active_season_where_sql')) {
	function sim_active_season_where_sql($link, $tableName, $columnName = 'season_id', $alias = '')
	{
		$condition = sim_active_season_condition($link, $tableName, $columnName, $alias);
		if ($condition === '') {
			return '';
		}
		return ' WHERE ' . $condition . ' ';
	}
}

if (!function_exists('sim_active_season_and_sql')) {
	function sim_active_season_and_sql($link, $tableName, $columnName = 'season_id', $alias = '')
	{
		$condition = sim_active_season_condition($link, $tableName, $columnName, $alias);
		if ($condition === '') {
			return '';
		}
		return ' AND ' . $condition . ' ';
	}
}

?>
