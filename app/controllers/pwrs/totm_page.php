<?php
// Obtener y sanitizar el parámetro 'b'
$totemPageID = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta segura para obtener los datos del tótem
$queryTotem = "SELECT * FROM dim_totems WHERE id = ? LIMIT 1";
$stmt = $link->prepare($queryTotem);
$stmt->bind_param('s', $totemPageID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) { // Si encontramos el tótem en la base de datos
    $resultQueryTotem = $result->fetch_assoc();

    // DATOS BÁSICOS
    $totemId    = htmlspecialchars($resultQueryTotem["id"]);
    $totemName  = htmlspecialchars($resultQueryTotem["name"]);
    $totemNameRaw = (string)($resultQueryTotem["name"] ?? "");
    $totemPrettyRaw = (string)($resultQueryTotem["pretty_id"] ?? "");
    $totemType  = htmlspecialchars($resultQueryTotem["tipo"]);
    $totemCost  = htmlspecialchars($resultQueryTotem["coste"]);
    $totemDesc  = $resultQueryTotem["desc"]; // NO usar htmlspecialchars() para mantener el formato HTML
    $totemAttr  = $resultQueryTotem["rasgos"];
    $totemBan   = $resultQueryTotem["prohib"];
    $totemOrigin = htmlspecialchars($resultQueryTotem["bibliography_id"]);
    $totemImgRaw = trim((string)($resultQueryTotem["img"] ?? ""));

    // Obtener el nombre del origen del tótem
    $totemOriginName = "-"; // Valor por defecto

    if (!empty($totemOrigin)) {
        $queryOrigen = "SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1";
        $stmt = $link->prepare($queryOrigen);
        $stmt->bind_param('s', $totemOrigin);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($rowOrigen = $result->fetch_assoc()) {
            $totemOriginName = htmlspecialchars($rowOrigen["name"]);
        }
    }

    // Obtener el tipo de tótem
    $nombreTipo = "Desconocido"; // Valor por defecto
    $queryTipo = "SELECT name FROM dim_totem_types WHERE id = ? LIMIT 1";
    $stmt = $link->prepare($queryTipo);
    $stmt->bind_param('s', $totemType);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($rowTipo = $result->fetch_assoc()) {
        $nombreTipo = htmlspecialchars($rowTipo["name"]);
    }

    // Guardar en sesión para breadcrumbs
    $_SESSION['punk2'] = $nombreTipo;

    // =========================
    // Portadores / grupos / organizaciones con este Tótem (respeta exclusiones de crónica)
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
    $cronicaNotInSQL = ($excludeChronicles !== '') ? " AND p.cronica NOT IN ($excludeChronicles) " : "";
    $totemCharOwners = [];
    if ($stOwners = $link->prepare("SELECT p.id, p.nombre, p.alias, p.img, p.estado FROM fact_characters p WHERE (p.totem_id = ? OR p.totem = ? OR p.totem = ?) $cronicaNotInSQL ORDER BY p.nombre")) {
        $stOwners->bind_param('iss', $totemPageID, $totemNameRaw, $totemPrettyRaw);
        $stOwners->execute();
        $rsOwners = $stOwners->get_result();
        while ($r = $rsOwners->fetch_assoc()) { $totemCharOwners[] = $r; }
        $stOwners->close();
    }

    $totemGroups = [];
    if ($stGroups = $link->prepare("SELECT id, name FROM dim_groups WHERE totem = ? ORDER BY name")) {
        $stGroups->bind_param('i', $totemPageID);
        $stGroups->execute();
        $rsGroups = $stGroups->get_result();
        while ($r = $rsGroups->fetch_assoc()) { $totemGroups[] = $r; }
        $stGroups->close();
    }

    $totemOrgs = [];
    if ($stOrgs = $link->prepare("SELECT id, name FROM dim_organizations WHERE totem = ? ORDER BY name")) {
        $stOrgs->bind_param('i', $totemPageID);
        $stOrgs->execute();
        $rsOrgs = $stOrgs->get_result();
        while ($r = $rsOrgs->fetch_assoc()) { $totemOrgs[] = $r; }
        $stOrgs->close();
    }

    $hasCharOwners = count($totemCharOwners) > 0;
    $hasGroupOwners = count($totemGroups) > 0;
    $hasOrgOwners = count($totemOrgs) > 0;
    $useTabs = ($hasCharOwners || $hasGroupOwners || $hasOrgOwners);
	
	$pageSect = "Tótems"; // PARA CAMBIAR EL TITULO A LA PAGINA
	$pageTitle2 = $totemName; // PARA CAMBIAR EL TITULO A LA PAGINA
	setMetaFromPage($totemName . " | Tótems | Heaven's Gate", meta_excerpt($totemDesc), null, 'article');

    // Incluir barra de navegación
    include("app/partials/main_nav_bar.php");

    // Título de la página
    ob_start();

    // Imagen del Totem
    $itemImg = "img/inv/no-photo.gif"; // Valor por defecto si no hay imagen
    if ($totemImgRaw !== "") {
        if (strpos($totemImgRaw, "/") !== false) {
            $itemImg = $totemImgRaw;
        } else {
            $itemImg = "img/totems/" . $totemImgRaw;
        }
    }

    echo "<div class='power-card power-card--totem'>";
    echo "  <div class='power-card__banner'>";
    echo "    <span class='power-card__title'>$totemName</span>";
    echo "  </div>";

    echo "  <div class='power-card__body'>";
    echo "    <div class='power-card__media'>";
    echo "      <img class='power-card__img' style='border:1px solid #001a55; box-shadow: 0 0 0 2px #001a55, 0 0 14px rgba(0,0,0,0.5)' src='$itemImg' alt='$totemName'/>";
    echo "    </div>";

    echo "    <div class='power-card__stats'>";
    if ($totemCost > 0) {
        echo "<div class='power-stat'><div class='power-stat__label'>Coste</div><div class='power-stat__value'><img class='bioAttCircle' src='img/ui/gems/pwr/gem-pwr-0$totemCost.png'/></div></div>";
    }
    if ($nombreTipo !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Tipo</div><div class='power-stat__value'>$nombreTipo</div></div>";
    }
    if ($totemOriginName !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>$totemOriginName</div></div>";
    }
    echo "    </div>"; // stats
    echo "  </div>"; // body

    if (!empty($totemDesc)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Descripci&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>$totemDesc</div>";
        echo "  </div>";
    }

    if (!empty($totemAttr)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Rasgos</div>";
        echo "    <div class='power-card__desc-body'>$totemAttr</div>";
        echo "  </div>";
    }

    if (!empty($totemBan)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Prohibici&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>$totemBan</div>";
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
        if ($hasCharOwners) echo "<button class='boton2 hgTabBtn' data-tab='owners'>Portadores</button>";
        if ($hasGroupOwners) echo "<button class='boton2 hgTabBtn' data-tab='groups'>Grupos</button>";
        if ($hasOrgOwners) echo "<button class='boton2 hgTabBtn' data-tab='orgs'>Organizaciones</button>";
        echo "</div>";

        echo "<section class='hg-tab-panel' data-tab='info'>$infoHtml</section>";

        if ($hasCharOwners) {
            echo "<section class='hg-tab-panel' data-tab='owners'>";
            echo "<div class='grupoBioClan'><div class='contenidoAfiliacion'>";
            foreach ($totemCharOwners as $o) {
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
            echo "<p align='right'>Personajes: " . count($totemCharOwners) . "</p>";
            echo "</section>";
        }

        if ($hasGroupOwners) {
            echo "<section class='hg-tab-panel' data-tab='groups'>";
            echo "<div class='grupoBioClan'><div class='contenidoAfiliacion'>";
            foreach ($totemGroups as $g) {
                $gid = (int)($g['id'] ?? 0);
                $gname = (string)($g['name'] ?? '');
                $href = pretty_url($link, 'dim_groups', '/groups', $gid);
                echo "<a href='" . htmlspecialchars($href) . "' target='_blank'><div class='renglon2col' style='text-align: center;'>" . htmlspecialchars($gname) . "</div></a>";
            }
            echo "</div></div>";
            echo "<p align='right'>Grupos: " . count($totemGroups) . "</p>";
            echo "</section>";
        }

        if ($hasOrgOwners) {
            echo "<section class='hg-tab-panel' data-tab='orgs'>";
            echo "<div class='grupoBioClan'><div class='contenidoAfiliacion'>";
            foreach ($totemOrgs as $g) {
                $gid = (int)($g['id'] ?? 0);
                $gname = (string)($g['name'] ?? '');
                $href = pretty_url($link, 'dim_organizations', '/organizations', $gid);
                echo "<a href='" . htmlspecialchars($href) . "' target='_blank'><div class='renglon2col' style='text-align: center;'>" . htmlspecialchars($gname) . "</div></a>";
            }
            echo "</div></div>";
            echo "<p align='right'>Organizaciones: " . count($totemOrgs) . "</p>";
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
    echo "<p>Error: Tótem no encontrado.</p>";
}
?>
