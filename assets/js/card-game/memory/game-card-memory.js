(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.memory = app.memory || {};

    var config = {
        getState: function () { return null; },
        normalizeTimestamp: function (value, fallback) { return fallback; },
        normalizeWorkAssignments: function (value) { return value || {}; },
        normalizeWorkPendingRewards: function (value) { return value || 0; },
        loadCollection: function () {},
        saveCollection: function () {},
        uniqueCollectionCount: function () { return 0; },
        copyRarity: function () { return 'common'; },
        qualityScore: function () { return 0; },
        totalStats: function () { return 0; },
        copyByInstanceId: function () { return null; },
        cardForCopy: function (_, copy) { return copy; },
        isCopyInCombatTeam: function () { return false; },
        ownedCopiesForCard: function () { return []; },
        addMnemones: function () { return 0; },
        renderSummary: function () {},
        renderPackInventory: function () {},
        renderCollectionTable: function () {},
        renderCombatSetup: function () {},
        renderWorkBench: function () {},
        showCardModal: function () {},
        setStatus: function () {},
        uiText: function (key, fallback, values) {
            var text = fallback || key;
            return values ? text.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
                return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
            }) : text;
        },
        playMoneySound: function () {},
        clampInt: function (value, fallback) {
            var n = Number(value);
            if (!Number.isFinite(n)) { return fallback; }
            return Math.round(n);
        },
        currentTime: function () { return Date.now(); },
        maxMnemones: function () { return 0; },
        startingMnemones: function () { return 0; },
        workMnemonesPerUnique: function () { return 10; },
        workRarityBase: function () { return {}; },
        workMaxAssignments: function () { return 0; },
        workMinDurationMs: function () { return 0; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function normalizeWorkAssignments(assignments) {
        var out = {};
        if (!assignments || typeof assignments !== 'object') { return out; }
        Object.keys(assignments).forEach(function (key) {
            var item = assignments[key];
            var id = String((item && item.instanceId) || key || '').slice(0, 80);
            if (!id) { return; }
            var startedAt = config.normalizeTimestamp(item && item.startedAt, config.currentTime());
            var lastClaimAt = config.normalizeTimestamp(item && item.lastClaimAt, startedAt);
            out[id] = {
                instanceId: id,
                startedAt: startedAt,
                lastClaimAt: Math.max(startedAt, lastClaimAt)
            };
        });
        return out;
    }

    function normalizeWorkPendingRewards(value) {
        var pending = Math.max(0, config.clampInt(value, 0));
        var max = config.maxMnemones();
        return max > 0 ? Math.min(max, pending) : pending;
    }

    function ensureWorkAssignments() {
        var state = config.getState();
        if (!state.collection) { config.loadCollection(); }
        state.collection.workAssignments = normalizeWorkAssignments(state.collection.workAssignments);
        state.collection.workPendingRewards = normalizeWorkPendingRewards(state.collection.workPendingRewards);
        limitWorkAssignments(false);
        return state.collection.workAssignments;
    }

    function isCopyWorking(instanceId) {
        var id = String(instanceId || '');
        if (!id) { return false; }
        return !!ensureWorkAssignments()[id];
    }

    function currentWorkRewardCap() {
        return Math.max(config.startingMnemones(), Math.min(config.maxMnemones(), config.uniqueCollectionCount() * config.workMnemonesPerUnique()));
    }

    function cleanWorkAssignments(persist) {
        var state = config.getState();
        if (!state.collection || !Array.isArray(state.collection.ownedCards)) { return false; }
        state.collection.workAssignments = normalizeWorkAssignments(state.collection.workAssignments);
        if (!state.collection.ownedCards.length && Object.keys(state.collection.workAssignments).length) { return false; }
        var owned = {};
        state.collection.ownedCards.forEach(function (copy) {
            if (copy && copy.instanceId) { owned[String(copy.instanceId)] = true; }
        });
        var changed = false;
        Object.keys(state.collection.workAssignments).forEach(function (id) {
            if (!owned[id]) {
                delete state.collection.workAssignments[id];
                changed = true;
            }
        });
        if (changed && persist) { config.saveCollection(); }
        return changed;
    }

    function limitWorkAssignments(persist) {
        var state = config.getState();
        if (!state.collection) { config.loadCollection(); }
        if (config.workMaxAssignments() <= 0) { return false; }
        var assignments = normalizeWorkAssignments(state.collection.workAssignments);
        var ids = Object.keys(assignments).sort(function (a, b) {
            return config.normalizeTimestamp(assignments[a].startedAt, 0) - config.normalizeTimestamp(assignments[b].startedAt, 0);
        });
        var changed = false;
        ids.slice(config.workMaxAssignments()).forEach(function (id) {
            delete assignments[id];
            changed = true;
        });
        state.collection.workAssignments = assignments;
        if (changed && persist) { config.saveCollection(); }
        return changed;
    }

    function workRatePerMinute(copy, card) {
        if (!copy || !card) { return 0; }
        var rarity = config.copyRarity(copy, card);
        var baseMap = config.workRarityBase();
        var base = baseMap[rarity] || baseMap.common;
        var qualityFactor = 0.6 + (config.qualityScore(copy, card) / 100) * 0.8;
        var statBonus = Math.min(4, config.totalStats(copy) / 150);
        return Math.max(0.5, Math.round((base * qualityFactor + statBonus) * 10) / 10);
    }

    function workEntryFromAssignment(assignment) {
        var state = config.getState();
        var copy = config.copyByInstanceId(assignment && assignment.instanceId);
        var card = copy ? state.catalogById[String(copy.cardId || '')] : null;
        if (!copy || !card) { return null; }
        var rate = workRatePerMinute(copy, card);
        var now = config.currentTime();
        var elapsed = Math.max(0, now - config.normalizeTimestamp(assignment.lastClaimAt, now));
        var startedAt = config.normalizeTimestamp(assignment.startedAt, now);
        return {
            assignment: assignment,
            copy: copy,
            baseCard: card,
            card: config.cardForCopy(card, copy),
            rarity: config.copyRarity(copy, card),
            rate: rate,
            claimable: Math.floor((elapsed / 60000) * rate),
            startedAt: startedAt,
            removableAt: startedAt + config.workMinDurationMs()
        };
    }

    function enforceWorkRewardCap(persist) {
        var state = config.getState();
        if (!state.collection) { config.loadCollection(); }
        var changed = false;
        var assignments = ensureWorkAssignments();
        var pending = normalizeWorkPendingRewards(state.collection.workPendingRewards);
        var capacity = currentWorkRewardCap();
        if (pending > capacity) {
            pending = capacity;
            changed = true;
        }
        state.collection.workPendingRewards = pending;
        capacity -= pending;
        var now = config.currentTime();
        Object.keys(assignments).sort(function (a, b) {
            return config.normalizeTimestamp(assignments[a].lastClaimAt, 0) - config.normalizeTimestamp(assignments[b].lastClaimAt, 0);
        }).forEach(function (id) {
            var assignment = assignments[id];
            var entry = workEntryFromAssignment(assignment);
            if (!entry || entry.rate <= 0 || entry.claimable <= 0) { return; }
            var allowed = Math.min(entry.claimable, capacity);
            if (allowed >= entry.claimable) {
                capacity -= allowed;
                return;
            }
            assignment.lastClaimAt = allowed > 0
                ? Math.max(0, now - Math.floor((allowed / entry.rate) * 60000))
                : now;
            capacity = Math.max(0, capacity - allowed);
            changed = true;
        });
        if (changed && persist) { config.saveCollection(); }
        return changed;
    }

    function activeWorkEntries() {
        cleanWorkAssignments(false);
        enforceWorkRewardCap(false);
        var assignments = ensureWorkAssignments();
        return Object.keys(assignments).map(function (id) {
            return workEntryFromAssignment(assignments[id]);
        }).filter(Boolean).sort(function (a, b) {
            return b.rate - a.rate || b.claimable - a.claimable || String(a.card.card_name).localeCompare(String(b.card.card_name));
        });
    }

    function workCandidateEntries() {
        var state = config.getState();
        if (!state.collection) { config.loadCollection(); }
        return (state.collection.ownedCards || []).map(function (copy) {
            var card = state.catalogById[String(copy.cardId || '')];
            if (!card || isCopyWorking(copy.instanceId) || config.isCopyInCombatTeam(copy.instanceId)) { return null; }
            return {
                copy: copy,
                baseCard: card,
                card: config.cardForCopy(card, copy),
                rarity: config.copyRarity(copy, card),
                rate: workRatePerMinute(copy, card),
                score: config.totalStats(copy)
            };
        }).filter(Boolean).sort(function (a, b) {
            return b.rate - a.rate || b.score - a.score || String(a.card.card_name).localeCompare(String(b.card.card_name));
        });
    }

    function totalWorkClaimable(entries) {
        var state = config.getState();
        var pending = state.collection ? normalizeWorkPendingRewards(state.collection.workPendingRewards) : 0;
        return pending + (entries || activeWorkEntries()).reduce(function (sum, entry) {
            return sum + entry.claimable;
        }, 0);
    }

    function workCanStop(entry) {
        return !!entry && config.currentTime() >= entry.removableAt;
    }

    function workRemainingLabel(entry) {
        var remaining = Math.max(0, (entry ? entry.removableAt : config.currentTime()) - config.currentTime());
        if (remaining <= 0) { return 'Disponible'; }
        var hours = Math.floor(remaining / 3600000);
        var minutes = Math.ceil((remaining % 3600000) / 60000);
        if (minutes >= 60) {
            hours += 1;
            minutes = 0;
        }
        return hours + 'h' + (minutes ? ' ' + minutes + 'm' : '');
    }

    function claimWorkRewards(targetId) {
        var state = config.getState();
        targetId = String(targetId || '');
        enforceWorkRewardCap(false);
        var entries = activeWorkEntries().filter(function (entry) {
            return !targetId || String(entry.copy.instanceId || '') === targetId;
        });
        var pending = targetId ? 0 : normalizeWorkPendingRewards(state.collection && state.collection.workPendingRewards);
        var claimable = pending + entries.reduce(function (sum, entry) {
            return sum + entry.claimable;
        }, 0);
        if (claimable <= 0) {
            config.setStatus(config.uiText('status.no_memory_claim', 'Todavía no hay Mnemones de rememoración para reclamar.'));
            config.renderWorkBench();
            return false;
        }
        var now = config.currentTime();
        entries.forEach(function (entry) {
            if (entry.claimable <= 0 || entry.rate <= 0) { return; }
            var elapsedClaimedMs = Math.floor((entry.claimable / entry.rate) * 60000);
            entry.assignment.lastClaimAt = Math.min(now, config.normalizeTimestamp(entry.assignment.lastClaimAt, now) + elapsedClaimedMs);
        });
        if (!targetId) { state.collection.workPendingRewards = 0; }
        config.addMnemones(claimable);
        config.saveCollection();
        config.playMoneySound();
        config.renderSummary();
        config.renderPackInventory();
        config.renderWorkBench();
        config.renderCollectionTable();
        config.setStatus(config.uiText('status.memory_claimed', 'Rememoración reclamada. +{amount} Mnemones.', { amount: claimable }));
        return true;
    }

    function assignCopyToWork(card, copy, options) {
        options = options || {};
        if (!copy || !copy.instanceId) { return false; }
        if (isCopyWorking(copy.instanceId)) {
            config.setStatus(config.uiText('status.card_already_remembering', 'Esta carta ya está rememorando.'));
            return false;
        }
        if (activeWorkEntries().length >= config.workMaxAssignments()) {
            config.setStatus(config.uiText('status.memory_limit', 'Sólo puedes tener {max} cartas rememorando a la vez.', { max: config.workMaxAssignments() }));
            return false;
        }
        if (config.isCopyInCombatTeam(copy.instanceId)) {
            config.setStatus(config.uiText('status.memory_card_in_team', 'Quita la carta del equipo antes de ponerla a recordar.'));
            return false;
        }
        var assignments = ensureWorkAssignments();
        var now = config.currentTime();
        assignments[String(copy.instanceId)] = { instanceId: String(copy.instanceId), startedAt: now, lastClaimAt: now };
        config.saveCollection();
        config.renderSummary();
        config.renderCollectionTable();
        config.renderCombatSetup();
        if (!options.noModal) {
            config.showCardModal(card, config.ownedCopiesForCard(card.card_id));
        }
        config.setStatus(config.uiText('status.memory_started', 'Carta puesta a recordar: +{rate} Mnemones/min.', { rate: workRatePerMinute(copy, card).toFixed(1) }));
        return true;
    }

    function assignCopyToWorkById(instanceId) {
        var state = config.getState();
        var copy = config.copyByInstanceId(instanceId);
        var card = copy ? state.catalogById[String(copy.cardId || '')] : null;
        if (!copy || !card) {
            config.setStatus(config.uiText('status.memory_card_missing', 'No se encontró esa carta para recordar.'));
            return false;
        }
        return assignCopyToWork(card, copy, { noModal: true });
    }

    function stopCopyWork(instanceId) {
        var state = config.getState();
        var id = String(instanceId || '');
        enforceWorkRewardCap(false);
        var assignments = ensureWorkAssignments();
        if (!assignments[id]) { return false; }
        var entry = workEntryFromAssignment(assignments[id]);
        if (!workCanStop(entry)) {
            config.setStatus(config.uiText('status.memory_min_duration', 'Esta carta debe rememorar al menos 24 horas. Quedan {remaining}.', { remaining: workRemainingLabel(entry) }));
            config.renderWorkBench();
            return false;
        }
        state.collection.workPendingRewards = normalizeWorkPendingRewards(
            normalizeWorkPendingRewards(state.collection.workPendingRewards) + Math.max(0, entry ? entry.claimable : 0)
        );
        delete assignments[id];
        config.saveCollection();
        config.renderSummary();
        config.renderWorkBench();
        config.renderCollectionTable();
        config.renderCombatSetup();
        config.setStatus(config.uiText('status.memory_stopped', 'Carta retirada de la rememoración. Sus ganancias quedan pendientes en Reclamar.'));
        return true;
    }

    var api = Object.freeze({
        configure: configure,
        normalizeWorkAssignments: normalizeWorkAssignments,
        normalizeWorkPendingRewards: normalizeWorkPendingRewards,
        ensureWorkAssignments: ensureWorkAssignments,
        isCopyWorking: isCopyWorking,
        currentWorkRewardCap: currentWorkRewardCap,
        cleanWorkAssignments: cleanWorkAssignments,
        limitWorkAssignments: limitWorkAssignments,
        workRatePerMinute: workRatePerMinute,
        workEntryFromAssignment: workEntryFromAssignment,
        enforceWorkRewardCap: enforceWorkRewardCap,
        activeWorkEntries: activeWorkEntries,
        workCandidateEntries: workCandidateEntries,
        totalWorkClaimable: totalWorkClaimable,
        workCanStop: workCanStop,
        workRemainingLabel: workRemainingLabel,
        claimWorkRewards: claimWorkRewards,
        assignCopyToWork: assignCopyToWork,
        assignCopyToWorkById: assignCopyToWorkById,
        stopCopyWork: stopCopyWork
    });

    app.memory.core = api;
})(window);
