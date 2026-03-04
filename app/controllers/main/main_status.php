<?php setMetaFromPage("Estado | Heaven's Gate", "Estado general de la campaña y su contenido.", null, 'website'); ?>
<?php

if (!$link) {
    die("Error de conexion a la base de datos: " . mysqli_connect_error());
}

include("app/partials/main_nav_bar.php");
echo "<h2>Estado</h2>";

function h_status($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function status_table_exists(mysqli $link, string $table): bool {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];

    $ok = false;
    if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?")) {
        $st->bind_param('s', $table);
        $st->execute();
        $st->bind_result($count);
        $st->fetch();
        $ok = ((int)$count > 0);
        $st->close();
    }

    $cache[$table] = $ok;
    return $ok;
}

function status_column_exists(mysqli $link, string $table, string $column): bool {
    static $cache = [];
    $key = $table . ':' . $column;
    if (isset($cache[$key])) return $cache[$key];

    $ok = false;
    if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
        $st->bind_param('ss', $table, $column);
        $st->execute();
        $st->bind_result($count);
        $st->fetch();
        $ok = ((int)$count > 0);
        $st->close();
    }

    $cache[$key] = $ok;
    return $ok;
}

function status_count_table(mysqli $link, string $table, string $where = '1=1'): ?int {
    if (!status_table_exists($link, $table)) return null;
    $sql = "SELECT COUNT(*) AS total FROM `$table` WHERE $where";
    $rs = mysqli_query($link, $sql);
    if (!$rs) return null;
    $row = mysqli_fetch_assoc($rs);
    mysqli_free_result($rs);
    return isset($row['total']) ? (int)$row['total'] : 0;
}

function status_count_distinct(mysqli $link, string $table, string $column, string $where = '1=1'): ?int {
    if (!status_table_exists($link, $table)) return null;
    if (!status_column_exists($link, $table, $column)) return null;
    $sql = "SELECT COUNT(DISTINCT `$column`) AS total FROM `$table` WHERE $where";
    $rs = mysqli_query($link, $sql);
    if (!$rs) return null;
    $row = mysqli_fetch_assoc($rs);
    mysqli_free_result($rs);
    return isset($row['total']) ? (int)$row['total'] : 0;
}

function status_ratio(int $part, int $total): string {
    if ($total <= 0) return '0%';
    return number_format(($part * 100) / $total, 1, ',', '.') . '%';
}

function status_row(string $label, ?int $value): void {
    if ($value === null) return;
    echo "<div class='renglonStatusIz'>" . h_status($label) . ":</div>";
    echo "<div class='renglonStatusDe'>" . number_format($value, 0, ',', '.') . "</div>";
}

function status_section(string $title, array $rows): void {
    $hasData = false;
    foreach ($rows as $r) {
        if (isset($r['value']) && $r['value'] !== null) { $hasData = true; break; }
    }
    if (!$hasData) return;

    echo "<fieldset class='renglonPaginaDon'>";
    echo "<legend>" . h_status($title) . "</legend>";
    foreach ($rows as $r) {
        status_row((string)$r['label'], isset($r['value']) ? (int)$r['value'] : null);
    }
    echo "</fieldset><br/>";
}

$players = status_count_table($link, 'dim_players');
$chronicles = status_count_table($link, 'dim_chronicles');
$characters = status_count_table($link, 'fact_characters');
$chapters = status_count_table($link, 'dim_chapters');
$docs = status_count_table($link, 'fact_docs');
$items = status_count_table($link, 'fact_items');
$powers = status_count_table($link, 'fact_gifts');
$rites = status_count_table($link, 'fact_rites');
$disciplines = status_count_table($link, 'fact_discipline_powers');

$globalShown = 0;
foreach ([$characters, $chapters, $docs, $items, $powers, $rites, $disciplines] as $v) {
    if ($v !== null) $globalShown += $v;
}

