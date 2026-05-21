<?php
setMetaFromPage('Resultados de busqueda | Heaven\'s Gate', 'Resultados de la busqueda en el repositorio.', null, 'website');
include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/search_catalog.php');

if (!$link) {
    hg_public_log_error('main_search_result', 'missing DB connection');
    hg_public_render_error('Busqueda no disponible', 'No se pudo ejecutar la busqueda en este momento.');
    return;
}

function hg_search_input(string $key): string
{
    $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
    if (!is_string($value)) {
        return '';
    }

    return trim(strip_tags($value));
}

function hg_search_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hg_search_html_label(array $config): string
{
    return (string)($config['label_html'] ?? '');
}

function hg_search_text_label(array $config): string
{
    return html_entity_decode((string)($config['label_html'] ?? ($config['label_text'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function hg_search_excerpt(string $text, int $max = 180): string
{
    $text = trim(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
    }

    return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
}

function hg_search_normalize_whitespace(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string)$text);
}

function hg_search_slugify_text(string $text): string
{
    $text = hg_search_normalize_whitespace($text);
    if ($text === '') {
        return '';
    }

    if (class_exists('Normalizer')) {
        $normalized = \Normalizer::normalize($text, \Normalizer::FORM_D);
        if (is_string($normalized) && $normalized !== '') {
            $text = preg_replace('/\p{Mn}+/u', '', $normalized);
        }
    }

    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }

    return $text;
}

function hg_search_starts_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    if (function_exists('str_starts_with')) {
        return str_starts_with($haystack, $needle);
    }

    return substr($haystack, 0, strlen($needle)) === $needle;
}

function hg_search_contains(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }

    if (function_exists('str_contains')) {
        return str_contains($haystack, $needle);
    }

    return strpos($haystack, $needle) !== false;
}

function hg_search_highlight(string $text, array $terms): string
{
    $text = hg_search_normalize_whitespace($text);
    if ($text === '') {
        return '';
    }

    $needles = [];
    foreach ($terms as $term) {
        $term = trim((string)$term);
        if ($term === '') {
            continue;
        }
        $needles[] = preg_quote($term, '/');
    }
    $needles = array_values(array_unique($needles));
    if (empty($needles)) {
        return hg_search_h($text);
    }

    $pattern = '/(' . implode('|', $needles) . ')/iu';
    $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) {
        return hg_search_h($text);
    }

    $html = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (preg_match($pattern, $part)) {
            $html .= '<mark class="search-hit">' . hg_search_h($part) . '</mark>';
        } else {
            $html .= hg_search_h($part);
        }
    }

    return $html;
}

