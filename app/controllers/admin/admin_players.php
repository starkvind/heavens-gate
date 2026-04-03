<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/admin_catalog_utils.php');
include_once(__DIR__ . '/../../helpers/admin_phase7_audit.php');
include_once(__DIR__ . '/../../helpers/admin_uploads.php');

$isAjaxRequest = (((string)($_GET['ajax'] ?? '') === '1') || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'));
$csrfKey = 'csrf_admin_players';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');
$PLAYER_UPLOADDIR = hg_admin_project_root() . '/public/img/player';
$PLAYER_URLBASE = '/img/player';
if (!is_dir($PLAYER_UPLOADDIR)) { @mkdir($PLAYER_UPLOADDIR, 0775, true); }

function hg_apl_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function hg_apl_short(string $text, int $max = 120): string {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    if ($text === '') return '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $max ? (mb_substr($text, 0, $max, 'UTF-8') . '...') : $text;
    }
    return strlen($text) > $max ? (substr($text, 0, $max) . '...') : $text;
}
function hg_apl_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token') ? hg_admin_extract_csrf_token($payload) : (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, 'csrf_admin_players')
        : (is_string($token) && $token !== '' && isset($_SESSION['csrf_admin_players']) && hash_equals((string)$_SESSION['csrf_admin_players'], $token));
}
function hg_apl_full_name(array $row): string {
    $name = trim((string)($row['name'] ?? ''));
    $surname = trim((string)($row['surname'] ?? ''));
    return trim($name . ' ' . $surname);
}
function hg_apl_dep_summary(array $row): string {
    $count = (int)($row['characters_count'] ?? 0);
    return $count > 0 ? ($count . ' personajes') : 'Sin dependencias';
}
function hg_apl_audit_class(array $flags): string {
    if (empty($flags)) return 'ok';
    if (count($flags) >= 3) return 'warn';
    return 'review';
}
function hg_apl_normalize_pretty_input(string $prettyId): string {
    $prettyId = trim($prettyId);
    return $prettyId !== '' ? slugify_pretty_id($prettyId) : '';
}
function hg_apl_player_exists(mysqli $link, string $name, string $surname, int $excludeId = 0): bool {
    $name = trim($name);
    $surname = trim($surname);
    if ($name === '') {
        return false;
    }
    $sql = hg_table_has_column($link, 'dim_players', 'surname')
        ? "SELECT id FROM dim_players WHERE TRIM(COALESCE(name, '')) = ? AND TRIM(COALESCE(surname, '')) = ? AND id <> ? LIMIT 1"
        : "SELECT id FROM dim_players WHERE TRIM(COALESCE(name, '')) = ? AND id <> ? LIMIT 1";
    $st = $link->prepare($sql);
    if (!$st) {
        return false;
    }
    $foundId = 0;
    if (hg_table_has_column($link, 'dim_players', 'surname')) {
        $st->bind_param('ssi', $name, $surname, $excludeId);
    } else {
        $st->bind_param('si', $name, $excludeId);
    }
    $ok = false;
    if ($st->execute()) {
        $st->bind_result($foundId);
        $ok = $st->fetch();
    }
    $st->close();
    return (bool)$ok && $foundId > 0;
}

$hasPrettyId = hg_table_has_column($link, 'dim_players', 'pretty_id');
$hasSurname = hg_table_has_column($link, 'dim_players', 'surname');
$hasShowInCatalog = hg_table_has_column($link, 'dim_players', 'show_in_catalog');
$hasPicture = hg_table_has_column($link, 'dim_players', 'picture');
$hasDescription = hg_table_has_column($link, 'dim_players', 'description');
$hasCreatedAt = hg_table_has_column($link, 'dim_players', 'created_at');
$hasUpdatedAt = hg_table_has_column($link, 'dim_players', 'updated_at');
$hasPlayerId = hg_table_has_column($link, 'fact_characters', 'player_id');

$actions = '<span class="adm-flex-right-8"><button class="btn btn-green" type="button" onclick="openPlayerModal()">+ Nuevo jugador</button><label class="adm-text-left">Filtro rapido <input class="inp" type="text" id="quickFilterPlayers" placeholder="En esta pagina..."></label></span>';
if (!$isAjaxRequest) admin_panel_open('Jugadores', $actions);

