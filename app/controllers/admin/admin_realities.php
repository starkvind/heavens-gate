<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/admin_catalog_utils.php');
include_once(__DIR__ . '/../../helpers/admin_phase7_audit.php');

$isAjaxRequest = (((string)($_GET['ajax'] ?? '') === '1') || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'));
$csrfKey = 'csrf_admin_realities';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');

function hg_are_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function hg_are_short(string $text, int $max = 120): string {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    if ($text === '') return '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $max ? (mb_substr($text, 0, $max, 'UTF-8') . '...') : $text;
    }
    return strlen($text) > $max ? (substr($text, 0, $max) . '...') : $text;
}
function hg_are_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token') ? hg_admin_extract_csrf_token($payload) : (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, 'csrf_admin_realities')
        : (is_string($token) && $token !== '' && isset($_SESSION['csrf_admin_realities']) && hash_equals((string)$_SESSION['csrf_admin_realities'], $token));
}
function hg_are_dep_summary(array $row): string {
    $parts = [];
    $chars = (int)($row['characters_count'] ?? 0);
    $timeline = (int)($row['timeline_count'] ?? 0);
    if ($chars > 0) $parts[] = $chars . ' personajes';
    if ($timeline > 0) $parts[] = $timeline . ' timeline';
    return !empty($parts) ? implode(' | ', $parts) : 'Sin dependencias';
}
function hg_are_audit_class(array $flags): string {
    if (empty($flags)) return 'ok';
    if (count($flags) >= 3) return 'warn';
    return 'review';
}
function hg_are_pretty_input(string $prettyId): string {
    return trim((string)$prettyId);
}

$hasPrettyId = hg_table_has_column($link, 'dim_realities', 'pretty_id');
$hasSortOrder = hg_table_has_column($link, 'dim_realities', 'sort_order');
$hasIsActive = hg_table_has_column($link, 'dim_realities', 'is_active');
$hasCreatedAt = hg_table_has_column($link, 'dim_realities', 'created_at');
$hasUpdatedAt = hg_table_has_column($link, 'dim_realities', 'updated_at');
$hasCharacterRealityId = hg_table_has_column($link, 'fact_characters', 'reality_id');
$hasTimelineRealityId = hg_table_has_column($link, 'bridge_timeline_events_realities', 'reality_id');

$actions = '<span class="adm-flex-right-8"><button class="btn btn-green" type="button" onclick="openRealityModal()">+ Nueva realidad</button><label class="adm-text-left">Filtro rapido <input class="inp" type="text" id="quickFilterRealities" placeholder="En esta pagina..."></label></span>';
if (!$isAjaxRequest) admin_panel_open('Realidades', $actions);

