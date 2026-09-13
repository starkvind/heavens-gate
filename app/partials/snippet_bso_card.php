<?php
include_once(__DIR__ . '/../helpers/runtime_response.php');
require_once(__DIR__ . '/../domains/soundtracks/queries.php');

function mostrarTarjetaBSO($link, $tipo, $id) {
	if (!in_array($tipo, ['personaje', 'temporada', 'episodio'], true)) return;

	if (function_exists('hg_page_register_stylesheet')) {
		hg_page_register_stylesheet('/assets/css/hg-bso.css');
	}

	$id = (int)$id;
	$rows = hg_soundtracks_fetch_for_object($link, $tipo, $id);
	if ($rows === null) {
		hg_runtime_log_error('snippet_bso_card.query', mysqli_error($link));
		return;
	}

	$tracks = [];
	foreach ($rows as $tema) {
		$youtubeID = '';
		$url = (string)($tema['enlace'] ?? '');
		if (preg_match('%(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|shorts/))([^&\n?#/]+)%i', $url, $matches)) {
			$youtubeID = trim((string)$matches[1]);
		}
		if ($youtubeID === '') continue;

		$tracks[] = [
			'youtube_id' => $youtubeID,
			'context_title' => trim((string)($tema['context_title'] ?? '')),
			'title' => trim((string)($tema['titulo_real'] ?? '')),
			'artist' => trim((string)($tema['artist'] ?? '')),
		];
	}

	if (empty($tracks)) return;

	$esc = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	$count = count($tracks);

	echo "<div class='bioTextData bso-carousel' data-bso-carousel>";
	echo "<div class='bso-carousel__viewport'>";
	foreach ($tracks as $index => $track) {
		$isActive = ($index === 0);
		$youtubeID = $esc($track['youtube_id']);
		$contextTitle = $esc($track['context_title'] !== '' ? $track['context_title'] : 'Banda sonora');
		$title = $esc($track['title'] !== '' ? $track['title'] : 'Tema');
		$artist = $esc($track['artist']);
		$embedUrl = "https://www.youtube-nocookie.com/embed/{$youtubeID}";
		$hiddenAttr = $isActive ? '' : ' hidden';
		$activeClass = $isActive ? ' is-active' : '';
		$srcAttr = $isActive ? " src='" . $esc($embedUrl) . "'" : '';

		echo "<section class='bso-carousel__slide{$activeClass}' data-bso-slide data-index='{$index}'{$hiddenAttr}>";
		echo "<fieldset class='bso-card bioSeccion'>";
		echo "<legend>&nbsp;&#127925; {$contextTitle}&nbsp;</legend>";
		echo "<div class='video-wrapper'>";
		echo "<iframe class='bso-card-iframe'{$srcAttr} data-src='" . $esc($embedUrl) . "' title='{$title}' loading='lazy' frameborder='0' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share' allowfullscreen></iframe>";
		echo "</div>";
		echo "<p class='bso-card-caption'><strong>{$title}</strong>" . ($artist !== '' ? " - {$artist}" : '') . "</p>";
		echo "</fieldset>";
		echo "</section>";
	}
	echo "</div>";

	if ($count > 1) {
		echo "<div class='bso-carousel__nav' role='tablist' aria-label='Banda sonora'>";
		foreach ($tracks as $index => $track) {
			$isActive = ($index === 0);
			$labelTitle = $track['title'] !== '' ? $track['title'] : ('Pista ' . ($index + 1));
			$label = $esc('Ver pista ' . ($index + 1) . ': ' . $labelTitle);
			$activeClass = $isActive ? ' is-active' : '';
			$current = $isActive ? " aria-current='true'" : '';
			echo "<button type='button' class='bso-carousel__dot{$activeClass}' data-bso-dot data-index='{$index}' aria-label='{$label}'{$current}></button>";
		}
		echo "</div>";
	}

	echo "</div>";
	?>
	<script>
	(() => {
	    document.querySelectorAll('[data-bso-carousel]').forEach(carousel => {
	        if (carousel.dataset.bsoReady === '1') return;
	        carousel.dataset.bsoReady = '1';

	        const slides = Array.from(carousel.querySelectorAll('[data-bso-slide]'));
	        const dots = Array.from(carousel.querySelectorAll('[data-bso-dot]'));
	        if (!slides.length) return;

	        const activate = index => {
	            index = Math.max(0, Math.min(slides.length - 1, Number(index) || 0));
	            slides.forEach((slide, slideIndex) => {
	                const active = slideIndex === index;
	                slide.hidden = !active;
	                slide.classList.toggle('is-active', active);
	                const iframe = slide.querySelector('iframe[data-src]');
	                if (!iframe) return;
	                if (active) {
	                    if (!iframe.getAttribute('src')) iframe.setAttribute('src', iframe.dataset.src || '');
	                } else if (iframe.getAttribute('src')) {
	                    iframe.removeAttribute('src');
	                }
	            });

	            dots.forEach((dot, dotIndex) => {
	                const active = dotIndex === index;
	                dot.classList.toggle('is-active', active);
	                if (active) dot.setAttribute('aria-current', 'true');
	                else dot.removeAttribute('aria-current');
	            });
	        };

	        dots.forEach(dot => {
	            dot.addEventListener('click', () => activate(dot.dataset.index));
	            dot.addEventListener('keydown', event => {
	                if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
	                event.preventDefault();
	                const current = Number(dot.dataset.index) || 0;
	                const next = event.key === 'ArrowRight'
	                    ? (current + 1) % slides.length
	                    : (current - 1 + slides.length) % slides.length;
	                activate(next);
	                if (dots[next]) dots[next].focus();
	            });
	        });
	    });
	})();
	</script>
	<?php
}
?>