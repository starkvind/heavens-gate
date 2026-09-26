<?php

include_once(__DIR__ . '/../../helpers/runtime_response.php');
include_once(__DIR__ . '/../../domains/configuration/admin.php');

$adminPassword = '';
$adminPasswordMode = 'hash';
$adminPasswordLoadError = '';

if (!function_exists('hg_admin_password_is_hash')) {
    function hg_admin_password_is_hash(string $value): bool
    {
        $info = password_get_info($value);
        return isset($info['algo']) && $info['algo'] !== null && $info['algo'] !== 0;
    }
}

$passwordState = hg_configuration_admin_load_admin_password($link);
if (empty($passwordState['ok'])) {
    hg_runtime_log_error('admin_get_pwd.query_prepare', (string)($passwordState['error'] ?? 'unknown'));
    $adminPasswordLoadError = 'No se pudo cargar la configuracion de acceso.';
    return;
}

if (empty($passwordState['found'])) {
    hg_runtime_log_error('admin_get_pwd.password_missing', 'rel_pwd no encontrado en dim_web_configuration.');
    $adminPasswordLoadError = 'No se encontro la contrasena de administracion.';
    return;
}

$adminPasswordRaw = (string)($passwordState['value'] ?? '');
if (!hg_admin_password_is_hash($adminPasswordRaw)) {
    hg_runtime_log_error('admin_get_pwd.password_format', 'rel_pwd no utiliza un password hash reconocido.');
    $adminPasswordLoadError = 'La configuracion de acceso administrativo requiere migracion.';
    return;
}

$adminPassword = $adminPasswordRaw;
?>
