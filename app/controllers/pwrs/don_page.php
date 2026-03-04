<?php
include_once(__DIR__ . '/../../helpers/character_avatar.php');
// Verificar si se recibe el parÃ¡metro 'b' y sanitizarlo
$donPageID = isset($_GET['b']) ? $_GET['b'] : ''; 

if (!function_exists('gift_has_column')) {
    function gift_has_column(mysqli $link, string $table, string $column): bool {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') return false;
        $rs = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        if (!$rs) return false;
        $ok = (mysqli_num_rows($rs) > 0);
        mysqli_free_result($rs);
        return $ok;
    }
}
$giftSystemCol = gift_has_column($link, 'fact_gifts', 'shifter_system_name') ? 'shifter_system_name' : 'system_name';
$giftRulesCol = gift_has_column($link, 'fact_gifts', 'mechanics_text') ? 'mechanics_text' : 'system_name';

// Consulta para obtener informaciÃ³n del Don
$queryDon = "
    SELECT g.*, s.name AS system_name, g.name AS nombre, g.kind AS tipo, g.rank AS rango, g.description AS descripcion, g.`$giftRulesCol` AS sistema, g.`$giftSystemCol` AS ferasistema
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

    // DATOS BÃSICOS
    $donId     = htmlspecialchars($resultQueryDon["id"]);
    $donName   = htmlspecialchars($resultQueryDon["nombre"]);
    $donType   = htmlspecialchars($resultQueryDon["tipo"]);
    $donGroup  = htmlspecialchars($resultQueryDon["gift_group"]);
    $donRank   = htmlspecialchars($resultQueryDon["rango"]);
    $donAttr   = htmlspecialchars($resultQueryDon["attribute_name"]);
    $donSkill  = htmlspecialchars($resultQueryDon["ability_name"]);
    $donDesc   = ($resultQueryDon["descripcion"]);
    $donRules  = ($resultQueryDon["sistema"]); // texto de reglas
    $donSystemName = htmlspecialchars($resultQueryDon["system_name"] ?? "");
    $donBreedLegacy  = trim((string)($resultQueryDon["ferasistema"] ?? ""));
    $donSystemLabel = $donSystemName;
    $donOrigin = htmlspecialchars($resultQueryDon["bibliography_id"]);
    $donImgRaw = trim((string)($resultQueryDon["image_url"] ?? ""));

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

    // Guardar en sesiÃ³n para los breadcrumbs
    $_SESSION['punk2'] = $nombreTipo;

    // =========================
    // Personajes con este Don (respeta exclusiones de crÃ³nica)
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
    $characterKindSql = hg_character_kind_select($link, 'c');
    if ($stOwners = $link->prepare("SELECT DISTINCT c.id, c.name AS nombre, c.alias, c.image_url, c.gender, COALESCE(dcs.label, '') AS status, c.status_id, {$characterKindSql} AS character_kind FROM bridge_characters_powers b JOIN fact_characters c ON c.id = b.character_id LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id WHERE b.power_kind='dones' AND b.power_id = ? $cronicaNotInSQL ORDER BY c.name")) {
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

    // Incluir barra de navegaciÃ³n
    include("app/partials/main_nav_bar.php");

    // TÃ­tulo de la pÃ¡gina
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
    if (!empty($donAttr) || !empty($donSkill)) {
        $tiradaDon2 = !empty($donSkill) ? "$donAttr + $donSkill" : $donAttr;
        echo "<div class='power-stat'><div class='power-stat__label'>Tirada</div><div class='power-stat__value'>$tiradaDon2</div></div>";
    }
    if ($donGroup !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Grupo</div><div class='power-stat__value'>$donGroup</div></div>";
    }
    if ($donBreedLegacy !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Sistema</div><div class='power-stat__value'>" . htmlspecialchars($donBreedLegacy) . "</div></div>";
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

      if (!empty($donRules)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Sistema</div>";
        if (!empty($donRules)) {
          echo "    <div class='power-card__desc-body'>$donRules</div>";
        }
        echo "  </div>";
      }

    echo "</div>"; // power-card

    $infoHtml = ob_get_clean();

    if ($useTabs) {
        include_once(__DIR__ . '/../../partials/owners_tabs_styles.php');
        hg_render_owner_tabs_styles(true, 28);

        echo "<div class='hg-tabs'>";
        echo "<button class='boton2 hgTabBtn' data-tab='info'>InformaciÃ³n</button>";
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
                $img = hg_character_avatar_url((string)($o['image_url'] ?? ''), (string)($o['gender'] ?? ''));
                $estado = (string)($o['status'] ?? '');
                $label = $alias !== '' ? $alias : $name;
                $mapEstado = [
                    "AÃºn por aparecer"     => "(&#64;)",
                    "Paradero desconocido" => "(&#63;)",
                    "CadÃ¡ver"              => "(&#8224;)"
                ];
                $simboloEstado = $mapEstado[$estado] ?? "";
                $href = pretty_url($link, 'fact_characters', '/characters', $oid);
                hg_render_character_avatar_tile([
                    'href' => $href,
                    'title' => $name,
                    'name' => $name,
                    'alias' => $alias,
                    'character_id' => $oid,
                    'image_url' => (string)($o['image_url'] ?? ''),
                    'gender' => (string)($o['gender'] ?? ''),
                    'status' => (string)($o['status'] ?? ''),
                    'character_kind' => hg_character_kind_from_row($o),
                    'target_blank' => true,
                ]);
            }
            echo "</div></div>";
            echo "<p align='right'>Personajes: " . count($donOwners) . "</p>";
            echo "</section>";
        }

    } else {
        echo $infoHtml;
    }

} else {
    echo "<p>Error: Don no encontrado.</p>";
}
?>





