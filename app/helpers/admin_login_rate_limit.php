<?php
// File-backed login throttling for the administrative login.
// Uses REMOTE_ADDR only; forwarded headers are intentionally ignored.

if (!function_exists('hg_admin_login_rate_path')) {
    function hg_admin_login_rate_path(): ?string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '' || strlen($ip) > 64) {
            return null;
        }

        $base = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'heavens-gate-admin-rate';
        if (!is_dir($base)) {
            @mkdir($base, 0700, true);
        }
        if (!is_dir($base) || !is_writable($base)) {
            return null;
        }
        @chmod($base, 0700);

        return $base . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
    }
}

if (!function_exists('hg_admin_login_rate_locked_state')) {
    function hg_admin_login_rate_locked_state(callable $mutator): array
    {
        $windowSeconds = 15 * 60;
        $maxFailures = 10;
        $now = time();
        $path = hg_admin_login_rate_path();

        $fallback = [
            'failures' => 0,
            'blocked' => false,
            'retry_after' => 0,
            'delay_us' => 250000,
        ];
        if ($path === null) {
            return $fallback;
        }

        $fh = @fopen($path, 'c+');
        if (!$fh) {
            return $fallback;
        }
        if (!@flock($fh, LOCK_EX)) {
            fclose($fh);
            return $fallback;
        }

        rewind($fh);
        $raw = stream_get_contents($fh);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        $attempts = is_array($decoded['attempts'] ?? null) ? $decoded['attempts'] : [];
        $attempts = array_values(array_filter(array_map('intval', $attempts), static function (int $ts) use ($now, $windowSeconds): bool {
            return $ts > 0 && ($now - $ts) < $windowSeconds;
        }));

        $attempts = $mutator($attempts, $now);
        if (!is_array($attempts)) {
            $attempts = [];
        }
        $attempts = array_values(array_map('intval', $attempts));

        $count = count($attempts);
        $retryAfter = 0;
        if ($count >= $maxFailures && !empty($attempts)) {
            $retryAfter = max(1, $windowSeconds - ($now - min($attempts)));
        }

        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, json_encode(['attempts' => $attempts], JSON_UNESCAPED_SLASHES));
        fflush($fh);
        @flock($fh, LOCK_UN);
        fclose($fh);
        @chmod($path, 0600);

        $delaySteps = max(1, min(8, $count - 1));
        return [
            'failures' => $count,
            'blocked' => $count >= $maxFailures,
            'retry_after' => $retryAfter,
            'delay_us' => min(2000000, 250000 * $delaySteps),
        ];
    }
}

if (!function_exists('hg_admin_login_rate_status')) {
    function hg_admin_login_rate_status(): array
    {
        return hg_admin_login_rate_locked_state(static fn(array $attempts, int $now): array => $attempts);
    }
}

if (!function_exists('hg_admin_login_rate_record_failure')) {
    function hg_admin_login_rate_record_failure(): array
    {
        return hg_admin_login_rate_locked_state(static function (array $attempts, int $now): array {
            $attempts[] = $now;
            return $attempts;
        });
    }
}

if (!function_exists('hg_admin_login_rate_reset')) {
    function hg_admin_login_rate_reset(): void
    {
        $path = hg_admin_login_rate_path();
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }
}
