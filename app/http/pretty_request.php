<?php

require_once __DIR__ . '/../helpers/pretty.php';

/**
 * Resolve pretty route parameters into the numeric values expected by legacy
 * data code without mutating request superglobals. Redirect behavior remains
 * identical to the historical normalizer.
 */
function hg_pretty_request_normalize(mysqli $link, string $route, array $query, string $requestUri): array
{
    $paramMap = [
        'muestrabio' => ['b', 'fact_characters', '/characters'],
        'biogroup' => ['t', 'dim_character_types', '/characters/type'],
        'chronicles' => ['t', 'dim_chronicles', '/chronicles'],
        'bio_chronicles' => ['t', 'dim_chronicles', '/chronicles'],
        'bio_worlds' => ['t', 'dim_realities', '/characters/worlds'],
        'seegroup' => ['b', null, null],
        'seeplayer' => ['b', 'dim_players', '/players'],
        'verdoc' => ['b', 'fact_docs', '/documents'],
        'verobj' => ['b', 'fact_items', '/inventory/items'],
        'inv_type' => ['t', 'dim_item_types', '/inventory/type'],
        'seeitem' => ['b', 'fact_items', '/inventory/items'],
        'muestradon' => ['b', 'fact_gifts', '/powers/gift'],
        'tipodon' => ['b', 'dim_gift_types', '/powers/gift/type'],
        'seerite' => ['b', 'fact_rites', '/powers/rite'],
        'tiporite' => ['b', 'dim_rite_types', '/powers/rite/type'],
        'muestratotem' => ['b', 'dim_totems', '/powers/totem'],
        'tipototm' => ['b', 'dim_totem_types', '/powers/totem/type'],
        'muestradisc' => ['b', 'fact_discipline_powers', '/powers/discipline'],
        'tipodisc' => ['b', 'dim_discipline_types', '/powers/discipline/type'],
        'verrasgo' => ['b', 'dim_traits', '/rules/traits'],
        'vercondition' => ['b', 'dim_character_conditions', '/rules/conditions'],
        'veraction' => ['b', 'fact_actions', '/rules/actions'],
        'vermyd' => ['b', 'dim_merits_flaws', '/rules/merits-flaws'],
        'verarch' => ['b', 'dim_archetypes', '/rules/archetypes'],
        'vermaneu' => ['b', 'fact_combat_maneuvers', '/rules/maneuvers'],
        'sistemas' => ['b', 'dim_systems', '/systems'],
        'verforma' => ['b', 'dim_forms', '/systems/form'],
        'versistdetalle' => ['b', 'dim_breeds', '/systems/detail'],
        'seechapter' => ['t', 'dim_chapters', '/chapters'],
        'temp' => ['t', 'dim_seasons', '/seasons'],
        'timeline_event' => ['t', 'fact_timeline_events', '/timeline/event'],
        'maps_detail' => ['id', 'fact_map_pois', '/maps/poi'],
    ];

    if (!isset($paramMap[$route])) {
        return $query;
    }

    [$param, $table, $base] = $paramMap[$route];

    if ($route === 'seegroup' && isset($query['t'], $query['b'])) {
        $type = (string)$query['t'];
        if ($type === '1') {
            $table = 'dim_groups';
            $base = '/groups';
            if (isset($query['org']) && trim((string)$query['org']) !== '') {
                $orgRaw = trim((string)$query['org']);
                $orgResolved = resolve_pretty_id($link, 'dim_organizations', $orgRaw);
                $orgSegment = $orgRaw;
                if ($orgResolved !== null) {
                    $query['org'] = (string)$orgResolved;
                    $orgPretty = get_pretty_id($link, 'dim_organizations', (int)$orgResolved);
                    if ($orgPretty) {
                        $orgSegment = $orgPretty;
                    }
                }
                $base = '/groups/' . rawurlencode($orgSegment);
            }
        } elseif ($type === '2') {
            $table = 'dim_organizations';
            $base = '/organizations';
        }
    }

    if ($route === 'versistdetalle' && isset($query['tc'], $query['b'])) {
        $detailType = (string)$query['tc'];
        if ($detailType === '1') {
            $table = 'dim_breeds';
            $base = '/systems/breeds';
        } elseif ($detailType === '2') {
            $table = 'dim_auspices';
            $base = '/systems/auspices';
        } elseif ($detailType === '3') {
            $table = 'dim_tribes';
            $base = '/systems/tribes';
        } elseif ($detailType === '4') {
            $table = 'fact_misc_systems';
            $base = '/systems/misc';
        } else {
            $base = '/systems/detail/' . $detailType;
        }
        $param = 'b';
    }

    if (!$table || !$base || !isset($query[$param])) {
        return $query;
    }

    $raw = (string)$query[$param];
    $resolved = resolve_pretty_id($link, $table, $raw);
    if ($resolved !== null) {
        $query[$param] = (string)$resolved;
    }

    if (($route === 'verobj' || $route === 'seeitem') && isset($query['b'])) {
        $itemId = (int)$query['b'];
        if ($itemId > 0) {
            $itemSlug = '';
            $typeSlug = '';
            if ($stmtItem = $link->prepare("
                SELECT i.id, i.pretty_id AS item_pretty, t.id AS type_id, t.pretty_id AS type_pretty
                FROM fact_items i
                LEFT JOIN dim_item_types t ON t.id = i.item_type_id
                WHERE i.id = ?
                LIMIT 1
            ")) {
                $stmtItem->bind_param('i', $itemId);
                $stmtItem->execute();
                $rsItem = $stmtItem->get_result();
                if ($rsItem && ($rowItem = $rsItem->fetch_assoc())) {
                    $itemSlug = (string)($rowItem['item_pretty'] ?? '');
                    if ($itemSlug === '' && isset($rowItem['id'])) {
                        $itemSlug = (string)$rowItem['id'];
                    }

                    $typeSlug = (string)($rowItem['type_pretty'] ?? '');
                    if ($typeSlug === '' && isset($rowItem['type_id'])) {
                        $typeSlug = (string)$rowItem['type_id'];
                    }
                }
                $stmtItem->close();
            }

            if ($itemSlug !== '' && $typeSlug !== '') {
                $target = '/inventory/' . rawurlencode($typeSlug) . '/' . rawurlencode($itemSlug);
                $currentPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
                $norm = static function (string $path): string {
                    $path = rtrim(rawurldecode($path), '/');
                    return $path === '' ? '/' : $path;
                };

                if ($norm($currentPath) !== $norm($target)) {
                    header("Location: $target", true, 301);
                    exit;
                }
            }
        }
    }

    if ($route === 'versistdetalle' && $resolved !== null) {
        $currentPretty = get_pretty_id($link, $table, (int)$resolved);
        $currentPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
        $target = rtrim($base, '/') . '/' . rawurlencode($currentPretty ?: $raw);
        if ($target !== '' && rtrim(rawurldecode($currentPath), '/') !== rtrim($target, '/')) {
            header("Location: $target", true, 301);
            exit;
        }
    }

    if ($resolved !== null && !preg_match('/^\d+$/', $raw)) {
        $currentPretty = get_pretty_id($link, $table, (int)$resolved);
        if ($currentPretty && $currentPretty !== $raw) {
            $target = rtrim($base, '/') . '/' . rawurlencode($currentPretty);
            header("Location: $target", true, 301);
            exit;
        }
    }

    if (preg_match('/^\d+$/', $raw)) {
        $pretty = get_pretty_id($link, $table, (int)$raw);
        if ($pretty) {
            $target = rtrim($base, '/') . '/' . rawurlencode($pretty);
            header("Location: $target", true, 301);
            exit;
        }
    }

    return $query;
}
