<?php
/**
 * schema_hardening_audit.php
 * Auditoria dirigida para decidir:
 * - que bridges pueden perder id artificial
 * - si birthdate_text ya se puede retirar en favor de fact_timeline_events
 */

require_once(__DIR__ . '/../helpers/runtime_response.php');
require_once(__DIR__ . '/../helpers/db_connection.php');
require_once(__DIR__ . '/../helpers/character_birth_events.php');
require_once(__DIR__ . '/../helpers/admin_ajax.php');

if (!hg_runtime_require_db($link, 'schema_hardening_audit', 'bootstrap', [
    'message' => 'No se pudo conectar a la base de datos.',
])) {
    return;
}

mysqli_set_charset($link, 'utf8mb4');

if (function_exists('hg_admin_session_start')) {
    hg_admin_session_start();
} elseif (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

function hg_sha_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hg_sha_scalar(mysqli $link, string $sql): int
{
    $rs = $link->query($sql);
    if (!$rs) return 0;
    $row = $rs->fetch_row();
    $rs->free();
    return (int)($row[0] ?? 0);
}

function hg_sha_bridge_tables(mysqli $link): array
{
    $rows = [];
    $sql = "
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_TYPE = 'BASE TABLE'
          AND TABLE_NAME LIKE 'bridge\\_%'
        ORDER BY TABLE_NAME
    ";
    $rs = $link->query($sql);
    while ($rs && ($row = $rs->fetch_assoc())) {
        $rows[] = (string)($row['TABLE_NAME'] ?? '');
    }
    if ($rs) $rs->free();
    return array_values(array_filter($rows));
}

function hg_sha_table_columns(mysqli $link, string $table): array
{
    $out = [];
    if ($st = $link->prepare("
        SELECT COLUMN_NAME, COLUMN_KEY, EXTRA
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
    ")) {
        $st->bind_param('s', $table);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $out[] = $row;
        }
        $st->close();
    }
    return $out;
}

function hg_sha_index_map(mysqli $link, string $table): array
{
    $out = [];
    if ($st = $link->prepare("
        SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        ORDER BY INDEX_NAME, SEQ_IN_INDEX
    ")) {
        $st->bind_param('s', $table);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $name = (string)($row['INDEX_NAME'] ?? '');
            if ($name === '') continue;
            if (!isset($out[$name])) {
                $out[$name] = [
                    'unique' => ((int)($row['NON_UNIQUE'] ?? 1) === 0),
                    'columns' => [],
                ];
            }
            $out[$name]['columns'][] = (string)($row['COLUMN_NAME'] ?? '');
        }
        $st->close();
    }
    return $out;
}

function hg_sha_has_exact_unique(array $indexMap, array $columns): bool
{
    foreach ($indexMap as $name => $info) {
        if (empty($info['unique']) || $name === 'PRIMARY') {
            continue;
        }
        if (($info['columns'] ?? []) === $columns) {
            return true;
        }
    }
    return false;
}

function hg_sha_duplicate_count(mysqli $link, string $table, array $columns): int
{
    if (count($columns) < 2) return 0;
    $cols = [];
    foreach ($columns as $col) {
        $cols[] = '`' . str_replace('`', '``', $col) . '`';
    }
    $group = implode(', ', $cols);
    $sql = "
        SELECT COALESCE(SUM(dup_count - 1), 0)
        FROM (
            SELECT COUNT(*) AS dup_count
            FROM `{$table}`
            GROUP BY {$group}
            HAVING COUNT(*) > 1
        ) d
    ";
    return hg_sha_scalar($link, $sql);
}

function hg_sha_scan_app_hits(string $needle): array
{
    $hits = [];
    $roots = [
        __DIR__ . '/../controllers',
        __DIR__ . '/../helpers',
        __DIR__ . '/../modules',
        __DIR__ . '/../tools',
        __DIR__ . '/../partials',
    ];

    foreach ($roots as $root) {
        if (!is_dir($root)) continue;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower((string)$file->getExtension());
            if (!in_array($ext, ['php', 'html', 'md'], true)) continue;

            $path = (string)$file->getPathname();
            $lines = @file($path);
            if (!is_array($lines)) continue;

            foreach ($lines as $lineNo => $line) {
                if (stripos($line, $needle) === false) continue;
                $hits[] = [
                    'file' => $path,
                    'line' => $lineNo + 1,
                    'text' => trim($line),
                ];
                if (count($hits) >= 18) {
                    return $hits;
                }
            }
        }
    }

    return $hits;
}

