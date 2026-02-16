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
        if ($name === '') continue;
        $img = $imgs[$c][$i] ?? '';
        echo "<div class='bioSheetAttrLeft'>{$name}:</div>";
        echo "<div class='bioSheetAttrRight'>{$img}</div>";
    }
}
?>
