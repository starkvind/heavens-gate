<?php
if (!isset($link) || !$link) {
    die("Error de conexion a la base de datos.");
}
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);
$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_timelines';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function timelines_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $t = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($t, 'csrf_admin_timelines');
    }
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_timelines']) && hash_equals($_SESSION['csrf_admin_timelines'], $t);
}

$flash = [];
$createdEvent = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (!timelines_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $descripcion = (string)($_POST['descripcion'] ?? '');
        $fecha = (string)($_POST['fecha'] ?? '');
        $tipo = (string)($_POST['tipo'] ?? 'evento');
        $ubicacion = (string)($_POST['ubicacion'] ?? '');
        $fuente = (string)($_POST['fuente'] ?? '');

        if ($titulo === '') {
            $flash[] = ['type' => 'error', 'msg' => 'El titulo es obligatorio.'];
        } else {
            $stmt = $link->prepare(
                "INSERT INTO fact_timeline_events (event_date, title, description, kind, location, source) VALUES (?, ?, ?, ?, ?, ?)"
            );
            if (!$stmt) {
                $flash[] = ['type' => 'error', 'msg' => 'Error al preparar INSERT: '.$link->error];
            } else {
                $stmt->bind_param('ssssss', $fecha, $titulo, $descripcion, $tipo, $ubicacion, $fuente);
                if ($stmt->execute()) {
                    $eventoId = (int)$stmt->insert_id;
                    hg_update_pretty_id_if_exists($link, 'fact_timeline_events', $eventoId, $titulo);
                    $createdEvent = [
                        'id' => $eventoId,
                        'event_date' => $fecha,
                        'title' => $titulo,
                    ];
                    $flash[] = ['type' => 'ok', 'msg' => 'Evento anadido correctamente.'];
                } else {
                    $flash[] = ['type' => 'error', 'msg' => 'Error al crear: '.$stmt->error];
                }
                $stmt->close();
            }
        }
    }
}

$chronicles = [];
$res = $link->query("SELECT id, name FROM dim_chronicles ORDER BY name ASC");
while ($res && ($row = $res->fetch_assoc())) { $chronicles[] = $row; }
if ($res) { $res->close(); }

$recent = [];
$res = $link->query("SELECT id, event_date, title FROM fact_timeline_events ORDER BY event_date DESC LIMIT 20");
while ($res && ($row = $res->fetch_assoc())) { $recent[] = $row; }
if ($res) { $res->close(); }

$ajaxSave = $isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event']);
if ($ajaxSave) {
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
        hg_admin_json_success(['event' => $createdEvent, 'messages' => $messages], $okMsg);
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'message' => $okMsg,
        'data' => ['event' => $createdEvent, 'messages' => $messages],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$actions = '<span class="adm-flex-right-8"><span class="adm-help-text">Altas por AJAX sin recarga</span></span>';
admin_panel_open('Linea Temporal', $actions);
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = $m['type']==='ok' ? 'ok' : (($m['type'] ?? '')==='error' ? 'err' : 'info'); ?>
        <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" id="timelineForm">
    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
    <input type="hidden" name="save_event" value="1">
    <fieldset class="add-event-form adm-timeline-fieldset" id="renglonArchivos">
        <div class="form-row">
            <label for="titulo">Titulo:</label>
            <input type="text" name="titulo" id="titulo" required>
        </div>
        <div class="form-row">
            <label for="fecha">Fecha in-game:</label>
            <input type="date" name="fecha" id="fecha">
        </div>
        <div class="form-row">
            <label for="descripcion">Descripcion:</label>
        </div>
        <textarea name="descripcion" id="descripcion" rows="6" cols="80" class="adm-timeline-desc"></textarea>
        <div class="form-row">
            <label for="tipo">Tipo:</label>
            <select name="tipo" id="tipo">
                <option value="evento">Evento</option>
                <option value="romance">Romance</option>
                <option value="fundacion">Fundacion</option>
                <option value="alianza">Alianza</option>
                <option value="reclutamiento">Reclutamiento</option>
                <option value="descubrimiento">Descubrimiento</option>
                <option value="enemistad">Enemistad</option>
                <option value="batalla">Batalla</option>
                <option value="traicion">Traicion</option>
                <option value="catastrofe">Catastrofe</option>
                <option value="nacimiento">Nacimiento</option>
                <option value="muerte">Muerte</option>
                <option value="otros">Otros</option>
            </select>
        </div>
        <div class="form-row">
            <label for="ubicacion">Ubicacion:</label>
            <input type="text" name="ubicacion" id="ubicacion">
        </div>
        <div class="form-row">
            <label for="fuente">Fuente:</label>
            <input type="text" name="fuente" id="fuente" placeholder="Nombre de documento o archivo fuente">
        </div>
        <div class="form-row">
            <label for="timeline">Linea temporal:</label>
            <select name="timeline" id="timeline">
                <?php foreach ($chronicles as $row): ?>
                <option value="<?= h((string)$row['name']) ?>"><?= h((string)$row['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </fieldset>
    <div class="adm-text-center"><input class="boton2" type="submit" value="Anadir evento"></div>
</form>

<br>
<hr>
<br>

<h3>Ultimos eventos registrados</h3>
<ul id="timelineRecentList">
<?php foreach ($recent as $row): ?>
    <li><strong><?= h((string)$row['event_date']) ?></strong> - <?= h((string)$row['title']) ?></li>
<?php endforeach; ?>
</ul>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
(function(){
    const form = document.getElementById('timelineForm');
    const list = document.getElementById('timelineRecentList');
    if (!form || !list) return;

    form.addEventListener('submit', function(ev){
        ev.preventDefault();
        const fd = new FormData(form);
        fd.set('ajax', '1');
        const url = new URL(window.location.href);
        url.searchParams.set('s', 'admin_timelines');
        url.searchParams.set('ajax', '1');
        url.searchParams.set('_ts', Date.now());

        window.HGAdminHttp.request(url.toString(), { method: 'POST', body: fd, loadingEl: form }).then(function(payload){
            const event = payload && payload.data ? payload.data.event : null;
            if (event && event.title) {
                const li = document.createElement('li');
                li.innerHTML = '<strong>' + String(event.event_date || '') + '</strong> - ' + String(event.title || '');
                list.insertBefore(li, list.firstChild);
                while (list.children.length > 20) list.removeChild(list.lastChild);
            }
            form.reset();
            if (window.HGAdminHttp && window.HGAdminHttp.notify) {
                window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok');
            }
        }).catch(function(err){
            const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error');
            alert(msg);
        });
    });
})();
</script>

<?php admin_panel_close(); ?>