function hg_sha_bridge_audit(mysqli $link): array
{
    $rows = [];
    foreach (hg_sha_bridge_tables($link) as $table) {
        $columnsInfo = hg_sha_table_columns($link, $table);
        $indexMap = hg_sha_index_map($link, $table);
        $columnNames = array_map(static function (array $row): string {
            return (string)($row['COLUMN_NAME'] ?? '');
        }, $columnsInfo);

        $hasAutoId = false;
        $primaryColumns = [];
        foreach ($columnsInfo as $row) {
            $name = (string)($row['COLUMN_NAME'] ?? '');
            if ($name === 'id' && stripos((string)($row['EXTRA'] ?? ''), 'auto_increment') !== false) {
                $hasAutoId = true;
            }
        }
        if (isset($indexMap['PRIMARY'])) {
            $primaryColumns = (array)$indexMap['PRIMARY']['columns'];
        }

        $relationColumns = [];
        $payloadColumns = [];
        foreach ($columnNames as $col) {
            if ($col === 'id') continue;
            if (preg_match('/_id$/', $col)) {
                $relationColumns[] = $col;
                continue;
            }
            if (in_array($col, ['created_at', 'updated_at'], true)) {
                continue;
            }
            $payloadColumns[] = $col;
        }

        $duplicateCount = hg_sha_duplicate_count($link, $table, $relationColumns);
        $exactUnique = hg_sha_has_exact_unique($indexMap, $relationColumns);
        $hits = hg_sha_scan_app_hits($table);
        $idLikeHits = 0;
        foreach ($hits as $hit) {
            $line = strtolower((string)$hit['text']);
            if (strpos($line, '.id') !== false || strpos($line, ' b.id') !== false || strpos($line, ' id,') !== false) {
                $idLikeHits++;
            }
        }

        $status = 'No aplica';
        $reason = 'No usa id artificial o no parece un bridge relacional puro.';
        if ($hasAutoId && count($relationColumns) >= 2) {
            if ($duplicateCount > 0 || !$exactUnique) {
                $status = 'Candidato con bloqueo';
                $reason = 'Necesita deduplicar y/o asegurar unicidad compuesta antes de retirar id.';
            } elseif (!empty($payloadColumns)) {
                $status = 'No directo';
                $reason = 'Tiene metadatos propios y conviene revisar si la fila tiene identidad funcional.';
            } else {
                $status = 'Candidato fuerte';
                $reason = 'Relacion pura, sin payload funcional y con unicidad compuesta limpia.';
            }
        }

        $rows[] = [
            'table' => $table,
            'has_auto_id' => $hasAutoId,
            'primary_columns' => $primaryColumns,
            'relation_columns' => $relationColumns,
            'payload_columns' => $payloadColumns,
            'duplicate_count' => $duplicateCount,
            'exact_unique' => $exactUnique,
            'code_hits' => $hits,
            'id_like_hits' => $idLikeHits,
            'status' => $status,
            'reason' => $reason,
        ];
    }

    return $rows;
}

