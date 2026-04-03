<?php
	require_once(__DIR__ . '/../helpers/runtime_response.php');
	require_once(__DIR__ . '/../helpers/character_avatar.php');

	if (!isset($link) || !($link instanceof mysqli)) {
		require_once(__DIR__ . '/../helpers/db_connection.php');
	}

	if (!function_exists('hg_normalize_palette_value')) {
		function hg_normalize_palette_value(string $raw, string $fallback = 'SkyBlue'): string {
			$v = trim($raw);
			if ($v === '') return $fallback;
			if ($v === '3') return 'SkyBlue';

			if (preg_match('/^\$([0-9a-f]{3}|[0-9a-f]{6})$/i', $v, $m)) {
				return '#'.strtolower($m[1]);
			}
			if (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $v, $m)) {
				return '#'.strtolower($m[1]);
			}
			if (preg_match('/^(?:rgb|hsl)a?\(\s*[0-9.%\s,]+\s*\)$/i', $v)) {
				$clean = preg_replace('/\s+/', ' ', $v);
				return trim((string)$clean);
			}
			if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/', $v)) {
				return $v;
			}
			return $fallback;
		}
	}
	
	$char_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
	$palette_raw = isset($_GET['palette']) ? (string)$_GET['palette'] : 'SkyBlue';
	$palette = hg_normalize_palette_value($palette_raw, 'SkyBlue');

	$msg = filter_input(INPUT_GET, 'msg');

	if (!$char_id || $msg === null || $msg === '') {
		hg_runtime_embed_error('Mensaje no disponible', 'Faltan parametros obligatorios para generar el mensaje.', 400);
		return;
	}
	
	$defaultImgPath = "public/img/ui/avatar/";
	$defaultAvatars = [
		-1 => ['name' => 'Hombre', 'img' => 'avatar_nadie_1.png'],
		-2 => ['name' => 'Mujer', 'img' => 'avatar_nadie_2.png'],
		-3 => ['name' => 'Silueta', 'img' => 'avatar_nadie_3.png'],
		-4 => ['name' => 'Espiritu', 'img' => 'avatar_nadie_4.png'],
	];

	if (array_key_exists($char_id, $defaultAvatars)) {
		$nombre = $defaultAvatars[$char_id]['name'];
		$img = $defaultAvatars[$char_id]['img'];
		$colortexto = '';
		$char_pretty = (string)$char_id;
	} else {
		$query = "SELECT name, image_url, gender, text_color, pretty_id FROM fact_characters WHERE id = ? LIMIT 1";
		$stmt = mysqli_prepare($link, $query);
		if (!$stmt) {
			hg_runtime_log_error('forum_message_snippet.prepare', mysqli_error($link));
			hg_runtime_embed_error('Mensaje no disponible', 'No se pudo preparar la consulta del personaje.', 500);
			return;
		}
		mysqli_stmt_bind_param($stmt, "i", $char_id);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);

		if (!$row = mysqli_fetch_assoc($result)) {
			hg_runtime_embed_error('Personaje no encontrado', 'No existe ningun personaje con ese identificador.', 404);
			return;
		}

		$nombre = htmlspecialchars($row['name']);
		$img = htmlspecialchars(ltrim(hg_character_avatar_url($row['image_url'] ?? '', $row['gender'] ?? ''), '/'));
		$colortexto = hg_normalize_palette_value((string)($row['text_color'] ?? ''), '');
		$char_pretty = trim((string)($row['pretty_id'] ?? ''));
		if ($char_pretty === '') {
			$char_pretty = (string)$char_id;
		}
	}

	$decoded_msg = htmlspecialchars_decode($msg);
	
	// Normaliza saltos de linea entre etiquetas de lista para evitar <br> entre <li>
	$decoded_msg = preg_replace('/\[\s*\/li\s*\]\s*\n\s*\[\s*li\s*\]/i', '[/li][li]', $decoded_msg);
	$decoded_msg = preg_replace('/\[\s*\/list\s*\]\s*\n/i', '[/list]', $decoded_msg);
	$decoded_msg = preg_replace('/\[\s*list\s*\]\s*\n/i', '[list]', $decoded_msg);

	// Reemplazo de BBCode basico (formato)
	$parsed_msg = preg_replace([
		'/\[b\](.*?)\[\/b\]/is',
		'/\[i\](.*?)\[\/i\]/is',
		'/\[u\](.*?)\[\/u\]/is',
		'/\[left\](.*?)\[\/left\]/is',
		'/\[center\](.*?)\[\/center\]/is',
		'/\[right\](.*?)\[\/right\]/is',
		'/\[list\](.*?)\[\/list\]/is',
		'/\[li\](.*?)\[\/li\]/is',
		'/\[url="(https?:\/\/[^"]+)"\](.*?)\[\/url\]/is',
		'/\[url=(https?:\/\/[^\]]+)\](.*?)\[\/url\]/is',
		'/\[url\](https?:\/\/[^\s\]]+)\[\/url\]/is'

	], [
		'<strong>$1</strong>',
		'<em>$1</em>',
		'<u>$1</u>',
		'<div class="hg-bb-align-left">$1</div>',
		'<div class="hg-bb-align-center">$1</div>',
		'<div class="hg-bb-align-right">$1</div>',
		'<ul>$1</ul>',
		'<li>$1</li>',
		'<a href="$1" target="_blank">$2</a>',
		'<a href="$1" target="_blank">$2</a>',
		'<a href="$1" target="_blank">$1</a>'

	], $decoded_msg);
	
	// Elimina <br> antes de <div>, despues de </div>, y similares
	$parsed_msg = preg_replace([
		'/<br\s*\/?>\s*(<div[^>]*>)/i',   // <br> antes de <div>
		'/(<\/div>)\s*<br\s*\/?>/i',      // <br> despues de </div>
		'/<br\s*\/?>\s*(<ul[^>]*>)/i',    // <br> antes de <ul>
		'/(<\/ul>)\s*<br\s*\/?>/i',       // <br> despues de </ul>
		'/<br\s*\/?>\s*(<li[^>]*>)/i',    // <br> antes de <li>
		'/(<\/li>)\s*<br\s*\/?>/i',       // <br> despues de </li>
	], [
		'$1',
		'$1',
		'$1',
		'$1',
		'$1',
		'$1'
	], $parsed_msg);

	// Remove leading blank lines / breaks at message start.
	$parsed_msg = preg_replace('/^(?:(?:\s|&nbsp;|<br\s*\/?>)+|<p>\s*(?:&nbsp;|<br\s*\/?>|\s)*<\/p>)+/i', '', $parsed_msg);
	
	// Color de texto por defecto.
	if ($colortexto != "" and $palette == "SkyBlue") $palette = $colortexto;

