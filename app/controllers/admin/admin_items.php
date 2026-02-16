<?php
// admin_items.php — CRUD Objetos (fact_items)
if (!isset($link) || !$link) { die("Error de conexión a la base de datos."); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
$actions = '<span style="margin-left:auto; display:flex; gap:8px; align-items:center;">'
	. '<button class="btn btn-green" type="button" onclick="openItemModal()">+ Nuevo objeto</button>'
	. '<label style="text-align:left;">Filtro r&aacute;pido '
	. '<input class="inp" type="text" id="quickFilterItems" placeholder="En esta p&aacute;gina..."></label>'
	. '</span>';
admin_panel_open('Objetos', $actions);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$flash = [];

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
	$sql = "UPDATE `$table` SET pretty_id=? WHERE id=?";
	if ($st = $link->prepare($sql)) {
		$st->bind_param("si", $slug, $id);
		$st->execute();
		$st->close();
	}
}

// Subidas de imagen
$DOCROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
$IMG_UPLOAD_DIR = $DOCROOT . '/public/img/inv/uploads';
$IMG_URL_BASE   = '/img/inv/uploads';
if (!is_dir($IMG_UPLOAD_DIR)) { @mkdir($IMG_UPLOAD_DIR, 0775, true); }

function save_item_image(array $file, string $uploadDir, string $urlBase): array {
	if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return ['ok'=>false,'msg'=>'no_file'];
	if ($file['error'] !== UPLOAD_ERR_OK) return ['ok'=>false,'msg'=>'Error de subida (#'.$file['error'].')'];
	if ($file['size'] > 5*1024*1024) return ['ok'=>false,'msg'=>'El archivo supera 5 MB'];
	$tmp = $file['tmp_name'];
	if (!is_uploaded_file($tmp)) return ['ok'=>false,'msg'=>'Subida no válida'];

	$mime = '';
	if (function_exists('finfo_open')) { $fi = finfo_open(FILEINFO_MIME_TYPE); if ($fi) { $mime = finfo_file($fi, $tmp); finfo_close($fi); } }
	if (!$mime) { $gi = @getimagesize($tmp); $mime = $gi['mime'] ?? ''; }

	$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
	if (!isset($allowed[$mime])) return ['ok'=>false,'msg'=>'Formato no permitido (JPG/PNG/GIF/WebP)'];

	$ext  = $allowed[$mime];
	$name = 'item-' . date('YmdHis') . '-' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
	$dst  = rtrim($uploadDir,'/').'/'.$name;

	if (!@move_uploaded_file($tmp, $dst)) return ['ok'=>false,'msg'=>'No se pudo mover el archivo subido'];
	@chmod($dst, 0644);
	return ['ok'=>true,'url'=>rtrim($urlBase,'/').'/'.$name, 'path'=>$dst];
}
function safe_unlink_item_image(string $relUrl, string $uploadDir): void {
	if ($relUrl === '') return;
	$abs = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__,'/').'/'.ltrim($relUrl,'/');
	$base = realpath($uploadDir);
	$absr = @realpath($abs);
	if ($absr && $base && strpos($absr, $base) === 0 && is_file($absr)) { @unlink($absr); }
}
function sanitize_utf8_text(string $s): string {
	// Ensure valid UTF-8, drop invalid bytes
	if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
		if (function_exists('iconv')) {
			$s = @iconv('UTF-8', 'UTF-8//IGNORE', $s) ?: $s;
		} elseif (function_exists('mb_convert_encoding')) {
			$s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
		}
	}
	return $s ?? '';
}

// Catálogos
$types = [];
if ($rs = $link->query("SELECT id, name FROM dim_item_types ORDER BY name ASC")) {
	while ($r = $rs->fetch_assoc()) { $types[] = $r; }
	$rs->close();
}
$origins = [];
if ($rs = $link->query("SELECT id, name FROM dim_bibliographies ORDER BY name ASC")) {
	while ($r = $rs->fetch_assoc()) { $origins[] = $r; }
	$rs->close();
}

// Borrar
if (isset($_GET['delete'])) {
	$id = (int)$_GET['delete'];
	if ($id > 0 && ($st = $link->prepare("DELETE FROM fact_items WHERE id = ?"))) {
		$st->bind_param("i", $id);
		$st->execute();
		$st->close();
		$flash[] = ['type'=>'ok','msg'=>'Objeto eliminado.'];
	}
}

