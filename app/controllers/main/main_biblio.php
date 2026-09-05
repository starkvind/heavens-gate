<?php setMetaFromPage("Bibliografia | Heaven's Gate", "Bibliografia y referencias de la campana.", null, 'website'); ?>
<?php include_once(__DIR__ . '/../../helpers/public_response.php'); ?>
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
    // Verificar si la conexion a la base de datos ($link) esta definida y es valida
    if (!$link) {
        hg_public_log_error('main_biblio', 'missing DB connection');
        hg_public_render_error('Bibliografia no disponible', 'No se pudo cargar la bibliografia en este momento.');
        return;
    }

    // Consulta para obtener la bibliografia ordenada por 'orden'
    $consulta = "SELECT id, name, year, description FROM dim_bibliographies ORDER BY sort_order";
    $IdConsulta = mysqli_query($link, $consulta);

    if (!$IdConsulta) {
        hg_public_log_error('main_biblio', 'query failed: ' . mysqli_error($link));
        hg_public_render_error('Bibliografia no disponible', 'No se pudo cargar la bibliografia en este momento.');
        return;
    }

    // Obtener el numero de filas del resultado
    $NFilas = mysqli_num_rows($IdConsulta);

    // Recorrer los resultados de la consulta y mostrar los datos
    while ($ResultQuery = mysqli_fetch_assoc($IdConsulta)) {
        $idBook = htmlspecialchars($ResultQuery["id"]);
        $nameBook = htmlspecialchars($ResultQuery["name"]);
        $yearBook = htmlspecialchars($ResultQuery["year"]);
        $descBook = htmlspecialchars($ResultQuery["description"]);
        $goodYearBook = $yearBook != 0 ? $yearBook : "";

        echo "<div class='hg-bibliography-entry' title='$descBook'><span>$nameBook</span><span class='hg-bibliography-entry__year'>$goodYearBook</span></div>";
    }

    // Liberar el resultado de la consulta
    mysqli_free_result($IdConsulta);
    ?>
</div>
