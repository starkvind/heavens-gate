<?php
// OAuth public-client token endpoint: authorization_code grant with PKCE S256.
require_once __DIR__ . '/../../helpers/tool_api.php';
require_once __DIR__ . '/../../domains/api/mcp_oauth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
$respond = static function(array $data,int $status=400): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
};
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respond(['error' => 'invalid_request'], 405);
    return;
}
$secret = hg_mcp_oauth_secret();
if ($secret === '') {
    $respond(['error' => 'temporarily_unavailable'], 503);
    return;
}
$grant = hg_request_body_value($hgRequest, 'grant_type', '');
$clientId = hg_request_body_value($hgRequest, 'client_id', '');
$redirectUri = hg_request_body_value($hgRequest, 'redirect_uri', '');
$resource = hg_request_body_value($hgRequest, 'resource', '');
$code = hg_request_body_value($hgRequest, 'code', '');
$verifier = hg_request_body_value($hgRequest, 'code_verifier', '');
if ($grant !== 'authorization_code' || $clientId !== HG_MCP_OAUTH_CLIENT
    || $redirectUri !== HG_MCP_OAUTH_REDIRECT || $resource !== HG_MCP_OAUTH_RESOURCE
    || hg_request_body_value($hgRequest, 'client_secret', '') !== '') {
    $respond(['error' => 'invalid_request']);
    return;
}
if (!hg_mcp_oauth_redeem_code($secret, $code, $verifier, [
    'client_id' => $clientId, 'redirect_uri' => $redirectUri,
    'resource' => $resource
], time())) {
    $respond(['error' => 'invalid_grant']);
    return;
}
$respond([
    'access_token' => hg_mcp_oauth_issue_access_token($secret, time()),
    'token_type' => 'Bearer',
    'expires_in' => 3600,
    'scope' => HG_MCP_OAUTH_SCOPE
], 200);
