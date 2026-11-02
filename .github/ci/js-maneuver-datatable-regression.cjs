#!/usr/bin/env node
'use strict';

/* #45 follow-up: the generic column picker must not mistake "Acciones" for
 * the Maneuver name. Exercise the real inference algorithm and HTML headings.
 * Shared filters must use the same multiselect pattern as Gifts/Characters. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
const page = read('app/controllers/docs/maneuver_list.php');
const source = read('assets/js/hg-datatables.js');

const headerMatch = [...page.matchAll(/<th>([^<]+)<\/th>/g)].map(match => match[1].trim());
assert.deepEqual(
    headerMatch,
    ['Nombre', 'Sistema de origen', 'Tirada', 'Dificultad', 'Daño', 'Acciones'],
    'Maneuver catalogue should follow the standard table heading layout'
);
assert.match(page, /class="display docs-table"/, 'Reuse shared documentation table style');
assert.match(page, /class="ms-wrap" id="filter-system"/, 'Use shared multiselect control');
assert.match(page, /HGDataTableFilters\.create\s*\(/, 'Use shared DataTable filters');
assert.match(page, /source:\s*'system_name',\s*column:\s*1/, 'Filter the actual System column');
assert.doesNotMatch(page, /hg-maneuvers-system-filter/, 'Do not use the old standalone select');

const profilesStart = source.indexOf('    var coreColumnProfiles = ');
const profilesEnd = source.indexOf('    function clearLegacyMultiselectInlineStyles(');
const logicStart = source.indexOf('    function normalizeLabel(');
const logicEnd = source.indexOf('    function markWideDataTable(');
assert.ok(profilesStart >= 0 && profilesEnd > profilesStart);
assert.ok(logicStart >= 0 && logicEnd > logicStart);
const inferCoreColumns = vm.runInNewContext(
    source.slice(profilesStart, profilesEnd)
    + '\n' + source.slice(logicStart, logicEnd)
    + '\ninferCoreColumns',
    {}
);

for (const firstColumnLabel of ['Nombre', 'Maniobra']) {
    const headers = [firstColumnLabel, ...headerMatch.slice(1)];
    const api = {
        columns: () => ({
            header: () => ({ toArray: () => headers.map(textContent => ({ textContent })) })
        })
    };
    const visibleIndexes = [0, 1, 2, 3, 4, 5];
    const defaultIndexes = Array.from(inferCoreColumns({ id: 'tabla-maniobras' }, api, visibleIndexes));
    assert.ok(defaultIndexes.includes(0), firstColumnLabel + ': name must be visible by default');
    assert.ok(defaultIndexes.includes(1), firstColumnLabel + ': origin System stays available');
    assert.ok(defaultIndexes.includes(2), firstColumnLabel + ': roll stays available');
    assert.ok(!defaultIndexes.includes(5), firstColumnLabel + ': Actions must not be mistaken for the name');
}
console.log('Maneuver catalogue table layout/default columns regression: PASS');
