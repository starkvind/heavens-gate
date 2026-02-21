<?php
// admin_avatar_mass.php - Actualizacion masiva de avatares de personajes (sin paginacion)

if (!isset($link) || !$link) { die("Error de conexion a la base de datos."); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$DOCROOT = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
$AV_UPLOADDIR = $DOCROOT . '/public/img/characters';
$AV_URLBASE = '/img/characters';
if (!is_dir($AV_UPLOADDIR)) { @mkdir($AV_UPLOADDIR, 0775, true); }

function slugify($text){
    $text = trim((string)$text);
    if (function_exists('iconv')) { $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text); }
    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $text = trim((string)$text, '-');
    $text = strtolower((string)$text);
    $text = preg_replace('~[^-a-z0-9]+~', '', (string)$text);
    return $text ?: 'pj';
}

function save_avatar_file(array $file, int $pjId, string $displayName, string $uploadDir, string $urlBase){
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return ['ok'=>false,'msg'=>'No file uploaded'];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['ok'=>false,'msg'=>'Upload error (#'.$file['error'].')'];
    if (($file['size'] ?? 0) > 5*1024*1024) return ['ok'=>false,'msg'=>'File exceeds 5 MB'];

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) return ['ok'=>false,'msg'=>'Invalid upload'];

    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = (string)finfo_file($fi, $tmp);
            finfo_close($fi);
        }
    }
    if ($mime === '') {
        $gi = @getimagesize($tmp);
        $mime = (string)($gi['mime'] ?? '');
    }

    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) return ['ok'=>false,'msg'=>'Unsupported format (JPG/PNG/GIF/WebP only)'];

    $ext  = $allowed[$mime];
    $slug = slugify($displayName ?: 'pj');
    $name = sprintf('pj-%d-%s-%s.%s', $pjId, $slug, date('YmdHis'), $ext);
    $dst  = rtrim($uploadDir, '/').'/'.$name;

    if (!@move_uploaded_file($tmp, $dst)) return ['ok'=>false,'msg'=>'Could not move uploaded file'];
    @chmod($dst, 0644);

    return ['ok'=>true, 'url'=>rtrim($urlBase, '/').'/'.$name, 'path'=>$dst];
}

