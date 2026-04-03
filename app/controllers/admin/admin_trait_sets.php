<?php
// admin_trait_sets.php - Configurar traits por sistema (AJAX)
include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_trait_sets';
$CSRF = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : ((empty($_SESSION[$ADMIN_CSRF_SESSION_KEY]) ? ($_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16))) : $_SESSION[$ADMIN_CSRF_SESSION_KEY]));

function trait_sets_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($token, 'csrf_admin_trait_sets');
    }
    return is_string($token) && $token !== '' && isset($_SESSION['csrf_admin_trait_sets']) && hash_equals($_SESSION['csrf_admin_trait_sets'], $token);
}

function trait_sets_load_systems(mysqli $link): array {
    $systems = [];
    if ($rs = $link->query('SELECT id, name FROM dim_systems ORDER BY name')) {
        while ($r = $rs->fetch_assoc()) {
            $systems[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];
        }
        $rs->close();
    }
    return $systems;
}

function trait_sets_load_state(mysqli $link, int $systemId): array {
    $traitsByType = [];
    $traitOrderFixed = ['Atributos','Talentos','Tecnicas','Conocimientos','Trasfondos'];

    if ($st = $link->prepare("SELECT id, name, kind AS tipo FROM dim_traits WHERE kind IS NOT NULL AND TRIM(kind) <> '' ORDER BY kind, name")) {
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) {
            $tipo = (string)$r['tipo'];
            if (!isset($traitsByType[$tipo])) $traitsByType[$tipo] = [];
            $traitsByType[$tipo][] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];
        }
        $st->close();
    }

    $traitTypes = $traitOrderFixed;
    foreach (array_keys($traitsByType) as $tipo) {
        if (!in_array($tipo, $traitTypes, true)) $traitTypes[] = $tipo;
    }

    $existing = [];
    if ($systemId > 0) {
        if ($st = $link->prepare('SELECT trait_id, sort_order, is_active FROM fact_trait_sets WHERE system_id = ?')) {
            $st->bind_param('i', $systemId);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) {
                $existing[(int)$r['trait_id']] = [
                    'sort_order' => (int)$r['sort_order'],
                    'is_active' => (int)$r['is_active'],
                ];
            }
            $st->close();
        }
    }

    $groups = [];
    foreach ($traitTypes as $tipo) {
        $list = $traitsByType[$tipo] ?? [];
        if (empty($list)) continue;
        $traits = [];
        foreach ($list as $t) {
            $tid = (int)$t['id'];
            $ex = $existing[$tid] ?? null;
            $traits[] = [
                'id' => $tid,
                'name' => (string)$t['name'],
                'checked' => ($ex && (int)$ex['is_active'] === 1),
                'sort_order' => $ex ? (int)$ex['sort_order'] : 0,
            ];
        }
        $groups[] = ['type' => $tipo, 'traits' => $traits];
    }

    return [
        'system_id' => $systemId,
        'groups' => $groups,
    ];
}

function trait_sets_save(mysqli $link, int $systemId, array $includeRaw, array $sortRaw): array {
    if ($systemId <= 0) {
        return ['ok' => false, 'message' => 'Sistema invalido.'];
    }

    $include = array_map('intval', $includeRaw);
    $include = array_values(array_filter($include, static function($v){ return $v > 0; }));

    $link->begin_transaction();
    $ok = true;

    if (empty($include)) {
        if ($st = $link->prepare('DELETE FROM fact_trait_sets WHERE system_id = ?')) {
            $st->bind_param('i', $systemId);
            $ok = $st->execute();
            $st->close();
        } else {
            $ok = false;
        }
    } else {
        $idList = implode(',', $include);
        $sql = "DELETE FROM fact_trait_sets WHERE system_id={$systemId} AND trait_id NOT IN ({$idList})";
        $ok = $link->query($sql) !== false;
    }

    if ($ok) {
        $sqlUpsert = 'INSERT INTO fact_trait_sets (system_id, trait_id, sort_order, is_active) VALUES (?,?,?,1) '
            . 'ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order), is_active=1, updated_at=NOW()';
        if ($st = $link->prepare($sqlUpsert)) {
            foreach ($include as $tid) {
                $ord = isset($sortRaw[$tid]) ? (int)$sortRaw[$tid] : 0;
                $st->bind_param('iii', $systemId, $tid, $ord);
                if (!$st->execute()) { $ok = false; break; }
            }
            $st->close();
        } else {
            $ok = false;
        }
    }

    if ($ok) {
        $link->commit();
        return ['ok' => true, 'message' => 'Guardado.'];
    }

    $link->rollback();
    return ['ok' => false, 'message' => 'Error al guardar.'];
}

