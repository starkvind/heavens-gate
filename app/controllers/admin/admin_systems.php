<?php
// admin_systems.php -- CRUD Sistemas (dim_systems)
if (!isset($link) || !$link) { die("Error de conexion a la base de datos."); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function slugify_pretty(string $text): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (function_exists('iconv')) { $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text; }
    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);
    return $text;
}
function update_pretty_id(mysqli $link, string $table, int $id, string $source): void {
    if ($id <= 0) return;
    $slug = slugify_pretty($source);
    if ($slug === '') $slug = (string)$id;
    if ($st = $link->prepare("UPDATE `$table` SET pretty_id=? WHERE id=?")) {
        $st->bind_param("si", $slug, $id);
        $st->execute();
        $st->close();
    }
}
function sanitize_utf8_text(string $s): string {
    if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
        if (function_exists('iconv')) {
            $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s) ?: $s;
        } elseif (function_exists('mb_convert_encoding')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
    }
    return $s ?? '';
}

$origins = [];
if ($rs = $link->query("SELECT id, name FROM dim_bibliographies ORDER BY name ASC")) {
    while ($r = $rs->fetch_assoc()) { $origins[] = $r; }
    $rs->close();
}

$actions = '<span class="adm-flex-right-8">'
    . '<button class="btn btn-green" type="button" onclick="openSystemModal()">+ Nuevo sistema</button>'
    . '<label class="adm-text-left">Filtro rapido '
    . '<input class="inp" type="text" id="quickFilterSystems" placeholder="En esta pagina..."></label>'
    . '</span>';
if (!$isAjaxRequest) {
    admin_panel_open('Sistemas', $actions);
}

$flash = [];
$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_systems';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function systems_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $t = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($t, 'csrf_admin_systems');
    }
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_systems']) && hash_equals($_SESSION['csrf_admin_systems'], $t);
}

// Borrar
if (!$isAjaxRequest && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0 && ($st = $link->prepare("DELETE FROM dim_systems WHERE id=?"))) {
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        $flash[] = ['type'=>'ok','msg'=>'Sistema eliminado.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['crud_action'] ?? '') === 'delete') {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (!systems_csrf_ok()) {
        $flash[] = ['type'=>'error','msg'=>'CSRF invalido. Recarga la pagina.'];
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $flash[] = ['type'=>'error','msg'=>'ID invalido para borrar.'];
        } elseif ($st = $link->prepare("DELETE FROM dim_systems WHERE id=?")) {
            $st->bind_param('i', $id);
            if ($st->execute()) {
                $flash[] = ['type'=>'ok','msg'=>'Sistema eliminado.'];
            } else {
                $flash[] = ['type'=>'error','msg'=>'Error al borrar: '.$st->error];
            }
            $st->close();
        } else {
            $flash[] = ['type'=>'error','msg'=>'Error al preparar DELETE: '.$link->error];
        }
    }
}

