<?php
setMetaFromPage("Grupos y sociedades | Heaven's Gate", "Listado de grupos, manadas y clanes.", null, 'website');
// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

$nameTypePack = "Grupos y sociedades";
$iconPack = "img/ui/icons/kek.gif";
$iconSept = "img/ui/icons/kek2.gif";
$pageSect = "Biografías";
$pageTitle2 = $nameTypePack;

// Si no existe, deja excludeChronicles vacío (no excluye nada)
$excludeChronicles = isset($excludeChronicles) && trim($excludeChronicles) !== '' ? $excludeChronicles : ''; // ej: "2,3,5"

// ============================================================
include("app/partials/main_nav_bar.php");
echo "<h2>" . htmlspecialchars($nameTypePack, ENT_QUOTES, 'UTF-8') . "</h2>";

// 1) Sacar clanes (dim)
$consulta = "SELECT id, name FROM dim_organizations ORDER BY sort_order";
$result = mysqli_query($link, $consulta);

if (!$result) {
    echo "Error en la consulta: " . mysqli_error($link);
    exit;
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

// 2) Preparar stmt para obtener manadas por bridge (más eficiente)
$sqlGrupoBase = "
    SELECT m.id, m.name, m.is_active
    FROM bridge_organizations_groups b
    INNER JOIN dim_groups m ON m.id = b.group_id
    WHERE b.clan_id = ?
      AND (b.is_active = 1 OR b.is_active IS NULL)
";

if ($excludeChronicles !== '') {
    // OJO: asume que $excludeChronicles es una lista de enteros separada por comas (ej: "2,3")
    $sqlGrupoBase .= " AND m.chronicle_id NOT IN ($excludeChronicles) ";
}

$sqlGrupoBase .= " ORDER BY m.name ";

$stmtGrupo = mysqli_prepare($link, $sqlGrupoBase);
if (!$stmtGrupo) {
    echo "Error preparando consulta de grupos: " . mysqli_error($link);
    exit;
}

// 3) Pintar clanes + sus manadas enlazadas por bridge
foreach ($clanes as $clan) {
    $clanId = $clan['id'];
    $clanName = $clan['name'];

    mysqli_stmt_bind_param($stmtGrupo, 'i', $clanId);
    mysqli_stmt_execute($stmtGrupo);
    $resultGrupo = mysqli_stmt_get_result($stmtGrupo);

    if ($resultGrupo && mysqli_num_rows($resultGrupo) > 0) {
        print("<fieldset id='renglonArchivos'>");

        // TITULO SECCION (link al clan)
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

// Footer contadores
print("<p style='text-align:right;'>Organizaciones halladas: " . htmlspecialchars((string)$numeroClanesHallados, ENT_QUOTES, 'UTF-8'));
print("<br/>Grupos hallados: " . htmlspecialchars((string)$numeroDeGruposHallados, ENT_QUOTES, 'UTF-8') . "</p>");
?>
