<?php
setMetaFromPage("Dones | Heaven's Gate", "Listado de dones por categoria.", null, 'website');

$routeParam = isset($_GET['b']) ? $_GET['b'] : '';

$consulta = "SELECT name, determinant AS determinante, description FROM dim_gift_types WHERE id = ? LIMIT 1";
$stmt = $link->prepare($consulta);
$stmt->bind_param('s', $routeParam);
$stmt->execute();
$result = $stmt->get_result();
$ResultQuery = $result->fetch_assoc();

if ($ResultQuery) {
    $routeLabel = htmlspecialchars($ResultQuery["name"]);
    $determinante = htmlspecialchars($ResultQuery["determinante"]);
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

    $consulta = "SELECT DISTINCT gift_group FROM fact_gifts WHERE kind = ? ORDER BY gift_group";
    $stmt = $link->prepare($consulta);
    $stmt->bind_param('s', $routeParam);
    $stmt->execute();
    $result = $stmt->get_result();

    $domoarigato = [];
    while ($row = $result->fetch_assoc()) {
        $domoarigato[] = htmlspecialchars($row["gift_group"]);
    }

    $misterroboto = count($domoarigato);

    if ($misterroboto > 0) {
        foreach ($domoarigato as $grupo) {
            $consulta = "SELECT id, pretty_id, name, rank FROM fact_gifts WHERE gift_group = ? AND kind = ? ORDER BY rank";
            $stmt = $link->prepare($consulta);
            $stmt->bind_param('ss', $grupo, $routeParam);
            $stmt->execute();
            $result = $stmt->get_result();

            $riteClasificacion = ($routeLabel !== "Menores") ? $grupo : "Sin nivel";

            echo "<fieldset class='hg-powers-group-list'>";
            echo "<legend><b><a name='$riteClasificacion'></a> $riteClasificacion</b></legend>";

            while ($row = $result->fetch_assoc()) {
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
    }

    echo "<p align='right'>Dones hallados: $misterroboto</p>";
} else {
    echo "<p>Error: No se encontró el tipo de don especificado.</p>";
}
?>
