<?php

require_once __DIR__ . '/../helpers/pretty.php';

/**
 * Legacy query-string canonicalization.
 *
 * Historical ?p=... URLs still need to resolve IDs/aliases before redirecting
 * to canonical paths. Canonical path matching itself lives in path_matcher.php.
 * The hg_request_router_* prefix is retained temporarily for compatibility;
 * this file is the only runtime owner of those legacy helpers.
 */

function hg_request_router_redirect(string $location, int $status = 301): array
{
    return [
        'action' => 'redirect',
        'location' => $location,
        'status' => $status,
    ];
}

function hg_request_router_noop(): array
{
    return [
        'action' => 'noop',
    ];
}

function hg_request_router_query(array $query, array $exclude = []): string
{
    foreach ($exclude as $key) {
        unset($query[$key]);
    }

    $qs = http_build_query($query);
    return $qs === '' ? '' : ('?' . $qs);
}

function hg_request_router_allowed_query(array $query, array $allowed): string
{
    $filtered = [];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $query)) {
            $filtered[$key] = $query[$key];
        }
    }

    return hg_request_router_query($filtered);
}

function hg_request_router_forum_embed_query(string $route, array $query): string
{
    switch ($route) {
        case 'forum_message':
            return hg_request_router_allowed_query($query, ['id', 'palette', 'msg']);
        case 'forum_diceroll':
            return hg_request_router_allowed_query($query, ['id', 'palette']);
        case 'forum_item':
            return hg_request_router_allowed_query($query, ['id']);
        default:
            return '';
    }
}

function hg_request_router_current_pretty_or_raw(mysqli $link, string $table, string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!hg_table_has_column($link, $table, 'pretty_id')) {
        return $value;
    }

    if (preg_match('/^\d+$/', $value)) {
        $pretty = get_pretty_id($link, $table, (int)$value);
        return $pretty !== null && $pretty !== '' ? $pretty : $value;
    }

    $resolved = resolve_pretty_id($link, $table, $value);
    if ($resolved === null) {
        return $value;
    }

    $pretty = get_pretty_id($link, $table, (int)$resolved);
    return $pretty !== null && $pretty !== '' ? $pretty : $value;
}

function hg_request_router_group_path(mysqli $link, array $query): ?string
{
    $type = trim((string)($query['t'] ?? ''));
    $item = trim((string)($query['b'] ?? ''));
    if ($type === '' || $item === '') {
        return null;
    }

    if ($type === '2') {
        $segment = hg_request_router_current_pretty_or_raw($link, 'dim_organizations', $item);
        return '/organizations/' . rawurlencode($segment);
    }

    if ($type !== '1') {
        return null;
    }

    $groupSegment = hg_request_router_current_pretty_or_raw($link, 'dim_groups', $item);
    $org = trim((string)($query['org'] ?? ''));
    if ($org === '') {
        return '/groups/' . rawurlencode($groupSegment);
    }

    $orgSegment = hg_request_router_current_pretty_or_raw($link, 'dim_organizations', $org);
    return '/groups/' . rawurlencode($orgSegment) . '/' . rawurlencode($groupSegment);
}

function hg_request_router_system_detail_path(mysqli $link, array $query): ?string
{
    $tc = trim((string)($query['tc'] ?? ''));
    $item = trim((string)($query['b'] ?? ''));
    if ($tc === '' || $item === '') {
        return null;
    }

    $map = [
        '1' => ['dim_breeds', '/systems/breeds'],
        '2' => ['dim_auspices', '/systems/auspices'],
        '3' => ['dim_tribes', '/systems/tribes'],
        '4' => ['fact_misc_systems', '/systems/misc'],
    ];

    if (!isset($map[$tc])) {
        return '/systems/detail/' . rawurlencode($tc) . '/' . rawurlencode($item);
    }

    [$table, $base] = $map[$tc];
    $segment = hg_request_router_current_pretty_or_raw($link, $table, $item);
    return $base . '/' . rawurlencode($segment);
}

