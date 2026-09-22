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
$sections = hg_admin_contract_source($root, 'app/helpers/admin_sections.php');
$auth = hg_admin_contract_source($root, 'app/helpers/admin_auth.php');
$ajax = hg_admin_contract_source($root, 'app/helpers/admin_ajax.php');

require_once $root . '/app/helpers/admin_sections.php';

$mainMarkers = [
    "admin_auth.php",
    "admin_sections.php",
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
    if (strpos($sections, $section) === false) {
        hg_admin_contract_fail('Admin registry lost active section: ' . $section);
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
    if (strpos($sections, $alias) === false) {
        hg_admin_contract_fail('Admin registry lost legacy alias: ' . $alias);
    }
}

$registryContracts = [
    ['admin_characters', 'normal', 'admin_characters.php'],
    ['admin_characters', 'ajax', 'admin_characters.php'],
    ['admin_pjs', 'normal', 'admin_characters.php'],
    ['admin_epis', 'ajax', 'admin_chapters.php'],
    ['admin_temp', 'normal', 'admin_seasons.php'],
    ['admin_plots', 'ajax', 'admin_parties.php'],
    ['admin_characters_conditions_brige', 'normal', 'admin_character_conditions_bridge.php'],
    ['admin_character_collision_audit', 'normal', 'admin_character_collision_audit.php'],
    ['admin_relations', 'normal', 'admin_relations.php'],
    ['admin_datatables', 'normal', 'admin_datatables.php'],
    ['admin_inspect_db', 'normal', '../../tools/inspect_db.php'],
    ['admin_mentions_help', 'normal', 'mentions_help.html'],
    ['logout', 'normal', 'admin_logout.php'],
];
foreach ($registryContracts as [$section, $mode, $target]) {
    $resolved = hg_admin_section_resolve($section, $mode);
    if (!is_array($resolved) || ($resolved['target'] ?? null) !== $target) {
        hg_admin_contract_fail("Admin registry contract mismatch: {$section} / {$mode}");
    }
}

$ajaxForbidden = [
    'admin_character_collision_audit',
    'admin_relations',
    'admin_datatables',
    'admin_inspect_db',
    'admin_mentions_help',
    'admin_org_chart_schema',
    'logout',
];
foreach ($ajaxForbidden as $section) {
    if (hg_admin_section_resolve($section, 'ajax') !== null) {
        hg_admin_contract_fail('Admin registry widened AJAX surface: ' . $section);
    }
}

if (substr_count($main, 'switch (') > 0 || substr_count($main, 'switch(') > 0) {
    hg_admin_contract_fail('admin_main.php regained switch-owned section dispatch');
}

foreach (['hg_admin_section_resolve($seccionAjax, \'ajax\')', 'hg_admin_section_resolve($seccion, \'normal\')'] as $marker) {
    if (strpos($main, $marker) === false) {
        hg_admin_contract_fail('Admin shell lost shared registry dispatch marker: ' . $marker);
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
