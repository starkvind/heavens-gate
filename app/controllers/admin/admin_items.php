<?php
// admin_items.php - CRUD Objetos (fact_items)
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
$isAjaxRequest = (
	((string)($_GET['ajax'] ?? '') === '1')
	|| (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);
$actions = '<span class="adm-flex-right-8">'
	. '<button class="btn btn-green" type="button" onclick="openItemModal()">+ Nuevo objeto</button>'
	. '<label class="adm-text-left">Filtro r&aacute;pido '
	. '<input class="inp" type="text" id="quickFilterItems" placeholder="En esta p&aacute;gina..."></label>'
	. '</span>';
if (!$isAjaxRequest) {
	admin_panel_open('Objetos', $actions);
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$flash = [];
$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_items';
if (function_exists('hg_admin_ensure_csrf_token')) {
	$CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
	if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
		$_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
	}
	$CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function items_csrf_ok(): bool {
	$payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
	$t = function_exists('hg_admin_extract_csrf_token')
		? hg_admin_extract_csrf_token($payload)
		: (string)($_POST['csrf'] ?? '');
	if (function_exists('hg_admin_csrf_valid')) {
		return hg_admin_csrf_valid($t, 'csrf_admin_items');
	}
	return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_items']) && hash_equals($_SESSION['csrf_admin_items'], $t);
}

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
	$docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__,'/');
	$rel = '/'.ltrim($relUrl,'/');
	if (strpos($rel, '/img/') === 0) {
		$abs = $docroot . '/public' . $rel;
	} else {
		$abs = $docroot . $rel;
	}
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

// Borrar legacy
if (!$isAjaxRequest && isset($_GET['delete'])) {
	$flash[] = ['type'=>'error','msg'=>'El borrado por URL ha sido desactivado por seguridad. Usa el boton Borrar.'];
}

// Borrar AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['crud_action'] ?? '') === 'delete') {
	if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
		hg_admin_require_session(true);
	}
	if (!items_csrf_ok()) {
		$flash[] = ['type'=>'error','msg'=>'CSRF invalido. Recarga la pagina.'];
	} else {
		$id = (int)($_POST['id'] ?? 0);
		if ($id <= 0) {
			$flash[] = ['type'=>'error','msg'=>'ID invalido para borrar.'];
		} elseif ($st = $link->prepare("DELETE FROM fact_items WHERE id = ?")) {
			$st->bind_param("i", $id);
			if ($st->execute()) {
				$flash[] = ['type'=>'ok','msg'=>'Objeto eliminado.'];
			} else {
				$flash[] = ['type'=>'error','msg'=>'Error al borrar: '.$st->error];
			}
			$st->close();
		} else {
			$flash[] = ['type'=>'error','msg'=>'Error al preparar DELETE: '.$link->error];
		}
	}
}

