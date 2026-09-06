<?php
// admin_datatables.php - Configure default/vital columns for public DataTables.
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/datatable_config.php');
if (!hg_admin_require_db($link)) { return; }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
include(__DIR__ . '/../../partials/admin/admin_styles.php');

if (!function_exists('admin_datatables_h')) {
    function admin_datatables_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$csrfKey = 'csrf_admin_datatables';
$csrf = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($csrfKey)
    : ($_SESSION[$csrfKey] ??= bin2hex(random_bytes(16)));
$tableReady = function_exists('hg_datatable_config_table_exists')
    && hg_datatable_config_table_exists($link);
$flash = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($_POST)
        : (string)($_POST['csrf'] ?? '');
    $validCsrf = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $csrfKey)
        : ($token !== '' && hash_equals((string)($_SESSION[$csrfKey] ?? ''), $token));

    if (!$validCsrf) {
        $flash[] = ['type' => 'err', 'msg' => 'CSRF inválido. Recarga la página.'];
    } elseif (!$tableReady) {
        $flash[] = ['type' => 'err', 'msg' => 'Falta instalar dim_datatable_columns.'];
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $isCore = isset($_POST['is_core']) ? 1 : 0;
            $visibleDefault = ($isCore || isset($_POST['visible_default'])) ? 1 : 0;

            if ($id <= 0) {
                $flash[] = ['type' => 'err', 'msg' => 'Fila inválida.'];
            } elseif ($stmt = $link->prepare('UPDATE dim_datatable_columns SET visible_default = ?, is_core = ? WHERE id = ?')) {
                $stmt->bind_param('iii', $visibleDefault, $isCore, $id);
                $ok = $stmt->execute();
                $stmt->close();
                $flash[] = $ok
                    ? ['type' => 'ok', 'msg' => 'Configuración actualizada.']
                    : ['type' => 'err', 'msg' => 'No se pudo actualizar la configuración.'];
            } else {
                $flash[] = ['type' => 'err', 'msg' => 'No se pudo preparar la actualización.'];
            }
        } elseif ($action === 'insert') {
            $datatableId = trim((string)($_POST['datatable_id'] ?? ''));
            $datatableLabel = trim((string)($_POST['datatable_label'] ?? ''));
            $columnLabel = trim((string)($_POST['column_label'] ?? ''));
            $columnIndexRaw = (string)($_POST['column_index'] ?? '');
            $columnIndex = filter_var($columnIndexRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 255]]);
            $isCore = isset($_POST['is_core']) ? 1 : 0;
            $visibleDefault = ($isCore || isset($_POST['visible_default'])) ? 1 : 0;

            $validId = (bool)preg_match('/^[A-Za-z0-9_-]{1,100}$/', $datatableId);
            if (!$validId || $datatableLabel === '' || mb_strlen($datatableLabel) > 100 || $columnLabel === '' || mb_strlen($columnLabel) > 100 || $columnIndex === false) {
                $flash[] = ['type' => 'err', 'msg' => 'Revisa ID, índice y etiquetas.'];
            } else {
                $sql = 'INSERT INTO dim_datatable_columns '
                    . '(datatable_id, datatable_label, column_index, column_label, visible_default, is_core) '
                    . 'VALUES (?, ?, ?, ?, ?, ?)';
                if ($stmt = $link->prepare($sql)) {
                    $stmt->bind_param('ssisii', $datatableId, $datatableLabel, $columnIndex, $columnLabel, $visibleDefault, $isCore);
                    $ok = $stmt->execute();
                    $duplicate = ((int)$stmt->errno === 1062);
                    $stmt->close();
                    if ($ok) {
                        $flash[] = ['type' => 'ok', 'msg' => 'Columna añadida.'];
                    } elseif ($duplicate) {
                        $flash[] = ['type' => 'err', 'msg' => 'Ese DataTable ya tiene configurado ese índice.'];
                    } else {
                        $flash[] = ['type' => 'err', 'msg' => 'No se pudo añadir la columna.'];
                    }
                } else {
                    $flash[] = ['type' => 'err', 'msg' => 'No se pudo preparar el alta.'];
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $flash[] = ['type' => 'err', 'msg' => 'Fila inválida.'];
            } elseif ($stmt = $link->prepare('DELETE FROM dim_datatable_columns WHERE id = ?')) {
                $stmt->bind_param('i', $id);
                $ok = $stmt->execute();
                $stmt->close();
                $flash[] = $ok
                    ? ['type' => 'ok', 'msg' => 'Columna eliminada de la configuración.']
                    : ['type' => 'err', 'msg' => 'No se pudo eliminar la columna.'];
            } else {
                $flash[] = ['type' => 'err', 'msg' => 'No se pudo preparar el borrado.'];
            }
        }
    }
}

