(function (window, document) {
  'use strict';

  var DEFAULT_COLOR = '#95A5A6';
  var DEFAULT_AREA_COLOR = '#2ECC71';

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (match) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[match];
    });
  }

  function safeUrl(value) {
    var url = String(value == null ? '' : value).trim();
    if (!url) {
      return '';
    }

    if (/^(https?:)?\/\//i.test(url)) {
      return url;
    }

    if (url.charAt(0) === '/') {
      return url;
    }

    return '';
  }

  function normalizeColor(value, fallback) {
    var color = String(value == null ? '' : value).trim();
    if (!color) {
      return fallback || DEFAULT_COLOR;
    }

    if (color.charAt(0) !== '#') {
      color = '#' + color;
    }

    return /^#[0-9a-f]{6}$/i.test(color) ? color.toUpperCase() : (fallback || DEFAULT_COLOR);
  }

  function uniqueCount(values) {
    var seen = Object.create(null);
    var count = 0;

    values.forEach(function (value) {
      var key = String(value || '');
      if (!key || seen[key]) {
        return;
      }
      seen[key] = true;
      count += 1;
    });

    return count;
  }

  function debounce(fn, wait) {
    var timer = 0;
    return function () {
      var args = arguments;
      clearTimeout(timer);
      timer = window.setTimeout(function () {
        fn.apply(null, args);
      }, wait);
    };
  }

  function pickEmoji(categoryName) {
    var key = String(categoryName || '').toLowerCase();
    var icons = {
      'hogar': 'H',
      'guarida': 'G',
      'punto conflictivo': '!',
      'templo': 'T',
      'lugar sagrado': '+',
      'otros': 'O',
      'tumulo': '@'
    };
    return icons[key] || '*';
  }

  function makeDivIcon(colorHex) {
    var color = normalizeColor(colorHex, DEFAULT_COLOR);

    return window.L.divIcon({
      className: 'hg-poi-icon',
      html: '<span class="hg-poi-dot" style="--marker-color:' + color + ';"></span>',
      iconSize: [20, 20],
      iconAnchor: [10, 10],
      popupAnchor: [0, -12]
    });
  }

  function makeEmojiIcon(emoji) {
    return window.L.divIcon({
      className: 'hg-poi-icon emoji-marker',
      html: '<span class="hg-poi-dot">' + escapeHtml(emoji || '*') + '</span>',
      iconSize: [20, 20],
      iconAnchor: [10, 10],
      popupAnchor: [0, -12]
    });
  }

  function buildPoiPopupHtml(poi, showMapName) {
    var thumbUrl = safeUrl(poi.thumbnail);
    var detailUrl = safeUrl(poi.detail_url);
    var parts = ['<div class="map-popup-card">'];

    parts.push('<div class="map-popup-title">' + escapeHtml(poi.name) + '</div>');

    if (poi.category_name || (showMapName && poi.map_name)) {
      parts.push('<div class="map-popup-meta">');
      if (poi.category_name) {
        parts.push('<span class="map-popup-pill">' + escapeHtml(poi.category_name) + '</span>');
      }
      if (showMapName && poi.map_name) {
        parts.push('<span class="map-popup-pill map-popup-pill-map">' + escapeHtml(poi.map_name) + '</span>');
      }
      parts.push('</div>');
    }

    if (thumbUrl) {
      parts.push('<img class="map-thumb" src="' + escapeHtml(thumbUrl) + '" alt="">');
    }

    if (poi.description) {
      parts.push('<div class="map-popup-desc">' + escapeHtml(poi.description) + '</div>');
    }

    if (detailUrl) {
      parts.push('<div class="map-popup-link"><a class="infoLink" href="' + escapeHtml(detailUrl) + '">Ver detalle</a></div>');
    }

    parts.push('</div>');
    return parts.join('');
  }

  function buildAreaPopupHtml(area) {
    var parts = ['<div class="map-popup-card">'];
    parts.push('<div class="map-popup-title">' + escapeHtml(area.name || 'Area') + '</div>');
    if (area.category_name) {
      parts.push('<div class="map-popup-meta"><span class="map-popup-pill">' + escapeHtml(area.category_name) + '</span></div>');
    }
    if (area.description) {
      parts.push('<div class="map-popup-desc">' + escapeHtml(area.description) + '</div>');
    }
    parts.push('</div>');
    return parts.join('');
  }

  function popupOptions() {
    return {
      maxWidth: 340,
      autoPan: true,
      keepInView: true,
      autoPanPaddingTopLeft: [28, 180],
      autoPanPaddingBottomRight: [28, 28],
      className: 'hg-map-popup'
    };
  }

  function keepPopupInView(map, marker) {
    if (!map || !marker || !marker.getPopup) {
      return;
    }

    window.setTimeout(function () {
      var popup = marker.getPopup();
      var popupEl = popup && popup.getElement ? popup.getElement() : null;
      var topPadding = 180;

      if (popupEl && popupEl.offsetHeight) {
        topPadding = Math.max(180, Math.min(340, popupEl.offsetHeight + 28));
      }

      map.panInside(marker.getLatLng(), {
        paddingTopLeft: [28, topPadding],
        paddingBottomRight: [28, 28],
        animate: true
      });
    }, 40);
  }

  function updateClearButton(state) {
    if (!state.dom.queryInput || !state.dom.clearBtn) {
      return;
    }
    state.dom.clearBtn.hidden = !state.dom.queryInput.value.trim();
  }

  function setLoading(state, isLoading) {
    state.isLoading = !!isLoading;
    if (state.dom.root) {
      state.dom.root.classList.toggle('is-loading', !!isLoading);
    }
    if (state.dom.statusText && isLoading) {
      state.dom.statusText.textContent = 'Actualizando mapa...';
    }
  }

  function showStatus(state, text, isError) {
    if (!state.dom.statusText) {
      return;
    }

    state.dom.statusText.textContent = text;
    state.dom.statusText.classList.toggle('is-error', !!isError);
  }

  function currentCategoryFilter(state) {
    if (!state.dom.categorySelect || !state.dom.categorySelect.value) {
      return { id: 0, name: '' };
    }

    var option = state.dom.categorySelect.options[state.dom.categorySelect.selectedIndex];
    var categoryId = parseInt(option.getAttribute('data-id') || '0', 10) || 0;

    return {
      id: categoryId,
      name: option.value || ''
    };
  }

  function includeAllMaps(state) {
    return !!(state.config.allowGlobalPoiScope && state.dom.includeAllMaps && state.dom.includeAllMaps.checked);
  }

  function currentSourceMapId(state) {
    if (!includeAllMaps(state) || !state.dom.sourceMapSelect) {
      return 0;
    }
    return parseInt(state.dom.sourceMapSelect.value || '0', 10) || 0;
  }

  function shouldShowMapColumn(state, items) {
    return includeAllMaps(state) || uniqueCount(items.map(function (item) { return item.map_name; })) > 1;
  }

  function updateSummary(state, items) {
    var categories = {};
    var categoryCount = 0;
    var mapsVisible = uniqueCount(items.map(function (item) { return item.map_name; }));
    var html = [];

    items.forEach(function (item) {
      var key = item.category_name || 'Sin categoria';
      if (!categories[key]) {
        categories[key] = {
          count: 0,
          color: normalizeColor(item.color_hex, DEFAULT_COLOR)
        };
        categoryCount += 1;
      }
      categories[key].count += 1;
    });

    if (state.dom.summary) {
      var scopeText = includeAllMaps(state) ? 'Todos los mapas' : (state.config.selectedMap.name || 'Mapa actual');
      state.dom.summary.innerHTML = [
        '<span class="map-stat"><strong>' + items.length + '</strong> lugares</span>',
        '<span class="map-stat"><strong>' + categoryCount + '</strong> categorias</span>',
        '<span class="map-stat"><strong>' + mapsVisible + '</strong> mapas</span>',
        '<span class="map-stat map-stat-wide">Ambito: <strong>' + escapeHtml(scopeText) + '</strong></span>'
      ].join('');
    }

    if (state.dom.legend) {
      Object.keys(categories).sort().forEach(function (key) {
        html.push(
          '<span class="map-legend-chip">' +
            '<span class="map-legend-swatch" style="background:' + escapeHtml(categories[key].color) + '"></span>' +
            escapeHtml(key) +
            '<strong>' + categories[key].count + '</strong>' +
          '</span>'
        );
      });
      state.dom.legend.innerHTML = html.join('');
    }

    if (items.length) {
      showStatus(
        state,
        includeAllMaps(state)
          ? 'Mostrando ' + items.length + ' lugares sobre ' + (state.config.selectedMap.name || 'el mapa global') + '.'
          : 'Mostrando ' + items.length + ' lugares visibles.',
        false
      );
    } else {
      showStatus(state, 'No hay lugares con los filtros actuales.', false);
    }
  }

  function focusPoi(state, poiId) {
    var marker = state.markerById[String(poiId)];
    if (!marker) {
      return;
    }

    var latLng = marker.getLatLng();
    state.map.flyTo(latLng, Math.max(state.map.getZoom(), 12), { duration: 0.55 });
    window.setTimeout(function () {
      marker.openPopup();
      keepPopupInView(state.map, marker);
    }, 350);
  }

  function renderTable(state, items) {
    var showMapColumn = shouldShowMapColumn(state, items);

    if (state.dataTable) {
      var rows = items.map(function (item) {
        return {
          id: item.id,
          name: item.name || '',
          category_name: item.category_name || '',
          map_name: item.map_name || ''
        };
      });

      state.dataTable.clear();
      state.dataTable.rows.add(rows);
      state.dataTable.draw();
      state.dataTable.column(2).visible(showMapColumn);
      return;
    }

    if (!state.dom.tableBody) {
      return;
    }

    state.dom.tableBody.innerHTML = '';

    items.forEach(function (item) {
      var row = document.createElement('tr');
      row.innerHTML =
        '<td>' + escapeHtml(item.name || '') + '</td>' +
        '<td>' + escapeHtml(item.category_name || '') + '</td>' +
        '<td class="' + (showMapColumn ? '' : 'is-hidden') + '">' + escapeHtml(item.map_name || '') + '</td>' +
        '<td><button type="button" class="boton2 map-focus-btn" data-poi-id="' + String(item.id) + '">Localizar</button></td>';
      state.dom.tableBody.appendChild(row);
    });
  }

  function initTable(state) {
    var tableEl = state.dom.table;
    if (!tableEl) {
      return;
    }

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) {
      state.dataTable = window.jQuery(tableEl).DataTable({
        data: [],
        autoWidth: false,
        responsive: false,
        searching: false,
        lengthChange: false,
        pageLength: 8,
        order: [[0, 'asc']],
        dom: 'tip',
        columns: [
          { data: 'name', title: 'Nombre' },
          { data: 'category_name', title: 'Categoria' },
          { data: 'map_name', title: 'Mapa' },
          {
            data: null,
            title: '',
            orderable: false,
            searchable: false,
            className: 'map-action-cell',
            render: function (data, type, row) {
              return '<button type="button" class="boton2 map-focus-btn" data-poi-id="' + String(row.id) + '">Localizar</button>';
            }
          }
        ],
        language: {
          info: 'Mostrando _START_ a _END_ de _TOTAL_ lugares',
          infoEmpty: 'No hay lugares visibles',
          emptyTable: 'No hay datos para mostrar',
          paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: '>',
            previous: '<'
          }
        }
      });

      window.jQuery(tableEl).off('click.hgMaps').on('click.hgMaps', '.map-focus-btn', function () {
        focusPoi(state, this.getAttribute('data-poi-id'));
      });
    } else {
      tableEl.addEventListener('click', function (event) {
        var button = event.target.closest('.map-focus-btn');
        if (!button) {
          return;
        }
        focusPoi(state, button.getAttribute('data-poi-id'));
      });
    }
  }

  function recenterToDefault(state) {
    if (state.config.bounds && state.config.bounds.length === 2) {
      state.map.flyToBounds(state.config.bounds, { padding: [20, 20], duration: 0.55 });
      return;
    }

    state.map.flyTo([state.config.selectedMap.center_lat, state.config.selectedMap.center_lng], state.config.selectedMap.default_zoom, {
      duration: 0.55
    });
  }

  function paintPois(state, items, fitBounds) {
    var latLngs = [];
    var showMapName = shouldShowMapColumn(state, items);

    state.markerById = Object.create(null);
    state.cluster.clearLayers();

    items.forEach(function (item) {
      var lat = parseFloat(item.latitude);
      var lng = parseFloat(item.longitude);
      var marker;
      var icon;

      if (!isFinite(lat) || !isFinite(lng)) {
        return;
      }

      icon = item.color_hex ? makeDivIcon(item.color_hex) : makeEmojiIcon(pickEmoji(item.category_name));
      marker = window.L.marker([lat, lng], { icon: icon });
      marker.bindPopup(buildPoiPopupHtml(item, showMapName), popupOptions());
      marker.on('click', function () {
        if (state.map.getZoom() < 12) {
          state.map.flyTo([lat, lng], 12, { duration: 0.35 });
        }
      });
      marker.on('popupopen', function () {
        keepPopupInView(state.map, marker);
      });

      state.cluster.addLayer(marker);
      state.markerById[String(item.id)] = marker;
      latLngs.push([lat, lng]);
    });

    renderTable(state, items);
    updateSummary(state, items);

    if (fitBounds && latLngs.length) {
      state.map.fitBounds(latLngs, { padding: [24, 24] });
    } else if (!latLngs.length) {
      recenterToDefault(state);
    }
  }

  function paintAreas(state, areas) {
    var features = [];

    state.areasLayer.clearLayers();

    areas.forEach(function (area) {
      var geometry = area && area.geometry ? area.geometry : null;

      if (!geometry || !geometry.type) {
        return;
      }

      features.push({
        type: 'Feature',
        properties: {
          id: area.id,
          name: area.name || 'Area',
          description: area.description || '',
          category_name: area.category_name || '',
          color_hex: normalizeColor(area.color_hex, DEFAULT_AREA_COLOR)
        },
        geometry: geometry.geometry || geometry
      });
    });

    if (features.length) {
      state.areasLayer.addData({
        type: 'FeatureCollection',
        features: features
      });
    }
  }

  function fetchJson(url, params, signal) {
    var finalUrl = url + '?' + params.toString();

    return window.fetch(finalUrl, {
      credentials: 'same-origin',
      signal: signal
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }
      return response.json();
    }).then(function (json) {
      if (!json || !json.ok) {
        throw new Error(json && json.error ? json.error : 'Respuesta no valida');
      }
      return json;
    });
  }

  function buildPoiParams(state) {
    var params = new window.URLSearchParams();
    var category = currentCategoryFilter(state);

    params.set('ajax', 'search');
    params.set('map_id', String(state.config.selectedMap.id));
    params.set('limit', includeAllMaps(state) ? '1000' : '600');

    if (category.id > 0) {
      params.set('category_id', String(category.id));
    } else if (category.name) {
      params.set('category_name', category.name);
    }

    if (includeAllMaps(state)) {
      params.set('include_all_maps', '1');
      if (currentSourceMapId(state) > 0) {
        params.set('source_map_id', String(currentSourceMapId(state)));
      }
    }

    if (state.dom.queryInput && state.dom.queryInput.value.trim()) {
      params.set('q', state.dom.queryInput.value.trim());
    }

    return params;
  }

  function buildAreaParams(state) {
    var params = new window.URLSearchParams();
    var category = currentCategoryFilter(state);

    params.set('ajax', 'areas');
    params.set('map_id', String(state.config.selectedMap.id));

    if (category.id > 0) {
      params.set('category_id', String(category.id));
    }

    return params;
  }

  function updateScopeControls(state) {
    if (!state.dom.scopeRow) {
      return;
    }

    var enabled = includeAllMaps(state);
    state.dom.scopeRow.classList.toggle('is-disabled', !enabled);

    if (state.dom.sourceMapSelect) {
      state.dom.sourceMapSelect.disabled = !enabled;
      if (!enabled) {
        state.dom.sourceMapSelect.value = '0';
      }
    }
  }

  function reloadData(state, fitBounds) {
    var requestId = ++state.requestId;
    var signal = null;

    if (state.abortController) {
      state.abortController.abort();
    }

    if (window.AbortController) {
      state.abortController = new window.AbortController();
      signal = state.abortController.signal;
    } else {
      state.abortController = null;
    }

    setLoading(state, true);

    return Promise.all([
      fetchJson(state.config.apiBase, buildPoiParams(state), signal),
      fetchJson(state.config.apiBase, buildAreaParams(state), signal)
    ]).then(function (results) {
      if (requestId !== state.requestId) {
        return;
      }

      paintPois(state, results[0].items || [], fitBounds !== false);
      paintAreas(state, results[1].items || []);
    }).catch(function (error) {
      if (error && error.name === 'AbortError') {
        return;
      }

      window.console.error('[hg-maps] reload failed', error);
      showStatus(state, 'No se pudo actualizar el mapa.', true);
    }).finally(function () {
      if (requestId === state.requestId) {
        setLoading(state, false);
      }
    });
  }

  function initMain(config) {
    var root = document.querySelector('[data-map-page="main"]');
    var map;
    var tileLayerConfig;
    var debouncedReload;
    var state;

    if (!root || !window.L) {
      return;
    }

    tileLayerConfig = config.tile || {};

    map = window.L.map('hg-map', {
      zoomControl: true,
      attributionControl: true,
      minZoom: config.selectedMap.min_zoom,
      maxZoom: config.selectedMap.max_zoom,
      maxBounds: config.bounds || null,
      maxBoundsViscosity: config.bounds ? 1.0 : 0
    }).setView([config.selectedMap.center_lat, config.selectedMap.center_lng], config.selectedMap.default_zoom);

    window.L.tileLayer(tileLayerConfig.url, {
      attribution: tileLayerConfig.attribution || '',
      subdomains: tileLayerConfig.subdomains || [],
      maxZoom: tileLayerConfig.maxZoom || config.selectedMap.max_zoom
    }).addTo(map);

    state = {
      config: config,
      map: map,
      cluster: window.L.markerClusterGroup({
        showCoverageOnHover: false,
        chunkedLoading: true,
        maxClusterRadius: 42
      }),
      areasLayer: window.L.geoJSON(null, {
        style: function (feature) {
          var color = normalizeColor(feature.properties && feature.properties.color_hex, DEFAULT_AREA_COLOR);
          return {
            color: color,
            weight: 2,
            fillColor: color,
            fillOpacity: 0.2
          };
        },
        onEachFeature: function (feature, layer) {
          layer.bindPopup(buildAreaPopupHtml(feature.properties || {}));
          layer.on('mouseover', function () {
            layer.setStyle({ weight: 3, fillOpacity: 0.28 });
          });
          layer.on('mouseout', function () {
            layer.setStyle({ weight: 2, fillOpacity: 0.2 });
          });
        }
      }),
      markerById: Object.create(null),
      dataTable: null,
      requestId: 0,
      abortController: null,
      dom: {
        root: root,
        queryInput: document.getElementById('poiSearch'),
        clearBtn: document.getElementById('btnClear'),
        searchBtn: document.getElementById('btnSearch'),
        recenterBtn: document.getElementById('btnRecenter'),
        fullscreenBtn: document.getElementById('btnFullscreen'),
        categorySelect: document.getElementById('catSel'),
        includeAllMaps: document.getElementById('toggleAllMaps'),
        sourceMapSelect: document.getElementById('sourceMapSel'),
        statusText: document.getElementById('mapStatusText'),
        summary: document.getElementById('mapSummary'),
        legend: document.getElementById('mapLegend'),
        table: document.getElementById('tabla-pois'),
        tableBody: document.querySelector('#tabla-pois tbody'),
        mapSelect: document.getElementById('mapSel'),
        mapForm: document.getElementById('mapControlsForm'),
        scopeRow: document.getElementById('sourceMapRow')
      }
    };

    map.addLayer(state.cluster);
    map.addLayer(state.areasLayer);
    window.L.control.layers(null, { Areas: state.areasLayer, Lugares: state.cluster }, { collapsed: true }).addTo(map);

    initTable(state);
    paintPois(state, config.initialPois || [], true);
    paintAreas(state, config.initialAreas || []);
    updateClearButton(state);
    updateScopeControls(state);

    debouncedReload = debounce(function () {
      reloadData(state, false);
    }, 280);

    if (state.dom.searchBtn) {
      state.dom.searchBtn.addEventListener('click', function () {
        reloadData(state, true);
      });
    }

    if (state.dom.queryInput) {
      state.dom.queryInput.addEventListener('input', function () {
        updateClearButton(state);
        debouncedReload();
      });
      state.dom.queryInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          reloadData(state, true);
        }
      });
    }

    if (state.dom.clearBtn) {
      state.dom.clearBtn.addEventListener('click', function () {
        if (!state.dom.queryInput) {
          return;
        }
        state.dom.queryInput.value = '';
        updateClearButton(state);
        reloadData(state, true);
      });
    }

    if (state.dom.categorySelect) {
      state.dom.categorySelect.addEventListener('change', function () {
        reloadData(state, true);
      });
    }

    if (state.dom.includeAllMaps) {
      state.dom.includeAllMaps.addEventListener('change', function () {
        updateScopeControls(state);
        reloadData(state, true);
      });
    }

    if (state.dom.sourceMapSelect) {
      state.dom.sourceMapSelect.addEventListener('change', function () {
        reloadData(state, true);
      });
    }

    if (state.dom.recenterBtn) {
      state.dom.recenterBtn.addEventListener('click', function () {
        recenterToDefault(state);
      });
    }

    if (state.dom.fullscreenBtn) {
      state.dom.fullscreenBtn.addEventListener('click', function () {
        var el = document.getElementById('hg-map');
        if (!document.fullscreenElement && el.requestFullscreen) {
          el.requestFullscreen();
        } else if (document.exitFullscreen) {
          document.exitFullscreen();
        }
      });
    }

    if (state.dom.mapSelect && state.dom.mapForm) {
      state.dom.mapSelect.addEventListener('change', function () {
        state.dom.mapForm.submit();
      });
    }

    document.querySelectorAll('.map-collapse').forEach(function (details) {
      details.addEventListener('toggle', function () {
        if (!details.open) {
          return;
        }

        window.setTimeout(function () {
          map.invalidateSize();
          if (state.dataTable && state.dataTable.columns) {
            state.dataTable.columns.adjust().draw(false);
          }
        }, 120);
      });
    });

    document.addEventListener('fullscreenchange', function () {
      window.setTimeout(function () {
        map.invalidateSize();
      }, 180);
    });

    window.addEventListener('resize', function () {
      map.invalidateSize();
    });
  }

  function initDetail(config) {
    var root = document.querySelector('[data-map-page="detail"]');
    var map;
    var marker;

    if (!root || !window.L || !config || !config.poi) {
      return;
    }

    map = window.L.map('hg-map-detail', {
      zoomControl: true,
      minZoom: config.poi.min_zoom || 3,
      maxZoom: config.poi.max_zoom || 19,
      maxBounds: config.poi.bounds || null,
      maxBoundsViscosity: config.poi.bounds ? 1.0 : 0
    }).setView([config.poi.latitude, config.poi.longitude], config.focusZoom || 12);

    window.L.tileLayer(config.tile.url, {
      attribution: config.tile.attribution || '',
      subdomains: config.tile.subdomains || [],
      maxZoom: config.tile.maxZoom || config.poi.max_zoom || 19
    }).addTo(map);

    marker = window.L.marker([config.poi.latitude, config.poi.longitude], {
      icon: window.L.divIcon({
        className: 'hg-detail-ping',
        html: '<span class="ping-wrap"><span class="ping-core"></span><span class="ping-wave"></span></span>',
        iconSize: [18, 18],
        iconAnchor: [9, 9]
      })
    }).addTo(map);

    marker.bindPopup(buildPoiPopupHtml(config.poi, !!config.poi.map_name)).openPopup();

    var fullscreenBtn = document.getElementById('btnDetailFullscreen');
    var recenterBtn = document.getElementById('btnDetailRecenter');

    if (fullscreenBtn) {
      fullscreenBtn.addEventListener('click', function () {
        var el = document.getElementById('hg-map-detail');
        if (!document.fullscreenElement && el.requestFullscreen) {
          el.requestFullscreen();
        } else if (document.exitFullscreen) {
          document.exitFullscreen();
        }
      });
    }

    if (recenterBtn) {
      recenterBtn.addEventListener('click', function () {
        map.flyTo([config.poi.latitude, config.poi.longitude], config.focusZoom || 12, { duration: 0.55 });
        window.setTimeout(function () {
          marker.openPopup();
        }, 320);
      });
    }

    document.addEventListener('fullscreenchange', function () {
      window.setTimeout(function () {
        map.invalidateSize();
      }, 180);
    });

    window.addEventListener('resize', function () {
      map.invalidateSize();
    });
  }

  window.HGMaps = {
    initMain: initMain,
    initDetail: initDetail
  };
})(window, document);
