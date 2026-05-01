<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/admin_characters_service.php');

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

function acac_fetch_active_map(mysqli $link, string $table, string $leftCol, string $rightCol): array
{
    $rows = [];
    if (!pjs_table_exists($link, $table)) {
        return $rows;
    }

    $hasActive = pjs_table_has_column($link, $table, 'is_active');
    $hasUpdated = pjs_table_has_column($link, $table, 'updated_at');
    $hasCreated = pjs_table_has_column($link, $table, 'created_at');

    $sql = "SELECT `$leftCol` AS left_id, `$rightCol` AS right_id FROM `$table`";
    if ($hasActive) {
        $sql .= " WHERE (is_active = 1 OR is_active IS NULL)";
    }
    $sql .= " ORDER BY `$leftCol` ASC";
    if ($hasUpdated) {
        $sql .= ", updated_at DESC";
    }
    if ($hasCreated) {
        $sql .= ", created_at DESC";
    }
    $sql .= ", `$rightCol` DESC";

    if (!($rs = $link->query($sql))) {
        return $rows;
    }

    while ($row = $rs->fetch_assoc()) {
        $leftId = (int)($row['left_id'] ?? 0);
        $rightId = (int)($row['right_id'] ?? 0);
        if ($leftId <= 0 || $rightId <= 0) {
            continue;
        }
        if (!isset($rows[$leftId])) {
            $rows[$leftId] = ['winner' => $rightId, 'all' => [$rightId], 'extras' => []];
            continue;
        }
        $rows[$leftId]['all'][] = $rightId;
        $rows[$leftId]['extras'][] = $rightId;
    }

    $rs->close();
    return $rows;
}

function acac_deactivate_other_pairs(mysqli $link, string $table, string $leftCol, string $rightCol, int $leftId, int $winnerRightId): bool
{
    $hasActive = pjs_table_has_column($link, $table, 'is_active');
    if ($hasActive) {
        $sql = "UPDATE `$table` SET is_active = 0 WHERE `$leftCol` = ? AND `$rightCol` <> ? AND (is_active = 1 OR is_active IS NULL)";
    } else {
        $sql = "DELETE FROM `$table` WHERE `$leftCol` = ? AND `$rightCol` <> ?";
    }
    $st = $link->prepare($sql);
    if (!$st) {
        return false;
    }
    $st->bind_param('ii', $leftId, $winnerRightId);
    $ok = $st->execute();
    $st->close();
    return $ok;
}

