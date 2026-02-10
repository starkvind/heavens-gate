<?php setMetaFromPage("Galeria | Heaven's Gate", "Galeria de imagenes de la campana.", null, 'website'); ?>
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

<style>
	.galleryHeader { padding:16px 20px; background:#121826; border-bottom:1px solid #1f2a44; display:flex; align-items:center; gap:12px; }
	header h1 { margin:0; font-size:18px; }
	.container { padding:18px 20px; }
	.breadcrumbs { width: 100%; display: block; margin-left: 2em; }
	.breadcrumbs a, .breadcrumbs span { color:#a7c0ff; text-decoration:none; margin-right:6px; }
	.section-title { margin:10px 0 8px; font-size:16px; margin-bottom: 2em; display: none; }
	.grid { display:flex; flex-wrap:wrap; gap:12px; }
	.card { background:#000044; border:1px solid #000088; border-radius:12px; padding:12px; }
	.folder { width:25%; text-align:center; cursor:pointer; }
	.folder .cover { width:100px; height:120px; object-fit:cover; border:2px solid #2a3a62; border-radius:10px; background:#000; display:block; margin:0 auto 8px; }
	.folder .icon { font-size:32px; display:block; margin-bottom:8px; }
	.folder .name { font-weight:600; }
	.img-grid { display:flex; flex-wrap:wrap; gap:10px; }
	.img-item { width:125px; text-align:center; }
	.img-item .thumb { width:125px; height:125px; object-fit:cover; border:2px solid #2a3a62; border-radius:10px; background:#000; cursor:pointer; }
	.img-title { margin-top:6px; font-size:13px; color:#c9d6ff; }

	#lightbox {
	  position:fixed; inset:0; background:rgba(0,0,0,0.9);
	  display:none; justify-content:center; align-items:center; flex-direction:column; z-index:9999;
	}
	#lightbox img { max-width:90%; max-height:75%; margin-bottom:10px; }
	#lightbox-title { color:#ccc; font-size:1.05em; margin-bottom:15px; }
	.lightbox-controls { display:flex; gap:20px; font-size:2em; }
	.lightbox-controls span { cursor:pointer; user-select:none; color:#fff; }
	.download-link { margin-top:10px; color:#66ccff; font-size:0.9em; }
	.embedForumSnippet { background:#111; border:1px solid #444; color:#0f0; font-family:monospace; padding:0.5em; border-radius:6px; overflow:auto; }
</style>

<h2>Galería</h2>

  <div class="breadcrumbs">
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

<div class="container">

  <?php if ($relDir === ''): ?>
    <div class="section-title">Carpetas</div>
    <div class="grid">
      <?php foreach ($subdirs as $dirName):
        $childRel = $dirName;
        $childAbs = fsPathJoin($absDir, $dirName);
        $link = '/gallery?dir=' . urlencode($childRel);
        $cover = firstThumbWeb($GALLERY_BASE_WEB, $childAbs, $childRel, $ALLOWED_EXT);
      ?>
      <a class="folder card" href="<?= $link ?>" title="Abrir carpeta">
        <?php if ($cover): ?>
          <img class="cover" src="<?= htmlspecialchars($cover) ?>" alt="">
        <?php else: ?>
          <span class="icon">📁</span>
        <?php endif; ?>
        <div class="name"><?= htmlspecialchars($dirName) ?></div>
      </a>
      <?php endforeach; ?>
      <?php if (!$subdirs): ?>
        <div class="card">No hay carpetas todavía.</div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="section-title"><?= htmlspecialchars($relDir) ?></div>
    <?php if ($subdirs): ?>
      <div class="section-title" style="margin-top:16px;">Subcarpetas</div>
      <div class="grid">
        <?php foreach ($subdirs as $dirName):
          $childRel = $relDir . '/' . $dirName;
          $childAbs = fsPathJoin($absDir, $dirName);
          $link = '/gallery?dir=' . urlencode($childRel);
          $cover = firstThumbWeb($GALLERY_BASE_WEB, $childAbs, $childRel, $ALLOWED_EXT);
        ?>
        <a class="folder card" href="<?= $link ?>" title="Abrir carpeta">
          <?php if ($cover): ?>
            <img class="cover" src="<?= htmlspecialchars($cover) ?>" alt="">
          <?php else: ?>
            <span class="icon">📁</span>
          <?php endif; ?>
          <div class="name"><?= htmlspecialchars($dirName) ?></div>
        </a>
        <?php endforeach; ?>
      </div>
      <hr style="border:0;border-top:1px solid #1f2a44;margin:16px 0;">
    <?php endif; ?>

    <div class="img-grid">
      <?php foreach ($images as $idx => $img):
        $title = formatTitle($img);
        $thumbWeb = webPathJoin($GALLERY_BASE_WEB, $relDir . "/thumbnails/" . $img);
        $imgWeb   = webPathJoin($GALLERY_BASE_WEB, $relDir . "/" . $img);
      ?>
      <div class="img-item">
        <img class="thumb"
             src="<?= htmlspecialchars($thumbWeb) ?>"
             data-full="<?= htmlspecialchars($imgWeb) ?>"
             data-title="<?= htmlspecialchars($title) ?>"
             data-index="<?= $idx ?>"
             alt="<?= htmlspecialchars($title) ?>">
        <div class="img-title"><?= htmlspecialchars($title) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$images): ?>
        <div class="card">No hay imágenes en esta carpeta.</div>
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
  
  	<p style="margin-top:3em;margin-bottom:0em;"><i>La totalidad de estas imágenes se han realizado con inteligencia artificial generativa.
	<br />
	Su licencia es CC0 1.0 Universal.
	</i></p>

</div>

<?php if ($images): ?>
	<script>
		const thumbs = Array.from(document.querySelectorAll('.img-item .thumb'));
		const lightbox = document.getElementById('lightbox');
		const lightboxImg = document.getElementById('lightbox-img');
		const lightboxTitle = document.getElementById('lightbox-title');
		const embedCode = document.getElementById('embedCode');
		let currentIndex = 0;

		// Variable con la URL base definida en PHP
		const baseUrl = "<?= htmlspecialchars($baseURL) ?>";

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
		  embedCode.textContent = `[img width=700]${baseUrl}${src}[/img]`;
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

