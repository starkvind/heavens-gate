<?php
	// Obtener y descifrar contraseña de administrador
	$stmt = $pdo->prepare("SELECT config_value FROM configuracion_web WHERE config_name = 'rel_pwd' LIMIT 1");
	$stmt->execute();
	$row = $stmt->fetch();

	if (!$row) {
		die("Error: no se pudo cargar la contraseña.");
	}

	$adminPassword = decrypt_string($row['config_value']);
?>