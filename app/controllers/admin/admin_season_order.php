<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../tools/season_order_schema_20260522.php');

$csrfKey = 'csrf_admin_season_order';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');
$isAjax = function_exists('hg_admin_is_ajax_request') ? hg_admin_is_ajax_request() : (((string)($_GET['ajax'] ?? '') === '1'));
$payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];

function hg_aso_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hg_aso_slugify(string $text): string
{
    $text = trim($text);
    if ($text === '') return '';
    if (function_exists('iconv')) {
        $try = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($try) && $try !== '') {
            $text = $try;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
    return trim($text, '-');
}

function hg_aso_csrf_ok(string $csrfKey, array $payload): bool
{
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, $csrfKey)
        : ($token !== '' && isset($_SESSION[$csrfKey]) && hash_equals((string)$_SESSION[$csrfKey], $token));
}

function hg_aso_schema_ready(mysqli $link): bool
{
    return hg_so_schema_table_exists($link, 'bridge_season_order_nodes');
}

function hg_aso_fetch_season_options(mysqli $link): array
{
    $rows = [];
    $sql = "
        SELECT id, name, pretty_id, season_number, COALESCE(season_kind, 'temporada') AS season_kind
        FROM dim_seasons
        ORDER BY
            CASE
                WHEN COALESCE(season_kind, 'temporada') = 'temporada' THEN 1
                WHEN COALESCE(season_kind, 'temporada') = 'inciso' THEN 2
                WHEN COALESCE(season_kind, 'temporada') = 'historia_personal' THEN 3
                WHEN COALESCE(season_kind, 'temporada') = 'especial' THEN 4
                ELSE 99
            END ASC,
            COALESCE(sort_order, 999999) ASC,
            season_number ASC,
            name ASC
    ";
    if ($rs = $link->query($sql)) {
        while ($row = $rs->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rows[] = [
                'id' => $id,
                'name' => (string)($row['name'] ?? ''),
                'pretty_id' => (string)($row['pretty_id'] ?? ''),
                'season_number' => (int)($row['season_number'] ?? 0),
                'season_kind' => (string)($row['season_kind'] ?? 'temporada'),
                'href' => pretty_url($link, 'dim_seasons', '/seasons', $id),
            ];
        }
        $rs->close();
    }
    return $rows;
}