status_section('Estado general del repositorio', [
    ['label' => 'Personajes', 'value' => $characters],
    ['label' => 'Capítulos', 'value' => $chapters],
    ['label' => 'Documentos', 'value' => $docs],
    ['label' => 'Objetos', 'value' => $items],
    ['label' => 'Poderes (Dones + Rituales + Disciplinas)', 'value' => (($powers ?? 0) + ($rites ?? 0) + ($disciplines ?? 0))],
    ['label' => 'Entradas visibles de contenido', 'value' => $globalShown],
]);

$charPj = status_column_exists($link, 'fact_characters', 'character_kind') ? status_count_table($link, 'fact_characters', "character_kind = 'pj'") : null;
$charNpc = status_column_exists($link, 'fact_characters', 'character_kind') ? status_count_table($link, 'fact_characters', "character_kind <> 'pj' OR character_kind IS NULL OR character_kind = ''") : null;
$charWithImage = status_column_exists($link, 'fact_characters', 'image_url') ? status_count_table($link, 'fact_characters', "image_url IS NOT NULL AND TRIM(image_url) <> ''") : null;
$charWithPlayer = status_column_exists($link, 'fact_characters', 'player_id') ? status_count_table($link, 'fact_characters', 'player_id > 0') : null;
$charTypes = status_count_table($link, 'dim_character_types');
$relations = status_count_table($link, 'bridge_characters_relations');
$orgs = status_count_table($link, 'dim_organizations');
$groups = status_count_table($link, 'dim_groups');
$comments = status_count_table($link, 'fact_characters_comments');

status_section('Biografías y Personajes', [
    ['label' => 'Jugadores', 'value' => $players],
    ['label' => 'Crónicas', 'value' => $chronicles],
    ['label' => 'Categorias de personajes', 'value' => $charTypes],
    ['label' => 'Personajes', 'value' => $characters],
    ['label' => 'Personajes jugadores', 'value' => $charPj],
    ['label' => 'Personajes no jugadores', 'value' => $charNpc],
    ['label' => 'Personajes con imagen', 'value' => $charWithImage],
    ['label' => 'Personajes asociados a jugador', 'value' => $charWithPlayer],
    ['label' => 'Relaciones entre personajes', 'value' => $relations],
    ['label' => 'Clanes/organizaciones', 'value' => $orgs],
    ['label' => 'Manadas/grupos', 'value' => $groups],
    ['label' => 'Comentarios en biografías', 'value' => $comments],
]);

$seasons = status_count_table($link, 'dim_seasons');
$hasSeasonKind = status_column_exists($link, 'dim_seasons', 'season_kind');
$seasonMain = status_count_table($link, 'dim_seasons', "season_kind = 'temporada'");
$seasonInciso = $hasSeasonKind ? status_count_table($link, 'dim_seasons', "season_kind = 'inciso'") : null;
$seasonSpecial = $hasSeasonKind ? status_count_table($link, 'dim_seasons', "season_kind = 'especial'") : null;
$seasonPersonal = status_count_table($link, 'dim_seasons', "season_kind = 'historia_personal'");
$seasonFinished = status_column_exists($link, 'dim_seasons', 'finished') ? status_count_table($link, 'dim_seasons', 'finished = 1') : null;
$chapterWithSynopsis = status_column_exists($link, 'dim_chapters', 'synopsis') ? status_count_table($link, 'dim_chapters', "synopsis IS NOT NULL AND TRIM(synopsis) <> ''") : null;
$chapterPlayed = status_column_exists($link, 'dim_chapters', 'played_date') ? status_count_table($link, 'dim_chapters', "played_date IS NOT NULL AND played_date <> '0000-00-00'") : null;
$chapterLinks = status_count_table($link, 'bridge_chapters_characters');

status_section('Temporadas y Capítulos', [
    ['label' => 'Temporadas / historias personales', 'value' => $seasons],
    ['label' => 'Temporadas principales', 'value' => $seasonMain],
    ['label' => 'Incisos', 'value' => $seasonInciso],
    ['label' => 'Historias personales', 'value' => $seasonPersonal],
    ['label' => 'Especiales', 'value' => $seasonSpecial],
    ['label' => 'Temporadas finalizadas', 'value' => $seasonFinished],
    ['label' => 'Capítulos', 'value' => $chapters],
    ['label' => 'Capítulos con resumen', 'value' => $chapterWithSynopsis],
    ['label' => 'Capítulos con fecha de juego', 'value' => $chapterPlayed],
    ['label' => 'Participaciones personaje-capítulo', 'value' => $chapterLinks],
]);

