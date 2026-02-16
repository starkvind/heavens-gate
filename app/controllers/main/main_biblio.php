<?php setMetaFromPage("Bibliograf?a | Heaven's Gate", "Bibliograf?a y referencias de la campa?a.", null, 'website'); ?>
<?php include("app/partials/main_nav_bar.php"); // Barra Navegación ?>
<h2>Bibliograf&iacute;a</h2>
<fieldset class="grupoHabilidad">
    <?php
    // Verificar si la conexión a la base de datos ($link) está definida y es válida
    if (!$link) {
        die("Error de conexión a la base de datos: " . mysqli_connect_error());
    }

    // Consulta para obtener la bibliografía ordenada por 'orden'
    $consulta = "SELECT id, name, year, description FROM dim_bibliographies ORDER BY sort_order";
    $IdConsulta = mysqli_query($link, $consulta);

    if (!$IdConsulta) {
        die("Error en la consulta: " . mysqli_error($link));
    }

    // Obtener el número de filas del resultado
    $NFilas = mysqli_num_rows($IdConsulta);

    // Recorrer los resultados de la consulta y mostrar los datos
    while ($ResultQuery = mysqli_fetch_assoc($IdConsulta)) {
        $idBook = htmlspecialchars($ResultQuery["id"]);
        $nameBook = htmlspecialchars($ResultQuery["name"]);
        $yearBook = htmlspecialchars($ResultQuery["year"]);
        $descBook = htmlspecialchars($ResultQuery["description"]);
        $goodYearBook = $yearBook != 0 ? $yearBook : "";

        echo "<div class='renglonBiblio' style='text-align: left;' title='$descBook'>$nameBook <div style='float:right;'>$goodYearBook</div></div>";
    }

    // Liberar el resultado de la consulta
    mysqli_free_result($IdConsulta);
    ?>
</fieldset>

