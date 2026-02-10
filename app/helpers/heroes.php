<?php
	/* Tomamos datos del entorno virtual. */
	$env = parse_ini_file(__DIR__ . '/../../../config.env');

	if (!defined('MYSQL_HOST')) define('MYSQL_HOST', $env['MYSQL_HOST']);
	if (!defined('MYSQL_USER')) define('MYSQL_USER', $env['MYSQL_USER']);
	if (!defined('MYSQL_PWD'))  define('MYSQL_PWD',  $env['MYSQL_PWD']);
	if (!defined('MYSQL_BDD'))  define('MYSQL_BDD',  $env['MYSQL_BDD']);

	$link = mysqli_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PWD, MYSQL_BDD);
	if (mysqli_connect_errno()) {
		echo "Failed to connect to MariaDB: " . mysqli_connect_error();
	}
?>
