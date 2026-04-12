<?php
// admin_character_conditions.php - CRUD de dim_character_conditions
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fetchPairsCharacterConditions(mysqli $link, string $sql): array {
    $out = [];
    $q = @$link->query($sql);
    if (!$q) return $out;
    while ($r = $q->fetch_assoc()) {
        $id = isset($r['id']) ? (int)$r['id'] : 0;
        $nm = (string)($r['name'] ?? '');
        if ($id > 0) $out[$id] = $nm;
    }
    $q->close();
    return $out;
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_character_conditions';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}

function character_conditions_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $t = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($t, 'csrf_admin_character_conditions');
    }
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_character_conditions']) && hash_equals($_SESSION['csrf_admin_character_conditions'], $t);
}

$categoryOptions = [
    'Deformidad Metis',
    'Cicatrices de Batalla',
    'Trastorno Mental',
];

$perPage = isset($_GET['pp']) ? max(10, min(200, (int)$_GET['pp'])) : 25;
$page    = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$q       = trim((string)($_GET['q'] ?? ''));
$offset  = ($page - 1) * $perPage;
$flash = [];
$optsOrigins = fetchPairsCharacterConditions($link, "SELECT id, name FROM dim_bibliographies ORDER BY name ASC");

$isAjaxCrudRequest = (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['crud_action'])
    && (
        ((string)($_POST['ajax'] ?? '') === '1')
        || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
    )
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    if ($isAjaxCrudRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }

    if (!character_conditions_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $action = (string)($_POST['crud_action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $bibliography_id = (int)($_POST['bibliography_id'] ?? 0);
        $max_instances = (int)($_POST['max_instances'] ?? 1);
        $description = trim((string)($_POST['description'] ?? ''));

        if ($bibliography_id < 0) $bibliography_id = 0;
        if ($max_instances < 0) $max_instances = 0;

        if ($action !== 'delete') {
            if ($name === '') $flash[] = ['type' => 'error', 'msg' => 'Nombre obligatorio.'];
            if ($category === '') $flash[] = ['type' => 'error', 'msg' => 'Categoria obligatoria.'];
            if (!in_array($category, $categoryOptions, true)) {
                $flash[] = ['type' => 'error', 'msg' => 'Categoria invalida.'];
            }
            if ($description === '') $flash[] = ['type' => 'error', 'msg' => 'Descripcion obligatoria.'];
            if ($max_instances > 999) {
                $flash[] = ['type' => 'error', 'msg' => 'Maximo de repeticiones demasiado alto.'];
            }
            if ($bibliography_id > 0 && !isset($optsOrigins[$bibliography_id])) {
                $flash[] = ['type' => 'error', 'msg' => 'Origen invalido.'];
            }
        }

        $hasErr = false;
        foreach ($flash as $m) {
            if (($m['type'] ?? '') === 'error') {
                $hasErr = true;
                break;
            }
        }

        if (!$hasErr) {
            if ($action === 'create') {
                $sql = "INSERT INTO dim_character_conditions
                        (name, category, description, max_instances, bibliography_id, created_at, updated_at)
                        VALUES (?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NOW(), NOW())";
                $st = $link->prepare($sql);
                if (!$st) {
                    $flash[] = ['type' => 'error', 'msg' => 'Error al preparar INSERT: ' . $link->error];
                } else {
                    $st->bind_param('sssii', $name, $category, $description, $max_instances, $bibliography_id);
                    if ($st->execute()) {
                        $newId = (int)$link->insert_id;
                        hg_update_pretty_id_if_exists($link, 'dim_character_conditions', $newId, $name);
                        $flash[] = ['type' => 'ok', 'msg' => 'Condicion creada correctamente.'];
                    } else {
                        $flash[] = ['type' => 'error', 'msg' => 'Error al crear: ' . $st->error];
                    }
                    $st->close();
                }
            } elseif ($action === 'update') {
                if ($id <= 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'ID invalido para actualizar.'];
                } else {
                    $sql = "UPDATE dim_character_conditions
                            SET name=?, category=?, description=?, max_instances=NULLIF(?, 0), bibliography_id=NULLIF(?, 0), updated_at=NOW()
                            WHERE id=?";
                    $st = $link->prepare($sql);
                    if (!$st) {
                        $flash[] = ['type' => 'error', 'msg' => 'Error al preparar UPDATE: ' . $link->error];
                    } else {
                        $st->bind_param('sssiii', $name, $category, $description, $max_instances, $bibliography_id, $id);
                        if ($st->execute()) {
                            hg_update_pretty_id_if_exists($link, 'dim_character_conditions', $id, $name);
                            $flash[] = ['type' => 'ok', 'msg' => 'Condicion actualizada.'];
                        } else {
                            $flash[] = ['type' => 'error', 'msg' => 'Error al actualizar: ' . $st->error];
                        }
                        $st->close();
                    }
                }
            } elseif ($action === 'delete') {
                if ($id <= 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'ID invalido para borrar.'];
                } else {
                    $st = $link->prepare("DELETE FROM dim_character_conditions WHERE id=?");
                    if (!$st) {
                        $flash[] = ['type' => 'error', 'msg' => 'Error al preparar DELETE: ' . $link->error];
                    } else {
                        $st->bind_param('i', $id);
                        if ($st->execute()) {
                            $flash[] = ['type' => 'ok', 'msg' => 'Condicion eliminada.'];
                        } else {
                            $flash[] = ['type' => 'error', 'msg' => 'Error al borrar: ' . $st->error];
                        }
                        $st->close();
                    }
                }
            }
        }
    }
}

