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
    const themeColors = { classic: '#050150', violet: '#21113d', 'violet-pearl': '#f3eef9', light: '#f6f7fb', 'power-save': '#000000' };
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

(function () {
    const lightbox = document.querySelector('[data-mobile-gallery-lightbox]');
    const thumbs = Array.from(document.querySelectorAll('[data-mobile-gallery-thumb]'));
    if (!lightbox || !thumbs.length) return;

    const image = lightbox.querySelector('[data-mobile-gallery-full]');
    const title = lightbox.querySelector('[data-mobile-gallery-title]');
    const bbcode = lightbox.querySelector('[data-mobile-gallery-bbcode]');
    let current = 0;

    function absoluteUrl(src) {
        try {
            return new URL(src, window.location.origin).toString();
        } catch (error) {
            return src;
        }
    }

    function show(index) {
        if (index < 0) index = thumbs.length - 1;
        if (index >= thumbs.length) index = 0;
        current = index;
        const node = thumbs[current];
        const src = node.getAttribute('data-full') || '';
        const label = node.getAttribute('data-title') || '';
        if (image) {
            image.src = src;
            image.alt = label;
        }
        if (title) title.textContent = label;
        if (bbcode) bbcode.value = '[img width=700]' + absoluteUrl(src) + '[/img]';
        lightbox.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function close() {
        lightbox.hidden = true;
        if (image) image.src = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (event) {
        const thumb = event.target.closest('[data-mobile-gallery-thumb]');
        if (thumb) {
            const index = thumbs.indexOf(thumb);
            if (index >= 0) show(index);
            return;
        }
        if (event.target.closest('[data-mobile-gallery-close]')) close();
        if (event.target.closest('[data-mobile-gallery-prev]')) show(current - 1);
        if (event.target.closest('[data-mobile-gallery-next]')) show(current + 1);
        if (event.target.closest('[data-mobile-gallery-copy]') && bbcode) {
            bbcode.select();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(bbcode.value).catch(function () {});
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (lightbox.hidden) return;
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowLeft') show(current - 1);
        if (event.key === 'ArrowRight') show(current + 1);
    });
})();

(function () {
    const player = document.querySelector('[data-mobile-ost-player]');
    if (!player) return;

    const frame = player.querySelector('[data-mobile-ost-frame]');
    const title = player.querySelector('[data-mobile-ost-player-title]');
    const subtitle = player.querySelector('[data-mobile-ost-player-subtitle]');
    const external = player.querySelector('[data-mobile-ost-external]');

    function youtubeId(value) {
        const text = String(value || '').trim();
        return /^[A-Za-z0-9_-]{11}$/.test(text) ? text : '';
    }

    document.addEventListener('click', function (event) {
        const play = event.target.closest('[data-mobile-ost-play]');
        if (play) {
            const id = youtubeId(play.getAttribute('data-youtube-id'));
            if (!id || !frame) return;
            frame.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0';
            if (title) title.textContent = play.getAttribute('data-title') || 'Reproductor';
            if (subtitle) subtitle.textContent = play.getAttribute('data-subtitle') || '';
            if (external) {
                external.href = play.getAttribute('data-watch-url')
                    || ('https://www.youtube.com/watch?v=' + encodeURIComponent(id));
            }
            player.hidden = false;
            player.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        if (event.target.closest('[data-mobile-ost-close]')) {
            if (frame) frame.src = '';
            player.hidden = true;
        }
    });
})();

(function () {
    const root = document.querySelector('[data-mobile-search-recent]');
    if (!root) return;

    const items = root.querySelector('[data-mobile-search-recent-items]');
    if (!items) return;

    const storageKey = 'hg-search-recent';
    const current = {
        q: root.dataset.currentQ || '',
        section: root.dataset.currentSection || 'all',
        label: root.dataset.currentLabel || root.dataset.currentSection || 'all'
    };

    let recent = [];
    try {
        recent = JSON.parse(localStorage.getItem(storageKey) || '[]');
    } catch (error) {
        recent = [];
    }
    if (!Array.isArray(recent)) recent = [];

    if (root.dataset.storeCurrent === '1' && current.q.length > 2) {
        recent = recent.filter(function (entry) {
            return !(entry && entry.q === current.q && entry.section === current.section);
        });
        recent.unshift(current);
        recent = recent.slice(0, 6);
        try {
            localStorage.setItem(storageKey, JSON.stringify(recent));
        } catch (error) {}
    }

    const start = root.dataset.skipCurrent === '1' ? 1 : 0;
    recent.slice(start, start + 5).forEach(function (entry) {
        if (!entry || !entry.q || !entry.section) return;
        const link = document.createElement('a');
        link.href = '/search/results?q=' + encodeURIComponent(entry.q)
            + '&section=' + encodeURIComponent(entry.section)
            + '&view=mobile';
        link.textContent = entry.q + ' - ' + (entry.label || entry.section);
        items.appendChild(link);
    });

    if (items.children.length > 0) root.classList.add('is-ready');
})();

(function () {
    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-mobile-copy]');
        if (!button) return;
        const value = button.getAttribute('data-mobile-copy') || '';
        if (!value) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).catch(function () {});
        }
        const original = button.textContent;
        button.textContent = 'Copiado';
        window.setTimeout(function () {
            button.textContent = original || 'Copiar';
        }, 1200);
    });
})();

