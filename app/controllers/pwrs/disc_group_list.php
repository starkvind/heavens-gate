<?php
setMetaFromPage("Disciplinas | Heaven's Gate", "Listado de poderes por disciplina.", null, 'website');
$routeParam = isset($_GET['b']) ? $_GET['b'] : '';

$consulta = "SELECT name, description FROM dim_discipline_types WHERE id = ? LIMIT 1";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $routeParam);
$stmt->execute();
$result = $stmt->get_result();
$ResultQuery = $result->fetch_assoc();

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

$consulta = "SELECT id, pretty_id, name, level FROM fact_discipline_powers WHERE disc = ? ORDER BY level";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $routeParam);
$stmt->execute();
$result = $stmt->get_result();

$totalDisciplinas = 0;

while ($row = $result->fetch_assoc()) {
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