$flash = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) hg_admin_require_session(true);
    if (!hg_are_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $action = (string)($_POST['crud_action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'delete') {
            if ($id <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'ID invalido para eliminar.'];
            } else {
                $deps = hg_admin_catalog_get_reality_dependencies($link, $id);
                if (hg_admin_catalog_dependencies_total($deps) > 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'No se puede borrar la realidad porque tiene dependencias: ' . hg_admin_catalog_dependencies_summary($deps) . '.'];
                } elseif ($st = $link->prepare('DELETE FROM dim_realities WHERE id = ?')) {
                    $st->bind_param('i', $id);
                    if ($st->execute()) $flash[] = ['type' => 'ok', 'msg' => 'Realidad eliminada.'];
                    else { hg_runtime_log_error('admin_realities.delete', $st->error); $flash[] = ['type' => 'error', 'msg' => 'No se pudo eliminar la realidad.']; }
                    $st->close();
                } else {
                    hg_runtime_log_error('admin_realities.delete.prepare', $link->error);
                    $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar el borrado de la realidad.'];
                }
            }
        }
        if ($action === 'create' || $action === 'update') {
            $name = trim((string)($_POST['name'] ?? ''));
            $description = (string)($_POST['description'] ?? '');
            $sortOrder = $hasSortOrder ? (int)($_POST['sort_order'] ?? 0) : 0;
            $isActive = $hasIsActive ? ((int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0) : 1;
            $prettyId = $hasPrettyId ? hg_are_pretty_input((string)($_POST['pretty_id'] ?? '')) : '';
            if ($name === '') {
                $flash[] = ['type' => 'error', 'msg' => 'El nombre es obligatorio.'];
            } elseif ($hasPrettyId && $prettyId === '') {
                $flash[] = ['type' => 'error', 'msg' => 'El pretty_id es obligatorio.'];
            } elseif ($hasPrettyId && !hg_phase7_pretty_is_valid($prettyId)) {
                $flash[] = ['type' => 'error', 'msg' => 'El pretty_id solo puede contener minusculas, numeros y guiones.'];
            } elseif ($hasPrettyId && hg_admin_catalog_pretty_exists($link, 'dim_realities', $prettyId, $id)) {
                $flash[] = ['type' => 'error', 'msg' => 'Ya existe otra realidad con ese pretty_id.'];
            } elseif (hg_admin_catalog_name_exists($link, 'dim_realities', $name, $id)) {
                $flash[] = ['type' => 'error', 'msg' => 'Ya existe otra realidad con ese nombre.'];
            } elseif ($action === 'create') {
                $cols = ['name', 'description'];
                $vals = [$name, $description];
                $types = 'ss';
                if ($hasSortOrder) { $cols[] = 'sort_order'; $vals[] = $sortOrder; $types .= 'i'; }
                if ($hasIsActive) { $cols[] = 'is_active'; $vals[] = $isActive; $types .= 'i'; }
                if ($hasCreatedAt) $cols[] = 'created_at';
                if ($hasUpdatedAt) $cols[] = 'updated_at';
                $ph = [];
                foreach ($cols as $col) $ph[] = ($col === 'created_at' || $col === 'updated_at') ? 'NOW()' : '?';
                $sql = "INSERT INTO dim_realities (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $ph) . ")";
                if ($st = $link->prepare($sql)) {
                    $st->bind_param($types, ...$vals);
                    if ($st->execute()) {
                        $prettyOk = $hasPrettyId ? hg_admin_catalog_update_pretty_id($link, 'dim_realities', (int)$link->insert_id, $prettyId) : true;
                        $flash[] = ['type' => $prettyOk ? 'ok' : 'error', 'msg' => $prettyOk ? 'Realidad creada.' : 'Realidad creada, pero no se pudo guardar pretty_id.'];
                    } else {
                        hg_runtime_log_error('admin_realities.create', $st->error);
                        $flash[] = ['type' => 'error', 'msg' => 'No se pudo crear la realidad.'];
                    }
                    $st->close();
                } else {
                    hg_runtime_log_error('admin_realities.create.prepare', $link->error);
                    $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar el alta de la realidad.'];
                }
            } else {
                if ($id <= 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'ID invalido para actualizar.'];
                } else {
                    $sets = ['`name` = ?', '`description` = ?'];
                    $vals = [$name, $description];
                    $types = 'ss';
                    if ($hasSortOrder) { $sets[] = '`sort_order` = ?'; $vals[] = $sortOrder; $types .= 'i'; }
                    if ($hasIsActive) { $sets[] = '`is_active` = ?'; $vals[] = $isActive; $types .= 'i'; }
                    if ($hasUpdatedAt) $sets[] = '`updated_at` = NOW()';
                    $vals[] = $id;
                    $types .= 'i';
                    $sql = "UPDATE dim_realities SET " . implode(', ', $sets) . " WHERE id = ?";
                    if ($st = $link->prepare($sql)) {
                        $st->bind_param($types, ...$vals);
                        if ($st->execute()) {
                            $prettyOk = $hasPrettyId ? hg_admin_catalog_update_pretty_id($link, 'dim_realities', $id, $prettyId) : true;
                            $flash[] = ['type' => $prettyOk ? 'ok' : 'error', 'msg' => $prettyOk ? 'Realidad actualizada.' : 'Realidad actualizada, pero no se pudo guardar pretty_id.'];
                        } else {
                            hg_runtime_log_error('admin_realities.update', $st->error);
                            $flash[] = ['type' => 'error', 'msg' => 'No se pudo actualizar la realidad.'];
                        }
                        $st->close();
                    } else {
                        hg_runtime_log_error('admin_realities.update.prepare', $link->error);
                        $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar la actualizacion de la realidad.'];
                    }
                }
            }
        }
    }
}

