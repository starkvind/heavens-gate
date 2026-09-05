<?php
include("app/partials/main_nav_bar.php");
setMetaFromPage("Totems | Heaven's Gate", "Categorias de totems.", null, 'website');
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-powers.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-powers.css">';
}
?>
<h2>Tótems</h2>
<fieldset class="hg-powers-category-list">
<?php
$pageSect = "Tótems";

$consulta = "SELECT id, name, determinant FROM dim_totem_types ORDER BY sort_order";
$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$totalCategorias = $result->num_rows;

while ($ResultQuery = $result->fetch_assoc()) {
    $typeID  = (int)$ResultQuery["id"];
    $typeName = htmlspecialchars($ResultQuery["name"]);
    $typeDet  = htmlspecialchars($ResultQuery["determinant"]);

    echo "
        <a href='" . htmlspecialchars(pretty_url($link, 'dim_totem_types', '/powers/totem/type', $typeID)) . "' title='$typeName'>
            <div class='hg-powers-category-card'>
                Tótems $typeDet $typeName
            </div>
        </a>
    ";
}
?>
</fieldset>
<?php echo "<p align='right'>Categorías: $totalCategorias</p>"; ?>
