<?php
require_once __DIR__ . '/../../domains/powers/queries.php';

setMetaFromPage("Disciplinas | Heaven's Gate", "Listado de poderes por disciplina.", null, 'website');
$routeParam = (int)hg_request_param($hgRequest, 'discipline_type');
$ResultQuery = hg_powers_fetch_type($link, 'disciplines', $routeParam);

$routeLabel = $ResultQuery ? htmlspecialchars($ResultQuery["name"]) : "-";
$descDones = $ResultQuery ? ($ResultQuery["description"] ?? '') : "<p>Descripción no disponible</p>";
$donTypePhrase = "Disciplina";
$pageSect = $donTypePhrase;
$pageTitle2 = $routeLabel;

if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-powers.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-powers.css">';
}

include("app/partials/main_nav_bar.php");

echo "<h2>$routeLabel</h2>";
echo "<fieldset class='hg-powers-description'>$descDones</fieldset>";
echo "<fieldset class='hg-powers-group-list'>";

$rows = hg_powers_fetch_disciplines_for_type($link, $routeParam);
if ($rows === false) $rows = [];
$totalDisciplinas = 0;

foreach ($rows as $row) {
    echo "
        <a href='" . htmlspecialchars(pretty_url($link, 'fact_discipline_powers', '/powers/discipline', (int)$row["id"])) . "'
           title='" . htmlspecialchars($row["name"]) . ", Nivel " . htmlspecialchars($row["level"]) . " de $routeLabel'>
            <div class='hg-powers-list-card'>
                <div class='hg-powers-list-card__main'>
                    <img class='hg-powers-list-icon' src='img/ui/icons/icon_fangs.webp'> " . htmlspecialchars($row["name"]) . "
                </div>
                <div class='hg-powers-list-card__meta'>" . htmlspecialchars($row["level"]) . "</div>
            </div>
        </a>
    ";
    $totalDisciplinas++;
}

echo "</fieldset>";
echo "<p align='right'>Niveles de $routeLabel: $totalDisciplinas</p>";
?>
