<?php
require_once __DIR__ . '/../../app/domains/api/mcp_oauth.php';
require_once __DIR__ . '/../../app/domains/api/mcp_oauth_refresh.php';

$secret = random_bytes(32);
$tests = [];
$tests['protected metadata'] = (hg_mcp_oauth_metadata('protected')['resource'] ?? '') === HG_MCP_OAUTH_RESOURCE;
$as = hg_mcp_oauth_metadata('authorization');
$tests['PKCE discovery'] = in_array('S256', $as['code_challenge_methods_supported'], true);
$tests['issuer identification'] = ($as['authorization_response_iss_parameter_supported'] ?? false) === true;
$valid = [
    'response_type'=>'code','client_id'=>HG_MCP_OAUTH_CLIENT,'redirect_uri'=>HG_MCP_OAUTH_REDIRECT,
    'code_challenge'=>str_repeat('A',43),'code_challenge_method'=>'S256',
    'resource'=>HG_MCP_OAUTH_RESOURCE,'scope'=>HG_MCP_OAUTH_SCOPE,'state'=>'random-state',
];
$tests['allowlisted OAuth client'] = hg_mcp_oauth_authorization_params($valid) !== null;
$invalid = $valid; $invalid['redirect_uri'] = 'https://evil.example/callback';
$tests['open redirect rejected'] = hg_mcp_oauth_authorization_params($invalid) === null;
$invalid = $valid; $invalid['resource'] = 'https://evil.example/mcp';
$tests['other audience rejected'] = hg_mcp_oauth_authorization_params($invalid) === null;
$invalid = $valid; $invalid['code_challenge_method'] = 'plain';
$tests['PKCE plain rejected'] = hg_mcp_oauth_authorization_params($invalid) === null;
$now = time();
$token = hg_mcp_oauth_issue_access_token($secret, $now);
$tests['valid signed access token'] = hg_mcp_oauth_verify_access_token($token, $secret, $now);
$tests['invalid signing key rejected'] = !hg_mcp_oauth_verify_access_token($token, random_bytes(32), $now);
$tests['expired access token rejected'] = !hg_mcp_oauth_verify_access_token($token, $secret, $now + 3601);
$tests['tampered access token rejected'] = !hg_mcp_oauth_verify_access_token($token . 'x', $secret, $now);
$verifier = hg_mcp_oauth_encode(random_bytes(32));
$context = $valid;
$context['challenge'] = hg_mcp_oauth_encode(hash('sha256', $verifier, true));
$code = hg_mcp_oauth_issue_code($secret, $context, $now);
$tests['code issuance'] = is_string($code);
if (is_string($code)) {
    $redeem = ['client_id'=>HG_MCP_OAUTH_CLIENT,'redirect_uri'=>HG_MCP_OAUTH_REDIRECT,'resource'=>HG_MCP_OAUTH_RESOURCE];
    $tests['wrong verifier rejected'] = !hg_mcp_oauth_redeem_code($secret, $code, str_repeat('Z', 43), $redeem, $now);
    $tests['valid verifier redeems'] = hg_mcp_oauth_redeem_code($secret, $code, $verifier, $redeem, $now);
    $tests['code cannot be replayed'] = !hg_mcp_oauth_redeem_code($secret, $code, $verifier, $redeem, $now);
}
$tests['refresh grant advertised'] = in_array('refresh_token', $as['grant_types_supported'], true);
$refresh = hg_mcp_refresh_issue($secret, $now);
$tests['refresh issued'] = is_string($refresh);
if (is_string($refresh)) {
    $new = hg_mcp_refresh_rotate($secret, $refresh, $now + 10);
    $tests['first refresh rotates token'] = is_string($new) && $new !== $refresh;
    if (is_string($new)) {
        $next = hg_mcp_refresh_rotate($secret, $new, $now + 20);
        $tests['second refresh rotates'] = is_string($next) && $next !== $new;
        $tests['old token replay rejected'] = hg_mcp_refresh_rotate($secret, $refresh, $now + 30) === false;
        $tests['family revoked on replay'] = is_string($next)
            && hg_mcp_refresh_rotate($secret, $next, $now + 40) === false;
    }
}
$expiring = hg_mcp_refresh_issue($secret, $now);
$tests['refresh independently issued'] = is_string($expiring);
if (is_string($expiring)) {
    $tests['refresh absolute expiry'] = hg_mcp_refresh_rotate(
        $secret, $expiring, $now + HG_MCP_REFRESH_LIFETIME + 1
    ) === false;
}
$revoke = hg_mcp_refresh_issue($secret, $now);
$tests['revocation setup'] = is_string($revoke);
if (is_string($revoke)) {
    $tests['explicit refresh revocation'] = hg_mcp_refresh_revoke($secret, $revoke);
    $tests['revoked token denied'] = hg_mcp_refresh_rotate($secret, $revoke, $now + 5) === false;
}
$crossSecret = hg_mcp_refresh_issue($secret, $now);
if (is_string($crossSecret)) {
    $tests['wrong signing secret denied'] = hg_mcp_refresh_rotate(
        random_bytes(32), $crossSecret, $now + 30
    ) === false;
}
foreach ($tests as $name => $ok) {
    if (!$ok) { fwrite(STDERR, 'FAIL: ' . $name . PHP_EOL); exit(1); }
    echo 'PASS: ' . $name . PHP_EOL;
}
