<?php
// Verificar si se recibe el parámetro 'b' y sanitizarlo
$donPageID = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta para obtener información del Don
$queryDon = "SELECT * FROM dones WHERE id = ? LIMIT 1;";
$stmt = $link->prepare($queryDon);
$stmt->bind_param('s', $donPageID);
$stmt->execute();
$result = $stmt->get_result();
$rowsQueryDon = $result->num_rows;

if ($rowsQueryDon > 0) { // Si encontramos el Don en la base de datos
    $resultQueryDon = $result->fetch_assoc();

    // DATOS BÁSICOS
    $donId     = htmlspecialchars($resultQueryDon["id"]);
    $donName   = htmlspecialchars($resultQueryDon["nombre"]);
    $donType   = htmlspecialchars($resultQueryDon["tipo"]);
    $donGroup  = htmlspecialchars($resultQueryDon["grupo"]);
    $donRank   = htmlspecialchars($resultQueryDon["rango"]);
    $donAttr   = htmlspecialchars($resultQueryDon["atributo"]);
    $donSkill  = htmlspecialchars($resultQueryDon["habilidad"]);
    $donDesc   = htmlspecialchars($resultQueryDon["descripcion"]);
    $donSystem = htmlspecialchars($resultQueryDon["sistema"]);
    $donBreed  = htmlspecialchars($resultQueryDon["ferasistema"]);
    $donOrigin = htmlspecialchars($resultQueryDon["origen"]);

    // Obtener el nombre del origen del Don
    $donOriginName = "Desconocido"; // Valor por defecto

    if (!empty($donOrigin)) {
        $queryOrigen = "SELECT name FROM nuevo2_bibliografia WHERE id = ? LIMIT 1;";
        $stmt = $link->prepare($queryOrigen);
        $stmt->bind_param('s', $donOrigin);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($rowOrigen = $result->fetch_assoc()) {
            $donOriginName = htmlspecialchars($rowOrigen["name"]);
        }
    }

    // Obtener el tipo de Don
    $nombreTipo = "Desconocido"; // Valor por defecto
    $queryTipo = "SELECT name FROM nuevo2_tipo_dones WHERE id = ? LIMIT 1;";
    $stmt = $link->prepare($queryTipo);
    $stmt->bind_param('s', $donType);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($rowTipo = $result->fetch_assoc()) {
        $nombreTipo = htmlspecialchars($rowTipo["name"]);
    }

    // Guardar en sesión para los breadcrumbs
    $_SESSION['punk2'] = $nombreTipo;

    // Incluir barra de navegación
    include("sep/main/main_nav_bar.php");

    // Título de la página
    echo "<h2>$donName</h2>";

    // Incluir menú social
    include("sep/main/main_social_menu.php");

    // Imagen del Don
    $itemImg = "img/inv/no-photo.gif"; // Valor por defecto si no hay imagen

    echo "<fieldset class='renglonPaginaDon'>";

    echo "<div class='itemSquarePhoto' style='padding-left:4px;'>";
    echo "<img class='photobio' style='width:100px;height:100px;' src='$itemImg' alt='$donName'/>";
    echo "</div>";

    // Datos generales del Don
    echo "<div class='bioSquareData'>";

    // Rango del Don
    if ($donRank > 0) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Rango:</div>";
        echo "<div class='bioDataText'><img class='bioAttCircle' src='img/gem-pwr-0$donRank.png'/></div>";
        echo "</div>";
    }

    // Grupo del Don
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Grupo:</div>";
    echo "<div class='bioDataText'>$donGroup</div>";
    echo "</div>";

    // Tirada del Don
    if (!empty($donAttr) || !empty($donSkill)) {
        $tiradaDon2 = !empty($donSkill) ? "$donAttr + $donSkill" : $donAttr;
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Tirada:</div>";
        echo "<div class='bioDataText'>$tiradaDon2</div>";
        echo "</div>";
    }

    // Orígenes del Don
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Origen:</div>";
    echo "<div class='bioDataText'>$donOriginName</div>";
    echo "</div>";

    echo "</div>";

    // Descripción del Don
    if (!empty($donDesc)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Descripción:</b><p>$donDesc</p>";
        echo "</div>";
    }

    // Sistema del Don
    if (!empty($donSystem)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Sistema:</b><p>$donSystem</p>";
        echo "</div>";
    }

    echo "</fieldset>";

} else {
    echo "<p>Error: Don no encontrado.</p>";
}
?>
