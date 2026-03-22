<?php
// admin_merits_flaws.php - CRUD de dim_merits_flaws
if (!isset($link) || !$link) { die("Sin conexion BD"); }
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

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_merits_flaws';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function csrf_ok_myd(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $t = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($t, 'csrf_admin_merits_flaws');
    }
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_merits_flaws']) && hash_equals($_SESSION['csrf_admin_merits_flaws'], $t);
}

$perPage = isset($_GET['pp']) ? max(10, min(200, (int)$_GET['pp'])) : 25;
$page    = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$q       = trim((string)($_GET['q'] ?? ''));
$offset  = ($page - 1) * $perPage;
$flash = [];

$opts_origins = fetchPairs($link, "SELECT id, name FROM dim_bibliographies ORDER BY name");
$opts_systems = fetchPairs($link, "SELECT id, name FROM dim_systems ORDER BY name");
$opts_kinds = [];
if ($rs = $link->query("SELECT DISTINCT kind FROM dim_merits_flaws WHERE kind IS NOT NULL AND TRIM(kind) <> '' ORDER BY kind ASC")) {
    while ($r = $rs->fetch_assoc()) {
        $opts_kinds[] = (string)($r['kind'] ?? '');
    }
    $rs->close();
}
$opts_affiliations = [];
if ($rs = $link->query("SELECT DISTINCT affiliation FROM dim_merits_flaws WHERE affiliation IS NOT NULL AND TRIM(affiliation) <> '' ORDER BY affiliation ASC")) {
    while ($r = $rs->fetch_assoc()) {
        $opts_affiliations[] = (string)($r['affiliation'] ?? '');
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
    if (!csrf_ok_myd()) {
        $flash[] = ['type'=>'error','msg'=>'CSRF invalido. Recarga la pagina.'];
    } else {
        $action = (string)($_POST['crud_action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        $name = trim((string)($_POST['name'] ?? ''));
        $kind = trim((string)($_POST['kind'] ?? ''));
        $affiliation = trim((string)($_POST['affiliation'] ?? ''));
        $cost = trim((string)($_POST['cost'] ?? ''));
        $description = (string)($_POST['description'] ?? '');
        $system_name = trim((string)($_POST['system_name'] ?? ''));
        $system_id = (int)($_POST['system_id'] ?? 0);
        $bibliography_id = (int)($_POST['bibliography_id'] ?? 0);

        if ($system_id < 0) $system_id = 0;
        if ($bibliography_id < 0) $bibliography_id = 0;

        $description = hg_mentions_convert($link, $description);
        if ($system_id > 0 && $system_name === '' && isset($opts_systems[$system_id])) {
            $system_name = (string)$opts_systems[$system_id];
        }

        if ($action !== 'delete') {
            if ($name === '') $flash[] = ['type'=>'error','msg'=>'Nombre obligatorio.'];
            if ($kind === '') $flash[] = ['type'=>'error','msg'=>'Tipo obligatorio.'];
            if ($affiliation === '') $flash[] = ['type'=>'error','msg'=>'Afiliacion obligatoria.'];
            if ($cost === '') $flash[] = ['type'=>'error','msg'=>'Coste obligatorio.'];
            if ($system_name === '') $flash[] = ['type'=>'error','msg'=>'Sistema (texto) obligatorio.'];
            if (trim(strip_tags($description)) === '') $flash[] = ['type'=>'error','msg'=>'Descripcion obligatoria.'];
            if (strlen($cost) > 3) $flash[] = ['type'=>'error','msg'=>'Coste demasiado largo (max 3 caracteres).'];
            if ($system_id > 0 && !isset($opts_systems[$system_id])) {
                $flash[] = ['type'=>'error','msg'=>'Sistema invalido.'];
            }
            if ($bibliography_id > 0 && !isset($opts_origins[$bibliography_id])) {
                $flash[] = ['type'=>'error','msg'=>'Origen invalido.'];
            }
        }

        $hasErr = false;
        foreach ($flash as $m) { if (($m['type'] ?? '') === 'error') { $hasErr = true; break; } }

        if (!$hasErr) {
            if ($action === 'create') {
                $sql = "INSERT INTO dim_merits_flaws
                        (name, kind, affiliation, cost, description, system_name, system_id, bibliography_id, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NOW(), NOW())";
                $st = $link->prepare($sql);
                if (!$st) {
                    $flash[] = ['type'=>'error','msg'=>'Error al preparar INSERT: '.$link->error];
                } else {
                    $st->bind_param("ssssssii", $name, $kind, $affiliation, $cost, $description, $system_name, $system_id, $bibliography_id);
                    if ($st->execute()) {
                        $newId = (int)$link->insert_id;
                        hg_update_pretty_id_if_exists($link, 'dim_merits_flaws', $newId, $name);
                        $flash[] = ['type'=>'ok','msg'=>'Merito/Defecto creado correctamente.'];
                    } else {
                        $flash[] = ['type'=>'error','msg'=>'Error al crear: '.$st->error];
                    }
                    $st->close();
                }
            } elseif ($action === 'update') {
                if ($id <= 0) {
                    $flash[] = ['type'=>'error','msg'=>'ID invalido para actualizar.'];
                } else {
                    $sql = "UPDATE dim_merits_flaws
                            SET name=?, kind=?, affiliation=?, cost=?, description=?, system_name=?, system_id=NULLIF(?, 0), bibliography_id=NULLIF(?, 0), updated_at=NOW()
                            WHERE id=?";
                    $st = $link->prepare($sql);
                    if (!$st) {
                        $flash[] = ['type'=>'error','msg'=>'Error al preparar UPDATE: '.$link->error];
                    } else {
                        $st->bind_param("ssssssiii", $name, $kind, $affiliation, $cost, $description, $system_name, $system_id, $bibliography_id, $id);
                        if ($st->execute()) {
                            hg_update_pretty_id_if_exists($link, 'dim_merits_flaws', $id, $name);
                            $flash[] = ['type'=>'ok','msg'=>'Merito/Defecto actualizado.'];
                        } else {
                            $flash[] = ['type'=>'error','msg'=>'Error al actualizar: '.$st->error];
                        }
                        $st->close();
                    }
                }
            } elseif ($action === 'delete') {
                if ($id <= 0) {
                    $flash[] = ['type'=>'error','msg'=>'ID invalido para borrar.'];
                } else {
                    $st = $link->prepare("DELETE FROM dim_merits_flaws WHERE id=?");
                    if (!$st) {
                        $flash[] = ['type'=>'error','msg'=>'Error al preparar DELETE: '.$link->error];
                    } else {
                        $st->bind_param("i", $id);
                        if ($st->execute()) $flash[] = ['type'=>'ok','msg'=>'Merito/Defecto eliminado.'];
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
            'meta' => ['module' => 'admin_merits_flaws'],
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
        'meta' => ['module' => 'admin_merits_flaws'],
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
        $whereAjax .= " AND (name LIKE ? OR kind LIKE ? OR affiliation LIKE ? OR cost LIKE ? OR system_name LIKE ?)";
        $typesAjax = "sssss";
        $needleAjax = "%".$qAjax."%";
        $paramsAjax[] = $needleAjax;
        $paramsAjax[] = $needleAjax;
        $paramsAjax[] = $needleAjax;
        $paramsAjax[] = $needleAjax;
        $paramsAjax[] = $needleAjax;
    }

    $sqlAjax = "SELECT id, name, kind, affiliation, cost, description, system_name, system_id, bibliography_id, pretty_id
                FROM dim_merits_flaws
                ".$whereAjax."
                ORDER BY id DESC";
    $stAjax = $link->prepare($sqlAjax);
    if ($typesAjax !== '') $stAjax->bind_param($typesAjax, ...$paramsAjax);
    $stAjax->execute();
    $rsAjax = $stAjax->get_result();

    $rowsAjax = [];
    $rowMapAjax = [];
    while ($r = $rsAjax->fetch_assoc()) {
        $sysId = (int)($r['system_id'] ?? 0);
        $sysName = trim((string)($r['system_name'] ?? ''));
        if ($sysName === '' && $sysId > 0 && isset($opts_systems[$sysId])) {
            $r['system_name'] = (string)$opts_systems[$sysId];
        }
        $r['origin_name'] = $opts_origins[(int)($r['bibliography_id'] ?? 0)] ?? '';
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
        'meta' => ['module' => 'admin_merits_flaws', 'mode' => 'search'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$where = "WHERE 1=1";
$types = "";
$params = [];
if ($q !== '') {
    $where .= " AND (name LIKE ? OR kind LIKE ? OR affiliation LIKE ? OR cost LIKE ? OR system_name LIKE ?)";
    $types .= "sssss";
    $needle = "%".$q."%";
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
}

$sqlCnt = "SELECT COUNT(*) AS c FROM dim_merits_flaws ".$where;
$stC = $link->prepare($sqlCnt);
if ($types !== '') $stC->bind_param($types, ...$params);
$stC->execute();
$rsC = $stC->get_result();
$total = ($rsC && ($rowC = $rsC->fetch_assoc())) ? (int)$rowC['c'] : 0;
$stC->close();

$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset= ($page - 1) * $perPage;

$sqlList = "SELECT id, name, kind, affiliation, cost, description, system_name, system_id, bibliography_id, pretty_id
            FROM dim_merits_flaws
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
    $sysId = (int)($r['system_id'] ?? 0);
    $sysName = trim((string)($r['system_name'] ?? ''));
    if ($sysName === '' && $sysId > 0 && isset($opts_systems[$sysId])) {
        $r['system_name'] = (string)$opts_systems[$sysId];
    }
    $r['origin_name'] = $opts_origins[(int)($r['bibliography_id'] ?? 0)] ?? '';
    $rows[] = $r;
    $rowMap[(int)$r['id']] = $r;
}
$stL->close();

$actions = '<span class="adm-flex-right-8">'
    . '<button class="btn btn-green" type="button" id="btnNewMyd">+ Nuevo merito/defecto</button>'
    . '</span>';
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
admin_panel_open('Meritos y Defectos', $actions);
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = $m['type']==='ok' ? 'ok' : (($m['type'] ?? '')==='error' ? 'err' : 'info'); ?>
        <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="get" id="mydFilterForm" class="adm-flex-8-m10">
    <input type="hidden" name="s" value="admin_merits_flaws">
    <label class="small">Busqueda
        <input class="inp" type="text" name="q" id="quickFilterMyd" value="<?= h($q) ?>" placeholder="Nombre, tipo, afiliacion, coste o sistema (realtime)">
    </label>
    <label class="small">Por pag
        <select class="select" name="pp" onchange="this.form.submit()">
            <?php foreach ([25,50,100,200] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage===$pp?'selected':'' ?>><?= $pp ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn" type="button" id="btnApplyMydFilter">Aplicar</button>
</form>

<table class="table" id="tablaMyd">
    <thead>
        <tr>
            <th class="adm-w-70">ID</th>
            <th class="adm-w-260">Nombre</th>
            <th class="adm-w-140">Tipo</th>
            <th class="adm-w-180">Afiliacion</th>
            <th class="adm-w-80">Coste</th>
            <th class="adm-w-180">Sistema</th>
            <th class="adm-w-180">Origen</th>
            <th class="adm-w-170">Acciones</th>
        </tr>
    </thead>
    <tbody id="mydTbody">
        <?php foreach ($rows as $r):
            $search = trim((string)$r['name'].' '.(string)$r['kind'].' '.(string)$r['affiliation'].' '.(string)$r['cost'].' '.(string)$r['system_name'].' '.(string)$r['origin_name']);
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
            else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><strong class="adm-color-accent"><?= (int)$r['id'] ?></strong></td>
            <td><?= h((string)$r['name']) ?></td>
            <td><?= h((string)$r['kind']) ?></td>
            <td><?= h((string)$r['affiliation']) ?></td>
            <td><?= h((string)$r['cost']) ?></td>
            <td><?= h((string)$r['system_name']) ?></td>
            <td><?= h((string)$r['origin_name']) ?></td>
            <td>
                <button class="btn" type="button" data-edit="<?= (int)$r['id'] ?>">Editar</button>
                <button class="btn btn-red" type="button" data-del="<?= (int)$r['id'] ?>">Borrar</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="adm-color-muted">(Sin resultados)</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="pager" id="mydPager">
    <?php
    $base = "/talim?s=admin_merits_flaws&pp=".$perPage."&q=".urlencode($q);
    $prev = max(1, $page-1);
    $next = min($pages, $page+1);
    ?>
    <a href="<?= $base ?>&pg=1">&laquo; Primero</a>
    <a href="<?= $base ?>&pg=<?= $prev ?>">&lsaquo; Anterior</a>
    <span class="cur">Pag <?= $page ?>/<?= $pages ?> - Total <?= (int)$total ?></span>
    <a href="<?= $base ?>&pg=<?= $next ?>">Siguiente &rsaquo;</a>
    <a href="<?= $base ?>&pg=<?= $pages ?>">Ultimo &raquo;</a>
</div>

<div class="modal-back" id="mbMyd">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="mydModalTitle">
        <h3 id="mydModalTitle">Nuevo merito/defecto</h3>
        <form method="post" id="mydForm" class="adm-m-0">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="crud_action" id="myd_action" value="create">
            <input type="hidden" name="id" id="myd_id" value="0">

            <div class="grid">
                <label><span>Nombre</span> <span class="badge">oblig.</span>
                    <input class="inp" type="text" name="name" id="myd_name" maxlength="100" required>
                </label>
                <label><span>Tipo</span> <span class="badge">oblig.</span>
                    <select class="select" name="kind" id="myd_kind" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($opts_kinds as $kind): ?>
                        <option value="<?= h($kind) ?>"><?= h($kind) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Afiliacion</span> <span class="badge">oblig.</span>
                    <select class="select" name="affiliation" id="myd_affiliation" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($opts_affiliations as $affiliation): ?>
                        <option value="<?= h($affiliation) ?>"><?= h($affiliation) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Coste</span> <span class="badge">oblig.</span>
                    <input class="inp" type="text" name="cost" id="myd_cost" maxlength="3" required>
                </label>
                <label><span>Sistema (catalogo)</span>
                    <select class="select" name="system_id" id="myd_system_id">
                        <option value="0">-</option>
                        <?php foreach ($opts_systems as $sid => $sname): ?>
                        <option value="<?= (int)$sid ?>"><?= h($sname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Sistema (texto)</span> <span class="badge">oblig.</span>
                    <input class="inp" type="text" name="system_name" id="myd_system_name" maxlength="100" required>
                </label>
                <label><span>Origen</span>
                    <select class="select" name="bibliography_id" id="myd_bibliography_id">
                        <option value="0">-</option>
                        <?php foreach ($opts_origins as $oid => $oname): ?>
                        <option value="<?= (int)$oid ?>"><?= h($oname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field-full"><span>Descripcion</span> <span class="badge">oblig.</span>
                    <textarea class="inp ta-lg" name="description" id="myd_description"></textarea>
                </label>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn" id="btnMydCancel">Cancelar</button>
                <button type="submit" class="btn btn-green">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-back" id="mbMydDel">
    <div class="modal adm-modal-sm">
        <h3>Confirmar borrado</h3>
        <div class="adm-help-text">
            Se eliminara el merito/defecto definitivamente.
        </div>
        <form method="post" id="mydDelForm" class="adm-m-0">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="crud_action" value="delete">
            <input type="hidden" name="id" id="myd_del_id" value="0">
            <div class="modal-actions">
                <button type="button" class="btn" id="btnMydDelCancel">Cancelar</button>
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
var MYD_ROWS = <?= json_encode($rowMap, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;
(function(){
    var modal = document.getElementById('mbMyd');
    var delModal = document.getElementById('mbMydDel');
    var form = document.getElementById('mydForm');
    var delForm = document.getElementById('mydDelForm');
    var input = document.getElementById('quickFilterMyd');
    var filterForm = document.getElementById('mydFilterForm');
    var filterBtn = document.getElementById('btnApplyMydFilter');
    var tbody = document.getElementById('mydTbody');
    var pager = document.getElementById('mydPager');
    var pagerCur = pager ? pager.querySelector('.cur') : null;
    var kindSelect = document.getElementById('myd_kind');
    var affiliationSelect = document.getElementById('myd_affiliation');
    var systemSelect = document.getElementById('myd_system_id');
    var systemNameInput = document.getElementById('myd_system_name');
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
        url.searchParams.set('s', 'admin_merits_flaws');
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

    function ensureAffiliationOption(value){
        var v = String(value || '').trim();
        if (!affiliationSelect || v === '') return;
        var exists = Array.prototype.some.call(affiliationSelect.options, function(opt){ return String(opt.value) === v; });
        if (!exists) {
            var opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            affiliationSelect.appendChild(opt);
        }
    }

    function syncSystemNameFromSelect(force){
        if (!systemSelect || !systemNameInput) return;
        var opt = systemSelect.options[systemSelect.selectedIndex];
        if (!opt) return;
        var txt = String(opt.textContent || '').trim();
        if (!txt || txt === '-') return;
        if (force || !String(systemNameInput.value || '').trim()) {
            systemNameInput.value = txt;
        }
    }

    function openCreate(){
        document.getElementById('mydModalTitle').textContent = 'Nuevo merito/defecto';
        document.getElementById('myd_action').value = 'create';
        document.getElementById('myd_id').value = '0';
        document.getElementById('myd_name').value = '';
        document.getElementById('myd_kind').value = '';
        document.getElementById('myd_affiliation').value = '';
        document.getElementById('myd_cost').value = '';
        document.getElementById('myd_system_id').value = '0';
        document.getElementById('myd_system_name').value = '';
        document.getElementById('myd_bibliography_id').value = '0';
        document.getElementById('myd_description').value = '';
        modal.style.display = 'flex';
        setTimeout(function(){ document.getElementById('myd_name').focus(); }, 0);
    }

    function openEdit(id){
        var row = MYD_ROWS[String(id)];
        if (!row) return;
        document.getElementById('mydModalTitle').textContent = 'Editar merito/defecto';
        document.getElementById('myd_action').value = 'update';
        document.getElementById('myd_id').value = String(id);
        document.getElementById('myd_name').value = row.name || '';
        ensureKindOption(row.kind || '');
        document.getElementById('myd_kind').value = row.kind || '';
        ensureAffiliationOption(row.affiliation || '');
        document.getElementById('myd_affiliation').value = row.affiliation || '';
        document.getElementById('myd_cost').value = row.cost || '';
        document.getElementById('myd_system_id').value = String(parseInt(row.system_id || 0, 10) || 0);
        document.getElementById('myd_system_name').value = row.system_name || '';
        document.getElementById('myd_bibliography_id').value = String(parseInt(row.bibliography_id || 0, 10) || 0);
        document.getElementById('myd_description').value = row.description || '';
        modal.style.display = 'flex';
        setTimeout(function(){ document.getElementById('myd_name').focus(); }, 0);
    }

    function openDelete(id){
        document.getElementById('myd_del_id').value = String(id || 0);
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
        MYD_ROWS = rowMap || {};
        if (!rows || !rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="adm-color-muted">(Sin resultados)</td></tr>';
            if (pagerCur) pagerCur.textContent = 'Total 0 (vista AJAX)';
            return;
        }
        var html = '';
        rows.forEach(function(r){
            html += '<tr>'
                + '<td><strong class="adm-color-accent">'+(parseInt(r.id || 0, 10) || 0)+'</strong></td>'
                + '<td>'+esc(r.name)+'</td>'
                + '<td>'+esc(r.kind)+'</td>'
                + '<td>'+esc(r.affiliation)+'</td>'
                + '<td>'+esc(r.cost)+'</td>'
                + '<td>'+esc(r.system_name)+'</td>'
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

    document.getElementById('btnNewMyd').addEventListener('click', openCreate);
    document.getElementById('btnMydCancel').addEventListener('click', closeAll);
    document.getElementById('btnMydDelCancel').addEventListener('click', closeAll);
    modal.addEventListener('click', function(e){ if (e.target === modal) closeAll(); });
    delModal.addEventListener('click', function(e){ if (e.target === delModal) closeAll(); });
    bindRowButtons(document);
    if (systemSelect) {
        systemSelect.addEventListener('change', function(){
            syncSystemNameFromSelect(false);
        });
    }

    form.addEventListener('submit', function(ev){
        ev.preventDefault();
        syncSystemNameFromSelect(false);
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




