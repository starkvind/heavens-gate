(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.shop = app.shop || {};

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

        bindRootClick(root, options.events, '[data-shop-buy-pack]', function (event, button) {
            if (typeof options.buyPack !== 'function') { return; }
            event.preventDefault();
            options.buyPack(button.getAttribute('data-shop-buy-pack') || 'standard', button.getAttribute('data-shop-buy-amount') || 1, {
                free: button.getAttribute('data-shop-buy-free') === '1'
            });
        });

        bindRootClick(root, options.events, '[data-shop-buy-material]', function (event, button) {
            if (typeof options.buyMaterial !== 'function') { return; }
            event.preventDefault();
            options.buyMaterial(button.getAttribute('data-shop-buy-material') || '', button.getAttribute('data-shop-buy-amount') || 1);
        });

        bindRootClick(root, options.events, '[data-shop-buy-exchange-remorias]', function (event, button) {
            if (typeof options.buyRemoriaExchange !== 'function') { return; }
            event.preventDefault();
            options.buyRemoriaExchange(button.getAttribute('data-shop-buy-exchange-remorias') || 0);
        });

        bindRootClick(root, options.events, '[data-shop-claim-daily-gift]', function (event, button) {
            if (typeof options.claimShopDailyGift !== 'function') { return; }
            event.preventDefault();
            options.claimShopDailyGift(button.getAttribute('data-shop-claim-daily-gift') || '');
        });

        if (app.bootstrap && typeof app.bootstrap.track === 'function') {
            app.bootstrap.track('shop/controls');
        }
        return true;
    }

    function startStateTimer(options) {
        options = options || {};
        var state = options.state;
        if (!state || state.rewardsTimer) { return false; }
        state.rewardsTimer = global.setInterval(function () {
            if (typeof options.syncShopState === 'function') { options.syncShopState(); }
            if (typeof options.renderDailyCounter === 'function') { options.renderDailyCounter(); }
            if ((!options.shouldRenderShop || options.shouldRenderShop()) && typeof options.renderShop === 'function') {
                options.renderShop();
            }
            if (typeof options.renderWorkBench === 'function') { options.renderWorkBench(); }
        }, options.intervalMs || 30000);

        if (app.bootstrap && typeof app.bootstrap.track === 'function') {
            app.bootstrap.track('shop/state-timer');
        }
        return true;
    }

    function createDomain(ctx) {
        function shopStateStorageReady() {
            return !ctx.rulesReady || ctx.rulesReady();
        }

        function dailyFreePackDate() {
            var now = new Date();
            return now.getFullYear() + '-' +
                String(now.getMonth() + 1).padStart(2, '0') + '-' +
                String(now.getDate()).padStart(2, '0');
        }

        function rollDailyGiftMaterialKey() {
            return ctx.DAILY_GIFT_MATERIAL_KEYS[Math.floor(Math.random() * ctx.DAILY_GIFT_MATERIAL_KEYS.length)] || 'mnemo_glyph';
        }

        function dailyShopPackCap(packKind) {
            if (Object.prototype.hasOwnProperty.call(ctx.PACK_DAILY_CAPS, packKind)) {
                return ctx.PACK_DAILY_CAPS[packKind];
            }
            return packKind === 'magic' ? ctx.DAILY_MAGIC_PACK_CAP : ctx.DAILY_SHOP_PACK_CAP;
        }

        function normalizeShopPackPurchases(purchases) {
            var out = {};
            ctx.PACK_KINDS.forEach(function (kind) {
                out[kind] = Math.max(0, Math.min(dailyShopPackCap(kind), ctx.clampInt(purchases && purchases[kind], 0)));
            });
            return out;
        }

        function createShopState() {
            return {
                version: 3,
                freePackDate: dailyFreePackDate(),
                freePacksClaimed: 0,
                dailyGiftDate: dailyFreePackDate(),
                dailyGiftClaimed: 0,
                dailyGiftKey: ctx.DAILY_GIFT_MATERIAL_KEYS[0],
                shopPackDate: dailyFreePackDate(),
                shopPackPurchases: normalizeShopPackPurchases({})
            };
        }

        function loadShopState() {
            if (!shopStateStorageReady()) { return null; }
            if (ctx.state.shopState) { return ctx.state.shopState; }
            var fallback = createShopState();
            var data = ctx.readMigratedJson(ctx.CARD_SHOP_STATE_KEY, ctx.LEGACY_FREE_REWARDS_KEY, null);
            if (!data || typeof data !== 'object') {
                ctx.state.shopState = fallback;
                ctx.writeJson(ctx.CARD_SHOP_STATE_KEY, ctx.state.shopState);
                return ctx.state.shopState;
            }
            var today = dailyFreePackDate();
            var storedDate = typeof data.freePackDate === 'string' ? data.freePackDate : today;
            var storedShopDate = typeof data.shopPackDate === 'string' ? data.shopPackDate : today;
            ctx.state.shopState = {
                version: 3,
                freePackDate: today,
                freePacksClaimed: storedDate === today
                    ? Math.max(0, Math.min(ctx.DAILY_FREE_PACK_CAP, ctx.clampInt(data.freePacksClaimed, 0)))
                    : 0,
                dailyGiftDate: typeof data.dailyGiftDate === 'string' ? data.dailyGiftDate : today,
                dailyGiftClaimed: (typeof data.dailyGiftDate === 'string' ? data.dailyGiftDate : today) === today
                    ? Math.max(0, Math.min(1, ctx.clampInt(data.dailyGiftClaimed, 0)))
                    : 0,
                dailyGiftKey: ctx.DAILY_GIFT_MATERIAL_KEYS.indexOf(String(data.dailyGiftKey || '')) !== -1
                    ? String(data.dailyGiftKey)
                    : rollDailyGiftMaterialKey(),
                shopPackDate: today,
                shopPackPurchases: storedShopDate === today
                    ? normalizeShopPackPurchases(data.shopPackPurchases)
                    : normalizeShopPackPurchases({})
            };
            if (ctx.state.shopState.dailyGiftDate !== today) {
                ctx.state.shopState.dailyGiftDate = today;
                ctx.state.shopState.dailyGiftClaimed = 0;
                ctx.state.shopState.dailyGiftKey = rollDailyGiftMaterialKey();
            }
            ctx.writeJson(ctx.CARD_SHOP_STATE_KEY, ctx.state.shopState);
            return ctx.state.shopState;
        }

        function saveShopState() {
            if (!shopStateStorageReady()) { return; }
            if (!ctx.state.shopState) { ctx.state.shopState = createShopState(); }
            ctx.writeJson(ctx.CARD_SHOP_STATE_KEY, ctx.state.shopState);
        }

        function syncShopState() {
            if (!shopStateStorageReady()) { return { packs: 0, mnemones: 0 }; }
            if (ctx.state.isAdmin) { return { packs: 0, mnemones: 0 }; }
            var rewards = loadShopState();
            if (!rewards) { return { packs: 0, mnemones: 0 }; }
            var today = dailyFreePackDate();
            if (rewards.freePackDate !== today) {
                rewards.freePackDate = today;
                rewards.freePacksClaimed = 0;
                rewards.dailyGiftDate = today;
                rewards.dailyGiftClaimed = 0;
                rewards.dailyGiftKey = rollDailyGiftMaterialKey();
                saveShopState();
            }
            if (rewards.dailyGiftDate !== today) {
                rewards.dailyGiftDate = today;
                rewards.dailyGiftClaimed = 0;
                rewards.dailyGiftKey = rollDailyGiftMaterialKey();
                saveShopState();
            }
            if (rewards.shopPackDate !== today) {
                rewards.shopPackDate = today;
                rewards.shopPackPurchases = normalizeShopPackPurchases({});
                saveShopState();
            }
            return { packs: 0, mnemones: 0 };
        }

        function dailyFreePacksRemaining() {
            if (ctx.state.isAdmin) { return Infinity; }
            if (!shopStateStorageReady()) { return 0; }
            syncShopState();
            return Math.max(0, ctx.DAILY_FREE_PACK_CAP - Math.max(0, Math.min(ctx.DAILY_FREE_PACK_CAP, ctx.clampInt((ctx.state.shopState || {}).freePacksClaimed, 0))));
        }

        function claimDailyFreePacks(amount) {
            amount = Math.max(1, ctx.clampInt(amount, 1));
            if (!shopStateStorageReady()) { return false; }
            if (ctx.state.isAdmin) { return true; }
            var rewards = loadShopState();
            if (!rewards) { return false; }
            syncShopState();
            if (dailyFreePacksRemaining() < amount) { return false; }
            rewards.freePacksClaimed = Math.min(ctx.DAILY_FREE_PACK_CAP, ctx.clampInt(rewards.freePacksClaimed, 0) + amount);
            saveShopState();
            return true;
        }

        function dailyGiftState() {
            if (ctx.state.isAdmin) {
                return { key: rollDailyGiftMaterialKey(), claimed: 0 };
            }
            if (!shopStateStorageReady()) {
                return { key: ctx.DAILY_GIFT_MATERIAL_KEYS[0] || 'mnemo_glyph', claimed: 0 };
            }
            syncShopState();
            return {
                key: String((ctx.state.shopState || {}).dailyGiftKey || rollDailyGiftMaterialKey()),
                claimed: Math.max(0, Math.min(1, ctx.clampInt((ctx.state.shopState || {}).dailyGiftClaimed, 0)))
            };
        }

        function dailyGiftRemaining() {
            return ctx.state.isAdmin ? Infinity : Math.max(0, 1 - dailyGiftState().claimed);
        }

        function claimDailyGift() {
            if (!shopStateStorageReady()) { return false; }
            if (ctx.state.isAdmin) { return true; }
            var rewards = loadShopState();
            if (!rewards) { return false; }
            syncShopState();
            if (dailyGiftRemaining() <= 0) { return false; }
            rewards.dailyGiftClaimed = 1;
            saveShopState();
            return true;
        }

        function syncDailyShopPackPurchases() {
            if (!shopStateStorageReady()) { return normalizeShopPackPurchases({}); }
            var shopState = loadShopState();
            if (!shopState) { return normalizeShopPackPurchases({}); }
            var today = dailyFreePackDate();
            var changed = false;
            if (shopState.shopPackDate !== today) {
                shopState.shopPackDate = today;
                shopState.shopPackPurchases = {};
                changed = true;
            }
            var normalized = normalizeShopPackPurchases(shopState.shopPackPurchases);
            if (JSON.stringify(normalized) !== JSON.stringify(shopState.shopPackPurchases || {})) {
                changed = true;
            }
            shopState.shopPackPurchases = normalized;
            if (changed) { saveShopState(); }
            return shopState.shopPackPurchases;
        }

        function dailyShopPackRemaining(packKind) {
            if (packKind === 'standard') { return Infinity; }
            var purchases = syncDailyShopPackPurchases();
            return Math.max(0, dailyShopPackCap(packKind) - Math.max(0, ctx.clampInt(purchases[packKind], 0)));
        }

        function claimDailyShopPacks(packKind, amount) {
            amount = Math.max(1, ctx.clampInt(amount, 1));
            if (packKind === 'standard') { return true; }
            var purchases = syncDailyShopPackPurchases();
            if (dailyShopPackRemaining(packKind) < amount) { return false; }
            purchases[packKind] = Math.min(dailyShopPackCap(packKind), ctx.clampInt(purchases[packKind], 0) + amount);
            ctx.state.shopState.shopPackPurchases = purchases;
            saveShopState();
            return true;
        }

        function renderDailyCounter() {
            if (!ctx.els.dailyPacksCounter) { return; }
            if (ctx.state.isAdmin) {
                ctx.els.dailyPacksCounter.textContent = ctx.uiText('shop.admin', 'Admin');
                return;
            }
            var remaining = dailyFreePacksRemaining();
            ctx.els.dailyPacksCounter.textContent = String(remaining) + ' / ' + ctx.DAILY_FREE_PACK_CAP;
            ctx.els.dailyPacksCounter.title = remaining > 0
                ? 'Sobres gratis pendientes de reclamar hoy en tienda.'
                : 'Ya reclamaste todos los sobres gratis de hoy.';
        }

        function msUntilDailyFreeReset() {
            var now = new Date();
            var next = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 0, 0, 0, 0);
            return Math.max(0, next.getTime() - now.getTime());
        }

        function dailyFreeResetProgress() {
            var dayMs = 24 * 60 * 60 * 1000;
            return Math.max(0, Math.min(100, ((dayMs - msUntilDailyFreeReset()) / dayMs) * 100));
        }

        function formatResetDuration(ms) {
            var totalMinutes = Math.max(0, Math.ceil(ms / 60000));
            var hours = Math.floor(totalMinutes / 60);
            var minutes = totalMinutes % 60;
            return hours <= 0 ? minutes + ' min' : hours + ' h ' + String(minutes).padStart(2, '0') + ' min';
        }

        function packPrice(packKind) {
            return Object.prototype.hasOwnProperty.call(ctx.PACK_PRICES, packKind) ? ctx.PACK_PRICES[packKind] : ctx.PACK_PRICES.standard;
        }

        function packLabel(packKind) {
            return ctx.PACK_LABELS[packKind] || ctx.PACK_LABELS.standard;
        }

        function packContents(packKind) {
            return ctx.PACK_CONTENTS[packKind] || ctx.PACK_CONTENTS.standard;
        }

        function packAvailableInShop(packKind, isFree) {
            return isFree || ctx.SHOP_PACK_KINDS.indexOf(packKind) !== -1;
        }

        function shopQuantitiesForPack(packKind, isFree) {
            if (isFree) { return ctx.FREE_SHOP_QUANTITIES; }
            if (packKind === 'standard') { return ctx.SHOP_QUANTITIES; }
            return ctx.SHOP_QUANTITIES.filter(function (amount) { return amount <= dailyShopPackCap(packKind); });
        }

        function renderFreePackResetMeter(item, freeRemaining) {
            var meter = item.querySelector('[data-free-pack-reset]');
            if (!meter) {
                meter = document.createElement('span');
                meter.className = 'hg-shop-free-reset';
                meter.setAttribute('data-free-pack-reset', '1');
                meter.innerHTML =
                    '<span class="hg-shop-free-reset__text"></span>' +
                    '<span class="hg-shop-free-reset__bar" aria-hidden="true"><span></span></span>';
                item.appendChild(meter);
            }
            var text = meter.querySelector('.hg-shop-free-reset__text');
            var fill = meter.querySelector('.hg-shop-free-reset__bar span');
            if (ctx.state.isAdmin) {
                if (text) { text.textContent = ctx.uiText('packs.admin_daily_free', 'Cupo diario libre en modo admin.'); }
                if (fill) { fill.style.width = '100%'; }
                return;
            }
            var resetText = formatResetDuration(msUntilDailyFreeReset());
            if (text) {
                text.textContent = freeRemaining > 0 ? 'Reinicio diario en ' + resetText : 'Nuevos sobres en ' + resetText;
            }
            if (fill) { fill.style.width = dailyFreeResetProgress().toFixed(2) + '%'; }
        }

        function renderShop() {
            ctx.ensureShopLayout();
            ctx.els.shopItems = Array.prototype.slice.call(document.querySelectorAll('[data-shop-pack], [data-shop-material], [data-shop-exchange-remorias], [data-shop-daily-gift]'));
            var money = ctx.currentMnemones();
            var remorias = ctx.currentRemorias();
            ctx.els.mnemonesCounters.forEach(function (node) { node.textContent = ctx.formatNumber(money); });
            ctx.els.remoriasCounters.forEach(function (node) { node.textContent = ctx.formatNumber(remorias); });
            ctx.els.shopItems.forEach(function (item) {
                if (item.hasAttribute('data-shop-daily-gift')) { renderDailyGiftShopItem(item); return; }
                var materialKey = item.getAttribute('data-shop-material') || '';
                if (materialKey) { renderMaterialShopItem(item, materialKey, money); return; }
                var exchangeRemorias = ctx.clampInt(item.getAttribute('data-shop-exchange-remorias'), 0);
                if (exchangeRemorias > 0) { renderExchangeShopItem(item, exchangeRemorias, money); return; }
                renderPackShopItem(item, money);
            });
            ctx.els.shopButtons = Array.prototype.slice.call(document.querySelectorAll('[data-shop-buy-pack]'));
        }

        function renderPackShopItem(item, money) {
            var kind = item.getAttribute('data-shop-pack') || 'standard';
            var isFree = item.getAttribute('data-shop-free') === '1';
            if (!packAvailableInShop(kind, isFree)) {
                item.hidden = true;
                return;
            }
            item.hidden = false;
            var nameNode = item.querySelector('span');
            if (nameNode && !nameNode.classList.contains('hg-shop-item__contents') && !nameNode.classList.contains('hg-shop-item__actions')) {
                nameNode.textContent = isFree ? ctx.uiText('shop.free_pack_name', 'Dispensador mnemónico') : packLabel(kind);
            }
            var price = isFree ? 0 : packPrice(kind);
            var freeRemaining = isFree ? dailyFreePacksRemaining() : 0;
            var dailyRemaining = !isFree ? dailyShopPackRemaining(kind) : Infinity;
            item.setAttribute('data-shop-daily-limit', kind === 'standard' || isFree ? '0' : String(dailyShopPackCap(kind)));
            item.setAttribute('data-shop-daily-remaining', kind === 'standard' || isFree ? '' : String(dailyRemaining));
            var priceNode = item.querySelector('strong');
            if (priceNode) {
                if (isFree) {
                    priceNode.textContent = ctx.state.isAdmin
                        ? ctx.uiText('shop.admin', 'Admin')
                        : (freeRemaining > 0 ? ctx.uiText('shop.free_available', 'Gratis - quedan {remaining}', { remaining: freeRemaining }) : ctx.uiText('shop.free_sold_out', 'Agotado hoy'));
                } else {
                    priceNode.innerHTML = '<span class="hg-shop-item__price">' + ctx.escapeHtml(ctx.uiText('shop.pack_price_stock', '{price} Mnemones', { price: ctx.formatNumber(price) })) + '</span>' +
                        (kind !== 'standard' ? '<span class="hg-shop-item__stock">' + ctx.escapeHtml(ctx.uiText('shop.pack_stock', 'quedan {remaining}', { remaining: dailyRemaining })) + '</span>' : '');
                }
            }
            var description = item.querySelector('.hg-shop-item__contents');
            if (!description) {
                description = document.createElement('span');
                description.className = 'hg-shop-item__contents';
                item.appendChild(description);
            }
            description.textContent = isFree ? ctx.uiText('shop.free_pack_description', 'Reclama hasta {cap} sobres mnemónicos gratis al día.', { cap: ctx.DAILY_FREE_PACK_CAP }) : packContents(kind);
            item.title = isFree
                ? ctx.uiText('shop.free_pack_title', 'Sobres mnemónicos gratis. Quedan {remaining} hoy.', { remaining: ctx.state.isAdmin ? ctx.uiText('shop.admin', 'Admin') : freeRemaining })
                : ctx.uiText('shop.pack_title', '{pack}: {description} Precio: {price} Mnemones.', { pack: packLabel(kind), description: packContents(kind), price: ctx.formatNumber(price) });
            var controls = item.querySelector('.hg-shop-item__actions');
            if (!controls) {
                controls = document.createElement('span');
                controls.className = 'hg-shop-item__actions';
                item.appendChild(controls);
            }
            controls.innerHTML = '';
            if (isFree) { renderFreePackResetMeter(item, freeRemaining); }
            shopQuantitiesForPack(kind, isFree).forEach(function (amount) {
                var buy = document.createElement('button');
                buy.type = 'button';
                buy.className = 'hg-shop-buy';
                buy.setAttribute('data-shop-buy-pack', kind);
                buy.setAttribute('data-shop-buy-amount', String(amount));
                buy.setAttribute('data-shop-daily-limit', kind === 'standard' || isFree ? '0' : String(dailyShopPackCap(kind)));
                buy.setAttribute('data-shop-daily-remaining', kind === 'standard' || isFree ? '' : String(dailyRemaining));
                if (isFree) { buy.setAttribute('data-shop-buy-free', '1'); }
                buy.textContent = 'x' + amount;
                buy.disabled = isFree
                    ? (!ctx.state.isAdmin && (freeRemaining < amount || ctx.packSpace(kind) < amount))
                    : ((dailyRemaining < amount) || (!ctx.state.isAdmin && (money < price * amount || ctx.packSpace(kind) < amount)));
                buy.title = isFree
                    ? ctx.uiText('shop.free_pack_buy_title', 'Reclamar {amount} sobre(s) mnemónicos gratis', { amount: amount })
                    : ctx.uiText('shop.pack_buy_title', 'Comprar {amount} por {price} Mnemones{remainingText}', {
                        amount: amount,
                        price: ctx.formatNumber(price * amount),
                        remainingText: kind !== 'standard' ? '. Quedan ' + dailyRemaining + ' hoy.' : ''
                    });
                controls.appendChild(buy);
            });
            item.classList.toggle('is-empty', isFree ? (!ctx.state.isAdmin && (freeRemaining <= 0 || ctx.packSpace(kind) <= 0)) : (dailyRemaining <= 0 || (!ctx.state.isAdmin && (money < price || ctx.packSpace(kind) <= 0))));
        }

        function renderDailyGiftShopItem(item) {
            var giftState = dailyGiftState();
            var materialKey = giftState.key;
            var material = ctx.UPGRADE_MATERIALS[materialKey];
            if (!material) { item.hidden = true; return; }
            item.hidden = false;
            item.classList.add('hg-shop-item--gift');
            var remaining = dailyGiftRemaining();
            var nameNode = item.querySelector('span');
            if (nameNode && !nameNode.classList.contains('hg-shop-item__contents')) {
                nameNode.innerHTML = ctx.materialIconHtml(materialKey) + '<span>' + ctx.escapeHtml(ctx.uiText('shop.daily_gift_name', 'Emisión ritual diaria')) + ': ' + ctx.escapeHtml(material.label) + '</span>';
            }
            var priceNode = item.querySelector('strong');
            if (priceNode) { priceNode.textContent = remaining > 0 ? ctx.uiText('shop.daily_gift_price_available', 'Gratis - 1 al día') : ctx.uiText('shop.daily_gift_price_claimed', 'Reclamado hoy'); }
            var description = item.querySelector('.hg-shop-item__contents');
            if (!description) {
                description = document.createElement('span');
                description.className = 'hg-shop-item__contents';
                item.appendChild(description);
            }
            description.textContent = ctx.uiText('shop.daily_gift_description', 'Cada día sale al azar: {materials}.', { materials: ctx.DAILY_GIFT_MATERIAL_KEYS.map(function (key) {
                return ctx.UPGRADE_MATERIALS[key] && ctx.UPGRADE_MATERIALS[key].label ? ctx.UPGRADE_MATERIALS[key].label : key;
            }).join(' o ') });
            var controls = item.querySelector('.hg-shop-item__actions');
            if (!controls) {
                controls = document.createElement('span');
                controls.className = 'hg-shop-item__actions';
                item.appendChild(controls);
            }
            controls.innerHTML = '';
            var claim = document.createElement('button');
            claim.type = 'button';
            claim.className = 'hg-shop-buy';
            claim.setAttribute('data-shop-claim-daily-gift', materialKey);
            claim.textContent = ctx.uiText('shop.claim', 'Reclamar');
            claim.disabled = !ctx.state.isAdmin && remaining <= 0;
            claim.title = remaining > 0 ? 'Reclamar regalo diario' : 'Ya reclamado hoy';
            controls.appendChild(claim);
            item.classList.toggle('is-empty', !ctx.state.isAdmin && remaining <= 0);
        }

        function renderMaterialShopItem(item, materialKey, mnemones) {
            var material = ctx.UPGRADE_MATERIALS[materialKey];
            if (!material) { item.hidden = true; return; }
            item.hidden = false;
            var nameNode = item.querySelector('span');
            if (nameNode && !nameNode.classList.contains('hg-shop-item__contents')) {
                nameNode.innerHTML = ctx.materialIconHtml(materialKey) + '<span>' + ctx.escapeHtml(material.label) + '</span>';
            }
            var priceNode = item.querySelector('strong');
            if (priceNode) { priceNode.textContent = ctx.uiText('shop.pack_price_stock', '{price} Mnemones', { price: ctx.formatNumber(material.price) }); }
            var description = item.querySelector('.hg-shop-item__contents');
            if (!description) {
                description = document.createElement('span');
                description.className = 'hg-shop-item__contents';
                item.appendChild(description);
            }
            description.textContent = ctx.uiText('shop.material_have', '{description} Tienes: {stock}.', {
                description: material.description,
                stock: ctx.state.isAdmin ? ctx.uiText('shop.admin', 'Admin') : ctx.materialStock(materialKey)
            });
            item.title = ctx.uiText('shop.material_title', '{material}: {description} Precio: {price} Mnemones.', { material: material.label, description: material.description, price: ctx.formatNumber(material.price) });
            var controls = item.querySelector('.hg-shop-item__actions');
            if (!controls) {
                controls = document.createElement('span');
                controls.className = 'hg-shop-item__actions';
                item.appendChild(controls);
            }
            controls.innerHTML = '';
            ctx.SHOP_QUANTITIES.forEach(function (amount) {
                var buy = document.createElement('button');
                buy.type = 'button';
                buy.className = 'hg-shop-buy';
                buy.setAttribute('data-shop-buy-material', materialKey);
                buy.setAttribute('data-shop-buy-amount', String(amount));
                buy.textContent = 'x' + amount;
                buy.disabled = !ctx.state.isAdmin && mnemones < material.price * amount;
                buy.title = ctx.uiText('shop.material_buy_title', 'Comprar {amount} por {price} Mnemones', { amount: amount, price: ctx.formatNumber(material.price * amount) });
                controls.appendChild(buy);
            });
            item.classList.toggle('is-empty', !ctx.state.isAdmin && mnemones < material.price);
        }

        function renderExchangeShopItem(item, remoriasAmount, mnemones) {
            var totalPrice = remoriasAmount * 10;
            item.hidden = false;
            item.classList.add('hg-shop-item--exchange');
            var nameNode = item.querySelector('span');
            if (nameNode && !nameNode.classList.contains('hg-shop-item__contents')) {
                nameNode.textContent = ctx.uiText('shop.exchange_name', 'Cambio por {remorias} Remorias', { remorias: ctx.formatNumber(remoriasAmount) });
            }
            var priceNode = item.querySelector('strong');
            if (priceNode) { priceNode.textContent = ctx.uiText('shop.pack_price_stock', '{price} Mnemones', { price: ctx.formatNumber(totalPrice) }); }
            var description = item.querySelector('.hg-shop-item__contents');
            if (!description) {
                description = document.createElement('span');
                description.className = 'hg-shop-item__contents';
                item.appendChild(description);
            }
            description.textContent = ctx.uiText('shop.exchange_rate', 'Tasa fija: 10 Mnemones = 1 Remoria.');
            item.title = ctx.uiText('shop.exchange_title', 'Cambiar {mnemones} Mnemones por {remorias} Remorias.', { mnemones: ctx.formatNumber(totalPrice), remorias: ctx.formatNumber(remoriasAmount) });
            var controls = item.querySelector('.hg-shop-item__actions');
            if (!controls) {
                controls = document.createElement('span');
                controls.className = 'hg-shop-item__actions';
                item.appendChild(controls);
            }
            controls.innerHTML = '';
            var buy = document.createElement('button');
            buy.type = 'button';
            buy.className = 'hg-shop-buy';
            buy.setAttribute('data-shop-buy-exchange-remorias', String(remoriasAmount));
            buy.textContent = ctx.uiText('shop.exchange_button', 'Cambiar');
            buy.disabled = !ctx.state.isAdmin && mnemones < totalPrice;
            buy.title = ctx.uiText('shop.exchange_title', 'Cambiar {mnemones} Mnemones por {remorias} Remorias.', { mnemones: ctx.formatNumber(totalPrice), remorias: ctx.formatNumber(remoriasAmount) });
            controls.appendChild(buy);
            item.classList.toggle('is-empty', !ctx.state.isAdmin && mnemones < totalPrice);
        }

        function buyPack(packKind, amount, options) {
            options = options || {};
            amount = Math.max(1, ctx.clampInt(amount, 1));
            packKind = packKind || 'standard';
            var isFree = options.free === true;
            if (ctx.PACK_KINDS.indexOf(packKind) === -1) {
                ctx.setStatus(ctx.uiText('status.pack_unknown', 'Ese sobre no existe.'));
                return false;
            }
            if (!isFree && !packAvailableInShop(packKind, false)) {
                ctx.setStatus(ctx.uiText('status.pack_not_shop', 'Ese sobre no está disponible en la tienda normal.'));
                return false;
            }
            if (!ctx.state.collection) { ctx.loadCollection(); }
            var price = packPrice(packKind);
            if (!ctx.state.isAdmin && ctx.packSpace(packKind) < amount) {
                ctx.setStatus(ctx.uiText('status.pack_stock_full', 'No puedes acumular más de {max} sobres de cada tipo.', { max: ctx.MAX_PACK_STOCK }));
                ctx.renderPackInventory();
                return false;
            }
            if (isFree) {
                if (!claimDailyFreePacks(amount)) {
                    ctx.setStatus(ctx.uiText('status.free_pack_not_enough', 'No puedes reclamar {amount} sobres gratis. Quedan {remaining} hoy.', { amount: amount, remaining: dailyFreePacksRemaining() }));
                    renderDailyCounter();
                    ctx.renderPackInventory();
                    return false;
                }
                ctx.playMoneySound();
                ctx.addPack('standard', amount, { silent: true, deferSave: true });
                ctx.saveCollection();
                renderDailyCounter();
                ctx.renderPackInventory();
                ctx.setStatus(ctx.uiText('status.free_pack_claimed', '{amount} sobre(s) mnemónicos gratis añadidos. Quedan {remaining} gratis hoy.', { amount: amount, remaining: dailyFreePacksRemaining() }));
                return true;
            }
            var totalPrice = price * amount;
            if (!ctx.state.isAdmin && ctx.currentMnemones() < totalPrice) {
                ctx.setStatus(ctx.uiText('status.not_enough_mnemones_pack', 'No tienes Mnemones suficientes para comprar {pack}.', { pack: packLabel(packKind).toLowerCase() }));
                return false;
            }
            if (!claimDailyShopPacks(packKind, amount)) {
                ctx.setStatus(ctx.uiText('status.pack_daily_limit', 'Límite diario alcanzado para {pack}. Quedan {remaining} hoy.', { pack: packLabel(packKind).toLowerCase(), remaining: dailyShopPackRemaining(packKind) }));
                ctx.renderPackInventory();
                return false;
            }
            if (!ctx.state.isAdmin) { ctx.addMnemones(-totalPrice); }
            ctx.playMoneySound();
            ctx.addPack(packKind, amount, { silent: true, deferSave: true });
            ctx.saveCollection();
            ctx.renderPackInventory();
            ctx.setStatus(ctx.uiText('status.pack_bought', '{amount} x {pack} añadidos a tus sobres.', { amount: amount, pack: packLabel(packKind).toLowerCase() }));
            return true;
        }

        function buyMaterial(materialKey, amount) {
            amount = Math.max(1, ctx.clampInt(amount, 1));
            var material = ctx.UPGRADE_MATERIALS[materialKey];
            if (!material) {
                ctx.setStatus(ctx.uiText('status.material_unknown', 'Ese objeto no existe.'));
                return false;
            }
            if (!ctx.state.collection) { ctx.loadCollection(); }
            var totalPrice = material.price * amount;
            if (!ctx.state.isAdmin && ctx.currentMnemones() < totalPrice) {
                ctx.setStatus(ctx.uiText('status.not_enough_mnemones_material', 'No tienes Mnemones suficientes para comprar {material}.', { material: material.label }));
                return false;
            }
            if (!ctx.state.isAdmin) { ctx.addMnemones(-totalPrice); }
            ctx.playMoneySound();
            var newStock = ctx.addMaterial(materialKey, amount);
            if (newStock === false) {
                ctx.setStatus(ctx.uiText('status.material_inventory_error', 'No se ha podido añadir ese objeto al inventario.'));
                return false;
            }
            if (ctx.saveCollection() === false) {
                ctx.setStatus(ctx.uiText('status.local_storage_error', 'No se pudo guardar en localStorage.'));
                return false;
            }
            ctx.renderSummary({ light: true });
            ctx.renderPackInventory();
            ctx.setStatus(ctx.uiText('status.material_bought', '{amount} x {material} añadido(s) al inventario por {price} Mnemones. Tienes {stock}.', {
                amount: amount,
                material: material.label,
                price: ctx.formatNumber(totalPrice),
                stock: ctx.state.isAdmin ? ctx.uiText('shop.admin', 'Admin') : newStock
            }));
            return true;
        }

        function claimShopDailyGift(materialKey) {
            materialKey = String(materialKey || '');
            var stateNow = dailyGiftState();
            var rewardKey = materialKey || stateNow.key;
            var material = ctx.UPGRADE_MATERIALS[rewardKey];
            if (!material) {
                ctx.setStatus(ctx.uiText('status.daily_gift_unknown', 'Ese regalo diario no existe.'));
                return false;
            }
            if (!claimDailyGift()) {
                ctx.setStatus(ctx.uiText('status.daily_gift_already_claimed', 'Ya has reclamado el regalo diario de hoy.'));
                return false;
            }
            ctx.addMaterial(rewardKey, 1);
            ctx.saveCollection();
            ctx.playMoneySound();
            ctx.renderSummary({ light: true });
            ctx.renderPackInventory();
            ctx.setStatus(ctx.uiText('status.daily_gift_claimed', 'Regalo diario reclamado: 1 x {material}.', { material: material.label }));
            return true;
        }

        function buyRemoriaExchange(remoriasAmount) {
            remoriasAmount = Math.max(1, ctx.clampInt(remoriasAmount, 1));
            var totalPrice = remoriasAmount * 10;
            if (!ctx.state.collection) { ctx.loadCollection(); }
            if (!ctx.state.isAdmin && ctx.currentMnemones() < totalPrice) {
                ctx.setStatus(ctx.uiText('status.not_enough_mnemones_exchange', 'No tienes Mnemones suficientes para ese cambio.'));
                return false;
            }
            if (!ctx.state.isAdmin) {
                ctx.addMnemones(-totalPrice);
                ctx.addRemorias(remoriasAmount);
            }
            ctx.playMoneySound();
            ctx.saveCollection();
            ctx.renderSummary({ light: true });
            ctx.renderPackInventory();
            ctx.setStatus(ctx.uiText('status.exchange_done', 'Cambio realizado: -{mnemones} Mnemones, +{remorias} Remorias.', { mnemones: ctx.formatNumber(totalPrice), remorias: ctx.formatNumber(remoriasAmount) }));
            return true;
        }

        return Object.freeze({
            createShopState: createShopState,
            loadShopState: loadShopState,
            saveShopState: saveShopState,
            syncShopState: syncShopState,
            dailyFreePacksRemaining: dailyFreePacksRemaining,
            claimDailyFreePacks: claimDailyFreePacks,
            dailyGiftState: dailyGiftState,
            dailyGiftRemaining: dailyGiftRemaining,
            claimDailyGift: claimDailyGift,
            normalizeShopPackPurchases: normalizeShopPackPurchases,
            syncDailyShopPackPurchases: syncDailyShopPackPurchases,
            dailyShopPackRemaining: dailyShopPackRemaining,
            claimDailyShopPacks: claimDailyShopPacks,
            renderDailyCounter: renderDailyCounter,
            renderShop: renderShop,
            buyPack: buyPack,
            buyMaterial: buyMaterial,
            claimShopDailyGift: claimShopDailyGift,
            buyRemoriaExchange: buyRemoriaExchange
        });
    }

    app.shop.controls = Object.freeze({
        bind: bindControls
    });
    app.shop.stateTimer = Object.freeze({
        start: startStateTimer
    });
    app.shop.domain = Object.freeze({
        create: createDomain
    });
})(window);
