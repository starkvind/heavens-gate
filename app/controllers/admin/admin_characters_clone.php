<?php
// admin_characters_clone.php - Clonado de personajes entre cronicas/realidades.

if (!isset($link) || !$link) { die("Sin conexion BD"); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

if (!function_exists('hg_acc_h')) {
    function hg_acc_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('hg_acc_ident')) {
    function hg_acc_ident(string $name): string {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new RuntimeException('Identificador SQL invalido: ' . $name);
        }
        return '`' . $name . '`';
    }
}

if (!function_exists('hg_acc_table_exists')) {
    function hg_acc_table_exists(mysqli $db, string $table): bool {
        $table = trim($table);
        if ($table === '') return false;
        $count = 0;
        if ($st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?")) {
            $st->bind_param('s', $table);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
        }
        return ((int)$count > 0);
    }
}

if (!function_exists('hg_acc_fetch_table_columns')) {
    function hg_acc_fetch_table_columns(mysqli $db, string $table): array {
        $rows = [];
        if ($st = $db->prepare("
            SELECT COLUMN_NAME, COALESCE(EXTRA, '') AS extra_info
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ")) {
            $st->bind_param('s', $table);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) {
                $rows[] = [
                    'column_name' => (string)($r['COLUMN_NAME'] ?? ''),
                    'extra' => (string)($r['extra_info'] ?? ''),
                ];
            }
            $st->close();
        }
        return $rows;
    }
}

if (!function_exists('hg_acc_fetch_pairs')) {
    function hg_acc_fetch_pairs(mysqli $db, string $sql): array {
        $out = [];
        $q = @$db->query($sql);
        if (!$q) return $out;
        while ($r = $q->fetch_assoc()) {
            $id = (int)($r['id'] ?? 0);
            $name = (string)($r['name'] ?? '');
            if ($id > 0) $out[$id] = $name;
        }
        $q->close();
        return $out;
    }
}

if (!function_exists('hg_acc_pretty_exists')) {
    function hg_acc_pretty_exists(mysqli $db, string $pretty): bool {
        if ($pretty === '') return false;
        $found = 0;
        if ($st = $db->prepare("SELECT id FROM fact_characters WHERE pretty_id = ? LIMIT 1")) {
            $st->bind_param('s', $pretty);
            $st->execute();
            $st->store_result();
            $found = $st->num_rows;
            $st->close();
        }
        return ((int)$found > 0);
    }
}

if (!function_exists('hg_acc_build_pretty_for_clone')) {
    function hg_acc_build_pretty_for_clone(mysqli $db, string $sourcePretty, string $sourceName, int $newCharacterId): string {
        $base = trim($sourcePretty);
        if ($base === '') {
            $base = slugify_pretty_id($sourceName);
        }
        if ($base === '') {
            $base = 'character';
        }
        $suffix = '-copia-' . (int)$newCharacterId;
        $maxLen = 190;
        $room = $maxLen - strlen($suffix);
        if ($room < 1) $room = 1;
        if (strlen($base) > $room) {
            $base = substr($base, 0, $room);
            $base = rtrim($base, '-');
        }
        if ($base === '') $base = 'character';
        $candidate = $base . $suffix;
        if (!hg_acc_pretty_exists($db, $candidate)) {
            return $candidate;
        }
        $i = 2;
        while ($i < 10000) {
            $suffix2 = $suffix . '-' . $i;
            $room2 = $maxLen - strlen($suffix2);
            $base2 = $base;
            if (strlen($base2) > $room2) {
                $base2 = substr($base2, 0, max(1, $room2));
                $base2 = rtrim($base2, '-');
            }
            if ($base2 === '') $base2 = 'character';
            $candidate2 = $base2 . $suffix2;
            if (!hg_acc_pretty_exists($db, $candidate2)) {
                return $candidate2;
            }
            $i++;
        }
        return 'character-copia-' . (int)$newCharacterId;
    }
}

