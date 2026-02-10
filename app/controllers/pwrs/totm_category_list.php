<?php include("app/partials/main_nav_bar.php"); // Barra Navegación ?>
setMetaFromPage("Totems | Heaven's Gate", "Categorias de totems.", null, 'website');
<h2> Tótems </h2>
<fieldset class="grupoHabilidad">
<?php
$pageSect = "Tótems"; // PARA CAMBIAR EL TÍTULO DE LA PÁGINA

// Consulta segura usando MySQLi
$consulta = "SELECT id, name, determinante FROM dim_totem_types ORDER BY orden";
$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$totalCategorias = $result->num_rows;

while ($ResultQuery = $result->fetch_assoc()) {
    $typeID  = (int)$ResultQuery["id"];
    $typeName = htmlspecialchars($ResultQuery["name"]);
    $typeDet  = htmlspecialchars($ResultQuery["determinante"]);

    echo "
        <a href='" . htmlspecialchars(pretty_url($link, 'dim_totem_types', '/powers/totem/type', $typeID)) . "' title='$typeName'>
            <div class='renglon3col'>
                Tótems $typeDet $typeName
            </div>
        </a>
    ";
}
?>
</fieldset>
<?php echo "<p align='right'>Categorías: $totalCategorias</p>"; ?>
