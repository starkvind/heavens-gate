<?php
if (!$link) {
    die("Error de conexion a la base de datos.");
}
include_once(__DIR__ . '/../../helpers/pretty.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'])) {
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $descripcion = (string)($_POST['descripcion'] ?? '');
    $fecha = (string)($_POST['fecha'] ?? '');
    $tipo = (string)($_POST['tipo'] ?? 'evento');
    $ubicacion = (string)($_POST['ubicacion'] ?? '');
    $fuente = (string)($_POST['fuente'] ?? '');

    if ($titulo !== '') {
        $stmt = $link->prepare(
            "INSERT INTO fact_timeline_events (event_date, title, description, kind, location, source) VALUES (?, ?, ?, ?, ?, ?)"
        );
        if ($stmt) {
            $stmt->bind_param('ssssss', $fecha, $titulo, $descripcion, $tipo, $ubicacion, $fuente);
            $stmt->execute();
            $evento_id = (int)$stmt->insert_id;
            $stmt->close();

            hg_update_pretty_id_if_exists($link, 'fact_timeline_events', $evento_id, $titulo);

            if (!empty($_POST['capitulo_id'])) {
                $ref_id = (int)$_POST['capitulo_id'];
                if ($st = $link->prepare("INSERT INTO bridge_timeline_links (event_id, relation_type, ref_id) VALUES (?, 'capitulo', ?)")) {
                    $st->bind_param('ii', $evento_id, $ref_id);
                    $st->execute();
                    $st->close();
                }
            }

            if (!empty($_POST['character_id'])) {
                $ref_id = (int)$_POST['character_id'];
                if ($st = $link->prepare("INSERT INTO bridge_timeline_links (event_id, relation_type, ref_id) VALUES (?, 'personaje', ?)")) {
                    $st->bind_param('ii', $evento_id, $ref_id);
                    $st->execute();
                    $st->close();
                }
            }

            echo "<p class='adm-admin-ok'>Evento anadido correctamente.</p>";
        }
    }
}
?>

<h2>Anadir nuevo evento historico</h2>
<form method="post">
    <fieldset class="add-event-form adm-timeline-fieldset" id="renglonArchivos">
        <div class="form-row">
            <label for="titulo">Titulo:</label>
            <input type="text" name="titulo" id="titulo" required>
        </div>

        <div class="form-row">
            <label for="fecha">Fecha in-game:</label>
            <input type="date" name="fecha" id="fecha">
        </div>

        <div class="form-row">
            <label for="descripcion">Descripcion:</label>
        </div>
        <textarea name="descripcion" id="descripcion" rows="6" cols="80" class="adm-timeline-desc"></textarea>

        <div class="form-row">
            <label for="tipo">Tipo:</label>
            <select name="tipo" id="tipo">
                <option value="evento">Evento</option>
                <option value="romance">Romance</option>
                <option value="fundacion">Fundacion</option>
                <option value="alianza">Alianza</option>
                <option value="reclutamiento">Reclutamiento</option>
                <option value="descubrimiento">Descubrimiento</option>
                <option value="enemistad">Enemistad</option>
                <option value="batalla">Batalla</option>
                <option value="traicion">Traicion</option>
                <option value="catastrofe">Catastrofe</option>
                <option value="nacimiento">Nacimiento</option>
                <option value="muerte">Muerte</option>
                <option value="otros">Otros</option>
            </select>
        </div>

        <div class="form-row">
            <label for="ubicacion">Ubicacion:</label>
            <input type="text" name="ubicacion" id="ubicacion">
        </div>

        <div class="form-row">
            <label for="fuente">Fuente:</label>
            <input type="text" name="fuente" id="fuente" placeholder="Nombre de documento o archivo fuente">
        </div>

        <div class="form-row">
            <label for="timeline">Linea temporal:</label>
            <select name="timeline" id="timeline">
                <?php
                $res = $link->query("SELECT id, name FROM dim_chronicles");
                while ($res && ($row = $res->fetch_assoc())) {
                    echo "<option value='" . htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') . "</option>";
                }
                ?>
            </select>
        </div>
    </fieldset>
    <div class="adm-text-center"><input class="boton2" type="submit" value="Anadir evento"></div>
</form>

<br>
<hr>
<br>

<h3>Ultimos eventos registrados</h3>
<ul>
<?php
$res = $link->query("SELECT id, event_date, title FROM fact_timeline_events ORDER BY event_date DESC LIMIT 20");
while ($res && ($row = $res->fetch_assoc())) {
    echo "<li><strong>" . htmlspecialchars((string)$row['event_date'], ENT_QUOTES, 'UTF-8') . "</strong> - " . htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') . "</li>";
}
?>
</ul>