$traitKinds = status_count_distinct($link, 'dim_traits', 'kind', "kind IS NOT NULL AND TRIM(kind) <> ''");
$traits = status_count_table($link, 'dim_traits');
$merits = status_count_table($link, 'dim_merits_flaws');
$archetypes = status_count_table($link, 'dim_archetypes');
$maneuvers = status_count_table($link, 'fact_combat_maneuvers');

status_section('Reglamento', [
    ['label' => 'Categorías de rasgos', 'value' => $traitKinds],
    ['label' => 'Rasgos', 'value' => $traits],
    ['label' => 'Méritos y Defectos', 'value' => $merits],
    ['label' => 'Arquetipos', 'value' => $archetypes],
    ['label' => 'Maniobras de combate', 'value' => $maneuvers],
]);

$docCats = status_count_table($link, 'dim_doc_categories');
$itemTypes = status_count_table($link, 'dim_item_types');
$biblio = status_count_table($link, 'dim_bibliographies');
$itemLinks = status_count_table($link, 'bridge_characters_items');
$docWithText = status_column_exists($link, 'fact_docs', 'content') ? status_count_table($link, 'fact_docs', "content IS NOT NULL AND TRIM(content) <> ''") : null;

status_section('Documentacion e inventario', [
    ['label' => 'Bibliografias / origenes', 'value' => $biblio],
    ['label' => 'Categorias de documentos', 'value' => $docCats],
    ['label' => 'Documentos', 'value' => $docs],
    ['label' => 'Documentos con contenido', 'value' => $docWithText],
    ['label' => 'Categorias de objetos', 'value' => $itemTypes],
    ['label' => 'Objetos', 'value' => $items],
    ['label' => 'Vinculos personaje-objeto', 'value' => $itemLinks],
]);

$giftKinds = status_count_distinct($link, 'fact_gifts', 'kind', "kind IS NOT NULL AND TRIM(kind) <> ''");
$giftTypes = status_count_table($link, 'dim_gift_types');
$riteTypes = status_count_table($link, 'dim_rite_types');
$totemTypes = status_count_table($link, 'dim_totem_types');
$totems = status_count_table($link, 'dim_totems');
$disciplineTypes = status_count_table($link, 'dim_discipline_types');
$charPowers = status_count_table($link, 'bridge_characters_powers');
$meritLinks = status_count_table($link, 'bridge_characters_merits_flaws');

status_section('Poderes sobrenaturales', [
    ['label' => 'Categorias de Dones (por Grupo)', 'value' => $giftKinds],
    ['label' => 'Tipos de Dones', 'value' => $giftTypes],
    ['label' => 'Dones', 'value' => $powers],
    ['label' => 'Tipos de Rituales', 'value' => $riteTypes],
    ['label' => 'Rituales', 'value' => $rites],
    ['label' => 'Tipos de Tótems', 'value' => $totemTypes],
    ['label' => 'Tótems', 'value' => $totems],
    ['label' => 'Tipos de Disciplinas', 'value' => $disciplineTypes],
    ['label' => 'Poderes de Disciplina', 'value' => $disciplines],
    ['label' => 'Vínculos personaje-poder', 'value' => $charPowers],
    ['label' => 'Vínculos personaje-merito/defecto', 'value' => $meritLinks],
]);

$systems = status_count_table($link, 'dim_systems');
$forms = status_count_table($link, 'dim_forms');
$breeds = status_count_table($link, 'dim_breeds');
$auspices = status_count_table($link, 'dim_auspices');
$tribes = status_count_table($link, 'dim_tribes');
$miscSystems = status_count_table($link, 'fact_misc_systems');
$systemResources = status_count_table($link, 'dim_systems_resources');
$systemResBridge = status_count_table($link, 'bridge_systems_resources_to_system');
$charResBridge = status_count_table($link, 'bridge_characters_system_resources');

