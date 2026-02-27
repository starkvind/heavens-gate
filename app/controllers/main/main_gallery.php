<?php setMetaFromPage("Galería | Heaven's Gate", "Galería de imágenes de la campaña.", null, 'website'); ?>
<?php
/*******************************************************
 * Galería pública con carpetas y lightbox
 *******************************************************/
$GALLERY_BASE_WEB = "/img/gallery";
$GALLERY_BASE_FS  = rtrim($_SERVER['DOCUMENT_ROOT'], "/") . "/public/img/gallery";
$ALLOWED_EXT = ['jpg','jpeg','png','gif','webp'];

// Helpers
function isValidRelPath(string $rel): bool {
    return $rel === '' || (bool)preg_match('#^(?!/)(?!.*\.\.)([A-Za-z0-9 _\.\-]+/)*[A-Za-z0-9 _\.\-]+$#', $rel);
}
function fsPathJoin(string $base, string $rel = ''): string {
    $rel = trim($rel, "/");
    return $rel === '' ? $base : ($base . "/" . $rel);
}
function webPathJoin(string $base, string $rel = ''): string {
    $rel = trim($rel, "/");
    if ($rel === '') return $base;
    $parts = explode('/', $rel);
    $encoded = array_map('rawurlencode', $parts);
    return $base . "/" . implode('/', $encoded);
}

function listSubdirs(string $abs): array {
    $dirs = [];
    if (!is_dir($abs)) return $dirs;
    foreach (array_diff(scandir($abs), ['.','..']) as $it) {
        $p = $abs . "/" . $it;
        if (is_dir($p) && strtolower($it) !== 'thumbnails') $dirs[] = $it;
    }
    sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
    return $dirs;
}
function listImages(string $abs, array $allowed): array {
    $imgs = [];
    if (!is_dir($abs)) return $imgs;
    foreach (array_diff(scandir($abs), ['.','..','thumbnails']) as $it) {
        $p = $abs . "/" . $it;
        if (is_file($p)) {
            $ext = strtolower(pathinfo($it, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed, true)) $imgs[] = $it;
        }
    }
    sort($imgs, SORT_NATURAL | SORT_FLAG_CASE);
    return $imgs;
}
function formatTitle(string $filename): string {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = str_replace(['-','_'], ' ', $name);
    return ucfirst($name);
}

// Contexto de navegación
$relDir = isset($_GET['dir']) ? urldecode((string)$_GET['dir']) : '';
$relDir = trim($relDir);
if (!isValidRelPath($relDir)) $relDir = '';
$absDir = fsPathJoin($GALLERY_BASE_FS, $relDir);

// Datos
$breadcrumbs = ($relDir === '') ? [] : explode('/', $relDir);
$subdirs     = listSubdirs($absDir);
$images      = listImages($absDir, $ALLOWED_EXT);

// Para portada de carpeta: primera miniatura si existe
function firstThumbWeb(string $baseWeb, string $absDir, string $relDir, array $allowed): ?string {
    $imgs = listImages($absDir, $allowed);
    if (!$imgs) return null;
    $first = $imgs[0];
    $thumbRel = ($relDir === '' ? '' : $relDir . '/') . 'thumbnails/' . $first;
    return webPathJoin($baseWeb, $thumbRel);
}
?>

<link rel="stylesheet" href="/assets/css/hg-main.css">

<h2>Galería</h2>

  <div class="gallery-breadcrumbs">
    <?php if ($relDir != ''): ?><a href="/gallery"><?php endif; ?>
	📁 Inicio
	<?php if ($relDir != ''): ?></a><?php endif; ?>
    <?php
      $acc = [];
      foreach ($breadcrumbs as $i => $seg) {
          $acc[] = $seg;
          $link = '/gallery?dir=' . urlencode(implode('/', $acc));
          echo "/ <a href=\"{$link}\">" . htmlspecialchars($seg) . "</a>";
      }
    ?>
  </div>

<div class="gallery-container">

  <?php if ($relDir === ''): ?>
    <div class="gallery-section-title">Carpetas</div>
    <div class="gallery-grid">
      <?php foreach ($subdirs as $dirName):
        $childRel = $dirName;
        $childAbs = fsPathJoin($absDir, $dirName);
        $link = '/gallery?dir=' . urlencode($childRel);
        $cover = firstThumbWeb($GALLERY_BASE_WEB, $childAbs, $childRel, $ALLOWED_EXT);
      ?>
      <a class="gallery-folder gallery-card" href="<?= $link ?>" title="Abrir carpeta">
        <?php if ($cover): ?>
          <img class="cover" src="<?= htmlspecialchars($cover) ?>" alt="">
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
        <?php foreach ($subdirs as $dirName):
          $childRel = $relDir . '/' . $dirName;
          $childAbs = fsPathJoin($absDir, $dirName);
          $link = '/gallery?dir=' . urlencode($childRel);
          $cover = firstThumbWeb($GALLERY_BASE_WEB, $childAbs, $childRel, $ALLOWED_EXT);
        ?>
        <a class="gallery-folder gallery-card" href="<?= $link ?>" title="Abrir carpeta">
          <?php if ($cover): ?>
            <img class="cover" src="<?= htmlspecialchars($cover) ?>" alt="">
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
        $title = formatTitle($img);
        $thumbWeb = webPathJoin($GALLERY_BASE_WEB, $relDir . "/thumbnails/" . $img);
        $imgWeb   = webPathJoin($GALLERY_BASE_WEB, $relDir . "/" . $img);
      ?>
      <div class="gallery-img-item">
        <img class="thumb"
             src="<?= htmlspecialchars($thumbWeb) ?>"
             data-full="<?= htmlspecialchars($imgWeb) ?>"
             data-title="<?= htmlspecialchars($title) ?>"
             data-index="<?= $idx ?>"
             alt="<?= htmlspecialchars($title) ?>">
        <div class="gallery-img-title"><?= htmlspecialchars($title) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$images): ?>
        <div class="gallery-card">No hay imágenes en esta carpeta.</div>
      <?php endif; ?>
    </div>


    <div id="lightbox">
      <img id="lightbox-img" src="">
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

		// URL base absoluta para snippets (fallback robusto)
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
		  // BBCode con URL absoluta
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

		// Precarga
		const preload = [];
		thumbs.forEach(img => { const i = new Image(); i.src = img.dataset.full; preload.push(i); });
	</script>
<?php endif; ?>

