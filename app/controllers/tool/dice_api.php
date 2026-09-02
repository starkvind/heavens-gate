<?php
require_once(__DIR__ . '/../../helpers/runtime_response.php');
require_once(__DIR__ . '/../../helpers/tool_api.php');

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

if (!function_exists('hg_api_roll_d10_pool')) {
    function hg_api_roll_d10_pool(int $dicePool, int $difficulty, array $forced = []): array
    {
        $results = [];
        $rawSuccesses = 0;
        $oneDetected = false;

        for ($i = 0; $i < $dicePool; $i++) {
            $die = !empty($forced) ? (int)$forced[$i] : rand(1, 10);
            $results[] = $die;
            if ($die >= $difficulty) {
                $rawSuccesses++;
            }
            if ($die === 1) {
                $oneDetected = true;
            }
        }

        $successes = $rawSuccesses;
        if ($oneDetected) {
            $successes--;
            if ($successes < 0) {
                $successes = 0;
            }
        }

        $botch = ($oneDetected && $rawSuccesses === 0);
        return [$results, $successes, $botch];
    }
}

if (!function_exists('hg_api_normalize_kind_key')) {
    function hg_api_normalize_kind_key(string $kind): string
    {
        $normalized = function_exists('mb_strtolower') ? mb_strtolower(trim($kind), 'UTF-8') : strtolower(trim($kind));
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
            if ($converted !== false) {
                $normalized = $converted;
            }
        }
        return strtolower($normalized);
    }
}

