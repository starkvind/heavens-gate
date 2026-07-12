(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.combat = app.combat || {};

    var config = {
        getState: function () { return null; },
        getEls: function () { return {}; },
        getRoot: function () { return null; },
        clampInt: function (value, fallback) {
            var n = Number(value);
            return Number.isFinite(n) ? Math.round(n) : fallback;
        },
        escapeHtml: function (value) { return String(value || ''); },
        playCombatSound: function () {},
        uiText: function (key, fallback) { return fallback || key; },
        setCombatBusy: function () {},
        combatHitSoundDelayMs: function () { return 120; },
        combatDefendMs: function () { return 620; },
        combatDefeatMs: function () { return 700; },
        combatEntryMs: function () { return 620; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function combatStand(side) {
        var els = config.getEls();
        return side === 'enemy' ? els.combatEnemyCard : els.combatPlayerCard;
    }

    function combatScreenElement() {
        var els = config.getEls();
        return els.combatPlayerCard ? els.combatPlayerCard.closest('.hg-combat-screen') : null;
    }

    function restartCombatAnimation(node, className, duration) {
        if (!node) { return; }
        node.classList.remove(className);
        void node.offsetWidth;
        node.classList.add(className);
        global.setTimeout(function () {
            node.classList.remove(className);
        }, duration || 620);
    }

    function showCombatDamage(side, amount) {
        var stand = combatStand(side);
        var number;
        if (!stand || !amount) { return; }
        number = global.document.createElement('span');
        number.className = 'hg-combat-damage';
        number.textContent = '-' + amount;
        stand.appendChild(number);
        global.setTimeout(function () {
            number.remove();
        }, 900);
    }

    function spawnStandEffect(side, className, count) {
        var stand = combatStand(side);
        var i;
        if (!stand) { return; }
        count = Math.max(1, config.clampInt(count, 1));
        for (i = 0; i < count; i++) {
            var particle = global.document.createElement('span');
            particle.className = 'hg-combat-particle ' + className;
            particle.style.left = (18 + Math.random() * 64) + '%';
            particle.style.top = (14 + Math.random() * 68) + '%';
            particle.style.setProperty('--hg-particle-dx', ((Math.random() * 2) - 1).toFixed(2));
            particle.style.setProperty('--hg-particle-dy', (-0.8 - Math.random()).toFixed(2));
            particle.style.animationDelay = (Math.random() * 120) + 'ms';
            stand.appendChild(particle);
            global.setTimeout(function (node) {
                return function () { node.remove(); };
            }(particle), 900);
        }
    }

    function spawnAuraEffect(side, className, duration) {
        var stand = combatStand(side);
        var aura;
        if (!stand) { return; }
        aura = global.document.createElement('span');
        aura.className = 'hg-combat-aura ' + className;
        stand.appendChild(aura);
        global.setTimeout(function () {
            aura.remove();
        }, duration || 900);
    }

    function shakeCombatScreen(className, duration) {
        var screen = combatScreenElement();
        if (!screen) { return; }
        screen.classList.remove(className);
        void screen.offsetWidth;
        screen.classList.add(className);
        global.setTimeout(function () {
            screen.classList.remove(className);
        }, duration || 520);
    }

    function spawnCombatOrb(fromSide, toSide, className) {
        var screen = combatScreenElement();
        var fromStand = combatStand(fromSide);
        var toStand = combatStand(toSide);
        var screenRect;
        var fromRect;
        var toRect;
        var orb;
        var startX;
        var startY;
        var endX;
        var endY;
        if (!screen || !fromStand || !toStand) { return; }
        screenRect = screen.getBoundingClientRect();
        fromRect = fromStand.getBoundingClientRect();
        toRect = toStand.getBoundingClientRect();
        orb = global.document.createElement('span');
        orb.className = 'hg-combat-orb ' + className;
        startX = (fromRect.left + (fromRect.width / 2)) - screenRect.left;
        startY = (fromRect.top + (fromRect.height / 2)) - screenRect.top;
        endX = (toRect.left + (toRect.width / 2)) - screenRect.left;
        endY = (toRect.top + (toRect.height / 2)) - screenRect.top;
        orb.style.left = startX + 'px';
        orb.style.top = startY + 'px';
        orb.style.setProperty('--hg-orb-x', (endX - startX).toFixed(1) + 'px');
        orb.style.setProperty('--hg-orb-y', (endY - startY).toFixed(1) + 'px');
        screen.appendChild(orb);
        global.setTimeout(function () {
            orb.remove();
        }, 820);
    }

    function playMoveVfx(move, attackerSide, targetSide) {
        if (!move) { return; }
        if (move.id === 'hero_stance') {
            spawnAuraEffect(attackerSide, 'hg-combat-aura--hero', 980);
            spawnStandEffect(attackerSide, 'hg-combat-particle--hero', 10);
            return;
        }
        if (move.id === 'weakening_blow') {
            spawnStandEffect(targetSide, 'hg-combat-particle--blue', 10);
            return;
        }
        if (move.id === 'armor_breaker') {
            spawnStandEffect(targetSide, 'hg-combat-particle--green', 10);
            return;
        }
        if (move.id === 'discouraging_impact') {
            spawnStandEffect(targetSide, 'hg-combat-particle--gold', 10);
            return;
        }
        if (move.id === 'brutal_strike') {
            shakeCombatScreen('is-combat-shaking', 540);
            spawnStandEffect(targetSide, 'hg-combat-particle--red', 14);
            spawnStandEffect(attackerSide, 'hg-combat-particle--red', 8);
            return;
        }
        if (move.id === 'phantom_leda') {
            spawnStandEffect(targetSide, 'hg-combat-particle--blood', 12);
            global.setTimeout(function () {
                spawnCombatOrb(targetSide, attackerSide, 'hg-combat-orb--blood');
            }, 120);
        }
    }

    function animateCombatAttack(attackerSide, targetSide, damage) {
        var impactDelay = attackerSide === 'enemy' ? 120 : 0;
        config.playCombatSound('attack');
        restartCombatAnimation(combatStand(attackerSide), attackerSide === 'enemy' ? 'is-attacking-enemy' : 'is-attacking-player');
        global.setTimeout(function () {
            restartCombatAnimation(combatStand(targetSide), 'is-hit');
            showCombatDamage(targetSide, damage);
        }, impactDelay);
        if (damage > 0) {
            global.setTimeout(function () {
                config.playCombatSound('damage');
            }, config.combatHitSoundDelayMs() + impactDelay);
        }
    }

    function animateCombatDefend(side) {
        config.playCombatSound('defend');
        restartCombatAnimation(combatStand(side), 'is-defending', config.combatDefendMs());
    }

    function animateCombatDefeat(side) {
        config.playCombatSound('defeat');
        restartCombatAnimation(combatStand(side), 'is-defeated', config.combatDefeatMs());
    }

    function animateCombatEntry(side) {
        restartCombatAnimation(combatStand(side), side === 'enemy' ? 'is-entering-enemy' : 'is-entering-player', config.combatEntryMs());
    }

    function removeCombatRivalIntro() {
        var screen = combatScreenElement();
        var current;
        if (!screen) { return; }
        current = screen.querySelector('.hg-combat-rival-intro');
        if (current) { current.remove(); }
    }

    function playCombatRivalIntro(done) {
        var state = config.getState();
        var screen;
        var rival;
        var intro;
        if (!state.combat || state.combat.mode === 'daily-boss') {
            if (state.combat) { state.combat.introActive = false; }
            if (done) { done(); }
            return;
        }
        screen = combatScreenElement();
        rival = state.combat.rivalProfile || state.combat.enemyTrainer;
        if (!screen || !rival) {
            if (state.combat) { state.combat.introActive = false; }
            if (done) { done(); }
            return;
        }
        removeCombatRivalIntro();
        config.setCombatBusy(true);
        intro = global.document.createElement('div');
        intro.className = 'hg-combat-rival-intro';
        intro.innerHTML =
            '<div class="hg-combat-rival-intro__panel">' +
                '<img src="' + config.escapeHtml(rival.spriteUrl || '') + '" alt="' + config.escapeHtml(rival.name || 'Rival') + '" class="hg-combat-rival-intro__sprite">' +
                '<p>&iexcl;' + config.escapeHtml(rival.name || 'Rival') + ' te desafia a un duelo de cartas!</p>' +
            '</div>';
        screen.appendChild(intro);
        global.setTimeout(function () {
            intro.classList.add('is-leaving');
        }, 1150);
        global.setTimeout(function () {
            removeCombatRivalIntro();
            if (state.combat) { state.combat.introActive = false; }
            config.setCombatBusy(false);
            if (done) { done(); }
        }, 1700);
    }

    function animateEnemyAction(action) {
        if (!action) { return; }
        if (action.type === 'switch') {
            config.playCombatSound('switch');
            animateCombatEntry('enemy');
        } else if (action.type === 'defend') {
            animateCombatDefend('enemy');
        } else if (action.type === 'attack' || action.type === 'move_attack') {
            animateCombatAttack('enemy', 'player', action.damage);
            if (action.move) { playMoveVfx(action.move, 'enemy', 'player'); }
        } else if (action.type === 'move_buff') {
            animateCombatDefend('enemy');
            playMoveVfx(action.move, 'enemy', 'player');
        }
    }

    var api = Object.freeze({
        configure: configure,
        combatStand: combatStand,
        combatScreenElement: combatScreenElement,
        restartCombatAnimation: restartCombatAnimation,
        showCombatDamage: showCombatDamage,
        spawnStandEffect: spawnStandEffect,
        spawnAuraEffect: spawnAuraEffect,
        shakeCombatScreen: shakeCombatScreen,
        spawnCombatOrb: spawnCombatOrb,
        playMoveVfx: playMoveVfx,
        animateCombatAttack: animateCombatAttack,
        animateCombatDefend: animateCombatDefend,
        animateCombatDefeat: animateCombatDefeat,
        animateCombatEntry: animateCombatEntry,
        removeCombatRivalIntro: removeCombatRivalIntro,
        playCombatRivalIntro: playCombatRivalIntro,
        animateEnemyAction: animateEnemyAction
    });

    app.combat.animations = api;
})(window);
