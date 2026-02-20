<?php
// admin_seasons.php - CRUD Temporadas (dim_seasons)
if (!isset($link) || !$link) { die("Error de conexion a la base de datos."); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
include_once(__DIR__ . '/../../helpers/pretty.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function slugify_season_pretty(string $text): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (function_exists('iconv')) { $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text; }
    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);
    return $text;
}
function persist_season_pretty_id(mysqli $link, int $id, string $name): bool {
    if ($id <= 0) return false;
    $slug = slugify_season_pretty($name);
    if ($slug === '') $slug = (string)$id;
    $st = $link->prepare("UPDATE dim_seasons SET pretty_id=? WHERE id=?");
    if (!$st) return false;
    $st->bind_param('si', $slug, $id);
    $ok = $st->execute();
    $st->close();
    return (bool)$ok;
}
function short_text(string $s, int $n = 140): string {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/u', ' ', $s);
    if (mb_strlen($s) <= $n) return $s;
    return mb_substr($s, 0, $n) . '...';
}

$hasSeasonCol    = true; // season es NOT NULL en dim_seasons
$hasDescription  = true; // description es NOT NULL en dim_seasons
$hasOpening      = hg_table_has_column($link, 'dim_seasons', 'opening');
$hasMainCast     = hg_table_has_column($link, 'dim_seasons', 'main_cast');
$hasSortOrder    = true; // existe en dim_seasons
$hasFinished     = true; // existe en dim_seasons
$hasCreatedAt    = hg_table_has_column($link, 'dim_seasons', 'created_at');
$hasUpdatedAt    = hg_table_has_column($link, 'dim_seasons', 'updated_at');
$hasPrettyId     = true; // existe en dim_seasons

$actions = '<span style="margin-left:auto; display:flex; gap:8px; align-items:center;">'
    . '<button class="btn btn-green" type="button" onclick="openSeasonModal()">+ Nueva temporada</button>'
    . '<label style="text-align:left;">Filtro rapido '
    . '<input class="inp" type="text" id="quickFilterSeasons" placeholder="En esta pagina..."></label>'
    . '</span>';
admin_panel_open('Temporadas', $actions);

