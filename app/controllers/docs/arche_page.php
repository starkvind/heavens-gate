<?php
// Obtener el parámetro 'b' de manera segura
$archeID = isset($_GET['b']) ? $_GET['b'] : '';  // ID del Arquetipo

// Preparar la consulta para evitar inyecciones SQL
$queryArche = "SELECT * FROM dim_archetypes WHERE id = ? LIMIT 1";
$stmtArche = $link->prepare($queryArche);
$stmtArche->bind_param('s', $archeID);
$stmtArche->execute();
$resultArche = $stmtArche->get_result();
$rowsQueryArche = $resultArche->num_rows;

// Comprobamos si hay resultados
if ($rowsQueryArche > 0) {
    $resultQueryArche = $resultArche->fetch_assoc();

    // Datos básicos
    $archeName = htmlspecialchars($resultQueryArche["name"]);
    $archeDesc = $resultQueryArche["desc"]; // keep HTML from DB
    $archeWill = $resultQueryArche["fv"];
    $archeOrig = htmlspecialchars($resultQueryArche["origen"]);

    // Seleccionar origen
    $archeOrigName = "-"; // Valor predeterminado si no hay origen
    if ($archeOrig != 0) {
        $queryOrigen = "SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1";
        $stmtOrigen = $link->prepare($queryOrigen);
        $stmtOrigen->bind_param('s', $archeOrig);
        $stmtOrigen->execute();
        $resultOrigen = $stmtOrigen->get_result();
        if ($resultOrigen->num_rows > 0) {
            $resultQueryArcheOrigen = $resultOrigen->fetch_assoc();
            $archeOrigName = htmlspecialchars($resultQueryArcheOrigen["name"]);
        }
        $stmtOrigen->close();
    }

    // Imágenes y Título
    $pageSect = "Arquetipo"; // Título de la Página
    $pageTitle2 = $archeName;
    setMetaFromPage($archeName . " | Arquetipos | Heaven's Gate", meta_excerpt($archeDesc), null, 'article');

    // Incluir barra de navegación
    include("app/partials/main_nav_bar.php"); // Barra de Navegación
    echo "<h2>$archeName</h2>"; // Encabezado de página

    // Cuerpo principal de la Ficha del Arquetipo
    echo "<fieldset class='renglonPaginaDon'>";

    // Imagen del Arquetipo
    $itemImg = "img/inv/no-photo.gif"; // Valor por defecto si no hay imagen

    echo "<div class='itemSquarePhoto' style='padding-left:4px;'>";
    echo "<img class='photobio' style='width:100px;height:100px;' src='$itemImg' alt='$archeName'/>";
    echo "</div>";

    // Datos generales del Arquetipo
    echo "<div class='bioSquareData'>";

    // Orígenes del Arquetipo
    if ($archeOrig != 0) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Origen:</div>";
        echo "<div class='bioDataText'>$archeOrigName</div>";
        echo "</div>";
    }

    echo "</div>";

    // Descripción del Arquetipo
    if ($archeDesc != "") {
        echo "<div class='renglonDonData'>";
        echo "<b>Descripci&oacute;n:</b><p>$archeDesc</p>";
        echo "</div>";
    }


    // Fuerza de Voluntad
    if ($archeWill != "") {
        echo "<div class='renglonDonData'>";
        echo "<b>Fuerza de Voluntad:</b><p>$archeWill</p>";
        echo "</div>";
    }

    echo "</fieldset>";
}

// Cerramos la sentencia preparada para la consulta principal
$stmtArche->close();
?>
