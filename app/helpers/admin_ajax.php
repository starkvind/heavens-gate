<?php
// Shared admin AJAX helpers: JSON contract, auth, and CSRF.

if (!function_exists('hg_admin_read_json_payload')) {
    function hg_admin_read_json_payload(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('hg_admin_json_response')) {
    function hg_admin_json_response(array $payload, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('hg_admin_json_success')) {
    function hg_admin_json_success($data = null, string $message = 'OK', array $meta = []): void
    {
        hg_admin_json_response([
            'ok' => true,
            'message' => $message,
            'msg' => $message, // backward compatibility
            'data' => $data,
            'errors' => [],
            'meta' => $meta,
        ], 200);
    }
}

if (!function_exists('hg_admin_json_error')) {
    function hg_admin_json_error(
        string $message,
        int $status = 400,
        array $errors = [],
        $data = null,
        array $meta = []
    ): void {
        hg_admin_json_response([
            'ok' => false,
            'message' => $message,
            'msg' => $message, // backward compatibility
            'data' => $data,
            'errors' => $errors,
            'meta' => $meta,
        ], $status);
    }
}

if (!function_exists('hg_admin_ensure_csrf_token')) {
    function hg_admin_ensure_csrf_token(string $sessionKey = 'csrf_admin_shared'): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION[$sessionKey]) || !is_string($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = bin2hex(random_bytes(16));
        }
        return $_SESSION[$sessionKey];
    }
}

if (!function_exists('hg_admin_extract_csrf_token')) {
    function hg_admin_extract_csrf_token(array $payload = []): string
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || $token === '') {
            $token = (string)($payload['csrf'] ?? ($_POST['csrf'] ?? ''));
        }
        return trim($token);
    }
}

if (!function_exists('hg_admin_csrf_valid')) {
    function hg_admin_csrf_valid(string $token, string $sessionKey = 'csrf_admin_shared'): bool
    {
        if ($token === '') {
            return false;
        }
        $sessionToken = $_SESSION[$sessionKey] ?? '';
        return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
    }
}

if (!function_exists('hg_admin_require_session')) {
    function hg_admin_require_session(bool $jsonOnFail = true): bool
    {
        $ok = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
        if ($ok) {
            return true;
        }
        if ($jsonOnFail) {
            hg_admin_json_error('No autorizado', 403, ['auth' => 'admin_session_required']);
        }
        return false;
    }
}

