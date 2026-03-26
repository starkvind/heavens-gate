<?php
include_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!function_exists('hg_merfla_normalize_text')) {
    function hg_merfla_normalize_text($value)
    {
        $text = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
        $text = trim(mb_strtolower($text, 'UTF-8'));
        $text = strtr($text, array(
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u'
        ));
        return $text;
    }
}

// Obtener par&aacute;metros 'b' y 'r' de manera segura
$mafPageID = isset($_GET['b']) ? $_GET['b'] : '';  // ID del M&eacute;rito/Defecto
$returnID = isset($_GET['r']) ? $_GET['r'] : '';  // ID del Regreso

$unknownOrigin = "-";

// Preparar la consulta para evitar inyecciones SQL
$queryMaf = "SELECT *, kind AS tipo, affiliation AS afiliacion, cost AS coste, description AS descripcion, system_name AS sistema FROM dim_merits_flaws WHERE id = ? LIMIT 1";

$stmtMaf = $link->prepare($queryMaf);
$stmtMaf->bind_param('s', $mafPageID);
$stmtMaf->execute();
$resultMaf = $stmtMaf->get_result();
$rowsQueryMaf = $resultMaf->num_rows;

// Comprobamos si hay resultados
if ($rowsQueryMaf > 0) {
    $resultQueryMaf = $resultMaf->fetch_assoc();

    // Datos b&aacute;sicos
    $mafId = htmlspecialchars($resultQueryMaf["id"]);
    $mafName = htmlspecialchars($resultQueryMaf["name"]);
    $mafType = htmlspecialchars($resultQueryMaf["tipo"]);
    $mafAfil = htmlspecialchars($resultQueryMaf["afiliacion"]);
    $mafCoste = htmlspecialchars($resultQueryMaf["coste"]);
    $mafDesc = $resultQueryMaf["descripcion"];
    $mafSystem = htmlspecialchars($resultQueryMaf["sistema"]);
    $mafOrigin = htmlspecialchars($resultQueryMaf["bibliography_id"]);

	$meritsAndFlawsQuery = "SELECT DISTINCT affiliation AS afiliacion FROM dim_merits_flaws ORDER BY affiliation ASC";

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
    $mafTypeNormalized = hg_merfla_normalize_text($mafType);
    if ($mafTypeNormalized === 'meritos' || strpos($mafTypeNormalized, 'merit') !== false) {
        $returnType = 1;
        $mafNameType = "M&eacute;rito";
    } elseif ($mafTypeNormalized === 'defectos' || strpos($mafTypeNormalized, 'defect') !== false) {
        $returnType = 2;
        $mafNameType = "Defecto";
    } else {
        $returnType = 1;
        $mafNameType = "Tipo desconocido";
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
    // Personajes con este M&eacute;rito/Defecto (respeta exclusiones de cr&oacute;nica)
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
    $mafOwners = [];
    $characterKindSql = hg_character_kind_select($link, 'c');
    if ($stOwners = $link->prepare("SELECT DISTINCT c.id, c.name AS nombre, c.alias, c.image_url, c.gender, COALESCE(dcs.label, '') AS status, c.status_id, {$characterKindSql} AS character_kind FROM bridge_characters_merits_flaws b JOIN fact_characters c ON c.id = b.character_id LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id WHERE b.merit_flaw_id = ? $cronicaNotInSQL ORDER BY c.name")) {
        $stOwners->bind_param('i', $mafPageID);
        $stOwners->execute();
        $rsOwners = $stOwners->get_result();
        while ($r = $rsOwners->fetch_assoc()) { $mafOwners[] = $r; }
        $stOwners->close();
    }
    $hasOwners = count($mafOwners) > 0;
    $useTabs = $hasOwners;

    // T&iacute;tulo e Im&aacute;genes
    $costMeritFlaw = $mafCoste;
    $pageSect = $mafNameType; // PARA CAMBIAR EL TITULO A LA PAGINA
    $pageTitle2 = $mafName;
    setMetaFromPage($mafName . " | Méritos y Defectos | Heaven's Gate", meta_excerpt($mafDesc), null, 'article');

    // Incluir archivos para navegaci&oacute;n y contenido
    include("app/partials/main_nav_bar.php"); // Barra navegaci&oacute;n
    echo '<link rel="stylesheet" href="/assets/css/hg-docs.css">';

    ob_start();

    $itemImg = "img/inv/no-photo.gif";
    $costText = '';
    if (is_numeric((string)$costMeritFlaw)) {
        $n = (int)$costMeritFlaw;
        $costText = $n . ' ' . (($n === 1) ? 'punto' : 'puntos');
    } else {
        $costText = (string)$costMeritFlaw;
    }

    echo "<div class='power-card power-card--merfla'>";
    echo "  <div class='power-card__banner'>";
    echo "    <span class='power-card__title'>$mafName</span>";
    echo "  </div>";

    echo "  <div class='power-card__body'>";
    echo "    <div class='power-card__media'>";
    echo "      <img class='power-card__img power-card__img--framed' src='$itemImg' alt='$mafName'/>";
    echo "    </div>";

    echo "    <div class='power-card__stats'>";
    if ($mafNameType !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Tipo</div><div class='power-stat__value'>$mafNameType</div></div>";
    }
    if ($costText !== '' && $costText !== '0 puntos') {
        echo "<div class='power-stat'><div class='power-stat__label'>Coste</div><div class='power-stat__value'>$costText</div></div>";
    }
    if ($mafAfil !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Categor&iacute;a</div><div class='power-stat__value'>$mafAfil</div></div>";
    }
    if ($mafSystem !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Sistema</div><div class='power-stat__value'>$mafSystem</div></div>";
    }
    if ($mafOriginName !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>$mafOriginName</div></div>";
    }
    echo "    </div>";
    echo "  </div>";

    // Descripci&oacute;n del M&eacute;rito
    if ($mafDesc != "") {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Descripci&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>$mafDesc</div>";
        echo "  </div>";
    }

    echo "</div>";
    $infoHtml = ob_get_clean();

    if ($useTabs) {
        include_once(__DIR__ . '/../../partials/owners_tabs_styles.php');
        hg_render_owner_tabs_styles(true, 28);

        echo "<div class='hg-tabs'>";
        echo "<button class='boton2 hgTabBtn' data-tab='info'>Informaci&oacute;n</button>";
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
                $img = hg_character_avatar_url((string)($o['image_url'] ?? ''), (string)($o['gender'] ?? ''));
                $estado = (string)($o['status'] ?? '');
                $estadoNormalized = hg_merfla_normalize_text($estado);
                $label = $alias !== '' ? $alias : $name;
                if ($estadoNormalized === 'aun por aparecer') {
                    $simboloEstado = "(&#64;)";
                } elseif ($estadoNormalized === 'paradero desconocido') {
                    $simboloEstado = "(&#63;)";
                } elseif ($estadoNormalized === 'cadaver') {
                    $simboloEstado = "(&#8224;)";
                } else {
                    $simboloEstado = "";
                }
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
            echo "<p align='right'>Personajes: " . count($mafOwners) . "</p>";
            echo "</section>";
        }

    } else {
        echo $infoHtml;
    }
} // Fin comprobaci&oacute;n

// Cerramos la sentencia preparada para la consulta principal
$stmtMaf->close();
?>





