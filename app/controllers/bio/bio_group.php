<?php
setMetaFromPage(
    "Biografías por grupo | Heaven's Gate",
    "Listado de biografias agrupadas por tipo y organizacion.",
    null,
    'website'
);

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!$link) {
    hg_public_log_error('bio_group', 'missing DB connection');
    hg_public_render_error(
        'Biografías no disponibles',
        'No se pudo cargar el listado de biografias por grupo en este momento.'
    );
    return;
}

if (!function_exists('hg_bio_group_h')) {
    function hg_bio_group_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_bio_group_sanitize_int_csv')) {
    function hg_bio_group_sanitize_int_csv($csv): string
    {
        $csv = (string)$csv;
        if (trim($csv) === '') {
            return '';
        }

        $parts = preg_split('/\s*,\s*/', trim($csv));
        $ints = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^\d+$/', $part)) {
                $ints[] = (string)(int)$part;
            }
        }

        $ints = array_values(array_unique($ints));
        return implode(',', $ints);
    }
}

$idTipo = isset($_GET['t']) ? (int)$_GET['t'] : 0;
if ($idTipo <= 0) {
    hg_public_render_not_found(
        'Tipo no encontrado',
        'No se encontro el tipo de personaje solicitado.',
        true
    );
    return;
}

$valuePJ = "p.id, p.name, p.alias, COALESCE(dcs.label, '') AS status, p.status_id, p.image_url, p.gender, p.character_kind, p.character_type_id,
                    COALESCE(nc2.id, nc_from_pack.id, 0) AS organization_id,
                    COALESCE(nc2.pretty_id, nc_from_pack.pretty_id) AS clan_pretty_id,
                    COALESCE(nc2.name, nc_from_pack.name, 'Sin clan') AS clan_name,
                    IFNULL(COALESCE(nc2.sort_order, nc_from_pack.sort_order), 999999) AS organization_sort_order";

$excludeChronicles = isset($excludeChronicles)
    ? hg_bio_group_sanitize_int_csv($excludeChronicles)
    : '';
$cronicaNotInSQL = ($excludeChronicles !== '')
    ? " AND p.chronicle_id NOT IN ($excludeChronicles) "
    : '';

$typeQuery = "SELECT kind FROM dim_character_types WHERE id = ? LIMIT 1";
$stmtType = mysqli_prepare($link, $typeQuery);
if (!$stmtType) {
    hg_public_log_error('bio_group', 'type prepare failed: ' . mysqli_error($link));
    hg_public_render_error(
        'Biografías no disponibles',
        'No se pudo cargar el listado de biografias por grupo en este momento.',
        500,
        true
    );
    return;
}

mysqli_stmt_bind_param($stmtType, 'i', $idTipo);
if (!mysqli_stmt_execute($stmtType)) {
    hg_public_log_error('bio_group', 'type query execute failed: ' . mysqli_error($link));
    mysqli_stmt_close($stmtType);
    hg_public_render_error(
        'Biografías no disponibles',
        'No se pudo cargar el listado de biografias por grupo en este momento.',
        500,
        true
    );
    return;
}

$resultTypeQuery = mysqli_stmt_get_result($stmtType);
if (!$resultTypeQuery) {
    hg_public_log_error('bio_group', 'type query result failed: ' . mysqli_error($link));
    mysqli_stmt_close($stmtType);
    hg_public_render_error(
        'Biografías no disponibles',
        'No se pudo cargar el listado de biografias por grupo en este momento.',
        500,
        true
    );
    return;
}

$rowType = mysqli_fetch_assoc($resultTypeQuery);
mysqli_free_result($resultTypeQuery);
mysqli_stmt_close($stmtType);

if (!$rowType) {
    hg_public_render_not_found(
        'Tipo no encontrado',
        'No se encontro el tipo de personaje solicitado.',
        true
    );
    return;
}

$nombreTipoRaw = (string)($rowType['kind'] ?? '');
$nombreTipo = hg_bio_group_h($nombreTipoRaw);
$pageSect = $nombreTipoRaw . " | Biografías";
$pageTitle2 = $nombreTipoRaw;

setMetaFromPage(
    $nombreTipoRaw . " | Biografías | Heaven's Gate",
    "Listado de personajes agrupados por clan para el tipo " . $nombreTipoRaw . ".",
    null,
    'website'
);

$queryPJBase = "
    SELECT $valuePJ
    FROM fact_characters p
        LEFT JOIN dim_character_status dcs
            ON dcs.id = p.status_id
        LEFT JOIN bridge_characters_groups hcg
            ON hcg.character_id = p.id
           AND (hcg.is_active = 1 OR hcg.is_active IS NULL)
        LEFT JOIN dim_groups nm2
            ON nm2.id = hcg.group_id
        LEFT JOIN bridge_characters_organizations hcc
            ON hcc.character_id = p.id
           AND (hcc.is_active = 1 OR hcc.is_active IS NULL)
        LEFT JOIN dim_organizations nc2
            ON nc2.id = hcc.organization_id
        LEFT JOIN bridge_organizations_groups hcg2
            ON hcg2.group_id = nm2.id
           AND (hcg2.is_active = 1 OR hcg2.is_active IS NULL)
        LEFT JOIN dim_organizations nc_from_pack
            ON nc_from_pack.id = hcg2.organization_id
    WHERE p.__TYPE_COL__ = ?
      $cronicaNotInSQL
    ORDER BY organization_id ASC, p.name ASC
";

