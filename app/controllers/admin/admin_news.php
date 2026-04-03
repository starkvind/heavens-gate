<?php
// admin_news.php - CRUD Noticias (fact_admin_posts)
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
$isAjaxRequest = (
	((string)($_GET['ajax'] ?? '') === '1')
	|| (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);
$actions = '<span class="adm-flex-right-8">'
	. '<button class="btn btn-green" type="button" onclick="openNewsModal()">+ Nueva noticia</button>'
	. '<label class="adm-text-left">Filtro r&aacute;pido '
	. '<input class="inp" type="text" id="quickFilterNews" placeholder="En esta p&aacute;gina..."></label>'
	. '</span>';
if (!$isAjaxRequest) {
	admin_panel_open('Noticias', $actions);
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$flash = [];
$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_news';
if (function_exists('hg_admin_ensure_csrf_token')) {
	$CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
	if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
		$_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
	}
	$CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function news_csrf_ok(): bool {
	$payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
	$t = function_exists('hg_admin_extract_csrf_token')
		? hg_admin_extract_csrf_token($payload)
		: (string)($_POST['csrf'] ?? '');
	if (function_exists('hg_admin_csrf_valid')) {
		return hg_admin_csrf_valid($t, 'csrf_admin_news');
	}
	return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_news']) && hash_equals($_SESSION['csrf_admin_news'], $t);
}

// Borrar
if (!$isAjaxRequest && isset($_GET['delete'])) {
	$flash[] = ['type'=>'error','msg'=>'El borrado por URL ha sido desactivado por seguridad. Usa el boton Borrar.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['crud_action'] ?? '') === 'delete') {
	if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
		hg_admin_require_session(true);
	}
	if (!news_csrf_ok()) {
		$flash[] = ['type'=>'error','msg'=>'CSRF invalido. Recarga la pagina.'];
	} else {
		$id = (int)($_POST['id'] ?? 0);
		if ($id <= 0) {
			$flash[] = ['type'=>'error','msg'=>'ID invalido para borrar.'];
		} elseif ($st = $link->prepare("DELETE FROM fact_admin_posts WHERE id = ?")) {
			$st->bind_param("i", $id);
			if ($st->execute()) {
				$flash[] = ['type'=>'ok','msg'=>'Noticia eliminada.'];
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_news'])) {
	if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
		hg_admin_require_session(true);
	}
	if (!news_csrf_ok()) {
		$flash[] = ['type'=>'error','msg'=>'CSRF invalido. Recarga la pagina.'];
	} else {
	$id = (int)($_POST['id'] ?? 0);
	$autor = trim((string)($_POST['author'] ?? ''));
	$titulo = trim((string)($_POST['titulo'] ?? ''));
	$mensaje = (string)($_POST['message'] ?? '');
	$mensaje = hg_mentions_convert($link, $mensaje);

	if ($autor === '' || $titulo === '' || $mensaje === '') {
		$flash[] = ['type'=>'error','msg'=>'Autor, título y mensaje son obligatorios.'];
	} else {
		if ($id > 0) {
			$st = $link->prepare("UPDATE fact_admin_posts SET author=?, title=?, message=?, posted_at=NOW() WHERE id=?");
			if ($st) {
				$st->bind_param("sssi", $autor, $titulo, $mensaje, $id);
				$st->execute();
			hg_update_pretty_id_if_exists($link, 'fact_admin_posts', $id, $titulo);
				$st->close();
				$flash[] = ['type'=>'ok','msg'=>'Noticia actualizada.'];
			}
		} else {
			$st = $link->prepare("INSERT INTO fact_admin_posts (author, title, message, posted_at) VALUES (?,?,?,NOW())");
			if ($st) {
				$st->bind_param("sss", $autor, $titulo, $mensaje);
				$st->execute();
			$newId = (int)$link->insert_id;
			hg_update_pretty_id_if_exists($link, 'fact_admin_posts', $newId, $titulo);
				$st->close();
				$flash[] = ['type'=>'ok','msg'=>'Noticia creada.'];
			}
		}
	}
}
}

// Prefill edición
// Listado
$rows = [];
$rs = $link->query("SELECT id, author, title, posted_at FROM fact_admin_posts ORDER BY id DESC");
if ($rs) { while ($r = $rs->fetch_assoc()) { $rows[] = $r; } $rs->close(); }

// Datos completos para edición en modal
$rowsFull = [];
$rs = $link->query("SELECT id, author, title, message FROM fact_admin_posts ORDER BY id DESC");
if ($rs) { while ($r = $rs->fetch_assoc()) { $rowsFull[] = $r; } $rs->close(); }

if ($isAjaxRequest && (string)($_GET['ajax_mode'] ?? '') === 'list') {
	if (function_exists('hg_admin_require_session')) {
		hg_admin_require_session(true);
	}
	if (function_exists('hg_admin_json_success')) {
		hg_admin_json_success(['rows' => $rows, 'rowsFull' => $rowsFull, 'total' => count($rows)], 'Listado');
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
	&& (isset($_POST['save_news']) || (string)($_POST['crud_action'] ?? '') === 'delete')
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
		hg_admin_json_success(['rows' => $rows, 'rowsFull' => $rowsFull, 'messages' => $messages], $okMsg);
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

<div class="modal-back" id="newsModal">
	<div class="modal">
		<h3 id="newsModalTitle">Nueva noticia</h3>
		<form method="post" id="newsForm">
			<input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
			<input type="hidden" name="save_news" value="1">
			<input type="hidden" name="id" id="news_id" value="">
			<div class="modal-body">
				<div class="adm-grid-1-2">
					<label>Autor</label>
					<input class="inp" type="text" name="author" id="news_autor" required>
					<label>Título</label>
					<input class="inp" type="text" name="titulo" id="news_titulo" required>
					<label>Mensaje</label>
					<div>
						<div id="news_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
						<div id="news_editor" class="ql-container ql-snow"></div>
						<textarea class="ta adm-hidden" name="message" id="news_mensaje" rows="10"></textarea>
					</div>
				</div>
			</div>
			<div class="modal-actions">
				<button class="btn btn-green" type="submit">Guardar</button>
				<button class="btn" type="button" onclick="closeNewsModal()">Cancelar</button>
			</div>
		</form>
	</div>
</div>

<table class="table" id="tablaNews">
	<thead>
		<tr>
			<th class="adm-w-60">ID</th>
			<th>Título</th>
			<th>Autor</th>
			<th>Fecha</th>
			<th class="adm-w-160">Acciones</th>
		</tr>
	</thead>
	<tbody id="newsTbody">
	<?php foreach ($rows as $r): ?>
		<?php
			$search = trim((string)($r['title'] ?? '') . ' ' . (string)($r['author'] ?? '') . ' ' . (string)($r['posted_at'] ?? ''));
			if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
			else { $search = strtolower($search); }
		?>
		<tr data-search="<?= h($search) ?>">
			<td><?= (int)$r['id'] ?></td>
			<td><?= h($r['title']) ?></td>
			<td><?= h($r['author']) ?></td>
			<td><?= h($r['posted_at']) ?></td>
			<td>
				<button class="btn" type="button" data-edit="<?= (int)$r['id'] ?>">Editar</button>
				<button class="btn btn-red" type="button" data-del="<?= (int)$r['id'] ?>">Borrar</button>
			</td>
		</tr>
	<?php endforeach; ?>
	<?php if (empty($rows)): ?>
		<tr><td colspan="5" class="adm-color-muted">(Sin noticias)</td></tr>
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
let newsData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
let newsEditor = null;

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
	url.searchParams.set('s', 'admin_news');
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

function ensureEditor(){
	if (!newsEditor && window.Quill) {
		newsEditor = new Quill('#news_editor', {
			theme: 'snow',
			modules: { toolbar: '#news_toolbar' }
		});
		if (window.hgMentions) { window.hgMentions.attachQuill(newsEditor, { types: ['character','season','episode','organization','group','gift','rite','totem','discipline','item','trait','background','merit','flaw','merydef','doc'] }); }
	}
}

function openNewsModal(id = null){
	ensureEditor();
	const modal = document.getElementById('newsModal');
	document.getElementById('news_id').value = '';
	document.getElementById('news_autor').value = '';
	document.getElementById('news_titulo').value = '';
	document.getElementById('news_mensaje').value = '';
	if (newsEditor) newsEditor.root.innerHTML = '';

	if (id) {
		const row = newsData.find(r => parseInt(r.id,10) === parseInt(id,10));
		if (row) {
			document.getElementById('newsModalTitle').textContent = 'Editar noticia';
			document.getElementById('news_id').value = row.id;
			document.getElementById('news_autor').value = row.author || '';
			document.getElementById('news_titulo').value = row.title || '';
			const msg = row.message || '';
			document.getElementById('news_mensaje').value = msg;
			if (newsEditor) newsEditor.root.innerHTML = msg;
		}
	} else {
		document.getElementById('newsModalTitle').textContent = 'Nueva noticia';
	}
	modal.style.display = 'flex';
}

function closeNewsModal(){
	document.getElementById('newsModal').style.display = 'none';
}

function bindRows(){
	document.querySelectorAll('#newsTbody [data-edit]').forEach(function(btn){
		btn.onclick = function(){ openNewsModal(parseInt(btn.getAttribute('data-edit') || '0', 10) || 0); };
	});
	document.querySelectorAll('#newsTbody [data-del]').forEach(function(btn){
		btn.onclick = function(){
			const id = parseInt(btn.getAttribute('data-del') || '0', 10) || 0;
			if (!id) return;
			if (!confirm('¿Eliminar noticia?')) return;
			const fd = new FormData();
			fd.set('ajax', '1');
			fd.set('csrf', window.ADMIN_CSRF_TOKEN || '');
			fd.set('crud_action', 'delete');
			fd.set('id', String(id));
			request(endpointUrl(''), { method:'POST', body: fd, loadingEl: btn }).then(function(payload){
				const data = payload && payload.data ? payload.data : {};
				if (Array.isArray(data.rows)) renderRows(data.rows);
				if (Array.isArray(data.rowsFull)) newsData = data.rowsFull;
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
	const tbody = document.getElementById('newsTbody');
	if (!tbody) return;
	if (!rows || !rows.length) {
		tbody.innerHTML = '<tr><td colspan="5" class="adm-color-muted">(Sin noticias)</td></tr>';
		bindRows();
		return;
	}
	let html = '';
	rows.forEach(function(r){
		const id = parseInt(r.id || 0, 10) || 0;
		const title = String(r.title || '');
		const author = String(r.author || '');
		const postedAt = String(r.posted_at || '');
		const search = (title + ' ' + author + ' ' + postedAt).toLowerCase();
		html += '<tr data-search="' + esc(search) + '">'
			+ '<td>' + id + '</td>'
			+ '<td>' + esc(title) + '</td>'
			+ '<td>' + esc(author) + '</td>'
			+ '<td>' + esc(postedAt) + '</td>'
			+ '<td><button class="btn" type="button" data-edit="' + id + '">Editar</button> '
			+ '<button class="btn btn-red" type="button" data-del="' + id + '">Borrar</button></td>'
			+ '</tr>';
	});
	tbody.innerHTML = html;
	bindRows();
}

function applyQuickFilter(){
	const input = document.getElementById('quickFilterNews');
	if (!input) return;
	const q = (input.value || '').toLowerCase();
	document.querySelectorAll('#newsTbody tr').forEach(function(tr){
		const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
		tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
	});
}

document.getElementById('newsForm').addEventListener('submit', function(ev){
	ev.preventDefault();
	if (newsEditor) {
		const html = newsEditor.root.innerHTML || '';
		const plain = (newsEditor.getText() || '').replace(/\s+/g,' ').trim();
		document.getElementById('news_mensaje').value = plain ? html : '';
	}
	if (!String(document.getElementById('news_mensaje').value || '').trim()) {
		alert('El mensaje es obligatorio.');
		return;
	}
	const fd = new FormData(this);
	fd.set('ajax', '1');
	request(endpointUrl(''), { method:'POST', body: fd, loadingEl: this }).then(function(payload){
		const data = payload && payload.data ? payload.data : {};
		if (Array.isArray(data.rows)) renderRows(data.rows);
		if (Array.isArray(data.rowsFull)) newsData = data.rowsFull;
		closeNewsModal();
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
	if (e.key === 'Escape') closeNewsModal();
});
document.getElementById('quickFilterNews').addEventListener('input', applyQuickFilter);
bindRows();
</script>

<?php admin_panel_close(); ?>





