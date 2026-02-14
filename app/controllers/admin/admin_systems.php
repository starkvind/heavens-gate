<?php
// admin_systems.php -- CRUD Sistemas (dim_systems)
if (!isset($link) || !$link) { die("Error de conexion a la base de datos."); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');
include_once(__DIR__ . '/../../helpers/mentions.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function slugify_pretty(string $text): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (function_exists('iconv')) { $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text; }
    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);
    return $text;
}
function update_pretty_id(mysqli $link, string $table, int $id, string $source): void {
    if ($id <= 0) return;
    $slug = slugify_pretty($source);
    if ($slug === '') $slug = (string)$id;
    if ($st = $link->prepare("UPDATE `$table` SET pretty_id=? WHERE id=?")) {
        $st->bind_param("si", $slug, $id);
        $st->execute();
        $st->close();
    }
}
function sanitize_utf8_text(string $s): string {
    if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
        if (function_exists('iconv')) {
            $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s) ?: $s;
        } elseif (function_exists('mb_convert_encoding')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
    }
    return $s ?? '';
}

$origins = [];
if ($rs = $link->query("SELECT id, name FROM dim_bibliographies ORDER BY name ASC")) {
    while ($r = $rs->fetch_assoc()) { $origins[] = $r; }
    $rs->close();
}

$actions = '<span style="margin-left:auto; display:flex; gap:8px; align-items:center;">'
    . '<button class="btn btn-green" type="button" onclick="openSystemModal()">+ Nuevo sistema</button>'
    . '<label style="text-align:left;">Filtro rapido '
    . '<input class="inp" type="text" id="quickFilterSystems" placeholder="En esta pagina..."></label>'
    . '</span>';
admin_panel_open('Sistemas', $actions);

$flash = [];

// Borrar
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0 && ($st = $link->prepare("DELETE FROM dim_systems WHERE id=?"))) {
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        $flash[] = ['type'=>'ok','msg'=>'Sistema eliminado.'];
    }
}