$flash = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    $action = (string)($_POST['crud_action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        if ($id > 0 && ($st = $link->prepare("DELETE FROM dim_seasons WHERE id=?"))) {
            $st->bind_param("i", $id);
            if ($st->execute()) $flash[] = ['type'=>'ok','msg'=>'Temporada eliminada.'];
            else $flash[] = ['type'=>'error','msg'=>'Error al eliminar: '.$st->error];
            $st->close();
        } else {
            $flash[] = ['type'=>'error','msg'=>'ID invalido para eliminar.'];
        }
    }

    if ($action === 'create' || $action === 'update') {
        $name = trim((string)($_POST['name'] ?? ''));
        $seasonNumber = (int)($_POST['season_number'] ?? 0);
        $seasonFlag = isset($_POST['season']) ? 1 : 0;
        $description = (string)($_POST['description'] ?? '');
        $opening = (string)($_POST['opening'] ?? '');
        $mainCast = (string)($_POST['main_cast'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $finished = isset($_POST['finished']) ? 1 : 0;

        if ($name === '') {
            $flash[] = ['type'=>'error','msg'=>'El nombre es obligatorio.'];
        } elseif ($seasonNumber <= 0) {
            $flash[] = ['type'=>'error','msg'=>'El numero de temporada debe ser mayor que 0.'];
        } else {
            $description = hg_mentions_convert($link, $description);
            $opening = hg_mentions_convert($link, $opening);
            $mainCast = hg_mentions_convert($link, $mainCast);

            if ($action === 'create') {
                $cols = ['name', 'season_number'];
                $vals = [$name, $seasonNumber];
                $types = 'si';

                $cols[] = 'season';      $vals[] = $seasonFlag; $types .= 'i';
                $cols[] = 'description'; $vals[] = $description; $types .= 's';
                if ($hasOpening)     { $cols[] = 'opening';     $vals[] = $opening; $types .= 's'; }
                if ($hasMainCast)    { $cols[] = 'main_cast';   $vals[] = $mainCast; $types .= 's'; }
                if ($hasSortOrder)   { $cols[] = 'sort_order';  $vals[] = $sortOrder; $types .= 'i'; }
                if ($hasFinished)    { $cols[] = 'finished';    $vals[] = $finished; $types .= 'i'; }

                if ($hasCreatedAt) $cols[] = 'created_at';
                if ($hasUpdatedAt) $cols[] = 'updated_at';

                $ph = [];
                foreach ($cols as $c) {
                    if ($c === 'created_at' || $c === 'updated_at') $ph[] = 'NOW()';
                    else $ph[] = '?';
                }

                $sql = "INSERT INTO dim_seasons (`".implode('`,`', $cols)."`) VALUES (".implode(',', $ph).")";
                $st = $link->prepare($sql);
                if (!$st) {
                    $flash[] = ['type'=>'error','msg'=>'Error al preparar INSERT: '.$link->error];
                } else {
                    $st->bind_param($types, ...$vals);
                    if ($st->execute()) {
                        $newId = (int)$link->insert_id;
                        $prettyOk = persist_season_pretty_id($link, $newId, $name);
                        $flash[] = ['type'=>$prettyOk ? 'ok' : 'error','msg'=>$prettyOk ? 'Temporada creada.' : 'Temporada creada, pero no se pudo guardar pretty_id.'];
                    } else {
                        $flash[] = ['type'=>'error','msg'=>'Error al crear: '.$st->error];
                    }
                    $st->close();
                }
            } else {
                if ($id <= 0) {
                    $flash[] = ['type'=>'error','msg'=>'ID invalido para actualizar.'];
                } else {
                    $sets = ['`name`=?', '`season_number`=?'];
                    $vals = [$name, $seasonNumber];
                    $types = 'si';

                    $sets[] = '`season`=?';      $vals[] = $seasonFlag; $types .= 'i';
                    $sets[] = '`description`=?'; $vals[] = $description; $types .= 's';
                    if ($hasOpening)     { $sets[] = '`opening`=?';     $vals[] = $opening; $types .= 's'; }
                    if ($hasMainCast)    { $sets[] = '`main_cast`=?';   $vals[] = $mainCast; $types .= 's'; }
                    if ($hasSortOrder)   { $sets[] = '`sort_order`=?';  $vals[] = $sortOrder; $types .= 'i'; }
                    if ($hasFinished)    { $sets[] = '`finished`=?';    $vals[] = $finished; $types .= 'i'; }
                    if ($hasUpdatedAt)   { $sets[] = '`updated_at`=NOW()'; }

                    $sql = "UPDATE dim_seasons SET ".implode(', ', $sets)." WHERE id=?";
                    $types .= 'i';
                    $vals[] = $id;

                    $st = $link->prepare($sql);
                    if (!$st) {
                        $flash[] = ['type'=>'error','msg'=>'Error al preparar UPDATE: '.$link->error];
                    } else {
                        $st->bind_param($types, ...$vals);
                        if ($st->execute()) {
                            $prettyOk = persist_season_pretty_id($link, $id, $name);
                            $flash[] = ['type'=>$prettyOk ? 'ok' : 'error','msg'=>$prettyOk ? 'Temporada actualizada.' : 'Temporada actualizada, pero no se pudo guardar pretty_id.'];
                        } else {
                            $flash[] = ['type'=>'error','msg'=>'Error al actualizar: '.$st->error];
                        }
                        $st->close();
                    }
                }
            }
        }
    }
}

$orderBy = $hasSortOrder ? 'sort_order ASC, season_number ASC' : 'season_number ASC';
$selectCols = ['id', 'name', 'season_number'];
$selectCols[] = 'season';
$selectCols[] = 'description';
if ($hasOpening)     $selectCols[] = 'opening';
if ($hasMainCast)    $selectCols[] = 'main_cast';
if ($hasSortOrder)   $selectCols[] = 'sort_order';
if ($hasFinished)    $selectCols[] = 'finished';
if ($hasPrettyId)    $selectCols[] = 'pretty_id';

$rows = [];
$rowsFull = [];
$rs = $link->query("SELECT ".implode(',', $selectCols)." FROM dim_seasons ORDER BY ".$orderBy);
if ($rs) {
    while ($r = $rs->fetch_assoc()) {
        $rows[] = $r;
        $rowsFull[] = $r;
    }
    $rs->close();
}
?>

<?php if (!empty($flash)): ?>
    <div class="flash">
        <?php foreach ($flash as $m):
            $cl = $m['type']==='ok'?'ok':($m['type']==='error'?'err':'info'); ?>
            <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.modal-back{
  position:fixed; inset:0;
  background:rgba(0,0,0,.6);
  display:none; align-items:center; justify-content:center;
  z-index:9999; padding:14px; box-sizing:border-box;
}
.modal{
  width:min(980px, 96vw);
  max-height:92vh;
  overflow:hidden;
  background:#05014E;
  border:1px solid #000088;
  border-radius:12px;
  padding:12px;
  position:relative;
  display:flex;
  flex-direction:column;
}
.modal form{ display:flex; flex-direction:column; flex:1; }
.modal-body{ flex:1; overflow:auto; padding-right:6px; min-height:0; }
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
.ql-editor{ min-height:140px; font-size:12px; }
.ql-snow .ql-stroke{ stroke:#cfe !important; }
.ql-snow .ql-fill{ fill:#cfe !important; }
.ql-snow .ql-picker{ color:#cfe !important; }
.ql-snow .ql-picker-options{
  background:#050b36 !important;
  border:1px solid #000088 !important;
}
.ql-snow .ql-picker-item{ color:#cfe !important; }
</style>

<div class="modal-back" id="seasonModal">
    <div class="modal">
        <h3 id="seasonModalTitle">Nueva temporada</h3>
        <form method="post" id="seasonForm">
            <input type="hidden" name="crud_action" id="season_action" value="create">
            <input type="hidden" name="id" id="season_id" value="0">
            <div class="modal-body">
                <div style="display:grid; grid-template-columns:1fr 2fr; gap:8px; align-items:center;">
                    <label>Nombre</label>
                    <input class="inp" type="text" name="name" id="season_name" maxlength="150" required>

                    <label>Numero de temporada</label>
                    <input class="inp" type="number" name="season_number" id="season_number" min="1" required>

                    <?php if ($hasSortOrder): ?>
                    <label>Orden</label>
                    <input class="inp" type="number" name="sort_order" id="season_sort_order" min="0">
                    <?php endif; ?>

                    <label>Historia personal</label>
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="season" id="season_flag" value="1">
                        <span>Si</span>
                    </label>

                    <?php if ($hasFinished): ?>
                    <label>Finalizada</label>
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="finished" id="season_finished" value="1">
                        <span>Si</span>
                    </label>
                    <?php endif; ?>

                    <label>Descripcion</label>
                    <div>
                        <div id="season_description_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
                        <div id="season_description_editor" class="ql-container ql-snow"></div>
                        <textarea class="ta" name="description" id="season_description" rows="8" style="display:none;"></textarea>
                    </div>

                    <?php if ($hasOpening): ?>
                    <label>Opening</label>
                    <div>
                        <div id="season_opening_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
                        <div id="season_opening_editor" class="ql-container ql-snow"></div>
                        <textarea class="ta" name="opening" id="season_opening" rows="3" style="display:none;"></textarea>
                    </div>
                    <?php endif; ?>

                    <?php if ($hasMainCast): ?>
                    <label>Protagonistas</label>
                    <div>
                        <div id="season_main_cast_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
                        <div id="season_main_cast_editor" class="ql-container ql-snow"></div>
                        <textarea class="ta" name="main_cast" id="season_main_cast" rows="4" style="display:none;"></textarea>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-green" type="submit">Guardar</button>
                <button class="btn" type="button" onclick="closeSeasonModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-back" id="seasonDeleteModal">
    <div class="modal" style="width:min(560px,96vw);">
        <h3>Confirmar borrado</h3>
        <div style="color:#cfe; font-size:12px; line-height:1.4; margin-bottom:10px;">
            Se eliminara la temporada y puede afectar al archivo de capitulos.
        </div>
        <form method="post" id="seasonDeleteForm" style="margin:0;">
            <input type="hidden" name="crud_action" value="delete">
            <input type="hidden" name="id" id="season_delete_id" value="0">
            <div class="modal-actions">
                <button type="button" class="btn" onclick="closeSeasonDeleteModal()">Cancelar</button>
                <button type="submit" class="btn btn-red">Borrar</button>
            </div>
        </form>
    </div>
</div>

<table class="table" id="tablaSeasons">
    <thead>
        <tr>
            <th style="width:60px;">ID</th>
            <th style="width:90px;">Numero</th>
            <th>Nombre</th>
            <?php if ($hasSortOrder): ?><th style="width:80px;">Orden</th><?php endif; ?>
            <?php if ($hasSeasonCol): ?><th style="width:90px;">Personal</th><?php endif; ?>
            <?php if ($hasFinished): ?><th style="width:90px;">Finalizada</th><?php endif; ?>
            <?php if ($hasPrettyId): ?><th style="width:220px;">Pretty ID</th><?php endif; ?>
            <?php if ($hasDescription): ?><th>Descripcion</th><?php endif; ?>
            <th style="width:160px;">Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <?php
            $search = trim((string)($r['name'] ?? '') . ' ' . (string)($r['season_number'] ?? '') . ' ' . (string)($r['description'] ?? ''));
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); } else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><?= (int)$r['id'] ?></td>
            <td><?= (int)($r['season_number'] ?? 0) ?></td>
            <td><?= h((string)($r['name'] ?? '')) ?></td>
            <?php if ($hasSortOrder): ?><td><?= (int)($r['sort_order'] ?? 0) ?></td><?php endif; ?>
            <td><?= !empty($r['season']) ? 'Si' : 'No' ?></td>
            <?php if ($hasFinished): ?><td><?= !empty($r['finished']) ? 'Si' : 'No' ?></td><?php endif; ?>
            <?php if ($hasPrettyId): ?><td><?= h((string)($r['pretty_id'] ?? '')) ?></td><?php endif; ?>
            <td><?= h(short_text(strip_tags((string)($r['description'] ?? '')), 20)) ?></td>
            <td>
                <button class="btn" type="button" onclick="openSeasonModal(<?= (int)$r['id'] ?>)">Editar</button>
                <button class="btn btn-red" type="button" onclick="openSeasonDeleteModal(<?= (int)$r['id'] ?>)">Borrar</button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="<?= 6 + ($hasSortOrder?1:0) + ($hasFinished?1:0) + ($hasPrettyId?1:0) ?>" style="color:#bbb;">(Sin temporadas)</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<link href="/assets/vendor/quill/1.3.7/quill.snow.css" rel="stylesheet">
<script src="/assets/vendor/quill/1.3.7/quill.min.js"></script>
<?php include_once(__DIR__ . '/../../partials/admin/mentions_includes.php'); ?>
<script>
const seasonsData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
const SEASON_MENTION_TYPES = ['character','season','episode','organization','group','gift','rite','totem','discipline','item','trait','background','merit','flaw','merydef','doc'];
const seasonEditors = {};

function ensureSeasonEditor(key){
    if (seasonEditors[key] || !window.Quill) return seasonEditors[key] || null;
    const editorSel = `#season_${key}_editor`;
    const toolbarSel = `#season_${key}_toolbar`;
    const editorEl = document.querySelector(editorSel);
    const toolbarEl = document.querySelector(toolbarSel);
    if (!editorEl || !toolbarEl) return null;
    const q = new Quill(editorSel, { theme:'snow', modules:{ toolbar: toolbarSel } });
    if (window.hgMentions) { window.hgMentions.attachQuill(q, { types: SEASON_MENTION_TYPES }); }
    seasonEditors[key] = q;
    return q;
}

function ensureSeasonEditors(){
    ensureSeasonEditor('description');
    ensureSeasonEditor('opening');
    ensureSeasonEditor('main_cast');
}

function setSeasonEditorHtml(key, html){
    const ta = document.getElementById(`season_${key}`);
    if (ta) ta.value = html || '';
    const q = ensureSeasonEditor(key);
    if (q) q.root.innerHTML = html || '';
}

function syncSeasonEditorToTextarea(key){
    const ta = document.getElementById(`season_${key}`);
    if (!ta) return;
    const q = seasonEditors[key];
    if (!q) return;
    const html = q.root.innerHTML || '';
    const plain = (q.getText() || '').replace(/\s+/g, ' ').trim();
    ta.value = plain ? html : '';
}

function openSeasonModal(id = null){
    ensureSeasonEditors();
    const modal = document.getElementById('seasonModal');
    document.getElementById('season_action').value = 'create';
    document.getElementById('season_id').value = '0';
    document.getElementById('season_name').value = '';
    document.getElementById('season_number').value = '1';
    const eSort = document.getElementById('season_sort_order'); if (eSort) eSort.value = '0';
    const eSeas = document.getElementById('season_flag'); if (eSeas) eSeas.checked = false;
    const eFin = document.getElementById('season_finished'); if (eFin) eFin.checked = false;
    setSeasonEditorHtml('description', '');
    setSeasonEditorHtml('opening', '');
    setSeasonEditorHtml('main_cast', '');

    if (id) {
        const row = seasonsData.find(r => parseInt(r.id, 10) === parseInt(id, 10));
        if (row) {
            document.getElementById('seasonModalTitle').textContent = 'Editar temporada';
            document.getElementById('season_action').value = 'update';
            document.getElementById('season_id').value = String(row.id || 0);
            document.getElementById('season_name').value = row.name || '';
            document.getElementById('season_number').value = String(parseInt(row.season_number || 0, 10) || 1);
            if (eSort) eSort.value = String(parseInt(row.sort_order || 0, 10) || 0);
            if (eSeas) eSeas.checked = parseInt(row.season || 0, 10) === 1;
            if (eFin) eFin.checked = parseInt(row.finished || 0, 10) === 1;
            setSeasonEditorHtml('description', row.description || '');
            setSeasonEditorHtml('opening', row.opening || '');
            setSeasonEditorHtml('main_cast', row.main_cast || '');
        }
    } else {
        document.getElementById('seasonModalTitle').textContent = 'Nueva temporada';
    }

    modal.style.display = 'flex';
}

function closeSeasonModal(){
    document.getElementById('seasonModal').style.display = 'none';
}

function openSeasonDeleteModal(id){
    document.getElementById('season_delete_id').value = String(parseInt(id || 0, 10) || 0);
    document.getElementById('seasonDeleteModal').style.display = 'flex';
}

function closeSeasonDeleteModal(){
    document.getElementById('seasonDeleteModal').style.display = 'none';
}

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
        closeSeasonModal();
        closeSeasonDeleteModal();
    }
});

document.getElementById('seasonForm').addEventListener('submit', function(){
    syncSeasonEditorToTextarea('description');
    syncSeasonEditorToTextarea('opening');
    syncSeasonEditorToTextarea('main_cast');
});
</script>

<script>
(function(){
    const input = document.getElementById('quickFilterSeasons');
    if (!input) return;
    input.addEventListener('input', function(){
        const q = (this.value || '').toLowerCase();
        document.querySelectorAll('#tablaSeasons tbody tr').forEach(function(tr){
            const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
            tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
        });
    });
})();
</script>

<?php admin_panel_close(); ?>
