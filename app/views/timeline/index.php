<?php
if (!defined('HG_MOBILE_TIMELINE_EMBED') || !HG_MOBILE_TIMELINE_EMBED) { include("app/partials/main_nav_bar.php"); }
?>
<script src="/assets/vendor/echarts/echarts.min.5.5.1.js"></script>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-events.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-events.css"><?php } ?>
<?php if (defined('HG_MOBILE_TIMELINE_EMBED') && HG_MOBILE_TIMELINE_EMBED): ?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-mobile-timeline.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-mobile-timeline.css"><?php } ?>
<?php endif; ?>
<?php include_once("app/partials/datatable_assets.php"); ?>

<div class="events-wrap events-wrap-compact">
    <section class="events-headline">
        <h1 class="events-page-title">Línea temporal de Heaven's Gate</h1>
        <div style="margin-bottom:0.5em;display:block;">&nbsp;</div>
        <div class="events-meta-row">
            <span class="events-meta-pill">Periodo: <?= hg_events_h($rangeStart !== '' ? $rangeStart : '-') ?> - <?= hg_events_h($rangeEnd !== '' ? $rangeEnd : '-') ?></span>
            <span class="events-meta-pill" id="eventsVisibleChip">Mostrando: <?= (int)$totalEvents ?></span>
            <span class="events-meta-pill">Tipos: <?= (int)count($typeStats) ?></span>
            <span class="events-meta-pill">Crónicas: <?= (int)count($chronicleFilterMap) ?></span>
            <span class="events-meta-pill">Realidades: <?= (int)count($realityFilterMap) ?></span>
        </div>
    </section>

    <section class="events-toolbar events-toolbar-compact">
        <div class="events-filters events-filters-compact">
            <label class="events-filter-block is-search">
                <span class="events-filter-label">Búsqueda</span>
                <input class="events-filter-input" type="text" id="evSearch" placeholder="Título, descripción, fuente...">
            </label>
            <div class="events-filter-block">
                <span class="events-filter-label">Tipo</span>
                <div class="events-ms-wrap" id="filter-type">
                    <button class="events-ms-btn events-filter-select" type="button" id="ms-toggle-type" aria-haspopup="true" aria-expanded="false">
                        <span class="events-ms-label">Tipo</span>
                        <span class="events-ms-summary" id="ms-summary-type">Todos</span>
                    </button>
                    <div class="events-ms-panel" id="ms-panel-type" aria-hidden="true">
                        <?php foreach ($typeStats as $typeData): ?>
                        <label class="events-ms-row">
                            <input type="checkbox" class="events-ms-check events-ms-check-type" value="<?= hg_events_h($typeData['slug']) ?>" checked>
                            <span><?= hg_events_h($typeData['name']) ?> (<?= (int)$typeData['count'] ?>)</span>
                        </label>
                        <?php endforeach; ?>
                        <div class="events-ms-actions">
                            <button type="button" id="ms-select-all-type">Todo</button>
                            <button type="button" id="ms-clear-type">Limpiar</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="events-filter-block">
                <span class="events-filter-label">Crónica</span>
                <div class="events-ms-wrap" id="filter-chronicle">
                    <button class="events-ms-btn events-filter-select" type="button" id="ms-toggle-chronicle" aria-haspopup="true" aria-expanded="false">
                        <span class="events-ms-label">Crónica</span>
                        <span class="events-ms-summary" id="ms-summary-chronicle">Todas</span>
                    </button>
                    <div class="events-ms-panel" id="ms-panel-chronicle" aria-hidden="true">
                        <?php foreach ($chronicleFilterMap as $cid => $cname): ?>
                        <label class="events-ms-row">
                            <input type="checkbox" class="events-ms-check events-ms-check-chronicle" value="<?= (int)$cid ?>" checked>
                            <span><?= hg_events_h($cname) ?></span>
                        </label>
                        <?php endforeach; ?>
                        <div class="events-ms-actions">
                            <button type="button" id="ms-select-all-chronicle">Todo</button>
                            <button type="button" id="ms-clear-chronicle">Limpiar</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="events-filter-block<?= $showRealityFilter ? '' : ' is-future-hidden' ?>">
                <span class="events-filter-label">Realidad</span>
                <div class="events-ms-wrap" id="filter-reality"<?= $showRealityFilter ? '' : ' aria-hidden="true"' ?>>
                    <button class="events-ms-btn events-filter-select" type="button" id="ms-toggle-reality" aria-haspopup="true" aria-expanded="false" <?= $showRealityFilter ? '' : 'disabled' ?>>
                        <span class="events-ms-label">Realidad</span>
                        <span class="events-ms-summary" id="ms-summary-reality">Todas</span>
                    </button>
                    <div class="events-ms-panel" id="ms-panel-reality" aria-hidden="true">
                        <?php foreach ($realityFilterMap as $rid => $rname): ?>
                        <label class="events-ms-row">
                            <input type="checkbox" class="events-ms-check events-ms-check-reality" value="<?= (int)$rid ?>" checked>
                            <span><?= hg_events_h($rname) ?></span>
                        </label>
                        <?php endforeach; ?>
                        <div class="events-ms-actions">
                            <button type="button" id="ms-select-all-reality">Todo</button>
                            <button type="button" id="ms-clear-reality">Limpiar</button>
                        </div>
                    </div>
                </div>
            </div>
            <label class="events-filter-block">
                <span class="events-filter-label">Desde</span>
                <input class="events-filter-input" type="date" id="evDateFrom" value="">
            </label>
            <label class="events-filter-block">
                <span class="events-filter-label">Hasta</span>
                <input class="events-filter-input" type="date" id="evDateTo" value="">
            </label>
        </div>
        <div class="events-toolbar-actions events-toolbar-actions-compact">
            <button class="events-mini-btn" type="button" id="evReset">Limpiar filtros</button>
            <div class="events-view-toggle">
                <button class="events-view-toggle-btn is-active" type="button" data-view="timeline">Línea temporal</button>
                <button class="events-view-toggle-btn" type="button" data-view="list">Listado</button>
            </div>
        </div>
    </section>

    <section class="events-view is-active" data-view-panel="timeline">
        <article class="events-card events-card-compact">
            <header class="events-card-head">
                <h3 class="events-card-title">Línea temporal</h3>
                <div class="events-timeline-controls">
                    <button class="events-mini-btn" type="button" id="evZoomIn">Zoom +</button>
                    <button class="events-mini-btn" type="button" id="evZoomOut">Zoom -</button>
                    <button class="events-mini-btn" type="button" id="evFit">Ajustar</button>
                    <button class="events-mini-btn" type="button" id="evFocusMain">Centrar en Heaven's Gate</button>
                    <button class="events-mini-btn" type="button" id="evFullscreen">Pantalla completa</button>
                </div>
            </header>
            <div id="eventsTimelineContainer"><div id="eventsChart"></div></div>
            <div class="events-chart-note" id="evChartNotice"></div>
        </article>
    </section>

    <section class="events-view" data-view-panel="list">
        <div class="events-list-wrap">
            <div class="events-list-top">
                <strong>Listado de eventos filtrados</strong>
                <span class="events-list-count" id="evListCount">0 filas</span>
            </div>
            <div class="events-table-container">
                <table class="events-table display" id="eventsTable">
                    <thead><tr><th>Fecha</th><th>Evento</th><th>Tipo</th><th>Crónica</th><th>Fuente</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="events-empty" id="evNoRows" style="display:none;">No hay eventos que cumplan los filtros actuales.</div>
        </div>
    </section>
