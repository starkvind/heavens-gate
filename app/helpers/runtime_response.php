<?php

if (!function_exists('hg_runtime_h')) {
    function hg_runtime_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_runtime_is_cli')) {
    function hg_runtime_is_cli(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }
}

if (!function_exists('hg_runtime_log_error')) {
    function hg_runtime_log_error(string $context, string $detail = ''): void
    {
        $message = 'HG runtime error [' . $context . ']';
        if ($detail !== '') {
            $message .= ' ' . $detail;
        }
        error_log($message);
    }
}

if (!function_exists('hg_runtime_send_status')) {
    function hg_runtime_send_status(int $status): void
    {
        if (!headers_sent() && !hg_runtime_is_cli()) {
            http_response_code($status);
        }
    }
}

if (!function_exists('hg_runtime_plain_error')) {
    function hg_runtime_plain_error(string $message, int $status = 500): void
    {
        hg_runtime_send_status($status);
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=UTF-8');
        }
        echo $message;
    }
}

if (!function_exists('hg_runtime_bootstrap_error')) {
    function hg_runtime_bootstrap_error(string $message, int $status = 500): void
    {
        if (hg_runtime_is_cli()) {
            hg_runtime_plain_error($message, $status);
            return;
        }

        hg_runtime_send_status($status);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Heaven\'s Gate</title>';
        echo '<style>body{margin:0;font-family:Quicksand,Arial,sans-serif;background:#0b1218;color:#f4efe7;display:grid;min-height:100vh;place-items:center;padding:24px}';
        echo '.hg-runtime-card{max-width:760px;padding:28px 32px;background:rgba(11,18,24,.88);border:1px solid rgba(255,255,255,.12);box-shadow:0 18px 40px rgba(0,0,0,.28)}';
        echo '.hg-runtime-card h1{margin:0 0 10px;font-size:clamp(1.6rem,2vw,2.2rem)}';
        echo '.hg-runtime-card p{margin:0;line-height:1.7;color:#d8d1c6}</style></head><body>';
        echo '<section class="hg-runtime-card"><h1>Servicio no disponible</h1><p>' . hg_runtime_h($message) . '</p></section>';
        echo '</body></html>';
    }
}

if (!function_exists('hg_runtime_embed_error_styles')) {
    function hg_runtime_embed_error_styles(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        echo '<style>';
        echo '.hg-embed-state{font-family:Quicksand,Arial,sans-serif;background:#091118;color:#f4efe7;border:1px solid rgba(255,255,255,.1);padding:18px 20px;max-width:720px;margin:0 auto}';
        echo '.hg-embed-state h1{margin:0 0 8px;font-size:1.25rem}';
        echo '.hg-embed-state p{margin:0;line-height:1.6;color:#d8d1c6}';
        echo '</style>';
    }
}

if (!function_exists('hg_runtime_embed_error')) {
    function hg_runtime_embed_error(string $title, string $message, int $status = 400): void
    {
        hg_runtime_send_status($status);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        hg_runtime_embed_error_styles();
        echo '<section class="hg-embed-state">';
        echo '<h1>' . hg_runtime_h($title) . '</h1>';
        echo '<p>' . hg_runtime_h($message) . '</p>';
        echo '</section>';
    }
}

if (!function_exists('hg_runtime_public_error')) {
    function hg_runtime_public_error(
        string $title,
        string $message,
        int $status = 500,
        bool $includeNav = false
    ): void {
        require_once __DIR__ . '/public_response.php';
        hg_public_render_error($title, $message, $status, $includeNav);
    }
}

if (!function_exists('hg_runtime_require_db')) {
    function hg_runtime_require_db(
        $link,
        string $context,
        string $mode = 'public',
        array $options = []
    ): bool {
        if ($link instanceof mysqli) {
            return true;
        }

        $title = (string)($options['title'] ?? 'Servicio no disponible');
        $message = (string)($options['message'] ?? 'No se pudo conectar a la base de datos.');
        $status = (int)($options['status'] ?? 500);
        $includeNav = (bool)($options['include_nav'] ?? false);
        $detail = mysqli_connect_error();
        $detail = is_string($detail) ? $detail : '';

        hg_runtime_log_error($context, $detail);

        switch ($mode) {
            case 'embed':
                hg_runtime_embed_error($title, $message, $status);
                break;
            case 'plain':
                hg_runtime_plain_error($message, $status);
                break;
            case 'bootstrap':
                hg_runtime_bootstrap_error($message, $status);
                break;
            case 'public':
            default:
                hg_runtime_public_error($title, $message, $status, $includeNav);
                break;
        }

        return false;
    }
}