function safe_unlink_avatar(string $relUrl, string $uploadDir){
    if ($relUrl === '') return;
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
    $rel = '/'.ltrim($relUrl, '/');

    if (strpos($rel, '/img/') === 0) {
        $abs = $docroot . '/public' . $rel;
    } else {
        $abs = $docroot . $rel;
    }

    $base = realpath($uploadDir);
    $absr = @realpath($abs);
    if ($absr && $base && strpos($absr, $base) === 0 && is_file($absr)) {
        @unlink($absr);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['ajax'] ?? '') === 'upload_avatar')) {
    // Captura cualquier salida accidental (warnings, espacios, BOM) para no romper JSON.
    if (ob_get_level() === 0) { ob_start(); }
    header('Content-Type: application/json; charset=UTF-8');
    $jsonExit = function(array $payload){
        $noise = '';
        if (ob_get_level() > 0) {
            $noise = (string)ob_get_clean();
        }
        if (trim($noise) !== '') {
            $payload['_debug_noise'] = substr(trim($noise), 0, 1200);
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    };

    $charId = isset($_POST['character_id']) ? (int)$_POST['character_id'] : 0;
    if ($charId <= 0) {
        $jsonExit(['ok'=>false, 'msg'=>'Invalid character id']);
    }

    $charName = '';
    $currentImg = '';
    if ($st = $link->prepare("SELECT name, image_url FROM fact_characters WHERE id=? LIMIT 1")) {
        $st->bind_param('i', $charId);
        $st->execute();
        $rs = $st->get_result();
        if ($rs && ($row = $rs->fetch_assoc())) {
            $charName = (string)($row['name'] ?? '');
            $currentImg = (string)($row['image_url'] ?? '');
        } else {
            $jsonExit(['ok'=>false, 'msg'=>'Character not found']);
            $st->close();
        }
        $st->close();
    } else {
        $jsonExit(['ok'=>false, 'msg'=>'SQL prepare error: '.$link->error]);
    }

    if (!isset($_FILES['avatar'])) {
        $jsonExit(['ok'=>false, 'msg'=>'Missing file']);
    }

    $res = save_avatar_file($_FILES['avatar'], $charId, $charName, $AV_UPLOADDIR, $AV_URLBASE);
    if (!$res['ok']) {
        $jsonExit(['ok'=>false, 'msg'=>$res['msg']]);
    }

    if ($st = $link->prepare("UPDATE fact_characters SET image_url=? WHERE id=?")) {
        $newUrl = (string)$res['url'];
        $st->bind_param('si', $newUrl, $charId);
        if (!$st->execute()) {
            $st->close();
            $jsonExit(['ok'=>false, 'msg'=>'Could not update DB: '.$link->error]);
        }
        $st->close();

        if ($currentImg !== '' && $currentImg !== $newUrl) {
            safe_unlink_avatar($currentImg, $AV_UPLOADDIR);
        }

        $jsonExit(['ok'=>true, 'msg'=>'Avatar updated', 'url'=>$newUrl]);
    }

    $jsonExit(['ok'=>false, 'msg'=>'SQL prepare error: '.$link->error]);
}

$characters = [];
$sql = "
SELECT
    p.id,
    p.pretty_id,
    p.name,
    p.image_url,
    p.chronicle_id,
    COALESCE(ch.name, '') AS chronicle_name,
    COALESCE(bg.group_id, 0) AS group_id,
    COALESCE(g.name, '') AS group_name,
    COALESCE(bo.clan_id, 0) AS org_id,
    COALESCE(o.name, '') AS org_name
FROM fact_characters p
LEFT JOIN (
    SELECT character_id, MIN(group_id) AS group_id
    FROM bridge_characters_groups
    WHERE (is_active=1 OR is_active IS NULL)
    GROUP BY character_id
) bg ON bg.character_id = p.id
LEFT JOIN dim_groups g ON g.id = bg.group_id
LEFT JOIN (
    SELECT character_id, MIN(clan_id) AS clan_id
    FROM bridge_characters_organizations
    WHERE (is_active=1 OR is_active IS NULL)
    GROUP BY character_id
) bo ON bo.character_id = p.id
LEFT JOIN dim_organizations o ON o.id = bo.clan_id
LEFT JOIN dim_chronicles ch ON ch.id = p.chronicle_id
ORDER BY p.name ASC
";

if ($rs = $link->query($sql)) {
    while ($r = $rs->fetch_assoc()) {
        $characters[] = $r;
    }
    $rs->close();
}

$groupOpts = [];
$orgOpts = [];
$chronOpts = [];
foreach ($characters as $c) {
    $gid = (int)($c['group_id'] ?? 0);
    $oid = (int)($c['org_id'] ?? 0);
    $cid = (int)($c['chronicle_id'] ?? 0);
    if ($gid > 0) $groupOpts[$gid] = (string)($c['group_name'] ?? '');
    if ($oid > 0) $orgOpts[$oid] = (string)($c['org_name'] ?? '');
    if ($cid > 0) $chronOpts[$cid] = (string)($c['chronicle_name'] ?? '');
}
asort($groupOpts, SORT_NATURAL | SORT_FLAG_CASE);
asort($orgOpts, SORT_NATURAL | SORT_FLAG_CASE);
asort($chronOpts, SORT_NATURAL | SORT_FLAG_CASE);
?>

<style>
.avatar-mass-wrap{ background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; }
.avatar-mass-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.avatar-mass-head h2{ margin:0; color:#33FFFF; font-size:16px; }
.avatar-mass-filters{ margin:10px 0; display:grid; grid-template-columns: repeat(3, minmax(190px,1fr)); gap:8px; }
.avatar-mass-filters select,
.avatar-mass-filters input{
  width:100%; background:#000033; color:#fff; border:1px solid #333; border-radius:6px; padding:6px 8px; font-size:12px;
}
.avatar-mass-list{ width:100%; border-collapse:collapse; font-size:11px; }
.avatar-mass-list th,.avatar-mass-list td{ border:1px solid #000088; padding:6px 8px; vertical-align:middle; background:#05014E; }
.avatar-mass-list th{ background:#050b36; color:#33CCCC; text-align:left; position:sticky; top:0; z-index:2; }
.avatar-mass-list tr:hover td{ background:#000066; }
.avatar-cell{ display:flex; align-items:center; gap:10px; }
.avatar-cell img{ width:48px; height:48px; object-fit:cover; border-radius:8px; border:1px solid #1b4aa0; background:#000022; }
.btn-upload{ background:#0d5d37; border:1px solid #168f59; color:#fff; border-radius:8px; padding:6px 10px; cursor:pointer; font-size:12px; }
.btn-upload[disabled]{ opacity:.55; cursor:not-allowed; }
.row-msg{ font-size:11px; color:#9dd; }
.row-msg.ok{ color:#7CFC00; }
.row-msg.err{ color:#FF6B6B; }
.table-scroll{ max-height:72vh; overflow:auto; border:1px solid #000088; border-radius:8px; }
@media (max-width:980px){
  .avatar-mass-filters{ grid-template-columns: 1fr; }
}
</style>

<div class="avatar-mass-wrap">
  <div class="avatar-mass-head">
    <h2>Avatares masivos de personajes</h2>
    <div style="color:#9dd;font-size:11px;">Sin paginacion - actualiza por fila via Ajax</div>
  </div>

  <div class="avatar-mass-filters">
    <select id="f-group">
      <option value="0">Grupo: Todos</option>
      <?php foreach ($groupOpts as $id=>$name): ?>
        <option value="<?= (int)$id ?>"><?= h($name) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="f-org">
      <option value="0">Organizacion: Todas</option>
      <?php foreach ($orgOpts as $id=>$name): ?>
        <option value="<?= (int)$id ?>"><?= h($name) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="f-chronicle">
      <option value="0">Cronica: Todas</option>
      <?php foreach ($chronOpts as $id=>$name): ?>
        <option value="<?= (int)$id ?>"><?= h($name) ?></option>
      <?php endforeach; ?>
    </select>
    <input id="f-search" type="text" placeholder="Buscar por nombre, id o pretty_id..." style="grid-column:1 / -1;">
  </div>

  <div class="table-scroll">
    <table class="avatar-mass-list" id="avatar-table">
      <thead>
        <tr>
          <th style="width:90px;">ID</th>
          <th style="width:170px;">Pretty ID</th>
          <th>Personaje</th>
          <th style="width:120px;">Avatar</th>
          <th style="width:360px;">Nuevo avatar</th>
          <th style="width:160px;">Estado</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($characters as $c):
          $id = (int)($c['id'] ?? 0);
          $pretty = (string)($c['pretty_id'] ?? '');
          $name = (string)($c['name'] ?? '');
          $img = (string)($c['image_url'] ?? '');
          $gid = (int)($c['group_id'] ?? 0);
          $oid = (int)($c['org_id'] ?? 0);
          $cid = (int)($c['chronicle_id'] ?? 0);
      ?>
        <tr data-group="<?= $gid ?>" data-org="<?= $oid ?>" data-chronicle="<?= $cid ?>" data-character-id="<?= $id ?>" data-search="<?= h(strtolower($name.' '.$id.' '.$pretty)) ?>">
          <td>#<?= $id ?></td>
          <td><?= $pretty !== '' ? h($pretty) : '-' ?></td>
          <td><?= h($name) ?></td>
          <td>
            <div class="avatar-cell">
              <img src="<?= h($img !== '' ? $img : '/img/characters/nada.png') ?>" alt="avatar" data-avatar-img>
            </div>
          </td>
          <td>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <input type="file" accept="image/*" data-avatar-file>
              <button class="btn-upload" data-upload-btn disabled>Subir</button>
            </div>
          </td>
          <td><span class="row-msg" data-row-msg>-</span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function(){
  var table = document.getElementById('avatar-table');
  var fGroup = document.getElementById('f-group');
  var fOrg = document.getElementById('f-org');
  var fChron = document.getElementById('f-chronicle');
  var fSearch = document.getElementById('f-search');

  if (!table || !fGroup || !fOrg || !fChron || !fSearch) return;

  function applyFilters(){
    var groupVal = parseInt(fGroup.value || '0', 10) || 0;
    var orgVal = parseInt(fOrg.value || '0', 10) || 0;
    var chronVal = parseInt(fChron.value || '0', 10) || 0;
    var q = String(fSearch.value || '').trim().toLowerCase();

    table.querySelectorAll('tbody tr').forEach(function(tr){
      var rg = parseInt(tr.getAttribute('data-group') || '0', 10) || 0;
      var ro = parseInt(tr.getAttribute('data-org') || '0', 10) || 0;
      var rc = parseInt(tr.getAttribute('data-chronicle') || '0', 10) || 0;
      var rs = String(tr.getAttribute('data-search') || '');

      var ok = true;
      if (groupVal > 0 && rg !== groupVal) ok = false;
      if (orgVal > 0 && ro !== orgVal) ok = false;
      if (chronVal > 0 && rc !== chronVal) ok = false;
      if (q !== '' && rs.indexOf(q) === -1) ok = false;

      tr.style.display = ok ? '' : 'none';
    });
  }

  [fGroup, fOrg, fChron].forEach(function(el){ el.addEventListener('change', applyFilters); });
  fSearch.addEventListener('input', applyFilters);

  table.querySelectorAll('tbody tr').forEach(function(tr){
    var fileInput = tr.querySelector('[data-avatar-file]');
    var uploadBtn = tr.querySelector('[data-upload-btn]');
    var msg = tr.querySelector('[data-row-msg]');
    var img = tr.querySelector('[data-avatar-img]');
    var charId = parseInt(tr.getAttribute('data-character-id') || '0', 10) || 0;

    if (!fileInput || !uploadBtn || !msg || !img || !charId) return;

    fileInput.addEventListener('change', function(){
      uploadBtn.disabled = !(fileInput.files && fileInput.files[0]);
      msg.textContent = uploadBtn.disabled ? '-' : 'Listo para subir';
      msg.className = 'row-msg';
    });

    uploadBtn.addEventListener('click', async function(){
      if (!fileInput.files || !fileInput.files[0] || !charId) return;

      uploadBtn.disabled = true;
      msg.textContent = 'Subiendo...';
      msg.className = 'row-msg';

      var fd = new FormData();
      fd.append('ajax', 'upload_avatar');
      fd.append('character_id', String(charId));
      fd.append('avatar', fileInput.files[0]);

      try {
        var endpoint = '/talim?s=admin_avatar_mass&ajax=1';
        var res = await fetch(endpoint, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        var raw = await res.text();
        var json = null;
        try {
          json = raw ? JSON.parse(raw) : null;
        } catch (parseErr) {
          console.error('[admin_avatar_mass] Non-JSON response', {
            endpoint: endpoint,
            status: res.status,
            statusText: res.statusText,
            bodyPreview: String(raw || '').slice(0, 1200)
          });
          msg.textContent = 'Respuesta invalida (' + res.status + ')';
          msg.className = 'row-msg err';
          return;
        }

        if (!res.ok) {
          var serverMsg = (json && json.msg) ? json.msg : ('HTTP ' + res.status + ' ' + res.statusText);
          console.error('[admin_avatar_mass] HTTP error', {
            endpoint: endpoint,
            status: res.status,
            statusText: res.statusText,
            response: json
          });
          msg.textContent = serverMsg;
          msg.className = 'row-msg err';
          return;
        }

        if (json && json._debug_noise) {
          console.warn('[admin_avatar_mass] Server noise captured', {
            endpoint: endpoint,
            characterId: charId,
            noise: json._debug_noise
          });
        }

        if (json && json.ok) {
          if (json.url) {
            img.src = json.url + '?t=' + Date.now();
          }
          msg.textContent = 'Actualizado';
          msg.className = 'row-msg ok';
          fileInput.value = '';
        } else {
          msg.textContent = (json && json.msg) ? json.msg : 'Error';
          msg.className = 'row-msg err';
          console.error('[admin_avatar_mass] Upload failed', {
            endpoint: endpoint,
            characterId: charId,
            response: json
          });
        }
      } catch (e) {
        msg.textContent = 'Error de red: ' + (e && e.message ? e.message : 'desconocido');
        msg.className = 'row-msg err';
        console.error('[admin_avatar_mass] Network/JS error', {
          characterId: charId,
          error: e
        });
      } finally {
        uploadBtn.disabled = true;
      }
    });
  });
})();
</script>
