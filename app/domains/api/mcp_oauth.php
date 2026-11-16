<?php
/**
 * Private, single-administrator OAuth 2.1 authorization-code + PKCE S256 for the
 * Heaven's Gate MCP resource only. This does NOT replace /talim authentication.
 *
 * Disabled unless HG_MCP_OAUTH_SECRET is explicitly configured outside Git.
 * Never use the MariaDB password, staging API token or the app code as signing key.
 */
const HG_MCP_OAUTH_ISSUER = 'https://naufragio-heavensgate.duckdns.org';
const HG_MCP_OAUTH_RESOURCE = HG_MCP_OAUTH_ISSUER . '/mcp';
const HG_MCP_OAUTH_REDIRECT = 'https://chatgpt.com/connector_platform_oauth_redirect';
const HG_MCP_OAUTH_CLIENT = 'https://chatgpt.com/oauth/client.json';
const HG_MCP_OAUTH_SCOPE = 'content:read';

function hg_mcp_oauth_secret(): string
{
    $hex = trim(hg_tool_api_config('HG_MCP_OAUTH_SECRET'));
    if (!preg_match('/^[0-9a-fA-F]{64,}$/D', $hex) || strlen($hex) % 2 !== 0) return '';
    $secret = hex2bin($hex);
    return is_string($secret) && strlen($secret) >= 32 ? $secret : '';
}

function hg_mcp_oauth_encode(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function hg_mcp_oauth_decode(string $encoded): string|false
{
    if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $encoded)) return false;
    return base64_decode(strtr($encoded, '-_', '+/'), true);
}

function hg_mcp_oauth_metadata(string $kind): array
{
    if ($kind === 'protected') return [
        'resource' => HG_MCP_OAUTH_RESOURCE,
        'authorization_servers' => [HG_MCP_OAUTH_ISSUER],
        'scopes_supported' => [HG_MCP_OAUTH_SCOPE],
        'bearer_methods_supported' => ['header'],
    ];
    return [
        'issuer' => HG_MCP_OAUTH_ISSUER,
        'authorization_endpoint' => HG_MCP_OAUTH_ISSUER . '/oauth/authorize',
        'token_endpoint' => HG_MCP_OAUTH_ISSUER . '/oauth/token',
        'response_types_supported' => ['code'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'code_challenge_methods_supported' => ['S256'],
        'token_endpoint_auth_methods_supported' => ['none'],
        'client_id_metadata_document_supported' => true,
        'scopes_supported' => [HG_MCP_OAUTH_SCOPE],
        'authorization_response_iss_parameter_supported' => true,
    ];
}

/** ChatGPT's fixed CIMD identity and issuer-aware callback, no open redirects. */
function hg_mcp_oauth_authorization_params(array $params): ?array
{
    if (($params['response_type'] ?? '') !== 'code'
        || ($params['client_id'] ?? '') !== HG_MCP_OAUTH_CLIENT
        || ($params['redirect_uri'] ?? '') !== HG_MCP_OAUTH_REDIRECT
        || ($params['code_challenge_method'] ?? '') !== 'S256'
        || !preg_match('/^[a-zA-Z0-9_-]{43,128}$/D', (string)($params['code_challenge'] ?? ''))
        || ($params['resource'] ?? '') !== HG_MCP_OAUTH_RESOURCE
        || !in_array(($params['scope'] ?? ''), ['', HG_MCP_OAUTH_SCOPE], true)
        || strlen((string)($params['state'] ?? '')) > 1024) {
        return null;
    }
    return [
        'client_id' => HG_MCP_OAUTH_CLIENT,
        'redirect_uri' => HG_MCP_OAUTH_REDIRECT,
        'challenge' => (string)$params['code_challenge'],
        'resource' => HG_MCP_OAUTH_RESOURCE,
        'scope' => HG_MCP_OAUTH_SCOPE,
        'state' => (string)($params['state'] ?? ''),
    ];
}

/**
 * php-fpm normally already has a writable tmp area for sessions.
 * This isolated 0700 directory holds *only* one-time codes for 5 minutes.
 * If storage is unavailable, authorization fails closed (never bypasses auth).
 */
function hg_mcp_oauth_code_dir(string $secret): string|false
{
    if ($secret === '') return false;
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'hg-mcp-oauth-' . substr(hash('sha256', $secret), 0, 24);
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return false;
    $mode = @fileperms($dir);
    if (!is_int($mode) || ($mode & 0077) !== 0 || !is_writable($dir)) return false;
    return $dir;
}

function hg_mcp_oauth_issue_code(string $secret, array $context, int $now): string|false
{
    $dir = hg_mcp_oauth_code_dir($secret);
    if (!is_string($dir)) return false;
    $code = hg_mcp_oauth_encode(random_bytes(32));
    $file = $dir . '/c-' . hash('sha256', $code) . '.json';
    $value = json_encode([
        'client_id' => $context['client_id'], 'redirect_uri' => $context['redirect_uri'],
        'challenge' => $context['challenge'], 'resource' => $context['resource'],
        'scope' => $context['scope'], 'expires_at' => $now + 300,
    ], JSON_UNESCAPED_SLASHES);
    if (!is_string($value)) return false;
    $handle = @fopen($file, 'x');
    if ($handle === false) return false;
    @chmod($file, 0600);
    $written = fwrite($handle, $value);
    fclose($handle);
    if ($written !== strlen($value)) { @unlink($file); return false; }
    return $code;
}

function hg_mcp_oauth_redeem_code(string $secret, string $code, string $verifier, array $context, int $now): bool
{
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $code)
        || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', $verifier)) return false;
    $dir = hg_mcp_oauth_code_dir($secret);
    if (!is_string($dir)) return false;
    $file = $dir . '/c-' . hash('sha256', $code) . '.json';
    $handle = @fopen($file, 'r+');
    if ($handle === false) return false;
    if (!flock($handle, LOCK_EX)) { fclose($handle); return false; }
    // A concurrent redeemer may have opened the file before it was removed.
    if (!file_exists($file)) { flock($handle, LOCK_UN); fclose($handle); return false; }
    $record = json_decode(stream_get_contents($handle), true);
    $challenge = hg_mcp_oauth_encode(hash('sha256', $verifier, true));
    $valid = is_array($record)
        && (int)($record['expires_at'] ?? 0) > $now
        && hash_equals((string)($record['challenge'] ?? ''), $challenge)
        && ($record['client_id'] ?? '') === ($context['client_id'] ?? '')
        && ($record['redirect_uri'] ?? '') === ($context['redirect_uri'] ?? '')
        && ($record['resource'] ?? '') === ($context['resource'] ?? '')
        && ($record['scope'] ?? '') === HG_MCP_OAUTH_SCOPE;
    if ($valid || (is_array($record) && (int)($record['expires_at'] ?? 0) <= $now)) @unlink($file);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $valid;
}

