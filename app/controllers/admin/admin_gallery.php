<?php
/********************************************************
 * Admin Galeria - gestion de carpetas e imagenes
 * Requisitos: extension GD habilitada
 * Estructura:
 *   /img/gallery/
 *       /CarpetaA/
 *           /thumbnails/
 *           imagen1.jpg
 *           ...
 ********************************************************/

// ==============================
// Configuracion base
// ==============================
$GALLERY_BASE_WEB = "/public/img/gallery"; // ruta web
$GALLERY_BASE_FS  = rtrim($_SERVER['DOCUMENT_ROOT'], "/") . $GALLERY_BASE_WEB; // ruta filesystem

$ALLOWED_EXT = ['jpg','jpeg','png','gif','webp'];
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
include_once(__DIR__ . '/../../helpers/admin_ajax.php');

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_gallery';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}

function gallery_csrf_ok(string $sessionKey): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($token, $sessionKey);
    }
    return is_string($token) && $token !== '' && isset($_SESSION[$sessionKey]) && hash_equals($_SESSION[$sessionKey], $token);
}

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

function gallery_build_state(string $baseFs, string $baseWeb, string $relDir, array $allowed): array {
    $absDir = fsPathJoin($baseFs, $relDir);
    ensureDir($absDir);
    ensureDir(fsPathJoin($absDir, "thumbnails"));

    $subdirs = listSubdirs($absDir);
    $images = listImages($absDir, $allowed);
    $allDirs = getAllDirsRecursive($baseFs, '');
array_unshift($allDirs, ''); // raiz al principio
    $breadcrumbs = ($relDir === '') ? [] : explode('/', $relDir);

    $subdirRows = [];
    foreach ($subdirs as $d) {
        $childRel = $relDir === '' ? $d : ($relDir . '/' . $d);
        $subdirRows[] = [
            'name' => $d,
            'rel' => $childRel,
            'link' => '/talim?s=admin_gallery&dir=' . urlencode($childRel),
        ];
    }

    $imageRows = [];
    foreach ($images as $img) {
        $imageRows[] = [
            'file' => $img,
            'title' => formatTitle($img),
            'thumb' => webPathJoin($baseWeb, ($relDir === '' ? '' : $relDir.'/') . 'thumbnails/' . $img),
            'url' => webPathJoin($baseWeb, ($relDir === '' ? '' : $relDir.'/') . $img),
        ];
    }

    return [
        'relDir' => $relDir,
        'breadcrumbs' => $breadcrumbs,
        'subdirs' => $subdirRows,
        'images' => $imageRows,
        'allDirs' => $allDirs,
        'hasSubdirs' => !empty($subdirRows),
        'hasImages' => !empty($imageRows),
    ];
}

// ==============================
// Imagen: compresion + thumb
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
    // Transparencia basica para PNG/GIF
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
    $messages[] = "Ruta no valida. Volviendo a la raiz.";
    $relDir = '';
}
$absDir = fsPathJoin($GALLERY_BASE_FS, $relDir);
ensureDir($absDir);
ensureDir(fsPathJoin($absDir, "thumbnails"));

$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1') || ((string)($_POST['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

