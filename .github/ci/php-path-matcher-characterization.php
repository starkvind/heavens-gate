<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/routing/request_runtime.php';

function hg_path_matcher_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function hg_path_matcher_same(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        hg_path_matcher_fail(
            $label . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual:   ' . var_export($actual, true)
        );
    }
}

$link = new mysqli();

$paths = [
    '/home',
    '/news',
    '/seasons',
    '/seasons/season-one',
    '/chapters/chapter-one',
    '/characters',
    '/characters/type/garou',
    '/characters/bruma-nocturna',
    '/chronicles/heavens-gate',
    '/organizations/justicia-metalica',
    '/groups/justicia-metalica/angeles-de-gaia',
    '/players/maurick',
    '/documents/ejemplo',
    '/inventory/type/artefactos',
    '/inventory/artefactos/objeto',
    '/systems/garou',
    '/systems/tribes/uktena',
    '/rules/traits/fuerza',
    '/powers/gift/sentir-al-wyrm',
    '/powers/rite/rito-ejemplo',
    '/powers/totem/unicornio',
    '/powers/discipline/celeridad',
    '/maps/poi/bilbao',
    '/forum/message',
    '/api/dice',
    '/tools/forum-topic-viewer',
    '/games/combat-simulator',
    '/games/card-game',
    '/does-not-exist',
];

foreach ($paths as $path) {
    hg_path_matcher_same(
        hg_request_router_match_path($link, $path),
        hg_request_path_matcher_match($path),
        "pure matcher preserves legacy matcher contract for {$path}"
    );
}

$redirectPaths = [
    '/index.php',
    '/crop.html',
    '/characters/chronicles',
    '/characters/chronicles/main',
    '/inventory/item/test-item',
    '/inventory/items',
    '/inventory/type',
    '/inventory/weapons',
];

foreach ($redirectPaths as $path) {
    hg_path_matcher_same(
        hg_request_router_match_path($link, $path),
        hg_request_path_matcher_match($path),
        "pure matcher preserves redirect contract for {$path}"
    );
}

hg_path_matcher_same(
    ['action' => 'route', 'params' => ['p' => 'muestrabio', 'b' => 'bruma-nocturna']],
    hg_request_routing_resolve($link, '/characters/bruma-nocturna', []),
    'runtime routes canonical pretty paths through pure matcher'
);

hg_path_matcher_same(
    ['action' => 'noop'],
    hg_request_routing_resolve($link, '/', []),
    'runtime keeps root request as noop for home fallback'
);

$matcherSource = file_get_contents(__DIR__ . '/../../app/routing/path_matcher.php');
if ($matcherSource === false) {
    hg_path_matcher_fail('Cannot read path matcher source');
}

foreach (['mysqli', '$_GET', '$_POST', '->prepare(', 'mysqli_query'] as $forbidden) {
    if (strpos($matcherSource, $forbidden) !== false) {
        hg_path_matcher_fail("Pure path matcher gained forbidden runtime/data dependency: {$forbidden}");
    }
}

$indexSource = file_get_contents(__DIR__ . '/../../index.php');
if ($indexSource === false
    || strpos($indexSource, 'app/routing/request_runtime.php') === false
    || strpos($indexSource, '$hgQuery = hg_request_routing_bootstrap($link, $uri, $_GET);') === false) {
    hg_path_matcher_fail('index.php is not using the extracted request runtime');
}

fwrite(STDOUT, "PHP path matcher characterization: OK\n");