$flash = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) hg_admin_require_session(true);
    if (!hg_apl_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $action = (string)($_POST['crud_action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $currentPicture = '';
        $currentPrettyId = '';
        $playerExists = false;
        if (($hasPicture || $hasPrettyId) && ($action === 'update' || $action === 'delete') && $id > 0) {
            $currentSelect = [];
            if ($hasPicture) $currentSelect[] = 'picture';
            if ($hasPrettyId) $currentSelect[] = 'pretty_id';
            if ($st = $link->prepare('SELECT ' . implode(', ', $currentSelect) . ' FROM dim_players WHERE id = ? LIMIT 1')) {
                $st->bind_param('i', $id);
                $st->execute();
                if ($rs = $st->get_result()) {
                    if ($row = $rs->fetch_assoc()) {
                        $playerExists = true;
                        $currentPicture = (string)($row['picture'] ?? '');
                        $currentPrettyId = (string)($row['pretty_id'] ?? '');
                    }
                }
                $st->close();
            }
        }
        if ($action === 'delete') {
            if ($id <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'ID invalido para eliminar.'];
            } elseif ($hasPicture && !$playerExists) {
                $flash[] = ['type' => 'error', 'msg' => 'El jugador no existe o ya no esta disponible.'];
            } else {
                $deps = hg_admin_catalog_get_player_dependencies($link, $id);
                if (hg_admin_catalog_dependencies_total($deps) > 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'No se puede borrar el jugador porque tiene dependencias: ' . hg_admin_catalog_dependencies_summary($deps) . '.'];
                } elseif ($st = $link->prepare('DELETE FROM dim_players WHERE id = ?')) {
                    $st->bind_param('i', $id);
                    if ($st->execute()) {
                        if ($hasPicture && $currentPicture !== '') {
                            hg_admin_safe_unlink_upload($currentPicture, $PLAYER_UPLOADDIR);
                        }
                        $flash[] = ['type' => 'ok', 'msg' => 'Jugador eliminado.'];
                    }
                    else { hg_runtime_log_error('admin_players.delete', $st->error); $flash[] = ['type' => 'error', 'msg' => 'No se pudo eliminar el jugador.']; }
                    $st->close();
                } else {
                    hg_runtime_log_error('admin_players.delete.prepare', $link->error);
                    $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar el borrado del jugador.'];
                }
            }
        }
        if ($action === 'create' || $action === 'update') {
            $name = trim((string)($_POST['name'] ?? ''));
            $surname = $hasSurname ? trim((string)($_POST['surname'] ?? '')) : '';
            $showInCatalog = $hasShowInCatalog ? (((int)($_POST['show_in_catalog'] ?? 0) === 1) ? 1 : 0) : 0;
            $picture = $hasPicture ? trim((string)($_POST['picture'] ?? '')) : '';
            $removePicture = $hasPicture && ((int)($_POST['picture_remove'] ?? 0) === 1);
            $hasPictureUpload = $hasPicture && !empty($_FILES['picture_upload']) && ((int)($_FILES['picture_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
            $description = $hasDescription ? (string)($_POST['description'] ?? '') : '';
            $prettyIdInput = $hasPrettyId ? trim((string)($_POST['pretty_id'] ?? '')) : '';
            $prettyIdManual = $hasPrettyId ? hg_apl_normalize_pretty_input($prettyIdInput) : '';
            $prettySource = trim($name . ' ' . $surname);

            if ($removePicture) {
                $picture = '';
            }

            if ($name === '') {
                $flash[] = ['type' => 'error', 'msg' => 'El nombre es obligatorio.'];
            } elseif ($hasPrettyId && $prettyIdInput !== '' && $prettyIdManual === '') {
                $flash[] = ['type' => 'error', 'msg' => 'El pretty_id indicado no es utilizable.'];
            } elseif ($hasPrettyId && $prettyIdManual !== '' && hg_admin_catalog_pretty_exists($link, 'dim_players', $prettyIdManual, $id)) {
                $flash[] = ['type' => 'error', 'msg' => 'Ya existe otro jugador con ese pretty_id.'];
            } elseif (hg_apl_player_exists($link, $name, $surname, $id)) {
                $flash[] = ['type' => 'error', 'msg' => 'Ya existe otro jugador con ese nombre y apellidos.'];
            } elseif ($action === 'create') {
                $cols = ['name'];
                $vals = [$name];
                $types = 's';
                if ($hasSurname) { $cols[] = 'surname'; $vals[] = $surname; $types .= 's'; }
                if ($hasShowInCatalog) { $cols[] = 'show_in_catalog'; $vals[] = $showInCatalog; $types .= 'i'; }
                if ($hasPicture) { $cols[] = 'picture'; $vals[] = $picture; $types .= 's'; }
                if ($hasDescription) { $cols[] = 'description'; $vals[] = $description; $types .= 's'; }
                if ($hasCreatedAt) $cols[] = 'created_at';
                if ($hasUpdatedAt) $cols[] = 'updated_at';
                $ph = [];
                foreach ($cols as $col) $ph[] = ($col === 'created_at' || $col === 'updated_at') ? 'NOW()' : '?';
                $sql = "INSERT INTO dim_players (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $ph) . ")";
                if ($st = $link->prepare($sql)) {
                    $st->bind_param($types, ...$vals);
                    if ($st->execute()) {
                        $newId = (int)$link->insert_id;
                        $prettyOk = hg_admin_catalog_assign_pretty_id($link, 'dim_players', $newId, $prettyIdManual, $prettySource !== '' ? $prettySource : $name);
                        if ($hasPictureUpload) {
                            $res = hg_admin_save_image_upload($_FILES['picture_upload'], 'player', $newId, $prettySource !== '' ? $prettySource : $name, $PLAYER_UPLOADDIR, $PLAYER_URLBASE);
                            if (!empty($res['ok'])) {
                                if ($st2 = $link->prepare('UPDATE dim_players SET picture = ? WHERE id = ?')) {
                                    $st2->bind_param('si', $res['url'], $newId);
                                    $st2->execute();
                                    $st2->close();
                                }
                                $picture = (string)$res['url'];
                                $flash[] = ['type' => 'ok', 'msg' => 'Imagen del jugador subida.'];
                            } elseif (($res['msg'] ?? '') !== 'no_file') {
                                $flash[] = ['type' => 'error', 'msg' => 'Imagen no guardada: ' . (string)$res['msg']];
                            }
                        }
                        $flash[] = ['type' => $prettyOk ? 'ok' : 'error', 'msg' => $prettyOk ? 'Jugador creado.' : 'Jugador creado, pero no se pudo guardar pretty_id.'];
                    } else {
                        hg_runtime_log_error('admin_players.create', $st->error);
                        $flash[] = ['type' => 'error', 'msg' => 'No se pudo crear el jugador.'];
                    }
                    $st->close();
                } else {
                    hg_runtime_log_error('admin_players.create.prepare', $link->error);
                    $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar el alta del jugador.'];
                }
            } else {
                if ($id <= 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'ID invalido para actualizar.'];
                } else {
                    $sets = ['`name` = ?'];
                    $vals = [$name];
                    $types = 's';
                    if ($hasSurname) { $sets[] = '`surname` = ?'; $vals[] = $surname; $types .= 's'; }
                    if ($hasShowInCatalog) { $sets[] = '`show_in_catalog` = ?'; $vals[] = $showInCatalog; $types .= 'i'; }
                    if ($hasPicture) { $sets[] = '`picture` = ?'; $vals[] = $picture; $types .= 's'; }
                    if ($hasDescription) { $sets[] = '`description` = ?'; $vals[] = $description; $types .= 's'; }
                    if ($hasUpdatedAt) $sets[] = '`updated_at` = NOW()';
                    $vals[] = $id;
                    $types .= 'i';
                    $sql = "UPDATE dim_players SET " . implode(', ', $sets) . " WHERE id = ?";
                    if ($st = $link->prepare($sql)) {
                        $st->bind_param($types, ...$vals);
                        if ($st->execute()) {
                            $prettyTarget = $prettyIdManual !== '' ? $prettyIdManual : $currentPrettyId;
                            $prettyOk = hg_admin_catalog_assign_pretty_id($link, 'dim_players', $id, $prettyTarget, $prettySource !== '' ? $prettySource : $name);
                            if ($hasPictureUpload) {
                                $res = hg_admin_save_image_upload($_FILES['picture_upload'], 'player', $id, $prettySource !== '' ? $prettySource : $name, $PLAYER_UPLOADDIR, $PLAYER_URLBASE);
                                if (!empty($res['ok'])) {
                                    if ($currentPicture !== '') {
                                        hg_admin_safe_unlink_upload($currentPicture, $PLAYER_UPLOADDIR);
                                    }
                                    if ($st2 = $link->prepare('UPDATE dim_players SET picture = ? WHERE id = ?')) {
                                        $st2->bind_param('si', $res['url'], $id);
                                        $st2->execute();
                                        $st2->close();
                                    }
                                    $picture = (string)$res['url'];
                                    $flash[] = ['type' => 'ok', 'msg' => 'Imagen del jugador actualizada.'];
                                } elseif (($res['msg'] ?? '') !== 'no_file') {
                                    $flash[] = ['type' => 'error', 'msg' => 'Imagen no guardada: ' . (string)$res['msg']];
                                }
                            } elseif ($hasPicture && $currentPicture !== '' && $currentPicture !== $picture) {
                                hg_admin_safe_unlink_upload($currentPicture, $PLAYER_UPLOADDIR);
                            }
                            $flash[] = ['type' => $prettyOk ? 'ok' : 'error', 'msg' => $prettyOk ? 'Jugador actualizado.' : 'Jugador actualizado, pero no se pudo guardar pretty_id.'];
                        } else {
                            hg_runtime_log_error('admin_players.update', $st->error);
                            $flash[] = ['type' => 'error', 'msg' => 'No se pudo actualizar el jugador.'];
                        }
                        $st->close();
                    } else {
                        hg_runtime_log_error('admin_players.update.prepare', $link->error);
                        $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar la actualizacion del jugador.'];
                    }
                }
            }
        }
    }
}

