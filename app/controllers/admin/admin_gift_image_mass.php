<?php
// admin_gift_image_mass.php - Actualizacion masiva de imagenes de dones.

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (method_exists($link, 'set_charset')) {
    $link->set_charset('utf8mb4');
} else {
    mysqli_set_charset($link, 'utf8mb4');
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_gift_image_mass';
$ADMIN_CSRF_TOKEN = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
$DOCROOT = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__), '/\\');
$GIFT_UPLOAD_DIR = $DOCROOT . '/public/img/gifts';
$GIFT_URL_BASE = '/img/gifts';
if (!is_dir($GIFT_UPLOAD_DIR)) {
    @mkdir($GIFT_UPLOAD_DIR, 0775, true);
}

function hg_gift_image_mass_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hg_gift_image_mass_plain_text(string $value): string
{
    $value = preg_replace('~<\s*br\s*/?\s*>~i', "\n", $value);
    $value = preg_replace('~<\s*li\b[^>]*>~i', '- ', (string)$value);
    $value = preg_replace('~</\s*(p|div|li|ul|ol|h[1-6])\s*>~i', "\n", (string)$value);
    $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace("/[ \t]+\n/u", "\n", (string)$value);
    $value = preg_replace("/\n{3,}/u", "\n\n", (string)$value);
    return trim((string)$value);
}

function hg_gift_image_mass_copy_prompt(array $gift): string
{
    $imagePrompt = 'Genera una imagen cuadrada 1:1 para representar el siguiente don como icono de habilidad. '
        . 'Utiliza un estilo de dibujo digital fantastico, dramatico y legible a tamano pequeno, inspirado en los iconos '
        . 'de habilidades de World of Warcraft o Lineage II. La escena debe comunicar visualmente el efecto principal del don. '
        . 'No incluyas texto, simbolos tipograficos, marco, borde ni interfaz; la ilustracion debe ocupar todo el lienzo. '
        . 'No leas la memoria del proyecto, no hace falta para esta tarea.';

    $typeParts = array_values(array_unique(array_filter([
        trim((string)($gift['kind_name'] ?? $gift['kind'] ?? '')),
        trim((string)($gift['gift_group'] ?? '')),
    ], static function ($value): bool {
        return $value !== '';
    })));

    $lines = [
        $imagePrompt,
        '',
        'Nombre: ' . trim((string)($gift['name'] ?? '')),
        'Rango: ' . trim((string)($gift['rank'] ?? '')),
        'Tipo: ' . implode(' - ', $typeParts),
    ];

    $systemName = trim((string)($gift['system_name'] ?? ''));
    if ($systemName !== '') {
        $lines[] = 'Sistema de juego: ' . $systemName;
    }

    $lines[] = '';
    $lines[] = 'Descripcion:';
    $lines[] = hg_gift_image_mass_plain_text((string)($gift['description'] ?? ''));
    $lines[] = '';
    $lines[] = 'Sistema / mecanicas:';
    $lines[] = hg_gift_image_mass_plain_text((string)($gift['mechanics_text'] ?? ''));

    return implode("\n", $lines);
}

function hg_gift_image_mass_save_upload(array $file, string $uploadDir, string $urlBase): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'msg' => 'Selecciona una imagen.'];
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'msg' => 'Error de subida (#' . (int)$file['error'] . ').'];
    }
    if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['ok' => false, 'msg' => 'El archivo supera 5 MB.'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'msg' => 'La subida no es valida.'];
    }

    $mime = '';
    if (function_exists('finfo_open')) {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo) {
            $mime = (string)finfo_file($fileInfo, $tmp);
            finfo_close($fileInfo);
        }
    }
    if ($mime === '') {
        $imageInfo = @getimagesize($tmp);
        $mime = (string)($imageInfo['mime'] ?? '');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'msg' => 'Formato no permitido (JPG/PNG/GIF/WebP).'];
    }

    try {
        $suffix = bin2hex(random_bytes(3));
    } catch (Exception $e) {
        $suffix = substr(md5(uniqid('', true)), 0, 6);
    }

    $filename = 'gift-' . date('YmdHis') . '-' . $suffix . '.' . $allowed[$mime];
    $destination = rtrim($uploadDir, '/\\') . '/' . $filename;
    if (!@move_uploaded_file($tmp, $destination)) {
        return ['ok' => false, 'msg' => 'No se pudo mover la imagen subida.'];
    }
    @chmod($destination, 0644);

    return [
        'ok' => true,
        'url' => rtrim($urlBase, '/') . '/' . $filename,
        'path' => $destination,
    ];
}

