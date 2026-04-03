<?php
// admin_resources.php - CRUD Catalogo de Recursos (dim_systems_resources)
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function slugify_resource_pretty(string $text): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (function_exists('iconv')) { $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text; }
    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);
    return $text;
}
function persist_resource_pretty_id(mysqli $link, int $id, string $source): bool {
    if ($id <= 0) return false;
    $slug = slugify_resource_pretty($source);
    if ($slug === '') $slug = (string)$id;
    $st = $link->prepare("UPDATE dim_systems_resources SET pretty_id=? WHERE id=?");
    if (!$st) return false;
    $st->bind_param('si', $slug, $id);
    $ok = $st->execute();
    $st->close();
    return (bool)$ok;
}
function short_txt(string $s, int $n=110): string {
    $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
    if (mb_strlen($s) <= $n) return $s;
    return mb_substr($s, 0, $n) . '...';
}

if (empty($_SESSION['csrf_admin_resources'])) {
    $_SESSION['csrf_admin_resources'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_admin_resources'];
function csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $t = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($t, 'csrf_admin_resources');
    }
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_resources']) && hash_equals($_SESSION['csrf_admin_resources'], $t);
}

$actions = '<span class="adm-flex-right-8">'
    . '<button class="btn btn-green" type="button" onclick="openResourceModal()">+ Nuevo recurso</button>'
    . '<label class="adm-text-left">Filtro rápido '
    . '<input class="inp" type="text" id="quickFilterResources" placeholder="En esta pagina..."></label>'
    . '</span>';
if (!$isAjaxRequest) {
    admin_panel_open('Recursos (catálogo)', $actions);
}

