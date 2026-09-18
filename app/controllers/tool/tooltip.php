<?php
// Tooltip endpoint (HTML fragment)
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../../domains/powers/queries.php';
require_once __DIR__ . '/../../domains/rules/queries.php';
require_once __DIR__ . '/../../domains/inventory/queries.php';
require_once __DIR__ . '/../../domains/systems/queries.php';
require_once __DIR__ . '/../../domains/organizations/queries.php';
require_once __DIR__ . '/../../domains/chronicles/queries.php';
require_once __DIR__ . '/../../domains/chapters/queries.php';
require_once __DIR__ . '/../../domains/timeline/queries.php';
require_once __DIR__ . '/../../domains/characters/detail_queries.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function short_text($html, $limit=520){
    $raw = (string)$html;
    $raw = preg_replace('/<\s*br\s*\/?>/i', "\n", $raw);
    $raw = preg_replace('/<\s*\/p\s*>/i', "\n", $raw);
    $raw = preg_replace('/<\s*li\s*>/i', " - ", $raw);
    $txt = trim(strip_tags($raw));
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

function tt_join_meta(array $parts): string {
    $safe = [];
    foreach ($parts as $p) {
        $v = trim((string)$p);
        if ($v !== '') $safe[] = h($v);
    }
    return implode(' - ', $safe);
}

function tt_event_date_label(?string $dateValue, string $precision = 'day', ?string $note = null): string {
    $precision = trim((string)$precision);
    $dateValue = trim((string)$dateValue);
    $note = trim((string)$note);

    if ($precision === 'unknown') {
        return ($note !== '') ? $note : 'Desconocida';
    }
    if ($dateValue === '' || $dateValue === '0000-00-00') {
        return ($note !== '') ? $note : '-';
    }
    $ts = strtotime($dateValue);
    if ($ts === false) {
        return ($note !== '') ? $note : $dateValue;
    }

    if ($precision === 'year') {
        $base = date('Y', $ts);
    } elseif ($precision === 'month') {
        $base = date('m/Y', $ts);
    } elseif ($precision === 'approx') {
        $base = 'Aprox. ' . date('d/m/Y', $ts);
    } else {
        $base = date('d/m/Y', $ts);
    }

    return ($note !== '') ? ($base . ' (' . $note . ')') : $base;
}

function tt_system_detail_meta(mysqli $link, string $table, int $id, array $row): array {
    $parts = [];
    $system = trim((string)($row['system_name'] ?? ''));
    if ($system !== '') {
        $parts[] = $system;
    }
    foreach (hg_ser_energy_entries_for_row($link, $table, $id, $row, $system) as $entry) {
        $label = trim((string)($entry['resource_name'] ?? ''));
        $value = (int)($entry['energy_value'] ?? 0);
        if ($label !== '' && $value > 0) {
            $parts[] = $label . ' ' . $value;
        }
    }
    return $parts;
}

$type = hg_request_query_value($hgRequest, 'type');
$id = (int)hg_request_query_value($hgRequest, 'id');
if (!$link || $id <= 0) {
    echo '<div class="hg-tip">No disponible</div>';
    exit;
}

$outTitle = '';
$outMeta = '';
$outSystem = '';
$outDesc = '';
$outImg = '';
$outImgAlt = '';
$outExtraLabel = '';
$outExtra = '';
$outPreDescLabel = '';
$outPreDesc = '';

