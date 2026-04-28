<?php

require_once __DIR__ . '/../helpers/pretty.php';

function hg_request_router_is_safe_method(): bool
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    return in_array($method, ['GET', 'HEAD'], true);
}

function hg_request_router_normalize_path(string $path): string
{
    $path = rawurldecode($path);
    $path = preg_replace('#/+#', '/', $path) ?? $path;
    if ($path === '') {
        return '/';
    }

    if ($path !== '/' && substr($path, -1) === '/') {
        $path = rtrim($path, '/');
    }

    return $path === '' ? '/' : $path;
}

function hg_request_router_redirect(string $location, int $status = 301): array
{
    return [
        'action' => 'redirect',
        'location' => $location,
        'status' => $status,
    ];
}

function hg_request_router_route(array $params): array
{
    return [
        'action' => 'route',
        'params' => $params,
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
        'listamyd' => '/rules/merits-flaws',
        'maneuver' => '/rules/maneuvers',
        'arquetip' => '/rules/archetypes',
        'powers' => '/powers',
        'dones' => '/powers/gifts',
        'listadones' => '/powers/gifts',
        'fulldon' => '/powers/gifts/full',
        'rites' => '/powers/rites',
        'ritelist' => '/powers/rites',
        'fullrite' => '/powers/rites/full',
        'totems' => '/powers/totems',
        'listatotems' => '/powers/totems',
        'fulltotem' => '/powers/totems/full',
        'disciplinas' => '/powers/disciplines',
        'ost' => '/music',
        'gallery' => '/gallery',
        'maps' => '/maps',
        'dados' => '/tools/dice',
        'csp' => '/tools/csp',
        'garou_name_gen' => '/tools/garou-name-generator',
        'forum_avatar_tool' => '/tools/forum-avatar',
        'forum_topic_viewer' => '/tools/forum-topic-viewer',
        'schema_sanitizer' => '/tools/schema-sanitizer',
        'combat_simulator' => '/tools/combat-simulator',
        'combat_simulator_result' => '/tools/combat-simulator/result',
        'combat_simulator_scores' => '/tools/combat-simulator/scores',
        'combat_simulator_weapons' => '/tools/combat-simulator/weapons',
        'combat_simulator_tournament' => '/tools/combat-simulator/tournament',
        'simulador' => '/tools/combat-simulator',
        'punts' => '/tools/combat-simulator/scores',
        'arms' => '/tools/combat-simulator/weapons',
        'sim_tournament' => '/tools/combat-simulator/tournament',
        'tooltip' => '/ajax/tooltip',
        'maps_api' => '/maps/api',
        'forum_message' => '/forum/message',
        'forum_diceroll' => '/forum/diceroll',
        'forum_item' => '/forum/item',
        'keygen' => '/tools/keygen',
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
            if (!isset($query['t'])) {
                return '/seasons';
            }
            return '/seasons/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_seasons', (string)$query['t']));
        case 'seechapter':
            if (!isset($query['t'])) {
                return '/chapters';
            }
            return '/chapters/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_chapters', (string)$query['t']));
        case 'biogroup':
            if (!isset($query['t'])) {
                return '/characters/types';
            }
            return '/characters/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_character_types', (string)$query['t']));
        case 'bio_worlds':
            if (!isset($query['t'])) {
                return '/characters/worlds';
            }
            return '/characters/worlds/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_realities', (string)$query['t']));
        case 'org_chart':
            $orgValue = isset($query['org']) ? (string)$query['org'] : (string)($query['b'] ?? 'justicia-metalica');
            return '/organizations/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_organizations', $orgValue)) . '/org-chart';
        case 'chronicles':
        case 'bio_chronicles':
            if (!isset($query['t'])) {
                return '/chronicles';
            }
            return '/chronicles/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_chronicles', (string)$query['t']));
        case 'chronicle_image':
            if (!isset($query['t'])) {
                return '/chronicles';
            }
            return '/chronicles/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_chronicles', (string)$query['t'])) . '/image';
        case 'muestrabio':
            if (!isset($query['b'])) {
                return '/characters';
            }
            return '/characters/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_characters', (string)$query['b']));
        case 'seeplayer':
            if (!isset($query['b'])) {
                return '/players';
            }
            return '/players/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_players', (string)$query['b']));
        case 'verdoc':
            if (!isset($query['b'])) {
                return '/documents';
            }
            return '/documents/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_docs', (string)$query['b']));
        case 'seeitem':
        case 'verobj':
            if (!isset($query['b'])) {
                return '/inventory';
            }
            return hg_request_router_inventory_item_path($link, (string)$query['b']);
        case 'inv':
            if (!isset($query['t'])) {
                return '/inventory';
            }
            return '/inventory/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_item_types', (string)$query['t']));
        case 'inv_type':
            if (!isset($query['t'])) {
                return '/inventory';
            }
            return '/inventory/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_item_types', (string)$query['t']));
        case 'sistemas':
            if (!isset($query['b'])) {
                return '/systems';
            }
            return '/systems/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_systems', (string)$query['b']));
        case 'verforma':
            if (!isset($query['b'])) {
                return '/systems';
            }
            return '/systems/form/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_forms', (string)$query['b']));
        case 'versistdetalle':
            return hg_request_router_system_detail_path($link, $query);
        case 'verrasgo':
            if (!isset($query['b'])) {
                return '/rules/traits';
            }
            return '/rules/traits/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_traits', (string)$query['b']));
        case 'vercondition':
            if (!isset($query['b'])) {
                return '/rules/conditions';
            }
            return '/rules/conditions/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_character_conditions', (string)$query['b']));
        case 'vermyd':
            if (!isset($query['b'])) {
                return '/rules/merits-flaws';
            }
            return '/rules/merits-flaws/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_merits_flaws', (string)$query['b']));
        case 'vermaneu':
            if (!isset($query['b'])) {
                return '/rules/maneuvers';
            }
            return '/rules/maneuvers/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_combat_maneuvers', (string)$query['b']));
        case 'verarch':
            if (!isset($query['b'])) {
                return '/rules/archetypes';
            }
            return '/rules/archetypes/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_archetypes', (string)$query['b']));
        case 'tipodon':
            if (!isset($query['b'])) {
                return '/powers/gifts';
            }
            return '/powers/gift/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_gift_types', (string)$query['b']));
        case 'muestradon':
            if (!isset($query['b'])) {
                return '/powers/gifts';
            }
            return '/powers/gift/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_gifts', (string)$query['b']));
        case 'tiporite':
            if (!isset($query['b'])) {
                return '/powers/rites';
            }
            return '/powers/rite/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_rite_types', (string)$query['b']));
        case 'seerite':
            if (!isset($query['b'])) {
                return '/powers/rites';
            }
            return '/powers/rite/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_rites', (string)$query['b']));
        case 'tipototm':
            if (!isset($query['b'])) {
                return '/powers/totems';
            }
            return '/powers/totem/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_totem_types', (string)$query['b']));
        case 'muestratotem':
            if (!isset($query['b'])) {
                return '/powers/totems';
            }
            return '/powers/totem/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_totems', (string)$query['b']));
        case 'tipodisc':
            if (!isset($query['b'])) {
                return '/powers/disciplines';
            }
            return '/powers/discipline/type/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'dim_discipline_types', (string)$query['b']));
        case 'muestradisc':
            if (!isset($query['b'])) {
                return '/powers/disciplines';
            }
            return '/powers/discipline/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_discipline_powers', (string)$query['b']));
        case 'timeline_event':
            if (!isset($query['t'])) {
                return '/timeline';
            }
            return '/timeline/event/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_timeline_events', (string)$query['t']));
        case 'maps_detail':
            if (!isset($query['id'])) {
                return '/maps';
            }
            return '/maps/poi/' . rawurlencode(hg_request_router_current_pretty_or_raw($link, 'fact_map_pois', (string)$query['id']));
        case 'combat_simulator_log':
        case 'vercombat':
            if (!isset($query['b'])) {
                return '/tools/combat-simulator/log';
            }
            return '/tools/combat-simulator/log/' . rawurlencode(trim((string)$query['b']));
        case 'combat_simulator_logs':
        case 'combtodo':
            return '/tools/combat-simulator/log';
        case 'mentions':
            if (trim((string)($query['type'] ?? '')) === 'episode') {
                return '/ajax/epis';
            }
            return '/ajax/mentions';
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
            $queryString = hg_request_router_query($query, ['p']);
            break;
        case 'talim':
            $queryString = hg_request_router_query($query, ['p']);
            break;
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
        default:
            $queryString = '';
            break;
    }

    if ($path === '/index.php' || $path === '/' || $path === '') {
        return hg_request_router_redirect($legacyPath . $queryString, 301);
    }

    return hg_request_router_noop();
}

