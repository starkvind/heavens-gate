<?php
// Validamos conexión
if (!$link) {
    die("Error de conexión a la base de datos.");
}
include_once(__DIR__ . '/../../helpers/pretty.php');

// Procesar envío de formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_tema'])) {
    $titulo      = trim($_POST['titulo']);
    $artista     = trim($_POST['artista']);
    $youtube     = trim($_POST['youtube_url']);
    $titulo_hg   = trim($_POST['context_title']);

    $stmt = $link->prepare("INSERT INTO dim_soundtracks (title, artist, youtube_url, context_title) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $titulo, $artista, $youtube, $titulo_hg);
    $stmt->execute();
    $newId = (int)$link->insert_id;
    hg_update_pretty_id_if_exists($link, 'dim_soundtracks', $newId, $titulo);
    echo "<p style='color:green;'>✅ Tema añadido correctamente.</p>";
}

// Obtener lista de temas existentes
$temas = $link->query("SELECT * FROM dim_soundtracks ORDER BY added_at DESC");
?>

<style>
	.bso_table {
		background-color: #000066;
		color: #fff;
		border: 1px solid #000099;
		border-collapse: collapse;
	}
	
	.bso_table td, .bso_table th {
		padding: 0.25em;
		text-align: left;
	}
	
	.bso_table th {
		background-color: #000055;
	}
</style>

<h2>🎵 Gestión de Banda Sonora</h2>

<div class='bioSheetPowers'>
	<fieldset class='bioSeccion' style="border:0px;">
		<a href='/talim?s=admin_bso_link'>
			<div class='bioSheetPower' style='width:47.5%;'>
				Vincular temas
			</div>
		</a>
	</fieldset>
</div>

<form method="POST" style="margin-bottom:30px;">
    <input type="hidden" name="nuevo_tema" value="1" />
    <fieldset style="max-width:600px;" id="renglonArchivos" style="padding:1em;">
        <legend id="archivosLegend">➕ Añadir nuevo tema</legend>
        <label>Título:</label><br>
        <input type="text" name="titulo" required style="width:100%;"><br><br>

        <label>Artista:</label><br>
        <input type="text" name="artista" style="width:100%;"><br><br>

        <label>Enlace YouTube (ID o URL completa):</label><br>
        <input type="text" name="youtube_url" style="width:100%;"><br><br>

        <label>Nombre simbólico (ej. "Tema de Aránzazu"):</label><br>
        <input type="text" name="context_title" style="width:100%;"><br><br>

        <button class="boton2" type="submit">Guardar tema</button>
    </fieldset>
</form>

<h3>🎼 Temas existentes</h3>
<table class="bso_table">
    <tr>
        <th>ID</th>
        <th>Título</th>
        <th>Artista</th>
        <th>Título en Heaven's Gate</th>
        <th>YouTube</th>
        <th>Fecha</th>
    </tr>
    <?php while ($row = $temas->fetch_assoc()): ?>
    <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= htmlspecialchars($row['artist']) ?></td>
        <td><?= htmlspecialchars($row['context_title']) ?></td>
        <td>
            <?php 
                if (strpos($row['youtube_url'], 'http') === 0) {
                    echo "<a href='{$row['youtube_url']}' target='_blank'>🔗</a>";
                } else {
                    echo "<a href='https://www.youtube.com/watch?v={$row['youtube_url']}' target='_blank'>🎥</a>";
                }
            ?>
        </td>
        <td><?= $row['added_at'] ?></td>
    </tr>
    <?php endwhile; ?>
</table>



