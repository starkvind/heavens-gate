(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.cards = app.cards || {};

    var config = {
        getState: function () { return null; },
        loadCollection: function () {},
        saveCollection: function () {},
        normalizeCurrency: function (value) { return value; },
        normalizeMaterialInventory: function (value) { return value; },
        clampInt: function (value, fallback) {
            var n = Number(value);
            return Number.isFinite(n) ? Math.round(n) : fallback;
        },
        formatNumber: function (value) { return String(value); },
        copyRarity: function () { return 'common'; },
        qualityScore: function () { return 0; },
        rarityRank: function () { return 0; },
        cardForCopy: function (_, copy) { return copy; },
        totalStats: function () { return 0; },
        retuneCopyStatsForRarity: function () {},
        resetCopySkills: function () {},
        copyHasLearnedMoves: function () { return false; },
        isCopyWorking: function () { return false; },
        isCopyInCombatTeam: function () { return false; },
        removeCopiesFromCombatTeams: function () { return 0; },
        ownedCopiesForCard: function () { return []; },
        showCardModal: function () {},
        closeRarityUpgradeModal: function () {},
        playDustSound: function () {},
        renderSummary: function () {},
        renderPackInventory: function () {},
        renderCollectionTable: function () {},
        renderCombatSetup: function () {},
        currentRemorias: function () { return 0; },
        materialStock: function () { return 0; },
        setStatus: function () {},
        uiText: function (key, fallback, values) {
            var text = fallback || key;
            return values ? text.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
                return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
            }) : text;
        },
        confirmGameAction: function () { return false; },
        rarityUpgradeOrder: function () { return []; },
        rarityUpgradeMultipliers: function () { return []; },
        upgradeCostByRarity: function () { return {}; },
        rarityUpgradeMaterials: function () { return {}; },
        rarityUpgradeMinQuality: function () { return 0; },
        rarityUpgradeRequired: function () { return 3; },
        rarityLabels: function () { return {}; },
        copySortValue: function () { return 0; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function nextRarity(rarity) {
        var order = config.rarityUpgradeOrder() || [];
        var index = order.indexOf(String(rarity || 'common'));
        return index >= 0 && index < order.length - 1 ? order[index + 1] : null;
    }

    function rarityUpgradeMultiplier(sourceRarity, targetRarity) {
        var diff = Math.max(0, config.rarityRank(sourceRarity) - config.rarityRank(targetRarity));
        var multipliers = config.rarityUpgradeMultipliers() || [];
        return multipliers[Math.min(diff, multipliers.length - 1)] || 1;
    }

    function upgradeMnemoneCost(card, copy) {
        var rarity = config.copyRarity(copy, card);
        var costs = config.upgradeCostByRarity() || {};
        var base = costs[rarity] || costs.common;
        var factor = Math.max(0.01, Math.min(1, config.qualityScore(copy, card) / 100));
        return Math.max(1, Math.ceil(base * factor));
    }

    function rarityUpgradeMaterial(nextRarityValue) {
        return (config.rarityUpgradeMaterials() || {})[nextRarityValue] || '';
    }

    function canPayUpgradeCost(cost, materialKey) {
        var state = config.getState();
        return !!(state && state.isAdmin) || (config.currentRemorias() >= cost && (!materialKey || config.materialStock(materialKey) >= 1));
    }

    function spendUpgradeCost(cost, materialKey) {
        var state = config.getState();
        if (state && state.isAdmin) { return true; }
        if (!state.collection) { config.loadCollection(); }
        state.collection.currency = config.normalizeCurrency(state.collection.currency);
        state.collection.materialInventory = config.normalizeMaterialInventory(state.collection.materialInventory);
        if (config.clampInt(state.collection.currency.remorias, 0) < cost) { return false; }
        if (materialKey && config.clampInt(state.collection.materialInventory[materialKey], 0) < 1) { return false; }
        state.collection.currency.remorias = Math.max(0, config.clampInt(state.collection.currency.remorias, 0) - cost);
        if (materialKey) {
            state.collection.materialInventory[materialKey] = Math.max(0, config.clampInt(state.collection.materialInventory[materialKey], 0) - 1);
        }
        return true;
    }

    function rarityUpgradeCandidates(targetCard, targetCopy) {
        var state = config.getState();
        var targetId;
        var targetRarity;
        if (!state.collection) { config.loadCollection(); }
        targetId = String(targetCopy && targetCopy.instanceId || '');
        targetRarity = config.copyRarity(targetCopy, targetCard);
        return (state.collection.ownedCards || []).map(function (copy) {
            var card = state.catalogById[String(copy.cardId || '')];
            var rarity;
            if (!card || String(copy.instanceId || '') === targetId) { return null; }
            if (config.isCopyWorking(copy.instanceId)) { return null; }
            rarity = config.copyRarity(copy, card);
            if (config.rarityRank(rarity) < config.rarityRank(targetRarity)) { return null; }
            if (config.qualityScore(copy, card) < config.rarityUpgradeMinQuality()) { return null; }
            if (config.isCopyInCombatTeam(copy.instanceId)) { return null; }
            return {
                card: config.cardForCopy(card, copy),
                baseCard: card,
                copy: copy,
                rarity: rarity,
                contribution: rarityUpgradeMultiplier(rarity, targetRarity),
                score: config.totalStats(copy)
            };
        }).filter(Boolean).sort(function (a, b) {
            var rarityDiff = config.rarityRank(a.rarity) - config.rarityRank(b.rarity);
            if (rarityDiff !== 0) { return rarityDiff; }
            return b.score - a.score;
        });
    }

    function worseDuplicateCandidateMap(candidates) {
        var state = config.getState();
        var candidateIds = {};
        var groups = {};
        var out = {};
        if (!state.collection) { config.loadCollection(); }
        (candidates || []).forEach(function (entry) {
            candidateIds[String(entry && entry.copy && entry.copy.instanceId || '')] = true;
        });
        (state.collection.ownedCards || []).forEach(function (copy) {
            var card = state.catalogById[String(copy && copy.cardId || '')];
            var cardId;
            if (!copy || !card) { return; }
            cardId = String(card.card_id || copy.cardId || '');
            if (!cardId) { return; }
            if (!groups[cardId]) { groups[cardId] = { card: card, copies: [] }; }
            groups[cardId].copies.push(copy);
        });
        Object.keys(groups).forEach(function (cardId) {
            var group = groups[cardId];
            if (!group || group.copies.length <= 1) { return; }
            group.copies.slice().sort(function (a, b) {
                return config.copySortValue(b, group.card) - config.copySortValue(a, group.card)
                    || String(a.instanceId || '').localeCompare(String(b.instanceId || ''));
            }).slice(1).forEach(function (entry) {
                var id = String(entry.instanceId || '');
                if (candidateIds[id]) { out[id] = true; }
            });
        });
        return out;
    }

    function applyRarityUpgrade(targetCard, targetCopy, selectedIds, options) {
        var state = config.getState();
        var targetRarity;
        var next;
        var upgradeCost;
        var requiredMaterial;
        var selected;
        var candidates;
        var byId = {};
        var progress;
        var currentRemoriaStock;
        var currentMaterialStock;
        var remove = {};
        options = options || {};
        if (config.isCopyWorking(targetCopy && targetCopy.instanceId)) {
            config.setStatus(config.uiText('upgrade.remove_memory_first', 'Retira la carta de la rememoración antes de evolucionarla.'));
            return false;
        }
        targetRarity = config.copyRarity(targetCopy, targetCard);
        next = nextRarity(targetRarity);
        if (!next) { return false; }
        if (config.copyHasLearnedMoves(targetCopy) && !options.skillResetConfirmed) {
            return config.confirmGameAction(
                config.uiText('upgrade.reset_skills_message', 'Esta evolución reinicia todas las habilidades aprendidas de la carta. Si ahora tiene habilidades, pasará a {rarity} sin habilidades. ¿Seguir?', { rarity: (config.rarityLabels() || {})[next] || next }),
                {
                    title: config.uiText('upgrade.reset_skills_title', 'Perder habilidades'),
                    confirmLabel: config.uiText('upgrade.reset_skills_confirm', 'Sí, evolucionar'),
                    cancelLabel: config.uiText('upgrade.cancel', 'Cancelar')
                },
                function () {
                    applyRarityUpgrade(targetCard, targetCopy, selectedIds, { skillResetConfirmed: true });
                }
            );
        }
        upgradeCost = upgradeMnemoneCost(targetCard, targetCopy);
        requiredMaterial = rarityUpgradeMaterial(next);
        selected = (selectedIds || []).slice(0, config.rarityUpgradeRequired());
        candidates = rarityUpgradeCandidates(targetCard, targetCopy);
        candidates.forEach(function (entry) {
            byId[String(entry.copy.instanceId || '')] = entry;
        });
        progress = selected.reduce(function (sum, id) {
            return sum + (byId[String(id)] ? byId[String(id)].contribution : 0);
        }, 0);
        if (progress < config.rarityUpgradeRequired()) {
            config.setStatus(config.uiText('upgrade.need_sacrifices', 'Elige sacrificios suficientes para completar la evolución.'));
            return false;
        }
        if (!state.collection) { config.loadCollection(); }
        state.collection.currency = config.normalizeCurrency(state.collection.currency);
        state.collection.materialInventory = config.normalizeMaterialInventory(state.collection.materialInventory);
        currentRemoriaStock = config.clampInt(state.collection.currency.remorias, 0);
        currentMaterialStock = requiredMaterial ? config.clampInt(state.collection.materialInventory[requiredMaterial], 0) : 0;
        if (!(state && state.isAdmin) && (currentRemoriaStock < upgradeCost || (requiredMaterial && currentMaterialStock < 1))) {
            config.setStatus(config.uiText('upgrade.missing_cost', 'Faltan Remorias u objetos rituales para evolucionar.'));
            return false;
        }
        if (global.console && typeof global.console.info === 'function') {
            global.console.info('[HG evolve]', {
                from: targetRarity,
                to: next,
                requiredMaterial: requiredMaterial,
                remoriasBefore: currentRemoriaStock,
                materialBefore: currentMaterialStock
            });
        }
        if (!(state && state.isAdmin)) {
            state.collection.currency.remorias = Math.max(0, currentRemoriaStock - upgradeCost);
            if (requiredMaterial) {
                state.collection.materialInventory[requiredMaterial] = Math.max(0, currentMaterialStock - 1);
            }
        }
        if (global.console && typeof global.console.info === 'function') {
            global.console.info('[HG evolve:after-pay]', {
                remoriasAfter: config.clampInt(state.collection.currency.remorias, 0),
                materialAfter: requiredMaterial ? config.clampInt(state.collection.materialInventory[requiredMaterial], 0) : null
            });
        }
        selected.forEach(function (id) {
            if (byId[String(id)]) { remove[String(id)] = true; }
        });
        config.retuneCopyStatsForRarity(targetCopy, targetCard, targetRarity, next);
        targetCopy.upgraded = true;
        config.resetCopySkills(targetCopy);
        state.collection.ownedCards = (state.collection.ownedCards || []).filter(function (copy) {
            return !remove[String(copy.instanceId || '')];
        });
        config.removeCopiesFromCombatTeams(remove);
        config.saveCollection();
        config.playDustSound();
        config.renderSummary();
        config.renderPackInventory();
        config.renderCollectionTable();
        config.renderCombatSetup();
        config.closeRarityUpgradeModal();
        config.showCardModal(targetCard, config.ownedCopiesForCard(targetCard.card_id));
        config.setStatus(config.uiText('upgrade.done', 'Rareza evolucionada a {rarity}. Coste: {cost} Remorias.', { rarity: (config.rarityLabels() || {})[next] || next, cost: config.formatNumber(upgradeCost) }));
        return true;
    }

    var api = Object.freeze({
        configure: configure,
        nextRarity: nextRarity,
        rarityUpgradeMultiplier: rarityUpgradeMultiplier,
        upgradeMnemoneCost: upgradeMnemoneCost,
        rarityUpgradeMaterial: rarityUpgradeMaterial,
        canPayUpgradeCost: canPayUpgradeCost,
        spendUpgradeCost: spendUpgradeCost,
        rarityUpgradeCandidates: rarityUpgradeCandidates,
        worseDuplicateCandidateMap: worseDuplicateCandidateMap,
        applyRarityUpgrade: applyRarityUpgrade
    });

    app.cards.evolve = api;
})(window);
