(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.combat = app.combat || {};

    var registry = {};
    var metadata = {};
    var config = {
        features: function () { return global.HG_CARD_GAME_FEATURES || {}; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function featureEnabled(meta) {
        var features;
        if (!meta || !meta.featureFlag) { return true; }
        features = config.features() || {};
        return features[meta.featureFlag] !== false;
    }

    function register(id, modeApi, meta) {
        id = String(id || '').trim();
        if (!id || !modeApi || typeof modeApi !== 'object') { return api; }
        registry[id] = modeApi;
        metadata[id] = Object.assign({ id: id }, meta || {});
        api[id] = modeApi;
        return api;
    }

    function get(id) {
        return registry[String(id || '').trim()] || null;
    }

    function meta(id) {
        return metadata[String(id || '').trim()] || null;
    }

    function isRegistered(id) {
        return !!get(id);
    }

    function isEnabled(id) {
        return isRegistered(id) && featureEnabled(meta(id));
    }

    function keys() {
        return Object.keys(registry);
    }

    function enabledKeys() {
        return keys().filter(isEnabled);
    }

    function firstEnabled() {
        var list = enabledKeys();
        return list.length ? list[0] : null;
    }

    function normalize(id, fallback) {
        if (isEnabled(id)) { return String(id); }
        if (isEnabled(fallback)) { return String(fallback); }
        return firstEnabled() || String(fallback || id || '');
    }

    function start(id, options) {
        var modeId = normalize(id);
        var modeApi = get(modeId);
        var modeMeta = meta(modeId) || {};
        var startMethod = modeMeta.startMethod || 'start';
        if (!modeApi || typeof modeApi[startMethod] !== 'function') { return false; }
        return modeApi[startMethod](options);
    }

    var api = {
        configure: configure,
        register: register,
        get: get,
        meta: meta,
        isRegistered: isRegistered,
        isEnabled: isEnabled,
        keys: keys,
        enabledKeys: enabledKeys,
        firstEnabled: firstEnabled,
        normalize: normalize,
        start: start
    };

    app.combat.modes = api;
})(window);
