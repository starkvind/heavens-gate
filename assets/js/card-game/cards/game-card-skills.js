(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.cards = app.cards || {};

    var config = {
        getState: function () { return null; },
        loadCollection: function () {},
        saveCollection: function () {},
        normalizeCopyMoveIds: function (value) { return Array.isArray(value) ? value : []; },
        cloneMoveDefinition: function (value) { return value; },
        moveLibrary: function () { return {}; },
        copyRarity: function () { return 'common'; },
        currentMnemones: function () { return 0; },
        materialStock: function () { return 0; },
        normalizeCurrency: function (value) { return value; },
        normalizeMaterialInventory: function (value) { return value; },
        clampInt: function (value, fallback) {
            var n = Number(value);
            return Number.isFinite(n) ? Math.round(n) : fallback;
        },
        normalizeRarity: function (value, fallback) { return String(value || fallback || 'common'); },
        setStatus: function () {},
        uiText: function (key, fallback, values) {
            var text = fallback || key;
            return values ? text.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
                return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
            }) : text;
        },
        formatNumber: function (value) { return String(value); },
        playSkillSound: function () {},
        refreshCollectionViews: function () {},
        showCardModal: function () {},
        ownedCopiesForCard: function () { return []; },
        skillCostMultiplierByRarity: function () { return {}; },
        skillBaseMnemones: function () { return 1; },
        skillMaterialKey: function () { return 'glyph'; },
        upgradeMaterials: function () { return {}; },
        skillSlotCount: function () { return 3; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function copyMoveDefinitions(copy) {
        var library = config.moveLibrary() || {};
        return config.normalizeCopyMoveIds(copy && copy.moves).map(function (moveId) {
            return config.cloneMoveDefinition(library[moveId]);
        }).filter(Boolean).slice(0, config.skillSlotCount());
    }

    function copyHasLearnedMoves(copy) {
        return config.normalizeCopyMoveIds(copy && copy.moves).length > 0;
    }

    function skillCostMultiplier(card, copy) {
        var rarity = config.copyRarity(copy, card);
        var map = config.skillCostMultiplierByRarity() || {};
        return map[rarity] || 1;
    }

    function skillMnemoneCost(card, copy) {
        return config.skillBaseMnemones() * skillCostMultiplier(card, copy);
    }

    function skillMaterialCost(card, copy) {
        return skillCostMultiplier(card, copy);
    }

    function skillMaterialLabel() {
        var key = config.skillMaterialKey();
        var material = (config.upgradeMaterials() || {})[key];
        return (material && material.label) || key || 'material';
    }

    function skillSlotState(copy, slotIndex) {
        var moveIds = config.normalizeCopyMoveIds(copy && copy.moves);
        var moveId = moveIds[slotIndex] || '';
        var library = config.moveLibrary() || {};
        return moveId ? config.cloneMoveDefinition(library[moveId]) : null;
    }

    function availableSkillMoveIds(copy, slotIndex) {
        var library = config.moveLibrary() || {};
        var current = config.normalizeCopyMoveIds(copy && copy.moves);
        var used = {};
        current.forEach(function (moveId, index) {
            if (index !== slotIndex && moveId) {
                used[moveId] = true;
            }
        });
        return Object.keys(library).filter(function (moveId) {
            return !used[moveId] && moveId !== (current[slotIndex] || '');
        });
    }

    function canAffordSkillRoll(card, copy) {
        var state = config.getState();
        if (state && state.isAdmin) { return true; }
        return config.currentMnemones() >= skillMnemoneCost(card, copy)
            && config.materialStock(config.skillMaterialKey()) >= skillMaterialCost(card, copy);
    }

    function skillShortageMessage(card, copy) {
        var missing = [];
        var needMnemones = skillMnemoneCost(card, copy);
        var needGlyphs = skillMaterialCost(card, copy);
        var haveMnemones = config.currentMnemones();
        var haveGlyphs = config.materialStock(config.skillMaterialKey());
        if (haveMnemones < needMnemones) {
            missing.push('Mnemones: ' + config.formatNumber(needMnemones) + ' / ' + config.formatNumber(haveMnemones));
        }
        if (haveGlyphs < needGlyphs) {
            missing.push(skillMaterialLabel() + ': ' + needGlyphs + ' / ' + haveGlyphs);
        }
        return missing.length ? ('Recursos insuficientes. Falta: ' + missing.join(' · ') + '.') : 'Recursos insuficientes.';
    }

    function spendSkillRollCost(card, copy) {
        var state = config.getState();
        var mnemoneCost;
        var materialCost;
        if (state && state.isAdmin) { return true; }
        if (!canAffordSkillRoll(card, copy)) { return false; }
        mnemoneCost = skillMnemoneCost(card, copy);
        materialCost = skillMaterialCost(card, copy);
        if (!state.collection) { config.loadCollection(); }
        state.collection.currency = config.normalizeCurrency(state.collection.currency);
        state.collection.materialInventory = config.normalizeMaterialInventory(state.collection.materialInventory);
        if (state.collection.currency.mnemones < mnemoneCost) { return false; }
        if (config.clampInt(state.collection.materialInventory[config.skillMaterialKey()], 0) < materialCost) { return false; }
        state.collection.currency.mnemones = Math.max(0, state.collection.currency.mnemones - mnemoneCost);
        state.collection.materialInventory[config.skillMaterialKey()] = Math.max(0, config.clampInt(state.collection.materialInventory[config.skillMaterialKey()], 0) - materialCost);
        return true;
    }

    function resetCopySkills(copy) {
        if (!copy) { return; }
        copy.moves = [];
        copy.moveRollRarity = config.normalizeRarity(copy.rarity, 'common');
    }

    function applySkillRoll(card, copy, slotIndex) {
        var selectedSlot = config.clampInt(slotIndex, 0);
        var currentMove;
        var available;
        var rolledId;
        var moveIds;
        var library;
        if (!card || !copy) { return false; }
        if (selectedSlot < 0 || selectedSlot >= config.skillSlotCount()) { return false; }
        currentMove = skillSlotState(copy, selectedSlot);
        available = availableSkillMoveIds(copy, selectedSlot);
        if (!available.length) {
            config.setStatus(config.uiText('skill.no_new_moves', 'No quedan habilidades nuevas para este hueco.'));
            return false;
        }
        if (!spendSkillRollCost(card, copy)) {
            config.setStatus(skillShortageMessage(card, copy));
            return false;
        }
        rolledId = available[Math.floor(Math.random() * available.length)];
        moveIds = config.normalizeCopyMoveIds(copy && copy.moves);
        moveIds[selectedSlot] = rolledId;
        copy.moves = moveIds.filter(Boolean).slice(0, config.skillSlotCount());
        copy.moveRollRarity = config.normalizeRarity(copy.rarity, 'common');
        config.saveCollection();
        config.playSkillSound();
        config.refreshCollectionViews();
        config.showCardModal(card, config.ownedCopiesForCard(card.card_id));
        library = config.moveLibrary() || {};
        config.setStatus(currentMove
            ? config.uiText('skill.changed', 'Habilidad cambiada: {move}.', { move: (library[rolledId] && library[rolledId].label) || rolledId })
            : config.uiText('skill.learned', 'Habilidad aprendida: {move}.', { move: (library[rolledId] && library[rolledId].label) || rolledId }));
        return true;
    }

    var api = Object.freeze({
        configure: configure,
        copyMoveDefinitions: copyMoveDefinitions,
        copyHasLearnedMoves: copyHasLearnedMoves,
        skillCostMultiplier: skillCostMultiplier,
        skillMnemoneCost: skillMnemoneCost,
        skillMaterialCost: skillMaterialCost,
        skillMaterialLabel: skillMaterialLabel,
        skillSlotState: skillSlotState,
        availableSkillMoveIds: availableSkillMoveIds,
        canAffordSkillRoll: canAffordSkillRoll,
        skillShortageMessage: skillShortageMessage,
        spendSkillRollCost: spendSkillRollCost,
        resetCopySkills: resetCopySkills,
        applySkillRoll: applySkillRoll
    });

    app.cards.skills = api;
})(window);
