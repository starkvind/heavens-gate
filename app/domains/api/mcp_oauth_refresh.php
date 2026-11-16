<?php
/**
 * Persistent rotating OAuth refresh credentials for the single approved MCP client.
 *
 * Storage is scoped to the OAuth signing secret and held outside the webroot,
 * with 0700 directory and 0600 files. A refresh family has a 90-day absolute
 * lifetime; rotation does not extend it. Replay revokes the whole family.
 *
 * No refresh token or secret value is written to disk in clear text.
 */
const HG_MCP_REFRESH_LIFETIME = 90 * 86400;

function hg_mcp_refresh_context(): array
{
    return [
        'client_id' => HG_MCP_OAUTH_CLIENT,
        'resource' => HG_MCP_OAUTH_RESOURCE,
        'scope' => HG_MCP_OAUTH_SCOPE,
    ];
}

function hg_mcp_refresh_digest(string $secret, string $family, string $key): string
{
    return hash_hmac('sha256', "refresh\0" . $family . "\0" . $key, $secret);
}

/** Returns a random one-use token and persists only its HMAC digest. */
function hg_mcp_refresh_issue(string $secret, int $now): string|false
{
    $dir = hg_mcp_oauth_code_dir($secret);
    if (!is_string($dir)) return false;

    $family = bin2hex(random_bytes(16));
    $key = hg_mcp_oauth_encode(random_bytes(32));
    $token = 'r1.' . $family . '.' . $key;
    $file = $dir . '/r-' . $family . '.json';
    $record = hg_mcp_refresh_context() + [
        'digest' => hg_mcp_refresh_digest($secret, $family, $key),
        'expires_at' => $now + HG_MCP_REFRESH_LIFETIME,
        'revoked' => false,
    ];
    $payload = json_encode($record, JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) return false;
    $handle = @fopen($file, 'x');
    if ($handle === false) return false;
    @chmod($file, 0600);
    $written = fwrite($handle, $payload);
    $flushed = fflush($handle);
    fclose($handle);
    if ($written !== strlen($payload) || !$flushed) {
        @unlink($file);
        return false;
    }
    return $token;
}

/**
 * Atomically consumes a refresh credential and replaces it with a new one.
 * Returns false for invalid/expired/replayed tokens (no access token issued).
 */
function hg_mcp_refresh_rotate(string $secret, string $token, int $now): string|false
{
    if ($secret === ''
        || !preg_match('/^r1\.([a-f0-9]{32})\.([A-Za-z0-9_-]{43})$/D', $token, $parts)) {
        return false;
    }
    $dir = hg_mcp_oauth_code_dir($secret);
    if (!is_string($dir)) return false;
    $family = $parts[1];
    $file = $dir . '/r-' . $family . '.json';
    $handle = @fopen($file, 'r+');
    if ($handle === false) return false;
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return false;
    }
    // A family file is deliberately not unlinked during rotation or revocation,
    // so other processes can always lock the same inode.
    rewind($handle);
    $raw = stream_get_contents($handle, 8192);
    $record = is_string($raw) ? json_decode($raw, true) : null;
    $context = hg_mcp_refresh_context();
    $validRecord = is_array($record)
        && ($record['client_id'] ?? null) === $context['client_id']
        && ($record['resource'] ?? null) === $context['resource']
        && ($record['scope'] ?? null) === $context['scope']
        && is_int($record['expires_at'] ?? null)
        && is_string($record['digest'] ?? null)
        && preg_match('/^[a-f0-9]{64}$/D', $record['digest']);
    if (!$validRecord || ($record['revoked'] ?? false) === true
        || $record['expires_at'] <= $now) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }

    $receivedDigest = hg_mcp_refresh_digest($secret, $family, $parts[2]);
    $matches = hash_equals($record['digest'], $receivedDigest);
    if ($matches) {
        $key = hg_mcp_oauth_encode(random_bytes(32));
        $record['digest'] = hg_mcp_refresh_digest($secret, $family, $key);
        $replacement = 'r1.' . $family . '.' . $key;
    } else {
        // Replay or unrecognized token for an existing family: revoke all
        // descendants, so the stolen credential cannot be used repeatedly.
        $record['revoked'] = true;
        $replacement = false;
    }

    $payload = json_encode($record, JSON_UNESCAPED_SLASHES);
    $saved = is_string($payload)
        && rewind($handle) !== false
        && ftruncate($handle, 0)
        && fwrite($handle, $payload) === strlen($payload)
        && fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $saved ? $replacement : false;
}
