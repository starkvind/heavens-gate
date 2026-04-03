<?php

include_once(__DIR__ . '/../../helpers/runtime_response.php');

$securityPath = __DIR__ . '/../../helpers/security.php';
$adminPassword = '';
$adminPasswordMode = 'hash';
$adminPasswordLoadError = '';

if (!file_exists($securityPath)) {
    hg_runtime_log_error('admin_get_pwd.security_missing', $securityPath);
    $adminPasswordLoadError = 'No se pudo cargar la configuracion de seguridad.';
    return;
}
include_once($securityPath);

if (!function_exists('hg_admin_password_is_hash')) {
    function hg_admin_password_is_hash(string $value): bool
    {
        $info = password_get_info($value);
        return isset($info['algo']) && $info['algo'] !== null && $info['algo'] !== 0;
    }
}

if (!function_exists('hg_admin_store_password_value')) {
    function hg_admin_store_password_value(mysqli $link, string $value): bool
    {
        $stmt = mysqli_prepare(
            $link,
            "UPDATE dim_web_configuration SET config_value = ? WHERE config_name = 'rel_pwd' ORDER BY id DESC LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 's', $value);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }
}

$query = "SELECT config_value FROM dim_web_configuration WHERE config_name = 'rel_pwd' ORDER BY id DESC LIMIT 1";
$stmt = mysqli_prepare($link, $query);
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $resultQuery = null;
    if ($result && mysqli_num_rows($result) > 0) {
        $resultQuery = mysqli_fetch_assoc($result);
    }

    if (!$resultQuery || !isset($resultQuery['config_value'])) {
        hg_runtime_log_error('admin_get_pwd.password_missing', 'rel_pwd no encontrado en dim_web_configuration.');
        $adminPasswordLoadError = 'No se encontro la contrasena de administracion.';
        return;
    }

    $adminPasswordRaw = (string)$resultQuery['config_value'];
    $adminPasswordMode = 'legacy';

    if (hg_admin_password_is_hash($adminPasswordRaw)) {
        $adminPassword = $adminPasswordRaw;
        $adminPasswordMode = 'hash';
    } else {
        $adminPassword = decrypt_string($adminPasswordRaw);
    }
} else {
    hg_runtime_log_error('admin_get_pwd.query_prepare', mysqli_error($link));
    $adminPasswordLoadError = 'No se pudo cargar la configuracion de acceso.';
    return;
}
?>
