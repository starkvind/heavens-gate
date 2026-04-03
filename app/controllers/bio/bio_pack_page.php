<?php
include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!$link) {
    hg_public_log_error('bio_pack_page', 'missing DB connection');
    hg_public_render_error(
        'Biografías no disponibles',
        'No se pudo cargar la ficha solicitada en este momento.'
    );
    return;
}

if (!function_exists('hg_bio_pack_page_h')) {
    function hg_bio_pack_page_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_bio_pack_page_sanitize_int_csv')) {
    function hg_bio_pack_page_sanitize_int_csv($csv): string
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

if (!function_exists('hg_bio_pack_page_render_character_tile')) {
    function hg_bio_pack_page_render_character_tile(mysqli $link, array $row): void
    {
        $characterId = (int)($row['id'] ?? 0);
        $characterName = (string)($row['name'] ?? '');
        $characterAlias = (string)($row['alias'] ?? '');
        $characterImage = hg_character_avatar_url((string)($row['image_url'] ?? ''), (string)($row['gender'] ?? ''));
        $characterStatus = (string)($row['status'] ?? '');
        $characterKind = (string)($row['character_kind'] ?? '');

        if ($characterAlias === '') {
            $characterAlias = $characterName;
        }

        hg_render_character_avatar_tile([
            'href' => pretty_url($link, 'fact_characters', '/characters', $characterId),
            'title' => $characterName,
            'name' => $characterName,
            'alias' => $characterAlias,
            'character_id' => $characterId,
            'avatar_url' => $characterImage,
            'status' => $characterStatus,
            'character_kind' => $characterKind,
        ]);
    }
}

$typePack = isset($_GET['t']) ? (int)$_GET['t'] : 0;
$packId = isset($_GET['b']) ? (int)$_GET['b'] : 0;

$nameTypePack = '';
$nameTypeForTitle = '';
$query = '';

switch ($typePack) {
    case 1:
        $query = "SELECT * FROM dim_groups WHERE id = ?";
        $nameTypePack = "packs";
        $nameTypeForTitle = "Grupo";
        break;

    case 2:
        $query = "SELECT * FROM dim_organizations WHERE id = ?";
        $nameTypePack = "septs";
        $nameTypeForTitle = "Organización";
        break;

    default:
        hg_public_render_not_found(
            'Contenido no encontrado',
            'El tipo de contenido solicitado no es válido.',
            true
        );
        return;
}

$excludeChronicles = isset($excludeChronicles)
    ? hg_bio_pack_page_sanitize_int_csv($excludeChronicles)
    : '';
$cronicaNotInSQL = ($excludeChronicles !== '')
    ? " AND p.chronicle_id NOT IN ($excludeChronicles) "
    : "";

$characterKindCol = 'character_kind';
$rsKind = mysqli_query($link, "SHOW COLUMNS FROM fact_characters LIKE 'kind'");
if ($rsKind) {
    if (mysqli_num_rows($rsKind) > 0) {
        $characterKindCol = 'kind';
    }
    mysqli_free_result($rsKind);
}

$stmtMain = mysqli_prepare($link, $query);
if (!$stmtMain) {
    hg_public_log_error('bio_pack_page', 'main prepare failed: ' . mysqli_error($link));
    hg_public_render_error(
        'Biografías no disponibles',
        'No se pudo cargar la ficha solicitada en este momento.',
        500,
        true
    );
    return;
}

mysqli_stmt_bind_param($stmtMain, 'i', $packId);
if (!mysqli_stmt_execute($stmtMain)) {
    hg_public_log_error('bio_pack_page', 'main execute failed: ' . mysqli_error($link));
    mysqli_stmt_close($stmtMain);
    hg_public_render_error(
        'Biografías no disponibles',
        'No se pudo cargar la ficha solicitada en este momento.',
        500,
        true
    );
    return;
}

$result = mysqli_stmt_get_result($stmtMain);
if (!$result) {
    hg_public_log_error('bio_pack_page', 'main result failed: ' . mysqli_error($link));
    mysqli_stmt_close($stmtMain);
    hg_public_render_error(
        'Biografías no disponibles',
        'No se pudo cargar la ficha solicitada en este momento.',
        500,
        true
    );
    return;
}

$resultQuery = mysqli_fetch_assoc($result);
mysqli_free_result($result);
mysqli_stmt_close($stmtMain);

if (!$resultQuery) {
    hg_public_render_not_found(
        'Contenido no encontrado',
        'No hay resultados para esta referencia.',
        true
    );
    return;
}

$namePack = (string)($resultQuery['name'] ?? '');
$infoPack = (string)($resultQuery['description'] ?? '');

$pageSect = "Biografías";
$pageTitle2 = $namePack;
setMetaFromPage(
    $namePack . " | " . $nameTypeForTitle . " | Heaven's Gate",
    meta_excerpt($infoPack),
    null,
    'article'
);

$clanLink = '';
if ($typePack === 1) {
    $clanBridgeQuery = "
        SELECT c.id AS organization_id, c.name AS clan_name
        FROM bridge_organizations_groups b
        INNER JOIN dim_organizations c ON c.id = b.organization_id
        WHERE b.group_id = ?
          AND (b.is_active = 1 OR b.is_active IS NULL)
        ORDER BY b.id ASC
        LIMIT 1
    ";

    $clanStmt = mysqli_prepare($link, $clanBridgeQuery);
    if ($clanStmt) {
        mysqli_stmt_bind_param($clanStmt, 'i', $packId);

        if (mysqli_stmt_execute($clanStmt)) {
            $clanResult = mysqli_stmt_get_result($clanStmt);
            if ($clanResult && mysqli_num_rows($clanResult) > 0) {
                $clanRow = mysqli_fetch_assoc($clanResult);
                $clanDataId = (int)($clanRow['organization_id'] ?? 0);
                $clanName = (string)($clanRow['clan_name'] ?? '');
                $clanHref = pretty_url($link, 'dim_organizations', '/organizations', $clanDataId);
                $clanLink = "<a href='" . hg_bio_pack_page_h($clanHref) . "'>" . hg_bio_pack_page_h($clanName) . "</a>";
            }

            if ($clanResult) {
                mysqli_free_result($clanResult);
            }
        } else {
            hg_public_log_error('bio_pack_page', 'clan bridge execute failed: ' . mysqli_error($link));
        }

        mysqli_stmt_close($clanStmt);
    } else {
        hg_public_log_error('bio_pack_page', 'clan bridge prepare failed: ' . mysqli_error($link));
    }
}

$totemLink = '';
$totemPack = isset($resultQuery['totem_id']) ? (int)$resultQuery['totem_id'] : 0;

if ($totemPack > 0) {
    $totemQuery = "SELECT id, name FROM dim_totems WHERE id = ? LIMIT 1";
    $totemStmt = mysqli_prepare($link, $totemQuery);

    if ($totemStmt) {
        mysqli_stmt_bind_param($totemStmt, 'i', $totemPack);

        if (mysqli_stmt_execute($totemStmt)) {
            $totemResult = mysqli_stmt_get_result($totemStmt);
            if ($totemResult && mysqli_num_rows($totemResult) > 0) {
                $totemRow = mysqli_fetch_assoc($totemResult);
                $totemDataId = (int)($totemRow['id'] ?? 0);
                $totemDataName = (string)($totemRow['name'] ?? '');
                $totemLink = "<a href='/powers/totem/" . hg_bio_pack_page_h($totemDataId) . "' target='_blank'>" . hg_bio_pack_page_h($totemDataName) . "</a>";
            }

            if ($totemResult) {
                mysqli_free_result($totemResult);
            }
        } else {
            hg_public_log_error('bio_pack_page', 'totem execute failed: ' . mysqli_error($link));
        }

        mysqli_stmt_close($totemStmt);
    } else {
        hg_public_log_error('bio_pack_page', 'totem prepare failed: ' . mysqli_error($link));
    }
}

if ($typePack === 1) {
    $packNavLinks = ($clanLink !== '') ? ($clanLink . " &raquo;&nbsp;" . hg_bio_pack_page_h($namePack)) : hg_bio_pack_page_h($namePack);
} else {
    $packNavLinks = hg_bio_pack_page_h($namePack);
}

include("app/partials/main_nav_bar.php");
echo "<h2>" . hg_bio_pack_page_h($namePack) . "</h2>";

echo "<table class='notix'>";
echo "<tr><td colspan='2' class='texti'>";

if ($typePack === 1 && $clanLink !== '') {
    echo "<b>Clan</b>: $clanLink<br/>";
}

if ($totemLink !== '') {
    echo "<b>Tótem</b>: $totemLink<br/>";
}

echo "<b>Descripción</b>:<br/><br/>" . $infoPack;
echo "</td></tr>";

if ($typePack === 1) {
    $packsOfSeptQuery = "
        SELECT p.id, p.name, p.alias, p.image_url, p.gender, COALESCE(dcs.label, '') AS status, p.status_id, p.`$characterKindCol` AS character_kind
        FROM bridge_characters_groups bg
        INNER JOIN fact_characters p ON p.id = bg.character_id
        LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
        WHERE bg.group_id = ?
          AND (bg.is_active = 1 OR bg.is_active IS NULL)
          $cronicaNotInSQL
        ORDER BY
            CASE LOWER(TRIM(COALESCE(dcs.label, '')))
                WHEN 'paradero desconocido' THEN 1
                WHEN 'cadaver' THEN 2
                WHEN 'aun por aparecer' THEN 9999
                ELSE 0
            END,
            p.name
    ";

    $stmtSept = mysqli_prepare($link, $packsOfSeptQuery);
    if ($stmtSept) {
        mysqli_stmt_bind_param($stmtSept, 'i', $packId);

        if (mysqli_stmt_execute($stmtSept)) {
            $packsOfSeptResult = mysqli_stmt_get_result($stmtSept);

            if ($packsOfSeptResult && mysqli_num_rows($packsOfSeptResult) > 0) {
                echo "<tr><td colspan='2' class='texti'><b>Miembros de " . hg_bio_pack_page_h($namePack) . "</b>:<br/><br/>";
                echo "<div style='padding-left:30px;'>";

                while ($packRow = mysqli_fetch_assoc($packsOfSeptResult)) {
                    hg_bio_pack_page_render_character_tile($link, $packRow);
                }

                echo "</div>";
                echo "</td></tr>";
            }

            if ($packsOfSeptResult) {
                mysqli_free_result($packsOfSeptResult);
            }
        } else {
            hg_public_log_error('bio_pack_page', 'active members execute failed: ' . mysqli_error($link));
        }

        mysqli_stmt_close($stmtSept);
    } else {
        hg_public_log_error('bio_pack_page', 'active members prepare failed: ' . mysqli_error($link));
    }

    $oldMembersQuery = "
        SELECT p.id, p.name, p.alias, p.image_url, p.gender, COALESCE(dcs.label, '') AS status, p.status_id, p.`$characterKindCol` AS character_kind
        FROM bridge_characters_groups bg
        INNER JOIN fact_characters p ON p.id = bg.character_id
        LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
        WHERE bg.group_id = ?
          AND bg.is_active = 0
          $cronicaNotInSQL
        ORDER BY
            CASE LOWER(TRIM(COALESCE(dcs.label, '')))
                WHEN 'paradero desconocido' THEN 1
                WHEN 'cadaver' THEN 2
                WHEN 'aun por aparecer' THEN 9999
                ELSE 0
            END,
            p.name
    ";

    $stmtOldMembers = mysqli_prepare($link, $oldMembersQuery);
    if ($stmtOldMembers) {
        mysqli_stmt_bind_param($stmtOldMembers, 'i', $packId);

        if (mysqli_stmt_execute($stmtOldMembers)) {
            $oldMembersResult = mysqli_stmt_get_result($stmtOldMembers);

            if ($oldMembersResult && mysqli_num_rows($oldMembersResult) > 0) {
                echo "<tr><td colspan='2' class='texti'><b>Antiguos miembros</b>:<br/><br/>";
                echo "<div style='padding-left:30px;'>";

                while ($oldMemberRow = mysqli_fetch_assoc($oldMembersResult)) {
                    hg_bio_pack_page_render_character_tile($link, $oldMemberRow);
                }

                echo "</div>";
                echo "</td></tr>";
            }

            if ($oldMembersResult) {
                mysqli_free_result($oldMembersResult);
            }
        } else {
            hg_public_log_error('bio_pack_page', 'old members execute failed: ' . mysqli_error($link));
        }

        mysqli_stmt_close($stmtOldMembers);
    } else {
        hg_public_log_error('bio_pack_page', 'old members prepare failed: ' . mysqli_error($link));
    }
}

if ($typePack === 2) {
    $packsActiveQuery = "
        SELECT m.id, m.name
        FROM bridge_organizations_groups b
        INNER JOIN dim_groups m ON m.id = b.group_id
        WHERE b.organization_id = ?
          AND (b.is_active = 1 OR b.is_active IS NULL)
          AND m.is_active = 1
          " . (($excludeChronicles !== '') ? " AND m.chronicle_id NOT IN ($excludeChronicles) " : "") . "
        ORDER BY m.name
    ";

    $packsInactiveQuery = "
        SELECT m.id, m.name
        FROM bridge_organizations_groups b
        INNER JOIN dim_groups m ON m.id = b.group_id
        WHERE b.organization_id = ?
          AND (b.is_active = 1 OR b.is_active IS NULL)
          AND m.is_active = 0
          " . (($excludeChronicles !== '') ? " AND m.chronicle_id NOT IN ($excludeChronicles) " : "") . "
        ORDER BY m.name
    ";

    $stmtA = mysqli_prepare($link, $packsActiveQuery);
    $stmtI = mysqli_prepare($link, $packsInactiveQuery);

    $packsOfSeptResult = null;
    $packsOfSeptResult2 = null;
    $packsOfSeptFilas = 0;
    $packsOfSeptFilas2 = 0;

    if ($stmtA) {
        mysqli_stmt_bind_param($stmtA, 'i', $packId);
        if (mysqli_stmt_execute($stmtA)) {
            $packsOfSeptResult = mysqli_stmt_get_result($stmtA);
            $packsOfSeptFilas = $packsOfSeptResult ? mysqli_num_rows($packsOfSeptResult) : 0;
        } else {
            hg_public_log_error('bio_pack_page', 'active groups execute failed: ' . mysqli_error($link));
        }
    } else {
        hg_public_log_error('bio_pack_page', 'active groups prepare failed: ' . mysqli_error($link));
    }

    if ($stmtI) {
        mysqli_stmt_bind_param($stmtI, 'i', $packId);
        if (mysqli_stmt_execute($stmtI)) {
            $packsOfSeptResult2 = mysqli_stmt_get_result($stmtI);
            $packsOfSeptFilas2 = $packsOfSeptResult2 ? mysqli_num_rows($packsOfSeptResult2) : 0;
        } else {
            hg_public_log_error('bio_pack_page', 'inactive groups execute failed: ' . mysqli_error($link));
        }
    } else {
        hg_public_log_error('bio_pack_page', 'inactive groups prepare failed: ' . mysqli_error($link));
    }

    $sumaSeptFilas = $packsOfSeptFilas + $packsOfSeptFilas2;
    if ($sumaSeptFilas > 0) {
        $widthCelda = ($packsOfSeptFilas > 0 && $packsOfSeptFilas2 > 0) ? "50%" : "100%";
        echo "<tr>";

        if ($packsOfSeptFilas > 0) {
            echo "<td class='texti' style='width:$widthCelda; vertical-align:top;'><b>En activo</b>:<br/><ul>";
            while ($rowA = mysqli_fetch_assoc($packsOfSeptResult)) {
                echo "<li><a href='" . hg_bio_pack_page_h(pretty_url($link, 'dim_groups', '/groups', (int)$rowA['id'])) . "'>" . hg_bio_pack_page_h($rowA['name']) . "</a></li>";
            }
            echo "</ul></td>";
        }

        if ($packsOfSeptFilas2 > 0) {
            echo "<td class='texti' style='width:$widthCelda; vertical-align:top;'><b>Grupos antiguos</b>:<br/><ul>";
            while ($rowI = mysqli_fetch_assoc($packsOfSeptResult2)) {
                echo "<li><a href='" . hg_bio_pack_page_h(pretty_url($link, 'dim_groups', '/groups', (int)$rowI['id'])) . "'>" . hg_bio_pack_page_h($rowI['name']) . "</a></li>";
            }
            echo "</ul></td>";
        }

        echo "</tr>";
    }

    if ($packsOfSeptResult) {
        mysqli_free_result($packsOfSeptResult);
    }
    if ($packsOfSeptResult2) {
        mysqli_free_result($packsOfSeptResult2);
    }
    if ($stmtA) {
        mysqli_stmt_close($stmtA);
    }
    if ($stmtI) {
        mysqli_stmt_close($stmtI);
    }

    $charsWithoutPackQuery = "
        SELECT p.id, p.name, p.alias, p.image_url, p.gender, COALESCE(dcs.label, '') AS status, p.status_id, p.`$characterKindCol` AS character_kind
        FROM bridge_characters_organizations bc
        INNER JOIN fact_characters p ON p.id = bc.character_id
        LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
        LEFT JOIN bridge_characters_groups bg
            ON bg.character_id = p.id
           AND (bg.is_active = 1 OR bg.is_active IS NULL)
        WHERE bc.organization_id = ?
          AND (bc.is_active = 1 OR bc.is_active IS NULL)
          AND bg.character_id IS NULL
          $cronicaNotInSQL
        ORDER BY
            CASE LOWER(TRIM(COALESCE(dcs.label, '')))
                WHEN 'paradero desconocido' THEN 1
                WHEN 'cadaver' THEN 2
                WHEN 'aun por aparecer' THEN 9999
                ELSE 0
            END,
            p.name
    ";

    $charsWithoutPackStmt = mysqli_prepare($link, $charsWithoutPackQuery);
    if ($charsWithoutPackStmt) {
        mysqli_stmt_bind_param($charsWithoutPackStmt, 'i', $packId);

        if (mysqli_stmt_execute($charsWithoutPackStmt)) {
            $charsWithoutPackResult = mysqli_stmt_get_result($charsWithoutPackStmt);

            if ($charsWithoutPackResult && mysqli_num_rows($charsWithoutPackResult) > 0) {
                echo "<tr><td colspan='2' class='texti'><b>Personajes</b>:<br/><br/>";
                echo "<div style='padding-left:30px;'>";

                while ($charRow = mysqli_fetch_assoc($charsWithoutPackResult)) {
                    hg_bio_pack_page_render_character_tile($link, $charRow);
                }

                echo "</div>";
                echo "</td></tr>";
            }

            if ($charsWithoutPackResult) {
                mysqli_free_result($charsWithoutPackResult);
            }
        } else {
            hg_public_log_error('bio_pack_page', 'characters without group execute failed: ' . mysqli_error($link));
        }

        mysqli_stmt_close($charsWithoutPackStmt);
    } else {
        hg_public_log_error('bio_pack_page', 'characters without group prepare failed: ' . mysqli_error($link));
    }
}

echo "</table>";
?>
