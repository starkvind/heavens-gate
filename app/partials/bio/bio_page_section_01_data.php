<?php
require_once(__DIR__ . '/../../domains/characters/queries.php');

// Funcion para crear enlaces seguros.
if (!function_exists('createLink')) {
    function createLink($href, $text, $target = '_blank', $title = '', $extraAttrs = '') {
        $titleAttr = $title ? "title='$title'" : '';
        $extra = trim((string)$extraAttrs);
        if ($extra !== '') $extra = ' ' . $extra;
        return "<a href='$href' target='$target' $titleAttr$extra>$text</a>";
    }
}

$characterId = isset($characterId) ? (int)$characterId : 0;

// JUGADOR
$idJugador = $bioPlayer;
if ($idJugador != 'PNJ') {
    $resultCheckNPla = hg_characters_fetch_lookup($link, 'dim_players', (int)$idJugador, ['name', 'show_in_catalog']);
    $finalPlayer = (string)($resultCheckNPla['name'] ?? '');
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
    $namePlayerOfChara = htmlspecialchars((string)$bioPlayer, ENT_QUOTES, 'UTF-8');
    $playerLinkOfChara = '';
}

// CRONICA
$idCronica = (int)$bioChronic;
$resultCronica = hg_characters_fetch_lookup($link, 'dim_chronicles', $idCronica, ['name', 'description']);
$descCronica = '';
if ($resultCronica) {
    $nameCronica = htmlspecialchars((string)($resultCronica['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $descCronica = htmlspecialchars((string)($resultCronica['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $nameCronicaFinal = createLink(
        pretty_url($link, 'dim_chronicles', '/chronicles', $idCronica),
        $nameCronica,
        '_blank',
        '',
        "class='hg-tooltip' data-tip='dim_chronicle' data-id='" . $idCronica . "'"
    );
} else {
    $nameCronicaFinal = htmlspecialchars((string)$bioChronic, ENT_QUOTES, 'UTF-8');
}

// RAZA
$idRace = (int)$bioRace;
$resultRace = hg_characters_fetch_lookup($link, 'dim_breeds', $idRace);
if ($resultRace) {
    $nameRaceFinal = htmlspecialchars((string)$resultRace['name'], ENT_QUOTES, 'UTF-8');
    $raceLink = createLink(
        pretty_url($link, 'dim_breeds', '/systems/detail/1', $idRace),
        $nameRaceFinal,
        '_blank',
        '',
        "class='hg-tooltip' data-tip='breed' data-id='" . $idRace . "'"
    );
} else {
    $raceLink = htmlspecialchars((string)$idRace, ENT_QUOTES, 'UTF-8');
}

// AUSPICIO
$idAuspice = (int)$bioAuspice;
$resultAuspice = hg_characters_fetch_lookup($link, 'dim_auspices', $idAuspice);
if ($resultAuspice) {
    $nameAuspiceFinal = htmlspecialchars((string)$resultAuspice['name'], ENT_QUOTES, 'UTF-8');
    $auspiceLink = createLink(
        pretty_url($link, 'dim_auspices', '/systems/detail/2', $idAuspice),
        $nameAuspiceFinal,
        '_blank',
        '',
        "class='hg-tooltip' data-tip='auspice' data-id='" . $idAuspice . "'"
    );
} else {
    $auspiceLink = htmlspecialchars((string)$idAuspice, ENT_QUOTES, 'UTF-8');
}

// TRIBU
$idTribe = (int)$bioTribe;
$resultTribe = hg_characters_fetch_lookup($link, 'dim_tribes', $idTribe);
if ($resultTribe) {
    $nameTribeFinal = htmlspecialchars((string)$resultTribe['name'], ENT_QUOTES, 'UTF-8');
    $tribeLink = createLink(
        pretty_url($link, 'dim_tribes', '/systems/detail/3', $idTribe),
        $nameTribeFinal,
        '_blank',
        '',
        "class='hg-tooltip' data-tip='tribe' data-id='" . $idTribe . "'"
    );
} else {
    $tribeLink = htmlspecialchars((string)$idTribe, ENT_QUOTES, 'UTF-8');
}

// SISTEMAS MISC
$bioMiscLinksByKind = [];
foreach (hg_characters_fetch_misc_systems($link, $characterId) as $row) {
    $miscId = (int)($row['misc_system_id'] ?? 0);
    $miscName = trim((string)($row['name'] ?? ''));
    $miscKind = trim((string)($row['kind'] ?? ''));
    if ($miscId <= 0 || $miscName === '') continue;
    if ($miscKind === '') $miscKind = 'Misc';
    if (!isset($bioMiscLinksByKind[$miscKind])) $bioMiscLinksByKind[$miscKind] = [];
    $bioMiscLinksByKind[$miscKind][$miscId] = createLink(
        pretty_url($link, 'fact_misc_systems', '/systems/misc', $miscId),
        htmlspecialchars($miscName, ENT_QUOTES, 'UTF-8'),
        '_blank',
        '',
        "class='hg-tooltip' data-tip='misc_system' data-id='" . $miscId . "'"
    );
}

// MANADA / ORGANIZACION principal
$affiliations = hg_characters_fetch_primary_affiliations($link, $characterId);
$bioPack = (int)($affiliations['group_id'] ?? 0);
$bioClan = (int)($affiliations['organization_id'] ?? 0);

$idPack = $bioPack;
$resultPack = $idPack > 0 ? hg_characters_fetch_lookup($link, 'dim_groups', $idPack) : null;
$packLink = $resultPack
    ? createLink(
        pretty_url($link, 'dim_groups', '/groups', $idPack),
        htmlspecialchars((string)$resultPack['name'], ENT_QUOTES, 'UTF-8'),
        '_blank',
        '',
        "class='hg-tooltip' data-tip='group' data-id='" . $idPack . "'"
    )
    : htmlspecialchars((string)$idPack, ENT_QUOTES, 'UTF-8');

$idClan = $bioClan;
$resultClan = $idClan > 0 ? hg_characters_fetch_lookup($link, 'dim_organizations', $idClan) : null;
$clanLink = $resultClan
    ? createLink(
        pretty_url($link, 'dim_organizations', '/organizations', $idClan),
        htmlspecialchars((string)$resultClan['name'], ENT_QUOTES, 'UTF-8'),
        '_blank',
        '',
        "class='hg-tooltip' data-tip='organization' data-id='" . $idClan . "'"
    )
    : htmlspecialchars((string)$idClan, ENT_QUOTES, 'UTF-8');
$nameClanFinal = $resultClan ? htmlspecialchars((string)$resultClan['name'], ENT_QUOTES, 'UTF-8') : '';

// TIPO
$idTipo = (int)$bioType;
$resultTipo = hg_characters_fetch_lookup($link, 'dim_character_types', $idTipo, ['kind']);
$nameTipo = $resultTipo ? htmlspecialchars((string)$resultTipo['kind'], ENT_QUOTES, 'UTF-8') : '';

// NATURALEZA
$idNature = (int)$bioNature;
$resultNature = hg_characters_fetch_lookup($link, 'dim_archetypes', $idNature);
if ($resultNature) {
    $nameNatureFinal = htmlspecialchars((string)$resultNature['name'], ENT_QUOTES, 'UTF-8');
    $natureLink = createLink(
        pretty_url($link, 'dim_archetypes', '/rules/archetypes', $idNature),
        $nameNatureFinal,
        '_blank',
        '',
        "class='hg-tooltip' data-tip='archetype' data-id='" . $idNature . "'"
    );
} else {
    $natureLink = htmlspecialchars((string)($idNature ?: 'Sin especificar'), ENT_QUOTES, 'UTF-8');
}

// CONDUCTA
$idDemeanor = (int)$bioBehavior;
$resultDemeanor = hg_characters_fetch_lookup($link, 'dim_archetypes', $idDemeanor);
if ($resultDemeanor) {
    $nameDemeanorFinal = htmlspecialchars((string)$resultDemeanor['name'], ENT_QUOTES, 'UTF-8');
    $demeanorLink = createLink(
        pretty_url($link, 'dim_archetypes', '/rules/archetypes', $idDemeanor),
        $nameDemeanorFinal,
        '_blank',
        '',
        "class='hg-tooltip' data-tip='archetype' data-id='" . $idDemeanor . "'"
    );
} else {
    $demeanorLink = htmlspecialchars((string)($idDemeanor ?: 'Sin especificar'), ENT_QUOTES, 'UTF-8');
}

// TOTEM
$totemLink = '';
if (!empty($bioTotemId) && (int)$bioTotemId > 0) {
    $totemId = (int)$bioTotemId;
    $resultTotem = hg_characters_fetch_lookup($link, 'dim_totems', $totemId, ['name']);
    if ($resultTotem && !empty($resultTotem['name'])) {
        $totemName = (string)$resultTotem['name'];
    } elseif (!empty($bioTotem)) {
        $totemName = (string)$bioTotem;
    } else {
        $totemName = (string)$totemId;
    }
    $totemLink = createLink(
        pretty_url($link, 'dim_totems', '/powers/totem', $totemId),
        htmlspecialchars($totemName, ENT_QUOTES, 'UTF-8'),
        '_blank',
        '',
        "class='hg-tooltip' data-tip='totem' data-id='" . $totemId . "'"
    );
} elseif ($bioTotem !== '') {
    $totemLink = htmlspecialchars((string)$bioTotem, ENT_QUOTES, 'UTF-8');
}

// Calculo de circulos de habilidad, atributos, etc.
if (!function_exists('createSkillCircle')) {
    function createSkillCircle($array, $prefix) {
        $result = [];
        foreach ($array as $value) {
            $baseDir = ($prefix === 'gem-pwr') ? 'img/ui/gems/pwr' : 'img/ui/gems/attr';
            $result[] = "<img class='bioAttCircle' src='{$baseDir}/{$prefix}-0$value.webp'/>";
        }
        return $result;
    }
}

if (isset($bioArrayAtt)) $bioAttrImg = createSkillCircle($bioArrayAtt, 'gem-attr');
if (isset($bioArraySki)) $bioSkilImg = createSkillCircle($bioArraySki, 'gem-attr');

if (!function_exists('hg_bio_parse_iso_date')) {
    function hg_bio_parse_iso_date(?string $dateValue): ?array {
        $dateValue = trim((string)$dateValue);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T].*)?$/', $dateValue, $match)) {
            return null;
        }

        $year = (int)$match[1];
        $month = (int)$match[2];
        $day = (int)$match[3];
        if ($year < 1 || !checkdate($month, $day, $year)) {
            return null;
        }

        return ['year' => $year, 'month' => $month, 'day' => $day];
    }
}

