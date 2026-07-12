(function (global) {
    'use strict';

    if (!global.HGCardGame || !global.HGCardGame.bootstrap) {
        return;
    }

    var bootstrap = global.HGCardGame.bootstrap;
    var env = bootstrap.env || {};

    bootstrap.runtime = Object.freeze({
        mode: env.runtimeMode || 'hybrid',
        view: env.view || 'gacha',
        mobile: !!env.mobile,
        modules: (bootstrap.state && bootstrap.state.viewModules ? bootstrap.state.viewModules.slice() : []),
        allRoutesUseModularBootstrap: true,
        wrapperStillRequired: false
    });

    bootstrap.track('bootstrap/app');
    bootstrap.state.ready = true;
})(window);
