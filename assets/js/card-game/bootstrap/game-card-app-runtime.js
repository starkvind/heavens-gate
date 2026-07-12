(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.bootstrap = app.bootstrap || {};

    function runLegacyRuntime(hooks) {
        hooks = hooks || {};
        if (typeof hooks.loadCollectionViewPrefs === 'function') { hooks.loadCollectionViewPrefs(); }
        if (typeof hooks.decorateIconNavigation === 'function') { hooks.decorateIconNavigation(); }
        if (typeof hooks.updateDesktopHashPanels === 'function') { hooks.updateDesktopHashPanels(); }
        if (typeof hooks.bindHashChange === 'function') {
            hooks.bindHashChange();
        } else if (typeof hooks.updateDesktopHashPanels === 'function') {
            global.addEventListener('hashchange', hooks.updateDesktopHashPanels);
        }
        if (typeof hooks.bindEvents === 'function') { hooks.bindEvents(); }
        if (typeof hooks.loadGameRules !== 'function') { return; }

        hooks.loadGameRules().then(function (rulesPayload) {
            if (!rulesPayload) { return []; }
            if (typeof hooks.decorateIconNavigation === 'function') { hooks.decorateIconNavigation(); }
            if (typeof hooks.loadCollection === 'function') { hooks.loadCollection(); }
            if (typeof hooks.renderAfterCollectionLoad === 'function') { hooks.renderAfterCollectionLoad(); }
            if (typeof hooks.startShopStateTimer === 'function') { hooks.startShopStateTimer(); }
            return typeof hooks.loadCatalog === 'function' ? hooks.loadCatalog() : [];
        });
    }

    app.bootstrap.appRuntime = Object.freeze({
        runLegacyRuntime: runLegacyRuntime
    });
})(window);
