<?php
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

if (!function_exists('hg_bio_skills_bucket')) {
    function hg_bio_skills_bucket(string $kindRaw): string {
        $raw = trim($kindRaw);
        // Match exact/known variants first (accent + mojibake variants).
        if ($raw === 'Talentos') return 'Talentos';
        if ($raw === 'Técnicas' || $raw === 'Tecnicas' || $raw === 'TÃ©cnicas' || $raw === 'TÃƒÂ©cnicas') return 'Técnicas';
        if ($raw === 'Conocimientos' || $raw === 'Habilidades') return 'Conocimientos';

        $k = hg_bio_skills_norm($kindRaw);
        if ($k === 'talentos') return 'Talentos';
        if ($k === 'tecnicas') return 'Técnicas';
        if ($k === 'conocimientos') return 'Conocimientos';
        // Compat legacy wording
        if ($k === 'habilidades') return 'Conocimientos';
        return '';
    }
}

$skillsDebugEnabled = (isset($_GET['debug']) && (string)$_GET['debug'] === '1');

$cid = isset($characterId) ? (int)$characterId : (int)($_GET['b'] ?? 0);
$sid = isset($bioSystemId) ? (int)$bioSystemId : 0;

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
$debugRows = [];

if ($cid > 0 && $sid > 0) {
    $sql = "
        SELECT
            t.id,
            t.name,
            t.kind,
            t.classification,
            s.sort_order,
            COALESCE(b.value, 0) AS value
        FROM fact_trait_sets s
        INNER JOIN dim_traits t ON t.id = s.trait_id
        LEFT JOIN bridge_characters_traits b
            ON b.trait_id = t.id
           AND b.character_id = ?
        WHERE s.system_id = ?
          AND s.is_active = 1
        ORDER BY s.sort_order ASC, t.name ASC
    ";

    if ($st = $link->prepare($sql)) {
        $st->bind_param('ii', $cid, $sid);
        $st->execute();
        if ($rs = $st->get_result()) {
            while ($r = $rs->fetch_assoc()) {
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

                $bucket = hg_bio_skills_bucket((string)$row['kind']);
                if ($bucket === '') continue;

                $skillsByCol[$bucket][] = $row;
                $debugRows[] = $row + ['bucket' => $bucket];
            }
            $rs->free();
        }
        $st->close();
    }
}

$tal = $skillsByCol['Talentos'];
$tec = $skillsByCol['Técnicas'];
$con = $skillsByCol['Conocimientos'];

// Secondary skills: traits present in character but outside active set for this system.
if ($cid > 0 && $sid > 0) {
    $sqlSecondary = "
        SELECT
            t.id,
            t.name,
            t.kind,
            t.classification,
            COALESCE(b.value, 0) AS value
        FROM bridge_characters_traits b
        INNER JOIN dim_traits t ON t.id = b.trait_id
        WHERE b.character_id = ?
          AND b.value > 0
          AND NOT EXISTS (
              SELECT 1
              FROM fact_trait_sets s
              WHERE s.system_id = ?
                AND s.trait_id = t.id
                AND s.is_active = 1
          )
        ORDER BY t.name ASC
    ";

    if ($stSec = $link->prepare($sqlSecondary)) {
        $stSec->bind_param('ii', $cid, $sid);
        $stSec->execute();
        if ($rsSec = $stSec->get_result()) {
            while ($r = $rsSec->fetch_assoc()) {
                $row = [
                    'id' => (int)($r['id'] ?? 0),
                    'name' => (string)($r['name'] ?? ''),
                    'value' => (int)($r['value'] ?? 0),
                    'kind' => (string)($r['kind'] ?? ''),
                    'classification' => (string)($r['classification'] ?? ''),
                    'sort_order' => 999999,
                ];
                $bucket = hg_bio_skills_bucket((string)$row['kind']);
                if ($bucket === '') continue;
                $secondaryByCol[$bucket][] = $row;
                if ($skillsDebugEnabled) {
                    $debugRows[] = $row + ['bucket' => $bucket, 'secondary' => 1];
                }
            }
            $rsSec->free();
        }
        $stSec->close();
    }
}

$talSec = $secondaryByCol['Talentos'];
$tecSec = $secondaryByCol['Técnicas'];
$conSec = $secondaryByCol['Conocimientos'];

echo "<div class='bioSheetData'>"; // Habilidades de la Hoja ~~ #SEC06
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
echo "</div>"; // Cerramos Habilidades ~~

$talSecImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $talSec), 'gem-attr');
$tecSecImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $tecSec), 'gem-attr');
$conSecImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $conSec), 'gem-attr');

$maxSecRows = max(count($talSec), count($tecSec), count($conSec));
if ($maxSecRows > 0) {
    echo "<div class='bioSheetData'>"; // Habilidades secundarias de la Hoja ~~ #SEC06
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
    echo "</div>"; // Cerramos Habilidades ~~
}
?>
