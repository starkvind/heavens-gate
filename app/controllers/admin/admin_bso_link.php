<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/admin_catalog_utils.php');
include_once(__DIR__ . '/../../domains/soundtracks/admin_links.php');

$isAjaxRequest = (((string)($_GET['ajax'] ?? '') === '1') || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'));
$csrfKey = 'csrf_admin_bso_link';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');

function hg_abl_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function hg_abl_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token') ? hg_admin_extract_csrf_token($payload) : (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, 'csrf_admin_bso_link')
        : (is_string($token) && $token !== '' && isset($_SESSION['csrf_admin_bso_link']) && hash_equals((string)$_SESSION['csrf_admin_bso_link'], $token));
}










$flash = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) hg_admin_require_session(true);
    if (!hg_abl_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $action = (string)($_POST['crud_action'] ?? '');
        if ($action === 'create') {
            $soundtrackId = (int)($_POST['soundtrack_id'] ?? 0);
            $objectType = trim((string)($_POST['object_type'] ?? ''));
            $objectId = (int)($_POST['object_id'] ?? 0);
            if ($soundtrackId <= 0 || $objectId <= 0 || !in_array($objectType, ['personaje','temporada','episodio'], true)) {
                $flash[] = ['type'=>'error','msg'=>'Datos de vinculacion invalidos.'];
            } elseif (!hg_abl_soundtrack_exists($link,$soundtrackId)) {
                $flash[] = ['type'=>'error','msg'=>'El tema seleccionado ya no existe.'];
            } elseif (!hg_abl_object_exists($link,$objectType,$objectId)) {
                $flash[] = ['type'=>'error','msg'=>'El destino seleccionado ya no existe.'];
            } elseif (hg_abl_link_exists($link,$soundtrackId,$objectType,$objectId)) {
                $flash[] = ['type'=>'error','msg'=>'Ese vinculo exacto ya existe.'];
            } else {
                $result = hg_abl_create_link($link,$soundtrackId,$objectType,$objectId);
                $flash[] = ['type'=>!empty($result['ok'])?'ok':'error','msg'=>(string)$result['message']];
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $result = hg_abl_delete_link($link,$id);
            $flash[] = ['type'=>!empty($result['ok'])?'ok':'error','msg'=>(string)$result['message']];
        } elseif ($action === 'dedupe') {
            $result = hg_abl_dedupe($link);
            $flash[] = ['type'=>!empty($result['ok'])?'ok':'error','msg'=>(string)$result['message']];
        }
    }
}

$payload = hg_abl_fetch_payload($link);
$rows = $payload['rows'];
$rowsFull = $payload['rows'];
$soundtracks = $payload['soundtracks'];
$characters = $payload['characters'];
$seasons = $payload['seasons'];
$chapters = $payload['chapters'];
$hasCreatedAt = (bool)$payload['has_created_at'];