if ($isAjaxCrudRequest) {
    $errors = [];
    $messages = [];
    foreach ($flash as $m) {
        $type = (string)($m['type'] ?? '');
        $msg = (string)($m['msg'] ?? '');
        if ($msg === '') continue;
        if ($type === 'error') $errors[] = $msg;
        else $messages[] = $msg;
    }
    if (!empty($errors)) {
        if (function_exists('hg_admin_json_error')) {
            hg_admin_json_error($errors[0], 400, ['flash' => $errors], ['messages' => $messages]);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'message' => $errors[0],
            'error' => $errors[0],
            'errors' => $errors,
            'data' => ['messages' => $messages],
            'meta' => ['module' => 'admin_character_conditions'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $okMsg = !empty($messages) ? $messages[count($messages)-1] : 'Guardado';
    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success(['messages' => $messages], $okMsg);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'message' => $okMsg,
        'msg' => $okMsg,
        'data' => ['messages' => $messages],
        'errors' => [],
        'meta' => ['module' => 'admin_character_conditions'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$ajaxMode = (string)($_GET['ajax_mode'] ?? ($_GET['ajax'] ?? ''));
if ($ajaxMode === 'search' || $ajaxMode === '1') {
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $qAjax = trim((string)($_GET['q'] ?? ''));
    $whereAjax = "WHERE 1=1";
    $typesAjax = "";
    $paramsAjax = [];
    if ($qAjax !== '') {
        $whereAjax .= " AND (cc.name LIKE ? OR cc.category LIKE ? OR COALESCE(b.name, '') LIKE ? OR cc.description LIKE ?)";
        $typesAjax = "ssss";
        $needleAjax = "%".$qAjax."%";
        $paramsAjax[] = $needleAjax;
        $paramsAjax[] = $needleAjax;
        $paramsAjax[] = $needleAjax;
        $paramsAjax[] = $needleAjax;
    }

    $sqlAjax = "SELECT cc.id, cc.pretty_id, cc.name, cc.category, cc.description, cc.max_instances, cc.bibliography_id,
                       COALESCE(b.name, '') AS origin_name
                FROM dim_character_conditions cc
                LEFT JOIN dim_bibliographies b ON b.id = cc.bibliography_id
                ".$whereAjax."
                ORDER BY cc.id DESC";
    $stAjax = $link->prepare($sqlAjax);
    if ($typesAjax !== '') $stAjax->bind_param($typesAjax, ...$paramsAjax);
    $stAjax->execute();
    $rsAjax = $stAjax->get_result();

    $rowsAjax = [];
    $rowMapAjax = [];
    while ($r = $rsAjax->fetch_assoc()) {
        $rowsAjax[] = $r;
        $rowMapAjax[(int)$r['id']] = $r;
    }
    $stAjax->close();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'message' => 'OK',
        'msg' => 'OK',
        'rows' => $rowsAjax,
        'rowMap' => $rowMapAjax,
        'total' => count($rowsAjax),
        'data' => ['rows' => $rowsAjax, 'rowMap' => $rowMapAjax, 'total' => count($rowsAjax)],
        'errors' => [],
        'meta' => ['module' => 'admin_character_conditions', 'mode' => 'search'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$where = "WHERE 1=1";
$types = "";
$params = [];
if ($q !== '') {
    $where .= " AND (cc.name LIKE ? OR cc.category LIKE ? OR COALESCE(b.name, '') LIKE ? OR cc.description LIKE ?)";
    $types .= "ssss";
    $needle = "%".$q."%";
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
}

$sqlCnt = "SELECT COUNT(*) AS c
           FROM dim_character_conditions cc
           LEFT JOIN dim_bibliographies b ON b.id = cc.bibliography_id
           ".$where;
$stC = $link->prepare($sqlCnt);
if ($types !== '') $stC->bind_param($types, ...$params);
$stC->execute();
$rsC = $stC->get_result();
$total = ($rsC && ($rowC = $rsC->fetch_assoc())) ? (int)$rowC['c'] : 0;
$stC->close();

$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset= ($page - 1) * $perPage;

$sqlList = "SELECT cc.id, cc.pretty_id, cc.name, cc.category, cc.description, cc.max_instances, cc.bibliography_id,
                   COALESCE(b.name, '') AS origin_name
            FROM dim_character_conditions cc
            LEFT JOIN dim_bibliographies b ON b.id = cc.bibliography_id
            ".$where."
            ORDER BY cc.id DESC
            LIMIT ?, ?";
$types2 = $types . "ii";
$params2 = $params;
$params2[] = $offset;
$params2[] = $perPage;

$rows = [];
$rowMap = [];
$stL = $link->prepare($sqlList);
$stL->bind_param($types2, ...$params2);
$stL->execute();
$rsL = $stL->get_result();
while ($r = $rsL->fetch_assoc()) {
    $rows[] = $r;
    $rowMap[(int)$r['id']] = $r;
}
$stL->close();

$actions = '<span class="adm-flex-right-8">'
    . '<button class="btn btn-green" type="button" id="btnNewCondition">+ Nueva condicion</button>'
    . '</span>';
admin_panel_open('Condiciones de Personaje', $actions);
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = $m['type']==='ok' ? 'ok' : (($m['type'] ?? '')==='error' ? 'err' : 'info'); ?>
        <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="get" id="conditionFilterForm" class="adm-flex-8-m10">
    <input type="hidden" name="s" value="admin_character_conditions">
    <label class="small">Busqueda
        <input class="inp" type="text" name="q" id="quickFilterConditions" value="<?= h($q) ?>" placeholder="Nombre, categoria, origen o descripcion (realtime)">
    </label>
    <label class="small">Por pag.
        <select class="select" name="pp" onchange="this.form.submit()">
            <?php foreach ([25,50,100,200] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage===$pp?'selected':'' ?>><?= $pp ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn" type="button" id="btnApplyConditionFilter">Aplicar</button>
</form>

<table class="table" id="tablaConditions">
    <thead>
        <tr>
            <th class="adm-w-70">ID</th>
            <th class="adm-w-260">Nombre</th>
            <th class="adm-w-180">Categoria</th>
            <th class="adm-w-120">Repeticiones</th>
            <th class="adm-w-220">Origen</th>
            <th>Descripcion</th>
            <th class="adm-w-170">Acciones</th>
        </tr>
    </thead>
    <tbody id="conditionsTbody">
        <?php foreach ($rows as $r):
            $search = trim((string)$r['name'].' '.(string)$r['category'].' '.(string)$r['origin_name'].' '.(string)$r['description']);
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
            else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><strong class="adm-color-accent"><?= (int)$r['id'] ?></strong></td>
            <td><?= h((string)$r['name']) ?></td>
            <td><?= h((string)$r['category']) ?></td>
            <td><?= $r['max_instances'] === null ? 'Sin limite' : (int)$r['max_instances'] ?></td>
            <td><?= h((string)$r['origin_name']) ?></td>
            <td class="adm-cell-wrap"><?= h((string)$r['description']) ?></td>
            <td>
                <button class="btn" type="button" data-edit="<?= (int)$r['id'] ?>">Editar</button>
                <button class="btn btn-red" type="button" data-del="<?= (int)$r['id'] ?>">Borrar</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="adm-color-muted">(Sin resultados)</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="pager" id="conditionsPager">
    <?php
    $base = "/talim?s=admin_character_conditions&pp=".$perPage."&q=".urlencode($q);
    $prev = max(1, $page-1);
    $next = min($pages, $page+1);
    ?>
    <a href="<?= $base ?>&pg=1">&laquo; Primero</a>
    <a href="<?= $base ?>&pg=<?= $prev ?>">&lsaquo; Anterior</a>
    <span class="cur">Pag. <?= $page ?>/<?= $pages ?> - Total <?= (int)$total ?></span>
    <a href="<?= $base ?>&pg=<?= $next ?>">Siguiente &rsaquo;</a>
    <a href="<?= $base ?>&pg=<?= $pages ?>">Ultimo &raquo;</a>
</div>

<div class="modal-back" id="mbCondition">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="conditionModalTitle">
        <h3 id="conditionModalTitle">Nueva condicion</h3>
        <form method="post" id="conditionForm" class="adm-m-0">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="crud_action" id="condition_action" value="create">
            <input type="hidden" name="id" id="condition_id" value="0">

            <div class="grid">
                <label><span>Nombre</span> <span class="badge">oblig.</span>
                    <input class="inp" type="text" name="name" id="condition_name" maxlength="100" required>
                </label>
                <label><span>Categoria</span> <span class="badge">oblig.</span>
                    <select class="select" name="category" id="condition_category" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($categoryOptions as $category): ?>
                        <option value="<?= h($category) ?>"><?= h($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Max. repeticiones</span>
                    <input class="inp" type="number" name="max_instances" id="condition_max_instances" min="0" max="999" value="1">
                    <span class="adm-help-text">Usa 1 para condiciones unicas. Usa 0 para sin limite.</span>
                </label>
                <label class="field-full"><span>Origen</span>
                    <select class="select" name="bibliography_id" id="condition_bibliography_id">
                        <option value="0">-- Sin origen --</option>
                        <?php foreach ($optsOrigins as $originId => $originName): ?>
                        <option value="<?= (int)$originId ?>"><?= h($originName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field-full"><span>Descripcion</span> <span class="badge">oblig.</span>
                    <textarea class="inp ta-lg" name="description" id="condition_description" required></textarea>
                </label>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn" id="btnConditionCancel">Cancelar</button>
                <button type="submit" class="btn btn-green">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-back" id="mbConditionDel">
    <div class="modal adm-modal-sm">
        <h3>Confirmar borrado</h3>
        <div class="adm-help-text">
            Se eliminara la condicion definitivamente.
        </div>
        <form method="post" id="conditionDelForm" class="adm-m-0">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="crud_action" value="delete">
            <input type="hidden" name="id" id="condition_del_id" value="0">
            <div class="modal-actions">
                <button type="button" class="btn" id="btnConditionDelCancel">Cancelar</button>
                <button type="submit" class="btn btn-red">Borrar</button>
            </div>
        </form>
    </div>
</div>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
var CONDITION_ROWS = <?= json_encode($rowMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;
(function(){
    var modal = document.getElementById('mbCondition');
    var delModal = document.getElementById('mbConditionDel');
    var form = document.getElementById('conditionForm');
    var delForm = document.getElementById('conditionDelForm');
    var input = document.getElementById('quickFilterConditions');
    var filterForm = document.getElementById('conditionFilterForm');
    var filterBtn = document.getElementById('btnApplyConditionFilter');
    var tbody = document.getElementById('conditionsTbody');
    var pager = document.getElementById('conditionsPager');
    var pagerCur = pager ? pager.querySelector('.cur') : null;
    var categorySelect = document.getElementById('condition_category');
    if (!input || !tbody || !form || !delForm || !modal || !delModal) return;

    var defaultQuery = input.value || '';
    var initialHtml = tbody.innerHTML;
    var reqSeq = 0;
    var timer = null;

    function esc(s){
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function endpointUrl(mode, term){
        var url = new URL(window.location.href);
        url.searchParams.set('s', 'admin_character_conditions');
        url.searchParams.set('ajax', '1');
        if (mode) url.searchParams.set('ajax_mode', mode);
        if (typeof term === 'string') {
            if (term.trim()) url.searchParams.set('q', term.trim());
            else url.searchParams.delete('q');
        }
        url.searchParams.set('_ts', Date.now());
        return url.toString();
    }

    function errorMessage(err){
        if (window.HGAdminHttp && typeof window.HGAdminHttp.errorMessage === 'function') {
            return window.HGAdminHttp.errorMessage(err);
        }
        return (err && (err.message || err.error)) ? (err.message || err.error) : 'Error';
    }

    function request(url, opts){
        if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
            return window.HGAdminHttp.request(url, opts || {});
        }
        return fetch(url, Object.assign({
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }, opts || {})).then(function(r){ return r.json(); });
    }

    function ensureCategoryOption(value){
        var v = String(value || '').trim();
        if (!categorySelect || v === '') return;
        var exists = Array.prototype.some.call(categorySelect.options, function(opt){ return String(opt.value) === v; });
        if (!exists) {
            var opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            categorySelect.appendChild(opt);
        }
    }

    function openCreate(){
        document.getElementById('conditionModalTitle').textContent = 'Nueva condicion';
        document.getElementById('condition_action').value = 'create';
        document.getElementById('condition_id').value = '0';
        document.getElementById('condition_name').value = '';
        document.getElementById('condition_category').value = '';
        document.getElementById('condition_max_instances').value = '1';
        document.getElementById('condition_bibliography_id').value = '0';
        document.getElementById('condition_description').value = '';
        modal.style.display = 'flex';
        setTimeout(function(){ document.getElementById('condition_name').focus(); }, 0);
    }

    function openEdit(id){
        var row = CONDITION_ROWS[String(id)];
        if (!row) return;
        document.getElementById('conditionModalTitle').textContent = 'Editar condicion';
        document.getElementById('condition_action').value = 'update';
        document.getElementById('condition_id').value = String(id);
        document.getElementById('condition_name').value = row.name || '';
        ensureCategoryOption(row.category || '');
        document.getElementById('condition_category').value = row.category || '';
        document.getElementById('condition_max_instances').value = String(parseInt(row.max_instances || 0, 10) || 0);
        document.getElementById('condition_bibliography_id').value = String(parseInt(row.bibliography_id || 0, 10) || 0);
        document.getElementById('condition_description').value = row.description || '';
        modal.style.display = 'flex';
        setTimeout(function(){ document.getElementById('condition_name').focus(); }, 0);
    }

    function openDelete(id){
        document.getElementById('condition_del_id').value = String(id || 0);
        delModal.style.display = 'flex';
    }

    function closeAll(){
        modal.style.display = 'none';
        delModal.style.display = 'none';
    }

    function bindRowButtons(scope){
        Array.prototype.forEach.call((scope || document).querySelectorAll('button[data-edit]'), function(btn){
            btn.onclick = function(){ openEdit(parseInt(btn.getAttribute('data-edit') || '0', 10) || 0); };
        });
        Array.prototype.forEach.call((scope || document).querySelectorAll('button[data-del]'), function(btn){
            btn.onclick = function(){ openDelete(parseInt(btn.getAttribute('data-del') || '0', 10) || 0); };
        });
    }

    function renderRows(rows, rowMap){
        CONDITION_ROWS = rowMap || {};
        if (!rows || !rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="adm-color-muted">(Sin resultados)</td></tr>';
            if (pagerCur) pagerCur.textContent = 'Total 0 (vista AJAX)';
            return;
        }
        var html = '';
        rows.forEach(function(r){
            html += '<tr>'
                + '<td><strong class="adm-color-accent">'+(parseInt(r.id || 0, 10) || 0)+'</strong></td>'
                + '<td>'+esc(r.name)+'</td>'
                + '<td>'+esc(r.category)+'</td>'
                + '<td>'+esc(r.max_instances === null ? 'Sin limite' : String(parseInt(r.max_instances || 0, 10) || 0))+'</td>'
                + '<td>'+esc(r.origin_name)+'</td>'
                + '<td class="adm-cell-wrap">'+esc(r.description)+'</td>'
                + '<td>'
                + '<button class="btn" type="button" data-edit="'+(parseInt(r.id || 0, 10) || 0)+'">Editar</button> '
                + '<button class="btn btn-red" type="button" data-del="'+(parseInt(r.id || 0, 10) || 0)+'">Borrar</button>'
                + '</td>'
                + '</tr>';
        });
        tbody.innerHTML = html;
        bindRowButtons(tbody);
        if (pagerCur) pagerCur.textContent = 'Total ' + rows.length + ' (vista AJAX)';
    }

    function runSearch(forceAjax){
        var term = (input.value || '').trim();
        if (!forceAjax && term === '') {
            tbody.innerHTML = initialHtml;
            if (pager) pager.style.display = '';
            bindRowButtons(tbody);
            return Promise.resolve();
        }
        var mySeq = ++reqSeq;
        if (pager) pager.style.display = 'none';
        return request(endpointUrl('search', term), { method: 'GET' })
            .then(function(data){
                if (mySeq !== reqSeq) return;
                if (!data || data.ok !== true) return;
                renderRows(data.rows || [], data.rowMap || {});
            })
            .catch(function(){
                if (!forceAjax && term === '' && pager) pager.style.display = '';
            });
    }

    document.getElementById('btnNewCondition').addEventListener('click', openCreate);
    document.getElementById('btnConditionCancel').addEventListener('click', closeAll);
    document.getElementById('btnConditionDelCancel').addEventListener('click', closeAll);
    modal.addEventListener('click', function(e){ if (e.target === modal) closeAll(); });
    delModal.addEventListener('click', function(e){ if (e.target === delModal) closeAll(); });
    bindRowButtons(document);

    form.addEventListener('submit', function(ev){
        ev.preventDefault();
        var fd = new FormData(form);
        fd.set('ajax', '1');
        request(endpointUrl('', ''), {
            method: 'POST',
            body: fd,
            loadingEl: form
        }).then(function(payload){
            closeAll();
            if (window.HGAdminHttp && window.HGAdminHttp.notify) {
                window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok');
            }
            return runSearch(true);
        }).catch(function(err){
            alert(errorMessage(err));
        });
    });

    delForm.addEventListener('submit', function(ev){
        ev.preventDefault();
        var fd = new FormData(delForm);
        fd.set('ajax', '1');
        request(endpointUrl('', ''), {
            method: 'POST',
            body: fd,
            loadingEl: delForm
        }).then(function(payload){
            closeAll();
            if (window.HGAdminHttp && window.HGAdminHttp.notify) {
                window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Eliminado', 'ok');
            }
            return runSearch(true);
        }).catch(function(err){
            alert(errorMessage(err));
        });
    });

    input.addEventListener('input', function(){
        clearTimeout(timer);
        timer = setTimeout(runSearch, 180);
    });
    if (filterForm) {
        filterForm.addEventListener('submit', function(e){
            e.preventDefault();
            runSearch();
        });
    }
    if (filterBtn) {
        filterBtn.addEventListener('click', function(){
            runSearch();
        });
    }

    window.addEventListener('popstate', function(){
        var usp = new URLSearchParams(window.location.search || '');
        input.value = usp.get('q') || defaultQuery || '';
        runSearch(true);
    });
})();
</script>
<?php admin_panel_close(); ?>
