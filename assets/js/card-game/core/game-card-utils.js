(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.core = app.core || {};

    function normalizeStorageScope(scope) {
        return String(scope || 'prod').toLowerCase().replace(/[^a-z0-9_-]+/g, '_');
    }

    function scopedStorageKey(baseKey, storageScope) {
        var scope = normalizeStorageScope(
            storageScope
            || (app.bootstrap && app.bootstrap.env && app.bootstrap.env.storageScope)
            || 'prod'
        );
        return scope === 'prod' ? baseKey : ('hg_' + scope + '__' + baseKey);
    }

    function nowIso() {
        return new Date().toISOString();
    }

    function clampInt(value, fallback) {
        var n = Number(value);
        if (!Number.isFinite(n)) { return fallback; }
        return Math.round(n);
    }

    function clampQuality(value, fallback) {
        var n = Number(value);
        if (!Number.isFinite(n)) { return fallback; }
        return Math.max(0, Math.min(100, Math.round(n * 10) / 10));
    }

    function formatNumber(value) {
        return clampInt(value, 0).toLocaleString('es-ES');
    }

    function formatDate(value) {
        var d = new Date(value);
        if (Number.isNaN(d.getTime())) { return '-'; }
        return d.toLocaleDateString('es-ES') + ' ' + d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(text) {
        return String(text === null || text === undefined ? '' : text).replace(/[&<>"']/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
        });
    }

    function normalizeSearchText(value) {
        value = String(value || '').trim().toLowerCase();
        if (typeof value.normalize === 'function') {
            value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return value.replace(/\s+/g, ' ');
    }

    app.core.utils = Object.freeze({
        normalizeStorageScope: normalizeStorageScope,
        scopedStorageKey: scopedStorageKey,
        nowIso: nowIso,
        clampInt: clampInt,
        clampQuality: clampQuality,
        formatNumber: formatNumber,
        formatDate: formatDate,
        escapeHtml: escapeHtml,
        normalizeSearchText: normalizeSearchText
    });
})(window);
