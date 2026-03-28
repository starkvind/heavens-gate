<?php

$hgfvEmbedded = defined('HG_FORUM_TOPIC_VIEWER_EMBED') && HG_FORUM_TOPIC_VIEWER_EMBED;

if (!isset($link) || !$link) {
    require_once __DIR__ . '/../helpers/db_connection.php';
}
require_once __DIR__ . '/../helpers/character_avatar.php';

if (!isset($link) || !$link) {
    die('DB connection error.');
}

mysqli_set_charset($link, 'utf8mb4');

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hg_normalize_palette_value($raw, $fallback = 'SkyBlue')
{
    $v = trim((string)$raw);
    if ($v === '') {
        return $fallback;
    }
    if ($v === '3') {
        return 'SkyBlue';
    }

    if (preg_match('/^\$([0-9a-f]{3}|[0-9a-f]{6})$/i', $v, $m)) {
        return '#' . strtolower($m[1]);
    }
    if (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $v, $m)) {
        return '#' . strtolower($m[1]);
    }
    if (preg_match('/^(?:rgb|hsl)a?\(\s*[0-9.%\s,]+\s*\)$/i', $v)) {
        return trim((string)preg_replace('/\s+/', ' ', $v));
    }
    if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/', $v)) {
        return $v;
    }

    return $fallback;
}

function parse_bbcode_inline($rawText, $convertBreaks = true)
{
    $text = (string)$rawText;

    $text = preg_replace('/\[\s*\/li\s*\]\s*\n\s*\[\s*li\s*\]/i', '[/li][li]', $text);
    $text = preg_replace('/\[\s*\/list\s*\]\s*\n/i', '[/list]', $text);
    $text = preg_replace('/\[\s*list\s*\]\s*\n/i', '[list]', $text);

    // [spoiler]...[/spoiler] y [spoiler=Titulo]...[/spoiler]
    $text = preg_replace_callback(
        '/\[spoiler(?:=([^\]]+))?\](.*?)\[\/spoiler\]/is',
        static function ($m) {
            $title = trim((string)($m[1] ?? ''));
            $content = (string)($m[2] ?? '');
            if ($title === '') {
                $title = 'Spoiler';
            }
            return '<details class="hg-bb-spoiler"><summary>' . h($title) . '</summary><div class="hg-bb-spoiler-body">' . $content . '</div></details>';
        },
        $text
    );

    // [size=3]...[/size] (estilo SMF): mapea 1..7 y acepta unidades seguras.
    $text = preg_replace_callback(
        '/\[size=([^\]]+)\](.*?)\[\/size\]/is',
        static function ($m) {
            $raw = trim((string)($m[1] ?? ''));
            $content = (string)($m[2] ?? '');

            $mapped = '';
            if (preg_match('/^\d+$/', $raw)) {
                $n = (int)$raw;
                $legacyMap = [
                    1 => '0.72rem',
                    2 => '0.86rem',
                    3 => '1.00rem',
                    4 => '1.18rem',
                    5 => '1.40rem',
                    6 => '1.72rem',
                    7 => '2.08rem',
                ];
                if (isset($legacyMap[$n])) {
                    $mapped = $legacyMap[$n];
                } else {
                    $px = max(8, min(64, $n));
                    $mapped = $px . 'px';
                }
            } elseif (preg_match('/^(\d+(?:\.\d+)?)(px|em|rem|%)$/i', $raw, $u)) {
                $value = (float)$u[1];
                $unit = strtolower((string)$u[2]);
                if ($unit === 'px') {
                    $value = max(8.0, min(64.0, $value));
                } elseif ($unit === '%') {
                    $value = max(50.0, min(300.0, $value));
                } else {
                    $value = max(0.6, min(3.2, $value));
                }
                $mapped = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . $unit;
            } else {
                $mapped = '1rem';
            }

            return '<span class="hg-bb-size" style="font-size:' . h($mapped) . ';">' . $content . '</span>';
        },
        $text
    );

    $text = preg_replace([
        '/\[b\](.*?)\[\/b\]/is',
        '/\[i\](.*?)\[\/i\]/is',
        '/\[u\](.*?)\[\/u\]/is',
        '/\[left\](.*?)\[\/left\]/is',
        '/\[center\](.*?)\[\/center\]/is',
        '/\[right\](.*?)\[\/right\]/is',
        '/\[list\](.*?)\[\/list\]/is',
        '/\[li\](.*?)\[\/li\]/is',
        '/\[url="(https?:\/\/[^"]+)"\](.*?)\[\/url\]/is',
        '/\[url=(https?:\/\/[^\]]+)\](.*?)\[\/url\]/is',
        '/\[url\](https?:\/\/[^\s\]]+)\[\/url\]/is',
    ], [
        '<strong>$1</strong>',
        '<em>$1</em>',
        '<u>$1</u>',
        '<div class="hg-bb-align-left">$1</div>',
        '<div class="hg-bb-align-center">$1</div>',
        '<div class="hg-bb-align-right">$1</div>',
        '<ul>$1</ul>',
        '<li>$1</li>',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
    ], $text);

    $text = preg_replace_callback(
        '/\[img(?:\s+width=(\d+))?\](https?:\/\/[^\]]+)\[\/img\]/is',
        static function ($m) {
            $width = isset($m[1]) ? (int)$m[1] : 0;
            $src = h($m[2]);
            $style = $width > 0 ? ' style="max-width:100%;width:' . $width . 'px;"' : '';
            return '<img class="bb-img" src="' . $src . '" alt="imagen foro"' . $style . '>';
        },
        $text
    );

    $text = preg_replace_callback(
        '/\[quote([^\]]*)\](.*?)\[\/quote\]/is',
        static function ($m) {
            $attrRaw = trim((string)($m[1] ?? ''));
            $content = $m[2] ?? '';

            $attrs = [];
            if ($attrRaw !== '') {
                preg_match_all('/([a-z_]+)=("([^"]*)"|\'([^\']*)\'|([^\s\]]+))/i', $attrRaw, $parts, PREG_SET_ORDER);
                foreach ($parts as $p) {
                    $key = strtolower((string)($p[1] ?? ''));
                    $val = '';
                    if (isset($p[3]) && $p[3] !== '') {
                        $val = (string)$p[3];
                    } elseif (isset($p[4]) && $p[4] !== '') {
                        $val = (string)$p[4];
                    } else {
                        $val = (string)($p[5] ?? '');
                    }
                    if ($key !== '') {
                        $attrs[$key] = $val;
                    }
                }
            }

            $author = trim((string)($attrs['author'] ?? ''));
            $label = $author !== '' ? 'Cita de ' . h($author) : 'Cita';

            $unix = isset($attrs['date']) ? (int)$attrs['date'] : 0;
            if ($unix > 0) {
                $label .= ' | ' . h(date('Y-m-d H:i:s', $unix));
            }

            return '<blockquote><div class="bb-quote-head">' . $label . '</div>' . $content . '</blockquote>';
        },
        $text
    );

    $text = preg_replace_callback(
        '/\[youtube\]([a-zA-Z0-9_-]{6,20})\[\/youtube\]/i',
        static function ($m) {
            $id = h($m[1]);
            return '<div class="bb-youtube"><iframe loading="lazy" src="https://www.youtube-nocookie.com/embed/' . $id . '" title="YouTube" allowfullscreen></iframe></div>';
        },
        $text
    );

    $text = preg_replace('/\[hr\]/i', '<hr>', $text);

    if ($convertBreaks) {
        $text = nl2br($text, false);
    }

    $text = preg_replace([
        '/<br\s*\/?>\s*(<div[^>]*>)/i',
        '/(<\/div>)\s*<br\s*\/?>/i',
        '/<br\s*\/?>\s*(<ul[^>]*>)/i',
        '/(<\/ul>)\s*<br\s*\/?>/i',
        '/<br\s*\/?>\s*(<li[^>]*>)/i',
        '/(<\/li>)\s*<br\s*\/?>/i',
        '/<br\s*\/?>\s*(<blockquote[^>]*>)/i',
        '/(<\/blockquote>)\s*<br\s*\/?>/i',
        '/<br\s*\/?>\s*(<details[^>]*>)/i',
        '/(<\/details>)\s*<br\s*\/?>/i',
        '/<br\s*\/?>\s*(<summary[^>]*>)/i',
        '/(<\/summary>)\s*<br\s*\/?>/i',
        '/<br\s*\/?>\s*(<hr[^>]*>)/i',
        '/(<hr[^>]*>)\s*<br\s*\/?>/i',
    ], [
        '$1', '$1', '$1', '$1', '$1', '$1', '$1', '$1', '$1', '$1', '$1', '$1', '$1', '$1',
    ], $text);

    $text = preg_replace('/^(?:(?:\s|&nbsp;|<br\s*\/?>)+|<p>\s*(?:&nbsp;|<br\s*\/?>|\s)*<\/p>)+/i', '', $text);

    return $text;
}