// Crear/Actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_system'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (!systems_csrf_ok()) {
        $flash[] = ['type'=>'error','msg'=>'CSRF invalido. Recarga la pagina.'];
    } else {
    $id = (int)($_POST['id'] ?? 0);
    $orden = (int)($_POST['orden'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $img = trim((string)($_POST['image_url'] ?? ''));
    $formas = (int)($_POST['formas'] ?? 0);
    $desc = sanitize_utf8_text((string)($_POST['descripcion'] ?? ''));
    $desc = hg_mentions_convert($link, $desc);
    $bibliographyId = (int)($_POST['bibliography_id'] ?? 0);

    if ($name === '') {
        $flash[] = ['type'=>'error','msg'=>'El nombre es obligatorio.'];
    } else {
        if ($id > 0) {
            $sql = "UPDATE dim_systems SET sort_order=?, name=?, image_url=?, forms=?, description=?, bibliography_id=? WHERE id=?";
            if ($st = $link->prepare($sql)) {
                $st->bind_param('issisii', $orden, $name, $img, $formas, $desc, $bibliographyId, $id);
                if ($st->execute()) {
                    update_pretty_id($link, 'dim_systems', $id, $name);
                    $flash[] = ['type'=>'ok','msg'=>'Sistema actualizado.'];
                } else {
                    $flash[] = ['type'=>'error','msg'=>'Error al actualizar: '.$st->error];
                }
                $st->close();
            } else {
                $flash[] = ['type'=>'error','msg'=>'Error al preparar UPDATE: '.$link->error];
            }
        } else {
            $sql = "INSERT INTO dim_systems (sort_order, name, image_url, forms, description, bibliography_id, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())";
            if ($st = $link->prepare($sql)) {
                $st->bind_param('issisi', $orden, $name, $img, $formas, $desc, $bibliographyId);
                if ($st->execute()) {
                    $newId = (int)$st->insert_id;
                    update_pretty_id($link, 'dim_systems', $newId, $name);
                    $flash[] = ['type'=>'ok','msg'=>'Sistema creado.'];
                } else {
                    $flash[] = ['type'=>'error','msg'=>'Error al crear: '.$st->error];
                }
                $st->close();
            } else {
                $flash[] = ['type'=>'error','msg'=>'Error al preparar INSERT: '.$link->error];
            }
        }
    }
    }
}

// Listado
$rows = [];
$rowsFull = [];
$sql = "SELECT s.id, s.sort_order AS orden, s.name, s.image_url, s.forms AS formas, s.description AS descripcion, s.bibliography_id, COALESCE(b.name,'') AS origen_name FROM dim_systems s LEFT JOIN dim_bibliographies b ON s.bibliography_id=b.id ORDER BY s.sort_order, s.name";
if ($rs = $link->query($sql)) {
    while ($r = $rs->fetch_assoc()) { $rows[] = $r; $rowsFull[] = $r; }
    $rs->close();
}

if ($isAjaxRequest && (string)($_GET['ajax_mode'] ?? '') === 'list') {
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success(['rows' => $rows, 'rowsFull' => $rowsFull, 'total' => count($rows)], 'Listado');
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'rows' => $rows,
        'rowsFull' => $rowsFull,
        'total' => count($rows),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$ajaxSaveDelete = (
    $isAjaxRequest
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['save_system']) || (string)($_POST['crud_action'] ?? '') === 'delete')
);
if ($ajaxSaveDelete) {
    $errors = [];
    $messages = [];
    foreach ($flash as $m) {
        $msg = (string)($m['msg'] ?? '');
        if ($msg === '') continue;
        if ((string)($m['type'] ?? '') === 'error') $errors[] = $msg;
        else $messages[] = $msg;
    }
    if (!empty($errors)) {
        if (function_exists('hg_admin_json_error')) {
            hg_admin_json_error($errors[0], 400, ['flash' => $errors], ['messages' => $messages]);
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => false,
            'message' => $errors[0],
            'errors' => $errors,
            'data' => ['messages' => $messages],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $okMsg = !empty($messages) ? $messages[count($messages)-1] : 'Guardado';
    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success(['rows' => $rows, 'rowsFull' => $rowsFull, 'messages' => $messages], $okMsg);
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'message' => $okMsg,
        'msg' => $okMsg,
        'data' => [
            'rows' => $rows,
            'rowsFull' => $rowsFull,
            'messages' => $messages,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
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

<div class="modal-back" id="systemModal">
    <div class="modal">
        <h3 id="systemModalTitle">Nuevo sistema</h3>
        <form method="post" id="systemForm">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="save_system" value="1">
            <input type="hidden" name="id" id="system_id" value="">
            <div class="modal-body">
                <div class="adm-grid-1-2">
                    <label>Orden</label>
                    <input class="inp" type="number" name="orden" id="system_orden">

                    <label>Nombre</label>
                    <input class="inp" type="text" name="name" id="system_name" required>

                    <label>Imagen</label>
                    <input class="inp" type="text" name="image_url" id="system_img">

                    <label>Formas</label>
                    <select class="select" name="formas" id="system_formas">
                        <option value="0">No</option>
                        <option value="1">Si</option>
                    </select>

                    <label>Origen</label>
                    <select class="select" name="bibliography_id" id="system_bibliography_id">
                        <option value="0">--</option>
                        <?php foreach ($origins as $o): ?>
                            <option value="<?= (int)$o['id'] ?>"><?= h($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>Descripcion</label>
                    <div>
                        <div id="system_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
                        <div id="system_editor" class="ql-container ql-snow"></div>
                        <textarea class="ta adm-hidden" name="descripcion" id="system_desc" rows="8"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-green" type="submit">Guardar</button>
                <button class="btn" type="button" onclick="closeSystemModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<table class="table" id="tablaSystems">
    <thead>
        <tr>
            <th class="adm-w-60">ID</th>
            <th>Nombre</th>
            <th>Orden</th>
            <th>Formas</th>
            <th>Origen</th>
            <th class="adm-w-160">Acciones</th>
        </tr>
    </thead>
    <tbody id="systemsTbody">
    <?php foreach ($rows as $r): ?>
        <?php
            $search = trim((string)($r['name'] ?? '') . ' ' . (string)($r['origen_name'] ?? '') . ' ' . (string)($r['orden'] ?? ''));
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
            else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><?= (int)$r['id'] ?></td>
            <td><?= h($r['name']) ?></td>
            <td><?= (int)$r['orden'] ?></td>
            <td><?= ((int)$r['formas'] === 1) ? 'Si' : 'No' ?></td>
            <td><?= h($r['origen_name'] ?? '') ?></td>
            <td>
                <button class="btn" type="button" data-edit="<?= (int)$r['id'] ?>">Editar</button>
                <button class="btn btn-red" type="button" data-del="<?= (int)$r['id'] ?>">Borrar</button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="adm-color-muted">(Sin sistemas)</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<link href="/assets/vendor/quill/1.3.7/quill.snow.css" rel="stylesheet">
<script src="/assets/vendor/quill/1.3.7/quill.min.js"></script>
<?php include_once(__DIR__ . '/../../partials/admin/mentions_includes.php'); ?>
<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
let systemsData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
let sysEditor = null;

function request(url, opts){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
        return window.HGAdminHttp.request(url, opts || {});
    }
    const cfg = Object.assign({
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }, opts || {});
    return fetch(url, cfg).then(async function(resp){
        const text = await resp.text();
        let payload = {};
        if (text) {
            try { payload = JSON.parse(text); }
            catch (e) { payload = { ok:false, message:'Respuesta no JSON', raw:text }; }
        }
        if (!resp.ok || (payload && payload.ok === false)) {
            const err = new Error((payload && (payload.message || payload.error || payload.msg)) || ('HTTP ' + resp.status));
            err.status = resp.status;
            err.payload = payload;
            throw err;
        }
        return payload;
    });
}

function endpointUrl(mode){
    const url = new URL(window.location.href);
    url.searchParams.set('s', 'admin_systems');
    url.searchParams.set('ajax', '1');
    if (mode) url.searchParams.set('ajax_mode', mode);
    else url.searchParams.delete('ajax_mode');
    url.searchParams.set('_ts', Date.now());
    return url.toString();
}

function esc(s){
    return String(s || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function ensureSysEditor(){
    if (!sysEditor && window.Quill) {
        sysEditor = new Quill('#system_editor', { theme:'snow', modules:{ toolbar:'#system_toolbar' } });
        if (window.hgMentions) { window.hgMentions.attachQuill(sysEditor, { types: ['character','season','episode','organization','group','gift','rite','totem','discipline','item','trait','background','merit','flaw','merydef','doc'] }); }
    }
}

function openSystemModal(id = null){
    ensureSysEditor();
    const modal = document.getElementById('systemModal');
    document.getElementById('system_id').value = '';
    document.getElementById('system_orden').value = '';
    document.getElementById('system_name').value = '';
    document.getElementById('system_img').value = '';
    document.getElementById('system_formas').value = '0';
    document.getElementById('system_bibliography_id').value = '0';
    document.getElementById('system_desc').value = '';
    if (sysEditor) sysEditor.root.innerHTML = '';

    if (id) {
        const row = systemsData.find(r => parseInt(r.id,10) === parseInt(id,10));
        if (row) {
            document.getElementById('systemModalTitle').textContent = 'Editar sistema';
            document.getElementById('system_id').value = row.id;
            document.getElementById('system_orden').value = row.orden || 0;
            document.getElementById('system_name').value = row.name || '';
            document.getElementById('system_img').value = row.image_url || '';
            document.getElementById('system_formas').value = row.formas || 0;
            document.getElementById('system_bibliography_id').value = row.bibliography_id || 0;
            const desc = row.descripcion || '';
            document.getElementById('system_desc').value = desc;
            if (sysEditor) sysEditor.root.innerHTML = desc;
        }
    } else {
        document.getElementById('systemModalTitle').textContent = 'Nuevo sistema';
    }
    modal.style.display = 'flex';
}

function closeSystemModal(){
    document.getElementById('systemModal').style.display = 'none';
}

function bindRows(){
    document.querySelectorAll('#systemsTbody [data-edit]').forEach(function(btn){
        btn.onclick = function(){ openSystemModal(parseInt(btn.getAttribute('data-edit') || '0', 10) || 0); };
    });
    document.querySelectorAll('#systemsTbody [data-del]').forEach(function(btn){
        btn.onclick = function(){
            const id = parseInt(btn.getAttribute('data-del') || '0', 10) || 0;
            if (!id) return;
            if (!confirm('Eliminar sistema?')) return;
            const fd = new FormData();
            fd.set('ajax', '1');
            fd.set('csrf', window.ADMIN_CSRF_TOKEN || '');
            fd.set('crud_action', 'delete');
            fd.set('id', String(id));
            request(endpointUrl(''), { method:'POST', body: fd, loadingEl: btn }).then(function(payload){
                const data = payload && payload.data ? payload.data : {};
                if (Array.isArray(data.rows)) renderRows(data.rows);
                if (Array.isArray(data.rowsFull)) systemsData = data.rowsFull;
                applyQuickFilter();
                if (window.HGAdminHttp && window.HGAdminHttp.notify) {
                    window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Eliminado', 'ok');
                }
            }).catch(function(err){
                const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al borrar');
                alert(msg);
            });
        };
    });
}

function renderRows(rows){
    const tbody = document.getElementById('systemsTbody');
    if (!tbody) return;
    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="adm-color-muted">(Sin sistemas)</td></tr>';
        bindRows();
        return;
    }
    let html = '';
    rows.forEach(function(r){
        const id = parseInt(r.id || 0, 10) || 0;
        const name = String(r.name || '');
        const orden = parseInt(r.orden || 0, 10) || 0;
        const formas = (parseInt(r.formas || 0, 10) === 1) ? 'Si' : 'No';
        const origen = String(r.origen_name || '');
        const search = (name + ' ' + origen + ' ' + orden).toLowerCase();
        html += '<tr data-search="' + esc(search) + '">'
            + '<td>' + id + '</td>'
            + '<td>' + esc(name) + '</td>'
            + '<td>' + orden + '</td>'
            + '<td>' + formas + '</td>'
            + '<td>' + esc(origen) + '</td>'
            + '<td><button class="btn" type="button" data-edit="' + id + '">Editar</button> '
            + '<button class="btn btn-red" type="button" data-del="' + id + '">Borrar</button></td>'
            + '</tr>';
    });
    tbody.innerHTML = html;
    bindRows();
}

function applyQuickFilter(){
    const input = document.getElementById('quickFilterSystems');
    if (!input) return;
    const q = (input.value || '').toLowerCase();
    document.querySelectorAll('#systemsTbody tr').forEach(function(tr){
        const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
        tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
    });
}

document.getElementById('systemForm').addEventListener('submit', function(ev){
    ev.preventDefault();
    if (sysEditor) {
        const html = sysEditor.root.innerHTML || '';
        const plain = (sysEditor.getText() || '').replace(/\s+/g,' ').trim();
        document.getElementById('system_desc').value = plain ? html : '';
    }
    const fd = new FormData(this);
    fd.set('ajax', '1');
    request(endpointUrl(''), { method:'POST', body: fd, loadingEl: this }).then(function(payload){
        const data = payload && payload.data ? payload.data : {};
        if (Array.isArray(data.rows)) renderRows(data.rows);
        if (Array.isArray(data.rowsFull)) systemsData = data.rowsFull;
        closeSystemModal();
        applyQuickFilter();
        if (window.HGAdminHttp && window.HGAdminHttp.notify) {
            window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok');
        }
    }).catch(function(err){
        const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al guardar');
        alert(msg);
    });
});

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeSystemModal();
});
document.getElementById('quickFilterSystems').addEventListener('input', applyQuickFilter);
bindRows();
</script>

<?php admin_panel_close(); ?>




