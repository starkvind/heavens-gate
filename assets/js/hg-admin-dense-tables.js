(function () {
  'use strict';

  var STORAGE_PREFIX = 'hg-admin-columns:v2:';
  var groups = Object.create(null);
  var rail = null;
  var railTrack = null;
  var activeWrapper = null;
  var syncingRail = false;
  var refreshQueued = false;

  var defaultsById = {
    tablaTraits: ['clasificacion', 'origen'],
    tablaConditions: ['origen', 'descripcion'],
    tablaMyd: ['origen'],
    tablaItems: ['origen'],
    acdTable: ['estado pj', 'responsable', 'evento', 'peso'],
    accTable: [],
    'worlds-table': ['pretty id'],
    eventsTable: ['tipo', 'vinculos', 'estado'],
    abqTable: ['pretty', 'fecha evento', 'evento id', 'estado'],
    'gift-image-table': ['pretty id', 'grupo', 'rango', 'personajes'],
    plotsTable: []
  };

  var freezeById = {
    'worlds-table': 3,
    abqTable: 3,
    'gift-image-table': 3
  };

  var identityById = {
    'worlds-table': [0, 2],
    abqTable: [0, 2],
    'gift-image-table': [0, 2]
  };

  function normalize(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function routeKey() {
    try {
      var url = new URL(window.location.href);
      return url.searchParams.get('s') || window.location.pathname || 'admin';
    } catch (e) {
      return window.location.pathname || 'admin';
    }
  }

  function headers(table) {
    var row = table.tHead && table.tHead.rows && table.tHead.rows[0];
    if (!row) return [];
    return Array.prototype.map.call(row.cells, function (cell) {
      return String(cell.textContent || '').replace(/\s+/g, ' ').trim();
    });
  }

  function signature(table) {
    return headers(table).map(normalize).join('|');
  }

  function tableKey(table) {
    var explicit = table.getAttribute('data-admin-table-key');
    if (explicit) return routeKey() + ':' + explicit;
    if (table.id) return routeKey() + ':' + table.id;
    return routeKey() + ':sig:' + signature(table);
  }

  function defaultHidden(table) {
    if (table.id && Object.prototype.hasOwnProperty.call(defaultsById, table.id)) {
      return defaultsById[table.id].slice();
    }
    var hs = headers(table).map(normalize);
    if (hs.indexOf('imagen') !== -1 && hs.indexOf('tirada') !== -1 && hs.indexOf('dificultad') !== -1) {
      return ['imagen', 'descripcion'];
    }
    if (hs.indexOf('titulo') !== -1 && hs.indexOf('seccion') !== -1 && hs.indexOf('origen') !== -1) {
      return ['origen'];
    }
    return [];
  }

  function freezeCount(table) {
    var attr = parseInt(table.getAttribute('data-freeze-left') || '', 10);
    if (attr > 0) return attr;
    if (table.id && freezeById[table.id]) return freezeById[table.id];
    return Math.min(2, headers(table).length);
  }

  function wrapperFor(table) {
    var wrapper = table.closest('.adm-table-scroll, .adm-table-wrap, .table-scroll, .worlds-table-wrap');
    if (wrapper) return wrapper;

    wrapper = document.createElement('div');
    wrapper.className = 'adm-table-scroll';
    wrapper.tabIndex = 0;
    wrapper.setAttribute('aria-label', 'Tabla desplazable');
    table.parentNode.insertBefore(wrapper, table);
    wrapper.appendChild(table);
    return wrapper;
  }

  function panelFor(wrapper) {
    return wrapper.closest('.panel-wrap');
  }

  function columnIndex(cell) {
    return typeof cell.cellIndex === 'number' ? cell.cellIndex : -1;
  }

  function isActionHeader(label, index, total) {
    var n = normalize(label);
    if (index === total - 1 && /^(acc\.?|accion|acciones|opciones)$/.test(n)) return true;
    return false;
  }

  function identityColumns(table) {
    if (table.id && identityById[table.id]) return identityById[table.id].slice();

    var hs = headers(table).map(normalize);
    var out = [];

    function add(index) {
      if (index >= 0 && out.indexOf(index) === -1 && !isActionHeader(hs[index], index, hs.length)) {
        out.push(index);
      }
    }

    var idIndex = hs.findIndex(function (label) {
      return /^(id|id pj|id personaje|#)$/.test(label);
    });
    add(idIndex);

    var identityIndex = hs.findIndex(function (label) {
      return /^(nombre|alias|titulo|personaje|accion|don|sistema|cronica|temporada|documento|recurso|trait|rasgo|condicion|objeto|merito|defecto|forma|menu|organizacion|trama)$/.test(label);
    });
    add(identityIndex);

    if (out.length === 0) {
      var freeze = Math.min(freezeCount(table), hs.length);
      for (var i = 0; i < freeze; i += 1) add(i);
    } else if (out.length === 1 && idIndex >= 0) {
      for (var j = 0; j < hs.length && out.length < 2; j += 1) {
        if (j === idIndex) continue;
        if (/^(pretty|pretty id|imagen|estado|orden|pos|fecha|tipo|act\.?|activo|activa)$/.test(hs[j])) continue;
        add(j);
      }
    }

    return out.slice(0, 2);
  }

  function requiredColumns(table) {
    var hs = headers(table);
    var required = Object.create(null);
    identityColumns(table).forEach(function (index) {
      if (index >= 0 && index < hs.length) required[index] = true;
    });
    hs.forEach(function (label, index) {
      if (isActionHeader(label, index, hs.length)) required[index] = true;
    });
    return required;
  }

  function savedHidden(key) {
    try {
      var raw = window.localStorage.getItem(STORAGE_PREFIX + key);
      if (!raw) return null;
      var value = JSON.parse(raw);
      return Array.isArray(value) ? value.map(normalize) : null;
    } catch (e) {
      return null;
    }
  }

  function saveHidden(key, labels) {
    try {
      window.localStorage.setItem(STORAGE_PREFIX + key, JSON.stringify(labels));
    } catch (e) {}
  }

  function groupFor(table) {
    var key = tableKey(table);
    if (!groups[key]) {
      groups[key] = {
        key: key,
        tables: [],
        hidden: null,
        defaultHidden: defaultHidden(table),
        toolbar: null,
        countNode: null
      };
      var saved = savedHidden(key);
      groups[key].hidden = saved === null ? groups[key].defaultHidden.slice() : saved;
    }
    return groups[key];
  }

  function setCellHidden(cell, hidden) {
    if (hidden) {
      cell.classList.add('adm-col-hidden');
      cell.setAttribute('aria-hidden', 'true');
    } else {
      cell.classList.remove('adm-col-hidden');
      cell.removeAttribute('aria-hidden');
    }
  }

  function applyHidden(table, hiddenLabels) {
    var hs = headers(table);
    var hidden = Object.create(null);
    hiddenLabels.forEach(function (label) { hidden[normalize(label)] = true; });
    var required = requiredColumns(table);

    Array.prototype.forEach.call(table.rows, function (row) {
      Array.prototype.forEach.call(row.cells, function (cell) {
        var index = columnIndex(cell);
        if (index < 0 || index >= hs.length) return;
        var shouldHide = !required[index] && !!hidden[normalize(hs[index])];
        setCellHidden(cell, shouldHide);
      });
    });
  }

  function clearFreeze(table) {
    Array.prototype.forEach.call(table.querySelectorAll('.adm-freeze-left, .adm-freeze-left-edge'), function (cell) {
      cell.classList.remove('adm-freeze-left', 'adm-freeze-left-edge');
      cell.style.removeProperty('left');
    });
  }

  function applyFreeze(table) {
    clearFreeze(table);
    var indexes = identityColumns(table);
    var offsets = Object.create(null);
    var running = 0;

    indexes.forEach(function (index) {
      var head = table.tHead && table.tHead.rows[0] ? table.tHead.rows[0].cells[index] : null;
      if (!head || head.classList.contains('adm-col-hidden')) return;
      offsets[index] = running;
      running += head.getBoundingClientRect().width;
    });

    Array.prototype.forEach.call(table.rows, function (row) {
      var lastVisible = null;
      indexes.forEach(function (index) {
        var cell = row.cells[index];
        if (!cell || cell.classList.contains('adm-col-hidden') || offsets[index] === undefined) return;
        cell.classList.add('adm-freeze-left');
        cell.style.left = offsets[index] + 'px';
        lastVisible = cell;
      });
      if (lastVisible) lastVisible.classList.add('adm-freeze-left-edge');
    });
  }

  function visibleCount(group) {
    if (!group.tables.length) return { visible: 0, total: 0 };
    var table = group.tables[0];
    var hs = headers(table);
    var hidden = Object.create(null);
    group.hidden.forEach(function (label) { hidden[normalize(label)] = true; });
    var required = requiredColumns(table);
    var visible = 0;
    hs.forEach(function (label, index) {
      if (required[index] || !hidden[normalize(label)]) visible += 1;
    });
    return { visible: visible, total: hs.length };
  }

  function refreshToolbarCount(group) {
    if (!group.countNode) return;
    var counts = visibleCount(group);
    group.countNode.textContent = counts.visible + '/' + counts.total;
  }

  function applyGroup(group) {
    group.tables = group.tables.filter(function (table) { return document.documentElement.contains(table); });
    group.tables.forEach(function (table) {
      applyHidden(table, group.hidden);
      window.requestAnimationFrame(function () {
        applyFreeze(table);
      });
    });
    refreshToolbarCount(group);
    scheduleRailRefresh();
  }

  function setGroupHidden(group, hidden) {
    group.hidden = hidden.map(normalize);
    saveHidden(group.key, group.hidden);
    applyGroup(group);
  }

  function makeToolbar(group, table, wrapper) {
    if (group.toolbar && !document.documentElement.contains(group.toolbar)) {
      group.toolbar = null;
      group.countNode = null;
    }
    if (group.toolbar || headers(table).length < 5) return;

    var hs = headers(table);
    var required = requiredColumns(table);
    var toolbar = document.createElement('div');
    toolbar.className = 'adm-table-tools';

    var details = document.createElement('details');
    details.className = 'adm-column-picker';

    var summary = document.createElement('summary');
    summary.className = 'btn adm-column-picker-summary';
    summary.innerHTML = 'Columnas <span class="adm-column-count"></span>';
    group.countNode = summary.querySelector('.adm-column-count');
    details.appendChild(summary);

    var menu = document.createElement('div');
    menu.className = 'adm-column-picker-menu';

    hs.forEach(function (label, index) {
      var clean = String(label || '').trim() || ('Columna ' + (index + 1));
      var n = normalize(clean);
      var row = document.createElement('label');
      row.className = 'adm-column-option';

      var input = document.createElement('input');
      input.type = 'checkbox';
      input.checked = required[index] || group.hidden.indexOf(n) === -1;
      input.disabled = !!required[index];
      input.setAttribute('data-column-label', n);
      input.addEventListener('change', function () {
        var next = group.hidden.filter(function (item) { return item !== n; });
        if (!input.checked) next.push(n);
        setGroupHidden(group, next);
      });

      var text = document.createElement('span');
      text.textContent = clean + (required[index] ? ' · fija' : '');

      row.appendChild(input);
      row.appendChild(text);
      menu.appendChild(row);
    });

    var actions = document.createElement('div');
    actions.className = 'adm-column-picker-actions';

    var all = document.createElement('button');
    all.type = 'button';
    all.className = 'btn';
    all.textContent = 'Mostrar todas';
    all.addEventListener('click', function () {
      setGroupHidden(group, []);
      Array.prototype.forEach.call(menu.querySelectorAll('input[type="checkbox"]'), function (input) {
        input.checked = true;
      });
    });

    var reset = document.createElement('button');
    reset.type = 'button';
    reset.className = 'btn';
    reset.textContent = 'Vista inicial';
    reset.addEventListener('click', function () {
      setGroupHidden(group, group.defaultHidden.slice());
      var hidden = Object.create(null);
      group.hidden.forEach(function (label) { hidden[label] = true; });
      Array.prototype.forEach.call(menu.querySelectorAll('input[type="checkbox"]'), function (input) {
        input.checked = input.disabled || !hidden[input.getAttribute('data-column-label')];
      });
    });

    actions.appendChild(all);
    actions.appendChild(reset);
    menu.appendChild(actions);
    details.appendChild(menu);
    toolbar.appendChild(details);

    var help = document.createElement('span');
    help.className = 'adm-table-tools-help';
    help.textContent = 'ID/nombre y acciones quedan fijos';
    toolbar.appendChild(help);

    wrapper.parentNode.insertBefore(toolbar, wrapper);
    group.toolbar = toolbar;
    refreshToolbarCount(group);
  }

  function isDenseCandidate(table) {
    if (!table || !table.classList) return false;
    if (table.getAttribute('data-admin-no-spreadsheet') === '1') return false;
    if (table.classList.contains('adm-wide-table')) return true;
    if (table.closest('.modal, .popup-edit, .icon-picker')) return false;
    return headers(table).length >= 5;
  }

  function compactActionColumn(table, wrapper) {
    var hs = headers(table);
    if (!hs.length) return;
    var index = hs.length - 1;
    if (!isActionHeader(hs[index], index, hs.length)) return;

    wrapper.classList.add('adm-sticky-actions');
    var head = table.tHead && table.tHead.rows[0] ? table.tHead.rows[0].cells[index] : null;
    if (head) {
      head.classList.add('adm-th-actions');
      head.title = 'Acciones';
      if (normalize(head.textContent) !== 'acc.') head.textContent = 'Acc.';
    }

    Array.prototype.forEach.call(table.tBodies || [], function (tbody) {
      Array.prototype.forEach.call(tbody.rows || [], function (row) {
        var cell = row.cells[index];
        if (!cell) return;
        cell.classList.add('adm-cell-actions');

        Array.prototype.forEach.call(cell.querySelectorAll('button, a'), function (control) {
          var label = normalize(control.textContent);
          if (label === 'editar' || label === '✏ editar' || label === '✏') {
            control.classList.add('adm-icon-btn');
            if (!control.getAttribute('aria-label')) control.setAttribute('aria-label', 'Editar');
            if (!control.getAttribute('title')) control.setAttribute('title', 'Editar');
            control.textContent = '✏';
          } else if (label === 'borrar' || label === '🗑 borrar' || label === '🗑') {
            control.classList.add('adm-icon-btn');
            if (!control.getAttribute('aria-label')) control.setAttribute('aria-label', 'Borrar');
            if (!control.getAttribute('title')) control.setAttribute('title', 'Borrar');
            control.textContent = '🗑';
          }
        });
      });
    });
  }

  function registerTable(table) {
    if (!isDenseCandidate(table)) return;
    table.classList.add('adm-wide-table');

    var wrapper = wrapperFor(table);
    wrapper.classList.add('adm-dense-table');
    if (!wrapper.hasAttribute('tabindex')) wrapper.tabIndex = 0;

    var panel = panelFor(wrapper);
    if (panel) panel.classList.add('adm-admin-wide-panel');

    compactActionColumn(table, wrapper);

    var group = groupFor(table);
    if (group.tables.indexOf(table) === -1) group.tables.push(table);

    makeToolbar(group, table, wrapper);
    applyHidden(table, group.hidden);
    applyFreeze(table);

    if (!table.dataset.admDenseObserved) {
      var observer = new MutationObserver(function () {
        window.requestAnimationFrame(function () {
          compactActionColumn(table, wrapper);
          applyHidden(table, group.hidden);
          applyFreeze(table);
          scheduleRailRefresh();
        });
      });
      observer.observe(table, { childList: true, subtree: true });
      table.dataset.admDenseObserved = '1';
    }

    wrapper.addEventListener('mouseenter', function () {
      activeWrapper = wrapper;
      scheduleRailRefresh();
    });
    wrapper.addEventListener('focusin', function () {
      activeWrapper = wrapper;
      scheduleRailRefresh();
    });
    wrapper.addEventListener('scroll', function () {
      if (!rail || activeWrapper !== wrapper || syncingRail) return;
      syncingRail = true;
      rail.scrollLeft = wrapper.scrollLeft;
      syncingRail = false;
    });

    table.dataset.admDenseReady = '1';
    scheduleRailRefresh();
  }

  function scan(root) {
    var scope = root && root.querySelectorAll ? root : document;
    if (scope.matches && scope.matches('table')) registerTable(scope);

    if (scope.closest) {
      var ownerTable = scope.closest('table');
      if (ownerTable) registerTable(ownerTable);
    }

    Array.prototype.forEach.call(scope.querySelectorAll('table'), registerTable);
  }

  function ensureRail() {
    if (rail) return;
    rail = document.createElement('div');
    rail.className = 'adm-table-bottom-rail';
    rail.setAttribute('aria-label', 'Desplazamiento horizontal de la tabla');
    railTrack = document.createElement('div');
    railTrack.className = 'adm-table-bottom-rail-track';
    rail.appendChild(railTrack);
    document.body.appendChild(rail);

    rail.addEventListener('scroll', function () {
      if (!activeWrapper || syncingRail) return;
      syncingRail = true;
      activeWrapper.scrollLeft = rail.scrollLeft;
      syncingRail = false;
    });
  }

  function visibleScore(wrapper) {
    var rect = wrapper.getBoundingClientRect();
    var top = Math.max(rect.top, 0);
    var bottom = Math.min(rect.bottom, window.innerHeight);
    var height = Math.max(0, bottom - top);
    return height * Math.max(0, Math.min(rect.right, window.innerWidth) - Math.max(rect.left, 0));
  }

  function chooseWrapper() {
    var candidates = Array.prototype.slice.call(document.querySelectorAll('.adm-dense-table'));
    var best = null;
    var bestScore = 0;

    candidates.forEach(function (wrapper) {
      if (wrapper.scrollWidth <= wrapper.clientWidth + 2) return;
      var score = visibleScore(wrapper);
      if (score > bestScore) {
        best = wrapper;
        bestScore = score;
      }
    });
    return best;
  }

  function refreshRail() {
    refreshQueued = false;
    ensureRail();

    if (!activeWrapper || !document.documentElement.contains(activeWrapper) || visibleScore(activeWrapper) <= 0) {
      activeWrapper = chooseWrapper();
    }

    if (!activeWrapper || activeWrapper.scrollWidth <= activeWrapper.clientWidth + 2 || visibleScore(activeWrapper) <= 0) {
      rail.classList.remove('is-visible');
      return;
    }

    var rect = activeWrapper.getBoundingClientRect();
    var left = Math.max(0, rect.left);
    var right = Math.min(window.innerWidth, rect.right);
    if (right - left < 80) {
      rail.classList.remove('is-visible');
      return;
    }

    rail.style.left = left + 'px';
    rail.style.width = (right - left) + 'px';
    railTrack.style.width = activeWrapper.scrollWidth + 'px';

    syncingRail = true;
    rail.scrollLeft = activeWrapper.scrollLeft;
    syncingRail = false;
    rail.classList.add('is-visible');
  }

  function scheduleRailRefresh() {
    if (refreshQueued) return;
    refreshQueued = true;
    window.requestAnimationFrame(refreshRail);
  }

  function refreshAllFreeze() {
    Object.keys(groups).forEach(function (key) {
      groups[key].tables.forEach(function (table) {
        applyFreeze(table);
      });
    });
    scheduleRailRefresh();
  }

  var mutation = new MutationObserver(function (records) {
    records.forEach(function (record) {
      Array.prototype.forEach.call(record.addedNodes || [], function (node) {
        if (node.nodeType === 1) scan(node);
      });
    });
  });

  function init() {
    document.body.classList.add('adm-admin-dense-ready');
    scan(document);
    mutation.observe(document.body, { childList: true, subtree: true });
    window.addEventListener('resize', refreshAllFreeze);
    window.addEventListener('scroll', scheduleRailRefresh, { passive: true });
    document.addEventListener('visibilitychange', scheduleRailRefresh);
    scheduleRailRefresh();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
}());