$select = ['r.id', $hasPrettyId ? "COALESCE(r.pretty_id, '') AS pretty_id" : "'' AS pretty_id", 'r.name', "COALESCE(r.description, '') AS description", $hasSortOrder ? 'COALESCE(r.sort_order, 0) AS sort_order' : '0 AS sort_order', $hasIsActive ? 'COALESCE(r.is_active, 1) AS is_active' : '1 AS is_active', $hasCharacterRealityId ? '(SELECT COUNT(*) FROM fact_characters fc WHERE fc.reality_id = r.id) AS characters_count' : '0 AS characters_count', $hasTimelineRealityId ? '(SELECT COUNT(*) FROM bridge_timeline_events_realities bt WHERE bt.reality_id = r.id) AS timeline_count' : '0 AS timeline_count'];
$rows = [];
$rowsFull = [];
$auditRealitiesCount = 0;
$auditRealitiesPrettyCount = 0;
$realitiesWithoutTimelineCount = 0;
$orderBy = ($hasIsActive ? 'COALESCE(r.is_active, 1) DESC, ' : '') . ($hasSortOrder ? 'COALESCE(r.sort_order, 999999) ASC, ' : '') . 'r.name ASC, r.id ASC';
$rs = $link->query('SELECT ' . implode(', ', $select) . ' FROM dim_realities r ORDER BY ' . $orderBy);
if ($rs) {
    while ($row = $rs->fetch_assoc()) {
        $row['dependency_summary'] = hg_are_dep_summary($row);
        $row['audit_flags'] = hg_phase7_reality_flags($row);
        $row['audit_summary'] = hg_phase7_build_flags_summary((array)$row['audit_flags']);
        $row['audit_class'] = hg_are_audit_class((array)$row['audit_flags']);
        if (!empty($row['audit_flags'])) $auditRealitiesCount++;
        if (in_array('Sin pretty_id', (array)$row['audit_flags'], true) || in_array('Pretty invalido', (array)$row['audit_flags'], true)) $auditRealitiesPrettyCount++;
        if ((int)($row['timeline_count'] ?? 0) <= 0) $realitiesWithoutTimelineCount++;
        $rows[] = $row;
        $rowsFull[] = $row;
    }
    $rs->close();
}

