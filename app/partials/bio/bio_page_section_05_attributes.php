<?php
// Atributos en 3 columnas (1-3, 4-6, 7-9)
$cols = $bioAttrCols ?? [[],[],[]];
$imgs = $bioAttrColImgs ?? [[],[],[]];
$maxRows = 0;
foreach ($cols as $c) { $maxRows = max($maxRows, count($c)); }
for ($i = 0; $i < $maxRows; $i++) {
    for ($c = 0; $c < 3; $c++) {
        if (!isset($cols[$c][$i])) continue;
        $t = $cols[$c][$i];
        $name = h($t['name'] ?? '');
        $tid = (int)($t['id'] ?? 0);
        if ($name === '') continue;
        $img = $imgs[$c][$i] ?? '';
        if ($tid > 0 && function_exists('pretty_url')) {
            $href = pretty_url($link, 'dim_traits', '/rules/traits', $tid);
            $nameHtml = "<a href='" . h($href) . "' target='_blank' class='hg-tooltip' data-tip='trait' data-id='" . $tid . "'>{$name}</a>";
        } else {
            $nameHtml = $name;
        }
        echo "<div class='bioSheetAttrLeft'>{$nameHtml}:</div>";
        echo "<div class='bioSheetAttrRight'>{$img}</div>";
    }
}
?>
