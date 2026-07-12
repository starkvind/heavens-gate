(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.packs = app.packs || {};

    function bindRootClick(root, events, selector, handler) {
        if (events && typeof events.onRootClick === 'function') {
            events.onRootClick(root, selector, handler);
            return;
        }
        document.addEventListener('click', function (event) {
            var target = event.target && event.target.closest ? event.target.closest(selector) : null;
            if (!target || !root || !root.contains(target)) { return; }
            handler(event, target);
        });
    }

    function bindControls(options) {
        options = options || {};
        var root = options.root;
        if (!root) { return false; }

        bindRootClick(root, options.events, '[data-pack-kind]', function (event, packButton) {
            if (typeof options.openPack !== 'function') { return; }
            event.preventDefault();
            options.openPack(packButton.getAttribute('data-pack-kind') || 'standard');
        });

        bindRootClick(root, options.events, '[data-pack-open-all]', function (event) {
            if (typeof options.openAllPacks !== 'function') { return; }
            event.preventDefault();
            options.openAllPacks();
        });

        if (app.bootstrap && typeof app.bootstrap.track === 'function') {
            app.bootstrap.track('packs/controls');
        }
        return true;
    }

    function createDomain(ctx) {
        function defer(callback, delay) {
            if (typeof requestIdleCallback === 'function') {
                requestIdleCallback(callback, { timeout: delay || 900 });
                return;
            }
            window.setTimeout(function () {
                var schedule = window.requestAnimationFrame || function (fn) { return window.setTimeout(fn, 0); };
                schedule(callback);
            }, delay || 900);
        }

        function packStock(packKind) {
            if (ctx.state.isAdmin) { return Infinity; }
            if (!ctx.state.collection) { ctx.loadCollection(); }
            return Math.max(0, ctx.clampInt((ctx.state.collection.packInventory || {})[packKind], 0));
        }

        function packSpace(packKind) {
            if (ctx.state.isAdmin) { return Infinity; }
            return Math.max(0, ctx.MAX_PACK_STOCK - packStock(packKind));
        }

        function canOpenPack(packKind) {
            return packStock(packKind) > 0;
        }

        function consumePack(packKind) {
            if (ctx.state.isAdmin) { return; }
            if (!ctx.state.collection) { ctx.loadCollection(); }
            ctx.state.collection.packInventory = ctx.normalizePackInventory(ctx.state.collection.packInventory);
            ctx.state.collection.packInventory[packKind] = Math.max(0, ctx.clampInt(ctx.state.collection.packInventory[packKind], 0) - 1);
        }

        function addPack(packKind, amount, options) {
            options = options || {};
            if (ctx.PACK_KINDS.indexOf(packKind) === -1) { return false; }
            if (!ctx.state.collection) { ctx.loadCollection(); }
            ctx.state.collection.packInventory = ctx.normalizePackInventory(ctx.state.collection.packInventory);
            ctx.state.collection.packInventory[packKind] = Math.max(0, Math.min(ctx.MAX_PACK_STOCK, ctx.state.collection.packInventory[packKind] + Math.max(1, ctx.clampInt(amount, 1))));
            if (!options.deferSave) { ctx.saveCollection(); }
            if (!options.silent) {
                ctx.renderSummary({ light: true });
                renderPackInventory();
            }
            return true;
        }

        function totalPackStock() {
            if (ctx.state.isAdmin) { return Infinity; }
            if (!ctx.state.collection) { ctx.loadCollection(); }
            ctx.state.collection.packInventory = ctx.normalizePackInventory(ctx.state.collection.packInventory);
            return ctx.PACK_KINDS.reduce(function (sum, kind) {
                return sum + Math.max(0, ctx.clampInt(ctx.state.collection.packInventory[kind], 0));
            }, 0);
        }

        function renderPackEmptyState(totalStock) {
            if (!ctx.els.packGrid) { return; }
            var empty = ctx.els.packGrid.querySelector('[data-pack-empty-state]');
            if (!empty) {
                empty = document.createElement('p');
                empty.className = 'hg-empty-state hg-pack-empty-state';
                empty.setAttribute('data-pack-empty-state', '1');
                empty.textContent = ctx.uiText('packs.empty', 'No te quedan sobres. Puedes comprar más en la tienda o probar suerte en las Mazmorras.');
                ctx.els.packGrid.appendChild(empty);
            }
            empty.hidden = ctx.state.isAdmin || totalStock > 0;
            if (ctx.els.packOpenAll) {
                ctx.els.packOpenAll.hidden = ctx.state.isAdmin || totalStock <= 0;
                ctx.els.packOpenAll.disabled = ctx.state.isAdmin || totalStock <= 0;
            }
        }

        function renderPackInventory() {
            ctx.ensurePackLayout();
            var totalStock = totalPackStock();
            ctx.els.packStocks.forEach(function (node) {
                var kind = node.getAttribute('data-pack-stock') || 'standard';
                var stock = packStock(kind);
                if (ctx.state.isAdmin) {
                    node.textContent = ctx.uiText('shop.admin', 'Admin');
                } else if (kind === 'standard') {
                    node.textContent = 'x' + stock;
                    node.title = ctx.uiText('packs.available_title', 'Sobres mnemónicos disponibles.');
                } else {
                    node.textContent = 'x' + stock;
                }
            });
            ctx.els.packButtons.forEach(function (button) {
                var kind = button.getAttribute('data-pack-kind') || 'standard';
                ctx.applyPackSkin(button, kind);
                var stock = packStock(kind);
                var visible = ctx.state.isAdmin || stock > 0;
                var available = canOpenPack(kind);
                button.hidden = !visible;
                button.disabled = !available;
                button.classList.toggle('is-empty', !available);
                button.classList.toggle('is-hidden', !visible);
                button.setAttribute('aria-disabled', available ? 'false' : 'true');
            });
            renderPackEmptyState(totalStock);
            ctx.renderShop();
        }

        function openPack(packKind, options) {
            options = options || {};
            packKind = packKind || 'standard';
            if (!ctx.state.catalog.length) {
                ctx.setStatus(ctx.uiText('status.no_cards_for_packs', 'No hay cartas disponibles para abrir sobres.'));
                return [];
            }
            if (packKind === 'standard' && packStock('standard') <= 0) {
                ctx.setStatus(ctx.uiText('status.no_standard_packs', 'No tienes sobres mnemónicos disponibles. Compra unidades desde la tienda.'));
                return [];
            }
            if (packKind !== 'standard' && packStock(packKind) <= 0) {
                ctx.setStatus(ctx.uiText('status.no_pack_units', 'No tienes unidades de {pack}.', { pack: ctx.packLabel(packKind).toLowerCase() }));
                return [];
            }
            if (!ctx.state.collection) { ctx.loadCollection(); }

            var hasPackCards = ctx.state.catalog.some(function (card) { return ctx.cardAllowedForPack(card, packKind); });
            if (!hasPackCards) {
                ctx.setStatus(ctx.uiText('status.no_cards_for_pack_type', 'No hay cartas disponibles para este tipo de sobre.'));
                return [];
            }
            var obtained = [];
            var usedCardIds = {};
            for (var i = 0; i < ctx.PACK_SIZE; i++) {
                var card = ctx.pickCardByRarity(ctx.pickRarity(packKind), packKind, usedCardIds);
                if (!card) { continue; }
                usedCardIds[String(card.card_id)] = true;
                var copy = ctx.createCardCopy(card);
                ctx.state.collection.ownedCards.push(copy);
                obtained.push({ catalog: card, instance: copy });
            }

            consumePack(packKind);

            if (!options.deferSave) { ctx.saveCollection(); }
            if (!options.silent) {
                if (obtained.length) { ctx.playPackOpenSound(); }
                ctx.renderSummary();
                renderPackInventory();
                if (ctx.state.view === 'gacha') {
                    ctx.showPackReveal(obtained, packKind);
                    defer(function () { ctx.renderPackResults(obtained); }, 900);
                } else {
                    ctx.renderPackResults(obtained);
                }
                ctx.setStatus(ctx.uiText('status.pack_opened', '{pack}: {count} cartas obtenidas.', { pack: ctx.packLabel(packKind), count: obtained.length }));
            }
            return obtained;
        }

        function openAllPacks() {
            if (!ctx.state.catalog.length) {
                ctx.setStatus(ctx.uiText('status.no_cards_for_packs', 'No hay cartas disponibles para abrir sobres.'));
                return [];
            }
            if (!ctx.state.collection) { ctx.loadCollection(); }
            var opened = 0;
            var obtained = [];
            ctx.PACK_KINDS.forEach(function (kind) {
                if (!ctx.state.isAdmin) {
                    while (packStock(kind) > 0) {
                        obtained = obtained.concat(openPack(kind, { silent: true, deferSave: true }));
                        opened += 1;
                    }
                }
            });
            if (!opened) {
                ctx.setStatus(ctx.uiText('status.no_packs_left_open_all', 'No te quedan sobres por abrir.'));
                renderPackInventory();
                return [];
            }
            if (obtained.length) { ctx.playPackOpenSound(); }
            ctx.saveCollection();
            ctx.renderPackResults(obtained.slice(-5));
            ctx.renderSummary();
            renderPackInventory();
            ctx.setStatus(ctx.uiText('status.open_all_done', 'Sobres abiertos: {opened}. Mostrando las últimas 5 cartas obtenidas.', { opened: opened }));
            return obtained;
        }

        return Object.freeze({
            packStock: packStock,
            packSpace: packSpace,
            canOpenPack: canOpenPack,
            consumePack: consumePack,
            addPack: addPack,
            totalPackStock: totalPackStock,
            renderPackInventory: renderPackInventory,
            openPack: openPack,
            openAllPacks: openAllPacks
        });
    }

    app.packs.controls = Object.freeze({
        bind: bindControls
    });
    app.packs.domain = Object.freeze({
        create: createDomain
    });
})(window);
