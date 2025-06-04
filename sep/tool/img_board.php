<?php

// Obtener el parámetro 'order' de la URL de manera segura
$orderOrNot = isset($_GET['order']) ? $_GET['order'] : '';

$pageSect = "Galer&iacute;a de im&aacute;genes"; // PARA CAMBIAR EL TÍTULO A LA PÁGINA
include("sep/main/main_nav_bar.php"); // Barra Navegación

echo "<h2>Im&aacute;genes</h2>";
echo "
<ul>
    <li><a href='index.php?p=imgz'>Ver im&aacute;genes ordenadas</a></li>
    <li><a href='index.php?p=imgz&order=no'>Ver im&aacute;genes sin ordenar</a></li>
</ul>
";
echo "<center><table class='galerytable'><tr><td>";

if ($orderOrNot !== "no") {
    /* CONTAMOS LOS TIPOS DIFERENTES DE IMÁGENES QUE HAY */
    $consulta = "SELECT DISTINCT tipo FROM imagenes ORDER BY tipo";
    $stmt = $link->prepare($consulta);
    $stmt->execute();
    $result = $stmt->get_result();
    $tiposDiferentes = [];

    while ($row = $result->fetch_assoc()) {
        $tiposDiferentes[] = htmlspecialchars($row["tipo"]);
    }

    $totalTiposDiferentes = count($tiposDiferentes);

    if ($totalTiposDiferentes > 0) {
        $tiposDiferentes = array_unique($tiposDiferentes); // Aseguramos que no haya duplicados
        $totalTiposDiferentes = count($tiposDiferentes);

        for ($totalmanada = 0; $totalmanada < $totalTiposDiferentes; $totalmanada++) {
            $tipoActual = $tiposDiferentes[$totalmanada];
            $consulta = "SELECT ruta, miniatura, comentario FROM imagenes WHERE tipo = ? ORDER BY id";
            $stmt = $link->prepare($consulta);
            $stmt->bind_param('s', $tipoActual);
            $stmt->execute();
            $result = $stmt->get_result();

            include("img_type_convert.php"); // Incluir archivo para convertir el tipo de imagen
            echo "<table>
                    <tr>
                        <td><b>" . htmlspecialchars($nombreTipo) . "</b><br/></td>
                    </tr>
                    <tr><td>";

            while ($row = $result->fetch_assoc()) {
                echo "
                <div class='contenedorfoto'>
                    <a href='" . htmlspecialchars($row["ruta"]) . "' target='_blank'>
                        <img class='ph_galery' src='" . htmlspecialchars($row["miniatura"]) . "' alt='" . htmlspecialchars($row["comentario"]) . "' title='" . htmlspecialchars($row["comentario"]) . "'/>
                        <br/>
                        <span>" . ($row["comentario"]) . "</span>
                    </a>
                </div>";
            }

            echo "</td></tr></table>";
            $stmt->close();
        }
    }

} else {
    $consulta = "SELECT * FROM imagenes ORDER BY tipo";
    $stmt = $link->prepare($consulta);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo "
        <div class='contenedorfoto'>
            <a href='" . htmlspecialchars($row["ruta"]) . "' target='_blank'>
                <img class='ph_galery' src='" . htmlspecialchars($row["miniatura"]) . "' alt='" . htmlspecialchars($row["comentario"]) . "' title='" . htmlspecialchars($row["comentario"]) . "'/>
                <br/>
                <span>" . htmlspecialchars($row["comentario"]) . "</span>
            </a>
        </div>";
    }

    $stmt->close();
}

echo "</td></tr></table>";
echo "</center>";

?>
