<?php
// admin_relations.php - Editor de relaciones entre personajes
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }
include_once(__DIR__ . '/../../helpers/admin_auth.php');
hg_admin_session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function relations_is_ajax(): bool { return function_exists('hg_admin_is_ajax_request') ? hg_admin_is_ajax_request() : false; }
function relations_success(string $message, array $data = []): void {
	if (function_exists('hg_admin_json_success')) {
		hg_admin_json_success($data, $message);
	}
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode(['ok' => true, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}
function relations_error(string $message, int $status = 400, array $errors = [], array $data = []): void {
	if (function_exists('hg_admin_json_error')) {
		hg_admin_json_error($message, $status, $errors, $data);
	}
	http_response_code($status);
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode(['ok' => false, 'message' => $message, 'errors' => $errors, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

$tipos = ['Amigo','Aliado','Mentor','Protegido','Salvador','Amante','Pareja','Rival','Traidor','Extorsionador','Enemigo','Asesino','Padre','Madre','Hijo','Hermano','Abuelo','Tio','Primo','Superior','Subordinado','Amo','Creacion','Vinculo'];
$tags  = ['amistad','conflicto','familia','alianza','otro'];
$arrows = ["to" => "Origen -> Destino","from" => "Destino -> Origen","to,from" => "Doble direccion","" => "Sin flechas"];

// Datos
$personajes = [];
$rs = $link->query("SELECT id, name FROM fact_characters WHERE chronicle_id NOT IN (2, 7) ORDER BY name ASC");
if ($rs) { while ($r = $rs->fetch_assoc()) { $personajes[] = $r; } $rs->close(); }
$personajesById = [];
foreach ($personajes as $p) { $personajesById[(int)$p['id']] = (string)$p['name']; }

$flash = [];
$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_relations';
if (function_exists('hg_admin_ensure_csrf_token')) {
	$CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
	if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
		$_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
	}
	$CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function relations_csrf_ok(): bool {
	$payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
	$t = function_exists('hg_admin_extract_csrf_token')
		? hg_admin_extract_csrf_token($payload)
		: (string)($_POST['csrf'] ?? '');
	if (function_exists('hg_admin_csrf_valid')) {
		return hg_admin_csrf_valid($t, 'csrf_admin_relations');
	}
	return is_string($t) && $t !== '' && isset($_SESSION['csrf_admin_relations']) && hash_equals($_SESSION['csrf_admin_relations'], $t);
}

// Eliminar relacion
if (isset($_GET['delete'])) {
	$flash[] = ['type'=>'error','msg'=>'El borrado por URL ha sido desactivado por seguridad. Usa el boton Borrar.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['crud_action'] ?? '') === 'delete') {
	if (!relations_csrf_ok()) {
		if (relations_is_ajax()) { relations_error('CSRF invalido. Recarga la pagina.', 403, ['csrf' => 'invalid']); }
		$flash[] = ['type'=>'error','msg'=>'CSRF invalido. Recarga la pagina.'];
	} else {
		$id = (int)($_POST['id'] ?? 0);
		if ($id <= 0) {
			if (relations_is_ajax()) { relations_error('ID de relacion invalido.', 422, ['id' => 'invalid']); }
			$flash[] = ['type'=>'error','msg'=>'ID de relacion invalido.'];
		}
		if ($id > 0 && ($st = $link->prepare("DELETE FROM bridge_characters_relations WHERE id = ?"))) {
			$st->bind_param("i", $id);
			$st->execute();
			$st->close();
			if (relations_is_ajax()) { relations_success('Relacion eliminada.', ['id' => $id]); }
			$flash[] = ['type'=>'ok','msg'=>'Relacion eliminada.'];
		}
	}
}

// Crear / editar relacion (modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rel']) && is_array($_POST['rel'])) {
	if (!relations_csrf_ok()) {
		if (relations_is_ajax()) { relations_error('CSRF invalido. Recarga la pagina.', 403, ['csrf' => 'invalid']); }
		$flash[] = ['type'=>'error','msg'=>'CSRF invalido. Recarga la pagina.'];
	} else {
		$mode = (string)($_POST['rel']['mode'] ?? '');
		$id = (int)($_POST['rel']['id'] ?? 0);
		$source = (int)($_POST['rel']['source_id'] ?? 0);
		$target = (int)($_POST['rel']['target_id'] ?? 0);
		$type = (string)($_POST['rel']['relation_type'] ?? '');
		$tag = (string)($_POST['rel']['tag'] ?? '');
		$importance = (int)($_POST['rel']['importance'] ?? 0);
		$description = (string)($_POST['rel']['description'] ?? '');
		$ar = (string)($_POST['rel']['arrows'] ?? '');

		if ($source <= 0 || $target <= 0 || $type === '') {
			if (relations_is_ajax()) {
				$errors = [];
				if ($source <= 0) $errors['source_id'] = 'required';
				if ($target <= 0) $errors['target_id'] = 'required';
				if ($type === '') $errors['relation_type'] = 'required';
				relations_error('Faltan campos obligatorios para la relacion.', 422, $errors);
			}
			$flash[] = ['type'=>'error','msg'=>'Faltan campos obligatorios para la relacion.'];
		} elseif ($source === $target) {
			if (relations_is_ajax()) { relations_error('Origen y destino no pueden ser el mismo personaje.', 422, ['target_id' => 'same_as_source']); }
			$flash[] = ['type'=>'error','msg'=>'Origen y destino no pueden ser el mismo personaje.'];
		} else {
			if ($mode === 'create') {
				$st = $link->prepare("INSERT INTO bridge_characters_relations (source_id, target_id, relation_type, tag, importance, description, arrows) VALUES (?,?,?,?,?,?,?)");
				if ($st) {
					$st->bind_param("iississ", $source, $target, $type, $tag, $importance, $description, $ar);
					$st->execute();
					$newId = (int)$st->insert_id;
					$st->close();
                    hg_content_touch_many($link, 'character', [$source, $target]);
					if (relations_is_ajax()) { relations_success('Relacion creada.', ['id' => $newId]); }
					$flash[] = ['type'=>'ok','msg'=>'Relacion creada.'];
				} elseif (relations_is_ajax()) {
					relations_error('No se pudo crear la relacion.', 500, ['db' => 'insert_prepare_failed']);
				}
			} elseif ($mode === 'edit' && $id > 0) {
				$st = $link->prepare("UPDATE bridge_characters_relations SET source_id=?, target_id=?, relation_type=?, tag=?, importance=?, description=?, arrows=? WHERE id=?");
				if ($st) {
					$st->bind_param("iississi", $source, $target, $type, $tag, $importance, $description, $ar, $id);
					$st->execute();
					$st->close();
                    hg_content_touch_many($link, 'character', [$source, $target]);
					if (relations_is_ajax()) { relations_success('Relacion actualizada.', ['id' => $id]); }
					$flash[] = ['type'=>'ok','msg'=>'Relacion actualizada.'];
				} elseif (relations_is_ajax()) {
					relations_error('No se pudo actualizar la relacion.', 500, ['db' => 'update_prepare_failed']);
				}
			} else {
				if (relations_is_ajax()) { relations_error('Modo de guardado invalido.', 422, ['mode' => 'invalid']); }
				$flash[] = ['type'=>'error','msg'=>'Modo de guardado invalido.'];
			}
		}
	}
}

// Paginacion simple
// Relaciones completas (paginaci?n en cliente)
$relaciones = [];
$rs = $link->query("SELECT * FROM bridge_characters_relations ORDER BY id DESC");
if ($rs) { while ($r = $rs->fetch_assoc()) { $relaciones[] = $r; } $rs->close(); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
admin_panel_open('Relaciones', '<button class="btn btn-green" type="button" onclick="openRelModal()">+ Nueva relacion</button>');
?>

<link rel="stylesheet" href="/assets/vendor/select2/select2.min.4.1.0.css">
<script src="/assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="/assets/vendor/select2/select2.min.4.1.0.js"></script>
<style>
#relModal{
	--adm-s2-bg: #000033;
	--adm-s2-color: #ffffff;
	--adm-s2-border: #333333;
	--adm-s2-hover: #001199;
	--adm-s2-selected: #00105f;
}
#relModal .select2-dropdown{
	background: var(--adm-s2-bg) !important;
	border: 1px solid var(--adm-s2-border) !important;
	color: var(--adm-s2-color) !important;
}
#relModal .select2-results__option{
	background: transparent !important;
	color: var(--adm-s2-color) !important;
}
#relModal .select2-container--default .select2-results__option--selected{
	background: var(--adm-s2-selected) !important;
	color: var(--adm-s2-color) !important;
}
#relModal .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable{
	background: var(--adm-s2-hover) !important;
	color: #ffffff !important;
}
#relModal .select2-container--default .select2-selection--single .select2-selection__arrow b{
	border-color: #9fd8ff transparent transparent transparent !important;
}
#relModal .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b{
	border-color: transparent transparent #9fd8ff transparent !important;
}
#relModal .select2-container{
	width: 100% !important;
}
.relations-toolbar{
	display:flex;
	flex-wrap:wrap;
	gap:10px;
	align-items:flex-end;
	margin-bottom:12px;
}
.relations-stats{
	display:flex;
	flex-wrap:wrap;
	gap:8px;
}
.relations-stat{
	padding:8px 12px;
	border:1px solid rgba(255,255,255,.14);
	border-radius:10px;
	background:rgba(255,255,255,.04);
	min-width:120px;
}
.relations-stat strong{
	display:block;
	font-size:18px;
	line-height:1.1;
}
.relations-stat span{
	opacity:.75;
	font-size:12px;
}
.relations-swap{
	display:flex;
	align-items:flex-end;
	justify-content:center;
}
.relations-swap .btn{
	width:100%;
}
.arrow-picker{
	display:grid;
	grid-template-columns:repeat(4, minmax(0, 1fr));
	gap:8px;
	margin-top:6px;
}
.arrow-choice{
	display:flex;
	align-items:center;
	justify-content:center;
	min-height:44px;
	border:1px solid rgba(255,255,255,.18);
	border-radius:10px;
	background:rgba(255,255,255,.05);
	color:#ffffff;
	cursor:pointer;
	font-size:22px;
	line-height:1;
	transition:background .15s ease, border-color .15s ease, transform .15s ease;
}
.arrow-choice:hover{
	background:rgba(255,255,255,.1);
	border-color:rgba(159,216,255,.45);
}
.arrow-choice.is-active{
	background:rgba(0,17,153,.42);
	border-color:#9fd8ff;
	transform:translateY(-1px);
}
.arrow-choice small{
	font-size:11px;
	opacity:.8;
	margin-left:6px;
}
</style>

