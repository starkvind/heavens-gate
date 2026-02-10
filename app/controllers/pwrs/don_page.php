<?php
// Verificar si se recibe el parámetro 'b' y sanitizarlo
$donPageID = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta para obtener información del Don
$queryDon = "SELECT * FROM fact_gifts WHERE id = ? LIMIT 1;";
$stmt = $link->prepare($queryDon);
$stmt->bind_param('s', $donPageID);
$stmt->execute();
$result = $stmt->get_result();
$rowsQueryDon = $result->num_rows;

if ($rowsQueryDon > 0) { // Si encontramos el Don en la base de datos
    $resultQueryDon = $result->fetch_assoc();

    // DATOS BÁSICOS
    $donId     = htmlspecialchars($resultQueryDon["id"]);
    $donName   = htmlspecialchars($resultQueryDon["nombre"]);
    $donType   = htmlspecialchars($resultQueryDon["tipo"]);
    $donGroup  = htmlspecialchars($resultQueryDon["grupo"]);
    $donRank   = htmlspecialchars($resultQueryDon["rango"]);
    $donAttr   = htmlspecialchars($resultQueryDon["atributo"]);
    $donSkill  = htmlspecialchars($resultQueryDon["habilidad"]);
    $donDesc   = ($resultQueryDon["descripcion"]);
    $donSystem = ($resultQueryDon["sistema"]);
    $donBreed  = htmlspecialchars($resultQueryDon["ferasistema"]);
    $donOrigin = htmlspecialchars($resultQueryDon["origen"]);

    // Obtener el nombre del origen del Don
    $donOriginName = "-"; // Valor por defecto

    if (!empty($donOrigin)) {
        $queryOrigen = "SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1;";
        $stmt = $link->prepare($queryOrigen);
        $stmt->bind_param('s', $donOrigin);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($rowOrigen = $result->fetch_assoc()) {
            $donOriginName = htmlspecialchars($rowOrigen["name"]);
        }
    }

    // Obtener el tipo de Don
    $nombreTipo = "Desconocido"; // Valor por defecto
    $queryTipo = "SELECT name FROM dim_gift_types WHERE id = ? LIMIT 1;";
    $stmt = $link->prepare($queryTipo);
    $stmt->bind_param('s', $donType);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($rowTipo = $result->fetch_assoc()) {
        $nombreTipo = htmlspecialchars($rowTipo["name"]);
    }

    // Guardar en sesión para los breadcrumbs
    $_SESSION['punk2'] = $nombreTipo;

    // =========================
    // Personajes con este Don (respeta exclusiones de crónica)
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
    $donOwners = [];
    if ($stOwners = $link->prepare("SELECT DISTINCT c.id, c.nombre, c.alias, c.img, c.estado FROM bridge_characters_powers b JOIN fact_characters c ON c.id = b.personaje_id WHERE b.tipo_poder='dones' AND b.poder_id = ? $cronicaNotInSQL ORDER BY c.nombre")) {
        $stOwners->bind_param('i', $donPageID);
        $stOwners->execute();
        $rsOwners = $stOwners->get_result();
        while ($r = $rsOwners->fetch_assoc()) { $donOwners[] = $r; }
        $stOwners->close();
    }
    $hasOwners = count($donOwners) > 0;
    $useTabs = $hasOwners;
	
	$pageSect = "Dones"; // PARA CAMBIAR EL TITULO A LA PAGINA
	$pageTitle2 = $donName; // PARA CAMBIAR EL TITULO A LA PAGINA
	setMetaFromPage($donName . " | Dones | Heaven's Gate", meta_excerpt($donDesc), null, 'article');

    // Incluir barra de navegación
    include("app/partials/main_nav_bar.php");

    // Título de la página
    echo "<h2>$donName</h2>";

    ob_start();

    // Imagen del Don
    $itemImg = "img/inv/no-photo.gif"; // Valor por defecto si no hay imagen

    echo "<fieldset class='renglonPaginaDon'>";

    echo "<div class='itemSquarePhoto' style='padding-left:4px;'>";
    echo "<img class='photobio' style='width:100px;height:100px;' src='$itemImg' alt='$donName'/>";
    echo "</div>";

    // Datos generales del Don
    echo "<div class='bioSquareData'>";

    // Rango del Don
    if ($donRank > 0) {
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Rango:</div>";
        echo "<div class='bioDataText'><img class='bioAttCircle' src='img/ui/gems/pwr/gem-pwr-0$donRank.png'/></div>";
        echo "</div>";
    }

    // Grupo del Don
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Grupo:</div>";
    echo "<div class='bioDataText'>$donGroup</div>";
    echo "</div>";

    // Tirada del Don
    if (!empty($donAttr) || !empty($donSkill)) {
        $tiradaDon2 = !empty($donSkill) ? "$donAttr + $donSkill" : $donAttr;
        echo "<div class='bioRenglonData'>";
        echo "<div class='bioDataName'>Tirada:</div>";
        echo "<div class='bioDataText'>$tiradaDon2</div>";
        echo "</div>";
    }

    // Orígenes del Don
    echo "<div class='bioRenglonData'>";
    echo "<div class='bioDataName'>Origen:</div>";
    echo "<div class='bioDataText'>$donOriginName</div>";
    echo "</div>";

    echo "</div>";

    // Descripción del Don
    if (!empty($donDesc)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Descripción:</b><p>$donDesc</p>";
        echo "</div>";
    }

    // Sistema del Don
    if (!empty($donSystem)) {
        echo "<div class='renglonDonData'>";
        echo "<b>Sistema:</b><p>$donSystem</p>";
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
            foreach ($donOwners as $o) {
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
            echo "<p align='right'>Personajes: " . count($donOwners) . "</p>";
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

} else {
    echo "<p>Error: Don no encontrado.</p>";
}
?>
