<?php
require_once(__DIR__ . '/../../helpers/runtime_response.php');
require_once(__DIR__ . '/../../helpers/tool_api.php');
require_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!isset($link) || !($link instanceof mysqli)) {
    require_once(__DIR__ . '/../../helpers/db_connection.php');
}

if (!function_exists('hg_api_normalize_palette_value')) {
    function hg_api_normalize_palette_value(string $raw, string $fallback = 'SkyBlue'): string
    {
        $value = trim($raw);
        if ($value === '') {
            return $fallback;
        }
        if ($value === '3') {
            return 'SkyBlue';
        }
        if (preg_match('/^\$([0-9a-f]{3}|[0-9a-f]{6})$/i', $value, $m)) {
            return '#' . strtolower($m[1]);
        }
        if (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $value, $m)) {
            return '#' . strtolower($m[1]);
        }
        if (preg_match('/^(?:rgb|hsl)a?\(\s*[0-9.%\s,]+\s*\)$/i', $value)) {
            $clean = preg_replace('/\s+/', ' ', $value);
            return trim((string)$clean);
        }
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/', $value)) {
            return $value;
        }
        return $fallback;
    }
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    hg_tool_api_error('Method not allowed.', 405);
    return;
}

if (!hg_runtime_require_db($link, 'forum_avatar_api', 'plain', [
    'title' => 'API no disponible',
    'message' => 'No se pudo conectar a la base de datos.',
    'status' => 500,
])) {
    return;
}

if (!hg_tool_api_require_token((string)($_GET['token'] ?? ''))) {
    return;
}

$charRefRaw = isset($_GET['char_id']) ? (string)$_GET['char_id'] : (string)($_GET['id'] ?? '');
$charRef = hg_character_avatar_parse_ref($charRefRaw);
$characterId = (int)($charRef['character_id'] ?? 0);
$variantCode = (string)($charRef['variant_code'] ?? '');
$msg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
$msg = str_replace(["\r\n", "\r"], "\n", $msg);
$paletteRaw = isset($_GET['palette']) ? (string)$_GET['palette'] : (string)($_GET['color'] ?? '');
$paletteRaw = trim($paletteRaw);

if ($characterId === 0) {
    hg_tool_api_error('Invalid or missing char_id.', 400);
    return;
}

if (!in_array($characterId, [-1, -2, -3, -4], true) && $characterId < 1) {
    hg_tool_api_error('Invalid char_id.', 400);
    return;
}

if (trim($msg) === '') {
    hg_tool_api_error('Missing msg.', 400);
    return;
}

if ($characterId > 0) {
    $stmt = mysqli_prepare($link, 'SELECT id FROM fact_characters WHERE id = ? LIMIT 1');
    if (!$stmt) {
        hg_runtime_log_error('forum_avatar_api.prepare_character', mysqli_error($link));
        hg_tool_api_error('Could not validate character.', 500);
        return;
    }
    mysqli_stmt_bind_param($stmt, 'i', $characterId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = (bool)mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$exists) {
        hg_tool_api_error('Character not found.', 404);
        return;
    }

    if ($variantCode !== '' && hg_character_avatar_variants_table_exists($link) && hg_character_avatar_variant_image_url($link, $characterId, $variantCode) === '') {
        hg_tool_api_error('Avatar variant not found.', 404);
        return;
    }
}

$refText = $variantCode !== '' ? ($characterId . ':' . $variantCode) : (string)$characterId;
if ($paletteRaw === '' || strcasecmp($paletteRaw, '__CHAR_DEFAULT__') === 0 || strcasecmp($paletteRaw, 'default') === 0) {
    hg_tool_api_send_text('[hg_avatar=' . $refText . ']' . $msg . '[/hg_avatar]');
    return;
}

$palette = hg_api_normalize_palette_value($paletteRaw, '');
if ($palette === '') {
    hg_tool_api_error('Invalid palette.', 400);
    return;
}

hg_tool_api_send_text('[hg_avatar=' . $refText . ',' . $palette . ']' . $msg . '[/hg_avatar]');
