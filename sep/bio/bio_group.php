<?php
// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Obtener y sanitizar los parámetros de la URL
$idClan = isset($_GET['t']) ? intval($_GET['t']) : 0;
$idType = isset($_GET['b']) ? intval($_GET['b']) : 0;

$valuePJ = "id, nombre, alias, estado, img, kes"; // Datos que cogeremos de los PJS
$howMuch = 0; // Variable para contar los PJS

// ======================================== //
// Consultar el nombre del clan
$clanQuery = "SELECT name FROM nuevo2_clanes WHERE id = ? LIMIT 1";
$stmtClan = mysqli_prepare($link, $clanQuery);
mysqli_stmt_bind_param($stmtClan, 'i', $idClan);
mysqli_stmt_execute($stmtClan);
$resultClanQuery = mysqli_stmt_get_result($stmtClan);

if (mysqli_num_rows($resultClanQuery) > 0) { // Comprobamos si se encontraron resultados
    $rowClan = mysqli_fetch_assoc($resultClanQuery);
    $nombreClan = htmlspecialchars($rowClan["name"]); // Nombre del clan al que pertenecen los PJS

    // Consultar el nombre del tipo
    $typeQuery = "SELECT tipo FROM afiliacion WHERE id = ? LIMIT 1";
    $stmtType = mysqli_prepare($link, $typeQuery);
    mysqli_stmt_bind_param($stmtType, 'i', $idType);
    mysqli_stmt_execute($stmtType);
    $resultTypeQuery = mysqli_stmt_get_result($stmtType);
    $rowType = mysqli_fetch_assoc($resultTypeQuery);
    $nombreType = htmlspecialchars($rowType["tipo"]); // Nombre del Tipo de PJS

    $pageSect = "$nombreType - $nombreClan"; // PARA CAMBIAR EL TITULO A LA PAGINA

    // Encabezado y Barra de Navegación
    include("sep/main/main_nav_bar.php"); // Barra Navegación
    echo "<h2>$nombreClan</h2>"; // Título de la Página - Sección
    // Fin Encabezado y Barra de Navegación
    // ======================================== //
    // Organización por Crónica
    $queryCronica = "SELECT id, name FROM nuevo2_cronicas ORDER BY id";
    $resultCronica = mysqli_query($link, $queryCronica);
    
    while ($rowCronica = mysqli_fetch_assoc($resultCronica)) { // Vamos comprobando Crónica por Crónica
        $idCronica = $rowCronica["id"];
        $nombreCronica = ($rowCronica["name"]);
        
        // Selección Personajes y Orden por Crónica
        $queryPJ = "SELECT $valuePJ FROM pjs1 WHERE clan = ? AND tipo = ? AND cronica = ? ORDER BY manada";
        $stmtPJ = mysqli_prepare($link, $queryPJ);
        mysqli_stmt_bind_param($stmtPJ, 'iii', $idClan, $idType, $idCronica);
        mysqli_stmt_execute($stmtPJ);
        $resultPJ = mysqli_stmt_get_result($stmtPJ);
        $rowsQueryPJ = mysqli_num_rows($resultPJ);
        
        if ($rowsQueryPJ > 0) { // Si la Crónica no posee PJs, pasamos a la siguiente
            $howMuch += $rowsQueryPJ; // Sumamos los PJs encontrados!
            
            // Sección de Crónica
            echo "<fieldset class='grupoBioClan' style='background:;'>";
            echo "<legend><b>$nombreCronica</b></legend>";
            
            while ($rowPJ = mysqli_fetch_assoc($resultPJ)) {
                // Datos
                $nombrePJ = htmlspecialchars($rowPJ["nombre"]);    // Nombre del Personaje
                $aliasPJ = htmlspecialchars($rowPJ["alias"]);     // Alias del Personaje
                $imgPJ = htmlspecialchars($rowPJ["img"]);         // Avatar del Personaje
                $clasePJ = htmlspecialchars($rowPJ["kes"]);       // Si el PJ tiene ficha o no
                $estadoPJ = htmlspecialchars($rowPJ["estado"]);   // Estado del Personaje. Vivo, muerto, etc.
                
                // Ajustes
                if ($aliasPJ == "") { $aliasPJ = $nombrePJ; } // Si el PJ no tiene Alias, usamos su Nombre
                
                // Colocarle un color diferente si no tiene hoja de PJ
                switch($clasePJ) { 
                    case "pj": // Lo dejamos todo en blanco si tiene hoja de PJ
                        $fondoFoto = "";
                        $estiloLink = "";
                        break;
                    default: // Colocamos todo en Rojo si no tiene Hoja de PJ
                        $fondoFoto = "NoSheet";
                        $estiloLink = "color: #EE0000;";
                        break;
                }

                // Crear icono de estado
                switch($estadoPJ) { 
                    case "Aún por aparecer":
                        $simboloEstado = "(&#64)";
                        break;
                    case "Paradero desconocido":
                        $simboloEstado = "(&#63;)";
                        break;
                    case "Cadáver":
                        $simboloEstado = "(&#8224;)";
                        break;
                    default:
                        $simboloEstado = "";
                        break;
                }

                // Imprimir Datos en Pantalla
                echo "<a href='index.php?p=muestrabio&amp;b=" . htmlspecialchars($rowPJ["id"]) . "' title='$nombrePJ' style='$estiloLink'>"; // Enlace a la ficha del PJ
                    echo "<div class='marcoFotoBio$fondoFoto'>"; // Marco de la selección
                        echo "<div class='textoDentroFotoBio$fondoFoto'>$aliasPJ $simboloEstado</div>"; // Nombre (o alias) y Estado
                        echo "<div class='dentroFotoBio'><img class='fotoBioList' src='$imgPJ'></div>"; // Avatar
                    echo "</div>"; // Cierre de Marco
                echo "</a>"; // Cierre de Enlace
            }
            echo "</fieldset>";
        }
        mysqli_stmt_close($stmtPJ);
    }
    mysqli_free_result($resultCronica);
    // Fin Organización por Crónica
    // ======================================== //
    print ("<p align='right'>Personajes:".""." $howMuch</p>");
} else { // Fin chequeo de datos.
    echo "<p style='text-align:center;'>No se encontró el clan especificado.</p>"; // Mensaje de error en caso de introducir datos manualmente.
}

mysqli_stmt_close($stmtClan);
mysqli_stmt_close($stmtType);
mysqli_close($link);
?>