function hg_gift_image_mass_unlink(string $relativeUrl, string $uploadDir): void
{
    if ($relativeUrl === '') {
        return;
    }

    $docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__), '/\\');
    $relative = '/' . ltrim($relativeUrl, '/');
    $absolute = strpos($relative, '/img/') === 0
        ? $docroot . '/public' . $relative
        : $docroot . $relative;

    $base = realpath($uploadDir);
    $target = @realpath($absolute);
    if ($base === false || $target === false || !is_file($target)) {
        return;
    }

    $basePrefix = rtrim($base, '/\\') . DIRECTORY_SEPARATOR;
    if (strpos($target, $basePrefix) === 0) {
        @unlink($target);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (string)($_GET['action'] ?? '') === 'copy_prompt') {
    hg_admin_require_session(true);

    $giftId = (int)($_GET['gift_id'] ?? 0);
    if ($giftId <= 0) {
        hg_admin_json_error('Don no valido.', 422, ['gift_id' => 'required']);
    }

    $statement = $link->prepare(
        'SELECT g.name, g.rank, g.kind, COALESCE(t.name, g.kind) AS kind_name, g.gift_group, g.system_name, g.description, g.mechanics_text
         FROM fact_gifts g
         LEFT JOIN dim_gift_types t ON t.id = CAST(g.kind AS UNSIGNED)
         WHERE g.id = ?
         LIMIT 1'
    );
    if (!$statement) {
        hg_admin_json_error('No se pudo preparar la consulta del don.', 500);
    }
    $statement->bind_param('i', $giftId);
    $statement->execute();
    $result = $statement->get_result();
    $gift = $result ? $result->fetch_assoc() : null;
    $statement->close();
    if (!$gift) {
        hg_admin_json_error('El don no existe.', 404);
    }

    hg_admin_json_success(
        ['id' => $giftId, 'text' => hg_gift_image_mass_copy_prompt($gift)],
        'Texto preparado.'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'upload_image') {
    hg_admin_require_session(true);

    $csrfToken = hg_admin_extract_csrf_token($_POST);
    if (!hg_admin_csrf_valid($csrfToken, $ADMIN_CSRF_SESSION_KEY)) {
        hg_admin_json_error('CSRF invalido.', 403, ['csrf' => 'invalid_token']);
    }

    $giftId = (int)($_POST['gift_id'] ?? 0);
    if ($giftId <= 0) {
        hg_admin_json_error('Don no valido.', 422, ['gift_id' => 'required']);
    }

    $giftName = '';
    $currentImage = '';
    $statement = $link->prepare('SELECT name, image_url FROM fact_gifts WHERE id = ? LIMIT 1');
    if (!$statement) {
        hg_admin_json_error('No se pudo preparar la consulta del don.', 500);
    }
    $statement->bind_param('i', $giftId);
    $statement->execute();
    $result = $statement->get_result();
    $gift = $result ? $result->fetch_assoc() : null;
    $statement->close();
    if (!$gift) {
        hg_admin_json_error('El don no existe.', 404);
    }
    $giftName = (string)($gift['name'] ?? '');
    $currentImage = (string)($gift['image_url'] ?? '');

    $upload = hg_gift_image_mass_save_upload($_FILES['gift_image'] ?? [], $GIFT_UPLOAD_DIR, $GIFT_URL_BASE);
    if (empty($upload['ok'])) {
        hg_admin_json_error((string)($upload['msg'] ?? 'No se pudo subir la imagen.'), 422);
    }

    $newImage = (string)$upload['url'];
    $statement = $link->prepare('UPDATE fact_gifts SET image_url = ? WHERE id = ?');
    if (!$statement) {
        hg_gift_image_mass_unlink($newImage, $GIFT_UPLOAD_DIR);
        hg_admin_json_error('No se pudo preparar la actualizacion del don.', 500);
    }
    $statement->bind_param('si', $newImage, $giftId);
    if (!$statement->execute()) {
        $statement->close();
        hg_gift_image_mass_unlink($newImage, $GIFT_UPLOAD_DIR);
        hg_admin_json_error('No se pudo actualizar la imagen del don.', 500);
    }
    $statement->close();

    if ($currentImage !== '' && $currentImage !== $newImage) {
        hg_gift_image_mass_unlink($currentImage, $GIFT_UPLOAD_DIR);
    }

    hg_admin_json_success(
        ['id' => $giftId, 'name' => $giftName, 'url' => $newImage],
        'Imagen actualizada.'
    );
}

$gifts = [];
$query = "
    SELECT g.id, g.pretty_id, g.name, g.image_url, g.kind,
           COALESCE(t.name, g.kind) AS kind_name, g.gift_group, g.rank, g.system_name,
           COALESCE(owners.character_count, 0) AS character_count
    FROM fact_gifts g
    LEFT JOIN dim_gift_types t ON t.id = CAST(g.kind AS UNSIGNED)
    LEFT JOIN (
        SELECT power_id, COUNT(DISTINCT character_id) AS character_count
        FROM bridge_characters_powers
        WHERE power_kind = 'dones'
        GROUP BY power_id
    ) owners ON owners.power_id = g.id
    ORDER BY g.name ASC, g.id ASC
";
if ($result = $link->query($query)) {
    while ($row = $result->fetch_assoc()) {
        $gifts[] = $row;
    }
    $result->close();
}

$kindOptions = [];
$groupOptions = [];
$rankOptions = [];
foreach ($gifts as $gift) {
    $kind = trim((string)($gift['kind_name'] ?? $gift['kind'] ?? ''));
    $group = trim((string)($gift['gift_group'] ?? ''));
    $rank = trim((string)($gift['rank'] ?? ''));
    if ($kind !== '') {
        $kindOptions[$kind] = $kind;
    }
    if ($group !== '') {
        $groupOptions[$group] = $group;
    }
    if ($rank !== '') {
        $rankOptions[$rank] = $rank;
    }
}
natcasesort($kindOptions);
natcasesort($groupOptions);
uksort($rankOptions, 'strnatcasecmp');
?>

<?php include_once(__DIR__ . '/../../partials/datatable_assets.php'); ?>

<div class="avatar-mass-wrap">
  <div class="avatar-mass-head">
    <h2>Imagen dones masivos</h2>
    <div class="adm-text-9dd-11">Subida por fila, sin paginacion</div>
  </div>

  <div class="avatar-mass-filters">
    <select id="gift-image-kind">
      <option value="">Tipo: Todos</option>
      <?php foreach ($kindOptions as $kind): ?>
        <option value="<?= hg_gift_image_mass_h(strtolower($kind)) ?>"><?= hg_gift_image_mass_h($kind) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="gift-image-group">
      <option value="">Grupo: Todos</option>
      <?php foreach ($groupOptions as $group): ?>
        <option value="<?= hg_gift_image_mass_h(strtolower($group)) ?>"><?= hg_gift_image_mass_h($group) ?></option>
      <?php endforeach; ?>
    </select>
    <select id="gift-image-rank">
      <option value="">Rango: Todos</option>
      <?php foreach ($rankOptions as $rank): ?>
        <option value="<?= hg_gift_image_mass_h(strtolower($rank)) ?>"><?= hg_gift_image_mass_h($rank) ?></option>
      <?php endforeach; ?>
    </select>
    <input id="gift-image-search" class="adm-grid-full" type="text" placeholder="Buscar por nombre, id o pretty_id...">
  </div>

  <div class="table-scroll">
    <table class="avatar-mass-list display" id="gift-image-table">
      <thead>
        <tr>
          <th class="adm-w-90">ID</th>
          <th class="adm-w-170">Pretty ID</th>
          <th>Don</th>
          <th>Tipo</th>
          <th>Grupo</th>
          <th>Rango</th>
          <th class="adm-w-90">Personajes</th>
          <th class="adm-w-160">Para icono</th>
          <th class="adm-w-120">Imagen</th>
          <th class="adm-w-360">Nueva imagen</th>
          <th class="adm-w-160">Estado</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($gifts as $gift):
          $id = (int)($gift['id'] ?? 0);
          $prettyId = (string)($gift['pretty_id'] ?? '');
          $name = (string)($gift['name'] ?? '');
          $image = trim((string)($gift['image_url'] ?? ''));
          $kind = (string)($gift['kind_name'] ?? $gift['kind'] ?? '');
          $group = (string)($gift['gift_group'] ?? '');
          $rank = (string)($gift['rank'] ?? '');
          $characterCount = (int)($gift['character_count'] ?? 0);
          $searchText = strtolower($name . ' ' . $id . ' ' . $prettyId . ' ' . $kind . ' ' . $group . ' ' . $rank);
      ?>
        <tr
          data-gift-id="<?= $id ?>"
          data-kind="<?= hg_gift_image_mass_h(strtolower($kind)) ?>"
          data-group="<?= hg_gift_image_mass_h(strtolower($group)) ?>"
          data-rank="<?= hg_gift_image_mass_h(strtolower($rank)) ?>"
          data-search="<?= hg_gift_image_mass_h($searchText) ?>"
        >
          <td data-order="<?= $id ?>">#<?= $id ?></td>
          <td><?= $prettyId !== '' ? hg_gift_image_mass_h($prettyId) : '-' ?></td>
          <td>
            <?= hg_gift_image_mass_h($name) ?>
          </td>
          <td><?= $kind !== '' ? hg_gift_image_mass_h($kind) : '-' ?></td>
          <td><?= $group !== '' ? hg_gift_image_mass_h($group) : '-' ?></td>
          <td data-order="<?= hg_gift_image_mass_h($rank) ?>"><?= $rank !== '' ? hg_gift_image_mass_h($rank) : '-' ?></td>
          <td data-order="<?= $characterCount ?>"><?= $characterCount ?></td>
          <td>
            <button class="btn-upload" type="button" data-copy-btn>Copiar datos</button>
          </td>
          <td>
            <div class="avatar-cell">
              <img<?= $image !== '' ? ' src="' . hg_gift_image_mass_h($image) . '"' : '' ?> alt="" data-gift-img <?= $image === '' ? 'hidden' : '' ?>>
              <span data-gift-empty <?= $image !== '' ? 'hidden' : '' ?>>Sin imagen</span>
            </div>
          </td>
          <td>
            <div class="adm-flex-wrap-8">
              <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-gift-file>
              <button class="btn-upload" type="button" data-upload-btn disabled>Subir</button>
            </div>
          </td>
          <td><span class="row-msg" data-row-msg>-</span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="/assets/js/admin/admin-http.js"></script>
<script>
window.jQuery(function(){
  window.ADMIN_CSRF_TOKEN = <?= json_encode((string)$ADMIN_CSRF_TOKEN, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  var endpoint = '/talim?s=admin_gift_image_mass&ajax=1';
  var table = document.getElementById('gift-image-table');
  var kindFilter = document.getElementById('gift-image-kind');
  var groupFilter = document.getElementById('gift-image-group');
  var rankFilter = document.getElementById('gift-image-rank');
  var searchFilter = document.getElementById('gift-image-search');
  var dataTable = null;
  if (!table || !kindFilter || !groupFilter || !rankFilter || !searchFilter) return;

  function matchesFilters(row, includeSearch){
    var kind = String(kindFilter.value || '');
    var group = String(groupFilter.value || '');
    var rank = String(rankFilter.value || '');
    var search = String(searchFilter.value || '').trim().toLowerCase();
    return (!kind || row.getAttribute('data-kind') === kind)
      && (!group || row.getAttribute('data-group') === group)
      && (!rank || row.getAttribute('data-rank') === rank)
      && (!includeSearch || !search || String(row.getAttribute('data-search') || '').indexOf(search) !== -1);
  }

  function applyFilters(){
    if (dataTable) {
      dataTable.draw();
      return;
    }
    table.querySelectorAll('tbody tr').forEach(function(row){
      var matches = matchesFilters(row, true);
      row.style.display = matches ? '' : 'none';
    });
  }

  kindFilter.addEventListener('change', applyFilters);
  groupFilter.addEventListener('change', applyFilters);
  rankFilter.addEventListener('change', applyFilters);
  searchFilter.addEventListener('input', applyFilters);

  function legacyCopyText(text){
    return new Promise(function(resolve, reject){
      var input = document.createElement('textarea');
      input.value = text;
      input.setAttribute('readonly', 'readonly');
      input.style.position = 'fixed';
      input.style.left = '-9999px';
      document.body.appendChild(input);
      input.focus();
      input.select();
      try {
        if (!document.execCommand('copy')) throw new Error('No se pudo copiar.');
        resolve();
      } catch (error) {
        reject(error);
      } finally {
        document.body.removeChild(input);
      }
    });
  }

  function copyText(text){
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      return navigator.clipboard.writeText(text).catch(function(){
        return legacyCopyText(text);
      });
    }
    return legacyCopyText(text);
  }

  table.querySelectorAll('tbody tr').forEach(function(row){
    var giftId = parseInt(row.getAttribute('data-gift-id') || '0', 10) || 0;
    var fileInput = row.querySelector('[data-gift-file]');
    var uploadButton = row.querySelector('[data-upload-btn]');
    var copyButton = row.querySelector('[data-copy-btn]');
    var message = row.querySelector('[data-row-msg]');
    var image = row.querySelector('[data-gift-img]');
    var empty = row.querySelector('[data-gift-empty]');
    if (!giftId || !fileInput || !uploadButton || !copyButton || !message || !image || !empty) return;

    copyButton.addEventListener('click', function(){
      copyButton.disabled = true;
      message.textContent = 'Preparando copia...';
      message.className = 'row-msg';

      HGAdminHttp.request(endpoint + '&action=copy_prompt&gift_id=' + encodeURIComponent(String(giftId)), {
        method: 'GET',
        loadingEl: copyButton
      }).then(function(payload){
        var text = payload && payload.data ? String(payload.data.text || '') : '';
        if (!text) throw new Error('No se recibio texto para copiar.');
        return copyText(text);
      }).then(function(){
        message.textContent = 'Datos copiados';
        message.className = 'row-msg ok';
      }).catch(function(error){
        message.textContent = HGAdminHttp.errorMessage(error);
        message.className = 'row-msg err';
      }).finally(function(){
        copyButton.disabled = false;
      });
    });

    fileInput.addEventListener('change', function(){
      uploadButton.disabled = !(fileInput.files && fileInput.files[0]);
      message.textContent = uploadButton.disabled ? '-' : 'Lista para subir';
      message.className = 'row-msg';
    });

    uploadButton.addEventListener('click', function(){
      if (!fileInput.files || !fileInput.files[0]) return;

      var body = new FormData();
      body.append('action', 'upload_image');
      body.append('gift_id', String(giftId));
      body.append('gift_image', fileInput.files[0]);

      uploadButton.disabled = true;
      message.textContent = 'Subiendo...';
      message.className = 'row-msg';

      HGAdminHttp.request(endpoint, { method: 'POST', body: body, loadingEl: uploadButton })
        .then(function(payload){
          var data = payload && payload.data ? payload.data : {};
          if (data.url) {
            image.src = data.url + '?t=' + Date.now();
            image.hidden = false;
            empty.hidden = true;
          }
          fileInput.value = '';
          message.textContent = payload.message || 'Imagen actualizada';
          message.className = 'row-msg ok';
        })
        .catch(function(error){
          message.textContent = HGAdminHttp.errorMessage(error);
          message.className = 'row-msg err';
          uploadButton.disabled = !(fileInput.files && fileInput.files[0]);
        });
    });
  });

  if (window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) {
    window.jQuery.fn.dataTable.ext.search.push(function(settings, data, dataIndex){
      if (settings.nTable !== table) return true;
      var row = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
      return row ? matchesFilters(row, true) : true;
    });

    dataTable = window.jQuery(table).DataTable({
      pageLength: 50,
      lengthMenu: [25, 50, 100, 250],
      order: [[2, 'asc']],
      searching: true,
      dom: 'ltip',
      columnDefs: [
        { orderable: false, targets: [7, 8, 9, 10] }
      ]
    });
  }
});
</script>
