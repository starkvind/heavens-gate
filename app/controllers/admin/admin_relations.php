<?php
// admin_relations.php — Editor de relaciones entre personajes
if (!isset($link) || !$link) { die("Error de conexión a la base de datos."); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
admin_panel_open('Relaciones', '<button class="btn btn-green" type="button" onclick="openRelModal()">➕ Nueva relación</button>');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$tipos = ['Amigo','Aliado','Mentor','Protegido','Salvador','Amante','Pareja','Rival','Traidor','Extorsionador','Enemigo','Asesino','Padre','Madre','Hijo','Hermano','Abuelo','Tío','Primo','Superior','Subordinado','Amo','Creación','Vínculo'];
$tags  = ['amistad','conflicto','familia','alianza','otro'];
$arrows = ["to" => "➡️ Origen → Destino","from" => "⬅️ Destino → Origen","to,from" => "🔁 Doble dirección","" => "🚫 Sin flechas"];

?>
<link rel="stylesheet" href="/assets/css/admin/admin.relations.css">

<?php

// Datos
$personajes = [];
$rs = $link->query("SELECT id, nombre FROM fact_characters WHERE cronica NOT IN (2, 7) ORDER BY nombre ASC");
if ($rs) { while ($r = $rs->fetch_assoc()) { $personajes[] = $r; } $rs->close(); }
$personajesById = [];
foreach ($personajes as $p) { $personajesById[(int)$p['id']] = (string)$p['nombre']; }

$flash = [];

// Eliminar relación
if (isset($_GET['delete'])) {
	$id = (int)$_GET['delete'];
	if ($id > 0 && ($st = $link->prepare("DELETE FROM bridge_characters_relations WHERE id = ?"))) {
		$st->bind_param("i", $id);
		$st->execute();
		$st->close();
		$flash[] = ['type'=>'ok','msg'=>'Relación eliminada.'];
	}
}

// Crear / editar relación (modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rel']) && is_array($_POST['rel'])) {
	$mode = (string)($_POST['rel']['mode'] ?? '');
	$id = (int)($_POST['rel']['id'] ?? 0);
	$source = (int)($_POST['rel']['source_id'] ?? 0);
	$target = (int)($_POST['rel']['target_id'] ?? 0);
	$type = (string)($_POST['rel']['relation_type'] ?? '');
	$tag = (string)($_POST['rel']['tag'] ?? '');
	$importance = (int)($_POST['rel']['importance'] ?? 0);
	$description = (string)($_POST['rel']['description'] ?? '');
	$ar = (string)($_POST['rel']['arrows'] ?? '');

	if ($source > 0 && $target > 0 && $type !== '') {
		if ($mode === 'create') {
			$st = $link->prepare("INSERT INTO bridge_characters_relations (source_id, target_id, relation_type, tag, importance, description, arrows) VALUES (?,?,?,?,?,?,?)");
			if ($st) {
				$st->bind_param("iississ", $source, $target, $type, $tag, $importance, $description, $ar);
				$st->execute();
				$st->close();
				$flash[] = ['type'=>'ok','msg'=>'Relación creada.'];
			}
		} elseif ($mode === 'edit' && $id > 0) {
			$st = $link->prepare("UPDATE bridge_characters_relations SET source_id=?, target_id=?, relation_type=?, tag=?, importance=?, description=?, arrows=? WHERE id=?");
			if ($st) {
				$st->bind_param("iississi", $source, $target, $type, $tag, $importance, $description, $ar, $id);
				$st->execute();
				$st->close();
				$flash[] = ['type'=>'ok','msg'=>'Relación actualizada.'];
			}
		}
	}
}

// Paginación simple
// Relaciones completas (paginaci?n en cliente)
$relaciones = [];
$rs = $link->query("SELECT * FROM bridge_characters_relations ORDER BY id DESC");
if ($rs) { while ($r = $rs->fetch_assoc()) { $relaciones[] = $r; } $rs->close(); }
?>

