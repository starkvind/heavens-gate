#!/usr/bin/env node
'use strict';

// Regression: a non-JSON upload reply must not access XMLHttpRequest.responseText
// while responseType='json'; failures must advance the sequential queue once.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.resolve(__dirname, '../../app/controllers/admin/admin_gallery.php'), 'utf8');
const start = source.indexOf('// ===== Subida secuencial con barra de progreso por fichero =====');
const end = source.indexOf('</script>', start);
assert.ok(start >= 0 && end > start, 'Gallery upload script should exist');
const uploadScript = source.slice(start, end);
assert.doesNotMatch(uploadScript, /xhr\.responseText/, 'JSON-mode XHR must never read responseText');

function simulate(status, response, filename = 'Escena A.png') {
  class Element {
    constructor() {
      this.children = [];
      this.classes = new Set();
      this.classList = { add: name => this.classes.add(name) };
      this.style = {};
      this.textContent = '';
      this.innerHTML = '';
    }
    appendChild(child) { this.children.push(child); return child; }
    querySelector(selector) {
      if (selector === '.name') return this.nameNode ||= new Element();
      if (selector === '.progress-bar') return this.barNode ||= new Element();
      return null;
    }
  }

  let onSubmit = null;
  let refreshes = 0;
  const form = {
    addEventListener(event, cb) { assert.equal(event, 'submit'); onSubmit = cb; },
    querySelector(selector) { assert.equal(selector, 'input[name="relDir"]'); return { value: 'Escenas' }; }
  };
  const input = { files: [{ name: filename }] };
  const progress = new Element();
  const grid = new Element();
  const nodes = { uploadForm: form, imagesInput: input, uploadProgress: progress, imgGrid: grid };
  const requests = [];
  const context = {
    document: {
      getElementById(id) { return nodes[id]; },
      createElement() { return new Element(); }
    },
    window: {
      ADMIN_CSRF_TOKEN: 'test',
      location: { href: 'https://example.test/talim?s=admin_gallery&dir=Escenas' },
      refreshGalleryState() { refreshes++; }
    },
    URL,
    Date,
    FormData: class { append() {} },
    XMLHttpRequest: class {
      constructor() { this.upload = {}; this.headers = {}; requests.push(this); }
      open(method) { assert.equal(method, 'POST'); }
      setRequestHeader(name, value) { this.headers[name] = value; }
      send() {
        this.readyState = 4;
        this.status = status;
        this.response = response;
        Object.defineProperty(this, 'responseText', {
          get() { throw new Error('InvalidStateError: responseText inaccessible for responseType=json'); }
        });
        this.onreadystatechange();
        if (status === 0 && this.onerror) this.onerror();
      }
    },
    alert() { assert.fail('No alert expected'); }
  };
  vm.runInNewContext(uploadScript, context);
  assert.equal(typeof onSubmit, 'function');
  onSubmit({ preventDefault() {} });
  assert.equal(requests.length, 1, 'Exactly one upload per selected file');
  assert.equal(requests[0].headers['X-Requested-With'], 'XMLHttpRequest');
  assert.equal(refreshes, 1, 'Queue should finish exactly once');
  assert.equal(progress.children.length, 1);
  assert.equal(progress.children[0].nameNode.textContent, filename, 'Filename rendered as text');
  return { row: progress.children[0], grid };
}

const oversized = simulate(413, null);
assert.equal(oversized.row.classes.has('error'), true);
assert.match(oversized.row.barNode.textContent, /HTTP 413/);

const diskFailure = simulate(400, { ok: false, msg: 'La carpeta de destino no permite escritura.' });
assert.equal(diskFailure.row.classes.has('error'), true);
assert.match(diskFailure.row.barNode.textContent, /no permite escritura/);

const network = simulate(0, null);
assert.equal(network.row.classes.has('error'), true);

const success = simulate(200, {
  ok: true, url: '/public/img/gallery/Escenas/escena.webp',
  thumb: '/public/img/gallery/Escenas/thumbnails/escena.webp',
  title: '<img src=x onerror=alert(1)>'
});
assert.equal(success.row.classes.has('done'), true);
assert.equal(success.grid.children.length, 1);
assert.equal(success.grid.children[0].children[1].textContent, '<img src=x onerror=alert(1)>');

console.log('Admin Gallery upload XHR: non-JSON, HTTP 413, permission error, network, success PASS');
