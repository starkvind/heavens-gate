<?php
// admin_forms.php -- CRUD Formas (dim_forms)
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
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

$systems = [];
$systemNameById = [];
$systemIdByName = [];
if ($rs = $link->query("SELECT id, name FROM dim_systems ORDER BY sort_order ASC, name ASC")) {
    while ($r = $rs->fetch_assoc()) {
        $sid = (int)($r['id'] ?? 0);
        $sname = trim((string)($r['name'] ?? ''));
        if ($sid <= 0 || $sname === '') { continue; }
        $systems[] = ['id' => $sid, 'name' => $sname];
        $systemNameById[$sid] = $sname;
        $key = function_exists('mb_strtolower') ? mb_strtolower($sname, 'UTF-8') : strtolower($sname);
        $systemIdByName[$key] = $sid;
    }
    $rs->close();
}
$tribesBySystem = [];
if ($rs = $link->query("SELECT system_id, name FROM dim_tribes ORDER BY system_id ASC, name ASC")) {
    while ($r = $rs->fetch_assoc()) {
        $sid = (int)($r['system_id'] ?? 0);
        $tname = trim((string)($r['name'] ?? ''));
        if ($sid <= 0 || $tname === '') { continue; }
        if (!isset($tribesBySystem[$sid])) { $tribesBySystem[$sid] = []; }
        $tribesBySystem[$sid][] = $tname;
    }
    $rs->close();
}
$sysRaw = trim((string)($_GET['sys'] ?? ''));
$sys = 0;
if ($sysRaw !== '') {
    if (ctype_digit($sysRaw)) {
        $sys = (int)$sysRaw;
    } else {
        $sysKey = function_exists('mb_strtolower') ? mb_strtolower($sysRaw, 'UTF-8') : strtolower($sysRaw);
        $sys = (int)($systemIdByName[$sysKey] ?? 0);
    }
}
$sysOptions = '<option value="">-- Todos --</option>';
$sysModalOptions = '<option value="0">-- Seleccionar --</option>';
foreach ($systems as $srow) {
    $sid = (int)$srow['id'];
    $sname = (string)$srow['name'];
    $sel = ($sid === (int)$sys) ? ' selected' : '';
    $sysOptions .= '<option value="'.(int)$sid.'"'.$sel.'>'.h($sname).'</option>';
    $sysModalOptions .= '<option value="'.(int)$sid.'">'.h($sname).'</option>';
}

$actions = '<span class="adm-flex-right-8">'
    . '<label class="adm-text-left">Sistema '
    . '<select class="select" id="filterSystemForms">'.$sysOptions.'</select></label>'
    . '<button class="btn btn-green" type="button" onclick="openFormModal()">+ Nueva forma</button>'
    . '<label class="adm-text-left">Filtro rápido '
    . '<input class="inp" type="text" id="quickFilterForms" placeholder="En esta pagina..."></label>'
    . '</span>';
if (!$isAjaxRequest) {
    admin_panel_open('Formas', $actions);
}

$flash = [];
$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_forms';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function forms_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $t = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($t, 'csrf_admin_forms');
    }
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_forms']) && hash_equals($_SESSION['csrf_admin_forms'], $t);
}

