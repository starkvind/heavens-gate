<?php
// Obtener el parámetro 'b' de manera segura
$archeID = isset($_GET['b']) ? $_GET['b'] : '';  // ID del Arquetipo

// Preparar la consulta para evitar inyecciones SQL
$queryArche = "SELECT * FROM nuevo_personalidad WHERE id = ? LIMIT 1";
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
    $archeDesc = htmlspecialchars($resultQueryArche["desc"]);
    $archeWill = htmlspecialchars($resultQueryArche["fv"]);
    $archeOrig = htmlspecialchars($resultQueryArche["origen"]);

    // Seleccionar origen
    $archeOrigName = "Desconocido"; // Valor predeterminado si no hay origen
    if ($archeOrig != 0) {
        $queryOrigen = "SELECT name FROM nuevo2_bibliografia WHERE id = ? LIMIT 1";
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

    // Incluir barra de navegación
    include("sep/main/main_nav_bar.php"); // Barra de Navegación
    echo "<h2>$archeName</h2>"; // Encabezado de página

    // Incluir menú social
    include("sep/main/main_social_menu.php"); // Zona de Impresión y Redes Sociales

    // Cuerpo principal de la Ficha del Arquetipo
    echo "<fieldset class='renglonPaginaDon'>";

    // Orígenes del Arquetipo
    if ($archeOrig != 0) {
        echo "<div class='renglonDonIz'>Origen:</div>";
        echo "<div class='renglonDonDe'>$archeOrigName</div>";
    }

    // Descripción del Arquetipo
    if ($archeDesc != "") {
        echo "<div class='renglonDonData'>";
        echo "<b>Descripci&oacute;n:</b><p>$archeDesc</p>";
        echo "</div>";
    }

    // Recuperar Fuerza de Voluntad
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
