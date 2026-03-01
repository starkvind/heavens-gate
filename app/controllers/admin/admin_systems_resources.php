<?php
// admin_systems_resources.php - Recursos por sistema (AJAX)
include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');

if (!isset($link) || !($link instanceof mysqli)) { die('DB no disponible.'); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function asr_table_exists(mysqli $link, string $table): bool {
    $st = $link->prepare('SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if (!$st) return false;
    $st->bind_param('s', $table);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    return ((int)($row['c'] ?? 0) > 0);
}

function asr_table_columns(mysqli $link, string $table): array {
    $out = [];
    $safe = str_replace('`', '``', $table);
    $sql = "SHOW COLUMNS FROM `{$safe}`";
    if ($res = $link->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $out[(string)$r['Field']] = true;
        }
        $res->free();
    }
    return $out;
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_systems_resources';
$CSRF = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : ((empty($_SESSION[$ADMIN_CSRF_SESSION_KEY]) ? ($_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16))) : $_SESSION[$ADMIN_CSRF_SESSION_KEY]));

function asr_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($token, 'csrf_admin_systems_resources');
    }
    return is_string($token) && $token !== '' && isset($_SESSION['csrf_admin_systems_resources']) && hash_equals($_SESSION['csrf_admin_systems_resources'], $token);
}

$tblResources = 'dim_systems_resources';
$tblBridge = 'bridge_systems_resources_to_system';
$tblSystems = 'dim_systems';

$hasResources = asr_table_exists($link, $tblResources);
$hasBridge = asr_table_exists($link, $tblBridge);
$hasSystems = asr_table_exists($link, $tblSystems);
$bridgeCols = $hasBridge ? asr_table_columns($link, $tblBridge) : [];

if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
    hg_admin_require_session(true);
}

function asr_load_state(mysqli $link, int $systemId, string $tblSystems, string $tblResources, string $tblBridge, array $bridgeCols): array {
    $systems = [];
    if ($res = $link->query("SELECT id, name FROM `{$tblSystems}` ORDER BY sort_order, name")) {
        while ($r = $res->fetch_assoc()) $systems[] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];
        $res->free();
    }

    if ($systemId <= 0 && !empty($systems)) {
        $systemId = (int)$systems[0]['id'];
    }

    $resources = [];
    if ($res = $link->query("SELECT id, name, kind, sort_order, description FROM `{$tblResources}` ORDER BY kind, sort_order, name")) {
        while ($r = $res->fetch_assoc()) {
            $resources[] = [
                'id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'kind' => (string)$r['kind'],
                'sort_order' => (int)$r['sort_order'],
                'description' => (string)($r['description'] ?? ''),
            ];
        }
        $res->free();
    }

    $current = [];
    if ($systemId > 0) {
        $selCols = ['resource_id'];
        if (isset($bridgeCols['sort_order'])) $selCols[] = 'sort_order';
        if (isset($bridgeCols['is_active'])) $selCols[] = 'is_active';
        if (isset($bridgeCols['position'])) $selCols[] = 'position';

        $sqlCur = "SELECT " . implode(',', $selCols) . " FROM `{$tblBridge}` WHERE system_id = ?";
        if ($st = $link->prepare($sqlCur)) {
            $st->bind_param('i', $systemId);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) {
                $rid = (int)$r['resource_id'];
                $current[$rid] = [
                    'sort_order' => (int)($r['sort_order'] ?? 0),
                    'is_active' => (int)($r['is_active'] ?? 1),
                    'position' => (string)($r['position'] ?? ''),
                ];
            }
            $st->close();
        }
    }

    return [
        'system_id' => $systemId,
        'systems' => $systems,
        'resources' => $resources,
        'current' => $current,
        'flags' => [
            'has_sort' => isset($bridgeCols['sort_order']),
            'has_active' => isset($bridgeCols['is_active']),
            'has_position' => isset($bridgeCols['position']),
        ],
    ];
}

