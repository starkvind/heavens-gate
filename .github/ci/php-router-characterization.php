<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap/request_router.php';

function hg_characterization_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function hg_characterization_same(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        hg_characterization_fail(
            $label . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual:   ' . var_export($actual, true)
        );
    }
}

hg_characterization_same('/', hg_request_router_normalize_path(''), 'empty path normalizes to root');
hg_characterization_same('/news', hg_request_router_normalize_path('//news///'), 'duplicate/trailing slashes normalize');
hg_characterization_same('/characters/Jane Doe', hg_request_router_normalize_path('/characters/Jane%20Doe/'), 'encoded path is decoded before normalization');

hg_characterization_same('?q=bruma&page=2', hg_request_router_query(['q' => 'bruma', 'page' => 2]), 'query builder preserves values');
hg_characterization_same('?q=bruma', hg_request_router_query(['p' => 'busk', 'q' => 'bruma'], ['p']), 'query builder excludes legacy route key');
hg_characterization_same('?id=42&palette=dark&msg=9', hg_request_router_forum_embed_query('forum_message', ['id' => 42, 'palette' => 'dark', 'msg' => 9, 'junk' => 'drop']), 'forum message embed keeps only supported query keys');
hg_characterization_same('?id=42', hg_request_router_forum_embed_query('forum_item', ['id' => 42, 'palette' => 'drop']), 'forum item embed keeps only id');

$link = new mysqli();

$directRoutes = [
    'home' => '/home',
    'news' => '/news',
    'biblio' => '/bibliography',
    'busq' => '/search',
    'timeline' => '/timeline',
    'seasons_home' => '/seasons',
    'party' => '/parties',
    'bios' => '/characters/types',
    'list_table' => '/characters',
    'listgroups' => '/organizations',
    'players' => '/players',
    'listadocs' => '/documents',
    'listaobj' => '/inventory',
    'listasistemas' => '/systems',
    'rules' => '/rules',
    'powers' => '/powers',
    'dones' => '/powers/gifts',
    'rites' => '/powers/rites',
    'totems' => '/powers/totems',
    'disciplinas' => '/powers/disciplines',
    'ost' => '/music',
    'gallery' => '/gallery',
    'maps' => '/maps',
    'dados' => '/tools/dice',
    'forum_avatar_tool' => '/tools/forum-avatar',
    'forum_topic_viewer' => '/tools/forum-topic-viewer',
    'tooltip' => '/ajax/tooltip',
    'maps_api' => '/maps/api',
    'dice_api' => '/api/dice',
    'forum_message' => '/forum/message',
    'forum_diceroll' => '/forum/diceroll',
    'forum_item' => '/forum/item',
];

foreach ($directRoutes as $legacy => $canonical) {
    hg_characterization_same(
        $canonical,
        hg_request_router_path_from_query($link, ['p' => $legacy]),
        "legacy route {$legacy} keeps canonical target"
    );
}

hg_characterization_same('/search/results', hg_request_router_path_from_query($link, ['p' => 'busk']), 'search results legacy route target');
hg_characterization_same('/talim', hg_request_router_path_from_query($link, ['p' => 'talim']), 'talim legacy route target');
hg_characterization_same(null, hg_request_router_path_from_query($link, ['p' => '__unknown__']), 'unknown legacy route remains unresolved');
hg_characterization_same(null, hg_request_router_path_from_query($link, []), 'missing legacy route remains unresolved');

fwrite(STDOUT, "PHP router characterization: OK\n");
