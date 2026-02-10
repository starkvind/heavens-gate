<?php include("app/partials/main_nav_bar.php"); // Barra de Navegación ?>
setMetaFromPage("Rituales | Heaven's Gate", "Categorias de rituales.", null, 'website');
<h2> Rituales </h2>
<fieldset class="grupoHabilidad">
<?php
$pageSect = "Rituales"; // PARA CAMBIAR EL TÍTULO DE LA PÁGINA
$sustantivo = "Ritos";

// Consulta segura usando MySQLi
$consulta = "SELECT id, name, determinante FROM dim_rite_types ORDER BY orden";
$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$NFilas = $result->num_rows;
while ($ResultQuery = $result->fetch_assoc()) {
    $typeId = (int)$ResultQuery["id"];
    $typeName = htmlspecialchars($ResultQuery["name"]);
    $determinante = htmlspecialchars($ResultQuery["determinante"]);

    print("
        <a href='" . htmlspecialchars(pretty_url($link, 'dim_rite_types', '/powers/rite/type', $typeId)) . "' title='$typeName'>
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