<?php if (!empty($flash)): ?>
	<div class="flash">
		<?php foreach ($flash as $m):
			$cl = $m['type']==='ok'?'ok':($m['type']==='error'?'err':'info'); ?>
			<div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<div class="bar" style="display:flex; gap:8px; align-items:center; margin:8px 0 12px;">
	<input class="inp" id="quickFilterRelations" type="text" placeholder="Buscar relacion...">
</div>

<table class="table">
	<thead>
		<tr>
			<th style="width:60px;">ID</th>
			<th>Origen</th>
			<th>Destino</th>
			<th>Tipo</th>
			<th>Tag</th>
			<th>Flechas</th>
			<th style="width:160px;">Acciones</th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ($relaciones as $r): ?>
		<?php
			$srcName = $personajesById[(int)$r['source_id']] ?? '';
			$dstName = $personajesById[(int)$r['target_id']] ?? '';
			$relName = (string)($r['relation_type'] ?? '');
			$relTag  = (string)($r['tag'] ?? '');
			$filterText = trim($srcName.' '.$dstName.' '.$relName.' '.$relTag);
		?>
		<tr data-name="<?= h($filterText) ?>">
			<td><?= (int)$r['id'] ?></td>
			<td><?= h($srcName) ?></td>
			<td><?= h($dstName) ?></td>
			<td><?= h($relName) ?></td>
			<td><?= h(ucfirst($relTag)) ?></td>
			<td><?= h($arrows[$r['arrows'] ?? ''] ?? '') ?></td>
			<td>
				<button
					class="btn"
					type="button"
					data-id="<?= (int)$r['id'] ?>"
					data-source="<?= (int)$r['source_id'] ?>"
					data-target="<?= (int)$r['target_id'] ?>"
					data-type="<?= h($relName) ?>"
					data-tag="<?= h($relTag) ?>"
					data-arrows="<?= h((string)($r['arrows'] ?? '')) ?>"
					data-importance="<?= (int)($r['importance'] ?? 0) ?>"
					data-description="<?= h((string)($r['description'] ?? '')) ?>"
					onclick="openRelModal(this)"
				>Editar</button>
				<a class="btn btn-red" href="/talim?s=admin_relations&delete=<?= (int)$r['id'] ?>" onclick="return confirm('¿Eliminar relación?');">Borrar</a>
			</td>
		</tr>
	<?php endforeach; ?>
	<?php if (empty($relaciones)): ?>
		<tr><td colspan="7" style="color:#bbb;">(Sin relaciones)</td></tr>
	<?php endif; ?>
	</tbody>
</table>

<div class="pager" id="relPager"></div>

<!-- Modal nueva relación -->
<div class="modal-back" id="relModal" style="display:none;">
	<div class="modal" style="width:min(720px,96vw);">
		<h3 id="relModalTitle">➕ Nueva relación</h3>
		<form method="post" id="relForm">
			<input type="hidden" name="rel[mode]" id="rel_mode" value="create">
			<input type="hidden" name="rel[id]" id="rel_id" value="0">
			<div class="grid" style="grid-template-columns:repeat(2, minmax(220px,1fr));">
				<label>Origen
					<select class="select" name="rel[source_id]" id="rel_source">
						<?php foreach ($personajes as $p): ?>
							<option value="<?= (int)$p['id'] ?>"><?= h($p['nombre']) ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Destino
					<select class="select" name="rel[target_id]" id="rel_target">
						<?php foreach ($personajes as $p): ?>
							<option value="<?= (int)$p['id'] ?>"><?= h($p['nombre']) ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Tipo
					<select class="select" name="rel[relation_type]" id="rel_type">
						<?php foreach ($tipos as $t): ?>
							<option value="<?= h($t) ?>"><?= h($t) ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Tag
					<select class="select" name="rel[tag]" id="rel_tag">
						<?php foreach ($tags as $t): ?>
							<option value="<?= h($t) ?>"><?= ucfirst(h($t)) ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Flechas
					<select class="select" name="rel[arrows]" id="rel_arrows">
						<?php foreach ($arrows as $val => $label): ?>
							<option value="<?= h($val) ?>"><?= h($label) ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Importancia (0-10)
					<input class="inp" type="number" name="rel[importance]" id="rel_importance" min="0" max="10" value="1">
				</label>
				<label style="grid-column:1 / -1;">Descripción
					<textarea class="ta" name="rel[description]" id="rel_description" rows="3"></textarea>
				</label>
			</div>
			<div class="modal-actions">
				<button class="btn btn-green" type="submit">Crear</button>
				<button class="btn" type="button" onclick="closeRelModal()">Cancelar</button>
			</div>
		</form>
	</div>
