<?php
// Full Maneuver CRUD + normalized availability editor.
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/maneuver_bridges.php');
include_once(__DIR__ . '/../../domains/rules/admin_maneuver_state.php');
include_once(__DIR__ . '/../../domains/rules/admin.php');
include_once(__DIR__ . '/../../domains/rules/admin_maneuvers.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
include(__DIR__ . '/../../partials/admin/admin_styles.php');

if (!function_exists('admin_maneuver_h')) {
    function admin_maneuver_h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$csrfKey = 'csrf_admin_maneuvers';
$csrf = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($csrfKey)
    : ($_SESSION[$csrfKey] ?? ($_SESSION[$csrfKey] = bin2hex(random_bytes(16))));

$bridgesReady = hg_maneuver_bridge_table_exists($link, 'bridge_maneuvers_systems')
    && hg_maneuver_bridge_table_exists($link, 'bridge_maneuvers_forms');

$flash = [];
$newMode = hg_request_query_has($hgRequest, 'new');
$selectedId = max(0, (int)hg_request_query_param($hgRequest, 'maneuver_id'));
$crudAction = hg_request_body_param($hgRequest, 'crud_action');

if ($crudAction !== '') {
    $token = hg_request_body_value($hgRequest, 'csrf');
    $valid = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $csrfKey)
        : ($token !== '' && isset($_SESSION[$csrfKey]) && hash_equals((string)$_SESSION[$csrfKey], $token));

    if (!$valid) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF inválido. Recarga la página.'];
    } elseif ($crudAction === 'save') {
        $id = max(0, (int)hg_request_body_param($hgRequest, 'id'));
        $payload = [
            'name' => hg_request_body_value($hgRequest, 'name'),
            'image_url' => hg_request_body_value($hgRequest, 'image_url'),
            'text' => hg_request_body_value($hgRequest, 'text'),
            'roll' => hg_request_body_value($hgRequest, 'roll'),
            'difficulty' => hg_request_body_value($hgRequest, 'difficulty'),
            'damage' => hg_request_body_value($hgRequest, 'damage'),
            'actions' => hg_request_body_value($hgRequest, 'actions'),
            'system_id' => (int)hg_request_body_param($hgRequest, 'system_id'),
            'bibliography_id' => (int)hg_request_body_param($hgRequest, 'bibliography_id'),
        ];
        $result = $id > 0
            ? hg_rules_admin_maneuver_update($link, $id, $payload)
            : hg_rules_admin_maneuver_create($link, $payload);
        $flash[] = ['type' => !empty($result['ok']) ? 'ok' : 'error', 'msg' => (string)($result['message'] ?? 'Error al guardar.')];
        if (!empty($result['ok'])) {
            $selectedId = $id > 0 ? $id : (int)($result['id'] ?? 0);
            $newMode = false;
        } else {
            $selectedId = $id;
            $newMode = $id <= 0;
        }
    } elseif ($crudAction === 'delete') {
        $id = max(0, (int)hg_request_body_param($hgRequest, 'id'));
        $result = hg_rules_admin_maneuver_delete($link, $id);
        $flash[] = ['type' => !empty($result['ok']) ? 'ok' : 'error', 'msg' => (string)($result['message'] ?? 'Error al borrar.')];
        if (!empty($result['ok'])) {
            $selectedId = 0;
            $newMode = false;
        } else {
            $selectedId = $id;
        }
    } elseif ($crudAction === 'links') {
        $selectedId = max(0, (int)hg_request_body_param($hgRequest, 'maneuver_id'));
        if (!$bridgesReady || $selectedId <= 0) {
            $flash[] = ['type' => 'error', 'msg' => 'No se puede guardar la disponibilidad.'];
        } else {
            // RequestContext intentionally scalarizes input. These two checkbox arrays
            // therefore stay at the admin edge until the shared request contract grows
            // first-class list support.
            $systemsPosted = isset($_POST['system_ids']) && is_array($_POST['system_ids']) ? $_POST['system_ids'] : [];
            $formsPosted = isset($_POST['form_ids']) && is_array($_POST['form_ids']) ? $_POST['form_ids'] : [];
            $result = hg_rules_admin_maneuver_save_links($link, $selectedId, $systemsPosted, $formsPosted);
            $flash[] = !empty($result['ok'])
                ? ['type' => 'ok', 'msg' => 'Disponibilidad guardada.']
                : ['type' => 'error', 'msg' => 'Error al guardar disponibilidad: ' . (string)($result['error'] ?? '')];
        }
    }
}

$rows = hg_rules_admin_maneuver_rows($link);
if (!$newMode && $selectedId <= 0 && !empty($rows)) $selectedId = (int)$rows[0]['id'];

