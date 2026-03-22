<?php
if (!isset($link) || !$link) {
    die("Error de conexion a la base de datos.");
}
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (method_exists($link, 'set_charset')) {
    $link->set_charset('utf8mb4');
} else {
    mysqli_set_charset($link, 'utf8mb4');
}

include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/pretty.php');

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function adl_table_exists(mysqli $db, string $table): bool
{
    $safe = mysqli_real_escape_string($db, preg_replace('/[^a-zA-Z0-9_]/', '', $table));
    if ($safe === '') return false;
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safe}' LIMIT 1";
    $rs = mysqli_query($db, $sql);
    return ($rs && mysqli_num_rows($rs) > 0);
}

function adl_column_exists(mysqli $db, string $table, string $column): bool
{
    $safeTable = mysqli_real_escape_string($db, preg_replace('/[^a-zA-Z0-9_]/', '', $table));
    $safeCol = mysqli_real_escape_string($db, preg_replace('/[^a-zA-Z0-9_]/', '', $column));
    if ($safeTable === '' || $safeCol === '') return false;
    $sql = "SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$safeTable}'
              AND COLUMN_NAME = '{$safeCol}'
            LIMIT 1";
    $rs = mysqli_query($db, $sql);
    return ($rs && mysqli_num_rows($rs) > 0);
}

function adl_bind_params(mysqli_stmt $st, string $types, array &$values): bool
{
    if ($types === '') return true;
    $refs = [];
    $refs[] = $types;
    foreach ($values as $k => $v) {
        $refs[] = &$values[$k];
    }
    return (bool)call_user_func_array([$st, 'bind_param'], $refs);
}

function adl_parse_int_list($value): array
{
    $raw = [];
    if (is_array($value)) {
        $raw = $value;
    } elseif (is_string($value) && trim($value) !== '') {
        $raw = explode(',', $value);
    }
    $out = [];
    foreach ($raw as $v) {
        $id = (int)$v;
        if ($id > 0) $out[$id] = $id;
    }
    return array_values($out);
}

function adl_fetch_docs(mysqli $db, string $q = ''): array
{
    $rows = [];
    $sql = "SELECT d.id, d.title, d.pretty_id, COALESCE(c.kind, '') AS category_name
            FROM fact_docs d
            LEFT JOIN dim_doc_categories c ON c.id = d.section_id
            WHERE 1=1";
    $types = '';
    $params = [];
    $q = trim($q);
    if ($q !== '') {
        $sql .= " AND (d.title LIKE ? OR d.pretty_id LIKE ?)";
        $types .= 'ss';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " ORDER BY c.sort_order ASC, d.title ASC";
    if ($st = $db->prepare($sql)) {
        if ($types !== '') adl_bind_params($st, $types, $params);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) { $rows[] = $row; }
        $st->close();
    }
    return $rows;
}

function adl_fetch_pairs(mysqli $db, string $sql): array
{
    $rows = [];
    if ($rs = $db->query($sql)) {
        while ($row = $rs->fetch_assoc()) { $rows[] = $row; }
        $rs->close();
    }
    return $rows;
}

function adl_fetch_doc_title(mysqli $db, int $docId): string
{
    if ($docId <= 0) return '';
    $title = '';
    if ($st = $db->prepare("SELECT title FROM fact_docs WHERE id = ? LIMIT 1")) {
        $st->bind_param('i', $docId);
        $st->execute();
        $st->bind_result($title);
        $st->fetch();
        $st->close();
    }
    return (string)$title;
}

