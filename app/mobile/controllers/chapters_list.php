<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../domains/chapters/queries.php');

$metaTitle = "Capítulos | Heaven's Gate";
$metaDescription = "Listado móvil de capítulos.";
$pageSect = 'Capítulos';

if (!function_exists('hg_mobile_chl_h')) {
    function hg_mobile_chl_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_mobile_chl_url')) {
    function hg_mobile_chl_url(mysqli $link, string $table, string $base, int $id): string {
        return $id > 0 && function_exists('pretty_url') ? pretty_url($link, $table, $base, $id) : rtrim($base, '/') . '/' . $id;
    }
}
if (!function_exists('hg_mobile_chl_date')) {
    function hg_mobile_chl_date(?string $raw): string {
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === '0000-00-00') return '';
        $ts = strtotime($raw);
        return $ts !== false ? date('d-m-Y', $ts) : $raw;
    }
}
if (!function_exists('hg_mobile_chl_excerpt')) {
    function hg_mobile_chl_excerpt(string $text, int $max = 120): string {
        $text = trim(strip_tags($text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}
if (!function_exists('hg_mobile_chl_kind_label')) {
    function hg_mobile_chl_kind_label(array $row): string {
        $kind = trim((string)($row['season_kind'] ?? 'temporada'));
        if ($kind === 'historia_personal') return 'Historia personal';
        if ($kind === 'especial') return 'Especial';
        if ($kind === 'inciso') return 'Inciso';
        return 'Temporada';
    }
}
if (!function_exists('hg_mobile_chl_season_label')) {
    function hg_mobile_chl_season_label(array $row): string {
        $kind = trim((string)($row['season_kind'] ?? 'temporada'));
        $number = (int)($row['season_number'] ?? 0);
        $name = trim((string)($row['season_name'] ?? ''));
        if ($kind === 'historia_personal') return $name !== '' ? 'Historia personal - ' . $name : 'Historia personal';
        if ($kind === 'especial') return $name !== '' ? 'Especial - ' . $name : 'Especial';
        if ($kind === 'inciso') {
            $inciso = $number;
            if ($inciso >= 100 && $inciso < 200) $inciso -= 100;
            $prefix = 'Inciso ' . ($inciso > 0 ? $inciso : '?');
            return $name !== '' ? $prefix . ' - ' . $name : $prefix;
        }
        $prefix = 'T' . ($number > 0 ? $number : '?');
        return $name !== '' ? $prefix . ' - ' . $name : $prefix;
    }
}
if (!function_exists('hg_mobile_chl_filter_options')) {
    function hg_mobile_chl_filter_options(array $rows, string $source): array {
        $values = [];
        foreach ($rows as $row) {
            $value = trim((string)($row[$source] ?? ''));
            if ($value === '') $value = '-';
            $values[$value] = $value;
        }
        $values = array_values($values);
        usort($values, static function (string $a, string $b): int {
            if ($a === '-' && $b !== '-') return 1;
            if ($b === '-' && $a !== '-') return -1;
            return strnatcasecmp($a, $b);
        });
        return $values;
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_chapters_list', 'missing DB connection');
    hg_public_render_error('Capítulos no disponibles', 'No se pudo cargar el listado.');
    return;
}

$rows = hg_chapters_fetch_mobile_list($link, '', 0);
if ($rows === null) {
    hg_public_log_error('mobile_chapters_list', 'query failed: ' . mysqli_error($link));
    $rows = [];
}

$tableRows = hg_chapters_fetch_table_rows($link) ?? [];
$chapterMeta = [];
foreach ($tableRows as $tableRow) {
    $chapterMeta[(int)($tableRow['chapter_id'] ?? 0)] = [
        'chronicle_name' => trim((string)($tableRow['chronicle_name'] ?? '')),
    ];
}

foreach ($rows as &$row) {
    $id = (int)($row['id'] ?? 0);
    $row['_filter_kind'] = hg_mobile_chl_kind_label($row);
    $row['_filter_chronicle'] = $chapterMeta[$id]['chronicle_name'] ?? '';
    if ($row['_filter_chronicle'] === '') $row['_filter_chronicle'] = '-';
    $row['_filter_season'] = hg_mobile_chl_season_label($row);
}
unset($row);

$filters = [
    ['key'=>'kind','label'=>'Tipo de temporada','source'=>'_filter_kind','all'=>'Todos','depends_on'=>[]],
    ['key'=>'chronicle','label'=>'Crónica','source'=>'_filter_chronicle','all'=>'Todas','depends_on'=>['kind']],
    ['key'=>'season','label'=>'Temporada','source'=>'_filter_season','all'=>'Todas','depends_on'=>['kind','chronicle']],
];
?>

<section class="hg-mobile-section">
    <h1>Capítulos</h1>
    <p class="hg-mobile-muted"><?= number_format(count($rows), 0, ',', '.') ?> capítulos</p>
</section>

<section class="hg-mobile-section">
    <div class="hg-mobile-card-list"
         data-mobile-paginated
         data-mobile-search="1"
         data-page-size="20"
         data-search-placeholder="Buscar capítulos"
         data-empty-text="No hay capítulos con esos filtros."
         data-mobile-cascading-filters="1">
        <details class="hg-mobile-details">
            <summary>Filtros <span data-mobile-list-filter-count hidden></span></summary>
            <div class="hg-mobile-filterbar">
                <?php foreach ($filters as $filter): ?>
                    <?php
                        $options = hg_mobile_chl_filter_options($rows, (string)$filter['source']);
                        $dependsOn = implode(',', $filter['depends_on']);
                    ?>
                    <label>
                        <span><?= hg_mobile_chl_h($filter['label']) ?></span>
                        <select data-mobile-list-filter
                                data-mobile-filter-key="<?= hg_mobile_chl_h($filter['key']) ?>"
                                data-mobile-filter-depends-on="<?= hg_mobile_chl_h($dependsOn) ?>"
                                aria-label="Filtrar por <?= hg_mobile_chl_h($filter['label']) ?>">
                            <option value=""><?= hg_mobile_chl_h($filter['all']) ?></option>
                            <?php foreach ($options as $option): ?>
                                <option value="<?= hg_mobile_chl_h($option) ?>"><?= hg_mobile_chl_h($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endforeach; ?>
                <button type="button" data-mobile-list-filter-clear disabled>Limpiar filtros</button>
            </div>
        </details>

        <?php if (empty($rows)): ?><p class="hg-mobile-muted">No hay capítulos disponibles.</p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <?php
                $id = (int)($row['id'] ?? 0);
                $href = hg_mobile_chl_url($link, 'dim_chapters', '/chapters', $id);
                $kind = (string)($row['season_kind'] ?? 'temporada');
                $code = $kind === 'temporada' ? sprintf('%dx%02d', (int)$row['season_number'], (int)$row['chapter_number']) : sprintf('%02d', (int)$row['chapter_number']);
                $date = hg_mobile_chl_date($row['played_date'] ?? '');
                $excerpt = hg_mobile_chl_excerpt((string)($row['synopsis'] ?? ''));
                $chronicle = (string)($row['_filter_chronicle'] ?? '-');
                $season = (string)($row['_filter_season'] ?? '');
                $search = trim($code . ' ' . (string)($row['name'] ?? '') . ' ' . $season . ' ' . $chronicle . ' ' . strip_tags((string)($row['synopsis'] ?? '')));
            ?>
            <a class="hg-mobile-card"
               href="<?= hg_mobile_chl_h($href) ?>"
               data-mobile-item
               data-mobile-search="<?= hg_mobile_chl_h($search) ?>"
               data-mobile-filter-kind="<?= hg_mobile_chl_h($row['_filter_kind'] ?? '') ?>"
               data-mobile-filter-chronicle="<?= hg_mobile_chl_h($chronicle) ?>"
               data-mobile-filter-season="<?= hg_mobile_chl_h($season) ?>">
                <strong><?= hg_mobile_chl_h($code . ' - ' . (string)($row['name'] ?? '')) ?></strong>
                <span><?= hg_mobile_chl_h($season) ?><?= $chronicle !== '-' ? ' - ' . hg_mobile_chl_h($chronicle) : '' ?><?= $date !== '' ? ' - ' . hg_mobile_chl_h($date) : '' ?> - <?= (int)($row['participant_count'] ?? 0) ?> participantes</span>
                <?php if ($excerpt !== ''): ?><span><?= hg_mobile_chl_h($excerpt) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>