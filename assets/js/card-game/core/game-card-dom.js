(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.core = app.core || {};

    function findRoot() {
        return document.querySelector('.hg-cards');
    }

    function all(selector) {
        return Array.prototype.slice.call(document.querySelectorAll(selector));
    }

    function one(selector) {
        return document.querySelector(selector);
    }

    function id(value) {
        return document.getElementById(value);
    }

    function collectElements() {
        return {
            packButtons: all('[data-pack-kind]'),
            shopItems: all('[data-shop-pack], [data-shop-material], [data-shop-exchange-remorias], [data-shop-daily-gift]'),
            shopButtons: all('[data-shop-buy-pack]'),
            packStocks: all('[data-pack-stock]'),
            packGrid: one('[data-pack-grid]'),
            packOpenAll: one('[data-pack-open-all]'),
            mnemonesCounters: all('[data-mnemones-counter]'),
            remoriasCounters: all('[data-remorias-counter]'),
            packResultsSection: id('hgPackResultsSection'),
            packResults: id('hgPackResults'),
            statusText: id('hgStatusText'),
            uniqueCounter: id('hgUniqueCounter'),
            totalCopiesCounter: id('hgTotalCopiesCounter'),
            dailyPacksCounter: id('hgDailyPacksCounter'),
            exportBtn: id('hgExportCollection'),
            importFile: id('hgImportFile'),
            resetBtn: id('hgResetCollection'),
            bulkSellRarity: id('hgBulkSellRarity'),
            bulkSellBtn: id('hgBulkSellButton'),
            bulkSellPreview: id('hgBulkSellPreview'),
            bulkSellKeepBest: id('hgBulkSellKeepBest'),
            workSummary: one('[data-work-summary]'),
            workList: one('[data-work-list]'),
            workClaimBtn: one('[data-work-claim]'),
            packSection: one('.hg-pack-section'),
            shopSection: one('.hg-shop-section'),
            collectionBrowser: one('.hg-collection-browser'),
            collectionTools: one('.hg-collection-tools'),
            workBench: one('.hg-workbench'),
            collectionTable: id('hgCollectionTable'),
            albumTabs: one('[data-album-tabs]'),
            albumGrid: one('[data-album-grid]'),
            collectionModeButtons: all('[data-collection-mode]'),
            collectionPageSize: one('[data-collection-page-size]'),
            collectionOwnedFilter: one('[data-collection-owned-filter]'),
            collectionMissingFilter: one('[data-collection-missing-filter]'),
            collectionHasMovesFilter: one('[data-collection-has-moves-filter]'),
            collectionInTeamFilter: one('[data-collection-in-team-filter]'),
            collectionWorkingFilter: one('[data-collection-working-filter]'),
            collectionNameFilter: one('[data-collection-name-filter]'),
            collectionRarityFilter: one('[data-collection-rarity-filter]'),
            collectionTypeFilter: one('[data-collection-type-filter]'),
            collectionViews: all('[data-collection-view]'),
            collectionPagers: all('[data-collection-pager]'),
            mobileTabs: all('[data-mobile-panel-tab]'),
            mobilePanels: all('[data-mobile-panel]'),
            combatScreenTabs: all('[data-combat-screen-tab]'),
            combatScreenPanels: all('[data-combat-screen]'),
            combatModeButtons: all('[data-combat-mode]'),
            dailyBossSummaries: all('[data-daily-boss-summary]'),
            combatDifficultyWraps: all('[data-combat-difficulty-wrap]'),
            combatTeamSelects: all('[data-combat-team-select], [data-combat-team-select-mirror]'),
            combatTeamSelect: one('[data-combat-team-select]'),
            combatTeamNames: all('[data-combat-team-name]'),
            combatTeamPreviews: all('[data-combat-team-preview]'),
            combatProfileNames: all('[data-combat-profile-name]'),
            combatTeamSlots: one('[data-combat-team-slots]'),
            combatSaveTeam: one('[data-combat-save-team]'),
            combatClearTeam: one('[data-combat-clear-team]'),
            combatAutoTeam: one('[data-combat-auto-team]'),
            combatOnlyReady: one('[data-combat-only-ready]'),
            combatRarityFilter: one('[data-combat-rarity-filter]'),
            combatTypeFilter: one('[data-combat-type-filter]'),
            combatSort: one('[data-combat-sort]'),
            combatCardList: one('[data-combat-card-list]'),
            combatDifficulty: one('[data-combat-difficulty]'),
            combatSetups: all('.hg-combat-setup'),
            combatStart: one('[data-combat-start]'),
            combatActions: all('[data-combat-action]'),
            combatExtraActionSlots: all('[data-combat-extra-action-slot]'),
            combatCommandViews: all('[data-combat-command-view]'),
            combatCommandButtons: all('[data-combat-command]'),
            combatCommandBackButtons: all('[data-combat-command-back]'),
            combatBench: one('[data-combat-bench]'),
            combatLog: one('[data-combat-log]'),
            combatMessage: one('[data-combat-message]'),
            combatPlayerCard: one('[data-combat-player-card]'),
            combatEnemyCard: one('[data-combat-enemy-card]'),
            combatEnemyRival: one('[data-combat-enemy-rival]'),
            combatEnemyRivalAvatar: one('[data-combat-enemy-rival-avatar]'),
            combatEnemyRivalName: one('[data-combat-enemy-rival-name]'),
            combatEnemyRivalTitle: one('[data-combat-enemy-rival-title]'),
            combatPlayerName: one('[data-combat-player-name]'),
            combatEnemyName: one('[data-combat-enemy-name]'),
            combatPlayerHp: one('[data-combat-player-hp]'),
            combatEnemyHp: one('[data-combat-enemy-hp]'),
            combatPlayerShields: one('[data-combat-player-shields]'),
            combatEnemyShields: one('[data-combat-enemy-shields]'),
            combatPlayerHpBar: one('[data-combat-player-hp-bar]'),
            combatEnemyHpBar: one('[data-combat-enemy-hp-bar]'),
            combatPlayerAtk: one('[data-combat-player-atk]'),
            combatPlayerDef: one('[data-combat-player-def]'),
            combatEnemyAtk: one('[data-combat-enemy-atk]'),
            combatEnemyDef: one('[data-combat-enemy-def]')
        };
    }

    app.core.dom = Object.freeze({
        findRoot: findRoot,
        collectElements: collectElements
    });
})(window);
