<?php
// admin_news.php — CRUD Noticias (fact_admin_posts)
if (!isset($link) || !$link) { die("Error de conexión a la base de datos."); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
$actions = '<span class="adm-flex-right-8">'
	. '<button class="btn btn-green" type="button" onclick="openNewsModal()">+ Nueva noticia</button>'
	. '<label class="adm-text-left">Filtro r&aacute;pido '
	. '<input class="inp" type="text" id="quickFilterNews" placeholder="En esta p&aacute;gina..."></label>'
	. '</span>';
admin_panel_open('Noticias', $actions);
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$flash = [];

// Borrar
if (isset($_GET['delete'])) {
	$id = (int)$_GET['delete'];
	if ($id > 0 && ($st = $link->prepare("DELETE FROM fact_admin_posts WHERE id = ?"))) {
		$st->bind_param("i", $id);
		$st->execute();
		$st->close();
		$flash[] = ['type'=>'ok','msg'=>'Noticia eliminada.'];
	}
}

// Crear / actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_news'])) {
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

// Prefill edición
// Listado
$rows = [];
$rs = $link->query("SELECT id, author, title, posted_at FROM fact_admin_posts ORDER BY id DESC");
if ($rs) { while ($r = $rs->fetch_assoc()) { $rows[] = $r; } $rs->close(); }

// Datos completos para edición en modal
$rowsFull = [];
$rs = $link->query("SELECT id, author, title, message FROM fact_admin_posts ORDER BY id DESC");
if ($rs) { while ($r = $rs->fetch_assoc()) { $rowsFull[] = $r; } $rs->close(); }
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
						<textarea class="ta adm-hidden" name="message" id="news_mensaje" rows="10" required></textarea>
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
	<tbody>
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
				<button class="btn" type="button" onclick="openNewsModal(<?= (int)$r['id'] ?>)">Editar</button>
				<a class="btn btn-red" href="/talim?s=admin_news&delete=<?= (int)$r['id'] ?>" onclick="return confirm('¿Eliminar noticia?');">Borrar</a>
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
<script>
const newsData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let newsEditor = null;

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

document.getElementById('newsForm').addEventListener('submit', function(){
	if (newsEditor) {
		const html = newsEditor.root.innerHTML || '';
		const plain = (newsEditor.getText() || '').replace(/\s+/g,' ').trim();
		document.getElementById('news_mensaje').value = plain ? html : '';
	}
});

document.addEventListener('keydown', function(e){
	if (e.key === 'Escape') closeNewsModal();
});
</script>

<script>
(function(){
	const input = document.getElementById('quickFilterNews');
	if (!input) return;
	input.addEventListener('input', function(){
		const q = (this.value || '').toLowerCase();
		document.querySelectorAll('#tablaNews tbody tr').forEach(function(tr){
			const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase();
			tr.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
		});
	});
})();
</script>

<?php admin_panel_close(); ?>




