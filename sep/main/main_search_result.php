<?php

// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Sanitización de las entradas
$bsq = filter_input(INPUT_GET, 'bsq', FILTER_SANITIZE_STRING);
$skz = filter_input(INPUT_GET, 'skz', FILTER_SANITIZE_STRING);

// Eliminar etiquetas HTML
$bsq = strip_tags($bsq);
$skz = strip_tags($skz);

// Longitud de la cadena de búsqueda
$letrax = strlen($bsq);

include("sep/main/main_nav_bar.php"); // Barra Navegación

echo "<h2>Resultado de la b&uacute;squeda</h2>";

// Verificar si la búsqueda es válida
if (!empty($bsq) && $letrax > 3) {
    $katxos = explode(" ", $bsq);
    $nor = count($katxos);

    // Inicialización de variables
    $consulta = '';
    $punk = '';
    $rutu = '';

    // Determinar la tabla y los campos de búsqueda basados en $skz
    switch ($skz) {
        case 'biografias':
            $tabla = 'pjs1';
            $campos = 'nombre, infotext';
            $punk = 'nombre';
            $rutu = 'muestrabio';
            break;

        case 'escritos':
            $tabla = 'docz';
            $campos = 'titulo, texto';
            $punk = 'titulo';
            $rutu = 'docx';
            break;

        case 'objetos':
            $tabla = 'nuevo3_objetos';
            $campos = 'name, descri';
            $punk = 'name';
            $rutu = 'seeitem';
            break;

        case 'dones':
            $tabla = 'dones';
            $campos = 'nombre, descripcion, sistema';
            $punk = 'nombre';
            $rutu = 'muestradon';
            break;

        case 'habilidades':
            $tabla = 'nuevo_habilidades';
            $campos = 'name, descripcion';
            $punk = 'name';
            $rutu = 'skill';
            break;

        case 'sistemas':
            $tabla = 'nuevo_sistema';
            $campos = 'name, descripcion';
            $punk = 'name';
            $rutu = 'sistemas';
            break;

        case 'merydef':
            $tabla = 'nuevo_mer_y_def';
            $campos = 'name, descripcion';
            $punk = 'name';
            $rutu = 'merfla';
            break;

        default:
            echo "<center>Categoría de búsqueda no válida.</center>";
            exit;
    }

    // Construcción de la consulta basada en el número de palabras
    if ($nor == 1) {
        $consulta = "SELECT id, $punk FROM $tabla WHERE nombre LIKE ? OR infotext LIKE ? ORDER BY id LIMIT 50";
        $param = "%$bsq%";
    } elseif ($nor > 1) {
        $consulta = "SELECT id, $punk FROM $tabla WHERE MATCH(nombre, infotext) AGAINST (?) ORDER BY id ASC LIMIT 50";
        $param = $bsq;
    }

    // Preparar y ejecutar la consulta
    $stmt = mysqli_prepare($link, $consulta);
    if ($stmt) {
        if ($nor == 1) {
            mysqli_stmt_bind_param($stmt, 'ss', $param, $param);
        } else {
            mysqli_stmt_bind_param($stmt, 's', $param);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            $numregistros = mysqli_num_rows($result);

            if ($numregistros > 0) {
                echo "<p>Con <b><u>$bsq</u></b> se ha encontrado lo siguiente:</p>";
                echo "<fieldset class='grupoHabilidad'>";
                while ($row = mysqli_fetch_assoc($result)) {
                    $titulo = htmlspecialchars($row[$punk]);
                    $id = htmlspecialchars($row['id']);
                    echo "<a title='$titulo' href='index.php?p=$rutu&amp;b=$id' target='_blank'>
                        <div class='renglon2col' style='text-align:center;'>$titulo</div>
                    </a>";
                }
                echo "</fieldset>";
            } else {
                echo "<p style='text-align:center;'>No se ha encontrado nada que concuerde con '$bsq'. Intenta de nuevo.</p>";
            }

            mysqli_free_result($result);
        } else {
            // Error en la ejecución de la consulta
            echo "<p style='text-align:center;'>Error en la consulta: " . htmlspecialchars(mysqli_error($link)) . "</p>";
        }

        mysqli_stmt_close($stmt);
    } else {
        // Error al preparar la consulta
        echo "<p style='text-align:center;'>Error al preparar la consulta: " . htmlspecialchars(mysqli_error($link)) . "</p>";
    }
} elseif (empty($bsq)) {
    echo "<center>No existe ning&uacute;n criterio.</center>";
} else {
    echo "<center>La b&uacute;squeda debe realizarse con m&aacute;s de 3 letras.</center>";
}

echo "<br/><center><input class='boton1' type='button' onclick='history.go(-1)' value='Volver'/></center>";

?>