function get_character_embed_data($link, $charId)
{
    static $cache = [];
    if (isset($cache[$charId])) {
        return $cache[$charId];
    }

    $defaultAvatars = [
        -1 => ['name' => 'Hombre', 'img' => '/public/img/ui/avatar/avatar_nadie_1.png'],
        -2 => ['name' => 'Mujer', 'img' => '/public/img/ui/avatar/avatar_nadie_2.png'],
        -3 => ['name' => 'Silueta', 'img' => '/public/img/ui/avatar/avatar_nadie_3.png'],
        -4 => ['name' => 'Espiritu', 'img' => '/public/img/ui/avatar/avatar_nadie_4.png'],
    ];

    if (isset($defaultAvatars[$charId])) {
        $cache[$charId] = [
            'name' => $defaultAvatars[$charId]['name'],
            'img' => $defaultAvatars[$charId]['img'],
            'pretty_id' => (string)$charId,
            'text_color' => '',
            'is_default' => true,
        ];
        return $cache[$charId];
    }

    $data = [
        'name' => 'Desconocido',
        'img' => '/public/img/ui/avatar/avatar_nadie_3.png',
        'pretty_id' => (string)$charId,
        'text_color' => '',
        'is_default' => true,
    ];

    if ($charId > 0) {
        $sql = 'SELECT name, image_url, gender, text_color, pretty_id FROM fact_characters WHERE id = ? LIMIT 1';
        $stmt = mysqli_prepare($link, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $charId);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                if ($row = mysqli_fetch_assoc($res)) {
                    $avatar = hg_character_avatar_url((string)($row['image_url'] ?? ''), (string)($row['gender'] ?? ''));
                    if (strpos($avatar, '/') !== 0) {
                        $avatar = '/' . ltrim((string)$avatar, '/');
                    }

                    $pretty = trim((string)($row['pretty_id'] ?? ''));
                    if ($pretty === '') {
                        $pretty = (string)$charId;
                    }

                    $data = [
                        'name' => (string)($row['name'] ?? 'Desconocido'),
                        'img' => $avatar,
                        'pretty_id' => $pretty,
                        'text_color' => hg_normalize_palette_value((string)($row['text_color'] ?? ''), ''),
                        'is_default' => false,
                    ];
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    $cache[$charId] = $data;
    return $data;
}

function render_hg_avatar_inline($link, $charId, $paletteRaw, $msgRaw)
{
    $char = get_character_embed_data($link, (int)$charId);
    $palette = hg_normalize_palette_value($paletteRaw, 'SkyBlue');
    if ($char['text_color'] !== '' && $palette === 'SkyBlue') {
        $palette = $char['text_color'];
    }

    $parsedMsg = parse_bbcode_inline(trim((string)$msgRaw), true);

    $html = '<div class="hg-inline-message" style="--palette:' . h($palette) . ';">';
    $html .= '<div class="msg_main_box">';

    if (!$char['is_default']) {
        $profileHref = '/characters/' . rawurlencode((string)$char['pretty_id']);
        $html .= '<a class="img_link" href="' . h($profileHref) . '" target="_blank" rel="noopener noreferrer">';
        $html .= '<img class="msg_face" src="' . h($char['img']) . '" alt="avatar">';
        $html .= '</a>';
    } else {
        $html .= '<img class="msg_face" src="' . h($char['img']) . '" alt="avatar">';
    }

    $html .= '<div class="msg_body">';
    if (!$char['is_default']) {
        $html .= '<div class="msg_name_box">' . h($char['name']) . '</div>';
        $html .= '<div class="msg_container">' . $parsedMsg . '</div>';
    } else {
        $html .= '<div class="msg_container msg_container--plain">' . $parsedMsg . '</div>';
    }
    $html .= '</div></div></div>';

    return $html;
}

function render_hg_tirada_inline($link, $rollId, $paletteRaw = '')
{
    static $cache = [];
    $cacheKey = (int)$rollId . '|' . (string)$paletteRaw;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $rollId = (int)$rollId;
    if ($rollId <= 0) {
        return '<div class="inline-error">Tirada inválida.</div>';
    }

    $sql = 'SELECT * FROM fact_dice_rolls WHERE id = ? LIMIT 1';
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        return '<div class="inline-error">No se pudo preparar la tirada.</div>';
    }

    mysqli_stmt_bind_param($stmt, 'i', $rollId);
    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) {
        mysqli_stmt_close($stmt);
        return '<div class="inline-error">No se pudo ejecutar la tirada.</div>';
    }

    $res = mysqli_stmt_get_result($stmt);
    $roll = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$roll) {
        return '<div class="inline-error">Tirada #' . $rollId . ' no encontrada.</div>';
    }

    $nombre = h($roll['name'] ?? 'Desconocido');
    $titulo = h($roll['roll_name'] ?? ('Tirada #' . $rollId));
    $dificultad = (int)($roll['difficulty'] ?? 6);
    $resultadosRaw = trim((string)($roll['roll_results'] ?? ''));
    $resultados = $resultadosRaw === '' ? [] : explode(',', $resultadosRaw);
    $exitos = (int)($roll['successes'] ?? 0);
    $pifia = !empty($roll['botch']);
    $willpowerSpent = !empty($roll['willpower_spent']);

    $palette = $pifia ? '#3A1010' : '#05014E';
    $customPalette = trim((string)$paletteRaw);
    if ($customPalette !== '') {
        $palette = hg_normalize_palette_value($customPalette, $palette);
    }

    $diceHtml = '';
    foreach ($resultados as $dadoRaw) {
        $dado = (int)$dadoRaw;
        $color = ($dado === 1) ? '#f55' : (($dado >= $dificultad) ? '#5f5' : '#5ff');
        $diceHtml .= '<div class="dado" style="--die-color:' . h($color) . ';"><span>' . $dado . '</span></div>';
    }

    $html = '<div class="hg-inline-roll" style="--palette:' . h($palette) . ';">';
    $html .= '<div class="roll-main-box"><div class="roll-box">';
    $html .= '<div class="roll-box-name">' . $titulo . '</div>';
    $html .= '<p class="roll-head"><strong>' . $nombre . '</strong> lanzo ' . count($resultados) . 'd10 a dificultad <strong>' . $dificultad . '</strong>.</p>';
    $html .= '<div class="roll-results">' . $diceHtml . '</div>';
    $html .= '<p><strong>Exitos</strong>: ' . $exitos;
    if ($willpowerSpent) {
        $html .= ' <span>(+1 por Fuerza de Voluntad)</span>';
    }
    $html .= '</p>';
    if ($pifia) {
        $html .= '<p class="roll-botch"><strong>¡PIFIA!</strong></p>';
    }
    $html .= '</div></div></div>';

    $cache[$cacheKey] = $html;
    return $html;
}

