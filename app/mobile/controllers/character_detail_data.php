<?php

$mobileCharacterDetailReady = false;

include_once __DIR__ . '/../../helpers/public_response.php';
include_once __DIR__ . '/../../helpers/character_avatar.php';
require_once __DIR__ . '/../../domains/characters/detail_queries.php';
require_once __DIR__ . '/../../domains/characters/sheet_queries.php';

if (!function_exists('hg_mobile_bio_h')) {
    function hg_mobile_bio_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_bio_pretty_href')) {
    function hg_mobile_bio_pretty_href(mysqli $link, string $table, string $base, int $id): string
    {
        if ($id <= 0) {
            return '#';
        }
        return function_exists('pretty_url') ? pretty_url($link, $table, $base, $id) : rtrim($base, '/') . '/' . $id;
    }
}

if (!function_exists('hg_mobile_bio_link')) {
    function hg_mobile_bio_link(string $href, string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        return '<a href="' . hg_mobile_bio_h($href) . '">' . hg_mobile_bio_h($label) . '</a>';
    }
}

if (!function_exists('hg_mobile_bio_trait_dots')) {
    function hg_mobile_bio_trait_dots(int $value): string
    {
        $value = max(0, min(10, $value));
        return "<span class='hg-mobile-trait-dots' aria-label='{$value} puntos'>"
            . str_repeat('&#9679;', $value)
            . str_repeat('&#9675;', max(0, 5 - $value))
            . '</span>';
    }
}

if (!function_exists('hg_mobile_bio_date')) {
    function hg_mobile_bio_date(?string $raw): string
    {
        $raw = trim((string)$raw);
        if ($raw === '' || preg_match('/^0{4}-0{2}-0{2}(?:[ T].*)?$/', $raw)) {
            return '';
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T].*)?$/', $raw, $parts)) {
            $year = (int)$parts[1];
            $month = (int)$parts[2];
            $day = (int)$parts[3];
            if ($year >= 1 && checkdate($month, $day, $year)) {
                return sprintf('%02d-%02d-%04d', $day, $month, $year);
            }
        }

        return $raw;
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_character_detail', 'missing DB connection');
    hg_public_render_error('Personaje no disponible', 'No se pudo cargar el personaje.');
    return;
}

$rawId = hg_request_param($hgRequest, 'character');
$characterId = 0;
if ($rawId !== '') {
    $resolved = resolve_pretty_id($link, 'fact_characters', $rawId);
    $characterId = (int)($resolved ?? 0);
}

if ($characterId <= 0) {
    hg_public_render_not_found('Personaje no encontrado', 'No se pudo localizar el personaje solicitado.');
    return;
}

$detail = hg_characters_fetch_detail_context_row($link, $characterId, '2,7');
$character = $detail['row'] ?? null;
if (!is_array($character)) {
    hg_public_render_not_found('Personaje no encontrado', 'No se pudo localizar el personaje solicitado.');
    return;
}

$pageSect = 'Biografía';
$metaTitle = (string)($character['name'] ?? '') . " | Personajes | Heaven's Gate";
$metaDescription = trim(strip_tags((string)($character['info_text'] ?? '')));
if (function_exists('mb_substr')) {
    $metaDescription = mb_substr($metaDescription, 0, 160, 'UTF-8');
} else {
    $metaDescription = substr($metaDescription, 0, 160);
}

$avatar = hg_character_avatar_url((string)($character['image_url'] ?? ''), (string)($character['gender'] ?? ''));
$infoHtml = (string)($character['info_text'] ?? '');
$status = trim((string)($character['status_label'] ?? ''));
$deathDescription = trim((string)($character['death_description'] ?? ''));
$hasCharacterSheet = strtolower(trim((string)($character['character_kind'] ?? ''))) === 'pj';

$cid = (int)$characterId;
$systemId = (int)($character['system_id'] ?? 0);
$chronicleId = (int)($character['chronicle_id'] ?? 0);
$playerId = (int)($character['player_id'] ?? 0);
$breedId = (int)($character['breed_id'] ?? 0);
$auspiceId = (int)($character['auspice_id'] ?? 0);
$tribeId = (int)($character['tribe_id'] ?? 0);
$totemId = (int)($character['totem_id'] ?? 0);
$natureId = (int)($character['nature_id'] ?? 0);
$demeanorId = (int)($character['demeanor_id'] ?? 0);

$systemDetailLabels = hg_characters_fetch_system_detail_labels($link, $systemId);
$detailLabel = static function (string $key, string $fallback) use ($systemDetailLabels): string {
    $label = trim((string)($systemDetailLabels[$key] ?? ''));
    return $label !== '' ? $label : $fallback;
};

$affiliations = hg_characters_fetch_active_affiliations($link, $cid);
$groups = $affiliations['groups'] ?? [];
$organizations = $affiliations['organizations'] ?? [];
$hidePlayer = $playerId === 48 || strcasecmp(trim((string)($character['player_name'] ?? '')), 'PNJ') === 0;

