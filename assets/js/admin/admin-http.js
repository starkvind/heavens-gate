(function (global) {
  'use strict';

  function csrfToken() {
    if (typeof global.ADMIN_CSRF_TOKEN === 'string' && global.ADMIN_CSRF_TOKEN !== '') {
      return global.ADMIN_CSRF_TOKEN;
    }
    var meta = document.querySelector('meta[name="hg-admin-csrf"]');
    return meta ? (meta.getAttribute('content') || '') : '';
  }

  function errorMessage(err) {
    if (!err) return 'Error inesperado';
    if (err.status === 413) {
      return 'La subida supera el limite permitido por el servidor (HTTP 413).';
    }
    if (err.payload) {
      if (err.payload.message === 'Respuesta no JSON') {
        var raw = String(err.payload.raw || '');
        raw = raw.replace(/\s+/g, ' ').trim();
        if (raw.length > 180) raw = raw.slice(0, 180) + '...';
        return 'El servidor no devolvio JSON valido. Respuesta parcial: ' + (raw || '(vacia)');
      }
      return err.payload.message || err.payload.msg || err.payload.error || err.message || 'Error de peticion';
    }
    return err.message || 'Error de peticion';
  }

  function setLoading(el, active) {
    if (!el || !el.classList) return;
    if (active) {
      el.classList.add('adm-loading');
      el.setAttribute('aria-busy', 'true');
    } else {
      el.classList.remove('adm-loading');
      el.removeAttribute('aria-busy');
    }
  }

  function ensureToastStack() {
    var stack = document.getElementById('admToastStack');
    if (stack) return stack;
    stack = document.createElement('div');
    stack.id = 'admToastStack';
    stack.className = 'adm-toast-stack';
    document.body.appendChild(stack);
    return stack;
  }

  function notify(message, type, timeout) {
    var stack = ensureToastStack();
    var node = document.createElement('div');
    node.className = 'adm-toast adm-toast-' + (type || 'info');
    node.textContent = message || 'Hecho';
    stack.appendChild(node);

    var ms = typeof timeout === 'number' ? timeout : 2200;
    setTimeout(function () {
      node.classList.add('out');
      setTimeout(function () {
        if (node.parentNode) node.parentNode.removeChild(node);
      }, 180);
    }, ms);
  }

  async function parseResponse(response) {
    var text = await response.text();
    if (!text) return {};
    try {
      return JSON.parse(text);
    } catch (e) {
      return { ok: false, message: 'Respuesta no JSON', raw: text };
    }
  }

  async function request(url, options) {
    var opts = options || {};
    var method = (opts.method || 'POST').toUpperCase();
    var headers = Object.assign({}, opts.headers || {});
    headers['X-Requested-With'] = headers['X-Requested-With'] || 'XMLHttpRequest';

    if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
      var token = csrfToken();
      if (token && !headers['X-CSRF-Token']) {
        headers['X-CSRF-Token'] = token;
      }
    }

    var body = opts.body;
    if (opts.json !== undefined) {
      headers['Content-Type'] = headers['Content-Type'] || 'application/json';
      body = JSON.stringify(opts.json);
    }

    var loadingEl = opts.loadingEl || null;
    if (loadingEl) setLoading(loadingEl, true);

    try {
      var response = await fetch(url, {
        method: method,
        credentials: opts.credentials || 'same-origin',
        headers: headers,
        body: body
      });
      var payload = await parseResponse(response);
      if (!response.ok || (payload && payload.ok === false)) {
        var err = new Error((payload && (payload.message || payload.msg)) || ('HTTP ' + response.status));
        err.status = response.status;
        err.payload = payload;
        throw err;
      }
      return payload;
    } finally {
      if (loadingEl) setLoading(loadingEl, false);
    }
  }

  function postAction(url, action, payload, options) {
    var body = Object.assign({ action: action }, payload || {});
    var opts = Object.assign({}, options || {}, { method: 'POST', json: body });
    return request(url, opts);
  }

  global.HGAdminHttp = {
    csrfToken: csrfToken,
    request: request,
    postAction: postAction,
    notify: notify,
    setLoading: setLoading,
    errorMessage: errorMessage
  };
})(window);
