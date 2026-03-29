<?php
	/* Resolucion robusta de config.env para runtime web y herramientas. */
	$configCandidates = [
		__DIR__ . '/../../../config.env', // compatibilidad con instalacion actual
		__DIR__ . '/../../config.env',    // raiz del proyecto
		__DIR__ . '/../config.env',       // ubicacion legacy
	];

	$env = null;
	foreach ($configCandidates as $candidate) {
		if (is_file($candidate)) {
			$parsed = parse_ini_file($candidate);
			if (is_array($parsed)) {
				$env = $parsed;
				break;
			}
		}
	}

	if (!is_array($env)) {
		http_response_code(500);
		error_log("HG bootstrap error: config.env not found in expected locations.");
		die("Configuration error.");
	}

	$requiredKeys = ['MYSQL_HOST', 'MYSQL_USER', 'MYSQL_PWD', 'MYSQL_BDD'];
	foreach ($requiredKeys as $requiredKey) {
		if (!array_key_exists($requiredKey, $env) || $env[$requiredKey] === '') {
			http_response_code(500);
			error_log("HG bootstrap error: missing config key {$requiredKey}.");
			die("Configuration error.");
		}
	}

	if (!defined('MYSQL_HOST')) define('MYSQL_HOST', (string)$env['MYSQL_HOST']);
	if (!defined('MYSQL_USER')) define('MYSQL_USER', (string)$env['MYSQL_USER']);
	if (!defined('MYSQL_PWD'))  define('MYSQL_PWD',  (string)$env['MYSQL_PWD']);
	if (!defined('MYSQL_BDD'))  define('MYSQL_BDD',  (string)$env['MYSQL_BDD']);

	$link = mysqli_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PWD, MYSQL_BDD);
	if (mysqli_connect_errno()) {
		http_response_code(500);
		error_log("HG database connection failed: " . mysqli_connect_error());
		die("Database connection error.");
	}

	// Enforce UTF-8 end-to-end for all queries/results.
	mysqli_set_charset($link, 'utf8mb4');
?>