<div id="relationsFlash">
<?php if (!empty($flash)): ?>
	<div class="flash">
		<?php foreach ($flash as $m):
			$cl = $m['type']==='ok'?'ok':($m['type']==='error'?'err':'info'); ?>
			<div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
</div>

<div class="relations-toolbar">
	<div class="bar adm-u-021">
		<input class="inp" id="quickFilterRelations" type="text" placeholder="Buscar por ID, personaje, tipo o tag...">
	</div>
	<div class="relations-stats">
		<div class="relations-stat">
			<strong id="relationsVisibleCount"><?= count($relaciones) ?></strong>
			<span>relaciones visibles</span>
		</div>
		<div class="relations-stat">
			<strong id="relationsTotalCount"><?= count($relaciones) ?></strong>
			<span>relaciones totales</span>
		</div>
	</div>
</div>

<table class="table">
	<thead>
		<tr>
			<th class="adm-w-60">ID</th>
			<th>Origen</th>
			<th>Destino</th>
			<th>Tipo</th>
			<th>Tag</th>
			<th>Flechas</th>
			<th class="adm-w-160">Acciones</th>
		</tr>
	</thead>
	<tbody id="relationsTableBody">
	<?php foreach ($relaciones as $r): ?>
		<?php
			$srcName = $personajesById[(int)$r['source_id']] ?? '';
			$dstName = $personajesById[(int)$r['target_id']] ?? '';
			$relName = (string)($r['relation_type'] ?? '');
			$relTag  = (string)($r['tag'] ?? '');
			$filterText = trim(
				(string)$r['id'].' '.
				(string)$r['source_id'].' '.$srcName.' '.
				(string)$r['target_id'].' '.$dstName.' '.
				$relName.' '.$relTag.' '.
				(string)($r['description'] ?? '')
			);
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
					data-edit="1"
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
				<form method="post" class="rel-delete-form" data-id="<?= (int)$r['id'] ?>" style="display:inline">
					<input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
					<input type="hidden" name="crud_action" value="delete">
					<input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
					<button class="btn btn-red" type="submit">Borrar</button>
				</form>
			</td>
		</tr>
	<?php endforeach; ?>
	<?php if (empty($relaciones)): ?>
		<tr><td colspan="7" class="adm-color-muted">(Sin relaciones)</td></tr>
	<?php endif; ?>
	</tbody>
