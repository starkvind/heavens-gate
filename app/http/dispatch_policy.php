<?php

function hg_dispatch_bare_routes(): array
{
    return [
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
    ];
}

function hg_dispatch_resolve(array $routes, string $routeKey): array
{
    if (isset($routes[$routeKey])) {
        $definition = $routes[$routeKey];

        return [
            'file' => (string)($definition[0] ?? ''),
            'section' => $definition[1] ?? null,
            'bare' => in_array($routeKey, hg_dispatch_bare_routes(), true),
        ];
    }

    if ($routeKey === '') {
        return [
            'file' => 'app/controllers/main/main_home.php',
            'section' => 'Inicio',
            'bare' => false,
        ];
    }

    return [
        'file' => 'app/controllers/main/main_news.php',
        'section' => 'Noticias',
        'bare' => false,
    ];
}
