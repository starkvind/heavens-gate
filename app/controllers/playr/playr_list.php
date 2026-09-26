<?php
setMetaFromPage("Jugadores | Heaven's Gate", "Listado de jugadores de la campana.", null, 'website');
include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../domains/players/queries.php');
header('Content-Type: text/html; charset=utf-8');
if ($link) { mysqli_set_charset($link, "utf8mb4"); }
include("app/partials/main_nav_bar.php");

if (!$link) {
    hg_public_log_error('playr_list', 'missing DB connection');
    hg_public_render_error('Jugadores no disponibles', 'No se pudo cargar el listado de jugadores en este momento.');
    return;
}

$chronicleScope = isset($excludeChronicles) ? $excludeChronicles : '2,7';
$players = hg_players_fetch_catalog($link, $chronicleScope);
if ($players === false) {
    hg_public_log_error('playr_list', 'query failed: ' . mysqli_error($link));
    hg_public_render_error('Jugadores no disponibles', 'No se pudo cargar el listado de jugadores en este momento.');
    return;
}

$pageSect = "Jugadores";
?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-playr.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-playr.css"><?php } ?>
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
