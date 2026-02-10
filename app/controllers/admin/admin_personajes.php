<?php
// admin_personajes.php — Gestión de Personajes (nombre / color / subida de imagen 1:1 JPG/PNG)

// === Configuración de subidas ===
// Carpeta pública donde se servirán las imágenes (URL relativa desde la raíz web)
$WEB_UPLOAD_DIR = "/img/characters/";
// Carpeta física donde se guardarán (asegúrate de que exista o se pueda crear)
$FS_UPLOAD_DIR  = rtrim($_SERVER['DOCUMENT_ROOT'], '/').$WEB_UPLOAD_DIR;
// Límite de tamaño (5 MB)
$MAX_BYTES      = 5 * 1024 * 1024;

// Crear carpeta si no existe
if (!is_dir($FS_UPLOAD_DIR)) {
    @mkdir($FS_UPLOAD_DIR, 0755, true);
}

// === Seguridad / conexión (igual que el resto del panel) ===
if (!isset($link) || !$link) {
    die("Error de conexión a la base de datos.");
}

// Polyfill para PHP < 8
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        // Mismo comportamiento que PHP 8: needle vacío => true
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

$flash = []; // mensajes de estado

// === Helper: borra la imagen anterior si estaba en la misma carpeta de subidas ===
function deleteOldIfLocal($oldWebPath, $WEB_UPLOAD_DIR) {
    $oldWebPath = (string)$oldWebPath;
    if ($oldWebPath && str_starts_with($oldWebPath, $WEB_UPLOAD_DIR)) {
        $full = rtrim($_SERVER['DOCUMENT_ROOT'], '/').$oldWebPath;
        if (is_file($full)) @unlink($full);
    }
}

