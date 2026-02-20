<?php
// admin_chapters.php
if (!isset($link) || !$link) { die('Error de conexion a la base de datos.'); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function norm_date($v){ $v = trim((string)$v); return $v === '' ? null : $v; }
function parse_int_list($raw){
    $raw = (string)$raw;
    if (trim($raw) === '') return [];
    $parts = preg_split('/[\s,]+/', $raw);
    $out = [];
    foreach ($parts as $p) {
        if ($p === '' || !preg_match('/^\d+$/', $p)) continue;
        $n = (int)$p;
        if ($n > 0) $out[$n] = $n;
    }
    return array_values($out);
}
function attach_chapter_characters(mysqli $link, int $chapterId, array $characterIds): int {
    if ($chapterId <= 0 || empty($characterIds)) return 0;
    $added = 0;
    $chk = $link->prepare('SELECT id FROM bridge_chapters_characters WHERE chapter_id = ? AND character_id = ? LIMIT 1');
    $ins = $link->prepare('INSERT INTO bridge_chapters_characters (chapter_id, character_id) VALUES (?, ?)');
    if (!$chk || !$ins) {
        if ($chk) $chk->close();
        if ($ins) $ins->close();
        return 0;
    }
    foreach ($characterIds as $characterId) {
        $characterId = (int)$characterId;
        if ($characterId <= 0) continue;
        $chk->bind_param('ii', $chapterId, $characterId);
        $chk->execute();
        $rs = $chk->get_result();
        $exists = $rs && $rs->fetch_assoc() ? true : false;
        if (!$exists) {
            $ins->bind_param('ii', $chapterId, $characterId);
            if ($ins->execute()) $added++;
        }
    }
    $chk->close();
    $ins->close();
    return $added;
}

// AJAX in same controller (standard admin pattern)
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['ok' => false, 'error' => 'Metodo invalido']);
        exit;
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'get_relations') {
        $chapterId = (int)($_POST['chapter_id'] ?? 0);
        $rows = [];
        if ($chapterId > 0 && ($clean = $link->prepare("
            DELETE b1
            FROM bridge_chapters_characters b1
            INNER JOIN bridge_chapters_characters b2
                ON b1.chapter_id = b2.chapter_id
               AND b1.character_id = b2.character_id
               AND b1.id > b2.id
            WHERE b1.chapter_id = ?
        "))) {
            $clean->bind_param('i', $chapterId);
            $clean->execute();
            $clean->close();
        }
        if ($chapterId > 0 && ($st = $link->prepare("SELECT b.id, b.character_id, c.name, ch.name AS chronicle_name FROM bridge_chapters_characters b JOIN fact_characters c ON c.id = b.character_id LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id WHERE b.chapter_id = ? ORDER BY c.name ASC, c.id ASC"))) {
            $st->bind_param('i', $chapterId);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
            $st->close();
        }
        echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'add_relation') {
        $chapterId = (int)($_POST['chapter_id'] ?? 0);
        $characterId = (int)($_POST['character_id'] ?? 0);
        $ok = false;
        if ($chapterId > 0 && $characterId > 0) {
            $exists = false;
            if ($chk = $link->prepare('SELECT id FROM bridge_chapters_characters WHERE chapter_id = ? AND character_id = ? LIMIT 1')) {
                $chk->bind_param('ii', $chapterId, $characterId);
                $chk->execute();
                $rs = $chk->get_result();
                $exists = $rs && $rs->fetch_assoc() ? true : false;
                $chk->close();
            }
            if (!$exists && ($st = $link->prepare('INSERT INTO bridge_chapters_characters (chapter_id, character_id) VALUES (?, ?)'))) {
                $st->bind_param('ii', $chapterId, $characterId);
                $ok = $st->execute();
                $st->close();
            } else {
                $ok = true;
            }
        }
        echo json_encode(['ok' => (bool)$ok]);
        exit;
    }

    if ($action === 'del_relation') {
        $relId = (int)($_POST['rel_id'] ?? 0);
        $ok = false;
        if ($relId > 0 && ($st = $link->prepare('DELETE FROM bridge_chapters_characters WHERE id = ?'))) {
            $st->bind_param('i', $relId);
            $ok = $st->execute();
            $st->close();
        }
        echo json_encode(['ok' => (bool)$ok]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Accion no valida']);
    exit;
}

$flash = [];

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0 && ($st = $link->prepare('DELETE FROM dim_chapters WHERE id = ?'))) {
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        $flash[] = ['type' => 'ok', 'msg' => 'Capitulo eliminado.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_chapter'])) {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $chapterNumber = (int)($_POST['chapter_number'] ?? 0);
    $seasonNumber = (int)($_POST['season_number'] ?? 0);
    $playedDate = norm_date($_POST['played_date'] ?? '');
    $ingameDate = norm_date($_POST['in_game_date'] ?? '');
    $synopsis = hg_mentions_convert($link, trim((string)($_POST['synopsis'] ?? '')));
    $pendingCharacterIds = parse_int_list($_POST['pending_character_ids'] ?? '');

    if ($name === '' || $chapterNumber <= 0 || $seasonNumber <= 0) {
        $flash[] = ['type' => 'err', 'msg' => 'Nombre, capitulo y temporada son obligatorios.'];
    } else {
        if ($id > 0) {
            $sql = 'UPDATE dim_chapters SET name=?, chapter_number=?, season_number=?, played_date=?, in_game_date=?, synopsis=?, updated_at=NOW() WHERE id=?';
            $st = $link->prepare($sql);
            $st->bind_param('siisssi', $name, $chapterNumber, $seasonNumber, $playedDate, $ingameDate, $synopsis, $id);
            $ok = $st->execute();
            $st->close();
            if ($ok) {
                hg_update_pretty_id_if_exists($link, 'dim_chapters', $id, $name);
                attach_chapter_characters($link, $id, $pendingCharacterIds);
                $flash[] = ['type' => 'ok', 'msg' => 'Capitulo actualizado.'];
            }
        } else {
            $sql = 'INSERT INTO dim_chapters (name, chapter_number, season_number, played_date, in_game_date, synopsis, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())';
            $st = $link->prepare($sql);
            $st->bind_param('siisss', $name, $chapterNumber, $seasonNumber, $playedDate, $ingameDate, $synopsis);
            $ok = $st->execute();
            $newId = (int)$link->insert_id;
            $st->close();
            if ($ok) {
                hg_update_pretty_id_if_exists($link, 'dim_chapters', $newId, $name);
                attach_chapter_characters($link, $newId, $pendingCharacterIds);
                $flash[] = ['type' => 'ok', 'msg' => 'Capitulo creado.'];
            }
        }
    }
}

$personajes = [];
if ($rs = $link->query('SELECT p.id, p.name, COALESCE(ch.name, "") AS chronicle_name FROM fact_characters p LEFT JOIN dim_chronicles ch ON ch.id = p.chronicle_id ORDER BY p.name ASC, p.id ASC')) {
    while ($r = $rs->fetch_assoc()) { $personajes[] = $r; }
    $rs->close();
}

$temporadasCatalogo = [];
if ($rs = $link->query('SELECT season_number, name FROM dim_seasons ORDER BY season_number ASC')) {
    while ($r = $rs->fetch_assoc()) { $temporadasCatalogo[] = $r; }
    $rs->close();
}

$chapters = [];
if ($rs = $link->query("SELECT c.id, c.name, c.chapter_number, c.season_number, c.played_date, c.in_game_date, c.synopsis, s.name AS season_name FROM dim_chapters c LEFT JOIN dim_seasons s ON s.season_number = c.season_number ORDER BY c.season_number ASC, c.chapter_number ASC")) {
    while ($r = $rs->fetch_assoc()) { $chapters[] = $r; }
    $rs->close();
}

$actions = '<span style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">'
    . '<label style="text-align:left;">Filtro rapido <input class="inp" type="text" id="quickFilter" placeholder="Nombre..."></label>'
    . '<label style="text-align:left;">Temporada <select id="seasonFilter" class="select"><option value="">Todas</option>';
foreach ($temporadasCatalogo as $t) {
    $actions .= '<option value="' . (int)$t['season_number'] . '">' . h($t['name']) . '</option>';
}
$actions .= '</select></label>'
    . '<button class="btn btn-green" type="button" onclick="openChapterModal(0)">+ Nuevo capitulo</button>'
    . '</span>';

include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
admin_panel_open('Capitulos', $actions);
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m): ?>
    <div class="<?= h($m['type']) ?>"><?= h($m['msg']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<table class="table" id="chaptersTable">
    <thead>
        <tr>
            <th>Temporada</th>
            <th>#</th>
            <th>Nombre</th>
            <th>Fecha</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
<div id="chaptersPager" class="pager" style="justify-content:flex-end;"></div>

<div class="chap-modal-back" id="chapterModalBack" aria-hidden="true">
    <div class="chap-modal" style="width:min(980px,96vw)">
        <h3 id="chapterModalTitle">Capitulo</h3>
        <form method="post" action="/talim?s=admin_chapters" id="chapterForm">
            <input type="hidden" name="save_chapter" value="1">
            <input type="hidden" name="id" id="f_id" value="0">
            <input type="hidden" name="pending_character_ids" id="f_pending_character_ids" value="">

            <div class="grid">
                <label>Nombre
                    <input class="inp" type="text" name="name" id="f_name" required>
                </label>
                <label>Capitulo
                    <input class="inp" type="number" min="1" name="chapter_number" id="f_chapter" required>
                </label>
                <label>Temporada
                    <select class="select" name="season_number" id="f_season" required>
                        <?php foreach ($temporadasCatalogo as $t): ?>
                        <option value="<?= (int)$t['season_number'] ?>"><?= h($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Fecha jugada
                    <input class="inp" type="date" name="played_date" id="f_played">
                </label>
                <label>Fecha in-game
                    <input class="inp" type="date" name="in_game_date" id="f_ingame">
                </label>
                <label style="grid-column:1/-1">Sinopsis
                    <div>
                        <div id="chapter_synopsis_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
                        <div id="chapter_synopsis_editor" class="ql-container ql-snow"></div>
                        <textarea class="ta" name="synopsis" id="f_synopsis" rows="8" style="display:none;"></textarea>
                    </div>
                </label>
            </div>

            <div class="box-like" style="margin-top:12px; border:1px solid #000088; border-radius:10px; padding:10px;">
                <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                    <strong>Participantes</strong>
                    <select id="characterSelect" class="select" style="max-width:360px;">
                        <option value="">Seleccionar personaje</option>
                        <?php foreach ($personajes as $pj): ?>
                        <option value="<?= (int)$pj['id'] ?>"><?= h($pj['name']) ?> (#<?= (int)$pj['id'] ?><?= $pj['chronicle_name'] !== '' ? ' - "' . h($pj['chronicle_name']) . '"' : '' ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn" type="button" id="btnAddRel">Agregar</button>
                </div>
                <div id="relationsList" class="small">Sin participantes.</div>
            </div>

            <div class="modal-actions">
                <button class="btn btn-green" type="submit">Guardar</button>
                <button class="btn" type="button" onclick="closeChapterModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<link href="/assets/vendor/quill/1.3.7/quill.snow.css" rel="stylesheet">
<script src="/assets/vendor/quill/1.3.7/quill.min.js"></script>
<script>
const chapters = <?= json_encode($chapters, JSON_UNESCAPED_UNICODE); ?>;
const charactersCatalog = <?= json_encode($personajes, JSON_UNESCAPED_UNICODE); ?>;
const CHAPTER_MENTION_TYPES = ['character','season','episode','organization','group','gift','rite','totem','discipline','item','trait','background','merit','flaw','merydef','doc'];
let page = 1;
const pageSize = 20;
let currentId = 0;
let pendingRelationCharacterIds = [];
const characterById = new Map((charactersCatalog || []).map(c => [Number(c.id), c]));
let chapterSynopsisEditor = null;

function esc(s){
    if (!s) return '';
    return String(s).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;');
}

function filteredChapters(){
    const q = (document.getElementById('quickFilter').value || '').toLowerCase();
    const sf = document.getElementById('seasonFilter').value;
    return chapters.filter(c => {
        const okName = (c.name || '').toLowerCase().includes(q);
        const okSeason = (sf === '' || String(c.season_number) === sf);
        return okName && okSeason;
    });
}

function renderTable(){
    const rows = filteredChapters();
    const tbody = document.querySelector('#chaptersTable tbody');
    tbody.innerHTML = '';

    const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
    if (page > totalPages) page = totalPages;
    const start = (page - 1) * pageSize;
    const end = Math.min(start + pageSize, rows.length);

    for (let i = start; i < end; i++) {
        const c = rows[i];
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${esc(c.season_name || ('Temporada ' + c.season_number))}</td>
            <td>${esc(c.chapter_number)}</td>
            <td>${esc(c.name)}</td>
            <td>${esc(c.played_date || '')}</td>
            <td>
                <button class="btn" type="button" onclick="openChapterModal(${Number(c.id)})">Editar</button>
                <a class="btn btn-red" href="/talim?s=admin_chapters&delete=${Number(c.id)}" onclick="return confirm('Eliminar este capitulo?')">Borrar</a>
            </td>`;
        tbody.appendChild(tr);
    }

    const pager = document.getElementById('chaptersPager');
    pager.innerHTML = '';
    if (totalPages <= 1) return;
    if (page > 1) {
        pager.innerHTML += `<button class="btn" type="button" onclick="goPage(${page-1})">Anterior</button>`;
    }
    pager.innerHTML += `<span class="cur">${page}/${totalPages}</span>`;
    if (page < totalPages) {
        pager.innerHTML += `<button class="btn" type="button" onclick="goPage(${page+1})">Siguiente</button>`;
    }
}

function goPage(n){ page = n; renderTable(); }

function chapterById(id){
    return chapters.find(c => Number(c.id) === Number(id)) || null;
}

function ensureChapterSynopsisEditor(){
    if (chapterSynopsisEditor || !window.Quill) return chapterSynopsisEditor;
    const editor = document.getElementById('chapter_synopsis_editor');
    const toolbar = document.getElementById('chapter_synopsis_toolbar');
    if (!editor || !toolbar) return null;
    chapterSynopsisEditor = new Quill('#chapter_synopsis_editor', { theme:'snow', modules:{ toolbar:'#chapter_synopsis_toolbar' } });
    if (window.hgMentions) { window.hgMentions.attachQuill(chapterSynopsisEditor, { types: CHAPTER_MENTION_TYPES }); }
    return chapterSynopsisEditor;
}

function characterLabelById(id){
    const c = characterById.get(Number(id));
    if (!c) return `#${Number(id)}`;
    const chron = c.chronicle_name ? ` - "${c.chronicle_name}"` : '';
    return `${c.name} (#${Number(c.id)}${chron})`;
}

function syncPendingField(){
    document.getElementById('f_pending_character_ids').value = pendingRelationCharacterIds.join(',');
}

function renderPendingRelations(){
    const box = document.getElementById('relationsList');
    if (!pendingRelationCharacterIds.length) {
        box.textContent = 'Sin participantes (se guardaran al crear el capitulo).';
        return;
    }
    let html = '<ul style="margin:0; padding-left:18px;">';
    for (const cid of pendingRelationCharacterIds) {
        html += `<li>${esc(characterLabelById(cid))} <button class="btn btn-red" style="padding:2px 6px; font-size:10px;" type="button" onclick="removePendingRelation(${Number(cid)})">Quitar</button></li>`;
    }
    html += '</ul>';
    box.innerHTML = html;
}

function openChapterModal(id){
    ensureChapterSynopsisEditor();
    currentId = Number(id || 0);
    pendingRelationCharacterIds = [];
    syncPendingField();
    const c = chapterById(currentId);

    document.getElementById('f_id').value = c ? c.id : 0;
    document.getElementById('f_name').value = c ? (c.name || '') : '';
    document.getElementById('f_chapter').value = c ? (c.chapter_number || '') : '';
    document.getElementById('f_season').value = c ? String(c.season_number || '') : (document.getElementById('seasonFilter').value || '');
    document.getElementById('f_played').value = c ? (c.played_date || '') : '';
    document.getElementById('f_ingame').value = c ? (c.in_game_date || '') : '';
    const synopsisHtml = c ? (c.synopsis || '') : '';
    document.getElementById('f_synopsis').value = synopsisHtml;
    if (chapterSynopsisEditor) chapterSynopsisEditor.root.innerHTML = synopsisHtml;
    document.getElementById('chapterModalTitle').textContent = c ? 'Editar capitulo' : 'Nuevo capitulo';

    document.getElementById('chapterModalBack').style.display = 'flex';
    loadRelations();
}

function closeChapterModal(){
    document.getElementById('chapterModalBack').style.display = 'none';
}

async function postAjax(data){
    const body = new URLSearchParams(data);
    const res = await fetch('/talim?s=admin_chapters&ajax=1', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body
    });
    return res.json();
}

async function loadRelations(){
    const box = document.getElementById('relationsList');
    if (!currentId) {
        renderPendingRelations();
        return;
    }
    try {
        const data = await postAjax({ action: 'get_relations', chapter_id: currentId });
        if (!data.ok) { box.textContent = 'No se pudieron cargar participantes.'; return; }
        if (!data.data || !data.data.length) { box.textContent = 'Sin participantes.'; return; }
        let html = '<ul style="margin:0; padding-left:18px;">';
        for (const rel of data.data) {
            const chron = rel.chronicle_name ? ` - "${rel.chronicle_name}"` : '';
            html += `<li>${esc(rel.name)} (#${Number(rel.character_id)}${esc(chron)}) <button class="btn btn-red" style="padding:2px 6px; font-size:10px;" type="button" onclick="removeRelation(${Number(rel.id)})">Quitar</button></li>`;
        }
        html += '</ul>';
        box.innerHTML = html;
    } catch (e) {
        box.textContent = 'Error al cargar participantes.';
    }
}

async function addRelation(){
    const characterId = Number(document.getElementById('characterSelect').value || 0);
    if (!characterId) return;

    if (!currentId) {
        if (!pendingRelationCharacterIds.includes(characterId)) {
            pendingRelationCharacterIds.push(characterId);
            syncPendingField();
        }
        document.getElementById('characterSelect').value = '';
        renderPendingRelations();
        return;
    }

    try {
        const data = await postAjax({ action: 'add_relation', chapter_id: currentId, character_id: characterId });
        if (data.ok) {
            document.getElementById('characterSelect').value = '';
            loadRelations();
        }
    } catch (e) {
        // no-op: loadRelations already reports recoverable failures
    }
}

function removePendingRelation(characterId){
    pendingRelationCharacterIds = pendingRelationCharacterIds.filter(id => Number(id) !== Number(characterId));
    syncPendingField();
    renderPendingRelations();
}

async function removeRelation(relId){
    const data = await postAjax({ action: 'del_relation', rel_id: relId });
    if (data.ok) loadRelations();
}

document.getElementById('quickFilter').addEventListener('input', () => { page = 1; renderTable(); });
document.getElementById('seasonFilter').addEventListener('change', () => { page = 1; renderTable(); });
document.getElementById('btnAddRel').addEventListener('click', addRelation);
document.getElementById('chapterForm').addEventListener('submit', () => {
    if (chapterSynopsisEditor) {
        const html = chapterSynopsisEditor.root.innerHTML || '';
        const plain = (chapterSynopsisEditor.getText() || '').replace(/\s+/g, ' ').trim();
        document.getElementById('f_synopsis').value = plain ? html : '';
    }
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeChapterModal();
});

renderTable();
</script>
<?php include_once(__DIR__ . '/../../partials/admin/mentions_includes.php'); ?>

<style>
.chap-modal-back{
    position: fixed;
    inset: 0;
    z-index: 12000;
    background: rgba(0,0,0,.6);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 14px;
}
.chap-modal{
    max-height: 92vh;
    overflow: auto;
    background: #05014E;
    border: 1px solid #000088;
    border-radius: 12px;
    padding: 12px;
}
.ql-toolbar.ql-snow{
    border:1px solid #000088 !important;
    background:#050b36 !important;
    border-radius:8px 8px 0 0;
}
.ql-container.ql-snow{
    border:1px solid #000088 !important;
    border-top:none !important;
    background:#000033 !important;
    color:#fff !important;
    border-radius:0 0 8px 8px;
}
.ql-editor{ min-height:220px; font-size:12px; }
.ql-snow .ql-stroke{ stroke:#cfe !important; }
.ql-snow .ql-fill{ fill:#cfe !important; }
.ql-snow .ql-picker{ color:#cfe !important; }
.ql-snow .ql-picker-options{
    background:#050b36 !important;
    border:1px solid #000088 !important;
}
.ql-snow .ql-picker-item{ color:#cfe !important; }
</style>

<?php admin_panel_close(); ?>
