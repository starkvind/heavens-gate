<?php

if (!function_exists('hg_public_h')) {
    function hg_public_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_public_log_error')) {
    function hg_public_log_error(string $context, string $detail = ''): void
    {
        $message = 'HG public error [' . $context . ']';
        if ($detail !== '') {
            $message .= ' ' . $detail;
        }
        error_log($message);
    }
}

if (!function_exists('hg_public_render_state_styles')) {
    function hg_public_render_state_styles(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        echo <<<HTML
<style>
.hg-public-state{
    max-width: 860px;
    margin: 24px auto;
    padding: 24px 28px;
    border: 1px solid rgba(255,255,255,.14);
    background: rgba(11,18,24,.82);
    color: #f4efe7;
    box-shadow: 0 18px 40px rgba(0,0,0,.24);
}
.hg-public-state h1{
    margin: 0 0 10px 0;
    font-size: clamp(1.5rem, 2vw, 2.1rem);
    line-height: 1.2;
}
.hg-public-state p{
    margin: 0;
    color: #d8d1c6;
    line-height: 1.7;
}
</style>
HTML;
    }
}

if (!function_exists('hg_public_render_error')) {
    function hg_public_render_error(
        string $title,
        string $message,
        int $status = 500,
        bool $includeNav = false
    ): void {
        global $pageSect, $pageTitle2;

        if (!headers_sent()) {
            http_response_code($status);
        }

        $pageSect = ($status === 404) ? '404' : 'Aviso';
        $pageTitle2 = $title;

        if (function_exists('setMetaFromPage')) {
            setMetaFromPage($title . " | Heaven's Gate", $message, null, 'website');
        }

        if ($includeNav) {
            include(__DIR__ . '/../partials/main_nav_bar.php');
        }

        hg_public_render_state_styles();

        echo '<section class="hg-public-state">';
        echo '<h1>' . hg_public_h($title) . '</h1>';
        echo '<p>' . hg_public_h($message) . '</p>';
        echo '</section>';
    }
}

if (!function_exists('hg_public_render_not_found')) {
    function hg_public_render_not_found(
        string $title,
        string $message,
        bool $includeNav = false
    ): void {
        hg_public_render_error($title, $message, 404, $includeNav);
    }
}
