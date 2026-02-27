<?php
// admin_resources.php - CRUD Catalogo de Recursos (dim_systems_resources)
if (!isset($link) || !$link) { die("Error de conexion a la base de datos."); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/mentions.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function slugify_resource_pretty(string $text): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (function_exists('iconv')) { $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text; }
    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);
    return $text;
}
function persist_resource_pretty_id(mysqli $link, int $id, string $source): bool {
    if ($id <= 0) return false;
    $slug = slugify_resource_pretty($source);
    if ($slug === '') $slug = (string)$id;
    $st = $link->prepare("UPDATE dim_systems_resources SET pretty_id=? WHERE id=?");
    if (!$st) return false;
    $st->bind_param('si', $slug, $id);
    $ok = $st->execute();
    $st->close();
    return (bool)$ok;
}
function short_txt(string $s, int $n=110): string {
    $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
    if (mb_strlen($s) <= $n) return $s;
    return mb_substr($s, 0, $n) . '...';
}

if (empty($_SESSION['csrf_admin_resources'])) {
    $_SESSION['csrf_admin_resources'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_admin_resources'];
function csrf_ok(): bool {
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_resources']) && hash_equals($_SESSION['csrf_admin_resources'], $t);
}

$actions = '<span class="adm-flex-right-8">'
    . '<button class="btn btn-green" type="button" onclick="openResourceModal()">+ Nuevo recurso</button>'
    . '<label class="adm-text-left">Filtro rapido '
    . '<input class="inp" type="text" id="quickFilterResources" placeholder="En esta pagina..."></label>'
    . '</span>';
admin_panel_open('Recursos (catalogo)', $actions);

$flash = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    if (!csrf_ok()) {
        $flash[] = ['type'=>'error','msg'=>'CSRF invalido. Recarga la pagina.'];
    } else {
        $action = (string)($_POST['crud_action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'delete') {
            if ($id > 0 && ($st = $link->prepare("DELETE FROM dim_systems_resources WHERE id=?"))) {
                $st->bind_param('i', $id);
                if ($st->execute()) $flash[] = ['type'=>'ok','msg'=>'Recurso eliminado.'];
                else $flash[] = ['type'=>'error','msg'=>'Error al eliminar: '.$st->error];
                $st->close();
            } else {
                $flash[] = ['type'=>'error','msg'=>'ID invalido para eliminar.'];
            }
        }

        if ($action === 'create' || $action === 'update') {
            $name = trim((string)($_POST['name'] ?? ''));
            $kind = trim((string)($_POST['kind'] ?? ''));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $description = (string)($_POST['description'] ?? '');
            $description = hg_mentions_convert($link, $description);
            $kindAllowed = ['renombre', 'estado'];

            if ($name === '' || $kind === '') {
                $flash[] = ['type'=>'error','msg'=>'Nombre y tipo son obligatorios.'];
            } elseif (mb_strlen($kind) > 30) {
                $flash[] = ['type'=>'error','msg'=>'Tipo demasiado largo (max 30).'];
            } elseif (!in_array($kind, $kindAllowed, true)) {
                $flash[] = ['type'=>'error','msg'=>'Tipo invalido. Solo se permite: renombre o estado.'];
            } else {
                if ($action === 'create') {
                    $sql = "INSERT INTO dim_systems_resources (name, kind, sort_order, description, created_at, updated_at) VALUES (?,?,?,?,NOW(),NOW())";
                    $st = $link->prepare($sql);
                    if (!$st) {
                        $flash[] = ['type'=>'error','msg'=>'Error al preparar INSERT: '.$link->error];
                    } else {
                        $st->bind_param('ssis', $name, $kind, $sortOrder, $description);
                        if ($st->execute()) {
                            $newId = (int)$link->insert_id;
                            $prettyOk = persist_resource_pretty_id($link, $newId, $name);
                            $flash[] = ['type'=>$prettyOk ? 'ok' : 'error','msg'=>$prettyOk ? 'Recurso creado.' : 'Recurso creado, pero no se pudo guardar pretty_id.'];
                        } else {
                            $flash[] = ['type'=>'error','msg'=>'Error al crear: '.$st->error];
                        }
                        $st->close();
                    }
                } else {
                    if ($id <= 0) {
                        $flash[] = ['type'=>'error','msg'=>'ID invalido para actualizar.'];
                    } else {
                        $sql = "UPDATE dim_systems_resources SET name=?, kind=?, sort_order=?, description=?, updated_at=NOW() WHERE id=?";
                        $st = $link->prepare($sql);
                        if (!$st) {
                            $flash[] = ['type'=>'error','msg'=>'Error al preparar UPDATE: '.$link->error];
                        } else {
                            $st->bind_param('ssisi', $name, $kind, $sortOrder, $description, $id);
                            if ($st->execute()) {
                                $prettyOk = persist_resource_pretty_id($link, $id, $name);
                                $flash[] = ['type'=>$prettyOk ? 'ok' : 'error','msg'=>$prettyOk ? 'Recurso actualizado.' : 'Recurso actualizado, pero no se pudo guardar pretty_id.'];
                            } else {
                                $flash[] = ['type'=>'error','msg'=>'Error al actualizar: '.$st->error];
                            }
                            $st->close();
                        }
                    }
                }
            }
        }
    }
}

$rows = [];
$rowsFull = [];
$rs = $link->query("SELECT id, pretty_id, name, kind, sort_order, description FROM dim_systems_resources ORDER BY kind ASC, sort_order ASC, name ASC");
if ($rs) {
    while ($r = $rs->fetch_assoc()) {
        $rows[] = $r;
        $rowsFull[] = $r;
    }
    $rs->close();
}
?>

<?php if (!empty($flash)): ?>
    <div class="flash">
        <?php foreach ($flash as $m):
            $cl = $m['type']==='ok'?'ok':($m['type']==='error'?'err':'info'); ?>
            <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="modal-back" id="resourceModal">
    <div class="modal">
        <h3 id="resourceModalTitle">Nuevo recurso</h3>
        <form method="post" id="resourceForm">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="crud_action" id="resource_action" value="create">
            <input type="hidden" name="id" id="resource_id" value="0">
            <div class="modal-body">
                <div class="adm-grid-1-2">
                    <label>Nombre</label>
                    <input class="inp" type="text" name="name" id="resource_name" maxlength="100" required>

                    <label>Tipo</label>
                    <select class="select" name="kind" id="resource_kind" required>
                        <option value="renombre">renombre</option>
                        <option value="estado">estado</option>
                    </select>

                    <label>Orden</label>
                    <input class="inp" type="number" name="sort_order" id="resource_sort_order" value="0">

                    <label>Descripcion</label>
                    <textarea class="inp" name="description" id="resource_description" rows="8"></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-green" type="submit">Guardar</button>
                <button class="btn" type="button" onclick="closeResourceModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-back" id="resourceDeleteModal">
    <div class="modal adm-modal-sm">
        <h3>Confirmar borrado</h3>
        <div class="adm-help-text">
            Esto eliminara el recurso del catalogo.
        </div>
        <form method="post" id="resourceDeleteForm" class="adm-m-0">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="crud_action" value="delete">
            <input type="hidden" name="id" id="resource_delete_id" value="0">
            <div class="modal-actions">
                <button type="button" class="btn" onclick="closeResourceDeleteModal()">Cancelar</button>
                <button type="submit" class="btn btn-red">Borrar</button>
            </div>
        </form>
    </div>
</div>

<table class="table" id="tablaResources">
    <thead>
        <tr>
            <th class="adm-w-60">ID</th>
            <th class="adm-w-220">Nombre</th>
            <th class="adm-w-150">Tipo</th>
            <th class="adm-w-80">Orden</th>
            <th class="adm-w-220">Pretty ID</th>
            <th>Descripcion</th>
            <th class="adm-w-160">Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <?php
            $search = trim((string)($r['name'] ?? '') . ' ' . (string)($r['kind'] ?? '') . ' ' . (string)($r['pretty_id'] ?? '') . ' ' . (string)($r['description'] ?? ''));
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
            else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><?= (int)$r['id'] ?></td>
            <td><?= h((string)$r['name']) ?></td>
            <td><?= h((string)$r['kind']) ?></td>
            <td><?= (int)($r['sort_order'] ?? 0) ?></td>
            <td><?= h((string)($r['pretty_id'] ?? '')) ?></td>
            <td><?= h(short_txt(strip_tags((string)($r['description'] ?? '')), 20)) ?></td>
            <td>
                <button class="btn" type="button" onclick="openResourceModal(<?= (int)$r['id'] ?>)">Editar</button>
                <button class="btn btn-red" type="button" onclick="openResourceDeleteModal(<?= (int)$r['id'] ?>)">Borrar</button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="adm-color-muted">(Sin recursos)</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<script>
const resourcesData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;

function openResourceModal(id = null){
    const modal = document.getElementById('resourceModal');
    document.getElementById('resource_id').value = '0';
    document.getElementById('resource_action').value = 'create';
    document.getElementById('resource_name').value = '';
    document.getElementById('resource_kind').value = '';
    document.getElementById('resource_sort_order').value = '0';
    document.getElementById('resource_description').value = '';

    if (id) {
        const row = resourcesData.find(r => parseInt(r.id,10) === parseInt(id,10));
        if (row) {
            document.getElementById('resourceModalTitle').textContent = 'Editar recurso';
            document.getElementById('resource_action').value = 'update';
            document.getElementById('resource_id').value = String(row.id || 0);
            document.getElementById('resource_name').value = row.name || '';
            document.getElementById('resource_kind').value = row.kind || '';
            document.getElementById('resource_sort_order').value = String(parseInt(row.sort_order || 0, 10) || 0);
            document.getElementById('resource_description').value = row.description || '';
        }
    } else {
        document.getElementById('resourceModalTitle').textContent = 'Nuevo recurso';
    }
    modal.style.display = 'flex';
}

function closeResourceModal(){
    document.getElementById('resourceModal').style.display = 'none';
}

function openResourceDeleteModal(id){
    document.getElementById('resource_delete_id').value = String(parseInt(id || 0, 10) || 0);
    document.getElementById('resourceDeleteModal').style.display = 'flex';
}

function closeResourceDeleteModal(){
    document.getElementById('resourceDeleteModal').style.display = 'none';
}

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
        closeResourceModal();
        closeResourceDeleteModal();
    }
});
</script>

<script>
(function(){
    const input = document.getElementById('quickFilterResources');
    if (!input) return;
    input.addEventListener('input', function(){
        const q = (this.value || '').toLowerCase();
        document.querySelectorAll('#tablaResources tbody tr').forEach(function(tr){
            const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
            tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
        });
    });
})();
</script>

<?php admin_panel_close(); ?>