function hg_sha_birthdate_audit(mysqli $link): array
{
    $summary = [
        'total' => 0,
        'empty' => 0,
        'unknown' => 0,
        'mappable' => 0,
        'narrative' => 0,
        'with_event' => 0,
        'without_event' => 0,
        'ready_to_retire' => false,
        'samples' => [],
        'code_hits' => hg_sha_scan_app_hits('birthdate_text'),
    ];

    if (!hg_cbe_table_exists($link, 'fact_characters')) {
        return $summary;
    }

    $expr = hg_cbe_birthtext_expr($link, 'fc');
    $sql = "
        SELECT
            fc.id,
            fc.name,
            {$expr} AS birth_text,
            (
                SELECT e.id
                FROM bridge_timeline_events_characters btec
                INNER JOIN fact_timeline_events e ON e.id = btec.event_id
                INNER JOIN dim_timeline_events_types tet ON tet.id = e.event_type_id
                WHERE btec.character_id = fc.id
                  AND tet.pretty_id = 'nacimiento'
                ORDER BY e.id ASC
                LIMIT 1
            ) AS birth_event_id
        FROM fact_characters fc
        ORDER BY fc.id ASC
    ";
    $rs = $link->query($sql);
    while ($rs && ($row = $rs->fetch_assoc())) {
        $summary['total']++;
        $raw = trim((string)($row['birth_text'] ?? ''));
        $parsed = hg_cbe_parse_birth_text($raw);
        $eventId = (int)($row['birth_event_id'] ?? 0);

        if ($parsed['kind'] === 'empty') {
            $summary['empty']++;
        } elseif ($parsed['kind'] === 'unknown') {
            $summary['unknown']++;
        } elseif (!empty($parsed['can_event'])) {
            $summary['mappable']++;
        } else {
            $summary['narrative']++;
            if (count($summary['samples']) < 10) {
                $summary['samples'][] = '#' . (int)$row['id'] . ' ' . (string)$row['name'] . ' -> ' . $raw;
            }
        }

        if ($eventId > 0) {
            $summary['with_event']++;
        } else {
            $summary['without_event']++;
        }
    }
    if ($rs) $rs->free();

    $summary['ready_to_retire'] = ($summary['narrative'] === 0);
    return $summary;
}

function hg_sha_supported_bridge_migrations(): array
{
    return [
        'bridge_characters_items' => [
            'label' => 'PJ <-> objetos',
            'relation_columns' => ['character_id', 'item_id'],
            'kind' => 'pure',
        ],
        'bridge_chapters_characters' => [
            'label' => 'capitulos <-> personajes',
            'relation_columns' => ['chapter_id', 'character_id'],
            'kind' => 'context',
        ],
        'bridge_characters_docs' => [
            'label' => 'PJ <-> documentos',
            'relation_columns' => ['character_id', 'doc_id'],
            'kind' => 'context',
        ],
        'bridge_characters_external_links' => [
            'label' => 'PJ <-> enlaces externos',
            'relation_columns' => ['character_id', 'external_link_id'],
            'kind' => 'context',
        ],
        'bridge_characters_groups' => [
            'label' => 'PJ <-> manadas',
            'relation_columns' => ['character_id', 'group_id'],
            'kind' => 'context',
        ],
        'bridge_characters_merits_flaws' => [
            'label' => 'PJ <-> meritos/defectos',
            'relation_columns' => ['character_id', 'merit_flaw_id'],
            'kind' => 'context',
        ],
        'bridge_characters_organizations' => [
            'label' => 'PJ <-> clanes/organizaciones',
            'relation_columns' => ['character_id', 'organization_id'],
            'kind' => 'context',
        ],
        'bridge_organizations_groups' => [
            'label' => 'clanes <-> manadas',
            'relation_columns' => ['organization_id', 'group_id'],
            'kind' => 'context',
        ],
    ];
}

