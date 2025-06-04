<?php
// Sanitización de entrada de usuario
$pjId = filter_input(INPUT_GET, 'b', FILTER_SANITIZE_STRING);

// Verificar si la conexión a la base de datos ($link) está definida y es válida
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Usar una consulta preparada para evitar inyecciones SQL
$consulta = "SELECT * FROM nuevo_jugadores WHERE id = ?";
$stmt = mysqli_prepare($link, $consulta);

if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($link));
}

mysqli_stmt_bind_param($stmt, 's', $pjId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result) {
    while ($ResultQuery = mysqli_fetch_assoc($result)) {
        $namePJ = htmlspecialchars($ResultQuery["name"]);
        $surnamePJ = htmlspecialchars($ResultQuery["surname"]);
        $picPJ = htmlspecialchars($ResultQuery["picture"]);
        $descPJ = ($ResultQuery["desc"]);
        $pageSect = "Jugador"; // PARA CAMBIAR EL TITULO A LA PAGINA
        $pageTitle2 = $namePJ . " " . $surnamePJ;
    }
} else {
    die("Error en la consulta: " . mysqli_error($link));
}

mysqli_stmt_close($stmt);

include("sep/main/main_nav_bar.php"); // Barra Navegación
echo "<h2>$namePJ $surnamePJ</h2>";
include("sep/main/main_social_menu.php"); // Zona de Impresión y Redes Sociales
?>
<table width="100%">
<?php 
// Mostrar imagen de jugador
if (empty($picPJ)) {
    $picPJ = "img/player/sinfoto.jpg";
}
echo "<tr><td class='bext2'><img src='$picPJ'></td>";

// Obtener personajes asociados al jugador
$selectPJ = "SELECT id, nombre FROM pjs1 WHERE jugador = ?";
$stmt2 = mysqli_prepare($link, $selectPJ);

if (!$stmt2) {
    die("Error al preparar la consulta de personajes: " . mysqli_error($link));
}

mysqli_stmt_bind_param($stmt2, 's', $pjId);
mysqli_stmt_execute($stmt2);
$result2 = mysqli_stmt_get_result($stmt2);

if ($result2) {
    $nRowsSelPJ = mysqli_num_rows($result2);

    if ($nRowsSelPJ > 0) {
        echo "<td class='texti' valign='top' style='text-align:left;padding-left:28px;'>
        <p>$descPJ</p><p><b>Personajes de $namePJ:</b></p>";
    }

    $contarPJS = 0; // Inicializar el contador de personajes

    while ($resultSelectPJ2 = mysqli_fetch_assoc($result2)) {
        $pjIdResult = htmlspecialchars($resultSelectPJ2['id']);
        $pjNombreResult = htmlspecialchars($resultSelectPJ2['nombre']);

        echo "
        <a href='index.php?p=muestrabio&amp;b=$pjIdResult' title='$pjNombreResult' target='_blank'>
            <div class='renglon3col'>
                $pjNombreResult
            </div>
        </a>";
        $contarPJS++;
    }

    $countPJstyle = "position:relative;width:100%;float:right;text-align:right;top:10px;left:0;padding:4px;";
    $circulosPJcont = "<img class='bioAttCircle' src='img/gem-pwr-0$contarPJS.png' title='Factor Dani: $contarPJS' />";

    if ($nRowsSelPJ > 0) {
        echo "<div style='$countPJstyle'><div class='bioDataText' style='width:30%;'>$circulosPJcont</div></div>";
        echo "</td></tr>";
    }
} else {
    die("Error en la consulta de personajes: " . mysqli_error($link));
}

mysqli_free_result($result2);
mysqli_stmt_close($stmt2);
?>
</table>
