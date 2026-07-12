(function (global) {
    'use strict';

    var root = document.querySelector('.hg-cards');
    var app = global.HGCardGame || {};
    var view = root ? (root.getAttribute('data-view') || 'gacha') : 'gacha';
    var isMobile = !!(root && root.getAttribute('data-mobile') === '1');

    app.core = app.core || {};
    app.data = app.data || {};
    app.packs = app.packs || {};
    app.collection = app.collection || {};
    app.shop = app.shop || {};
    app.memory = app.memory || {};
    app.teams = app.teams || {};
    app.cards = app.cards || {};
    app.combat = app.combat || {};
    app.bootstrap = app.bootstrap || {};

    app.bootstrap.env = Object.freeze({
        root: root,
        view: view,
        mobile: isMobile,
        isAdmin: !!(root && root.getAttribute('data-is-admin') === '1'),
        basePath: root ? (root.getAttribute('data-base-path') || '/games/card-game') : '/games/card-game',
        mobileUrl: root ? (root.getAttribute('data-mobile-url') || '') : '',
        storageScope: root ? (root.getAttribute('data-storage-scope') || 'prod') : 'prod',
        catalogUrl: root ? (root.getAttribute('data-catalog-url') || '/api/game_cards.php') : '/api/game_cards.php',
        runtimeMode: root ? (root.getAttribute('data-runtime-mode') || 'hybrid') : 'hybrid'
    });

    app.bootstrap.state = app.bootstrap.state || {
        bootOrder: [],
        viewModules: [],
        ready: false
    };

    app.bootstrap.track = function (moduleName) {
        moduleName = String(moduleName || '').trim();
        if (!moduleName) { return app.bootstrap.state.bootOrder.slice(); }
        if (app.bootstrap.state.bootOrder.indexOf(moduleName) === -1) {
            app.bootstrap.state.bootOrder.push(moduleName);
        }
        return app.bootstrap.state.bootOrder.slice();
    };

    app.bootstrap.resolveViewModules = function () {
        var modules = ['core', 'data', 'packs', 'shop', 'bootstrap'];
        if (isMobile || view === 'collection' || view === 'combat') {
            modules = modules.concat(['collection', 'memory', 'cards', 'teams']);
        }
        if (isMobile || view === 'combat') {
            modules.push('combat');
        }
        return modules;
    };

    app.bootstrap.state.viewModules = app.bootstrap.resolveViewModules();
    app.bootstrap.track('bootstrap/loader');

    global.HGCardGame = app;
})(window);
