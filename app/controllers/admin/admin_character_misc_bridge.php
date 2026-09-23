<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../domains/characters/admin_misc_bridge.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_character_misc_bridge';
$CSRF = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : '';

$hasCharacters = acmb_table_exists($link, 'fact_characters');
$hasMisc = acmb_table_exists($link, 'fact_misc_systems');
$hasBridge = acmb_table_exists($link, 'bridge_characters_misc_systems');
$schemaReady = $hasCharacters && $hasMisc && $hasBridge;

$selectedCharacterId = (int)($_GET['character_id'] ?? $_POST['character_id'] ?? 0);
$selectedSystemId = (int)($_GET['system_id'] ?? $_POST['system_id'] ?? 0);
$search = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));

$characterOptions = $hasCharacters ? acmb_character_options($link) : [];
$miscOptions = $hasMisc ? acmb_misc_options($link) : [];
$systemOptions = acmb_system_options($link);

if ($isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('hg_admin_require_session')) hg_admin_require_session(true);
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $csrfToken = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    $csrfOk = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid((string)$csrfToken, $ADMIN_CSRF_SESSION_KEY)
        : false;
    if (!$csrfOk) {
        hg_admin_json_error('CSRF inválido. Recarga la página.', 403, ['csrf' => 'invalid']);
    }
    if (!$schemaReady) {
        hg_admin_json_error('Falta el bridge de misc systems en la base de datos.', 400, ['schema' => 'missing']);
    }

    $action = (string)($payload['action'] ?? $_POST['action'] ?? '');
    if ($action === '') {
        hg_admin_json_error('Acción no válida.', 400, ['action' => 'required']);
    }

    if ($action === 'save') {
        $id = (int)($payload['id'] ?? $_POST['id'] ?? 0);
        $characterId = (int)($payload['character_id'] ?? $_POST['character_id'] ?? 0);
        $miscId = (int)($payload['misc_system_id'] ?? $_POST['misc_system_id'] ?? 0);
        $sortOrder = (int)($payload['sort_order'] ?? $_POST['sort_order'] ?? 0);
        $notes = trim((string)($payload['notes'] ?? $_POST['notes'] ?? ''));
        $isActive = ((string)($payload['is_active'] ?? $_POST['is_active'] ?? '1') === '1') ? 1 : 0;

        if ($characterId <= 0 || !isset($characterOptions[$characterId])) {
            hg_admin_json_error('Selecciona un personaje válido.', 400, ['character_id' => 'invalid']);
        }
        if ($miscId <= 0 || !isset($miscOptions[$miscId])) {
            hg_admin_json_error('Selecciona un misc system válido.', 400, ['misc_system_id' => 'invalid']);
        }

        if ($id > 0 && !acmb_assignment_exists($link, $id)) {
            hg_admin_json_error('La asignación no existe.', 404, ['id' => 'not_found']);
        }

        $duplicateId = acmb_duplicate_assignment($link, $characterId, $miscId, $id);
        if ($duplicateId > 0) {
            hg_admin_json_error('Ese misc system ya está asignado al personaje.', 400, ['duplicate' => $duplicateId]);
        }

        $result = acmb_save_assignment($link, $id, $characterId, $miscId, $sortOrder, $notes, $isActive);
        if (empty($result['ok'])) {
            hg_admin_json_error((string)$result['message'], 500, ['db'=>(string)($result['error'] ?? '')]);
        }
        hg_admin_json_success(['id'=>(int)$result['id']], (string)$result['message']);
    }

    if ($action === 'delete') {
        $id = (int)($payload['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            hg_admin_json_error('ID inválido.', 400, ['id' => 'invalid']);
        }
        $result = acmb_delete_assignment($link, $id);
        if (empty($result['ok'])) {
            hg_admin_json_error((string)$result['message'], 500, ['db'=>(string)($result['error'] ?? '')]);
        }
        hg_admin_json_success(['id'=>$id], (string)$result['message']);
    }

    hg_admin_json_error('Acción no soportada.', 400, ['action' => 'unsupported']);
}

$assignments = $schemaReady ? acmb_fetch_assignments($link, $selectedCharacterId, $selectedSystemId, $search) : [];
$selectedCharacterMeta = ($selectedCharacterId > 0 && isset($characterOptions[$selectedCharacterId])) ? $characterOptions[$selectedCharacterId] : null;

$actions = "<span class='adm-flex-right-8'>"
    . "<a class='btn' href='/talim?s=admin_system_details&tab=misc'>Catálogo misc</a>"
    . "</span>";
