<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../domains/chapters/admin_season_order_schema.php');

function hg_asos_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
