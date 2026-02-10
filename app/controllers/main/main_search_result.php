<?php setMetaFromPage("Resultados de busqueda | Heaven's Gate", "Resultados de la busqueda en la campana.", null, 'website'); ?>
<?php

// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Sanitización de las entradas (compatibilidad con parámetros antiguos)
$bsq = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_STRING);
$skz = filter_input(INPUT_GET, 'section', FILTER_SANITIZE_STRING);
if ($bsq === null || $bsq === '') {
    $bsq = filter_input(INPUT_GET, 'bsq', FILTER_SANITIZE_STRING);
}
if ($skz === null || $skz === '') {
    $skz = filter_input(INPUT_GET, 'skz', FILTER_SANITIZE_STRING);
}

// Eliminar etiquetas HTML
$bsq = strip_tags($bsq);
$skz = strip_tags($skz);

// Longitud de la cadena de búsqueda
$letrax = strlen($bsq);

include("app/partials/main_nav_bar.php"); // Barra Navegación

echo "<h2>Resultado de la b&uacute;squeda</h2>";

// Verificar si la búsqueda es válida
if (!empty($bsq) && $letrax > 3) {
    $katxos = explode(" ", $bsq);
    $nor = count($katxos);

    // Inicialización de variables
    $consulta = '';
    $searchField = '';
    $rutu = '';

function build_pretty_search_url(string $rutu, string $id): string {
        global $link;
        $idInt = (int)$id;
        switch ($rutu) {
            case 'muestrabio':
                return pretty_url($link, 'fact_characters', '/characters', $idInt);
            case 'verdoc':
                return pretty_url($link, 'fact_docs', '/documents', $idInt);
            case 'seeitem':
                return pretty_url($link, 'fact_items', '/inventory/items', $idInt);
            case 'muestradon':
                return pretty_url($link, 'fact_gifts', '/powers/gift', $idInt);
            case 'verrasgo':
                return pretty_url($link, 'dim_traits', '/rules/traits', $idInt);
            case 'sistemas':
                return pretty_url($link, 'dim_systems', '/systems', $idInt);
            case 'vermyd':
                return pretty_url($link, 'dim_merits_flaws', '/rules/merits-flaws', $idInt);
            default:
                return "?p=$rutu&b=$id";
        }
}

    // Determinar la tabla y los campos de búsqueda basados en $skz
    switch ($skz) {
        case 'biografias':
            $tabla = 'fact_characters';
            $campos = 'nombre, infotext';
            $searchField = 'nombre';
            $rutu = 'muestrabio';
            break;

        case 'escritos':
            $tabla = 'fact_docs';
            $campos = 'titulo, texto';
            $searchField = 'titulo';
            $rutu = 'verdoc';
            break;

        case 'objetos':
            $tabla = 'fact_items';
            $campos = 'name, descri';
            $searchField = 'name';
            $rutu = 'seeitem';
            break;

        case 'dones':
            $tabla = 'fact_gifts';
            $campos = 'nombre, descripcion, sistema';
            $searchField = 'nombre';
            $rutu = 'muestradon';
            break;

        case 'habilidades':
            $tabla = 'dim_traits';
            $campos = 'name, descripcion';
            $searchField = 'name';
            $rutu = 'verrasgo';
            break;

        case 'sistemas':
            $tabla = 'dim_systems';
            $campos = 'name, descripcion';
            $searchField = 'name';
            $rutu = 'sistemas';
            break;

        case 'merydef':
            $tabla = 'dim_merits_flaws';
            $campos = 'name, descripcion';
            $searchField = 'name';
            $rutu = 'vermyd';
            break;

        default:
            echo "<center>Categoría de búsqueda no válida.</center>";
            exit;
    }

    // Construcci?n de la consulta basada en los campos definidos en $campos
    $fields = array_map('trim', explode(',', $campos));
    $whereParts = [];
    $params = [];
    $types = '';

    foreach ($katxos as $term) {
        $term = trim($term);
        if ($term === '') continue;
        $like = '%' . $term . '%';
        $sub = [];
        foreach ($fields as $f) {
            $sub[] = "$f LIKE ?";
            $params[] = $like;
            $types .= 's';
        }
        if (!empty($sub)) $whereParts[] = '(' . implode(' OR ', $sub) . ')';
    }

    if (empty($whereParts)) {
        echo "<center>La b?squeda debe realizarse con m?s de 3 letras.</center>";
        exit;
    }

    $whereSql = implode(' AND ', $whereParts);
    $consulta = "SELECT id, $searchField FROM $tabla WHERE $whereSql ORDER BY id LIMIT 50";

    // Preparar y ejecutar la consulta
    $stmt = mysqli_prepare($link, $consulta);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            $numregistros = mysqli_num_rows($result);

            if ($numregistros > 0) {
                echo "<p>Con <b><u>$bsq</u></b> se ha encontrado lo siguiente:</p>";
                echo "<fieldset class='grupoHabilidad'>";
                while ($row = mysqli_fetch_assoc($result)) {
                    $titulo = htmlspecialchars($row[$searchField]);
                    $id = htmlspecialchars($row['id']);
                    $href = build_pretty_search_url($rutu, $id);
                    echo "<a title='$titulo' href='$href' target='_blank'>
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
