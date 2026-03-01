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
$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_bso';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function bso_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $t = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($t, 'csrf_admin_bso');
    }
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_bso']) && hash_equals($_SESSION['csrf_admin_bso'], $t);
}

$flash = [];
$created = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_tema'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (!bso_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $artista = trim((string)($_POST['artista'] ?? ''));
        $youtube = trim((string)($_POST['youtube_url'] ?? ''));
        $tituloHg = trim((string)($_POST['context_title'] ?? ''));

        if ($titulo === '') {
            $flash[] = ['type' => 'error', 'msg' => 'El titulo es obligatorio.'];
        } elseif ($youtube === '') {
            $flash[] = ['type' => 'error', 'msg' => 'El enlace de YouTube es obligatorio.'];
        } else {
            $stmt = $link->prepare("INSERT INTO dim_soundtracks (title, artist, youtube_url, context_title) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                $flash[] = ['type' => 'error', 'msg' => 'Error al preparar INSERT: '.$link->error];
            } else {
                $stmt->bind_param("ssss", $titulo, $artista, $youtube, $tituloHg);
                if ($stmt->execute()) {
                    $newId = (int)$link->insert_id;
                    hg_update_pretty_id_if_exists($link, 'dim_soundtracks', $newId, $titulo);
                    $created = [
                        'id' => $newId,
                        'title' => $titulo,
                        'artist' => $artista,
                        'context_title' => $tituloHg,
                        'youtube_url' => $youtube,
                        'added_at' => date('Y-m-d H:i:s'),
                    ];
                    $flash[] = ['type' => 'ok', 'msg' => 'Tema anadido correctamente.'];
                } else {
                    $flash[] = ['type' => 'error', 'msg' => 'Error al crear: '.$stmt->error];
                }
                $stmt->close();
            }
        }
    }
}

$temas = [];
$rs = $link->query("SELECT * FROM dim_soundtracks ORDER BY added_at DESC");
while ($rs && ($row = $rs->fetch_assoc())) { $temas[] = $row; }
if ($rs) { $rs->close(); }

$ajaxSave = $isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_tema']);
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
        hg_admin_json_success(['row' => $created, 'rows' => $temas, 'messages' => $messages], $okMsg);
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'message' => $okMsg,
        'data' => ['row' => $created, 'rows' => $temas, 'messages' => $messages],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$actions = '<span class="adm-flex-right-8"><a class="btn" href="/talim?s=admin_bso_link">Vincular temas</a></span>';
admin_panel_open('Banda Sonora', $actions);
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = $m['type']==='ok' ? 'ok' : (($m['type'] ?? '')==='error' ? 'err' : 'info'); ?>
        <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" id="bsoForm" class="adm-mb-30">
    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
    <input type="hidden" name="nuevo_tema" value="1">
    <fieldset id="renglonArchivos" class="adm-bso-form-fieldset">
        <legend id="archivosLegend">Anadir nuevo tema</legend>
        <label>Titulo:</label><br>
        <input type="text" name="titulo" required class="adm-w-full"><br><br>

        <label>Artista:</label><br>
        <input type="text" name="artista" class="adm-w-full"><br><br>

        <label>Enlace YouTube (ID o URL completa):</label><br>
        <input type="text" name="youtube_url" required class="adm-w-full"><br><br>

        <label>Nombre simbolico (ej. Tema de Aranzazu):</label><br>
        <input type="text" name="context_title" class="adm-w-full"><br><br>

        <button class="boton2" type="submit">Guardar tema</button>
    </fieldset>
</form>

<h3>Temas existentes</h3>
<table class="bso_table" id="bsoTable">
    <thead>
    <tr>
        <th>ID</th>
        <th>Titulo</th>
        <th>Artista</th>
        <th>Titulo en Heaven's Gate</th>
        <th>YouTube</th>
        <th>Fecha</th>
    </tr>
    </thead>
    <tbody id="bsoTbody">
    <?php foreach ($temas as $row): ?>
    <tr>
        <td><?= (int)$row['id'] ?></td>
        <td><?= h((string)$row['title']) ?></td>
        <td><?= h((string)$row['artist']) ?></td>
        <td><?= h((string)$row['context_title']) ?></td>
        <td>
            <?php $yt = trim((string)($row['youtube_url'] ?? '')); ?>
            <?php if ($yt === ''): ?>
                -
            <?php elseif (strpos($yt, 'http') === 0): ?>
                <a href="<?= h($yt) ?>" target="_blank" rel="noopener noreferrer">Link</a>
            <?php else: ?>
                <a href="https://www.youtube.com/watch?v=<?= h($yt) ?>" target="_blank" rel="noopener noreferrer">Video</a>
            <?php endif; ?>
        </td>
        <td><?= h((string)$row['added_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
(function(){
    const form = document.getElementById('bsoForm');
    const tbody = document.getElementById('bsoTbody');
    if (!form || !tbody) return;

    function esc(s){
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
    function youtubeCell(url){
        const u = String(url || '').trim();
        if (!u) return '-';
        if (u.indexOf('http') === 0) {
            return '<a href=\"' + esc(u) + '\" target=\"_blank\" rel=\"noopener noreferrer\">Link</a>';
        }
        return '<a href=\"https://www.youtube.com/watch?v=' + esc(u) + '\" target=\"_blank\" rel=\"noopener noreferrer\">Video</a>';
    }
    function prependRow(row){
        if (!row) return;
        const html = '<tr>'
            + '<td>' + (parseInt(row.id || 0, 10) || 0) + '</td>'
            + '<td>' + esc(row.title || '') + '</td>'
            + '<td>' + esc(row.artist || '') + '</td>'
            + '<td>' + esc(row.context_title || '') + '</td>'
            + '<td>' + youtubeCell(row.youtube_url || '') + '</td>'
            + '<td>' + esc(row.added_at || '') + '</td>'
            + '</tr>';
        tbody.insertAdjacentHTML('afterbegin', html);
    }

    form.addEventListener('submit', function(ev){
        ev.preventDefault();
        const fd = new FormData(form);
        fd.set('ajax', '1');
        const url = new URL(window.location.href);
        url.searchParams.set('s', 'admin_bso');
        url.searchParams.set('ajax', '1');
        url.searchParams.set('_ts', Date.now());
        window.HGAdminHttp.request(url.toString(), { method: 'POST', body: fd, loadingEl: form }).then(function(payload){
            const row = payload && payload.data ? payload.data.row : null;
            prependRow(row);
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