function asr_save(mysqli $link, int $systemId, array $rows, array $selected, string $tblBridge, array $bridgeCols): array {
    if ($systemId <= 0) {
        return ['ok' => false, 'message' => 'Sistema invalido.'];
    }

    $selectedMap = [];
    foreach ($selected as $rid) {
        $rid = (int)$rid;
        if ($rid > 0) $selectedMap[$rid] = true;
    }

    $keepRows = [];
    foreach ($rows as $rid => $row) {
        $rid = (int)$rid;
        if ($rid <= 0 || !isset($selectedMap[$rid])) continue;
        $keepRows[$rid] = [
            'sort_order' => (int)($row['sort_order'] ?? 0),
            'is_active' => isset($row['is_active']) ? 1 : 0,
            'position' => trim((string)($row['position'] ?? '')),
        ];
    }

    $link->begin_transaction();
    try {
        $del = $link->prepare("DELETE FROM `{$tblBridge}` WHERE system_id = ?");
        if (!$del) throw new RuntimeException('No se pudo preparar DELETE.');
        $del->bind_param('i', $systemId);
        $del->execute();
        $del->close();

        $insertCols = ['system_id', 'resource_id'];
        $hasSort = isset($bridgeCols['sort_order']);
        $hasActive = isset($bridgeCols['is_active']);
        $hasPosition = isset($bridgeCols['position']);
        if ($hasSort) $insertCols[] = 'sort_order';
        if ($hasActive) $insertCols[] = 'is_active';
        if ($hasPosition) $insertCols[] = 'position';

        $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
        $sqlIns = "INSERT INTO `{$tblBridge}` (" . implode(',', $insertCols) . ") VALUES ({$placeholders})";
        $ins = $link->prepare($sqlIns);
        if (!$ins) throw new RuntimeException('No se pudo preparar INSERT.');

        foreach ($keepRows as $rid => $row) {
            $sortOrder = (int)$row['sort_order'];
            $isActive = (int)$row['is_active'];
            $position = (string)$row['position'];

            if ($hasSort && $hasActive && $hasPosition) {
                $ins->bind_param('iiiis', $systemId, $rid, $sortOrder, $isActive, $position);
            } elseif ($hasSort && $hasActive && !$hasPosition) {
                $ins->bind_param('iiii', $systemId, $rid, $sortOrder, $isActive);
            } elseif ($hasSort && !$hasActive && $hasPosition) {
                $ins->bind_param('iiis', $systemId, $rid, $sortOrder, $position);
            } elseif (!$hasSort && $hasActive && $hasPosition) {
                $ins->bind_param('iiis', $systemId, $rid, $isActive, $position);
            } elseif ($hasSort && !$hasActive && !$hasPosition) {
                $ins->bind_param('iii', $systemId, $rid, $sortOrder);
            } elseif (!$hasSort && $hasActive && !$hasPosition) {
                $ins->bind_param('iii', $systemId, $rid, $isActive);
            } elseif (!$hasSort && !$hasActive && $hasPosition) {
                $ins->bind_param('iis', $systemId, $rid, $position);
            } else {
                $ins->bind_param('ii', $systemId, $rid);
            }
            $ins->execute();
        }
        $ins->close();

        $link->commit();
        return ['ok' => true, 'message' => 'Guardado correctamente.'];
    } catch (Throwable $e) {
        $link->rollback();
        return ['ok' => false, 'message' => 'Error al guardar: ' . $e->getMessage()];
    }
}

if (!$hasSystems || !$hasResources || !$hasBridge) {
    if ($isAjaxRequest && function_exists('hg_admin_json_error')) {
        $missing = [];
        if (!$hasSystems) $missing[] = 'dim_systems';
        if (!$hasResources) $missing[] = 'dim_systems_resources';
        if (!$hasBridge) $missing[] = 'bridge_systems_resources_to_system';
        hg_admin_json_error('Faltan tablas para este modulo.', 400, ['missing_tables' => $missing]);
    }

    admin_panel_open('Recursos por Sistema', '');
    echo "<div class='flash'><div class='err'>Faltan tablas para este modulo. Revisa: dim_systems, dim_systems_resources, bridge_systems_resources_to_system.</div></div>";
    admin_panel_close();
    return;
}

$systemId = max(0, (int)($_GET['system_id'] ?? $_POST['system_id'] ?? 0));

