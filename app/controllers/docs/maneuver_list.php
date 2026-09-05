<?php
setMetaFromPage("Maniobras | Heaven's Gate", "Listado de maniobras de combate.", null, 'website');
$pageSect = "Maniobras de combate";
$numregistros = 0;

include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-maneuvers.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-maneuvers.css">';
}

echo "<h2>Maniobras de combate</h2>";

$queryClasi = "SELECT DISTINCT system_name FROM fact_combat_maneuvers ORDER BY name ASC";
$stmtClasi = $link->prepare($queryClasi);
$stmtClasi->execute();
$resultClasi = $stmtClasi->get_result();

while ($resultClasiArray = $resultClasi->fetch_assoc()) {
    $nameClasi = htmlspecialchars($resultClasiArray["system_name"]);
    echo "<fieldset class='hg-maneuvers-group'>";
    echo "<legend><b>$nameClasi</b></legend>";

    $consulta = "SELECT id, name FROM fact_combat_maneuvers WHERE system_name = ? ORDER BY roll DESC";
    $stmtConsulta = $link->prepare($consulta);
    $stmtConsulta->bind_param('s', $nameClasi);
    $stmtConsulta->execute();
    $resultConsulta = $stmtConsulta->get_result();
    $NFilas = $resultConsulta->num_rows;

    while ($ResultQuery = $resultConsulta->fetch_assoc()) {
        echo "
            <a href='" . htmlspecialchars(pretty_url($link, 'fact_combat_maneuvers', '/rules/maneuvers', (int)$ResultQuery["id"])) . "'>
                <span class='hg-maneuvers-card'>" . htmlspecialchars($ResultQuery["name"]) . "</span>
            </a>
        ";
    }

    echo "</fieldset>";
    $numregistros += $NFilas;
    $stmtConsulta->close();
}

echo "<p align='right'>Maniobras: $numregistros</p>";
$stmtClasi->close();
?>