</table>

<div class="pager" id="relPager"></div>

<!-- Modal nueva relacion -->
<div class="modal-back" id="relModal">
	<div class="modal adm-u-070">
		<h3 id="relModalTitle">+ Nueva relacion</h3>
		<form method="post" id="relForm">
			<input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
			<input type="hidden" name="rel[mode]" id="rel_mode" value="create">
			<input type="hidden" name="rel[id]" id="rel_id" value="0">
			<div class="grid adm-u-032">
				<label>Origen
					<select class="select" name="rel[source_id]" id="rel_source">
						<option value="0">- Selecciona personaje -</option>
						<?php foreach ($personajes as $p): ?>
							<option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?> (#<?= h($p['id']) ?>)</option>
						<?php endforeach; ?>
					</select>
				</label>
				<div class="relations-swap">
					<button class="btn" type="button" id="rel_swap_btn" onclick="swapRelCharacters()">Intercambiar</button>
				</div>
				<label>Destino
					<select class="select" name="rel[target_id]" id="rel_target">
						<option value="0">- Selecciona personaje -</option>
						<?php foreach ($personajes as $p): ?>
							<option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?> (#<?= h($p['id']) ?>)</option>
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
					<input type="hidden" name="rel[arrows]" id="rel_arrows" value="">
					<div class="arrow-picker" id="rel_arrows_picker" role="group" aria-label="Direccion de flechas">
						<button class="arrow-choice" type="button" data-arrow-value="to" title="Origen a destino" aria-label="Origen a destino">→</button>
						<button class="arrow-choice" type="button" data-arrow-value="from" title="Destino a origen" aria-label="Destino a origen">←</button>
						<button class="arrow-choice" type="button" data-arrow-value="to,from" title="Doble direccion" aria-label="Doble direccion">↔</button>
						<button class="arrow-choice" type="button" data-arrow-value="" title="Sin flechas" aria-label="Sin flechas">·</button>
					</div>
				</label>
				<label>Importancia (0-10)
					<input class="inp" type="number" name="rel[importance]" id="rel_importance" min="0" max="10" value="1">
				</label>
				<label class="adm-grid-full">Descripcion
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
function syncRelationsSelect2Palette(){
	const modal = document.getElementById('relModal');
	if (!modal) return;
	const probe = modal.querySelector('select.select, select');
	if (!probe) return;
	const cs = window.getComputedStyle(probe);
	modal.style.setProperty('--adm-s2-bg', (cs.backgroundColor || '').trim() || '#000033');
	modal.style.setProperty('--adm-s2-color', (cs.color || '').trim() || '#ffffff');
	modal.style.setProperty('--adm-s2-border', (cs.borderColor || '').trim() || '#333333');
}

function initRelationsSelect2(){
	if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return;
	const $modal = jQuery('#relModal');
	if (!$modal.length) return;
	syncRelationsSelect2Palette();

	$modal.find('select').each(function(){
		const $select = jQuery(this);
		if ($select.data('select2')) $select.select2('destroy');
		$select.select2({
			width: '100%',
			dropdownParent: $modal,
			minimumResultsForSearch: 0,
			matcher: function(params, data){
				const term = (params.term || '').trim().toLowerCase();
				if (!term) return data;
				const text = String(data.text || '').toLowerCase();
				return text.indexOf(term) !== -1 ? data : null;
			}
		});
	});
}

function refreshRelationsSelect(el){
	if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2 || !el) return;
	const $select = jQuery(el);
	$select.trigger('change.select2');
}

function setRelArrowValue(value){
	const input = document.getElementById('rel_arrows');
	const buttons = document.querySelectorAll('#rel_arrows_picker .arrow-choice');
	const nextValue = typeof value === 'string' ? value : '';
	if (input) input.value = nextValue;
	buttons.forEach(function(btn){
		const active = (btn.getAttribute('data-arrow-value') || '') === nextValue;
		btn.classList.toggle('is-active', active);
		btn.setAttribute('aria-pressed', active ? 'true' : 'false');
	});
}

function openRelModal(btn){
	const modal = document.getElementById('relModal');
	const title = document.getElementById('relModalTitle');
	const mode = document.getElementById('rel_mode');
	const id = document.getElementById('rel_id');
	const src = document.getElementById('rel_source');
	const dst = document.getElementById('rel_target');
	const type = document.getElementById('rel_type');
	const tag = document.getElementById('rel_tag');
	const imp = document.getElementById('rel_importance');
	const desc = document.getElementById('rel_description');

	if (btn && btn.dataset && btn.dataset.id) {
		title.textContent = 'Editar relacion';
		mode.value = 'edit';
		id.value = btn.dataset.id || '0';
		if (src) src.value = btn.dataset.source || '0';
		if (dst) dst.value = btn.dataset.target || '0';
		if (type) type.value = btn.dataset.type || '';
		if (tag) tag.value = btn.dataset.tag || '';
		setRelArrowValue(btn.dataset.arrows || '');
		if (imp) imp.value = btn.dataset.importance || '0';
		if (desc) desc.value = btn.dataset.description || '';
	} else {
		title.textContent = '+ Nueva relacion';
		mode.value = 'create';
		id.value = '0';
		if (src) src.value = '0';
		if (dst) dst.value = '0';
		if (type) type.value = '';
		if (tag) tag.value = '';
		setRelArrowValue('');
		if (imp) imp.value = '1';
		if (desc) desc.value = '';
	}

	modal.style.display = 'flex';
	initRelationsSelect2();
	refreshRelationsSelect(src);
	refreshRelationsSelect(dst);
}
function closeRelModal(){ document.getElementById('relModal').style.display = 'none'; }
function swapRelCharacters(){
	const src = document.getElementById('rel_source');
	const dst = document.getElementById('rel_target');
	if (!src || !dst) return;
	const currentSource = src.value;
	src.value = dst.value;
	dst.value = currentSource;
	refreshRelationsSelect(src);
	refreshRelationsSelect(dst);
}
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeRelModal(); });
</script>