if (!function_exists('hg_api_strlen')) {
    function hg_api_strlen(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

if (!function_exists('hg_api_fetch_roll_profile')) {
    function hg_api_fetch_roll_profile(mysqli $link, int $characterId): ?array
    {
        if ($characterId <= 0) {
            return null;
        }

        $profile = null;
        $sqlCharacter = "
            SELECT c.id, c.name, ch.name AS chronicle_name
            FROM fact_characters c
            LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id
            WHERE c.id = ?
              AND LOWER(c.character_kind) = 'pj'
            LIMIT 1
        ";
        $stmt = mysqli_prepare($link, $sqlCharacter);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $profile = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'chronicle' => (string)($row['chronicle_name'] ?? ''),
                'attribute_map' => [],
                'attribute_labels' => [],
                'skill_map' => [],
                'skill_labels' => [],
                'skill_kind_map' => [],
                'resource_map' => [],
                'resource_labels' => [],
            ];
        }
        mysqli_stmt_close($stmt);

        if ($profile === null) {
            return null;
        }

        $sqlTraits = "
            SELECT t.id AS trait_id, t.name, t.kind, b.value
            FROM bridge_characters_traits b
            JOIN dim_traits t ON t.id = b.trait_id
            WHERE b.character_id = ?
              AND t.kind IN ('Atributos','Talentos','Técnicas','Tecnicas','Conocimientos','Trasfondos')
            ORDER BY t.name
        ";
        $stmtTraits = mysqli_prepare($link, $sqlTraits);
        if ($stmtTraits) {
            mysqli_stmt_bind_param($stmtTraits, 'i', $characterId);
            mysqli_stmt_execute($stmtTraits);
            $resultTraits = mysqli_stmt_get_result($stmtTraits);
            while ($row = mysqli_fetch_assoc($resultTraits)) {
                $traitId = (int)$row['trait_id'];
                $value = (int)$row['value'];
                if ($traitId <= 0 || $value <= 0) {
                    continue;
                }
                $kindKey = hg_api_normalize_kind_key((string)$row['kind']);
                if ($kindKey === 'atributos') {
                    $profile['attribute_map'][$traitId] = $value;
                    $profile['attribute_labels'][$traitId] = (string)$row['name'];
                    continue;
                }
                if (in_array($kindKey, ['talentos', 'tecnicas', 'conocimientos', 'trasfondos'], true)) {
                    $skillKind = ($kindKey === 'trasfondos') ? 'trasfondo' : 'habilidad';
                    $profile['skill_map'][$traitId] = $value;
                    $profile['skill_labels'][$traitId] = (string)$row['name'];
                    $profile['skill_kind_map'][$traitId] = $skillKind;
                }
            }
            mysqli_stmt_close($stmtTraits);
        }

        $sqlResources = "
            SELECT d.id AS resource_id, d.name, r.value_permanent, r.value_temporary
            FROM bridge_characters_system_resources r
            JOIN dim_systems_resources d ON d.id = r.resource_id
            WHERE r.character_id = ?
              AND LOWER(d.kind) = 'estado'
            ORDER BY d.sort_order, d.name
        ";
        $stmtResources = mysqli_prepare($link, $sqlResources);
        if ($stmtResources) {
            mysqli_stmt_bind_param($stmtResources, 'i', $characterId);
            mysqli_stmt_execute($stmtResources);
            $resultResources = mysqli_stmt_get_result($stmtResources);
            while ($row = mysqli_fetch_assoc($resultResources)) {
                $resourceId = (int)$row['resource_id'];
                $valuePerm = (int)$row['value_permanent'];
                $valueTemp = (int)$row['value_temporary'];
                $value = ($valueTemp > 0) ? $valueTemp : $valuePerm;
                if ($resourceId <= 0 || $value <= 0) {
                    continue;
                }
                $profile['resource_map'][$resourceId] = $value;
                $profile['resource_labels'][$resourceId] = (string)$row['name'];
            }
            mysqli_stmt_close($stmtResources);
        }

        return $profile;
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

if (!hg_tool_api_require_token((string)($_GET['token'] ?? ''))) {
    return;
}

$rollId = (int)($_GET['roll_id'] ?? 0);
if ($rollId > 0) {
    $stmtRoll = mysqli_prepare($link, '
        SELECT id, name, roll_name, dice_pool, difficulty, roll_results, successes, botch, willpower_spent, ip, rolled_at
        FROM fact_dice_rolls
        WHERE id = ?
        LIMIT 1
    ');
    if (!$stmtRoll) {
        hg_runtime_log_error('dice_api.prepare_select', mysqli_error($link));
        hg_tool_api_error('Could not prepare roll query.', 500);
        return;
    }

    mysqli_stmt_bind_param($stmtRoll, 'i', $rollId);
    mysqli_stmt_execute($stmtRoll);
    $resultRoll = mysqli_stmt_get_result($stmtRoll);
    $row = mysqli_fetch_assoc($resultRoll);
    mysqli_stmt_close($stmtRoll);

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

$difficultyRaw = $_GET['roll_diff'] ?? ($_GET['roll_dif'] ?? ($_GET['dificultad'] ?? null));
$extraDiceRaw = $_GET['extra_dice'] ?? null;
if ($difficultyRaw === null || $extraDiceRaw === null) {
    hg_tool_api_error('roll_diff and extra_dice are required.', 400);
    return;
}

$difficulty = (int)$difficultyRaw;
$extraDice = (int)$extraDiceRaw;
$characterId = (int)($_GET['char_id'] ?? ($_GET['character_id'] ?? 0));
$attrTraitId = (int)($_GET['attrib_id'] ?? ($_GET['attr_trait_id'] ?? 0));
$skillTraitId = (int)($_GET['skill_id'] ?? ($_GET['skill_trait_id'] ?? 0));
$resourceId = (int)($_GET['resource_id'] ?? 0);
$name = trim((string)($_GET['name'] ?? ($_GET['nombre'] ?? '')));
$rollName = trim((string)($_GET['roll_name'] ?? ($_GET['tirada_nombre'] ?? '')));
$willpowerSpent = isset($_GET['willpower_spent']) ? (int)$_GET['willpower_spent'] : 0;
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
    $profile = hg_api_fetch_roll_profile($link, $characterId);
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

$stmtLast = mysqli_prepare($link, 'SELECT rolled_at FROM fact_dice_rolls WHERE ip = ? ORDER BY rolled_at DESC LIMIT 1');
if ($stmtLast) {
    mysqli_stmt_bind_param($stmtLast, 's', $ip);
    mysqli_stmt_execute($stmtLast);
    $resLast = mysqli_stmt_get_result($stmtLast);
    if ($row = mysqli_fetch_assoc($resLast)) {
        if (strtotime((string)$row['rolled_at']) > time() - 10) {
            mysqli_stmt_close($stmtLast);
            hg_tool_api_error('Has tirado hace menos de 10 segundos.', 429);
            return;
        }
    }
    mysqli_stmt_close($stmtLast);
}

$stmtCount = mysqli_prepare($link, 'SELECT COUNT(*) AS total FROM fact_dice_rolls WHERE roll_name = ?');
if ($stmtCount) {
    mysqli_stmt_bind_param($stmtCount, 's', $rollName);
    mysqli_stmt_execute($stmtCount);
    $resCount = mysqli_stmt_get_result($stmtCount);
    $rowCount = mysqli_fetch_assoc($resCount);
    mysqli_stmt_close($stmtCount);
    if ((int)($rowCount['total'] ?? 0) > 0) {
        hg_tool_api_error('Generated roll_name collision. Retry the request.', 409);
        return;
    }
}

[$results, $successes, $botch] = hg_api_roll_d10_pool($dicePool, $difficulty, $debugForcedRolls);
if ($willpowerSpent === 1) {
    $successes++;
    $botch = false;
}

$rollResults = implode(',', $results);
$botchValue = $botch ? 1 : 0;
$stmtInsert = mysqli_prepare($link, 'INSERT INTO fact_dice_rolls (name, roll_name, dice_pool, difficulty, roll_results, successes, botch, willpower_spent, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (!$stmtInsert) {
    hg_runtime_log_error('dice_api.prepare_insert', mysqli_error($link));
    hg_tool_api_error('Could not prepare roll insert.', 500);
    return;
}

mysqli_stmt_bind_param($stmtInsert, 'ssiisiiis', $name, $rollName, $dicePool, $difficulty, $rollResults, $successes, $botchValue, $willpowerSpent, $ip);
if (!mysqli_stmt_execute($stmtInsert)) {
    $error = mysqli_stmt_error($stmtInsert);
    mysqli_stmt_close($stmtInsert);
    hg_runtime_log_error('dice_api.insert', $error);
    hg_tool_api_error('Could not save roll.', 500);
    return;
}

$lastId = mysqli_insert_id($link);
mysqli_stmt_close($stmtInsert);
if ($lastId <= 0) {
    hg_tool_api_error('Roll saved without a valid id.', 500);
    return;
}

if (!headers_sent()) {
    header('X-HG-Roll-Id: ' . $lastId);
}
hg_tool_api_send_text('[hg_tirada]' . $lastId . '[/hg_tirada]');
