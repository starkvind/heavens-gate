<?php
include("app/partials/main_nav_bar.php");
?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-chapters.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-chapters.css"><?php } ?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-maps.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-maps.css"><?php } ?>
<script type="text/javascript" src="/assets/vendor/d3/d3.v7.min.js"></script>
<script type="text/javascript" src="/assets/vendor/d3-flextree/d3-flextree.2.1.2.js"></script>
<script type="text/javascript" src="/assets/vendor/d3-org-chart/d3-org-chart.3.1.1.js"></script>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/pages/legacy/controllers-bio-bio_org_chart.css'); } else { ?><link rel="stylesheet" href="/assets/css/pages/legacy/controllers-bio-bio_org_chart.css"><?php } ?>

<div class="chapter-shell map-shell-root org-shell-root">
    <div class="chapter-hero map-hero">
        <h2>Organigrama</h2>
        <span class="chapter-code"><?= hg_bio_org_chart_h($orgName) ?></span>
    </div>

    <section class="chapter-block map-stage-block org-stage-block">
        <div class="org-quickbar">
            <div class="org-quick-field">
                <label for="orgSelector">Organizacion</label>
                <select class="org-select" id="orgSelector" aria-label="Organizacion">
                    <?php foreach ($organizationOptions as $option): ?>
                        <?php
                        $optionId = (int)$option['id'];
                        $optionSlug = trim((string)($option['pretty_id'] ?? ''));
                        $optionValue = $optionSlug !== '' ? $optionSlug : (string)$optionId;
                        $optionLabel = (string)($option['name'] ?? '');
                        ?>
                        <option value="<?= hg_bio_org_chart_h($optionValue) ?>" <?= $optionId === $orgId ? 'selected' : '' ?>><?= hg_bio_org_chart_h($optionLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="org-quick-field">
                <label class="map-sr-only" for="orgSearch">Buscar</label>
                <input class="org-search" id="orgSearch" type="search" placeholder="Buscar cargo, departamento o personaje" autocomplete="off">
            </div>
            <div class="org-actions" role="toolbar" aria-label="Controles del organigrama">
                <button class="org-btn" type="button" id="orgFit" title="Centrar" aria-label="Centrar">🎯</button>
                <button class="org-btn" type="button" id="orgExpand" title="Expandir" aria-label="Expandir">➕</button>
                <button class="org-btn" type="button" id="orgCollapse" title="Contraer" aria-label="Contraer">➖</button>
                <button class="org-btn" type="button" id="orgFullscreen" title="Pantalla completa" aria-label="Pantalla completa">⛶</button>
                <button class="org-btn" type="button" id="orgExport" title="Exportar PNG" aria-label="Exportar PNG">🖼️</button>
            </div>
        </div>
        <div class="org-workspace"><div class="chart-container"></div></div>
    </section>
</div>

<script>
(function () {
    const orgData = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const chartContainer = document.querySelector('.chart-container');
    let chart = null;
    let selectedData = null;
    let resizeTimer = null;
    let preFullscreenHeight = 0;
    let preFullscreenTransform = null;
    const nodeIds = new Set(orgData.map(item => item.id));
    const rootNode = orgData.find(item => !item.parentId) || orgData[0];
    orgData.forEach(item => {
        if (item.parentId && !nodeIds.has(item.parentId)) item.parentId = rootNode ? rootNode.id : '';
    });

    function esc(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        });
    }
    function isChartFullscreen() {
        return document.fullscreenElement === chartContainer || document.webkitFullscreenElement === chartContainer;
    }
    function normalChartHeight() {
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 760;
        if (window.matchMedia && window.matchMedia('(max-width: 980px)').matches) return Math.max(560, Math.round(viewportHeight * 0.68));
        return Math.max(420, Math.round(viewportHeight * 0.78));
    }
    function chartHeight() {
        if (isChartFullscreen()) return Math.max(420, Math.round(window.innerHeight || document.documentElement.clientHeight || 760));
        return normalChartHeight();
    }
    function syncChartSize(fitAfter, preservedTransform, forcedHeight) {
        const fullscreenActive = isChartFullscreen();
        const restoreHeight = Number(forcedHeight);
        const height = (!fullscreenActive && Number.isFinite(restoreHeight) && restoreHeight > 0)
            ? Math.round(restoreHeight)
            : chartHeight();
        chartContainer.classList.toggle('is-org-fullscreen', fullscreenActive);
        chartContainer.style.width = '100%';
        chartContainer.style.maxWidth = '100%';
        chartContainer.style.minWidth = '0';
        chartContainer.style.height = height + 'px';
        chartContainer.style.minHeight = height + 'px';
        if (!chart) return;
        chart.svgHeight(height).render();
        if (preservedTransform) {
            requestAnimationFrame(function () {
                const state = chart.getChartState();
                if (!state || !state.svg || !state.zoomBehavior) return;
                state.svg.call(state.zoomBehavior.transform, d3.zoomIdentity.translate(preservedTransform.x, preservedTransform.y).scale(preservedTransform.k));
            });
            return;
        }
        if (fitAfter) requestAnimationFrame(function () { chart.fit({ animate: false }); });
    }
    function currentTransformSnapshot() {
        if (!chart) return null;
        const state = chart.getChartState();
        if (!state || !state.lastTransform) return null;
        return { x: state.lastTransform.x, y: state.lastTransform.y, k: state.lastTransform.k };
    }
    function restorePreFullscreenSize() {
        const height = preFullscreenHeight > 0 ? preFullscreenHeight : normalChartHeight();
        const transform = preFullscreenTransform || currentTransformSnapshot();
        chartContainer.classList.remove('is-org-fullscreen');
        syncChartSize(false, transform, height);
    }
    function cardHtml(d) {
        const data = d.data;
        const color = /^#[0-9a-f]{6}$/i.test(data.color || '') ? data.color : '#94a3b8';
        const cardStyle = ['width:278px','min-height:132px','border:1px solid rgba(0,0,153,.85)',`border-top:5px solid ${color}`,'border-radius:10px','background:linear-gradient(180deg,rgba(0,0,102,.96) 0%,rgba(5,1,78,.96) 100%)','color:#ffffff','box-shadow:0 14px 28px rgba(0,0,0,.28),inset 0 0 0 1px rgba(255,255,255,.03)','overflow:hidden','text-align:left','font-family:Trebuchet MS,Verdana,sans-serif'].join(';');
        const departmentCardStyle = ['width:278px','min-height:58px','border:1px solid rgba(0,0,153,.85)',`border-top:5px solid ${color}`,'border-radius:10px','background:rgba(0,0,85,.92)','color:#ffffff','box-shadow:0 14px 28px rgba(0,0,0,.24),inset 0 0 0 1px rgba(255,255,255,.03)','overflow:hidden','text-align:left','font-family:Trebuchet MS,Verdana,sans-serif'].join(';');
        const headStyle = 'display:grid;grid-template-columns:54px minmax(0,1fr);gap:10px;padding:12px 12px 8px;align-items:center;text-align:left';
        const deptHeadStyle = 'display:block;padding:14px 12px 12px;text-align:left';
        const titleStyle = 'font-weight:800;font-size:14px;line-height:1.2;color:#ffffff;overflow-wrap:anywhere;text-align:left';
        const nameStyle = 'display:inline-block;margin-top:4px;font-weight:700;font-size:13px;color:#33CCCC;overflow-wrap:anywhere;text-decoration:none;text-align:left';
        const subStyle = 'padding:0 12px 10px;color:#b9d6ff;font-size:12px;line-height:1.35;text-align:left';
        const tagsStyle = 'display:flex;flex-wrap:wrap;gap:5px;padding:0 12px 12px;text-align:left';
        const tagStyle = 'display:inline-flex;align-items:center;min-height:21px;padding:2px 7px;border-radius:999px;border:1px solid rgba(51,204,204,.25);background:rgba(4,10,26,.72);color:#dff5ff;font-size:11px;font-weight:700;text-align:left';
        const avatarStyle = 'width:54px;height:54px;border-radius:50%;object-fit:cover;background:#000033;box-shadow:0 0 0 1px #000099,0 0 0 4px rgba(51,204,204,.1)';
        const tags = [data.department ? esc(data.department) : '', data.scope ? esc(data.scope) : '', data.level !== undefined ? 'Nivel ' + esc(data.level) : ''].filter(Boolean);
        if (data.kind === 'department') return `<div class="org-card is-department" style="${departmentCardStyle}"><div class="org-card-head" style="${deptHeadStyle}"><div class="org-card-title" style="${titleStyle}">${esc(data.title)}</div></div></div>`;
        const image = data.image ? `<img class="org-card-avatar" style="${avatarStyle}" src="${esc(data.image)}" alt="">` : `<div class="org-card-avatar" style="${avatarStyle}"></div>`;
        const nameHtml = data.href ? `<a class="org-card-name" style="${nameStyle}" href="${esc(data.href)}" target="_blank" rel="noopener noreferrer" title="Abrir ficha en nueva ventana">${esc(data.name)}</a>` : `<span class="org-card-name" style="${nameStyle}">${esc(data.name)}</span>`;
        return `<div class="org-card" style="${cardStyle}"><div class="org-card-head" style="${headStyle}">${image}<div><div class="org-card-title" style="${titleStyle}">${esc(data.title)}</div>${nameHtml}</div></div><div class="org-card-sub" style="${subStyle}">${esc(data.meta || data.note || '')}</div><div class="org-card-tags" style="${tagsStyle}">${tags.map(t => `<span class="org-tag" style="${tagStyle}">${t}</span>`).join('')}</div></div>`;
    }
    function selectNode(data) { selectedData = data; }
    function searchNode(query) {
        const q = String(query || '').trim().toLowerCase();
        if (!q || !chart) return;
        const found = orgData.find(item => [item.title,item.name,item.department,item.directDepartment,item.scope,item.note,item.meta].join(' ').toLowerCase().includes(q));
        if (!found) return;
        chart.setCentered(found.id).render(); selectNode(found);
    }
    function visibleChildCount(node) {
        return (node.children || node._children || []).reduce(function (count, child) {
            if (child.data && child.data.kind === 'department') return count + visibleChildCount(child);
            return count + 1;
        }, 0);
    }

    syncChartSize(false);
    chart = new d3.OrgChart()
        .container('.chart-container').data(orgData).svgHeight(chartHeight())
        .nodeWidth(() => 278).nodeHeight(d => d.data.kind === 'department' ? 74 : 148)
        .childrenMargin(d => d.data.kind === 'department' ? 52 : 72).siblingsMargin(() => 22)
        .compactMarginPair(() => 48).compactMarginBetween(() => 18)
        .nodeButtonWidth(() => 32).nodeButtonHeight(() => 32).nodeButtonX(() => -16).nodeButtonY(() => 8)
        .layout('top').compact(false).nodeContent(cardHtml)
        .buttonContent(({ node }) => { const count = visibleChildCount(node) || node.data._directSubordinates || 0; return `<div style="width:28px;height:28px;border-radius:999px;background:rgba(4,10,26,.96);border:1px solid rgba(51,204,204,.42);display:flex;align-items:center;justify-content:center;color:#dff5ff;font-weight:800;box-shadow:0 0 0 3px rgba(51,204,204,.08)">${count}</div>`; })
        .onNodeClick(function (node) { selectNode(node.data); }).render();
    chart.fit();

    chartContainer.addEventListener('click', function (event) { const characterLink = event.target.closest('.org-card-name[href]'); if (characterLink) event.stopPropagation(); });
    chartContainer.addEventListener('dblclick', function () { if (selectedData && selectedData.href) window.open(selectedData.href, '_blank', 'noopener'); });
    document.getElementById('orgFit').addEventListener('click', function () { chart.fit(); });
    document.getElementById('orgExpand').addEventListener('click', function () { chart.expandAll(); });
    document.getElementById('orgCollapse').addEventListener('click', function () { orgData.forEach(function (item) { item._expanded = item.kind === 'department' || !item.parentId; }); chart.initialExpandLevel(1).render(); chart.fit(); });
    document.getElementById('orgFullscreen').addEventListener('click', function () {
        if (document.fullscreenElement === chartContainer) { document.exitFullscreen(); return; }
        if (document.webkitFullscreenElement === chartContainer) { document.webkitExitFullscreen(); return; }
        preFullscreenHeight = chartContainer.clientHeight || normalChartHeight();
        preFullscreenTransform = currentTransformSnapshot();
        if (chartContainer.requestFullscreen) chartContainer.requestFullscreen(); else if (chartContainer.webkitRequestFullscreen) chartContainer.webkitRequestFullscreen();
    });
    function handleFullscreenChange() {
        if (isChartFullscreen()) {
            syncChartSize(false, preFullscreenTransform || currentTransformSnapshot());
            return;
        }
        restorePreFullscreenSize();
        requestAnimationFrame(function () { restorePreFullscreenSize(); });
        setTimeout(function () { restorePreFullscreenSize(); }, 180);
        setTimeout(function () {
            restorePreFullscreenSize();
            preFullscreenHeight = 0;
            preFullscreenTransform = null;
        }, 520);
    }
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (!isChartFullscreen() && preFullscreenHeight > 0) {
                restorePreFullscreenSize();
                return;
            }
            syncChartSize(false, currentTransformSnapshot());
        }, 160);
    });
    document.getElementById('orgExport').addEventListener('click', function () { chart.exportImg({ full: true, scale: 3, save: true, backgroundColor: '#040a19' }); });
    document.getElementById('orgSearch').addEventListener('input', function () { searchNode(this.value); });
    document.getElementById('orgSelector').addEventListener('change', function () { const org = String(this.value || '').trim(); if (org) window.location.href = '/organizations/' + encodeURIComponent(org) + '/org-chart'; });
})();
</script>