$stateSeedId = $selectedId > 0 ? $selectedId : (int)($rows[0]['id'] ?? 0);
$state = hg_rules_admin_maneuver_state($link, $stateSeedId, $bridgesReady);
$systems = $state['systems'];
$forms = $state['forms'];
$maneuverLinkMap = $state['maneuverLinkMap'];
$selectedSystems = $newMode ? [] : $state['selectedSystems'];
$selectedForms = $newMode ? [] : $state['selectedForms'];
if (!$newMode) $selectedId = (int)$state['selectedId'];

$options = hg_rules_admin_maneuver_options($link);
$bibliographies = $options['bibliographies'];
$editing = $selectedId > 0 ? hg_rules_admin_maneuver_fetch($link, $selectedId) : null;
if ($newMode) {
    $selectedId = 0;
    $editing = null;
}

$formsBySystem = [];
foreach ($forms as $form) {
    $sid = (int)($form['system_id'] ?? 0);
    if ($sid <= 0) continue;
    $formsBySystem[$sid][] = $form;
}

$originSystems = [];
foreach ($rows as $row) {
    $systemName = trim((string)($row['system_name'] ?? ''));
    if ($systemName !== '') $originSystems[$systemName] = true;
}

$publicUrl = $editing ? pretty_url($link, 'fact_combat_maneuvers', '/rules/maneuvers', (int)$editing['id']) : '';

admin_panel_open('Maniobras', '<a class="btn btn-green" href="/talim?s=admin_maneuvers&amp;new=1">+ Nueva maniobra</a>');
?>

<?php if (!empty($flash)): ?>
    <div class="flash">
        <?php foreach ($flash as $notice): ?>
            <div class="<?= ($notice['type'] ?? '') === 'ok' ? 'ok' : 'err' ?>"><?= admin_maneuver_h($notice['msg'] ?? '') ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$bridgesReady): ?>
    <div class="flash"><div class="err">No están disponibles los bridges normalizados de Maniobras.</div></div>
<?php endif; ?>

<div class="adm-summary-band">
    <span class="adm-summary-pill">Maniobras: <?= count($rows) ?></span>
    <span class="adm-summary-pill">Sistemas de origen: <?= count($originSystems) ?></span>
</div>

