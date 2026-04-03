<?php setMetaFromPage("Banda sonora | Heaven's Gate", "Temas musicales usados en la campana.", null, 'website'); ?>
<?php
include_once(__DIR__ . '/../../helpers/runtime_response.php');

if (!hg_runtime_require_db($link, 'bso_main', 'public', [
    'title' => 'Banda sonora no disponible',
    'message' => 'No se pudo conectar a la base de datos.',
    'include_nav' => true,
])) {
    return;
}

$result = $link->query("SELECT id, context_title, artist, youtube_url, title, added_at FROM dim_soundtracks ORDER BY context_title ASC");
if (!$result) {
    hg_runtime_log_error('bso_main.query', $link->error);
    hg_runtime_public_error(
        'Banda sonora no disponible',
        'No se pudo cargar el listado musical.',
        500,
        true
    );
    return;
}

$canciones = [];
while ($row = $result->fetch_assoc()) $canciones[] = $row;
mysqli_free_result($result);
?>
<link rel="stylesheet" href="/assets/css/hg-ost.css">
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="ost-title">Banda sonora</h2>

<p class="ost-intro">Los siguientes temas musicales se han empleado para dar mas dimension a personajes, agrupaciones y situaciones de Heaven's Gate. Se proporciona el titulo, el artista y un enlace a YouTube para poder disfrutar de la musica en todo su esplendor.</p>

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
		const normalizedYoutubeUrl = normalizeYoutubeUrl(c.youtube_url || '');
		const externalYoutubeUrl = normalizedYoutubeUrl ? withReferrer(normalizedYoutubeUrl) : '';
		const safeTitle = escapeHtml(c.title || '(Sin titulo)');
		const safeArtist = escapeHtml(c.artist || '-');
		const linkTitulo = externalYoutubeUrl
			? `<a href="${escapeAttr(externalYoutubeUrl)}" target="_blank" rel="noopener noreferrer">${safeTitle}</a>`
			: safeTitle;
		const linkArtista = c.artist
			? `<a href="https://www.youtube.com/results?search_query=${encodeURIComponent(c.artist)}" target="_blank" rel="noopener noreferrer">${safeArtist}</a>`
			: safeArtist;
		const hg = escapeHtml(c.context_title || '');
		const btn = normalizedYoutubeUrl
			? `<button class="boton2 js-play-song" type="button" data-url="${escapeAttr(normalizedYoutubeUrl)}" data-hg="${escapeAttr(c.context_title || '')}">&#9654;&#65039;</button>`
			: '-';

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
	const embedUrl = buildEmbedUrl(url);
	if (!embedUrl) {
		if (url) window.open(withReferrer(url), '_blank', 'noopener');
		return;
	}
	const frame = document.getElementById("youtubeFrame");
	const container = document.getElementById("popupPlayer");
	const titleSpan = document.getElementById("titleSong");

	frame.src = embedUrl;
	container.style.display = "block";
	titleSpan.textContent = hgTitle || "";

	frame.onerror = () => {
		container.style.display = "none";
		window.open(withReferrer(url), '_blank', 'noopener');
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
function extractYoutubeId(url) {
	const text = String(url || '').trim();
	if (!text) return '';
	if (/^[A-Za-z0-9_-]{11}$/.test(text)) return text;
	const match = text.match(/(?:youtube\.com\/watch\?v=|youtube\.com\/embed\/|youtube\.com\/shorts\/|youtu\.be\/)([A-Za-z0-9_-]{11})/i)
		|| text.match(/[?&]v=([A-Za-z0-9_-]{11})/i);
	return match ? String(match[1] || '') : '';
}
function normalizeYoutubeUrl(url) {
	const id = extractYoutubeId(url);
	return id ? ('https://www.youtube.com/watch?v=' + id) : '';
}
function buildEmbedUrl(url) {
	const id = extractYoutubeId(url);
	return id ? ('https://www.youtube-nocookie.com/embed/' + id) : '';
}
function withReferrer(url) {
	try {
		const parsed = new URL(String(url || '').trim(), window.location.origin);
		parsed.searchParams.set('referrer', 'heavensgate');
		return parsed.toString();
	} catch (e) {
		return String(url || '').trim();
	}
}
</script>






