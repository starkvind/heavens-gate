<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!function_exists('hg_mobile_bio_h')) {
    function hg_mobile_bio_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_bio_table_exists')) {
    function hg_mobile_bio_table_exists(mysqli $link, string $table): bool
    {
        $table = mysqli_real_escape_string($link, $table);
        if ($res = $link->query("SHOW TABLES LIKE '{$table}'")) {
            $exists = $res->num_rows > 0;
            $res->free();
            return $exists;
        }
        return false;
    }
}

if (!function_exists('hg_mobile_bio_column_exists')) {
    function hg_mobile_bio_column_exists(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . ':' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $exists = false;
        if ($stmt = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
            $stmt->bind_param('ss', $table, $column);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
            $exists = ((int)$count > 0);
        }
        $cache[$key] = $exists;
        return $exists;
    }
}

if (!function_exists('hg_mobile_bio_system_detail_labels')) {
    function hg_mobile_bio_system_detail_labels(mysqli $link, int $systemId): array
    {
        if ($systemId <= 0 || !hg_mobile_bio_table_exists($link, 'bridge_systems_detail_labels')) return [];
        $labels = [];
        if ($stmt = $link->prepare('SELECT * FROM bridge_systems_detail_labels WHERE system_id = ? LIMIT 1')) {
            $stmt->bind_param('i', $systemId); $stmt->execute(); $result = $stmt->get_result();
            $labels = $result ? ($result->fetch_assoc() ?: []) : []; $stmt->close();
        }
        return $labels;
    }
}

if (!function_exists('hg_mobile_bio_rows')) {
    function hg_mobile_bio_rows(mysqli $link, string $sql): array
    {
        $rows = [];
        if ($res = $link->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        } else {
            hg_public_log_error('mobile_character_detail', 'query failed: ' . mysqli_error($link));
        }
        return $rows;
    }
}

if (!function_exists('hg_mobile_bio_pretty_href')) {
    function hg_mobile_bio_pretty_href(mysqli $link, string $table, string $base, int $id): string
    {
        if ($id <= 0) {
            return '#';
        }
        return function_exists('pretty_url') ? pretty_url($link, $table, $base, $id) : rtrim($base, '/') . '/' . $id;
    }
}

if (!function_exists('hg_mobile_bio_link')) {
    function hg_mobile_bio_link(string $href, string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        return '<a href="' . hg_mobile_bio_h($href) . '">' . hg_mobile_bio_h($label) . '</a>';
    }
}

if (!function_exists('hg_mobile_bio_trait_dots')) {
    function hg_mobile_bio_trait_dots(int $value): string
    {
        $value = max(0, min(10, $value));
        return "<span class='hg-mobile-trait-dots' aria-label='{$value} puntos'>"
            . str_repeat('&#9679;', $value)
            . str_repeat('&#9675;', max(0, 5 - $value))
            . '</span>';
    }
}

if (!function_exists('hg_mobile_bio_date')) {
    function hg_mobile_bio_date(?string $raw): string
    {
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === '0000-00-00') {
            return '';
        }
        $ts = strtotime($raw);
        return $ts !== false ? date('d-m-Y', $ts) : $raw;
    }
}