admin_panel_open('Misc Systems de Personaje', $actions);
$moduleUrl = '/talim?s=admin_character_misc_bridge';
$moduleAjaxUrl = '/talim?s=admin_character_misc_bridge&ajax=1';
?>
<div id="acmb-container">
<div id="acmb-root">

<?php if (!$schemaReady): ?>
    <div class="flash">
        <div class="err">Falta el esquema requerido en la base de datos.</div>
    </div>
<?php endif; ?>

<fieldset id="renglonArchivos">
    <legend>Filtros</legend>
    <form method="get" class="adm-inline-filters" data-acmb-filter="1">
        <input type="hidden" name="s" value="admin_character_misc_bridge">
        <label>Personaje
            <select class="select" name="character_id">
                <option value="0">Todos</option>
                <?php foreach ($characterOptions as $cid => $meta): ?>
                    <option value="<?= (int)$cid ?>" <?= ($selectedCharacterId === (int)$cid ? 'selected' : '') ?>><?= h((string)$meta['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Sistema
            <select class="select" name="system_id">
                <option value="0">Todos</option>
                <?php foreach ($systemOptions as $sid => $name): ?>
                    <option value="<?= (int)$sid ?>" <?= ($selectedSystemId === (int)$sid ? 'selected' : '') ?>><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Buscar
            <input class="inp" type="text" name="q" value="<?= h($search) ?>" placeholder="Personaje, misc, kind...">
        </label>
        <button class="btn" type="submit">Filtrar</button>
        <a class="btn" href="/talim?s=admin_character_misc_bridge">Limpiar</a>
    </form>
    <?php if ($selectedCharacterMeta): ?>
        <p class="small-note">Personaje seleccionado: <strong><?= h((string)$selectedCharacterMeta['label']) ?></strong></p>
    <?php endif; ?>
</fieldset>

<fieldset id="renglonArchivos">
    <legend>Asignar misc system</legend>
    <?php if (!$schemaReady): ?>
        <p>No disponible mientras falte el bridge en la base de datos.</p>
    <?php else: ?>
        <form method="post" class="adm-grid-2">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="0">
            <label class="adm-col-span-2">Personaje
                <select class="select" name="character_id" required>
                    <option value="">-- Selecciona personaje --</option>
                    <?php foreach ($characterOptions as $cid => $meta): ?>
                        <option value="<?= (int)$cid ?>" <?= ($selectedCharacterId === (int)$cid ? 'selected' : '') ?>><?= h((string)$meta['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="adm-col-span-2">Misc system
                <select class="select" name="misc_system_id" required>
                    <option value="">-- Selecciona misc system --</option>
                    <?php foreach ($miscOptions as $mid => $meta): ?>
                        <option value="<?= (int)$mid ?>"><?= h((string)$meta['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Orden
                <input class="inp" type="number" name="sort_order" value="0">
            </label>
            <label>Estado
                <select class="select" name="is_active">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo / histórico</option>
                </select>
            </label>
            <label class="adm-col-span-2">Notas
                <input class="inp" type="text" name="notes" maxlength="500" placeholder="Observaciones opcionales">
            </label>
            <div class="adm-flex-right-8 adm-col-span-2">
                <button class="btn btn-green" type="submit">Asignar misc system</button>
            </div>
        </form>
    <?php endif; ?>
</fieldset>

<fieldset id="renglonArchivos">
    <legend>Asignaciones actuales</legend>
    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Personaje</th>
                    <th>Sistema</th>
                    <th>Kind</th>
                    <th>Misc system</th>
                    <th>Edición</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignments)): ?>
                    <tr><td colspan="7">No hay asignaciones registradas.</td></tr>
                <?php else: ?>
                    <?php foreach ($assignments as $row): ?>
                        <?php
                            $rowId = (int)($row['id'] ?? 0);
                            $charId = (int)($row['character_id'] ?? 0);
                            $miscId = (int)($row['misc_system_id'] ?? 0);
                        ?>
                        <tr>
                            <td><?= $rowId ?></td>
                            <td>
                                <a href="<?= h(pretty_url($link, 'fact_characters', '/characters', $charId)) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= h((string)($row['character_name'] ?? '')) ?>
                                </a>
                            </td>
                            <td><?= h((string)($row['character_system_name'] ?: $row['misc_system_name'])) ?></td>
                            <td><?= h((string)($row['misc_kind'] ?? '')) ?></td>
                            <td>
                                <a href="<?= h(pretty_url($link, 'fact_misc_systems', '/systems/misc', $miscId)) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= h((string)($row['misc_name'] ?? '')) ?>
                                </a>
                            </td>
                            <td>
                                <form method="post" class="adm-inline-filters">
                                    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="id" value="<?= $rowId ?>">
                                    <input type="hidden" name="character_id" value="<?= $charId ?>">
                                    <input type="hidden" name="misc_system_id" value="<?= $miscId ?>">
                                    <input class="inp adm-w-90" type="number" name="sort_order" value="<?= (int)($row['sort_order'] ?? 0) ?>">
                                    <select class="select" name="is_active">
                                        <option value="1" <?= ((int)($row['is_active'] ?? 0) === 1 ? 'selected' : '') ?>>Activo</option>
                                        <option value="0" <?= ((int)($row['is_active'] ?? 0) !== 1 ? 'selected' : '') ?>>Inactivo</option>
                                    </select>
                                    <input class="inp" type="text" name="notes" maxlength="500" value="<?= h((string)($row['notes'] ?? '')) ?>" placeholder="Notas">
                                    <button class="btn btn-small" type="submit">Guardar</button>
                                </form>
                            </td>
                            <td class="adm-actions">
                                <form method="post" onsubmit="return confirm('¿Eliminar esta asignación?');">
                                    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $rowId ?>">
                                    <button class="btn btn-red btn-small" type="submit">Quitar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</fieldset>

</div>
</div>

<script src="/assets/js/admin/admin-http.js"></script>
<script>
(function(){
    var container = document.getElementById('acmb-container');
    if (!container) return;
    window.ADMIN_CSRF_TOKEN = <?= json_encode((string)$CSRF, JSON_UNESCAPED_UNICODE) ?>;
    var moduleUrl = <?= json_encode($moduleUrl, JSON_UNESCAPED_UNICODE) ?>;
    var ajaxUrl = <?= json_encode($moduleAjaxUrl, JSON_UNESCAPED_UNICODE) ?>;
    var filterTimer = null;

    function buildFilterQuery(){
        var form = container.querySelector('form[data-acmb-filter]');
        if (!form) return '';
        var fd = new FormData(form);
        var params = new URLSearchParams();
        fd.forEach(function(value, key){
            var v = String(value == null ? '' : value).trim();
            if (key === 's') return;
            if (v === '' || v === '0') return;
            params.set(key, v);
        });
        return params.toString();
    }

    async function reloadModule(updateUrl){
        var query = buildFilterQuery();
        var url = moduleUrl + (query ? ('&' + query) : '');
        var res = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        var html = await res.text();
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var nextRoot = doc.querySelector('#acmb-root');
        if (!nextRoot) throw new Error('No se pudo recargar el módulo.');
        container.innerHTML = nextRoot.outerHTML;
        if (updateUrl) {
            window.history.replaceState({}, '', url);
        }
        bindLiveFilters();
    }

    function notifyError(err){
        if (window.HGAdminHttp && window.HGAdminHttp.notify) {
            window.HGAdminHttp.notify(window.HGAdminHttp.errorMessage(err), 'err', 3600);
        }
    }

    function bindLiveFilters(){
        var form = container.querySelector('form[data-acmb-filter]');
        if (!form) return;
        var qInput = form.querySelector('input[name=\"q\"]');
        if (qInput && !qInput.dataset.bound) {
            qInput.dataset.bound = '1';
            qInput.addEventListener('input', function(){
                if (filterTimer) clearTimeout(filterTimer);
                filterTimer = setTimeout(function(){
                    reloadModule(true).catch(notifyError);
                }, 300);
            });
        }
        form.querySelectorAll('select').forEach(function(sel){
            if (sel.dataset.bound) return;
            sel.dataset.bound = '1';
            sel.addEventListener('change', function(){
                reloadModule(true).catch(notifyError);
            });
        });
    }

    container.addEventListener('submit', async function(ev){
        var form = ev.target;
        if (!form || form.tagName !== 'FORM') return;
        var method = String(form.getAttribute('method') || 'GET').toUpperCase();
        if (method === 'GET') {
            ev.preventDefault();
            reloadModule(true).catch(notifyError);
            return;
        }
        if (method !== 'POST' || !window.HGAdminHttp || typeof window.HGAdminHttp.request !== 'function') {
            return;
        }
        ev.preventDefault();
        try {
            var fd = new FormData(form);
            var payload = await HGAdminHttp.request(ajaxUrl, {
                method: 'POST',
                body: fd,
                loadingEl: form
            });
            if (window.HGAdminHttp && window.HGAdminHttp.notify) {
                window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok');
            }
            await reloadModule(true);
        } catch (err) {
            notifyError(err);
        }
    });

    bindLiveFilters();
})();
</script>

<?php admin_panel_close(); ?>
