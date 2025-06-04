<?php include("sep/main/main_nav_bar.php"); // Barra de Navegación ?>
<h2> Rituales </h2>
<fieldset class="grupoHabilidad">
<?php
$pageSect = "Rituales"; // PARA CAMBIAR EL TÍTULO DE LA PÁGINA
$sustantivo = "Ritos";

// Consulta segura usando MySQLi
$consulta = "SELECT id, name, determinante FROM nuevo2_tipo_rituales ORDER BY orden";
$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$NFilas = $result->num_rows;
while ($ResultQuery = $result->fetch_assoc()) {
    $typeId = htmlspecialchars($ResultQuery["id"]);
    $typeName = htmlspecialchars($ResultQuery["name"]);
    $determinante = htmlspecialchars($ResultQuery["determinante"]);

    print("
        <a href='index.php?p=tiporite&amp;b=$typeId' title='$typeName'>
            <div class='renglon3col'>
                $sustantivo $determinante $typeName
            </div>
        </a>
    ");
}

$totalCategorias = $NFilas;
?>
</fieldset>
<?php print ("<p align='right'>Categorías: $totalCategorias</p>"); ?>
