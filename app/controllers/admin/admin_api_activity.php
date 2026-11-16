<?php
require_once __DIR__ . '/../../helpers/admin_auth.php';
hg_admin_send_security_headers();
if (!hg_admin_is_authenticated()) {
    http_response_code(403);
    return;
}
require_once __DIR__ . '/../../domains/api/audit.php';
require_once __DIR__ . '/../../partials/admin/admin_styles.php';

$day = hg_api_audit_day(filter_input(INPUT_GET, 'day', FILTER_UNSAFE_RAW), time());
$clientLabels = ['chatgpt-oauth' => 'ChatGPT · OAuth', 'rest-shared' => 'REST · credencial compartida',
    'unauthenticated' => 'Sin autenticar', 'unchecked' => 'Sin comprobar (método rechazado)'];
$requestedClient = filter_input(INPUT_GET, 'client', FILTER_UNSAFE_RAW);
$client = is_string($requestedClient) && isset($clientLabels[$requestedClient]) ? $requestedClient : '';
$requestedOutcome = filter_input(INPUT_GET, 'outcome', FILTER_UNSAFE_RAW);
$outcome = in_array($requestedOutcome, ['ok', 'error'], true) ? $requestedOutcome : '';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$report = hg_api_audit_report(hg_api_audit_directory(), $day, $client, $outcome, is_int($page) ? $page : 1);
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$url = static fn(int $number): string => '/talim?' . http_build_query([
    's' => 'admin_api_activity', 'day' => $day, 'client' => $client, 'outcome' => $outcome, 'page' => $number]);
$timeLabel = static function(string $value): string {
    try { return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Europe/Madrid'))->format('d/m/Y H:i:s'); }
    catch (Exception $ignored) { return '—'; }
};
admin_panel_open('Actividad de la API');
?>
<p class="adm-note">Peticiones REST y MCP. Conservación: 30 días. Las horas se muestran en Madrid; el día del filtro corresponde a UTC.</p>
<form class="adm-toolbar" method="get" action="/talim">
  <input type="hidden" name="s" value="admin_api_activity">
  <label>Día (UTC)
    <input class="inp" type="date" name="day" value="<?= $h($day) ?>" min="<?= $h(gmdate('Y-m-d', time() - 29 * 86400)) ?>" max="<?= $h(gmdate('Y-m-d')) ?>">
  </label>
  <label>Cliente <select class="inp" name="client"><option value="">Todos</option>
    <?php foreach ($clientLabels as $key => $label): ?><option value="<?= $h($key) ?>" <?= $client === $key ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
  </select></label>
  <label>Resultado <select class="inp" name="outcome">
    <option value="">Todos</option><option value="ok" <?= $outcome === 'ok' ? 'selected' : '' ?>>Correcto</option><option value="error" <?= $outcome === 'error' ? 'selected' : '' ?>>Error</option>
  </select></label>
  <button class="btn" type="submit">Ver actividad</button>
</form>
<?php if (!$report['ready']): ?>
  <p class="adm-callout">El registro no está disponible. Las peticiones a la API siguen funcionando; revisa la instalación del registro privado.</p>
<?php else: ?>
  <?php if ($report['capped']): ?><p class="adm-callout">Se ha alcanzado el límite diario de almacenamiento. La actividad de este día puede estar incompleta.</p><?php endif; ?>
  <div class="adm-usage-summary">
    <div class="adm-usage-card"><strong><?= (int)$report['total'] ?></strong><span>Peticiones</span></div>
    <div class="adm-usage-card"><strong><?= (int)$report['errors'] ?></strong><span>Errores</span></div>
    <div class="adm-usage-card"><strong><?= $h(number_format($report['total'] ? $report['duration_ms'] / $report['total'] : 0, 1)) ?> ms</strong><span>Duración media</span></div>
    <div class="adm-usage-card"><strong><?= $h(number_format($report['bytes'] / 1024, 1)) ?> KiB</strong><span>Respuestas JSON</span></div>
  </div>
  <p class="adm-note">Resumen del día y filtros seleccionados. OAuth identifica a ChatGPT con la autorización de administrador actual. Una credencial REST compartida no permite distinguir personas. El origen es un identificador anónimo de la conexión; el tipo de cliente procede del navegador o programa declarado y no acredita identidad.</p>
  <?php if ($report['clients']): ?><ul><?php foreach ($report['clients'] as $name => $count): ?><li><?= $h($clientLabels[$name] ?? 'Desconocido') ?>: <?= (int)$count ?></li><?php endforeach; ?></ul><?php endif; ?>
  <div class="adm-table-scroll" tabindex="0" aria-label="Actividad REST y MCP">
    <table class="table adm-wide-table">
      <thead><tr><th>Cuándo (Madrid)</th><th>Cliente</th><th>Origen / programa</th><th>Petición</th><th>Recurso / filtros</th><th>Resultado</th><th>Duración</th><th>Respuesta</th></tr></thead>
      <tbody>
      <?php foreach ($report['rows'] as $row): ?>
        <tr>
          <td><?= $h($timeLabel((string)($row['at'] ?? ''))) ?></td>
          <td><?= $h($clientLabels[$row['client'] ?? ''] ?? 'Desconocido') ?></td>
          <td><code><?= $h($row['origin'] ?? '') ?></code><br><?= $h($row['agent'] ?? '') ?></td>
          <td><?= $h(($row['method'] ?? '') . ' ' . ($row['endpoint'] ?? '')) ?><br><?= $h($row['operation'] ?? '') ?></td>
          <td><?= $h($row['resource'] ?? '') ?><br><?= $h(implode(', ', is_array($row['filters'] ?? null) ? $row['filters'] : [])) ?></td>
          <td><?= (int)($row['status'] ?? 0) ?> · <?= ($row['outcome'] ?? '') === 'error' ? 'Error' : 'Correcto' ?></td>
          <td><?= $h($row['duration_ms'] ?? 0) ?> ms</td>
          <td><?= (int)($row['response_bytes'] ?? 0) ?> B</td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$report['rows']): ?><tr><td colspan="8">No hay peticiones registradas para este día y estos filtros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <nav class="adm-toolbar" aria-label="Páginas de actividad">
    <?php if ($report['page'] > 1): ?><a class="btn" href="<?= $h($url($report['page'] - 1)) ?>">Más recientes</a><?php endif; ?>
    <span>Página <?= (int)$report['page'] ?> de <?= (int)$report['pages'] ?> · 50 peticiones por página</span>
    <?php if ($report['page'] < $report['pages']): ?><a class="btn" href="<?= $h($url($report['page'] + 1)) ?>">Anteriores</a><?php endif; ?>
  </nav>
<?php endif; ?>
<p class="adm-note">Se registran nombres de filtros, pero no sus valores, búsquedas, credenciales, cabeceras de autorización ni contenidos de las respuestas. Solo se incluyen peticiones que llegan a la aplicación. El registro comenzó al instalar esta función.</p>
<?php admin_panel_close(); ?>
