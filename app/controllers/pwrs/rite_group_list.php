<?php
require_once __DIR__ . '/../../domains/powers/queries.php';

setMetaFromPage("Rituales | Heaven's Gate", "Listado de rituales por categoria.", null, 'website');
$routeParam = (int)hg_request_param($hgRequest, 'rite_type');
$ResultQuery = hg_powers_fetch_type($link, 'rites', $routeParam);

$routeLabel = $ResultQuery ? htmlspecialchars($ResultQuery["name"]) : "Desconocido";
$determinante = $ResultQuery ? htmlspecialchars($ResultQuery["determinant"]) : "";
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

$rows = hg_powers_fetch_rites_for_type($link, $routeParam);
if ($rows === false) $rows = [];
$levels = [];
foreach ($rows as $row) {
    $level = (string)($row['level'] ?? '');
    $levels[$level][] = $row;
}

$misterroboto = count($levels);
foreach ($levels as $levelRaw => $levelRows) {
    $level = htmlspecialchars($levelRaw);
    $riteClasificacion = ($routeLabel !== "Menores") ? "Nivel $level" : "Sin level";

    echo "<fieldset class='hg-powers-group-list'>";
    echo "<legend><b><a name='$riteClasificacion'></a> $riteClasificacion</b></legend>";

    foreach ($levelRows as $row) {
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

echo "<p align='right'>Rituales hallados: $misterroboto</p>";
?>
