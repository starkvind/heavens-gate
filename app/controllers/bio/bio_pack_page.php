<?php
// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Helper escape
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Sanitiza lista tipo "1,2, 3" -> "1,2,3" (solo ints). Si queda vacío, devuelve ""
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

// Obtener y sanitizar los parámetros de la URL
$typePack = isset($_GET['t']) ? (int)$_GET['t'] : 0;  /* Tipo de contenido */
$packId   = isset($_GET['b']) ? (int)$_GET['b'] : 0;  /* ID del contenido */

$nameTypePack = '';
$nameTypeForTitle = '';
$query = '';

switch($typePack) {
    case 1:
        $query = "SELECT * FROM dim_groups WHERE id = ?";
        $nameTypePack = "packs";
        $nameTypeForTitle = "Manada";
        break;
    case 2:
        $query = "SELECT * FROM dim_organizations WHERE id = ?";
        $nameTypePack = "septs";
        $nameTypeForTitle = "Clan";
        break;
    default:
        // Tipo inválido: evita warnings y sal con algo legible
        echo "<h2>Error</h2>";
        echo "<p class='texti'>Tipo de contenido inválido.</p>";
        exit;
}

// Excluir crónicas (si existe la variable)
$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
$cronicaNotInSQL = ($excludeChronicles !== '') ? " AND p.chronicle_id NOT IN ($excludeChronicles) " : "";

// Ejecutar la consulta principal
$stmtMain = mysqli_prepare($link, $query);
if (!$stmtMain) {
    die("Error preparando consulta principal: " . mysqli_error($link));
}
mysqli_stmt_bind_param($stmtMain, 'i', $packId);
mysqli_stmt_execute($stmtMain);
$result = mysqli_stmt_get_result($stmtMain);

if (!$result) {
    mysqli_stmt_close($stmtMain);
    die("Error ejecutando consulta principal: " . mysqli_error($link));
}

if (mysqli_num_rows($result) <= 0) {
    mysqli_free_result($result);
    mysqli_stmt_close($stmtMain);
    //include("app/partials/main_nav_bar.php");
    echo "<h2>No encontrado</h2>";
    echo "<p class='texti'>No hay resultados para esta referencia.</p>";
    exit;
}

