(function (w) {
    'use strict';

    function init(root) {
        var scope = (root && root.querySelectorAll) ? root : w.document;
        var wraps = scope.querySelectorAll('.hg-tabs');

        wraps.forEach(function (tabsWrap) {
            if (tabsWrap.dataset.hgTabsReady === '1') return;

            var tabs = Array.from(tabsWrap.querySelectorAll('.hgTabBtn'));
            if (!tabs.length) return;

            var panels = [];
            var n = tabsWrap.nextElementSibling;
            while (n && n.classList && n.classList.contains('hg-tab-panel')) {
                panels.push(n);
                n = n.nextElementSibling;
            }
            if (!panels.length) return;

            var panelKeys = new Set(
                panels
                    .map(function (p) { return p.dataset.tab || ''; })
                    .filter(function (k) { return k !== ''; })
            );
            if (!panelKeys.size) return;

            function activate(key) {
                if (!panelKeys.has(key)) return;
                panels.forEach(function (p) { p.classList.toggle('active', p.dataset.tab === key); });
                tabs.forEach(function (b) { b.classList.toggle('active', b.dataset.tab === key); });
            }

            var activeBtn = tabs.find(function (b) { return b.classList.contains('active') && panelKeys.has(b.dataset.tab || ''); });
            var activePanel = panels.find(function (p) { return p.classList.contains('active') && panelKeys.has(p.dataset.tab || ''); });
            var firstValidTab = tabs.find(function (b) { return panelKeys.has(b.dataset.tab || ''); });

            var initialKey = '';
            if (activeBtn) initialKey = activeBtn.dataset.tab || '';
            else if (activePanel) initialKey = activePanel.dataset.tab || '';
            else if (firstValidTab) initialKey = firstValidTab.dataset.tab || '';

            if (initialKey) activate(initialKey);

            tabs.forEach(function (b) {
                b.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    activate(b.dataset.tab || '');
                });
            });

            tabsWrap.dataset.hgTabsReady = '1';
        });
    }

    w.hgTabs = {
        init: init
    };

    if (w.document.readyState === 'loading') {
        w.document.addEventListener('DOMContentLoaded', function () { init(w.document); });
    } else {
        init(w.document);
    }
})(window);
