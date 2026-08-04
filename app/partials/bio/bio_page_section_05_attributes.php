<?php
// Atributos en 3 columnas (1-3, 4-6, 7-9)
$cols = $bioAttrCols ?? [[], [], []];
$imgs = $bioAttrColImgs ?? [[], [], []];
$maxRows = 0;
foreach ($cols as $column) { $maxRows = max($maxRows, count($column)); }

for ($row = 0; $row < $maxRows; $row++) {
    for ($column = 0; $column < 3; $column++) {
        if (!isset($cols[$column][$row])) continue;
        $trait = $cols[$column][$row];
        $name = h($trait['name'] ?? '');
        $traitId = (int)($trait['id'] ?? 0);
        if ($name === '') continue;

        $image = $imgs[$column][$row] ?? '';
        if ($traitId > 0 && function_exists('pretty_url')) {
            $href = pretty_url($link, 'dim_traits', '/rules/traits', $traitId);
            $nameHtml = "<a href='" . h($href) . "' target='_blank' class='hg-tooltip' data-tip='trait' data-id='" . $traitId . "'>{$name}</a>";
        } else {
            $nameHtml = $name;
        }

        $formData = $traitId > 0
            ? " data-bio-form-trait-id='{$traitId}' data-bio-form-base='" . (int)($trait['value'] ?? 0) . "'"
            : '';
        echo "<div class='bioSheetAttrLeft'>{$nameHtml}:</div>";
        echo "<div class='bioSheetAttrRight'{$formData}>{$image}<span class='bio-form-attribute-value' aria-live='polite'></span></div>";
    }
}
?>