$ajaxWrite = $isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action']);
if ($ajaxWrite) {
    $errors = [];
    $messages = [];
    foreach ($flash as $m) {
        $msg = (string)($m['msg'] ?? '');
        if ($msg === '') continue;
        if ((string)($m['type'] ?? '') === 'error') $errors[] = $msg; else $messages[] = $msg;
    }
    $data = ['rows' => $rows, 'rowsFull' => $rowsFull, 'soundtracks' => $soundtracks, 'characters' => $characters, 'seasons' => $seasons, 'chapters' => $chapters];
    if (!empty($errors)) {
        if (function_exists('hg_admin_json_error')) hg_admin_json_error($errors[0], 400, ['flash' => $errors], $data);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => $errors[0], 'errors' => $errors, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $okMsg = !empty($messages) ? $messages[count($messages) - 1] : 'Guardado';
    if (function_exists('hg_admin_json_success')) hg_admin_json_success($data, $okMsg);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'message' => $okMsg, 'msg' => $okMsg, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$dupCount = 0; $orphanCount = 0;
foreach ($rows as $row) {
    if ((int)($row['duplicate_count'] ?? 0) > 1) $dupCount++;
    if ((int)($row['soundtrack_exists'] ?? 0) <= 0 || (int)($row['object_exists'] ?? 0) <= 0) $orphanCount++;
}

$actions = '<span class="adm-flex-right-8"><a class="btn" href="/talim?s=admin_bso">Gestionar catalogo BSO</a><button class="btn btn-green" type="button" onclick="openBsoLinkModal()">+ Nuevo vinculo</button><button class="btn" type="button" onclick="submitBsoLinkDedupe()">Deduplicar exactos</button><label class="adm-text-left">Filtro rapido <input class="inp" type="text" id="quickFilterBsoLink" placeholder="Tema, tipo, destino..."></label></span>';
if (!$isAjaxRequest) admin_panel_open('Vinculos BSO', $actions);
?>
<?php if (!empty($flash)): ?><div class="flash"><?php foreach ($flash as $m): $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?><div class="<?= $cl ?>"><?= hg_abl_h($m['msg'] ?? '') ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="adm-summary-band"><span class="adm-summary-pill">Vinculos: <?= (int)count($rows) ?></span><span class="adm-summary-pill">Duplicados detectados: <?= (int)$dupCount ?></span><span class="adm-summary-pill">Huerfanos detectados: <?= (int)$orphanCount ?></span></div>
<div class="modal-back" id="bsoLinkModal"><div class="modal"><h3>Nuevo vinculo BSO</h3><form method="post" id="bsoLinkForm"><input type="hidden" name="csrf" value="<?= hg_abl_h($csrf) ?>"><input type="hidden" name="crud_action" value="create"><div class="modal-body"><div class="adm-grid-1-2"><label>Tema</label><select class="inp" name="soundtrack_id" id="bso_link_soundtrack" required><option value="">Seleccionar tema</option><?php foreach ($soundtracks as $row): ?><option value="<?= (int)$row['id'] ?>"><?= hg_abl_h((string)($row['label'] ?? '')) ?></option><?php endforeach; ?></select><label>Tipo de destino</label><select class="inp" name="object_type" id="bso_link_type" required><option value="">Seleccionar tipo</option><option value="personaje">Personaje</option><option value="temporada">Temporada</option><option value="episodio">Episodio</option></select><label>Destino</label><select class="inp" name="object_id" id="bso_link_object" required><option value="">Selecciona primero el tipo</option></select></div></div><div class="modal-actions"><button class="btn btn-green" type="submit">Guardar</button><button class="btn" type="button" onclick="closeBsoLinkModal()">Cancelar</button></div></form></div></div>
<div class="modal-back" id="bsoLinkDeleteModal"><div class="modal adm-modal-sm"><h3>Confirmar borrado</h3><div class="adm-help-text" id="bsoLinkDeleteHelp">Se borrara el vinculo seleccionado.</div><form method="post" id="bsoLinkDeleteForm" class="adm-m-0"><input type="hidden" name="csrf" value="<?= hg_abl_h($csrf) ?>"><input type="hidden" name="crud_action" value="delete"><input type="hidden" name="id" id="bso_link_delete_id" value="0"><div class="modal-actions"><button type="button" class="btn" onclick="closeBsoLinkDeleteModal()">Cancelar</button><button type="submit" class="btn btn-red">Borrar</button></div></form></div></div>
<form method="post" id="bsoLinkDedupeForm" class="adm-hidden"><input type="hidden" name="csrf" value="<?= hg_abl_h($csrf) ?>"><input type="hidden" name="crud_action" value="dedupe"></form>
<div class="adm-table-wrap adm-bso-link-table"><table class="table" id="tablaBsoLinks"><thead><tr><th class="adm-w-60">ID</th><th class="adm-bso-link-theme adm-cell-wrap">Tema</th><th class="adm-w-120">Tipo</th><th class="adm-bso-link-dest adm-cell-wrap">Destino</th><th class="adm-bso-link-state adm-cell-wrap">Estado</th><?php if ($hasCreatedAt): ?><th class="adm-w-140">Creado</th><?php endif; ?><th class="adm-w-160 adm-th-actions">Acciones</th></tr></thead><tbody id="bsoLinkTbody"><?php foreach ($rows as $row): $search = trim((string)($row['soundtrack_label'] ?? '') . ' ' . (string)($row['type_label'] ?? '') . ' ' . (string)($row['object_label'] ?? '') . ' ' . (string)($row['state_label'] ?? '')); if (function_exists('mb_strtolower')) $search = mb_strtolower($search, 'UTF-8'); else $search = strtolower($search); $state = (string)($row['state_label'] ?? 'OK'); $stateClass = 'adm-state-badge'; if (strpos($state, 'Duplicado') !== false) $stateClass .= ' warn'; if (strpos($state, 'huerfano') !== false) $stateClass .= ' err'; ?><tr data-search="<?= hg_abl_h($search) ?>"><td><?= (int)$row['id'] ?></td><td class="adm-cell-wrap"><div class="adm-link-row"><span><?= hg_abl_h((string)($row['soundtrack_label'] ?? '')) ?></span><a href="/talim?s=admin_bso" target="_blank" rel="noopener">Catalogo</a></div></td><td><?= hg_abl_h((string)($row['type_label'] ?? '')) ?></td><td class="adm-cell-wrap"><div class="adm-link-row"><span><?= hg_abl_h((string)($row['object_label'] ?? '')) ?></span><?php $publicUrl = trim((string)($row['public_url'] ?? '')); if ($publicUrl !== ''): ?><a href="<?= hg_abl_h($publicUrl) ?>" target="_blank" rel="noopener">Abrir</a><?php endif; ?></div></td><td class="adm-cell-wrap"><span class="<?= hg_abl_h($stateClass) ?>"><?= hg_abl_h($state) ?></span></td><?php if ($hasCreatedAt): ?><td><?= hg_abl_h((string)($row['created_at'] ?? '')) ?></td><?php endif; ?><td class="adm-cell-actions"><div class="adm-actions-inline"><button class="btn btn-red" type="button" data-del="<?= (int)$row['id'] ?>">Borrar</button></div></td></tr><?php endforeach; ?><?php if (empty($rows)): ?><tr><td colspan="<?= 6 + ($hasCreatedAt ? 1 : 0) ?>" class="adm-color-muted">(Sin vinculos)</td></tr><?php endif; ?></tbody></table></div>
<?php $adminHttpJs = '/assets/js/admin/admin-http.js'; $adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time(); ?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($csrf, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= hg_abl_h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
let bsoLinkRows = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
let bsoLinkOptions = { soundtrack: <?= json_encode($soundtracks, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>, personaje: <?= json_encode($characters, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>, temporada: <?= json_encode($seasons, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>, episodio: <?= json_encode($chapters, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?> };
function bsoLinkRequest(url, options){ return (window.HGAdminHttp && window.HGAdminHttp.request) ? window.HGAdminHttp.request(url, options || {}) : fetch(url, options || {}).then(async r => { const p = await r.json(); if (!r.ok || (p && p.ok === false)) { const e = new Error((p && (p.message || p.msg)) || ('HTTP ' + r.status)); e.status = r.status; e.payload = p; throw e; } return p; }); }
function bsoLinkUrl(){ const url = new URL(window.location.href); url.searchParams.set('s', 'admin_bso_link'); url.searchParams.set('ajax', '1'); url.searchParams.set('_ts', Date.now()); return url.toString(); }
function bsoLinkEsc(text){ return String(text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
function bsoLinkStateClass(state){ let cls = 'adm-state-badge'; if (String(state || '').indexOf('Duplicado') !== -1) cls += ' warn'; if (String(state || '').indexOf('huerfano') !== -1) cls += ' err'; return cls; }
function populateBsoLinkObjects(selectedType, selectedId){ const select = document.getElementById('bso_link_object'); if (!select) return; const rows = Array.isArray(bsoLinkOptions[selectedType]) ? bsoLinkOptions[selectedType] : []; let html = '<option value=\"\">Seleccionar destino</option>'; rows.forEach(function(row){ const id = parseInt(row.id || 0, 10) || 0; const selected = id === (parseInt(selectedId || 0, 10) || 0) ? ' selected' : ''; html += '<option value=\"' + id + '\"' + selected + '>' + bsoLinkEsc(row.label || ('#' + id)) + '</option>'; }); select.innerHTML = html; }
function openBsoLinkModal(){ document.getElementById('bso_link_soundtrack').value = ''; document.getElementById('bso_link_type').value = ''; populateBsoLinkObjects('', 0); document.getElementById('bsoLinkModal').style.display = 'flex'; }
function closeBsoLinkModal(){ document.getElementById('bsoLinkModal').style.display = 'none'; }
function openBsoLinkDeleteModal(id){ const row = bsoLinkRows.find(r => parseInt(r.id || 0, 10) === (parseInt(id || 0, 10) || 0)) || null; document.getElementById('bso_link_delete_id').value = String(parseInt(id || 0, 10) || 0); document.getElementById('bsoLinkDeleteHelp').textContent = row ? ('Se borrara el vinculo entre \"' + (row.soundtrack_label || '') + '\" y \"' + (row.object_label || '') + '\".') : 'Se borrara el vinculo seleccionado.'; document.getElementById('bsoLinkDeleteModal').style.display = 'flex'; }
function closeBsoLinkDeleteModal(){ document.getElementById('bsoLinkDeleteModal').style.display = 'none'; }
function submitBsoLinkDedupe(){ const form = document.getElementById('bsoLinkDedupeForm'); const fd = new FormData(form); fd.set('ajax', '1'); bsoLinkRequest(bsoLinkUrl(), { method: 'POST', body: fd, loadingEl: form }).then(function(payload){ bsoLinkSync(payload); if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Deduplicado', 'ok'); }).catch(function(err){ const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al deduplicar'); alert(msg); }); }
function bindBsoLinkRows(){ document.querySelectorAll('#bsoLinkTbody [data-del]').forEach(btn => btn.onclick = () => openBsoLinkDeleteModal(parseInt(btn.getAttribute('data-del') || '0', 10) || 0)); }
function renderBsoLinkRows(rows){ const tbody = document.getElementById('bsoLinkTbody'); if (!tbody) return; if (!rows || !rows.length) { tbody.innerHTML = '<tr><td colspan="<?= 6 + ($hasCreatedAt ? 1 : 0) ?>" class="adm-color-muted">(Sin vinculos)</td></tr>'; bindBsoLinkRows(); return; } let html = ''; rows.forEach(function(row){ const id = parseInt(row.id || 0, 10) || 0; const search = (String(row.soundtrack_label || '') + ' ' + String(row.type_label || '') + ' ' + String(row.object_label || '') + ' ' + String(row.state_label || '')).toLowerCase(); const publicUrl = String(row.public_url || '').trim(); const publicLink = publicUrl ? ('<a href=\"' + bsoLinkEsc(publicUrl) + '\" target=\"_blank\" rel=\"noopener\">Abrir</a>') : ''; html += '<tr data-search=\"' + bsoLinkEsc(search) + '\"><td>' + id + '</td><td class=\"adm-cell-wrap\"><div class=\"adm-link-row\"><span>' + bsoLinkEsc(row.soundtrack_label || '') + '</span><a href=\"/talim?s=admin_bso\" target=\"_blank\" rel=\"noopener\">Catalogo</a></div></td><td>' + bsoLinkEsc(row.type_label || '') + '</td><td class=\"adm-cell-wrap\"><div class=\"adm-link-row\"><span>' + bsoLinkEsc(row.object_label || '') + '</span>' + publicLink + '</div></td><td class=\"adm-cell-wrap\"><span class=\"' + bsoLinkEsc(bsoLinkStateClass(row.state_label || 'OK')) + '\">' + bsoLinkEsc(row.state_label || 'OK') + '</span></td><?php if ($hasCreatedAt): ?><td>' + bsoLinkEsc(row.created_at || '') + '</td><?php endif; ?><td class=\"adm-cell-actions\"><div class=\"adm-actions-inline\"><button class=\"btn btn-red\" type=\"button\" data-del=\"' + id + '\">Borrar</button></div></td></tr>'; }); tbody.innerHTML = html; bindBsoLinkRows(); }
function applyBsoLinkFilter(){ const input = document.getElementById('quickFilterBsoLink'); if (!input) return; const q = (input.value || '').toLowerCase(); document.querySelectorAll('#bsoLinkTbody tr').forEach(function(tr){ const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase(); tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none'; }); }
function refreshBsoLinkSummary(rows){ const total = Array.isArray(rows) ? rows.length : 0; let dup = 0; let orphan = 0; (rows || []).forEach(function(row){ if ((parseInt(row.duplicate_count || 0, 10) || 0) > 1) dup++; if ((parseInt(row.soundtrack_exists || 0, 10) || 0) <= 0 || (parseInt(row.object_exists || 0, 10) || 0) <= 0) orphan++; }); const pills = document.querySelectorAll('.adm-summary-pill'); if (pills[0]) pills[0].textContent = 'Vinculos: ' + total; if (pills[1]) pills[1].textContent = 'Duplicados detectados: ' + dup; if (pills[2]) pills[2].textContent = 'Huerfanos detectados: ' + orphan; }
function bsoLinkSync(payload){ const data = payload && payload.data ? payload.data : {}; if (Array.isArray(data.rows)) { renderBsoLinkRows(data.rows); refreshBsoLinkSummary(data.rows); } if (Array.isArray(data.rowsFull)) bsoLinkRows = data.rowsFull; if (Array.isArray(data.soundtracks)) bsoLinkOptions.soundtrack = data.soundtracks; if (Array.isArray(data.characters)) bsoLinkOptions.personaje = data.characters; if (Array.isArray(data.seasons)) bsoLinkOptions.temporada = data.seasons; if (Array.isArray(data.chapters)) bsoLinkOptions.episodio = data.chapters; applyBsoLinkFilter(); }
document.getElementById('bso_link_type').addEventListener('change', function(){ populateBsoLinkObjects(this.value || '', 0); });
document.getElementById('bsoLinkForm').addEventListener('submit', function(ev){ ev.preventDefault(); const fd = new FormData(this); fd.set('ajax', '1'); bsoLinkRequest(bsoLinkUrl(), { method: 'POST', body: fd, loadingEl: this }).then(function(payload){ bsoLinkSync(payload); closeBsoLinkModal(); if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok'); }).catch(function(err){ const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al guardar'); alert(msg); }); });
document.getElementById('bsoLinkDeleteForm').addEventListener('submit', function(ev){ ev.preventDefault(); const fd = new FormData(this); fd.set('ajax', '1'); bsoLinkRequest(bsoLinkUrl(), { method: 'POST', body: fd, loadingEl: this }).then(function(payload){ bsoLinkSync(payload); closeBsoLinkDeleteModal(); if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Eliminado', 'ok'); }).catch(function(err){ const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al borrar'); alert(msg); }); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { closeBsoLinkModal(); closeBsoLinkDeleteModal(); } });
const bsoLinkFilter = document.getElementById('quickFilterBsoLink'); if (bsoLinkFilter) bsoLinkFilter.addEventListener('input', applyBsoLinkFilter);
bindBsoLinkRows();
</script>
<?php if (!$isAjaxRequest) admin_panel_close(); ?>
