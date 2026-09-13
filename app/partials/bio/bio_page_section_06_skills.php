<?php
require_once(__DIR__ . '/../../domains/characters/queries.php');

// Skills section ordered by fact_trait_sets.sort_order.
// Fixed 3 columns: Talentos, Tecnicas, Conocimientos.

if (!function_exists('hg_bio_skills_norm')) {
    function hg_bio_skills_norm(string $s): string {
        $s = trim(mb_strtolower($s, 'UTF-8'));
        if (function_exists('iconv')) {
            $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        }
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    }
}

if (!function_exists('hg_bio_skills_fix_mojibake')) {
    function hg_bio_skills_fix_mojibake(string $s): string {
        if (!function_exists('mb_convert_encoding')) {
            return $s;
        }

        $out = trim($s);
        for ($i = 0; $i < 2; $i++) {
            if (strpos($out, "\xC3\x83") === false && strpos($out, "\xC3\x82") === false) {
                break;
            }
            $fixed = @mb_convert_encoding($out, 'UTF-8', 'ISO-8859-1');
            if (!is_string($fixed) || $fixed === '') {
                break;
            }
            $out = $fixed;
        }

        return $out;
    }
}

if (!function_exists('hg_bio_skills_bucket')) {
    function hg_bio_skills_bucket(string $kindRaw): string {
        $raw = hg_bio_skills_fix_mojibake($kindRaw);
        if ($raw === 'Talentos') return 'Talentos';
        if ($raw === 'Técnicas' || $raw === 'Tecnicas') return 'Técnicas';
        if ($raw === 'Conocimientos' || $raw === 'Habilidades') return 'Conocimientos';

        $k = hg_bio_skills_norm($raw);
        if ($k === 'talentos') return 'Talentos';
        if ($k === 'tecnicas') return 'Técnicas';
        if ($k === 'conocimientos') return 'Conocimientos';
        if ($k === 'habilidades') return 'Conocimientos';
        return '';
    }
}

$cid = isset($characterId) ? (int)$characterId : 0;
$sid = isset($bioSystemId) ? (int)$bioSystemId : 0;
$sheetSkills = hg_characters_fetch_sheet_skills($link, $cid, $sid);

$skillsByCol = [
    'Talentos' => [],
    'Técnicas' => [],
    'Conocimientos' => [],
];
$secondaryByCol = [
    'Talentos' => [],
    'Técnicas' => [],
    'Conocimientos' => [],
];

foreach (($sheetSkills['primary'] ?? []) as $r) {
    $tid = (int)($r['id'] ?? 0);
    if ($tid <= 0) continue;
    $row = [
        'id' => $tid,
        'name' => (string)($r['name'] ?? ''),
        'value' => (int)($r['value'] ?? 0),
        'kind' => (string)($r['kind'] ?? ''),
        'classification' => (string)($r['classification'] ?? ''),
        'sort_order' => (int)($r['sort_order'] ?? 0),
    ];
    $bucket = hg_bio_skills_bucket($row['kind']);
    if ($bucket !== '') $skillsByCol[$bucket][] = $row;
}

foreach (($sheetSkills['secondary'] ?? []) as $r) {
    $tid = (int)($r['id'] ?? 0);
    if ($tid <= 0) continue;
    $row = [
        'id' => $tid,
        'name' => (string)($r['name'] ?? ''),
        'value' => (int)($r['value'] ?? 0),
        'kind' => (string)($r['kind'] ?? ''),
        'classification' => (string)($r['classification'] ?? ''),
        'sort_order' => 999999,
    ];
    $bucket = hg_bio_skills_bucket($row['kind']);
    if ($bucket !== '') $secondaryByCol[$bucket][] = $row;
}

$tal = $skillsByCol['Talentos'];
$tec = $skillsByCol['Técnicas'];
$con = $skillsByCol['Conocimientos'];
$talSec = $secondaryByCol['Talentos'];
$tecSec = $secondaryByCol['Técnicas'];
$conSec = $secondaryByCol['Conocimientos'];

echo "<div class='bioSheetData'>";
echo "<fieldset class='bioSeccion'><legend>$titleSkill</legend>";

$talImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $tal), 'gem-attr');
$tecImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $tec), 'gem-attr');
$conImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $con), 'gem-attr');

$maxRows = max(count($tal), count($tec), count($con));
for ($i = 0; $i < $maxRows; $i++) {
    $cols = [
        ['list' => $tal, 'imgs' => $talImg],
        ['list' => $tec, 'imgs' => $tecImg],
        ['list' => $con, 'imgs' => $conImg],
    ];

    foreach ($cols as $c) {
        if (isset($c['list'][$i])) {
            $row = $c['list'][$i];
            $rawName = (string)($row['name'] ?? '');
            $name = h($rawName);
            $tid = (int)($row['id'] ?? 0);
            $img = $c['imgs'][$i] ?? '';
            if ($tid > 0 && function_exists('pretty_url')) {
                $href = pretty_url($link, 'dim_traits', '/rules/traits', $tid);
                $nameHtml = "<a href='" . h($href) . "' target='_blank' class='hg-tooltip' data-tip='trait' data-id='" . $tid . "'>{$name}</a>";
            } else {
                $nameHtml = $name;
            }
            echo "<div class='bioSheetAttrLeft bioSkillNameCell'>{$nameHtml}:</div>";
            echo "<div class='bioSheetAttrRight'>{$img}</div>";
        } else {
            echo "<div class='bioSheetAttrLeft bioSkillNameCell'>&nbsp;</div>";
            echo "<div class='bioSheetAttrRight'>&nbsp;</div>";
        }
    }
}

echo "</fieldset>";
echo "</div>";

$talSecImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $talSec), 'gem-attr');
$tecSecImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $tecSec), 'gem-attr');
$conSecImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $conSec), 'gem-attr');

$maxSecRows = max(count($talSec), count($tecSec), count($conSec));
if ($maxSecRows > 0) {
    echo "<div class='bioSheetData'>";
    echo "<fieldset class='bioSeccion'><legend>{$titleSkill}secundarias</legend>";
    for ($i = 0; $i < $maxSecRows; $i++) {
        $secCols = [
            ['list' => $talSec, 'imgs' => $talSecImg],
            ['list' => $tecSec, 'imgs' => $tecSecImg],
            ['list' => $conSec, 'imgs' => $conSecImg],
        ];
        foreach ($secCols as $c) {
            if (isset($c['list'][$i])) {
                $row = $c['list'][$i];
                $rawName = (string)($row['name'] ?? '');
                $name = h($rawName);
                $tid = (int)($row['id'] ?? 0);
                $img = $c['imgs'][$i] ?? '';
                if ($tid > 0 && function_exists('pretty_url')) {
                    $href = pretty_url($link, 'dim_traits', '/rules/traits', $tid);
                    $nameHtml = "<a href='" . h($href) . "' target='_blank' class='hg-tooltip' data-tip='trait' data-id='" . $tid . "'>{$name}</a>";
                } else {
                    $nameHtml = $name;
                }
                echo "<div class='bioSheetAttrLeft bioSkillNameCell'>{$nameHtml}:</div>";
                echo "<div class='bioSheetAttrRight'>{$img}</div>";
            } else {
                echo "<div class='bioSheetAttrLeft bioSkillNameCell'>&nbsp;</div>";
                echo "<div class='bioSheetAttrRight'>&nbsp;</div>";
            }
        }
    }
    echo "</fieldset>";
    echo "</div>";
}
?>