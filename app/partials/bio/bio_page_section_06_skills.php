<?php
// NUEVO (bridge_characters_traits)
// Habilidades por columnas: Talentos, Técnicas, Conocimientos
echo "<style>
.bioSkillNameCell{
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bioSheetAttrLeft a.hg-tooltip{ color:cyan; text-decoration:none; }
.bioSheetAttrLeft a.hg-tooltip:hover{ color:#33FFFF; text-decoration:underline; }
</style>";
$tal = array_values(array_filter($bioSkillCols['Talentos'] ?? [], function($t){
    return isset($t['name']) && $t['name'] !== '';
}));
$tec = array_values(array_filter($bioSkillCols['Técnicas'] ?? [], function($t){
    return isset($t['name']) && $t['name'] !== '';
}));
$con = array_values(array_filter($bioSkillCols['Conocimientos'] ?? [], function($t){
    return isset($t['name']) && $t['name'] !== '';
}));

function norm_skill_name(string $s): string {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    if (function_exists('iconv')) {
        $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    }
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

function order_skill_column(array $list, array $orderSpec): array {
    $byName = [];
    foreach ($list as $item) {
        $name = (string)($item['name'] ?? '');
        if ($name === '') continue;
        $byName[norm_skill_name($name)] = $item;
    }

    $ordered = [];
    $used = [];
    foreach ($orderSpec as $slot) {
        $candidates = is_array($slot) ? $slot : [$slot];
        $picked = null;
        foreach ($candidates as $cand) {
            $key = norm_skill_name($cand);
            if (isset($byName[$key])) { $picked = $byName[$key]; break; }
        }
        if ($picked) {
            $ordered[] = $picked;
            $used[norm_skill_name($picked['name'])] = true;
        }
    }

    $extras = [];
    foreach ($list as $item) {
        $key = norm_skill_name((string)($item['name'] ?? ''));
        if ($key === '' || isset($used[$key])) continue;
        $extras[] = $item;
    }
    usort($extras, function($a, $b){
        $an = norm_skill_name((string)($a['name'] ?? ''));
        $bn = norm_skill_name((string)($b['name'] ?? ''));
        return $an <=> $bn;
    });

    return array_merge($ordered, $extras);
}

$talOrder = [
    'Alerta','Atletismo','Callejeo','Empatía','Esquivar','Expresión',
    'Impulso Primario','Intimidación','Pelea','Subterfugio',
];
$tecOrder = [
    'Armas Cuerpo a Cuerpo','Armas de Fuego','Conducir','Etiqueta','Interpretación',
    'Liderazgo',['Reparaciones','Pericias'],'Sigilo','Supervivencia','Trato con Animales',
];
$conOrder = [
    'Academicismo','Ciencias','Enigmas','Informática','Investigación',
    'Leyes','Medicina','Ocultismo','Política','Rituales',
];

$tal = order_skill_column($tal, $talOrder);
$tec = order_skill_column($tec, $tecOrder);
$con = order_skill_column($con, $conOrder);

$talImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $tal), 'gem-attr');
$tecImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $tec), 'gem-attr');
$conImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $con), 'gem-attr');

$maxRows = max(count($tal), count($tec), count($con));
for ($i = 0; $i < $maxRows; $i++) {
    if (isset($tal[$i])) {
        $rawName = (string)($tal[$i]['name'] ?? '');
        $name = h($rawName);
        $tid = (int)($tal[$i]['id'] ?? 0);
        if ($name !== '') {
            $img = $talImg[$i] ?? '';
            if ($tid > 0 && function_exists('pretty_url')) {
                $href = pretty_url($link, 'dim_traits', '/rules/traits', $tid);
                $nameHtml = "<a href='" . h($href) . "' target='_blank' class='hg-tooltip' data-tip='trait' data-id='" . $tid . "'>{$name}</a>";
            } else {
                $nameHtml = $name;
            }
            echo "<div class='bioSheetAttrLeft bioSkillNameCell'>{$nameHtml}:</div>";
            echo "<div class='bioSheetAttrRight'>{$img}</div>";
        }
    }
    if (isset($tec[$i])) {
        $rawName = (string)($tec[$i]['name'] ?? '');
        $name = h($rawName);
        $tid = (int)($tec[$i]['id'] ?? 0);
        if ($name !== '') {
            $img = $tecImg[$i] ?? '';
            if ($tid > 0 && function_exists('pretty_url')) {
                $href = pretty_url($link, 'dim_traits', '/rules/traits', $tid);
                $nameHtml = "<a href='" . h($href) . "' target='_blank' class='hg-tooltip' data-tip='trait' data-id='" . $tid . "'>{$name}</a>";
            } else {
                $nameHtml = $name;
            }
            echo "<div class='bioSheetAttrLeft bioSkillNameCell'>{$nameHtml}:</div>";
            echo "<div class='bioSheetAttrRight'>{$img}</div>";
        }
    }
    if (isset($con[$i])) {
        $rawName = (string)($con[$i]['name'] ?? '');
        $name = h($rawName);
        $tid = (int)($con[$i]['id'] ?? 0);
        if ($name !== '') {
            $img = $conImg[$i] ?? '';
            if ($tid > 0 && function_exists('pretty_url')) {
                $href = pretty_url($link, 'dim_traits', '/rules/traits', $tid);
                $nameHtml = "<a href='" . h($href) . "' target='_blank' class='hg-tooltip' data-tip='trait' data-id='" . $tid . "'>{$name}</a>";
            } else {
                $nameHtml = $name;
            }
            echo "<div class='bioSheetAttrLeft bioSkillNameCell'>{$nameHtml}:</div>";
            echo "<div class='bioSheetAttrRight'>{$img}</div>";
        }
    }
}
// Legacy vars guard (para evitar notices)
$talentoExtras = $talentoExtras ?? [];
$tecnicaExtras = $tecnicaExtras ?? [];
$conociExtras  = $conociExtras ?? [];
$bioExtraTalImg = $bioExtraTalImg ?? [];
$bioExtraTecImg = $bioExtraTecImg ?? [];
$bioExtraConImg = $bioExtraConImg ?? [];
$bioTale1N = $bioTale1N ?? '';
$bioTale2N = $bioTale2N ?? '';
$bioTecn1N = $bioTecn1N ?? '';
$bioTecn2N = $bioTecn2N ?? '';
$bioCono1N = $bioCono1N ?? '';
$bioCono2N = $bioCono2N ?? '';

if (!isset($bioSkilImg)) {
    $legacyVals = $bioArraySkiLegacy ?? $bioArraySki ?? array_fill(0, 36, 0);
    if (!is_array($legacyVals)) $legacyVals = array_fill(0, 36, 0);
    // asegurar 36 posiciones
    $legacyVals = array_values($legacyVals);
    for ($i = count($legacyVals); $i < 36; $i++) { $legacyVals[$i] = 0; }
    $bioSkilImg = createSkillCircle($legacyVals, 'gem-attr');
}

// LEGACY (backup)
// echo "<div style='clear:both; height:8px;'></div>";
// echo "<div class='bioSheetSectionLeft' style='width:100%; text-align:left; color:#9ff;'>Legacy</div>";
// include __DIR__ . '/bio_page_section_06_skills.php.bak';
?>
