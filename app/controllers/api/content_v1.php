<?php
// Authenticated, read-only JSON facade for published Heaven's Gate content.
require_once __DIR__.'/../../helpers/tool_api.php';
require_once __DIR__.'/../../helpers/search_catalog.php';
require_once __DIR__.'/../../domains/search/queries.php';
require_once __DIR__.'/../../domains/api/content_details.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$send = static function(array $data,int $status=200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
};
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') {
    header('Allow: GET'); $send(['ok'=>false,'error'=>'method_not_allowed'],405); return;
}
// Temporary staging credential: SHA-256 of a 256-bit random token. No plaintext
// credential is stored in Git. Expires automatically on 2026-11-18 00:00 CET.
// When HG_CONTENT_API_TOKEN is configured, this fallback is permanently disabled.
$secret=hg_tool_api_config('HG_CONTENT_API_TOKEN');
$auth=(string)($_SERVER['HTTP_AUTHORIZATION']??'');
$token=preg_match('/^Bearer\\s+([^\\s]+)$/i',$auth,$match)?$match[1]:'';
$temporaryDigest='eea6db04bb0bf5e74d5876e3b0829e6475dc649b62095b3d628f1ca1ec6d6bb7';
$temporaryExpiresAt=strtotime('2026-11-18T00:00:00+01:00');
if ($secret!=='') {
    $authorized=$token!=='' && hash_equals($secret,$token);
} else {
    $authorized=$token!=='' && time()<$temporaryExpiresAt
        && hash_equals($temporaryDigest,hash('sha256',$token));
}
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
