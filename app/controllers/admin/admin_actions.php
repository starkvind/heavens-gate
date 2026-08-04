<?php
// admin_actions.php - CRUD del catálogo fact_actions mediante modal.
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

function admin_actions_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_actions_rows(mysqli $link, string $sql): array {
    $rows = [];
    if ($result = $link->query($sql)) {
        while ($row = $result->fetch_assoc()) { $rows[] = $row; }
        $result->free();
    }
    return $rows;
}

function admin_actions_save_image(array $file, string $uploadDir, string $urlBase): array {
    if (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'msg' => 'no_file'];
    if ((int)$file['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'msg' => 'Error de subida (#' . (int)$file['error'] . ').'];
    if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) return ['ok' => false, 'msg' => 'La imagen supera el límite de 5 MB.'];
    if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) return ['ok' => false, 'msg' => 'La subida de imagen no es válida.'];

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) { $mime = (string)finfo_file($finfo, $file['tmp_name']); finfo_close($finfo); }
    }
    if ($mime === '') { $size = @getimagesize($file['tmp_name']); $mime = (string)($size['mime'] ?? ''); }

    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) return ['ok' => false, 'msg' => 'Formato no permitido. Usa JPG, PNG, GIF o WebP.'];
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) return ['ok' => false, 'msg' => 'No se pudo preparar el directorio de imágenes.'];

    $filename = 'action-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $destination = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!@move_uploaded_file($file['tmp_name'], $destination)) return ['ok' => false, 'msg' => 'No se pudo guardar la imagen subida.'];
    @chmod($destination, 0644);
    return ['ok' => true, 'url' => rtrim($urlBase, '/') . '/' . $filename, 'path' => $destination];
}

function admin_actions_delete_local_image(string $url, string $uploadDir, string $urlBase): void {
    $prefix = rtrim($urlBase, '/') . '/';
    if ($url === '' || strpos($url, $prefix) !== 0) return;
    $path = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . basename($url);
    if (is_file($path)) @unlink($path);
}

$documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
if ($documentRoot === '') { $documentRoot = dirname(__DIR__, 3); }
$actionImageDir = $documentRoot . '/public/img/rules_actions';
$actionImageUrlBase = '/img/rules_actions';

$csrfKey = 'csrf_admin_actions';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : ($_SESSION[$csrfKey] ??= bin2hex(random_bytes(16)));
$flash = [];

