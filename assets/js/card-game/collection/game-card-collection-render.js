(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.collection = app.collection || {};

    var config = {
        getState: function () { return null; },
        getEls: function () { return null; },
        escapeHtml: function (value) { return String(value || ''); },
        typeChipHtml: function () { return ''; },
        uiText: function (key, fallback) { return fallback || key; },
        clampInt: function (value, fallback) {
            var n = Number(value);
            if (!Number.isFinite(n)) { return fallback; }
            return Math.round(n);
        },
        normalizePageSize: function (value) { return Number(value || 24) || 24; },
        onPageChange: function () {},
        onAlbumCategoryChange: function () {}
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function pageBounds(total) {
        var state = config.getState();
        var pageSize = config.normalizePageSize(state.collectionPageSize);
        var totalPages = Math.max(1, Math.ceil(total / pageSize));
        state.collectionPage = Math.max(1, Math.min(totalPages, config.clampInt(state.collectionPage, 1)));
        var start = (state.collectionPage - 1) * pageSize;
        var end = Math.min(total, start + pageSize);
        return { start: start, end: end, pageSize: pageSize, totalPages: totalPages };
    }

    function renderPagination(total) {
        var state = config.getState();
        var els = config.getEls();
        var bounds = pageBounds(total);
        (els.collectionPagers || []).forEach(function (pager) {
            pager.innerHTML = '';
            if (total <= 0) { return; }

            var prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'hg-pagination__button';
            prev.textContent = '<';
            prev.disabled = state.collectionPage <= 1;
            prev.setAttribute('aria-label', config.uiText('collection.page_previous', 'Página anterior'));
            prev.addEventListener('click', function () {
                state.collectionPage -= 1;
                config.onPageChange();
            });

            var label = document.createElement('span');
            label.className = 'hg-pagination__label';
            label.textContent = (bounds.start + 1) + '-' + bounds.end + ' de ' + total + ' · Página ' + state.collectionPage + ' / ' + bounds.totalPages;

            var next = document.createElement('button');
            next.type = 'button';
            next.className = 'hg-pagination__button';
            next.textContent = '>';
            next.disabled = state.collectionPage >= bounds.totalPages;
            next.setAttribute('aria-label', config.uiText('collection.page_next', 'Página siguiente'));
            next.addEventListener('click', function () {
                state.collectionPage += 1;
                config.onPageChange();
            });

            pager.appendChild(prev);
            pager.appendChild(label);
            pager.appendChild(next);
        });
        return bounds;
    }

    function renderAlbumTabs(categories) {
        var state = config.getState();
        var els = config.getEls();
        if (!els.albumTabs) { return; }
        els.albumTabs.innerHTML = '';
        categories.forEach(function (entry) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'hg-album-tabs__button';
            if (entry.type === state.albumCategory) { button.className += ' is-active'; }
            button.setAttribute('data-album-category', entry.type);
            button.setAttribute('role', 'tab');
            button.setAttribute('aria-selected', entry.type === state.albumCategory ? 'true' : 'false');
            var labelHtml = entry.type === 'all'
                ? '<span class="hg-album-tabs__label-text">' + config.escapeHtml(entry.label) + '</span>'
                : config.typeChipHtml(entry.type, 'hg-album-tabs__label-text');
            button.innerHTML = '<span class="hg-album-tabs__label">' + labelHtml + '</span><strong>' + entry.owned + ' / ' + entry.total + '</strong>';
            button.addEventListener('click', function () {
                state.albumCategory = entry.type;
                state.collectionPage = 1;
                config.onAlbumCategoryChange();
            });
            els.albumTabs.appendChild(button);
        });
    }

    function renderCollectionTypeFilter(categories) {
        var state = config.getState();
        var els = config.getEls();
        if (!els.collectionTypeFilter) { return; }
        var signature = categories.map(function (entry) {
            return entry.type + ':' + entry.owned + ':' + entry.total;
        }).join('|');
        if (els.collectionTypeFilter.getAttribute('data-options-signature') !== signature) {
            els.collectionTypeFilter.innerHTML = categories.map(function (entry) {
                return '<option value="' + config.escapeHtml(entry.type) + '">' +
                    config.escapeHtml(entry.label) + ' (' + entry.owned + '/' + entry.total + ')' +
                    '</option>';
            }).join('');
            els.collectionTypeFilter.setAttribute('data-options-signature', signature);
        }
        els.collectionTypeFilter.value = state.albumCategory;
    }

    var api = Object.freeze({
        configure: configure,
        pageBounds: pageBounds,
        renderPagination: renderPagination,
        renderAlbumTabs: renderAlbumTabs,
        renderCollectionTypeFilter: renderCollectionTypeFilter
    });

    app.collection.render = api;
})(window);
