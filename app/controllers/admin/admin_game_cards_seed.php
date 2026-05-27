<?php
// Admin: ejecutar seed del catalogo de cartas.

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../tools/seed_game_cards.php');

$csrfKey = 'csrf_admin_game_cards_seed';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');

function hg_agcs_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hg_agcs_csrf_ok(string $csrfKey): bool
{
    $token = (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $csrfKey)
        : ($token !== '' && isset($_SESSION[$csrfKey]) && hash_equals((string)$_SESSION[$csrfKey], $token));
}

$flash = [];
$stats = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hg_agcs_csrf_ok($csrfKey)) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina antes de reintentar.'];
    } else {
        $action = (string)($_POST['seed_action'] ?? '');
        if ($action === 'run' || $action === 'reset') {
            try {
                $stats = hg_gc_seed_run($link, $action === 'reset');
                $flash[] = ['type' => 'ok', 'msg' => $action === 'reset' ? 'Catalogo reiniciado y sembrado.' : 'Catalogo actualizado.'];
            } catch (Throwable $e) {
                $flash[] = ['type' => 'error', 'msg' => 'Seed fallido: ' . $e->getMessage()];
            }
        } else {
            $flash[] = ['type' => 'error', 'msg' => 'Accion no reconocida.'];
        }
    }
}

$currentTotal = 0;
if (function_exists('hg_gc_table_exists') && hg_gc_table_exists($link, 'fact_game_card_collection')) {
    if ($rs = $link->query('SELECT COUNT(*) AS total FROM fact_game_card_collection')) {
        $row = $rs->fetch_assoc();
        $currentTotal = (int)($row['total'] ?? 0);
        $rs->close();
    }
}

admin_panel_open(
    'Seed cartas del gacha',
    '<span class="adm-flex-right-8">'
    . '<a class="btn" href="/talim?s=admin_game_cards">Volver a cartas</a>'
    . '<a class="btn" href="/games/card-game" target="_blank" rel="noopener">Ver gacha</a>'
    . '</span>'
);
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m): $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?>
        <div class="<?= $cl ?>"><?= hg_agcs_h($m['msg'] ?? '') ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.adm-game-card-seed-actions{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}
.adm-game-card-seed-pills{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 12px}
.adm-game-card-seed-pill{padding:6px 10px;border-radius:999px;border:1px solid #17366e;background:#071b4a;color:#dfefff}
.adm-game-card-seed-log{white-space:pre-wrap;font-family:Consolas,monospace;font-size:12px;line-height:1.45;color:#d7e7ff;background:#06153a;border:1px solid #17366e;border-radius:8px;padding:10px;max-height:260px;overflow:auto}
</style>

<div class="adm-game-card-seed-pills">
    <span class="adm-game-card-seed-pill">Cartas actuales: <?= (int)$currentTotal ?></span>
    <span class="adm-game-card-seed-pill">Imagenes: personajes, episodios y temporadas leen su <code>image_url</code></span>
</div>

<p>Este panel ejecuta el mismo seed que el CLI. Actualiza el esquema, crea cartas nuevas y refresca nombre, texto, imagen, rareza y stats de las cartas ya vinculadas a sus fuentes.</p>

<div class="adm-game-card-seed-actions">
    <form method="post" class="adm-inline-form">
        <input type="hidden" name="csrf" value="<?= hg_agcs_h($csrf) ?>">
        <input type="hidden" name="seed_action" value="run">
        <button class="btn btn-green" type="submit">Actualizar catalogo</button>
    </form>
    <form method="post" class="adm-inline-form">
        <input type="hidden" name="csrf" value="<?= hg_agcs_h($csrf) ?>">
        <input type="hidden" name="seed_action" value="reset">
        <button class="btn btn-red" type="submit" onclick="return confirm('Esto borrara y recreara el catalogo de cartas. Continuar?')">Resetear y sembrar</button>
    </form>
</div>

<?php if (is_array($stats)): ?>
<fieldset class="bioSeccion">
    <legend>&nbsp;Ultima ejecucion&nbsp;</legend>
    <div class="adm-game-card-seed-log"><?= hg_agcs_h(
        "Schema ready.\n"
        . (!empty($stats['reset']) ? 'Catalog reset: ' . (int)$stats['deleted'] . " cards deleted.\n" : '')
        . 'Excluded chronicle cards deactivated: ' . (int)$stats['excluded_deactivated'] . "\n"
        . 'New cards inserted: ' . (int)$stats['inserted'] . "\n"
        . 'Catalog total: ' . (int)$stats['total']
    ) ?></div>
</fieldset>
<?php endif; ?>

<?php
admin_panel_close();
