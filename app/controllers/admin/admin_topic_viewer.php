<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (method_exists($link, 'set_charset')) {
    $link->set_charset('utf8mb4');
} else {
    mysqli_set_charset($link, 'utf8mb4');
}

include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../domains/chapters/admin_topic_viewer.php');

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!hg_topic_viewer_table_exists($link)) {
    echo "<div class='panel-wrap'><div class='hdr'><h2>Temas de visor de foro</h2><a class='btn' href='/talim'>&larr; Panel</a></div>";
    echo "<p class='adm-admin-error'>Falta la tabla <code>fact_tools_topic_viewer</code> en esta base de datos.</p></div>";
    return;
}

$hasChapterIdCol = hg_topic_viewer_column_exists($link, 'fact_tools_topic_viewer', 'chapter_id');
$hasScopeTypeCol = hg_topic_viewer_column_exists($link, 'fact_tools_topic_viewer', 'link_scope_type');
$hasScopeIdCol = hg_topic_viewer_column_exists($link, 'fact_tools_topic_viewer', 'link_scope_id');
$supportsEpisodeAndScope = $hasChapterIdCol && $hasScopeTypeCol && $hasScopeIdCol;

$csrfKey = 'csrf_admin_topic_viewer';
$csrf = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($csrfKey)
    : (empty($_SESSION[$csrfKey]) ? ($_SESSION[$csrfKey] = bin2hex(random_bytes(16))) : $_SESSION[$csrfKey]);

$flash = [];
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    $validCsrf = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid((string)$token, $csrfKey)
        : (is_string($token) && $token !== '' && isset($_SESSION[$csrfKey]) && hash_equals($_SESSION[$csrfKey], $token));

    if (!$validCsrf) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF inválido. Recarga la página.'];
    } else {
        $action = (string)$_POST['crud_action'];

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $result = hg_topic_viewer_delete($link, $id);
            $flash[] = ['type'=>!empty($result['ok'])?'ok':'error','msg'=>(string)$result['message']];
        }

        if ($action === 'move') {
            $id = (int)($_POST['id'] ?? 0);
            $direction = (string)($_POST['direction'] ?? '');
            $result = hg_topic_viewer_move($link, $id, $direction, $supportsEpisodeAndScope);
            $flash[] = ['type'=>!empty($result['ok'])?'ok':'error','msg'=>(string)$result['message']];
        }

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $topicName = trim((string)($_POST['topic_name'] ?? ''));
            $topicId = (int)($_POST['topic_id'] ?? 0);
            $topicUrl = trim((string)($_POST['topic_url'] ?? ''));
            $topicDescription = trim((string)($_POST['topic_description'] ?? ''));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $isActive = ((string)($_POST['is_active'] ?? '1') === '1') ? 1 : 0;
            $chapterId = (int)($_POST['chapter_id'] ?? 0);
            $scopeType = trim((string)($_POST['link_scope_type'] ?? ''));
            $scopeId = (int)($_POST['link_scope_id'] ?? 0);
            $allowedScopeTypes = ['', 'character', 'group', 'organization'];

            if ($topicName === '' || $topicId <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'Nombre y topic_id son obligatorios.'];
                $editId = $id;
            } elseif ($hasScopeTypeCol && !in_array($scopeType, $allowedScopeTypes, true)) {
                $flash[] = ['type' => 'error', 'msg' => 'Tipo de agrupación inválido.'];
                $editId = $id;
            } elseif ($hasScopeTypeCol && $hasScopeIdCol && $scopeType !== '' && $scopeId <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'Si eliges tipo de agrupación, link_scope_id debe ser > 0.'];
                $editId = $id;
            } else {
                $result = hg_topic_viewer_save($link, $id, [
                    'topic_name'=>$topicName,
                    'topic_id'=>$topicId,
                    'topic_url'=>$topicUrl,
                    'topic_description'=>$topicDescription,
                    'sort_order'=>$sortOrder,
                    'is_active'=>$isActive,
                    'chapter_id'=>$chapterId,
                    'scope_type'=>$scopeType,
                    'scope_id'=>$scopeId,
                ], $supportsEpisodeAndScope);
                $flash[] = ['type'=>!empty($result['ok'])?'ok':'error','msg'=>(string)$result['message']];
                $editId = !empty($result['ok']) ? 0 : $id;
            }
        }
    }
}

$editRow = [
    'id' => 0,
    'topic_name' => '',
    'topic_id' => '',
    'topic_url' => '',
    'topic_description' => '',
    'sort_order' => 0,
    'is_active' => 1,
    'chapter_id' => 0,
    'link_scope_type' => '',
    'link_scope_id' => 0,
];
if ($editId > 0) {
    $loaded = hg_topic_viewer_fetch_row($link, $editId);
    if ($loaded) $editRow = $loaded;
}

