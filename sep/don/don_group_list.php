<?php

    // Obtener y sanitizar el parámetro 'b'
    $punk = isset($_GET['b']) ? $_GET['b'] : ''; 

    // Preparar la consulta para obtener información sobre el tipo de don
    $consulta = "SELECT name, determinante, `desc` FROM nuevo2_tipo_dones WHERE id = ? LIMIT 1";
    $stmt = $link->prepare($consulta);
    $stmt->bind_param('s', $punk);
    $stmt->execute();
    $result = $stmt->get_result();
    $ResultQuery = $result->fetch_assoc();

    if ($ResultQuery) {
        $punk2 = htmlspecialchars($ResultQuery["name"]);
        $determinante = htmlspecialchars($ResultQuery["determinante"]);
        $descDones = htmlspecialchars($ResultQuery["desc"]);
        $donTypePhrase = "Dones";
        $pageSect = "$donTypePhrase $determinante $punk2"; // Para cambiar el título de la página
		
		include("sep/main/main_nav_bar.php"); // Barra de navegación

        echo "<h2>$donTypePhrase $determinante $punk2</h2>";
        echo "<fieldset class='descripcionGrupo'>$descDones</fieldset>";

        // Preparar la consulta para obtener los grupos de dones
        $consulta = "SELECT DISTINCT grupo FROM dones WHERE tipo = ? ORDER BY grupo";
        $stmt = $link->prepare($consulta);
        $stmt->bind_param('s', $punk);
        $stmt->execute();
        $result = $stmt->get_result();

        $domoarigato = [];
        while ($row = $result->fetch_assoc()) {
            $domoarigato[] = htmlspecialchars($row["grupo"]);
        }

        $misterroboto = count($domoarigato);

        if ($misterroboto > 0) {
            foreach ($domoarigato as $grupo) {
                // Preparar la consulta para obtener los dones dentro de cada grupo
                $consulta = "SELECT id, nombre, rango FROM dones WHERE grupo = ? AND tipo = ? ORDER BY rango";
                $stmt = $link->prepare($consulta);
                $stmt->bind_param('ss', $grupo, $punk);
                $stmt->execute();
                $result = $stmt->get_result();

                $riteClasificacion = ($punk2 !== "Menores") ? $grupo : "Sin nivel";

                echo "<fieldset class='grupoHabilidad'>";
                echo "<legend><b><a name='$riteClasificacion'></a> $riteClasificacion</b></legend>";

                while ($row = $result->fetch_assoc()) {
                    echo "
                        <a href='index.php?p=muestradon&amp;b=" . htmlspecialchars($row["id"]) . "' 
                            title='" . htmlspecialchars($row["nombre"]) . ", Rango " . htmlspecialchars($row["rango"]) . "'>
                            <div class='renglon2col'>
                                <div class='renglon2colIz'>
                                    <img class='valign' src='img/don.gif'> " . htmlspecialchars($row["nombre"]) . "
                                </div>
                                <div class='renglon2colDe'>" . htmlspecialchars($row["rango"]) . "</div>
                            </div>
                        </a>
                    ";
                }
                echo "</fieldset>";
            }
        }
        
        echo "<p align='right'>Dones hallados: $misterroboto</p>";
    } else {
        echo "<p>Error: No se encontró el tipo de don especificado.</p>";
    }
?>
