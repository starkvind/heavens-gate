<?php

/**
 * Pure path matcher for canonical request paths.
 *
 * This layer knows URL shapes only. It does not read request globals and does
 * not touch MySQL. Compatibility/canonicalization for legacy query-string
 * routes remains in request_router.php while that seam is being dismantled.
 */
function hg_request_path_matcher_match(string $path): array
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
        '/seasons/order' => ['p' => 'season_order'],
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
        '/groups' => ['p' => 'listgroups'],
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
        '/rules/actions' => ['p' => 'actions'],
        '/rules/merits-flaws' => ['p' => 'listamyd'],
        '/rules/maneuvers' => ['p' => 'maneuver'],
        '/rules/archetypes' => ['p' => 'arquetip'],
        '/powers' => ['p' => 'powers'],
        '/powers/gifts' => ['p' => 'listadones'],
        '/powers/gifts/full' => ['p' => 'fulldon'],
        '/powers/gifts/custom' => ['p' => 'customdon'],
        '/powers/rites' => ['p' => 'ritelist'],
        '/powers/rites/full' => ['p' => 'fullrite'],
        '/powers/rites/custom' => ['p' => 'customrite'],
        '/powers/totems' => ['p' => 'listatotems'],
        '/powers/totems/full' => ['p' => 'fulltotem'],
        '/powers/totems/custom' => ['p' => 'customtotem'],
        '/powers/disciplines' => ['p' => 'disciplinas'],
        '/powers/disciplines/full' => ['p' => 'fulldisc'],
        '/powers/disciplines/custom' => ['p' => 'customdisc'],
        '/music' => ['p' => 'ost'],
        '/gallery' => ['p' => 'gallery'],
        '/maps' => ['p' => 'maps'],
        '/talim' => ['p' => 'talim'],
        '/admin' => ['p' => 'talim'],
        '/admin/game-cards' => ['p' => 'talim', 's' => 'admin_game_cards'],
        '/admin/merits-flaws' => ['p' => 'talim', 's' => 'admin_merits_flaws'],
        '/admin/actions' => ['p' => 'talim', 's' => 'admin_actions'],
        '/forum/message' => ['p' => 'forum_message'],
        '/forum/diceroll' => ['p' => 'forum_diceroll'],
        '/forum/item' => ['p' => 'forum_item'],
        '/tools/crop' => ['p' => 'crop'],
        '/tools/dice' => ['p' => 'dados'],
        '/tools/csp' => ['p' => 'csp'],
        '/tools/garou-name-generator' => ['p' => 'garou_name_gen'],
        '/tools/forum-avatar' => ['p' => 'forum_avatar_tool'],
        '/api/forum-avatar' => ['p' => 'forum_avatar_api'],
        '/tools/forum-topic-viewer' => ['p' => 'forum_topic_viewer'],
        '/games/combat-simulator' => ['p' => 'combat_simulator'],
        '/games/combat-simulator/result' => ['p' => 'combat_simulator_result'],
        '/games/combat-simulator/log' => ['p' => 'combat_simulator_logs'],
        '/games/combat-simulator/scores' => ['p' => 'combat_simulator_scores'],
        '/games/combat-simulator/weapons' => ['p' => 'combat_simulator_weapons'],
        '/games/combat-simulator/tournament' => ['p' => 'combat_simulator_tournament'],
        '/tools/combat-simulator' => ['p' => 'combat_simulator'],
        '/tools/combat-simulator/result' => ['p' => 'combat_simulator_result'],
        '/tools/combat-simulator/log' => ['p' => 'combat_simulator_logs'],
        '/tools/combat-simulator/scores' => ['p' => 'combat_simulator_scores'],
        '/tools/combat-simulator/weapons' => ['p' => 'combat_simulator_weapons'],
        '/tools/combat-simulator/tournament' => ['p' => 'combat_simulator_tournament'],
        '/games/card-game' => ['p' => 'game_cards'],
        '/games/card-game/collection' => ['p' => 'game_cards_collection'],
        '/games/card-game/combat' => ['p' => 'game_cards_combat'],
        '/games/card-game/mobile' => ['p' => 'game_cards_mobile'],
        '/games/card-game/explanation' => ['p' => 'game_cards_explanation'],
        '/games/hg-cardgame-dev-lab' => ['p' => 'game_cards_lab'],
        '/games/hg-cardgame-dev-lab/collection' => ['p' => 'game_cards_lab_collection'],
        '/games/hg-cardgame-dev-lab/combat' => ['p' => 'game_cards_lab_combat'],
        '/games/hg-cardgame-dev-lab/mobile' => ['p' => 'game_cards_lab_mobile'],
        '/games/hg-cardgame-dev-lab/explanation' => ['p' => 'game_cards_lab_explanation'],
        '/game-cards' => ['p' => 'game_cards'],
        '/tools/game-cards' => ['p' => 'game_cards'],
        '/ajax/tooltip' => ['p' => 'tooltip'],
        '/ajax/mentions' => ['p' => 'mentions'],
        '/ajax/epis' => ['p' => 'mentions', 'type' => 'episode'],
        '/maps/api' => ['p' => 'maps_api'],
        '/api/dice' => ['p' => 'dice_api'],
    ];

    if (isset($static[$path])) {
        return [
            'action' => 'route',
            'params' => $static[$path],
        ];
    }

    $redirects = [
        '#^/index\.php$#' => '/',
        '#^/game_cards\.php$#' => '/games/card-game',
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

            return [
                'action' => 'redirect',
                'location' => $location,
                'status' => 301,
            ];
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
        '#^/rules/actions/([^/]+)$#' => static function (array $m): array {
            return ['p' => 'veraction', 'b' => $m[1]];
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
        '#^/games/combat-simulator/log/([0-9]+)$#' => static function (array $m): array {
            return ['p' => 'combat_simulator_log', 'b' => $m[1]];
        },
        '#^/tools/combat-simulator/log/([0-9]+)$#' => static function (array $m): array {
            return ['p' => 'combat_simulator_log', 'b' => $m[1]];
        },
    ];

    foreach ($regexRoutes as $pattern => $resolver) {
        if (preg_match($pattern, $path, $matches)) {
            return [
                'action' => 'route',
                'params' => $resolver($matches),
            ];
        }
    }

    return [
        'action' => 'route',
        'params' => ['p' => 'error404'],
    ];
}
