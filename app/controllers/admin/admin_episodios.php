<?php
	if (!$link) die("Error de conexión.");

	// Errores claros (quítalo en producción si molesta)
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/mentions.php');

	// Helper: normalizar fechas ('' -> NULL)
	function norm_date($v) {
		$v = trim((string)$v);
		return $v === '' ? null : $v;
	}

	// BORRADO EPISODIO
	if (isset($_GET['delete'])) {
		$id = (int)$_GET['delete'];
		$stmt = $link->prepare("DELETE FROM dim_chapters WHERE id = ?");
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$stmt->close();
		echo "<p style='color:red;'>❌ Episodio eliminado.</p>";
	}

	// EDICIÓN EPISODIO
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id']) && empty($_POST['add_capitulo'])) {
		$id          = (int)($_POST['edit_id'] ?? 0);
		$name        = trim($_POST['name'] ?? '');
		$capitulo    = (int)($_POST['capitulo'] ?? 0);
		$temporada   = (int)($_POST['temporada'] ?? 0);
		$fecha       = norm_date($_POST['fecha'] ?? '');
		$fechaIngame = norm_date($_POST['fecha_ingame'] ?? '');
		$sinopsis    = trim($_POST['sinopsis'] ?? '');
		$sinopsis    = hg_mentions_convert($link, $sinopsis);

		$stmt = $link->prepare("
			UPDATE dim_chapters 
			SET name = ?, capitulo = ?, temporada = ?, fecha = ?, fecha_ingame = ?, sinopsis = ?, updated_at = NOW()
			WHERE id = ?
		");

		$stmt->bind_param(
			"siisssi",
			$name,
			$capitulo,
			$temporada,
			$fecha,
			$fechaIngame,
			$sinopsis,
			$id
		);

		$stmt->execute();
		hg_update_pretty_id_if_exists($link, 'dim_chapters', $id, $name);
		$stmt->close();

		$filtroTemp = $_POST['filtro_temporada'] ?? '';
		$pagina     = $_POST['pagina_actual'] ?? 1;
		header("Location: /talim?s=admin_epis&updated=1&ft=".urlencode($filtroTemp)."&pg=".urlencode($pagina));
		exit;
	}

	// AÑADIR EPISODIO (NUEVO)
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['add_capitulo'])) {
		$name        = trim($_POST['name'] ?? '');
		$capitulo    = (int)($_POST['capitulo'] ?? 0);
		$temporada   = (int)($_POST['temporada'] ?? 0);
		$fecha       = norm_date($_POST['fecha'] ?? '');
		$fechaIngame = norm_date($_POST['fecha_ingame'] ?? '');
		$sinopsis    = trim($_POST['sinopsis'] ?? '');
		$sinopsis    = hg_mentions_convert($link, $sinopsis);

		// Nota: created_at es NOT NULL; usamos NOW() para evitar depender de DEFAULT.
		$stmt = $link->prepare("
			INSERT INTO dim_chapters (name, capitulo, temporada, fecha, fecha_ingame, sinopsis, created_at)
			VALUES (?, ?, ?, ?, ?, ?, NOW())
		");

		$stmt->bind_param(
			"siisss",
			$name,
			$capitulo,
			$temporada,
			$fecha,
			$fechaIngame,
			$sinopsis
		);

		$stmt->execute();
		$newId = (int)$link->insert_id;
		hg_update_pretty_id_if_exists($link, 'dim_chapters', $newId, $name);
		$stmt->close();

		$filtroTemp = $_POST['filtro_temporada'] ?? '';
		$pagina     = $_POST['pagina_actual'] ?? 1;
		header("Location: /talim?s=admin_epis&added=1&ft=".urlencode($filtroTemp)."&pg=".urlencode($pagina));
		exit;
	}

	// Obtener personajes
	$personajes = [];
	$resPJ = $link->query("SELECT id, nombre FROM fact_characters WHERE cronica NOT IN (2, 7) ORDER BY nombre ASC");
	while ($pj = $resPJ->fetch_assoc()) $personajes[] = $pj;

	// Obtener temporadas (para selects)
	$temporadasCatalogo = [];
	$resTemp = $link->query("SELECT numero, name FROM dim_seasons ORDER BY numero ASC");
	while ($t = $resTemp->fetch_assoc()) {
		$temporadasCatalogo[] = $t;
	}

	if (isset($_GET['updated'])) echo "<p style='color:deepskyblue;'>✔ Episodio actualizado correctamente.</p>";
	if (isset($_GET['added']))   echo "<p style='color:green;'>✔ Episodio creado correctamente.</p>";

	// OBTENER CAPÍTULOS
	$capitulos = [];
	$result = $link->query("
		SELECT ac.*, at.name AS temporada_name
		FROM dim_chapters ac
		LEFT JOIN dim_seasons at ON ac.temporada = at.numero
		ORDER BY ac.temporada ASC, ac.capitulo ASC
	");
	while ($row = $result->fetch_assoc()) $capitulos[] = $row;

	// Obtener relaciones de personajes (para render inicial; luego se refresca por AJAX)
	$relaciones = [];
	$resRel = $link->query("
		SELECT acp.id, acp.id_capitulo, acp.id_personaje, p.nombre
		FROM bridge_chapters_characters acp
		JOIN fact_characters p ON acp.id_personaje = p.id
	");
	while ($r = $resRel->fetch_assoc()) $relaciones[] = $r;

	$pageTitle2 = "Capítulos";
?>

<h2>📚 Gestión de Capítulos</h2>

<div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:12px;">
	<input type="text" id="filtro" placeholder="Buscar episodio..." style="font-size:12px;padding:4px;max-width:220px;"/>
	<select id="filtroTemporada" style="font-size:12px;padding:4px;max-width:220px;">
		<option value="">Todas las temporadas</option>
		<?php foreach ($temporadasCatalogo as $t): ?>
			<option value="<?= (int)$t['numero'] ?>"><?= htmlspecialchars($t['name']) ?></option>
		<?php endforeach; ?>
	</select>

	<button class="boton2" type="button" onclick="abrirPopupNuevo()">➕ Nuevo episodio</button>
</div>

<table id="tabla-episodios" class="tabla-pj">
  <thead>
    <tr class="pj-row-head">
      <th onclick="ordenar('temporada')">Temporada</th>
      <th onclick="ordenar('capitulo')">#</th>
      <th onclick="ordenar('name')">Nombre</th>
      <th onclick="ordenar('fecha')">Fecha</th>
      <th>Acciones</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>
<div id="paginacion-personajes" style="text-align:right; margin-top:10px;"></div>

<!-- POPUP EDITAR -->
<div id="popupEditar" class="popup-edit" style="display:none;">
	<form method="post" action="/talim?s=admin_epis" style="text-align:left;">
		<input type="hidden" id="add_relation_capitulo">
		<input type="hidden" name="edit_id" id="edit_id">

		<input type="hidden" name="filtro_temporada" id="form_filtro_temporada">
		<input type="hidden" name="pagina_actual" id="form_pagina_actual">

		<label>Nombre</label>
		<input type="text" name="name" id="edit_name" required>

		<label>Capítulo</label>
		<input type="number" name="capitulo" id="edit_capitulo" required>

		<label>Temporada</label>
		<select name="temporada" id="edit_temporada" required>
			<?php foreach ($temporadasCatalogo as $t): ?>
				<option value="<?= (int)$t['numero'] ?>"><?= htmlspecialchars($t['name']) ?></option>
			<?php endforeach; ?>
		</select>

		<label>Fecha</label>
		<input type="date" name="fecha" id="edit_fecha">

		<label>Fecha Ingame</label>
		<input type="date" name="fecha_ingame" id="edit_fecha_ingame">

		<label>Sinopsis</label>
		<textarea class="hg-mention-input" data-mentions="character,season,episode,organization,group,gift,rite,totem,discipline,item,trait,background,merit,flaw,merydef,doc" name="sinopsis" id="edit_sinopsis" rows="10"></textarea>

		<div style="margin-top:1em;">
			<h4>🎭 Participantes</h4>
			<div id="relacionesContainer" style="margin-bottom:0.5em;"></div>
			<div style="display:flex; gap:0.5em;">
				<select id="personaje_select" style="flex:1;">
					<option value="">-- Seleccionar personaje --</option>
					<?php foreach ($personajes as $pj): ?>
						<option value="<?= (int)$pj['id'] ?>"><?= htmlspecialchars($pj['nombre']) ?></option>
					<?php endforeach; ?>
				</select>
				<button class="boton2" type="button" onclick="agregarRelacion()">➕</button>
			</div>
		</div>

		<br />
		<button class="boton2" type="submit">Guardar</button>
		<button class="boton2" type="button" onclick="cerrarPopupEditar()">Cancelar</button>
	</form>
</div>

<!-- POPUP NUEVO -->
<div id="popupNuevo" class="popup-edit" style="display:none;">
	<form method="post" action="/talim?s=admin_epis" style="text-align:left;">
		<input type="hidden" name="add_capitulo" value="1">

		<input type="hidden" name="filtro_temporada" id="form_filtro_temporada_new">
		<input type="hidden" name="pagina_actual" id="form_pagina_actual_new">

		<label>Nombre</label>
		<input type="text" name="name" id="new_name" required>

		<label>Capítulo</label>
		<input type="number" name="capitulo" id="new_capitulo" required>

		<label>Temporada</label>
		<select name="temporada" id="new_temporada" required>
			<?php foreach ($temporadasCatalogo as $t): ?>
				<option value="<?= (int)$t['numero'] ?>"><?= htmlspecialchars($t['name']) ?></option>
			<?php endforeach; ?>
		</select>

		<label>Fecha</label>
		<input type="date" name="fecha" id="new_fecha">

		<label>Fecha Ingame</label>
		<input type="date" name="fecha_ingame" id="new_fecha_ingame">

		<label>Sinopsis</label>
		<textarea class="hg-mention-input" data-mentions="character,season,episode,organization,group,gift,rite,totem,discipline,item,trait,background,merit,flaw,merydef,doc" name="sinopsis" id="new_sinopsis" rows="10"></textarea>

		<br />
		<button class="boton2" type="submit">Crear episodio</button>
		<button class="boton2" type="button" onclick="cerrarPopupNuevo()">Cancelar</button>
	</form>
</div>

<script>
	const capitulos = <?= json_encode($capitulos, JSON_UNESCAPED_UNICODE); ?>;
	const relaciones = <?= json_encode($relaciones, JSON_UNESCAPED_UNICODE); ?>;

	let paginaActual = 1;
	let resultadosPorPagina = 15;
	let orden = { campo: 'temporada', asc: true };

	// Mantener filtros/paginación al enviar forms
	document.querySelector("#popupEditar form").addEventListener("submit", function() {
		document.getElementById('form_filtro_temporada').value = document.getElementById('filtroTemporada').value;
		document.getElementById('form_pagina_actual').value = paginaActual;
	});

	document.querySelector("#popupNuevo form").addEventListener("submit", function() {
		document.getElementById('form_filtro_temporada_new').value = document.getElementById('filtroTemporada').value;
		document.getElementById('form_pagina_actual_new').value = paginaActual;
	});

	function ordenar(campo) {
		orden.asc = (orden.campo === campo) ? !orden.asc : true;
		orden.campo = campo;
		render();
	}

	function escapeQuotes(str) {
		if (!str) return '';
		return str.replace(/'/g, '&#39;').replace(/"/g, '&quot;');
	}

	function render() {
		const filtro = document.getElementById('filtro').value.toLowerCase();
		const filtroTemporada = document.getElementById('filtroTemporada').value;

		let filtrados = capitulos.filter(c =>
			(c.name || '').toLowerCase().includes(filtro) &&
			(filtroTemporada === '' || c.temporada == filtroTemporada)
		);

		filtrados.sort((a, b) => {
			let A = (a[orden.campo] ?? '').toString().toLowerCase();
			let B = (b[orden.campo] ?? '').toString().toLowerCase();
			if (orden.campo === 'capitulo') return orden.asc ? (a.capitulo - b.capitulo) : (b.capitulo - a.capitulo);
			if (orden.campo === 'temporada') return orden.asc ? (a.temporada - b.temporada) : (b.temporada - a.temporada);
			return orden.asc ? A.localeCompare(B) : B.localeCompare(A);
		});

		const tbody = document.querySelector("#tabla-episodios tbody");
		tbody.innerHTML = '';

		let inicio = (paginaActual - 1) * resultadosPorPagina;
		let fin = Math.min(inicio + resultadosPorPagina, filtrados.length);

		for (let i = inicio; i < fin; i++) {
			const c = filtrados[i];
			const fecha = c.fecha ? c.fecha : '';
			let row = `<tr class="pj-row">
			  <td>${c.temporada_name || ('Temporada ' + c.temporada)}</td>
			  <td>${c.capitulo}</td>
			  <td>${c.name}</td>
			  <td>${fecha}</td>
			  <td>
				<button class='boton2' onclick='editar(this)'
				  data-id='${c.id}'
				  data-name='${escapeQuotes(c.name)}'
				  data-capitulo='${c.capitulo}'
				  data-temporada='${c.temporada}'
				  data-fecha='${c.fecha || ""}'
				  data-fecha-ingame='${c.fecha_ingame || ""}'
				  data-sinopsis='${escapeQuotes(c.sinopsis)}'
				>Editar</button>
				<a class='boton2' style='background:red;color:white;' href='/talim?s=admin_epis&delete=${c.id}' onclick='return confirm("¿Eliminar este capítulo?")'>Borrar</a>
			  </td>
			</tr>`;
			tbody.innerHTML += row;
		}

		renderPaginacion(filtrados.length);
	}

	function renderPaginacion(total) {
		const cont = document.getElementById('paginacion-personajes');
		let totalPaginas = Math.ceil(total / resultadosPorPagina);
		let html = '';

		if (totalPaginas > 1) {
			if (paginaActual > 1) {
				html += '<button class="pj-btn-pag" onclick="cambiarPagina(1)">«</button>';
				html += '<button class="pj-btn-pag" onclick="cambiarPagina('+(paginaActual-1)+')">‹</button>';
			}
			for (let p = 1; p <= totalPaginas; p++) {
				if (p === paginaActual) {
					html += `<button class="pj-btn-pag active">${p}</button>`;
				} else if (p === 1 || p === totalPaginas || Math.abs(p - paginaActual) < 3) {
					html += `<button class="pj-btn-pag" onclick="cambiarPagina(${p})">${p}</button>`;
				} else if (p === paginaActual - 3 || p === paginaActual + 3) {
					html += `<span style="padding:0 6px;">…</span>`;
				}
			}
			if (paginaActual < totalPaginas) {
				html += '<button class="pj-btn-pag" onclick="cambiarPagina('+(paginaActual+1)+')">›</button>';
				html += '<button class="pj-btn-pag" onclick="cambiarPagina('+totalPaginas+')">»</button>';
			}
		}
		cont.innerHTML = html;
	}

	function cambiarPagina(n) {
		paginaActual = n;
		render();
	}

	function editar(btn) {
		document.getElementById('edit_id').value = btn.dataset.id;
		document.getElementById('edit_name').value = btn.dataset.name;
		document.getElementById('edit_capitulo').value = btn.dataset.capitulo;
		document.getElementById('edit_temporada').value = btn.dataset.temporada;
		document.getElementById('edit_fecha').value = btn.dataset.fecha;
		document.getElementById('edit_fecha_ingame').value = btn.dataset.fechaIngame;
		document.getElementById('edit_sinopsis').value = btn.dataset.sinopsis;
		document.getElementById('add_relation_capitulo').value = btn.dataset.id;

		actualizarRelaciones(btn.dataset.id);
		document.getElementById('popupEditar').style.display = 'block';
	}

	// NUEVO
	function abrirPopupNuevo() {
		// Valores sugeridos: temporada actual filtrada o la primera disponible
		const ft = document.getElementById('filtroTemporada').value;
		if (ft) document.getElementById('new_temporada').value = ft;

		// Autonumero de capítulo por temporada (pequeño mimo)
		const t = document.getElementById('new_temporada').value;
		const maxCap = capitulos
			.filter(c => c.temporada == t)
			.reduce((m, c) => Math.max(m, parseInt(c.capitulo || 0)), 0);
		document.getElementById('new_capitulo').value = maxCap + 1;

		document.getElementById('new_name').value = '';
		document.getElementById('new_fecha').value = '';
		document.getElementById('new_fecha_ingame').value = '';
		document.getElementById('new_sinopsis').value = '';

		document.getElementById('popupNuevo').style.display = 'block';
	}

	function cerrarPopupNuevo() {
		document.getElementById('popupNuevo').style.display = 'none';
	}

	function cerrarPopupEditar() {
		document.getElementById('popupEditar').style.display = 'none';
	}

	// Relaciones (AJAX)
	function agregarRelacion() {
		const capituloId = document.getElementById("add_relation_capitulo").value;
		const personajeId = document.getElementById("personaje_select").value;
		if (!personajeId || !capituloId) return;

		fetch('sep/talim/talim_epis_ajax.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				action: 'add_relation',
				capitulo_id: capituloId,
				personaje_id: personajeId
			})
		})
		.then(res => res.json())
		.then(data => {
			if (data.ok) {
				document.getElementById("personaje_select").value = '';
				actualizarRelaciones(capituloId);
			}
		});
	}

	function actualizarRelaciones(idCapitulo) {
		fetch('sep/talim/talim_epis_ajax.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({ action: 'get_relations', capitulo_id: idCapitulo })
		})
		.then(res => res.json())
		.then(data => {
			if (!data.ok) return;
			const container = document.getElementById("relacionesContainer");
			let html = '<ul style="padding-left: 1em;">';
			data.data.forEach(rel => {
				html += `<li>${rel.nombre}
					<button type="button" onclick="eliminarRelacion(${rel.id}, ${idCapitulo})" style="color:red;font-size:10px;margin-left:5px;">[Eliminar]</button>
				</li>`;
			});
			html += '</ul>';
			container.innerHTML = html;
		});
	}

	function eliminarRelacion(relId, capituloId) {
		fetch('sep/talim/talim_epis_ajax.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({ action: 'del_relation', rel_id: relId })
		})
		.then(res => res.json())
		.then(data => {
			if (data.ok) actualizarRelaciones(capituloId);
		});
	}

	// Filtros
	document.getElementById('filtro').addEventListener('input', () => {
		paginaActual = 1;
		render();
	});

	document.getElementById('filtroTemporada').addEventListener('change', () => {
		paginaActual = 1;
		render();
	});

	// Restaurar filtros/página desde URL
	document.addEventListener("DOMContentLoaded", () => {
		const urlParams = new URLSearchParams(window.location.search);
		const tempParam = urlParams.get('ft');
		const pageParam = urlParams.get('pg');
		if (tempParam) document.getElementById('filtroTemporada').value = tempParam;
		if (pageParam) paginaActual = parseInt(pageParam);
		render();
	});

	// Esc para cerrar popups
	if (window.hgMentions) { window.hgMentions.attachAuto(); }
	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape') {
			cerrarPopupEditar();
			cerrarPopupNuevo();
		}
	});