?>
<!DOCTYPE html>
<html lang="es">
	<head>
		<meta charset="UTF-8">
		<title><?= $nombre ?></title>
		<link href="/assets/vendor/fonts/quicksand/quicksand.css" rel="stylesheet">
		<link rel="stylesheet" href="/assets/css/hg-embeds.css">
	</head>
	<body class="hg-embed-message" style="--palette: <?= htmlspecialchars($palette, ENT_QUOTES, 'UTF-8') ?>;">
		<div class="msg_main_box">
			<?php if ($char_id > 0): ?>
				<a class="img_link" href="https://naufragio-heavensgate.duckdns.org/characters/<?= rawurlencode($char_pretty) ?>" target="_blank">
					<img class="msg_face" src="../<?= $img ?>" alt="avatar">
				</a>
			<?php else: ?>
				<img class="msg_face" src="../<?= $defaultImgPath.$img ?>" alt="avatar">
			<?php endif; ?>
			<div class="msg_body">
				<?php if ($char_id > 0): ?>
				<div class="msg_name_box"><?= $nombre ?></div>
				<div class="msg_container"><?= $parsed_msg ?></div>
				<?php else: ?>
				<div class="msg_container msg_container--plain"><?= $parsed_msg ?></div>
				<?php endif; ?>
			</div>
		</div>

		<script>
			function getLuminance(r, g, b) {
				const a = [r, g, b].map(v => {
					v /= 255;
					return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
				});
				return 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2];
			}

			function detectAndApplyTextColor() {
				const box = document.querySelector('.msg_body');
				const style = getComputedStyle(box);
				const bgColor = style.backgroundColor;
				const rgb = bgColor.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
				if (rgb) {
					const lum = getLuminance(+rgb[1], +rgb[2], +rgb[3]);
					box.classList.add(lum < 0.5 ? 'dark-text' : 'light-text');
				}
			}

			function sendHeight() {
				const height = document.body.scrollHeight + 32;
				window.parent.postMessage({ type: 'setHeight', height }, '*');
			}

			window.addEventListener('load', () => {
				detectAndApplyTextColor();
				sendHeight();
			});

			window.addEventListener('resize', sendHeight);
		</script>
	</body>
</html>



