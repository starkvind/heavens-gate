<?php include("sep/main/main_nav_bar.php"); // Barra Navegación ?>
<h2> Tótems </h2>
<fieldset class="grupoHabilidad">
<?php
$pageSect = "Tótems"; // PARA CAMBIAR EL TÍTULO DE LA PÁGINA

// Consulta segura usando MySQLi
$consulta = "SELECT id, name, determinante FROM nuevo2_tipo_totems ORDER BY orden";
$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$totalCategorias = $result->num_rows;

while ($ResultQuery = $result->fetch_assoc()) {
    $typeID  = htmlspecialchars($ResultQuery["id"]);
    $typeName = htmlspecialchars($ResultQuery["name"]);
    $typeDet  = htmlspecialchars($ResultQuery["determinante"]);

    echo "
        <a href='index.php?p=tipototm&amp;b=$typeID' title='$typeName'>
            <div class='renglon3col'>
                Tótems $typeDet $typeName
            </div>
        </a>
    ";
}
?>
</fieldset>
<?php echo "<p align='right'>Categorías: $totalCategorias</p>"; ?>