function adl_fetch_characters_for_doc(
    mysqli $db,
    int $docId,
    int $chronicleId,
    int $realityId,
    int $systemId,
    int $organizationId,
    int $groupId,
    string $q,
    bool $hasChronicles,
    bool $hasRealityFilter,
    bool $hasSystems,
    bool $hasOrgFilter,
    bool $hasGroupFilter
): array {
    if ($docId <= 0) return [];

    $chronicleNameExpr = $hasChronicles ? "COALESCE(ch.name, '') AS chronicle_name" : "'' AS chronicle_name";
    $realityJoin = $hasRealityFilter ? "LEFT JOIN dim_realities r ON r.id = c.reality_id" : "";
    $realityNameExpr = $hasRealityFilter ? "COALESCE(r.name, '') AS reality_name" : "'' AS reality_name";
    $realityIdExpr = $hasRealityFilter ? "c.reality_id" : "0";
    $systemJoin = $hasSystems ? "LEFT JOIN dim_systems ds ON ds.id = c.system_id" : "";
    $systemNameExpr = $hasSystems ? "COALESCE(ds.name, '') AS system_name" : "'' AS system_name";
    $systemIdExpr = $hasSystems ? "COALESCE(c.system_id, 0)" : "0";

    $hasOrgActiveCol = $hasOrgFilter ? adl_column_exists($db, 'bridge_characters_organizations', 'is_active') : false;
    $hasGroupActiveCol = $hasGroupFilter ? adl_column_exists($db, 'bridge_characters_groups', 'is_active') : false;
    $orgActiveWhere = $hasOrgActiveCol ? "WHERE (is_active = 1 OR is_active IS NULL)" : "";
    $groupActiveWhere = $hasGroupActiveCol ? "WHERE (is_active = 1 OR is_active IS NULL)" : "";
    $orgActiveCond = $hasOrgActiveCol ? " AND (bcof.is_active = 1 OR bcof.is_active IS NULL)" : "";
    $groupActiveCond = $hasGroupActiveCol ? " AND (bcgf.is_active = 1 OR bcgf.is_active IS NULL)" : "";

    $orgBridgeJoin = $hasOrgFilter
        ? "LEFT JOIN (
                SELECT character_id, MIN(organization_id) AS organization_id
                FROM bridge_characters_organizations
                {$orgActiveWhere}
                GROUP BY character_id
            ) bo ON bo.character_id = c.id
           LEFT JOIN dim_organizations dorg ON dorg.id = bo.organization_id"
        : "";
    $orgIdExpr = $hasOrgFilter ? "COALESCE(bo.organization_id, 0)" : "0";
    $orgNameExpr = $hasOrgFilter ? "COALESCE(dorg.name, '') AS organization_name" : "'' AS organization_name";

    $groupBridgeJoin = $hasGroupFilter
        ? "LEFT JOIN (
                SELECT character_id, MIN(group_id) AS group_id
                FROM bridge_characters_groups
                {$groupActiveWhere}
                GROUP BY character_id
            ) bg ON bg.character_id = c.id
           LEFT JOIN dim_groups dg ON dg.id = bg.group_id"
        : "";
    $groupIdExpr = $hasGroupFilter ? "COALESCE(bg.group_id, 0)" : "0";
    $groupNameExpr = $hasGroupFilter ? "COALESCE(dg.name, '') AS group_name" : "'' AS group_name";

    $hasRelLabel = adl_column_exists($db, 'bridge_characters_docs', 'relation_label');
    $hasSortOrder = adl_column_exists($db, 'bridge_characters_docs', 'sort_order');
    $relExpr = $hasRelLabel ? "COALESCE(b.relation_label, '')" : "''";
    $sortExpr = $hasSortOrder ? "COALESCE(b.sort_order, 0)" : "0";

    $sql = "SELECT
                c.id,
                c.name,
                COALESCE(c.alias, '') AS alias,
                c.chronicle_id,
                {$realityIdExpr} AS reality_id,
                {$systemIdExpr} AS system_id,
                {$orgIdExpr} AS organization_id,
                {$groupIdExpr} AS group_id,
                {$chronicleNameExpr},
                {$realityNameExpr},
                {$systemNameExpr},
                {$orgNameExpr},
                {$groupNameExpr},
                CASE WHEN b.id IS NULL THEN 0 ELSE 1 END AS is_linked,
                {$relExpr} AS relation_label,
                {$sortExpr} AS sort_order
            FROM fact_characters c
            LEFT JOIN bridge_characters_docs b
                   ON b.character_id = c.id
                  AND b.doc_id = ?";
    if ($hasChronicles) $sql .= " LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id";
    $sql .= " {$systemJoin}";
    $sql .= " {$realityJoin}
             {$orgBridgeJoin}
             {$groupBridgeJoin}
            WHERE 1=1";

    $types = 'i';
    $params = [$docId];

    if ($chronicleId > 0) {
        $sql .= " AND c.chronicle_id = ?";
        $types .= 'i';
        $params[] = $chronicleId;
    }
    if ($realityId > 0 && $hasRealityFilter) {
        $sql .= " AND c.reality_id = ?";
        $types .= 'i';
        $params[] = $realityId;
    }
    if ($systemId > 0 && $hasSystems) {
        $sql .= " AND c.system_id = ?";
        $types .= 'i';
        $params[] = $systemId;
    }
    if ($organizationId > 0 && $hasOrgFilter) {
        $sql .= " AND EXISTS (
                    SELECT 1
                    FROM bridge_characters_organizations bcof
                    WHERE bcof.character_id = c.id
                      AND bcof.organization_id = ?
                      {$orgActiveCond}
                )";
        $types .= 'i';
        $params[] = $organizationId;
    }
    if ($groupId > 0 && $hasGroupFilter) {
        $sql .= " AND EXISTS (
                    SELECT 1
                    FROM bridge_characters_groups bcgf
                    WHERE bcgf.character_id = c.id
                      AND bcgf.group_id = ?
                      {$groupActiveCond}
                )";
        $types .= 'i';
        $params[] = $groupId;
    }

    $q = trim($q);
    if ($q !== '') {
        $sql .= " AND (
                    c.name LIKE ?
                    OR c.alias LIKE ?
                    OR CAST(c.id AS CHAR) = ?
                    OR " . ($hasSystems ? "ds.name LIKE ?" : "'' LIKE ?") . "
                    OR " . ($hasOrgFilter ? "dorg.name LIKE ?" : "'' LIKE ?") . "
                    OR " . ($hasGroupFilter ? "dg.name LIKE ?" : "'' LIKE ?") . "
                )";
        $types .= 'ssssss';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $q;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY is_linked DESC, c.name ASC";

    $rows = [];
    if ($st = $db->prepare($sql)) {
        adl_bind_params($st, $types, $params);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) { $rows[] = $row; }
        $st->close();
    }
    return $rows;
}

