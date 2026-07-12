(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.core = app.core || {};

    function onRootClick(root, selector, handler) {
        if (!root || !selector || typeof handler !== 'function') { return null; }
        var listener = function (event) {
            var target = event.target && event.target.closest ? event.target.closest(selector) : null;
            if (!target || !root.contains(target)) { return; }
            handler(event, target);
        };
        document.addEventListener('click', listener);
        return listener;
    }

    function onHashChange(handler) {
        if (typeof handler !== 'function') { return null; }
        window.addEventListener('hashchange', handler);
        return handler;
    }

    app.core.events = Object.freeze({
        onRootClick: onRootClick,
        onHashChange: onHashChange
    });
})(window);
