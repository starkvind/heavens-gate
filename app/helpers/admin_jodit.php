<?php
declare(strict_types=1);
require_once __DIR__ . '/admin_ajax.php';
require_once __DIR__ . '/html_sanitizer.php';

/** Explicit per-module preproduction opt-in; never trust request parameters. */
function hg_admin_jodit_settings(string $module, string $csrfKey, array $request): array
{
    $modules = array_map('trim', explode(',', (string)getenv('HG_JODIT_PILOT_MODULES')));
    $enabled = in_array($module, $modules, true);
    $root = dirname(__DIR__, 2) . '/assets/vendor/jodit/4.21.0';
    $ready = $enabled && is_file($root . '/jodit.min.js') && is_file($root . '/jodit.min.css');
    if (hg_request_query_param($request, 'ajax_mode') === 'sanitize_paste') {
        hg_admin_require_session(true);
        if (!$ready) hg_admin_json_error('Piloto Jodit no disponible', 503);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') hg_admin_json_error('Método no permitido', 405);
        if (!hg_admin_csrf_valid(hg_admin_extract_csrf_token([]), $csrfKey)) hg_admin_json_error('CSRF inválido', 403);
        try {
            $body = hg_admin_read_json_payload();
            if (!is_string($body['html'] ?? null)) throw new InvalidArgumentException('Invalid clipboard HTML');
            hg_admin_json_success(['content'=>hg_sanitize_rich_html($body['html'])]);
        } catch (Throwable $e) {
            error_log('HG Jodit paste unavailable: ' . get_class($e));
            hg_admin_json_error('No se pudo validar el contenido del portapapeles.', 400);
        }
    }
    return ['enabled'=>$enabled, 'ready'=>$ready, 'section'=>'admin_' . $module];
}

function hg_admin_jodit_editor_html(string $html, array $settings): string
{
    if (!$settings['ready']) throw new RuntimeException('Jodit assets unavailable');
    return hg_sanitize_rich_html($html);
}