function adl_sync_doc_characters(mysqli $db, int $docId, array $characterIds): array
{
    $characterIds = adl_parse_int_list($characterIds);
    $existing = [];
    if ($st = $db->prepare("SELECT character_id FROM bridge_characters_docs WHERE doc_id = ?")) {
        $st->bind_param('i', $docId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $cid = (int)($row['character_id'] ?? 0);
            if ($cid > 0) $existing[$cid] = $cid;
        }
        $st->close();
    }

    $selectedMap = [];
    foreach ($characterIds as $cid) { $selectedMap[(int)$cid] = (int)$cid; }

    $toDelete = [];
    foreach ($existing as $cid) {
        if (!isset($selectedMap[$cid])) $toDelete[] = $cid;
    }

    $toInsert = [];
    foreach ($selectedMap as $cid) {
        if (!isset($existing[$cid])) $toInsert[] = $cid;
    }

    $hasRelLabel = adl_column_exists($db, 'bridge_characters_docs', 'relation_label');
    $hasSortOrder = adl_column_exists($db, 'bridge_characters_docs', 'sort_order');

    $db->begin_transaction();
    try {
        foreach ($toDelete as $cid) {
            if ($st = $db->prepare("DELETE FROM bridge_characters_docs WHERE doc_id = ? AND character_id = ?")) {
                $st->bind_param('ii', $docId, $cid);
                $st->execute();
                $st->close();
            }
        }

        $sort = 0;
        foreach ($toInsert as $cid) {
            $sort++;
            if ($hasRelLabel && $hasSortOrder) {
                $sql = "INSERT IGNORE INTO bridge_characters_docs (character_id, doc_id, relation_label, sort_order) VALUES (?, ?, '', ?)";
                if ($st = $db->prepare($sql)) {
                    $st->bind_param('iii', $cid, $docId, $sort);
                    $st->execute();
                    $st->close();
                }
            } elseif ($hasRelLabel) {
                $sql = "INSERT IGNORE INTO bridge_characters_docs (character_id, doc_id, relation_label) VALUES (?, ?, '')";
                if ($st = $db->prepare($sql)) {
                    $st->bind_param('ii', $cid, $docId);
                    $st->execute();
                    $st->close();
                }
            } elseif ($hasSortOrder) {
                $sql = "INSERT IGNORE INTO bridge_characters_docs (character_id, doc_id, sort_order) VALUES (?, ?, ?)";
                if ($st = $db->prepare($sql)) {
                    $st->bind_param('iii', $cid, $docId, $sort);
                    $st->execute();
                    $st->close();
                }
            } else {
                $sql = "INSERT IGNORE INTO bridge_characters_docs (character_id, doc_id) VALUES (?, ?)";
                if ($st = $db->prepare($sql)) {
                    $st->bind_param('ii', $cid, $docId);
                    $st->execute();
                    $st->close();
                }
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }

    return [
        'linked_count' => count($selectedMap),
        'inserted' => count($toInsert),
        'deleted' => count($toDelete),
    ];
}

$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_doc_links';
$CSRF = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : '';

$hasDocs = adl_table_exists($link, 'fact_docs');
$hasCharacters = adl_table_exists($link, 'fact_characters');
$hasBridge = adl_table_exists($link, 'bridge_characters_docs');
$hasChronicles = adl_table_exists($link, 'dim_chronicles');
$hasRealities = adl_table_exists($link, 'dim_realities');
$hasCharacterReality = adl_column_exists($link, 'fact_characters', 'reality_id');
$hasRealityFilter = ($hasRealities && $hasCharacterReality);
$hasSystems = adl_table_exists($link, 'dim_systems') && adl_column_exists($link, 'fact_characters', 'system_id');
$hasOrgFilter = adl_table_exists($link, 'bridge_characters_organizations') && adl_table_exists($link, 'dim_organizations');
$hasGroupFilter = adl_table_exists($link, 'bridge_characters_groups') && adl_table_exists($link, 'dim_groups');
$schemaReady = ($hasDocs && $hasCharacters && $hasBridge);

$payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
$req = function(string $key, $default = null) use ($payload) {
    if (array_key_exists($key, $payload)) return $payload[$key];
    if (array_key_exists($key, $_POST)) return $_POST[$key];
    return $default;
};

if ($isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('hg_admin_require_session')) hg_admin_require_session(true);

    $csrfToken = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    $csrfOk = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid((string)$csrfToken, $ADMIN_CSRF_SESSION_KEY)
        : false;
    if (!$csrfOk) {
        hg_admin_json_error('CSRF invalido. Recarga la pagina.', 403, ['csrf' => 'invalid']);
    }
    if (!$schemaReady) {
        hg_admin_json_error('Falta esquema necesario para este modulo.', 400, ['schema' => 'missing']);
    }

    $action = (string)$req('action', '');
    $docId = (int)$req('doc_id', 0);
    $filCr = (int)$req('fil_cr', 0);
    $filRe = (int)$req('fil_re', 0);
    $filSy = (int)$req('fil_sy', 0);
    $filOr = (int)$req('fil_or', 0);
    $filGr = (int)$req('fil_gr', 0);
    $charQ = trim((string)$req('char_q', ''));

    if ($action === 'list_state') {
        if ($docId <= 0) {
            hg_admin_json_error('Selecciona un documento valido.', 400, ['doc_id' => 'required']);
        }
        $rows = adl_fetch_characters_for_doc(
            $link,
            $docId,
            $filCr,
            $filRe,
            $filSy,
            $filOr,
            $filGr,
            $charQ,
            $hasChronicles,
            $hasRealityFilter,
            $hasSystems,
            $hasOrgFilter,
            $hasGroupFilter
        );
        $docTitle = adl_fetch_doc_title($link, $docId);
        hg_admin_json_success([
            'doc_id' => $docId,
            'doc_title' => $docTitle,
            'rows' => $rows,
        ], 'Estado cargado', ['count' => count($rows)]);
    }

    if ($action === 'save_links') {
        if ($docId <= 0) {
            hg_admin_json_error('Selecciona un documento valido.', 400, ['doc_id' => 'required']);
        }
        $characterIds = $req('character_ids', []);
        if (!is_array($characterIds) && array_key_exists('character_ids[]', $_POST)) {
            $characterIds = $_POST['character_ids[]'];
        }
        try {
            $stats = adl_sync_doc_characters($link, $docId, is_array($characterIds) ? $characterIds : []);
        } catch (Throwable $e) {
            hg_admin_json_error('Error al guardar vinculos.', 500, ['sql' => $e->getMessage()]);
        }
        $rows = adl_fetch_characters_for_doc(
            $link,
            $docId,
            $filCr,
            $filRe,
            $filSy,
            $filOr,
            $filGr,
            $charQ,
            $hasChronicles,
            $hasRealityFilter,
            $hasSystems,
            $hasOrgFilter,
            $hasGroupFilter
        );
        hg_admin_json_success([
            'stats' => $stats,
            'rows' => $rows,
        ], 'Vinculos guardados', ['count' => count($rows)]);
    }

    hg_admin_json_error('Accion no valida', 400, ['action' => 'unsupported']);
}

$selectedDocId = (int)($_GET['doc_id'] ?? $_POST['doc_id'] ?? 0);
$selectedChronicleId = (int)($_GET['fil_cr'] ?? $_POST['fil_cr'] ?? 0);
$selectedRealityId = (int)($_GET['fil_re'] ?? $_POST['fil_re'] ?? 0);
$selectedSystemId = (int)($_GET['fil_sy'] ?? $_POST['fil_sy'] ?? 0);
$selectedOrganizationId = (int)($_GET['fil_or'] ?? $_POST['fil_or'] ?? 0);
$selectedGroupId = (int)($_GET['fil_gr'] ?? $_POST['fil_gr'] ?? 0);
$docQ = trim((string)($_GET['doc_q'] ?? $_POST['doc_q'] ?? ''));
$charQ = trim((string)($_GET['char_q'] ?? $_POST['char_q'] ?? ''));

$docs = $hasDocs ? adl_fetch_docs($link, $docQ) : [];
$chronicles = $hasChronicles ? adl_fetch_pairs($link, "SELECT id, name FROM dim_chronicles ORDER BY name ASC") : [];
$realities = $hasRealityFilter ? adl_fetch_pairs($link, "SELECT id, name FROM dim_realities ORDER BY name ASC") : [];
$systems = $hasSystems ? adl_fetch_pairs($link, "SELECT id, name FROM dim_systems ORDER BY name ASC") : [];
$organizations = $hasOrgFilter ? adl_fetch_pairs($link, "SELECT id, name FROM dim_organizations ORDER BY name ASC") : [];
$groups = $hasGroupFilter ? adl_fetch_pairs($link, "SELECT id, name FROM dim_groups ORDER BY name ASC") : [];

$docTitle = ($selectedDocId > 0 && $hasDocs) ? adl_fetch_doc_title($link, $selectedDocId) : '';
$rows = ($selectedDocId > 0 && $schemaReady)
    ? adl_fetch_characters_for_doc(
        $link,
        $selectedDocId,
        $selectedChronicleId,
        $selectedRealityId,
        $selectedSystemId,
        $selectedOrganizationId,
        $selectedGroupId,
        $charQ,
        $hasChronicles,
        $hasRealityFilter,
        $hasSystems,
        $hasOrgFilter,
        $hasGroupFilter
    )
    : [];

$linkedCount = 0;
foreach ($rows as $r) {
    if ((int)($r['is_linked'] ?? 0) === 1) $linkedCount++;
}
$characterTableCols = 4
    + ($hasRealityFilter ? 1 : 0)
    + ($hasSystems ? 1 : 0)
    + ($hasOrgFilter ? 1 : 0)
    + ($hasGroupFilter ? 1 : 0);

$actions = "<span class='adm-flex-right-8'>"
    . "<a class='btn' href='/talim?s=admin_character_links'>Vincular desde Personaje</a>"
    . "</span>";
admin_panel_open('Vincular Documento -> Personajes', $actions);
echo "<style>.panel-wrap, .panel-wrap * { text-align: left !important; }</style>";
?>
<div id="adl-container">
<div id="adl-root">

<?php if (!$schemaReady): ?>
    <div class="flash">
        <div class="err">Falta esquema requerido. Necesario: <code>fact_docs</code>, <code>fact_characters</code>, <code>bridge_characters_docs</code>.</div>
    </div>
<?php endif; ?>

<fieldset id="renglonArchivos">
    <legend>Seleccionar documento</legend>
    <form method="GET" class="adm-inline-filters" data-adl-filter="1">
        <input type="hidden" name="s" value="admin_doc_links">
        <input class="inp" type="text" name="doc_q" value="<?= h($docQ) ?>" placeholder="Buscar documento...">
        <select class="select" name="doc_id" required>
            <option value="">-- Selecciona documento --</option>
            <?php foreach ($docs as $d): ?>
                <?php $did = (int)($d['id'] ?? 0); ?>
                <option value="<?= $did ?>" <?= ($did === $selectedDocId ? 'selected' : '') ?>>
                    [<?= h((string)($d['category_name'] ?? '-')) ?>] <?= h((string)($d['title'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($hasChronicles): ?>
            <select class="select" name="fil_cr">
                <option value="0">Cronica: Todas</option>
                <?php foreach ($chronicles as $ch): ?>
                    <?php $cid = (int)($ch['id'] ?? 0); ?>
                    <option value="<?= $cid ?>" <?= ($cid === $selectedChronicleId ? 'selected' : '') ?>>
                        <?= h((string)($ch['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?php if ($hasRealityFilter): ?>
            <select class="select" name="fil_re">
                <option value="0">Realidad: Todas</option>
                <?php foreach ($realities as $re): ?>
                    <?php $rid = (int)($re['id'] ?? 0); ?>
                    <option value="<?= $rid ?>" <?= ($rid === $selectedRealityId ? 'selected' : '') ?>>
                        <?= h((string)($re['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?php if ($hasSystems): ?>
            <select class="select" name="fil_sy">
                <option value="0">Sistema: Todos</option>
                <?php foreach ($systems as $sy): ?>
                    <?php $sid = (int)($sy['id'] ?? 0); ?>
                    <option value="<?= $sid ?>" <?= ($sid === $selectedSystemId ? 'selected' : '') ?>>
                        <?= h((string)($sy['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?php if ($hasOrgFilter): ?>
            <select class="select" name="fil_or">
                <option value="0">Organizacion: Todas</option>
                <?php foreach ($organizations as $or): ?>
                    <?php $oid = (int)($or['id'] ?? 0); ?>
                    <option value="<?= $oid ?>" <?= ($oid === $selectedOrganizationId ? 'selected' : '') ?>>
                        <?= h((string)($or['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?php if ($hasGroupFilter): ?>
            <select class="select" name="fil_gr">
                <option value="0">Grupo: Todos</option>
                <?php foreach ($groups as $gr): ?>
                    <?php $gid = (int)($gr['id'] ?? 0); ?>
                    <option value="<?= $gid ?>" <?= ($gid === $selectedGroupId ? 'selected' : '') ?>>
                        <?= h((string)($gr['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <input class="inp" type="text" name="char_q" value="<?= h($charQ) ?>" placeholder="Filtrar personajes...">
        <button class="btn" type="submit">Cargar</button>
        <a class="btn" href="/talim?s=admin_doc_links">Limpiar</a>
    </form>
    <?php if ($selectedDocId > 0): ?>
        <p>
            Documento actual:
            <strong><?= h($docTitle !== '' ? $docTitle : ('#' . $selectedDocId)) ?></strong>
            <span>| Vinculados: <?= (int)$linkedCount ?></span>
            <a href="<?= h(pretty_url($link, 'fact_docs', '/documents', $selectedDocId)) ?>" target="_blank" rel="noopener noreferrer">Ver documento</a>
        </p>
    <?php endif; ?>
</fieldset>

<?php if ($selectedDocId > 0 && $schemaReady): ?>
<fieldset id="renglonArchivos">
    <legend>Personajes vinculados al documento</legend>
    <form method="POST" data-adl-save="1">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="action" value="save_links">
        <input type="hidden" name="doc_id" value="<?= (int)$selectedDocId ?>">
        <input type="hidden" name="fil_cr" value="<?= (int)$selectedChronicleId ?>">
        <input type="hidden" name="fil_re" value="<?= (int)$selectedRealityId ?>">
        <input type="hidden" name="fil_sy" value="<?= (int)$selectedSystemId ?>">
        <input type="hidden" name="fil_or" value="<?= (int)$selectedOrganizationId ?>">
        <input type="hidden" name="fil_gr" value="<?= (int)$selectedGroupId ?>">
        <input type="hidden" name="doc_q" value="<?= h($docQ) ?>">
        <input type="hidden" name="char_q" value="<?= h($charQ) ?>">

        <div class="adm-flex-right-8" style="margin-bottom: 10px;">
            <button class="btn" type="button" id="adlCheckAll">Seleccionar todos</button>
            <button class="btn" type="button" id="adlUncheckAll">Deseleccionar todos</button>
            <button class="btn btn-blue" type="submit">Guardar vinculos</button>
        </div>

        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th class="adm-w-80">Vinculo</th>
                        <th class="adm-w-80">ID</th>
                        <th>Personaje</th>
                        <th>Cronica</th>
                        <?php if ($hasRealityFilter): ?><th>Realidad</th><?php endif; ?>
                        <?php if ($hasSystems): ?><th>Sistema</th><?php endif; ?>
                        <?php if ($hasOrgFilter): ?><th>Organizacion</th><?php endif; ?>
                        <?php if ($hasGroupFilter): ?><th>Grupo</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="<?= (int)$characterTableCols ?>">No hay personajes con el filtro actual.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                                $cid = (int)($r['id'] ?? 0);
                                $checked = ((int)($r['is_linked'] ?? 0) === 1);
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="character_ids[]" value="<?= $cid ?>" <?= $checked ? 'checked' : '' ?>>
                                </td>
                                <td>#<?= $cid ?></td>
                                <td>
                                    <?= h((string)($r['name'] ?? '')) ?>
                                    <?php if (trim((string)($r['alias'] ?? '')) !== ''): ?>
                                        <small>(<?= h((string)$r['alias']) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= h((string)($r['chronicle_name'] ?? '')) ?></td>
                                <?php if ($hasRealityFilter): ?>
                                    <td><?= h((string)($r['reality_name'] ?? '')) ?></td>
                                <?php endif; ?>
                                <?php if ($hasSystems): ?>
                                    <td><?= h((string)($r['system_name'] ?? '')) ?></td>
                                <?php endif; ?>
                                <?php if ($hasOrgFilter): ?>
                                    <td><?= h((string)($r['organization_name'] ?? '')) ?></td>
                                <?php endif; ?>
                                <?php if ($hasGroupFilter): ?>
                                    <td><?= h((string)($r['group_name'] ?? '')) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</fieldset>
<?php endif; ?>

</div>
</div>

<script src="/assets/js/admin/admin-http.js"></script>
<script>
(function(){
    var container = document.getElementById('adl-container');
    if (!container) return;

    window.ADMIN_CSRF_TOKEN = <?= json_encode((string)$CSRF, JSON_UNESCAPED_UNICODE) ?>;
    var moduleUrl = '/talim?s=admin_doc_links';
    var ajaxUrl = '/talim?ajax=1&s=admin_doc_links';

    function bindMassButtons() {
        var root = container.querySelector('#adl-root');
        if (!root) return;
        var checkAll = root.querySelector('#adlCheckAll');
        var uncheckAll = root.querySelector('#adlUncheckAll');
        if (checkAll) {
            checkAll.addEventListener('click', function(){
                root.querySelectorAll('input[name="character_ids[]"]').forEach(function(cb){ cb.checked = true; });
            });
        }
        if (uncheckAll) {
            uncheckAll.addEventListener('click', function(){
                root.querySelectorAll('input[name="character_ids[]"]').forEach(function(cb){ cb.checked = false; });
            });
        }
    }

    function buildFilterQuery() {
        var form = container.querySelector('form[data-adl-filter]');
        if (!form) return '';
        var fd = new FormData(form);
        var params = new URLSearchParams();
        fd.forEach(function(value, key){
            var v = String(value == null ? '' : value).trim();
            if (key === 's') return;
            if (v === '' || v === '0') return;
            params.set(key, v);
        });
        return params.toString();
    }

    async function reloadModule(updateUrl) {
        var query = buildFilterQuery();
        var url = moduleUrl + (query ? ('&' + query) : '');
        var res = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        var html = await res.text();
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var nextRoot = doc.querySelector('#adl-root');
        if (nextRoot) {
            container.innerHTML = nextRoot.outerHTML;
            bindMassButtons();
            if (updateUrl) {
                var prettyUrl = moduleUrl + (query ? ('&' + query) : '');
                window.history.replaceState({}, '', prettyUrl);
            }
        }
    }

    container.addEventListener('submit', async function(ev){
        var form = ev.target;
        if (!form || form.tagName !== 'FORM') return;

        var method = String(form.getAttribute('method') || 'GET').toUpperCase();
        if (method === 'GET') {
            ev.preventDefault();
            try {
                await reloadModule(true);
            } catch (e) {
                if (window.HGAdminHttp && window.HGAdminHttp.notify) {
                    window.HGAdminHttp.notify(window.HGAdminHttp.errorMessage(e), 'err', 3200);
                }
            }
            return;
        }

        if (method === 'POST') {
            if (!window.HGAdminHttp || typeof window.HGAdminHttp.request !== 'function') {
                return;
            }
            ev.preventDefault();
            try {
                var fd = new FormData(form);
                var payload = await HGAdminHttp.request(ajaxUrl, {
                    method: 'POST',
                    body: fd,
                    loadingEl: form
                });
                if (window.HGAdminHttp && window.HGAdminHttp.notify) {
                    window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok');
                }
                await reloadModule(true);
            } catch (e) {
                if (window.HGAdminHttp && window.HGAdminHttp.notify) {
                    window.HGAdminHttp.notify(window.HGAdminHttp.errorMessage(e), 'err', 3600);
                }
            }
        }
    });

    bindMassButtons();
})();
</script>

<?php admin_panel_close(); ?>
