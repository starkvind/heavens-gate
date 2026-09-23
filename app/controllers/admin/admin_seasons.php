<?php
// admin_seasons.php - CRUD Temporadas (dim_seasons)
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/admin_uploads.php');
include_once(__DIR__ . '/../../domains/chapters/admin_seasons.php');

$SEASON_UPLOADDIR = hg_admin_project_root() . '/public/img/seasons';
$SEASON_URLBASE = '/img/seasons';
if (!is_dir($SEASON_UPLOADDIR)) { @mkdir($SEASON_UPLOADDIR, 0775, true); }

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
function short_text(string $s, int $n = 140): string {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/u', ' ', $s);
    if (mb_strlen($s) <= $n) return $s;
    return mb_substr($s, 0, $n) . '...';
}
function season_kind_label(string $kind): string {
    $kind = trim($kind);
    if ($kind === 'inciso') return 'Inciso';
    if ($kind === 'historia_personal') return 'Historia personal';
    if ($kind === 'especial') return 'Especial';
    return 'Temporada';
}

$hasSeasonCol    = hg_table_has_column($link, 'dim_seasons', 'season_kind');
$hasDescription  = true; // description es NOT NULL en dim_seasons
$hasOpening      = hg_table_has_column($link, 'dim_seasons', 'opening');
$hasMainCast     = hg_table_has_column($link, 'dim_seasons', 'main_cast');
$hasSortOrder    = true; // existe en dim_seasons
$hasFinished     = true; // existe en dim_seasons
$hasSeasonKind   = hg_table_has_column($link, 'dim_seasons', 'season_kind');
$hasChronicleId  = hg_table_has_column($link, 'dim_seasons', 'chronicle_id');
$hasCreatedAt    = hg_table_has_column($link, 'dim_seasons', 'created_at');
$hasUpdatedAt    = hg_table_has_column($link, 'dim_seasons', 'updated_at');
$hasPrettyId     = true; // existe en dim_seasons
$hasImageUrl     = hg_table_has_column($link, 'dim_seasons', 'image_url');

$chronicleOptions = $hasChronicleId ? hg_seasons_admin_chronicle_options($link) : [];

$actions = '<span class="adm-flex-right-8">'
    . '<button class="btn btn-green" type="button" onclick="openSeasonModal()">+ Nueva temporada</button>'
    . '<label class="adm-text-left">Filtro rápido '
    . '<input class="inp" type="text" id="quickFilterSeasons" placeholder="En esta pagina..."></label>'
    . '</span>';
admin_panel_open('Temporadas', $actions);

