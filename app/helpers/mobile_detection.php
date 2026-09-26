<?php

function hg_mobile_view_override(string $requestedView = ''): string
{
    $allowed = ['mobile', 'desktop', 'auto'];
    $requested = strtolower(trim($requestedView));

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

function hg_should_render_mobile(string $routeKey = '', string $requestedView = ''): bool
{
    $routeKey = trim($routeKey);
    if (hg_mobile_is_excluded_route($routeKey)) {
        return false;
    }

    /*
     * Phase 9 keeps the dedicated mobile presentation as an explicit,
     * user-selected view while the normal responsive frontend remains the
     * default. Automatic user-agent splitting stays retired. ?view=mobile
     * and the hg_view cookie select presentation only; routes, data access
     * and application rules remain canonical/shared.
     */
    return hg_mobile_view_override($requestedView) === 'mobile';
}

