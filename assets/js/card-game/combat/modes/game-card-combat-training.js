(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.combat = app.combat || {};

    var config = {
        getState: function () { return null; },
        isCombatContext: function () { return false; },
        preloadCombatSounds: function () {},
        cleanCombatTeamsAgainstCollection: function () {},
        validDraftTeam: function () { return []; },
        promptQuickCombatTeam: function () { return false; },
        combatEntryFromCopy: function () { return null; },
        copyByInstanceId: function () { return null; },
        createCombatUnit: function () { return null; },
        setCombatMessage: function () {},
        uiText: function (key, fallback, values) {
            var text = fallback || key;
            return values ? text.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
                return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
            }) : text;
        },
        combatDifficultyConfig: function () { return { key: 'apprentice', label: 'Aprendiz', weights: {} }; },
        combatRewardMultiplier: function () { return 1; },
        enemyDifficultyUsesMoves: function () { return false; },
        normalizeRarity: function (value, fallback) { return String(value || fallback || 'common'); },
        normalizeCopyMoveIds: function (value) { return Array.isArray(value) ? value : []; },
        initialMoveIdsForCopy: function () { return []; },
        moveLibrary: function () { return {}; },
        cardForCopy: function (_, copy) { return copy; },
        createCardCopy: function (_, copy) { return copy; },
        nowIso: function () { return ''; },
        clampInt: function (value, fallback) {
            var n = Number(value);
            return Number.isFinite(n) ? Math.round(n) : fallback;
        },
        combatPlayerName: function () { return 'Jugador'; },
        combatEnemyTrainerName: function () { return 'El rival'; },
        pushCombatLog: function () {},
        showCombatScreen: function () {},
        renderCombatBattle: function () {},
        playCombatRivalIntro: function (done) { if (done) { done(); } },
        animateCombatEntry: function () {},
        trainingRivalNames: function () { return {}; },
        trainingRivalTitles: function () { return {}; },
        rarityWeights: function () { return {}; },
        naturalRarityOrder: function () { return []; },
        loadCollection: function () {},
        addMnemones: function () {},
        saveCollection: function () {},
        renderSummary: function () {},
        rollStat: function (min) { return min; },
        trainingRewardTable: function () { return {}; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function pickWeightedEnemyRarity(difficultyConfig) {
        var weights = difficultyConfig.weights || config.rarityWeights();
        var order = config.naturalRarityOrder();
        var total = order.reduce(function (sum, rarity) {
            return sum + Math.max(0, weights[rarity] || 0);
        }, 0);
        var roll = Math.random() * Math.max(1, total);
        var acc = 0;
        var i;
        for (i = 0; i < order.length; i++) {
            var rarity = order[i];
            acc += Math.max(0, weights[rarity] || 0);
            if (roll <= acc) { return rarity; }
        }
        return 'common';
    }

    function pickEnemyCatalogCard(excluded) {
        var state = config.getState();
        var pool = (state.catalog || []).filter(function (card) {
            return card.card_rarity !== 'stigmatic' && !excluded[String(card.card_id)];
        });
        return pool.length ? pool[Math.floor(Math.random() * pool.length)] : null;
    }

    function ensureEnemyCopyMoves(copy, card, rarity, difficultyConfig) {
        var moveIds;
        var libraryIds;
        var start;
        if (!copy || !card || !config.enemyDifficultyUsesMoves(difficultyConfig)) { return; }
        copy.moveRollRarity = config.normalizeRarity(rarity || copy.rarity, copy.rarity || 'common');
        copy.moves = config.normalizeCopyMoveIds(copy.moves);
        if (copy.moves.length) { return; }
        moveIds = config.initialMoveIdsForCopy(card, copy.moveRollRarity);
        if (!moveIds.length) {
            libraryIds = Object.keys(config.moveLibrary() || {});
            if (!libraryIds.length) { return; }
            start = Math.abs(config.clampInt(card && card.card_id, 1) - 1) % libraryIds.length;
            moveIds = [libraryIds[start]];
        }
        copy.moves = config.normalizeCopyMoveIds(moveIds).slice(0, 3);
    }

    function rivalPalette(seed) {
        var palettes = [
            { skin: '#f2d2b6', hair: '#3b241a', cloth: '#305b8f', aura: '#9fd2ff' },
            { skin: '#d7b08a', hair: '#101826', cloth: '#7c2f2f', aura: '#ffbf8a' },
            { skin: '#f1c7d8', hair: '#5b2a68', cloth: '#264653', aura: '#d9b8ff' },
            { skin: '#c48a67', hair: '#ddd7c5', cloth: '#4f772d', aura: '#c7f0a6' },
            { skin: '#e8d8b0', hair: '#7b3f00', cloth: '#5a189a', aura: '#ffd166' }
        ];
        return palettes[Math.abs(config.clampInt(seed, 0)) % palettes.length];
    }

    function buildTrainingRivalSprite(seed) {
        var palette = rivalPalette(seed);
        var svg = '' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 84 84">' +
                '<defs>' +
                    '<linearGradient id="bg" x1="0" x2="1" y1="0" y2="1">' +
                        '<stop offset="0%" stop-color="#08111f"/>' +
                        '<stop offset="100%" stop-color="' + palette.cloth + '"/>' +
                    '</linearGradient>' +
                '</defs>' +
                '<rect width="84" height="84" rx="16" fill="url(#bg)"/>' +
                '<circle cx="42" cy="28" r="17" fill="' + palette.hair + '" opacity="0.28"/>' +
                '<circle cx="42" cy="29" r="13" fill="' + palette.skin + '"/>' +
                '<path d="M22 78c4-16 17-23 20-23s16 7 20 23" fill="' + palette.cloth + '"/>' +
                '<path d="M28 23c3-10 11-14 14-14s11 4 14 14c-3-2-9-5-14-5s-11 3-14 5z" fill="' + palette.hair + '"/>' +
                '<circle cx="37" cy="30" r="1.7" fill="#111"/>' +
                '<circle cx="47" cy="30" r="1.7" fill="#111"/>' +
                '<path d="M37 37c2 2 8 2 10 0" stroke="#7a3d3d" stroke-width="2" stroke-linecap="round" fill="none"/>' +
                '<circle cx="66" cy="18" r="8" fill="' + palette.aura + '" opacity="0.35"/>' +
            '</svg>';
        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
    }

    function createTrainingRivalProfile(difficultyConfig, enemyUnits) {
        var key = String(difficultyConfig && difficultyConfig.key || 'apprentice');
        var namesByDifficulty = config.trainingRivalNames() || {};
        var titlesByDifficulty = config.trainingRivalTitles() || {};
        var names = namesByDifficulty[key] || namesByDifficulty.apprentice || [];
        var seed = Date.now() + (enemyUnits && enemyUnits[0] && enemyUnits[0].card ? enemyUnits[0].card.card_id : 0);
        return {
            name: names[Math.abs(config.clampInt(seed, 0)) % Math.max(1, names.length)] || 'Rival',
            title: titlesByDifficulty[key] || 'Rival de entrenamiento',
            spriteUrl: buildTrainingRivalSprite(seed)
        };
    }

    function createEnemyCard(difficultyConfig, index, excluded) {
        var rarity = pickWeightedEnemyRarity(difficultyConfig);
        var card = pickEnemyCatalogCard(excluded || {});
        var copy;
        if (!card) { return null; }
        excluded[String(card.card_id)] = true;
        copy = config.createCardCopy(card, {
            instanceId: 'enemy-' + Date.now() + '-' + index,
            rarity: rarity,
            obtainedAt: config.nowIso()
        });
        ensureEnemyCopyMoves(copy, card, rarity, difficultyConfig);
        return {
            card: config.cardForCopy(card, copy),
            copy: copy
        };
    }

    function awardTrainingVictory() {
        var state = config.getState();
        var rewardTable = config.trainingRewardTable() || {};
        var multiplier = state.combat ? Math.max(1, Number(state.combat.rewardMultiplier) || 1) : 1;
        var base = Math.max(1, config.clampInt(rewardTable.base, 5));
        var rollMin = Math.max(1, config.clampInt(rewardTable.rollMin, 1));
        var rollMax = Math.max(rollMin, config.clampInt(rewardTable.rollMax, 5));
        var reward = config.clampInt(base * config.rollStat(rollMin, rollMax) * multiplier, base);
        if (!state.collection) { config.loadCollection(); }
        config.addMnemones(reward);
        config.saveCollection();
        config.renderSummary();
        return reward;
    }

    function startTrainingCombat() {
        var state = config.getState();
        var teamIds;
        var playerUnits;
        var difficultyConfig;
        var excludedEnemies;
        var enemyUnits;
        var rivalProfile;
        if (!config.isCombatContext()) { return false; }
        config.preloadCombatSounds();
        config.cleanCombatTeamsAgainstCollection(true);
        teamIds = config.validDraftTeam();
        if (teamIds.length !== 5) {
            return config.promptQuickCombatTeam();
        }
        playerUnits = teamIds.map(function (id, index) {
            var entry = config.combatEntryFromCopy(config.copyByInstanceId(id));
            return entry ? config.createCombatUnit(entry.card, entry.copy, 'player', index) : null;
        }).filter(Boolean);
        if (playerUnits.length !== 5) {
            config.setCombatMessage(config.uiText('combat.team_missing_card', 'Alguna carta del equipo ya no existe en la colección.'));
            return false;
        }
        difficultyConfig = config.combatDifficultyConfig();
        excludedEnemies = {};
        enemyUnits = [0, 1, 2, 3, 4].map(function (index) {
            var enemy = createEnemyCard(difficultyConfig, index, excludedEnemies);
            return enemy ? config.createCombatUnit(enemy.card, enemy.copy, 'enemy', index) : null;
        }).filter(Boolean);
        if (enemyUnits.length !== 5) {
            config.setCombatMessage(config.uiText('combat.no_catalog_for_enemy', 'No hay suficientes cartas en el catálogo para generar rival.'));
            return false;
        }
        rivalProfile = createTrainingRivalProfile(difficultyConfig, enemyUnits);
        state.combat = {
            mode: 'training',
            difficultyKey: difficultyConfig.key,
            difficultyLabel: difficultyConfig.label,
            rewardMultiplier: config.combatRewardMultiplier(),
            rivalProfile: rivalProfile,
            enemyTrainer: rivalProfile,
            background: {
                css: '',
                theme: ''
            },
            ai: {
                turnNumber: 0,
                enemySwitchCooldownUntilTurn: -1
            },
            player: playerUnits,
            enemy: enemyUnits,
            playerActive: 0,
            enemyActive: 0,
            introActive: true,
            over: false,
            result: '',
            reward: 0,
            log: []
        };
        config.pushCombatLog(config.uiText('combat.training_log', 'Entrenamiento contra {label}.', { label: difficultyConfig.label }));
        config.showCombatScreen('battle');
        config.renderCombatBattle();
        config.playCombatRivalIntro(function () {
            if (!state.combat || state.combat.over) { return; }
            config.pushCombatLog(config.combatPlayerName() + ' saca una carta.');
            config.pushCombatLog(config.combatEnemyTrainerName() + ' saca una carta.');
            config.setCombatMessage(config.uiText('combat.started', 'ĄCombate iniciado!'));
            config.renderCombatBattle();
            config.animateCombatEntry('player');
            config.animateCombatEntry('enemy');
        });
        return true;
    }

    var api = Object.freeze({
        configure: configure,
        pickWeightedEnemyRarity: pickWeightedEnemyRarity,
        pickEnemyCatalogCard: pickEnemyCatalogCard,
        ensureEnemyCopyMoves: ensureEnemyCopyMoves,
        rivalPalette: rivalPalette,
        buildTrainingRivalSprite: buildTrainingRivalSprite,
        createTrainingRivalProfile: createTrainingRivalProfile,
        createEnemyCard: createEnemyCard,
        awardTrainingVictory: awardTrainingVictory,
        startTrainingCombat: startTrainingCombat
    });

    if (app.combat.modes && typeof app.combat.modes.register === 'function') {
        app.combat.modes.register('training', api, {
            featureFlag: 'trainingCombat',
            startMethod: 'startTrainingCombat',
            selectedMessage: 'Entrenamiento seleccionado.'
        });
    }
})(window);
