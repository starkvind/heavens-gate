<?php
// admin_temporadas.php — Gestión de Temporadas (corregido)

// Validamos conexión
if (!isset($link) || !$link) {
    die("Error de conexión a la base de datos.");
}
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/mentions.php');

// Helper: fallo con info útil
function db_fail(mysqli $link, string $msg) {
    $err = $link->error ?: 'Sin detalle (revisa logs del servidor / mysqli_report).';
    die("<p style='color:#ff6b6b;'><b>ERROR:</b> ".htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')."<br><small>"
        .htmlspecialchars($err, ENT_QUOTES, 'UTF-8')."</small></p>");
}

// ADD: agregar nueva temporada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_temporada'])) {
    $name   = trim($_POST['name'] ?? '');
    $numero = (int)($_POST['numero'] ?? 0);
    $season = isset($_POST['season']) ? 1 : 0;
    $desc   = trim($_POST['desc'] ?? '');
    $desc   = hg_mentions_convert($link, $desc);

    // Campos NOT NULL en tu tabla que NO estabas rellenando:
    // opening (varchar NOT NULL), protagonistas (mediumtext NOT NULL)
    // Si tu created_at NO tiene DEFAULT, también reventaría, pero lo normal es que lo tenga.
    $opening = trim($_POST['opening'] ?? '');            // por si lo añades luego al form
    $protas  = trim($_POST['protagonistas'] ?? '');      // por si lo añades luego al form

    $sql = "INSERT INTO dim_seasons (`name`, `season_number`, `season`, `desc`, `opening`, `main_cast`)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $link->prepare($sql);
    if (!$stmt) db_fail($link, "Prepare INSERT falló");

    // Tipos correctos: name(s), numero(i), season(i), desc(s), opening(s), protagonistas(s)
    $stmt->bind_param("siisss", $name, $numero, $season, $desc, $opening, $protas);
    if (!$stmt->execute()) db_fail($link, "Execute INSERT falló");
    $newId = (int)$link->insert_id;
    hg_update_pretty_id_if_exists($link, 'dim_seasons', $newId, $name);
    $stmt->close();

    echo "<p style='color:green;'>✔ Nueva temporada añadida.</p>";
}

// EDIT: editar temporada existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id']) && !isset($_POST['add_temporada'])) {
    $id     = (int)($_POST['edit_id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $numero = (int)($_POST['numero'] ?? 0);
    $season = isset($_POST['season']) ? 1 : 0;
    $desc   = trim($_POST['desc'] ?? '');
    $desc   = hg_mentions_convert($link, $desc);

    // Igual: si no lo editas desde aquí, lo mantenemos como está (mejor que vaciarlo sin querer)
    // Pero como tu form actual no envía opening/protagonistas, NO los tocamos en UPDATE.
    // Si quieres editarlos, añade campos al form y descomenta abajo.
    //
    // $opening = trim($_POST['opening'] ?? '');
    // $protas  = trim($_POST['protagonistas'] ?? '');

    $sql = "UPDATE dim_seasons
            SET `name`=?, `season_number`=?, `season`=?, `desc`=?
            WHERE id=?";
    $stmt = $link->prepare($sql);
    if (!$stmt) db_fail($link, "Prepare UPDATE falló");

    // Tipos correctos: name(s), numero(i), season(i), desc(s), id(i)
    $stmt->bind_param("siisi", $name, $numero, $season, $desc, $id);
    if (!$stmt->execute()) db_fail($link, "Execute UPDATE falló");
    hg_update_pretty_id_if_exists($link, 'dim_seasons', $id, $name);
    $stmt->close();

    echo "<p style='color:deepskyblue;'>✏ Temporada actualizada.</p>";
}

// Obtener todas las temporadas
$temporadas = [];
$result = $link->query("SELECT *, season_number AS numero, main_cast AS protagonistas FROM dim_seasons ORDER BY season_number ASC");
if (!$result) db_fail($link, "Query SELECT falló");

while ($row = $result->fetch_assoc()) {
    $temporadas[] = $row;
}
?>

<h2>📺 Edición de Temporadas</h2>

<style>
.temporada-block {
    margin-bottom: 1.5em;
    padding: 2em;
    border: 1px solid #000099;
    background: #000055;
    border-radius: 0.5em;
}
.temporada-block label { margin-right: 0.5em; }
</style>

<?php foreach ($temporadas as $temp): ?>
<form method="post" class="temporada-block">
    <input type="hidden" name="edit_id" value="<?= (int)$temp['id'] ?>">

    <div class="temp-data" style="text-align:left;">
        <label>Nombre:</label>
        <input type="text" name="name" value="<?= htmlspecialchars($temp['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

        <label>Número:</label>
        <input type="number" name="numero" value="<?= (int)($temp['numero'] ?? 0) ?>" required>
        <br />

        <label>Historia personal:</label>
        <input type="checkbox" name="season" <?= !empty($temp['season']) ? 'checked' : '' ?>>

        <textarea class="hg-mention-input" data-mentions="character,season,episode,organization,group,gift,rite,totem,discipline,item,trait,background,merit,flaw,merydef,doc" name="desc" rows="6" cols="80" style="margin-top: 1em;"><?= htmlspecialchars($temp['desc'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        <br /><br />
    </div>

    <button class="boton2" type="submit">Actualizar</button>
</form>
<?php endforeach; ?>

<h2>➕ Añadir nueva Temporada</h2>
<form method="post" class="temporada-block">
    <input type="hidden" name="add_temporada" value="1">

    <div class="temp-data">
        <label>Nombre:</label>
        <input type="text" name="name" required>

        <label>Número:</label>
        <input type="number" name="numero" required>
        <br/>

        <label>Historia personal:</label>
        <input type="checkbox" name="season">
        <br />

        <textarea class="hg-mention-input" data-mentions="character,season,episode,organization,group,gift,rite,totem,discipline,item,trait,background,merit,flaw,merydef,doc" name="desc" rows="6" cols="80" style="margin-top: 1em;"></textarea>

        <!--
        Estos dos NO estaban en tu form original, pero tu tabla los exige NOT NULL.
        Los dejo ocultos con valor vacío para cumplir sin cambiar tu UI.
        Si prefieres, conviértelos en inputs visibles.
        -->
        <input type="hidden" name="opening" value="">
        <input type="hidden" name="protagonistas" value="">

        <br /><br />
    </div>

    <button class="boton2" type="submit">Añadir Temporada</button>
</form>

<script>if (window.hgMentions) { window.hgMentions.attachAuto(); }</script>