$detailLinks = [];
if (!$hidePlayer && $playerId > 0 && trim((string)($character['player_name'] ?? '')) !== '' && (int)($character['player_show_in_catalog'] ?? 0) === 1) {
    $detailLinks['Jugador'] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_players', '/players', $playerId),
        (string)$character['player_name']
    );
} elseif (!$hidePlayer && trim((string)($character['player_name'] ?? '')) !== '') {
    $detailLinks['Jugador'] = hg_mobile_bio_h($character['player_name']);
}
if ($chronicleId > 0 && trim((string)($character['chronicle_name'] ?? '')) !== '') {
    $detailLinks['Crónica'] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_chronicles', '/chronicles', $chronicleId),
        (string)$character['chronicle_name']
    );
}
if ($breedId > 0 && trim((string)($character['breed_name'] ?? '')) !== '') {
    $detailLinks[$detailLabel('label_breed', 'Raza')] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_breeds', '/systems/detail/1', $breedId),
        (string)$character['breed_name']
    );
}
if ($auspiceId > 0 && trim((string)($character['auspice_name'] ?? '')) !== '') {
    $detailLinks[$detailLabel('label_auspice', 'Auspicio')] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_auspices', '/systems/detail/2', $auspiceId),
        (string)$character['auspice_name']
    );
}
if ($tribeId > 0 && trim((string)($character['tribe_name'] ?? '')) !== '') {
    $detailLinks[$detailLabel('label_tribe', 'Tribu')] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_tribes', '/systems/detail/3', $tribeId),
        (string)$character['tribe_name']
    );
}
if ($totemId > 0 && trim((string)($character['totem_name'] ?? '')) !== '') {
    $detailLinks['Tótem'] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_totems', '/powers/totem', $totemId),
        (string)$character['totem_name']
    );
}
if ($natureId > 0 && trim((string)($character['nature_name'] ?? '')) !== '') {
    $detailLinks['Naturaleza'] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_archetypes', '/rules/archetypes', $natureId),
        (string)$character['nature_name']
    );
}
if ($demeanorId > 0 && trim((string)($character['demeanor_name'] ?? '')) !== '') {
    $detailLinks['Conducta'] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_archetypes', '/rules/archetypes', $demeanorId),
        (string)$character['demeanor_name']
    );
}
if (!empty($groups)) {
    $links = [];
    foreach ($groups as $group) {
        $gid = (int)($group['id'] ?? 0);
        $links[] = hg_mobile_bio_link(
            hg_mobile_bio_pretty_href($link, 'dim_groups', '/groups', $gid),
            (string)($group['name'] ?? '')
        );
    }
    $detailLinks[$detailLabel('label_pack', 'Manada')] = implode(', ', array_filter($links));
}
if (!empty($organizations)) {
    $links = [];
    foreach ($organizations as $organization) {
        $oid = (int)($organization['id'] ?? 0);
        $links[] = hg_mobile_bio_link(
            hg_mobile_bio_pretty_href($link, 'dim_organizations', '/organizations', $oid),
            (string)($organization['name'] ?? '')
        );
    }
    $detailLinks[$detailLabel('label_clan', 'Clan')] = implode(', ', array_filter($links));
}

$traitsByKind = [];
if ($hasCharacterSheet) {
    foreach (['Atributos', 'Talentos', 'Técnicas', 'Conocimientos', 'Trasfondos'] as $kindName) {
        $rows = hg_characters_fetch_traits_for_system_type($link, $cid, $systemId, $kindName);
        foreach ($rows as $row) {
            if ($kindName === 'Trasfondos' && (int)($row['value'] ?? 0) <= 0) {
                continue;
            }
            $row['kind'] = $kindName;
            $traitsByKind[$kindName][] = $row;
        }
    }
}

$mobileActions = $hasCharacterSheet ? hg_characters_fetch_actions_for_sheet($link, $cid) : [];
foreach ($mobileActions as &$action) {
    $action['href'] = hg_mobile_bio_pretty_href($link, 'fact_actions', '/rules/actions', (int)($action['id'] ?? 0));
    $difficulty = (int)(($action['difficulty_mode'] ?? '') === 'fixed'
        ? ($action['fixed_difficulty'] ?? 6)
        : ($action['suggested_difficulty'] ?? 6));
    $action['roll_href'] = '/tools/dice?' . http_build_query([
        'character_id' => $cid,
        'attr_trait_id' => (int)($action['attribute_trait_id'] ?? 0),
        'skill_trait_id' => (int)($action['skill_trait_id'] ?? 0),
        'dificultad' => $difficulty,
        'action_name' => (string)($action['name'] ?? 'Acción'),
    ], '', '&', PHP_QUERY_RFC3986);
}
unset($action);

