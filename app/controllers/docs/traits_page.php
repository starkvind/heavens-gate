<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
include_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!function_exists('hg_render_trait_levels_with_gems')) {
    function hg_render_trait_levels_with_gems(string $levelsHtml): string
    {
        $levelsHtml = trim($levelsHtml);
        if ($levelsHtml === '' || !class_exists('DOMDocument')) return $levelsHtml;
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?><div id="hg-trait-levels-root">' . $levelsHtml . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$loaded) { libxml_clear_errors(); libxml_use_internal_errors($previousUseInternalErrors); return $levelsHtml; }
        $root = $dom->getElementById('hg-trait-levels-root');
        if (!$root instanceof DOMElement) { libxml_clear_errors(); libxml_use_internal_errors($previousUseInternalErrors); return $levelsHtml; }
        $children = [];
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMText && trim((string)$child->textContent) === '') continue;
            $children[] = $child;
        }
        if (count($children) !== 1 || !($children[0] instanceof DOMElement) || strtolower($children[0]->tagName) !== 'ol') { libxml_clear_errors(); libxml_use_internal_errors($previousUseInternalErrors); return $levelsHtml; }
        $items = [];
        foreach ($children[0]->childNodes as $child) {
            if ($child instanceof DOMText && trim((string)$child->textContent) === '') continue;
            if (!($child instanceof DOMElement) || strtolower($child->tagName) !== 'li') { libxml_clear_errors(); libxml_use_internal_errors($previousUseInternalErrors); return $levelsHtml; }
            $items[] = $child;
        }
        if (!$items) { libxml_clear_errors(); libxml_use_internal_errors($previousUseInternalErrors); return $levelsHtml; }
        $rendered = "<div class='hg-traits-levels'>";
        foreach ($items as $index => $item) {
            $level = $index + 1;
            $itemHtml = '';
            foreach ($item->childNodes as $node) $itemHtml .= $dom->saveHTML($node);
            $gemFile = sprintf('img/ui/gems/attr/gem-attr-%02d.webp', $level);
            $rendered .= "<div class='hg-traits-levels__item'><div class='hg-traits-levels__icon'><img class='hg-traits-gem hg-traits-levels__img' src='{$gemFile}' alt='Nivel {$level}'/></div><div class='hg-traits-levels__text'>{$itemHtml}</div></div>";
        }
        $rendered .= '</div>';
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);
        return $rendered;
    }
}

$traitPageID = (int)hg_request_param($hgRequest, 'trait');
$skillId = $traitPageID;
$trait = $traitPageID > 0 ? hg_rules_fetch_trait($link, $traitPageID) : null;
if (!$trait) return;

$traitName = htmlspecialchars((string)$trait['name']);
$nameSkill = $traitName;
$traitKind = htmlspecialchars((string)($trait['rule_kind'] ?? ''));
$traitClassRaw = (string)($trait['classification'] ?? '');
$traitClass = htmlspecialchars(strlen($traitClassRaw) >= 5 ? substr($traitClassRaw, 4) : $traitClassRaw);
$traitDescription = (string)($trait['description'] ?? '');
$traitLevels = (string)($trait['levels'] ?? '');
$traitPosse = (string)($trait['posse'] ?? '');
$traitSpecial = (string)($trait['special'] ?? '');
$traitOriginName = htmlspecialchars((string)($trait['origin_name'] ?? '-'));
if ($traitOriginName === '') $traitOriginName = '-';

$pageSect = 'Rasgo';
$pageTitle2 = $traitName;
setMetaFromPage($traitName . " | Rasgos | Heaven's Gate", meta_excerpt($traitDescription), null, 'article');
include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-docs.css');
    hg_page_register_stylesheet('/assets/css/hg-traits.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-docs.css"><link rel="stylesheet" href="/assets/css/hg-traits.css">';
}

