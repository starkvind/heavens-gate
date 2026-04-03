<?php
setMetaFromPage("Resultados de busqueda | Heaven's Gate", "Resultados de la busqueda en el repositorio.", null, 'website');
include_once(__DIR__ . '/../../helpers/public_response.php');

if (!$link) {
    hg_public_log_error('main_search_result', 'missing DB connection');
    hg_public_render_error('Busqueda no disponible', 'No se pudo ejecutar la busqueda en este momento.');
    return;
}

function hg_search_input(string $key): string
{
    $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
    if (!is_string($value)) {
        return '';
    }

    return trim(strip_tags($value));
}

function hg_search_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$bsq = hg_search_input('q');
$skz = hg_search_input('section');
if ($bsq === '') {
    $bsq = hg_search_input('bsq');
}
if ($skz === '') {
    $skz = hg_search_input('skz');
}

$letrax = function_exists('mb_strlen') ? mb_strlen($bsq, 'UTF-8') : strlen($bsq);

include("app/partials/main_nav_bar.php");
echo '<link rel="stylesheet" href="/assets/css/hg-main.css">';
echo "<h2>Resultado de la busqueda</h2>";

if (!empty($bsq) && $letrax > 3) {
    $katxos = preg_split('/\s+/', $bsq, -1, PREG_SPLIT_NO_EMPTY);
    $consulta = '';
    $searchField = '';
    $rutu = '';

    function build_pretty_search_url(string $rutu, string $id): string {
        global $link;
        $idInt = (int)$id;
        switch ($rutu) {
            case 'muestrabio':
                return pretty_url($link, 'fact_characters', '/characters', $idInt);
            case 'verdoc':
                return pretty_url($link, 'fact_docs', '/documents', $idInt);
            case 'seeitem':
                $itemId = $idInt;
                $typeSlug = '';
                $itemSlug = '';
                if ($stmt = $link->prepare("SELECT i.pretty_id AS item_pretty, t.pretty_id AS type_pretty, t.id AS type_id FROM fact_items i LEFT JOIN dim_item_types t ON t.id = i.item_type_id WHERE i.id = ? LIMIT 1")) {
                    $stmt->bind_param('i', $itemId);
                    $stmt->execute();
                    $rs = $stmt->get_result();
                    if ($rs && ($row = $rs->fetch_assoc())) {
                        $itemSlug = (string)($row['item_pretty'] ?? '');
                        $typeSlug = (string)($row['type_pretty'] ?? '');
                        if ($typeSlug === '' && isset($row['type_id'])) {
                            $typeSlug = (string)$row['type_id'];
                        }
                    }
                    $stmt->close();
                }
                if ($itemSlug === '') $itemSlug = (string)$itemId;
                if ($typeSlug === '') $typeSlug = 'tipo';
                return "/inventory/" . rawurlencode($typeSlug) . "/" . rawurlencode($itemSlug);
            case 'muestradon':
                return pretty_url($link, 'fact_gifts', '/powers/gift', $idInt);
            case 'verrasgo':
                return pretty_url($link, 'dim_traits', '/rules/traits', $idInt);
            case 'sistemas':
                return pretty_url($link, 'dim_systems', '/systems', $idInt);
            case 'vermyd':
                return pretty_url($link, 'dim_merits_flaws', '/rules/merits-flaws', $idInt);
            default:
                return "?p=$rutu&b=$id";
        }
    }

    $giftRulesField = 'system_name';
    if ($rsGiftCol = mysqli_query($link, "SHOW COLUMNS FROM `fact_gifts` LIKE 'mechanics_text'")) {
        if (mysqli_num_rows($rsGiftCol) > 0) {
            $giftRulesField = 'mechanics_text';
        }
        mysqli_free_result($rsGiftCol);
    }

    switch ($skz) {
        case 'biografias':
            $tabla = 'fact_characters';
            $campos = 'name, info_text';
            $searchField = 'name';
            $rutu = 'muestrabio';
            break;
        case 'escritos':
            $tabla = 'fact_docs';
            $campos = 'title, content';
            $searchField = 'title';
            $rutu = 'verdoc';
            break;
        case 'objetos':
            $tabla = 'fact_items';
            $campos = 'name, description';
            $searchField = 'name';
            $rutu = 'seeitem';
            break;
        case 'dones':
            $tabla = 'fact_gifts';
            $campos = 'name, description, ' . $giftRulesField;
            $searchField = 'name';
            $rutu = 'muestradon';
            break;
        case 'habilidades':
            $tabla = 'dim_traits';
            $campos = 'name, description';
            $searchField = 'name';
            $rutu = 'verrasgo';
            break;
        case 'sistemas':
            $tabla = 'dim_systems';
            $campos = 'name, description';
            $searchField = 'name';
            $rutu = 'sistemas';
            break;
        case 'merydef':
            $tabla = 'dim_merits_flaws';
            $campos = 'name, description';
            $searchField = 'name';
            $rutu = 'vermyd';
            break;
        default:
            hg_public_render_not_found('Busqueda no disponible', 'La categoria de busqueda solicitada no es valida.');
            return;
    }

    $fields = array_map('trim', explode(',', $campos));
    $whereParts = [];
    $params = [];
    $types = '';

    foreach ($katxos as $term) {
        $like = '%' . $term . '%';
        $sub = [];
        foreach ($fields as $f) {
            $sub[] = "$f LIKE ?";
            $params[] = $like;
            $types .= 's';
        }
        if (!empty($sub)) {
            $whereParts[] = '(' . implode(' OR ', $sub) . ')';
        }
    }

    if (empty($whereParts)) {
        echo "<center>La busqueda debe realizarse con mas de 3 letras.</center>";
        return;
    }

    $whereSql = implode(' AND ', $whereParts);
    $consulta = "SELECT id, $searchField FROM $tabla WHERE $whereSql ORDER BY id LIMIT 50";

    $stmt = mysqli_prepare($link, $consulta);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            $numregistros = mysqli_num_rows($result);

            if ($numregistros > 0) {
                echo "<p>Con <b><u>" . hg_search_h($bsq) . "</u></b> se ha encontrado lo siguiente:</p>";
                echo "<fieldset class='grupoHabilidad'>";
                while ($row = mysqli_fetch_assoc($result)) {
                    $titulo = hg_search_h($row[$searchField] ?? '');
                    $id = hg_search_h($row['id'] ?? '');
                    $href = build_pretty_search_url($rutu, (string)$id);
                    echo "<a title='$titulo' href='" . hg_search_h($href) . "' target='_blank'>";
                    echo "    <div class='renglon2col main-search-center'>$titulo</div>";
                    echo "</a>";
                }
                echo "</fieldset>";
            } else {
                echo "<p class='main-search-center'>No se ha encontrado nada que concuerde con '" . hg_search_h($bsq) . "'. Intenta de nuevo.</p>";
            }

            mysqli_free_result($result);
        } else {
            hg_public_log_error('main_search_result', 'query failed: ' . mysqli_error($link));
            echo "<p class='main-search-center'>No se pudo completar la busqueda en este momento.</p>";
        }

        mysqli_stmt_close($stmt);
    } else {
        hg_public_log_error('main_search_result', 'prepare failed: ' . mysqli_error($link));
        echo "<p class='main-search-center'>No se pudo completar la busqueda en este momento.</p>";
    }
} elseif (empty($bsq)) {
    echo "<center>No existe ningun criterio.</center>";
} else {
    echo "<center>La busqueda debe realizarse con mas de 3 letras.</center>";
}

echo "<br/><center><input class='boton1' type='button' onclick='history.go(-1)' value='Volver'/></center>";
?>
