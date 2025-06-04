<?php
// Obtener y sanitizar el parámetro 'b'
$totemPageID = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta segura para obtener los datos del tótem
$queryTotem = "SELECT * FROM nuevo_totems WHERE id = ? LIMIT 1";
$stmt = $link->prepare($queryTotem);
$stmt->bind_param('s', $totemPageID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) { // Si encontramos el tótem en la base de datos
    $resultQueryTotem = $result->fetch_assoc();

    // DATOS BÁSICOS
    $totemId    = htmlspecialchars($resultQueryTotem["id"]);
    $totemName  = htmlspecialchars($resultQueryTotem["name"]);
    $totemType  = htmlspecialchars($resultQueryTotem["tipo"]);
    $totemCost  = htmlspecialchars($resultQueryTotem["coste"]);
    $totemDesc  = $resultQueryTotem["desc"]; // NO usar htmlspecialchars() para mantener el formato HTML
    $totemAttr  = $resultQueryTotem["rasgos"];
    $totemBan   = $resultQueryTotem["prohib"];
    $totemOrigin = htmlspecialchars($resultQueryTotem["origen"]);

    // Obtener el nombre del origen del tótem
    $totemOriginName = "Desconocido"; // Valor por defecto

    if (!empty($totemOrigin)) {
        $queryOrigen = "SELECT name FROM nuevo2_bibliografia WHERE id = ? LIMIT 1";
        $stmt = $link->prepare($queryOrigen);
        $stmt->bind_param('s', $totemOrigin);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($rowOrigen = $result->fetch_assoc()) {
            $totemOriginName = htmlspecialchars($rowOrigen["name"]);
        }
    }

    // Obtener el tipo de tótem
    $nombreTipo = "Desconocido"; // Valor por defecto
    $queryTipo = "SELECT name FROM nuevo2_tipo_totems WHERE id = ? LIMIT 1";
    $stmt = $link->prepare($queryTipo);
    $stmt->bind_param('s', $totemType);
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
    echo "<h2>$totemName</h2>";

    // Incluir menú social
    include("sep/main/main_social_menu.php");

    // Imagen del Tótem
    $itemImg = "img/inv/no-photo.gif"; // Valor por defecto si no hay imagen

    echo "<fieldset class='renglonPaginaDon'>";

    echo "<div class='itemSquarePhoto' style='padding-left:4px;'>";
    echo "<img class='photobio' style='width:100px;height:100px;' src='$itemImg' alt='$totemName'/>";
    echo "</div>";

    // Datos generales del Tótem
    echo "<div class='bioSquareData'>";

    // Coste del Tótem
    if ($totemCost > 0) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Coste:</div>";
        echo "<div class='bioDataText'><img class='bioAttCircle' src='img/gem-pwr-0$totemCost.png'/></div>";
        echo "</div>";
    }

    // Clasificación del Tótem
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Tipo:</div>";
    echo "<div class='bioDataText'>$nombreTipo</div>";
    echo "</div>";

    // Origen del Tótem
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Origen:</div>";
    echo "<div class='bioDataText'>$totemOriginName</div>";
    echo "</div>";

    echo "</div>";

    // Descripción del Tótem (permitiendo etiquetas HTML)
    if (!empty($totemDesc)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Descripción:</b><p>$totemDesc</p>";
        echo "</div>";
    }

    // Rasgos del Tótem
    if (!empty($totemAttr)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Rasgos:</b><p>$totemAttr</p>";
        echo "</div>";
    }

    // Prohibiciones del Tótem
    if (!empty($totemBan)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Prohibición:</b><p>$totemBan</p>";
        echo "</div>";
    }

    echo "</fieldset>";

} else {
    echo "<p>Error: Tótem no encontrado.</p>";
}
?>
