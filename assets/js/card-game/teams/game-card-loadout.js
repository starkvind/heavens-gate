(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.teams = app.teams || {};

    var config = {
        getState: function () { return null; },
        getEls: function () { return {}; },
        isCombatContext: function () { return false; },
        isCombatLoadoutVisible: function () { return false; },
        loadCollection: function () {},
        loadCombatTeams: function () { return null; },
        saveCombatTeams: function () {},
        loadCombatProfile: function () { return null; },
        saveCombatProfile: function () {},
        cleanCombatTeamsAgainstCollection: function () { return false; },
        combatTeamDisplayName: function (_, index) { return 'Equipo ' + (Number(index || 0) + 1); },
        combatPlayerName: function () { return 'Jugador'; },
        copyByInstanceId: function () { return null; },
        validDraftTeam: function () { return []; },
        copyRarity: function () { return 'common'; },
        cardForCopy: function (_, copy) { return copy; },
        totalStats: function () { return 0; },
        qualityScore: function () { return 0; },
        rarityRank: function () { return 0; },
        isCopyWorking: function () { return false; },
        typeLabel: function (type) { return String(type || ''); },
        rarityLabels: function () { return {}; },
        typeOrder: function () { return []; },
        uiText: function (key, fallback, values) {
            var text = fallback || key;
            return values ? text.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
                return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
            }) : text;
        },
        escapeHtml: function (value) { return String(value || ''); },
        setCombatMessage: function () {},
        updateCombatModeButtons: function () {},
        loadDailyBossState: function () { return null; },
        dailyBossRewardClaimedToday: function () { return false; },
        renderDailyBossSummary: function () {},
        renderCombatBattle: function () {},
        normalizeCollectionRarity: function (value) { return String(value || 'all'); }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function combatEntryFromCopy(copy) {
        var state = config.getState();
        var card = copy ? state.catalogById[String(copy.cardId || '')] : null;
        if (!card) { return null; }
        return { card: config.cardForCopy(card, copy), baseCard: card, copy: copy, score: config.totalStats(copy) };
    }

    function combatStatPillHtml(label, value) {
        return '<span><em>' + config.escapeHtml(label) + '</em><b>' + config.escapeHtml(value) + '</b></span>';
    }

    function combatEntryStatsHtml(entry, options) {
        var copy;
        var card;
        var hpText;
        var stats;
        if (!entry) { return ''; }
        options = options || {};
        copy = entry.copy;
        card = entry.card;
        hpText = options.currentHp !== undefined
            ? String(options.currentHp) + ' / ' + String(options.maxHp || copy.hp || 0)
            : String(copy.hp || 0);
        stats = [
            combatStatPillHtml('Total', entry.score),
            combatStatPillHtml('PS', hpText),
            combatStatPillHtml('ATQ', copy.atk || 0),
            combatStatPillHtml('DEF', options.effectiveDef !== undefined ? options.effectiveDef : (copy.def || 0))
        ];
        if (options.includeQuality) {
            stats.push(combatStatPillHtml('CAL', config.qualityScore(copy, entry.baseCard).toFixed(1) + '%'));
        }
        return '<span class="hg-combat-statline">' + stats.join('') + '</span>' +
            '<small class="hg-combat-card-meta">' +
                '<span>' + config.escapeHtml((config.rarityLabels() || {})[card.card_rarity] || card.card_rarity) + '</span>' +
                '<span>' + config.escapeHtml(config.typeLabel(card.source_type)) + '</span>' +
            '</small>';
    }

    function combatOwnedEntries() {
        var state = config.getState();
        if (!state.collection) { config.loadCollection(); }
        return (state.collection.ownedCards || []).filter(function (copy) {
            return !config.isCopyWorking(copy.instanceId);
        }).map(combatEntryFromCopy).filter(Boolean).sort(function (a, b) {
            var dateDiff = String(b.copy.obtainedAt || '').localeCompare(String(a.copy.obtainedAt || ''));
            if (dateDiff !== 0) { return dateDiff; }
            return String(b.copy.instanceId || '').localeCompare(String(a.copy.instanceId || ''));
        });
    }

    function sortCombatEntries(entries) {
        var state = config.getState();
        var mode = state.combatSort || 'quality';
        return entries.slice().sort(function (a, b) {
            if (mode === 'total') {
                return b.score - a.score
                    || config.qualityScore(b.copy, b.baseCard) - config.qualityScore(a.copy, a.baseCard)
                    || String(a.card.card_name).localeCompare(String(b.card.card_name));
            }
            if (mode === 'rarity') {
                return config.rarityRank(b.card.card_rarity) - config.rarityRank(a.card.card_rarity)
                    || config.qualityScore(b.copy, b.baseCard) - config.qualityScore(a.copy, a.baseCard)
                    || b.score - a.score;
            }
            if (mode === 'recent') {
                return String(b.copy.obtainedAt || '').localeCompare(String(a.copy.obtainedAt || ''))
                    || String(b.copy.instanceId || '').localeCompare(String(a.copy.instanceId || ''));
            }
            if (mode === 'name') {
                return String(a.card.card_name).localeCompare(String(b.card.card_name))
                    || config.qualityScore(b.copy, b.baseCard) - config.qualityScore(a.copy, a.baseCard);
            }
            return config.qualityScore(b.copy, b.baseCard) - config.qualityScore(a.copy, a.baseCard)
                || b.score - a.score
                || config.rarityRank(b.card.card_rarity) - config.rarityRank(a.card.card_rarity)
                || String(a.card.card_name).localeCompare(String(b.card.card_name));
        });
    }

    function renderCombatTypeFilter(entries) {
        var state = config.getState();
        var els = config.getEls();
        var counts = { all: entries.length };
        var types;
        var signature;
        if (!els.combatTypeFilter) { return; }
        entries.forEach(function (entry) {
            counts[entry.card.source_type] = (counts[entry.card.source_type] || 0) + 1;
        });
        types = (config.typeOrder() || []).filter(function (type) {
            return type === 'all' || counts[type] > 0;
        });
        Object.keys(counts).sort().forEach(function (type) {
            if (types.indexOf(type) === -1) { types.push(type); }
        });
        signature = types.map(function (type) { return type + ':' + counts[type]; }).join('|');
        if (els.combatTypeFilter.getAttribute('data-options-signature') !== signature) {
            els.combatTypeFilter.innerHTML = types.map(function (type) {
                var label = type === 'all' ? 'Todas' : config.typeLabel(type);
                return '<option value="' + config.escapeHtml(type) + '">' + config.escapeHtml(label) + ' (' + (counts[type] || 0) + ')</option>';
            }).join('');
            els.combatTypeFilter.setAttribute('data-options-signature', signature);
        }
        if (!counts[state.combatTypeFilter] && state.combatTypeFilter !== 'all') {
            state.combatTypeFilter = 'all';
        }
        els.combatTypeFilter.value = state.combatTypeFilter;
    }

    function renderCombatTeamSelect() {
        var state = config.getState();
        var els = config.getEls();
        var html;
        if (!els.combatTeamSelects.length) { return; }
        config.loadCombatTeams();
        html = state.combatTeams.teams.map(function (team, index) {
            return '<option value="' + index + '">' + config.escapeHtml(config.combatTeamDisplayName(team, index)) + ' (' + team.cards.length + '/5)</option>';
        }).join('');
        els.combatTeamSelects.forEach(function (select) {
            select.innerHTML = html;
            select.value = String(state.activeCombatTeam);
        });
    }

    function renderCombatTeamName() {
        var state = config.getState();
        var els = config.getEls();
        var team;
        var name;
        if (!els.combatTeamNames.length) { return; }
        config.loadCombatTeams();
        team = state.combatTeams.teams[state.activeCombatTeam] || state.combatTeams.teams[0];
        name = config.combatTeamDisplayName(team, state.activeCombatTeam);
        els.combatTeamNames.forEach(function (input) {
            if (input.value !== name) { input.value = name; }
        });
    }

    function renderCombatTeamPreview() {
        var state = config.getState();
        var els = config.getEls();
        var team;
        var ids;
        if (!els.combatTeamPreviews.length) { return; }
        config.loadCombatTeams();
        team = state.combatTeams.teams[state.activeCombatTeam] || state.combatTeams.teams[0];
        ids = team ? (team.cards || []).slice(0, 5) : [];
        els.combatTeamPreviews.forEach(function (preview) {
            var total = 0;
            preview.innerHTML = '';
            if (!ids.length) {
                var empty = global.document.createElement('span');
                empty.className = 'hg-combat-team-preview__empty';
                empty.textContent = config.uiText('combat.team_empty', 'Equipo vacío. Prepáralo antes de combatir.');
                preview.appendChild(empty);
                return;
            }
            for (var i = 0; i < 5; i++) {
                var id = ids[i] || '';
                var entry = combatEntryFromCopy(config.copyByInstanceId(id));
                var item = global.document.createElement('span');
                item.className = 'hg-combat-team-preview__card' + (entry ? ' hg-collection-row--' + entry.card.card_rarity : ' is-empty');
                if (entry) {
                    total += entry.score;
                    item.innerHTML =
                        '<strong>' + config.escapeHtml(entry.card.card_name) + '</strong>' +
                        '<small>PS ' + config.escapeHtml(entry.copy.hp) + ' · ATQ ' + config.escapeHtml(entry.copy.atk) + ' · DEF ' + config.escapeHtml(entry.copy.def) + '</small>';
                } else {
                    item.innerHTML = '<strong>' + config.escapeHtml(config.uiText('memory.slot_label', 'Hueco {slot}', { slot: i + 1 })) + '</strong><small>' + config.escapeHtml(config.uiText('combat.slot_empty', 'Sin carta')) + '</small>';
                }
                preview.appendChild(item);
            }
            var totalItem = global.document.createElement('span');
            totalItem.className = 'hg-combat-team-preview__total';
            totalItem.innerHTML = '<strong>' + config.escapeHtml(config.uiText('combat.team_total', 'Total equipo')) + '</strong><small>' + total + '</small>';
            preview.appendChild(totalItem);
        });
    }

    function renderCombatProfile() {
        var els = config.getEls();
        var profile;
        if (!els.combatProfileNames.length) { return; }
        profile = config.loadCombatProfile();
        els.combatProfileNames.forEach(function (input) {
            if (input.value !== profile.playerName) { input.value = profile.playerName; }
        });
    }

    function renderCombatTeamSlots() {
        var state = config.getState();
        var els = config.getEls();
        var teamTotal;
        var summary;
        if (!els.combatTeamSlots) { return; }
        state.draftCombatTeam = config.validDraftTeam();
        els.combatTeamSlots.innerHTML = '';
        teamTotal = state.draftCombatTeam.reduce(function (sum, id) {
            var entry = combatEntryFromCopy(config.copyByInstanceId(id));
            return sum + (entry ? entry.score : 0);
        }, 0);
        summary = global.document.createElement('div');
        summary.className = 'hg-combat-team-total';
        summary.innerHTML = '<strong>' + config.escapeHtml(config.uiText('combat.team_total_full', 'Total del equipo')) + '</strong><b>' + teamTotal + '</b><span>' + config.escapeHtml(config.uiText('combat.cards_count', '{count} / 5 cartas', { count: state.draftCombatTeam.length })) + '</span>';
        els.combatTeamSlots.appendChild(summary);
        for (var i = 0; i < 5; i++) {
            var id = state.draftCombatTeam[i] || '';
            var entry = combatEntryFromCopy(config.copyByInstanceId(id));
            var slot = global.document.createElement('button');
            slot.type = 'button';
            slot.className = 'hg-combat-team-slot' + (entry ? ' is-filled' : '');
            slot.setAttribute('data-combat-slot', String(i));
            if (entry) {
                slot.innerHTML =
                    '<strong>' + config.escapeHtml(entry.card.card_name) + '</strong>' +
                    combatEntryStatsHtml(entry) +
                    '<small>Quitar</small>';
                slot.addEventListener('click', function () {
                    state.draftCombatTeam.splice(Number(this.getAttribute('data-combat-slot') || 0), 1);
                    renderCombatSetup();
                });
            } else {
                slot.innerHTML = '<strong>' + config.escapeHtml(config.uiText('memory.slot_label', 'Hueco {slot}', { slot: i + 1 })) + '</strong><span>' + config.escapeHtml(config.uiText('combat.choose_card', 'Elige una carta')) + '</span>';
            }
            els.combatTeamSlots.appendChild(slot);
        }
    }

    function renderCombatCardList() {
        var state = config.getState();
        var els = config.getEls();
        var selected = {};
        var onlyReady;
        var allEntries;
        var entries;
        if (!els.combatCardList) { return; }
        state.draftCombatTeam.forEach(function (id) { selected[id] = true; });
        onlyReady = !els.combatOnlyReady || els.combatOnlyReady.checked;
        allEntries = combatOwnedEntries();
        renderCombatTypeFilter(allEntries);
        entries = sortCombatEntries(allEntries.filter(function (entry) {
            return (!onlyReady || !selected[String(entry.copy.instanceId || '')])
                && (state.combatRarityFilter === 'all' || entry.card.card_rarity === state.combatRarityFilter)
                && (state.combatTypeFilter === 'all' || entry.card.source_type === state.combatTypeFilter);
        }));
        els.combatCardList.innerHTML = '';
        if (!entries.length) {
            var empty = global.document.createElement('p');
            empty.className = 'hg-empty-state';
            empty.textContent = state.catalog.length ? config.uiText('combat.cards_filter_empty', 'No hay cartas disponibles con esos filtros.') : config.uiText('combat.cards_loading', 'Cargando cartas...');
            els.combatCardList.appendChild(empty);
            return;
        }
        entries.forEach(function (entry) {
            var button = global.document.createElement('button');
            button.type = 'button';
            button.className = 'hg-combat-card-pick hg-collection-row--' + entry.card.card_rarity;
            button.disabled = state.draftCombatTeam.length >= 5 || !!selected[String(entry.copy.instanceId || '')];
            button.innerHTML =
                '<strong>' + config.escapeHtml(entry.card.card_name) + '</strong>' +
                combatEntryStatsHtml(entry, { includeQuality: true });
            button.addEventListener('click', function () {
                if (state.draftCombatTeam.length >= 5) {
                    config.setCombatMessage(config.uiText('combat.team_full', 'El equipo ya tiene 5 cartas.'));
                    return;
                }
                state.draftCombatTeam.push(String(entry.copy.instanceId || ''));
                renderCombatSetup();
            });
            els.combatCardList.appendChild(button);
        });
    }

    function autoBuildCombatTeam(options) {
        var state = config.getState();
        var entries;
        var picked;
        options = options || {};
        if (!config.isCombatContext()) { return false; }
        config.loadCombatTeams();
        entries = combatOwnedEntries().filter(function (entry) {
            if (options.ignoreFilters) { return true; }
            return (state.combatRarityFilter === 'all' || entry.card.card_rarity === state.combatRarityFilter)
                && (state.combatTypeFilter === 'all' || entry.card.source_type === state.combatTypeFilter);
        }).sort(function (a, b) {
            return b.score - a.score
                || config.qualityScore(b.copy, b.baseCard) - config.qualityScore(a.copy, a.baseCard)
                || config.rarityRank(b.card.card_rarity) - config.rarityRank(a.card.card_rarity)
                || String(a.card.card_name).localeCompare(String(b.card.card_name));
        });
        picked = entries.slice(0, 5).map(function (entry) {
            return String(entry.copy.instanceId || '');
        }).filter(Boolean);
        if (!picked.length) {
            config.setCombatMessage(config.uiText('combat.auto_no_cards', 'No hay cartas disponibles para crear autoequipo con esos filtros.'));
            return false;
        }
        if (options.requireFullTeam && picked.length < 5) {
            config.setCombatMessage(config.uiText('combat.auto_need_full', 'Necesitas al menos 5 cartas disponibles para crear un equipo rápido.'));
            return false;
        }
        state.draftCombatTeam = picked;
        state.combatTeams.teams[state.activeCombatTeam].cards = picked.slice();
        config.saveCombatTeams();
        renderCombatSetup();
        config.setCombatMessage(config.uiText('combat.auto_saved', 'Autoequipo guardado: {count}/5 mejores cartas disponibles.', { count: picked.length }));
        return true;
    }

    function renderCombatSetup() {
        var state = config.getState();
        var els = config.getEls();
        var bossState;
        var completed;
        if (!config.isCombatContext()) { return; }
        config.cleanCombatTeamsAgainstCollection(true);
        config.updateCombatModeButtons();
        if (els.combatSort) { els.combatSort.value = state.combatSort; }
        renderCombatTeamSelect();
        renderCombatTeamName();
        renderCombatTeamPreview();
        renderCombatProfile();
        if (els.combatStart) {
            bossState = state.combatMode === 'daily-boss' ? (state.dailyBoss || config.loadDailyBossState()) : null;
            completed = bossState && (bossState.completed || bossState.hp <= 0 || config.dailyBossRewardClaimedToday());
            global.document.querySelector('.hg-cards').classList.toggle('is-daily-boss-completed', !!completed);
            els.combatStart.disabled = !!completed;
            els.combatStart.textContent = state.combatMode === 'daily-boss'
                ? (completed ? 'Desafío diario completado' : 'Desafiar jefe diario')
                : 'Iniciar combate';
        }
        if (!config.isCombatLoadoutVisible() || !els.combatTeamSlots) { return; }
        renderCombatTeamSlots();
        renderCombatCardList();
    }

    function showCombatScreen(screen) {
        var state = config.getState();
        var els = config.getEls();
        state.activeCombatScreen = screen === 'loadout' ? 'loadout' : 'battle';
        els.combatScreenTabs.forEach(function (button) {
            var active = (button.getAttribute('data-combat-screen-tab') || 'battle') === state.activeCombatScreen;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        els.combatScreenPanels.forEach(function (panel) {
            var activePanel = (panel.getAttribute('data-combat-screen') || 'battle') === state.activeCombatScreen;
            panel.hidden = !activePanel;
            panel.classList.toggle('is-active', activePanel);
        });
        if (state.activeCombatScreen === 'loadout') { renderCombatSetup(); }
        if (state.activeCombatScreen === 'battle') {
            renderCombatSetup();
            config.renderCombatBattle();
        }
    }

    function saveDraftCombatTeam() {
        var state = config.getState();
        config.loadCombatTeams();
        state.draftCombatTeam = config.validDraftTeam();
        state.combatTeams.teams[state.activeCombatTeam].cards = state.draftCombatTeam.slice();
        config.saveCombatTeams();
        renderCombatSetup();
        config.setCombatMessage(state.draftCombatTeam.length + '/5 cartas guardadas en ' + config.combatTeamDisplayName(state.combatTeams.teams[state.activeCombatTeam], state.activeCombatTeam) + '.');
    }

    function clearDraftCombatTeam() {
        var state = config.getState();
        state.draftCombatTeam = [];
        saveDraftCombatTeam();
    }

    var api = Object.freeze({
        configure: configure,
        combatEntryFromCopy: combatEntryFromCopy,
        combatStatPillHtml: combatStatPillHtml,
        combatEntryStatsHtml: combatEntryStatsHtml,
        combatOwnedEntries: combatOwnedEntries,
        sortCombatEntries: sortCombatEntries,
        renderCombatTypeFilter: renderCombatTypeFilter,
        renderCombatTeamSelect: renderCombatTeamSelect,
        renderCombatTeamName: renderCombatTeamName,
        renderCombatTeamPreview: renderCombatTeamPreview,
        renderCombatProfile: renderCombatProfile,
        renderCombatTeamSlots: renderCombatTeamSlots,
        renderCombatCardList: renderCombatCardList,
        autoBuildCombatTeam: autoBuildCombatTeam,
        renderCombatSetup: renderCombatSetup,
        showCombatScreen: showCombatScreen,
        saveDraftCombatTeam: saveDraftCombatTeam,
        clearDraftCombatTeam: clearDraftCombatTeam
    });

    app.teams.loadout = api;
})(window);
