<?php
include_once(__DIR__ . '/../../helpers/runtime_response.php');
include_once(__DIR__ . '/../../helpers/pretty.php');

if (!function_exists('hg_so_h')) {
    function hg_so_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_so_plain_text')) {
    function hg_so_plain_text(string $text): string
    {
        $text = trim(strip_tags($text));
        return $text === '' ? '' : $text;
    }
}

if (!function_exists('hg_so_table_exists')) {
    function hg_so_table_exists(mysqli $link, string $table): bool
    {
        $stmt = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return (int)$count > 0;
    }
}

if (!function_exists('hg_so_kind_label')) {
    function hg_so_kind_label(string $kind): string
    {
        if ($kind === 'season_range') return 'Rango';
        if ($kind === 'arc') return 'Arco';
        if ($kind === 'custom') return 'Custom';
        return 'Temporada';
    }
}

setMetaFromPage(
    "Orden de temporadas | Heaven's Gate",
    "Consulta Heaven's Gate por orden jugado, cronologico u otros recorridos narrativos.",
    null,
    'website'
);
echo '<link rel="stylesheet" href="/assets/css/hg-chapters.css">';
echo '<link rel="stylesheet" href="/assets/css/hg-maps.css">';
echo '<link rel="stylesheet" href="/assets/css/hg-main.css">';

if (!hg_runtime_require_db($link, 'season_order', 'public', [
    'title' => 'Orden de temporadas no disponible',
    'message' => 'No se pudo conectar a la base de datos.',
    'include_nav' => true,
])) {
    return;
}

include_once(__DIR__ . '/../../partials/main_nav_bar.php');

$schemaReady = hg_so_table_exists($link, 'bridge_season_order_nodes');
$orders = [];
$selectedOrder = trim((string)($_GET['order'] ?? ''));
$nodes = [];
$renderNodes = [];