if (!function_exists('hg_acc_copy_bridges_for_character')) {
    function hg_acc_copy_bridges_for_character(mysqli $db, int $sourceCharacterId, int $newCharacterId): array {
        $excluded = [
            'bridge_characters_groups' => true,
            'bridge_characters_items' => true,
            'bridge_characters_relations' => true,
            'bridge_timeline_events_characters' => true,
        ];

        $tables = [];
        $sqlTables = "
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = 'BASE TABLE'
              AND TABLE_NAME LIKE 'bridge\\_%' ESCAPE '\\\\'
            ORDER BY TABLE_NAME ASC
        ";
        if ($rs = $db->query($sqlTables)) {
            while ($r = $rs->fetch_assoc()) {
                $t = (string)($r['TABLE_NAME'] ?? '');
                if ($t !== '') $tables[] = $t;
            }
            $rs->close();
        }

        $tablesChecked = 0;
        $tablesCopied = 0;
        $rowsInserted = 0;
        $details = [];

        foreach ($tables as $table) {
            if (isset($excluded[$table])) continue;
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) continue;

            $columns = hg_acc_fetch_table_columns($db, $table);
            if (empty($columns)) continue;

            $hasCharacterId = false;
            $copyColumns = [];
            foreach ($columns as $meta) {
                $col = (string)($meta['column_name'] ?? '');
                $extra = strtolower((string)($meta['extra'] ?? ''));
                if ($col === 'character_id') {
                    $hasCharacterId = true;
                    continue;
                }
                if ($col === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $col)) continue;
                if (strpos($extra, 'auto_increment') !== false) continue;
                if (strpos($extra, 'virtual generated') !== false) continue;
                if (strpos($extra, 'stored generated') !== false) continue;
                $copyColumns[] = $col;
            }
            if (!$hasCharacterId) continue;

            $tablesChecked++;

            $insertCols = [hg_acc_ident('character_id')];
            $selectCols = [(string)$newCharacterId];
            foreach ($copyColumns as $col) {
                $ident = hg_acc_ident($col);
                $insertCols[] = $ident;
                $selectCols[] = $ident;
            }

            $sql = "INSERT IGNORE INTO " . hg_acc_ident($table)
                . " (" . implode(', ', $insertCols) . ") "
                . "SELECT " . implode(', ', $selectCols) . " FROM " . hg_acc_ident($table)
                . " WHERE " . hg_acc_ident('character_id') . " = ?";
            $st = $db->prepare($sql);
            if (!$st) {
                throw new RuntimeException("No se pudo preparar clonado de {$table}: " . $db->error);
            }
            $st->bind_param('i', $sourceCharacterId);
            $ok = $st->execute();
            $affected = (int)$st->affected_rows;
            $st->close();
            if (!$ok) {
                throw new RuntimeException("Error al clonar filas de {$table}");
            }

            if ($affected > 0) {
                $tablesCopied++;
                $rowsInserted += $affected;
                $details[] = ['table' => $table, 'rows' => $affected];
            }
        }

        return [
            'tables_checked' => $tablesChecked,
            'tables_copied' => $tablesCopied,
            'rows_inserted' => $rowsInserted,
            'details' => $details,
        ];
    }
}