function hg_request_router_match_path(mysqli $link, string $path): array
{
    $static = [
        '/home' => ['p' => 'home'],
        '/news' => ['p' => 'news'],
        '/status' => ['p' => 'status'],
        '/about' => ['p' => 'about'],
        '/bibliography' => ['p' => 'biblio'],
        '/search' => ['p' => 'busq'],
        '/search/results' => ['p' => 'busk'],
        '/timeline' => ['p' => 'timeline'],
        '/seasons' => ['p' => 'seasons_home'],
        '/seasons/analysis' => ['p' => 'temp_analisis'],
        '/seasons/complete' => ['p' => 'seasons_complete'],
        '/seasons/interludes' => ['p' => 'seasons_interludes'],
        '/seasons/personal-stories' => ['p' => 'seasons_personal'],
        '/seasons/specials' => ['p' => 'seasons_specials'],
        '/chapters' => ['p' => 'chapters_table'],
        '/parties' => ['p' => 'party'],
        '/characters/types' => ['p' => 'bios'],
        '/characters/worlds' => ['p' => 'bio_worlds'],
        '/characters' => ['p' => 'list_table'],
        '/chronicles' => ['p' => 'chronicles'],
        '/organizations' => ['p' => 'listgroups'],
        '/relationship-map/organizations' => ['p' => 'nebula_clan'],
        '/relationship-map/characters' => ['p' => 'nebula_character'],
        '/relationship-map/groups' => ['p' => 'nebula_groups'],
        '/organizations/org-chart' => ['p' => 'org_chart', 'org' => 'justicia-metalica'],
        '/players' => ['p' => 'players'],
        '/documents' => ['p' => 'listadocs'],
        '/inventory' => ['p' => 'inv'],
        '/systems' => ['p' => 'listasistemas'],
        '/rules' => ['p' => 'rules'],
        '/rules/traits' => ['p' => 'listarasgos'],
        '/rules/conditions' => ['p' => 'listconditions'],
        '/rules/merits-flaws' => ['p' => 'listamyd'],
        '/rules/maneuvers' => ['p' => 'maneuver'],
        '/rules/archetypes' => ['p' => 'arquetip'],
        '/powers' => ['p' => 'powers'],
        '/powers/gifts' => ['p' => 'listadones'],
        '/powers/gifts/full' => ['p' => 'fulldon'],
        '/powers/rites' => ['p' => 'ritelist'],
        '/powers/rites/full' => ['p' => 'fullrite'],
        '/powers/totems' => ['p' => 'listatotems'],
        '/powers/totems/full' => ['p' => 'fulltotem'],
        '/powers/disciplines' => ['p' => 'disciplinas'],
        '/music' => ['p' => 'ost'],
        '/gallery' => ['p' => 'gallery'],
        '/maps' => ['p' => 'maps'],
        '/talim' => ['p' => 'talim'],
        '/admin/merits-flaws' => ['p' => 'talim', 's' => 'admin_merits_flaws'],
        '/forum/message' => ['p' => 'forum_message'],
        '/forum/diceroll' => ['p' => 'forum_diceroll'],
        '/forum/item' => ['p' => 'forum_item'],
        '/tools/keygen' => ['p' => 'keygen'],
        '/tools/crop' => ['p' => 'crop'],
        '/tools/dice' => ['p' => 'dados'],
        '/tools/csp' => ['p' => 'csp'],
        '/tools/garou-name-generator' => ['p' => 'garou_name_gen'],
        '/tools/forum-avatar' => ['p' => 'forum_avatar_tool'],
        '/tools/forum-topic-viewer' => ['p' => 'forum_topic_viewer'],
        '/tools/schema-sanitizer' => ['p' => 'schema_sanitizer'],
        '/tools/combat-simulator' => ['p' => 'combat_simulator'],
        '/tools/combat-simulator/result' => ['p' => 'combat_simulator_result'],
        '/tools/combat-simulator/log' => ['p' => 'combat_simulator_logs'],
        '/tools/combat-simulator/scores' => ['p' => 'combat_simulator_scores'],
        '/tools/combat-simulator/weapons' => ['p' => 'combat_simulator_weapons'],
        '/tools/combat-simulator/tournament' => ['p' => 'combat_simulator_tournament'],
        '/ajax/tooltip' => ['p' => 'tooltip'],
        '/ajax/mentions' => ['p' => 'mentions'],
        '/ajax/epis' => ['p' => 'mentions', 'type' => 'episode'],
        '/maps/api' => ['p' => 'maps_api'],
    ];

    if (isset($static[$path])) {
        return hg_request_router_route($static[$path]);
    }

    $redirects = [
        '#^/index\.php$#' => '/',
        '#^/generador_claves\.php$#' => '/tools/keygen',
        '#^/crop\.html$#' => '/tools/crop',
        '#^/sep/snippet_forum_hg\.php$#' => '/forum/message',
        '#^/characters/chronicles$#' => '/chronicles',
        '#^/characters/chronicles/(.+)$#' => '/chronicles/$1',
        '#^/inventory/item/(.+)$#' => '/inventory/items/$1',
        '#^/inventory/items$#' => '/inventory',
        '#^/inventory/type$#' => '/inventory',
        '#^/inventory/(?!type(?:/|$)|items?(?:/|$))([^/]+)$#' => '/inventory/type/$1',
    ];

    foreach ($redirects as $pattern => $target) {
        if (preg_match($pattern, $path, $matches)) {
            $location = $target;
            foreach ($matches as $idx => $match) {
                if ($idx === 0) {
                    continue;
                }
                $location = str_replace('$' . $idx, rawurlencode($match), $location);
            }
            return hg_request_router_redirect($location, 301);
        }
    }

    $regexRoutes = [
        '#^/timeline/event/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'timeline_event', 't' => $m[1]];
        },
        '#^/seasons/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'temp', 't' => $m[1]];
        },
        '#^/chapters/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'seechapter', 't' => $m[1]];
        },
        '#^/characters/type/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'biogroup', 't' => $m[1]];
        },
        '#^/characters/worlds/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'bio_worlds', 't' => $m[1]];
        },
        '#^/characters/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'muestrabio', 'b' => $m[1]];
        },
        '#^/chronicles/([^/]+)/image$#' => static function (array $m): array {
            return ['p' => 'chronicle_image', 't' => $m[1]];
        },
        '#^/chronicles/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'chronicles', 't' => $m[1]];
        },
        '#^/organizations/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'seegroup', 't' => '2', 'b' => $m[1]];
        },
        '#^/organizations/([^/]+)/org-chart$#' => static function (array $m): array {
            return ['p' => 'org_chart', 'org' => $m[1]];
        },
        '#^/groups/([^/]+)/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'seegroup', 't' => '1', 'org' => $m[1], 'b' => $m[2]];
        },
        '#^/groups/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'seegroup', 't' => '1', 'b' => $m[1]];
        },
        '#^/players/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'seeplayer', 'b' => $m[1]];
        },
        '#^/documents/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'verdoc', 'b' => $m[1]];
        },
        '#^/inventory/type/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'inv_type', 't' => $m[1]];
        },
        '#^/inventory/items/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'verobj', 'b' => $m[1]];
        },
        '#^/inventory/([^/]+)/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'verobj', 't' => $m[1], 'b' => $m[2]];
        },
        '#^/systems/breeds/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'versistdetalle', 'tc' => '1', 'b' => $m[1]];
        },
        '#^/systems/auspices/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'versistdetalle', 'tc' => '2', 'b' => $m[1]];
        },
        '#^/systems/tribes/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'versistdetalle', 'tc' => '3', 'b' => $m[1]];
        },
        '#^/systems/misc/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'versistdetalle', 'tc' => '4', 'b' => $m[1]];
        },
        '#^/systems/detail/([0-9]+)/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'versistdetalle', 'tc' => $m[1], 'b' => $m[2]];
        },
        '#^/systems/form/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'verforma', 'b' => $m[1]];
        },
        '#^/systems/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'sistemas', 'b' => $m[1]];
        },
        '#^/rules/traits/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'verrasgo', 'b' => $m[1]];
        },
        '#^/rules/conditions/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'vercondition', 'b' => $m[1]];
        },
        '#^/rules/merits-flaws/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'vermyd', 'b' => $m[1]];
        },
        '#^/rules/maneuvers/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'vermaneu', 'b' => $m[1]];
        },
        '#^/rules/archetypes/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'verarch', 'b' => $m[1]];
        },
        '#^/powers/gift/type/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'tipodon', 'b' => $m[1]];
        },
        '#^/powers/gift/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'muestradon', 'b' => $m[1]];
        },
        '#^/powers/rite/type/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'tiporite', 'b' => $m[1]];
        },
        '#^/powers/rite/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'seerite', 'b' => $m[1]];
        },
        '#^/powers/totem/type/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'tipototm', 'b' => $m[1]];
        },
        '#^/powers/totem/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'muestratotem', 'b' => $m[1]];
        },
        '#^/powers/discipline/type/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'tipodisc', 'b' => $m[1]];
        },
        '#^/powers/discipline/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'muestradisc', 'b' => $m[1]];
        },
        '#^/maps/poi/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'maps_detail', 'id' => $m[1]];
        },
        '#^/tools/combat-simulator/log/([0-9]+)$#' => static function (array $m): array {
            return ['p' => 'combat_simulator_log', 'b' => $m[1]];
        },
    ];

    foreach ($regexRoutes as $pattern => $resolver) {
        if (preg_match($pattern, $path, $matches)) {
            return hg_request_router_route($resolver($matches));
        }
    }

    return hg_request_router_route(['p' => 'error404']);
}

