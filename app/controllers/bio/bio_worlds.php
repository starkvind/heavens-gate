<?php
setMetaFromPage("Biografías por realidad | Heaven's Gate", "Listado de personajes agrupados por realidad.", null, 'website');
?>
<style>
    .toggleAfiliacion {
      background: #05014e;
      color: #fff;
      border: 1px solid #000088;
      padding: 6px 10px;
      margin: 8px 0 0 0;
      font-size: 1.1em;
      cursor: pointer;
      width: 85%;
      text-align: left;
    }
    .toggleAfiliacion:hover {
      background: #000066;
      border: 1px solid #0000BB;
    }
    .contenidoAfiliacion {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      padding: 8px 0 12px 0;
    }
    .oculto { display: none; }
</style>

<?php
if (!$link) {
    die("Error de conexion a la base de datos: " . mysqli_connect_error());
}
include_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!function_exists('hg_bwr_h')) {
    function hg_bwr_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('hg_bwr_sanitize_int_csv')) {
    function hg_bwr_sanitize_int_csv($csv){
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

$excludeChronicles = isset($excludeChronicles) ? hg_bwr_sanitize_int_csv($excludeChronicles) : '';
$chronicleNotInSQL = ($excludeChronicles !== '') ? " AND p.chronicle_id NOT IN ($excludeChronicles) " : "";
$realityFilterId = isset($_GET['t']) ? (int)$_GET['t'] : 0;
$realityFilterSQL = ($realityFilterId > 0) ? " AND p.reality_id = " . $realityFilterId . " " : "";

include("app/partials/main_nav_bar.php");
echo "<h2>Biografias por realidad</h2>";

$hasTable = false;
$rsChk = mysqli_query($link, "SHOW TABLES LIKE 'dim_realities'");
if ($rsChk) {
    $hasTable = (mysqli_num_rows($rsChk) > 0);
    mysqli_free_result($rsChk);
}

$hasColumn = false;
if ($hasTable) {
    $rsCol = mysqli_query($link, "SHOW COLUMNS FROM fact_characters LIKE 'reality_id'");
    if ($rsCol) {
        $hasColumn = (mysqli_num_rows($rsCol) > 0);
        mysqli_free_result($rsCol);
    }
}

if (!$hasTable || !$hasColumn) {
    echo "<p class='texti'>No existe el esquema de realidades en BDD (dim_realities / fact_characters.reality_id).</p>";
    return;
}

$sql = "
    SELECT
        p.id,
        p.name,
        p.alias,
        p.status,
        p.image_url,
        p.gender,
        p.character_kind,
        p.reality_id,
        COALESCE(r.name, 'Sin realidad') AS reality_name
    FROM fact_characters p
    LEFT JOIN dim_realities r ON r.id = p.reality_id
    WHERE 1=1
      $chronicleNotInSQL
      $realityFilterSQL
    ORDER BY r.name ASC, p.name ASC
";

$res = mysqli_query($link, $sql);
if (!$res) {
    echo "<p class='texti'>No se pudo cargar la lista de personajes.</p>";
    return;
}

$groups = [];
$countAll = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $realityId = (int)($row['reality_id'] ?? 0);
    $realityName = (string)($row['reality_name'] ?? 'Sin realidad');
    $key = $realityId > 0 ? (string)$realityId : 'none';

    if (!isset($groups[$key])) {
        $groups[$key] = [
            'id' => $realityId,
            'name' => $realityName,
            'items' => [],
        ];
    }
    $groups[$key]['items'][] = $row;
    $countAll++;
}
mysqli_free_result($res);

$keys = array_keys($groups);
usort($keys, function($a, $b) use ($groups){
    if ($a === 'none') return 1;
    if ($b === 'none') return -1;
    return strcasecmp((string)$groups[$a]['name'], (string)$groups[$b]['name']);
});

foreach ($keys as $k) {
    $grp = $groups[$k];
    $realityId = (int)$grp['id'];
    $realityName = (string)$grp['name'];
    $fieldsetId = 'reality_' . ($realityId > 0 ? $realityId : 'none');

    echo "<h3 class='toggleAfiliacion' data-target='" . hg_bwr_h($fieldsetId) . "'>" . hg_bwr_h($realityName) . "</h3>";
    echo "<fieldset class='grupoBioClan'>";
    echo "<div id='" . hg_bwr_h($fieldsetId) . "' class='contenidoAfiliacion'>";

    foreach ($grp['items'] as $rowPJ) {
        $idPJ = (int)($rowPJ['id'] ?? 0);
        $nombrePJ = hg_bwr_h($rowPJ['name'] ?? '');
        $aliasPJ = hg_bwr_h($rowPJ['alias'] ?? '');
        $imgPJ = hg_bwr_h(hg_character_avatar_url($rowPJ['image_url'] ?? '', $rowPJ['gender'] ?? ''));
        $claseRaw = strtolower(trim((string)($rowPJ['character_kind'] ?? '')));
        $estadoPJ = hg_bwr_h($rowPJ['status'] ?? '');

        if ($aliasPJ === '') $aliasPJ = $nombrePJ;

        $fondoFoto = '';
        $estiloLink = '';
        $isMonster = ($claseRaw === 'mon' || $claseRaw === 'monster');
        $isPj = ($claseRaw === 'pj');
        if ($isMonster) {
            $fondoFoto = "Monster";
            $estiloLink = "color: #FFD54A;";
        } elseif (!$isPj && $claseRaw !== '') {
            $fondoFoto = "NoSheet";
            $estiloLink = "color: #EE0000;";
        }

        $mapEstado = [
            "Aún por aparecer"     => "(&#64;)",
            "Paradero desconocido" => "(&#63;)",
            "Cadáver"              => "(&#8224;)",
            "Cadaver "             => "(&#8224;)"
        ];
        $simboloEstado = $mapEstado[$estadoPJ] ?? "";

        $hrefPJ = pretty_url($link, 'fact_characters', '/characters', $idPJ);
        echo "<a href='" . hg_bwr_h($hrefPJ) . "' title='" . $nombrePJ . "' style='" . hg_bwr_h($estiloLink) . "'>";
            echo "<div class='marcoFotoBio" . hg_bwr_h($fondoFoto) . "'>";
                echo "<div class='textoDentroFotoBio" . hg_bwr_h($fondoFoto) . "'>$aliasPJ $simboloEstado</div>";
                echo "<div class='dentroFotoBio'><img class='fotoBioList' src='$imgPJ' alt='$nombrePJ'></div>";
            echo "</div>";
        echo "</a>";
    }

    echo "</div>";
    echo "</fieldset>";
}

echo "<p align='right'>Personajes: " . hg_bwr_h($countAll) . "</p>";
?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var toggles = document.querySelectorAll('.toggleAfiliacion');
    for (var i = 0; i < toggles.length; i++) {
        toggles[i].addEventListener('click', function(){
            var targetId = this.getAttribute('data-target');
            var el = document.getElementById(targetId);
            if (!el) return;
            el.classList.toggle('oculto');
        });
    }
});
</script>
