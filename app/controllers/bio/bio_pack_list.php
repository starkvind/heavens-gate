<?php
setMetaFromPage("Grupos y sociedades | Heaven's Gate", "Listado de grupos, manadas y clanes.", null, 'website');
include_once(__DIR__ . '/../../helpers/public_response.php');

if (!$link) {
    hg_public_log_error('bio_pack_list', 'missing DB connection');
    hg_public_render_error('Grupos no disponibles', 'No se pudo cargar el listado de grupos y sociedades en este momento.');
    return;
}

$nameTypePack = "Grupos y sociedades";
$iconPack = "img/ui/icons/icon_person_active.png";
$iconSept = "img/ui/icons/icon_person_dead.png";
$pageSect = "Biografías";
$pageTitle2 = $nameTypePack;

$excludeChronicles = isset($excludeChronicles) && trim($excludeChronicles) !== '' ? $excludeChronicles : '';

include("app/partials/main_nav_bar.php");
echo "<h2>" . htmlspecialchars($nameTypePack, ENT_QUOTES, 'UTF-8') . "</h2>";

$consulta = "SELECT id, name FROM dim_organizations ORDER BY sort_order";
$result = mysqli_query($link, $consulta);
if (!$result) {
    hg_public_log_error('bio_pack_list', 'organization query failed: ' . mysqli_error($link));
    hg_public_render_error('Grupos no disponibles', 'No se pudo cargar el listado de grupos y sociedades en este momento.');
    return;
}

$clanes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $clanes[] = [
        'id'   => (int)$row['id'],
        'name' => (string)$row['name'],
    ];
}
mysqli_free_result($result);

$numeroClanesHallados = count($clanes);
$numeroDeGruposHallados = 0;

$sqlGrupoBase = "
    SELECT m.id, m.name, m.is_active
    FROM bridge_organizations_groups b
    INNER JOIN dim_groups m ON m.id = b.group_id
    WHERE b.organization_id = ?
      AND (b.is_active = 1 OR b.is_active IS NULL)
";

if ($excludeChronicles !== '') {
    $sqlGrupoBase .= " AND m.chronicle_id NOT IN ($excludeChronicles) ";
}

$sqlGrupoBase .= " ORDER BY m.name ";

$stmtGrupo = mysqli_prepare($link, $sqlGrupoBase);
if (!$stmtGrupo) {
    hg_public_log_error('bio_pack_list', 'group prepare failed: ' . mysqli_error($link));
    hg_public_render_error('Grupos no disponibles', 'No se pudo cargar el listado de grupos y sociedades en este momento.');
    return;
}

foreach ($clanes as $clan) {
    $clanId = $clan['id'];
    $clanName = $clan['name'];

    mysqli_stmt_bind_param($stmtGrupo, 'i', $clanId);
    mysqli_stmt_execute($stmtGrupo);
    $resultGrupo = mysqli_stmt_get_result($stmtGrupo);

    if ($resultGrupo && mysqli_num_rows($resultGrupo) > 0) {
        print("<fieldset id='renglonArchivos'>");
        print("<legend id='archivosLegend'>");
        $hrefClan = pretty_url($link, 'dim_organizations', '/organizations', (int)$clanId);
        print("<a href='" . htmlspecialchars($hrefClan, ENT_QUOTES, 'UTF-8') . "' title='" . htmlspecialchars($clanName, ENT_QUOTES, 'UTF-8') . "'>");
        print("&nbsp;" . htmlspecialchars($clanName, ENT_QUOTES, 'UTF-8') . "&nbsp;");
        print("</a>");
        print("</legend>");
        print("<ul class='listaManadas'>");

        while ($rowGrupo = mysqli_fetch_assoc($resultGrupo)) {
            $enActivo = (int)$rowGrupo["is_active"];
            $iconManada = ($enActivo === 0) ? $iconSept : $iconPack;

            $gid = (int)$rowGrupo["id"];
            $gname = (string)$rowGrupo["name"];

            print("<li class='listaManadas'>");
            $hrefGroup = pretty_url($link, 'dim_groups', '/groups', (int)$gid);
            print("<a href='" . htmlspecialchars($hrefGroup, ENT_QUOTES, 'UTF-8') . "' title='" . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . "'>");
            print("<img src='" . htmlspecialchars($iconManada, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . "' title='" . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . "' class='valign'/>");
            print(" " . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8'));
            print("</a></li>");

            $numeroDeGruposHallados++;
        }

        print("</ul>");
        print("</fieldset>");
    }

    if ($resultGrupo) {
        mysqli_free_result($resultGrupo);
    }
}

mysqli_stmt_close($stmtGrupo);

print("<p style='text-align:right;'>Organizaciones halladas: " . htmlspecialchars((string)$numeroClanesHallados, ENT_QUOTES, 'UTF-8'));
print("<br/>Grupos hallados: " . htmlspecialchars((string)$numeroDeGruposHallados, ENT_QUOTES, 'UTF-8') . "</p>");
?>