function hg_sha_bridge_drop_index_if_exists(mysqli $link, string $table, string $indexName): void
{
    $map = hg_sha_index_map($link, $table);
    if (isset($map[$indexName])) {
        $link->query("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
    }
}

function hg_sha_migrate_bridge_to_composite_pk(mysqli $link, string $table, array $relationColumns): array
{
    $messages = [];
    $columns = hg_sha_table_columns($link, $table);
    $hasAutoId = false;
    foreach ($columns as $row) {
        if ((string)($row['COLUMN_NAME'] ?? '') === 'id' && stripos((string)($row['EXTRA'] ?? ''), 'auto_increment') !== false) {
            $hasAutoId = true;
            break;
        }
    }

    $duplicates = hg_sha_duplicate_count($link, $table, $relationColumns);
    if ($duplicates > 0 && $hasAutoId) {
        $cond = [];
        foreach ($relationColumns as $col) {
            $cond[] = 'b1.`' . $col . '` = b2.`' . $col . '`';
        }
        $sql = "
            DELETE b1
            FROM `{$table}` b1
            INNER JOIN `{$table}` b2
                ON " . implode(' AND ', $cond) . "
               AND b1.id > b2.id
        ";
        $link->query($sql);
        $messages[] = 'Duplicados exactos eliminados: ' . (int)$link->affected_rows . '.';
    } else {
        $messages[] = 'Duplicados exactos eliminados: 0.';
    }

    $indexMap = hg_sha_index_map($link, $table);
    $primaryComposite = isset($indexMap['PRIMARY']) && ((array)$indexMap['PRIMARY']['columns'] === $relationColumns);
    if ($primaryComposite && !$hasAutoId) {
        $messages[] = 'La tabla ya estaba con PK compuesta.';
        return $messages;
    }

    if ($hasAutoId) {
        $link->query("ALTER TABLE `{$table}` DROP PRIMARY KEY, DROP COLUMN `id`, ADD PRIMARY KEY (`" . implode('`,`', $relationColumns) . "`)");
        $messages[] = 'id eliminado y PK compuesta creada.';
    } elseif (!$primaryComposite) {
        $link->query("ALTER TABLE `{$table}` ADD PRIMARY KEY (`" . implode('`,`', $relationColumns) . "`)");
        $messages[] = 'PK compuesta creada.';
    }

    if ($table === 'bridge_characters_docs') {
        hg_sha_bridge_drop_index_if_exists($link, $table, 'uq_bcd_character_doc');
    } elseif ($table === 'bridge_characters_external_links') {
        hg_sha_bridge_drop_index_if_exists($link, $table, 'uq_bcel_character_link');
    }

    return $messages;
}

$bridgeMigrationExecution = [];
$bridgeMigrationFlash = [];
$bridgeMigrationCsrfKey = 'csrf_admin_bridge_key_migrator';
$bridgeMigrationCsrf = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($bridgeMigrationCsrfKey)
    : (string)($_SESSION[$bridgeMigrationCsrfKey] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['bridge_migration_action'] ?? '') === 'migrate_bridge') {
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token()
        : (string)($_POST['csrf'] ?? '');
    $csrfOk = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $bridgeMigrationCsrfKey)
        : ($token !== '' && isset($_SESSION[$bridgeMigrationCsrfKey]) && hash_equals((string)$_SESSION[$bridgeMigrationCsrfKey], $token));

    if (!$csrfOk) {
        $bridgeMigrationFlash[] = ['type' => 'err', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $bridgeId = (string)($_POST['bridge_id'] ?? '');
        $supported = hg_sha_supported_bridge_migrations();
        if (!isset($supported[$bridgeId])) {
            $bridgeMigrationFlash[] = ['type' => 'err', 'msg' => 'Bridge no soportado por el migrador manual.'];
        } else {
            try {
                $config = $supported[$bridgeId];
                $messages = hg_sha_migrate_bridge_to_composite_pk($link, $bridgeId, (array)$config['relation_columns']);
                $bridgeMigrationExecution[] = [
                    'bridge' => $bridgeId,
                    'status' => 'OK',
                    'messages' => $messages,
                ];
                $bridgeMigrationFlash[] = ['type' => 'ok', 'msg' => 'Migracion aplicada sobre ' . $bridgeId . '.'];
            } catch (Throwable $e) {
                $bridgeMigrationExecution[] = [
                    'bridge' => $bridgeId,
                    'status' => 'ERROR',
                    'messages' => [$e->getMessage()],
                ];
                $bridgeMigrationFlash[] = ['type' => 'err', 'msg' => 'La migracion de ' . $bridgeId . ' ha fallado.'];
            }
        }
    }
}

