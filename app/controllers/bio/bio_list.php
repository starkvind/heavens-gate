<?php
setMetaFromPage("Biograf?as | Heaven's Gate", "Listado de biograf?as y personajes.", null, 'website');
// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Helper escape (mantengo estilo)
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Sanitiza lista tipo "1,2, 3" -> "1,2,3" (solo ints). Si queda vacío, devuelve ""
function sanitize_int_csv($csv){
    $csv = (string)$csv;
    if (trim($csv) === '') return '';
    $parts = preg_split('/\s*,\s*/', trim($csv));
    $ints = [];
    foreach ($parts as $p) {
        if ($p === '') continue;
        if (preg_match('/^\d+$/', $p)) $ints[] = (string)(int)$p;
    }
    $ints = array_values(array_unique($ints));
    return implode(',', $ints);
}

// Excluir crónicas (si existe la variable, la sanitizamos)
$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
$cronicaNotInSQL = ($excludeChronicles !== '') ? " AND p.chronicle_id NOT IN ($excludeChronicles) " : "";

// Orden Guay
include("app/partials/main_nav_bar.php"); // Barra Navegación
echo "<h2>Biografías por tipo</h2>";

// Imprimir el campo de biografías
print("<fieldset class='grupoBioClan'>");

$howMuch = 0;

// Obtener todos los tipos de personaje
$types = [];
$queryType = "SELECT id, kind FROM dim_character_types ORDER BY sort_order";
$resultType = mysqli_query($link, $queryType);
if ($resultType) {
    while ($row = mysqli_fetch_assoc($resultType)) {
        $types[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['kind'],
        ];
    }
    mysqli_free_result($resultType);
}

function renderTypesByColumn($link, $types, $typeCol, $cronicaNotInSQL){
    $howMuch = 0;
    $countQuery = "
        SELECT COUNT(DISTINCT p.id) AS count
        FROM fact_characters p
        WHERE p.$typeCol = ?
          $cronicaNotInSQL
    ";
    $stmtCount = mysqli_prepare($link, $countQuery);
    if (!$stmtCount) {
        return -1; // columna inexistente
    }

    foreach ($types as $t) {
        $idType = (int)$t['id'];
        $nombreType = (string)$t['name'];

        mysqli_stmt_bind_param($stmtCount, 'i', $idType);
        mysqli_stmt_execute($stmtCount);
        $resultCount = mysqli_stmt_get_result($stmtCount);

        $rowsCountQuery = 0;
        if ($resultCount) {
            $row = mysqli_fetch_assoc($resultCount);
            $rowsCountQuery = (int)($row['count'] ?? 0);
            mysqli_free_result($resultCount);
        }

        if ($rowsCountQuery > 0) {
            $howMuch++;
            $hrefType = pretty_url($link, 'dim_character_types', '/characters/type', $idType);
            print("
                <a href='" . h($hrefType) . "'>
                    <div class='renglon2col' style='text-align: center;'>
                        " . h($nombreType) . "
                    </div>
                </a>
            ");
        }
    }

    mysqli_stmt_close($stmtCount);
    return $howMuch;
}

if (!empty($types)) {
    $howMuch = renderTypesByColumn($link, $types, 'character_type_id', $cronicaNotInSQL);
    if ($howMuch <= 0) {
        $howMuchLegacy = renderTypesByColumn($link, $types, 'kind', $cronicaNotInSQL);
        if ($howMuchLegacy > 0) $howMuch = $howMuchLegacy;
    }
    if ($howMuch <= 0) {
        $howMuchLegacy2 = renderTypesByColumn($link, $types, 'tipo', $cronicaNotInSQL);
        if ($howMuchLegacy2 > 0) $howMuch = $howMuchLegacy2;
    }
}

print("</fieldset>");
print("<p align='right'>Categorías: " . h($howMuch) . "</p>");
?>