<form method="get" class="adm-flex-gap-8 adm-mt-12" id="maneuverPickerForm">
    <input type="hidden" name="s" value="admin_maneuvers">
    <input class="inp" type="search" id="maneuverPickerFilter" placeholder="Filtrar maniobras…" autocomplete="off">
    <select class="select" name="maneuver_id" id="maneuverPicker" onchange="this.form.submit()">
        <?php foreach ($rows as $row): ?>
            <option value="<?= (int)$row['id'] ?>"<?= !$newMode && (int)$row['id'] === $selectedId ? ' selected' : '' ?>>
                <?= admin_maneuver_h(($row['system_name'] ?: 'Sin sistema') . ' — ' . $row['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<h3><?= $editing ? 'Datos de la maniobra' : 'Nueva maniobra' ?></h3>
<form method="post">
    <input type="hidden" name="csrf" value="<?= admin_maneuver_h($csrf) ?>">
    <input type="hidden" name="crud_action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

    <div class="adm-grid-1-2">
        <label for="maneuver_name">Nombre</label>
        <input class="inp" id="maneuver_name" name="name" required maxlength="100" value="<?= admin_maneuver_h($editing['name'] ?? '') ?>">

        <label for="maneuver_system">Sistema de origen</label>
        <select class="select" id="maneuver_system" name="system_id">
            <option value="0">— Sin sistema —</option>
            <?php foreach ($systems as $system): ?>
                <option value="<?= (int)$system['id'] ?>"<?= (int)($editing['system_id'] ?? 0) === (int)$system['id'] ? ' selected' : '' ?>><?= admin_maneuver_h($system['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="maneuver_bibliography">Origen bibliográfico</label>
        <select class="select" id="maneuver_bibliography" name="bibliography_id">
            <option value="0">— Sin origen —</option>
            <?php foreach ($bibliographies as $bibliography): ?>
                <option value="<?= (int)$bibliography['id'] ?>"<?= (int)($editing['bibliography_id'] ?? 0) === (int)$bibliography['id'] ? ' selected' : '' ?>><?= admin_maneuver_h($bibliography['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="maneuver_image">Imagen</label>
        <input class="inp" id="maneuver_image" name="image_url" maxlength="190" value="<?= admin_maneuver_h($editing['image_url'] ?? '') ?>" placeholder="archivo.webp o ruta completa">

        <label for="maneuver_roll">Tirada</label>
        <input class="inp" id="maneuver_roll" name="roll" maxlength="100" value="<?= admin_maneuver_h($editing['roll'] ?? '') ?>">

        <label for="maneuver_difficulty">Dificultad</label>
        <input class="inp" id="maneuver_difficulty" name="difficulty" maxlength="100" value="<?= admin_maneuver_h($editing['difficulty'] ?? '') ?>">

        <label for="maneuver_damage">Daño / efecto</label>
        <input class="inp" id="maneuver_damage" name="damage" maxlength="100" value="<?= admin_maneuver_h($editing['damage'] ?? '') ?>">

        <label for="maneuver_actions">Acciones</label>
        <input class="inp" id="maneuver_actions" name="actions" maxlength="100" value="<?= admin_maneuver_h($editing['actions'] ?? '') ?>">

        <label for="maneuver_text">Descripción</label>
        <textarea class="ta" id="maneuver_text" name="text" rows="8"><?= admin_maneuver_h($editing['text'] ?? '') ?></textarea>
    </div>

    <?php if ($editing): ?>
        <p class="adm-color-muted">ID estable: <code><?= admin_maneuver_h($editing['pretty_id'] ?? '') ?></code>. El nombre puede cambiar sin alterar este identificador.</p>
    <?php else: ?>
        <p class="adm-color-muted">El ID de URL se genera al crear la maniobra y después permanece estable.</p>
    <?php endif; ?>

    <div class="adm-flex-gap-8">
        <button class="btn btn-green" type="submit"><?= $editing ? 'Guardar datos' : 'Crear maniobra' ?></button>
        <?php if ($editing && $publicUrl !== ''): ?><a class="btn" href="<?= admin_maneuver_h($publicUrl) ?>" target="_blank" rel="noopener">Ver ficha pública</a><?php endif; ?>
        <?php if ($newMode): ?><a class="btn" href="/talim?s=admin_maneuvers">Cancelar</a><?php endif; ?>
    </div>
</form>

<?php if ($editing): ?>
    <form method="post" class="adm-mt-12" onsubmit="return confirm('¿Borrar esta maniobra? Sus enlaces de sistemas y Formas también desaparecerán.');">
        <input type="hidden" name="csrf" value="<?= admin_maneuver_h($csrf) ?>">
        <input type="hidden" name="crud_action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
        <button class="btn btn-red" type="submit">Borrar maniobra</button>
    </form>

    <hr>
    <h3>Disponibilidad</h3>
    <p class="adm-color-muted">Un sistema marcado concede acceso general. Las Formas permiten añadir disponibilidad específica sin alterar el sistema de origen de la maniobra.</p>

    <form method="post" id="maneuverAvailabilityForm">
        <input type="hidden" name="csrf" value="<?= admin_maneuver_h($csrf) ?>">
        <input type="hidden" name="crud_action" value="links">
        <input type="hidden" name="maneuver_id" value="<?= (int)$editing['id'] ?>">

        <div class="adm-flex-gap-8">
            <input class="inp" type="search" id="availabilityFilter" placeholder="Filtrar sistemas o Formas…" autocomplete="off">
            <select class="select" id="importManeuver">
                <option value="">Copiar disponibilidad de…</option>
                <?php foreach ($rows as $row): ?>
                    <?php if ((int)$row['id'] === (int)$editing['id']) continue; ?>
                    <option value="<?= (int)$row['id'] ?>"><?= admin_maneuver_h(($row['system_name'] ?: 'Sin sistema') . ' — ' . $row['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="button" id="importManeuverLinks">Copiar</button>
        </div>

        <div class="adm-summary-band adm-mt-12">
            <span class="adm-summary-pill" id="availabilitySystemSummary"></span>
            <span class="adm-summary-pill" id="availabilityFormSummary"></span>
        </div>

        <?php foreach ($systems as $system): ?>
            <?php
                $sid = (int)$system['id'];
                $systemForms = $formsBySystem[$sid] ?? [];
                $selectedFormCount = 0;
                $searchParts = [(string)$system['name']];
                foreach ($systemForms as $form) {
                    if (isset($selectedForms[(int)$form['id']])) $selectedFormCount++;
                    $searchParts[] = (string)($form['applicability_name'] ?? '');
                    $searchParts[] = (string)($form['form'] ?? '');
                }
                $open = isset($selectedSystems[$sid]) || $selectedFormCount > 0;
            ?>
            <details class="adm-mt-12 maneuver-scope" data-search="<?= admin_maneuver_h(mb_strtolower(implode(' ', $searchParts), 'UTF-8')) ?>"<?= $open ? ' open' : '' ?>>
                <summary><strong><?= admin_maneuver_h($system['name']) ?></strong> · <?= isset($selectedSystems[$sid]) ? 'acceso general' : ($selectedFormCount . ' Formas específicas') ?></summary>
                <div class="adm-mt-12">
                    <label><input type="checkbox" name="system_ids[]" value="<?= $sid ?>"<?= isset($selectedSystems[$sid]) ? ' checked' : '' ?>> <strong>Acceso general a todo el sistema</strong></label>
                </div>

                <?php if ($systemForms): ?>
                    <div class="adm-flex-gap-8 adm-mt-12">
                        <span class="adm-color-muted">Formas específicas</span>
                        <button class="btn" type="button" data-form-system="<?= $sid ?>" data-checked="1">Todo</button>
                        <button class="btn" type="button" data-form-system="<?= $sid ?>" data-checked="0">Nada</button>
                    </div>
                    <div class="adm-grid-1-2 adm-mt-12">
                        <?php foreach ($systemForms as $form): ?>
                            <?php $scope = trim((string)($form['applicability_name'] ?? '')); ?>
                            <label>
                                <input type="checkbox" name="form_ids[]" data-form-system-id="<?= $sid ?>" value="<?= (int)$form['id'] ?>"<?= isset($selectedForms[(int)$form['id']]) ? ' checked' : '' ?>>
                                <?= admin_maneuver_h(($scope !== '' ? $scope . ' / ' : '') . $form['form']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="adm-color-muted">Este sistema no tiene Formas registradas.</p>
                <?php endif; ?>
            </details>
        <?php endforeach; ?>

        <p class="adm-mt-12"><button class="btn btn-green" type="submit"<?= $bridgesReady ? '' : ' disabled' ?>>Guardar disponibilidad</button></p>
    </form>
<?php endif; ?>

<script>
(function() {
    const pickerFilter = document.getElementById('maneuverPickerFilter');
    const picker = document.getElementById('maneuverPicker');
    if (pickerFilter && picker) {
        pickerFilter.addEventListener('input', function() {
            const q = pickerFilter.value.trim().toLocaleLowerCase();
            Array.from(picker.options).forEach(function(option) {
                option.hidden = q !== '' && !option.text.toLocaleLowerCase().includes(q);
            });
        });
    }

    const linkMap = <?= json_encode($maneuverLinkMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const importButton = document.getElementById('importManeuverLinks');
    const importSelect = document.getElementById('importManeuver');

    function refreshAvailabilitySummary() {
        const systems = document.querySelectorAll('input[name="system_ids[]"]:checked').length;
        const forms = document.querySelectorAll('input[name="form_ids[]"]:checked').length;
        const systemSummary = document.getElementById('availabilitySystemSummary');
        const formSummary = document.getElementById('availabilityFormSummary');
        if (systemSummary) systemSummary.textContent = systems + ' sistemas completos';
        if (formSummary) formSummary.textContent = forms + ' Formas específicas';
    }

    if (importButton && importSelect) {
        importButton.addEventListener('click', function() {
            const sourceId = importSelect.value;
            if (!sourceId) return;
            const links = linkMap[sourceId] || {systems: [], forms: []};
            const systems = new Set((links.systems || []).map(String));
            const forms = new Set((links.forms || []).map(String));
            document.querySelectorAll('input[name="system_ids[]"]').forEach(function(input) {
                input.checked = systems.has(input.value);
            });
            document.querySelectorAll('input[name="form_ids[]"]').forEach(function(input) {
                input.checked = forms.has(input.value);
            });
            refreshAvailabilitySummary();
        });
    }

    document.querySelectorAll('[data-form-system]').forEach(function(button) {
        button.addEventListener('click', function() {
            const sid = button.getAttribute('data-form-system');
            const checked = button.getAttribute('data-checked') === '1';
            document.querySelectorAll('input[name="form_ids[]"][data-form-system-id="' + sid + '"]').forEach(function(input) {
                input.checked = checked;
            });
            refreshAvailabilitySummary();
        });
    });

    document.querySelectorAll('input[name="system_ids[]"], input[name="form_ids[]"]').forEach(function(input) {
        input.addEventListener('change', refreshAvailabilitySummary);
    });

    const availabilityFilter = document.getElementById('availabilityFilter');
    if (availabilityFilter) {
        availabilityFilter.addEventListener('input', function() {
            const q = availabilityFilter.value.trim().toLocaleLowerCase();
            document.querySelectorAll('.maneuver-scope').forEach(function(scope) {
                scope.hidden = q !== '' && !(scope.getAttribute('data-search') || '').includes(q);
                if (q !== '' && !scope.hidden) scope.open = true;
            });
        });
    }

    refreshAvailabilitySummary();
})();
</script>

<?php admin_panel_close(); ?>