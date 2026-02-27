<?php
setMetaFromPage("Jugadores | Heaven's Gate", "Listado de jugadores de la campaña.", null, 'website');
header('Content-Type: text/html; charset=utf-8');
if ($link) { mysqli_set_charset($link, "utf8mb4"); }
include("app/partials/main_nav_bar.php");

if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

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

$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '2,7';
$chronicleNotInJoin = ($excludeChronicles !== '') ? " AND c.chronicle_id NOT IN ($excludeChronicles) " : "";

$query = "
    SELECT
        p.id AS player_id,
        p.pretty_id AS player_pretty_id,
        p.name AS player_name,
        p.surname AS player_surname,
        COUNT(DISTINCT c.id) AS player_characters
    FROM dim_players p
    LEFT JOIN fact_characters c ON c.player_id = p.id $chronicleNotInJoin
    WHERE p.show_in_catalog = 1
    GROUP BY p.id, p.pretty_id, p.name, p.surname
    ORDER BY p.name ASC, p.surname ASC
";

$result = mysqli_query($link, $query);
if (!$result) {
    die("Error en la consulta: " . mysqli_error($link));
}

$players = [];
while ($row = mysqli_fetch_assoc($result)) {
    $players[] = $row;
}
mysqli_free_result($result);

$pageSect = "Jugadores";
?>
<link rel="stylesheet" href="/assets/css/hg-playr.css">
<?php include_once("app/partials/datatable_assets.php"); ?>

<h2 class="playr-title">Jugadores</h2>

<div class="playr-wrap">
    <div class="playr-inner">
        <table id="tabla-jugadores" class="display playr-table">
            <thead>
                <tr>
                    <th>Jugador</th>
                    <th>Personajes</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function () {
    const players = <?= json_encode($players, JSON_UNESCAPED_UNICODE) ?>;
    const tbody = $('#tabla-jugadores tbody');

    players.forEach(p => {
        const playerSlug = p.player_pretty_id || p.player_id;
        const fullName = [p.player_name || '', p.player_surname || ''].join(' ').trim();
        const playerName = fullName !== '' ? fullName : String(p.player_id || '');
        const characters = Number(p.player_characters || 0);

        const safeName = escapeHtml(playerName);
        const href = '/players/' + encodeURIComponent(String(playerSlug));

        tbody.append(`<tr><td><a href="${href}">${safeName}</a></td><td>${characters}</td></tr>`);
    });

    $('#tabla-jugadores').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, "asc"]],
        language: {
            search: "&#128269; Buscar: ",
            lengthMenu: "Mostrar _MENU_ jugadores",
            info: "Mostrando _START_ a _END_ de _TOTAL_ jugadores",
            infoEmpty: "No hay jugadores disponibles",
            emptyTable: "No hay datos en la tabla",
            paginate: {
                first: "Primero",
                last: "&Uacute;ltimo",
                next: "&#9654;",
                previous: "&#9664;"
            }
        }
    });
});

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, function (m) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
    });
}
</script>

