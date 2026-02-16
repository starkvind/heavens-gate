<?php
// Obtener y sanitizar el parámetro 'b'
$ritePageID = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta segura para obtener los datos del ritual
$queryRite = "
    SELECT r.*, s.name AS system_name, r.kind AS tipo, r.level AS nivel, r.race AS raza, r.system_name AS sistema
    FROM fact_rites r
    LEFT JOIN dim_systems s ON r.system_id = s.id
    WHERE r.id = ? LIMIT 1
";
$stmt = $link->prepare($queryRite);
$stmt->bind_param('s', $ritePageID);
$stmt->execute();
$result = $stmt->get_result();
$rowsQueryRite = $result->num_rows;

if ($rowsQueryRite > 0) { // Si encontramos el ritual en la base de datos
    $resultQueryRite = $result->fetch_assoc();

    // DATOS BÁSICOS
    $riteId     = htmlspecialchars($resultQueryRite["id"]);
    $riteName   = htmlspecialchars($resultQueryRite["name"]);
    $riteType   = htmlspecialchars($resultQueryRite["tipo"]);
    $riteLevel  = htmlspecialchars($resultQueryRite["nivel"]);
    $riteBreed  = htmlspecialchars($resultQueryRite["raza"]);
    $riteDesc   = $resultQueryRite["description"] ?? $resultQueryRite["desc"] ?? ''; // NO usar htmlspecialchars() para conservar el HTML
    $riteSystemRules = $resultQueryRite["syst"];
    $riteSystemName  = htmlspecialchars($resultQueryRite["system_name"] ?? "");
    $riteSistemaLegacy = trim((string)($resultQueryRite["sistema"] ?? ""));
    if (trim((string)$riteSystemRules) === '' && $riteSistemaLegacy !== '') { $riteSystemRules = $riteSistemaLegacy; }
    $riteOrigin = htmlspecialchars($resultQueryRite["bibliography_id"]);
    $riteImgRaw = trim((string)($resultQueryRite["img"] ?? ""));

    // Obtener el nombre del origen del ritual
    $riteOriginName = "-"; // Valor por defecto

    if (!empty($riteOrigin)) {
        $queryOrigen = "SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1";
        $stmt = $link->prepare($queryOrigen);
        $stmt->bind_param('s', $riteOrigin);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($rowOrigen = $result->fetch_assoc()) {
            $riteOriginName = htmlspecialchars($rowOrigen["name"]);
        }
    }

    // Obtener el tipo de ritual
    $nombreTipo = "Desconocido"; // Valor por defecto
    $queryTipo = "SELECT name FROM dim_rite_types WHERE id = ? LIMIT 1";
    $stmt = $link->prepare($queryTipo);
    $stmt->bind_param('s', $riteType);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($rowTipo = $result->fetch_assoc()) {
        $nombreTipo = htmlspecialchars($rowTipo["name"]);
    }

    // Guardar en sesión para breadcrumbs
    $_SESSION['punk2'] = $nombreTipo;

    // =========================
    // Personajes con este Ritual (respeta exclusiones de crónica)
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
    $cronicaNotInSQL = ($excludeChronicles !== '') ? " AND c.chronicle_id NOT IN ($excludeChronicles) " : "";
    $riteOwners = [];
    if ($stOwners = $link->prepare("SELECT DISTINCT c.id, c.name AS nombre, c.alias, c.img, c.estado FROM bridge_characters_powers b JOIN fact_characters c ON c.id = b.character_id WHERE b.power_kind='rituales' AND b.power_id = ? $cronicaNotInSQL ORDER BY c.name")) {
        $stOwners->bind_param('i', $ritePageID);
        $stOwners->execute();
        $rsOwners = $stOwners->get_result();
        while ($r = $rsOwners->fetch_assoc()) { $riteOwners[] = $r; }
        $stOwners->close();
    }
    $hasOwners = count($riteOwners) > 0;
    $useTabs = $hasOwners;
	
	$pageSect = "Rituales"; // PARA CAMBIAR EL TITULO A LA PAGINA
	$pageTitle2 = $riteName; // PARA CAMBIAR EL TITULO A LA PAGINA
	setMetaFromPage($riteName . " | Rituales | Heaven's Gate", meta_excerpt($riteDesc), null, 'article');

    // Incluir barra de navegación
    include("app/partials/main_nav_bar.php");

    // Título de la página
    ob_start();

    // Imagen del Ritual
    $itemImg = "img/inv/no-photo.gif"; // Valor por defecto si no hay imagen
    if ($riteImgRaw !== "") {
        if (strpos($riteImgRaw, "/") !== false) {
            $itemImg = $riteImgRaw;
        } else {
            $itemImg = "img/rites/" . $riteImgRaw;
        }
    }

    echo "<div class='power-card power-card--rite'>";
    echo "  <div class='power-card__banner'>";
    echo "    <span class='power-card__title'>$riteName</span>";
    echo "  </div>";

    echo "  <div class='power-card__body'>";
    echo "    <div class='power-card__media'>";
    echo "      <img class='power-card__img' style='border:1px solid #001a55; box-shadow: 0 0 0 2px #001a55, 0 0 14px rgba(0,0,0,0.5)' src='$itemImg' alt='$riteName'/>";
    echo "    </div>";

    echo "    <div class='power-card__stats'>";
    if ($riteLevel > 0) {
        echo "<div class='power-stat'><div class='power-stat__label'>Nivel</div><div class='power-stat__value'><img class='bioAttCircle' src='img/ui/gems/pwr/gem-pwr-0$riteLevel.png'/></div></div>";
    }
    if ($nombreTipo !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Tipo</div><div class='power-stat__value'>$nombreTipo</div></div>";
    }
    if (!empty($riteBreed)) {
        echo "<div class='power-stat'><div class='power-stat__label'>Raza</div><div class='power-stat__value'>$riteBreed</div></div>";
    }
    if ($riteOriginName !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>$riteOriginName</div></div>";
    }
    echo "    </div>"; // stats
    echo "  </div>"; // body

    if (!empty($riteDesc)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Descripci&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>$riteDesc</div>";
        echo "  </div>";
    }

    if ($riteSystemName !== "" || !empty($riteSystemRules)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Sistema</div>";
        if ($riteSystemName !== "") {
            echo "    <div class='power-card__desc-body'><strong>$riteSystemName</strong></div>";
        }
        if (!empty($riteSystemRules)) {
            echo "    <div class='power-card__desc-body'>$riteSystemRules</div>";
        }
        echo "  </div>";
    }

    echo "</div>"; // power-card

    $infoHtml = ob_get_clean();


    if ($useTabs) {
        echo "<style>
            .hg-tabs{ display:flex; gap:8px; flex-wrap:wrap; margin:6px 0 12px; justify-content:flex-end; }
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
            foreach ($riteOwners as $o) {
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
            echo "<p align='right'>Personajes: " . count($riteOwners) . "</p>";
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
    echo "<p>Error: Ritual no encontrado.</p>";
}
?>
