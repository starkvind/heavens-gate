(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.combat = app.combat || {};

    var config = {
        getState: function () { return null; },
        getEls: function () { return {}; },
        getRoot: function () { return null; },
        isCombatContext: function () { return false; },
        isCombatLoadoutVisible: function () { return false; },
        setStatus: function () {},
        uiText: function (key, fallback, values) {
            var text = fallback || key;
            return values ? text.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
                return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
            }) : text;
        },
        escapeHtml: function (value) { return String(value || ''); },
        clampInt: function (value, fallback) {
            var n = Number(value);
            return Number.isFinite(n) ? Math.round(n) : fallback;
        },
        activeCombatUnit: function () { return null; },
        livingCombatIndexes: function () { return []; },
        moveCooldownRemaining: function () { return 0; },
        effectiveDef: function () { return 0; },
        combatEntryFromCopy: function () { return null; },
        combatStatPillHtml: function () { return ''; },
        renderCard: function () { return global.document.createElement('div'); },
        renderCombatSetup: function () {},
        renderCombatBattle: function () {},
        startSelectedCombat: function () {},
        showCombatScreen: function () {},
        switchPlayerCard: function () {},
        renderDailyBossSummary: function () {},
        dailyBossRewardSummary: function () { return ''; },
        applyCombatScreenBackground: function () {},
        combatScreenElement: function () { return null; },
        animateEnemyAction: function () {},
        combatEnemyTrainerName: function () { return 'El rival'; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function setCombatMessage(message) {
        var els = config.getEls();
        if (els.combatMessage) { els.combatMessage.textContent = message; }
        config.setStatus(message);
    }

    function pushCombatLog(message) {
        var state = config.getState();
        if (!state.combat) { return; }
        if (state.combat.mode === 'daily-boss' && String(message || '').indexOf('No pierdes cartas') !== -1) { return; }
        state.combat.log.unshift(message);
        state.combat.log = state.combat.log.slice(0, 8);
    }

    function showCombatCommandView(view) {
        var state = config.getState();
        var els = config.getEls();
        var root = config.getRoot();
        state.combatCommandView = view === 'actions' || view === 'inventory' ? view : 'root';
        if (root) { root.classList.toggle('is-combat-subcommand-open', state.combatCommandView !== 'root'); }
        els.combatCommandViews.forEach(function (node) {
            var active = (node.getAttribute('data-combat-command-view') || 'root') === state.combatCommandView;
            node.hidden = !active;
        });
    }

    function renderCombatMoveSlots(player, active) {
        var els = config.getEls();
        els.combatExtraActionSlots.forEach(function (button, index) {
            var move = player && Array.isArray(player.moves) ? player.moves[index] : null;
            var cooldown;
            if (!move) {
                button.textContent = config.uiText('combat.action_slot', 'Acción {slot}', { slot: index + 1 });
                button.disabled = true;
                button.removeAttribute('data-combat-move');
                button.title = '';
                return;
            }
            cooldown = config.moveCooldownRemaining(player, move);
            button.textContent = (move.icon ? move.icon + ' ' : '') + move.label + (cooldown > 0 ? ' (' + cooldown + ')' : '');
            button.disabled = !active || cooldown > 0;
            button.setAttribute('data-combat-move', move.id);
            button.title = move.description || move.label;
        });
    }

    function renderCombatActionState() {
        var state = config.getState();
        var els = config.getEls();
        var root = config.getRoot();
        var combat = state.combat;
        var combatInProgress = !!combat && state.activeCombatScreen === 'battle';
        var active = !!combat && !combat.over && !state.combatAnimating;
        var player = config.activeCombatUnit('player');
        if (root) { root.classList.toggle('is-combat-active', combatInProgress); }
        if (els.combatStart) { els.combatStart.hidden = combatInProgress; }
        els.combatSetups.forEach(function (setup) {
            setup.classList.toggle('is-combat-running', combatInProgress);
        });
        if (!combatInProgress) { showCombatCommandView('root'); }
        els.combatCommandButtons.forEach(function (button) {
            button.disabled = !active;
        });
        els.combatActions.forEach(function (button) {
            var action = button.getAttribute('data-combat-action') || '';
            button.hidden = state.combatMode === 'daily-boss' && action === 'flee';
            button.disabled = !active
                || (action === 'switch' && config.livingCombatIndexes('player').length <= 1)
                || (action === 'defend' && (!player || player.shields <= 0))
                || (action === 'flee' && state.combatMode === 'daily-boss');
        });
        renderCombatMoveSlots(player, active);
    }

    function renderCombatShields(unit, node) {
        var max;
        var current;
        var i;
        if (!node) { return; }
        node.innerHTML = '';
        max = unit ? Math.max(0, config.clampInt(unit.maxShields, 0)) : 0;
        current = unit ? Math.max(0, config.clampInt(unit.shields, 0)) : 0;
        for (i = 0; i < max; i++) {
            var shield = global.document.createElement('span');
            shield.className = i < current ? 'is-active' : 'is-spent';
            shield.setAttribute('aria-hidden', 'true');
            node.appendChild(shield);
        }
        node.setAttribute('title', config.uiText('combat.shields_title', 'Escudos {current} / {max}', { current: unit ? current : 0, max: unit ? max : 0 }));
    }

    function renderCombatUnit(unit, cardWrap, nameNode, hpNode, hpBar, shieldNode, atkNode, defNode) {
        var currentId;
        var nextId;
        if (nameNode) {
            if (unit) {
                nameNode.textContent = unit.card.card_name;
            } else {
                nameNode.textContent = '-';
            }
        }
        if (hpNode) { hpNode.textContent = unit ? 'PS ' + unit.hp + ' / ' + unit.maxHp : 'PS 0 / 0'; }
        if (hpBar) { hpBar.style.width = unit ? Math.max(0, Math.min(100, (unit.hp / unit.maxHp) * 100)) + '%' : '0%'; }
        renderCombatShields(unit, shieldNode);
        if (atkNode) { atkNode.textContent = unit ? String(unit.atk) : '0'; }
        if (defNode) { defNode.textContent = unit ? String(config.effectiveDef(unit)) : '0'; }
        if (cardWrap) {
            currentId = cardWrap.getAttribute('data-combat-instance') || '';
            nextId = unit ? String(unit.copy && unit.copy.instanceId || '') : '';
            if (!unit) {
                cardWrap.innerHTML = '';
                cardWrap.removeAttribute('data-combat-instance');
            } else if (currentId !== nextId || !cardWrap.firstElementChild) {
                cardWrap.innerHTML = '';
                var cardNode = config.renderCard(unit.card, unit.copy, { noLink: true, combatUnit: true });
                cardNode.classList.add('hg-card--combat-unit');
                cardWrap.appendChild(cardNode);
                cardWrap.setAttribute('data-combat-instance', nextId);
            }
        }
    }

    function renderTrainingRivalProfile() {
        var els = config.getEls();
        if (!els.combatEnemyRival) { return; }
        els.combatEnemyRival.hidden = true;
    }

    function renderCombatBench() {
        var state = config.getState();
        var els = config.getEls();
        if (!els.combatBench || !state.combat) { return; }
        els.combatBench.innerHTML = '';
        if (state.combat.over) {
            els.combatBench.hidden = true;
            return;
        }
        var cancel = global.document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'hg-combat-bench__cancel';
        cancel.innerHTML = '<strong>' + config.escapeHtml(config.uiText('combat.cancel', 'Cancelar')) + '</strong><span>' + config.escapeHtml(config.uiText('combat.back', 'Volver')) + '</span>';
        cancel.addEventListener('click', function () {
            els.combatBench.hidden = true;
        });
        els.combatBench.appendChild(cancel);
        state.combat.player.forEach(function (unit, index) {
            if (index === state.combat.playerActive || unit.defeated || unit.hp <= 0) { return; }
            var button = global.document.createElement('button');
            button.type = 'button';
            button.className = 'hg-combat-bench-card hg-collection-row--' + unit.card.card_rarity;
            button.innerHTML =
                '<strong>' + config.escapeHtml(unit.card.card_name) + '</strong>' +
                '<span class="hg-combat-statline hg-combat-statline--switch">' +
                    config.combatStatPillHtml('PS', unit.hp + ' / ' + unit.maxHp) +
                    config.combatStatPillHtml('ATQ', unit.atk) +
                    config.combatStatPillHtml('DEF', config.effectiveDef(unit)) +
                '</span>';
            button.addEventListener('click', function () {
                els.combatBench.hidden = true;
                config.switchPlayerCard(index, true);
            });
            els.combatBench.appendChild(button);
        });
        if (els.combatBench.children.length <= 1) {
            var empty = global.document.createElement('span');
            empty.textContent = config.uiText('combat.switch_empty', 'No hay cartas disponibles para cambiar.');
            els.combatBench.appendChild(empty);
        }
    }

    function renderCombatEndOverlay() {
        var state = config.getState();
        var screen = config.combatScreenElement();
        var current;
        var victory;
        var overlay;
        var panel;
        var title;
        var text;
        var restart;
        var actions;
        if (!screen) { return; }
        current = screen.querySelector('.hg-combat-end');
        if (current) { current.remove(); }
        if (!state.combat || !state.combat.over || !state.combat.result) { return; }
        victory = state.combat.result === 'victory';
        overlay = global.document.createElement('div');
        overlay.className = 'hg-combat-end hg-combat-end--' + (victory ? 'victory' : 'defeat');
        panel = global.document.createElement('div');
        panel.className = 'hg-combat-end__panel';
        title = global.document.createElement('h3');
        title.textContent = victory ? config.uiText('combat.training_victory_title', '¡Superaste el entrenamiento!') : config.uiText('combat.training_defeat_title', '¡Te han derrotado!');
        text = global.document.createElement('p');
        if (state.combat.mode === 'daily-boss') {
            var dailyReward = state.combat.dailyBossRewardData || null;
            var dailyCasualties = config.clampInt(state.combat.dailyBossCasualties, 0);
            title.textContent = victory ? config.uiText('combat.daily_victory_title', 'Jefe diario derrotado') : config.uiText('combat.daily_defeat_title', 'El Jefe diario vence');
            if (victory) {
                text.textContent = state.combat.reward
                    ? config.dailyBossRewardSummary(dailyReward) || config.uiText('combat.daily_card_reward_text', 'Obtienes la carta Estigmática del Jefe diario.')
                    : config.uiText('combat.daily_card_already_text', 'Ya habías reclamado la carta Estigmática de hoy.');
            } else {
                text.textContent = dailyCasualties > 0
                    ? config.uiText('combat.daily_team_lost_text', 'Cartas derrotadas perdidas: {count}.', { count: dailyCasualties })
                    : config.uiText('combat.daily_team_safe_text', 'No se perdió ninguna carta de tu colección en este intento.');
            }
        } else {
            text.textContent = victory
                ? config.uiText('combat.training_reward_text', 'Recompensa: +{reward} Mnemones.', { reward: config.clampInt(state.combat.reward, 0) })
                : config.uiText('combat.training_no_loss_text', 'No pierdes cartas en entrenamiento.');
        }
        restart = global.document.createElement('button');
        restart.type = 'button';
        restart.className = 'hg-combat-end__restart';
        restart.textContent = state.combat.mode === 'daily-boss' ? config.uiText('combat.retry_daily', 'Reintentar jefe diario') : config.uiText('combat.restart_training', 'Empezar otro combate');
        restart.addEventListener('click', config.startSelectedCombat);
        actions = global.document.createElement('div');
        actions.className = 'hg-combat-end__actions';
        panel.appendChild(title);
        panel.appendChild(text);
        actions.appendChild(restart);
        if (state.combat.mode !== 'daily-boss') {
            var chooseTeam = global.document.createElement('button');
            chooseTeam.type = 'button';
            chooseTeam.className = 'hg-combat-end__restart';
            chooseTeam.textContent = config.uiText('combat.choose_team', 'Elegir equipo');
            chooseTeam.addEventListener('click', function () {
                state.combat = null;
                config.showCombatScreen('battle');
                setCombatMessage(config.uiText('combat.team_ready_prompt', 'Elige uno de tus 5 equipos y empieza un entrenamiento.'));
            });
            actions.appendChild(chooseTeam);
            var manageTeam = global.document.createElement('button');
            manageTeam.type = 'button';
            manageTeam.className = 'hg-combat-end__restart';
            manageTeam.textContent = config.uiText('combat.open_loadout', 'Preparar equipo');
            manageTeam.addEventListener('click', function () {
                config.showCombatScreen('loadout');
            });
            actions.appendChild(manageTeam);
        }
        panel.appendChild(actions);
        overlay.appendChild(panel);
        screen.appendChild(overlay);
    }

    function renderCombatBattle() {
        var state = config.getState();
        var els = config.getEls();
        var combat;
        var screen;
        var introActive;
        var player;
        var enemy;
        if (!config.isCombatContext() || !els.combatPlayerCard) { return; }
        combat = state.combat;
        screen = config.combatScreenElement();
        config.applyCombatScreenBackground(screen);
        introActive = !!(combat && combat.introActive);
        player = introActive ? null : config.activeCombatUnit('player');
        enemy = introActive ? null : config.activeCombatUnit('enemy');
        renderCombatUnit(player, els.combatPlayerCard, els.combatPlayerName, els.combatPlayerHp, els.combatPlayerHpBar, els.combatPlayerShields, els.combatPlayerAtk, els.combatPlayerDef);
        renderCombatUnit(enemy, els.combatEnemyCard, els.combatEnemyName, els.combatEnemyHp, els.combatEnemyHpBar, els.combatEnemyShields, els.combatEnemyAtk, els.combatEnemyDef);
        renderTrainingRivalProfile();
        renderCombatActionState();
        if (els.combatLog) {
            els.combatLog.innerHTML = combat && combat.log.length
                ? combat.log.map(function (line) { return '<p>' + config.escapeHtml(line) + '</p>'; }).join('')
                : '<p>El registro del combate aparecerá aquí.</p>';
        }
        renderCombatBench();
        renderCombatEndOverlay();
    }

    function renderCombat() {
        var state = config.getState();
        if (!config.isCombatContext()) { return; }
        if (config.isCombatLoadoutVisible()) { config.renderCombatSetup(); }
        config.showCombatScreen(state.activeCombatScreen);
        renderCombatBattle();
    }

    var api = Object.freeze({
        configure: configure,
        setCombatMessage: setCombatMessage,
        pushCombatLog: pushCombatLog,
        showCombatCommandView: showCombatCommandView,
        renderCombatMoveSlots: renderCombatMoveSlots,
        renderCombatActionState: renderCombatActionState,
        renderCombatShields: renderCombatShields,
        renderCombatUnit: renderCombatUnit,
        renderTrainingRivalProfile: renderTrainingRivalProfile,
        renderCombatBench: renderCombatBench,
        renderCombatEndOverlay: renderCombatEndOverlay,
        renderCombatBattle: renderCombatBattle,
        renderCombat: renderCombat
    });

    app.combat.render = api;
})(window);
