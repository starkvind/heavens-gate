<?php setMetaFromPage("Heaven's Gate", "Archivo vivo de una cronica alternativa de Hombre Lobo: El Apocalipsis. Explora personajes, temporadas, eventos, mapas y material de juego.", null, 'website'); ?>
<?php
if (!$link) {
    die("Error de conexion a la base de datos.");
}

include("app/partials/main_nav_bar.php");
echo '<link rel="stylesheet" href="/assets/css/hg-main.css">';

if (!function_exists('hg_home_h')) {
    function hg_home_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hg_home_count_table')) {
    function hg_home_count_table(mysqli $link, string $table, string $where = '1=1'): ?int {
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

if (!function_exists('hg_home_excerpt')) {
    function hg_home_excerpt(string $html, int $maxLen = 180): string {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $maxLen
                ? mb_substr($text, 0, $maxLen, 'UTF-8') . '...'
                : $text;
        }

        return strlen($text) > $maxLen ? substr($text, 0, $maxLen) . '...' : $text;
    }
}

$stats = [
    [
        'label' => 'Personajes',
        'value' => hg_home_count_table($link, 'fact_characters'),
        'href' => '/characters',
    ],
    [
        'label' => 'Capitulos',
        'value' => hg_home_count_table($link, 'dim_chapters'),
        'href' => '/chapters',
    ],
    [
        'label' => 'Eventos',
        'value' => hg_home_count_table($link, 'fact_timeline_events'),
        'href' => '/timeline',
    ],
    [
        'label' => 'Documentos',
        'value' => hg_home_count_table($link, 'fact_docs'),
        'href' => '/documents',
    ],
    [
        'label' => 'Cronicas',
        'value' => hg_home_count_table($link, 'dim_chronicles'),
        'href' => '/chronicles',
    ],
    [
        'label' => 'Mapas',
        'value' => hg_home_count_table($link, 'dim_maps'),
        'href' => '/maps',
    ],
];

$newsRows = [];
if ($stmt = mysqli_prepare($link, "SELECT title, message, author, posted_at FROM fact_admin_posts ORDER BY id DESC LIMIT 3")) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $newsRows[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$shortcuts = [
    [
        'eyebrow' => 'Seguir la historia',
        'title' => 'Linea temporal',
        'text' => 'Recorre los sucesos principales y entra desde ahí en personajes, capítulos y crónicas relacionadas.',
        'href' => '/timeline',
    ],
    [
        'eyebrow' => 'Consultar la campaña',
        'title' => 'Temporadas y Capítulos',
        'text' => 'Consulta el archivo narrativo de la crónica y su evolución a lo largo de los años.',
        'href' => '/seasons',
    ],
    [
        'eyebrow' => 'Conocer al reparto',
        'title' => 'Personajes',
        'text' => 'Explora biografías, relaciones, grupos y organizaciones del universo de Heaven\'s Gate.',
        'href' => '/characters',
    ],
    [
        'eyebrow' => 'Entrar por contexto',
        'title' => 'Crónicas',
        'text' => 'Ubicate por líneas argumentales y realidades antes de sumergirte en el detalle de las fichas.',
        'href' => '/chronicles',
    ],
    [
        'eyebrow' => 'Consultar material',
        'title' => 'Sistemas y poderes',
        'text' => 'Accede a reglas, razas, auspicios, dones, rituales, disciplinas y demás material de juego.',
        'href' => '/systems',
    ],
    [
        'eyebrow' => 'Explorar el mundo',
        'title' => 'Mapas',
        'text' => 'Navega por lugares de interés y usa la geografía como puerta de entrada al trasfondo.',
        'href' => '/maps',
    ],
    [
        'eyebrow' => 'Ir al detalle',
        'title' => 'Buscador',
        'text' => 'Si ya sabes qué quieres encontrar, ésta es la forma más rápida de llegar a ello.',
        'href' => '/search',
    ],
    [
        'eyebrow' => 'Historial de cambios',
        'title' => 'Noticias',
        'text' => 'Mantente al día de novedades editoriales, cambios de contenido y ajustes recientes de la web.',
        'href' => '/news',
    ],
];
?>

<div class="home-landing">
    <section class="home-hero">
        <div class="home-hero-copy">
            <h2>Bienvenid@ a Heaven&apos;s Gate</h2>
            <p class="home-lead">Heaven&apos;s Gate es el archivo vivo de una cr&oacute;nica alternativa de <b>Hombre Lobo: 
                El Apocalipsis</b> iniciada en 2006. Re&uacute;ne personajes, temporadas, eventos, cronicas, mapas y material 
                de juego dentro de una continuidad propia.</p>
            <p class="home-copy">Si llegas por primera vez, lo mejor es entrar por la historia, por los personajes o por 
                una de las cr&oacute;nicas.</p>
            <p class="home-copy">Si ya conoces el universo, puedes saltar directamente al buscador, las noticias 
                o las secciones de consulta.</p>
            <div class="home-cta-row">
                <a class="home-cta home-cta-primary" href="/timeline">Empezar por la Línea temporal</a>
                <a class="home-cta" href="/characters">Explorar personajes</a>
                <a class="home-cta" href="/news">Ver noticias</a>
            </div>
        </div>
        <div class="home-hero-side">
            <div class="home-stat-grid">
                <?php foreach ($stats as $stat): ?>
                    <?php if ($stat['value'] === null) { continue; } ?>
                    <a class="home-stat-card" href="<?= hg_home_h($stat['href']) ?>">
                        <span class="home-stat-value"><?= number_format((int)$stat['value'], 0, ',', '.') ?></span>
                        <span class="home-stat-label"><?= hg_home_h($stat['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="home-section">
        <div class="home-section-head">
            <h3>Puertas de entrada</h3>
            <p>No hace falta conocer toda la cr&oacute;nica. Estas son las rutas mas &uacute;tiles seg&uacute;n el tipo de visita.</p>
        </div>
        <div class="home-shortcuts-grid">
            <?php foreach ($shortcuts as $shortcut): ?>
                <a class="home-shortcut-card" href="<?= hg_home_h($shortcut['href']) ?>">
                    <span class="home-shortcut-eyebrow"><?= hg_home_h($shortcut['eyebrow']) ?></span>
                    <h4><?= hg_home_h($shortcut['title']) ?></h4>
                    <p><?= hg_home_h($shortcut['text']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>
