(function () {
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
            return;
        }
        fn();
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function compareValues(a, b, mode) {
        if (mode === 'number') {
            var aNum = Number(String(a).replace(',', '.'));
            var bNum = Number(String(b).replace(',', '.'));
            var aValid = !Number.isNaN(aNum);
            var bValid = !Number.isNaN(bNum);
            if (aValid && bValid && aNum !== bNum) {
                return aNum - bNum;
            }
            if (aValid && !bValid) {
                return -1;
            }
            if (!aValid && bValid) {
                return 1;
            }
        }

        return String(a).localeCompare(String(b), 'es', { numeric: true, sensitivity: 'base' });
    }

    ready(function () {
        var config = window.HGPowerCustomPage;
        var root = document.getElementById('hgpc-root');
        if (!config || !root) {
            return;
        }

        var searchInput = document.getElementById('hgpc-search-input');
        var filterBar = document.getElementById('hgpc-filter-bar');
        var catalogSelect = document.getElementById('hgpc-catalog-select');
        var addSelectedBtn = document.getElementById('hgpc-add-selected');
        var clearFiltersBtn = document.getElementById('hgpc-clear-filters');
        var clearSelectionBtn = document.getElementById('hgpc-clear-selection');
        var libraryResults = document.getElementById('hgpc-library-results');
        var selectionList = document.getElementById('hgpc-selection-list');
        var selectionDrop = document.getElementById('hgpc-selection-drop');
        var renderEmpty = document.getElementById('hgpc-render-empty');
        var renderCardsEl = document.getElementById('hgpc-rendered-cards');
        var filteredCount = document.getElementById('hgpc-filtered-count');
        var selectionStatus = document.getElementById('hgpc-selection-status');
        var selectedCount = document.getElementById('hgpc-selected-count');
        var printNowBtn = document.getElementById('hgpc-print-now');

        var items = Array.isArray(config.items) ? config.items : [];
        var itemsById = new Map();
        items.forEach(function (item) {
            itemsById.set(String(item.id), item);
        });

        var state = {
            search: '',
            filters: {},
            selectedIds: loadSelection(),
            dragPayload: null
        };

        buildFilters();
        bindEvents();
        renderAll();

        function loadSelection() {
            if (!config.storageKey || !window.localStorage) {
                return [];
            }
            try {
                var raw = window.localStorage.getItem(config.storageKey);
                var parsed = raw ? JSON.parse(raw) : [];
                if (!Array.isArray(parsed)) {
                    return [];
                }
                return parsed
                    .map(function (id) { return String(id); })
                    .filter(function (id) { return itemsById.has(id); });
            } catch (error) {
                return [];
            }
        }

        function saveSelection() {
            if (!config.storageKey || !window.localStorage) {
                return;
            }
            try {
                window.localStorage.setItem(config.storageKey, JSON.stringify(state.selectedIds));
            } catch (error) {
                /* localStorage no disponible; seguimos en memoria. */
            }
        }

        function buildFilters() {
            filterBar.innerHTML = '';
            (config.filters || []).forEach(function (filterConfig) {
                var values = Array.from(new Set(items.map(function (item) {
                    return String((item.fields && item.fields[filterConfig.key]) || '').trim();
                }).filter(Boolean)));

                values.sort(function (a, b) {
                    return compareValues(a, b, filterConfig.sort || 'text');
                });

                var wrap = document.createElement('div');
                wrap.className = 'hgpc-filter';

                var label = document.createElement('label');
                label.setAttribute('for', 'hgpc-filter-' + filterConfig.key);
                label.textContent = filterConfig.label;
                wrap.appendChild(label);

                var select = document.createElement('select');
                select.id = 'hgpc-filter-' + filterConfig.key;
                select.dataset.filterKey = filterConfig.key;

                var emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = 'Todos';
                select.appendChild(emptyOption);

                values.forEach(function (value) {
                    var option = document.createElement('option');
                    option.value = value;
                    option.textContent = value;
                    select.appendChild(option);
                });

                wrap.appendChild(select);
                filterBar.appendChild(wrap);
            });
        }

        function bindEvents() {
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    state.search = (searchInput.value || '').trim().toLowerCase();
                    renderAll();
                });
            }

            filterBar.addEventListener('change', function (event) {
                var target = event.target;
                if (!(target instanceof HTMLSelectElement) || !target.dataset.filterKey) {
                    return;
                }
                state.filters[target.dataset.filterKey] = target.value || '';
                renderAll();
            });

            if (catalogSelect) {
                catalogSelect.addEventListener('dblclick', addSelectedFromSelect);
            }

            if (addSelectedBtn) {
                addSelectedBtn.addEventListener('click', addSelectedFromSelect);
            }

            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', function () {
                    state.search = '';
                    state.filters = {};
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    Array.from(filterBar.querySelectorAll('select')).forEach(function (select) {
                        select.value = '';
                    });
                    renderAll();
                });
            }

            if (clearSelectionBtn) {
                clearSelectionBtn.addEventListener('click', function () {
                    state.selectedIds = [];
                    saveSelection();
                    renderAll();
                });
            }

            if (printNowBtn) {
                printNowBtn.addEventListener('click', function () {
                    window.print();
                });
            }

            libraryResults.addEventListener('click', function (event) {
                var addBtn = event.target.closest('[data-add-id]');
                if (addBtn) {
                    addToSelection(addBtn.getAttribute('data-add-id'));
                }
            });

            libraryResults.addEventListener('dragstart', function (event) {
                var itemEl = event.target.closest('[data-library-id]');
                if (!itemEl || !event.dataTransfer) {
                    return;
                }
                state.dragPayload = {
                    kind: 'catalog',
                    id: itemEl.getAttribute('data-library-id')
                };
                event.dataTransfer.effectAllowed = 'copyMove';
                event.dataTransfer.setData('text/plain', JSON.stringify(state.dragPayload));
            });

            selectionList.addEventListener('click', function (event) {
                var removeBtn = event.target.closest('[data-remove-id]');
                if (removeBtn) {
                    removeFromSelection(removeBtn.getAttribute('data-remove-id'));
                }
            });

            selectionList.addEventListener('dragstart', function (event) {
                var itemEl = event.target.closest('[data-selected-id]');
                if (!itemEl || !event.dataTransfer) {
                    return;
                }
                state.dragPayload = {
                    kind: 'selected',
                    id: itemEl.getAttribute('data-selected-id')
                };
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', JSON.stringify(state.dragPayload));
            });

            selectionList.addEventListener('dragover', function (event) {
                var targetEl = event.target.closest('[data-selected-id]');
                if (!targetEl) {
                    return;
                }
                event.preventDefault();
                targetEl.classList.add('is-drop-target');
            });

            selectionList.addEventListener('dragleave', function (event) {
                var targetEl = event.target.closest('[data-selected-id]');
                if (targetEl) {
                    targetEl.classList.remove('is-drop-target');
                }
            });

            selectionList.addEventListener('drop', function (event) {
                var targetEl = event.target.closest('[data-selected-id]');
                if (!targetEl) {
                    return;
                }
                event.preventDefault();
                targetEl.classList.remove('is-drop-target');
                var payload = readDragPayload(event);
                if (!payload || !payload.id) {
                    return;
                }
                moveOrInsert(payload, targetEl.getAttribute('data-selected-id'));
            });

            selectionDrop.addEventListener('dragover', function (event) {
                event.preventDefault();
                selectionDrop.classList.add('is-over');
            });

            selectionDrop.addEventListener('dragleave', function () {
                selectionDrop.classList.remove('is-over');
            });

            selectionDrop.addEventListener('drop', function (event) {
                event.preventDefault();
                selectionDrop.classList.remove('is-over');
                var payload = readDragPayload(event);
                if (!payload || !payload.id) {
                    return;
                }
                moveOrInsert(payload, null);
            });
        }

        function readDragPayload(event) {
            if (state.dragPayload) {
                return state.dragPayload;
            }
            if (!event.dataTransfer) {
                return null;
            }
            try {
                return JSON.parse(event.dataTransfer.getData('text/plain') || '{}');
            } catch (error) {
                return null;
            }
        }

        function getFilteredItems() {
            return items.filter(function (item) {
                var matchesFilters = (config.filters || []).every(function (filterConfig) {
                    var expected = state.filters[filterConfig.key] || '';
                    if (!expected) {
                        return true;
                    }
                    return String((item.fields && item.fields[filterConfig.key]) || '') === expected;
                });

                if (!matchesFilters) {
                    return false;
                }

                if (!state.search) {
                    return true;
                }

                var haystack = [
                    item.name || '',
                    item.library_meta || '',
                    JSON.stringify(item.fields || {})
                ].join(' ').toLowerCase();

                return haystack.indexOf(state.search) !== -1;
            });
        }

        function addSelectedFromSelect() {
            if (!catalogSelect || !catalogSelect.value) {
                return;
            }
            addToSelection(catalogSelect.value);
        }

        function addToSelection(id, insertBeforeId) {
            id = String(id || '');
            if (!itemsById.has(id)) {
                return;
            }

            var existingIndex = state.selectedIds.indexOf(id);
            if (existingIndex !== -1) {
                if (insertBeforeId && insertBeforeId !== id) {
                    state.selectedIds.splice(existingIndex, 1);
                } else {
                    renderAll();
                    return;
                }
            }

            if (insertBeforeId) {
                var targetIndex = state.selectedIds.indexOf(String(insertBeforeId));
                if (targetIndex !== -1) {
                    state.selectedIds.splice(targetIndex, 0, id);
                } else {
                    state.selectedIds.push(id);
                }
            } else {
                state.selectedIds.push(id);
            }

            saveSelection();
            renderAll();
        }

        function removeFromSelection(id) {
            id = String(id || '');
            state.selectedIds = state.selectedIds.filter(function (selectedId) {
                return selectedId !== id;
            });
            saveSelection();
            renderAll();
        }

        function moveOrInsert(payload, targetId) {
            var id = String(payload.id || '');
            if (!id) {
                return;
            }

            if (payload.kind === 'catalog') {
                addToSelection(id, targetId);
                return;
            }

            if (payload.kind !== 'selected') {
                return;
            }

            var fromIndex = state.selectedIds.indexOf(id);
            if (fromIndex === -1) {
                return;
            }

            state.selectedIds.splice(fromIndex, 1);

            if (targetId) {
                var toIndex = state.selectedIds.indexOf(String(targetId));
                if (toIndex === -1) {
                    state.selectedIds.push(id);
                } else {
                    state.selectedIds.splice(toIndex, 0, id);
                }
            } else {
                state.selectedIds.push(id);
            }

            saveSelection();
            renderAll();
        }

        function renderAll() {
            var filteredItems = getFilteredItems();
            renderCatalogSelect(filteredItems);
            renderLibraryResults(filteredItems);
            renderSelection();
            renderRenderedCards();
            updateCounters(filteredItems);
        }

        function renderCatalogSelect(filteredItems) {
            if (!catalogSelect) {
                return;
            }

            var available = filteredItems.filter(function (item) {
                return state.selectedIds.indexOf(String(item.id)) === -1;
            });

            catalogSelect.innerHTML = '';
            available.forEach(function (item) {
                var option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = item.library_meta ? item.name + ' [' + item.library_meta + ']' : item.name;
                catalogSelect.appendChild(option);
            });
        }

        function renderLibraryResults(filteredItems) {
            libraryResults.innerHTML = '';

            var available = filteredItems.filter(function (item) {
                return state.selectedIds.indexOf(String(item.id)) === -1;
            });

            if (!available.length) {
                var empty = document.createElement('div');
                empty.className = 'hgpc-empty';
                empty.textContent = 'No hay más entradas disponibles con los filtros actuales.';
                libraryResults.appendChild(empty);
                return;
            }

            available.slice(0, 80).forEach(function (item) {
                var article = document.createElement('article');
                article.className = 'hgpc-library-item';
                article.draggable = true;
                article.setAttribute('data-library-id', String(item.id));
                article.innerHTML =
                    '<div class="hgpc-library-item__top">' +
                        '<div>' +
                            '<a class="hgpc-library-item__name" href="' + escapeHtml(item.href || '#') + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(item.name || '') + '</a>' +
                            '<div class="hgpc-library-item__meta">' + escapeHtml(item.library_meta || 'Sin metadatos') + '</div>' +
                        '</div>' +
                        '<div class="hgpc-library-item__buttons">' +
                            '<button type="button" class="hgpc-btn hgpc-btn--primary" data-add-id="' + escapeHtml(item.id) + '">Añadir</button>' +
                        '</div>' +
                    '</div>';
                libraryResults.appendChild(article);
            });

            if (available.length > 80) {
                var note = document.createElement('div');
                note.className = 'hgpc-empty';
                note.textContent = 'Mostrando las primeras 80 coincidencias. Refina filtros o usa la búsqueda para acotar más.';
                libraryResults.appendChild(note);
            }
        }

        function renderSelection() {
            selectionList.innerHTML = '';

            if (!state.selectedIds.length) {
                selectionDrop.textContent = config.emptySelectionText || 'No hay elementos seleccionados.';
                return;
            }

            selectionDrop.textContent = 'Suelta aquí para mandar un elemento al final de la lista.';

            state.selectedIds.forEach(function (id, index) {
                var item = itemsById.get(String(id));
                if (!item) {
                    return;
                }

                var li = document.createElement('li');
                li.className = 'hgpc-selection-item';
                li.draggable = true;
                li.setAttribute('data-selected-id', String(item.id));
                li.innerHTML =
                    '<div class="hgpc-selection-item__top">' +
                        '<div style="display:flex; gap:10px; align-items:flex-start;">' +
                            '<span class="hgpc-selection-item__order">' + String(index + 1) + '</span>' +
                            '<div>' +
                                '<a class="hgpc-selection-item__name" href="' + escapeHtml(item.href || '#') + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(item.name || '') + '</a>' +
                                '<div class="hgpc-selection-item__meta">' + escapeHtml(item.library_meta || '') + '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="hgpc-selection-item__buttons">' +
                            '<button type="button" class="hgpc-btn hgpc-btn--danger" data-remove-id="' + escapeHtml(item.id) + '">Quitar</button>' +
                        '</div>' +
                    '</div>';
                selectionList.appendChild(li);
            });
        }

        function renderRenderedCards() {
            var selectedItems = state.selectedIds.map(function (id) {
                return itemsById.get(String(id));
            }).filter(Boolean);

            renderCardsEl.innerHTML = '';
            renderEmpty.style.display = selectedItems.length ? 'none' : '';

            selectedItems.forEach(function (item) {
                var card = document.createElement('article');
                card.className = 'hgpc-card';

                var mediaHtml = '';
                if (item.image && item.image.src) {
                    mediaHtml =
                        '<div class="hgpc-card__media">' +
                            '<img class="hgpc-card__image" src="' + escapeHtml(item.image.src) + '" alt="' + escapeHtml((item.image.alt || item.name || '')) + '">' +
                        '</div>';
                }

                var chipsHtml = Array.isArray(item.chips) && item.chips.length
                    ? '<div class="hgpc-card__chips">' + item.chips.map(function (chip) {
                        return '<span class="hgpc-chip">' + escapeHtml(chip) + '</span>';
                    }).join('') + '</div>'
                    : '';

                var sectionsHtml = (item.sections || []).map(function (section) {
                    return (
                        '<div class="hgpc-card__section">' +
                            '<h4>' + escapeHtml(section.title || '') + '</h4>' +
                            '<div class="hgpc-card__content">' + String(section.html || '') + '</div>' +
                        '</div>'
                    );
                }).join('');

                card.innerHTML =
                    '<div class="hgpc-card__top">' +
                        '<h3>' + escapeHtml(item.name || '') + '</h3>' +
                        '<a class="hgpc-card__back" href="#hgpc-root">Volver arriba</a>' +
                    '</div>' +
                    mediaHtml +
                    chipsHtml +
                    '<div class="hgpc-card__sections">' + sectionsHtml + '</div>';

                renderCardsEl.appendChild(card);
            });
        }

        function updateCounters(filteredItems) {
            var availableCount = filteredItems.filter(function (item) {
                return state.selectedIds.indexOf(String(item.id)) === -1;
            }).length;

            if (filteredCount) {
                filteredCount.textContent = String(availableCount) + ' coincidencias disponibles';
            }
            if (selectionStatus) {
                selectionStatus.textContent = String(state.selectedIds.length) + ' elementos';
            }
            if (selectedCount) {
                selectedCount.textContent = String(state.selectedIds.length);
            }
        }
    });
})();
