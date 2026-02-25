<?php
include_once(__DIR__ . '/../../helpers/character_avatar.php');
$archeRaw = $_GET['b'] ?? '';
$archeId = resolve_pretty_id($link, 'dim_archetypes', (string)$archeRaw) ?? 0;

$queryArche = "SELECT * FROM dim_archetypes WHERE id = ? LIMIT 1";
$stmtArche = $link->prepare($queryArche);

if (!$stmtArche || $archeId <= 0) {
    echo "No se encontraron resultados para la busqueda.";
    return;
}

$stmtArche->bind_param('i', $archeId);
$stmtArche->execute();
$resultArche = $stmtArche->get_result();

if (!$resultArche || $resultArche->num_rows <= 0) {
    echo "No se encontraron resultados para la busqueda.";
    $stmtArche->close();
    return;
}

$resultQueryArche = $resultArche->fetch_assoc();

$archeName = htmlspecialchars((string)$resultQueryArche['name']);
$archeDesc = (string)($resultQueryArche['description'] ?? '');
$archeWill = (string)($resultQueryArche['willpower_text'] ?? '');
$archeOrig = (int)($resultQueryArche['bibliography_id'] ?? 0);

$archeOrigName = '-';
if ($archeOrig > 0) {
    $queryOrigen = "SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1";
    if ($stmtOrigen = $link->prepare($queryOrigen)) {
        $stmtOrigen->bind_param('i', $archeOrig);
        $stmtOrigen->execute();
        $resultOrigen = $stmtOrigen->get_result();
        if ($resultOrigen && ($rowOrigen = $resultOrigen->fetch_assoc())) {
            $archeOrigName = htmlspecialchars((string)$rowOrigen['name']);
        }
        $stmtOrigen->close();
    }
}

$pageSect = 'Arquetipo';
$pageTitle2 = $archeName;
setMetaFromPage($archeName . " | Arquetipos | Heaven's Gate", meta_excerpt($archeDesc), null, 'article');
include("app/partials/main_nav_bar.php");

$itemImg = 'img/inv/no-photo.gif';

ob_start();

echo "<div class='power-card power-card--item'>";
echo "  <div class='power-card__banner'>";
echo "    <span class='power-card__title'>{$archeName}</span>";
echo "  </div>";

echo "  <div class='power-card__body'>";
echo "    <div class='power-card__media'>";
echo "      <div class='power-card__img-wrap'>";
echo "        <img class='power-card__img' src='" . htmlspecialchars($itemImg) . "' alt='{$archeName}'/>";
echo "      </div>";
echo "    </div>";

echo "    <div class='power-card__stats'>";
echo "      <div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>{$archeOrigName}</div></div>";
echo "    </div>";
echo "  </div>";

if ($archeDesc !== '') {
    echo "  <div class='power-card__desc'>";
    echo "    <div class='power-card__desc-title'>Descripcion</div>";
    echo "    <div class='power-card__desc-body'>{$archeDesc}</div>";
    echo "  </div>";
}
if ($archeWill !== '') {
    echo "  <div class='power-card__desc'>";
    echo "    <div class='power-card__desc-title'>Fuerza de Voluntad</div>";
    echo "    <div class='power-card__desc-body'>" . ($archeWill) . "</div>";
    echo "  </div>";
}

echo "</div>";

$infoHtml = ob_get_clean();

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
$chronicleNotInSQL = ($excludeChronicles !== '') ? " AND p.chronicle_id NOT IN ($excludeChronicles) " : "";

$natureOwners = [];
$demeanorOwners = [];

$queryOwnersNature = "
    SELECT p.id, p.name, p.alias, p.image_url, p.gender, p.status
    FROM fact_characters p
    WHERE p.nature_id = ? {$chronicleNotInSQL}
    ORDER BY p.name
";
if ($stNature = $link->prepare($queryOwnersNature)) {
    $stNature->bind_param('i', $archeId);
    $stNature->execute();
    $rsNature = $stNature->get_result();
    while ($row = $rsNature->fetch_assoc()) {
        $natureOwners[] = $row;
    }
    $stNature->close();
}

$queryOwnersDemeanor = "
    SELECT p.id, p.name, p.alias, p.image_url, p.gender, p.status
    FROM fact_characters p
    WHERE p.demeanor_id = ? {$chronicleNotInSQL}
    ORDER BY p.name
";
if ($stDemeanor = $link->prepare($queryOwnersDemeanor)) {
    $stDemeanor->bind_param('i', $archeId);
    $stDemeanor->execute();
    $rsDemeanor = $stDemeanor->get_result();
    while ($row = $rsDemeanor->fetch_assoc()) {
        $demeanorOwners[] = $row;
    }
    $stDemeanor->close();
}