$flash = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (!csrf_ok()) {
        $flash[] = ['type'=>'error','msg'=>'CSRF inválido. Recarga la página.'];
    } else {
        $action = (string)($_POST['crud_action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'delete') {
            if ($id > 0 && ($st = $link->prepare("DELETE FROM dim_systems_resources WHERE id=?"))) {
                $st->bind_param('i', $id);
                if ($st->execute()) $flash[] = ['type'=>'ok','msg'=>'Recurso eliminado.'];
                else $flash[] = ['type'=>'error','msg'=>'Error al eliminar: '.$st->error];
                $st->close();
            } else {
                $flash[] = ['type'=>'error','msg'=>'ID inválido para eliminar.'];
            }
        }

        if ($action === 'create' || $action === 'update') {
            $name = trim((string)($_POST['name'] ?? ''));
            $kind = trim((string)($_POST['kind'] ?? ''));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $description = (string)($_POST['description'] ?? '');
            $description = hg_mentions_convert($link, $description);
            $kindAllowed = ['renombre', 'estado'];

            if ($name === '' || $kind === '') {
                $flash[] = ['type'=>'error','msg'=>'Nombre y tipo son obligatorios.'];
            } elseif (mb_strlen($kind) > 30) {
                $flash[] = ['type'=>'error','msg'=>'Tipo demasiado largo (max 30).'];
            } elseif (!in_array($kind, $kindAllowed, true)) {
                $flash[] = ['type'=>'error','msg'=>'Tipo inválido. Solo se permite: renombre o estado.'];
            } else {
                if ($action === 'create') {
                    $sql = "INSERT INTO dim_systems_resources (name, kind, sort_order, description, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())";
                    $st = $link->prepare($sql);
                    if (!$st) {
                        $flash[] = ['type'=>'error','msg'=>'Error al preparar INSERT: '.$link->error];
                    } else {
                        $st->bind_param('ssis', $name, $kind, $sortOrder, $description);
                        if ($st->execute()) {
                            $newId = (int)$link->insert_id;
                            $prettyOk = persist_resource_pretty_id($link, $newId, $name);
                            $flash[] = ['type'=>$prettyOk ? 'ok' : 'error','msg'=>$prettyOk ? 'Recurso creado.' : 'Recurso creado, pero no se pudo guardar pretty_id.'];
                        } else {
                            $flash[] = ['type'=>'error','msg'=>'Error al crear: '.$st->error];
                        }
                        $st->close();
                    }
                } else {
                    if ($id <= 0) {
                        $flash[] = ['type'=>'error','msg'=>'ID inválido para actualizar.'];
                    } else {
                        $sql = "UPDATE dim_systems_resources SET name=?, kind=?, sort_order=?, description=?, updated_at=NOW() WHERE id=?";
                        $st = $link->prepare($sql);
                        if (!$st) {
                            $flash[] = ['type'=>'error','msg'=>'Error al preparar UPDATE: '.$link->error];
                        } else {
                            $st->bind_param('ssisi', $name, $kind, $sortOrder, $description, $id);
                            if ($st->execute()) {
                                $prettyOk = persist_resource_pretty_id($link, $id, $name);
                                $flash[] = ['type'=>$prettyOk ? 'ok' : 'error','msg'=>$prettyOk ? 'Recurso actualizado.' : 'Recurso actualizado, pero no se pudo guardar pretty_id.'];
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
}

$rows = [];
$rowsFull = [];
$rs = $link->query("SELECT id, pretty_id, name, kind, sort_order, description FROM dim_systems_resources ORDER BY kind ASC, sort_order ASC, name ASC");
if ($rs) {
    while ($r = $rs->fetch_assoc()) {
        $rows[] = $r;
        $rowsFull[] = $r;
    }
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
    && isset($_POST['crud_action'])
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

<div class="modal-back" id="resourceModal">
    <div class="modal">
        <h3 id="resourceModalTitle">Nuevo recurso</h3>
        <form method="post" id="resourceForm">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="crud_action" id="resource_action" value="create">
            <input type="hidden" name="id" id="resource_id" value="0">
            <div class="modal-body">
                <div class="adm-grid-1-2">
                    <label>Nombre</label>
                    <input class="inp" type="text" name="name" id="resource_name" maxlength="100" required>

                    <label>Tipo</label>
                    <select class="select" name="kind" id="resource_kind" required>
                        <option value="renombre">renombre</option>
                        <option value="estado">estado</option>
                    </select>

                    <label>Orden</label>
                    <input class="inp" type="number" name="sort_order" id="resource_sort_order" value="0">

                    <label>Descripción</label>
                    <textarea class="inp" name="description" id="resource_description" rows="8"></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-green" type="submit">Guardar</button>
                <button class="btn" type="button" onclick="closeResourceModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-back" id="resourceDeleteModal">
    <div class="modal adm-modal-sm">
        <h3>Confirmar borrado</h3>
        <div class="adm-help-text">
            Esto eliminará el recurso del catálogo.
        </div>
        <form method="post" id="resourceDeleteForm" class="adm-m-0">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="crud_action" value="delete">
            <input type="hidden" name="id" id="resource_delete_id" value="0">
            <div class="modal-actions">
                <button type="button" class="btn" onclick="closeResourceDeleteModal()">Cancelar</button>
                <button type="submit" class="btn btn-red">Borrar</button>
            </div>
        </form>
    </div>
</div>

<table class="table" id="tablaResources">
    <thead>
        <tr>
            <th class="adm-w-60">ID</th>
            <th class="adm-w-220">Nombre</th>
            <th class="adm-w-150">Tipo</th>
            <th class="adm-w-80">Orden</th>
            <th class="adm-w-220">Pretty ID</th>
            <th>Descripción</th>
            <th class="adm-w-160">Acciones</th>
        </tr>
    </thead>
    <tbody id="resourcesTbody">
    <?php foreach ($rows as $r): ?>
        <?php
            $search = trim((string)($r['name'] ?? '') . ' ' . (string)($r['kind'] ?? '') . ' ' . (string)($r['pretty_id'] ?? '') . ' ' . (string)($r['description'] ?? ''));
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
            else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><?= (int)$r['id'] ?></td>
            <td><?= h((string)$r['name']) ?></td>
            <td><?= h((string)$r['kind']) ?></td>
            <td><?= (int)($r['sort_order'] ?? 0) ?></td>
            <td><?= h((string)($r['pretty_id'] ?? '')) ?></td>
            <td><?= h(short_txt(strip_tags((string)($r['description'] ?? '')), 20)) ?></td>
            <td>
                <button class="btn" type="button" data-edit="<?= (int)$r['id'] ?>">Editar</button>
                <button class="btn btn-red" type="button" data-del="<?= (int)$r['id'] ?>">Borrar</button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="adm-color-muted">(Sin recursos)</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
let resourcesData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;

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
    url.searchParams.set('s', 'admin_resources');
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

function shortTxt(s, n){
    const txt = String(s || '').replace(/\s+/g, ' ').trim();
    return txt.length <= n ? txt : (txt.slice(0, n) + '...');
}

function openResourceModal(id = null){
    const modal = document.getElementById('resourceModal');
    document.getElementById('resource_id').value = '0';
    document.getElementById('resource_action').value = 'create';
    document.getElementById('resource_name').value = '';
    document.getElementById('resource_kind').value = 'renombre';
    document.getElementById('resource_sort_order').value = '0';
    document.getElementById('resource_description').value = '';

    if (id) {
        const row = resourcesData.find(r => parseInt(r.id,10) === parseInt(id,10));
        if (row) {
            document.getElementById('resourceModalTitle').textContent = 'Editar recurso';
            document.getElementById('resource_action').value = 'update';
            document.getElementById('resource_id').value = String(row.id || 0);
            document.getElementById('resource_name').value = row.name || '';
            document.getElementById('resource_kind').value = row.kind || '';
            document.getElementById('resource_sort_order').value = String(parseInt(row.sort_order || 0, 10) || 0);
            document.getElementById('resource_description').value = row.description || '';
        }
    } else {
        document.getElementById('resourceModalTitle').textContent = 'Nuevo recurso';
    }
    modal.style.display = 'flex';
}

function closeResourceModal(){
    document.getElementById('resourceModal').style.display = 'none';
}

function openResourceDeleteModal(id){
    document.getElementById('resource_delete_id').value = String(parseInt(id || 0, 10) || 0);
    document.getElementById('resourceDeleteModal').style.display = 'flex';
}

function closeResourceDeleteModal(){
    document.getElementById('resourceDeleteModal').style.display = 'none';
}

function bindRows(){
    document.querySelectorAll('#resourcesTbody [data-edit]').forEach(function(btn){
        btn.onclick = function(){
            openResourceModal(parseInt(btn.getAttribute('data-edit') || '0', 10) || 0);
        };
    });
    document.querySelectorAll('#resourcesTbody [data-del]').forEach(function(btn){
        btn.onclick = function(){
            openResourceDeleteModal(parseInt(btn.getAttribute('data-del') || '0', 10) || 0);
        };
    });
}

function renderRows(rows){
    const tbody = document.getElementById('resourcesTbody');
    if (!tbody) return;
    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="adm-color-muted">(Sin recursos)</td></tr>';
        bindRows();
        return;
    }
    let html = '';
    rows.forEach(function(r){
        const id = parseInt(r.id || 0, 10) || 0;
        const name = String(r.name || '');
        const kind = String(r.kind || '');
        const sortOrder = parseInt(r.sort_order || 0, 10) || 0;
        const pretty = String(r.pretty_id || '');
        const desc = String(r.description || '');
        const search = (name + ' ' + kind + ' ' + pretty + ' ' + desc).toLowerCase();
        html += '<tr data-search="' + esc(search) + '">'
            + '<td>' + id + '</td>'
            + '<td>' + esc(name) + '</td>'
            + '<td>' + esc(kind) + '</td>'
            + '<td>' + sortOrder + '</td>'
            + '<td>' + esc(pretty) + '</td>'
            + '<td>' + esc(shortTxt(desc.replace(/<[^>]*>/g, ''), 20)) + '</td>'
            + '<td><button class="btn" type="button" data-edit="' + id + '">Editar</button> '
            + '<button class="btn btn-red" type="button" data-del="' + id + '">Borrar</button></td>'
            + '</tr>';
    });
    tbody.innerHTML = html;
    bindRows();
}

function applyQuickFilter(){
    const input = document.getElementById('quickFilterResources');
    if (!input) return;
    const q = (input.value || '').toLowerCase();
    document.querySelectorAll('#resourcesTbody tr').forEach(function(tr){
        const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
        tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
    });
}

document.getElementById('resourceForm').addEventListener('submit', function(ev){
    ev.preventDefault();
    const fd = new FormData(this);
    fd.set('ajax', '1');
    request(endpointUrl(''), { method:'POST', body: fd, loadingEl: this }).then(function(payload){
        const data = payload && payload.data ? payload.data : {};
        if (Array.isArray(data.rows)) renderRows(data.rows);
        if (Array.isArray(data.rowsFull)) resourcesData = data.rowsFull;
        closeResourceModal();
        applyQuickFilter();
        if (window.HGAdminHttp && window.HGAdminHttp.notify) {
            window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok');
        }
    }).catch(function(err){
        const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al guardar');
        alert(msg);
    });
});

document.getElementById('resourceDeleteForm').addEventListener('submit', function(ev){
    ev.preventDefault();
    const fd = new FormData(this);
    fd.set('ajax', '1');
    request(endpointUrl(''), { method:'POST', body: fd, loadingEl: this }).then(function(payload){
        const data = payload && payload.data ? payload.data : {};
        if (Array.isArray(data.rows)) renderRows(data.rows);
        if (Array.isArray(data.rowsFull)) resourcesData = data.rowsFull;
        closeResourceDeleteModal();
        applyQuickFilter();
        if (window.HGAdminHttp && window.HGAdminHttp.notify) {
            window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Eliminado', 'ok');
        }
    }).catch(function(err){
        const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al borrar');
        alert(msg);
    });
});

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
        closeResourceModal();
        closeResourceDeleteModal();
    }
});
document.getElementById('quickFilterResources').addEventListener('input', applyQuickFilter);
bindRows();
</script>

<?php admin_panel_close(); ?>