$hasFlashError = false;
foreach ($flash as $flashItem) {
    if (($flashItem['type'] ?? '') === 'error') {
        $hasFlashError = true;
        break;
    }
}
if (
    $hasFlashError
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string)($_POST['crud_action'] ?? '') === 'save'
) {
    $editRow = array_merge($editRow, [
        'id' => (int)($_POST['id'] ?? 0),
        'topic_name' => (string)($_POST['topic_name'] ?? ''),
        'topic_id' => (string)($_POST['topic_id'] ?? ''),
        'topic_url' => (string)($_POST['topic_url'] ?? ''),
        'topic_description' => (string)($_POST['topic_description'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'is_active' => ((string)($_POST['is_active'] ?? '1') === '1') ? 1 : 0,
        'chapter_id' => (int)($_POST['chapter_id'] ?? 0),
        'link_scope_type' => (string)($_POST['link_scope_type'] ?? ''),
        'link_scope_id' => (int)($_POST['link_scope_id'] ?? 0),
    ]);
}

$chapterOptions = $hasChapterIdCol ? hg_topic_viewer_chapter_options($link) : [];
$rows = hg_topic_viewer_rows($link, $supportsEpisodeAndScope);
$totalTopics = count($rows);
$activeTopics = 0;
foreach ($rows as $rowCount) {
    if ((int)($rowCount['is_active'] ?? 0) === 1) {
        $activeTopics++;
    }
}
$inactiveTopics = max(0, $totalTopics - $activeTopics);
$openTopicModal = ($editId > 0) || (
    $hasFlashError
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string)($_POST['crud_action'] ?? '') === 'save'
);
?>



<div class="panel-wrap topic-viewer-admin">
  <div class="hdr">
    <h2>Temas de visor de foro</h2>
    <button class="btn btn-green" type="button" id="topicNewBtn">+ Nuevo tema</button>
    <a class="btn" href="/talim">&larr; Panel</a>
    <div class="topic-toolbar">
      <label>Filtro rápido
        <input class="inp" type="text" id="quickFilterTopicViewer" placeholder="Nombre, topic_id, episodio...">
      </label>
    </div>
  </div>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = ($m['type'] ?? '') === 'ok' ? 'ok' : 'err'; ?>
        <div class="<?= $cl ?>"><?= h($m['msg'] ?? '') ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$supportsEpisodeAndScope): ?>
<div class="flash">
    <div class="err">Faltan columnas (`chapter_id`, `link_scope_type`, `link_scope_id`) en `fact_tools_topic_viewer`. El panel funcionará en modo reducido.</div>
</div>
<?php endif; ?>

  <div class="topic-stats">
    <span class="topic-stat">Total <?= (int)$totalTopics ?></span>
    <span class="topic-stat">Activos <?= (int)$activeTopics ?></span>
    <span class="topic-stat">Inactivos <?= (int)$inactiveTopics ?></span>
  </div>  <div class="topic-order-guide">
    <strong>Cómo se muestra el visor:</strong> primero separa por agrupación (personaje, grupo u organización) y después usa la posición de cada tema. Usa <strong>↑</strong> y <strong>↓</strong> para reordenar sin calcular números; el campo “Posición” queda como alternativa avanzada.
  </div>

