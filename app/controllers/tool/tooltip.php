<?php
// Tooltip endpoint (HTML fragment)
header('Content-Type: text/html; charset=UTF-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function short_text($html, $limit=520){
    $raw = (string)$html;
    // Quill suele guardar bloques HTML; convertimos saltos útiles antes de limpiar.
    $raw = preg_replace('/<\s*br\s*\/?>/i', "\n", $raw);
    $raw = preg_replace('/<\s*\/p\s*>/i', "\n", $raw);
    $raw = preg_replace('/<\s*li\s*>/i', " - ", $raw);
    $txt = trim(strip_tags($raw));
    // Evita mostrar entidades tipo &aacute; en bruto en el tooltip.
    $txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = preg_replace('/[ \t]+/', ' ', $txt);
    $txt = preg_replace('/\n{3,}/', "\n\n", $txt);
    if ($txt === '') return '';
    if (function_exists('mb_substr')) {
        if (mb_strlen($txt,'UTF-8') > $limit) return mb_substr($txt,0,$limit,'UTF-8') . '...';
        return $txt;
    }
    if (strlen($txt) > $limit) return substr($txt,0,$limit) . '...';
    return $txt;
}

function tt_has_column(mysqli $link, string $table, string $column): bool {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') return false;
    $rs = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    if (!$rs) return false;
    $ok = (mysqli_num_rows($rs) > 0);
    mysqli_free_result($rs);
    return $ok;
}
function tt_join_meta(array $parts): string {
    $safe = [];
    foreach ($parts as $p) {
        $v = trim((string)$p);
        if ($v !== '') $safe[] = h($v);
    }
    return implode(' - ', $safe);
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
    $giftSystemCol = tt_has_column($link, 'fact_gifts', 'shifter_system_name') ? 'shifter_system_name' : 'system_name';
    $giftRulesCol = tt_has_column($link, 'fact_gifts', 'mechanics_text') ? 'mechanics_text' : 'system_name';
    $sqlDon = "SELECT name, rank, `{$giftSystemCol}` AS gift_system_name, `{$giftRulesCol}` AS gift_rules, description FROM fact_gifts WHERE id=? LIMIT 1";
    if ($st = $link->prepare($sqlDon)) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $rango = $r['rank'] ?? '';
            $fera = $r['gift_system_name'] ?? '';
            $outMeta = "Rango " . h($rango);
            if ($fera !== '') $outMeta .= " - " . h($fera);
            $outSystem = short_text($r['gift_rules'] ?? '');
            $outDesc = short_text($r['description'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'rite') {
    if ($st = $link->prepare("
        SELECT
            r.name,
            r.kind,
            r.level,
            r.race,
            r.system_text,
            r.description AS descr,
            rt.name AS rite_type_name
        FROM fact_rites r
        LEFT JOIN dim_rite_types rt
            ON rt.id = CASE
                WHEN r.kind REGEXP '^[0-9]+$' THEN CAST(r.kind AS UNSIGNED)
                ELSE NULL
            END
        WHERE r.id = ?
        LIMIT 1
    ")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $tipo = trim((string)($r['rite_type_name'] ?? ''));
            if ($tipo === '') {
                $tipo = trim((string)($r['kind'] ?? ''));
            }
            $nivel = $r['level'] ?? '';
            $raza = $r['race'] ?? '';
            $meta = [];
            if ($tipo !== '') $meta[] = $tipo;
            $meta[] = "Nivel " . $nivel;
            if ($raza !== '') $meta[] = $raza;
            $outMeta = tt_join_meta($meta);
            $outSystem = short_text($r['system_text'] ?? '');
            $outDesc = short_text($r['descr'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'merit') {
    if ($st = $link->prepare("SELECT name, kind, cost, affiliation, description FROM dim_merits_flaws WHERE id=? LIMIT 1")) {
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
            $outDesc = short_text($r['description'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'item' || $type === 'items' || $type === 'fact_items') {
    if ($st = $link->prepare("SELECT name, item_type_id, level, gnosis, description, image_url, skill_name, damage_type, bonus, metal FROM fact_items WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $tipo = (int)($r['item_type_id'] ?? 0);
            $nivel = $r['level'] ?? '';
            $gnosis = $r['gnosis'] ?? '';
            $habilidad = (string)($r['skill_name'] ?? '');
            $dano = (string)($r['damage_type'] ?? '');
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
            $outImg = (string)($r['image_url'] ?? '');
            $outImgAlt = $outTitle;
        }
        $st->close();
    }
} elseif ($type === 'trait') {
    if ($st = $link->prepare("SELECT name, kind, description FROM dim_traits WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $kind = (string)($r['kind'] ?? '');
            if ($kind !== '') $outMeta = h($kind);
            $outDesc = short_text($r['description'] ?? '', 320);
        }
        $st->close();
    }
} elseif ($type === 'breed') {
    if ($st = $link->prepare("SELECT name, system_name, forms, energy, description FROM dim_breeds WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $parts = [];
            $system = trim((string)($r['system_name'] ?? ''));
            $energy = (int)($r['energy'] ?? 0);
            if ($system !== '') $parts[] = $system;
            if ($energy > 0) $parts[] = 'Gnosis ' . $energy;
            $outMeta = tt_join_meta($parts);
            $outDesc = short_text($r['description'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'auspice') {
    if ($st = $link->prepare("SELECT name, system_name, energy, description FROM dim_auspices WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $parts = [];
            $system = trim((string)($r['system_name'] ?? ''));
            $energy = (int)($r['energy'] ?? 0);
            if ($system !== '') $parts[] = $system;
            if ($energy > 0) $parts[] = 'Rabia ' . $energy;
            $outMeta = tt_join_meta($parts);
            $outDesc = short_text($r['description'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'tribe') {
    if ($st = $link->prepare("SELECT name, system_name, affiliation, energy, description FROM dim_tribes WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $parts = [];
            $system = trim((string)($r['system_name'] ?? ''));
            $energy = (int)($r['energy'] ?? 0);
            if ($system !== '') $parts[] = $system;
            if ($energy > 0) $parts[] = 'Fuerza de Voluntad ' . $energy;
            $outMeta = tt_join_meta($parts);
            $outDesc = short_text($r['description'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'archetype') {
    if ($st = $link->prepare("SELECT name, description, willpower_text FROM dim_archetypes WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $outMeta = 'Arquetipo de personalidad';
            $outDesc = short_text($r['description'] ?? '', 280);
            $wp = short_text($r['willpower_text'] ?? '', 220);
            if ($wp !== '') {
                $outExtraLabel = 'Recuperacion de voluntad';
                $outExtra = $wp;
            }
        }
        $st->close();
    }
} elseif ($type === 'totem') {
    if ($st = $link->prepare("SELECT t.name, t.cost, t.description, tt.name AS type_name FROM dim_totems t LEFT JOIN dim_totem_types tt ON tt.id=t.totem_type_id WHERE t.id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $parts = [];
            $typeName = trim((string)($r['type_name'] ?? ''));
            $cost = (int)($r['cost'] ?? 0);
            if ($typeName !== '') $parts[] = $typeName;
            if ($cost > 0) $parts[] = 'Coste ' . $cost;
            $outMeta = tt_join_meta($parts);
            $outDesc = short_text($r['description'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'group') {
    if ($st = $link->prepare("SELECT name, description FROM dim_groups WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $outMeta = 'Grupo';
            $outDesc = short_text($r['description'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'organization') {
    if ($st = $link->prepare("SELECT name, description FROM dim_organizations WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $outMeta = 'Organización';
            $outDesc = short_text($r['description'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'chronicle' || $type === 'dim_chronicle' || $type === 'dim_chronicles') {
    if ($st = $link->prepare("SELECT name, description FROM dim_chronicles WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $outTitle = $r['name'] ?? '';
            $outMeta = 'Crónica';
            $outDesc = short_text($r['description'] ?? '', 360);
        }
        $st->close();
    }
} elseif ($type === 'resource') {
    if ($st = $link->prepare("SELECT name, kind, description FROM dim_systems_resources WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $kindRaw = strtolower(trim((string)($r['kind'] ?? '')));
            if (in_array($kindRaw, ['renombre', 'estado'], true)) {
                $outTitle = $r['name'] ?? '';
                $kindNorm = $kindRaw !== '' ? ucfirst($kindRaw) : '';
                if ($kindNorm !== '') $outMeta = h($kindNorm);
                $outDesc = short_text($r['description'] ?? '', 320);
            }
        }
        $st->close();
    }
} elseif ($type === 'character' || $type === 'bio' || $type === 'pj') {
    $kindCol = tt_has_column($link, 'fact_characters', 'character_kind') ? 'character_kind' : (tt_has_column($link, 'fact_characters', 'kind') ? 'kind' : '');
    $descExpr = "COALESCE(NULLIF(c.info_text,''), NULLIF(c.notes,''), '')";
    if (tt_has_column($link, 'fact_characters', 'description')) {
        $descExpr = "COALESCE(NULLIF(c.description,''), NULLIF(c.info_text,''), NULLIF(c.notes,''), '')";
    }
    $kindSelect = ($kindCol !== '') ? "c.`$kindCol` AS character_kind," : "";
    $sql = "
        SELECT
            c.name,
            c.alias,
            {$kindSelect}
            {$descExpr} AS char_desc,
            b.name AS breed_name,
            a.name AS auspice_name,
            t.name AS tribe_name
        FROM fact_characters c
        LEFT JOIN dim_breeds b   ON b.id = c.breed_id
        LEFT JOIN dim_auspices a ON a.id = c.auspice_id
        LEFT JOIN dim_tribes t   ON t.id = c.tribe_id
        WHERE c.id = ?
        LIMIT 1
    ";
    if ($st = $link->prepare($sql)) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($r = $rs->fetch_assoc()) {
            $name = (string)($r['name'] ?? '');
            $alias = trim((string)($r['alias'] ?? ''));
            $outTitle = ($alias !== '' ? $alias . ' (' . $name . ')' : $name);

            $meta = [];
            $breed = trim((string)($r['breed_name'] ?? ''));
            $auspice = trim((string)($r['auspice_name'] ?? ''));
            $tribe = trim((string)($r['tribe_name'] ?? ''));
            if ($breed !== '') $meta[] = h($breed);
            if ($auspice !== '') $meta[] = h($auspice);
            if ($tribe !== '') $meta[] = h($tribe);
            if (!empty($meta)) $outMeta = implode(' - ', $meta);

            $outDesc = short_text((string)($r['char_desc'] ?? ''), 440);
        }
        $st->close();
    }
}

if ($outTitle === '') { echo '<div class="hg-tip">No disponible</div>'; exit; }

echo "<div class='hg-tip hg-tip-row'>";
	if ($outImg !== '') {
		$imgSrc = h($outImg);
		echo "<div class='hg-tip-media'><img src=\"{$imgSrc}\" alt=\"" . h($outImgAlt) . "\" class=\"hg-tip-thumb\"></div>";
	}
	echo "<div class='hg-tip-body'>";
	echo "<div class='hg-tip-title'>" . h($outTitle) . "</div>";
	if ($outMeta !== '') echo "<div class='hg-tip-meta'>" . $outMeta . "</div>";
	if ($outDesc !== '') {
		echo "<div class='hg-tip-label'>Descripci&oacute;n</div>";
		echo "<div class='hg-tip-text'>" . h($outDesc) . "</div>";
	}
	if ($outExtra !== '') {
		echo "<div class='hg-tip-label'>" . h($outExtraLabel) . "</div>";
		echo "<div class='hg-tip-text'>" . h($outExtra) . "</div>";
	}
	if ($outSystem !== '') {
		echo "<div class='hg-tip-label'>Sistema</div>";
		echo "<div class='hg-tip-text'>" . h($outSystem) . "</div>";
	}
	echo "</div>";
echo "</div>";
?>