if (!$isAjaxRequest && isset($_GET['delete'])) {
    $flash[] = ['type'=>'error','msg'=>'El borrado por URL ha sido desactivado por seguridad. Usa el boton Borrar.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['crud_action'] ?? '') === 'delete') {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (!forms_csrf_ok()) {
        $flash[] = ['type'=>'error','msg'=>'CSRF inválido. Recarga la página.'];
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $flash[] = ['type'=>'error','msg'=>'ID inválido para borrar.'];
        } elseif ($st = $link->prepare("DELETE FROM dim_forms WHERE id=?")) {
            $st->bind_param('i', $id);
            if ($st->execute()) $flash[] = ['type'=>'ok','msg'=>'Forma eliminada.'];
            else $flash[] = ['type'=>'error','msg'=>'Error al borrar: '.$st->error];
            $st->close();
        } else {
            $flash[] = ['type'=>'error','msg'=>'Error al preparar DELETE: '.$link->error];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (!forms_csrf_ok()) {
        $flash[] = ['type'=>'error','msg'=>'CSRF inválido. Recarga la página.'];
    } else {
    $id = (int)($_POST['id'] ?? 0);
    $afiliacion = trim((string)($_POST['afiliacion'] ?? ''));
    $raza = trim((string)($_POST['raza'] ?? ''));
    $systemId = (int)($_POST['system_id'] ?? 0);
    $forma = trim((string)($_POST['forma'] ?? ''));
    $desc = sanitize_utf8_text((string)($_POST['description'] ?? ''));
    $desc = hg_mentions_convert($link, $desc);
    $imagen = trim((string)($_POST['imagen'] ?? ''));
    $armas = (int)($_POST['armas'] ?? 0);
    $armasfuego = (int)($_POST['armasfuego'] ?? 0);
    $bonfue = trim((string)($_POST['bonfue'] ?? ''));
    $bondes = trim((string)($_POST['bondes'] ?? ''));
    $bonres = trim((string)($_POST['bonres'] ?? ''));
    $regenera = (int)($_POST['regenera'] ?? 0);
    $hpregen = (int)($_POST['hpregen'] ?? 0);
    $bibliographyId = (int)($_POST['bibliography_id'] ?? 0);

    if ($raza === '' && $systemId > 0) {
        $raza = (string)($systemNameById[$systemId] ?? '');
    }
    if ($raza !== '') {
        $afiliacion = $raza;
    } elseif ($systemId > 0) {
        $afiliacion = (string)($systemNameById[$systemId] ?? '');
    }
    $systemNameForSlug = (string)($systemNameById[$systemId] ?? '');
    if ($systemId <= 0 || $afiliacion === '' || $raza === '' || $forma === '') {
        $flash[] = ['type'=>'error','msg'=>'Sistema, afiliacion, raza y forma son obligatorias.'];
    } else {
        if ($id > 0) {
            $sql = "UPDATE dim_forms SET affiliation=?, race=?, system_id=?, form=?, description=?, image_url=?, weapons=?, firearms=?, strength_bonus=?, dexterity_bonus=?, stamina_bonus=?, regeneration=?, hpregen=?, bibliography_id=?, updated_at=NOW() WHERE id=?";
            if ($st = $link->prepare($sql)) {
                $st->bind_param('ssisssiisssiiii', $afiliacion, $raza, $systemId, $forma, $desc, $imagen, $armas, $armasfuego, $bonfue, $bondes, $bonres, $regenera, $hpregen, $bibliographyId, $id);
                if ($st->execute()) {
                    $src = trim($systemNameForSlug.' '.$afiliacion.' '.$forma);
                    update_pretty_id($link, 'dim_forms', $id, $src);
                    $flash[] = ['type'=>'ok','msg'=>'Forma actualizada.'];
                } else {
                    $flash[] = ['type'=>'error','msg'=>'Error al actualizar: '.$st->error];
                }
                $st->close();
            } else {
                $flash[] = ['type'=>'error','msg'=>'Error al preparar UPDATE: '.$link->error];
            }
        } else {
            $sql = "INSERT INTO dim_forms (affiliation, race, system_id, form, description, image_url, weapons, firearms, strength_bonus, dexterity_bonus, stamina_bonus, regeneration, hpregen, bibliography_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())";
            if ($st = $link->prepare($sql)) {
                $st->bind_param('ssisssiisssiii', $afiliacion, $raza, $systemId, $forma, $desc, $imagen, $armas, $armasfuego, $bonfue, $bondes, $bonres, $regenera, $hpregen, $bibliographyId);
                if ($st->execute()) {
                    $newId = (int)$st->insert_id;
                    $src = trim($systemNameForSlug.' '.$afiliacion.' '.$forma);
                    update_pretty_id($link, 'dim_forms', $newId, $src);
                    $flash[] = ['type'=>'ok','msg'=>'Forma creada.'];
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

$rows = [];
$rowsFull = [];
$sql = "SELECT f.id, f.pretty_id, f.description, f.affiliation AS afiliacion, f.race AS raza, f.system_id, COALESCE(ds.name,'') AS system_name, f.form AS forma, f.image_url AS imagen, f.weapons AS armas, f.firearms AS armasfuego, f.strength_bonus AS bonfue, f.dexterity_bonus AS bondes, f.stamina_bonus AS bonres, f.regeneration AS regenera, f.hpregen, f.bibliography_id, COALESCE(b.name,'') AS origen_name FROM dim_forms f LEFT JOIN dim_systems ds ON ds.id=f.system_id LEFT JOIN dim_bibliographies b ON f.bibliography_id=b.id";
if ($sys > 0) {
    $sql .= " WHERE f.system_id = ?";
}
$sql .= " ORDER BY ds.sort_order, ds.name, f.affiliation, f.race, f.form";
if ($sys > 0) {
    if ($st = $link->prepare($sql)) {
        $st->bind_param('i', $sys);
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) { $rows[] = $r; $rowsFull[] = $r; }
        $st->close();
    }
} else {
    if ($rs = $link->query($sql)) {
        while ($r = $rs->fetch_assoc()) { $rows[] = $r; $rowsFull[] = $r; }
        $rs->close();
    }
}

if ($isAjaxRequest && (string)($_GET['ajax_mode'] ?? '') === 'list') {
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success(['rows' => $rows, 'rowsFull' => $rowsFull, 'total' => count($rows), 'sys' => $sys], 'Listado');
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'rows' => $rows,
        'rowsFull' => $rowsFull,
        'total' => count($rows),
        'sys' => $sys,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$ajaxSaveDelete = (
    $isAjaxRequest
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['save_form']) || (string)($_POST['crud_action'] ?? '') === 'delete')
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

<div class="modal-back" id="formModal">
    <div class="modal">
        <h3 id="formModalTitle">Nueva forma</h3>
        <form method="post" id="formForm">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="save_form" value="1">
            <input type="hidden" name="id" id="form_id" value="">
            <div class="modal-body">
                <div class="adm-grid-1-2">
                    <label>Sistema</label>
                    <select class="select" name="system_id" id="form_system_id" required>
                        <?= $sysModalOptions ?>
                    </select>

                    <input type="hidden" name="afiliacion" id="form_afiliacion" value="">

                    <label>Raza</label>
                    <select class="select" name="raza" id="form_raza" required>
                        <option value="">-- Seleccionar sistema primero --</option>
                    </select>

                    <label>Forma</label>
                    <input class="inp" type="text" name="forma" id="form_forma" required>

                    <label>Imagen</label>
                    <input class="inp" type="text" name="imagen" id="form_imagen">

                    <label>Armas</label>
                    <select class="select" name="armas" id="form_armas">
                        <option value="0">No</option>
                        <option value="1">Si</option>
                    </select>

                    <label>Armas de fuego</label>
                    <select class="select" name="armasfuego" id="form_armasfuego">
                        <option value="0">No</option>
                        <option value="1">Si</option>
                    </select>

                    <label>Bon. Fuerza</label>
                    <input class="inp" type="text" name="bonfue" id="form_bonfue">

                    <label>Bon. Destreza</label>
                    <input class="inp" type="text" name="bondes" id="form_bondes">

                    <label>Bon. Resistencia</label>
                    <input class="inp" type="text" name="bonres" id="form_bonres">

                    <label>Regenera</label>
                    <select class="select" name="regenera" id="form_regenera">
                        <option value="0">No</option>
                        <option value="1">Si</option>
                    </select>

                    <label>HP Regenera</label>
                    <input class="inp" type="number" name="hpregen" id="form_hpregen">

                    <label>Origen</label>
                    <select class="select" name="bibliography_id" id="form_bibliography_id">
                        <option value="0">--</option>
                        <?php foreach ($origins as $o): ?>
                            <option value="<?= (int)$o['id'] ?>"><?= h($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>Descripción</label>
                    <div>
                        <div id="form_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
                        <div id="form_editor" class="ql-container ql-snow"></div>
                        <textarea class="ta adm-hidden" name="description" id="form_desc" rows="8"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-green" type="submit">Guardar</button>
                <button class="btn" type="button" onclick="closeFormModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<table class="table" id="tablaForms">
    <thead>
        <tr>
            <th class="adm-w-60">ID</th>
            <th>Sistema</th>
            <th>Raza</th>
            <th>Forma</th>
            <th>Origen</th>
            <th class="adm-w-160">Acciones</th>
        </tr>
    </thead>
    <tbody id="formsTbody">
    <?php foreach ($rows as $r): ?>
        <?php
            $search = trim((string)($r['system_name'] ?? '') . ' ' . (string)($r['raza'] ?? '') . ' ' . (string)($r['forma'] ?? '') . ' ' . (string)($r['origen_name'] ?? ''));
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
            else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><?= (int)$r['id'] ?></td>
            <td><?= h($r['system_name'] ?? '') ?></td>
            <td><?= h($r['raza']) ?></td>
            <td><?= h($r['forma']) ?></td>
            <td><?= h($r['origen_name'] ?? '') ?></td>
            <td>
                <button class="btn" type="button" data-edit="<?= (int)$r['id'] ?>">Editar</button>
                <button class="btn btn-red" type="button" data-del="<?= (int)$r['id'] ?>">Borrar</button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="adm-color-muted">(Sin formas)</td></tr>
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
let formsData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
let formEditor = null;
let currentSys = <?= json_encode($sys > 0 ? (string)$sys : '', JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
const FORM_SYSTEMS = <?= json_encode($systemNameById, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
const FORM_TRIBES_BY_SYSTEM = <?= json_encode($tribesBySystem, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;

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
function endpointUrl(mode, sysValue){
    const url = new URL(window.location.href);
    url.searchParams.set('s', 'admin_forms');
    url.searchParams.set('ajax', '1');
    if (mode) url.searchParams.set('ajax_mode', mode);
    else url.searchParams.delete('ajax_mode');
    if (typeof sysValue === 'string') {
        if (sysValue) url.searchParams.set('sys', sysValue);
        else url.searchParams.delete('sys');
    }
    url.searchParams.set('_ts', Date.now());
    return url.toString();
}
function esc(s){
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function ensureFormEditor(){
    if (!formEditor && window.Quill) {
        formEditor = new Quill('#form_editor', { theme:'snow', modules:{ toolbar:'#form_toolbar' } });
        if (window.hgMentions) { window.hgMentions.attachQuill(formEditor, { types: ['character','season','episode','organization','group','gift','rite','totem','discipline','item','trait','background','merit','flaw','merydef','doc'] }); }
    }
}
function syncAffiliationFromSelection(){
    const raceSel = document.getElementById('form_raza');
    const sysSel = document.getElementById('form_system_id');
    const inp = document.getElementById('form_afiliacion');
    if (!inp) return;
    let txt = '';
    if (raceSel) {
        txt = String(raceSel.value || '').trim();
    }
    if (!txt && sysSel && sysSel.selectedIndex >= 0) {
        txt = String(sysSel.options[sysSel.selectedIndex].text || '').trim();
    }
    inp.value = txt;
}
function buildRaceOptionsForSystem(systemId, selectedRace){
    const sel = document.getElementById('form_raza');
    if (!sel) return;
    const sid = parseInt(systemId || '0', 10) || 0;
    const desired = String(selectedRace || '').trim();
    const options = [];
    const seen = new Set();
    const addOption = function(value){
        const v = String(value || '').trim();
        if (!v) return;
        const key = v.toLowerCase();
        if (seen.has(key)) return;
        seen.add(key);
        options.push(v);
    };

    if (sid > 0) {
        addOption((FORM_SYSTEMS && (FORM_SYSTEMS[sid] || FORM_SYSTEMS[String(sid)])) || '');
        const tribes = (FORM_TRIBES_BY_SYSTEM && (FORM_TRIBES_BY_SYSTEM[sid] || FORM_TRIBES_BY_SYSTEM[String(sid)])) || [];
        if (Array.isArray(tribes)) tribes.forEach(addOption);
    }

    let html = '';
    if (!options.length) {
        html = '<option value="">-- Seleccionar sistema primero --</option>';
    } else {
        options.forEach(function(opt){
            html += '<option value="' + esc(opt) + '">' + esc(opt) + '</option>';
        });
    }

    let valueToSelect = options.length ? options[0] : '';
    if (desired) {
        const found = options.find(function(opt){ return opt.toLowerCase() === desired.toLowerCase(); });
        if (found) {
            valueToSelect = found;
        } else {
            html += '<option value="' + esc(desired) + '">' + esc(desired) + ' (legacy)</option>';
            valueToSelect = desired;
        }
    }

    sel.innerHTML = html;
    sel.value = valueToSelect;
}
function openFormModal(id = null){
    ensureFormEditor();
    const modal = document.getElementById('formModal');
    document.getElementById('form_id').value = '';
    const initialSystemId = (parseInt(currentSys || '0', 10) || 0) > 0 ? String(parseInt(currentSys || '0', 10)) : '0';
    document.getElementById('form_system_id').value = initialSystemId;
    document.getElementById('form_afiliacion').value = '';
    buildRaceOptionsForSystem(initialSystemId, '');
    document.getElementById('form_forma').value = '';
    document.getElementById('form_desc').value = '';
    document.getElementById('form_imagen').value = '';
    document.getElementById('form_armas').value = '0';
    document.getElementById('form_armasfuego').value = '0';
    document.getElementById('form_bonfue').value = '';
    document.getElementById('form_bondes').value = '';
    document.getElementById('form_bonres').value = '';
    document.getElementById('form_regenera').value = '0';
    document.getElementById('form_hpregen').value = '0';
    document.getElementById('form_bibliography_id').value = '0';
    syncAffiliationFromSelection();
    if (formEditor) formEditor.root.innerHTML = '';
    if (id) {
        const row = formsData.find(function(r){ return (parseInt(r.id,10) || 0) === (parseInt(id,10) || 0); });
        if (row) {
            document.getElementById('formModalTitle').textContent = 'Editar forma';
            document.getElementById('form_id').value = row.id;
            const rowSystemId = String(parseInt(row.system_id || 0, 10) || 0);
            document.getElementById('form_system_id').value = rowSystemId;
            buildRaceOptionsForSystem(rowSystemId, row.raza || '');
            syncAffiliationFromSelection();
            document.getElementById('form_forma').value = row.forma || '';
            document.getElementById('form_imagen').value = row.imagen || '';
            document.getElementById('form_armas').value = row.armas || 0;
            document.getElementById('form_armasfuego').value = row.armasfuego || 0;
            document.getElementById('form_bonfue').value = row.bonfue || '';
            document.getElementById('form_bondes').value = row.bondes || '';
            document.getElementById('form_bonres').value = row.bonres || '';
            document.getElementById('form_regenera').value = row.regenera || 0;
            document.getElementById('form_hpregen').value = row.hpregen || 0;
            document.getElementById('form_bibliography_id').value = row.bibliography_id || 0;
            const desc = row.description || '';
            document.getElementById('form_desc').value = desc;
            if (formEditor) formEditor.root.innerHTML = desc;
        }
    } else {
        document.getElementById('formModalTitle').textContent = 'Nueva forma';
    }
    modal.style.display = 'flex';
}
function closeFormModal(){ document.getElementById('formModal').style.display = 'none'; }

function bindRows(){
    document.querySelectorAll('#formsTbody [data-edit]').forEach(function(btn){
        btn.onclick = function(){ openFormModal(parseInt(btn.getAttribute('data-edit') || '0', 10) || 0); };
    });
    document.querySelectorAll('#formsTbody [data-del]').forEach(function(btn){
        btn.onclick = function(){
            const id = parseInt(btn.getAttribute('data-del') || '0', 10) || 0;
            if (!id) return;
            if (!confirm('Eliminar forma?')) return;
            const fd = new FormData();
            fd.set('ajax', '1');
            fd.set('csrf', window.ADMIN_CSRF_TOKEN || '');
            fd.set('crud_action', 'delete');
            fd.set('id', String(id));
            request(endpointUrl('', currentSys), { method: 'POST', body: fd, loadingEl: btn }).then(function(payload){
                const data = payload && payload.data ? payload.data : {};
                if (Array.isArray(data.rows)) renderRows(data.rows);
                if (Array.isArray(data.rowsFull)) formsData = data.rowsFull;
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
    const tbody = document.getElementById('formsTbody');
    if (!tbody) return;
    if (!rows || !rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="adm-color-muted">(Sin formas)</td></tr>';
        bindRows();
        return;
    }
    let html = '';
    rows.forEach(function(r){
        const id = parseInt(r.id || 0, 10) || 0;
        const systemName = String(r.system_name || '');
        const raza = String(r.raza || '');
        const forma = String(r.forma || '');
        const origen = String(r.origen_name || '');
        const search = (systemName + ' ' + raza + ' ' + forma + ' ' + origen).toLowerCase();
        html += '<tr data-search="' + esc(search) + '">'
            + '<td>' + id + '</td>'
            + '<td>' + esc(systemName) + '</td>'
            + '<td>' + esc(raza) + '</td>'
            + '<td>' + esc(forma) + '</td>'
            + '<td>' + esc(origen) + '</td>'
            + '<td><button class="btn" type="button" data-edit="' + id + '">Editar</button> '
            + '<button class="btn btn-red" type="button" data-del="' + id + '">Borrar</button></td>'
            + '</tr>';
    });
    tbody.innerHTML = html;
    bindRows();
}
function applyQuickFilter(){
    const input = document.getElementById('quickFilterForms');
    if (!input) return;
    const q = (input.value || '').toLowerCase();
    document.querySelectorAll('#formsTbody tr').forEach(function(tr){
        const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
        tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
    });
}
function reloadBySystem(sys){
    request(endpointUrl('list', sys), { method: 'GET' }).then(function(payload){
        const data = payload && payload.data ? payload.data : payload;
        if (Array.isArray(data.rows)) renderRows(data.rows);
        if (Array.isArray(data.rowsFull)) formsData = data.rowsFull;
        currentSys = String(sys || '');
        applyQuickFilter();
        const url = new URL(window.location.href);
        if (currentSys) url.searchParams.set('sys', currentSys);
        else url.searchParams.delete('sys');
        history.replaceState({}, '', url.pathname + '?' + url.searchParams.toString());
    }).catch(function(err){
        const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al filtrar');
        alert(msg);
    });
}

document.getElementById('formForm').addEventListener('submit', function(ev){
    ev.preventDefault();
    if (formEditor) {
        const html = formEditor.root.innerHTML || '';
        const plain = (formEditor.getText() || '').replace(/\s+/g,' ').trim();
        document.getElementById('form_desc').value = plain ? html : '';
    }
    const fd = new FormData(this);
    fd.set('ajax', '1');
    request(endpointUrl('', currentSys), { method: 'POST', body: fd, loadingEl: this }).then(function(payload){
        const data = payload && payload.data ? payload.data : {};
        if (Array.isArray(data.rows)) renderRows(data.rows);
        if (Array.isArray(data.rowsFull)) formsData = data.rowsFull;
        closeFormModal();
        applyQuickFilter();
        if (window.HGAdminHttp && window.HGAdminHttp.notify) {
            window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok');
        }
    }).catch(function(err){
        const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al guardar');
        alert(msg);
    });
});

document.getElementById('quickFilterForms').addEventListener('input', applyQuickFilter);
document.getElementById('filterSystemForms').addEventListener('change', function(){
    reloadBySystem(this.value || '');
});
document.getElementById('form_system_id').addEventListener('change', function(){
    buildRaceOptionsForSystem(this.value || '0', '');
    syncAffiliationFromSelection();
});
document.getElementById('form_raza').addEventListener('change', function(){
    syncAffiliationFromSelection();
});
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeFormModal(); });
bindRows();
</script>

<?php admin_panel_close(); ?>





