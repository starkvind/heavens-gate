(function (w) {
    'use strict';

    function sortValues(values, locale) {
        return values.sort(function (a, b) {
            if (a === '-' && b !== '-') return 1;
            if (b === '-' && a !== '-') return -1;
            return String(a).localeCompare(String(b), locale || 'es', { numeric: true, sensitivity: 'base' });
        });
    }

    function valueFromRow(row, config) {
        var value;
        if (typeof config.value === 'function') {
            value = config.value(row);
        } else if (typeof config.source === 'function') {
            value = config.source(row);
        } else {
            value = row ? row[config.source] : '';
        }
        if (value === null || value === undefined || String(value).trim() === '') return '-';
        return String(value).trim();
    }

    function uniqueValues(rows, config, locale) {
        var seen = {};
        rows.forEach(function (row) {
            var value = valueFromRow(row, config);
            seen[value] = true;
        });
        return sortValues(Object.keys(seen), locale);
    }

    function create(options) {
        var $ = w.jQuery;
        if (!$ || !$.fn || !$.fn.dataTable) return null;
        if (!options || !options.table || !Array.isArray(options.rows) || !Array.isArray(options.filters)) return null;

        var dt = options.table;
        var rows = options.rows;
        var locale = options.locale || 'es';
        var configs = options.filters.map(function (input) {
            var config = Object.assign({}, input);
            config.domKey = config.domKey || config.key;
            config.dependsOn = Array.isArray(config.dependsOn) ? config.dependsOn.slice() : [];
            config.allLabel = config.allLabel || 'Todos';
            config.available = [];
            config.selected = null;
            return config;
        });
        var byKey = {};
        configs.forEach(function (config) { byKey[config.key] = config; });

        function rowsForDependencies(config) {
            if (!config.dependsOn.length) return rows;
            return rows.filter(function (row) {
                return config.dependsOn.every(function (dependencyKey) {
                    var dependency = byKey[dependencyKey];
                    if (!dependency || dependency.selected === null) return true;
                    return dependency.selected.has(valueFromRow(row, dependency));
                });
            });
        }

        function pruneSelection(config) {
            if (config.selected === null) return false;
            var valid = new Set(config.available);
            var next = new Set();
            config.selected.forEach(function (value) {
                if (valid.has(value)) next.add(value);
            });
            if (next.size === 0) {
                config.selected = null;
                return true;
            }
            if (next.size !== config.selected.size) {
                config.selected = next;
                return true;
            }
            return false;
        }

        function recomputeFacets() {
            var changed = false;
            var passes = Math.max(1, configs.length + 1);
            do {
                changed = false;
                configs.forEach(function (config) {
                    config.available = uniqueValues(rowsForDependencies(config), config, locale);
                    if (pruneSelection(config)) changed = true;
                });
                passes -= 1;
            } while (changed && passes > 0);
        }

        function panel(config) { return $('#ms-panel-' + config.domKey); }
        function toggle(config) { return $('#ms-toggle-' + config.domKey); }
        function optionsNode(config) { return $('#ms-options-' + config.domKey); }
        function summary(config) { return $('#ms-summary-' + config.domKey); }

        function openPanel(config) {
            panel(config).show().attr('aria-hidden', 'false');
            toggle(config).attr('aria-expanded', 'true');
        }

        function closePanel(config) {
            panel(config).hide().attr('aria-hidden', 'true');
            toggle(config).attr('aria-expanded', 'false');
        }

        function togglePanel(config) {
            panel(config).is(':visible') ? closePanel(config) : openPanel(config);
        }

        function renderSummary(config) {
            var node = summary(config);
            if (config.selected === null) {
                node.text(config.allLabel);
                return;
            }
            var selected = Array.from(config.selected);
            node.text(selected.length === 1 ? selected[0] : selected.length + ' selecc.');
        }

        function renderOptions(config) {
            var target = optionsNode(config);
            target.empty();
            config.available.forEach(function (value) {
                var row = $('<label>', { 'class': 'ms-row' });
                var input = $('<input>', { type: 'checkbox', value: value });
                input.prop('checked', config.selected === null || config.selected.has(value));
                row.append(input, $('<span>').text(value));
                target.append(row);
            });
            renderSummary(config);
        }

        function renderAllOptions() {
            configs.forEach(renderOptions);
        }

        function escapeRegex(value) {
            if ($.fn.dataTable.util && typeof $.fn.dataTable.util.escapeRegex === 'function') {
                return $.fn.dataTable.util.escapeRegex(String(value));
            }
            return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function applyDataTableFilters() {
            configs.forEach(function (config) {
                if (config.selected === null) {
                    dt.column(config.column).search('', true, false);
                    return;
                }
                var pattern = '^(?:' + Array.from(config.selected).map(escapeRegex).join('|') + ')$';
                dt.column(config.column).search(pattern, true, false);
            });
            dt.draw();
        }

        function refresh() {
            recomputeFacets();
            renderAllOptions();
            applyDataTableFilters();
        }

        configs.forEach(function (config) {
            toggle(config).on('click.hgCascadingFilters', function () { togglePanel(config); });
            toggle(config).on('keydown.hgCascadingFilters', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    togglePanel(config);
                }
            });

            optionsNode(config).on('change.hgCascadingFilters', 'input', function () {
                var checked = optionsNode(config).find('input:checked').map(function () { return $(this).val(); }).get();
                config.selected = checked.length ? new Set(checked) : null;
                refresh();
            });

            $('#ms-select-all-' + config.domKey).on('click.hgCascadingFilters', function () {
                config.selected = new Set(config.available);
                refresh();
            });

            $('#ms-clear-' + config.domKey).on('click.hgCascadingFilters', function () {
                config.selected = null;
                refresh();
            });
        });

        $(w.document).on('click.hgCascadingFilters', function (event) {
            configs.forEach(function (config) {
                var wrap = $('#filter-' + config.domKey);
                if (!wrap.length) wrap = $('#' + config.domKey + '-filter');
                if (!$(event.target).closest(wrap).length) closePanel(config);
            });
        });

        refresh();

        return {
            refresh: refresh,
            clear: function () {
                configs.forEach(function (config) { config.selected = null; });
                refresh();
            }
        };
    }

    w.HGDataTableFilters = { create: create };
})(window);
