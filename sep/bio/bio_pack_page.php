<?php
// Verificar la conexión a la base de datos
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Obtener y sanitizar los parámetros de la URL
$typePack = isset($_GET['t']) ? (int)$_GET['t'] : 0;  /* Tipo de contenido */
$packId = isset($_GET['b']) ? (int)$_GET['b'] : 0;    /* ID del contenido */

$nameTypePack = '';
$nameTypeForTitle = '';
$query = '';

switch($typePack) {
    case 1:
        $query = "SELECT * FROM nuevo2_manadas WHERE id = ?";
        $nameTypePack = "packs";
        $nameTypeForTitle = "Manada";
        break;
    case 2:
        $query = "SELECT * FROM nuevo2_clanes WHERE id = ?";
        $nameTypePack = "septs";
        $nameTypeForTitle = "Clan";
        break;
}

// Ejecutar la consulta principal si hay una query definida
if ($query != "") {
    $stmt = mysqli_prepare($link, $query);
    mysqli_stmt_bind_param($stmt, 'i', $packId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $NFilas = mysqli_num_rows($result);

    if ($NFilas > 0) {
        while ($ResultQuery = mysqli_fetch_assoc($result)) {
            // Datos de la agrupación
            $namePack = $ResultQuery["name"];
            $infoPack = $ResultQuery["desc"];
            
            $pageSect = $nameTypeForTitle; // PARA CAMBIAR EL TITULO A LA PAGINA
            $pageTitle2 = $namePack;
            
            // TEMA DEL CLAN
            if ($typePack == 1) {
                $clanPack = $ResultQuery["clan"];
                $clanQuery = "SELECT id FROM nuevo2_clanes WHERE name = ? LIMIT 1";
                $clanStmt = mysqli_prepare($link, $clanQuery);
                mysqli_stmt_bind_param($clanStmt, 's', $clanPack);
                mysqli_stmt_execute($clanStmt);
                $clanResult = mysqli_stmt_get_result($clanStmt);

                if (mysqli_num_rows($clanResult) > 0) {
                    $clanRow = mysqli_fetch_assoc($clanResult);
                    $clanDataId = $clanRow["id"];
                    $clanLink = "<a href='index.php?p=seegroup&amp;t=2&amp;b=" . htmlspecialchars($clanDataId) . "'>" . ($clanPack) . "</a>";
                } else {
                    $clanLink = htmlspecialchars($clanPack);
                }
                mysqli_free_result($clanResult);
                mysqli_stmt_close($clanStmt);
            }
            // TEMA DEL CLAN
            // TEMA DEL TOTEM
            $totemPack = $ResultQuery["totem"];
            $totemQuery = "SELECT id, name FROM nuevo_totems WHERE id = ? LIMIT 1";
            $totemStmt = mysqli_prepare($link, $totemQuery);
            mysqli_stmt_bind_param($totemStmt, 'i', $totemPack);
            mysqli_stmt_execute($totemStmt);
            $totemResult = mysqli_stmt_get_result($totemStmt);

            if (mysqli_num_rows($totemResult) > 0) {
                $totemRow = mysqli_fetch_assoc($totemResult);
                $totemDataId = $totemRow["id"];
                $totemDataName = $totemRow["name"];
                $totemLink = "<a href='index.php?p=muestratotem&amp;b=" . htmlspecialchars($totemDataId) . "' target='_blank'>" . htmlspecialchars($totemDataName) . "</a>";
            } else {
                $totemLink = "";
            }
            mysqli_free_result($totemResult);
            mysqli_stmt_close($totemStmt);

            if ($typePack == 1) {
                $packNavLinks = "$clanLink > $namePack";
            } else {
                $packNavLinks = $namePack;
            }

            include("sep/main/main_nav_bar.php"); // Barra Navegación
            echo "<h2>" . htmlspecialchars($namePack) . "</h2>";
            
            echo "<table class='notix'>";
            print("<tr><td colspan='2' class='texti'>");
            if ($totemLink != "") {
                print("<b>T&oacute;tem</b>: $totemLink<br/>");
            }
            print("<b>Descripci&oacute;n</b>:<br/><br/>" . ($infoPack) . "</td></tr>");
            
            if ($typePack == 1) {
                $packsOfSeptQuery = "SELECT id, nombre, alias, img, estado FROM pjs1 WHERE manada = ? ORDER BY nombre";
                $stmtSept = mysqli_prepare($link, $packsOfSeptQuery);
                mysqli_stmt_bind_param($stmtSept, 'i', $packId);
                mysqli_stmt_execute($stmtSept);
                $packsOfSeptResult = mysqli_stmt_get_result($stmtSept);

                if (mysqli_num_rows($packsOfSeptResult) > 0) {
                    print("<tr><td colspan='2' class='texti'><b>Miembros de " . htmlspecialchars($namePack) . "</b>:<br/><br/>");
                    echo "<div style='padding-left:30px;'>";
                    while ($packRow = mysqli_fetch_assoc($packsOfSeptResult)) {
                        $packDataId = $packRow["id"];
                        $packDataName = $packRow["nombre"];
                        $packDataAlias = $packRow["alias"] ?: $packRow["nombre"];
                        $packDataImg = $packRow["img"];
                        $packDataStatus = $packRow["estado"];

                        switch ($packDataStatus) {
                            case "Aún por aparecer":
                                $simboloEstado = "(&#64)";
                                break;
                            case "Paradero desconocido":
                                $simboloEstado = "(&#63;)";
                                break;
                            case "Cadáver":
                                $simboloEstado = "(&#8224;)";
                                break;
                            default:
                                $simboloEstado = "";
                                break;
                        }

                        echo "
                        <a href='index.php?p=muestrabio&amp;b=" . htmlspecialchars($packDataId) . "' title='" . htmlspecialchars($packDataName) . "'>
                            <div class='marcoFotoBio'>
                                <div class='textoDentroFotoBio'>
                                    " . htmlspecialchars($packDataAlias) . " $simboloEstado
                                </div>
                                <div class='dentroFotoBio'>
                                    <img class='fotoBioList' src='" . htmlspecialchars($packDataImg) . "'>
                                </div>
                            </div>
                        </a>
                        ";
                    }
                    echo "</div>";
                    print("</td></tr>");
                }
                mysqli_free_result($packsOfSeptResult);
                mysqli_stmt_close($stmtSept);
            }

            if ($typePack == 2) {
                // Grupos Activos
                $packsOfSeptQuery = "SELECT id, name FROM nuevo2_manadas WHERE clan = ? AND activa = 1 ORDER BY name";
                $packsOfSeptConsulta = mysqli_prepare($link, $packsOfSeptQuery);
                mysqli_stmt_bind_param($packsOfSeptConsulta, 's', $namePack);
                mysqli_stmt_execute($packsOfSeptConsulta);
                $packsOfSeptResult = mysqli_stmt_get_result($packsOfSeptConsulta);
                $packsOfSeptFilas = mysqli_num_rows($packsOfSeptResult);

                // Grupos Inactivos
                $packsOfSeptQuery2 = "SELECT id, name FROM nuevo2_manadas WHERE clan = ? AND activa = 0 ORDER BY name";
                $packsOfSeptConsulta2 = mysqli_prepare($link, $packsOfSeptQuery2);
                mysqli_stmt_bind_param($packsOfSeptConsulta2, 's', $namePack);
                mysqli_stmt_execute($packsOfSeptConsulta2);
                $packsOfSeptResult2 = mysqli_stmt_get_result($packsOfSeptConsulta2);
                $packsOfSeptFilas2 = mysqli_num_rows($packsOfSeptResult2);

                $sumaSeptFilas = $packsOfSeptFilas + $packsOfSeptFilas2;

                if ($sumaSeptFilas > 0) {
                    echo "<tr>";
                    $widthCelda = ($packsOfSeptFilas > 0 && $packsOfSeptFilas2 > 0) ? "50%" : "100%";
                }

                // Columna de Grupos ACTIVOS
                if ($packsOfSeptFilas > 0) {
                    print("<td class='texti' style='width:$widthCelda; vertical-align:top;'><b>En activo</b>:<br/><ul>");
                    while ($packsOfSeptRow = mysqli_fetch_assoc($packsOfSeptResult)) {
                        $packDataId = $packsOfSeptRow["id"];
                        $packDataName = $packsOfSeptRow["name"];
                        echo "<li><a href='index.php?p=seegroup&amp;t=1&amp;b=" . htmlspecialchars($packDataId) . "'>" . htmlspecialchars($packDataName) . "</a></li>";
                    }
                    print("</ul></td>");
                }

                // Columna de Grupos INACTIVOS
                if ($packsOfSeptFilas2 > 0) {
                    print("<td class='texti' style='width:$widthCelda; vertical-align:top;'><b>Grupos antiguos</b>:<br/><ul>");
                    while ($packsOfSeptRow2 = mysqli_fetch_assoc($packsOfSeptResult2)) {
                        $packDataId2 = $packsOfSeptRow2["id"];
                        $packDataName2 = $packsOfSeptRow2["name"];
                        echo "<li><a href='index.php?p=seegroup&amp;t=1&amp;b=" . htmlspecialchars($packDataId2) . "'>" . htmlspecialchars($packDataName2) . "</a></li>";
                    }
                    print("</ul></td>");
                }

                if ($sumaSeptFilas > 0) {
                    echo "</tr>";
                }

                // Mostrar personajes que están en el clan pero no tienen manada
                $charsWithoutPackQuery = "SELECT id, nombre, alias, img, estado FROM pjs1 WHERE clan = ? AND manada = 0 ORDER BY nombre";
                $charsWithoutPackStmt = mysqli_prepare($link, $charsWithoutPackQuery);
                mysqli_stmt_bind_param($charsWithoutPackStmt, 'i', $packId);
                mysqli_stmt_execute($charsWithoutPackStmt);
                $charsWithoutPackResult = mysqli_stmt_get_result($charsWithoutPackStmt);

                if (mysqli_num_rows($charsWithoutPackResult) > 0) {
                    print("<tr><td colspan='2' class='texti'><b>Personajes</b>:<br/><br/>");
                    echo "<div style='padding-left:30px;'>";
                    while ($charRow = mysqli_fetch_assoc($charsWithoutPackResult)) {
                        $charWithoutPackId = $charRow["id"];
                        $charWithoutPackName = $charRow["nombre"];
                        $charWithoutPackAlias = $charRow["alias"] ?: $charRow["nombre"];
                        $charWithoutPackImg = $charRow["img"];
                        $charWithoutPackStatus = $charRow["estado"];

                        switch ($charWithoutPackStatus) {
                            case "Aún por aparecer":
                                $simboloEstado = "(&#64)";
                                break;
                            case "Paradero desconocido":
                                $simboloEstado = "(&#63;)";
                                break;
                            case "Cadáver":
                                $simboloEstado = "(&#8224;)";
                                break;
                            default:
                                $simboloEstado = "";
                                break;
                        }

                        echo "<div style='padding-left:30px;'>";
                        echo "
                            <a href='index.php?p=muestrabio&amp;b=" . htmlspecialchars($charWithoutPackId) . "' title='" . htmlspecialchars($charWithoutPackName) . "'>
                                <div class='marcoFotoBio'>
                                    <div class='textoDentroFotoBio'>
                                        " . htmlspecialchars($charWithoutPackAlias) . " $simboloEstado
                                    </div>
                                    <div class='dentroFotoBio'>
                                        <img class='fotoBioList' src='" . htmlspecialchars($charWithoutPackImg) . "'>
                                    </div>
                                </div>
                            </a>
                        ";
                        echo "</div>";
                    }
                    print("</td></tr>");
                    echo "</div>";
                }
                mysqli_free_result($charsWithoutPackResult);
                mysqli_stmt_close($charsWithoutPackStmt);
            }
            echo "</table>";
        }
    }

    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
}
?>
