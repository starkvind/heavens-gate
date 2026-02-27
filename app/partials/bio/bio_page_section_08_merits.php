<?php
// Consulta directa al bridge
$sql = "
    SELECT
        nmd.id,
        nmd.name,
        nmd.kind,
        nmd.cost,
        b.level
    FROM bridge_characters_merits_flaws b
    JOIN dim_merits_flaws nmd ON nmd.id = b.merit_flaw_id
    WHERE b.character_id = ?
    ORDER BY nmd.kind DESC, nmd.cost, nmd.name
";

$stmt = $link->prepare($sql);
$stmt->bind_param('i', $characterId);
$stmt->execute();
$result = $stmt->get_result();

echo "<div class='bioSheetMeritFlaws'>";
echo "<fieldset class='bioSeccion'><legend>$titleMerits</legend>";

if ($result->num_rows === 0) {
    echo "<p class='bio-empty-note'>Este personaje no posee Meritos o Defectos</p>";
} else {
    while ($row = $result->fetch_assoc()) {
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
            $meritIcon = "img/ui/icons/icon_merit.png";
        } elseif ($kindKey === 'defectos' || strpos($kindKey, 'defect') !== false || strpos($kindKey, 'flaw') !== false) {
            $meritIcon = "img/ui/icons/icon_flaw.png";
        } else {
            $meritIcon = "img/ui/icons/default.jpg";
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

$stmt->close();

echo "</fieldset>";
echo "</div>";
?>
