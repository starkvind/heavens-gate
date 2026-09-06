(function (w) {
    'use strict';

    var legacyMultiselectStyleProps = [
        'font-family',
        'font-size',
        'font-weight',
        'line-height',
        'padding',
        'border',
        'border-color',
        'border-radius',
        'background-color',
        'color',
        'box-sizing',
        'height',
        'min-height',
        'outline',
        'box-shadow'
    ];

    var databaseColumnProfiles = (
        w.HG_DATATABLE_COLUMNS
        && typeof w.HG_DATATABLE_COLUMNS === 'object'
        && !Array.isArray(w.HG_DATATABLE_COLUMNS)
    ) ? w.HG_DATATABLE_COLUMNS : {};

    /* Fallback only: database configuration is authoritative when installed. */
    var coreColumnProfiles = {
        'tabla-capitulos': [
            ['episodio'],
            ['nº', 'numero'],
            ['temporada']
        ]
    };

    /* Fallback middle/key column for tables that fit Name + key + Origin. */
    var keyColumnProfiles = {
        'tabla-acciones': ['tirada'],
        'tabla-meritos': ['tipo'],
        'tabla-rasgos': ['tipo'],
        'tabla-documentos': ['tipo', 'categoria'],
        'tabla-inventario': ['tipo', 'categoria'],
        'tabla-dones': ['nivel', 'rango', 'tipo'],
        'tabla-ritos': ['nivel', 'rango', 'tipo'],
        'tabla-disciplinas': ['nivel', 'rango', 'tipo'],
        'tabla-totems': ['nivel', 'rango', 'tipo'],
        'tabla-personajes': ['tipo', 'cronica', 'estado']
    };

    var genericKeyLabels = [
        'tirada',
        'tipo',
        'categoria',
        'nivel',
        'rango',
        'sistema',
        'clasificacion',
        'temporada',
        'cronica',
        'personajes',
        'estado',
        'coste'
    ];

    function clearLegacyMultiselectInlineStyles() {
        w.document.querySelectorAll('.ms-btn').forEach(function (button) {
            legacyMultiselectStyleProps.forEach(function (property) {
                button.style.removeProperty(property);
            });
        });
    }

    function normalizeLabel(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }

    function findColumnByLabels(labels, candidates, allowedIndexes, excludedIndexes) {
        var normalized = labels.map(normalizeLabel);
        var excluded = excludedIndexes || [];

        for (var c = 0; c < candidates.length; c += 1) {
            var candidate = normalizeLabel(candidates[c]);
            if (!candidate) continue;
            for (var i = 0; i < normalized.length; i += 1) {
                if (allowedIndexes.indexOf(i) === -1 || excluded.indexOf(i) !== -1) continue;
                if (normalized[i] === candidate || normalized[i].indexOf(candidate) !== -1) {
                    return i;
                }
            }
        }

        return -1;
    }

    function configuredColumnSets(table, managedIndexes) {
        var profile = table && table.id ? databaseColumnProfiles[table.id] : null;
        if (!profile || !Array.isArray(profile.columns)) return null;

        var core = [];
        var defaults = [];

        profile.columns.forEach(function (column) {
            if (!column || typeof column !== 'object') return;
            var index = Number(column.index);
            if (!Number.isInteger(index) || managedIndexes.indexOf(index) === -1) return;

            if (column.is_core === true && core.indexOf(index) === -1) {
                core.push(index);
            }
            if ((column.visible_default === true || column.is_core === true) && defaults.indexOf(index) === -1) {
                defaults.push(index);
            }
        });

        if (!core.length) return null;
        core.sort(function (a, b) { return a - b; });
        defaults.sort(function (a, b) { return a - b; });

        return {
            core: core,
            defaults: defaults.length ? defaults : core.slice()
        };
    }

    function inferCoreColumns(table, api, managedIndexes) {
        var labels = api.columns().header().toArray().map(function (header) {
            return header ? header.textContent.trim() : '';
        });

        var explicitProfile = coreColumnProfiles[table.id];
        if (explicitProfile) {
            var explicitCore = [];
            explicitProfile.forEach(function (candidates) {
                var index = findColumnByLabels(labels, candidates, managedIndexes, explicitCore);
                if (index !== -1) explicitCore.push(index);
            });
            if (explicitCore.length) return explicitCore;
        }

        var nameCandidates = [
            'nombre', 'accion', 'personaje', 'documento', 'objeto', 'don', 'rito',
            'ritual', 'disciplina', 'totem', 'cancion', 'jugador', 'arquetipo',
            'rasgo', 'merito', 'defecto', 'capitulo', 'episodio'
        ];
        var originCandidates = ['origen', 'fuente', 'bibliografia'];

        var nameIndex = findColumnByLabels(labels, nameCandidates, managedIndexes, []);
        if (nameIndex === -1) nameIndex = managedIndexes[0];

        var originIndex = findColumnByLabels(labels, originCandidates, managedIndexes, [nameIndex]);
        if (originIndex === -1) {
            for (var oi = managedIndexes.length - 1; oi >= 0; oi -= 1) {
                if (managedIndexes[oi] !== nameIndex) {
                    originIndex = managedIndexes[oi];
                    break;
                }
            }
        }

        var keyCandidates = keyColumnProfiles[table.id] || genericKeyLabels;
        var keyIndex = findColumnByLabels(labels, keyCandidates, managedIndexes, [nameIndex, originIndex]);
        if (keyIndex === -1) {
            keyIndex = managedIndexes.find(function (index) {
                return index !== nameIndex && index !== originIndex;
            });
        }

        var core = [];
        [nameIndex, keyIndex, originIndex].forEach(function (index) {
            if (typeof index !== 'number' || index < 0) return;
            if (managedIndexes.indexOf(index) === -1 || core.indexOf(index) !== -1) return;
            core.push(index);
        });

        return core;
    }

    function markWideDataTable(table, wrapper, api) {
        if (!table || !wrapper) return;

        var visibleCount = api
            ? api.columns(':visible').count()
            : (table.tHead && table.tHead.rows.length ? table.tHead.rows[0].cells.length : 0);

        wrapper.classList.toggle('hg-datatable-wide', visibleCount > 4);
    }

    function markExistingWideDataTables($) {
        w.document.querySelectorAll('.dataTables_wrapper table.dataTable').forEach(function (table) {
            var wrapper = table.closest('.dataTables_wrapper');
            var api = $.fn.dataTable.isDataTable(table) ? new $.fn.dataTable.Api(table) : null;
            markWideDataTable(table, wrapper, api);
        });
    }

    function setColumnSet(api, managedIndexes, visibleIndexes) {
        managedIndexes.forEach(function (index) {
            api.column(index).visible(visibleIndexes.indexOf(index) !== -1, false);
        });
        api.columns.adjust().draw(false);
    }

    function createColumnPicker($, settings) {
        var table = settings && settings.nTable;
        var wrapper = settings && settings.nTableWrapper;
        if (!table || !wrapper || table.dataset.hgColumnsReady === '1') return;
        if (table.getAttribute('data-hg-column-picker') === 'off') return;

        /* Special DataTables such as the map browser use a deliberately minimal
         * DOM (for example "tip") and own their column model themselves. */
        if (settings.sDom && settings.sDom.indexOf('f') === -1 && settings.sDom.indexOf('l') === -1) return;

        var api = new $.fn.dataTable.Api(settings);
        var managedIndexes = api.columns().indexes().toArray().filter(function (index) {
            return api.column(index).visible();
        });

        if (managedIndexes.length <= 4) {
            markWideDataTable(table, wrapper, api);
            return;
        }

        table.dataset.hgColumnsReady = '1';

        var labels = api.columns().header().toArray().map(function (header, index) {
            var text = header ? header.textContent.trim() : '';
            return text || ('Columna ' + (index + 1));
        });
        var configuredSets = configuredColumnSets(table, managedIndexes);
        var coreIndexes = configuredSets ? configuredSets.core : inferCoreColumns(table, api, managedIndexes);
        var defaultIndexes = configuredSets ? configuredSets.defaults : coreIndexes.slice();

        setColumnSet(api, managedIndexes, defaultIndexes);
        markWideDataTable(table, wrapper, api);

        var toolbar = w.document.createElement('div');
        toolbar.className = 'hg-dt-column-toolbar';

        var picker = w.document.createElement('details');
        picker.className = 'hg-dt-column-picker';

        var summary = w.document.createElement('summary');
        summary.appendChild(w.document.createTextNode('Columnas'));
        var summaryCount = w.document.createElement('span');
        summaryCount.className = 'hg-dt-column-picker__summary';
        summary.appendChild(summaryCount);
        picker.appendChild(summary);

        var panel = w.document.createElement('div');
        panel.className = 'hg-dt-column-picker__panel';

        managedIndexes.forEach(function (index) {
            var option = w.document.createElement('label');
            option.className = 'hg-dt-column-picker__option';

            var checkbox = w.document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = defaultIndexes.indexOf(index) !== -1;
            checkbox.disabled = coreIndexes.indexOf(index) !== -1;
            checkbox.setAttribute('data-column-index', String(index));

            var text = w.document.createElement('span');
            text.textContent = labels[index];

            option.appendChild(checkbox);
            option.appendChild(text);
            panel.appendChild(option);
        });

        var actions = w.document.createElement('div');
        actions.className = 'hg-dt-column-picker__actions';

        var showAllButton = w.document.createElement('button');
        showAllButton.type = 'button';
        showAllButton.textContent = 'Todo';

        var basicsButton = w.document.createElement('button');
        basicsButton.type = 'button';
        basicsButton.textContent = 'Básicas';

        actions.appendChild(showAllButton);
        actions.appendChild(basicsButton);
        panel.appendChild(actions);
        picker.appendChild(panel);
        toolbar.appendChild(picker);
        wrapper.parentNode.insertBefore(toolbar, wrapper);

        function syncPicker() {
            var visible = api.columns(':visible').indexes().toArray();
            var visibleManaged = 0;

            panel.querySelectorAll('input[data-column-index]').forEach(function (checkbox) {
                var index = Number(checkbox.getAttribute('data-column-index'));
                checkbox.checked = visible.indexOf(index) !== -1;
                if (checkbox.checked) visibleManaged += 1;
            });

            summaryCount.textContent = visibleManaged + '/' + managedIndexes.length;
            markWideDataTable(table, wrapper, api);
        }

        panel.addEventListener('change', function (event) {
            var checkbox = event.target;
            if (!checkbox.matches('input[data-column-index]') || checkbox.disabled) return;

            var index = Number(checkbox.getAttribute('data-column-index'));
            api.column(index).visible(checkbox.checked, false);
            api.columns.adjust().draw(false);
            syncPicker();
        });

        showAllButton.addEventListener('click', function () {
            setColumnSet(api, managedIndexes, managedIndexes);
            syncPicker();
        });

        basicsButton.addEventListener('click', function () {
            setColumnSet(api, managedIndexes, coreIndexes);
            syncPicker();
        });

        $(table).on('column-visibility.dt.hgColumns', function () {
            w.setTimeout(syncPicker, 0);
        });

        syncPicker();
    }

    function wireMultiselectAccessibility() {
        var doc = w.document;

        doc.querySelectorAll('.ms-wrap').forEach(function (wrap) {
            var toggle = wrap.querySelector('.ms-btn[role="button"]');
            var panel = wrap.querySelector('.ms-panel');
            if (!toggle || !panel) return;

            if (panel.id && !toggle.hasAttribute('aria-controls')) {
                toggle.setAttribute('aria-controls', panel.id);
            }
            if (toggle.id && !panel.hasAttribute('aria-labelledby')) {
                panel.setAttribute('aria-labelledby', toggle.id);
            }
        });

        doc.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;

            var openPanels = Array.prototype.slice.call(
                doc.querySelectorAll('.ms-panel[aria-hidden="false"]')
            );
            if (!openPanels.length) return;

            var target = event.target;
            var targetWrap = target && target.closest ? target.closest('.ms-wrap') : null;
            var focusToggle = targetWrap ? targetWrap.querySelector('.ms-btn[role="button"]') : null;

            openPanels.forEach(function (panel) {
                var wrap = panel.closest('.ms-wrap');
                var toggle = wrap ? wrap.querySelector('.ms-btn[role="button"]') : null;

                panel.style.display = 'none';
                panel.setAttribute('aria-hidden', 'true');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');

                if (!focusToggle && toggle) focusToggle = toggle;
            });

            event.preventDefault();
            if (focusToggle) focusToggle.focus();
        });
    }

    function init() {
        wireMultiselectAccessibility();

        var $ = w.jQuery;
        if (!$ || !$.fn || !$.fn.dataTable) return;

        if ($.fn.dataTable.ext) {
            $.fn.dataTable.ext.errMode = 'none';
        }

        if ($.fn.dataTable.defaults && !$.fn.dataTable.defaults.__hgDefaultsApplied) {
            $.extend(true, $.fn.dataTable.defaults, {
                language: {
                    search: '&#128269; Buscar:&nbsp;',
                    lengthMenu: 'Mostrar _MENU_ resultados',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ resultados',
                    infoEmpty: 'No hay resultados disponibles',
                    emptyTable: 'No hay datos en la tabla',
                    paginate: {
                        first: 'Primero',
                        last: 'Ultimo',
                        next: '&#9654;',
                        previous: '&#9664;'
                    }
                }
            });
            $.fn.dataTable.defaults.__hgDefaultsApplied = true;
        }

        $(w.document).on('init.dt.hgDataTables', function (event, settings) {
            if (settings) {
                w.setTimeout(function () {
                    createColumnPicker($, settings);
                }, 0);
            }
            w.setTimeout(clearLegacyMultiselectInlineStyles, 0);
        });

        w.setTimeout(function () {
            clearLegacyMultiselectInlineStyles();
            markExistingWideDataTables($);
        }, 0);
    }

    if (w.document.readyState === 'loading') {
        w.document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window);