$mobileActionsByCategory = [];
foreach ($mobileActions as $action) {
    $category = trim((string)($action['category'] ?? '')) ?: 'Sin categoría';
    $mobileActionsByCategory[$category][] = $action;
}

$mobileResourcesByKind = $hasCharacterSheet
    ? hg_characters_fetch_resources($link, $cid, $systemId)
    : ['renombre' => [], 'estado' => [], 'exp' => []];

$mobileForms = [];
$mobileBaseManeuvers = [];
if ($hasCharacterSheet && $systemId > 0) {
    $forms = hg_characters_fetch_forms_for_system($link, $systemId);
    $races = [];
    foreach ($forms as $form) {
        $race = trim((string)($form['race'] ?? ''));
        if ($race !== '') {
            $races[$race] = true;
        }
    }

    $formIds = [];
    foreach ($forms as $form) {
        if (count($races) > 1 && trim((string)($form['race'] ?? '')) !== trim((string)($character['tribe_name'] ?? ''))) {
            continue;
        }
        $formIds[] = (int)($form['id'] ?? 0);
    }

    $modifiersByForm = hg_characters_fetch_form_modifiers($link, $formIds);
    foreach (hg_characters_fetch_system_maneuvers($link, $systemId) as $maneuver) {
        $maneuver['href'] = hg_mobile_bio_pretty_href(
            $link,
            'fact_combat_maneuvers',
            '/rules/maneuvers',
            (int)($maneuver['id'] ?? 0)
        );
        $mobileBaseManeuvers[(int)($maneuver['id'] ?? 0)] = $maneuver;
    }

    $formManeuvers = [];
    foreach (hg_characters_fetch_form_maneuvers($link, $formIds) as $formId => $maneuvers) {
        foreach ($maneuvers as $maneuver) {
            $maneuver['href'] = hg_mobile_bio_pretty_href(
                $link,
                'fact_combat_maneuvers',
                '/rules/maneuvers',
                (int)($maneuver['id'] ?? 0)
            );
            $formManeuvers[(int)$formId][(int)($maneuver['id'] ?? 0)] = $maneuver;
        }
    }

    foreach ($forms as $form) {
        $formId = (int)($form['id'] ?? 0);
        if (!in_array($formId, $formIds, true)) {
            continue;
        }
        $maneuvers = $mobileBaseManeuvers;
        foreach (($formManeuvers[$formId] ?? []) as $maneuverId => $maneuver) {
            $maneuvers[(int)$maneuverId] = $maneuver;
        }
        $mobileForms[] = [
            'id' => $formId,
            'name' => trim((string)($form['form'] ?? '')),
            'modifiers' => $modifiersByForm[$formId] ?? [],
            'maneuvers' => array_values($maneuvers),
        ];
    }
}
$mobileBaseManeuvers = array_values($mobileBaseManeuvers);

$merits = $hasCharacterSheet ? hg_characters_fetch_merits_flaws($link, $cid) : [];
usort($merits, static function (array $left, array $right): int {
    $kindCmp = strcasecmp((string)($right['kind'] ?? ''), (string)($left['kind'] ?? ''));
    return $kindCmp !== 0 ? $kindCmp : strcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
});

$conditions = $hasCharacterSheet ? hg_characters_fetch_conditions($link, $cid) : [];
usort($conditions, static function (array $left, array $right): int {
    $categoryCmp = strcasecmp((string)($left['category'] ?? ''), (string)($right['category'] ?? ''));
    return $categoryCmp !== 0 ? $categoryCmp : strcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
});

$powerRows = $hasCharacterSheet ? hg_characters_fetch_powers($link, $cid) : [];
$powers = [
    'Dones' => $powerRows['dones'] ?? [],
    'Disciplinas' => $powerRows['disciplinas'] ?? [],
    'Rituales' => $powerRows['rituales'] ?? [],
];

$items = $hasCharacterSheet ? hg_characters_fetch_items($link, $cid) : [];
foreach ($items as &$item) {
    $item['type_name'] = (string)($item['item_type_name'] ?? '');
}
unset($item);
usort($items, static function (array $left, array $right): int {
    $typeCmp = strcasecmp((string)($left['type_name'] ?? ''), (string)($right['type_name'] ?? ''));
    return $typeCmp !== 0 ? $typeCmp : strcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
});

$comments = array_slice(hg_characters_fetch_comments($link, $cid), 0, 20);
$relations = hg_characters_fetch_relations($link, $cid, '2,7');
$chapterParticipation = hg_characters_fetch_chapter_participation($link, $cid, 'season');
$timelineEvents = hg_characters_fetch_participation_events($link, $cid, 24);
$characterDocs = hg_characters_fetch_docs($link, $cid);
$characterExternalLinks = hg_characters_fetch_external_links($link, $cid);

$mobileCharacterDetailReady = true;