if ($isAjaxRequest && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && (string)($_GET['ajax_mode'] ?? '') === 'state') {
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $state = gallery_build_state($GALLERY_BASE_FS, $GALLERY_BASE_WEB, $relDir, $ALLOWED_EXT);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $state], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// ==============================
// Procesar POST (acciones normales + AJAX)
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isAjaxPost = (
        ((string)($_POST['ajax'] ?? '') === '1')
        || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
    );

    if ($isAjaxPost && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $csrfValid = gallery_csrf_ok($ADMIN_CSRF_SESSION_KEY);
    if (!$csrfValid) {
        if ($isAjaxPost) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => 'CSRF invalido. Recarga la pagina.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $messages[] = 'CSRF invalido. Recarga la pagina.';
    }
    // Normalizar inputs
    $postRel = isset($_POST['relDir']) ? trim(urldecode((string)$_POST['relDir'])) : $relDir;
    if ($postRel !== '' && !isValidRelPath($postRel)) $postRel = '';
    $postAbs = fsPathJoin($GALLERY_BASE_FS, $postRel);

    // --- Endpoint AJAX: subir UNO con JSON limpio ---
    if ($csrfValid && $action === 'upload_one') {
        $resp = ["ok" => false, "msg" => ""];

        if (!isset($_FILES['imagen'])) {
            $resp["msg"] = "No se recibio archivo.";
        } else {
            $file = $_FILES['imagen'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $origName = basename($file['name']);
                if (!isValidFileName($origName, $ALLOWED_EXT)) {
                    $resp["msg"] = "Extension no permitida ($origName).";
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
                        $resp["msg"]   = "Subida completada: $safeName";
                    } else {
                        $resp["msg"] = "Error al procesar $origName.";
                    }
                }
            } else {
                $resp["msg"] = "Error en la subida.";
            }
        }

        // Limpia cualquier salida previa para no romper el JSON.
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo json_encode($resp);
        exit; // Importantisimo.
    }
    // --- FIN endpoint AJAX ---

    if ($isAjaxPost && $action === 'get_state') {
        $state = gallery_build_state($GALLERY_BASE_FS, $GALLERY_BASE_WEB, $postRel, $ALLOWED_EXT);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'message' => 'OK', 'data' => $state], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $actionOk = true;
    $actionMsg = '';
    if ($csrfValid) switch ($action) {
        case 'create_dir':
            $newName = trim((string)($_POST['new_dir_name'] ?? ''));
            if (!isValidDirName($newName)) { $messages[] = "Nombre de carpeta no valido."; break; }
            $targetAbs = fsPathJoin($postAbs, $newName);
            if (is_dir($targetAbs)) { $messages[] = "La carpeta ya existe."; break; }
            if (ensureDir($targetAbs) && ensureDir($targetAbs . "/thumbnails")) {
                $messages[] = "Carpeta creada: " . htmlspecialchars($newName);
            } else {
                $messages[] = "No se pudo crear la carpeta.";
            }
            break;

        case 'rename_dir':
            $old = trim((string)($_POST['old_dir'] ?? ''));
            $new = trim((string)($_POST['new_dir'] ?? ''));
            if (!isValidDirName($old) || !isValidDirName($new)) { $messages[] = "Nombres invalidos."; break; }
            $oldAbs = fsPathJoin($postAbs, $old);
            $newAbs = fsPathJoin($postAbs, $new);
            if (!is_dir($oldAbs)) { $messages[] = "La carpeta origen no existe."; break; }
            if (is_dir($newAbs)) { $messages[] = "Ya existe una carpeta con ese nombre."; break; }
            if (@rename($oldAbs, $newAbs)) {
                $messages[] = "Carpeta renombrada.";
                if ($relDir === ($postRel === '' ? $old : $postRel . "/" . $old)) {
                    $relDir = ($postRel === '' ? $new : $postRel . "/" . $new);
                }
            } else {
                $messages[] = "No se pudo renombrar.";
            }
            break;

        case 'delete_dir':
            $del = trim((string)($_POST['del_dir'] ?? ''));
            if (!isValidRelPath($del)) { $messages[] = "Carpeta invalida."; break; }
            if ($del === '') { $messages[] = "No se puede eliminar la raiz."; break; }
            $delAbs = fsPathJoin($GALLERY_BASE_FS, $del);
            if (!is_dir($delAbs)) { $messages[] = "Carpeta no encontrada."; break; }
            if (rrmdir($delAbs)) {
                $messages[] = "Carpeta eliminada.";
                if (strpos($relDir, $del) === 0) $relDir = '';
            } else {
                $messages[] = "No se pudo eliminar la carpeta.";
            }
            break;

        case 'upload_images': // fallback no-AJAX
            if (!isset($_FILES['images'])) { $messages[] = "No se recibieron archivos."; break; }
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
            $messages[] = $okN ? "$okN imagen(es) subida(s)." : "No se subio ninguna imagen valida.";
            break;

        case 'delete_image':
            $file = basename((string)($_POST['file'] ?? ''));
            if (!isValidFileName($file, $ALLOWED_EXT)) { $messages[] = "Archivo invalido."; break; }
            $imgAbs   = fsPathJoin($postAbs, $file);
            $thumbAbs = fsPathJoin($postAbs, "thumbnails/" . $file);
            $ok = true;
            if (is_file($imgAbs))   $ok = $ok && @unlink($imgAbs);
            if (is_file($thumbAbs)) $ok = $ok && @unlink($thumbAbs);
            $messages[] = $ok ? "Imagen eliminada." : "No se pudo eliminar.";
            break;

        case 'move_image':
            $file = basename((string)($_POST['file'] ?? ''));
            $toRel = trim((string)($_POST['to_dir'] ?? ''));
            if (!isValidFileName($file, $ALLOWED_EXT)) { $messages[] = "Archivo invalido."; break; }
            if ($toRel === '' || !isValidRelPath($toRel)) { $messages[] = "Carpeta destino invalida."; break; }
            $fromAbs = $postAbs;
            $toAbs   = fsPathJoin($GALLERY_BASE_FS, $toRel);
            ensureDir($toAbs);
            ensureDir($toAbs . "/thumbnails");

            $srcImg  = fsPathJoin($fromAbs, $file);
            $srcTh   = fsPathJoin($fromAbs, "thumbnails/" . $file);
            if (!is_file($srcImg)) { $messages[] = "Imagen origen no encontrada."; break; }

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
                $messages[] = "Imagen movida.";
            } else {
                $messages[] = "No se pudo mover la imagen.";
            }
            break;
    }

    // Actualizar contexto
    $relDir = $postRel;
    $absDir = fsPathJoin($GALLERY_BASE_FS, $relDir);

    if ($isAjaxPost && $action !== 'upload_one') {
        $lastMsg = '';
        if (!empty($messages)) {
            $lastMsg = strip_tags((string)$messages[count($messages)-1]);
        }
        $msgNorm = function_exists('mb_strtolower') ? mb_strtolower($lastMsg, 'UTF-8') : strtolower($lastMsg);
        if (preg_match('/no se pudo|inval|error|no encontrada|no existe|no se recibieron|no se puede|ninguna imagen/', $msgNorm)) {
            $actionOk = false;
        }
        if ($lastMsg === '') {
            $lastMsg = $actionOk ? 'Operacion completada.' : 'No se pudo completar la operacion.';
        }
        $state = gallery_build_state($GALLERY_BASE_FS, $GALLERY_BASE_WEB, $relDir, $ALLOWED_EXT);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => (bool)$actionOk,
            'message' => $lastMsg,
            'data' => $state,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}

