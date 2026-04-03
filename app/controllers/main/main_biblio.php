<?php setMetaFromPage("Bibliografia | Heaven's Gate", "Bibliografia y referencias de la campana.", null, 'website'); ?>
<?php include_once(__DIR__ . '/../../helpers/public_response.php'); ?>
<?php include("app/partials/main_nav_bar.php"); // Barra Navegacion ?>
<link rel="stylesheet" href="/assets/css/hg-main.css">
<h2>Bibliograf&iacute;a</h2>
<fieldset class="grupoHabilidad">
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

        echo "<div class='renglonBiblio main-biblio-row' title='$descBook'>$nameBook <div class='main-biblio-year'>$goodYearBook</div></div>";
    }

    // Liberar el resultado de la consulta
    mysqli_free_result($IdConsulta);
    ?>
</fieldset>
