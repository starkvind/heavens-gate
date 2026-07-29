<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../helpers/chronicle_scope.php');

$metaTitle = "Estado | Heaven's Gate";
$metaDescription = 'Estado público móvil del archivo.';
$pageSect = 'Estado';

if (!function_exists('hg_mobile_status_h')) {
    function hg_mobile_status_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_status_table_exists')) {
    function hg_mobile_status_table_exists(mysqli $link, string $table): bool
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') return false;
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
        return $cache[$table] = $ok;
    }
}

if (!function_exists('hg_mobile_status_column_exists')) {
    function hg_mobile_status_column_exists(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') return false;
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
        return $cache[$key] = $ok;
    }
}

if (!function_exists('hg_mobile_status_count')) {
    function hg_mobile_status_count(mysqli $link, string $table, string $where = '1=1'): ?int
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '' || !hg_mobile_status_table_exists($link, $table)) return null;
        $sql = "SELECT COUNT(*) AS total FROM `{$table}` WHERE {$where}";
        $rs = mysqli_query($link, $sql);
        if (!$rs) return null;
        $row = mysqli_fetch_assoc($rs);
        mysqli_free_result($rs);
        return isset($row['total']) ? (int)$row['total'] : 0;
    }
}

if (!function_exists('hg_mobile_status_count_nonempty')) {
    function hg_mobile_status_count_nonempty(mysqli $link, string $table, string $column, string $extraWhere = '1=1'): ?int
    {
        if (!hg_mobile_status_column_exists($link, $table, $column)) return null;
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        return hg_mobile_status_count($link, $table, "`{$column}` IS NOT NULL AND TRIM(`{$column}`) <> '' AND {$extraWhere}");
    }
}

if (!function_exists('hg_mobile_status_count_distinct')) {
    function hg_mobile_status_count_distinct(mysqli $link, string $table, string $column, string $where = '1=1'): ?int
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '' || !hg_mobile_status_table_exists($link, $table) || !hg_mobile_status_column_exists($link, $table, $column)) return null;
        $rs = mysqli_query($link, "SELECT COUNT(DISTINCT `{$column}`) AS total FROM `{$table}` WHERE {$where}");
        if (!$rs) return null;
        $row = mysqli_fetch_assoc($rs);
        mysqli_free_result($rs);
        return isset($row['total']) ? (int)$row['total'] : 0;
    }
}

if (!function_exists('hg_mobile_status_sum')) {
    function hg_mobile_status_sum(array $values): ?int
    {
        $has = false;
        $total = 0;
        foreach ($values as $value) {
            if ($value !== null) {
                $has = true;
                $total += (int)$value;
            }
        }
        return $has ? $total : null;
    }
}

if (!function_exists('hg_mobile_status_ratio')) {
    function hg_mobile_status_ratio(?int $part, ?int $total): string
    {
        if ($part === null || $total === null || $total <= 0) return '';
        return number_format(($part * 100) / $total, 1, ',', '.') . '%';
    }
}

if (!function_exists('hg_mobile_status_gallery_count')) {
    function hg_mobile_status_gallery_count(): ?int
    {
        $base = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\') . '/public/img/gallery';
        if ($base === '/public/img/gallery' || !is_dir($base)) return null;
        $allowed = ['jpg' => true, 'jpeg' => true, 'png' => true, 'gif' => true, 'webp' => true];
        $count = 0;
        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile()) continue;
                $path = str_replace('\\', '/', $file->getPathname());
                if (strpos($path, '/thumbnails/') !== false) continue;
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (isset($allowed[$ext])) $count++;
            }
        } catch (Throwable $e) {
            return null;
        }
        return $count;
    }
}