if (!function_exists('hg_acc_clone_character')) {
    function hg_acc_clone_character(mysqli $db, int $sourceCharacterId, int $targetChronicleId, int $targetRealityId): array {
        if ($sourceCharacterId <= 0) throw new RuntimeException('Personaje origen invalido');
        if ($targetChronicleId <= 0) throw new RuntimeException('Cronica destino invalida');
        if ($targetRealityId <= 0) throw new RuntimeException('Realidad destino invalida');

        if (!hg_acc_table_exists($db, 'fact_characters')) {
            throw new RuntimeException('No existe fact_characters');
        }

        $source = null;
        if ($st = $db->prepare("SELECT id, name, COALESCE(pretty_id, '') AS pretty_id FROM fact_characters WHERE id = ? LIMIT 1")) {
            $st->bind_param('i', $sourceCharacterId);
            $st->execute();
            $rs = $st->get_result();
            $source = $rs ? $rs->fetch_assoc() : null;
            $st->close();
        }
        if (!$source) throw new RuntimeException('No existe el personaje origen');

        $okChronicle = false;
        if ($st = $db->prepare("SELECT id FROM dim_chronicles WHERE id = ? LIMIT 1")) {
            $st->bind_param('i', $targetChronicleId);
            $st->execute();
            $st->store_result();
            $okChronicle = ($st->num_rows > 0);
            $st->close();
        }
        if (!$okChronicle) throw new RuntimeException('La cronica destino no existe');

        $okReality = false;
        if ($st = $db->prepare("SELECT id FROM dim_realities WHERE id = ? LIMIT 1")) {
            $st->bind_param('i', $targetRealityId);
            $st->execute();
            $st->store_result();
            $okReality = ($st->num_rows > 0);
            $st->close();
        }
        if (!$okReality) throw new RuntimeException('La realidad destino no existe');

        $columns = hg_acc_fetch_table_columns($db, 'fact_characters');
        if (empty($columns)) throw new RuntimeException('No se pudieron leer columnas de fact_characters');

        $insertCols = [];
        $selectExprs = [];
        $hasPrettyColumn = false;

        foreach ($columns as $meta) {
            $col = (string)($meta['column_name'] ?? '');
            $extra = strtolower((string)($meta['extra'] ?? ''));
            if ($col === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $col)) continue;
            if (strpos($extra, 'auto_increment') !== false) continue;

            $insertCols[] = hg_acc_ident($col);
            if ($col === 'chronicle_id') {
                $selectExprs[] = (string)$targetChronicleId;
            } elseif ($col === 'reality_id') {
                $selectExprs[] = (string)$targetRealityId;
            } elseif ($col === 'pretty_id') {
                $hasPrettyColumn = true;
                $selectExprs[] = "NULL";
            } else {
                $selectExprs[] = hg_acc_ident($col);
            }
        }

        if (empty($insertCols)) {
            throw new RuntimeException('No hay columnas insertables en fact_characters');
        }

        $db->begin_transaction();
        try {
            $sqlInsert = "INSERT INTO `fact_characters` (" . implode(', ', $insertCols) . ") "
                . "SELECT " . implode(', ', $selectExprs) . " FROM `fact_characters` WHERE `id` = " . (int)$sourceCharacterId . " LIMIT 1";
            $okInsert = $db->query($sqlInsert);
            if (!$okInsert) {
                throw new RuntimeException('Error al clonar personaje: ' . $db->error);
            }

            $newCharacterId = (int)$db->insert_id;
            if ($newCharacterId <= 0) {
                throw new RuntimeException('No se pudo obtener el ID del personaje clonado');
            }

            if ($hasPrettyColumn) {
                $newPretty = hg_acc_build_pretty_for_clone(
                    $db,
                    (string)($source['pretty_id'] ?? ''),
                    (string)($source['name'] ?? ''),
                    $newCharacterId
                );
                if ($stUp = $db->prepare("UPDATE fact_characters SET pretty_id = ? WHERE id = ? LIMIT 1")) {
                    $stUp->bind_param('si', $newPretty, $newCharacterId);
                    if (!$stUp->execute()) {
                        $stUp->close();
                        throw new RuntimeException('Error al fijar pretty_id del clon');
                    }
                    $stUp->close();
                } else {
                    throw new RuntimeException('No se pudo preparar update de pretty_id');
                }
            }

            $bridgeStats = hg_acc_copy_bridges_for_character($db, $sourceCharacterId, $newCharacterId);

            $db->commit();

            return [
                'new_character_id' => $newCharacterId,
                'source_character_id' => $sourceCharacterId,
                'bridge' => $bridgeStats,
                'source_name' => (string)($source['name'] ?? ''),
            ];
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_characters_clone';
$CSRF = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : '';

if (!function_exists('hg_acc_csrf_ok')) {
    function hg_acc_csrf_ok(): bool {
        $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
        $token = function_exists('hg_admin_extract_csrf_token')
            ? hg_admin_extract_csrf_token($payload)
            : (string)($_POST['csrf'] ?? '');
        if (function_exists('hg_admin_csrf_valid')) {
            return hg_admin_csrf_valid($token, 'csrf_admin_characters_clone');
        }
        return is_string($token) && $token !== '' && isset($_SESSION['csrf_admin_characters_clone']) && hash_equals($_SESSION['csrf_admin_characters_clone'], $token);
    }
}

$flash = [];

$isAjaxCrudRequest = (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ((string)($_POST['crud_action'] ?? '') === 'clone_character')
    && (
        ((string)($_POST['ajax'] ?? '') === '1')
        || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
    )
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['crud_action'] ?? '') === 'clone_character') {
    if ($isAjaxCrudRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }

    if (!hg_acc_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $sourceCharacterId = (int)($_POST['source_character_id'] ?? 0);
        $targetChronicleId = (int)($_POST['target_chronicle_id'] ?? 0);
        $targetRealityId = (int)($_POST['target_reality_id'] ?? 0);

        try {
            $result = hg_acc_clone_character($link, $sourceCharacterId, $targetChronicleId, $targetRealityId);
            $flash[] = [
                'type' => 'ok',
                'msg' => 'Personaje clonado como #' . (int)$result['new_character_id']
                    . ' (' . (int)($result['bridge']['rows_inserted'] ?? 0) . ' filas bridge copiadas).'
            ];
        } catch (Throwable $e) {
            $flash[] = ['type' => 'error', 'msg' => $e->getMessage()];
        }
    }
}

if ($isAjaxCrudRequest) {
    $errors = [];
    $messages = [];
    foreach ($flash as $m) {
        $type = (string)($m['type'] ?? '');
        $msg = (string)($m['msg'] ?? '');
        if ($msg === '') continue;
        if ($type === 'error') $errors[] = $msg;
        else $messages[] = $msg;
    }
    if (!empty($errors)) {
        if (function_exists('hg_admin_json_error')) {
            hg_admin_json_error($errors[0], 400, ['flash' => $errors], ['messages' => $messages]);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'message' => $errors[0],
            'error' => $errors[0],
            'errors' => $errors,
            'data' => ['messages' => $messages],
            'meta' => ['module' => 'admin_characters_clone'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $okMsg = !empty($messages) ? $messages[count($messages) - 1] : 'Clonado';
    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success(['messages' => $messages], $okMsg);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'message' => $okMsg,
        'msg' => $okMsg,
        'data' => ['messages' => $messages],
        'errors' => [],
        'meta' => ['module' => 'admin_characters_clone'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$perPage = isset($_GET['pp']) ? max(10, min(200, (int)$_GET['pp'])) : 50;
$page = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$q = trim((string)($_GET['q'] ?? ''));
$offset = ($page - 1) * $perPage;

$optsChronicles = hg_acc_fetch_pairs($link, "SELECT id, name FROM dim_chronicles ORDER BY sort_order ASC, name ASC");
$optsRealities = hg_acc_fetch_pairs($link, "SELECT id, name FROM dim_realities ORDER BY is_active DESC, name ASC");

$where = "WHERE 1=1";
$types = '';
$params = [];
if ($q !== '') {
    $where .= " AND (
        p.name LIKE ?
        OR COALESCE(p.pretty_id, '') LIKE ?
        OR COALESCE(c.name, '') LIKE ?
        OR COALESCE(r.name, '') LIKE ?
        OR CAST(p.id AS CHAR) LIKE ?
    )";
    $needle = '%' . $q . '%';
    $types = 'sssss';
    $params = [$needle, $needle, $needle, $needle, $needle];
}

$total = 0;
$sqlCount = "
    SELECT COUNT(*) AS c
    FROM fact_characters p
    LEFT JOIN dim_chronicles c ON c.id = p.chronicle_id
    LEFT JOIN dim_realities r ON r.id = p.reality_id
    {$where}
";
$stCnt = $link->prepare($sqlCount);
if ($stCnt) {
    if ($types !== '') $stCnt->bind_param($types, ...$params);
    $stCnt->execute();
    $rsCnt = $stCnt->get_result();
    if ($rsCnt && ($rowCnt = $rsCnt->fetch_assoc())) {
        $total = (int)($rowCnt['c'] ?? 0);
    }
    $stCnt->close();
}

$pages = max(1, (int)ceil(($total > 0 ? $total : 1) / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = [];
$rowMap = [];
$sqlList = "
    SELECT
        p.id,
        p.name,
        p.chronicle_id,
        p.reality_id,
        COALESCE(c.name, '') AS chronicle_name,
        COALESCE(r.name, '') AS reality_name
    FROM fact_characters p
    LEFT JOIN dim_chronicles c ON c.id = p.chronicle_id
    LEFT JOIN dim_realities r ON r.id = p.reality_id
    {$where}
    ORDER BY p.id DESC
    LIMIT ?, ?
";
$stList = $link->prepare($sqlList);
if ($stList) {
    $types2 = $types . 'ii';
    $params2 = $params;
    $params2[] = $offset;
    $params2[] = $perPage;
    $stList->bind_param($types2, ...$params2);
    $stList->execute();
    $rsList = $stList->get_result();
    while ($r = $rsList->fetch_assoc()) {
        $r['id'] = (int)($r['id'] ?? 0);
        $r['chronicle_id'] = (int)($r['chronicle_id'] ?? 0);
        $r['reality_id'] = (int)($r['reality_id'] ?? 0);
        $rows[] = $r;
        $rowMap[$r['id']] = $r;
    }
    $stList->close();
}

$actions = '<span class="adm-flex-right-8"></span>';
admin_panel_open('Copiar personajes entre cronicas', $actions);
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?>
        <div class="<?= $cl ?>"><?= hg_acc_h($m['msg']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="get" id="accFilterForm" class="adm-flex-8-m10">
    <input type="hidden" name="s" value="admin_characters_clone">
    <label class="small">Busqueda
        <input class="inp" type="text" name="q" value="<?= hg_acc_h($q) ?>" placeholder="ID, nombre, cronica o realidad">
    </label>
    <label class="small">Por pag
        <select class="select" name="pp" onchange="this.form.submit()">
            <?php foreach ([25,50,100,200] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn" type="submit">Aplicar</button>
</form>

<table class="table" id="accTable">
    <thead>
        <tr>
            <th class="adm-w-70">ID</th>
            <th>Nombre</th>
            <th class="adm-w-260">Cronica</th>
            <th class="adm-w-220">Realidad</th>
            <th class="adm-w-170">Acciones</th>
        </tr>
    </thead>
    <tbody id="accTbody">
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><strong class="adm-color-accent"><?= (int)$r['id'] ?></strong></td>
            <td><?= hg_acc_h((string)$r['name']) ?></td>
            <td><?= hg_acc_h((string)$r['chronicle_name']) ?></td>
            <td><?= hg_acc_h((string)$r['reality_name']) ?></td>
            <td>
                <button
                    class="btn"
                    type="button"
                    data-clone="1"
                    data-id="<?= (int)$r['id'] ?>"
                    data-name="<?= hg_acc_h((string)$r['name']) ?>"
                    data-chronicle-id="<?= (int)$r['chronicle_id'] ?>"
                    data-reality-id="<?= (int)$r['reality_id'] ?>"
                >Copiar</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="adm-color-muted">(Sin resultados)</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="pager" id="accPager">
    <?php
    $base = "/talim?s=admin_characters_clone&pp=" . $perPage . "&q=" . urlencode($q);
    $prev = max(1, $page - 1);
    $next = min($pages, $page + 1);
    ?>
    <a href="<?= $base ?>&pg=1">&laquo; Primero</a>
    <a href="<?= $base ?>&pg=<?= $prev ?>">&lsaquo; Anterior</a>
    <span class="cur">Pag <?= $page ?>/<?= $pages ?> - Total <?= (int)$total ?></span>
    <a href="<?= $base ?>&pg=<?= $next ?>">Siguiente &rsaquo;</a>
    <a href="<?= $base ?>&pg=<?= $pages ?>">Ultimo &raquo;</a>
</div>

<div class="modal-back" id="mbAccClone">
    <div class="modal adm-modal-sm" role="dialog" aria-modal="true" aria-labelledby="accCloneTitle">
        <h3 id="accCloneTitle">Copiar personaje</h3>
        <div class="adm-help-text" id="accCloneSummary">Se creara una copia del personaje seleccionado.</div>
        <form method="post" id="accCloneForm" class="adm-m-0">
            <input type="hidden" name="csrf" value="<?= hg_acc_h($CSRF) ?>">
            <input type="hidden" name="crud_action" value="clone_character">
            <input type="hidden" name="source_character_id" id="acc_source_character_id" value="0">

            <label><span>Cronica destino</span>
                <select class="select" name="target_chronicle_id" id="acc_target_chronicle_id" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($optsChronicles as $cid => $cname): ?>
                    <option value="<?= (int)$cid ?>"><?= hg_acc_h($cname) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Realidad destino</span>
                <select class="select" name="target_reality_id" id="acc_target_reality_id" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($optsRealities as $rid => $rname): ?>
                    <option value="<?= (int)$rid ?>"><?= hg_acc_h($rname) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="adm-help-text">
                Se copiaran los bridges del personaje excepto:
                <code>bridge_characters_groups</code>,
                <code>bridge_characters_items</code>,
                <code>bridge_characters_relations</code>,
                <code>bridge_timeline_events_characters</code>.
            </div>

            <div class="modal-actions">
                <button type="button" class="btn" id="btnAccCancel">Cancelar</button>
                <button type="submit" class="btn btn-green">Clonar personaje</button>
            </div>
        </form>
    </div>
</div>

<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script src="<?= hg_acc_h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
(function(){
    var modal = document.getElementById('mbAccClone');
    var form = document.getElementById('accCloneForm');
    var summary = document.getElementById('accCloneSummary');
    var sourceIdInput = document.getElementById('acc_source_character_id');
    var targetChronicleInput = document.getElementById('acc_target_chronicle_id');
    var targetRealityInput = document.getElementById('acc_target_reality_id');
    var cancelBtn = document.getElementById('btnAccCancel');

    if (!modal || !form || !sourceIdInput || !targetChronicleInput || !targetRealityInput) return;

    function endpointUrl(){
        var url = new URL(window.location.href);
        url.searchParams.set('s', 'admin_characters_clone');
        url.searchParams.set('ajax', '1');
        url.searchParams.set('_ts', Date.now());
        return url.toString();
    }

    function errorMessage(err){
        if (window.HGAdminHttp && typeof window.HGAdminHttp.errorMessage === 'function') {
            return window.HGAdminHttp.errorMessage(err);
        }
        return (err && (err.message || err.error)) ? (err.message || err.error) : 'Error';
    }

    function request(url, opts){
        if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
            return window.HGAdminHttp.request(url, opts || {});
        }
        return fetch(url, Object.assign({
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }, opts || {})).then(function(r){ return r.json(); });
    }

    function openCloneFromButton(btn){
        var id = parseInt(btn.getAttribute('data-id') || '0', 10) || 0;
        var name = String(btn.getAttribute('data-name') || '').trim();
        var chronicleId = parseInt(btn.getAttribute('data-chronicle-id') || '0', 10) || 0;
        var realityId = parseInt(btn.getAttribute('data-reality-id') || '0', 10) || 0;
        if (id <= 0) return;

        sourceIdInput.value = String(id);
        targetChronicleInput.value = chronicleId > 0 ? String(chronicleId) : '';
        targetRealityInput.value = realityId > 0 ? String(realityId) : '';
        summary.textContent = 'Se clona #' + id + (name ? (' - ' + name) : '') + ' con todos sus datos y bridges permitidos.';
        modal.style.display = 'flex';
    }

    function closeModal(){
        modal.style.display = 'none';
    }

    Array.prototype.forEach.call(document.querySelectorAll('button[data-clone="1"]'), function(btn){
        btn.addEventListener('click', function(){
            openCloneFromButton(btn);
        });
    });

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e){
        if (e.target === modal) closeModal();
    });

    form.addEventListener('submit', function(ev){
        ev.preventDefault();
        var fd = new FormData(form);
        fd.set('ajax', '1');
        request(endpointUrl(), {
            method: 'POST',
            body: fd,
            loadingEl: form
        }).then(function(payload){
            closeModal();
            var msg = (payload && (payload.message || payload.msg)) || 'Personaje clonado';
            if (window.HGAdminHttp && window.HGAdminHttp.notify) {
                window.HGAdminHttp.notify(msg, 'ok');
            }
            window.location.reload();
        }).catch(function(err){
            alert(errorMessage(err));
        });
    });
})();
</script>
<?php admin_panel_close(); ?>