if (!function_exists('hg_mobile_bio_first_lookup')) {
    function hg_mobile_bio_first_lookup(mysqli $link, string $table, int $id, string $base): array
    {
        if ($id <= 0 || !hg_mobile_bio_table_exists($link, $table)) {
            return [];
        }
        $idSql = (int)$id;
        $rows = hg_mobile_bio_rows($link, "SELECT id, name FROM `{$table}` WHERE id = {$idSql} LIMIT 1");
        if (empty($rows)) {
            return [];
        }
        $rows[0]['href'] = hg_mobile_bio_pretty_href($link, $table, $base, $id);
        return $rows[0];
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_character_detail', 'missing DB connection');
    hg_public_render_error('Personaje no disponible', 'No se pudo cargar el personaje.');
    return;
}

$rawId = trim((string)($_GET['b'] ?? ''));
$characterId = 0;
if ($rawId !== '') {
    $resolved = resolve_pretty_id($link, 'fact_characters', $rawId);
    $characterId = (int)($resolved ?? 0);
}

if ($characterId <= 0) {
    hg_public_render_not_found('Personaje no encontrado', 'No se pudo localizar el personaje solicitado.');
    return;
}

$deathTable = null;
if ($rs = $link->query("SHOW TABLES LIKE 'fact_characters_deaths'")) {
    if ($rs->num_rows > 0) $deathTable = 'fact_characters_deaths';
    $rs->free();
}
if ($deathTable === null && ($rs = $link->query("SHOW TABLES LIKE 'fact_characters_death'"))) {
    if ($rs->num_rows > 0) $deathTable = 'fact_characters_death';
    $rs->free();
}
$deathJoin = $deathTable ? "LEFT JOIN `{$deathTable}` fd ON fd.character_id = p.id" : "";
$deathSelect = $deathTable ? "COALESCE(fd.death_description, '') AS death_description, COALESCE(fd.death_date, '') AS death_date," : "'' AS death_description, '' AS death_date,";

$characterChronicleAnd = function_exists('hg_mobile_chronicle_exclusion_and') ? hg_mobile_chronicle_exclusion_and('p') : ' AND p.chronicle_id NOT IN (2,7) ';

$sql = "
    SELECT
        p.id,
        p.name,
        p.system_id,
        p.chronicle_id,
        p.player_id,
        p.breed_id,
        p.auspice_id,
        p.tribe_id,
        p.totem_id,
        p.nature_id,
        p.demeanor_id,
        p.alias,
        p.garou_name,
        p.gender,
        p.concept,
        p.image_url,
        p.info_text,
        p.rank,
        p.birthdate_text,
        p.character_kind,
        {$deathSelect}
        COALESCE(sys.name, '') AS system_name,
        COALESCE(ct.kind, '') AS type_name,
        COALESCE(ch.name, '') AS chronicle_name,
        COALESCE(pl.name, '') AS player_name,
        COALESCE(pl.show_in_catalog, 0) AS player_show_in_catalog,
        COALESCE(br.name, '') AS breed_name,
        COALESCE(au.name, '') AS auspice_name,
        COALESCE(tr.name, '') AS tribe_name,
        COALESCE(tox.name, '') AS totem_name,
        COALESCE(na.name, '') AS nature_name,
        COALESCE(de.name, '') AS demeanor_name,
        COALESCE(st.label, '') AS status_label
    FROM fact_characters p
    LEFT JOIN dim_systems sys ON sys.id = p.system_id
    LEFT JOIN dim_character_types ct ON ct.id = p.character_type_id
    LEFT JOIN dim_chronicles ch ON ch.id = p.chronicle_id
    LEFT JOIN dim_players pl ON pl.id = p.player_id
    LEFT JOIN dim_breeds br ON br.id = p.breed_id
    LEFT JOIN dim_auspices au ON au.id = p.auspice_id
    LEFT JOIN dim_tribes tr ON tr.id = p.tribe_id
    LEFT JOIN dim_totems tox ON tox.id = p.totem_id
    LEFT JOIN dim_archetypes na ON na.id = p.nature_id
    LEFT JOIN dim_archetypes de ON de.id = p.demeanor_id
    LEFT JOIN dim_character_status st ON st.id = p.status_id
    {$deathJoin}
    WHERE p.id = ? {$characterChronicleAnd}
    LIMIT 1
";
$character = null;
if ($stmt = $link->prepare($sql)) {
    $stmt->bind_param('i', $characterId);
    $stmt->execute();
    $result = $stmt->get_result();
    $character = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

if (!$character) {
    hg_public_render_not_found('Personaje no encontrado', 'No se pudo localizar el personaje solicitado.');
    return;
}

$pageSect = 'Biografía';
$metaTitle = (string)($character['name'] ?? '') . " | Personajes | Heaven's Gate";
$metaDescription = trim(strip_tags((string)($character['info_text'] ?? '')));
if (function_exists('mb_substr')) {
    $metaDescription = mb_substr($metaDescription, 0, 160, 'UTF-8');
} else {
    $metaDescription = substr($metaDescription, 0, 160);
}

$avatar = hg_character_avatar_url((string)($character['image_url'] ?? ''), (string)($character['gender'] ?? ''));
$infoHtml = (string)($character['info_text'] ?? '');
$status = trim((string)($character['status_label'] ?? ''));
$deathDescription = trim((string)($character['death_description'] ?? ''));
$hasCharacterSheet = strtolower(trim((string)($character['character_kind'] ?? ''))) === 'pj';

$cid = (int)$characterId;
$systemId = (int)($character['system_id'] ?? 0);
$systemDetailLabels = hg_mobile_bio_system_detail_labels($link, $systemId);
$detailLabel = static function (string $key, string $fallback) use ($systemDetailLabels): string {
    $label = trim((string)($systemDetailLabels[$key] ?? ''));
    return $label !== '' ? $label : $fallback;
};
$chronicleId = (int)($character['chronicle_id'] ?? 0);
$playerId = (int)($character['player_id'] ?? 0);
$hidePlayer = $playerId === 48 || strcasecmp(trim((string)($character['player_name'] ?? '')), 'PNJ') === 0;
$breedId = (int)($character['breed_id'] ?? 0);
$auspiceId = (int)($character['auspice_id'] ?? 0);
$tribeId = (int)($character['tribe_id'] ?? 0);
$totemId = (int)($character['totem_id'] ?? 0);
$natureId = (int)($character['nature_id'] ?? 0);
$demeanorId = (int)($character['demeanor_id'] ?? 0);

$groups = hg_mobile_bio_rows($link, "
    SELECT g.id, g.name
    FROM bridge_characters_groups bcg
    INNER JOIN dim_groups g ON g.id = bcg.group_id
    WHERE bcg.character_id = {$cid}
      AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
    ORDER BY bcg.updated_at DESC, bcg.created_at DESC, bcg.group_id DESC
");
$organizations = hg_mobile_bio_rows($link, "
    SELECT o.id, o.name
    FROM bridge_characters_organizations bco
    INNER JOIN dim_organizations o ON o.id = bco.organization_id
    WHERE bco.character_id = {$cid}
      AND (bco.is_active = 1 OR bco.is_active IS NULL)
    ORDER BY bco.updated_at DESC, bco.created_at DESC, bco.organization_id DESC
");
if (empty($organizations)) {
    $organizations = hg_mobile_bio_rows($link, "
        SELECT DISTINCT o.id, o.name
        FROM bridge_characters_groups bcg
        INNER JOIN bridge_organizations_groups bog ON bog.group_id = bcg.group_id
        INNER JOIN dim_organizations o ON o.id = bog.organization_id
        WHERE bcg.character_id = {$cid}
          AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
          AND (bog.is_active = 1 OR bog.is_active IS NULL)
        ORDER BY bog.updated_at DESC, bog.created_at DESC, bog.organization_id DESC
    ");
}

$detailLinks = [];
if (!$hidePlayer && $playerId > 0 && trim((string)($character['player_name'] ?? '')) !== '' && (int)($character['player_show_in_catalog'] ?? 0) === 1) {
    $detailLinks['Jugador'] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_players', '/players', $playerId),
        (string)$character['player_name']
    );
} elseif (!$hidePlayer && trim((string)($character['player_name'] ?? '')) !== '') {
    $detailLinks['Jugador'] = hg_mobile_bio_h($character['player_name']);
}
if ($chronicleId > 0 && trim((string)($character['chronicle_name'] ?? '')) !== '') {
    $detailLinks['Crónica'] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_chronicles', '/chronicles', $chronicleId),
        (string)$character['chronicle_name']
    );
}
if ($breedId > 0 && trim((string)($character['breed_name'] ?? '')) !== '') {
    $detailLinks[$detailLabel('label_breed', 'Raza')] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_breeds', '/systems/detail/1', $breedId),
        (string)$character['breed_name']
    );
}
if ($auspiceId > 0 && trim((string)($character['auspice_name'] ?? '')) !== '') {
    $detailLinks[$detailLabel('label_auspice', 'Auspicio')] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_auspices', '/systems/detail/2', $auspiceId),
        (string)$character['auspice_name']
    );
}
if ($tribeId > 0 && trim((string)($character['tribe_name'] ?? '')) !== '') {
    $detailLinks[$detailLabel('label_tribe', 'Tribu')] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_tribes', '/systems/detail/3', $tribeId),
        (string)$character['tribe_name']
    );
}
if ($totemId > 0 && trim((string)($character['totem_name'] ?? '')) !== '') {
    $detailLinks['Tótem'] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_totems', '/powers/totem', $totemId),
        (string)$character['totem_name']
    );
}
if ($natureId > 0 && trim((string)($character['nature_name'] ?? '')) !== '') {
    $detailLinks['Naturaleza'] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_archetypes', '/rules/archetypes', $natureId),
        (string)$character['nature_name']
    );
}
if ($demeanorId > 0 && trim((string)($character['demeanor_name'] ?? '')) !== '') {
    $detailLinks['Conducta'] = hg_mobile_bio_link(
        hg_mobile_bio_pretty_href($link, 'dim_archetypes', '/rules/archetypes', $demeanorId),
        (string)$character['demeanor_name']
    );
}
if (!empty($groups)) {
    $links = [];
    foreach ($groups as $group) {
        $gid = (int)($group['id'] ?? 0);
        $links[] = hg_mobile_bio_link(hg_mobile_bio_pretty_href($link, 'dim_groups', '/groups', $gid), (string)($group['name'] ?? ''));
    }
    $detailLinks[$detailLabel('label_pack', 'Manada')] = implode(', ', array_filter($links));
}
if (!empty($organizations)) {
    $links = [];
    foreach ($organizations as $organization) {
        $oid = (int)($organization['id'] ?? 0);
        $links[] = hg_mobile_bio_link(hg_mobile_bio_pretty_href($link, 'dim_organizations', '/organizations', $oid), (string)($organization['name'] ?? ''));
    }
    $detailLinks[$detailLabel('label_clan', 'Clan')] = implode(', ', array_filter($links));
}

