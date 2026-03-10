<?php

// Defaults for legacy callers.
$podesluchar = isset($podesluchar) ? (int)$podesluchar : 1;
$simRateLimitMessage = '';

if (session_status() === PHP_SESSION_NONE) {
	@session_start();
}

if (!function_exists('sim_cfg_value')) {
	function sim_cfg_value($link, $configName, $defaultValue)
	{
		$safeName = mysql_real_escape_string((string)$configName, $link);
		$query = "SELECT config_value FROM dim_web_configuration WHERE config_name = '$safeName' ORDER BY id DESC LIMIT 1";
		$result = mysql_query($query, $link);
		if (!$result || mysql_num_rows($result) === 0) {
			return $defaultValue;
		}
		$row = mysql_fetch_array($result);
		return (string)($row['config_value'] ?? $defaultValue);
	}
}

if (!function_exists('sim_cfg_bool')) {
	function sim_cfg_bool($link, $configName, $defaultValue)
	{
		$raw = strtoupper(trim((string)sim_cfg_value($link, $configName, $defaultValue ? 'TRUE' : 'FALSE')));
		return in_array($raw, array('1', 'TRUE', 'YES', 'ON'), true);
	}
}

if (!function_exists('sim_cfg_int')) {
	function sim_cfg_int($link, $configName, $defaultValue)
	{
		$raw = sim_cfg_value($link, $configName, (string)$defaultValue);
		if (!is_numeric($raw)) {
			return (int)$defaultValue;
		}
		return (int)$raw;
	}
}

if (!function_exists('sim_count_ip_attempts_windows')) {
	function sim_count_ip_attempts_windows($link, $ipAddress)
	{
		$safeIp = mysql_real_escape_string((string)$ipAddress, $link);
		$query = "SELECT"
			. " COUNT(*) AS day_total,"
			. " SUM(CASE WHEN created_at >= (NOW() - INTERVAL 1 HOUR) THEN 1 ELSE 0 END) AS hour_total"
			. " FROM fact_sim_battles"
			. " WHERE request_ip = '$safeIp'"
			. "   AND created_at >= (NOW() - INTERVAL 1 DAY)";
		$result = mysql_query($query, $link);
		if (!$result || mysql_num_rows($result) === 0) {
			return array('hour' => 0, 'day' => 0);
		}
		$row = mysql_fetch_array($result);
		return array(
			'hour' => (int)($row['hour_total'] ?? 0),
			'day' => (int)($row['day_total'] ?? 0)
		);
	}
}

$rateLimitEnabled = sim_cfg_bool($link, 'combat_simulator_ip_limit_enabled', true);
if (!$rateLimitEnabled) {
	return;
}

// Admin sessions bypass combat simulator IP limits.
$isAdminSession = (!empty($_SESSION) && is_array($_SESSION) && !empty($_SESSION['is_admin']));
if ($isAdminSession) {
	return;
}

$requestIp = $_SERVER['REMOTE_ADDR'] ?? '';
if ($requestIp === '') {
	return;
}

$maxAttemptsPerHour = sim_cfg_int($link, 'combat_simulator_ip_limit_max_attempts_per_hour', 25);
$maxAttemptsPerDay = sim_cfg_int($link, 'combat_simulator_ip_limit_max_attempts_per_day', 120);

$attemptsWindow = (($maxAttemptsPerHour > 0) || ($maxAttemptsPerDay > 0))
	? sim_count_ip_attempts_windows($link, $requestIp)
	: array('hour' => 0, 'day' => 0);

$attemptsLastHour = (int)($attemptsWindow['hour'] ?? 0);
$attemptsLastDay = (int)($attemptsWindow['day'] ?? 0);

$hitHourLimit = ($maxAttemptsPerHour > 0 && $attemptsLastHour >= $maxAttemptsPerHour);
$hitDayLimit = ($maxAttemptsPerDay > 0 && $attemptsLastDay >= $maxAttemptsPerDay);

if ($hitHourLimit || $hitDayLimit) {
	$podesluchar = 0;

	$parts = array();
	if ($hitHourLimit) {
		$parts[] = "límite por hora ({$attemptsLastHour}/{$maxAttemptsPerHour})";
	}
	if ($hitDayLimit) {
		$parts[] = "límite por dia ({$attemptsLastDay}/{$maxAttemptsPerDay})";
	}

	$simRateLimitMessage = "Has alcanzado el " . implode(' y ', $parts) . " para esta IP. Vuelve a intentarlo más tarde.";
}

?>
