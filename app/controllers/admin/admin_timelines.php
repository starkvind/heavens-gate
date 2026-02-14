<?php
if (!$link) {
    die("Error de conexión a la base de datos.");
include_once(__DIR__ . '/../../helpers/pretty.php');
}

// INSERCIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'])) {
    $titulo      = $_POST['titulo'];
    $descripcion = $_POST['descripcion'] ?? '';
    $fecha       = $_POST['fecha'] ?? null;
    $tipo        = $_POST['tipo'] ?? 'evento';
    $ubicacion   = $_POST['ubicacion'] ?? '';
    $fuente      = $_POST['fuente'] ?? '';

    $stmt = $link->prepare("INSERT INTO fact_timeline_events (fecha, titulo, descripcion, tipo, ubicacion, fuente) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $fecha, $titulo, $descripcion, $tipo, $ubicacion, $fuente);
    $stmt->execute();
    hg_update_pretty_id_if_exists($link, 'fact_timeline_events', (int)$evento_id, $titulo);
    $evento_id = $stmt->insert_id;
    $stmt->close();

    // VINCULACIÓN
    if (!empty($_POST['capitulo_id'])) {
        $ref_id = intval($_POST['capitulo_id']);
        $stmt = $link->prepare("INSERT INTO bridge_timeline_links (evento_id, tipo_relacion, ref_id) VALUES (?, 'capitulo', ?)");
        $stmt->bind_param("ii", $evento_id, $ref_id);
        $stmt->execute();
        $stmt->close();
    }

    if (!empty($_POST['personaje_id'])) {
        $ref_id = intval($_POST['personaje_id']);
        $stmt = $link->prepare("INSERT INTO bridge_timeline_links (evento_id, tipo_relacion, ref_id) VALUES (?, 'personaje', ?)");
        $stmt->bind_param("ii", $evento_id, $ref_id);
        $stmt->execute();
        $stmt->close();
    }

    echo "<p style='color:green;'>✅ Evento añadido correctamente.</p>";
}
?>

<style>
	.add-event-form .form-row {
		display: flex;
		align-items: center;
		margin-bottom: 10px;
	}
	.add-event-form label {
		width: 15%;
		padding: 4px;
	}
	.add-event-form input,
	.add-event-form select {
		width: 65%;
	}
</style>

<h2>➕ Añadir nuevo evento histórico</h2>
<form method="post">
	<fieldset class="add-event-form" style="max-width:600px; padding:1em;margin-bottom: 1em;" id="renglonArchivos">
		<div class="form-row">
			<label for="titulo">Título:</label>
			<input type="text" name="titulo" id="titulo" required>
		</div>

		<div class="form-row">
			<label for="fecha">Fecha in-game:</label>
			<input type="date" name="fecha" id="fecha">
		</div>

		<div class="form-row">
			<label for="descripcion">Descripción:</label>
		</div>
		<textarea name="descripcion" id="descripcion" rows="6" cols="80" style="width:80%;margin-bottom: 1em;"></textarea>

		<div class="form-row">
			<label for="tipo">Tipo:</label>
			<select name="tipo" id="tipo">
				<option value="evento">🌀 Evento</option>
				<option value="romance">💖 Romance</option>
				<option value="fundacion">🏛️ Fundación</option>
				<option value="alianza">🤝 Alianza</option>
				<option value="reclutamiento">🧭 Reclutamiento</option>
				<option value="descubrimiento">🔍 Descubrimiento</option>
				<option value="enemistad">☠️ Enemistad</option>
				<option value="batalla">⚔️ Batalla</option>
				<option value="traicion">🩸 Traición</option>
				<option value="catastrofe">🔥 Catástrofe</option>
				<option value="nacimiento">🟢 Nacimiento</option>
				<option value="muerte">⚰️ Muerte</option>
				<option value="otros">📌 Otros</option>
			</select>
		</div>

		<div class="form-row">
			<label for="ubicacion">Ubicación:</label>
			<input type="text" name="ubicacion" id="ubicacion">
		</div>

		<div class="form-row">
			<label for="fuente">Fuente:</label>
			<input type="text" name="fuente" id="fuente" placeholder="Nombre de documento o archivo fuente">
		</div>
		
		<div class="form-row">
			<label for="timeline">Línea temporal:</label>
			<select name="timeline" id="timeline">
				<?php
					$res = $link->query("SELECT id, name FROM dim_chronicles");
					while ($row = $res->fetch_assoc()) {
						echo "<option value='" . $row['name'] ."'>{$row['name']}</option>";
					}
				?>
			</select>
		</div>
	</fieldset>
    <center><input class="boton2" type="submit" value="Añadir evento"></center>
</form>

<br/>
<hr>
<br/>

<h3>📜 Últimos eventos registrados</h3>
<ul>
<?php
$res = $link->query("SELECT id, fecha, titulo FROM fact_timeline_events ORDER BY fecha DESC LIMIT 20");
while ($row = $res->fetch_assoc()) {
    echo "<li><strong>{$row['fecha']}</strong> — {$row['titulo']}</li>";
}
?>
</ul>
