(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.collection = app.collection || {};

    var config = {
        getCollection: function () { return null; },
        getCatalogById: function () { return {}; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function collectionGroups() {
        var groups = {};
        var collection = typeof config.getCollection === 'function' ? config.getCollection() : null;
        var catalogById = typeof config.getCatalogById === 'function' ? (config.getCatalogById() || {}) : {};
        var owned = collection && Array.isArray(collection.ownedCards) ? collection.ownedCards : [];
        owned.forEach(function (copy) {
            var cardId = String(copy.cardId || '');
            var card = catalogById[cardId];
            if (!card) { return; }
            if (!groups[cardId]) { groups[cardId] = { catalog: card, copies: [] }; }
            groups[cardId].copies.push(copy);
        });
        return groups;
    }

    function uniqueCollectionCount() {
        return Object.keys(collectionGroups()).length;
    }

    var api = Object.freeze({
        configure: configure,
        collectionGroups: collectionGroups,
        uniqueCollectionCount: uniqueCollectionCount
    });

    app.collection.core = api;
})(window);
