#!/usr/bin/env node
'use strict';

// Regression for issue #68: a childList MutationObserver watches the entire
// dense table. Reassigning an already-rendered icon's textContent causes
// another childList mutation, resulting in repeated animation frames.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const dense = fs.readFileSync(path.join(root, 'assets/js/hg-admin-dense-tables.js'), 'utf8');
const relations = fs.readFileSync(path.join(root, 'app/controllers/admin/admin_relations.php'), 'utf8');

const source = dense.match(/  function compactActionColumn\(table, wrapper\) \{[\s\S]*?\n  \}\n\n  function registerTable/);
assert.ok(source, 'compactActionColumn must be extractable for its regression test');
const functionSource = source[0].replace(/\n\n  function registerTable$/, '');

const classes = () => {
  const set = new Set();
  return { add: (...items) => items.forEach(item => set.add(item)), has: item => set.has(item) };
};
const makeButton = (initial) => {
  let text = initial;
  let writes = 0;
  const attrs = new Map();
  return {
    classList: classes(),
    get textContent() { return text; },
    set textContent(value) { writes += 1; text = value; },
    getAttribute(name) { return attrs.get(name) ?? null; },
    setAttribute(name, value) { attrs.set(name, value); },
    get writes() { return writes; }
  };
};
const edit = makeButton('Editar');
const remove = makeButton('Borrar');
const heading = { textContent: 'Acciones', classList: classes(), title: '' };
const actionCell = {
  classList: classes(),
  querySelectorAll(selector) {
    assert.equal(selector, 'button, a');
    return [edit, remove];
  }
};
const cells = [{}, {}, {}, {}, actionCell];
const table = {
  tHead: { rows: [{ cells: [{}, {}, {}, {}, heading] }] },
  tBodies: [{ rows: [{ cells }] }]
};
const wrapper = { classList: classes() };
const normalize = (value) => String(value || '').toLowerCase().trim();

const isActionHeader = (label, index, total) =>
  index === total - 1 && /^(acc\\.?|accion|acciones|opciones)$/.test(normalize(label));
assert.ok(isActionHeader('Acc.', 4, 5), 'Compacted header must still qualify on subsequent passes');

const compactActionColumn = vm.runInNewContext(
  functionSource + '\ncompactActionColumn;',
  {
    headers: () => ['ID', 'Origen', 'Destino', 'Tag', heading.textContent],
    normalize,
    isActionHeader: (label, index, total) =>
      index === total - 1 && /^(acc\.?|accion|acciones|opciones)$/.test(normalize(label))
  }
);

compactActionColumn(table, wrapper);
assert.equal(edit.textContent, '✏', 'Edit action must be replaced with icon');
assert.equal(remove.textContent, '🗑', 'Delete action must be replaced with icon');
assert.equal(edit.writes, 1, 'Edit button must be mutated once on initial render');
assert.equal(remove.writes, 1, 'Delete button must be mutated once on initial render');

compactActionColumn(table, wrapper);
assert.equal(edit.writes, 1, 'Repeated refresh must NOT mutate an unchanged Edit icon');
assert.equal(remove.writes, 1, 'Repeated refresh must NOT mutate an unchanged Delete icon');

const editMarkup = relations.match(/data-edit="1"[\s\S]*?>Editar<\/button>/);
assert.ok(editMarkup, 'Relations edit button must exist');
assert.doesNotMatch(editMarkup[0], /onclick\s*=/, 'Do not inline-bind the edit button twice');
assert.match(relations, /btn\.addEventListener\('click', function\(\)\{ openRelModal\(btn\); \}\)/,
  'Delegated edit-button initialization must remain');

console.log('Issue #68: idempotent compact actions and single modal binding PASS');
