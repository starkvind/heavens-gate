<?php
// Obtener el parámetro 'b' de manera segura
$maneuverId = isset($_GET['b']) ? $_GET['b'] : '';  // ID de la Maniobra

// Preparar la consulta para evitar inyecciones SQL
$queryManeuver = "SELECT * FROM nuevo2_maniobras WHERE id = ?";
$stmtManeuver = $link->prepare($queryManeuver);
$stmtManeuver->bind_param('s', $maneuverId);
$stmtManeuver->execute();
$resultManeuver = $stmtManeuver->get_result();
$rowsQueryManeuver = $resultManeuver->num_rows;

// Comprobamos si hay resultados
if ($rowsQueryManeuver > 0) {
    $resultQueryManeuver = $resultManeuver->fetch_assoc();

    // Datos básicos de la maniobra
    $maneID = htmlspecialchars($resultQueryManeuver["id"]);
    $maneName = htmlspecialchars($resultQueryManeuver["name"]);
    $maneText = htmlspecialchars($resultQueryManeuver["text"]);
    $maneUser = htmlspecialchars($resultQueryManeuver["user"]);
    $maneRoll = htmlspecialchars($resultQueryManeuver["roll"]);
    $maneDiff = htmlspecialchars($resultQueryManeuver["difficulty"]);
    $maneDamg = htmlspecialchars($resultQueryManeuver["damage"]);
    $maneActi = htmlspecialchars($resultQueryManeuver["actions"]);
    $maneSist = htmlspecialchars($resultQueryManeuver["sistema"]);
    $maneOrig = htmlspecialchars($resultQueryManeuver["origen"]);

    // Seleccionar origen
    $queryOrigen = "SELECT name FROM nuevo2_bibliografia WHERE id = ? LIMIT 1";
    $stmtOrigen = $link->prepare($queryOrigen);
    $stmtOrigen->bind_param('s', $maneOrig);
    $stmtOrigen->execute();
    $resultOrigen = $stmtOrigen->get_result();
    if ($resultOrigen->num_rows > 0) {
        $resultQueryManeuverOrigen = $resultOrigen->fetch_assoc();
        $maneOrig = htmlspecialchars($resultQueryManeuverOrigen["name"]);
    } else {
        $maneOrig = "Desconocido";
    }
    $stmtOrigen->close();

    // Imágenes y Título
    $pageSect = "Maniobra"; // Título de la Página
    $pageTitle2 = $maneName; 

    // Incluir barra de navegación
    include("sep/main/main_nav_bar.php");  // Barra de Navegación
    echo "<h2>$maneName</h2>"; // Encabezado de página

    // Incluir menú social
    include("sep/main/main_social_menu.php");  // Zona de Impresión y Redes Sociales

    // Cuerpo principal de la Ficha del Tótem
    echo "<fieldset class='renglonPaginaDon'>";

    // Formas en las que se puede usar
    if ($maneUser != "") {
        echo "<div class='renglonDonIz'>Formas:</div>";
        echo "<div class='renglonDonDe'>$maneUser</div>";
    }

    // Tirada y Dificultad
    if ($maneRoll != "") {
        echo "<div class='renglonDonIz'>Tirada:</div>";
        echo "<div class='renglonDonDe'>$maneRoll ($maneDiff)</div>";
    }

    // Daño de la Maniobra
    if ($maneDamg != "") {
        echo "<div class='renglonDonIz'>Daño:</div>";
        echo "<div class='renglonDonDe'>$maneDamg</div>";
    }

    // Acciones de la Maniobra
    if ($maneActi != "") {
        echo "<div class='renglonDonIz'>Acciones:</div>";
        echo "<div class='renglonDonDe'>$maneActi</div>";
    }

    // Raza de la Maniobra
    if ($maneSist != "") {
        echo "<div class='renglonDonIz'>Raza:</div>";
        echo "<div class='renglonDonDe'>$maneSist</div>";
    }

    // Orígenes de la Maniobra
    if ($maneOrig != "") {
        echo "<div class='renglonDonIz'>Origen:</div>";
        echo "<div class='renglonDonDe'>$maneOrig</div>"; 
    }

    // Descripción de la Maniobra
    if ($maneText != "") {
        echo "<div class='renglonDonData'>";
        echo "<b>Descripci&oacute;n:</b><p>$maneText</p>";
        echo "</div>";
    }

    echo "</fieldset>";

} // Fin comprobación

// Cerramos la sentencia preparada para la consulta principal
$stmtManeuver->close();
?>
