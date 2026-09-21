<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function hg_admin_contract_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function hg_admin_contract_source(string $root, string $relative): string
{
    $source = file_get_contents($root . '/' . $relative);
    if ($source === false) {
        hg_admin_contract_fail('Cannot read ' . $relative);
    }
    return $source;
}

$main = hg_admin_contract_source($root, 'app/controllers/admin/admin_main.php');
$auth = hg_admin_contract_source($root, 'app/helpers/admin_auth.php');
$ajax = hg_admin_contract_source($root, 'app/helpers/admin_ajax.php');

$mainMarkers = [
    "admin_auth.php",
    'hg_admin_session_start()',
    'hg_admin_send_security_headers()',
    'hg_admin_require_db($link)',
    'hg_admin_is_authenticated()',
    'http_response_code(403)',
    'application/json; charset=UTF-8',
    'admin_login.php',
    'main_nav_bar.php',
    '/assets/css/hg-admin.css',
];
foreach ($mainMarkers as $marker) {
    if (strpos($main, $marker) === false) {
        hg_admin_contract_fail('Admin shell lost contract marker: ' . $marker);
    }
}

$requiredSections = [
    'admin_characters',
    'admin_players',
    'admin_groups',
    'admin_organizations',
    'admin_chronicles',
    'admin_parties',
    'admin_seasons',
    'admin_chapters',
    'admin_timelines',
    'admin_realities',
    'admin_pois',
    'admin_gallery',
    'admin_bso',
    'admin_docs',
    'admin_external_links',
    'admin_character_links',
    'admin_doc_links',
    'admin_systems',
    'admin_system_details',
    'admin_traits',
    'admin_resources',
    'admin_forms',
    'admin_maneuvers',
    'admin_actions',
    'admin_powers',
    'admin_items',
    'admin_merits_flaws',
    'admin_character_conditions',
    'admin_menu',
    'admin_datatables',
    'admin_inspect_db',
    'admin_mentions_help',
    'logout',
];
foreach ($requiredSections as $section) {
    if (strpos($main, $section) === false) {
        hg_admin_contract_fail('Admin shell lost active section: ' . $section);
    }
}

$legacyAliases = [
    'admin_pjs',
    'admin_epis',
    'admin_temp',
    'admin_plots',
    'admin_characters_conditions_brige',
];
foreach ($legacyAliases as $alias) {
    if (strpos($main, $alias) === false) {
        hg_admin_contract_fail('Admin shell lost legacy alias: ' . $alias);
    }
}

$authMarkers = [
    "session.use_strict_mode",
    "session.use_only_cookies",
    "'httponly' => true",
    "'samesite' => 'Strict'",
    "X-Frame-Options: DENY",
    "frame-ancestors 'none'",
    "Referrer-Policy: no-referrer",
    '$absoluteTimeout = 12 * 60 * 60',
    '$idleTimeout = 2 * 60 * 60',
    'session_regenerate_id(true)',
    "if (\$path !== '/talim'",
    "isset(\$params['ajax'])",
];
foreach ($authMarkers as $marker) {
    if (strpos($auth, $marker) === false) {
        hg_admin_contract_fail('Admin auth contract lost marker: ' . $marker);
    }
}

$ajaxMarkers = [
    'hg_admin_json_response',
    "'ok' => true",
    "'ok' => false",
    "'message' =>",
    "'msg' =>",
    "'data' =>",
    "'errors' =>",
    "'meta' =>",
    'hg_admin_ensure_csrf_token',
    'random_bytes(16)',
    'HTTP_X_CSRF_TOKEN',
    'hash_equals',
    "hg_admin_json_error('No autorizado', 403",
    'hg_admin_require_db',
];
foreach ($ajaxMarkers as $marker) {
    if (strpos($ajax, $marker) === false) {
        hg_admin_contract_fail('Admin AJAX/security contract lost marker: ' . $marker);
    }
}

$retired = [
    'app/controllers/admin/admin_game_cards.php',
    'app/controllers/admin/admin_sim_browser.php',
    'app/controllers/admin/admin_sim_character_talk.php',
];
foreach ($retired as $relative) {
    $source = hg_admin_contract_source($root, $relative);
    if (strpos($source, 'http_response_code(410)') === false) {
        hg_admin_contract_fail('Retired Admin surface lost 410 tombstone: ' . $relative);
    }
}

fwrite(STDOUT, "Admin shell/security characterization: PASS\n");
