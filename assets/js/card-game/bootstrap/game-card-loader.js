(function (global) {
    'use strict';

    var root = document.querySelector('.hg-cards');
    var app = global.HGCardGame || {};

    app.core = app.core || {};
    app.data = app.data || {};
    app.collection = app.collection || {};
    app.shop = app.shop || {};
    app.memory = app.memory || {};
    app.teams = app.teams || {};
    app.cards = app.cards || {};
    app.combat = app.combat || {};
    app.bootstrap = app.bootstrap || {};

    app.bootstrap.env = {
        basePath: root ? (root.getAttribute('data-base-path') || '/games/card-game') : '/games/card-game',
        storageScope: root ? (root.getAttribute('data-storage-scope') || 'prod') : 'prod',
        mobile: !!(root && root.getAttribute('data-mobile') === '1')
    };

    global.HGCardGame = app;
})(window);
