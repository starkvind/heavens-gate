<?php
$idsRites = explode("-", $bioRites);
$cantidadRites = count($idsRites);
$iconoRiteSelect = "img/rite.gif";
$tablaDeRite = "nuevo_rituales";
$nombreRite = "name, nivel";
$linkRite = "seerite";

// Preparar la consulta SQL
$stmt = $link->prepare("SELECT $nombreRite FROM $tablaDeRite WHERE id = ? LIMIT 1");

for ($nrites = 0; $nrites < $cantidadRites; $nrites++) {
    $riteIdSelect = $idsRites[$nrites];

    // Bind del parámetro para prevenir inyecciones SQL
    $stmt->bind_param('s', $riteIdSelect);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $ResultQueryRite = $result->fetch_assoc();
        $nombreRiteSelect = htmlspecialchars($ResultQueryRite['name']);

        echo "<a href='?p=" . htmlspecialchars($linkRite) . "&amp;b=" . htmlspecialchars($riteIdSelect) . "' target='_blank'>
                <div class='bioSheetPower'>
                    <img class='valign' style='width:13px; height:13px;' src='" . htmlspecialchars($iconoRiteSelect) . "'>
                    $nombreRiteSelect
                </div>
              </a>";
    }
}

$stmt->close();
?>
