<?php
// Stateless authenticated MCP over HTTP; all tool calls reuse read-only Content API.
require_once __DIR__ . '/../../helpers/tool_api.php';
require_once __DIR__ . '/../../helpers/content_api_auth.php';
require_once __DIR__ . '/../../domains/api/mcp_oauth.php';
require_once __DIR__ . '/../../domains/api/mcp_protocol.php';
require_once __DIR__ . '/../../domains/api/audit.php';
$apiAudit = hg_api_audit_begin('mcp', (string)($_SERVER['REQUEST_METHOD'] ?? ''), [],
    (string)($_SERVER['REMOTE_ADDR'] ?? ''), (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('MCP-Protocol-Version: 2025-06-18');
$send = static function(array $payload, int $status = 200) use ($apiAudit): void {
    http_response_code($status);
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    hg_api_audit_response($apiAudit, (string)$encoded,
        $status >= 400 || isset($payload['error']) || !empty($payload['result']['isError']));
    echo $encoded;
};
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
if ($method !== 'POST') {
    header('Allow: POST');
    $send(['error' => 'method_not_allowed'], 405);
    return;
}
$header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$provided = preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) ? $matches[1] : '';
$oauthSecret = hg_mcp_oauth_secret();
// Once OAuth is configured, MCP accepts ONLY audience-bound OAuth credentials.
// The REST Content API remains independently Bearer-authenticated.
$authorized = $oauthSecret !== ''
    ? hg_mcp_oauth_verify_access_token($provided, $oauthSecret, time())
    : hg_content_api_authenticate($provided, hg_tool_api_config('HG_CONTENT_API_TOKEN'), time());
$apiAudit->row['client'] = !$authorized ? 'unauthenticated' : ($oauthSecret !== '' ? 'chatgpt-oauth' : 'rest-shared');
if (!$authorized) {
    if ($oauthSecret !== '') {
        header('WWW-Authenticate: Bearer resource_metadata="' . HG_MCP_OAUTH_ISSUER . '/.well-known/oauth-protected-resource", scope="content:read"');
    } else {
        header('WWW-Authenticate: Bearer realm="HeavensGateMCP"');
    }
    $send(['error' => 'unauthorized'], 401);
    return;
}
if (!isset($link) || !($link instanceof mysqli)) {
    $send(['error' => 'service_unavailable'], 503);
    return;
}
$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 32768) {
    $send(['error' => 'request_too_large'], 413);
    return;
}
$message = json_decode($raw, true);
if (!is_array($message) || array_is_list($message)) {
    $send(hg_mcp_error(null, -32700, 'Parse error'), 400);
    return;
}
hg_api_audit_mcp($apiAudit, $message);
require_once __DIR__ . '/../../helpers/search_catalog.php';
require_once __DIR__ . '/../../domains/search/queries.php';
require_once __DIR__ . '/../../domains/api/content_details.php';
require_once __DIR__ . '/../../domains/api/content_queries.php';
$result = hg_mcp_dispatch($message, static function(array $query) use ($link): array {
    $data = null;
    $readRequest = ['query' => $query];
    hg_content_api_handle($link, $readRequest, static function(array $payload, int $status = 200) use (&$data): void {
        $data = $payload;
        $data['_http_status'] = $status;
    });
    return $data ?? ['ok' => false, 'error' => 'empty_response'];
});
if ($result === null) {
    http_response_code(202);
    return;
}
$send($result);
