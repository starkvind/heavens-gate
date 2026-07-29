(function () {
    const button = document.querySelector('[data-mobile-menu-toggle]');
    const menu = document.getElementById('hgMobileMenu');
    if (!button || !menu) {
        return;
    }

    button.addEventListener('click', function () {
        const willOpen = menu.hasAttribute('hidden');
        if (willOpen) {
            menu.removeAttribute('hidden');
        } else {
            menu.setAttribute('hidden', '');
        }
        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });
})();

(function () {
    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    }

    function initPaginatedList(list) {
        const items = Array.from(list.querySelectorAll('[data-mobile-item]'));
        if (!items.length || list.dataset.mobilePaginatedReady === '1') return;
        list.dataset.mobilePaginatedReady = '1';

        const pageSize = Math.max(1, parseInt(list.dataset.pageSize || '20', 10) || 20);
        const alwaysSearch = list.dataset.mobileSearch === '1';
        const filterSelect = list.querySelector('[data-mobile-list-filter]');
        if (items.length <= pageSize && !alwaysSearch && !filterSelect) return;

        let page = 1;
        let query = '';
        let filter = '';

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

        function itemText(item) {
            return normalize(item.dataset.mobileSearch || item.textContent || '');
        }

        function render() {
            const needle = normalize(query.trim());
            const matched = items.filter(item => {
                const matchesSearch = needle === '' || itemText(item).includes(needle);
                const matchesFilter = filter === '' || normalize(item.dataset.mobileFilterValue) === filter;
                return matchesSearch && matchesFilter;
            });
            const totalPages = Math.max(1, Math.ceil(matched.length / pageSize));
            if (page > totalPages) page = totalPages;
            const start = (page - 1) * pageSize;
            const end = start + pageSize;
            const visible = new Set(matched.slice(start, end));

            items.forEach(item => {
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
                : `${start + 1}-${Math.min(end, matched.length)} de ${matched.length}`;
        }

        input.addEventListener('input', function () {
            query = input.value;
            page = 1;
            render();
        });
        if (filterSelect) {
            filterSelect.addEventListener('change', function () {
                filter = normalize(filterSelect.value);
                page = 1;
                render();
            });
        }
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

        render();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-mobile-paginated]').forEach(initPaginatedList);
    });
})();
(function () {
    const buttons = document.querySelectorAll('[data-mobile-theme]');
    if (!buttons.length) return;
    const themeColors = { classic: '#050150', violet: '#21113d', light: '#f6f7fb', 'power-save': '#000000' };
    const body = document.body;
    const themeMeta = document.querySelector('[data-mobile-theme-color]');
    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            const theme = themeColors[button.dataset.mobileTheme] ? button.dataset.mobileTheme : 'classic';
            Object.keys(themeColors).forEach(function (name) { body.classList.remove(`theme-${name}`); });
            body.classList.add(`theme-${theme}`);
            buttons.forEach(function (item) {
                const active = item === button;
                item.classList.toggle('is-active', active);
                if (active) item.setAttribute('aria-current', 'true'); else item.removeAttribute('aria-current');
            });
            if (themeMeta) themeMeta.setAttribute('content', themeColors[theme]);
            document.cookie = `hg_mobile_theme=${encodeURIComponent(theme)}; path=/; max-age=31536000; SameSite=Lax`;
        });
    });
})();
(function () {
    const backTop = document.querySelector('[data-mobile-back-top]');
    if (!backTop) return;

    const syncBackTop = () => backTop.classList.toggle('is-visible', window.scrollY > 480);
    window.addEventListener('scroll', syncBackTop, { passive: true });
    syncBackTop();

    backTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
