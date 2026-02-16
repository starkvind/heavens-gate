<?php
setMetaFromPage("Rituales | Heaven's Gate", "Listado de rituales por categoria.", null, 'website');
// Obtener y sanitizar el parámetro 'b'
$routeParam = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta segura para obtener la información del kind de ritual
$consulta = "SELECT name, determinant AS determinante, description FROM dim_rite_types WHERE id = ? LIMIT 1";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $routeParam);
$stmt->execute();
$result = $stmt->get_result();
$ResultQuery = $result->fetch_assoc();

// Definir variables con valores por defecto
$routeLabel = $ResultQuery ? htmlspecialchars($ResultQuery["name"]) : "Desconocido";
$determinante = $ResultQuery ? htmlspecialchars($ResultQuery["determinante"]) : "";
$descRituales = $ResultQuery ? ($ResultQuery["description"] ?? $ResultQuery["desc"] ?? '') : "<p>Descripción no disponible</p>"; // NO usar htmlspecialchars()
$donTypePhrase = "Ritos";
$pageSect = "$donTypePhrase $determinante $routeLabel"; // Para cambiar el título de la página

// Guardar en sesión para breadcrumbs
$_SESSION['punk2'] = $routeLabel;

// Incluir la barra de navegación
include("app/partials/main_nav_bar.php");

echo "<h2>$donTypePhrase $determinante $routeLabel</h2>";
echo "<fieldset class='descripcionGrupo'><p>$descRituales</p></fieldset>";

// Obtener los leveles de los rituales
$consulta = "SELECT DISTINCT level FROM fact_rites WHERE kind = ? ORDER BY level";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $routeParam);
$stmt->execute();
$result = $stmt->get_result();

$domoarigato = [];
while ($row = $result->fetch_assoc()) {
    $domoarigato[] = htmlspecialchars($row["level"]);
}

// Si hay leveles de rituales, los mostramos
$misterroboto = count($domoarigato);

if ($misterroboto > 0) {
    foreach ($domoarigato as $level) {
        // Consulta para obtener los rituales de cada level
        $consulta = "SELECT id, pretty_id, name FROM fact_rites WHERE level = ? AND kind = ? ORDER BY name";
        $stmt = $link->prepare($consulta);
        $stmt->bind_param('ss', $level, $routeParam);
        $stmt->execute();
        $result = $stmt->get_result();

        $riteClasificacion = ($routeLabel !== "Menores") ? "Nivel $level" : "Sin level";

        echo "<fieldset class='grupoHabilidad'>";
        echo "<legend><b><a name='$riteClasificacion'></a> $riteClasificacion</b></legend>";

        while ($row = $result->fetch_assoc()) {
            echo "
                <a href='" . htmlspecialchars(pretty_url($link, 'fact_rites', '/powers/rite', (int)$row["id"])) . "'
                    title='" . htmlspecialchars($row["name"]) . "'>
                    <div class='renglon2col'>
                        <div class='renglon2colIz'>
                            <img class='valign' src='img/ui/powers/rite.gif'> " . htmlspecialchars($row["name"]) . "
                        </div>
                    </div>
                </a>
            ";
        }

        echo "</fieldset>";
    }
}

echo "<p align='right'>Rituales hallados: $misterroboto</p>";
?>
