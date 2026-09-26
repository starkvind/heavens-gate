<?php
$bioListTitle = html_entity_decode('Biograf&iacute;as | Heaven\'s Gate', ENT_QUOTES | ENT_HTML5, 'UTF-8');
$bioListDescription = html_entity_decode('Listado de biograf&iacute;as y personajes.', ENT_QUOTES | ENT_HTML5, 'UTF-8');
setMetaFromPage($bioListTitle, $bioListDescription, '/img/og/og_image_bio.webp', 'website');
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-archive.css');
} else {
    if (function_exists('hg_page_register_stylesheet')) {
        hg_page_register_stylesheet('/assets/css/hg-archive.css');
    } else {
        echo '<link rel="stylesheet" href="/assets/css/hg-archive.css">';
    }
}
include_once(__DIR__ . '/../../helpers/public_response.php');
require_once(__DIR__ . '/../../domains/characters/queries.php');

if (!$link) {
    hg_public_log_error('bio_list', 'missing DB connection');
    hg_public_render_error('Biografias no disponibles', 'No se pudo cargar el listado de biografias en este momento.');
    return;
}

if (!function_exists('hg_bio_list_h')) {
    function hg_bio_list_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$chronicleScope = isset($excludeChronicles) ? $excludeChronicles : '';
$typeCards = hg_characters_fetch_type_cards($link, $chronicleScope);
if ($typeCards === false) {
    hg_public_log_error('bio_list', 'type query failed: ' . mysqli_error($link));
    hg_public_render_error('Biografias no disponibles', 'No se pudo cargar el listado de biografias en este momento.');
    return;
}

include('app/partials/main_nav_bar.php');
?>
<div class="chron-detail">
    <section class="chron-box">
        <div class="chron-box-head">
            <h2>Biograf&iacute;as por tipo</h2>
            <p>Consulta aqu&iacute; los personajes de Heaven's Gate agrupados por su funci&oacute;n en la historia.</p>
        </div>

        <?php if (empty($typeCards)): ?>
            <p class="texti chron-empty">No hay tipos de personaje disponibles.</p>
        <?php else: ?>
            <div class="chron-grid">
                <?php foreach ($typeCards as $type): ?>
                    <?php
                    $typeId = (int)$type['id'];
                    $typeName = (string)$type['name'];
                    $characterCount = (int)$type['total'];
                    $typeDescription = trim((string)($type['description'] ?? ''));
                    if ($typeDescription === '') $typeDescription = 'Personajes clasificados como ' . $typeName . '.';
                    $href = pretty_url($link, 'dim_character_types', '/characters/type', $typeId);
                    ?>
                    <a class="chron-card" href="<?= hg_bio_list_h($href) ?>" title="<?= hg_bio_list_h($typeName) ?>">
                        <img src="<?= hg_bio_list_h($type['image_url']) ?>" alt="<?= hg_bio_list_h($typeName) ?>">
                        <div class="chron-card-body">
                            <h3><?= hg_bio_list_h($typeName) ?></h3>
                            <p><?= hg_bio_list_h($typeDescription) ?></p>
                            <div class="chron-card-meta">
                                <span><?= number_format($characterCount, 0, ',', '.') ?> <?= $characterCount === 1 ? 'personaje' : 'personajes' ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
