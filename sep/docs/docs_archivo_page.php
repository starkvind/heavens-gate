<?php

// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Sanitizar y obtener el parámetro 't' de la URL
$gest = filter_input(INPUT_GET, 't', FILTER_SANITIZE_STRING);

// Preparar la consulta para obtener detalles del capítulo
$Query = "SELECT * FROM archivos_capitulos WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $Query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $gest);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $ResultQuery = mysqli_fetch_assoc($result);
        
        // Asignar valores de la base de datos a las variables
        $nameCapi 	= $ResultQuery["name"];
        $sinoCapi 	= $ResultQuery["sinopsis"];
        $noSinoCapi	= "Este capítulo no dispone de información, disculpa las molestias.";
        $tempCapi 	= $ResultQuery["temporada"];
        $numeCapi	= $ResultQuery["capitulo"];
        $protCapi	= $ResultQuery["protagonistas"];
        $dateCapi	= $ResultQuery["fecha"];

        // Títulos para diferentes secciones
        $titleInfo 	= "Resumen";
        $titleProta	= "Participantes";

        // Preparamos la temporada
        $tempQuery = "SELECT id, name, numero FROM archivos_temporadas WHERE numero = ? LIMIT 1";
        $stmtTemp = mysqli_prepare($link, $tempQuery);
        if ($stmtTemp) {
            mysqli_stmt_bind_param($stmtTemp, 's', $tempCapi);
            mysqli_stmt_execute($stmtTemp);
            $resultTemp = mysqli_stmt_get_result($stmtTemp);
            $resultDataTemp = mysqli_fetch_assoc($resultTemp);

            $idTemporada   = $resultDataTemp["id"];
            $nameTemporada = $resultDataTemp["name"];
            $numbTemporada = $resultDataTemp["numero"];
        }

        // Preparamos los títulos y nombres
        $checkNumCapi = ($numeCapi < 10) ? '0' : '';
        $goodNumTemp = ($numbTemporada >= 100) ? '' : $numbTemporada;
        $numeracionOK = ($numbTemporada < 99) ? "{$goodNumTemp}x{$checkNumCapi}{$numeCapi}" : "{$checkNumCapi}{$numeCapi}";

        $goodFecha	= date("d-m-Y", strtotime($dateCapi));

        // Cambiar el título de la página
        $pageSect = "{$nameTemporada} {$numeracionOK}";
        $pageTitle2	= $nameCapi;

        include("sep/main/main_nav_bar.php");	// Barra Navegación
        echo "<h2>{$nameCapi}</h2>";
        include("sep/main/main_social_menu.php");	// Zona de Impresión y Redes Sociales

        echo "<div class='bioBody'>"; // Cuerpo principal de la Ficha de Temporada

        // Sección Sinopsis
        echo "<fieldset id='renglonArchivosTop'>";
        echo "<legend id='archivosLegend'>{$titleInfo}</legend>";
        if ($dateCapi != "0000-00-00") {
            echo "<p><b>Fecha de juego:</b> {$goodFecha}</p>";
        }
        echo (!empty($sinoCapi)) ? "<p>{$sinoCapi}</p>" : "<p>{$noSinoCapi}</p>";
        echo "</fieldset>";

        // Sección Protagonistas (solo si hay personajes)
        if (!empty($protCapi)) {
            echo "<fieldset id='renglonArchivos'>";
            echo "<legend id='archivosLegend'>{$titleProta}</legend>";
            echo "<center>";

            $idsDePjs = explode(";", $protCapi);
            foreach ($idsDePjs as $idPJSelect) {
                $consultaPJ = "SELECT nombre, img FROM pjs1 WHERE id = ?";
                $stmtPJ = mysqli_prepare($link, $consultaPJ);
                if ($stmtPJ) {
                    mysqli_stmt_bind_param($stmtPJ, 's', $idPJSelect);
                    mysqli_stmt_execute($stmtPJ);
                    $resultPJ = mysqli_stmt_get_result($stmtPJ);

                    if ($resultPJ && mysqli_num_rows($resultPJ) > 0) {
                        while ($ResultQueryPJ = mysqli_fetch_assoc($resultPJ)) {
                            echo "<a href='index.php?p=muestrabio&amp;b={$idPJSelect}' title='" . htmlspecialchars($ResultQueryPJ["nombre"]) . "' target='_blank'>";
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

        echo "</div>"; // Cierre del Cuerpo Principal

        mysqli_free_result($result);
        if (isset($resultTemp)) {
            mysqli_free_result($resultTemp);
        }
        mysqli_stmt_close($stmt);
        if (isset($stmtTemp)) {
            mysqli_stmt_close($stmtTemp);
        }
    } else {
        echo "No se encontraron resultados para la búsqueda.";
    }
} else {
    echo "Error al preparar la consulta: " . mysqli_error($link);
}
?>
