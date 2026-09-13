<?php
require_once(__DIR__ . '/../../domains/characters/queries.php');

$items = hg_characters_fetch_items($link, (int)$characterId);

if (!empty($items)) {
    echo "<div class='bioSheetPowers'>";
    echo "<fieldset class='bioSeccion'><legend>$titleItems</legend>";

    foreach ($items as $row) {
        $itemIdSelect = (int)($row['id'] ?? 0);
        $nombreItemSelect = htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tipoItemSelect = (int)($row['item_type_id'] ?? 0);

        switch ($tipoItemSelect) {
            case 1:
                $iconoItemSelect = 'img/ui/icons/icon_machete.webp';
                break;
            case 2:
                $iconoItemSelect = 'img/ui/icons/icon_kevlar.webp';
                break;
            case 3:
                $iconoItemSelect = 'img/ui/icons/icon_magic_orb.webp';
                break;
            case 4:
                $iconoItemSelect = 'img/ui/icons/icon_crate.webp';
                break;
            case 5:
                $iconoItemSelect = 'img/ui/icons/icon_amulet.webp';
                break;
            default:
                $iconoItemSelect = 'img/ui/icons/default.webp';
                break;
        }

        $typeSlug = trim((string)($row['type_pretty'] ?? ''));
        if ($typeSlug === '') $typeSlug = (string)$tipoItemSelect;
        $itemSlug = trim((string)($row['item_pretty'] ?? ''));
        if ($itemSlug === '') $itemSlug = (string)$itemIdSelect;
        $href = '/inventory/' . rawurlencode($typeSlug) . '/' . rawurlencode($itemSlug);

        echo "
            <a href='" . h($href) . "' target='_blank' class='hg-tooltip' data-tip='item' data-id='{$itemIdSelect}'>
                <div class='bioSheetPower'>
                    <img class='valign bio-inline-icon' src='{$iconoItemSelect}'>
                    {$nombreItemSelect}
                </div>
            </a>
        ";
    }

    echo "</fieldset>";
    echo "</div>";
}
?>