function parse_forum_body($link, $body)
{
    $text = htmlspecialchars_decode((string)$body, ENT_QUOTES | ENT_HTML5);
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    $embeds = [];
    $embedIdx = 0;

    $stashEmbed = static function ($html) use (&$embeds, &$embedIdx) {
        $key = '__HG_EMBED_' . $embedIdx . '__';
        $embeds[$key] = $html;
        $embedIdx++;
        return $key;
    };

    $text = preg_replace_callback(
        '/\[hg_tirada\]\s*(\d+)\s*\[\/hg_tirada\]/i',
        static function ($m) use ($stashEmbed, $link) {
            $id = (int)$m[1];
            $html = render_hg_tirada_inline($link, $id);
            return $stashEmbed($html);
        },
        $text
    );

    $text = preg_replace_callback(
        '/\[hg_avatar\s*=\s*([0-9-]+)(?:\s*,\s*([^\]]+))?\s*\](.*?)\[\/hg_avatar\]/is',
        static function ($m) use ($stashEmbed, $link) {
            $charId = (int)$m[1];
            $palette = isset($m[2]) ? trim((string)$m[2]) : '';
            $msg = trim((string)$m[3]);
            $html = render_hg_avatar_inline($link, $charId, $palette, $msg);
            return $stashEmbed($html);
        },
        $text
    );

    $text = parse_bbcode_inline($text, true);

    if (!empty($embeds)) {
        $text = strtr($text, $embeds);
    }

    return $text;
}

function hgfv_table_exists($link, $tableName)
{
    $safe = mysqli_real_escape_string($link, (string)$tableName);
    $rs = mysqli_query($link, "SHOW TABLES LIKE '$safe'");
    return ($rs && mysqli_num_rows($rs) > 0);
}

function hgfv_pick_smf_table($link, $tableBaseName)
{
    $base = trim((string)$tableBaseName);
    if ($base === '') {
        return '';
    }

    if (hgfv_table_exists($link, $base)) {
        return $base;
    }

    $sql = "SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = 'smf' AND TABLE_NAME = ?
            LIMIT 1";
    $stmt = mysqli_prepare($link, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $base);
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        $existsInSmf = ($rs && mysqli_num_rows($rs) > 0);
        mysqli_stmt_close($stmt);
        if ($existsInSmf) {
            return 'smf.' . $base;
        }
    }

    return '';
}

function hgfv_column_exists($link, $tableName, $columnName)
{
    $safeTable = mysqli_real_escape_string($link, (string)$tableName);
    $safeCol = mysqli_real_escape_string($link, (string)$columnName);
    $sql = "SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '$safeTable'
              AND COLUMN_NAME = '$safeCol'
            LIMIT 1";
    $rs = mysqli_query($link, $sql);
    return ($rs && mysqli_num_rows($rs) > 0);
}

