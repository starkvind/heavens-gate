<?php include("app/partials/main_nav_bar.php"); ?>

<div class="sim-ui">
<h2>Listado de Armas</h2>
<br/>
<center>

<table width="100%">

<tr>
<td class="celdacombat" width="25%">Nombre</td>
<td class="celdacombat" width="25%">Habilidad</td>
<td class="celdacombat" width="25%">Da&ntilde;o</td>
<td class="celdacombat" width="25%">Veces usada</td>
</tr>

<?php
$consulta = "SELECT * FROM fact_sim_item_usage INNER JOIN vw_sim_items ON fact_sim_item_usage.item_id = vw_sim_items.id ORDER BY times_used DESC";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);
for ($i = 0; $i < $NFilas; $i++) {
    $ResultQuery = mysql_fetch_array($IdConsulta);

    $id = $ResultQuery["id"];
    $nambre = $ResultQuery["item_name_snapshot"];
    if ($nambre == "") {
        $nambre = $ResultQuery["name"];
    }
    $veces = $ResultQuery["times_used"];
    $ability = $ResultQuery['habilidad'];
    $damage = $ResultQuery['dano'];
    $plata = $ResultQuery['metal'];
    $bonux = $ResultQuery['bonus'];

    if (($ability == "Cuerpo a Cuerpo") && ($bonux == "0")) {
        $bonux = "Fuerza";
    } elseif ($ability == "Cuerpo a Cuerpo") {
        $bonux = "Fuerza + $bonux";
    }

    if ($damage == "Agravado") {
        $damage = "<a title='Agravado'>(*)</a>";
    } elseif ($damage == "Letal") {
        $damage = "<a title='Letal'>(+)</a>";
    } else {
        $damage = "<a title='Contundente'>(/)</a>";
    }

    if ($plata == 1) {
        $plata = "<a title='Plata'>&#8224;</a>";
    } else {
        $plata = "";
    }

    print("
    <tr>
        <td class='ajustcelda'>
            <a href='/inventory/item/$id' title='' target='_blank'>$nambre</a>
        </td>
        <td class='ajustcelda'>$ability</td>
        <td class='ajustcelda'>$bonux $damage $plata</td>
        <td class='ajustcelda'>$veces</td>
    </tr>");
}

mysql_free_result($IdConsulta);
?>

<tr>
<td colspan="4" style="text-align:right;"><h4>
<?php
$pageSect = ":: Armas utilizadas";

$sql = "SELECT SUM(times_used) AS suma FROM fact_sim_item_usage";
$result = mysql_query($sql, $link);
$row = mysql_fetch_array($result);

echo "<b>Las armas han sido utilizadas</b> ";
$titi = $row['suma'];
$tite = $titi;
echo "$tite veces";

mysql_free_result($result);
?>
</h4></td>
</tr>

</table>

<div class="sim-actions-row">
<a class="sim-classic-btn" href="/tools/combat-simulator">Volver</a>
</div>

</center>
</div>