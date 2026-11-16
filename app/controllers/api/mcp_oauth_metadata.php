<?php
// Discovery is public, while the OAuth flow itself is fail-closed until configured.
require_once __DIR__ . '/../../helpers/tool_api.php';
require_once __DIR__ . '/../../domains/api/mcp_oauth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo '{"error":"method_not_allowed"}';
    return;
}
if (hg_mcp_oauth_secret() === '') {
    http_response_code(503);
    echo '{"error":"oauth_not_configured"}';
    return;
}
$kind = hg_request_route($hgRequest) === 'mcp_oauth_protected_metadata' ? 'protected' : 'authorization';
echo json_encode(hg_mcp_oauth_metadata($kind), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