// Datos para pintar UI
$breadcrumbs = ($relDir === '') ? [] : explode('/', $relDir);
$subdirs     = listSubdirs($absDir);
$images      = listImages($absDir, $ALLOWED_EXT);
$allDirs     = getAllDirsRecursive($GALLERY_BASE_FS, ''); // para selects de mover
array_unshift($allDirs, ''); // raiz al principio
$galleryState = gallery_build_state($GALLERY_BASE_FS, $GALLERY_BASE_WEB, $relDir, $ALLOWED_EXT);
?>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script src="<?= htmlspecialchars($adminHttpJs, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
window.GALLERY_STATE = <?= json_encode($galleryState, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); ?>;
</script>

<script>
function confirmDeleteDir(name){ return confirm("¿Eliminar la carpeta \"" + name + "\" y todo su contenido?"); }
function confirmDeleteImg(name){ return confirm("¿Eliminar la imagen \"" + name + "\"?"); }
</script>

  <h2>Admin Galeria</h2>
  <div class="breadcrumbs" id="galleryBreadcrumbs">
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

<div class="container" id="galleryContainer">
  <div id="galleryMessages">
  <?php foreach ($messages as $m): ?>
    <div class="msg"><?= $m ?></div>
  <?php endforeach; ?>
  </div>

  <div class="card">
    <h3>Crear carpeta dentro de: <span class="small" id="galleryCurrentPath"><?= $relDir === '' ? 'public/img/gallery' : 'public/img/gallery/'.htmlspecialchars($relDir) ?></span></h3>
    <form method="post" data-gallery-form="create_dir">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" value="create_dir">
      <input type="hidden" name="relDir" value="<?= htmlspecialchars($relDir) ?>">
      <label>Nombre de la carpeta</label>
      <input class="input" type="text" name="new_dir_name" placeholder="Ej. Protagonistas" required>
      <button class="btn" type="submit">Crear</button>
    </form>
  </div>

  <hr class="sep" id="gallerySubdirsSep"<?= $subdirs ? '' : ' style="display:none;"' ?>>
    <div class="card" id="gallerySubdirsCard"<?= $subdirs ? '' : ' style="display:none;"' ?>>
      <h3>Subcarpetas</h3>
      <div class="folder-list" id="gallerySubdirsList">
        <?php foreach ($subdirs as $d):
          $childRel = $relDir === '' ? $d : ($relDir . '/' . $d);
          $link = '/talim?s=admin_gallery&dir=' . urlencode($childRel);
        ?>
        <div class="folder card">
          <div>
            <a class="name" href="<?= $link ?>"><?= htmlspecialchars($d) ?></a>
          </div>
          <div class="folder-actions adm-mt-8">
            <form class="inline" method="post" data-gallery-form="rename_dir" onsubmit="return confirm('¿Renombrar carpeta <?= htmlspecialchars($d) ?>?');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
              <input type="hidden" name="action" value="rename_dir">
              <input type="hidden" name="relDir" value="<?= htmlspecialchars($relDir) ?>">
              <input type="hidden" name="old_dir" value="<?= htmlspecialchars($d) ?>">
              <input class="input" type="text" name="new_dir" placeholder="Nuevo nombre" required>
              <button class="btn" type="submit">Renombrar</button>
            </form>
            <form class="inline" method="post" data-gallery-form="delete_dir" onsubmit="return confirmDeleteDir('<?= htmlspecialchars($childRel) ?>');">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
              <input type="hidden" name="action" value="delete_dir">
              <input type="hidden" name="del_dir" value="<?= htmlspecialchars($childRel) ?>">
              <button class="btn" type="submit">Eliminar</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  <hr class="sep">

  <!-- Subida con progreso (secuencial). Mantiene fallback POST normal si no hay JS -->
  <div class="card">
    <h3>Subir imagenes a: <span class="small"><?= $relDir === '' ? 'public/img/gallery' : 'public/img/gallery/'.htmlspecialchars($relDir) ?></span></h3>
    <form id="uploadForm" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
      <input type="hidden" name="action" value="upload_images">
      <input type="hidden" name="relDir" value="<?= htmlspecialchars($relDir) ?>">
      <input class="input" type="file" id="imagesInput" name="images[]" accept=".jpg,.jpeg,.png,.gif,.webp" multiple required>
      <button class="btn" type="submit">Subir</button>
      <div class="small adm-mt-6">Se comprimen automaticamente (JPG/WEBP ~80%, PNG compresion 8) y se crean thumbnails (200x200).</div>
      <div id="uploadProgress"></div>
    </form>
  </div>

  <hr class="sep" id="galleryImagesSep"<?= $images ? '' : ' style="display:none;"' ?>>
  <div class="card" id="galleryImagesCard"<?= $images ? '' : ' style="display:none;"' ?>>
    <h3>Imagenes en esta carpeta</h3>
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
        <div class="small adm-mt-4"><?= htmlspecialchars($title) ?></div>
        <div class="img-actions">
          <form method="post" class="inline" data-gallery-form="delete_image" onsubmit="return confirmDeleteImg('<?= htmlspecialchars($img) ?>');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
            <input type="hidden" name="action" value="delete_image">
            <input type="hidden" name="relDir" value="<?= htmlspecialchars($relDir) ?>">
            <input type="hidden" name="file" value="<?= htmlspecialchars($img) ?>">
            <button class="btn" type="submit">Eliminar</button>
          </form>
          <form method="post" class="inline" data-gallery-form="move_image">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
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
  <div class="msg" id="galleryEmptyBox"<?= (!$subdirs && !$images) ? '' : ' style="display:none;"' ?>>Esta carpeta esta vacia. Crea subcarpetas o sube imagenes.</div>

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
    const ajaxUrl = new URL(window.location.href);
    ajaxUrl.searchParams.set('s', 'admin_gallery');
    ajaxUrl.searchParams.set('ajax', '1');

    // Cola secuencial
    let index = 0;
    const next = () => {
      if (index >= files.length) {
        if (typeof window.refreshGalleryState === 'function') window.refreshGalleryState();
        return; // fin
      }
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
      fd.append('ajax', '1');
      if (window.ADMIN_CSRF_TOKEN) fd.append('csrf', String(window.ADMIN_CSRF_TOKEN));

      const xhr = new XMLHttpRequest();
      xhr.open('POST', ajaxUrl.toString(), true);
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

            // Anadir a la grilla, si existe en DOM
            if (imgGrid) {
              const item = document.createElement('div');
              item.className = 'img-item';
              const cacheBust = '?t=' + Date.now();
              item.innerHTML = `
                <a href="${resp.url}${cacheBust}" target="_blank" title="Ver original">
                  <img class="thumb" src="${resp.thumb}${cacheBust}" alt="${resp.title}">
                </a>
                <div class="small adm-mt-4">${resp.title}</div>
              `;
              imgGrid.appendChild(item);
            }
          } else {
            row.classList.add('error');
            bar.textContent = (resp && resp.msg) ? resp.msg : 'Error de respuesta';
          }
          // Siguiente fichero tras finalizar este (exito o error)
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

