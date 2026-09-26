<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/admin_sections.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../domains/admin_usage/queries.php');

if (!hg_admin_require_db($link)) {
    return;
}

if (!function_exists('hg_admin_usage_h')) {
    function hg_admin_usage_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$windowDaysRaw = filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT);
$windowDays = max(7, min(365, is_int($windowDaysRaw) ? $windowDaysRaw : 30));
$report = hg_admin_usage_report($link, $windowDays);

$tracked = [];
foreach (($report['rows'] ?? []) as $row) {
    $key = (string)($row['section_key'] ?? '');
    if ($key !== '') {
        $tracked[$key] = $row;
    }
}

$catalog = ['admin_main' => ['target' => 'admin_main.php', 'normal' => true]];
foreach (hg_admin_section_registry() as $key => $entry) {
    if ($key === 'logout' || empty($entry['normal'])) {
        continue;
    }
    $catalog[$key] = $entry;
}

$rows = [];
foreach ($catalog as $key => $entry) {
    $row = $tracked[$key] ?? [
        'section_key' => $key,
        'total_views' => 0,
        'window_views' => 0,
        'active_days' => 0,
        'window_active_days' => 0,
        'first_seen_at' => null,
        'last_seen_at' => null,
    ];
    $row['target'] = (string)($entry['target'] ?? '');
    $rows[] = $row;
}
foreach ($tracked as $key => $row) {
    if (!isset($catalog[$key])) {
        $row['target'] = '(ruta histórica/no registrada)';
        $rows[] = $row;
    }
}

usort($rows, static function (array $a, array $b): int {
    $cmp = ((int)$b['window_views']) <=> ((int)$a['window_views']);
    if ($cmp !== 0) return $cmp;
    $cmp = ((int)$b['total_views']) <=> ((int)$a['total_views']);
    if ($cmp !== 0) return $cmp;
    return strcmp((string)$a['section_key'], (string)$b['section_key']);
});

$overview = $report['overview'] ?? [];
$actions = '<span class="adm-flex-right-8">'
    . '<a class="btn" href="/talim?s=admin_usage&days=30">30 días</a>'
    . '<a class="btn" href="/talim?s=admin_usage&days=90">90 días</a>'
    . '<a class="btn" href="/talim?s=admin_usage&days=365">365 días</a>'
    . '</span>';
admin_panel_open('Uso del Admin', $actions);
?>

<?php if (empty($report['ready'])): ?>
  <div class="adm-callout">
    <strong>Telemetría pendiente de instalar.</strong>
    Ejecuta <code>sql/2026-09-24_admin_section_usage.sql</code> una sola vez sobre la base de datos.
    Hasta entonces el Admin funciona con normalidad, pero no se registran accesos.
  </div>
<?php else: ?>
  <div class="adm-usage-summary">
    <div class="adm-usage-card"><strong><?= (int)($overview['window_views'] ?? 0) ?></strong><span>visitas · <?= (int)$windowDays ?> días</span></div>
    <div class="adm-usage-card"><strong><?= (int)($overview['total_views'] ?? 0) ?></strong><span>visitas totales</span></div>
    <div class="adm-usage-card"><strong><?= (int)($overview['sections_used'] ?? 0) ?></strong><span>secciones usadas</span></div>
    <div class="adm-usage-card"><strong><?= (int)($overview['active_days'] ?? 0) ?></strong><span>días con actividad</span></div>
  </div>

  <p class="adm-note">
    Solo cuenta cargas completas GET del Admin. No cuenta AJAX, guardados POST, IP, navegador ni identidad.
    La telemetría empieza cuando se instala la migración.
  </p>

  <div class="adm-toolbar adm-usage-toolbar">
    <label>Filtrar
      <input class="inp" type="search" id="adminUsageFilter" placeholder="Sección o fichero...">
    </label>
    <span class="adm-note">Orden: uso en <?= (int)$windowDays ?> días → uso total.</span>
  </div>

  <div class="adm-table-scroll adm-sticky-actions" tabindex="0" aria-label="Uso de secciones Admin">
    <table class="table adm-wide-table" id="adminUsageTable">
      <thead>
        <tr>
          <th>Sección</th>
          <th>Controlador</th>
          <th><?= (int)$windowDays ?>d</th>
          <th>Total</th>
          <th>Días <?= (int)$windowDays ?>d</th>
          <th>Días total</th>
          <th>Último acceso</th>
          <th>Primer acceso</th>
          <th class="adm-th-actions" title="Abrir">Acc.</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <?php
          $section = (string)$row['section_key'];
          $href = $section === 'admin_main' ? '/talim' : '/talim?s=' . rawurlencode($section);
          $search = strtolower($section . ' ' . (string)$row['target']);
        ?>
        <tr data-usage-search="<?= hg_admin_usage_h($search) ?>">
          <td><strong><?= hg_admin_usage_h($section) ?></strong></td>
          <td><?= hg_admin_usage_h($row['target']) ?></td>
          <td><?= (int)$row['window_views'] ?></td>
          <td><?= (int)$row['total_views'] ?></td>
          <td><?= (int)$row['window_active_days'] ?></td>
          <td><?= (int)$row['active_days'] ?></td>
          <td><?= hg_admin_usage_h($row['last_seen_at'] ?: '—') ?></td>
          <td><?= hg_admin_usage_h($row['first_seen_at'] ?: '—') ?></td>
          <td class="adm-cell-actions"><a class="btn adm-icon-btn" href="<?= hg_admin_usage_h($href) ?>" title="Abrir" aria-label="Abrir">↗</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <h3>Actividad diaria · <?= (int)$windowDays ?> días</h3>
  <?php
    $maxDaily = 1;
    foreach (($report['daily'] ?? []) as $day) {
        $maxDaily = max($maxDaily, (int)($day['views'] ?? 0));
    }
  ?>
  <div class="adm-usage-days">
    <?php foreach (($report['daily'] ?? []) as $day): ?>
      <?php $pct = max(2, (int)round(((int)$day['views'] / $maxDaily) * 100)); ?>
      <div class="adm-usage-day">
        <span><?= hg_admin_usage_h($day['access_date']) ?></span>
        <div class="adm-usage-bar-track"><i class="adm-usage-bar" style="--adm-usage-pct:<?= (int)$pct ?>%"></i></div>
        <strong><?= (int)$day['views'] ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if (empty($report['daily'])): ?><p class="adm-note">Todavía no hay actividad registrada.</p><?php endif; ?>
  </div>
<?php endif; ?>

<script>
(function () {
  var input = document.getElementById('adminUsageFilter');
  var table = document.getElementById('adminUsageTable');
  if (!input || !table) return;
  input.addEventListener('input', function () {
    var q = String(input.value || '').toLowerCase().trim();
    Array.prototype.forEach.call(table.tBodies[0].rows, function (row) {
      var haystack = String(row.getAttribute('data-usage-search') || '');
      row.hidden = q !== '' && haystack.indexOf(q) === -1;
    });
  });
}());
</script>
