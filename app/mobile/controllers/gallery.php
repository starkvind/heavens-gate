<?php

include_once(__DIR__ . '/../../helpers/public_response.php');

$metaTitle = "Galería | Heaven's Gate";
$metaDescription = 'Galería móvil de imagenes de la campaña.';
$pageSect = 'Galería';

require_once __DIR__ . '/../../domains/gallery/catalog.php';

$galleryBaseWeb = '/img/gallery';
$galleryBaseFs = hg_gallery_base_fs();
$allowedExt = hg_gallery_allowed_extensions();

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
?>

<section class="hg-mobile-section hg-mobile-gallery-head">
    <h1>Galería</h1>
    <p class="hg-mobile-muted"><?= hg_mobile_gallery_h($relDir === '' ? 'Carpetas principales' : $relDir) ?></p>
</section>

<nav class="hg-mobile-gallery-crumbs" aria-label="Ruta de galeria">
    <?php if ($relDir === ''): ?>
        <span>Inicio</span>
    <?php else: ?>
        <a href="/gallery">Inicio</a>
        <?php $acc = []; ?>
        <?php foreach ($breadcrumbs as $seg): ?>
            <?php $acc[] = $seg; ?>
            <span>/</span>
            <a href="/gallery?dir=<?= hg_mobile_gallery_h(rawurlencode(implode('/', $acc))) ?>"><?= hg_mobile_gallery_h($seg) ?></a>
        <?php endforeach; ?>
    <?php endif; ?>
</nav>

<?php if (!empty($subdirs)): ?>
<section class="hg-mobile-section">
    <h2><?= $relDir === '' ? 'Carpetas' : 'Subcarpetas' ?></h2>
    <div class="hg-mobile-gallery-folder-grid" data-mobile-paginated data-mobile-search="1" data-page-size="12" data-search-placeholder="Buscar carpeta" data-empty-text="No hay carpetas con ese filtro.">
        <?php foreach ($subdirs as $dirName): ?>
            <?php
                $childRel = trim($relDir . '/' . $dirName, '/');
                $childAbs = hg_gallery_fs_join($realAbsDir, $dirName);
                $cover = hg_gallery_folder_cover($galleryBaseWeb, $childAbs, $childRel, $allowedExt);
            ?>
            <a class="hg-mobile-gallery-folder" href="/gallery?dir=<?= hg_mobile_gallery_h(rawurlencode($childRel)) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_gallery_h($dirName) ?>">
                <?php if ($cover !== ''): ?>
                    <img src="<?= hg_mobile_gallery_h($cover) ?>" alt="">
                <?php else: ?>
                    <span aria-hidden="true"></span>
                <?php endif; ?>
                <strong><?= hg_mobile_gallery_h($dirName) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($relDir !== ''): ?>
<section class="hg-mobile-section">
    <h2>Imagenes</h2>
    <p class="hg-mobile-muted"><?= number_format(count($images), 0, ',', '.') ?> imagenes en <?= hg_mobile_gallery_h($folderLabel) ?></p>

    <?php if (empty($images)): ?>
        <p class="hg-mobile-muted">No hay imagenes en esta carpeta.</p>
    <?php else: ?>
        <div class="hg-mobile-gallery-grid" data-mobile-gallery-grid data-mobile-paginated data-mobile-search="1" data-page-size="24" data-search-placeholder="Buscar imagen" data-empty-text="No hay imagenes con ese filtro.">
            <?php foreach ($images as $idx => $img): ?>
                <?php
                    $title = hg_gallery_title($img);
                    $imageUrls = hg_gallery_image_urls($galleryBaseWeb, $realAbsDir, $relDir, $img);
                    $thumbWeb = $imageUrls['thumb'];
                    $fullWeb = $imageUrls['full'];
                ?>
                <button class="hg-mobile-gallery-image" type="button" data-mobile-item data-mobile-search="<?= hg_mobile_gallery_h($title) ?>" data-mobile-gallery-thumb data-full="<?= hg_mobile_gallery_h($fullWeb) ?>" data-title="<?= hg_mobile_gallery_h($title) ?>" data-index="<?= (int)$idx ?>">
                    <img src="<?= hg_mobile_gallery_h($thumbWeb) ?>" alt="<?= hg_mobile_gallery_h($title) ?>" loading="lazy">
                    <span><?= hg_mobile_gallery_h($title) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php elseif (empty($subdirs)): ?>
<section class="hg-mobile-section">
    <p class="hg-mobile-muted">No hay carpetas todavia.</p>
</section>
<?php endif; ?>

<section class="hg-mobile-section hg-mobile-gallery-note">
    <p>La totalidad de estas imagenes se han realizado con inteligencia artificial generativa. Su licencia es CC0 1.0 Universal.</p>
</section>

<div class="hg-mobile-gallery-lightbox" data-mobile-gallery-lightbox hidden>
    <div class="hg-mobile-gallery-lightbox-bar">
        <button type="button" data-mobile-gallery-close aria-label="Cerrar">Cerrar</button>
    </div>
    <img src="" alt="" data-mobile-gallery-full>
    <strong data-mobile-gallery-title></strong>
    <div class="hg-mobile-gallery-lightbox-nav">
        <button type="button" data-mobile-gallery-prev>Anterior</button>
        <button type="button" data-mobile-gallery-next>Siguiente</button>
    </div>
    <label>
        BBCode
        <textarea readonly rows="3" data-mobile-gallery-bbcode></textarea>
    </label>
    <button type="button" data-mobile-gallery-copy>Copiar BBCode</button>
</div>

<script>
(function () {
    var thumbs = Array.prototype.slice.call(document.querySelectorAll('[data-mobile-gallery-thumb]'));
    var lightbox = document.querySelector('[data-mobile-gallery-lightbox]');
    if (!thumbs.length || !lightbox) return;

    var img = lightbox.querySelector('[data-mobile-gallery-full]');
    var title = lightbox.querySelector('[data-mobile-gallery-title]');
    var bbcode = lightbox.querySelector('[data-mobile-gallery-bbcode]');
    var current = 0;

    function absoluteUrl(src) {
        try {
            return new URL(src, window.location.origin).toString();
        } catch (e) {
            return src;
        }
    }

    function show(index) {
        if (index < 0) index = thumbs.length - 1;
        if (index >= thumbs.length) index = 0;
        current = index;
        var node = thumbs[current];
        var src = node.getAttribute('data-full') || '';
        var label = node.getAttribute('data-title') || '';
        img.src = src;
        img.alt = label;
        title.textContent = label;
        bbcode.value = '[img width=700]' + absoluteUrl(src) + '[/img]';
        lightbox.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function close() {
        lightbox.hidden = true;
        img.src = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (event) {
        var thumb = event.target.closest('[data-mobile-gallery-thumb]');
        if (thumb) {
            var index = thumbs.indexOf(thumb);
            if (index >= 0) show(index);
            return;
        }
        if (event.target.closest('[data-mobile-gallery-close]')) close();
        if (event.target.closest('[data-mobile-gallery-prev]')) show(current - 1);
        if (event.target.closest('[data-mobile-gallery-next]')) show(current + 1);
        if (event.target.closest('[data-mobile-gallery-copy]') && bbcode) {
            bbcode.select();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(bbcode.value).catch(function () {});
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (lightbox.hidden) return;
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowLeft') show(current - 1);
        if (event.key === 'ArrowRight') show(current + 1);
    });
})();
</script>