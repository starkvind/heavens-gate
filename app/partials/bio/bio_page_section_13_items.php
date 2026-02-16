<?php
    $tablaDeItem = "fact_items";
    $linkItem = "verobj";

    // Consulta directa al bridge
    $sql = "
        SELECT
            o.id,
            o.name,
            o.item_type_id, t.pretty_id AS tipo_pretty
        FROM bridge_characters_items b
        JOIN fact_items o ON o.id = b.item_id
        LEFT JOIN dim_item_types t ON t.id = o.item_type_id
        WHERE b.character_id = ?
        ORDER BY o.item_type_id, o.name
    ";

    $stmt = $link->prepare($sql);
    $stmt->bind_param('i', $characterId);
    $stmt->execute();
    $result = $stmt->get_result();

    // 👉 SI HAY OBJETOS
    if ($result->num_rows > 0) {
        echo "<div class='bioSheetPowers'>"; // Objetos y Fetiches de la Hoja ~~ #SEC13
		echo "<fieldset class='bioSeccion'><legend>$titleItems</legend>";
        while ($row = $result->fetch_assoc()) {
            $itemIdSelect      = (int)$row['id'];
            $nombreItemSelect  = htmlspecialchars($row['name']);
            $tipoItemSelect    = (int)$row['item_type_id'];

            // ================================================= //
            // Elección de icono
            switch ($tipoItemSelect) {
                case 1:
                    $iconoItemSelect = "img/ui/inv-icons/inv-sword.gif";
                    break;
                case 2:
                    $iconoItemSelect = "img/ui/inv-icons/inv-armor.gif";
                    break;
                case 3:
                    $iconoItemSelect = "img/ui/inv-icons/inv-fetish.gif";
                    break;
                case 4:
                    $iconoItemSelect = "img/ui/inv-icons/inv-potion.gif";
                    break;
                case 5:
                    $iconoItemSelect = "img/ui/inv-icons/inv-talen.gif";
                    break;
                default:
                    $iconoItemSelect = "img/ui/icons/default.jpg";
                    break;
            }
            // ================================================= //

            echo "
                <a href='" . htmlspecialchars(('/inventory/' . ($row['tipo_pretty'] ?? $tipoItemSelect) . '/' . (get_pretty_id($link, 'fact_items', (int)$itemIdSelect) ?: $itemIdSelect))) . "' target='_blank' class='hg-tooltip' data-tip='item' data-id='{$itemIdSelect}'>
                    <div class='bioSheetPower'>
                        <img class='valign' style='width:13px; height:13px;' src='{$iconoItemSelect}'>
                        {$nombreItemSelect}
                    </div>
                </a>
            ";
        }
        echo "</fieldset>";
		echo "</div>"; // Cerramos Objetos y Fetiches ~~
    // Si el personaje no tiene Objetos, no mostramos nada.
    }
    $stmt->close();
?>

