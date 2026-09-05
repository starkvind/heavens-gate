<?php
include("app/partials/main_nav_bar.php");
setMetaFromPage("Disciplinas | Heaven's Gate", "Categorias de disciplinas.", null, 'website');
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-powers.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-powers.css">';
}
?>
<h2>Disciplinas</h2>
<fieldset class="hg-powers-category-list">
<?php
$pageSect = "Disciplinas";

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
            <div class='hg-powers-category-card'>
                $typeName
            </div>
        </a>
    ";
}
?>
</fieldset>
<?php echo "<p align='right'>Categorías: $totalCategorias</p>"; ?>
