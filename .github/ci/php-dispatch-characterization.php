<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routesPath = $root . '/app/routing/routes.php';
$dispatcherPath = $root . '/app/http/dispatcher.php';
$bodyPath = $root . '/app/bootstrap/body_work.php';

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
    'listaobj' => 'app/controllers/docs/item_table.php',
    'seeitem' => 'app/controllers/docs/item_page.php',
    'listasistemas' => 'app/controllers/systems/systems_table.php',
    'sistemas' => 'app/controllers/systems/system_overview_page.php',
    'powers' => 'app/controllers/pwrs/powers_home.php',
    'dones' => 'app/controllers/pwrs/don_category_list.php',
    'muestradon' => 'app/controllers/pwrs/don_page.php',
    'dados' => 'app/controllers/tool/dice_roller.php',
    'dice_api' => 'app/controllers/tool/dice_api.php',
    'maps' => 'app/controllers/maps/maps_main.php',
    'maps_api' => 'app/controllers/maps/maps_api.php',
    'players' => 'app/controllers/playr/playr_list.php',
    'seeplayer' => 'app/controllers/playr/playr_page.php',
    'forum_message' => 'app/partials/forum_message_snippet.php',
    'combat_simulator' => 'app/controllers/tool/combat_simulator.php',
    'game_cards' => 'app/controllers/tool/game_cards.php',
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

$dispatcher = file_get_contents($dispatcherPath);
if ($dispatcher === false) {
    hg_dispatch_fail('Cannot read dispatcher.php');
}

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
    if (strpos($dispatcher, "'{$bareRoute}'") === false) {
        hg_dispatch_fail("Bare-page contract changed: missing {$bareRoute}");
    }
}

if (strpos($dispatcher, "include('app/controllers/main/main_home.php')") === false) {
    hg_dispatch_fail('Empty-route home fallback changed');
}
if (strpos($dispatcher, "include('app/controllers/main/main_news.php')") === false) {
    hg_dispatch_fail('Unknown-route news fallback changed');
}

$body = file_get_contents($bodyPath);
if ($body === false) {
    hg_dispatch_fail('Cannot read body_work.php');
}
foreach ([
    "require __DIR__ . '/page_context.php'",
    "require __DIR__ . '/../routing/routes.php'",
    "require __DIR__ . '/../http/dispatcher.php'",
] as $needle) {
    if (strpos($body, $needle) === false) {
        hg_dispatch_fail("body_work seam missing: {$needle}");
    }
}

foreach (['combat_simulator.php', 'game_cards.php'] as $retired) {
    $retiredSource = file_get_contents($root . '/app/controllers/tool/' . $retired);
    if ($retiredSource === false || strpos($retiredSource, 'http_response_code(410)') === false) {
        hg_dispatch_fail("Retired tool is no longer an explicit HTTP 410 stub: {$retired}");
    }
}

fwrite(STDOUT, 'PHP dispatch characterization: OK (' . count($routes) . " mapped routes)\n");
