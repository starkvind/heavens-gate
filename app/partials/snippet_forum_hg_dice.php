<?php
include("app/helpers/heroes.php");

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

$nombre = htmlspecialchars($tirada['nombre']);
$titulo = htmlspecialchars($tirada['tirada_nombre']);
$dificultad = (int)$tirada['dificultad'];
$resultados = explode(",", $tirada['resultados']);
$exitos = (int)$tirada['exitos'];
$pifia = (bool)$tirada['pifia'];

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
		<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap" rel="stylesheet">
		<style>
			:root {
				--palette: <?= $palette ?>;
			}
			body {
				font-family: 'Quicksand', sans-serif;
				color: white;
				margin: 0;
				padding: 1em;
				box-sizing: border-box;
			}
			.roll-box {
				/*background-color: rgba(0, 0, 0, 0.3);*/
				background-color: var(--palette)
				padding: 1em;
				/*border-radius: 12px 0;*/
				max-width: 600px;
				/*margin: auto;*/
				border: 1px solid #666;
				text-align: center;
			}
			.roll-title {
				font-size: 1.2em;
				margin-bottom: 0.5em;
			}
			.roll-box-name {
				position: absolute;
				top: 0em;
				left: 3em;
				border: 1px solid #333;
				background: #444;
				color: white;
				padding: 0.25em 0.5em;
				font-weight: bold;
				border-radius: 4px;
			}
			.roll-results {
				display: flex;
				flex-wrap: wrap;
				justify-content: center;
				gap: 6px;
				margin-bottom: 1em;
			}
			.dado {
				width: 40px;
				height: 40px;
				background: url('../../img/ui/dice/dado_d10.png') center/contain no-repeat;
				position: relative;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 0.8em;
				font-weight: bold;
				border-radius: 4px;
				filter: drop-shadow(0 0 3px #5ff);
			}
			.dado span {
				position:absolute; 
				top:8px; 
				left:0; 
				right:0; 
				text-align:center; 
				font-weight:bold;
				color: white;
			}
		</style>
	</head>
	<body>
		<div class="roll-box-name"><?= $titulo ?></div>
		<div class="roll-box">
			<!--<div class="roll-title"><?= $titulo ?></div>-->
			<p style="margin-top:1.5em;"><strong><?= $nombre ?></strong> lanzó <?= count($resultados) ?>d10 a dificultad <strong><?= $dificultad ?></strong>.</p>
			<div class="roll-results">
				<?php
				foreach ($resultados as $dado) {
					$dado = (int)$dado;
					$color = ($dado == 1) ? '#f55' : (($dado >= $dificultad) ? '#5f5' : '#5ff');
					echo "<div class='dado' style='filter: drop-shadow(0 0 3px $color);'><span style='color:$color;'>$dado</span></div>";
				}
				?>
			</div>
			<p><strong>Éxitos</strong>: <?= $exitos ?></p>
			<?php if ($pifia): ?><p style="color: #f55;"><strong>¡PIFIA!</strong></p><?php endif; ?>
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
