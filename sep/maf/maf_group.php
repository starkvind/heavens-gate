<?php
// Obtener los parámetros 't' y 'b' de manera segura
$mafCategory = isset($_GET['t']) ? $_GET['t'] : '';
$mafType = isset($_GET['b']) ? $_GET['b'] : ''; 

include("maf_group_convert.php");

$pageSect = htmlspecialchars($mafCategoryName); // PARA CAMBIAR EL TITULO A LA PAGINA
$pageTitle2 = htmlspecialchars($mafTypeName);
$numregistros = 0;

// Incluir barra de navegación
include("sep/main/main_nav_bar.php"); // Barra de Navegación
echo "<h2>$mafTypeName</h2>";

// Preparar la consulta para obtener sistemas únicos de méritos y defectos
$queryMeriF = "SELECT DISTINCT sistema FROM nuevo_mer_y_def WHERE afiliacion = ? AND tipo = ? ORDER BY sistema";
$stmtMeriF = $link->prepare($queryMeriF);
$stmtMeriF->bind_param('ss', $mafTypeName, $mafCategoryName);
$stmtMeriF->execute();
$resultMeriF = $stmtMeriF->get_result();
$filasMeriF = $resultMeriF->num_rows;

while ($resultMeriFArray = $resultMeriF->fetch_assoc()) {
    $costeMeriF = htmlspecialchars($resultMeriFArray["sistema"]);
    echo "<fieldset class='grupoHabilidad'>";
    echo "<legend><b>$costeMeriF</b></legend>";

    // Preparar la consulta para obtener detalles de méritos y defectos
    $consulta = "SELECT * FROM nuevo_mer_y_def WHERE sistema = ? AND afiliacion = ? AND tipo = ? ORDER BY coste ASC";
    $stmtConsulta = $link->prepare($consulta);
    $stmtConsulta->bind_param('sss', $costeMeriF, $mafTypeName, $mafCategoryName);
    $stmtConsulta->execute();
    $resultConsulta = $stmtConsulta->get_result();
    $NFilas = $resultConsulta->num_rows;

    while ($ResultQuery = $resultConsulta->fetch_assoc()) {
        $tipeOfMeritFlaw = htmlspecialchars($ResultQuery["tipo"]);
        $costOfMeritFlaw = htmlspecialchars($ResultQuery["coste"]);
        $nameType = $tipeOfMeritFlaw === "Méritos" ? "Mérito" : "Defecto";
        $imgRoute = $tipeOfMeritFlaw === "Méritos" ? "merit" : "flaw";
        $pointPhrase = ($costOfMeritFlaw == 1) ? "punto" : "puntos";

        echo "
            <a href='index.php?p=merfla&amp;b=" . htmlspecialchars($ResultQuery["id"]) . "' title='$nameType de $costOfMeritFlaw $pointPhrase'>
                <div class='renglon2col'>
                    <div class='renglon2colIz'>
                        <img src='img/$imgRoute.gif' class='valign'/>
                        " . htmlspecialchars($ResultQuery["name"]) . "
                    </div>
                    <div class='renglon2colDe'>
                        $costOfMeritFlaw
                    </div>
                </div>
            </a>
        ";
    }

    echo "</fieldset>";
    $numregistros += $NFilas;

    // Cerramos la sentencia preparada
    $stmtConsulta->close();
}

echo "<p align='right'>" . htmlspecialchars($tipeOfMeritFlaw) . " encontrados: $numregistros</p>";

// Cerramos la sentencia preparada para sistemas únicos
$stmtMeriF->close();
?>
