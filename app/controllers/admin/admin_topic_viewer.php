<?php
if (!isset($link) || !$link) {
    die("Error de conexion a la base de datos.");
}
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (method_exists($link, 'set_charset')) {
    $link->set_charset('utf8mb4');
} else {
    mysqli_set_charset($link, 'utf8mb4');
}

include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function topic_viewer_table_exists(mysqli $link): bool
{
    $rs = $link->query("SHOW TABLES LIKE 'fact_tools_topic_viewer'");
    return $rs && $rs->num_rows > 0;
}

$actions = '<span class="adm-flex-right-8">'
    . '<a class="btn" href="/app/tools/topic_viewer_setup_20260318.php" target="_blank">Ejecutar setup</a>'
    . '<label class="adm-text-left">Filtro rapido '
    . '<input class="inp" type="text" id="quickFilterTopicViewer" placeholder="En esta pagina..."></label>'
    . '</span>';
admin_panel_open('Temas de visor de foro', $actions);

if (!topic_viewer_table_exists($link)) {
    echo "<p class='adm-admin-error'>Falta la tabla <code>fact_tools_topic_viewer</code>. Ejecuta <code>app/tools/topic_viewer_setup_20260318.php</code>.</p>";
    admin_panel_close();
    return;
}

$csrfKey = 'csrf_admin_topic_viewer';
$csrf = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($csrfKey)
    : (empty($_SESSION[$csrfKey]) ? ($_SESSION[$csrfKey] = bin2hex(random_bytes(16))) : $_SESSION[$csrfKey]);

$flash = [];
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    $validCsrf = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid((string)$token, $csrfKey)
        : (is_string($token) && $token !== '' && isset($_SESSION[$csrfKey]) && hash_equals($_SESSION[$csrfKey], $token));

    if (!$validCsrf) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $action = (string)$_POST['crud_action'];

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'ID invalido para borrar.'];
            } else {
                $st = $link->prepare("DELETE FROM fact_tools_topic_viewer WHERE id = ? LIMIT 1");
                if (!$st) {
                    $flash[] = ['type' => 'error', 'msg' => 'Error al preparar DELETE: ' . $link->error];
                } else {
                    $st->bind_param("i", $id);
                    if ($st->execute()) {
                        $flash[] = ['type' => 'ok', 'msg' => 'Tema eliminado.'];
                    } else {
                        $flash[] = ['type' => 'error', 'msg' => 'Error al borrar: ' . $st->error];
                    }
                    $st->close();
                }
            }
        }

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $topicName = trim((string)($_POST['topic_name'] ?? ''));
            $topicId = (int)($_POST['topic_id'] ?? 0);
            $topicUrl = trim((string)($_POST['topic_url'] ?? ''));
            $topicDescription = trim((string)($_POST['topic_description'] ?? ''));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $isActive = ((string)($_POST['is_active'] ?? '1') === '1') ? 1 : 0;

            if ($topicName === '' || $topicId <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'Nombre y topic_id son obligatorios.'];
                $editId = $id;
            } else {
                if ($id > 0) {
                    $st = $link->prepare("UPDATE fact_tools_topic_viewer
                        SET topic_name = ?, topic_id = ?, topic_url = ?, topic_description = ?, sort_order = ?, is_active = ?
                        WHERE id = ? LIMIT 1");
                    if (!$st) {
                        $flash[] = ['type' => 'error', 'msg' => 'Error al preparar UPDATE: ' . $link->error];
                    } else {
                        $st->bind_param("sissiii", $topicName, $topicId, $topicUrl, $topicDescription, $sortOrder, $isActive, $id);
                        if ($st->execute()) {
                            $flash[] = ['type' => 'ok', 'msg' => 'Tema actualizado.'];
                            $editId = 0;
                        } else {
                            $code = (int)$st->errno;
                            $msg = ($code === 1062) ? 'Ya existe un tema con ese topic_id.' : ('Error al actualizar: ' . $st->error);
                            $flash[] = ['type' => 'error', 'msg' => $msg];
                            $editId = $id;
                        }
                        $st->close();
                    }
                } else {
                    $st = $link->prepare("INSERT INTO fact_tools_topic_viewer
                        (topic_name, topic_id, topic_url, topic_description, sort_order, is_active, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    if (!$st) {
                        $flash[] = ['type' => 'error', 'msg' => 'Error al preparar INSERT: ' . $link->error];
                    } else {
                        $st->bind_param("sissii", $topicName, $topicId, $topicUrl, $topicDescription, $sortOrder, $isActive);
                        if ($st->execute()) {
                            $flash[] = ['type' => 'ok', 'msg' => 'Tema creado.'];
                        } else {
                            $code = (int)$st->errno;
                            $msg = ($code === 1062) ? 'Ya existe un tema con ese topic_id.' : ('Error al crear: ' . $st->error);
                            $flash[] = ['type' => 'error', 'msg' => $msg];
                            $editId = 0;
                        }
                        $st->close();
                    }
                }
            }
        }
    }
}

$editRow = [
    'id' => 0,
    'topic_name' => '',
    'topic_id' => '',
    'topic_url' => '',
    'topic_description' => '',
    'sort_order' => 0,
    'is_active' => 1,
];
if ($editId > 0) {
    $st = $link->prepare("SELECT * FROM fact_tools_topic_viewer WHERE id = ? LIMIT 1");
    if ($st) {
        $st->bind_param("i", $editId);
        $st->execute();
        $rs = $st->get_result();
        if ($row = $rs->fetch_assoc()) {
            $editRow = $row;
        }
        $st->close();
    }
}

