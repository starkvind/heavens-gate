<?php include("app/partials/main_nav_bar.php"); ?>
<?php include_once("sim_battle_summary.php"); ?>

<?php
if (!function_exists('sim_log_list_h')) {
    function sim_log_list_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sim_log_list_table_exists')) {
    function sim_log_list_table_exists($link, $tableName)
    {
        $safe = mysql_real_escape_string((string)$tableName, $link);
        $rs = mysql_query("SHOW TABLES LIKE '$safe'", $link);
        return ($rs && mysql_num_rows($rs) > 0);
    }
}

if (!function_exists('sim_log_list_column_exists')) {
    function sim_log_list_column_exists($link, $tableName, $columnName)
    {
        $safeTable = mysql_real_escape_string((string)$tableName, $link);
        $safeCol = mysql_real_escape_string((string)$columnName, $link);
        $rs = mysql_query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeCol'", $link);
        return ($rs && mysql_num_rows($rs) > 0);
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
$selectedSeasonId = isset($_GET["season_id"]) ? (int)$_GET["season_id"] : 0;
if (!$pagina) {
    $inicio = 0;
    $pagina = 1;
} else {
    if ($pagina < 1) {
        $pagina = 1;
    }
    $inicio = ($pagina - 1) * $tamano_pagina;
}

$seasonOptions = array();
$hasSeasonTables = sim_log_list_table_exists($link, 'fact_sim_seasons');
$hasSeasonColumn = sim_log_list_column_exists($link, 'fact_sim_battles', 'season_id');
if ($hasSeasonTables) {
    $rsSeason = mysql_query("SELECT id, COALESCE(name, '') AS name FROM fact_sim_seasons ORDER BY is_active DESC, updated_at DESC, id DESC", $link);
    if ($rsSeason) {
        while ($r = mysql_fetch_array($rsSeason)) {
            $seasonOptions[] = $r;
        }
    }
}

$where = '';
if ($hasSeasonColumn && $selectedSeasonId > 0) {
    $where = " WHERE season_id = " . (int)$selectedSeasonId;
}

$consulta = "SELECT COUNT(*) AS total FROM fact_sim_battles{$where}";
$IdConsulta = mysql_query($consulta, $link);
$num_total_registros = 0;
if ($IdConsulta && mysql_num_rows($IdConsulta) > 0) {
    $rowTotal = mysql_fetch_array($IdConsulta);
    $num_total_registros = (int)($rowTotal['total'] ?? 0);
}
$total_paginas = (int)ceil($num_total_registros / $tamano_pagina);

$consulta = "SELECT * FROM fact_sim_battles{$where} ORDER BY id DESC LIMIT $inicio,$tamano_pagina";
$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);

if ($hasSeasonColumn && !empty($seasonOptions)) {
    echo "<form method='get' id='simLogSeasonForm' action='/tools/combat-simulator/log' style='margin:0 0 10px 0; text-align:left;'>";
    echo "<label style='display:inline-block; margin-right:8px;'>Temporada</label>";
    echo "<select name='season_id' id='simLogSeasonSelect' class='inp' style='max-width:260px;'>";
    echo "<option value='0'>Todas</option>";
    foreach ($seasonOptions as $sopt) {
        $sid = (int)($sopt['id'] ?? 0);
        $sname = sim_log_list_h($sopt['name'] ?? ('#' . $sid));
        $sel = ($sid === $selectedSeasonId) ? " selected='selected'" : '';
        echo "<option value='{$sid}'{$sel}>{$sname} [ID:{$sid}]</option>";
    }
    echo "</select>";
    if ($pagina > 1) {
        echo "<input type='hidden' name='pagina' value='1'>";
    }
    echo "</form>";
}

if ($NFilas == "") {
    echo "A&uacute;n no se ha celebrado ning&uacute;n combate.";
} else {
    echo "<table class='sim-combats-table'>";

    for ($i = 0; $i < $NFilas; $i++) {
        $ResultQuery = mysql_fetch_array($IdConsulta);

        $kid = (int)($ResultQuery["id"] ?? 0);
        $ki1 = sim_log_list_h($ResultQuery["fighter_one_alias_snapshot"] ?? "");
        $ki2 = sim_log_list_h($ResultQuery["fighter_two_alias_snapshot"] ?? "");
        $kires = sim_battle_summary_html_from_row($ResultQuery);

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
            $qs = "pagina=$ix";
            if ($selectedSeasonId > 0) {
                $qs .= "&season_id=" . (int)$selectedSeasonId;
            }
            echo "<a href='/tools/combat-simulator/log?$qs'>" . $ix . "</a> ";
        }
    }
}
?>
</p>

<div class="sim-actions-row">
    <a class="sim-classic-btn" href="/tools/combat-simulator">Regresar</a>
</div>

<script>
(function() {
    var form = document.getElementById('simLogSeasonForm');
    var select = document.getElementById('simLogSeasonSelect');
    if (!form || !select) { return; }
    select.addEventListener('change', function() {
        form.submit();
    });
})();
</script>

</center>
</div>
