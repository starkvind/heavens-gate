<?php
require_once __DIR__ . '/../../domains/powers/queries.php';

setMetaFromPage("Dones | Heaven's Gate", "Listado de dones por categoria.", null, 'website');
$routeParam = (int)hg_request_param($hgRequest, 'gift_type');
$ResultQuery = hg_powers_fetch_type($link, 'gifts', $routeParam);

if ($ResultQuery) {
    $routeLabel = htmlspecialchars($ResultQuery["name"]);
    $determinante = htmlspecialchars($ResultQuery["determinant"]);
    $descDones = htmlspecialchars($ResultQuery["description"] ?? '');
    $donTypePhrase = "Dones";
    $pageSect = "$donTypePhrase $determinante $routeLabel";

    if (function_exists('hg_page_register_stylesheet')) {
        hg_page_register_stylesheet('/assets/css/hg-powers.css');
    } else {
        echo '<link rel="stylesheet" href="/assets/css/hg-powers.css">';
    }

    include("app/partials/main_nav_bar.php");

    echo "<h2>$donTypePhrase $determinante $routeLabel</h2>";
    echo "<fieldset class='hg-powers-description'>$descDones</fieldset>";

    $rows = hg_powers_fetch_gifts_for_type($link, $routeParam);
    if ($rows === false) $rows = [];
    $groups = [];
    foreach ($rows as $row) {
        $group = (string)($row['gift_group'] ?? '');
        $groups[$group][] = $row;
    }

    $misterroboto = count($groups);
    foreach ($groups as $grupoRaw => $groupRows) {
        $grupo = htmlspecialchars($grupoRaw);
        $riteClasificacion = ($routeLabel !== "Menores") ? $grupo : "Sin nivel";

        echo "<fieldset class='hg-powers-group-list'>";
        echo "<legend><b><a name='$riteClasificacion'></a> $riteClasificacion</b></legend>";

        foreach ($groupRows as $row) {
            echo "
                <a href='" . htmlspecialchars(pretty_url($link, 'fact_gifts', '/powers/gift', (int)$row["id"])) . "'
                    title='" . htmlspecialchars($row["name"]) . ", Rango " . htmlspecialchars($row["rank"]) . "'>
                    <div class='hg-powers-list-card'>
                        <div class='hg-powers-list-card__main'>
                            <img class='hg-powers-list-icon' src='img/ui/icons/icon_claws.webp'> " . htmlspecialchars($row["name"]) . "
                        </div>
                        <div class='hg-powers-list-card__meta'>" . htmlspecialchars($row["rank"]) . "</div>
                    </div>
                </a>
            ";
        }
        echo "</fieldset>";
    }

    echo "<p align='right'>Dones hallados: $misterroboto</p>";
} else {
    echo "<p>Error: No se encontró el tipo de don especificado.</p>";
}
?>
