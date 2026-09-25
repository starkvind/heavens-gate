<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routesPath = $root . '/app/routing/routes.php';
$dispatcherPath = $root . '/app/http/dispatcher.php';
$dispatchPolicyPath = $root . '/app/http/dispatch_policy.php';
$pageDispatchPath = $root . '/app/http/page_dispatch.php';
$mobileFallbackPath = $root . '/app/mobile/controllers/fallback.php';
$indexPath = $root . '/index.php';

function hg_dispatch_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$routes = require $routesPath;
if (!is_array($routes) || count($routes) < 70) {
    hg_dispatch_fail('Dispatch route table unexpectedly small: ' . (is_array($routes) ? count($routes) : 0));
}

$expected = [
    'home' => 'app/controllers/main/main_home.php',
    'news' => 'app/controllers/main/main_news.php',
    'biblio' => 'app/controllers/main/main_biblio.php',
    'talim' => 'app/controllers/admin/admin_main.php',
    'seasons_home' => 'app/controllers/chapters/seasons_home.php',
    'temp' => 'app/controllers/chapters/season_archive.php',
    'seechapter' => 'app/controllers/chapters/chapter_page.php',
    'muestrabio' => 'app/controllers/bio/bio_page.php',
    'seegroup' => 'app/controllers/bio/bio_pack_page.php',
    'listadocs' => 'app/controllers/docs/docs_table.php',
    'verdoc' => 'app/controllers/docs/docs_page.php',
    'listasistemas' => 'app/controllers/systems/systems_table.php',
    'sistemas' => 'app/controllers/systems/system_overview_page.php',
    'powers' => 'app/controllers/pwrs/powers_home.php',
    'muestradon' => 'app/controllers/pwrs/don_page.php',
    'dados' => 'app/controllers/tool/dice_roller.php',
    'dice_api' => 'app/controllers/tool/dice_api.php',
    'maps' => 'app/controllers/maps/maps_main.php',
    'maps_api' => 'app/controllers/maps/maps_api.php',
    'players' => 'app/controllers/playr/playr_list.php',
    'seeplayer' => 'app/controllers/playr/playr_page.php',
    'forum_message' => 'app/partials/forum_message_snippet.php',
];

foreach ($expected as $route => $file) {
    if (($routes[$route][0] ?? null) !== $file) {
        hg_dispatch_fail("Dispatch mapping changed for {$route}: expected {$file}, got " . ($routes[$route][0] ?? '<missing>'));
    }
}

foreach ($routes as $route => $definition) {
    $file = $definition[0] ?? null;
    if (!is_string($file) || !is_file($root . '/' . $file)) {
        hg_dispatch_fail("Dispatch target missing for {$route}: " . (string)$file);
    }
}

require_once $dispatchPolicyPath;

foreach ([
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
] as $bareRoute) {
    $resolved = hg_dispatch_resolve($routes, $bareRoute);
    if (empty($resolved['bare'])) {
        hg_dispatch_fail("Bare-page contract changed: missing {$bareRoute}");
    }
}

$homeFallback = hg_dispatch_resolve($routes, '');
if (($homeFallback['file'] ?? null) !== 'app/controllers/main/main_home.php' || ($homeFallback['section'] ?? null) !== 'Inicio') {
    hg_dispatch_fail('Empty-route home fallback changed');
}

$newsFallback = hg_dispatch_resolve($routes, '__unknown_route__');
if (($newsFallback['file'] ?? null) !== 'app/controllers/main/main_news.php' || ($newsFallback['section'] ?? null) !== 'Noticias') {
    hg_dispatch_fail('Unknown-route news fallback changed');
}

$dispatcher = file_get_contents($dispatcherPath);
if ($dispatcher === false) {
    hg_dispatch_fail('Cannot read dispatcher.php');
}
foreach ([
    "require_once __DIR__ . '/dispatch_policy.php'",
    'hg_dispatch_resolve($routes, $routeKey)',
    'include $file',
] as $needle) {
    if (strpos($dispatcher, $needle) === false) {
        hg_dispatch_fail("Dispatcher orchestration seam missing: {$needle}");
    }
}

$pageDispatch = file_get_contents($pageDispatchPath);
if ($pageDispatch === false) {
    hg_dispatch_fail('Cannot read page_dispatch.php');
}
foreach ([
    "require __DIR__ . '/page_context.php'",
    "require __DIR__ . '/../routing/routes.php'",
    "require __DIR__ . '/dispatcher.php'",
] as $needle) {
    if (strpos($pageDispatch, $needle) === false) {
        hg_dispatch_fail("Page dispatch seam missing: {$needle}");
    }
}

$index = file_get_contents($indexPath);
if ($index === false) {
    hg_dispatch_fail('Cannot read index.php');
}
foreach ([
    "require_once __DIR__ . '/app/http/output.php'",
    "require_once __DIR__ . '/app/bootstrap/runtime.php'",
    "require_once __DIR__ . '/app/routing/request_runtime.php'",
    "include __DIR__ . '/app/http/page_dispatch.php'",
    "require_once __DIR__ . '/app/presentation/desktop_context.php'",
    "include __DIR__ . '/app/views/layout/desktop.php'",
] as $needle) {
    if (strpos($index, $needle) === false) {
        hg_dispatch_fail("Front-controller seam missing: {$needle}");
    }
}
if (strpos($index, '<!DOCTYPE html>') !== false) {
    hg_dispatch_fail('index.php regained desktop layout markup');
}

$mobileFallback = file_get_contents($mobileFallbackPath);
if ($mobileFallback === false || strpos($mobileFallback, "../../http/page_dispatch.php") === false) {
    hg_dispatch_fail('Mobile fallback no longer shares the page dispatch path');
}

foreach ([
    'body_work.php',
    'page_context.php',
    'request_router.php',
    'head_work.php',
    'error_reporting.php',
] as $retiredBootstrap) {
    if (is_file($root . '/app/bootstrap/' . $retiredBootstrap)) {
        hg_dispatch_fail("Retired bootstrap owner was reintroduced: {$retiredBootstrap}");
    }
}

$bootstrapFiles = glob($root . '/app/bootstrap/*.php') ?: [];
sort($bootstrapFiles);
$expectedBootstrap = [$root . '/app/bootstrap/runtime.php'];
if ($bootstrapFiles !== $expectedBootstrap) {
    hg_dispatch_fail('Bootstrap is no longer startup-only: ' . implode(', ', array_map('basename', $bootstrapFiles)));
}

$desktopLayout = file_get_contents($root . '/app/views/layout/desktop.php');
if ($desktopLayout === false || strpos($desktopLayout, "include __DIR__ . '/head.php'") === false) {
    hg_dispatch_fail('Desktop layout no longer owns its document head');
}


fwrite(STDOUT, 'PHP dispatch characterization: OK (' . count($routes) . " mapped routes)\n");
