<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
require_once(__DIR__ . '/../../domains/documents/queries.php');

$metaTitle = "Documentos | Heaven's Gate";
$metaDescription = 'Archivo móvil de documentos.';
$pageSect = 'Documentos';

if (!function_exists('hg_mobile_docs_list_h')) {
    function hg_mobile_docs_list_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_docs_list_url')) {
    function hg_mobile_docs_list_url(mysqli $link, int $id): string
    {
        return $id > 0 && function_exists('pretty_url')
            ? pretty_url($link, 'fact_docs', '/documents', $id)
            : '/documents/' . $id;
    }
}

if (!function_exists('hg_mobile_docs_list_excerpt')) {
    function hg_mobile_docs_list_excerpt(string $text, int $max = 120): string
    {
        $text = trim(strip_tags($text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        }
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}

if (!function_exists('hg_mobile_docs_list_filter_value')) {
    function hg_mobile_docs_list_filter_value(array $row, string $source): string
    {
        $value = trim((string)($row[$source] ?? ''));
        return $value !== '' ? $value : '-';
    }
}

if (!function_exists('hg_mobile_docs_list_filter_options')) {
    function hg_mobile_docs_list_filter_options(array $rows, string $source): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = hg_mobile_docs_list_filter_value($row, $source);
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
    hg_public_log_error('mobile_documents_list', 'missing DB connection');
    hg_public_render_error('Documentos no disponibles', 'No se pudo cargar el archivo.');
    return;
}

$docs = hg_documents_fetch_mobile_catalog($link);
if ($docs === null) {
    hg_public_log_error('mobile_documents_list', 'list query failed: ' . mysqli_error($link));
    $docs = [];
}

$filters = [
    ['key' => 'category', 'label' => 'Categoría', 'source' => 'category', 'all' => 'Todas', 'depends_on' => []],
    ['key' => 'origin', 'label' => 'Origen', 'source' => 'origin', 'all' => 'Todos', 'depends_on' => ['category']],
];
?>
<section class="hg-mobile-section">
    <h1>Documentos</h1>
    <p class="hg-mobile-muted"><?= number_format(count($docs), 0, ',', '.') ?> documentos</p>
</section>

<section class="hg-mobile-section">
    <div class="hg-mobile-card-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar documentos" data-empty-text="No hay documentos con esos filtros." data-mobile-cascading-filters="1">
        <details class="hg-mobile-details">
            <summary>Filtros <span data-mobile-list-filter-count hidden></span></summary>
            <div class="hg-mobile-filterbar">
                <?php foreach ($filters as $filter): ?>
                    <?php
                        $options = hg_mobile_docs_list_filter_options($docs, (string)$filter['source']);
                        $dependsOn = implode(',', $filter['depends_on']);
                    ?>
                    <label>
                        <span><?= hg_mobile_docs_list_h($filter['label']) ?></span>
                        <select data-mobile-list-filter data-mobile-filter-key="<?= hg_mobile_docs_list_h($filter['key']) ?>" data-mobile-filter-depends-on="<?= hg_mobile_docs_list_h($dependsOn) ?>" aria-label="Filtrar por <?= hg_mobile_docs_list_h($filter['label']) ?>">
                            <option value=""><?= hg_mobile_docs_list_h($filter['all']) ?></option>
                            <?php foreach ($options as $option): ?>
                                <option value="<?= hg_mobile_docs_list_h($option) ?>"><?= hg_mobile_docs_list_h($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endforeach; ?>
                <button type="button" data-mobile-list-filter-clear disabled>Limpiar filtros</button>
            </div>
        </details>

        <?php if (empty($docs)): ?><p class="hg-mobile-muted">No hay documentos disponibles.</p><?php endif; ?>
        <?php foreach ($docs as $doc): ?>
            <?php
                $id = (int)($doc['id'] ?? 0);
                $href = hg_mobile_docs_list_url($link, $id);
                $category = hg_mobile_docs_list_filter_value($doc, 'category');
                $origin = hg_mobile_docs_list_filter_value($doc, 'origin');
                $excerpt = hg_mobile_docs_list_excerpt((string)($doc['content'] ?? ''));
                $search = trim((string)($doc['title'] ?? '') . ' ' . $category . ' ' . $origin . ' ' . strip_tags((string)($doc['content'] ?? '')));
            ?>
            <a class="hg-mobile-card" href="<?= hg_mobile_docs_list_h($href) ?>"
               data-mobile-item
               data-mobile-search="<?= hg_mobile_docs_list_h($search) ?>"
               data-mobile-filter-category="<?= hg_mobile_docs_list_h($category) ?>"
               data-mobile-filter-origin="<?= hg_mobile_docs_list_h($origin) ?>">
                <strong><?= hg_mobile_docs_list_h($doc['title'] ?? '') ?></strong>
                <span><?= hg_mobile_docs_list_h($category) ?><?= $origin !== '-' ? ' - ' . hg_mobile_docs_list_h($origin) : '' ?></span>
                <?php if ($excerpt !== ''): ?><span><?= hg_mobile_docs_list_h($excerpt) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
