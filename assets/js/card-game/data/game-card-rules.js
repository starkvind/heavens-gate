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
        getCombatAdvancedRules: function () { return {}; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
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

    function normalizeRewardRangeConfig(configValue, fallbackMin, fallbackMax) {
        var min = config.clampInt(configValue && configValue.min, fallbackMin);
        var max = config.clampInt(configValue && configValue.max, fallbackMax);
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
                amount: Math.max(1, config.clampInt(entry.amount, 1))
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

    function normalizeCombatAdvancedRules(nextRules) {
        if (!nextRules || typeof nextRules !== 'object') { return null; }
        var currentRules = typeof config.getCombatAdvancedRules === 'function' ? (config.getCombatAdvancedRules() || {}) : {};
        var rarityShields = assignObjectValue(currentRules.rarityShields, nextRules.rarity_shields);
        return {
            defendHealRatio: Math.max(0, Number(nextRules.defend_heal_ratio) || currentRules.defendHealRatio || 0),
            defendDefMultiplier: Math.max(1, Number(nextRules.defend_def_multiplier) || currentRules.defendDefMultiplier || 1),
            enemyDefendHpRatio: Math.max(0, Math.min(1, Number(nextRules.enemy_defend_hp_ratio) || currentRules.enemyDefendHpRatio || 0)),
            enemyDefendChance: Math.max(0, Math.min(1, Number(nextRules.enemy_defend_chance) || currentRules.enemyDefendChance || 0)),
            enemyPickAttempts: Math.max(1, config.clampInt(nextRules.enemy_pick_attempts, currentRules.enemyPickAttempts || 1)),
            damageRandomBonusMin: Math.max(0, config.clampInt(nextRules.damage_random_bonus_min, currentRules.damageRandomBonusMin || 0)),
            damageRandomBonusMax: Math.max(0, config.clampInt(nextRules.damage_random_bonus_max, currentRules.damageRandomBonusMax || 0)),
            rarityAdvantageStep: Math.max(0, Number(nextRules.rarity_advantage_step) || currentRules.rarityAdvantageStep || 0),
            rarityDisadvantageStep: Math.max(0, Number(nextRules.rarity_disadvantage_step) || currentRules.rarityDisadvantageStep || 0),
            rarityDisadvantageMinMultiplier: Math.max(0, Math.min(1, Number(nextRules.rarity_disadvantage_min_multiplier) || currentRules.rarityDisadvantageMinMultiplier || 0)),
            rarityShields: rarityShields
        };
    }

    var api = Object.freeze({
        configure: configure,
        hasOwn: hasOwn,
        assignObjectValue: assignObjectValue,
        assignArrayValue: assignArrayValue,
        normalizeRewardRangeConfig: normalizeRewardRangeConfig,
        normalizeDropConfigList: normalizeDropConfigList,
        normalizeCombatDifficultyTable: normalizeCombatDifficultyTable,
        normalizeCombatAdvancedRules: normalizeCombatAdvancedRules
    });

    app.data.rules = api;
})(window);