// Crear/Actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_system'])) {
    $id = (int)($_POST['id'] ?? 0);
    $orden = (int)($_POST['orden'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $img = trim((string)($_POST['img'] ?? ''));
    $formas = (int)($_POST['formas'] ?? 0);
    $desc = sanitize_utf8_text((string)($_POST['descripcion'] ?? ''));
    $desc = hg_mentions_convert($link, $desc);
    $origen = (int)($_POST['origen'] ?? 0);

    if ($name === '') {
        $flash[] = ['type'=>'error','msg'=>'El nombre es obligatorio.'];
    } else {
        if ($id > 0) {
            $sql = "UPDATE dim_systems SET orden=?, name=?, img=?, formas=?, descripcion=?, origen=? WHERE id=?";
            if ($st = $link->prepare($sql)) {
                $st->bind_param('issisii', $orden, $name, $img, $formas, $desc, $origen, $id);
                if ($st->execute()) {
                    update_pretty_id($link, 'dim_systems', $id, $name);
                    $flash[] = ['type'=>'ok','msg'=>'Sistema actualizado.'];
                } else {
                    $flash[] = ['type'=>'error','msg'=>'Error al actualizar: '.$st->error];
                }
                $st->close();
            } else {
                $flash[] = ['type'=>'error','msg'=>'Error al preparar UPDATE: '.$link->error];
            }
        } else {
            $sql = "INSERT INTO dim_systems (orden, name, img, formas, descripcion, origen, created_at, updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())";
            if ($st = $link->prepare($sql)) {
                $st->bind_param('issisi', $orden, $name, $img, $formas, $desc, $origen);
                if ($st->execute()) {
                    $newId = (int)$st->insert_id;
                    update_pretty_id($link, 'dim_systems', $newId, $name);
                    $flash[] = ['type'=>'ok','msg'=>'Sistema creado.'];
                } else {
                    $flash[] = ['type'=>'error','msg'=>'Error al crear: '.$st->error];
                }
                $st->close();
            } else {
                $flash[] = ['type'=>'error','msg'=>'Error al preparar INSERT: '.$link->error];
            }
        }
    }
}

// Listado
$rows = [];
$rowsFull = [];
$sql = "SELECT s.id, s.orden, s.name, s.img, s.formas, s.descripcion, s.origen, COALESCE(b.name,'') AS origen_name FROM dim_systems s LEFT JOIN dim_bibliographies b ON s.origen=b.id ORDER BY s.orden, s.name";
if ($rs = $link->query($sql)) {
    while ($r = $rs->fetch_assoc()) { $rows[] = $r; $rowsFull[] = $r; }
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

<style>
.modal-back{
  position:fixed; inset:0;
  background:rgba(0,0,0,.6);
  display:none; align-items:center; justify-content:center;
  z-index:9999; padding:14px; box-sizing:border-box;
}
.modal{
  width:min(980px, 96vw);
  max-height:92vh;
  overflow:hidden;
  background:#05014E;
  border:1px solid #000088;
  border-radius:12px;
  padding:12px;
  position:relative;
  display:flex;
  flex-direction:column;
}
.modal form{ display:flex; flex-direction:column; flex:1; }
.modal-body{ flex:1; overflow:auto; padding-right:6px; min-height:0; }
.ql-toolbar.ql-snow{
  border:1px solid #000088 !important;
  background:#050b36 !important;
  border-radius:8px 8px 0 0;
}
.ql-container.ql-snow{
  border:1px solid #000088 !important;
  border-top:none !important;
  background:#000033 !important;
  color:#fff !important;
  border-radius:0 0 8px 8px;
}
.ql-editor{ min-height:220px; font-size:12px; }
.ql-snow .ql-stroke{ stroke:#cfe !important; }
.ql-snow .ql-fill{ fill:#cfe !important; }
.ql-snow .ql-picker{ color:#cfe !important; }

.ql-snow .ql-picker-options{
  background:#050b36 !important;
  border:1px solid #000088 !important;
}
.ql-snow .ql-picker-item{
  color:#cfe !important;
}

</style>

<div class="modal-back" id="systemModal">
    <div class="modal">
        <h3 id="systemModalTitle">Nuevo sistema</h3>
        <form method="post" id="systemForm">
            <input type="hidden" name="save_system" value="1">
            <input type="hidden" name="id" id="system_id" value="">
            <div class="modal-body">
                <div style="display:grid; grid-template-columns:1fr 2fr; gap:8px; align-items:center;">
                    <label>Orden</label>
                    <input class="inp" type="number" name="orden" id="system_orden">

                    <label>Nombre</label>
                    <input class="inp" type="text" name="name" id="system_name" required>

                    <label>Imagen</label>
                    <input class="inp" type="text" name="img" id="system_img">

                    <label>Formas</label>
                    <select class="select" name="formas" id="system_formas">
                        <option value="0">No</option>
                        <option value="1">Si</option>
                    </select>

                    <label>Origen</label>
                    <select class="select" name="origen" id="system_origen">
                        <option value="0">--</option>
                        <?php foreach ($origins as $o): ?>
                            <option value="<?= (int)$o['id'] ?>"><?= h($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>Descripcion</label>
                    <div>
                        <div id="system_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
                        <div id="system_editor" class="ql-container ql-snow"></div>
                        <textarea class="ta" name="descripcion" id="system_desc" rows="8" style="display:none;"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-green" type="submit">Guardar</button>
                <button class="btn" type="button" onclick="closeSystemModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<table class="table" id="tablaSystems">
    <thead>
        <tr>
            <th style="width:60px;">ID</th>
            <th>Nombre</th>
            <th>Orden</th>
            <th>Formas</th>
            <th>Origen</th>
            <th style="width:160px;">Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <?php
            $search = trim((string)($r['name'] ?? '') . ' ' . (string)($r['origen_name'] ?? '') . ' ' . (string)($r['orden'] ?? ''));
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
            else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><?= (int)$r['id'] ?></td>
            <td><?= h($r['name']) ?></td>
            <td><?= (int)$r['orden'] ?></td>
            <td><?= ((int)$r['formas'] === 1) ? 'Si' : 'No' ?></td>
            <td><?= h($r['origen_name'] ?? '') ?></td>
            <td>
                <button class="btn" type="button" onclick="openSystemModal(<?= (int)$r['id'] ?>)">Editar</button>
                <a class="btn btn-red" href="/talim?s=admin_systems&delete=<?= (int)$r['id'] ?>" onclick="return confirm('Eliminar sistema?');">Borrar</a>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="6" style="color:#bbb;">(Sin sistemas)</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<link href="/assets/vendor/quill/1.3.7/quill.snow.css" rel="stylesheet">
<script src="/assets/vendor/quill/1.3.7/quill.min.js"></script>
<?php include_once(__DIR__ . '/../../partials/admin/mentions_includes.php'); ?>
<script>
const systemsData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let sysEditor = null;

function ensureSysEditor(){
    if (!sysEditor && window.Quill) {
        sysEditor = new Quill('#system_editor', { theme:'snow', modules:{ toolbar:'#system_toolbar' } });
        if (window.hgMentions) { window.hgMentions.attachQuill(sysEditor, { types: ['character','season','episode','organization','group','gift','rite','totem','discipline','item','trait','background','merit','flaw','merydef','doc'] }); }
    }
}

function openSystemModal(id = null){
    ensureSysEditor();
    const modal = document.getElementById('systemModal');
    document.getElementById('system_id').value = '';
    document.getElementById('system_orden').value = '';
    document.getElementById('system_name').value = '';
    document.getElementById('system_img').value = '';
    document.getElementById('system_formas').value = '0';
    document.getElementById('system_origen').value = '0';
    document.getElementById('system_desc').value = '';
    if (sysEditor) sysEditor.root.innerHTML = '';

    if (id) {
        const row = systemsData.find(r => parseInt(r.id,10) === parseInt(id,10));
        if (row) {
            document.getElementById('systemModalTitle').textContent = 'Editar sistema';
            document.getElementById('system_id').value = row.id;
            document.getElementById('system_orden').value = row.orden || 0;
            document.getElementById('system_name').value = row.name || '';
            document.getElementById('system_img').value = row.img || '';
            document.getElementById('system_formas').value = row.formas || 0;
            document.getElementById('system_origen').value = row.origen || 0;
            const desc = row.descripcion || '';
            document.getElementById('system_desc').value = desc;
            if (sysEditor) sysEditor.root.innerHTML = desc;
        }
    } else {
        document.getElementById('systemModalTitle').textContent = 'Nuevo sistema';
    }
    modal.style.display = 'flex';
}

function closeSystemModal(){
    document.getElementById('systemModal').style.display = 'none';
}

document.getElementById('systemForm').addEventListener('submit', function(){
    if (sysEditor) {
        const html = sysEditor.root.innerHTML || '';
        const plain = (sysEditor.getText() || '').replace(/\s+/g,' ').trim();
        document.getElementById('system_desc').value = plain ? html : '';
    }
});

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeSystemModal();
});
</script>

<script>
(function(){
    const input = document.getElementById('quickFilterSystems');
    if (!input) return;
    input.addEventListener('input', function(){
        const q = (this.value || '').toLowerCase();
        document.querySelectorAll('#tablaSystems tbody tr').forEach(function(tr){
            const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
            tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
        });
    });
})();
</script>

<?php admin_panel_close(); ?>
