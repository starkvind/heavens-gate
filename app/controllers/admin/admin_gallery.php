<?php
/********************************************************
 * Admin Galería - gestión de carpetas e imágenes
 * Requisitos: extensión GD habilitada
 * Estructura:
 *   /img/gallery/
 *       /CarpetaA/
 *           /thumbnails/
 *           imagen1.jpg
 *           ...
 ********************************************************/

// ==============================
// Configuración base
// ==============================
$GALLERY_BASE_WEB = "/public/img/gallery"; // ruta web
$GALLERY_BASE_FS  = rtrim($_SERVER['DOCUMENT_ROOT'], "/") . $GALLERY_BASE_WEB; // ruta filesystem

$ALLOWED_EXT = ['jpg','jpeg','png','gif','webp'];

// ==============================
// Utilidades de seguridad y FS
// ==============================
function isValidRelPath(string $rel): bool {
    // Sin barras iniciales, sin '..', solo caracteres seguros y separadores '/'
    return (bool)preg_match('#^(?!/)(?!.*\.\.)([A-Za-z0-9 _\.\-]+/)*[A-Za-z0-9 _\.\-]+$#', $rel);
}
function isValidDirName(string $name): bool {
    return (bool)preg_match('#^[A-Za-z0-9 _\.\-]+$#', $name) && !in_array(strtolower($name), ['','thumbnails','.','..']);
}
function isValidFileName(string $file, array $allowed): bool {
    if (!preg_match('#^[A-Za-z0-9 _\.\-]+\.[A-Za-z0-9]+$#', $file)) return false;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($ext, $allowed, true);
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

function ensureDir(string $path): bool {
    return is_dir($path) ?: mkdir($path, 0775, true);
}
function rrmdir(string $dir): bool {
    if (!is_dir($dir)) return false;
    $items = array_diff(scandir($dir), ['.','..']);
    foreach ($items as $it) {
        $p = $dir . "/" . $it;
        if (is_dir($p)) rrmdir($p);
        else @unlink($p);
    }
    return @rmdir($dir);
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
        if (is_file($p) && isValidFileName($it, $allowed)) $imgs[] = $it;
    }
    sort($imgs, SORT_NATURAL | SORT_FLAG_CASE);
    return $imgs;
}
function getAllDirsRecursive(string $baseAbs, string $rel = ''): array {
    $curAbs = fsPathJoin($baseAbs, $rel);
    $dirs = [];
    if ($rel !== '') $dirs[] = $rel;
    foreach (listSubdirs($curAbs) as $sub) {
        $childRel = $rel === '' ? $sub : ($rel . "/" . $sub);
        $dirs = array_merge($dirs, getAllDirsRecursive($baseAbs, $childRel));
    }
    return $dirs;
}
function uniqueFileName(string $dirAbs, string $file): string {
    $name = pathinfo($file, PATHINFO_FILENAME);
    $ext  = pathinfo($file, PATHINFO_EXTENSION);
    $candidate = $file;
    $i = 1;
    while (file_exists($dirAbs . "/" . $candidate)) {
        $candidate = sprintf("%s-%d.%s", $name, $i, $ext);
        $i++;
    }
    return $candidate;
}
function formatTitle(string $filename): string {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = str_replace(['-','_'], ' ', $name);
    return ucfirst($name);
}

// ==============================
// Imagen: compresión + thumb
// ==============================
function compressImage(string $srcTmp, string $dest, int $quality = 80): bool {
    [$w, $h, $type] = @getimagesize($srcTmp);
    if (!$w || !$h) return false;
    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($srcTmp); if(!$img) return false; $ok = imagejpeg($img, $dest, $quality); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($srcTmp);  if(!$img) return false; imagesavealpha($img, true); $ok = imagepng($img, $dest, 8); break; // 0-9
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($srcTmp);  if(!$img) return false; $ok = imagegif($img, $dest); break;
        case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($srcTmp); if(!$img) return false; $ok = imagewebp($img, $dest, $quality); break;
        default: return false;
    }
    if (isset($img) && is_resource($img)) imagedestroy($img);
    return (bool)$ok;
}
function createThumbnail(string $src, string $dest, int $maxW = 200, int $maxH = 200): bool {
    [$w, $h, $type] = @getimagesize($src);
    if (!$w || !$h) return false;
    $ratio = min($maxW / $w, $maxH / $h);
    $nw = max(1, (int)round($w * $ratio));
    $nh = max(1, (int)round($h * $ratio));
    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src);  imagesavealpha($img, true); break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($src);  break;
        case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($src); break;
        default: return false;
    }
    if(!$img) return false;
    $thumb = imagecreatetruecolor($nw, $nh);
    // Transparencia básica para PNG/GIF
    if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
        imagefilledrectangle($thumb, 0, 0, $nw, $nh, $transparent);
    }
    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $ok = imagejpeg($thumb, $dest, 80); break;
        case IMAGETYPE_PNG:  $ok = imagepng($thumb, $dest, 8); break;
        case IMAGETYPE_GIF:  $ok = imagegif($thumb, $dest); break;
        case IMAGETYPE_WEBP: $ok = imagewebp($thumb, $dest, 80); break;
    }
    imagedestroy($img);
    imagedestroy($thumb);
    return $ok;
}

