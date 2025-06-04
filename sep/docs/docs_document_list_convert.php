<?php

// Asegurarse de que la conexión a la base de datos ($link) esté definida y sea válida
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Utilizar una consulta preparada para evitar inyecciones SQL
$consulta = "SELECT id, tipo FROM documentacion WHERE id LIKE ? ORDER BY id";
$stmt = mysqli_prepare($link, $consulta);

// Comprobar si la preparación de la consulta fue exitosa
if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($link));
}

// Vincular la variable $punk a la consulta preparada
mysqli_stmt_bind_param($stmt, 's', $punk);

// Ejecutar la consulta
mysqli_stmt_execute($stmt);

// Obtener el resultado de la consulta
$result = mysqli_stmt_get_result($stmt);

// Comprobar si el resultado es válido
if (!$result) {
    die("Error en la consulta: " . mysqli_error($link));
}

// Obtener el resultado de la consulta
$morir = mysqli_fetch_array($result, MYSQLI_NUM);

// Asignar valores si hay resultados
if ($morir) {
    $idx = $morir[0];
    $tipox = $morir[1];
} else {
    // Si no se encuentra ningún resultado
    $idx = null;
    $tipox = null;
}

// Lógica del switch
switch ($punk) {
    case $idx:
        $punk = $tipox;
        break;
}

// Liberar el resultado y cerrar la declaración
mysqli_free_result($result);
mysqli_stmt_close($stmt);

?>
