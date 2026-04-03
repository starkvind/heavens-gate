<?php
// Aseguramos que $link ya este definido y sea una conexion valida de mysqli.

// Funcion para obtener el nombre y otros detalles basados en el ID
function getSingleRecord($link, $table, $id, $fields = ['name']) {
    $fieldList = implode(', ', $fields);
    $stmt = $link->prepare("SELECT $fieldList FROM $table WHERE id = ? LIMIT 1");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Funcion para crear enlaces seguros
function createLink($href, $text, $target = '_blank', $title = '', $extraAttrs = '') {
    $titleAttr = $title ? "title='$title'" : '';
    $extra = trim((string)$extraAttrs);
    if ($extra !== '') $extra = ' ' . $extra;
    return "<a href='$href' target='$target' $titleAttr$extra>$text</a>";
}

// JUGADOR
$idJugador = $bioPlayer;
if ($idJugador != "PNJ") {
    $resultCheckNPla = getSingleRecord($link, 'dim_players', $idJugador, ['name', 'show_in_catalog']);
    $finalPlayer = ($resultCheckNPla['name'] ?? '');
    $namePlayerOfChara = htmlspecialchars($finalPlayer, ENT_QUOTES, 'UTF-8');
    $playerLinkOfChara = '';
    if (!empty($resultCheckNPla) && (int)($resultCheckNPla['show_in_catalog'] ?? 0) === 1) {
        $playerLinkOfChara = createLink(
            pretty_url($link, 'dim_players', '/players', (int)$idJugador),
            $namePlayerOfChara,
            '_blank'
        );
    }
} else {
    $namePlayerOfChara = htmlspecialchars($bioPlayer);
    $playerLinkOfChara = '';
}

// CRONICA
$idCronica = $bioChronic;
$resultCronica = getSingleRecord($link, 'dim_chronicles', $idCronica, ['name', 'description']);
if ($resultCronica) {
    $nameCronica = htmlspecialchars((string)($resultCronica['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $descCronica = htmlspecialchars((string)($resultCronica['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $nameCronicaFinal = createLink(
        pretty_url($link, 'dim_chronicles', '/chronicles', (int)$idCronica),
        $nameCronica,
        '_blank',
        '',
        "class='hg-tooltip' data-tip='dim_chronicle' data-id='" . (int)$idCronica . "'"
    );
} else {
    $nameCronicaFinal = htmlspecialchars($bioChronic);
}

// RAZA
$idRace = $bioRace;
$resultRace = getSingleRecord($link, 'dim_breeds', $idRace);
if ($resultRace) {
    $nameRaceFinal = htmlspecialchars($resultRace['name']);
    $raceLink = createLink(
        pretty_url($link, 'dim_breeds', '/systems/detail/1', (int)$idRace),
        $nameRaceFinal,
        '_blank',
        '',
        "class='hg-tooltip' data-tip='breed' data-id='" . (int)$idRace . "'"
    );
} else {
    $raceLink = htmlspecialchars($idRace);
}

// AUSPICIO
$idAuspice = $bioAuspice;
$resultAuspice = getSingleRecord($link, 'dim_auspices', $idAuspice);
if ($resultAuspice) {
    $nameAuspiceFinal = htmlspecialchars($resultAuspice['name']);
    $auspiceLink = createLink(
        pretty_url($link, 'dim_auspices', '/systems/detail/2', (int)$idAuspice),
        $nameAuspiceFinal,
        '_blank',
        '',
        "class='hg-tooltip' data-tip='auspice' data-id='" . (int)$idAuspice . "'"
    );
} else {
    $auspiceLink = htmlspecialchars($idAuspice);
}

// TRIBU
$idTribe = $bioTribe;
$resultTribe = getSingleRecord($link, 'dim_tribes', $idTribe);
if ($resultTribe) {
    $nameTribeFinal = htmlspecialchars($resultTribe['name']);
    $tribeLink = createLink(
        pretty_url($link, 'dim_tribes', '/systems/detail/3', (int)$idTribe),
        $nameTribeFinal,
        '_blank',
        '',
        "class='hg-tooltip' data-tip='tribe' data-id='" . (int)$idTribe . "'"
    );
} else {
    $tribeLink = htmlspecialchars($idTribe);
}

/* Cambio septiembre 2025 */

/* $characterId = id del personaje (int) */
$characterId = isset($characterId) ? (int)$characterId : (int)($_GET['b'] ?? 0);

$bioPack = 0;  // dim_groups.id
$bioClan = 0;  // dim_organizations.id

/* 1) PACK activo (bridge personaje-manada) */
$sql = "
  SELECT cgb.group_id
  FROM bridge_characters_groups AS cgb
  WHERE cgb.character_id = ? AND cgb.is_active = 1
  ORDER BY cgb.updated_at DESC, cgb.created_at DESC, cgb.group_id DESC
  LIMIT 1
";
if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, 'i', $characterId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $packId);
    if (mysqli_stmt_fetch($stmt)) { $bioPack = (int)$packId; }
    mysqli_stmt_close($stmt);
}

/* Fallback pack legacy */
if ($bioPack === 0) {
    $res = mysqli_query($link, "SELECT manada FROM fact_characters WHERE id = {$characterId} LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res))) $bioPack = (int)$row['manada'];
}

/* 2) CLAN: prioridad por pack-clan, si no hay pack mirar vinculo directo personaje-clan */
if ($bioPack > 0) {
    // clan via manada activa
    $sql = "
      SELECT cgb2.organization_id
      FROM bridge_organizations_groups AS cgb2
      WHERE cgb2.group_id = ? AND cgb2.is_active = 1
      ORDER BY cgb2.updated_at DESC, cgb2.created_at DESC, cgb2.organization_id DESC
      LIMIT 1
    ";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $bioPack);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $clanId);
        if (mysqli_stmt_fetch($stmt)) { $bioClan = (int)$clanId; }
        mysqli_stmt_close($stmt);
    }
}

if ($bioClan === 0) {
    // clan directo (personaje sin manada)
    $sql = "
      SELECT h.organization_id
      FROM bridge_characters_organizations h
      WHERE h.character_id = ? AND h.is_active = 1
      ORDER BY h.updated_at DESC, h.created_at DESC, h.organization_id DESC
      LIMIT 1
    ";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $characterId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $clanId);
        if (mysqli_stmt_fetch($stmt)) { $bioClan = (int)$clanId; }
        mysqli_stmt_close($stmt);
    }
}

/* Fallbacks de legado */
if ($bioClan === 0 && $bioPack > 0) {
    // pack-clan por nombre (solo mientras conviva nm.clan texto)
    $sql = "
      SELECT c.id
      FROM dim_organizations c
      JOIN dim_groups m ON m.clan = c.name
      WHERE m.id = ? LIMIT 1
    ";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $bioPack);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $clanId2);
        if (mysqli_stmt_fetch($stmt)) { $bioClan = (int)$clanId2; }
        mysqli_stmt_close($stmt);
    }
}
if ($bioClan === 0) {
    $res = mysqli_query($link, "SELECT clan FROM fact_characters WHERE id = {$characterId} LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res))) $bioClan = (int)$row['clan'];
}

/* Enlaces finales como ya tenias */
$idPack = $bioPack;
$resultPack = $idPack ? getSingleRecord($link, 'dim_groups', $idPack) : null;
$packLink = $resultPack
    ? createLink(
        pretty_url($link, 'dim_groups', '/groups', (int)$idPack),
        htmlspecialchars($resultPack['name']),
        '_blank',
        '',
        "class='hg-tooltip' data-tip='group' data-id='" . (int)$idPack . "'"
    )
    : htmlspecialchars($idPack);

$idClan = $bioClan;
$resultClan = $idClan ? getSingleRecord($link, 'dim_organizations', $idClan) : null;
$clanLink = $resultClan
    ? createLink(
        pretty_url($link, 'dim_organizations', '/organizations', (int)$idClan),
        htmlspecialchars($resultClan['name']),
        '_blank',
        '',
        "class='hg-tooltip' data-tip='organization' data-id='" . (int)$idClan . "'"
    )
    : htmlspecialchars($idClan);
$nameClanFinal = $resultClan ? htmlspecialchars($resultClan['name']) : '';
	
/*
// MANADA
$idPack = $bioPack;
$resultPack = getSingleRecord($link, 'dim_groups', $idPack);
if ($resultPack) {
    $namePackFinal = htmlspecialchars($resultPack['name']);
    $packLink = createLink("/groups/$idPack", $namePackFinal);
} else {
    $packLink = htmlspecialchars($idPack);
}

// CLAN
$idClan = $bioClan;
$resultClan = getSingleRecord($link, 'dim_organizations', $idClan);
if ($resultClan) {
    $nameClanFinal = htmlspecialchars($resultClan['name']);
    $clanLink = createLink("/organizations/$idClan", $nameClanFinal);
} else {
    $clanLink = htmlspecialchars($idClan);
}
*/

// TIPO
$idTipo = $bioType;
$resultTipo = getSingleRecord($link, 'dim_character_types', $idTipo, ['kind']);
$nameTipo = $resultTipo ? htmlspecialchars($resultTipo['kind']) : '';

// NATURALEZA
$idNature = $bioNature;
$resultNature = getSingleRecord($link, 'dim_archetypes', $idNature);
if ($resultNature) {
    $nameNatureFinal = htmlspecialchars($resultNature['name']);
    $natureLink = createLink(
        pretty_url($link, 'dim_archetypes', '/rules/archetypes', (int)$idNature),
        $nameNatureFinal,
        '_blank',
        '',
        "class='hg-tooltip' data-tip='archetype' data-id='" . (int)$idNature . "'"
    );
} else {
    $natureLink = htmlspecialchars($idNature ? $idNature : 'Sin especificar');
}

// CONDUCTA
$idDemeanor = $bioBehavior;
$resultDemeanor = getSingleRecord($link, 'dim_archetypes', $idDemeanor);
if ($resultDemeanor) {
    $nameDemeanorFinal = htmlspecialchars($resultDemeanor['name']);
    $demeanorLink = createLink(
        pretty_url($link, 'dim_archetypes', '/rules/archetypes', (int)$idDemeanor),
        $nameDemeanorFinal,
        '_blank',
        '',
        "class='hg-tooltip' data-tip='archetype' data-id='" . (int)$idDemeanor . "'"
    );
} else {
    $demeanorLink = htmlspecialchars($idDemeanor ? $idDemeanor : 'Sin especificar');
}

// TOTEM
$totemLink = '';
if (!empty($bioTotemId) && $bioTotemId > 0) {
    $totemName = '';
    $resultTotem = getSingleRecord($link, 'dim_totems', (int)$bioTotemId, ['name']);
    if ($resultTotem && !empty($resultTotem['name'])) {
        $totemName = (string)$resultTotem['name'];
    } elseif (!empty($bioTotem)) {
        $totemName = (string)$bioTotem;
    } else {
        $totemName = (string)$bioTotemId;
    }
    $totemLink = createLink(
        pretty_url($link, 'dim_totems', '/powers/totem', (int)$bioTotemId),
        htmlspecialchars($totemName),
        '_blank',
        '',
        "class='hg-tooltip' data-tip='totem' data-id='" . (int)$bioTotemId . "'"
    );
} elseif ($bioTotem !== '') {
    $totemLink = htmlspecialchars($bioTotem);
}

// Calculo de circulos de habilidad, atributos, etc.
if (!function_exists('createSkillCircle')) {
    function createSkillCircle($array, $prefix) {
        $result = [];
        foreach ($array as $value) {
            $baseDir = ($prefix === 'gem-pwr') ? 'img/ui/gems/pwr' : 'img/ui/gems/attr';
            $result[] = "<img class='bioAttCircle' src='{$baseDir}/{$prefix}-0$value.png'/>";
        }
        return $result;
    }
}

if (isset($bioArrayAtt)) $bioAttrImg = createSkillCircle($bioArrayAtt, 'gem-attr');
if (isset($bioArraySki)) $bioSkilImg = createSkillCircle($bioArraySki, 'gem-attr');

// CUMPLEANOS desde Operacion Eventos 5.0 (evento de nacimiento + bridge)
if (!function_exists('hg_bio_timeline_col_exists')) {
    function hg_bio_timeline_col_exists(mysqli $link, string $table, string $column): bool {
        static $cache = [];
        $key = $table . ':' . $column;
        if (isset($cache[$key])) return $cache[$key];
        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
            $st->bind_param('ss', $table, $column);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }
        $cache[$key] = $ok;
        return $ok;
    }
}