$attributes = admin_actions_rows($link, "SELECT id, name FROM dim_traits WHERE kind = 'Atributos' ORDER BY name");
$skills = admin_actions_rows($link, "SELECT id, name, kind FROM dim_traits WHERE kind IN ('Talentos', 'Técnicas', 'Conocimientos') ORDER BY kind, name");
$bibliographies = admin_actions_rows($link, 'SELECT id, name FROM dim_bibliographies ORDER BY name');
$attributeIds = array_flip(array_map(static fn(array $row): int => (int)$row['id'], $attributes));
$skillIds = array_flip(array_map(static fn(array $row): int => (int)$row['id'], $skills));
$bibliographyIds = array_flip(array_map(static fn(array $row): int => (int)$row['id'], $bibliographies));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    $token = (string)($_POST['csrf'] ?? '');
    $csrfValid = function_exists('hg_admin_csrf_valid') ? hg_admin_csrf_valid($token, $csrfKey) : hash_equals((string)($_SESSION[$csrfKey] ?? ''), $token);
    $crudAction = (string)$_POST['crud_action'];
    $id = (int)($_POST['id'] ?? 0);

    if (!$csrfValid) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF inválido. Recarga la página.'];
    } elseif ($crudAction === 'delete') {
        $oldImage = '';
        if ($id > 0 && ($stmt = $link->prepare('SELECT image_url FROM fact_actions WHERE id = ? LIMIT 1'))) {
            $stmt->bind_param('i', $id); $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc(); $oldImage = (string)($row['image_url'] ?? ''); $stmt->close();
        }
        if ($id <= 0) {
            $flash[] = ['type' => 'error', 'msg' => 'Acción inválida para borrar.'];
        } elseif ($stmt = $link->prepare('DELETE FROM fact_actions WHERE id = ?')) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                admin_actions_delete_local_image($oldImage, $actionImageDir, $actionImageUrlBase);
                $flash[] = ['type' => 'ok', 'msg' => 'Acción eliminada.'];
            } else {
                $flash[] = ['type' => 'error', 'msg' => 'No se pudo eliminar la acción: ' . $stmt->error];
            }
            $stmt->close();
        }
    } elseif (in_array($crudAction, ['create', 'update'], true)) {
        $name = trim((string)($_POST['name'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $text = trim((string)($_POST['text'] ?? ''));
        $imageUrl = trim((string)($_POST['image_url'] ?? ''));
        $attributeId = (int)($_POST['attribute_trait_id'] ?? 0);
        $skillId = (int)($_POST['skill_trait_id'] ?? 0);
        $mode = (string)($_POST['difficulty_mode'] ?? 'variable');
        $fixedDifficulty = (int)($_POST['fixed_difficulty'] ?? 0);
        $suggestedDifficulty = (int)($_POST['suggested_difficulty'] ?? 0);
        $minDifficulty = (int)($_POST['min_difficulty'] ?? 0);
        $maxDifficulty = (int)($_POST['max_difficulty'] ?? 0);
        $bibliographyId = (int)($_POST['bibliography_id'] ?? 0);
        $removeImage = !empty($_POST['image_remove']);
        $oldImage = '';

        if ($crudAction === 'update' && $id > 0 && ($stmt = $link->prepare('SELECT image_url FROM fact_actions WHERE id = ? LIMIT 1'))) {
            $stmt->bind_param('i', $id); $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc(); $oldImage = (string)($row['image_url'] ?? ''); $stmt->close();
        }
        if ($name === '') $flash[] = ['type' => 'error', 'msg' => 'El nombre es obligatorio.'];
        if ($category === '') $flash[] = ['type' => 'error', 'msg' => 'La categoría es obligatoria.'];
        if ($text === '') $flash[] = ['type' => 'error', 'msg' => 'La descripción es obligatoria.'];
        if (!isset($attributeIds[$attributeId])) $flash[] = ['type' => 'error', 'msg' => 'Selecciona un atributo válido.'];
        if (!isset($skillIds[$skillId])) $flash[] = ['type' => 'error', 'msg' => 'Selecciona una habilidad válida.'];
        if (!in_array($mode, ['fixed', 'variable'], true)) $flash[] = ['type' => 'error', 'msg' => 'El modo de dificultad no es válido.'];
        if (mb_strlen($imageUrl, 'UTF-8') > 600) $flash[] = ['type' => 'error', 'msg' => 'La URL de imagen es demasiado larga.'];
        if ($bibliographyId > 0 && !isset($bibliographyIds[$bibliographyId])) $flash[] = ['type' => 'error', 'msg' => 'La bibliografía no es válida.'];
        if ($mode === 'fixed') {
            if ($fixedDifficulty < 2 || $fixedDifficulty > 10) $flash[] = ['type' => 'error', 'msg' => 'La dificultad fija debe estar entre 2 y 10.'];
            $suggestedDifficulty = $fixedDifficulty; $minDifficulty = 0; $maxDifficulty = 0;
        } else {
            if ($minDifficulty < 2 || $maxDifficulty > 10 || $minDifficulty > $maxDifficulty) $flash[] = ['type' => 'error', 'msg' => 'El rango de dificultad debe estar entre 2 y 10.'];
            if ($suggestedDifficulty < $minDifficulty || $suggestedDifficulty > $maxDifficulty) $flash[] = ['type' => 'error', 'msg' => 'La dificultad sugerida debe estar dentro del rango.'];
            $fixedDifficulty = 0;
        }
        $hasErrors = (bool)array_filter($flash, static fn(array $notice): bool => ($notice['type'] ?? '') === 'error');
        $uploadedImage = '';
        if (!$hasErrors && !empty($_FILES['action_image']) && (int)($_FILES['action_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = admin_actions_save_image($_FILES['action_image'], $actionImageDir, $actionImageUrlBase);
            if (!$upload['ok']) { $flash[] = ['type' => 'error', 'msg' => $upload['msg']]; $hasErrors = true; }
            else { $imageUrl = $uploadedImage = (string)$upload['url']; }
        }
        if ($removeImage && $uploadedImage === '') $imageUrl = '';

        if (!$hasErrors && $crudAction === 'create') {
            $stmt = $link->prepare("INSERT INTO fact_actions (name, category, text, image_url, attribute_trait_id, skill_trait_id, difficulty_mode, fixed_difficulty, suggested_difficulty, min_difficulty, max_difficulty, bibliography_id) VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0))");
            if ($stmt) {
                $stmt->bind_param('ssssiisiiiii', $name, $category, $text, $imageUrl, $attributeId, $skillId, $mode, $fixedDifficulty, $suggestedDifficulty, $minDifficulty, $maxDifficulty, $bibliographyId);
                if ($stmt->execute()) { hg_update_pretty_id_if_exists($link, 'fact_actions', (int)$link->insert_id, $name); $flash[] = ['type' => 'ok', 'msg' => 'Acción creada.']; }
                else { $flash[] = ['type' => 'error', 'msg' => 'No se pudo crear la acción: ' . $stmt->error]; }
                $stmt->close();
            }
        } elseif (!$hasErrors && $crudAction === 'update') {
            $stmt = $link->prepare("UPDATE fact_actions SET name = ?, category = ?, text = ?, image_url = NULLIF(?, ''), attribute_trait_id = ?, skill_trait_id = ?, difficulty_mode = ?, fixed_difficulty = NULLIF(?, 0), suggested_difficulty = NULLIF(?, 0), min_difficulty = NULLIF(?, 0), max_difficulty = NULLIF(?, 0), bibliography_id = NULLIF(?, 0) WHERE id = ?");
            if ($id <= 0) { $flash[] = ['type' => 'error', 'msg' => 'Acción inválida para actualizar.']; }
            elseif ($stmt) {
                $stmt->bind_param('ssssiisiiiiii', $name, $category, $text, $imageUrl, $attributeId, $skillId, $mode, $fixedDifficulty, $suggestedDifficulty, $minDifficulty, $maxDifficulty, $bibliographyId, $id);
                if ($stmt->execute()) { if ($oldImage !== $imageUrl) admin_actions_delete_local_image($oldImage, $actionImageDir, $actionImageUrlBase); hg_update_pretty_id_if_exists($link, 'fact_actions', $id, $name); $flash[] = ['type' => 'ok', 'msg' => 'Acción actualizada.']; }
                else { $flash[] = ['type' => 'error', 'msg' => 'No se pudo actualizar la acción: ' . $stmt->error]; }
                $stmt->close();
            }
        }
        $hasErrors = (bool)array_filter($flash, static fn(array $notice): bool => ($notice['type'] ?? '') === 'error');
        if ($hasErrors && $uploadedImage !== '') admin_actions_delete_local_image($uploadedImage, $actionImageDir, $actionImageUrlBase);
    }
}

$actions = admin_actions_rows($link, "SELECT a.*, COALESCE(attr.name, '') AS attribute_name, COALESCE(skill.name, '') AS skill_name FROM fact_actions a LEFT JOIN dim_traits attr ON attr.id = a.attribute_trait_id LEFT JOIN dim_traits skill ON skill.id = a.skill_trait_id ORDER BY a.category, a.name");
$actionCategories = [];
foreach ($actions as $action) {
    $actionCategory = trim((string)($action['category'] ?? ''));
    if ($actionCategory !== '') $actionCategories[$actionCategory] = $actionCategory;
}
$actionCategories = array_values($actionCategories);
natcasesort($actionCategories);
$actionCategories = array_values($actionCategories);
admin_panel_open('Gestionar acciones', '<button class="btn btn-green" type="button" onclick="openActionModal()">Nueva acción</button>');
?>
<?php foreach ($flash as $notice): ?><div class="flash"><div class="<?= ($notice['type'] ?? '') === 'ok' ? 'ok' : 'err' ?>"><?= admin_actions_h($notice['msg'] ?? '') ?></div></div><?php endforeach; ?>

<style>
.adm-actions-toolbar{display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;align-items:center;margin:0 0 12px}.adm-actions-category-filter{min-width:200px}.adm-actions-filter{min-width:min(360px,100%)}.adm-action-image{width:54px;height:54px;object-fit:cover;border:1px solid #000088;border-radius:6px;background:#05014e}.adm-action-preview{width:120px;height:80px;object-fit:cover;border:1px solid #000088;border-radius:6px;background:#05014e}.adm-action-preview[hidden]{display:none}.adm-action-empty{font-size:11px;color:#aebed5}.adm-actions-table{max-height:70vh;overflow:auto;border:1px solid #000088;border-radius:8px}.adm-action-desc{max-width:420px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
</style>

<div class="adm-actions-toolbar">
    <select id="actionCategoryFilter" class="select adm-actions-category-filter" aria-label="Filtrar por categoría"><option value="">Todas las categorías</option><?php foreach ($actionCategories as $actionCategory): ?><option value="<?= admin_actions_h($actionCategory) ?>"><?= admin_actions_h($actionCategory) ?></option><?php endforeach; ?></select>
    <input id="actionFilter" class="input adm-actions-filter" type="search" placeholder="Buscar por nombre, categoría, atributo o habilidad">
    <span class="adm-action-empty"><span id="actionCount"><?= count($actions) ?></span> acciones</span>
</div>
<div class="adm-actions-table"><table class="table"><thead><tr><th>Imagen</th><th>Acción</th><th>Categoría</th><th>Tirada</th><th>Dificultad</th><th>Descripción</th><th>Opciones</th></tr></thead><tbody id="actionsTbody">
<?php foreach ($actions as $action): $search = strtolower(implode(' ', [(string)$action['name'], (string)$action['category'], (string)$action['attribute_name'], (string)$action['skill_name'], (string)$action['text']])); ?>
<tr data-search="<?= admin_actions_h($search) ?>" data-category="<?= admin_actions_h(trim((string)$action['category'])) ?>"><td><?php if (!empty($action['image_url'])): ?><img class="adm-action-image" src="<?= admin_actions_h($action['image_url']) ?>" alt=""><?php else: ?><span class="adm-action-empty">Sin imagen</span><?php endif; ?></td><td><?= admin_actions_h($action['name']) ?></td><td><?= admin_actions_h($action['category']) ?></td><td><?= admin_actions_h($action['attribute_name']) ?> + <?= admin_actions_h($action['skill_name']) ?></td><td><?= $action['difficulty_mode'] === 'fixed' ? 'Fija: ' . (int)$action['fixed_difficulty'] : ((int)$action['min_difficulty'] . '–' . (int)$action['max_difficulty'] . ' (sug. ' . (int)$action['suggested_difficulty'] . ')') ?></td><td class="adm-action-desc" title="<?= admin_actions_h(strip_tags((string)$action['text'])) ?>"><?= admin_actions_h(strip_tags((string)$action['text'])) ?></td><td><button class="btn" type="button" data-action-edit="<?= (int)$action['id'] ?>">Editar</button> <button class="btn btn-red" type="button" data-action-delete="<?= (int)$action['id'] ?>">Borrar</button></td></tr>
<?php endforeach; ?>
<?php if (!$actions): ?><tr><td colspan="7" class="adm-action-empty">Todavía no hay acciones.</td></tr><?php endif; ?>
</tbody></table></div>

<div class="modal-back" id="actionModal"><div class="modal"><h3 id="actionModalTitle">Nueva acción</h3><form method="post" enctype="multipart/form-data" id="actionForm"><input type="hidden" name="csrf" value="<?= admin_actions_h($csrf) ?>"><input type="hidden" name="crud_action" id="actionCrud" value="create"><input type="hidden" name="id" id="actionId" value="0"><div class="modal-body"><div class="adm-grid-1-2"><label>Nombre<input class="input" type="text" name="name" id="actionName" maxlength="150" required></label><label>Categoría<input class="input" type="text" name="category" id="actionCategory" maxlength="80" required></label><label>Atributo<select class="select" name="attribute_trait_id" id="actionAttribute" required><option value="">-- Elegir atributo --</option><?php foreach ($attributes as $trait): ?><option value="<?= (int)$trait['id'] ?>"><?= admin_actions_h($trait['name']) ?></option><?php endforeach; ?></select></label><label>Habilidad<select class="select" name="skill_trait_id" id="actionSkill" required><option value="">-- Elegir habilidad --</option><?php foreach ($skills as $trait): ?><option value="<?= (int)$trait['id'] ?>"><?= admin_actions_h($trait['kind'] . ' · ' . $trait['name']) ?></option><?php endforeach; ?></select></label><label>Modo de dificultad<select class="select" name="difficulty_mode" id="actionDifficultyMode"><option value="variable">Variable</option><option value="fixed">Fija</option></select></label><label data-action-fixed>Dificultad fija<input class="input" type="number" name="fixed_difficulty" id="actionFixedDifficulty" min="2" max="10" value="6"></label><label data-action-variable>Dificultad sugerida<input class="input" type="number" name="suggested_difficulty" id="actionSuggestedDifficulty" min="2" max="10" value="6"></label><label data-action-variable>Mínima<input class="input" type="number" name="min_difficulty" id="actionMinDifficulty" min="2" max="10" value="4"></label><label data-action-variable>Máxima<input class="input" type="number" name="max_difficulty" id="actionMaxDifficulty" min="2" max="10" value="9"></label><label>Bibliografía<select class="select" name="bibliography_id" id="actionBibliography"><option value="0">-- Sin bibliografía --</option><?php foreach ($bibliographies as $bibliography): ?><option value="<?= (int)$bibliography['id'] ?>"><?= admin_actions_h($bibliography['name']) ?></option><?php endforeach; ?></select></label><label>Imagen (ruta o URL)<input class="input" type="text" name="image_url" id="actionImageUrl" maxlength="600" placeholder="/img/rules_actions/... o URL externa"></label><label>Subir imagen<input class="input" type="file" name="action_image" id="actionImageUpload" accept="image/jpeg,image/png,image/gif,image/webp"><span class="adm-action-empty">JPG, PNG, GIF o WebP; máximo 5 MB.</span></label><label id="actionRemoveImageLabel" hidden><input type="checkbox" name="image_remove" id="actionRemoveImage" value="1"> Quitar imagen actual</label></div><p><img class="adm-action-preview" id="actionImagePreview" alt="Vista previa" hidden></p><label>Descripción<textarea class="input" name="text" id="actionText" rows="7" required></textarea></label></div><div class="modal-actions"><button class="btn" type="button" onclick="closeActionModal()">Cancelar</button><button class="btn btn-green" type="submit">Guardar acción</button></div></form></div></div>
<div class="modal-back" id="actionDeleteModal"><div class="modal adm-modal-sm"><h3>Eliminar acción</h3><p id="actionDeleteText">Esta acción se eliminará definitivamente.</p><form method="post"><input type="hidden" name="csrf" value="<?= admin_actions_h($csrf) ?>"><input type="hidden" name="crud_action" value="delete"><input type="hidden" name="id" id="actionDeleteId"><div class="modal-actions"><button class="btn" type="button" onclick="closeActionDeleteModal()">Cancelar</button><button class="btn btn-red" type="submit">Eliminar</button></div></form></div></div>

<script>
const actionsData = <?= json_encode($actions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const actionModal = document.getElementById('actionModal');
function actionById(id){ return actionsData.find(action => Number(action.id) === Number(id)); }
function refreshActionDifficulty(){ const fixed = document.getElementById('actionDifficultyMode').value === 'fixed'; document.querySelectorAll('[data-action-fixed]').forEach(el => el.hidden = !fixed); document.querySelectorAll('[data-action-variable]').forEach(el => el.hidden = fixed); }
function setActionPreview(url){ const preview = document.getElementById('actionImagePreview'); preview.hidden = !url; if (url) preview.src = url; }
function openActionModal(id = 0){ const action = id ? actionById(id) : null; document.getElementById('actionForm').reset(); document.getElementById('actionCrud').value = action ? 'update' : 'create'; document.getElementById('actionId').value = action ? action.id : '0'; document.getElementById('actionModalTitle').textContent = action ? 'Editar acción' : 'Nueva acción'; document.getElementById('actionFixedDifficulty').value = action ? (action.fixed_difficulty || 6) : 6; document.getElementById('actionSuggestedDifficulty').value = action ? (action.suggested_difficulty || 6) : 6; document.getElementById('actionMinDifficulty').value = action ? (action.min_difficulty || 4) : 4; document.getElementById('actionMaxDifficulty').value = action ? (action.max_difficulty || 9) : 9; if (action) { document.getElementById('actionName').value = action.name || ''; document.getElementById('actionCategory').value = action.category || ''; document.getElementById('actionAttribute').value = action.attribute_trait_id || ''; document.getElementById('actionSkill').value = action.skill_trait_id || ''; document.getElementById('actionDifficultyMode').value = action.difficulty_mode || 'variable'; document.getElementById('actionBibliography').value = action.bibliography_id || '0'; document.getElementById('actionImageUrl').value = action.image_url || ''; document.getElementById('actionText').value = action.text || ''; } document.getElementById('actionRemoveImage').checked = false; document.getElementById('actionRemoveImageLabel').hidden = !action || !action.image_url; setActionPreview(action ? action.image_url : ''); refreshActionDifficulty(); actionModal.style.display = 'flex'; }
function closeActionModal(){ actionModal.style.display = 'none'; }
function openActionDeleteModal(id){ const action = actionById(id); document.getElementById('actionDeleteId').value = id; document.getElementById('actionDeleteText').textContent = 'Vas a eliminar “' + (action ? action.name : 'esta acción') + '”. Esta operación no se puede deshacer.'; document.getElementById('actionDeleteModal').style.display = 'flex'; }
function closeActionDeleteModal(){ document.getElementById('actionDeleteModal').style.display = 'none'; }
document.querySelectorAll('[data-action-edit]').forEach(button => button.addEventListener('click', () => openActionModal(button.dataset.actionEdit)));
document.querySelectorAll('[data-action-delete]').forEach(button => button.addEventListener('click', () => openActionDeleteModal(button.dataset.actionDelete)));
document.getElementById('actionDifficultyMode').addEventListener('change', refreshActionDifficulty);
document.getElementById('actionImageUrl').addEventListener('input', event => setActionPreview(event.target.value.trim()));
document.getElementById('actionImageUpload').addEventListener('change', event => { const file = event.target.files[0]; if (file) setActionPreview(URL.createObjectURL(file)); });
const actionFilter = document.getElementById('actionFilter');
const actionCategoryFilter = document.getElementById('actionCategoryFilter');
const actionCategoryStorageKey = 'hg.admin.actions.category-filter';
function filterActions(){ const query = actionFilter.value.trim().toLowerCase(); const category = actionCategoryFilter.value; let visible = 0; document.querySelectorAll('#actionsTbody tr[data-search]').forEach(row => { const match = row.dataset.search.includes(query) && (!category || row.dataset.category === category); row.hidden = !match; if (match) visible++; }); document.getElementById('actionCount').textContent = visible; }
const savedActionCategory = localStorage.getItem(actionCategoryStorageKey);
if (savedActionCategory && Array.from(actionCategoryFilter.options).some(option => option.value === savedActionCategory)) actionCategoryFilter.value = savedActionCategory;
filterActions();
actionFilter.addEventListener('input', filterActions);
actionCategoryFilter.addEventListener('change', () => { localStorage.setItem(actionCategoryStorageKey, actionCategoryFilter.value); filterActions(); });
document.addEventListener('keydown', event => { if (event.key === 'Escape') { closeActionModal(); closeActionDeleteModal(); } });
</script>
<?php admin_panel_close(); ?>