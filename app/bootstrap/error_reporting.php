<?php
	if (!isset($link) || !($link instanceof mysqli)) {
		require_once(__DIR__ . "/../helpers/db_connection.php");
	}

	if (!function_exists('hg_bootstrap_get_config_value')) {
		function hg_bootstrap_get_config_value(mysqli $link, string $configName, string $default = 'FALSE'): string
		{
			$sql = "SELECT config_value FROM dim_web_configuration WHERE config_name = ? LIMIT 1";
			$stmt = mysqli_prepare($link, $sql);
			if (!$stmt) {
				error_log("HG bootstrap config prepare failed for {$configName}: " . mysqli_error($link));
				return $default;
			}

			mysqli_stmt_bind_param($stmt, 's', $configName);
			if (!mysqli_stmt_execute($stmt)) {
				error_log("HG bootstrap config execute failed for {$configName}: " . mysqli_error($link));
				mysqli_stmt_close($stmt);
				return $default;
			}

			$result = mysqli_stmt_get_result($stmt);
			if (!$result) {
				error_log("HG bootstrap config result failed for {$configName}: " . mysqli_error($link));
				mysqli_stmt_close($stmt);
				return $default;
			}

			$row = mysqli_fetch_assoc($result);
			mysqli_free_result($result);
			mysqli_stmt_close($stmt);

			return (string)($row['config_value'] ?? $default);
		}
	}

	$showErrors = hg_bootstrap_get_config_value($link, 'error_reporting', 'FALSE');
	if ($showErrors === "TRUE") {
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		error_reporting(E_ALL);
	}

	$excludeChronicles = hg_bootstrap_get_config_value($link, 'exclude_chronicles', 'FALSE');
?>
