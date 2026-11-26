<?php
require_once __DIR__ . '/../../domains/characters/trait_specializations.php';

function hg_trait_specializations_label(string $nameHtml,string $name,array $items,string $suffix=''): string {
    if (!$items) return $nameHtml.$suffix;
    $escape=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $json=$escape(json_encode($items,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    return '<span class="hg-trait-specialized-name"><span class="hg-trait-name-text">'.$nameHtml.$suffix.'</span><button type="button" class="hg-trait-specialization-trigger" aria-label="'.$escape('Especializaciones de '.$name).'" aria-expanded="false" aria-haspopup="dialog" aria-controls="hg-trait-specialization-popup" data-specializations="'.$json.'"><span aria-hidden="true">ⓘ</span></button></span>';
}
function hg_trait_specializations_assets(): void {
    $css='/assets/css/components/trait-specializations.css';
    $cssVersion=(int)(@filemtime(dirname(__DIR__,3).$css)?:1);
    $js='/assets/js/hg-trait-specializations.js';
    $version=(int)(@filemtime(dirname(__DIR__,3).$js)?:1);
    if (function_exists('hg_page_register_stylesheet')) hg_page_register_stylesheet($css);
    else echo '<link rel="stylesheet" href="'.$css.'?v='.$cssVersion.'">';
    echo '<script defer src="'.$js.'?v='.$version.'"></script>';
}
