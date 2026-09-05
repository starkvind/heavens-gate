<?php
include("app/partials/main_nav_bar.php");
setMetaFromPage("Rituales | Heaven's Gate", "Categorias de rituales.", null, 'website');
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-powers.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-powers.css">';
}
?>
<h2>Rituales</h2>
<fieldset class="hg-powers-category-list">
<?php
$pageSect = "Rituales";
$sustantivo = "Ritos";

$consulta = "SELECT id, name, determinant FROM dim_rite_types ORDER BY sort_order";
$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$NFilas = $result->num_rows;
while ($ResultQuery = $result->fetch_assoc()) {
    $typeId = (int)$ResultQuery["id"];
    $typeName = htmlspecialchars($ResultQuery["name"]);
    $determinant = htmlspecialchars($ResultQuery["determinant"]);

    print("
        <a href='" . htmlspecialchars(pretty_url($link, 'dim_rite_types', '/powers/rite/type', $typeId)) . "' title='$typeName'>
            <div class='hg-powers-category-card'>
                $sustantivo $determinant $typeName
            </div>
        </a>
    ");
}

$totalCategorias = $NFilas;
?>
</fieldset>
<?php print ("<p align='right'>Categorías: $totalCategorias</p>"); ?>