if ($isAjaxRequest && (string)($_GET['ajax_mode'] ?? '') === 'state') {
    $state = asr_load_state($link, $systemId, $tblSystems, $tblResources, $tblBridge, $bridgeCols);
    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success(['state' => $state], 'Estado');
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'data' => ['state' => $state]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$flash = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_system_resources'])) {
    if (!asr_csrf_ok()) {
        if ($isAjaxRequest && function_exists('hg_admin_json_error')) {
            hg_admin_json_error('CSRF invalido. Recarga la pagina.', 400, ['csrf' => 'invalid']);
        }
        $flash[] = ['type' => 'err', 'msg' => 'CSRF invalido.'];
    } else {
        $save = asr_save(
            $link,
            max(0, (int)($_POST['system_id'] ?? 0)),
            isset($_POST['rows']) && is_array($_POST['rows']) ? $_POST['rows'] : [],
            isset($_POST['selected']) ? (array)$_POST['selected'] : [],
            $tblBridge,
            $bridgeCols
        );
        $systemId = max(0, (int)($_POST['system_id'] ?? $systemId));

        if ($isAjaxRequest) {
            $state = asr_load_state($link, $systemId, $tblSystems, $tblResources, $tblBridge, $bridgeCols);
            if (!empty($save['ok'])) {
                if (function_exists('hg_admin_json_success')) {
                    hg_admin_json_success(['state' => $state], (string)$save['message']);
                }
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => true, 'message' => (string)$save['message'], 'data' => ['state' => $state]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

$initialState = asr_load_state($link, $systemId, $tblSystems, $tblResources, $tblBridge, $bridgeCols);

$sysOptions = '<option value="0">-- Selecciona sistema --</option>';
foreach (($initialState['systems'] ?? []) as $s) {
    $sid = (int)$s['id'];
    $sel = ($sid === (int)$initialState['system_id']) ? ' selected' : '';
    $sysOptions .= '<option value="'.$sid.'"'.$sel.'>'.h($s['name']).' (#'.$sid.')</option>';
}

$actions = '<span class="adm-flex-right-8">'
    . '<label class="adm-text-left">Sistema '
    . '<select class="select" id="asrSystem">'.$sysOptions.'</select></label>'
    . '<label class="adm-text-left">Filtro rapido '
    . '<input class="inp" type="text" id="asrQuickFilter" placeholder="Recurso o kind..."></label>'
    . '</span>';

if (!$isAjaxRequest) {
    admin_panel_open('Recursos por Sistema', $actions);
}
?>

<?php if (!empty($flash)): ?>
    <div class="flash">
        <?php foreach ($flash as $f): ?>
            <div class="<?= $f['type'] === 'ok' ? 'ok' : 'err' ?>"><?= h($f['msg']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" id="asrForm">
    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
    <input type="hidden" name="save_system_resources" value="1">
    <input type="hidden" name="system_id" id="asrSystemId" value="<?= (int)$initialState['system_id'] ?>">

    <table class="table" id="asrTable">
        <thead>
            <tr>
                <th>Usar</th>
                <th>Recurso</th>
                <th>Kind</th>
                <th>Orden</th>
                <th id="asrPositionHeader" class="adm-hidden">Posicion</th>
                <th>Activo</th>
            </tr>
        </thead>
        <tbody id="asrTbody"></tbody>
    </table>

    <div class="modal-actions adm-mt-12">
        <button type="submit" class="btn btn-green">Guardar mapeo</button>
    </div>
</form>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
const ASR_INITIAL_STATE = <?= json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
let asrState = ASR_INITIAL_STATE;

function asrEsc(s){
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function asrRequest(url, opts){
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

function asrEndpointUrl(mode, systemId){
    const url = new URL(window.location.href);
    url.searchParams.set('s', 'admin_systems_resources');
    url.searchParams.set('ajax', '1');
    if (mode) url.searchParams.set('ajax_mode', mode);
    else url.searchParams.delete('ajax_mode');
    if (systemId) url.searchParams.set('system_id', String(systemId));
    else url.searchParams.delete('system_id');
    url.searchParams.set('_ts', Date.now());
    return url.toString();
}

function asrRender(state){
    asrState = state || { system_id: 0, resources: [], current: {}, flags: {} };
    const sid = parseInt(asrState.system_id || 0, 10) || 0;

    const sel = document.getElementById('asrSystem');
    if (sel) sel.value = String(sid);
    const hidden = document.getElementById('asrSystemId');
    if (hidden) hidden.value = String(sid);

    const flags = asrState.flags || {};
    const showPosition = !!flags.has_position;
    const positionHeader = document.getElementById('asrPositionHeader');
    if (positionHeader) {
        positionHeader.classList.toggle('adm-hidden', !showPosition);
    }

    const tbody = document.getElementById('asrTbody');
    if (!tbody) return;
    const resources = Array.isArray(asrState.resources) ? asrState.resources : [];
    const current = asrState.current || {};

    if (!resources.length) {
        const emptyCols = showPosition ? 6 : 5;
        tbody.innerHTML = '<tr><td colspan="' + emptyCols + '" class="adm-color-muted">(Sin recursos)</td></tr>';
        return;
    }

    let html = '';
    resources.forEach(function(r){
        const rid = parseInt(r.id || 0, 10) || 0;
        if (!rid) return;

        const cur = current[rid] || current[String(rid)] || null;
        const isLinked = !!cur;
        const sortOrder = cur ? (parseInt(cur.sort_order || 0, 10) || 0) : (parseInt(r.sort_order || 0, 10) || 0);
        const isActive = cur ? (parseInt(cur.is_active || 0, 10) === 1) : true;
        const position = cur ? String(cur.position || '') : '';

        const search = (String(r.name || '') + ' ' + String(r.kind || '')).toLowerCase();

        html += '<tr data-search="' + asrEsc(search) + '">';
        html += '<td><input type="checkbox" name="selected[]" value="' + rid + '" ' + (isLinked ? 'checked' : '') + '></td>';
        html += '<td>' + asrEsc(r.name || '') + ' <span class="small">#' + rid + '</span></td>';
        html += '<td>' + asrEsc(r.kind || '') + '</td>';
        html += '<td><input class="inp adm-w-70" type="number" name="rows[' + rid + '][sort_order]" value="' + sortOrder + '"></td>';

        if (showPosition) {
            html += '<td><input class="inp" type="text" name="rows[' + rid + '][position]" value="' + asrEsc(position) + '" placeholder="main / reputation"></td>';
        }

        html += '<td><input type="checkbox" name="rows[' + rid + '][is_active]" value="1" ' + (isActive ? 'checked' : '') + '></td>';
        html += '</tr>';
    });

    tbody.innerHTML = html;
    asrApplyQuickFilter();
}

function asrApplyQuickFilter(){
    const qInput = document.getElementById('asrQuickFilter');
    if (!qInput) return;
    const q = (qInput.value || '').toLowerCase();
    document.querySelectorAll('#asrTbody tr').forEach(function(tr){
        const hay = (tr.getAttribute('data-search') || '').toLowerCase();
        tr.style.display = (!q || hay.indexOf(q) !== -1) ? '' : 'none';
    });
}

function asrLoadState(systemId){
    asrRequest(asrEndpointUrl('state', systemId), { method: 'GET' }).then(function(payload){
        const data = payload && payload.data ? payload.data : {};
        asrRender(data.state || {});
    }).catch(function(err){
        const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error cargando estado');
        alert(msg);
    });
}

asrRender(ASR_INITIAL_STATE);

const asrSystem = document.getElementById('asrSystem');
if (asrSystem) {
    asrSystem.addEventListener('change', function(){
        asrLoadState(parseInt(this.value || '0', 10) || 0);
    });
}

const asrFilter = document.getElementById('asrQuickFilter');
if (asrFilter) {
    asrFilter.addEventListener('input', asrApplyQuickFilter);
}

const asrForm = document.getElementById('asrForm');
if (asrForm) {
    asrForm.addEventListener('submit', function(ev){
        ev.preventDefault();
        const fd = new FormData(asrForm);
        fd.set('ajax', '1');
        asrRequest(asrEndpointUrl('', document.getElementById('asrSystemId').value || '0'), { method: 'POST', body: fd, loadingEl: asrForm }).then(function(payload){
            const data = payload && payload.data ? payload.data : {};
            asrRender(data.state || asrState);
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
