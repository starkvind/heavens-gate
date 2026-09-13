<?php
require_once(__DIR__ . '/../../domains/characters/queries.php');

$merits = hg_characters_fetch_merits_flaws($link, (int)$characterId);

echo "<div class='bioSheetMeritFlaws'>";
echo "<fieldset class='bioSeccion'><legend>$titleMerits</legend>";

if (empty($merits)) {
    echo "<p class='bio-empty-note'>Este personaje no posee Meritos o Defectos</p>";
} else {
    foreach ($merits as $row) {
        $meritId = (int)$row['id'];
        $nameMerit = htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8');
        $typeMeritRaw = (string)($row['kind'] ?? '');
        $costMerit = $row['cost'];
        $lvlMerit = $row['level'];

        $labelNivel = ($lvlMerit !== null) ? $lvlMerit : $costMerit;

        // Normaliza el tipo para evitar problemas de encoding.
        $kindKey = strtolower(trim($typeMeritRaw));
        if (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $kindKey);
            if ($tmp !== false) $kindKey = strtolower($tmp);
        }
        $kindKey = preg_replace('/[^a-z]+/', '', $kindKey);

        if ($kindKey === 'mritos' || strpos($kindKey, 'merit') !== false) {
            $meritIcon = "img/ui/icons/icon_merit.webp";
        } elseif ($kindKey === 'defectos' || strpos($kindKey, 'defect') !== false || strpos($kindKey, 'flaw') !== false) {
            $meritIcon = "img/ui/icons/icon_flaw.webp";
        } else {
            $meritIcon = "img/ui/icons/default.webp";
        }

        echo "
            <a href='/rules/merits-flaws/{$meritId}' target='_blank' class='hg-tooltip' data-tip='merit' data-id='{$meritId}'>
                <div class='bioSheetMeritFlaw'>
                    <img class='valign bio-inline-icon' src='{$meritIcon}' alt='Tipo de merito'>
                    {$nameMerit}
                    <div class='bio-inline-level'>{$labelNivel}</div>
                </div>
              </a>
        ";
    }
}

echo "</fieldset>";
echo "</div>";
?>
