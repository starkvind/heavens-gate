(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.core = app.core || {};

    function createShellState(root) {
        return {
            view: root ? (root.getAttribute('data-view') || 'gacha') : 'gacha',
            mobile: !!(root && root.getAttribute('data-mobile') === '1'),
            isAdmin: !!(root && root.getAttribute('data-is-admin') === '1')
        };
    }

    function createCollectionUiState(root) {
        return {
            albumCategory: 'all',
            collectionOwnedOnly: false,
            collectionMissingOnly: false,
            collectionHasMovesOnly: false,
            collectionInTeamOnly: false,
            collectionWorkingOnly: false,
            collectionSearch: '',
            collectionRarity: 'all',
            collectionMode: 'album',
            collectionPage: 1,
            collectionPageSize: root && root.getAttribute('data-mobile') === '1' ? 20 : 24
        };
    }

    function createCombatUiState() {
        return {
            activeCombatTeam: 0,
            draftCombatTeam: [],
            activeCombatScreen: 'battle',
            combatMode: 'training',
            combatAnimating: false,
            combatCommandView: 'root',
            combatRarityFilter: 'all',
            combatTypeFilter: 'all',
            combatSort: 'quality'
        };
    }

    function createDomainState() {
        return {
            combat: null,
            combatTeams: null,
            combatProfile: null,
            dailyBoss: null,
            catalog: [],
            catalogById: {},
            shopProducts: [],
            shopState: null,
            rewardsTimer: null,
            workTimer: null,
            collection: null,
            table: null,
            rulesCatalog: null
        };
    }

    function createLegacyState(root) {
        return Object.assign(
            {},
            createShellState(root),
            createCollectionUiState(root),
            createCombatUiState(),
            createDomainState()
        );
    }

    app.core.state = Object.freeze({
        createShellState: createShellState,
        createCollectionUiState: createCollectionUiState,
        createCombatUiState: createCombatUiState,
        createDomainState: createDomainState,
        createLegacyState: createLegacyState
    });
})(window);
