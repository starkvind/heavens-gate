<?php
setMetaFromPage(
    "Biografías por grupo | Heaven's Gate",
    "Listado de biografias agrupadas por tipo y organizacion.",
    null,
    'website'
);

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
require_once(__DIR__ . '/../../domains/characters/type_queries.php');
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-archive.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-archive.css">';
}

if (!$link) {
    hg_public_log_error('bio_group', 'missing DB connection');
    hg_public_render_error(
        'Biografías no disponibles',
        'No se pudo cargar el listado de biografias por grupo en este momento.'
    );
    return;
}

if (!function_exists('hg_bio_group_h')) {
    function hg_bio_group_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$idTipo = (int)hg_request_param($hgRequest, 'character_type');
if ($idTipo <= 0) {
    hg_public_render_not_found(
        'Tipo no encontrado',
        'No se encontro el tipo de personaje solicitado.',
        true
    );
    return;
}

$rowType = hg_character_types_fetch_one($link, $idTipo);
if (!$rowType) {
    hg_public_render_not_found(
        'Tipo no encontrado',
        'No se encontro el tipo de personaje solicitado.',
        true
    );
    return;
}

$nombreTipoRaw = (string)($rowType['kind'] ?? '');
$typeImageUrl = trim((string)($rowType['image_url'] ?? ''));
if (strpos($typeImageUrl, '/public/') === 0) $typeImageUrl = substr($typeImageUrl, 7);
if ($typeImageUrl === '') $typeImageUrl = '/img/og/og_image_bio.webp';
$typeDescription = trim((string)($rowType['description'] ?? ''));
$bioLabel = html_entity_decode('Biograf&iacute;as', ENT_QUOTES | ENT_HTML5, 'UTF-8');
$pageSect = $nombreTipoRaw . ' | ' . $bioLabel;
$pageTitle2 = $nombreTipoRaw;

setMetaFromPage(
    $nombreTipoRaw . ' | ' . $bioLabel . " | Heaven's Gate",
    $typeDescription !== '' ? $typeDescription : ('Listado de personajes agrupados por clan para el tipo ' . $nombreTipoRaw . '.'),
    $typeImageUrl,
    'website'
);

$rows = hg_character_types_fetch_characters($link, $idTipo, $excludeChronicles ?? '');
if ($rows === null) {
    hg_public_log_error('bio_group', 'character type query failed');
    hg_public_render_error(
        'Biografías no disponibles',
        'No se pudo cargar el listado de biografias por grupo en este momento.',
        500,
        true
    );
    return;
}

$howMuch = count($rows);
$grupos = [];
foreach ($rows as $rowPJ) {
    $clanId = (int)($rowPJ['organization_id'] ?? 0);
    $clanName = (string)($rowPJ['clan_name'] ?? 'Sin clan');
    $clanPretty = (string)($rowPJ['clan_pretty_id'] ?? '');
    $key = $clanId > 0 ? (string)$clanId : 'none';

    if (!isset($grupos[$key])) {
        $grupos[$key] = [
            'id' => $clanId,
            'name' => $clanName,
            'pretty_id' => $clanPretty,
            'sort_order' => (int)($rowPJ['organization_sort_order'] ?? 999999),
            'items' => [],
        ];
    }
    $grupos[$key]['items'][] = $rowPJ;
}

$keys = array_keys($grupos);
usort(
    $keys,
    static function (string $a, string $b) use ($grupos): int {
        if ($a === 'none') return 1;
        if ($b === 'none') return -1;
        $sortA = (int)($grupos[$a]['sort_order'] ?? 999999);
        $sortB = (int)($grupos[$b]['sort_order'] ?? 999999);
        if ($sortA !== $sortB) return $sortA <=> $sortB;
        return (int)$a <=> (int)$b;
    }
);

include("app/partials/main_nav_bar.php");
?>
<div class="bio-group-page">
<div class="chron-detail">
    <section class="chron-hero">
        <div class="chron-hero-media">
            <img src="<?= hg_bio_group_h($typeImageUrl) ?>" alt="<?= hg_bio_group_h($nombreTipoRaw) ?>">
            <div class="chron-hero-overlay">
                <p class="chron-kicker">Tipo de personaje</p>
                <h2><?= hg_bio_group_h($nombreTipoRaw) ?></h2>
                <?php if ($howMuch > 0): ?>
                    <div class="chron-hero-meta">
                        <span class="chron-hero-pill"><?= number_format($howMuch, 0, ',', '.') ?> <?= $howMuch === 1 ? 'personaje' : 'personajes' ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($typeDescription !== ''): ?>
        <section class="chron-box">
            <div class="chron-box-head">
                <h3>Descripci&oacute;n</h3>
            </div>
            <div class="chron-rich"><?= nl2br(hg_bio_group_h($typeDescription)) ?></div>
        </section>
    <?php endif; ?>
</div>
<?php

foreach ($keys as $key) {
    $grupo = $grupos[$key];
    $clanId = (int)$grupo['id'];
    $clanName = (string)$grupo['name'];
    $fieldsetId = 'clan_' . ($clanId > 0 ? $clanId : 'none');

    echo "<h3 class='toggleAfiliacion' data-target='" . hg_bio_group_h($fieldsetId) . "'>" . hg_bio_group_h($clanName) . "</h3>";
    echo "<fieldset class='grupoBioClan'>";
    echo "<div id='" . hg_bio_group_h($fieldsetId) . "' class='contenidoAfiliacion'>";

    foreach ($grupo['items'] as $rowPJ) {
        $idPJ = (int)($rowPJ['id'] ?? 0);
        $nombrePJ = (string)($rowPJ['name'] ?? '');
        $aliasPJ = (string)($rowPJ['alias'] ?? '');
        $imgPJ = (string)hg_character_avatar_url($rowPJ['image_url'] ?? '', $rowPJ['gender'] ?? '');
        $claseRaw = (string)($rowPJ['character_kind'] ?? $rowPJ['kind'] ?? '');
        $estadoPJ = (string)($rowPJ['status'] ?? '');

        if ($aliasPJ === '') $aliasPJ = $nombrePJ;

        $hrefPJ = pretty_url($link, 'fact_characters', '/characters', $idPJ);
        hg_render_character_avatar_tile([
            'href' => $hrefPJ,
            'title' => $nombrePJ,
            'name' => $nombrePJ,
            'alias' => $aliasPJ,
            'character_id' => $idPJ,
            'avatar_url' => $imgPJ,
            'status' => $estadoPJ,
            'character_kind' => $claseRaw,
        ]);
    }

    echo "</div>";
    echo "</fieldset>";
}

echo "<p align='right'>Personajes: " . hg_bio_group_h($howMuch) . "</p>";
?>
</div>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/pages/legacy/controllers-bio-bio_group.css'); } else { ?><link rel="stylesheet" href="/assets/css/pages/legacy/controllers-bio-bio_group.css"><?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggles = document.querySelectorAll('.toggleAfiliacion');

    for (var i = 0; i < toggles.length; i++) {
        toggles[i].addEventListener('click', function () {
            var targetId = this.getAttribute('data-target');
            var el = document.getElementById(targetId);

            if (!el) return;
            el.classList.toggle('oculto');
        });
    }
});
</script>