<?php include("sep/main/main_nav_bar.php"); // Barra de Navegación ?>
<h2> Disciplinas </h2>
<fieldset class="grupoHabilidad">
<?php
$pageSect = "Disciplinas"; // PARA CAMBIAR EL TÍTULO DE LA PÁGINA

// Consulta segura usando MySQLi
$consulta = "SELECT id, name FROM nuevo2_tipo_disciplinas ORDER BY id";
$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$totalCategorias = $result->num_rows;

while ($ResultQuery = $result->fetch_assoc()) {
    $typeId = htmlspecialchars($ResultQuery["id"]);
    $typeName = htmlspecialchars($ResultQuery["name"]);

    echo "
        <a href='index.php?p=tipodisc&amp;b=$typeId' title='$typeName'>
            <div class='renglon3col'>
                $typeName
            </div>
        </a>
    ";
}
?>
</fieldset>
<?php echo "<p align='right'>Categorías: $totalCategorias</p>"; ?>
