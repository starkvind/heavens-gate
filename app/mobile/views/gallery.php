<?php
?>

<section class="hg-mobile-section hg-mobile-gallery-head">
    <h1>Galería</h1>
    <p class="hg-mobile-muted"><?= hg_mobile_gallery_h($relDir === '' ? 'Carpetas principales' : $relDir) ?></p>
</section>

<nav class="hg-mobile-gallery-crumbs" aria-label="Ruta de galeria">
    <?php if ($relDir === ''): ?>
        <span>Inicio</span>
    <?php else: ?>
        <a href="/gallery?view=mobile">Inicio</a>
        <?php $acc = []; ?>
        <?php foreach ($breadcrumbs as $seg): ?>
            <?php $acc[] = $seg; ?>
            <span>/</span>
            <a href="/gallery?dir=<?= hg_mobile_gallery_h(rawurlencode(implode('/', $acc))) ?>&amp;view=mobile"><?= hg_mobile_gallery_h($seg) ?></a>
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
            <a class="hg-mobile-gallery-folder" href="/gallery?dir=<?= hg_mobile_gallery_h(rawurlencode($childRel)) ?>&amp;view=mobile" data-mobile-item data-mobile-search="<?= hg_mobile_gallery_h($dirName) ?>">
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

