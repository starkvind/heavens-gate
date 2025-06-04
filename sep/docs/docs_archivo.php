<?php
// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Sanitizar y obtener el parámetro 't' de la URL
$punk = filter_input(INPUT_GET, 't', FILTER_SANITIZE_STRING);

// Preparar la consulta para obtener detalles de la temporada
$consulta = "SELECT * FROM archivos_temporadas WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $consulta);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $punk);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($ResultQuery = mysqli_fetch_assoc($result)) {
            // Asignar valores de la base de datos a las variables
            $nameTemp = $ResultQuery["name"];
            $numberTemp = $ResultQuery["numero"];
            $sinopsis = $ResultQuery["desc"];
            $linkYoutube = $ResultQuery["opening"];
            $charas = $ResultQuery["protagonistas"];

            // Títulos para diferentes secciones
            $titleSinop = "Sinopsis";
            $titleProta = "Protagonistas";
            $titleChapt = "Capítulos";
            $titleOpeni = "Opening";

            // Determinar el título de la página basado en la temporada
            $esTempOno = $ResultQuery["season"];
            $pageSect = ($esTempOno == 0) ? "Archivo" : "Historia personal";
            $pageTitle2 = $nameTemp;

            include("sep/main/main_nav_bar.php"); // Barra Navegación
            echo "<h2>$nameTemp</h2>";
            include("sep/main/main_social_menu.php"); // Barra Navegación

            echo "<div class='bioBody'>"; // Cuerpo principal de la Ficha de Temporada

            // Sección Sinopsis
            echo "<fieldset id='renglonArchivosTop'>";
            echo "<legend id='archivosLegend'>$titleSinop</legend>";
            echo "<p>$sinopsis</p>";
            echo "</fieldset>";

            // Sección Protagonistas (solo si hay protagonistas)
            if (!empty($charas)) {
                echo "<fieldset id='renglonArchivos'>";
                echo "<legend id='archivosLegend'>$titleProta</legend>";
                echo "<center>";

                // Obtener personajes protagonistas
                $idsDePjs = explode(";", $charas);
                foreach ($idsDePjs as $idPJSelect) {
                    $consultaPJ = "SELECT nombre, img FROM pjs1 WHERE id = ?";
                    $stmtPJ = mysqli_prepare($link, $consultaPJ);
                    if ($stmtPJ) {
                        mysqli_stmt_bind_param($stmtPJ, 's', $idPJSelect);
                        mysqli_stmt_execute($stmtPJ);
                        $resultPJ = mysqli_stmt_get_result($stmtPJ);

                        if ($resultPJ) {
                            while ($ResultQueryPJ = mysqli_fetch_assoc($resultPJ)) {
                                echo "<a href='index.php?p=muestrabio&amp;b=$idPJSelect' title='" . htmlspecialchars($ResultQueryPJ["nombre"]) . "' target='_blank'>";
                                echo "<img src='" . htmlspecialchars($ResultQueryPJ["img"]) . "' class='photochapter'>";
                                echo "</a>";
                            }
                        }
                        mysqli_free_result($resultPJ);
                        mysqli_stmt_close($stmtPJ);
                    }
                }
                echo "</center>";
                echo "</fieldset>";
            }

            // Sección Capítulos
            echo "<fieldset id='renglonArchivos' style='padding-left:46px;'>";
            echo "<legend id='archivosLegend' style='margin-left:-36px;'>$titleChapt</legend>";

            $consultaChapt = "SELECT id, name, capitulo FROM archivos_capitulos WHERE temporada = ? ORDER BY capitulo";
            $stmtChapt = mysqli_prepare($link, $consultaChapt);
            if ($stmtChapt) {
                mysqli_stmt_bind_param($stmtChapt, 's', $numberTemp);
                mysqli_stmt_execute($stmtChapt);
                $resultChapt = mysqli_stmt_get_result($stmtChapt);

                if ($resultChapt && mysqli_num_rows($resultChapt) > 0) {
                    while ($ResultQueryChapt = mysqli_fetch_assoc($resultChapt)) {
                        $idEpi = $ResultQueryChapt["id"];
                        $nameEpi = $ResultQueryChapt["name"];
                        $capiEpi = $ResultQueryChapt["capitulo"];

                        // Definir estilo del popup de capítulos
                        if ($esTempOno == 0) {
                            if ($capiEpi < 10 && $numberTemp < 100) { $capiEpi = "0$capiEpi"; }
                            $numeEpi = ($numberTemp < 100) ? "Capítulo $numberTemp"."x$capiEpi" : "Capítulo $capiEpi";
                        } else {
                            $numeEpi = "Capítulo $capiEpi";
                        }

                        echo "<a href='index.php?p=seechapter&amp;t=$idEpi'>";
                        echo "<div class='renglon2col' style='text-align:center;' title='$numeEpi'>$nameEpi</div>";
                        echo "</a>";
                    }
                }
                mysqli_free_result($resultChapt);
                mysqli_stmt_close($stmtChapt);
            }

            echo "</fieldset>";

            // Sección Opening (solo si hay enlace de YouTube)
            if (!empty($linkYoutube)) {
                echo "<fieldset id='renglonArchivos'>";
                echo "<legend id='archivosLegend'>$titleOpeni</legend>";
                ?>
                <center>
                    <object width="350" height="293">
                        <param name="movie" value="https://www.youtube-nocookie.com/v/<?php echo htmlspecialchars($linkYoutube); ?>?fs=1&amp;hl=es_ES&amp;rel=0"></param>
                        <param name="allowFullScreen" value="true"></param>
                        <param name="allowscriptaccess" value="always"></param>
                        <embed src="https://www.youtube-nocookie.com/v/<?php echo htmlspecialchars($linkYoutube); ?>?fs=1&amp;hl=es_ES&amp;rel=0" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="350" height="293"></embed>
                    </object>
                </center>
                <?php
                echo "</fieldset>";
            }

            echo "</div>"; // Cierre del Cuerpo Principal
        }
    } else {
        echo "No se encontraron resultados para la búsqueda.";
    }

    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
} else {
    echo "Error al preparar la consulta: " . mysqli_error($link);
}
?>
