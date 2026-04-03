<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/admin_catalog_utils.php');
include_once(__DIR__ . '/../../helpers/admin_uploads.php');

$isAjaxRequest = (((string)($_GET['ajax'] ?? '') === '1') || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'));
$csrfKey = 'csrf_admin_chronicles';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');
$CHRONICLE_UPLOADDIR = hg_admin_project_root() . '/public/img/chronicles';
$CHRONICLE_URLBASE = '/img/chronicles';
if (!is_dir($CHRONICLE_UPLOADDIR)) { @mkdir($CHRONICLE_UPLOADDIR, 0775, true); }

function hg_ach_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function hg_ach_short(string $text, int $max = 120): string {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    if ($text === '') return '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $max ? (mb_substr($text, 0, $max, 'UTF-8') . '...') : $text;
    }
    return strlen($text) > $max ? (substr($text, 0, $max) . '...') : $text;
}
function hg_ach_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token') ? hg_admin_extract_csrf_token($payload) : (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, 'csrf_admin_chronicles')
        : (is_string($token) && $token !== '' && isset($_SESSION['csrf_admin_chronicles']) && hash_equals((string)$_SESSION['csrf_admin_chronicles'], $token));
}
function hg_ach_dep_summary(array $row): string {
    $parts = [];
    $chars = (int)($row['characters_count'] ?? 0);
    $timeline = (int)($row['timeline_count'] ?? 0);
    $seasons = (int)($row['seasons_count'] ?? 0);
    if ($chars > 0) $parts[] = $chars . ' personajes';
    if ($timeline > 0) $parts[] = $timeline . ' timeline';
    if ($seasons > 0) $parts[] = $seasons . ' temporadas';
    return !empty($parts) ? implode(' | ', $parts) : 'Sin dependencias';
}

$hasPrettyId = hg_table_has_column($link, 'dim_chronicles', 'pretty_id');
$hasSortOrder = hg_table_has_column($link, 'dim_chronicles', 'sort_order');
$hasImageUrl = hg_table_has_column($link, 'dim_chronicles', 'image_url');
$hasCreatedAt = hg_table_has_column($link, 'dim_chronicles', 'created_at');
$hasUpdatedAt = hg_table_has_column($link, 'dim_chronicles', 'updated_at');
$hasSeasonChronicleId = hg_table_has_column($link, 'dim_seasons', 'chronicle_id');
$hasCharacterChronicleId = hg_table_has_column($link, 'fact_characters', 'chronicle_id');
$hasTimelineChronicleId = hg_table_has_column($link, 'bridge_timeline_events_chronicles', 'chronicle_id');

$actions = '<span class="adm-flex-right-8"><button class="btn btn-green" type="button" onclick="openChronicleModal()">+ Nueva cronica</button><label class="adm-text-left">Filtro rapido <input class="inp" type="text" id="quickFilterChronicles" placeholder="En esta pagina..."></label></span>';
if (!$isAjaxRequest) admin_panel_open('Cronicas', $actions);

