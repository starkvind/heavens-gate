<?php

// Aseguramos que $returnType esté definido de manera segura
$returnType = isset($returnType) ? $returnType : '';

function hg_ascii_name($s){
    $s = (string)$s;
    if ($s === '') return $s;
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false && $t !== '') return $t;
    }
    return $s;
}

// Preparamos la consulta para evitar inyecciones SQL
$ordenQuery = "SELECT id FROM dim_systems WHERE name = ?";
$stmt = $link->prepare($ordenQuery);
$stmt->bind_param('s', $returnType);
$stmt->execute();
$result = $stmt->get_result();

$returnTypeId = ''; // Inicializamos la variable

if ($result->num_rows > 0) {
    while ($ordenQueryResult = $result->fetch_assoc()) {
        $returnTypeId = htmlspecialchars($ordenQueryResult["id"]);
    }
} else {
    $alt = hg_ascii_name($returnType);
    if ($alt !== '' && $alt !== $returnType) {
        $stmt2 = $link->prepare($ordenQuery);
        $stmt2->bind_param('s', $alt);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        if ($result2->num_rows > 0) {
            while ($row = $result2->fetch_assoc()) {
                $returnTypeId = htmlspecialchars($row["id"]);
            }
        }
        $stmt2->close();
    }
}

// Cerramos la sentencia preparada
$stmt->close();

?>