/** HMAC-signed, resource/audience-bound bearer; short-lived and no token passthrough. */
function hg_mcp_oauth_issue_access_token(string $secret, int $now): string
{
    $claims = [
        'iss' => HG_MCP_OAUTH_ISSUER, 'aud' => HG_MCP_OAUTH_RESOURCE,
        'sub' => 'heavens-gate-admin', 'scope' => HG_MCP_OAUTH_SCOPE,
        'iat' => $now, 'exp' => $now + 3600,
        'jti' => hg_mcp_oauth_encode(random_bytes(16)),
    ];
    $body = hg_mcp_oauth_encode((string)json_encode($claims, JSON_UNESCAPED_SLASHES));
    return $body . '.' . hg_mcp_oauth_encode(hash_hmac('sha256', $body, $secret, true));
}

function hg_mcp_oauth_verify_access_token(string $token, string $secret, int $now): bool
{
    if ($secret === '' || strlen($token) > 4096 || substr_count($token, '.') !== 1) return false;
    [$body, $sig] = explode('.', $token, 2);
    $signature = hg_mcp_oauth_decode($sig);
    if (!is_string($signature) || strlen($signature) !== 32
        || !hash_equals(hash_hmac('sha256', $body, $secret, true), $signature)) return false;
    $json = hg_mcp_oauth_decode($body);
    $claims = is_string($json) ? json_decode($json, true) : null;
    return is_array($claims)
        && ($claims['iss'] ?? '') === HG_MCP_OAUTH_ISSUER
        && ($claims['aud'] ?? '') === HG_MCP_OAUTH_RESOURCE
        && ($claims['sub'] ?? '') === 'heavens-gate-admin'
        && ($claims['scope'] ?? '') === HG_MCP_OAUTH_SCOPE
        && is_int($claims['exp'] ?? null) && $claims['exp'] > $now
        && is_int($claims['iat'] ?? null) && $claims['iat'] <= $now + 30;
}
