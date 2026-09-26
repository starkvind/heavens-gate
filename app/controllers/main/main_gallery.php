<?php setMetaFromPage("Galería | Heaven's Gate", "Galería de imágenes de la campaña.", null, 'website'); ?>
<?php
/*******************************************************
 * Galería pública con carpetas y lightbox
 *******************************************************/
require_once __DIR__ . '/../../domains/gallery/catalog.php';

$GALLERY_BASE_WEB = '/img/gallery';
$GALLERY_BASE_FS = hg_gallery_base_fs();
$ALLOWED_EXT = hg_gallery_allowed_extensions();

if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-gallery.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-gallery.css">';
}

$relDir = urldecode(hg_request_query_param($hgRequest, 'dir'));
$relDir = trim($relDir);
if (!hg_gallery_valid_relative_path($relDir)) $relDir = '';
$absDir = hg_gallery_fs_join($GALLERY_BASE_FS, $relDir);

$galleryRequestedView = strtolower(hg_request_query_param($hgRequest, 'view'));
$galleryViewQuery = in_array($galleryRequestedView, ['desktop', 'auto'], true)
    ? ('&view=' . rawurlencode($galleryRequestedView))
    : '';
$galleryRootQuery = $galleryViewQuery !== ''
    ? ('?view=' . rawurlencode($galleryRequestedView))
    : '';

$breadcrumbs = ($relDir === '') ? [] : explode('/', $relDir);
$subdirs     = hg_gallery_list_subdirectories($absDir);
$images      = hg_gallery_list_images($absDir, $ALLOWED_EXT);

?>

<h2>Galería</h2>

<div class="gallery-breadcrumbs">
    <?php if ($relDir != ''): ?><a href="/gallery<?= htmlspecialchars($galleryRootQuery) ?>"><?php endif; ?>
    📁 Inicio
    <?php if ($relDir != ''): ?></a><?php endif; ?>
    <?php
      $acc = [];
      foreach ($breadcrumbs as $i => $seg) {
          $acc[] = $seg;
          $link = '/gallery?dir=' . urlencode(implode('/', $acc)) . $galleryViewQuery;
          echo "/ <a href=\"{$link}\">" . htmlspecialchars($seg) . "</a>";
      }
    ?>
</div>

