(function () {
    'use strict';

    var STORAGE_KEY = 'hg_card_collection_v2';
    var LEGACY_STORAGE_KEY = 'hg_card_collection_v1';
    var CARD_SHOP_STATE_KEY = 'hg_card_shop_state_v2';
    var LEGACY_FREE_REWARDS_KEY = 'hg_card_free_rewards_v1';
    var COLLECTION_MODE_KEY = 'hg_card_collection_mode_v2';
    var LEGACY_COLLECTION_MODE_KEY = 'hg_card_collection_mode_v1';
    var COLLECTION_PAGE_SIZE_KEY = 'hg_card_collection_page_size_v2';
    var LEGACY_COLLECTION_PAGE_SIZE_KEY = 'hg_card_collection_page_size_v1';
    var COLLECTION_PAGE_SIZES_DESKTOP = [12, 24, 48, 96];
    var COLLECTION_PAGE_SIZES_MOBILE = [10, 20, 50];
    var COMBAT_TEAMS_KEY = 'hg_card_combat_teams_v2';
    var LEGACY_COMBAT_TEAMS_KEY = 'hg_card_combat_teams_v1';
    var COMBAT_PROFILE_KEY = 'hg_card_combat_profile_v2';
    var LEGACY_COMBAT_PROFILE_KEY = 'hg_card_combat_profile_v1';
    var DAILY_BOSS_REWARD_KEY = 'hg_card_daily_boss_reward_v1';
    var DAILY_BOSS_STATE_KEY = 'hg_card_daily_boss_state_v1';
    var STARTING_MNEMONES = 0;
    var STARTING_REMORIAS = 0;
    var MAX_MNEMONES = 0;
    var MAX_REMORIAS = 0;
    var PACK_SIZE = 0;
    var MAX_PACK_STOCK = 0;
    var COMBAT_SOUNDS = {};
    var COMBAT_ATTACK_MS = 420;
    var COMBAT_DEFEND_MS = 560;
    var COMBAT_DEFEAT_MS = 760;
    var COMBAT_ENTRY_MS = 620;
    var COMBAT_TURN_GAP_MS = 80;
    var COMBAT_HIT_SOUND_DELAY_MS = 150;
    var COMBAT_DIFFICULTY_TABLE = {
        apprentice: { label: 'Aprendiz', weights: { common: 72, unusual: 22, rare: 6, epic: 0, legendary: 0, mythic: 0 } },
        hobbyist: { label: 'Aficionado', weights: { common: 44, unusual: 34, rare: 17, epic: 5, legendary: 0, mythic: 0 } },
        expert: { label: 'Experto', weights: { common: 12, unusual: 28, rare: 34, epic: 18, legendary: 6, mythic: 2 } },
        master: { label: 'Maestro', weights: { common: 0, unusual: 8, rare: 32, epic: 36, legendary: 18, mythic: 6 } },
        nemesis: { label: 'Némesis', weights: { common: 0, unusual: 0, rare: 12, epic: 34, legendary: 36, mythic: 18 } }
    };
    var COMBAT_ADVANCED_RULES = {
        defendHealRatio: 0.33,
        defendDefMultiplier: 1.5,
        enemyDefendHpRatio: 0.35,
        enemyDefendChance: 0.34,
        enemyPickAttempts: 12,
        damageRandomBonusMin: 1,
        damageRandomBonusMax: 20,
        rarityAdvantageStep: 0.2,
        rarityDisadvantageStep: 0.13,
        rarityDisadvantageMinMultiplier: 0.35,
        rarityShields: {
            common: 1,
            unusual: 2,
            rare: 3,
            epic: 4,
            legendary: 5,
            mythic: 6,
            stigmatic: 7
        }
    };
    var DAILY_BOSS_HP_MULTIPLIER_MIN = 0;
    var DAILY_BOSS_HP_MULTIPLIER_MAX = 0;
    var DAILY_BOSS_STIGMATIC_DAMAGE_MULTIPLIER = 0;
    var DAILY_BOSS_SHIELD_BREAK_CHANCE = 0;
    var TRAINING_REWARD_TABLE = {
        base: 5,
        rollMin: 1,
        rollMax: 5,
        difficultyMultipliers: {
            apprentice: 1,
            hobbyist: 1.25,
            expert: 1.5,
            master: 2,
            nemesis: 3
        }
    };
    var DAILY_BOSS_CARD_REWARD = {
        rarity: 'stigmatic'
    };
    var DAILY_BOSS_LOOT_TABLE = {
        mnemones: { min: 500, max: 1200 },
        remorias: { min: 120, max: 420 },
        guaranteedMaterialDrop: [
            { key: 'babylon_shred', chance: 0.12, amount: 1 },
            { key: 'stigma_orb', chance: 0.30, amount: 1 },
            { key: 'icarus_vial', chance: 0.58, amount: 1 }
        ],
        bonusDrops: [
            { key: 'stigma_orb', chance: 0.18, amount: 1 }
        ]
    };
    var MOVE_DEBUFF_MIN_RATIO = 0;
    var MOVE_BUFF_MAX_RATIO = 0;
    var MOVE_LEARN_RULES = {};
    var MOVE_LIBRARY = {};
    var DAILY_FREE_PACK_CAP = 0;
    var DAILY_SHOP_PACK_CAP = 0;
    var DAILY_MAGIC_PACK_CAP = 0;
    var SHOP_QUANTITIES = [];
    var FREE_SHOP_QUANTITIES = [];
    var DAILY_GIFT_MATERIAL_KEYS = [];
    var PACK_KINDS = [];
    var SHOP_PACK_KINDS = [];
    var PACK_DAILY_CAPS = {};
    var PACK_PRICES = {};
    var RECYCLE_VALUES = {};
    var WORK_MAX_ASSIGNMENTS = 0;
    var WORK_MIN_DURATION_MS = 0;
    var WORK_RARITY_BASE = {};
    var RARITY_UPGRADE_REQUIRED = 0;
    var RARITY_UPGRADE_MIN_QUALITY = 0;
    var RARITY_UPGRADE_MULTIPLIERS = [];
    var QUALITY_UPGRADE_MAX_SLOTS = 0;
    var UPGRADE_COST_BY_RARITY = {};
    var RARITY_UPGRADE_MATERIALS = {};
    var UPGRADE_MATERIALS = {};
    var SKILL_SLOT_COUNT = 0;
    var SKILL_COST_MULTIPLIER_BY_RARITY = {};
    var SKILL_BASE_MNEMONES = 0;
    var SKILL_MATERIAL_KEY = '';
    var RARITY_STAT_RANGES = {};
    var RARITY_ORDER = [];
    var NATURAL_RARITY_ORDER = [];
    var RARITY_UPGRADE_ORDER = [];
    var RARITY_WEIGHTS = {};
    var PACK_RARITY_WEIGHTS = {};
    var PACK_LABELS = {};
    var PACK_CONTENTS = {};
    var PACK_SKINS = {};
    var CARD_GAME_ICONS = {};
    var POWER_TYPES = [];
    var CHRONICLE_TYPES = [];
    var RELIC_TYPES = [];
    var LINEAGE_TYPES = [];
    var ESSENCE_TYPES = [];
    var RARITY_LABELS = {};
    var RARITY_ICONS = {};
    var RARITY_SHORT = {};
    var RARITY_SKINS = {};
    var TYPE_LABELS = {};
    var TYPE_ORDER = [];
    var TYPE_EMOJI = {};
    var TYPE_ALIASES = {};
    var TYPE_ICON_SVG = {};
    var UI_TEXTS = {};
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
        collectionHasMovesOnly: false,
        collectionInTeamOnly: false,
        collectionWorkingOnly: false,
        collectionSearch: '',
        collectionRarity: 'all',
        collectionMode: 'album',
        collectionPage: 1,
        collectionPageSize: root.getAttribute('data-mobile') === '1' ? 20 : 24,
        combat: null,
        combatTeams: null,
        combatProfile: null,
        dailyBoss: null,
        activeCombatTeam: 0,
        draftCombatTeam: [],
        activeCombatScreen: 'battle',
        combatMode: 'training',
        combatAnimating: false,
        combatCommandView: 'root',
        combatRarityFilter: 'all',
        combatTypeFilter: 'all',
        combatSort: 'quality',
        catalog: [],
        catalogById: {},
        shopProducts: [],
        shopState: null,
        rewardsTimer: null,
        workTimer: null,
        collection: null,
        table: null
    };

    var els = {
        packButtons: Array.prototype.slice.call(document.querySelectorAll('[data-pack-kind]')),
        shopItems: Array.prototype.slice.call(document.querySelectorAll('[data-shop-pack], [data-shop-material], [data-shop-exchange-remorias], [data-shop-daily-gift]')),
        shopButtons: Array.prototype.slice.call(document.querySelectorAll('[data-shop-buy-pack]')),
        packStocks: Array.prototype.slice.call(document.querySelectorAll('[data-pack-stock]')),
        packGrid: document.querySelector('[data-pack-grid]'),
        packOpenAll: document.querySelector('[data-pack-open-all]'),
        mnemonesCounters: Array.prototype.slice.call(document.querySelectorAll('[data-mnemones-counter]')),
        remoriasCounters: Array.prototype.slice.call(document.querySelectorAll('[data-remorias-counter]')),
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
        collectionHasMovesFilter: document.querySelector('[data-collection-has-moves-filter]'),
        collectionInTeamFilter: document.querySelector('[data-collection-in-team-filter]'),
        collectionWorkingFilter: document.querySelector('[data-collection-working-filter]'),
        collectionNameFilter: document.querySelector('[data-collection-name-filter]'),
        collectionRarityFilter: document.querySelector('[data-collection-rarity-filter]'),
        collectionTypeFilter: document.querySelector('[data-collection-type-filter]'),
        collectionViews: Array.prototype.slice.call(document.querySelectorAll('[data-collection-view]')),
        collectionPagers: Array.prototype.slice.call(document.querySelectorAll('[data-collection-pager]')),
        mobileTabs: Array.prototype.slice.call(document.querySelectorAll('[data-mobile-panel-tab]')),
        mobilePanels: Array.prototype.slice.call(document.querySelectorAll('[data-mobile-panel]')),
        combatScreenTabs: Array.prototype.slice.call(document.querySelectorAll('[data-combat-screen-tab]')),
        combatScreenPanels: Array.prototype.slice.call(document.querySelectorAll('[data-combat-screen]')),
        combatModeButtons: Array.prototype.slice.call(document.querySelectorAll('[data-combat-mode]')),
        dailyBossSummaries: Array.prototype.slice.call(document.querySelectorAll('[data-daily-boss-summary]')),
        combatDifficultyWraps: Array.prototype.slice.call(document.querySelectorAll('[data-combat-difficulty-wrap]')),
        combatTeamSelects: Array.prototype.slice.call(document.querySelectorAll('[data-combat-team-select], [data-combat-team-select-mirror]')),
        combatTeamSelect: document.querySelector('[data-combat-team-select]'),
        combatTeamNames: Array.prototype.slice.call(document.querySelectorAll('[data-combat-team-name]')),
        combatTeamPreviews: Array.prototype.slice.call(document.querySelectorAll('[data-combat-team-preview]')),
        combatProfileNames: Array.prototype.slice.call(document.querySelectorAll('[data-combat-profile-name]')),
        combatTeamSlots: document.querySelector('[data-combat-team-slots]'),
        combatSaveTeam: document.querySelector('[data-combat-save-team]'),
        combatClearTeam: document.querySelector('[data-combat-clear-team]'),
        combatAutoTeam: document.querySelector('[data-combat-auto-team]'),
        combatOnlyReady: document.querySelector('[data-combat-only-ready]'),
        combatRarityFilter: document.querySelector('[data-combat-rarity-filter]'),
        combatTypeFilter: document.querySelector('[data-combat-type-filter]'),
        combatSort: document.querySelector('[data-combat-sort]'),
        combatCardList: document.querySelector('[data-combat-card-list]'),
        combatDifficulty: document.querySelector('[data-combat-difficulty]'),
        combatSetups: Array.prototype.slice.call(document.querySelectorAll('.hg-combat-setup')),
        combatStart: document.querySelector('[data-combat-start]'),
        combatActions: Array.prototype.slice.call(document.querySelectorAll('[data-combat-action]')),
        combatExtraActionSlots: Array.prototype.slice.call(document.querySelectorAll('[data-combat-extra-action-slot]')),
        combatCommandViews: Array.prototype.slice.call(document.querySelectorAll('[data-combat-command-view]')),
        combatCommandButtons: Array.prototype.slice.call(document.querySelectorAll('[data-combat-command]')),
        combatCommandBackButtons: Array.prototype.slice.call(document.querySelectorAll('[data-combat-command-back]')),
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

    function uiText(key, fallback, values) {
        var template = Object.prototype.hasOwnProperty.call(UI_TEXTS, key) ? UI_TEXTS[key] : fallback;
        template = String(template || '');
        if (!values) { return template; }
        return template.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
            return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
        });
    }

    function decorateIconNavigation() {
        var mobileLabels = {
            packs: 'Sobres',
            shop: uiText('nav.shop', 'Tienda'),
            collection: uiText('nav.collection', 'Colección'),
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

    function packSkin(packKind) {
        return PACK_SKINS[packKind] || {};
    }

    function applyPackSkin(button, packKind) {
        if (!button) { return; }
        var skin = packSkin(packKind);
        if (skin.primary) { button.style.setProperty('--pack-primary', skin.primary); }
        if (skin.secondary) { button.style.setProperty('--pack-secondary', skin.secondary); }
        if (skin.dark) { button.style.setProperty('--pack-dark', skin.dark); }
        if (skin.accent) { button.style.setProperty('--pack-accent', skin.accent); }
        var seal = button.querySelector('.hg-pack__seal');
        if (seal && skin.seal) { seal.textContent = skin.seal; }
        var title = button.querySelector('.hg-pack__title');
        if (title) { title.textContent = packLabel(packKind); }
        var count = button.querySelector('.hg-pack__count');
        if (count) { count.textContent = skin.summary || packContents(packKind); }
    }

    function applyRaritySkins() {
        Object.keys(RARITY_SKINS || {}).forEach(function (rarityKey) {
            var skin = RARITY_SKINS[rarityKey] || {};
            if (skin.color) { root.style.setProperty('--rarity-' + rarityKey, skin.color); }
            if (skin.bg) { root.style.setProperty('--rarity-bg-' + rarityKey, skin.bg); }
            if (skin.head) { root.style.setProperty('--rarity-head-' + rarityKey, skin.head); }
            if (skin.body) { root.style.setProperty('--rarity-body-' + rarityKey, skin.body); }
            if (skin.rowBg) { root.style.setProperty('--rarity-row-' + rarityKey, skin.rowBg); }
        });
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
        overlay.setAttribute('aria-label', options.title || uiText('confirm.default_title', 'Confirmar acción'));

        var panel = document.createElement('div');
        panel.className = 'hg-confirm-modal__panel';
        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        var title = document.createElement('h3');
        title.textContent = options.title || 'Confirmar acción';

        var text = document.createElement('p');
        if (options.messageHtml) {
            text.innerHTML = options.messageHtml;
        } else {
            text.textContent = message;
        }

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
            version: 2,
            createdAt: now,
            updatedAt: now,
            favoriteCopyIds: [],
            ownedCards: [],
            workAssignments: {},
            workPendingRewards: 0,
            currency: { mnemones: STARTING_MNEMONES, remorias: STARTING_REMORIAS },
            packInventory: normalizePackInventory({}),
            dailyShopPackPurchases: normalizeDailyShopPackPurchases(null),
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
            setStatus(uiText('status.local_storage_error', 'No se pudo guardar en localStorage.'));
        }
    }

    function readMigratedJson(key, legacyKey, fallback) {
        var data = readJson(key, null);
        if (data !== null && data !== undefined) { return data; }
        data = readJson(legacyKey, null);
        if (data !== null && data !== undefined) {
            writeJson(key, data);
            return data;
        }
        return fallback;
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

    function readMigratedText(key, legacyKey, fallback) {
        var value = readText(key, null);
        if (value !== null && value !== undefined) { return value; }
        value = readText(legacyKey, null);
        if (value !== null && value !== undefined) {
            writeText(key, value);
            return value;
        }
        return fallback;
    }

    function collectionPageSizeOptions() {
        return state.mobile ? COLLECTION_PAGE_SIZES_MOBILE : COLLECTION_PAGE_SIZES_DESKTOP;
    }

    function defaultCollectionPageSize() {
        return state.mobile ? 20 : 24;
    }

    function normalizePageSize(value) {
        var fallback = defaultCollectionPageSize();
        var size = clampInt(value, fallback);
        return collectionPageSizeOptions().indexOf(size) === -1 ? fallback : size;
    }

    function normalizeCollectionRarity(value) {
        value = String(value || 'all');
        return value === 'all' || RARITY_ORDER.indexOf(value) !== -1 ? value : 'all';
    }

    function normalizeSearchText(value) {
        value = String(value || '').trim().toLowerCase();
        if (typeof value.normalize === 'function') {
            value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return value.replace(/\s+/g, ' ');
    }

    function normalizeRarity(value, fallback) {
        value = String(value || '');
        return RARITY_ORDER.indexOf(value) !== -1 ? value : (fallback || 'common');
    }

    function loadCollectionViewPrefs() {
        var mode = readMigratedText(COLLECTION_MODE_KEY, LEGACY_COLLECTION_MODE_KEY, 'album');
        state.collectionMode = mode === 'table' ? 'table' : 'album';
        state.collectionPageSize = normalizePageSize(readMigratedText(COLLECTION_PAGE_SIZE_KEY, LEGACY_COLLECTION_PAGE_SIZE_KEY, String(defaultCollectionPageSize())));
        if (els.collectionPageSize) {
            els.collectionPageSize.value = String(state.collectionPageSize);
        }
        if (els.collectionOwnedFilter) {
            state.collectionOwnedOnly = !!els.collectionOwnedFilter.checked;
        }
        if (els.collectionHasMovesFilter) {
            state.collectionHasMovesOnly = !!els.collectionHasMovesFilter.checked;
        }
        if (els.collectionInTeamFilter) {
            state.collectionInTeamOnly = !!els.collectionInTeamFilter.checked;
        }
        if (els.collectionWorkingFilter) {
            state.collectionWorkingOnly = !!els.collectionWorkingFilter.checked;
        }
        if (els.collectionNameFilter) {
            state.collectionSearch = normalizeSearchText(els.collectionNameFilter.value);
        }
        if (els.collectionRarityFilter) {
            state.collectionRarity = normalizeCollectionRarity(els.collectionRarityFilter.value);
            els.collectionRarityFilter.value = state.collectionRarity;
        }
    }

    function createShopState() {
        return {
            version: 3,
            freePackDate: dailyFreePackDate(),
            freePacksClaimed: 0,
            dailyGiftDate: dailyFreePackDate(),
            dailyGiftClaimed: 0,
            dailyGiftKey: DAILY_GIFT_MATERIAL_KEYS[0],
            shopPackDate: dailyFreePackDate(),
            shopPackPurchases: normalizeShopPackPurchases({})
        };
    }

    function rollDailyGiftMaterialKey() {
        return DAILY_GIFT_MATERIAL_KEYS[Math.floor(Math.random() * DAILY_GIFT_MATERIAL_KEYS.length)] || 'mnemo_glyph';
    }

    function dailyFreePackDate() {
        var now = new Date();
        return now.getFullYear() + '-' +
            String(now.getMonth() + 1).padStart(2, '0') + '-' +
            String(now.getDate()).padStart(2, '0');
    }

    function dailyBossRewardState() {
        var data = readJson(DAILY_BOSS_REWARD_KEY, null);
        if (!data || typeof data !== 'object') {
            return { date: '', rewardCopyId: '' };
        }
        return {
            date: String(data.date || ''),
            rewardCopyId: String(data.rewardCopyId || '')
        };
    }

    function dailyBossRewardClaimedToday() {
        return dailyBossRewardState().date === dailyFreePackDate();
    }

    function markDailyBossRewardClaimed(copyId) {
        writeJson(DAILY_BOSS_REWARD_KEY, {
            date: dailyFreePackDate(),
            rewardCopyId: String(copyId || '')
        });
    }

    function dailyBossActiveAttempt(value) {
        value = value && typeof value === 'object' ? value : null;
        if (!value) { return null; }
        var seenRisk = {};
        var seenDefeated = {};
        return {
            startedAt: normalizeTimestamp(value.startedAt, Date.now()),
            riskedCopyIds: (Array.isArray(value.riskedCopyIds) ? value.riskedCopyIds : []).map(function (id) {
                return String(id || '').slice(0, 80);
            }).filter(function (id) {
                if (!id || seenRisk[id]) { return false; }
                seenRisk[id] = true;
                return true;
            }).slice(0, 5),
            defeatedCopyIds: (Array.isArray(value.defeatedCopyIds) ? value.defeatedCopyIds : []).map(function (id) {
                return String(id || '').slice(0, 80);
            }).filter(function (id) {
                if (!id || seenDefeated[id]) { return false; }
                seenDefeated[id] = true;
                return true;
            }).slice(0, 5)
        };
    }

    function createDailyBossState(card) {
        if (!card) { return null; }
        var copy = createCardCopy(card, { rarity: 'stigmatic', instanceId: 'daily-boss-' + dailyFreePackDate() });
        var hpMultiplier = rollStat(DAILY_BOSS_HP_MULTIPLIER_MIN, DAILY_BOSS_HP_MULTIPLIER_MAX);
        var maxHp = Math.max(1, clampInt(copy.hp * hpMultiplier, copy.hp));
        return {
            version: 1,
            date: dailyFreePackDate(),
            cardId: card.card_id,
            cardName: card.card_name,
            instanceId: copy.instanceId,
            hpMultiplier: hpMultiplier,
            hp: maxHp,
            maxHp: maxHp,
            atk: Math.max(1, clampInt(copy.atk * 0.35, copy.atk)),
            def: Math.max(1, clampInt(copy.def * 0.15, 1)),
            quality: calculatedQualityScore(copy, card),
            attempts: 0,
            completed: false,
            activeAttempt: null,
            updatedAt: nowIso()
        };
    }

    function normalizeDailyBossState(data, card) {
        if (!card) { return null; }
        if (!data || typeof data !== 'object' || data.date !== dailyFreePackDate() || clampInt(data.cardId, 0) !== card.card_id) {
            return createDailyBossState(card);
        }
        var maxHp = Math.max(1, clampInt(data.maxHp, card.hp_max || 1));
        var hp = Math.max(0, Math.min(maxHp, clampInt(data.hp, maxHp)));
        return {
            version: 1,
            date: dailyFreePackDate(),
            cardId: card.card_id,
            cardName: card.card_name,
            instanceId: String(data.instanceId || ('daily-boss-' + dailyFreePackDate())).slice(0, 80),
            hpMultiplier: Math.max(DAILY_BOSS_HP_MULTIPLIER_MIN, Math.min(DAILY_BOSS_HP_MULTIPLIER_MAX, clampInt(data.hpMultiplier, DAILY_BOSS_HP_MULTIPLIER_MIN))),
            hp: hp,
            maxHp: maxHp,
            atk: Math.max(1, clampInt(data.atk, card.atk_max || 1)),
            def: Math.max(1, clampInt(data.def, card.def_max || 1)),
            quality: clampQuality(data.quality, 100),
            attempts: Math.max(0, clampInt(data.attempts, 0)),
            completed: !!data.completed || dailyBossRewardClaimedToday() || hp <= 0,
            activeAttempt: dailyBossActiveAttempt(data.activeAttempt),
            updatedAt: String(data.updatedAt || nowIso())
        };
    }

    function loadDailyBossState() {
        var card = pickDailyBossCard();
        if (!card) {
            state.dailyBoss = null;
            return null;
        }
        state.dailyBoss = normalizeDailyBossState(readJson(DAILY_BOSS_STATE_KEY, null), card);
        saveDailyBossState();
        return state.dailyBoss;
    }

    function saveDailyBossState() {
        if (!state.dailyBoss) { return; }
        state.dailyBoss.updatedAt = nowIso();
        writeJson(DAILY_BOSS_STATE_KEY, state.dailyBoss);
    }

    function resetDailyBossState() {
        try {
            window.localStorage.removeItem(DAILY_BOSS_STATE_KEY);
            window.localStorage.removeItem(DAILY_BOSS_REWARD_KEY);
        } catch (e) {}
        state.combat = null;
        state.dailyBoss = null;
        loadDailyBossState();
        renderCombatSetup();
        renderCombatBattle();
        setCombatMessage(uiText('combat.daily_reset', 'Jefe diario reiniciado para depuración.'));
    }

    function dailyBossEntryFromState() {
        var bossState = state.dailyBoss || loadDailyBossState();
        var card = bossState ? state.catalogById[String(bossState.cardId || '')] : null;
        if (!bossState || !card) { return null; }
        var copy = {
            instanceId: bossState.instanceId,
            cardId: card.card_id,
            rarity: 'stigmatic',
            hp: bossState.maxHp,
            atk: bossState.atk,
            def: bossState.def,
            quality: bossState.quality,
            obtainedAt: bossState.date
        };
        return { card: cardForCopy(card, copy), baseCard: card, copy: copy, bossState: bossState };
    }

    function updateDailyBossHp(hp) {
        if (!state.combat || state.combat.mode !== 'daily-boss') { return; }
        var bossState = state.dailyBoss || loadDailyBossState();
        if (!bossState) { return; }
        bossState.hp = Math.max(0, Math.min(bossState.maxHp, clampInt(hp, bossState.hp)));
        if (bossState.hp <= 0) { bossState.completed = true; }
        saveDailyBossState();
        renderDailyBossSummary();
    }

    function startDailyBossAttempt(teamIds) {
        var bossState = state.dailyBoss || loadDailyBossState();
        if (!bossState) { return null; }
        bossState.attempts = Math.max(0, clampInt(bossState.attempts, 0)) + 1;
        bossState.activeAttempt = {
            startedAt: Date.now(),
            riskedCopyIds: teamIds.slice(0, 5),
            defeatedCopyIds: []
        };
        saveDailyBossState();
        return bossState.activeAttempt;
    }

    function markDailyBossCopyDefeated(copyId) {
        if (!state.combat || state.combat.mode !== 'daily-boss') { return; }
        var id = String(copyId || '');
        var bossState = state.dailyBoss || loadDailyBossState();
        if (!id || !bossState || !bossState.activeAttempt) { return; }
        if (bossState.activeAttempt.defeatedCopyIds.indexOf(id) === -1) {
            bossState.activeAttempt.defeatedCopyIds.push(id);
            saveDailyBossState();
        }
    }

    function destroyDailyBossCopies(copyIds) {
        if (!copyIds || !copyIds.length) { return 0; }
        if (!state.collection) { loadCollection(); }
        var remove = {};
        copyIds.forEach(function (id) {
            id = String(id || '');
            if (id) { remove[id] = true; }
        });
        var count = 0;
        state.collection.ownedCards = (state.collection.ownedCards || []).filter(function (copy) {
            var id = String(copy && copy.instanceId || '');
            if (remove[id]) {
                count++;
                return false;
            }
            return true;
        });
        state.collection.favoriteCopyIds = (state.collection.favoriteCopyIds || []).filter(function (id) {
            return !remove[String(id || '')];
        });
        Object.keys(state.collection.workAssignments || {}).forEach(function (id) {
            if (remove[String(id || '')]) { delete state.collection.workAssignments[id]; }
        });
        removeCopiesFromCombatTeams(remove);
        cleanCombatTeamsAgainstCollection(true);
        saveCollection();
        renderSummary();
        renderCollectionTable();
        renderCombatSetup();
        return count;
    }

    function destroyDailyBossDefeatedCards(clearAttempt) {
        var bossState = state.dailyBoss || loadDailyBossState();
        if (!bossState || !bossState.activeAttempt) { return 0; }
        var defeated = bossState.activeAttempt.defeatedCopyIds || [];
        var count = destroyDailyBossCopies(defeated);
        if (clearAttempt !== false) {
            bossState.activeAttempt = null;
            saveDailyBossState();
        }
        return count;
    }

    function finishDailyBossAttempt(completed) {
        var bossState = state.dailyBoss || loadDailyBossState();
        if (!bossState) { return; }
        bossState.activeAttempt = null;
        bossState.completed = !!completed || bossState.completed;
        saveDailyBossState();
        renderDailyBossSummary();
    }

    function interruptDailyBossCombat(showMessage) {
        if (!state.combat || state.combat.mode !== 'daily-boss' || state.combat.over) { return 0; }
        var lost = destroyDailyBossDefeatedCards(true);
        state.combat.over = true;
        state.combat = null;
        if (showMessage && lost > 0) {
            setCombatMessage(uiText('combat.daily_interrupted', 'Intento del Jefe diario interrumpido. Cartas derrotadas perdidas: {lost}.', { lost: lost }));
        }
        renderCombatBattle();
        renderCombatSetup();
        return lost;
    }

    function recoverAbandonedDailyBossAttempt() {
        if (state.combat) { return; }
        var bossState = loadDailyBossState();
        if (!bossState || !bossState.activeAttempt) { return; }
        var lost = destroyDailyBossDefeatedCards(true);
        if (lost > 0) {
            setStatus(uiText('combat.daily_previous_closed', 'Intento anterior del Jefe diario cerrado. Cartas derrotadas perdidas: {lost}.', { lost: lost }));
        }
    }

    function msUntilDailyFreeReset() {
        var now = new Date();
        var next = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 0, 0, 0, 0);
        return Math.max(0, next.getTime() - now.getTime());
    }

    function dailyFreeResetProgress() {
        var dayMs = 24 * 60 * 60 * 1000;
        return Math.max(0, Math.min(100, ((dayMs - msUntilDailyFreeReset()) / dayMs) * 100));
    }

    function formatResetDuration(ms) {
        var totalMinutes = Math.max(0, Math.ceil(ms / 60000));
        var hours = Math.floor(totalMinutes / 60);
        var minutes = totalMinutes % 60;
        if (hours <= 0) {
            return minutes + ' min';
        }
        return hours + ' h ' + String(minutes).padStart(2, '0') + ' min';
    }

    function normalizeTimestamp(value, fallback) {
        var n = Number(value);
        return Number.isFinite(n) && n > 0 ? n : fallback;
    }

    function loadShopState() {
        if (state.shopState) { return state.shopState; }
        var fallback = createShopState();
        var data = readMigratedJson(CARD_SHOP_STATE_KEY, LEGACY_FREE_REWARDS_KEY, null);
        if (!data || typeof data !== 'object') {
            state.shopState = fallback;
            writeJson(CARD_SHOP_STATE_KEY, state.shopState);
            return state.shopState;
        }
        var today = dailyFreePackDate();
        var storedDate = typeof data.freePackDate === 'string' ? data.freePackDate : today;
        var storedShopDate = typeof data.shopPackDate === 'string' ? data.shopPackDate : today;
        state.shopState = {
            version: 3,
            freePackDate: today,
            freePacksClaimed: storedDate === today
                ? Math.max(0, Math.min(DAILY_FREE_PACK_CAP, clampInt(data.freePacksClaimed, 0)))
                : 0,
            dailyGiftDate: typeof data.dailyGiftDate === 'string' ? data.dailyGiftDate : today,
            dailyGiftClaimed: (typeof data.dailyGiftDate === 'string' ? data.dailyGiftDate : today) === today
                ? Math.max(0, Math.min(1, clampInt(data.dailyGiftClaimed, 0)))
                : 0,
            dailyGiftKey: DAILY_GIFT_MATERIAL_KEYS.indexOf(String(data.dailyGiftKey || '')) !== -1
                ? String(data.dailyGiftKey)
                : rollDailyGiftMaterialKey(),
            shopPackDate: today,
            shopPackPurchases: storedShopDate === today
                ? normalizeShopPackPurchases(data.shopPackPurchases)
                : normalizeShopPackPurchases({})
        };
        if (state.shopState.dailyGiftDate !== today) {
            state.shopState.dailyGiftDate = today;
            state.shopState.dailyGiftClaimed = 0;
            state.shopState.dailyGiftKey = rollDailyGiftMaterialKey();
        }
        writeJson(CARD_SHOP_STATE_KEY, state.shopState);
        return state.shopState;
    }

    function saveShopState() {
        if (!state.shopState) { state.shopState = createShopState(); }
        writeJson(CARD_SHOP_STATE_KEY, state.shopState);
    }

    function syncShopState() {
        if (state.isAdmin) { return { packs: 0, mnemones: 0 }; }
        var rewards = loadShopState();
        var today = dailyFreePackDate();
        if (rewards.freePackDate !== today) {
            rewards.freePackDate = today;
            rewards.freePacksClaimed = 0;
            rewards.dailyGiftDate = today;
            rewards.dailyGiftClaimed = 0;
            rewards.dailyGiftKey = rollDailyGiftMaterialKey();
            saveShopState();
        }
        if (rewards.dailyGiftDate !== today) {
            rewards.dailyGiftDate = today;
            rewards.dailyGiftClaimed = 0;
            rewards.dailyGiftKey = rollDailyGiftMaterialKey();
            saveShopState();
        }
        if (rewards.shopPackDate !== today) {
            rewards.shopPackDate = today;
            rewards.shopPackPurchases = normalizeShopPackPurchases({});
            saveShopState();
        }
        return { packs: 0, mnemones: 0 };
    }

    function dailyFreePacksRemaining() {
        if (state.isAdmin) {
            return Infinity;
        }
        syncShopState();
        return Math.max(0, DAILY_FREE_PACK_CAP - Math.max(0, Math.min(DAILY_FREE_PACK_CAP, clampInt((state.shopState || {}).freePacksClaimed, 0))));
    }

    function claimDailyFreePacks(amount) {
        amount = Math.max(1, clampInt(amount, 1));
        if (state.isAdmin) { return true; }
        var rewards = loadShopState();
        syncShopState();
        if (dailyFreePacksRemaining() < amount) { return false; }
        rewards.freePacksClaimed = Math.min(DAILY_FREE_PACK_CAP, clampInt(rewards.freePacksClaimed, 0) + amount);
        saveShopState();
        return true;
    }

    function dailyGiftState() {
        if (state.isAdmin) {
            return { key: rollDailyGiftMaterialKey(), claimed: 0 };
        }
        syncShopState();
        return {
            key: String((state.shopState || {}).dailyGiftKey || rollDailyGiftMaterialKey()),
            claimed: Math.max(0, Math.min(1, clampInt((state.shopState || {}).dailyGiftClaimed, 0)))
        };
    }

    function dailyGiftRemaining() {
        return state.isAdmin ? Infinity : Math.max(0, 1 - dailyGiftState().claimed);
    }

    function claimDailyGift() {
        if (state.isAdmin) { return true; }
        var rewards = loadShopState();
        syncShopState();
        if (dailyGiftRemaining() <= 0) { return false; }
        rewards.dailyGiftClaimed = 1;
        saveShopState();
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

    function normalizeShopPackPurchases(purchases) {
        var out = {};
        PACK_KINDS.forEach(function (kind) {
            out[kind] = Math.max(0, Math.min(dailyShopPackCap(kind), clampInt(purchases && purchases[kind], 0)));
        });
        return out;
    }

    function normalizeDailyShopPackPurchases(value) {
        var today = dailyFreePackDate();
        var out = { date: today, packs: {} };
        PACK_KINDS.forEach(function (kind) {
            out.packs[kind] = 0;
        });
        if (!value || typeof value !== 'object' || value.date !== today || !value.packs || typeof value.packs !== 'object') {
            return out;
        }
        PACK_KINDS.forEach(function (kind) {
            out.packs[kind] = Math.max(0, Math.min(dailyShopPackCap(kind), clampInt(value.packs[kind], 0)));
        });
        return out;
    }

    function syncDailyShopPackPurchases() {
        var shopState = loadShopState();
        var today = dailyFreePackDate();
        var changed = false;
        if (shopState.shopPackDate !== today) {
            shopState.shopPackDate = today;
            shopState.shopPackPurchases = {};
            changed = true;
        }
        var normalized = normalizeShopPackPurchases(shopState.shopPackPurchases);
        if (JSON.stringify(normalized) !== JSON.stringify(shopState.shopPackPurchases || {})) {
            changed = true;
        }
        shopState.shopPackPurchases = normalized;
        if (changed) { saveShopState(); }
        return shopState.shopPackPurchases;
    }

    function dailyShopPackRemaining(packKind) {
        if (packKind === 'standard') { return Infinity; }
        var purchases = syncDailyShopPackPurchases();
        return Math.max(0, dailyShopPackCap(packKind) - Math.max(0, clampInt(purchases[packKind], 0)));
    }

    function dailyShopPackCap(packKind) {
        if (Object.prototype.hasOwnProperty.call(PACK_DAILY_CAPS, packKind)) {
            return PACK_DAILY_CAPS[packKind];
        }
        return packKind === 'magic' ? DAILY_MAGIC_PACK_CAP : DAILY_SHOP_PACK_CAP;
    }

    function claimDailyShopPacks(packKind, amount) {
        amount = Math.max(1, clampInt(amount, 1));
        if (packKind === 'standard') { return true; }
        var purchases = syncDailyShopPackPurchases();
        if (dailyShopPackRemaining(packKind) < amount) { return false; }
        purchases[packKind] = Math.min(dailyShopPackCap(packKind), clampInt(purchases[packKind], 0) + amount);
        state.shopState.shopPackPurchases = purchases;
        saveShopState();
        return true;
    }

    function renderDailyCounter() {
        if (!els.dailyPacksCounter) { return; }
        if (state.isAdmin) {
            els.dailyPacksCounter.textContent = uiText('shop.admin', 'Admin');
            return;
        }
        var remaining = dailyFreePacksRemaining();
        els.dailyPacksCounter.textContent = String(remaining) + ' / ' + DAILY_FREE_PACK_CAP;
        els.dailyPacksCounter.title = remaining > 0
            ? 'Sobres gratis pendientes de reclamar hoy en tienda.'
            : 'Cupo diario de sobres gratis agotado.';
    }

    function buildShopGroup(title, extraClass) {
        var section = document.createElement('section');
        section.className = 'hg-shop-group';
        var heading = document.createElement('h4');
        heading.textContent = title;
        var grid = document.createElement('div');
        grid.className = 'hg-shop-group__grid' + (extraClass ? ' ' + extraClass : '');
        section.appendChild(heading);
        section.appendChild(grid);
        return { section: section, grid: grid };
    }

    function createShopItemBase() {
        var item = document.createElement('article');
        item.className = 'hg-shop-item';
        var name = document.createElement('span');
        var price = document.createElement('strong');
        item.appendChild(name);
        item.appendChild(price);
        return item;
    }

    function createPackButton(kind, mobile) {
        var skin = packSkin(kind);
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'hg-pack hg-pack--' + kind;
        button.setAttribute('data-pack-kind', kind);

        var seal = document.createElement('span');
        seal.className = 'hg-pack__seal';
        seal.textContent = skin.seal || kind.slice(0, 2).toUpperCase();

        var title = document.createElement('span');
        title.className = 'hg-pack__title';
        title.textContent = packLabel(kind);

        var count = document.createElement('span');
        count.className = 'hg-pack__count';
        count.textContent = skin.summary || packContents(kind);

        var stock = document.createElement('span');
        stock.className = 'hg-pack__stock';
        stock.setAttribute('data-pack-stock', kind);
        stock.textContent = 'x0';

        button.appendChild(seal);
        button.appendChild(title);
        button.appendChild(count);
        button.appendChild(stock);
        applyPackSkin(button, kind);
        if (mobile) {
            button.classList.add('hg-pack--mobile-ready');
        }
        return button;
    }

    function ensurePackLayout() {
        if (!els.packGrid) { return; }
        var mobile = els.packGrid.classList.contains('hg-pack-grid--mobile');
        var existingButtons = Array.prototype.slice.call(els.packGrid.querySelectorAll('[data-pack-kind]'));
        var known = {};
        existingButtons.forEach(function (button) {
            known[String(button.getAttribute('data-pack-kind') || '')] = button;
        });
        PACK_KINDS.forEach(function (kind) {
            if (known[kind]) { return; }
            var openAll = els.packGrid.querySelector('[data-pack-open-all]');
            var button = createPackButton(kind, mobile);
            if (openAll) {
                els.packGrid.insertBefore(button, openAll);
            } else {
                els.packGrid.appendChild(button);
            }
        });
        if (!els.packGrid.querySelector('[data-pack-open-all]')) {
            var openAllButton = document.createElement('button');
            openAllButton.type = 'button';
            openAllButton.className = 'hg-pack-open-all';
            openAllButton.setAttribute('data-pack-open-all', '1');
            openAllButton.textContent = 'Abrir todos';
            els.packGrid.appendChild(openAllButton);
        }
        Array.prototype.slice.call(els.packGrid.querySelectorAll('[data-pack-kind]')).forEach(function (button) {
            var kind = String(button.getAttribute('data-pack-kind') || '');
            if (PACK_KINDS.indexOf(kind) === -1) {
                button.remove();
            }
        });
        els.packButtons = Array.prototype.slice.call(document.querySelectorAll('[data-pack-kind]'));
        els.packStocks = Array.prototype.slice.call(document.querySelectorAll('[data-pack-stock]'));
        els.packOpenAll = document.querySelector('[data-pack-open-all]');
    }

    function ensureDynamicShopItems(container) {
        if (!container) { return; }
        var products = Array.isArray(state.shopProducts) ? state.shopProducts : [];
        var groups = container.querySelector('.hg-shop-groups');
        var flatGrid = container.querySelector('.hg-shop-grid');
        var groupSections = groups ? Array.prototype.slice.call(groups.querySelectorAll('.hg-shop-group')) : [];
        var freeTarget = groups ? groups.querySelector('[data-shop-group="free"] .hg-shop-group__grid') : flatGrid;
        var packsTarget = groups ? groups.querySelector('[data-shop-group="packs"] .hg-shop-group__grid') : flatGrid;
        var materialsTarget = groups ? groups.querySelector('[data-shop-group="materials"] .hg-shop-group__grid') : flatGrid;
        var exchangeTarget = groups ? groups.querySelector('[data-shop-group="exchange"] .hg-shop-group__grid') : flatGrid;
        if (!freeTarget && groupSections[0]) { freeTarget = groupSections[0].querySelector('.hg-shop-group__grid'); }
        if (!packsTarget && groupSections[1]) { packsTarget = groupSections[1].querySelector('.hg-shop-group__grid'); }
        if (!materialsTarget && groupSections[2]) { materialsTarget = groupSections[2].querySelector('.hg-shop-group__grid'); }
        if (!exchangeTarget && groupSections[3]) { exchangeTarget = groupSections[3].querySelector('.hg-shop-group__grid'); }
        var scanRoot = groups || flatGrid || container;
        if (!scanRoot) { return; }
        var hasDailyGift = false;
        var packKeys = {};
        var materialKeys = {};
        var exchangeKeys = {};
        Array.prototype.slice.call(scanRoot.querySelectorAll('.hg-shop-item')).forEach(function (item) {
            if (item.hasAttribute('data-shop-daily-gift')) { hasDailyGift = true; }
            var packKey = (item.getAttribute('data-shop-pack') || '') + '|' + (item.getAttribute('data-shop-free') === '1' ? 'free' : 'paid');
            if (item.hasAttribute('data-shop-pack')) { packKeys[packKey] = true; }
            var materialKey = item.getAttribute('data-shop-material') || '';
            if (materialKey) { materialKeys[materialKey] = true; }
            var exchangeRemorias = item.getAttribute('data-shop-exchange-remorias') || '';
            if (exchangeRemorias) { exchangeKeys[exchangeRemorias] = true; }
        });

        var freePackKey = 'standard|free';
        if (!packKeys[freePackKey] && freeTarget) {
            var freePackItem = createShopItemBase();
            freePackItem.setAttribute('data-shop-pack', 'standard');
            freePackItem.setAttribute('data-shop-free', '1');
            packKeys[freePackKey] = true;
            freeTarget.appendChild(freePackItem);
        }

        var paidPackKinds = ['standard'].concat(Array.isArray(SHOP_PACK_KINDS) ? SHOP_PACK_KINDS : []);
        paidPackKinds.forEach(function (kind) {
            var normalizedKind = String(kind || '').trim();
            var packKey = normalizedKind + '|paid';
            if (!normalizedKind || packKeys[packKey] || PACK_KINDS.indexOf(normalizedKind) === -1) { return; }
            var packItem = createShopItemBase();
            packItem.setAttribute('data-shop-pack', normalizedKind);
            packKeys[packKey] = true;
            if (packsTarget) { packsTarget.appendChild(packItem); }
        });

        products.forEach(function (product) {
            if (!product || typeof product !== 'object') { return; }
            var item = null;
            if (product.product_type === 'daily_gift') {
                if (hasDailyGift) { return; }
                item = createShopItemBase();
                item.setAttribute('data-shop-daily-gift', '1');
                hasDailyGift = true;
                if (freeTarget) { freeTarget.appendChild(item); }
            } else if (product.product_type === 'material') {
                var materialKey = String(product.material_key || '');
                if (!materialKey || materialKeys[materialKey]) { return; }
                item = createShopItemBase();
                item.setAttribute('data-shop-material', materialKey);
                materialKeys[materialKey] = true;
                if (materialsTarget) { materialsTarget.appendChild(item); }
            } else if (product.product_type === 'exchange_remorias') {
                var remoriasAmount = String(clampInt(product.remorias_amount, 0));
                if (!remoriasAmount || remoriasAmount === '0' || exchangeKeys[remoriasAmount]) { return; }
                item = createShopItemBase();
                item.setAttribute('data-shop-exchange-remorias', remoriasAmount);
                exchangeKeys[remoriasAmount] = true;
                if (exchangeTarget) { exchangeTarget.appendChild(item); }
            }
            if (item && !item.parentNode && flatGrid) { flatGrid.appendChild(item); }
        });
    }

    function ensureShopLayout() {
        Array.prototype.slice.call(document.querySelectorAll('.hg-shop-section, .hg-mobile-panel[data-mobile-panel="shop"]')).forEach(function (container) {
            ensureDynamicShopItems(container);
            var groups = container.querySelector('.hg-shop-groups');
            if (groups) { return; }
            var flatGrid = container.querySelector('.hg-shop-grid');
            if (!flatGrid) { return; }
            var items = Array.prototype.slice.call(flatGrid.querySelectorAll('.hg-shop-item'));
            var groupsWrap = document.createElement('div');
            groupsWrap.className = 'hg-shop-groups';
            var freeGroup = buildShopGroup(uiText('shop.free_group', 'Gratis hoy'), flatGrid.classList.contains('hg-shop-grid--mobile') ? 'hg-shop-grid--mobile' : '');
            freeGroup.section.setAttribute('data-shop-group', 'free');
            var packsGroup = buildShopGroup('Sobres', flatGrid.classList.contains('hg-shop-grid--mobile') ? 'hg-shop-grid--mobile' : '');
            packsGroup.section.setAttribute('data-shop-group', 'packs');
            var materialsGroup = buildShopGroup('Objetos rituales', flatGrid.classList.contains('hg-shop-grid--mobile') ? 'hg-shop-grid--mobile' : '');
            materialsGroup.section.setAttribute('data-shop-group', 'materials');
            var exchangeGroup = buildShopGroup('Servicio de canje', (flatGrid.classList.contains('hg-shop-grid--mobile') ? 'hg-shop-grid--mobile ' : '') + 'hg-shop-group__grid--exchange');
            exchangeGroup.section.setAttribute('data-shop-group', 'exchange');

            if (!items.some(function (item) { return item.hasAttribute('data-shop-daily-gift'); })) {
                var dailyGift = document.createElement('article');
                dailyGift.className = 'hg-shop-item';
                dailyGift.setAttribute('data-shop-daily-gift', '1');
                dailyGift.innerHTML = '<span>' + escapeHtml(uiText('shop.daily_gift_name', 'Regalo diario')) + '</span><strong>' + escapeHtml(uiText('shop.daily_gift_price_available', 'Gratis - 1 al día')) + '</strong>';
                items.unshift(dailyGift);
            }

            items.forEach(function (item) {
                if (item.hasAttribute('data-shop-daily-gift') || item.getAttribute('data-shop-free') === '1') {
                    freeGroup.grid.appendChild(item);
                    return;
                }
                if (item.hasAttribute('data-shop-material')) {
                    materialsGroup.grid.appendChild(item);
                    return;
                }
                if (item.hasAttribute('data-shop-exchange-remorias')) {
                    exchangeGroup.grid.appendChild(item);
                    return;
                }
                packsGroup.grid.appendChild(item);
            });

            groupsWrap.appendChild(freeGroup.section);
            groupsWrap.appendChild(packsGroup.section);
            groupsWrap.appendChild(materialsGroup.section);
            groupsWrap.appendChild(exchangeGroup.section);
            flatGrid.replaceWith(groupsWrap);
        });
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
        if (!currency || typeof currency !== 'object') {
            return { mnemones: STARTING_MNEMONES, remorias: STARTING_REMORIAS };
        }
        return {
            mnemones: typeof currency.mnemones === 'undefined'
                ? STARTING_MNEMONES
                : Math.max(0, Math.min(MAX_MNEMONES, clampInt(currency && currency.mnemones, 0))),
            remorias: Math.max(0, Math.min(MAX_REMORIAS, clampInt(currency && currency.remorias, STARTING_REMORIAS)))
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

    function currentRemorias() {
        if (!state.collection) { loadCollection(); }
        state.collection.currency = normalizeCurrency(state.collection.currency);
        return state.collection.currency.remorias;
    }

    function addRemorias(amount) {
        if (!state.collection) { loadCollection(); }
        state.collection.currency = normalizeCurrency(state.collection.currency);
        state.collection.currency.remorias = Math.max(0, Math.min(MAX_REMORIAS, state.collection.currency.remorias + clampInt(amount, 0)));
        return state.collection.currency.remorias;
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
                '<span><strong>' + entries.length + ' / ' + WORK_MAX_ASSIGNMENTS + '</strong><small>' + escapeHtml(uiText('memory.summary_active', 'rememorando')) + '</small></span>',
                '<span><strong>' + totalRate.toFixed(1) + '</strong><small>' + escapeHtml(uiText('memory.summary_rate', 'Mn/min')) + '</small></span>',
                '<span><strong>' + claimable + '</strong><small>' + escapeHtml(uiText('memory.summary_claimable', 'reclamables')) + '</small></span>'
            ].join('');
        }
        if (els.workClaimBtn) {
            els.workClaimBtn.disabled = claimable <= 0;
            els.workClaimBtn.textContent = claimable > 0
                ? uiText('memory.claim_button_amount', 'Reclamar +{amount}', { amount: claimable })
                : uiText('memory.claim_button', 'Reclamar');
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
                        '<strong>' + escapeHtml(uiText('memory.slot_label', 'Hueco {slot}', { slot: slotIndex + 1 })) + '</strong>' +
                        '<span>' + escapeHtml(uiText('memory.empty_slot_text', 'Elige una carta para recordar.')) + '</span>' +
                    '</div>';
                if (candidates.length) {
                    var select = document.createElement('select');
                    select.setAttribute('aria-label', uiText('memory.select_label', 'Carta para recordar en hueco {slot}', { slot: slotIndex + 1 }));
                    candidates.forEach(function (candidate) {
                        var option = document.createElement('option');
                        option.value = String(candidate.copy.instanceId || '');
                        option.textContent = candidate.card.card_name + ' · ' + candidate.rate.toFixed(1) + ' Mn/min';
                        select.appendChild(option);
                    });
                    var add = document.createElement('button');
                    add.type = 'button';
                    add.className = 'hg-icon-action hg-icon-action--memory';
                    add.title = uiText('memory.remember_action', 'Recordar');
                    add.setAttribute('aria-label', uiText('memory.remember_selected_label', 'Recordar carta seleccionada'));
                    add.innerHTML = cardGameIconHtml('remembrance', uiText('memory.remember_action', 'Recordar'));
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
                    none.textContent = uiText('memory.no_cards', 'No hay cartas disponibles.');
                    slot.appendChild(none);
                }
                els.workList.appendChild(slot);
                continue;
            }
            var canStop = workCanStop(entry);
            var cardWrap = document.createElement('div');
            cardWrap.className = 'hg-work-slot__card';
            var memoryCard = renderCard(entry.baseCard, entry.copy, { noLink: true, memoryCompact: true });
            memoryCard.className += ' hg-card--memory';
            cardWrap.appendChild(memoryCard);
            var effects = document.createElement('div');
            effects.className = 'hg-work-slot__effects';
            effects.innerHTML =
                '<b>' + entry.rate.toFixed(1) + ' Mnemones/min</b>' +
                '<span>' + escapeHtml(uiText('memory.gains', 'Ganancias: +{amount}', { amount: entry.claimable })) + '</span>' +
                '<small>' + escapeHtml(canStop ? uiText('memory.can_return', 'Puede volver') : uiText('memory.returns_in', 'Vuelve en {time}', { time: workRemainingLabel(entry) })) + '</small>';
            var stop = document.createElement('button');
            stop.type = 'button';
            stop.textContent = uiText('memory.stop', 'Retirar');
            stop.disabled = !canStop;
            stop.title = canStop ? '' : uiText('memory.min_duration_title', 'Debe rememorar al menos 24 horas.');
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
            setStatus(uiText('status.no_memory_claim', 'Todavía no hay Mnemones de rememoración para reclamar.'));
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
        setStatus(uiText('status.memory_claimed', 'Rememoración reclamada. +{amount} Mnemones.', { amount: claimable }));
        return true;
    }

    function assignCopyToWork(card, copy, options) {
        options = options || {};
        if (!copy || !copy.instanceId) { return false; }
        if (isCopyWorking(copy.instanceId)) {
            setStatus(uiText('status.card_already_remembering', 'Esta carta ya está rememorando.'));
            return false;
        }
        if (activeWorkEntries().length >= WORK_MAX_ASSIGNMENTS) {
            setStatus(uiText('status.memory_limit', 'Sólo puedes tener {max} cartas rememorando a la vez.', { max: WORK_MAX_ASSIGNMENTS }));
            return false;
        }
        if (isCopyInCombatTeam(copy.instanceId)) {
            setStatus(uiText('status.memory_card_in_team', 'Quita la carta del equipo antes de ponerla a recordar.'));
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
        setStatus(uiText('status.memory_started', 'Carta puesta a recordar: +{rate} Mnemones/min.', { rate: workRatePerMinute(copy, card).toFixed(1) }));
        return true;
    }

    function assignCopyToWorkById(instanceId) {
        var copy = copyByInstanceId(instanceId);
        var card = copy ? state.catalogById[String(copy.cardId || '')] : null;
        if (!copy || !card) {
            setStatus(uiText('status.memory_card_missing', 'No se encontró esa carta para recordar.'));
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
            setStatus(uiText('status.memory_min_duration', 'Esta carta debe rememorar al menos 24 horas. Quedan {remaining}.', { remaining: workRemainingLabel(entry) }));
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
        setStatus(uiText('status.memory_stopped', 'Carta retirada de la rememoración. Sus ganancias quedan pendientes en Reclamar.'));
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
        ensurePackLayout();
        var totalStock = totalPackStock();
        els.packStocks.forEach(function (node) {
            var kind = node.getAttribute('data-pack-stock') || 'standard';
            var stock = packStock(kind);
            if (state.isAdmin) {
                node.textContent = uiText('shop.admin', 'Admin');
            } else if (kind === 'standard') {
                node.textContent = 'x' + stock;
                node.title = uiText('packs.available_title', 'Sobres mnemónicos disponibles.');
            } else {
                node.textContent = 'x' + stock;
            }
        });
        els.packButtons.forEach(function (button) {
            var kind = button.getAttribute('data-pack-kind') || 'standard';
            applyPackSkin(button, kind);
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
            empty.textContent = uiText('packs.empty', 'No te quedan sobres. Puedes comprar más en la tienda o probar suerte en las Mazmorras.');
            els.packGrid.appendChild(empty);
        }
        empty.hidden = state.isAdmin || totalStock > 0;
        if (els.packOpenAll) {
            els.packOpenAll.hidden = state.isAdmin || totalStock <= 0;
            els.packOpenAll.disabled = state.isAdmin || totalStock <= 0;
        }
    }

    function renderFreePackResetMeter(item, freeRemaining) {
        var meter = item.querySelector('[data-free-pack-reset]');
        if (!meter) {
            meter = document.createElement('span');
            meter.className = 'hg-shop-free-reset';
            meter.setAttribute('data-free-pack-reset', '1');
            meter.innerHTML =
                '<span class="hg-shop-free-reset__text"></span>' +
                '<span class="hg-shop-free-reset__bar" aria-hidden="true"><span></span></span>';
            item.appendChild(meter);
        }
        var text = meter.querySelector('.hg-shop-free-reset__text');
        var fill = meter.querySelector('.hg-shop-free-reset__bar span');
        if (state.isAdmin) {
            if (text) { text.textContent = uiText('packs.admin_daily_free', 'Cupo diario libre en modo admin.'); }
            if (fill) { fill.style.width = '100%'; }
            return;
        }
        var resetText = formatResetDuration(msUntilDailyFreeReset());
        if (text) {
            text.textContent = freeRemaining > 0
                ? 'Reinicio diario en ' + resetText
                : 'Nuevos sobres en ' + resetText;
        }
        if (fill) {
            fill.style.width = dailyFreeResetProgress().toFixed(2) + '%';
        }
    }

    function packAvailableInShop(packKind, isFree) {
        return isFree || SHOP_PACK_KINDS.indexOf(packKind) !== -1;
    }

    function shopQuantitiesForPack(packKind, isFree) {
        if (isFree) { return FREE_SHOP_QUANTITIES; }
        if (packKind === 'standard') { return SHOP_QUANTITIES; }
        return SHOP_QUANTITIES.filter(function (amount) { return amount <= dailyShopPackCap(packKind); });
    }

    function renderShop() {
        ensureShopLayout();
        els.shopItems = Array.prototype.slice.call(document.querySelectorAll('[data-shop-pack], [data-shop-material], [data-shop-exchange-remorias], [data-shop-daily-gift]'));
        var money = currentMnemones();
        var remorias = currentRemorias();
        els.mnemonesCounters.forEach(function (node) {
            node.textContent = formatNumber(money);
        });
        els.remoriasCounters.forEach(function (node) {
            node.textContent = formatNumber(remorias);
        });
        els.shopItems.forEach(function (item) {
            if (item.hasAttribute('data-shop-daily-gift')) {
                renderDailyGiftShopItem(item);
                return;
            }
            var materialKey = item.getAttribute('data-shop-material') || '';
            if (materialKey) {
                renderMaterialShopItem(item, materialKey, money);
                return;
            }
            var exchangeRemorias = clampInt(item.getAttribute('data-shop-exchange-remorias'), 0);
            if (exchangeRemorias > 0) {
                renderExchangeShopItem(item, exchangeRemorias, money);
                return;
            }
            var kind = item.getAttribute('data-shop-pack') || 'standard';
            var isFree = item.getAttribute('data-shop-free') === '1';
            if (!packAvailableInShop(kind, isFree)) {
                item.hidden = true;
                return;
            }
            item.hidden = false;
            var price = isFree ? 0 : packPrice(kind);
            var freeRemaining = isFree ? dailyFreePacksRemaining() : 0;
            var dailyRemaining = !isFree ? dailyShopPackRemaining(kind) : Infinity;
            item.setAttribute('data-shop-daily-limit', kind === 'standard' || isFree ? '0' : String(dailyShopPackCap(kind)));
            item.setAttribute('data-shop-daily-remaining', kind === 'standard' || isFree ? '' : String(dailyRemaining));
            var priceNode = item.querySelector('strong');
            if (priceNode) {
                if (isFree) {
                    priceNode.textContent = state.isAdmin
                        ? uiText('shop.admin', 'Admin')
                        : (freeRemaining > 0 ? uiText('shop.free_available', 'Gratis - quedan {remaining}', { remaining: freeRemaining }) : uiText('shop.free_sold_out', 'Agotado hoy'));
                } else {
                    priceNode.innerHTML = '<span class="hg-shop-item__price">' + escapeHtml(uiText('shop.pack_price_stock', '{price} Mnemones', { price: formatNumber(price) })) + '</span>' +
                        (kind !== 'standard' ? '<span class="hg-shop-item__stock">' + escapeHtml(uiText('shop.pack_stock', 'quedan {remaining}', { remaining: dailyRemaining })) + '</span>' : '');
                }
            }
            var description = item.querySelector('.hg-shop-item__contents');
            if (!description) {
                description = document.createElement('span');
                description.className = 'hg-shop-item__contents';
                item.appendChild(description);
            }
            description.textContent = isFree ? uiText('shop.free_pack_description', 'Reclama hasta {cap} sobres mnemónicos gratis al día.', { cap: DAILY_FREE_PACK_CAP }) : packContents(kind);
            item.title = isFree
                ? uiText('shop.free_pack_title', 'Sobres mnemónicos gratis. Quedan {remaining} hoy.', { remaining: state.isAdmin ? uiText('shop.admin', 'Admin') : freeRemaining })
                : uiText('shop.pack_title', '{pack}: {description} Precio: {price} Mnemones.', { pack: packLabel(kind), description: packContents(kind), price: formatNumber(price) });
            var controls = item.querySelector('.hg-shop-item__actions');
            if (!controls) {
                controls = document.createElement('span');
                controls.className = 'hg-shop-item__actions';
                item.appendChild(controls);
            }
            controls.innerHTML = '';
            if (isFree) {
                renderFreePackResetMeter(item, freeRemaining);
            }
            shopQuantitiesForPack(kind, isFree).forEach(function (amount) {
                var buy = document.createElement('button');
                buy.type = 'button';
                buy.className = 'hg-shop-buy';
                buy.setAttribute('data-shop-buy-pack', kind);
                buy.setAttribute('data-shop-buy-amount', String(amount));
                buy.setAttribute('data-shop-daily-limit', kind === 'standard' || isFree ? '0' : String(dailyShopPackCap(kind)));
                buy.setAttribute('data-shop-daily-remaining', kind === 'standard' || isFree ? '' : String(dailyRemaining));
                if (isFree) { buy.setAttribute('data-shop-buy-free', '1'); }
                buy.textContent = 'x' + amount;
                buy.disabled = isFree
                    ? (!state.isAdmin && (freeRemaining < amount || packSpace(kind) < amount))
                    : ((dailyRemaining < amount) || (!state.isAdmin && (money < price * amount || packSpace(kind) < amount)));
                buy.title = isFree
                    ? uiText('shop.free_pack_buy_title', 'Reclamar {amount} sobre(s) mnemónicos gratis', { amount: amount })
                    : uiText('shop.pack_buy_title', 'Comprar {amount} por {price} Mnemones{remainingText}', {
                        amount: amount,
                        price: formatNumber(price * amount),
                        remainingText: kind !== 'standard' ? '. Quedan ' + dailyRemaining + ' hoy.' : ''
                    });
                controls.appendChild(buy);
            });
            item.classList.toggle('is-empty', isFree ? (!state.isAdmin && (freeRemaining <= 0 || packSpace(kind) <= 0)) : (dailyRemaining <= 0 || (!state.isAdmin && (money < price || packSpace(kind) <= 0))));
        });
        els.shopButtons = Array.prototype.slice.call(document.querySelectorAll('[data-shop-buy-pack]'));
    }

    function renderDailyGiftShopItem(item) {
        var giftState = dailyGiftState();
        var materialKey = giftState.key;
        var material = UPGRADE_MATERIALS[materialKey];
        if (!material) {
            item.hidden = true;
            return;
        }
        item.hidden = false;
        item.classList.add('hg-shop-item--gift');
        var remaining = dailyGiftRemaining();
        var nameNode = item.querySelector('span');
        if (nameNode && !nameNode.classList.contains('hg-shop-item__contents')) {
            nameNode.innerHTML = materialIconHtml(materialKey) + '<span>Regalo diario: ' + escapeHtml(material.label) + '</span>';
        }
        var priceNode = item.querySelector('strong');
        if (priceNode) {
            priceNode.textContent = remaining > 0 ? uiText('shop.daily_gift_price_available', 'Gratis - 1 al día') : uiText('shop.daily_gift_price_claimed', 'Reclamado hoy');
        }
        var description = item.querySelector('.hg-shop-item__contents');
        if (!description) {
            description = document.createElement('span');
            description.className = 'hg-shop-item__contents';
            item.appendChild(description);
        }
        description.textContent = uiText('shop.daily_gift_description', 'Cada día sale al azar: {materials}.', { materials: DAILY_GIFT_MATERIAL_KEYS.map(function (key) {
            return UPGRADE_MATERIALS[key] && UPGRADE_MATERIALS[key].label ? UPGRADE_MATERIALS[key].label : key;
        }).join(' o ') });
        var controls = item.querySelector('.hg-shop-item__actions');
        if (!controls) {
            controls = document.createElement('span');
            controls.className = 'hg-shop-item__actions';
            item.appendChild(controls);
        }
        controls.innerHTML = '';
        var claim = document.createElement('button');
        claim.type = 'button';
        claim.className = 'hg-shop-buy';
        claim.setAttribute('data-shop-claim-daily-gift', materialKey);
        claim.textContent = uiText('shop.claim', 'Reclamar');
        claim.disabled = !state.isAdmin && remaining <= 0;
        claim.title = remaining > 0 ? 'Reclamar regalo diario' : 'Ya reclamado hoy';
        controls.appendChild(claim);
        item.classList.toggle('is-empty', !state.isAdmin && remaining <= 0);
    }

    function materialIconHtml(materialKey) {
        var material = UPGRADE_MATERIALS[materialKey];
        var icon = material ? RARITY_ICONS[material.rarity] : '';
        return icon ? '<img src="' + escapeHtml(icon) + '" alt="" width="24" height="24">' : '';
    }

    function renderMaterialShopItem(item, materialKey, mnemones) {
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
            priceNode.textContent = uiText('shop.pack_price_stock', '{price} Mnemones', { price: formatNumber(material.price) });
        }
        var description = item.querySelector('.hg-shop-item__contents');
        if (!description) {
            description = document.createElement('span');
            description.className = 'hg-shop-item__contents';
            item.appendChild(description);
        }
        description.textContent = uiText('shop.material_have', '{description} Tienes: {stock}.', {
            description: material.description,
            stock: state.isAdmin ? uiText('shop.admin', 'Admin') : materialStock(materialKey)
        });
        item.title = uiText('shop.material_title', '{material}: {description} Precio: {price} Mnemones.', {
            material: material.label,
            description: material.description,
            price: formatNumber(material.price)
        });
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
            buy.disabled = !state.isAdmin && mnemones < material.price * amount;
            buy.title = uiText('shop.material_buy_title', 'Comprar {amount} por {price} Mnemones', { amount: amount, price: formatNumber(material.price * amount) });
            controls.appendChild(buy);
        });
        item.classList.toggle('is-empty', !state.isAdmin && mnemones < material.price);
    }

    function renderExchangeShopItem(item, remoriasAmount, mnemones) {
        var totalPrice = remoriasAmount * 10;
        item.hidden = false;
        item.classList.add('hg-shop-item--exchange');
        var nameNode = item.querySelector('span');
        if (nameNode && !nameNode.classList.contains('hg-shop-item__contents')) {
            nameNode.textContent = uiText('shop.exchange_name', 'Cambio por {remorias} Remorias', { remorias: formatNumber(remoriasAmount) });
        }
        var priceNode = item.querySelector('strong');
        if (priceNode) {
            priceNode.textContent = uiText('shop.pack_price_stock', '{price} Mnemones', { price: formatNumber(totalPrice) });
        }
        var description = item.querySelector('.hg-shop-item__contents');
        if (!description) {
            description = document.createElement('span');
            description.className = 'hg-shop-item__contents';
            item.appendChild(description);
        }
        description.textContent = uiText('shop.exchange_rate', 'Tasa fija: 10 Mnemones = 1 Remoria.');
        item.title = uiText('shop.exchange_title', 'Cambiar {mnemones} Mnemones por {remorias} Remorias.', { mnemones: formatNumber(totalPrice), remorias: formatNumber(remoriasAmount) });
        var controls = item.querySelector('.hg-shop-item__actions');
        if (!controls) {
            controls = document.createElement('span');
            controls.className = 'hg-shop-item__actions';
            item.appendChild(controls);
        }
        controls.innerHTML = '';
        var buy = document.createElement('button');
        buy.type = 'button';
        buy.className = 'hg-shop-buy';
        buy.setAttribute('data-shop-buy-exchange-remorias', String(remoriasAmount));
        buy.textContent = uiText('shop.exchange_button', 'Cambiar');
        buy.disabled = !state.isAdmin && mnemones < totalPrice;
        buy.title = uiText('shop.exchange_title', 'Cambiar {mnemones} Mnemones por {remorias} Remorias.', { mnemones: formatNumber(totalPrice), remorias: formatNumber(remoriasAmount) });
        controls.appendChild(buy);
        item.classList.toggle('is-empty', !state.isAdmin && mnemones < totalPrice);
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

    function normalizeMoveId(value, fallback) {
        var id = String(value || '').trim();
        return id || fallback;
    }

    function cloneMoveDefinition(move) {
        if (!move || typeof move !== 'object') { return null; }
        var accuracy = Number(move.accuracy);
        if (!Number.isFinite(accuracy)) { accuracy = 1; }
        return {
            id: String(move.id || ''),
            label: String(move.label || ''),
            icon: String(move.icon || ''),
            type: String(move.type || 'damage'),
            power: Number(move.power),
            formula: String(move.formula || ''),
            accuracy: Math.max(0, Math.min(1, accuracy)),
            cooldown: Math.max(0, clampInt(move.cooldown, 0)),
            target: String(move.target || 'enemy'),
            effect: move.effect && typeof move.effect === 'object' ? {
                kind: String(move.effect.kind || ''),
                amount: Number(move.effect.amount),
                chance: Number(move.effect.chance),
                ratio: Number(move.effect.ratio),
                minRatio: Number(move.effect.minRatio),
                maxRatio: Number(move.effect.maxRatio)
            } : null,
            description: String(move.description || '')
        };
    }

    function normalizeCardMoves(card) {
        var source = Array.isArray(card && card.moves) ? card.moves : [];
        var moves = [];
        source.forEach(function (entry, index) {
            var move = null;
            if (typeof entry === 'string') {
                move = cloneMoveDefinition(MOVE_LIBRARY[entry]);
            } else if (entry && typeof entry === 'object') {
                var moveId = normalizeMoveId(entry.id || entry.move_key, 'move_' + (index + 1));
                if (MOVE_LIBRARY[moveId]) {
                    move = cloneMoveDefinition(MOVE_LIBRARY[moveId]);
                    Object.keys(entry).forEach(function (key) {
                        if (key === 'effect' && entry.effect && typeof entry.effect === 'object') {
                            move.effect = move.effect || {};
                            Object.keys(entry.effect).forEach(function (effectKey) {
                                move.effect[effectKey] = entry.effect[effectKey];
                            });
                        } else {
                            move[key] = entry[key];
                        }
                    });
                } else {
                    move = cloneMoveDefinition(entry);
                }
                if (move) { move.id = moveId; }
            }
            if (!move || !move.id || !move.label) { return; }
            if (moves.some(function (existing) { return existing.id === move.id; })) { return; }
            moves.push(cloneMoveDefinition(move));
        });
        return moves.slice(0, 3);
    }

    function normalizeCopyMoveIds(value) {
        if (!Array.isArray(value)) { return []; }
        var seen = {};
        return value.map(function (entry) {
            return normalizeMoveId(entry, '');
        }).filter(function (id) {
            if (!id || !MOVE_LIBRARY[id] || seen[id]) { return false; }
            seen[id] = true;
            return true;
        }).slice(0, 3);
    }

    function initialMoveIdsForCopy(card, rarity) {
        var rules = MOVE_LEARN_RULES[String(rarity || '')] || MOVE_LEARN_RULES.common;
        var libraryIds = Object.keys(MOVE_LIBRARY);
        if (!libraryIds.length || !rules || rules.count <= 0 || Math.random() > Math.max(0, Math.min(1, Number(rules.chance) || 0))) {
            return [];
        }
        var start = Math.abs(clampInt(card && card.card_id, 1) - 1) % libraryIds.length;
        var pool = libraryIds.map(function (_, index) {
            return libraryIds[(start + index) % libraryIds.length];
        });
        for (var i = pool.length - 1; i > 0; i--) {
            var swapIndex = Math.floor(Math.random() * (i + 1));
            var temp = pool[i];
            pool[i] = pool[swapIndex];
            pool[swapIndex] = temp;
        }
        return pool.slice(0, Math.max(0, clampInt(rules.count, 0)));
    }

    function highestMoveCheckpoint(value) {
        var rarity = normalizeRarity(value, 'common');
        return rarityRank(rarity) >= 0 ? rarity : 'common';
    }

    function addMoveIdsToCopy(copy, moveIds) {
        if (!copy) { return 0; }
        var current = normalizeCopyMoveIds(copy.moves);
        var added = 0;
        normalizeCopyMoveIds(moveIds).forEach(function (moveId) {
            if (current.indexOf(moveId) !== -1 || current.length >= 3) { return; }
            current.push(moveId);
            added++;
        });
        copy.moves = current.slice(0, 3);
        return added;
    }

    function ensureCopyMovesForRarity(copy, card, targetRarity, force) {
        if (!copy || !card) { return 0; }
        var rarity = normalizeRarity(targetRarity || copy.rarity, card.card_rarity);
        var checkpoint = highestMoveCheckpoint(copy.moveRollRarity || copy.movesRarityCheckpoint || 'common');
        if (!force && rarityRank(rarity) <= rarityRank(checkpoint)) {
            copy.moves = normalizeCopyMoveIds(copy.moves);
            copy.moveRollRarity = checkpoint;
            return 0;
        }
        var added = addMoveIdsToCopy(copy, initialMoveIdsForCopy(card, rarity));
        copy.moveRollRarity = rarity;
        return added;
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
            def_max: defMax,
            moves: normalizeCardMoves(card)
        };
    }

    function loadCatalog() {
        var url = root.getAttribute('data-catalog-url') || '/api/game_cards.php';
        setStatus(uiText('status.loading_catalog', 'Cargando catálogo...'));
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
                if (state.collection && Array.isArray(state.collection.ownedCards)) {
                    state.collection = validateCollection(state.collection);
                    state.collection.currency = normalizeCurrency(state.collection.currency);
                    state.collection.packInventory = normalizePackInventory(state.collection.packInventory);
                    state.collection.dailyShopPackPurchases = normalizeDailyShopPackPurchases(state.collection.dailyShopPackPurchases);
                    state.collection.materialInventory = normalizeMaterialInventory(state.collection.materialInventory);
                    state.collection.workAssignments = normalizeWorkAssignments(state.collection.workAssignments);
                    state.collection.workPendingRewards = normalizeWorkPendingRewards(state.collection.workPendingRewards);
                    saveCollection();
                }
                recoverAbandonedDailyBossAttempt();
                migrateCollectionQuality();
                setStatus(state.catalog.length ? uiText('status.catalog_ready', 'Listo.') : uiText('status.catalog_empty', 'No hay cartas activas en el catálogo.'));
                renderSummary();
                renderCollectionTable();
                renderCombat();
                return state.catalog;
            })
            .catch(function (err) {
                state.catalog = [];
                state.catalogById = {};
                setStatus(err.message || uiText('status.catalog_load_error', 'No se pudo cargar el catálogo.'));
                renderSummary();
                renderCollectionTable();
                renderCombat();
                return [];
            });
    }

    function hasOwn(object, key) {
        return !!object && Object.prototype.hasOwnProperty.call(object, key);
    }

    function assignObjectValue(currentValue, nextValue) {
        return nextValue && typeof nextValue === 'object' && !Array.isArray(nextValue) ? nextValue : currentValue;
    }

    function assignArrayValue(currentValue, nextValue) {
        return Array.isArray(nextValue) ? nextValue : currentValue;
    }

    function normalizeRewardRangeConfig(config, fallbackMin, fallbackMax) {
        var min = clampInt(config && config.min, fallbackMin);
        var max = clampInt(config && config.max, fallbackMax);
        if (max < min) { max = min; }
        return { min: min, max: max };
    }

    function normalizeDropConfigList(list) {
        if (!Array.isArray(list)) { return []; }
        return list.map(function (entry) {
            var key = String(entry && entry.key || '');
            if (!key) { return null; }
            return {
                key: key,
                chance: Math.max(0, Math.min(1, Number(entry.chance) || 0)),
                amount: Math.max(1, clampInt(entry.amount, 1))
            };
        }).filter(Boolean);
    }

    function normalizeCombatDifficultyTable(table) {
        if (!table || typeof table !== 'object') { return null; }
        var normalized = {};
        Object.keys(table).forEach(function (key) {
            var entry = table[key];
            if (!entry || typeof entry !== 'object' || !entry.weights || typeof entry.weights !== 'object') { return; }
            normalized[String(key)] = {
                label: String(entry.label || key),
                weights: entry.weights
            };
        });
        return Object.keys(normalized).length ? normalized : null;
    }

    function normalizeCombatAdvancedRules(config) {
        if (!config || typeof config !== 'object') { return null; }
        var rarityShields = assignObjectValue(COMBAT_ADVANCED_RULES.rarityShields, config.rarity_shields);
        return {
            defendHealRatio: Math.max(0, Number(config.defend_heal_ratio) || COMBAT_ADVANCED_RULES.defendHealRatio),
            defendDefMultiplier: Math.max(1, Number(config.defend_def_multiplier) || COMBAT_ADVANCED_RULES.defendDefMultiplier),
            enemyDefendHpRatio: Math.max(0, Math.min(1, Number(config.enemy_defend_hp_ratio) || COMBAT_ADVANCED_RULES.enemyDefendHpRatio)),
            enemyDefendChance: Math.max(0, Math.min(1, Number(config.enemy_defend_chance) || COMBAT_ADVANCED_RULES.enemyDefendChance)),
            enemyPickAttempts: Math.max(1, clampInt(config.enemy_pick_attempts, COMBAT_ADVANCED_RULES.enemyPickAttempts)),
            damageRandomBonusMin: Math.max(0, clampInt(config.damage_random_bonus_min, COMBAT_ADVANCED_RULES.damageRandomBonusMin)),
            damageRandomBonusMax: Math.max(0, clampInt(config.damage_random_bonus_max, COMBAT_ADVANCED_RULES.damageRandomBonusMax)),
            rarityAdvantageStep: Math.max(0, Number(config.rarity_advantage_step) || COMBAT_ADVANCED_RULES.rarityAdvantageStep),
            rarityDisadvantageStep: Math.max(0, Number(config.rarity_disadvantage_step) || COMBAT_ADVANCED_RULES.rarityDisadvantageStep),
            rarityDisadvantageMinMultiplier: Math.max(0, Math.min(1, Number(config.rarity_disadvantage_min_multiplier) || COMBAT_ADVANCED_RULES.rarityDisadvantageMinMultiplier)),
            rarityShields: rarityShields
        };
    }

    function applyGameRulesSettings(settings, packs) {
        if (!settings || typeof settings !== 'object') { return; }
        if (hasOwn(settings, 'starting_mnemones')) { STARTING_MNEMONES = clampInt(settings.starting_mnemones, STARTING_MNEMONES); }
        if (hasOwn(settings, 'starting_remorias')) { STARTING_REMORIAS = clampInt(settings.starting_remorias, STARTING_REMORIAS); }
        if (hasOwn(settings, 'max_mnemones')) { MAX_MNEMONES = clampInt(settings.max_mnemones, MAX_MNEMONES); }
        if (hasOwn(settings, 'max_remorias')) { MAX_REMORIAS = clampInt(settings.max_remorias, MAX_REMORIAS); }
        if (hasOwn(settings, 'daily_free_pack_cap')) { DAILY_FREE_PACK_CAP = clampInt(settings.daily_free_pack_cap, DAILY_FREE_PACK_CAP); }
        if (hasOwn(settings, 'daily_shop_pack_cap')) { DAILY_SHOP_PACK_CAP = clampInt(settings.daily_shop_pack_cap, DAILY_SHOP_PACK_CAP); }
        if (hasOwn(settings, 'daily_magic_pack_cap')) { DAILY_MAGIC_PACK_CAP = clampInt(settings.daily_magic_pack_cap, DAILY_MAGIC_PACK_CAP); }
        if (Array.isArray(settings.shop_quantities)) { SHOP_QUANTITIES = settings.shop_quantities.map(function (value) { return clampInt(value, 1); }).filter(Boolean); }
        if (Array.isArray(settings.free_shop_quantities)) { FREE_SHOP_QUANTITIES = settings.free_shop_quantities.map(function (value) { return clampInt(value, 1); }).filter(Boolean); }
        if (hasOwn(settings, 'rarity_upgrade_required')) { RARITY_UPGRADE_REQUIRED = clampInt(settings.rarity_upgrade_required, RARITY_UPGRADE_REQUIRED); }
        if (hasOwn(settings, 'rarity_upgrade_min_quality')) { RARITY_UPGRADE_MIN_QUALITY = clampInt(settings.rarity_upgrade_min_quality, RARITY_UPGRADE_MIN_QUALITY); }
        if (Array.isArray(settings.rarity_upgrade_multipliers)) { RARITY_UPGRADE_MULTIPLIERS = settings.rarity_upgrade_multipliers.map(function (value) { return Number(value) || 0; }).filter(function (value) { return value > 0; }); }
        if (hasOwn(settings, 'quality_upgrade_max_slots')) { QUALITY_UPGRADE_MAX_SLOTS = clampInt(settings.quality_upgrade_max_slots, QUALITY_UPGRADE_MAX_SLOTS); }
        if (hasOwn(settings, 'skill_slot_count')) { SKILL_SLOT_COUNT = clampInt(settings.skill_slot_count, SKILL_SLOT_COUNT); }
        if (hasOwn(settings, 'skill_base_mnemones')) { SKILL_BASE_MNEMONES = clampInt(settings.skill_base_mnemones, SKILL_BASE_MNEMONES); }
        if (hasOwn(settings, 'skill_material_key')) { SKILL_MATERIAL_KEY = String(settings.skill_material_key || SKILL_MATERIAL_KEY); }
        if (hasOwn(settings, 'work_max_assignments')) { WORK_MAX_ASSIGNMENTS = clampInt(settings.work_max_assignments, WORK_MAX_ASSIGNMENTS); }
        if (hasOwn(settings, 'work_min_duration_ms')) { WORK_MIN_DURATION_MS = clampInt(settings.work_min_duration_ms, WORK_MIN_DURATION_MS); }
        if (hasOwn(settings, 'daily_boss_hp_multiplier_min')) { DAILY_BOSS_HP_MULTIPLIER_MIN = clampInt(settings.daily_boss_hp_multiplier_min, DAILY_BOSS_HP_MULTIPLIER_MIN); }
        if (hasOwn(settings, 'daily_boss_hp_multiplier_max')) { DAILY_BOSS_HP_MULTIPLIER_MAX = clampInt(settings.daily_boss_hp_multiplier_max, DAILY_BOSS_HP_MULTIPLIER_MAX); }
        if (hasOwn(settings, 'daily_boss_stigmatic_damage_multiplier')) { DAILY_BOSS_STIGMATIC_DAMAGE_MULTIPLIER = Number(settings.daily_boss_stigmatic_damage_multiplier) || DAILY_BOSS_STIGMATIC_DAMAGE_MULTIPLIER; }
        if (hasOwn(settings, 'daily_boss_shield_break_chance')) { DAILY_BOSS_SHIELD_BREAK_CHANCE = Number(settings.daily_boss_shield_break_chance) || DAILY_BOSS_SHIELD_BREAK_CHANCE; }
        if (settings.training_reward_table && typeof settings.training_reward_table === 'object') {
            TRAINING_REWARD_TABLE = {
                base: Math.max(1, clampInt(settings.training_reward_table.base, TRAINING_REWARD_TABLE.base)),
                rollMin: Math.max(1, clampInt(settings.training_reward_table.roll_min, TRAINING_REWARD_TABLE.rollMin)),
                rollMax: Math.max(1, clampInt(settings.training_reward_table.roll_max, TRAINING_REWARD_TABLE.rollMax)),
                difficultyMultipliers: assignObjectValue(TRAINING_REWARD_TABLE.difficultyMultipliers, settings.training_reward_table.difficulty_multipliers)
            };
            if (TRAINING_REWARD_TABLE.rollMax < TRAINING_REWARD_TABLE.rollMin) {
                TRAINING_REWARD_TABLE.rollMax = TRAINING_REWARD_TABLE.rollMin;
            }
        }
        if (settings.daily_boss_card_reward && typeof settings.daily_boss_card_reward === 'object') {
            DAILY_BOSS_CARD_REWARD = {
                rarity: String(settings.daily_boss_card_reward.rarity || DAILY_BOSS_CARD_REWARD.rarity || 'stigmatic')
            };
        }
        if (settings.daily_boss_loot_table && typeof settings.daily_boss_loot_table === 'object') {
            DAILY_BOSS_LOOT_TABLE = {
                mnemones: normalizeRewardRangeConfig(settings.daily_boss_loot_table.mnemones, DAILY_BOSS_LOOT_TABLE.mnemones.min, DAILY_BOSS_LOOT_TABLE.mnemones.max),
                remorias: normalizeRewardRangeConfig(settings.daily_boss_loot_table.remorias, DAILY_BOSS_LOOT_TABLE.remorias.min, DAILY_BOSS_LOOT_TABLE.remorias.max),
                guaranteedMaterialDrop: normalizeDropConfigList(settings.daily_boss_loot_table.guaranteed_material_drop),
                bonusDrops: normalizeDropConfigList(settings.daily_boss_loot_table.bonus_drops)
            };
        }
        if (settings.combat_difficulty_table) {
            COMBAT_DIFFICULTY_TABLE = normalizeCombatDifficultyTable(settings.combat_difficulty_table) || COMBAT_DIFFICULTY_TABLE;
        }
        if (settings.combat_advanced_rules) {
            COMBAT_ADVANCED_RULES = normalizeCombatAdvancedRules(settings.combat_advanced_rules) || COMBAT_ADVANCED_RULES;
            if (COMBAT_ADVANCED_RULES.damageRandomBonusMax < COMBAT_ADVANCED_RULES.damageRandomBonusMin) {
                COMBAT_ADVANCED_RULES.damageRandomBonusMax = COMBAT_ADVANCED_RULES.damageRandomBonusMin;
            }
        }
        if (hasOwn(settings, 'move_debuff_min_ratio')) { MOVE_DEBUFF_MIN_RATIO = Number(settings.move_debuff_min_ratio) || MOVE_DEBUFF_MIN_RATIO; }
        if (hasOwn(settings, 'move_buff_max_ratio')) { MOVE_BUFF_MAX_RATIO = Number(settings.move_buff_max_ratio) || MOVE_BUFF_MAX_RATIO; }
        if (settings.card_game_icons && typeof settings.card_game_icons === 'object') { CARD_GAME_ICONS = settings.card_game_icons; }
        if (settings.combat_sounds && typeof settings.combat_sounds === 'object') { COMBAT_SOUNDS = settings.combat_sounds; }
        if (Array.isArray(packs) && packs.length) {
            PACK_SIZE = clampInt(packs[0].pack_size, PACK_SIZE);
            MAX_PACK_STOCK = clampInt(packs[0].max_stock, MAX_PACK_STOCK);
            PACK_DAILY_CAPS = {};
            packs.forEach(function (pack) {
                var key = String(pack && pack.pack_key || '');
                if (!key || key === 'standard' || pack.daily_cap === null || typeof pack.daily_cap === 'undefined' || pack.daily_cap === '') { return; }
                PACK_DAILY_CAPS[key] = Math.max(0, clampInt(pack.daily_cap, DAILY_SHOP_PACK_CAP));
            });
        }
        if (!FREE_SHOP_QUANTITIES.length) { FREE_SHOP_QUANTITIES = [1, DAILY_FREE_PACK_CAP]; }
    }

    function applyGameRulesPayload(payload) {
        if (!payload || payload.success !== true || !payload.legacy || typeof payload.legacy !== 'object') {
            return false;
        }
        state.shopProducts = Array.isArray(payload.shopProducts) ? payload.shopProducts : [];
        var legacy = payload.legacy;
        RARITY_LABELS = assignObjectValue(RARITY_LABELS, legacy.RARITY_LABELS);
        RARITY_ICONS = assignObjectValue(RARITY_ICONS, legacy.RARITY_ICONS);
        RARITY_SHORT = assignObjectValue(RARITY_SHORT, legacy.RARITY_SHORT);
        RARITY_SKINS = assignObjectValue(RARITY_SKINS, legacy.RARITY_SKINS);
        RARITY_STAT_RANGES = assignObjectValue(RARITY_STAT_RANGES, legacy.RARITY_STAT_RANGES);
        RARITY_ORDER = assignArrayValue(RARITY_ORDER, legacy.RARITY_ORDER);
        NATURAL_RARITY_ORDER = assignArrayValue(NATURAL_RARITY_ORDER, legacy.NATURAL_RARITY_ORDER);
        RARITY_UPGRADE_ORDER = assignArrayValue(RARITY_UPGRADE_ORDER, legacy.RARITY_UPGRADE_ORDER);
        RARITY_WEIGHTS = assignObjectValue(RARITY_WEIGHTS, legacy.RARITY_WEIGHTS);
        PACK_RARITY_WEIGHTS = assignObjectValue(PACK_RARITY_WEIGHTS, legacy.PACK_RARITY_WEIGHTS);
        PACK_KINDS = assignArrayValue(PACK_KINDS, legacy.PACK_KINDS);
        SHOP_PACK_KINDS = assignArrayValue(SHOP_PACK_KINDS, legacy.SHOP_PACK_KINDS);
        PACK_PRICES = assignObjectValue(PACK_PRICES, legacy.PACK_PRICES);
        PACK_LABELS = assignObjectValue(PACK_LABELS, legacy.PACK_LABELS);
        PACK_CONTENTS = assignObjectValue(PACK_CONTENTS, legacy.PACK_CONTENTS);
        PACK_SKINS = assignObjectValue(PACK_SKINS, legacy.PACK_SKINS);
        RECYCLE_VALUES = assignObjectValue(RECYCLE_VALUES, legacy.RECYCLE_VALUES);
        WORK_RARITY_BASE = assignObjectValue(WORK_RARITY_BASE, legacy.WORK_RARITY_BASE);
        UPGRADE_COST_BY_RARITY = assignObjectValue(UPGRADE_COST_BY_RARITY, legacy.UPGRADE_COST_BY_RARITY);
        RARITY_UPGRADE_MATERIALS = assignObjectValue(RARITY_UPGRADE_MATERIALS, legacy.RARITY_UPGRADE_MATERIALS);
        UPGRADE_MATERIALS = assignObjectValue(UPGRADE_MATERIALS, legacy.UPGRADE_MATERIALS);
        SKILL_COST_MULTIPLIER_BY_RARITY = assignObjectValue(SKILL_COST_MULTIPLIER_BY_RARITY, legacy.SKILL_COST_MULTIPLIER_BY_RARITY);
        TYPE_LABELS = assignObjectValue(TYPE_LABELS, legacy.TYPE_LABELS);
        TYPE_ORDER = assignArrayValue(TYPE_ORDER, legacy.TYPE_ORDER);
        TYPE_EMOJI = assignObjectValue(TYPE_EMOJI, legacy.TYPE_EMOJI);
        TYPE_ALIASES = assignObjectValue(TYPE_ALIASES, legacy.TYPE_ALIASES);
        TYPE_ICON_SVG = assignObjectValue(TYPE_ICON_SVG, legacy.TYPE_ICON_SVG);
        UI_TEXTS = assignObjectValue(UI_TEXTS, legacy.UI_TEXTS);
        POWER_TYPES = assignArrayValue(POWER_TYPES, legacy.POWER_TYPES);
        CHRONICLE_TYPES = assignArrayValue(CHRONICLE_TYPES, legacy.CHRONICLE_TYPES);
        RELIC_TYPES = assignArrayValue(RELIC_TYPES, legacy.RELIC_TYPES);
        LINEAGE_TYPES = assignArrayValue(LINEAGE_TYPES, legacy.LINEAGE_TYPES);
        ESSENCE_TYPES = assignArrayValue(ESSENCE_TYPES, legacy.ESSENCE_TYPES);
        DAILY_GIFT_MATERIAL_KEYS = assignArrayValue(DAILY_GIFT_MATERIAL_KEYS, legacy.DAILY_GIFT_MATERIAL_KEYS);
        MOVE_LIBRARY = assignObjectValue(MOVE_LIBRARY, legacy.MOVE_LIBRARY);
        MOVE_LEARN_RULES = assignObjectValue(MOVE_LEARN_RULES, legacy.MOVE_LEARN_RULES);
        applyRaritySkins();
        applyGameRulesSettings(payload.settings || {}, payload.packs || []);
        if (!RARITY_ORDER.length || !NATURAL_RARITY_ORDER.length || !PACK_KINDS.length || !TYPE_ORDER.length || !Object.keys(UPGRADE_MATERIALS).length || !Object.keys(MOVE_LIBRARY).length) {
            return false;
        }
        if (!PACK_SIZE || !MAX_PACK_STOCK || !MAX_MNEMONES || !MAX_REMORIAS || !SKILL_SLOT_COUNT || !SKILL_MATERIAL_KEY) {
            return false;
        }
        state.rulesCatalog = payload;
        return true;
    }

    function loadGameRules() {
        var url = root.getAttribute('data-rules-url') || '/api/game_card_rules.php';
        setStatus(uiText('status.loading_rules', 'Cargando reglas del juego...'));
        return fetch(url, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) { throw new Error('No se pudieron cargar las reglas.'); }
                return res.json();
            })
            .then(function (payload) {
                if (!applyGameRulesPayload(payload)) {
                    throw new Error('Reglas invalidas.');
                }
                if (state.collection) {
                    state.collection.packInventory = normalizePackInventory(state.collection.packInventory);
                    state.collection.dailyShopPackPurchases = normalizeDailyShopPackPurchases(state.collection.dailyShopPackPurchases);
                    state.collection.materialInventory = normalizeMaterialInventory(state.collection.materialInventory);
                    saveCollection();
                }
                renderDailyCounter();
                renderPackInventory();
                renderShop();
                return payload;
            })
            .catch(function (err) {
                state.rulesCatalog = null;
                setStatus((err && err.message) || uiText('status.rules_load_error', 'No se pudieron cargar las reglas del juego.'));
                return null;
            });
    }

    function loadCollection() {
        var data = readMigratedJson(STORAGE_KEY, LEGACY_STORAGE_KEY, null);
        if (!data) {
            state.collection = createEmptyCollection();
            return state.collection;
        }
        try {
            state.collection = validateCollection(data);
        } catch (e) {
            state.collection = createEmptyCollection();
        }
        state.collection.currency = normalizeCurrency(state.collection.currency);
        state.collection.packInventory = normalizePackInventory(state.collection.packInventory);
        state.collection.dailyShopPackPurchases = normalizeDailyShopPackPurchases(state.collection.dailyShopPackPurchases);
        state.collection.materialInventory = normalizeMaterialInventory(state.collection.materialInventory);
        state.collection.workAssignments = normalizeWorkAssignments(state.collection.workAssignments);
        state.collection.workPendingRewards = normalizeWorkPendingRewards(state.collection.workPendingRewards);
        cleanWorkAssignments(false);
        limitWorkAssignments(false);
        saveCollection();
        return state.collection;
    }

    function saveCollection() {
        if (!state.collection) { state.collection = createEmptyCollection(); }
        state.collection.currency = normalizeCurrency(state.collection.currency);
        state.collection.packInventory = normalizePackInventory(state.collection.packInventory);
        state.collection.dailyShopPackPurchases = normalizeDailyShopPackPurchases(state.collection.dailyShopPackPurchases);
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
        if (card.card_rarity === 'stigmatic') {
            return packKind === 'daily-boss' || packKind === 'stigmatic';
        }
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
        NATURAL_RARITY_ORDER.forEach(function (rarity) { total += weights[rarity] || 0; });
        var roll = Math.random() * total;
        var acc = 0;
        for (var i = 0; i < NATURAL_RARITY_ORDER.length; i++) {
            var rarity = NATURAL_RARITY_ORDER[i];
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
        var order = (rarity === 'stigmatic' || packKind === 'daily-boss' || packKind === 'stigmatic') ? RARITY_ORDER : NATURAL_RARITY_ORDER;
        var start = order.indexOf(rarity);
        if (start === -1) { start = 0; }
        for (var i = start; i >= 0; i--) {
            var lower = order[i];
            var lowerPool = rarityPool(lower, packKind, excludedIds);
            if (lowerPool.length) { return lowerPool[Math.floor(Math.random() * lowerPool.length)]; }
        }
        for (var j = start + 1; j < order.length; j++) {
            var higher = order[j];
            var higherPool = rarityPool(higher, packKind, excludedIds);
            if (higherPool.length) { return higherPool[Math.floor(Math.random() * higherPool.length)]; }
        }
        if (excludedIds && Object.keys(excludedIds).length) {
            return pickCardByRarity(rarity, packKind, null);
        }
        return null;
    }

    function createCardCopy(card, options) {
        options = options || {};
        var rarity = normalizeRarity(options.rarity || card.card_rarity, card.card_rarity);
        var hpBounds = statBoundsForRarity(card, rarity, 'hp');
        var atkBounds = statBoundsForRarity(card, rarity, 'atk');
        var defBounds = statBoundsForRarity(card, rarity, 'def');
        var copy = {
            instanceId: options.instanceId || instanceId(),
            cardId: card.card_id,
            rarity: rarity,
            hp: rollStat(hpBounds[0], hpBounds[1]),
            atk: rollStat(atkBounds[0], atkBounds[1]),
            def: rollStat(defBounds[0], defBounds[1]),
            obtainedAt: options.obtainedAt || nowIso(),
            moves: normalizeCopyMoveIds(Array.isArray(options.moves) ? options.moves : []),
            moveRollRarity: normalizeRarity(options.moveRollRarity || rarity, rarity)
        };
        ensureCopyMovesForRarity(copy, card, rarity, !Array.isArray(options.moves));
        copy.quality = calculatedQualityScore(copy, card);
        return copy;
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

    function playPackOpenSound() { playUiSound('/sounds/ui/tear.ogg', 0.8); }
    function playFlipSound() { playUiSound('/sounds/ui/flip.ogg', 0.8); }
    function playSkillSound() { playUiSound('/sounds/ui/skill.ogg', 0.82); }
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
            setStatus(uiText('status.no_cards_for_packs', 'No hay cartas disponibles para abrir sobres.'));
            return [];
        }
        if (packKind === 'standard' && packStock('standard') <= 0) {
            setStatus(uiText('status.no_standard_packs', 'No tienes sobres mnemónicos disponibles. Compra unidades desde la tienda.'));
            return [];
        }
        if (packKind !== 'standard' && packStock(packKind) <= 0) {
            setStatus(uiText('status.no_pack_units', 'No tienes unidades de {pack}.', { pack: packLabel(packKind).toLowerCase() }));
            return [];
        }
        if (!state.collection) { loadCollection(); }

        var hasPackCards = state.catalog.some(function (card) { return cardAllowedForPack(card, packKind); });
        if (!hasPackCards) {
            setStatus(uiText('status.no_cards_for_pack_type', 'No hay cartas disponibles para este tipo de sobre.'));
            return [];
        }
        var obtained = [];
        var usedCardIds = {};
        for (var i = 0; i < PACK_SIZE; i++) {
            var card = pickCardByRarity(pickRarity(packKind), packKind, usedCardIds);
            if (!card) { continue; }
            usedCardIds[String(card.card_id)] = true;
            var copy = createCardCopy(card);
            state.collection.ownedCards.push(copy);
            obtained.push({ catalog: card, instance: copy });
        }

        consumePack(packKind);

        if (!options.deferSave) { saveCollection(); }
        if (!options.silent) {
            if (obtained.length) { playPackOpenSound(); }
            renderPackResults(obtained);
            renderSummary();
            renderPackInventory();
            showPackReveal(obtained, packKind);
            setStatus(uiText('status.pack_opened', '{pack}: {count} cartas obtenidas.', { pack: packLabel(packKind), count: obtained.length }));
        }
        return obtained;
    }

    function buyPack(packKind, amount, options) {
        options = options || {};
        amount = Math.max(1, clampInt(amount, 1));
        packKind = packKind || 'standard';
        var isFree = options.free === true;
        if (PACK_KINDS.indexOf(packKind) === -1) {
            setStatus(uiText('status.pack_unknown', 'Ese sobre no existe.'));
            return false;
        }
        if (!isFree && !packAvailableInShop(packKind, false)) {
            setStatus(uiText('status.pack_not_shop', 'Ese sobre no está disponible en la tienda normal.'));
            return false;
        }
        if (!state.collection) { loadCollection(); }
        var price = packPrice(packKind);
        if (!state.isAdmin && packSpace(packKind) < amount) {
            setStatus(uiText('status.pack_stock_full', 'No puedes acumular más de {max} sobres de cada tipo.', { max: MAX_PACK_STOCK }));
            renderPackInventory();
            return false;
        }
        if (isFree) {
            if (!claimDailyFreePacks(amount)) {
                setStatus(uiText('status.free_pack_not_enough', 'No puedes reclamar {amount} sobres gratis. Quedan {remaining} hoy.', { amount: amount, remaining: dailyFreePacksRemaining() }));
                renderDailyCounter();
                renderPackInventory();
                return false;
            }
            playMoneySound();
            addPack('standard', amount, { silent: true, deferSave: true });
            saveCollection();
            renderDailyCounter();
            renderPackInventory();
            setStatus(uiText('status.free_pack_claimed', '{amount} sobre(s) mnemónicos gratis añadidos. Quedan {remaining} gratis hoy.', { amount: amount, remaining: dailyFreePacksRemaining() }));
            return true;
        }
        var totalPrice = price * amount;
        if (!state.isAdmin && currentMnemones() < totalPrice) {
            setStatus(uiText('status.not_enough_mnemones_pack', 'No tienes Mnemones suficientes para comprar {pack}.', { pack: packLabel(packKind).toLowerCase() }));
            return false;
        }
        if (!isFree && !claimDailyShopPacks(packKind, amount)) {
            setStatus(uiText('status.pack_daily_limit', 'Límite diario alcanzado para {pack}. Quedan {remaining} hoy.', { pack: packLabel(packKind).toLowerCase(), remaining: dailyShopPackRemaining(packKind) }));
            renderPackInventory();
            return false;
        }
        if (!state.isAdmin) {
            addMnemones(-totalPrice);
        }
        playMoneySound();
        addPack(packKind, amount, { silent: true, deferSave: true });
        saveCollection();
        renderPackInventory();
        setStatus(uiText('status.pack_bought', '{amount} x {pack} añadidos a tus sobres.', { amount: amount, pack: packLabel(packKind).toLowerCase() }));
        return true;
    }

    function buyMaterial(materialKey, amount) {
        amount = Math.max(1, clampInt(amount, 1));
        var material = UPGRADE_MATERIALS[materialKey];
        if (!material) {
            setStatus(uiText('status.material_unknown', 'Ese objeto no existe.'));
            return false;
        }
        if (!state.collection) { loadCollection(); }
        var totalPrice = material.price * amount;
        if (!state.isAdmin && currentMnemones() < totalPrice) {
            setStatus(uiText('status.not_enough_mnemones_material', 'No tienes Mnemones suficientes para comprar {material}.', { material: material.label }));
            return false;
        }
        if (!state.isAdmin) {
            addMnemones(-totalPrice);
        }
        playMoneySound();
        var newStock = addMaterial(materialKey, amount);
        if (newStock === false) {
            setStatus(uiText('status.material_inventory_error', 'No se ha podido añadir ese objeto al inventario.'));
            return false;
        }
        saveCollection();
        renderSummary({ light: true });
        renderPackInventory();
        setStatus(uiText('status.material_bought', '{amount} x {material} añadido(s) al inventario por {price} Mnemones. Tienes {stock}.', {
            amount: amount,
            material: material.label,
            price: formatNumber(totalPrice),
            stock: state.isAdmin ? uiText('shop.admin', 'Admin') : newStock
        }));
        return true;
    }

    function claimShopDailyGift(materialKey) {
        materialKey = String(materialKey || '');
        var stateNow = dailyGiftState();
        var rewardKey = materialKey || stateNow.key;
        var material = UPGRADE_MATERIALS[rewardKey];
        if (!material) {
            setStatus(uiText('status.daily_gift_unknown', 'Ese regalo diario no existe.'));
            return false;
        }
        if (!claimDailyGift()) {
            setStatus(uiText('status.daily_gift_already_claimed', 'Ya has reclamado el regalo diario de hoy.'));
            return false;
        }
        addMaterial(rewardKey, 1);
        saveCollection();
        playMoneySound();
        renderSummary({ light: true });
        renderPackInventory();
        setStatus(uiText('status.daily_gift_claimed', 'Regalo diario reclamado: 1 x {material}.', { material: material.label }));
        return true;
    }

    function buyRemoriaExchange(remoriasAmount) {
        remoriasAmount = Math.max(1, clampInt(remoriasAmount, 1));
        var totalPrice = remoriasAmount * 10;
        if (!state.collection) { loadCollection(); }
        if (!state.isAdmin && currentMnemones() < totalPrice) {
            setStatus(uiText('status.not_enough_mnemones_exchange', 'No tienes Mnemones suficientes para ese cambio.'));
            return false;
        }
        if (!state.isAdmin) {
            addMnemones(-totalPrice);
            addRemorias(remoriasAmount);
        }
        playMoneySound();
        saveCollection();
        renderSummary({ light: true });
        renderPackInventory();
        setStatus(uiText('status.exchange_done', 'Cambio realizado: -{mnemones} Mnemones, +{remorias} Remorias.', { mnemones: formatNumber(totalPrice), remorias: formatNumber(remoriasAmount) }));
        return true;
    }

    function openAllPacks() {
        if (!state.catalog.length) {
            setStatus(uiText('status.no_cards_for_packs', 'No hay cartas disponibles para abrir sobres.'));
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
            setStatus(uiText('status.no_packs_left_open_all', 'No te quedan sobres por abrir.'));
            renderPackInventory();
            return [];
        }
        if (obtained.length) { playPackOpenSound(); }
        saveCollection();
        renderPackResults(obtained.slice(-5));
        renderSummary();
        renderPackInventory();
        setStatus(uiText('status.open_all_done', 'Sobres abiertos: {opened}. Mostrando las últimas 5 cartas obtenidas.', { opened: opened }));
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
            renderCombatProfile();
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
        renderCombatProfile();
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

    function collectionFilterSets() {
        var inTeam = {};
        var working = {};
        var teams = loadCombatTeams();
        (teams.teams || []).forEach(function (team) {
            (team.cards || []).forEach(function (id) {
                id = String(id || '');
                if (id) { inTeam[id] = true; }
            });
        });
        Object.keys(ensureWorkAssignments()).forEach(function (id) {
            id = String(id || '');
            if (id) { working[id] = true; }
        });
        return { inTeam: inTeam, working: working };
    }

    function cardPassesCollectionFilters(card, groups, filterSets) {
        if (!card) { return false; }
        var group = groups[String(card.card_id)];
        filterSets = filterSets || { inTeam: {}, working: {} };
        if (state.collectionSearch) {
            if (!group) { return false; }
            var searchText = normalizeSearchText([
                card.card_name,
                card.card_slug,
                typeLabel(card.source_type),
                TYPE_LABELS[card.source_type]
            ].join(' '));
            if (searchText.indexOf(state.collectionSearch) === -1) { return false; }
        }
        if (state.collectionRarity !== 'all') {
            var hasRarity = group && group.copies && group.copies.some(function (copy) {
                return copyRarity(copy, card) === state.collectionRarity;
            });
            if (!hasRarity && card.card_rarity !== state.collectionRarity) { return false; }
        }
        if (state.collectionOwnedOnly && !groups[String(card.card_id)]) { return false; }
        if (state.collectionHasMovesOnly) {
            var hasMoves = group && group.copies && group.copies.some(function (copy) {
                return copyHasLearnedMoves(copy);
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
        var present = {};
        state.catalog.forEach(function (card) {
            if (!cardPassesCollectionFilters(card, groups, filterSets)) { return; }
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
                .filter(function (card) { return cardPassesCollectionFilters(card, groups, filterSets); });
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
            score.textContent = uiText('card.total', 'Total {total}', { total: totalStats(best) });
            button.appendChild(score);
        }

        button.appendChild(image);
        button.appendChild(meta);
        button.appendChild(title);
        button.appendChild(footer);
        button.addEventListener('click', function () {
            if (!owned) {
                setStatus(uiText('collection.undiscovered_slot', 'Ese hueco todavía no está descubierto.'));
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

    function cardsForCurrentCategory(groups, filterSets) {
        var cards = state.albumCategory === 'all'
            ? state.catalog
            : state.catalog.filter(function (card) { return card.source_type === state.albumCategory; });
        return sortedCatalogCards(cards.filter(function (card) {
            return cardPassesCollectionFilters(card, groups || {}, filterSets || {});
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
            prev.setAttribute('aria-label', uiText('collection.page_previous', 'Página anterior'));
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
            next.setAttribute('aria-label', uiText('collection.page_next', 'Página siguiente'));
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

    function renderAlbum(groups, filterSets) {
        if (!els.albumGrid || !isCollectionContext()) { return 0; }
        groups = groups || collectionGroups();
        var cards = cardsForCurrentCategory(groups, filterSets);
        var bounds = pageBounds(cards.length);
        var pageCards = cards.slice(bounds.start, bounds.end);
        els.albumGrid.innerHTML = '';
        if (!pageCards.length) {
            var empty = document.createElement('p');
            empty.className = 'hg-empty-state';
            empty.textContent = uiText('collection.empty_filters', 'No hay cartas con estos filtros.');
            els.albumGrid.appendChild(empty);
            return cards.length;
        }
        pageCards.forEach(function (card) {
            els.albumGrid.appendChild(renderAlbumSlot(card, groups[String(card.card_id)]));
        });
        return cards.length;
    }

    function tableEntriesForCurrentCategory(groups, filterSets) {
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
                card: card,
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
            return cardPassesCollectionFilters(entry.card, groups, filterSets)
                && (state.albumCategory === 'all' || entry.sourceType === state.albumCategory)
                && (state.collectionRarity === 'all' || entry.rarity === state.collectionRarity);
        }).sort(function (a, b) {
            return (b.score || 0) - (a.score || 0);
        });
    }

    function renderCollectionTable() {
        if (!isCollectionContext()) { return; }
        var groups = collectionGroups();
        var filterSets = collectionFilterSets();
        var categories = albumCategories(groups, filterSets);
        ensureAlbumCategory(categories);
        renderAlbumTabs(categories);
        renderCollectionTypeFilter(categories);
        applyCollectionMode();

        var totalItems = 0;
        if (state.collectionMode === 'album') {
            totalItems = renderAlbum(groups, filterSets);
        } else {
            var tableRows = tableEntriesForCurrentCategory(groups, filterSets);
            totalItems = tableRows.length;
            var bounds = pageBounds(totalItems);
            var pageRows = tableRows.slice(bounds.start, bounds.end);
            var tbody = els.collectionTable ? els.collectionTable.querySelector('tbody') : null;
            if (tbody) {
                tbody.innerHTML = pageRows.map(function (entry) {
            return '<tr class="hg-collection-row--' + entry.rarity + '" data-card-id="' + entry.cardId + '">' + entry.row.map(function (cell) { return '<td>' + cell + '</td>'; }).join('') + '</tr>';
                }).join('');
                if (!pageRows.length) {
                    tbody.innerHTML = '<tr><td colspan="' + (state.mobile ? '6' : '11') + '">' + escapeHtml(uiText('collection.empty_obtained_filters', 'No hay cartas obtenidas con estos filtros.')) + '</td></tr>';
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
            version: 2,
            activeTeam: 0,
            teams: [0, 1, 2, 3, 4].map(function (index) {
                return { name: 'Equipo ' + (index + 1), cards: [] };
            })
        };
    }

    function defaultCombatTeamName(index) {
        return 'Equipo ' + (Number(index || 0) + 1);
    }

    function combatTeamDisplayName(team, index) {
        var name = String(team && team.name || '').trim();
        return name || defaultCombatTeamName(index);
    }

    function normalizeCombatTeams(data) {
        var out = createEmptyCombatTeams();
        if (!data || typeof data !== 'object') { return out; }
        out.activeTeam = Math.max(0, Math.min(4, clampInt(data.activeTeam, 0)));
        if (Array.isArray(data.teams)) {
            data.teams.slice(0, 5).forEach(function (team, index) {
                if (!team || typeof team !== 'object') { return; }
                out.teams[index] = {
                    name: String(team.name || defaultCombatTeamName(index)).slice(0, 40),
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
        state.combatTeams = normalizeCombatTeams(readMigratedJson(COMBAT_TEAMS_KEY, LEGACY_COMBAT_TEAMS_KEY, null));
        state.activeCombatTeam = state.combatTeams.activeTeam;
        state.draftCombatTeam = state.combatTeams.teams[state.activeCombatTeam].cards.slice();
        saveCombatTeams();
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
            playerName: String(data.playerName || '').slice(0, 32)
        };
    }

    function loadCombatProfile() {
        if (state.combatProfile) { return state.combatProfile; }
        state.combatProfile = normalizeCombatProfile(readMigratedJson(COMBAT_PROFILE_KEY, LEGACY_COMBAT_PROFILE_KEY, null));
        saveCombatProfile();
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

    function sortCombatEntries(entries) {
        var mode = state.combatSort || 'quality';
        return entries.slice().sort(function (a, b) {
            if (mode === 'total') {
                return b.score - a.score
                    || qualityScore(b.copy, b.baseCard) - qualityScore(a.copy, a.baseCard)
                    || String(a.card.card_name).localeCompare(String(b.card.card_name));
            }
            if (mode === 'rarity') {
                return rarityRank(b.card.card_rarity) - rarityRank(a.card.card_rarity)
                    || qualityScore(b.copy, b.baseCard) - qualityScore(a.copy, a.baseCard)
                    || b.score - a.score;
            }
            if (mode === 'recent') {
                return String(b.copy.obtainedAt || '').localeCompare(String(a.copy.obtainedAt || ''))
                    || String(b.copy.instanceId || '').localeCompare(String(a.copy.instanceId || ''));
            }
            if (mode === 'name') {
                return String(a.card.card_name).localeCompare(String(b.card.card_name))
                    || qualityScore(b.copy, b.baseCard) - qualityScore(a.copy, a.baseCard);
            }
            return qualityScore(b.copy, b.baseCard) - qualityScore(a.copy, a.baseCard)
                || b.score - a.score
                || rarityRank(b.card.card_rarity) - rarityRank(a.card.card_rarity)
                || String(a.card.card_name).localeCompare(String(b.card.card_name));
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
            return '<option value="' + index + '">' + escapeHtml(combatTeamDisplayName(team, index)) + ' (' + team.cards.length + '/5)</option>';
        }).join('');
        els.combatTeamSelects.forEach(function (select) {
            select.innerHTML = html;
            select.value = String(state.activeCombatTeam);
        });
    }

    function renderCombatTeamName() {
        if (!els.combatTeamNames.length) { return; }
        loadCombatTeams();
        var team = state.combatTeams.teams[state.activeCombatTeam] || state.combatTeams.teams[0];
        var name = combatTeamDisplayName(team, state.activeCombatTeam);
        els.combatTeamNames.forEach(function (input) {
            if (input.value !== name) { input.value = name; }
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
                empty.textContent = uiText('combat.team_empty', 'Equipo vacío. Prepáralo antes de combatir.');
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
                    item.innerHTML = '<strong>' + escapeHtml(uiText('memory.slot_label', 'Hueco {slot}', { slot: i + 1 })) + '</strong><small>' + escapeHtml(uiText('combat.slot_empty', 'Sin carta')) + '</small>';
                }
                preview.appendChild(item);
            }
            var totalItem = document.createElement('span');
            totalItem.className = 'hg-combat-team-preview__total';
            totalItem.innerHTML = '<strong>' + escapeHtml(uiText('combat.team_total', 'Total equipo')) + '</strong><small>' + total + '</small>';
            preview.appendChild(totalItem);
        });
    }

    function renderCombatProfile() {
        if (!els.combatProfileNames.length) { return; }
        var profile = loadCombatProfile();
        els.combatProfileNames.forEach(function (input) {
            if (input.value !== profile.playerName) { input.value = profile.playerName; }
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
        summary.innerHTML = '<strong>' + escapeHtml(uiText('combat.team_total_full', 'Total del equipo')) + '</strong><b>' + teamTotal + '</b><span>' + escapeHtml(uiText('combat.cards_count', '{count} / 5 cartas', { count: state.draftCombatTeam.length })) + '</span>';
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
                slot.innerHTML = '<strong>' + escapeHtml(uiText('memory.slot_label', 'Hueco {slot}', { slot: i + 1 })) + '</strong><span>' + escapeHtml(uiText('combat.choose_card', 'Elige una carta')) + '</span>';
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
        var entries = sortCombatEntries(allEntries.filter(function (entry) {
            return (!onlyReady || !selected[String(entry.copy.instanceId || '')])
                && (state.combatRarityFilter === 'all' || entry.card.card_rarity === state.combatRarityFilter)
                && (state.combatTypeFilter === 'all' || entry.card.source_type === state.combatTypeFilter);
        }));
        els.combatCardList.innerHTML = '';
        if (!entries.length) {
            var empty = document.createElement('p');
            empty.className = 'hg-empty-state';
            empty.textContent = state.catalog.length ? uiText('combat.cards_filter_empty', 'No hay cartas disponibles con esos filtros.') : uiText('combat.cards_loading', 'Cargando cartas...');
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
                    setCombatMessage(uiText('combat.team_full', 'El equipo ya tiene 5 cartas.'));
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
            setCombatMessage(uiText('combat.auto_no_cards', 'No hay cartas disponibles para crear autoequipo con esos filtros.'));
            return false;
        }
        if (options.requireFullTeam && picked.length < 5) {
            setCombatMessage(uiText('combat.auto_need_full', 'Necesitas al menos 5 cartas disponibles para crear un equipo rápido.'));
            return false;
        }
        state.draftCombatTeam = picked;
        state.combatTeams.teams[state.activeCombatTeam].cards = picked.slice();
        saveCombatTeams();
        renderCombatSetup();
        setCombatMessage(uiText('combat.auto_saved', 'Autoequipo guardado: {count}/5 mejores cartas disponibles.', { count: picked.length }));
        return true;
    }

    function promptQuickCombatTeam() {
        return confirmGameAction(
            uiText('combat.quick_team_prompt', '¿Quieres crear un equipo de 5 cartas rápido?'),
            {
                title: uiText('combat.quick_team_title', 'Equipo rápido'),
                confirmLabel: uiText('combat.quick_team_confirm', 'Sí, crear equipo'),
                cancelLabel: uiText('combat.quick_team_cancel', 'Ahora no')
            },
            function () {
                if (autoBuildCombatTeam({ ignoreFilters: true, requireFullTeam: true })) {
                    startSelectedCombat();
                }
            }
        );
    }

    function renderDailyBossSummary() {
        if (!els.dailyBossSummaries.length) { return; }
        var combatInProgress = !!(state.combat && !state.combat.over);
        var active = state.combatMode === 'daily-boss' && !combatInProgress;
        var bossState = active ? (state.dailyBoss || loadDailyBossState()) : null;
        els.dailyBossSummaries.forEach(function (summary) {
            summary.hidden = !active;
            summary.classList.remove('is-completed', 'is-unavailable');
            summary.innerHTML = '';
            if (!active) { return; }
            if (!bossState) {
                summary.classList.add('is-unavailable');
                summary.innerHTML = '<strong>' + escapeHtml(uiText('combat.daily_unavailable_title', 'Jefe diario no disponible')) + '</strong><span>' + escapeHtml(uiText('combat.daily_unavailable_text', 'No hay carta válida para generar el desafío.')) + '</span>';
                return;
            }
            if (bossState.completed || bossState.hp <= 0 || dailyBossRewardClaimedToday()) {
                summary.classList.add('is-completed');
                summary.innerHTML = '<strong>' + escapeHtml(uiText('combat.daily_completed_title', 'Desafío diario completado')) + '</strong><span>' + escapeHtml(uiText('combat.daily_completed_text', 'Vuelve mañana para otro Jefe diario.')) + '</span>';
                if (state.isAdmin) { appendDailyBossResetButton(summary); }
                return;
            }
            var entry = dailyBossEntryFromState();
            var card = entry ? entry.card : null;
            var hpPercent = bossState.maxHp > 0 ? Math.max(0, Math.min(100, (bossState.hp / bossState.maxHp) * 100)) : 0;
            var defeated = bossState.activeAttempt && bossState.activeAttempt.defeatedCopyIds
                ? bossState.activeAttempt.defeatedCopyIds.length
                : 0;
            summary.innerHTML =
                '<div class="hg-daily-boss-summary__media">' +
                    '<img src="' + escapeHtml(card && card.card_image_url || '/img/og/og_image.jpg') + '" alt="">' +
                '</div>' +
                '<div class="hg-daily-boss-summary__body">' +
                    '<strong>' + escapeHtml(card ? card.card_name : bossState.cardName) + '</strong>' +
                    '<span>Jefe Estigmático persistente. No puede huir ni curarse.</span>' +
                    '<div class="hg-daily-boss-summary__hp"><i><b style="width:' + hpPercent.toFixed(2) + '%"></b></i><em>PS ' + formatNumber(bossState.hp) + ' / ' + formatNumber(bossState.maxHp) + '</em></div>' +
                    '<small>Intentos: ' + formatNumber(bossState.attempts) + ' · ATQ ' + formatNumber(bossState.atk) + ' · DEF ' + formatNumber(bossState.def) + (defeated ? ' · Caídas pendientes: ' + defeated : '') + '</small>' +
                '</div>';
            if (state.isAdmin) { appendDailyBossResetButton(summary); }
        });
    }

    function appendDailyBossResetButton(summary) {
        var reset = document.createElement('button');
        reset.type = 'button';
        reset.className = 'hg-daily-boss-summary__reset';
        reset.textContent = uiText('combat.daily_reset_button', 'Reset admin');
        reset.addEventListener('click', resetDailyBossState);
        summary.appendChild(reset);
    }

    function updateCombatModeButtons() {
        var bossMode = state.combatMode === 'daily-boss';
        var trainingMode = state.combatMode === 'training';
        root.classList.toggle('is-daily-boss-mode', bossMode);
        root.classList.toggle('is-training-mode', trainingMode);
        els.combatModeButtons.forEach(function (button) {
            var mode = button.getAttribute('data-combat-mode') || 'training';
            var active = mode === state.combatMode;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        els.combatDifficultyWraps.forEach(function (wrap) {
            wrap.hidden = !trainingMode;
        });
        if (els.combatStart && !state.combat) {
            els.combatStart.textContent = bossMode ? 'Desafiar jefe diario' : 'Iniciar combate';
        }
        renderDailyBossSummary();
    }

    function renderCombatSetup() {
        if (!isCombatContext()) { return; }
        cleanCombatTeamsAgainstCollection(true);
        updateCombatModeButtons();
        if (els.combatSort) { els.combatSort.value = state.combatSort; }
        renderCombatTeamSelect();
        renderCombatTeamName();
        renderCombatTeamPreview();
        renderCombatProfile();
        if (els.combatStart) {
            var bossState = state.combatMode === 'daily-boss' ? (state.dailyBoss || loadDailyBossState()) : null;
            var completed = bossState && (bossState.completed || bossState.hp <= 0 || dailyBossRewardClaimedToday());
            root.classList.toggle('is-daily-boss-completed', !!completed);
            els.combatStart.disabled = !!completed;
            els.combatStart.textContent = state.combatMode === 'daily-boss'
                ? (completed ? 'Desafío diario completado' : 'Desafiar jefe diario')
                : 'Iniciar combate';
        } else {
            root.classList.remove('is-daily-boss-completed');
        }
        if (!isCombatLoadoutVisible() || !els.combatTeamSlots) { return; }
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
        if (state.activeCombatScreen === 'battle') {
            renderCombatSetup();
            renderCombatBattle();
        }
    }

    function saveDraftCombatTeam() {
        loadCombatTeams();
        state.draftCombatTeam = validDraftTeam();
        state.combatTeams.teams[state.activeCombatTeam].cards = state.draftCombatTeam.slice();
        saveCombatTeams();
        renderCombatSetup();
        setCombatMessage(state.draftCombatTeam.length + '/5 cartas guardadas en ' + combatTeamDisplayName(state.combatTeams.teams[state.activeCombatTeam], state.activeCombatTeam) + '.');
    }

    function clearDraftCombatTeam() {
        state.draftCombatTeam = [];
        saveDraftCombatTeam();
    }

    function combatDifficultyConfig() {
        var value = els.combatDifficulty ? els.combatDifficulty.value : 'apprentice';
        return COMBAT_DIFFICULTY_TABLE[value] || COMBAT_DIFFICULTY_TABLE.apprentice;
    }

    function combatRewardMultiplier() {
        var value = els.combatDifficulty ? els.combatDifficulty.value : 'apprentice';
        var multipliers = TRAINING_REWARD_TABLE.difficultyMultipliers || {};
        return Math.max(1, Number(multipliers[value]) || Number(multipliers.apprentice) || 1);
    }

    function createCombatUnit(card, copy, side, index, options) {
        options = options || {};
        var maxHp = Math.max(1, clampInt(options.maxHp || copy.hp, 1));
        var currentHp = Math.max(1, Math.min(maxHp, clampInt(options.currentHp || copy.hp, maxHp)));
        var shields = options.noShields ? 0 : rarityShieldCount(card && card.card_rarity);
        var moves = normalizeCombatMoves(card, copy);
        var baseAtk = Math.max(1, clampInt(copy.atk, 1));
        var baseDef = Math.max(1, clampInt(copy.def, 1));
        return {
            side: side,
            index: index,
            card: card,
            copy: copy,
            hp: currentHp,
            maxHp: maxHp,
            baseAtk: baseAtk,
            baseDef: baseDef,
            atk: baseAtk,
            def: baseDef,
            shields: shields,
            maxShields: shields,
            moves: moves,
            moveState: createMoveState(moves),
            combatBuffs: { atk: 0, def: 0 },
            combatDebuffs: { atk: 0, def: 0 },
            defending: false,
            defeated: false
        };
    }

    function normalizeCombatMoves(card, copy) {
        var copyMoveIds = normalizeCopyMoveIds(copy && copy.moves);
        if (copyMoveIds.length) {
            return copyMoveIds.map(function (moveId) {
                return cloneMoveDefinition(MOVE_LIBRARY[moveId]);
            }).filter(Boolean).slice(0, 3);
        }
        return (card && Array.isArray(card.moves) ? card.moves : []).map(function (move) {
            return cloneMoveDefinition(move);
        }).filter(Boolean).slice(0, 3);
    }

    function createMoveState(moves) {
        var state = {};
        (moves || []).forEach(function (move) {
            state[move.id] = { cooldownRemaining: 0 };
        });
        return state;
    }

    function rarityRank(rarity) {
        var index = RARITY_ORDER.indexOf(String(rarity || 'common'));
        return index === -1 ? 0 : index;
    }

    function rarityShieldCount(rarity) {
        var map = COMBAT_ADVANCED_RULES.rarityShields || {};
        return Math.max(0, clampInt(map[String(rarity || 'common')], rarityRank(rarity) + 1));
    }

    function pickWeightedEnemyRarity(config) {
        var weights = config.weights || RARITY_WEIGHTS;
        var total = NATURAL_RARITY_ORDER.reduce(function (sum, rarity) {
            return sum + Math.max(0, weights[rarity] || 0);
        }, 0);
        var roll = Math.random() * Math.max(1, total);
        var acc = 0;
        for (var i = 0; i < NATURAL_RARITY_ORDER.length; i++) {
            var rarity = NATURAL_RARITY_ORDER[i];
            acc += Math.max(0, weights[rarity] || 0);
            if (roll <= acc) { return rarity; }
        }
        return 'common';
    }

    function pickEnemyCatalogCard(config, excluded) {
        for (var attempt = 0; attempt < COMBAT_ADVANCED_RULES.enemyPickAttempts; attempt++) {
            var rarity = pickWeightedEnemyRarity(config);
            var pool = state.catalog.filter(function (card) {
                return card.card_rarity === rarity && card.card_rarity !== 'stigmatic' && !excluded[String(card.card_id)];
            });
            if (pool.length) { return pool[Math.floor(Math.random() * pool.length)]; }
        }
        var fallback = state.catalog.filter(function (card) {
            return card.card_rarity !== 'stigmatic' && !excluded[String(card.card_id)];
        });
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

    function pickDailyBossCard() {
        var pool = state.catalog.filter(function (card) {
            return card.source_type === 'character' && card.card_rarity !== 'stigmatic';
        });
        if (!pool.length) {
            pool = state.catalog.filter(function (card) { return card.card_rarity !== 'stigmatic'; });
        }
        if (!pool.length) { return null; }
        var date = dailyFreePackDate().replace(/-/g, '');
        var seed = clampInt(date, 1);
        return pool[seed % pool.length];
    }

    function createDailyBoss() {
        return dailyBossEntryFromState();
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
            setCombatMessage(uiText('combat.team_missing_card', 'Alguna carta del equipo ya no existe en la colección.'));
            return false;
        }
        var config = combatDifficultyConfig();
        var excludedEnemies = {};
        var enemyUnits = [0, 1, 2, 3, 4].map(function (index) {
            var enemy = createEnemyCard(config, index, excludedEnemies);
            return enemy ? createCombatUnit(enemy.card, enemy.copy, 'enemy', index) : null;
        }).filter(Boolean);
        if (enemyUnits.length !== 5) {
            setCombatMessage(uiText('combat.no_catalog_for_enemy', 'No hay suficientes cartas en el catálogo para generar rival.'));
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
        pushCombatLog(uiText('combat.training_log', 'Entrenamiento contra {label}.', { label: config.label }));
        pushCombatLog(combatPlayerName() + ' saca una carta.');
        pushCombatLog(uiText('combat.enemy_draw', 'El rival saca una carta.'));
        setCombatMessage(uiText('combat.started', '¡Combate iniciado!'));
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

    function findUnitMove(unit, moveId) {
        if (!unit || !Array.isArray(unit.moves)) { return null; }
        moveId = String(moveId || '');
        for (var i = 0; i < unit.moves.length; i++) {
            if (String(unit.moves[i].id) === moveId) { return unit.moves[i]; }
        }
        return null;
    }

    function moveCooldownRemaining(unit, move) {
        if (!unit || !move || !unit.moveState || !unit.moveState[move.id]) { return 0; }
        return Math.max(0, clampInt(unit.moveState[move.id].cooldownRemaining, 0));
    }

    function setMoveCooldown(unit, move) {
        if (!unit || !move || !unit.moveState || !unit.moveState[move.id]) { return; }
        unit.moveState[move.id].cooldownRemaining = Math.max(0, clampInt(move.cooldown, 0));
    }

    function reduceMoveCooldowns(unit) {
        if (!unit || !unit.moveState) { return; }
        Object.keys(unit.moveState).forEach(function (id) {
            unit.moveState[id].cooldownRemaining = Math.max(0, clampInt(unit.moveState[id].cooldownRemaining, 0) - 1);
        });
    }

    function recalculateCombatStats(unit) {
        if (!unit) { return; }
        var atkBuff = unit.combatBuffs && Number(unit.combatBuffs.atk) || 0;
        var atkDebuff = unit.combatDebuffs && Number(unit.combatDebuffs.atk) || 0;
        var defBuff = unit.combatBuffs && Number(unit.combatBuffs.def) || 0;
        var defDebuff = unit.combatDebuffs && Number(unit.combatDebuffs.def) || 0;
        var atkRatio = Math.max(MOVE_DEBUFF_MIN_RATIO, Math.min(MOVE_BUFF_MAX_RATIO, 1 + atkBuff - atkDebuff));
        var defRatio = Math.max(MOVE_DEBUFF_MIN_RATIO, Math.min(MOVE_BUFF_MAX_RATIO, 1 + defBuff - defDebuff));
        unit.atk = Math.max(1, Math.round(unit.baseAtk * atkRatio));
        unit.def = Math.max(1, Math.round(unit.baseDef * defRatio));
    }

    function applyCombatModifier(unit, statKey, amount, mode, limitRatio) {
        if (!unit || (statKey !== 'atk' && statKey !== 'def')) { return 0; }
        amount = Math.max(0, Number(amount) || 0);
        if (!amount) { return 0; }
        if (mode === 'buff') {
            var currentBuff = unit.combatBuffs && Number(unit.combatBuffs[statKey]) || 0;
            var maxBuffRatio = Math.max(1, Number(limitRatio) || MOVE_BUFF_MAX_RATIO);
            var nextBuff = Math.min(maxBuffRatio - 1, currentBuff + amount);
            unit.combatBuffs[statKey] = nextBuff;
            recalculateCombatStats(unit);
            return Math.max(0, nextBuff - currentBuff);
        }
        var currentDebuff = unit.combatDebuffs && Number(unit.combatDebuffs[statKey]) || 0;
        var minDebuffRatio = Math.max(0, Math.min(1, Number(limitRatio) || MOVE_DEBUFF_MIN_RATIO));
        var nextDebuff = Math.min(1 - minDebuffRatio, currentDebuff + amount);
        unit.combatDebuffs[statKey] = nextDebuff;
        recalculateCombatStats(unit);
        return Math.max(0, nextDebuff - currentDebuff);
    }

    function healCombatUnit(unit, amount) {
        if (!unit) { return 0; }
        amount = Math.max(0, Math.round(Number(amount) || 0));
        if (!amount) { return 0; }
        var before = unit.hp;
        unit.hp = Math.min(unit.maxHp, unit.hp + amount);
        return unit.hp - before;
    }

    function breakCombatShields(unit, amount) {
        if (!unit) { return 0; }
        var before = Math.max(0, clampInt(unit.shields, 0));
        unit.shields = Math.max(0, before - Math.max(0, clampInt(amount, 0)));
        return before - unit.shields;
    }

    function effectiveDef(unit) {
        return Math.round((unit.def || 0) * (unit.defending ? COMBAT_ADVANCED_RULES.defendDefMultiplier : 1));
    }

    function combatDamageForAttackValue(attacker, defender, attackValue) {
        var base = Math.max(1, Math.round((attackValue || 0) - effectiveDef(defender)));
        var rarityDiff = rarityRank(attacker.card && attacker.card.card_rarity) - rarityRank(defender.card && defender.card.card_rarity);
        var multiplier = rarityDiff >= 0
            ? 1 + (rarityDiff * COMBAT_ADVANCED_RULES.rarityAdvantageStep)
            : Math.max(COMBAT_ADVANCED_RULES.rarityDisadvantageMinMultiplier, 1 + (rarityDiff * COMBAT_ADVANCED_RULES.rarityDisadvantageStep));
        var randomExtra = Math.max(1, Math.round(rollStat(COMBAT_ADVANCED_RULES.damageRandomBonusMin, COMBAT_ADVANCED_RULES.damageRandomBonusMax) * multiplier));
        var damage = Math.max(1, base + randomExtra);
        if (state.combat && state.combat.mode === 'daily-boss' && attacker.side === 'enemy' && copyRarity(defender.copy, defender.card) === 'stigmatic') {
            damage = Math.max(1, damage * DAILY_BOSS_STIGMATIC_DAMAGE_MULTIPLIER);
        }
        return damage;
    }

    function combatDamage(attacker, defender) {
        return combatDamageForAttackValue(attacker, defender, attacker && attacker.atk || 0);
    }

    function combatMoveDamage(attacker, defender, move) {
        if (!attacker || !defender || !move) { return 0; }
        var attackValue = attacker.atk || 0;
        if (move.formula === 'average_atk_def') {
            attackValue = Math.round(((attacker.atk || 0) + (attacker.def || 0)) / 2);
        } else if (Number.isFinite(Number(move.power))) {
            attackValue = Math.round((attacker.atk || 0) * Number(move.power));
        }
        return combatDamageForAttackValue(attacker, defender, attackValue);
    }

    function applyMoveEffect(move, attacker, defender, damage) {
        var effect = move && move.effect;
        var log = [];
        if (!effect || !effect.kind) { return log; }
        if (effect.kind === 'debuff_atk') {
            if (applyCombatModifier(defender, 'atk', effect.amount, 'debuff', effect.minRatio) > 0) {
                log.push(defender.card.card_name + ' pierde ATQ. Queda en ATQ ' + defender.atk + '.');
            }
        } else if (effect.kind === 'debuff_def') {
            if (applyCombatModifier(defender, 'def', effect.amount, 'debuff', effect.minRatio) > 0) {
                log.push(defender.card.card_name + ' pierde DEF. Queda en DEF ' + defender.def + '.');
            }
        } else if (effect.kind === 'shield_break') {
            if (Math.random() < Math.max(0, Math.min(1, Number(effect.chance) || 0))) {
                var broken = breakCombatShields(defender, effect.amount || 1);
                if (broken > 0) {
                    log.push('El golpe rompe ' + broken + ' escudo de ' + defender.card.card_name + '.');
                }
            }
        } else if (effect.kind === 'recoil') {
            var recoil = Math.max(1, Math.round(Math.max(0, damage) * Math.max(0, Number(effect.ratio) || 0)));
            applyCombatDamage(attacker, recoil);
            log.push(attacker.card.card_name + ' recibe ' + recoil + ' PS de recoil.');
        } else if (effect.kind === 'lifesteal') {
            var healed = healCombatUnit(attacker, Math.round(Math.max(0, damage) * Math.max(0, Number(effect.ratio) || 0)));
            if (healed > 0) {
                log.push(attacker.card.card_name + ' recupera ' + healed + ' PS.');
            }
        } else if (effect.kind === 'buff_atk_def') {
            var atkBuff = applyCombatModifier(attacker, 'atk', effect.amount, 'buff', effect.maxRatio);
            var defBuff = applyCombatModifier(attacker, 'def', effect.amount, 'buff', effect.maxRatio);
            if (atkBuff > 0 || defBuff > 0) {
                log.push(attacker.card.card_name + ' refuerza su postura: ATQ ' + attacker.atk + ', DEF ' + attacker.def + '.');
            } else {
                log.push(attacker.card.card_name + ' ya esta en el maximo de ' + (move.label || 'la habilidad') + '.');
            }
        }
        return log;
    }

    function copyMoveDefinitions(copy) {
        return normalizeCopyMoveIds(copy && copy.moves).map(function (moveId) {
            return cloneMoveDefinition(MOVE_LIBRARY[moveId]);
        }).filter(Boolean).slice(0, 3);
    }

    function copyHasLearnedMoves(copy) {
        return normalizeCopyMoveIds(copy && copy.moves).length > 0;
    }

    function skillCostMultiplier(card, copy) {
        var rarity = copyRarity(copy, card);
        return SKILL_COST_MULTIPLIER_BY_RARITY[rarity] || 1;
    }

    function skillMnemoneCost(card, copy) {
        return SKILL_BASE_MNEMONES * skillCostMultiplier(card, copy);
    }

    function skillMaterialCost(card, copy) {
        return skillCostMultiplier(card, copy);
    }

    function skillMaterialLabel() {
        return (UPGRADE_MATERIALS[SKILL_MATERIAL_KEY] && UPGRADE_MATERIALS[SKILL_MATERIAL_KEY].label) || SKILL_MATERIAL_KEY || 'material';
    }

    function skillSlotState(copy, slotIndex) {
        var moveIds = normalizeCopyMoveIds(copy && copy.moves);
        var moveId = moveIds[slotIndex] || '';
        return moveId ? cloneMoveDefinition(MOVE_LIBRARY[moveId]) : null;
    }

    function availableSkillMoveIds(copy, slotIndex) {
        var current = normalizeCopyMoveIds(copy && copy.moves);
        var used = {};
        current.forEach(function (moveId, index) {
            if (index !== slotIndex && moveId) {
                used[moveId] = true;
            }
        });
        return Object.keys(MOVE_LIBRARY).filter(function (moveId) {
            return !used[moveId] && moveId !== (current[slotIndex] || '');
        });
    }

    function canAffordSkillRoll(card, copy) {
        if (state.isAdmin) { return true; }
        return currentMnemones() >= skillMnemoneCost(card, copy) && materialStock(SKILL_MATERIAL_KEY) >= skillMaterialCost(card, copy);
    }

    function skillShortageMessage(card, copy) {
        var missing = [];
        var needMnemones = skillMnemoneCost(card, copy);
        var needGlyphs = skillMaterialCost(card, copy);
        var haveMnemones = currentMnemones();
        var haveGlyphs = materialStock(SKILL_MATERIAL_KEY);
        if (haveMnemones < needMnemones) {
            missing.push('Mnemones: ' + formatNumber(needMnemones) + ' / ' + formatNumber(haveMnemones));
        }
        if (haveGlyphs < needGlyphs) {
            missing.push(skillMaterialLabel() + ': ' + needGlyphs + ' / ' + haveGlyphs);
        }
        return missing.length ? ('Recursos insuficientes. Falta: ' + missing.join(' · ') + '.') : 'Recursos insuficientes.';
    }

    function spendSkillRollCost(card, copy) {
        if (state.isAdmin) { return true; }
        if (!canAffordSkillRoll(card, copy)) { return false; }
        var mnemoneCost = skillMnemoneCost(card, copy);
        var materialCost = skillMaterialCost(card, copy);
        if (!state.collection) { loadCollection(); }
        state.collection.currency = normalizeCurrency(state.collection.currency);
        state.collection.materialInventory = normalizeMaterialInventory(state.collection.materialInventory);
        if (state.collection.currency.mnemones < mnemoneCost) { return false; }
        if (clampInt(state.collection.materialInventory[SKILL_MATERIAL_KEY], 0) < materialCost) { return false; }
        state.collection.currency.mnemones = Math.max(0, state.collection.currency.mnemones - mnemoneCost);
        state.collection.materialInventory[SKILL_MATERIAL_KEY] = Math.max(0, clampInt(state.collection.materialInventory[SKILL_MATERIAL_KEY], 0) - materialCost);
        return true;
    }

    function resetCopySkills(copy) {
        if (!copy) { return; }
        copy.moves = [];
        copy.moveRollRarity = normalizeRarity(copy.rarity, 'common');
    }

    function refreshCollectionViews() {
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        renderCombatSetup();
    }

    function applySkillRoll(card, copy, slotIndex) {
        if (!card || !copy) { return false; }
        var selectedSlot = clampInt(slotIndex, 0);
        if (selectedSlot < 0 || selectedSlot >= SKILL_SLOT_COUNT) { return false; }
        var currentMove = skillSlotState(copy, selectedSlot);
        var available = availableSkillMoveIds(copy, selectedSlot);
        if (!available.length) {
            setStatus(uiText('skill.no_new_moves', 'No quedan habilidades nuevas para este hueco.'));
            return false;
        }
        if (!spendSkillRollCost(card, copy)) {
            setStatus(skillShortageMessage(card, copy));
            return false;
        }
        var rolledId = available[Math.floor(Math.random() * available.length)];
        var moveIds = normalizeCopyMoveIds(copy && copy.moves);
        moveIds[selectedSlot] = rolledId;
        copy.moves = moveIds.filter(Boolean).slice(0, SKILL_SLOT_COUNT);
        copy.moveRollRarity = normalizeRarity(copy.rarity, 'common');
        saveCollection();
        playSkillSound();
        refreshCollectionViews();
        showCardModal(card, ownedCopiesForCard(card.card_id));
        setStatus(currentMove
            ? uiText('skill.changed', 'Habilidad cambiada: {move}.', { move: MOVE_LIBRARY[rolledId].label || rolledId })
            : uiText('skill.learned', 'Habilidad aprendida: {move}.', { move: MOVE_LIBRARY[rolledId].label || rolledId }));
        return true;
    }

    function confirmSkillRoll(card, copy, slotIndex) {
        if (!card || !copy) { return false; }
        var currentMove = skillSlotState(copy, slotIndex);
        var available = availableSkillMoveIds(copy, slotIndex);
        if (!available.length) {
            setStatus(uiText('skill.no_new_moves', 'No quedan habilidades nuevas para este hueco.'));
            return false;
        }
        var costMnemones = skillMnemoneCost(card, copy);
        var costMaterial = skillMaterialCost(card, copy);
        var actionLabel = currentMove ? uiText('skill.change_action', 'Cambiar') : uiText('skill.learn_action', 'Aprender');
        var currentLabel = currentMove ? ((currentMove.icon ? currentMove.icon + ' ' : '') + currentMove.label) : uiText('skill.empty_slot', 'Hueco vacío');
        var message = actionLabel + ' habilidad en Hueco ' + (slotIndex + 1) + '. Actual: ' + currentLabel + '. Coste: ' + formatNumber(costMnemones) + ' Mnemones y ' + costMaterial + ' ' + skillMaterialLabel() + '. Resultado aleatorio y sin duplicados. \u00bfSeguir?';
        var messageHtml =
            '<span>' + escapeHtml(actionLabel + ' habilidad en Hueco ' + (slotIndex + 1) + '. Actual: ' + currentLabel + '.') + '</span>' +
            '<span class="hg-confirm-modal__costs">' +
                '<span class="hg-confirm-modal__cost-row">' + materialIconHtml(SKILL_MATERIAL_KEY) + '<b>' + escapeHtml(skillMaterialLabel()) + ':</b> ' + costMaterial + ' <em>(tienes ' + escapeHtml(state.isAdmin ? 'Admin' : String(materialStock(SKILL_MATERIAL_KEY))) + ')</em></span>' +
                '<span class="hg-confirm-modal__cost-row">' + cardGameIconHtml('remembrance', 'Remorias') + '<b>Mnemones:</b> ' + formatNumber(costMnemones) + ' <em>(tienes ' + escapeHtml(state.isAdmin ? 'Admin' : formatNumber(currentMnemones())) + ')</em></span>' +
            '</span>' +
            '<span>' + escapeHtml(uiText('skill.confirm_random', 'Resultado aleatorio y sin duplicados. ¿Seguir?')) + '</span>';
        return confirmGameAction(
            message,
            {
                title: currentMove ? uiText('skill.confirm_title_change', 'Cambiar habilidad') : uiText('skill.confirm_title_learn', 'Aprender habilidad'),
                confirmLabel: actionLabel,
                cancelLabel: uiText('confirm.cancel', 'Cancelar'),
                messageHtml: messageHtml
            },
            function () {
                applySkillRoll(card, copy, slotIndex);
            }
        );
    }

    function healDefendingUnit(unit) {
        var amount = Math.max(1, Math.round(unit.maxHp * COMBAT_ADVANCED_RULES.defendHealRatio));
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

    function dailyBossRewardRarity() {
        var rarity = String((DAILY_BOSS_CARD_REWARD && DAILY_BOSS_CARD_REWARD.rarity) || 'stigmatic');
        return RARITY_ORDER.indexOf(rarity) !== -1 ? rarity : 'stigmatic';
    }

    function rewardDropLabel(item) {
        var material = UPGRADE_MATERIALS[item.key];
        return material && material.label ? material.label : item.key;
    }

    function pickGuaranteedRewardDrop(entries) {
        var roll = Math.random();
        var acc = 0;
        var fallback = null;
        for (var i = 0; i < entries.length; i++) {
            var entry = entries[i];
            if (!entry || !entry.key) { continue; }
            fallback = fallback || entry;
            acc += Math.max(0, Number(entry.chance) || 0);
            if (roll <= acc) {
                return entry;
            }
        }
        return fallback;
    }

    function pushLootMaterialReward(rewards, drop) {
        if (!drop || !drop.key) { return; }
        addMaterial(drop.key, drop.amount);
        rewards.materials.push({
            key: drop.key,
            amount: drop.amount,
            label: rewardDropLabel(drop)
        });
    }

    function awardTrainingVictory() {
        var multiplier = state.combat ? Math.max(1, Number(state.combat.rewardMultiplier) || 1) : 1;
        var base = Math.max(1, clampInt(TRAINING_REWARD_TABLE.base, 5));
        var rollMin = Math.max(1, clampInt(TRAINING_REWARD_TABLE.rollMin, 1));
        var rollMax = Math.max(rollMin, clampInt(TRAINING_REWARD_TABLE.rollMax, 5));
        var reward = clampInt(base * rollStat(rollMin, rollMax) * multiplier, base);
        addMnemones(reward);
        saveCollection();
        renderSummary();
        return reward;
    }

    function awardDailyBossLoot() {
        var guaranteedDrops = normalizeDropConfigList(DAILY_BOSS_LOOT_TABLE.guaranteedMaterialDrop);
        var bonusDrops = normalizeDropConfigList(DAILY_BOSS_LOOT_TABLE.bonusDrops);
        var mnemonesRange = normalizeRewardRangeConfig(DAILY_BOSS_LOOT_TABLE.mnemones, 500, 1200);
        var remoriasRange = normalizeRewardRangeConfig(DAILY_BOSS_LOOT_TABLE.remorias, 120, 420);
        var rewards = {
            mnemones: rollStat(mnemonesRange.min, mnemonesRange.max),
            remorias: rollStat(remoriasRange.min, remoriasRange.max),
            materials: []
        };
        addMnemones(rewards.mnemones);
        addRemorias(rewards.remorias);
        pushLootMaterialReward(rewards, pickGuaranteedRewardDrop(guaranteedDrops));
        bonusDrops.forEach(function (drop) {
            if (Math.random() <= drop.chance) {
                pushLootMaterialReward(rewards, drop);
            }
        });
        return rewards;
    }

    function dailyBossLootText(loot) {
        if (!loot) { return ''; }
        var parts = [];
        if (loot.mnemones) { parts.push('+' + formatNumber(loot.mnemones) + ' Mnemones'); }
        if (loot.remorias) { parts.push('+' + formatNumber(loot.remorias) + ' Remorias'); }
        (loot.materials || []).forEach(function (item) {
            parts.push('+' + item.amount + ' ' + item.label);
        });
        return parts.join(', ');
    }

    function destroyDailyBossTeam() {
        if (!state.combat || state.combat.mode !== 'daily-boss') { return 0; }
        var count = destroyDailyBossCopies(state.combat.riskedCopyIds || []);
        finishDailyBossAttempt(false);
        return count;
    }

    function awardDailyBossVictory() {
        if (!state.combat || state.combat.mode !== 'daily-boss') { return null; }

        if (dailyBossRewardClaimedToday()) {
            return { alreadyClaimed: true, card: null, copy: null };
        }

        if (!state.collection) { loadCollection(); }

        var bossUnit = state.combat.enemy && state.combat.enemy[0];
        if (!bossUnit || !bossUnit.copy) { return null; }

        var baseCard = state.catalogById[String(bossUnit.copy.cardId || '')] || bossUnit.card;
        if (!baseCard) { return null; }

        var rewardCopy = createCardCopy(baseCard, {
            rarity: dailyBossRewardRarity(),
            instanceId: instanceId(),
            obtainedAt: nowIso()
        });
        var casualties = destroyDailyBossDefeatedCards(false);
        var loot = awardDailyBossLoot();

        state.collection.ownedCards.push(rewardCopy);
        markDailyBossRewardClaimed(rewardCopy.instanceId);
        finishDailyBossAttempt(true);

        saveCollection();
        renderSummary();
        renderCollectionTable();
        renderCombatSetup();

        return {
            alreadyClaimed: false,
            card: cardForCopy(baseCard, rewardCopy),
            copy: rewardCopy,
            loot: loot,
            casualties: casualties
        };
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
                //var reward = state.combat.mode === 'daily-boss' ? 0 : awardTrainingVictory();
                var reward = null;
                    if (state.combat.mode === 'daily-boss') {
                        reward = awardDailyBossVictory();

                        state.combat.reward = reward && reward.copy ? reward.copy.instanceId : '';
                        state.combat.result = 'victory';

                        if (reward && reward.alreadyClaimed) {
                            setCombatMessage(uiText('combat.daily_reward_already', 'Victoria contra el Jefe diario. La recompensa diaria ya fue reclamada.'));
                            pushCombatLog(uiText('combat.daily_reward_already_log', 'Has derrotado al Jefe diario, pero la carta Estigmática de hoy ya fue reclamada.'));
                        } else if (reward && reward.card) {
                            setCombatMessage(uiText('combat.daily_victory_reward', 'Victoria contra el Jefe diario. Obtienes {card} Estigmático y botín: {loot}.', { card: reward.card.card_name, loot: dailyBossLootText(reward.loot) }));
                            pushCombatLog(uiText('combat.daily_victory_reward_log', 'Has derrotado al Jefe diario. Obtienes {card} Estigmático.', { card: reward.card.card_name }));
                            pushCombatLog(uiText('combat.daily_loot_log', 'Botín adicional: {loot}.', { loot: dailyBossLootText(reward.loot) }));
                            if (reward.casualties > 0) {
                                pushCombatLog(uiText('combat.daily_casualties_log', 'Cartas caídas durante el desafío: {count}.', { count: reward.casualties }));
                            }
                        } else {
                            setCombatMessage(uiText('combat.daily_victory', 'Victoria contra el Jefe diario.'));
                            pushCombatLog(uiText('combat.daily_survives_log', 'Has derrotado al Jefe diario. Tu equipo sobrevive.'));
                        }
                    } else {
                        reward = awardTrainingVictory();
                        state.combat.reward = reward;
                        state.combat.result = 'victory';

                        setCombatMessage(uiText('combat.training_victory', 'Victoria de entrenamiento. +{reward} Mnemones.', { reward: reward }));
                        pushCombatLog(uiText('combat.training_victory_log', 'Has vencido al equipo rival. Ganas {reward} Mnemones.', { reward: reward }));
                    }
            } else {
                state.combat.result = 'defeat';
                state.combat.reward = 0;
                var destroyed = state.combat.mode === 'daily-boss' ? destroyDailyBossTeam() : 0;
                setCombatMessage(state.combat.mode === 'daily-boss' ? 'Derrota contra el Jefe diario. ' + destroyed + ' cartas perdidas.' : 'Derrota de entrenamiento.');
                if (state.combat.mode === 'daily-boss') {
                    pushCombatLog(uiText('combat.daily_destroy_team_log', 'El Jefe diario consume tu equipo. Pierdes {count} cartas.', { count: destroyed }));
                }
                pushCombatLog(uiText('combat.training_defeat_log', 'Tu equipo ha caído. No pierdes cartas en entrenamiento.'));
            }
            return false;
        }
        if (side === 'enemy') {
            state.combat.enemyActive = living[0];
            pushCombatLog(uiText('combat.enemy_draw', 'El rival saca una carta.'));
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
        if (state.combat.mode === 'daily-boss') {
            updateDailyBossHp(enemy.hp);
        }
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
                    completePlayerTurn();
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
            setCombatMessage(uiText('combat.no_shields', 'Esta carta ya no tiene escudos.'));
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

    function playerUseMove(moveId) {
        if (!state.combat || state.combat.over || state.combatAnimating) { return; }
        var player = activeCombatUnit('player');
        var enemy = activeCombatUnit('enemy');
        if (!player || !enemy) { return; }
        var move = findUnitMove(player, moveId);
        if (!move) {
            setCombatMessage(uiText('combat.move_unavailable', 'Movimiento no disponible.'));
            return;
        }
        if (moveCooldownRemaining(player, move) > 0) {
            setCombatMessage(uiText('combat.move_cooldown', 'Movimiento en recarga.'));
            return;
        }
        if (move.type !== 'damage' && move.type !== 'buff') {
            setCombatMessage(uiText('combat.move_not_implemented', 'Movimiento aún no implementado.'));
            return;
        }
        if ((move.type === 'damage' && move.target !== 'enemy') || (move.type === 'buff' && move.target !== 'self')) {
            setCombatMessage(uiText('combat.move_not_implemented', 'Movimiento aún no implementado.'));
            return;
        }

        setCombatBusy(true);
        player.defending = false;
        setMoveCooldown(player, move);

        if (Math.random() > move.accuracy) {
            pushCombatLog(player.card.card_name + ' usa ' + move.label + ', pero falla.');
            renderCombatBattle();
            window.setTimeout(function () {
                if (!state.combat || state.combat.over) {
                    setCombatBusy(false);
                    return;
                }
                var failedEnemyAction = enemyTurn();
                renderCombatBattle();
                animateEnemyAction(failedEnemyAction);
                finishEnemyAction(failedEnemyAction);
            }, COMBAT_TURN_GAP_MS);
            return;
        }

        var defeatedEnemy = false;
        var defeatedPlayer = false;
        if (move.type === 'damage') {
            var damage = combatMoveDamage(player, enemy, move);
            applyCombatDamage(enemy, damage);
            if (state.combat.mode === 'daily-boss') {
                updateDailyBossHp(enemy.hp);
            }
            pushCombatLog(player.card.card_name + ' usa ' + move.label + ' y causa ' + damage + ' puntos de dano.');
            applyMoveEffect(move, player, enemy, damage).forEach(pushCombatLog);
            defeatedEnemy = enemy.defeated;
            defeatedPlayer = player.defeated;
            if (defeatedEnemy) {
                pushCombatLog(enemy.card.card_name + ' cae.');
            }
            if (defeatedPlayer) {
                if (state.combat.mode === 'daily-boss') {
                    markDailyBossCopyDefeated(player.copy && player.copy.instanceId);
                }
                pushCombatLog(player.card.card_name + ' cae.');
            }
            renderCombatBattle();
            animateCombatAttack('player', 'enemy', damage);
            playMoveVfx(move, 'player', 'enemy');
        } else {
            pushCombatLog(player.card.card_name + ' adopta ' + move.label + '.');
            applyMoveEffect(move, player, enemy, 0).forEach(pushCombatLog);
            renderCombatBattle();
            animateCombatDefend('player');
            playMoveVfx(move, 'player', 'enemy');
        }

        window.setTimeout(function () {
            if (defeatedEnemy && defeatedPlayer && state.combat && !state.combat.over) {
                resolveDefeatedSide('enemy', function () {
                    completePlayerTurn();
                    if (state.combat && !state.combat.over) {
                        var currentPlayer = activeCombatUnit('player');
                        if (currentPlayer && currentPlayer.defeated) {
                            resolveDefeatedSide('player', function () {
                                setCombatBusy(false);
                            });
                            return;
                        }
                    }
                    setCombatBusy(false);
                });
                return;
            }
            if (defeatedEnemy && state.combat && !state.combat.over) {
                resolveDefeatedSide('enemy', function () {
                    completePlayerTurn();
                    setCombatBusy(false);
                });
                return;
            }
            if (defeatedPlayer && state.combat && !state.combat.over) {
                resolveDefeatedSide('player', function () {
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
        }, move.type === 'buff' ? COMBAT_DEFEND_MS : (COMBAT_ATTACK_MS + COMBAT_TURN_GAP_MS));
    }

    function switchPlayerCard(index, consumeTurn) {
        if (!state.combat || state.combat.over || state.combatAnimating) { return; }
        index = clampInt(index, state.combat.playerActive);
        var unit = state.combat.player[index];
        if (!unit || unit.defeated || unit.hp <= 0 || index === state.combat.playerActive) { return; }
        setCombatBusy(true);
        activeCombatUnit('player').defending = false;
        state.combat.playerActive = index;
        pushCombatLog(uiText('combat.switch_log', 'Cambias a {card}.', { card: unit.card.card_name }));
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
        if (state.combat.mode === 'daily-boss') {
            setCombatMessage(uiText('combat.no_daily_flee', 'No puedes huir del Jefe diario.'));
            return;
        }
        state.combat.over = true;
        pushCombatLog(uiText('combat.flee_log', 'Huyes del entrenamiento. Sin coste y sin pérdida de cartas.'));
        setCombatMessage(uiText('combat.flee_done', 'Combate finalizado porque has huido.'));
        renderCombatBattle();
    }

    function enemyTurn() {
        if (!state.combat || state.combat.over) { return null; }
        var enemy = activeCombatUnit('enemy');
        var player = activeCombatUnit('player');
        if (!enemy || !player) { return null; }
        enemy.defending = false;
        var shouldDefend = enemy.shields > 0
            && enemy.hp < enemy.maxHp * COMBAT_ADVANCED_RULES.enemyDefendHpRatio
            && Math.random() < COMBAT_ADVANCED_RULES.enemyDefendChance;
        if (shouldDefend) {
            enemy.shields = Math.max(0, enemy.shields - 1);
            enemy.defending = true;
            var healed = healDefendingUnit(enemy);
            pushCombatLog(enemy.card.card_name + ' gasta 1 escudo, defiende y recupera ' + healed + ' PS.');
            return { type: 'defend', side: 'enemy' };
        }
        var damage = combatDamage(enemy, player);
        applyCombatDamage(player, damage);
        if (state.combat.mode === 'daily-boss' && player.shields > 0 && Math.random() < DAILY_BOSS_SHIELD_BREAK_CHANCE) {
            player.shields = Math.max(0, player.shields - 1);
            pushCombatLog(uiText('combat.daily_shield_break_log', 'El impacto del Jefe diario quiebra 1 escudo de {card}.', { card: player.card.card_name }));
        }
        pushCombatLog(enemy.card.card_name + ' ataca e inflige ' + damage + ' PS.');
        if (player.defeated) {
            markDailyBossCopyDefeated(player.copy && player.copy.instanceId);
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
                    completePlayerTurn();
                    setCombatBusy(false);
                });
                return;
            }
            completePlayerTurn();
            setCombatBusy(false);
        }, action && action.type === 'defend' ? COMBAT_DEFEND_MS : COMBAT_ATTACK_MS);
    }

    function completePlayerTurn() {
        if (state.combat && !state.combat.over) {
            reduceMoveCooldowns(activeCombatUnit('player'));
            renderCombatBattle();
        }
    }

    function setCombatMessage(message) {
        if (els.combatMessage) { els.combatMessage.textContent = message; }
        setStatus(message);
    }

    function pushCombatLog(message) {
        if (!state.combat) { return; }
        if (state.combat.mode === 'daily-boss' && String(message || '').indexOf('No pierdes cartas') !== -1) { return; }
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

    function spawnStandEffect(side, className, count) {
        var stand = combatStand(side);
        if (!stand) { return; }
        count = Math.max(1, clampInt(count, 1));
        for (var i = 0; i < count; i++) {
            var particle = document.createElement('span');
            particle.className = 'hg-combat-particle ' + className;
            particle.style.left = (18 + Math.random() * 64) + '%';
            particle.style.top = (14 + Math.random() * 68) + '%';
            particle.style.setProperty('--hg-particle-dx', ((Math.random() * 2) - 1).toFixed(2));
            particle.style.setProperty('--hg-particle-dy', (-0.8 - Math.random()).toFixed(2));
            particle.style.animationDelay = (Math.random() * 120) + 'ms';
            stand.appendChild(particle);
            window.setTimeout(function (node) {
                return function () { node.remove(); };
            }(particle), 900);
        }
    }

    function spawnAuraEffect(side, className, duration) {
        var stand = combatStand(side);
        if (!stand) { return; }
        var aura = document.createElement('span');
        aura.className = 'hg-combat-aura ' + className;
        stand.appendChild(aura);
        window.setTimeout(function () {
            aura.remove();
        }, duration || 900);
    }

    function shakeCombatScreen(className, duration) {
        var screen = combatScreenElement();
        if (!screen) { return; }
        screen.classList.remove(className);
        void screen.offsetWidth;
        screen.classList.add(className);
        window.setTimeout(function () {
            screen.classList.remove(className);
        }, duration || 520);
    }

    function spawnCombatOrb(fromSide, toSide, className) {
        var screen = combatScreenElement();
        var fromStand = combatStand(fromSide);
        var toStand = combatStand(toSide);
        if (!screen || !fromStand || !toStand) { return; }
        var screenRect = screen.getBoundingClientRect();
        var fromRect = fromStand.getBoundingClientRect();
        var toRect = toStand.getBoundingClientRect();
        var orb = document.createElement('span');
        orb.className = 'hg-combat-orb ' + className;
        var startX = (fromRect.left + (fromRect.width / 2)) - screenRect.left;
        var startY = (fromRect.top + (fromRect.height / 2)) - screenRect.top;
        var endX = (toRect.left + (toRect.width / 2)) - screenRect.left;
        var endY = (toRect.top + (toRect.height / 2)) - screenRect.top;
        orb.style.left = startX + 'px';
        orb.style.top = startY + 'px';
        orb.style.setProperty('--hg-orb-x', (endX - startX).toFixed(1) + 'px');
        orb.style.setProperty('--hg-orb-y', (endY - startY).toFixed(1) + 'px');
        screen.appendChild(orb);
        window.setTimeout(function () {
            orb.remove();
        }, 820);
    }

    function playMoveVfx(move, attackerSide, targetSide) {
        if (!move) { return; }
        if (move.id === 'hero_stance') {
            spawnAuraEffect(attackerSide, 'hg-combat-aura--hero', 980);
            spawnStandEffect(attackerSide, 'hg-combat-particle--hero', 10);
            return;
        }
        if (move.id === 'weakening_blow') {
            spawnStandEffect(targetSide, 'hg-combat-particle--blue', 10);
            return;
        }
        if (move.id === 'armor_breaker') {
            spawnStandEffect(targetSide, 'hg-combat-particle--green', 10);
            return;
        }
        if (move.id === 'discouraging_impact') {
            spawnStandEffect(targetSide, 'hg-combat-particle--gold', 10);
            return;
        }
        if (move.id === 'brutal_strike') {
            shakeCombatScreen('is-combat-shaking', 540);
            spawnStandEffect(targetSide, 'hg-combat-particle--red', 14);
            spawnStandEffect(attackerSide, 'hg-combat-particle--red', 8);
            return;
        }
        if (move.id === 'phantom_leda') {
            spawnStandEffect(targetSide, 'hg-combat-particle--blood', 12);
            window.setTimeout(function () {
                spawnCombatOrb(targetSide, attackerSide, 'hg-combat-orb--blood');
            }, 120);
        }
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

    function showCombatCommandView(view) {
        state.combatCommandView = view === 'actions' || view === 'inventory' ? view : 'root';
        root.classList.toggle('is-combat-subcommand-open', state.combatCommandView !== 'root');
        els.combatCommandViews.forEach(function (node) {
            var active = (node.getAttribute('data-combat-command-view') || 'root') === state.combatCommandView;
            node.hidden = !active;
        });
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
        if (!combatInProgress) { showCombatCommandView('root'); }
        els.combatCommandButtons.forEach(function (button) {
            button.disabled = !active;
        });
        els.combatActions.forEach(function (button) {
            var action = button.getAttribute('data-combat-action') || '';
            button.hidden = state.combatMode === 'daily-boss' && action === 'flee';
            button.disabled = !active
                || (action === 'switch' && livingCombatIndexes('player').length <= 1)
                || (action === 'defend' && (!player || player.shields <= 0))
                || (action === 'flee' && state.combatMode === 'daily-boss');
        });
        renderCombatMoveSlots(player, active);
    }

    function renderCombatMoveSlots(player, active) {
        els.combatExtraActionSlots.forEach(function (button, index) {
            var move = player && Array.isArray(player.moves) ? player.moves[index] : null;
            if (!move) {
                button.textContent = uiText('combat.action_slot', 'Acción {slot}', { slot: index + 1 });
                button.disabled = true;
                button.removeAttribute('data-combat-move');
                button.title = '';
                return;
            }
            var cooldown = moveCooldownRemaining(player, move);
            button.textContent = (move.icon ? move.icon + ' ' : '') + move.label + (cooldown > 0 ? ' (' + cooldown + ')' : '');
            button.disabled = !active || cooldown > 0;
            button.setAttribute('data-combat-move', move.id);
            button.title = move.description || move.label;
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
        node.setAttribute('title', uiText('combat.shields_title', 'Escudos {current} / {max}', { current: unit ? current : 0, max: unit ? max : 0 }));
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
        cancel.innerHTML = '<strong>' + escapeHtml(uiText('combat.cancel', 'Cancelar')) + '</strong><span>' + escapeHtml(uiText('combat.back', 'Volver')) + '</span>';
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
            empty.textContent = uiText('combat.switch_empty', 'No hay cartas disponibles para cambiar.');
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
        title.textContent = victory ? uiText('combat.training_victory_title', '¡Superaste el entrenamiento!') : uiText('combat.training_defeat_title', '¡Te han derrotado!');

        var text = document.createElement('p');

        if (state.combat.mode === 'daily-boss') {
            title.textContent = victory ? uiText('combat.daily_victory_title', 'Jefe diario derrotado') : uiText('combat.daily_defeat_title', 'El Jefe diario vence');

            if (victory) {
                text.textContent = state.combat.reward
                    ? uiText('combat.daily_card_reward_text', 'Obtienes la carta Estigmática del Jefe diario.')
                    : uiText('combat.daily_card_already_text', 'Ya habías reclamado la carta Estigmática de hoy.');
            } else {
                text.textContent = uiText('combat.daily_team_lost_text', 'Las 5 cartas usadas en el intento se han perdido.');
            }
        } else {
            text.textContent = victory
                ? uiText('combat.training_reward_text', 'Recompensa: +{reward} Mnemones.', { reward: clampInt(state.combat.reward, 0) })
                : uiText('combat.training_no_loss_text', 'No pierdes cartas en entrenamiento.');
        }

        var restart = document.createElement('button');
        restart.type = 'button';
        restart.className = 'hg-combat-end__restart';
        restart.textContent = state.combat.mode === 'daily-boss' ? uiText('combat.retry_daily', 'Reintentar jefe diario') : uiText('combat.restart_training', 'Empezar otro combate');
        restart.addEventListener('click', startSelectedCombat);

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

    function renderSelectedModalCard(cardWrap, card, copy) {
        if (!cardWrap) { return; }
        cardWrap.innerHTML = '';
        cardWrap.appendChild(renderCard(card, copy, {
            onSkillSlotClick: function (slotIndex) {
                confirmSkillRoll(card, copy, slotIndex);
            }
        }));
    }

    function renderCard(card, copy, options) {
        options = options || {};
        var rarityKey = copyRarity(copy, card);
        var flippable = !!copy && !options.combatUnit && !options.memoryCompact;
        var article = document.createElement('article');
        article.className = 'hg-card hg-card--' + rarityKey;
        if (flippable) { article.className += ' hg-card--flippable'; }
        if (isFavoriteCopy(copy)) { article.className += ' hg-card--favorite'; }
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
        if (flippable) {
            var flipButton = document.createElement('button');
            flipButton.type = 'button';
            flipButton.className = 'hg-card__flip-btn';
            flipButton.textContent = '\u21BA';
            flipButton.setAttribute('aria-label', uiText('card.flip_label', 'Girar carta'));
            flipButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                playFlipSound();
                article.classList.add('is-flipped');
            });
            head.appendChild(flipButton);
        }
        if (isFavoriteCopy(copy)) {
            var favoriteMark = document.createElement('span');
            favoriteMark.className = 'hg-card__favorite';
            favoriteMark.setAttribute('aria-label', uiText('card.favorite_label', 'Carta favorita'));
            favoriteMark.innerHTML = '&#9733;';
            head.appendChild(favoriteMark);
        }

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
        if (!options.memoryCompact) {
            body.appendChild(text);
        }

        var stats = document.createElement('div');
        stats.className = 'hg-card__stats';
        stats.appendChild(statNode('PS', copy ? copy.hp : card.hp_min + '-' + card.hp_max));
        stats.appendChild(statNode('ATQ', copy ? copy.atk : card.atk_min + '-' + card.atk_max));
        stats.appendChild(statNode('DEF', copy ? copy.def : card.def_min + '-' + card.def_max));

        var frontFace = document.createElement('div');
        frontFace.className = 'hg-card__face hg-card__face--front';
        frontFace.appendChild(head);
        frontFace.appendChild(imageWrap);
        frontFace.appendChild(body);
        if (!options.memoryCompact) {
            frontFace.appendChild(stats);
        }
        article.appendChild(frontFace);

        if (flippable) {
            var backFace = document.createElement('div');
            backFace.className = 'hg-card__face hg-card__face--back';

            var backHead = document.createElement('div');
            backHead.className = 'hg-card__head';
            var backRarity = rarity.cloneNode(true);
            backHead.appendChild(backRarity);
            var backTitle = document.createElement('h4');
            backTitle.textContent = card.card_name || 'Carta';
            backHead.appendChild(backTitle);
            var backButton = document.createElement('button');
            backButton.type = 'button';
            backButton.className = 'hg-card__flip-btn';
            backButton.textContent = '\u21BA';
            backButton.setAttribute('aria-label', uiText('card.flip_label', 'Girar carta'));
            backButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                playFlipSound();
                article.classList.remove('is-flipped');
            });
            backHead.appendChild(backButton);

            var backBody = document.createElement('div');
            backBody.className = 'hg-card__body hg-card__body--back';
            backBody.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
            });
            var backMeta = document.createElement('div');
            backMeta.className = 'hg-card__meta';
            backMeta.innerHTML = '<span class="hg-card__type">' + typeChipHtml(card.source_type, 'hg-card__type-label') + '</span><span class="hg-card__rarity-name">' + escapeHtml(uiText('card.skill_slots', '{count} huecos', { count: SKILL_SLOT_COUNT || 3 })) + '</span>';
            backBody.appendChild(backMeta);

            var movesBlock = document.createElement('div');
            movesBlock.className = 'hg-card__moves';
            movesBlock.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
            });
            movesBlock.innerHTML = '<strong>' + escapeHtml(uiText('card.learned_moves', 'Habilidades aprendidas')) + '</strong>';
            var moves = copyMoveDefinitions(copy);
            for (var moveIndex = 0; moveIndex < SKILL_SLOT_COUNT; moveIndex++) {
                var move = moves[moveIndex];
                var moveLine = document.createElement(options.onSkillSlotClick ? 'button' : 'span');
                var moveTooltip = move
                    ? String(move.description || move.label || '')
                    : 'Hueco vacio. Pulsa para aprender una Habilidad aleatoria.';
                if (options.onSkillSlotClick) {
                    moveLine.type = 'button';
                    moveLine.className = 'hg-card__move-btn';
                    moveLine.addEventListener('click', (function (index) {
                        return function (event) {
                            event.preventDefault();
                            event.stopPropagation();
                            options.onSkillSlotClick(index);
                        };
                    }(moveIndex)));
                }
                moveLine.setAttribute('data-move-tooltip', moveTooltip);
                moveLine.title = moveTooltip;
                moveLine.textContent = move ? ((move.icon ? move.icon + ' ' : '') + move.label) : 'Hueco vacio';
                movesBlock.appendChild(moveLine);
            }
            backBody.appendChild(movesBlock);

            var backStats = stats.cloneNode(true);
            backFace.appendChild(backHead);
            backFace.appendChild(backBody);
            backFace.appendChild(backStats);
            article.appendChild(backFace);
        }
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
        overlay.setAttribute('aria-label', uiText('card.results_label', 'Cartas obtenidas'));

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
        close.textContent = uiText('card.close', 'Cerrar');
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
        prev.setAttribute('aria-label', uiText('card.previous', 'Carta anterior'));
        prev.textContent = '<';

        var next = document.createElement('button');
        next.type = 'button';
        next.className = 'hg-mobile-carousel__nav hg-mobile-carousel__nav--next';
        next.setAttribute('aria-label', uiText('card.next', 'Carta siguiente'));
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
            dot.setAttribute('aria-label', uiText('card.view_card', 'Ver carta {index}', { index: index + 1 }));
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
        close.setAttribute('aria-label', uiText('card.close_card', 'Cerrar carta'));
        close.textContent = '×';
        close.addEventListener('click', closeCardModal);

        panel.appendChild(close);

        var cardWrap = document.createElement('div');
        cardWrap.className = 'hg-card-modal__card';
        renderSelectedModalCard(cardWrap, card, selected);
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
        summary.textContent = uiText('card.variants_summary', 'Variantes obtenidas ({count})', { count: copies.length });
        details.appendChild(summary);

        var actions = document.createElement('div');
        actions.className = 'hg-card-variants__actions';
        var favoriteCount = copies.filter(isFavoriteCopy).length;
        var recycleAll = document.createElement('button');
        recycleAll.type = 'button';
        recycleAll.className = 'hg-recycle-btn hg-icon-action hg-icon-action--wide';
        recycleAll.title = uiText('card.recycle_all_title', 'Desintegrar no favoritas: +{amount} Remorias', { amount: copies.reduce(function (sum, copy) {
            return sum + (isFavoriteCopy(copy) ? 0 : recycleValue(card, copy));
        }, 0) });
        recycleAll.setAttribute('aria-label', recycleAll.title);
        recycleAll.innerHTML = cardGameIconHtml('sell', uiText('card.recycle_all_label', 'Desintegrar todas'));
        recycleAll.disabled = favoriteCount >= copies.length || copies.some(function (copy) {
            return !isFavoriteCopy(copy) && isCopyWorking(copy.instanceId);
        });
        if (favoriteCount >= copies.length) {
            recycleAll.title = uiText('recycle.all_favorites', 'Todas las copias son favoritas.');
            recycleAll.setAttribute('aria-label', recycleAll.title);
        } else if (recycleAll.disabled) {
            recycleAll.title = uiText('recycle.all_memory_blocked', 'Retira primero las cartas que están rememorando.');
            recycleAll.setAttribute('aria-label', recycleAll.title);
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
            var favoriteActive = isFavoriteCopy(copy);
            var item = document.createElement('div');
            item.className = 'hg-card-variants__item' + (working ? ' is-working' : '') + (favoriteActive ? ' is-favorite' : '');
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
                renderSelectedModalCard(cardWrap, card, copy);
            });

            var favorite = document.createElement('button');
            favorite.type = 'button';
            favorite.className = 'hg-favorite-btn hg-icon-action' + (favoriteActive ? ' is-active' : '');
            favorite.title = favoriteActive ? uiText('card.favorite_remove', 'Quitar favorita') : uiText('card.favorite_add', 'Marcar como favorita');
            favorite.setAttribute('aria-label', favorite.title);
            favorite.innerHTML = '<span aria-hidden="true">&#9733;</span>';
            favorite.addEventListener('click', function () {
                if (toggleFavoriteCopy(copy)) {
                    showCardModal(card, ownedCopiesForCard(card.card_id));
                }
            });

            var recycle = document.createElement('button');
            recycle.type = 'button';
            recycle.className = 'hg-recycle-btn hg-recycle-btn--small hg-icon-action';
            recycle.title = uiText('card.recycle_title', 'Desintegrar: +{amount} Remorias', { amount: recycleValue(card, copy) });
            recycle.innerHTML = cardGameIconHtml('sell', uiText('recycle.confirm', 'Desintegrar'));
            recycle.disabled = favoriteActive || working;
            recycle.setAttribute('aria-label', uiText('card.recycle_copy_label', 'Desintegrar esta copia por {amount} Remorias', { amount: recycleValue(card, copy) }));
            if (favoriteActive) {
                recycle.title = uiText('recycle.favorite_blocked', 'Esta copia es favorita y no se puede vender.');
                recycle.setAttribute('aria-label', recycle.title);
            }
            recycle.addEventListener('click', function () {
                recycleCopy(card, copy);
            });

            var upgrade = document.createElement('button');
            upgrade.type = 'button';
            upgrade.className = 'hg-upgrade-btn hg-upgrade-btn--small hg-icon-action';
            upgrade.title = uiText('card.evolve_title', 'Evolucionar');
            upgrade.innerHTML = cardGameIconHtml('evolve', uiText('card.evolve_title', 'Evolucionar'));
            upgrade.disabled = working || !nextRarity(copyRarity(copy, card));
            upgrade.setAttribute('aria-label', uiText('card.evolve_label', 'Evolucionar rareza de esta copia'));
            upgrade.addEventListener('click', function () {
                showRarityUpgradeModal(card, copy);
            });

            var improve = document.createElement('button');
            improve.type = 'button';
            improve.className = 'hg-improve-btn hg-improve-btn--small hg-icon-action';
            improve.title = uiText('card.improve_title', 'Mejorar');
            improve.innerHTML = cardGameIconHtml('upgrade', uiText('card.improve_title', 'Mejorar'));
            improve.disabled = working || qualityScore(copy, card) >= 100;
            improve.setAttribute('aria-label', uiText('card.improve_label', 'Mejorar atributos de esta copia'));
            improve.addEventListener('click', function () {
                showQualityUpgradeModal(card, copy);
            });

            item.appendChild(select);
            item.appendChild(favorite);
            item.appendChild(upgrade);
            item.appendChild(improve);
            item.appendChild(recycle);
            list.appendChild(item);
        });
        details.appendChild(list);
        return details;
    }

    function nextRarity(rarity) {
        var index = RARITY_UPGRADE_ORDER.indexOf(String(rarity || 'common'));
        return index >= 0 && index < RARITY_UPGRADE_ORDER.length - 1 ? RARITY_UPGRADE_ORDER[index + 1] : null;
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
        var balanceText = state.isAdmin ? 'Admin' : formatNumber(currentRemorias());
        var materialText = material
            ? '<span>' + materialIconHtml(materialKey) + '<b>' + escapeHtml(material.label) + '</b><em>' + (state.isAdmin ? 'Admin' : materialStock(materialKey)) + ' / 1</em></span>'
            : '<span><b>Sin objeto ritual</b><em>No requerido</em></span>';
        return '<div class="hg-upgrade-cost">' +
            '<span><b>' + formatNumber(cost) + ' / ' + escapeHtml(balanceText) + ' Remorias</b><em>Coste / saldo</em></span>' +
            materialText +
        '</div>';
    }

    function canPayUpgradeCost(cost, materialKey) {
        return state.isAdmin || (currentRemorias() >= cost && (!materialKey || materialStock(materialKey) >= 1));
    }

    function spendUpgradeCost(cost, materialKey) {
        if (state.isAdmin) { return true; }
        if (!state.collection) { loadCollection(); }
        state.collection.currency = normalizeCurrency(state.collection.currency);
        state.collection.materialInventory = normalizeMaterialInventory(state.collection.materialInventory);
        if (clampInt(state.collection.currency.remorias, 0) < cost) { return false; }
        if (materialKey && clampInt(state.collection.materialInventory[materialKey], 0) < 1) { return false; }
        state.collection.currency.remorias = Math.max(0, clampInt(state.collection.currency.remorias, 0) - cost);
        if (materialKey) {
            state.collection.materialInventory[materialKey] = Math.max(0, clampInt(state.collection.materialInventory[materialKey], 0) - 1);
        }
        return true;
    }

    function startDailyBossCombat(options) {
        options = options || {};
        if (!isCombatContext()) { return false; }
        preloadCombatSounds();
        cleanCombatTeamsAgainstCollection(true);
        var bossState = state.dailyBoss || loadDailyBossState();
        if (!bossState) {
            setCombatMessage(uiText('combat.no_daily_characters', 'No hay personajes disponibles para generar el jefe diario.'));
            return false;
        }
        if (bossState.completed || bossState.hp <= 0 || dailyBossRewardClaimedToday()) {
            setCombatMessage(uiText('combat.daily_completed', 'Desafío diario completado.'));
            renderDailyBossSummary();
            return false;
        }
        var teamIds = validDraftTeam();
        if (teamIds.length !== 5) {
            return promptQuickCombatTeam();
        }
        if (!options.confirmed) {
            return confirmGameAction(
                'El Jefe diario conserva sus PS, no permite huir y destruye las cartas que derrota. Si cae todo tu equipo, pierdes las 5 cartas del intento. ¿Entrar igualmente?',
                { title: 'Jefe diario', confirmLabel: 'Entrar al desafio', cancelLabel: 'Cancelar' },
                function () {
                    startDailyBossCombat({ confirmed: true });
                }
            );
        }
        var playerUnits = teamIds.map(function (id, index) {
            var entry = combatEntryFromCopy(copyByInstanceId(id));
            return entry ? createCombatUnit(entry.card, entry.copy, 'player', index) : null;
        }).filter(Boolean);
        if (playerUnits.length !== 5) {
            setCombatMessage(uiText('combat.team_missing_card', 'Alguna carta del equipo ya no existe en la colección.'));
            return false;
        }
        var boss = createDailyBoss();
        if (!boss) {
            setCombatMessage(uiText('combat.no_daily_characters', 'No hay personajes disponibles para generar el jefe diario.'));
            return false;
        }
        startDailyBossAttempt(teamIds);
        state.combat = {
            mode: 'daily-boss',
            difficultyLabel: 'Jefe diario',
            rewardMultiplier: 0,
            player: playerUnits,
            enemy: [createCombatUnit(boss.card, boss.copy, 'enemy', 0, {
                currentHp: boss.bossState.hp,
                maxHp: boss.bossState.maxHp,
                noShields: true
            })],
            playerActive: 0,
            enemyActive: 0,
            over: false,
            result: '',
            reward: 0,
            riskedCopyIds: teamIds.slice(),
            log: []
        };
        pushCombatLog(uiText('combat.daily_boss_log', 'Jefe diario: {card} emerge como Estigmático.', { card: boss.card.card_name }));
        pushCombatLog(uiText('combat.daily_risk_log', 'Si tu equipo cae, esas 5 cartas se pierden.'));
        pushCombatLog(combatPlayerName() + ' saca una carta.');
        setCombatMessage(uiText('combat.daily_started', 'Jefe diario iniciado. Alto riesgo.'));
        showCombatScreen('battle');
        renderCombatBattle();
        animateCombatEntry('player');
        animateCombatEntry('enemy');
        return true;
    }

    function startSelectedCombat() {
        return state.combatMode === 'daily-boss' ? startDailyBossCombat() : startTrainingCombat();
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

    function worseDuplicateCandidateMap(candidates) {
        if (!state.collection) { loadCollection(); }
        var candidateIds = {};
        (candidates || []).forEach(function (entry) {
            candidateIds[String(entry && entry.copy && entry.copy.instanceId || '')] = true;
        });
        var groups = {};
        (state.collection.ownedCards || []).forEach(function (copy) {
            var card = state.catalogById[String(copy && copy.cardId || '')];
            if (!copy || !card) { return; }
            var cardId = String(card.card_id || copy.cardId || '');
            if (!cardId) { return; }
            if (!groups[cardId]) { groups[cardId] = { card: card, copies: [] }; }
            groups[cardId].copies.push(copy);
        });
        var out = {};
        Object.keys(groups).forEach(function (cardId) {
            var group = groups[cardId];
            if (!group || group.copies.length <= 1) { return; }
            group.copies.slice().sort(function (a, b) {
                return copySortValue(b, group.card) - copySortValue(a, group.card)
                    || String(a.instanceId || '').localeCompare(String(b.instanceId || ''));
            }).slice(1).forEach(function (entry) {
                var id = String(entry.instanceId || '');
                if (candidateIds[id]) { out[id] = true; }
            });
        });
        return out;
    }

    function duplicateFilterLabelHtml() {
        return '<label class="hg-upgrade-filter-check"><input type="checkbox"><span>Solo duplicadas peores</span></label>';
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
            setStatus(uiText('upgrade.rarity_max', 'Esta copia ya está en la rareza máxima.'));
            return;
        }
        var candidates = rarityUpgradeCandidates(targetCard, targetCopy);
        var selected = [];
        var filters = { rarity: 'all', type: 'all', minTotal: 0, duplicatesOnly: false };

        var overlay = document.createElement('div');
        overlay.className = 'hg-upgrade-modal' + (state.mobile ? ' hg-upgrade-modal--mobile' : '');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', uiText('upgrade.rarity_title', 'Evolucionar rareza'));

        var panel = document.createElement('div');
        panel.className = 'hg-upgrade-modal__panel';
        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        var head = document.createElement('div');
        head.className = 'hg-upgrade-modal__head';
        head.innerHTML = '<div><h3>' + escapeHtml(uiText('upgrade.rarity_title', 'Evolucionar rareza')) + '</h3><p>' + escapeHtml(targetCard.card_name) + ' · ' + escapeHtml(RARITY_LABELS[targetRarity] || targetRarity) + ' a ' + escapeHtml(RARITY_LABELS[next] || next) + '</p></div>';
        var close = document.createElement('button');
        close.type = 'button';
        close.textContent = uiText('upgrade.close', 'Cerrar');
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
        var duplicateFilter = document.createElement('span');
        duplicateFilter.innerHTML = duplicateFilterLabelHtml();
        var duplicateCheckbox = duplicateFilter.querySelector('input');
        filterWrap.appendChild(raritySelect);
        filterWrap.appendChild(typeSelect);
        filterWrap.appendChild(totalSelect);
        filterWrap.appendChild(duplicateFilter.firstChild);

        var list = document.createElement('div');
        list.className = 'hg-upgrade-list';

        var actions = document.createElement('div');
        actions.className = 'hg-upgrade-actions';
        var confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'hg-upgrade-confirm';
        confirm.textContent = uiText('upgrade.confirm', 'Evolucionar');
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.textContent = uiText('upgrade.cancel', 'Cancelar');
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

        function visibleCandidateMap() {
            return filters.duplicatesOnly ? worseDuplicateCandidateMap(candidates) : null;
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
            var worseMap = visibleCandidateMap();
            if (worseMap) {
                selected = selected.filter(function (id) { return !!worseMap[String(id)]; });
            }
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
                    && entry.score >= filters.minTotal
                    && (!worseMap || !!worseMap[String(entry.copy.instanceId || '')]);
            });
            list.innerHTML = '';
            if (!rows.length) {
                var empty = document.createElement('p');
                empty.className = 'hg-empty-state';
                empty.textContent = filters.duplicatesOnly
                    ? 'No hay duplicadas peores disponibles con esos filtros.'
                    : 'No hay sacrificios disponibles con esos filtros.';
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
        if (duplicateCheckbox) {
            duplicateCheckbox.addEventListener('change', function () {
                filters.duplicatesOnly = !!duplicateCheckbox.checked;
                renderUpgradeState();
            });
        }
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

    function applyRarityUpgrade(targetCard, targetCopy, selectedIds, options) {
        options = options || {};
        if (isCopyWorking(targetCopy && targetCopy.instanceId)) {
            setStatus(uiText('upgrade.remove_memory_first', 'Retira la carta de la rememoración antes de evolucionarla.'));
            return false;
        }
        var targetRarity = copyRarity(targetCopy, targetCard);
        var next = nextRarity(targetRarity);
        if (!next) { return false; }
        if (copyHasLearnedMoves(targetCopy) && !options.skillResetConfirmed) {
            return confirmGameAction(
                uiText('upgrade.reset_skills_message', 'Esta evolución reinicia todas las habilidades aprendidas de la carta. Si ahora tiene habilidades, pasará a {rarity} sin habilidades. ¿Seguir?', { rarity: RARITY_LABELS[next] || next }),
                {
                    title: uiText('upgrade.reset_skills_title', 'Perder habilidades'),
                    confirmLabel: uiText('upgrade.reset_skills_confirm', 'Sí, evolucionar'),
                    cancelLabel: uiText('upgrade.cancel', 'Cancelar')
                },
                function () {
                    applyRarityUpgrade(targetCard, targetCopy, selectedIds, { skillResetConfirmed: true });
                }
            );
        }
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
            setStatus(uiText('upgrade.need_sacrifices', 'Elige sacrificios suficientes para completar la evolución.'));
            return false;
        }
        if (!state.collection) { loadCollection(); }
        state.collection.currency = normalizeCurrency(state.collection.currency);
        state.collection.materialInventory = normalizeMaterialInventory(state.collection.materialInventory);
        var currentRemoriaStock = clampInt(state.collection.currency.remorias, 0);
        var currentMaterialStock = requiredMaterial ? clampInt(state.collection.materialInventory[requiredMaterial], 0) : 0;
        if (!state.isAdmin && (currentRemoriaStock < upgradeCost || (requiredMaterial && currentMaterialStock < 1))) {
            setStatus(uiText('upgrade.missing_cost', 'Faltan Remorias u objetos rituales para evolucionar.'));
            return false;
        }
        if (window.console && typeof window.console.info === 'function') {
            window.console.info('[HG evolve]', {
                from: targetRarity,
                to: next,
                requiredMaterial: requiredMaterial,
                remoriasBefore: currentRemoriaStock,
                materialBefore: currentMaterialStock
            });
        }
        if (!state.isAdmin) {
            state.collection.currency.remorias = Math.max(0, currentRemoriaStock - upgradeCost);
            if (requiredMaterial) {
                state.collection.materialInventory[requiredMaterial] = Math.max(0, currentMaterialStock - 1);
            }
        }
        if (window.console && typeof window.console.info === 'function') {
            window.console.info('[HG evolve:after-pay]', {
                remoriasAfter: clampInt(state.collection.currency.remorias, 0),
                materialAfter: requiredMaterial ? clampInt(state.collection.materialInventory[requiredMaterial], 0) : null
            });
        }
        var remove = {};
        selected.forEach(function (id) {
            if (byId[String(id)]) { remove[String(id)] = true; }
        });
        retuneCopyStatsForRarity(targetCopy, targetCard, targetRarity, next);
        resetCopySkills(targetCopy);
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
        setStatus(uiText('upgrade.done', 'Rareza evolucionada a {rarity}. Coste: {cost} Remorias.', { rarity: RARITY_LABELS[next] || next, cost: formatNumber(upgradeCost) }));
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
            setStatus(uiText('improve.quality_max', 'Esta copia ya tiene calidad 100%.'));
            return;
        }
        var targetRarity = copyRarity(targetCopy, targetCard);
        var candidates = qualityUpgradeCandidates(targetCard, targetCopy);
        var selected = [];
        var filters = { type: 'all', minTotal: 0, duplicatesOnly: false };

        var overlay = document.createElement('div');
        overlay.className = 'hg-upgrade-modal' + (state.mobile ? ' hg-upgrade-modal--mobile' : '');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', uiText('improve.title', 'Mejorar atributos'));

        var panel = document.createElement('div');
        panel.className = 'hg-upgrade-modal__panel';
        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        var head = document.createElement('div');
        head.className = 'hg-upgrade-modal__head';
        head.innerHTML = '<div><h3>' + escapeHtml(uiText('improve.title', 'Mejorar atributos')) + '</h3><p>' + escapeHtml(targetCard.card_name) + ' · ' + escapeHtml(RARITY_LABELS[targetRarity] || targetRarity) + ' · CAL ' + targetQuality.toFixed(1) + '%</p></div>';
        var close = document.createElement('button');
        close.type = 'button';
        close.textContent = uiText('upgrade.close', 'Cerrar');
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
        var duplicateFilter = document.createElement('span');
        duplicateFilter.innerHTML = duplicateFilterLabelHtml();
        var duplicateCheckbox = duplicateFilter.querySelector('input');
        filterWrap.appendChild(typeSelect);
        filterWrap.appendChild(totalSelect);
        filterWrap.appendChild(duplicateFilter.firstChild);

        var list = document.createElement('div');
        list.className = 'hg-upgrade-list';

        var actions = document.createElement('div');
        actions.className = 'hg-upgrade-actions';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.textContent = uiText('upgrade.cancel', 'Cancelar');
        cancel.addEventListener('click', closeQualityUpgradeModal);
        var confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'hg-upgrade-confirm';
        confirm.textContent = uiText('improve.confirm', 'Mejorar');
        actions.appendChild(cancel);
        actions.appendChild(confirm);

        function selectedEntries() {
            return selected.map(function (id) {
                return candidates.filter(function (entry) {
                    return String(entry.copy.instanceId || '') === String(id);
                })[0] || null;
            }).filter(Boolean);
        }

        function visibleCandidateMap() {
            return filters.duplicatesOnly ? worseDuplicateCandidateMap(candidates) : null;
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
            var worseMap = visibleCandidateMap();
            if (worseMap) {
                selected = selected.filter(function (id) { return !!worseMap[String(id)]; });
            }
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
                    && entry.score >= filters.minTotal
                    && (!worseMap || !!worseMap[String(entry.copy.instanceId || '')]);
            });
            list.innerHTML = '';
            if (!rows.length) {
                var empty = document.createElement('p');
                empty.className = 'hg-empty-state';
                empty.textContent = filters.duplicatesOnly
                    ? 'No hay duplicadas peores disponibles con esos filtros.'
                    : 'No hay sacrificios disponibles con esos filtros.';
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
        if (duplicateCheckbox) {
            duplicateCheckbox.addEventListener('change', function () {
                filters.duplicatesOnly = !!duplicateCheckbox.checked;
                renderImproveState();
            });
        }
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
            setStatus(uiText('improve.remove_memory_first', 'Retira la carta de la rememoración antes de mejorarla.'));
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
            setStatus(uiText('improve.need_sacrifices', 'Elige al menos una carta para mejorar atributos.'));
            return false;
        }
        var projected = projectedQualityAfterSacrifices(targetQuality, selected);
        if (projected <= targetQuality) {
            setStatus(uiText('improve.no_gain', 'Esos sacrificios no mejoran la calidad.'));
            return false;
        }
        var improveCost = upgradeMnemoneCost(targetCard, targetCopy);
        if (!spendUpgradeCost(improveCost, '')) {
            setStatus(uiText('improve.missing_cost', 'Faltan Remorias para mejorar atributos.'));
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
        setStatus(uiText('improve.done', 'Atributos mejorados a CAL {quality}%. Coste: {cost} Remorias.', { quality: qualityScore(targetCopy, targetCard).toFixed(1), cost: formatNumber(improveCost) }));
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

    function favoriteCopyMap() {
        if (!state.collection) { loadCollection(); }
        if (!Array.isArray(state.collection.favoriteCopyIds)) { state.collection.favoriteCopyIds = []; }
        var map = {};
        state.collection.favoriteCopyIds.forEach(function (id) {
            id = String(id || '');
            if (id) { map[id] = true; }
        });
        return map;
    }

    function isFavoriteCopy(copyOrId) {
        var copyId = typeof copyOrId === 'object' && copyOrId ? copyOrId.instanceId : copyOrId;
        return !!copyId && !!favoriteCopyMap()[String(copyId)];
    }

    function toggleFavoriteCopy(copy) {
        if (!copy || !copy.instanceId) { return false; }
        if (!state.collection) { loadCollection(); }
        var copyId = String(copy.instanceId || '');
        var owned = (state.collection.ownedCards || []).some(function (item) {
            return String(item.instanceId || '') === copyId;
        });
        if (!owned) {
            setStatus(uiText('recycle.favorite_only_owned', 'Solo puedes marcar como favorita una copia que tengas.'));
            return false;
        }
        var map = favoriteCopyMap();
        if (map[copyId]) {
            state.collection.favoriteCopyIds = state.collection.favoriteCopyIds.filter(function (id) {
                return String(id || '') !== copyId;
            });
        } else {
            state.collection.favoriteCopyIds.push(copyId);
        }
        saveCollection();
        renderCollectionTable();
        renderBulkSellPreview();
        setStatus(map[copyId] ? uiText('recycle.favorite_removed', 'Copia retirada de favoritas.') : uiText('recycle.favorite_added', 'Copia marcada como favorita.'));
        return true;
    }

    function recycleCopy(card, copy, confirmed) {
        if (!copy || !copy.instanceId) { return false; }
        if (isFavoriteCopy(copy)) {
            setStatus(uiText('recycle.favorite_blocked', 'Esta copia es favorita y no se puede vender.'));
            return false;
        }
        if (isCopyWorking(copy.instanceId)) {
            setStatus(uiText('recycle.remove_memory_first', 'Retira la carta de la rememoración antes de venderla.'));
            return false;
        }
        var copies = ownedCopiesForCard(card.card_id);
        var rarity = copyRarity(copy, card);
        if ((rarity === 'legendary' || rarity === 'mythic' || rarity === 'stigmatic') && !confirmed) {
            return confirmGameAction(
                uiText('recycle.confirm_single', 'Vas a desintegrar una copia {rarity}.', { rarity: (RARITY_LABELS[rarity] || rarity).toLowerCase() }),
                { title: uiText('recycle.single_title', 'Desintegrar carta'), confirmLabel: uiText('recycle.confirm', 'Desintegrar') },
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
        addRemorias(gained);
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
        setStatus(uiText('recycle.single_done', 'Copia desintegrada. +{gained} Remorias.{extra}', { gained: gained, extra: removedFromTeams ? ' Retirada de ' + removedFromTeams + ' hueco(s) de equipo.' : '' }));
        return true;
    }

    function recycleDuplicateCopies(card, confirmed) {
        var copies = sortedCopies(ownedCopiesForCard(card.card_id), card);
        if (copies.length <= 1) {
            setStatus(uiText('recycle.no_duplicates', 'No hay duplicadas que desintegrar.'));
            return false;
        }
        var keep = copies[0];
        var recycled = copies.slice(1).filter(function (copy) { return !isFavoriteCopy(copy); });
        if (!recycled.length) {
            setStatus(uiText('recycle.no_sellable_duplicates', 'No hay duplicadas vendibles: las duplicadas son favoritas.'));
            return false;
        }
        if (recycled.some(function (copy) { return isCopyWorking(copy.instanceId); })) {
            setStatus(uiText('recycle.duplicates_memory_blocked', 'Retira primero las duplicadas que están rememorando.'));
            return false;
        }
        var gained = recycled.reduce(function (sum, copy) { return sum + recycleValue(card, copy); }, 0);
        if (!confirmed) {
            return confirmGameAction(
                'Se conservara la mejor copia y se desintegraran ' + recycled.length + ' duplicadas por ' + gained + ' Remorias.',
                { title: uiText('recycle.duplicates_title', 'Desintegrar duplicadas'), confirmLabel: uiText('recycle.confirm', 'Desintegrar') },
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
        addRemorias(gained);
        saveCollection();
        playDustSound();
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        renderCombatSetup();
        showCardModal(card, ownedCopiesForCard(card.card_id));
        setStatus(uiText('recycle.duplicates_done', 'Duplicadas desintegradas. +{gained} Remorias.{extra}', { gained: gained, extra: removedFromTeams ? ' Retiradas de ' + removedFromTeams + ' hueco(s) de equipo.' : '' }));
        return true;
    }

    function recycleAllCopies(card, confirmed) {
        var copies = sortedCopies(ownedCopiesForCard(card.card_id), card);
        var sellable = copies.filter(function (copy) { return !isFavoriteCopy(copy); });
        if (!copies.length) {
            setStatus(uiText('recycle.no_copies', 'No hay copias que desintegrar.'));
            return false;
        }
        if (!sellable.length) {
            setStatus(uiText('recycle.all_favorites', 'Todas las copias son favoritas.'));
            return false;
        }
        if (sellable.some(function (copy) { return isCopyWorking(copy.instanceId); })) {
            setStatus(uiText('recycle.all_memory_blocked', 'Retira primero las cartas que están rememorando.'));
            return false;
        }
        var gained = sellable.reduce(function (sum, copy) { return sum + recycleValue(card, copy); }, 0);
        if (!confirmed) {
            return confirmGameAction(
                'Se desintegraran ' + sellable.length + ' copias no favoritas de esta carta por ' + gained + ' Remorias.',
                { title: uiText('recycle.all_title', 'Desintegrar todas'), confirmLabel: uiText('recycle.confirm', 'Desintegrar') },
                function () { recycleAllCopies(card, true); }
            );
        }
        var remove = {};
        sellable.forEach(function (copy) {
            remove[String(copy.instanceId)] = true;
        });
        var removedFromTeams = removeCopiesFromCombatTeams(remove);
        state.collection.ownedCards = state.collection.ownedCards.filter(function (copy) {
            return !remove[String(copy.instanceId)];
        });
        addRemorias(gained);
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
        setStatus(uiText('recycle.all_done', 'Copias no favoritas desintegradas. +{gained} Remorias.{extra}', { gained: gained, extra: removedFromTeams ? ' Retirada de ' + removedFromTeams + ' hueco(s) de equipo.' : '' }));
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
        var protectedCount = 0;

        (state.collection.ownedCards || []).forEach(function (copy) {
            var card = state.catalogById[String(copy.cardId || '')];
            if (!card || copyRarity(copy, card) !== rarity) { return; }
            if (isFavoriteCopy(copy)) {
                protectedCount += 1;
                return;
            }
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

        return { count: count, gained: gained, remove: remove, kept: kept, keepBest: keepBest, protectedCount: protectedCount };
    }

    function renderBulkSellPreview() {
        if (!els.bulkSellRarity || !els.bulkSellBtn || !els.bulkSellPreview) { return; }
        var rarity = els.bulkSellRarity.value || 'common';
        var keepBest = !els.bulkSellKeepBest || els.bulkSellKeepBest.checked;
        var stats = bulkSellStats(rarity, keepBest);
        var label = RARITY_LABELS[rarity] || rarity;
        var previewParts = [
            '<span>' + stats.count + ' cartas ' + escapeHtml(label.toLowerCase()) + '</span>',
            '<span>+' + stats.gained + ' Remorias</span>'
        ];
        if (stats.keepBest && stats.kept) {
            previewParts.push('<span>conserva ' + stats.kept + ' mejores</span>');
        }
        if (stats.protectedCount) {
            previewParts.push('<span>' + stats.protectedCount + ' favoritas protegidas</span>');
        }
        els.bulkSellPreview.innerHTML = previewParts.join('');
        els.bulkSellBtn.disabled = stats.count <= 0;
    }

    function sellCardsByRarity(confirmed) {
        if (!els.bulkSellRarity) { return false; }
        if (!state.catalog.length) {
            setStatus(uiText('recycle.wait_catalog', 'Espera a que cargue el catálogo.'));
            return false;
        }
        var rarity = els.bulkSellRarity.value || 'common';
        if (RARITY_ORDER.indexOf(rarity) === -1) {
            setStatus(uiText('recycle.invalid_rarity', 'Rareza no válida.'));
            return false;
        }

        var keepBest = !els.bulkSellKeepBest || els.bulkSellKeepBest.checked;
        var stats = bulkSellStats(rarity, keepBest);
        if (stats.count <= 0) {
            setStatus(stats.protectedCount ? 'Las cartas favoritas de esa rareza estan protegidas.' : (stats.keepBest ? 'No tienes duplicadas de esa rareza para vender.' : 'No tienes cartas de esa rareza para vender.'));
            renderBulkSellPreview();
            return false;
        }

        var label = RARITY_LABELS[rarity] || rarity;
        var keepText = stats.keepBest ? ' Se conservará la copia con mayor PS + ATQ + DEF de cada carta.' : '';
        if (!confirmed) {
            return confirmGameAction(
                'Vas a vender ' + stats.count + ' cartas de rareza ' + label.toLowerCase() + ' por ' + stats.gained + ' Remorias.' + keepText + ' Esta accion no se puede deshacer.',
                { title: 'Vender cartas', confirmLabel: 'Vender' },
                function () { sellCardsByRarity(true); }
            );
        }

        var removedFromTeams = removeCopiesFromCombatTeams(stats.remove);
        state.collection.ownedCards = state.collection.ownedCards.filter(function (copy) {
            return !stats.remove[String(copy.instanceId)];
        });
        addRemorias(stats.gained);
        saveCollection();
        playDustSound();
        closeCardModal();
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        renderCombatSetup();
        setStatus(uiText('recycle.sale_done', 'Venta completada. +{gained} Remorias.{extra}', { gained: stats.gained, extra: removedFromTeams ? ' Retiradas de ' + removedFromTeams + ' hueco(s) de equipo.' : '' }));
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
        var exportData = JSON.parse(JSON.stringify(state.collection));
        exportData.combatTeams = normalizeCombatTeams(loadCombatTeams());
        exportData.combatProfile = normalizeCombatProfile(loadCombatProfile());
        var blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'hg_card_collection_v2.json';
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.setTimeout(function () { URL.revokeObjectURL(url); }, 250);
        setStatus(uiText('collection.export_done', 'Colección y equipos exportados a JSON.'));
    }

    function validateCollection(data) {
        if (!data || typeof data !== 'object' || [1, 2].indexOf(Number(data.version)) === -1 || !Array.isArray(data.ownedCards)) {
            throw new Error('El JSON no tiene una colección compatible.');
        }
        if (data.ownedCards.length > 10000) {
            throw new Error('La colección importada es demasiado grande.');
        }
        var out = {
            version: 2,
            createdAt: typeof data.createdAt === 'string' && data.createdAt ? data.createdAt : nowIso(),
            updatedAt: nowIso(),
            favoriteCopyIds: Array.isArray(data.favoriteCopyIds) ? data.favoriteCopyIds.map(function (id) {
                return String(id || '').slice(0, 80);
            }).filter(Boolean) : [],
            ownedCards: [],
            workAssignments: normalizeWorkAssignments(data.workAssignments),
            workPendingRewards: normalizeWorkPendingRewards(data.workPendingRewards),
            currency: normalizeCurrency(data.currency),
            packInventory: normalizePackInventory(data.packInventory),
            dailyShopPackPurchases: normalizeDailyShopPackPurchases(data.dailyShopPackPurchases),
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
            copy.moves = normalizeCopyMoveIds(item.moves);
            copy.moveRollRarity = highestMoveCheckpoint(item.moveRollRarity || item.movesRarityCheckpoint || 'common');
            if (card) {
                ensureCopyMovesForRarity(copy, card, copy.rarity, !item.moveRollRarity && !item.movesRarityCheckpoint);
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
        if (data.favoriteCardId) {
            var legacyCardId = String(data.favoriteCardId || '');
            out.favoriteCopyIds = out.favoriteCopyIds.filter(function (id) {
                var favoriteCopy = out.ownedCards.find(function (copy) {
                    return String(copy.instanceId || '') === String(id || '');
                });
                return !favoriteCopy || String(favoriteCopy.cardId || '') !== legacyCardId;
            });
            var legacyFavorites = out.ownedCards.filter(function (copy) {
                return String(copy.cardId || '') === legacyCardId;
            });
            if (legacyFavorites.length) {
                var legacyCard = state.catalogById[legacyCardId] || null;
                legacyFavorites.sort(function (a, b) {
                    return copySortValue(b, legacyCard) - copySortValue(a, legacyCard)
                        || String(a.instanceId || '').localeCompare(String(b.instanceId || ''));
                });
                out.favoriteCopyIds.push(String(legacyFavorites[0].instanceId || ''));
            }
        }
        var ownedIds = {};
        out.ownedCards.forEach(function (copy) { ownedIds[String(copy.instanceId || '')] = true; });
        var favoriteSeen = {};
        out.favoriteCopyIds = out.favoriteCopyIds.filter(function (id) {
            id = String(id || '');
            if (!id || !ownedIds[id] || favoriteSeen[id]) { return false; }
            favoriteSeen[id] = true;
            return true;
        });
        return out;
    }

    function importCollection(json) {
        try {
            var payload = JSON.parse(json);
            state.collection = validateCollection(payload);
            saveCollection();
            if (payload && payload.combatTeams) {
                state.combatTeams = normalizeCombatTeams(payload.combatTeams);
                state.activeCombatTeam = state.combatTeams.activeTeam;
                state.draftCombatTeam = state.combatTeams.teams[state.activeCombatTeam].cards.slice();
                cleanCombatTeamsAgainstCollection(true);
                saveCombatTeams();
            }
            if (payload && payload.combatProfile) {
                state.combatProfile = normalizeCombatProfile(payload.combatProfile);
                saveCombatProfile();
            }
            renderPackResults([]);
            renderSummary();
            renderPackInventory();
            renderCollectionTable();
            renderCombat();
            setStatus(uiText('collection.import_done', 'Colección importada correctamente.'));
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
        state.shopState = createShopState();
        try { window.localStorage.removeItem(STORAGE_KEY); } catch (e) { saveCollection(); }
        try { window.localStorage.removeItem(LEGACY_STORAGE_KEY); } catch (e1) {}
        try { window.localStorage.removeItem(CARD_SHOP_STATE_KEY); } catch (e2) { saveShopState(); }
        try { window.localStorage.removeItem(LEGACY_FREE_REWARDS_KEY); } catch (e3) {}
        renderPackResults([]);
        renderSummary();
        renderDailyCounter();
        renderPackInventory();
        renderCollectionTable();
        setStatus(uiText('collection.reset_done', 'Colección local borrada.'));
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
                if (target !== 'combat') {
                    interruptDailyBossCombat(true);
                }
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
        if (els.collectionHasMovesFilter) {
            els.collectionHasMovesFilter.addEventListener('change', function () {
                state.collectionHasMovesOnly = !!els.collectionHasMovesFilter.checked;
                state.collectionPage = 1;
                renderCollectionTable();
            });
        }
        if (els.collectionInTeamFilter) {
            els.collectionInTeamFilter.addEventListener('change', function () {
                state.collectionInTeamOnly = !!els.collectionInTeamFilter.checked;
                state.collectionPage = 1;
                renderCollectionTable();
            });
        }
        if (els.collectionWorkingFilter) {
            els.collectionWorkingFilter.addEventListener('change', function () {
                state.collectionWorkingOnly = !!els.collectionWorkingFilter.checked;
                state.collectionPage = 1;
                renderCollectionTable();
            });
        }
        if (els.collectionNameFilter) {
            els.collectionNameFilter.addEventListener('input', function () {
                state.collectionSearch = normalizeSearchText(els.collectionNameFilter.value);
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
        els.combatModeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (state.combat && !state.combat.over) {
                    setCombatMessage(uiText('combat.finish_before_mode', 'Termina el combate actual antes de cambiar de modo.'));
                    return;
                }
                if (state.combat && state.combat.over) {
                    state.combat = null;
                }
                state.combatMode = button.getAttribute('data-combat-mode') === 'daily-boss' ? 'daily-boss' : 'training';
                updateCombatModeButtons();
                setCombatMessage(state.combatMode === 'daily-boss'
                    ? 'Jefe diario: si tu equipo cae, pierdes esas 5 cartas.'
                    : 'Entrenamiento seleccionado.');
                renderCombatSetup();
                renderCombatBattle();
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
        els.combatTeamNames.forEach(function (input) {
            input.addEventListener('input', function () {
                loadCombatTeams();
                var team = state.combatTeams.teams[state.activeCombatTeam];
                if (!team) { return; }
                team.name = String(input.value || '').slice(0, 40);
                saveCombatTeams();
                renderCombatTeamSelect();
                renderCombatTeamPreview();
            });
            input.addEventListener('blur', function () {
                loadCombatTeams();
                var team = state.combatTeams.teams[state.activeCombatTeam];
                if (!team) { return; }
                team.name = combatTeamDisplayName(team, state.activeCombatTeam);
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
        if (els.combatSort) {
            els.combatSort.addEventListener('change', function () {
                state.combatSort = els.combatSort.value || 'quality';
                renderCombatCardList();
            });
        }
        if (els.combatStart) { els.combatStart.addEventListener('click', startSelectedCombat); }
        if (els.workClaimBtn) { els.workClaimBtn.addEventListener('click', function () { claimWorkRewards(); }); }
        els.combatCommandButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (button.disabled) { return; }
                showCombatCommandView(button.getAttribute('data-combat-command') || 'root');
            });
        });
        els.combatCommandBackButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                showCombatCommandView('root');
            });
        });
        els.combatActions.forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-combat-action') || '';
                showCombatCommandView('root');
                if (action === 'attack') { playerAttack(); }
                if (action === 'defend') { playerDefend(); }
                if (action === 'switch' && els.combatBench) {
                    renderCombatBench();
                    els.combatBench.hidden = false;
                }
                if (action === 'flee') { fleeCombat(); }
            });
        });
        els.combatExtraActionSlots.forEach(function (button) {
            button.addEventListener('click', function () {
                if (button.disabled) { return; }
                var moveId = button.getAttribute('data-combat-move') || '';
                if (!moveId) { return; }
                showCombatCommandView('root');
                playerUseMove(moveId);
            });
        });
    }

    function startShopStateTimer() {
        if (state.rewardsTimer) { return; }
        state.rewardsTimer = window.setInterval(function () {
            syncShopState();
            renderDailyCounter();
            renderPackInventory();
            renderWorkBench();
        }, 30000);
    }

    function bindEvents() {
        bindMobilePanels();
        bindCollectionControls();
        document.addEventListener('click', function (event) {
            var packButton = event.target.closest('[data-pack-kind]');
            if (!packButton || !root.contains(packButton)) { return; }
            event.preventDefault();
            openPack(packButton.getAttribute('data-pack-kind') || 'standard');
        });
        document.addEventListener('click', function (event) {
            var openAllButton = event.target.closest('[data-pack-open-all]');
            if (!openAllButton || !root.contains(openAllButton)) { return; }
            event.preventDefault();
            openAllPacks();
        });
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
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-shop-buy-exchange-remorias]');
            if (!button || !root.contains(button)) { return; }
            event.preventDefault();
            buyRemoriaExchange(button.getAttribute('data-shop-buy-exchange-remorias') || 0);
        });
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-shop-claim-daily-gift]');
            if (!button || !root.contains(button)) { return; }
            event.preventDefault();
            claimShopDailyGift(button.getAttribute('data-shop-claim-daily-gift') || '');
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
                    setStatus(uiText('collection.read_json_error', 'No se pudo leer el archivo JSON.'));
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
    window.addEventListener('beforeunload', function () {
        interruptDailyBossCombat(false);
    });
    bindEvents();
    loadGameRules().then(function (rulesPayload) {
        if (!rulesPayload) { return []; }
        decorateIconNavigation();
        loadCollection();
        renderMobileSwitchPrompt();
        renderDailyCounter();
        renderPackInventory();
        startShopStateTimer();
        return loadCatalog();
    });

    window.hgGameCards = {
        loadCatalog: loadCatalog,
        loadGameRules: loadGameRules,
        loadCollection: loadCollection,
        saveCollection: saveCollection,
        openPack: openPack,
        pickRarity: pickRarity,
        pickCardByRarity: pickCardByRarity,
        createCardCopy: createCardCopy,
        rollStat: rollStat,
        renderPackResults: renderPackResults,
        renderCollectionTable: renderCollectionTable,
        exportCollection: exportCollection,
        importCollection: importCollection,
        resetCollection: resetCollection,
        addPack: addPack,
        buyPack: buyPack,
        currentRemorias: currentRemorias,
        addRemorias: addRemorias,
        resetDailyBossState: resetDailyBossState,
        recycleCopy: recycleCopy,
        recycleDuplicateCopies: recycleDuplicateCopies,
        recycleAllCopies: recycleAllCopies,
        sellCardsByRarity: sellCardsByRarity
    };
})();
