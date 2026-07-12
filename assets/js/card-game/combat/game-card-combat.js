(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.combat = app.combat || {};

    var config = {
        getState: function () { return null; },
        uiText: function (key, fallback, values) {
            var text = fallback || key;
            return values ? text.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
                return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
            }) : text;
        },
        clampInt: function (value, fallback) {
            var n = Number(value);
            return Number.isFinite(n) ? Math.round(n) : fallback;
        },
        activeCombatUnit: function () { return null; },
        livingCombatIndexes: function () { return []; },
        findUnitMove: function () { return null; },
        moveCooldownRemaining: function () { return 0; },
        setMoveCooldown: function () {},
        reduceMoveCooldowns: function () {},
        clearCombatDebuffs: function () {},
        healDefendingUnit: function () { return 0; },
        applyCombatDamage: function () {},
        combatDamage: function () { return 0; },
        combatMoveDamage: function () { return 0; },
        applyMoveEffect: function () { return []; },
        setCombatBusy: function () {},
        setCombatMessage: function () {},
        pushCombatLog: function () {},
        renderCombatBattle: function () {},
        renderCombat: function () {},
        animateCombatAttack: function () {},
        animateCombatDefend: function () {},
        animateCombatDefeat: function () {},
        animateCombatEntry: function () {},
        animateEnemyAction: function () {},
        enemyTurn: function () { return null; },
        playSkillSound: function () {},
        playCombatSound: function () {},
        playMoveVfx: function () {},
        updateDailyBossHp: function () {},
        markDailyBossCopyDefeated: function () {},
        combatEnemyTrainerName: function () { return 'El rival'; },
        combatPlayerName: function () { return 'Jugador'; },
        awardTrainingVictory: function () { return 0; },
        awardDailyBossVictory: function () { return null; },
        destroyDailyBossTeam: function () { return 0; },
        dailyBossLootText: function () { return ''; },
        combatTurnGapMs: function () { return 220; },
        combatAttackMs: function () { return 620; },
        combatDefendMs: function () { return 620; },
        combatEntryMs: function () { return 620; },
        combatDefeatMs: function () { return 700; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function advanceDefeatedSide(side) {
        var state = config.getState();
        var living;
        var reward;
        var destroyed;
        if (!state.combat) { return false; }
        living = config.livingCombatIndexes(side);
        if (!living.length) {
            state.activeCombatScreen = 'battle';
            state.combat.over = true;
            if (side === 'enemy') {
                state.combat.enemyActive = -1;
            } else {
                state.combat.playerActive = -1;
            }
            if (side === 'enemy') {
                if (state.combat.mode === 'daily-boss') {
                    reward = config.awardDailyBossVictory();
                    state.combat.dailyBossRewardData = reward || null;
                    state.combat.dailyBossCasualties = reward && reward.casualties ? config.clampInt(reward.casualties, 0) : 0;
                    state.combat.reward = reward && reward.copy ? reward.copy.instanceId : '';
                    state.combat.result = 'victory';
                    if (reward && reward.alreadyClaimed) {
                        config.setCombatMessage(config.uiText('combat.daily_reward_already', 'Victoria contra el Jefe diario. La recompensa diaria ya fue reclamada.'));
                        config.pushCombatLog(config.uiText('combat.daily_reward_already_log', 'Has derrotado al Jefe diario, pero la carta Estigmática de hoy ya fue reclamada.'));
                    } else if (reward && reward.card) {
                        config.setCombatMessage(config.uiText('combat.daily_victory_reward', 'Victoria contra el Jefe diario. Obtienes {card} Estigmático y botín: {loot}.', { card: reward.card.card_name, loot: config.dailyBossLootText(reward.loot) }));
                        config.pushCombatLog(config.uiText('combat.daily_victory_reward_log', 'Has derrotado al Jefe diario. Obtienes {card} Estigmático.', { card: reward.card.card_name }));
                        config.pushCombatLog(config.uiText('combat.daily_loot_log', 'Botín adicional: {loot}.', { loot: config.dailyBossLootText(reward.loot) }));
                        if (reward.casualties > 0) {
                            config.pushCombatLog(config.uiText('combat.daily_casualties_log', 'Cartas caídas durante el desafío: {count}.', { count: reward.casualties }));
                        }
                    } else {
                        config.setCombatMessage(config.uiText('combat.daily_victory', 'Victoria contra el Jefe diario.'));
                    }
                } else {
                    reward = config.awardTrainingVictory();
                    state.combat.reward = reward;
                    state.combat.result = 'victory';
                    config.setCombatMessage(config.uiText('combat.training_victory', 'Victoria de entrenamiento. +{reward} Mnemones.', { reward: reward }));
                    config.pushCombatLog('Has vencido a ' + config.combatEnemyTrainerName() + '. Ganas ' + reward + ' Mnemones.');
                }
            } else {
                state.combat.result = 'defeat';
                state.combat.reward = 0;
                destroyed = state.combat.mode === 'daily-boss' ? config.destroyDailyBossTeam() : 0;
                state.combat.dailyBossCasualties = state.combat.mode === 'daily-boss' ? config.clampInt(destroyed, 0) : 0;
                config.setCombatMessage(state.combat.mode === 'daily-boss'
                    ? config.uiText('combat.daily_defeat_casualties', 'Derrota contra el Jefe diario. Cartas derrotadas perdidas: {count}.', { count: destroyed })
                    : 'Derrota de entrenamiento.');
                config.pushCombatLog(config.uiText('combat.training_defeat_log', 'Tu equipo ha caído. No pierdes cartas en entrenamiento.'));
            }
            return false;
        }
        if (side === 'enemy') {
            state.combat.enemyActive = living[0];
            config.pushCombatLog(config.combatEnemyTrainerName() + ' saca una carta.');
        } else {
            state.combat.playerActive = living[0];
            config.pushCombatLog(config.combatPlayerName() + ' saca una carta.');
        }
        return true;
    }

    function resolveDefeatedSide(side, done) {
        global.setTimeout(function () {
            if (!config.getState().combat) {
                if (done) { done(); }
                return;
            }
            config.animateCombatDefeat(side);
            global.setTimeout(function () {
                var advanced = advanceDefeatedSide(side);
                config.renderCombatBattle();
                if (advanced) {
                    config.animateCombatEntry(side);
                    global.setTimeout(function () {
                        if (done) { done(); }
                    }, config.combatEntryMs());
                    return;
                }
                if (done) { done(); }
            }, config.combatDefeatMs());
        }, config.combatTurnGapMs());
    }

    function completePlayerTurn() {
        var state = config.getState();
        if (state.combat && !state.combat.over) {
            if (state.combat.ai) {
                state.combat.ai.turnNumber = config.clampInt(state.combat.ai.turnNumber, 0) + 1;
            }
            config.reduceMoveCooldowns(config.activeCombatUnit('player'));
            config.reduceMoveCooldowns(config.activeCombatUnit('enemy'));
            config.renderCombatBattle();
        }
    }

    function finishEnemyAction(action) {
        global.setTimeout(function () {
            if (action && action.defeatedTarget && config.getState().combat && !config.getState().combat.over) {
                resolveDefeatedSide('player', function () {
                    completePlayerTurn();
                    config.setCombatBusy(false);
                });
                return;
            }
            completePlayerTurn();
            config.setCombatBusy(false);
        }, action && action.type === 'switch' ? config.combatEntryMs() : (action && (action.type === 'defend' || action.type === 'move_buff') ? config.combatDefendMs() : config.combatAttackMs()));
    }

    function playerAttack() {
        var state = config.getState();
        var player;
        var enemy;
        var damage;
        var defeatedEnemy;
        if (!state.combat || state.combat.over || state.combatAnimating) { return; }
        player = config.activeCombatUnit('player');
        enemy = config.activeCombatUnit('enemy');
        if (!player || !enemy) { return; }
        config.setCombatBusy(true);
        player.defending = false;
        damage = config.combatDamage(player, enemy);
        config.applyCombatDamage(enemy, damage);
        if (state.combat.mode === 'daily-boss') { config.updateDailyBossHp(enemy.hp); }
        config.pushCombatLog(player.card.card_name + ' ataca y causa ' + damage + ' puntos de daño.');
        defeatedEnemy = enemy.defeated;
        if (defeatedEnemy) { config.pushCombatLog(enemy.card.card_name + ' cae.'); }
        config.renderCombatBattle();
        config.animateCombatAttack('player', 'enemy', damage);
        global.setTimeout(function () {
            var enemyAction;
            if (defeatedEnemy && config.getState().combat && !config.getState().combat.over) {
                resolveDefeatedSide('enemy', function () {
                    completePlayerTurn();
                    config.setCombatBusy(false);
                });
                return;
            }
            if (!config.getState().combat || config.getState().combat.over) {
                config.setCombatBusy(false);
                return;
            }
            enemyAction = config.enemyTurn();
            config.renderCombatBattle();
            config.animateEnemyAction(enemyAction);
            finishEnemyAction(enemyAction);
        }, config.combatAttackMs() + config.combatTurnGapMs());
    }

    function playerDefend() {
        var state = config.getState();
        var player;
        var healed;
        if (!state.combat || state.combat.over || state.combatAnimating) { return; }
        player = config.activeCombatUnit('player');
        if (!player) { return; }
        if (player.shields <= 0) {
            config.setCombatMessage(config.uiText('combat.no_shields', 'Esta carta ya no tiene escudos.'));
            return;
        }
        config.setCombatBusy(true);
        player.shields = Math.max(0, player.shields - 1);
        player.defending = true;
        healed = config.healDefendingUnit(player);
        config.pushCombatLog(player.card.card_name + ' gasta 1 escudo, defiende y recupera ' + healed + ' PS.');
        config.renderCombatBattle();
        config.animateCombatDefend('player');
        global.setTimeout(function () {
            var enemyAction;
            if (!config.getState().combat || config.getState().combat.over) {
                config.setCombatBusy(false);
                return;
            }
            enemyAction = config.enemyTurn();
            config.renderCombatBattle();
            config.animateEnemyAction(enemyAction);
            finishEnemyAction(enemyAction);
        }, config.combatDefendMs());
    }

    function playerUseMove(moveId) {
        var state = config.getState();
        var player;
        var enemy;
        var move;
        var defeatedEnemy = false;
        var defeatedPlayer = false;
        if (!state.combat || state.combat.over || state.combatAnimating) { return; }
        player = config.activeCombatUnit('player');
        enemy = config.activeCombatUnit('enemy');
        if (!player || !enemy) { return; }
        move = config.findUnitMove(player, moveId);
        if (!move) {
            config.setCombatMessage(config.uiText('combat.move_unavailable', 'Movimiento no disponible.'));
            return;
        }
        if (config.moveCooldownRemaining(player, move) > 0) {
            config.setCombatMessage(config.uiText('combat.move_cooldown', 'Movimiento en recarga.'));
            return;
        }
        if (move.type !== 'damage' && move.type !== 'buff') {
            config.setCombatMessage(config.uiText('combat.move_not_implemented', 'Movimiento aún no implementado.'));
            return;
        }
        if ((move.type === 'damage' && move.target !== 'enemy') || (move.type === 'buff' && move.target !== 'self')) {
            config.setCombatMessage(config.uiText('combat.move_not_implemented', 'Movimiento aún no implementado.'));
            return;
        }
        config.setCombatBusy(true);
        player.defending = false;
        config.setMoveCooldown(player, move);
        config.playSkillSound();
        if (Math.random() > move.accuracy) {
            config.pushCombatLog(player.card.card_name + ' usa ' + move.label + ', pero falla.');
            config.renderCombatBattle();
            global.setTimeout(function () {
                var failedEnemyAction;
                if (!config.getState().combat || config.getState().combat.over) {
                    config.setCombatBusy(false);
                    return;
                }
                failedEnemyAction = config.enemyTurn();
                config.renderCombatBattle();
                config.animateEnemyAction(failedEnemyAction);
                finishEnemyAction(failedEnemyAction);
            }, config.combatTurnGapMs());
            return;
        }
        if (move.type === 'damage') {
            var damage = config.combatMoveDamage(player, enemy, move);
            config.applyCombatDamage(enemy, damage);
            if (state.combat.mode === 'daily-boss') { config.updateDailyBossHp(enemy.hp); }
            config.pushCombatLog(player.card.card_name + ' usa ' + move.label + ' y causa ' + damage + ' puntos de dano.');
            config.applyMoveEffect(move, player, enemy, damage).forEach(config.pushCombatLog);
            defeatedEnemy = enemy.defeated;
            defeatedPlayer = player.defeated;
            if (defeatedEnemy) { config.pushCombatLog(enemy.card.card_name + ' cae.'); }
            if (defeatedPlayer) {
                if (state.combat.mode === 'daily-boss') { config.markDailyBossCopyDefeated(player.copy && player.copy.instanceId); }
                config.pushCombatLog(player.card.card_name + ' cae.');
            }
            config.renderCombatBattle();
            config.animateCombatAttack('player', 'enemy', damage);
            config.playMoveVfx(move, 'player', 'enemy');
        } else {
            config.pushCombatLog(player.card.card_name + ' adopta ' + move.label + '.');
            config.applyMoveEffect(move, player, enemy, 0).forEach(config.pushCombatLog);
            config.renderCombatBattle();
            config.animateCombatDefend('player');
            config.playMoveVfx(move, 'player', 'enemy');
        }
        global.setTimeout(function () {
            var enemyAction;
            if (defeatedEnemy && defeatedPlayer && config.getState().combat && !config.getState().combat.over) {
                resolveDefeatedSide('enemy', function () {
                    completePlayerTurn();
                    if (config.getState().combat && !config.getState().combat.over) {
                        var currentPlayer = config.activeCombatUnit('player');
                        if (currentPlayer && currentPlayer.defeated) {
                            resolveDefeatedSide('player', function () { config.setCombatBusy(false); });
                            return;
                        }
                    }
                    config.setCombatBusy(false);
                });
                return;
            }
            if (defeatedEnemy && config.getState().combat && !config.getState().combat.over) {
                resolveDefeatedSide('enemy', function () {
                    completePlayerTurn();
                    config.setCombatBusy(false);
                });
                return;
            }
            if (defeatedPlayer && config.getState().combat && !config.getState().combat.over) {
                resolveDefeatedSide('player', function () { config.setCombatBusy(false); });
                return;
            }
            if (!config.getState().combat || config.getState().combat.over) {
                config.setCombatBusy(false);
                return;
            }
            enemyAction = config.enemyTurn();
            config.renderCombatBattle();
            config.animateEnemyAction(enemyAction);
            finishEnemyAction(enemyAction);
        }, move.type === 'buff' ? config.combatDefendMs() : (config.combatAttackMs() + config.combatTurnGapMs()));
    }

    function switchPlayerCard(index, consumeTurn) {
        var state = config.getState();
        var unit;
        var outgoing;
        if (!state.combat || state.combat.over || state.combatAnimating) { return; }
        index = config.clampInt(index, state.combat.playerActive);
        unit = state.combat.player[index];
        if (!unit || unit.defeated || unit.hp <= 0 || index === state.combat.playerActive) { return; }
        config.setCombatBusy(true);
        outgoing = config.activeCombatUnit('player');
        if (outgoing) {
            outgoing.defending = false;
            config.clearCombatDebuffs(outgoing);
        }
        state.combat.playerActive = index;
        config.pushCombatLog(config.uiText('combat.switch_log', 'Cambias a {card}.', { card: unit.card.card_name }));
        config.playCombatSound('switch');
        config.renderCombatBattle();
        config.animateCombatEntry('player');
        if (!consumeTurn) {
            global.setTimeout(function () {
                config.setCombatBusy(false);
            }, config.combatEntryMs());
            return;
        }
        global.setTimeout(function () {
            var enemyAction;
            if (!config.getState().combat || config.getState().combat.over) {
                config.setCombatBusy(false);
                return;
            }
            enemyAction = config.enemyTurn();
            config.renderCombatBattle();
            config.animateEnemyAction(enemyAction);
            finishEnemyAction(enemyAction);
        }, config.combatEntryMs());
    }

    function fleeCombat() {
        var state = config.getState();
        if (!state.combat || state.combat.over || state.combatAnimating) { return; }
        if (state.combat.mode === 'daily-boss') {
            config.setCombatMessage(config.uiText('combat.no_daily_flee', 'No puedes huir del Jefe diario.'));
            return;
        }
        state.activeCombatScreen = 'battle';
        state.combat.over = true;
        config.pushCombatLog(config.uiText('combat.flee_log', 'Huyes del entrenamiento. Sin coste y sin pérdida de cartas.'));
        config.setCombatMessage(config.uiText('combat.flee_done', 'Combate finalizado porque has huido.'));
        config.renderCombatBattle();
    }

    var api = Object.freeze({
        configure: configure,
        advanceDefeatedSide: advanceDefeatedSide,
        resolveDefeatedSide: resolveDefeatedSide,
        completePlayerTurn: completePlayerTurn,
        finishEnemyAction: finishEnemyAction,
        playerAttack: playerAttack,
        playerDefend: playerDefend,
        playerUseMove: playerUseMove,
        switchPlayerCard: switchPlayerCard,
        fleeCombat: fleeCombat
    });

    app.combat.core = api;
})(window);