ob_start();
echo "<div class='power-card power-card--trait'><div class='power-card__banner'><span class='power-card__title'>{$traitName}</span></div><div class='power-card__body'><div class='power-card__media'><img class='power-card__img power-card__img--framed' src='img/inv/no-photo.webp' alt='{$traitName}'/></div><div class='power-card__stats'>";
if ($traitKind !== '') echo "<div class='power-stat'><div class='power-stat__label'>Tipo de rasgo</div><div class='power-stat__value'>{$traitKind}</div></div>";
if ($traitClass !== '') echo "<div class='power-stat'><div class='power-stat__label'>Clasificaci&oacute;n</div><div class='power-stat__value'>{$traitClass}</div></div>";
if (trim($traitPosse) !== '') echo "<div class='power-stat'><div class='power-stat__label'>Pose&iacute;do por</div><div class='power-stat__value'>{$traitPosse}</div></div>";
if (trim($traitSpecial) !== '') echo "<div class='power-stat'><div class='power-stat__label'>Maestr&iacute;as</div><div class='power-stat__value'>{$traitSpecial}</div></div>";
echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>{$traitOriginName}</div></div></div></div>";
if (trim($traitDescription) !== '') echo "<div class='power-card__desc'><div class='power-card__desc-title'>Descripci&oacute;n</div><div class='power-card__desc-body'>{$traitDescription}</div></div>";
if (trim($traitLevels) !== '') {
    $traitLevelsHtml = hg_render_trait_levels_with_gems($traitLevels);
    echo "<div class='power-card__desc'><div class='power-card__desc-title'>Niveles</div><div class='power-card__desc-body'>{$traitLevelsHtml}</div></div>";
}
echo '</div>';
$infoHtml = ob_get_clean();

$excludeChronicles = isset($excludeChronicles) ? hg_rules_normalize_int_csv($excludeChronicles) : '';
$ownerRows = hg_rules_fetch_trait_owners($link, $traitPageID, $excludeChronicles);
$traitOwners = [];
foreach ($ownerRows as $row) {
    $value = (int)($row['value'] ?? 0);
    if ($value >= 1) $traitOwners[$value][] = $row;
}
ksort($traitOwners);

if ($traitOwners) {
    echo "<div class='hg-tabs'><button class='hgTabBtn' data-tab='info'>Informaci&oacute;n</button><button class='hgTabBtn' data-tab='owners'>Portadores</button></div>";
    echo "<section class='hg-tab-panel' data-tab='info'>{$infoHtml}</section><section class='hg-tab-panel' data-tab='owners'>";
    $totalOwners = 0;
    foreach ($traitOwners as $value => $owners) {
        $totalOwners += count($owners);
        $gemSrc = "img/ui/gems/attr/gem-attr-0{$value}.webp";
        $puntos = $value === 1 ? '1 punto' : "{$value} puntos";
        echo "<div class='power-card__desc'><div class='power-card__desc-title'><img class='hg-traits-gem hg-traits-owner-level' src='{$gemSrc}' alt='{$puntos}'/>&nbsp;</div><div class='power-card__desc-body'><div class='hg-traits-owners'><div class='hg-affiliation-content'>";
        foreach ($owners as $o) {
            $oid = (int)($o['id'] ?? 0);
            $name = (string)($o['name'] ?? '');
            $href = pretty_url($link, 'fact_characters', '/characters', $oid);
            hg_render_character_avatar_tile(['href'=>$href,'title'=>$name,'name'=>$name,'alias'=>(string)($o['alias'] ?? ''),'character_id'=>$oid,'image_url'=>(string)($o['image_url'] ?? ''),'gender'=>(string)($o['gender'] ?? ''),'status'=>(string)($o['status'] ?? ''),'character_kind'=>hg_character_kind_from_row($o),'target_blank'=>true]);
        }
        echo '</div></div></div></div>';
    }
    echo "<p align='right'>Personajes: {$totalOwners}</p></section>";
} else {
    echo $infoHtml;
}
?>