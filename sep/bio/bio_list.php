<?php
// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Sanitizar y obtener el parámetro 't' de la URL
$idType = filter_input(INPUT_GET, 't', FILTER_SANITIZE_STRING);

// Preparar la consulta para obtener el tipo de afiliación
$queryType = "SELECT id, tipo FROM afiliacion WHERE id = ? LIMIT 1";
$stmtType = mysqli_prepare($link, $queryType);
if ($stmtType) {
    mysqli_stmt_bind_param($stmtType, 's', $idType);
    mysqli_stmt_execute($stmtType);
    $resultType = mysqli_stmt_get_result($stmtType);

    if ($resultType && mysqli_num_rows($resultType) > 0) {
        $resultQueryType = mysqli_fetch_assoc($resultType);
        $nameTypeReal = $resultQueryType["tipo"];

        $howMuch = 0;

        if (!empty($nameTypeReal)) {
            $pageSect = "Biografías - " . htmlspecialchars($nameTypeReal); // PARA CAMBIAR EL TITULO A LA PAGINA

            // Orden Guay
            include("sep/main/main_nav_bar.php");	// Barra Navegación
            echo "<h2>" . htmlspecialchars($nameTypeReal) . "</h2>";

            // Imprimir el campo de biografías
            print("<fieldset class='grupoBioClan'>");

            // Preparar la consulta para obtener los clanes
            $queryClan = "SELECT id, name FROM nuevo2_clanes ORDER BY orden";
            $stmtClan = mysqli_prepare($link, $queryClan);
            if ($stmtClan) {
                mysqli_stmt_execute($stmtClan);
                $resultClan = mysqli_stmt_get_result($stmtClan);
                $rowsQueryClan = mysqli_num_rows($resultClan);

                // Iterar sobre cada clan y realizar consultas adicionales
                while ($resultQueryClan = mysqli_fetch_assoc($resultClan)) {
                    $idClan = $resultQueryClan["id"];
                    $nombreClan = $resultQueryClan["name"];

                    // Preparar la consulta para contar los personajes por tipo y clan
                    $countQuery = "SELECT COUNT(id) as count FROM pjs1 WHERE tipo = ? AND clan = ?";
                    $stmtCount = mysqli_prepare($link, $countQuery);
                    if ($stmtCount) {
                        mysqli_stmt_bind_param($stmtCount, 'ss', $idType, $idClan);
                        mysqli_stmt_execute($stmtCount);
                        $resultCount = mysqli_stmt_get_result($stmtCount);
                        $rowsCountQuery = mysqli_fetch_assoc($resultCount)['count'];

                        if ($rowsCountQuery > 0) {
                            $howMuch++;
                            print("<a href='index.php?p=biogroup&amp;b=$idType&amp;t=$idClan'>
                                    <div class='renglon2col' style='text-align: center;'>
                                        " . ($nombreClan) . "
                                    </div>
                                </a>");
                        }

                        mysqli_free_result($resultCount);
                        mysqli_stmt_close($stmtCount);
                    }
                }

                mysqli_free_result($resultClan);
                mysqli_stmt_close($stmtClan);
            }

            print("</fieldset>");
            // Mostrar el número de categorías encontradas
            print("<p align='right'>Categorías: " . htmlspecialchars($howMuch) . "</p>");
        } else {
            echo "<p style='text-align:center;'>$mensajeDeError</p>";
        }
    } else {
        echo "<p style='text-align:center;'>No se encontró el tipo de afiliación.</p>";
    }

    mysqli_free_result($resultType);
    mysqli_stmt_close($stmtType);
} else {
    echo "Error al preparar la consulta: " . mysqli_error($link);
}
?>