$hasNature = count($natureOwners) > 0;
$hasDemeanor = count($demeanorOwners) > 0;
$hasOwnersTabs = $hasNature || $hasDemeanor;

if ($hasOwnersTabs) {
    include_once(__DIR__ . '/../../partials/owners_tabs_styles.php');
    hg_render_owner_tabs_styles(true, 28);

    echo "<style>
        .hg-tab-panel[data-tab='owners'] .grupoBioClan{ display:flex; justify-content:flex-start !important; }
        .hg-tab-panel[data-tab='owners'] .contenidoAfiliacion{
            display:flex;
            flex-wrap:wrap;
            gap:6px;
            padding:8px 0 12px 0 !important;
            margin-left:28px !important;
            justify-content:flex-start !important;
        }
        .owners-section-title{
            color:#66CCFF;
            font-weight:bold;
            margin:4px 0 6px 28px;
            text-transform:uppercase;
            letter-spacing:.04em;
        }
    </style>";

    echo "<div class='hg-tabs'>";
    echo "<button class='boton2 hgTabBtn' data-tab='info'>Informacion</button>";
    echo "<button class='boton2 hgTabBtn' data-tab='owners'>Portadores</button>";
    echo "</div>";

    echo "<section class='hg-tab-panel' data-tab='info'>{$infoHtml}</section>";

    $mapEstado = [
        'Aun por aparecer'      => '(@)',
        'Aún por aparecer'      => '(@)',
        'Paradero desconocido'  => '(?)',
        'Cadaver'               => '(&#8224;)',
        'Cadáver'               => '(&#8224;)'
    ];

    echo "<section class='hg-tab-panel' data-tab='owners'>";

    if ($hasNature) {
        echo "<div class='owners-section-title'>Naturaleza</div>";
        echo "<div class='grupoBioClan'><div class='contenidoAfiliacion'>";
        foreach ($natureOwners as $o) {
            $oid = (int)($o['id'] ?? 0);
            $name = htmlspecialchars((string)($o['name'] ?? ''));
            $alias = htmlspecialchars((string)($o['alias'] ?? ''));
            $img = htmlspecialchars(hg_character_avatar_url((string)($o['image_url'] ?? ''), (string)($o['gender'] ?? '')));
            $estado = (string)($o['status'] ?? '');
            $label = $alias !== '' ? $alias : $name;
            $simboloEstado = $mapEstado[$estado] ?? '';
            $href = pretty_url($link, 'fact_characters', '/characters', $oid);
            echo "<a href='" . htmlspecialchars($href) . "' target='_blank' title='{$name}'>";
            echo "<div class='marcoFotoBio'>";
            echo "<div class='textoDentroFotoBio'>{$label} {$simboloEstado}</div>";
            echo "<div class='dentroFotoBio'><img class='fotoBioList' src='{$img}' alt='{$name}'></div>";
            echo "</div>";
            echo "</a>";
        }
        echo "</div></div>";
        echo "<p align='right'>Personajes (Naturaleza): " . count($natureOwners) . "</p>";
    }

    if ($hasDemeanor) {
        echo "<div class='owners-section-title'>Conducta</div>";
        echo "<div class='grupoBioClan'><div class='contenidoAfiliacion'>";
        foreach ($demeanorOwners as $o) {
            $oid = (int)($o['id'] ?? 0);
            $name = htmlspecialchars((string)($o['name'] ?? ''));
            $alias = htmlspecialchars((string)($o['alias'] ?? ''));
            $img = htmlspecialchars(hg_character_avatar_url((string)($o['image_url'] ?? ''), (string)($o['gender'] ?? '')));
            $estado = (string)($o['status'] ?? '');
            $label = $alias !== '' ? $alias : $name;
            $simboloEstado = $mapEstado[$estado] ?? '';
            $href = pretty_url($link, 'fact_characters', '/characters', $oid);
            echo "<a href='" . htmlspecialchars($href) . "' target='_blank' title='{$name}'>";
            echo "<div class='marcoFotoBio'>";
            echo "<div class='textoDentroFotoBio'>{$label} {$simboloEstado}</div>";
            echo "<div class='dentroFotoBio'><img class='fotoBioList' src='{$img}' alt='{$name}'></div>";
            echo "</div>";
            echo "</a>";
        }
        echo "</div></div>";
        echo "<p align='right'>Personajes (Conducta): " . count($demeanorOwners) . "</p>";
    }
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

$stmtArche->close();
?>