$select = [
    'p.id',
    $hasPrettyId ? "COALESCE(p.pretty_id, '') AS pretty_id" : "'' AS pretty_id",
    "COALESCE(p.name, '') AS name",
    $hasSurname ? "COALESCE(p.surname, '') AS surname" : "'' AS surname",
    $hasShowInCatalog ? 'COALESCE(p.show_in_catalog, 0) AS show_in_catalog' : '0 AS show_in_catalog',
    $hasPicture ? "COALESCE(p.picture, '') AS picture" : "'' AS picture",
    $hasDescription ? "COALESCE(p.description, '') AS description" : "'' AS description",
    $hasPlayerId ? '(SELECT COUNT(*) FROM fact_characters fc WHERE fc.player_id = p.id) AS characters_count' : '0 AS characters_count',
];
$rows = [];
$rowsFull = [];
$auditPlayersCount = 0;
$auditPlayersPrettyCount = 0;
$playersWithoutCharactersCount = 0;
$orderBy = 'p.name ASC' . ($hasSurname ? ', p.surname ASC' : '') . ', p.id ASC';
$rs = $link->query('SELECT ' . implode(', ', $select) . ' FROM dim_players p ORDER BY ' . $orderBy);
if ($rs) {
    while ($row = $rs->fetch_assoc()) {
        $row['full_name'] = hg_apl_full_name($row);
        $row['dependency_summary'] = hg_apl_dep_summary($row);
        $row['audit_flags'] = hg_phase7_player_flags($row);
        $row['audit_summary'] = hg_phase7_build_flags_summary((array)$row['audit_flags']);
        $row['audit_class'] = hg_apl_audit_class((array)$row['audit_flags']);
        if (!empty($row['audit_flags'])) $auditPlayersCount++;
        if (in_array('Sin pretty_id', (array)$row['audit_flags'], true) || in_array('Pretty invalido', (array)$row['audit_flags'], true)) $auditPlayersPrettyCount++;
        if ((int)($row['characters_count'] ?? 0) <= 0) $playersWithoutCharactersCount++;
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
<?php if (!empty($flash)): ?><div class="flash"><?php foreach ($flash as $m): $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?><div class="<?= $cl ?>"><?= hg_apl_h($m['msg'] ?? '') ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if (!$hasPicture): ?><div class="flash"><div class="info">El esquema actual de <code>dim_players</code> no incluye <code>picture</code>; la subida de imagenes no estara disponible hasta anadir esa columna.</div></div><?php endif; ?>
<style>.adm-dep-badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #1b4aa0;background:#00135a;color:#dff7ff;font-size:10px;line-height:1.2}.adm-dep-badge.off{background:#2a2a2a;border-color:#555;color:#ddd}.adm-status-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;border:1px solid #1b4aa0;background:#083679;color:#d9efff}.adm-status-pill.off{background:#3a2a2a;border-color:#884444;color:#ffd9d9}.adm-table-wrap{max-height:72vh;overflow:auto;border:1px solid #000088;border-radius:8px}.adm-thumb-hint{font-size:10px;color:#9db5d3}.adm-summary-band{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 12px 0}.adm-summary-pill{padding:5px 10px;border-radius:999px;border:1px solid #17366e;background:#071b4a;color:#dfefff}.adm-audit-badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #2d6a9f;background:#0a2147;color:#e8f5ff;font-size:10px;line-height:1.2}.adm-audit-badge.review{background:#4a3200;border-color:#b37a11;color:#ffefbf}.adm-audit-badge.warn{background:#4a0000;border-color:#b31111;color:#ffd7d7}</style>
<div class="adm-summary-band"><span class="adm-summary-pill">Jugadores auditados: <?= (int)count($rows) ?></span><span class="adm-summary-pill">Con legacy pendiente: <?= (int)$auditPlayersCount ?></span><span class="adm-summary-pill">Con pretty_id pendiente: <?= (int)$auditPlayersPrettyCount ?></span><span class="adm-summary-pill">Sin personajes: <?= (int)$playersWithoutCharactersCount ?></span></div>
<div class="modal-back" id="playerModal"><div class="modal"><h3 id="playerModalTitle">Nuevo jugador</h3><form method="post" id="playerForm" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= hg_apl_h($csrf) ?>"><input type="hidden" name="crud_action" id="player_action" value="create"><input type="hidden" name="id" id="player_id" value="0"><div class="modal-body"><div class="adm-grid-1-2"><label>Nombre</label><input class="inp" type="text" name="name" id="player_name" maxlength="120" required><?php if ($hasSurname): ?><label>Apellidos</label><input class="inp" type="text" name="surname" id="player_surname" maxlength="160"><?php endif; ?><?php if ($hasPrettyId): ?><label>Pretty ID</label><input class="inp" type="text" name="pretty_id" id="player_pretty_id" maxlength="190" placeholder="manual o vacio para autogenerar"><div class="adm-thumb-hint">Editable manualmente. Si se deja vacio al crear, se autogenera.</div><?php endif; ?><?php if ($hasShowInCatalog): ?><label>Visible en catalogo</label><select class="select" name="show_in_catalog" id="player_show_in_catalog"><option value="0">No</option><option value="1">Si</option></select><?php endif; ?><?php if ($hasPicture): ?><label>Imagen</label><input class="inp" type="text" name="picture" id="player_picture" maxlength="600" placeholder="/img/player/... o URL completa"><div class="adm-thumb-hint">Puedes escribir una ruta manual o subir un fichero.</div><label>Subir imagen</label><input class="inp" type="file" name="picture_upload" id="player_picture_upload" accept="image/*"><label class="adm-thumb-hint"><input type="checkbox" name="picture_remove" id="player_picture_remove" value="1"> Quitar imagen actual</label><?php endif; ?><?php if ($hasDescription): ?><label>Descripcion</label><textarea class="ta adm-w-full-resize-v" name="description" id="player_description" rows="12"></textarea><?php endif; ?></div></div><div class="modal-actions"><button class="btn btn-green" type="submit">Guardar</button><button class="btn" type="button" onclick="closePlayerModal()">Cancelar</button></div></form></div></div>
<div class="modal-back" id="playerDeleteModal"><div class="modal adm-modal-sm"><h3>Confirmar borrado</h3><div class="adm-help-text" id="playerDeleteHelp">Se eliminara el jugador seleccionado.</div><form method="post" id="playerDeleteForm" class="adm-m-0"><input type="hidden" name="csrf" value="<?= hg_apl_h($csrf) ?>"><input type="hidden" name="crud_action" value="delete"><input type="hidden" name="id" id="player_delete_id" value="0"><div class="modal-actions"><button type="button" class="btn" onclick="closePlayerDeleteModal()">Cancelar</button><button type="submit" class="btn btn-red">Borrar</button></div></form></div></div>
<div class="adm-table-wrap"><table class="table" id="tablaPlayers"><thead><tr><th class="adm-w-60">ID</th><th class="adm-w-220">Jugador</th><?php if ($hasShowInCatalog): ?><th class="adm-w-120">Catalogo</th><?php endif; ?><?php if ($hasPrettyId): ?><th class="adm-w-220">Pretty ID</th><?php endif; ?><th class="adm-w-140">Personajes</th><th class="adm-w-220">Revision</th><?php if ($hasPicture): ?><th class="adm-w-220">Imagen</th><?php endif; ?><th>Descripcion</th><th class="adm-w-160">Acciones</th></tr></thead><tbody id="playersTbody"><?php foreach ($rows as $row): $search = trim((string)($row['full_name'] ?? '') . ' ' . (string)($row['pretty_id'] ?? '') . ' ' . (string)($row['picture'] ?? '') . ' ' . (string)($row['description'] ?? '') . ' ' . (string)($row['dependency_summary'] ?? '') . ' ' . (string)($row['audit_summary'] ?? '')); if (function_exists('mb_strtolower')) $search = mb_strtolower($search, 'UTF-8'); else $search = strtolower($search); $totalDeps = (int)($row['characters_count'] ?? 0); $auditClass = trim((string)($row['audit_class'] ?? 'ok')); ?><tr data-search="<?= hg_apl_h($search) ?>"><td><?= (int)$row['id'] ?></td><td><?= hg_apl_h((string)($row['full_name'] ?? '')) ?></td><?php if ($hasShowInCatalog): ?><td><span class="adm-status-pill <?= (int)($row['show_in_catalog'] ?? 0) === 1 ? '' : 'off' ?>"><?= (int)($row['show_in_catalog'] ?? 0) === 1 ? 'Visible' : 'Oculto' ?></span></td><?php endif; ?><?php if ($hasPrettyId): ?><td><?= hg_apl_h((string)($row['pretty_id'] ?? '')) ?></td><?php endif; ?><td><span class="adm-dep-badge <?= $totalDeps <= 0 ? 'off' : '' ?>"><?= hg_apl_h((string)($row['dependency_summary'] ?? 'Sin dependencias')) ?></span></td><td><span class="adm-audit-badge <?= hg_apl_h($auditClass) ?>"><?= hg_apl_h((string)($row['audit_summary'] ?? 'OK')) ?></span></td><?php if ($hasPicture): ?><td><?= hg_apl_h(hg_apl_short((string)($row['picture'] ?? ''), 70)) ?></td><?php endif; ?><td><?= hg_apl_h(hg_apl_short((string)($row['description'] ?? ''), 110)) ?></td><td><button class="btn" type="button" data-edit="<?= (int)$row['id'] ?>">Editar</button> <button class="btn btn-red" type="button" data-del="<?= (int)$row['id'] ?>">Borrar</button></td></tr><?php endforeach; ?><?php if (empty($rows)): ?><tr><td colspan="<?= 6 + ($hasShowInCatalog ? 1 : 0) + ($hasPrettyId ? 1 : 0) + ($hasPicture ? 1 : 0) ?>" class="adm-color-muted">(Sin jugadores)</td></tr><?php endif; ?></tbody></table></div>
<?php $adminHttpJs = '/assets/js/admin/admin-http.js'; $adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time(); ?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($csrf, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= hg_apl_h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
let playersData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
function playerRequest(url, options){ return (window.HGAdminHttp && window.HGAdminHttp.request) ? window.HGAdminHttp.request(url, options || {}) : fetch(url, options || {}).then(async r => { const p = await r.json(); if (!r.ok || (p && p.ok === false)) { const e = new Error((p && (p.message || p.msg)) || ('HTTP ' + r.status)); e.status = r.status; e.payload = p; throw e; } return p; }); }
function playerUrl(){ const url = new URL(window.location.href); url.searchParams.set('s', 'admin_players'); url.searchParams.set('ajax', '1'); url.searchParams.set('_ts', Date.now()); return url.toString(); }
function playerEsc(text){ return String(text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
function playerShort(text, max){ const clean = String(text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim(); return clean.length <= max ? clean : (clean.slice(0, max) + '...'); }
function playerFullName(row){ return [String(row.name || '').trim(), String(row.surname || '').trim()].join(' ').replace(/\s+/g, ' ').trim(); }
function playerSummary(row){ const chars = parseInt(row.characters_count || 0, 10) || 0; return chars > 0 ? (chars + ' personajes') : 'Sin dependencias'; }
function playerAuditSummary(row){ const flags = Array.isArray(row.audit_flags) ? row.audit_flags.filter(Boolean) : []; return flags.length ? flags.join(' | ') : 'OK'; }
function playerAuditClass(row){ const cls = String(row.audit_class || '').trim(); return cls || (Array.isArray(row.audit_flags) && row.audit_flags.length >= 3 ? 'warn' : (Array.isArray(row.audit_flags) && row.audit_flags.length ? 'review' : 'ok')); }
function refreshPlayerSummary(rows){ const list = Array.isArray(rows) ? rows : []; const pills = document.querySelectorAll('.adm-summary-pill'); let legacy = 0; let pretty = 0; let withoutChars = 0; list.forEach(row => { const flags = Array.isArray(row.audit_flags) ? row.audit_flags : []; if (flags.length) legacy++; if (flags.includes('Sin pretty_id') || flags.includes('Pretty invalido')) pretty++; if ((parseInt(row.characters_count || 0, 10) || 0) <= 0) withoutChars++; }); if (pills[0]) pills[0].textContent = 'Jugadores auditados: ' + list.length; if (pills[1]) pills[1].textContent = 'Con legacy pendiente: ' + legacy; if (pills[2]) pills[2].textContent = 'Con pretty_id pendiente: ' + pretty; if (pills[3]) pills[3].textContent = 'Sin personajes: ' + withoutChars; }
function openPlayerModal(id = null){ document.getElementById('player_action').value = 'create'; document.getElementById('player_id').value = '0'; document.getElementById('player_name').value = ''; const surname = document.getElementById('player_surname'); if (surname) surname.value = ''; const pretty = document.getElementById('player_pretty_id'); if (pretty) pretty.value = ''; const visible = document.getElementById('player_show_in_catalog'); if (visible) visible.value = '0'; const picture = document.getElementById('player_picture'); if (picture) picture.value = ''; const pictureUpload = document.getElementById('player_picture_upload'); if (pictureUpload) pictureUpload.value = ''; const pictureRemove = document.getElementById('player_picture_remove'); if (pictureRemove) pictureRemove.checked = false; const description = document.getElementById('player_description'); if (description) description.value = ''; if (id) { const row = playersData.find(r => parseInt(r.id || 0, 10) === parseInt(id || 0, 10)); if (row) { document.getElementById('playerModalTitle').textContent = 'Editar jugador'; document.getElementById('player_action').value = 'update'; document.getElementById('player_id').value = String(row.id || 0); document.getElementById('player_name').value = row.name || ''; if (surname) surname.value = row.surname || ''; if (pretty) pretty.value = row.pretty_id || ''; if (visible) visible.value = String(parseInt(row.show_in_catalog || 0, 10) || 0); if (picture) picture.value = row.picture || ''; if (description) description.value = row.description || ''; } } else { document.getElementById('playerModalTitle').textContent = 'Nuevo jugador'; } document.getElementById('playerModal').style.display = 'flex'; }
function closePlayerModal(){ document.getElementById('playerModal').style.display = 'none'; }
function openPlayerDeleteModal(id){ const row = playersData.find(r => parseInt(r.id || 0, 10) === parseInt(id || 0, 10)) || null; document.getElementById('player_delete_id').value = String(parseInt(id || 0, 10) || 0); document.getElementById('playerDeleteHelp').textContent = row ? ('Dependencias detectadas: ' + playerSummary(row) + '. El servidor bloqueara el borrado si sigue habiendo personajes asociados.') : 'Se eliminara el jugador seleccionado.'; document.getElementById('playerDeleteModal').style.display = 'flex'; }
function closePlayerDeleteModal(){ document.getElementById('playerDeleteModal').style.display = 'none'; }
function bindPlayerRows(){ document.querySelectorAll('#playersTbody [data-edit]').forEach(btn => btn.onclick = () => openPlayerModal(parseInt(btn.getAttribute('data-edit') || '0', 10) || 0)); document.querySelectorAll('#playersTbody [data-del]').forEach(btn => btn.onclick = () => openPlayerDeleteModal(parseInt(btn.getAttribute('data-del') || '0', 10) || 0)); }
function renderPlayerRows(rows){ const tbody = document.getElementById('playersTbody'); if (!tbody) return; if (!rows || !rows.length) { tbody.innerHTML = '<tr><td colspan="<?= 6 + ($hasShowInCatalog ? 1 : 0) + ($hasPrettyId ? 1 : 0) + ($hasPicture ? 1 : 0) ?>" class="adm-color-muted">(Sin jugadores)</td></tr>'; refreshPlayerSummary([]); bindPlayerRows(); return; } let html = ''; rows.forEach(row => { const id = parseInt(row.id || 0, 10) || 0; const fullName = playerFullName(row); const summary = playerSummary(row); const totalDeps = parseInt(row.characters_count || 0, 10) || 0; const auditSummary = playerAuditSummary(row); const auditClass = playerAuditClass(row); const search = (fullName + ' ' + String(row.pretty_id || '') + ' ' + String(row.picture || '') + ' ' + String(row.description || '') + ' ' + summary + ' ' + auditSummary).toLowerCase(); html += '<tr data-search="' + playerEsc(search) + '"><td>' + id + '</td><td>' + playerEsc(fullName) + '</td><?php if ($hasShowInCatalog): ?><td><span class="adm-status-pill ' + ((parseInt(row.show_in_catalog || 0, 10) || 0) === 1 ? '' : 'off') + '">' + ((parseInt(row.show_in_catalog || 0, 10) || 0) === 1 ? 'Visible' : 'Oculto') + '</span></td><?php endif; ?><?php if ($hasPrettyId): ?><td>' + playerEsc(row.pretty_id || '') + '</td><?php endif; ?><td><span class="adm-dep-badge ' + (totalDeps <= 0 ? 'off' : '') + '">' + playerEsc(summary) + '</span></td><td><span class="adm-audit-badge ' + playerEsc(auditClass) + '">' + playerEsc(auditSummary) + '</span></td><?php if ($hasPicture): ?><td>' + playerEsc(playerShort(row.picture || '', 70)) + '</td><?php endif; ?><td>' + playerEsc(playerShort(row.description || '', 110)) + '</td><td><button class="btn" type="button" data-edit="' + id + '">Editar</button> <button class="btn btn-red" type="button" data-del="' + id + '">Borrar</button></td></tr>'; }); tbody.innerHTML = html; refreshPlayerSummary(rows); bindPlayerRows(); }
function applyPlayerFilter(){ const input = document.getElementById('quickFilterPlayers'); if (!input) return; const q = (input.value || '').toLowerCase(); document.querySelectorAll('#playersTbody tr').forEach(tr => { const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase(); tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none'; }); }
document.getElementById('playerForm').addEventListener('submit', function(ev){ ev.preventDefault(); const fd = new FormData(this); fd.set('ajax', '1'); playerRequest(playerUrl(), { method: 'POST', body: fd, loadingEl: this }).then(payload => { const data = payload && payload.data ? payload.data : {}; if (Array.isArray(data.rows)) renderPlayerRows(data.rows); if (Array.isArray(data.rowsFull)) playersData = data.rowsFull; closePlayerModal(); applyPlayerFilter(); if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok'); }).catch(err => { const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al guardar'); alert(msg); }); });
document.getElementById('playerDeleteForm').addEventListener('submit', function(ev){ ev.preventDefault(); const fd = new FormData(this); fd.set('ajax', '1'); playerRequest(playerUrl(), { method: 'POST', body: fd, loadingEl: this }).then(payload => { const data = payload && payload.data ? payload.data : {}; if (Array.isArray(data.rows)) renderPlayerRows(data.rows); if (Array.isArray(data.rowsFull)) playersData = data.rowsFull; closePlayerDeleteModal(); applyPlayerFilter(); if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Eliminado', 'ok'); }).catch(err => { const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al borrar'); alert(msg); }); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closePlayerModal(); closePlayerDeleteModal(); } });
const playerFilter = document.getElementById('quickFilterPlayers'); if (playerFilter) playerFilter.addEventListener('input', applyPlayerFilter);
bindPlayerRows();
</script>
<?php if (!$isAjaxRequest) admin_panel_close(); ?>
