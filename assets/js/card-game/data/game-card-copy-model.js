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
        normalizeRarity: function (value, fallback) {
            return String(value || fallback || 'common');
        },
        normalizeSourceType: function (value) {
            return String(value || 'document');
        },
        rarityRank: function (rarity) {
            var order = api.getRarityOrder();
            var index = order.indexOf(String(rarity || 'common'));
            return index === -1 ? 0 : index;
        },
        getMoveLibrary: function () { return {}; },
        getMoveLearnRules: function () { return {}; },
        getRarityOrder: function () { return []; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function validCard(card) {
        if (!card || typeof card !== 'object') { return false; }
        if (!Number.isFinite(Number(card.card_id)) || Number(card.card_id) <= 0) { return false; }
        return api.getRarityOrder().indexOf(String(card.card_rarity || '')) !== -1;
    }

    function normalizeMoveId(value, fallback) {
        var id = String(value || '').trim();
        return id || fallback;
    }

    function cloneMoveDefinition(move) {
        if (!move || typeof move !== 'object') { return null; }
        var accuracy = Number(move.accuracy);
        if (!Number.isFinite(accuracy)) { accuracy = 1; }
        return {
            id: String(move.id || ''),
            label: String(move.label || ''),
            icon: String(move.icon || ''),
            type: String(move.type || 'damage'),
            power: Number(move.power),
            formula: String(move.formula || ''),
            accuracy: Math.max(0, Math.min(1, accuracy)),
            cooldown: Math.max(0, config.clampInt(move.cooldown, 0)),
            target: String(move.target || 'enemy'),
            effect: move.effect && typeof move.effect === 'object' ? {
                kind: String(move.effect.kind || ''),
                amount: Number(move.effect.amount),
                chance: Number(move.effect.chance),
                ratio: Number(move.effect.ratio),
                minRatio: Number(move.effect.minRatio),
                maxRatio: Number(move.effect.maxRatio)
            } : null,
            description: String(move.description || '')
        };
    }

    function normalizeCardMoves(card) {
        var library = api.getMoveLibrary();
        var source = Array.isArray(card && card.moves) ? card.moves : [];
        var moves = [];
        source.forEach(function (entry, index) {
            var move = null;
            if (typeof entry === 'string') {
                move = cloneMoveDefinition(library[entry]);
            } else if (entry && typeof entry === 'object') {
                var moveId = normalizeMoveId(entry.id || entry.move_key, 'move_' + (index + 1));
                if (library[moveId]) {
                    move = cloneMoveDefinition(library[moveId]);
                    Object.keys(entry).forEach(function (key) {
                        if (key === 'effect' && entry.effect && typeof entry.effect === 'object') {
                            move.effect = move.effect || {};
                            Object.keys(entry.effect).forEach(function (effectKey) {
                                move.effect[effectKey] = entry.effect[effectKey];
                            });
                        } else {
                            move[key] = entry[key];
                        }
                    });
                } else {
                    move = cloneMoveDefinition(entry);
                }
                if (move) { move.id = moveId; }
            }
            if (!move || !move.id || !move.label) { return; }
            if (moves.some(function (existing) { return existing.id === move.id; })) { return; }
            moves.push(cloneMoveDefinition(move));
        });
        return moves.slice(0, 3);
    }

    function normalizeCopyMoveIds(value) {
        var library = api.getMoveLibrary();
        if (!Array.isArray(value)) { return []; }
        var libraryReady = Object.keys(library).length > 0;
        var seen = {};
        return value.map(function (entry) {
            return normalizeMoveId(entry, '');
        }).filter(function (id) {
            if (!id || seen[id]) { return false; }
            if (libraryReady && !library[id]) { return false; }
            seen[id] = true;
            return true;
        }).slice(0, 3);
    }

    function initialMoveIdsForCopy(card, rarity) {
        var rulesTable = api.getMoveLearnRules();
        var library = api.getMoveLibrary();
        var rules = rulesTable[String(rarity || '')] || rulesTable.common;
        var libraryIds = Object.keys(library);
        if (!libraryIds.length || !rules || rules.count <= 0 || Math.random() > Math.max(0, Math.min(1, Number(rules.chance) || 0))) {
            return [];
        }
        var start = Math.abs(config.clampInt(card && card.card_id, 1) - 1) % libraryIds.length;
        var pool = libraryIds.map(function (_, index) {
            return libraryIds[(start + index) % libraryIds.length];
        });
        for (var i = pool.length - 1; i > 0; i--) {
            var swapIndex = Math.floor(Math.random() * (i + 1));
            var temp = pool[i];
            pool[i] = pool[swapIndex];
            pool[swapIndex] = temp;
        }
        return pool.slice(0, Math.max(0, config.clampInt(rules.count, 0)));
    }

    function highestMoveCheckpoint(value) {
        var rarity = config.normalizeRarity(value, 'common');
        return config.rarityRank(rarity) >= 0 ? rarity : 'common';
    }

    function addMoveIdsToCopy(copy, moveIds) {
        if (!copy) { return 0; }
        var current = normalizeCopyMoveIds(copy.moves);
        var added = 0;
        normalizeCopyMoveIds(moveIds).forEach(function (moveId) {
            if (current.indexOf(moveId) !== -1 || current.length >= 3) { return; }
            current.push(moveId);
            added++;
        });
        copy.moves = current.slice(0, 3);
        return added;
    }

    function ensureCopyMovesForRarity(copy, card, targetRarity, force) {
        if (!copy || !card) { return 0; }
        var rarity = config.normalizeRarity(targetRarity || copy.rarity, card.card_rarity);
        var checkpoint = highestMoveCheckpoint(copy.moveRollRarity || copy.movesRarityCheckpoint || 'common');
        if (!force && config.rarityRank(rarity) <= config.rarityRank(checkpoint)) {
            copy.moves = normalizeCopyMoveIds(copy.moves);
            copy.moveRollRarity = checkpoint;
            return 0;
        }
        var added = addMoveIdsToCopy(copy, initialMoveIdsForCopy(card, rarity));
        copy.moveRollRarity = rarity;
        return added;
    }

    function normalizeCard(card) {
        var hpMin = config.clampInt(card.hp_min, card.atk_min || 10);
        var hpMax = config.clampInt(card.hp_max, card.atk_max || 40);
        var atkMin = config.clampInt(card.atk_min, 10);
        var atkMax = config.clampInt(card.atk_max, 40);
        var defMin = config.clampInt(card.def_min, atkMin);
        var defMax = config.clampInt(card.def_max, atkMax);
        if (hpMax < hpMin) { hpMax = hpMin; }
        if (atkMax < atkMin) { atkMax = atkMin; }
        if (defMax < defMin) { defMax = defMin; }
        return {
            card_id: config.clampInt(card.card_id, 0),
            source_type: config.normalizeSourceType(card.source_type),
            source_id: config.clampInt(card.source_id, 0),
            card_name: String(card.card_name || 'Carta sin nombre'),
            card_text: String(card.card_text || ''),
            card_image_url: String(card.card_image_url || '/img/og/og_image.webp'),
            card_url: String(card.card_url || ''),
            card_rarity: String(card.card_rarity || 'common'),
            hp_min: hpMin,
            hp_max: hpMax,
            atk_min: atkMin,
            atk_max: atkMax,
            def_min: defMin,
            def_max: defMax,
            moves: normalizeCardMoves(card)
        };
    }

    var api = Object.freeze({
        configure: configure,
        getMoveLibrary: function () {
            return typeof config.getMoveLibrary === 'function' ? (config.getMoveLibrary() || {}) : {};
        },
        getMoveLearnRules: function () {
            return typeof config.getMoveLearnRules === 'function' ? (config.getMoveLearnRules() || {}) : {};
        },
        getRarityOrder: function () {
            return typeof config.getRarityOrder === 'function' ? (config.getRarityOrder() || []) : [];
        },
        validCard: validCard,
        normalizeMoveId: normalizeMoveId,
        cloneMoveDefinition: cloneMoveDefinition,
        normalizeCardMoves: normalizeCardMoves,
        normalizeCopyMoveIds: normalizeCopyMoveIds,
        initialMoveIdsForCopy: initialMoveIdsForCopy,
        highestMoveCheckpoint: highestMoveCheckpoint,
        addMoveIdsToCopy: addMoveIdsToCopy,
        ensureCopyMovesForRarity: ensureCopyMovesForRarity,
        normalizeCard: normalizeCard
    });

    app.data.copyModel = api;
})(window);
