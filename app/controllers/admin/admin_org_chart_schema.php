<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../domains/organizations/admin_chart_schema.php');

$csrfKey = 'csrf_admin_org_chart_schema';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');

function hg_aocs_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hg_aocs_csrf_ok(string $csrfKey): bool
{
    $token = (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $csrfKey)
        : ($token !== '' && isset($_SESSION[$csrfKey]) && hash_equals((string)$_SESSION[$csrfKey], $token));
}

$flash = [];
$departmentTypes = [
    'board' => 'Bloque / Consejo',
    'department' => 'Departamento',
    'unit' => 'Unidad',
    'delegation' => 'Delegacion',
    'special' => 'Especial',
    'territory' => 'Territorio',
    'other' => 'Otra',
];

$departmentsReady = hg_aocs_table_exists($link, 'dim_organization_departments');
$bridgeReady = hg_aocs_table_exists($link, 'bridge_characters_org');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hg_aocs_csrf_ok($csrfKey)) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina antes de reintentar.'];
    } else {
        $action = (string)($_POST['schema_action'] ?? '');
        if ($action === 'create_department') {
            if (!$departmentsReady) {
                $flash[] = ['type' => 'error', 'msg' => 'La tabla de categorias no esta disponible en esta base de datos.'];
            } else {
                $categoryOrgId = max(0, (int)($_POST['category_org_id'] ?? 0));
                $parentId = max(0, (int)($_POST['parent_department_id'] ?? 0));
                $parentIdOrNull = $parentId > 0 ? $parentId : null;
                $name = trim((string)($_POST['department_name'] ?? ''));
                $prettyRaw = trim((string)($_POST['department_pretty_id'] ?? ''));
                $type = (string)($_POST['department_type'] ?? 'department');
                $level = max(0, min(99, (int)($_POST['hierarchy_level'] ?? 1)));
                $color = trim((string)($_POST['department_color'] ?? '#e2e8f0'));
                $description = trim((string)($_POST['department_description'] ?? ''));
                $sort = (int)($_POST['sort_order'] ?? 0);

                if ($categoryOrgId <= 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'Elige una organizacion.'];
                } elseif ($name === '') {
                    $flash[] = ['type' => 'error', 'msg' => 'El nombre de la categoria es obligatorio.'];
                } elseif (!isset($departmentTypes[$type])) {
                    $flash[] = ['type' => 'error', 'msg' => 'Tipo de categoria no valido.'];
                } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                    $flash[] = ['type' => 'error', 'msg' => 'El color debe estar en formato hexadecimal, por ejemplo #33CCCC.'];
                } elseif (!hg_aocs_department_belongs_to_org($link, $parentId, $categoryOrgId)) {
                    $flash[] = ['type' => 'error', 'msg' => 'La categoria padre no pertenece a esa organizacion.'];
                } else {
                    $pretty = hg_aocs_unique_department_pretty($link, $categoryOrgId, $prettyRaw !== '' ? $prettyRaw : $name);
                    if ($sort <= 0) {
                        $sort = hg_aocs_next_department_sort($link, $categoryOrgId, $parentIdOrNull);
                    }
                    $newId = hg_aocs_upsert_department(
                        $link,
                        $categoryOrgId,
                        $parentIdOrNull,
                        $pretty,
                        $name,
                        $type,
                        $level,
                        $color,
                        $description,
                        $sort
                    );
                    $flash[] = $newId > 0
                        ? ['type' => 'ok', 'msg' => 'Categoria creada para el organigrama.']
                        : ['type' => 'error', 'msg' => 'No se pudo crear la categoria.'];
                }
            }
        }
    }
}

$departmentsCount = hg_aocs_count_rows($link, 'dim_organization_departments');
$bridgeCount = hg_aocs_count_rows($link, 'bridge_characters_org');
$organizations = hg_aocs_fetch_organizations($link);
$selectedCategoryOrgId = max(0, (int)($_POST['category_org_id'] ?? $_GET['category_org_id'] ?? ($organizations[0]['id'] ?? 0)));
$selectedOrgDepartments = $departmentsReady ? hg_aocs_fetch_departments($link, $selectedCategoryOrgId) : [];

admin_panel_open(
    'Organigramas: categorias',
    '<span class="adm-flex-right-8">'
    . '<a class="btn" href="/organizations/justicia-metalica/org-chart" target="_blank">Ver organigrama</a>'
    . '</span>'
);
?>
<?php if (!empty($flash)): ?><div class="flash"><?php foreach ($flash as $m): $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?><div class="<?= $cl ?>"><?= hg_aocs_h($m['msg'] ?? '') ?></div><?php endforeach; ?></div><?php endif; ?>

