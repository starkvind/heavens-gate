<?php
// Sanitizar y validar la entrada
$gest = filter_input(INPUT_GET, 'b', FILTER_SANITIZE_STRING);

// Asegurarse de que la conexión a la base de datos ($link) esté definida y sea válida
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Utilizar una consulta preparada para evitar inyecciones SQL
$Query = "SELECT titulo, doc, seccion, texto FROM docz WHERE id LIKE ?";
$stmt = mysqli_prepare($link, $Query);

if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($link));
}

// Vincular la variable $gest a la consulta preparada
mysqli_stmt_bind_param($stmt, 's', $gest);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    die("Error en la consulta: " . mysqli_error($link));
}

// Procesar los resultados de la consulta
while ($ResultQuery = mysqli_fetch_assoc($result)) {
    $titleDoc = ($ResultQuery["titulo"]);
    $descargaDoc = htmlspecialchars($ResultQuery["doc"]);
    $borrasca = htmlspecialchars($ResultQuery['seccion']);
    $texto = ($ResultQuery["texto"]);

    $pageSect = "Documento"; // PARA CAMBIAR EL TITULO A LA PAGINA
    $pageTitle2 = $titleDoc;

    // Datos de la Historia
    include("sep/main/main_nav_bar.php"); // Barra Navegación
    echo "<h2>$titleDoc</h2>";
    include("sep/main/main_social_menu.php"); // Zona de Impresión y Redes Sociales
    echo "<table class='notix'>";
    echo "<tr><td class='texti' colspan='2'><p>" . nl2br($texto) . "</p></td></tr>";
    echo "</table>";
}

// Liberar el resultado de la memoria
mysqli_free_result($result);
mysqli_stmt_close($stmt);
?>
