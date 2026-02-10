<?php
// Obtener id o pretty-id
$pjRaw = $_GET['b'] ?? '';
$pjId = resolve_pretty_id($link, 'dim_players', (string)$pjRaw) ?? 0;

// Verificar si la conexión a la base de datos ($link) está definida y es válida
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}
if ($pjId <= 0) {
    die("Jugador inválido.");
}

// Usar una consulta preparada para evitar inyecciones SQL
$consulta = "SELECT * FROM dim_players WHERE id = ? LIMIT 1;";
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
        setMetaFromPage($namePJ . " " . $surnamePJ . " | Jugadores | Heaven's Gate", meta_excerpt($descPJ), $picPJ, 'article');
    }
} else {
    die("Error en la consulta: " . mysqli_error($link));
}

mysqli_stmt_close($stmt);

include("app/partials/main_nav_bar.php"); // Barra Navegación
echo "<h2>$namePJ $surnamePJ</h2>";
?>
<table width="100%">
<?php 
// Excluir cr?nicas si aplica
if (!function_exists('sanitize_int_csv')) {
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
}
$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
$cronicaNotInSQL = ($excludeChronicles !== '') ? " AND cronica NOT IN ($excludeChronicles) " : "";

// Mostrar imagen de jugador
if (empty($picPJ)) {
    $picPJ = "img/player/sinfoto.jpg";
}
echo "<tr><td class='bext2'><img src='$picPJ'></td>";

// Obtener personajes asociados al jugador
$sqlPlayerCharacters = "SELECT id, nombre FROM fact_characters WHERE jugador = ? $cronicaNotInSQL";
$stmt2 = mysqli_prepare($link, $sqlPlayerCharacters);

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
        $pjIdResult = (int)$resultSelectPJ2['id'];
        $pjNombreResult = htmlspecialchars($resultSelectPJ2['nombre']);
        $pjHref = pretty_url($link, 'fact_characters', '/characters', $pjIdResult);

        echo "
        <a href='" . htmlspecialchars($pjHref) . "' title='$pjNombreResult' target='_blank'>
            <div class='renglon3col'>
                $pjNombreResult
            </div>
        </a>";
        $contarPJS++;
    }

    $countPJstyle = "position:relative;width:100%;float:right;text-align:right;top:10px;left:0;padding:4px;";
$circulosPJcont = "<img class='bioAttCircle' src='img/ui/gems/pwr/gem-pwr-0$contarPJS.png' title='Factor Dani: $contarPJS' />";

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