$traitsByKind = [];
$traitRows = [];
$traitKinds = ['Atributos', 'Talentos', 'Técnicas', 'Conocimientos', 'Trasfondos'];
if ($systemId > 0 && hg_mobile_bio_table_exists($link, 'fact_trait_sets')) {
    foreach ($traitKinds as $kindName) {
        $kindSql = mysqli_real_escape_string($link, $kindName);
        $traitRows = array_merge($traitRows, hg_mobile_bio_rows($link, "
            SELECT t.id, t.kind, t.classification, t.name, COALESCE(b.value, 0) AS value, s.sort_order
            FROM fact_trait_sets s
            INNER JOIN dim_traits t ON t.id = s.trait_id AND t.kind = '{$kindSql}'
            LEFT JOIN bridge_characters_traits b ON b.trait_id = t.id AND b.character_id = {$cid}
            WHERE s.system_id = {$systemId}
              AND s.is_active = 1
            ORDER BY
                COALESCE(NULLIF(CAST(SUBSTRING_INDEX(TRIM(t.classification), ' ', 1) AS UNSIGNED), 0), 9999),
                s.sort_order,
                t.name
        "));
        $traitRows = array_merge($traitRows, hg_mobile_bio_rows($link, "
            SELECT t.id, t.kind, t.classification, t.name, b.value, 999999 AS sort_order
            FROM bridge_characters_traits b
            INNER JOIN dim_traits t ON t.id = b.trait_id AND t.kind = '{$kindSql}'
            WHERE b.character_id = {$cid}
              AND b.value > 0
              AND t.id NOT IN (
                  SELECT trait_id FROM fact_trait_sets WHERE system_id = {$systemId} AND is_active = 1
              )
            ORDER BY b.value DESC, t.name
        "));
    }
} else {
    $traitRows = hg_mobile_bio_rows($link, "
        SELECT t.id, t.kind, t.classification, t.name, b.value, 999999 AS sort_order
        FROM bridge_characters_traits b
        INNER JOIN dim_traits t ON t.id = b.trait_id
        WHERE b.character_id = {$cid}
          AND b.value > 0
        ORDER BY t.kind, t.classification, t.name
    ");
}
foreach ($traitRows as $row) {
    $kind = trim((string)($row['kind'] ?? 'Otros')) ?: 'Otros';
    if ($kind === 'Trasfondos' && (int)($row['value'] ?? 0) <= 0) {
        continue;
    }
    if (!isset($traitsByKind[$kind])) {
        $traitsByKind[$kind] = [];
    }
    $traitsByKind[$kind][] = $row;
}

$merits = hg_mobile_bio_rows($link, "
    SELECT mf.id, mf.name, mf.kind, mf.cost, b.level
    FROM bridge_characters_merits_flaws b
    INNER JOIN dim_merits_flaws mf ON mf.id = b.merit_flaw_id
    WHERE b.character_id = {$cid}
    ORDER BY mf.kind DESC, mf.name
");

$conditions = [];
if (hg_mobile_bio_table_exists($link, 'bridge_characters_conditions') && hg_mobile_bio_table_exists($link, 'dim_character_conditions')) {
    $conditions = hg_mobile_bio_rows($link, "
        SELECT c.id, c.name, c.category
        FROM bridge_characters_conditions b
        INNER JOIN dim_character_conditions c ON c.id = b.condition_id
        WHERE b.character_id = {$cid}
        ORDER BY c.category, c.name
    ");
}

$powers = [
    'Dones' => hg_mobile_bio_rows($link, "
        SELECT g.id, g.name, g.rank AS level
        FROM bridge_characters_powers b
        INNER JOIN fact_gifts g ON g.id = b.power_id
        WHERE b.character_id = {$cid}
          AND b.power_kind = 'dones'
        ORDER BY g.rank, g.name
    "),
    'Disciplinas' => hg_mobile_bio_rows($link, "
        SELECT d.id, d.name, b.power_level AS level
        FROM bridge_characters_powers b
        INNER JOIN dim_discipline_types d ON d.id = b.power_id
        WHERE b.character_id = {$cid}
          AND b.power_kind = 'disciplinas'
        ORDER BY d.name
    "),
    'Rituales' => hg_mobile_bio_rows($link, "
        SELECT r.id, r.name, r.level
        FROM bridge_characters_powers b
        INNER JOIN fact_rites r ON r.id = b.power_id
        WHERE b.character_id = {$cid}
          AND b.power_kind = 'rituales'
        ORDER BY r.level, r.name
    "),
];

$items = hg_mobile_bio_rows($link, "
    SELECT i.id, i.item_type_id, t.pretty_id AS type_pretty, i.name, COALESCE(t.name, '') AS type_name
    FROM bridge_characters_items b
    INNER JOIN fact_items i ON i.id = b.item_id
    LEFT JOIN dim_item_types t ON t.id = i.item_type_id
    WHERE b.character_id = {$cid}
    ORDER BY t.name, i.name
");

$comments = hg_mobile_bio_rows($link, "
    SELECT nick, commented_at, message
    FROM fact_characters_comments
    WHERE character_id = {$cid}
    ORDER BY commented_at DESC, comment_time DESC
    LIMIT 20
");

$relationChronicleAnd = function_exists('hg_mobile_chronicle_exclusion_and') ? hg_mobile_chronicle_exclusion_and('p2') : ' AND p2.chronicle_id NOT IN (2,7) ';

$relations = [];
if (hg_mobile_bio_table_exists($link, 'bridge_characters_relations')) {
    $relations = array_merge($relations, hg_mobile_bio_rows($link, "
        SELECT cr.id, cr.relation_type, cr.tag, cr.importance, cr.description, cr.arrows,
               p2.id AS other_id, p2.name AS other_name, p2.alias AS other_alias,
               'outgoing' AS direction
        FROM bridge_characters_relations cr
        LEFT JOIN fact_characters p2 ON p2.id = cr.target_id
        WHERE cr.source_id = {$cid} {$relationChronicleAnd}
        ORDER BY cr.relation_type, p2.name
    "));
    $relations = array_merge($relations, hg_mobile_bio_rows($link, "
        SELECT cr.id, cr.relation_type, cr.tag, cr.importance, cr.description, cr.arrows,
               p2.id AS other_id, p2.name AS other_name, p2.alias AS other_alias,
               'incoming' AS direction
        FROM bridge_characters_relations cr
        LEFT JOIN fact_characters p2 ON p2.id = cr.source_id
        WHERE cr.target_id = {$cid} {$relationChronicleAnd}
        ORDER BY cr.relation_type, p2.name
    "));
    usort($relations, static function (array $a, array $b): int {
        return strcasecmp((string)($a['relation_type'] ?? ''), (string)($b['relation_type'] ?? ''));
    });
}

$chapterParticipation = [];
if (hg_mobile_bio_table_exists($link, 'bridge_chapters_characters') && hg_mobile_bio_table_exists($link, 'dim_chapters')) {
    $hasSeasonId = hg_mobile_bio_column_exists($link, 'dim_chapters', 'season_id');
    $hasChapterSeasonNumber = hg_mobile_bio_column_exists($link, 'dim_chapters', 'season_number');
    $hasSeasonKind = hg_mobile_bio_column_exists($link, 'dim_seasons', 'season_kind');
    $seasonKindExpr = $hasSeasonKind ? "COALESCE(s.season_kind, 'temporada')" : "'temporada'";
    $seasonJoin = '';
    $chapterSeasonExpr = $hasChapterSeasonNumber ? 'ac.season_number' : '0';
    $seasonSelect = "'' AS temporada_name, {$chapterSeasonExpr} AS season_number, 'temporada' AS season_kind";
    $seasonOrder = $hasChapterSeasonNumber ? 'ac.season_number' : 'ac.chapter_number';
    if (hg_mobile_bio_table_exists($link, 'dim_seasons')) {
        if ($hasSeasonId) {
            $seasonJoin = "LEFT JOIN dim_seasons s ON s.id = ac.season_id";
        } elseif ($hasChapterSeasonNumber) {
            $seasonJoin = "LEFT JOIN dim_seasons s ON s.season_number = ac.season_number";
        }
        if ($seasonJoin !== '') {
            $seasonNumberExpr = $hasChapterSeasonNumber ? 'COALESCE(s.season_number, ac.season_number)' : 'COALESCE(s.season_number, 0)';
            $seasonSelect = "COALESCE(s.name, '') AS temporada_name, {$seasonNumberExpr} AS season_number, {$seasonKindExpr} AS season_kind";
            $seasonOrder = $hasChapterSeasonNumber ? "COALESCE(s.sort_order, s.season_number, ac.season_number)" : "COALESCE(s.sort_order, s.season_number)";
        }
    }
    $chapterParticipation = hg_mobile_bio_rows($link, "
        SELECT ac.id, ac.name, ac.chapter_number, ac.played_date, {$seasonSelect}
        FROM dim_chapters ac
        INNER JOIN bridge_chapters_characters bcc ON bcc.chapter_id = ac.id
        {$seasonJoin}
        WHERE bcc.character_id = {$cid}
        ORDER BY {$seasonOrder}, ac.played_date, ac.chapter_number, ac.id
    ");
}

$timelineEvents = [];
if (hg_mobile_bio_table_exists($link, 'bridge_timeline_events_characters') && hg_mobile_bio_table_exists($link, 'fact_timeline_events')) {
    $hasEventPretty = hg_mobile_bio_column_exists($link, 'fact_timeline_events', 'pretty_id');
    $hasEventTypeId = hg_mobile_bio_column_exists($link, 'fact_timeline_events', 'event_type_id');
    $hasEventTypes = $hasEventTypeId && hg_mobile_bio_table_exists($link, 'dim_timeline_events_types');
    $eventPrettyExpr = $hasEventPretty ? 'e.pretty_id' : "''";
    $eventTypeJoin = $hasEventTypes ? 'LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id' : '';
    $eventTypeExpr = $hasEventTypes ? "COALESCE(t.name, 'Evento')" : "COALESCE(e.kind, 'Evento')";
    $timelineEvents = hg_mobile_bio_rows($link, "
        SELECT e.id, {$eventPrettyExpr} AS pretty_id, e.title, e.event_date, {$eventTypeExpr} AS type_name
        FROM bridge_timeline_events_characters bec
        INNER JOIN fact_timeline_events e ON e.id = bec.event_id
        {$eventTypeJoin}
        WHERE bec.character_id = {$cid}
        ORDER BY
            CASE WHEN e.event_date = '0000-00-00' OR e.event_date IS NULL THEN 1 ELSE 0 END ASC,
            e.event_date ASC,
            e.id ASC
        LIMIT 24
    ");
}

$characterDocs = [];
if (hg_mobile_bio_table_exists($link, 'bridge_characters_docs') && hg_mobile_bio_table_exists($link, 'fact_docs')) {
    $hasDocRelLabel = hg_mobile_bio_column_exists($link, 'bridge_characters_docs', 'relation_label');
    $hasDocSortOrder = hg_mobile_bio_column_exists($link, 'bridge_characters_docs', 'sort_order');
    $hasDocCategories = hg_mobile_bio_table_exists($link, 'dim_doc_categories');
    $docRelExpr = $hasDocRelLabel ? 'COALESCE(b.relation_label, "")' : '""';
    $docSortExpr = $hasDocSortOrder ? 'COALESCE(b.sort_order, 0)' : '0';
    $docOrder = $hasDocSortOrder ? 'b.sort_order ASC, d.title ASC' : 'd.title ASC';
    $docJoin = $hasDocCategories ? 'LEFT JOIN dim_doc_categories c ON c.id = d.section_id' : '';
    $docSectionExpr = $hasDocCategories ? "COALESCE(c.kind, '')" : "''";
    $characterDocs = hg_mobile_bio_rows($link, "
        SELECT b.doc_id, {$docRelExpr} AS relation_label, {$docSortExpr} AS sort_order,
               d.title, d.pretty_id, {$docSectionExpr} AS section_name
        FROM bridge_characters_docs b
        INNER JOIN fact_docs d ON d.id = b.doc_id
        {$docJoin}
        WHERE b.character_id = {$cid}
        ORDER BY {$docOrder}
    ");
}

$characterExternalLinks = [];
if (hg_mobile_bio_table_exists($link, 'bridge_characters_external_links') && hg_mobile_bio_table_exists($link, 'fact_external_links')) {
    $hasExtRelLabel = hg_mobile_bio_column_exists($link, 'bridge_characters_external_links', 'relation_label');
    $hasExtSortOrder = hg_mobile_bio_column_exists($link, 'bridge_characters_external_links', 'sort_order');
    $hasExternalActive = hg_mobile_bio_column_exists($link, 'fact_external_links', 'is_active');
    $hasExternalKind = hg_mobile_bio_column_exists($link, 'fact_external_links', 'kind');
    $hasExternalSource = hg_mobile_bio_column_exists($link, 'fact_external_links', 'source_label');
    $hasExternalDescription = hg_mobile_bio_column_exists($link, 'fact_external_links', 'description');
    $extRelExpr = $hasExtRelLabel ? 'COALESCE(b.relation_label, "")' : '""';
    $extSortExpr = $hasExtSortOrder ? 'COALESCE(b.sort_order, 0)' : '0';
    $extOrder = $hasExtSortOrder ? 'b.sort_order ASC, l.title ASC' : 'l.title ASC';
    $extActiveExpr = $hasExternalActive ? 'COALESCE(l.is_active, 1)' : '1';
    $extKindExpr = $hasExternalKind ? 'COALESCE(l.kind, "")' : '""';
    $extSourceExpr = $hasExternalSource ? 'COALESCE(l.source_label, "")' : '""';
    $extDescriptionExpr = $hasExternalDescription ? 'COALESCE(l.description, "")' : '""';
    $characterExternalLinks = hg_mobile_bio_rows($link, "
        SELECT b.external_link_id, {$extRelExpr} AS relation_label, {$extSortExpr} AS sort_order,
               l.title, l.url, {$extKindExpr} AS kind, {$extSourceExpr} AS source_label, {$extDescriptionExpr} AS description,
               {$extActiveExpr} AS is_active
        FROM bridge_characters_external_links b
        INNER JOIN fact_external_links l ON l.id = b.external_link_id
        WHERE b.character_id = {$cid}
        ORDER BY {$extOrder}
    ");
}
?>

<article class="hg-mobile-bio">
    <nav class="hg-mobile-local-nav">
        <a href="/characters?view=mobile">Volver a personajes</a>
    </nav>

    <header class="hg-mobile-bio-hero">
        <?php if ($avatar !== ''): ?>
            <img src="<?= hg_mobile_bio_h($avatar) ?>" alt="">
        <?php endif; ?>
        <div>
            <p class="hg-mobile-kicker"><?= hg_mobile_bio_h($character['type_name'] ?? '') ?></p>
            <h1><?= hg_mobile_bio_h($character['name'] ?? '') ?></h1>
            <?php if (!empty($character['alias'])): ?>
                <p><?= hg_mobile_bio_h($character['alias']) ?></p>
            <?php endif; ?>
        </div>
    </header>

    <section class="hg-mobile-section">
        <div class="hg-mobile-fact-grid">
            <div><span>Concepto</span><strong><?= hg_mobile_bio_h($character['concept'] ?? '') ?></strong></div>
            <div><span>Sistema</span><strong><?= hg_mobile_bio_h($character['system_name'] ?? '') ?></strong></div>
            <?php if ($status !== ''): ?>
                <div><span>Estado</span><strong><?= hg_mobile_bio_h($status) ?></strong></div>
            <?php endif; ?>
            <?php if (!empty($character['rank'])): ?>
                <div><span>Rango</span><strong><?= hg_mobile_bio_h($character['rank']) ?></strong></div>
            <?php endif; ?>
            <?php if (!empty($character['garou_name'])): ?>
                <div><span>Nombre Garou</span><strong><?= hg_mobile_bio_h($character['garou_name']) ?></strong></div>
            <?php endif; ?>
            <?php foreach ($detailLinks as $label => $html): ?>
                <?php if (trim(strip_tags((string)$html)) === '') { continue; } ?>
                <div><span><?= hg_mobile_bio_h($label) ?></span><strong><?= $html ?></strong></div>
            <?php endforeach; ?>
            <div><span>ID</span><strong><?= $characterId ?></strong></div>
        </div>
    </section>

    <?php if ($deathDescription !== ''): ?>
        <section class="hg-mobile-section">
            <h2>Muerte</h2>
            <p><?= hg_mobile_bio_h($deathDescription) ?></p>
        </section>
    <?php endif; ?>

    <?php if (trim(strip_tags($infoHtml)) !== ''): ?>
        <section class="hg-mobile-section hg-mobile-prose">
            <h2>Información</h2>
            <?= $infoHtml ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($relations)): ?>
        <section class="hg-mobile-section">
            <h2>Relaciones</h2>
            <div class="hg-mobile-list hg-mobile-linked-list">
                <?php foreach ($relations as $relation): ?>
                    <?php
                        $otherId = (int)($relation['other_id'] ?? 0);
                        $otherName = trim((string)($relation['other_name'] ?? ''));
                        $otherAlias = trim((string)($relation['other_alias'] ?? ''));
                        $relationType = trim((string)($relation['relation_type'] ?? 'Relación'));
                        $relationTag = trim((string)($relation['tag'] ?? ''));
                        $relationDesc = trim((string)($relation['description'] ?? ''));
                        $direction = (string)($relation['direction'] ?? '') === 'incoming' ? 'Entrante' : 'Saliente';
                        $otherHref = hg_mobile_bio_pretty_href($link, 'fact_characters', '/characters', $otherId);
                    ?>
                    <div>
                        <strong><?= hg_mobile_bio_link($otherHref, $otherName !== '' ? $otherName : ('Personaje #' . $otherId)) ?></strong>
                        <span><?= hg_mobile_bio_h($relationType) ?> · <?= hg_mobile_bio_h($direction) ?><?= $relationTag !== '' ? ' · ' . hg_mobile_bio_h($relationTag) : '' ?><?= $otherAlias !== '' ? ' · ' . hg_mobile_bio_h($otherAlias) : '' ?></span>
                        <?php if ($relationDesc !== ''): ?>
                            <p><?= nl2br(hg_mobile_bio_h($relationDesc)) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($hasCharacterSheet && !empty($traitsByKind)): ?>
        <section class="hg-mobile-section">
            <h2>Rasgos</h2>
            <?php foreach ($traitsByKind as $kind => $traits): ?>
                <details class="hg-mobile-details" open>
                    <summary><?= hg_mobile_bio_h($kind) ?></summary>
                    <div class="hg-mobile-trait-list">
                        <?php foreach ($traits as $trait): ?>
                            <div>
                                <span><?= hg_mobile_bio_link(hg_mobile_bio_pretty_href($link, 'dim_traits', '/rules/traits', (int)($trait['id'] ?? 0)), (string)($trait['name'] ?? '')) ?></span>
                                <strong><?= (int)($trait['value'] ?? 0) ?> <?= hg_mobile_bio_trait_dots((int)($trait['value'] ?? 0)) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($hasCharacterSheet && !empty($merits)): ?>
        <section class="hg-mobile-section">
            <h2>Méritos y defectos</h2>
            <div class="hg-mobile-list">
                <?php foreach ($merits as $merit): ?>
                    <div>
                        <strong><?= hg_mobile_bio_link(hg_mobile_bio_pretty_href($link, 'dim_merits_flaws', '/rules/merits-flaws', (int)($merit['id'] ?? 0)), (string)($merit['name'] ?? '')) ?></strong>
                        <span><?= hg_mobile_bio_h($merit['kind'] ?? '') ?><?= isset($merit['level']) && $merit['level'] !== null ? ' ' . (int)$merit['level'] : '' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($hasCharacterSheet && !empty($conditions)): ?>
        <section class="hg-mobile-section">
            <h2>Condiciones</h2>
            <div class="hg-mobile-list">
                <?php foreach ($conditions as $condition): ?>
                    <div>
                        <strong><?= hg_mobile_bio_link(hg_mobile_bio_pretty_href($link, 'dim_character_conditions', '/rules/conditions', (int)($condition['id'] ?? 0)), (string)($condition['name'] ?? '')) ?></strong>
                        <span><?= hg_mobile_bio_h($condition['category'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($hasCharacterSheet): ?>
    <?php foreach ($powers as $powerTitle => $powerRows): ?>
        <?php if (empty($powerRows)) { continue; } ?>
        <section class="hg-mobile-section">
            <h2><?= hg_mobile_bio_h($powerTitle) ?></h2>
            <div class="hg-mobile-list">
                <?php foreach ($powerRows as $power): ?>
                    <?php
                        $powerRoutes = [
                            'Dones' => ['fact_gifts', '/powers/gift'],
                            'Disciplinas' => ['dim_discipline_types', '/powers/discipline/type'],
                            'Rituales' => ['fact_rites', '/powers/rite'],
                        ];
                        $powerRoute = $powerRoutes[$powerTitle] ?? ['fact_rites', '/powers/rite'];
                    ?>
                    <div>
                        <strong><?= hg_mobile_bio_link(hg_mobile_bio_pretty_href($link, $powerRoute[0], $powerRoute[1], (int)($power['id'] ?? 0)), (string)($power['name'] ?? '')) ?></strong>
                        <?php if (isset($power['level']) && $power['level'] !== null && (string)$power['level'] !== ''): ?>
                            <span>Nivel <?= (int)$power['level'] ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($hasCharacterSheet && !empty($items)): ?>
        <section class="hg-mobile-section">
            <h2>Inventario</h2>
            <div class="hg-mobile-list">
                <?php foreach ($items as $item): ?>
                    <?php
                        $itemType = (string)($item['type_pretty'] ?? $item['item_type_id'] ?? '');
                        $itemSlug = function_exists('get_pretty_id') ? (get_pretty_id($link, 'fact_items', (int)($item['id'] ?? 0)) ?: (int)($item['id'] ?? 0)) : (int)($item['id'] ?? 0);
                        $itemHref = '/inventory/' . rawurlencode($itemType) . '/' . rawurlencode((string)$itemSlug);
                    ?>
                    <div>
                        <strong><?= hg_mobile_bio_link($itemHref, (string)($item['name'] ?? '')) ?></strong>
                        <span><?= hg_mobile_bio_h($item['type_name'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($chapterParticipation) || !empty($timelineEvents)): ?>
        <section class="hg-mobile-section">
            <h2>Participación</h2>

            <?php if (!empty($chapterParticipation)): ?>
                <details class="hg-mobile-details" open>
                    <summary>Capítulos</summary>
                    <div class="hg-mobile-list hg-mobile-linked-list">
                        <?php foreach ($chapterParticipation as $chapter): ?>
                            <?php
                                $chapterId = (int)($chapter['id'] ?? 0);
                                $chapterHref = hg_mobile_bio_pretty_href($link, 'dim_chapters', '/chapters', $chapterId);
                                $seasonName = trim((string)($chapter['temporada_name'] ?? ''));
                                $seasonNumber = trim((string)($chapter['season_number'] ?? ''));
                                $seasonLabel = $seasonName !== '' ? $seasonName : ($seasonNumber !== '' ? 'Temporada ' . $seasonNumber : '');
                                $playedDate = hg_mobile_bio_date($chapter['played_date'] ?? '');
                            ?>
                            <div>
                                <strong><?= hg_mobile_bio_link($chapterHref, (string)($chapter['name'] ?? '')) ?></strong>
                                <span><?= hg_mobile_bio_h($seasonLabel) ?><?= $playedDate !== '' ? ' · ' . hg_mobile_bio_h($playedDate) : '' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if (!empty($timelineEvents)): ?>
                <details class="hg-mobile-details" open>
                    <summary>Eventos</summary>
                    <div class="hg-mobile-list hg-mobile-linked-list">
                        <?php foreach ($timelineEvents as $event): ?>
                            <?php
                                $eventId = (int)($event['id'] ?? 0);
                                $eventSlug = trim((string)($event['pretty_id'] ?? ''));
                                $eventHref = '/timeline/event/' . rawurlencode($eventSlug !== '' ? $eventSlug : (string)$eventId);
                                $eventDate = hg_mobile_bio_date($event['event_date'] ?? '');
                            ?>
                            <div>
                                <strong><?= hg_mobile_bio_link($eventHref, (string)($event['title'] ?? 'Evento')) ?></strong>
                                <span><?= hg_mobile_bio_h($event['type_name'] ?? 'Evento') ?><?= $eventDate !== '' ? ' · ' . hg_mobile_bio_h($eventDate) : '' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($characterDocs) || !empty($characterExternalLinks)): ?>
        <section class="hg-mobile-section">
            <h2>Documentos y enlaces</h2>

            <?php if (!empty($characterDocs)): ?>
                <details class="hg-mobile-details" open>
                    <summary>Documentos internos</summary>
                    <div class="hg-mobile-list hg-mobile-linked-list">
                        <?php foreach ($characterDocs as $doc): ?>
                            <?php
                                $docId = (int)($doc['doc_id'] ?? 0);
                                $docHref = hg_mobile_bio_pretty_href($link, 'fact_docs', '/documents', $docId);
                                $docSection = trim((string)($doc['section_name'] ?? 'Documento'));
                                $docRel = trim((string)($doc['relation_label'] ?? ''));
                            ?>
                            <div>
                                <strong><?= hg_mobile_bio_link($docHref, (string)($doc['title'] ?? ('Documento #' . $docId))) ?></strong>
                                <span><?= hg_mobile_bio_h($docSection !== '' ? $docSection : 'Documento') ?><?= $docRel !== '' ? ' · ' . hg_mobile_bio_h($docRel) : '' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if (!empty($characterExternalLinks)): ?>
                <details class="hg-mobile-details" open>
                    <summary>Enlaces externos</summary>
                    <div class="hg-mobile-list hg-mobile-linked-list">
                        <?php foreach ($characterExternalLinks as $external): ?>
                            <?php
                                $externalTitle = trim((string)($external['title'] ?? ''));
                                $externalUrl = trim((string)($external['url'] ?? '#'));
                                $externalKind = trim((string)($external['kind'] ?? 'Enlace'));
                                $externalSource = trim((string)($external['source_label'] ?? ''));
                                $externalRel = trim((string)($external['relation_label'] ?? ''));
                                $externalActive = (int)($external['is_active'] ?? 1) === 1;
                            ?>
                            <div>
                                <strong><a href="<?= hg_mobile_bio_h($externalUrl !== '' ? $externalUrl : '#') ?>" target="_blank" rel="noopener noreferrer"><?= hg_mobile_bio_h($externalTitle !== '' ? $externalTitle : $externalUrl) ?></a></strong>
                                <span><?= hg_mobile_bio_h($externalKind !== '' ? $externalKind : 'Enlace') ?><?= $externalSource !== '' ? ' · ' . hg_mobile_bio_h($externalSource) : '' ?><?= $externalRel !== '' ? ' · ' . hg_mobile_bio_h($externalRel) : '' ?><?= !$externalActive ? ' · inactivo' : '' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($comments)): ?>
        <section class="hg-mobile-section">
            <h2>Comentarios</h2>
            <div class="hg-mobile-list">
                <?php foreach ($comments as $comment): ?>
                    <div>
                        <strong><?= hg_mobile_bio_h($comment['nick'] ?? '') ?></strong>
                        <span><?= hg_mobile_bio_h($comment['commented_at'] ?? '') ?></span>
                        <p><?= nl2br(hg_mobile_bio_h($comment['message'] ?? '')) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>