$flash = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) hg_admin_require_session(true);
    if (!hg_ach_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $action = (string)($_POST['crud_action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $currentImage = '';
        $chronicleExists = false;
        if ($hasImageUrl && ($action === 'update' || $action === 'delete') && $id > 0) {
            if ($st = $link->prepare('SELECT image_url FROM dim_chronicles WHERE id = ? LIMIT 1')) {
                $st->bind_param('i', $id);
                $st->execute();
                if ($rs = $st->get_result()) {
                    if ($row = $rs->fetch_assoc()) {
                        $chronicleExists = true;
                        $currentImage = (string)($row['image_url'] ?? '');
                    }
                }
                $st->close();
            }
        }
        if ($action === 'delete') {
            if ($id <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'ID invalido para eliminar.'];
            } elseif ($hasImageUrl && !$chronicleExists) {
                $flash[] = ['type' => 'error', 'msg' => 'La cronica no existe o ya no esta disponible.'];
            } else {
                $deps = hg_admin_catalog_get_chronicle_dependencies($link, $id);
                if (hg_admin_catalog_dependencies_total($deps) > 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'No se puede borrar la cronica porque tiene dependencias: ' . hg_admin_catalog_dependencies_summary($deps) . '.'];
                } elseif ($st = $link->prepare('DELETE FROM dim_chronicles WHERE id = ?')) {
                    $st->bind_param('i', $id);
                    if ($st->execute()) {
                        if ($hasImageUrl && $currentImage !== '') {
                            hg_admin_safe_unlink_upload($currentImage, $CHRONICLE_UPLOADDIR);
                        }
                        $flash[] = ['type' => 'ok', 'msg' => 'Cronica eliminada.'];
                    }
                    else { hg_runtime_log_error('admin_chronicles.delete', $st->error); $flash[] = ['type' => 'error', 'msg' => 'No se pudo eliminar la cronica.']; }
                    $st->close();
                } else {
                    hg_runtime_log_error('admin_chronicles.delete.prepare', $link->error);
                    $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar el borrado de la cronica.'];
                }
            }
        }
        if ($action === 'create' || $action === 'update') {
            $name = trim((string)($_POST['name'] ?? ''));
            $description = (string)($_POST['description'] ?? '');
            $sortOrder = $hasSortOrder ? (int)($_POST['sort_order'] ?? 0) : 0;
            $imageUrl = $hasImageUrl ? trim((string)($_POST['image_url'] ?? '')) : '';
            $removeImage = $hasImageUrl && ((int)($_POST['image_remove'] ?? 0) === 1);
            $hasImageUpload = $hasImageUrl && !empty($_FILES['image_upload']) && ((int)($_FILES['image_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
            if ($removeImage) {
                $imageUrl = '';
            }
            if ($name === '') {
                $flash[] = ['type' => 'error', 'msg' => 'El nombre es obligatorio.'];
            } elseif (hg_admin_catalog_name_exists($link, 'dim_chronicles', $name, $id)) {
                $flash[] = ['type' => 'error', 'msg' => 'Ya existe otra cronica con ese nombre.'];
            } elseif ($action === 'create') {
                $cols = ['name', 'description'];
                $vals = [$name, $description];
                $types = 'ss';
                if ($hasSortOrder) { $cols[] = 'sort_order'; $vals[] = $sortOrder; $types .= 'i'; }
                if ($hasImageUrl) { $cols[] = 'image_url'; $vals[] = $imageUrl; $types .= 's'; }
                if ($hasCreatedAt) $cols[] = 'created_at';
                if ($hasUpdatedAt) $cols[] = 'updated_at';
                $ph = [];
                foreach ($cols as $col) $ph[] = ($col === 'created_at' || $col === 'updated_at') ? 'NOW()' : '?';
                $sql = "INSERT INTO dim_chronicles (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $ph) . ")";
                if ($st = $link->prepare($sql)) {
                    $st->bind_param($types, ...$vals);
                    if ($st->execute()) {
                        $newId = (int)$link->insert_id;
                        $prettyOk = hg_admin_catalog_persist_pretty_id($link, 'dim_chronicles', $newId, $name);
                        if ($hasImageUpload) {
                            $res = hg_admin_save_image_upload($_FILES['image_upload'], 'chronicle', $newId, $name, $CHRONICLE_UPLOADDIR, $CHRONICLE_URLBASE);
                            if (!empty($res['ok'])) {
                                if ($st2 = $link->prepare('UPDATE dim_chronicles SET image_url = ? WHERE id = ?')) {
                                    $st2->bind_param('si', $res['url'], $newId);
                                    $st2->execute();
                                    $st2->close();
                                }
                                $imageUrl = (string)$res['url'];
                                $flash[] = ['type' => 'ok', 'msg' => 'Imagen de la cronica subida.'];
                            } elseif (($res['msg'] ?? '') !== 'no_file') {
                                $flash[] = ['type' => 'error', 'msg' => 'Imagen no guardada: ' . (string)$res['msg']];
                            }
                        }
                        $flash[] = ['type' => $prettyOk ? 'ok' : 'error', 'msg' => $prettyOk ? 'Cronica creada.' : 'Cronica creada, pero no se pudo guardar pretty_id.'];
                    } else {
                        hg_runtime_log_error('admin_chronicles.create', $st->error);
                        $flash[] = ['type' => 'error', 'msg' => 'No se pudo crear la cronica.'];
                    }
                    $st->close();
                } else {
                    hg_runtime_log_error('admin_chronicles.create.prepare', $link->error);
                    $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar el alta de la cronica.'];
                }
            } else {
                if ($id <= 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'ID invalido para actualizar.'];
                } else {
                    $sets = ['`name` = ?', '`description` = ?'];
                    $vals = [$name, $description];
                    $types = 'ss';
                    if ($hasSortOrder) { $sets[] = '`sort_order` = ?'; $vals[] = $sortOrder; $types .= 'i'; }
                    if ($hasImageUrl) { $sets[] = '`image_url` = ?'; $vals[] = $imageUrl; $types .= 's'; }
                    if ($hasUpdatedAt) $sets[] = '`updated_at` = NOW()';
                    $vals[] = $id;
                    $types .= 'i';
                    $sql = "UPDATE dim_chronicles SET " . implode(', ', $sets) . " WHERE id = ?";
                    if ($st = $link->prepare($sql)) {
                        $st->bind_param($types, ...$vals);
                        if ($st->execute()) {
                            $prettyOk = hg_admin_catalog_persist_pretty_id($link, 'dim_chronicles', $id, $name);
                            if ($hasImageUpload) {
                                $res = hg_admin_save_image_upload($_FILES['image_upload'], 'chronicle', $id, $name, $CHRONICLE_UPLOADDIR, $CHRONICLE_URLBASE);
                                if (!empty($res['ok'])) {
                                    if ($currentImage !== '') {
                                        hg_admin_safe_unlink_upload($currentImage, $CHRONICLE_UPLOADDIR);
                                    }
                                    if ($st2 = $link->prepare('UPDATE dim_chronicles SET image_url = ? WHERE id = ?')) {
                                        $st2->bind_param('si', $res['url'], $id);
                                        $st2->execute();
                                        $st2->close();
                                    }
                                    $imageUrl = (string)$res['url'];
                                    $flash[] = ['type' => 'ok', 'msg' => 'Imagen de la cronica actualizada.'];
                                } elseif (($res['msg'] ?? '') !== 'no_file') {
                                    $flash[] = ['type' => 'error', 'msg' => 'Imagen no guardada: ' . (string)$res['msg']];
                                }
                            } elseif ($hasImageUrl && $currentImage !== '' && $currentImage !== $imageUrl) {
                                hg_admin_safe_unlink_upload($currentImage, $CHRONICLE_UPLOADDIR);
                            }
                            $flash[] = ['type' => $prettyOk ? 'ok' : 'error', 'msg' => $prettyOk ? 'Cronica actualizada.' : 'Cronica actualizada, pero no se pudo guardar pretty_id.'];
                        } else {
                            hg_runtime_log_error('admin_chronicles.update', $st->error);
                            $flash[] = ['type' => 'error', 'msg' => 'No se pudo actualizar la cronica.'];
                        }
                        $st->close();
                    } else {
                        hg_runtime_log_error('admin_chronicles.update.prepare', $link->error);
                        $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar la actualizacion de la cronica.'];
                    }
                }
            }
        }
    }
}