$flash = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    $action = (string)($_POST['crud_action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $currentImage = ($hasImageUrl && $id > 0) ? hg_seasons_admin_current_image($link, $id) : '';

    if ($action === 'delete') {
        $result = hg_seasons_admin_delete($link, $id);
        if (!empty($result['ok']) && $currentImage !== '') {
            hg_admin_safe_unlink_upload($currentImage, $SEASON_UPLOADDIR);
        }
        $flash[] = ['type'=>!empty($result['ok'])?'ok':'error','msg'=>(string)$result['message']];
    }

    if ($action === 'create' || $action === 'update') {
        $name = trim((string)($_POST['name'] ?? ''));
        $seasonNumber = (int)($_POST['season_number'] ?? 0);
        $seasonKind = trim((string)($_POST['season_kind'] ?? ''));
        if ($seasonKind === '') {
            $seasonKind = 'temporada';
        }
        $seasonKindAllowed = ['temporada', 'inciso', 'historia_personal', 'especial'];
        if (!in_array($seasonKind, $seasonKindAllowed, true)) {
            $seasonKind = 'temporada';
        }
        $description = (string)($_POST['description'] ?? '');
        $opening = (string)($_POST['opening'] ?? '');
        $mainCast = (string)($_POST['main_cast'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $finished = isset($_POST['finished']) ? 1 : 0;
        $imageUrl = $hasImageUrl ? trim((string)($_POST['image_url'] ?? '')) : '';
        $removeImage = $hasImageUrl && ((int)($_POST['image_remove'] ?? 0) === 1);
        $hasImageUpload = $hasImageUrl && !empty($_FILES['image_upload']) && ((int)($_FILES['image_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
        if ($removeImage) {
            $imageUrl = '';
        }
        $chronicleId = $hasChronicleId ? (int)($_POST['chronicle_id'] ?? 0) : 0;
        if ($hasChronicleId && $chronicleId > 0 && !isset($chronicleOptions[$chronicleId])) {
            $chronicleId = 0;
        }

        if ($name === '') {
            $flash[] = ['type'=>'error','msg'=>'El nombre es obligatorio.'];
        } elseif ($seasonNumber <= 0) {
            $flash[] = ['type'=>'error','msg'=>'El numero de temporada debe ser mayor que 0.'];
        } else {
            $description = hg_mentions_convert($link, $description);
            $opening = hg_mentions_convert($link, $opening);
            $mainCast = hg_mentions_convert($link, $mainCast);

            $schema = [
                'season_kind'=>$hasSeasonKind,
                'chronicle_id'=>$hasChronicleId,
                'opening'=>$hasOpening,
                'main_cast'=>$hasMainCast,
                'sort_order'=>$hasSortOrder,
                'finished'=>$hasFinished,
                'image_url'=>$hasImageUrl,
                'created_at'=>$hasCreatedAt,
                'updated_at'=>$hasUpdatedAt,
                'pretty_id'=>$hasPrettyId,
            ];
            $values = [
                'name'=>$name,
                'season_number'=>$seasonNumber,
                'season_kind'=>$seasonKind,
                'chronicle_id'=>$chronicleId,
                'description'=>$description,
                'opening'=>$opening,
                'main_cast'=>$mainCast,
                'sort_order'=>$sortOrder,
                'finished'=>$finished,
                'image_url'=>$imageUrl,
            ];
            $result = ($action === 'create')
                ? hg_seasons_admin_create($link, $schema, $values)
                : hg_seasons_admin_update($link, $schema, $id, $values);

            if (empty($result['ok'])) {
                $flash[] = ['type'=>'error','msg'=>(string)$result['message']];
            } else {
                $targetId = (int)($result['id'] ?? $id);
                if ($hasImageUpload) {
                    $upload = hg_admin_save_image_upload($_FILES['image_upload'], 'season', $targetId, $name, $SEASON_UPLOADDIR, $SEASON_URLBASE);
                    if (!empty($upload['ok'])) {
                        if ($action === 'update' && $currentImage !== '') {
                            hg_admin_safe_unlink_upload($currentImage, $SEASON_UPLOADDIR);
                        }
                        hg_seasons_admin_set_image($link, $targetId, (string)$upload['url']);
                        $flash[] = ['type'=>'ok','msg'=>$action === 'create' ? 'Imagen de la temporada subida.' : 'Imagen de la temporada actualizada.'];
                    } elseif (($upload['msg'] ?? '') !== 'no_file') {
                        $flash[] = ['type'=>'error','msg'=>'Imagen no guardada: ' . (string)$upload['msg']];
                    }
                } elseif ($action === 'update' && $hasImageUrl && $currentImage !== '' && $currentImage !== $imageUrl) {
                    hg_admin_safe_unlink_upload($currentImage, $SEASON_UPLOADDIR);
                }
                $flash[] = ['type'=>!empty($result['pretty_ok']) ? 'ok' : 'error','msg'=>(string)$result['message']];
            }
        }
    }
}

$schema = [
    'season_kind'=>$hasSeasonKind,
    'chronicle_id'=>$hasChronicleId,
    'opening'=>$hasOpening,
    'main_cast'=>$hasMainCast,
    'sort_order'=>$hasSortOrder,
    'finished'=>$hasFinished,
    'pretty_id'=>$hasPrettyId,
    'image_url'=>$hasImageUrl,
];
$rows = hg_seasons_admin_rows($link, $schema);
$rowsFull = $rows;
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
.season-kind-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.2;
    border: 1px solid #0a4fa3;
    background: #0a2e66;
    color: #e7f3ff;
}
.season-kind--temporada { border-color: #0e64c7; background: #083679; color: #d9efff; }
.season-kind--inciso { border-color: #3f86d4; background: #1d4878; color: #d9f1ff; }
.season-kind--historia_personal { border-color: #0aa88f; background: #0b5a4e; color: #d8fff8; }
.season-kind--especial { border-color: #8b7cff; background: #3e2d8a; color: #ece8ff; }
.adm-thumb-hint { font-size: 10px; color: #9db5d3; }
</style>

<div class="modal-back" id="seasonModal">
    <div class="modal">
        <h3 id="seasonModalTitle">Nueva temporada</h3>
        <form method="post" id="seasonForm" enctype="multipart/form-data">
            <input type="hidden" name="crud_action" id="season_action" value="create">
            <input type="hidden" name="id" id="season_id" value="0">
            <div class="modal-body">
                <div class="adm-grid-1-2">
                    <label>Nombre</label>
                    <input class="inp" type="text" name="name" id="season_name" maxlength="150" required>

                    <label>Numero de temporada</label>
                    <input class="inp" type="number" name="season_number" id="season_number" min="1" required>

                    <?php if ($hasSortOrder): ?>
                    <label>Orden</label>
                    <input class="inp" type="number" name="sort_order" id="season_sort_order" min="0">
                    <?php endif; ?>

                    <label>Tipo</label>
                    <select class="select" name="season_kind" id="season_kind">
                        <option value="temporada">Temporada</option>
                        <option value="inciso">Inciso</option>
                        <option value="historia_personal">Historia personal</option>
                        <option value="especial">Especial</option>
                    </select>

                    <?php if ($hasChronicleId): ?>
                    <label>Cr&oacute;nica</label>
                    <select class="select" name="chronicle_id" id="season_chronicle_id">
                        <option value="0">(Sin cr&oacute;nica)</option>
                        <?php foreach ($chronicleOptions as $cid => $cname): ?>
                            <option value="<?= (int)$cid ?>"><?= h($cname) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>

                    <?php if ($hasImageUrl): ?>
                    <label>Imagen</label>
                    <div>
                        <input class="inp" type="text" name="image_url" id="season_image_url" maxlength="600" placeholder="/img/seasons/... o URL completa">
                        <div class="adm-thumb-hint">Puedes escribir una ruta manual o subir un fichero.</div>
                    </div>

                    <label>Subir imagen</label>
                    <div>
                        <input class="inp" type="file" name="image_upload" id="season_image_upload" accept="image/*">
                        <label class="adm-thumb-hint"><input type="checkbox" name="image_remove" id="season_image_remove" value="1"> Quitar imagen actual</label>
                    </div>
                    <?php endif; ?>

                    <?php if ($hasFinished): ?>
                    <label>Finalizada</label>
                    <label class="adm-flex-8-center">
                        <input type="checkbox" name="finished" id="season_finished" value="1">
                        <span>Sí</span>
                    </label>
                    <?php endif; ?>

                    <label>Descripción</label>
                    <div>
                        <div id="season_description_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
                        <div id="season_description_editor" class="ql-container ql-snow"></div>
                        <textarea class="ta adm-hidden" name="description" id="season_description" rows="8"></textarea>
                    </div>

                    <?php if ($hasOpening): ?>
                    <label>Opening</label>
                    <div>
                        <div id="season_opening_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
                        <div id="season_opening_editor" class="ql-container ql-snow"></div>
                        <textarea class="ta adm-hidden" name="opening" id="season_opening" rows="3"></textarea>
                    </div>
                    <?php endif; ?>

                    <?php if ($hasMainCast): ?>
                    <label>Protagonistas</label>
                    <div>
                        <div id="season_main_cast_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
                        <div id="season_main_cast_editor" class="ql-container ql-snow"></div>
                        <textarea class="ta adm-hidden" name="main_cast" id="season_main_cast" rows="4"></textarea>
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
    <div class="modal adm-modal-sm">
        <h3>Confirmar borrado</h3>
        <div class="adm-help-text">
            Se eliminará la temporada y puede afectar al archivo de capítulos.
        </div>
        <form method="post" id="seasonDeleteForm" class="adm-m-0">
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
            <th class="adm-w-60">ID</th>
            <th class="adm-w-90">Número</th>
            <th>Nombre</th>
            <?php if ($hasSortOrder): ?><th class="adm-w-80">Orden</th><?php endif; ?>
            <?php if ($hasSeasonCol): ?><th class="adm-w-140">Tipo</th><?php endif; ?>
            <?php if ($hasChronicleId): ?><th class="adm-w-180">Cr&oacute;nica</th><?php endif; ?>
            <?php if ($hasFinished): ?><th class="adm-w-90">Finalizada</th><?php endif; ?>
            <?php if ($hasPrettyId): ?><th class="adm-w-220">Pretty ID</th><?php endif; ?>
            <?php if ($hasImageUrl): ?><th class="adm-w-220">Imagen</th><?php endif; ?>
            <?php if ($hasDescription): ?><th>Descripción</th><?php endif; ?>
            <th class="adm-w-160">Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <?php
            $search = trim((string)($r['name'] ?? '') . ' ' . (string)($r['season_number'] ?? '') . ' ' . (string)($r['chronicle_name'] ?? '') . ' ' . (string)($r['description'] ?? ''));
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); } else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><?= (int)$r['id'] ?></td>
            <td><?= (int)($r['season_number'] ?? 0) ?></td>
            <td><?= h((string)($r['name'] ?? '')) ?></td>
            <?php if ($hasSortOrder): ?><td><?= (int)($r['sort_order'] ?? 0) ?></td><?php endif; ?>
            <?php $k = (string)($r['season_kind'] ?? 'temporada'); ?>
            <?php if ($hasSeasonCol): ?><td><span class="season-kind-badge season-kind--<?= h($k) ?>"><?= h(season_kind_label($k)) ?></span></td><?php endif; ?>
            <?php if ($hasChronicleId): ?><td><?= h((string)($r['chronicle_name'] ?? '')) ?: '-' ?></td><?php endif; ?>
            <?php if ($hasFinished): ?><td><?= !empty($r['finished']) ? 'Si' : 'No' ?></td><?php endif; ?>
            <?php if ($hasPrettyId): ?><td><?= h((string)($r['pretty_id'] ?? '')) ?></td><?php endif; ?>
            <?php if ($hasImageUrl): ?><td><?= h(short_text((string)($r['image_url'] ?? ''), 70)) ?></td><?php endif; ?>
            <td><?= h(short_text(strip_tags((string)($r['description'] ?? '')), 20)) ?></td>
            <td>
                <button class="btn" type="button" onclick="openSeasonModal(<?= (int)$r['id'] ?>)">Editar</button>
                <button class="btn btn-red" type="button" onclick="openSeasonDeleteModal(<?= (int)$r['id'] ?>)">Borrar</button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="<?= 4 + ($hasSortOrder?1:0) + ($hasSeasonCol?1:0) + ($hasChronicleId?1:0) + ($hasFinished?1:0) + ($hasPrettyId?1:0) + ($hasImageUrl?1:0) + ($hasDescription?1:0) ?>" class="adm-color-muted">(Sin temporadas)</td></tr>
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
    const eKind = document.getElementById('season_kind'); if (eKind) eKind.value = 'temporada';
    const eChron = document.getElementById('season_chronicle_id'); if (eChron) eChron.value = '0';
    const eFin = document.getElementById('season_finished'); if (eFin) eFin.checked = false;
    const eImage = document.getElementById('season_image_url'); if (eImage) eImage.value = '';
    const eImageUpload = document.getElementById('season_image_upload'); if (eImageUpload) eImageUpload.value = '';
    const eImageRemove = document.getElementById('season_image_remove'); if (eImageRemove) eImageRemove.checked = false;
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
            if (eKind) eKind.value = (row.season_kind || 'temporada');
            if (eChron) eChron.value = String(parseInt(row.chronicle_id || 0, 10) || 0);
            if (eFin) eFin.checked = parseInt(row.finished || 0, 10) === 1;
            if (eImage) eImage.value = row.image_url || '';
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




