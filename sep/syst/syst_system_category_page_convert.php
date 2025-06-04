<?php

// Aseguramos que $returnType esté definido de manera segura
$returnType = isset($returnType) ? $returnType : '';

// Preparamos la consulta para evitar inyecciones SQL
$ordenQuery = "SELECT id FROM nuevo_sistema WHERE name = ?";
$stmt = $link->prepare($ordenQuery);
$stmt->bind_param('s', $returnType);
$stmt->execute();
$result = $stmt->get_result();

$returnTypeId = ''; // Inicializamos la variable

if ($result->num_rows > 0) {
    while ($ordenQueryResult = $result->fetch_assoc()) {
        $returnTypeId = htmlspecialchars($ordenQueryResult["id"]);
    }
}

// Cerramos la sentencia preparada
$stmt->close();

?>
