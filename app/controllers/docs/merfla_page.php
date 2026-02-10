<?php
// Obtener parámetros 'b' y 'r' de manera segura
$mafPageID = isset($_GET['b']) ? $_GET['b'] : '';  // ID del Mérito Defecto
$returnID = isset($_GET['r']) ? $_GET['r'] : '';  // ID del Regreso

$unknownOrigin = "-";

// Preparar la consulta para evitar inyecciones SQL
$queryMaf = "SELECT * FROM dim_merits_flaws WHERE id = ? LIMIT 1";

$stmtMaf = $link->prepare($queryMaf);
$stmtMaf->bind_param('s', $mafPageID);
$stmtMaf->execute();
$resultMaf = $stmtMaf->get_result();
$rowsQueryMaf = $resultMaf->num_rows;

// Comprobamos si hay resultados
if ($rowsQueryMaf > 0) {
    $resultQueryMaf = $resultMaf->fetch_assoc();

    // Datos básicos
    $mafId = htmlspecialchars($resultQueryMaf["id"]);
    $mafName = htmlspecialchars($resultQueryMaf["name"]);
    $mafType = htmlspecialchars($resultQueryMaf["tipo"]);
    $mafAfil = htmlspecialchars($resultQueryMaf["afiliacion"]);
    $mafCoste = htmlspecialchars($resultQueryMaf["coste"]);
    $mafDesc = $resultQueryMaf["descripcion"];
    $mafSystem = htmlspecialchars($resultQueryMaf["sistema"]);
    $mafOrigin = htmlspecialchars($resultQueryMaf["origen"]);

	$meritsAndFlawsQuery = "SELECT DISTINCT afiliacion FROM dim_merits_flaws ORDER BY afiliacion ASC";

    // Seleccionar origen
    $mafOriginName = $unknownOrigin; // Valor predeterminado si no hay origen
    if ($mafOrigin != 0) {
        $queryOrigen = "SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1";
        $stmtOrigen = $link->prepare($queryOrigen);
        $stmtOrigen->bind_param('s', $mafOrigin);
        $stmtOrigen->execute();
        $resultOrigen = $stmtOrigen->get_result();
        if ($resultOrigen->num_rows > 0) {
            $resultQueryOrigen = $resultOrigen->fetch_assoc();
            $mafOriginName = htmlspecialchars($resultQueryOrigen["name"]);
        }
        $stmtOrigen->close();
    }

    // Datos para regresar
    switch ($mafType) {
        case "Méritos":
            $returnType = 1;
            $mafNameType = "Mérito";
            break;
        case "Defectos":
            $returnType = 2;
            $mafNameType = "Defecto";
            break;
        default:
            $returnType = 1;
            $mafNameType = "Tipo desconocido";
            break;
    }

    // Crear un array para regresar
    $returnArray = array();
    $returnQuery = $meritsAndFlawsQuery; //"";
    $stmtReturn = $link->prepare($returnQuery);
    $stmtReturn->execute();
    $resultReturn = $stmtReturn->get_result();
	$i = 0;
    while ($returnQueryResult = $resultReturn->fetch_assoc()) {
		$returnArray[$returnQueryResult["afiliacion"]] = $i + 1;
		$i++;
    }
    $stmtReturn->close();

	$typeReturnId = $returnArray[$mafAfil];

    // =========================
    // Personajes con este Mérito/Defecto (respeta exclusiones de crónica)
    // =========================
    if (!function_exists('sanitize_int_csv')) {
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
    }
    $excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
    $cronicaNotInSQL = ($excludeChronicles !== '') ? " AND c.cronica NOT IN ($excludeChronicles) " : "";
    $mafOwners = [];
    if ($stOwners = $link->prepare("SELECT DISTINCT c.id, c.nombre, c.alias, c.img, c.estado FROM bridge_characters_merits_flaws b JOIN fact_characters c ON c.id = b.personaje_id WHERE b.mer_y_def_id = ? $cronicaNotInSQL ORDER BY c.nombre")) {
        $stOwners->bind_param('i', $mafPageID);
        $stOwners->execute();
        $rsOwners = $stOwners->get_result();
        while ($r = $rsOwners->fetch_assoc()) { $mafOwners[] = $r; }
        $stOwners->close();
    }
    $hasOwners = count($mafOwners) > 0;
    $useTabs = $hasOwners;

    // Título e Imágenes
    $costMeritFlaw = $mafCoste; // Usamos $mafCoste para el coste
    $costeEsFijo = is_numeric($costMeritFlaw);
    $iconoCoste = "img/ui/icons/range-star.gif";
    $pageSect = $mafNameType; // PARA CAMBIAR EL TITULO A LA PAGINA
    $pageTitle2 = $mafName;
    setMetaFromPage($mafName . " | Méritos y Defectos | Heaven's Gate", meta_excerpt($mafDesc), null, 'article');
    $pointQty = ($costMeritFlaw >= 2) ? "puntos" : "punto"; // Numeración

    // Incluir archivos para navegación y contenido
    include("app/partials/main_nav_bar.php"); // Barra Navegación
    echo "<h2>$mafName</h2>"; // Encabezado de página

    ob_start();

    echo "<fieldset class='renglonPaginaDon'>"; // Cuerpo principal de la Ficha del Mérito
    
    $itemImg = "img/inv/no-photo.gif";

    echo "<div class='itemSquarePhoto' style='padding-left:4px;'>"; // Colocamos la Fotografia del Don
    echo "<img class='photobio' style='width:100px;height:100px;' src='$itemImg' alt='$mafName'/>";
    echo "</div>"; // Dejamos la Fotografía ya colocada

    echo "<div class='bioSquareData'>";

    // Coste del Mérito
    echo "<div class='bioRenglonData'>";
    if ($mafCoste > 0) { 
        echo "<div class='bioDataName'>Coste:</div>"; 
        echo "<div class='bioDataText'>";
        for ($nrange = 0; $nrange < $mafCoste; $nrange++) {
            echo "<img src='$iconoCoste' alt='Coste $mafCoste'>";
        }    
        echo "</div>";
    }
    echo "</div>";

    // Tipo del Mérito
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Tipo:</div>";
    echo "<div class='bioDataText'>$mafNameType</div>";
    echo "</div>";

    // Sistema del Mérito
    if ($mafSystem != "") {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Sistema:</div>";
        echo "<div class='bioDataText'>$mafSystem</div>"; 
        echo "</div>";
    }

    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Categoría:</div>";
    echo "<div class='bioDataText'>$mafAfil</div>"; 
    echo "</div>";

    // Orígenes del Mérito
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Origen:</div>";
    echo "<div class='bioDataText'>$mafOriginName</div>"; 
    echo "</div>";

    echo "</div>";

    // Descripción del Mérito
    if ($mafDesc != "") {
        echo "<div class='renglonDonData'>";
        echo "<b>Descripci&oacute;n:</b><p>$mafDesc</p>";
        echo "</div>";
    }
    echo "</fieldset>";
    $infoHtml = ob_get_clean();

    if ($useTabs) {
        echo "<style>
            .hg-tabs{ display:flex; gap:8px; flex-wrap:wrap; margin:6px 0 12px; }
            .hg-tab-panel{ display:none; }
            .hg-tab-panel.active{ display:block; }
            .hgTabBtn{ border:1px solid #003399; }
            .hgTabBtn.active{ background:#001199; color:#01b3fa; border-color:#003399; }
            .contenidoAfiliacion{ display:flex; flex-wrap:wrap; gap:6px; padding:8px 0 12px 0; }
        </style>";

        echo "<div class='hg-tabs'>";
        echo "<button class='boton2 hgTabBtn' data-tab='info'>Información</button>";
        if ($hasOwners) echo "<button class='boton2 hgTabBtn' data-tab='owners'>Portadores</button>";
        echo "</div>";

        echo "<section class='hg-tab-panel' data-tab='info'>$infoHtml</section>";

        if ($hasOwners) {
            echo "<section class='hg-tab-panel' data-tab='owners'>";
            echo "<div class='grupoBioClan'><div class='contenidoAfiliacion'>";
            foreach ($mafOwners as $o) {
                $oid = (int)($o['id'] ?? 0);
                $name = (string)($o['nombre'] ?? '');
                $alias = (string)($o['alias'] ?? '');
                $img = (string)($o['img'] ?? '');
                $estado = (string)($o['estado'] ?? '');
                $label = $alias !== '' ? $alias : $name;
                $mapEstado = [
                    "Aún por aparecer"     => "(&#64;)",
                    "Paradero desconocido" => "(&#63;)",
                    "Cadáver"              => "(&#8224;)"
                ];
                $simboloEstado = $mapEstado[$estado] ?? "";
                $href = pretty_url($link, 'fact_characters', '/characters', $oid);
                echo "<a href='" . htmlspecialchars($href) . "' target='_blank' title='" . htmlspecialchars($name) . "'>";
                    echo "<div class='marcoFotoBio'>";
                        echo "<div class='textoDentroFotoBio'>{$label} {$simboloEstado}</div>";
                        if ($img !== "") {
                            echo "<div class='dentroFotoBio'><img class='fotoBioList' src='" . htmlspecialchars($img) . "' alt='" . htmlspecialchars($name) . "'></div>";
                        } else {
                            echo "<div class='dentroFotoBio'><span>Sin imagen</span></div>";
                        }
                    echo "</div>";
                echo "</a>";
            }
            echo "</div></div>";
            echo "<p align='right'>Personajes: " . count($mafOwners) . "</p>";
            echo "</section>";
        }

        echo "<script>
            document.addEventListener('DOMContentLoaded', () => {
                const tabs = Array.from(document.querySelectorAll('.hgTabBtn'));
                const panels = Array.from(document.querySelectorAll('.hg-tab-panel'));
                function activate(key){
                    panels.forEach(p => p.classList.toggle('active', p.dataset.tab === key));
                    tabs.forEach(b => b.classList.toggle('active', b.dataset.tab === key));
                }
                if (tabs.length) activate(tabs[0].dataset.tab);
                tabs.forEach(b => b.addEventListener('click', () => activate(b.dataset.tab)));
            });
        </script>";
    } else {
        echo $infoHtml;
    }
} // Fin comprobación

// Cerramos la sentencia preparada para la consulta principal
$stmtMaf->close();
?>
