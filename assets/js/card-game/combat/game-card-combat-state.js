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
        normalizeCopyMoveIds: function (value) { return Array.isArray(value) ? value : []; },
        cloneMoveDefinition: function (value) { return value; },
        moveLibrary: function () { return {}; },
        rarityShieldCount: function () { return 0; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function normalizeCombatMoves(card, copy) {
        var copyMoveIds = config.normalizeCopyMoveIds(copy && copy.moves);
        var library = config.moveLibrary() || {};
        if (copyMoveIds.length) {
            return copyMoveIds.map(function (moveId) {
                return config.cloneMoveDefinition(library[moveId]);
            }).filter(Boolean).slice(0, 3);
        }
        return (card && Array.isArray(card.moves) ? card.moves : []).map(function (move) {
            return config.cloneMoveDefinition(move);
        }).filter(Boolean).slice(0, 3);
    }

    function createMoveState(moves) {
        var state = {};
        (moves || []).forEach(function (move) {
            state[move.id] = { cooldownRemaining: 0 };
        });
        return state;
    }

    function createCombatUnit(card, copy, side, index, options) {
        var maxHp;
        var currentHp;
        var shields;
        var moves;
        var baseAtk;
        var baseDef;
        options = options || {};
        maxHp = Math.max(1, config.clampInt(options.maxHp || copy.hp, 1));
        currentHp = Math.max(1, Math.min(maxHp, config.clampInt(options.currentHp || copy.hp, maxHp)));
        shields = options.noShields ? 0 : config.rarityShieldCount(card && card.card_rarity);
        moves = normalizeCombatMoves(card, copy);
        baseAtk = Math.max(1, config.clampInt(copy.atk, 1));
        baseDef = Math.max(1, config.clampInt(copy.def, 1));
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
            aiMemory: {
                lastDebuffedTurn: -999,
                switchLockUntilTurn: -1
            },
            defending: false,
            defeated: false
        };
    }

    function activeCombatUnit(side) {
        var state = config.getState();
        var list;
        var index;
        if (!state.combat) { return null; }
        list = side === 'enemy' ? state.combat.enemy : state.combat.player;
        index = side === 'enemy' ? state.combat.enemyActive : state.combat.playerActive;
        return list[index] || null;
    }

    function livingCombatIndexes(side) {
        var state = config.getState();
        var list;
        if (!state.combat) { return []; }
        list = side === 'enemy' ? state.combat.enemy : state.combat.player;
        return list.map(function (unit, index) {
            return unit && !unit.defeated && unit.hp > 0 ? index : -1;
        }).filter(function (index) { return index >= 0; });
    }

    function findUnitMove(unit, moveId) {
        var i;
        if (!unit || !Array.isArray(unit.moves)) { return null; }
        moveId = String(moveId || '');
        for (i = 0; i < unit.moves.length; i++) {
            if (String(unit.moves[i].id) === moveId) { return unit.moves[i]; }
        }
        return null;
    }

    function moveCooldownRemaining(unit, move) {
        if (!unit || !move || !unit.moveState || !unit.moveState[move.id]) { return 0; }
        return Math.max(0, config.clampInt(unit.moveState[move.id].cooldownRemaining, 0));
    }

    function setMoveCooldown(unit, move) {
        if (!unit || !move || !unit.moveState || !unit.moveState[move.id]) { return; }
        unit.moveState[move.id].cooldownRemaining = Math.max(0, config.clampInt(move.cooldown, 0));
    }

    function reduceMoveCooldowns(unit) {
        if (!unit || !unit.moveState) { return; }
        Object.keys(unit.moveState).forEach(function (id) {
            unit.moveState[id].cooldownRemaining = Math.max(0, config.clampInt(unit.moveState[id].cooldownRemaining, 0) - 1);
        });
    }

    var api = Object.freeze({
        configure: configure,
        normalizeCombatMoves: normalizeCombatMoves,
        createMoveState: createMoveState,
        createCombatUnit: createCombatUnit,
        activeCombatUnit: activeCombatUnit,
        livingCombatIndexes: livingCombatIndexes,
        findUnitMove: findUnitMove,
        moveCooldownRemaining: moveCooldownRemaining,
        setMoveCooldown: setMoveCooldown,
        reduceMoveCooldowns: reduceMoveCooldowns
    });

    app.combat.state = api;
})(window);
