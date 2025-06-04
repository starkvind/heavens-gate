<?php

$idTypeITem = isset($_GET["t"]) ? intval($_GET["t"]) : 0;  // Asegurar que el valor sea un número entero

// Ahorramos tiempo y programación
switch ($idTypeITem) {
    case 1:
        $nameTypeItem = "Armamento";
        $orderType = "ORDER BY habilidad DESC";
        break;
    case 2:
        $nameTypeItem = "Protectores";
        $orderType = "ORDER BY bonus";
        break;
    case 3:
        $nameTypeItem = "Objetos mágicos";
        $orderType = "ORDER BY nivel";
        break;
    case 5:
        $nameTypeItem = "Amuletos";
        $orderType = "ORDER BY gnosis";
        break;
    default:
        $nameTypeItem = "Objetos";
        $orderType = "ORDER BY id";
        break;
}

// ORDEN GUAY
$pageSect = "Inventario"; // PARA CAMBIAR EL TITULO A LA PAGINA
$pageTitle2 = $nameTypeItem;
include("sep/main/main_nav_bar.php"); // Barra Navegación
echo "<h2>" . htmlspecialchars($nameTypeItem) . "</h2>";

// Cuerpo de la página
echo "<fieldset class='grupoHabilidad'>";

// Preparamos la consulta para evitar inyecciones SQL
$queryTypeItem = "SELECT id, name, nivel, habilidad FROM nuevo3_objetos WHERE tipo = ? $orderType";
$stmt = $link->prepare($queryTypeItem);
$stmt->bind_param('i', $idTypeITem);
$stmt->execute();
$result = $stmt->get_result();
$rowsQueryTypeItem = $result->num_rows;

while ($resultQueryTypeItem = $result->fetch_assoc()) {
    // ============================================================= //
    // Preparación de Variables
    $idItem = htmlspecialchars($resultQueryTypeItem["id"]);
    $nameItem = htmlspecialchars($resultQueryTypeItem["name"]);
    $skillItem = htmlspecialchars($resultQueryTypeItem["habilidad"]);
    $levelItem = (int)$resultQueryTypeItem["nivel"];
    // ============================================================= //

    // Elección de icono - Armas
    switch ($skillItem) {
        case "Cuerpo a Cuerpo":
            $iconItem = "img/inv-sword.gif";
            break;
        case "Armas de Fuego":
            $iconItem = "img/inv-pistol.gif";
            break;
        case "Tiro con Arco":
            $iconItem = "img/inv-bow.gif";
            break;
        case "Arrojar":
            $iconItem = "img/inv-throw.gif";
            break;
        case "Atletismo":
            $iconItem = "img/inv-whip.gif";
            break;
        case "Pelea":
            $iconItem = "img/inv-fist.gif";
            break;
        default:
            $iconItem = "img/inv-fetish.gif";
            break;
    }

    // Elección de icono - Otros
    switch ($idTypeITem) {
        case 2:
            $iconItem = "img/inv-armor.gif";
            break;
        case 4:
            $iconItem = "img/inv-potion.gif";
            break;
        case 5:
            $iconItem = "img/inv-talen.gif";
            break;
    }

    // ============================================================= //
    echo "<a href='index.php?p=seeitem&amp;b=$idItem'>";
    echo "<div class='renglon2col'>";
    echo "<div class='renglon2colIz'>";
    echo "<img class='valign' src='" . htmlspecialchars($iconItem) . "'/> ";
    echo $nameItem;
    echo "</div>";
    if ($levelItem > 0) { 
        echo "<div class='renglon2colDe'>";
        echo $levelItem;
        echo "</div>";                
    }
    echo "</div>";
    echo "</a>";
}

echo "</fieldset>";
echo "<p align='right'>Inventario hallado: " . htmlspecialchars($rowsQueryTypeItem) . "</p>";

$stmt->close();

?>
