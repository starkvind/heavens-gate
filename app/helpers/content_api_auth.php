<?php
/**
 * Read-only API authentication. The test credential is hashed and time-limited.
 * Configure HG_CONTENT_API_TOKEN for production; that overrides test access.
 */
function hg_content_api_authenticate(string $provided, string $configured, int $now): bool
{
    if ($provided === '') return false;
    if ($configured !== '') return hash_equals($configured, $provided);
    $expiresAt = strtotime('2026-11-18T00:00:00+01:00');
    $temporaryDigest = 'eea6db04bb0bf5e74d5876e3b0829e6475dc649b62095b3d628f1ca1ec6d6bb7';
    return $now < $expiresAt && hash_equals($temporaryDigest, hash('sha256', $provided));
}