status_section('Sistemas de juego', [
    ['label' => 'Sistemas', 'value' => $systems],
    ['label' => 'Formas', 'value' => $forms],
    ['label' => 'Razas', 'value' => $breeds],
    ['label' => 'Auspicios', 'value' => $auspices],
    ['label' => 'Tribus', 'value' => $tribes],
    ['label' => 'Datos misceláneos de sistema', 'value' => $miscSystems],
    ['label' => 'Catálogo de recursos de sistema', 'value' => $systemResources],
    ['label' => 'Mapeos sistema-recurso', 'value' => $systemResBridge],
    ['label' => 'Recursos en personajes', 'value' => $charResBridge],
]);

$news = status_count_table($link, 'fact_admin_posts');
$soundtracks = status_count_table($link, 'dim_soundtracks');
$soundtrackLinks = status_count_table($link, 'bridge_soundtrack_links');
$timelineEvents = status_count_table($link, 'fact_timeline_events');
$timelineLinks = status_count_table($link, 'bridge_timeline_links');
$plots = status_count_table($link, 'dim_parties');
$plotMembers = status_count_table($link, 'fact_party_members');
$plotChanges = status_count_table($link, 'fact_party_members_changes');
$menuItems = status_count_table($link, 'dim_menu_items');
$maps = status_count_table($link, 'dim_maps');
$mapCats = status_count_table($link, 'dim_map_categories');
$pois = status_count_table($link, 'fact_map_pois');
$areas = status_count_table($link, 'fact_map_areas');

status_section('Contenido y herramientas', [
    ['label' => 'Noticias', 'value' => $news],
    ['label' => 'Temas de banda sonora', 'value' => $soundtracks],
    ['label' => 'Vínculos de banda sonora', 'value' => $soundtrackLinks],
    ['label' => 'Eventos de la línea temporal', 'value' => $timelineEvents],
    ['label' => 'Vínculos de la línea temporal', 'value' => $timelineLinks],
    ['label' => 'Grupos de juego activos', 'value' => $plots],
    ['label' => 'Miembros en activo', 'value' => $plotMembers],
    ['label' => 'Cambios históricos', 'value' => $plotChanges],
    ['label' => 'Elementos de menu dinámico', 'value' => $menuItems],
    ['label' => 'Mapas', 'value' => $maps],
    ['label' => 'Categorías de mapa', 'value' => $mapCats],
    ['label' => 'Puntos de interés', 'value' => $pois],
    ['label' => 'Áreas en el mapa', 'value' => $areas],
]);

if (($characters ?? 0) > 0 && $charWithImage !== null) {
    echo "<fieldset class='renglonPaginaDon'>";
    echo "<legend>Cobertura rapida</legend>";
    echo "<div class='renglonStatusIz'>Personajes con imagen:</div>";
    echo "<div class='renglonStatusDe'>" . number_format($charWithImage, 0, ',', '.') . " / " . number_format((int)$characters, 0, ',', '.') . " (" . status_ratio((int)$charWithImage, (int)$characters) . ")</div>";

    if (($chapters ?? 0) > 0 && $chapterWithSynopsis !== null) {
        echo "<div class='renglonStatusIz'>Capitulos con resumen:</div>";
        echo "<div class='renglonStatusDe'>" . number_format($chapterWithSynopsis, 0, ',', '.') . " / " . number_format((int)$chapters, 0, ',', '.') . " (" . status_ratio((int)$chapterWithSynopsis, (int)$chapters) . ")</div>";
    }

    if (($docs ?? 0) > 0 && $docWithText !== null) {
        echo "<div class='renglonStatusIz'>Documentos con contenido:</div>";
        echo "<div class='renglonStatusDe'>" . number_format($docWithText, 0, ',', '.') . " / " . number_format((int)$docs, 0, ',', '.') . " (" . status_ratio((int)$docWithText, (int)$docs) . ")</div>";
    }

    echo "</fieldset><br/>";
}
?>