// Crear / actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
	$id        = (int)($_POST['id'] ?? ($_POST['item_id'] ?? 0));
	$name      = trim((string)($_POST['name'] ?? ''));
	$itemTypeId = (int)($_POST['item_type_id'] ?? ($_POST['tipo'] ?? 0));
	$habilidad = trim((string)($_POST['habilidad'] ?? ''));
	$level     = (int)($_POST['level'] ?? ($_POST['nivel'] ?? 0));
	$gnosis    = (int)($_POST['gnosis'] ?? 0);
	$valor     = trim((string)($_POST['valor'] ?? ''));
	$bonus     = (int)($_POST['bonus'] ?? 0);
	$dano      = trim((string)($_POST['dano'] ?? ''));
	$metal     = (int)($_POST['metal'] ?? 0);
	$fuerza    = (int)($_POST['fuerza'] ?? 0);
	$destreza  = (int)($_POST['destreza'] ?? 0);
	$img       = trim((string)($_POST['img'] ?? ''));
	$description = sanitize_utf8_text((string)($_POST['description'] ?? ($_POST['descri'] ?? '')));
	$description = hg_mentions_convert($link, $description);
	$bibliographyId = (int)($_POST['bibliography_id'] ?? 0);
	$currentImg = trim((string)($_POST['current_img'] ?? ''));

	// Subida opcional
	$upload = save_item_image($_FILES['img_file'] ?? [], $IMG_UPLOAD_DIR, $IMG_URL_BASE);
	if (($upload['ok'] ?? false) === true) {
		if ($currentImg !== '') safe_unlink_item_image($currentImg, $IMG_UPLOAD_DIR);
		$img = $upload['url'];
	} else {
		if ($img === '' && $currentImg !== '') $img = $currentImg;
	}

	if ($name === '') {
		$flash[] = ['type'=>'error','msg'=>'El nombre es obligatorio.'];
	} else {
		if ($id > 0) {
			$st = $link->prepare("UPDATE fact_items
				SET name=?, item_type_id=?, habilidad=?, level=?, gnosis=?, valor=?, bonus=?, dano=?, metal=?, fuerza=?, destreza=?, img=?, description=?, bibliography_id=?
				WHERE id=?");
			if ($st) {
				$st->bind_param(
					"sisiisisiiissii",
					$name, $itemTypeId, $habilidad, $level, $gnosis, $valor, $bonus, $dano, $metal,
					$fuerza, $destreza, $img, $description, $bibliographyId, $id
				);
				$ok = $st->execute();
				$stErr = $st->error;
				$stNo = $st->errno;
				$st->close();
				if ($ok) {
					update_pretty_id($link, 'fact_items', $id, $name);
					$flash[] = ['type'=>'ok','msg'=>'Objeto actualizado.'];
				} else {
					$flash[] = ['type'=>'error','msg'=>'Error al actualizar (id '.$id.'): ' . ($stErr ?: $link->error) . ' ['.$stNo.']'];
				}
			} else {
				$flash[] = ['type'=>'error','msg'=>'Error al preparar UPDATE: '.$link->error];
			}
		} else {
			// Si no hay ID al editar, no insertamos por error
			if (!empty($_POST['id']) || !empty($_POST['item_id'])) {
				$flash[] = ['type'=>'error','msg'=>'ID inválido. No se pudo actualizar el objeto.'];
			} else {
			$st = $link->prepare("INSERT INTO fact_items
				(name, item_type_id, habilidad, level, gnosis, valor, bonus, dano, metal, fuerza, destreza, img, description, bibliography_id)
				VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
			if ($st) {
				$st->bind_param(
					"sisiisisiiissi",
					$name, $itemTypeId, $habilidad, $level, $gnosis, $valor, $bonus, $dano, $metal,
					$fuerza, $destreza, $img, $description, $bibliographyId
				);
				$ok = $st->execute();
				$stErr = $st->error;
				$stNo = $st->errno;
				$st->close();
				if ($ok) {
					$newId = $link->insert_id;
					update_pretty_id($link, 'fact_items', (int)$newId, $name);
					$flash[] = ['type'=>'ok','msg'=>'Objeto creado.'];
				} else {
					$flash[] = ['type'=>'error','msg'=>'Error al crear: ' . ($stErr ?: $link->error) . ' ['.$stNo.']'];
				}
			} else {
				$flash[] = ['type'=>'error','msg'=>'Error al preparar INSERT: '.$link->error];
			}
			}
		}
	}
}

// Prefill edición
$edit = null;
if (isset($_GET['edit'])) {
	$eid = (int)$_GET['edit'];
	if ($eid > 0 && ($st = $link->prepare("SELECT * FROM fact_items WHERE id=?"))) {
		$st->bind_param("i", $eid);
		$st->execute();
		$res = $st->get_result();
		$edit = $res ? $res->fetch_assoc() : null;
		$st->close();
	}
}

// Listado
$rows = [];
$rs = $link->query("SELECT id, name, item_type_id, bibliography_id FROM fact_items ORDER BY name ASC");
if ($rs) { while ($r = $rs->fetch_assoc()) { $rows[] = $r; } $rs->close(); }

// Datos completos para edición en modal
$rowsFull = [];
$rs = $link->query("SELECT * FROM fact_items ORDER BY name ASC");
if ($rs) { while ($r = $rs->fetch_assoc()) { $rowsFull[] = $r; } $rs->close(); }

function type_name($types, $id){
	foreach ($types as $t) if ((int)$t['id'] === (int)$id) return $t['name'];
	return '';
}
function origin_name($origins, $id){
	foreach ($origins as $o) if ((int)$o['id'] === (int)$id) return $o['name'];
	return '';
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
  width:min(1000px, 96vw);
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
.modal-body{ flex:1; overflow:auto; padding-right:6px; }

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

<div class="modal-back" id="itemModal">
	<div class="modal">
		<h3 id="itemModalTitle">Nuevo objeto</h3>
		<form method="post" id="itemForm" enctype="multipart/form-data">
			<input type="hidden" name="save_item" value="1">
			<input type="hidden" name="id" id="item_id" value="">
			<input type="hidden" name="current_img" id="item_current_img" value="">
			<div class="modal-body">
				<div style="display:grid; grid-template-columns:1fr 2fr; gap:8px; align-items:center;">
					<label>Nombre</label>
					<input class="inp" type="text" name="name" id="item_name" required>

					<label>Tipo</label>
					<select class="select" name="item_type_id" id="item_type_id">
						<option value="0">--</option>
						<?php foreach ($types as $t): ?>
							<option value="<?= (int)$t['id'] ?>"><?= h($t['name']) ?></option>
						<?php endforeach; ?>
					</select>

					<label>Origen</label>
					<select class="select" name="bibliography_id" id="item_bibliography_id">
						<option value="0">--</option>
						<?php foreach ($origins as $o): ?>
							<option value="<?= (int)$o['id'] ?>"><?= h($o['name']) ?></option>
						<?php endforeach; ?>
					</select>

					<label>Habilidad</label>
					<input class="inp" type="text" name="habilidad" id="item_habilidad">

					<label>Nivel</label>
					<input class="inp" type="number" name="level" id="item_level">

					<label>Gnosis</label>
					<input class="inp" type="number" name="gnosis" id="item_gnosis">

					<label>Valor</label>
					<input class="inp" type="text" name="valor" id="item_valor">

					<label>Bonus</label>
					<input class="inp" type="number" name="bonus" id="item_bonus">

					<label>Daño</label>
					<input class="inp" type="text" name="dano" id="item_dano">

					<label>Metal</label>
					<input class="inp" type="number" name="metal" id="item_metal">

					<label>Fuerza</label>
					<input class="inp" type="number" name="fuerza" id="item_fuerza">

					<label>Destreza</label>
					<input class="inp" type="number" name="destreza" id="item_destreza">

					<label>Imagen (URL)</label>
					<input class="inp" type="text" name="img" id="item_img">

					<label>Subir imagen</label>
					<input class="inp" type="file" name="img_file" id="item_img_file" accept="image/*">

					<label>Vista previa</label>
					<img class="img-preview" id="item_img_preview" alt="">

					<label>Descripción</label>
					<div>
						<div id="item_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
						<div id="item_editor" class="ql-container ql-snow"></div>
						<textarea class="ta" name="description" id="item_description" rows="8" style="display:none;"></textarea>
					</div>
				</div>
			</div>
			<div class="modal-actions">
				<button class="btn btn-green" type="submit">Guardar</button>
				<button class="btn" type="button" onclick="closeItemModal()">Cancelar</button>
			</div>
		</form>
	</div>
</div>

<table class="table" id="tablaItems">
	<thead>
		<tr>
			<th style="width:60px;">ID</th>
			<th>Nombre</th>
			<th>Tipo</th>
			<th>Origen</th>
			<th style="width:160px;">Acciones</th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ($rows as $r): ?>
		<?php
			$search = trim((string)($r['name'] ?? '') . ' ' . (string)type_name($types, $r['item_type_id'] ?? 0) . ' ' . (string)origin_name($origins, $r['bibliography_id'] ?? 0));
			if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
			else { $search = strtolower($search); }
		?>
		<tr data-search="<?= h($search) ?>">
			<td><?= (int)$r['id'] ?></td>
			<td><?= h($r['name']) ?></td>
			<td><?= h(type_name($types, $r['item_type_id'] ?? 0)) ?></td>
			<td><?= h(origin_name($origins, $r['bibliography_id'] ?? 0)) ?></td>
			<td>
				<button class="btn" type="button" onclick="openItemModal(<?= (int)$r['id'] ?>)">Editar</button>
				<a class="btn btn-red" href="/talim?s=admin_items&delete=<?= (int)$r['id'] ?>" onclick="return confirm('¿Eliminar objeto?');">Borrar</a>
			</td>
		</tr>
	<?php endforeach; ?>
	<?php if (empty($rows)): ?>
		<tr><td colspan="5" style="color:#bbb;">(Sin objetos)</td></tr>
	<?php endif; ?>
	</tbody>
</table>

<link href="/assets/vendor/quill/1.3.7/quill.snow.css" rel="stylesheet">
<script src="/assets/vendor/quill/1.3.7/quill.min.js"></script>
<?php include_once(__DIR__ . '/../../partials/admin/mentions_includes.php'); ?>
<script>
const itemsData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let itemEditor = null;

function ensureItemEditor(){
	if (!itemEditor && window.Quill) {
		itemEditor = new Quill('#item_editor', {
			theme: 'snow',
			modules: { toolbar: '#item_toolbar' }
		});
		if (window.hgMentions) { window.hgMentions.attachQuill(itemEditor, { types: ['character','season','episode','organization','group','gift','rite','totem','discipline','item','trait','background','merit','flaw','merydef','doc'] }); }
	}
}

function openItemModal(id = null){
	ensureItemEditor();
	const modal = document.getElementById('itemModal');
	document.getElementById('item_id').value = '';
	document.getElementById('item_name').value = '';
	document.getElementById('item_type_id').value = 0;
	document.getElementById('item_bibliography_id').value = 0;
	document.getElementById('item_habilidad').value = '';
	document.getElementById('item_level').value = 0;
	document.getElementById('item_gnosis').value = 0;
	document.getElementById('item_valor').value = '';
	document.getElementById('item_bonus').value = 0;
	document.getElementById('item_dano').value = '';
	document.getElementById('item_metal').value = 0;
	document.getElementById('item_fuerza').value = 0;
	document.getElementById('item_destreza').value = 0;
	document.getElementById('item_img').value = '';
	document.getElementById('item_current_img').value = '';
	document.getElementById('item_img_file').value = '';
	document.getElementById('item_img_preview').src = '';
	if (itemEditor) itemEditor.root.innerHTML = '';
	document.getElementById('item_description').value = '';

	if (id) {
		const row = itemsData.find(r => parseInt(r.id,10) === parseInt(id,10));
		if (row) {
			document.getElementById('itemModalTitle').textContent = 'Editar objeto';
			document.getElementById('item_id').value = row.id;
			document.getElementById('item_name').value = row.name || '';
			document.getElementById('item_type_id').value = row.item_type_id || 0;
			document.getElementById('item_bibliography_id').value = row.bibliography_id || 0;
			document.getElementById('item_habilidad').value = row.habilidad || '';
			document.getElementById('item_level').value = row.level || 0;
			document.getElementById('item_gnosis').value = row.gnosis || 0;
			document.getElementById('item_valor').value = row.valor || '';
			document.getElementById('item_bonus').value = row.bonus || 0;
			document.getElementById('item_dano').value = row.dano || '';
			document.getElementById('item_metal').value = row.metal || 0;
			document.getElementById('item_fuerza').value = row.fuerza || 0;
			document.getElementById('item_destreza').value = row.destreza || 0;
			document.getElementById('item_img').value = row.img || '';
			document.getElementById('item_current_img').value = row.img || '';
			if (row.img) document.getElementById('item_img_preview').src = row.img;
			const descri = row.description || '';
			document.getElementById('item_description').value = descri;
			if (itemEditor) itemEditor.root.innerHTML = descri;
		}
	} else {
		document.getElementById('itemModalTitle').textContent = 'Nuevo objeto';
	}
	modal.style.display = 'flex';
}

function closeItemModal(){
	document.getElementById('itemModal').style.display = 'none';
}

document.getElementById('item_img_file').addEventListener('change', function(){
	const f = this.files && this.files[0];
	if (!f) return;
	const url = URL.createObjectURL(f);
	document.getElementById('item_img_preview').src = url;
});

document.getElementById('itemForm').addEventListener('submit', function(){
	if (itemEditor) {
		const html = itemEditor.root.innerHTML || '';
		const plain = (itemEditor.getText() || '').replace(/\\s+/g,' ').trim();
		document.getElementById('item_description').value = plain ? html : '';
	}
});

document.addEventListener('keydown', function(e){
	if (e.key === 'Escape') closeItemModal();
});
</script>


<script>
(function(){
	const input = document.getElementById('quickFilterItems');
	if (!input) return;
	input.addEventListener('input', function(){
		const q = (this.value || '').toLowerCase();
		document.querySelectorAll('#tablaItems tbody tr').forEach(function(tr){
			const name = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
			tr.style.display = name.indexOf(q) !== -1 ? '' : 'none';
		});
	});
})();
</script>
<?php admin_panel_close(); ?>
