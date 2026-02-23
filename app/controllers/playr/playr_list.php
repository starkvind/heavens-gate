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

<link rel="stylesheet" href="/assets/vendor/datatables/jquery.dataTables.min.css">
<script src="/assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="/assets/vendor/datatables/jquery.dataTables.min.js"></script>

<h2 style="text-align:right;">Jugadores</h2>

<div style="display:flex; justify-content:center; width: 100%;">
    <div style="flex: 1; max-width:640px; min-width:640px;">
        <table id="tabla-jugadores" class="display" style="width:100%">
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

<style>
.dataTables_wrapper {
    color: #eee;
}

table.dataTable {
    background-color: transparent;
    color: #eee;
    border-collapse: collapse;
    width: 100%;
    font-size: 0.9em;
    border: 1px solid #000099;
}

table.dataTable td {
    text-align: left;
    border-bottom: 1px solid #000099;
    border-top: 1px solid #000099;
}

table.dataTable th {
    background-color: #000066;
    color: #fff;
}

table.dataTable tbody tr:hover {
    background-color: #111177;
}

.dataTables_info, .dataTables_paginate {
    margin-top: 1em;
}

.dataTables_filter input,
.dataTables_length select {
    font-family: verdana;
    font-size: 10px;
    background-color: #000066;
    color: #fff;
    padding: 0.5em;
    border: 1px solid #000099 !important;
    margin-bottom: 1em;
}

.dataTables_length option {
    background-color: #000099 !important;
}

.dataTables_paginate .paginate_button {
    color: #fff !important;
    background: #000066 !important;
    border: 1px solid #000099 !important;
    margin: 2px;
}

.dataTables_paginate .paginate_button:hover {
    color: #00CCFF !important;
    cursor: pointer;
    border: 1px solid #000088 !important;
}

.dataTables_paginate .paginate_button.current {
    background: #000044 !important;
}

@media (max-width: 720px) {
    div[style*='min-width:640px'] {
        min-width: 100% !important;
    }
}
</style>