if (!function_exists('hg_bio_timeline_table_exists')) {
    function hg_bio_timeline_table_exists(mysqli $link, string $table): bool {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?")) {
            $st->bind_param('s', $table);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }
        $cache[$table] = $ok;
        return $ok;
    }
}

if (!function_exists('hg_bio_event_date_label')) {
    function hg_bio_event_date_label(?string $dateValue, ?string $precision, ?string $note): string {
        $precision = trim((string)$precision);
        $dateValue = trim((string)$dateValue);
        $note = trim((string)$note);

        if ($precision === 'unknown') return ($note !== '') ? $note : 'Desconocido';
        if ($dateValue === '' || $dateValue === '0000-00-00') return ($note !== '') ? $note : '';

        $ts = strtotime($dateValue);
        if ($ts === false) return ($note !== '') ? $note : $dateValue;

        if ($precision === 'year') $base = date('Y', $ts);
        elseif ($precision === 'month') $base = date('m/Y', $ts);
        elseif ($precision === 'approx') $base = 'Aprox. ' . date('d/m/Y', $ts);
        else $base = date('d/m/Y', $ts);

        return ($note !== '') ? ($base . ' (' . $note . ')') : $base;
    }
}

if (!function_exists('hg_bio_fetch_birth_label')) {
    function hg_bio_fetch_birth_data(mysqli $link, int $characterId): array {
        if (
            $characterId <= 0 ||
            !hg_bio_timeline_table_exists($link, 'fact_timeline_events') ||
            !hg_bio_timeline_table_exists($link, 'bridge_timeline_events_characters')
        ) {
            return [
                'label' => 'Desconocido',
                'event_date' => '',
                'date_precision' => 'unknown',
                'date_note' => '',
            ];
        }

        $hasTypeTable = hg_bio_timeline_table_exists($link, 'dim_timeline_events_types');
        $hasEventTypeId = hg_bio_timeline_col_exists($link, 'fact_timeline_events', 'event_type_id');
        $hasKind = hg_bio_timeline_col_exists($link, 'fact_timeline_events', 'kind');
        $hasPretty = hg_bio_timeline_col_exists($link, 'fact_timeline_events', 'pretty_id');
        $hasPrecision = hg_bio_timeline_col_exists($link, 'fact_timeline_events', 'date_precision');
        $hasNote = hg_bio_timeline_col_exists($link, 'fact_timeline_events', 'date_note');
        $hasSortDate = hg_bio_timeline_col_exists($link, 'fact_timeline_events', 'sort_date');
        $hasActive = hg_bio_timeline_col_exists($link, 'fact_timeline_events', 'is_active');

        $datePrecisionExpr = $hasPrecision ? 'e.date_precision' : "'day'";
        $dateNoteExpr = $hasNote ? 'e.date_note' : 'NULL';
        $sortDateExpr = $hasSortDate ? 'COALESCE(e.sort_date, e.event_date)' : 'e.event_date';
        $joinTypes = ($hasTypeTable && $hasEventTypeId) ? 'LEFT JOIN dim_timeline_events_types tet ON tet.id = e.event_type_id' : '';
        $activeCond = $hasActive ? 'AND e.is_active = 1' : '';

        $prettyId = 'birthday-char-' . $characterId;
        $prettyIdSql = "'" . mysqli_real_escape_string($link, $prettyId) . "'";
        $whereParts = [];
        if ($hasPretty) $whereParts[] = 'e.pretty_id = ' . $prettyIdSql;
        if ($hasTypeTable && $hasEventTypeId) $whereParts[] = "tet.pretty_id = 'nacimiento'";
        if ($hasKind) $whereParts[] = "e.kind = 'nacimiento'";
        if (empty($whereParts)) {
            return [
                'label' => 'Desconocido',
                'event_date' => '',
                'date_precision' => 'unknown',
                'date_note' => '',
            ];
        }

        $rankExpr = '9';
        if ($hasPretty && $hasTypeTable && $hasEventTypeId && $hasKind) {
            $rankExpr = "CASE WHEN e.pretty_id = {$prettyIdSql} THEN 0 WHEN tet.pretty_id = 'nacimiento' THEN 1 WHEN e.kind = 'nacimiento' THEN 2 ELSE 9 END";
        } elseif ($hasPretty && $hasTypeTable && $hasEventTypeId) {
            $rankExpr = "CASE WHEN e.pretty_id = {$prettyIdSql} THEN 0 WHEN tet.pretty_id = 'nacimiento' THEN 1 ELSE 9 END";
        } elseif ($hasPretty && $hasKind) {
            $rankExpr = "CASE WHEN e.pretty_id = {$prettyIdSql} THEN 0 WHEN e.kind = 'nacimiento' THEN 1 ELSE 9 END";
        } elseif ($hasPretty) {
            $rankExpr = "CASE WHEN e.pretty_id = {$prettyIdSql} THEN 0 ELSE 9 END";
        } elseif ($hasTypeTable && $hasEventTypeId && $hasKind) {
            $rankExpr = "CASE WHEN tet.pretty_id = 'nacimiento' THEN 0 WHEN e.kind = 'nacimiento' THEN 1 ELSE 9 END";
        } elseif ($hasTypeTable && $hasEventTypeId) {
            $rankExpr = "CASE WHEN tet.pretty_id = 'nacimiento' THEN 0 ELSE 9 END";
        } elseif ($hasKind) {
            $rankExpr = "CASE WHEN e.kind = 'nacimiento' THEN 0 ELSE 9 END";
        }

        $sql = "
            SELECT
                e.event_date,
                {$datePrecisionExpr} AS date_precision,
                {$dateNoteExpr} AS date_note
            FROM fact_timeline_events e
            LEFT JOIN bridge_timeline_events_characters bec ON bec.event_id = e.id
            {$joinTypes}
            WHERE (bec.character_id = ?" . ($hasPretty ? ' OR e.pretty_id = ' . $prettyIdSql : '') . ")
              {$activeCond}
              AND (" . implode(' OR ', $whereParts) . ")
            ORDER BY {$rankExpr} ASC, {$sortDateExpr} ASC, e.id ASC
            LIMIT 1
        ";

        if (!$st = $link->prepare($sql)) {
            return [
                'label' => 'Desconocido',
                'event_date' => '',
                'date_precision' => 'unknown',
                'date_note' => '',
            ];
        }

        $types = 'i';
        $params = [$characterId];
        $st->bind_param($types, ...$params);
        $st->execute();
        $eventDate = null;
        $datePrecision = null;
        $dateNote = null;
        $st->bind_result($eventDate, $datePrecision, $dateNote);
        $label = 'Desconocido';
        if ($st->fetch()) {
            $label = hg_bio_event_date_label(
                (string)($eventDate ?? ''),
                (string)($datePrecision ?? 'day'),
                (string)($dateNote ?? '')
            );
            if (trim($label) === '') $label = 'Desconocido';
        }
        $st->close();

        return [
            'label' => $label,
            'event_date' => (string)($eventDate ?? ''),
            'date_precision' => (string)($datePrecision ?? 'unknown'),
            'date_note' => (string)($dateNote ?? ''),
        ];
    }
}

