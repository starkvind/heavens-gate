<?php

if (isset($routes[$routeKey])) {
    [$file, $sect] = $routes[$routeKey];
    if ($sect) {
        $pageSect = $sect;
    }

    if (in_array($routeKey, [
        'snippet_forum_a',
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
    ], true)) {
        $isBarePage = true;
    }

    include($file);
} else {
    if ($routeKey === '') {
        $pageSect = 'Inicio';
        include('app/controllers/main/main_home.php');
    } else {
        $pageSect = 'Noticias';
        include('app/controllers/main/main_news.php');
    }
}
