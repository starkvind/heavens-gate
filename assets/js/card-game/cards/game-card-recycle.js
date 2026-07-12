(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.cards = app.cards || {};

    var config = {
        getState: function () { return null; },
        getEls: function () { return {}; },
        loadCollection: function () {},
        copyRarity: function () { return 'common'; },
        sortedCopies: function (copies) { return copies || []; },
        isCopyWorking: function () { return false; },
        removeCopiesFromCombatTeams: function () { return 0; },
        addRemorias: function () { return 0; },
        saveCollection: function () {},
        playDustSound: function () {},
        renderSummary: function () {},
        renderPackInventory: function () {},
        renderCollectionTable: function () {},
        renderCombatSetup: function () {},
        showCardModal: function () {},
        closeCardModal: function () {},
        setStatus: function () {},
        uiText: function (key, fallback, values) {
            var text = fallback || key;
            return values ? text.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
                return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
            }) : text;
        },
        confirmGameAction: function () { return false; },
        escapeHtml: function (value) { return String(value || ''); },
        rarityLabels: function () { return {}; },
        rarityOrder: function () { return []; },
        recycleValues: function () { return {}; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function recycleValue(card, copy) {
        var values = config.recycleValues() || {};
        return values[config.copyRarity(copy, card)] || values.common;
    }

    function ownedCopiesForCard(cardId) {
        var state = config.getState();
        if (!state.collection) { config.loadCollection(); }
        return (state.collection.ownedCards || []).filter(function (copy) {
            return String(copy.cardId) === String(cardId);
        });
    }

    function favoriteCopyMap() {
        var state = config.getState();
        var map = {};
        if (!state.collection) { config.loadCollection(); }
        if (!Array.isArray(state.collection.favoriteCopyIds)) { state.collection.favoriteCopyIds = []; }
        state.collection.favoriteCopyIds.forEach(function (id) {
            id = String(id || '');
            if (id) { map[id] = true; }
        });
        return map;
    }

    function isFavoriteCopy(copyOrId) {
        var copyId = typeof copyOrId === 'object' && copyOrId ? copyOrId.instanceId : copyOrId;
        return !!copyId && !!favoriteCopyMap()[String(copyId)];
    }

    function toggleFavoriteCopy(copy) {
        var state = config.getState();
        var copyId;
        var owned;
        var map;
        if (!copy || !copy.instanceId) { return false; }
        if (!state.collection) { config.loadCollection(); }
        copyId = String(copy.instanceId || '');
        owned = (state.collection.ownedCards || []).some(function (item) {
            return String(item.instanceId || '') === copyId;
        });
        if (!owned) {
            config.setStatus(config.uiText('recycle.favorite_only_owned', 'Solo puedes marcar como favorita una copia que tengas.'));
            return false;
        }
        map = favoriteCopyMap();
        if (map[copyId]) {
            state.collection.favoriteCopyIds = state.collection.favoriteCopyIds.filter(function (id) {
                return String(id || '') !== copyId;
            });
        } else {
            state.collection.favoriteCopyIds.push(copyId);
        }
        config.saveCollection();
        config.renderCollectionTable();
        renderBulkSellPreview();
        config.setStatus(map[copyId] ? config.uiText('recycle.favorite_removed', 'Copia retirada de favoritas.') : config.uiText('recycle.favorite_added', 'Copia marcada como favorita.'));
        return true;
    }

    function recycleCopy(card, copy, confirmed) {
        var state = config.getState();
        var rarity;
        var remove = {};
        var removedFromTeams;
        var gained;
        var remaining;
        if (!copy || !copy.instanceId) { return false; }
        if (isFavoriteCopy(copy)) {
            config.setStatus(config.uiText('recycle.favorite_blocked', 'Esta copia es favorita y no se puede vender.'));
            return false;
        }
        if (config.isCopyWorking(copy.instanceId)) {
            config.setStatus(config.uiText('recycle.remove_memory_first', 'Retira la carta de la rememoración antes de venderla.'));
            return false;
        }
        rarity = config.copyRarity(copy, card);
        if ((rarity === 'legendary' || rarity === 'mythic' || rarity === 'stigmatic') && !confirmed) {
            return config.confirmGameAction(
                config.uiText('recycle.confirm_single', 'Vas a desintegrar una copia {rarity}.', { rarity: ((config.rarityLabels() || {})[rarity] || rarity).toLowerCase() }),
                { title: config.uiText('recycle.single_title', 'Desintegrar carta'), confirmLabel: config.uiText('recycle.confirm', 'Desintegrar') },
                function () { recycleCopy(card, copy, true); }
            );
        }
        remove[String(copy.instanceId)] = true;
        removedFromTeams = config.removeCopiesFromCombatTeams(remove);
        state.collection.ownedCards = state.collection.ownedCards.filter(function (item) {
            return String(item.instanceId) !== String(copy.instanceId);
        });
        gained = recycleValue(card, copy);
        config.addRemorias(gained);
        config.saveCollection();
        config.playDustSound();
        config.renderSummary();
        config.renderPackInventory();
        config.renderCollectionTable();
        config.renderCombatSetup();
        remaining = ownedCopiesForCard(card.card_id);
        if (remaining.length) {
            config.showCardModal(card, remaining);
        } else {
            config.closeCardModal();
        }
        config.setStatus(config.uiText('recycle.single_done', 'Copia desintegrada. +{gained} Remorias.{extra}', { gained: gained, extra: removedFromTeams ? ' Retirada de ' + removedFromTeams + ' hueco(s) de equipo.' : '' }));
        return true;
    }

    function recycleDuplicateCopies(card, confirmed) {
        var state = config.getState();
        var copies = config.sortedCopies(ownedCopiesForCard(card.card_id), card);
        var recycled;
        var gained;
        var remove = {};
        var removedFromTeams;
        if (copies.length <= 1) {
            config.setStatus(config.uiText('recycle.no_duplicates', 'No hay duplicadas que desintegrar.'));
            return false;
        }
        recycled = copies.slice(1).filter(function (copy) { return !isFavoriteCopy(copy); });
        if (!recycled.length) {
            config.setStatus(config.uiText('recycle.no_sellable_duplicates', 'No hay duplicadas vendibles: las duplicadas son favoritas.'));
            return false;
        }
        if (recycled.some(function (copy) { return config.isCopyWorking(copy.instanceId); })) {
            config.setStatus(config.uiText('recycle.duplicates_memory_blocked', 'Retira primero las duplicadas que están rememorando.'));
            return false;
        }
        gained = recycled.reduce(function (sum, item) { return sum + recycleValue(card, item); }, 0);
        if (!confirmed) {
            return config.confirmGameAction(
                'Se conservara la mejor copia y se desintegraran ' + recycled.length + ' duplicadas por ' + gained + ' Remorias.',
                { title: config.uiText('recycle.duplicates_title', 'Desintegrar duplicadas'), confirmLabel: config.uiText('recycle.confirm', 'Desintegrar') },
                function () { recycleDuplicateCopies(card, true); }
            );
        }
        recycled.forEach(function (copy) {
            remove[String(copy.instanceId)] = true;
        });
        removedFromTeams = config.removeCopiesFromCombatTeams(remove);
        state.collection.ownedCards = state.collection.ownedCards.filter(function (item) {
            return !remove[String(item.instanceId)];
        });
        config.addRemorias(gained);
        config.saveCollection();
        config.playDustSound();
        config.renderSummary();
        config.renderPackInventory();
        config.renderCollectionTable();
        config.renderCombatSetup();
        config.showCardModal(card, ownedCopiesForCard(card.card_id));
        config.setStatus(config.uiText('recycle.duplicates_done', 'Duplicadas desintegradas. +{gained} Remorias.{extra}', { gained: gained, extra: removedFromTeams ? ' Retiradas de ' + removedFromTeams + ' hueco(s) de equipo.' : '' }));
        return true;
    }

    function recycleAllCopies(card, confirmed) {
        var state = config.getState();
        var copies = config.sortedCopies(ownedCopiesForCard(card.card_id), card);
        var sellable = copies.filter(function (copy) { return !isFavoriteCopy(copy); });
        var gained;
        var remove = {};
        var removedFromTeams;
        var remaining;
        if (!copies.length) {
            config.setStatus(config.uiText('recycle.no_copies', 'No hay copias que desintegrar.'));
            return false;
        }
        if (!sellable.length) {
            config.setStatus(config.uiText('recycle.all_favorites', 'Todas las copias son favoritas.'));
            return false;
        }
        if (sellable.some(function (copy) { return config.isCopyWorking(copy.instanceId); })) {
            config.setStatus(config.uiText('recycle.all_memory_blocked', 'Retira primero las cartas que están rememorando.'));
            return false;
        }
        gained = sellable.reduce(function (sum, copy) { return sum + recycleValue(card, copy); }, 0);
        if (!confirmed) {
            return config.confirmGameAction(
                'Se desintegraran ' + sellable.length + ' copias no favoritas de esta carta por ' + gained + ' Remorias.',
                { title: config.uiText('recycle.all_title', 'Desintegrar todas'), confirmLabel: config.uiText('recycle.confirm', 'Desintegrar') },
                function () { recycleAllCopies(card, true); }
            );
        }
        sellable.forEach(function (copy) {
            remove[String(copy.instanceId)] = true;
        });
        removedFromTeams = config.removeCopiesFromCombatTeams(remove);
        state.collection.ownedCards = state.collection.ownedCards.filter(function (item) {
            return !remove[String(item.instanceId)];
        });
        config.addRemorias(gained);
        config.saveCollection();
        config.playDustSound();
        config.renderSummary();
        config.renderPackInventory();
        config.renderCollectionTable();
        config.renderCombatSetup();
        remaining = ownedCopiesForCard(card.card_id);
        if (remaining.length) {
            config.showCardModal(card, remaining);
        } else {
            config.closeCardModal();
        }
        config.setStatus(config.uiText('recycle.all_done', 'Copias no favoritas desintegradas. +{gained} Remorias.{extra}', { gained: gained, extra: removedFromTeams ? ' Retirada de ' + removedFromTeams + ' hueco(s) de equipo.' : '' }));
        return true;
    }

    function bulkSellStats(rarity, keepBest) {
        var state = config.getState();
        var byCard = {};
        var remove = {};
        var count = 0;
        var gained = 0;
        var kept = 0;
        var protectedCount = 0;
        if (!state.collection) { config.loadCollection(); }
        keepBest = !!keepBest;
        (state.collection.ownedCards || []).forEach(function (copy) {
            var card = state.catalogById[String(copy.cardId || '')];
            var cardId;
            if (!card || config.copyRarity(copy, card) !== rarity) { return; }
            if (isFavoriteCopy(copy)) {
                protectedCount += 1;
                return;
            }
            if (config.isCopyWorking(copy.instanceId)) { return; }
            cardId = String(card.card_id);
            if (!byCard[cardId]) { byCard[cardId] = { card: card, copies: [] }; }
            byCard[cardId].copies.push(copy);
        });
        Object.keys(byCard).forEach(function (cardId) {
            var entry = byCard[cardId];
            var copies = config.sortedCopies(entry.copies, entry.card);
            var toSell = keepBest ? copies.slice(1) : copies;
            if (keepBest && copies.length) { kept += 1; }
            toSell.forEach(function (copy) {
                remove[String(copy.instanceId)] = true;
                count += 1;
                gained += recycleValue(entry.card, copy);
            });
        });
        return { count: count, gained: gained, remove: remove, kept: kept, keepBest: keepBest, protectedCount: protectedCount };
    }

    function renderBulkSellPreview() {
        var els = config.getEls() || {};
        var rarity;
        var keepBest;
        var stats;
        var label;
        var previewParts;
        if (!els.bulkSellRarity || !els.bulkSellBtn || !els.bulkSellPreview) { return; }
        rarity = els.bulkSellRarity.value || 'common';
        keepBest = !els.bulkSellKeepBest || els.bulkSellKeepBest.checked;
        stats = bulkSellStats(rarity, keepBest);
        label = (config.rarityLabels() || {})[rarity] || rarity;
        previewParts = [
            '<span>' + stats.count + ' cartas ' + config.escapeHtml(label.toLowerCase()) + '</span>',
            '<span>+' + stats.gained + ' Remorias</span>'
        ];
        if (stats.keepBest && stats.kept) {
            previewParts.push('<span>conserva ' + stats.kept + ' mejores</span>');
        }
        if (stats.protectedCount) {
            previewParts.push('<span>' + stats.protectedCount + ' favoritas protegidas</span>');
        }
        els.bulkSellPreview.innerHTML = previewParts.join('');
        els.bulkSellBtn.disabled = stats.count <= 0;
    }

    function sellCardsByRarity(confirmed) {
        var state = config.getState();
        var els = config.getEls() || {};
        var rarity;
        var keepBest;
        var stats;
        var label;
        var keepText;
        var removedFromTeams;
        if (!els.bulkSellRarity) { return false; }
        if (!state.catalog.length) {
            config.setStatus(config.uiText('recycle.wait_catalog', 'Espera a que cargue el catálogo.'));
            return false;
        }
        rarity = els.bulkSellRarity.value || 'common';
        if ((config.rarityOrder() || []).indexOf(rarity) === -1) {
            config.setStatus(config.uiText('recycle.invalid_rarity', 'Rareza no válida.'));
            return false;
        }
        keepBest = !els.bulkSellKeepBest || els.bulkSellKeepBest.checked;
        stats = bulkSellStats(rarity, keepBest);
        if (stats.count <= 0) {
            config.setStatus(stats.protectedCount ? 'Las cartas favoritas de esa rareza estan protegidas.' : (stats.keepBest ? 'No tienes duplicadas de esa rareza para vender.' : 'No tienes cartas de esa rareza para vender.'));
            renderBulkSellPreview();
            return false;
        }
        label = (config.rarityLabels() || {})[rarity] || rarity;
        keepText = stats.keepBest ? ' Se conservará la copia con mayor PS + ATQ + DEF de cada carta.' : '';
        if (!confirmed) {
            return config.confirmGameAction(
                'Vas a vender ' + stats.count + ' cartas de rareza ' + label.toLowerCase() + ' por ' + stats.gained + ' Remorias.' + keepText + ' Esta accion no se puede deshacer.',
                { title: 'Vender cartas', confirmLabel: 'Vender' },
                function () { sellCardsByRarity(true); }
            );
        }
        removedFromTeams = config.removeCopiesFromCombatTeams(stats.remove);
        state.collection.ownedCards = state.collection.ownedCards.filter(function (copy) {
            return !stats.remove[String(copy.instanceId)];
        });
        config.addRemorias(stats.gained);
        config.saveCollection();
        config.playDustSound();
        config.closeCardModal();
        config.renderSummary();
        config.renderPackInventory();
        config.renderCollectionTable();
        config.renderCombatSetup();
        config.setStatus(config.uiText('recycle.sale_done', 'Venta completada. +{gained} Remorias.{extra}', { gained: stats.gained, extra: removedFromTeams ? ' Retiradas de ' + removedFromTeams + ' hueco(s) de equipo.' : '' }));
        return true;
    }

    var api = Object.freeze({
        configure: configure,
        recycleValue: recycleValue,
        ownedCopiesForCard: ownedCopiesForCard,
        favoriteCopyMap: favoriteCopyMap,
        isFavoriteCopy: isFavoriteCopy,
        toggleFavoriteCopy: toggleFavoriteCopy,
        recycleCopy: recycleCopy,
        recycleDuplicateCopies: recycleDuplicateCopies,
        recycleAllCopies: recycleAllCopies,
        bulkSellStats: bulkSellStats,
        renderBulkSellPreview: renderBulkSellPreview,
        sellCardsByRarity: sellCardsByRarity
    });

    app.cards.recycle = api;
})(window);
