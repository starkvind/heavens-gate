(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.combat = app.combat || {};

    var config = {
        getState: function () { return null; },
        clampInt: function (value, fallback) {
            var n = Number(value);
            return Number.isFinite(n) ? Math.round(n) : fallback;
        },
        rarityOrder: function () { return []; },
        copyRarity: function () { return 'common'; },
        rollStat: function (min) { return min; },
        advancedRules: function () { return {}; },
        moveBuffMaxRatio: function () { return 2; },
        moveDebuffMinRatio: function () { return 0.35; },
        dailyBossStigmaticDamageMultiplier: function () { return 1; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function rarityRank(rarity) {
        var index = (config.rarityOrder() || []).indexOf(String(rarity || 'common'));
        return index === -1 ? 0 : index;
    }

    function rarityShieldCount(rarity) {
        var map = config.advancedRules().rarityShields || {};
        return Math.max(0, config.clampInt(map[String(rarity || 'common')], rarityRank(rarity) + 1));
    }

    function combatDifficultyRank(key) {
        var order = ['apprentice', 'hobbyist', 'expert', 'master', 'nemesis'];
        var index = order.indexOf(String(key || 'apprentice'));
        return index === -1 ? 0 : index;
    }

    function recalculateCombatStats(unit) {
        var atkBuff;
        var atkDebuff;
        var defBuff;
        var defDebuff;
        var atkRatio;
        var defRatio;
        if (!unit) { return; }
        atkBuff = unit.combatBuffs && Number(unit.combatBuffs.atk) || 0;
        atkDebuff = unit.combatDebuffs && Number(unit.combatDebuffs.atk) || 0;
        defBuff = unit.combatBuffs && Number(unit.combatBuffs.def) || 0;
        defDebuff = unit.combatDebuffs && Number(unit.combatDebuffs.def) || 0;
        atkRatio = Math.max(config.moveDebuffMinRatio(), Math.min(config.moveBuffMaxRatio(), 1 + atkBuff - atkDebuff));
        defRatio = Math.max(config.moveDebuffMinRatio(), Math.min(config.moveBuffMaxRatio(), 1 + defBuff - defDebuff));
        unit.atk = Math.max(1, Math.round(unit.baseAtk * atkRatio));
        unit.def = Math.max(1, Math.round(unit.baseDef * defRatio));
    }

    function applyCombatModifier(unit, statKey, amount, mode, limitRatio) {
        var currentBuff;
        var maxBuffRatio;
        var nextBuff;
        var currentDebuff;
        var minDebuffRatio;
        var nextDebuff;
        if (!unit || (statKey !== 'atk' && statKey !== 'def')) { return 0; }
        amount = Math.max(0, Number(amount) || 0);
        if (!amount) { return 0; }
        if (mode === 'buff') {
            currentBuff = unit.combatBuffs && Number(unit.combatBuffs[statKey]) || 0;
            maxBuffRatio = Math.max(1, Number(limitRatio) || config.moveBuffMaxRatio());
            nextBuff = Math.min(maxBuffRatio - 1, currentBuff + amount);
            unit.combatBuffs[statKey] = nextBuff;
            recalculateCombatStats(unit);
            return Math.max(0, nextBuff - currentBuff);
        }
        currentDebuff = unit.combatDebuffs && Number(unit.combatDebuffs[statKey]) || 0;
        minDebuffRatio = Math.max(0, Math.min(1, Number(limitRatio) || config.moveDebuffMinRatio()));
        nextDebuff = Math.min(1 - minDebuffRatio, currentDebuff + amount);
        unit.combatDebuffs[statKey] = nextDebuff;
        if (unit.aiMemory && config.getState().combat && config.getState().combat.ai) {
            unit.aiMemory.lastDebuffedTurn = Math.max(0, config.clampInt(config.getState().combat.ai.turnNumber, 0));
        }
        recalculateCombatStats(unit);
        return Math.max(0, nextDebuff - currentDebuff);
    }

    function clearCombatDebuffs(unit) {
        if (!unit) { return; }
        unit.combatDebuffs = { atk: 0, def: 0 };
        recalculateCombatStats(unit);
    }

    function healCombatUnit(unit, amount) {
        var before;
        if (!unit) { return 0; }
        amount = Math.max(0, Math.round(Number(amount) || 0));
        if (!amount) { return 0; }
        before = unit.hp;
        unit.hp = Math.min(unit.maxHp, unit.hp + amount);
        return unit.hp - before;
    }

    function healDefendingUnit(unit) {
        var amount = Math.max(1, Math.round(unit.maxHp * config.advancedRules().defendHealRatio));
        var before = unit.hp;
        unit.hp = Math.min(unit.maxHp, unit.hp + amount);
        return unit.hp - before;
    }

    function breakCombatShields(unit, amount) {
        var before;
        if (!unit) { return 0; }
        before = Math.max(0, config.clampInt(unit.shields, 0));
        unit.shields = Math.max(0, before - Math.max(0, config.clampInt(amount, 0)));
        return before - unit.shields;
    }

    function effectiveDef(unit) {
        return Math.round((unit.def || 0) * (unit.defending ? config.advancedRules().defendDefMultiplier : 1));
    }

    function applyCombatDamage(target, amount) {
        target.hp = Math.max(0, target.hp - amount);
        target.defending = false;
        if (target.hp <= 0) {
            target.defeated = true;
        }
    }

    function combatDamageForAttackValue(attacker, defender, attackValue) {
        var base = Math.max(1, Math.round((attackValue || 0) - effectiveDef(defender)));
        var rarityDiff = rarityRank(attacker.card && attacker.card.card_rarity) - rarityRank(defender.card && defender.card.card_rarity);
        var rules = config.advancedRules();
        var multiplier = rarityDiff >= 0
            ? 1 + (rarityDiff * rules.rarityAdvantageStep)
            : Math.max(rules.rarityDisadvantageMinMultiplier, 1 + (rarityDiff * rules.rarityDisadvantageStep));
        var randomExtra = Math.max(1, Math.round(config.rollStat(rules.damageRandomBonusMin, rules.damageRandomBonusMax) * multiplier));
        var damage = Math.max(1, base + randomExtra);
        if (config.getState().combat && config.getState().combat.mode === 'daily-boss' && attacker.side === 'enemy' && config.copyRarity(defender.copy, defender.card) === 'stigmatic') {
            damage = Math.max(1, damage * config.dailyBossStigmaticDamageMultiplier());
        }
        return damage;
    }

    function combatDamage(attacker, defender) {
        return combatDamageForAttackValue(attacker, defender, attacker && attacker.atk || 0);
    }

    function combatMoveDamage(attacker, defender, move) {
        var attackValue = attacker.atk || 0;
        if (!attacker || !defender || !move) { return 0; }
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
        var broken;
        var recoil;
        var healed;
        var atkBuff;
        var defBuff;
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
                broken = breakCombatShields(defender, effect.amount || 1);
                if (broken > 0) {
                    log.push('El golpe rompe ' + broken + ' escudo de ' + defender.card.card_name + '.');
                }
            }
        } else if (effect.kind === 'recoil') {
            recoil = Math.max(1, Math.round(Math.max(0, damage) * Math.max(0, Number(effect.ratio) || 0)));
            applyCombatDamage(attacker, recoil);
            log.push(attacker.card.card_name + ' recibe ' + recoil + ' PS de recoil.');
        } else if (effect.kind === 'lifesteal') {
            healed = healCombatUnit(attacker, Math.round(Math.max(0, damage) * Math.max(0, Number(effect.ratio) || 0)));
            if (healed > 0) {
                log.push(attacker.card.card_name + ' recupera ' + healed + ' PS.');
            }
        } else if (effect.kind === 'buff_atk_def') {
            atkBuff = applyCombatModifier(attacker, 'atk', effect.amount, 'buff', effect.maxRatio);
            defBuff = applyCombatModifier(attacker, 'def', effect.amount, 'buff', effect.maxRatio);
            if (atkBuff > 0 || defBuff > 0) {
                log.push(attacker.card.card_name + ' refuerza su postura: ATQ ' + attacker.atk + ', DEF ' + attacker.def + '.');
            } else {
                log.push(attacker.card.card_name + ' ya esta en el maximo de ' + (move.label || 'la habilidad') + '.');
            }
        }
        return log;
    }

    var api = Object.freeze({
        configure: configure,
        rarityRank: rarityRank,
        rarityShieldCount: rarityShieldCount,
        combatDifficultyRank: combatDifficultyRank,
        recalculateCombatStats: recalculateCombatStats,
        applyCombatModifier: applyCombatModifier,
        clearCombatDebuffs: clearCombatDebuffs,
        healCombatUnit: healCombatUnit,
        healDefendingUnit: healDefendingUnit,
        breakCombatShields: breakCombatShields,
        effectiveDef: effectiveDef,
        applyCombatDamage: applyCombatDamage,
        combatDamageForAttackValue: combatDamageForAttackValue,
        combatDamage: combatDamage,
        combatMoveDamage: combatMoveDamage,
        applyMoveEffect: applyMoveEffect
    });

    app.combat.rules = api;
})(window);
