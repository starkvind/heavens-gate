<?php setMetaFromPage("Tiradados | Heaven's Gate", "Herramienta para tirar dados d10 y registrar tiradas.", null, 'website'); ?>
<style>
    .form-box {
        background-color: #222;
        padding: 1.5em;
        border-radius: 8px;
        max-width: 400px;
        margin: auto;
        border: 1px solid #444;
    }
    label {
        display: block;
        margin-top: 1em;
        margin-bottom: 0.5em;
    }
    input {
        width: 95%;
    }
    input, select {
        padding: 0.5em;
        background: #333;
        color: #eee;
        border: 1px solid #555;
        border-radius: 4px;
    }
    select {
        width: 23%;
        margin: 1em;
    }
    button {
        margin-top: 1.5em;
        padding: 0.75em;
        width: 25%;
        border: none;
        font-weight: bold;
        cursor: pointer;
    }
	.dado-d10 {
		background: url('../../img/ui/dice/dado_d10.png') center/contain no-repeat;
		width: 40px;
		height: 40px;
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 0.8em;
		font-weight: bold;
	}
</style>

<?php
if (!$link) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Mostrar tirada concreta si ?see={id}
if (isset($_GET['see'])) {
    $id_ver = (int)$_GET['see'];
    $stmt = mysqli_prepare($link, "SELECT * FROM fact_dice_rolls WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_ver);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        mostrar_tirada($row, $id_ver);
        echo '<p style="text-align: center;"><a class="boton2" href="/tools/dice">Volver al tiradados</a></p>';
        //exit;
    } else {
        echo '<p style="text-align: center;">No se ha encontrado la tirada solicitada.</p>';
    }
}

$mostrar_resultado = false;
$mensaje_error = '';
$resultado_html = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_jugador = trim($_POST['nombre'] ?? '');
    $tirada_nombre = trim($_POST['tirada_nombre'] ?? '');
    $dados = (int)($_POST['dados'] ?? 0);
    $dificultad = (int)($_POST['dificultad'] ?? 0);
    $ip = $_SERVER['REMOTE_ADDR'];

    if ($nombre_jugador === '' || $tirada_nombre === '' || $dados < 1 || $dados > 15 || $dificultad < 2 || $dificultad > 10) {
        $mensaje_error = "Parámetros inválidos.";
    } else {
        $query = "SELECT timestamp FROM fact_dice_rolls WHERE ip = ? ORDER BY timestamp DESC LIMIT 1";
        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, "s", $ip);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            if (strtotime($row['timestamp']) > time() - 10) {
                $mensaje_error = "Has tirado hace menos de 10 segundos.";
            }
        }
    }

    if ($mensaje_error === '') {
        $query = "SELECT COUNT(*) as total FROM fact_dice_rolls WHERE tirada_nombre = ?";
        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, "s", $tirada_nombre);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        if ($row['total'] > 0) {
            $mensaje_error = "Ese nombre de tirada ya existe.";
        }
    }

    if ($mensaje_error === '') {
        $resultados = [];
        $exitos = 0;
        $uno_detectado = false;

        for ($i = 0; $i < $dados; $i++) {
            $dado = rand(1, 10);
            $resultados[] = $dado;
            if ($dado >= $dificultad) $exitos++;
            if ($dado == 1 && !$uno_detectado) $uno_detectado = true;
        }

        if ($uno_detectado) {
            $exitos--;
            if ($exitos < 0) $exitos = 0;
        }

        $pifia = ($uno_detectado && $exitos === 0);
        $pifia_valor = $pifia ? 1 : 0;
        $str_resultados = implode(",", $resultados);

        $query = "INSERT INTO fact_dice_rolls (nombre, tirada_nombre, dados, dificultad, resultados, exitos, pifia, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, "ssiisiss", $nombre_jugador, $tirada_nombre, $dados, $dificultad, $str_resultados, $exitos, $pifia_valor, $ip);
        mysqli_stmt_execute($stmt);

        // Obtener el último ID insertado
		$last_id = mysqli_insert_id($link);

		// Redirigir a la vista individual de la tirada
		header("Location: /tools/dice?see=$last_id");
		exit;
    }
}