// === PROCESADO DEL FORM ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pj'])) {
    $id         = intval($_POST['id'] ?? 0);
    $nombre     = trim($_POST['nombre'] ?? '');
    $colortexto = trim($_POST['colortexto'] ?? '');

    if ($id <= 0 || $nombre === '') {
        $flash[] = ["type" => "error", "msg" => "⚠ Falta el ID o el nombre."];
    } else {
        // 1) Actualizar nombre / color
        $stmt = $link->prepare("UPDATE fact_characters SET nombre = ?, colortexto = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssi", $nombre, $colortexto, $id);
            $stmt->execute();
            $stmt->close();
            $flash[] = ["type" => "info", "msg" => "✏ Nombre/Color actualizados."];
        } else {
            $flash[] = ["type" => "error", "msg" => "❌ Error al preparar UPDATE: {$link->error}"];
        }

        // 2) Si hay archivo subido, validar y guardar
        if (isset($_FILES['nueva_img']) && $_FILES['nueva_img']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['nueva_img'];

            if ($f['error'] !== UPLOAD_ERR_OK) {
                $flash[] = ["type" => "error", "msg" => "❌ Error de subida (código {$f['error']})."];
            } elseif ($f['size'] <= 0 || $f['size'] > $MAX_BYTES) {
                $flash[] = ["type" => "error", "msg" => "❌ Tamaño no válido (máx. 5 MB)."];
            } elseif (!is_uploaded_file($f['tmp_name'])) {
                $flash[] = ["type" => "error", "msg" => "❌ Archivo no reconocido como subida válida."];
            } else {
                // Validar MIME real
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($f['tmp_name']);
                $ext   = '';
                if ($mime === 'image/jpeg') $ext = '.jpg';
                if ($mime === 'image/png')  $ext = '.png';

                if ($ext === '') {
                    $flash[] = ["type" => "error", "msg" => "❌ Solo se permiten imágenes JPG o PNG."];
                } else {
                    // Validar proporción 1:1 exacta
                    $is = @getimagesize($f['tmp_name']); // [width, height, type, attr]
                    if (!$is) {
                        $flash[] = ["type" => "error", "msg" => "❌ No se pudo leer la imagen."];
                    } else {
                        $w = intval($is[0] ?? 0);
                        $h = intval($is[1] ?? 0);

                        if ($w <= 0 || $h <= 0 || $w !== $h) {
                            $flash[] = ["type" => "error", "msg" => "❌ La imagen debe ser estrictamente 1:1 (cuadrada)."];
                        } else {
                            // Re-encode para limpiar metadatos y asegurar formato
                            if ($mime === 'image/jpeg') {
                                $src = @imagecreatefromjpeg($f['tmp_name']);
                            } else { // png
                                $src = @imagecreatefrompng($f['tmp_name']);
                            }

                            if (!$src) {
                                $flash[] = ["type" => "error", "msg" => "❌ No se pudo abrir la imagen subida."];
                            } else {
                                $out = imagecreatetruecolor($w, $h);
                                if ($mime === 'image/png') {
                                    imagealphablending($out, false);
                                    imagesavealpha($out, true);
                                }
                                imagecopy($out, $src, 0, 0, 0, 0, $w, $h);

                                // Nombre final (evitamos colisiones y caché)
                                $fname = "pj_{$id}_".date('Ymd_His').$ext;
                                $destFS = $FS_UPLOAD_DIR.$fname;
                                $destWEB = $WEB_UPLOAD_DIR.$fname;

                                $ok = false;
                                if ($mime === 'image/jpeg') {
                                    $ok = imagejpeg($out, $destFS, 90);
                                } else {
                                    $ok = imagepng($out, $destFS, 6);
                                }

                                imagedestroy($src);
                                imagedestroy($out);

                                if (!$ok) {
                                    $flash[] = ["type" => "error", "msg" => "❌ No se pudo guardar la imagen procesada."];
                                } else {
                                    // Obtener ruta antigua para borrar si procede
                                    $old = null;
                                    $st2 = $link->prepare("SELECT img FROM fact_characters WHERE id = ?");
                                    if ($st2) {
                                        $st2->bind_param("i", $id);
                                        $st2->execute();
                                        $st2->bind_result($old);
                                        $st2->fetch();
                                        $st2->close();
                                    }

                                    // Actualizar DB con la nueva ruta web
                                    $st3 = $link->prepare("UPDATE fact_characters SET img = ? WHERE id = ?");
                                    if ($st3) {
                                        $st3->bind_param("si", $destWEB, $id);
                                        $st3->execute();
                                        $st3->close();
                                        $flash[] = ["type" => "ok", "msg" => "✅ Imagen subida y asignada."];

                                        // Borrar la anterior si era local
                                        deleteOldIfLocal($old, $WEB_UPLOAD_DIR);
                                    } else {
                                        // Si falla el UPDATE, borra el archivo recién creado
                                        @unlink($destFS);
                                        $flash[] = ["type" => "error", "msg" => "❌ No se pudo actualizar la ruta en la base de datos."];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// === Cargar listado (según tu SQL)
$result = $link->query("
    SELECT p.id, p.nombre, p.colortexto, p.img, p.cronica
    FROM fact_characters p
    WHERE p.cronica NOT IN (2, 5, 6, 7)
    ORDER BY p.nombre ASC
");
$personajes = [];
while ($row = $result->fetch_assoc()) { $personajes[] = $row; }
?>

<style>
.tabla-pj {
    width: 100%;
    background: #05014E;
    border: 1px solid #000088;
    border-collapse: collapse;
    margin: 0 auto;
    font-family: Verdana, Arial, sans-serif;
    font-size: 11px;
}
.pj-row-head th {
    background: #050b36;
    color: #33CCCC;
    font-weight: bold;
    border-bottom: 2px solid #000088;
    padding: 6px 10px;
    text-align: left;
    white-space: nowrap;
}
.tabla-pj td, .tabla-pj th {
    border: 1px solid #000088;
    background: #05014E;
    padding: 6px 10px;
    vertical-align: middle;
    white-space: nowrap;
}
.tabla-pj tr.pj-row:hover td { background: #000066; color: #33FFFF; }
.inp {
    background: #000033; color: #fff; border: 1px solid #333;
    padding: 4px 6px; font-size: 11px; width: 100%;
}
.color-preview {
    display: inline-block; width: 18px; height: 18px; border:1px solid #000099;
    vertical-align: middle; margin-left:6px;
}
.img-preview { max-height: 36px; max-width: 120px; display:block; }
.filtros-wrap { margin-bottom: 12px; }
.boton2 { cursor: pointer; }
.badge-ok   { color:#7CFC00; }
.badge-info { color:#33FFFF; }
.badge-err  { color:#FF6B6B; }
.note { color:#bbb; font-size:10px; }
</style>

<h2>👤 Gestión de Personajes</h2>

<?php if (!empty($flash)): ?>
    <div style="margin:8px 0;">
        <?php foreach ($flash as $m):
            $cls = $m['type']==='ok'?'badge-ok':($m['type']==='info'?'badge-info':'badge-err'); ?>
            <div class="<?= $cls ?>"><?= htmlspecialchars($m['msg']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="filtros-wrap">
    <input type="text" id="filtroNombre" class="inp" placeholder="Buscar personaje por nombre…" style="max-width:260px;">
    <div class="note">Solo se aceptan imágenes cuadradas (1:1) en JPG o PNG (máx. 5 MB).</div>
</div>

<table id="tabla-personajes" class="tabla-pj">
    <thead>
        <tr class="pj-row-head">
            <th style="width:60px;">ID</th>
            <th style="min-width:220px;">Nombre</th>
            <th style="min-width:180px;">Color (colortexto)</th>
            <th style="min-width:180px;">Imagen actual</th>
            <th style="min-width:260px;">Subir nueva (1:1 JPG/PNG)</th>
            <th style="width:120px;">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($personajes as $pj):
            $c     = htmlspecialchars($pj['colortexto'] ?? '');
            $img   = htmlspecialchars($pj['img'] ?? '');
            $nombre= htmlspecialchars($pj['nombre'] ?? '');
        ?>
        <tr class="pj-row" data-nombre="<?= strtolower($nombre) ?>">
            <td><strong style="color:#33FFFF;"><a href="/characters/<?= $pj['id'] ?>"><?= $pj['id'] ?></a></strong></td>

            <td>
                <form method="post" enctype="multipart/form-data" style="margin:0; display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                    <input type="hidden" name="update_pj" value="1">
                    <input type="hidden" name="id" value="<?= $pj['id'] ?>">
                    <input class="inp" type="text" name="nombre" value="<?= $nombre ?>" placeholder="Nombre">
            </td>

            <td>
                <div style="display:flex; align-items:center; gap:6px;">
                    <input class="inp colortexto" type="text" name="colortexto" value="<?= $c ?>" placeholder="#RRGGBB o nombre CSS">
                    <span class="color-preview" style="background: <?= $c ?: '#000' ?>;" title="Previsualización"></span>
                </div>
            </td>

            <td>
                <?php if ($img): ?>
                    <img class="img-preview" src="<?= $img ?>" alt="img">
                    <div class="note"><?= $img ?></div>
                <?php else: ?>
                    <span style="color:#999;">(sin imagen)</span>
                <?php endif; ?>
            </td>

            <td>
                <input class="inp fileinp" type="file" name="nueva_img" accept="image/png,image/jpeg">
                <div class="note">Requisito: 1:1 exacto</div>
            </td>

            <td>
                <button class="boton2" type="submit">Guardar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
// Filtro rápido por nombre
document.getElementById('filtroNombre').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#tabla-personajes tbody tr').forEach(tr => {
        const nombre = tr.getAttribute('data-nombre') || '';
        tr.style.display = nombre.indexOf(q) !== -1 ? '' : 'none';
    });
});

// Previsualización dinámica del color
document.querySelectorAll('.colortexto').forEach(inp => {
    inp.addEventListener('input', function(){
        const box = this.parentElement.querySelector('.color-preview');
        if (box) box.style.background = this.value || '#000';
    });
});

// Validación rápida en cliente: 1:1 y tipo imagen
document.querySelectorAll('.fileinp').forEach(inp => {
    inp.addEventListener('change', function(){
        const f = this.files && this.files[0];
        if (!f) return;
        if (!['image/png','image/jpeg'].includes(f.type)) {
            alert('Solo JPG o PNG.');
            this.value = '';
            return;
        }
        const url = URL.createObjectURL(f);
        const img = new Image();
        img.onload = () => {
            if (img.naturalWidth !== img.naturalHeight) {
                alert('La imagen debe ser estrictamente 1:1 (cuadrada).');
                this.value = '';
            }
            URL.revokeObjectURL(url);
        };
        img.src = url;
    });
});
</script>