</script>

<?php include_once(__DIR__ . '/../../partials/admin/mentions_includes.php'); ?>

<style>
	/* Popups (comparten clase) */
	.popup-edit {
		background: #05014E;
		border: 2px solid #000088;
		position: fixed;
		top: 10%;
		left: 50%;
		transform: translateX(-50%);
		padding: 20px;
		z-index: 9999;
		width: 520px;
		color: white;
	}

	.popup-edit input,
	.popup-edit textarea,
	.popup-edit select {
		width: 100%;
		background: #000033;
		color: white;
		border: 1px solid #333;
		padding: 6px;
		margin-bottom: 8px;
	}

	.tabla-pj {
		width: 100%;
		background: #05014E;
		border: 1px solid #000088;
		border-collapse: collapse;
		margin: 0 auto;
		font-family: Verdana, Arial, sans-serif;
		font-size: 11px;
	}

	.pj-row-head th {
		background: #050b36;
		color: #33CCCC;
		font-weight: bold;
		border-bottom: 2px solid #000088;
		padding: 6px 10px;
		cursor: pointer;
		text-align: left;
		transition: background 0.18s, color 0.18s;
		white-space: nowrap;
	}

	.tabla-pj td, .tabla-pj th {
		border: 1px solid #000088;
		background: #05014E;
		padding: 6px 10px;
		vertical-align: middle;
		white-space: nowrap;
	}

	.tabla-pj tr.pj-row:hover td {
		background: #000066;
		color: #33FFFF;
	}

	.pj-btn-pag {
		font-family: Verdana, Arial, sans-serif;
		font-size: 11px;
		background-color: #000066;
		color: #fff;
		padding: 0.38em 0.9em;
		border: 1px solid #000099;
		border-radius: 0;
		margin: 0 2px;
		transition: background 0.14s, color 0.14s;
		vertical-align: middle;
	}

	.pj-btn-pag:hover,
	.pj-btn-pag.active {
		background-color: #050b36;
		color: #00CCFF;
		border: 1px solid #000088;
		cursor: pointer;
	}

	#paginacion-personajes { margin-bottom: 2em; }
</style>
