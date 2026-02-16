<?php
setMetaFromPage("Disciplinas | Heaven's Gate", "Listado de poderes por disciplina.", null, 'website');
// Obtener y sanitizar el parámetro 'b'
$routeParam = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta segura para obtener la información de la disciplina
$consulta = "SELECT name, description FROM dim_discipline_types WHERE id = ? LIMIT 1";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $routeParam);
$stmt->execute();
$result = $stmt->get_result();
$ResultQuery = $result->fetch_assoc();

// Definir variables con valores por defecto
$routeLabel = $ResultQuery ? htmlspecialchars($ResultQuery["name"]) : "-";
$descDones = $ResultQuery ? ($ResultQuery["description"] ?? $ResultQuery["desc"] ?? '') : "<p>Descripción no disponible</p>"; // NO usar htmlspecialchars()
$donTypePhrase = "Disciplina";
$pageSect = $donTypePhrase; // Para cambiar el título de la página
$pageTitle2 = $routeLabel;

// Incluir la barra de navegación
include("app/partials/main_nav_bar.php");

// Mostrar el nombre de la Disciplina
echo "<h2>$routeLabel</h2>";
echo "<fieldset class='descripcionGrupo'>$descDones</fieldset>";

// Contenedor de disciplinas
echo "<fieldset class='grupoHabilidad'>";

// Consulta segura para obtener las disciplinas por categoría
$consulta = "SELECT id, pretty_id, name, level FROM fact_discipline_powers WHERE disc = ? ORDER BY level";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $routeParam);
$stmt->execute();
$result = $stmt->get_result();

$totalDisciplinas = 0;

while ($row = $result->fetch_assoc()) {
    echo "
        <a href='" . htmlspecialchars(pretty_url($link, 'fact_discipline_powers', '/powers/discipline', (int)$row["id"])) . "' 
           title='" . htmlspecialchars($row["name"]) . ", Nivel " . htmlspecialchars($row["level"]) . " de $routeLabel'>
            <div class='renglon2col'>
                <div class='renglon2colIz'>
                    <img class='valign' src='img/ui/powers/don.gif'> " . htmlspecialchars($row["name"]) . "
                </div>
                <div class='renglon2colDe'>" . htmlspecialchars($row["level"]) . "</div>
            </div>
        </a>
    ";
    $totalDisciplinas++;
}

echo "</fieldset>";

// Mostrar el número de disciplinas halladas
echo "<p align='right'>Niveles de $routeLabel: $totalDisciplinas</p>";
?>