<div class="topic-table-wrap">
<table class="table" id="topicViewerTable">
    <thead>
        <tr>
            <th class="adm-w-60">ID</th>
            <th>Nombre</th>
            <th class="adm-w-80">topic_id</th>
            <?php if ($supportsEpisodeAndScope): ?>
                <th>Episodio</th>
                <th>Agrupación</th>
            <?php endif; ?>
            <th>URL</th>
            <th class="adm-w-80">Orden</th>
            <th class="adm-w-80">Estado</th>
            <th class="adm-w-160">Fechas</th>
            <th class="adm-w-160">Acciones</th>
        </tr>
    </thead>
    <tbody id="topicViewerBody">
    <?php foreach ($rows as $r): ?>
        <?php
            $search = trim(
                (string)$r['topic_name']
                . ' ' . (string)$r['topic_id']
                . ' ' . (string)$r['topic_url']
                . ' ' . (string)($r['chapter_name'] ?? '')
                . ' ' . (string)($r['season_name'] ?? '')
                . ' ' . (string)($r['link_scope_type'] ?? '')
                . ' ' . (string)($r['link_scope_id'] ?? '')
            );
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
            else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><strong class="adm-color-accent"><?= (int)$r['id'] ?></strong></td>
            <td class="topic-name-cell">
                <strong><?= h($r['topic_name']) ?></strong>
                <?php if (trim((string)$r['topic_description']) !== ''): ?>
                    <div class="adm-color-muted small topic-desc"><?= h($r['topic_description']) ?></div>
                <?php endif; ?>
            </td>
            <td><?= (int)$r['topic_id'] ?></td>
            <?php if ($supportsEpisodeAndScope): ?>
                <td class="topic-episode-cell">
                    <?php
                        $chapterTxt = '';
                        $sName = trim((string)($r['season_name'] ?? ''));
                        $sNum = (int)($r['season_number'] ?? 0);
                        $cNum = (int)($r['chapter_number'] ?? 0);
                        $cName = trim((string)($r['chapter_name'] ?? ''));
                        if ($sName !== '') {
                            $chapterTxt = $sName;
                            if ($sNum > 0) { $chapterTxt .= ' (T' . $sNum . ')'; }
                        }
                        if ($cNum > 0) { $chapterTxt .= ($chapterTxt !== '' ? ' · ' : '') . 'Ep. ' . $cNum; }
                        if ($cName !== '') { $chapterTxt .= ($chapterTxt !== '' ? ' · ' : '') . $cName; }
                        if ($chapterTxt === '') { $chapterTxt = '(sin capítulo)'; }
                    ?>
                    <?= h($chapterTxt) ?>
                </td>
                <td>
                    <?php
                        $scopeType = trim((string)($r['link_scope_type'] ?? ''));
                        $scopeId = (int)($r['link_scope_id'] ?? 0);
                        if ($scopeType === '' || $scopeId <= 0) {
                            echo '<span class="adm-color-muted">(sin agrupación)</span>';
                        } else {
                            $scopeLabel = ($scopeType === 'character') ? 'Personaje' : (($scopeType === 'group') ? 'Grupo' : (($scopeType === 'organization') ? 'Organización' : $scopeType));
                            echo h($scopeLabel . ' #' . $scopeId);
                        }
                    ?>
                </td>
            <?php endif; ?>
            <td>
                <?php if (trim((string)$r['topic_url']) !== ''): ?>
                    <a href="<?= h($r['topic_url']) ?>" target="_blank" rel="noopener noreferrer">Abrir</a>
                <?php else: ?>
                    <span class="adm-color-muted">(vacío)</span>
                <?php endif; ?>
            </td>
            <td class="topic-order-cell">
                <span class="topic-order-number">Pos. <?= (int)$r['sort_order'] ?></span>
                <span class="topic-order-move" aria-label="Cambiar posición">
                  <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="crud_action" value="move"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="direction" value="up"><button class="btn" type="submit" title="Subir dentro de su agrupación" aria-label="Subir dentro de su agrupación">↑</button></form>
                  <form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="crud_action" value="move"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="direction" value="down"><button class="btn" type="submit" title="Bajar dentro de su agrupación" aria-label="Bajar dentro de su agrupación">↓</button></form>
                </span>
            </td>
            <td>
                <span class="topic-status <?= ((int)$r['is_active'] === 1) ? '' : 'off' ?>">
                    <?= ((int)$r['is_active'] === 1) ? 'Activo' : 'Inactivo' ?>
                </span>
            </td>
            <td>
                <div>Alta: <?= h((string)($r['created_at'] ?? '')) ?></div>
                <div>Mod: <?= h((string)($r['updated_at'] ?? '')) ?></div>
            </td>
            <td>
                <div class="topic-actions">
                <button class="btn" type="button"
                    data-topic-edit="1"
                    data-id="<?= (int)$r['id'] ?>"
                    data-topic-name="<?= h($r['topic_name']) ?>"
                    data-topic-id="<?= (int)$r['topic_id'] ?>"
                    data-topic-url="<?= h($r['topic_url']) ?>"
                    data-topic-description="<?= h($r['topic_description']) ?>"
                    data-sort-order="<?= (int)$r['sort_order'] ?>"
                    data-is-active="<?= (int)$r['is_active'] ?>"
                    data-chapter-id="<?= (int)($r['chapter_id'] ?? 0) ?>"
                    data-link-scope-type="<?= h((string)($r['link_scope_type'] ?? '')) ?>"
                    data-link-scope-id="<?= (int)($r['link_scope_id'] ?? 0) ?>"
                >Editar</button>
                <a class="btn topic-preview-link" href="/tools/forum-topic-viewer?id_topic=<?= (int)$r['topic_id'] ?>" target="_blank" rel="noopener noreferrer">Ver visor</a>
                <form method="post" onsubmit="return confirm('¿Borrar este tema?');">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="crud_action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-red" type="submit">Borrar</button>
                </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="<?= $supportsEpisodeAndScope ? '10' : '8' ?>" class="adm-color-muted">(Sin temas configurados)</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