// Crear / actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
	if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
		hg_admin_require_session(true);
	}
	if (!items_csrf_ok()) {
		$flash[] = ['type'=>'error','msg'=>'CSRF invalido. Recarga la pagina.'];
	} else {
		$id        = (int)($_POST['id'] ?? ($_POST['item_id'] ?? 0));
		$name      = trim((string)($_POST['name'] ?? ''));
		$itemTypeId = (int)($_POST['item_type_id'] ?? ($_POST['tipo'] ?? 0));
		$habilidad = trim((string)($_POST['ability_name'] ?? ''));
		$level     = (int)($_POST['level'] ?? ($_POST['nivel'] ?? 0));
		$gnosis    = (int)($_POST['gnosis'] ?? 0);
		$valor     = (int)($_POST['rating'] ?? 0);
		$bonus     = (int)($_POST['bonus'] ?? 0);
		$dano      = trim((string)($_POST['damage_type'] ?? ''));
		$metal     = (int)($_POST['metal'] ?? 0);
		$fuerza    = (int)($_POST['strength_req'] ?? 0);
		$destreza  = (int)($_POST['dexterity_req'] ?? 0);
		$img       = trim((string)($_POST['image_url'] ?? ''));
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
			if (($upload['msg'] ?? '') !== '' && (string)$upload['msg'] !== 'no_file') {
				$flash[] = ['type'=>'error','msg'=>(string)$upload['msg']];
			}
		}

		if ($name === '') {
			$flash[] = ['type'=>'error','msg'=>'El nombre es obligatorio.'];
		} else {
			if ($id > 0) {
				$st = $link->prepare("UPDATE fact_items
					SET name=?, item_type_id=?, skill_name=?, level=?, gnosis=?, rating=?, bonus=?, damage_type=?, metal=?, strength_req=?, dexterity_req=?, image_url=?, description=?, bibliography_id=?
					WHERE id=?");
				if ($st) {
					$st->bind_param(
						"sisiiiisiiissii",
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
					(name, item_type_id, skill_name, level, gnosis, rating, bonus, damage_type, metal, strength_req, dexterity_req, image_url, description, bibliography_id)
					VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
				if ($st) {
					$st->bind_param(
						"sisiiiisiiissi",
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

if ($isAjaxRequest && (string)($_GET['ajax_mode'] ?? '') === 'list') {
	if (function_exists('hg_admin_require_session')) {
		hg_admin_require_session(true);
	}
	if (function_exists('hg_admin_json_success')) {
		hg_admin_json_success([
			'rows' => $rows,
			'rowsFull' => $rowsFull,
			'total' => count($rows),
		], 'Listado');
	}
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode([
		'ok' => true,
		'rows' => $rows,
		'rowsFull' => $rowsFull,
		'total' => count($rows),
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
}

$ajaxSaveDelete = (
	$isAjaxRequest
	&& $_SERVER['REQUEST_METHOD'] === 'POST'
	&& (isset($_POST['save_item']) || (string)($_POST['crud_action'] ?? '') === 'delete')
);
if ($ajaxSaveDelete) {
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
		hg_admin_json_success([
			'rows' => $rows,
			'rowsFull' => $rowsFull,
			'messages' => $messages,
		], $okMsg);
	}
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode([
		'ok' => true,
		'message' => $okMsg,
		'msg' => $okMsg,
		'data' => [
			'rows' => $rows,
			'rowsFull' => $rowsFull,
			'messages' => $messages,
		],
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
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

<div class="modal-back" id="itemModal">
	<div class="modal">
		<h3 id="itemModalTitle">Nuevo objeto</h3>
		<form method="post" id="itemForm" enctype="multipart/form-data">
			<input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
			<input type="hidden" name="save_item" value="1">
			<input type="hidden" name="id" id="item_id" value="">
			<input type="hidden" name="current_img" id="item_current_img" value="">
			<div class="modal-body">
				<div class="adm-grid-1-2">
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
					<input class="inp" type="text" name="ability_name" id="item_habilidad">

					<label>Nivel</label>
					<input class="inp" type="number" name="level" id="item_level">

					<label>Gnosis</label>
					<input class="inp" type="number" name="gnosis" id="item_gnosis">

					<label>Valor</label>
					<input class="inp" type="text" name="rating" id="item_valor">

					<label>Bonus</label>
					<input class="inp" type="number" name="bonus" id="item_bonus">

					<label>Daño</label>
					<input class="inp" type="text" name="damage_type" id="item_dano">

					<label>Metal</label>
					<input class="inp" type="number" name="metal" id="item_metal">

					<label>Fuerza</label>
					<input class="inp" type="number" name="strength_req" id="item_fuerza">

					<label>Destreza</label>
					<input class="inp" type="number" name="dexterity_req" id="item_destreza">

					<label>Imagen (URL)</label>
					<input class="inp" type="text" name="image_url" id="item_img">

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
						<textarea class="ta adm-hidden" name="description" id="item_description" rows="8"></textarea>
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
			<th class="adm-w-60">ID</th>
			<th>Nombre</th>
			<th>Tipo</th>
			<th>Origen</th>
			<th class="adm-w-160">Acciones</th>
		</tr>
	</thead>
	<tbody id="itemsTbody">
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
				<button class="btn" type="button" data-edit="<?= (int)$r['id'] ?>">Editar</button>
				<button class="btn btn-red" type="button" data-del="<?= (int)$r['id'] ?>">Borrar</button>
			</td>
		</tr>
	<?php endforeach; ?>
	<?php if (empty($rows)): ?>
		<tr><td colspan="5" class="adm-color-muted">(Sin objetos)</td></tr>
	<?php endif; ?>
	</tbody>
</table>

<link href="/assets/vendor/quill/1.3.7/quill.snow.css" rel="stylesheet">
<script src="/assets/vendor/quill/1.3.7/quill.min.js"></script>
<?php include_once(__DIR__ . '/../../partials/admin/mentions_includes.php'); ?>
<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
let itemsData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
const itemTypes = <?= json_encode($types, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const itemOrigins = <?= json_encode($origins, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let itemEditor = null;

function request(url, opts){
	if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
		return window.HGAdminHttp.request(url, opts || {});
	}
	const cfg = Object.assign({
		method: 'GET',
		credentials: 'same-origin',
		headers: { 'X-Requested-With': 'XMLHttpRequest' }
	}, opts || {});
	return fetch(url, cfg).then(async function(resp){
		const text = await resp.text();
		let payload = {};
		if (text) {
			try { payload = JSON.parse(text); }
			catch (e) { payload = { ok:false, message:'Respuesta no JSON', raw:text }; }
		}
		if (!resp.ok || (payload && payload.ok === false)) {
			const err = new Error((payload && (payload.message || payload.error || payload.msg)) || ('HTTP ' + resp.status));
			err.status = resp.status;
			err.payload = payload;
			throw err;
		}
		return payload;
	});
}

function endpointUrl(mode){
	const url = new URL(window.location.href);
	url.searchParams.set('s', 'admin_items');
	url.searchParams.set('ajax', '1');
	if (mode) url.searchParams.set('ajax_mode', mode);
	else url.searchParams.delete('ajax_mode');
	url.searchParams.set('_ts', Date.now());
	return url.toString();
}

function esc(s){
	return String(s || '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}

function nameById(list, id){
	const n = parseInt(id || 0, 10) || 0;
	const row = (list || []).find(function(it){ return (parseInt(it.id || 0, 10) || 0) === n; });
	return row ? String(row.name || '') : '';
}

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
			document.getElementById('item_habilidad').value = row.skill_name || row.ability_name || '';
			document.getElementById('item_level').value = row.level || 0;
			document.getElementById('item_gnosis').value = row.gnosis || 0;
			document.getElementById('item_valor').value = row.rating || '';
			document.getElementById('item_bonus').value = row.bonus || 0;
			document.getElementById('item_dano').value = row.damage_type || '';
			document.getElementById('item_metal').value = row.metal || 0;
			document.getElementById('item_fuerza').value = row.strength_req || 0;
			document.getElementById('item_destreza').value = row.dexterity_req || 0;
			document.getElementById('item_img').value = row.image_url || '';
			document.getElementById('item_current_img').value = row.image_url || '';
			if (row.image_url) document.getElementById('item_img_preview').src = row.image_url;
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

function bindRows(){
	document.querySelectorAll('#itemsTbody [data-edit]').forEach(function(btn){
		btn.onclick = function(){
			openItemModal(parseInt(btn.getAttribute('data-edit') || '0', 10) || 0);
		};
	});
	document.querySelectorAll('#itemsTbody [data-del]').forEach(function(btn){
		btn.onclick = function(){
			const id = parseInt(btn.getAttribute('data-del') || '0', 10) || 0;
			if (!id) return;
			if (!confirm('¿Eliminar objeto?')) return;
			const fd = new FormData();
			fd.set('ajax', '1');
			fd.set('csrf', window.ADMIN_CSRF_TOKEN || '');
			fd.set('crud_action', 'delete');
			fd.set('id', String(id));
			request(endpointUrl(''), { method:'POST', body: fd, loadingEl: btn }).then(function(payload){
				const data = payload && payload.data ? payload.data : {};
				if (Array.isArray(data.rows)) renderRows(data.rows);
				if (Array.isArray(data.rowsFull)) itemsData = data.rowsFull;
				applyQuickFilter();
				if (window.HGAdminHttp && window.HGAdminHttp.notify) {
					window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Eliminado', 'ok');
				}
			}).catch(function(err){
				const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al borrar');
				alert(msg);
			});
		};
	});
}

function renderRows(rows){
	const tbody = document.getElementById('itemsTbody');
	if (!tbody) return;
	if (!rows || !rows.length) {
		tbody.innerHTML = '<tr><td colspan="5" class="adm-color-muted">(Sin objetos)</td></tr>';
		bindRows();
		return;
	}
	let html = '';
	rows.forEach(function(r){
		const id = parseInt(r.id || 0, 10) || 0;
		const typeName = nameById(itemTypes, r.item_type_id || 0);
		const originName = nameById(itemOrigins, r.bibliography_id || 0);
		const search = ((r.name || '') + ' ' + typeName + ' ' + originName).toLowerCase();
		html += '<tr data-search="' + esc(search) + '">'
			+ '<td>' + id + '</td>'
			+ '<td>' + esc(r.name || '') + '</td>'
			+ '<td>' + esc(typeName) + '</td>'
			+ '<td>' + esc(originName) + '</td>'
			+ '<td><button class="btn" type="button" data-edit="' + id + '">Editar</button> '
			+ '<button class="btn btn-red" type="button" data-del="' + id + '">Borrar</button></td>'
			+ '</tr>';
	});
	tbody.innerHTML = html;
	bindRows();
}

function applyQuickFilter(){
	const input = document.getElementById('quickFilterItems');
	if (!input) return;
	const q = (input.value || '').toLowerCase();
	document.querySelectorAll('#itemsTbody tr').forEach(function(tr){
		const name = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
		tr.style.display = name.indexOf(q) !== -1 ? '' : 'none';
	});
}

document.getElementById('item_img_file').addEventListener('change', function(){
	const f = this.files && this.files[0];
	if (!f) return;
	const url = URL.createObjectURL(f);
	document.getElementById('item_img_preview').src = url;
});

document.getElementById('itemForm').addEventListener('submit', function(ev){
	ev.preventDefault();
	if (itemEditor) {
		const html = itemEditor.root.innerHTML || '';
		const plain = (itemEditor.getText() || '').replace(/\s+/g,' ').trim();
		document.getElementById('item_description').value = plain ? html : '';
	}
	const fd = new FormData(this);
	fd.set('ajax', '1');
	request(endpointUrl(''), { method:'POST', body: fd, loadingEl: this }).then(function(payload){
		const data = payload && payload.data ? payload.data : {};
		if (Array.isArray(data.rows)) renderRows(data.rows);
		if (Array.isArray(data.rowsFull)) itemsData = data.rowsFull;
		closeItemModal();
		applyQuickFilter();
		if (window.HGAdminHttp && window.HGAdminHttp.notify) {
			window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok');
		}
	}).catch(function(err){
		const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al guardar');
		alert(msg);
	});
});

document.addEventListener('keydown', function(e){
	if (e.key === 'Escape') closeItemModal();
});
document.getElementById('quickFilterItems').addEventListener('input', applyQuickFilter);
bindRows();
</script>
<?php admin_panel_close(); ?>