// ==============================
// Estado y acciones
// ==============================
$messages = [];

// Directorio actual (relativo)
$relDir = isset($_GET['dir']) ? urldecode((string)$_GET['dir']) : '';
$relDir = trim($relDir);
if ($relDir !== '' && !isValidRelPath($relDir)) {
    $messages[] = "⚠️ Ruta no válida. Volviendo a la raíz.";
    $relDir = '';
}
$absDir = fsPathJoin($GALLERY_BASE_FS, $relDir);
ensureDir($absDir);
ensureDir(fsPathJoin($absDir, "thumbnails"));

// ==============================
// Procesar POST (acciones normales + AJAX)
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Normalizar inputs
    $postRel = isset($_POST['relDir']) ? trim(urldecode((string)$_POST['relDir'])) : $relDir;
    if ($postRel !== '' && !isValidRelPath($postRel)) $postRel = '';
    $postAbs = fsPathJoin($GALLERY_BASE_FS, $postRel);

    // --- Endpoint AJAX: subir UNO con JSON limpio ---
    if ($action === 'upload_one') {
        $resp = ["ok" => false, "msg" => ""];

        if (!isset($_FILES['imagen'])) {
            $resp["msg"] = "❌ No se recibió archivo.";
        } else {
            $file = $_FILES['imagen'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $origName = basename($file['name']);
                if (!isValidFileName($origName, $ALLOWED_EXT)) {
                    $resp["msg"] = "❌ Extensión no permitida ($origName).";
                } else {
                    $tmpName  = $file['tmp_name'];
                    $safeName = uniqueFileName($postAbs, $origName);
                    $destAbs  = fsPathJoin($postAbs, $safeName);
                    if (compressImage($tmpName, $destAbs, 80)) {
                        $thumbAbs = fsPathJoin($postAbs, "thumbnails/" . $safeName);
                        ensureDir(dirname($thumbAbs));
                        createThumbnail($destAbs, $thumbAbs, 200, 200);

                        $resp["ok"]    = true;
                        $resp["file"]  = $safeName;
                        $resp["title"] = formatTitle($safeName);
                        $resp["thumb"] = webPathJoin($GALLERY_BASE_WEB, ($postRel === '' ? '' : $postRel . '/') . 'thumbnails/' . $safeName);
                        $resp["url"]   = webPathJoin($GALLERY_BASE_WEB, ($postRel === '' ? '' : $postRel . '/') . $safeName);
                        $resp["msg"]   = "✅ Subida: $safeName";
                    } else {
                        $resp["msg"] = "❌ Error al procesar $origName.";
                    }
                }
            } else {
                $resp["msg"] = "❌ Error en la subida.";
            }
        }

        // ⚠️ Limpia cualquier salida previa para no romper el JSON:
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo json_encode($resp);
        exit; // ¡Importantísimo!
    }
    // --- FIN endpoint AJAX ---

    switch ($action) {
        case 'create_dir':
            $newName = trim((string)($_POST['new_dir_name'] ?? ''));
            if (!isValidDirName($newName)) { $messages[] = "❌ Nombre de carpeta no válido."; break; }
            $targetAbs = fsPathJoin($postAbs, $newName);
            if (is_dir($targetAbs)) { $messages[] = "⚠️ La carpeta ya existe."; break; }
            if (ensureDir($targetAbs) && ensureDir($targetAbs . "/thumbnails")) {
                $messages[] = "✅ Carpeta creada: " . htmlspecialchars($newName);
            } else {
                $messages[] = "❌ No se pudo crear la carpeta.";
            }
            break;

        case 'rename_dir':
            $old = trim((string)($_POST['old_dir'] ?? ''));
            $new = trim((string)($_POST['new_dir'] ?? ''));
            if (!isValidDirName($old) || !isValidDirName($new)) { $messages[] = "❌ Nombres inválidos."; break; }
            $oldAbs = fsPathJoin($postAbs, $old);
            $newAbs = fsPathJoin($postAbs, $new);
            if (!is_dir($oldAbs)) { $messages[] = "❌ La carpeta origen no existe."; break; }
            if (is_dir($newAbs)) { $messages[] = "⚠️ Ya existe una carpeta con ese nombre."; break; }
            if (@rename($oldAbs, $newAbs)) {
                $messages[] = "✅ Carpeta renombrada.";
                if ($relDir === ($postRel === '' ? $old : $postRel . "/" . $old)) {
                    $relDir = ($postRel === '' ? $new : $postRel . "/" . $new);
                }
            } else {
                $messages[] = "❌ No se pudo renombrar.";
            }
            break;

        case 'delete_dir':
            $del = trim((string)($_POST['del_dir'] ?? ''));
            if (!isValidRelPath($del)) { $messages[] = "❌ Carpeta inválida."; break; }
            if ($del === '') { $messages[] = "❌ No se puede eliminar la raíz."; break; }
            $delAbs = fsPathJoin($GALLERY_BASE_FS, $del);
            if (!is_dir($delAbs)) { $messages[] = "❌ Carpeta no encontrada."; break; }
            if (rrmdir($delAbs)) {
                $messages[] = "🗑️ Carpeta eliminada.";
                if (strpos($relDir, $del) === 0) $relDir = '';
            } else {
                $messages[] = "❌ No se pudo eliminar la carpeta.";
            }
            break;

        case 'upload_images': // fallback no-AJAX
            if (!isset($_FILES['images'])) { $messages[] = "❌ No se recibieron archivos."; break; }
            $count = count($_FILES['images']['name']);
            $okN = 0;
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $origName = basename($_FILES['images']['name'][$i]);
                if (!isValidFileName($origName, $ALLOWED_EXT)) { continue; }
                $tmpName  = $_FILES['images']['tmp_name'][$i];
                $safeName = uniqueFileName($postAbs, $origName);
                $destAbs  = fsPathJoin($postAbs, $safeName);
                if (compressImage($tmpName, $destAbs, 80)) {
                    $thumbAbs = fsPathJoin($postAbs, "thumbnails/" . $safeName);
                    ensureDir(dirname($thumbAbs));
                    createThumbnail($destAbs, $thumbAbs, 200, 200);
                    $okN++;
                }
            }
            $messages[] = $okN ? "✅ $okN imagen(es) subida(s)." : "⚠️ No se subió ninguna imagen válida.";
            break;

        case 'delete_image':
            $file = basename((string)($_POST['file'] ?? ''));
            if (!isValidFileName($file, $ALLOWED_EXT)) { $messages[] = "❌ Archivo inválido."; break; }
            $imgAbs   = fsPathJoin($postAbs, $file);
            $thumbAbs = fsPathJoin($postAbs, "thumbnails/" . $file);
            $ok = true;
            if (is_file($imgAbs))   $ok = $ok && @unlink($imgAbs);
            if (is_file($thumbAbs)) $ok = $ok && @unlink($thumbAbs);
            $messages[] = $ok ? "🗑️ Imagen eliminada." : "❌ No se pudo eliminar.";
            break;

        case 'move_image':
            $file = basename((string)($_POST['file'] ?? ''));
            $toRel = trim((string)($_POST['to_dir'] ?? ''));
            if (!isValidFileName($file, $ALLOWED_EXT)) { $messages[] = "❌ Archivo inválido."; break; }
            if ($toRel === '' || !isValidRelPath($toRel)) { $messages[] = "❌ Carpeta destino inválida."; break; }
            $fromAbs = $postAbs;
            $toAbs   = fsPathJoin($GALLERY_BASE_FS, $toRel);
            ensureDir($toAbs);
            ensureDir($toAbs . "/thumbnails");

            $srcImg  = fsPathJoin($fromAbs, $file);
            $srcTh   = fsPathJoin($fromAbs, "thumbnails/" . $file);
            if (!is_file($srcImg)) { $messages[] = "❌ Imagen origen no encontrada."; break; }

            $destName = uniqueFileName($toAbs, $file);
            $dstImg   = fsPathJoin($toAbs, $destName);
            $dstTh    = fsPathJoin($toAbs, "thumbnails/" . $destName);

            $okMove = @rename($srcImg, $dstImg);
            if ($okMove) {
                if (is_file($srcTh)) {
                    @rename($srcTh, $dstTh);
                } else {
                    createThumbnail($dstImg, $dstTh, 200, 200);
                }
                $messages[] = "✅ Imagen movida.";
            } else {
                $messages[] = "❌ No se pudo mover la imagen.";
            }
            break;
    }

    // Actualizar contexto
    $relDir = $postRel;
    $absDir = fsPathJoin($GALLERY_BASE_FS, $relDir);
}

