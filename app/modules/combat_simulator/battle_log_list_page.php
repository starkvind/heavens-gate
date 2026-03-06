<?php include("app/partials/main_nav_bar.php"); ?>

<?php
if (!function_exists('sim_log_list_h')) {
    function sim_log_list_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sim_log_list_winner_html')) {
    function sim_log_list_winner_html($value)
    {
        $decoded = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
        $clean = strip_tags($decoded, '<b><strong>');
        $clean = preg_replace('/<\s*strong[^>]*>/i', '<b>', $clean);
        $clean = preg_replace('/<\s*\/\s*strong\s*>/i', '</b>', $clean);
        $clean = preg_replace('/<\s*b[^>]*>/i', '<b>', $clean);
        $clean = preg_replace('/<\s*\/\s*b\s*>/i', '</b>', $clean);
        return trim((string)$clean);
    }
}
?>

<div class="sim-ui">
<h2>Registro de Combates</h2>
<center>

<fieldset>
<legend>Combates</legend>

<?php

$pageSect = ":: Combates del Simulador";

$tamano_pagina = 30;
$pagina = isset($_GET["pagina"]) ? (int)$_GET["pagina"] : 0;
if (!$pagina) {
    $inicio = 0;
    $pagina = 1;
} else {
    if ($pagina < 1) {
        $pagina = 1;
    }
    $inicio = ($pagina - 1) * $tamano_pagina;
}

$consulta = "SELECT * FROM fact_sim_battles";
$IdConsulta = mysql_query($consulta, $link);
$num_total_registros = mysql_num_rows($IdConsulta);
$total_paginas = (int)ceil($num_total_registros / $tamano_pagina);

$consulta = "SELECT * FROM fact_sim_battles ORDER BY id DESC LIMIT $inicio,$tamano_pagina";
$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($NFilas == "") {
    echo "A&uacute;n no se ha celebrado ning&uacute;n combate.";
} else {
    echo "<table class='sim-combats-table'>";

    for ($i = 0; $i < $NFilas; $i++) {
        $ResultQuery = mysql_fetch_array($IdConsulta);

        $kid = (int)($ResultQuery["id"] ?? 0);
        $ki1 = sim_log_list_h($ResultQuery["fighter_one_alias_snapshot"] ?? "");
        $ki2 = sim_log_list_h($ResultQuery["fighter_two_alias_snapshot"] ?? "");
        $kires = sim_log_list_winner_html($ResultQuery["winner_summary"] ?? "");

        echo "
        <tr>
            <td class='sim-col-id'>#<a href='/tools/combat-simulator/log/$kid'>$kid</a></td>
            <td class='sim-col-p1'>$ki1</td>
            <td class='sim-col-vs'>VS</td>
            <td class='sim-col-p2'>$ki2</td>
            <td class='sim-col-result'>$kires</td>
        </tr>";
    }

    echo "</table>";
}

?>

</fieldset>

<p align='right'>
<?php
if ($total_paginas >= 2) {
    echo "P&aacute;gina: ";
}

if ($total_paginas > 1) {
    for ($ix = 1; $ix <= $total_paginas; $ix++) {
        if ($pagina == $ix) {
            echo $pagina . " ";
        } else {
            echo "<a href='/tools/combat-simulator/log?pagina=$ix'>" . $ix . "</a> ";
        }
    }
}
?>
</p>

<div class="sim-actions-row">
    <a class="sim-classic-btn" href="/tools/combat-simulator">Regresar</a>
</div>

</center>
</div>
