<?php
// Recursos desde bridge_characters_system_resources + dim_systems_resources.
// Fallback opcional a bridge_characters_resources si en algun entorno existe ese nombre.
echo "<style>
.bio-renown-row{
    display:flex;
    align-items:stretch;
    margin-bottom:2px;
}
.bio-renown-right{
    border:1px solid #009;
    background-color:#000055;
    color:#FFF;
    font-size:9px;
    text-align:left;
    width:136px;
    min-height:28px;
    height:auto;
    padding:2px;
    margin-bottom:0;
    box-sizing:border-box;
}
.bio-renown-left{
    border:1px solid #009;
    background-color:#000066;
    color:cyan;
    font-size:9px;
    text-align:right;
    width:122px;
    min-height:28px;
    height:auto;
    padding:2px;
    margin-bottom:0;
    box-sizing:border-box;
    display:flex;
    align-items:center;
    justify-content:flex-end;
}
.bio-renown-line{ display:flex; align-items:center; gap:4px; line-height:1.1; }
.bio-renown-line + .bio-renown-line{ margin-top:2px; }
.bio-renown-tag{ min-width:10px; color:#9fd; font-weight:bold; font-size:8px; }
</style>";

$resourcesByKind = [
    'renombre' => [],
    'estado' => [],
    'exp' => [],
];

$bridgeTable = null;
if (isset($link) && $link instanceof mysqli) {
    foreach (['bridge_characters_system_resources', 'bridge_characters_resources'] as $candidate) {
        if ($stChk = $link->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
        ")) {
            $stChk->bind_param('s', $candidate);
            $stChk->execute();
            $stChk->bind_result($countTbl);
            $stChk->fetch();
            $stChk->close();
            if ((int)$countTbl > 0) {
                $bridgeTable = $candidate;
                break;
            }
        }
    }
}

if ($bridgeTable && !empty($characterId) && isset($link) && $link instanceof mysqli) {
    $sqlRes = "
        SELECT r.name, r.kind, r.sort_order, b.value_permanent, b.value_temporary
        FROM `$bridgeTable` b
        INNER JOIN dim_systems_resources r ON r.id = b.resource_id
        WHERE b.character_id = ?
        ORDER BY r.kind, r.sort_order, r.name
    ";
    if ($stRes = $link->prepare($sqlRes)) {
        $stRes->bind_param('i', $characterId);
        $stRes->execute();
        $rsRes = $stRes->get_result();
        while ($rsRes && ($row = $rsRes->fetch_assoc())) {
            $kind = strtolower((string)($row['kind'] ?? ''));
            if (!isset($resourcesByKind[$kind])) $resourcesByKind[$kind] = [];
            $resourcesByKind[$kind][] = [
                'name' => (string)($row['name'] ?? ''),
                'perm' => (int)($row['value_permanent'] ?? 0),
                'temp' => (int)($row['value_temporary'] ?? 0),
            ];
        }
        $stRes->close();
    }
}

$renderPwrPip = function(int $value): string {
    if ($value >= 0 && $value <= 10) {
        $img = createSkillCircle([$value], 'gem-pwr');
        return $img[0] ?? h((string)$value);
    }
    return h((string)$value);
};

$renderEstadoValue = function(array $res) use ($renderPwrPip): string {
    $perm = (int)($res['perm'] ?? 0);
    $temp = (int)($res['temp'] ?? 0);
    $html = $renderPwrPip($temp);
    if ($temp !== $perm) {
        $html .= " <span style='font-size:10px;color:#9fd;'>(" . h((string)$temp) . "/" . h((string)$perm) . ")</span>";
    }
    return $html;
};

echo "<div class='bioSheetSociaWhole'>"; // Caja de la Seccion SOCIAL y VENTAJAS
    echo "<div class='bioSheetSocialPower'>"; // Datos Sociales de la Hoja ~~ #SEC09
    echo "<fieldset class='bioSeccion'><legend>$titleSocial</legend>";
        foreach (($resourcesByKind['renombre'] ?? []) as $res) {
            $nm = (string)($res['name'] ?? '');
            if ($nm === '') continue;
            $perm = (int)($res['perm'] ?? 0);
            $temp = (int)($res['temp'] ?? 0);
            echo "<div class='bio-renown-row'>";
            echo "<div class='bio-renown-left'>" . h($nm) . ":</div>";
            echo "<div class='bio-renown-right'>";
            echo "<div class='bio-renown-line'><span class='bio-renown-tag'>P</span><span>" . $renderPwrPip($perm) . "</span></div>";
            echo "<div class='bio-renown-line'><span class='bio-renown-tag'>T</span><span>" . $renderPwrPip($temp) . "</span></div>";
            echo "</div>";
            echo "</div>";
        }
        if ($bioRange != "") { // Rango del Personaje
            echo "<div class='bio-renown-row'>";
            echo "<div class='bio-renown-left'>Rango:</div>";
            echo "<div class='bio-renown-right' style='display:flex;align-items:center;justify-content:center;'>" . h($bioRange) . "</div>";
            echo "</div>";
        }
    echo "</fieldset>";
    echo "</div>"; // Cerramos Datos Sociales ~~

    echo "<div class='bioSheetSocialPower'>"; // Fuerza de Voluntad y demas de la Hoja ~~ #SEC10
    echo "<fieldset class='bioSeccion'><legend>$titleAdvant</legend>";
        foreach (($resourcesByKind['estado'] ?? []) as $res) {
            $nm = (string)($res['name'] ?? '');
            if ($nm === '') continue;
            echo "<div class='bioSheetSocialPowerLeft'>" . h($nm) . ":</div>";
            echo "<div class='bioSheetSocialPowerRight'>" . $renderEstadoValue($res) . "</div>";
        }
        foreach (($resourcesByKind['exp'] ?? []) as $res) {
            $nm = (string)($res['name'] ?? '');
            if ($nm === '') continue;
            $temp = (int)($res['temp'] ?? 0);
            $perm = (int)($res['perm'] ?? 0);
            $txt = h((string)$temp) . " / " . h((string)$perm) . " PX";
            echo "<div class='bioSheetSocialPowerLeft'>" . h($nm) . ":</div>";
            echo "<div class='bioSheetSocialPowerRight'>" . $txt . "</div>";
        }
    echo "</fieldset>";
    echo "</div>"; // Cerramos Fuerza de Voluntad y demas~~
echo "</div>"; // Caja de la Seccion SOCIAL y VENTAJAS
?>
