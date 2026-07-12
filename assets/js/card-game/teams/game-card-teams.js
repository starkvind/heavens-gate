(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.teams = app.teams || {};

    var config = {
        getState: function () { return null; },
        readMigratedJson: function () { return null; },
        writeJson: function () { return false; },
        loadCollection: function () {},
        clampInt: function (value, fallback) {
            var n = Number(value);
            return Number.isFinite(n) ? Math.round(n) : fallback;
        },
        isCopyWorking: function () { return false; },
        combatTeamsKey: function () { return 'combat_teams'; },
        legacyCombatTeamsKey: function () { return 'combat_teams_legacy'; },
        combatProfileKey: function () { return 'combat_profile'; },
        legacyCombatProfileKey: function () { return 'combat_profile_legacy'; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function createEmptyCombatTeams() {
        return {
            version: 2,
            activeTeam: 0,
            teams: [0, 1, 2, 3, 4].map(function (index) {
                return { name: 'Equipo ' + (index + 1), cards: [] };
            })
        };
    }

    function defaultCombatTeamName(index) {
        return 'Equipo ' + (Number(index || 0) + 1);
    }

    function combatTeamDisplayName(team, index) {
        var name = String(team && team.name || '').trim();
        return name || defaultCombatTeamName(index);
    }

    function normalizeCombatTeams(data) {
        var out = createEmptyCombatTeams();
        if (!data || typeof data !== 'object') { return out; }
        out.activeTeam = Math.max(0, Math.min(4, config.clampInt(data.activeTeam, 0)));
        if (Array.isArray(data.teams)) {
            data.teams.slice(0, 5).forEach(function (team, index) {
                if (!team || typeof team !== 'object') { return; }
                out.teams[index] = {
                    name: String(team.name || defaultCombatTeamName(index)).slice(0, 40),
                    cards: Array.isArray(team.cards) ? team.cards.map(function (id) {
                        return String(id || '').slice(0, 80);
                    }).filter(Boolean).slice(0, 5) : []
                };
            });
        }
        return out;
    }

    function loadCombatTeams() {
        var state = config.getState();
        if (state.combatTeams) { return state.combatTeams; }
        state.combatTeams = normalizeCombatTeams(config.readMigratedJson(config.combatTeamsKey(), config.legacyCombatTeamsKey(), null));
        state.activeCombatTeam = state.combatTeams.activeTeam;
        state.draftCombatTeam = state.combatTeams.teams[state.activeCombatTeam].cards.slice();
        saveCombatTeams();
        return state.combatTeams;
    }

    function saveCombatTeams() {
        var state = config.getState();
        if (!state.combatTeams) { loadCombatTeams(); }
        state.combatTeams.activeTeam = state.activeCombatTeam;
        config.writeJson(config.combatTeamsKey(), state.combatTeams);
    }

    function ownedCopyIdMap(availableForCombat) {
        var state = config.getState();
        var ids = {};
        if (!state.collection) { config.loadCollection(); }
        (state.collection.ownedCards || []).forEach(function (copy) {
            if (copy && copy.instanceId && (!availableForCombat || !config.isCopyWorking(copy.instanceId))) {
                ids[String(copy.instanceId)] = true;
            }
        });
        return ids;
    }

    function pruneDraftCombatTeam(ownedMap, removeMap) {
        var state = config.getState();
        var seen = {};
        state.draftCombatTeam = (state.draftCombatTeam || []).filter(function (id) {
            id = String(id || '');
            if (!id || seen[id]) { return false; }
            if (ownedMap && !ownedMap[id]) { return false; }
            if (removeMap && removeMap[id]) { return false; }
            seen[id] = true;
            return true;
        }).slice(0, 5);
    }

    function cleanCombatTeamsAgainstCollection(persist) {
        var state = config.getState();
        var owned;
        var changed = false;
        loadCombatTeams();
        owned = ownedCopyIdMap(true);
        state.combatTeams.teams.forEach(function (team) {
            var seen = {};
            var clean = [];
            (team.cards || []).forEach(function (id) {
                id = String(id || '');
                if (!id || !owned[id] || seen[id] || clean.length >= 5) {
                    changed = true;
                    return;
                }
                seen[id] = true;
                clean.push(id);
            });
            if (clean.length !== (team.cards || []).length) {
                changed = true;
            }
            team.cards = clean;
        });
        if (changed) {
            pruneDraftCombatTeam(owned, null);
            if (persist) { saveCombatTeams(); }
        }
        return changed;
    }

    function removeCopiesFromCombatTeams(removeMap) {
        var state = config.getState();
        var removed = 0;
        if (!removeMap) { return 0; }
        loadCombatTeams();
        state.combatTeams.teams.forEach(function (team) {
            team.cards = (team.cards || []).filter(function (id) {
                var remove = !!removeMap[String(id || '')];
                if (remove) { removed += 1; }
                return !remove;
            }).slice(0, 5);
        });
        if (removed > 0) {
            pruneDraftCombatTeam(null, removeMap);
            saveCombatTeams();
        }
        return removed;
    }

    function normalizeCombatProfile(data) {
        data = data && typeof data === 'object' ? data : {};
        return {
            playerName: String(data.playerName || '').slice(0, 32)
        };
    }

    function loadCombatProfile() {
        var state = config.getState();
        if (state.combatProfile) { return state.combatProfile; }
        state.combatProfile = normalizeCombatProfile(config.readMigratedJson(config.combatProfileKey(), config.legacyCombatProfileKey(), null));
        saveCombatProfile();
        return state.combatProfile;
    }

    function saveCombatProfile() {
        var state = config.getState();
        if (!state.combatProfile) { loadCombatProfile(); }
        config.writeJson(config.combatProfileKey(), state.combatProfile);
    }

    function combatPlayerName() {
        var profile = loadCombatProfile();
        return profile.playerName.trim() || 'Jugador';
    }

    function copyByInstanceId(instanceId) {
        var state = config.getState();
        var id = String(instanceId || '');
        var i;
        var copy;
        if (!state.collection) { config.loadCollection(); }
        for (i = 0; i < (state.collection.ownedCards || []).length; i++) {
            copy = state.collection.ownedCards[i];
            if (String(copy.instanceId || '') === id) { return copy; }
        }
        return null;
    }

    function validDraftTeam() {
        var state = config.getState();
        var seen = {};
        return (state.draftCombatTeam || []).filter(function (id) {
            if (seen[id] || !copyByInstanceId(id) || config.isCopyWorking(id)) { return false; }
            seen[id] = true;
            return true;
        }).slice(0, 5);
    }

    var api = Object.freeze({
        configure: configure,
        createEmptyCombatTeams: createEmptyCombatTeams,
        defaultCombatTeamName: defaultCombatTeamName,
        combatTeamDisplayName: combatTeamDisplayName,
        normalizeCombatTeams: normalizeCombatTeams,
        loadCombatTeams: loadCombatTeams,
        saveCombatTeams: saveCombatTeams,
        ownedCopyIdMap: ownedCopyIdMap,
        pruneDraftCombatTeam: pruneDraftCombatTeam,
        cleanCombatTeamsAgainstCollection: cleanCombatTeamsAgainstCollection,
        removeCopiesFromCombatTeams: removeCopiesFromCombatTeams,
        normalizeCombatProfile: normalizeCombatProfile,
        loadCombatProfile: loadCombatProfile,
        saveCombatProfile: saveCombatProfile,
        combatPlayerName: combatPlayerName,
        copyByInstanceId: copyByInstanceId,
        validDraftTeam: validDraftTeam
    });

    app.teams.core = api;
})(window);
