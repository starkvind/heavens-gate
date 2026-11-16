<?php
// Stateless authenticated MCP over HTTP; all tool calls reuse read-only Content API.
require_once __DIR__ . '/../../helpers/tool_api.php';
require_once __DIR__ . '/../../helpers/content_api_auth.php';
require_once __DIR__ . '/../../domains/api/mcp_protocol.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('MCP-Protocol-Version: 2025-06-18');
$send = static function(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
};
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
if ($method !== 'POST') {
    header('Allow: POST');
    $send(['error' => 'method_not_allowed'], 405);
    return;
}
$header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$provided = preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) ? $matches[1] : '';
$configured = hg_tool_api_config('HG_CONTENT_API_TOKEN');
if (!hg_content_api_authenticate($provided, $configured, time())) {
    header('WWW-Authenticate: Bearer realm="HeavensGateMCP"');
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
