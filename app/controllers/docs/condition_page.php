<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../../helpers/public_response.php');

$conditionRaw = hg_request_param($hgRequest, 'condition');
$conditionId = 0;
if ($conditionRaw !== '') {
    if (preg_match('/^\d+$/', $conditionRaw)) $conditionId = (int)$conditionRaw;
    elseif (function_exists('resolve_pretty_id')) $conditionId = (int)(resolve_pretty_id($link, 'dim_character_conditions', $conditionRaw) ?? 0);
}

if (!$link || $conditionId <= 0) {
    hg_public_render_error('Condición no disponible', 'No se pudo cargar esta condición.', 404, true);
    return;
}

$condition = hg_rules_fetch_condition($link, $conditionId);
if (!$condition) {
    hg_public_render_error('Condición no encontrada', 'La condición solicitada no existe o ya no está disponible.', 404, true);
    return;
}

$conditionName = htmlspecialchars((string)$condition['name']);
$conditionCategory = htmlspecialchars((string)($condition['category'] ?? ''));
$conditionDescription = (string)($condition['description'] ?? '');
$conditionOriginName = htmlspecialchars((string)($condition['origin_name'] ?? ''));
if ($conditionOriginName === '') $conditionOriginName = '-';
$conditionMaxInstancesRaw = $condition['max_instances'] ?? null;
$conditionMaxInstancesText = $conditionMaxInstancesRaw === null ? 'Sin límite' : ((int)$conditionMaxInstancesRaw === 1 ? 'Única' : (string)((int)$conditionMaxInstancesRaw));

$excludeChronicles = isset($excludeChronicles) ? hg_rules_normalize_int_csv($excludeChronicles) : '';
$conditionEffects = hg_rules_fetch_condition_effects($link, $conditionId);
$conditionOwners = hg_rules_fetch_condition_owners($link, $conditionId, $excludeChronicles);

$pageSect = 'Condición';
$pageTitle2 = $conditionName;
setMetaFromPage($conditionName . " | Condiciones | Heaven's Gate", meta_excerpt($conditionDescription), null, 'article');
include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet('/assets/css/hg-docs.css');
else echo '<link rel="stylesheet" href="/assets/css/hg-docs.css">';

ob_start();
echo "<div class='power-card power-card--merfla'><div class='power-card__banner'><span class='power-card__title'>{$conditionName}</span></div><div class='power-card__body'><div class='power-card__media'><img class='power-card__img power-card__img--framed' src='img/inv/no-photo.webp' alt='{$conditionName}'/></div><div class='power-card__stats'>";
if ($conditionCategory !== '') echo "<div class='power-stat'><div class='power-stat__label'>Categoría</div><div class='power-stat__value'>{$conditionCategory}</div></div>";
echo "<div class='power-stat'><div class='power-stat__label'>Máx. repeticiones</div><div class='power-stat__value'>" . htmlspecialchars($conditionMaxInstancesText, ENT_QUOTES, 'UTF-8') . "</div></div>";
echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>{$conditionOriginName}</div></div>";
echo "<div class='power-stat'><div class='power-stat__label'>Efectos</div><div class='power-stat__value'>" . count($conditionEffects) . "</div></div></div></div>";
if (trim($conditionDescription) !== '') echo "<div class='power-card__desc'><div class='power-card__desc-title'>Descripción</div><div class='power-card__desc-body'>{$conditionDescription}</div></div>";
if ($conditionEffects) {
    echo "<div class='power-card__desc'><div class='power-card__desc-title'>Efectos sobre rasgos</div><div class='power-card__desc-body'><ul>";
    foreach ($conditionEffects as $effect) {
        $traitName = htmlspecialchars((string)($effect['trait_name'] ?? ''));
        $slug = (string)($effect['trait_pretty_id'] ?? '');
        if ($slug === '') $slug = (string)((int)($effect['trait_id'] ?? 0));
        $href = '/rules/traits/' . rawurlencode($slug);
        $modifier = (int)($effect['modifier_value'] ?? 0);
        $modifierText = $modifier > 0 ? '+' . $modifier : (string)$modifier;
        $effectDesc = trim((string)($effect['effect_description'] ?? ''));
        echo "<li><a href='" . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . "'>{$traitName}</a>: <strong>" . htmlspecialchars($modifierText, ENT_QUOTES, 'UTF-8') . '</strong>';
        if ($effectDesc !== '') echo ' <span>(' . htmlspecialchars($effectDesc, ENT_QUOTES, 'UTF-8') . ')</span>';
        echo '</li>';
    }
    echo '</ul></div></div>';
}
echo '</div>';
$infoHtml = ob_get_clean();

if ($conditionOwners) {
    echo "<div class='hg-tabs'><button class='hgTabBtn' data-tab='info'>Información</button><button class='hgTabBtn' data-tab='owners'>Afectados</button></div>";
    echo "<section class='hg-tab-panel' data-tab='info'>{$infoHtml}</section><section class='hg-tab-panel' data-tab='owners'><div class='grupoBioClan'><div class='contenidoAfiliacion'>";
    foreach ($conditionOwners as $o) {
        $oid = (int)($o['id'] ?? 0); $name = (string)($o['name'] ?? '');
        $href = pretty_url($link, 'fact_characters', '/characters', $oid);
        hg_render_character_avatar_tile(['href'=>$href,'title'=>$name,'name'=>$name,'alias'=>(string)($o['alias'] ?? ''),'character_id'=>$oid,'image_url'=>(string)($o['image_url'] ?? ''),'gender'=>(string)($o['gender'] ?? ''),'status'=>(string)($o['status'] ?? ''),'character_kind'=>hg_character_kind_from_row($o),'target_blank'=>true]);
    }
    echo '</div></div></section>';
} else echo $infoHtml;
?>