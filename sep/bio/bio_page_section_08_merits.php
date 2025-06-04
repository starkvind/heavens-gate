<?php
$idsMerits = explode("-", $bioMerFla);
$quantityMerits = count($idsMerits);
$goodMerit = 0; // Inicializamos el contador de méritos correctos

// AÑADIR MÉRITOS NUEVOS: ID-ID
for ($nmerits = 0; $nmerits < $quantityMerits; $nmerits++) {
    $meritIdSelect = $idsMerits[$nmerits];

    // Preparamos la consulta para evitar inyecciones SQL
    $stmt = $link->prepare("SELECT name, tipo FROM nuevo_mer_y_def WHERE id = ? LIMIT 1");
    $stmt->bind_param('s', $meritIdSelect);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $goodMerit++; // Incrementamos el contador si el mérito está correcto
        $resultRowQuery = $result->fetch_assoc();
        $nameMerit = htmlspecialchars($resultRowQuery['name']);
        $typeMerit = htmlspecialchars($resultRowQuery['tipo']);

        switch ($typeMerit) {
            case "Méritos":
                $meritIcon = "img/merit.gif";
                break;
            case "Defectos":
                $meritIcon = "img/flaw.gif";
                break;
            default:
                $meritIcon = "img/default.gif"; // Icono por defecto si no coincide
                break;
        }

        echo "<a href='?p=merfla&amp;b=" . htmlspecialchars($meritIdSelect) . "' target='_blank'>
                <div class='bioSheetMeritFlaw'>
                    <img class='valign' style='width:13px; height:13px;' src='" . htmlspecialchars($meritIcon) . "'>
                    $nameMerit
                </div>
              </a>";
    }

    $stmt->close();
}

//echo $goodMerit;
?>