function mostrar_tirada($tirada, $id_ver) {
    $resultados = explode(",", $tirada['resultados']);
    $dificultad = $tirada['dificultad'];
	$html = "<div class='form-box'>";
    $html .= "<h3 style='text-align: center;'>{$tirada['tirada_nombre']}</h3>";
    $html .= "<p><strong>{$tirada['nombre']}</strong> lanzó <strong>{$tirada['dados']}d10</strong> a dificultad <strong>{$dificultad}</strong>.</p><br />";
    $html .= "<div style='display: flex; gap: 6px; flex-wrap: wrap;'>";

	foreach ($resultados as $dado) {
		$dado = (int)$dado;

		if ($dado == 1) {
			$color = '#f55'; // SIEMPRE rojo si es 1
		} elseif ($dado >= $dificultad) {
			$color = '#5f5'; // Verde si es éxito
		} else {
			$color = '#5ff'; // Turquesa si es fallo
		}

		$html .= "<div style='width:40px; height:40px; margin-right: 0.25em; align-items: center; background: url(../../img/ui/dice/dado_d10.png) center/contain no-repeat; position: relative; filter: drop-shadow(0 0 3px $color);'>" .
				 "<span style='font-size: 8px; position:absolute; top:8px; left:0; right:0; text-align:center; font-weight:bold; color:$color;'>$dado</span></div>";
	}

    $html .= "</div>";
    $html .= "<br /><p><strong>Éxitos:</strong> {$tirada['exitos']}</p>";
    if ($tirada['pifia']) $html .= "<p style='color:red;'>¡PIFIA!</p>";
	$html .= "<hr />";
	$html .= "<p style='margin-top:1em;'>Embeber tirada en el foro:</p>";
	$html .= "<pre style='background:#111; border:1px solid #444; color:#0f0; font-family:monospace; padding:0.5em; border-radius:6px; overflow:auto;'><code>[hg_tirada]{$id_ver}[/hg_tirada]</code></pre>";
	$html .= "</div>";
    echo $html;
}

// Formulario como está actualmente si no es POST ni ?see
// Mostrar el formulario si no venimos de ?see
if (!isset($_GET['see']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
<div class="form-box">
    <h2>Tiradados</h2>
    <?php if ($mensaje_error): ?>
        <p style="color: red;"><?php echo $mensaje_error; ?></p>
    <?php endif; ?>
    <?php if (!$mostrar_resultado): ?>
    <form method="post">
        <label for="nombre">Nombre del jugador / personaje</label>
        <input type="text" name="nombre" id="nombre" maxlength="50" required>

        <label for="tirada_nombre">Nombre de la tirada (único)</label>
        <input type="text" name="tirada_nombre" id="tirada_nombre" maxlength="50" placeholder="Ej: Ataque del lobo" required>

        <div style="width:100%; display: flex; margin-top: 1em; margin-bottom: 1em;align-items: center;">
            <label for="dados">Dados (1–20)</label>
            <select name="dados" id="dados" required>
                <?php for ($i = 1; $i <= 20; $i++) echo "<option value=\"$i\">$i</option>"; ?>
            </select>

            <label for="dificultad">Dificultad (2–10)</label>
            <select name="dificultad" id="dificultad" required>
                <?php for ($i = 2; $i <= 10; $i++) echo "<option value=\"$i\">$i</option>"; ?>
            </select>
        </div>
		<div style="margin: auto;width:100%;text-align: center;">
			<button class='boton2' type="submit">¡Tirar!</button>
		</div>
    </form>
    <?php else: ?>
        <?php echo $resultado_html; ?>
    <?php endif; ?>
</div>
<?php
	/* --------------------------------------------------------------- */
	$query = "SELECT id, tirada_nombre, nombre, timestamp FROM fact_dice_rolls ORDER BY timestamp DESC LIMIT 10";
	$res = mysqli_query($link, $query);

	if ($res && mysqli_num_rows($res) > 0) {
		echo "<div class='form-box' style='margin-top:3em;'><h3>Últimas tiradas</h3><ul style='padding-left: 1em;'>";
		while ($row = mysqli_fetch_assoc($res)) {
			$nombre = htmlspecialchars($row['nombre']);
			$titulo = htmlspecialchars($row['tirada_nombre']);
			echo "<li><a href='/tools/dice?see={$row['id']}'>$titulo</a> por $nombre</li>";
		}
		echo "</ul></div>";
	}
	/* --------------------------------------------------------------- */
}
?>
