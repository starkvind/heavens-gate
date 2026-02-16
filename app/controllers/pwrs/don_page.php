<?php
// Verificar si se recibe el parámetro 'b' y sanitizarlo
$donPageID = isset($_GET['b']) ? $_GET['b'] : ''; 

// Consulta para obtener información del Don
$queryDon = "
    SELECT g.*, s.name AS system_name, g.name AS nombre, g.kind AS tipo, g.rank AS rango, g.description AS descripcion, g.system_name AS sistema, g.shifter_system_name AS ferasistema
    FROM fact_gifts g
    LEFT JOIN dim_systems s ON g.system_id = s.id
    WHERE g.id = ? LIMIT 1;
";
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
    $donRules  = ($resultQueryDon["sistema"]); // texto de reglas
    $donSystemName = htmlspecialchars($resultQueryDon["system_name"] ?? "");
    $donBreedLegacy  = trim((string)($resultQueryDon["ferasistema"] ?? ""));
    $donSystemLabel = $donSystemName;
    $donOrigin = htmlspecialchars($resultQueryDon["bibliography_id"]);
    $donImgRaw = trim((string)($resultQueryDon["img"] ?? ""));

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
    $cronicaNotInSQL = ($excludeChronicles !== '') ? " AND c.chronicle_id NOT IN ($excludeChronicles) " : "";
    $donOwners = [];
    if ($stOwners = $link->prepare("SELECT DISTINCT c.id, c.name AS nombre, c.alias, c.img, c.estado FROM bridge_characters_powers b JOIN fact_characters c ON c.id = b.character_id WHERE b.power_kind='dones' AND b.power_id = ? $cronicaNotInSQL ORDER BY c.name")) {
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
    //echo "<h2>$donName</h2>";

    ob_start();

    // Imagen del Don
    $itemImg = "img/inv/no-photo.gif"; // Valor por defecto si no hay imagen
    if ($donImgRaw !== "") {
        if (strpos($donImgRaw, "/") !== false) {
            $itemImg = $donImgRaw;
        } else {
            $itemImg = "img/gifts/" . $donImgRaw;
        }
    }

    echo "<div class='power-card power-card--don'>";
    echo "  <div class='power-card__banner'>";
    echo "    <span class='power-card__title'>$donName</span>";
    echo "  </div>";

    echo "  <div class='power-card__body'>";
    echo "    <div class='power-card__media'>";
    echo "      <img class='power-card__img' style='border:1px solid #001a55; box-shadow: 0 0 0 2px #001a55, 0 0 14px rgba(0,0,0,0.5)' src='$itemImg' alt='$donName'/>";
    echo "    </div>";

    echo "    <div class='power-card__stats'>";
    if ($donRank > 0) {
        echo "<div class='power-stat'><div class='power-stat__label'>Rango</div><div class='power-stat__value'><img class='bioAttCircle' src='img/ui/gems/pwr/gem-pwr-0$donRank.png'/></div></div>";
    }
    if ($donGroup !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Grupo</div><div class='power-stat__value'>$donGroup</div></div>";
    }
    if (!empty($donAttr) || !empty($donSkill)) {
        $tiradaDon2 = !empty($donSkill) ? "$donAttr + $donSkill" : $donAttr;
        echo "<div class='power-stat'><div class='power-stat__label'>Tirada</div><div class='power-stat__value'>$tiradaDon2</div></div>";
    }
      if ($donBreedLegacy !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Fera-sistema</div><div class='power-stat__value'>" . htmlspecialchars($donBreedLegacy) . "</div></div>";
      }
      if ($donOriginName !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>$donOriginName</div></div>";
      }
    echo "    </div>"; // stats
    echo "  </div>"; // body

      if (!empty($donDesc)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Descripci&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>$donDesc</div>";
        echo "  </div>";
      }

      if ($donSystemLabel !== "" || !empty($donRules)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Sistema</div>";
        if ($donSystemLabel !== "") {
          echo "    <div class='power-card__desc-body'><strong>$donSystemLabel</strong></div>";
        }
        if (!empty($donRules)) {
          echo "    <div class='power-card__desc-body'>$donRules</div>";
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
