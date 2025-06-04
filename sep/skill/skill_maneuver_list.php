<?php 
$pageSect = "Maniobras de combate"; // PARA CAMBIAR EL TITULO A LA PAGINA
$numregistros = 0;

include("sep/main/main_nav_bar.php"); // Barra de Navegación
echo "<h2>Maniobras de combate</h2>";

// Consulta para obtener sistemas distintos de maniobras de combate
$queryClasi = "SELECT DISTINCT sistema FROM nuevo2_maniobras ORDER BY name ASC";
$stmtClasi = $link->prepare($queryClasi);
$stmtClasi->execute();
$resultClasi = $stmtClasi->get_result();
$filasClasi = $resultClasi->num_rows;

// Iterar sobre cada sistema de maniobras
while ($resultClasiArray = $resultClasi->fetch_assoc()) {
    $nameClasi = htmlspecialchars($resultClasiArray["sistema"]);
    echo "<fieldset class='grupoHabilidad'>"; // Inicio Fieldset
    echo "<legend><b>$nameClasi</b></legend>";

    // Consulta para obtener maniobras específicas del sistema actual
    $consulta = "SELECT id, name FROM nuevo2_maniobras WHERE sistema = ? ORDER BY roll DESC";
    $stmtConsulta = $link->prepare($consulta);
    $stmtConsulta->bind_param('s', $nameClasi);
    $stmtConsulta->execute();
    $resultConsulta = $stmtConsulta->get_result();
    $NFilas = $resultConsulta->num_rows;

    // Iterar sobre cada maniobra
    while ($ResultQuery = $resultConsulta->fetch_assoc()) {
        echo "
            <a href='index.php?p=vermaneu&amp;b=" . htmlspecialchars($ResultQuery["id"]) . "'>
                <div class='renglon3col'>
                    " . htmlspecialchars($ResultQuery["name"]) . "
                </div>
            </a>
        ";	
    }

    echo "</fieldset><br/>"; // Fin Fieldset
    $numregistros += $NFilas;

    // Liberar el statement de maniobras
    $stmtConsulta->close();
}

// Mostrar el número total de maniobras encontradas
echo "<p align='right'>Maniobras: $numregistros</p>";

// Liberar el statement de sistemas
$stmtClasi->close();
?>
