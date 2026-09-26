<?php
// admin_character_conditions_bridge.php - asignacion de condiciones a personajes
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../domains/characters/admin_conditions_bridge.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function accb_dt(?string $raw): ?string {
    $s = trim((string)$raw);
    if ($s === '') return null;
    $s = str_replace('T', ' ', $s);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) $s .= ' 00:00:00';
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?$/', $s)) return null;
    if (strlen($s) === 16) $s .= ':00';
    return $s;
}

$csrfKey = 'csrf_admin_character_conditions_bridge';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');
if ($csrf === '') {
    $_SESSION[$csrfKey] = bin2hex(random_bytes(16));
    $csrf = $_SESSION[$csrfKey];
}
function accb_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token') ? hg_admin_extract_csrf_token($payload) : (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, 'csrf_admin_character_conditions_bridge')
        : (is_string($token) && $token !== '' && isset($_SESSION['csrf_admin_character_conditions_bridge']) && hash_equals((string)$_SESSION['csrf_admin_character_conditions_bridge'], $token));
}

$bridgeColumns = ['instance_no', 'location', 'notes', 'source', 'acquired_at', 'healed_at', 'is_active', 'created_at', 'updated_at'];
$bridgeReady = hg_accb_table_exists($link, 'bridge_characters_conditions') && hg_accb_table_exists($link, 'dim_character_conditions');
foreach ($bridgeColumns as $bridgeColumn) {
    $bridgeReady = $bridgeReady && hg_accb_column_exists($link, 'bridge_characters_conditions', $bridgeColumn);
}

$flash = [];
$characterOptions = hg_accb_character_options($link);
$conditionCatalog = hg_accb_condition_catalog($link);
$conditionOptions = $conditionCatalog['options'];
$conditionMeta = $conditionCatalog['meta'];

