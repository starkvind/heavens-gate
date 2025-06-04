<?php
// Obtener y sanitizar el parámetro 'b'
$punk = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta segura para obtener la información de la disciplina
$consulta = "SELECT name, `desc` FROM nuevo2_tipo_disciplinas WHERE id = ? LIMIT 1";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $punk);
$stmt->execute();
$result = $stmt->get_result();
$ResultQuery = $result->fetch_assoc();

// Definir variables con valores por defecto
$punk2 = $ResultQuery ? htmlspecialchars($ResultQuery["name"]) : "Desconocido";
$descDones = $ResultQuery ? $ResultQuery["desc"] : "<p>Descripción no disponible</p>"; // NO usar htmlspecialchars()
$donTypePhrase = "Disciplina";
$pageSect = $donTypePhrase; // Para cambiar el título de la página
$pageTitle2 = $punk2;

// Incluir la barra de navegación
include("sep/main/main_nav_bar.php");

// Mostrar el nombre de la Disciplina
echo "<h2>$punk2</h2>";
echo "<fieldset class='descripcionGrupo'>$descDones</fieldset>";

// Contenedor de disciplinas
echo "<fieldset class='grupoHabilidad'>";

// Consulta segura para obtener las disciplinas por categoría
$consulta = "SELECT id, name, nivel FROM nuevo_disciplinas WHERE disc = ? ORDER BY nivel";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $punk);
$stmt->execute();
$result = $stmt->get_result();

$totalDisciplinas = 0;

while ($row = $result->fetch_assoc()) {
    echo "
        <a href='index.php?p=muestradisc&amp;b=" . htmlspecialchars($row["id"]) . "' 
           title='" . htmlspecialchars($row["name"]) . ", Nivel " . htmlspecialchars($row["nivel"]) . " de $punk2'>
            <div class='renglon2col'>
                <div class='renglon2colIz'>
                    <img class='valign' src='img/don.gif'> " . htmlspecialchars($row["name"]) . "
                </div>
                <div class='renglon2colDe'>" . htmlspecialchars($row["nivel"]) . "</div>
            </div>
        </a>
    ";
    $totalDisciplinas++;
}

echo "</fieldset>";

// Mostrar el número de disciplinas halladas
echo "<p align='right'>Niveles de $punk2: $totalDisciplinas</p>";
?>
