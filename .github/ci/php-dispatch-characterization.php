<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$bodyPath = $root . '/app/bootstrap/body_work.php';
$source = file_get_contents($bodyPath);
if ($source === false) {
    fwrite(STDERR, "Cannot read body_work.php\n");
    exit(1);
}

function hg_dispatch_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$routes = [];
preg_match_all(
    "/^[\\t ]*'([^']+)'[\\t ]*=>[\\t ]*\\['([^']+)'[\\t ]*,[\\t ]*(?:'([^']*)'|null)\\][\\t ]*,/m",
    $source,
    $matches,
    PREG_SET_ORDER
);

foreach ($matches as $match) {
    $routes[$match[1]] = $match[2];
}

if (count($routes) < 70) {
    hg_dispatch_fail('Dispatch route table unexpectedly small: ' . count($routes));
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
    if (($routes[$route] ?? null) !== $file) {
        hg_dispatch_fail("Dispatch mapping changed for {$route}: expected {$file}, got " . ($routes[$route] ?? '<missing>'));
    }
}

foreach ($routes as $route => $file) {
    if (!is_file($root . '/' . $file)) {
        hg_dispatch_fail("Dispatch target missing for {$route}: {$file}");
    }
}

$bareNeedles = [
    "'forum_message'",
    "'forum_diceroll'",
    "'forum_item'",
    "'crop'",
    "'tooltip'",
    "'mentions'",
    "'maps_api'",
    "'dice_api'",
    "'forum_avatar_api'",
    "'chronicle_image'",
];
foreach ($bareNeedles as $needle) {
    $bareStart = strpos($source, "in_array(\$routeKey, [");
    if ($bareStart === false) {
        hg_dispatch_fail('Bare-page route list not found');
    }
    $bareSlice = substr($source, $bareStart, 500);
    if (strpos($bareSlice, $needle) === false) {
        hg_dispatch_fail("Bare-page contract changed: missing {$needle}");
    }
}

foreach (['combat_simulator.php', 'game_cards.php'] as $retired) {
    $retiredSource = file_get_contents($root . '/app/controllers/tool/' . $retired);
    if ($retiredSource === false || strpos($retiredSource, 'http_response_code(410)') === false) {
        hg_dispatch_fail("Retired tool is no longer an explicit HTTP 410 stub: {$retired}");
    }
}

if (strpos($source, 'include("app/controllers/main/main_home.php")') === false) {
    hg_dispatch_fail('Empty-route home fallback changed');
}
if (strpos($source, 'include("app/controllers/main/main_news.php")') === false) {
    hg_dispatch_fail('Unknown-route news fallback changed');
}

fwrite(STDOUT, 'PHP dispatch characterization: OK (' . count($routes) . " mapped routes)\n");
