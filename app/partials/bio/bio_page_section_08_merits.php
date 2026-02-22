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

    // 👉 SI NO HAY MÉRITOS / DEFECTOS
    if ($result->num_rows === 0) {

        echo "<p style='text-align:center;'>Este personaje no posee Méritos o Defectos</p>";

    } else {

        // 👉 SI HAY MÉRITOS / DEFECTOS
        while ($row = $result->fetch_assoc()) {
            $meritId   = (int)$row['id'];
            $nameMerit = htmlspecialchars($row['name']);
            $typeMerit = htmlspecialchars($row['kind']);
            $costMerit = $row['cost'];
            $lvlMerit  = $row['level'];

            if ($lvlMerit !== null) {
                $labelNivel = $lvlMerit;
            } else {
                $labelNivel = $costMerit;
            }

            switch ($typeMerit) {
                case "Méritos":
                    $meritIcon = "img/ui/icons/icon_merit.png";
                    break;
                case "Defectos":
                    $meritIcon = "img/ui/icons/icon_flaw.png";
                    break;
                default:
                    $meritIcon = "img/ui/icons/default.jpg";
                    break;
            }

            echo "
                <a href='/rules/merits-flaws/{$meritId}' target='_blank' class='hg-tooltip' data-tip='merit' data-id='{$meritId}'>
                    <div class='bioSheetMeritFlaw'>
                        <img class='valign' style='width:13px; height:13px;' src='{$meritIcon}'>
                        {$nameMerit}
                        <div style='float:right;font-size:8px;padding-top:2px;'>{$labelNivel}</div>
                    </div>
                </a>
            ";
        }
    }

    $stmt->close();

    echo "</fieldset>";
    echo "</div>";
?>


