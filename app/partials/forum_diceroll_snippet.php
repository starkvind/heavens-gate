<?php
include("app/helpers/db_connection.php");

$id_tirada = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_tirada) {
    die("Tirada no especificada.");
}

$query = "SELECT * FROM fact_dice_rolls WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt, "i", $id_tirada);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$tirada = mysqli_fetch_assoc($res)) {
    die("Tirada no encontrada.");
}

$nombre = htmlspecialchars($tirada['name']);
$titulo = htmlspecialchars($tirada['roll_name']);
$dificultad = (int)$tirada['difficulty'];
$resultados = explode(",", $tirada['roll_results']);
$exitos = (int)$tirada['successes'];
$pifia = (bool)$tirada['botch'];

$palette = '#222';

if ($exitos > 0) {
	$palette = '#0FA';
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
		<div class="roll-box-name"><?= $titulo ?></div>
		<div class="roll-box">
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
			<p><strong>Exitos</strong>: <?= $exitos ?></p>
			<?php if ($pifia): ?><p class="roll-botch"><strong>&#161;PIFIA!</strong></p><?php endif; ?>
		</div>
		<script>
				function sendHeight() {
					const height = document.body.scrollHeight + 12;
					window.parent.postMessage({ type: 'setHeight', height }, '*');
				}

				window.addEventListener('load', () => {
					sendHeight();
				});

				window.addEventListener('resize', sendHeight);
		</script>
	</body>
</html>