<script>
(function(){
	const listUrl = window.location.pathname + window.location.search;
	const input = document.getElementById('quickFilterRelations');
	const pager = document.getElementById('relPager');
	const visibleCount = document.getElementById('relationsVisibleCount');
	const totalCount = document.getElementById('relationsTotalCount');
	const flashWrap = document.getElementById('relationsFlash');
	const relForm = document.getElementById('relForm');
	const saveButton = relForm ? relForm.querySelector('button[type="submit"]') : null;
	const pageSize = 50;
	let currentPage = 1;
	let rows = [];
	let visibleRows = [];

	function setFlashMessage(message, type){
		if (!flashWrap) return;
		if (!message) {
			flashWrap.innerHTML = '';
			return;
		}
		const cls = type === 'error' ? 'err' : (type === 'ok' ? 'ok' : 'info');
		flashWrap.innerHTML = '<div class="flash"><div class="' + cls + '">' + escapeHtml(message) + '</div></div>';
	}

	function escapeHtml(value){
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function collectRows(){
		rows = Array.from(document.querySelectorAll('#relationsTableBody tr'));
		visibleRows = rows.slice();
	}

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
		if (visibleCount) visibleCount.textContent = String(visibleRows.length);
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

	function bindDeleteForms(scope){
		const root = scope || document;
		root.querySelectorAll('form.rel-delete-form').forEach(function(form){
			if (form.dataset.bound === '1') return;
			form.dataset.bound = '1';
			form.addEventListener('submit', function(e){
				e.preventDefault();
				if (!window.confirm('Eliminar relacion?')) return;
				deleteRelation(form);
			});
		});
	}

	function bindEditButtons(scope){
		const root = scope || document;
		root.querySelectorAll('button[data-edit="1"]').forEach(function(btn){
			if (btn.dataset.bound === '1') return;
			btn.dataset.bound = '1';
			btn.addEventListener('click', function(){ openRelModal(btn); });
		});
	}

	function refreshRelationsList(){
		fetch(listUrl, {
			method: 'GET',
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		})
		.then(function(res){
			if (!res.ok) throw new Error('HTTP ' + res.status);
			return res.text();
		})
		.then(function(html){
			const doc = new DOMParser().parseFromString(html, 'text/html');
			const nextBody = doc.getElementById('relationsTableBody');
			const nextTotal = doc.getElementById('relationsTotalCount');
			if (!nextBody) throw new Error('No se pudo recargar la tabla de relaciones.');
			const currentBody = document.getElementById('relationsTableBody');
			if (currentBody) currentBody.innerHTML = nextBody.innerHTML;
			if (totalCount && nextTotal) totalCount.textContent = nextTotal.textContent;
			collectRows();
			bindEditButtons();
			bindDeleteForms();
			applyFilter();
		})
		.catch(function(err){
			console.error('[admin_relations] error recargando lista:', err);
			window.location.reload();
		});
	}

	function requestErrorMessage(err, fallback){
		if (window.HGAdminHttp && typeof window.HGAdminHttp.errorMessage === 'function') {
			return window.HGAdminHttp.errorMessage(err);
		}
		if (err && err.message) return err.message;
		return fallback || 'Error en la peticion';
	}

	function notifyResult(message, type){
		setFlashMessage(message, type);
		if (window.HGAdminHttp && typeof window.HGAdminHttp.notify === 'function') {
			window.HGAdminHttp.notify(message, type === 'error' ? 'error' : 'ok', 2400);
		}
	}

	function saveRelationAjax(){
		if (!relForm || relForm.dataset.saving === '1') return;
		relForm.dataset.saving = '1';
		const formData = new FormData(relForm);
		formData.set('ajax', '1');
		const req = (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function')
			? window.HGAdminHttp.request(listUrl, { method: 'POST', body: formData, loadingEl: saveButton || relForm })
			: fetch(listUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData }).then(function(res){ return res.json(); });

		Promise.resolve(req)
			.then(function(payload){
				notifyResult((payload && (payload.message || payload.msg)) || 'Relacion guardada.', 'ok');
				closeRelModal();
				refreshRelationsList();
			})
			.catch(function(err){
				notifyResult(requestErrorMessage(err, 'No se pudo guardar la relacion.'), 'error');
				console.error('[admin_relations] error guardando:', err);
			})
			.finally(function(){
				relForm.dataset.saving = '';
			});
	}

	function deleteRelation(form){
		const req = (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function')
			? window.HGAdminHttp.request(listUrl, { method: 'POST', body: new FormData(form), loadingEl: form.querySelector('button[type="submit"]') || form })
			: fetch(listUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) }).then(function(res){ return res.json(); });

		Promise.resolve(req)
			.then(function(payload){
				notifyResult((payload && (payload.message || payload.msg)) || 'Relacion eliminada.', 'ok');
				refreshRelationsList();
			})
			.catch(function(err){
				notifyResult(requestErrorMessage(err, 'No se pudo eliminar la relacion.'), 'error');
				console.error('[admin_relations] error borrando:', err);
			});
	}

	if (relForm) {
		relForm.addEventListener('submit', function(e){
			e.preventDefault();
			saveRelationAjax();
		});
	}
	document.querySelectorAll('#rel_arrows_picker .arrow-choice').forEach(function(btn){
		btn.addEventListener('click', function(){
			setRelArrowValue(btn.getAttribute('data-arrow-value') || '');
		});
	});
	if (input) input.addEventListener('input', applyFilter);
	setRelArrowValue('');
	collectRows();
	bindEditButtons();
	bindDeleteForms();
	applyFilter();
})();
</script>
<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<?php admin_panel_close(); ?>