$rows = [];
if ($tableReady) {
    $sql = 'SELECT id, datatable_id, datatable_label, column_index, column_label, visible_default, is_core '
        . 'FROM dim_datatable_columns ORDER BY datatable_label, datatable_id, column_index';
    if ($result = $link->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
}

admin_panel_open('Columnas DataTables');
?>
<?php foreach ($flash as $notice): ?>
    <div class="flash"><div class="<?= $notice['type'] === 'ok' ? 'ok' : 'err' ?>"><?= admin_datatables_h($notice['msg']) ?></div></div>
<?php endforeach; ?>

<p class="adm-color-muted">Define qué columnas aparecen de inicio. Una columna <strong>vital</strong> queda siempre visible y bloqueada en el selector público. El índice es el índice DataTables, empezando por 0.</p>

<?php if (!$tableReady): ?>
    <div class="flash"><div class="err">Falta la tabla <code>dim_datatable_columns</code>. Ejecuta <code>sql/2026-09-06_datatable_columns.sql</code> en la base de datos.</div></div>
<?php else: ?>
    <h3>Añadir columna</h3>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= admin_datatables_h($csrf) ?>">
        <input type="hidden" name="action" value="insert">
        <div class="grid">
            <label>ID DataTable
                <input class="inp adm-w-full" type="text" name="datatable_id" maxlength="100" placeholder="tabla-capitulos" required>
            </label>
            <label>Sección
                <input class="inp adm-w-full" type="text" name="datatable_label" maxlength="100" placeholder="Capítulos" required>
            </label>
            <label>Índice
                <input class="inp adm-w-full" type="number" name="column_index" min="0" max="255" required>
            </label>
            <label>Columna
                <input class="inp adm-w-full" type="text" name="column_label" maxlength="100" placeholder="Temporada" required>
            </label>
        </div>
        <p>
            <label><input type="checkbox" name="visible_default" value="1"> Visible inicialmente</label>
            &nbsp;&nbsp;
            <label><input type="checkbox" name="is_core" value="1"> Vital / bloqueada</label>
        </p>
        <p><button class="btn btn-green" type="submit">Añadir columna</button></p>
    </form>

    <h3>Configuración actual</h3>
    <div class="adm-w-full">
        <table class="table">
            <thead>
                <tr>
                    <th>Sección</th>
                    <th>ID DataTable</th>
                    <th>Índice</th>
                    <th>Columna</th>
                    <th>Visible inicial</th>
                    <th>Vital</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7">No hay columnas configuradas.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php $formId = 'dt-config-' . (int)$row['id']; ?>
                    <tr>
                        <td><?= admin_datatables_h($row['datatable_label']) ?></td>
                        <td><code><?= admin_datatables_h($row['datatable_id']) ?></code></td>
                        <td><?= (int)$row['column_index'] ?></td>
                        <td><?= admin_datatables_h($row['column_label']) ?></td>
                        <td><input form="<?= $formId ?>" type="checkbox" name="visible_default" value="1"<?= (int)$row['visible_default'] === 1 ? ' checked' : '' ?>></td>
                        <td><input form="<?= $formId ?>" type="checkbox" name="is_core" value="1"<?= (int)$row['is_core'] === 1 ? ' checked' : '' ?>></td>
                        <td>
                            <form id="<?= $formId ?>" method="post">
                                <input type="hidden" name="csrf" value="<?= admin_datatables_h($csrf) ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            </form>
                            <button class="btn btn-green" form="<?= $formId ?>" type="submit">Guardar</button>
                            <form method="post" class="adm-inline" onsubmit="return confirm('¿Eliminar esta columna de la configuración?');">
                                <input type="hidden" name="csrf" value="<?= admin_datatables_h($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button class="btn btn-red" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php admin_panel_close(); ?>
