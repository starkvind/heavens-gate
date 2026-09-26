<?php
require_once(__DIR__ . '/../../domains/characters/queries.php');

$isMonsterBio = !empty($bioIsMonster);
$resourcesByKind = hg_characters_fetch_resources($link, (int)$characterId, (int)($bioSystemId ?? 0));

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

echo "<div class='bioSheetSociaWhole'>";
    if (!$isMonsterBio) {
        echo "<div class='bioSheetSocialPower'>";
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
            if ($bioRange != "") {
                echo "<div class='bio-renown-row'>";
                echo "<div class='bio-renown-left'>Rango:</div>";
                echo "<div class='bio-renown-right bio-renown-right-center'>" . h($bioRange) . "</div>";
                echo "</div>";
            }
        echo "</fieldset>";
        echo "</div>";
    }

    echo "<div class='bioSheetSocialPower'>";
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
    echo "</div>";
echo "</div>";
?>
