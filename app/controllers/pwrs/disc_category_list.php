<?php
require_once __DIR__ . '/../../domains/powers/queries.php';

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
$types = hg_powers_fetch_types($link, 'disciplines');
if ($types === false) $types = [];
$totalCategorias = count($types);

foreach ($types as $ResultQuery) {
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
