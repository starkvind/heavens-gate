<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
include_once __DIR__ . '/../../helpers/public_response.php';

$rawId = hg_request_param($hgRequest, 'action');
$actionId = preg_match('/^\d+$/', $rawId) ? (int)$rawId : (int)(resolve_pretty_id($link, 'fact_actions', $rawId) ?? 0);
if (!$link || !($link instanceof mysqli) || $actionId <= 0) {
    hg_public_render_error('Acción no encontrada', 'La acción solicitada no existe.', 404, true);
    return;
}

$action = hg_rules_fetch_action($link, $actionId);
if (!$action) {
    hg_public_render_error('Acción no encontrada', 'La acción solicitada no existe.', 404, true);
    return;
}

if (!function_exists('hg_action_page_h')) {
    function hg_action_page_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_action_page_difficulty')) {
    function hg_action_page_difficulty(array $action): string
    {
        if (($action['difficulty_mode'] ?? '') === 'fixed') return 'Fija: ' . (int)$action['fixed_difficulty'];
        $parts = ['Variable'];
        if ((int)($action['suggested_difficulty'] ?? 0) > 0) $parts[] = 'sugerida ' . (int)$action['suggested_difficulty'];
        if ((int)($action['min_difficulty'] ?? 0) > 0 && (int)($action['max_difficulty'] ?? 0) > 0) $parts[] = (int)$action['min_difficulty'] . '–' . (int)$action['max_difficulty'];
        return implode(' · ', $parts);
    }
}

$name = hg_action_page_h($action['name']);
$pageSect = 'Acciones';
$pageTitle2 = $name;
setMetaFromPage($name . " | Acciones | Heaven's Gate", meta_excerpt((string)$action['text']), null, 'article');
include 'app/partials/main_nav_bar.php';
$image = trim((string)($action['image_url'] ?? ''));
if ($image === '') $image = 'img/inv/no-photo.webp';
elseif (!str_contains($image, '/')) $image = 'img/actions/' . $image;
?>
<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/hg-docs.css'); } else { ?><link rel="stylesheet" href="/assets/css/hg-docs.css"><?php } ?>
<div class="power-card power-card--action">
    <div class="power-card__banner"><span class="power-card__title"><?= $name ?></span></div>
    <div class="power-card__body">
        <div class="power-card__media"><img class="power-card__img power-card__img--framed" src="<?= hg_action_page_h($image) ?>" alt="<?= $name ?>"></div>
        <div class="power-card__stats">
            <div class="power-stat"><div class="power-stat__label">Categoría</div><div class="power-stat__value"><?= hg_action_page_h($action['category']) ?></div></div>
            <div class="power-stat"><div class="power-stat__label">Tirada</div><div class="power-stat__value"><?= hg_action_page_h($action['attribute_name']) ?> + <?= hg_action_page_h($action['skill_name']) ?></div></div>
            <div class="power-stat"><div class="power-stat__label">Dificultad</div><div class="power-stat__value"><?= hg_action_page_h(hg_action_page_difficulty($action)) ?></div></div>
            <?php if (trim((string)$action['origin_name']) !== ''): ?><div class="power-stat"><div class="power-stat__label">Origen</div><div class="power-stat__value"><?= hg_action_page_h($action['origin_name']) ?></div></div><?php endif; ?>
        </div>
    </div>
    <?php if (trim((string)$action['text']) !== ''): ?><div class="power-card__desc"><div class="power-card__desc-title">Descripción</div><div class="power-card__desc-body"><?= $action['text'] ?></div></div><?php endif; ?>
</div>
