<?php

$securityPath = __DIR__ . '/../../helpers/security.php';
if (!file_exists($securityPath)) {
    die("Error: no se pudo cargar seguridad (security.php).");
}
include_once($securityPath);

$query = "SELECT config_value FROM dim_web_configuration WHERE config_name = 'rel_pwd' LIMIT 1";
$stmt = mysqli_prepare($link, $query);
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $resultQuery = null;
    if ($result && mysqli_num_rows($result) > 0) {
        $resultQuery = mysqli_fetch_assoc($result);
    }

    if (!$resultQuery || !isset($resultQuery['config_value'])) {
        die("Error: contrase??a de administrador no encontrada.");
    }

    // Obtener y descifrar contrase??a de administrador
    $adminPassword = decrypt_string($resultQuery['config_value']);
} else {
    die("Error: no se pudo cargar la contrase??a.");
}
?>
