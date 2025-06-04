<?php
// Aseguramos que $link ya esté definido y sea una conexión válida de mysqli.

$tipoDePoderes = explode(";", $bioPowers);
$tipoDePoderExacto = $tipoDePoderes[0];

$idsPoderes = explode("-", $bioPowers);
$cantidadPoderes = count($idsPoderes);

$iconoDonSelect = "img/don.gif";

// Checkeamos los niveles de las Disciplinas, en caso de que las tengamos
if ($tipoDePoderExacto == "disciplinas") {
    for ($nivelDisc = 1; $nivelDisc < $cantidadPoderes; $nivelDisc++) {
        $contarNivDisc = explode("|", $idsPoderes[$nivelDisc]);
        $idsPoderes[$nivelDisc] = $contarNivDisc[0];
        $nivelDisciplina[$nivelDisc] = $contarNivDisc[1];
    }
}

// Definición de la tabla y campos a consultar dependiendo del tipo de poder
switch ($tipoDePoderExacto) {
    case "dones":
        $tablaDePoder = "dones";
        $nombrePoder = "nombre, rango";
        $linkPoder = "muestradon";
        break;

    case "disciplinas":
        $tablaDePoder = "nuevo2_tipo_disciplinas";
        $nombrePoder = "name";
        $linkPoder = "tipodisc";
        break;
}

for ($npoderes = 1; $npoderes < $cantidadPoderes; $npoderes++) {
    $donIdSelect = $idsPoderes[$npoderes];

    // Preparamos la consulta para evitar inyecciones SQL
    $stmt = $link->prepare("SELECT $nombrePoder FROM $tablaDePoder WHERE id = ? LIMIT 1");
    $stmt->bind_param('s', $donIdSelect);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $ResultQueryDon = $result->fetch_array();
        $nombreDonSelect = htmlspecialchars($ResultQueryDon[0]);

        // Imprimimos el poder con la información correspondiente
        echo "<a href='?p=$linkPoder&amp;b=" . htmlspecialchars($donIdSelect) . "' target='_blank'>
                <div class='bioSheetPower'>
                    <img class='valign' style='width:13px; height:13px;' src='" . htmlspecialchars($iconoDonSelect) . "'>
                    $nombreDonSelect";

        if (!empty($nivelDisciplina[$npoderes])) {
            echo "<div style='float:right'>
                    <img src='img/gem-attr-0" . htmlspecialchars($nivelDisciplina[$npoderes]) . ".png' style='padding-top: 2px;' />
                  </div>";
        }

        echo "</div></a>";
    }
    $stmt->close();
}
?>
