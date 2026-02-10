<?php
setMetaFromPage("Rituales | Heaven's Gate", "Listado de rituales por categoria.", null, 'website');
// Obtener y sanitizar el parámetro 'b'
$routeParam = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta segura para obtener la información del tipo de ritual
$consulta = "SELECT name, determinante, `desc` FROM dim_rite_types WHERE id = ? LIMIT 1";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $routeParam);
$stmt->execute();
$result = $stmt->get_result();
$ResultQuery = $result->fetch_assoc();

// Definir variables con valores por defecto
$routeLabel = $ResultQuery ? htmlspecialchars($ResultQuery["name"]) : "Desconocido";
$determinante = $ResultQuery ? htmlspecialchars($ResultQuery["determinante"]) : "";
$descRituales = $ResultQuery ? $ResultQuery["desc"] : "<p>Descripción no disponible</p>"; // NO usar htmlspecialchars()
$donTypePhrase = "Ritos";
$pageSect = "$donTypePhrase $determinante $routeLabel"; // Para cambiar el título de la página

// Guardar en sesión para breadcrumbs
$_SESSION['punk2'] = $routeLabel;

// Incluir la barra de navegación
include("app/partials/main_nav_bar.php");

echo "<h2>$donTypePhrase $determinante $routeLabel</h2>";
echo "<fieldset class='descripcionGrupo'><p>$descRituales</p></fieldset>";

// Obtener los niveles de los rituales
$consulta = "SELECT DISTINCT nivel FROM fact_rites WHERE tipo = ? ORDER BY nivel";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $routeParam);
$stmt->execute();
$result = $stmt->get_result();

$domoarigato = [];
while ($row = $result->fetch_assoc()) {
    $domoarigato[] = htmlspecialchars($row["nivel"]);
}

// Si hay niveles de rituales, los mostramos
$misterroboto = count($domoarigato);

if ($misterroboto > 0) {
    foreach ($domoarigato as $nivel) {
        // Consulta para obtener los rituales de cada nivel
        $consulta = "SELECT id, pretty_id, name FROM fact_rites WHERE nivel = ? AND tipo = ? ORDER BY name";
        $stmt = $link->prepare($consulta);
        $stmt->bind_param('ss', $nivel, $routeParam);
        $stmt->execute();
        $result = $stmt->get_result();

        $riteClasificacion = ($routeLabel !== "Menores") ? "Nivel $nivel" : "Sin nivel";

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