$ajaxWrite = $isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action']);
if ($ajaxWrite) {
    $errors = [];
    $messages = [];
    foreach ($flash as $m) {
        $msg = (string)($m['msg'] ?? '');
        if ($msg === '') continue;
        if ((string)($m['type'] ?? '') === 'error') $errors[] = $msg; else $messages[] = $msg;
    }
    if (!empty($errors)) {
        if (function_exists('hg_admin_json_error')) hg_admin_json_error($errors[0], 400, ['flash' => $errors], ['messages' => $messages]);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => $errors[0], 'errors' => $errors, 'data' => ['messages' => $messages]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $okMsg = !empty($messages) ? $messages[count($messages) - 1] : 'Guardado';
    if (function_exists('hg_admin_json_success')) hg_admin_json_success(['rows' => $rows, 'rowsFull' => $rowsFull, 'messages' => $messages], $okMsg);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'message' => $okMsg, 'msg' => $okMsg, 'data' => ['rows' => $rows, 'rowsFull' => $rowsFull, 'messages' => $messages]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
?>
<?php if (!empty($flash)): ?><div class="flash"><?php foreach ($flash as $m): $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?><div class="<?= $cl ?>"><?= hg_are_h($m['msg'] ?? '') ?></div><?php endforeach; ?></div><?php endif; ?>
<style>.adm-dep-badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #1b4aa0;background:#00135a;color:#dff7ff;font-size:10px;line-height:1.2}.adm-dep-badge.off{background:#2a2a2a;border-color:#555;color:#ddd}.adm-status-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;border:1px solid #1b4aa0;background:#083679;color:#d9efff}.adm-status-pill.off{background:#3a2a2a;border-color:#884444;color:#ffd9d9}.adm-table-wrap{max-height:72vh;overflow:auto;border:1px solid #000088;border-radius:8px}.adm-summary-band{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 12px 0}.adm-summary-pill{padding:5px 10px;border-radius:999px;border:1px solid #17366e;background:#071b4a;color:#dfefff}.adm-audit-badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #2d6a9f;background:#0a2147;color:#e8f5ff;font-size:10px;line-height:1.2}.adm-audit-badge.review{background:#4a3200;border-color:#b37a11;color:#ffefbf}.adm-audit-badge.warn{background:#4a0000;border-color:#b31111;color:#ffd7d7}</style>
<div class="adm-summary-band"><span class="adm-summary-pill">Realidades auditadas: <?= (int)count($rows) ?></span><span class="adm-summary-pill">Con legacy pendiente: <?= (int)$auditRealitiesCount ?></span><span class="adm-summary-pill">Con pretty_id pendiente: <?= (int)$auditRealitiesPrettyCount ?></span><span class="adm-summary-pill">Sin timeline: <?= (int)$realitiesWithoutTimelineCount ?></span></div>
<div class="modal-back" id="realityModal"><div class="modal"><h3 id="realityModalTitle">Nueva realidad</h3><form method="post" id="realityForm"><input type="hidden" name="csrf" value="<?= hg_are_h($csrf) ?>"><input type="hidden" name="crud_action" id="reality_action" value="create"><input type="hidden" name="id" id="reality_id" value="0"><div class="modal-body"><div class="adm-grid-1-2"><label>Nombre</label><input class="inp" type="text" name="name" id="reality_name" maxlength="100" required><?php if ($hasPrettyId): ?><label>Pretty ID</label><input class="inp" type="text" name="pretty_id" id="reality_pretty_id" maxlength="190" placeholder="slug editorial obligatorio"><div class="adm-help-text">Manual y editorial. Solo minusculas, numeros y guiones.</div><?php endif; ?><?php if ($hasSortOrder): ?><label>Orden</label><input class="inp" type="number" name="sort_order" id="reality_sort_order" value="0"><?php endif; ?><?php if ($hasIsActive): ?><label>Estado</label><select class="select" name="is_active" id="reality_is_active"><option value="1">Activa</option><option value="0">Inactiva</option></select><?php endif; ?><label>Descripcion</label><textarea class="ta adm-w-full-resize-v" name="description" id="reality_description" rows="12"></textarea></div></div><div class="modal-actions"><button class="btn btn-green" type="submit">Guardar</button><button class="btn" type="button" onclick="closeRealityModal()">Cancelar</button></div></form></div></div>
<div class="modal-back" id="realityDeleteModal"><div class="modal adm-modal-sm"><h3>Confirmar borrado</h3><div class="adm-help-text" id="realityDeleteHelp">Se eliminara la realidad seleccionada.</div><form method="post" id="realityDeleteForm" class="adm-m-0"><input type="hidden" name="csrf" value="<?= hg_are_h($csrf) ?>"><input type="hidden" name="crud_action" value="delete"><input type="hidden" name="id" id="reality_delete_id" value="0"><div class="modal-actions"><button type="button" class="btn" onclick="closeRealityDeleteModal()">Cancelar</button><button type="submit" class="btn btn-red">Borrar</button></div></form></div></div>
<div class="adm-table-wrap"><table class="table" id="tablaRealities"><thead><tr><th class="adm-w-60">ID</th><th class="adm-w-220">Nombre</th><?php if ($hasSortOrder): ?><th class="adm-w-80">Orden</th><?php endif; ?><?php if ($hasIsActive): ?><th class="adm-w-90">Estado</th><?php endif; ?><?php if ($hasPrettyId): ?><th class="adm-w-220">Pretty ID</th><?php endif; ?><th class="adm-w-220">Dependencias</th><th class="adm-w-220">Revision</th><th>Descripcion</th><th class="adm-w-160">Acciones</th></tr></thead><tbody id="realitiesTbody"><?php foreach ($rows as $row): $search = trim((string)($row['name'] ?? '') . ' ' . (string)($row['pretty_id'] ?? '') . ' ' . (string)($row['description'] ?? '') . ' ' . (string)($row['dependency_summary'] ?? '') . ' ' . (string)($row['audit_summary'] ?? '')); if (function_exists('mb_strtolower')) $search = mb_strtolower($search, 'UTF-8'); else $search = strtolower($search); $totalDeps = (int)($row['characters_count'] ?? 0) + (int)($row['timeline_count'] ?? 0); $auditClass = trim((string)($row['audit_class'] ?? 'ok')); ?><tr data-search="<?= hg_are_h($search) ?>"><td><?= (int)$row['id'] ?></td><td><?= hg_are_h((string)($row['name'] ?? '')) ?></td><?php if ($hasSortOrder): ?><td><?= (int)($row['sort_order'] ?? 0) ?></td><?php endif; ?><?php if ($hasIsActive): ?><td><span class="adm-status-pill <?= (int)($row['is_active'] ?? 1) === 1 ? '' : 'off' ?>"><?= (int)($row['is_active'] ?? 1) === 1 ? 'Activa' : 'Inactiva' ?></span></td><?php endif; ?><?php if ($hasPrettyId): ?><td><?= hg_are_h((string)($row['pretty_id'] ?? '')) ?></td><?php endif; ?><td><span class="adm-dep-badge <?= $totalDeps <= 0 ? 'off' : '' ?>"><?= hg_are_h((string)($row['dependency_summary'] ?? 'Sin dependencias')) ?></span></td><td><span class="adm-audit-badge <?= hg_are_h($auditClass) ?>"><?= hg_are_h((string)($row['audit_summary'] ?? 'OK')) ?></span></td><td><?= hg_are_h(hg_are_short((string)($row['description'] ?? ''), 110)) ?></td><td><button class="btn" type="button" data-edit="<?= (int)$row['id'] ?>">Editar</button> <button class="btn btn-red" type="button" data-del="<?= (int)$row['id'] ?>">Borrar</button></td></tr><?php endforeach; ?><?php if (empty($rows)): ?><tr><td colspan="<?= 6 + ($hasSortOrder ? 1 : 0) + ($hasIsActive ? 1 : 0) + ($hasPrettyId ? 1 : 0) ?>" class="adm-color-muted">(Sin realidades)</td></tr><?php endif; ?></tbody></table></div>
<?php $adminHttpJs = '/assets/js/admin/admin-http.js'; $adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time(); ?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($csrf, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= hg_are_h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
let realitiesData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
function realityRequest(url, options){ return (window.HGAdminHttp && window.HGAdminHttp.request) ? window.HGAdminHttp.request(url, options || {}) : fetch(url, options || {}).then(async r => { const p = await r.json(); if (!r.ok || (p && p.ok === false)) { const e = new Error((p && (p.message || p.msg)) || ('HTTP ' + r.status)); e.status = r.status; e.payload = p; throw e; } return p; }); }
function realityUrl(){ const url = new URL(window.location.href); url.searchParams.set('s', 'admin_realities'); url.searchParams.set('ajax', '1'); url.searchParams.set('_ts', Date.now()); return url.toString(); }
function realityEsc(text){ return String(text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
function realityShort(text, max){ const clean = String(text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(); return clean.length <= max ? clean : (clean.slice(0, max) + '...'); }
function realitySummary(row){ const parts = []; const chars = parseInt(row.characters_count || 0, 10) || 0; const timeline = parseInt(row.timeline_count || 0, 10) || 0; if (chars > 0) parts.push(chars + ' personajes'); if (timeline > 0) parts.push(timeline + ' timeline'); return parts.length ? parts.join(' | ') : 'Sin dependencias'; }
function realityAuditSummary(row){ const flags = Array.isArray(row.audit_flags) ? row.audit_flags.filter(Boolean) : []; return flags.length ? flags.join(' | ') : 'OK'; }
function realityAuditClass(row){ const cls = String(row.audit_class || '').trim(); return cls || (Array.isArray(row.audit_flags) && row.audit_flags.length >= 3 ? 'warn' : (Array.isArray(row.audit_flags) && row.audit_flags.length ? 'review' : 'ok')); }
function refreshRealitySummary(rows){ const list = Array.isArray(rows) ? rows : []; const pills = document.querySelectorAll('.adm-summary-pill'); let legacy = 0; let pretty = 0; let withoutTimeline = 0; list.forEach(row => { const flags = Array.isArray(row.audit_flags) ? row.audit_flags : []; if (flags.length) legacy++; if (flags.includes('Sin pretty_id') || flags.includes('Pretty invalido')) pretty++; if ((parseInt(row.timeline_count || 0, 10) || 0) <= 0) withoutTimeline++; }); if (pills[0]) pills[0].textContent = 'Realidades auditadas: ' + list.length; if (pills[1]) pills[1].textContent = 'Con legacy pendiente: ' + legacy; if (pills[2]) pills[2].textContent = 'Con pretty_id pendiente: ' + pretty; if (pills[3]) pills[3].textContent = 'Sin timeline: ' + withoutTimeline; }
function openRealityModal(id = null){ document.getElementById('reality_action').value = 'create'; document.getElementById('reality_id').value = '0'; document.getElementById('reality_name').value = ''; const pretty = document.getElementById('reality_pretty_id'); if (pretty) pretty.value = ''; const sort = document.getElementById('reality_sort_order'); if (sort) sort.value = '0'; const active = document.getElementById('reality_is_active'); if (active) active.value = '1'; document.getElementById('reality_description').value = ''; if (id) { const row = realitiesData.find(r => parseInt(r.id || 0, 10) === parseInt(id || 0, 10)); if (row) { document.getElementById('realityModalTitle').textContent = 'Editar realidad'; document.getElementById('reality_action').value = 'update'; document.getElementById('reality_id').value = String(row.id || 0); document.getElementById('reality_name').value = row.name || ''; if (pretty) pretty.value = row.pretty_id || ''; if (sort) sort.value = String(parseInt(row.sort_order || 0, 10) || 0); if (active) active.value = String(parseInt(row.is_active || 1, 10) || 0); document.getElementById('reality_description').value = row.description || ''; } } else { document.getElementById('realityModalTitle').textContent = 'Nueva realidad'; } document.getElementById('realityModal').style.display = 'flex'; }
function closeRealityModal(){ document.getElementById('realityModal').style.display = 'none'; }
function openRealityDeleteModal(id){ const row = realitiesData.find(r => parseInt(r.id || 0, 10) === parseInt(id || 0, 10)) || null; document.getElementById('reality_delete_id').value = String(parseInt(id || 0, 10) || 0); document.getElementById('realityDeleteHelp').textContent = row ? ('Dependencias detectadas: ' + realitySummary(row) + '. El servidor bloqueara el borrado si sigue habiendo relaciones activas.') : 'Se eliminara la realidad seleccionada.'; document.getElementById('realityDeleteModal').style.display = 'flex'; }
function closeRealityDeleteModal(){ document.getElementById('realityDeleteModal').style.display = 'none'; }
function bindRealityRows(){ document.querySelectorAll('#realitiesTbody [data-edit]').forEach(btn => btn.onclick = () => openRealityModal(parseInt(btn.getAttribute('data-edit') || '0', 10) || 0)); document.querySelectorAll('#realitiesTbody [data-del]').forEach(btn => btn.onclick = () => openRealityDeleteModal(parseInt(btn.getAttribute('data-del') || '0', 10) || 0)); }
function renderRealityRows(rows){ const tbody = document.getElementById('realitiesTbody'); if (!tbody) return; if (!rows || !rows.length) { tbody.innerHTML = '<tr><td colspan="<?= 6 + ($hasSortOrder ? 1 : 0) + ($hasIsActive ? 1 : 0) + ($hasPrettyId ? 1 : 0) ?>" class="adm-color-muted">(Sin realidades)</td></tr>'; refreshRealitySummary([]); bindRealityRows(); return; } let html = ''; rows.forEach(row => { const id = parseInt(row.id || 0, 10) || 0; const summary = realitySummary(row); const auditSummary = realityAuditSummary(row); const auditClass = realityAuditClass(row); const totalDeps = (parseInt(row.characters_count || 0, 10) || 0) + (parseInt(row.timeline_count || 0, 10) || 0); const search = (String(row.name || '') + ' ' + String(row.pretty_id || '') + ' ' + String(row.description || '') + ' ' + summary + ' ' + auditSummary).toLowerCase(); html += '<tr data-search="' + realityEsc(search) + '"><td>' + id + '</td><td>' + realityEsc(row.name || '') + '</td><?php if ($hasSortOrder): ?><td>' + (parseInt(row.sort_order || 0, 10) || 0) + '</td><?php endif; ?><?php if ($hasIsActive): ?><td><span class="adm-status-pill ' + ((parseInt(row.is_active || 1, 10) || 0) === 1 ? '' : 'off') + '">' + ((parseInt(row.is_active || 1, 10) || 0) === 1 ? 'Activa' : 'Inactiva') + '</span></td><?php endif; ?><?php if ($hasPrettyId): ?><td>' + realityEsc(row.pretty_id || '') + '</td><?php endif; ?><td><span class="adm-dep-badge ' + (totalDeps <= 0 ? 'off' : '') + '">' + realityEsc(summary) + '</span></td><td><span class="adm-audit-badge ' + realityEsc(auditClass) + '">' + realityEsc(auditSummary) + '</span></td><td>' + realityEsc(realityShort(row.description || '', 110)) + '</td><td><button class="btn" type="button" data-edit="' + id + '">Editar</button> <button class="btn btn-red" type="button" data-del="' + id + '">Borrar</button></td></tr>'; }); tbody.innerHTML = html; refreshRealitySummary(rows); bindRealityRows(); }
function applyRealityFilter(){ const input = document.getElementById('quickFilterRealities'); if (!input) return; const q = (input.value || '').toLowerCase(); document.querySelectorAll('#realitiesTbody tr').forEach(tr => { const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase(); tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none'; }); }
document.getElementById('realityForm').addEventListener('submit', function(ev){ ev.preventDefault(); const fd = new FormData(this); fd.set('ajax', '1'); realityRequest(realityUrl(), { method: 'POST', body: fd, loadingEl: this }).then(payload => { const data = payload && payload.data ? payload.data : {}; if (Array.isArray(data.rows)) renderRealityRows(data.rows); if (Array.isArray(data.rowsFull)) realitiesData = data.rowsFull; closeRealityModal(); applyRealityFilter(); if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok'); }).catch(err => { const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al guardar'); alert(msg); }); });
document.getElementById('realityDeleteForm').addEventListener('submit', function(ev){ ev.preventDefault(); const fd = new FormData(this); fd.set('ajax', '1'); realityRequest(realityUrl(), { method: 'POST', body: fd, loadingEl: this }).then(payload => { const data = payload && payload.data ? payload.data : {}; if (Array.isArray(data.rows)) renderRealityRows(data.rows); if (Array.isArray(data.rowsFull)) realitiesData = data.rowsFull; closeRealityDeleteModal(); applyRealityFilter(); if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Eliminado', 'ok'); }).catch(err => { const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al borrar'); alert(msg); }); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeRealityModal(); closeRealityDeleteModal(); } });
const realityFilter = document.getElementById('quickFilterRealities'); if (realityFilter) realityFilter.addEventListener('input', applyRealityFilter);
bindRealityRows();
</script>
<?php if (!$isAjaxRequest) admin_panel_close(); ?>
