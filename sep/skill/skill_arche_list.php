<?php 
$skillCategoryName = "Arquetipos de personalidad"; 
include("sep/main/main_nav_bar.php"); // Barra de Navegación
?>
<h2><?php echo htmlspecialchars($skillCategoryName); ?></h2>
<fieldset class="grupoHabilidad">
<?php 
$pageSect = "Arquetipos de personalidad"; // PARA CAMBIAR EL TITULO A LA PAGINA
///////////////////////////////////////////////////////////////////////////

// Consulta para contar el número total de registros
$consultaPag = "SELECT COUNT(*) as total FROM nuevo_personalidad";
$resultPag = $link->query($consultaPag);
$num_total_registros = 0;

if ($resultPag) {
    $row = $resultPag->fetch_assoc();
    $num_total_registros = $row['total'];
}
///////////////////////////////////////////////////////////////////////////

// Consulta para obtener los arquetipos de personalidad ordenados por nombre
$consulta = "SELECT id, name FROM nuevo_personalidad ORDER BY name ASC";
$result = $link->query($consulta);
$numregistros = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "
            <a 
                href='index.php?p=verarch&amp;b=" . htmlspecialchars($row["id"]) . "' 
                title='" . htmlspecialchars($row["name"]) . "'
            >
                <div class='renglon3col'>
                    " . htmlspecialchars($row["name"]) . "
                </div>
            </a>
        ";  
        $numregistros++;
    }
}
?>
</fieldset>
<?php 
    echo "<p align='right'>Arquetipos de personalidad: $numregistros</p>";
?>
