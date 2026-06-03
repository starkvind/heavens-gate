<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');

function hg_asos_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hg_asos_table_exists(mysqli $link, string $table): bool
{
    $stmt = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count > 0;
}

function hg_asos_count_rows(mysqli $link, string $table): int
{
    if (!hg_asos_table_exists($link, $table)) {
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

$tableReady = hg_asos_table_exists($link, 'bridge_season_order_nodes');
$rowCount = hg_asos_count_rows($link, 'bridge_season_order_nodes');

admin_panel_open(
    'Orden de temporadas: bridge',
    '<span class="adm-flex-right-8">'
    . '<a class="btn" href="/talim?s=admin_season_order">Abrir editor</a>'
    . '</span>'
);
?>

<style>
.adm-season-order-schema-pills{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 12px}
.adm-season-order-schema-pill{padding:6px 10px;border-radius:999px;border:1px solid #17366e;background:#071b4a;color:#dfefff}
</style>

<div class="adm-season-order-schema-pills">
  <span class="adm-season-order-schema-pill">Tabla: <?= $tableReady ? 'OK' : 'Ausente' ?><?= $tableReady ? ' (' . (int)$rowCount . ' filas)' : '' ?></span>
</div>

<?php if ($tableReady): ?>
  <p>El bridge `bridge_season_order_nodes` ya existe. Esta pantalla queda solo como referencia de estado.</p>
<?php else: ?>
  <div class="err">No existe `bridge_season_order_nodes` en esta base de datos. El editor de orden no estara disponible hasta que esa tabla exista.</div>
<?php endif; ?>

<?php
admin_panel_close();
