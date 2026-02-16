<?php
// Tooltip endpoint (HTML fragment)
header('Content-Type: text/html; charset=UTF-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function short_text($html, $limit=520){
    $txt = trim(strip_tags((string)$html));
    if ($txt === '') return '';
    if (function_exists('mb_substr')) {
        if (mb_strlen($txt,'UTF-8') > $limit) return mb_substr($txt,0,$limit,'UTF-8') . '...';
        return $txt;
    }
    if (strlen($txt) > $limit) return substr($txt,0,$limit) . '...';
    return $txt;
}

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
if (!$link || $id <= 0) { echo '<div class="hg-tip">No disponible</div>'; exit; }

$outTitle = '';
$outMeta = '';
$outSystem = '';
$outDesc = '';
$outImg = '';
$outImgAlt = '';
$outExtraLabel = '';
$outExtra = '';

if ($type === 'don') {
    if ($st = $link->prepare("SELECT name, rank, shifter_system_name, system_name, description FROM fact_gifts WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $rango = $r['rank'] ?? '';
            $fera = $r['shifter_system_name'] ?? '';
            $outMeta = "Rango " . h($rango);
            if ($fera !== '') $outMeta .= " - " . h($fera);
            $outSystem = short_text($r['system_name'] ?? '');
            $outDesc = short_text($r['description'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'rite') {
    if ($st = $link->prepare("SELECT name, level, race, system_name, description AS descr FROM fact_rites WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $nivel = $r['level'] ?? '';
            $raza = $r['race'] ?? '';
            $outMeta = "Nivel " . h($nivel);
            if ($raza !== '') $outMeta .= " - " . h($raza);
            $outSystem = short_text($r['system_name'] ?? '');
            $outDesc = short_text($r['descr'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'merit') {
    if ($st = $link->prepare("SELECT name, kind, cost, affiliation, system_name, description FROM dim_merits_flaws WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $tipo = $r['kind'] ?? '';
            $coste = $r['cost'] ?? '';
            $afil = $r['affiliation'] ?? '';
            $outMeta = h($tipo);
            if ($coste !== '') $outMeta .= " - Coste " . h($coste);
            if ($afil !== '') $outMeta .= " - " . h($afil);
            $outSystem = short_text($r['system_name'] ?? '');
            $outDesc = short_text($r['description'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'item') {
    if ($st = $link->prepare("SELECT name, item_type_id, level, gnosis, description, img, habilidad, dano, bonus, metal FROM fact_items WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $tipo = (int)($r['item_type_id'] ?? 0);
            $nivel = $r['level'] ?? '';
            $gnosis = $r['gnosis'] ?? '';
            $habilidad = (string)($r['habilidad'] ?? '');
            $dano = (string)($r['dano'] ?? '');
            $bonus = (int)($r['bonus'] ?? 0);
            $metal = (int)($r['metal'] ?? 0);
            $mapTipo = [
                1=>'Arma', 2=>'Protector', 3=>'Objeto m&aacute;gico', 4=>'Objeto', 5=>'Amuleto'
            ];
            $outMeta = $mapTipo[$tipo] ?? 'Objeto';
            if ($nivel !== '' && (int)$nivel > 0) $outMeta .= " - Nivel " . h($nivel);
            if ($gnosis !== '' && (int)$gnosis > 0) $outMeta .= " - Gnosis " . h($gnosis);

            $extraMeta = '';
            if ($tipo === 1 && $dano !== '') {
                $metalText = '';
                if ($metal === 1) $metalText = " y de plata";
                if ($metal === 2) $metalText = " y de oro";
                switch ($habilidad) {
                    case "Cuerpo a Cuerpo":
                    case "Pelea":
                    case "Arrojar":
                        $damageText = "Fuerza + " . $bonus;
                        break;
                    default:
                        $damageText = $bonus . " dados";
                        break;
                }
                $extraMeta = "Da&ntilde;o " . h($damageText) . ", " . h($dano) . $metalText;
            } elseif ($tipo === 2 && $bonus !== 0) {
                $extraMeta = "Protecci&oacute;n +" . h($bonus);
            }
            if ($extraMeta !== '') $outMeta .= " - " . $extraMeta;

            $outDesc = short_text($r['description'] ?? '', 360);
            $outImg = (string)($r['img'] ?? '');
            $outImgAlt = $outTitle;
        }
        $st->close();
    }
}

if ($outTitle === '') { echo '<div class="hg-tip">No disponible</div>'; exit; }

echo "<div class='hg-tip' style='display:flex; gap:8px; align-items:flex-start;'>";
	if ($outImg !== '') {
		$imgSrc = h($outImg);
		echo "<div class='hg-tip-media'><img src=\"{$imgSrc}\" alt=\"" . h($outImgAlt) . "\" style=\"width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #003399;\"></div>";
	}
	echo "<div class='hg-tip-body'>";
	echo "<div class='hg-tip-title'>" . h($outTitle) . "</div>";
	if ($outMeta !== '') echo "<div class='hg-tip-meta'>" . $outMeta . "</div>";
	if ($outExtra !== '') {
		echo "<div class='hg-tip-label'>" . h($outExtraLabel) . "</div>";
		echo "<div class='hg-tip-text'>" . h($outExtra) . "</div>";
	}
	if ($outDesc !== '') {
		echo "<div class='hg-tip-label'>Descripci&oacute;n</div>";
		echo "<div class='hg-tip-text'>" . h($outDesc) . "</div>";
	}
	if ($outSystem !== '') {
		echo "<div class='hg-tip-label'>Sistema</div>";
		echo "<div class='hg-tip-text'>" . h($outSystem) . "</div>";
	}
	echo "</div>";
echo "</div>";
?>

