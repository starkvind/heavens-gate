<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
include_once(__DIR__ . '/../../helpers/character_avatar.php');

$archeRaw = hg_request_param($hgRequest, 'archetype');
$archeId = (int)(resolve_pretty_id($link, 'dim_archetypes', (string)$archeRaw) ?? 0);
$archetype = $archeId > 0 ? hg_rules_fetch_archetype($link, $archeId) : null;
if (!$archetype) {
    echo 'No se encontraron resultados para la busqueda.';
    return;
}

$archeName = htmlspecialchars((string)$archetype['name']);
$archeDesc = (string)($archetype['description'] ?? '');
$archeWill = (string)($archetype['willpower_text'] ?? '');
$archeOrigName = htmlspecialchars((string)($archetype['origin_name'] ?? '-'));
if ($archeOrigName === '') $archeOrigName = '-';

$pageSect = 'Arquetipo';
$pageTitle2 = $archeName;
setMetaFromPage($archeName . " | Arquetipos | Heaven's Gate", meta_excerpt($archeDesc), null, 'article');
include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet('/assets/css/hg-docs.css');
else echo '<link rel="stylesheet" href="/assets/css/hg-docs.css">';

$itemImg = 'img/inv/no-photo.webp';
ob_start();
echo "<div class='power-card power-card--item'><div class='power-card__banner'><span class='power-card__title'>{$archeName}</span></div><div class='power-card__body'><div class='power-card__media'><div class='power-card__img-wrap'><img class='power-card__img' src='" . htmlspecialchars($itemImg) . "' alt='{$archeName}'/></div></div><div class='power-card__stats'><div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>{$archeOrigName}</div></div></div></div>";
if ($archeDesc !== '') echo "<div class='power-card__desc'><div class='power-card__desc-title'>Descripción</div><div class='power-card__desc-body'>{$archeDesc}</div></div>";
if ($archeWill !== '') echo "<div class='power-card__desc'><div class='power-card__desc-title'>Fuerza de Voluntad</div><div class='power-card__desc-body'>{$archeWill}</div></div>";
echo '</div>';
$infoHtml = ob_get_clean();

$excludeChronicles = isset($excludeChronicles) ? hg_rules_normalize_int_csv($excludeChronicles) : '';
$natureOwners = hg_rules_fetch_archetype_owners($link, $archeId, 'nature', $excludeChronicles);
$demeanorOwners = hg_rules_fetch_archetype_owners($link, $archeId, 'demeanor', $excludeChronicles);

if ($natureOwners || $demeanorOwners) {
    echo "<div class='hg-tabs'><button class='hgTabBtn' data-tab='info'>Información</button><button class='hgTabBtn' data-tab='owners'>Portadores</button></div>";
    echo "<section class='hg-tab-panel' data-tab='info'>{$infoHtml}</section><section class='hg-tab-panel' data-tab='owners'>";
    foreach ([['Naturaleza',$natureOwners],['Conducta',$demeanorOwners]] as [$label,$owners]) {
        if (!$owners) continue;
        echo "<div class='owners-section-title'>{$label}</div><div class='grupoBioClan'><div class='contenidoAfiliacion'>";
        foreach ($owners as $o) {
            $oid=(int)($o['id']??0);$name=(string)($o['name']??'');$href=pretty_url($link,'fact_characters','/characters',$oid);
            hg_render_character_avatar_tile(['href'=>$href,'title'=>$name,'name'=>$name,'alias'=>(string)($o['alias']??''),'character_id'=>$oid,'image_url'=>(string)($o['image_url']??''),'gender'=>(string)($o['gender']??''),'status'=>(string)($o['status']??''),'character_kind'=>hg_character_kind_from_row($o),'target_blank'=>true]);
        }
        echo '</div></div><p align="right">Personajes (' . $label . '): ' . count($owners) . '</p>';
    }
    echo '</section>';
} else echo $infoHtml;
?>