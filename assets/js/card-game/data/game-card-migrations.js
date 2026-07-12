(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.data = app.data || {};

    var governance = app.data && app.data.governance ? app.data.governance : null;
    var utils = app.core && app.core.utils ? app.core.utils : null;
    var config = {
        clampQuality: utils && typeof utils.clampQuality === 'function' ? utils.clampQuality : function (value, fallback) {
            var n = Number(value);
            if (!Number.isFinite(n)) { return fallback; }
            return Math.max(0, Math.min(100, Math.round(n * 10) / 10));
        },
        normalizeRarity: function (value, fallback) {
            return String(value || fallback || 'common');
        },
        copyUpgradedFlag: governance && typeof governance.copyUpgradedFlag === 'function' ? governance.copyUpgradedFlag : function () { return false; },
        copyRarity: governance && typeof governance.copyRarity === 'function' ? governance.copyRarity : function (copy, card) {
            return String((copy && copy.rarity) || (card && card.card_rarity) || 'common');
        },
        statsBelowRarityFloor: governance && typeof governance.statsBelowRarityFloor === 'function' ? governance.statsBelowRarityFloor : function () { return false; },
        retuneCopyStatsForRarity: governance && typeof governance.retuneCopyStatsForRarity === 'function' ? governance.retuneCopyStatsForRarity : function () {},
        calculatedQualityScore: governance && typeof governance.calculatedQualityScore === 'function' ? governance.calculatedQualityScore : function () { return 0; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function migrateCollectionQualityCopy(copy, card) {
        if (!copy || typeof copy !== 'object' || !card) { return false; }
        var changed = false;
        var upgraded = config.copyUpgradedFlag(copy, card);
        if (!!copy.upgraded !== upgraded) {
            copy.upgraded = upgraded;
            changed = true;
        }
        if (upgraded) {
            var upgradedQuality = config.clampQuality(copy.quality, null);
            if (upgradedQuality !== null) {
                if (copy.quality !== upgradedQuality) {
                    copy.quality = upgradedQuality;
                    changed = true;
                }
            } else {
                copy.quality = config.calculatedQualityScore(copy, card);
                changed = true;
            }
            return changed;
        }

        var rarity = config.copyRarity(copy, card);
        if (copy.rarity !== rarity) {
            copy.rarity = rarity;
            changed = true;
        }
        if (rarity !== config.normalizeRarity(card.card_rarity, 'common') && config.statsBelowRarityFloor(copy, card, rarity)) {
            config.retuneCopyStatsForRarity(copy, card, card.card_rarity, rarity);
            changed = true;
        }
        var current = config.clampQuality(copy.quality, null);
        if (current !== null) {
            if (copy.quality !== current) {
                copy.quality = current;
                changed = true;
            }
            return changed;
        }
        copy.quality = config.calculatedQualityScore(copy, card);
        return true;
    }

    function migrateCollectionQualityCopies(copies, catalogById) {
        if (!Array.isArray(copies)) { return false; }
        var changed = false;
        copies.forEach(function (copy) {
            if (!copy || typeof copy !== 'object') { return; }
            var card = catalogById && typeof catalogById === 'object'
                ? catalogById[String(copy.cardId || '')]
                : null;
            if (!card) { return; }
            if (migrateCollectionQualityCopy(copy, card)) {
                changed = true;
            }
        });
        return changed;
    }

    var api = Object.freeze({
        configure: configure,
        migrateCollectionQualityCopy: migrateCollectionQualityCopy,
        migrateCollectionQualityCopies: migrateCollectionQualityCopies
    });

    app.data.migrations = api;
})(window);
