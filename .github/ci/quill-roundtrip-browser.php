<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/helpers/html_sanitizer.php';

$fixtures = [
    'baseline' => '<h2>Crónica</h2><p><strong>Negrita</strong> <em>Cursiva</em> <a href="/characters/ejemplo">Enlace</a></p>',
    'rich' => '<p>Antes <a href="/characters/ejemplo" class="hg-mention" data-type="character" data-id="42" target="_blank">Personaje</a></p>'
        . '<table><tbody><tr><th colspan="2">Título</th></tr><tr><td rowspan="2">A</td><td>B</td></tr><tr><td>C</td></tr></tbody></table><p>Final</p>',
];
$fixtures = array_map('hg_sanitize_rich_html', $fixtures);
?>
<!doctype html>
<html lang="es"><head><meta charset="UTF-8"><title>HG Quill browser regression</title>
<link rel="stylesheet" href="assets/vendor/quill/1.3.7/quill.snow.css"></head><body>
<div id="editors"></div><pre id="result">{"error":"browser did not execute"}</pre>
<script src="assets/vendor/quill/1.3.7/quill.min.js"></script>
<script>
(async function(){
    const fixtures = <?= json_encode($fixtures, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    const delay = () => new Promise(resolve => setTimeout(resolve, 30));
    const markers = root => ({
        headings: root.querySelectorAll('h2').length,
        bold: root.querySelectorAll('strong,b').length,
        italic: root.querySelectorAll('em,i').length,
        links: root.querySelectorAll('a[href="/characters/ejemplo"]').length,
        mentions: root.querySelectorAll('a.hg-mention[data-type="character"][data-id="42"]').length,
        tables: root.querySelectorAll('table').length,
        colspan: root.querySelectorAll('th[colspan="2"]').length,
        rowspan: root.querySelectorAll('td[rowspan="2"]').length,
        cells: root.querySelectorAll('td,th').length,
        text: root.textContent.replace(/\s+/g,' ').trim()
    });
    const report = {version: window.Quill && Quill.version};
    try {
        for (const [name, source] of Object.entries(fixtures)) {
            const input = document.createElement('div');
            input.innerHTML = source;
            const host = document.createElement('div');
            document.getElementById('editors').appendChild(host);
            const editor = new Quill(host, {theme:'snow', modules:{toolbar:false}});
            // Reproduce the existing application loading method.
            editor.root.innerHTML = source;
            await delay();
            const loaded = markers(editor.root);
            // Reproduce a small ordinary edit followed by reading root.innerHTML.
            editor.insertText(0, 'X', 'user');
            await delay();
            const edited = markers(editor.root);
            const expected = markers(input);
            const structureKeys = Object.keys(expected).filter(k => k !== 'text');
            const loadedOk = structureKeys.every(k => expected[k] === loaded[k]) && expected.text === loaded.text;
            const editedOk = structureKeys.every(k => expected[k] === edited[k]) && edited.text === 'X' + expected.text;
            report[name] = {expected, loaded, edited, loaded_ok:loadedOk, edited_ok:editedOk};
            report[name + '_ok'] = loadedOk && editedOk;
        }
    } catch (error) {
        report.error = String(error);
    }
    document.getElementById('result').textContent = JSON.stringify(report);
})();
</script></body></html>