$systems = trait_sets_load_systems($link);
$systemId = isset($_GET['system_id']) ? (int)$_GET['system_id'] : (int)($_POST['system_id'] ?? 0);
if ($systemId <= 0 && !empty($systems)) $systemId = (int)$systems[0]['id'];

if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
    hg_admin_require_session(true);
}

if ($isAjaxRequest && (string)($_GET['ajax_mode'] ?? '') === 'state') {
    $state = trait_sets_load_state($link, $systemId);
    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success(['systems' => $systems, 'state' => $state], 'Estado');
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'data' => ['systems' => $systems, 'state' => $state]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$flash = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_trait_sets'])) {
    if (!trait_sets_csrf_ok()) {
        if ($isAjaxRequest && function_exists('hg_admin_json_error')) {
            hg_admin_json_error('CSRF invalido. Recarga la pagina.', 400, ['csrf' => 'invalid']);
        }
        $flash[] = ['type' => 'err', 'msg' => 'CSRF invalido.'];
    } else {
        $save = trait_sets_save(
            $link,
            (int)($_POST['system_id'] ?? 0),
            isset($_POST['include']) ? (array)$_POST['include'] : [],
            isset($_POST['sort_order']) && is_array($_POST['sort_order']) ? $_POST['sort_order'] : []
        );
        $systemId = (int)($_POST['system_id'] ?? $systemId);

        if ($isAjaxRequest) {
            $state = trait_sets_load_state($link, $systemId);
            if (!empty($save['ok'])) {
                if (function_exists('hg_admin_json_success')) {
                    hg_admin_json_success(['systems' => $systems, 'state' => $state], (string)$save['message']);
                }
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => true, 'message' => (string)$save['message'], 'data' => ['systems' => $systems, 'state' => $state]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            if (function_exists('hg_admin_json_error')) {
                hg_admin_json_error((string)$save['message'], 400);
            }
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'message' => (string)$save['message']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $flash[] = ['type' => !empty($save['ok']) ? 'ok' : 'err', 'msg' => (string)$save['message']];
    }
}

$initialState = trait_sets_load_state($link, $systemId);

$systemOptions = '';
foreach ($systems as $s) {
    $sid = (int)$s['id'];
    $sel = ($sid === $systemId) ? ' selected' : '';
    $systemOptions .= '<option value="'.$sid.'"'.$sel.'>'.h($s['name']).'</option>';
}

$actions = '<span class="adm-flex-right-8">'
    . '<label class="adm-text-left">Sistema '
    . '<select class="select" id="tsSystem">'.$systemOptions.'</select></label>'
    . '<button class="btn" type="button" id="tsSelectAll">Seleccionar todo</button>'
    . '<button class="btn" type="button" id="tsUnselectAll">Limpiar</button>'
    . '</span>';

if (!$isAjaxRequest) {
    admin_panel_open('Traits por sistema', $actions);
}
?>

<?php if (!empty($flash)): ?>
    <div class="flash">
        <?php foreach ($flash as $f): ?>
            <div class="<?= $f['type'] === 'ok' ? 'ok' : 'err' ?>"><?= h($f['msg']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" id="traitSetsForm">
    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
    <input type="hidden" name="save_trait_sets" value="1">
    <input type="hidden" name="system_id" id="tsSystemId" value="<?= (int)$systemId ?>">

    <div id="traitSetsGrid" class="traits-grid"></div>

    <div class="modal-actions adm-mt-12">
        <button type="submit" class="btn btn-green">Guardar</button>
    </div>
</form>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
const TS_INITIAL_STATE = <?= json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
let tsCurrentState = TS_INITIAL_STATE;

function tsEsc(s){
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function tsRequest(url, opts){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
        return window.HGAdminHttp.request(url, opts || {});
    }
    const cfg = Object.assign({ method: 'GET', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }, opts || {});
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

function tsEndpointUrl(mode, systemId){
    const url = new URL(window.location.href);
    url.searchParams.set('s', 'admin_trait_sets');
    url.searchParams.set('ajax', '1');
    if (mode) url.searchParams.set('ajax_mode', mode);
    else url.searchParams.delete('ajax_mode');
    if (systemId) url.searchParams.set('system_id', String(systemId));
    else url.searchParams.delete('system_id');
    url.searchParams.set('_ts', Date.now());
    return url.toString();
}

function tsRenderState(state){
    tsCurrentState = state || { system_id: 0, groups: [] };
    const systemSel = document.getElementById('tsSystem');
    const systemHidden = document.getElementById('tsSystemId');
    const sid = String((tsCurrentState && tsCurrentState.system_id) ? tsCurrentState.system_id : 0);
    if (systemSel) systemSel.value = sid;
    if (systemHidden) systemHidden.value = sid;

    const grid = document.getElementById('traitSetsGrid');
    if (!grid) return;
    const groups = Array.isArray(tsCurrentState.groups) ? tsCurrentState.groups : [];
    if (!groups.length) {
        grid.innerHTML = '<div class="adm-color-muted">(Sin rasgos disponibles)</div>';
        return;
    }

    let html = '';
    groups.forEach(function(group){
        const type = tsEsc(group.type || '');
        html += '<div class="traits-group">';
        html += '<div class="traits-title">' + type + '</div>';
        (Array.isArray(group.traits) ? group.traits : []).forEach(function(t){
            const tid = parseInt(t.id || 0, 10) || 0;
            if (!tid) return;
            const checked = !!t.checked;
            const ord = parseInt(t.sort_order || 0, 10) || 0;
            html += '<label class="trait-row">';
            html += '<input type="checkbox" name="include[]" value="' + tid + '" ' + (checked ? 'checked' : '') + '>';
            html += '<span>' + tsEsc(t.name || '') + '</span>';
            html += '<input class="inp" type="number" name="sort_order[' + tid + ']" value="' + ord + '">';
            html += '</label>';
        });
        html += '</div>';
    });

    grid.innerHTML = html;
}

function tsLoadState(systemId){
    return tsRequest(tsEndpointUrl('state', systemId), { method: 'GET' }).then(function(payload){
        const data = payload && payload.data ? payload.data : {};
        tsRenderState(data.state || { system_id: systemId, groups: [] });
    }).catch(function(err){
        const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error cargando estado');
        alert(msg);
    });
}

tsRenderState(TS_INITIAL_STATE);

const tsSystem = document.getElementById('tsSystem');
if (tsSystem) {
    tsSystem.addEventListener('change', function(){
        const sid = parseInt(this.value || '0', 10) || 0;
        tsLoadState(sid);
    });
}

const tsSelectAll = document.getElementById('tsSelectAll');
if (tsSelectAll) {
    tsSelectAll.addEventListener('click', function(){
        document.querySelectorAll('#traitSetsGrid input[type="checkbox"][name="include[]"]').forEach(function(cb){ cb.checked = true; });
    });
}

const tsUnselectAll = document.getElementById('tsUnselectAll');
if (tsUnselectAll) {
    tsUnselectAll.addEventListener('click', function(){
        document.querySelectorAll('#traitSetsGrid input[type="checkbox"][name="include[]"]').forEach(function(cb){ cb.checked = false; });
    });
}

const tsForm = document.getElementById('traitSetsForm');
if (tsForm) {
    tsForm.addEventListener('submit', function(ev){
        ev.preventDefault();
        const fd = new FormData(tsForm);
        fd.set('ajax', '1');
        tsRequest(tsEndpointUrl('', document.getElementById('tsSystemId').value || '0'), { method: 'POST', body: fd, loadingEl: tsForm }).then(function(payload){
            const data = payload && payload.data ? payload.data : {};
            tsRenderState(data.state || tsCurrentState);
            if (window.HGAdminHttp && window.HGAdminHttp.notify) {
                window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok');
            }
        }).catch(function(err){
            const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error guardando');
            alert(msg);
        });
    });
}
</script>

<?php if (!$isAjaxRequest) { admin_panel_close(); } ?>

