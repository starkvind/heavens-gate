<?php
// Obtener el parámetro 'b' de manera segura
$skillCategory = isset($_GET['b']) ? $_GET['b'] : ''; 

include("skill_convert.php");
include("sep/main/main_nav_bar.php"); // Barra de navegación
?>

<h2><?php echo htmlspecialchars($skillCategoryName); ?></h2>

<?php 
$pageSect = htmlspecialchars($skillCategoryName); // PARA CAMBIAR EL TITULO A LA PAGINA
$numregistros = 0;

// Consulta para obtener las clasificaciones de habilidades
$queryClasi = "SELECT DISTINCT clasificacion FROM nuevo_habilidades WHERE tipo = ? ORDER BY clasificacion ASC";
$stmtClasi = $link->prepare($queryClasi);
$stmtClasi->bind_param('s', $skillCategoryName);
$stmtClasi->execute();
$resultClasi = $stmtClasi->get_result(); // Aquí es donde conseguimos el objeto de resultado correcto

// Comprobamos que resultClasi es un objeto de resultado y no un array
if ($resultClasi !== false) {
    // Recorremos los resultados de la consulta
    while ($rowClasi = $resultClasi->fetch_assoc()) {
        $nameClasi = htmlspecialchars($rowClasi["clasificacion"]);
        $nameClasi2 = preg_replace("/[0-9]/", '', $nameClasi);  
        echo "<fieldset class='grupoHabilidad'>"; # ==== Inicio Fieldset
        echo "<legend><b>$nameClasi2</b></legend>";

        // Consulta para obtener habilidades por tipo y clasificación
        $consulta = "SELECT id, name FROM nuevo_habilidades WHERE tipo = ? AND clasificacion = ? ORDER BY name ASC";
        $stmtHabilidades = $link->prepare($consulta);
        $stmtHabilidades->bind_param('ss', $skillCategoryName, $nameClasi);
        $stmtHabilidades->execute();
        $resultHabilidades = $stmtHabilidades->get_result();
        $NFilas = $resultHabilidades->num_rows;

        while ($ResultQuery = $resultHabilidades->fetch_assoc()) {
            echo "
                <a href='index.php?p=skill&amp;b=" . htmlspecialchars($ResultQuery["id"]) . "'>
                    <div class='renglon3col'>
                        " . htmlspecialchars($ResultQuery["name"]) . "
                    </div>
                </a>
            ";
        }

        echo "</fieldset><br/>"; # ==== Fin Fieldset
        $numregistros += $NFilas;

        $stmtHabilidades->close();
    }
}

echo "<p align='right'>Habilidades: $numregistros</p>";

// Cerramos la sentencia preparada para las clasificaciones
$stmtClasi->close();
?>
