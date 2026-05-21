(function () {
    'use strict';

    var STORAGE_KEY = 'hg_card_collection_v1';
    var FREE_REWARDS_KEY = 'hg_card_free_rewards_v1';
    var COLLECTION_MODE_KEY = 'hg_card_collection_mode_v1';
    var COLLECTION_PAGE_SIZE_KEY = 'hg_card_collection_page_size_v1';
    var COMBAT_TEAMS_KEY = 'hg_card_combat_teams_v1';
    var COMBAT_PROFILE_KEY = 'hg_card_combat_profile_v1';
    var STARTING_MNEMONES = 500;
    var MAX_MNEMONES = 9999999;
    var PACK_SIZE = 5;
    var MAX_PACK_STOCK = 99;
    var COMBAT_SOUNDS = {
        attack: ['/sounds/ui/attack1.ogg', '/sounds/ui/attack2.ogg'],
        defend: '/sounds/ui/heal.ogg',
        switch: '',
        damage: ['/sounds/ui/hit1.ogg', '/sounds/ui/hit2.ogg'],
        victory: '',
        defeat: '/sounds/ui/card_defeat.ogg'
    };
    var COMBAT_ATTACK_MS = 420;
    var COMBAT_DEFEND_MS = 560;
    var COMBAT_DEFEAT_MS = 760;
    var COMBAT_ENTRY_MS = 620;
    var COMBAT_TURN_GAP_MS = 80;
    var COMBAT_HIT_SOUND_DELAY_MS = 150;
    var DAILY_FREE_PACK_CAP = 3;
    var SHOP_QUANTITIES = [1, 5, 20];
    var PACK_KINDS = ['standard', 'echoes', 'magic', 'characters', 'lineage', 'essence', 'powers', 'chronicles', 'relics', 'omens', 'gaian'];
    var PACK_PRICES = {
        standard: 50,
        echoes: 90,
        chronicles: 140,
        relics: 160,
        magic: 220,
        characters: 240,
        powers: 240,
        essence: 300,
        lineage: 420,
        omens: 650,
        gaian: 2000
    };
    var RECYCLE_VALUES = { common: 5, unusual: 20, rare: 50, epic: 250, legendary: 500, mythic: 1000 };
    var WORK_MAX_ASSIGNMENTS = 5;
    var WORK_MIN_DURATION_MS = 24 * 60 * 60 * 1000;
    var WORK_RARITY_BASE = { common: 1, unusual: 2, rare: 4, epic: 7, legendary: 11, mythic: 18 };
    var RARITY_UPGRADE_REQUIRED = 5;
    var RARITY_UPGRADE_MIN_QUALITY = 50;
    var RARITY_UPGRADE_MULTIPLIERS = [1, 1.2, 1.5, 2];
    var QUALITY_UPGRADE_MAX_SLOTS = 5;
    var UPGRADE_COST_BY_RARITY = { common: 100, unusual: 300, rare: 900, epic: 2000, legendary: 8000, mythic: 24000 };
    var RARITY_UPGRADE_MATERIALS = { epic: 'icarus_vial', legendary: 'stigma_orb', mythic: 'babylon_shred' };
    var UPGRADE_MATERIALS = {
        icarus_vial: { label: 'Vial de \u00cdcaro', price: 10000, rarity: 'epic', description: 'Necesario para evolucionar de Raro a \u00c9pico.' },
        stigma_orb: { label: 'Orbe de Estigma', price: 50000, rarity: 'legendary', description: 'Necesario para evolucionar de \u00c9pico a Legendario.' },
        babylon_shred: { label: 'Retal de Babilonia', price: 125000, rarity: 'mythic', description: 'Necesario para evolucionar de Legendario a M\u00edtico.' }
    };
    var RARITY_STAT_RANGES = {
        common: [10, 40],
        unusual: [30, 60],
        rare: [50, 85],
        epic: [70, 105],
        legendary: [90, 125],
        mythic: [115, 155]
    };
    var RARITY_ORDER = ['common', 'unusual', 'rare', 'epic', 'legendary', 'mythic'];
    var RARITY_WEIGHTS = { common: 64, unusual: 22, rare: 9, epic: 3.5, legendary: 1.2, mythic: 0.3 };
    var PACK_RARITY_WEIGHTS = {
        standard: RARITY_WEIGHTS,
        magic: { common: 20, unusual: 38, rare: 24, epic: 11, legendary: 5, mythic: 2 },
        echoes: { common: 82, unusual: 18, rare: 0, epic: 0, legendary: 0, mythic: 0 },
        characters: RARITY_WEIGHTS,
        lineage: { common: 46, unusual: 30, rare: 16, epic: 6, legendary: 1.6, mythic: 0.4 },
        essence: { common: 52, unusual: 29, rare: 13, epic: 4.2, legendary: 1.4, mythic: 0.4 },
        powers: RARITY_WEIGHTS,
        chronicles: { common: 58, unusual: 26, rare: 11, epic: 3.8, legendary: 1, mythic: 0.2 },
        relics: { common: 55, unusual: 28, rare: 12, epic: 3.8, legendary: 1, mythic: 0.2 },
        omens: { common: 0, unusual: 0, rare: 70, epic: 21, legendary: 7, mythic: 2 },
        gaian: { common: 0, unusual: 0, rare: 0, epic: 55, legendary: 30, mythic: 15 }
    };
    var PACK_LABELS = {
        standard: 'Sobre mnemónico',
        echoes: 'Sobre de ecos',
        magic: 'Sobre mágico',
        characters: 'Sobre de personajes',
        lineage: 'Sobre de linaje',
        essence: 'Sobre de esencia',
        powers: 'Sobre arcano',
        chronicles: 'Sobre de crónica',
        relics: 'Sobre de reliquias',
        omens: 'Sobre de presagios',
        gaian: 'Sobre gaiano'
    };
    var PACK_CONTENTS = {
        standard: '5 cartas de cualquier coleccion.',
        echoes: '5 cartas comunes o inusuales.',
        magic: '5 cartas de cualquier coleccion, con mejores pesos de rareza.',
        characters: '5 cartas de personaje.',
        lineage: '5 cartas de personajes, tribus o auspicios.',
        essence: '5 cartas de sistemas, tribus, auspicios o formas.',
        powers: '5 cartas de dones, ritos, totems o disciplinas.',
        chronicles: '5 cartas de cronicas, temporadas o episodios.',
        relics: '5 cartas de objetos, documentos o totems.',
        omens: '5 cartas raras o superiores.',
        gaian: '5 cartas epicas, legendarias o miticas.'
    };
    var CARD_GAME_ICON_BASE = '/img/ui/card_game_icons/';
    var CARD_GAME_ICONS = {
        evolve: CARD_GAME_ICON_BASE + 'card_game_evolve_card.png',
        upgrade: CARD_GAME_ICON_BASE + 'card_game_upgrade_card.png',
        sell: CARD_GAME_ICON_BASE + 'card_game_sell_card.png',
        remembrance: CARD_GAME_ICON_BASE + 'card_game_remembrance.png'
    };
    var POWER_TYPES = ['power', 'gift', 'rite', 'totem', 'discipline'];
    var CHRONICLE_TYPES = ['chronicle', 'season', 'episode'];
    var RELIC_TYPES = ['object', 'document', 'totem'];
    var LINEAGE_TYPES = ['character', 'tribe', 'auspice'];
    var ESSENCE_TYPES = ['system', 'tribe', 'auspice', 'form'];
    var RARITY_LABELS = {
        common: 'Común',
        unusual: 'Inusual',
        rare: 'Raro',
        epic: 'Épico',
        legendary: 'Legendario',
        mythic: 'Mítico'
    };
    var RARITY_ICONS = {
        common: '/img/ui/rarity_icons/common.svg',
        unusual: '/img/ui/rarity_icons/unusual.svg',
        rare: '/img/ui/rarity_icons/rare.svg',
        epic: '/img/ui/rarity_icons/epic.svg',
        legendary: '/img/ui/rarity_icons/legendary.svg',
        mythic: '/img/ui/rarity_icons/mythic.svg'
    };
    var RARITY_SHORT = {
        common: 'C',
        unusual: 'I',
        rare: 'R',
        epic: 'E',
        legendary: 'L',
        mythic: 'M'
    };
    var TYPE_LABELS = {
        character: 'Personaje',
        episode: 'Episodio',
        season: 'Temporada',
        chronicle: 'Crónica',
        system: 'Sistema',
        tribe: 'Tribu',
        auspice: 'Auspicio',
        form: 'Forma',
        systems: 'Sistema',
        tribes: 'Tribu',
        auspices: 'Auspicio',
        forms: 'Forma',
        object: 'Objeto',
        document: 'Documento',
        power: 'Poder',
        totem: 'Tótem',
        gift: 'Don',
        rite: 'Rito',
        discipline: 'Disciplina',
        creature: 'Criatura'
    };
    var TYPE_ORDER = ['all', 'character', 'system', 'tribe', 'auspice', 'form', 'gift', 'rite', 'power', 'discipline', 'totem', 'chronicle', 'season', 'episode', 'object', 'document'];
    var TYPE_EMOJI = {
        all: '·',
        system: '⬡',
        tribe: '⬟',
        auspice: '☾',
        form: '⇄',
        character: '👤',
        episode: '🎬',
        season: '📚',
        chronicle: '🌌',
        object: '🗝️',
        document: '📜',
        power: '✦',
        totem: '🪶',
        gift: '✨',
        rite: '🕯️',
        discipline: '🩸',
        creature: '♢'
    };

    var TYPE_ALIASES = {
        systems: 'system',
        tribes: 'tribe',
        auspices: 'auspice',
        forms: 'form'
    };
    var TYPE_ICON_SVG = {
        system: '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 3l7 4v10l-7 4-7-4V7l7-4z"></path><path d="M12 8v8"></path><path d="M8.5 10.5l7 3"></path><path d="M15.5 10.5l-7 3"></path></svg>',
        tribe: '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"></path><path d="M8 10h8"></path><path d="M12 7v10"></path></svg>',
        auspice: '<svg viewBox="0 0 24 24" focusable="false"><path d="M15.5 3.5a8.8 8.8 0 1 0 0 17 7 7 0 0 1 0-17z"></path><path d="M6.5 12h3"></path></svg>',
        form: '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 7h10l-3-3"></path><path d="M14 4l3 3-3 3"></path><path d="M20 17H10l3 3"></path><path d="M10 20l-3-3 3-3"></path></svg>'
    };
    var uiSoundCache = {};
    var combatSoundsPreloaded = false;

    var root = document.querySelector('.hg-cards');
    if (!root) { return; }

    var state = {
        view: root.getAttribute('data-view') || 'gacha',
        mobile: root.getAttribute('data-mobile') === '1',
        isAdmin: root.getAttribute('data-is-admin') === '1',
        albumCategory: 'all',
        collectionOwnedOnly: false,
        collectionRarity: 'all',
        collectionMode: 'album',
        collectionPage: 1,
        collectionPageSize: 20,
        combat: null,
        combatTeams: null,
        combatProfile: null,
        activeCombatTeam: 0,
        draftCombatTeam: [],
        activeCombatScreen: 'battle',
        combatAnimating: false,
        combatRarityFilter: 'all',
        combatTypeFilter: 'all',
        catalog: [],
        catalogById: {},
        freeRewards: null,
        rewardsTimer: null,
        workTimer: null,
        collection: null,
        table: null
    };

    var els = {
        packButtons: Array.prototype.slice.call(document.querySelectorAll('[data-pack-kind]')),
        shopItems: Array.prototype.slice.call(document.querySelectorAll('[data-shop-pack], [data-shop-material]')),
        shopButtons: Array.prototype.slice.call(document.querySelectorAll('[data-shop-buy-pack]')),
        packStocks: Array.prototype.slice.call(document.querySelectorAll('[data-pack-stock]')),
        packGrid: document.querySelector('[data-pack-grid]'),
        packOpenAll: document.querySelector('[data-pack-open-all]'),
        mnemonesCounters: Array.prototype.slice.call(document.querySelectorAll('[data-mnemones-counter]')),
        packResults: document.getElementById('hgPackResults'),
        statusText: document.getElementById('hgStatusText'),
        uniqueCounter: document.getElementById('hgUniqueCounter'),
        totalCopiesCounter: document.getElementById('hgTotalCopiesCounter'),
        dailyPacksCounter: document.getElementById('hgDailyPacksCounter'),
        exportBtn: document.getElementById('hgExportCollection'),
        importFile: document.getElementById('hgImportFile'),
        resetBtn: document.getElementById('hgResetCollection'),
        bulkSellRarity: document.getElementById('hgBulkSellRarity'),
        bulkSellBtn: document.getElementById('hgBulkSellButton'),
        bulkSellPreview: document.getElementById('hgBulkSellPreview'),
        bulkSellKeepBest: document.getElementById('hgBulkSellKeepBest'),
        workSummary: document.querySelector('[data-work-summary]'),
        workList: document.querySelector('[data-work-list]'),
        workClaimBtn: document.querySelector('[data-work-claim]'),
        packSection: document.querySelector('.hg-pack-section'),
        shopSection: document.querySelector('.hg-shop-section'),
        collectionBrowser: document.querySelector('.hg-collection-browser'),
        collectionTools: document.querySelector('.hg-collection-tools'),
        workBench: document.querySelector('.hg-workbench'),
        collectionTable: document.getElementById('hgCollectionTable'),
        albumTabs: document.querySelector('[data-album-tabs]'),
        albumGrid: document.querySelector('[data-album-grid]'),
        collectionModeButtons: Array.prototype.slice.call(document.querySelectorAll('[data-collection-mode]')),
        collectionPageSize: document.querySelector('[data-collection-page-size]'),
        collectionOwnedFilter: document.querySelector('[data-collection-owned-filter]'),
        collectionRarityFilter: document.querySelector('[data-collection-rarity-filter]'),
        collectionTypeFilter: document.querySelector('[data-collection-type-filter]'),
        collectionViews: Array.prototype.slice.call(document.querySelectorAll('[data-collection-view]')),
        collectionPagers: Array.prototype.slice.call(document.querySelectorAll('[data-collection-pager]')),
        mobileTabs: Array.prototype.slice.call(document.querySelectorAll('[data-mobile-panel-tab]')),
        mobilePanels: Array.prototype.slice.call(document.querySelectorAll('[data-mobile-panel]')),
        combatScreenTabs: Array.prototype.slice.call(document.querySelectorAll('[data-combat-screen-tab]')),
        combatScreenPanels: Array.prototype.slice.call(document.querySelectorAll('[data-combat-screen]')),
        combatTeamSelects: Array.prototype.slice.call(document.querySelectorAll('[data-combat-team-select], [data-combat-team-select-mirror]')),
        combatTeamSelect: document.querySelector('[data-combat-team-select]'),
        combatTeamPreviews: Array.prototype.slice.call(document.querySelectorAll('[data-combat-team-preview]')),
        combatProfileNames: Array.prototype.slice.call(document.querySelectorAll('[data-combat-profile-name]')),
        combatProfileFavorites: Array.prototype.slice.call(document.querySelectorAll('[data-combat-profile-favorite]')),
        combatTeamSlots: document.querySelector('[data-combat-team-slots]'),
        combatSaveTeam: document.querySelector('[data-combat-save-team]'),
        combatClearTeam: document.querySelector('[data-combat-clear-team]'),
        combatAutoTeam: document.querySelector('[data-combat-auto-team]'),
        combatOnlyReady: document.querySelector('[data-combat-only-ready]'),
        combatRarityFilter: document.querySelector('[data-combat-rarity-filter]'),
        combatTypeFilter: document.querySelector('[data-combat-type-filter]'),
        combatCardList: document.querySelector('[data-combat-card-list]'),
        combatDifficulty: document.querySelector('[data-combat-difficulty]'),
        combatSetups: Array.prototype.slice.call(document.querySelectorAll('.hg-combat-setup')),
        combatStart: document.querySelector('[data-combat-start]'),
        combatActions: Array.prototype.slice.call(document.querySelectorAll('[data-combat-action]')),
        combatBench: document.querySelector('[data-combat-bench]'),
        combatLog: document.querySelector('[data-combat-log]'),
        combatMessage: document.querySelector('[data-combat-message]'),
        combatPlayerCard: document.querySelector('[data-combat-player-card]'),
        combatEnemyCard: document.querySelector('[data-combat-enemy-card]'),
        combatPlayerName: document.querySelector('[data-combat-player-name]'),
        combatEnemyName: document.querySelector('[data-combat-enemy-name]'),
        combatPlayerHp: document.querySelector('[data-combat-player-hp]'),
        combatEnemyHp: document.querySelector('[data-combat-enemy-hp]'),
        combatPlayerShields: document.querySelector('[data-combat-player-shields]'),
        combatEnemyShields: document.querySelector('[data-combat-enemy-shields]'),
        combatPlayerHpBar: document.querySelector('[data-combat-player-hp-bar]'),
        combatEnemyHpBar: document.querySelector('[data-combat-enemy-hp-bar]'),
        combatPlayerAtk: document.querySelector('[data-combat-player-atk]'),
        combatPlayerDef: document.querySelector('[data-combat-player-def]'),
        combatEnemyAtk: document.querySelector('[data-combat-enemy-atk]'),
        combatEnemyDef: document.querySelector('[data-combat-enemy-def]')
    };

    function setStatus(message) {
        if (els.statusText) {
            els.statusText.textContent = message;
        }
    }

    function decorateIconNavigation() {
        var mobileLabels = {
            packs: 'Sobres',
            shop: 'Tienda',
            collection: 'Colección',
            memory: 'Recuerdos',
            combat: 'Combate',
            info: 'Información'
        };
        els.mobileTabs.forEach(function (button) {
            var label = mobileLabels[button.getAttribute('data-mobile-panel-tab') || ''] || button.textContent.trim();
            button.title = label;
            button.setAttribute('aria-label', label);
        });
        Array.prototype.slice.call(document.querySelectorAll('.hg-game-tabs a')).forEach(function (link) {
            var text = link.textContent.trim();
            if (text) {
                link.title = text;
                link.setAttribute('aria-label', text);
            }
        });
    }

    function currentHashPanel() {
        return String(window.location.hash || '').replace(/^#/, '').toLowerCase();
    }

    function setDesktopSection(section, visible) {
        if (!section || state.mobile) { return; }
        section.hidden = !visible;
    }

    function updateDesktopNavActive() {
        var hash = currentHashPanel();
        Array.prototype.slice.call(document.querySelectorAll('.hg-game-tabs a')).forEach(function (link) {
            var href = link.getAttribute('href') || '';
            var active = false;
            if (state.view === 'gacha') {
                active = hash === 'shop' ? href === '/games/card-game#shop' : href === '/games/card-game';
            } else if (state.view === 'collection') {
                active = hash === 'memory' ? href === '/games/card-game/collection#memory' : href === '/games/card-game/collection';
            } else {
                active = link.classList.contains('is-active');
            }
            link.classList.toggle('is-active', active);
        });
    }

    function updateDesktopHashPanels() {
        var hash = currentHashPanel();
        if (state.view === 'gacha') {
            var shopActive = hash === 'shop';
            setDesktopSection(els.packSection, !shopActive);
            setDesktopSection(els.packResults, !shopActive);
            setDesktopSection(els.shopSection, shopActive);
        }
        if (state.view === 'collection') {
            var memoryActive = hash === 'memory';
            setDesktopSection(els.collectionBrowser, !memoryActive);
            setDesktopSection(els.collectionTools, !memoryActive);
            setDesktopSection(els.workBench, memoryActive);
        }
        updateDesktopNavActive();
    }

    function packContents(packKind) {
        return PACK_CONTENTS[packKind] || PACK_CONTENTS.standard;
    }

    function closeConfirmModal() {
        var current = document.querySelector('.hg-confirm-modal');
        if (current) {
            current.remove();
        }
        document.removeEventListener('keydown', confirmEscapeHandler);
    }

    function confirmEscapeHandler(event) {
        if (event.key === 'Escape') {
            closeConfirmModal();
        }
    }

    function confirmGameAction(message, options, onConfirm) {
        options = options || {};
        closeConfirmModal();

        var overlay = document.createElement('div');
        overlay.className = 'hg-confirm-modal';
        if (state.mobile) { overlay.className += ' hg-confirm-modal--mobile'; }
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', options.title || 'Confirmar acción');

        var panel = document.createElement('div');
        panel.className = 'hg-confirm-modal__panel';
        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        var title = document.createElement('h3');
        title.textContent = options.title || 'Confirmar acción';

        var text = document.createElement('p');
        text.textContent = message;

        var actions = document.createElement('div');
        actions.className = 'hg-confirm-modal__actions';

        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'hg-confirm-modal__cancel';
        cancel.textContent = options.cancelLabel || 'Cancelar';
        cancel.addEventListener('click', closeConfirmModal);

        var accept = document.createElement('button');
        accept.type = 'button';
        accept.className = 'hg-confirm-modal__accept';
        accept.textContent = options.confirmLabel || 'Confirmar';
        accept.addEventListener('click', function () {
            closeConfirmModal();
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        });

        actions.appendChild(cancel);
        actions.appendChild(accept);
        panel.appendChild(title);
        panel.appendChild(text);
        panel.appendChild(actions);
        overlay.appendChild(panel);
        overlay.addEventListener('click', closeConfirmModal);
        document.body.appendChild(overlay);
        document.addEventListener('keydown', confirmEscapeHandler);
        accept.focus();
        return false;
    }

    function nowIso() {
        return new Date().toISOString();
    }

    function clampInt(value, fallback) {
        var n = Number(value);
        if (!Number.isFinite(n)) { return fallback; }
        return Math.round(n);
    }

    function clampQuality(value, fallback) {
        var n = Number(value);
        if (!Number.isFinite(n)) { return fallback; }
        return Math.max(0, Math.min(100, Math.round(n * 10) / 10));
    }

    function createEmptyCollection() {
        var now = nowIso();
        return {
            version: 1,
            createdAt: now,
            updatedAt: now,
            ownedCards: [],
            workAssignments: {},
            workPendingRewards: 0,
            currency: { mnemones: STARTING_MNEMONES },
            packInventory: normalizePackInventory({}),
            materialInventory: normalizeMaterialInventory({})
        };
    }

    function instanceId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'hg-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    }

    function readJson(key, fallback) {
        try {
            var raw = window.localStorage.getItem(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function writeJson(key, value) {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            setStatus('No se pudo guardar en localStorage.');
        }
    }

    function readText(key, fallback) {
        try {
            var raw = window.localStorage.getItem(key);
            return raw ? String(raw) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function writeText(key, value) {
        try {
            window.localStorage.setItem(key, String(value));
        } catch (e) {}
    }

    function normalizePageSize(value) {
        var size = clampInt(value, 20);
        return [10, 20, 50].indexOf(size) === -1 ? 20 : size;
    }

    function normalizeCollectionRarity(value) {
        value = String(value || 'all');
        return value === 'all' || RARITY_ORDER.indexOf(value) !== -1 ? value : 'all';
    }

    function normalizeRarity(value, fallback) {
        value = String(value || '');
        return RARITY_ORDER.indexOf(value) !== -1 ? value : (fallback || 'common');
    }

    function loadCollectionViewPrefs() {
        var mode = readText(COLLECTION_MODE_KEY, 'album');
        state.collectionMode = mode === 'table' ? 'table' : 'album';
        state.collectionPageSize = normalizePageSize(readText(COLLECTION_PAGE_SIZE_KEY, '20'));
        if (els.collectionPageSize) {
            els.collectionPageSize.value = String(state.collectionPageSize);
        }
        if (els.collectionOwnedFilter) {
            state.collectionOwnedOnly = !!els.collectionOwnedFilter.checked;
        }
        if (els.collectionRarityFilter) {
            state.collectionRarity = normalizeCollectionRarity(els.collectionRarityFilter.value);
            els.collectionRarityFilter.value = state.collectionRarity;
        }
    }

    function createFreeRewards() {
        return {
            version: 2,
            freePackDate: dailyFreePackDate(),
            freePacksClaimed: 0
        };
    }

    function dailyFreePackDate() {
        var now = new Date();
        return now.getFullYear() + '-' +
            String(now.getMonth() + 1).padStart(2, '0') + '-' +
            String(now.getDate()).padStart(2, '0');
    }

    function normalizeTimestamp(value, fallback) {
        var n = Number(value);
        return Number.isFinite(n) && n > 0 ? n : fallback;
    }

    function loadFreeRewards() {
        if (state.freeRewards) { return state.freeRewards; }
        var fallback = createFreeRewards();
        var data = readJson(FREE_REWARDS_KEY, null);
        if (!data || typeof data !== 'object') {
            state.freeRewards = fallback;
            writeJson(FREE_REWARDS_KEY, state.freeRewards);
            return state.freeRewards;
        }
        var today = dailyFreePackDate();
        var storedDate = typeof data.freePackDate === 'string' ? data.freePackDate : today;
        state.freeRewards = {
            version: 2,
            freePackDate: today,
            freePacksClaimed: storedDate === today
                ? Math.max(0, Math.min(DAILY_FREE_PACK_CAP, clampInt(data.freePacksClaimed, 0)))
                : 0
        };
        return state.freeRewards;
    }

    function saveFreeRewards() {
        if (!state.freeRewards) { state.freeRewards = createFreeRewards(); }
        writeJson(FREE_REWARDS_KEY, state.freeRewards);
    }

    function syncFreeRewards() {
        if (state.isAdmin) { return { packs: 0, mnemones: 0 }; }
        var rewards = loadFreeRewards();
        var today = dailyFreePackDate();
        if (rewards.freePackDate !== today) {
            rewards.freePackDate = today;
            rewards.freePacksClaimed = 0;
            saveFreeRewards();
        }
        return { packs: 0, mnemones: 0 };
    }

    function dailyFreePacksRemaining() {
        if (state.isAdmin) {
            return Infinity;
        }
        syncFreeRewards();
        return Math.max(0, DAILY_FREE_PACK_CAP - Math.max(0, Math.min(DAILY_FREE_PACK_CAP, clampInt((state.freeRewards || {}).freePacksClaimed, 0))));
    }

    function claimDailyFreePacks(amount) {
        amount = Math.max(1, clampInt(amount, 1));
        if (state.isAdmin) { return true; }
        var rewards = loadFreeRewards();
        syncFreeRewards();
        if (dailyFreePacksRemaining() < amount) { return false; }
        rewards.freePacksClaimed = Math.min(DAILY_FREE_PACK_CAP, clampInt(rewards.freePacksClaimed, 0) + amount);
        saveFreeRewards();
        return true;
    }

    function normalizeWorkAssignments(assignments) {
        var out = {};
        if (!assignments || typeof assignments !== 'object') { return out; }
        Object.keys(assignments).forEach(function (key) {
            var item = assignments[key];
            var id = String((item && item.instanceId) || key || '').slice(0, 80);
            if (!id) { return; }
            var startedAt = normalizeTimestamp(item && item.startedAt, Date.now());
            var lastClaimAt = normalizeTimestamp(item && item.lastClaimAt, startedAt);
            out[id] = {
                instanceId: id,
                startedAt: startedAt,
                lastClaimAt: Math.max(startedAt, lastClaimAt)
            };
        });
        return out;
    }

    function normalizeWorkPendingRewards(value) {
        return Math.max(0, Math.min(MAX_MNEMONES, clampInt(value, 0)));
    }

    function renderDailyCounter() {
        if (!els.dailyPacksCounter) { return; }
        if (state.isAdmin) {
            els.dailyPacksCounter.textContent = 'Admin';
            return;
        }
        var remaining = dailyFreePacksRemaining();
        els.dailyPacksCounter.textContent = String(remaining) + ' / ' + DAILY_FREE_PACK_CAP;
        els.dailyPacksCounter.title = remaining > 0
            ? 'Sobres gratis pendientes de reclamar hoy en tienda.'
            : 'Cupo diario de sobres gratis agotado.';
    }

    function normalizePackInventory(inventory) {
        var out = {};
        PACK_KINDS.forEach(function (kind) {
            out[kind] = 0;
        });
        if (!inventory || typeof inventory !== 'object') { return out; }
        PACK_KINDS.forEach(function (kind) {
            out[kind] = Math.max(0, Math.min(MAX_PACK_STOCK, clampInt(inventory[kind], 0)));
        });
        return out;
    }

    function normalizeMaterialInventory(inventory) {
        var out = {};
        Object.keys(UPGRADE_MATERIALS).forEach(function (key) {
            out[key] = 0;
        });
        if (!inventory || typeof inventory !== 'object') { return out; }
        Object.keys(UPGRADE_MATERIALS).forEach(function (key) {
            out[key] = Math.max(0, Math.min(999, clampInt(inventory[key], 0)));
        });
        return out;
    }

    function normalizeCurrency(currency) {
        if (!currency || typeof currency !== 'object' || typeof currency.mnemones === 'undefined') {
            return { mnemones: STARTING_MNEMONES };
        }
        return {
            mnemones: Math.max(0, Math.min(MAX_MNEMONES, clampInt(currency && currency.mnemones, 0)))
        };
    }

    function currentMnemones() {
        if (!state.collection) { loadCollection(); }
        state.collection.currency = normalizeCurrency(state.collection.currency);
        return state.collection.currency.mnemones;
    }

    function addMnemones(amount) {
        if (!state.collection) { loadCollection(); }
        state.collection.currency = normalizeCurrency(state.collection.currency);
        state.collection.currency.mnemones = Math.max(0, Math.min(MAX_MNEMONES, state.collection.currency.mnemones + clampInt(amount, 0)));
        return state.collection.currency.mnemones;
    }

    function formatNumber(value) {
        return clampInt(value, 0).toLocaleString('es-ES');
    }

    function materialStock(materialKey) {
        if (state.isAdmin) { return Infinity; }
        if (!state.collection) { loadCollection(); }
        state.collection.materialInventory = normalizeMaterialInventory(state.collection.materialInventory);
        return Math.max(0, clampInt(state.collection.materialInventory[materialKey], 0));
    }

    function addMaterial(materialKey, amount) {
        if (!UPGRADE_MATERIALS[materialKey]) { return false; }
        if (!state.collection) { loadCollection(); }
        state.collection.materialInventory = normalizeMaterialInventory(state.collection.materialInventory);
        var current = Math.max(0, clampInt(state.collection.materialInventory[materialKey], 0));
        state.collection.materialInventory[materialKey] = Math.max(0, Math.min(999, current + Math.max(1, clampInt(amount, 1))));
        return state.collection.materialInventory[materialKey];
    }

    function consumeMaterial(materialKey, amount) {
        amount = Math.max(1, clampInt(amount, 1));
        if (state.isAdmin || !materialKey) { return true; }
        if (materialStock(materialKey) < amount) { return false; }
        state.collection.materialInventory[materialKey] = Math.max(0, materialStock(materialKey) - amount);
        return true;
    }

    function ensureWorkAssignments() {
        if (!state.collection) { loadCollection(); }
        state.collection.workAssignments = normalizeWorkAssignments(state.collection.workAssignments);
        state.collection.workPendingRewards = normalizeWorkPendingRewards(state.collection.workPendingRewards);
        limitWorkAssignments(false);
        return state.collection.workAssignments;
    }

    function isCopyWorking(instanceId) {
        var id = String(instanceId || '');
        if (!id) { return false; }
        return !!ensureWorkAssignments()[id];
    }

    function cleanWorkAssignments(persist) {
        if (!state.collection || !Array.isArray(state.collection.ownedCards)) { return false; }
        state.collection.workAssignments = normalizeWorkAssignments(state.collection.workAssignments);
        var owned = {};
        state.collection.ownedCards.forEach(function (copy) {
            if (copy && copy.instanceId) { owned[String(copy.instanceId)] = true; }
        });
        var changed = false;
        Object.keys(state.collection.workAssignments).forEach(function (id) {
            if (!owned[id]) {
                delete state.collection.workAssignments[id];
                changed = true;
            }
        });
        if (changed && persist) { saveCollection(); }
        return changed;
    }

    function limitWorkAssignments(persist) {
        if (!state.collection) { loadCollection(); }
        var assignments = normalizeWorkAssignments(state.collection.workAssignments);
        var ids = Object.keys(assignments).sort(function (a, b) {
            return normalizeTimestamp(assignments[a].startedAt, 0) - normalizeTimestamp(assignments[b].startedAt, 0);
        });
        var changed = false;
        ids.slice(WORK_MAX_ASSIGNMENTS).forEach(function (id) {
            delete assignments[id];
            changed = true;
        });
        state.collection.workAssignments = assignments;
        if (changed && persist) { saveCollection(); }
        return changed;
    }

    function workRatePerMinute(copy, card) {
        if (!copy || !card) { return 0; }
        var rarity = copyRarity(copy, card);
        var base = WORK_RARITY_BASE[rarity] || WORK_RARITY_BASE.common;
        var qualityFactor = 0.6 + (qualityScore(copy, card) / 100) * 0.8;
        var statBonus = Math.min(4, totalStats(copy) / 150);
        return Math.max(0.5, Math.round((base * qualityFactor + statBonus) * 10) / 10);
    }

    function workEntryFromAssignment(assignment) {
        var copy = copyByInstanceId(assignment && assignment.instanceId);
        var card = copy ? state.catalogById[String(copy.cardId || '')] : null;
        if (!copy || !card) { return null; }
        var rate = workRatePerMinute(copy, card);
        var elapsed = Math.max(0, Date.now() - normalizeTimestamp(assignment.lastClaimAt, Date.now()));
        var startedAt = normalizeTimestamp(assignment.startedAt, Date.now());
        return {
            assignment: assignment,
            copy: copy,
            baseCard: card,
            card: cardForCopy(card, copy),
            rarity: copyRarity(copy, card),
            rate: rate,
            claimable: Math.floor((elapsed / 60000) * rate),
            startedAt: startedAt,
            removableAt: startedAt + WORK_MIN_DURATION_MS
        };
    }

    function activeWorkEntries() {
        cleanWorkAssignments(false);
        var assignments = ensureWorkAssignments();
        return Object.keys(assignments).map(function (id) {
            return workEntryFromAssignment(assignments[id]);
        }).filter(Boolean).sort(function (a, b) {
            return b.rate - a.rate || b.claimable - a.claimable || String(a.card.card_name).localeCompare(String(b.card.card_name));
        });
    }

    function workCandidateEntries() {
        if (!state.collection) { loadCollection(); }
        return (state.collection.ownedCards || []).map(function (copy) {
            var card = state.catalogById[String(copy.cardId || '')];
            if (!card || isCopyWorking(copy.instanceId) || isCopyInCombatTeam(copy.instanceId)) { return null; }
            return {
                copy: copy,
                baseCard: card,
                card: cardForCopy(card, copy),
                rarity: copyRarity(copy, card),
                rate: workRatePerMinute(copy, card),
                score: totalStats(copy)
            };
        }).filter(Boolean).sort(function (a, b) {
            return b.rate - a.rate || b.score - a.score || String(a.card.card_name).localeCompare(String(b.card.card_name));
        });
    }

    function totalWorkClaimable(entries) {
        var pending = state.collection ? normalizeWorkPendingRewards(state.collection.workPendingRewards) : 0;
        return pending + (entries || activeWorkEntries()).reduce(function (sum, entry) {
            return sum + entry.claimable;
        }, 0);
    }

    function workCanStop(entry) {
        return !!entry && Date.now() >= entry.removableAt;
    }

    function workRemainingLabel(entry) {
        var remaining = Math.max(0, (entry ? entry.removableAt : Date.now()) - Date.now());
        if (remaining <= 0) { return 'Disponible'; }
        var hours = Math.floor(remaining / 3600000);
        var minutes = Math.ceil((remaining % 3600000) / 60000);
        if (minutes >= 60) {
            hours += 1;
            minutes = 0;
        }
        return hours + 'h' + (minutes ? ' ' + minutes + 'm' : '');
    }

    function renderWorkBench() {
        if (!els.workSummary && !els.workList && !els.workClaimBtn) { return; }
        if (state.mobile && !isMemoryContext()) { return; }
        var entries = activeWorkEntries();
        var candidates = workCandidateEntries();
        var totalRate = entries.reduce(function (sum, entry) { return sum + entry.rate; }, 0);
        var claimable = totalWorkClaimable(entries);
        if (els.workSummary) {
            els.workSummary.innerHTML = [
                '<span><strong>' + entries.length + ' / ' + WORK_MAX_ASSIGNMENTS + '</strong><small>rememorando</small></span>',
                '<span><strong>' + totalRate.toFixed(1) + '</strong><small>Mn/min</small></span>',
                '<span><strong>' + claimable + '</strong><small>reclamables</small></span>'
            ].join('');
        }
        if (els.workClaimBtn) {
            els.workClaimBtn.disabled = claimable <= 0;
            els.workClaimBtn.textContent = claimable > 0 ? 'Reclamar +' + claimable : 'Reclamar';
        }
        if (!els.workList) { return; }
        els.workList.innerHTML = '';
        for (var slotIndex = 0; slotIndex < WORK_MAX_ASSIGNMENTS; slotIndex++) {
            var entry = entries[slotIndex] || null;
            var slot = document.createElement('article');
            slot.className = 'hg-work-slot' + (entry ? ' hg-collection-row--' + entry.rarity : ' is-empty');
            if (!entry) {
                slot.innerHTML =
                    '<div class="hg-work-slot__empty">' +
                        '<strong>Hueco ' + (slotIndex + 1) + '</strong>' +
                        '<span>Elige una carta para recordar.</span>' +
                    '</div>';
                if (candidates.length) {
                    var select = document.createElement('select');
                    select.setAttribute('aria-label', 'Carta para recordar en hueco ' + (slotIndex + 1));
                    candidates.forEach(function (candidate) {
                        var option = document.createElement('option');
                        option.value = String(candidate.copy.instanceId || '');
                        option.textContent = candidate.card.card_name + ' · ' + candidate.rate.toFixed(1) + ' Mn/min';
                        select.appendChild(option);
                    });
                    var add = document.createElement('button');
                    add.type = 'button';
                    add.className = 'hg-icon-action hg-icon-action--memory';
                    add.title = 'Recordar';
                    add.setAttribute('aria-label', 'Recordar carta seleccionada');
                    add.innerHTML = cardGameIconHtml('remembrance', 'Recordar');
                    (function (selectNode) {
                        add.addEventListener('click', function () {
                            assignCopyToWorkById(selectNode.value);
                        });
                    })(select);
                    slot.appendChild(select);
                    slot.appendChild(add);
                } else {
                    var none = document.createElement('p');
                    none.className = 'hg-empty-state';
                    none.textContent = 'No hay cartas disponibles.';
                    slot.appendChild(none);
                }
                els.workList.appendChild(slot);
                continue;
            }
            var canStop = workCanStop(entry);
            var cardWrap = document.createElement('div');
            cardWrap.className = 'hg-work-slot__card';
            var memoryCard = renderCard(entry.baseCard, entry.copy, { noLink: true });
            memoryCard.className += ' hg-card--memory';
            cardWrap.appendChild(memoryCard);
            var effects = document.createElement('div');
            effects.className = 'hg-work-slot__effects';
            effects.innerHTML =
                '<b>' + entry.rate.toFixed(1) + ' Mnemones/min</b>' +
                '<span>Ganancias: +' + entry.claimable + '</span>' +
                '<small>' + escapeHtml(canStop ? 'Puede volver' : 'Vuelve en ' + workRemainingLabel(entry)) + '</small>';
            var stop = document.createElement('button');
            stop.type = 'button';
            stop.textContent = 'Retirar';
            stop.disabled = !canStop;
            stop.title = canStop ? '' : 'Debe rememorar al menos 24 horas.';
            (function (entryForStop) {
                stop.addEventListener('click', function () {
                    stopCopyWork(entryForStop.copy.instanceId);
                });
            })(entry);
            effects.appendChild(stop);
            slot.appendChild(cardWrap);
            slot.appendChild(effects);
            els.workList.appendChild(slot);
        }
    }

    function claimWorkRewards(targetId) {
        targetId = String(targetId || '');
        var entries = activeWorkEntries().filter(function (entry) {
            return !targetId || String(entry.copy.instanceId || '') === targetId;
        });
        var pending = targetId ? 0 : normalizeWorkPendingRewards(state.collection && state.collection.workPendingRewards);
        var claimable = pending + entries.reduce(function (sum, entry) {
            return sum + entry.claimable;
        }, 0);
        if (claimable <= 0) {
            setStatus('Todavia no hay Mnemones de rememoración para reclamar.');
            renderWorkBench();
            return false;
        }
        var now = Date.now();
        entries.forEach(function (entry) {
            if (entry.claimable <= 0 || entry.rate <= 0) { return; }
            var elapsedClaimedMs = Math.floor((entry.claimable / entry.rate) * 60000);
            entry.assignment.lastClaimAt = Math.min(now, normalizeTimestamp(entry.assignment.lastClaimAt, now) + elapsedClaimedMs);
        });
        if (!targetId) { state.collection.workPendingRewards = 0; }
        addMnemones(claimable);
        saveCollection();
        playMoneySound();
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        setStatus('Rememoración reclamada. +' + claimable + ' Mnemones.');
        return true;
    }

    function assignCopyToWork(card, copy, options) {
        options = options || {};
        if (!copy || !copy.instanceId) { return false; }
        if (isCopyWorking(copy.instanceId)) {
            setStatus('Esta carta ya esta rememorando.');
            return false;
        }
        if (activeWorkEntries().length >= WORK_MAX_ASSIGNMENTS) {
            setStatus('Sólo puedes tener ' + WORK_MAX_ASSIGNMENTS + ' cartas rememorando a la vez.');
            return false;
        }
        if (isCopyInCombatTeam(copy.instanceId)) {
            setStatus('Quita la carta del equipo antes de ponerla a recordar.');
            return false;
        }
        var assignments = ensureWorkAssignments();
        var now = Date.now();
        assignments[String(copy.instanceId)] = { instanceId: String(copy.instanceId), startedAt: now, lastClaimAt: now };
        saveCollection();
        renderSummary();
        renderCollectionTable();
        renderCombatSetup();
        if (!options.noModal) {
            showCardModal(card, ownedCopiesForCard(card.card_id));
        }
        setStatus('Carta puesta a recordar: +' + workRatePerMinute(copy, card).toFixed(1) + ' Mnemones/min.');
        return true;
    }

    function assignCopyToWorkById(instanceId) {
        var copy = copyByInstanceId(instanceId);
        var card = copy ? state.catalogById[String(copy.cardId || '')] : null;
        if (!copy || !card) {
            setStatus('No se encontró esa carta para recordar.');
            return false;
        }
        return assignCopyToWork(card, copy, { noModal: true });
    }

    function stopCopyWork(instanceId) {
        var id = String(instanceId || '');
        var assignments = ensureWorkAssignments();
        if (!assignments[id]) { return false; }
        var entry = workEntryFromAssignment(assignments[id]);
        if (!workCanStop(entry)) {
            setStatus('Esta carta debe rememorar al menos 24 horas. Quedan ' + workRemainingLabel(entry) + '.');
            renderWorkBench();
            return false;
        }
        state.collection.workPendingRewards = normalizeWorkPendingRewards(
            normalizeWorkPendingRewards(state.collection.workPendingRewards) + Math.max(0, entry ? entry.claimable : 0)
        );
        delete assignments[id];
        saveCollection();
        renderSummary();
        renderWorkBench();
        renderCollectionTable();
        renderCombatSetup();
        setStatus('Carta retirada de la rememoración. Sus ganancias quedan pendientes en Reclamar.');
        return true;
    }

    function packStock(packKind) {
        if (state.isAdmin) { return Infinity; }
        if (!state.collection) { loadCollection(); }
        return Math.max(0, clampInt((state.collection.packInventory || {})[packKind], 0));
    }

    function packSpace(packKind) {
        if (state.isAdmin) { return Infinity; }
        return Math.max(0, MAX_PACK_STOCK - packStock(packKind));
    }

    function canOpenPack(packKind) {
        return packStock(packKind) > 0;
    }

    function consumePack(packKind) {
        if (state.isAdmin) { return; }
        if (!state.collection) { loadCollection(); }
        state.collection.packInventory = normalizePackInventory(state.collection.packInventory);
        state.collection.packInventory[packKind] = Math.max(0, clampInt(state.collection.packInventory[packKind], 0) - 1);
    }

    function addPack(packKind, amount, options) {
        options = options || {};
        if (PACK_KINDS.indexOf(packKind) === -1) { return false; }
        if (!state.collection) { loadCollection(); }
        state.collection.packInventory = normalizePackInventory(state.collection.packInventory);
        state.collection.packInventory[packKind] = Math.max(0, Math.min(MAX_PACK_STOCK, state.collection.packInventory[packKind] + Math.max(1, clampInt(amount, 1))));
        if (!options.deferSave) { saveCollection(); }
        if (!options.silent) {
            renderSummary({ light: true });
            renderPackInventory();
        }
        return true;
    }

    function totalPackStock() {
        if (state.isAdmin) { return Infinity; }
        if (!state.collection) { loadCollection(); }
        state.collection.packInventory = normalizePackInventory(state.collection.packInventory);
        return PACK_KINDS.reduce(function (sum, kind) {
            return sum + Math.max(0, clampInt(state.collection.packInventory[kind], 0));
        }, 0);
    }

    function renderPackInventory() {
        var totalStock = totalPackStock();
        els.packStocks.forEach(function (node) {
            var kind = node.getAttribute('data-pack-stock') || 'standard';
            var stock = packStock(kind);
            if (state.isAdmin) {
                node.textContent = 'Admin';
            } else if (kind === 'standard') {
                node.textContent = 'x' + stock;
                node.title = 'Sobres mnemónicos disponibles.';
            } else {
                node.textContent = 'x' + stock;
            }
        });
        els.packButtons.forEach(function (button) {
            var kind = button.getAttribute('data-pack-kind') || 'standard';
            var stock = packStock(kind);
            var visible = state.isAdmin || stock > 0;
            var available = canOpenPack(kind);
            button.hidden = !visible;
            button.disabled = !available;
            button.classList.toggle('is-empty', !available);
            button.classList.toggle('is-hidden', !visible);
            button.setAttribute('aria-disabled', available ? 'false' : 'true');
        });
        renderPackEmptyState(totalStock);
        renderShop();
    }

    function renderPackEmptyState(totalStock) {
        if (!els.packGrid) { return; }
        var empty = els.packGrid.querySelector('[data-pack-empty-state]');
        if (!empty) {
            empty = document.createElement('p');
            empty.className = 'hg-empty-state hg-pack-empty-state';
            empty.setAttribute('data-pack-empty-state', '1');
            empty.textContent = 'No te quedan sobres. Puedes comprar más en la tienda o probar suerte en las Mazmorras.';
            els.packGrid.appendChild(empty);
        }
        empty.hidden = state.isAdmin || totalStock > 0;
        if (els.packOpenAll) {
            els.packOpenAll.hidden = state.isAdmin || totalStock <= 0;
            els.packOpenAll.disabled = state.isAdmin || totalStock <= 0;
        }
    }

    function renderShop() {
        els.shopItems = Array.prototype.slice.call(document.querySelectorAll('[data-shop-pack], [data-shop-material]'));
        var money = currentMnemones();
        els.mnemonesCounters.forEach(function (node) {
            node.textContent = formatNumber(money);
        });
        els.shopItems.forEach(function (item) {
            var materialKey = item.getAttribute('data-shop-material') || '';
            if (materialKey) {
                renderMaterialShopItem(item, materialKey, money);
                return;
            }
            var kind = item.getAttribute('data-shop-pack') || 'standard';
            var isFree = item.getAttribute('data-shop-free') === '1';
            var price = isFree ? 0 : packPrice(kind);
            var freeRemaining = isFree ? dailyFreePacksRemaining() : 0;
            var priceNode = item.querySelector('strong');
            if (priceNode) {
                priceNode.textContent = isFree
                    ? (state.isAdmin ? 'Admin' : (freeRemaining > 0 ? 'Gratis - quedan ' + freeRemaining : 'Agotado hoy'))
                    : formatNumber(price) + ' Mnemones';
            }
            var description = item.querySelector('.hg-shop-item__contents');
            if (!description) {
                description = document.createElement('span');
                description.className = 'hg-shop-item__contents';
                item.appendChild(description);
            }
            description.textContent = isFree ? 'Reclama hasta ' + DAILY_FREE_PACK_CAP + ' sobres mnemónicos gratis al día.' : packContents(kind);
            item.title = isFree
                ? 'Sobres mnemónicos gratis. Quedan ' + (state.isAdmin ? 'Admin' : freeRemaining) + ' hoy.'
                : packLabel(kind) + ': ' + packContents(kind) + ' Precio: ' + formatNumber(price) + ' Mnemones.';
            var controls = item.querySelector('.hg-shop-item__actions');
            if (!controls) {
                controls = document.createElement('span');
                controls.className = 'hg-shop-item__actions';
                item.appendChild(controls);
            }
            controls.innerHTML = '';
            SHOP_QUANTITIES.forEach(function (amount) {
                var buy = document.createElement('button');
                buy.type = 'button';
                buy.className = 'hg-shop-buy';
                buy.setAttribute('data-shop-buy-pack', kind);
                buy.setAttribute('data-shop-buy-amount', String(amount));
                if (isFree) { buy.setAttribute('data-shop-buy-free', '1'); }
                buy.textContent = 'x' + amount;
                buy.disabled = isFree
                    ? (!state.isAdmin && (freeRemaining < amount || packSpace(kind) < amount))
                    : (!state.isAdmin && (money < price * amount || packSpace(kind) < amount));
                buy.title = isFree
                    ? 'Reclamar ' + amount + ' sobre(s) mnemónicos gratis'
                    : 'Comprar ' + amount + ' por ' + formatNumber(price * amount) + ' Mnemones';
                controls.appendChild(buy);
            });
            item.classList.toggle('is-empty', isFree ? (!state.isAdmin && (freeRemaining <= 0 || packSpace(kind) <= 0)) : (!state.isAdmin && (money < price || packSpace(kind) <= 0)));
        });
        els.shopButtons = Array.prototype.slice.call(document.querySelectorAll('[data-shop-buy-pack]'));
    }

    function materialIconHtml(materialKey) {
        var material = UPGRADE_MATERIALS[materialKey];
        var icon = material ? RARITY_ICONS[material.rarity] : '';
        return icon ? '<img src="' + escapeHtml(icon) + '" alt="" width="24" height="24">' : '';
    }

    function renderMaterialShopItem(item, materialKey, money) {
        var material = UPGRADE_MATERIALS[materialKey];
        if (!material) {
            item.hidden = true;
            return;
        }
        item.hidden = false;
        var nameNode = item.querySelector('span');
        if (nameNode && !nameNode.classList.contains('hg-shop-item__contents')) {
            nameNode.innerHTML = materialIconHtml(materialKey) + '<span>' + escapeHtml(material.label) + '</span>';
        }
        var priceNode = item.querySelector('strong');
        if (priceNode) {
            priceNode.textContent = formatNumber(material.price) + ' Mnemones';
        }
        var description = item.querySelector('.hg-shop-item__contents');
        if (!description) {
            description = document.createElement('span');
            description.className = 'hg-shop-item__contents';
            item.appendChild(description);
        }
        description.textContent = material.description + ' Tienes: ' + (state.isAdmin ? 'Admin' : materialStock(materialKey)) + '.';
        item.title = material.label + ': ' + material.description + ' Precio: ' + formatNumber(material.price) + ' Mnemones.';
        var controls = item.querySelector('.hg-shop-item__actions');
        if (!controls) {
            controls = document.createElement('span');
            controls.className = 'hg-shop-item__actions';
            item.appendChild(controls);
        }
        controls.innerHTML = '';
        SHOP_QUANTITIES.forEach(function (amount) {
            var buy = document.createElement('button');
            buy.type = 'button';
            buy.className = 'hg-shop-buy';
            buy.setAttribute('data-shop-buy-material', materialKey);
            buy.setAttribute('data-shop-buy-amount', String(amount));
            buy.textContent = 'x' + amount;
            buy.disabled = !state.isAdmin && money < material.price * amount;
            buy.title = 'Comprar ' + amount + ' por ' + formatNumber(material.price * amount) + ' Mnemones';
            controls.appendChild(buy);
        });
        item.classList.toggle('is-empty', !state.isAdmin && money < material.price);
    }

    function packPrice(packKind) {
        return Object.prototype.hasOwnProperty.call(PACK_PRICES, packKind) ? PACK_PRICES[packKind] : PACK_PRICES.standard;
    }

    function normalizeSourceType(type) {
        var key = String(type || 'document').trim().toLowerCase();
        return TYPE_ALIASES[key] || key || 'document';
    }

    function typeLabel(type) {
        var normalized = normalizeSourceType(type);
        return TYPE_LABELS[normalized] || TYPE_LABELS[type] || normalized;
    }

    function typeIconHtml(type, extraClass) {
        var normalized = normalizeSourceType(type);
        var classes = 'hg-type-icon hg-type-icon--' + normalized + (extraClass ? ' ' + extraClass : '');
        if (TYPE_ICON_SVG[normalized]) {
            return '<span class="' + classes + '" aria-hidden="true">' + TYPE_ICON_SVG[normalized] + '</span>';
        }
        return '<span class="' + classes + '" aria-hidden="true">' + escapeHtml(TYPE_EMOJI[normalized] || '*') + '</span>';
    }

    function typeChipHtml(type, labelClass) {
        var normalized = normalizeSourceType(type);
        return typeIconHtml(normalized) + '<span' + (labelClass ? ' class="' + labelClass + '"' : '') + '>' + escapeHtml(typeLabel(normalized)) + '</span>';
    }

    function cardGameIconHtml(icon, label) {
        return '<img src="' + escapeHtml(CARD_GAME_ICONS[icon] || '') + '" alt="' + escapeHtml(label || '') + '" width="64" height="64">';
    }

    function combatCardNameHtml(card, extraClass) {
        if (!card || typeof card !== 'object') { return '-'; }
        var classes = 'hg-combat-card-title' + (extraClass ? ' ' + extraClass : '');
        var label = typeLabel(card.source_type) + ' · ' + card.card_name;
        return '<span class="' + classes + '" title="' + escapeHtml(label) + '">' +
            typeIconHtml(card.source_type, 'hg-type-icon--combat') +
            '<span class="hg-combat-card-title__name">' + escapeHtml(card.card_name) + '</span>' +
            '</span>';
    }

    function validCard(card) {
        if (!card || typeof card !== 'object') { return false; }
        if (!Number.isFinite(Number(card.card_id)) || Number(card.card_id) <= 0) { return false; }
        return RARITY_ORDER.indexOf(String(card.card_rarity || '')) !== -1;
    }

    function normalizeCard(card) {
        var hpMin = clampInt(card.hp_min, card.atk_min || 10);
        var hpMax = clampInt(card.hp_max, card.atk_max || 40);
        var atkMin = clampInt(card.atk_min, 10);
        var atkMax = clampInt(card.atk_max, 40);
        var defMin = clampInt(card.def_min, atkMin);
        var defMax = clampInt(card.def_max, atkMax);
        if (hpMax < hpMin) { hpMax = hpMin; }
        if (atkMax < atkMin) { atkMax = atkMin; }
        if (defMax < defMin) { defMax = defMin; }
        return {
            card_id: clampInt(card.card_id, 0),
            source_type: normalizeSourceType(card.source_type),
            source_id: clampInt(card.source_id, 0),
            card_name: String(card.card_name || 'Carta sin nombre'),
            card_text: String(card.card_text || ''),
            card_image_url: String(card.card_image_url || '/img/og/og_image.jpg'),
            card_url: String(card.card_url || ''),
            card_rarity: String(card.card_rarity || 'common'),
            hp_min: hpMin,
            hp_max: hpMax,
            atk_min: atkMin,
            atk_max: atkMax,
            def_min: defMin,
            def_max: defMax
        };
    }

    function loadCatalog() {
        var url = root.getAttribute('data-catalog-url') || '/api/game_cards.php';
        setStatus('Cargando catálogo...');
        return fetch(url, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) { throw new Error('No se pudo cargar el catálogo.'); }
                return res.json();
            })
            .then(function (payload) {
                if (!payload || payload.success !== true || !Array.isArray(payload.cards)) {
                    throw new Error('Catálogo inválido.');
                }
                state.catalog = payload.cards.filter(validCard).map(normalizeCard);
                state.catalogById = {};
                state.catalog.forEach(function (card) {
                    state.catalogById[String(card.card_id)] = card;
                });
                migrateCollectionQuality();
                setStatus(state.catalog.length ? 'Listo.' : 'No hay cartas activas en el catálogo.');
                renderSummary();
                renderCollectionTable();
                renderCombat();
                return state.catalog;
            })
            .catch(function (err) {
                state.catalog = [];
                state.catalogById = {};
                setStatus(err.message || 'No se pudo cargar el catálogo.');
                renderSummary();
                renderCollectionTable();
                renderCombat();
                return [];
            });
    }

    function loadCollection() {
        var raw = '';
        try { raw = window.localStorage.getItem(STORAGE_KEY) || ''; } catch (e) { raw = ''; }
        if (!raw) {
            state.collection = createEmptyCollection();
            return state.collection;
        }
        try {
            state.collection = validateCollection(JSON.parse(raw));
        } catch (e) {
            state.collection = createEmptyCollection();
        }
        state.collection.currency = normalizeCurrency(state.collection.currency);
        state.collection.packInventory = normalizePackInventory(state.collection.packInventory);
        state.collection.materialInventory = normalizeMaterialInventory(state.collection.materialInventory);
        state.collection.workAssignments = normalizeWorkAssignments(state.collection.workAssignments);
        state.collection.workPendingRewards = normalizeWorkPendingRewards(state.collection.workPendingRewards);
        cleanWorkAssignments(false);
        limitWorkAssignments(false);
        return state.collection;
    }

    function saveCollection() {
        if (!state.collection) { state.collection = createEmptyCollection(); }
        state.collection.currency = normalizeCurrency(state.collection.currency);
        state.collection.packInventory = normalizePackInventory(state.collection.packInventory);
        state.collection.materialInventory = normalizeMaterialInventory(state.collection.materialInventory);
        state.collection.updatedAt = nowIso();
        writeJson(STORAGE_KEY, state.collection);
    }

    function packWeights(packKind) {
        return PACK_RARITY_WEIGHTS[packKind] || RARITY_WEIGHTS;
    }

    function packLabel(packKind) {
        return PACK_LABELS[packKind] || PACK_LABELS.standard;
    }

    function cardAllowedForPack(card, packKind) {
        if (packKind === 'echoes') {
            return card.card_rarity === 'common' || card.card_rarity === 'unusual';
        }
        if (packKind === 'characters') {
            return card.source_type === 'character';
        }
        if (packKind === 'lineage') {
            return LINEAGE_TYPES.indexOf(card.source_type) !== -1;
        }
        if (packKind === 'essence') {
            return ESSENCE_TYPES.indexOf(card.source_type) !== -1;
        }
        if (packKind === 'powers') {
            return POWER_TYPES.indexOf(card.source_type) !== -1;
        }
        if (packKind === 'chronicles') {
            return CHRONICLE_TYPES.indexOf(card.source_type) !== -1;
        }
        if (packKind === 'relics') {
            return RELIC_TYPES.indexOf(card.source_type) !== -1;
        }
        if (packKind === 'omens') {
            return RARITY_ORDER.indexOf(card.card_rarity) >= RARITY_ORDER.indexOf('rare');
        }
        if (packKind === 'gaian') {
            return RARITY_ORDER.indexOf(card.card_rarity) >= RARITY_ORDER.indexOf('epic');
        }
        return true;
    }

    function openCardUrl(url) {
        if (!url) { return; }
        window.open(url, '_blank', 'noopener,noreferrer');
    }

    function pickRarity(packKind) {
        var weights = packWeights(packKind);
        var total = 0;
        RARITY_ORDER.forEach(function (rarity) { total += weights[rarity] || 0; });
        var roll = Math.random() * total;
        var acc = 0;
        for (var i = 0; i < RARITY_ORDER.length; i++) {
            var rarity = RARITY_ORDER[i];
            acc += weights[rarity] || 0;
            if (roll <= acc) { return rarity; }
        }
        return 'common';
    }

    function cardExcluded(card, excludedIds) {
        return excludedIds && excludedIds[String(card.card_id)] === true;
    }

    function rarityPool(rarity, packKind, excludedIds) {
        return state.catalog.filter(function (card) {
            return card.card_rarity === rarity
                && cardAllowedForPack(card, packKind)
                && !cardExcluded(card, excludedIds);
        });
    }

    function pickCardByRarity(rarity, packKind, excludedIds) {
        var start = RARITY_ORDER.indexOf(rarity);
        if (start === -1) { start = 0; }
        for (var i = start; i >= 0; i--) {
            var lower = RARITY_ORDER[i];
            var lowerPool = rarityPool(lower, packKind, excludedIds);
            if (lowerPool.length) { return lowerPool[Math.floor(Math.random() * lowerPool.length)]; }
        }
        for (var j = start + 1; j < RARITY_ORDER.length; j++) {
            var higher = RARITY_ORDER[j];
            var higherPool = rarityPool(higher, packKind, excludedIds);
            if (higherPool.length) { return higherPool[Math.floor(Math.random() * higherPool.length)]; }
        }
        if (excludedIds && Object.keys(excludedIds).length) {
            return pickCardByRarity(rarity, packKind, null);
        }
        return null;
    }

    function rollStat(min, max) {
        var low = clampInt(min, 10);
        var high = clampInt(max, low);
        if (high < low) { high = low; }
        return low + Math.floor(Math.random() * (high - low + 1));
    }

    function randomBetween(min, max) {
        return min + (Math.random() * (max - min));
    }

    function soundPaths(value) {
        if (!value) { return []; }
        return Array.isArray(value) ? value.filter(Boolean) : [value];
    }

    function preloadUiSound(path) {
        if (!path || uiSoundCache[path]) { return uiSoundCache[path] || null; }
        try {
            var audio = new Audio(path);
            audio.preload = 'auto';
            audio.load();
            uiSoundCache[path] = audio;
            return audio;
        } catch (e) {
            return null;
        }
    }

    function preloadCombatSounds() {
        if (combatSoundsPreloaded) { return; }
        combatSoundsPreloaded = true;
        Object.keys(COMBAT_SOUNDS).forEach(function (kind) {
            soundPaths(COMBAT_SOUNDS[kind]).forEach(preloadUiSound);
        });
    }

    function playUiSound(path, volume, options) {
        options = options || {};
        try {
            var audio = preloadUiSound(path) || new Audio(path);
            audio.volume = typeof volume === 'number' ? volume : 0.8;
            if (typeof options.playbackRate === 'number') {
                audio.playbackRate = Math.max(0.5, Math.min(2, options.playbackRate));
            }
            try { audio.currentTime = 0; } catch (e2) {}
            var played = audio.play();
            if (played && typeof played.catch === 'function') {
                played.catch(function () {});
            }
        } catch (e) {}
    }

    function playFlipSound() { playUiSound('/sounds/ui/tear.ogg', 0.8); }
    function playCardSound() { playUiSound('/sounds/ui/card.ogg', 0.72); }
    function playMoneySound() { playUiSound('/sounds/ui/money.ogg', 0.78); }
    function playDustSound() { playUiSound('/sounds/ui/dust.ogg', 0.78); }
    function playCombatSound(kind) {
        preloadCombatSounds();
        var sound = COMBAT_SOUNDS[kind] || '';
        var path = Array.isArray(sound) ? sound[Math.floor(Math.random() * sound.length)] : sound;
        var options = {};
        if (kind === 'attack' || kind === 'damage') {
            options.playbackRate = randomBetween(0.92, 1.1);
        }
        if (path) { playUiSound(path, 0.74, options); }
    }

    function openPack(packKind, options) {
        options = options || {};
        packKind = packKind || 'standard';
        if (!state.catalog.length) {
            setStatus('No hay cartas disponibles para abrir sobres.');
            return [];
        }
        if (packKind === 'standard' && packStock('standard') <= 0) {
            setStatus('No tienes sobres mnemónicos disponibles. Compra unidades desde la tienda.');
            return [];
        }
        if (packKind !== 'standard' && packStock(packKind) <= 0) {
            setStatus('No tienes unidades de ' + packLabel(packKind).toLowerCase() + '.');
            return [];
        }
        if (!state.collection) { loadCollection(); }

        var hasPackCards = state.catalog.some(function (card) { return cardAllowedForPack(card, packKind); });
        if (!hasPackCards) {
            setStatus('No hay cartas disponibles para este tipo de sobre.');
            return [];
        }
        var obtained = [];
        var usedCardIds = {};
        for (var i = 0; i < PACK_SIZE; i++) {
            var card = pickCardByRarity(pickRarity(packKind), packKind, usedCardIds);
            if (!card) { continue; }
            usedCardIds[String(card.card_id)] = true;
            var copy = {
                instanceId: instanceId(),
                cardId: card.card_id,
                rarity: card.card_rarity,
                hp: rollStat(card.hp_min, card.hp_max),
                atk: rollStat(card.atk_min, card.atk_max),
                def: rollStat(card.def_min, card.def_max),
                obtainedAt: nowIso()
            };
            copy.quality = calculatedQualityScore(copy, card);
            state.collection.ownedCards.push(copy);
            obtained.push({ catalog: card, instance: copy });
        }

        consumePack(packKind);

        if (!options.deferSave) { saveCollection(); }
        if (!options.silent) {
            if (obtained.length) { playFlipSound(); }
            renderPackResults(obtained);
            renderSummary();
            renderPackInventory();
            showPackReveal(obtained, packKind);
            setStatus(packLabel(packKind) + ': ' + obtained.length + ' cartas obtenidas.');
        }
        return obtained;
    }

    function buyPack(packKind, amount, options) {
        options = options || {};
        amount = Math.max(1, clampInt(amount, 1));
        packKind = packKind || 'standard';
        var isFree = options.free === true;
        if (PACK_KINDS.indexOf(packKind) === -1) {
            setStatus('Ese sobre no existe.');
            return false;
        }
        if (!state.collection) { loadCollection(); }
        var price = packPrice(packKind);
        if (!state.isAdmin && packSpace(packKind) < amount) {
            setStatus('No puedes acumular mas de ' + MAX_PACK_STOCK + ' sobres de cada tipo.');
            renderPackInventory();
            return false;
        }
        if (isFree) {
            if (!claimDailyFreePacks(amount)) {
                setStatus('No puedes reclamar ' + amount + ' sobres gratis. Quedan ' + dailyFreePacksRemaining() + ' hoy.');
                renderDailyCounter();
                renderPackInventory();
                return false;
            }
            playMoneySound();
            addPack('standard', amount, { silent: true, deferSave: true });
            saveCollection();
            renderDailyCounter();
            renderPackInventory();
            setStatus(amount + ' sobre(s) mnemónicos gratis añadidos. Quedan ' + dailyFreePacksRemaining() + ' gratis hoy.');
            return true;
        }
        var totalPrice = price * amount;
        if (!state.isAdmin && currentMnemones() < totalPrice) {
            setStatus('No tienes Mnemones suficientes para comprar ' + packLabel(packKind).toLowerCase() + '.');
            return false;
        }
        if (!state.isAdmin) {
            addMnemones(-totalPrice);
        }
        playMoneySound();
        addPack(packKind, amount, { silent: true, deferSave: true });
        saveCollection();
        renderPackInventory();
        setStatus(amount + ' x ' + packLabel(packKind).toLowerCase() + ' añadidos a tus sobres.');
        return true;
    }

    function buyMaterial(materialKey, amount) {
        amount = Math.max(1, clampInt(amount, 1));
        var material = UPGRADE_MATERIALS[materialKey];
        if (!material) {
            setStatus('Ese objeto no existe.');
            return false;
        }
        if (!state.collection) { loadCollection(); }
        var totalPrice = material.price * amount;
        if (!state.isAdmin && currentMnemones() < totalPrice) {
            setStatus('No tienes Mnemones suficientes para comprar ' + material.label + '.');
            return false;
        }
        if (!state.isAdmin) {
            addMnemones(-totalPrice);
        }
        playMoneySound();
        var newStock = addMaterial(materialKey, amount);
        if (newStock === false) {
            setStatus('No se ha podido anadir ese objeto al inventario.');
            return false;
        }
        saveCollection();
        renderSummary({ light: true });
        setStatus(amount + ' x ' + material.label + ' anadido(s) al inventario. Tienes ' + (state.isAdmin ? 'Admin' : newStock) + '.');
        return true;
    }

    function openAllPacks() {
        if (!state.catalog.length) {
            setStatus('No hay cartas disponibles para abrir sobres.');
            return [];
        }
        if (!state.collection) { loadCollection(); }
        var opened = 0;
        var obtained = [];
        PACK_KINDS.forEach(function (kind) {
            if (!state.isAdmin) {
                while (packStock(kind) > 0) {
                    obtained = obtained.concat(openPack(kind, { silent: true, deferSave: true }));
                    opened += 1;
                }
            }
        });
        if (!opened) {
            setStatus('No te quedan sobres por abrir.');
            renderPackInventory();
            return [];
        }
        if (obtained.length) { playFlipSound(); }
        saveCollection();
        renderPackResults(obtained.slice(-5));
        renderSummary();
        renderPackInventory();
        setStatus('Sobres abiertos: ' + opened + '. Mostrando las últimas 5 cartas obtenidas.');
        return obtained;
    }

    function renderPackResults(cards) {
        if (!els.packResults) { return; }
        els.packResults.innerHTML = '';
        if (state.mobile && cards.length) {
            els.packResults.appendChild(renderMobileCardCarousel(cards, false));
            return;
        }
        cards.forEach(function (entry, index) {
            els.packResults.appendChild(renderCard(entry.catalog, entry.instance, { fresh: true, delay: index * 70 }));
        });
    }

    function collectionGroups() {
        var groups = {};
        var owned = (state.collection && Array.isArray(state.collection.ownedCards)) ? state.collection.ownedCards : [];
        owned.forEach(function (copy) {
            var cardId = String(copy.cardId || '');
            var card = state.catalogById[cardId];
            if (!card) { return; }
            if (!groups[cardId]) { groups[cardId] = { catalog: card, copies: [] }; }
            groups[cardId].copies.push(copy);
        });
        return groups;
    }

    function copyRarity(copy, card) {
        return normalizeRarity(copy && copy.rarity, normalizeRarity(card && card.card_rarity, 'common'));
    }

    function rarityStatRange(rarity) {
        return RARITY_STAT_RANGES[normalizeRarity(rarity, 'common')] || RARITY_STAT_RANGES.common;
    }

    function statBoundsForRarity(card, rarity, stat) {
        rarity = normalizeRarity(rarity, 'common');
        if (card && rarity === normalizeRarity(card.card_rarity, 'common')) {
            var min = clampInt(card[stat + '_min'], rarityStatRange(rarity)[0]);
            var max = clampInt(card[stat + '_max'], rarityStatRange(rarity)[1]);
            return max >= min ? [min, max] : [min, min];
        }
        return rarityStatRange(rarity);
    }

    function statPercentInBounds(value, bounds) {
        var min = bounds[0];
        var max = bounds[1];
        if (max <= min) { return 1; }
        return Math.max(0, Math.min(1, (Number(value || 0) - min) / (max - min)));
    }

    function scaledStatForRarity(copy, card, stat, fromRarity, toRarity) {
        var from = statBoundsForRarity(card, fromRarity, stat);
        var to = statBoundsForRarity(card, toRarity, stat);
        var percent = statPercentInBounds(copy && copy[stat], from);
        return Math.max(to[0], Math.min(to[1], clampInt(to[0] + ((to[1] - to[0]) * percent), to[0])));
    }

    function retuneCopyStatsForRarity(copy, card, fromRarity, toRarity) {
        if (!copy || !card) { return; }
        copy.hp = scaledStatForRarity(copy, card, 'hp', fromRarity, toRarity);
        copy.atk = scaledStatForRarity(copy, card, 'atk', fromRarity, toRarity);
        copy.def = scaledStatForRarity(copy, card, 'def', fromRarity, toRarity);
        copy.rarity = normalizeRarity(toRarity, fromRarity);
        copy.quality = calculatedQualityScore(copy, card);
    }

    function statForQuality(card, rarity, stat, quality) {
        var bounds = statBoundsForRarity(card, rarity, stat);
        var percent = Math.max(0, Math.min(1, Number(quality || 0) / 100));
        return Math.max(bounds[0], Math.min(bounds[1], clampInt(bounds[0] + ((bounds[1] - bounds[0]) * percent), bounds[0])));
    }

    function applyQualityToCopyStats(copy, card, quality) {
        if (!copy || !card) { return; }
        var rarity = copyRarity(copy, card);
        var targetQuality = clampQuality(quality, qualityScore(copy, card));
        copy.hp = statForQuality(card, rarity, 'hp', targetQuality);
        copy.atk = statForQuality(card, rarity, 'atk', targetQuality);
        copy.def = statForQuality(card, rarity, 'def', targetQuality);
        copy.quality = calculatedQualityScore(copy, card);
    }

    function statsBelowRarityFloor(copy, card, rarity) {
        var hpBounds = statBoundsForRarity(card, rarity, 'hp');
        var atkBounds = statBoundsForRarity(card, rarity, 'atk');
        var defBounds = statBoundsForRarity(card, rarity, 'def');
        return (copy.hp || 0) < hpBounds[0] || (copy.atk || 0) < atkBounds[0] || (copy.def || 0) < defBounds[0];
    }

    function cardForCopy(card, copy) {
        if (!card) { return null; }
        var rarity = copyRarity(copy, card);
        if (rarity === card.card_rarity) { return card; }
        var out = {};
        Object.keys(card).forEach(function (key) {
            out[key] = card[key];
        });
        out.card_rarity = rarity;
        return out;
    }

    function copySortValue(copy, card) {
        return (rarityRank(copyRarity(copy, card)) * 100000) + (qualityScore(copy, card) * 100) + totalStats(copy);
    }

    function bestCopy(copies, card) {
        return copies.slice().sort(function (a, b) {
            return copySortValue(b, card) - copySortValue(a, card);
        })[0] || null;
    }

    function totalStats(copy) {
        if (!copy) { return 0; }
        return (copy.hp || 0) + (copy.atk || 0) + (copy.def || 0);
    }

    function calculatedQualityScore(copy, card) {
        if (!copy || !card) { return 0; }
        var rarity = copyRarity(copy, card);
        var hpBounds = statBoundsForRarity(card, rarity, 'hp');
        var atkBounds = statBoundsForRarity(card, rarity, 'atk');
        var defBounds = statBoundsForRarity(card, rarity, 'def');
        var min = hpBounds[0] + atkBounds[0] + defBounds[0];
        var max = hpBounds[1] + atkBounds[1] + defBounds[1];
        var value = totalStats(copy);
        if (max <= min) { return 100; }
        return clampQuality(((value - min) / (max - min)) * 100, 0);
    }

    function qualityScore(copy, card) {
        if (!copy || !card) { return 0; }
        return clampQuality(copy.quality, calculatedQualityScore(copy, card));
    }

    function migrateCollectionQuality() {
        if (!state.collection || !Array.isArray(state.collection.ownedCards)) { return false; }
        var changed = false;
        state.collection.ownedCards.forEach(function (copy) {
            if (!copy || typeof copy !== 'object') { return; }
            var card = state.catalogById[String(copy.cardId || '')];
            if (!card) { return; }
            var rarity = copyRarity(copy, card);
            if (copy.rarity !== rarity) {
                copy.rarity = rarity;
                changed = true;
            }
            if (rarity !== normalizeRarity(card.card_rarity, 'common') && statsBelowRarityFloor(copy, card, rarity)) {
                retuneCopyStatsForRarity(copy, card, card.card_rarity, rarity);
                changed = true;
            }
            var current = clampQuality(copy.quality, null);
            if (current !== null) {
                if (copy.quality !== current) {
                    copy.quality = current;
                    changed = true;
                }
                return;
            }
            copy.quality = calculatedQualityScore(copy, card);
            changed = true;
        });
        if (changed) { saveCollection(); }
        return changed;
    }

    function renderSummary() {
        var light = arguments[0] && arguments[0].light;
        if (light) {
            renderDailyCounter();
            renderShop();
            return;
        }
        var groups = collectionGroups();
        var uniqueCount = Object.keys(groups).length;
        var totalCopies = state.collection && Array.isArray(state.collection.ownedCards) ? state.collection.ownedCards.length : 0;
        if (els.uniqueCounter) { els.uniqueCounter.textContent = uniqueCount + ' / ' + state.catalog.length; }
        if (els.totalCopiesCounter) { els.totalCopiesCounter.textContent = String(totalCopies); }
        renderDailyCounter();
        renderShop();
        renderBulkSellPreview();
        renderWorkBench();
        renderCombatSetup();
    }

    function sortedCatalogCards(cards) {
        return cards.slice().sort(function (a, b) {
            var rankA = TYPE_ORDER.indexOf(a.source_type);
            var rankB = TYPE_ORDER.indexOf(b.source_type);
            if (rankA === -1) { rankA = TYPE_ORDER.length; }
            if (rankB === -1) { rankB = TYPE_ORDER.length; }
            var typeDiff = rankA - rankB;
            if (typeDiff !== 0) { return typeDiff; }
            return cardDisplayId(a) - cardDisplayId(b);
        });
    }

    function cardDisplayId(card) {
        var sourceId = Number(card && card.source_id);
        if (Number.isFinite(sourceId) && sourceId > 0) { return sourceId; }
        return Number(card && card.card_id || 0);
    }

    function cardPassesCollectionFilters(card, groups) {
        if (!card) { return false; }
        if (state.collectionRarity !== 'all') {
            var group = groups[String(card.card_id)];
            var hasRarity = group && group.copies && group.copies.some(function (copy) {
                return copyRarity(copy, card) === state.collectionRarity;
            });
            if (!hasRarity && card.card_rarity !== state.collectionRarity) { return false; }
        }
        if (state.collectionOwnedOnly && !groups[String(card.card_id)]) { return false; }
        return true;
    }

    function albumCategories(groups) {
        var present = {};
        state.catalog.forEach(function (card) {
            if (!cardPassesCollectionFilters(card, groups)) { return; }
            present[card.source_type] = true;
        });
        var ordered = TYPE_ORDER.filter(function (type) {
            return type === 'all' || present[type];
        });
        Object.keys(present).sort().forEach(function (type) {
            if (ordered.indexOf(type) === -1) { ordered.push(type); }
        });
        return ordered.map(function (type) {
            var cards = (type === 'all'
                ? state.catalog
                : state.catalog.filter(function (card) { return card.source_type === type; }))
                .filter(function (card) { return cardPassesCollectionFilters(card, groups); });
            var owned = cards.filter(function (card) {
                return !!groups[String(card.card_id)];
            }).length;
            return {
                type: type,
                label: type === 'all' ? 'Todos' : typeLabel(type),
                total: cards.length,
                owned: owned
            };
        }).filter(function (entry) {
            return entry.type === 'all' || entry.total > 0;
        });
    }

    function ensureAlbumCategory(categories) {
        if (!categories.length) {
            state.albumCategory = 'all';
            return;
        }
        var available = categories.some(function (entry) {
            return entry.type === state.albumCategory;
        });
        if (!available) { state.albumCategory = categories[0].type; }
    }

    function renderAlbumTabs(categories) {
        if (!els.albumTabs) { return; }
        els.albumTabs.innerHTML = '';
        categories.forEach(function (entry) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'hg-album-tabs__button';
            if (entry.type === state.albumCategory) { button.className += ' is-active'; }
            button.setAttribute('data-album-category', entry.type);
            button.setAttribute('role', 'tab');
            button.setAttribute('aria-selected', entry.type === state.albumCategory ? 'true' : 'false');
            var labelHtml = entry.type === 'all'
                ? typeIconHtml('all') + '<span class="hg-album-tabs__label-text">' + escapeHtml(entry.label) + '</span>'
                : typeChipHtml(entry.type, 'hg-album-tabs__label-text');
            button.innerHTML = '<span class="hg-album-tabs__label">' + labelHtml + '</span><strong>' + entry.owned + ' / ' + entry.total + '</strong>';
            button.addEventListener('click', function () {
                state.albumCategory = entry.type;
                state.collectionPage = 1;
                renderCollectionTable();
            });
            els.albumTabs.appendChild(button);
        });
    }

    function renderCollectionTypeFilter(categories) {
        if (!els.collectionTypeFilter) { return; }
        var signature = categories.map(function (entry) {
            return entry.type + ':' + entry.owned + ':' + entry.total;
        }).join('|');
        if (els.collectionTypeFilter.getAttribute('data-options-signature') !== signature) {
            els.collectionTypeFilter.innerHTML = categories.map(function (entry) {
                return '<option value="' + escapeHtml(entry.type) + '">' +
                    escapeHtml(entry.label) + ' (' + entry.owned + '/' + entry.total + ')' +
                    '</option>';
            }).join('');
            els.collectionTypeFilter.setAttribute('data-options-signature', signature);
        }
        els.collectionTypeFilter.value = state.albumCategory;
    }

    function renderAlbumSlot(card, group) {
        var owned = !!(group && group.copies && group.copies.length);
        var best = owned ? bestCopy(group.copies, card) : null;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'hg-album-slot hg-album-slot--' + card.card_rarity + (owned ? ' is-owned' : ' is-empty');
        button.setAttribute('data-card-id', String(card.card_id));
        button.setAttribute('aria-label', owned ? card.card_name : 'Hueco por descubrir #' + cardDisplayId(card));

        var image = document.createElement('span');
        image.className = 'hg-album-slot__image';
        if (owned) {
            var img = document.createElement('img');
            img.src = card.card_image_url || '/img/og/og_image.jpg';
            img.alt = '';
            img.loading = 'lazy';
            img.addEventListener('error', function () { img.src = '/img/og/og_image.jpg'; }, { once: true });
            image.appendChild(img);
        } else {
            image.textContent = '?';
        }

        var meta = document.createElement('span');
        meta.className = 'hg-album-slot__meta';
        meta.textContent = '#' + cardDisplayId(card) + ' · ' + (TYPE_LABELS[card.source_type] || card.source_type);

        meta.innerHTML = '<span>#' + cardDisplayId(card) + '</span>' + typeChipHtml(card.source_type, 'hg-album-slot__type-label');

        var title = document.createElement('strong');
        title.className = 'hg-album-slot__title';
        title.textContent = owned ? card.card_name : 'Carta por descubrir';

        var footer = document.createElement('span');
        footer.className = 'hg-album-slot__footer';
        footer.innerHTML =
            '<span>' + escapeHtml(RARITY_LABELS[card.card_rarity] || card.card_rarity) + '</span>' +
            '<b>' + (owned ? 'x' + group.copies.length : 'Hueco') + '</b>';

        if (owned && best) {
            var score = document.createElement('span');
            score.className = 'hg-album-slot__score';
            score.textContent = 'Total ' + totalStats(best);
            button.appendChild(score);
        }

        button.appendChild(image);
        button.appendChild(meta);
        button.appendChild(title);
        button.appendChild(footer);
        button.addEventListener('click', function () {
            if (!owned) {
                setStatus('Ese hueco todavía no está descubierto.');
                return;
            }
            openCollectionCard(card, group.copies);
        });
        return button;
    }

    function activeMobilePanel() {
        if (!state.mobile) { return ''; }
        var active = els.mobilePanels.filter(function (panel) {
            return panel.classList.contains('is-active');
        })[0] || null;
        return active ? (active.getAttribute('data-mobile-panel') || '') : 'packs';
    }

    function isCollectionContext() {
        return state.view === 'collection' || (state.mobile && activeMobilePanel() === 'collection');
    }

    function isMemoryContext() {
        return state.view === 'collection' || (state.mobile && activeMobilePanel() === 'memory');
    }

    function openCollectionCard(card, copies) {
        playCardSound();
        showCardModal(card, copies || []);
    }

    function applyCollectionMode() {
        els.collectionModeButtons.forEach(function (button) {
            var mode = button.getAttribute('data-collection-mode') || 'album';
            var active = mode === state.collectionMode;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        els.collectionViews.forEach(function (view) {
            var activeView = view.getAttribute('data-collection-view') === state.collectionMode;
            view.hidden = !activeView;
            view.classList.toggle('is-active', activeView);
        });
    }

    function cardsForCurrentCategory(groups) {
        var cards = state.albumCategory === 'all'
            ? state.catalog
            : state.catalog.filter(function (card) { return card.source_type === state.albumCategory; });
        return sortedCatalogCards(cards.filter(function (card) {
            return cardPassesCollectionFilters(card, groups || {});
        }));
    }

    function pageBounds(total) {
        var pageSize = normalizePageSize(state.collectionPageSize);
        var totalPages = Math.max(1, Math.ceil(total / pageSize));
        state.collectionPage = Math.max(1, Math.min(totalPages, clampInt(state.collectionPage, 1)));
        var start = (state.collectionPage - 1) * pageSize;
        var end = Math.min(total, start + pageSize);
        return { start: start, end: end, pageSize: pageSize, totalPages: totalPages };
    }

    function renderPagination(total) {
        var bounds = pageBounds(total);
        els.collectionPagers.forEach(function (pager) {
            pager.innerHTML = '';
            if (total <= 0) { return; }

            var prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'hg-pagination__button';
            prev.textContent = '<';
            prev.disabled = state.collectionPage <= 1;
            prev.setAttribute('aria-label', 'Página anterior');
            prev.addEventListener('click', function () {
                state.collectionPage -= 1;
                renderCollectionTable();
            });

            var label = document.createElement('span');
            label.className = 'hg-pagination__label';
            label.textContent = (bounds.start + 1) + '-' + bounds.end + ' de ' + total + ' · Página ' + state.collectionPage + ' / ' + bounds.totalPages;

            var next = document.createElement('button');
            next.type = 'button';
            next.className = 'hg-pagination__button';
            next.textContent = '>';
            next.disabled = state.collectionPage >= bounds.totalPages;
            next.setAttribute('aria-label', 'Página siguiente');
            next.addEventListener('click', function () {
                state.collectionPage += 1;
                renderCollectionTable();
            });

            pager.appendChild(prev);
            pager.appendChild(label);
            pager.appendChild(next);
        });
        return bounds;
    }

    function renderAlbum(groups) {
        if (!els.albumGrid || !isCollectionContext()) { return 0; }
        groups = groups || collectionGroups();
        var cards = cardsForCurrentCategory(groups);
        var bounds = pageBounds(cards.length);
        var pageCards = cards.slice(bounds.start, bounds.end);
        els.albumGrid.innerHTML = '';
        if (!pageCards.length) {
            var empty = document.createElement('p');
            empty.className = 'hg-empty-state';
            empty.textContent = 'No hay cartas con estos filtros.';
            els.albumGrid.appendChild(empty);
            return cards.length;
        }
        pageCards.forEach(function (card) {
            els.albumGrid.appendChild(renderAlbumSlot(card, groups[String(card.card_id)]));
        });
        return cards.length;
    }

    function tableEntriesForCurrentCategory(groups) {
        groups = groups || collectionGroups();
        return Object.keys(groups).map(function (id) {
            var group = groups[id];
            var card = group.catalog;
            var best = bestCopy(group.copies, card);
            var total = totalStats(best);
            var rowRarity = copyRarity(best, card);
            var obtained = group.copies.slice().sort(function (a, b) {
                return String(b.obtainedAt || '').localeCompare(String(a.obtainedAt || ''));
            })[0];
            return {
                cardId: card.card_id,
                rarity: rowRarity,
                sourceType: card.source_type,
                score: total,
                row: state.mobile ? [
                    qualityScore(best, card).toFixed(1),
                    '<button type="button" class="hg-table-card-btn hg-table-card-btn--mobile hg-rarity-label--' + rowRarity + '" data-card-id="' + card.card_id + '"><strong>' + escapeHtml(card.card_name) + '</strong></button>',
                    '<span class="hg-type-cell hg-type-cell--mobile" title="' + escapeHtml(typeLabel(card.source_type)) + '">' + typeIconHtml(card.source_type, 'hg-type-icon--table') + '</span>',
                    '#' + String(cardDisplayId(card)),
                    total,
                    'x' + group.copies.length
                ] : [
                    '<span class="hg-rarity-label hg-rarity-label--' + rowRarity + '">' + escapeHtml(RARITY_LABELS[rowRarity] || rowRarity) + '</span>',
                    qualityScore(best, card).toFixed(1),
                    '<button type="button" class="hg-table-card-btn" data-card-id="' + card.card_id + '"><strong>' + escapeHtml(card.card_name) + '</strong></button>',
                    '<span class="hg-type-cell">' + escapeHtml(TYPE_EMOJI[card.source_type] || '◦') + ' ' + escapeHtml(TYPE_LABELS[card.source_type] || card.source_type) + '</span>',
                    '#' + String(cardDisplayId(card)),
                    best ? best.hp : 0,
                    best ? best.atk : 0,
                    best ? best.def : 0,
                    total,
                    'x' + group.copies.length,
                    obtained ? formatDate(obtained.obtainedAt) : '-'
                ]
            };
        }).filter(function (entry) {
            return (state.albumCategory === 'all' || entry.sourceType === state.albumCategory)
                && (state.collectionRarity === 'all' || entry.rarity === state.collectionRarity);
        }).sort(function (a, b) {
            return (b.score || 0) - (a.score || 0);
        });
    }

    function renderCollectionTable() {
        if (!isCollectionContext()) { return; }
        var groups = collectionGroups();
        var categories = albumCategories(groups);
        ensureAlbumCategory(categories);
        renderAlbumTabs(categories);
        renderCollectionTypeFilter(categories);
        applyCollectionMode();

        var totalItems = 0;
        if (state.collectionMode === 'album') {
            totalItems = renderAlbum(groups);
        } else {
            var tableRows = tableEntriesForCurrentCategory(groups);
            totalItems = tableRows.length;
            var bounds = pageBounds(totalItems);
            var pageRows = tableRows.slice(bounds.start, bounds.end);
            var tbody = els.collectionTable ? els.collectionTable.querySelector('tbody') : null;
            if (tbody) {
                tbody.innerHTML = pageRows.map(function (entry) {
            return '<tr class="hg-collection-row--' + entry.rarity + '" data-card-id="' + entry.cardId + '">' + entry.row.map(function (cell) { return '<td>' + cell + '</td>'; }).join('') + '</tr>';
                }).join('');
                if (!pageRows.length) {
                    tbody.innerHTML = '<tr><td colspan="' + (state.mobile ? '6' : '11') + '">No hay cartas obtenidas con estos filtros.</td></tr>';
                }
                bindCollectionTableClicks();
            }
        }
        renderPagination(totalItems);
    }

    function bindCollectionTableClicks() {
        if (!els.collectionTable || els.collectionTable.getAttribute('data-hg-bound') === '1') { return; }
        els.collectionTable.setAttribute('data-hg-bound', '1');
        var tbody = els.collectionTable.querySelector('tbody');
        if (!tbody) { return; }
        tbody.addEventListener('click', function (event) {
            var row = event.target.closest('tr[data-card-id]');
            if (!row) { return; }
            var card = state.catalogById[String(row.getAttribute('data-card-id') || '')];
            if (!card) { return; }
            var group = collectionGroups()[String(card.card_id)];
            openCollectionCard(card, group ? group.copies : []);
        });
    }

    function isCombatContext() {
        return state.view === 'combat' || (state.mobile && activeMobilePanel() === 'combat');
    }

    function isCombatLoadoutVisible() {
        return isCombatContext() && state.activeCombatScreen === 'loadout';
    }

    function createEmptyCombatTeams() {
        return {
            version: 1,
            activeTeam: 0,
            teams: [0, 1, 2, 3, 4].map(function (index) {
                return { name: 'Equipo ' + (index + 1), cards: [] };
            })
        };
    }

    function normalizeCombatTeams(data) {
        var out = createEmptyCombatTeams();
        if (!data || typeof data !== 'object') { return out; }
        out.activeTeam = Math.max(0, Math.min(4, clampInt(data.activeTeam, 0)));
        if (Array.isArray(data.teams)) {
            data.teams.slice(0, 5).forEach(function (team, index) {
                if (!team || typeof team !== 'object') { return; }
                out.teams[index] = {
                    name: String(team.name || ('Equipo ' + (index + 1))).slice(0, 40),
                    cards: Array.isArray(team.cards) ? team.cards.map(function (id) {
                        return String(id || '').slice(0, 80);
                    }).filter(Boolean).slice(0, 5) : []
                };
            });
        }
        return out;
    }

    function loadCombatTeams() {
        if (state.combatTeams) { return state.combatTeams; }
        state.combatTeams = normalizeCombatTeams(readJson(COMBAT_TEAMS_KEY, null));
        state.activeCombatTeam = state.combatTeams.activeTeam;
        state.draftCombatTeam = state.combatTeams.teams[state.activeCombatTeam].cards.slice();
        return state.combatTeams;
    }

    function saveCombatTeams() {
        if (!state.combatTeams) { loadCombatTeams(); }
        state.combatTeams.activeTeam = state.activeCombatTeam;
        writeJson(COMBAT_TEAMS_KEY, state.combatTeams);
    }

    function ownedCopyIdMap(availableForCombat) {
        if (!state.collection) { loadCollection(); }
        var ids = {};
        (state.collection.ownedCards || []).forEach(function (copy) {
            if (copy && copy.instanceId && (!availableForCombat || !isCopyWorking(copy.instanceId))) {
                ids[String(copy.instanceId)] = true;
            }
        });
        return ids;
    }

    function pruneDraftCombatTeam(ownedMap, removeMap) {
        var seen = {};
        state.draftCombatTeam = (state.draftCombatTeam || []).filter(function (id) {
            id = String(id || '');
            if (!id || seen[id]) { return false; }
            if (ownedMap && !ownedMap[id]) { return false; }
            if (removeMap && removeMap[id]) { return false; }
            seen[id] = true;
            return true;
        }).slice(0, 5);
    }

    function cleanCombatTeamsAgainstCollection(persist) {
        loadCombatTeams();
        var owned = ownedCopyIdMap(true);
        var changed = false;
        state.combatTeams.teams.forEach(function (team) {
            var seen = {};
            var clean = [];
            (team.cards || []).forEach(function (id) {
                id = String(id || '');
                if (!id || !owned[id] || seen[id] || clean.length >= 5) {
                    changed = true;
                    return;
                }
                seen[id] = true;
                clean.push(id);
            });
            if (clean.length !== (team.cards || []).length) {
                changed = true;
            }
            team.cards = clean;
        });
        if (changed) {
            pruneDraftCombatTeam(owned, null);
            if (persist) { saveCombatTeams(); }
        }
        return changed;
    }

    function removeCopiesFromCombatTeams(removeMap) {
        if (!removeMap) { return 0; }
        loadCombatTeams();
        var removed = 0;
        state.combatTeams.teams.forEach(function (team) {
            team.cards = (team.cards || []).filter(function (id) {
                var remove = !!removeMap[String(id || '')];
                if (remove) { removed += 1; }
                return !remove;
            }).slice(0, 5);
        });
        if (removed > 0) {
            pruneDraftCombatTeam(null, removeMap);
            saveCombatTeams();
        }
        return removed;
    }

    function normalizeCombatProfile(data) {
        data = data && typeof data === 'object' ? data : {};
        return {
            playerName: String(data.playerName || '').slice(0, 32),
            favoriteCard: String(data.favoriteCard || '').slice(0, 80)
        };
    }

    function loadCombatProfile() {
        if (state.combatProfile) { return state.combatProfile; }
        state.combatProfile = normalizeCombatProfile(readJson(COMBAT_PROFILE_KEY, null));
        return state.combatProfile;
    }

    function saveCombatProfile() {
        if (!state.combatProfile) { loadCombatProfile(); }
        writeJson(COMBAT_PROFILE_KEY, state.combatProfile);
    }

    function combatPlayerName() {
        var profile = loadCombatProfile();
        return profile.playerName.trim() || 'Jugador';
    }

    function copyByInstanceId(instanceId) {
        if (!state.collection) { loadCollection(); }
        var id = String(instanceId || '');
        for (var i = 0; i < (state.collection.ownedCards || []).length; i++) {
            var copy = state.collection.ownedCards[i];
            if (String(copy.instanceId || '') === id) { return copy; }
        }
        return null;
    }

    function combatEntryFromCopy(copy) {
        var card = copy ? state.catalogById[String(copy.cardId || '')] : null;
        if (!card) { return null; }
        return { card: cardForCopy(card, copy), baseCard: card, copy: copy, score: totalStats(copy) };
    }

    function combatStatPillHtml(label, value) {
        return '<span><em>' + escapeHtml(label) + '</em><b>' + escapeHtml(value) + '</b></span>';
    }

    function combatEntryStatsHtml(entry, options) {
        if (!entry) { return ''; }
        options = options || {};
        var copy = entry.copy;
        var card = entry.card;
        var hpText = options.currentHp !== undefined
            ? String(options.currentHp) + ' / ' + String(options.maxHp || copy.hp || 0)
            : String(copy.hp || 0);
        var stats = [
            combatStatPillHtml('Total', entry.score),
            combatStatPillHtml('PS', hpText),
            combatStatPillHtml('ATQ', copy.atk || 0),
            combatStatPillHtml('DEF', options.effectiveDef !== undefined ? options.effectiveDef : (copy.def || 0))
        ];
        if (options.includeQuality) {
            stats.push(combatStatPillHtml('CAL', qualityScore(copy, entry.baseCard).toFixed(1) + '%'));
        }
        return '<span class="hg-combat-statline">' + stats.join('') + '</span>' +
            '<small class="hg-combat-card-meta">' +
                '<span>' + escapeHtml(RARITY_LABELS[card.card_rarity] || card.card_rarity) + '</span>' +
                '<span>' + escapeHtml(typeLabel(card.source_type)) + '</span>' +
            '</small>';
    }

    function combatOwnedEntries() {
        if (!state.collection) { loadCollection(); }
        return (state.collection.ownedCards || []).filter(function (copy) {
            return !isCopyWorking(copy.instanceId);
        }).map(combatEntryFromCopy).filter(Boolean).sort(function (a, b) {
            var dateDiff = String(b.copy.obtainedAt || '').localeCompare(String(a.copy.obtainedAt || ''));
            if (dateDiff !== 0) { return dateDiff; }
            return String(b.copy.instanceId || '').localeCompare(String(a.copy.instanceId || ''));
        });
    }

    function renderCombatTypeFilter(entries) {
        if (!els.combatTypeFilter) { return; }
        var counts = { all: entries.length };
        entries.forEach(function (entry) {
            counts[entry.card.source_type] = (counts[entry.card.source_type] || 0) + 1;
        });
        var types = TYPE_ORDER.filter(function (type) {
            return type === 'all' || counts[type] > 0;
        });
        Object.keys(counts).sort().forEach(function (type) {
            if (types.indexOf(type) === -1) { types.push(type); }
        });
        var signature = types.map(function (type) { return type + ':' + counts[type]; }).join('|');
        if (els.combatTypeFilter.getAttribute('data-options-signature') !== signature) {
            els.combatTypeFilter.innerHTML = types.map(function (type) {
                var label = type === 'all' ? 'Todas' : typeLabel(type);
                return '<option value="' + escapeHtml(type) + '">' + escapeHtml(label) + ' (' + (counts[type] || 0) + ')</option>';
            }).join('');
            els.combatTypeFilter.setAttribute('data-options-signature', signature);
        }
        if (!counts[state.combatTypeFilter] && state.combatTypeFilter !== 'all') {
            state.combatTypeFilter = 'all';
        }
        els.combatTypeFilter.value = state.combatTypeFilter;
    }

    function validDraftTeam() {
        var seen = {};
        return state.draftCombatTeam.filter(function (id) {
            if (seen[id] || !copyByInstanceId(id) || isCopyWorking(id)) { return false; }
            seen[id] = true;
            return true;
        }).slice(0, 5);
    }

    function renderCombatTeamSelect() {
        if (!els.combatTeamSelects.length) { return; }
        loadCombatTeams();
        var html = state.combatTeams.teams.map(function (team, index) {
            return '<option value="' + index + '">' + escapeHtml(team.name) + ' (' + team.cards.length + '/5)</option>';
        }).join('');
        els.combatTeamSelects.forEach(function (select) {
            select.innerHTML = html;
            select.value = String(state.activeCombatTeam);
        });
    }

    function renderCombatTeamPreview() {
        if (!els.combatTeamPreviews.length) { return; }
        loadCombatTeams();
        var team = state.combatTeams.teams[state.activeCombatTeam] || state.combatTeams.teams[0];
        var ids = team ? (team.cards || []).slice(0, 5) : [];
        els.combatTeamPreviews.forEach(function (preview) {
            preview.innerHTML = '';
            if (!ids.length) {
                var empty = document.createElement('span');
                empty.className = 'hg-combat-team-preview__empty';
                empty.textContent = 'Equipo vacío. Prepáralo antes de combatir.';
                preview.appendChild(empty);
                return;
            }
            var total = 0;
            for (var i = 0; i < 5; i++) {
                var id = ids[i] || '';
                var entry = combatEntryFromCopy(copyByInstanceId(id));
                var item = document.createElement('span');
                item.className = 'hg-combat-team-preview__card' + (entry ? ' hg-collection-row--' + entry.card.card_rarity : ' is-empty');
                if (entry) {
                    total += entry.score;
                    item.innerHTML =
                        '<strong>' + combatCardNameHtml(entry.card) + '</strong>' +
                        '<small>PS ' + escapeHtml(entry.copy.hp) + ' · ATQ ' + escapeHtml(entry.copy.atk) + ' · DEF ' + escapeHtml(entry.copy.def) + '</small>';
                } else {
                    item.innerHTML = '<strong>Hueco ' + (i + 1) + '</strong><small>Sin carta</small>';
                }
                preview.appendChild(item);
            }
            var totalItem = document.createElement('span');
            totalItem.className = 'hg-combat-team-preview__total';
            totalItem.innerHTML = '<strong>Total equipo</strong><small>' + total + '</small>';
            preview.appendChild(totalItem);
        });
    }

    function renderCombatProfile() {
        if (!els.combatProfileNames.length && !els.combatProfileFavorites.length) { return; }
        var profile = loadCombatProfile();
        els.combatProfileNames.forEach(function (input) {
            if (input.value !== profile.playerName) { input.value = profile.playerName; }
        });
        var entries = combatOwnedEntries();
        var hasFavorite = !profile.favoriteCard;
        var signature = entries.map(function (entry) { return String(entry.copy.instanceId || ''); }).join('|');
        var html = '<option value="">Sin favorita</option>' + entries.map(function (entry) {
            var id = String(entry.copy.instanceId || '');
            if (id === profile.favoriteCard) { hasFavorite = true; }
            return '<option value="' + escapeHtml(id) + '">' + escapeHtml(entry.card.card_name) + ' · ' + escapeHtml(RARITY_LABELS[entry.card.card_rarity] || entry.card.card_rarity) + '</option>';
        }).join('');
        if (!hasFavorite) {
            profile.favoriteCard = '';
            saveCombatProfile();
        }
        els.combatProfileFavorites.forEach(function (select) {
            if (select.getAttribute('data-options-signature') !== signature) {
                select.innerHTML = html;
                select.setAttribute('data-options-signature', signature);
            }
            select.value = profile.favoriteCard;
        });
    }

    function renderCombatTeamSlots() {
        if (!els.combatTeamSlots) { return; }
        state.draftCombatTeam = validDraftTeam();
        els.combatTeamSlots.innerHTML = '';
        var teamTotal = state.draftCombatTeam.reduce(function (sum, id) {
            var entry = combatEntryFromCopy(copyByInstanceId(id));
            return sum + (entry ? entry.score : 0);
        }, 0);
        var summary = document.createElement('div');
        summary.className = 'hg-combat-team-total';
        summary.innerHTML = '<strong>Total del equipo</strong><b>' + teamTotal + '</b><span>' + state.draftCombatTeam.length + ' / 5 cartas</span>';
        els.combatTeamSlots.appendChild(summary);
        for (var i = 0; i < 5; i++) {
            var id = state.draftCombatTeam[i] || '';
            var entry = combatEntryFromCopy(copyByInstanceId(id));
            var slot = document.createElement('button');
            slot.type = 'button';
            slot.className = 'hg-combat-team-slot' + (entry ? ' is-filled' : '');
            slot.setAttribute('data-combat-slot', String(i));
            if (entry) {
                slot.innerHTML =
                    '<strong>' + combatCardNameHtml(entry.card) + '</strong>' +
                    '<span>' + escapeHtml(RARITY_LABELS[entry.card.card_rarity] || entry.card.card_rarity) + ' · Total ' + entry.score + '</span>' +
                    '<small>Quitar</small>';
                slot.innerHTML =
                    '<strong>' + combatCardNameHtml(entry.card) + '</strong>' +
                    combatEntryStatsHtml(entry) +
                    '<small>Quitar</small>';
                slot.addEventListener('click', function () {
                    state.draftCombatTeam.splice(Number(this.getAttribute('data-combat-slot') || 0), 1);
                    renderCombatSetup();
                });
            } else {
                slot.innerHTML = '<strong>Hueco ' + (i + 1) + '</strong><span>Elige una carta</span>';
            }
            els.combatTeamSlots.appendChild(slot);
        }
    }

    function renderCombatCardList() {
        if (!els.combatCardList) { return; }
        var selected = {};
        state.draftCombatTeam.forEach(function (id) { selected[id] = true; });
        var onlyReady = !els.combatOnlyReady || els.combatOnlyReady.checked;
        var allEntries = combatOwnedEntries();
        renderCombatTypeFilter(allEntries);
        var entries = allEntries.filter(function (entry) {
            return (!onlyReady || !selected[String(entry.copy.instanceId || '')])
                && (state.combatRarityFilter === 'all' || entry.card.card_rarity === state.combatRarityFilter)
                && (state.combatTypeFilter === 'all' || entry.card.source_type === state.combatTypeFilter);
        });
        els.combatCardList.innerHTML = '';
        if (!entries.length) {
            var empty = document.createElement('p');
            empty.className = 'hg-empty-state';
            empty.textContent = state.catalog.length ? 'No hay cartas disponibles con esos filtros.' : 'Cargando cartas...';
            els.combatCardList.appendChild(empty);
            return;
        }
        entries.forEach(function (entry) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'hg-combat-card-pick hg-collection-row--' + entry.card.card_rarity;
            button.disabled = state.draftCombatTeam.length >= 5 || !!selected[String(entry.copy.instanceId || '')];
            button.innerHTML =
                '<strong>' + combatCardNameHtml(entry.card) + '</strong>' +
                '<span>' + escapeHtml(typeLabel(entry.card.source_type)) + ' · ' + escapeHtml(RARITY_LABELS[entry.card.card_rarity] || entry.card.card_rarity) + '</span>' +
                '<b>PS ' + escapeHtml(entry.copy.hp) + ' / ATQ ' + escapeHtml(entry.copy.atk) + ' / DEF ' + escapeHtml(entry.copy.def) + '</b>';
            button.innerHTML =
                '<strong>' + combatCardNameHtml(entry.card) + '</strong>' +
                combatEntryStatsHtml(entry, { includeQuality: true });
            button.addEventListener('click', function () {
                if (state.draftCombatTeam.length >= 5) {
                    setCombatMessage('El equipo ya tiene 5 cartas.');
                    return;
                }
                state.draftCombatTeam.push(String(entry.copy.instanceId || ''));
                renderCombatSetup();
            });
            els.combatCardList.appendChild(button);
        });
    }

    function autoBuildCombatTeam(options) {
        options = options || {};
        if (!isCombatContext()) { return false; }
        loadCombatTeams();
        var entries = combatOwnedEntries().filter(function (entry) {
            if (options.ignoreFilters) { return true; }
            return (state.combatRarityFilter === 'all' || entry.card.card_rarity === state.combatRarityFilter)
                && (state.combatTypeFilter === 'all' || entry.card.source_type === state.combatTypeFilter);
        }).sort(function (a, b) {
            return b.score - a.score
                || qualityScore(b.copy, b.baseCard) - qualityScore(a.copy, a.baseCard)
                || rarityRank(b.card.card_rarity) - rarityRank(a.card.card_rarity)
                || String(a.card.card_name).localeCompare(String(b.card.card_name));
        });
        var picked = entries.slice(0, 5).map(function (entry) {
            return String(entry.copy.instanceId || '');
        }).filter(Boolean);
        if (!picked.length) {
            setCombatMessage('No hay cartas disponibles para crear autoequipo con esos filtros.');
            return false;
        }
        if (options.requireFullTeam && picked.length < 5) {
            setCombatMessage('Necesitas al menos 5 cartas disponibles para crear un equipo rapido.');
            return false;
        }
        state.draftCombatTeam = picked;
        state.combatTeams.teams[state.activeCombatTeam].cards = picked.slice();
        saveCombatTeams();
        renderCombatSetup();
        setCombatMessage('Autoequipo guardado: ' + picked.length + '/5 mejores cartas disponibles.');
        return true;
    }

    function promptQuickCombatTeam() {
        return confirmGameAction(
            'Quieres crear un equipo de 5 cartas rapido?',
            { title: 'Equipo rapido', confirmLabel: 'Si, crear equipo', cancelLabel: 'Ahora no' },
            function () {
                if (autoBuildCombatTeam({ ignoreFilters: true, requireFullTeam: true })) {
                    startTrainingCombat();
                }
            }
        );
    }

    function renderCombatSetup() {
        if (!isCombatLoadoutVisible() || !els.combatTeamSlots) { return; }
        cleanCombatTeamsAgainstCollection(true);
        renderCombatTeamSelect();
        renderCombatTeamPreview();
        renderCombatProfile();
        renderCombatTeamSlots();
        renderCombatCardList();
    }

    function showCombatScreen(screen) {
        state.activeCombatScreen = screen === 'loadout' ? 'loadout' : 'battle';
        els.combatScreenTabs.forEach(function (button) {
            var active = (button.getAttribute('data-combat-screen-tab') || 'battle') === state.activeCombatScreen;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        els.combatScreenPanels.forEach(function (panel) {
            var activePanel = (panel.getAttribute('data-combat-screen') || 'battle') === state.activeCombatScreen;
            panel.hidden = !activePanel;
            panel.classList.toggle('is-active', activePanel);
        });
        if (state.activeCombatScreen === 'loadout') { renderCombatSetup(); }
        if (state.activeCombatScreen === 'battle') { renderCombatBattle(); }
    }

    function saveDraftCombatTeam() {
        loadCombatTeams();
        state.draftCombatTeam = validDraftTeam();
        state.combatTeams.teams[state.activeCombatTeam].cards = state.draftCombatTeam.slice();
        saveCombatTeams();
        renderCombatSetup();
        setCombatMessage(state.draftCombatTeam.length + '/5 cartas guardadas en ' + state.combatTeams.teams[state.activeCombatTeam].name + '.');
    }

    function clearDraftCombatTeam() {
        state.draftCombatTeam = [];
        saveDraftCombatTeam();
    }

    function combatDifficultyConfig() {
        var value = els.combatDifficulty ? els.combatDifficulty.value : 'apprentice';
        var configs = {
            apprentice: { label: 'Aprendiz', weights: { common: 72, unusual: 22, rare: 6, epic: 0, legendary: 0, mythic: 0 } },
            hobbyist: { label: 'Aficionado', weights: { common: 44, unusual: 34, rare: 17, epic: 5, legendary: 0, mythic: 0 } },
            expert: { label: 'Experto', weights: { common: 12, unusual: 28, rare: 34, epic: 18, legendary: 6, mythic: 2 } },
            master: { label: 'Maestro', weights: { common: 0, unusual: 8, rare: 32, epic: 36, legendary: 18, mythic: 6 } },
            nemesis: { label: 'Némesis', weights: { common: 0, unusual: 0, rare: 12, epic: 34, legendary: 36, mythic: 18 } }
        };
        return configs[value] || configs.apprentice;
    }

    function combatRewardMultiplier() {
        var value = els.combatDifficulty ? els.combatDifficulty.value : 'apprentice';
        var multipliers = {
            apprentice: 1,
            hobbyist: 1.25,
            expert: 1.5,
            master: 2,
            nemesis: 3
        };
        return multipliers[value] || multipliers.apprentice;
    }

    function createCombatUnit(card, copy, side, index) {
        var shields = rarityShieldCount(card && card.card_rarity);
        return {
            side: side,
            index: index,
            card: card,
            copy: copy,
            hp: Math.max(1, clampInt(copy.hp, 1)),
            maxHp: Math.max(1, clampInt(copy.hp, 1)),
            atk: Math.max(1, clampInt(copy.atk, 1)),
            def: Math.max(1, clampInt(copy.def, 1)),
            shields: shields,
            maxShields: shields,
            defending: false,
            defeated: false
        };
    }

    function rarityRank(rarity) {
        var index = RARITY_ORDER.indexOf(String(rarity || 'common'));
        return index === -1 ? 0 : index;
    }

    function rarityShieldCount(rarity) {
        return rarityRank(rarity) + 1;
    }

    function pickWeightedEnemyRarity(config) {
        var weights = config.weights || RARITY_WEIGHTS;
        var total = RARITY_ORDER.reduce(function (sum, rarity) {
            return sum + Math.max(0, weights[rarity] || 0);
        }, 0);
        var roll = Math.random() * Math.max(1, total);
        var acc = 0;
        for (var i = 0; i < RARITY_ORDER.length; i++) {
            var rarity = RARITY_ORDER[i];
            acc += Math.max(0, weights[rarity] || 0);
            if (roll <= acc) { return rarity; }
        }
        return 'common';
    }

    function pickEnemyCatalogCard(config, excluded) {
        for (var attempt = 0; attempt < 12; attempt++) {
            var rarity = pickWeightedEnemyRarity(config);
            var pool = state.catalog.filter(function (card) {
                return card.card_rarity === rarity && !excluded[String(card.card_id)];
            });
            if (pool.length) { return pool[Math.floor(Math.random() * pool.length)]; }
        }
        var fallback = state.catalog.filter(function (card) {
            return !excluded[String(card.card_id)];
        });
        if (!fallback.length) { fallback = state.catalog.slice(); }
        return fallback.length ? fallback[Math.floor(Math.random() * fallback.length)] : null;
    }

    function createEnemyCard(config, index, excluded) {
        var card = pickEnemyCatalogCard(config, excluded || {});
        if (!card) { return null; }
        excluded[String(card.card_id)] = true;
        var hp = rollStat(card.hp_min, card.hp_max);
        var atk = rollStat(card.atk_min, card.atk_max);
        var def = rollStat(card.def_min, card.def_max);
        return {
            card: card,
            copy: {
                instanceId: 'enemy-' + Date.now() + '-' + index,
                cardId: card.card_id,
                hp: hp,
                atk: atk,
                def: def,
                obtainedAt: nowIso()
            }
        };
    }

    function startTrainingCombat() {
        if (!isCombatContext()) { return false; }
        preloadCombatSounds();
        cleanCombatTeamsAgainstCollection(true);
        var teamIds = validDraftTeam();
        if (teamIds.length !== 5) {
            return promptQuickCombatTeam();
        }
        var playerUnits = teamIds.map(function (id, index) {
            var entry = combatEntryFromCopy(copyByInstanceId(id));
            return entry ? createCombatUnit(entry.card, entry.copy, 'player', index) : null;
        }).filter(Boolean);
        if (playerUnits.length !== 5) {
            setCombatMessage('Alguna carta del equipo ya no existe en la colección.');
            return false;
        }
        var config = combatDifficultyConfig();
        var excludedEnemies = {};
        var enemyUnits = [0, 1, 2, 3, 4].map(function (index) {
            var enemy = createEnemyCard(config, index, excludedEnemies);
            return enemy ? createCombatUnit(enemy.card, enemy.copy, 'enemy', index) : null;
        }).filter(Boolean);
        if (enemyUnits.length !== 5) {
            setCombatMessage('No hay suficientes cartas en el catálogo para generar rival.');
            return false;
        }
        state.combat = {
            mode: 'training',
            difficultyLabel: config.label,
            rewardMultiplier: combatRewardMultiplier(),
            player: playerUnits,
            enemy: enemyUnits,
            playerActive: 0,
            enemyActive: 0,
            over: false,
            result: '',
            reward: 0,
            log: []
        };
        pushCombatLog('Entrenamiento contra ' + config.label + '.');
        pushCombatLog(combatPlayerName() + ' saca una carta.');
        pushCombatLog('El rival saca una carta.');
        setCombatMessage('¡Combate iniciado!');
        showCombatScreen('battle');
        renderCombatBattle();
        animateCombatEntry('player');
        animateCombatEntry('enemy');
        return true;
    }

    function activeCombatUnit(side) {
        if (!state.combat) { return null; }
        var list = side === 'enemy' ? state.combat.enemy : state.combat.player;
        var index = side === 'enemy' ? state.combat.enemyActive : state.combat.playerActive;
        return list[index] || null;
    }

    function livingCombatIndexes(side) {
        if (!state.combat) { return []; }
        var list = side === 'enemy' ? state.combat.enemy : state.combat.player;
        return list.map(function (unit, index) {
            return unit && !unit.defeated && unit.hp > 0 ? index : -1;
        }).filter(function (index) { return index >= 0; });
    }

    function effectiveDef(unit) {
        return Math.round((unit.def || 0) * (unit.defending ? 1.5 : 1));
    }

    function combatDamage(attacker, defender) {
        var base = Math.max(1, Math.round((attacker.atk || 0) - effectiveDef(defender)));
        var rarityDiff = rarityRank(attacker.card && attacker.card.card_rarity) - rarityRank(defender.card && defender.card.card_rarity);
        var multiplier = rarityDiff >= 0
            ? 1 + (rarityDiff * 0.2)
            : Math.max(0.35, 1 + (rarityDiff * 0.13));
        var randomExtra = Math.max(1, Math.round(rollStat(1, 20) * multiplier));
        return Math.max(1, base + randomExtra);
    }

    function healDefendingUnit(unit) {
        var amount = Math.max(1, Math.round(unit.maxHp * 0.33));
        var before = unit.hp;
        unit.hp = Math.min(unit.maxHp, unit.hp + amount);
        return unit.hp - before;
    }

    function applyCombatDamage(target, amount) {
        target.hp = Math.max(0, target.hp - amount);
        target.defending = false;
        if (target.hp <= 0) {
            target.defeated = true;
        }
    }

    function awardTrainingVictory() {
        var multiplier = state.combat ? Math.max(1, Number(state.combat.rewardMultiplier) || 1) : 1;
        var reward = clampInt(5 * rollStat(1, 5) * multiplier, 5);
        addMnemones(reward);
        saveCollection();
        renderSummary();
        return reward;
    }

    function advanceDefeatedSide(side) {
        if (!state.combat) { return false; }
        var living = livingCombatIndexes(side);
        if (!living.length) {
            state.combat.over = true;
            if (side === 'enemy') {
                state.combat.enemyActive = -1;
            } else {
                state.combat.playerActive = -1;
            }
            if (side === 'enemy') {
                var reward = awardTrainingVictory();
                state.combat.result = 'victory';
                state.combat.reward = reward;
                setCombatMessage('Victoria de entrenamiento. +' + reward + ' Mnemones.');
                pushCombatLog('Has vencido al equipo rival. Ganas ' + reward + ' Mnemones.');
            } else {
                state.combat.result = 'defeat';
                state.combat.reward = 0;
                setCombatMessage('Derrota de entrenamiento.');
                pushCombatLog('Tu equipo ha caído. No pierdes cartas en entrenamiento.');
            }
            return false;
        }
        if (side === 'enemy') {
            state.combat.enemyActive = living[0];
            pushCombatLog('El rival saca una carta.');
        } else {
            state.combat.playerActive = living[0];
            pushCombatLog(combatPlayerName() + ' saca una carta.');
        }
        return true;
    }

    function playerAttack() {
        if (!state.combat || state.combat.over || state.combatAnimating) { return; }
        var player = activeCombatUnit('player');
        var enemy = activeCombatUnit('enemy');
        if (!player || !enemy) { return; }
        setCombatBusy(true);
        player.defending = false;
        var damage = combatDamage(player, enemy);
        applyCombatDamage(enemy, damage);
        pushCombatLog(player.card.card_name + ' ataca y causa ' + damage + ' puntos de daño.');
        var defeatedEnemy = enemy.defeated;
        if (defeatedEnemy) {
            pushCombatLog(enemy.card.card_name + ' cae.');
        }
        renderCombatBattle();
        animateCombatAttack('player', 'enemy', damage);
        window.setTimeout(function () {
            if (defeatedEnemy && state.combat && !state.combat.over) {
                resolveDefeatedSide('enemy', function () {
                    setCombatBusy(false);
                });
                return;
            }
            if (!state.combat || state.combat.over) {
                setCombatBusy(false);
                return;
            }
            var enemyAction = enemyTurn();
            renderCombatBattle();
            animateEnemyAction(enemyAction);
            finishEnemyAction(enemyAction);
        }, COMBAT_ATTACK_MS + COMBAT_TURN_GAP_MS);
    }

    function playerDefend() {
        if (!state.combat || state.combat.over || state.combatAnimating) { return; }
        var player = activeCombatUnit('player');
        if (!player) { return; }
        if (player.shields <= 0) {
            setCombatMessage('Esta carta ya no tiene escudos.');
            return;
        }
        setCombatBusy(true);
        player.shields = Math.max(0, player.shields - 1);
        player.defending = true;
        var healed = healDefendingUnit(player);
        pushCombatLog(player.card.card_name + ' gasta 1 escudo, defiende y recupera ' + healed + ' PS.');
        renderCombatBattle();
        animateCombatDefend('player');
        window.setTimeout(function () {
            if (!state.combat || state.combat.over) {
                setCombatBusy(false);
                return;
            }
            var enemyAction = enemyTurn();
            renderCombatBattle();
            animateEnemyAction(enemyAction);
            finishEnemyAction(enemyAction);
        }, COMBAT_DEFEND_MS);
    }

    function switchPlayerCard(index, consumeTurn) {
        if (!state.combat || state.combat.over || state.combatAnimating) { return; }
        index = clampInt(index, state.combat.playerActive);
        var unit = state.combat.player[index];
        if (!unit || unit.defeated || unit.hp <= 0 || index === state.combat.playerActive) { return; }
        setCombatBusy(true);
        activeCombatUnit('player').defending = false;
        state.combat.playerActive = index;
        pushCombatLog('Cambias a ' + unit.card.card_name + '.');
        playCombatSound('switch');
        renderCombatBattle();
        animateCombatEntry('player');
        if (!consumeTurn) {
            window.setTimeout(function () {
                setCombatBusy(false);
            }, COMBAT_ENTRY_MS);
            return;
        }
        window.setTimeout(function () {
            if (!state.combat || state.combat.over) {
                setCombatBusy(false);
                return;
            }
            var enemyAction = enemyTurn();
            renderCombatBattle();
            animateEnemyAction(enemyAction);
            finishEnemyAction(enemyAction);
        }, COMBAT_ENTRY_MS);
    }

    function fleeCombat() {
        if (!state.combat || state.combat.over || state.combatAnimating) { return; }
        state.combat.over = true;
        pushCombatLog('Huyes del entrenamiento. Sin coste y sin pérdida de cartas.');
        setCombatMessage('Combate finalizado porque has huído.');
        renderCombatBattle();
    }

    function enemyTurn() {
        if (!state.combat || state.combat.over) { return null; }
        var enemy = activeCombatUnit('enemy');
        var player = activeCombatUnit('player');
        if (!enemy || !player) { return null; }
        enemy.defending = false;
        var shouldDefend = enemy.shields > 0 && enemy.hp < enemy.maxHp * 0.35 && Math.random() < 0.34;
        if (shouldDefend) {
            enemy.shields = Math.max(0, enemy.shields - 1);
            enemy.defending = true;
            var healed = healDefendingUnit(enemy);
            pushCombatLog(enemy.card.card_name + ' gasta 1 escudo, defiende y recupera ' + healed + ' PS.');
            return { type: 'defend', side: 'enemy' };
        }
        var damage = combatDamage(enemy, player);
        applyCombatDamage(player, damage);
        pushCombatLog(enemy.card.card_name + ' ataca e inflige ' + damage + ' PS.');
        if (player.defeated) {
            pushCombatLog(player.card.card_name + ' cae.');
        }
        return { type: 'attack', side: 'enemy', target: 'player', damage: damage, defeatedTarget: player.defeated };
    }

    function animateEnemyAction(action) {
        if (!action) { return; }
        if (action.type === 'defend') {
            animateCombatDefend('enemy');
        } else if (action.type === 'attack') {
            animateCombatAttack('enemy', 'player', action.damage);
        }
    }

    function finishEnemyAction(action) {
        window.setTimeout(function () {
            if (action && action.defeatedTarget && state.combat && !state.combat.over) {
                resolveDefeatedSide('player', function () {
                    setCombatBusy(false);
                });
                return;
            }
            setCombatBusy(false);
        }, action && action.type === 'defend' ? COMBAT_DEFEND_MS : COMBAT_ATTACK_MS);
    }

    function setCombatMessage(message) {
        if (els.combatMessage) { els.combatMessage.textContent = message; }
        setStatus(message);
    }

    function pushCombatLog(message) {
        if (!state.combat) { return; }
        state.combat.log.unshift(message);
        state.combat.log = state.combat.log.slice(0, 8);
    }

    function combatStand(side) {
        return side === 'enemy' ? els.combatEnemyCard : els.combatPlayerCard;
    }

    function restartCombatAnimation(node, className, duration) {
        if (!node) { return; }
        node.classList.remove(className);
        void node.offsetWidth;
        node.classList.add(className);
        window.setTimeout(function () {
            node.classList.remove(className);
        }, duration || 620);
    }

    function showCombatDamage(side, amount) {
        var stand = combatStand(side);
        if (!stand || !amount) { return; }
        var number = document.createElement('span');
        number.className = 'hg-combat-damage';
        number.textContent = '-' + amount;
        stand.appendChild(number);
        window.setTimeout(function () {
            number.remove();
        }, 900);
    }

    function animateCombatAttack(attackerSide, targetSide, damage) {
        playCombatSound('attack');
        restartCombatAnimation(combatStand(attackerSide), attackerSide === 'enemy' ? 'is-attacking-enemy' : 'is-attacking-player');
        restartCombatAnimation(combatStand(targetSide), 'is-hit');
        showCombatDamage(targetSide, damage);
        if (damage > 0) {
            window.setTimeout(function () {
                playCombatSound('damage');
            }, COMBAT_HIT_SOUND_DELAY_MS);
        }
    }

    function animateCombatDefend(side) {
        playCombatSound('defend');
        restartCombatAnimation(combatStand(side), 'is-defending', COMBAT_DEFEND_MS);
    }

    function animateCombatDefeat(side) {
        playCombatSound('defeat');
        restartCombatAnimation(combatStand(side), 'is-defeated', COMBAT_DEFEAT_MS);
    }

    function animateCombatEntry(side) {
        restartCombatAnimation(combatStand(side), side === 'enemy' ? 'is-entering-enemy' : 'is-entering-player', COMBAT_ENTRY_MS);
    }

    function resolveDefeatedSide(side, done) {
        window.setTimeout(function () {
            if (!state.combat) {
                if (done) { done(); }
                return;
            }
            animateCombatDefeat(side);
            window.setTimeout(function () {
                var advanced = advanceDefeatedSide(side);
                renderCombatBattle();
                if (advanced) {
                    animateCombatEntry(side);
                    window.setTimeout(function () {
                        if (done) { done(); }
                    }, COMBAT_ENTRY_MS);
                    return;
                }
                if (done) { done(); }
            }, COMBAT_DEFEAT_MS);
        }, COMBAT_TURN_GAP_MS);
    }

    function setCombatBusy(value) {
        state.combatAnimating = !!value;
        renderCombatActionState();
    }

    function renderCombatActionState() {
        var combat = state.combat;
        var combatInProgress = !!combat && !combat.over;
        var active = !!combat && !combat.over && !state.combatAnimating;
        var player = activeCombatUnit('player');
        root.classList.toggle('is-combat-active', combatInProgress);
        if (els.combatStart) { els.combatStart.hidden = combatInProgress; }
        els.combatSetups.forEach(function (setup) {
            setup.classList.toggle('is-combat-running', combatInProgress);
        });
        els.combatActions.forEach(function (button) {
            var action = button.getAttribute('data-combat-action') || '';
            button.disabled = !active
                || (action === 'switch' && livingCombatIndexes('player').length <= 1)
                || (action === 'defend' && (!player || player.shields <= 0));
        });
    }

    function renderCombatShields(unit, node) {
        if (!node) { return; }
        node.innerHTML = '';
        var max = unit ? Math.max(0, clampInt(unit.maxShields, 0)) : 0;
        var current = unit ? Math.max(0, clampInt(unit.shields, 0)) : 0;
        for (var i = 0; i < max; i++) {
            var shield = document.createElement('span');
            shield.className = i < current ? 'is-active' : 'is-spent';
            shield.setAttribute('aria-hidden', 'true');
            node.appendChild(shield);
        }
        node.setAttribute('title', unit ? 'Escudos ' + current + ' / ' + max : 'Escudos 0 / 0');
    }

    function renderCombatUnit(unit, cardWrap, nameNode, hpNode, hpBar, shieldNode, atkNode, defNode) {
        if (nameNode) {
            if (unit) {
                nameNode.innerHTML = combatCardNameHtml(unit.card, 'hg-combat-card-title--hud');
            } else {
                nameNode.textContent = '-';
            }
        }
        if (hpNode) { hpNode.textContent = unit ? 'PS ' + unit.hp + ' / ' + unit.maxHp : 'PS 0 / 0'; }
        if (hpBar) { hpBar.style.width = unit ? Math.max(0, Math.min(100, (unit.hp / unit.maxHp) * 100)) + '%' : '0%'; }
        renderCombatShields(unit, shieldNode);
        if (atkNode) { atkNode.textContent = unit ? String(unit.atk) : '0'; }
        if (defNode) { defNode.textContent = unit ? String(effectiveDef(unit)) : '0'; }
        if (cardWrap) {
            var currentId = cardWrap.getAttribute('data-combat-instance') || '';
            var nextId = unit ? String(unit.copy && unit.copy.instanceId || '') : '';
            if (!unit) {
                cardWrap.innerHTML = '';
                cardWrap.removeAttribute('data-combat-instance');
            } else if (currentId !== nextId || !cardWrap.firstElementChild) {
                cardWrap.innerHTML = '';
                var cardNode = renderCard(unit.card, unit.copy, { noLink: true, combatUnit: true });
                cardNode.classList.add('hg-card--combat-unit');
                cardWrap.appendChild(cardNode);
                cardWrap.setAttribute('data-combat-instance', nextId);
            }
        }
    }

    function renderCombatBench() {
        if (!els.combatBench || !state.combat) { return; }
        els.combatBench.innerHTML = '';
        if (state.combat.over) {
            els.combatBench.hidden = true;
            return;
        }
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'hg-combat-bench__cancel';
        cancel.innerHTML = '<strong>Cancelar</strong><span>Volver</span>';
        cancel.addEventListener('click', function () {
            els.combatBench.hidden = true;
        });
        els.combatBench.appendChild(cancel);
        state.combat.player.forEach(function (unit, index) {
            if (index === state.combat.playerActive || unit.defeated || unit.hp <= 0) { return; }
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'hg-combat-bench-card hg-collection-row--' + unit.card.card_rarity;
            button.innerHTML =
                '<strong>' + combatCardNameHtml(unit.card) + '</strong>' +
                '<span class="hg-combat-statline hg-combat-statline--switch">' +
                    combatStatPillHtml('PS', unit.hp + ' / ' + unit.maxHp) +
                    combatStatPillHtml('ATQ', unit.atk) +
                    combatStatPillHtml('DEF', effectiveDef(unit)) +
                '</span>';
            button.addEventListener('click', function () {
                els.combatBench.hidden = true;
                switchPlayerCard(index, true);
            });
            els.combatBench.appendChild(button);
        });
        if (els.combatBench.children.length <= 1) {
            var empty = document.createElement('span');
            empty.textContent = 'No hay cartas disponibles para cambiar.';
            els.combatBench.appendChild(empty);
        }
    }

    function combatScreenElement() {
        return els.combatPlayerCard ? els.combatPlayerCard.closest('.hg-combat-screen') : null;
    }

    function renderCombatEndOverlay() {
        var screen = combatScreenElement();
        if (!screen) { return; }
        var current = screen.querySelector('.hg-combat-end');
        if (current) { current.remove(); }
        if (!state.combat || !state.combat.over || !state.combat.result) { return; }

        var victory = state.combat.result === 'victory';
        var overlay = document.createElement('div');
        overlay.className = 'hg-combat-end hg-combat-end--' + (victory ? 'victory' : 'defeat');

        var panel = document.createElement('div');
        panel.className = 'hg-combat-end__panel';

        var title = document.createElement('h3');
        title.textContent = victory ? '¡Superaste el entrenamiento!' : '¡Te han derrotado!';

        var text = document.createElement('p');
        text.textContent = victory
            ? 'Recompensa: +' + clampInt(state.combat.reward, 0) + ' Mnemones.'
            : 'No pierdes cartas en entrenamiento.';

        var restart = document.createElement('button');
        restart.type = 'button';
        restart.className = 'hg-combat-end__restart';
        restart.textContent = 'Empezar otro combate';
        restart.addEventListener('click', startTrainingCombat);

        panel.appendChild(title);
        panel.appendChild(text);
        panel.appendChild(restart);
        overlay.appendChild(panel);
        screen.appendChild(overlay);
    }

    function renderCombatBattle() {
        if (!isCombatContext() || !els.combatPlayerCard) { return; }
        var combat = state.combat;
        var player = activeCombatUnit('player');
        var enemy = activeCombatUnit('enemy');
        renderCombatUnit(player, els.combatPlayerCard, els.combatPlayerName, els.combatPlayerHp, els.combatPlayerHpBar, els.combatPlayerShields, els.combatPlayerAtk, els.combatPlayerDef);
        renderCombatUnit(enemy, els.combatEnemyCard, els.combatEnemyName, els.combatEnemyHp, els.combatEnemyHpBar, els.combatEnemyShields, els.combatEnemyAtk, els.combatEnemyDef);
        renderCombatActionState();
        if (els.combatLog) {
            els.combatLog.innerHTML = combat && combat.log.length
                ? combat.log.map(function (line) { return '<p>' + escapeHtml(line) + '</p>'; }).join('')
                : '<p>El registro del combate aparecerá aquí.</p>';
        }
        renderCombatBench();
        renderCombatEndOverlay();
    }

    function renderCombat() {
        if (!isCombatContext()) { return; }
        if (isCombatLoadoutVisible()) { renderCombatSetup(); }
        showCombatScreen(state.activeCombatScreen);
        renderCombatBattle();
    }

    function renderCard(card, copy, options) {
        options = options || {};
        var rarityKey = copyRarity(copy, card);
        var article = document.createElement('article');
        article.className = 'hg-card hg-card--' + rarityKey;
        article.setAttribute('data-rarity', RARITY_LABELS[rarityKey] || rarityKey);
        article.setAttribute('data-type', card.source_type);
        if (card.card_url && !options.noLink) {
            article.className += ' hg-card--linked';
            article.setAttribute('role', 'link');
            article.setAttribute('tabindex', '0');
            article.setAttribute('title', 'Abrir ' + card.card_name);
            article.addEventListener('click', function () {
                openCardUrl(card.card_url);
            });
            article.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openCardUrl(card.card_url);
                }
            });
        }
        if (options.fresh) {
            article.className += ' hg-card--fresh';
            article.style.animationDelay = (options.delay || 0) + 'ms';
        }
        if (options.reveal) {
            article.className += ' hg-card--reveal';
            article.style.animationDelay = (options.delay || 0) + 'ms';
        }

        var head = document.createElement('div');
        head.className = 'hg-card__head';
        var rarity = document.createElement('span');
        rarity.className = 'hg-card__short';
        rarity.setAttribute('aria-label', RARITY_LABELS[rarityKey] || rarityKey);
        var iconUrl = RARITY_ICONS[rarityKey] || '';
        if (iconUrl) {
            var rarityIcon = document.createElement('img');
            rarityIcon.src = iconUrl;
            rarityIcon.alt = RARITY_LABELS[rarityKey] || rarityKey;
            rarityIcon.loading = 'lazy';
            rarityIcon.addEventListener('error', function () {
                rarity.textContent = RARITY_SHORT[rarityKey] || '?';
            }, { once: true });
            rarity.appendChild(rarityIcon);
        } else {
            rarity.textContent = RARITY_SHORT[rarityKey] || '?';
        }
        var title = document.createElement('h4');
        if (options.combatUnit) {
            title.innerHTML = combatCardNameHtml(card, 'hg-combat-card-title--card');
        } else {
            title.textContent = card.card_name;
        }
        head.appendChild(rarity);
        head.appendChild(title);

        var imageWrap = document.createElement('div');
        imageWrap.className = 'hg-card__image';
        var img = document.createElement('img');
        img.src = card.card_image_url || '/img/og/og_image.jpg';
        img.alt = card.card_name;
        img.loading = 'lazy';
        img.addEventListener('error', function () { img.src = '/img/og/og_image.jpg'; }, { once: true });
        imageWrap.appendChild(img);

        var body = document.createElement('div');
        body.className = 'hg-card__body';
        var meta = document.createElement('div');
        meta.className = 'hg-card__meta';
        meta.textContent = (TYPE_EMOJI[card.source_type] || '◦') + ' ' + (TYPE_LABELS[card.source_type] || card.source_type) + ' · ' + (RARITY_LABELS[rarityKey] || rarityKey);
        meta.innerHTML = '<span class="hg-card__type">' + typeChipHtml(card.source_type, 'hg-card__type-label') + '</span><span class="hg-card__rarity-name">' + escapeHtml(RARITY_LABELS[rarityKey] || rarityKey) + '</span>';
        var text = document.createElement('p');
        text.className = 'hg-card__text';
        text.textContent = card.card_text || 'Sin texto de carta.';
        body.appendChild(meta);
        body.appendChild(text);

        var stats = document.createElement('div');
        stats.className = 'hg-card__stats';
        stats.appendChild(statNode('PS', copy ? copy.hp : card.hp_min + '-' + card.hp_max));
        stats.appendChild(statNode('ATQ', copy ? copy.atk : card.atk_min + '-' + card.atk_max));
        stats.appendChild(statNode('DEF', copy ? copy.def : card.def_min + '-' + card.def_max));

        article.appendChild(head);
        article.appendChild(imageWrap);
        article.appendChild(body);
        article.appendChild(stats);
        return article;
    }

    function showPackReveal(cards, packKind) {
        if (!cards.length || state.view !== 'gacha') { return; }
        closePackReveal();
        var overlay = document.createElement('div');
        overlay.className = 'hg-pack-reveal';
        if (state.mobile) { overlay.className += ' hg-pack-reveal--mobile'; }
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Cartas obtenidas');

        var panel = document.createElement('div');
        panel.className = 'hg-pack-reveal__panel';
        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        var head = document.createElement('div');
        head.className = 'hg-pack-reveal__head';
        var title = document.createElement('h3');
        title.textContent = packLabel(packKind);
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'hg-pack-reveal__close';
        close.textContent = 'Cerrar';
        close.addEventListener('click', closePackReveal);
        head.appendChild(title);
        head.appendChild(close);

        var grid = state.mobile ? renderMobileCardCarousel(cards, true) : document.createElement('div');
        if (!state.mobile) {
            grid.className = 'hg-pack-reveal__grid';
            cards.forEach(function (entry, index) {
                grid.appendChild(renderCard(entry.catalog, entry.instance, { reveal: true, delay: index * 110 }));
            });
        }

        panel.appendChild(head);
        panel.appendChild(grid);
        overlay.appendChild(panel);
        overlay.addEventListener('click', closePackReveal);
        document.body.appendChild(overlay);
        document.addEventListener('keydown', packRevealEscapeHandler);
        close.focus();
    }

    function renderMobileCardCarousel(cards, reveal) {
        var current = 0;
        var wrap = document.createElement('div');
        wrap.className = 'hg-mobile-carousel';

        var frame = document.createElement('div');
        frame.className = 'hg-mobile-carousel__frame';

        var prev = document.createElement('button');
        prev.type = 'button';
        prev.className = 'hg-mobile-carousel__nav hg-mobile-carousel__nav--prev';
        prev.setAttribute('aria-label', 'Carta anterior');
        prev.textContent = '<';

        var next = document.createElement('button');
        next.type = 'button';
        next.className = 'hg-mobile-carousel__nav hg-mobile-carousel__nav--next';
        next.setAttribute('aria-label', 'Carta siguiente');
        next.textContent = '>';

        var counter = document.createElement('div');
        counter.className = 'hg-mobile-carousel__counter';

        var dots = document.createElement('div');
        dots.className = 'hg-mobile-carousel__dots';

        function renderCurrent() {
            var entry = cards[current];
            frame.innerHTML = '';
            frame.appendChild(renderCard(entry.catalog, entry.instance, { reveal: reveal, fresh: !reveal, delay: 0 }));
            counter.textContent = (current + 1) + ' / ' + cards.length;
            dots.querySelectorAll('button').forEach(function (dot, index) {
                dot.classList.toggle('is-active', index === current);
            });
            prev.disabled = current <= 0;
            next.disabled = current >= cards.length - 1;
        }

        function moveTo(index, withSound) {
            var nextIndex = Math.max(0, Math.min(cards.length - 1, index));
            if (nextIndex === current) { return; }
            current = nextIndex;
            if (withSound) { playCardSound(); }
            renderCurrent();
        }

        cards.forEach(function (entry, index) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.setAttribute('aria-label', 'Ver carta ' + (index + 1));
            dot.addEventListener('click', function () {
                moveTo(index, true);
            });
            dots.appendChild(dot);
        });

        prev.addEventListener('click', function () {
            moveTo(current - 1, true);
        });
        next.addEventListener('click', function () {
            moveTo(current + 1, true);
        });

        var touchStart = null;
        frame.addEventListener('touchstart', function (event) {
            touchStart = event.touches && event.touches[0] ? event.touches[0].clientX : null;
        }, { passive: true });
        frame.addEventListener('touchend', function (event) {
            if (touchStart === null || !event.changedTouches || !event.changedTouches[0]) { return; }
            var delta = event.changedTouches[0].clientX - touchStart;
            if (Math.abs(delta) > 38) {
                moveTo(delta < 0 ? current + 1 : current - 1, true);
            }
            touchStart = null;
        }, { passive: true });

        wrap.appendChild(prev);
        wrap.appendChild(frame);
        wrap.appendChild(next);
        wrap.appendChild(counter);
        wrap.appendChild(dots);
        renderCurrent();
        return wrap;
    }

    function closePackReveal() {
        var current = document.querySelector('.hg-pack-reveal');
        if (current) {
            current.remove();
        }
        document.removeEventListener('keydown', packRevealEscapeHandler);
    }

    function packRevealEscapeHandler(event) {
        if (event.key === 'Escape') {
            closePackReveal();
        }
    }

    function sortedCopies(copies, card) {
        return (Array.isArray(copies) ? copies : []).slice().sort(function (a, b) {
            return copySortValue(b, card) - copySortValue(a, card);
        });
    }

    function showCardModal(card, copies) {
        closeCardModal();
        var sorted = sortedCopies(copies, card);
        var selected = sorted[0] || null;
        var overlay = document.createElement('div');
        overlay.className = 'hg-card-modal';
        if (state.mobile) { overlay.className += ' hg-card-modal--mobile'; }
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', card.card_name);

        var panel = document.createElement('div');
        panel.className = 'hg-card-modal__panel hg-card-modal__panel--variants';
        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'hg-card-modal__close';
        close.setAttribute('aria-label', 'Cerrar carta');
        close.textContent = '×';
        close.addEventListener('click', closeCardModal);

        panel.appendChild(close);

        var cardWrap = document.createElement('div');
        cardWrap.className = 'hg-card-modal__card';
        cardWrap.appendChild(renderCard(card, selected, {}));
        panel.appendChild(cardWrap);

        if (sorted.length > 0) {
            panel.appendChild(renderCopyList(card, sorted, cardWrap));
        }

        overlay.appendChild(panel);
        overlay.addEventListener('click', closeCardModal);
        document.body.appendChild(overlay);
        document.addEventListener('keydown', modalEscapeHandler);
        close.focus();
    }

    function renderCopyList(card, copies, cardWrap) {
        var details = document.createElement('details');
        details.className = 'hg-card-variants';
        details.open = true;

        var summary = document.createElement('summary');
        summary.textContent = 'Variantes obtenidas (' + copies.length + ')';
        details.appendChild(summary);

        var actions = document.createElement('div');
        actions.className = 'hg-card-variants__actions';
        var recycleAll = document.createElement('button');
        recycleAll.type = 'button';
        recycleAll.className = 'hg-recycle-btn hg-icon-action hg-icon-action--wide';
        recycleAll.title = 'Desintegrar todas: +' + copies.reduce(function (sum, copy) { return sum + recycleValue(card, copy); }, 0) + ' Mnemones';
        recycleAll.setAttribute('aria-label', recycleAll.title);
        recycleAll.innerHTML = cardGameIconHtml('sell', 'Desintegrar todas');
        recycleAll.disabled = copies.some(function (copy) { return isCopyWorking(copy.instanceId); });
        if (recycleAll.disabled) {
            recycleAll.title = 'Retira primero las cartas que estan rememorando.';
        }
        recycleAll.addEventListener('click', function () {
            recycleAllCopies(card);
        });
        actions.appendChild(recycleAll);
        details.appendChild(actions);

        var list = document.createElement('div');
        list.className = 'hg-card-variants__list';
        copies.forEach(function (copy, index) {
            var working = isCopyWorking(copy.instanceId);
            var item = document.createElement('div');
            item.className = 'hg-card-variants__item' + (working ? ' is-working' : '');
            if (index === 0) { item.className += ' is-active'; }

            var select = document.createElement('button');
            select.type = 'button';
            select.className = 'hg-card-variants__select';
            select.innerHTML =
                '<span>#' + (index + 1) + '</span>' +
                '<strong>CAL ' + qualityScore(copy, card).toFixed(1) + '%</strong>' +
                '<em>Total ' + escapeHtml(totalStats(copy)) + '</em>';
            select.addEventListener('click', function () {
                list.querySelectorAll('.is-active').forEach(function (active) {
                    active.classList.remove('is-active');
                });
                item.classList.add('is-active');
                cardWrap.innerHTML = '';
                cardWrap.appendChild(renderCard(card, copy, {}));
            });

            var recycle = document.createElement('button');
            recycle.type = 'button';
            recycle.className = 'hg-recycle-btn hg-recycle-btn--small hg-icon-action';
            recycle.title = 'Desintegrar: +' + recycleValue(card, copy) + ' Mnemones';
            recycle.innerHTML = cardGameIconHtml('sell', 'Desintegrar');
            recycle.disabled = working;
            recycle.setAttribute('aria-label', 'Desintegrar esta copia por ' + recycleValue(card, copy) + ' Mnemones');
            recycle.addEventListener('click', function () {
                recycleCopy(card, copy);
            });

            var upgrade = document.createElement('button');
            upgrade.type = 'button';
            upgrade.className = 'hg-upgrade-btn hg-upgrade-btn--small hg-icon-action';
            upgrade.title = 'Evolucionar';
            upgrade.innerHTML = cardGameIconHtml('evolve', 'Evolucionar');
            upgrade.disabled = working || !nextRarity(copyRarity(copy, card));
            upgrade.setAttribute('aria-label', 'Evolucionar rareza de esta copia');
            upgrade.addEventListener('click', function () {
                showRarityUpgradeModal(card, copy);
            });

            var improve = document.createElement('button');
            improve.type = 'button';
            improve.className = 'hg-improve-btn hg-improve-btn--small hg-icon-action';
            improve.title = 'Mejorar';
            improve.innerHTML = cardGameIconHtml('upgrade', 'Mejorar');
            improve.disabled = working || qualityScore(copy, card) >= 100;
            improve.setAttribute('aria-label', 'Mejorar atributos de esta copia');
            improve.addEventListener('click', function () {
                showQualityUpgradeModal(card, copy);
            });

            item.appendChild(select);
            item.appendChild(upgrade);
            item.appendChild(improve);
            item.appendChild(recycle);
            list.appendChild(item);
        });
        details.appendChild(list);
        return details;
    }

    function nextRarity(rarity) {
        var index = rarityRank(rarity);
        return index >= 0 && index < RARITY_ORDER.length - 1 ? RARITY_ORDER[index + 1] : null;
    }

    function rarityUpgradeMultiplier(sourceRarity, targetRarity) {
        var diff = Math.max(0, rarityRank(sourceRarity) - rarityRank(targetRarity));
        return RARITY_UPGRADE_MULTIPLIERS[Math.min(diff, RARITY_UPGRADE_MULTIPLIERS.length - 1)] || 1;
    }

    function upgradeMnemoneCost(card, copy) {
        var rarity = copyRarity(copy, card);
        var base = UPGRADE_COST_BY_RARITY[rarity] || UPGRADE_COST_BY_RARITY.common;
        var factor = Math.max(0.01, Math.min(1, qualityScore(copy, card) / 100));
        return Math.max(1, Math.ceil(base * factor));
    }

    function rarityUpgradeMaterial(nextRarityValue) {
        return RARITY_UPGRADE_MATERIALS[nextRarityValue] || '';
    }

    function upgradeCostHtml(cost, materialKey) {
        var material = materialKey ? UPGRADE_MATERIALS[materialKey] : null;
        var materialText = material
            ? '<span>' + materialIconHtml(materialKey) + '<b>' + escapeHtml(material.label) + '</b><em>' + (state.isAdmin ? 'Admin' : materialStock(materialKey)) + ' / 1</em></span>'
            : '<span><b>Sin objeto ritual</b><em>No requerido</em></span>';
        return '<div class="hg-upgrade-cost">' +
            '<span><b>' + formatNumber(cost) + ' Mnemones</b><em>Coste ritual</em></span>' +
            materialText +
        '</div>';
    }

    function canPayUpgradeCost(cost, materialKey) {
        return state.isAdmin || (currentMnemones() >= cost && (!materialKey || materialStock(materialKey) >= 1));
    }

    function spendUpgradeCost(cost, materialKey) {
        if (state.isAdmin) { return true; }
        if (!canPayUpgradeCost(cost, materialKey)) { return false; }
        addMnemones(-cost);
        if (materialKey) { consumeMaterial(materialKey, 1); }
        return true;
    }

    function isCopyInCombatTeam(instanceId) {
        var id = String(instanceId || '');
        if (!id) { return false; }
        var teams = loadCombatTeams();
        return teams.teams.some(function (team) {
            return (team.cards || []).some(function (cardId) {
                return String(cardId) === id;
            });
        });
    }

    function rarityUpgradeCandidates(targetCard, targetCopy) {
        if (!state.collection) { loadCollection(); }
        var targetId = String(targetCopy && targetCopy.instanceId || '');
        var targetRarity = copyRarity(targetCopy, targetCard);
        return (state.collection.ownedCards || []).map(function (copy) {
            var card = state.catalogById[String(copy.cardId || '')];
            if (!card || String(copy.instanceId || '') === targetId) { return null; }
            if (isCopyWorking(copy.instanceId)) { return null; }
            var rarity = copyRarity(copy, card);
            if (rarityRank(rarity) < rarityRank(targetRarity)) { return null; }
            if (qualityScore(copy, card) < RARITY_UPGRADE_MIN_QUALITY) { return null; }
            if (isCopyInCombatTeam(copy.instanceId)) { return null; }
            return {
                card: cardForCopy(card, copy),
                baseCard: card,
                copy: copy,
                rarity: rarity,
                contribution: rarityUpgradeMultiplier(rarity, targetRarity),
                score: totalStats(copy)
            };
        }).filter(Boolean).sort(function (a, b) {
            var rarityDiff = rarityRank(a.rarity) - rarityRank(b.rarity);
            if (rarityDiff !== 0) { return rarityDiff; }
            return b.score - a.score;
        });
    }

    function closeRarityUpgradeModal() {
        var current = document.querySelector('.hg-upgrade-modal');
        if (current) { current.remove(); }
        document.removeEventListener('keydown', rarityUpgradeEscapeHandler);
    }

    function rarityUpgradeEscapeHandler(event) {
        if (event.key === 'Escape') { closeRarityUpgradeModal(); }
    }

    function showRarityUpgradeModal(targetCard, targetCopy) {
        closeRarityUpgradeModal();
        if (!targetCopy || !targetCopy.instanceId) { return; }
        var targetRarity = copyRarity(targetCopy, targetCard);
        var next = nextRarity(targetRarity);
        if (!next) {
            setStatus('Esta copia ya esta en la rareza maxima.');
            return;
        }
        var candidates = rarityUpgradeCandidates(targetCard, targetCopy);
        var selected = [];
        var filters = { rarity: 'all', type: 'all', minTotal: 0 };

        var overlay = document.createElement('div');
        overlay.className = 'hg-upgrade-modal' + (state.mobile ? ' hg-upgrade-modal--mobile' : '');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Evolucionar rareza');

        var panel = document.createElement('div');
        panel.className = 'hg-upgrade-modal__panel';
        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        var head = document.createElement('div');
        head.className = 'hg-upgrade-modal__head';
        head.innerHTML = '<div><h3>Evolucionar rareza</h3><p>' + escapeHtml(targetCard.card_name) + ' · ' + escapeHtml(RARITY_LABELS[targetRarity] || targetRarity) + ' a ' + escapeHtml(RARITY_LABELS[next] || next) + '</p></div>';
        var close = document.createElement('button');
        close.type = 'button';
        close.textContent = 'Cerrar';
        close.addEventListener('click', closeRarityUpgradeModal);
        head.appendChild(close);

        var bars = document.createElement('div');
        bars.className = 'hg-upgrade-bars';

        var qualityBar = document.createElement('div');
        qualityBar.className = 'hg-upgrade-bar';
        var targetQuality = qualityScore(targetCopy, targetCard);
        qualityBar.innerHTML =
            '<span>Calidad objetivo</span><strong>' + targetQuality.toFixed(1) + '%</strong>' +
            '<i><b style="width:' + Math.min(100, targetQuality) + '%"></b></i>';

        var rarityBar = document.createElement('div');
        rarityBar.className = 'hg-upgrade-bar hg-upgrade-bar--rarity';
        bars.appendChild(qualityBar);
        bars.appendChild(rarityBar);

        var upgradeCost = upgradeMnemoneCost(targetCard, targetCopy);
        var requiredMaterial = rarityUpgradeMaterial(next);
        var costBox = document.createElement('div');
        costBox.innerHTML = upgradeCostHtml(upgradeCost, requiredMaterial);

        var slots = document.createElement('div');
        slots.className = 'hg-upgrade-slots';

        var filterWrap = document.createElement('div');
        filterWrap.className = 'hg-upgrade-filters';
        var raritySelect = document.createElement('select');
        var typeSelect = document.createElement('select');
        var totalSelect = document.createElement('select');
        filterWrap.appendChild(raritySelect);
        filterWrap.appendChild(typeSelect);
        filterWrap.appendChild(totalSelect);

        var list = document.createElement('div');
        list.className = 'hg-upgrade-list';

        var actions = document.createElement('div');
        actions.className = 'hg-upgrade-actions';
        var confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'hg-upgrade-confirm';
        confirm.textContent = 'Evolucionar';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.textContent = 'Cancelar';
        cancel.addEventListener('click', closeRarityUpgradeModal);
        actions.appendChild(cancel);
        actions.appendChild(confirm);

        function selectedEntries() {
            return selected.map(function (id) {
                return candidates.filter(function (entry) {
                    return String(entry.copy.instanceId || '') === String(id);
                })[0] || null;
            }).filter(Boolean);
        }

        function selectedProgress() {
            return selectedEntries().reduce(function (sum, entry) {
                return sum + entry.contribution;
            }, 0);
        }

        function renderFilters() {
            var rarityOptions = ['all'].concat(RARITY_ORDER.filter(function (rarity) {
                return rarityRank(rarity) >= rarityRank(targetRarity);
            }));
            raritySelect.innerHTML = rarityOptions.map(function (rarity) {
                var label = rarity === 'all' ? 'Rareza: validas' : (RARITY_LABELS[rarity] || rarity);
                return '<option value="' + escapeHtml(rarity) + '">' + escapeHtml(label) + '</option>';
            }).join('');
            raritySelect.value = filters.rarity;

            var types = { all: candidates.length };
            candidates.forEach(function (entry) {
                types[entry.baseCard.source_type] = (types[entry.baseCard.source_type] || 0) + 1;
            });
            var typeKeys = TYPE_ORDER.filter(function (type) {
                return type === 'all' || types[type] > 0;
            });
            Object.keys(types).sort().forEach(function (type) {
                if (typeKeys.indexOf(type) === -1) { typeKeys.push(type); }
            });
            typeSelect.innerHTML = typeKeys.map(function (type) {
                var label = type === 'all' ? 'Tipo: todos' : typeLabel(type);
                return '<option value="' + escapeHtml(type) + '">' + escapeHtml(label) + '</option>';
            }).join('');
            typeSelect.value = filters.type;

            totalSelect.innerHTML = [
                '<option value="0">Total: cualquiera</option>',
                '<option value="200">Total >= 200</option>',
                '<option value="300">Total >= 300</option>',
                '<option value="400">Total >= 400</option>',
                '<option value="500">Total >= 500</option>'
            ].join('');
            totalSelect.value = String(filters.minTotal);
        }

        function renderUpgradeState() {
            var picked = selectedEntries();
            var progress = selectedProgress();
            rarityBar.innerHTML =
                '<span>Progreso de rareza</span><strong>' + progress.toFixed(1) + ' / ' + RARITY_UPGRADE_REQUIRED + '</strong>' +
                '<i><b style="width:' + Math.min(100, (progress / RARITY_UPGRADE_REQUIRED) * 100) + '%"></b></i>';

            slots.innerHTML = '';
            for (var i = 0; i < RARITY_UPGRADE_REQUIRED; i++) {
                var slot = document.createElement('span');
                var entry = picked[i] || null;
                slot.className = 'hg-upgrade-slot' + (entry ? ' is-filled' : '');
                slot.innerHTML = entry
                    ? '<strong>' + combatCardNameHtml(entry.baseCard) + '</strong><small>x' + entry.contribution + ' · ' + escapeHtml(RARITY_LABELS[entry.rarity] || entry.rarity) + '</small>'
                    : '<strong>Hueco ' + (i + 1) + '</strong><small>Sin sacrificio</small>';
                slots.appendChild(slot);
            }

            var rows = candidates.filter(function (entry) {
                return (filters.rarity === 'all' || entry.rarity === filters.rarity)
                    && (filters.type === 'all' || entry.baseCard.source_type === filters.type)
                    && entry.score >= filters.minTotal;
            });
            list.innerHTML = '';
            if (!rows.length) {
                var empty = document.createElement('p');
                empty.className = 'hg-empty-state';
                empty.textContent = 'No hay sacrificios disponibles con esos filtros.';
                list.appendChild(empty);
            }
            rows.forEach(function (entry) {
                var id = String(entry.copy.instanceId || '');
                var isSelected = selected.indexOf(id) !== -1;
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'hg-upgrade-row hg-collection-row--' + entry.rarity + (isSelected ? ' is-selected' : '');
                button.disabled = !isSelected && selected.length >= RARITY_UPGRADE_REQUIRED;
                button.innerHTML =
                    '<strong>' + combatCardNameHtml(entry.baseCard) + '</strong>' +
                    '<span>' + escapeHtml(RARITY_LABELS[entry.rarity] || entry.rarity) + ' · CAL ' + qualityScore(entry.copy, entry.baseCard).toFixed(1) + '% · Total ' + entry.score + '</span>' +
                    '<b>x' + entry.contribution + '</b>';
                var meta = button.children[1];
                if (meta) {
                    meta.className = 'hg-upgrade-row__meta';
                    meta.innerHTML =
                        '<em>' + escapeHtml(RARITY_LABELS[entry.rarity] || entry.rarity) + '</em>' +
                        '<em>CAL ' + qualityScore(entry.copy, entry.baseCard).toFixed(1) + '%</em>' +
                        '<em>Total ' + entry.score + '</em>';
                }
                button.addEventListener('click', function () {
                    var index = selected.indexOf(id);
                    if (index !== -1) {
                        selected.splice(index, 1);
                    } else if (selected.length < RARITY_UPGRADE_REQUIRED) {
                        selected.push(id);
                    }
                    renderUpgradeState();
                });
                list.appendChild(button);
            });
            costBox.innerHTML = upgradeCostHtml(upgradeCost, requiredMaterial);
            confirm.disabled = progress < RARITY_UPGRADE_REQUIRED || !canPayUpgradeCost(upgradeCost, requiredMaterial);
        }

        raritySelect.addEventListener('change', function () {
            filters.rarity = raritySelect.value || 'all';
            renderUpgradeState();
        });
        typeSelect.addEventListener('change', function () {
            filters.type = typeSelect.value || 'all';
            renderUpgradeState();
        });
        totalSelect.addEventListener('change', function () {
            filters.minTotal = Math.max(0, clampInt(totalSelect.value, 0));
            renderUpgradeState();
        });
        confirm.addEventListener('click', function () {
            applyRarityUpgrade(targetCard, targetCopy, selected);
        });

        panel.appendChild(head);
        panel.appendChild(bars);
        panel.appendChild(costBox);
        panel.appendChild(slots);
        panel.appendChild(filterWrap);
        panel.appendChild(list);
        panel.appendChild(actions);
        overlay.appendChild(panel);
        overlay.addEventListener('click', closeRarityUpgradeModal);
        document.body.appendChild(overlay);
        document.addEventListener('keydown', rarityUpgradeEscapeHandler);
        renderFilters();
        renderUpgradeState();
        close.focus();
    }

    function applyRarityUpgrade(targetCard, targetCopy, selectedIds) {
        if (isCopyWorking(targetCopy && targetCopy.instanceId)) {
            setStatus('Retira la carta de la rememoracion antes de evolucionarla.');
            return false;
        }
        var targetRarity = copyRarity(targetCopy, targetCard);
        var next = nextRarity(targetRarity);
        if (!next) { return false; }
        var upgradeCost = upgradeMnemoneCost(targetCard, targetCopy);
        var requiredMaterial = rarityUpgradeMaterial(next);
        var selected = (selectedIds || []).slice(0, RARITY_UPGRADE_REQUIRED);
        var candidates = rarityUpgradeCandidates(targetCard, targetCopy);
        var byId = {};
        candidates.forEach(function (entry) {
            byId[String(entry.copy.instanceId || '')] = entry;
        });
        var progress = selected.reduce(function (sum, id) {
            return sum + (byId[String(id)] ? byId[String(id)].contribution : 0);
        }, 0);
        if (progress < RARITY_UPGRADE_REQUIRED) {
            setStatus('Elige sacrificios suficientes para completar la evolucion.');
            return false;
        }
        if (!spendUpgradeCost(upgradeCost, requiredMaterial)) {
            setStatus('Faltan Mnemones u objetos rituales para evolucionar.');
            return false;
        }
        var remove = {};
        selected.forEach(function (id) {
            if (byId[String(id)]) { remove[String(id)] = true; }
        });
        retuneCopyStatsForRarity(targetCopy, targetCard, targetRarity, next);
        state.collection.ownedCards = (state.collection.ownedCards || []).filter(function (copy) {
            return !remove[String(copy.instanceId || '')];
        });
        removeCopiesFromCombatTeams(remove);
        saveCollection();
        playDustSound();
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        renderCombatSetup();
        closeRarityUpgradeModal();
        showCardModal(targetCard, ownedCopiesForCard(targetCard.card_id));
        setStatus('Rareza evolucionada a ' + (RARITY_LABELS[next] || next) + '. Coste: ' + formatNumber(upgradeCost) + ' Mnemones.');
        return true;
    }

    function qualityUpgradeContribution(entry, targetQuality) {
        var sourceQuality = qualityScore(entry.copy, entry.baseCard);
        var base = 8 + (sourceQuality * 0.12);
        var resistance = 1 + (Math.max(0, targetQuality) / 45);
        return Math.max(0.5, Math.round((base / resistance) * 10) / 10);
    }

    function projectedQualityAfterSacrifices(targetQuality, entries) {
        var quality = clampQuality(targetQuality, 0);
        entries.forEach(function (entry) {
            quality = clampQuality(quality + qualityUpgradeContribution(entry, quality), quality);
        });
        return quality;
    }

    function qualityUpgradeCandidates(targetCard, targetCopy) {
        if (!state.collection) { loadCollection(); }
        var targetId = String(targetCopy && targetCopy.instanceId || '');
        var targetRarity = copyRarity(targetCopy, targetCard);
        return (state.collection.ownedCards || []).map(function (copy) {
            var card = state.catalogById[String(copy.cardId || '')];
            if (!card || String(copy.instanceId || '') === targetId) { return null; }
            if (isCopyWorking(copy.instanceId)) { return null; }
            var rarity = copyRarity(copy, card);
            if (rarity !== targetRarity) { return null; }
            if (isCopyInCombatTeam(copy.instanceId)) { return null; }
            return {
                card: cardForCopy(card, copy),
                baseCard: card,
                copy: copy,
                rarity: rarity,
                score: totalStats(copy)
            };
        }).filter(Boolean).sort(function (a, b) {
            return qualityScore(b.copy, b.baseCard) - qualityScore(a.copy, a.baseCard) || b.score - a.score;
        });
    }

    function closeQualityUpgradeModal() {
        var current = document.querySelector('.hg-upgrade-modal');
        if (current) { current.remove(); }
        document.removeEventListener('keydown', qualityUpgradeEscapeHandler);
    }

    function qualityUpgradeEscapeHandler(event) {
        if (event.key === 'Escape') { closeQualityUpgradeModal(); }
    }

    function showQualityUpgradeModal(targetCard, targetCopy) {
        closeQualityUpgradeModal();
        if (!targetCopy || !targetCopy.instanceId) { return; }
        var targetQuality = qualityScore(targetCopy, targetCard);
        if (targetQuality >= 100) {
            setStatus('Esta copia ya tiene calidad 100%.');
            return;
        }
        var targetRarity = copyRarity(targetCopy, targetCard);
        var candidates = qualityUpgradeCandidates(targetCard, targetCopy);
        var selected = [];
        var filters = { type: 'all', minTotal: 0 };

        var overlay = document.createElement('div');
        overlay.className = 'hg-upgrade-modal' + (state.mobile ? ' hg-upgrade-modal--mobile' : '');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Mejorar atributos');

        var panel = document.createElement('div');
        panel.className = 'hg-upgrade-modal__panel';
        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        var head = document.createElement('div');
        head.className = 'hg-upgrade-modal__head';
        head.innerHTML = '<div><h3>Mejorar atributos</h3><p>' + escapeHtml(targetCard.card_name) + ' · ' + escapeHtml(RARITY_LABELS[targetRarity] || targetRarity) + ' · CAL ' + targetQuality.toFixed(1) + '%</p></div>';
        var close = document.createElement('button');
        close.type = 'button';
        close.textContent = 'Cerrar';
        close.addEventListener('click', closeQualityUpgradeModal);
        head.appendChild(close);

        var bars = document.createElement('div');
        bars.className = 'hg-upgrade-bars';
        var currentBar = document.createElement('div');
        currentBar.className = 'hg-upgrade-bar';
        currentBar.innerHTML =
            '<span>Calidad actual</span><strong>' + targetQuality.toFixed(1) + '%</strong>' +
            '<i><b style="width:' + Math.min(100, targetQuality) + '%"></b></i>';
        var projectedBar = document.createElement('div');
        projectedBar.className = 'hg-upgrade-bar hg-upgrade-bar--quality';
        bars.appendChild(currentBar);
        bars.appendChild(projectedBar);

        var improveCost = upgradeMnemoneCost(targetCard, targetCopy);
        var costBox = document.createElement('div');
        costBox.innerHTML = upgradeCostHtml(improveCost, '');

        var slots = document.createElement('div');
        slots.className = 'hg-upgrade-slots';

        var filterWrap = document.createElement('div');
        filterWrap.className = 'hg-upgrade-filters hg-upgrade-filters--quality';
        var typeSelect = document.createElement('select');
        var totalSelect = document.createElement('select');
        filterWrap.appendChild(typeSelect);
        filterWrap.appendChild(totalSelect);

        var list = document.createElement('div');
        list.className = 'hg-upgrade-list';

        var actions = document.createElement('div');
        actions.className = 'hg-upgrade-actions';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.textContent = 'Cancelar';
        cancel.addEventListener('click', closeQualityUpgradeModal);
        var confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'hg-upgrade-confirm';
        confirm.textContent = 'Mejorar';
        actions.appendChild(cancel);
        actions.appendChild(confirm);

        function selectedEntries() {
            return selected.map(function (id) {
                return candidates.filter(function (entry) {
                    return String(entry.copy.instanceId || '') === String(id);
                })[0] || null;
            }).filter(Boolean);
        }

        function renderFilters() {
            var types = { all: candidates.length };
            candidates.forEach(function (entry) {
                types[entry.baseCard.source_type] = (types[entry.baseCard.source_type] || 0) + 1;
            });
            var typeKeys = TYPE_ORDER.filter(function (type) {
                return type === 'all' || types[type] > 0;
            });
            Object.keys(types).sort().forEach(function (type) {
                if (typeKeys.indexOf(type) === -1) { typeKeys.push(type); }
            });
            typeSelect.innerHTML = typeKeys.map(function (type) {
                var label = type === 'all' ? 'Tipo: todos' : typeLabel(type);
                return '<option value="' + escapeHtml(type) + '">' + escapeHtml(label) + '</option>';
            }).join('');
            typeSelect.value = filters.type;

            totalSelect.innerHTML = [
                '<option value="0">Total: cualquiera</option>',
                '<option value="200">Total >= 200</option>',
                '<option value="300">Total >= 300</option>',
                '<option value="400">Total >= 400</option>',
                '<option value="500">Total >= 500</option>'
            ].join('');
            totalSelect.value = String(filters.minTotal);
        }

        function renderImproveState() {
            var picked = selectedEntries();
            var projected = projectedQualityAfterSacrifices(targetQuality, picked);
            projectedBar.innerHTML =
                '<span>Calidad tras mejora</span><strong>' + projected.toFixed(1) + '%</strong>' +
                '<i><b style="width:' + Math.min(100, projected) + '%"></b></i>';

            slots.innerHTML = '';
            for (var i = 0; i < QUALITY_UPGRADE_MAX_SLOTS; i++) {
                var slot = document.createElement('span');
                var entry = picked[i] || null;
                slot.className = 'hg-upgrade-slot' + (entry ? ' is-filled' : '');
                slot.innerHTML = entry
                    ? '<strong>' + combatCardNameHtml(entry.baseCard) + '</strong><small>+' + qualityUpgradeContribution(entry, targetQuality).toFixed(1) + '% · CAL ' + qualityScore(entry.copy, entry.baseCard).toFixed(1) + '%</small>'
                    : '<strong>Hueco ' + (i + 1) + '</strong><small>Sin sacrificio</small>';
                slots.appendChild(slot);
            }

            var rows = candidates.filter(function (entry) {
                return (filters.type === 'all' || entry.baseCard.source_type === filters.type)
                    && entry.score >= filters.minTotal;
            });
            list.innerHTML = '';
            if (!rows.length) {
                var empty = document.createElement('p');
                empty.className = 'hg-empty-state';
                empty.textContent = 'No hay sacrificios disponibles con esos filtros.';
                list.appendChild(empty);
            }
            rows.forEach(function (entry) {
                var id = String(entry.copy.instanceId || '');
                var isSelected = selected.indexOf(id) !== -1;
                var contribution = qualityUpgradeContribution(entry, targetQuality);
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'hg-upgrade-row hg-collection-row--' + entry.rarity + (isSelected ? ' is-selected' : '');
                button.disabled = !isSelected && selected.length >= QUALITY_UPGRADE_MAX_SLOTS;
                button.innerHTML =
                    '<strong>' + combatCardNameHtml(entry.baseCard) + '</strong>' +
                    '<span class="hg-upgrade-row__meta">' +
                        '<em>' + escapeHtml(RARITY_LABELS[entry.rarity] || entry.rarity) + '</em>' +
                        '<em>CAL ' + qualityScore(entry.copy, entry.baseCard).toFixed(1) + '%</em>' +
                        '<em>Total ' + entry.score + '</em>' +
                    '</span>' +
                    '<b>+' + contribution.toFixed(1) + '%</b>';
                button.addEventListener('click', function () {
                    var index = selected.indexOf(id);
                    if (index !== -1) {
                        selected.splice(index, 1);
                    } else if (selected.length < QUALITY_UPGRADE_MAX_SLOTS) {
                        selected.push(id);
                    }
                    renderImproveState();
                });
                list.appendChild(button);
            });
            costBox.innerHTML = upgradeCostHtml(improveCost, '');
            confirm.disabled = !picked.length || projected <= targetQuality || !canPayUpgradeCost(improveCost, '');
        }

        typeSelect.addEventListener('change', function () {
            filters.type = typeSelect.value || 'all';
            renderImproveState();
        });
        totalSelect.addEventListener('change', function () {
            filters.minTotal = Math.max(0, clampInt(totalSelect.value, 0));
            renderImproveState();
        });
        confirm.addEventListener('click', function () {
            applyQualityUpgrade(targetCard, targetCopy, selected);
        });

        panel.appendChild(head);
        panel.appendChild(bars);
        panel.appendChild(costBox);
        panel.appendChild(slots);
        panel.appendChild(filterWrap);
        panel.appendChild(list);
        panel.appendChild(actions);
        overlay.appendChild(panel);
        overlay.addEventListener('click', closeQualityUpgradeModal);
        document.body.appendChild(overlay);
        document.addEventListener('keydown', qualityUpgradeEscapeHandler);
        renderFilters();
        renderImproveState();
        close.focus();
    }

    function applyQualityUpgrade(targetCard, targetCopy, selectedIds) {
        if (isCopyWorking(targetCopy && targetCopy.instanceId)) {
            setStatus('Retira la carta de la rememoracion antes de mejorarla.');
            return false;
        }
        var targetQuality = qualityScore(targetCopy, targetCard);
        var candidates = qualityUpgradeCandidates(targetCard, targetCopy);
        var byId = {};
        candidates.forEach(function (entry) {
            byId[String(entry.copy.instanceId || '')] = entry;
        });
        var selected = (selectedIds || []).slice(0, QUALITY_UPGRADE_MAX_SLOTS).map(function (id) {
            return byId[String(id)] || null;
        }).filter(Boolean);
        if (!selected.length) {
            setStatus('Elige al menos una carta para mejorar atributos.');
            return false;
        }
        var projected = projectedQualityAfterSacrifices(targetQuality, selected);
        if (projected <= targetQuality) {
            setStatus('Esos sacrificios no mejoran la calidad.');
            return false;
        }
        var improveCost = upgradeMnemoneCost(targetCard, targetCopy);
        if (!spendUpgradeCost(improveCost, '')) {
            setStatus('Faltan Mnemones para mejorar atributos.');
            return false;
        }
        var remove = {};
        selected.forEach(function (entry) {
            remove[String(entry.copy.instanceId || '')] = true;
        });
        applyQualityToCopyStats(targetCopy, targetCard, projected);
        state.collection.ownedCards = (state.collection.ownedCards || []).filter(function (copy) {
            return !remove[String(copy.instanceId || '')];
        });
        removeCopiesFromCombatTeams(remove);
        saveCollection();
        playDustSound();
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        renderCombatSetup();
        closeQualityUpgradeModal();
        showCardModal(targetCard, ownedCopiesForCard(targetCard.card_id));
        setStatus('Atributos mejorados a CAL ' + qualityScore(targetCopy, targetCard).toFixed(1) + '%. Coste: ' + formatNumber(improveCost) + ' Mnemones.');
        return true;
    }

    function recycleValue(card, copy) {
        return RECYCLE_VALUES[copyRarity(copy, card)] || RECYCLE_VALUES.common;
    }

    function ownedCopiesForCard(cardId) {
        if (!state.collection) { loadCollection(); }
        return (state.collection.ownedCards || []).filter(function (copy) {
            return String(copy.cardId) === String(cardId);
        });
    }

    function recycleCopy(card, copy, confirmed) {
        if (!copy || !copy.instanceId) { return false; }
        if (isCopyWorking(copy.instanceId)) {
            setStatus('Retira la carta de la rememoracion antes de venderla.');
            return false;
        }
        var copies = ownedCopiesForCard(card.card_id);
        var rarity = copyRarity(copy, card);
        if ((rarity === 'legendary' || rarity === 'mythic') && !confirmed) {
            return confirmGameAction(
                'Vas a desintegrar una copia ' + (RARITY_LABELS[rarity] || rarity).toLowerCase() + '.',
                { title: 'Desintegrar carta', confirmLabel: 'Desintegrar' },
                function () { recycleCopy(card, copy, true); }
            );
        }
        var remove = {};
        remove[String(copy.instanceId)] = true;
        var removedFromTeams = removeCopiesFromCombatTeams(remove);
        state.collection.ownedCards = state.collection.ownedCards.filter(function (item) {
            return String(item.instanceId) !== String(copy.instanceId);
        });
        var gained = recycleValue(card, copy);
        addMnemones(gained);
        saveCollection();
        playDustSound();
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        renderCombatSetup();
        var remaining = ownedCopiesForCard(card.card_id);
        if (remaining.length) {
            showCardModal(card, remaining);
        } else {
            closeCardModal();
        }
        setStatus('Copia desintegrada. +' + gained + ' Mnemones.' + (removedFromTeams ? ' Retirada de ' + removedFromTeams + ' hueco(s) de equipo.' : ''));
        return true;
    }

    function recycleDuplicateCopies(card, confirmed) {
        var copies = sortedCopies(ownedCopiesForCard(card.card_id), card);
        if (copies.length <= 1) {
            setStatus('No hay duplicadas que desintegrar.');
            return false;
        }
        var keep = copies[0];
        var recycled = copies.slice(1);
        if (recycled.some(function (copy) { return isCopyWorking(copy.instanceId); })) {
            setStatus('Retira primero las duplicadas que estan rememorando.');
            return false;
        }
        var gained = recycled.reduce(function (sum, copy) { return sum + recycleValue(card, copy); }, 0);
        if (!confirmed) {
            return confirmGameAction(
                'Se conservara la mejor copia y se desintegraran ' + recycled.length + ' duplicadas por ' + gained + ' Mnemones.',
                { title: 'Desintegrar duplicadas', confirmLabel: 'Desintegrar' },
                function () { recycleDuplicateCopies(card, true); }
            );
        }
        var remove = {};
        recycled.forEach(function (copy) {
            remove[String(copy.instanceId)] = true;
        });
        var removedFromTeams = removeCopiesFromCombatTeams(remove);
        state.collection.ownedCards = state.collection.ownedCards.filter(function (copy) {
            return !remove[String(copy.instanceId)];
        });
        addMnemones(gained);
        saveCollection();
        playDustSound();
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        renderCombatSetup();
        showCardModal(card, [keep]);
        setStatus('Duplicadas desintegradas. +' + gained + ' Mnemones.' + (removedFromTeams ? ' Retiradas de ' + removedFromTeams + ' hueco(s) de equipo.' : ''));
        return true;
    }

    function recycleAllCopies(card, confirmed) {
        var copies = sortedCopies(ownedCopiesForCard(card.card_id), card);
        if (!copies.length) {
            setStatus('No hay copias que desintegrar.');
            return false;
        }
        if (copies.some(function (copy) { return isCopyWorking(copy.instanceId); })) {
            setStatus('Retira primero las cartas que estan rememorando.');
            return false;
        }
        var gained = copies.reduce(function (sum, copy) { return sum + recycleValue(card, copy); }, 0);
        if (!confirmed) {
            return confirmGameAction(
                'Se desintegraran todas las copias de esta carta (' + copies.length + ') por ' + gained + ' Mnemones.',
                { title: 'Desintegrar todas', confirmLabel: 'Desintegrar' },
                function () { recycleAllCopies(card, true); }
            );
        }
        var remove = {};
        copies.forEach(function (copy) {
            remove[String(copy.instanceId)] = true;
        });
        var removedFromTeams = removeCopiesFromCombatTeams(remove);
        state.collection.ownedCards = state.collection.ownedCards.filter(function (copy) {
            return !remove[String(copy.instanceId)];
        });
        addMnemones(gained);
        saveCollection();
        playDustSound();
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        renderCombatSetup();
        closeCardModal();
        setStatus('Carta desintegrada. +' + gained + ' Mnemones.' + (removedFromTeams ? ' Retirada de ' + removedFromTeams + ' hueco(s) de equipo.' : ''));
        return true;
    }

    function bulkSellStats(rarity, keepBest) {
        if (!state.collection) { loadCollection(); }
        keepBest = !!keepBest;
        var byCard = {};
        var remove = {};
        var count = 0;
        var gained = 0;
        var kept = 0;

        (state.collection.ownedCards || []).forEach(function (copy) {
            var card = state.catalogById[String(copy.cardId || '')];
            if (!card || copyRarity(copy, card) !== rarity) { return; }
            if (isCopyWorking(copy.instanceId)) { return; }
            var cardId = String(card.card_id);
            if (!byCard[cardId]) { byCard[cardId] = { card: card, copies: [] }; }
            byCard[cardId].copies.push(copy);
        });

        Object.keys(byCard).forEach(function (cardId) {
            var entry = byCard[cardId];
            var copies = sortedCopies(entry.copies, entry.card);
            var toSell = keepBest ? copies.slice(1) : copies;
            if (keepBest && copies.length) { kept += 1; }
            toSell.forEach(function (copy) {
                remove[String(copy.instanceId)] = true;
                count += 1;
                gained += recycleValue(entry.card, copy);
            });
        });

        return { count: count, gained: gained, remove: remove, kept: kept, keepBest: keepBest };
    }

    function renderBulkSellPreview() {
        if (!els.bulkSellRarity || !els.bulkSellBtn || !els.bulkSellPreview) { return; }
        var rarity = els.bulkSellRarity.value || 'common';
        var keepBest = !els.bulkSellKeepBest || els.bulkSellKeepBest.checked;
        var stats = bulkSellStats(rarity, keepBest);
        var label = RARITY_LABELS[rarity] || rarity;
        var previewParts = [
            '<span>' + stats.count + ' cartas ' + escapeHtml(label.toLowerCase()) + '</span>',
            '<span>+' + stats.gained + ' Mnemones</span>'
        ];
        if (stats.keepBest && stats.kept) {
            previewParts.push('<span>conserva ' + stats.kept + ' mejores</span>');
        }
        els.bulkSellPreview.innerHTML = previewParts.join('');
        els.bulkSellBtn.disabled = stats.count <= 0;
    }

    function sellCardsByRarity(confirmed) {
        if (!els.bulkSellRarity) { return false; }
        if (!state.catalog.length) {
            setStatus('Espera a que cargue el catalogo.');
            return false;
        }
        var rarity = els.bulkSellRarity.value || 'common';
        if (RARITY_ORDER.indexOf(rarity) === -1) {
            setStatus('Rareza no valida.');
            return false;
        }

        var keepBest = !els.bulkSellKeepBest || els.bulkSellKeepBest.checked;
        var stats = bulkSellStats(rarity, keepBest);
        if (stats.count <= 0) {
            setStatus(stats.keepBest ? 'No tienes duplicadas de esa rareza para vender.' : 'No tienes cartas de esa rareza para vender.');
            renderBulkSellPreview();
            return false;
        }

        var label = RARITY_LABELS[rarity] || rarity;
        var keepText = stats.keepBest ? ' Se conservará la copia con mayor PS + ATQ + DEF de cada carta.' : '';
        if (!confirmed) {
            return confirmGameAction(
                'Vas a vender ' + stats.count + ' cartas de rareza ' + label.toLowerCase() + ' por ' + stats.gained + ' Mnemones.' + keepText + ' Esta accion no se puede deshacer.',
                { title: 'Vender cartas', confirmLabel: 'Vender' },
                function () { sellCardsByRarity(true); }
            );
        }

        var removedFromTeams = removeCopiesFromCombatTeams(stats.remove);
        state.collection.ownedCards = state.collection.ownedCards.filter(function (copy) {
            return !stats.remove[String(copy.instanceId)];
        });
        addMnemones(stats.gained);
        saveCollection();
        playDustSound();
        closeCardModal();
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        renderCombatSetup();
        setStatus('Venta completada. +' + stats.gained + ' Mnemones.' + (removedFromTeams ? ' Retiradas de ' + removedFromTeams + ' hueco(s) de equipo.' : ''));
        return true;
    }

    function closeCardModal() {
        Array.prototype.slice.call(document.querySelectorAll('.hg-card-modal')).forEach(function (current) {
            current.remove();
        });
        document.removeEventListener('keydown', modalEscapeHandler);
    }

    function modalEscapeHandler(event) {
        if (event.key === 'Escape') {
            closeCardModal();
        }
    }

    function statNode(label, value) {
        var strong = document.createElement('strong');
        var small = document.createElement('span');
        small.textContent = label;
        var big = document.createElement('b');
        big.textContent = String(value);
        strong.appendChild(small);
        strong.appendChild(big);
        return strong;
    }

    function exportCollection() {
        if (!state.collection) { loadCollection(); }
        var blob = new Blob([JSON.stringify(state.collection, null, 2)], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'hg_card_collection_v1.json';
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.setTimeout(function () { URL.revokeObjectURL(url); }, 250);
        setStatus('Colección exportada a JSON.');
    }

    function validateCollection(data) {
        if (!data || typeof data !== 'object' || Number(data.version) !== 1 || !Array.isArray(data.ownedCards)) {
            throw new Error('El JSON no tiene una colección compatible.');
        }
        if (data.ownedCards.length > 10000) {
            throw new Error('La colección importada es demasiado grande.');
        }
        var out = {
            version: 1,
            createdAt: typeof data.createdAt === 'string' && data.createdAt ? data.createdAt : nowIso(),
            updatedAt: nowIso(),
            ownedCards: [],
            workAssignments: normalizeWorkAssignments(data.workAssignments),
            workPendingRewards: normalizeWorkPendingRewards(data.workPendingRewards),
            currency: normalizeCurrency(data.currency),
            packInventory: normalizePackInventory(data.packInventory),
            materialInventory: normalizeMaterialInventory(data.materialInventory)
        };
        var seen = {};
        data.ownedCards.forEach(function (item) {
            if (!item || typeof item !== 'object') { return; }
            var cardId = clampInt(item.cardId, 0);
            if (cardId <= 0) { return; }
            if (state.catalog.length && !state.catalogById[String(cardId)]) { return; }
            var id = String(item.instanceId || instanceId()).slice(0, 80);
            if (seen[id]) { id = instanceId(); }
            seen[id] = true;
            var card = state.catalogById[String(cardId)] || null;
            var atkFallback = clampInt(item.atk, card ? card.atk_min : 10);
            var copy = {
                instanceId: id,
                cardId: cardId,
                hp: clampInt(item.hp, card ? card.hp_min : atkFallback),
                atk: atkFallback,
                def: clampInt(item.def, card ? card.def_min : 10),
                obtainedAt: typeof item.obtainedAt === 'string' && item.obtainedAt ? item.obtainedAt : nowIso()
            };
            if (RARITY_ORDER.indexOf(String(item.rarity || '')) !== -1) {
                copy.rarity = String(item.rarity);
            } else if (card) {
                copy.rarity = card.card_rarity;
            }
            var importedQuality = clampQuality(item.quality, null);
            if (importedQuality !== null) {
                copy.quality = importedQuality;
            } else if (card) {
                copy.quality = calculatedQualityScore(copy, card);
            }
            out.ownedCards.push(copy);
        });
        Object.keys(out.workAssignments).forEach(function (id) {
            if (!seen[id]) { delete out.workAssignments[id]; }
        });
        return out;
    }

    function importCollection(json) {
        try {
            state.collection = validateCollection(JSON.parse(json));
            saveCollection();
            renderPackResults([]);
            renderSummary();
            renderPackInventory();
            renderCollectionTable();
            setStatus('Colección importada correctamente.');
            return true;
        } catch (e) {
            setStatus(e.message || 'No se pudo importar la colección.');
            return false;
        }
    }

    function resetCollection(confirmed) {
        if (!confirmed) {
            return confirmGameAction(
                'Esto borrara la coleccion local de este navegador.',
                { title: 'Borrar coleccion', confirmLabel: 'Borrar' },
                function () { resetCollection(true); }
            );
        }
        state.collection = createEmptyCollection();
        state.freeRewards = createFreeRewards();
        try { window.localStorage.removeItem(STORAGE_KEY); } catch (e) { saveCollection(); }
        try { window.localStorage.removeItem(FREE_REWARDS_KEY); } catch (e2) { saveFreeRewards(); }
        renderPackResults([]);
        renderSummary();
        renderDailyCounter();
        renderPackInventory();
        renderCollectionTable();
        setStatus('Colección local borrada.');
    }

    function formatDate(value) {
        var d = new Date(value);
        if (Number.isNaN(d.getTime())) { return '-'; }
        return d.toLocaleDateString('es-ES') + ' ' + d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(text) {
        return String(text === null || text === undefined ? '' : text).replace(/[&<>"']/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
        });
    }

    function isTouchDevice() {
        var ua = navigator.userAgent || '';
        return window.matchMedia('(pointer: coarse)').matches
            || /Android|iPhone|iPad|iPod|Mobile|Tablet/i.test(ua);
    }

    function renderMobileSwitchPrompt() {
        if (state.mobile || !isTouchDevice() || window.sessionStorage.getItem('hg_card_mobile_prompt_closed') === '1') { return; }
        var prompt = document.createElement('div');
        prompt.className = 'hg-mobile-switch';
        prompt.innerHTML =
            '<div><strong>Modo movil disponible</strong><span>Una vista pensada para pantalla tactil.</span></div>' +
            '<a href="/games/card-game/mobile">Abrir</a>' +
            '<button type="button" aria-label="Cerrar aviso">&times;</button>';
        prompt.querySelector('button').addEventListener('click', function () {
            window.sessionStorage.setItem('hg_card_mobile_prompt_closed', '1');
            prompt.remove();
        });
        root.insertBefore(prompt, root.firstChild);
    }

    function bindMobilePanels() {
        if (!state.mobile || !els.mobileTabs.length) { return; }
        els.mobileTabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                if (tab.classList.contains('is-active')) { return; }
                var target = tab.getAttribute('data-mobile-panel-tab') || 'packs';
                unloadInactiveMobilePanels(target);
                els.mobileTabs.forEach(function (item) {
                    item.classList.toggle('is-active', item === tab);
                });
                els.mobilePanels.forEach(function (panel) {
                    panel.classList.toggle('is-active', panel.getAttribute('data-mobile-panel') === target);
                });
                if (target === 'collection') {
                    renderCollectionTable();
                } else if (target === 'memory') {
                    renderWorkBench();
                } else if (target === 'combat') {
                    renderCombat();
                }
            });
        });
    }

    function unloadInactiveMobilePanels(target) {
        if (!state.mobile) { return; }
        if (target !== 'collection') {
            if (els.albumGrid) { els.albumGrid.innerHTML = ''; }
            if (els.collectionTable) {
                var tbody = els.collectionTable.querySelector('tbody');
                if (tbody) { tbody.innerHTML = ''; }
            }
            els.collectionPagers.forEach(function (pager) { pager.innerHTML = ''; });
        }
        if (target !== 'memory') {
            if (els.workList) { els.workList.innerHTML = ''; }
            if (els.workSummary) { els.workSummary.innerHTML = ''; }
        }
        if (target !== 'combat' || state.activeCombatScreen !== 'loadout') {
            if (els.combatCardList) { els.combatCardList.innerHTML = ''; }
            if (els.combatTeamSlots) { els.combatTeamSlots.innerHTML = ''; }
        }
    }

    function bindCollectionControls() {
        els.collectionModeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var mode = button.getAttribute('data-collection-mode') || 'album';
                state.collectionMode = mode === 'table' ? 'table' : 'album';
                state.collectionPage = 1;
                writeText(COLLECTION_MODE_KEY, state.collectionMode);
                renderCollectionTable();
            });
        });
        if (els.collectionPageSize) {
            els.collectionPageSize.addEventListener('change', function () {
                state.collectionPageSize = normalizePageSize(els.collectionPageSize.value);
                els.collectionPageSize.value = String(state.collectionPageSize);
                state.collectionPage = 1;
                writeText(COLLECTION_PAGE_SIZE_KEY, state.collectionPageSize);
                renderCollectionTable();
            });
        }
        if (els.collectionOwnedFilter) {
            els.collectionOwnedFilter.addEventListener('change', function () {
                state.collectionOwnedOnly = !!els.collectionOwnedFilter.checked;
                state.collectionPage = 1;
                renderCollectionTable();
            });
        }
        if (els.collectionRarityFilter) {
            els.collectionRarityFilter.addEventListener('change', function () {
                state.collectionRarity = normalizeCollectionRarity(els.collectionRarityFilter.value);
                els.collectionRarityFilter.value = state.collectionRarity;
                state.collectionPage = 1;
                renderCollectionTable();
            });
        }
        if (els.collectionTypeFilter) {
            els.collectionTypeFilter.addEventListener('change', function () {
                state.albumCategory = els.collectionTypeFilter.value || 'all';
                state.collectionPage = 1;
                renderCollectionTable();
            });
        }
        els.combatScreenTabs.forEach(function (button) {
            button.addEventListener('click', function () {
                showCombatScreen(button.getAttribute('data-combat-screen-tab') || 'battle');
            });
        });
        els.combatTeamSelects.forEach(function (select) {
            select.addEventListener('change', function () {
                loadCombatTeams();
                state.activeCombatTeam = Math.max(0, Math.min(4, clampInt(select.value, 0)));
                state.combatTeams.activeTeam = state.activeCombatTeam;
                state.draftCombatTeam = state.combatTeams.teams[state.activeCombatTeam].cards.slice();
                saveCombatTeams();
                renderCombatSetup();
            });
        });
        els.combatProfileNames.forEach(function (input) {
            input.addEventListener('input', function () {
                var profile = loadCombatProfile();
                profile.playerName = String(input.value || '').slice(0, 32);
                saveCombatProfile();
            });
        });
        els.combatProfileFavorites.forEach(function (select) {
            select.addEventListener('change', function () {
                var profile = loadCombatProfile();
                profile.favoriteCard = String(select.value || '').slice(0, 80);
                saveCombatProfile();
                renderCombatProfile();
            });
        });
        if (els.combatSaveTeam) { els.combatSaveTeam.addEventListener('click', saveDraftCombatTeam); }
        if (els.combatClearTeam) { els.combatClearTeam.addEventListener('click', clearDraftCombatTeam); }
        if (els.combatAutoTeam) { els.combatAutoTeam.addEventListener('click', autoBuildCombatTeam); }
        if (els.combatOnlyReady) { els.combatOnlyReady.addEventListener('change', renderCombatCardList); }
        if (els.combatRarityFilter) {
            els.combatRarityFilter.addEventListener('change', function () {
                state.combatRarityFilter = normalizeCollectionRarity(els.combatRarityFilter.value);
                els.combatRarityFilter.value = state.combatRarityFilter;
                renderCombatCardList();
            });
        }
        if (els.combatTypeFilter) {
            els.combatTypeFilter.addEventListener('change', function () {
                state.combatTypeFilter = els.combatTypeFilter.value || 'all';
                renderCombatCardList();
            });
        }
        if (els.combatStart) { els.combatStart.addEventListener('click', startTrainingCombat); }
        if (els.workClaimBtn) { els.workClaimBtn.addEventListener('click', function () { claimWorkRewards(); }); }
        els.combatActions.forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-combat-action') || '';
                if (action === 'attack') { playerAttack(); }
                if (action === 'defend') { playerDefend(); }
                if (action === 'switch' && els.combatBench) {
                    renderCombatBench();
                    els.combatBench.hidden = false;
                }
                if (action === 'flee') { fleeCombat(); }
            });
        });
    }

    function startFreeRewardTimer() {
        if (state.rewardsTimer) { return; }
        state.rewardsTimer = window.setInterval(function () {
            syncFreeRewards();
            renderDailyCounter();
            renderPackInventory();
            renderWorkBench();
        }, 30000);
    }

    function bindEvents() {
        bindMobilePanels();
        bindCollectionControls();
        els.packButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                openPack(button.getAttribute('data-pack-kind') || 'standard');
            });
        });
        if (els.packOpenAll) { els.packOpenAll.addEventListener('click', openAllPacks); }
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-shop-buy-pack]');
            if (!button || !root.contains(button)) { return; }
            event.preventDefault();
            buyPack(button.getAttribute('data-shop-buy-pack') || 'standard', button.getAttribute('data-shop-buy-amount') || 1, {
                free: button.getAttribute('data-shop-buy-free') === '1'
            });
        });
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-shop-buy-material]');
            if (!button || !root.contains(button)) { return; }
            event.preventDefault();
            buyMaterial(button.getAttribute('data-shop-buy-material') || '', button.getAttribute('data-shop-buy-amount') || 1);
        });
        if (els.exportBtn) { els.exportBtn.addEventListener('click', exportCollection); }
        if (els.importFile) {
            els.importFile.addEventListener('change', function () {
                var file = els.importFile.files && els.importFile.files[0] ? els.importFile.files[0] : null;
                if (!file) { return; }
                var reader = new FileReader();
                reader.onload = function () {
                    importCollection(String(reader.result || ''));
                    els.importFile.value = '';
                };
                reader.onerror = function () {
                    setStatus('No se pudo leer el archivo JSON.');
                    els.importFile.value = '';
                };
                reader.readAsText(file);
            });
        }
        if (els.resetBtn) { els.resetBtn.addEventListener('click', function () { resetCollection(); }); }
        if (els.bulkSellRarity) { els.bulkSellRarity.addEventListener('change', renderBulkSellPreview); }
        if (els.bulkSellKeepBest) { els.bulkSellKeepBest.addEventListener('change', renderBulkSellPreview); }
        if (els.bulkSellBtn) { els.bulkSellBtn.addEventListener('click', function () { sellCardsByRarity(); }); }
    }

    loadCollectionViewPrefs();
    decorateIconNavigation();
    updateDesktopHashPanels();
    window.addEventListener('hashchange', updateDesktopHashPanels);
    bindEvents();
    loadCollection();
    renderMobileSwitchPrompt();
    renderDailyCounter();
    renderPackInventory();
    startFreeRewardTimer();
    loadCatalog();

    window.hgGameCards = {
        loadCatalog: loadCatalog,
        loadCollection: loadCollection,
        saveCollection: saveCollection,
        openPack: openPack,
        pickRarity: pickRarity,
        pickCardByRarity: pickCardByRarity,
        rollStat: rollStat,
        renderPackResults: renderPackResults,
        renderCollectionTable: renderCollectionTable,
        exportCollection: exportCollection,
        importCollection: importCollection,
        resetCollection: resetCollection,
        addPack: addPack,
        buyPack: buyPack,
        recycleCopy: recycleCopy,
        recycleDuplicateCopies: recycleDuplicateCopies,
        recycleAllCopies: recycleAllCopies,
        sellCardsByRarity: sellCardsByRarity
    };
})();
