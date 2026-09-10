<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
require_once(__DIR__ . '/../../domains/characters/queries.php');

$metaTitle = "Personajes | Heaven's Gate";
$metaDescription = "Listado móvil de personajes.";
$pageSect = 'Personajes';

if (!function_exists('hg_mobile_char_h')) {
    function hg_mobile_char_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_characters_list', 'missing DB connection');
    hg_public_render_error('Personajes no disponibles', 'No se pudo cargar la lista de personajes.');
    return;
}

$queryText = hg_request_query_param($hgRequest, 'q');
$typeFilter = max(0, (int)hg_request_query_param($hgRequest, 'type'));
$groupFilter = max(0, (int)hg_request_query_param($hgRequest, 'group'));
$organizationFilter = max(0, (int)hg_request_query_param($hgRequest, 'organization'));
$systemFilter = max(0, (int)hg_request_query_param($hgRequest, 'system'));
$statusFilter = max(0, (int)hg_request_query_param($hgRequest, 'status'));
$page = max(1, (int)hg_request_query_param($hgRequest, 'pag', '1'));
$pageSize = 24;
$chronicleScope = isset($excludeChronicles) ? $excludeChronicles : '2,7';

$filters = hg_characters_mobile_filter_options($link, $chronicleScope);
$typeRows = $filters['types'];
$groupRows = $filters['groups'];
$organizationRows = $filters['organizations'];
$systemRows = $filters['systems'];
$statusRows = $filters['statuses'];

$pageData = hg_characters_fetch_mobile_page(
    $link,
    [
        'query' => $queryText,
        'type' => $typeFilter,
        'group' => $groupFilter,
        'organization' => $organizationFilter,
        'system' => $systemFilter,
        'status' => $statusFilter,
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

function hg_mobile_char_page_url(int $page, string $queryText, int $typeFilter, int $groupFilter, int $organizationFilter, int $systemFilter, int $statusFilter): string
{
    $query = ['pag' => $page];
    if ($queryText !== '') {
        $query['q'] = $queryText;
    }
    if ($typeFilter > 0) {
        $query['type'] = $typeFilter;
    }
    if ($groupFilter > 0) $query['group'] = $groupFilter;
    if ($organizationFilter > 0) $query['organization'] = $organizationFilter;
    if ($systemFilter > 0) $query['system'] = $systemFilter;
    if ($statusFilter > 0) $query['status'] = $statusFilter;
    return '/characters?' . http_build_query($query);
}
?>

<section class="hg-mobile-section">
    <h1>Personajes</h1>

    <form class="hg-mobile-filterbar" action="/characters" method="get">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= hg_mobile_char_h($queryText) ?>" placeholder="Nombre, alias, concepto">
        </label>
        <label>
            <span>Tipo</span>
            <select name="type">
                <option value="0">Todos</option>
                <?php foreach ($typeRows as $typeRow): ?>
                    <?php $typeId = (int)($typeRow['id'] ?? 0); ?>
                    <option value="<?= $typeId ?>"<?= $typeId === $typeFilter ? ' selected' : '' ?>>
                        <?= hg_mobile_char_h($typeRow['kind'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Grupo</span>
            <select name="group">
                <option value="0">Todos</option>
                <?php foreach ($groupRows as $row): ?>
                    <?php $id = (int)($row['id'] ?? 0); ?>
                    <option value="<?= $id ?>"<?= $id === $groupFilter ? ' selected' : '' ?>><?= hg_mobile_char_h($row['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Organizacion</span>
            <select name="organization">
                <option value="0">Todas</option>
                <?php foreach ($organizationRows as $row): ?>
                    <?php $id = (int)($row['id'] ?? 0); ?>
                    <option value="<?= $id ?>"<?= $id === $organizationFilter ? ' selected' : '' ?>><?= hg_mobile_char_h($row['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Sistema</span>
            <select name="system">
                <option value="0">Todos</option>
                <?php foreach ($systemRows as $row): ?>
                    <?php $id = (int)($row['id'] ?? 0); ?>
                    <option value="<?= $id ?>"<?= $id === $systemFilter ? ' selected' : '' ?>><?= hg_mobile_char_h($row['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="0">Todos</option>
                <?php foreach ($statusRows as $row): ?>
                    <?php $id = (int)($row['id'] ?? 0); ?>
                    <option value="<?= $id ?>"<?= $id === $statusFilter ? ' selected' : '' ?>><?= hg_mobile_char_h($row['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Filtrar</button>
    </form>

    <p class="hg-mobile-muted"><?= number_format($totalRows, 0, ',', '.') ?> personajes</p>

    <div class="hg-mobile-character-list">
        <?php if (empty($characters)): ?>
            <p class="hg-mobile-muted">No se pudieron cargar personajes para esta pagina.</p>
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
        <nav class="hg-mobile-pagination" aria-label="Paginacion de personajes">
            <?php if ($page > 1): ?>
                <a href="<?= hg_mobile_char_h(hg_mobile_char_page_url($page - 1, $queryText, $typeFilter, $groupFilter, $organizationFilter, $systemFilter, $statusFilter)) ?>">Anterior</a>
            <?php endif; ?>
            <span><?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="<?= hg_mobile_char_h(hg_mobile_char_page_url($page + 1, $queryText, $typeFilter, $groupFilter, $organizationFilter, $systemFilter, $statusFilter)) ?>">Siguiente</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
