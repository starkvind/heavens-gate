<?php
// admin_characters_clone.php - Clonado de personajes entre cronicas/realidades.

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../domains/characters/admin_clone.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

if (!function_exists('hg_acc_h')) {
    function hg_acc_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_characters_clone';
$CSRF = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : '';

if (!function_exists('hg_acc_csrf_ok')) {
    function hg_acc_csrf_ok(): bool {
        $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
        $token = function_exists('hg_admin_extract_csrf_token')
            ? hg_admin_extract_csrf_token($payload)
            : (string)($_POST['csrf'] ?? '');
        if (function_exists('hg_admin_csrf_valid')) {
            return hg_admin_csrf_valid($token, 'csrf_admin_characters_clone');
        }
        return is_string($token) && $token !== '' && isset($_SESSION['csrf_admin_characters_clone']) && hash_equals($_SESSION['csrf_admin_characters_clone'], $token);
    }
}

$flash = [];

$isAjaxCrudRequest = (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ((string)($_POST['crud_action'] ?? '') === 'clone_character')
    && (
        ((string)($_POST['ajax'] ?? '') === '1')
        || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
    )
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['crud_action'] ?? '') === 'clone_character') {
    if ($isAjaxCrudRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }

    if (!hg_acc_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $sourceCharacterId = (int)($_POST['source_character_id'] ?? 0);
        $targetChronicleId = (int)($_POST['target_chronicle_id'] ?? 0);
        $targetRealityId = (int)($_POST['target_reality_id'] ?? 0);

        try {
            $result = hg_acc_clone_character($link, $sourceCharacterId, $targetChronicleId, $targetRealityId);
            $flash[] = [
                'type' => 'ok',
                'msg' => 'Personaje clonado como #' . (int)$result['new_character_id']
                    . ' (' . (int)($result['bridge']['rows_inserted'] ?? 0) . ' filas bridge copiadas).'
            ];
        } catch (Throwable $e) {
            $flash[] = ['type' => 'error', 'msg' => $e->getMessage()];
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
            'meta' => ['module' => 'admin_characters_clone'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $okMsg = !empty($messages) ? $messages[count($messages) - 1] : 'Clonado';
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
        'meta' => ['module' => 'admin_characters_clone'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$perPage = isset($_GET['pp']) ? max(10, min(200, (int)$_GET['pp'])) : 50;
$page = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$q = trim((string)($_GET['q'] ?? ''));
$offset = ($page - 1) * $perPage;

$listing = hg_acc_load_listing($link, $q, $page, $perPage);
$optsChronicles = $listing['optsChronicles'];
$optsRealities = $listing['optsRealities'];
$total = $listing['total'];
$pages = $listing['pages'];
$page = $listing['page'];
$offset = $listing['offset'];
$rows = $listing['rows'];
$rowMap = $listing['rowMap'];

$actions = '<span class="adm-flex-right-8"></span>';
admin_panel_open('Copiar personajes entre cronicas', $actions);
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?>
        <div class="<?= $cl ?>"><?= hg_acc_h($m['msg']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="get" id="accFilterForm" class="adm-flex-8-m10">
    <input type="hidden" name="s" value="admin_characters_clone">
    <label class="small">Busqueda
        <input class="inp" type="text" name="q" value="<?= hg_acc_h($q) ?>" placeholder="ID, nombre, cronica o realidad">
    </label>
    <label class="small">Por pag
        <select class="select" name="pp" onchange="this.form.submit()">
            <?php foreach ([25,50,100,200] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn" type="submit">Aplicar</button>
</form>

<div class="adm-table-scroll adm-sticky-actions" tabindex="0" aria-label="Tabla para clonar personajes"><table class="table adm-wide-table" id="accTable">
    <thead>
        <tr>
            <th class="adm-w-70">ID</th>
            <th class="adm-col-name">Nombre</th>
            <th class="adm-w-260">Cronica</th>
            <th class="adm-w-220">Realidad</th>
            <th class="adm-w-170 adm-th-actions">Acciones</th>
        </tr>
    </thead>
    <tbody id="accTbody">
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><strong class="adm-color-accent"><?= (int)$r['id'] ?></strong></td>
            <td><?= hg_acc_h((string)$r['name']) ?></td>
            <td><?= hg_acc_h((string)$r['chronicle_name']) ?></td>
            <td><?= hg_acc_h((string)$r['reality_name']) ?></td>
            <td class="adm-cell-actions"><div class="adm-actions-inline">
                <button
                    class="btn"
                    type="button"
                    data-clone="1"
                    data-id="<?= (int)$r['id'] ?>"
                    data-name="<?= hg_acc_h((string)$r['name']) ?>"
                    data-chronicle-id="<?= (int)$r['chronicle_id'] ?>"
                    data-reality-id="<?= (int)$r['reality_id'] ?>"
                >Copiar</button>
                </div></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="adm-color-muted">(Sin resultados)</td></tr>
        <?php endif; ?>
    </tbody>
</table></div>

<div class="pager" id="accPager">
    <?php
    $base = "/talim?s=admin_characters_clone&pp=" . $perPage . "&q=" . urlencode($q);
    $prev = max(1, $page - 1);
    $next = min($pages, $page + 1);
    ?>
    <a href="<?= $base ?>&pg=1">&laquo; Primero</a>
    <a href="<?= $base ?>&pg=<?= $prev ?>">&lsaquo; Anterior</a>
    <span class="cur">Pag <?= $page ?>/<?= $pages ?> - Total <?= (int)$total ?></span>
    <a href="<?= $base ?>&pg=<?= $next ?>">Siguiente &rsaquo;</a>
    <a href="<?= $base ?>&pg=<?= $pages ?>">Ultimo &raquo;</a>
</div>

<div class="modal-back" id="mbAccClone">
    <div class="modal adm-modal-sm" role="dialog" aria-modal="true" aria-labelledby="accCloneTitle">
        <h3 id="accCloneTitle">Copiar personaje</h3>
        <div class="adm-help-text" id="accCloneSummary">Se creara una copia del personaje seleccionado.</div>
        <form method="post" id="accCloneForm" class="adm-m-0">
            <input type="hidden" name="csrf" value="<?= hg_acc_h($CSRF) ?>">
            <input type="hidden" name="crud_action" value="clone_character">
            <input type="hidden" name="source_character_id" id="acc_source_character_id" value="0">

            <label><span>Cronica destino</span>
                <select class="select" name="target_chronicle_id" id="acc_target_chronicle_id" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($optsChronicles as $cid => $cname): ?>
                    <option value="<?= (int)$cid ?>"><?= hg_acc_h($cname) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Realidad destino</span>
                <select class="select" name="target_reality_id" id="acc_target_reality_id" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($optsRealities as $rid => $rname): ?>
                    <option value="<?= (int)$rid ?>"><?= hg_acc_h($rname) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="adm-help-text">
                Se copiaran los bridges del personaje excepto:
                <code>bridge_characters_groups</code>,
                <code>bridge_characters_items</code>,
                <code>bridge_characters_relations</code>,
                <code>bridge_timeline_events_characters</code>.
            </div>

            <div class="modal-actions">
                <button type="button" class="btn" id="btnAccCancel">Cancelar</button>
                <button type="submit" class="btn btn-green">Clonar personaje</button>
            </div>
        </form>
    </div>
</div>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script src="<?= hg_acc_h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
(function(){
    var modal = document.getElementById('mbAccClone');
    var form = document.getElementById('accCloneForm');
    var summary = document.getElementById('accCloneSummary');
    var sourceIdInput = document.getElementById('acc_source_character_id');
    var targetChronicleInput = document.getElementById('acc_target_chronicle_id');
    var targetRealityInput = document.getElementById('acc_target_reality_id');
    var cancelBtn = document.getElementById('btnAccCancel');

    if (!modal || !form || !sourceIdInput || !targetChronicleInput || !targetRealityInput) return;

    function endpointUrl(){
        var url = new URL(window.location.href);
        url.searchParams.set('s', 'admin_characters_clone');
        url.searchParams.set('ajax', '1');
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
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }, opts || {})).then(function(r){ return r.json(); });
    }

    function openCloneFromButton(btn){
        var id = parseInt(btn.getAttribute('data-id') || '0', 10) || 0;
        var name = String(btn.getAttribute('data-name') || '').trim();
        var chronicleId = parseInt(btn.getAttribute('data-chronicle-id') || '0', 10) || 0;
        var realityId = parseInt(btn.getAttribute('data-reality-id') || '0', 10) || 0;
        if (id <= 0) return;

        sourceIdInput.value = String(id);
        targetChronicleInput.value = chronicleId > 0 ? String(chronicleId) : '';
        targetRealityInput.value = realityId > 0 ? String(realityId) : '';
        summary.textContent = 'Se clona #' + id + (name ? (' - ' + name) : '') + ' con todos sus datos y bridges permitidos.';
        modal.style.display = 'flex';
    }

    function closeModal(){
        modal.style.display = 'none';
    }

    Array.prototype.forEach.call(document.querySelectorAll('button[data-clone="1"]'), function(btn){
        btn.addEventListener('click', function(){
            openCloneFromButton(btn);
        });
    });

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e){
        if (e.target === modal) closeModal();
    });

    form.addEventListener('submit', function(ev){
        ev.preventDefault();
        var fd = new FormData(form);
        fd.set('ajax', '1');
        request(endpointUrl(), {
            method: 'POST',
            body: fd,
            loadingEl: form
        }).then(function(payload){
            closeModal();
            var msg = (payload && (payload.message || payload.msg)) || 'Personaje clonado';
            if (window.HGAdminHttp && window.HGAdminHttp.notify) {
                window.HGAdminHttp.notify(msg, 'ok');
            }
            window.location.reload();
        }).catch(function(err){
            alert(errorMessage(err));
        });
    });
})();
</script>
<?php admin_panel_close(); ?>

