<?php
require_once __DIR__ . '/../../domains/powers/queries.php';

include("app/partials/main_nav_bar.php");
setMetaFromPage("Dones | Heaven's Gate", "Categorias de dones.", null, 'website');
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-powers.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-powers.css">';
}
?>
<h2>Dones</h2>
<fieldset class="hg-powers-category-list">
<?php
    $pageSect = "Dones";
    $donTypePhrase = "Dones";
    $types = hg_powers_fetch_types($link, 'gifts');
    if ($types === false) $types = [];

    foreach ($types as $ResultQuery) {
        $typeId = (int)$ResultQuery["id"];
        $typeName = htmlspecialchars($ResultQuery["name"]);
        $determinant = htmlspecialchars($ResultQuery["determinant"]);
        print("
            <a href='" . htmlspecialchars(pretty_url($link, 'dim_gift_types', '/powers/gift/type', $typeId)) . "' title='$typeName'>
                <div class='hg-powers-category-card'>
                    $donTypePhrase $determinant $typeName
                </div>
            </a>
        ");
    }

    $numregistros = count($types);
?>
</fieldset>
<?php print ("<p align='right'>Categorías: $numregistros</p>"); ?>
