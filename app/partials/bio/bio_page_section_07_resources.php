<?php
// Recursos desde bridge_characters_system_resources + dim_systems_resources.
// Fallback opcional a bridge_characters_resources si en algun entorno existe ese nombre.
$isMonsterBio = !empty($bioIsMonster);

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
    $systemId = (int)($bioSystemId ?? 0);
    $hasBridgeSysRes = false;
    $hasBridgeSysResSort = false;
    if ($stChkBridge = $link->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bridge_systems_resources_to_system'
    ")) {
        $stChkBridge->execute();
        $stChkBridge->bind_result($cTbl);
        $stChkBridge->fetch();
        $stChkBridge->close();
        $hasBridgeSysRes = ((int)$cTbl > 0);
    }
    if ($hasBridgeSysRes && $stChkCol = $link->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'bridge_systems_resources_to_system'
          AND COLUMN_NAME = 'sort_order'
    ")) {
        $stChkCol->execute();
        $stChkCol->bind_result($cCol);
        $stChkCol->fetch();
        $stChkCol->close();
        $hasBridgeSysResSort = ((int)$cCol > 0);
    }

    $sqlRes = "
        SELECT r.id, r.name, r.kind, r.sort_order, b.value_permanent, b.value_temporary
    ";
    if ($hasBridgeSysRes && $hasBridgeSysResSort && $systemId > 0) {
        $sqlRes .= ", COALESCE(bs.sort_order, r.sort_order, 9999) AS sort_order_eff ";
    } else {
        $sqlRes .= ", COALESCE(r.sort_order, 9999) AS sort_order_eff ";
    }

    $sqlRes .= "
        FROM `$bridgeTable` b
        INNER JOIN dim_systems_resources r ON r.id = b.resource_id
    ";
    if ($hasBridgeSysRes && $hasBridgeSysResSort && $systemId > 0) {
        $sqlRes .= "
        LEFT JOIN bridge_systems_resources_to_system bs
               ON bs.resource_id = r.id
              AND bs.system_id = ?
        ";
    }
    $sqlRes .= "
        WHERE b.character_id = ?
        ORDER BY r.kind, sort_order_eff, r.name
    ";

    if ($stRes = $link->prepare($sqlRes)) {
        if ($hasBridgeSysRes && $hasBridgeSysResSort && $systemId > 0) {
            $stRes->bind_param('ii', $systemId, $characterId);
        } else {
            $stRes->bind_param('i', $characterId);
        }
        $stRes->execute();
        $rsRes = $stRes->get_result();
        while ($rsRes && ($row = $rsRes->fetch_assoc())) {
            $kind = strtolower((string)($row['kind'] ?? ''));
            if (!isset($resourcesByKind[$kind])) $resourcesByKind[$kind] = [];
            $resourcesByKind[$kind][] = [
                'id' => (int)($row['id'] ?? 0),
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
        $html .= " <span class='bio-renown-temp'>(" . h((string)$temp) . "/" . h((string)$perm) . ")</span>";
    }
    return $html;
};

echo "<div class='bioSheetSociaWhole'>"; // Caja de la Seccion SOCIAL y VENTAJAS
    if (!$isMonsterBio) {
        echo "<div class='bioSheetSocialPower'>"; // Datos Sociales de la Hoja ~~ #SEC09
        echo "<fieldset class='bioSeccion'><legend>$titleSocial</legend>";
            foreach (($resourcesByKind['renombre'] ?? []) as $res) {
                $nm = (string)($res['name'] ?? '');
                if ($nm === '') continue;
                $rid = (int)($res['id'] ?? 0);
                $nameHtml = h($nm);
                if ($rid > 0) {
                    $nameHtml = "<span class='hg-tooltip hg-tooltip--help' data-tip='resource' data-id='" . $rid . "'>" . h($nm) . "</span>";
                }
                $perm = (int)($res['perm'] ?? 0);
                $temp = (int)($res['temp'] ?? 0);
                echo "<div class='bio-renown-row'>";
                echo "<div class='bio-renown-left'>" . $nameHtml . ":</div>";
                echo "<div class='bio-renown-right'>";
                echo "<div class='bio-renown-line'><span class='bio-renown-tag'>P</span><span>" . $renderPwrPip($perm) . "</span></div>";
                echo "<div class='bio-renown-line'><span class='bio-renown-tag'>T</span><span>" . $renderPwrPip($temp) . "</span></div>";
                echo "</div>";
                echo "</div>";
            }
            if ($bioRange != "") { // Rango del Personaje
                echo "<div class='bio-renown-row'>";
                echo "<div class='bio-renown-left'>Rango:</div>";
                echo "<div class='bio-renown-right bio-renown-right-center'>" . h($bioRange) . "</div>";
                echo "</div>";
            }
        echo "</fieldset>";
        echo "</div>"; // Cerramos Datos Sociales ~~
    }

    echo "<div class='bioSheetSocialPower'>"; // Fuerza de Voluntad y demas de la Hoja ~~ #SEC10
    echo "<fieldset class='bioSeccion'><legend>$titleAdvant</legend>";
        foreach (($resourcesByKind['estado'] ?? []) as $res) {
            $nm = (string)($res['name'] ?? '');
            if ($nm === '') continue;
            $rid = (int)($res['id'] ?? 0);
            $nameHtml = h($nm);
            if ($rid > 0) {
                $nameHtml = "<span class='hg-tooltip hg-tooltip--help' data-tip='resource' data-id='" . $rid . "'>" . h($nm) . "</span>";
            }
            echo "<div class='bioSheetSocialPowerLeft'>" . $nameHtml . ":</div>";
            echo "<div class='bioSheetSocialPowerRight'>" . $renderEstadoValue($res) . "</div>";
        }
        if (!$isMonsterBio) {
            foreach (($resourcesByKind['exp'] ?? []) as $res) {
                $nm = (string)($res['name'] ?? '');
                if ($nm === '') continue;
                $temp = (int)($res['temp'] ?? 0);
                $perm = (int)($res['perm'] ?? 0);
                $txt = h((string)$temp) . " / " . h((string)$perm) . " PX";
                echo "<div class='bioSheetSocialPowerLeft'>" . h($nm) . ":</div>";
                echo "<div class='bioSheetSocialPowerRight'>" . $txt . "</div>";
            }
        }
    echo "</fieldset>";
    echo "</div>"; // Cerramos Fuerza de Voluntad y demas~~
echo "</div>"; // Caja de la Seccion SOCIAL y VENTAJAS
?>
