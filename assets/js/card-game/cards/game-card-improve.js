(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.cards = app.cards || {};

    var config = {
        getState: function () { return null; },
        loadCollection: function () {},
        clampQuality: function (value, fallback) { return fallback; },
        copyRarity: function () { return 'common'; },
        qualityScore: function () { return 0; },
        totalStats: function () { return 0; },
        cardForCopy: function (_, copy) { return copy; },
        isCopyWorking: function () { return false; },
        isCopyInCombatTeam: function () { return false; },
        upgradeMnemoneCost: function () { return 1; },
        spendUpgradeCost: function () { return false; },
        applyQualityToCopyStats: function () {},
        removeCopiesFromCombatTeams: function () { return 0; },
        saveCollection: function () {},
        playDustSound: function () {},
        renderSummary: function () {},
        renderPackInventory: function () {},
        renderCollectionTable: function () {},
        renderCombatSetup: function () {},
        closeQualityUpgradeModal: function () {},
        showCardModal: function () {},
        ownedCopiesForCard: function () { return []; },
        setStatus: function () {},
        uiText: function (key, fallback, values) {
            var text = fallback || key;
            return values ? text.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
                return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
            }) : text;
        },
        formatNumber: function (value) { return String(value); },
        qualityUpgradeMaxSlots: function () { return 5; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function qualityUpgradeContribution(entry, targetQuality) {
        var sourceQuality = config.qualityScore(entry.copy, entry.baseCard);
        var base = 8 + (sourceQuality * 0.12);
        var resistance = 1 + (Math.max(0, targetQuality) / 45);
        return Math.max(0.5, Math.round((base / resistance) * 10) / 10);
    }

    function projectedQualityAfterSacrifices(targetQuality, entries) {
        var quality = config.clampQuality(targetQuality, 0);
        (entries || []).forEach(function (entry) {
            quality = config.clampQuality(quality + qualityUpgradeContribution(entry, quality), quality);
        });
        return quality;
    }

    function qualityUpgradeCandidates(targetCard, targetCopy) {
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
            if (rarity !== targetRarity) { return null; }
            if (config.isCopyInCombatTeam(copy.instanceId)) { return null; }
            return {
                card: config.cardForCopy(card, copy),
                baseCard: card,
                copy: copy,
                rarity: rarity,
                score: config.totalStats(copy)
            };
        }).filter(Boolean).sort(function (a, b) {
            return config.qualityScore(b.copy, b.baseCard) - config.qualityScore(a.copy, a.baseCard) || b.score - a.score;
        });
    }

    function applyQualityUpgrade(targetCard, targetCopy, selectedIds) {
        var state = config.getState();
        var targetQuality;
        var candidates;
        var byId = {};
        var selected;
        var projected;
        var improveCost;
        var remove = {};
        if (config.isCopyWorking(targetCopy && targetCopy.instanceId)) {
            config.setStatus(config.uiText('improve.remove_memory_first', 'Retira la carta de la rememoración antes de mejorarla.'));
            return false;
        }
        targetQuality = config.qualityScore(targetCopy, targetCard);
        candidates = qualityUpgradeCandidates(targetCard, targetCopy);
        candidates.forEach(function (entry) {
            byId[String(entry.copy.instanceId || '')] = entry;
        });
        selected = (selectedIds || []).slice(0, config.qualityUpgradeMaxSlots()).map(function (id) {
            return byId[String(id)] || null;
        }).filter(Boolean);
        if (!selected.length) {
            config.setStatus(config.uiText('improve.need_sacrifices', 'Elige al menos una carta para mejorar atributos.'));
            return false;
        }
        projected = projectedQualityAfterSacrifices(targetQuality, selected);
        if (projected <= targetQuality) {
            config.setStatus(config.uiText('improve.no_gain', 'Esos sacrificios no mejoran la calidad.'));
            return false;
        }
        improveCost = config.upgradeMnemoneCost(targetCard, targetCopy);
        if (!config.spendUpgradeCost(improveCost, '')) {
            config.setStatus(config.uiText('improve.missing_cost', 'Faltan Remorias para mejorar atributos.'));
            return false;
        }
        selected.forEach(function (entry) {
            remove[String(entry.copy.instanceId || '')] = true;
        });
        config.applyQualityToCopyStats(targetCopy, targetCard, projected);
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
        config.closeQualityUpgradeModal();
        config.showCardModal(targetCard, config.ownedCopiesForCard(targetCard.card_id));
        config.setStatus(config.uiText('improve.done', 'Atributos mejorados a CAL {quality}%. Coste: {cost} Remorias.', { quality: config.qualityScore(targetCopy, targetCard).toFixed(1), cost: config.formatNumber(improveCost) }));
        return true;
    }

    var api = Object.freeze({
        configure: configure,
        qualityUpgradeContribution: qualityUpgradeContribution,
        projectedQualityAfterSacrifices: projectedQualityAfterSacrifices,
        qualityUpgradeCandidates: qualityUpgradeCandidates,
        applyQualityUpgrade: applyQualityUpgrade
    });

    app.cards.improve = api;
})(window);
