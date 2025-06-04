<?php
// Obtener el parámetro 'b' de manera segura
$mafCategory = isset($_GET['b']) ? $_GET['b'] : ''; 

include("maf_convert.php");
include("sep/main/main_nav_bar.php"); // Barra de Navegación
?>

<h2><?php echo htmlspecialchars($mafCategoryName); ?></h2>
<br/>
<fieldset class="grupoHabilidad">
<?php 
$pageSect = htmlspecialchars($mafCategoryName); // PARA CAMBIAR EL TITULO A LA PAGINA
$numregistros = 0;

/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
// Consulta para obtener las afiliaciones de mérito y defecto
$queryMeriF = "SELECT DISTINCT afiliacion FROM nuevo_mer_y_def WHERE tipo = ? ORDER BY afiliacion ASC";
$stmtMeriF = $link->prepare($queryMeriF);
$stmtMeriF->bind_param('s', $mafCategoryName);
$stmtMeriF->execute();
$resultMeriF = $stmtMeriF->get_result();
$filasMeriF = $resultMeriF->num_rows;

// Recorremos los resultados de la consulta
for ($c = 0; $c < $filasMeriF; $c++) {
    $resultMeriFArray = $resultMeriF->fetch_assoc();
    $nameMeriF = htmlspecialchars($resultMeriFArray["afiliacion"]);
    $typeLink = $c + 1;
    
    echo "
        <a href='index.php?p=mfgroup&amp;t=$mafCategory&amp;b=$typeLink' title='$nameMeriF'>
            <div class='renglon3col'>
                $nameMeriF
            </div>
        </a>
    ";
}

$numregistros = $filasMeriF;

?>
</fieldset>
<?php echo "<p align='right'>Categor&iacute;as halladas: $numregistros</p>"; ?>

<?php
// Cerramos la sentencia preparada
$stmtMeriF->close();
?>
