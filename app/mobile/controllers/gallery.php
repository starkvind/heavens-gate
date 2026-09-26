<?php

include_once(__DIR__ . '/../../helpers/public_response.php');

$metaTitle = "Galería | Heaven's Gate";
$metaDescription = 'Galería móvil de imagenes de la campaña.';
$pageSect = 'Galería';

require_once __DIR__ . '/../../domains/gallery/catalog.php';

$galleryBaseWeb = '/img/gallery';
$galleryBaseFs = hg_gallery_base_fs();
$allowedExt = hg_gallery_allowed_extensions();

if (!function_exists('hg_mobile_gallery_h')) {
    function hg_mobile_gallery_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!is_string($galleryBaseFs) || $galleryBaseFs === '' || !is_dir($galleryBaseFs)) {
    hg_public_log_error('mobile_gallery', 'missing gallery directory');
    hg_public_render_error('Galería no disponible', 'No se pudo localizar el directorio de imagenes.');
    return;
}

$relDir = trim(rawurldecode(hg_request_query_param($hgRequest, 'dir')));
$relDir = trim($relDir, '/');
if (!hg_gallery_valid_relative_path($relDir)) {
    $relDir = '';
}

$realAbsDir = hg_gallery_resolve_directory($galleryBaseFs, $relDir);
if ($realAbsDir === null) {
    hg_public_render_not_found('Carpeta no encontrada', 'No se encontro la carpeta solicitada.');
    return;
}

$breadcrumbs = $relDir === '' ? [] : explode('/', $relDir);
$subdirs = hg_gallery_list_subdirectories($realAbsDir);
$images = hg_gallery_list_images($realAbsDir, $allowedExt);
$folderLabel = $relDir === '' ? 'Inicio' : basename($relDir);

include __DIR__ . '/../views/gallery.php';
