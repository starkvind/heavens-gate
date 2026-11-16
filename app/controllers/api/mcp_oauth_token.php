<?php
// OAuth public-client token endpoint: authorization_code PKCE and rotating refresh_token.
require_once __DIR__ . '/../../helpers/tool_api.php';
require_once __DIR__ . '/../../domains/api/mcp_oauth.php';
require_once __DIR__ . '/../../domains/api/mcp_oauth_refresh.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
$respond = static function(array $data, int $status = 400): void {
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
$resource = hg_request_body_value($hgRequest, 'resource', '');
if ($clientId !== HG_MCP_OAUTH_CLIENT
    || ($resource !== '' && $resource !== HG_MCP_OAUTH_RESOURCE)
    || hg_request_body_value($hgRequest, 'client_secret', '') !== '') {
    $respond(['error' => 'invalid_client']);
    return;
}
if ($grant === 'authorization_code') {
    $redirectUri = hg_request_body_value($hgRequest, 'redirect_uri', '');
    $code = hg_request_body_value($hgRequest, 'code', '');
    $verifier = hg_request_body_value($hgRequest, 'code_verifier', '');
    if ($redirectUri !== HG_MCP_OAUTH_REDIRECT || $resource !== HG_MCP_OAUTH_RESOURCE) {
        $respond(['error' => 'invalid_request']);
        return;
    }
    if (!hg_mcp_oauth_redeem_code($secret, $code, $verifier, [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'resource' => $resource
    ], time())) {
        $respond(['error' => 'invalid_grant']);
        return;
    }
    $refresh = hg_mcp_refresh_issue($secret, time());
    if ($refresh === false) {
        $respond(['error' => 'temporarily_unavailable'], 503);
        return;
    }
} elseif ($grant === 'refresh_token') {
    // A refresh credential is bound to its original client, audience and scope.
    // Every successful refresh returns a new token and invalidates the previous.
    $submitted = hg_request_body_value($hgRequest, 'refresh_token', '');
    if ($submitted === '' || strlen($submitted) > 200
        || hg_request_body_value($hgRequest, 'scope', '') !== ''
            && hg_request_body_value($hgRequest, 'scope', '') !== HG_MCP_OAUTH_SCOPE) {
        $respond(['error' => 'invalid_request']);
        return;
    }
    $refresh = hg_mcp_refresh_rotate($secret, $submitted, time());
    if ($refresh === false) {
        $respond(['error' => 'invalid_grant']);
        return;
    }
} else {
    $respond(['error' => 'unsupported_grant_type']);
    return;
}
$respond([
    'access_token' => hg_mcp_oauth_issue_access_token($secret, time()),
    'token_type' => 'Bearer',
    'expires_in' => 3600,
    'refresh_token' => $refresh,
    'scope' => HG_MCP_OAUTH_SCOPE
], 200);