// Datos para pintar UI
$breadcrumbs = ($relDir === '') ? [] : explode('/', $relDir);
$subdirs     = listSubdirs($absDir);
$images      = listImages($absDir, $ALLOWED_EXT);
$allDirs     = getAllDirsRecursive($GALLERY_BASE_FS, ''); // para selects de mover
array_unshift($allDirs, ''); // raíz al principio
?>

<style>

.container { padding:18px 20px; }
.msg { background:#0f1724; border:1px solid #1f2a44; padding:10px 12px; margin:10px 0; border-radius:8px; }
.breadcrumbs a, .breadcrumbs span { color:#a7c0ff; text-decoration:none; margin-right:6px; }
.grid { display:flex; flex-wrap:wrap; gap:12px; }
.card { background:#0f1724; border:1px solid #1f2a44; border-radius:12px; padding:12px; }
.card h3 { margin:0 0 8px 0; font-size:14px; }
.folder { width:220px; }
.folder .name { font-weight:600; }
.folder-actions form { display:inline-block; margin-right:6px; }
.folder-list { display:flex; flex-wrap:wrap; gap:12px; margin-top:10px; }
.folder-icon { font-size:28px; margin-right:6px; }
.btn { background:#1f2a44; color:#e6e9ef; border:1px solid #2a3a62; padding:6px 10px; border-radius:8px; cursor:pointer; }
.btn:hover { background:#23304f; }
.input, select { background:#0b1220; color:#e6e9ef; border:1px solid #1f2a44; padding:6px 8px; border-radius:8px; }
.small { font-size:12px; color:#9fb1d9; }
.img-grid { display:flex; flex-wrap:wrap; gap:10px; }
.img-item { width:170px; text-align:center; }
.img-item .thumb { width:150px; height:150px; object-fit:cover; border:2px solid #2a3a62; border-radius:10px; background:#000; }
.img-actions { margin-top:6px; display:flex; gap:6px; justify-content:center; flex-wrap:wrap; }
hr.sep { border:0; border-top:1px solid #1f2a44; margin:16px 0; }
form.inline { display:inline-block; }
label { display:block; margin-bottom:4px; }

/* Progreso */
#uploadProgress { margin-top:10px; }
.progress-row { margin:8px 0; }
.progress-row .name { font-size:12px; margin-bottom:4px; color:#cfd8ff; }
.progress { width:100%; background:#222; border:1px solid #555; height:18px; border-radius:4px; overflow:hidden; }
.progress-bar { height:100%; width:0%; background:#4caf50; text-align:center; font-size:12px; color:white; transition:width .2s; }
.progress-row.done .progress-bar { background:#28a745; }
.progress-row.error .progress-bar { background:#c0392b; }
</style>
<script>
function confirmDeleteDir(name){ return confirm("¿Eliminar la carpeta '" + name + "' y todo su contenido?"); }
function confirmDeleteImg(name){ return confirm("¿Eliminar la imagen '" + name + "'?"); }
</script>

  <h2>🗂️ Admin Galería</h2>
  <div class="breadcrumbs">
    <a href="/talim?s=admin_gallery">Inicio</a>
    <?php
      $acc = [];
      foreach ($breadcrumbs as $i => $seg) {
          $acc[] = $seg;
          $link = '/talim?s=admin_gallery&dir=' . urlencode(implode('/', $acc));
          echo " / <a href=\"{$link}\">" . htmlspecialchars($seg) . "</a>";
      }
    ?>
  </div>

<div class="container">
  <?php foreach ($messages as $m): ?>
    <div class="msg"><?= $m ?></div>
  <?php endforeach; ?>

  <div class="card">
    <h3>📁 Crear carpeta dentro de: <span class="small"><?= $relDir === '' ? 'public/img/gallery' : 'public/img/gallery/'.htmlspecialchars($relDir) ?></span></h3>
    <form method="post">
      <input type="hidden" name="action" value="create_dir">
      <input type="hidden" name="relDir" value="<?= htmlspecialchars($relDir) ?>">
      <label>Nombre de la carpeta</label>
      <input class="input" type="text" name="new_dir_name" placeholder="Ej. Protagonistas" required>
      <button class="btn" type="submit">Crear</button>
    </form>
  </div>

  <?php if ($subdirs): ?>
    <hr class="sep">
    <div class="card">
      <h3>📂 Subcarpetas</h3>
      <div class="folder-list">
        <?php foreach ($subdirs as $d):
          $childRel = $relDir === '' ? $d : ($relDir . '/' . $d);
          $link = '/talim?s=admin_gallery&dir=' . urlencode($childRel);
        ?>
        <div class="folder card">
          <div>
            <span class="folder-icon">📁</span>
            <a class="name" href="<?= $link ?>"><?= htmlspecialchars($d) ?></a>
          </div>
          <div class="folder-actions" style="margin-top:8px;">
            <form class="inline" method="post" onsubmit="return confirm('¿Renombrar carpeta <?= htmlspecialchars($d) ?>?');">
              <input type="hidden" name="action" value="rename_dir">
              <input type="hidden" name="relDir" value="<?= htmlspecialchars($relDir) ?>">
              <input type="hidden" name="old_dir" value="<?= htmlspecialchars($d) ?>">
              <input class="input" type="text" name="new_dir" placeholder="Nuevo nombre" required>
              <button class="btn" type="submit">Renombrar</button>
            </form>
            <form class="inline" method="post" onsubmit="return confirmDeleteDir('<?= htmlspecialchars($childRel) ?>');">
              <input type="hidden" name="action" value="delete_dir">
              <input type="hidden" name="del_dir" value="<?= htmlspecialchars($childRel) ?>">
              <button class="btn" type="submit">Eliminar</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <hr class="sep">

  <!-- Subida con progreso (secuencial). Mantiene fallback POST normal si no hay JS -->
  <div class="card">
    <h3>⬆️ Subir imágenes a: <span class="small"><?= $relDir === '' ? 'public/img/gallery' : 'public/img/gallery/'.htmlspecialchars($relDir) ?></span></h3>
    <form id="uploadForm" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload_images">
      <input type="hidden" name="relDir" value="<?= htmlspecialchars($relDir) ?>">
      <input class="input" type="file" id="imagesInput" name="images[]" accept=".jpg,.jpeg,.png,.gif,.webp" multiple required>
      <button class="btn" type="submit">Subir</button>
      <div class="small" style="margin-top:6px;">Se comprimen automáticamente (JPG/WEBP ~80%, PNG compresión 8) y se crean thumbnails (200×200).</div>
      <div id="uploadProgress"></div>
    </form>
  </div>

  <?php if ($images): ?>
  <hr class="sep">
  <div class="card">
    <h3>🖼️ Imágenes en esta carpeta</h3>
    <div class="img-grid" id="imgGrid">
      <?php foreach ($images as $img):
        $title = formatTitle($img);
        $thumbWeb = webPathJoin($GALLERY_BASE_WEB, ($relDir === '' ? '' : $relDir.'/') . 'thumbnails/' . $img);
        $imgWeb   = webPathJoin($GALLERY_BASE_WEB, ($relDir === '' ? '' : $relDir.'/') . $img);
      ?>
      <div class="img-item">
        <a href="<?= htmlspecialchars($imgWeb) ?>" target="_blank" title="Ver original">
          <img class="thumb" src="<?= htmlspecialchars($thumbWeb) ?>" alt="<?= htmlspecialchars($title) ?>">
        </a>
        <div class="small" style="margin-top:4px;"><?= htmlspecialchars($title) ?></div>
        <div class="img-actions">
          <form method="post" class="inline" onsubmit="return confirmDeleteImg('<?= htmlspecialchars($img) ?>');">
            <input type="hidden" name="action" value="delete_image">
            <input type="hidden" name="relDir" value="<?= htmlspecialchars($relDir) ?>">
            <input type="hidden" name="file" value="<?= htmlspecialchars($img) ?>">
            <button class="btn" type="submit">Eliminar</button>
          </form>
          <form method="post" class="inline">
            <input type="hidden" name="action" value="move_image">
            <input type="hidden" name="relDir" value="<?= htmlspecialchars($relDir) ?>">
            <input type="hidden" name="file" value="<?= htmlspecialchars($img) ?>">
            <select name="to_dir" class="input" required>
              <?php foreach ($allDirs as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $d===$relDir ? 'disabled' : '' ?>>
                  <?= $d === '' ? '/' : '/'. $d ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Mover</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$subdirs && !$images): ?>
    <div class="msg">Esta carpeta está vacía. Crea subcarpetas o sube imágenes.</div>
  <?php endif; ?>

</div>

<script>
// ===== Subida secuencial con barra de progreso por fichero =====
(function(){
  const form = document.getElementById('uploadForm');
  const input = document.getElementById('imagesInput');
  const progressWrap = document.getElementById('uploadProgress');
  const imgGrid = document.getElementById('imgGrid');

  form.addEventListener('submit', function(ev){
    // Interceptar envío normal para activar el modo secuencial con progreso
    ev.preventDefault();

    const files = Array.from(input.files || []);
    if (!files.length) { alert('Selecciona uno o más archivos'); return; }

    progressWrap.innerHTML = ''; // limpiar
    const relDir = form.querySelector('input[name="relDir"]').value || '';

    // Cola secuencial
    let index = 0;
    const next = () => {
      if (index >= files.length) return; // fin
      const file = files[index];

      // Fila de progreso
      const row = document.createElement('div');
      row.className = 'progress-row';
      row.innerHTML = `
        <div class="name">${file.name}</div>
        <div class="progress"><div class="progress-bar">0%</div></div>
      `;
      progressWrap.appendChild(row);
      const bar = row.querySelector('.progress-bar');

      const fd = new FormData();
      fd.append('action', 'upload_one');   // endpoint AJAX en este mismo archivo
      fd.append('relDir', relDir);
      fd.append('imagen', file);

      const xhr = new XMLHttpRequest();
      xhr.open('POST', window.location.href, true);
      xhr.responseType = 'json'; // <-- que el propio XHR parsee JSON

      // Progreso de subida
      xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
          const pct = Math.round((e.loaded / e.total) * 100);
          bar.style.width = pct + '%';
          bar.textContent = pct + '%';
        }
      };

      xhr.onerror = () => {
        row.classList.add('error');
        bar.textContent = 'Error de red';
        index++; next();
      };

      xhr.onreadystatechange = () => {
        if (xhr.readyState === 4) {
          let resp = xhr.response;
          if (!resp && xhr.responseText) {
            try { resp = JSON.parse(xhr.responseText); } catch (e) { /* sigue null */ }
          }
          if (xhr.status === 200 && resp && resp.ok) {
            row.classList.add('done');
            bar.style.width = '100%';
            bar.textContent = '100%';

            // Añadir a la grilla, si existe en DOM
            if (imgGrid) {
              const item = document.createElement('div');
              item.className = 'img-item';
              const cacheBust = '?t=' + Date.now();
              item.innerHTML = `
                <a href="${resp.url}${cacheBust}" target="_blank" title="Ver original">
                  <img class="thumb" src="${resp.thumb}${cacheBust}" alt="${resp.title}">
                </a>
                <div class="small" style="margin-top:4px;">${resp.title}</div>
              `;
              imgGrid.appendChild(item);
            }
          } else {
            row.classList.add('error');
            bar.textContent = (resp && resp.msg) ? resp.msg : 'Error de respuesta';
          }
          // Siguiente fichero tras finalizar éste (éxito o error)
          index++;
          next();
        }
      };

      xhr.send(fd);
    };

    // Arrancar cola
    next();
  });
})();
</script>
