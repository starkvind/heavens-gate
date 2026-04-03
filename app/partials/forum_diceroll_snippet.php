<?php
require_once(__DIR__ . '/../helpers/runtime_response.php');

if (!isset($link) || !($link instanceof mysqli)) {
    require_once(__DIR__ . '/../helpers/db_connection.php');
}

$id_tirada = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_tirada) {
    hg_runtime_embed_error('Tirada no disponible', 'No se ha indicado ninguna tirada.', 400);
    return;
}

$query = "SELECT * FROM fact_dice_rolls WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $query);
if (!$stmt) {
    hg_runtime_log_error('forum_diceroll_snippet.prepare', mysqli_error($link));
    hg_runtime_embed_error('Tirada no disponible', 'No se pudo preparar la consulta de la tirada.', 500);
    return;
}
mysqli_stmt_bind_param($stmt, "i", $id_tirada);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$tirada = mysqli_fetch_assoc($res)) {
    hg_runtime_embed_error('Tirada no encontrada', 'No existe ninguna tirada con ese identificador.', 404);
    return;
}

$nombre = htmlspecialchars($tirada['name']);
$titulo = htmlspecialchars($tirada['roll_name']);
$dificultad = (int)$tirada['difficulty'];
$resultados = explode(",", $tirada['roll_results']);
$exitos = (int)$tirada['successes'];
$pifia = (bool)$tirada['botch'];
$willpowerSpent = !empty($tirada['willpower_spent']);

$paletteParam = filter_input(INPUT_GET, 'palette', FILTER_UNSAFE_RAW) ?? '';
$paletteParam = preg_replace('/[^a-zA-Z0-9#(),.\s%-]/', '', (string)$paletteParam);

$palette = $pifia ? '#3A1010' : '#05014E';
if (trim($paletteParam) !== '') {
    $palette = $paletteParam;
}

?>
<!DOCTYPE html>
<html lang="es">
	<head>
		<meta charset="UTF-8">
		<title><?= $titulo ?></title>
		<link href="/assets/vendor/fonts/quicksand/quicksand.css" rel="stylesheet">
		<link rel="stylesheet" href="/assets/css/hg-embeds.css">
</head>
	<body class="hg-embed-roll" style="--palette: <?= $palette ?>;">
		<div class="roll-main-box">
			<div class="roll-box">
				<div class="roll-box-name"><?= $titulo ?></div>
				<!--<div class="roll-title"><?= $titulo ?></div>-->
				<p class="roll-head"><strong><?= $nombre ?></strong> lanzo <?= count($resultados) ?>d10 a dificultad <strong><?= $dificultad ?></strong>.</p>
				<div class="roll-results">
					<?php
					foreach ($resultados as $dado) {
						$dado = (int)$dado;
						$color = ($dado == 1) ? '#f55' : (($dado >= $dificultad) ? '#5f5' : '#5ff');
						echo "<div class='dado' style='--die-color:$color;'><span>$dado</span></div>";
					}
					?>
				</div>
				<p><strong>Exitos</strong>: <?= $exitos ?><?php if ($willpowerSpent): ?> <span>(+1 por Fuerza de Voluntad)</span><?php endif; ?></p>
				<?php if ($pifia): ?><p class="roll-botch"><strong>&#161;PIFIA!</strong></p><?php endif; ?>
			</div>
		</div>
		<script>
				function sendHeight() {
					const height = document.body.scrollHeight + 24;
					window.parent.postMessage({ type: 'setHeight', height }, '*');
				}

				window.addEventListener('load', () => {
					sendHeight();
				});

				window.addEventListener('resize', sendHeight);
		</script>
	</body>
</html>




