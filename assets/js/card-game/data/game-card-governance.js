(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.data = app.data || {};

    var utils = app.core && app.core.utils ? app.core.utils : null;
    var config = {
        clampInt: utils && typeof utils.clampInt === 'function' ? utils.clampInt : function (value, fallback) {
            var n = Number(value);
            if (!Number.isFinite(n)) { return fallback; }
            return Math.round(n);
        },
        clampQuality: utils && typeof utils.clampQuality === 'function' ? utils.clampQuality : function (value, fallback) {
            var n = Number(value);
            if (!Number.isFinite(n)) { return fallback; }
            return Math.max(0, Math.min(100, Math.round(n * 10) / 10));
        },
        normalizeRarity: function (value, fallback) {
            return String(value || fallback || 'common');
        },
        getRarityStatRanges: function () { return {}; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function rarityRanges() {
        return typeof config.getRarityStatRanges === 'function' ? (config.getRarityStatRanges() || {}) : {};
    }

    function copyRarity(copy, card) {
        return config.normalizeRarity(copy && copy.rarity, config.normalizeRarity(card && card.card_rarity, 'common'));
    }

    function rarityStatRange(rarity) {
        var ranges = rarityRanges();
        return ranges[config.normalizeRarity(rarity, 'common')] || ranges.common || [10, 40];
    }

    function statBoundsForRarity(card, rarity, stat) {
        rarity = config.normalizeRarity(rarity, 'common');
        if (card && rarity === config.normalizeRarity(card.card_rarity, 'common')) {
            var fallback = rarityStatRange(rarity);
            var min = config.clampInt(card[stat + '_min'], fallback[0]);
            var max = config.clampInt(card[stat + '_max'], fallback[1]);
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
        return Math.max(to[0], Math.min(to[1], config.clampInt(to[0] + ((to[1] - to[0]) * percent), to[0])));
    }

    function retuneCopyStatsForRarity(copy, card, fromRarity, toRarity) {
        if (!copy || !card) { return; }
        copy.hp = scaledStatForRarity(copy, card, 'hp', fromRarity, toRarity);
        copy.atk = scaledStatForRarity(copy, card, 'atk', fromRarity, toRarity);
        copy.def = scaledStatForRarity(copy, card, 'def', fromRarity, toRarity);
        copy.rarity = config.normalizeRarity(toRarity, fromRarity);
        copy.quality = calculatedQualityScore(copy, card);
    }

    function statForQuality(card, rarity, stat, quality) {
        var bounds = statBoundsForRarity(card, rarity, stat);
        var percent = Math.max(0, Math.min(1, Number(quality || 0) / 100));
        return Math.max(bounds[0], Math.min(bounds[1], config.clampInt(bounds[0] + ((bounds[1] - bounds[0]) * percent), bounds[0])));
    }

    function applyQualityToCopyStats(copy, card, quality) {
        if (!copy || !card) { return; }
        var rarity = copyRarity(copy, card);
        var targetQuality = config.clampQuality(quality, qualityScore(copy, card));
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
        return config.clampQuality(((value - min) / (max - min)) * 100, 0);
    }

    function copyUpgradedFlag(copy, card) {
        if (!copy) { return false; }
        if (copy.upgraded === true || copy.upgraded === 1 || copy.upgraded === '1') { return true; }
        if (!card) { return false; }
        return copyRarity(copy, card) !== config.normalizeRarity(card.card_rarity, 'common');
    }

    function qualityScore(copy, card) {
        if (!copy || !card) { return 0; }
        return config.clampQuality(copy.quality, calculatedQualityScore(copy, card));
    }

    var api = Object.freeze({
        configure: configure,
        copyRarity: copyRarity,
        rarityStatRange: rarityStatRange,
        statBoundsForRarity: statBoundsForRarity,
        statPercentInBounds: statPercentInBounds,
        scaledStatForRarity: scaledStatForRarity,
        retuneCopyStatsForRarity: retuneCopyStatsForRarity,
        statForQuality: statForQuality,
        applyQualityToCopyStats: applyQualityToCopyStats,
        statsBelowRarityFloor: statsBelowRarityFloor,
        cardForCopy: cardForCopy,
        totalStats: totalStats,
        calculatedQualityScore: calculatedQualityScore,
        copyUpgradedFlag: copyUpgradedFlag,
        qualityScore: qualityScore
    });

    app.data.governance = api;
})(window);