<script>
(function(){
  const container = document.getElementById('galleryContainer');
  if (!container) return;

  function esc(s){
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function showMessage(msg, isError){
    const wrap = document.getElementById('galleryMessages');
    if (!wrap) return;
    wrap.innerHTML = '';
    if (!msg) return;
    const div = document.createElement('div');
    div.className = 'msg' + (isError ? ' err' : '');
    div.textContent = msg;
    wrap.appendChild(div);
  }

  function updateRelDirFields(relDir){
    document.querySelectorAll('#galleryContainer input[name="relDir"]').forEach(function(inp){
      inp.value = relDir || '';
    });
    const pathEl = document.getElementById('galleryCurrentPath');
    if (pathEl) {
      pathEl.textContent = relDir ? ('public/img/gallery/' + relDir) : 'public/img/gallery';
    }
  }

  function renderBreadcrumbs(state){
    const el = document.getElementById('galleryBreadcrumbs');
    if (!el) return;
    const crumbs = Array.isArray(state.breadcrumbs) ? state.breadcrumbs : [];
    let html = '<a href="/talim?s=admin_gallery">Inicio</a>';
    const acc = [];
    crumbs.forEach(function(seg){
      acc.push(seg);
      html += ' / <a href="/talim?s=admin_gallery&dir=' + encodeURIComponent(acc.join('/')) + '">' + esc(seg) + '</a>';
    });
    el.innerHTML = html;
  }

  function renderSubdirs(state){
    const sep = document.getElementById('gallerySubdirsSep');
    const card = document.getElementById('gallerySubdirsCard');
    const list = document.getElementById('gallerySubdirsList');
    if (!sep || !card || !list) return;
    const rows = Array.isArray(state.subdirs) ? state.subdirs : [];
    if (!rows.length) {
      sep.style.display = 'none';
      card.style.display = 'none';
      list.innerHTML = '';
      return;
    }
    sep.style.display = '';
    card.style.display = '';
    let html = '';
    rows.forEach(function(d){
      html += '<div class="folder card">';
      html += '<div><a class="name" href="' + esc(d.link || '#') + '">' + esc(d.name || '') + '</a></div>';
      html += '<div class="folder-actions adm-mt-8">';
      html += '<form class="inline" method="post" data-gallery-form="rename_dir" onsubmit="return confirm(\'¿Renombrar carpeta ' + esc(d.name || '') + '?\');">';
      html += '<input type="hidden" name="csrf" value="' + esc(window.ADMIN_CSRF_TOKEN || '') + '">';
      html += '<input type="hidden" name="action" value="rename_dir">';
      html += '<input type="hidden" name="relDir" value="' + esc(state.relDir || '') + '">';
      html += '<input type="hidden" name="old_dir" value="' + esc(d.name || '') + '">';
      html += '<input class="input" type="text" name="new_dir" placeholder="Nuevo nombre" required>';
      html += '<button class="btn" type="submit">Renombrar</button></form>';
      html += '<form class="inline" method="post" data-gallery-form="delete_dir" onsubmit="return confirmDeleteDir(\'' + esc(d.rel || '') + '\');">';
      html += '<input type="hidden" name="csrf" value="' + esc(window.ADMIN_CSRF_TOKEN || '') + '">';
      html += '<input type="hidden" name="action" value="delete_dir">';
      html += '<input type="hidden" name="del_dir" value="' + esc(d.rel || '') + '">';
      html += '<button class="btn" type="submit">Eliminar</button></form>';
      html += '</div></div>';
    });
    list.innerHTML = html;
  }

  function moveOptions(state){
    const allDirs = Array.isArray(state.allDirs) ? state.allDirs : [''];
    return allDirs.map(function(d){
      const disabled = (String(d || '') === String(state.relDir || '')) ? ' disabled' : '';
      const label = d === '' ? '/' : ('/' + d);
      return '<option value="' + esc(d) + '"' + disabled + '>' + esc(label) + '</option>';
    }).join('');
  }

  function renderImages(state){
    const sep = document.getElementById('galleryImagesSep');
    const card = document.getElementById('galleryImagesCard');
    const grid = document.getElementById('imgGrid');
    if (!sep || !card || !grid) return;
    const rows = Array.isArray(state.images) ? state.images : [];
    if (!rows.length) {
      sep.style.display = 'none';
      card.style.display = 'none';
      grid.innerHTML = '';
      return;
    }
    sep.style.display = '';
    card.style.display = '';
    const options = moveOptions(state);
    let html = '';
    rows.forEach(function(r){
      html += '<div class="img-item">';
      html += '<a href="' + esc(r.url || '#') + '" target="_blank" title="Ver original"><img class="thumb" src="' + esc(r.thumb || '') + '" alt="' + esc(r.title || '') + '"></a>';
      html += '<div class="small adm-mt-4">' + esc(r.title || '') + '</div><div class="img-actions">';
      html += '<form method="post" class="inline" data-gallery-form="delete_image" onsubmit="return confirmDeleteImg(\'' + esc(r.file || '') + '\');">';
      html += '<input type="hidden" name="csrf" value="' + esc(window.ADMIN_CSRF_TOKEN || '') + '">';
      html += '<input type="hidden" name="action" value="delete_image"><input type="hidden" name="relDir" value="' + esc(state.relDir || '') + '">';
      html += '<input type="hidden" name="file" value="' + esc(r.file || '') + '"><button class="btn" type="submit">Eliminar</button></form>';
      html += '<form method="post" class="inline" data-gallery-form="move_image">';
      html += '<input type="hidden" name="csrf" value="' + esc(window.ADMIN_CSRF_TOKEN || '') + '">';
      html += '<input type="hidden" name="action" value="move_image"><input type="hidden" name="relDir" value="' + esc(state.relDir || '') + '">';
      html += '<input type="hidden" name="file" value="' + esc(r.file || '') + '"><select name="to_dir" class="input" required>' + options + '</select>';
      html += '<button class="btn" type="submit">Mover</button></form></div></div>';
    });
    grid.innerHTML = html;
  }

  function renderEmpty(state){
    const box = document.getElementById('galleryEmptyBox');
    if (!box) return;
    const has = !!(state && (state.hasSubdirs || state.hasImages));
    box.style.display = has ? 'none' : '';
  }

  function applyState(state){
    if (!state) return;
    window.GALLERY_STATE = state;
    updateRelDirFields(state.relDir || '');
    renderBreadcrumbs(state);
    renderSubdirs(state);
    renderImages(state);
    renderEmpty(state);
  }

  async function request(url, opts){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
      return window.HGAdminHttp.request(url, opts || {});
    }
    const cfg = Object.assign({ method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }, opts || {});
    const res = await fetch(url, cfg);
    const txt = await res.text();
    const json = txt ? JSON.parse(txt) : {};
    if (!res.ok || !json || json.ok === false) throw new Error((json && (json.message || json.msg || json.error)) || ('HTTP ' + res.status));
    return json;
  }

  function endpointUrl(){
    const url = new URL(window.location.href);
    url.searchParams.set('s', 'admin_gallery');
    url.searchParams.set('ajax', '1');
    return url.toString();
  }

  window.refreshGalleryState = async function(){
    try {
      const fd = new FormData();
      fd.set('action', 'get_state');
      fd.set('relDir', (window.GALLERY_STATE && window.GALLERY_STATE.relDir) ? window.GALLERY_STATE.relDir : '');
      fd.set('ajax', '1');
      if (window.ADMIN_CSRF_TOKEN) fd.set('csrf', String(window.ADMIN_CSRF_TOKEN));
      const payload = await request(endpointUrl(), { method: 'POST', body: fd });
      if (payload && payload.data) applyState(payload.data);
    } catch (e) {
      // fallback silencioso
    }
  };

  container.addEventListener('submit', async function(ev){
    const form = ev.target;
    if (!form || !form.matches('form[data-gallery-form]')) return;
    ev.preventDefault();
    const fd = new FormData(form);
    fd.set('ajax', '1');
    if (window.ADMIN_CSRF_TOKEN) fd.set('csrf', String(window.ADMIN_CSRF_TOKEN));
    try {
      const payload = await request(endpointUrl(), { method: 'POST', body: fd, loadingEl: form });
      if (payload && payload.data) applyState(payload.data);
      showMessage((payload && payload.message) || 'Operacion completada.', false);
      if (form.getAttribute('data-gallery-form') === 'create_dir') {
        const inp = form.querySelector('input[name="new_dir_name"]');
        if (inp) inp.value = '';
      }
    } catch (e) {
      showMessage((window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(e) : (e.message || 'Error'), true);
    }
  });

  applyState(window.GALLERY_STATE || {});
})();
</script>