function hg_search_item_url(mysqli $link, int $itemId): string
{
    $typeSlug = '';
    $itemSlug = '';
    if ($stmt = $link->prepare("
        SELECT i.pretty_id AS item_pretty, t.pretty_id AS type_pretty, t.id AS type_id
        FROM fact_items i
        LEFT JOIN dim_item_types t ON t.id = i.item_type_id
        WHERE i.id = ?
        LIMIT 1
    ")) {
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $rs = $stmt->get_result();
        if ($rs && ($row = $rs->fetch_assoc())) {
            $itemSlug = (string)($row['item_pretty'] ?? '');
            $typeSlug = (string)($row['type_pretty'] ?? '');
            if ($typeSlug === '' && isset($row['type_id'])) {
                $typeSlug = (string)$row['type_id'];
            }
        }
        $stmt->close();
    }

    if ($itemSlug === '') {
        $itemSlug = (string)$itemId;
    }
    if ($typeSlug === '') {
        $typeSlug = 'tipo';
    }

    return '/inventory/' . rawurlencode($typeSlug) . '/' . rawurlencode($itemSlug);
}

function hg_search_result_url(mysqli $link, string $routeKey, int $id): string
{
    switch ($routeKey) {
        case 'muestrabio':
            return pretty_url($link, 'fact_characters', '/characters', $id);
        case 'chronicles':
            return pretty_url($link, 'dim_chronicles', '/chronicles', $id);
        case 'temp':
            return pretty_url($link, 'dim_seasons', '/seasons', $id);
        case 'seechapter':
            return pretty_url($link, 'dim_chapters', '/chapters', $id);
        case 'verdoc':
            return pretty_url($link, 'fact_docs', '/documents', $id);
        case 'seeitem':
            return hg_search_item_url($link, $id);
        case 'muestradon':
            return pretty_url($link, 'fact_gifts', '/powers/gift', $id);
        case 'verrasgo':
            return pretty_url($link, 'dim_traits', '/rules/traits', $id);
        case 'sistemas':
            return pretty_url($link, 'dim_systems', '/systems', $id);
        case 'versistdetalle_breed':
            return pretty_url($link, 'dim_breeds', '/systems/breeds', $id);
        case 'versistdetalle_auspice':
            return pretty_url($link, 'dim_auspices', '/systems/auspices', $id);
        case 'versistdetalle_tribe':
            return pretty_url($link, 'dim_tribes', '/systems/tribes', $id);
        case 'versistdetalle_misc':
            return pretty_url($link, 'fact_misc_systems', '/systems/misc', $id);
        case 'vermyd':
            return pretty_url($link, 'dim_merits_flaws', '/rules/merits-flaws', $id);
        default:
            return '?p=' . rawurlencode($routeKey) . '&b=' . $id;
    }
}

function hg_search_build_where(array $fields, array $terms, array &$params, string &$types): string
{
    $whereParts = [];
    foreach ($terms as $term) {
        $like = '%' . $term . '%';
        $sub = [];
        foreach ($fields as $field) {
            $sub[] = $field . ' LIKE ?';
            $params[] = $like;
            $types .= 's';
        }
        if (!empty($sub)) {
            $whereParts[] = '(' . implode(' OR ', $sub) . ')';
        }
    }

    return implode(' AND ', $whereParts);
}

function hg_search_fetch_section_results(mysqli $link, string $sectionKey, array $config, array $terms, int $limit): array
{
    $params = [];
    $types = '';
    $whereSql = hg_search_build_where($config['search_fields'], $terms, $params, $types);
    if ($whereSql === '') {
        return [];
    }

    $sql = "
        SELECT
            {$config['id_expr']} AS result_id,
            {$config['title_expr']} AS result_title,
            {$config['excerpt_expr']} AS result_excerpt,
            {$config['secondary_expr']} AS result_secondary
        FROM {$config['from_sql']}
        WHERE {$whereSql}
    ";
    if (!empty($config['group_sql'])) {
        $sql .= " GROUP BY {$config['group_sql']}";
    }
    $sql .= " ORDER BY {$config['order_sql']} LIMIT " . (int)$limit;

    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        hg_public_log_error('main_search_result', 'prepare failed for section ' . $sectionKey . ': ' . mysqli_error($link));
        return [];
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        hg_public_log_error('main_search_result', 'query failed for section ' . $sectionKey . ': ' . mysqli_error($link));
        mysqli_stmt_close($stmt);
        return [];
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = [
            'section_key' => $sectionKey,
            'section_label_html' => hg_search_html_label($config),
            'section_label_text' => hg_search_text_label($config),
            'route' => $config['route'],
            'section_weight' => (int)($config['section_weight'] ?? 0),
            'id' => (int)($row['result_id'] ?? 0),
            'title' => hg_search_normalize_whitespace((string)($row['result_title'] ?? '')),
            'excerpt' => hg_search_excerpt((string)($row['result_excerpt'] ?? '')),
            'secondary' => hg_search_normalize_whitespace((string)($row['result_secondary'] ?? '')),
        ];
    }

    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
    return $rows;
}

function hg_search_score_result(array $row, array $terms, string $fullQuery = ''): int
{
    $title = hg_search_slugify_text((string)($row['title'] ?? ''));
    $excerpt = hg_search_slugify_text((string)($row['excerpt'] ?? ''));
    $secondary = hg_search_slugify_text((string)($row['secondary'] ?? ''));
    $score = 0;
    $fullNeedle = hg_search_slugify_text($fullQuery);

    if ($fullNeedle !== '') {
        if ($title === $fullNeedle) {
            $score += 240;
        } elseif (hg_search_starts_with($title, $fullNeedle)) {
            $score += 170;
        } elseif (hg_search_contains($title, $fullNeedle)) {
            $score += 110;
        }

        if ($secondary !== '' && hg_search_contains($secondary, $fullNeedle)) {
            $score += 40;
        }

        if ($excerpt !== '' && hg_search_contains($excerpt, $fullNeedle)) {
            $score += 22;
        }
    }

    foreach ($terms as $term) {
        $needle = hg_search_slugify_text($term);
        if ($needle === '') {
            continue;
        }
        if ($title === $needle) {
            $score += 140;
        } elseif (hg_search_starts_with($title, $needle)) {
            $score += 90;
        } elseif (hg_search_contains($title, $needle)) {
            $score += 55;
        }

        if ($secondary !== '' && hg_search_contains($secondary, $needle)) {
            $score += 25;
        }

        if ($excerpt !== '' && hg_search_contains($excerpt, $needle)) {
            $score += 12;
        }
    }

    if ($secondary !== '') {
        $score += 3;
    }

    $score += (int)($row['section_weight'] ?? 0);

    return $score;
}