if ($type === 'don') {
    $r = hg_powers_fetch_gift($link, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $outMeta = 'Rango ' . h($r['rank'] ?? '');
        $fera = trim((string)($r['legacy_system_name'] ?? ''));
        if ($fera !== '') $outMeta .= ' - ' . h($fera);
        $outSystem = short_text($r['mechanics_resolved'] ?? '');
        $outDesc = short_text($r['description'] ?? '', 360);
        $outImg = trim((string)($r['image_url'] ?? ''));
        $outImgAlt = $outTitle;
    }
} elseif ($type === 'rite') {
    $r = hg_powers_fetch_rite($link, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $tipo = trim((string)($r['type_name'] ?? ''));
        if ($tipo === '') $tipo = trim((string)($r['kind'] ?? ''));
        $meta = [];
        if ($tipo !== '') $meta[] = $tipo;
        $meta[] = 'Nivel ' . ($r['level'] ?? '');
        $raza = trim((string)($r['race'] ?? ''));
        if ($raza !== '') $meta[] = $raza;
        $outMeta = tt_join_meta($meta);
        $outSystem = short_text($r['system_text'] ?? '');
        $outDesc = short_text($r['description'] ?? '', 360);
    }
} elseif ($type === 'merit') {
    $r = hg_rules_fetch_merit($link, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $outMeta = h($r['kind'] ?? $r['tipo'] ?? '');
        $coste = $r['cost'] ?? $r['coste'] ?? '';
        $afil = $r['affiliation'] ?? $r['afiliacion'] ?? '';
        if ($coste !== '') $outMeta .= ' - Coste ' . h($coste);
        if ($afil !== '') $outMeta .= ' - ' . h($afil);
        $outDesc = short_text($r['description'] ?? $r['descripcion'] ?? '', 360);
    }
} elseif ($type === 'condition' || $type === 'character_condition' || $type === 'dim_character_condition') {
    $r = hg_rules_fetch_condition($link, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $category = trim((string)($r['category'] ?? ''));
        $outMeta = 'Condici&oacute;n';
        if ($category !== '') $outMeta .= ' - ' . h($category);
        $outDesc = short_text($r['description'] ?? '', 360);
    }
} elseif ($type === 'item' || $type === 'items' || $type === 'fact_items') {
    $r = hg_inventory_fetch_item($link, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $tipo = (int)($r['item_type_id'] ?? 0);
        $nivel = $r['level'] ?? '';
        $gnosis = $r['gnosis'] ?? '';
        $habilidad = (string)($r['skill_name'] ?? '');
        $dano = (string)($r['damage_type'] ?? '');
        $bonus = (int)($r['bonus'] ?? 0);
        $metal = (int)($r['metal'] ?? 0);
        $mapTipo = [1=>'Arma', 2=>'Protector', 3=>'Objeto m&aacute;gico', 4=>'Objeto', 5=>'Amuleto'];
        $outMeta = $mapTipo[$tipo] ?? 'Objeto';
        if ($nivel !== '' && (int)$nivel > 0) $outMeta .= ' - Nivel ' . h($nivel);
        if ($gnosis !== '' && (int)$gnosis > 0) $outMeta .= ' - Gnosis ' . h($gnosis);

        $extraMeta = '';
        if ($tipo === 1 && $dano !== '') {
            $metalText = '';
            if ($metal === 1) $metalText = ' y de plata';
            if ($metal === 2) $metalText = ' y de oro';
            switch ($habilidad) {
                case 'Cuerpo a Cuerpo':
                case 'Pelea':
                case 'Arrojar':
                    $damageText = 'Fuerza + ' . $bonus;
                    break;
                default:
                    $damageText = $bonus . ' dados';
                    break;
            }
            $extraMeta = 'Da&ntilde;o ' . h($damageText) . ', ' . h($dano) . $metalText;
        } elseif ($tipo === 2 && $bonus !== 0) {
            $extraMeta = 'Protecci&oacute;n +' . h($bonus);
        }
        if ($extraMeta !== '') $outMeta .= ' - ' . $extraMeta;

        $outDesc = short_text($r['description'] ?? '', 360);
        $outImg = trim((string)($r['image_url'] ?? ''));
        $outImgAlt = $outTitle;
    }
} elseif ($type === 'maneuver' || $type === 'combat_maneuver' || $type === 'fact_combat_maneuvers') {
    $r = hg_rules_fetch_maneuver($link, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $actions = trim((string)($r['actions'] ?? ''));
        $outMeta = tt_join_meta([
            trim((string)($r['system_name'] ?? '')),
            $actions !== '' ? $actions . ' acci' . ((int)$actions === 1 ? 'on' : 'ones') : '',
        ]);
        $outPreDescLabel = 'Tirada';
        $outPreDesc = tt_join_meta([
            trim((string)($r['roll'] ?? '')),
            trim((string)($r['difficulty'] ?? '')) !== '' ? 'Dificultad ' . trim((string)$r['difficulty']) : '',
            trim((string)($r['damage'] ?? '')) !== '' ? 'Daño ' . trim((string)$r['damage']) : '',
        ]);
        $outDesc = short_text((string)($r['text'] ?? ''), 360);
        $outImg = trim((string)($r['image_url'] ?? ''));
        if ($outImg !== '' && strpos($outImg, '/') === false) $outImg = '/img/maneuvers/' . $outImg;
        $outImgAlt = $outTitle;
    }
} elseif ($type === 'action' || $type === 'fact_action' || $type === 'fact_actions') {
    $r = hg_rules_fetch_action($link, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $outMeta = h(trim((string)($r['category'] ?? '')));
        $outPreDescLabel = 'Tirada';
        $outPreDesc = tt_join_meta([(string)($r['attribute_name'] ?? ''), (string)($r['skill_name'] ?? '')]);
        if (($r['difficulty_mode'] ?? '') === 'fixed') {
            $outExtra = 'Fija: ' . (int)($r['fixed_difficulty'] ?? 0);
        } else {
            $difficultyParts = [];
            if ((int)($r['suggested_difficulty'] ?? 0) > 0) $difficultyParts[] = 'Sugerida ' . (int)$r['suggested_difficulty'];
            if ((int)($r['min_difficulty'] ?? 0) > 0 && (int)($r['max_difficulty'] ?? 0) > 0) {
                $difficultyParts[] = (int)$r['min_difficulty'] . ' - ' . (int)$r['max_difficulty'];
            }
            $outExtra = implode(' / ', $difficultyParts);
        }
        $outExtraLabel = 'Dificultad';
        $outDesc = short_text((string)($r['text'] ?? ''), 360);
    }
} elseif ($type === 'trait') {
    $r = hg_rules_fetch_trait($link, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $kind = (string)($r['kind'] ?? $r['rule_kind'] ?? '');
        if ($kind !== '') $outMeta = h($kind);
        $outDesc = short_text($r['description'] ?? '', 320);
    }
} elseif ($type === 'breed' || $type === 'auspice' || $type === 'tribe') {
    $detailType = $type === 'breed' ? 1 : ($type === 'auspice' ? 2 : 3);
    $table = $type === 'breed' ? 'dim_breeds' : ($type === 'auspice' ? 'dim_auspices' : 'dim_tribes');
    $r = hg_systems_fetch_detail($link, $detailType, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $outMeta = tt_join_meta(tt_system_detail_meta($link, $table, $id, $r));
        $outDesc = short_text($r['description'] ?? '', 360);
    }
} elseif ($type === 'archetype') {
    $r = hg_rules_fetch_archetype($link, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $outMeta = 'Arquetipo de personalidad';
        $outDesc = short_text($r['description'] ?? '', 280);
        $wp = short_text($r['willpower_text'] ?? '', 220);
        if ($wp !== '') {
            $outExtraLabel = 'Recuperacion de voluntad';
            $outExtra = $wp;
        }
    }
} elseif ($type === 'totem') {
    $r = hg_powers_fetch_totem($link, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $parts = [];
        $typeName = trim((string)($r['type_name'] ?? ''));
        $cost = (int)($r['cost'] ?? 0);
        if ($typeName !== '') $parts[] = $typeName;
        if ($cost > 0) $parts[] = 'Coste ' . $cost;
        $outMeta = tt_join_meta($parts);
        $outDesc = short_text($r['description'] ?? '', 360);
    }
} elseif ($type === 'group' || $type === 'organization') {
    $r = hg_organizations_fetch_entity($link, $type === 'group' ? 1 : 2, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $outMeta = $type === 'group' ? 'Grupo' : 'Organización';
        $outDesc = short_text($r['description'] ?? '', 360);
    }
} elseif ($type === 'chronicle' || $type === 'dim_chronicle' || $type === 'dim_chronicles') {
    $r = hg_chronicles_fetch_one($link, hg_chronicles_schema($link), $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $outMeta = 'Crónica';
        $outDesc = short_text($r['description'] ?? '', 360);
    }
} elseif ($type === 'resource') {
    $r = hg_systems_fetch_resource($link, $id);
    if ($r) {
        $kindRaw = strtolower(trim((string)($r['kind'] ?? '')));
        if (in_array($kindRaw, ['renombre', 'estado'], true)) {
            $outTitle = (string)($r['name'] ?? '');
            if ($kindRaw !== '') $outMeta = h(ucfirst($kindRaw));
            $outDesc = short_text($r['description'] ?? '', 320);
        }
    }
} elseif ($type === 'misc_system' || $type === 'misc' || $type === 'fact_misc_systems') {
    $r = hg_systems_fetch_detail($link, 4, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $parts = [];
        $kind = trim((string)($r['kind'] ?? ''));
        $systemName = trim((string)($r['system_name'] ?? ''));
        if ($kind !== '') $parts[] = $kind;
        if ($systemName !== '') $parts[] = $systemName;
        foreach (hg_ser_energy_entries_for_row($link, 'fact_misc_systems', $id, $r, $systemName) as $entry) {
            $label = trim((string)($entry['resource_name'] ?? ''));
            $value = (int)($entry['energy_value'] ?? 0);
            if ($label !== '' && $value > 0) $parts[] = $label . ' ' . $value;
        }
        $outMeta = tt_join_meta($parts);
        $outDesc = short_text($r['description'] ?? '', 360);
    }
} elseif ($type === 'chapter' || $type === 'dim_chapter' || $type === 'dim_chapters') {
    $r = hg_chapters_fetch_chapter_detail($link, $id);
    if ($r) {
        $outTitle = (string)($r['name'] ?? '');
        $chapterNum = (int)($r['chapter_number'] ?? 0);
        $seasonNum = (int)($r['season_number'] ?? 0);
        $seasonKind = trim((string)($r['season_kind'] ?? 'temporada'));
        if ($seasonKind === '') $seasonKind = 'temporada';

        $chapterLabel = 'Capitulo ' . ($chapterNum > 0 ? $chapterNum : '?');
        if ($seasonKind === 'historia_personal') {
            $seasonLabel = 'Historia personal';
        } elseif ($seasonKind === 'inciso') {
            $incisoNum = $seasonNum;
            if ($incisoNum >= 100 && $incisoNum < 200) $incisoNum -= 100;
            $seasonLabel = 'Inciso ' . ($incisoNum > 0 ? $incisoNum : '?');
        } elseif ($seasonKind === 'especial') {
            $seasonLabel = 'Especial';
        } else {
            $seasonLabel = 'Temporada ' . ($seasonNum > 0 ? $seasonNum : '?');
        }
        $outMeta = tt_join_meta([$chapterLabel, $seasonLabel]);

        $playedDate = trim((string)($r['played_date'] ?? ''));
        if ($playedDate !== '' && $playedDate !== '0000-00-00') {
            $ts = strtotime($playedDate);
            if ($ts !== false) {
                $outPreDescLabel = 'Fecha de juego';
                $outPreDesc = date('d/m/Y', $ts);
            }
        }
        $outDesc = short_text((string)($r['synopsis'] ?? ''), 360);
    }
} elseif ($type === 'event' || $type === 'timeline_event' || $type === 'fact_timeline_events') {
    $r = hg_timeline_fetch_event($link, $id);
    if ($r) {
        $outTitle = (string)($r['title'] ?? '');
        $dateLabel = tt_event_date_label(
            (string)($r['event_date'] ?? ''),
            (string)($r['date_precision'] ?? 'day'),
            (string)($r['date_note'] ?? '')
        );
        $chronicles = hg_timeline_fetch_event_chronicles($link, $id);
        $chronicleNames = [];
        if (is_array($chronicles)) {
            foreach ($chronicles as $chronicle) {
                $name = trim((string)($chronicle['name'] ?? ''));
                if ($name !== '') $chronicleNames[] = $name;
            }
        }
        $chronicleLine = !empty($chronicleNames)
            ? implode(' | ', $chronicleNames)
            : trim((string)($r['timeline'] ?? ''));
        if ($chronicleLine === '') $chronicleLine = '-';
        $outMeta = tt_join_meta([$dateLabel, trim((string)($r['type_name'] ?? 'Evento')), $chronicleLine]);
        $outDesc = short_text((string)($r['description'] ?? ''), 360);
    }
} elseif ($type === 'character' || $type === 'bio' || $type === 'pj') {
    $detail = hg_characters_fetch_detail_context_row($link, $id);
    $r = $detail['row'] ?? null;
    if (is_array($r)) {
        $name = (string)($r['name'] ?? '');
        $alias = trim((string)($r['alias'] ?? ''));
        $outTitle = $alias !== '' ? $alias . ' (' . $name . ')' : $name;
        $outMeta = tt_join_meta([
            trim((string)($r['breed_name'] ?? '')),
            trim((string)($r['auspice_name'] ?? '')),
            trim((string)($r['tribe_name'] ?? '')),
        ]);
        $description = trim((string)($r['description'] ?? ''));
        if ($description === '') $description = trim((string)($r['info_text'] ?? ''));
        if ($description === '') $description = trim((string)($r['notes'] ?? ''));
        $outDesc = short_text($description, 440);
    }
}

if ($outTitle === '') {
    echo '<div class="hg-tip">No disponible</div>';
    exit;
}

echo "<div class='hg-tip hg-tip-row'>";
if ($outImg !== '') {
    $imgSrc = h($outImg);
    echo "<div class='hg-tip-media'><img src=\"{$imgSrc}\" alt=\"" . h($outImgAlt) . "\" class=\"hg-tip-thumb\"></div>";
}
echo "<div class='hg-tip-body'>";
echo "<div class='hg-tip-title'>" . h($outTitle) . "</div>";
if ($outMeta !== '') echo "<div class='hg-tip-meta'>" . $outMeta . "</div>";
if ($outPreDesc !== '') {
    echo "<div class='hg-tip-label'>" . h($outPreDescLabel) . "</div>";
    echo "<div class='hg-tip-text'>" . h($outPreDesc) . "</div>";
}
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