$rows = [];
$rs = $link->query("SELECT id, topic_name, topic_id, topic_url, topic_description, sort_order, is_active, created_at, updated_at
                    FROM fact_tools_topic_viewer
                    ORDER BY is_active DESC, sort_order ASC, topic_name ASC, id DESC");
if ($rs) {
    while ($r = $rs->fetch_assoc()) {
        $rows[] = $r;
    }
    $rs->close();
}
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = ($m['type'] ?? '') === 'ok' ? 'ok' : 'err'; ?>
        <div class="<?= $cl ?>"><?= h($m['msg'] ?? '') ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h3><?= ((int)$editRow['id'] > 0) ? 'Editar tema' : 'Nuevo tema' ?></h3>
<form method="post" class="adm-grid-1-2">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="crud_action" value="save">
        <input type="hidden" name="id" value="<?= (int)($editRow['id'] ?? 0) ?>">

        <label>Nombre del tema
            <input class="inp" type="text" name="topic_name" maxlength="180" required value="<?= h($editRow['topic_name'] ?? '') ?>">
        </label>

        <label>topic_id
            <input class="inp" type="number" min="1" name="topic_id" required value="<?= h((string)($editRow['topic_id'] ?? '')) ?>">
        </label>

        <label>URL (opcional)
            <input class="inp" type="text" name="topic_url" maxlength="255" value="<?= h($editRow['topic_url'] ?? '') ?>">
        </label>

        <label>Orden
            <input class="inp" type="number" min="0" name="sort_order" value="<?= h((string)($editRow['sort_order'] ?? 0)) ?>">
        </label>

        <label class="field-full">Descripcion (opcional)
            <textarea class="ta" name="topic_description" rows="3"><?= h($editRow['topic_description'] ?? '') ?></textarea>
        </label>

        <label>
            Estado
            <select class="select" name="is_active">
                <option value="1" <?= ((int)($editRow['is_active'] ?? 1) === 1) ? 'selected' : '' ?>>Activo</option>
                <option value="0" <?= ((int)($editRow['is_active'] ?? 1) === 0) ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </label>

        <div class="field-full adm-flex-right-8">
            <?php if ((int)($editRow['id'] ?? 0) > 0): ?>
                <a class="btn" href="/talim?s=admin_topic_viewer">Cancelar edicion</a>
            <?php endif; ?>
            <button class="btn btn-green" type="submit">Guardar</button>
        </div>
</form>

<table class="table" id="topicViewerTable">
    <thead>
        <tr>
            <th class="adm-w-60">ID</th>
            <th>Nombre</th>
            <th class="adm-w-80">topic_id</th>
            <th>URL</th>
            <th class="adm-w-80">Orden</th>
            <th class="adm-w-80">Estado</th>
            <th class="adm-w-160">Fechas</th>
            <th class="adm-w-160">Acciones</th>
        </tr>
    </thead>
    <tbody id="topicViewerBody">
    <?php foreach ($rows as $r): ?>
        <?php
            $search = trim((string)$r['topic_name'] . ' ' . (string)$r['topic_id'] . ' ' . (string)$r['topic_url']);
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
            else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><?= (int)$r['id'] ?></td>
            <td>
                <strong><?= h($r['topic_name']) ?></strong>
                <?php if (trim((string)$r['topic_description']) !== ''): ?>
                    <div class="adm-color-muted small"><?= h($r['topic_description']) ?></div>
                <?php endif; ?>
            </td>
            <td><?= (int)$r['topic_id'] ?></td>
            <td>
                <?php if (trim((string)$r['topic_url']) !== ''): ?>
                    <a href="<?= h($r['topic_url']) ?>" target="_blank" rel="noopener noreferrer">Abrir</a>
                <?php else: ?>
                    <span class="adm-color-muted">(vacío)</span>
                <?php endif; ?>
            </td>
            <td><?= (int)$r['sort_order'] ?></td>
            <td><?= ((int)$r['is_active'] === 1) ? 'Activo' : 'Inactivo' ?></td>
            <td>
                <div>Alta: <?= h((string)($r['created_at'] ?? '')) ?></div>
                <div>Mod: <?= h((string)($r['updated_at'] ?? '')) ?></div>
            </td>
            <td>
                <a class="btn" href="/talim?s=admin_topic_viewer&edit=<?= (int)$r['id'] ?>">Editar</a>
                <form method="post" class="adm-inline-form" onsubmit="return confirm('¿Borrar este tema?');">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="crud_action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-red" type="submit">Borrar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="adm-color-muted">(Sin temas configurados)</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<script>
(function(){
    var input = document.getElementById('quickFilterTopicViewer');
    var tbody = document.getElementById('topicViewerBody');
    if (!input || !tbody) return;
    input.addEventListener('input', function(){
        var q = String(input.value || '').toLowerCase();
        var rows = tbody.querySelectorAll('tr');
        for (var i = 0; i < rows.length; i++) {
            var hay = String(rows[i].getAttribute('data-search') || rows[i].textContent || '').toLowerCase();
            rows[i].style.display = hay.indexOf(q) !== -1 ? '' : 'none';
        }
    });
})();
</script>

<?php admin_panel_close(); ?>
