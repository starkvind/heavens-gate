<?php
include_once(__DIR__ . '/../../helpers/recent_content.php');

$metaTitle = "Heaven's Gate | Móvil";
$metaDescription = "Archivo móvil de Heaven's Gate.";
$pageSect = 'Inicio';

if (!function_exists('hg_mobile_home_h')) {
    function hg_mobile_home_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_home_count_table')) {
    function hg_mobile_home_count_table(mysqli $link, string $table): ?int
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return null;
        }

        $result = mysqli_query($link, "SELECT COUNT(*) AS total FROM `{$table}`");
        if (!$result) {
            return null;
        }

        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        return isset($row['total']) ? (int)$row['total'] : 0;
    }
}



$counts = [];
$rulesCount = null;
if (isset($link) && ($link instanceof mysqli)) {
    $counts = [
        'characters' => hg_mobile_home_count_table($link, 'fact_characters'),
        'chapters' => hg_mobile_home_count_table($link, 'dim_chapters'),
        'events' => hg_mobile_home_count_table($link, 'fact_timeline_events'),
        'documents' => hg_mobile_home_count_table($link, 'fact_docs'),
        'chronicles' => hg_mobile_home_count_table($link, 'dim_chronicles'),
        'seasons' => hg_mobile_home_count_table($link, 'dim_seasons'),
        'powers' => hg_mobile_home_count_table($link, 'fact_gifts'),
        'maps' => hg_mobile_home_count_table($link, 'dim_maps'),
    ];
    $ruleCounts = [
        hg_mobile_home_count_table($link, 'dim_traits'),
        hg_mobile_home_count_table($link, 'dim_merits_flaws'),
        hg_mobile_home_count_table($link, 'dim_character_conditions'),
        hg_mobile_home_count_table($link, 'dim_archetypes'),
        hg_mobile_home_count_table($link, 'fact_combat_maneuvers'),
    ];
    $rulesCount = in_array(null, $ruleCounts, true) ? null : array_sum($ruleCounts);
}

$sections = [
    ['label' => 'Personajes', 'href' => '/characters', 'text' => 'Biografías, relaciones y destinos de protagonistas y figuras secundarias.', 'count' => $counts['characters'] ?? null],
    ['label' => 'Temporadas', 'href' => '/seasons', 'text' => 'Arcos narrativos, episodios y capítulos ordenados por temporada.', 'count' => $counts['seasons'] ?? null],
    ['label' => 'Crónicas', 'href' => '/chronicles', 'text' => 'Campañas, continuidades, líneas temporales y realidades.', 'count' => $counts['chronicles'] ?? null],
    ['label' => 'Reglas', 'href' => '/rules', 'text' => 'Sistemas de juego, mecánicas y material de consulta rápida.', 'count' => $rulesCount],
    ['label' => 'Poderes', 'href' => '/powers', 'text' => 'Dones, rituales, tótems, disciplinas y capacidades sobrenaturales.', 'count' => $counts['powers'] ?? null],
    ['label' => 'Mapas', 'href' => '/maps', 'text' => 'Lugares, dominios, túmulos y geografías del archivo.', 'count' => $counts['maps'] ?? null],
];

$stats = [
    ['label' => 'Personajes', 'value' => $counts['characters'] ?? null],
    ['label' => 'Capítulos', 'value' => $counts['chapters'] ?? null],
    ['label' => 'Eventos', 'value' => $counts['events'] ?? null],
    ['label' => 'Documentos', 'value' => $counts['documents'] ?? null],
];
$recentContent = isset($link) && ($link instanceof mysqli) ? hg_recent_content_feed($link) : [];
?>

<section class="hg-mobile-home-hero" aria-labelledby="mobile-home-title">
    <h1 id="mobile-home-title">Heaven's Gate</h1>
    <p class="hg-mobile-home-lead">Continuidad viva de personajes, crónicas, temporadas y sistemas de juego.</p>
    <p class="hg-mobile-home-copy">Un archivo en constante reconstrucción sobre quienes habitaron, combatieron y sobrevivieron a esta historia.</p>
    <form class="hg-mobile-home-search" action="/search/results?view=mobile" method="get">
        <label class="hg-mobile-visually-hidden" for="mobile-home-search-q">Buscar en el archivo</label>
        <input id="mobile-home-search-q" type="search" name="q" minlength="3" maxlength="80" placeholder="Buscar personajes, capítulos, lugares, poderes o documentos..." required>
        <input type="hidden" name="section" value="all">
        <button type="submit">Buscar</button>
    </form>
</section>

<section class="hg-mobile-section hg-mobile-home-section" aria-labelledby="mobile-home-explore-title">
    <h2 id="mobile-home-explore-title">Explora</h2>
    <div class="hg-mobile-home-grid">
        <?php foreach ($sections as $section): ?>
            <a class="hg-mobile-home-card" href="<?= hg_mobile_home_h($section['href']) ?>">
                <span class="hg-mobile-home-card-heading"><strong><?= hg_mobile_home_h($section['label']) ?></strong><span aria-hidden="true">&rarr;</span></span>
                <span><?= hg_mobile_home_h($section['text']) ?></span>
                <?php if ($section['count'] !== null): ?><small><?= number_format((int)$section['count'], 0, ',', '.') ?> <?= hg_mobile_home_h(function_exists('mb_strtolower') ? mb_strtolower($section['label'], 'UTF-8') : strtolower($section['label'])) ?></small><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($recentContent): ?>
<section class="hg-mobile-section hg-mobile-home-section" aria-labelledby="mobile-home-recent-content-title">
    <h2 id="mobile-home-recent-content-title">Contenido actualizado recientemente</h2>
    <div class="hg-mobile-home-archive-list">
        <?php foreach ($recentContent as $item): ?>
            <?php
            $excerpt = trim(preg_replace('/\s+/', ' ', strip_tags((string)($item['description'] ?? ''))));
            if (function_exists('mb_strimwidth')) {
                $excerpt = mb_strimwidth($excerpt, 0, 150, '…', 'UTF-8');
            }
            $updatedAt = strtotime((string)($item['updated_at'] ?? ''));
            ?>
            <a class="hg-mobile-home-archive-card" href="<?= hg_mobile_home_h($item['href'] ?? '#') ?>">
                <small><?= hg_mobile_home_h($item['type_label'] ?? 'Contenido') ?> actualizado</small>
                <strong><?= hg_mobile_home_h($item['title'] ?? '') ?></strong>
                <?php if ($excerpt !== ''): ?><span><?= hg_mobile_home_h($excerpt) ?></span><?php endif; ?>
                <?php if ($updatedAt): ?><time datetime="<?= hg_mobile_home_h(date('Y-m-d', $updatedAt)) ?>">Actualizado <?= hg_mobile_home_h(date('d/m/Y', $updatedAt)) ?> <span aria-hidden="true">→</span></time><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

