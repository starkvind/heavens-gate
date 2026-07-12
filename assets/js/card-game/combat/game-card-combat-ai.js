(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.combat = app.combat || {};

    var config = {
        getState: function () { return null; },
        uiText: function (key, fallback) { return fallback || key; },
        clampInt: function (value, fallback) {
            var n = Number(value);
            return Number.isFinite(n) ? Math.round(n) : fallback;
        },
        activeCombatUnit: function () { return null; },
        livingCombatIndexes: function () { return []; },
        moveCooldownRemaining: function () { return 0; },
        setMoveCooldown: function () {},
        clearCombatDebuffs: function () {},
        healDefendingUnit: function () { return 0; },
        applyCombatDamage: function () {},
        combatDamage: function () { return 0; },
        combatMoveDamage: function () { return 0; },
        applyMoveEffect: function () { return []; },
        effectiveDef: function () { return 0; },
        combatDifficultyConfig: function () { return { key: 'apprentice' }; },
        combatDifficultyRank: function () { return 0; },
        combatEnemyTrainerName: function () { return 'El rival'; },
        playSkillSound: function () {},
        pushCombatLog: function () {},
        advancedRules: function () { return {}; },
        dailyBossShieldBreakChance: function () { return 0; },
        markDailyBossCopyDefeated: function () {}
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function currentCombatDifficultyKey() {
        var state = config.getState();
        return state.combat && state.combat.difficultyKey
            ? String(state.combat.difficultyKey)
            : String((config.combatDifficultyConfig() && config.combatDifficultyConfig().key) || 'apprentice');
    }

    function currentCombatDifficultyRank() {
        return config.combatDifficultyRank(currentCombatDifficultyKey());
    }

    function currentCombatTurnNumber() {
        var state = config.getState();
        return state.combat && state.combat.ai ? config.clampInt(state.combat.ai.turnNumber, 0) : 0;
    }

    function enemyUsableMoves(enemy) {
        if (!enemy || !Array.isArray(enemy.moves)) { return []; }
        return enemy.moves.filter(function (move) {
            if (!move || config.moveCooldownRemaining(enemy, move) > 0) { return false; }
            if (move.type === 'damage') { return move.target === 'enemy'; }
            if (move.type === 'buff') { return move.target === 'self'; }
            return false;
        });
    }

    function enemyMoveScore(move, enemy, player) {
        var difficultyRank;
        var maxRatio;
        var atkCap;
        var currentAtkBuff;
        var score;
        var effect;
        if (!move || !enemy || !player) { return -1; }
        difficultyRank = currentCombatDifficultyRank();
        if (move.type === 'buff') {
            maxRatio = Math.max(1, Number(move.effect && move.effect.maxRatio) || 2 || 1);
            atkCap = Math.max(0, maxRatio - 1);
            currentAtkBuff = enemy.combatBuffs && Number(enemy.combatBuffs.atk) || 0;
            if (currentAtkBuff >= atkCap) { return -1; }
            return 8 + (difficultyRank * 3) + ((enemy.hp / Math.max(1, enemy.maxHp)) * (difficultyRank >= 3 ? 5 : 2));
        }
        score = config.combatMoveDamage(enemy, player, move);
        if (score >= player.hp) { score += 18 + (difficultyRank * 8); }
        effect = move.effect || {};
        if (difficultyRank >= 2 && effect.kind === 'debuff_atk' && player.atk > Math.round(player.baseAtk * 0.35)) { score += 4 + difficultyRank; }
        if (difficultyRank >= 2 && effect.kind === 'debuff_def' && player.def > Math.round(player.baseDef * 0.35)) { score += 4 + difficultyRank; }
        if (difficultyRank >= 1 && effect.kind === 'shield_break' && player.shields > 0) { score += 3 + difficultyRank; }
        if (difficultyRank >= 2 && effect.kind === 'lifesteal' && enemy.hp < enemy.maxHp) { score += 2 + difficultyRank; }
        if (effect.kind === 'recoil') { score -= difficultyRank >= 4 ? 0 : 4; }
        return score;
    }

    function pickEnemyMove(enemy, player) {
        var available = enemyUsableMoves(enemy);
        var difficultyRank;
        var best = null;
        var bestScore;
        if (!available.length) { return null; }
        difficultyRank = currentCombatDifficultyRank();
        if (difficultyRank <= 0) {
            return Math.random() < 0.35 ? available[Math.floor(Math.random() * available.length)] : null;
        }
        if (difficultyRank === 1 && Math.random() < 0.45) {
            return available[Math.floor(Math.random() * available.length)];
        }
        bestScore = config.combatDamage(enemy, player);
        available.forEach(function (move) {
            var score = enemyMoveScore(move, enemy, player);
            if (score > bestScore) {
                best = move;
                bestScore = score;
            }
        });
        return best;
    }

    function enemySwitchCandidates() {
        var state = config.getState();
        var currentTurn;
        if (!state.combat) { return []; }
        currentTurn = currentCombatTurnNumber();
        return state.combat.enemy.map(function (unit, index) {
            return { unit: unit, index: index };
        }).filter(function (entry) {
            return entry.unit
                && entry.index !== state.combat.enemyActive
                && !entry.unit.defeated
                && entry.unit.hp > 0
                && (!entry.unit.aiMemory || config.clampInt(entry.unit.aiMemory.switchLockUntilTurn, -1) < currentTurn);
        });
    }

    function enemySwitchScore(candidate, player) {
        var unit;
        var hpRatio;
        var defenseValue;
        var attackValue;
        if (!candidate || !player) { return -9999; }
        unit = candidate.unit;
        hpRatio = unit.hp / Math.max(1, unit.maxHp);
        defenseValue = config.effectiveDef(unit);
        attackValue = config.combatDamage(unit, player);
        return (hpRatio * 18) + (defenseValue * 0.55) + (attackValue * 0.35) + (unit.shields * 5);
    }

    function pickEnemySwitch(enemy, player) {
        var difficultyRank = currentCombatDifficultyRank();
        var state = config.getState();
        var currentTurn;
        var candidates;
        var currentHpRatio;
        var currentPressure;
        var currentDefense;
        var desperate;
        var justDebuffed;
        var best = null;
        var bestScore = -9999;
        var bestUnit;
        var betterDefense;
        var saferHp;
        var strongerPressure;
        if (difficultyRank <= 1) { return null; }
        currentTurn = currentCombatTurnNumber();
        if (state.combat && state.combat.ai && config.clampInt(state.combat.ai.enemySwitchCooldownUntilTurn, -1) >= currentTurn) {
            return null;
        }
        candidates = enemySwitchCandidates();
        if (!candidates.length) { return null; }
        currentHpRatio = enemy.hp / Math.max(1, enemy.maxHp);
        currentPressure = config.combatDamage(enemy, player);
        currentDefense = Math.max(config.effectiveDef(enemy), enemy.baseDef || 0);
        desperate = currentHpRatio <= (difficultyRank >= 4 ? 0.45 : (difficultyRank >= 3 ? 0.36 : 0.3))
            || (enemy.shields === 0 && currentHpRatio <= (difficultyRank >= 4 ? 0.55 : 0.42));
        justDebuffed = enemy.aiMemory && (currentTurn - config.clampInt(enemy.aiMemory.lastDebuffedTurn, -999)) <= 0;
        if (justDebuffed && !desperate) { return null; }
        candidates.forEach(function (candidate) {
            var score = enemySwitchScore(candidate, player);
            if (score > bestScore) {
                best = candidate;
                bestScore = score;
            }
        });
        if (!best) { return null; }
        bestUnit = best.unit;
        betterDefense = config.effectiveDef(bestUnit) >= currentDefense + (difficultyRank >= 4 ? 6 : (difficultyRank >= 3 ? 9 : 12));
        saferHp = (bestUnit.hp / Math.max(1, bestUnit.maxHp)) >= currentHpRatio + (difficultyRank >= 4 ? 0.1 : (difficultyRank >= 3 ? 0.16 : 0.22));
        strongerPressure = config.combatDamage(bestUnit, player) >= currentPressure + (difficultyRank >= 4 ? 6 : (difficultyRank >= 3 ? 10 : 14));
        if (!desperate && !betterDefense && !saferHp && !strongerPressure) { return null; }
        if (!desperate && bestScore < ((currentHpRatio * 18) + (currentDefense * 0.55) + (currentPressure * 0.35) + (enemy.shields * 5) + (difficultyRank >= 4 ? 2 : (difficultyRank >= 3 ? 6 : 10)))) {
            return null;
        }
        if (difficultyRank === 2 && Math.random() < 0.25) { return null; }
        return best;
    }

    function lockEnemySwitchState(outgoingUnit) {
        var state = config.getState();
        var currentTurn;
        var difficultyRank;
        if (!state.combat || !state.combat.ai || !outgoingUnit) { return; }
        currentTurn = currentCombatTurnNumber();
        difficultyRank = currentCombatDifficultyRank();
        state.combat.ai.enemySwitchCooldownUntilTurn = currentTurn + (difficultyRank >= 4 ? 1 : 2);
        outgoingUnit.aiMemory = outgoingUnit.aiMemory || {};
        outgoingUnit.aiMemory.switchLockUntilTurn = currentTurn + (difficultyRank >= 4 ? 2 : 3);
    }

    function enemyTurn() {
        var state = config.getState();
        var enemy;
        var player;
        var difficultyRank;
        var switchPick;
        var shouldDefend;
        var healed;
        var move;
        var enemyAccuracyBonus;
        var moveDamage;
        var damage;
        if (!state.combat || state.combat.over) { return null; }
        enemy = config.activeCombatUnit('enemy');
        player = config.activeCombatUnit('player');
        if (!enemy || !player) { return null; }
        enemy.defending = false;
        difficultyRank = currentCombatDifficultyRank();
        switchPick = pickEnemySwitch(enemy, player);
        if (switchPick) {
            config.clearCombatDebuffs(enemy);
            lockEnemySwitchState(enemy);
            state.combat.enemyActive = switchPick.index;
            config.pushCombatLog(config.combatEnemyTrainerName() + ' cambia a ' + switchPick.unit.card.card_name + '.');
            return { type: 'switch', side: 'enemy', index: switchPick.index };
        }
        shouldDefend = enemy.shields > 0
            && enemy.hp < enemy.maxHp * (difficultyRank >= 4 ? 0.52 : (difficultyRank >= 3 ? 0.44 : config.advancedRules().enemyDefendHpRatio))
            && Math.random() < (difficultyRank <= 0 ? 0.46 : (difficultyRank === 1 ? 0.4 : (difficultyRank === 2 ? 0.34 : (difficultyRank === 3 ? 0.28 : 0.22))));
        if (shouldDefend) {
            enemy.shields = Math.max(0, enemy.shields - 1);
            enemy.defending = true;
            healed = config.healDefendingUnit(enemy);
            config.pushCombatLog(enemy.card.card_name + ' gasta 1 escudo, defiende y recupera ' + healed + ' PS.');
            return { type: 'defend', side: 'enemy' };
        }
        move = pickEnemyMove(enemy, player);
        if (move) {
            config.setMoveCooldown(enemy, move);
            config.playSkillSound();
            enemyAccuracyBonus = difficultyRank >= 4 ? 0.12 : (difficultyRank === 3 ? 0.07 : (difficultyRank === 2 ? 0.03 : 0));
            if (Math.random() > Math.min(1, move.accuracy + enemyAccuracyBonus)) {
                config.pushCombatLog(enemy.card.card_name + ' usa ' + move.label + ', pero falla.');
                return { type: 'move_miss', side: 'enemy', move: move };
            }
            if (move.type === 'buff') {
                config.pushCombatLog(enemy.card.card_name + ' adopta ' + move.label + '.');
                config.applyMoveEffect(move, enemy, player, 0).forEach(config.pushCombatLog);
                return { type: 'move_buff', side: 'enemy', move: move };
            }
            moveDamage = config.combatMoveDamage(enemy, player, move);
            config.applyCombatDamage(player, moveDamage);
            config.pushCombatLog(enemy.card.card_name + ' usa ' + move.label + ' e inflige ' + moveDamage + ' PS.');
            config.applyMoveEffect(move, enemy, player, moveDamage).forEach(config.pushCombatLog);
            if (player.defeated) {
                if (state.combat.mode === 'daily-boss') {
                    config.markDailyBossCopyDefeated(player.copy && player.copy.instanceId);
                }
                config.pushCombatLog(player.card.card_name + ' cae.');
            }
            return { type: 'move_attack', side: 'enemy', target: 'player', move: move, damage: moveDamage, defeatedTarget: player.defeated };
        }
        damage = config.combatDamage(enemy, player);
        config.applyCombatDamage(player, damage);
        if (state.combat.mode === 'daily-boss' && player.shields > 0 && Math.random() < config.dailyBossShieldBreakChance()) {
            player.shields = Math.max(0, player.shields - 1);
            config.pushCombatLog(config.uiText('combat.daily_shield_break_log', 'El impacto del Jefe diario quiebra 1 escudo de {card}.', { card: player.card.card_name }));
        }
        config.pushCombatLog(enemy.card.card_name + ' ataca e inflige ' + damage + ' PS.');
        if (player.defeated) {
            config.markDailyBossCopyDefeated(player.copy && player.copy.instanceId);
            config.pushCombatLog(player.card.card_name + ' cae.');
        }
        return { type: 'attack', side: 'enemy', target: 'player', damage: damage, defeatedTarget: player.defeated };
    }

    var api = Object.freeze({
        configure: configure,
        currentCombatDifficultyKey: currentCombatDifficultyKey,
        currentCombatDifficultyRank: currentCombatDifficultyRank,
        currentCombatTurnNumber: currentCombatTurnNumber,
        enemyUsableMoves: enemyUsableMoves,
        enemyMoveScore: enemyMoveScore,
        pickEnemyMove: pickEnemyMove,
        enemySwitchCandidates: enemySwitchCandidates,
        enemySwitchScore: enemySwitchScore,
        pickEnemySwitch: pickEnemySwitch,
        lockEnemySwitchState: lockEnemySwitchState,
        enemyTurn: enemyTurn
    });

    app.combat.ai = api;
})(window);
