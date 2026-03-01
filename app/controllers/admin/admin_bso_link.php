<?php
if (!isset($link) || !$link) {
    die("Error de conexion a la base de datos.");
}
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);
$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_bso_link';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function bso_link_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $t = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($t, 'csrf_admin_bso_link');
    }
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_bso_link']) && hash_equals($_SESSION['csrf_admin_bso_link'], $t);
}

$flash = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vincular_tema'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (!bso_link_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $idBso = (int)($_POST['id_bso'] ?? 0);
        $tipo = (string)($_POST['tipo'] ?? '');
        $idObj = (int)($_POST['id_objeto'] ?? 0);

        if ($idBso <= 0 || $idObj <= 0 || !in_array($tipo, ['personaje', 'temporada', 'episodio'], true)) {
            $flash[] = ['type' => 'error', 'msg' => 'Datos de vinculacion invalidos.'];
        } else {
            $stmt = $link->prepare("INSERT INTO bridge_soundtrack_links (soundtrack_id, object_type, object_id) VALUES (?, ?, ?)");
            if (!$stmt) {
                $flash[] = ['type' => 'error', 'msg' => 'Error al preparar INSERT: '.$link->error];
            } else {
                $stmt->bind_param("isi", $idBso, $tipo, $idObj);
                if ($stmt->execute()) {
                    $flash[] = ['type' => 'ok', 'msg' => 'Relacion anadida correctamente.'];
                } else {
                    $flash[] = ['type' => 'error', 'msg' => 'Error al vincular: '.$stmt->error];
                }
                $stmt->close();
            }
        }
    }
}

$temas = [];
$rs = $link->query("SELECT id, context_title FROM dim_soundtracks ORDER BY added_at DESC");
while ($rs && ($row = $rs->fetch_assoc())) { $temas[] = $row; }
if ($rs) { $rs->close(); }

$personajes = [];
$rs = $link->query("SELECT id, name FROM fact_characters WHERE chronicle_id NOT IN (2, 7) ORDER BY name");
while ($rs && ($row = $rs->fetch_assoc())) { $personajes[] = $row; }
if ($rs) { $rs->close(); }

$temporadas = [];
$rs = $link->query("SELECT id, name FROM dim_seasons ORDER BY season_number");
while ($rs && ($row = $rs->fetch_assoc())) { $temporadas[] = $row; }
if ($rs) { $rs->close(); }

$episodios = [];
$rs = $link->query("SELECT id, name FROM dim_chapters ORDER BY played_date DESC");
while ($rs && ($row = $rs->fetch_assoc())) { $episodios[] = $row; }
if ($rs) { $rs->close(); }

$ajaxSave = $isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vincular_tema']);
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

$actions = '<span class="adm-flex-right-8"><a class="btn" href="/talim?s=admin_bso">Gestionar banda sonora</a></span>';
admin_panel_open('Vincular Banda Sonora', $actions);
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = $m['type']==='ok' ? 'ok' : (($m['type'] ?? '')==='error' ? 'err' : 'info'); ?>
        <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" id="bsoLinkForm">
    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
    <input type="hidden" name="vincular_tema" value="1">
    <input type="hidden" name="id_objeto" id="id_objeto_final" value="">
    <fieldset id="renglonArchivos" class="adm-bso-form-fieldset">
        <legend>Nueva asociacion</legend>

        <label>Tema musical:</label><br>
        <select name="id_bso" required class="adm-w-full">
            <option value="">Seleccionar tema</option>
            <?php foreach ($temas as $row): ?>
            <option value="<?= (int)$row['id'] ?>"><?= h((string)$row['context_title']) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Tipo de vinculo:</label><br>
        <select name="tipo" id="tipoSelector" required class="adm-w-full">
            <option value="">Seleccionar tipo</option>
            <option value="personaje">Personaje</option>
            <option value="temporada">Temporada</option>
            <option value="episodio">Episodio</option>
        </select><br><br>

        <div id="selectorPersonaje" class="adm-hidden">
            <label>Personaje:</label><br>
            <select id="select_personaje" class="adm-w-full">
                <?php foreach ($personajes as $row): ?>
                <option value="<?= (int)$row['id'] ?>"><?= h((string)$row['name']) ?></option>
                <?php endforeach; ?>
            </select><br><br>
        </div>

        <div id="selectorTemporada" class="adm-hidden">
            <label>Temporada:</label><br>
            <select id="select_temporada" class="adm-w-full">
                <?php foreach ($temporadas as $row): ?>
                <option value="<?= (int)$row['id'] ?>"><?= h((string)$row['name']) ?></option>
                <?php endforeach; ?>
            </select><br><br>
        </div>

        <div id="selectorEpisodio" class="adm-hidden">
            <label>Episodio:</label><br>
            <select id="select_episodio" class="adm-w-full">
                <?php foreach ($episodios as $row): ?>
                <option value="<?= (int)$row['id'] ?>"><?= h((string)$row['name']) ?></option>
                <?php endforeach; ?>
            </select><br><br>
        </div>

        <button class="boton2" type="submit">Guardar vinculo</button>
    </fieldset>
</form>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
function mostrarSelector() {
    document.getElementById('selectorPersonaje').style.display = 'none';
    document.getElementById('selectorTemporada').style.display = 'none';
    document.getElementById('selectorEpisodio').style.display = 'none';
    document.getElementById('id_objeto_final').value = '';

    const tipo = document.getElementById('tipoSelector').value;
    if (tipo === 'personaje') {
        document.getElementById('selectorPersonaje').style.display = 'block';
        document.getElementById('id_objeto_final').value = document.getElementById('select_personaje').value;
    } else if (tipo === 'temporada') {
        document.getElementById('selectorTemporada').style.display = 'block';
        document.getElementById('id_objeto_final').value = document.getElementById('select_temporada').value;
    } else if (tipo === 'episodio') {
        document.getElementById('selectorEpisodio').style.display = 'block';
        document.getElementById('id_objeto_final').value = document.getElementById('select_episodio').value;
    }
}

(function(){
    const form = document.getElementById('bsoLinkForm');
    const tipo = document.getElementById('tipoSelector');
    const p = document.getElementById('select_personaje');
    const t = document.getElementById('select_temporada');
    const e = document.getElementById('select_episodio');
    const hidden = document.getElementById('id_objeto_final');
    if (!form || !tipo || !hidden) return;

    tipo.addEventListener('change', mostrarSelector);
    if (p) p.addEventListener('change', function(){ hidden.value = this.value; });
    if (t) t.addEventListener('change', function(){ hidden.value = this.value; });
    if (e) e.addEventListener('change', function(){ hidden.value = this.value; });

    form.addEventListener('submit', function(ev){
        ev.preventDefault();
        if (!hidden.value) {
            alert('Selecciona un objeto para el vinculo.');
            return;
        }
        const fd = new FormData(form);
        fd.set('ajax', '1');
        const url = new URL(window.location.href);
        url.searchParams.set('s', 'admin_bso_link');
        url.searchParams.set('ajax', '1');
        url.searchParams.set('_ts', Date.now());
        window.HGAdminHttp.request(url.toString(), { method: 'POST', body: fd, loadingEl: form }).then(function(payload){
            form.reset();
            hidden.value = '';
            mostrarSelector();
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
