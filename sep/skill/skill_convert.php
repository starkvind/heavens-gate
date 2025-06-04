<?php

$ordenArray = array();

// Preparamos la consulta para evitar inyecciones SQL
$ordenQuery = "SELECT DISTINCT tipo FROM nuevo_habilidades ORDER BY id";
$stmt = $link->prepare($ordenQuery);
$stmt->execute();
$result = $stmt->get_result();
$ordenQueryFilas = $result->num_rows;

// Recorremos los resultados para llenar el array
while ($ordenQueryResult = $result->fetch_assoc()) {
    $ordenArray[] = htmlspecialchars($ordenQueryResult["tipo"]);
}

// Cerramos la sentencia preparada
$stmt->close();

// Asignamos el nombre de la categoría de habilidades
$skillCategoryName = isset($ordenArray[$skillCategory - 1]) ? $ordenArray[$skillCategory - 1] : '';

// Descomentar para imprimir el array y el nombre de la categoría (para debug)
// print_r($ordenArray);
// echo $skillCategoryName;

?>
