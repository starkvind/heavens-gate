<?php
// admin_traits.php - CRUD de dim_traits
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
include_once(__DIR__ . '/../../helpers/pretty.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fetchPairs(mysqli $link, string $sql): array {
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

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_traits';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $t = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($t, 'csrf_admin_traits');
    }
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_traits']) && hash_equals($_SESSION['csrf_admin_traits'], $t);
}

$perPage = isset($_GET['pp']) ? max(10, min(200, (int)$_GET['pp'])) : 25;
$page    = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$q       = trim((string)($_GET['q'] ?? ''));
$offset  = ($page - 1) * $perPage;
$flash = [];

$opts_origins = fetchPairs($link, "SELECT id, name FROM dim_bibliographies ORDER BY name");
$opts_kinds = [];
if ($rs = $link->query("SELECT DISTINCT kind FROM dim_traits WHERE kind IS NOT NULL AND TRIM(kind) <> '' ORDER BY kind ASC")) {
    while ($r = $rs->fetch_assoc()) {
        $opts_kinds[] = (string)($r['kind'] ?? '');
    }
    $rs->close();
}
$opts_classifications = [];
if ($rs = $link->query("SELECT DISTINCT classification FROM dim_traits WHERE classification IS NOT NULL AND TRIM(classification) <> '' ORDER BY classification ASC")) {
    while ($r = $rs->fetch_assoc()) {
        $opts_classifications[] = (string)($r['classification'] ?? '');
    }
    $rs->close();
}

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
    if (!csrf_ok()) {
        $flash[] = ['type'=>'error','msg'=>'CSRF inválido. Recarga la página.'];
    } else {
        $action = (string)($_POST['crud_action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        $name = trim((string)($_POST['name'] ?? ''));
        $kind = trim((string)($_POST['kind'] ?? ''));
        $classification = trim((string)($_POST['classification'] ?? ''));
        $description = (string)($_POST['description'] ?? '');
        $levels = (string)($_POST['levels'] ?? '');
        $posse = (string)($_POST['posse'] ?? '');
        $special = (string)($_POST['special'] ?? '');
        $bibliography_id = (int)($_POST['bibliography_id'] ?? 0);

        $description = hg_mentions_convert($link, $description);
        $levels = hg_mentions_convert($link, $levels);
        $posse = hg_mentions_convert($link, $posse);
        $special = hg_mentions_convert($link, $special);

        if ($action !== 'delete') {
            if ($name === '') $flash[] = ['type'=>'error','msg'=>'Nombre obligatorio.'];
            if ($kind === '') $flash[] = ['type'=>'error','msg'=>'Tipo obligatorio.'];
            if ($classification === '') $flash[] = ['type'=>'error','msg'=>'Clasificacion obligatoria.'];
            if (trim(strip_tags($description)) === '') $flash[] = ['type'=>'error','msg'=>'Descripción obligatoria.'];
        }

        $hasErr = false;
        foreach ($flash as $m) { if (($m['type'] ?? '') === 'error') { $hasErr = true; break; } }

        if (!$hasErr) {
            if ($action === 'create') {
                $sql = "INSERT INTO dim_traits (name, kind, classification, description, levels, posse, special, bibliography_id, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NOW(), NOW())";
                $st = $link->prepare($sql);
                if (!$st) {
                    $flash[] = ['type'=>'error','msg'=>'Error al preparar INSERT: '.$link->error];
                } else {
                    $st->bind_param("sssssssi", $name, $kind, $classification, $description, $levels, $posse, $special, $bibliography_id);
                    if ($st->execute()) {
                        $newId = (int)$link->insert_id;
                        hg_update_pretty_id_if_exists($link, 'dim_traits', $newId, $name);
                        $flash[] = ['type'=>'ok','msg'=>'Trait creado correctamente.'];
                    } else {
                        $flash[] = ['type'=>'error','msg'=>'Error al crear: '.$st->error];
                    }
                    $st->close();
                }
            } elseif ($action === 'update') {
                if ($id <= 0) {
                    $flash[] = ['type'=>'error','msg'=>'ID inválido para actualizar.'];
                } else {
                    $sql = "UPDATE dim_traits
                            SET name=?, kind=?, classification=?, description=?, levels=?, posse=?, special=?, bibliography_id=NULLIF(?, 0), updated_at=NOW()
                            WHERE id=?";
                    $st = $link->prepare($sql);
                    if (!$st) {
                        $flash[] = ['type'=>'error','msg'=>'Error al preparar UPDATE: '.$link->error];
                    } else {
                        $st->bind_param("sssssssii", $name, $kind, $classification, $description, $levels, $posse, $special, $bibliography_id, $id);
                        if ($st->execute()) {
                            hg_update_pretty_id_if_exists($link, 'dim_traits', $id, $name);
                            $flash[] = ['type'=>'ok','msg'=>'Trait actualizado.'];
                        } else {
                            $flash[] = ['type'=>'error','msg'=>'Error al actualizar: '.$st->error];
                        }
                        $st->close();
                    }
                }
            } elseif ($action === 'delete') {
                if ($id <= 0) {
                    $flash[] = ['type'=>'error','msg'=>'ID inválido para borrar.'];
                } else {
                    $st = $link->prepare("DELETE FROM dim_traits WHERE id=?");
                    if (!$st) {
                        $flash[] = ['type'=>'error','msg'=>'Error al preparar DELETE: '.$link->error];
                    } else {
                        $st->bind_param("i", $id);
                        if ($st->execute()) $flash[] = ['type'=>'ok','msg'=>'Trait eliminado.'];
                        else $flash[] = ['type'=>'error','msg'=>'Error al borrar: '.$st->error];
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
        $whereAjax .= " AND (name LIKE ? OR kind LIKE ? OR classification LIKE ?)";
        $typesAjax = "sss";
        $needleAjax = "%".$qAjax."%";
        $paramsAjax[] = $needleAjax;
        $paramsAjax[] = $needleAjax;
        $paramsAjax[] = $needleAjax;
    }

    $sqlAjax = "SELECT id, name, kind, classification, description, levels, posse, special, bibliography_id, pretty_id
                FROM dim_traits
                ".$whereAjax."
                ORDER BY id DESC";
    $stAjax = $link->prepare($sqlAjax);
    if ($typesAjax !== '') $stAjax->bind_param($typesAjax, ...$paramsAjax);
    $stAjax->execute();
    $rsAjax = $stAjax->get_result();

    $rowsAjax = [];
    $rowMapAjax = [];
    while ($r = $rsAjax->fetch_assoc()) {
        $r['origin_name'] = $opts_origins[(int)($r['bibliography_id'] ?? 0)] ?? '';
        $rowsAjax[] = $r;
        $rowMapAjax[(int)$r['id']] = $r;
    }
    $stAjax->close();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'rows' => $rowsAjax,
        'rowMap' => $rowMapAjax,
        'total' => count($rowsAjax),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$where = "WHERE 1=1";
$types = "";
$params = [];
if ($q !== '') {
    $where .= " AND (name LIKE ? OR kind LIKE ? OR classification LIKE ?)";
    $types .= "sss";
    $needle = "%".$q."%";
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
}

$sqlCnt = "SELECT COUNT(*) AS c FROM dim_traits ".$where;
$stC = $link->prepare($sqlCnt);
if ($types !== '') $stC->bind_param($types, ...$params);
$stC->execute();
$rsC = $stC->get_result();
$total = ($rsC && ($rowC = $rsC->fetch_assoc())) ? (int)$rowC['c'] : 0;
$stC->close();

$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset= ($page - 1) * $perPage;

$sqlList = "SELECT id, name, kind, classification, description, levels, posse, special, bibliography_id, pretty_id
            FROM dim_traits
            ".$where."
            ORDER BY id DESC
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
    $r['origin_name'] = $opts_origins[(int)($r['bibliography_id'] ?? 0)] ?? '';
    $rows[] = $r;
    $rowMap[(int)$r['id']] = $r;
}
$stL->close();

$actions = '<span class="adm-flex-right-8">'
    . '<button class="btn btn-green" type="button" id="btnNewTrait">+ Nuevo trait</button>'
    . '</span>';
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
admin_panel_open('Traits', $actions);
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = $m['type']==='ok' ? 'ok' : (($m['type'] ?? '')==='error' ? 'err' : 'info'); ?>
        <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="get" id="traitsFilterForm" class="adm-flex-8-m10">
    <input type="hidden" name="s" value="admin_traits">
    <label class="small">Búsqueda
        <input class="inp" type="text" name="q" id="quickFilterTraits" value="<?= h($q) ?>" placeholder="Nombre, tipo o clasificacion (realtime en todo el set)">
    </label>
    <label class="small">Por pag
        <select class="select" name="pp" onchange="this.form.submit()">
            <?php foreach ([25,50,100,200] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage===$pp?'selected':'' ?>><?= $pp ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn" type="button" id="btnApplyTraitsFilter">Aplicar</button>
</form>

<table class="table" id="tablaTraits">
    <thead>
        <tr>
            <th class="adm-w-70">ID</th>
            <th class="adm-w-260">Nombre</th>
            <th class="adm-w-180">Tipo</th>
            <th class="adm-w-180">Clasificacion</th>
            <th class="adm-w-180">Origen</th>
            <th class="adm-w-170">Acciones</th>
        </tr>
    </thead>
    <tbody id="traitsTbody">
        <?php foreach ($rows as $r):
            $search = trim((string)$r['name'].' '.(string)$r['kind'].' '.(string)$r['classification'].' '.(string)$r['origin_name']);
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
            else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><strong class="adm-color-accent"><?= (int)$r['id'] ?></strong></td>
            <td><?= h((string)$r['name']) ?></td>
            <td><?= h((string)$r['kind']) ?></td>
            <td><?= h((string)$r['classification']) ?></td>
            <td><?= h((string)$r['origin_name']) ?></td>
            <td>
                <button class="btn" type="button" data-edit="<?= (int)$r['id'] ?>">Editar</button>
                <button class="btn btn-red" type="button" data-del="<?= (int)$r['id'] ?>">Borrar</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="adm-color-muted">(Sin resultados)</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="pager" id="traitsPager">
    <?php
    $base = "/talim?s=admin_traits&pp=".$perPage."&q=".urlencode($q);
    $prev = max(1, $page-1);
    $next = min($pages, $page+1);
    ?>
    <a href="<?= $base ?>&pg=1">&laquo; Primero</a>
    <a href="<?= $base ?>&pg=<?= $prev ?>">&lsaquo; Anterior</a>
    <span class="cur">Pag <?= $page ?>/<?= $pages ?> - Total <?= (int)$total ?></span>
    <a href="<?= $base ?>&pg=<?= $next ?>">Siguiente &rsaquo;</a>
    <a href="<?= $base ?>&pg=<?= $pages ?>">Ultimo &raquo;</a>
</div>

<div class="modal-back" id="mbTrait">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="traitModalTitle">
        <h3 id="traitModalTitle">Nuevo trait</h3>
        <form method="post" id="traitForm" class="adm-m-0">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="crud_action" id="trait_action" value="create">
            <input type="hidden" name="id" id="trait_id" value="0">

            <div class="grid">
                <label><span>Nombre</span> <span class="badge">oblig.</span>
                    <input class="inp" type="text" name="name" id="trait_name" maxlength="100" required>
                </label>
                <label><span>Tipo</span> <span class="badge">oblig.</span>
                    <select class="select" name="kind" id="trait_kind" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($opts_kinds as $kind): ?>
                        <option value="<?= h($kind) ?>"><?= h($kind) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Clasificacion</span> <span class="badge">oblig.</span>
                    <select class="select" name="classification" id="trait_classification" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($opts_classifications as $classification): ?>
                        <option value="<?= h($classification) ?>"><?= h($classification) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Origen</span>
                    <select class="select" name="bibliography_id" id="trait_bibliography_id">
                        <option value="0">-</option>
                        <?php foreach ($opts_origins as $oid => $oname): ?>
                        <option value="<?= (int)$oid ?>"><?= h($oname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field-full"><span>Descripción</span> <span class="badge">oblig.</span>
                    <textarea class="inp ta-lg" name="description" id="trait_description"></textarea>
                </label>
                <label class="field-full"><span>Niveles</span>
                    <textarea class="inp ta-md" name="levels" id="trait_levels"></textarea>
                </label>
                <label class="field-full"><span>Posse</span>
                    <textarea class="inp ta-md" name="posse" id="trait_posse"></textarea>
                </label>
                <label class="field-full"><span>Especial</span>
                    <textarea class="inp ta-md" name="special" id="trait_special"></textarea>
                </label>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn" id="btnTraitCancel">Cancelar</button>
                <button type="submit" class="btn btn-green">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-back" id="mbTraitDel">
    <div class="modal adm-modal-sm">
        <h3>Confirmar borrado</h3>
        <div class="adm-help-text">
            Se eliminara el trait definitivamente.
        </div>
        <form method="post" id="traitDelForm" class="adm-m-0">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="crud_action" value="delete">
            <input type="hidden" name="id" id="trait_del_id" value="0">
            <div class="modal-actions">
                <button type="button" class="btn" id="btnTraitDelCancel">Cancelar</button>
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
var TRAITS = <?= json_encode($rowMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;
(function(){
    var modal = document.getElementById('mbTrait');
    var delModal = document.getElementById('mbTraitDel');
    var form = document.getElementById('traitForm');
    var delForm = document.getElementById('traitDelForm');
    var input = document.getElementById('quickFilterTraits');
    var filterForm = document.getElementById('traitsFilterForm');
    var filterBtn = document.getElementById('btnApplyTraitsFilter');
    var tbody = document.getElementById('traitsTbody');
    var pager = document.getElementById('traitsPager');
    var pagerCur = pager ? pager.querySelector('.cur') : null;
    var kindSelect = document.getElementById('trait_kind');
    var classificationSelect = document.getElementById('trait_classification');
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
        url.searchParams.set('s', 'admin_traits');
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

    function ensureKindOption(value){
        var v = String(value || '').trim();
        if (!kindSelect || v === '') return;
        var exists = Array.prototype.some.call(kindSelect.options, function(opt){ return String(opt.value) === v; });
        if (!exists) {
            var opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            kindSelect.appendChild(opt);
        }
    }

    function ensureClassificationOption(value){
        var v = String(value || '').trim();
        if (!classificationSelect || v === '') return;
        var exists = Array.prototype.some.call(classificationSelect.options, function(opt){ return String(opt.value) === v; });
        if (!exists) {
            var opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            classificationSelect.appendChild(opt);
        }
    }

    function openCreate(){
        document.getElementById('traitModalTitle').textContent = 'Nuevo trait';
        document.getElementById('trait_action').value = 'create';
        document.getElementById('trait_id').value = '0';
        document.getElementById('trait_name').value = '';
        document.getElementById('trait_kind').value = '';
        document.getElementById('trait_classification').value = '';
        document.getElementById('trait_bibliography_id').value = '0';
        document.getElementById('trait_description').value = '';
        document.getElementById('trait_levels').value = '';
        document.getElementById('trait_posse').value = '';
        document.getElementById('trait_special').value = '';
        modal.style.display = 'flex';
        setTimeout(function(){ document.getElementById('trait_name').focus(); }, 0);
    }

    function openEdit(id){
        var row = TRAITS[String(id)];
        if (!row) return;
        document.getElementById('traitModalTitle').textContent = 'Editar trait';
        document.getElementById('trait_action').value = 'update';
        document.getElementById('trait_id').value = String(id);
        document.getElementById('trait_name').value = row.name || '';
        ensureKindOption(row.kind || '');
        document.getElementById('trait_kind').value = row.kind || '';
        ensureClassificationOption(row.classification || '');
        document.getElementById('trait_classification').value = row.classification || '';
        document.getElementById('trait_bibliography_id').value = String(parseInt(row.bibliography_id || 0, 10) || 0);
        document.getElementById('trait_description').value = row.description || '';
        document.getElementById('trait_levels').value = row.levels || '';
        document.getElementById('trait_posse').value = row.posse || '';
        document.getElementById('trait_special').value = row.special || '';
        modal.style.display = 'flex';
        setTimeout(function(){ document.getElementById('trait_name').focus(); }, 0);
    }

    function openDelete(id){
        document.getElementById('trait_del_id').value = String(id || 0);
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
        TRAITS = rowMap || {};
        if (!rows || !rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="adm-color-muted">(Sin resultados)</td></tr>';
            if (pagerCur) pagerCur.textContent = 'Total 0 (vista AJAX)';
            return;
        }
        var html = '';
        rows.forEach(function(r){
            html += '<tr>'
                + '<td><strong class="adm-color-accent">'+(parseInt(r.id || 0, 10) || 0)+'</strong></td>'
                + '<td>'+esc(r.name)+'</td>'
                + '<td>'+esc(r.kind)+'</td>'
                + '<td>'+esc(r.classification)+'</td>'
                + '<td>'+esc(r.origin_name)+'</td>'
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

    document.getElementById('btnNewTrait').addEventListener('click', openCreate);
    document.getElementById('btnTraitCancel').addEventListener('click', closeAll);
    document.getElementById('btnTraitDelCancel').addEventListener('click', closeAll);
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