</div>

<script>
(function(){
    const allEvents = <?= json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const eventMap = new Map(allEvents.map(e => [Number(e.id), e]));
    const allowRealityFilter = <?= $showRealityFilter ? 'true' : 'false' ?>;
    const chartContainer = document.getElementById('eventsChart');
    const timelineWrap = document.getElementById('eventsTimelineContainer');
    const chartNotice = document.getElementById('evChartNotice');
    const visibleChip = document.getElementById('eventsVisibleChip');
    const listCount = document.getElementById('evListCount');
    const noRows = document.getElementById('evNoRows');
    const inputSearch = document.getElementById('evSearch');
    const typeChecks = Array.from(document.querySelectorAll('.events-ms-check-type'));
    const chronicleChecks = Array.from(document.querySelectorAll('.events-ms-check-chronicle'));
    const realityChecks = Array.from(document.querySelectorAll('.events-ms-check-reality'));
    const inputDateFrom = document.getElementById('evDateFrom');
    const inputDateTo = document.getElementById('evDateTo');
    const tableBody = document.querySelector('#eventsTable tbody');
    let chart = null;
    let chartIndexById = new Map();
    let filteredEvents = allEvents.slice();
    let selectedId = 0;
    let currentRowsMinTs = 0;
    let currentRowsMaxTs = 0;
    const globalMinTs = Date.parse('<?= $timelineStart ?>T00:00:00');
    const globalMaxTs = Date.parse('<?= $timelineEnd ?>T00:00:00');
    let listDt = null;

    function escapeHtml(value) {
        return String(value || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;');
    }

    const multiFilterConfigs = [
        { key: 'type', allLabel: 'Todos', checks: typeChecks, hidden: false },
        { key: 'chronicle', allLabel: 'Todas', checks: chronicleChecks, hidden: false },
        { key: 'reality', allLabel: 'Todas', checks: realityChecks, hidden: !allowRealityFilter }
    ];

    function openMsPanel(key) {
        const panel = document.getElementById('ms-panel-' + key);
        const toggle = document.getElementById('ms-toggle-' + key);
        if (!panel || !toggle || toggle.disabled) return;
        panel.style.display = 'block'; panel.setAttribute('aria-hidden', 'false'); toggle.setAttribute('aria-expanded', 'true');
    }
    function closeMsPanel(key) {
        const panel = document.getElementById('ms-panel-' + key);
        const toggle = document.getElementById('ms-toggle-' + key);
        if (!panel || !toggle) return;
        panel.style.display = 'none'; panel.setAttribute('aria-hidden', 'true'); toggle.setAttribute('aria-expanded', 'false');
    }
    function toggleMsPanel(key) {
        const panel = document.getElementById('ms-panel-' + key);
        if (!panel) return;
        if (panel.style.display === 'block') closeMsPanel(key); else openMsPanel(key);
    }
    function getSelectedValues(checks) {
        const selected = checks.filter(node => node.checked).map(node => String(node.value));
        return selected.length ? selected : null;
    }
    function updateMsSummary(cfg) {
        if (cfg.hidden) return;
        const summary = document.getElementById('ms-summary-' + cfg.key);
        if (!summary) return;
        const selected = getSelectedValues(cfg.checks);
        if (selected === null) { summary.textContent = cfg.allLabel; return; }
        if (selected.length === 1) {
            const firstLabel = cfg.checks.find(node => node.checked);
            let txt = selected[0];
            if (firstLabel) {
                const rowLabel = firstLabel.closest('label');
                const span = rowLabel ? rowLabel.querySelector('span') : null;
                if (span && span.textContent) txt = String(span.textContent);
            }
            summary.textContent = txt; return;
        }
        summary.textContent = selected.length + ' selecc.';
    }
    function setAllChecks(checks, state) { checks.forEach(node => { node.checked = state; }); }
    function dateInRange(eventDate, fromDate, toDate) {
        if (!eventDate) return false;
        if (fromDate && eventDate < fromDate) return false;
        if (toDate && eventDate > toDate) return false;
        return true;
    }
    function eventToTimestamp(ev) {
        if (!ev || !ev.event_date) return 0;
        const ts = Date.parse(String(ev.event_date) + 'T00:00:00');
        return Number.isFinite(ts) ? ts : 0;
    }
    function computeRowsRange(rows) {
        const values = rows.map(eventToTimestamp).filter(v => v > 0).sort((a, b) => a - b);
        if (!values.length) return { minTs: 0, maxTs: 0 };
        return { minTs: values[0], maxTs: values[values.length - 1] };
    }
    function computeDenseWindow(rows) {
        const counts = {}; let minYear = 999999; let maxYear = 0;
        rows.forEach(ev => {
            if (!ev.event_date || ev.event_date.length < 4) return;
            const y = Number(ev.event_date.slice(0, 4));
            if (!Number.isFinite(y) || y <= 0) return;
            counts[y] = (counts[y] || 0) + 1;
            if (y < minYear) minYear = y;
            if (y > maxYear) maxYear = y;
        });
        if (!Number.isFinite(minYear) || maxYear <= 0 || maxYear < minYear) return null;
        const windowSize = 8; let bestStart = minYear; let bestScore = -1;
        for (let start = minYear; start <= maxYear; start++) {
            let score = 0;
            for (let y = start; y < start + windowSize; y++) score += (counts[y] || 0);
            if (score > bestScore) { bestScore = score; bestStart = start; }
        }
        return { startTs: Date.UTC(bestStart - 1, 0, 1), endTs: Date.UTC(bestStart + windowSize, 11, 31) };
    }
    function typeColor(ev) {
        const fallback = '#4b78d4';
        const raw = String((ev && ev.type_color) || '').trim();
        if (!raw) return fallback;
        if (/^#[0-9a-fA-F]{6}$/.test(raw) || /^#[0-9a-fA-F]{3}$/.test(raw)) return raw;
        return fallback;
    }
    function buildChartData(rows) {
        const laneByType = new Map(); let laneCursor = 0; chartIndexById = new Map();
        return rows.map((ev, index) => {
            const type = String(ev.type_slug || 'evento');
            if (!laneByType.has(type)) laneByType.set(type, laneCursor++);
            const lane = laneByType.get(type); const ts = eventToTimestamp(ev); chartIndexById.set(Number(ev.id), index);
            const selected = Number(ev.id) === Number(selectedId);
            return { value: [ts, lane], event: ev, symbolSize: selected ? 20 : 14,
                itemStyle: { color: typeColor(ev), borderColor: selected ? '#ffffff' : '#132e73', borderWidth: selected ? 2 : 1, shadowBlur: selected ? 10 : 0, shadowColor: selected ? '#8ce8ff' : 'transparent' } };
        });
    }
    function applyZoomToRange(minTs, maxTs, mode) {
        if (!chart || !minTs || !maxTs || minTs >= maxTs) return;
        let startTs = minTs; let endTs = maxTs;
        if (mode === 'dense') {
            const dense = computeDenseWindow(filteredEvents);
            if (dense) { startTs = Math.max(minTs, dense.startTs); endTs = Math.min(maxTs, dense.endTs); }
        }
        chart.dispatchAction({ type: 'dataZoom', dataZoomIndex: 0, startValue: startTs, endValue: endTs });
    }
    function renderChart(rows, mode) {
        if (!window.echarts || !chartContainer) return;
        if (!chart) {
            chart = echarts.init(chartContainer, null, { renderer: 'canvas' });
            chart.on('click', function(params){ if (params && params.data && params.data.event && params.data.event.url) window.location.href = String(params.data.event.url); });
        }
        const range = computeRowsRange(rows); currentRowsMinTs = range.minTs; currentRowsMaxTs = range.maxTs;
        const chartData = buildChartData(rows); const laneMax = Math.max(1, ...chartData.map(d => Number(d.value[1]) || 0));
        chart.setOption({ animation: false, grid: { left: 40, right: 20, top: 16, bottom: 64 },
            tooltip: { trigger: 'item', backgroundColor: '#070d33', borderColor: '#1d4da8', borderWidth: 1, textStyle: { color: '#e9f6ff', fontSize: 12 },
                formatter: function(params){ const ev = params && params.data ? params.data.event : null; if (!ev) return ''; return '<div style="font-weight:bold;margin-bottom:4px;">' + escapeHtml(ev.title || '(Sin titulo)') + '</div><div><b>Fecha:</b> ' + escapeHtml(ev.date_label || ev.event_date || '-') + '</div><div><b>Tipo:</b> ' + escapeHtml(ev.type_name || 'Evento') + '</div><div><b>Crónica:</b> ' + escapeHtml(ev.chronicle_line || '-') + '</div>'; } },
            xAxis: { type: 'time', min: globalMinTs, max: globalMaxTs, axisLabel: { color: '#d8ebff' }, axisLine: { lineStyle: { color: '#2c57b3' } }, splitLine: { lineStyle: { color: 'rgba(95,130,220,.25)' } } },
            yAxis: { type: 'value', min: -0.5, max: laneMax + 0.5, show: false },
            dataZoom: [ { type: 'inside', xAxisIndex: 0, filterMode: 'none', zoomOnMouseWheel: true, moveOnMouseMove: true, moveOnMouseWheel: true }, { type: 'slider', xAxisIndex: 0, height: 18, bottom: 16, backgroundColor: '#081a53', fillerColor: 'rgba(104,213,255,.35)', borderColor: '#1f4aa7', handleStyle: { color: '#8edaff' }, textStyle: { color: '#9ec7ff' }, filterMode: 'none' } ],
            series: [ { type: 'scatter', label: { show: false }, emphasis: { label: { show: false } }, data: chartData } ]
        }, true);
        if (mode === 'dense') applyZoomToRange(currentRowsMinTs, currentRowsMaxTs, 'dense');
        else if (mode === 'fit') applyZoomToRange(currentRowsMinTs, currentRowsMaxTs, 'fit');
    }
    function zoomChart(factor) {
        if (!chart) return;
        const opt = chart.getOption(); if (!opt || !opt.dataZoom || !opt.dataZoom[1]) return;
        const dz = opt.dataZoom[1]; let start = Number(dz.start); let end = Number(dz.end);
        if (!Number.isFinite(start) || !Number.isFinite(end)) return;
        const center = (start + end) / 2; let width = (end - start) * factor; width = Math.max(2, Math.min(100, width));
        start = Math.max(0, center - width / 2); end = Math.min(100, center + width / 2); if (end - start < 2) end = Math.min(100, start + 2);
        chart.dispatchAction({ type: 'dataZoom', dataZoomIndex: 1, start, end });
    }
    function eventMatchesFilters(ev) {
        const q = (inputSearch.value || '').trim().toLowerCase();
        if (q !== '') {
            const hay = [ev.title, ev.description, ev.source, ev.location, ev.chronicle_line, ev.reality_line, ev.type_name].join(' ').toLowerCase();
            if (!hay.includes(q)) return false;
        }
        const selectedTypes = getSelectedValues(typeChecks); if (selectedTypes !== null && !selectedTypes.includes(String(ev.type_slug || ''))) return false;
        const selectedChronicles = getSelectedValues(chronicleChecks);
        if (selectedChronicles !== null) { const selectedSet = new Set(selectedChronicles.map(v => Number(v))); const ids = Array.isArray(ev.chronicle_ids) ? ev.chronicle_ids : []; if (!ids.some(id => selectedSet.has(Number(id)))) return false; }
        if (allowRealityFilter) { const selectedRealities = getSelectedValues(realityChecks); if (selectedRealities !== null) { const selectedSet = new Set(selectedRealities.map(v => Number(v))); const ids = Array.isArray(ev.reality_ids) ? ev.reality_ids : []; if (!ids.some(id => selectedSet.has(Number(id)))) return false; } }
        const fromDate = (inputDateFrom.value || '').trim(); const toDate = (inputDateTo.value || '').trim();
        if ((fromDate !== '' || toDate !== '') && !dateInRange(String(ev.event_date || ''), fromDate, toDate)) return false;
        return true;
    }
    function initListDataTableIfAvailable() {
        if (listDt || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.DataTable) return;
        listDt = window.jQuery('#eventsTable').DataTable({ paging: true, pageLength: 25, lengthMenu: [10,25,50,100], searching: false, ordering: false, info: true, autoWidth: false, deferRender: true,
            language: { lengthMenu: 'Mostrar _MENU_ eventos', info: 'Mostrando _START_ a _END_ de _TOTAL_ eventos', infoEmpty: 'No hay eventos disponibles', emptyTable: 'No hay eventos que cumplan los filtros actuales', paginate: { first: 'Primero', last: 'Ultimo', next: '&#9654;', previous: '&#9664;' } } });
        window.jQuery('#eventsTable').on('draw.dt', function(){ refreshRowsSelection(); });
        window.jQuery('#eventsTable tbody').on('click', 'tr', function(evt) { if (evt.target && evt.target.tagName === 'A') return; const anchor = this.querySelector('a.events-table-title[data-event-id]'); const id = Number(anchor ? anchor.getAttribute('data-event-id') : 0); if (id > 0) selectEvent(id, true); });
        window.jQuery('#eventsTable tbody').on('dblclick', 'tr', function() { const anchor = this.querySelector('a.events-table-title'); if (anchor && anchor.href) window.location.href = anchor.href; });
    }
    function renderTable(rows) {
        initListDataTableIfAvailable();
        if (listDt) {
            const dataRows = rows.map(ev => [escapeHtml(ev.date_label || ev.event_date || '-'), '<a class="events-table-title" data-event-id="' + Number(ev.id) + '" href="' + escapeHtml(ev.url) + '">' + escapeHtml(ev.title || '(Sin titulo)') + '</a>', '<span class="events-pill">' + escapeHtml(ev.type_name || 'Evento') + '</span>', escapeHtml(ev.chronicle_line || '-'), escapeHtml(ev.source || '-')]);
            listDt.clear(); if (dataRows.length > 0) listDt.rows.add(dataRows); listDt.draw(false);
        } else {
            tableBody.innerHTML = '';
            rows.forEach(ev => { const tr = document.createElement('tr'); tr.dataset.eventId = String(ev.id); if (Number(ev.id) === Number(selectedId)) tr.classList.add('is-active'); tr.innerHTML = '<td>' + escapeHtml(ev.date_label || ev.event_date || '-') + '</td><td><a class="events-table-title" data-event-id="' + Number(ev.id) + '" href="' + escapeHtml(ev.url) + '">' + escapeHtml(ev.title || '(Sin titulo)') + '</a></td><td><span class="events-pill">' + escapeHtml(ev.type_name || 'Evento') + '</span></td><td>' + escapeHtml(ev.chronicle_line || '-') + '</td><td>' + escapeHtml(ev.source || '-') + '</td>'; tr.addEventListener('click', function(evt) { if (evt.target && evt.target.tagName === 'A') return; selectEvent(Number(ev.id), true); }); tr.addEventListener('dblclick', function() { if (ev.url) window.location.href = ev.url; }); tableBody.appendChild(tr); });
        }
        listCount.textContent = rows.length + ' filas'; noRows.style.display = listDt ? 'none' : (rows.length === 0 ? '' : 'none');
    }
    function refreshRowsSelection() {
        document.querySelectorAll('#eventsTable tbody tr').forEach(tr => { const anchor = tr.querySelector('a.events-table-title[data-event-id]'); const rowId = Number(anchor ? anchor.getAttribute('data-event-id') : (tr.dataset.eventId || 0)); tr.classList.toggle('is-active', rowId === Number(selectedId)); });
    }
    function selectEvent(id, focusTimeline) {
        const ev = eventMap.get(Number(id)); if (!ev) return; selectedId = Number(id); renderChart(filteredEvents, ''); refreshRowsSelection();
        if (focusTimeline && chart) { const ts = eventToTimestamp(ev); if (ts > 0) { const spanMs = 1000 * 60 * 60 * 24 * 365 * 4; chart.dispatchAction({ type: 'dataZoom', dataZoomIndex: 0, startValue: ts - spanMs, endValue: ts + spanMs }); } }
    }
    function fitTimelineIfPossible(rows) { if (!rows || rows.length === 0 || !chart) return; applyZoomToRange(currentRowsMinTs, currentRowsMaxTs, 'fit'); }
    function focusMainEra() { if (!chart) return; chart.dispatchAction({ type: 'dataZoom', dataZoomIndex: 0, startValue: Date.UTC(2000,0,1), endValue: Date.UTC(2007,11,31) }); }
    function focusGameplayEraOnLoad() { if (!chart) return; chart.dispatchAction({ type: 'dataZoom', dataZoomIndex: 0, startValue: Date.UTC(2004,0,1), endValue: Date.UTC(2007,11,31) }); }
    function applyFilters(keepSelection) {
        filteredEvents = allEvents.filter(eventMatchesFilters); visibleChip.textContent = 'Mostrando: ' + filteredEvents.length; renderTable(filteredEvents);
        if (filteredEvents.length === 0) { selectedId = 0; refreshRowsSelection(); renderChart(filteredEvents, 'fit'); return; }
        if (!(keepSelection && filteredEvents.some(e => Number(e.id) === Number(selectedId)))) selectedId = Number(filteredEvents[0].id);
        renderChart(filteredEvents, keepSelection ? '' : 'dense'); refreshRowsSelection();
    }
    function setView(view) {
        document.querySelectorAll('.events-view-toggle-btn').forEach(btn => btn.classList.toggle('is-active', btn.dataset.view === view));
        document.querySelectorAll('.events-view').forEach(panel => panel.classList.toggle('is-active', panel.dataset.viewPanel === view));
        if (view === 'timeline' && chart) chart.resize();
    }
    document.querySelectorAll('.events-view-toggle-btn').forEach(btn => btn.addEventListener('click', function(){ setView(String(btn.dataset.view || 'timeline')); }));
    [inputSearch, inputDateFrom, inputDateTo].forEach(node => { node.addEventListener('input', function(){ applyFilters(true); }); node.addEventListener('change', function(){ applyFilters(true); }); });
    multiFilterConfigs.forEach(cfg => {
        if (cfg.hidden) return;
        const toggle = document.getElementById('ms-toggle-' + cfg.key); const wrap = document.getElementById('filter-' + cfg.key); const btnAll = document.getElementById('ms-select-all-' + cfg.key); const btnClear = document.getElementById('ms-clear-' + cfg.key);
        if (toggle) { toggle.addEventListener('click', function(){ toggleMsPanel(cfg.key); }); toggle.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleMsPanel(cfg.key); } }); }
        cfg.checks.forEach(node => node.addEventListener('change', function(){ updateMsSummary(cfg); applyFilters(true); }));
        if (btnAll) btnAll.addEventListener('click', function(){ setAllChecks(cfg.checks, true); updateMsSummary(cfg); applyFilters(true); });
        if (btnClear) btnClear.addEventListener('click', function(){ setAllChecks(cfg.checks, false); updateMsSummary(cfg); applyFilters(true); });
        document.addEventListener('click', function(e){ if (wrap && !wrap.contains(e.target)) closeMsPanel(cfg.key); });
    });
    document.getElementById('evReset').addEventListener('click', function(){ inputSearch.value = ''; multiFilterConfigs.forEach(cfg => { if (!cfg.hidden) { setAllChecks(cfg.checks, true); updateMsSummary(cfg); closeMsPanel(cfg.key); } }); inputDateFrom.value = ''; inputDateTo.value = ''; applyFilters(false); if (chart) applyZoomToRange(currentRowsMinTs, currentRowsMaxTs, 'dense'); });
    document.getElementById('evZoomIn').addEventListener('click', function(){ zoomChart(0.75); });
    document.getElementById('evZoomOut').addEventListener('click', function(){ zoomChart(1.35); });
    document.getElementById('evFit').addEventListener('click', function(){ fitTimelineIfPossible(filteredEvents); });
    document.getElementById('evFocusMain').addEventListener('click', function(){ focusMainEra(); });
    document.getElementById('evFullscreen').addEventListener('click', function(){ if (!timelineWrap) return; if (document.fullscreenElement) document.exitFullscreen(); else timelineWrap.requestFullscreen(); });
    document.addEventListener('fullscreenchange', function(){ if (chart) chart.resize(); });
    window.addEventListener('resize', function(){ if (chart) chart.resize(); });
    setView('timeline');
    if (!window.echarts) { if (chartNotice) { chartNotice.style.display = 'block'; chartNotice.textContent = 'No se pudo cargar Apache ECharts. Revisa conectividad o bloqueos de CDN.'; } return; }
    multiFilterConfigs.forEach(updateMsSummary); applyFilters(false); focusGameplayEraOnLoad();
})();
</script>
