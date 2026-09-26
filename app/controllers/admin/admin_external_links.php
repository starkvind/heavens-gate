<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../domains/documents/admin_external_links.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ael_valid_url(string $url): bool {
    $url = trim($url);
    if ($url === '') return false;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_external_links';
$CSRF = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : '';

if (!function_exists('admin_external_links_csrf_ok')) {
    function admin_external_links_csrf_ok(): bool {
        $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
        $token = function_exists('hg_admin_extract_csrf_token')
            ? hg_admin_extract_csrf_token($payload)
            : (string)($_POST['csrf'] ?? '');
        if (function_exists('hg_admin_csrf_valid')) {
            return hg_admin_csrf_valid($token, 'csrf_admin_external_links');
        }
        return false;
    }
}

$flash = [];
$hasTable = hg_external_links_admin_table_exists($link, 'fact_external_links');
$hasBridge = hg_external_links_admin_table_exists($link, 'bridge_characters_external_links');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (!admin_external_links_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF inválido. Recarga la página.'];
    } elseif (!$hasTable) {
        $flash[] = ['type' => 'error', 'msg' => 'La tabla fact_external_links no existe todavia.'];
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'create' || $action === 'update') {
            $title = trim((string)($_POST['title'] ?? ''));
            $url = trim((string)($_POST['url'] ?? ''));
            $kind = trim((string)($_POST['kind'] ?? ''));
            $sourceLabel = trim((string)($_POST['source_label'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($title === '') {
                $flash[] = ['type' => 'error', 'msg' => 'El titulo es obligatorio.'];
            } elseif (!ael_valid_url($url)) {
                $flash[] = ['type' => 'error', 'msg' => 'La URL debe ser valida y empezar por http(s).'];
            } else {
                $result = $action === 'create'
                    ? hg_external_links_admin_create($link, $title, $url, $kind, $sourceLabel, $description, $isActive)
                    : hg_external_links_admin_update($link, $id, $title, $url, $kind, $sourceLabel, $description, $isActive);
                $flash[] = [
                    'type' => !empty($result['ok']) ? 'ok' : 'error',
                    'msg' => (string)($result['message'] ?? 'Error al guardar.'),
                ];
            }
        } elseif ($action === 'delete') {
            $result = hg_external_links_admin_delete($link, $id);
            $flash[] = [
                'type' => !empty($result['ok']) ? 'ok' : 'error',
                'msg' => (string)($result['message'] ?? 'Error al borrar.'),
            ];
        }
    }
}

if ($isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
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
        echo json_encode(['ok' => false, 'message' => $errors[0], 'errors' => $errors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $okMsg = !empty($messages) ? $messages[count($messages)-1] : 'Guardado';
    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success(['messages' => $messages], $okMsg);
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'message' => $okMsg, 'data' => ['messages' => $messages]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$rows = [];

if ($hasTable) {
    $rows = hg_external_links_admin_fetch_rows($link, $q, $hasBridge);
}

$actions = "<span class='adm-flex-right-8'><a class='btn' href='/talim?s=admin_character_links'>Gestionar vínculos de personajes</a></span>";
admin_panel_open('Enlaces Externos', $actions);
echo "";
?>

<?php if (!$hasTable): ?>
    <div class="flash"><div class="err">No existe <code>fact_external_links</code> en esta base de datos.</div></div>
    <?php admin_panel_close(); return; ?>
<?php endif; ?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = $m['type']==='ok' ? 'ok' : 'err'; ?>
        <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<fieldset id="renglonArchivos">
    <legend>Crear / Actualizar enlace externo</legend>
    <form method="POST" class="adm-grid-2" id="aelForm">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="action" id="ael_action" value="create">
        <input type="hidden" name="id" id="ael_id" value="0">

        <label>Título
            <input class="inp" type="text" name="title" id="ael_title" maxlength="180" required>
        </label>
        <label>URL
            <input class="inp" type="url" name="url" id="ael_url" maxlength="700" placeholder="https://..." required>
        </label>
        <label>Tipo / Kind
            <input class="inp" type="text" name="kind" id="ael_kind" maxlength="100" placeholder="blog, wiki, noticia...">
        </label>
        <label>Fuente
            <input class="inp" type="text" name="source_label" id="ael_source_label" maxlength="140" placeholder="El Naufragio...">
        </label>
        <label class="adm-col-span-2">Descripción
            <textarea class="ta" name="description" id="ael_description" rows="3"></textarea>
        </label>
        <label><input type="checkbox" name="is_active" id="ael_is_active" checked> Activo</label>
        <div class="adm-flex-right-8">
            <button class="btn btn-blue" type="submit">Guardar</button>
            <button class="btn" type="button" id="ael_reset">Limpiar</button>
        </div>
    </form>
</fieldset>

<fieldset id="renglonArchivos">
    <legend>Listado</legend>
    <form method="GET" class="adm-inline-filters">
        <input type="hidden" name="s" value="admin_external_links">
        <input class="inp" type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar por título, url, tipo o fuente...">
        <button class="btn" type="submit">Buscar</button>
        <a class="btn" href="/talim?s=admin_external_links">Limpiar</a>
    </form>

    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>URL</th>
                    <th>Tipo</th>
                    <th>Fuente</th>
                    <th>Activo</th>
                    <th>PJs</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8">No hay resultados.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td>
                            <strong><?= h($r['title']) ?></strong>
                            <?php if (trim((string)($r['pretty_id'] ?? '')) !== ''): ?>
                                <br><small>slug: <?= h((string)$r['pretty_id']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><a href="<?= h($r['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($r['url']) ?></a></td>
                        <td><?= h($r['kind']) ?></td>
                        <td><?= h($r['source_label']) ?></td>
                        <td><?= ((int)$r['is_active'] === 1 ? 'Si' : 'No') ?></td>
                        <td><?= (int)($r['chars_count'] ?? 0) ?></td>
                        <td class="adm-actions">
                            <button
                                type="button"
                                class="btn btn-small js-ael-edit"
                                data-id="<?= (int)$r['id'] ?>"
                                data-title="<?= h($r['title']) ?>"
                                data-url="<?= h($r['url']) ?>"
                                data-kind="<?= h($r['kind']) ?>"
                                data-source="<?= h($r['source_label']) ?>"
                                data-description="<?= h((string)($r['description'] ?? '')) ?>"
                                data-active="<?= (int)$r['is_active'] ?>"
                            >Editar</button>
                            <form method="POST" onsubmit="return confirm('Eliminar enlace externo?');">
                                <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-red btn-small" type="submit">Borrar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</fieldset>

<script>
(function(){
    var form = document.getElementById('aelForm');
    if (!form) return;
    var fAction = document.getElementById('ael_action');
    var fId = document.getElementById('ael_id');
    var fTitle = document.getElementById('ael_title');
    var fUrl = document.getElementById('ael_url');
    var fKind = document.getElementById('ael_kind');
    var fSource = document.getElementById('ael_source_label');
    var fDescription = document.getElementById('ael_description');
    var fActive = document.getElementById('ael_is_active');
    var reset = document.getElementById('ael_reset');

    function clean(){
        fAction.value = 'create';
        fId.value = '0';
        fTitle.value = '';
        fUrl.value = '';
        fKind.value = '';
        fSource.value = '';
        fDescription.value = '';
        fActive.checked = true;
    }

    if (reset) {
        reset.addEventListener('click', clean);
    }

    document.querySelectorAll('.js-ael-edit').forEach(function(btn){
        btn.addEventListener('click', function(){
            fAction.value = 'update';
            fId.value = this.getAttribute('data-id') || '0';
            fTitle.value = this.getAttribute('data-title') || '';
            fUrl.value = this.getAttribute('data-url') || '';
            fKind.value = this.getAttribute('data-kind') || '';
            fSource.value = this.getAttribute('data-source') || '';
            fDescription.value = this.getAttribute('data-description') || '';
            fActive.checked = String(this.getAttribute('data-active') || '0') === '1';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
})();
</script>

<?php admin_panel_close(); ?>

