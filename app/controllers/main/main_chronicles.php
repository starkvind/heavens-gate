<?php
setMetaFromPage("Cronicas | Heaven's Gate", "Cronicas del universo Heaven's Gate.", null, 'website');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
?>
<style>
    .chron-grid{
      display:grid;
      grid-template-columns: repeat(2, minmax(280px, 1fr));
      gap:14px;
      margin-top:10px;
    }
    .chron-card{
      display:grid;
      grid-template-columns: 140px 1fr;
      gap:10px;
      align-items:stretch;
      border:1px solid #000088;
      background:#05014e;
      padding:8px;
      text-decoration:none;
      color:#fff;
    }
    .chron-card:hover{ background:#000066; border-color:#0000bb; }
    .chron-card img{
      width:140px; height:86px; object-fit:cover; border:1px solid #1b4aa0; background:#000022;
    }
    .chron-card h3{ margin:0 0 6px 0; color:#7ef7ff; font-size:1.05em; text-align: left; }
    .chron-card p{
      margin:0;
      color:#dbe7ff;
      line-height:1.35;
      font-size:.95em;
      text-align:left;
    }
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
    .chronicleIntro {
      margin-bottom: 12px;
      padding: 8px 12px;
    }
    .chronicleIntroTitle { margin:0 0 8px 0; font-size:1.35em; }
    .chronicleIntroDesc {
      margin:0;
      max-width:980px;
      line-height:1.45;
      text-align:left;
      white-space:normal;
      overflow-wrap:anywhere;
    }
    .oculto { display: none; }
    @media (max-width:860px){ .chron-grid{ grid-template-columns:1fr; } }
</style>

<?php
if (!$link) {
    die("Error de conexion a la base de datos: " . mysqli_connect_error());
}

if (!function_exists('hg_ch_h')) {
    function hg_ch_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('hg_ch_sanitize_int_csv')) {
    function hg_ch_sanitize_int_csv($csv){
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
if (!function_exists('hg_ch_has_column')) {
    function hg_ch_has_column(mysqli $link, string $table, string $column): bool {
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
if (!function_exists('hg_ch_excerpt')) {
    function hg_ch_excerpt(string $txt, int $max = 170): string {
        $txt = trim(strip_tags($txt));
        if ($txt === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return (mb_strlen($txt, 'UTF-8') > $max) ? (mb_substr($txt, 0, $max, 'UTF-8') . '...') : $txt;
        }
        return (strlen($txt) > $max) ? (substr($txt, 0, $max) . '...') : $txt;
    }
}

$excludeChronicles = isset($excludeChronicles) ? hg_ch_sanitize_int_csv($excludeChronicles) : '';
$chronicleNotInSQL = ($excludeChronicles !== '') ? " AND p.chronicle_id NOT IN ($excludeChronicles) " : "";
$chronicleFilterId = isset($_GET['t']) ? (int)$_GET['t'] : 0;
$chronicleFilterSQL = ($chronicleFilterId > 0) ? " AND p.chronicle_id = " . $chronicleFilterId . " " : "";
$kindColumn = hg_ch_has_column($link, 'fact_characters', 'character_kind') ? 'character_kind' : 'kind';
$statusColumn = hg_ch_has_column($link, 'fact_characters', 'status') ? 'status' : 'character_status';

include("app/partials/main_nav_bar.php");

if ($chronicleFilterId <= 0) {
    echo "<h2>Crónicas</h2>";

    $chronicles = [];
    $sqlChron = "
        SELECT id, pretty_id, name, description, IFNULL(sort_order, 999999) AS sort_order
        FROM dim_chronicles
        WHERE 1=1
        " . (($excludeChronicles !== '') ? " AND id NOT IN ($excludeChronicles) " : "") . "
        ORDER BY sort_order ASC, name ASC
    ";
    if ($rsChron = mysqli_query($link, $sqlChron)) {
        while ($r = mysqli_fetch_assoc($rsChron)) { $chronicles[] = $r; }
        mysqli_free_result($rsChron);
    }

    if (count($chronicles) === 0) {
        echo "<p class='texti'>No hay cronicas disponibles.</p>";
        return;
    }

    $imgByPretty = [
        'heavens-gate' => '/img/og/og_image_bio.jpg',
        'javi' => '/img/og/og_image.jpg',
        'werewolf-gt' => '/img/og/og_image_temp.jpg',
        'hg-tercer-ojo' => '/img/og/og_image_power.jpg',
        'hg-babylon' => '/img/og/og_image_monster.jpg',
        'hg-london' => '/img/og/og_image_temp.jpg',
        'cenizas' => '/img/og/og_image_power.jpg',
    ];
    $fallbackImgs = [
        '/img/og/og_image_bio.jpg',
        '/img/og/og_image.jpg',
        '/img/og/og_image_temp.jpg',
        '/img/og/og_image_power.jpg',
    ];

    echo "<div class='chron-grid'>";
    $i = 0;
    foreach ($chronicles as $ch) {
        $cid = (int)($ch['id'] ?? 0);
        $pretty = (string)($ch['pretty_id'] ?? '');
        $name = (string)($ch['name'] ?? '');
        $desc = (string)($ch['description'] ?? '');
        $img = $imgByPretty[$pretty] ?? $fallbackImgs[$i % count($fallbackImgs)];
        $href = pretty_url($link, 'dim_chronicles', '/chronicles', $cid);
        $descShort = hg_ch_excerpt($desc, 180);

        echo "<a class='chron-card' href='" . hg_ch_h($href) . "' title='" . hg_ch_h($name) . "'>";
        echo "  <img src='" . hg_ch_h($img) . "' alt='" . hg_ch_h($name) . "'>";
        echo "  <div>";
        echo "    <h3>" . hg_ch_h($name) . "</h3>";
        echo "    <p>" . hg_ch_h($descShort !== '' ? $descShort : 'Sin descripcion.') . "</p>";
        echo "  </div>";
        echo "</a>";
        $i++;
    }
    echo "</div>";
    return;
}

if ($chronicleFilterId > 0) {
    if ($stChron = $link->prepare("SELECT name, description FROM dim_chronicles WHERE id = ? LIMIT 1")) {
        $stChron->bind_param('i', $chronicleFilterId);
        $stChron->execute();
        $rsChron = $stChron->get_result();
        if ($rowChron = $rsChron->fetch_assoc()) {
            $chronName = hg_ch_h((string)($rowChron['name'] ?? ''));
            echo "<h2>$chronName</h2>";
            $chronDescRaw = trim((string)($rowChron['description'] ?? ''));
            $chronDesc = $chronDescRaw !== '' ? nl2br(hg_ch_h($chronDescRaw)) : '';
            echo "<fieldset class='grupoBioClan chronicleIntro'>";
            //echo "<h3 class='chronicleIntroTitle'>$chronName</h3>";
            if ($chronDesc !== '') {
                echo "<p class='texti chronicleIntroDesc'>$chronDesc</p>";
            } else {
                echo "<p class='texti chronicleIntroDesc'>Sin descripcion.</p>";
            }
            echo "</fieldset>";
        }
        $stChron->close();
    }
}

$sql = "
    SELECT
        p.id,
        p.name,
        p.alias,
        p.`$statusColumn` AS status,
        p.image_url,
        p.gender,
        p.`$kindColumn` AS character_kind,
        p.chronicle_id,
        COALESCE(ch.name, 'Sin cronica') AS chronicle_name,
        COALESCE(nc2.id, nc_from_pack.id, 0) AS organization_id,
        COALESCE(nc2.name, nc_from_pack.name, 'Sin organizacion') AS organization_name,
        COALESCE(nc2.sort_order, nc_from_pack.sort_order, 999999) AS organization_sort_order
    FROM fact_characters p
    LEFT JOIN dim_chronicles ch ON ch.id = p.chronicle_id
    LEFT JOIN bridge_characters_groups hcg
        ON hcg.character_id = p.id
       AND (hcg.is_active = 1 OR hcg.is_active IS NULL)
    LEFT JOIN dim_groups ng2
        ON ng2.id = hcg.group_id
    LEFT JOIN bridge_characters_organizations hcc
        ON hcc.character_id = p.id
       AND (hcc.is_active = 1 OR hcc.is_active IS NULL)
    LEFT JOIN dim_organizations nc2
        ON nc2.id = hcc.organization_id
    LEFT JOIN bridge_organizations_groups hog
        ON hog.group_id = ng2.id
       AND (hog.is_active = 1 OR hog.is_active IS NULL)
    LEFT JOIN dim_organizations nc_from_pack
        ON nc_from_pack.id = hog.organization_id
    WHERE 1=1
      $chronicleNotInSQL
      $chronicleFilterSQL
    ORDER BY
      ch.name ASC,
      COALESCE(nc2.sort_order, nc_from_pack.sort_order, 999999) ASC,
      COALESCE(nc2.name, nc_from_pack.name, 'Sin organizacion') ASC,
      CASE LOWER(TRIM(p.`$kindColumn`))
        WHEN 'pj' THEN 1
        WHEN 'pnj' THEN 2
        WHEN 'mon' THEN 3
        ELSE 99
      END ASC,
      CASE LOWER(TRIM(p.`$statusColumn`))
        WHEN 'en activo' THEN 1
        WHEN 'paradero desconocido' THEN 2
        WHEN 'cadáver' THEN 3
        WHEN 'cadaver' THEN 3
        WHEN 'aún por aparecer' THEN 4
        WHEN 'aun por aparecer' THEN 4
        ELSE 99
      END ASC,
      p.name ASC
";

$res = mysqli_query($link, $sql);
if (!$res) {
    echo "<p class='texti'>No se pudo cargar la lista de personajes.</p>";
    return;
}

$groups = [];
$countAll = 0;
while ($row = mysqli_fetch_assoc($res)) {
    if ($chronicleFilterId > 0) {
        $organizationId = (int)($row['organization_id'] ?? 0);
        $organizationName = (string)($row['organization_name'] ?? 'Sin organizacion');
        $key = $organizationId > 0 ? (string)$organizationId : 'none';
        $groupId = $organizationId;
        $groupName = $organizationName;
    } else {
        $chronicleId = (int)($row['chronicle_id'] ?? 0);
        $chronicleName = (string)($row['chronicle_name'] ?? 'Sin cronica');
        $key = $chronicleId > 0 ? (string)$chronicleId : 'none';
        $groupId = $chronicleId;
        $groupName = $chronicleName;
    }

    if (!isset($groups[$key])) {
        $groups[$key] = [
            'id' => $groupId,
            'name' => $groupName,
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
    $groupId = (int)$grp['id'];
    $groupName = (string)$grp['name'];
    $fieldsetId = ($chronicleFilterId > 0 ? 'org_' : 'chronicle_') . ($groupId > 0 ? $groupId : 'none');

    echo "<h3 class='toggleAfiliacion' data-target='" . hg_ch_h($fieldsetId) . "'>" . hg_ch_h($groupName) . "</h3>";
    echo "<fieldset class='grupoBioClan'>";
    echo "<div id='" . hg_ch_h($fieldsetId) . "' class='contenidoAfiliacion'>";

    foreach ($grp['items'] as $rowPJ) {
        $idPJ = (int)($rowPJ['id'] ?? 0);
        $nombrePJ = hg_ch_h($rowPJ['name'] ?? '');
        $aliasPJ = hg_ch_h($rowPJ['alias'] ?? '');
        $imgPJ = hg_ch_h(hg_character_avatar_url($rowPJ['image_url'] ?? '', $rowPJ['gender'] ?? ''));
        $claseRaw = strtolower(trim((string)($rowPJ['character_kind'] ?? '')));
        $estadoPJ = hg_ch_h($rowPJ['status'] ?? '');

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
            "Aun por aparecer"     => "(&#64;)",
            "Paradero desconocido" => "(&#63;)",
            "Cadáver"              => "(&#8224;)",
            "Cadaver"              => "(&#8224;)"
        ];
        $simboloEstado = $mapEstado[$estadoPJ] ?? "";

        $hrefPJ = pretty_url($link, 'fact_characters', '/characters', $idPJ);
        echo "<a href='" . hg_ch_h($hrefPJ) . "' title='" . $nombrePJ . "' style='" . hg_ch_h($estiloLink) . "'>";
            echo "<div class='marcoFotoBio" . hg_ch_h($fondoFoto) . "'>";
                echo "<div class='textoDentroFotoBio" . hg_ch_h($fondoFoto) . "'>$aliasPJ $simboloEstado</div>";
                echo "<div class='dentroFotoBio'><img class='fotoBioList' src='$imgPJ' alt='$nombrePJ'></div>";
            echo "</div>";
        echo "</a>";
    }

    echo "</div>";
    echo "</fieldset>";
}

echo "<p align='right'>Personajes: " . hg_ch_h($countAll) . "</p>";
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
