(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.combat = app.combat || {};

    var config = {
        getState: function () { return null; },
        getEls: function () { return {}; },
        readJson: function () { return null; },
        writeJson: function () { return false; },
        dailyBossRewardKey: function () { return ''; },
        dailyBossStateKey: function () { return ''; },
        dailyFreePackDate: function () { return ''; },
        normalizeTimestamp: function (value, fallback) {
            var n = Number(value);
            return Number.isFinite(n) && n > 0 ? n : fallback;
        },
        createCardCopy: function (_, copy) { return copy; },
        rollStat: function (min) { return min; },
        clampInt: function (value, fallback) {
            var n = Number(value);
            return Number.isFinite(n) ? Math.round(n) : fallback;
        },
        clampQuality: function (value, fallback) {
            var n = Number(value);
            return Number.isFinite(n) ? Math.max(0, Math.min(100, n)) : fallback;
        },
        calculatedQualityScore: function () { return 100; },
        nowIso: function () { return ''; },
        cardForCopy: function (_, copy) { return copy; },
        loadCollection: function () {},
        saveCollection: function () {},
        renderSummary: function () {},
        renderCollectionTable: function () {},
        renderCombatSetup: function () {},
        renderCombatBattle: function () {},
        setCombatMessage: function () {},
        setStatus: function () {},
        uiText: function (key, fallback, values) {
            var text = fallback || key;
            return values ? text.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
                return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
            }) : text;
        },
        escapeHtml: function (value) { return String(value || ''); },
        formatNumber: function (value) { return String(value || 0); },
        removeCopiesFromCombatTeams: function () {},
        cleanCombatTeamsAgainstCollection: function () {},
        isCombatContext: function () { return false; },
        preloadCombatSounds: function () {},
        validDraftTeam: function () { return []; },
        promptQuickCombatTeam: function () { return false; },
        confirmGameAction: function (_, __, onConfirm) { if (onConfirm) { onConfirm(); } return true; },
        combatEntryFromCopy: function () { return null; },
        copyByInstanceId: function () { return null; },
        createCombatUnit: function () { return null; },
        showCombatScreen: function () {},
        animateCombatEntry: function () {},
        combatPlayerName: function () { return 'Jugador'; },
        pushCombatLog: function () {},
        addMnemones: function () {},
        addRemorias: function () {},
        addMaterial: function () {},
        upgradeMaterials: function () { return {}; },
        normalizeDropConfigList: function (value) { return Array.isArray(value) ? value : []; },
        normalizeRewardRangeConfig: function (_, min, max) { return { min: min, max: max }; },
        dailyBossLootTable: function () { return {}; },
        rarityOrder: function () { return []; },
        dailyBossCardReward: function () { return {}; },
        instanceId: function () { return ''; },
        dailyBossHpMultiplierMin: function () { return 1; },
        dailyBossHpMultiplierMax: function () { return 1; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function dailyBossRewardState() {
        var data = config.readJson(config.dailyBossRewardKey(), null);
        if (!data || typeof data !== 'object') {
            return { date: '', rewardCopyId: '' };
        }
        return {
            date: String(data.date || ''),
            rewardCopyId: String(data.rewardCopyId || '')
        };
    }

    function dailyBossRewardClaimedToday() {
        return dailyBossRewardState().date === config.dailyFreePackDate();
    }

    function markDailyBossRewardClaimed(copyId) {
        config.writeJson(config.dailyBossRewardKey(), {
            date: config.dailyFreePackDate(),
            rewardCopyId: String(copyId || '')
        });
    }

    function dailyBossActiveAttempt(value) {
        var seenRisk = {};
        var seenDefeated = {};
        value = value && typeof value === 'object' ? value : null;
        if (!value) { return null; }
        return {
            startedAt: config.normalizeTimestamp(value.startedAt, Date.now()),
            riskedCopyIds: (Array.isArray(value.riskedCopyIds) ? value.riskedCopyIds : []).map(function (id) {
                return String(id || '').slice(0, 80);
            }).filter(function (id) {
                if (!id || seenRisk[id]) { return false; }
                seenRisk[id] = true;
                return true;
            }).slice(0, 5),
            defeatedCopyIds: (Array.isArray(value.defeatedCopyIds) ? value.defeatedCopyIds : []).map(function (id) {
                return String(id || '').slice(0, 80);
            }).filter(function (id) {
                if (!id || seenDefeated[id]) { return false; }
                seenDefeated[id] = true;
                return true;
            }).slice(0, 5)
        };
    }

    function pickDailyBossCard() {
        var state = config.getState();
        var pool = (state.catalog || []).filter(function (card) {
            return card.source_type === 'character' && card.card_rarity !== 'stigmatic';
        });
        var date;
        var seed;
        if (!pool.length) {
            pool = (state.catalog || []).filter(function (card) { return card.card_rarity !== 'stigmatic'; });
        }
        if (!pool.length) { return null; }
        date = config.dailyFreePackDate().replace(/-/g, '');
        seed = config.clampInt(date, 1);
        return pool[seed % pool.length];
    }

    function createDailyBossState(card) {
        var copy;
        var hpMultiplier;
        var maxHp;
        if (!card) { return null; }
        copy = config.createCardCopy(card, { rarity: 'stigmatic', instanceId: 'daily-boss-' + config.dailyFreePackDate() });
        hpMultiplier = config.rollStat(config.dailyBossHpMultiplierMin(), config.dailyBossHpMultiplierMax());
        maxHp = Math.max(1, config.clampInt(copy.hp * hpMultiplier, copy.hp));
        return {
            version: 1,
            date: config.dailyFreePackDate(),
            cardId: card.card_id,
            cardName: card.card_name,
            instanceId: copy.instanceId,
            hpMultiplier: hpMultiplier,
            hp: maxHp,
            maxHp: maxHp,
            atk: Math.max(1, config.clampInt(copy.atk * 0.35, copy.atk)),
            def: Math.max(1, config.clampInt(copy.def * 0.15, 1)),
            quality: config.calculatedQualityScore(copy, card),
            attempts: 0,
            completed: false,
            activeAttempt: null,
            updatedAt: config.nowIso()
        };
    }

    function normalizeDailyBossState(data, card) {
        var maxHp;
        var hp;
        if (!card) { return null; }
        if (!data || typeof data !== 'object' || data.date !== config.dailyFreePackDate() || config.clampInt(data.cardId, 0) !== card.card_id) {
            return createDailyBossState(card);
        }
        maxHp = Math.max(1, config.clampInt(data.maxHp, card.hp_max || 1));
        hp = Math.max(0, Math.min(maxHp, config.clampInt(data.hp, maxHp)));
        return {
            version: 1,
            date: config.dailyFreePackDate(),
            cardId: card.card_id,
            cardName: card.card_name,
            instanceId: String(data.instanceId || ('daily-boss-' + config.dailyFreePackDate())).slice(0, 80),
            hpMultiplier: Math.max(config.dailyBossHpMultiplierMin(), Math.min(config.dailyBossHpMultiplierMax(), config.clampInt(data.hpMultiplier, config.dailyBossHpMultiplierMin()))),
            hp: hp,
            maxHp: maxHp,
            atk: Math.max(1, config.clampInt(data.atk, card.atk_max || 1)),
            def: Math.max(1, config.clampInt(data.def, card.def_max || 1)),
            quality: config.clampQuality(data.quality, 100),
            attempts: Math.max(0, config.clampInt(data.attempts, 0)),
            completed: !!data.completed || dailyBossRewardClaimedToday() || hp <= 0,
            activeAttempt: dailyBossActiveAttempt(data.activeAttempt),
            updatedAt: String(data.updatedAt || config.nowIso())
        };
    }

    function loadDailyBossState() {
        var state = config.getState();
        var card = pickDailyBossCard();
        if (!card) {
            state.dailyBoss = null;
            return null;
        }
        state.dailyBoss = normalizeDailyBossState(config.readJson(config.dailyBossStateKey(), null), card);
        saveDailyBossState();
        return state.dailyBoss;
    }

    function saveDailyBossState() {
        var state = config.getState();
        if (!state.dailyBoss) { return; }
        state.dailyBoss.updatedAt = config.nowIso();
        config.writeJson(config.dailyBossStateKey(), state.dailyBoss);
    }

    function resetDailyBossState() {
        var state = config.getState();
        try {
            global.localStorage.removeItem(config.dailyBossStateKey());
            global.localStorage.removeItem(config.dailyBossRewardKey());
        } catch (e) {}
        state.combat = null;
        state.dailyBoss = null;
        loadDailyBossState();
        config.renderCombatSetup();
        config.renderCombatBattle();
        config.setCombatMessage(config.uiText('combat.daily_reset', 'Jefe diario reiniciado para depuración.'));
    }

    function dailyBossEntryFromState() {
        var state = config.getState();
        var bossState = state.dailyBoss || loadDailyBossState();
        var card = bossState ? state.catalogById[String(bossState.cardId || '')] : null;
        var copy;
        if (!bossState || !card) { return null; }
        copy = {
            instanceId: bossState.instanceId,
            cardId: card.card_id,
            rarity: 'stigmatic',
            hp: bossState.maxHp,
            atk: bossState.atk,
            def: bossState.def,
            quality: bossState.quality,
            obtainedAt: bossState.date
        };
        return { card: config.cardForCopy(card, copy), baseCard: card, copy: copy, bossState: bossState };
    }

    function updateDailyBossHp(hp) {
        var state = config.getState();
        var bossState;
        if (!state.combat || state.combat.mode !== 'daily-boss') { return; }
        bossState = state.dailyBoss || loadDailyBossState();
        if (!bossState) { return; }
        bossState.hp = Math.max(0, Math.min(bossState.maxHp, config.clampInt(hp, bossState.hp)));
        if (bossState.hp <= 0) { bossState.completed = true; }
        saveDailyBossState();
        renderDailyBossSummary();
    }

    function startDailyBossAttempt(teamIds) {
        var state = config.getState();
        var bossState = state.dailyBoss || loadDailyBossState();
        if (!bossState) { return null; }
        bossState.attempts = Math.max(0, config.clampInt(bossState.attempts, 0)) + 1;
        bossState.activeAttempt = {
            startedAt: Date.now(),
            riskedCopyIds: teamIds.slice(0, 5),
            defeatedCopyIds: []
        };
        saveDailyBossState();
        return bossState.activeAttempt;
    }

    function markDailyBossCopyDefeated(copyId) {
        var state = config.getState();
        var id = String(copyId || '');
        var bossState = state.dailyBoss || loadDailyBossState();
        if (!state.combat || state.combat.mode !== 'daily-boss') { return; }
        if (!id || !bossState || !bossState.activeAttempt) { return; }
        if (bossState.activeAttempt.defeatedCopyIds.indexOf(id) === -1) {
            bossState.activeAttempt.defeatedCopyIds.push(id);
            saveDailyBossState();
        }
    }

    function destroyDailyBossCopies(copyIds) {
        var state = config.getState();
        var remove = {};
        var count = 0;
        if (!copyIds || !copyIds.length) { return 0; }
        if (!state.collection) { config.loadCollection(); }
        copyIds.forEach(function (id) {
            id = String(id || '');
            if (id) { remove[id] = true; }
        });
        state.collection.ownedCards = (state.collection.ownedCards || []).filter(function (copy) {
            var id = String(copy && copy.instanceId || '');
            if (remove[id]) {
                count++;
                return false;
            }
            return true;
        });
        state.collection.favoriteCopyIds = (state.collection.favoriteCopyIds || []).filter(function (id) {
            return !remove[String(id || '')];
        });
        Object.keys(state.collection.workAssignments || {}).forEach(function (id) {
            if (remove[String(id || '')]) { delete state.collection.workAssignments[id]; }
        });
        config.removeCopiesFromCombatTeams(remove);
        config.cleanCombatTeamsAgainstCollection(true);
        config.saveCollection();
        config.renderSummary();
        config.renderCollectionTable();
        config.renderCombatSetup();
        return count;
    }

    function destroyDailyBossDefeatedCards(clearAttempt) {
        var state = config.getState();
        var bossState = state.dailyBoss || loadDailyBossState();
        var risked = {};
        var defeated;
        var count;
        if (!bossState || !bossState.activeAttempt) { return 0; }
        (bossState.activeAttempt.riskedCopyIds || []).forEach(function (id) {
            id = String(id || '');
            if (id) { risked[id] = true; }
        });
        defeated = (bossState.activeAttempt.defeatedCopyIds || []).filter(function (id, index, list) {
            id = String(id || '');
            return !!id && !!risked[id] && list.indexOf(id) === index;
        });
        count = destroyDailyBossCopies(defeated);
        if (clearAttempt !== false) {
            bossState.activeAttempt = null;
            saveDailyBossState();
        }
        return count;
    }

    function finishDailyBossAttempt(completed) {
        var state = config.getState();
        var bossState = state.dailyBoss || loadDailyBossState();
        if (!bossState) { return; }
        bossState.activeAttempt = null;
        bossState.completed = !!completed || bossState.completed;
        saveDailyBossState();
        renderDailyBossSummary();
    }

    function interruptDailyBossCombat(showMessage) {
        var state = config.getState();
        var lost;
        if (!state.combat || state.combat.mode !== 'daily-boss' || state.combat.over) { return 0; }
        lost = destroyDailyBossDefeatedCards(true);
        state.combat.over = true;
        state.combat = null;
        if (showMessage && lost > 0) {
            config.setCombatMessage(config.uiText('combat.daily_interrupted', 'Intento del Jefe diario interrumpido. Cartas derrotadas perdidas: {lost}.', { lost: lost }));
        }
        config.renderCombatBattle();
        config.renderCombatSetup();
        return lost;
    }

    function recoverAbandonedDailyBossAttempt() {
        var state = config.getState();
        var bossState;
        var lost;
        if (state.combat) { return; }
        bossState = loadDailyBossState();
        if (!bossState || !bossState.activeAttempt) { return; }
        lost = destroyDailyBossDefeatedCards(true);
        if (lost > 0) {
            config.setStatus(config.uiText('combat.daily_previous_closed', 'Intento anterior del Jefe diario cerrado. Cartas derrotadas perdidas: {lost}.', { lost: lost }));
        }
    }

    function dailyBossRewardRarity() {
        var rarity = String((config.dailyBossCardReward() && config.dailyBossCardReward().rarity) || 'stigmatic');
        return config.rarityOrder().indexOf(rarity) !== -1 ? rarity : 'stigmatic';
    }

    function rewardDropLabel(item) {
        var material = (config.upgradeMaterials() || {})[item.key];
        return material && material.label ? material.label : item.key;
    }

    function pickGuaranteedRewardDrop(entries) {
        var roll = Math.random();
        var acc = 0;
        var fallback = null;
        var i;
        for (i = 0; i < entries.length; i++) {
            var entry = entries[i];
            if (!entry || !entry.key) { continue; }
            fallback = fallback || entry;
            acc += Math.max(0, Number(entry.chance) || 0);
            if (roll <= acc) {
                return entry;
            }
        }
        return fallback;
    }

    function pushLootMaterialReward(rewards, drop) {
        if (!drop || !drop.key) { return; }
        config.addMaterial(drop.key, drop.amount);
        rewards.materials.push({
            key: drop.key,
            amount: drop.amount,
            label: rewardDropLabel(drop)
        });
    }

    function awardDailyBossLoot() {
        var lootTable = config.dailyBossLootTable() || {};
        var guaranteedDrops = config.normalizeDropConfigList(lootTable.guaranteedMaterialDrop);
        var bonusDrops = config.normalizeDropConfigList(lootTable.bonusDrops);
        var mnemonesRange = config.normalizeRewardRangeConfig(lootTable.mnemones, 500, 1200);
        var remoriasRange = config.normalizeRewardRangeConfig(lootTable.remorias, 120, 420);
        var rewards = {
            mnemones: config.rollStat(mnemonesRange.min, mnemonesRange.max),
            remorias: config.rollStat(remoriasRange.min, remoriasRange.max),
            materials: []
        };
        config.addMnemones(rewards.mnemones);
        config.addRemorias(rewards.remorias);
        pushLootMaterialReward(rewards, pickGuaranteedRewardDrop(guaranteedDrops));
        bonusDrops.forEach(function (drop) {
            if (Math.random() <= drop.chance) {
                pushLootMaterialReward(rewards, drop);
            }
        });
        return rewards;
    }

    function dailyBossLootText(loot) {
        var parts = [];
        if (!loot) { return ''; }
        if (loot.mnemones) { parts.push('+' + config.formatNumber(loot.mnemones) + ' Mnemones'); }
        if (loot.remorias) { parts.push('+' + config.formatNumber(loot.remorias) + ' Remorias'); }
        (loot.materials || []).forEach(function (item) {
            parts.push('+' + item.amount + ' ' + item.label);
        });
        return parts.join(', ');
    }

    function dailyBossRewardSummary(reward) {
        var parts;
        if (!reward || reward.alreadyClaimed || !reward.card || !reward.loot) { return ''; }
        parts = ['Carta: ' + reward.card.card_name];
        if (reward.loot.mnemones) {
            parts.push('Mnemones: +' + config.formatNumber(reward.loot.mnemones));
        }
        if (reward.loot.remorias) {
            parts.push('Remorias: +' + config.formatNumber(reward.loot.remorias));
        }
        if (Array.isArray(reward.loot.materials) && reward.loot.materials.length) {
            parts.push('Objetos: ' + reward.loot.materials.map(function (item) {
                return '+' + item.amount + ' ' + item.label;
            }).join(', '));
        }
        if (reward.casualties) {
            parts.push('Cartas perdidas: ' + config.formatNumber(reward.casualties));
        }
        return parts.join(' · ');
    }

    function destroyDailyBossTeam() {
        var state = config.getState();
        var count;
        if (!state.combat || state.combat.mode !== 'daily-boss') { return 0; }
        count = destroyDailyBossDefeatedCards(false);
        finishDailyBossAttempt(false);
        return count;
    }

    function awardDailyBossVictory() {
        var state = config.getState();
        var bossUnit;
        var baseCard;
        var rewardCopy;
        var casualties;
        var loot;
        if (!state.combat || state.combat.mode !== 'daily-boss') { return null; }
        if (dailyBossRewardClaimedToday()) {
            return { alreadyClaimed: true, card: null, copy: null };
        }
        if (!state.collection) { config.loadCollection(); }
        bossUnit = state.combat.enemy && state.combat.enemy[0];
        if (!bossUnit || !bossUnit.copy) { return null; }
        baseCard = state.catalogById[String(bossUnit.copy.cardId || '')] || bossUnit.card;
        if (!baseCard) { return null; }
        rewardCopy = config.createCardCopy(baseCard, {
            rarity: dailyBossRewardRarity(),
            instanceId: config.instanceId(),
            obtainedAt: config.nowIso()
        });
        casualties = destroyDailyBossDefeatedCards(false);
        loot = awardDailyBossLoot();
        state.collection.ownedCards.push(rewardCopy);
        markDailyBossRewardClaimed(rewardCopy.instanceId);
        finishDailyBossAttempt(true);
        config.saveCollection();
        config.renderSummary();
        config.renderCollectionTable();
        config.renderCombatSetup();
        return {
            alreadyClaimed: false,
            card: config.cardForCopy(baseCard, rewardCopy),
            copy: rewardCopy,
            loot: loot,
            casualties: casualties
        };
    }

    function appendDailyBossResetButton(summary) {
        var reset = global.document.createElement('button');
        reset.type = 'button';
        reset.className = 'hg-daily-boss-summary__reset';
        reset.textContent = config.uiText('combat.daily_reset_button', 'Reset admin');
        reset.addEventListener('click', resetDailyBossState);
        summary.appendChild(reset);
    }

    function renderDailyBossSummary() {
        var state = config.getState();
        var els = config.getEls();
        var combatInProgress = !!(state.combat && !state.combat.over);
        var active = state.combatMode === 'daily-boss' && !combatInProgress;
        var bossState = active ? (state.dailyBoss || loadDailyBossState()) : null;
        (els.dailyBossSummaries || []).forEach(function (summary) {
            var entry;
            var card;
            var hpPercent;
            var defeated;
            summary.hidden = !active;
            summary.classList.remove('is-completed', 'is-unavailable');
            summary.innerHTML = '';
            if (!active) { return; }
            if (!bossState) {
                summary.classList.add('is-unavailable');
                summary.innerHTML = '<strong>' + config.escapeHtml(config.uiText('combat.daily_unavailable_title', 'Jefe diario no disponible')) + '</strong><span>' + config.escapeHtml(config.uiText('combat.daily_unavailable_text', 'No hay carta válida para generar el desafío.')) + '</span>';
                return;
            }
            if (bossState.completed || bossState.hp <= 0 || dailyBossRewardClaimedToday()) {
                summary.classList.add('is-completed');
                summary.innerHTML = '<strong>' + config.escapeHtml(config.uiText('combat.daily_completed_title', 'Desafío diario completado')) + '</strong><span>' + config.escapeHtml(config.uiText('combat.daily_completed_text', 'Vuelve mañana para otro Jefe diario.')) + '</span>';
                if (state.isAdmin) { appendDailyBossResetButton(summary); }
                return;
            }
            entry = dailyBossEntryFromState();
            card = entry ? entry.card : null;
            hpPercent = bossState.maxHp > 0 ? Math.max(0, Math.min(100, (bossState.hp / bossState.maxHp) * 100)) : 0;
            defeated = bossState.activeAttempt && bossState.activeAttempt.defeatedCopyIds
                ? bossState.activeAttempt.defeatedCopyIds.length
                : 0;
            summary.innerHTML =
                '<div class="hg-daily-boss-summary__media">' +
                    '<img src="' + config.escapeHtml(card && card.card_image_url || '/img/og/og_image.webp') + '" alt="">' +
                '</div>' +
                '<div class="hg-daily-boss-summary__body">' +
                    '<strong>' + config.escapeHtml(card ? card.card_name : bossState.cardName) + '</strong>' +
                    '<span>Desafia a este rival mejorado con energias estigmaticas.</span>' +
                    '<div class="hg-daily-boss-summary__hp"><i><b style="width:' + hpPercent.toFixed(2) + '%"></b></i><em>PS ' + config.formatNumber(bossState.hp) + ' / ' + config.formatNumber(bossState.maxHp) + '</em></div>' +
                    '<small>Intentos: ' + config.formatNumber(bossState.attempts) + ' · ATQ ' + config.formatNumber(bossState.atk) + ' · DEF ' + config.formatNumber(bossState.def) + (defeated ? ' · Caídas pendientes: ' + defeated : '') + '</small>' +
                '</div>';
            if (state.isAdmin) { appendDailyBossResetButton(summary); }
        });
    }

    function createDailyBoss() {
        return dailyBossEntryFromState();
    }

    function startDailyBossCombat(options) {
        var state = config.getState();
        var bossState;
        var teamIds;
        var playerUnits;
        var boss;
        options = options || {};
        if (!config.isCombatContext()) { return false; }
        config.preloadCombatSounds();
        config.cleanCombatTeamsAgainstCollection(true);
        bossState = state.dailyBoss || loadDailyBossState();
        if (!bossState) {
            config.setCombatMessage(config.uiText('combat.no_daily_characters', 'No hay personajes disponibles para generar el jefe diario.'));
            return false;
        }
        if (bossState.completed || bossState.hp <= 0 || dailyBossRewardClaimedToday()) {
            config.setCombatMessage(config.uiText('combat.daily_completed', 'Desafío diario completado.'));
            renderDailyBossSummary();
            return false;
        }
        teamIds = config.validDraftTeam();
        if (teamIds.length !== 5) {
            return config.promptQuickCombatTeam();
        }
        if (!options.confirmed) {
            return config.confirmGameAction(
                'El Jefe diario conserva sus PS, no permite huir y destruye las cartas que derrota. Si cae todo tu equipo, pierdes las 5 cartas del intento. ¿Entrar igualmente?',
                { title: 'Jefe diario', confirmLabel: 'Entrar al desafio', cancelLabel: 'Cancelar' },
                function () {
                    startDailyBossCombat({ confirmed: true });
                }
            );
        }
        playerUnits = teamIds.map(function (id, index) {
            var entry = config.combatEntryFromCopy(config.copyByInstanceId(id));
            return entry ? config.createCombatUnit(entry.card, entry.copy, 'player', index) : null;
        }).filter(Boolean);
        if (playerUnits.length !== 5) {
            config.setCombatMessage(config.uiText('combat.team_missing_card', 'Alguna carta del equipo ya no existe en la colección.'));
            return false;
        }
        boss = createDailyBoss();
        if (!boss) {
            config.setCombatMessage(config.uiText('combat.no_daily_characters', 'No hay personajes disponibles para generar el jefe diario.'));
            return false;
        }
        startDailyBossAttempt(teamIds);
        state.combat = {
            mode: 'daily-boss',
            difficultyLabel: 'Jefe diario',
            rewardMultiplier: 0,
            player: playerUnits,
            enemy: [config.createCombatUnit(boss.card, boss.copy, 'enemy', 0, {
                currentHp: boss.bossState.hp,
                maxHp: boss.bossState.maxHp,
                noShields: true
            })],
            playerActive: 0,
            enemyActive: 0,
            over: false,
            result: '',
            reward: 0,
            riskedCopyIds: teamIds.slice(),
            log: []
        };
        config.pushCombatLog(config.uiText('combat.daily_boss_log', 'Jefe diario: {card} emerge como Estigmático.', { card: boss.card.card_name }));
        config.pushCombatLog(config.uiText('combat.daily_risk_log', 'Solo las cartas derrotadas durante este desafío se pierden.'));
        config.pushCombatLog(config.combatPlayerName() + ' saca una carta.');
        config.setCombatMessage(config.uiText('combat.daily_started', 'Jefe diario iniciado. Alto riesgo.'));
        config.showCombatScreen('battle');
        config.renderCombatBattle();
        config.animateCombatEntry('player');
        config.animateCombatEntry('enemy');
        return true;
    }

    var api = Object.freeze({
        configure: configure,
        dailyBossRewardState: dailyBossRewardState,
        dailyBossRewardClaimedToday: dailyBossRewardClaimedToday,
        markDailyBossRewardClaimed: markDailyBossRewardClaimed,
        dailyBossActiveAttempt: dailyBossActiveAttempt,
        pickDailyBossCard: pickDailyBossCard,
        createDailyBossState: createDailyBossState,
        normalizeDailyBossState: normalizeDailyBossState,
        loadDailyBossState: loadDailyBossState,
        saveDailyBossState: saveDailyBossState,
        resetDailyBossState: resetDailyBossState,
        dailyBossEntryFromState: dailyBossEntryFromState,
        updateDailyBossHp: updateDailyBossHp,
        startDailyBossAttempt: startDailyBossAttempt,
        markDailyBossCopyDefeated: markDailyBossCopyDefeated,
        destroyDailyBossCopies: destroyDailyBossCopies,
        destroyDailyBossDefeatedCards: destroyDailyBossDefeatedCards,
        finishDailyBossAttempt: finishDailyBossAttempt,
        interruptDailyBossCombat: interruptDailyBossCombat,
        recoverAbandonedDailyBossAttempt: recoverAbandonedDailyBossAttempt,
        dailyBossRewardRarity: dailyBossRewardRarity,
        awardDailyBossLoot: awardDailyBossLoot,
        dailyBossLootText: dailyBossLootText,
        dailyBossRewardSummary: dailyBossRewardSummary,
        createDailyBoss: createDailyBoss,
        destroyDailyBossTeam: destroyDailyBossTeam,
        awardDailyBossVictory: awardDailyBossVictory,
        appendDailyBossResetButton: appendDailyBossResetButton,
        renderDailyBossSummary: renderDailyBossSummary,
        startDailyBossCombat: startDailyBossCombat
    });

    if (app.combat.modes && typeof app.combat.modes.register === 'function') {
        app.combat.modes.register('daily-boss', api, {
            featureFlag: 'dailyBoss',
            startMethod: 'startDailyBossCombat',
            selectedMessage: 'Jefe diario: si tu equipo cae, pierdes esas 5 cartas.'
        });
    }
})(window);
