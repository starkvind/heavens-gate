<?php
require_once(__DIR__ . '/../../domains/characters/queries.php');

$conditions = hg_characters_fetch_conditions($link, (int)$characterId);

if (!empty($conditions)) {
    echo "<div class='bioSheetPowers'>";
    echo "<fieldset class='bioSeccion'><legend>{$titleConditions}</legend>";

    foreach ($conditions as $row) {
        $conditionId = (int)($row['id'] ?? 0);
        $conditionName = (string)($row['name'] ?? '');
        $conditionCategory = (string)($row['category'] ?? '');
        $conditionLocation = trim((string)($row['condition_location'] ?? ''));
        $instanceNo = (int)($row['instance_no'] ?? 1);

        $categoryKey = strtolower(trim($conditionCategory));
        if (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $categoryKey);
            if ($tmp !== false) {
                $categoryKey = strtolower($tmp);
            }
        }

        if (strpos($categoryKey, 'trastorno') !== false || strpos($categoryKey, 'mental') !== false) {
            $conditionIcon = 'img/ui/icons/icon_magic_orb.webp';
        } elseif (strpos($categoryKey, 'herida') !== false || strpos($categoryKey, 'cicatriz') !== false || strpos($categoryKey, 'batalla') !== false) {
            $conditionIcon = 'img/ui/icons/achievements_001_first_blood.webp';
        } elseif (strpos($categoryKey, 'deformidad') !== false || strpos($categoryKey, 'metis') !== false) {
            $conditionIcon = 'img/ui/icons/icon_flaw.webp';
        } else {
            $conditionIcon = 'img/ui/icons/default.webp';
        }

        $label = $conditionName;
        if ($conditionLocation !== '') {
            $label .= ' (' . $conditionLocation . ')';
        } elseif ($instanceNo > 1) {
            $label .= ' #' . $instanceNo;
        }

        $slug = (string)($row['pretty_id'] ?? '');
        if ($slug === '') {
            $slug = (string)$conditionId;
        }
        $href = '/rules/conditions/' . rawurlencode($slug);

        echo "
            <a href='" . h($href) . "' target='_blank' class='hg-tooltip' data-tip='condition' data-id='" . (int)$conditionId . "'>
                <div class='bioSheetPower'>
                    <img class='valign bio-inline-icon' src='" . h($conditionIcon) . "' alt='Condicion'>
                    " . h($label) . "
                </div>
            </a>
        ";
    }

    echo "</fieldset>";
    echo "</div>";
}
?>
