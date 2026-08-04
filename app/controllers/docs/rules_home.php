<?php
setMetaFromPage("Reglamento | Heaven's Gate", "Resumen y acceso al reglamento del juego.", null, 'website');

$pageSect = 'Reglamento';

if (!function_exists('hg_rules_home_h')) {
    function hg_rules_home_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_rules_home_count')) {
    function hg_rules_home_count(mysqli $link, string $table): ?int
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

$rulesTypes = [
    ['name' => 'Rasgos', 'href' => '/rules/traits', 'desc' => 'Rasgos numéricos, como Atributos y Habilidades.', 'table' => 'dim_traits', 'image' => '/img/og/og_image_bio.webp'],
    ['name' => 'Méritos y Defectos', 'href' => '/rules/merits-flaws', 'desc' => 'Rasgos que definen ventajas y debilidades del personaje.', 'table' => 'dim_merits_flaws', 'image' => '/img/og/og_image_power.webp'],
    ['name' => 'Condiciones', 'href' => '/rules/conditions', 'desc' => 'Deformidades, heridas de guerra y trastornos mentales que afectan a los personajes.', 'table' => 'dim_character_conditions', 'image' => '/img/og/og_image_monster.webp'],
    ['name' => 'Acciones', 'href' => '/rules/actions', 'desc' => 'Tiradas básicas que combinan un Atributo, una Habilidad y una dificultad.', 'table' => 'fact_actions', 'image' => '/img/og/og_image_temp.webp'],
    ['name' => 'Personalidades', 'href' => '/rules/archetypes', 'desc' => 'Arquetipos de personalidad que ayudan a interpretar el personaje.', 'table' => 'dim_archetypes', 'image' => '/img/og/og_image.webp'],
    ['name' => 'Maniobras de pelea', 'href' => '/rules/maneuvers', 'desc' => 'Técnicas de combate que van más allá de la pelea simple.', 'table' => 'fact_combat_maneuvers', 'image' => '/img/og/og_image_power.webp'],
];
foreach ($rulesTypes as &$rule) {
    $rule['count'] = isset($link) && ($link instanceof mysqli) ? hg_rules_home_count($link, $rule['table']) : null;
}
unset($rule);
?>

<link rel="stylesheet" href="/assets/css/hg-main.css">
<link rel="stylesheet" href="/assets/css/hg-docs.css">

<main class="chron-detail rules-chron-home" aria-labelledby="rules-home-title">
    <section class="chron-box">
        <div class="chron-box-head">
            <h2 id="rules-home-title">Reglamento</h2>
            <p>Material de consulta para interpretar personajes, resolver acciones y preparar el juego.</p>
        </div>
        <div class="chron-grid" aria-label="Secciones del reglamento">
            <?php foreach ($rulesTypes as $rule): ?>
                <a class="chron-card" href="<?= hg_rules_home_h($rule['href']) ?>">
                    <img src="<?= hg_rules_home_h($rule['image']) ?>" alt="" loading="lazy">
                    <div class="chron-card-body">
                        <h3><?= hg_rules_home_h($rule['name']) ?></h3>
                        <p><?= hg_rules_home_h($rule['desc']) ?></p>
                        <div class="chron-card-meta">
                            <?php if ($rule['count'] !== null): ?><span><?= number_format((int)$rule['count'], 0, ',', '.') ?> elementos</span><?php endif; ?>
                            <span>Consultar &rarr;</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</main>