if (!function_exists('hg_bio_format_iso_date')) {
    function hg_bio_format_iso_date(array $date): string {
        return sprintf('%02d/%02d/%04d', (int)$date['day'], (int)$date['month'], (int)$date['year']);
    }
}

if (!function_exists('hg_bio_event_date_label')) {
    function hg_bio_event_date_label(?string $dateValue, ?string $precision, ?string $note): string {
        $precision = trim((string)$precision);
        $dateValue = trim((string)$dateValue);
        $note = trim((string)$note);

        if ($precision === 'unknown') return ($note !== '') ? $note : 'Desconocido';
        if ($dateValue === '' || $dateValue === '0000-00-00') return ($note !== '') ? $note : '';

        $date = hg_bio_parse_iso_date($dateValue);
        if ($date === null) return ($note !== '') ? $note : $dateValue;

        if ($precision === 'year') $base = sprintf('%04d', (int)$date['year']);
        elseif ($precision === 'month') $base = sprintf('%02d/%04d', (int)$date['month'], (int)$date['year']);
        elseif ($precision === 'approx') $base = 'Aprox. ' . hg_bio_format_iso_date($date);
        else $base = hg_bio_format_iso_date($date);

        return ($note !== '') ? ($base . ' (' . $note . ')') : $base;
    }
}

