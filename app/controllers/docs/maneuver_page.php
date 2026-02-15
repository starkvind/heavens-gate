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
    $maneOrig = htmlspecialchars($resultQueryManeuver["bibliography_id"]);
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
    // Imagen de la Maniobra
    $maneImg = trim((string)($resultQueryManeuver["img"] ?? ""));
    $itemImg = "img/inv/no-photo.gif"; // Valor por defecto si no hay imagen
    if ($maneImg !== "") {
        if (strpos($maneImg, "/") !== false) {
            $itemImg = $maneImg;
        } else {
            $itemImg = "img/maneuvers/" . $maneImg;
        }
    }
    // Cuerpo principal de la Ficha (estilo carta)
    echo "<div class='power-card power-card--maneuver'>";
    echo "  <div class='power-card__banner'>";
    echo "    <span class='power-card__title'>$maneName</span>";
    echo "  </div>";

    echo "  <div class='power-card__body'>";
    echo "    <div class='power-card__media'>";
    echo "      <img class='power-card__img' src='$itemImg' alt='$maneName'/>";
    echo "    </div>";

    echo "    <div class='power-card__stats'>";
    if ($maneActi != "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Acciones</div><div class='power-stat__value'>$maneActi</div></div>";
    }
    if ($maneUser != "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Formas</div><div class='power-stat__value'>$maneUser</div></div>";
    }
    if ($maneRoll != "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Tirada</div><div class='power-stat__value'>$maneRoll ($maneDiff)</div></div>";
    }
    if ($maneDamg != "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Da&ntilde;o</div><div class='power-stat__value'>$maneDamg</div></div>";
    }
    if ($maneSist != "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Raza</div><div class='power-stat__value'>$maneSist</div></div>";
    }
    if ($maneOrig != "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>$maneOrig</div></div>";
    }
    echo "    </div>"; // stats
    echo "  </div>"; // body

    if ($maneText != "") {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Descripci&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>$maneText</div>";
        echo "  </div>";
    }
    echo "</div>"; // power-card
} // Fin comprobación
// Cerramos la sentencia preparada para la consulta principal
$stmtManeuver->close();
?>
