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

if (!function_exists('hg_bio_pack_group_url')) {
    function hg_bio_pack_group_url(mysqli $link, int $organizationId, int $groupId): string
    {
        $orgPath = (string)parse_url(pretty_url($link, 'dim_organizations', '/organizations', $organizationId), PHP_URL_PATH);
        $groupPath = (string)parse_url(pretty_url($link, 'dim_groups', '/groups', $groupId), PHP_URL_PATH);
        $orgSlug = basename($orgPath);
        $groupSlug = basename($groupPath);

        return '/groups/' . $orgSlug . '/' . $groupSlug;
    }
}

if (!function_exists('hg_bio_pack_org_chart_available')) {
    function hg_bio_pack_org_chart_available(mysqli $link, int $organizationId): bool
    {
        if ($organizationId <= 0 || !function_exists('hg_table_exists')) {
            return false;
        }
        if (!hg_table_exists($link, 'dim_organization_departments') || !hg_table_exists($link, 'bridge_characters_org')) {
            return false;
        }

        $stmt = $link->prepare("
            SELECT
                (SELECT COUNT(*) FROM dim_organization_departments WHERE organization_id = ? AND is_active = 1)
              + (SELECT COUNT(*) FROM bridge_characters_org WHERE organization_id = ? AND is_active = 1) AS total
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $organizationId, $organizationId);
        $stmt->execute();
        $rs = $stmt->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $stmt->close();
        return (int)($row['total'] ?? 0) > 0;
    }
}

include("app/partials/main_nav_bar.php");
echo "<h2>" . htmlspecialchars($nameTypePack, ENT_QUOTES, 'UTF-8') . "</h2>";
echo "<style>.bio-pack-org-chart-link{display:inline-block;margin-left:8px;padding:2px 8px;border:1px solid #33cccc;border-radius:999px;background:#071b4a;color:#dff7ff!important;font-size:10px;text-decoration:none}.bio-pack-org-chart-link:hover{background:#003b8f;color:#fff!important}</style>";

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
    $groupRows = $resultGrupo ? mysqli_num_rows($resultGrupo) : 0;
    $hasOrgChart = hg_bio_pack_org_chart_available($link, (int)$clanId);

    if ($groupRows > 0 || $hasOrgChart) {
        print("<fieldset id='renglonArchivos'>");
        print("<legend id='archivosLegend'>");
        $hrefClan = pretty_url($link, 'dim_organizations', '/organizations', (int)$clanId);
        print("<a href='" . htmlspecialchars($hrefClan, ENT_QUOTES, 'UTF-8') . "' title='" . htmlspecialchars($clanName, ENT_QUOTES, 'UTF-8') . "'>");
        print("&nbsp;" . htmlspecialchars($clanName, ENT_QUOTES, 'UTF-8') . "&nbsp;");
        print("</a>");
        if ($hasOrgChart) {
            print("<a class='bio-pack-org-chart-link' href='" . htmlspecialchars(rtrim($hrefClan, '/') . '/org-chart', ENT_QUOTES, 'UTF-8') . "' title='Organigrama de " . htmlspecialchars($clanName, ENT_QUOTES, 'UTF-8') . "'>Organigrama</a>");
        }
        print("</legend>");
        print("<ul class='listaManadas'>");

        if ($resultGrupo && $groupRows > 0) {
            while ($rowGrupo = mysqli_fetch_assoc($resultGrupo)) {
                $enActivo = (int)$rowGrupo["is_active"];
                $iconManada = ($enActivo === 0) ? $iconSept : $iconPack;

                $gid = (int)$rowGrupo["id"];
                $gname = (string)$rowGrupo["name"];

                print("<li class='listaManadas'>");
                $hrefGroup = hg_bio_pack_group_url($link, $clanId, $gid);
                print("<a href='" . htmlspecialchars($hrefGroup, ENT_QUOTES, 'UTF-8') . "' title='" . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . "'>");
                print("<img src='" . htmlspecialchars($iconManada, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . "' title='" . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . "' class='valign'/>");
                print(" " . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8'));
                print("</a></li>");

                $numeroDeGruposHallados++;
            }
        } else {
            print("<li class='listaManadas'>Organizacion sin grupos vinculados.</li>");
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
