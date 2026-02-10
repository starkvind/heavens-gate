<?php
// Validamos conexión
if (!$link) {
    die("Error de conexión a la base de datos.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vincular_tema'])) {
    $id_bso   = intval($_POST['id_bso']);
    $tipo     = $_POST['tipo'];
    $id_obj   = intval($_POST['id_objeto']);

    $stmt = $link->prepare("INSERT INTO bridge_soundtrack_links (id_bso, tipo_objeto, id_objeto) VALUES (?, ?, ?)");

	if (!$stmt) {
		die("Error al preparar la consulta: " . $link->error);
	}
    $stmt->bind_param("isi", $id_bso, $tipo, $id_obj);
    $stmt->execute();
    echo "<p style='color:green;'>✅ Relación añadida correctamente.</p>";
}

// Obtener listas necesarias
$temas = $link->query("SELECT id, titulo_hg FROM dim_soundtracks ORDER BY fecha_add DESC");
$personajes = $link->query("SELECT id, nombre FROM fact_characters WHERE cronica NOT IN (2, 7) ORDER BY nombre");
$temporadas = $link->query("SELECT id, name FROM dim_seasons ORDER BY numero");
$episodios = $link->query("SELECT id, name FROM dim_chapters ORDER BY fecha DESC");
?>

<h2>🔗 Asociar tema musical</h2>

<div class='bioSheetPowers'>
	<fieldset class='bioSeccion' style="border:0px;">
		<a href='/talim?s=admin_bso'>
			<div class='bioSheetPower' style='width:47.5%;'>
				Gestionar banda sonora
			</div>
		</a>
	</fieldset>
</div>

<form method="POST">
    <input type="hidden" name="vincular_tema" value="1" />
	<input type="hidden" name="id_objeto" id="id_objeto_final" value="">
    <fieldset style="max-width:600px;" id="renglonArchivos" style="padding:1em;">
        <legend>➕ Nueva asociación</legend>

        <label>Tema musical:</label><br>
        <select name="id_bso" required style="width:100%;">
            <option value="">Seleccionar tema</option>
            <?php while ($row = $temas->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['titulo_hg']) ?></option>
            <?php endwhile; ?>
        </select><br><br>

        <label>Tipo de vínculo:</label><br>
        <select name="tipo" id="tipoSelector" required style="width:100%;" onchange="mostrarSelector()">
            <option value="">Seleccionar tipo</option>
            <option value="personaje">Personaje</option>
            <option value="temporada">Temporada</option>
            <option value="episodio">Episodio</option>
        </select><br><br>

        <div id="selectorPersonaje" style="display:none;">
            <label>Personaje:</label><br>
            <select id="select_personaje" style="width:100%;"> <!-- sin name -->
                <?php while ($row = $personajes->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nombre']) ?></option>
                <?php endwhile; ?>
            </select><br><br>
        </div>

        <div id="selectorTemporada" style="display:none;">
            <label>Temporada:</label><br>
            <select id="select_temporada" style="width:100%;">
                <?php while ($row = $temporadas->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                <?php endwhile; ?>
            </select><br><br>
        </div>

        <div id="selectorEpisodio" style="display:none;">
            <label>Episodio:</label><br>
            <select id="select_episodio" style="width:100%;">
                <?php while ($row = $episodios->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                <?php endwhile; ?>
            </select><br><br>
        </div>

        <button class="boton2" type="submit">Guardar vínculo</button>
    </fieldset>
</form>

<script>
function mostrarSelector() {
    document.getElementById('selectorPersonaje').style.display = 'none';
    document.getElementById('selectorTemporada').style.display = 'none';
    document.getElementById('selectorEpisodio').style.display = 'none';
    document.getElementById('id_objeto_final').value = ''; // reset

    let tipo = document.getElementById('tipoSelector').value;

    if (tipo === 'personaje') {
        document.getElementById('selectorPersonaje').style.display = 'block';
        document.getElementById('id_objeto_final').value = document.getElementById('select_personaje').value;
    } else if (tipo === 'temporada') {
        document.getElementById('selectorTemporada').style.display = 'block';
        document.getElementById('id_objeto_final').value = document.getElementById('select_temporada').value;
    } else if (tipo === 'episodio') {
        document.getElementById('selectorEpisodio').style.display = 'block';
        document.getElementById('id_objeto_final').value = document.getElementById('select_episodio').value;
    }
}

// Actualizar el hidden cuando se cambia el valor:
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('select_personaje').addEventListener('change', function () {
        document.getElementById('id_objeto_final').value = this.value;
    });
    document.getElementById('select_temporada').addEventListener('change', function () {
        document.getElementById('id_objeto_final').value = this.value;
    });
    document.getElementById('select_episodio').addEventListener('change', function () {
        document.getElementById('id_objeto_final').value = this.value;
    });
});

</script>
