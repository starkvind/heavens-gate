<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/search_catalog.php');
require_once(__DIR__ . '/../../domains/chronicles/scope.php');
require_once(__DIR__ . '/../../domains/search/queries.php');

$metaTitle = "Busqueda | Heaven's Gate";
$metaDescription = 'Buscador móvil del archivo.';
$pageSect = 'Buscar';

if (!function_exists('hg_mobile_search_h')) {
    function hg_mobile_search_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_search_input')) {
    function hg_mobile_search_input(array $request, string $key): string
    {
        return trim(strip_tags(hg_request_query_param($request, $key)));
    }
}

if (!function_exists('hg_mobile_search_html_label')) {
    function hg_mobile_search_html_label(array $config): string
    {
        return (string)($config['label_html'] ?? '');
    }
}

if (!function_exists('hg_mobile_search_text_label')) {
    function hg_mobile_search_text_label(array $config): string
    {
        return html_entity_decode((string)($config['label_html'] ?? ($config['label_text'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_search_excerpt')) {
    function hg_mobile_search_excerpt(string $text, int $max = 165): string
    {
        $text = trim(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        }
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}

if (!function_exists('hg_mobile_search_normalize_whitespace')) {
    function hg_mobile_search_normalize_whitespace(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string)$text);
    }
}

if (!function_exists('hg_mobile_search_slugify_text')) {
    function hg_mobile_search_slugify_text(string $text): string
    {
        $text = hg_mobile_search_normalize_whitespace($text);
        if ($text === '') return '';
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_D);
            if (is_string($normalized) && $normalized !== '') $text = preg_replace('/\p{Mn}+/u', '', $normalized);
        }
        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }
}

if (!function_exists('hg_mobile_search_contains')) {
    function hg_mobile_search_contains(string $haystack, string $needle): bool
    {
        if ($needle === '') return true;
        return function_exists('str_contains') ? str_contains($haystack, $needle) : strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('hg_mobile_search_starts_with')) {
    function hg_mobile_search_starts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') return true;
        return function_exists('str_starts_with') ? str_starts_with($haystack, $needle) : substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (!function_exists('hg_mobile_search_highlight')) {
    function hg_mobile_search_highlight(string $text, array $terms): string
    {
        $text = hg_mobile_search_normalize_whitespace($text);
        if ($text === '') return '';
        $needles = [];
        foreach ($terms as $term) {
            $term = trim((string)$term);
            if ($term !== '') $needles[] = preg_quote($term, '/');
        }
        $needles = array_values(array_unique($needles));
        if (empty($needles)) return hg_mobile_search_h($text);
        $pattern = '/(' . implode('|', $needles) . ')/iu';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) return hg_mobile_search_h($text);
        $html = '';
        foreach ($parts as $part) {
            if ($part === '') continue;
            $html .= preg_match($pattern, $part)
                ? '<mark class="hg-mobile-search-hit">' . hg_mobile_search_h($part) . '</mark>'
                : hg_mobile_search_h($part);
        }
        return $html;
    }
}

if (!function_exists('hg_mobile_search_item_url')) {
    function hg_mobile_search_item_url(mysqli $link, int $itemId): string
    {
        $row = hg_search_query_item_slug_row($link, $itemId) ?? [];
        $itemSlug = (string)($row['item_pretty'] ?? '');
        $typeSlug = (string)($row['type_pretty'] ?? '');
        if ($typeSlug === '' && isset($row['type_id'])) $typeSlug = (string)$row['type_id'];
        return '/inventory/' . rawurlencode($typeSlug !== '' ? $typeSlug : 'tipo') . '/' . rawurlencode($itemSlug !== '' ? $itemSlug : (string)$itemId);
    }
}

if (!function_exists('hg_mobile_search_result_url')) {
    function hg_mobile_search_result_url(mysqli $link, string $routeKey, int $id): string
    {
        switch ($routeKey) {
            case 'muestrabio': return pretty_url($link, 'fact_characters', '/characters', $id);
            case 'chronicles': return pretty_url($link, 'dim_chronicles', '/chronicles', $id);
            case 'temp': return pretty_url($link, 'dim_seasons', '/seasons', $id);
            case 'seechapter': return pretty_url($link, 'dim_chapters', '/chapters', $id);
            case 'verdoc': return pretty_url($link, 'fact_docs', '/documents', $id);
            case 'verobj': return hg_mobile_search_item_url($link, $id);
            case 'muestradon': return pretty_url($link, 'fact_gifts', '/powers/gift', $id);
            case 'verrasgo': return pretty_url($link, 'dim_traits', '/rules/traits', $id);
            case 'sistemas': return pretty_url($link, 'dim_systems', '/systems', $id);
            case 'versistdetalle_breed': return pretty_url($link, 'dim_breeds', '/systems/breeds', $id);
            case 'versistdetalle_auspice': return pretty_url($link, 'dim_auspices', '/systems/auspices', $id);
            case 'versistdetalle_tribe': return pretty_url($link, 'dim_tribes', '/systems/tribes', $id);
            case 'versistdetalle_misc': return pretty_url($link, 'fact_misc_systems', '/systems/misc', $id);
            case 'vermyd': return pretty_url($link, 'dim_merits_flaws', '/rules/merits-flaws', $id);
            default: return '/search';
        }
    }
}

if (!function_exists('hg_mobile_search_base_where')) {
    function hg_mobile_search_base_where(mysqli $link, string $sectionKey): string
    {
        $csv = hg_chronicle_scope_excluded_csv();
        if ($csv === '') return '';
        if ($sectionKey === 'biografías') return 'src.chronicle_id NOT IN (' . $csv . ')';
        if ($sectionKey === 'crónicas') return 'src.id NOT IN (' . $csv . ')';
        if ($sectionKey === 'temporadas' && hg_search_catalog_column_exists($link, 'dim_seasons', 'chronicle_id')) {
            return 'src.chronicle_id NOT IN (' . $csv . ')';
        }
        if ($sectionKey === 'episodios' && hg_search_catalog_column_exists($link, 'dim_seasons', 'chronicle_id')) {
            return '(s.chronicle_id IS NULL OR s.chronicle_id NOT IN (' . $csv . '))';
        }
        return '';
    }
}

if (!function_exists('hg_mobile_search_fetch_section_results')) {
    function hg_mobile_search_fetch_section_results(mysqli $link, string $sectionKey, array $config, array $terms, int $limit): array
    {
        $rawRows = hg_search_query_fetch_section($link, $config, $terms, $limit, hg_mobile_search_base_where($link, $sectionKey));
        if ($rawRows === null) {
            hg_public_log_error('mobile_search', 'query failed for ' . $sectionKey);
            return [];
        }
        $rows = [];
        foreach ($rawRows as $row) {
            $rows[] = [
                'section_key' => $sectionKey,
                'section_label_html' => hg_mobile_search_html_label($config),
                'section_label_text' => hg_mobile_search_text_label($config),
                'route' => $config['route'],
                'section_weight' => (int)($config['section_weight'] ?? 0),
                'id' => (int)($row['result_id'] ?? 0),
                'title' => hg_mobile_search_normalize_whitespace((string)($row['result_title'] ?? '')),
                'excerpt' => hg_mobile_search_excerpt((string)($row['result_excerpt'] ?? '')),
                'secondary' => hg_mobile_search_normalize_whitespace((string)($row['result_secondary'] ?? '')),
            ];
        }
        return $rows;
    }
}

if (!function_exists('hg_mobile_search_score_result')) {
    function hg_mobile_search_score_result(array $row, array $terms, string $fullQuery = ''): int
    {
        $title = hg_mobile_search_slugify_text((string)($row['title'] ?? ''));
        $excerpt = hg_mobile_search_slugify_text((string)($row['excerpt'] ?? ''));
        $secondary = hg_mobile_search_slugify_text((string)($row['secondary'] ?? ''));
        $fullNeedle = hg_mobile_search_slugify_text($fullQuery);
        $score = 0;
        if ($fullNeedle !== '') {
            if ($title === $fullNeedle) $score += 240;
            elseif (hg_mobile_search_starts_with($title, $fullNeedle)) $score += 170;
            elseif (hg_mobile_search_contains($title, $fullNeedle)) $score += 110;
            if ($secondary !== '' && hg_mobile_search_contains($secondary, $fullNeedle)) $score += 40;
            if ($excerpt !== '' && hg_mobile_search_contains($excerpt, $fullNeedle)) $score += 22;
        }
        foreach ($terms as $term) {
            $needle = hg_mobile_search_slugify_text($term);
            if ($needle === '') continue;
            if ($title === $needle) $score += 140;
            elseif (hg_mobile_search_starts_with($title, $needle)) $score += 90;
            elseif (hg_mobile_search_contains($title, $needle)) $score += 55;
            if ($secondary !== '' && hg_mobile_search_contains($secondary, $needle)) $score += 25;
            if ($excerpt !== '' && hg_mobile_search_contains($excerpt, $needle)) $score += 12;
        }
        if ($secondary !== '') $score += 3;
        return $score + (int)($row['section_weight'] ?? 0);
    }
}

if (!function_exists('hg_mobile_search_sort_rows')) {
    function hg_mobile_search_sort_rows(array $rows, array $terms, string $fullQuery = ''): array
    {
        foreach ($rows as $idx => $row) $rows[$idx]['score'] = hg_mobile_search_score_result($row, $terms, $fullQuery);
        usort($rows, static function (array $a, array $b): int {
            $scoreCompare = (($b['score'] ?? 0) <=> ($a['score'] ?? 0));
            if ($scoreCompare !== 0) return $scoreCompare;
            $sectionCompare = strcmp((string)($a['section_label_text'] ?? ''), (string)($b['section_label_text'] ?? ''));
            if ($sectionCompare !== 0) return $sectionCompare;
            return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });
        return $rows;
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    echo '<section class="hg-mobile-section"><h1>Buscar</h1><p>No se pudo conectar con la base de datos.</p></section>';
    return;
}

$catalog = hg_search_catalog($link);
$routeKey = hg_request_route($hgRequest);
$isResults = ($routeKey === 'busk');
$query = hg_mobile_search_input($hgRequest, 'q');
$sectionKey = hg_mobile_search_input($hgRequest, 'section');
if ($query === '') $query = hg_mobile_search_input($hgRequest, 'bsq');
if ($sectionKey === '') $sectionKey = hg_mobile_search_input($hgRequest, 'skz');
if ($sectionKey === '') $sectionKey = 'all';
if (!isset($catalog[$sectionKey])) $sectionKey = 'all';
$sectionConfig = $catalog[$sectionKey];
$queryLength = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);
$terms = [];
$rows = [];
$sectionBreakdown = [];

if ($isResults && $query !== '' && $queryLength > 2) {
    $terms = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($sectionKey === 'all') {
        foreach ($catalog as $key => $config) {
            if (!empty($config['virtual'])) continue;
            $sectionRows = hg_mobile_search_fetch_section_results($link, $key, $config, $terms, (int)($config['all_limit'] ?? 6));
            if (!empty($sectionRows)) {
                $sectionBreakdown[$key] = [
                    'label_html' => hg_mobile_search_html_label($config),
                    'label_text' => hg_mobile_search_text_label($config),
                    'count' => count($sectionRows),
                ];
                $rows = array_merge($rows, $sectionRows);
            }
        }
        uasort($sectionBreakdown, static function (array $a, array $b): int {
            $countCompare = (($b['count'] ?? 0) <=> ($a['count'] ?? 0));
            return $countCompare !== 0 ? $countCompare : strcmp((string)($a['label_text'] ?? ''), (string)($b['label_text'] ?? ''));
        });
        $rows = array_slice(hg_mobile_search_sort_rows($rows, $terms, $query), 0, 50);
    } else {
        $rows = hg_mobile_search_fetch_section_results($link, $sectionKey, $sectionConfig, $terms, 100);
        $rows = array_slice(hg_mobile_search_sort_rows($rows, $terms, $query), 0, 50);
    }
}

$metaTitle = $isResults ? "Resultados de busqueda | Heaven's Gate" : "Busqueda | Heaven's Gate";

include __DIR__ . '/../views/search.php';