function hg_search_sort_rows(array $rows, array $terms, string $fullQuery = ''): array
{
    foreach ($rows as $idx => $row) {
        $rows[$idx]['score'] = hg_search_score_result($row, $terms, $fullQuery);
    }

    usort($rows, static function (array $a, array $b): int {
        $scoreCompare = (($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        if ($scoreCompare !== 0) {
            return $scoreCompare;
        }
        $sectionCompare = strcmp((string)($a['section_label_text'] ?? ''), (string)($b['section_label_text'] ?? ''));
        if ($sectionCompare !== 0) {
            return $sectionCompare;
        }
        return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    });

    return $rows;
}

$query = hg_search_input('q');
$sectionKey = hg_search_input('section');
if ($query === '') {
    $query = hg_search_input('bsq');
}
if ($sectionKey === '') {
    $sectionKey = hg_search_input('skz');
}
if ($sectionKey === '') {
    $sectionKey = 'all';
}

$queryLength = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);
$catalog = hg_search_catalog($link);
if (!isset($catalog[$sectionKey])) {
    hg_public_render_not_found('Busqueda no disponible', 'La categoria de busqueda solicitada no es valida.');
    return;
}
$sectionConfig = $catalog[$sectionKey];

$rows = [];
$sectionBreakdown = [];
if ($query !== '' && $queryLength > 2) {
    $terms = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
    if ($sectionKey === 'all') {
        foreach ($catalog as $key => $config) {
            if (!empty($config['virtual'])) {
                continue;
            }
            $sectionRows = hg_search_fetch_section_results($link, $key, $config, $terms, (int)($config['all_limit'] ?? 6));
            if (!empty($sectionRows)) {
                $sectionBreakdown[$key] = [
                    'label_html' => hg_search_html_label($config),
                    'label_text' => hg_search_text_label($config),
                    'count' => count($sectionRows),
                ];
                $rows = array_merge($rows, $sectionRows);
            }
        }
        uasort($sectionBreakdown, static function (array $a, array $b): int {
            $countCompare = (($b['count'] ?? 0) <=> ($a['count'] ?? 0));
            if ($countCompare !== 0) {
                return $countCompare;
            }
            return strcmp((string)($a['label_text'] ?? ''), (string)($b['label_text'] ?? ''));
        });
        $rows = hg_search_sort_rows($rows, $terms, $query);
        $rows = array_slice($rows, 0, 50);
    } else {
        $rows = hg_search_fetch_section_results($link, $sectionKey, $sectionConfig, $terms, 100);
        $rows = hg_search_sort_rows($rows, $terms, $query);
        $rows = array_slice($rows, 0, 50);
    }
}

include('app/partials/main_nav_bar.php');
echo '<link rel="stylesheet" href="/assets/css/hg-main.css">';
?>
<style>
.search-page {
    max-width: 940px;
    margin: 0 auto;
    text-align: left;
}

.search-header,
.search-results-panel {
    border: 1px solid #000088;
    background: #05014e;
    padding: 22px 24px;
}

.search-header {
    margin-bottom: 18px;
}

.search-header h2,
.search-header p {
    text-align: left;
}

.search-header p {
    margin: 0;
    color: #d5defe;
    max-width: 720px;
    line-height: 1.5;
}

.search-kicker {
    margin: 0 0 8px;
    color: #99ffff;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    opacity: 0.82;
}

.search-header h2 {
    margin-bottom: 10px;
}

.search-header-copy {
    margin-bottom: 18px;
}

.search-refine {
    margin-top: 0;
    display: grid;
    grid-template-columns: minmax(0, 1.8fr) minmax(220px, 1fr) auto;
    gap: 12px;
    align-items: end;
}

.search-refine label {
    display: block;
    margin-bottom: 5px;
    color: #99ffff;
    font-weight: bold;
}

.search-refine input,
.search-refine select {
    width: 100%;
    box-sizing: border-box;
    padding: 7px 8px;
    font-size: 11px;
}

.search-refine .boton1 {
    width: auto;
    min-width: 96px;
    padding: 7px 14px;
}

.search-results-meta {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 18px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(0, 80, 160, 0.45);
}

.search-summary {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.search-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 8px;
    border: 1px solid rgba(0, 120, 220, 0.6);
    background: rgba(0, 30, 80, 0.28);
    color: #cfe0ff;
    font-size: 10px;
}

.search-chip strong {
    color: #99ffff;
}

.search-results-count {
    color: #99ffff;
    font-size: 11px;
}

.search-results-tools {
    margin: 0 0 18px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.search-results-tool {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 9px;
    border: 1px solid rgba(0, 120, 220, 0.55);
    background: rgba(0, 30, 80, 0.2);
    color: #cfe0ff;
    text-decoration: none;
    font-size: 10px;
}

.search-results-tool:hover {
    background: rgba(0, 40, 95, 0.38);
    border-color: rgba(0, 150, 255, 0.75);
}

.search-section-bar {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin: 0 0 18px;
}

.search-section-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border: 1px solid rgba(0, 120, 220, 0.55);
    background: rgba(0, 30, 80, 0.22);
    color: #cfe0ff;
    text-decoration: none;
    font-size: 10px;
}

.search-section-link strong {
    color: #99ffff;
}

.search-section-link:hover {
    background: rgba(0, 40, 95, 0.4);
    border-color: rgba(0, 150, 255, 0.75);
}

.search-results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 12px;
}

