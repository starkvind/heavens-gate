<?php
// admin_forms.php -- CRUD Formas (dim_forms)
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

$systems = [];
if ($rs = $link->query("SELECT name FROM dim_systems ORDER BY sort_order ASC, name ASC")) {
    while ($r = $rs->fetch_assoc()) { $systems[] = (string)$r['name']; }
    $rs->close();
}
$sys = trim((string)($_GET['sys'] ?? ''));
$sysOptions = '<option value="">-- Todos --</option>';
foreach ($systems as $sname) {
    $sel = ($sname === $sys) ? ' selected' : '';
    $sysOptions .= '<option value="'.h($sname).'"'.$sel.'>'.h($sname).'</option>';
}

$actions = '<span style="margin-left:auto; display:flex; gap:8px; align-items:center;">'
    . '<label style="text-align:left;">Sistema '
    . '<select class="select" id="filterSystemForms">'.$sysOptions.'</select></label>'
    . '<button class="btn btn-green" type="button" onclick="openFormModal()">+ Nueva forma</button>'
    . '<label style="text-align:left;">Filtro rapido '
    . '<input class="inp" type="text" id="quickFilterForms" placeholder="En esta pagina..."></label>'
    . '</span>';
admin_panel_open('Formas', $actions);

$flash = [];

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0 && ($st = $link->prepare("DELETE FROM dim_forms WHERE id=?"))) {
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        $flash[] = ['type'=>'ok','msg'=>'Forma eliminada.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form'])) {
    $id = (int)($_POST['id'] ?? 0);
    $afiliacion = trim((string)($_POST['afiliacion'] ?? ''));
    $raza = trim((string)($_POST['raza'] ?? ''));
    $forma = trim((string)($_POST['forma'] ?? ''));
    $desc = sanitize_utf8_text((string)($_POST['desc'] ?? ''));
    $desc = hg_mentions_convert($link, $desc);
    $imagen = trim((string)($_POST['imagen'] ?? ''));
    $armas = (int)($_POST['armas'] ?? 0);
    $armasfuego = (int)($_POST['armasfuego'] ?? 0);
    $bonfue = trim((string)($_POST['bonfue'] ?? ''));
    $bondes = trim((string)($_POST['bondes'] ?? ''));
    $bonres = trim((string)($_POST['bonres'] ?? ''));
    $regenera = (int)($_POST['regenera'] ?? 0);
    $hpregen = (int)($_POST['hpregen'] ?? 0);
    $bibliographyId = (int)($_POST['bibliography_id'] ?? 0);

    if ($afiliacion === '' || $raza === '' || $forma === '') {
        $flash[] = ['type'=>'error','msg'=>'Afiliacion, raza y forma son obligatorias.'];
    } else {
        if ($id > 0) {
            $sql = "UPDATE dim_forms SET affiliation=?, race=?, form=?, description=?, image_url=?, weapons=?, firearms=?, strength_bonus=?, dexterity_bonus=?, stamina_bonus=?, regeneration=?, hpregen=?, bibliography_id=?, updated_at=NOW() WHERE id=?";
            if ($st = $link->prepare($sql)) {
                $st->bind_param('sssssiisssiiii', $afiliacion, $raza, $forma, $desc, $imagen, $armas, $armasfuego, $bonfue, $bondes, $bonres, $regenera, $hpregen, $bibliographyId, $id);
                if ($st->execute()) {
                    $src = trim($afiliacion.' '.$raza.' '.$forma);
                    update_pretty_id($link, 'dim_forms', $id, $src);
                    $flash[] = ['type'=>'ok','msg'=>'Forma actualizada.'];
                } else {
                    $flash[] = ['type'=>'error','msg'=>'Error al actualizar: '.$st->error];
                }
                $st->close();
            } else {
                $flash[] = ['type'=>'error','msg'=>'Error al preparar UPDATE: '.$link->error];
            }
        } else {
            $sql = "INSERT INTO dim_forms (affiliation, race, form, description, image_url, weapons, firearms, strength_bonus, dexterity_bonus, stamina_bonus, regeneration, hpregen, bibliography_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())";
            if ($st = $link->prepare($sql)) {
                $st->bind_param('sssssiisssiii', $afiliacion, $raza, $forma, $desc, $imagen, $armas, $armasfuego, $bonfue, $bondes, $bonres, $regenera, $hpregen, $bibliographyId);
                if ($st->execute()) {
                    $newId = (int)$st->insert_id;
                    $src = trim($afiliacion.' '.$raza.' '.$forma);
                    update_pretty_id($link, 'dim_forms', $newId, $src);
                    $flash[] = ['type'=>'ok','msg'=>'Forma creada.'];
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

$rows = [];
$rowsFull = [];
$sql = "SELECT f.id, f.name, f.pretty_id, f.description, f.affiliation AS afiliacion, f.race AS raza, f.form AS forma, f.image_url AS imagen, f.weapons AS armas, f.firearms AS armasfuego, f.strength_bonus AS bonfue, f.dexterity_bonus AS bondes, f.stamina_bonus AS bonres, f.regeneration AS regenera, f.hpregen, f.bibliography_id, COALESCE(b.name,'') AS origen_name FROM dim_forms f LEFT JOIN dim_bibliographies b ON f.bibliography_id=b.id";
if ($sys !== '') {
    $sql .= " WHERE f.affiliation = ?";
}
$sql .= " ORDER BY f.affiliation, f.race, f.form";
if ($sys !== '') {
    if ($st = $link->prepare($sql)) {
        $st->bind_param('s', $sys);
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) { $rows[] = $r; $rowsFull[] = $r; }
        $st->close();
    }
} else {
    if ($rs = $link->query($sql)) {
        while ($r = $rs->fetch_assoc()) { $rows[] = $r; $rowsFull[] = $r; }
        $rs->close();
    }
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

<div class="modal-back" id="formModal">
    <div class="modal">
        <h3 id="formModalTitle">Nueva forma</h3>
        <form method="post" id="formForm">
            <input type="hidden" name="save_form" value="1">
            <input type="hidden" name="id" id="form_id" value="">
            <div class="modal-body">
                <div style="display:grid; grid-template-columns:1fr 2fr; gap:8px; align-items:center;">
                    <label>Afiliacion</label>
                    <input class="inp" type="text" name="afiliacion" id="form_afiliacion" required>

                    <label>Raza</label>
                    <input class="inp" type="text" name="raza" id="form_raza" required>

                    <label>Forma</label>
                    <input class="inp" type="text" name="forma" id="form_forma" required>

                    <label>Imagen</label>
                    <input class="inp" type="text" name="imagen" id="form_imagen">

                    <label>Armas</label>
                    <select class="select" name="armas" id="form_armas">
                        <option value="0">No</option>
                        <option value="1">Si</option>
                    </select>

                    <label>Armas de fuego</label>
                    <select class="select" name="armasfuego" id="form_armasfuego">
                        <option value="0">No</option>
                        <option value="1">Si</option>
                    </select>

                    <label>Bon. Fuerza</label>
                    <input class="inp" type="text" name="bonfue" id="form_bonfue">

                    <label>Bon. Destreza</label>
                    <input class="inp" type="text" name="bondes" id="form_bondes">

                    <label>Bon. Resistencia</label>
                    <input class="inp" type="text" name="bonres" id="form_bonres">

                    <label>Regenera</label>
                    <select class="select" name="regenera" id="form_regenera">
                        <option value="0">No</option>
                        <option value="1">Si</option>
                    </select>

                    <label>HP Regenera</label>
                    <input class="inp" type="number" name="hpregen" id="form_hpregen">

                    <label>Origen</label>
                    <select class="select" name="bibliography_id" id="form_bibliography_id">
                        <option value="0">--</option>
                        <?php foreach ($origins as $o): ?>
                            <option value="<?= (int)$o['id'] ?>"><?= h($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>Descripcion</label>
                    <div>
                        <div id="form_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
                        <div id="form_editor" class="ql-container ql-snow"></div>
                        <textarea class="ta" name="desc" id="form_desc" rows="8" style="display:none;"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-green" type="submit">Guardar</button>
                <button class="btn" type="button" onclick="closeFormModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<table class="table" id="tablaForms">
    <thead>
        <tr>
            <th style="width:60px;">ID</th>
            <th>Afiliacion</th>
            <th>Raza</th>
            <th>Forma</th>
            <th>Origen</th>
            <th style="width:160px;">Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <?php
            $search = trim((string)($r['afiliacion'] ?? '') . ' ' . (string)($r['raza'] ?? '') . ' ' . (string)($r['forma'] ?? '') . ' ' . (string)($r['origen_name'] ?? ''));
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
            else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><?= (int)$r['id'] ?></td>
            <td><?= h($r['afiliacion']) ?></td>
            <td><?= h($r['raza']) ?></td>
            <td><?= h($r['forma']) ?></td>
            <td><?= h($r['origen_name'] ?? '') ?></td>
            <td>
                <button class="btn" type="button" onclick="openFormModal(<?= (int)$r['id'] ?>)">Editar</button>
                <a class="btn btn-red" href="/talim?s=admin_forms&delete=<?= (int)$r['id'] ?>" onclick="return confirm('Eliminar forma?');">Borrar</a>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="6" style="color:#bbb;">(Sin formas)</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<link href="/assets/vendor/quill/1.3.7/quill.snow.css" rel="stylesheet">
<script src="/assets/vendor/quill/1.3.7/quill.min.js"></script>
<?php include_once(__DIR__ . '/../../partials/admin/mentions_includes.php'); ?>
<script>
const formsData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let formEditor = null;

function ensureFormEditor(){
    if (!formEditor && window.Quill) {
        formEditor = new Quill('#form_editor', { theme:'snow', modules:{ toolbar:'#form_toolbar' } });
        if (window.hgMentions) { window.hgMentions.attachQuill(formEditor, { types: ['character','season','episode','organization','group','gift','rite','totem','discipline','item','trait','background','merit','flaw','merydef','doc'] }); }
    }
}

function openFormModal(id = null){
    ensureFormEditor();
    const modal = document.getElementById('formModal');
    document.getElementById('form_id').value = '';
    document.getElementById('form_afiliacion').value = '';
    document.getElementById('form_raza').value = '';
    document.getElementById('form_forma').value = '';
    document.getElementById('form_desc').value = '';
    document.getElementById('form_imagen').value = '';
    document.getElementById('form_armas').value = '0';
    document.getElementById('form_armasfuego').value = '0';
    document.getElementById('form_bonfue').value = '';
    document.getElementById('form_bondes').value = '';
    document.getElementById('form_bonres').value = '';
    document.getElementById('form_regenera').value = '0';
    document.getElementById('form_hpregen').value = '0';
    document.getElementById('form_bibliography_id').value = '0';
    if (formEditor) formEditor.root.innerHTML = '';

    if (id) {
        const row = formsData.find(r => parseInt(r.id,10) === parseInt(id,10));
        if (row) {
            document.getElementById('formModalTitle').textContent = 'Editar forma';
            document.getElementById('form_id').value = row.id;
            document.getElementById('form_afiliacion').value = row.afiliacion || '';
            document.getElementById('form_raza').value = row.raza || '';
            document.getElementById('form_forma').value = row.forma || '';
            document.getElementById('form_imagen').value = row.imagen || '';
            document.getElementById('form_armas').value = row.armas || 0;
            document.getElementById('form_armasfuego').value = row.armasfuego || 0;
            document.getElementById('form_bonfue').value = row.bonfue || '';
            document.getElementById('form_bondes').value = row.bondes || '';
            document.getElementById('form_bonres').value = row.bonres || '';
            document.getElementById('form_regenera').value = row.regenera || 0;
            document.getElementById('form_hpregen').value = row.hpregen || 0;
            document.getElementById('form_bibliography_id').value = row.bibliography_id || 0;
            const desc = row.description || row.desc || '';
            document.getElementById('form_desc').value = desc;
            if (formEditor) formEditor.root.innerHTML = desc;
        }
    } else {
        document.getElementById('formModalTitle').textContent = 'Nueva forma';
    }
    modal.style.display = 'flex';
}

function closeFormModal(){
    document.getElementById('formModal').style.display = 'none';
}

document.getElementById('formForm').addEventListener('submit', function(){
    if (formEditor) {
        const html = formEditor.root.innerHTML || '';
        const plain = (formEditor.getText() || '').replace(/\s+/g,' ').trim();
        document.getElementById('form_desc').value = plain ? html : '';
    }
});

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeFormModal();
});
</script>

<script>
(function(){
    const input = document.getElementById('quickFilterForms');
    if (!input) return;
    input.addEventListener('input', function(){
        const q = (this.value || '').toLowerCase();
        document.querySelectorAll('#tablaForms tbody tr').forEach(function(tr){
            const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
            tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
        });
    });
})();
</script>

<script>
(function(){
    const sel = document.getElementById('filterSystemForms');
    if (!sel) return;
    sel.addEventListener('change', function(){
        const url = new URL(window.location.href);
        const v = this.value || '';
        if (v) url.searchParams.set('sys', v);
        else url.searchParams.delete('sys');
        const qs = url.searchParams.toString();
        window.location.href = url.pathname + (qs ? ('?'+qs) : '');
    });
})();
</script>

<?php admin_panel_close(); ?>
