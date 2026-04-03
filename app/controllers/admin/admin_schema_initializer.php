<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../tools/schema_initializer.php');

$csrfKey = 'csrf_admin_schema_initializer';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');

function hg_asi_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hg_asi_csrf_ok(string $csrfKey): bool
{
    $token = (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $csrfKey)
        : (is_string($token) && $token !== '' && isset($_SESSION[$csrfKey]) && hash_equals((string)$_SESSION[$csrfKey], $token));
}

function hg_asi_phase_label(string $phase): string
{
    $map = [
        'create_tables' => 'Crear tablas',
        'add_columns' => 'Agregar columnas',
        'add_indexes' => 'Agregar indices',
        'add_foreign_keys' => 'Agregar claves foraneas',
        'views' => 'Crear vistas',
        'seed_config' => 'Sembrar configuracion',
    ];
    return (string)($map[$phase] ?? $phase);
}

$flash = [];
$execution = ['executed' => [], 'errors' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['schema_action'] ?? '') === 'apply') {
    if (!hg_asi_csrf_ok($csrfKey)) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina antes de reintentar.'];
    } else {
        $before = hg_schema_analyze($link);
        if ((int)($before['total_pending'] ?? 0) <= 0) {
            $flash[] = ['type' => 'info', 'msg' => 'No hay acciones pendientes.'];
        } else {
            $execution = hg_schema_apply($link, $before);
            $executedCount = count((array)$execution['executed']);
            $errorCount = count((array)$execution['errors']);
            if ($errorCount > 0) {
                $firstError = (string)($execution['errors'][0]['error'] ?? 'Error SQL');
                $flash[] = ['type' => 'error', 'msg' => 'La inicializacion no pudo completarse. ' . $firstError];
            } else {
                $flash[] = ['type' => 'ok', 'msg' => 'Inicializacion completada. Acciones ejecutadas: ' . $executedCount . '.'];
            }
        }
    }
}

$analysis = hg_schema_analyze($link);
$stats = (array)($analysis['stats'] ?? []);
$plan = (array)($analysis['plan'] ?? []);
$definition = (array)($analysis['definition'] ?? []);

admin_panel_open('Inicializador de esquema', '<span class="adm-flex-right-8"><form method="post" class="adm-inline-form"><input type="hidden" name="csrf" value="' . hg_asi_h($csrf) . '"><input type="hidden" name="schema_action" value="apply"><button class="btn btn-green" type="submit" onclick="return confirm(\'Esto intentara crear y reparar tablas, indices, FKs, vistas y configuracion segura. Continuar?\')">Aplicar cambios pendientes</button></form></span>');
?>
<?php if (!empty($flash)): ?><div class="flash"><?php foreach ($flash as $m): $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?><div class="<?= $cl ?>"><?= hg_asi_h($m['msg'] ?? '') ?></div><?php endforeach; ?></div><?php endif; ?>
<style>
.adm-schema-summary{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 12px 0}
.adm-schema-pill{padding:6px 10px;border-radius:999px;border:1px solid #17366e;background:#071b4a;color:#dfefff}
.adm-schema-meta{margin:0 0 14px 0;color:#d8ddff}
.adm-schema-table-wrap{max-height:72vh;overflow:auto;border:1px solid #000088;border-radius:8px}
.adm-schema-sql{max-width:520px;white-space:pre-wrap;word-break:break-word;font-family:Consolas,monospace;font-size:11px;line-height:1.45;color:#d7e7ff}
.adm-schema-phase{font-weight:700}
</style>
<div class="adm-schema-meta">
  <p><strong>Definicion embebida:</strong> <?= hg_asi_h((string)($definition['generated_from'] ?? 'schema_definition.php')) ?></p>
  <p><strong>Generada:</strong> <?= hg_asi_h((string)($definition['generated_at'] ?? 'n/d')) ?></p>
  <p>Este modulo no lee dumps en runtime. Analiza la base de datos actual frente a la definicion PHP embebida del esquema y propone un plan reproducible de reparacion.</p>
  <p>CLI equivalente: <code>php app/tools/schema_initializer.php</code> para dry-run y <code>php app/tools/schema_initializer.php --apply</code> para ejecutar.</p>
</div>
<div class="adm-schema-summary">
  <span class="adm-schema-pill">Pendientes: <?= (int)($analysis['total_pending'] ?? 0) ?></span>
  <span class="adm-schema-pill">Tablas: <?= (int)($stats['tables_missing'] ?? 0) ?></span>
  <span class="adm-schema-pill">Columnas: <?= (int)($stats['columns_missing'] ?? 0) ?></span>
  <span class="adm-schema-pill">Indices: <?= (int)($stats['indexes_missing'] ?? 0) ?></span>
  <span class="adm-schema-pill">FKs: <?= (int)($stats['foreign_keys_missing'] ?? 0) ?></span>
  <span class="adm-schema-pill">Vistas: <?= (int)($stats['views_missing'] ?? 0) ?></span>
  <span class="adm-schema-pill">Config segura: <?= (int)($stats['config_missing'] ?? 0) ?></span>
</div>

<?php if (!empty($execution['executed'])): ?>
<fieldset class="bioSeccion">
  <legend>&nbsp;Ultima ejecucion&nbsp;</legend>
  <div class="adm-table-wrap">
    <table class="table">
      <thead><tr><th>Fase</th><th>Tabla</th><th>Objetivo</th></tr></thead>
      <tbody>
      <?php foreach ((array)$execution['executed'] as $entry): ?>
        <tr>
          <td><?= hg_asi_h(hg_asi_phase_label((string)($entry['phase'] ?? ''))) ?></td>
          <td><?= hg_asi_h((string)($entry['table'] ?? '-')) ?></td>
          <td><?= hg_asi_h((string)($entry['target'] ?? '-')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</fieldset>
<?php endif; ?>

<?php if (!empty($execution['errors'])): ?>
<fieldset class="bioSeccion">
  <legend>&nbsp;Errores de ejecucion&nbsp;</legend>
  <?php foreach ((array)$execution['errors'] as $error): ?>
    <div class="err">
      <strong><?= hg_asi_h((string)(($error['entry']['target'] ?? 'error'))) ?></strong><br>
      <?= hg_asi_h((string)($error['error'] ?? 'Error SQL')) ?>
    </div>
  <?php endforeach; ?>
</fieldset>
<?php endif; ?>

<fieldset class="bioSeccion">
  <legend>&nbsp;Plan actual&nbsp;</legend>
  <?php if (empty($plan)): ?>
    <div class="ok">El esquema ya esta alineado con la definicion embebida. No hay acciones pendientes.</div>
  <?php else: ?>
    <div class="adm-schema-table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th class="adm-w-180">Fase</th>
            <th class="adm-w-220">Tabla</th>
            <th class="adm-w-220">Objetivo</th>
            <th>SQL previsto</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($plan as $entry): ?>
          <tr>
            <td class="adm-schema-phase"><?= hg_asi_h(hg_asi_phase_label((string)($entry['phase'] ?? ''))) ?></td>
            <td><?= hg_asi_h((string)($entry['table'] ?? '-')) ?></td>
            <td><?= hg_asi_h((string)($entry['target'] ?? '-')) ?></td>
            <td><div class="adm-schema-sql"><?= hg_asi_h((string)($entry['sql'] ?? ('INSERT config ' . ($entry['target'] ?? '')))) ?></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</fieldset>
<?php
admin_panel_close();