.search-result-card {
    display: block;
    border: 1px solid #000088;
    background: rgba(0, 0, 40, 0.35);
    padding: 12px;
    text-decoration: none;
}

.search-result-card:hover {
    border-color: #003399;
    background: #000066;
}

.search-result-type {
    display: inline-block;
    margin-bottom: 8px;
    padding: 2px 7px;
    border: 1px solid #003399;
    color: #99ffff;
    font-size: 10px;
    text-transform: uppercase;
}

.search-result-card h3 {
    margin-bottom: 6px;
}

.search-result-card p {
    margin: 0;
    color: #d5defe;
    line-height: 1.45;
}

.search-hit {
    background: rgba(0, 255, 255, 0.14);
    color: #ffffff;
    padding: 0 2px;
}

.search-result-foot {
    margin-top: 10px;
    color: #9fb7f0;
    font-size: 10px;
}

.search-empty {
    padding: 18px;
    border: 1px solid #000088;
    background: rgba(0, 0, 40, 0.28);
}

.search-empty h3 {
    margin-bottom: 6px;
}

.search-empty p {
    margin: 0;
    color: #d5defe;
}

.search-results-actions {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}

.search-results-actions .search-results-back {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 110px;
    padding: 7px 14px;
    border: 1px solid rgba(0, 120, 220, 0.55);
    background: rgba(0, 30, 80, 0.22);
    color: #cfe0ff;
    text-decoration: none;
    font-size: 11px;
}

.search-results-actions .search-results-back:hover {
    background: rgba(0, 40, 95, 0.38);
    border-color: rgba(0, 150, 255, 0.75);
}

