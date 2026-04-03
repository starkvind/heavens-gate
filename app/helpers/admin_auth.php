<?php
// Shared admin session/auth helpers.

if (!function_exists('hg_admin_is_https')) {
    function hg_admin_is_https(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

        return ($https !== '' && strtolower((string)$https) !== 'off')
            || strtolower((string)$forwardedProto) === 'https';
    }
}

if (!function_exists('hg_admin_session_start')) {
    function hg_admin_session_start(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (headers_sent()) {
            return false;
        }

        ini_set('session.use_strict_mode', '1');
        $params = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => (int)($params['lifetime'] ?? 0),
            'path' => (string)($params['path'] ?? '/'),
            'domain' => (string)($params['domain'] ?? ''),
            'secure' => hg_admin_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return session_start();
    }
}

if (!function_exists('hg_admin_is_authenticated')) {
    function hg_admin_is_authenticated(): bool
    {
        if (!hg_admin_session_start()) {
            return false;
        }

        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
}

if (!function_exists('hg_admin_mark_authenticated')) {
    function hg_admin_mark_authenticated(): void
    {
        if (!hg_admin_session_start()) {
            return;
        }

        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_logged_in_at'] = time();
    }
}

if (!function_exists('hg_admin_redirect')) {
    function hg_admin_redirect(string $path = '/talim'): void
    {
        if (!headers_sent()) {
            header('Location: ' . $path);
        }
        exit;
    }
}

if (!function_exists('hg_admin_logout')) {
    function hg_admin_logout(string $redirectPath = '/talim'): void
    {
        hg_admin_session_start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => (string)($params['path'] ?? '/'),
                'domain' => (string)($params['domain'] ?? ''),
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        hg_admin_redirect($redirectPath);
    }
}