function hgfv_normalize_author_avatar_url($raw)
{
    $v = trim((string)$raw);
    if ($v === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $v)) {
        return $v;
    }
    if (strpos($v, '//') === 0) {
        return 'https:' . $v;
    }
    return 'https://naufragio-foros.duckdns.org/' . ltrim($v, '/');
}

function hgfv_author_initial($name)
{
    $name = trim((string)$name);
    if ($name === '') {
        return '?';
    }
    if (function_exists('mb_substr')) {
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return strtoupper(substr($name, 0, 1));
}

function hgfv_scope_type_label($type)
{
    switch ((string)$type) {
        case 'character': return 'Personaje';
        case 'group': return 'Grupo';
        case 'organization': return 'Organización';
        default: return 'Sin agrupación';
    }
}

function hgfv_scope_name_from_row(array $row)
{
    $scopeType = (string)($row['scope_type'] ?? '');
    $scopeId = (int)($row['scope_id'] ?? 0);

    if ($scopeType === 'character' && trim((string)($row['scope_character_name'] ?? '')) !== '') {
        return trim((string)$row['scope_character_name']);
    }
    if ($scopeType === 'group' && trim((string)($row['scope_group_name'] ?? '')) !== '') {
        return trim((string)$row['scope_group_name']);
    }
    if ($scopeType === 'organization' && trim((string)($row['scope_organization_name'] ?? '')) !== '') {
        return trim((string)$row['scope_organization_name']);
    }
    if ($scopeId > 0) {
        return hgfv_scope_type_label($scopeType) . ' #' . $scopeId;
    }
    return 'Sin agrupación';
}

function hgfv_scope_href_from_row(array $row)
{
    $scopeType = (string)($row['scope_type'] ?? '');
    $scopeId = (int)($row['scope_id'] ?? 0);
    if ($scopeId <= 0) {
        return '';
    }

    if ($scopeType === 'character') {
        $slug = trim((string)($row['scope_character_pretty_id'] ?? ''));
        return '/characters/' . rawurlencode($slug !== '' ? $slug : (string)$scopeId);
    }
    if ($scopeType === 'group') {
        $slug = trim((string)($row['scope_group_pretty_id'] ?? ''));
        return '/groups/' . rawurlencode($slug !== '' ? $slug : (string)$scopeId);
    }
    if ($scopeType === 'organization') {
        $slug = trim((string)($row['scope_organization_pretty_id'] ?? ''));
        return '/organizations/' . rawurlencode($slug !== '' ? $slug : (string)$scopeId);
    }
    return '';
}

function hgfv_chapter_href_from_row(array $row)
{
    $chapterId = (int)($row['chapter_id'] ?? 0);
    if ($chapterId <= 0) {
        return '';
    }
    $slug = trim((string)($row['chapter_pretty_id'] ?? ''));
    return '/chapters/' . rawurlencode($slug !== '' ? $slug : (string)$chapterId);
}

$topicId = filter_input(INPUT_GET, 'id_topic', FILTER_VALIDATE_INT);
$topicId = $topicId ? (int)$topicId : 0;

$messages = [];
$savedTopics = [];
$savedTopicsGrouped = [];
$savedTopicsByTopicId = [];
$selectedSavedTopic = null;
$hasSavedTopicsTable = false;
$query = '';
$error = '';
$smfMessagesTable = hgfv_pick_smf_table($link, 'smf_messages');
$smfMembersTable = hgfv_pick_smf_table($link, 'smf_members');
$smfAttachmentsTable = hgfv_pick_smf_table($link, 'smf_attachments');
$hasChapterIdCol = false;
$hasScopeTypeCol = false;
$hasScopeIdCol = false;

if (hgfv_table_exists($link, 'fact_tools_topic_viewer')) {
    $hasSavedTopicsTable = true;
    $hasChapterIdCol = hgfv_column_exists($link, 'fact_tools_topic_viewer', 'chapter_id');
    $hasScopeTypeCol = hgfv_column_exists($link, 'fact_tools_topic_viewer', 'link_scope_type');
    $hasScopeIdCol = hgfv_column_exists($link, 'fact_tools_topic_viewer', 'link_scope_id');
    $supportsEpisodeAndScope = $hasChapterIdCol && $hasScopeTypeCol && $hasScopeIdCol;

    if ($supportsEpisodeAndScope) {
    $rsSaved = mysqli_query(
        $link,
        "SELECT
            ftv.id,
            ftv.topic_name,
            ftv.topic_id,
            ftv.topic_url,
            ftv.topic_description,
            ftv.sort_order,
            ftv.is_active,
            ftv.chapter_id,
            ftv.link_scope_type AS scope_type,
            ftv.link_scope_id AS scope_id,
            dc.pretty_id AS chapter_pretty_id,
            dc.name AS chapter_name,
            dc.chapter_number,
            dc.played_date,
            ds.id AS season_id,
            ds.pretty_id AS season_pretty_id,
            ds.name AS season_name,
            ds.season_number,
            fc.name AS scope_character_name,
            fc.pretty_id AS scope_character_pretty_id,
            dg.name AS scope_group_name,
            dg.pretty_id AS scope_group_pretty_id,
            do2.name AS scope_organization_name,
            do2.pretty_id AS scope_organization_pretty_id
         FROM fact_tools_topic_viewer ftv
         LEFT JOIN dim_chapters dc ON dc.id = ftv.chapter_id
         LEFT JOIN dim_seasons ds ON ds.id = dc.season_id
         LEFT JOIN fact_characters fc
            ON ftv.link_scope_type = 'character'
           AND ftv.link_scope_id = fc.id
         LEFT JOIN dim_groups dg
            ON ftv.link_scope_type = 'group'
           AND ftv.link_scope_id = dg.id
         LEFT JOIN dim_organizations do2
            ON ftv.link_scope_type = 'organization'
           AND ftv.link_scope_id = do2.id
         WHERE ftv.is_active = 1
         ORDER BY
            COALESCE(ftv.link_scope_type, 'zzz') ASC,
            COALESCE(ftv.link_scope_id, 0) ASC,
            ftv.sort_order ASC,
            ftv.topic_name ASC,
            ftv.id ASC"
    );
    if ($rsSaved) {
        while ($rowSaved = mysqli_fetch_assoc($rsSaved)) {
            $seasonName = trim((string)($rowSaved['season_name'] ?? ''));
            $seasonNumber = (int)($rowSaved['season_number'] ?? 0);
            $chapterNumber = (int)($rowSaved['chapter_number'] ?? 0);
            $chapterName = trim((string)($rowSaved['chapter_name'] ?? ''));

            $chapterBadge = '';
            if ($seasonName !== '') {
                $chapterBadge = $seasonName;
                if ($seasonNumber > 0) {
                    $chapterBadge .= ' (T' . $seasonNumber . ')';
                }
            }
            if ($chapterNumber > 0) {
                $chapterBadge .= ($chapterBadge !== '' ? ' · ' : '') . 'Ep. ' . $chapterNumber;
            }
            if ($chapterName !== '') {
                $chapterBadge .= ($chapterBadge !== '' ? ' · ' : '') . $chapterName;
            }
            $rowSaved['chapter_badge'] = $chapterBadge;

            $scopeType = (string)($rowSaved['scope_type'] ?? '');
            $scopeId = (int)($rowSaved['scope_id'] ?? 0);
            $scopeLabel = hgfv_scope_type_label($scopeType);
            $scopeName = hgfv_scope_name_from_row($rowSaved);
            $scopeHref = hgfv_scope_href_from_row($rowSaved);
            $scopeKey = ($scopeType !== '' && $scopeId > 0) ? ($scopeType . ':' . $scopeId) : 'none';
            $scopeGroupTitle = $scopeName;
            if ($scopeKey === 'none') {
                $scopeGroupTitle = 'Sin agrupación';
            } elseif ($scopeLabel !== '') {
                $scopeGroupTitle = $scopeLabel . ' · ' . $scopeName;
            }

            $rowSaved['scope_label'] = $scopeLabel;
            $rowSaved['scope_name'] = $scopeName;
            $rowSaved['scope_href'] = $scopeHref;
            $rowSaved['scope_key'] = $scopeKey;
            $rowSaved['scope_group_title'] = $scopeGroupTitle;
            $rowSaved['chapter_href'] = hgfv_chapter_href_from_row($rowSaved);

            $savedTopics[] = $rowSaved;
            $savedTopicsByTopicId[(int)($rowSaved['topic_id'] ?? 0)] = $rowSaved;

            if (!isset($savedTopicsGrouped[$scopeKey])) {
                $savedTopicsGrouped[$scopeKey] = [
                    'title' => $scopeGroupTitle,
                    'items' => [],
                ];
            }
            $savedTopicsGrouped[$scopeKey]['items'][] = $rowSaved;
        }
        mysqli_free_result($rsSaved);
    }
    } else {
        $rsSaved = mysqli_query(
            $link,
            "SELECT id, topic_name, topic_id, topic_url, topic_description, sort_order, is_active
             FROM fact_tools_topic_viewer
             WHERE is_active = 1
             ORDER BY sort_order ASC, topic_name ASC, id ASC"
        );
        if ($rsSaved) {
            while ($rowSaved = mysqli_fetch_assoc($rsSaved)) {
                $rowSaved['chapter_badge'] = '';
                $rowSaved['scope_label'] = 'Sin agrupación';
                $rowSaved['scope_name'] = 'Sin agrupación';
                $rowSaved['scope_href'] = '';
                $rowSaved['scope_key'] = 'none';
                $rowSaved['scope_group_title'] = 'Sin agrupación';
                $rowSaved['chapter_href'] = '';
                $savedTopics[] = $rowSaved;
                $savedTopicsByTopicId[(int)($rowSaved['topic_id'] ?? 0)] = $rowSaved;
                if (!isset($savedTopicsGrouped['none'])) {
                    $savedTopicsGrouped['none'] = ['title' => 'Sin agrupación', 'items' => []];
                }
                $savedTopicsGrouped['none']['items'][] = $rowSaved;
            }
            mysqli_free_result($rsSaved);
        }
    }
}

if ($topicId > 0 && isset($savedTopicsByTopicId[$topicId])) {
    $selectedSavedTopic = $savedTopicsByTopicId[$topicId];
}

if ($topicId > 0) {
    if ($smfMessagesTable === '') {
        $error = 'No se encontro la tabla smf_messages (ni local ni en esquema smf).';
    } else {
        if ($smfMembersTable !== '' && $smfAttachmentsTable !== '') {
            $query = "SELECT
                        sm.subject,
                        COALESCE(smm.real_name, sm.poster_name) AS poster_name,
                        sm.poster_time,
                        sm.body,
                        sm.id_member,
                        COALESCE(
                            MAX(CASE WHEN sa.filename IS NULL OR sa.filename = '' THEN NULL ELSE CONCAT('https://naufragio-foros.duckdns.org/custom_avatar/', sa.filename) END),
                            MAX(NULLIF(smm.avatar, '')),
                            NULL
                        ) AS poster_avatar
                      FROM {$smfMessagesTable} sm
                      LEFT JOIN {$smfMembersTable} smm ON smm.id_member = sm.id_member
                      LEFT JOIN {$smfAttachmentsTable} sa ON sm.id_member = sa.id_member AND sa.attachment_type = 1
                      WHERE sm.id_topic = ?
                      GROUP BY sm.id_msg, sm.subject, sm.poster_name, sm.poster_time, sm.body, sm.id_member, smm.real_name
                      ORDER BY sm.poster_time ASC, sm.id_msg ASC";
        } elseif ($smfMembersTable !== '') {
            $query = "SELECT
                        sm.subject,
                        COALESCE(smm.real_name, sm.poster_name) AS poster_name,
                        sm.poster_time,
                        sm.body,
                        sm.id_member,
                        NULLIF(smm.avatar, '') AS poster_avatar
                      FROM {$smfMessagesTable} sm
                      LEFT JOIN {$smfMembersTable} smm ON smm.id_member = sm.id_member
                      WHERE sm.id_topic = ?
                      ORDER BY sm.poster_time ASC, sm.id_msg ASC";
        } else {
            $query = "SELECT
                        sm.subject,
                        sm.poster_name,
                        sm.poster_time,
                        sm.body,
                        sm.id_member,
                        NULL AS poster_avatar
                      FROM {$smfMessagesTable} sm
                      WHERE sm.id_topic = ?
                      ORDER BY sm.poster_time ASC, sm.id_msg ASC";
        }

        $stmt = mysqli_prepare($link, $query);
        if (!$stmt) {
            $error = 'No se pudo preparar la consulta: ' . mysqli_error($link);
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $topicId);
            if (!mysqli_stmt_execute($stmt)) {
                $error = 'No se pudo ejecutar la consulta: ' . mysqli_stmt_error($stmt);
            } else {
                $res = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($res)) {
                    $messages[] = $row;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$metaTitle = "Visor de temas de foro | Heaven's Gate";
$metaDescription = "Visualiza temas del foro con render BBCode incluyendo hg_avatar y hg_tirada.";
$panelTitle = 'Visualizador de mensajes SMF (BBCode + snippets)';

if (is_array($selectedSavedTopic)) {
    $chapterName = trim((string)($selectedSavedTopic['chapter_name'] ?? ''));
    $topicName = trim((string)($selectedSavedTopic['topic_name'] ?? ''));
    $seasonName = trim((string)($selectedSavedTopic['season_name'] ?? ''));
    $chapterNumber = (int)($selectedSavedTopic['chapter_number'] ?? 0);
    $topicDesc = trim((string)($selectedSavedTopic['topic_description'] ?? ''));

    $pieces = [];
    if ($chapterName !== '') {
        $pieces[] = $chapterName;
    }
    if ($seasonName !== '') {
        $pieces[] = $seasonName;
    }
    if ($chapterNumber > 0) {
        $pieces[] = 'Ep. ' . $chapterNumber;
    }
    $pieces[] = "Foro";
    $pieces[] = "Heaven's Gate";
    $metaTitle = implode(' | ', $pieces);

    if ($topicDesc !== '') {
        $metaDescription = $topicDesc;
    } else {
        $descPieces = [];
        if ($topicName !== '') {
            $descPieces[] = $topicName;
        }
        if ($chapterName !== '') {
            $descPieces[] = 'Episodio: ' . $chapterName;
        }
        if ($seasonName !== '') {
            $descPieces[] = 'Temporada: ' . $seasonName;
        }
        $metaDescription = !empty($descPieces)
            ? implode(' | ', $descPieces)
            : $metaDescription;
    }

    if ($chapterName !== '') {
        $panelTitle = 'Foro del episodio: ' . $chapterName;
    } elseif ($topicName !== '') {
        $panelTitle = $topicName;
    }
} elseif (!empty($messages)) {
    $firstSubject = trim((string)($messages[0]['subject'] ?? ''));
    if ($firstSubject !== '') {
        $metaTitle = $firstSubject . " | Foro | Heaven's Gate";
        $metaDescription = "Tema del foro: " . $firstSubject;
        $panelTitle = $firstSubject;
    }
}

if (function_exists('setMetaFromPage')) {
    setMetaFromPage($metaTitle, $metaDescription, null, 'article');
}

?>
<?php
$hgfvAssets = <<<'HTML'
<link href="/assets/vendor/fonts/quicksand/quicksand.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/hg-embeds.css">
<style>
    .hgfv-root {
        --bg: #f2f5fb;
        --card: #ffffff;
        --ink: #172033;
        --muted: #54607a;
        --line: #d7dff0;
        --accent: #1f4ab8;
        --accent-2: #7897e6;
        font-family: "Segoe UI", Tahoma, sans-serif;
        color: var(--ink);
        background: radial-gradient(circle at top, #ffffff 0%, var(--bg) 55%);
    }
    .hgfv-root * { box-sizing: border-box; }
    .hgfv-wrap {
        max-width: 1100px;
        margin: 0 auto;
        padding: 24px 16px 42px;
    }
    .hgfv-panel {
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 16px;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.07);
    }
    .hgfv-top {
        display: grid;
        gap: 12px;
        margin-bottom: 18px;
    }
    .hgfv-top h1 {
        margin: 0;
        font-size: 1.3rem;
    }
    .hgfv-form-row {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    .hgfv-form-row input {
        width: 160px;
        padding: 10px 12px;
        border-radius: 8px;
        border: 1px solid #b9c7ea;
        font-size: 1rem;
    }
    .hgfv-select-block {
        border: 1px dashed #c4d2f2;
        background: #f8fbff;
        border-radius: 10px;
        padding: 10px 12px;
    }
    .hgfv-select-wrap {
        position: relative;
        width: 100%;
        max-width: 560px;
    }
    .hgfv-select-wrap::after {
        content: '▾';
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #5a6ea7;
        font-size: 0.95rem;
        pointer-events: none;
    }
    .hgfv-form-row select {
        width: 100%;
        max-width: 520px;
        padding: 10px 12px;
        border-radius: 8px;
        border: 1px solid #b9c7ea;
        font-size: 0.95rem;
        background: #fff;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        cursor: pointer;
        box-shadow: 0 1px 4px rgba(20, 40, 90, 0.08);
    }
    .hgfv-form-row select:focus {
        outline: 2px solid #8aa6ef;
        border-color: #6b8fe0;
    }
    .hgfv-select-help {
        margin: 8px 0 0;
        color: var(--muted);
        font-size: 0.86rem;
    }
    .hgfv-form-row button {
        padding: 10px 14px;
        border: 0;
        border-radius: 8px;
        background: linear-gradient(140deg, var(--accent), var(--accent-2));
        color: #fff;
        font-weight: 700;
        cursor: pointer;
    }
    .hgfv-query-box {
        margin-top: 8px;
        background: #0f1724;
        color: #d6e4ff;
        padding: 12px;
        border-radius: 10px;
        overflow: auto;
        font-family: Consolas, "Courier New", monospace;
        font-size: 0.88rem;
    }
    .hgfv-error {
        margin-top: 8px;
        color: #9e1f1f;
        font-weight: 600;
    }
    .hgfv-thread-head {
        margin: 14px 0 12px;
        color: var(--muted);
        font-weight: 600;
    }
    .hgfv-message {
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 12px;
        box-shadow: 0 4px 14px rgba(10, 30, 90, 0.06);
    }
    .hgfv-message h2 {
        margin: 0 0 6px;
        font-size: 1.1rem;
    }
    .hgfv-meta {
        color: var(--muted);
        font-size: 0.9rem;
        margin-bottom: 12px;
    }
    .hgfv-meta-row {
        display: flex;
        align-items: center;
        gap: 9px;
        flex-wrap: wrap;
    }
    .hgfv-author-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid #9fb0d8;
        box-shadow: 0 1px 3px rgba(17, 31, 68, 0.16);
        background: #fff;
        flex: 0 0 auto;
    }
    .hgfv-author-fallback {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(145deg, #4f6ec0, #3957a9);
        border: 1px solid #2f4d9a;
        flex: 0 0 auto;
    }
    .hgfv-meta-text {
        line-height: 1.25;
    }
    .hgfv-body {
        line-height: 1.5;
        word-break: break-word;
    }
    .hgfv-body a { color: #1840a4; }
    .hgfv-body blockquote {
        border-left: 4px solid #9ab0e6;
        margin: 8px 0;
        padding: 8px 10px;
        background: #eef3ff;
        border-radius: 8px;
    }
    .hgfv-root .bb-quote-head {
        font-size: 0.86rem;
        font-weight: 700;
        color: #3a4a73;
        margin-bottom: 4px;
    }
    .hgfv-root .bb-img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
    }
    .hgfv-root .bb-youtube {
        position: relative;
        width: 100%;
        max-width: 560px;
        padding-top: 56.25%;
        margin: 8px 0;
        border-radius: 10px;
        overflow: hidden;
        background: #000;
    }
    .hgfv-root .bb-youtube iframe {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        border: 0;
    }
    .hgfv-root .hg-bb-spoiler {
        margin: 8px 0;
        border: 1px solid #b9c8ea;
        border-radius: 8px;
        background: #f5f8ff;
        overflow: hidden;
    }
    .hgfv-root .hg-bb-spoiler > summary {
        list-style: none;
        cursor: pointer;
        padding: 7px 10px;
        font-weight: 700;
        color: #2d3e67;
        background: #e8efff;
    }
    .hgfv-root .hg-bb-spoiler > summary::-webkit-details-marker {
        display: none;
    }
    .hgfv-root .hg-bb-spoiler[open] > summary {
        border-bottom: 1px solid #c7d4f1;
    }
    .hgfv-root .hg-bb-spoiler-body {
        padding: 8px 10px;
    }
    .hgfv-root .hg-inline-message .msg_main_box {
        margin: 0.8em 0 0.6em;
    }
    .hgfv-root .hg-inline-roll {
        color: #fff;
    }
    .hgfv-root .hg-inline-roll .roll-main-box {
        margin: 0.9em 0 1.1em;
    }
    .hgfv-root .inline-error {
        color: #9e1f1f;
        font-weight: 700;
        padding: 6px 0;
    }
    @media (max-width: 760px) {
        .hgfv-wrap { padding: 16px 10px 30px; }
        .hgfv-form-row input { width: 100%; max-width: 230px; }
        .hgfv-form-row select { max-width: 100%; }
    }
</style>
HTML;

if (!$hgfvEmbedded) {
    echo "<!doctype html>\n<html lang='es'>\n<head>\n";
    echo "<meta charset='UTF-8'>\n<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n<title>" . h($metaTitle) . "</title>\n";
    echo $hgfvAssets . "\n</head>\n<body>\n";
} else {
    echo $hgfvAssets . "\n";
}
?>
<section class="hgfv-root">
    <div class="hgfv-wrap">
        <section class="hgfv-panel hgfv-top">
            <h1><?= h($panelTitle) ?></h1>
            <?php if (empty($savedTopics)): ?>
                <form method="get" class="hgfv-form-row">
                    <label for="id_topic"><strong>id_topic:</strong></label>
                    <input type="number" min="1" id="id_topic" name="id_topic" value="<?= $topicId > 0 ? $topicId : '' ?>" placeholder="Ej: 39" required>
                    <button type="submit">Cargar hilo</button>
                </form>
            <?php endif; ?>

            <?php if (!empty($savedTopics)): ?>
                <div class="hgfv-select-block">
                    <label for="saved_topic_pick"><strong>Temas guardados:</strong></label>
                    <div class="hgfv-select-wrap">
                        <select id="saved_topic_pick">
                            <option value="">Selecciona un tema...</option>
                            <?php foreach ($savedTopicsGrouped as $scopeGroup): ?>
                                <optgroup label="<?= h((string)($scopeGroup['title'] ?? 'Sin agrupación')) ?>">
                                    <?php foreach (($scopeGroup['items'] ?? []) as $topicRow): ?>
                                        <?php
                                            $tId = (int)($topicRow['topic_id'] ?? 0);
                                            $tName = trim((string)($topicRow['topic_name'] ?? ''));
                                            $chapterBadge = trim((string)($topicRow['chapter_badge'] ?? ''));
                                            if ($tId <= 0) { continue; }
                                            $optionText = $tName !== '' ? $tName : ('Tema #' . $tId);
                                            if ($chapterBadge !== '') {
                                                $optionText .= ' [' . $chapterBadge . ']';
                                            }
                                        ?>
                                        <option value="<?= $tId ?>" <?= ($topicId === $tId) ? 'selected' : '' ?>>
                                            <?= h($optionText) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!--<div class="hgfv-select-help">Selecciona un tema para cargarlo sin escribir el ID.</div>-->
                </div>
            <?php elseif ($hasSavedTopicsTable): ?>
                <div class="hgfv-thread-head">No hay temas activos en `fact_tools_topic_viewer`.</div>
            <?php endif; ?>

            <?php if (is_array($selectedSavedTopic)): ?>
                <div class="hgfv-thread-head">
                    <?php
                        $chapterName = trim((string)($selectedSavedTopic['chapter_name'] ?? ''));
                        $seasonName = trim((string)($selectedSavedTopic['season_name'] ?? ''));
                        $chapterNumber = (int)($selectedSavedTopic['chapter_number'] ?? 0);
                        $scopeLabel = trim((string)($selectedSavedTopic['scope_label'] ?? ''));
                        $scopeName = trim((string)($selectedSavedTopic['scope_name'] ?? ''));
                        $chapterHref = trim((string)($selectedSavedTopic['chapter_href'] ?? ''));
                        $scopeHref = trim((string)($selectedSavedTopic['scope_href'] ?? ''));
                    ?>
                    <?php if ($chapterName !== ''): ?>
                        Episodio:
                        <?php if ($chapterHref !== ''): ?>
                            <a href="<?= h($chapterHref) ?>" target="_blank" rel="noopener noreferrer"><?= h($chapterName) ?></a>
                        <?php else: ?>
                            <strong><?= h($chapterName) ?></strong>
                        <?php endif; ?>
                        <?php if ($seasonName !== ''): ?> | Temporada: <?= h($seasonName) ?><?php endif; ?>
                        <?php if ($chapterNumber > 0): ?> | Nº episodio: <?= $chapterNumber ?><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($scopeName !== '' && $scopeName !== 'Sin agrupación'): ?>
                        <br>
                        Agrupación:
                        <?php if ($scopeHref !== ''): ?>
                            <a href="<?= h($scopeHref) ?>" target="_blank" rel="noopener noreferrer"><?= h($scopeLabel . ' · ' . $scopeName) ?></a>
                        <?php else: ?>
                            <strong><?= h($scopeLabel . ' · ' . $scopeName) ?></strong>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- <?php if ($query !== ''): ?>
                <div class="hgfv-query-box"><?= h($query) ?> | params: [id_topic=<?= $topicId ?>]</div>
            <?php endif; ?> -->

            <?php if ($error !== ''): ?>
                <div class="hgfv-error"><?= h($error) ?></div>
            <?php endif; ?>

            <?php if ($topicId > 0 && $error === ''): ?>
                <div class="hgfv-thread-head">
                    <?php /* Resultado para `id_topic=<?= $topicId ?>`: <?= count($messages) ?> mensaje(s), orden cronológico ascendente. */ ?>
                    Resultado: <?= count($messages) ?> mensaje(s), orden cronológico ascendente.
                </div>
            <?php endif; ?>
        </section>

        <?php foreach ($messages as $msg): ?>
            <?php
                $subject = trim((string)($msg['subject'] ?? ''));
                $poster = trim((string)($msg['poster_name'] ?? ''));
                $posterAvatar = hgfv_normalize_author_avatar_url((string)($msg['poster_avatar'] ?? ''));
                $posterInitial = hgfv_author_initial($poster);
                $posterTime = (int)($msg['poster_time'] ?? 0);
                $humanTime = $posterTime > 0 ? date('Y-m-d H:i:s', $posterTime) : 'Sin fecha';
                $parsedBody = parse_forum_body($link, (string)($msg['body'] ?? ''));
            ?>
            <article class="hgfv-message">
                <h2><?= h($subject !== '' ? $subject : '(Sin asunto)') ?></h2>
                <div class="hgfv-meta hgfv-meta-row">
                    <?php if ($posterAvatar !== ''): ?>
                        <img class="hgfv-author-avatar" src="<?= h($posterAvatar) ?>" alt="avatar de <?= h($poster !== '' ? $poster : 'autor') ?>">
                    <?php else: ?>
                        <span class="hgfv-author-fallback"><?= h($posterInitial) ?></span>
                    <?php endif; ?>
                    <span class="hgfv-meta-text">
                        <strong><?= h($poster !== '' ? $poster : 'Desconocido') ?></strong> |
                        <?php //unix: <?= $posterTime > 0 ? $posterTime : 'n/d' | ?>
                        <?= h($humanTime) ?>
                    </span>
                </div>
                <div class="hgfv-body"><?= $parsedBody ?></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<script>
var hgfvMetaTitle = <?= json_encode((string)$metaTitle, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var hgfvMetaDescription = <?= json_encode((string)$metaDescription, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
(function(){
    if (typeof document === 'undefined') return;
    if (hgfvMetaTitle && String(hgfvMetaTitle).trim() !== '') {
        document.title = String(hgfvMetaTitle);
    }
    if (hgfvMetaDescription && String(hgfvMetaDescription).trim() !== '') {
        var metaDesc = document.querySelector('meta[name="description"]');
        if (!metaDesc) {
            metaDesc = document.createElement('meta');
            metaDesc.setAttribute('name', 'description');
            if (document.head) {
                document.head.appendChild(metaDesc);
            }
        }
        if (metaDesc) {
            metaDesc.setAttribute('content', String(hgfvMetaDescription));
        }
    }
})();

function getLuminance(r, g, b) {
    var a = [r, g, b].map(function(v) {
        v /= 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2];
}

function detectAndApplyTextColor() {
    var boxes = document.querySelectorAll('.hgfv-root .hg-inline-message .msg_body');
    for (var i = 0; i < boxes.length; i++) {
        var style = getComputedStyle(boxes[i]);
        var bgColor = style.backgroundColor || '';
        var rgb = bgColor.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (!rgb) {
            continue;
        }
        var lum = getLuminance(+rgb[1], +rgb[2], +rgb[3]);
        boxes[i].classList.add(lum < 0.5 ? 'dark-text' : 'light-text');
    }
}

window.addEventListener('load', detectAndApplyTextColor);

(function(){
    var pick = document.getElementById('saved_topic_pick');
    if (!pick) return;
    pick.addEventListener('change', function(){
        var val = String(pick.value || '').trim();
        if (val === '') return;
        var url = new URL(window.location.href);
        url.searchParams.set('id_topic', val);
        window.location.href = url.pathname + '?' + url.searchParams.toString();
    });
})();
</script>
<?php if (!$hgfvEmbedded): ?>
</body>
</html>
<?php endif; ?>
