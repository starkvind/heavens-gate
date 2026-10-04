<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
require_once(__DIR__ . '/../../domains/characters/queries.php');
require_once(__DIR__ . '/../../domains/characters/mobile_multiselect.php');

$metaTitle = "Personajes | Heaven's Gate";
$metaDescription = "Listado móvil de personajes.";
$pageSect = 'Personajes';

if (!function_exists('hg_mobile_char_h')) {
    function hg_mobile_char_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_char_csv')) {
    function hg_mobile_char_csv(array $ids): string
    {
        return implode(',', array_map('intval', $ids));
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_characters_list', 'missing DB connection');
    hg_public_render_error('Personajes no disponibles', 'No se pudo cargar la lista de personajes.');
    return;
}

$queryText = hg_request_query_param($hgRequest, 'q');
$typeFilters = hg_characters_multifilter_ids(hg_request_query_param($hgRequest, 'type'));
$groupFilters = hg_characters_multifilter_ids(hg_request_query_param($hgRequest, 'group'));
$organizationFilters = hg_characters_multifilter_ids(hg_request_query_param($hgRequest, 'organization'));
$systemFilters = hg_characters_multifilter_ids(hg_request_query_param($hgRequest, 'system'));
$statusFilters = hg_characters_multifilter_ids(hg_request_query_param($hgRequest, 'status'));
$page = max(1, (int)hg_request_query_param($hgRequest, 'pag', '1'));
$pageSize = 24;
$chronicleScope = isset($excludeChronicles) ? $excludeChronicles : '2,7';

$filters = hg_characters_mobile_filter_options($link, $chronicleScope);
$typeRows = $filters['types'];
$groupRows = $filters['groups'];
$organizationRows = $filters['organizations'];
$systemRows = $filters['systems'];
$statusRows = $filters['statuses'];

$pageData = hg_characters_fetch_mobile_multiselect_page(
    $link,
    [
        'query' => $queryText,
        'type' => $typeFilters,
        'group' => $groupFilters,
        'organization' => $organizationFilters,
        'system' => $systemFilters,
        'status' => $statusFilters,
    ],
    $chronicleScope,
    $page,
    $pageSize
);

if ($pageData['count_error'] !== '') {
    hg_public_log_error('mobile_characters_list', 'count query failed: ' . $pageData['count_error']);
}
if ($pageData['list_error'] !== '') {
    hg_public_log_error('mobile_characters_list', 'list query failed: ' . $pageData['list_error']);
}

$totalRows = (int)$pageData['total'];
$totalPages = (int)$pageData['total_pages'];
$page = (int)$pageData['page'];
$characters = $pageData['characters'];

function hg_mobile_char_url(array $row, mysqli $link): string
{
    $id = (int)($row['id'] ?? 0);
    return $id > 0 ? pretty_url($link, 'fact_characters', '/characters', $id) : '/characters';
}

function hg_mobile_char_page_url(
    int $page,
    string $queryText,
    array $typeFilters,
    array $groupFilters,
    array $organizationFilters,
    array $systemFilters,
    array $statusFilters
): string {
    $query = ['pag' => $page];
    if ($queryText !== '') $query['q'] = $queryText;
    if ($typeFilters) $query['type'] = hg_mobile_char_csv($typeFilters);
    if ($groupFilters) $query['group'] = hg_mobile_char_csv($groupFilters);
    if ($organizationFilters) $query['organization'] = hg_mobile_char_csv($organizationFilters);
    if ($systemFilters) $query['system'] = hg_mobile_char_csv($systemFilters);
    if ($statusFilters) $query['status'] = hg_mobile_char_csv($statusFilters);
    return '/characters?' . http_build_query($query);
}
?>

<section class="hg-mobile-section">
    <h1>Personajes</h1>

    <form class="hg-mobile-filterbar" action="/characters" method="get" data-mobile-server-filter-form>
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= hg_mobile_char_h($queryText) ?>" placeholder="Nombre, alias, concepto">
        </label>

        <details class="hg-mobile-details">
            <summary>Filtros <span data-mobile-server-filter-count hidden></span></summary>
            <div class="hg-mobile-filterbar">
                <label>
                    <span>Tipo</span>
                    <select multiple data-mobile-server-multifilter data-mobile-filter-key="type" aria-label="Filtrar por tipo">
                        <option value="">Todos</option>
                        <?php foreach ($typeRows as $typeRow): ?>
                            <?php $typeId = (int)($typeRow['id'] ?? 0); ?>
                            <option value="<?= $typeId ?>"<?= in_array($typeId, $typeFilters, true) ? ' selected' : '' ?>><?= hg_mobile_char_h($typeRow['kind'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="type" value="<?= hg_mobile_char_h(hg_mobile_char_csv($typeFilters)) ?>" data-mobile-server-filter-value="type">
                </label>

                <label>
                    <span>Grupo</span>
                    <select multiple data-mobile-server-multifilter data-mobile-filter-key="group" aria-label="Filtrar por grupo">
                        <option value="">Todos</option>
                        <?php foreach ($groupRows as $row): ?>
                            <?php $id = (int)($row['id'] ?? 0); ?>
                            <option value="<?= $id ?>"<?= in_array($id, $groupFilters, true) ? ' selected' : '' ?>><?= hg_mobile_char_h($row['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="group" value="<?= hg_mobile_char_h(hg_mobile_char_csv($groupFilters)) ?>" data-mobile-server-filter-value="group">
                </label>

                <label>
                    <span>Organización</span>
                    <select multiple data-mobile-server-multifilter data-mobile-filter-key="organization" aria-label="Filtrar por organización">
                        <option value="">Todas</option>
                        <?php foreach ($organizationRows as $row): ?>
                            <?php $id = (int)($row['id'] ?? 0); ?>
                            <option value="<?= $id ?>"<?= in_array($id, $organizationFilters, true) ? ' selected' : '' ?>><?= hg_mobile_char_h($row['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="organization" value="<?= hg_mobile_char_h(hg_mobile_char_csv($organizationFilters)) ?>" data-mobile-server-filter-value="organization">
                </label>

                <label>
                    <span>Sistema</span>
                    <select multiple data-mobile-server-multifilter data-mobile-filter-key="system" aria-label="Filtrar por sistema">
                        <option value="">Todos</option>
                        <?php foreach ($systemRows as $row): ?>
                            <?php $id = (int)($row['id'] ?? 0); ?>
                            <option value="<?= $id ?>"<?= in_array($id, $systemFilters, true) ? ' selected' : '' ?>><?= hg_mobile_char_h($row['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="system" value="<?= hg_mobile_char_h(hg_mobile_char_csv($systemFilters)) ?>" data-mobile-server-filter-value="system">
                </label>

                <label>
                    <span>Estado</span>
                    <select multiple data-mobile-server-multifilter data-mobile-filter-key="status" aria-label="Filtrar por estado">
                        <option value="">Todos</option>
                        <?php foreach ($statusRows as $row): ?>
                            <?php $id = (int)($row['id'] ?? 0); ?>
                            <option value="<?= $id ?>"<?= in_array($id, $statusFilters, true) ? ' selected' : '' ?>><?= hg_mobile_char_h($row['label'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="status" value="<?= hg_mobile_char_h(hg_mobile_char_csv($statusFilters)) ?>" data-mobile-server-filter-value="status">
                </label>

                <div class="hg-mobile-server-filter-actions">
                    <button type="submit">Aplicar filtros</button>
                    <button type="button" data-mobile-server-filter-clear>Limpiar filtros</button>
                </div>
            </div>
        </details>
    </form>

    <p class="hg-mobile-muted"><?= number_format($totalRows, 0, ',', '.') ?> personajes</p>

    <div class="hg-mobile-character-list">
        <?php if (empty($characters)): ?>
            <p class="hg-mobile-muted">No se pudieron cargar personajes para estos filtros.</p>
        <?php endif; ?>
        <?php foreach ($characters as $character): ?>
            <?php
                $imageUrl = trim((string)($character['image_url'] ?? ''));
                $href = hg_mobile_char_url($character, $link);
            ?>
            <a class="hg-mobile-character-card" href="<?= hg_mobile_char_h($href) ?>">
                <?php if ($imageUrl !== ''): ?>
                    <img src="<?= hg_mobile_char_h($imageUrl) ?>" alt="">
                <?php else: ?>
                    <span class="hg-mobile-character-avatar" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="hg-mobile-character-main">
                    <strong><?= hg_mobile_char_h($character['name'] ?? '') ?></strong>
                    <?php if (!empty($character['alias'])): ?>
                        <em><?= hg_mobile_char_h($character['alias']) ?></em>
                    <?php endif; ?>
                    <span><?= hg_mobile_char_h($character['concept'] ?? '') ?></span>
                    <small>
                        <?= hg_mobile_char_h($character['type_name'] ?? '') ?>
                        <?php if (!empty($character['system_name'])): ?>
                            - <?= hg_mobile_char_h($character['system_name']) ?>
                        <?php endif; ?>
                    </small>
                    <small>
                        <?= hg_mobile_char_h($character['pack_name'] ?? '') ?>
                        <?php if (!empty($character['organization_name'])): ?>
                            - <?= hg_mobile_char_h($character['organization_name']) ?>
                        <?php endif; ?>
                    </small>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="hg-mobile-pagination" aria-label="Paginación de personajes">
            <?php if ($page > 1): ?>
                <a href="<?= hg_mobile_char_h(hg_mobile_char_page_url($page - 1, $queryText, $typeFilters, $groupFilters, $organizationFilters, $systemFilters, $statusFilters)) ?>">Anterior</a>
            <?php endif; ?>
            <span><?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="<?= hg_mobile_char_h(hg_mobile_char_page_url($page + 1, $queryText, $typeFilters, $groupFilters, $organizationFilters, $systemFilters, $statusFilters)) ?>">Siguiente</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
