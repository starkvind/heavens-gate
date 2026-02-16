<?php setMetaFromPage("Banda sonora | Heaven's Gate", "Banda sonora y temas musicales usados en la campana.", null, 'website'); ?>
<?php
if (!$link) die("Error de conexión a la base de datos.");

$result = $link->query("SELECT id, title_hg, artist, youtube, title, added_at FROM dim_soundtracks ORDER BY title_hg ASC");
if (!$result) die("Error al preparar la consulta: " . $link->error);

$canciones = [];
while ($row = $result->fetch_assoc()) $canciones[] = $row;
mysqli_free_result($result);
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<h2 style="text-align:right;">Banda sonora</h2>

<p style="color:#eee;">Los siguientes temas musicales se han empleado para dar más dimensión a personajes, agrupaciones y situaciones de Heaven's Gate. Se proporciona el título, el artista y un enlace a YouTube para poder disfrutar de la música en todo su esplendor.</p>

<div style="display:flex; justify-content:center; width: 100%;">
  <div style="flex: 1; max-width:600px; min-width:600px;">
    <table id="tabla-canciones" class="display" style="width:100%">
        <thead>
            <tr>
                <th>Título</th>
                <th>Artista</th>
                <th>Título HG</th>
                <th>🎶</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
  </div>
</div>

<!-- Popup del reproductor -->
<div id="popupPlayer" style="display:none; position:fixed; bottom:20px; right:20px; width:350px; height:235px; background-color:#000055; border:1px solid #009; z-index:9999; padding:5px;">
	<div style="width:100%; padding-bottom: 2em; padding-top: 0.5em;text-align: left!important;">
		<div style="width: 85%; float:left;"><span id="titleSong" style="margin-left: 1em;"></span></div>
		<div style="width: 10%; float:right; text-align: right;"><a href="#" onclick="cerrarPopup()" style="color:#66CCFF;margin-right:0.5em;">❌</a></div>
	</div>
    <iframe id="youtubeFrame" width="350" height="200" frameborder="0" allowfullscreen></iframe>
</div>

<script>
$(document).ready(function () {
	const canciones = <?= json_encode($canciones, JSON_UNESCAPED_UNICODE) ?>;
	const tbody = $('#tabla-canciones tbody');

	canciones.forEach(c => {
		const linkTitulo = `<a href="${escapeHtml(c.youtube)}&referrer=heavensgate" target="_blank">${escapeHtml(c.title)}</a>`;
		const linkArtista = `<a href="https://www.youtube.com/results?search_query=${encodeURIComponent(c.artist)}" target="_blank">${escapeHtml(c.artist)}</a>`;
		const hg = escapeHtml(c.title_hg || '');
		const btn = `<button class="boton2" onclick="abrirPopup('${escapeHtml(c.youtube)}', '${escapeHtml(c.title)}', '${escapeHtml(c.artist)}')">▶️</button>`;

		const row = `<tr>
			<td>${linkTitulo}</td>
			<td>${linkArtista}</td>
			<td>${hg}</td>
			<td style="text-align:center;">${btn}</td>
		</tr>`;
		tbody.append(row);
	});

	$('#tabla-canciones').DataTable({
		pageLength: 10,
		lengthMenu: [10, 25, 50, 100],
		order: [[0, "asc"]],
		language: {
			search: "🔍 Buscar:&nbsp;",
			lengthMenu: "Mostrar _MENU_ canciones",
			info: "Mostrando _START_ a _END_ de _TOTAL_ canciones",
			infoEmpty: "No hay canciones disponibles",
			emptyTable: "No hay datos en la tabla",
			paginate: {
				first: "Primero",
				last: "Último",
				next: "▶",
				previous: "◀"
			}
		}
	});
});

function abrirPopup(url, titulo = "", artista = "") {
	const embedUrl = url.replace("watch?v=", "embed/");
	const frame = document.getElementById("youtubeFrame");
	const container = document.getElementById("popupPlayer");
	const titleSpan = document.getElementById("titleSong");

	frame.src = embedUrl;
	container.style.display = "block";
	titleSpan.textContent = `${titulo} — ${artista}`;

	frame.onerror = () => {
		container.style.display = "none";
		window.open(url, '_blank');
	};
}

function cerrarPopup() {
	document.getElementById("popupPlayer").style.display = "none";
	document.getElementById("youtubeFrame").src = "";
}

function escapeHtml(text) {
	if (!text) return '';
	return text.replace(/[&<>"']/g, m => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[m]);
}
</script>

<style>
	/* ------------------------ */
	/* Estilo de los Datatables */
	/* ------------------------ */
	/* DataTables estilo oscuro */
	.dataTables_wrapper {
		color: #eee;
	}

	table.dataTable {
		background-color: transparent;
		color: #eee;
		border-collapse: collapse;
		width: 100%;
		font-size: 0.9em;
		border: 1px solid #000099;
	}

	table.dataTable td {
		text-align: left;
		border-bottom: 1px solid #000099;
		border-top: 1px solid #000099;
	}

	.dataTables_info, .dataTables_paginate {
		margin-top: 1em;
	}

	table.dataTable th {
		background-color: #000066;
		color: #fff;
	}

	table.dataTable tbody tr:hover {
		background-color: #111177;
		cursor: pointer;
	}

	.dataTables_filter input,
	.dataTables_length select {
		font-family: verdana;
		font-size: 10px;
		background-color: #000066;
		color: #fff;
		padding: 0.5em;
		border: 1px solid #000099!important;
		margin-bottom: 1em;
	}

	.dataTables_length option {
		background-color: #000099!important;
	}

	.dataTables_paginate .paginate_button {
		color: #fff !important;
		background: #000066 !important;
		border: 1px solid #000099 !important;
		margin: 2px;
	}

	.dataTables_paginate .paginate_button:hover {
		color: #00CCFF !important;
		cursor: pointer;
		border: 1px solid #000088 !important;
	}

	.dataTables_paginate .paginate_button.current {
		background: #000044 !important;
	}
</style>

