<?php
// Authenticated, read-only JSON facade for published Heaven's Gate content.
require_once __DIR__.'/../../helpers/tool_api.php';
require_once __DIR__.'/../../helpers/search_catalog.php';
require_once __DIR__.'/../../domains/search/queries.php';
require_once __DIR__.'/../../domains/api/content_details.php';
require_once __DIR__.'/../../domains/api/audit.php';
$apiAudit = hg_api_audit_begin('rest', (string)($_SERVER['REQUEST_METHOD'] ?? ''),
    is_array($hgRequest['query'] ?? null) ? $hgRequest['query'] : [],
    (string)($_SERVER['REMOTE_ADDR'] ?? ''), (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$send = static function(array $data,int $status=200) use ($apiAudit): void {
    http_response_code($status);
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    hg_api_audit_response($apiAudit, (string)$encoded, $status >= 400 || ($data['ok'] ?? true) === false);
    echo $encoded;
};
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') {
    header('Allow: GET'); $send(['ok'=>false,'error'=>'method_not_allowed'],405); return;
}
require_once __DIR__.'/../../helpers/content_api_auth.php';
$secret=hg_tool_api_config('HG_CONTENT_API_TOKEN');
$auth=(string)($_SERVER['HTTP_AUTHORIZATION']??'');
$token=preg_match('/^Bearer\\s+([^\\s]+)$/i',$auth,$match)?$match[1]:'';
$authorized=hg_content_api_authenticate($token,$secret,time());
$apiAudit->row['client'] = $authorized ? 'rest-shared' : 'unauthenticated';
if (!$authorized) {
    header('WWW-Authenticate: Bearer realm="HeavensGateContent"');
    $send(['ok'=>false,'error'=>'unauthorized'],401);
    return;
}
if (!isset($link) || !($link instanceof mysqli)) {
    $send(['ok'=>false,'error'=>'service_unavailable'],503); return;
}
require_once __DIR__.'/../../domains/api/content_queries.php';
hg_content_api_handle($link,$hgRequest,$send);
