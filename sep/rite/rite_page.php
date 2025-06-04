<?php
// Obtener y sanitizar el parámetro 'b'
$ritePageID = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta segura para obtener los datos del ritual
$queryRite = "SELECT * FROM nuevo_rituales WHERE id = ? LIMIT 1";
$stmt = $link->prepare($queryRite);
$stmt->bind_param('s', $ritePageID);
$stmt->execute();
$result = $stmt->get_result();
$rowsQueryRite = $result->num_rows;

if ($rowsQueryRite > 0) { // Si encontramos el ritual en la base de datos
    $resultQueryRite = $result->fetch_assoc();

    // DATOS BÁSICOS
    $riteId     = htmlspecialchars($resultQueryRite["id"]);
    $riteName   = htmlspecialchars($resultQueryRite["name"]);
    $riteType   = htmlspecialchars($resultQueryRite["tipo"]);
    $riteLevel  = htmlspecialchars($resultQueryRite["nivel"]);
    $riteBreed  = htmlspecialchars($resultQueryRite["raza"]);
    $riteDesc   = $resultQueryRite["desc"]; // NO usar htmlspecialchars() para conservar el HTML
    $riteSystem = $resultQueryRite["syst"];
    $riteSistema = $resultQueryRite["sistema"];
    $riteOrigin = htmlspecialchars($resultQueryRite["origen"]);

    // Obtener el nombre del origen del ritual
    $riteOriginName = "Desconocido"; // Valor por defecto

    if (!empty($riteOrigin)) {
        $queryOrigen = "SELECT name FROM nuevo2_bibliografia WHERE id = ? LIMIT 1";
        $stmt = $link->prepare($queryOrigen);
        $stmt->bind_param('s', $riteOrigin);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($rowOrigen = $result->fetch_assoc()) {
            $riteOriginName = htmlspecialchars($rowOrigen["name"]);
        }
    }

    // Obtener el tipo de ritual
    $nombreTipo = "Desconocido"; // Valor por defecto
    $queryTipo = "SELECT name FROM nuevo2_tipo_rituales WHERE id = ? LIMIT 1";
    $stmt = $link->prepare($queryTipo);
    $stmt->bind_param('s', $riteType);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($rowTipo = $result->fetch_assoc()) {
        $nombreTipo = htmlspecialchars($rowTipo["name"]);
    }

    // Guardar en sesión para breadcrumbs
    $_SESSION['punk2'] = $nombreTipo;

    // Incluir barra de navegación
    include("sep/main/main_nav_bar.php");

    // Título de la página
    echo "<h2>$riteName</h2>";

    // Incluir menú social
    include("sep/main/main_social_menu.php");

    // Imagen del Rito
    $itemImg = "img/inv/no-photo.gif"; // Valor por defecto si no hay imagen

    echo "<fieldset class='renglonPaginaDon'>";

    echo "<div class='itemSquarePhoto' style='padding-left:4px;'>";
    echo "<img class='photobio' style='width:100px;height:100px;' src='$itemImg' alt='$riteName'/>";
    echo "</div>";

    // Datos generales del Rito
    echo "<div class='bioSquareData'>";

    // Nivel del Ritual
    if ($riteLevel > 0) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Nivel:</div>";
        echo "<div class='bioDataText'><img class='bioAttCircle' src='img/gem-pwr-0$riteLevel.png'/></div>";
        echo "</div>";
    }

    // Clasificación del Ritual
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Tipo:</div>";
    echo "<div class='bioDataText'>$nombreTipo</div>";
    echo "</div>";

    // Origen del Ritual
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Origen:</div>";
    echo "<div class='bioDataText'>$riteOriginName</div>";
    echo "</div>";

    echo "</div>";

    // Descripción del Ritual (permitiendo etiquetas HTML)
    if (!empty($riteDesc)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Descripción:</b><p>$riteDesc</p>";
        echo "</div>";
    }

    // Sistema del Ritual
    if (!empty($riteSystem)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Sistema:</b><p>$riteSystem</p>";
        echo "</div>";
    }

    echo "</fieldset>";

} else {
    echo "<p>Error: Ritual no encontrado.</p>";
}
?>