(function () {
    const button = document.querySelector('[data-mobile-copy-organization]');
    const status = document.querySelector('[data-mobile-copy-organization-status]');
    const source = document.querySelector('[data-mobile-organization-json]');
    if (!button || !source) return;

    let data = null;
    try {
        data = JSON.parse(source.textContent || '{}');
    } catch (error) {
        data = null;
    }
    if (!data) return;

    function plainText(html) {
        const node = document.createElement('div');
        node.innerHTML = html || '';
        return node.innerText.replace(/\n{3,}/g, '\n\n').trim();
    }

    function characterLines(character) {
        const lines = ['- **Nombre completo:** ' + String(character.name || '')];
        const alias = String(character.alias || '').trim();
        const garouName = String(character.garou_name || '').trim();
        const description = plainText(character.description);
        const state = String(character.status || '').trim();
        if (alias) lines.push('  - **Alias:** ' + alias);
        if (garouName) lines.push('  - **Nombre Garou:** ' + garouName);
        if (description) lines.push('  - **Descripción:** ' + description.replace(/\n/g, '\n    '));
        if (state && state.toLocaleLowerCase() !== 'en activo') lines.push('  - [' + state + ']');
        return lines;
    }

    function buildMarkdown() {
        let lines = ['# ' + String(data.name || '')];
        const description = plainText(data.description);
        if (description) lines.push('', description);

        const members = Array.isArray(data.members) ? data.members : [];
        if (members.length) {
            lines.push('', '## Miembros sin grupo asociado', '');
            members.forEach(function (character) {
                lines = lines.concat(characterLines(character));
            });
        }

        const groups = Array.isArray(data.groups) ? data.groups : [];
        if (groups.length) {
            lines.push('', '## Grupos');
            groups.forEach(function (group) {
                lines.push('', '### ' + String(group.name || ''));
                const groupDescription = plainText(group.description);
                if (groupDescription) lines.push('', groupDescription);
                const groupMembers = Array.isArray(group.members) ? group.members : [];
                if (groupMembers.length) {
                    lines.push('', '#### Miembros', '');
                    groupMembers.forEach(function (character) {
                        lines = lines.concat(characterLines(character));
                    });
                }
            });
        }
        return lines.join('\n').trim();
    }

    function copy(value) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(value);
        }
        const area = document.createElement('textarea');
        area.value = value;
        area.style.position = 'fixed';
        area.style.left = '-9999px';
        document.body.appendChild(area);
        area.select();
        const copied = document.execCommand('copy');
        document.body.removeChild(area);
        return copied ? Promise.resolve() : Promise.reject(new Error('copy failed'));
    }

    button.addEventListener('click', function () {
        copy(buildMarkdown()).then(function () {
            if (!status) return;
            status.textContent = 'Markdown copiado al portapapeles.';
            status.className = 'hg-mobile-copy-status is-ok';
        }).catch(function () {
            if (!status) return;
            status.textContent = 'No se pudo copiar automáticamente.';
            status.className = 'hg-mobile-copy-status is-error';
        });
    });
})();
