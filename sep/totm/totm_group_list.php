<?php
// Obtener y sanitizar el parámetro 'b'
$punk = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta segura para obtener la información del tipo de tótem
$consulta = "SELECT name, determinante FROM nuevo2_tipo_totems WHERE id = ? LIMIT 1";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $punk);
$stmt->execute();
$result = $stmt->get_result();
$ResultQuery = $result->fetch_assoc();

// Definir variables con valores por defecto
$totemName = $ResultQuery ? htmlspecialchars($ResultQuery["name"]) : "Desconocido";
$totemDett = $ResultQuery ? htmlspecialchars($ResultQuery["determinante"]) : "";
$pageSect = "Tótems $totemDett $totemName"; // Para cambiar el título de la página

// Incluir la barra de navegación
include("sep/main/main_nav_bar.php");

echo "<h2>Tótems $totemName</h2>";

echo "<fieldset class='grupoHabilidad'>";

// Consulta segura para obtener los tótems de esta categoría
$consulta = "SELECT id, name, coste FROM nuevo_totems WHERE tipo = ? ORDER BY coste";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $punk);
$stmt->execute();
$result = $stmt->get_result();

$totalTotems = 0;

while ($row = $result->fetch_assoc()) {
    echo "
        <a href='index.php?p=muestratotem&amp;b=" . htmlspecialchars($row["id"]) . "'>
            <div class='renglon2col'>
                <div class='renglon2colIz'>
                    <img class='valign' src='img/totem.gif'> " . htmlspecialchars($row["name"]) . "
                </div>
                <div class='renglon2colDe'>" . htmlspecialchars($row["coste"]) . "</div>
            </div>
        </a>
    ";
    $totalTotems++;
}

echo "</fieldset>";

// Mostrar el número de tótems hallados
echo "<p align='right'>Tótems hallados: $totalTotems</p>";
?>
