<?php setMetaFromPage("Banda sonora | Heaven's Gate", "Temas musicales usados en la campaña.", null, 'website'); ?>
<?php
if (!$link) die("Error de conexión a la base de datos.");

$result = $link->query("SELECT id, context_title, artist, youtube_url, title, added_at FROM dim_soundtracks ORDER BY context_title ASC");
if (!$result) die("Error al preparar la consulta: " . $link->error);

$canciones = [];
while ($row = $result->fetch_assoc()) $canciones[] = $row;
mysqli_free_result($result);
?>
<link rel="stylesheet" href="/assets/css/hg-ost.css">
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="ost-title">Banda sonora</h2>

<p class="ost-intro">Los siguientes temas musicales se han empleado para dar más dimensión a personajes, agrupaciones y situaciones de Heaven's Gate. Se proporciona el título, el artista y un enlace a YouTube para poder disfrutar de la música en todo su esplendor.</p>

<div class="ost-wrap">
  <div class="ost-inner">
    <table id="tabla-canciones" class="display ost-table">
        <thead>
            <tr>
                <th>T&iacute;tulo</th>
                <th>Artista</th>
                <th>T&iacute;tulo HG</th>
                <th>&#127925;</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
  </div>
</div>

<!-- Popup del reproductor -->
<div id="popupPlayer">
	<div class="ost-popup-top">
		<div class="ost-popup-title-wrap"><span id="titleSong"></span></div>
		<div class="ost-popup-close-wrap"><a class="ost-popup-close" href="#" onclick="cerrarPopup(); return false;">&#10060;</a></div>
	</div>
    <iframe id="youtubeFrame" width="350" height="200" frameborder="0" allowfullscreen></iframe>
</div>

<script>
$(document).ready(function () {
	const canciones = <?= json_encode($canciones, JSON_UNESCAPED_UNICODE) ?>;
	const tbody = $('#tabla-canciones tbody');

	canciones.forEach(c => {
		const linkTitulo = `<a href="${escapeHtml(c.youtube_url)}&referrer=heavensgate" target="_blank">${escapeHtml(c.title)}</a>`;
		const linkArtista = `<a href="https://www.youtube.com/results?search_query=${encodeURIComponent(c.artist)}" target="_blank">${escapeHtml(c.artist)}</a>`;
		const hg = escapeHtml(c.context_title || '');
		const btn = `<button class="boton2 js-play-song" type="button" data-url="${escapeAttr(c.youtube_url)}" data-hg="${escapeAttr(c.context_title || '')}">&#9654;&#65039;</button>`;

		const row = `<tr>
			<td>${linkTitulo}</td>
			<td>${linkArtista}</td>
			<td>${hg}</td>
			<td class="ost-play-cell">${btn}</td>
		</tr>`;
		tbody.append(row);
	});

	tbody.on('click', '.js-play-song', function () {
		const url = this.getAttribute('data-url') || '';
		const hgTitle = this.getAttribute('data-hg') || '';
		if (url) abrirPopup(url, hgTitle);
	});

	$('#tabla-canciones').DataTable({
		pageLength: 10,
		lengthMenu: [10, 25, 50, 100],
		order: [[0, "asc"]],
		language: {
			search: "&#128269; Buscar:&nbsp;",
			lengthMenu: "Mostrar _MENU_ canciones",
			info: "Mostrando _START_ a _END_ de _TOTAL_ canciones",
			infoEmpty: "No hay canciones disponibles",
			emptyTable: "No hay datos en la tabla",
			paginate: {
				first: "Primero",
				last: "&Uacute;ltimo",
				next: "&#9654;",
				previous: "&#9664;"
			}
		}
	});
});

function abrirPopup(url, hgTitle = "") {
	const embedUrl = url.replace("watch?v=", "embed/");
	const frame = document.getElementById("youtubeFrame");
	const container = document.getElementById("popupPlayer");
	const titleSpan = document.getElementById("titleSong");

	frame.src = embedUrl;
	container.style.display = "block";
	titleSpan.textContent = hgTitle || "";

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
function escapeAttr(text) {
	return escapeHtml(text ?? '');
}
</script>






