<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/admin_characters_service.php');
include_once(__DIR__ . '/../../domains/organizations/admin_canonical.php');

$csrfKey = 'csrf_admin_character_affiliations_canonical';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');

function acac_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function acac_csrf_ok(string $csrfKey): bool
{
    $token = (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $csrfKey)
        : ($token !== '' && isset($_SESSION[$csrfKey]) && hash_equals((string)$_SESSION[$csrfKey], $token));
}

$flash = [];
$execution = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!acac_csrf_ok($csrfKey)) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina antes de reintentar.'];
    } else {
        $mode = (string)($_POST['canonical_action'] ?? '');
        if ($mode !== 'dry_run' && $mode !== 'apply') {
            $flash[] = ['type' => 'error', 'msg' => 'Accion no valida.'];
        } else {
            $execution = acac_run_canonicalizer($link, $mode === 'apply');
            $flash[] = [
                'type' => ($mode === 'apply') ? 'ok' : 'info',
                'msg' => ($mode === 'apply')
                    ? 'Canonicalizacion ejecutada.'
                    : 'Dry-run completado. No se han aplicado cambios.',
            ];
        }
    }
}

admin_panel_open(
    'Canonizar afiliaciones de personajes',
    '<span class="adm-flex-right-8">'
    . '<a class="btn" href="/talim?s=admin_bridges">Ver bridges</a>'
    . '<a class="btn" href="/talim?s=admin_characters">Volver a personajes</a>'
    . '</span>'
);
?>
<?php if (!empty($flash)): ?><div class="flash"><?php foreach ($flash as $m): $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?><div class="<?= $cl ?>"><?= acac_h($m['msg'] ?? '') ?></div><?php endforeach; ?></div><?php endif; ?>

<style>
.adm-charcanon-actions{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}
.adm-charcanon-log{white-space:pre-wrap;font-family:Consolas,monospace;font-size:12px;line-height:1.45;color:#d7e7ff;background:#06153a;border:1px solid #17366e;border-radius:8px;padding:10px;max-height:420px;overflow:auto}
.adm-charcanon-summary{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 12px 0}
.adm-charcanon-pill{padding:6px 10px;border-radius:999px;border:1px solid #17366e;background:#071b4a;color:#dfefff}
</style>

<p>Regla canonica aplicada por esta pantalla:</p>
<ul>
  <li>Con manada: manda <code>grupo -&gt; organizacion</code>.</li>
  <li>Sin manada: manda <code>personaje -&gt; organizacion</code>.</li>
</ul>

<div class="adm-charcanon-actions">
  <form method="post" class="adm-inline-form">
    <input type="hidden" name="csrf" value="<?= acac_h($csrf) ?>">
    <input type="hidden" name="canonical_action" value="dry_run">
    <button class="btn" type="submit">Ver dry-run</button>
  </form>
  <form method="post" class="adm-inline-form">
    <input type="hidden" name="csrf" value="<?= acac_h($csrf) ?>">
    <input type="hidden" name="canonical_action" value="apply">
    <button class="btn btn-green" type="submit" onclick="return confirm('Aplicar la canonicalizacion de afiliaciones?')">Aplicar cambios</button>
  </form>
</div>

<?php if (is_array($execution)): ?>
  <div class="adm-charcanon-summary">
    <span class="adm-charcanon-pill">Personajes revisados: <?= (int)($execution['summary']['characters_considered'] ?? 0) ?></span>
    <span class="adm-charcanon-pill">Conflictos grupo-&gt;clan: <?= (int)($execution['summary']['group_owner_conflicts'] ?? 0) ?></span>
    <span class="adm-charcanon-pill">PJ con varias manadas: <?= (int)($execution['summary']['character_group_conflicts'] ?? 0) ?></span>
    <span class="adm-charcanon-pill">PJ con varios clanes directos: <?= (int)($execution['summary']['character_org_conflicts'] ?? 0) ?></span>
    <span class="adm-charcanon-pill">PJ resincronizados: <?= (int)($execution['summary']['character_org_synced'] ?? 0) ?></span>
    <span class="adm-charcanon-pill">Avisos: <?= (int)($execution['summary']['warnings'] ?? 0) ?></span>
  </div>

  <?php if (!empty($execution['messages'])): ?>
    <fieldset class="bioSeccion">
      <legend>&nbsp;Plan / resultado&nbsp;</legend>
      <div class="adm-charcanon-log"><?= acac_h(implode("\n", (array)$execution['messages'])) ?></div>
    </fieldset>
  <?php endif; ?>

  <?php if (!empty($execution['warnings'])): ?>
    <fieldset class="bioSeccion">
      <legend>&nbsp;Avisos&nbsp;</legend>
      <div class="adm-charcanon-log"><?= acac_h(implode("\n", (array)$execution['warnings'])) ?></div>
    </fieldset>
  <?php endif; ?>
<?php endif; ?>

<?php
admin_panel_close();
