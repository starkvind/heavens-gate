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
    echo "<p class='adm-admin-ok'>Tema anadido correctamente.</p>";
}

// Obtener lista de temas existentes
$temas = $link->query("SELECT * FROM dim_soundtracks ORDER BY added_at DESC");
?>

<h2>🎵 Gestión de Banda Sonora</h2>

<div class='bioSheetPowers'>
	<fieldset class='bioSeccion adm-border-none'>
		<a href='/talim?s=admin_bso_link'>
			<div class='bioSheetPower adm-admin-tile'>
				Vincular temas
			</div>
		</a>
	</fieldset>
</div>

<form method="POST" class="adm-mb-30">
    <input type="hidden" name="nuevo_tema" value="1" />
    <fieldset id="renglonArchivos" class="adm-bso-form-fieldset">
        <legend id="archivosLegend">➕ Añadir nuevo tema</legend>
        <label>Título:</label><br>
        <input type="text" name="titulo" required class="adm-w-full"><br><br>

        <label>Artista:</label><br>
        <input type="text" name="artista" class="adm-w-full"><br><br>

        <label>Enlace YouTube (ID o URL completa):</label><br>
        <input type="text" name="youtube_url" class="adm-w-full"><br><br>

        <label>Nombre simbólico (ej. "Tema de Aránzazu"):</label><br>
        <input type="text" name="context_title" class="adm-w-full"><br><br>

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




