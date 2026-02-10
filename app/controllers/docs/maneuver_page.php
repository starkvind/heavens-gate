<?php

// Obtener el parámetro 'b' de manera segura

$maneuverId = isset($_GET['b']) ? $_GET['b'] : '';  // ID de la Maniobra



// Preparar la consulta para evitar inyecciones SQL

$queryManeuver = "SELECT * FROM fact_combat_maneuvers WHERE id = ?";

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

    $maneText = $resultQueryManeuver["text"]; // keep HTML from DB

    $maneUser = htmlspecialchars($resultQueryManeuver["user"]);

    $maneRoll = htmlspecialchars($resultQueryManeuver["roll"]);

    $maneDiff = htmlspecialchars($resultQueryManeuver["difficulty"]);

    $maneDamg = htmlspecialchars($resultQueryManeuver["damage"]);

    $maneActi = htmlspecialchars($resultQueryManeuver["actions"]);

    $maneSist = htmlspecialchars($resultQueryManeuver["sistema"]);

    $maneOrig = htmlspecialchars($resultQueryManeuver["origen"]);



    // Seleccionar origen

    $queryOrigen = "SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1";

    $stmtOrigen = $link->prepare($queryOrigen);

    $stmtOrigen->bind_param('s', $maneOrig);

    $stmtOrigen->execute();

    $resultOrigen = $stmtOrigen->get_result();

    if ($resultOrigen->num_rows > 0) {

        $resultQueryManeuverOrigen = $resultOrigen->fetch_assoc();

        $maneOrig = htmlspecialchars($resultQueryManeuverOrigen["name"]);

    } else {

        $maneOrig = "-";

    }

    $stmtOrigen->close();



    // Imágenes y Título

    $pageSect = "Maniobra"; // Título de la Página

    $pageTitle2 = $maneName; 

    setMetaFromPage($maneName . " | Maniobras | Heaven's Gate", meta_excerpt($maneText), null, 'article');



    // Incluir barra de navegación

    include("app/partials/main_nav_bar.php");  // Barra de Navegación

    echo "<h2>$maneName</h2>"; // Encabezado de página



    // Cuerpo principal de la Ficha del Tótem

    echo "<fieldset class='renglonPaginaDon'>";



    // Imagen de la Maniobra

    $itemImg = "img/inv/no-photo.gif"; // Valor por defecto si no hay imagen



    echo "<div class='itemSquarePhoto' style='padding-left:4px;'>";

    echo "<img class='photobio' style='width:100px;height:100px;' src='$itemImg' alt='$maneName'/>";

    echo "</div>";



    // Datos generales de la Maniobra

    echo "<div class='bioSquareData'>";



    // Formas en las que se puede usar

    if ($maneUser != "") {

        echo "<div class='bioRenglonData'>";

        echo "<div class='bioDataName'>Formas:</div>";

        echo "<div class='bioDataText'>$maneUser</div>";

        echo "</div>";

    }



    // Tirada y Dificultad

    if ($maneRoll != "") {

        echo "<div class='bioRenglonData'>";

        echo "<div class='bioDataName'>Tirada:</div>";

        echo "<div class='bioDataText'>$maneRoll ($maneDiff)</div>";

        echo "</div>";

    }



    // Daño de la Maniobra

    if ($maneDamg != "") {

        echo "<div class='bioRenglonData'>";

        echo "<div class='bioDataName'>Da&ntilde;o:</div>";

        echo "<div class='bioDataText'>$maneDamg</div>";

        echo "</div>";

    }



    // Acciones de la Maniobra

    if ($maneActi != "") {

        echo "<div class='bioRenglonData'>";

        echo "<div class='bioDataName'>Acciones:</div>";

        echo "<div class='bioDataText'>$maneActi</div>";

        echo "</div>";

    }



    // Raza de la Maniobra

    if ($maneSist != "") {

        echo "<div class='bioRenglonData'>";

        echo "<div class='bioDataName'>Raza:</div>";

        echo "<div class='bioDataText'>$maneSist</div>";

        echo "</div>";

    }



    // Orígenes de la Maniobra

    if ($maneOrig != "") {

        echo "<div class='bioRenglonData'>";

        echo "<div class='bioDataName'>Origen:</div>";

        echo "<div class='bioDataText'>$maneOrig</div>";

        echo "</div>";

    }



    echo "</div>";



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

