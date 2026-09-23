<?php
// admin_maneuvers.php -- assignments for bridge_maneuvers_systems and bridge_maneuvers_forms
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/maneuver_bridges.php');
include_once(__DIR__ . '/../../domains/rules/admin.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
include(__DIR__ . '/../../partials/admin/admin_styles.php');

function admin_maneuver_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$csrfKey = 'csrf_admin_maneuvers';
$csrf = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($csrfKey)
    : ($_SESSION[$csrfKey] ??= bin2hex(random_bytes(16)));

$bridgesReady = hg_maneuver_bridge_table_exists($link, 'bridge_maneuvers_systems')
    && hg_maneuver_bridge_table_exists($link, 'bridge_maneuvers_forms');

$flash = [];
$selectedId = (int)($_REQUEST['maneuver_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_maneuver_links'])) {
    $token = (string)($_POST['csrf'] ?? '');
    $valid = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $csrfKey)
        : hash_equals((string)($_SESSION[$csrfKey] ?? ''), $token);
    if (!$valid) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } elseif (!$bridgesReady || $selectedId <= 0) {
        $flash[] = ['type' => 'error', 'msg' => 'No se puede guardar la asignacion.'];
    } else {
        $systemsPosted = (array)($_POST['system_ids'] ?? []);
        $formsPosted = (array)($_POST['form_ids'] ?? []);
        $result = hg_rules_admin_maneuver_save_links($link, $selectedId, $systemsPosted, $formsPosted);
        $flash[] = !empty($result['ok'])
            ? ['type' => 'ok', 'msg' => 'Asignacion de maniobra guardada.']
            : ['type' => 'error', 'msg' => 'Error al guardar: ' . (string)($result['error'] ?? '')];
    }
}

$state = hg_rules_admin_maneuver_state($link, $selectedId, $bridgesReady);
$maneuvers = $state['maneuvers'];
$selectedId = (int)$state['selectedId'];
$systems = $state['systems'];
$forms = $state['forms'];
$selectedSystems = $state['selectedSystems'];
$selectedForms = $state['selectedForms'];
$maneuverLinkMap = $state['maneuverLinkMap'];

admin_panel_open('Maniobras: sistemas y formas');
?>
<?php foreach ($flash as $notice): ?>
    <div class="flash"><div class="<?= $notice['type'] === 'ok' ? 'ok' : 'err' ?>"><?= admin_maneuver_h($notice['msg']) ?></div></div>
<?php endforeach; ?>

<?php if (!$bridgesReady): ?>
    <div class="flash"><div class="err">Ejecuta primero <code>php app/tools/migrate_maneuver_bridges.php</code>.</div></div>
<?php endif; ?>

<form method="get" class="adm-flex-right-8">
    <input type="hidden" name="s" value="admin_maneuvers">
    <label>Maniobra
        <select class="select" name="maneuver_id" onchange="this.form.submit()">
            <?php foreach ($maneuvers as $maneuver): ?>
                <option value="<?= (int)$maneuver['id'] ?>"<?= (int)$maneuver['id'] === $selectedId ? ' selected' : '' ?>>
                    <?= admin_maneuver_h($maneuver['system_name'] . ' - ' . $maneuver['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<form method="post">
    <input type="hidden" name="csrf" value="<?= admin_maneuver_h($csrf) ?>">
    <div class="adm-flex-right-8">
        <label>Importar configuracion de
            <select class="select" id="importManeuver">
                <option value="">-- Elegir maniobra --</option>
                <?php foreach ($maneuvers as $maneuver): ?>
                    <?php if ((int)$maneuver['id'] === $selectedId) continue; ?>
                    <option value="<?= (int)$maneuver['id'] ?>"><?= admin_maneuver_h($maneuver['system_name'] . ' - ' . $maneuver['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn" type="button" id="importManeuverLinks">Copiar configuracion</button>
    </div>
    <p class="adm-color-muted">Copia los checks en pantalla. Pulsa Guardar enlaces para confirmar el cambio.</p>
    <input type="hidden" name="csrf" value="<?= admin_maneuver_h($csrf) ?>">
    <input type="hidden" name="save_maneuver_links" value="1">
    <input type="hidden" name="maneuver_id" value="<?= $selectedId ?>">

    <div class="adm-flex-right-8"><h3>Acceso general por sistema</h3><button class="btn" type="button" data-toggle-checks="system_ids[]" data-checked="1">Todo</button><button class="btn" type="button" data-toggle-checks="system_ids[]" data-checked="0">Nada</button></div>
    <p class="adm-color-muted">La maniobra se muestra en cualquier forma de los sistemas marcados.</p>
    <div class="adm-grid-1-2">
        <?php foreach ($systems as $system): ?>
            <label><input type="checkbox" name="system_ids[]" value="<?= (int)$system['id'] ?>"<?= isset($selectedSystems[(int)$system['id']]) ? ' checked' : '' ?>> <?= admin_maneuver_h($system['name']) ?></label>
        <?php endforeach; ?>
    </div>

    <div class="adm-flex-right-8"><h3>Acceso exclusivo por forma</h3><button class="btn" type="button" data-toggle-checks="form_ids[]" data-checked="1">Todo</button><button class="btn" type="button" data-toggle-checks="form_ids[]" data-checked="0">Nada</button></div>
    <p class="adm-color-muted">La maniobra se suma solo al elegir las formas marcadas.</p>
    <div class="adm-grid-1-2">
        <?php foreach ($forms as $form): ?>
            <label><input type="checkbox" name="form_ids[]" value="<?= (int)$form['id'] ?>"<?= isset($selectedForms[(int)$form['id']]) ? ' checked' : '' ?>> <?= admin_maneuver_h($form['system_name'] . ' / ' . $form['race'] . ' / ' . $form['form']) ?></label>
        <?php endforeach; ?>
    </div>

    <p class="adm-mt-12"><button class="btn btn-green" type="submit"<?= $bridgesReady ? '' : ' disabled' ?>>Guardar enlaces</button></p>
</form>
<script>
const maneuverLinkMap = <?= json_encode($maneuverLinkMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
document.getElementById('importManeuverLinks').addEventListener('click', function() {
    const sourceId = document.getElementById('importManeuver').value;
    if (!sourceId) return;
    const links = maneuverLinkMap[sourceId] || { systems: [], forms: [] };
    const systems = new Set((links.systems || []).map(String));
    const forms = new Set((links.forms || []).map(String));
    document.querySelectorAll('input[name="system_ids[]"]').forEach(function(input) {
        input.checked = systems.has(input.value);
    });
    document.querySelectorAll('input[name="form_ids[]"]').forEach(function(input) {
        input.checked = forms.has(input.value);
    });
});
document.querySelectorAll('[data-toggle-checks]').forEach(function(button) {
    button.addEventListener('click', function() {
        const name = button.getAttribute('data-toggle-checks');
        const checked = button.getAttribute('data-checked') === '1';
        document.querySelectorAll('input[type="checkbox"][name="' + name + '"]').forEach(function(input) {
            input.checked = checked;
        });
    });
});
</script>
<?php admin_panel_close(); ?>