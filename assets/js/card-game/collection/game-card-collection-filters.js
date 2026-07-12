(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.collection = app.collection || {};

    var utils = app.core && app.core.utils ? app.core.utils : null;
    var config = {
        getState: function () { return null; },
        getCatalog: function () { return []; },
        getTypeOrder: function () { return []; },
        collectionGroups: function () { return {}; },
        loadCombatTeams: function () { return { teams: [] }; },
        ensureWorkAssignments: function () { return {}; },
        copyHasLearnedMoves: function () { return false; },
        copyRarity: function () { return 'common'; },
        typeLabel: function (type) { return String(type || ''); },
        typeLabels: function () { return {}; },
        normalizeSearchText: utils && typeof utils.normalizeSearchText === 'function' ? utils.normalizeSearchText : function (value) { return String(value || ''); }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function collectionFilterSets() {
        var inTeam = {};
        var working = {};
        var teams = config.loadCombatTeams() || { teams: [] };
        (teams.teams || []).forEach(function (team) {
            (team.cards || []).forEach(function (id) {
                id = String(id || '');
                if (id) { inTeam[id] = true; }
            });
        });
        Object.keys(config.ensureWorkAssignments() || {}).forEach(function (id) {
            id = String(id || '');
            if (id) { working[id] = true; }
        });
        return { inTeam: inTeam, working: working };
    }

    function cardPassesCollectionFilters(card, groups, filterSets) {
        var state = config.getState();
        var typeLabels = config.typeLabels() || {};
        if (!card || !state) { return false; }
        var group = groups[String(card.card_id)];
        filterSets = filterSets || { inTeam: {}, working: {} };
        if (state.collectionSearch) {
            if (!group) { return false; }
            var searchText = config.normalizeSearchText([
                card.card_name,
                card.card_slug,
                config.typeLabel(card.source_type),
                typeLabels[card.source_type]
            ].join(' '));
            if (searchText.indexOf(state.collectionSearch) === -1) { return false; }
        }
        if (state.collectionRarity !== 'all') {
            var hasRarity = group && group.copies && group.copies.some(function (copy) {
                return config.copyRarity(copy, card) === state.collectionRarity;
            });
            if (!hasRarity && card.card_rarity !== state.collectionRarity) { return false; }
        }
        if (state.collectionOwnedOnly && !groups[String(card.card_id)]) { return false; }
        if (state.collectionMissingOnly && groups[String(card.card_id)]) { return false; }
        if (state.collectionHasMovesOnly) {
            var hasMoves = group && group.copies && group.copies.some(function (copy) {
                return config.copyHasLearnedMoves(copy);
            });
            if (!hasMoves) { return false; }
        }
        if (state.collectionInTeamOnly) {
            var isInTeam = group && group.copies && group.copies.some(function (copy) {
                return !!filterSets.inTeam[String(copy.instanceId || '')];
            });
            if (!isInTeam) { return false; }
        }
        if (state.collectionWorkingOnly) {
            var isWorking = group && group.copies && group.copies.some(function (copy) {
                return !!filterSets.working[String(copy.instanceId || '')];
            });
            if (!isWorking) { return false; }
        }
        return true;
    }

    function albumCategories(groups, filterSets) {
        var state = config.getState();
        var catalog = config.getCatalog() || [];
        var typeOrder = config.getTypeOrder() || [];
        var present = {};
        if (!state) { return []; }
        catalog.forEach(function (card) {
            if (!cardPassesCollectionFilters(card, groups, filterSets)) { return; }
            present[card.source_type] = true;
        });
        var ordered = typeOrder.filter(function (type) {
            return type === 'all' || present[type];
        });
        Object.keys(present).sort().forEach(function (type) {
            if (ordered.indexOf(type) === -1) { ordered.push(type); }
        });
        return ordered.map(function (type) {
            var cards = (type === 'all'
                ? catalog
                : catalog.filter(function (card) { return card.source_type === type; }))
                .filter(function (card) { return cardPassesCollectionFilters(card, groups, filterSets); });
            var owned = cards.filter(function (card) {
                return !!groups[String(card.card_id)];
            }).length;
            return {
                type: type,
                label: type === 'all' ? 'Todos' : config.typeLabel(type),
                total: cards.length,
                owned: owned
            };
        }).filter(function (entry) {
            return entry.type === 'all' || entry.total > 0;
        });
    }

    function ensureAlbumCategory(categories) {
        var state = config.getState();
        if (!state) { return; }
        if (!categories.length) {
            state.albumCategory = 'all';
            return;
        }
        var available = categories.some(function (entry) {
            return entry.type === state.albumCategory;
        });
        if (!available) { state.albumCategory = categories[0].type; }
    }

    var api = Object.freeze({
        configure: configure,
        collectionFilterSets: collectionFilterSets,
        cardPassesCollectionFilters: cardPassesCollectionFilters,
        albumCategories: albumCategories,
        ensureAlbumCategory: ensureAlbumCategory
    });

    app.collection.filters = api;
})(window);