function hg_aso_fetch_orders(mysqli $link): array
{
    $rows = [];
    if (!hg_aso_schema_ready($link)) {
        return $rows;
    }

    $sql = "
        SELECT
            order_key,
            MAX(order_label) AS order_label,
            COUNT(*) AS node_count,
            SUM(CASE WHEN branch_type = 'secondary' THEN 1 ELSE 0 END) AS secondary_count,
            MIN(position) AS first_position
        FROM bridge_season_order_nodes
        WHERE is_active = 1
        GROUP BY order_key
        ORDER BY first_position ASC, order_key ASC
    ";
    if ($rs = $link->query($sql)) {
        while ($row = $rs->fetch_assoc()) {
            $key = trim((string)($row['order_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $rows[] = [
                'order_key' => $key,
                'order_label' => trim((string)($row['order_label'] ?? '')) !== '' ? (string)$row['order_label'] : $key,
                'node_count' => (int)($row['node_count'] ?? 0),
                'secondary_count' => (int)($row['secondary_count'] ?? 0),
            ];
        }
        $rs->close();
    }
    return $rows;
}

function hg_aso_fetch_nodes(mysqli $link, string $orderKey): array
{
    $rows = [];
    if (!hg_aso_schema_ready($link) || trim($orderKey) === '') {
        return $rows;
    }

    $sql = "
        SELECT
            n.id,
            n.order_key,
            n.order_label,
            n.position,
            n.branch_type,
            n.parent_node_id,
            n.node_kind,
            n.season_id,
            n.episode_start,
            n.episode_end,
            n.label,
            n.description,
            n.is_active,
            s.name AS season_name,
            s.pretty_id AS season_pretty_id
        FROM bridge_season_order_nodes n
        LEFT JOIN dim_seasons s ON s.id = n.season_id
        WHERE n.order_key = ?
          AND n.is_active = 1
        ORDER BY n.position ASC, n.id ASC
    ";
    if ($stmt = $link->prepare($sql)) {
        $stmt->bind_param('s', $orderKey);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($row = $rs->fetch_assoc()) {
            $seasonId = (int)($row['season_id'] ?? 0);
            $row['id'] = (int)($row['id'] ?? 0);
            $row['position'] = (int)($row['position'] ?? 0);
            $row['parent_node_id'] = (int)($row['parent_node_id'] ?? 0);
            $row['season_id'] = $seasonId;
            $row['episode_start'] = ($row['episode_start'] !== null) ? (int)$row['episode_start'] : null;
            $row['episode_end'] = ($row['episode_end'] !== null) ? (int)$row['episode_end'] : null;
            $row['is_active'] = (int)($row['is_active'] ?? 0);
            $row['season_name'] = (string)($row['season_name'] ?? '');
            $row['href'] = $seasonId > 0 ? pretty_url($link, 'dim_seasons', '/seasons', $seasonId) : '';
            $rows[] = $row;
        }
        $stmt->close();
    }
    return $rows;
}

function hg_aso_build_state(mysqli $link, string $selectedOrder = ''): array
{
    $schemaReady = hg_aso_schema_ready($link);
    $seasonOptions = hg_aso_fetch_season_options($link);
    $orders = hg_aso_fetch_orders($link);

    if ($selectedOrder === '' && !empty($orders)) {
        $selectedOrder = (string)$orders[0]['order_key'];
    }

    return [
        'schema_ready' => $schemaReady,
        'selected_order' => $selectedOrder,
        'orders' => $orders,
        'nodes' => $schemaReady ? hg_aso_fetch_nodes($link, $selectedOrder) : [],
        'season_options' => $seasonOptions,
    ];
}

function hg_aso_next_position(mysqli $link, string $orderKey): int
{
    $stmt = $link->prepare("SELECT COALESCE(MAX(position), 0) + 10 AS next_pos FROM bridge_season_order_nodes WHERE order_key = ? AND is_active = 1");
    if (!$stmt) {
        return 10;
    }
    $stmt->bind_param('s', $orderKey);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return max(10, (int)($row['next_pos'] ?? 10));
}

function hg_aso_order_exists(mysqli $link, string $orderKey): bool
{
    $stmt = $link->prepare("SELECT id FROM bridge_season_order_nodes WHERE order_key = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $orderKey);
    $stmt->execute();
    $rs = $stmt->get_result();
    $ok = $rs && $rs->fetch_assoc();
    $stmt->close();
    return (bool)$ok;
}

function hg_aso_node_exists_in_order(mysqli $link, int $nodeId, string $orderKey): bool
{
    if ($nodeId <= 0 || $orderKey === '') {
        return false;
    }
    $stmt = $link->prepare("SELECT id FROM bridge_season_order_nodes WHERE id = ? AND order_key = ? AND is_active = 1 LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $nodeId, $orderKey);
    $stmt->execute();
    $rs = $stmt->get_result();
    $ok = $rs && $rs->fetch_assoc();
    $stmt->close();
    return (bool)$ok;
}

function hg_aso_save_node(mysqli $link, array $payload, array $seasonOptions): array
{
    $allowedNodeKinds = ['season', 'season_range', 'arc', 'custom'];
    $allowedBranchTypes = ['main', 'secondary'];
    $seasonIds = [];
    foreach ($seasonOptions as $season) {
        $seasonIds[(int)$season['id']] = (string)($season['name'] ?? '');
    }

    $id = max(0, (int)($payload['id'] ?? 0));
    $orderKey = hg_aso_slugify((string)($payload['order_key'] ?? ''));
    $orderLabel = trim((string)($payload['order_label'] ?? ''));
    $position = (int)($payload['position'] ?? 0);
    $branchType = (string)($payload['branch_type'] ?? 'main');
    $parentNodeId = max(0, (int)($payload['parent_node_id'] ?? 0));
    $nodeKind = (string)($payload['node_kind'] ?? 'season');
    $seasonId = max(0, (int)($payload['season_id'] ?? 0));
    $episodeStart = trim((string)($payload['episode_start'] ?? '')) !== '' ? max(1, (int)$payload['episode_start']) : null;
    $episodeEnd = trim((string)($payload['episode_end'] ?? '')) !== '' ? max(1, (int)$payload['episode_end']) : null;
    $label = trim((string)($payload['label'] ?? ''));
    $description = trim((string)($payload['description'] ?? ''));

    if ($orderKey === '') {
        return ['ok' => false, 'message' => 'El identificador del orden es obligatorio.'];
    }
    if ($orderLabel === '') {
        $orderLabel = ucwords(str_replace(['-', '_'], ' ', $orderKey));
    }
    if (!in_array($branchType, $allowedBranchTypes, true)) {
        $branchType = 'main';
    }
    if (!in_array($nodeKind, $allowedNodeKinds, true)) {
        $nodeKind = 'season';
    }
    if ($seasonId > 0 && !isset($seasonIds[$seasonId])) {
        return ['ok' => false, 'message' => 'La temporada seleccionada no existe.'];
    }
    if ($branchType === 'secondary') {
        if ($parentNodeId <= 0) {
            return ['ok' => false, 'message' => 'Una rama secundaria necesita un nodo padre.'];
        }
        if (!hg_aso_node_exists_in_order($link, $parentNodeId, $orderKey)) {
            return ['ok' => false, 'message' => 'El nodo padre no pertenece a ese orden.'];
        }
    } else {
        $parentNodeId = 0;
    }
    if ($episodeStart !== null && $episodeEnd !== null && $episodeEnd < $episodeStart) {
        return ['ok' => false, 'message' => 'El episodio final no puede ir antes del inicial.'];
    }
    if ($label === '' && $seasonId > 0) {
        $label = $seasonIds[$seasonId];
    }
    if ($label === '') {
        return ['ok' => false, 'message' => 'El nodo necesita un titulo o una temporada vinculada.'];
    }
    if ($position <= 0) {
        $position = hg_aso_next_position($link, $orderKey);
    }

    $seasonIdValue = $seasonId > 0 ? $seasonId : 0;
    $episodeStartValue = $episodeStart !== null ? $episodeStart : 0;
    $episodeEndValue = $episodeEnd !== null ? $episodeEnd : 0;

    if ($id > 0) {
        $sql = "
            UPDATE bridge_season_order_nodes
            SET order_key = ?, order_label = ?, position = ?, branch_type = ?, parent_node_id = NULLIF(?, 0), node_kind = ?, season_id = NULLIF(?, 0), episode_start = NULLIF(?, 0), episode_end = NULLIF(?, 0), label = ?, description = ?
            WHERE id = ?
        ";
        $stmt = $link->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'No se pudo preparar la actualizacion.'];
        }
        $stmt->bind_param(
            'ssisisiiissi',
            $orderKey,
            $orderLabel,
            $position,
            $branchType,
            $parentNodeId,
            $nodeKind,
            $seasonIdValue,
            $episodeStartValue,
            $episodeEndValue,
            $label,
            $description,
            $id
        );
        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();
        return $ok
            ? ['ok' => true, 'message' => 'Nodo actualizado.', 'selected_order' => $orderKey]
            : ['ok' => false, 'message' => 'No se pudo actualizar el nodo: ' . $error];
    }

    $sql = "
        INSERT INTO bridge_season_order_nodes
            (order_key, order_label, position, branch_type, parent_node_id, node_kind, season_id, episode_start, episode_end, label, description, is_active)
        VALUES
            (?, ?, ?, ?, NULLIF(?, 0), ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?, ?, 1)
    ";
    $stmt = $link->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'message' => 'No se pudo preparar el alta del nodo.'];
    }
    $stmt->bind_param(
        'ssisisiiiss',
        $orderKey,
        $orderLabel,
        $position,
        $branchType,
        $parentNodeId,
        $nodeKind,
        $seasonIdValue,
        $episodeStartValue,
        $episodeEndValue,
        $label,
        $description
    );
    $ok = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();
    return $ok
        ? ['ok' => true, 'message' => 'Nodo creado.', 'selected_order' => $orderKey]
        : ['ok' => false, 'message' => 'No se pudo crear el nodo: ' . $error];
}

