(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.core = app.core || {};

    function readJson(key, fallback) {
        try {
            var raw = global.localStorage.getItem(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function writeJson(key, value) {
        try {
            global.localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (e) {
            return false;
        }
    }

    function readMigratedJson(key, legacyKey, fallback) {
        var data = readJson(key, null);
        if (data !== null && data !== undefined) { return data; }
        data = readJson(legacyKey, null);
        if (data !== null && data !== undefined) {
            writeJson(key, data);
            return data;
        }
        return fallback;
    }

    function readText(key, fallback) {
        try {
            var raw = global.localStorage.getItem(key);
            return raw ? String(raw) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function writeText(key, value) {
        try {
            global.localStorage.setItem(key, String(value));
            return true;
        } catch (e) {
            return false;
        }
    }

    function readMigratedText(key, legacyKey, fallback) {
        var value = readText(key, null);
        if (value !== null && value !== undefined) { return value; }
        value = readText(legacyKey, null);
        if (value !== null && value !== undefined) {
            writeText(key, value);
            return value;
        }
        return fallback;
    }

    app.core.storage = Object.freeze({
        readJson: readJson,
        writeJson: writeJson,
        readMigratedJson: readMigratedJson,
        readText: readText,
        writeText: writeText,
        readMigratedText: readMigratedText
    });
})(window);