</div>

<div class="modal-back topic-viewer-admin" id="topicModal">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="topicModalTitle">
    <h3 id="topicModalTitle"><?= ((int)$editRow['id'] > 0) ? 'Editar tema' : 'Nuevo tema' ?></h3>
    <form method="post" id="topicForm" class="topic-modal-form adm-m-0">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="crud_action" value="save">
      <input type="hidden" name="id" id="f_topic_row_id" value="<?= (int)($editRow['id'] ?? 0) ?>">

      <div class="topic-modal-body">
        <div class="topic-modal-section">1. Tema del foro</div>
        <label>Nombre del tema
          <input class="inp" type="text" name="topic_name" id="f_topic_name" maxlength="180" required value="<?= h($editRow['topic_name'] ?? '') ?>">
        </label>

        <label>ID del hilo (topic_id)
          <input class="inp" type="number" min="1" name="topic_id" id="f_topic_id" required value="<?= h((string)($editRow['topic_id'] ?? '')) ?>">
          <small class="topic-field-help">Al pegar una URL del foro se propone automáticamente su ID.</small>
        </label>

        <div class="topic-modal-section">2. Contexto</div>
        <?php if ($hasChapterIdCol): ?>
        <label class="field-full">Episodio
          <select class="select" name="chapter_id" id="f_chapter_id">
            <option value="">Sin episodio</option>
            <?php foreach ($chapterOptions as $ch): ?>
              <?php
                $cid = (int)($ch['id'] ?? 0);
                $sel = ((int)($editRow['chapter_id'] ?? 0) === $cid) ? 'selected' : '';
                $seasonName = trim((string)($ch['season_name'] ?? ''));
                $seasonNum = (int)($ch['season_number'] ?? 0);
                $chapterNum = (int)($ch['chapter_number'] ?? 0);
                $chapterName = trim((string)($ch['name'] ?? ''));
                $label = '';
                if ($seasonName !== '') {
                    $label = $seasonName;
                    if ($seasonNum > 0) { $label .= ' (T' . $seasonNum . ')'; }
                }
                if ($chapterNum > 0) { $label .= ($label !== '' ? ' · ' : '') . 'Ep. ' . $chapterNum; }
                if ($chapterName !== '') { $label .= ($label !== '' ? ' · ' : '') . $chapterName; }
                if ($label === '') { $label = 'Capítulo #' . $cid; }
              ?>
              <option value="<?= $cid ?>" <?= $sel ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php endif; ?>

        <label class="field-full">URL del tema en el foro
          <input class="inp" type="text" name="topic_url" id="f_topic_url" maxlength="255" value="<?= h($editRow['topic_url'] ?? '') ?>" placeholder="https://naufragio-foros.duckdns.org/index.php/topic,00.0.html">
        </label>

        <div class="topic-modal-section">3. Agrupación, visibilidad y posición</div>
        <label>Posición dentro de esta agrupación
          <input class="inp" type="number" min="0" name="sort_order" id="f_sort_order" value="<?= h((string)($editRow['sort_order'] ?? 0)) ?>">
          <small class="topic-field-help">Los valores bajos aparecen antes. Solo compite con los temas de la misma agrupación.</small>
        </label>

        <label>Estado
          <select class="select" name="is_active" id="f_is_active">
            <option value="1" <?= ((int)($editRow['is_active'] ?? 1) === 1) ? 'selected' : '' ?>>Activo</option>
            <option value="0" <?= ((int)($editRow['is_active'] ?? 1) === 0) ? 'selected' : '' ?>>Inactivo</option>
          </select>
        </label>

        <?php if ($hasScopeTypeCol): ?>
        <label>Agrupar por
          <?php $scopeTypeNow = trim((string)($editRow['link_scope_type'] ?? '')); ?>
          <select class="select" name="link_scope_type" id="f_link_scope_type">
            <option value="" <?= ($scopeTypeNow === '') ? 'selected' : '' ?>>Sin agrupación</option>
            <option value="character" <?= ($scopeTypeNow === 'character') ? 'selected' : '' ?>>Personaje</option>
            <option value="group" <?= ($scopeTypeNow === 'group') ? 'selected' : '' ?>>Grupo</option>
            <option value="organization" <?= ($scopeTypeNow === 'organization') ? 'selected' : '' ?>>Organización</option>
          </select>
        </label>
        <?php endif; ?>

        <?php if ($hasScopeIdCol): ?>
        <label>ID de la entidad
          <input class="inp" type="number" min="0" name="link_scope_id" id="f_link_scope_id" value="<?= h((string)($editRow['link_scope_id'] ?? 0)) ?>" placeholder="Ej: 110, 60, 20">
          <small class="topic-field-help">El ID identifica el personaje, grupo u organización escogido arriba. Sin agrupación, déjalo en 0.</small>
        </label>
        <?php endif; ?>

        <label class="field-full">Descripción