while ($ResultQuery = mysqli_fetch_assoc($result)) {

    // Datos base
    $namePack = $ResultQuery["name"] ?? '';
    $infoPack = $ResultQuery["description"] ?? $ResultQuery["desc"] ?? '';

    $pageSect   = $nameTypeForTitle;
    $pageTitle2 = $namePack;
    setMetaFromPage($namePack . " | " . $nameTypeForTitle . " | Heaven's Gate", meta_excerpt($infoPack), null, 'article');

    // ------------------------------------------------------------
    // TYPE 1: Resolver clan de una MANADA usando BRIDGE (OK)
    // ------------------------------------------------------------
    $clanLink = '';
    if ($typePack == 1) {
        $clanBridgeQuery = "
            SELECT c.id AS clan_id, c.name AS clan_name
            FROM bridge_organizations_groups b
            INNER JOIN dim_organizations c ON c.id = b.clan_id
            WHERE b.group_id = ?
              AND (b.is_active = 1 OR b.is_active IS NULL)
            ORDER BY b.id ASC
            LIMIT 1
        ";
        $clanStmt = mysqli_prepare($link, $clanBridgeQuery);
        if ($clanStmt) {
            mysqli_stmt_bind_param($clanStmt, 'i', $packId);
            mysqli_stmt_execute($clanStmt);
            $clanResult = mysqli_stmt_get_result($clanStmt);

            if ($clanResult && mysqli_num_rows($clanResult) > 0) {
                $clanRow = mysqli_fetch_assoc($clanResult);
                $clanDataId = (int)$clanRow["clan_id"];
                $clanName   = (string)$clanRow["clan_name"];
                $clanHref   = pretty_url($link, 'dim_organizations', '/organizations', (int)$clanDataId);
                $clanLink   = "<a href='" . h($clanHref) . "'>" . h($clanName) . "</a>";
            }

            if ($clanResult) mysqli_free_result($clanResult);
            mysqli_stmt_close($clanStmt);
        }
        // Si no hay bridge, no rompemos nada: simplemente no mostramos link
    }

    // ------------------------------------------------------------
    // Tótem (igual que lo tenías)
    // ------------------------------------------------------------
    $totemLink = "";
    $totemPack  = isset($ResultQuery["totem_id"]) ? (int)$ResultQuery["totem_id"] : 0;

    if ($totemPack > 0) {
        $totemQuery = "SELECT id, name FROM dim_totems WHERE id = ? LIMIT 1";
        $totemStmt  = mysqli_prepare($link, $totemQuery);
        if ($totemStmt) {
            mysqli_stmt_bind_param($totemStmt, 'i', $totemPack);
            mysqli_stmt_execute($totemStmt);
            $totemResult = mysqli_stmt_get_result($totemStmt);

            if ($totemResult && mysqli_num_rows($totemResult) > 0) {
                $totemRow      = mysqli_fetch_assoc($totemResult);
                $totemDataId   = (int)$totemRow["id"];
                $totemDataName = (string)$totemRow["name"];
                $totemLink     = "<a href='/powers/totem/" . h($totemDataId) . "' target='_blank'>" . h($totemDataName) . "</a>";
            }

            if ($totemResult) mysqli_free_result($totemResult);
            mysqli_stmt_close($totemStmt);
        }
    }

    if ($typePack == 1) {
        $packNavLinks = "$clanLink > $namePack";
    } else {
        $packNavLinks = $namePack;
    }

    // Render
    include("app/partials/main_nav_bar.php");
    echo "<h2>" . h($namePack) . "</h2>";

    echo "<table class='notix'>";
    echo "<tr><td colspan='2' class='texti'>";

    if ($typePack == 1 && $clanLink !== '') {
        echo "<b>Clan</b>: $clanLink<br/>";
    }
    if ($totemLink !== "") {
        echo "<b>T&oacute;tem</b>: $totemLink<br/>";
    }

    echo "<b>Descripci&oacute;n</b>:<br/><br/>" . $infoPack;
    echo "</td></tr>";

    // ============================================================
    // TYPE 1: MANADA -> miembros (AHORA VIA bridge_characters_groups)
    // ============================================================
    if ($typePack == 1) {

        $packsOfSeptQuery = "
            SELECT p.id, p.name, p.alias, p.img, p.estado
            FROM bridge_characters_groups bg
            INNER JOIN fact_characters p ON p.id = bg.character_id
            WHERE bg.group_id = ?
              AND (bg.is_active = 1 OR bg.is_active IS NULL)
              $cronicaNotInSQL
            ORDER BY
                CASE p.estado
                    WHEN 'Paradero desconocido' THEN 1
                    WHEN 'Cadáver' THEN 2
                    WHEN 'Aún por aparecer' THEN 9999
                    ELSE 0
                END,
                p.name
        ";

        $stmtSept = mysqli_prepare($link, $packsOfSeptQuery);
        if ($stmtSept) {
            mysqli_stmt_bind_param($stmtSept, 'i', $packId);
            mysqli_stmt_execute($stmtSept);
            $packsOfSeptResult = mysqli_stmt_get_result($stmtSept);

            if ($packsOfSeptResult && mysqli_num_rows($packsOfSeptResult) > 0) {
                echo "<tr><td colspan='2' class='texti'><b>Miembros de " . h($namePack) . "</b>:<br/><br/>";
                echo "<div style='padding-left:30px;'>";
                while ($packRow = mysqli_fetch_assoc($packsOfSeptResult)) {
                    $packDataId     = (int)$packRow["id"];
                    $packDataName   = (string)$packRow["name"];
                    $packDataAlias  = ($packRow["alias"] !== '' && $packRow["alias"] !== null) ? (string)$packRow["alias"] : $packDataName;
                    $packDataImg    = (string)$packRow["img"];
                    $packDataStatus = (string)$packRow["estado"];

                    switch ($packDataStatus) {
                        case "Aún por aparecer":       $simboloEstado = "(&#64)"; break;
                        case "Paradero desconocido":   $simboloEstado = "(&#63;)"; break;
                        case "Cadáver":                $simboloEstado = "(&#8224;)"; break;
                        default:                       $simboloEstado = ""; break;
                    }

                    echo "
                    <a href='" . h(pretty_url($link, 'fact_characters', '/characters', (int)$packDataId)) . "' title='" . h($packDataName) . "'>
                        <div class='marcoFotoBio'>
                            <div class='textoDentroFotoBio'>" . h($packDataAlias) . " $simboloEstado</div>
                            <div class='dentroFotoBio'>
                                <img class='fotoBioList' src='" . h($packDataImg) . "'>
                            </div>
                        </div>
                    </a>
                    ";
                }
                echo "</div>";
                echo "</td></tr>";
            }

            if ($packsOfSeptResult) mysqli_free_result($packsOfSeptResult);
            mysqli_stmt_close($stmtSept);
        }
    }

    // ============================================================
    // TYPE 2: CLAN -> manadas (por BRIDGE) + personajes sin manada
    //         personajes sin manada AHORA VIA bridge_characters_organizations
    // ============================================================
    if ($typePack == 2) {

        // Manadas activas e inactivas del clan via bridge
        $packsActiveQuery = "
            SELECT m.id, m.name
            FROM bridge_organizations_groups b
            INNER JOIN dim_groups m ON m.id = b.group_id
            WHERE b.clan_id = ?
              AND (b.is_active = 1 OR b.is_active IS NULL)
              AND m.is_active = 1
              " . (($excludeChronicles !== '') ? " AND m.chronicle_id NOT IN ($excludeChronicles) " : "") . "
            ORDER BY m.name
        ";

        $packsInactiveQuery = "
            SELECT m.id, m.name
            FROM bridge_organizations_groups b
            INNER JOIN dim_groups m ON m.id = b.group_id
            WHERE b.clan_id = ?
              AND (b.is_active = 1 OR b.is_active IS NULL)
              AND m.is_active = 0
              " . (($excludeChronicles !== '') ? " AND m.chronicle_id NOT IN ($excludeChronicles) " : "") . "
            ORDER BY m.name
        ";

        $stmtA = mysqli_prepare($link, $packsActiveQuery);
        $stmtI = mysqli_prepare($link, $packsInactiveQuery);

        $packsOfSeptResult  = null;
        $packsOfSeptResult2 = null;

        $packsOfSeptFilas  = 0;
        $packsOfSeptFilas2 = 0;

        if ($stmtA) {
            mysqli_stmt_bind_param($stmtA, 'i', $packId);
            mysqli_stmt_execute($stmtA);
            $packsOfSeptResult = mysqli_stmt_get_result($stmtA);
            $packsOfSeptFilas  = ($packsOfSeptResult) ? mysqli_num_rows($packsOfSeptResult) : 0;
        }

        if ($stmtI) {
            mysqli_stmt_bind_param($stmtI, 'i', $packId);
            mysqli_stmt_execute($stmtI);
            $packsOfSeptResult2 = mysqli_stmt_get_result($stmtI);
            $packsOfSeptFilas2  = ($packsOfSeptResult2) ? mysqli_num_rows($packsOfSeptResult2) : 0;
        }

        $sumaSeptFilas = $packsOfSeptFilas + $packsOfSeptFilas2;

        if ($sumaSeptFilas > 0) {
            $widthCelda = ($packsOfSeptFilas > 0 && $packsOfSeptFilas2 > 0) ? "50%" : "100%";
            echo "<tr>";

            if ($packsOfSeptFilas > 0) {
                echo "<td class='texti' style='width:$widthCelda; vertical-align:top;'><b>En activo</b>:<br/><ul>";
                while ($rowA = mysqli_fetch_assoc($packsOfSeptResult)) {
                    echo "<li><a href='" . h(pretty_url($link, 'dim_groups', '/groups', (int)$rowA["id"])) . "'>" . h($rowA["name"]) . "</a></li>";
                }
                echo "</ul></td>";
            }

            if ($packsOfSeptFilas2 > 0) {
                echo "<td class='texti' style='width:$widthCelda; vertical-align:top;'><b>Grupos antiguos</b>:<br/><ul>";
                while ($rowI = mysqli_fetch_assoc($packsOfSeptResult2)) {
                    echo "<li><a href='" . h(pretty_url($link, 'dim_groups', '/groups', (int)$rowI["id"])) . "'>" . h($rowI["name"]) . "</a></li>";
                }
                echo "</ul></td>";
            }

            echo "</tr>";
        }

        if ($packsOfSeptResult)  mysqli_free_result($packsOfSeptResult);
        if ($packsOfSeptResult2) mysqli_free_result($packsOfSeptResult2);
        if ($stmtA) mysqli_stmt_close($stmtA);
        if ($stmtI) mysqli_stmt_close($stmtI);

        // Personajes del clan sin manada:
        //  - Clan: bridge_characters_organizations
        //  - Sin manada: NO existe enlace activo en bridge_characters_groups
        $charsWithoutPackQuery = "
            SELECT p.id, p.name, p.alias, p.img, p.estado
            FROM bridge_characters_organizations bc
            INNER JOIN fact_characters p ON p.id = bc.character_id
            LEFT JOIN bridge_characters_groups bg
                ON bg.character_id = p.id
               AND (bg.is_active = 1 OR bg.is_active IS NULL)
            WHERE bc.clan_id = ?
              AND (bc.is_active = 1 OR bc.is_active IS NULL)
              AND bg.character_id IS NULL
              $cronicaNotInSQL
            ORDER BY
                CASE p.estado
                    WHEN 'Paradero desconocido' THEN 1
                    WHEN 'Cadáver' THEN 2
                    WHEN 'Aún por aparecer' THEN 9999
                    ELSE 0
                END,
                p.name
        ";

        $charsWithoutPackStmt = mysqli_prepare($link, $charsWithoutPackQuery);
        if ($charsWithoutPackStmt) {
            mysqli_stmt_bind_param($charsWithoutPackStmt, 'i', $packId);
            mysqli_stmt_execute($charsWithoutPackStmt);
            $charsWithoutPackResult = mysqli_stmt_get_result($charsWithoutPackStmt);

            if ($charsWithoutPackResult && mysqli_num_rows($charsWithoutPackResult) > 0) {
                echo "<tr><td colspan='2' class='texti'><b>Personajes</b>:<br/><br/>";
                echo "<div style='padding-left:30px;'>";
                while ($charRow = mysqli_fetch_assoc($charsWithoutPackResult)) {
                    $cid   = (int)$charRow["id"];
                    $cname = (string)$charRow["name"];
                    $calias = ($charRow["alias"] !== '' && $charRow["alias"] !== null) ? (string)$charRow["alias"] : $cname;
                    $cimg  = (string)$charRow["img"];
                    $cst   = (string)$charRow["estado"];

                    switch ($cst) {
                        case "Aún por aparecer":       $simboloEstado = "(&#64)"; break;
                        case "Paradero desconocido":   $simboloEstado = "(&#63;)"; break;
                        case "Cadáver":                $simboloEstado = "(&#8224;)"; break;
                        default:                       $simboloEstado = ""; break;
                    }

                    echo "
                        <a href='" . h(pretty_url($link, 'fact_characters', '/characters', (int)$cid)) . "' title='" . h($cname) . "'>
                            <div class='marcoFotoBio'>
                                <div class='textoDentroFotoBio'>" . h($calias) . " $simboloEstado</div>
                                <div class='dentroFotoBio'>
                                    <img class='fotoBioList' src='" . h($cimg) . "'>
                                </div>
                            </div>
                        </a>
                    ";
                }
                echo "</div>";
                echo "</td></tr>";
            }

            if ($charsWithoutPackResult) mysqli_free_result($charsWithoutPackResult);
            mysqli_stmt_close($charsWithoutPackStmt);
        }
    }

    echo "</table>";
}

mysqli_free_result($result);
mysqli_stmt_close($stmtMain);
?>