if (!$bridgeReady) {
    $flash[] = ['type' => 'error', 'msg' => 'La tabla de condiciones de personaje no esta disponible con la estructura esperada en esta base de datos.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    if (!accb_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } elseif (!$bridgeReady) {
        $flash[] = ['type' => 'error', 'msg' => 'No se puede guardar hasta aplicar la migracion del bridge.'];
    } else {
        $action = (string)($_POST['crud_action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'delete') {
            if ($id <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'ID invalido para borrar.'];
            } else {
                $result = hg_accb_delete($link, $id);
                $flash[] = ['type'=>!empty($result['ok'])?'ok':'error','msg'=>(string)$result['message']];
            }
        } elseif ($action === 'create' || $action === 'update') {
            $characterId = (int)($_POST['character_id'] ?? 0);
            $conditionId = (int)($_POST['condition_id'] ?? 0);
            $instanceNo = max(1, (int)($_POST['instance_no'] ?? 1));
            $location = trim((string)($_POST['location'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            $source = trim((string)($_POST['source'] ?? ''));
            $isActive = ((string)($_POST['is_active'] ?? '1') === '1') ? 1 : 0;
            $acquiredAt = accb_dt($_POST['acquired_at'] ?? '');
            $healedAt = accb_dt($_POST['healed_at'] ?? '');

            if (!isset($characterOptions[$characterId])) $flash[] = ['type' => 'error', 'msg' => 'Personaje invalido.'];
            if (!isset($conditionMeta[$conditionId])) $flash[] = ['type' => 'error', 'msg' => 'Condicion invalida.'];
            if (strlen($location) > 120) $flash[] = ['type' => 'error', 'msg' => 'Localizacion demasiado larga.'];
            if (strlen($source) > 150) $flash[] = ['type' => 'error', 'msg' => 'Fuente demasiado larga.'];
            if ($conditionId > 0 && isset($conditionMeta[$conditionId])) {
                $max = $conditionMeta[$conditionId]['max_instances'];
                if ($max !== null && $max > 0 && $instanceNo > $max) {
                    $flash[] = ['type' => 'error', 'msg' => 'La instancia supera el maximo permitido para esta condicion.'];
                }
            }

            $hasErr = false;
            foreach ($flash as $m) { if (($m['type'] ?? '') === 'error') { $hasErr = true; break; } }

            if (!$hasErr) {
                $result = hg_accb_save($link, $action, $id, [
                    'character_id'=>$characterId,
                    'condition_id'=>$conditionId,
                    'instance_no'=>$instanceNo,
                    'location'=>$location,
                    'notes'=>$notes,
                    'source'=>$source,
                    'acquired_at'=>$acquiredAt,
                    'healed_at'=>$healedAt,
                    'is_active'=>$isActive,
                ]);
                $flash[] = ['type'=>!empty($result['ok'])?'ok':'error','msg'=>(string)$result['message']];
            }
        }
    }
}

$selectedCharacter = isset($_GET['character_id']) ? max(0, (int)$_GET['character_id']) : 0;
$assignments = $bridgeReady ? hg_accb_fetch_assignments($link, $selectedCharacter) : [];

$byCharacter = [];
foreach ($assignments as $row) {
    $cid = (int)$row['character_id'];
    if (!isset($byCharacter[$cid])) {
        $byCharacter[$cid] = [
            'character_id' => $cid,
            'character_name' => (string)$row['character_name'],
            'active_count' => 0,
            'total_count' => 0,
            'summary' => [],
        ];
    }
    $byCharacter[$cid]['total_count']++;
    if ((int)$row['is_active'] === 1) $byCharacter[$cid]['active_count']++;
    $label = (string)$row['condition_name'];
    if (trim((string)$row['location']) !== '') $label .= ' (' . trim((string)$row['location']) . ')';
    elseif ((int)$row['instance_no'] > 1) $label .= ' #' . (int)$row['instance_no'];
    $byCharacter[$cid]['summary'][] = $label;
}

$actions = '<span class="adm-flex-right-8">'
    . '<button class="btn btn-green" type="button" id="btnNewConditionLink">+ Asignar condicion</button>'
    . '<a class="btn" href="/talim?s=admin_character_conditions">Catalogo de condiciones</a>'
    . '</span>';
admin_panel_open('Condiciones de Personaje', $actions);
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m): $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?>
        <div class="<?= $cl ?>"><?= h($m['msg'] ?? '') ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="get" class="adm-flex-8-m10">
    <input type="hidden" name="s" value="admin_character_conditions_bridge">
    <label>Personaje
        <select class="select" name="character_id" onchange="this.form.submit()">
            <option value="0">Todos</option>
            <?php foreach ($characterOptions as $cid => $name): ?>
                <option value="<?= (int)$cid ?>" <?= $selectedCharacter === (int)$cid ? 'selected' : '' ?>><?= h($name) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn" type="submit">Filtrar</button>
</form>

<table class="table">
    <thead>
        <tr>
            <th class="adm-w-260">Personaje</th>
            <th class="adm-w-120">Activas</th>
            <th>Condiciones</th>
            <th class="adm-w-260">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($byCharacter as $row): ?>
            <tr>
                <td><?= h($row['character_name']) ?></td>
                <td><?= (int)$row['active_count'] ?> / <?= (int)$row['total_count'] ?></td>
                <td><?= h(implode(', ', array_slice($row['summary'], 0, 8))) ?><?= count($row['summary']) > 8 ? '...' : '' ?></td>
                <td>
                    <button class="btn" type="button" data-manage-character="<?= (int)$row['character_id'] ?>">Gestionar</button>
                    <a class="btn" href="/talim?s=admin_characters&character_id=<?= (int)$row['character_id'] ?>">Ver PJ</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($byCharacter)): ?>
            <tr><td colspan="4" class="adm-color-muted">(No hay personajes con condiciones asignadas todavia)</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<h3>Asignaciones</h3>
<table class="table" id="conditionsBridgeTable">
    <thead>
        <tr>
            <th class="adm-w-70">ID</th>
            <th class="adm-w-220">Personaje</th>
            <th class="adm-w-220">Condicion</th>
            <th class="adm-w-90">Inst.</th>
            <th class="adm-w-160">Localizacion</th>
            <th class="adm-w-90">Estado</th>
            <th class="adm-w-210">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($assignments as $r): ?>
            <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= h($r['character_name']) ?></td>
                <td><?= h($r['condition_name']) ?></td>
                <td><?= (int)$r['instance_no'] ?></td>
                <td><?= h((string)$r['location']) ?></td>
                <td><?= (int)$r['is_active'] === 1 ? 'Activa' : 'Inactiva' ?></td>
                <td>
                    <button class="btn" type="button" data-edit="<?= (int)$r['id'] ?>">Editar</button>
                    <button class="btn btn-red" type="button" data-delete="<?= (int)$r['id'] ?>">Borrar</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($assignments)): ?>
            <tr><td colspan="7" class="adm-color-muted">(Sin asignaciones)</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="modal-back" id="conditionBridgeModal">
    <div class="modal" role="dialog" aria-modal="true">
        <h3 id="conditionBridgeTitle">Asignar condicion</h3>
        <form method="post" id="conditionBridgeForm" class="adm-m-0">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="crud_action" id="cb_action" value="create">
            <input type="hidden" name="id" id="cb_id" value="0">
            <div class="grid">
                <label><span>Personaje</span>
                    <select class="select" name="character_id" id="cb_character_id" required>
                        <option value="0">-- Selecciona --</option>
                        <?php foreach ($characterOptions as $cid => $name): ?>
                            <option value="<?= (int)$cid ?>"><?= h($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Condicion</span>
                    <select class="select" name="condition_id" id="cb_condition_id" required>
                        <option value="0">-- Selecciona --</option>
                        <?php foreach ($conditionMeta as $cond): ?>
                            <?php $max = $cond['max_instances']; $maxText = $max === null ? 'sin limite' : ('max ' . (int)$max); ?>
                            <option value="<?= (int)$cond['id'] ?>"><?= h($cond['name'] . ' - ' . $cond['category'] . ' (' . $maxText . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Instancia</span>
                    <input class="inp" type="number" name="instance_no" id="cb_instance_no" min="1" value="1">
                </label>
                <label><span>Estado</span>
                    <select class="select" name="is_active" id="cb_is_active">
                        <option value="1">Activa</option>
                        <option value="0">Inactiva / historica</option>
                    </select>
                </label>
                <label><span>Localizacion</span>
                    <input class="inp" type="text" name="location" id="cb_location" maxlength="120" placeholder="brazo izquierdo, ojo derecho...">
                </label>
                <label><span>Fuente</span>
                    <input class="inp" type="text" name="source" id="cb_source" maxlength="150">
                </label>
                <label><span>Adquirida</span>
                    <input class="inp" type="datetime-local" name="acquired_at" id="cb_acquired_at">
                </label>
                <label><span>Curada</span>
                    <input class="inp" type="datetime-local" name="healed_at" id="cb_healed_at">
                </label>
                <label class="field-full"><span>Notas</span>
                    <textarea class="inp ta-lg" name="notes" id="cb_notes"></textarea>
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn" id="btnConditionBridgeCancel">Cancelar</button>
                <button type="submit" class="btn btn-green">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-back" id="conditionBridgeDeleteModal">
    <div class="modal adm-modal-sm">
        <h3>Confirmar borrado</h3>
        <form method="post" class="adm-m-0">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="crud_action" value="delete">
            <input type="hidden" name="id" id="cb_delete_id" value="0">
            <div class="modal-actions">
                <button type="button" class="btn" id="btnConditionBridgeDeleteCancel">Cancelar</button>
                <button type="submit" class="btn btn-red">Borrar</button>
            </div>
        </form>
    </div>
</div>

<script>
var CONDITION_ASSIGNMENTS = <?= json_encode($assignments, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
(function(){
    var modal = document.getElementById('conditionBridgeModal');
    var delModal = document.getElementById('conditionBridgeDeleteModal');
    var form = document.getElementById('conditionBridgeForm');
    function rowById(id){
        id = parseInt(id || 0, 10) || 0;
        return CONDITION_ASSIGNMENTS.find(function(r){ return (parseInt(r.id || 0, 10) || 0) === id; }) || null;
    }
    function dtLocal(v){
        v = String(v || '');
        if (!v) return '';
        return v.replace(' ', 'T').slice(0, 16);
    }
    function openForm(characterId, row){
        document.getElementById('conditionBridgeTitle').textContent = row ? 'Editar condicion de personaje' : 'Asignar condicion';
        document.getElementById('cb_action').value = row ? 'update' : 'create';
        document.getElementById('cb_id').value = row ? String(row.id || 0) : '0';
        document.getElementById('cb_character_id').value = String(row ? (row.character_id || 0) : (characterId || <?= (int)$selectedCharacter ?> || 0));
        document.getElementById('cb_condition_id').value = String(row ? (row.condition_id || 0) : '0');
        document.getElementById('cb_instance_no').value = String(row ? (row.instance_no || 1) : 1);
        document.getElementById('cb_is_active').value = String(row ? (row.is_active || 0) : 1);
        document.getElementById('cb_location').value = row ? (row.location || '') : '';
        document.getElementById('cb_source').value = row ? (row.source || '') : '';
        document.getElementById('cb_acquired_at').value = row ? dtLocal(row.acquired_at || '') : '';
        document.getElementById('cb_healed_at').value = row ? dtLocal(row.healed_at || '') : '';
        document.getElementById('cb_notes').value = row ? (row.notes || '') : '';
        modal.style.display = 'flex';
    }
    function closeAll(){
        modal.style.display = 'none';
        delModal.style.display = 'none';
    }
    document.getElementById('btnNewConditionLink').addEventListener('click', function(){ openForm(<?= (int)$selectedCharacter ?>, null); });
    document.getElementById('btnConditionBridgeCancel').addEventListener('click', closeAll);
    document.getElementById('btnConditionBridgeDeleteCancel').addEventListener('click', closeAll);
    document.querySelectorAll('[data-manage-character]').forEach(function(btn){
        btn.addEventListener('click', function(){ openForm(parseInt(btn.getAttribute('data-manage-character') || '0', 10) || 0, null); });
    });
    document.querySelectorAll('[data-edit]').forEach(function(btn){
        btn.addEventListener('click', function(){ openForm(0, rowById(btn.getAttribute('data-edit'))); });
    });
    document.querySelectorAll('[data-delete]').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.getElementById('cb_delete_id').value = String(parseInt(btn.getAttribute('data-delete') || '0', 10) || 0);
            delModal.style.display = 'flex';
        });
    });
    modal.addEventListener('click', function(e){ if (e.target === modal) closeAll(); });
    delModal.addEventListener('click', function(e){ if (e.target === delModal) closeAll(); });
})();
</script>
<?php admin_panel_close(); ?>