$select = ['c.id', $hasPrettyId ? "COALESCE(c.pretty_id, '') AS pretty_id" : "'' AS pretty_id", 'c.name', $hasSortOrder ? 'COALESCE(c.sort_order, 0) AS sort_order' : '0 AS sort_order', "COALESCE(c.description, '') AS description", $hasImageUrl ? "COALESCE(c.image_url, '') AS image_url" : "'' AS image_url", $hasCharacterChronicleId ? '(SELECT COUNT(*) FROM fact_characters fc WHERE fc.chronicle_id = c.id) AS characters_count' : '0 AS characters_count', $hasTimelineChronicleId ? '(SELECT COUNT(*) FROM bridge_timeline_events_chronicles bt WHERE bt.chronicle_id = c.id) AS timeline_count' : '0 AS timeline_count', $hasSeasonChronicleId ? '(SELECT COUNT(*) FROM dim_seasons s WHERE s.chronicle_id = c.id) AS seasons_count' : '0 AS seasons_count'];
$rows = [];
$rowsFull = [];
$orderBy = $hasSortOrder ? 'COALESCE(c.sort_order, 999999) ASC, c.name ASC, c.id ASC' : 'c.name ASC, c.id ASC';
$rs = $link->query('SELECT ' . implode(', ', $select) . ' FROM dim_chronicles c ORDER BY ' . $orderBy);
if ($rs) {
    while ($row = $rs->fetch_assoc()) { $row['dependency_summary'] = hg_ach_dep_summary($row); $rows[] = $row; $rowsFull[] = $row; }
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
<?php if (!empty($flash)): ?><div class="flash"><?php foreach ($flash as $m): $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?><div class="<?= $cl ?>"><?= hg_ach_h($m['msg'] ?? '') ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if (!$hasImageUrl): ?><div class="flash"><div class="info">El esquema actual de <code>dim_chronicles</code> no incluye <code>image_url</code>; la subida de imagenes en cronicas depende de esa columna.</div></div><?php endif; ?>
<style>.adm-dep-badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #1b4aa0;background:#00135a;color:#dff7ff;font-size:10px;line-height:1.2}.adm-dep-badge.off{background:#2a2a2a;border-color:#555;color:#ddd}.adm-table-wrap{max-height:72vh;overflow:auto;border:1px solid #000088;border-radius:8px}.adm-thumb-hint{font-size:10px;color:#9db5d3}</style>
<div class="modal-back" id="chronicleModal"><div class="modal"><h3 id="chronicleModalTitle">Nueva cronica</h3><form method="post" id="chronicleForm" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= hg_ach_h($csrf) ?>"><input type="hidden" name="crud_action" id="chronicle_action" value="create"><input type="hidden" name="id" id="chronicle_id" value="0"><div class="modal-body"><div class="adm-grid-1-2"><label>Nombre</label><input class="inp" type="text" name="name" id="chronicle_name" maxlength="100" required><?php if ($hasSortOrder): ?><label>Orden</label><input class="inp" type="number" name="sort_order" id="chronicle_sort_order" value="0"><?php endif; ?><?php if ($hasImageUrl): ?><label>Imagen</label><input class="inp" type="text" name="image_url" id="chronicle_image_url" maxlength="600" placeholder="/img/chronicles/... o URL completa"><div class="adm-thumb-hint">Puedes escribir una ruta manual o subir un fichero.</div><label>Subir imagen</label><input class="inp" type="file" name="image_upload" id="chronicle_image_upload" accept="image/*"><label class="adm-thumb-hint"><input type="checkbox" name="image_remove" id="chronicle_image_remove" value="1"> Quitar imagen actual</label><?php endif; ?><label>Descripcion</label><textarea class="ta adm-w-full-resize-v" name="description" id="chronicle_description" rows="12"></textarea></div></div><div class="modal-actions"><button class="btn btn-green" type="submit">Guardar</button><button class="btn" type="button" onclick="closeChronicleModal()">Cancelar</button></div></form></div></div>
<div class="modal-back" id="chronicleDeleteModal"><div class="modal adm-modal-sm"><h3>Confirmar borrado</h3><div class="adm-help-text" id="chronicleDeleteHelp">Se eliminara la cronica seleccionada.</div><form method="post" id="chronicleDeleteForm" class="adm-m-0"><input type="hidden" name="csrf" value="<?= hg_ach_h($csrf) ?>"><input type="hidden" name="crud_action" value="delete"><input type="hidden" name="id" id="chronicle_delete_id" value="0"><div class="modal-actions"><button type="button" class="btn" onclick="closeChronicleDeleteModal()">Cancelar</button><button type="submit" class="btn btn-red">Borrar</button></div></form></div></div>
<div class="adm-table-wrap"><table class="table" id="tablaChronicles"><thead><tr><th class="adm-w-60">ID</th><th class="adm-w-220">Nombre</th><?php if ($hasSortOrder): ?><th class="adm-w-80">Orden</th><?php endif; ?><?php if ($hasPrettyId): ?><th class="adm-w-220">Pretty ID</th><?php endif; ?><?php if ($hasImageUrl): ?><th class="adm-w-220">Imagen</th><?php endif; ?><th class="adm-w-220">Dependencias</th><th>Descripcion</th><th class="adm-w-160">Acciones</th></tr></thead><tbody id="chroniclesTbody"><?php foreach ($rows as $row): $search = trim((string)($row['name'] ?? '') . ' ' . (string)($row['pretty_id'] ?? '') . ' ' . (string)($row['image_url'] ?? '') . ' ' . (string)($row['description'] ?? '') . ' ' . (string)($row['dependency_summary'] ?? '')); if (function_exists('mb_strtolower')) $search = mb_strtolower($search, 'UTF-8'); else $search = strtolower($search); $totalDeps = (int)($row['characters_count'] ?? 0) + (int)($row['timeline_count'] ?? 0) + (int)($row['seasons_count'] ?? 0); ?><tr data-search="<?= hg_ach_h($search) ?>"><td><?= (int)$row['id'] ?></td><td><?= hg_ach_h((string)($row['name'] ?? '')) ?></td><?php if ($hasSortOrder): ?><td><?= (int)($row['sort_order'] ?? 0) ?></td><?php endif; ?><?php if ($hasPrettyId): ?><td><?= hg_ach_h((string)($row['pretty_id'] ?? '')) ?></td><?php endif; ?><?php if ($hasImageUrl): ?><td><?= hg_ach_h(hg_ach_short((string)($row['image_url'] ?? ''), 70)) ?></td><?php endif; ?><td><span class="adm-dep-badge <?= $totalDeps <= 0 ? 'off' : '' ?>"><?= hg_ach_h((string)($row['dependency_summary'] ?? 'Sin dependencias')) ?></span></td><td><?= hg_ach_h(hg_ach_short((string)($row['description'] ?? ''), 110)) ?></td><td><button class="btn" type="button" data-edit="<?= (int)$row['id'] ?>">Editar</button> <button class="btn btn-red" type="button" data-del="<?= (int)$row['id'] ?>">Borrar</button></td></tr><?php endforeach; ?><?php if (empty($rows)): ?><tr><td colspan="<?= 5 + ($hasSortOrder ? 1 : 0) + ($hasPrettyId ? 1 : 0) + ($hasImageUrl ? 1 : 0) ?>" class="adm-color-muted">(Sin cronicas)</td></tr><?php endif; ?></tbody></table></div>
<?php $adminHttpJs = '/assets/js/admin/admin-http.js'; $adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time(); ?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($csrf, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= hg_ach_h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
let chroniclesData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
function chronicleRequest(url, options){ return (window.HGAdminHttp && window.HGAdminHttp.request) ? window.HGAdminHttp.request(url, options || {}) : fetch(url, options || {}).then(async r => { const p = await r.json(); if (!r.ok || (p && p.ok === false)) { const e = new Error((p && (p.message || p.msg)) || ('HTTP ' + r.status)); e.status = r.status; e.payload = p; throw e; } return p; }); }
function chronicleUrl(){ const url = new URL(window.location.href); url.searchParams.set('s', 'admin_chronicles'); url.searchParams.set('ajax', '1'); url.searchParams.set('_ts', Date.now()); return url.toString(); }
function chronicleEsc(text){ return String(text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
function chronicleShort(text, max){ const clean = String(text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(); return clean.length <= max ? clean : (clean.slice(0, max) + '...'); }
function chronicleSummary(row){ const parts = []; const chars = parseInt(row.characters_count || 0, 10) || 0; const timeline = parseInt(row.timeline_count || 0, 10) || 0; const seasons = parseInt(row.seasons_count || 0, 10) || 0; if (chars > 0) parts.push(chars + ' personajes'); if (timeline > 0) parts.push(timeline + ' timeline'); if (seasons > 0) parts.push(seasons + ' temporadas'); return parts.length ? parts.join(' | ') : 'Sin dependencias'; }
function openChronicleModal(id = null){ document.getElementById('chronicle_action').value = 'create'; document.getElementById('chronicle_id').value = '0'; document.getElementById('chronicle_name').value = ''; const sort = document.getElementById('chronicle_sort_order'); if (sort) sort.value = '0'; const image = document.getElementById('chronicle_image_url'); if (image) image.value = ''; const imageUpload = document.getElementById('chronicle_image_upload'); if (imageUpload) imageUpload.value = ''; const imageRemove = document.getElementById('chronicle_image_remove'); if (imageRemove) imageRemove.checked = false; document.getElementById('chronicle_description').value = ''; if (id) { const row = chroniclesData.find(r => parseInt(r.id || 0, 10) === parseInt(id || 0, 10)); if (row) { document.getElementById('chronicleModalTitle').textContent = 'Editar cronica'; document.getElementById('chronicle_action').value = 'update'; document.getElementById('chronicle_id').value = String(row.id || 0); document.getElementById('chronicle_name').value = row.name || ''; if (sort) sort.value = String(parseInt(row.sort_order || 0, 10) || 0); if (image) image.value = row.image_url || ''; document.getElementById('chronicle_description').value = row.description || ''; } } else { document.getElementById('chronicleModalTitle').textContent = 'Nueva cronica'; } document.getElementById('chronicleModal').style.display = 'flex'; }
function closeChronicleModal(){ document.getElementById('chronicleModal').style.display = 'none'; }
function openChronicleDeleteModal(id){ const row = chroniclesData.find(r => parseInt(r.id || 0, 10) === parseInt(id || 0, 10)) || null; document.getElementById('chronicle_delete_id').value = String(parseInt(id || 0, 10) || 0); document.getElementById('chronicleDeleteHelp').textContent = row ? ('Dependencias detectadas: ' + chronicleSummary(row) + '. El servidor bloqueara el borrado si sigue habiendo relaciones activas.') : 'Se eliminara la cronica seleccionada.'; document.getElementById('chronicleDeleteModal').style.display = 'flex'; }
function closeChronicleDeleteModal(){ document.getElementById('chronicleDeleteModal').style.display = 'none'; }
function bindChronicleRows(){ document.querySelectorAll('#chroniclesTbody [data-edit]').forEach(btn => btn.onclick = () => openChronicleModal(parseInt(btn.getAttribute('data-edit') || '0', 10) || 0)); document.querySelectorAll('#chroniclesTbody [data-del]').forEach(btn => btn.onclick = () => openChronicleDeleteModal(parseInt(btn.getAttribute('data-del') || '0', 10) || 0)); }
function renderChronicleRows(rows){ const tbody = document.getElementById('chroniclesTbody'); if (!tbody) return; if (!rows || !rows.length) { tbody.innerHTML = '<tr><td colspan="<?= 5 + ($hasSortOrder ? 1 : 0) + ($hasPrettyId ? 1 : 0) + ($hasImageUrl ? 1 : 0) ?>" class="adm-color-muted">(Sin cronicas)</td></tr>'; bindChronicleRows(); return; } let html = ''; rows.forEach(row => { const id = parseInt(row.id || 0, 10) || 0; const summary = chronicleSummary(row); const totalDeps = (parseInt(row.characters_count || 0, 10) || 0) + (parseInt(row.timeline_count || 0, 10) || 0) + (parseInt(row.seasons_count || 0, 10) || 0); const search = (String(row.name || '') + ' ' + String(row.pretty_id || '') + ' ' + String(row.image_url || '') + ' ' + String(row.description || '') + ' ' + summary).toLowerCase(); html += '<tr data-search="' + chronicleEsc(search) + '"><td>' + id + '</td><td>' + chronicleEsc(row.name || '') + '</td><?php if ($hasSortOrder): ?><td>' + (parseInt(row.sort_order || 0, 10) || 0) + '</td><?php endif; ?><?php if ($hasPrettyId): ?><td>' + chronicleEsc(row.pretty_id || '') + '</td><?php endif; ?><?php if ($hasImageUrl): ?><td>' + chronicleEsc(chronicleShort(row.image_url || '', 70)) + '</td><?php endif; ?><td><span class="adm-dep-badge ' + (totalDeps <= 0 ? 'off' : '') + '">' + chronicleEsc(summary) + '</span></td><td>' + chronicleEsc(chronicleShort(row.description || '', 110)) + '</td><td><button class="btn" type="button" data-edit="' + id + '">Editar</button> <button class="btn btn-red" type="button" data-del="' + id + '">Borrar</button></td></tr>'; }); tbody.innerHTML = html; bindChronicleRows(); }
function applyChronicleFilter(){ const input = document.getElementById('quickFilterChronicles'); if (!input) return; const q = (input.value || '').toLowerCase(); document.querySelectorAll('#chroniclesTbody tr').forEach(tr => { const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase(); tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none'; }); }
document.getElementById('chronicleForm').addEventListener('submit', function(ev){ ev.preventDefault(); const fd = new FormData(this); fd.set('ajax', '1'); chronicleRequest(chronicleUrl(), { method: 'POST', body: fd, loadingEl: this }).then(payload => { const data = payload && payload.data ? payload.data : {}; if (Array.isArray(data.rows)) renderChronicleRows(data.rows); if (Array.isArray(data.rowsFull)) chroniclesData = data.rowsFull; closeChronicleModal(); applyChronicleFilter(); if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok'); }).catch(err => { const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al guardar'); alert(msg); }); });
document.getElementById('chronicleDeleteForm').addEventListener('submit', function(ev){ ev.preventDefault(); const fd = new FormData(this); fd.set('ajax', '1'); chronicleRequest(chronicleUrl(), { method: 'POST', body: fd, loadingEl: this }).then(payload => { const data = payload && payload.data ? payload.data : {}; if (Array.isArray(data.rows)) renderChronicleRows(data.rows); if (Array.isArray(data.rowsFull)) chroniclesData = data.rowsFull; closeChronicleDeleteModal(); applyChronicleFilter(); if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Eliminado', 'ok'); }).catch(err => { const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al borrar'); alert(msg); }); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeChronicleModal(); closeChronicleDeleteModal(); } });
const chronicleFilter = document.getElementById('quickFilterChronicles'); if (chronicleFilter) chronicleFilter.addEventListener('input', applyChronicleFilter);
bindChronicleRows();
</script>
<?php if (!$isAjaxRequest) admin_panel_close(); ?>