$bridgeAudit = hg_sha_bridge_audit($link);
$birthAudit = hg_sha_birthdate_audit($link);
?>
<style>
  .hg-sha-wrap{padding:18px 10px}
  .hg-sha-card{background:#111926;border:1px solid #24374f;border-radius:14px;padding:18px;margin:0 0 18px;color:#e9eef5}
  .hg-sha-card h3{margin:0 0 12px}
  .hg-sha-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:0 0 14px}
  .hg-sha-kpi{background:#0c1320;border:1px solid #1f3046;border-radius:10px;padding:12px}
  .hg-sha-kpi strong{display:block;font-size:22px;color:#fff}
  .hg-sha-table{width:100%;border-collapse:collapse}
  .hg-sha-table th,.hg-sha-table td{border-bottom:1px solid #24374f;padding:8px 10px;text-align:left;vertical-align:top}
  .hg-sha-badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700}
  .hg-sha-badge.ok{background:#1a5f38;color:#dff6e7}
  .hg-sha-badge.warn{background:#7a5c16;color:#fff1c7}
  .hg-sha-badge.no{background:#6b2632;color:#ffe1e8}
  .hg-sha-list{margin:8px 0 0 18px}
  .hg-sha-note{color:#bfcbd9}
  .hg-sha-flash{padding:10px 12px;border-radius:10px;margin:0 0 12px}
  .hg-sha-flash.ok{background:#173b25;border:1px solid #2f6c45}
  .hg-sha-flash.err{background:#4a1f29;border:1px solid #7b3143}
  .hg-sha-btn{background:#19324c;color:#fff;border:1px solid #325579;border-radius:8px;padding:7px 10px;cursor:pointer}
  .hg-sha-btn[disabled]{opacity:.45;cursor:not-allowed}
</style>

<div class="hg-sha-wrap">
  <div class="hg-sha-card">
    <h3>Auditoria de endurecimiento</h3>
    <p class="hg-sha-note">Esta herramienta cruza esquema, datos y referencias en codigo para decidir dos cosas: que bridges pueden perder <code>id</code> artificial y si <code>birthdate_text</code> ya puede retirarse en favor de <code>fact_timeline_events</code>.</p>
    <?php foreach ($bridgeMigrationFlash as $msg): ?>
      <div class="hg-sha-flash <?= hg_sha_h((string)$msg['type']) ?>"><?= hg_sha_h((string)$msg['msg']) ?></div>
    <?php endforeach; ?>
    <?php if (!empty($bridgeMigrationExecution)): ?>
      <ul class="hg-sha-list"><?php foreach ($bridgeMigrationExecution as $row): ?><li><strong><?= hg_sha_h((string)$row['bridge']) ?></strong>: <?= hg_sha_h((string)$row['status']) ?> | <?= hg_sha_h(implode(' | ', (array)$row['messages'])) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div>

  <div class="hg-sha-card">
    <h3>Bridges con id artificial</h3>
    <div class="hg-sha-grid">
      <div class="hg-sha-kpi"><strong><?= count($bridgeAudit) ?></strong> bridges auditados</div>
      <div class="hg-sha-kpi"><strong><?= count(array_filter($bridgeAudit, static function(array $row): bool { return ($row['status'] ?? '') === 'Candidato fuerte'; })) ?></strong> candidatos fuertes</div>
      <div class="hg-sha-kpi"><strong><?= count(array_filter($bridgeAudit, static function(array $row): bool { return ($row['status'] ?? '') === 'Candidato con bloqueo'; })) ?></strong> candidatos con bloqueo</div>
    </div>
    <table class="hg-sha-table">
      <thead>
        <tr><th>Tabla</th><th>Estado</th><th>Relacion</th><th>Payload</th><th>Duplicados</th><th>Codigo</th><th>Lectura</th><th>Accion</th></tr>
      </thead>
      <tbody>
        <?php foreach ($bridgeAudit as $row): ?>
          <?php
            $status = (string)($row['status'] ?? '');
            $badge = $status === 'Candidato fuerte' ? 'ok' : ($status === 'Candidato con bloqueo' ? 'warn' : 'no');
            $supported = hg_sha_supported_bridge_migrations();
            $canMigrateHere = isset($supported[(string)$row['table']]) && (int)($row['duplicate_count'] ?? 0) === 0;
          ?>
          <tr>
            <td><code><?= hg_sha_h((string)$row['table']) ?></code></td>
            <td><span class="hg-sha-badge <?= $badge ?>"><?= hg_sha_h($status) ?></span></td>
            <td><?= hg_sha_h(implode(', ', (array)$row['relation_columns'])) ?></td>
            <td><?= !empty($row['payload_columns']) ? hg_sha_h(implode(', ', (array)$row['payload_columns'])) : '<span class="hg-sha-note">sin payload</span>' ?></td>
            <td><?= (int)($row['duplicate_count'] ?? 0) ?><?= !empty($row['exact_unique']) ? ' | UNIQUE exacta' : ' | sin UNIQUE exacta' ?></td>
            <td><?= count((array)($row['code_hits'] ?? [])) ?> archivos / <?= (int)($row['id_like_hits'] ?? 0) ?> pistas de row-id</td>
            <td><?= hg_sha_h((string)($row['reason'] ?? '')) ?></td>
            <td>
              <?php if ($canMigrateHere): ?>
                <form method="POST" onsubmit="return confirm('Esto quitara el id autoincremental de <?= hg_sha_h((string)$row['table']) ?> y dejara PK compuesta. Continuar?');">
                  <input type="hidden" name="csrf" value="<?= hg_sha_h($bridgeMigrationCsrf) ?>">
                  <input type="hidden" name="bridge_migration_action" value="migrate_bridge">
                  <input type="hidden" name="bridge_id" value="<?= hg_sha_h((string)$row['table']) ?>">
                  <button class="hg-sha-btn" type="submit">Migrar</button>
                </form>
              <?php elseif (isset($supported[(string)$row['table']])): ?>
                <span class="hg-sha-note">Bloqueado por duplicados</span>
              <?php else: ?>
                <span class="hg-sha-note">No automatizado hoy</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="hg-sha-card">
    <h3>Retirada de birthdate_text</h3>
    <div class="hg-sha-grid">
      <div class="hg-sha-kpi"><strong><?= (int)$birthAudit['total'] ?></strong> personajes auditados</div>
      <div class="hg-sha-kpi"><strong><?= (int)$birthAudit['mappable'] ?></strong> textos migrables a evento</div>
      <div class="hg-sha-kpi"><strong><?= (int)$birthAudit['narrative'] ?></strong> textos narrativos bloqueantes</div>
      <div class="hg-sha-kpi"><strong><?= (int)$birthAudit['with_event'] ?></strong> personajes con evento de nacimiento</div>
    </div>
    <p><strong>Estado:</strong> <span class="hg-sha-badge <?= $birthAudit['ready_to_retire'] ? 'ok' : 'warn' ?>"><?= $birthAudit['ready_to_retire'] ? 'Retirable con saneado' : 'Aun no retirable sin revisar restos narrativos' ?></span></p>
    <ul class="hg-sha-list">
      <li><?= (int)$birthAudit['empty'] ?> vacios reales</li>
      <li><?= (int)$birthAudit['unknown'] ?> desconocidos o equivalentes</li>
      <li><?= (int)$birthAudit['mappable'] ?> con fecha interpretable</li>
      <li><?= (int)$birthAudit['narrative'] ?> narrativos o no mapeables</li>
      <li><?= (int)$birthAudit['without_event'] ?> sin evento de nacimiento enlazado</li>
      <li><?= count((array)$birthAudit['code_hits']) ?> referencias de codigo a <code>birthdate_text</code> detectadas</li>
    </ul>
    <?php if (!empty($birthAudit['samples'])): ?>
      <p><strong>Muestras narrativas:</strong></p>
      <ul class="hg-sha-list"><?php foreach ((array)$birthAudit['samples'] as $sample): ?><li><?= hg_sha_h((string)$sample) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if (!empty($birthAudit['code_hits'])): ?>
      <p><strong>Referencias de codigo:</strong></p>
      <ul class="hg-sha-list"><?php foreach ((array)$birthAudit['code_hits'] as $hit): ?><li><?= hg_sha_h((string)$hit['file']) ?>:<?= (int)$hit['line'] ?> - <?= hg_sha_h((string)$hit['text']) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</div>
