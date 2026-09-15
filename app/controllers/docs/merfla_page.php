<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
include_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!function_exists('hg_merfla_normalize_text')) {
    function hg_merfla_normalize_text($value)
    {
        $text = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
        $text = trim(mb_strtolower($text, 'UTF-8'));
        return strtr($text, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u']);
    }
}

$mafPageID = (int)hg_request_param($hgRequest, 'merit_flaw');
$returnID = hg_request_query_param($hgRequest, 'r');
$merit = $mafPageID > 0 ? hg_rules_fetch_merit($link, $mafPageID) : null;
if (!$merit) return;

$mafId = htmlspecialchars((string)$merit['id']);
$mafName = htmlspecialchars((string)$merit['name']);
$mafType = htmlspecialchars((string)($merit['tipo'] ?? ''));
$mafAfil = htmlspecialchars((string)($merit['afiliacion'] ?? ''));
$mafCoste = htmlspecialchars((string)($merit['coste'] ?? ''));
$mafDesc = (string)($merit['descripcion'] ?? '');
$mafSystem = htmlspecialchars((string)($merit['sistema'] ?? ''));
$mafOriginName = htmlspecialchars((string)($merit['origin_name'] ?? '-'));
if ($mafOriginName === '') $mafOriginName = '-';

$mafTypeNormalized = hg_merfla_normalize_text($mafType);
if ($mafTypeNormalized === 'meritos' || strpos($mafTypeNormalized, 'merit') !== false) {
    $returnType = 1; $mafNameType = 'M&eacute;rito';
} elseif ($mafTypeNormalized === 'defectos' || strpos($mafTypeNormalized, 'defect') !== false) {
    $returnType = 2; $mafNameType = 'Defecto';
} else {
    $returnType = 1; $mafNameType = 'Tipo desconocido';
}

$returnArray = [];
foreach (hg_rules_fetch_merit_affiliations($link) as $index => $affiliation) $returnArray[$affiliation] = $index + 1;
$typeReturnId = $returnArray[html_entity_decode($mafAfil, ENT_QUOTES, 'UTF-8')] ?? 0;

$excludeChronicles = isset($excludeChronicles) ? hg_rules_normalize_int_csv($excludeChronicles) : '';
$mafOwners = hg_rules_fetch_merit_owners($link, $mafPageID, $excludeChronicles);

$costMeritFlaw = $mafCoste;
$pageSect = $mafNameType;
$pageTitle2 = $mafName;
setMetaFromPage($mafName . " | Méritos y Defectos | Heaven's Gate", meta_excerpt($mafDesc), null, 'article');
include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet('/assets/css/hg-docs.css');
else echo '<link rel="stylesheet" href="/assets/css/hg-docs.css">';

ob_start();
$itemImg = 'img/inv/no-photo.webp';
$costText = is_numeric((string)$costMeritFlaw) ? ((int)$costMeritFlaw . ' ' . ((int)$costMeritFlaw === 1 ? 'punto' : 'puntos')) : (string)$costMeritFlaw;
echo "<div class='power-card power-card--merfla'><div class='power-card__banner'><span class='power-card__title'>{$mafName}</span></div><div class='power-card__body'><div class='power-card__media'><img class='power-card__img power-card__img--framed' src='{$itemImg}' alt='{$mafName}'/></div><div class='power-card__stats'>";
if ($mafNameType !== '') echo "<div class='power-stat'><div class='power-stat__label'>Tipo</div><div class='power-stat__value'>{$mafNameType}</div></div>";
if ($costText !== '' && $costText !== '0 puntos') echo "<div class='power-stat'><div class='power-stat__label'>Coste</div><div class='power-stat__value'>{$costText}</div></div>";
if ($mafAfil !== '') echo "<div class='power-stat'><div class='power-stat__label'>Categor&iacute;a</div><div class='power-stat__value'>{$mafAfil}</div></div>";
if ($mafSystem !== '') echo "<div class='power-stat'><div class='power-stat__label'>Sistema</div><div class='power-stat__value'>{$mafSystem}</div></div>";
if ($mafOriginName !== '') echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>{$mafOriginName}</div></div>";
echo '</div></div>';
if ($mafDesc !== '') echo "<div class='power-card__desc'><div class='power-card__desc-title'>Descripci&oacute;n</div><div class='power-card__desc-body'>{$mafDesc}</div></div>";
echo '</div>';
$infoHtml = ob_get_clean();

if ($mafOwners) {
    echo "<div class='hg-tabs'><button class='hgTabBtn' data-tab='info'>Informaci&oacute;n</button><button class='hgTabBtn' data-tab='owners'>Portadores</button></div>";
    echo "<section class='hg-tab-panel' data-tab='info'>{$infoHtml}</section><section class='hg-tab-panel' data-tab='owners'><div class='grupoBioClan'><div class='contenidoAfiliacion'>";
    foreach ($mafOwners as $o) {
        $oid=(int)($o['id']??0);$name=(string)($o['nombre']??'');$href=pretty_url($link,'fact_characters','/characters',$oid);
        hg_render_character_avatar_tile(['href'=>$href,'title'=>$name,'name'=>$name,'alias'=>(string)($o['alias']??''),'character_id'=>$oid,'image_url'=>(string)($o['image_url']??''),'gender'=>(string)($o['gender']??''),'status'=>(string)($o['status']??''),'character_kind'=>hg_character_kind_from_row($o),'target_blank'=>true]);
    }
    echo '</div></div><p align="right">Personajes: ' . count($mafOwners) . '</p></section>';
} else echo $infoHtml;
?>