<?php
setMetaFromPage("Biografías por realidad | Heaven's Gate", "Listado de personajes agrupados por realidad.", null, 'website');
?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/pages/legacy/controllers-bio-bio_worlds.css'); } else { ?><link rel="stylesheet" href="/assets/css/pages/legacy/controllers-bio-bio_worlds.css"><?php } ?>

<?php
include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
require_once(__DIR__ . '/../../domains/characters/world_queries.php');
if (!$link) {
    hg_public_log_error('bio_worlds', 'missing DB connection');
    hg_public_render_error('Biografías no disponibles', 'No se pudo cargar el listado por realidad en este momento.');
    return;
}

if (!function_exists('hg_bwr_h')) {
    function hg_bwr_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$realityFilterId = (int)hg_request_param($hgRequest, 'world');
include("app/partials/main_nav_bar.php");
echo "<h2>Biografías por realidad</h2>";

if (!hg_character_worlds_schema_ready($link)) {
    echo "<p class='texti'>No existe el esquema de realidades en BDD (dim_realities / fact_characters.reality_id).</p>";
    return;
}

$rows = hg_character_worlds_fetch($link, $realityFilterId, $excludeChronicles ?? '');
if ($rows === null) {
    hg_public_log_error('bio_worlds', 'query failed: ' . mysqli_error($link));
    echo "<p class='texti'>No se pudo cargar la lista de personajes.</p>";
    return;
}

$groups = [];
$countAll = 0;
foreach ($rows as $row) {
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
        $imgPJ = (string)hg_character_avatar_url($rowPJ['image_url'] ?? '', $rowPJ['gender'] ?? '');
        $claseRaw = (string)($rowPJ['character_kind'] ?? '');
        $estadoPJ = hg_bwr_h($rowPJ['status'] ?? '');

        if ($aliasPJ === '') $aliasPJ = $nombrePJ;

        $hrefPJ = pretty_url($link, 'fact_characters', '/characters', $idPJ);
        hg_render_character_avatar_tile([
            'href' => $hrefPJ,
            'title' => html_entity_decode($nombrePJ, ENT_QUOTES, 'UTF-8'),
            'name' => html_entity_decode($nombrePJ, ENT_QUOTES, 'UTF-8'),
            'alias' => html_entity_decode($aliasPJ, ENT_QUOTES, 'UTF-8'),
            'character_id' => $idPJ,
            'avatar_url' => $imgPJ,
            'status' => html_entity_decode($estadoPJ, ENT_QUOTES, 'UTF-8'),
            'character_kind' => $claseRaw,
        ]);
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
