<?php
if (!table_exists($link, 'bridge_characters_conditions') || !table_exists($link, 'dim_character_conditions')) {
    return;
}

$hasConditionInstanceNo = column_exists($link, 'bridge_characters_conditions', 'instance_no');
$hasConditionLocation = column_exists($link, 'bridge_characters_conditions', 'location');
$hasConditionActive = column_exists($link, 'bridge_characters_conditions', 'is_active');
$conditionInstanceSelect = $hasConditionInstanceNo ? 'bcc.instance_no' : '1';
$conditionLocationSelect = $hasConditionLocation ? 'bcc.location' : 'NULL';
$conditionActiveWhere = $hasConditionActive ? "AND (bcc.is_active = 1 OR bcc.is_active IS NULL)" : "";

$sql = "
    SELECT
        c.id,
        c.pretty_id,
        c.name,
        c.category,
        {$conditionInstanceSelect} AS instance_no,
        {$conditionLocationSelect} AS condition_location
    FROM bridge_characters_conditions bcc
    JOIN dim_character_conditions c ON c.id = bcc.condition_id
    WHERE bcc.character_id = ?
      {$conditionActiveWhere}
    ORDER BY
        CASE
            WHEN c.category = 'Deformidad Metis' THEN 0
            WHEN c.category = 'Herida de Guerra' THEN 1
            WHEN c.category LIKE '%Cicatrices%' THEN 1
            WHEN c.category = 'Trastorno Mental' THEN 2
            ELSE 9999
        END ASC,
        c.name ASC,
        instance_no ASC
";

$stmt = $link->prepare($sql);
if (!$stmt) {
    return;
}

$stmt->bind_param('i', $characterId);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    echo "<div class='bioSheetPowers'>";
    echo "<fieldset class='bioSeccion'><legend>{$titleConditions}</legend>";

    while ($row = $result->fetch_assoc()) {
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
            $conditionIcon = 'img/ui/icons/icon_magic_orb.png';
        } elseif (strpos($categoryKey, 'herida') !== false || strpos($categoryKey, 'cicatriz') !== false || strpos($categoryKey, 'batalla') !== false) {
            $conditionIcon = 'img/ui/icons/achievements_001_first_blood.jpg';
        } elseif (strpos($categoryKey, 'deformidad') !== false || strpos($categoryKey, 'metis') !== false) {
            $conditionIcon = 'img/ui/icons/icon_flaw.png';
        } else {
            $conditionIcon = 'img/ui/icons/default.jpg';
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

$stmt->close();
?>
