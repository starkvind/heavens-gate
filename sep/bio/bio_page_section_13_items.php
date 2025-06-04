<?php
$idsItems = explode("-", $bioItems);
$cantidadItems = count($idsItems);

// AÑADIR OBJETOS NUEVOS: ID-ID-ID
$tablaDeItem = "nuevo3_objetos";
$nombreItem = "name, tipo";
$linkItem = "seeitem";

// Preparar la consulta SQL
$stmt = $link->prepare("SELECT $nombreItem FROM $tablaDeItem WHERE id = ? LIMIT 1");

for ($nitems = 0; $nitems < $cantidadItems; $nitems++) {
    $itemIdSelect = $idsItems[$nitems];
    
    // Bind del parámetro para prevenir inyecciones SQL
    $stmt->bind_param('s', $itemIdSelect);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $ResultQueryItem = $result->fetch_array();
        $nombreItemSelect = htmlspecialchars($ResultQueryItem['name']);
        $tipoItemSelect = $ResultQueryItem['tipo'];

        // ================================================= //
        // Elección de icono - Otros
        switch ($tipoItemSelect) {
            case 1:
                $iconoItemSelect = "img/inv-sword.gif";
                break;
            case 2:
                $iconoItemSelect = "img/inv-armor.gif";
                break;
            case 3:
                $iconoItemSelect = "img/inv-fetish.gif";
                break;
            case 4:
                $iconoItemSelect = "img/inv-potion.gif";
                break;
            case 5:
                $iconoItemSelect = "img/inv-talen.gif";
                break;
            default:
                $iconoItemSelect = "img/default.gif"; // Icono por defecto para tipos desconocidos
                break;
        }
        // ================================================= //

        echo "<a href='?p=" . htmlspecialchars($linkItem) . "&amp;b=" . htmlspecialchars($itemIdSelect) . "' target='_blank'>
                <div class='bioSheetPower'>
                    <img class='valign' style='width:13px; height:13px;' src='" . htmlspecialchars($iconoItemSelect) . "'>
                    $nombreItemSelect
                </div>
              </a>";
    }
}

$stmt->close();
?>
