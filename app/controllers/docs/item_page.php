<?php

// Aseguramos que el parámetro GET 'b' esté definido y es un valor seguro
$itemPageID = isset($_GET['b']) ? $_GET['b'] : '';
$itemId = (int)$itemPageID;

// Preparamos la consulta para evitar inyecciones SQL
$queryItem = "SELECT * FROM fact_items WHERE id = ? LIMIT 1;";
$stmt = $link->prepare($queryItem);
$stmt->bind_param('s', $itemPageID);
$stmt->execute();
$result = $stmt->get_result();
$rowsQueryItem = $result->num_rows;

// ================================================================== //
if ($rowsQueryItem > 0) { // Si encontramos el Objeto en la BDD...
    $resultQueryItem = $result->fetch_assoc();

    // ================================================================== //
    // DATOS BÁSICOS
    $itemID     = htmlspecialchars($resultQueryItem["id"]);
    $itemName   = htmlspecialchars($resultQueryItem["name"]);
    $itemType   = (int)$resultQueryItem["tipo"];
    $itemSkill  = htmlspecialchars($resultQueryItem["habilidad"]);
    $itemLevel  = (int)$resultQueryItem["nivel"];
    $itemGnosis = (int)$resultQueryItem["gnosis"];
    $itemValue  = htmlspecialchars($resultQueryItem["valor"]);
    $itemBonus  = (int)$resultQueryItem["bonus"];
    $itemDamage = htmlspecialchars(strtolower($resultQueryItem["dano"]));
    $itemMetal  = (int)$resultQueryItem["metal"];
    $itemSTR    = (int)$resultQueryItem["fuerza"];
    $itemDEX    = (int)$resultQueryItem["destreza"];
    $itemImg    = htmlspecialchars($resultQueryItem["img"]);
    $itemInfo   = ($resultQueryItem["descri"]);
    $itemOrig   = (int)$resultQueryItem["origen"];
    
    // ================================================================== //
    // SELECCIONAR ORIGEN
    $itemOriginName = "-"; // Valor predeterminado si no se encuentra
    if ($itemOrig != 0) {
        $queryOrigen = "SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1;";
        $stmtOrigin = $link->prepare($queryOrigen);
        $stmtOrigin->bind_param('i', $itemOrig);
        $stmtOrigin->execute();
        $resultOrigin = $stmtOrigin->get_result();
        if ($resultOrigin->num_rows > 0) {
            $resultQueryOrigen = $resultOrigin->fetch_assoc();
            $itemOriginName = htmlspecialchars($resultQueryOrigen["name"]);
        }
        $stmtOrigin->close();
    }

    // ================================================================== //
    // Preparar Tipo
    switch ($itemType) {
        case 1:
            $nameTypeItem = "Arma";
            $nameTypeBack = "Armamento";
            break;
        case 2:
            $nameTypeItem = "Protector";
            $nameTypeBack = "Protectores";
            break;
        case 3:
            $nameTypeItem = "Objeto mágico";
            $nameTypeBack = "Objetos mágicos";
            break;
        case 5:
            $nameTypeItem = "Amuleto";
            $nameTypeBack = "Amuletos";
            break;
        default:
            $nameTypeItem = "Objeto";
            $nameTypeBack = "Objetos";
            break;
    }

    // ================================================================== //
    // Preparar Daño
    switch ($itemMetal) {
        case 1:
            $metalText = " y de plata";
            break;
        case 2:
            $metalText = " y de oro";
            break;
        default:
            $metalText = "";
            break;          
    }

    switch ($itemSkill) {
        case "Cuerpo a Cuerpo":
        case "Pelea":
        case "Arrojar":
            $damageText = "Fuerza + $itemBonus";
            break;
        default:
            $damageText = "$itemBonus dados";
            break;
    }

    // ================================================================== //
    // Imágenes y Título
    $pageSect = "Objeto"; // Título de la Página ( #$itemID )
    $pageTitle2 = $itemName;
    setMetaFromPage($itemName . " | Objetos | Heaven's Gate", meta_excerpt($itemInfo), null, 'article');
    if (empty($itemImg)) {
        $itemImg = "img/inv/no-photo.gif";
    }

    // ================================================================== //
    /* MODERNO NUEVO */
    include("app/partials/main_nav_bar.php"); // Barra Navegación
    echo "<h2>$itemName</h2>"; // Encabezado de página

    ob_start();

    // ================================================================== //
    echo "<fieldset class='renglonPaginaDon'>"; // Cuerpo principal de la Ficha del Objeto

    // ================================================================== //
    echo "<div class='itemSquarePhoto' style='padding-left:4px;'>"; // Colocamos la Fotografia del Objeto
    echo "<img class='photobio' style='width:100px;height:100px;' src='$itemImg' alt='$itemName'/>";
    echo "</div>"; // Dejamos la Fotografía ya colocada

    // ================================================================== //
    echo "<div class='bioSquareData'>"; //

    // ================================================================== //
    // Tipo de Objeto
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Tipo:</div>";
    echo "<div class='bioDataText'>$nameTypeItem</div>"; 
    echo "</div>";

    // ================================================================== //
    // Habilidad del Objeto
    if (!empty($itemSkill)) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Habilidad:</div>";
        echo "<div class='bioDataText'>$itemSkill</div>"; 
        echo "</div>";
    }

    // ================================================================== //
    // Daño del Objeto
    if (!empty($itemDamage)) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Daño:</div>";
        echo "<div class='bioDataText'>$damageText, $itemDamage$metalText</div>"; 
        echo "</div>";
    }

    // ================================================================== //
    // Bonus de Defensa del Objeto
    if ($itemBonus != 0 && empty($itemSkill)) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Bonificación:</div>";
        echo "<div class='bioDataText'>+$itemBonus de absorción</div>"; 
        echo "</div>";
    }

    // ================================================================== //
    // Comprobación si es Fetiche 
    if ($itemLevel != 0) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Nivel:</div>";
        echo "<div class='bioDataText'><img class='bioAttCircle' src='img/ui/gems/pwr/gem-pwr-0$itemLevel.png'/></div>"; 
        echo "</div>";
        if ($itemGnosis != 0) {
            echo "<div class='bioRenglonData'>";
            echo "<div class='bioDataName'>Gnosis:</div>";
            echo "<div class='bioDataText'><img class='bioAttCircle' src='img/ui/gems/pwr/gem-pwr-0$itemGnosis.png'/></div>"; 
            echo "</div>";
        }
    }

    // ================================================================== //
    // Gnosis del Objeto (Solo amuletos)
    if ($itemGnosis != 0 && $itemLevel == 0 && $itemType == 5) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Gnosis:</div>";
        echo "<div class='bioDataText'><img class='bioAttCircle' src='img/ui/gems/pwr/gem-pwr-0$itemGnosis.png'/></div>"; 
        echo "</div>";
    }

    // ================================================================== //
    // Requiere Fuerza el Objeto?
    if ($itemSTR != 0) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Requiere:</div>";
        echo "<div class='bioDataText'>Fuerza $itemSTR mínimo</div>"; 
        echo "</div>";
    }

    // ================================================================== //
    // Penalizador de Destreza
    if ($itemDEX != 0) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Penalización:</div>";
        echo "<div class='bioDataText'>Destreza -$itemDEX</div>"; 
        echo "</div>";
    }

    // ================================================================== //
    // Orígenes del Objeto 
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Origen:</div>";
    echo "<div class='bioDataText'>$itemOriginName</div>";
    echo "</div>";

    // ================================================================== //
    echo "</div>";

    // ================================================================== //
    // Descripción del Objeto
    if (!empty($itemInfo)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Descripci&oacute;n:</b><p>$itemInfo</p>";
        echo "</div>";
    }

    echo "</fieldset>";
    $infoHtml = ob_get_clean();

    // ================================================================== //
    // Portadores
    $itemOwners = [];
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
    $cronicaNotInSQL = ($excludeChronicles !== '') ? " AND p.cronica NOT IN ($excludeChronicles) " : "";
    $queryOwners = "
        SELECT
            p.id,
            p.nombre,
            p.alias,
            p.img,
            p.estado
        FROM bridge_characters_items b
        JOIN fact_characters p ON p.id = b.personaje_id
        WHERE b.objeto_id = ? $cronicaNotInSQL
        ORDER BY p.nombre
    ";
    if ($stOwners = $link->prepare($queryOwners)) {
        $stOwners->bind_param('i', $itemPageID);
        $stOwners->execute();
        $rsOwners = $stOwners->get_result();
        while ($r = $rsOwners->fetch_assoc()) { $itemOwners[] = $r; }
        $stOwners->close();
    }
    $hasOwners = count($itemOwners) > 0;

    if ($hasOwners) {
        echo "<style>
            .hg-tabs{ display:flex; gap:8px; flex-wrap:wrap; margin:6px 0 12px; }
            .hg-tab-panel{ display:none; }
            .hg-tab-panel.active{ display:block; }
            .hgTabBtn{ border:1px solid #003399; }
            .hgTabBtn.active{ background:#001199; color:#01b3fa; border-color:#003399; }
            .contenidoAfiliacion{ display:flex; flex-wrap:wrap; gap:6px; padding:8px 0 12px 0; }
        </style>";

        echo "<div class='hg-tabs'>";
        echo "<button class='boton2 hgTabBtn' data-tab='info'>Informaci&oacute;n</button>";
        echo "<button class='boton2 hgTabBtn' data-tab='owners'>Portadores</button>";
        echo "</div>";

        echo "<section class='hg-tab-panel' data-tab='info'>$infoHtml</section>";

        echo "<section class='hg-tab-panel' data-tab='owners'>";
        echo "<div class='grupoBioClan'><div class='contenidoAfiliacion'>";
        $mapEstado = [
            "A?n por aparecer"     => "(&#64;)",
            "Paradero desconocido" => "(&#63;)",
            "Cad?ver"              => "(&#8224;)"
        ];
        foreach ($itemOwners as $o) {
            $oid = (int)($o['id'] ?? 0);
            $name = htmlspecialchars($o['nombre'] ?? '');
            $alias = htmlspecialchars($o['alias'] ?? '');
            $img = htmlspecialchars($o['img'] ?? '');
            $estado = (string)($o['estado'] ?? '');
            $label = $alias !== '' ? $alias : $name;
            $simboloEstado = $mapEstado[$estado] ?? "";
            $href = pretty_url($link, 'fact_characters', '/characters', $oid);
            echo "<a href='" . htmlspecialchars($href) . "' target='_blank' title='{$name}'>";
                echo "<div class='marcoFotoBio'>";
                    echo "<div class='textoDentroFotoBio'>{$label} {$simboloEstado}</div>";
                    if ($img !== "") {
                        echo "<div class='dentroFotoBio'><img class='fotoBioList' src='{$img}' alt='{$name}'></div>";
                    } else {
                        echo "<div class='dentroFotoBio'><span>Sin imagen</span></div>";
                    }
                echo "</div>";
            echo "</a>";
        }
        echo "</div></div>";
        echo "<p align='right'>Personajes: " . count($itemOwners) . "</p>";
        echo "</section>";

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
    /* =========== */
} // Fin comprobación

$stmt->close();

?>
