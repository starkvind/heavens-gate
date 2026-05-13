(function () {
    'use strict';

    var STORAGE_KEY = 'hg_card_collection_v1';
    var FREE_REWARDS_KEY = 'hg_card_free_rewards_v1';
    var COLLECTION_MODE_KEY = 'hg_card_collection_mode_v1';
    var COLLECTION_PAGE_SIZE_KEY = 'hg_card_collection_page_size_v1';
    var STARTING_MNEMONES = 500;
    var MAX_MNEMONES = 9999999;
    var PACK_SIZE = 5;
    var FREE_PACK_INTERVAL_MS = 10 * 60 * 1000;
    var FREE_PACK_CAP = 10;
    var FREE_MNEMONES_INTERVAL_MS = 60 * 60 * 1000;
    var FREE_MNEMONES_AMOUNT = 100;
    var FREE_MNEMONES_CAP = 1000;
    var PACK_KINDS = ['standard', 'echoes', 'magic', 'characters', 'lineage', 'essence', 'powers', 'chronicles', 'relics', 'omens'];
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
        omens: 650
    };
    var RECYCLE_VALUES = { common: 5, unusual: 20, rare: 50, epic: 250, legendary: 500, mythic: 1000 };
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
        omens: { common: 0, unusual: 0, rare: 70, epic: 21, legendary: 7, mythic: 2 }
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
        omens: 'Sobre de presagios'
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
        discipline: 'Disciplina'
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
        power: '✨',
        totem: '🪶',
        gift: '🎁',
        rite: '🕯️',
        discipline: '🩸'
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
        catalog: [],
        catalogById: {},
        freeRewards: null,
        rewardsTimer: null,
        collection: null,
        table: null
    };

    var els = {
        packButtons: Array.prototype.slice.call(document.querySelectorAll('[data-pack-kind]')),
        shopButtons: Array.prototype.slice.call(document.querySelectorAll('[data-buy-pack]')),
        packStocks: Array.prototype.slice.call(document.querySelectorAll('[data-pack-stock]')),
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
        mobilePanels: Array.prototype.slice.call(document.querySelectorAll('[data-mobile-panel]'))
    };

    function setStatus(message) {
        if (els.statusText) {
            els.statusText.textContent = message;
        }
    }

    function nowIso() {
        return new Date().toISOString();
    }

    function clampInt(value, fallback) {
        var n = Number(value);
        if (!Number.isFinite(n)) { return fallback; }
        return Math.round(n);
    }

    function createEmptyCollection() {
        var now = nowIso();
        return {
            version: 1,
            createdAt: now,
            updatedAt: now,
            ownedCards: [],
            currency: { mnemones: STARTING_MNEMONES },
            packInventory: normalizePackInventory({})
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
        var now = Date.now();
        return {
            version: 1,
            standardPacks: 1,
            lastPackAt: now,
            lastMnemonesAt: now
        };
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
        state.freeRewards = {
            version: 1,
            standardPacks: Math.max(0, Math.min(FREE_PACK_CAP, clampInt(data.standardPacks, 0))),
            lastPackAt: normalizeTimestamp(data.lastPackAt, Date.now()),
            lastMnemonesAt: normalizeTimestamp(data.lastMnemonesAt, Date.now())
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
        var now = Date.now();
        var changed = false;
        var gainedPacks = 0;
        var gainedMnemones = 0;

        if (rewards.standardPacks < FREE_PACK_CAP) {
            var packTicks = Math.floor(Math.max(0, now - rewards.lastPackAt) / FREE_PACK_INTERVAL_MS);
            if (packTicks > 0) {
                gainedPacks = Math.min(packTicks, FREE_PACK_CAP - rewards.standardPacks);
                rewards.standardPacks += gainedPacks;
                rewards.lastPackAt = rewards.standardPacks >= FREE_PACK_CAP
                    ? now
                    : rewards.lastPackAt + (packTicks * FREE_PACK_INTERVAL_MS);
                changed = true;
            }
        }

        var mnemonesTicks = Math.floor(Math.max(0, now - rewards.lastMnemonesAt) / FREE_MNEMONES_INTERVAL_MS);
        if (mnemonesTicks > 0) {
            gainedMnemones = Math.min(mnemonesTicks * FREE_MNEMONES_AMOUNT, FREE_MNEMONES_CAP);
            rewards.lastMnemonesAt = gainedMnemones >= FREE_MNEMONES_CAP
                ? now
                : rewards.lastMnemonesAt + (mnemonesTicks * FREE_MNEMONES_INTERVAL_MS);
            changed = true;
            if (gainedMnemones > 0) {
                addMnemones(gainedMnemones);
                saveCollection();
            }
        }

        if (changed) { saveFreeRewards(); }
        return { packs: gainedPacks, mnemones: gainedMnemones };
    }

    function freeStandardPacks() {
        if (state.isAdmin) {
            return Infinity;
        }
        syncFreeRewards();
        return Math.max(0, Math.min(FREE_PACK_CAP, clampInt((state.freeRewards || {}).standardPacks, 0)));
    }

    function nextFreePackMs() {
        if (state.isAdmin) { return 0; }
        var rewards = loadFreeRewards();
        if (rewards.standardPacks >= FREE_PACK_CAP) { return 0; }
        return Math.max(0, FREE_PACK_INTERVAL_MS - (Date.now() - rewards.lastPackAt));
    }

    function formatDuration(ms) {
        var total = Math.max(0, Math.ceil(ms / 1000));
        var minutes = Math.floor(total / 60);
        var seconds = total % 60;
        return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    }

    function renderDailyCounter() {
        if (!els.dailyPacksCounter) { return; }
        if (state.isAdmin) {
            els.dailyPacksCounter.textContent = 'Admin';
            return;
        }
        var free = freeStandardPacks();
        els.dailyPacksCounter.textContent = String(free) + ' / ' + FREE_PACK_CAP;
        var next = nextFreePackMs();
        els.dailyPacksCounter.title = free >= FREE_PACK_CAP ? 'Máximo de sobres gratis alcanzado' : 'Siguiente sobre gratis en ' + formatDuration(next);
    }

    function normalizePackInventory(inventory) {
        var out = {};
        PACK_KINDS.forEach(function (kind) {
            out[kind] = 0;
        });
        if (!inventory || typeof inventory !== 'object') { return out; }
        PACK_KINDS.forEach(function (kind) {
            out[kind] = Math.max(0, Math.min(999, clampInt(inventory[kind], 0)));
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

    function packStock(packKind) {
        if (packKind === 'standard') {
            if (state.isAdmin) { return Infinity; }
            if (!state.collection) { loadCollection(); }
            return freeStandardPacks() + Math.max(0, clampInt((state.collection.packInventory || {}).standard, 0));
        }
        if (state.isAdmin) { return Infinity; }
        if (!state.collection) { loadCollection(); }
        return Math.max(0, clampInt((state.collection.packInventory || {})[packKind], 0));
    }

    function canOpenPack(packKind) {
        return packStock(packKind) > 0;
    }

    function consumePack(packKind) {
        if (state.isAdmin) { return; }
        if (!state.collection) { loadCollection(); }
        if (packKind === 'standard') {
            var rewards = loadFreeRewards();
            if (rewards.standardPacks > 0) {
                rewards.standardPacks = Math.max(0, rewards.standardPacks - 1);
                if (rewards.standardPacks < FREE_PACK_CAP) {
                    rewards.lastPackAt = Date.now();
                }
                saveFreeRewards();
                return;
            }
        }
        state.collection.packInventory = normalizePackInventory(state.collection.packInventory);
        state.collection.packInventory[packKind] = Math.max(0, clampInt(state.collection.packInventory[packKind], 0) - 1);
    }

    function addPack(packKind, amount) {
        if (PACK_KINDS.indexOf(packKind) === -1) { return false; }
        if (!state.collection) { loadCollection(); }
        state.collection.packInventory = normalizePackInventory(state.collection.packInventory);
        state.collection.packInventory[packKind] = Math.max(0, Math.min(999, state.collection.packInventory[packKind] + Math.max(1, clampInt(amount, 1))));
        saveCollection();
        renderSummary();
        renderPackInventory();
        return true;
    }

    function renderPackInventory() {
        els.packStocks.forEach(function (node) {
            var kind = node.getAttribute('data-pack-stock') || 'standard';
            var stock = packStock(kind);
            if (state.isAdmin) {
                node.textContent = 'Admin';
            } else if (kind === 'standard') {
                var bought = Math.max(0, clampInt((state.collection && state.collection.packInventory || {}).standard, 0));
                var free = freeStandardPacks();
                node.textContent = bought > 0 ? free + ' gratis + x' + bought : free + ' gratis';
                var next = nextFreePackMs();
                node.title = free >= FREE_PACK_CAP ? 'Máximo de sobres gratis alcanzado' : 'Siguiente sobre gratis en ' + formatDuration(next);
            } else {
                node.textContent = 'x' + stock;
            }
        });
        els.packButtons.forEach(function (button) {
            var kind = button.getAttribute('data-pack-kind') || 'standard';
            var available = canOpenPack(kind);
            button.disabled = !available;
            button.classList.toggle('is-empty', !available);
            button.setAttribute('aria-disabled', available ? 'false' : 'true');
        });
        renderShop();
    }

    function renderShop() {
        var money = currentMnemones();
        els.mnemonesCounters.forEach(function (node) {
            node.textContent = String(money);
        });
        els.shopButtons.forEach(function (button) {
            var kind = button.getAttribute('data-buy-pack') || 'standard';
            var price = packPrice(kind);
            var canBuy = state.isAdmin || money >= price;
            button.disabled = !canBuy;
            button.classList.toggle('is-empty', !canBuy);
            button.setAttribute('aria-disabled', canBuy ? 'false' : 'true');
        });
    }

    function packPrice(packKind) {
        return PACK_PRICES[packKind] || PACK_PRICES.standard;
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
                setStatus(state.catalog.length ? 'Listo.' : 'No hay cartas activas en el catálogo.');
                renderSummary();
                renderCollectionTable();
                return state.catalog;
            })
            .catch(function (err) {
                state.catalog = [];
                state.catalogById = {};
                setStatus(err.message || 'No se pudo cargar el catálogo.');
                renderSummary();
                renderCollectionTable();
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
        return state.collection;
    }

    function saveCollection() {
        if (!state.collection) { state.collection = createEmptyCollection(); }
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

    function playUiSound(path, volume) {
        try {
            var audio = new Audio(path);
            audio.volume = typeof volume === 'number' ? volume : 0.8;
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

    function openPack(packKind) {
        packKind = packKind || 'standard';
        if (!state.catalog.length) {
            setStatus('No hay cartas disponibles para abrir sobres.');
            return [];
        }
        if (packKind === 'standard' && packStock('standard') <= 0) {
            setStatus('No tienes sobres mnemónicos disponibles. Se recarga 1 gratis cada 10 minutos o puedes reclamar uno con Mnemones.');
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
                hp: rollStat(card.hp_min, card.hp_max),
                atk: rollStat(card.atk_min, card.atk_max),
                def: rollStat(card.def_min, card.def_max),
                obtainedAt: nowIso()
            };
            state.collection.ownedCards.push(copy);
            obtained.push({ catalog: card, instance: copy });
        }

        consumePack(packKind);

        saveCollection();
        if (obtained.length) { playFlipSound(); }
        renderPackResults(obtained);
        renderSummary();
        renderPackInventory();
        showPackReveal(obtained, packKind);
        setStatus(packLabel(packKind) + ': ' + obtained.length + ' cartas obtenidas.');
        return obtained;
    }

    function buyPack(packKind) {
        packKind = packKind || 'standard';
        if (PACK_KINDS.indexOf(packKind) === -1) {
            setStatus('Ese sobre no existe.');
            return false;
        }
        if (!state.collection) { loadCollection(); }
        var price = packPrice(packKind);
        if (!state.isAdmin && currentMnemones() < price) {
            setStatus('No tienes Mnemones suficientes para comprar ' + packLabel(packKind).toLowerCase() + '.');
            return false;
        }
        if (!state.isAdmin) {
            addMnemones(-price);
        }
        addPack(packKind, 1);
        saveCollection();
        playMoneySound();
        renderSummary();
        renderPackInventory();
        setStatus(packLabel(packKind) + ' añadido a tus sobres.');
        return true;
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

    function bestCopy(copies) {
        return copies.slice().sort(function (a, b) {
            return totalStats(b) - totalStats(a);
        })[0] || null;
    }

    function totalStats(copy) {
        if (!copy) { return 0; }
        return (copy.hp || 0) + (copy.atk || 0) + (copy.def || 0);
    }

    function renderSummary() {
        var groups = collectionGroups();
        var uniqueCount = Object.keys(groups).length;
        var totalCopies = state.collection && Array.isArray(state.collection.ownedCards) ? state.collection.ownedCards.length : 0;
        if (els.uniqueCounter) { els.uniqueCounter.textContent = uniqueCount + ' / ' + state.catalog.length; }
        if (els.totalCopiesCounter) { els.totalCopiesCounter.textContent = String(totalCopies); }
        renderDailyCounter();
        renderShop();
        renderBulkSellPreview();
    }

    function qualityScore(copy, card) {
        if (!copy || !card) { return 0; }
        var min = (card.hp_min || 0) + (card.atk_min || 0) + (card.def_min || 0);
        var max = (card.hp_max || 0) + (card.atk_max || 0) + (card.def_max || 0);
        var value = totalStats(copy);
        if (max <= min) { return 100; }
        return Math.max(0, Math.min(100, ((value - min) / (max - min)) * 100));
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
        if (state.collectionRarity !== 'all' && card.card_rarity !== state.collectionRarity) { return false; }
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
        var best = owned ? bestCopy(group.copies) : null;
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

    function isCollectionContext() {
        return state.view === 'collection' || state.mobile;
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

    function renderAlbum() {
        if (!els.albumGrid || !isCollectionContext()) { return 0; }
        var groups = collectionGroups();
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

    function tableEntriesForCurrentCategory() {
        var groups = collectionGroups();
        return Object.keys(groups).map(function (id) {
            var group = groups[id];
            var card = group.catalog;
            var best = bestCopy(group.copies);
            var total = totalStats(best);
            var obtained = group.copies.slice().sort(function (a, b) {
                return String(b.obtainedAt || '').localeCompare(String(a.obtainedAt || ''));
            })[0];
            return {
                cardId: card.card_id,
                rarity: card.card_rarity,
                sourceType: card.source_type,
                score: total,
                row: state.mobile ? [
                    qualityScore(best, card).toFixed(1),
                    '<button type="button" class="hg-table-card-btn hg-table-card-btn--mobile hg-rarity-label--' + card.card_rarity + '" data-card-id="' + card.card_id + '"><strong>' + escapeHtml(card.card_name) + '</strong></button>',
                    '<span class="hg-type-cell hg-type-cell--mobile" title="' + escapeHtml(typeLabel(card.source_type)) + '">' + typeIconHtml(card.source_type, 'hg-type-icon--table') + '</span>',
                    '#' + String(cardDisplayId(card)),
                    total,
                    'x' + group.copies.length
                ] : [
                    '<span class="hg-rarity-label hg-rarity-label--' + card.card_rarity + '">' + escapeHtml(RARITY_LABELS[card.card_rarity] || card.card_rarity) + '</span>',
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
            totalItems = renderAlbum();
        } else {
            var tableRows = tableEntriesForCurrentCategory();
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

    function renderCard(card, copy, options) {
        options = options || {};
        var article = document.createElement('article');
        article.className = 'hg-card hg-card--' + card.card_rarity;
        article.setAttribute('data-rarity', RARITY_LABELS[card.card_rarity] || card.card_rarity);
        article.setAttribute('data-type', card.source_type);
        if (card.card_url) {
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
        rarity.setAttribute('aria-label', RARITY_LABELS[card.card_rarity] || card.card_rarity);
        var iconUrl = RARITY_ICONS[card.card_rarity] || '';
        if (iconUrl) {
            var rarityIcon = document.createElement('img');
            rarityIcon.src = iconUrl;
            rarityIcon.alt = RARITY_LABELS[card.card_rarity] || card.card_rarity;
            rarityIcon.loading = 'lazy';
            rarityIcon.addEventListener('error', function () {
                rarity.textContent = RARITY_SHORT[card.card_rarity] || '?';
            }, { once: true });
            rarity.appendChild(rarityIcon);
        } else {
            rarity.textContent = RARITY_SHORT[card.card_rarity] || '?';
        }
        var title = document.createElement('h4');
        title.textContent = card.card_name;
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
        meta.textContent = (TYPE_EMOJI[card.source_type] || '◦') + ' ' + (TYPE_LABELS[card.source_type] || card.source_type) + ' · ' + (RARITY_LABELS[card.card_rarity] || card.card_rarity);
        meta.innerHTML = '<span class="hg-card__type">' + typeChipHtml(card.source_type, 'hg-card__type-label') + '</span><span class="hg-card__rarity-name">' + escapeHtml(RARITY_LABELS[card.card_rarity] || card.card_rarity) + '</span>';
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

    function sortedCopies(copies) {
        return (Array.isArray(copies) ? copies : []).slice().sort(function (a, b) {
            return totalStats(b) - totalStats(a);
        });
    }

    function showCardModal(card, copies) {
        closeCardModal();
        var sorted = sortedCopies(copies);
        var selected = sorted[0] || null;
        var overlay = document.createElement('div');
        overlay.className = 'hg-card-modal';
        if (state.mobile) { overlay.className += ' hg-card-modal--mobile'; }
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', card.card_name);

        var panel = document.createElement('div');
        panel.className = 'hg-card-modal__panel ' + (sorted.length > 1 ? 'hg-card-modal__panel--variants' : 'hg-card-modal__panel--single');
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
        recycleAll.className = 'hg-recycle-btn';
        recycleAll.textContent = 'Desintegrar todas +' + (recycleValue(card) * copies.length) + ' Mnemones';
        recycleAll.addEventListener('click', function () {
            recycleAllCopies(card);
        });
        actions.appendChild(recycleAll);
        details.appendChild(actions);

        var list = document.createElement('div');
        list.className = 'hg-card-variants__list';
        copies.forEach(function (copy, index) {
            var item = document.createElement('div');
            item.className = 'hg-card-variants__item';
            if (index === 0) { item.className += ' is-active'; }

            var select = document.createElement('button');
            select.type = 'button';
            select.className = 'hg-card-variants__select';
            select.innerHTML =
                '<span>#' + (index + 1) + '</span>' +
                '<strong>PS ' + escapeHtml(copy.hp) + ' / ATQ ' + escapeHtml(copy.atk) + ' / DEF ' + escapeHtml(copy.def) + '</strong>' +
                '<em>' + escapeHtml(totalStats(copy)) + '</em>';
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
            recycle.className = 'hg-recycle-btn hg-recycle-btn--small';
            recycle.textContent = '+' + recycleValue(card);
            recycle.setAttribute('aria-label', 'Desintegrar esta copia por ' + recycleValue(card) + ' Mnemones');
            recycle.addEventListener('click', function () {
                recycleCopy(card, copy);
            });

            item.appendChild(select);
            item.appendChild(recycle);
            list.appendChild(item);
        });
        details.appendChild(list);
        return details;
    }

    function recycleValue(card) {
        return RECYCLE_VALUES[card && card.card_rarity] || RECYCLE_VALUES.common;
    }

    function ownedCopiesForCard(cardId) {
        if (!state.collection) { loadCollection(); }
        return (state.collection.ownedCards || []).filter(function (copy) {
            return String(copy.cardId) === String(cardId);
        });
    }

    function recycleCopy(card, copy) {
        if (!copy || !copy.instanceId) { return false; }
        var copies = ownedCopiesForCard(card.card_id);
        if ((card.card_rarity === 'legendary' || card.card_rarity === 'mythic') && !window.confirm('Vas a desintegrar una copia ' + (RARITY_LABELS[card.card_rarity] || card.card_rarity).toLowerCase() + '. ¿Continuar?')) {
            return false;
        }
        state.collection.ownedCards = state.collection.ownedCards.filter(function (item) {
            return String(item.instanceId) !== String(copy.instanceId);
        });
        var gained = recycleValue(card);
        addMnemones(gained);
        saveCollection();
        playDustSound();
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        var remaining = ownedCopiesForCard(card.card_id);
        if (remaining.length) {
            showCardModal(card, remaining);
        } else {
            closeCardModal();
        }
        setStatus('Copia desintegrada. +' + gained + ' Mnemones.');
        return true;
    }

    function recycleDuplicateCopies(card) {
        var copies = sortedCopies(ownedCopiesForCard(card.card_id));
        if (copies.length <= 1) {
            setStatus('No hay duplicadas que desintegrar.');
            return false;
        }
        var keep = copies[0];
        var recycled = copies.slice(1);
        var gained = recycleValue(card) * recycled.length;
        if (!window.confirm('Se conservará la mejor copia y se desintegrarán ' + recycled.length + ' duplicadas por ' + gained + ' Mnemones. ¿Continuar?')) {
            return false;
        }
        var remove = {};
        recycled.forEach(function (copy) {
            remove[String(copy.instanceId)] = true;
        });
        state.collection.ownedCards = state.collection.ownedCards.filter(function (copy) {
            return !remove[String(copy.instanceId)];
        });
        addMnemones(gained);
        saveCollection();
        playDustSound();
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        showCardModal(card, [keep]);
        setStatus('Duplicadas desintegradas. +' + gained + ' Mnemones.');
        return true;
    }

    function recycleAllCopies(card) {
        var copies = sortedCopies(ownedCopiesForCard(card.card_id));
        if (!copies.length) {
            setStatus('No hay copias que desintegrar.');
            return false;
        }
        var gained = recycleValue(card) * copies.length;
        if (!window.confirm('Se desintegrarán todas las copias de esta carta (' + copies.length + ') por ' + gained + ' Mnemones. ¿Continuar?')) {
            return false;
        }
        var remove = {};
        copies.forEach(function (copy) {
            remove[String(copy.instanceId)] = true;
        });
        state.collection.ownedCards = state.collection.ownedCards.filter(function (copy) {
            return !remove[String(copy.instanceId)];
        });
        addMnemones(gained);
        saveCollection();
        playDustSound();
        renderSummary();
        renderPackInventory();
        renderCollectionTable();
        closeCardModal();
        setStatus('Carta desintegrada. +' + gained + ' Mnemones.');
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
            if (!card || card.card_rarity !== rarity) { return; }
            var cardId = String(card.card_id);
            if (!byCard[cardId]) { byCard[cardId] = { card: card, copies: [] }; }
            byCard[cardId].copies.push(copy);
        });

        Object.keys(byCard).forEach(function (cardId) {
            var entry = byCard[cardId];
            var copies = sortedCopies(entry.copies);
            var toSell = keepBest ? copies.slice(1) : copies;
            if (keepBest && copies.length) { kept += 1; }
            toSell.forEach(function (copy) {
                remove[String(copy.instanceId)] = true;
                count += 1;
                gained += recycleValue(entry.card);
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
        els.bulkSellPreview.textContent = stats.count + ' cartas ' + label.toLowerCase() + ' - +' + stats.gained + ' Mnemones'
            + (stats.keepBest && stats.kept ? ' · conserva ' + stats.kept + ' mejores' : '');
        els.bulkSellBtn.disabled = stats.count <= 0;
    }

    function sellCardsByRarity() {
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
        if (!window.confirm('Vas a vender ' + stats.count + ' cartas de rareza ' + label.toLowerCase() + ' por ' + stats.gained + ' Mnemones.' + keepText + ' Esta acción no se puede deshacer. ¿Continuar?')) {
            return false;
        }

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
        setStatus('Venta completada. +' + stats.gained + ' Mnemones.');
        return true;
    }

    function closeCardModal() {
        var current = document.querySelector('.hg-card-modal');
        if (current) {
            current.remove();
        }
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
            currency: normalizeCurrency(data.currency),
            packInventory: normalizePackInventory(data.packInventory)
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
            out.ownedCards.push({
                instanceId: id,
                cardId: cardId,
                hp: clampInt(item.hp, card ? card.hp_min : atkFallback),
                atk: atkFallback,
                def: clampInt(item.def, card ? card.def_min : 10),
                obtainedAt: typeof item.obtainedAt === 'string' && item.obtainedAt ? item.obtainedAt : nowIso()
            });
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

    function resetCollection() {
        if (!window.confirm('Esto borrará la colección local de este navegador. ¿Continuar?')) { return; }
        state.collection = createEmptyCollection();
        state.freeRewards = createFreeRewards();
        try { window.localStorage.removeItem(STORAGE_KEY); } catch (e) { saveCollection(); }
        try { window.localStorage.removeItem(FREE_REWARDS_KEY); } catch (e2) { saveFreeRewards(); }
        renderPackResults([]);
        renderSummary();
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
        return String(text || '').replace(/[&<>"']/g, function (m) {
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
                var target = tab.getAttribute('data-mobile-panel-tab') || 'packs';
                els.mobileTabs.forEach(function (item) {
                    item.classList.toggle('is-active', item === tab);
                });
                els.mobilePanels.forEach(function (panel) {
                    panel.classList.toggle('is-active', panel.getAttribute('data-mobile-panel') === target);
                });
                if (target === 'collection') {
                    renderCollectionTable();
                }
            });
        });
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
    }

    function startFreeRewardTimer() {
        if (state.isAdmin || state.rewardsTimer) { return; }
        state.rewardsTimer = window.setInterval(function () {
            var gained = syncFreeRewards();
            if (gained.packs > 0 || gained.mnemones > 0) {
                renderSummary();
                renderPackInventory();
                renderCollectionTable();
                if (gained.packs > 0 && gained.mnemones > 0) {
                    setStatus('Recarga gratuita: +' + gained.packs + ' sobre y +' + gained.mnemones + ' Mnemones.');
                } else if (gained.packs > 0) {
                    setStatus('Recarga gratuita: +' + gained.packs + ' sobre mnemónico.');
                } else {
                    setStatus('Recarga gratuita: +' + gained.mnemones + ' Mnemones.');
                }
            } else {
                renderDailyCounter();
                renderPackInventory();
            }
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
        els.shopButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                buyPack(button.getAttribute('data-buy-pack') || 'standard');
            });
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
        if (els.resetBtn) { els.resetBtn.addEventListener('click', resetCollection); }
        if (els.bulkSellRarity) { els.bulkSellRarity.addEventListener('change', renderBulkSellPreview); }
        if (els.bulkSellKeepBest) { els.bulkSellKeepBest.addEventListener('change', renderBulkSellPreview); }
        if (els.bulkSellBtn) { els.bulkSellBtn.addEventListener('click', sellCardsByRarity); }
    }

    loadCollectionViewPrefs();
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
