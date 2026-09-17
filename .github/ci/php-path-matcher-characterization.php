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

$routeCases = [
    '/home' => ['p' => 'home'],
    '/seasons/season-one' => ['p' => 'temp', 't' => 'season-one'],
    '/chapters/chapter-one' => ['p' => 'seechapter', 't' => 'chapter-one'],
    '/characters/bruma-nocturna' => ['p' => 'muestrabio', 'b' => 'bruma-nocturna'],
    '/organizations/justicia-metalica' => ['p' => 'seegroup', 't' => '2', 'b' => 'justicia-metalica'],
    '/groups/justicia-metalica/angeles-de-gaia' => ['p' => 'seegroup', 't' => '1', 'org' => 'justicia-metalica', 'b' => 'angeles-de-gaia'],
    '/inventory/artefactos/objeto' => ['p' => 'verobj', 't' => 'artefactos', 'b' => 'objeto'],
    '/systems/tribes/uktena' => ['p' => 'versistdetalle', 'tc' => '3', 'b' => 'uktena'],
    '/powers/gift/sentir-al-wyrm' => ['p' => 'muestradon', 'b' => 'sentir-al-wyrm'],
    '/timeline/event/caida-bilbao' => ['p' => 'timeline_event', 't' => 'caida-bilbao'],
    '/maps/poi/bilbao' => ['p' => 'maps_detail', 'id' => 'bilbao'],
    '/forum/message' => ['p' => 'forum_message'],
    '/api/dice' => ['p' => 'dice_api'],
    '/tools/forum-topic-viewer' => ['p' => 'forum_topic_viewer'],
    '/does-not-exist' => ['p' => 'error404'],
];

foreach ($routeCases as $path => $params) {
    hg_path_matcher_same(
        ['action' => 'route', 'params' => $params],
        hg_request_path_matcher_match($path),
        "pure matcher route contract for {$path}"
    );
}

$redirectCases = [
    '/index.php' => '/',
    '/crop.html' => '/tools/crop',
    '/characters/chronicles' => '/chronicles',
    '/characters/chronicles/main' => '/chronicles/main',
    '/inventory/item/test-item' => '/inventory/items/test-item',
    '/inventory/items' => '/inventory',
    '/inventory/type' => '/inventory',
    '/inventory/weapons' => '/inventory/type/weapons',
];

foreach ($redirectCases as $path => $location) {
    hg_path_matcher_same(
        ['action' => 'redirect', 'location' => $location, 'status' => 301],
        hg_request_path_matcher_match($path),
        "pure matcher redirect contract for {$path}"
    );
}

hg_path_matcher_same(
    ['action' => 'route', 'params' => ['p' => 'muestrabio', 'b' => 'bruma-nocturna']],
    hg_request_routing_resolve($link, '/characters/bruma-nocturna', [], 'GET'),
    'runtime routes canonical pretty paths through pure matcher'
);

hg_path_matcher_same(
    ['action' => 'noop'],
    hg_request_routing_resolve($link, '/', [], 'GET'),
    'runtime keeps root request as noop for home fallback'
);

hg_path_matcher_same(
    ['action' => 'redirect', 'location' => '/home', 'status' => 301],
    hg_request_routing_resolve($link, '/?p=home', ['p' => 'home'], 'GET'),
    'safe legacy query canonicalizes through compatibility layer'
);

hg_path_matcher_same(
    ['action' => 'noop'],
    hg_request_routing_resolve($link, '/?p=home', ['p' => 'home'], 'POST'),
    'non-safe legacy query is not redirected'
);

hg_path_matcher_same(
    ['action' => 'redirect', 'location' => '/forum/message?id=42&palette=dark&msg=9', 'status' => 301],
    hg_request_routing_resolve(
        $link,
        '/sep/snippet_forum_hg.php?id=42&palette=dark&msg=9&junk=drop',
        ['id' => '42', 'palette' => 'dark', 'msg' => '9', 'junk' => 'drop'],
        'GET'
    ),
    'historical forum snippet redirects with only supported query keys'
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

$normalizationSource = file_get_contents(__DIR__ . '/../../app/routing/path_normalization.php');
if ($normalizationSource === false) {
    hg_path_matcher_fail('Cannot read path normalization source');
}
foreach (['mysqli', '$_GET', '$_POST', '$_REQUEST', '$_SERVER'] as $forbidden) {
    if (strpos($normalizationSource, $forbidden) !== false) {
        hg_path_matcher_fail("Path normalization gained forbidden runtime/data dependency: {$forbidden}");
    }
}

$runtimeSource = file_get_contents(__DIR__ . '/../../app/routing/request_runtime.php');
if ($runtimeSource === false) {
    hg_path_matcher_fail('Cannot read request runtime');
}
foreach (['$_GET', '$_POST', '$_REQUEST', '$_SERVER'] as $forbidden) {
    if (strpos($runtimeSource, $forbidden) !== false) {
        hg_path_matcher_fail("Request runtime regained raw transport dependency: {$forbidden}");
    }
}

$indexSource = file_get_contents(__DIR__ . '/../../index.php');
if ($indexSource === false
    || strpos($indexSource, 'app/routing/request_runtime.php') === false
    || strpos($indexSource, '$method = (string)($_SERVER[\'REQUEST_METHOD\'] ?? \'GET\');') === false
    || strpos($indexSource, '$hgQuery = hg_request_routing_bootstrap($link, $uri, $_GET, $method);') === false) {
    hg_path_matcher_fail('index.php is not using the explicit request runtime edge');
}

fwrite(STDOUT, "PHP path matcher characterization: OK\n");