<style>
.adm-org-schema-summary{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 12px 0}
.adm-org-schema-pill{padding:6px 10px;border-radius:999px;border:1px solid #17366e;background:#071b4a;color:#dfefff}
.adm-org-category-grid{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px 12px;align-items:end}
.adm-org-category-grid label{display:grid;gap:5px;color:#dfefff}
.adm-org-category-grid input,.adm-org-category-grid select,.adm-org-category-grid textarea{width:100%;box-sizing:border-box}
.adm-org-category-grid textarea{min-height:74px}
.adm-org-category-wide{grid-column:1/-1}
.adm-org-category-table{width:100%;border-collapse:collapse;margin-top:10px}
.adm-org-category-table th,.adm-org-category-table td{border-bottom:1px solid #17366e;padding:6px 7px;text-align:left;vertical-align:top}
.adm-org-category-swatch{display:inline-block;width:12px;height:12px;border-radius:999px;border:1px solid rgba(255,255,255,.65);vertical-align:middle;margin-right:6px}
@media(max-width:760px){.adm-org-category-grid{grid-template-columns:1fr}}
</style>

<div class="adm-org-schema-summary">
  <span class="adm-org-schema-pill">Departamentos: <?= $departmentsReady ? 'OK' : 'Ausente' ?><?= $departmentsReady ? ' (' . (int)$departmentsCount . ')' : '' ?></span>
  <span class="adm-org-schema-pill">Bridge cargos: <?= $bridgeReady ? 'OK' : 'Ausente' ?><?= $bridgeReady ? ' (' . (int)$bridgeCount . ')' : '' ?></span>
</div>

<p>Este panel ya no ejecuta setups ni semillas historicas. Solo muestra el estado actual y permite crear categorias si las tablas existen.</p>

<fieldset class="bioSeccion">
  <legend>&nbsp;Categorias para organigramas&nbsp;</legend>
  <?php if (!$departmentsReady): ?>
    <div class="err">No existe `dim_organization_departments` en esta base de datos. El panel queda en modo consulta.</div>
  <?php else: ?>
    <form method="get" class="adm-org-category-grid" style="margin-bottom:12px">
      <input type="hidden" name="s" value="admin_org_chart_schema">
      <label>
        Organizacion a editar
        <select name="category_org_id" onchange="this.form.submit()">
          <?php foreach ($organizations as $org): ?>
            <option value="<?= (int)$org['id'] ?>" <?= (int)$org['id'] === $selectedCategoryOrgId ? 'selected' : '' ?>>
              <?= hg_aocs_h((string)$org['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <div>
        <button class="btn" type="submit">Cargar</button>
        <?php
        $selectedOrgPretty = '';
        foreach ($organizations as $org) {
            if ((int)$org['id'] === $selectedCategoryOrgId) {
                $selectedOrgPretty = trim((string)($org['pretty_id'] ?? ''));
                break;
            }
        }
        ?>
        <?php if ($selectedOrgPretty !== ''): ?>
          <a class="btn" href="/organizations/<?= rawurlencode($selectedOrgPretty) ?>/org-chart" target="_blank">Ver organigrama</a>
        <?php endif; ?>
      </div>
    </form>

    <form method="post" class="adm-org-category-grid">
      <input type="hidden" name="csrf" value="<?= hg_aocs_h($csrf) ?>">
      <input type="hidden" name="schema_action" value="create_department">

      <label>
        Organizacion
        <select name="category_org_id">
          <?php foreach ($organizations as $org): ?>
            <option value="<?= (int)$org['id'] ?>" <?= (int)$org['id'] === $selectedCategoryOrgId ? 'selected' : '' ?>>
              <?= hg_aocs_h((string)$org['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Categoria padre
        <select name="parent_department_id">
          <option value="0">Sin padre / raiz</option>
          <?php foreach ($selectedOrgDepartments as $dept): ?>
            <option value="<?= (int)$dept['id'] ?>">
              <?= str_repeat('-- ', max(0, (int)$dept['hierarchy_level'])) . hg_aocs_h((string)$dept['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Nombre de categoria
        <input type="text" name="department_name" maxlength="150" required placeholder="Ej. Delegaciones territoriales">
      </label>

      <label>
        Pretty ID opcional
        <input type="text" name="department_pretty_id" maxlength="190" placeholder="Se genera automaticamente">
      </label>

      <label>
        Tipo
        <select name="department_type">
          <?php foreach ($departmentTypes as $typeKey => $typeLabel): ?>
            <option value="<?= hg_aocs_h($typeKey) ?>" <?= $typeKey === 'department' ? 'selected' : '' ?>>
              <?= hg_aocs_h($typeLabel) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Nivel visual
        <input type="number" name="hierarchy_level" min="0" max="99" value="1">
      </label>

      <label>
        Color
        <input type="color" name="department_color" value="#33cccc">
      </label>

      <label>
        Orden
        <input type="number" name="sort_order" value="0" placeholder="0 = automatico">
      </label>

      <label class="adm-org-category-wide">
        Descripcion
        <textarea name="department_description" placeholder="Texto breve que aparecera en la tarjeta de categoria"></textarea>
      </label>

      <div class="adm-org-category-wide">
        <button class="btn btn-green" type="submit">Crear categoria</button>
      </div>
    </form>

    <?php if (!empty($selectedOrgDepartments)): ?>
      <table class="adm-org-category-table">
        <thead>
          <tr>
            <th>Categoria</th>
            <th>Tipo</th>
            <th>Nivel</th>
            <th>Padre</th>
            <th>Pretty ID</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($selectedOrgDepartments as $dept): ?>
            <tr>
              <td><span class="adm-org-category-swatch" style="background:<?= hg_aocs_h((string)$dept['color']) ?>"></span><?= hg_aocs_h((string)$dept['name']) ?></td>
              <td><?= hg_aocs_h((string)($departmentTypes[(string)$dept['department_type']] ?? $dept['department_type'])) ?></td>
              <td><?= (int)$dept['hierarchy_level'] ?></td>
              <td><?= hg_aocs_h((string)($dept['parent_name'] ?: 'Raiz')) ?></td>
              <td><code><?= hg_aocs_h((string)$dept['pretty_id']) ?></code></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>
</fieldset>

<?php
admin_panel_close();
