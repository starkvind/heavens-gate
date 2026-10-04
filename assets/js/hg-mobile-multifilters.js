(function () {
    'use strict';

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    }

    function filterKey(control, index) {
        const explicit = String(control.dataset.mobileFilterKey || '').trim();
        if (explicit) return explicit;
        const name = String(control.getAttribute('name') || '').trim();
        return name || (index === 0 ? 'value' : 'filter' + String(index + 1));
    }

    function filterDependencies(control) {
        return String(control.dataset.mobileFilterDependsOn || '')
            .split(',')
            .map(function (value) { return value.trim(); })
            .filter(Boolean);
    }

    function itemFilterValue(item, key) {
        if (key === 'value') return item.dataset.mobileFilterValue || '';
        const attr = 'data-mobile-filter-' + key.replace(/[^a-z0-9_-]+/gi, '-').toLowerCase();
        return item.getAttribute(attr) || '';
    }

    function sortFacetValues(values) {
        return values.sort(function (a, b) {
            if (a === '-' && b !== '-') return 1;
            if (b === '-' && a !== '-') return -1;
            return String(a).localeCompare(String(b), 'es', { numeric: true, sensitivity: 'base' });
        });
    }

    function availableMap(values) {
        const map = new Map();
        sortFacetValues(values).forEach(function (raw) {
            const normalized = normalize(raw);
            if (!map.has(normalized)) map.set(normalized, raw);
        });
        return map;
    }

    function initialSelected(control) {
        const selected = new Set();
        Array.from(control.options || []).forEach(function (option) {
            const raw = String(option.value || '').trim();
            if (raw !== '' && option.selected) selected.add(normalize(raw));
        });
        return selected;
    }

    function mountWidget(entry) {
        const control = entry.control;
        const originalLabel = control.closest('label');
        const originalLabelText = originalLabel
            ? String((originalLabel.querySelector('span') || {}).textContent || '').trim()
            : '';
        const labelText = originalLabelText || String(control.getAttribute('aria-label') || entry.key).trim();

        const field = document.createElement('div');
        field.className = 'hg-mobile-filter-field';
        field.dataset.mobileFilterField = entry.key;

        const label = document.createElement('span');
        label.className = 'hg-mobile-filter-field-label';
        label.textContent = labelText;
        const labelId = 'hg-mobile-filter-label-' + entry.uid;
        label.id = labelId;

        control.hidden = true;
        control.setAttribute('aria-hidden', 'true');
        control.tabIndex = -1;
        control.multiple = true;

        const widget = document.createElement('div');
        widget.className = 'hg-mobile-multiselect';

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'hg-mobile-multiselect-toggle';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-labelledby', labelId + ' ' + labelId + '-summary');

        const summary = document.createElement('span');
        summary.className = 'hg-mobile-multiselect-summary';
        summary.id = labelId + '-summary';

        const caret = document.createElement('span');
        caret.className = 'hg-mobile-multiselect-caret';
        caret.setAttribute('aria-hidden', 'true');
        caret.textContent = '▾';

        toggle.append(summary, caret);

        const panel = document.createElement('div');
        panel.className = 'hg-mobile-multiselect-panel';
        panel.hidden = true;

        const options = document.createElement('div');
        options.className = 'hg-mobile-multiselect-options';
        panel.appendChild(options);
        widget.append(toggle, panel);

        if (originalLabel && originalLabel.parentNode) {
            originalLabel.parentNode.replaceChild(field, originalLabel);
        } else if (control.parentNode) {
            control.parentNode.insertBefore(field, control);
        }
        field.append(label, control, widget);

        entry.field = field;
        entry.toggle = toggle;
        entry.summary = summary;
        entry.panel = panel;
        entry.optionsNode = options;

        toggle.addEventListener('click', function () {
            const willOpen = panel.hidden;
            entry.closePeers();
            panel.hidden = !willOpen;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        options.addEventListener('change', function (event) {
            const checkbox = event.target.closest('[data-mobile-multiselect-option]');
            if (!checkbox) return;
            const normalized = normalize(checkbox.value);
            if (checkbox.checked) entry.selected.add(normalized);
            else entry.selected.delete(normalized);
            entry.onSelectionChange();
        });
    }

    function renderWidget(entry) {
        entry.optionsNode.innerHTML = '';

        if (!entry.available.size) {
            const empty = document.createElement('span');
            empty.className = 'hg-mobile-multiselect-empty';
            empty.textContent = 'Sin opciones';
            entry.optionsNode.appendChild(empty);
        } else {
            entry.available.forEach(function (raw, normalized) {
                const row = document.createElement('label');
                row.className = 'hg-mobile-multiselect-option';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = raw;
                checkbox.checked = entry.selected.has(normalized);
                checkbox.setAttribute('data-mobile-multiselect-option', '');

                const text = document.createElement('span');
                text.textContent = raw;
                row.append(checkbox, text);
                entry.optionsNode.appendChild(row);
            });
        }

        if (entry.selected.size === 0) {
            entry.summary.textContent = entry.allLabel;
        } else if (entry.selected.size === 1) {
            const normalized = Array.from(entry.selected)[0];
            entry.summary.textContent = entry.available.get(normalized) || '1 selecc.';
        } else {
            entry.summary.textContent = entry.selected.size + ' selecc.';
        }

        entry.control.innerHTML = '';
        const neutral = document.createElement('option');
        neutral.value = '';
        neutral.textContent = entry.allLabel;
        entry.control.appendChild(neutral);
        entry.available.forEach(function (raw, normalized) {
            const option = document.createElement('option');
            option.value = raw;
            option.textContent = raw;
            option.selected = entry.selected.has(normalized);
            entry.control.appendChild(option);
        });
    }

    function closeEntry(entry) {
        if (!entry.panel) return;
        entry.panel.hidden = true;
        if (entry.toggle) entry.toggle.setAttribute('aria-expanded', 'false');
    }

    function buildEntry(control, index, prefix) {
        const neutralOption = Array.from(control.options || []).find(function (option) {
            return option.value === '';
        });
        const sourceValues = Array.from(control.options || [])
            .map(function (option) { return String(option.value || '').trim(); })
            .filter(function (value) { return value !== ''; });

        return {
            control: control,
            key: filterKey(control, index),
            dependsOn: filterDependencies(control),
            allLabel: neutralOption ? neutralOption.textContent : 'Todos',
            selected: initialSelected(control),
            available: availableMap(sourceValues),
            uid: prefix + '-' + index + '-' + Math.random().toString(36).slice(2, 8),
            closePeers: function () {},
            onSelectionChange: function () {}
        };
    }

    function initClientList(list) {
        if (list.dataset.mobilePaginatedReady === '1') return;
        const filterControls = Array.from(list.querySelectorAll('[data-mobile-list-filter]'));
        if (!filterControls.length) return;

        const items = Array.from(list.querySelectorAll('[data-mobile-item]'));
        if (!items.length) return;

        list.dataset.mobilePaginatedReady = '1';

        const pageSize = Math.max(1, parseInt(list.dataset.pageSize || '20', 10) || 20);
        const cascadingFilters = list.dataset.mobileCascadingFilters === '1';
        const entries = filterControls.map(function (control, index) {
            return buildEntry(control, index, 'client');
        });
        const byKey = {};
        entries.forEach(function (entry) { byKey[entry.key] = entry; });

        let page = 1;
        let query = '';

        function closePeers(current) {
            entries.forEach(function (entry) {
                if (entry !== current) closeEntry(entry);
            });
        }

        entries.forEach(function (entry) {
            entry.closePeers = function () { closePeers(entry); };
            mountWidget(entry);
        });

        const tools = document.createElement('div');
        tools.className = 'hg-mobile-list-tools';

        const input = document.createElement('input');
        input.type = 'search';
        input.placeholder = list.dataset.searchPlaceholder || 'Buscar en esta lista';
        input.setAttribute('aria-label', input.placeholder);

        const meta = document.createElement('div');
        meta.className = 'hg-mobile-list-meta';

        const prev = document.createElement('button');
        prev.type = 'button';
        prev.textContent = 'Anterior';

        const next = document.createElement('button');
        next.type = 'button';
        next.textContent = 'Siguiente';

        const nav = document.createElement('div');
        nav.className = 'hg-mobile-list-nav';
        nav.append(prev, next);
        tools.append(input, meta, nav);
        list.parentNode.insertBefore(tools, list);

        const empty = document.createElement('p');
        empty.className = 'hg-mobile-muted hg-mobile-list-empty';
        empty.textContent = list.dataset.emptyText || 'No hay resultados.';
        empty.hidden = true;
        list.parentNode.insertBefore(empty, list.nextSibling);

        const clearButtons = Array.from(list.querySelectorAll('[data-mobile-list-filter-clear]'));
        const activeCounters = Array.from(list.querySelectorAll('[data-mobile-list-filter-count]'));

        function itemText(item) {
            return normalize(item.dataset.mobileSearch || item.textContent || '');
        }

        function dependencyMatches(item, entry) {
            if (!cascadingFilters) return true;
            return entry.dependsOn.every(function (dependencyKey) {
                const dependency = byKey[dependencyKey];
                if (!dependency || dependency.selected.size === 0) return true;
                return dependency.selected.has(normalize(itemFilterValue(item, dependencyKey)));
            });
        }

        function rebuildFacet(entry) {
            const values = [];
            items.forEach(function (item) {
                if (!dependencyMatches(item, entry)) return;
                values.push(itemFilterValue(item, entry.key) || '-');
            });
            entry.available = availableMap(values);

            let changed = false;
            Array.from(entry.selected).forEach(function (selected) {
                if (!entry.available.has(selected)) {
                    entry.selected.delete(selected);
                    changed = true;
                }
            });
            return changed;
        }

        function recomputeFacets() {
            let passes = Math.max(1, entries.length + 1);
            let changed;
            do {
                changed = false;
                entries.forEach(function (entry) {
                    if (rebuildFacet(entry)) changed = true;
                });
                passes -= 1;
            } while (changed && passes > 0);
            entries.forEach(renderWidget);
        }

        function activeFilterCount() {
            return entries.reduce(function (count, entry) {
                return count + (entry.selected.size > 0 ? 1 : 0);
            }, 0);
        }

        function syncFilterSummary() {
            const count = activeFilterCount();
            activeCounters.forEach(function (node) {
                node.textContent = count > 0 ? String(count) : '';
                node.hidden = count === 0;
            });
            clearButtons.forEach(function (button) {
                button.disabled = count === 0;
            });
        }

        function render() {
            const needle = normalize(query.trim());
            const matched = items.filter(function (item) {
                const matchesSearch = needle === '' || itemText(item).includes(needle);
                if (!matchesSearch) return false;

                return entries.every(function (entry) {
                    if (entry.selected.size === 0) return true;
                    return entry.selected.has(normalize(itemFilterValue(item, entry.key)));
                });
            });

            const totalPages = Math.max(1, Math.ceil(matched.length / pageSize));
            if (page > totalPages) page = totalPages;
            const start = (page - 1) * pageSize;
            const end = start + pageSize;
            const visible = new Set(matched.slice(start, end));

            items.forEach(function (item) {
                const isVisible = visible.has(item);
                item.hidden = !isVisible;
                item.style.display = isVisible ? '' : 'none';
            });

            empty.hidden = matched.length !== 0;
            empty.style.display = matched.length === 0 ? '' : 'none';
            prev.disabled = page <= 1;
            next.disabled = page >= totalPages;
            meta.textContent = matched.length === 0
                ? '0 resultados'
                : (start + 1) + '-' + Math.min(end, matched.length) + ' de ' + matched.length;
            syncFilterSummary();
        }

        entries.forEach(function (entry) {
            entry.onSelectionChange = function () {
                page = 1;
                recomputeFacets();
                render();
            };
        });

        input.addEventListener('input', function () {
            query = input.value;
            page = 1;
            render();
        });

        clearButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                entries.forEach(function (entry) { entry.selected.clear(); });
                page = 1;
                recomputeFacets();
                render();
            });
        });

        prev.addEventListener('click', function () {
            if (page > 1) {
                page -= 1;
                render();
            }
        });
        next.addEventListener('click', function () {
            page += 1;
            render();
        });

        document.addEventListener('click', function (event) {
            entries.forEach(function (entry) {
                if (entry.field && !entry.field.contains(event.target)) closeEntry(entry);
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') entries.forEach(closeEntry);
        });

        recomputeFacets();
        render();
    }

    function initServerFilterForm(form) {
        if (form.dataset.mobileServerMultifilterReady === '1') return;
        const controls = Array.from(form.querySelectorAll('[data-mobile-server-multifilter]'));
        if (!controls.length) return;
        form.dataset.mobileServerMultifilterReady = '1';

        const entries = controls.map(function (control, index) {
            return buildEntry(control, index, 'server');
        });
        const counters = Array.from(form.querySelectorAll('[data-mobile-server-filter-count]'));
        const clearButtons = Array.from(form.querySelectorAll('[data-mobile-server-filter-clear]'));

        function closePeers(current) {
            entries.forEach(function (entry) {
                if (entry !== current) closeEntry(entry);
            });
        }

        function syncEntry(entry) {
            renderWidget(entry);
            const hidden = form.querySelector('[data-mobile-server-filter-value="' + entry.key + '"]');
            if (hidden) {
                const rawValues = Array.from(entry.selected)
                    .map(function (normalized) { return entry.available.get(normalized) || ''; })
                    .filter(Boolean);
                hidden.value = rawValues.join(',');
            }
        }

        function syncForm() {
            entries.forEach(syncEntry);
            const count = entries.reduce(function (total, entry) {
                return total + (entry.selected.size > 0 ? 1 : 0);
            }, 0);
            counters.forEach(function (node) {
                node.textContent = count > 0 ? String(count) : '';
                node.hidden = count === 0;
            });
            clearButtons.forEach(function (button) { button.disabled = count === 0; });
        }

        entries.forEach(function (entry) {
            entry.closePeers = function () { closePeers(entry); };
            entry.onSelectionChange = syncForm;
            mountWidget(entry);
        });

        clearButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                entries.forEach(function (entry) { entry.selected.clear(); });
                syncForm();
            });
        });

        document.addEventListener('click', function (event) {
            entries.forEach(function (entry) {
                if (entry.field && !entry.field.contains(event.target)) closeEntry(entry);
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') entries.forEach(closeEntry);
        });

        syncForm();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-mobile-paginated]').forEach(initClientList);
        document.querySelectorAll('[data-mobile-server-filter-form]').forEach(initServerFilterForm);
    });
})();
