(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.collection = app.collection || {};

    var config = {
        getState: function () { return null; },
        setCollection: function () {},
        getCatalog: function () { return []; },
        getCatalogById: function () { return {}; },
        nowIso: function () { return new Date().toISOString(); },
        clampInt: function (value, fallback) {
            var n = Number(value);
            if (!Number.isFinite(n)) { return fallback; }
            return Math.round(n);
        },
        clampQuality: function (value, fallback) {
            var n = Number(value);
            if (!Number.isFinite(n)) { return fallback; }
            return Math.max(0, Math.min(100, Math.round(n * 10) / 10));
        },
        normalizeCurrency: function (value) { return value; },
        normalizePackInventory: function (value) { return value; },
        normalizeDailyShopPackPurchases: function (value) { return value; },
        normalizeMaterialInventory: function (value) { return value; },
        normalizeWorkAssignments: function (value) { return value || {}; },
        normalizeWorkPendingRewards: function (value) { return value || 0; },
        normalizeCombatTeams: function (value) { return value; },
        normalizeCombatProfile: function (value) { return value; },
        loadCombatTeams: function () { return null; },
        loadCombatProfile: function () { return null; },
        cleanCombatTeamsAgainstCollection: function () {},
        saveCombatTeams: function () {},
        saveCombatProfile: function () {},
        saveCollection: function () {},
        saveShopState: function () {},
        createEmptyCollection: function () { return {}; },
        createShopState: function () { return {}; },
        instanceId: function () { return 'id'; },
        normalizeRarity: function (value, fallback) { return String(value || fallback || 'common'); },
        normalizeCopyMoveIds: function (value) { return Array.isArray(value) ? value : []; },
        highestMoveCheckpoint: function (value) { return String(value || 'common'); },
        ensureCopyMovesForRarity: function () {},
        calculatedQualityScore: function () { return 0; },
        copySortValue: function () { return 0; },
        renderPackResults: function () {},
        renderSummary: function () {},
        renderDailyCounter: function () {},
        renderPackInventory: function () {},
        renderCollectionTable: function () {},
        renderCombat: function () {},
        setStatus: function () {},
        uiText: function (key, fallback) { return fallback || key; },
        confirmGameAction: function () { return false; },
        removeStorageKey: function () {}
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function validateCollection(data) {
        var state = config.getState();
        var catalog = config.getCatalog() || [];
        var catalogById = config.getCatalogById() || {};
        if (!data || typeof data !== 'object' || [1, 2].indexOf(Number(data.version)) === -1 || !Array.isArray(data.ownedCards)) {
            throw new Error('El JSON no tiene una colección compatible.');
        }
        if (data.ownedCards.length > 10000) {
            throw new Error('La colección importada es demasiado grande.');
        }
        var out = {
            version: 2,
            createdAt: typeof data.createdAt === 'string' && data.createdAt ? data.createdAt : config.nowIso(),
            updatedAt: config.nowIso(),
            favoriteCopyIds: Array.isArray(data.favoriteCopyIds) ? data.favoriteCopyIds.map(function (id) {
                return String(id || '').slice(0, 80);
            }).filter(Boolean) : [],
            ownedCards: [],
            workAssignments: config.normalizeWorkAssignments(data.workAssignments),
            workPendingRewards: config.normalizeWorkPendingRewards(data.workPendingRewards),
            currency: config.normalizeCurrency(data.currency),
            packInventory: config.normalizePackInventory(data.packInventory),
            dailyShopPackPurchases: config.normalizeDailyShopPackPurchases(data.dailyShopPackPurchases),
            materialInventory: config.normalizeMaterialInventory(data.materialInventory)
        };
        var seen = {};
        data.ownedCards.forEach(function (item) {
            if (!item || typeof item !== 'object') { return; }
            var cardId = config.clampInt(item.cardId, 0);
            if (cardId <= 0) { return; }
            if (catalog.length && !catalogById[String(cardId)]) { return; }
            var id = String(item.instanceId || config.instanceId()).slice(0, 80);
            if (seen[id]) { id = config.instanceId(); }
            seen[id] = true;
            var card = catalogById[String(cardId)] || null;
            var atkFallback = config.clampInt(item.atk, card ? card.atk_min : 10);
            var copy = {
                instanceId: id,
                cardId: cardId,
                hp: config.clampInt(item.hp, card ? card.hp_min : atkFallback),
                atk: atkFallback,
                def: config.clampInt(item.def, card ? card.def_min : 10),
                obtainedAt: typeof item.obtainedAt === 'string' && item.obtainedAt ? item.obtainedAt : config.nowIso()
            };
            if ((state && state.RARITY_ORDER || []).indexOf(String(item.rarity || '')) !== -1) {
                copy.rarity = String(item.rarity);
            } else if (card) {
                copy.rarity = card.card_rarity;
            }
            copy.upgraded = !!item.upgraded || (card ? copy.rarity !== config.normalizeRarity(card.card_rarity, 'common') : false);
            copy.moves = config.normalizeCopyMoveIds(item.moves);
            copy.moveRollRarity = config.highestMoveCheckpoint(item.moveRollRarity || item.movesRarityCheckpoint || 'common');
            if (card) {
                config.ensureCopyMovesForRarity(copy, card, copy.rarity, !item.moveRollRarity && !item.movesRarityCheckpoint);
            }
            var importedQuality = config.clampQuality(item.quality, null);
            if (importedQuality !== null) {
                copy.quality = importedQuality;
            } else if (card) {
                copy.quality = config.calculatedQualityScore(copy, card);
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
                var legacyCard = catalogById[legacyCardId] || null;
                legacyFavorites.sort(function (a, b) {
                    return config.copySortValue(b, legacyCard) - config.copySortValue(a, legacyCard)
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

    function exportCollection() {
        var state = config.getState();
        if (!state.collection) { return false; }
        var exportData = JSON.parse(JSON.stringify(state.collection));
        exportData.combatTeams = config.normalizeCombatTeams(state.combatTeams || config.loadCombatTeams());
        exportData.combatProfile = config.normalizeCombatProfile(state.combatProfile || config.loadCombatProfile());
        var blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
        var url = global.URL.createObjectURL(blob);
        var a = global.document.createElement('a');
        a.href = url;
        a.download = 'hg_card_collection_v2.json';
        global.document.body.appendChild(a);
        a.click();
        a.remove();
        global.setTimeout(function () { global.URL.revokeObjectURL(url); }, 250);
        config.setStatus(config.uiText('collection.export_done', 'Colección y equipos exportados a JSON.'));
        return true;
    }

    function importCollection(json) {
        var state = config.getState();
        try {
            var payload = JSON.parse(json);
            config.setCollection(validateCollection(payload));
            config.saveCollection();
            if (payload && payload.combatTeams) {
                state.combatTeams = config.normalizeCombatTeams(payload.combatTeams);
                state.activeCombatTeam = state.combatTeams.activeTeam;
                state.draftCombatTeam = state.combatTeams.teams[state.activeCombatTeam].cards.slice();
                config.cleanCombatTeamsAgainstCollection(true);
                config.saveCombatTeams();
            }
            if (payload && payload.combatProfile) {
                state.combatProfile = config.normalizeCombatProfile(payload.combatProfile);
                config.saveCombatProfile();
            }
            config.renderPackResults([]);
            config.renderSummary();
            config.renderPackInventory();
            config.renderCollectionTable();
            config.renderCombat();
            config.setStatus(config.uiText('collection.import_done', 'Colección importada correctamente.'));
            return true;
        } catch (e) {
            config.setStatus(e.message || 'No se pudo importar la colección.');
            return false;
        }
    }

    function resetCollection(confirmed) {
        var state = config.getState();
        if (!confirmed) {
            return config.confirmGameAction(
                'Esto borrara la coleccion local de este navegador.',
                { title: 'Borrar coleccion', confirmLabel: 'Borrar' },
                function () { resetCollection(true); }
            );
        }
        config.setCollection(config.createEmptyCollection());
        state.shopState = config.createShopState();
        config.removeStorageKey('STORAGE_KEY');
        config.removeStorageKey('LEGACY_STORAGE_KEY');
        config.removeStorageKey('CARD_SHOP_STATE_KEY');
        config.removeStorageKey('LEGACY_FREE_REWARDS_KEY');
        config.renderPackResults([]);
        config.renderSummary();
        config.renderDailyCounter();
        config.renderPackInventory();
        config.renderCollectionTable();
        config.setStatus(config.uiText('collection.reset_done', 'Colección local borrada.'));
        return true;
    }

    var api = Object.freeze({
        configure: configure,
        validateCollection: validateCollection,
        exportCollection: exportCollection,
        importCollection: importCollection,
        resetCollection: resetCollection
    });

    app.collection.importExport = api;
})(window);