</div>

<script>
function openRelModal(btn){
	const modal = document.getElementById('relModal');
	const title = document.getElementById('relModalTitle');
	const mode = document.getElementById('rel_mode');
	const id = document.getElementById('rel_id');
	const src = document.getElementById('rel_source');
	const dst = document.getElementById('rel_target');
	const type = document.getElementById('rel_type');
	const tag = document.getElementById('rel_tag');
	const arrows = document.getElementById('rel_arrows');
	const imp = document.getElementById('rel_importance');
	const desc = document.getElementById('rel_description');

	if (btn && btn.dataset && btn.dataset.id) {
		title.textContent = 'Editar relación';
		mode.value = 'edit';
		id.value = btn.dataset.id || '0';
		if (src) src.value = btn.dataset.source || '0';
		if (dst) dst.value = btn.dataset.target || '0';
		if (type) type.value = btn.dataset.type || '';
		if (tag) tag.value = btn.dataset.tag || '';
		if (arrows) arrows.value = btn.dataset.arrows || '';
		if (imp) imp.value = btn.dataset.importance || '0';
		if (desc) desc.value = btn.dataset.description || '';
	} else {
		title.textContent = '➕ Nueva relación';
		mode.value = 'create';
		id.value = '0';
		if (src) src.value = '0';
		if (dst) dst.value = '0';
		if (type) type.value = '';
		if (tag) tag.value = '';
		if (arrows) arrows.value = '';
		if (imp) imp.value = '1';
		if (desc) desc.value = '';
	}

	modal.style.display = 'flex';
}
function closeRelModal(){ document.getElementById('relModal').style.display = 'none'; }
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeRelModal(); });
</script>




<script>
(function(){
	const input = document.getElementById('quickFilterRelations');
	const pager = document.getElementById('relPager');
	const rows = Array.from(document.querySelectorAll('table.table tbody tr'));
	const pageSize = 50;
	let currentPage = 1;
	let visibleRows = rows;

	function applyFilter(){
		const q = (input && input.value ? input.value : '').toLowerCase();
		visibleRows = rows.filter(function(tr){
			const name = (tr.getAttribute('data-name') || tr.textContent || '').toLowerCase();
			return name.indexOf(q) !== -1;
		});
		currentPage = 1;
		renderRelPage();
	}

	function renderRelPage(){
		const totalPages = Math.max(1, Math.ceil(visibleRows.length / pageSize));
		if (currentPage > totalPages) currentPage = totalPages;
		const start = (currentPage - 1) * pageSize;
		const end = start + pageSize;
		rows.forEach(tr => { tr.style.display = 'none'; });
		visibleRows.slice(start, end).forEach(tr => { tr.style.display = ''; });
		renderPager(totalPages);
	}

	function renderPager(totalPages){
		if (!pager) return;
		let html = '';
		for (let i = 1; i <= totalPages; i++) {
			const cls = (i === currentPage) ? 'cur' : '';
			html += '<a class="' + cls + '" href="#" data-page="' + i + '">' + i + '</a>';
		}
		pager.innerHTML = html;
		pager.querySelectorAll('a').forEach(a => {
			a.addEventListener('click', function(e){
				e.preventDefault();
				currentPage = parseInt(this.getAttribute('data-page'), 10) || 1;
				renderRelPage();
			});
		});
	}

	if (input) input.addEventListener('input', applyFilter);
	applyFilter();
})();
</script>
<?php admin_panel_close(); ?>
