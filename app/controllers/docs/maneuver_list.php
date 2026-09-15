<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
setMetaFromPage("Maniobras | Heaven's Gate", "Listado de maniobras de combate.", null, 'website');
$pageSect = "Maniobras de combate";
include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet('/assets/css/hg-maneuvers.css');
else echo '<link rel="stylesheet" href="/assets/css/hg-maneuvers.css">';

echo '<h2>Maniobras de combate</h2>';
$maneuvers = hg_rules_fetch_maneuvers($link) ?: [];
$groups = [];
foreach ($maneuvers as $row) {
    $groups[(string)($row['system_name'] ?? '')][] = $row;
}
$numregistros = 0;
foreach ($groups as $systemName => $rows) {
    $safeSystem = htmlspecialchars($systemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<fieldset class='hg-maneuvers-group'><legend><b>{$safeSystem}</b></legend>";
    foreach ($rows as $row) {
        $href = htmlspecialchars(pretty_url($link, 'fact_combat_maneuvers', '/rules/maneuvers', (int)$row['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name = htmlspecialchars((string)$row['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo "<a href='{$href}'><span class='hg-maneuvers-card'>{$name}</span></a>";
        $numregistros++;
    }
    echo '</fieldset>';
}
echo "<p align='right'>Maniobras: {$numregistros}</p>";
?>