function hg_request_router_inventory_item_path(mysqli $link, string $itemValue): string
{
    $itemValue = trim($itemValue);
    if ($itemValue === '') {
        return '/inventory';
    }

    $resolved = resolve_pretty_id($link, 'fact_items', $itemValue);
    if ($resolved === null) {
        return '/inventory/items/' . rawurlencode($itemValue);
    }

    $itemId = (int)$resolved;
    if ($itemId <= 0) {
        return '/inventory/items/' . rawurlencode($itemValue);
    }

    $itemSlug = get_pretty_id($link, 'fact_items', $itemId) ?: (string)$itemId;
    $typeSlug = '';

    if ($stmt = $link->prepare("
        SELECT t.id, t.pretty_id
        FROM fact_items i
        LEFT JOIN dim_item_types t ON t.id = i.item_type_id
        WHERE i.id = ?
        LIMIT 1
    ")) {
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (is_array($row)) {
            $typeSlug = trim((string)($row['pretty_id'] ?? ''));
            if ($typeSlug === '' && !empty($row['id'])) {
                $typeSlug = (string)((int)$row['id']);
            }
        }
    }

    if ($typeSlug !== '') {
        return '/inventory/' . rawurlencode($typeSlug) . '/' . rawurlencode($itemSlug);
    }

    return '/inventory/items/' . rawurlencode($itemSlug);
}

function hg_request_router_path_from_query(mysqli $link, array $query): ?string
{
    $route = trim((string)($query['p'] ?? ''));
    if ($route === '') {
        return null;
    }

    $direct = [
        'home' => '/home',
        'news' => '/news',
        'status' => '/status',
        'about' => '/about',
        'biblio' => '/bibliography',
        'busq' => '/search',
        'timeline' => '/timeline',
        'temp_analisis' => '/seasons/analysis',
        'seasons_home' => '/seasons',
        'season_order' => '/seasons/order',
        'seasons_complete' => '/seasons/complete',
        'seasons_interludes' => '/seasons/interludes',
        'seasons_personal' => '/seasons/personal-stories',
        'seasons_specials' => '/seasons/specials',
        'party' => '/parties',
        'bios' => '/characters/types',
        'list_table' => '/characters',
        'listgroups' => '/organizations',
        'nebula_clan' => '/relationship-map/organizations',
        'nebula_character' => '/relationship-map/characters',
        'nebula_groups' => '/relationship-map/groups',
        'players' => '/players',
        'listadocs' => '/documents',
        'listaobj' => '/inventory',
        'listasistemas' => '/systems',
        'rules' => '/rules',
        'listarasgos' => '/rules/traits',
        'listconditions' => '/rules/conditions',
        'actions' => '/rules/actions',
        'listamyd' => '/rules/merits-flaws',
        'maneuver' => '/rules/maneuvers',
        'arquetip' => '/rules/archetypes',
        'powers' => '/powers',
        'dones' => '/powers/gifts',
        'listadones' => '/powers/gifts',
        'fulldon' => '/powers/gifts/full',
        'customdon' => '/powers/gifts/custom',
        'rites' => '/powers/rites',
        'ritelist' => '/powers/rites',
        'fullrite' => '/powers/rites/full',
        'customrite' => '/powers/rites/custom',
        'totems' => '/powers/totems',
        'listatotems' => '/powers/totems',
        'fulltotem' => '/powers/totems/full',
        'customtotem' => '/powers/totems/custom',
        'disciplinas' => '/powers/disciplines',
        'fulldisc' => '/powers/disciplines/full',
        'customdisc' => '/powers/disciplines/custom',
        'ost' => '/music',
        'gallery' => '/gallery',
        'maps' => '/maps',
        'dados' => '/tools/dice',
        'csp' => '/tools/csp',
        'garou_name_gen' => '/tools/garou-name-generator',
        'forum_avatar_tool' => '/tools/forum-avatar',
        'forum_avatar_api' => '/api/forum-avatar',
        'forum_topic_viewer' => '/tools/forum-topic-viewer',
        'combat_simulator' => '/games/combat-simulator',
        'combat_simulator_result' => '/games/combat-simulator/result',
        'combat_simulator_scores' => '/games/combat-simulator/scores',
        'combat_simulator_weapons' => '/games/combat-simulator/weapons',
        'combat_simulator_tournament' => '/games/combat-simulator/tournament',
        'game_cards' => '/games/card-game',
        'game_cards_collection' => '/games/card-game/collection',
        'game_cards_combat' => '/games/card-game/combat',
        'game_cards_mobile' => '/games/card-game/mobile',
        'game_cards_explanation' => '/games/card-game/explanation',
        'game_cards_lab' => '/games/hg-cardgame-dev-lab',
        'game_cards_lab_collection' => '/games/hg-cardgame-dev-lab/collection',
        'game_cards_lab_combat' => '/games/hg-cardgame-dev-lab/combat',
        'game_cards_lab_mobile' => '/games/hg-cardgame-dev-lab/mobile',
        'game_cards_lab_explanation' => '/games/hg-cardgame-dev-lab/explanation',
        'simulador' => '/games/combat-simulator',
        'punts' => '/games/combat-simulator/scores',
        'arms' => '/games/combat-simulator/weapons',
        'sim_tournament' => '/games/combat-simulator/tournament',
        'tooltip' => '/ajax/tooltip',
        'maps_api' => '/maps/api',
        'dice_api' => '/api/dice',
        'forum_message' => '/forum/message',
        'forum_diceroll' => '/forum/diceroll',
        'forum_item' => '/forum/item',
        'crop' => '/tools/crop',
    ];

    if (isset($direct[$route])) {
        return $direct[$route];
    }

    switch ($route) {
        case 'busk':
            return '/search/results';
        case 'talim':
            return '/talim';
        case 'temp':
            if (!isset($query['t'])) return '/seasons';
            return '/seasons/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_seasons', (string)$query['t']));
        case 'seechapter':
            if (!isset($query['t'])) return '/chapters';
            return '/chapters/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_chapters', (string)$query['t']));
        case 'biogroup':
            if (!isset($query['t'])) return '/characters/types';
            return '/characters/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_character_types', (string)$query['t']));
        case 'bio_worlds':
            if (!isset($query['t'])) return '/characters/worlds';
            return '/characters/worlds/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_realities', (string)$query['t']));
        case 'org_chart':
            $orgValue = isset($query['org']) ? (string)$query['org'] : (string)($query['b'] ?? 'justicia-metalica');
            return '/organizations/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_organizations', $orgValue)) . '/org-chart';
        case 'chronicles':
        case 'bio_chronicles':
            if (!isset($query['t'])) return '/chronicles';
            return '/chronicles/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_chronicles', (string)$query['t']));
        case 'chronicle_image':
            if (!isset($query['t'])) return '/chronicles';
            return '/chronicles/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_chronicles', (string)$query['t'])) . '/image';
        case 'muestrabio':
            if (!isset($query['b'])) return '/characters';
            return '/characters/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_characters', (string)$query['b']));
        case 'seeplayer':
            if (!isset($query['b'])) return '/players';
            return '/players/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_players', (string)$query['b']));
        case 'verdoc':
            if (!isset($query['b'])) return '/documents';
            return '/documents/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_docs', (string)$query['b']));
        case 'seeitem':
        case 'verobj':
            if (!isset($query['b'])) return '/inventory';
            return hg_request_router_inventory_item_path($link, (string)$query['b']);
        case 'inv':
        case 'inv_type':
            if (!isset($query['t'])) return '/inventory';
            return '/inventory/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_item_types', (string)$query['t']));
        case 'sistemas':
            if (!isset($query['b'])) return '/systems';
            return '/systems/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_systems', (string)$query['b']));
        case 'verforma':
            if (!isset($query['b'])) return '/systems';
            return '/systems/form/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_forms', (string)$query['b']));
        case 'versistdetalle':
            return hg_request_router_system_detail_path($link, $query);
        case 'verrasgo':
            if (!isset($query['b'])) return '/rules/traits';
            return '/rules/traits/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_traits', (string)$query['b']));
        case 'vercondition':
            if (!isset($query['b'])) return '/rules/conditions';
            return '/rules/conditions/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_character_conditions', (string)$query['b']));
        case 'veraction':
            if (!isset($query['b'])) return '/rules/actions';
            return '/rules/actions/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_actions', (string)$query['b']));
        case 'vermyd':
            if (!isset($query['b'])) return '/rules/merits-flaws';
            return '/rules/merits-flaws/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_merits_flaws', (string)$query['b']));
        case 'vermaneu':
            if (!isset($query['b'])) return '/rules/maneuvers';
            return '/rules/maneuvers/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_combat_maneuvers', (string)$query['b']));
        case 'verarch':
            if (!isset($query['b'])) return '/rules/archetypes';
            return '/rules/archetypes/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_archetypes', (string)$query['b']));
        case 'tipodon':
            if (!isset($query['b'])) return '/powers/gifts';
            return '/powers/gift/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_gift_types', (string)$query['b']));
        case 'muestradon':
            if (!isset($query['b'])) return '/powers/gifts';
            return '/powers/gift/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_gifts', (string)$query['b']));
        case 'tiporite':
            if (!isset($query['b'])) return '/powers/rites';
            return '/powers/rite/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_rite_types', (string)$query['b']));
        case 'seerite':
            if (!isset($query['b'])) return '/powers/rites';
            return '/powers/rite/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_rites', (string)$query['b']));
        case 'tipototm':
            if (!isset($query['b'])) return '/powers/totems';
            return '/powers/totem/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_totem_types', (string)$query['b']));
        case 'muestratotem':
            if (!isset($query['b'])) return '/powers/totems';
            return '/powers/totem/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_totems', (string)$query['b']));
        case 'tipodisc':
            if (!isset($query['b'])) return '/powers/disciplines';
            return '/powers/discipline/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_discipline_types', (string)$query['b']));
        case 'muestradisc':
            if (!isset($query['b'])) return '/powers/disciplines';
            return '/powers/discipline/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_discipline_powers', (string)$query['b']));
        case 'timeline_event':
            if (!isset($query['t'])) return '/timeline';
            return '/timeline/event/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_timeline_events', (string)$query['t']));
        case 'maps_detail':
            if (!isset($query['id'])) return '/maps';
            return '/maps/poi/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_map_pois', (string)$query['id']));
        case 'combat_simulator_log':
        case 'vercombat':
            if (!isset($query['b'])) return '/games/combat-simulator/log';
            return '/games/combat-simulator/log/' . rawurlencode(trim((string)$query['b']));
        case 'combat_simulator_logs':
        case 'combtodo':
            return '/games/combat-simulator/log';
        case 'mentions':
            return trim((string)($query['type'] ?? '')) === 'episode' ? '/ajax/epis' : '/ajax/mentions';
        case 'seegroup':
            return hg_request_router_group_path($link, $query);
        default:
            return null;
    }
}

function hg_request_router_legacy_query_result(mysqli $link, string $path, array $query): array
{
    $legacyPath = hg_request_router_path_from_query($link, $query);
    if ($legacyPath === null) {
        return hg_request_router_noop();
    }

    $route = trim((string)($query['p'] ?? ''));
    $queryString = '';

    switch ($route) {
        case 'busk':
        case 'talim':
        case 'combat_simulator_logs':
        case 'combtodo':
            $queryString = hg_request_router_query($query, ['p']);
            break;
        case 'mentions':
            if (trim((string)($query['type'] ?? '')) === 'episode') {
                $legacyPath = '/ajax/epis';
                $queryString = hg_request_router_query($query, ['p', 'type']);
            } else {
                $queryString = hg_request_router_query($query, ['p']);
            }
            break;
        case 'forum_message':
        case 'forum_diceroll':
        case 'forum_item':
            $queryString = hg_request_router_forum_embed_query($route, $query);
            break;
    }

    if ($path === '/index.php' || $path === '/' || $path === '') {
        return hg_request_router_redirect($legacyPath . $queryString, 301);
    }

    return hg_request_router_noop();
}