<div class="gallery-container">

  <?php if ($relDir === ''): ?>
    <div class="gallery-section-title">Carpetas</div>
    <div class="gallery-grid">
      <?php foreach ($subdirs as $dirIndex => $dirName):
        $childRel = $dirName;
        $childAbs = hg_gallery_fs_join($absDir, $dirName);
        $link = '/gallery?dir=' . urlencode($childRel) . $galleryViewQuery;
        $cover = hg_gallery_folder_cover($GALLERY_BASE_WEB, $childAbs, $childRel, $ALLOWED_EXT);
      ?>
      <a class="gallery-folder gallery-card" href="<?= $link ?>" title="Abrir carpeta">
        <?php if ($cover): ?>
          <img class="cover" src="<?= htmlspecialchars($cover) ?>" alt="" width="100" height="120" loading="<?= $dirIndex < 4 ? 'eager' : 'lazy' ?>" decoding="async">
        <?php else: ?>
          <span class="icon">📁</span>
        <?php endif; ?>
        <div class="name"><?= htmlspecialchars($dirName) ?></div>
      </a>
      <?php endforeach; ?>
      <?php if (!$subdirs): ?>
        <div class="gallery-card">No hay carpetas todavía.</div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="gallery-section-title"><?= htmlspecialchars($relDir) ?></div>
    <?php if ($subdirs): ?>
      <div class="gallery-section-title gallery-section-title-sub">Subcarpetas</div>
      <div class="gallery-grid">
        <?php foreach ($subdirs as $dirIndex => $dirName):
          $childRel = $relDir . '/' . $dirName;
          $childAbs = hg_gallery_fs_join($absDir, $dirName);
          $link = '/gallery?dir=' . urlencode($childRel);
          $cover = hg_gallery_folder_cover($GALLERY_BASE_WEB, $childAbs, $childRel, $ALLOWED_EXT);
        ?>
        <a class="gallery-folder gallery-card" href="<?= $link ?>" title="Abrir carpeta">
          <?php if ($cover): ?>
            <img class="cover" src="<?= htmlspecialchars($cover) ?>" alt="" width="100" height="120" loading="<?= $dirIndex < 4 ? 'eager' : 'lazy' ?>" decoding="async">
          <?php else: ?>
            <span class="icon">📁</span>
          <?php endif; ?>
          <div class="name"><?= htmlspecialchars($dirName) ?></div>
        </a>
        <?php endforeach; ?>
      </div>
      <hr class="gallery-divider">
    <?php endif; ?>

    <div class="gallery-img-grid">
      <?php foreach ($images as $idx => $img):
        $title = hg_gallery_title($img);
        $imageUrls = hg_gallery_image_urls($GALLERY_BASE_WEB, $absDir, $relDir, $img);
        $thumbWeb = $imageUrls['thumb'];
        $imgWeb = $imageUrls['full'];
      ?>
      <div class="gallery-img-item">
        <img class="thumb"
             src="<?= htmlspecialchars($thumbWeb) ?>"
             data-full="<?= htmlspecialchars($imgWeb) ?>"
             data-title="<?= htmlspecialchars($title) ?>"
             data-index="<?= $idx ?>"
             alt="<?= htmlspecialchars($title) ?>"
             width="125"
             height="125"
             loading="<?= $idx < 12 ? 'eager' : 'lazy' ?>"
             decoding="async">
        <div class="gallery-img-title"><?= htmlspecialchars($title) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$images): ?>
        <div class="gallery-card">No hay imágenes en esta carpeta.</div>
      <?php endif; ?>
    </div>

    <div id="lightbox">
      <img id="lightbox-img" src="" alt="">
      <div id="lightbox-title"></div>
      <div class="lightbox-controls">
        <span id="prev">&#9664;</span>
        <span id="next">&#9654;</span>
        <span id="close">&times;</span>
      </div>
      <pre class="embedForumSnippet"><code id="embedCode"></code></pre>
    </div>
  <?php endif; ?>

  <p class="gallery-ai-note"><i>La totalidad de estas imágenes se han realizado con inteligencia artificial generativa.
  <br />
  Su licencia es CC0 1.0 Universal.
  </i></p>

</div>

<?php if ($images): ?>
<script>
    const thumbs = Array.from(document.querySelectorAll('.gallery-img-item .thumb'));
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightbox-img');
    const lightboxTitle = document.getElementById('lightbox-title');
    const embedCode = document.getElementById('embedCode');
    let currentIndex = 0;
    const baseUrl = window.location.origin;

    function showImage(index) {
      if(index < 0) index = thumbs.length - 1;
      if(index >= thumbs.length) index = 0;
      currentIndex = index;
      const img = thumbs[index];
      const src = img.dataset.full;
      const title = img.dataset.title;
      lightboxImg.src = src;
      lightboxTitle.textContent = title;
      preloadAround(index);
      const fullForEmbed = /^https?:\/\//i.test(src) ? src : `${baseUrl}${src}`;
      embedCode.textContent = `[img width=700]${fullForEmbed}[/img]`;
      lightbox.style.display = 'flex';
    }

    thumbs.forEach((img, idx) => img.addEventListener('click', () => showImage(idx)));
    document.getElementById('prev').addEventListener('click', () => showImage(currentIndex - 1));
    document.getElementById('next').addEventListener('click', () => showImage(currentIndex + 1));
    document.getElementById('close').addEventListener('click', () => lightbox.style.display = 'none');
    document.addEventListener('keydown', (e) => {
      if (lightbox.style.display === 'flex') {
        if (e.key === 'ArrowLeft') showImage(currentIndex - 1);
        if (e.key === 'ArrowRight') showImage(currentIndex + 1);
        if (e.key === 'Escape') lightbox.style.display = 'none';
      }
    });

    const preloadedFullImages = new Set();

    function preloadFull(index) {
      if (thumbs.length < 2) return;
      const normalized = (index + thumbs.length) % thumbs.length;
      const src = thumbs[normalized]?.dataset.full;
      if (!src || preloadedFullImages.has(src)) return;
      const image = new Image();
      image.decoding = 'async';
      image.src = src;
      preloadedFullImages.add(src);
    }

    function preloadAround(index) {
      preloadFull(index - 1);
      preloadFull(index + 1);
    }
</script>
<?php endif; ?>
