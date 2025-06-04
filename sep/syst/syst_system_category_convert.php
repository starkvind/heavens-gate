<?php

// Aseguramos que $systemCategory esté definido y saneado
$systemCategory = isset($systemCategory) ? $systemCategory : '';

// Preparamos la consulta para evitar inyecciones SQL
$ordenQuery = "SELECT name FROM nuevo_sistema WHERE id = ?";
$stmt = $link->prepare($ordenQuery);
$stmt->bind_param('s', $systemCategory);
$stmt->execute();
$result = $stmt->get_result();

$systemCategoryName = ''; // Inicializamos la variable

if ($result->num_rows > 0) {
    while ($ordenQueryResult = $result->fetch_assoc()) {
        $systemCategoryName = htmlspecialchars($ordenQueryResult["name"]);
    }
}

// Cerramos la sentencia preparada
$stmt->close();

?>