$bioBirthLabel = 'Fecha de nacimiento';
$birthEvent = hg_characters_fetch_birth_event($link, $characterId);
$bioBday = hg_bio_event_date_label(
    (string)($birthEvent['event_date'] ?? ''),
    (string)($birthEvent['date_precision'] ?? 'unknown'),
    (string)($birthEvent['date_note'] ?? '')
);
if (trim($bioBday) === '') $bioBday = 'Desconocido';
$bioBirthData = [
    'label' => $bioBday,
    'event_date' => (string)($birthEvent['event_date'] ?? ''),
    'date_precision' => (string)($birthEvent['date_precision'] ?? 'unknown'),
    'date_note' => (string)($birthEvent['date_note'] ?? ''),
];

if (!function_exists('hg_bio_format_death_display')) {
    function hg_bio_format_death_display(string $deathCause, string $deathDateRaw, array $birthData = []): string
    {
        $deathCause = trim($deathCause);
        $deathDateRaw = trim($deathDateRaw);
        if ($deathCause === '') return '';

        $parts = [ucfirst($deathCause)];
        $hasRealDeathDate = ($deathDateRaw !== '' && $deathDateRaw !== '1000-01-01' && $deathDateRaw !== '0000-00-00');
        $deathDate = $hasRealDeathDate ? hg_bio_parse_iso_date($deathDateRaw) : null;
        if ($deathDate !== null) {
            $parts[0] .= ' (' . hg_bio_format_iso_date($deathDate) . ')';
        }

        $birthDateRaw = trim((string)($birthData['event_date'] ?? ''));
        $birthPrecision = trim((string)($birthData['date_precision'] ?? 'unknown'));
        $birthDate = ($birthPrecision === 'day') ? hg_bio_parse_iso_date($birthDateRaw) : null;
        if ($deathDate !== null && $birthDate !== null) {
            $birthKey = ((int)$birthDate['year'] * 10000) + ((int)$birthDate['month'] * 100) + (int)$birthDate['day'];
            $deathKey = ((int)$deathDate['year'] * 10000) + ((int)$deathDate['month'] * 100) + (int)$deathDate['day'];
            if ($deathKey >= $birthKey) {
                $age = (int)$deathDate['year'] - (int)$birthDate['year'];
                if (
                    (int)$deathDate['month'] < (int)$birthDate['month']
                    || ((int)$deathDate['month'] === (int)$birthDate['month'] && (int)$deathDate['day'] < (int)$birthDate['day'])
                ) {
                    $age--;
                }
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