if ($schemaReady) {
    $sqlOrders = "
        SELECT order_key, MAX(order_label) AS order_label, COUNT(*) AS node_count, MIN(position) AS first_position
        FROM bridge_season_order_nodes
        WHERE is_active = 1
        GROUP BY order_key
        ORDER BY first_position ASC, order_key ASC
    ";
    if ($rsOrders = $link->query($sqlOrders)) {
        while ($row = $rsOrders->fetch_assoc()) {
            $key = trim((string)($row['order_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $orders[$key] = [
                'order_key' => $key,
                'order_label' => trim((string)($row['order_label'] ?? '')) !== '' ? (string)$row['order_label'] : $key,
                'node_count' => (int)($row['node_count'] ?? 0),
            ];
        }
        $rsOrders->close();
    }

    if ($selectedOrder === '' && !empty($orders)) {
        $selectedOrder = (string)array_key_first($orders);
    }
    if ($selectedOrder !== '' && !isset($orders[$selectedOrder])) {
        $selectedOrder = (string)array_key_first($orders);
    }

    if ($selectedOrder !== '') {
        $sqlNodes = "
            SELECT
                n.id,
                n.position,
                n.branch_type,
                n.parent_node_id,
                n.node_kind,
                n.season_id,
                n.episode_start,
                n.episode_end,
                n.label,
                n.description,
                s.name AS season_name
            FROM bridge_season_order_nodes n
            LEFT JOIN dim_seasons s ON s.id = n.season_id
            WHERE n.order_key = ?
              AND n.is_active = 1
            ORDER BY n.position ASC, n.id ASC
        ";
        if ($stmtNodes = $link->prepare($sqlNodes)) {
            $stmtNodes->bind_param('s', $selectedOrder);
            $stmtNodes->execute();
            $rsNodes = $stmtNodes->get_result();
            while ($row = $rsNodes->fetch_assoc()) {
                $nodeId = (int)($row['id'] ?? 0);
                $seasonId = (int)($row['season_id'] ?? 0);
                $nodes[$nodeId] = [
                    'id' => $nodeId,
                    'position' => (int)($row['position'] ?? 0),
                    'branch_type' => (string)($row['branch_type'] ?? 'main'),
                    'parent_node_id' => (int)($row['parent_node_id'] ?? 0),
                    'node_kind' => (string)($row['node_kind'] ?? 'season'),
                    'season_id' => $seasonId,
                    'episode_start' => ($row['episode_start'] !== null) ? (int)$row['episode_start'] : null,
                    'episode_end' => ($row['episode_end'] !== null) ? (int)$row['episode_end'] : null,
                    'label' => trim((string)($row['label'] ?? '')) !== '' ? (string)$row['label'] : (string)($row['season_name'] ?? ('Nodo ' . $nodeId)),
                    'description' => (string)($row['description'] ?? ''),
                    'season_name' => (string)($row['season_name'] ?? ''),
                    'href' => $seasonId > 0 ? pretty_url($link, 'dim_seasons', '/seasons', $seasonId) : '',
                ];
            }
            $stmtNodes->close();
        }
    }
}

if (!empty($orders) && isset($orders[$selectedOrder])) {
    foreach ($nodes as $node) {
        $rangeLabel = '';
        if ($node['episode_start'] !== null && $node['episode_end'] !== null) {
            $rangeLabel = 'Episodios ' . (int)$node['episode_start'] . ' a ' . (int)$node['episode_end'];
        } elseif ($node['episode_start'] !== null) {
            $rangeLabel = 'Desde episodio ' . (int)$node['episode_start'];
        }

        $color = '#66b7ff';
        if ($node['branch_type'] === 'secondary') {
            $color = '#28c7a9';
        } elseif ($node['node_kind'] === 'arc') {
            $color = '#f3bb5f';
        } elseif ($node['node_kind'] === 'custom') {
            $color = '#c98dff';
        } elseif ($node['node_kind'] === 'season_range') {
            $color = '#ff8a72';
        }

        $renderNodes[] = [
            'id' => (int)$node['id'],
            'position' => (int)$node['position'],
            'branch_type' => (string)$node['branch_type'],
            'parent_node_id' => (int)$node['parent_node_id'],
            'title' => (string)$node['label'],
            'subtitle' => $node['season_name'] !== '' ? $node['season_name'] : hg_so_kind_label((string)$node['node_kind']),
            'description' => hg_so_plain_text((string)$node['description']),
            'meta' => hg_so_kind_label((string)$node['node_kind']),
            'href' => (string)$node['href'],
            'color' => $color,
            'range_label' => $rangeLabel,
        ];
    }
}
?>
<script type="text/javascript" src="/assets/vendor/d3/d3.v7.min.js"></script>

<style>
.org-shell-root {
    max-width: 1280px;
}

.org-stage-block {
    padding: 16px 18px 18px;
}

.org-quickbar {
    display: grid;
    grid-template-columns: minmax(220px, 1.05fr) minmax(260px, 1.45fr) max-content;
    gap: 10px;
    align-items: end;
    margin-bottom: 12px;
}

.org-quick-field {
    display: grid;
    gap: 6px;
}

.org-quick-field label {
    color: var(--map-muted);
    font-size: .82rem;
    text-transform: uppercase;
    letter-spacing: .06em;
}

.org-actions {
    display: flex;
    flex-wrap: nowrap;
    justify-content: flex-end;
    gap: 8px;
}

.org-btn,
.org-search,
.org-select {
    min-height: 40px;
    width: 100%;
    box-sizing: border-box;
    border-radius: 10px;
    border: 1px solid rgba(0, 0, 153, .8);
    background: rgba(4, 10, 26, .92);
    color: #fff;
    padding: 8px 10px;
    font: inherit;
}

.org-search::placeholder {
    color: rgba(217, 232, 255, .54);
}

.org-btn {
    width: 42px;
    height: 40px;
    min-width: 42px;
    padding: 0;
    font-size: 1.08rem;
    line-height: 1;
    cursor: pointer;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.03);
    transition: transform .14s ease, border-color .14s ease, background .14s ease;
}

.org-btn:hover,
.org-btn:focus-visible,
.org-search:focus,
.org-select:focus {
    border-color: rgba(51, 204, 204, .58);
    background: rgba(7, 17, 38, .98);
    outline: none;
}

.org-btn:hover {
    transform: translateY(-1px);
}

.org-workspace {
    position: relative;
}

.chart-container {
    min-height: 78vh;
    height: 78vh;
    width: 100%;
    border: 1px solid rgba(0, 0, 153, .9);
    border-radius: 12px;
    overflow: hidden;
    background:
        linear-gradient(rgba(102, 183, 255, .05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(102, 183, 255, .05) 1px, transparent 1px),
        radial-gradient(circle at top left, rgba(52, 102, 191, .28), transparent 36%),
        linear-gradient(180deg, #06112b 0%, #040a19 100%);
    background-size: 28px 28px, 28px 28px, auto, auto;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.03), 0 16px 32px rgba(0,0,0,.24);
}

.chart-container.is-org-fullscreen,
.chart-container:fullscreen {
    width: 100vw;
    min-height: 100vh;
    height: 100vh;
    border-radius: 0;
}

.chart-container.is-org-fullscreen,
.chart-container:-webkit-full-screen {
    width: 100vw;
    min-height: 100vh;
    height: 100vh;
    border-radius: 0;
}

.season-order-empty {
    padding: 16px;
    border: 1px dashed #2852a7;
    color: #dcecff;
}

.season-order-empty a {
    color: #dff5ff;
}

@media (max-width: 980px) {
    .org-quickbar {
        grid-template-columns: 1fr;
    }

    .org-actions {
        justify-content: flex-start;
        overflow-x: auto;
    }

    .chart-container {
        min-height: 560px;
        height: 68vh;
    }
}
</style>

<div class="chapter-shell map-shell-root org-shell-root season-order-page">
    <div class="chapter-hero map-hero">
        <h2>Orden de temporadas y arcos</h2>
        <span class="chapter-code"><?= hg_so_h((string)($orders[$selectedOrder]['order_label'] ?? 'Sin recorrido')) ?></span>
    </div>

    <?php if (!$schemaReady): ?>
        <section class="chapter-block map-stage-block org-stage-block">
            <div class="season-order-empty">
                Todavia no existe el bridge de orden de temporadas. Preparalo desde <a href="/talim?s=admin_season_order_schema">el panel admin</a> para activar esta vista.
            </div>
        </section>
    <?php elseif (empty($orders)): ?>
        <section class="chapter-block map-stage-block org-stage-block">
            <div class="season-order-empty">
                Aun no hay recorridos cargados. En cuanto creemos el primero desde administracion, aparecera aqui.
            </div>
        </section>
    <?php else: ?>
        <section class="chapter-block map-stage-block org-stage-block">
            <div class="org-quickbar">
                <div class="org-quick-field">
                    <label for="seasonOrderSelect">Recorrido</label>
                    <select class="org-select" id="seasonOrderSelect" aria-label="Recorrido" <?= empty($orders) ? 'disabled' : '' ?>>
                        <?php foreach ($orders as $order): ?>
                            <option value="<?= hg_so_h((string)$order['order_key']) ?>" <?= $selectedOrder === (string)$order['order_key'] ? 'selected' : '' ?>>
                                <?= hg_so_h((string)$order['order_label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="org-quick-field">
                    <label class="map-sr-only" for="seasonOrderSearch">Buscar</label>
                    <input class="org-search" id="seasonOrderSearch" type="search" placeholder="Buscar bloque, temporada o descripcion" autocomplete="off">
                </div>

                <div class="org-actions" role="toolbar" aria-label="Controles del orden">
                    <button class="org-btn" type="button" id="seasonOrderFit" title="Centrar" aria-label="Centrar">🎯</button>
                    <button class="org-btn" type="button" id="seasonOrderExpand" title="Expandir" aria-label="Expandir">➕</button>
                    <button class="org-btn" type="button" id="seasonOrderCollapse" title="Contraer" aria-label="Contraer">➖</button>
                    <button class="org-btn" type="button" id="seasonOrderFullscreen" title="Pantalla completa" aria-label="Pantalla completa">⛶</button>
                    <button class="org-btn" type="button" id="seasonOrderExport" title="Exportar PNG" aria-label="Exportar PNG">🖼️</button>
                </div>
            </div>

            <div class="org-workspace">
                <div class="chart-container" id="seasonOrderChart"></div>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php if (!empty($renderNodes)): ?>
<script>
(function () {
    const renderNodes = <?= json_encode(array_values($renderNodes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const chartContainer = document.getElementById('seasonOrderChart');
    const orderSelect = document.getElementById('seasonOrderSelect');
    const searchInput = document.getElementById('seasonOrderSearch');
    let resizeTimer = null;
    let selectedData = null;
    let svg = null;
    let scene = null;
    let zoomBehavior = null;
    let nodeById = new Map();
    let layoutState = null;
    const CARD_WIDTH = 278;
    const MAIN_MIN_HEIGHT = 160;
    const SECONDARY_MIN_HEIGHT = 144;
    const SURFACE_PADDING_X = 64;
    const SURFACE_PADDING_Y = 84;
    const MAIN_GAP = 96;
    const BRANCH_GAP = 28;
    const BRANCH_OFFSET = 56;
    const ZOOM_MIN = 0.24;
    const ZOOM_MAX = 5.5;

    function esc(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        });
    }

    function isChartFullscreen() {
        return document.fullscreenElement === chartContainer
            || document.webkitFullscreenElement === chartContainer;
    }

    function normalChartHeight() {
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 760;
        if (window.matchMedia && window.matchMedia('(max-width: 980px)').matches) {
            return Math.max(560, Math.round(viewportHeight * 0.68));
        }
        return Math.max(440, Math.round(viewportHeight * 0.78));
    }

    function chartHeight() {
        if (isChartFullscreen()) {
            return Math.max(440, Math.round(window.innerHeight || document.documentElement.clientHeight || 760));
        }
        return normalChartHeight();
    }

    function syncChartSize(fitAfter, preservedTransform) {
        const height = chartHeight();
        chartContainer.classList.toggle('is-org-fullscreen', isChartFullscreen());
        chartContainer.style.width = '100%';
        chartContainer.style.maxWidth = '100%';
        chartContainer.style.minWidth = '0';
        chartContainer.style.height = height + 'px';
        chartContainer.style.minHeight = height + 'px';
        if (svg) {
            svg.attr('width', chartContainer.clientWidth || chartContainer.offsetWidth || 960);
            svg.attr('height', height);
        }
        if (preservedTransform && svg && zoomBehavior) {
            requestAnimationFrame(function () {
                svg.call(
                    zoomBehavior.transform,
                    d3.zoomIdentity.translate(preservedTransform.x, preservedTransform.y).scale(preservedTransform.k)
                );
            });
            return;
        }
        if (fitAfter) {
            requestAnimationFrame(function () {
                focusLeadNodes(2, false);
            });
        }
    }

    function cardHtml(data, height) {
        const color = /^#[0-9a-f]{6}$/i.test(data.color || '') ? data.color : '#66b7ff';
        const cardStyle = [
            'width:' + CARD_WIDTH + 'px',
            'min-height:' + height + 'px',
            'border:1px solid rgba(0,0,153,.85)',
            'border-top:5px solid ' + color,
            'border-radius:10px',
            'background:linear-gradient(180deg,rgba(0,0,102,.96) 0%,rgba(5,1,78,.96) 100%)',
            'color:#ffffff',
            'box-shadow:0 14px 28px rgba(0,0,0,.28), inset 0 0 0 1px rgba(255,255,255,.03)',
            'overflow:hidden',
            'text-align:left',
            'font-family:Trebuchet MS,Verdana,sans-serif'
        ].join(';');
        const kickerStyle = 'display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;border:1px solid rgba(102,183,255,.28);background:rgba(4,10,26,.72);color:#dff5ff;font-size:11px;font-weight:700;line-height:1.2';
        const titleStyle = 'font-weight:800;font-size:14px;line-height:1.2;color:#ffffff;overflow-wrap:anywhere;text-align:left;margin-top:8px';
        const subtitleStyle = 'color:#7be4ff;font-size:12px;line-height:1.35;text-align:left;margin-top:5px';
        const descStyle = 'color:#d5e7ff;font-size:12px;line-height:1.42;text-align:left;margin-top:8px';
        const metaStyle = 'display:flex;flex-wrap:wrap;gap:6px;margin-top:10px';
        const tagStyle = 'display:inline-flex;align-items:center;min-height:21px;padding:2px 7px;border-radius:999px;border:1px solid rgba(102,183,255,.24);background:rgba(4,10,26,.72);color:#dff5ff;font-size:11px;font-weight:700';
        const bodyStyle = 'padding:12px 12px 12px';
        const titleHtml = data.href
            ? '<a href="' + esc(data.href) + '" target="_blank" rel="noopener noreferrer" style="color:#ffffff;text-decoration:none">' + esc(data.title || '') + '</a>'
            : esc(data.title || '');
        const tags = [];
        if (data.meta) tags.push(data.meta);
        if (data.range_label) tags.push(data.range_label);

        return ''
            + '<div style="' + cardStyle + '">'
            +   '<div style="' + bodyStyle + '">'
            +     '<span style="' + kickerStyle + '">' + esc(data.subtitle || '') + '</span>'
            +     '<div style="' + titleStyle + '">' + titleHtml + '</div>'
            +     (data.description ? '<div style="' + descStyle + '">' + esc(data.description) + '</div>' : '')
            +     (tags.length ? '<div style="' + metaStyle + '">' + tags.map(function (tag) { return '<span style="' + tagStyle + '">' + esc(tag) + '</span>'; }).join('') + '</div>' : '')
            +   '</div>'
            + '</div>';
    }

    function measureCardHeights() {
        const probe = document.createElement('div');
        probe.style.position = 'absolute';
        probe.style.left = '-99999px';
        probe.style.top = '0';
        probe.style.width = CARD_WIDTH + 'px';
        probe.style.visibility = 'hidden';
        probe.style.pointerEvents = 'none';
        probe.style.zIndex = '-1';
        document.body.appendChild(probe);

        const heights = new Map();
        renderNodes.forEach(function (node) {
            const minHeight = node.branch_type === 'secondary' ? SECONDARY_MIN_HEIGHT : MAIN_MIN_HEIGHT;
            probe.innerHTML = cardHtml(node, minHeight);
            const card = probe.firstElementChild;
            const measuredHeight = card ? Math.ceil(card.getBoundingClientRect().height) : minHeight;
            heights.set(node.id, Math.max(minHeight, measuredHeight));
            probe.innerHTML = '';
        });

        document.body.removeChild(probe);
        return heights;
    }

    function selectNode(data) {
        selectedData = data || null;
    }

    function buildLayout() {
        const heightMap = measureCardHeights();
        const orderedNodes = renderNodes.slice().sort(function (a, b) {
            const byPosition = (parseInt(a.position || 0, 10) || 0) - (parseInt(b.position || 0, 10) || 0);
            if (byPosition !== 0) return byPosition;
            return (parseInt(a.id || 0, 10) || 0) - (parseInt(b.id || 0, 10) || 0);
        });
        const explicitNodeById = new Map(renderNodes.map(function (node) {
            return [parseInt(node.id || 0, 10) || 0, node];
        }));
        const mainNodes = [];
        const secondaryByParent = new Map();
        let lastMainId = 0;

        orderedNodes.forEach(function (node) {
            const nodeId = parseInt(node.id || 0, 10) || 0;
            if (node.branch_type !== 'secondary') {
                mainNodes.push(node);
                lastMainId = nodeId;
                return;
            }

            const explicitParentId = parseInt(node.parent_node_id || 0, 10) || 0;
            const explicitParent = explicitNodeById.get(explicitParentId) || null;
            const explicitParentPosition = explicitParent ? (parseInt(explicitParent.position || 0, 10) || 0) : 0;
            const nodePosition = parseInt(node.position || 0, 10) || 0;

            let anchorId = 0;
            if (explicitParent && explicitParent.branch_type !== 'secondary' && explicitParentPosition <= nodePosition) {
                anchorId = explicitParentId;
            } else if (lastMainId > 0) {
                anchorId = lastMainId;
            } else if (mainNodes.length) {
                anchorId = parseInt(mainNodes[0].id || 0, 10) || 0;
            }

            if (anchorId <= 0) return;
            if (!secondaryByParent.has(anchorId)) secondaryByParent.set(anchorId, []);
            secondaryByParent.get(anchorId).push(Object.assign({}, node, { render_parent_id: anchorId }));
        });

        secondaryByParent.forEach(function (items) {
            items.sort(function (a, b) {
                const byPosition = (parseInt(a.position || 0, 10) || 0) - (parseInt(b.position || 0, 10) || 0);
                if (byPosition !== 0) return byPosition;
                return (parseInt(a.id || 0, 10) || 0) - (parseInt(b.id || 0, 10) || 0);
            });
        });

        const positions = new Map();
        let mainRowHeight = MAIN_MIN_HEIGHT;
        mainNodes.forEach(function (node) {
            const measuredHeight = heightMap.get(node.id) || MAIN_MIN_HEIGHT;
            if (measuredHeight > mainRowHeight) {
                mainRowHeight = measuredHeight;
            }
        });

        let maxSecondaryDepth = 0;
        mainNodes.forEach(function (node, index) {
            const x = SURFACE_PADDING_X + index * (CARD_WIDTH + MAIN_GAP);
            const nodeHeight = heightMap.get(node.id) || MAIN_MIN_HEIGHT;
            const y = SURFACE_PADDING_Y + Math.max(0, (mainRowHeight - nodeHeight) / 2);
            positions.set(node.id, { x: x, y: y, width: CARD_WIDTH, height: nodeHeight, node: node });
            const children = secondaryByParent.get(node.id) || [];
            let currentChildY = SURFACE_PADDING_Y + mainRowHeight + BRANCH_OFFSET;
            children.forEach(function (child, childIndex) {
                const childHeight = heightMap.get(child.id) || SECONDARY_MIN_HEIGHT;
                const childY = currentChildY;
                positions.set(child.id, { x: x, y: childY, width: CARD_WIDTH, height: childHeight, node: child });
                maxSecondaryDepth = Math.max(maxSecondaryDepth, childY + childHeight);
                currentChildY += childHeight + BRANCH_GAP;
            });
        });

        const contentWidth = mainNodes.length
            ? (SURFACE_PADDING_X * 2) + (mainNodes.length * CARD_WIDTH) + ((mainNodes.length - 1) * MAIN_GAP)
            : 960;
        const contentHeight = Math.max(
            SURFACE_PADDING_Y + mainRowHeight + SURFACE_PADDING_Y,
            maxSecondaryDepth > 0 ? maxSecondaryDepth + SURFACE_PADDING_Y : SURFACE_PADDING_Y + mainRowHeight + SURFACE_PADDING_Y
        );

        return {
            mainNodes: mainNodes,
            secondaryByParent: secondaryByParent,
            positions: positions,
            mainRowHeight: mainRowHeight,
            contentWidth: contentWidth,
            contentHeight: contentHeight
        };
    }

    function renderScene() {
        layoutState = buildLayout();
        nodeById = new Map(renderNodes.map(function (node) { return [parseInt(node.id, 10), node]; }));

        chartContainer.innerHTML = '';
        svg = d3.select(chartContainer)
            .append('svg')
            .attr('width', chartContainer.clientWidth || chartContainer.offsetWidth || 960)
            .attr('height', chartHeight())
            .attr('viewBox', '0 0 ' + Math.max(960, layoutState.contentWidth) + ' ' + Math.max(chartHeight(), layoutState.contentHeight));

        scene = svg.append('g').attr('class', 'season-order-scene');

        zoomBehavior = d3.zoom()
            .scaleExtent([ZOOM_MIN, ZOOM_MAX])
            .on('zoom', function (event) {
                scene.attr('transform', event.transform);
            });

        svg.call(zoomBehavior).on('dblclick.zoom', null);

        scene.append('rect')
            .attr('x', 0)
            .attr('y', 0)
            .attr('width', layoutState.contentWidth)
            .attr('height', layoutState.contentHeight)
            .attr('fill', 'transparent');

        const lineGroup = scene.append('g').attr('class', 'season-order-lines');
        const nodeGroup = scene.append('g').attr('class', 'season-order-nodes');

        layoutState.mainNodes.forEach(function (node, index) {
            const current = layoutState.positions.get(node.id);
            if (!current) return;
            const mainConnectorY = SURFACE_PADDING_Y + (layoutState.mainRowHeight / 2);

            if (index < layoutState.mainNodes.length - 1) {
                const next = layoutState.positions.get(layoutState.mainNodes[index + 1].id);
                if (next) {
                    lineGroup.append('line')
                        .attr('x1', current.x + current.width)
                        .attr('y1', mainConnectorY)
                        .attr('x2', next.x)
                        .attr('y2', mainConnectorY)
                        .attr('stroke', 'rgba(130, 182, 255, 0.72)')
                        .attr('stroke-width', 3)
                        .attr('stroke-linecap', 'round');
                }
            }

            const secondaryNodes = layoutState.secondaryByParent.get(node.id) || [];
            if (secondaryNodes.length) {
                secondaryNodes.forEach(function (child, childIndex) {
                    const childPos = layoutState.positions.get(child.id);
                    if (!childPos) return;
                    const previousPos = childIndex === 0
                        ? current
                        : layoutState.positions.get(secondaryNodes[childIndex - 1].id);
                    if (!previousPos) return;

                    lineGroup.append('line')
                        .attr('x1', current.x + (current.width / 2))
                        .attr('y1', childIndex === 0 ? previousPos.y + previousPos.height : previousPos.y + previousPos.height)
                        .attr('x2', current.x + (current.width / 2))
                        .attr('y2', childPos.y)
                        .attr('stroke', 'rgba(67, 214, 188, 0.72)')
                        .attr('stroke-width', 3)
                        .attr('stroke-linecap', 'round');
                });
            }
        });

        renderNodes.forEach(function (node) {
            const pos = layoutState.positions.get(node.id);
            if (!pos) return;
            const fo = nodeGroup.append('foreignObject')
                .attr('x', pos.x)
                .attr('y', pos.y)
                .attr('width', pos.width)
                .attr('height', pos.height);

            fo.append('xhtml:div')
                .style('width', pos.width + 'px')
                .style('height', pos.height + 'px')
                .style('overflow', 'visible')
                .html(cardHtml(node, pos.height));

            fo.on('click', function () {
                selectNode(node);
            });
        });

        focusLeadNodes(2, false);
    }

    function fitToView(animate) {
        if (!svg || !scene || !layoutState) return;
        const width = chartContainer.clientWidth || chartContainer.offsetWidth || 960;
        const height = chartHeight();
        const scale = Math.max(
            ZOOM_MIN,
            Math.min(
                ZOOM_MAX,
                Math.min(
                    (width - 40) / Math.max(1, layoutState.contentWidth),
                    (height - 40) / Math.max(1, layoutState.contentHeight)
                )
            )
        );
        const translateX = Math.max(20, (width - (layoutState.contentWidth * scale)) / 2);
        const translateY = Math.max(20, (height - (layoutState.contentHeight * scale)) / 2);
        const transform = d3.zoomIdentity.translate(translateX, translateY).scale(scale);
        svg.transition().duration(animate === false ? 0 : 220).call(zoomBehavior.transform, transform);
    }

    function focusLeadNodes(count, animate) {
        if (!svg || !scene || !layoutState) return;
        const mainNodes = layoutState.mainNodes.slice(0, Math.max(1, count || 1));
        if (!mainNodes.length) {
            fitToView(animate);
            return;
        }

        const includedPositions = [];
        mainNodes.forEach(function (node) {
            const mainPos = layoutState.positions.get(node.id);
            if (mainPos) includedPositions.push(mainPos);
            const secondaryNodes = layoutState.secondaryByParent.get(node.id) || [];
            secondaryNodes.forEach(function (child) {
                const childPos = layoutState.positions.get(child.id);
                if (childPos) includedPositions.push(childPos);
            });
        });

        if (!includedPositions.length) {
            fitToView(animate);
            return;
        }

        let minX = Infinity;
        let minY = Infinity;
        let maxX = -Infinity;
        let maxY = -Infinity;
        includedPositions.forEach(function (pos) {
            minX = Math.min(minX, pos.x);
            minY = Math.min(minY, pos.y);
            maxX = Math.max(maxX, pos.x + pos.width);
            maxY = Math.max(maxY, pos.y + pos.height);
        });

        const boundsWidth = Math.max(1, maxX - minX);
        const boundsHeight = Math.max(1, maxY - minY);
        const viewportWidth = chartContainer.clientWidth || chartContainer.offsetWidth || 960;
        const viewportHeight = chartHeight();
        const paddingX = 44;
        const paddingY = 34;
        const baseScale = Math.max(
            ZOOM_MIN,
            Math.min(
                ZOOM_MAX,
                Math.min(
                    (viewportWidth - (paddingX * 2)) / boundsWidth,
                    (viewportHeight - (paddingY * 2)) / boundsHeight
                )
            )
        );
        const scale = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, baseScale * 8));

        const leftBias = 0.28;
        const targetCenterX = minX + (boundsWidth * leftBias);
        const targetCenterY = minY + (boundsHeight / 2);
        const translateX = (viewportWidth * 2) - (targetCenterX * scale);
        const translateY = (viewportHeight * 2.95) - (targetCenterY * scale);
        const transform = d3.zoomIdentity.translate(translateX, translateY).scale(scale);
        svg.transition().duration(animate === false ? 0 : 220).call(zoomBehavior.transform, transform);
    }

    function zoomBy(factor) {
        if (!svg || !zoomBehavior) return;
        svg.transition().duration(180).call(zoomBehavior.scaleBy, factor);
    }

    function currentTransformSnapshot() {
        if (!svg || !zoomBehavior) return null;
        const current = d3.zoomTransform(svg.node());
        return {
            x: current.x,
            y: current.y,
            k: current.k
        };
    }

    function centerOnNode(node) {
        if (!svg || !zoomBehavior || !layoutState || !node) return;
        const pos = layoutState.positions.get(parseInt(node.id, 10));
        if (!pos) return;
        const width = chartContainer.clientWidth || chartContainer.offsetWidth || 960;
        const height = chartHeight();
        const current = d3.zoomTransform(svg.node());
        const targetX = (width / 2) - ((pos.x + (pos.width / 2)) * current.k);
        const targetY = (height / 2) - ((pos.y + (pos.height / 2)) * current.k);
        const transform = d3.zoomIdentity.translate(targetX, targetY).scale(current.k);
        svg.transition().duration(220).call(zoomBehavior.transform, transform);
    }

    function searchNode(query) {
        const q = String(query || '').trim().toLowerCase();
        if (!q) return;
        const found = renderNodes.find(function (item) {
            return [
                item.title,
                item.subtitle,
                item.description,
                item.meta,
                item.range_label
            ].join(' ').toLowerCase().indexOf(q) !== -1;
        });
        if (!found) return;
        selectNode(found);
        centerOnNode(found);
    }

    syncChartSize(false);
    renderScene();

    chartContainer.addEventListener('click', function (event) {
        const titleLink = event.target.closest('a[href]');
        if (!titleLink) return;
        event.stopPropagation();
    });

    chartContainer.addEventListener('dblclick', function () {
        if (selectedData && selectedData.href) {
            window.open(selectedData.href, '_blank', 'noopener');
        }
    });

    document.getElementById('seasonOrderFit').addEventListener('click', function () {
        focusLeadNodes(2);
    });

    document.getElementById('seasonOrderExpand').addEventListener('click', function () {
        zoomBy(1.28);
    });

    document.getElementById('seasonOrderCollapse').addEventListener('click', function () {
        zoomBy(0.78);
    });

    document.getElementById('seasonOrderFullscreen').addEventListener('click', function () {
        if (document.fullscreenElement === chartContainer) {
            document.exitFullscreen();
            return;
        }
        if (document.webkitFullscreenElement === chartContainer) {
            document.webkitExitFullscreen();
            return;
        }
        if (chartContainer.requestFullscreen) {
            chartContainer.requestFullscreen();
        } else if (chartContainer.webkitRequestFullscreen) {
            chartContainer.webkitRequestFullscreen();
        }
    });

    function handleFullscreenChange() {
        const fullscreenActive = isChartFullscreen();
        const preservedTransform = currentTransformSnapshot();
        setTimeout(function () {
            syncChartSize(false, preservedTransform);
        }, fullscreenActive ? 120 : 220);
        if (!fullscreenActive) {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    syncChartSize(false, preservedTransform);
                });
            });
            setTimeout(function () {
                syncChartSize(false, preservedTransform);
            }, 520);
        }
    }

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);

    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            syncChartSize(false, currentTransformSnapshot());
        }, 160);
    });

    document.getElementById('seasonOrderExport').addEventListener('click', function () {
        if (!svg) return;
        const serializer = new XMLSerializer();
        const clone = svg.node().cloneNode(true);
        clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        clone.setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        const source = serializer.serializeToString(clone);
        const blob = new Blob([source], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const image = new Image();
        image.onload = function () {
            const canvas = document.createElement('canvas');
            canvas.width = clone.viewBox.baseVal && clone.viewBox.baseVal.width ? clone.viewBox.baseVal.width : (chartContainer.clientWidth || 1280);
            canvas.height = clone.viewBox.baseVal && clone.viewBox.baseVal.height ? clone.viewBox.baseVal.height : chartHeight();
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#040a19';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(image, 0, 0);
            URL.revokeObjectURL(url);
            const link = document.createElement('a');
            link.href = canvas.toDataURL('image/png');
            link.download = 'season-order.png';
            link.click();
        };
        image.src = url;
    });

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            searchNode(this.value);
        });
    }

    if (orderSelect) {
        orderSelect.addEventListener('change', function () {
            const order = String(this.value || '').trim();
            if (!order) return;
            window.location.href = '/seasons/order?order=' + encodeURIComponent(order);
        });
    }
})();
</script>
<?php endif; ?>
