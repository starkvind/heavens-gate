<?php include("sep/main/main_nav_bar.php"); // Barra Navegación ?>
<h2> Noticias </h2>

<table class="notix">
<?php

global $link; // Asegúrate de que $link sea accesible en el ámbito global

// Limitar la búsqueda
$tamano_pagina = 5;

// Examinar la página a mostrar y el inicio del registro a mostrar
$pagina = filter_input(INPUT_GET, 'pag', FILTER_VALIDATE_INT);
if (!$pagina) {
    $inicio = 0;
    $pagina = 1;
} else {
    $inicio = ($pagina - 1) * $tamano_pagina;
}

// Consulta para obtener el número total de registros
$consulta = "SELECT COUNT(*) as total FROM msg";
$result = mysqli_query($link, $consulta);
$row = mysqli_fetch_assoc($result);
$num_total_registros = $row['total'];
$total_paginas = ceil($num_total_registros / $tamano_pagina);

// Consulta para obtener los registros de la página actual
$consulta = "SELECT autor, titulo, mensaje, fecha FROM msg ORDER BY id DESC LIMIT ?, ?";
$stmt = mysqli_prepare($link, $consulta);
mysqli_stmt_bind_param($stmt, "ii", $inicio, $tamano_pagina);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($ResultQuery = mysqli_fetch_assoc($result)) {
    echo "<tr><td><fieldset class='notf'><legend class='notf'>" . htmlspecialchars($ResultQuery["titulo"]) . "</legend><p>" . (($ResultQuery["mensaje"])) . "</p>\n</fieldset></td></tr>";
    echo "<tr><td align='right'>por <b>" . htmlspecialchars($ResultQuery["autor"]) . "</b> el " . htmlspecialchars($ResultQuery["fecha"]) . "</td></tr>";
}

mysqli_stmt_close($stmt);

?>
</table>

<br/><br/>

<center>
<table width="50%">
<tr>
<?php
// Navegación de paginación
if ($total_paginas >= 2) {
    if ($pagina > 1) {
        $back = $pagina - 1;
        echo "<td align='center'><a href='index.php?p=news&amp;pag=$back'>&#60;&#60; Atr&aacute;s</a></td>";
    }

    if ($pagina < $total_paginas) {
        $adelante = $pagina + 1;
        echo "<td align='center'><a href='index.php?p=news&amp;pag=$adelante'>Siguiente &#62;&#62;</a></td>";
    }
}
?>
</tr>
</table>
</center>

<p align='right'>
<?php
// Mostrar los distintos índices de las páginas si hay varias páginas
if ($total_paginas > 1) {
    echo "P&aacute;gina: ";
    for ($ix = 1; $ix <= $total_paginas; $ix++) {
        if ($pagina == $ix) {
            // Si muestro el índice de la página actual, no coloco enlace
            echo $pagina . " ";
        } else {
            // Si el índice no corresponde con la página mostrada actualmente, coloco el enlace para ir a esa página
            echo "<a href='index.php?p=news&amp;pag=$ix'>" . $ix . "</a> ";
        }
    }
}
?>
</p>
