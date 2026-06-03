<?php setMetaFromPage("Heaven's Gate", "Archivo vivo de una cronica alternativa de Hombre Lobo: El Apocalipsis. Explora personajes, historia, cronicas y sistemas de juego.", null, 'website'); ?>
<?php
include_once(__DIR__ . '/../../helpers/public_response.php');
if (!$link) {
    hg_public_log_error('main_home', 'missing DB connection');
    hg_public_render_error('Inicio no disponible', 'No se pudo cargar la pagina de inicio en este momento.');
    return;
}

include("app/partials/main_nav_bar.php");
echo '<link rel="stylesheet" href="/assets/css/hg-main.css">';

if (!function_exists('hg_home_h')) {
    function hg_home_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_home_count_table')) {
    function hg_home_count_table(mysqli $link, string $table, string $where = '1=1'): ?int
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return null;
        }

        $sql = "SELECT COUNT(*) AS total FROM `{$table}` WHERE {$where}";
        $result = mysqli_query($link, $sql);
        if (!$result) {
            return null;
        }

        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);

        return isset($row['total']) ? (int)$row['total'] : 0;
    }
}

$stats = [
    ['label' => 'Personajes', 'value' => hg_home_count_table($link, 'fact_characters')],
    ['label' => 'Capítulos', 'value' => hg_home_count_table($link, 'dim_chapters')],
    ['label' => 'Eventos', 'value' => hg_home_count_table($link, 'fact_timeline_events')],
    ['label' => 'Documentos', 'value' => hg_home_count_table($link, 'fact_docs')],
    ['label' => 'Crónicas', 'value' => hg_home_count_table($link, 'dim_chronicles')],
    ['label' => 'Mapas', 'value' => hg_home_count_table($link, 'dim_maps')],
    ['label' => 'Sistemas', 'value' => hg_home_count_table($link, 'dim_systems')],
];

$entryPoints = [
    [
        'eyebrow' => 'La narración',
        'title' => 'Historia',
        'text' => 'Temporadas, capítulos y momentos decisivos para recorrer la crónica en su propio ritmo.',
        'href' => '/seasons',
        'cta' => 'Recorrer historia',
    ],
    [
        'eyebrow' => 'Material de juego',
        'title' => 'Sistemas',
        'text' => 'Reglas, razas, auspicios, dones, rituales, disciplinas y demás material de consulta.',
        'href' => '/systems',
        'cta' => 'Consultar sistemas',
    ],
    [
        'eyebrow' => 'Lo sobrenatural',
        'title' => 'Poderes',
        'text' => 'Dones, rituales, tótems y disciplinas para entrar en la parte más viva y peligrosa del juego.',
        'href' => '/powers',
        'cta' => 'Ver poderes',
    ],
    [
        'eyebrow' => 'Juego de cartas',
        'title' => 'Archivo de mnemógeno',
        'text' => 'Un minijuego web para coleccionar cartas con elementos de Heaven\'s Gate, como biografías, poderes, episodios y otras piezas del archivo.',
        'href' => '/games/card-game',
        'cta' => 'Abrir juego',
    ],
];
?>

<div class="home-landing">
    <section class="home-hero">
        <div class="home-hero-copy">
            <!-- <p class="home-kicker">Archivo narrativo y material de juego</p>-->
            <h2 style="font-size: 32px;">Adéntrate en Heaven&apos;s Gate</h2>
        </div>
        <div class="home-hero-body">
            <p class="home-lead">Heaven&apos;s Gate reúne veinte años de historia, personajes, campañas y sistemas en una continuidad propia de <b>Hombre Lobo: El Apocalipsis</b>.</p>
            <p class="home-copy">Aquí conviven el pulso de la crónica, sus protagonistas, sus conflictos y el material de juego que da forma al mundo.</p>
            <div class="home-cta-row">
                <a class="home-cta" href="/characters">Biografías</a>
                <a class="home-cta" href="/organizations">Organizaciones</a>
            </div>
        </div>
        <div class="home-hero-side">
            <div class="home-hero-panel">
                <p class="home-hero-panel-kicker">Varias formas de entrar</p>
                <ul class="home-hero-list">
                    <li>Personajes para seguir a quienes sostienen la historia.</li>
                    <li>Historia para recorrer capítulos, temporadas y giros narrativos.</li>
                    <li>Crónicas para orientarte entre campañas, arcos y realidades.</li>
                    <li>Sistemas y poderes para bajar al material de juego.</li>
                    <li>El Archivo de mnemógeno para echar unas partidas y coleccionar cartas.</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="home-section home-section-accent">
        <div class="home-section-head">
            <h3>Explora el archivo</h3>
            <p>Algunas rutas de consulta para ampliar la visita más allá del núcleo principal de la crónica.</p>
        </div>
        <div class="home-shortcuts-grid">
            <?php foreach ($entryPoints as $entryPoint): ?>
                <a class="home-shortcut-card" href="<?= hg_home_h($entryPoint['href']) ?>">
                    <span class="home-shortcut-eyebrow"><?= hg_home_h($entryPoint['eyebrow']) ?></span>
                    <h4><?= hg_home_h($entryPoint['title']) ?></h4>
                    <p><?= hg_home_h($entryPoint['text']) ?></p>
                    <span class="home-shortcut-cta"><?= hg_home_h($entryPoint['cta']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

<?php

/*
    <section class="home-section">
        <div class="home-section-head">
            <h3>Escala del archivo</h3>
            <p>Una visión rápida de la amplitud del archivo y de todo lo que guarda esta continuidad.</p>
        </div>
        <div class="home-stat-grid">
            <?php foreach ($stats as $stat): ?>
                <?php if ($stat['value'] === null) { continue; } ?>
                <div class="home-stat-card">
                    <span class="home-stat-value"><?= number_format((int)$stat['value'], 0, ',', '.') ?></span>
                    <span class="home-stat-label"><?= hg_home_h($stat['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
*/

?>
</div>
