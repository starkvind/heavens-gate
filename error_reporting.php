<?php
	include("sep/heroes.php");

	// Obtener si queremos mostrar errores.
	$stmt = mysqli_prepare($link, "SELECT config_value FROM configuracion_web WHERE config_name = 'error_reporting' LIMIT 1");
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);

	if (!$result) {
		die("Error: no se pudo cargar la configuración.");
	}

	$row = mysqli_fetch_assoc($result);
	$showErrors = $row['config_value'] ?? 'FALSE';

	if ($showErrors === "TRUE") {
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		error_reporting(E_ALL);	
	}

	mysqli_stmt_close($stmt);
?>
