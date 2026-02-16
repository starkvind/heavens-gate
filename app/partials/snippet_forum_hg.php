<?php
	include("app/helpers/heroes.php");
	
	$char_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
	$palette = filter_input(INPUT_GET, 'palette', FILTER_SANITIZE_STRING) ?? 'SkyBlue';
	$palette = preg_replace('/[^a-zA-Z0-9#(),.\s%-]/', '', $palette);
	if ($palette == "3") $palette = "SkyBlue";

	$msg = filter_input(INPUT_GET, 'msg');

	if (!$char_id || !$msg) {
		die("Parámetros incorrectos.");
	}

	/* 
	$query = "SELECT name, img FROM fact_characters WHERE id = ? LIMIT 1";
	$stmt = mysqli_prepare($link, $query);
	mysqli_stmt_bind_param($stmt, "i", $char_id);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);

	if (!$row = mysqli_fetch_assoc($result)) {
		die("Personaje no encontrado.");
	}

	$nombre = htmlspecialchars($row['name']);
	$img = htmlspecialchars($row['img']);
	*/
	
	$defaultImgPath = "img/subidas/";
	$defaultAvatars = [
		-1 => ['name' => 'Hombre', 'img' => 'avatar_nadie_1.png'],
		-2 => ['name' => 'Mujer', 'img' => 'avatar_nadie_2.png'],
		-3 => ['name' => 'Silueta', 'img' => 'avatar_nadie_3.png']
	];

	if (array_key_exists($char_id, $defaultAvatars)) {
		$nombre = $defaultAvatars[$char_id]['name'];
		$img = $defaultAvatars[$char_id]['img'];
		$colortexto = '';
	} else {
		$query = "SELECT name, img, colortexto FROM fact_characters WHERE id = ? LIMIT 1";
		$stmt = mysqli_prepare($link, $query);
		mysqli_stmt_bind_param($stmt, "i", $char_id);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);

		if (!$row = mysqli_fetch_assoc($result)) {
			die("Personaje no encontrado.");
		}

		$nombre = htmlspecialchars($row['name']);
		$img = htmlspecialchars($row['img']);
		$colortexto = htmlspecialchars($row['colortexto']);
	}

	$decoded_msg = htmlspecialchars_decode($msg);
	
	// Normaliza saltos de línea entre etiquetas de lista para evitar <br> entre <li>
	$decoded_msg = preg_replace('/\[\s*\/li\s*\]\s*\n\s*\[\s*li\s*\]/i', '[/li][li]', $decoded_msg);
	$decoded_msg = preg_replace('/\[\s*\/list\s*\]\s*\n/i', '[/list]', $decoded_msg);
	$decoded_msg = preg_replace('/\[\s*list\s*\]\s*\n/i', '[list]', $decoded_msg);

	// Reemplazo de BBCode básico (formato)
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
		'<div style="text-align:left;">$1</div>',
		'<div style="text-align:center;">$1</div>',
		'<div style="text-align:right;">$1</div>',
		'<ul>$1</ul>',
		'<li>$1</li>',
		'<a href="$1" target="_blank">$2</a>',
		'<a href="$1" target="_blank">$2</a>',
		'<a href="$1" target="_blank">$1</a>'

	], $decoded_msg);
	
	// Elimina <br> antes de <div>, después de </div>, y similares
	$parsed_msg = preg_replace([
		'/<br\s*\/?>\s*(<div[^>]*>)/i',   // <br> antes de <div>
		'/(<\/div>)\s*<br\s*\/?>/i',      // <br> después de </div>
		'/<br\s*\/?>\s*(<ul[^>]*>)/i',    // <br> antes de <ul>
		'/(<\/ul>)\s*<br\s*\/?>/i',       // <br> después de </ul>
		'/<br\s*\/?>\s*(<li[^>]*>)/i',    // <br> antes de <li>
		'/(<\/li>)\s*<br\s*\/?>/i',       // <br> después de </li>
	], [
		'$1',
		'$1',
		'$1',
		'$1',
		'$1',
		'$1'
	], $parsed_msg);
	
	// Color de texto por defecto.
	if ($colortexto != "" and $palette == "SkyBlue") $palette = $colortexto;

?>
<!DOCTYPE html>
<html lang="es">
	<head>
		<meta charset="UTF-8">
		<title><?= $nombre ?></title>
		<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap" rel="stylesheet">
		<style>
			:root {
				--palette: <?= $palette ?>;
			}
			.msg_main_box {
				display: flex;
				align-items: start;
				border-radius: 12px;
				font-family: 'Quicksand', sans-serif;
				font-size: 14px;
				max-width: 750px;
				margin: 1em;
				gap: 0.2em;
			}
			.img_link {
				z-index: 12;
			}
			.msg_face {
				border-radius: 50%;
				width: 80px;
				height: 80px;
				object-fit: cover;
				border: 2px solid #333;
				margin-right: -3em;
				transition: all 0.3s ease;
				z-index: 10;
			}
			.msg_face:hover {
				border-color: #aaa;
				box-shadow: 0 0 10px 4px rgba(255, 255, 255, 0.3);
				transform: scale(1.05);
			}
			.msg_body {
				flex: 1;
				background: var(--palette);
				border-radius: 8px;
				border: 1px solid #333;
				padding: 0.75em 1em;
				position: relative;
				box-shadow: 2px 2px 6px rgba(221, 221, 221, 0.5);
				color: black;
			}
			.dark-text { color: white; }
			.light-text { color: black; }
			.msg_name_box {
				position: absolute;
				top: -1em;
				left: 3em;
				border: 1px solid #333;
				background: #444;
				color: white;
				padding: 0.25em 0.5em;
				font-weight: bold;
				border-radius: 4px;
			}
			.msg_container {
				margin: 0;
				padding-left: 2.5em;
				padding-top: 0.5em;
				line-height: 1.5em;
				min-height: calc(1.5em * 2.5);
			}

			.msg_container a {
				color: #0055cc;
				text-decoration: none;
				font-weight: 600;
				border-bottom: 1px dashed #aaa;
				transition: all 0.25s ease-in-out;
			}

			.msg_container a:hover {
				color: #003399;
				background-color: rgba(255, 255, 255, 0.1);
				border-bottom: 1px solid #ccc;
				box-shadow: 0 0 6px rgba(100, 100, 255, 0.4);
				padding: 0.1em 0.2em;
				border-radius: 4px;
			}

		</style>
	</head>
	<body>
		<div class="msg_main_box">
			<?php if ($char_id > 0): ?>
				<a class="img_link" href="https://naufragio-heavensgate.duckdns.org/characters/<?= $char_id ?>" target="_blank">
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
				<div class="msg_container" style="padding-top: 0;"><?= $parsed_msg ?></div>
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

