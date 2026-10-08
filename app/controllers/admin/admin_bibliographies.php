<?php
// Bibliography Admin — AJAX CRUD; publication/source context is handled in Phase 6C.
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../domains/bibliography/admin.php');

if (!hg_admin_require_db($link)) { return; }
hg_admin_require_session(true);
if (method_exists($link, 'set_charset')) $link->set_charset('utf8mb4');

$csrfKey = 'csrf_admin_bibliographies';
$csrf = hg_admin_ensure_csrf_token($csrfKey);
$ajax = hg_admin_is_ajax_request();
if ($ajax) {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $payload = $method === 'POST' ? hg_admin_read_json_payload() : $_GET;
    if ($method === 'POST' && !$payload && !empty($_POST)) $payload = $_POST;
    $action = trim((string)($payload['action'] ?? ($method === 'GET' ? 'list' : '')));
    if ($method === 'POST' && !hg_admin_csrf_valid(hg_admin_extract_csrf_token($payload), $csrfKey)) {
        hg_admin_json_error('CSRF invalido. Recarga el administrador.', 403, ['csrf' => 'invalid']);
    }
    try {
        if ($method === 'GET' && $action === 'list') {
            hg_admin_json_success(hg_bib_admin_snapshot($link), 'Listado actualizado');
        }
        if ($method === 'GET' && $action === 'references') {
            $id = (int)($payload['id'] ?? 0);
            $table = (string)($payload['table'] ?? '');
            $page = (int)($payload['page'] ?? 1);
            hg_admin_json_success(hg_bib_admin_references($link, $id, $table, $page), 'Referencias');
        }
        if ($method === 'POST' && ($action === 'create' || $action === 'update')) {
            $id = $action === 'create' ? 0 : (int)($payload['id'] ?? 0);
            if ($action === 'update' && $id <= 0) throw new InvalidArgumentException('ID invalido.');
            $saved = hg_bib_admin_save($link, $payload, $id);
            hg_admin_json_success(['id' => $saved], $id === 0 ? 'Bibliografia creada.' : 'Bibliografia actualizada.');
        }
        if ($method === 'POST' && $action === 'delete') {
            hg_bib_admin_delete($link, (int)($payload['id'] ?? 0));
            hg_admin_json_success([], 'Bibliografia eliminada.');
        }
        hg_admin_json_error('Operacion no admitida.', 405);
    } catch (InvalidArgumentException $e) {
        hg_admin_json_error($e->getMessage(), 409);
    } catch (Throwable $e) {
        if (function_exists('hg_runtime_log_error')) {
            hg_runtime_log_error('admin.bibliographies', $e->getMessage());
        }
        hg_admin_json_error('No se pudo completar la operacion. Consulta el registro del servidor.', 500);
    }
    return;
}

include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
admin_panel_open('Bibliografias', '<span class="adm-flex-right-8"><button class="btn btn-green" type="button" id="bibNew">+ Nueva bibliografia</button></span>');
?>
<p class="adm-help-text">Gestiona fuentes bibliograficas, comprueba su uso y consulta los identificadores de cada tabla. Los registros en uso, incluidas las copias historicas, no se pueden borrar. La bibliografia de publicacion Camazotz se asignara en una fase editorial posterior; este administrador no cambia referencias de Dones.</p>
<div class="adm-flex-8-m10">
    <label>Buscar <input class="inp" type="search" id="bibFilter" placeholder="Titulo, editorial, año o identificador..."></label>
    <span id="bibStatus" role="status" aria-live="polite"></span>
</div>
<div class="adm-table-scroll adm-sticky-actions" tabindex="0" aria-label="Catalogo de bibliografias">
<table class="table adm-wide-table" id="bibTable">
    <thead><tr><th>ID</th><th>Bibliografia</th><th>Año</th><th>Editorial</th><th>Orden</th><th>Usos activos</th><th>Historico</th><th>Acciones</th></tr></thead>
    <tbody id="bibRows"><tr><td colspan="8">Cargando bibliografias...</td></tr></tbody>
</table>
</div>
<div class="modal-back" id="bibModal" style="display:none">
    <div class="modal">
        <h3 id="bibModalTitle">Nueva bibliografia</h3>
        <form id="bibForm">
            <input type="hidden" name="id" id="bibId" value="0">
            <div class="modal-body">
                <div class="adm-grid-1-2">
                    <label>Nombre</label>
                    <input class="inp" type="text" id="bibName" maxlength="180" required>
                    <label>Identificador público (slug)</label>
                    <input class="inp" type="text" id="bibSlug" maxlength="190" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="automatico al crear">
                    <label>Año</label>
                    <input class="inp" type="number" id="bibYear" min="0" max="9999" value="2026" required>
                    <label>Editorial</label>
                    <input class="inp" type="text" id="bibPublisher" maxlength="100">
                    <label>Orden</label>
                    <input class="inp" type="number" id="bibOrder" value="900" required>
                    <label>Descripción</label>
                    <textarea class="inp" id="bibDescription" rows="5"></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-green" type="submit" id="bibSave">Guardar</button>
                <button class="btn" type="button" id="bibCancel">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-back" id="bibDeleteModal" style="display:none">
    <div class="modal adm-modal-sm">
        <h3>Confirmar borrado</h3>
        <p id="bibDeleteText"></p>
        <div class="modal-actions">
            <button class="btn" type="button" id="bibDeleteCancel">Cancelar</button>
            <button class="btn btn-red" type="button" id="bibDeleteConfirm">Borrar definitivamente</button>
        </div>
    </div>
</div>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php
$shared = '/assets/js/admin/admin-http.js';
$script = '/assets/js/admin/admin-bibliographies.js';
$sharedVersion = @filemtime($_SERVER['DOCUMENT_ROOT'] . $shared) ?: '1';
$scriptVersion = @filemtime($_SERVER['DOCUMENT_ROOT'] . $script) ?: '1';
?>
<script src="<?= htmlspecialchars($shared, ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars((string)$sharedVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars((string)$scriptVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php admin_panel_close(); ?>
