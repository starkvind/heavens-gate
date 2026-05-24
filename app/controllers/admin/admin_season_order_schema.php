<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../tools/season_order_schema_20260522.php');

$csrfKey = 'csrf_admin_season_order_schema';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');

function hg_asos_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hg_asos_csrf_ok(string $csrfKey): bool
{
    $token = (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $csrfKey)
        : ($token !== '' && isset($_SESSION[$csrfKey]) && hash_equals((string)$_SESSION[$csrfKey], $token));
}

function hg_asos_count_rows(mysqli $link, string $table): int
{
    if (!hg_so_schema_table_exists($link, $table)) {
        return 0;
    }
    $rs = $link->query("SELECT COUNT(*) AS c FROM `$table`");
    if (!$rs) {
        return 0;
    }
    $row = $rs->fetch_assoc();
    $rs->close();
    return (int)($row['c'] ?? 0);
}

$flash = [];
$execution = ['messages' => [], 'errors' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hg_asos_csrf_ok($csrfKey)) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina antes de reintentar.'];
    } else {
        $action = (string)($_POST['schema_action'] ?? '');
        if ($action === 'apply') {
            $execution = hg_so_schema_apply($link, true);
            $flash[] = empty($execution['errors'])
                ? ['type' => 'ok', 'msg' => 'Bridge de orden creado o reparado.']
                : ['type' => 'error', 'msg' => 'No se pudo completar la preparacion del bridge.'];
        }
    }
}

$dryRun = hg_so_schema_apply($link, false);
$tableReady = hg_so_schema_table_exists($link, 'bridge_season_order_nodes');
$rowCount = hg_asos_count_rows($link, 'bridge_season_order_nodes');

admin_panel_open(
    'Orden de temporadas: schema',
    '<span class="adm-flex-right-8">'
    . '<form method="post" class="adm-inline-form"><input type="hidden" name="csrf" value="' . hg_asos_h($csrf) . '"><input type="hidden" name="schema_action" value="apply"><button class="btn btn-green" type="submit">Crear/reparar bridge</button></form>'
    . '</span>'
);
?>
<?php if (!empty($flash)): ?><div class="flash"><?php foreach ($flash as $m): $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?><div class="<?= $cl ?>"><?= hg_asos_h($m['msg'] ?? '') ?></div><?php endforeach; ?></div><?php endif; ?>

<style>
.adm-season-order-schema-pills{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 12px}
.adm-season-order-schema-pill{padding:6px 10px;border-radius:999px;border:1px solid #17366e;background:#071b4a;color:#dfefff}
.adm-season-order-schema-log{white-space:pre-wrap;font-family:Consolas,monospace;font-size:12px;line-height:1.45;color:#d7e7ff;background:#06153a;border:1px solid #17366e;border-radius:8px;padding:10px;max-height:360px;overflow:auto}
</style>

<div class="adm-season-order-schema-pills">
  <span class="adm-season-order-schema-pill">Tabla: <?= $tableReady ? 'OK' : 'Pendiente' ?><?= $tableReady ? ' (' . (int)$rowCount . ' filas)' : '' ?></span>
</div>

<p>Esta pantalla prepara desde navegador la tabla `bridge_season_order_nodes`, para que podamos editar recorridos narrativos sin tocar la base de datos desde el IDE.</p>

<p><a class="btn" href="/talim?s=admin_season_order">Abrir editor de orden</a></p>

<?php if (!empty($execution['messages'])): ?>
<fieldset class="bioSeccion">
  <legend>&nbsp;Ultima ejecucion&nbsp;</legend>
  <div class="adm-season-order-schema-log"><?= hg_asos_h(implode("\n", (array)$execution['messages'])) ?></div>
</fieldset>
<?php endif; ?>

<?php if (!empty($execution['errors'])): ?>
<fieldset class="bioSeccion">
  <legend>&nbsp;Errores&nbsp;</legend>
  <?php foreach ((array)$execution['errors'] as $error): ?>
    <div class="err">
      <strong><?= hg_asos_h((string)($error['label'] ?? 'error')) ?></strong><br>
      <?= hg_asos_h((string)($error['error'] ?? 'Error SQL')) ?>
    </div>
  <?php endforeach; ?>
</fieldset>
<?php endif; ?>

<fieldset class="bioSeccion">
  <legend>&nbsp;Plan SQL&nbsp;</legend>
  <div class="adm-season-order-schema-log"><?= hg_asos_h(implode("\n", (array)$dryRun['messages'])) ?></div>
</fieldset>
<?php
admin_panel_close();