function hg_aso_save_order_meta(mysqli $link, array $payload): array
{
    $original = hg_aso_slugify((string)($payload['original_order_key'] ?? ''));
    $orderKey = hg_aso_slugify((string)($payload['order_key'] ?? ''));
    $orderLabel = trim((string)($payload['order_label'] ?? ''));

    if ($original === '' || $orderKey === '') {
        return ['ok' => false, 'message' => 'El orden seleccionado no es valido.'];
    }
    if (!hg_aso_order_exists($link, $original)) {
        return ['ok' => false, 'message' => 'Ese orden ya no existe.'];
    }
    if ($orderLabel === '') {
        $orderLabel = ucwords(str_replace(['-', '_'], ' ', $orderKey));
    }
    if ($original !== $orderKey && hg_aso_order_exists($link, $orderKey)) {
        return ['ok' => false, 'message' => 'Ya existe otro orden con ese identificador.'];
    }

    $stmt = $link->prepare("UPDATE bridge_season_order_nodes SET order_key = ?, order_label = ? WHERE order_key = ?");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'No se pudo preparar la actualizacion del orden.'];
    }
    $stmt->bind_param('sss', $orderKey, $orderLabel, $original);
    $ok = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();

    return $ok
        ? ['ok' => true, 'message' => 'Cabecera del orden actualizada.', 'selected_order' => $orderKey]
        : ['ok' => false, 'message' => 'No se pudo actualizar el orden: ' . $error];
}