function hg_request_router_resolve(mysqli $link, string $requestUri, array $query): array
{
    $path = hg_request_router_normalize_path((string)(parse_url($requestUri, PHP_URL_PATH) ?? '/'));

    if ($path === '/sep/snippet_forum_hg.php' && hg_request_router_is_safe_method()) {
        return hg_request_router_redirect('/forum/message' . hg_request_router_forum_embed_query('forum_message', $query), 301);
    }

    if (!empty($query['p']) && hg_request_router_is_safe_method()) {
        return hg_request_router_legacy_query_result($link, $path, $query);
    }

    if (!empty($query['p'])) {
        return hg_request_router_noop();
    }

    if ($path === '/' || $path === '') {
        return hg_request_router_noop();
    }

    return hg_request_router_match_path($link, $path);
}

function hg_request_router_bootstrap(mysqli $link): void
{
    $result = hg_request_router_resolve(
        $link,
        (string)($_SERVER['REQUEST_URI'] ?? '/'),
        $_GET
    );

    if (($result['action'] ?? '') === 'redirect') {
        header('Location: ' . (string)$result['location'], true, (int)($result['status'] ?? 301));
        exit;
    }

    if (($result['action'] ?? '') !== 'route' || empty($result['params']) || !is_array($result['params'])) {
        return;
    }

    foreach ($result['params'] as $key => $value) {
        $_GET[$key] = (string)$value;
    }
}
