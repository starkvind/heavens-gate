<?php
require_once(__DIR__ . '/../../helpers/runtime_response.php');
require_once(__DIR__ . '/../../helpers/tool_api.php');
require_once(__DIR__ . '/../../domains/dice/queries.php');

if (!isset($link) || !($link instanceof mysqli)) {
    require_once(__DIR__ . '/../../helpers/db_connection.php');
}

if (!function_exists('hg_api_parse_debug_rolls')) {
    function hg_api_parse_debug_rolls(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/\s*,\s*/', $raw);
        $out = [];
        foreach ($parts as $part) {
            if ($part === '' || !preg_match('/^\d+$/', $part)) {
                return ['__invalid__'];
            }
            $value = (int)$part;
            if ($value < 1 || $value > 10) {
                return ['__invalid__'];
            }
            $out[] = $value;
        }
        return $out;
    }
}





if (!function_exists('hg_api_strlen')) {
    function hg_api_strlen(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

if (!function_exists('hg_api_query_first')) {
    function hg_api_query_first(array $request, array $names, ?string $default = null): ?string
    {
        foreach ($names as $name) {
            if (hg_request_query_has($request, (string)$name)) {
                return hg_request_query_value($request, (string)$name);
            }
        }
        return $default;
    }
}



if (!function_exists('hg_api_roll_title_from_context')) {
    function hg_api_roll_title_from_context(array $context, int $difficulty): string
    {
        $suffix = ' - Dificultad ' . $difficulty;
        $shortName = trim((string)($context['short_name'] ?? ''));
        if (!empty($context['is_resource_only'])) {
            return $shortName . ': ' . trim((string)($context['resource_name'] ?? 'Recurso')) . $suffix;
        }
        if (!empty($context['is_attr_only'])) {
            return $shortName . ': ' . trim((string)($context['attr_name'] ?? 'Atributo')) . $suffix;
        }
        if (!empty($context['is_background_only'])) {
            return $shortName . ': ' . trim((string)($context['skill_name'] ?? 'Trasfondo')) . $suffix;
        }
        if (!empty($context['is_attr_plus_skill'])) {
            return $shortName . ': ' . trim((string)($context['attr_name'] ?? 'Atributo')) . ' + ' . trim((string)($context['skill_name'] ?? 'Habilidad')) . $suffix;
        }
        return 'Tirada API' . $suffix;
    }
}

if (!function_exists('hg_api_json_response')) {
    function hg_api_json_response(array $payload, int $status = 200): void
    {
        hg_runtime_send_status($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    hg_tool_api_error('Method not allowed.', 405);
    return;
}

if (!hg_runtime_require_db($link, 'dice_api', 'plain', [
    'title' => 'API no disponible',
    'message' => 'No se pudo conectar a la base de datos.',
    'status' => 500,
])) {
    return;
}

if (!hg_tool_api_require_request_token($hgRequest)) {
    return;
}

$rollId = (int)hg_request_query_value($hgRequest, 'roll_id');
if ($rollId > 0) {
    $row = hg_dice_fetch_roll($link, $rollId);
    if (!$row) {
        hg_tool_api_error('Roll not found.', 404);
        return;
    }

    $rollResultsRaw = trim((string)($row['roll_results'] ?? ''));
    $rollResults = ($rollResultsRaw === '') ? [] : array_map('intval', explode(',', $rollResultsRaw));

    hg_api_json_response([
        'ok' => true,
        'roll' => [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'roll_name' => (string)$row['roll_name'],
            'dice_pool' => (int)$row['dice_pool'],
            'difficulty' => (int)$row['difficulty'],
            'roll_results' => $rollResults,
            'roll_results_csv' => $rollResultsRaw,
            'successes' => (int)$row['successes'],
            'botch' => ((int)$row['botch'] === 1),
            'willpower_spent' => ((int)$row['willpower_spent'] === 1),
            'rolled_at' => (string)$row['rolled_at'],
            'snippet' => '[hg_tirada]' . (int)$row['id'] . '[/hg_tirada]',
            'forum_url' => '/tools/dice?see=' . (int)$row['id'],
            'embed_url' => '/forum/diceroll?id=' . (int)$row['id'],
        ],
    ]);
    return;
}

$difficultyRaw = hg_api_query_first($hgRequest, ['roll_diff', 'roll_dif', 'dificultad']);
$extraDiceRaw = hg_api_query_first($hgRequest, ['extra_dice']);
if ($difficultyRaw === null || $extraDiceRaw === null) {
    hg_tool_api_error('roll_diff and extra_dice are required.', 400);
    return;
}

$difficulty = (int)$difficultyRaw;
$extraDice = (int)$extraDiceRaw;
$characterId = (int)hg_api_query_first($hgRequest, ['char_id', 'character_id'], '0');
$attrTraitId = (int)hg_api_query_first($hgRequest, ['attrib_id', 'attr_trait_id'], '0');
$skillTraitId = (int)hg_api_query_first($hgRequest, ['skill_id', 'skill_trait_id'], '0');
$resourceId = (int)hg_api_query_first($hgRequest, ['resource_id'], '0');
$name = trim((string)hg_api_query_first($hgRequest, ['name', 'nombre'], ''));
$rollName = trim((string)hg_api_query_first($hgRequest, ['roll_name', 'tirada_nombre'], ''));
$willpowerSpent = hg_request_query_has($hgRequest, 'willpower_spent')
    ? (int)hg_request_query_value($hgRequest, 'willpower_spent')
    : 0;
$debugForcedRolls = [];
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$dicePool = 0;
$maxDice = 20;
$rollContext = [
    'short_name' => '',
    'attr_name' => '',
    'skill_name' => '',
    'resource_name' => '',
    'is_attr_only' => false,
    'is_background_only' => false,
    'is_attr_plus_skill' => false,
    'is_resource_only' => false,
];

if ($difficulty < 2 || $difficulty > 10) {
    hg_tool_api_error('roll_diff must be between 2 and 10.', 400);
    return;
}

if ($characterId > 0) {
    $profile = hg_dice_fetch_roll_profile($link, $characterId);
    if ($profile === null) {
        hg_tool_api_error('Character not found or is not a protagonist.', 404);
        return;
    }

    if ($extraDice < 0 || $extraDice > 20) {
        hg_tool_api_error('extra_dice must be between 0 and 20 when using a character.', 400);
        return;
    }

    $attrVal = ($attrTraitId > 0 && isset($profile['attribute_map'][$attrTraitId])) ? (int)$profile['attribute_map'][$attrTraitId] : 0;
    $skillVal = ($skillTraitId > 0 && isset($profile['skill_map'][$skillTraitId])) ? (int)$profile['skill_map'][$skillTraitId] : 0;
    $resourceVal = ($resourceId > 0 && isset($profile['resource_map'][$resourceId])) ? (int)$profile['resource_map'][$resourceId] : 0;
    $skillKind = ($skillTraitId > 0 && isset($profile['skill_kind_map'][$skillTraitId])) ? (string)$profile['skill_kind_map'][$skillTraitId] : '';

    $hasAttr = ($attrVal > 0);
    $hasSkill = ($skillVal > 0);
    $hasResource = ($resourceVal > 0);
    $isAttrOnly = ($hasAttr && !$hasSkill && !$hasResource);
    $isBackgroundOnly = (!$hasAttr && $hasSkill && !$hasResource && $skillKind === 'trasfondo');
    $isAttrPlusSkill = ($hasAttr && $hasSkill && !$hasResource);
    $isResourceOnly = (!$hasAttr && !$hasSkill && $hasResource);
    $isValidCombo = ($isAttrOnly || $isBackgroundOnly || $isAttrPlusSkill || $isResourceOnly);

    if (!$isValidCombo) {
        hg_tool_api_error('Invalid combination. Allowed: Atributo, Trasfondo, Atributo+Habilidad/Trasfondo, or Recurso.', 400);
        return;
    }

    $dicePool = $attrVal + $skillVal + $resourceVal + $extraDice;
    $maxDice = 50;
    if ($name === '') {
        $name = (string)$profile['name'];
    }

    $shortNameParts = preg_split('/\s+/', trim((string)$profile['name']));
    $rollContext = [
        'short_name' => (string)($shortNameParts[0] ?? 'PJ'),
        'attr_name' => (string)($profile['attribute_labels'][$attrTraitId] ?? ''),
        'skill_name' => (string)($profile['skill_labels'][$skillTraitId] ?? ''),
        'resource_name' => (string)($profile['resource_labels'][$resourceId] ?? ''),
        'is_attr_only' => $isAttrOnly,
        'is_background_only' => $isBackgroundOnly,
        'is_attr_plus_skill' => $isAttrPlusSkill,
        'is_resource_only' => $isResourceOnly,
    ];
} else {
    if ($extraDice < 1 || $extraDice > 20) {
        hg_tool_api_error('extra_dice must be between 1 and 20 when no character is selected.', 400);
        return;
    }
    $dicePool = $extraDice;
    if ($name === '') {
        $name = 'API';
    }
}

if ($name === '' || $dicePool < 1 || $dicePool > $maxDice) {
    hg_tool_api_error('Invalid roll parameters.', 400);
    return;
}

if (hg_api_strlen($name) > 50) {
    hg_tool_api_error('name cannot exceed 50 characters.', 400);
    return;
}

if ($rollName === '') {
    $baseRollName = hg_api_roll_title_from_context($rollContext, $difficulty);
    $rollName = $baseRollName . ' [' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . ']';
}

if (hg_api_strlen($rollName) > 150) {
    hg_tool_api_error('roll_name cannot exceed 150 characters.', 400);
    return;
}

$lastRollAt = hg_dice_last_roll_at_for_ip($link, $ip);
if ($lastRollAt !== null && $lastRollAt !== '' && strtotime($lastRollAt) > time() - 10) {
    hg_tool_api_error('Has tirado hace menos de 10 segundos.', 429);
    return;
}

if (hg_dice_roll_name_exists($link, $rollName)) {
    hg_tool_api_error('Generated roll_name collision. Retry the request.', 409);
    return;
}

[$results, $successes, $botch] = hg_dice_roll_d10_pool($dicePool, $difficulty, $debugForcedRolls);
if ($willpowerSpent === 1) {
    $successes++;
    $botch = false;
}

$rollResults = implode(',', $results);
$insertError = null;
$lastId = hg_dice_insert_roll(
    $link,
    $name,
    $rollName,
    $dicePool,
    $difficulty,
    $rollResults,
    $successes,
    $botch,
    $willpowerSpent,
    $ip,
    $insertError
);
if ($lastId <= 0) {
    if ($insertError !== null && $insertError !== '') {
        hg_runtime_log_error('dice_api.insert', $insertError);
    }
    hg_tool_api_error('Could not save roll.', 500);
    return;
}

if (!headers_sent()) {
    header('X-HG-Roll-Id: ' . $lastId);
}
hg_tool_api_send_text('[hg_tirada]' . $lastId . '[/hg_tirada]');
