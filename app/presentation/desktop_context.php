<?php

function hg_desktop_theme_class(array $request): string
{
    $allowedThemes = ['classic', 'modern', 'power-save'];
    $requestedTheme = strtolower(hg_request_query_param($request, 'theme'));

    if ($requestedTheme !== '' && in_array($requestedTheme, $allowedThemes, true)) {
        setcookie('hg_theme', $requestedTheme, time() + 31536000, '/');
        $_COOKIE['hg_theme'] = $requestedTheme;
    }

    $activeTheme = isset($_COOKIE['hg_theme']) ? strtolower((string)$_COOKIE['hg_theme']) : 'classic';
    if (!in_array($activeTheme, $allowedThemes, true)) {
        $activeTheme = 'classic';
    }

    return 'theme-' . $activeTheme;
}

function hg_view_switch_url(string $requestUri, string $view): string
{
    $path = (string)(parse_url($requestUri, PHP_URL_PATH) ?: '/');
    $query = [];
    parse_str((string)(parse_url($requestUri, PHP_URL_QUERY) ?? ''), $query);
    $query['view'] = $view;

    return $path . '?' . http_build_query($query);
}