function hg_aso_delete_node(mysqli $link, array $payload): array
{
    $id = max(0, (int)($payload['id'] ?? 0));
    if ($id <= 0) {
        return ['ok' => false, 'message' => 'Nodo invalido.'];
    }

    $stmt = $link->prepare("DELETE FROM bridge_season_order_nodes WHERE id = ?");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'No se pudo preparar el borrado.'];
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'message' => 'No se pudo borrar el nodo: ' . $error];
    }

    $cleanup = $link->prepare("UPDATE bridge_season_order_nodes SET parent_node_id = NULL, branch_type = 'main' WHERE parent_node_id = ?");
    if ($cleanup) {
        $cleanup->bind_param('i', $id);
        $cleanup->execute();
        $cleanup->close();
    }

    return ['ok' => true, 'message' => 'Nodo borrado.'];
}

$selectedOrder = trim((string)($_GET['order'] ?? ($payload['selected_order'] ?? '')));

if ($isAjax) {
    hg_admin_require_session(true);

    if (!hg_aso_schema_ready($link)) {
        hg_admin_json_error('Falta la tabla del bridge de orden. Ejecuta primero el schema web.', 400, ['schema' => 'missing'], [
            'schema_ready' => false,
            'schema_url' => '/talim?s=admin_season_order_schema',
        ]);
    }

    $action = (string)($payload['action'] ?? ($_GET['action'] ?? 'state'));
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hg_aso_csrf_ok($csrfKey, $payload)) {
        hg_admin_json_error('CSRF invalido. Recarga la pagina.', 400, ['csrf' => 'invalid']);
    }

    $seasonOptions = hg_aso_fetch_season_options($link);
    if ($action === 'save_node') {
        $result = hg_aso_save_node($link, $payload, $seasonOptions);
        if (empty($result['ok'])) {
            hg_admin_json_error((string)$result['message'], 400);
        }
        $selectedOrder = (string)($result['selected_order'] ?? $selectedOrder);
        hg_admin_json_success(hg_aso_build_state($link, $selectedOrder), (string)$result['message']);
    }
    if ($action === 'save_order_meta') {
        $result = hg_aso_save_order_meta($link, $payload);
        if (empty($result['ok'])) {
            hg_admin_json_error((string)$result['message'], 400);
        }
        $selectedOrder = (string)($result['selected_order'] ?? $selectedOrder);
        hg_admin_json_success(hg_aso_build_state($link, $selectedOrder), (string)$result['message']);
    }
    if ($action === 'delete_node') {
        $result = hg_aso_delete_node($link, $payload);
        if (empty($result['ok'])) {
            hg_admin_json_error((string)$result['message'], 400);
        }
        hg_admin_json_success(hg_aso_build_state($link, $selectedOrder), (string)$result['message']);
    }

    hg_admin_json_success(hg_aso_build_state($link, $selectedOrder), 'Estado');
}

$initialState = hg_aso_build_state($link, $selectedOrder);
$schemaReady = !empty($initialState['schema_ready']);
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();

admin_panel_open(
    'Orden de temporadas',
    '<span class="adm-flex-right-8">'
    . '<a class="btn" href="/talim?s=admin_season_order_schema">Preparar schema</a>'
    . '<a class="btn" href="/seasons/order" target="_blank">Ver pagina publica</a>'
    . '</span>'
);
?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= hg_aso_h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>

