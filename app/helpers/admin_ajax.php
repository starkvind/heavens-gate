<?php
// Shared admin AJAX helpers: JSON contract, auth, and CSRF.

include_once(__DIR__ . '/admin_auth.php');
include_once(__DIR__ . '/runtime_response.php');

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
        if (function_exists('hg_admin_session_start')) {
            hg_admin_session_start();
        } elseif (session_status() === PHP_SESSION_NONE) {
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
        $ok = function_exists('hg_admin_is_authenticated')
            ? hg_admin_is_authenticated()
            : (isset($_SESSION['is_admin']) && $_SESSION['is_admin']);
        if ($ok) {
            return true;
        }
        if ($jsonOnFail) {
            hg_admin_json_error('No autorizado', 403, ['auth' => 'admin_session_required']);
        }
        return false;
    }
}

if (!function_exists('hg_admin_is_ajax_request')) {
    function hg_admin_is_ajax_request(): bool
    {
        $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));

        return $xhr
            || ((string)($_GET['ajax'] ?? '') === '1')
            || ((string)($_POST['ajax'] ?? '') === '1')
            || str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json');
    }
}

if (!function_exists('hg_admin_render_error')) {
    function hg_admin_render_error(
        string $title,
        string $message,
        int $status = 500
    ): void {
        if (hg_admin_is_ajax_request()) {
            hg_admin_json_error($message, $status, ['admin' => 'db_unavailable']);
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<section class="hg-admin-state" style="max-width:980px;margin:24px auto;padding:24px 28px;background:#071018;border:1px solid rgba(255,255,255,.12);color:#f4efe7;box-shadow:0 18px 40px rgba(0,0,0,.24)">';
        echo '<h2 style="margin:0 0 10px;font-size:1.6rem">' . hg_runtime_h($title) . '</h2>';
        echo '<p style="margin:0;line-height:1.7;color:#d8d1c6">' . hg_runtime_h($message) . '</p>';
        echo '</section>';
    }
}

if (!function_exists('hg_admin_require_db')) {
    function hg_admin_require_db(
        $link,
        string $title = 'Administracion no disponible',
        string $message = 'No se pudo conectar a la base de datos.'
    ): bool {
        if ($link instanceof mysqli) {
            return true;
        }

        hg_runtime_log_error('admin.db', mysqli_connect_error());
        hg_admin_render_error($title, $message, 500);
        return false;
    }
}
