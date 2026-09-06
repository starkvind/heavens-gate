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
    ];

    return in_array($routeKey, $excludedRoutes, true);
}

function hg_should_render_mobile(?string $routeKey = null): bool
{
    $routeKey = trim((string)($routeKey ?? ($_GET['p'] ?? '')));
    if (hg_mobile_is_excluded_route($routeKey)) {
        return false;
    }

    /*
     * Phase 9 makes the normal public frontend adaptive. Automatic user-agent
     * splitting is therefore retired: phones and tablets use the same public
     * shell as desktop by default. The old mobile renderer remains available
     * only as an explicit compatibility view through ?view=mobile or an
     * existing hg_view=mobile cookie until its duplicated presentation can be
     * retired deliberately.
     */
    return hg_mobile_view_override() === 'mobile';
}

