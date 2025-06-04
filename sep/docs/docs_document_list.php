<?php
// Sanitización y validación de entradas
$punk = filter_input(INPUT_GET, 't', FILTER_SANITIZE_NUMBER_INT);
$stella = filter_input(INPUT_GET, 't', FILTER_SANITIZE_STRING);

// Asegurar que $punk solo contenga números
$punk = preg_replace("/[^0-9]/", "", $punk);

include("docs_document_list_convert.php");

// Establecer título de la página
$pageSect = "Documentos"; 
$pageTitle2 = $punk;

include("sep/main/main_nav_bar.php"); // Barra Navegación

echo "<h2>" . htmlspecialchars($punk) . "</h2>";
echo "<fieldset class='grupoHabilidad'>";

// Asegúrate de que $link esté definido y conectado a la base de datos antes de esta sección
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Realizar consulta segura a la base de datos
$consulta = "SELECT id, titulo FROM docz WHERE seccion LIKE ? ORDER BY id";
$stmt = mysqli_prepare($link, $consulta);
mysqli_stmt_bind_param($stmt, 's', $stella);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    die("Error en la consulta: " . mysqli_error($link));
}

while ($ResultQuery = mysqli_fetch_assoc($result)) {
    $idDoc = htmlspecialchars($ResultQuery["id"]);
    $tituloDoc = ($ResultQuery["titulo"]);
    echo "
    <a href='index.php?p=docx&amp;b=$idDoc'>
        <div class='renglon2col'>
            <div class='renglon2colIz'>
                <img src='img/per.gif'/> 
                $tituloDoc
            </div>
        </div>
    </a>";
}

echo "</fieldset>";

// Mostrar el número total de documentos hallados
$numregistros = mysqli_num_rows($result);
echo "<p align='right'>Documentos hallados: $numregistros</p>";

// Liberar el resultado de la memoria
mysqli_free_result($result);
?>