<textarea class="ta" name="topic_description" id="f_topic_description" rows="4"><?= h($editRow['topic_description'] ?? '') ?></textarea>
        </label>
      </div>

      <div class="modal-actions">
        <button class="btn btn-red" type="button" id="topicCancelBtn">Cancelar</button>
        <button class="btn btn-green" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
    var input = document.getElementById('quickFilterTopicViewer');
    var tbody = document.getElementById('topicViewerBody');
    if (!input || !tbody) return;
    input.addEventListener('input', function(){
        var q = String(input.value || '').toLowerCase();
        var rows = tbody.querySelectorAll('tr');
        for (var i = 0; i < rows.length; i++) {
            var hay = String(rows[i].getAttribute('data-search') || rows[i].textContent || '').toLowerCase();
            rows[i].style.display = hay.indexOf(q) !== -1 ? '' : 'none';
        }
    });

    var modal = document.getElementById('topicModal');
    var title = document.getElementById('topicModalTitle');
    var newBtn = document.getElementById('topicNewBtn');
    var cancelBtn = document.getElementById('topicCancelBtn');
    var form = document.getElementById('topicForm');
    if (!modal || !form) return;

    function setValue(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value == null ? '' : String(value);
    }

    var topicUrlInput = document.getElementById('f_topic_url');
    var topicIdInput = document.getElementById('f_topic_id');
    if (topicUrlInput && topicIdInput) {
        topicUrlInput.addEventListener('change', function(){
            var match = String(topicUrlInput.value || '').match(/topic,([0-9]+)/i);
            if (match && (!String(topicIdInput.value || '').trim() || window.confirm('¿Actualizar el ID del hilo con el de la URL?'))) {
                topicIdInput.value = match[1];
            }
        });
    }
    function openModal() {
        modal.style.display = 'flex';
        var name = document.getElementById('f_topic_name');
        if (name) window.setTimeout(function(){ name.focus(); }, 40);
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    function resetForm() {
        form.reset();
        setValue('f_topic_row_id', 0);
        setValue('f_topic_name', '');
        setValue('f_topic_id', '');
        setValue('f_chapter_id', '');
        setValue('f_topic_url', '');
        setValue('f_sort_order', 0);
        setValue('f_is_active', 1);
        setValue('f_link_scope_type', '');
        setValue('f_link_scope_id', 0);
        setValue('f_topic_description', '');
        if (title) title.textContent = 'Nuevo tema';
    }

    if (newBtn) {
        newBtn.addEventListener('click', function(){
            resetForm();
            openModal();
        });
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }
    modal.addEventListener('click', function(ev){
        if (ev.target === modal) closeModal();
    });
    document.addEventListener('keydown', function(ev){
        if (ev.key === 'Escape' && modal.style.display === 'flex') closeModal();
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-topic-edit="1"]'), function(btn){
        btn.addEventListener('click', function(){
            setValue('f_topic_row_id', btn.getAttribute('data-id') || '0');
            setValue('f_topic_name', btn.getAttribute('data-topic-name') || '');
            setValue('f_topic_id', btn.getAttribute('data-topic-id') || '');
            setValue('f_chapter_id', btn.getAttribute('data-chapter-id') || '');
            setValue('f_topic_url', btn.getAttribute('data-topic-url') || '');
            setValue('f_sort_order', btn.getAttribute('data-sort-order') || '0');
            setValue('f_is_active', btn.getAttribute('data-is-active') || '1');
            setValue('f_link_scope_type', btn.getAttribute('data-link-scope-type') || '');
            setValue('f_link_scope_id', btn.getAttribute('data-link-scope-id') || '0');
            setValue('f_topic_description', btn.getAttribute('data-topic-description') || '');
            if (title) title.textContent = 'Editar tema';
            openModal();
        });
    });

    <?php if ($openTopicModal): ?>
    openModal();
    <?php endif; ?>
})();
</script>




