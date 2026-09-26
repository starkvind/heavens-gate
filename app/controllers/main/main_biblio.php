<?php setMetaFromPage("Bibliografia | Heaven's Gate", "Bibliografia y referencias de la campana.", null, 'website'); ?>
<?php include_once(__DIR__ . '/../../helpers/public_response.php'); ?>
<?php require_once(__DIR__ . '/../../domains/bibliography/queries.php'); ?>
<?php include("app/partials/main_nav_bar.php"); // Barra Navegacion ?>
<?php
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-bibliography.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-bibliography.css">';
}
?>
<h2>Bibliograf&iacute;a</h2>
<div class="hg-bibliography-list">
    <?php
    if (!$link) {
        hg_public_log_error('main_biblio', 'missing DB connection');
        hg_public_render_error('Bibliografia no disponible', 'No se pudo cargar la bibliografia en este momento.');
        return;
    }

    $entries = hg_bibliography_fetch_entries($link);
    if ($entries === null) {
        hg_public_log_error('main_biblio', 'query failed: ' . mysqli_error($link));
        hg_public_render_error('Bibliografia no disponible', 'No se pudo cargar la bibliografia en este momento.');
        return;
    }

    foreach ($entries as $entry) {
        $idBook = htmlspecialchars($entry["id"]);
        $nameBook = htmlspecialchars($entry["name"]);
        $yearBook = htmlspecialchars($entry["year"]);
        $descBook = htmlspecialchars($entry["description"]);
        $goodYearBook = $yearBook != 0 ? $yearBook : "";

        echo "<div class='hg-bibliography-entry' title='$descBook'><span>$nameBook</span><span class='hg-bibliography-entry__year'>$goodYearBook</span></div>";
    }
    ?>
</div>
