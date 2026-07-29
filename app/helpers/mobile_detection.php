<?php

function hg_mobile_view_override(): string
{
    $allowed = ['mobile', 'desktop', 'auto'];
    $requested = isset($_GET['view']) ? strtolower(trim((string)$_GET['view'])) : '';

    if ($requested !== '' && in_array($requested, $allowed, true)) {
        setcookie('hg_view', $requested, time() + 31536000, '/');
        $_COOKIE['hg_view'] = $requested;
        return $requested;
    }

    $cookie = isset($_COOKIE['hg_view']) ? strtolower(trim((string)$_COOKIE['hg_view'])) : '';
    return in_array($cookie, $allowed, true) ? $cookie : 'auto';
}

function hg_mobile_is_excluded_route(string $routeKey): bool
{
    if ($routeKey === '' || $routeKey === 'home') {
        return false;
    }

    $excludedRoutes = [
        'talim',
        'forum_message',
        'forum_diceroll',
        'forum_item',
        'keygen',
        'crop',
        'tooltip',
        'mentions',
        'maps_api',
        'dice_api',
        'forum_avatar_api',
        'chronicle_image',
        'season_order',
        'nebula_clan',
        'nebula_character',
        'nebula_groups',
        'org_chart',
        'schema_sanitizer',
    ];

    return in_array($routeKey, $excludedRoutes, true);
}

function hg_mobile_is_smartphone_user_agent(string $userAgent): bool
{
    $ua = strtolower($userAgent);
    if ($ua === '') {
        return false;
    }

    $tabletSignals = [
        'ipad',
        'tablet',
        'kindle',
        'silk/',
        'playbook',
    ];

    foreach ($tabletSignals as $signal) {
        if (strpos($ua, $signal) !== false) {
            return false;
        }
    }

    if (strpos($ua, 'android') !== false && strpos($ua, 'mobile') === false) {
        return false;
    }

    $phoneSignals = [
        'iphone',
        'ipod',
        'android',
        'mobile',
        'windows phone',
        'blackberry',
        'opera mini',
        'opera mobi',
    ];

    foreach ($phoneSignals as $signal) {
        if (strpos($ua, $signal) !== false) {
            return true;
        }
    }

    return false;
}

function hg_should_render_mobile(?string $routeKey = null): bool
{
    $routeKey = trim((string)($routeKey ?? ($_GET['p'] ?? '')));
    if (hg_mobile_is_excluded_route($routeKey)) {
        return false;
    }

    $override = hg_mobile_view_override();
    if ($override === 'mobile') {
        return true;
    }
    if ($override === 'desktop') {
        return false;
    }

    return hg_mobile_is_smartphone_user_agent((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