function acac_upsert_active_character_org(mysqli $link, int $characterId, int $organizationId): bool
{
    $st = $link->prepare("
        INSERT INTO bridge_characters_organizations (character_id, organization_id, is_active, role)
        VALUES (?, ?, 1, '')
        ON DUPLICATE KEY UPDATE is_active = 1
    ");
    if (!$st) {
        return false;
    }
    $st->bind_param('ii', $characterId, $organizationId);
    $ok = $st->execute();
    $st->close();
    return $ok;
}

function acac_run_canonicalizer(mysqli $link, bool $apply): array
{
    $result = [
        'summary' => [
            'group_owner_conflicts' => 0,
            'character_group_conflicts' => 0,
            'character_org_conflicts' => 0,
            'character_org_synced' => 0,
            'warnings' => 0,
            'characters_considered' => 0,
        ],
        'messages' => [],
        'warnings' => [],
    ];

    $requiredTables = [
        'bridge_characters_groups',
        'bridge_characters_organizations',
        'bridge_organizations_groups',
    ];
    foreach ($requiredTables as $table) {
        if (!pjs_table_exists($link, $table)) {
            $result['warnings'][] = 'Falta la tabla `' . $table . '`.';
            $result['summary']['warnings']++;
        }
    }
    if (!empty($result['warnings'])) {
        return $result;
    }

    $groupOwners = acac_fetch_active_map($link, 'bridge_organizations_groups', 'group_id', 'organization_id');
    $characterGroups = acac_fetch_active_map($link, 'bridge_characters_groups', 'character_id', 'group_id');
    $characterOrgs = acac_fetch_active_map($link, 'bridge_characters_organizations', 'character_id', 'organization_id');

    foreach ($groupOwners as $groupId => $info) {
        if (empty($info['extras'])) {
            continue;
        }
        $result['summary']['group_owner_conflicts']++;
        $result['messages'][] = 'Manada #' . $groupId . ': se conservara el clan #' . (int)$info['winner'] . ' y se desactivaran ' . count($info['extras']) . ' vinculos activos antiguos.';
        if ($apply) {
            acac_deactivate_other_pairs($link, 'bridge_organizations_groups', 'group_id', 'organization_id', (int)$groupId, (int)$info['winner']);
        }
    }

    $allCharacterIds = array_unique(array_merge(array_keys($characterGroups), array_keys($characterOrgs)));
    sort($allCharacterIds, SORT_NUMERIC);
    $result['summary']['characters_considered'] = count($allCharacterIds);

    foreach ($allCharacterIds as $characterId) {
        $activeGroup = (int)($characterGroups[$characterId]['winner'] ?? 0);
        $activeDirectOrg = (int)($characterOrgs[$characterId]['winner'] ?? 0);
        $groupExtras = (array)($characterGroups[$characterId]['extras'] ?? []);
        $orgExtras = (array)($characterOrgs[$characterId]['extras'] ?? []);

        if (!empty($groupExtras)) {
            $result['summary']['character_group_conflicts']++;
            $result['messages'][] = 'PJ #' . $characterId . ': se conservara la manada #' . $activeGroup . ' y se desactivaran ' . count($groupExtras) . ' manadas activas adicionales.';
            if ($apply) {
                acac_deactivate_other_pairs($link, 'bridge_characters_groups', 'character_id', 'group_id', (int)$characterId, $activeGroup);
            }
        }

        if ($activeGroup > 0) {
            $ownerOrg = (int)($groupOwners[$activeGroup]['winner'] ?? 0);
            if ($ownerOrg <= 0) {
                $result['warnings'][] = 'PJ #' . $characterId . ': tiene la manada #' . $activeGroup . ' pero esa manada no tiene clan activo resuelto.';
                $result['summary']['warnings']++;
                if (!empty($orgExtras)) {
                    $result['summary']['character_org_conflicts']++;
                    $result['messages'][] = 'PJ #' . $characterId . ': como no se pudo resolver el clan de su manada, se conservara su clan directo #' . $activeDirectOrg . ' y se desactivaran ' . count($orgExtras) . ' clanes directos activos adicionales.';
                    if ($apply && $activeDirectOrg > 0) {
                        acac_deactivate_other_pairs($link, 'bridge_characters_organizations', 'character_id', 'organization_id', (int)$characterId, $activeDirectOrg);
                    }
                }
                continue;
            }

            $needsSync = ($activeDirectOrg !== $ownerOrg) || !empty($orgExtras);
            if ($needsSync) {
                $result['summary']['character_org_synced']++;
                $result['messages'][] = 'PJ #' . $characterId . ': con manada #' . $activeGroup . ' debe quedar en el clan #' . $ownerOrg . '.';
                if ($apply) {
                    acac_upsert_active_character_org($link, (int)$characterId, $ownerOrg);
                    acac_deactivate_other_pairs($link, 'bridge_characters_organizations', 'character_id', 'organization_id', (int)$characterId, $ownerOrg);
                }
            }
            continue;
        }

        if (!empty($orgExtras) && $activeDirectOrg > 0) {
            $result['summary']['character_org_conflicts']++;
            $result['messages'][] = 'PJ #' . $characterId . ': sin manada, se conservara el clan directo #' . $activeDirectOrg . ' y se desactivaran ' . count($orgExtras) . ' clanes directos activos adicionales.';
            if ($apply) {
                acac_deactivate_other_pairs($link, 'bridge_characters_organizations', 'character_id', 'organization_id', (int)$characterId, $activeDirectOrg);
            }
        }
    }

    if (empty($result['messages']) && empty($result['warnings'])) {
        $result['messages'][] = 'No se detectaron incoherencias con la regla canonica.';
    }

    return $result;
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
