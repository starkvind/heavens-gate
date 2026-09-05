<?php setMetaFromPage("Heaven's Gate", "Archivo vivo de una crónica alternativa de Hombre Lobo: El Apocalipsis. Explora personajes, historia, crónicas y sistemas de juego.", null, 'website'); ?>
<?php
include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/recent_content.php');
if (!$link) {
    hg_public_log_error('main_home', 'missing DB connection');
    hg_public_render_error('Inicio no disponible', 'No se pudo cargar la página de inicio en este momento.');
    return;
}

include('app/partials/main_nav_bar.php');
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-home.css');
}

if (!function_exists('hg_home_h')) {
    function hg_home_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_home_count_table')) {
    function hg_home_count_table(mysqli $link, string $table): ?int
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

$counts = [
    'characters' => hg_home_count_table($link, 'fact_characters'),
    'chapters' => hg_home_count_table($link, 'dim_chapters'),
    'events' => hg_home_count_table($link, 'fact_timeline_events'),
    'documents' => hg_home_count_table($link, 'fact_docs'),
    'chronicles' => hg_home_count_table($link, 'dim_chronicles'),
    'seasons' => hg_home_count_table($link, 'dim_seasons'),
    'powers' => hg_home_count_table($link, 'fact_gifts'),
    'organizations' => hg_home_count_table($link, 'dim_organizations'),
];

$ruleCounts = [
    hg_home_count_table($link, 'dim_traits'),
    hg_home_count_table($link, 'dim_merits_flaws'),
    hg_home_count_table($link, 'dim_character_conditions'),
    hg_home_count_table($link, 'dim_archetypes'),
    hg_home_count_table($link, 'fact_combat_maneuvers'),
];
$rulesCount = in_array(null, $ruleCounts, true) ? null : array_sum($ruleCounts);

$categories = [
    ['title' => 'Personajes', 'description' => 'Biografías, relaciones y destinos de protagonistas y figuras secundarias.', 'href' => '/characters', 'count' => $counts['characters']],
    ['title' => 'Temporadas', 'description' => 'Arcos narrativos, episodios y capítulos ordenados por temporada.', 'href' => '/seasons', 'count' => $counts['seasons']],
    ['title' => 'Grupos', 'description' => 'Clanes, sociedades y grupos que conforman el mundo de la crónica.', 'href' => '/organizations', 'count' => $counts['organizations']],
    ['title' => 'Crónicas', 'description' => 'Campañas, continuidades, líneas temporales y realidades.', 'href' => '/chronicles', 'count' => $counts['chronicles']],
    ['title' => 'Reglas', 'description' => 'Sistemas de juego, mecánicas y material de consulta rápida.', 'href' => '/rules', 'count' => $rulesCount],
    ['title' => 'Poderes', 'description' => 'Dones, rituales, tótems, disciplinas y capacidades sobrenaturales.', 'href' => '/powers', 'count' => $counts['powers']],
];

$stats = [
    ['label' => 'Personajes', 'value' => $counts['characters']],
    ['label' => 'Capítulos', 'value' => $counts['chapters']],
    ['label' => 'Eventos', 'value' => $counts['events']],
    ['label' => 'Documentos', 'value' => $counts['documents']],
];

$latestNews = null;
if ($stmt = $link->prepare('SELECT title, message, author, posted_at FROM fact_admin_posts ORDER BY posted_at DESC, id DESC LIMIT 1')) {
    if ($stmt->execute() && ($result = $stmt->get_result())) {
        $latestNews = $result->fetch_assoc() ?: null;
        $result->free();
    }
    $stmt->close();
}
$recentContent = hg_recent_content_feed($link);
?>

<main class="home-landing">
    <section class="home-hero" aria-labelledby="home-title">
        <h1 id="home-title">Heaven's Gate</h1>
        <p class="home-lead">Una campaña de Mundo de Tinieblas, completamente personalizada y con trasfondo propio, en activo desde 2006.</p>
        <p class="home-copy">Archivo digital de historias, reglas y eventos, en constante reconstrucción sobre quienes participaron en esta epopeya.</p>

        <form class="home-search" action="/search/results" method="get">
            <label class="home-search-label" for="home-search-q">Buscar en el archivo</label>
            <input id="home-search-q" type="search" name="q" minlength="3" maxlength="80" placeholder="Buscar personajes, capítulos, lugares, poderes o documentos..." required>
            <input type="hidden" name="section" value="all">
            <button type="submit">Buscar</button>
        </form>
    </section>

    <?php if ($latestNews): ?>
        <?php
        $latestNewsExcerpt = trim(preg_replace('/\s+/', ' ', strip_tags((string)($latestNews['message'] ?? ''))));
        if (function_exists('mb_strimwidth')) {
            $latestNewsExcerpt = mb_strimwidth($latestNewsExcerpt, 0, 420, '...', 'UTF-8');
        }
        $latestNewsDate = strtotime((string)($latestNews['posted_at'] ?? ''));
        ?>
        <section class="home-section home-latest-news" aria-labelledby="home-latest-news-title">
            <div class="home-section-head">
                <h2 id="home-latest-news-title">&Uacute;ltima noticia</h2>
            </div>
            <a class="home-latest-news-card" href="/news">
                <span class="home-latest-news-main">
                    <span class="home-latest-news-title-row">
                        <span class="home-latest-news-title"><?= hg_home_h($latestNews['title'] ?? '') ?></span>
                        <?php if ($latestNewsDate): ?><time class="home-latest-news-date" datetime="<?= hg_home_h(date('Y-m-d', $latestNewsDate)) ?>"><?= hg_home_h(date('d/m/Y', $latestNewsDate)) ?></time><?php endif; ?>
                    </span>
                    <?php if ($latestNewsExcerpt !== ''): ?><span class="home-latest-news-description"><?= hg_home_h($latestNewsExcerpt) ?></span><?php endif; ?>
                </span>
                <span class="home-latest-news-meta">
                    <?php if (trim((string)($latestNews['author'] ?? '')) !== ''): ?><span>por <?= hg_home_h($latestNews['author']) ?></span><?php endif; ?>
                    <span class="home-latest-news-link" aria-hidden="true">Ver noticias &rarr;</span>
                </span>
            </a>
        </section>
    <?php endif; ?>
    <section class="home-section" aria-labelledby="home-explore-title">
        <div class="home-section-head">
            <h2 id="home-explore-title">Explora</h2>
        </div>
        <div class="home-shortcuts-grid">
            <?php foreach ($categories as $category): ?>
                <a class="home-shortcut-card" href="<?= hg_home_h($category['href']) ?>">
                    <span class="home-shortcut-card-main">
                        <span class="home-shortcut-title-row">
                            <span class="home-shortcut-title"><?= hg_home_h($category['title']) ?></span>
                            <span class="home-shortcut-arrow" aria-hidden="true">&rarr;</span>
                        </span>
                        <span class="home-shortcut-description"><?= hg_home_h($category['description']) ?></span>
                    </span>
                    <?php if ($category['count'] !== null): ?>
                        <span class="home-shortcut-count"><?= number_format((int)$category['count'], 0, ',', '.') ?> <?= hg_home_h(strtolower($category['title'])) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($recentContent): ?>
        <section class="home-section" aria-labelledby="home-recent-content-title">
            <div class="home-section-head">
                <h2 id="home-recent-content-title">Contenido actualizado recientemente</h2>
            </div>
            <div class="home-reading-grid">
                <?php foreach ($recentContent as $item): ?>
                    <?php
                    $excerpt = trim(preg_replace('/\s+/', ' ', strip_tags((string)($item['description'] ?? ''))));
                    if (function_exists('mb_strimwidth')) {
                        $excerpt = mb_strimwidth($excerpt, 0, 170, '…', 'UTF-8');
                    }
                    $updatedAt = strtotime((string)($item['updated_at'] ?? ''));
                    ?>
                    <a class="home-reading-card" href="<?= hg_home_h($item['href'] ?? '#') ?>">
                        <span class="home-reading-type"><?= hg_home_h($item['type_label'] ?? 'Contenido') ?> actualizado</span>
                        <span class="home-reading-title"><?= hg_home_h($item['title'] ?? '') ?></span>
                        <?php if ($excerpt !== ''): ?><span class="home-reading-description"><?= hg_home_h($excerpt) ?></span><?php endif; ?>
                        <?php if ($updatedAt): ?><time class="home-reading-date" datetime="<?= hg_home_h(date('Y-m-d', $updatedAt)) ?>">Actualizado <?= hg_home_h(date('d/m/Y', $updatedAt)) ?> <span aria-hidden="true">→</span></time><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php /*
    <section class="home-section home-scale" aria-labelledby="home-scale-title">
        <div class="home-section-head">
            <h2 id="home-scale-title">Escala</h2>
        </div>
        <dl class="home-stat-grid">
            <?php foreach ($stats as $stat): ?>
                <?php if ($stat['value'] === null) { continue; } ?>
                <div class="home-stat-card">
                    <dt><?= hg_home_h($stat['label']) ?></dt>
                    <dd><?= number_format((int)$stat['value'], 0, ',', '.') ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>
    */
    ?>
</main>