$runQuery = static function (string $typeCol) use ($link, $queryPJBase, $idTipo): array {
    $queryPJ = str_replace('__TYPE_COL__', $typeCol, $queryPJBase);
    $stmtPJ = mysqli_prepare($link, $queryPJ);
    if (!$stmtPJ) {
        return [null, null];
    }

    mysqli_stmt_bind_param($stmtPJ, 'i', $idTipo);
    if (!mysqli_stmt_execute($stmtPJ)) {
        mysqli_stmt_close($stmtPJ);
        return [null, null];
    }

    $resultPJ = mysqli_stmt_get_result($stmtPJ);
    if (!$resultPJ) {
        mysqli_stmt_close($stmtPJ);
        return [null, null];
    }

    return [$stmtPJ, $resultPJ];
};

$attemptedColumns = ['character_type_id', 'kind', 'tipo'];
$stmtPJ = null;
$resultPJ = null;

foreach ($attemptedColumns as $typeCol) {
    [$stmtPJ, $resultPJ] = $runQuery($typeCol);
    if ($stmtPJ !== null) {
        break;
    }
}

if ($stmtPJ === null) {
    hg_public_log_error('bio_group', 'character query prepare/execute failed for all known type columns');
    hg_public_render_error(
        'Biografías no disponibles',
        'No se pudo cargar el listado de biografias por grupo en este momento.',
        500,
        true
    );
    return;
}

$howMuch = 0;
$grupos = [];

if (mysqli_num_rows($resultPJ) > 0) {
    $howMuch = mysqli_num_rows($resultPJ);

    while ($rowPJ = mysqli_fetch_assoc($resultPJ)) {
        $clanId = (int)($rowPJ['organization_id'] ?? 0);
        $clanName = (string)($rowPJ['clan_name'] ?? 'Sin clan');
        $clanPretty = (string)($rowPJ['clan_pretty_id'] ?? '');
        $key = $clanId > 0 ? (string)$clanId : 'none';

        if (!isset($grupos[$key])) {
            $grupos[$key] = [
                'id' => $clanId,
                'name' => $clanName,
                'pretty_id' => $clanPretty,
                'sort_order' => (int)($rowPJ['organization_sort_order'] ?? 999999),
                'items' => [],
            ];
        }

        $grupos[$key]['items'][] = $rowPJ;
    }
}

mysqli_free_result($resultPJ);
mysqli_stmt_close($stmtPJ);

$keys = array_keys($grupos);
usort(
    $keys,
    static function (string $a, string $b) use ($grupos): int {
        if ($a === 'none') {
            return 1;
        }

        if ($b === 'none') {
            return -1;
        }

        $sortA = (int)($grupos[$a]['sort_order'] ?? 999999);
        $sortB = (int)($grupos[$b]['sort_order'] ?? 999999);

        if ($sortA !== $sortB) {
            return $sortA <=> $sortB;
        }

        return (int)$a <=> (int)$b;
    }
);

include("app/partials/main_nav_bar.php");
echo "<h2>$nombreTipo</h2>";

foreach ($keys as $key) {
    $grupo = $grupos[$key];
    $clanId = (int)$grupo['id'];
    $clanName = (string)$grupo['name'];
    $fieldsetId = 'clan_' . ($clanId > 0 ? $clanId : 'none');

    echo "<h3 class='toggleAfiliacion' data-target='" . hg_bio_group_h($fieldsetId) . "'>" . hg_bio_group_h($clanName) . "</h3>";
    echo "<fieldset class='grupoBioClan'>";
    echo "<div id='" . hg_bio_group_h($fieldsetId) . "' class='contenidoAfiliacion'>";

    foreach ($grupo['items'] as $rowPJ) {
        $idPJ = (int)($rowPJ['id'] ?? 0);
        $nombrePJ = (string)($rowPJ['name'] ?? '');
        $aliasPJ = (string)($rowPJ['alias'] ?? '');
        $imgPJ = (string)hg_character_avatar_url($rowPJ['image_url'] ?? '', $rowPJ['gender'] ?? '');
        $claseRaw = (string)($rowPJ['character_kind'] ?? $rowPJ['kind'] ?? '');
        $estadoPJ = (string)($rowPJ['status'] ?? '');

        if ($aliasPJ === '') {
            $aliasPJ = $nombrePJ;
        }

        $hrefPJ = pretty_url($link, 'fact_characters', '/characters', $idPJ);
        hg_render_character_avatar_tile([
            'href' => $hrefPJ,
            'title' => $nombrePJ,
            'name' => $nombrePJ,
            'alias' => $aliasPJ,
            'character_id' => $idPJ,
            'avatar_url' => $imgPJ,
            'status' => $estadoPJ,
            'character_kind' => $claseRaw,
        ]);
    }

    echo "</div>";
    echo "</fieldset>";
}

echo "<p align='right'>Personajes: " . hg_bio_group_h($howMuch) . "</p>";
?>
<style>
    .toggleAfiliacion {
        background: #05014e;
        color: #fff;
        border: 1px solid #000088;
        padding: 6px 10px;
        margin: 8px 0 0 0;
        font-size: 1.1em;
        cursor: pointer;
        width: 85%;
        text-align: left;
    }

    .toggleAfiliacion:hover {
        background: #000066;
        border: 1px solid #0000bb;
    }

    .contenidoAfiliacion {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 8px 0 12px 0;
    }

    .oculto {
        display: none;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggles = document.querySelectorAll('.toggleAfiliacion');

    for (var i = 0; i < toggles.length; i++) {
        toggles[i].addEventListener('click', function () {
            var targetId = this.getAttribute('data-target');
            var el = document.getElementById(targetId);

            if (!el) {
                return;
            }

            el.classList.toggle('oculto');
        });
    }
});
</script>
