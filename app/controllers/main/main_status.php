<?php setMetaFromPage("Estado | Heaven's Gate", "Estado general de la campaña y su contenido.", null, 'website'); ?>
<?php
include_once(__DIR__ . '/../../helpers/public_response.php');

header('Content-Type: text/html; charset=utf-8');
if ($link) {
    mysqli_set_charset($link, 'utf8mb4');
}

if (!$link) {
    hg_public_log_error('main_status', 'missing DB connection');
    hg_public_render_error('Estado no disponible', 'No se pudo cargar el estado general en este momento.');
    return;
}

include("app/partials/main_nav_bar.php");
echo "<h2>Estado</h2>";

function h_status($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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

function status_count_nonempty(mysqli $link, string $table, string $column): ?int {
    if (!status_table_exists($link, $table)) return null;
    if (!status_column_exists($link, $table, $column)) return null;
    return status_count_table($link, $table, "`$column` IS NOT NULL AND TRIM(`$column`) <> ''");
}

function status_sum(array $values): ?int {
    $hasData = false;
    $total = 0;
    foreach ($values as $value) {
        if ($value !== null) {
            $hasData = true;
            $total += (int)$value;
        }
    }
    return $hasData ? $total : null;
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
        if (isset($r['value']) && $r['value'] !== null) {
            $hasData = true;
            break;
        }
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
$realities = status_count_table($link, 'dim_realities');
$characters = status_count_table($link, 'fact_characters');
$chapters = status_count_table($link, 'dim_chapters');
$docs = status_count_table($link, 'fact_docs');
$items = status_count_table($link, 'fact_items');
$powers = status_count_table($link, 'fact_gifts');
$rites = status_count_table($link, 'fact_rites');
$disciplines = status_count_table($link, 'fact_discipline_powers');
$conditions = status_count_table($link, 'dim_character_conditions');
$timelineEvents = status_count_table($link, 'fact_timeline_events');
$gameCards = status_count_table($link, 'fact_game_card_collection');
$prettyAliases = status_count_table($link, 'fact_pretty_id_aliases');
$webConfig = status_count_table($link, 'dim_web_configuration');

$globalShown = status_sum([
    $characters,
    $chapters,
    $docs,
    $items,
    $powers,
    $rites,
    $disciplines,
    $conditions,
    $timelineEvents,
    $gameCards,
]);

status_section('Estado general del repositorio', [
    ['label' => 'Jugadores', 'value' => $players],
    ['label' => 'Crónicas', 'value' => $chronicles],
    ['label' => 'Realidades', 'value' => $realities],
    ['label' => 'Personajes', 'value' => $characters],
    ['label' => 'Capítulos', 'value' => $chapters],
    ['label' => 'Documentos', 'value' => $docs],
    ['label' => 'Objetos', 'value' => $items],
    ['label' => 'Poderes (Dones + Rituales + Disciplinas)', 'value' => status_sum([$powers, $rites, $disciplines])],
    ['label' => 'Condiciones', 'value' => $conditions],
    ['label' => 'Eventos de la línea temporal', 'value' => $timelineEvents],
    ['label' => 'Cartas del minijuego', 'value' => $gameCards],
    ['label' => 'Alias de URL bonita', 'value' => $prettyAliases],
    ['label' => 'Entradas de configuración web', 'value' => $webConfig],
    ['label' => 'Entradas visibles de contenido', 'value' => $globalShown],
]);

$hasCharacterKind = status_column_exists($link, 'fact_characters', 'character_kind');
$charPj = $hasCharacterKind ? status_count_table($link, 'fact_characters', "character_kind = 'pj'") : null;
$charNpc = $hasCharacterKind ? status_count_table($link, 'fact_characters', "character_kind <> 'pj' OR character_kind IS NULL OR character_kind = ''") : null;
$charWithImage = status_count_nonempty($link, 'fact_characters', 'image_url');
$charWithPlayer = status_column_exists($link, 'fact_characters', 'player_id') ? status_count_table($link, 'fact_characters', 'player_id > 0') : null;
$charWithInfo = status_count_nonempty($link, 'fact_characters', 'info_text');
$charWithNotes = status_count_nonempty($link, 'fact_characters', 'notes');
$charAbandoned = status_column_exists($link, 'fact_characters', 'is_abandoned') ? status_count_table($link, 'fact_characters', 'is_abandoned = 1') : null;
$charTypes = status_count_table($link, 'dim_character_types');
$charStatuses = status_count_table($link, 'dim_character_status');
$relations = status_count_table($link, 'bridge_characters_relations');
$orgs = status_count_table($link, 'dim_organizations');
$orgDepartments = status_count_table($link, 'dim_organization_departments');
$groups = status_count_table($link, 'dim_groups');
$charOrgLinks = status_count_table($link, 'bridge_characters_organizations');
$charGroupLinks = status_count_table($link, 'bridge_characters_groups');
$orgGroupLinks = status_count_table($link, 'bridge_organizations_groups');
$orgChartLinks = status_count_table($link, 'bridge_characters_org');
$comments = status_count_table($link, 'fact_characters_comments');
$deaths = status_count_table($link, 'fact_characters_deaths');
$charDocLinks = status_count_table($link, 'bridge_characters_docs');
$charExternalBridge = status_count_table($link, 'bridge_characters_external_links');
$externalLinks = status_count_table($link, 'fact_external_links');

status_section('Biografías y personajes', [
    ['label' => 'Jugadores', 'value' => $players],
    ['label' => 'Crónicas', 'value' => $chronicles],
    ['label' => 'Realidades', 'value' => $realities],
    ['label' => 'Categorías de personajes', 'value' => $charTypes],
    ['label' => 'Estados de personaje', 'value' => $charStatuses],
    ['label' => 'Personajes', 'value' => $characters],
    ['label' => 'Personajes jugadores', 'value' => $charPj],
    ['label' => 'Personajes no jugadores', 'value' => $charNpc],
    ['label' => 'Personajes abandonados', 'value' => $charAbandoned],
    ['label' => 'Personajes con imagen', 'value' => $charWithImage],
    ['label' => 'Personajes con biografía', 'value' => $charWithInfo],
    ['label' => 'Personajes con notas', 'value' => $charWithNotes],
    ['label' => 'Personajes asociados a jugador', 'value' => $charWithPlayer],
    ['label' => 'Relaciones entre personajes', 'value' => $relations],
    ['label' => 'Comentarios en biografías', 'value' => $comments],
    ['label' => 'Muertes registradas', 'value' => $deaths],
    ['label' => 'Clanes y organizaciones', 'value' => $orgs],
    ['label' => 'Departamentos de organizaciones', 'value' => $orgDepartments],
    ['label' => 'Manadas y grupos', 'value' => $groups],
    ['label' => 'Vínculos personaje-organización', 'value' => $charOrgLinks],
    ['label' => 'Vínculos personaje-manada', 'value' => $charGroupLinks],
    ['label' => 'Vínculos organización-manada', 'value' => $orgGroupLinks],
    ['label' => 'Puestos del organigrama', 'value' => $orgChartLinks],
    ['label' => 'Vínculos personaje-documento', 'value' => $charDocLinks],
    ['label' => 'Vínculos personaje-enlace externo', 'value' => $charExternalBridge],
    ['label' => 'Enlaces externos catalogados', 'value' => $externalLinks],
]);

$seasons = status_count_table($link, 'dim_seasons');
$hasSeasonKind = status_column_exists($link, 'dim_seasons', 'season_kind');
$seasonMain = $hasSeasonKind ? status_count_table($link, 'dim_seasons', "season_kind = 'temporada'") : $seasons;
$seasonInciso = $hasSeasonKind ? status_count_table($link, 'dim_seasons', "season_kind = 'inciso'") : null;
$seasonSpecial = $hasSeasonKind ? status_count_table($link, 'dim_seasons', "season_kind = 'especial'") : null;
$seasonPersonal = $hasSeasonKind ? status_count_table($link, 'dim_seasons', "season_kind = 'historia_personal'") : null;
$seasonFinished = status_column_exists($link, 'dim_seasons', 'finished') ? status_count_table($link, 'dim_seasons', 'finished = 1') : null;
$chapterWithSynopsis = status_count_nonempty($link, 'dim_chapters', 'synopsis');
$chapterPlayed = status_column_exists($link, 'dim_chapters', 'played_date') ? status_count_table($link, 'dim_chapters', "played_date IS NOT NULL AND played_date <> '0000-00-00'") : null;
$chapterLinks = status_count_table($link, 'bridge_chapters_characters');
$timelineChapterLinks = status_count_table($link, 'bridge_timeline_events_chapters');

status_section('Temporadas y capítulos', [
    ['label' => 'Temporadas e historias personales', 'value' => $seasons],
    ['label' => 'Temporadas principales', 'value' => $seasonMain],
    ['label' => 'Incisos', 'value' => $seasonInciso],
    ['label' => 'Historias personales', 'value' => $seasonPersonal],
    ['label' => 'Especiales', 'value' => $seasonSpecial],
    ['label' => 'Temporadas finalizadas', 'value' => $seasonFinished],
    ['label' => 'Capítulos', 'value' => $chapters],
    ['label' => 'Capítulos con resumen', 'value' => $chapterWithSynopsis],
    ['label' => 'Capítulos con fecha de juego', 'value' => $chapterPlayed],
    ['label' => 'Participaciones personaje-capítulo', 'value' => $chapterLinks],
    ['label' => 'Vínculos evento-capítulo', 'value' => $timelineChapterLinks],
]);

$traitKinds = status_count_distinct($link, 'dim_traits', 'kind', "kind IS NOT NULL AND TRIM(kind) <> ''");
$traits = status_count_table($link, 'dim_traits');
$traitSets = status_count_table($link, 'fact_trait_sets');
$merits = status_count_table($link, 'dim_merits_flaws');
$archetypes = status_count_table($link, 'dim_archetypes');
$maneuvers = status_count_table($link, 'fact_combat_maneuvers');
$charTraits = status_count_table($link, 'bridge_characters_traits');
$charTraitLog = status_count_table($link, 'bridge_characters_traits_log');
$charConditions = status_count_table($link, 'bridge_characters_conditions');
$conditionTraitLinks = status_count_table($link, 'bridge_character_conditions_traits');

status_section('Reglamento', [
    ['label' => 'Categorías de rasgos', 'value' => $traitKinds],
    ['label' => 'Rasgos', 'value' => $traits],
    ['label' => 'Conjuntos de rasgos', 'value' => $traitSets],
    ['label' => 'Rasgos asignados a personajes', 'value' => $charTraits],
    ['label' => 'Histórico de rasgos', 'value' => $charTraitLog],
    ['label' => 'Méritos y defectos', 'value' => $merits],
    ['label' => 'Arquetipos', 'value' => $archetypes],
    ['label' => 'Condiciones', 'value' => $conditions],
    ['label' => 'Condiciones aplicadas a personajes', 'value' => $charConditions],
    ['label' => 'Rasgos ligados a condiciones', 'value' => $conditionTraitLinks],
    ['label' => 'Maniobras de combate', 'value' => $maneuvers],
]);

$docCats = status_count_table($link, 'dim_doc_categories');
$itemTypes = status_count_table($link, 'dim_item_types');
$biblio = status_count_table($link, 'dim_bibliographies');
$itemLinks = status_count_table($link, 'bridge_characters_items');
$docWithText = status_count_nonempty($link, 'fact_docs', 'content');
$cspPosts = status_count_table($link, 'fact_csp_posts');

status_section('Documentación e inventario', [
    ['label' => 'Bibliografías y orígenes', 'value' => $biblio],
    ['label' => 'Categorías de documentos', 'value' => $docCats],
    ['label' => 'Documentos', 'value' => $docs],
    ['label' => 'Documentos con contenido', 'value' => $docWithText],
    ['label' => 'Entradas del tablón CSP', 'value' => $cspPosts],
    ['label' => 'Categorías de objetos', 'value' => $itemTypes],
    ['label' => 'Objetos', 'value' => $items],
    ['label' => 'Vínculos personaje-objeto', 'value' => $itemLinks],
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
    ['label' => 'Categorías de dones', 'value' => $giftKinds],
    ['label' => 'Tipos de dones', 'value' => $giftTypes],
    ['label' => 'Dones', 'value' => $powers],
    ['label' => 'Tipos de rituales', 'value' => $riteTypes],
    ['label' => 'Rituales', 'value' => $rites],
    ['label' => 'Tipos de tótems', 'value' => $totemTypes],
    ['label' => 'Tótems', 'value' => $totems],
    ['label' => 'Tipos de disciplinas', 'value' => $disciplineTypes],
    ['label' => 'Poderes de disciplina', 'value' => $disciplines],
    ['label' => 'Vínculos personaje-poder', 'value' => $charPowers],
    ['label' => 'Vínculos personaje-mérito o defecto', 'value' => $meritLinks],
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
$charResLog = status_count_table($link, 'bridge_characters_system_resources_log');
$charMiscSystems = status_count_table($link, 'bridge_characters_misc_systems');
$systemDetailLabels = status_count_table($link, 'bridge_systems_detail_labels');
$systemExAuspices = status_count_table($link, 'bridge_systems_ex_auspices');
$systemExRaces = status_count_table($link, 'bridge_systems_ex_races');
$systemExTribes = status_count_table($link, 'bridge_systems_ex_tribes');
$systemFormIcons = status_count_table($link, 'bridge_systems_form_icons');
$auspiceEnergy = status_count_table($link, 'bridge_auspices_energy_resources');
$breedEnergy = status_count_table($link, 'bridge_breeds_energy_resources');
$tribeEnergy = status_count_table($link, 'bridge_tribes_energy_resources');
$miscEnergy = status_count_table($link, 'bridge_misc_systems_energy_resources');

status_section('Sistemas de juego', [
    ['label' => 'Sistemas', 'value' => $systems],
    ['label' => 'Formas', 'value' => $forms],
    ['label' => 'Razas', 'value' => $breeds],
    ['label' => 'Auspicios', 'value' => $auspices],
    ['label' => 'Tribus', 'value' => $tribes],
    ['label' => 'Datos misceláneos de sistema', 'value' => $miscSystems],
    ['label' => 'Vínculos personaje-sistema misceláneo', 'value' => $charMiscSystems],
    ['label' => 'Catálogo de recursos de sistema', 'value' => $systemResources],
    ['label' => 'Mapeos sistema-recurso', 'value' => $systemResBridge],
    ['label' => 'Recursos en personajes', 'value' => $charResBridge],
    ['label' => 'Histórico de recursos en personajes', 'value' => $charResLog],
    ['label' => 'Etiquetas de detalle de sistema', 'value' => $systemDetailLabels],
    ['label' => 'Compatibilidades sistema-auspicio', 'value' => $systemExAuspices],
    ['label' => 'Compatibilidades sistema-raza', 'value' => $systemExRaces],
    ['label' => 'Compatibilidades sistema-tribu', 'value' => $systemExTribes],
    ['label' => 'Iconos de forma por sistema', 'value' => $systemFormIcons],
    ['label' => 'Recursos por auspicio', 'value' => $auspiceEnergy],
    ['label' => 'Recursos por raza', 'value' => $breedEnergy],
    ['label' => 'Recursos por tribu', 'value' => $tribeEnergy],
    ['label' => 'Recursos para sistemas misceláneos', 'value' => $miscEnergy],
]);

$timelineEventTypes = status_count_table($link, 'dim_timeline_events_types');
$timelineCharacterLinks = status_count_table($link, 'bridge_timeline_events_characters');
$timelineChronicleLinks = status_count_table($link, 'bridge_timeline_events_chronicles');
$timelineRealityLinks = status_count_table($link, 'bridge_timeline_events_realities');
$timelineLinks = status_count_table($link, 'bridge_timeline_links');

status_section('Línea temporal y realidades', [
    ['label' => 'Tipos de evento', 'value' => $timelineEventTypes],
    ['label' => 'Eventos de la línea temporal', 'value' => $timelineEvents],
    ['label' => 'Vínculos evento-personaje', 'value' => $timelineCharacterLinks],
    ['label' => 'Vínculos evento-capítulo', 'value' => $timelineChapterLinks],
    ['label' => 'Vínculos evento-crónica', 'value' => $timelineChronicleLinks],
    ['label' => 'Vínculos evento-realidad', 'value' => $timelineRealityLinks],
    ['label' => 'Enlaces auxiliares de cronología', 'value' => $timelineLinks],
    ['label' => 'Realidades', 'value' => $realities],
]);

$cardTypes = status_count_distinct($link, 'fact_game_card_collection', 'source_type', "source_type IS NOT NULL AND TRIM(source_type) <> ''");
$activeCards = status_column_exists($link, 'fact_game_card_collection', 'is_active') ? status_count_table($link, 'fact_game_card_collection', 'is_active = 1') : null;
$cardsWithText = status_count_nonempty($link, 'fact_game_card_collection', 'card_text');
$cardsWithImage = status_count_nonempty($link, 'fact_game_card_collection', 'card_image_url');
$simBattles = status_count_table($link, 'fact_sim_battles');
$simScores = status_count_table($link, 'fact_sim_character_scores');
$simTalk = status_count_table($link, 'fact_sim_characters_talk');
$simItemUsage = status_count_table($link, 'fact_sim_item_usage');
$simSeasons = status_count_table($link, 'fact_sim_seasons');
$simTournaments = status_count_table($link, 'fact_sim_tournaments');
$simBattleCharacterSeasons = status_count_table($link, 'bridge_battle_sim_characters_seasons');

status_section('Cartas y simuladores', [
    ['label' => 'Cartas del minijuego', 'value' => $gameCards],
    ['label' => 'Tipos de carta representados', 'value' => $cardTypes],
    ['label' => 'Cartas activas', 'value' => $activeCards],
    ['label' => 'Cartas con texto', 'value' => $cardsWithText],
    ['label' => 'Cartas con imagen', 'value' => $cardsWithImage],
    ['label' => 'Combates simulados', 'value' => $simBattles],
    ['label' => 'Puntuaciones del simulador', 'value' => $simScores],
    ['label' => 'Diálogos del simulador', 'value' => $simTalk],
    ['label' => 'Usos de objetos en simulación', 'value' => $simItemUsage],
    ['label' => 'Temporadas del simulador', 'value' => $simSeasons],
    ['label' => 'Torneos del simulador', 'value' => $simTournaments],
    ['label' => 'Vínculos personaje-temporada de simulación', 'value' => $simBattleCharacterSeasons],
]);

$news = status_count_table($link, 'fact_admin_posts');
$soundtracks = status_count_table($link, 'dim_soundtracks');
$soundtrackLinks = status_count_table($link, 'bridge_soundtrack_links');
$plots = status_count_table($link, 'dim_parties');
$plotMembers = status_count_table($link, 'fact_party_members');
$plotChanges = status_count_table($link, 'fact_party_members_changes');
$menuItems = status_count_table($link, 'dim_menu_items');
$maps = status_count_table($link, 'dim_maps');
$mapCats = status_count_table($link, 'dim_map_categories');
$pois = status_count_table($link, 'fact_map_pois');
$areas = status_count_table($link, 'fact_map_areas');
$diceRolls = status_count_table($link, 'fact_dice_rolls');
$topicViewer = status_count_table($link, 'fact_tools_topic_viewer');

status_section('Contenido y herramientas', [
    ['label' => 'Noticias', 'value' => $news],
    ['label' => 'Temas de banda sonora', 'value' => $soundtracks],
    ['label' => 'Vínculos de banda sonora', 'value' => $soundtrackLinks],
    ['label' => 'Grupos de juego activos', 'value' => $plots],
    ['label' => 'Miembros en activo', 'value' => $plotMembers],
    ['label' => 'Cambios históricos de grupos', 'value' => $plotChanges],
    ['label' => 'Elementos de menú dinámico', 'value' => $menuItems],
    ['label' => 'Mapas', 'value' => $maps],
    ['label' => 'Categorías de mapa', 'value' => $mapCats],
    ['label' => 'Puntos de interés', 'value' => $pois],
    ['label' => 'Áreas en mapas', 'value' => $areas],
    ['label' => 'Tiradas registradas', 'value' => $diceRolls],
    ['label' => 'Registros del visor temático', 'value' => $topicViewer],
]);

$hasCoverage = (($characters ?? 0) > 0 && $charWithImage !== null)
    || (($chapters ?? 0) > 0 && $chapterWithSynopsis !== null)
    || (($docs ?? 0) > 0 && $docWithText !== null)
    || (($gameCards ?? 0) > 0 && ($cardsWithImage !== null || $cardsWithText !== null));

if ($hasCoverage) {
    echo "<fieldset class='renglonPaginaDon'>";
    echo "<legend>Cobertura rápida</legend>";

    if (($characters ?? 0) > 0 && $charWithImage !== null) {
        echo "<div class='renglonStatusIz'>Personajes con imagen:</div>";
        echo "<div class='renglonStatusDe'>" . number_format($charWithImage, 0, ',', '.') . " / " . number_format((int)$characters, 0, ',', '.') . " (" . status_ratio((int)$charWithImage, (int)$characters) . ")</div>";
    }

    if (($characters ?? 0) > 0 && $charWithInfo !== null) {
        echo "<div class='renglonStatusIz'>Personajes con biografía:</div>";
        echo "<div class='renglonStatusDe'>" . number_format($charWithInfo, 0, ',', '.') . " / " . number_format((int)$characters, 0, ',', '.') . " (" . status_ratio((int)$charWithInfo, (int)$characters) . ")</div>";
    }

    if (($chapters ?? 0) > 0 && $chapterWithSynopsis !== null) {
        echo "<div class='renglonStatusIz'>Capítulos con resumen:</div>";
        echo "<div class='renglonStatusDe'>" . number_format($chapterWithSynopsis, 0, ',', '.') . " / " . number_format((int)$chapters, 0, ',', '.') . " (" . status_ratio((int)$chapterWithSynopsis, (int)$chapters) . ")</div>";
    }

    if (($docs ?? 0) > 0 && $docWithText !== null) {
        echo "<div class='renglonStatusIz'>Documentos con contenido:</div>";
        echo "<div class='renglonStatusDe'>" . number_format($docWithText, 0, ',', '.') . " / " . number_format((int)$docs, 0, ',', '.') . " (" . status_ratio((int)$docWithText, (int)$docs) . ")</div>";
    }

    if (($gameCards ?? 0) > 0 && $cardsWithImage !== null) {
        echo "<div class='renglonStatusIz'>Cartas con imagen:</div>";
        echo "<div class='renglonStatusDe'>" . number_format($cardsWithImage, 0, ',', '.') . " / " . number_format((int)$gameCards, 0, ',', '.') . " (" . status_ratio((int)$cardsWithImage, (int)$gameCards) . ")</div>";
    }

    if (($gameCards ?? 0) > 0 && $cardsWithText !== null) {
        echo "<div class='renglonStatusIz'>Cartas con texto:</div>";
        echo "<div class='renglonStatusDe'>" . number_format($cardsWithText, 0, ',', '.') . " / " . number_format((int)$gameCards, 0, ',', '.') . " (" . status_ratio((int)$cardsWithText, (int)$gameCards) . ")</div>";
    }

    echo "</fieldset><br/>";
}
?>