.search-recent {
    margin: 0 0 18px;
    display: none;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.search-recent.is-ready {
    display: flex;
}

.search-recent-label {
    color: #99ffff;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.search-recent-items {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.search-recent-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border: 1px solid rgba(0, 120, 220, 0.55);
    background: rgba(0, 30, 80, 0.22);
    color: #cfe0ff;
    text-decoration: none;
    font-size: 10px;
}

.search-recent-chip:hover {
    background: rgba(0, 40, 95, 0.38);
    border-color: rgba(0, 150, 255, 0.75);
}

@media (max-width: 760px) {
    .search-header,
    .search-results-panel {
        padding: 18px;
    }

    .search-header-copy {
        margin-bottom: 14px;
    }

    .search-refine {
        grid-template-columns: 1fr;
    }

    .search-refine .boton1,
    .search-results-actions .search-results-back {
        width: 100%;
    }

    .search-results-actions {
        justify-content: stretch;
    }
}
</style>
<div class="search-page">
    <section class="search-header">
        <p class="search-kicker">Buscador</p>
        <div class="search-header-copy">
            <h2>Resultado de la b&uacute;squeda</h2>
            <p>Consulta resultados por secci&oacute;n o mezcla todo el archivo desde una sola vista.</p>
        </div>
        <form action="/search/results" method="get" class="search-refine">
            <div>
                <label for="search-q">Texto a buscar</label>
                <input id="search-q" type="text" name="q" maxlength="80" minlength="3" value="<?= hg_search_h($query) ?>" />
            </div>
            <div>
                <label for="search-section">Secci&oacute;n</label>
                <select id="search-section" name="section">
                    <?php foreach ($catalog as $value => $config): ?>
                        <option value="<?= hg_search_h($value) ?>"<?= $value === $sectionKey ? ' selected' : '' ?>><?= hg_search_html_label($config) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <input type="submit" value="Buscar" class="boton1" />
            </div>
        </form>
    </section>
    <section class="search-results-panel">
<?php if (!empty($query) && $queryLength > 2): ?>
        <div class="search-results-meta">
            <div class="search-summary">
                <span class="search-chip"><strong>Texto</strong> <?= hg_search_h($query) ?></span>
                <span class="search-chip"><strong>Secci&oacute;n</strong> <?= hg_search_html_label($sectionConfig) ?></span>
            </div>
            <span class="search-results-count"><?= count($rows) ?> resultados</span>
        </div>
        <div class="search-results-tools">
            <?php if ($sectionKey !== 'all'): ?>
                <a class="search-results-tool" href="/search/results?q=<?= rawurlencode($query) ?>&section=all">Ver mezcla global</a>
            <?php endif; ?>
            <?php if (!empty($rows)): ?>
                <span class="search-results-tool">Ordenado por relevancia</span>
            <?php endif; ?>
        </div>
        <div class="search-recent" id="search-recent">
            <span class="search-recent-label">Recientes</span>
            <div class="search-recent-items" id="search-recent-items"></div>
        </div>
        <?php if ($sectionKey === 'all' && !empty($sectionBreakdown)): ?>
            <div class="search-section-bar">
                <?php foreach ($sectionBreakdown as $breakKey => $breakMeta): ?>
                    <a class="search-section-link" href="/search/results?q=<?= rawurlencode($query) ?>&section=<?= rawurlencode($breakKey) ?>">
                        <strong><?= $breakMeta['label_html'] ?></strong>
                        <span><?= (int)$breakMeta['count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($rows)): ?>
            <div class="search-results-grid">
                <?php foreach ($rows as $row): ?>
                    <?php $href = hg_search_result_url($link, (string)$row['route'], (int)$row['id']); ?>
                    <a class="search-result-card" title="<?= hg_search_h($row['title']) ?>" href="<?= hg_search_h($href) ?>">
                        <span class="search-result-type"><?= $row['section_label_html'] ?></span>
                        <h3><?= hg_search_highlight($row['title'] !== '' ? $row['title'] : ('Elemento #' . $row['id']), $terms) ?></h3>
                        <p><?= hg_search_highlight($row['excerpt'] !== '' ? $row['excerpt'] : 'Sin descripcion breve disponible.', $terms) ?></p>
                        <div class="search-result-foot">
                            <?php if (($row['secondary'] ?? '') !== ''): ?>
                                <?= hg_search_highlight($row['secondary'], $terms) ?> &nbsp;|&nbsp;
                            <?php endif; ?>
                            ID <?= (int)$row['id'] ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="search-empty">
                <h3>Sin coincidencias</h3>
                <p>No se ha encontrado nada que concuerde con '<?= hg_search_h($query) ?>' en <?= hg_search_html_label($sectionConfig) ?>. Prueba con otro t&eacute;rmino o cambia de secci&oacute;n.</p>
            </div>
        <?php endif; ?>
<?php elseif (empty($query)): ?>
        <div class="search-empty">
            <h3>Sin criterio</h3>
            <p>No has introducido ning&uacute;n criterio de b&uacute;squeda todav&iacute;a.</p>
        </div>
<?php else: ?>
        <div class="search-empty">
            <h3>Criterio demasiado corto</h3>
            <p>La b&uacute;squeda debe realizarse con al menos 3 letras.</p>
        </div>
<?php endif; ?>
        <div class="search-results-actions">
            <a class="search-results-back" href="/search">Volver</a>
        </div>
    </section>
</div>
<script>
(function () {
    const STORAGE_KEY = 'hg-search-recent';
    const current = {
        q: <?= json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        section: <?= json_encode($sectionKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        label: <?= json_encode($sectionConfig['label_text'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    };

    let recent = [];
    try {
        recent = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    } catch (err) {
        recent = [];
    }
    if (!Array.isArray(recent)) recent = [];

    if (current.q && current.section) {
        recent = recent.filter(function (entry) {
            return !(entry && entry.q === current.q && entry.section === current.section);
        });
        recent.unshift(current);
        recent = recent.slice(0, 6);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(recent));
    }

    const root = document.getElementById('search-recent');
    const items = document.getElementById('search-recent-items');
    if (!root || !items || recent.length <= 1) return;

    recent.slice(1, 6).forEach(function (entry) {
        if (!entry || !entry.q || !entry.section) return;
        const a = document.createElement('a');
        a.className = 'search-recent-chip';
        a.href = '/search/results?q=' + encodeURIComponent(entry.q) + '&section=' + encodeURIComponent(entry.section);
        a.textContent = entry.q + ' · ' + entry.label;
        items.appendChild(a);
    });

    if (items.children.length > 0) {
        root.classList.add('is-ready');
    }
})();
</script>
