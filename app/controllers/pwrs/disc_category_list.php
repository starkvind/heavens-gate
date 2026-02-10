<?php include("app/partials/main_nav_bar.php"); // Barra de Navegación ?>
setMetaFromPage("Disciplinas | Heaven's Gate", "Categorias de disciplinas.", null, 'website');
<h2> Disciplinas </h2>
<fieldset class="grupoHabilidad">
<?php
$pageSect = "Disciplinas"; // PARA CAMBIAR EL TÍTULO DE LA PÁGINA

// Consulta segura usando MySQLi
$consulta = "SELECT id, name FROM dim_discipline_types ORDER BY id";
$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$totalCategorias = $result->num_rows;

while ($ResultQuery = $result->fetch_assoc()) {
    $typeId = (int)$ResultQuery["id"];
    $typeName = htmlspecialchars($ResultQuery["name"]);

    echo "
        <a href='" . htmlspecialchars(pretty_url($link, 'dim_discipline_types', '/powers/discipline/type', $typeId)) . "' title='$typeName'>
            <div class='renglon3col'>
                $typeName
            </div>
        </a>
    ";
}
?>
</fieldset>
<?php echo "<p align='right'>Categorías: $totalCategorias</p>"; ?>
