#!/usr/bin/env node
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const read = (filename) => fs.readFileSync(path.join(root, filename), 'utf8');

// Exercise the real multiselect functions with small DOM doubles, rather than
// testing only source strings: labels are for humans and numeric IDs for transport.
const widgetSource = read('assets/js/hg-mobile-multifilters.js');
const start = widgetSource.indexOf('    function normalize(value) {');
const end = widgetSource.indexOf('    function initClientList(list) {');
assert.ok(start > 0 && end > start, 'Mobile multiselect helpers must be extractable');

function node(tag) {
    return {
        tag,
        children: [],
        attributes: {},
        textContent: '',
        value: '',
        append(...items) { this.children.push(...items); },
        appendChild(item) { this.children.push(item); },
        setAttribute(name, value) { this.attributes[name] = value; },
        set innerHTML(_) { this.children = []; },
        get innerHTML() { return ''; }
    };
}
function select(key, choices) {
    return {
        options: [{ value: '', textContent: 'Todos', selected: false }, ...choices],
        dataset: { mobileFilterKey: key },
        getAttribute: () => null,
        appendChild(option) { this.options.push(option); },
        set innerHTML(_) { this.options = []; }
    };
}
const { buildEntry, renderWidget } = vm.runInNewContext(
    widgetSource.slice(start, end) + '\n({ buildEntry, renderWidget });',
    { document: { createElement: node } }
);
const cases = [
    ['type', '1', 'Garou'],
    ['group', '5', 'Manada Aurora'],
    ['organization', '6', 'Clan de la Luna'],
    ['system', '10', 'Hombre Lobo'],
    ['status', '3', 'Activo']
];
cases.forEach(([key, id, label]) => {
    const control = select(key, [{ value: id, textContent: label, selected: true }]);
    const entry = buildEntry(control, 0, 'server');
    entry.optionsNode = node('div');
    entry.summary = node('span');
    renderWidget(entry);

    assert.equal(entry.optionsNode.children[0].children[1].textContent, label, key + ': visible label');
    assert.equal(entry.summary.textContent, label, key + ': selection summary');
    assert.equal(control.options[1].textContent, label, key + ': reconstructed select label');
    assert.equal(control.options[1].value, id, key + ': option keeps ID');
    assert.equal(control.options[1].selected, true, key + ': selection preserved');
    assert.equal(entry.available.get(id), id, key + ': submitted value stays numeric');
});

// Client-side facets still support plain-text option values.
const clientControl = select('value', [{ value: 'Espíritu', textContent: 'Espíritu', selected: true }]);
const client = buildEntry(clientControl, 0, 'client');
client.optionsNode = node('div');
client.summary = node('span');
renderWidget(client);
assert.equal(client.summary.textContent, 'Espíritu');

const mobileSearch = read('app/mobile/views/search.php');
const resultsPart = mobileSearch.slice(mobileSearch.indexOf('<?php if ($isResults): ?>'));
assert.ok(resultsPart.indexOf('class="hg-mobile-card-list"') < resultsPart.indexOf('data-mobile-search-recent'),
    'Mobile results must precede the recent-search block');
assert.match(resultsPart, /Búsquedas recientes/, 'Mobile recent-search heading');

const desktopSearch = read('app/controllers/main/main_search_result.php');
assert.ok(desktopSearch.indexOf('class="search-results-grid"') < desktopSearch.lastIndexOf('class="search-recent"'),
    'Desktop results must precede the recent-search block');
assert.match(desktopSearch, /Búsquedas recientes/, 'Desktop recent-search heading');

const forms = read('app/partials/bio/bio_page_section_05_forms.php');
assert.match(forms, /svg class="hgTabIcon"/, 'Forms tab needs its own vector icon');
assert.doesNotMatch(forms, /icon_character_sheet\.webp/, 'Forms icon must differ from Sheet');

const desktopSheet = read('app/partials/bio/bio_page_section_04_sheetup.php');
assert.match(desktopSheet, /bioPlayer\s*!==\s*48/, 'Desktop must hide the NPC player sentinel');
const mobileDetail = read('app/mobile/controllers/character_detail_data.php');
assert.match(mobileDetail, /\$playerId\s*===\s*48/, 'Mobile NPC behavior must be retained');

const catalogs = read('app/domains/characters/admin_queries.php');
assert.match(catalogs, /LEFT JOIN dim_bibliographies b ON b\.id = g\.bibliography_id/,
    'Gift catalog must resolve its publication');
assert.match(catalogs, /CONCAT\(' — ', b\.name\)/, 'Power label must show publication name');

const layout = read('assets/css/pages/admin-characters-layout.css');
assert.match(layout, /#resourceList \.res-chip \.pname/, 'Resource labels need dedicated sizing');
assert.match(layout, /#traitExtraList \.trait-chip \.pname/, 'Secondary-trait labels need dedicated sizing');
assert.match(read('app/controllers/admin/admin_characters.php'), /admin-characters-layout\.css/,
    'Character Admin must load its local CSS');

console.log('Quick UI issues #70 #57 #56 #55 #42 #40: regression assertions PASS');
