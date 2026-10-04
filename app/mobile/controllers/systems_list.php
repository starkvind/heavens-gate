<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
require_once __DIR__ . '/../../domains/systems/queries.php';

$metaTitle = "Sistemas | Heaven's Gate";
$metaDescription = 'Listado móvil de sistemas sobrenaturales.';
$pageSect = 'Sistemas';

if (!function_exists('hg_mobile_sys_list_h')) {
    function hg_mobile_sys_list_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_sys_list_url')) {
    function hg_mobile_sys_list_url(mysqli $link, int $id): string
    {
        return $id > 0 && function_exists('pretty_url')
            ? pretty_url($link, 'dim_systems', '/systems', $id)
            : '/systems/' . $id;
    }
}

if (!function_exists('hg_mobile_sys_list_image')) {
    function hg_mobile_sys_list_image($value): string
    {
        $image = trim((string)$value);
        return $image !== '' ? $image : 'img/system/nada.webp';
    }
}

if (!function_exists('hg_mobile_sys_list_excerpt')) {
    function hg_mobile_sys_list_excerpt(string $text, int $max = 140): string
    {
        $text = trim(strip_tags($text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        }
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}

if (!function_exists('hg_mobile_sys_list_options')) {
    function hg_mobile_sys_list_options(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = trim((string)($row[$field] ?? ''));
            if ($value === '') $value = '-';
            $values[$value] = $value;
        }
        natcasesort($values);
        return array_values($values);
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_systems_list', 'missing DB connection');
    hg_public_render_error('Sistemas no disponibles', 'No se pudo cargar Sistemas.');
    return;
}

mysqli_set_charset($link, 'utf8mb4');
$systems = hg_systems_fetch_catalog($link);
if ($systems === false) {
    hg_public_log_error('mobile_systems_list', 'list query failed: ' . mysqli_error($link));
    $systems = [];
}

$systemOptions = hg_mobile_sys_list_options($systems, 'system_name');
$originOptions = hg_mobile_sys_list_options($systems, 'system_origin');
?>

<section class="hg-mobile-section">
    <h1>Sistemas</h1>
    <p class="hg-mobile-muted"><?= number_format(count($systems), 0, ',', '.') ?> sistemas</p>
</section>

<section class="hg-mobile-section">
    <div class="hg-mobile-card-list hg-mobile-sys-list" data-mobile-paginated data-mobile-search="1" data-mobile-cascading-filters="1" data-page-size="20" data-search-placeholder="Buscar sistema u origen" data-empty-text="No hay sistemas con esos filtros.">
        <?php if ($systems): ?>
            <details class="hg-mobile-details">
                <summary>Filtros <span data-mobile-list-filter-count hidden></span></summary>
                <div class="hg-mobile-filterbar">
                    <label>
                        <span>Sistema</span>
                        <select data-mobile-list-filter data-mobile-filter-key="system" data-mobile-filter-depends-on="" aria-label="Filtrar por sistema">
                            <option value="">Todos</option>
                            <?php foreach ($systemOptions as $option): ?>
                                <option value="<?= hg_mobile_sys_list_h($option) ?>"><?= hg_mobile_sys_list_h($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Origen</span>
                        <select data-mobile-list-filter data-mobile-filter-key="origin" data-mobile-filter-depends-on="system" aria-label="Filtrar por origen">
                            <option value="">Todos</option>
                            <?php foreach ($originOptions as $option): ?>
                                <option value="<?= hg_mobile_sys_list_h($option) ?>"><?= hg_mobile_sys_list_h($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="button" data-mobile-list-filter-clear disabled>Limpiar filtros</button>
                </div>
            </details>
        <?php endif; ?>

        <?php if (!$systems): ?><p class="hg-mobile-muted">No hay sistemas disponibles.</p><?php endif; ?>
        <?php foreach ($systems as $system): ?>
            <?php
                $id = (int)($system['system_id'] ?? 0);
                $name = trim((string)($system['system_name'] ?? ''));
                $origin = trim((string)($system['system_origin'] ?? ''));
                $filterName = $name !== '' ? $name : '-';
                $filterOrigin = $origin !== '' ? $origin : '-';
                $desc = hg_mobile_sys_list_excerpt((string)($system['system_description'] ?? ''));
                $search = trim($name . ' ' . $origin . ' ' . $desc);
            ?>
            <a class="hg-mobile-card hg-mobile-sys-card"
               href="<?= hg_mobile_sys_list_h(hg_mobile_sys_list_url($link, $id)) ?>"
               data-mobile-item
               data-mobile-search="<?= hg_mobile_sys_list_h($search) ?>"
               data-mobile-filter-system="<?= hg_mobile_sys_list_h($filterName) ?>"
               data-mobile-filter-origin="<?= hg_mobile_sys_list_h($filterOrigin) ?>">
                <img src="<?= hg_mobile_sys_list_h(hg_mobile_sys_list_image($system['system_img'] ?? '')) ?>" alt="">
                <span class="hg-mobile-sys-card-main">
                    <strong><?= hg_mobile_sys_list_h($name !== '' ? $name : ('#' . $id)) ?></strong>
                    <span><?= hg_mobile_sys_list_h(trim($origin . ((int)($system['system_forms'] ?? 0) === 1 ? ' | Formas' : ''))) ?></span>
                    <?php if ($desc !== ''): ?><small><?= hg_mobile_sys_list_h($desc) ?></small><?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
