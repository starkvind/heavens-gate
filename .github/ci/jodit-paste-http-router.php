<?php
declare(strict_types=1);
// Local CI router only, never use this fixture as a deployed application.
if (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__,2).'/app/http/request_context.php';
require_once dirname(__DIR__,2).'/app/helpers/admin_jodit.php';
hg_admin_session_start();
$keys=['docs'=>'csrf_admin_docs','news'=>'csrf_admin_news','chapters'=>'csrf_admin_chapters','items'=>'csrf_admin_items',
    'systems'=>'csrf_admin_systems','forms'=>'csrf_admin_forms','seasons'=>'csrf_admin_seasons','powers'=>'csrf_admin_poderes',
    'system_details'=>'csrf_admin_system_details'];
if (($_SERVER['REQUEST_URI'] ?? '') === '/fixture-login') {
    hg_admin_mark_authenticated();
    foreach ($keys as $key) hg_admin_ensure_csrf_token($key);
    hg_admin_json_success(['tokens'=>array_intersect_key($_SESSION,array_flip($keys))]);
}
$module=(string)($_GET['module']??'');
if (!isset($keys[$module])) hg_admin_json_error('Unknown fixture module',404);
hg_admin_jodit_settings($module,$keys[$module],hg_request_context_from_query($_GET,$_POST));
hg_admin_json_error('No paste action',400);