<style>
.adm-season-order-grid{display:grid;grid-template-columns:minmax(260px,320px) minmax(0,1fr);gap:14px}
.adm-season-order-box{border:1px solid #17366e;background:#07153a;padding:12px}
.adm-season-order-box h3{margin:0 0 10px}
.adm-season-order-help{color:#b5cae6;font-size:12px;line-height:1.45}
.adm-season-order-select,.adm-season-order-input,.adm-season-order-textarea{width:100%;box-sizing:border-box}
.adm-season-order-meta{display:grid;gap:8px}
.adm-season-order-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.adm-season-order-table{width:100%;border-collapse:collapse}
.adm-season-order-table th,.adm-season-order-table td{padding:8px 6px;border-bottom:1px solid #17366e;text-align:left;vertical-align:top}
.adm-season-order-badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;border:1px solid #2d63c9;background:#082261;color:#dcecff;font-size:11px;line-height:1.2}
.adm-season-order-badge-secondary{border-color:#0fa88d;background:#083f38;color:#dcfff8}
.adm-season-order-node-title{font-weight:700;color:#eef6ff}
.adm-season-order-node-desc{margin-top:4px;color:#b8cae8;font-size:12px;line-height:1.4}
.adm-season-order-muted{color:#94abd1;font-size:12px}
.adm-season-order-empty{padding:14px;border:1px dashed #27467f;color:#c6d9f5}
.adm-season-order-modal-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 12px}
.adm-season-order-modal-grid label{display:grid;gap:5px}
.adm-season-order-modal-wide{grid-column:1/-1}
@media (max-width: 980px){.adm-season-order-grid{grid-template-columns:1fr}.adm-season-order-modal-grid{grid-template-columns:1fr}}
</style>

<?php if (!$schemaReady): ?>
    <div class="err">
        Falta `bridge_season_order_nodes`. Ejecuta primero el schema desde <a href="/talim?s=admin_season_order_schema">Orden de temporadas: schema</a>.
    </div>
<?php else: ?>
    <div class="adm-season-order-grid">
        <section class="adm-season-order-box">
            <h3>Recorrido</h3>
            <div class="adm-season-order-meta">
                <label>
                    Orden activo
                    <select id="asoOrderSelect" class="select adm-season-order-select"></select>
                </label>
                <label>
                    Identificador
                    <input id="asoOrderKey" class="inp adm-season-order-input" type="text" maxlength="80">
                </label>
                <label>
                    Titulo visible
                    <input id="asoOrderLabel" class="inp adm-season-order-input" type="text" maxlength="150">
                </label>
            </div>
            <div class="adm-season-order-actions">
                <button id="asoSaveOrderMeta" class="btn btn-green" type="button">Guardar cabecera</button>
                <button id="asoNewNode" class="btn" type="button">Nuevo nodo</button>
            </div>
            <p class="adm-season-order-help">Cada orden vive dentro del mismo bridge. El primer nodo de un orden nuevo puede crearse directamente desde “Nuevo nodo”.</p>
        </section>

        <section class="adm-season-order-box">
            <h3>Nodos</h3>
            <div class="adm-season-order-actions">
                <label class="adm-season-order-muted">Filtro rapido
                    <input id="asoQuickFilter" class="inp adm-season-order-input" type="text" placeholder="Titulo, descripcion o temporada...">
                </label>
            </div>
            <div id="asoNodesWrap"></div>
        </section>
    </div>
<?php endif; ?>

<div class="modal-back" id="asoNodeModal">
    <div class="modal">
        <h3 id="asoNodeModalTitle">Nuevo nodo</h3>
        <form id="asoNodeForm" class="adm-m-0">
            <input type="hidden" id="asoNodeId" value="0">
            <div class="modal-body">
                <div class="adm-season-order-modal-grid">
                    <label>
                        Orden
                        <input id="asoFormOrderKey" class="inp adm-season-order-input" type="text" maxlength="80" required>
                    </label>
                    <label>
                        Titulo del orden
                        <input id="asoFormOrderLabel" class="inp adm-season-order-input" type="text" maxlength="150" required>
                    </label>
                    <label>
                        Posicion
                        <input id="asoFormPosition" class="inp adm-season-order-input" type="number" min="0" step="1">
                    </label>
                    <label>
                        Rama
                        <select id="asoFormBranchType" class="select adm-season-order-select">
                            <option value="main">Principal</option>
                            <option value="secondary">Secundaria</option>
                        </select>
                    </label>
                    <label>
                        Nodo padre
                        <select id="asoFormParentNodeId" class="select adm-season-order-select">
                            <option value="0">Sin padre</option>
                        </select>
                    </label>
                    <label>
                        Tipo
                        <select id="asoFormNodeKind" class="select adm-season-order-select">
                            <option value="season">Temporada completa</option>
                            <option value="season_range">Rango de temporada</option>
                            <option value="arc">Arco o bloque</option>
                            <option value="custom">Custom</option>
                        </select>
                    </label>
                    <label>
                        Temporada asociada
                        <select id="asoFormSeasonId" class="select adm-season-order-select">
                            <option value="0">Sin temporada vinculada</option>
                        </select>
                    </label>
                    <label>
                        Episodio inicial
                        <input id="asoFormEpisodeStart" class="inp adm-season-order-input" type="number" min="1" step="1">
                    </label>
                    <label>
                        Episodio final
                        <input id="asoFormEpisodeEnd" class="inp adm-season-order-input" type="number" min="1" step="1">
                    </label>
                    <label class="adm-season-order-modal-wide">
                        Titulo del nodo
                        <input id="asoFormLabel" class="inp adm-season-order-input" type="text" maxlength="190" required>
                    </label>
                    <label class="adm-season-order-modal-wide">
                        Descripcion breve
                        <textarea id="asoFormDescription" class="ta adm-season-order-textarea" rows="4" maxlength="1200"></textarea>
                    </label>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-green" type="submit">Guardar</button>
                <button class="btn" type="button" id="asoCloseModal">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
  var state = <?= json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  var endpoint = '/talim?s=admin_season_order&ajax=1';
  var orderSelect = document.getElementById('asoOrderSelect');
  var orderKeyInput = document.getElementById('asoOrderKey');
  var orderLabelInput = document.getElementById('asoOrderLabel');
  var nodesWrap = document.getElementById('asoNodesWrap');
  var quickFilter = document.getElementById('asoQuickFilter');
  var modal = document.getElementById('asoNodeModal');
  var closeModalBtn = document.getElementById('asoCloseModal');
  var nodeForm = document.getElementById('asoNodeForm');

  function esc(str){
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function humanizeKind(kind){
    if (kind === 'season_range') return 'Rango';
    if (kind === 'arc') return 'Arco';
    if (kind === 'custom') return 'Custom';
    return 'Temporada';
  }

  function updateState(next){
    state = next || state;
    renderOrders();
    renderNodes();
    fillSeasonOptions();
  }

  function selectedOrderMeta(){
    var selected = String(state.selected_order || '');
    var orders = Array.isArray(state.orders) ? state.orders : [];
    for (var i = 0; i < orders.length; i++) {
      if (String(orders[i].order_key) === selected) return orders[i];
    }
    return null;
  }

  function renderOrders(){
    if (!orderSelect) return;
    var orders = Array.isArray(state.orders) ? state.orders : [];
    if (!orders.length) {
      orderSelect.innerHTML = '<option value="">(sin ordenes todavia)</option>';
      orderSelect.value = '';
      orderKeyInput.value = '';
      orderLabelInput.value = '';
      return;
    }
    var html = '';
    orders.forEach(function(order){
      var label = String(order.order_label || order.order_key || '');
      var meta = ' (' + (parseInt(order.node_count || 0, 10) || 0) + ' nodos)';
      html += '<option value="' + esc(order.order_key) + '">' + esc(label + meta) + '</option>';
    });
    orderSelect.innerHTML = html;
    orderSelect.value = String(state.selected_order || orders[0].order_key || '');
    var meta = selectedOrderMeta();
    orderKeyInput.value = meta ? String(meta.order_key || '') : '';
    orderLabelInput.value = meta ? String(meta.order_label || '') : '';
  }

  function filteredNodes(){
    var q = quickFilter ? String(quickFilter.value || '').toLowerCase() : '';
    var nodes = Array.isArray(state.nodes) ? state.nodes.slice() : [];
    if (!q) return nodes;
    return nodes.filter(function(node){
      var hay = [
        node.label,
        node.description,
        node.season_name,
        node.node_kind,
        node.branch_type
      ].join(' ').toLowerCase();
      return hay.indexOf(q) !== -1;
    });
  }

  function renderNodes(){
    if (!nodesWrap) return;
    var nodes = filteredNodes();
    if (!nodes.length) {
      nodesWrap.innerHTML = '<div class="adm-season-order-empty">Todavia no hay nodos en este orden.</div>';
      return;
    }
    var html = '<table class="adm-season-order-table"><thead><tr><th>Pos</th><th>Nodo</th><th>Vinculo</th><th>Rama</th><th>Acciones</th></tr></thead><tbody>';
    nodes.forEach(function(node){
      var parentText = '';
      if (parseInt(node.parent_node_id || 0, 10) > 0) {
        var parent = (state.nodes || []).find(function(candidate){ return parseInt(candidate.id || 0, 10) === parseInt(node.parent_node_id || 0, 10); });
        if (parent) parentText = ' de ' + String(parent.label || parent.season_name || ('#' + parent.id));
      }
      var seasonLink = '';
      if (node.href) {
        seasonLink = '<a class="adm-link-white" href="' + esc(node.href) + '" target="_blank">' + esc(node.season_name || node.label || 'Temporada') + '</a>';
      } else {
        seasonLink = '<span class="adm-season-order-muted">Sin temporada vinculada</span>';
      }
      var rangeBits = [];
      if (node.episode_start) rangeBits.push('ep. ' + node.episode_start);
      if (node.episode_end && node.episode_end !== node.episode_start) rangeBits.push('a ' + node.episode_end);
      html += '<tr>';
      html += '<td><strong class="adm-color-accent">' + esc(node.position) + '</strong></td>';
      html += '<td><div class="adm-season-order-node-title">' + esc(node.label || '') + '</div>';
      html += '<div class="adm-season-order-muted">' + esc(humanizeKind(node.node_kind)) + (rangeBits.length ? ' · ' + rangeBits.join(' ') : '') + '</div>';
      if (node.description) html += '<div class="adm-season-order-node-desc">' + esc(node.description) + '</div>';
      html += '</td>';
      html += '<td>' + seasonLink + '</td>';
      html += '<td><span class="adm-season-order-badge' + (node.branch_type === 'secondary' ? ' adm-season-order-badge-secondary' : '') + '">' + esc(node.branch_type === 'secondary' ? 'Secundaria' : 'Principal') + parentText + '</span></td>';
      html += '<td><button class="btn btn-green aso-edit-node" data-id="' + esc(node.id) + '" type="button">Editar</button> <button class="btn btn-red aso-delete-node" data-id="' + esc(node.id) + '" type="button">Borrar</button></td>';
      html += '</tr>';
    });
    html += '</tbody></table>';
    nodesWrap.innerHTML = html;
    bindNodeButtons();
  }

  function fillSeasonOptions(){
    var select = document.getElementById('asoFormSeasonId');
    if (!select) return;
    var html = '<option value="0">Sin temporada vinculada</option>';
    (state.season_options || []).forEach(function(season){
      var kind = season.season_kind ? ' [' + season.season_kind + ']' : '';
      html += '<option value="' + esc(season.id) + '">' + esc((season.name || ('Temporada ' + season.id)) + kind) + '</option>';
    });
    select.innerHTML = html;
  }

  function fillParentOptions(currentId){
    var select = document.getElementById('asoFormParentNodeId');
    if (!select) return;
    var html = '<option value="0">Sin padre</option>';
    (state.nodes || []).forEach(function(node){
      if (String(node.branch_type || 'main') !== 'main') return;
      if (parseInt(node.id || 0, 10) === parseInt(currentId || 0, 10)) return;
      html += '<option value="' + esc(node.id) + '">' + esc((node.position || 0) + ' · ' + (node.label || node.season_name || ('Nodo ' + node.id))) + '</option>';
    });
    select.innerHTML = html;
  }

  function openModal(node){
    document.getElementById('asoNodeModalTitle').textContent = node ? 'Editar nodo' : 'Nuevo nodo';
    document.getElementById('asoNodeId').value = node ? String(node.id || 0) : '0';
    document.getElementById('asoFormOrderKey').value = node ? String(node.order_key || '') : String(state.selected_order || orderKeyInput.value || '');
    document.getElementById('asoFormOrderLabel').value = node ? String(node.order_label || '') : String(orderLabelInput.value || '');
    document.getElementById('asoFormPosition').value = node ? String(node.position || '') : '';
    document.getElementById('asoFormBranchType').value = node ? String(node.branch_type || 'main') : 'main';
    fillParentOptions(node ? node.id : 0);
    document.getElementById('asoFormParentNodeId').value = node ? String(node.parent_node_id || 0) : '0';
    document.getElementById('asoFormNodeKind').value = node ? String(node.node_kind || 'season') : 'season';
    document.getElementById('asoFormSeasonId').value = node ? String(node.season_id || 0) : '0';
    document.getElementById('asoFormEpisodeStart').value = node && node.episode_start ? String(node.episode_start) : '';
    document.getElementById('asoFormEpisodeEnd').value = node && node.episode_end ? String(node.episode_end) : '';
    document.getElementById('asoFormLabel').value = node ? String(node.label || '') : '';
    document.getElementById('asoFormDescription').value = node ? String(node.description || '') : '';
    modal.style.display = 'flex';
  }

  function closeModal(){
    if (modal) modal.style.display = 'none';
  }

  function bindNodeButtons(){
    document.querySelectorAll('.aso-edit-node').forEach(function(btn){
      btn.addEventListener('click', function(){
        var id = parseInt(btn.getAttribute('data-id') || '0', 10) || 0;
        var node = (state.nodes || []).find(function(item){ return parseInt(item.id || 0, 10) === id; });
        openModal(node || null);
      });
    });
    document.querySelectorAll('.aso-delete-node').forEach(function(btn){
      btn.addEventListener('click', function(){
        var id = parseInt(btn.getAttribute('data-id') || '0', 10) || 0;
        if (!id || !confirm('Borrar este nodo del orden?')) return;
        HGAdminHttp.postAction(endpoint, 'delete_node', {
          id: id,
          selected_order: state.selected_order || ''
        }, { loadingEl: btn }).then(function(resp){
          updateState(resp.data || {});
          HGAdminHttp.notify(resp.message || 'Nodo borrado', 'ok');
        }).catch(function(err){
          alert(HGAdminHttp.errorMessage(err));
        });
      });
    });
  }

  function refreshState(selectedOrder){
    var url = endpoint + '&action=state';
    if (selectedOrder) {
      url += '&order=' + encodeURIComponent(selectedOrder);
    }
    HGAdminHttp.request(url, { method: 'GET' }).then(function(resp){
      updateState(resp.data || {});
    }).catch(function(err){
      alert(HGAdminHttp.errorMessage(err));
    });
  }

  if (orderSelect) {
    orderSelect.addEventListener('change', function(){
      refreshState(orderSelect.value || '');
    });
  }

  if (quickFilter) {
    quickFilter.addEventListener('input', renderNodes);
  }

  var saveOrderMetaBtn = document.getElementById('asoSaveOrderMeta');
  if (saveOrderMetaBtn) {
    saveOrderMetaBtn.addEventListener('click', function(){
      if (!String(orderSelect.value || '').trim()) {
        alert('Selecciona primero un orden existente.');
        return;
      }
      HGAdminHttp.postAction(endpoint, 'save_order_meta', {
        original_order_key: orderSelect.value || '',
        order_key: orderKeyInput.value || '',
        order_label: orderLabelInput.value || '',
        selected_order: orderSelect.value || ''
      }, { loadingEl: saveOrderMetaBtn }).then(function(resp){
        updateState(resp.data || {});
        HGAdminHttp.notify(resp.message || 'Orden actualizado', 'ok');
      }).catch(function(err){
        alert(HGAdminHttp.errorMessage(err));
      });
    });
  }

  var newNodeBtn = document.getElementById('asoNewNode');
  if (newNodeBtn) {
    newNodeBtn.addEventListener('click', function(){
      openModal(null);
    });
  }

  if (nodeForm) {
    nodeForm.addEventListener('submit', function(ev){
      ev.preventDefault();
      HGAdminHttp.postAction(endpoint, 'save_node', {
        id: parseInt(document.getElementById('asoNodeId').value || '0', 10) || 0,
        order_key: document.getElementById('asoFormOrderKey').value || '',
        order_label: document.getElementById('asoFormOrderLabel').value || '',
        position: parseInt(document.getElementById('asoFormPosition').value || '0', 10) || 0,
        branch_type: document.getElementById('asoFormBranchType').value || 'main',
        parent_node_id: parseInt(document.getElementById('asoFormParentNodeId').value || '0', 10) || 0,
        node_kind: document.getElementById('asoFormNodeKind').value || 'season',
        season_id: parseInt(document.getElementById('asoFormSeasonId').value || '0', 10) || 0,
        episode_start: document.getElementById('asoFormEpisodeStart').value || '',
        episode_end: document.getElementById('asoFormEpisodeEnd').value || '',
        label: document.getElementById('asoFormLabel').value || '',
        description: document.getElementById('asoFormDescription').value || '',
        selected_order: state.selected_order || ''
      }, { loadingEl: nodeForm }).then(function(resp){
        updateState(resp.data || {});
        closeModal();
        HGAdminHttp.notify(resp.message || 'Nodo guardado', 'ok');
      }).catch(function(err){
        alert(HGAdminHttp.errorMessage(err));
      });
    });
  }

  if (closeModalBtn) {
    closeModalBtn.addEventListener('click', closeModal);
  }
  if (modal) {
    modal.addEventListener('click', function(ev){
      if (ev.target === modal) closeModal();
    });
  }
  document.addEventListener('keydown', function(ev){
    if (ev.key === 'Escape') closeModal();
  });

  fillSeasonOptions();
  renderOrders();
  renderNodes();
})();
</script>
<?php
admin_panel_close();
