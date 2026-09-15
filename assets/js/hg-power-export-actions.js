(function () {
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
            return;
        }
        fn();
    }

    function markdownText(value) {
        var box = document.createElement('div');
        box.innerHTML = String(value || '')
            .replace(/<\s*br\s*\/?\s*>/gi, '\n')
            .replace(/<\s*\/p\s*>/gi, '\n\n')
            .replace(/<\s*li[^>]*>/gi, '- ')
            .replace(/<\s*\/li\s*>/gi, '\n');
        return (box.textContent || box.innerText || '')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function selectedItems(config) {
        if (!config || !config.storageKey || !window.localStorage) {
            return [];
        }

        var ids = [];
        try {
            var raw = window.localStorage.getItem(config.storageKey);
            var parsed = raw ? JSON.parse(raw) : [];
            ids = Array.isArray(parsed) ? parsed.map(String) : [];
        } catch (error) {
            ids = [];
        }

        var byId = new Map();
        (Array.isArray(config.items) ? config.items : []).forEach(function (item) {
            byId.set(String(item.id), item);
        });

        return ids.map(function (id) { return byId.get(id); }).filter(Boolean);
    }

    function buildMarkdown(config, items) {
        var lines = [
            '# ' + String(config.catalogTitle || 'Poderes') + ' — selección',
            '',
            'Total: ' + String(items.length),
            ''
        ];

        items.forEach(function (item) {
            lines.push('## ' + String(item.name || 'Sin nombre'), '');

            (Array.isArray(item.chips) ? item.chips : []).forEach(function (chip) {
                var text = String(chip || '').trim();
                if (text) {
                    lines.push('- ' + text);
                }
            });

            (Array.isArray(item.sections) ? item.sections : []).forEach(function (section) {
                var title = String(section.title || '').trim();
                var text = markdownText(section.html || '');
                if (!title || !text) {
                    return;
                }
                lines.push('', '### ' + title, '', text);
            });

            lines.push('');
        });

        return lines.join('\n') + '\n';
    }

    function filenameFor(config) {
        var kind = String(config.kind || 'powers')
            .toLowerCase()
            .replace(/[^a-z0-9_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
        return (kind || 'powers') + '-seleccion.md';
    }

    function downloadText(filename, body) {
        var blob = new Blob([body], { type: 'text/markdown;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = filename;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.setTimeout(function () { URL.revokeObjectURL(url); }, 0);
    }

    ready(function () {
        var config = window.HGPowerCustomPage;
        var builder = document.querySelector('.hg-power-custom .hgpc-builder');
        if (!config || !builder) {
            return;
        }

        var actions = document.querySelector('.hg-power-custom .hgpc-hero__actions');
        if (!actions) {
            return;
        }

        var printLink = actions.querySelector('a[href*="print=1"]');
        if (printLink) {
            printLink.textContent = 'Imprimir selección';
        }

        var markdownLink = actions.querySelector('a[href*="export=md"]');
        if (!markdownLink) {
            return;
        }

        markdownLink.textContent = 'Descargar selección (.md)';
        markdownLink.removeAttribute('href');
        markdownLink.setAttribute('role', 'button');
        markdownLink.setAttribute('tabindex', '0');

        function refreshState() {
            var count = selectedItems(config).length;
            markdownLink.classList.toggle('is-disabled', count === 0);
            markdownLink.setAttribute('aria-disabled', count === 0 ? 'true' : 'false');
        }

        function downloadSelection(event) {
            if (event) {
                event.preventDefault();
            }
            var items = selectedItems(config);
            if (!items.length) {
                return;
            }
            downloadText(filenameFor(config), buildMarkdown(config, items));
        }

        markdownLink.addEventListener('click', downloadSelection);
        markdownLink.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                downloadSelection(event);
            }
        });

        var selectedCount = document.getElementById('hgpc-selected-count');
        if (selectedCount && window.MutationObserver) {
            new MutationObserver(refreshState).observe(selectedCount, { childList: true, characterData: true, subtree: true });
        }

        refreshState();
    });
})();