if (!function_exists('hg_mobile_status_section')) {
    function hg_mobile_status_section(string $title, array $rows, bool $open = false): void
    {
        $visible = array_values(array_filter($rows, static fn($row) => array_key_exists('value', $row) && $row['value'] !== null));
        if (empty($visible)) return;
        ?>
        <details class="hg-mobile-status-section"<?= $open ? ' open' : '' ?>>
            <summary><?= hg_mobile_status_h($title) ?></summary>
            <div class="hg-mobile-status-list">
                <?php foreach ($visible as $row): ?>
                    <div class="hg-mobile-status-row">
                        <span><?= hg_mobile_status_h($row['label'] ?? '') ?></span>
                        <strong><?= number_format((int)$row['value'], 0, ',', '.') ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
        <?php
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    echo '<section class="hg-mobile-section"><h1>Estado</h1><p>No se pudo conectar con la base de datos.</p></section>';
    return;
}

if (method_exists($link, 'set_charset')) {
    $link->set_charset('utf8mb4');
}

$characterWhere = hg_mobile_chronicle_exclusion_condition('p');
$characterWhereBare = hg_mobile_chronicle_exclusion_condition('');
$seasonChronWhere = hg_mobile_status_column_exists($link, 'dim_seasons', 'chronicle_id') ? hg_mobile_chronicle_exclusion_condition('s') : '1=1';
$chapterChronWhere = hg_mobile_status_column_exists($link, 'dim_seasons', 'chronicle_id') ? hg_mobile_chronicle_exclusion_condition('s') : '1=1';

$players = hg_mobile_status_count($link, 'dim_players');
$chronicles = hg_mobile_status_count($link, 'dim_chronicles', 'id NOT IN (' . (hg_mobile_excluded_chronicles_csv() ?: '0') . ')');
$realities = hg_mobile_status_count($link, 'dim_realities');
$characters = hg_mobile_status_count($link, 'fact_characters', $characterWhereBare);
$charactersWithImage = hg_mobile_status_count_nonempty($link, 'fact_characters', 'image_url', $characterWhereBare);
$charactersWithInfo = hg_mobile_status_count_nonempty($link, 'fact_characters', 'info_text', $characterWhereBare);
$characterTypes = hg_mobile_status_count($link, 'dim_character_types');
$characterStatuses = hg_mobile_status_count($link, 'dim_character_status');
$relations = hg_mobile_status_count($link, 'bridge_characters_relations');
$orgs = hg_mobile_status_count($link, 'dim_organizations');
$groups = hg_mobile_status_count($link, 'dim_groups');

$seasons = hg_mobile_status_count($link, 'dim_seasons', str_replace('s.', '', $seasonChronWhere));
$seasonMain = hg_mobile_status_column_exists($link, 'dim_seasons', 'season_kind') ? hg_mobile_status_count($link, 'dim_seasons', "season_kind = 'temporada' AND " . str_replace('s.', '', $seasonChronWhere)) : null;
$seasonPersonal = hg_mobile_status_column_exists($link, 'dim_seasons', 'season_kind') ? hg_mobile_status_count($link, 'dim_seasons', "season_kind = 'historia_personal' AND " . str_replace('s.', '', $seasonChronWhere)) : null;
$seasonFinished = hg_mobile_status_column_exists($link, 'dim_seasons', 'finished') ? hg_mobile_status_count($link, 'dim_seasons', "finished = 1 AND " . str_replace('s.', '', $seasonChronWhere)) : null;
$chapters = hg_mobile_status_table_exists($link, 'dim_chapters')
    ? (function () use ($link, $chapterChronWhere): ?int {
        $sql = "SELECT COUNT(*) AS total FROM dim_chapters c LEFT JOIN dim_seasons s ON s.id = c.season_id WHERE {$chapterChronWhere}";
        $rs = mysqli_query($link, $sql);
        if (!$rs) return null;
        $row = mysqli_fetch_assoc($rs);
        mysqli_free_result($rs);
        return isset($row['total']) ? (int)$row['total'] : 0;
    })()
    : null;
$chaptersWithSynopsis = hg_mobile_status_column_exists($link, 'dim_chapters', 'synopsis')
    ? (function () use ($link, $chapterChronWhere): ?int {
        $sql = "SELECT COUNT(*) AS total FROM dim_chapters c LEFT JOIN dim_seasons s ON s.id = c.season_id WHERE c.synopsis IS NOT NULL AND TRIM(c.synopsis) <> '' AND {$chapterChronWhere}";
        $rs = mysqli_query($link, $sql);
        if (!$rs) return null;
        $row = mysqli_fetch_assoc($rs);
        mysqli_free_result($rs);
        return isset($row['total']) ? (int)$row['total'] : 0;
    })()
    : null;
$chapterLinks = hg_mobile_status_count($link, 'bridge_chapters_characters');

$docs = hg_mobile_status_count($link, 'fact_docs');
$docsWithContent = hg_mobile_status_count_nonempty($link, 'fact_docs', 'content');
$docCats = hg_mobile_status_count($link, 'dim_doc_categories');
$items = hg_mobile_status_count($link, 'fact_items');
$itemTypes = hg_mobile_status_count($link, 'dim_item_types');

$traits = hg_mobile_status_count($link, 'dim_traits');
$traitKinds = hg_mobile_status_count_distinct($link, 'dim_traits', 'kind', "kind IS NOT NULL AND TRIM(kind) <> ''");
$conditions = hg_mobile_status_count($link, 'dim_character_conditions');
$merits = hg_mobile_status_count($link, 'dim_merits_flaws');
$archetypes = hg_mobile_status_count($link, 'dim_archetypes');
$maneuvers = hg_mobile_status_count($link, 'fact_combat_maneuvers');

$gifts = hg_mobile_status_count($link, 'fact_gifts');
$rites = hg_mobile_status_count($link, 'fact_rites');
$totems = hg_mobile_status_count($link, 'dim_totems');
$disciplines = hg_mobile_status_count($link, 'fact_discipline_powers');
$powerLinks = hg_mobile_status_count($link, 'bridge_characters_powers');

$systems = hg_mobile_status_count($link, 'dim_systems');
$forms = hg_mobile_status_count($link, 'dim_forms');
$breeds = hg_mobile_status_count($link, 'dim_breeds');
$auspices = hg_mobile_status_count($link, 'dim_auspices');
$tribes = hg_mobile_status_count($link, 'dim_tribes');
$miscSystems = hg_mobile_status_count($link, 'fact_misc_systems');

$timelineEvents = hg_mobile_status_count($link, 'fact_timeline_events', hg_mobile_status_column_exists($link, 'fact_timeline_events', 'is_active') ? 'is_active = 1' : '1=1');
$timelineTypes = hg_mobile_status_count($link, 'dim_timeline_events_types');
$timelineCharacterLinks = hg_mobile_status_count($link, 'bridge_timeline_events_characters');
$timelineChapterLinks = hg_mobile_status_count($link, 'bridge_timeline_events_chapters');
$timelineChronicleLinks = hg_mobile_status_count($link, 'bridge_timeline_events_chronicles');
$timelineRealityLinks = hg_mobile_status_count($link, 'bridge_timeline_events_realities');

$soundtracks = hg_mobile_status_count($link, 'dim_soundtracks');
$soundtrackLinks = hg_mobile_status_count($link, 'bridge_soundtrack_links');
$galleryImages = hg_mobile_status_gallery_count();
$news = hg_mobile_status_count($link, 'fact_admin_posts');
$maps = hg_mobile_status_count($link, 'dim_maps');
$mapCats = hg_mobile_status_count($link, 'dim_map_categories');
$pois = hg_mobile_status_count($link, 'fact_map_pois');
$areas = hg_mobile_status_count($link, 'fact_map_areas');
$diceRolls = hg_mobile_status_count($link, 'fact_dice_rolls');
$topicViewer = hg_mobile_status_count($link, 'fact_tools_topic_viewer');
$gameCards = hg_mobile_status_count($link, 'fact_game_card_collection');
$activeCards = hg_mobile_status_column_exists($link, 'fact_game_card_collection', 'is_active') ? hg_mobile_status_count($link, 'fact_game_card_collection', 'is_active = 1') : null;

$totalVisible = hg_mobile_status_sum([$characters, $chapters, $docs, $items, $gifts, $rites, $disciplines, $timelineEvents, $gameCards, $pois]);
$powerTotal = hg_mobile_status_sum([$gifts, $rites, $totems, $disciplines]);
$rulesTotal = hg_mobile_status_sum([$traits, $conditions, $merits, $archetypes, $maneuvers]);

$topStats = [
    ['label' => 'Contenido visible', 'value' => $totalVisible],
    ['label' => 'Personajes', 'value' => $characters],
    ['label' => 'Capítulos', 'value' => $chapters],
    ['label' => 'Eventos', 'value' => $timelineEvents],
];
?>

<section class="hg-mobile-section hg-mobile-status-head">
    <h1>Estado</h1>
    <p>Resumen público del archivo móvil. Los recuentos sensibles aplican el filtro de crónicas excluidas.</p>
    <div class="hg-mobile-stat-grid">
        <?php foreach ($topStats as $stat): ?>
            <?php if ($stat['value'] === null) continue; ?>
            <div class="hg-mobile-stat">
                <strong><?= number_format((int)$stat['value'], 0, ',', '.') ?></strong>
                <span><?= hg_mobile_status_h($stat['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="hg-mobile-section hg-mobile-status-coverage">
    <h2>Cobertura</h2>
    <div class="hg-mobile-status-bars">
        <?php
        $coverageRows = [
            ['Personajes con imagen', $charactersWithImage, $characters],
            ['Personajes con biografia', $charactersWithInfo, $characters],
            ['Capítulos con resumen', $chaptersWithSynopsis, $chapters],
            ['Documentos con contenido', $docsWithContent, $docs],
        ];
        foreach ($coverageRows as $coverage):
            [$label, $part, $total] = $coverage;
            if ($part === null || $total === null || $total <= 0) continue;
            $pct = max(0, min(100, ($part * 100) / $total));
        ?>
            <div class="hg-mobile-status-bar">
                <div><span><?= hg_mobile_status_h($label) ?></span><strong><?= hg_mobile_status_h(hg_mobile_status_ratio((int)$part, (int)$total)) ?></strong></div>
                <i style="width: <?= number_format($pct, 2, '.', '') ?>%"></i>
                <small><?= number_format((int)$part, 0, ',', '.') ?> / <?= number_format((int)$total, 0, ',', '.') ?></small>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="hg-mobile-section hg-mobile-status-sections">
    <h2>Detalle</h2>
    <?php
    hg_mobile_status_section('Archivo general', [
        ['label' => 'Jugadores', 'value' => $players],
        ['label' => 'Crónicas visibles', 'value' => $chronicles],
        ['label' => 'Realidades', 'value' => $realities],
        ['label' => 'Noticias', 'value' => $news],
    ], true);

    hg_mobile_status_section('Biografías y grupos', [
        ['label' => 'Personajes visibles', 'value' => $characters],
        ['label' => 'Tipos de personaje', 'value' => $characterTypes],
        ['label' => 'Estados de personaje', 'value' => $characterStatuses],
        ['label' => 'Relaciones entre personajes', 'value' => $relations],
        ['label' => 'Organizaciones', 'value' => $orgs],
        ['label' => 'Grupos y manadas', 'value' => $groups],
    ]);

    hg_mobile_status_section('Temporadas y capítulos', [
        ['label' => 'Temporadas visibles', 'value' => $seasons],
        ['label' => 'Temporadas principales', 'value' => $seasonMain],
        ['label' => 'Historias personales', 'value' => $seasonPersonal],
        ['label' => 'Temporadas finalizadas', 'value' => $seasonFinished],
        ['label' => 'Capítulos visibles', 'value' => $chapters],
        ['label' => 'Participaciones personaje-capitulo', 'value' => $chapterLinks],
    ]);

    hg_mobile_status_section('Reglas y poderes', [
        ['label' => 'Reglas catalogadas', 'value' => $rulesTotal],
        ['label' => 'Categorías de rasgos', 'value' => $traitKinds],
        ['label' => 'Rasgos', 'value' => $traits],
        ['label' => 'Condiciones', 'value' => $conditions],
        ['label' => 'Méritos y defectos', 'value' => $merits],
        ['label' => 'Arquetipos', 'value' => $archetypes],
        ['label' => 'Maniobras', 'value' => $maneuvers],
        ['label' => 'Poderes totales', 'value' => $powerTotal],
        ['label' => 'Dones', 'value' => $gifts],
        ['label' => 'Rituales', 'value' => $rites],
        ['label' => 'Totems', 'value' => $totems],
        ['label' => 'Disciplinas', 'value' => $disciplines],
        ['label' => 'Poderes asignados', 'value' => $powerLinks],
    ]);

    hg_mobile_status_section('Documentos, sistemas e inventario', [
        ['label' => 'Documentos', 'value' => $docs],
        ['label' => 'Categorías de documentos', 'value' => $docCats],
        ['label' => 'Objetos', 'value' => $items],
        ['label' => 'Tipos de objeto', 'value' => $itemTypes],
        ['label' => 'Sistemas', 'value' => $systems],
        ['label' => 'Formas', 'value' => $forms],
        ['label' => 'Razas', 'value' => $breeds],
        ['label' => 'Auspicios', 'value' => $auspices],
        ['label' => 'Tribus', 'value' => $tribes],
        ['label' => 'Miscelanea de sistemas', 'value' => $miscSystems],
    ]);

    hg_mobile_status_section('Línea temporal', [
        ['label' => 'Eventos activos', 'value' => $timelineEvents],
        ['label' => 'Tipos de evento', 'value' => $timelineTypes],
        ['label' => 'Vinculos evento-personaje', 'value' => $timelineCharacterLinks],
        ['label' => 'Vinculos evento-capitulo', 'value' => $timelineChapterLinks],
        ['label' => 'Vinculos evento-cronica', 'value' => $timelineChronicleLinks],
        ['label' => 'Vinculos evento-realidad', 'value' => $timelineRealityLinks],
    ]);

    hg_mobile_status_section('Contenido y herramientas', [
        ['label' => 'Temas de banda sonora', 'value' => $soundtracks],
        ['label' => 'Vinculos de banda sonora', 'value' => $soundtrackLinks],
        ['label' => 'Imagenes en galeria', 'value' => $galleryImages],
        ['label' => 'Mapas', 'value' => $maps],
        ['label' => 'Categorías de mapa', 'value' => $mapCats],
        ['label' => 'Puntos de interes', 'value' => $pois],
        ['label' => 'Areas en mapas', 'value' => $areas],
        ['label' => 'Tiradas registradas', 'value' => $diceRolls],
        ['label' => 'Temas del lector de foro', 'value' => $topicViewer],
        ['label' => 'Cartas del minijuego', 'value' => $gameCards],
        ['label' => 'Cartas activas', 'value' => $activeCards],
    ]);
    ?>
</section>
