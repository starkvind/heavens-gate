<?php

// Aseguramos que $mafCategory esté definido y sea un número
$mafCategory = isset($mafCategory) ? (int)$mafCategory : 0;

// Ajuste del nombre de la categoría basado en $mafCategory
switch ($mafCategory) {
    case 1:
        $mafCategoryName = "Méritos";
        break;
    case 2:
        $mafCategoryName = "Defectos";
        break;
    default:
        $mafCategoryName = "";
        break;
}

// Inicializamos el array de orden
$ordenArray = array();

// Preparamos la consulta para evitar inyecciones SQL
$ordenQuery = "SELECT DISTINCT afiliacion FROM nuevo_mer_y_def ORDER BY afiliacion ASC";
$stmtOrden = $link->prepare($ordenQuery);
$stmtOrden->execute();
$resultOrden = $stmtOrden->get_result();
$ordenQueryFilas = $resultOrden->num_rows;

// Recorremos los resultados de la consulta para llenar el array
while ($ordenQueryResult = $resultOrden->fetch_assoc()) {
    $ordenArray[] = htmlspecialchars($ordenQueryResult["afiliacion"]);
}

// Cerramos la sentencia preparada
$stmtOrden->close();

// Asignamos el nombre del tipo de méritos o defectos, asegurándonos de que el índice exista
$mafType = isset($mafType) ? (int)$mafType : 0;
$mafTypeName = isset($ordenArray[$mafType - 1]) ? $ordenArray[$mafType - 1] : ''; 

// Descomentar para imprimir el array y el nombre del tipo (para debug)
// print_r($ordenArray);
// echo $mafTypeName;

?>
