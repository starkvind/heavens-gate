<?php
setMetaFromPage("Rituales | Heaven's Gate", "Listado de rituales por categoria.", null, 'website');
$routeParam = isset($_GET['b']) ? $_GET['b'] : '';

$consulta = "SELECT name, determinant AS determinante, description FROM dim_rite_types WHERE id = ? LIMIT 1";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $routeParam);
$stmt->execute();
$result = $stmt->get_result();
$ResultQuery = $result->fetch_assoc();

$routeLabel = $ResultQuery ? htmlspecialchars($ResultQuery["name"]) : "Desconocido";
$determinante = $ResultQuery ? htmlspecialchars($ResultQuery["determinante"]) : "";
$descRituales = $ResultQuery ? ($ResultQuery["description"] ?? '') : "<p>Descripción no disponible</p>";
$donTypePhrase = "Ritos";
$pageSect = "$donTypePhrase $determinante $routeLabel";

$_SESSION['punk2'] = $routeLabel;

if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-powers.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-powers.css">';
}

include("app/partials/main_nav_bar.php");

echo "<h2>$donTypePhrase $determinante $routeLabel</h2>";
echo "<fieldset class='hg-powers-description'><p>$descRituales</p></fieldset>";

$consulta = "SELECT DISTINCT level FROM fact_rites WHERE kind = ? ORDER BY level";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $routeParam);
$stmt->execute();
$result = $stmt->get_result();

$domoarigato = [];
while ($row = $result->fetch_assoc()) {
    $domoarigato[] = htmlspecialchars($row["level"]);
}

$misterroboto = count($domoarigato);

if ($misterroboto > 0) {
    foreach ($domoarigato as $level) {
        $consulta = "SELECT id, pretty_id, name FROM fact_rites WHERE level = ? AND kind = ? ORDER BY name";
        $stmt = $link->prepare($consulta);
        $stmt->bind_param('ss', $level, $routeParam);
        $stmt->execute();
        $result = $stmt->get_result();

        $riteClasificacion = ($routeLabel !== "Menores") ? "Nivel $level" : "Sin level";

        echo "<fieldset class='hg-powers-group-list'>";
        echo "<legend><b><a name='$riteClasificacion'></a> $riteClasificacion</b></legend>";

        while ($row = $result->fetch_assoc()) {
            echo "
                <a href='" . htmlspecialchars(pretty_url($link, 'fact_rites', '/powers/rite', (int)$row["id"])) . "'
                    title='" . htmlspecialchars($row["name"]) . "'>
                    <div class='hg-powers-list-card'>
                        <div class='hg-powers-list-card__main'>
                            <img class='hg-powers-list-icon' src='img/ui/icons/icon_book.webp'> " . htmlspecialchars($row["name"]) . "
                        </div>
                    </div>
                </a>
            ";
        }

        echo "</fieldset>";
    }
}

echo "<p align='right'>Rituales hallados: $misterroboto</p>";
?>
