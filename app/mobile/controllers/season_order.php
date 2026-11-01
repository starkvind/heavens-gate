<?php
/* Vertical, touch-friendly rendering of the existing season-order data. The
 * desktop D3 graph, underlying model and DB query contracts remain unchanged. */
require_once __DIR__ . '/../../domains/chapters/queries.php';
require_once __DIR__ . '/../../helpers/pretty.php';

$metaTitle = "Orden de temporadas | Heaven's Gate";
$metaDescription = 'Recorridos narrativos de temporadas y arcos.';
$pageSect = 'Temporadas';

if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-mobile-season-order.css');
}

if (!function_exists('hg_mso_h')) {
    function hg_mso_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('hg_mso_kind_label')) {
    function hg_mso_kind_label(string $kind): string
    {
        if ($kind === 'season_range') return 'Rango';
        if ($kind === 'arc') return 'Arco';
        if ($kind === 'custom') return 'Custom';
        return 'Temporada';
    }
}
if (!function_exists('hg_mso_render_card')) {
    function hg_mso_render_card(array $node, bool $secondary = false): void
    {
        $heading = $secondary ? 'h3' : 'h2';
        $title = (string)$node['title'];
        $subtitle = (string)$node['subtitle'];
        $description = (string)$node['description'];
        $rangeLabel = (string)$node['range_label'];
        $href = (string)$node['href'];
        ?>
        <article class="hg-mso-node<?= $secondary ? ' hg-mso-node--secondary' : '' ?>">
            <span class="hg-mso-kicker"><?= $secondary ? 'Rama secundaria' : 'Recorrido principal' ?> · <?= hg_mso_h($node['kind_label']) ?></span>
            <<?= $heading ?> class="hg-mso-title">
                <?php if ($href !== ''): ?>
                    <a href="<?= hg_mso_h($href) ?>"><?= hg_mso_h($title) ?></a>
                <?php else: ?>
                    <?= hg_mso_h($title) ?>
                <?php endif; ?>
            </<?= $heading ?>>
            <?php if ($subtitle !== '' && $subtitle !== $title): ?>
                <p class="hg-mso-subtitle"><?= hg_mso_h($subtitle) ?></p>
            <?php endif; ?>
            <?php if ($rangeLabel !== ''): ?>
                <p class="hg-mso-range"><?= hg_mso_h($rangeLabel) ?></p>
            <?php endif; ?>
            <?php if ($description !== ''): ?>
                <details class="hg-mso-description">
                    <summary>Descripción</summary>
                    <p><?= hg_mso_h($description) ?></p>
                </details>
            <?php endif; ?>
        </article>
        <?php
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    echo '<section class="hg-mobile-section"><h1>Orden de temporadas</h1><p>No se pudo conectar a la base de datos.</p></section>';
    return;
}

$catalog = hg_chapters_fetch_season_order_catalog($link);
if ($catalog === null) {
    echo '<section class="hg-mobile-section"><h1>Orden de temporadas</h1><p>No se pudieron cargar los recorridos.</p></section>';
    return;
}
$orders = [];
foreach ($catalog as $row) {
    $key = trim((string)($row['order_key'] ?? ''));
    if ($key === '') continue;
    $orders[$key] = [
        'label' => trim((string)($row['order_label'] ?? '')) ?: $key,
        'count' => (int)($row['node_count'] ?? 0),
    ];
}
$selectedOrder = (string)hg_request_query_param($hgRequest, 'order');
if ($selectedOrder === '' || !isset($orders[$selectedOrder])) {
    $selectedOrder = $orders ? (string)array_key_first($orders) : '';
}
$nodeRows = $selectedOrder !== '' ? hg_chapters_fetch_season_order_nodes($link, $selectedOrder) : [];
if ($nodeRows === null) {
    echo '<section class="hg-mobile-section"><h1>Orden de temporadas</h1><p>No se pudieron cargar los nodos del recorrido.</p></section>';
    return;
}

/* Match the desktop reader's positioning and parent-fallback semantics.
 * A secondary node with no valid prior main parent attaches to the most
 * recently visited main node; no additional fictional links are created. */
$byId = [];
foreach ($nodeRows as $row) {
    $byId[(int)($row['id'] ?? 0)] = $row;
}
$mainNodes = [];
$secondaryByParent = [];
$lastMainId = 0;
foreach ($nodeRows as $row) {
    $id = (int)($row['id'] ?? 0);
    $type = (string)($row['branch_type'] ?? 'main');
    $seasonId = (int)($row['season_id'] ?? 0);
    $title = trim((string)($row['label'] ?? ''));
    $seasonName = trim((string)($row['season_name'] ?? ''));
    if ($title === '') $title = $seasonName !== '' ? $seasonName : 'Nodo ' . $id;
    $kindLabel = hg_mso_kind_label((string)($row['node_kind'] ?? 'season'));
    $from = isset($row['episode_start']) ? (int)$row['episode_start'] : null;
    $to = isset($row['episode_end']) ? (int)$row['episode_end'] : null;
    $rangeLabel = $from !== null && $to !== null
        ? 'Episodios ' . $from . ' a ' . $to
        : ($from !== null ? 'Desde episodio ' . $from : '');
    $node = [
        'id' => $id,
        'title' => $title,
        'subtitle' => $seasonName !== '' ? $seasonName : $kindLabel,
        'description' => trim(strip_tags((string)($row['description'] ?? ''))),
        'kind_label' => $kindLabel,
        'range_label' => $rangeLabel,
        'href' => $seasonId > 0 ? pretty_url($link, 'dim_seasons', '/seasons', $seasonId) : '',
    ];
    if ($type !== 'secondary') {
        $mainNodes[] = $node;
        $lastMainId = $id;
        continue;
    }
    $parentId = (int)($row['parent_node_id'] ?? 0);
    $parent = $byId[$parentId] ?? null;
    $anchorId = 0;
    if ($parent !== null
        && (string)($parent['branch_type'] ?? 'main') !== 'secondary'
        && (int)($parent['position'] ?? 0) <= (int)($row['position'] ?? 0)) {
        $anchorId = $parentId;
    } elseif ($lastMainId > 0) {
        $anchorId = $lastMainId;
    } elseif ($mainNodes) {
        $anchorId = (int)$mainNodes[0]['id'];
    }
    if ($anchorId > 0) {
        $secondaryByParent[$anchorId][] = $node;
    }
}
?>
<section class="hg-mobile-section hg-mso-page">
    <h1>Orden de temporadas y arcos</h1>
    <p class="hg-mobile-muted">Recorre las temporadas de arriba abajo. Las ramas de color se desprenden de su bloque principal.</p>
    <?php if ($orders): ?>
        <form class="hg-mso-picker" action="/seasons/order" method="get">
            <input type="hidden" name="view" value="mobile">
            <label for="hg-mso-order">Recorrido</label>
            <div class="hg-mso-picker-controls">
                <select id="hg-mso-order" name="order">
                    <?php foreach ($orders as $key => $order): ?>
                        <option value="<?= hg_mso_h($key) ?>" <?= $selectedOrder === $key ? 'selected' : '' ?>><?= hg_mso_h($order['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Ver</button>
            </div>
        </form>
    <?php endif; ?>
    <?php if (!$orders): ?>
        <p class="hg-mobile-muted">Todavía no hay recorridos de temporadas disponibles.</p>
    <?php elseif (!$mainNodes): ?>
        <p class="hg-mobile-muted">Este recorrido no tiene un tronco principal disponible.</p>
    <?php else: ?>
        <ol class="hg-mso-trunk">
            <?php $branchIndex = 0; ?>
            <?php foreach ($mainNodes as $node): ?>
                <li class="hg-mso-step">
                    <?php hg_mso_render_card($node); ?>
                    <?php if (!empty($secondaryByParent[$node['id']])): ?>
                        <ol class="hg-mso-branches" aria-label="Ramas secundarias de <?= hg_mso_h($node['title']) ?>">
                            <?php foreach ($secondaryByParent[$node['id']] as $branch): ?>
                                <?php
                                    // Golden-angle distribution: a different accent for each successive branch.
                                    $hue = ((int)round($branchIndex * 137.508 + 36)) % 360;
                                    ++$branchIndex;
                                ?>
                                <li class="hg-mso-branch" style="--hg-mso-accent: hsl(<?= $hue ?> 78% 68%)">
                                    <?php hg_mso_render_card($branch, true); ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <p class="hg-mso-back"><a href="/seasons?view=mobile">Volver al archivo de temporadas</a></p>
</section>
