<?php

if (!function_exists('hg_tool_api_env_candidates')) {
    function hg_tool_api_env_candidates(): array
    {
        return [
            __DIR__ . '/../../../config.env',
            __DIR__ . '/../../config.env',
            __DIR__ . '/../config.env',
        ];
    }
}

if (!function_exists('hg_tool_api_load_env')) {
    function hg_tool_api_load_env(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];
        foreach (hg_tool_api_env_candidates() as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }
            $parsed = parse_ini_file($candidate);
            if (is_array($parsed)) {
                $cache = $parsed;
                break;
            }
        }

        return $cache;
    }
}

if (!function_exists('hg_tool_api_config')) {
    function hg_tool_api_config(string $key, string $fallback = ''): string
    {
        $key = trim($key);
        if ($key === '') {
            return $fallback;
        }

        $envValue = getenv($key);
        if (is_string($envValue) && $envValue !== '') {
            return $envValue;
        }

        $config = hg_tool_api_load_env();
        if (isset($config[$key]) && $config[$key] !== '') {
            return (string)$config[$key];
        }

        return $fallback;
    }
}

if (!function_exists('hg_tool_api_expected_token')) {
    function hg_tool_api_expected_token(): string
    {
        foreach (['HG_TOOL_API_TOKEN', 'TOOL_API_TOKEN', 'FORUM_TOOLS_API_TOKEN'] as $key) {
            $value = hg_tool_api_config($key, '');
            if ($value !== '') {
                return trim($value);
            }
        }
        return '';
    }
}

if (!function_exists('hg_tool_api_send_text')) {
    function hg_tool_api_send_text(string $body, int $status = 200): void
    {
        hg_runtime_send_status($status);
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=UTF-8');
        }
        echo $body;
    }
}

if (!function_exists('hg_tool_api_error')) {
    function hg_tool_api_error(string $message, int $status = 400): void
    {
        hg_tool_api_send_text($message, $status);
    }
}

if (!function_exists('hg_tool_api_require_token')) {
    function hg_tool_api_require_token(string $providedToken): bool
    {
        $expectedToken = hg_tool_api_expected_token();
        if ($expectedToken === '') {
            hg_runtime_log_error('tool_api.token_missing', 'No HG tool API token configured.');
            hg_tool_api_error('API token not configured.', 503);
            return false;
        }

        $providedToken = trim($providedToken);
        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            hg_tool_api_error('Invalid token.', 403);
            return false;
        }

        return true;
    }
}