$bioBirthLabel = 'Fecha de nacimiento';
$bioBirthData = hg_bio_fetch_birth_data($link, (int)($characterId ?? 0));
$bioBday = (string)($bioBirthData['label'] ?? 'Desconocido');

if (!function_exists('hg_bio_format_death_display')) {
    function hg_bio_format_death_display(string $deathCause, string $deathDateRaw, array $birthData = []): string
    {
        $deathCause = trim($deathCause);
        $deathDateRaw = trim($deathDateRaw);
        if ($deathCause === '') return '';

        $parts = [ucfirst($deathCause)];
        $hasRealDeathDate = ($deathDateRaw !== '' && $deathDateRaw !== '1000-01-01' && $deathDateRaw !== '0000-00-00');
        if ($hasRealDeathDate) {
            $deathTs = strtotime($deathDateRaw);
            if ($deathTs !== false) {
                $parts[0] .= ' (' . date('d/m/Y', $deathTs) . ')';
            }
        }

        $birthDate = trim((string)($birthData['event_date'] ?? ''));
        $birthPrecision = trim((string)($birthData['date_precision'] ?? 'unknown'));
        if ($hasRealDeathDate && $birthDate !== '' && $birthPrecision === 'day') {
            $birthTs = strtotime($birthDate);
            $deathTs = strtotime($deathDateRaw);
            if ($birthTs !== false && $deathTs !== false && $deathTs >= $birthTs) {
                $age = date_diff(date_create(date('Y-m-d', $birthTs)), date_create(date('Y-m-d', $deathTs)))->y;
                $parts[] = $age . ' años';
            }
        }

        return implode(' - ', $parts);
    }
}

$bioDeathDisplay = hg_bio_format_death_display(
    (string)($bioDethCaus ?? ''),
    (string)($bioDeathDateRaw ?? ''),
    (array)($bioBirthData ?? [])
);

?>

