<?php

// Aseguramos que el parámetro GET 'b' esté definido y es un valor seguro
$itemPageID = isset($_GET['b']) ? $_GET['b'] : '';

// Preparamos la consulta para evitar inyecciones SQL
$queryItem = "SELECT * FROM nuevo3_objetos WHERE id = ? LIMIT 1;";
$stmt = $link->prepare($queryItem);
$stmt->bind_param('s', $itemPageID);
$stmt->execute();
$result = $stmt->get_result();
$rowsQueryItem = $result->num_rows;

// ================================================================== //
if ($rowsQueryItem > 0) { // Si encontramos el Objeto en la BDD...
    $resultQueryItem = $result->fetch_assoc();

    // ================================================================== //
    // DATOS BÁSICOS
    $itemID     = htmlspecialchars($resultQueryItem["id"]);
    $itemName   = htmlspecialchars($resultQueryItem["name"]);
    $itemType   = (int)$resultQueryItem["tipo"];
    $itemSkill  = htmlspecialchars($resultQueryItem["habilidad"]);
    $itemLevel  = (int)$resultQueryItem["nivel"];
    $itemGnosis = (int)$resultQueryItem["gnosis"];
    $itemValue  = htmlspecialchars($resultQueryItem["valor"]);
    $itemBonus  = (int)$resultQueryItem["bonus"];
    $itemDamage = htmlspecialchars(strtolower($resultQueryItem["dano"]));
    $itemMetal  = (int)$resultQueryItem["metal"];
    $itemSTR    = (int)$resultQueryItem["fuerza"];
    $itemDEX    = (int)$resultQueryItem["destreza"];
    $itemImg    = htmlspecialchars($resultQueryItem["img"]);
    $itemInfo   = ($resultQueryItem["descri"]);
    $itemOrig   = (int)$resultQueryItem["origen"];
    
    // ================================================================== //
    // SELECCIONAR ORIGEN
    $itemOriginName = "Desconocido"; // Valor predeterminado si no se encuentra
    if ($itemOrig != 0) {
        $queryOrigen = "SELECT name FROM nuevo2_bibliografia WHERE id = ? LIMIT 1;";
        $stmtOrigin = $link->prepare($queryOrigen);
        $stmtOrigin->bind_param('i', $itemOrig);
        $stmtOrigin->execute();
        $resultOrigin = $stmtOrigin->get_result();
        if ($resultOrigin->num_rows > 0) {
            $resultQueryOrigen = $resultOrigin->fetch_assoc();
            $itemOriginName = htmlspecialchars($resultQueryOrigen["name"]);
        }
        $stmtOrigin->close();
    }

    // ================================================================== //
    // Preparar Tipo
    switch ($itemType) {
        case 1:
            $nameTypeItem = "Arma";
            $nameTypeBack = "Armamento";
            break;
        case 2:
            $nameTypeItem = "Protector";
            $nameTypeBack = "Protectores";
            break;
        case 3:
            $nameTypeItem = "Objeto mágico";
            $nameTypeBack = "Objetos mágicos";
            break;
        case 5:
            $nameTypeItem = "Amuleto";
            $nameTypeBack = "Amuletos";
            break;
        default:
            $nameTypeItem = "Objeto";
            $nameTypeBack = "Objetos";
            break;
    }

    // ================================================================== //
    // Preparar Daño
    switch ($itemMetal) {
        case 1:
            $metalText = " y de plata";
            break;
        case 2:
            $metalText = " y de oro";
            break;
        default:
            $metalText = "";
            break;          
    }

    switch ($itemSkill) {
        case "Cuerpo a Cuerpo":
        case "Pelea":
        case "Arrojar":
            $damageText = "Fuerza + $itemBonus";
            break;
        default:
            $damageText = "$itemBonus dados";
            break;
    }

    // ================================================================== //
    // Imágenes y Título
    $pageSect = "Objeto"; // Título de la Página ( #$itemID )
    $pageTitle2 = $itemName;
    if (empty($itemImg)) {
        $itemImg = "img/inv/no-photo.gif";
    }

    // ================================================================== //
    /* MODERNO NUEVO */
    include("sep/main/main_nav_bar.php"); // Barra Navegación
    echo "<h2>$itemName</h2>"; // Encabezado de página

    // ================================================================== //
    include("sep/main/main_social_menu.php"); // Zona de Impresión y Redes Sociales

    // ================================================================== //
    echo "<fieldset class='renglonPaginaDon'>"; // Cuerpo principal de la Ficha del Objeto

    // ================================================================== //
    echo "<div class='itemSquarePhoto' style='padding-left:4px;'>"; // Colocamos la Fotografia del Objeto
    echo "<img class='photobio' style='width:100px;height:100px;' src='$itemImg' alt='$itemName'/>";
    echo "</div>"; // Dejamos la Fotografía ya colocada

    // ================================================================== //
    echo "<div class='bioSquareData'>"; //

    // ================================================================== //
    // Tipo de Objeto
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Tipo:</div>";
    echo "<div class='bioDataText'>$nameTypeItem</div>"; 
    echo "</div>";

    // ================================================================== //
    // Habilidad del Objeto
    if (!empty($itemSkill)) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Habilidad:</div>";
        echo "<div class='bioDataText'>$itemSkill</div>"; 
        echo "</div>";
    }

    // ================================================================== //
    // Daño del Objeto
    if (!empty($itemDamage)) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Daño:</div>";
        echo "<div class='bioDataText'>$damageText, $itemDamage$metalText</div>"; 
        echo "</div>";
    }

    // ================================================================== //
    // Bonus de Defensa del Objeto
    if ($itemBonus != 0 && empty($itemSkill)) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Bonificación:</div>";
        echo "<div class='bioDataText'>+$itemBonus de absorción</div>"; 
        echo "</div>";
    }

    // ================================================================== //
    // Comprobación si es Fetiche 
    if ($itemLevel != 0) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Nivel:</div>";
        echo "<div class='bioDataText'><img class='bioAttCircle' src='img/gem-pwr-0$itemLevel.png'/></div>"; 
        echo "</div>";
        if ($itemGnosis != 0) {
            echo "<div class='bioRenglonData'>";
            echo "<div class='bioDataName'>Gnosis:</div>";
            echo "<div class='bioDataText'><img class='bioAttCircle' src='img/gem-pwr-0$itemGnosis.png'/></div>"; 
            echo "</div>";
        }
    }

    // ================================================================== //
    // Gnosis del Objeto (Solo amuletos)
    if ($itemGnosis != 0 && $itemLevel == 0 && $itemType == 5) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Gnosis:</div>";
        echo "<div class='bioDataText'><img class='bioAttCircle' src='img/gem-pwr-0$itemGnosis.png'/></div>"; 
        echo "</div>";
    }

    // ================================================================== //
    // Requiere Fuerza el Objeto?
    if ($itemSTR != 0) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Requiere:</div>";
        echo "<div class='bioDataText'>Fuerza $itemSTR mínimo</div>"; 
        echo "</div>";
    }

    // ================================================================== //
    // Penalizador de Destreza
    if ($itemDEX != 0) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Penalización:</div>";
        echo "<div class='bioDataText'>Destreza -$itemDEX</div>"; 
        echo "</div>";
    }

    // ================================================================== //
    // Orígenes del Objeto 
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Origen:</div>";
    echo "<div class='bioDataText'>$itemOriginName</div>";
    echo "</div>";

    // ================================================================== //
    echo "</div>";

    // ================================================================== //
    // Descripción del Objeto
    if (!empty($itemInfo)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Descripci&oacute;n:</b><p>$itemInfo</p>";
        echo "</div>";
    }
    echo "</fieldset>";
    /* =========== */
} // Fin comprobación

$stmt->close();
?>
