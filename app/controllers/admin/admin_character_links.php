<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/pretty.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function acl_table_exists(mysqli $db, string $table): bool {
    $safe = mysqli_real_escape_string($db, preg_replace('/[^a-zA-Z0-9_]/', '', $table));
    if ($safe === '') return false;
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safe}' LIMIT 1";
    $rs = mysqli_query($db, $sql);
    return ($rs && mysqli_num_rows($rs) > 0);
}
function acl_column_exists(mysqli $db, string $table, string $column): bool {
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

$isAjaxRequest = (
    ((string)($_GET['ajax'] ?? '') === '1')
    || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
);

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_character_links';
$CSRF = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : '';

if (!function_exists('admin_character_links_csrf_ok')) {
    function admin_character_links_csrf_ok(): bool {
        $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
        $token = function_exists('hg_admin_extract_csrf_token')
            ? hg_admin_extract_csrf_token($payload)
            : (string)($_POST['csrf'] ?? '');
        if (function_exists('hg_admin_csrf_valid')) {
            return hg_admin_csrf_valid($token, 'csrf_admin_character_links');
        }
        return false;
    }
}

$hasCharacters = acl_table_exists($link, 'fact_characters');
$hasDocs = acl_table_exists($link, 'fact_docs');
$hasBridgeDocs = acl_table_exists($link, 'bridge_characters_docs');
$hasExternal = acl_table_exists($link, 'fact_external_links');
$hasBridgeExternal = acl_table_exists($link, 'bridge_characters_external_links');
$hasChronicles = acl_table_exists($link, 'dim_chronicles');
$hasRealities = acl_table_exists($link, 'dim_realities');
$hasCharacterReality = acl_column_exists($link, 'fact_characters', 'reality_id');
$hasRealityFilter = ($hasRealities && $hasCharacterReality);

$selectedCharacterId = (int)($_GET['character_id'] ?? $_POST['character_id'] ?? 0);
$selectedChronicleId = (int)($_GET['fil_cr'] ?? $_POST['fil_cr'] ?? 0);
$selectedRealityId = (int)($_GET['fil_re'] ?? $_POST['fil_re'] ?? 0);
$flash = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if (!admin_character_links_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF inválido. Recarga la página.'];
    } elseif ($selectedCharacterId <= 0) {
        $flash[] = ['type' => 'error', 'msg' => 'Selecciona un personaje válido.'];
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'link_doc') {
            if (!$hasBridgeDocs || !$hasDocs) {
                $flash[] = ['type' => 'error', 'msg' => 'Falta la tabla de bridge o documentos.'];
            } else {
                $docId = (int)($_POST['doc_id'] ?? 0);
                $relationLabel = trim((string)($_POST['relation_label'] ?? ''));
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                if ($docId <= 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'Selecciona un documento válido.'];
                } else {
                    $sql = "INSERT INTO bridge_characters_docs (character_id, doc_id, relation_label, sort_order)
                            VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE relation_label=VALUES(relation_label), sort_order=VALUES(sort_order), updated_at=NOW()";
                    if ($st = $link->prepare($sql)) {
                        $st->bind_param('iisi', $selectedCharacterId, $docId, $relationLabel, $sortOrder);
                        if ($st->execute()) $flash[] = ['type' => 'ok', 'msg' => 'Documento vinculado al personaje.'];
                        else $flash[] = ['type' => 'error', 'msg' => 'Error al vincular documento: '.$st->error];
                        $st->close();
                    } else {
                        $flash[] = ['type' => 'error', 'msg' => 'Error al preparar vinculo de documento.'];
                    }
                }
            }
        } elseif ($action === 'update_doc_link') {
            $docId = (int)($_POST['doc_id'] ?? 0);
            $relationLabel = trim((string)($_POST['relation_label'] ?? ''));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            if ($docId <= 0 || !$hasBridgeDocs) {
                $flash[] = ['type' => 'error', 'msg' => 'Fila de documento invalida.'];
            } else {
                if ($st = $link->prepare("UPDATE bridge_characters_docs SET relation_label=?, sort_order=?, updated_at=NOW() WHERE character_id=? AND doc_id=?")) {
                    $st->bind_param('siii', $relationLabel, $sortOrder, $selectedCharacterId, $docId);
                    if ($st->execute()) $flash[] = ['type' => 'ok', 'msg' => 'Vinculo de documento actualizado.'];
                    else $flash[] = ['type' => 'error', 'msg' => 'Error al actualizar vinculo de documento: '.$st->error];
                    $st->close();
                } else {
                    $flash[] = ['type' => 'error', 'msg' => 'Error al preparar actualizacion de documento: '.$link->error];
                }
            }
        } elseif ($action === 'unlink_doc') {
            $docId = (int)($_POST['doc_id'] ?? 0);
            if ($docId <= 0 || !$hasBridgeDocs) {
                $flash[] = ['type' => 'error', 'msg' => 'Fila de documento invalida.'];
            } else {
                if ($st = $link->prepare("DELETE FROM bridge_characters_docs WHERE character_id=? AND doc_id=?")) {
                    $st->bind_param('ii', $selectedCharacterId, $docId);
                    if ($st->execute()) $flash[] = ['type' => 'ok', 'msg' => 'Vinculo de documento eliminado.'];
                    else $flash[] = ['type' => 'error', 'msg' => 'Error al eliminar vinculo de documento: '.$st->error];
                    $st->close();
                } else {
                    $flash[] = ['type' => 'error', 'msg' => 'Error al preparar borrado de documento: '.$link->error];
                }
            }
        } elseif ($action === 'link_external') {
            if (!$hasBridgeExternal || !$hasExternal) {
                $flash[] = ['type' => 'error', 'msg' => 'Falta la tabla de bridge o enlaces externos.'];
            } else {
                $externalId = (int)($_POST['external_link_id'] ?? 0);
                $relationLabel = trim((string)($_POST['relation_label'] ?? ''));
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                if ($externalId <= 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'Selecciona un enlace externo válido.'];
                } else {
                    $sql = "INSERT INTO bridge_characters_external_links (character_id, external_link_id, relation_label, sort_order)
                            VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE relation_label=VALUES(relation_label), sort_order=VALUES(sort_order), updated_at=NOW()";
                    if ($st = $link->prepare($sql)) {
                        $st->bind_param('iisi', $selectedCharacterId, $externalId, $relationLabel, $sortOrder);
                        if ($st->execute()) $flash[] = ['type' => 'ok', 'msg' => 'Enlace externo vinculado al personaje.'];
                        else $flash[] = ['type' => 'error', 'msg' => 'Error al vincular enlace externo: '.$st->error];
                        $st->close();
                    } else {
                        $flash[] = ['type' => 'error', 'msg' => 'Error al preparar vinculo de enlace externo.'];
                    }
                }
            }
        } elseif ($action === 'update_external_link') {
            $externalId = (int)($_POST['external_link_id'] ?? 0);
            $relationLabel = trim((string)($_POST['relation_label'] ?? ''));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            if ($externalId <= 0 || !$hasBridgeExternal) {
                $flash[] = ['type' => 'error', 'msg' => 'Fila de enlace externo invalida.'];
            } else {
                if ($st = $link->prepare("UPDATE bridge_characters_external_links SET relation_label=?, sort_order=?, updated_at=NOW() WHERE character_id=? AND external_link_id=?")) {
                    $st->bind_param('siii', $relationLabel, $sortOrder, $selectedCharacterId, $externalId);
                    if ($st->execute()) $flash[] = ['type' => 'ok', 'msg' => 'Vinculo de enlace externo actualizado.'];
                    else $flash[] = ['type' => 'error', 'msg' => 'Error al actualizar vinculo externo: '.$st->error];
                    $st->close();
                } else {
                    $flash[] = ['type' => 'error', 'msg' => 'Error al preparar actualizacion de enlace externo: '.$link->error];
                }
            }
        } elseif ($action === 'unlink_external') {
            $externalId = (int)($_POST['external_link_id'] ?? 0);
            if ($externalId <= 0 || !$hasBridgeExternal) {
                $flash[] = ['type' => 'error', 'msg' => 'Fila de enlace externo invalida.'];
            } else {
                if ($st = $link->prepare("DELETE FROM bridge_characters_external_links WHERE character_id=? AND external_link_id=?")) {
                    $st->bind_param('ii', $selectedCharacterId, $externalId);
                    if ($st->execute()) $flash[] = ['type' => 'ok', 'msg' => 'Vinculo de enlace externo eliminado.'];
                    else $flash[] = ['type' => 'error', 'msg' => 'Error al eliminar vinculo externo: '.$st->error];
                    $st->close();
                } else {
                    $flash[] = ['type' => 'error', 'msg' => 'Error al preparar borrado de enlace externo: '.$link->error];
                }
            }
        }
    }
}

if ($isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $errors = [];
    $messages = [];
    foreach ($flash as $m) {
        $msg = (string)($m['msg'] ?? '');
        if ($msg === '') continue;
        if ((string)($m['type'] ?? '') === 'error') $errors[] = $msg;
        else $messages[] = $msg;
    }
    if (!empty($errors)) {
        if (function_exists('hg_admin_json_error')) {
            hg_admin_json_error($errors[0], 400, ['flash' => $errors], ['messages' => $messages]);
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => $errors[0], 'errors' => $errors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $okMsg = !empty($messages) ? $messages[count($messages)-1] : 'Guardado';
    if (function_exists('hg_admin_json_success')) {
        hg_admin_json_success(['messages' => $messages], $okMsg);
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'message' => $okMsg, 'data' => ['messages' => $messages]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$characters = [];
if ($hasCharacters) {
    $chrNameSelect = $hasChronicles ? "COALESCE(ch.name, '') AS chronicle_name" : "'' AS chronicle_name";
    $realitySelect = $hasRealityFilter ? ", c.reality_id, COALESCE(r.name, '') AS reality_name" : ", 0 AS reality_id, '' AS reality_name";
    $sqlChars = "SELECT c.id, c.name, c.alias, c.chronicle_id, {$chrNameSelect}{$realitySelect}
                 FROM fact_characters c";
    if ($hasChronicles) $sqlChars .= " LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id";
    if ($hasRealityFilter) $sqlChars .= " LEFT JOIN dim_realities r ON r.id = c.reality_id";
    $sqlChars .= " WHERE 1=1";

    $bindChronicle = false;
    $bindReality = false;
    if ($selectedChronicleId > 0) {
        $sqlChars .= " AND c.chronicle_id = ?";
        $bindChronicle = true;
    }
    if ($selectedRealityId > 0 && $hasRealityFilter) {
        $sqlChars .= " AND c.reality_id = ?";
        $bindReality = true;
    }
    $sqlChars .= " ORDER BY c.name ASC";

    if ($st = $link->prepare($sqlChars)) {
        if ($bindChronicle && $bindReality) {
            $st->bind_param('ii', $selectedChronicleId, $selectedRealityId);
        } elseif ($bindChronicle) {
            $st->bind_param('i', $selectedChronicleId);
        } elseif ($bindReality) {
            $st->bind_param('i', $selectedRealityId);
        }
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) { $characters[] = $row; }
        $st->close();
    }
}

$chronicles = [];
if ($hasChronicles) {
    if ($rs = $link->query("SELECT id, name FROM dim_chronicles ORDER BY name ASC")) {
        while ($row = $rs->fetch_assoc()) { $chronicles[] = $row; }
        $rs->close();
    }
}

$realities = [];
if ($hasRealityFilter) {
    if ($rs = $link->query("SELECT id, name FROM dim_realities ORDER BY name ASC")) {
        while ($row = $rs->fetch_assoc()) { $realities[] = $row; }
        $rs->close();
    }
}

$docs = [];
if ($hasDocs) {
    $sqlDocs = "SELECT d.id, d.title, d.pretty_id, COALESCE(c.kind, '') AS category_name
                FROM fact_docs d
                LEFT JOIN dim_doc_categories c ON c.id = d.section_id
                ORDER BY c.sort_order ASC, d.title ASC";
    if ($rs = $link->query($sqlDocs)) {
        while ($row = $rs->fetch_assoc()) { $docs[] = $row; }
        $rs->close();
    }
}

$externalLinks = [];
if ($hasExternal) {
    if ($rs = $link->query("SELECT id, title, url, kind, is_active FROM fact_external_links ORDER BY is_active DESC, title ASC")) {
        while ($row = $rs->fetch_assoc()) { $externalLinks[] = $row; }
        $rs->close();
    }
}

$linkedDocs = [];
if ($selectedCharacterId > 0 && $hasBridgeDocs && $hasDocs) {
    $sql = "SELECT b.doc_id, COALESCE(b.relation_label, '') AS relation_label, COALESCE(b.sort_order, 0) AS sort_order,
                   d.title, d.pretty_id, COALESCE(c.kind, '') AS category_name
            FROM bridge_characters_docs b
            INNER JOIN fact_docs d ON d.id = b.doc_id
            LEFT JOIN dim_doc_categories c ON c.id = d.section_id
            WHERE b.character_id = ?
            ORDER BY b.sort_order ASC, d.title ASC";
    if ($st = $link->prepare($sql)) {
        $st->bind_param('i', $selectedCharacterId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) { $linkedDocs[] = $row; }
        $st->close();
    }
}

$linkedExternal = [];
if ($selectedCharacterId > 0 && $hasBridgeExternal && $hasExternal) {
    $sql = "SELECT b.external_link_id, COALESCE(b.relation_label, '') AS relation_label, COALESCE(b.sort_order, 0) AS sort_order,
                   l.title, l.url, l.kind, l.is_active
            FROM bridge_characters_external_links b
            INNER JOIN fact_external_links l ON l.id = b.external_link_id
            WHERE b.character_id = ?
            ORDER BY b.sort_order ASC, l.title ASC";
    if ($st = $link->prepare($sql)) {
        $st->bind_param('i', $selectedCharacterId);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) { $linkedExternal[] = $row; }
        $st->close();
    }
}

$characterName = '';
$characterChronicle = '';
$characterReality = '';
if ($selectedCharacterId > 0 && $hasCharacters) {
    $chrNameSelect = $hasChronicles ? "COALESCE(ch.name, '') AS chronicle_name" : "'' AS chronicle_name";
    $realitySelect = $hasRealityFilter ? ", COALESCE(r.name, '') AS reality_name" : ", '' AS reality_name";
    $sqlCurrentCharacter = "SELECT c.name, {$chrNameSelect}{$realitySelect}
                            FROM fact_characters c";
    if ($hasChronicles) $sqlCurrentCharacter .= " LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id";
    if ($hasRealityFilter) $sqlCurrentCharacter .= " LEFT JOIN dim_realities r ON r.id = c.reality_id";
    $sqlCurrentCharacter .= " WHERE c.id=? LIMIT 1";
    if ($st = $link->prepare($sqlCurrentCharacter)) {
        $st->bind_param('i', $selectedCharacterId);
        $st->execute();
        if ($rs = $st->get_result()) {
            if ($row = $rs->fetch_assoc()) {
                $characterName = (string)($row['name'] ?? '');
                $characterChronicle = (string)($row['chronicle_name'] ?? '');
                $characterReality = (string)($row['reality_name'] ?? '');
            }
        }
        $st->close();
    }
}

$actions = "<span class='adm-flex-right-8'>"
    . "<a class='btn' href='/talim?s=admin_external_links'>Gestionar enlaces externos</a>"
    . "<a class='btn' href='/talim?s=admin_doc_links'>Vincular desde Documento</a>"
    . "</span>";
admin_panel_open('Vinculos de Personajes (Docs y Enlaces)', $actions);
echo "<style>.panel-wrap, .panel-wrap * { text-align: left !important; }</style>";
$moduleUrl = '/talim?s=admin_character_links';
$moduleAjaxUrl = '/talim?ajax=1&s=admin_character_links';
?>
<div id="acl-container">
<div id="acl-root">

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = $m['type']==='ok' ? 'ok' : 'err'; ?>
        <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$hasCharacters): ?>
    <div class="flash"><div class="err">Falta la tabla <code>fact_characters</code>.</div></div>
    </div>
    </div>
    <?php admin_panel_close(); return; ?>
<?php endif; ?>

<?php if (!$hasDocs || !$hasBridgeDocs || !$hasExternal || !$hasBridgeExternal): ?>
    <div class="flash">
        <div class="err">Faltan tablas necesarias para todos los vínculos. Ejecuta: <code>app/tools/setup_character_documentation_links_20260322.php</code></div>
    </div>
<?php endif; ?>

<fieldset id="renglonArchivos">
    <legend>Seleccionar personaje</legend>
    <form method="GET" class="adm-inline-filters" data-acl-filter="1">
        <input type="hidden" name="s" value="admin_character_links">
        <?php if ($hasChronicles): ?>
            <select class="select" name="fil_cr">
                <option value="0">Crónica: Todas</option>
                <?php foreach ($chronicles as $ch): ?>
                    <?php $chid = (int)($ch['id'] ?? 0); ?>
                    <option value="<?= $chid ?>" <?= ($chid === $selectedChronicleId ? 'selected' : '') ?>>
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
        <select class="select" name="character_id" required>
            <option value="">-- Selecciona personaje --</option>
            <?php foreach ($characters as $c): ?>
                <?php $cid = (int)($c['id'] ?? 0); ?>
                <option value="<?= $cid ?>" <?= ($cid === $selectedCharacterId ? 'selected' : '') ?>>
                    <?= h((string)$c['name']) ?><?= trim((string)($c['alias'] ?? '')) !== '' ? ' ('.h((string)$c['alias']).')' : '' ?><?= trim((string)($c['chronicle_name'] ?? '')) !== '' ? ' [Cr: '.h((string)$c['chronicle_name']).']' : '' ?><?= ($hasRealityFilter && trim((string)($c['reality_name'] ?? '')) !== '') ? ' [Real: '.h((string)$c['reality_name']).']' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Cargar</button>
        <a class="btn" href="/talim?s=admin_character_links">Limpiar</a>
    </form>
    <?php if ($selectedCharacterId > 0): ?>
        <p>Personaje actual:
            <strong><?= h($characterName !== '' ? $characterName : ('#'.$selectedCharacterId)) ?></strong>
            <?php if ($characterChronicle !== ''): ?>
                <span>| Crónica: <?= h($characterChronicle) ?></span>
            <?php endif; ?>
            <?php if ($characterReality !== ''): ?>
                <span>| Realidad: <?= h($characterReality) ?></span>
            <?php endif; ?>
            <?php if ($selectedCharacterId > 0): ?>
                <a href="<?= h(pretty_url($link, 'fact_characters', '/characters', $selectedCharacterId)) ?>" target="_blank" rel="noopener noreferrer">Ver ficha</a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</fieldset>

<?php if ($selectedCharacterId > 0): ?>

<fieldset id="renglonArchivos">
    <legend>Vincular documento interno</legend>
    <?php if (!$hasDocs || !$hasBridgeDocs): ?>
        <p>No disponible: faltan <code>fact_docs</code> o <code>bridge_characters_docs</code>.</p>
    <?php else: ?>
        <form method="POST" class="adm-grid-2">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="action" value="link_doc">
            <input type="hidden" name="character_id" value="<?= (int)$selectedCharacterId ?>">
            <input type="hidden" name="fil_cr" value="<?= (int)$selectedChronicleId ?>">
            <input type="hidden" name="fil_re" value="<?= (int)$selectedRealityId ?>">
            <label class="adm-col-span-2">Documento
                <select class="select" name="doc_id" required>
                    <option value="">-- Selecciona documento --</option>
                    <?php foreach ($docs as $d): ?>
                        <option value="<?= (int)$d['id'] ?>">
                            [<?= h((string)($d['category_name'] ?? '-')) ?>] <?= h((string)$d['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Etiqueta relacion
                <input class="inp" type="text" name="relation_label" maxlength="120" placeholder="referencia, protagonista...">
            </label>
            <label>Orden
                <input class="inp" type="number" name="sort_order" value="0">
            </label>
            <div class="adm-flex-right-8 adm-col-span-2">
                <button class="btn btn-blue" type="submit">Vincular documento</button>
            </div>
        </form>
    <?php endif; ?>

    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Etiqueta</th>
                    <th>Orden</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($linkedDocs)): ?>
                    <tr><td colspan="4">No hay documentos vinculados.</td></tr>
                <?php else: ?>
                    <?php foreach ($linkedDocs as $row): ?>
                        <?php $docId = (int)($row['doc_id'] ?? 0); ?>
                        <tr>
                            <td>
                                <a href="<?= h(pretty_url($link, 'fact_docs', '/documents', $docId)) ?>" target="_blank" rel="noopener noreferrer">
                                    [<?= h((string)($row['category_name'] ?? '-')) ?>] <?= h((string)$row['title']) ?>
                                </a>
                            </td>
                            <td>
                                <form method="POST" class="adm-inline-filters">
                                    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                                    <input type="hidden" name="action" value="update_doc_link">
                                    <input type="hidden" name="character_id" value="<?= (int)$selectedCharacterId ?>">
                                    <input type="hidden" name="fil_cr" value="<?= (int)$selectedChronicleId ?>">
                                    <input type="hidden" name="fil_re" value="<?= (int)$selectedRealityId ?>">
                                    <input type="hidden" name="doc_id" value="<?= (int)$row['doc_id'] ?>">
                                    <input class="inp" type="text" name="relation_label" maxlength="120" value="<?= h((string)$row['relation_label']) ?>">
                            </td>
                            <td>
                                    <input class="inp" type="number" name="sort_order" value="<?= (int)($row['sort_order'] ?? 0) ?>">
                            </td>
                            <td class="adm-actions">
                                    <button class="btn btn-small" type="submit">Guardar</button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Eliminar vinculo de documento?');">
                                    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                                    <input type="hidden" name="action" value="unlink_doc">
                                    <input type="hidden" name="character_id" value="<?= (int)$selectedCharacterId ?>">
                                    <input type="hidden" name="fil_cr" value="<?= (int)$selectedChronicleId ?>">
                                    <input type="hidden" name="fil_re" value="<?= (int)$selectedRealityId ?>">
                                    <input type="hidden" name="doc_id" value="<?= (int)$row['doc_id'] ?>">
                                    <button class="btn btn-red btn-small" type="submit">Quitar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</fieldset>

<fieldset id="renglonArchivos">
    <legend>Vincular enlace externo</legend>
    <?php if (!$hasExternal || !$hasBridgeExternal): ?>
        <p>No disponible: faltan <code>fact_external_links</code> o <code>bridge_characters_external_links</code>.</p>
    <?php else: ?>
        <form method="POST" class="adm-grid-2">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="action" value="link_external">
            <input type="hidden" name="character_id" value="<?= (int)$selectedCharacterId ?>">
            <input type="hidden" name="fil_cr" value="<?= (int)$selectedChronicleId ?>">
            <input type="hidden" name="fil_re" value="<?= (int)$selectedRealityId ?>">
            <label class="adm-col-span-2">Enlace externo
                <select class="select" name="external_link_id" required>
                    <option value="">-- Selecciona enlace externo --</option>
                    <?php foreach ($externalLinks as $e): ?>
                        <option value="<?= (int)$e['id'] ?>">
                            [<?= ((int)$e['is_active'] === 1 ? 'Activo' : 'Inactivo') ?>] <?= h((string)$e['title']) ?><?= trim((string)($e['kind'] ?? '')) !== '' ? ' ('.h((string)$e['kind']).')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Etiqueta relacion
                <input class="inp" type="text" name="relation_label" maxlength="120" placeholder="blog personal, entrevista...">
            </label>
            <label>Orden
                <input class="inp" type="number" name="sort_order" value="0">
            </label>
            <div class="adm-flex-right-8 adm-col-span-2">
                <button class="btn btn-blue" type="submit">Vincular enlace externo</button>
            </div>
        </form>
    <?php endif; ?>

    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Enlace</th>
                    <th>Etiqueta</th>
                    <th>Orden</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($linkedExternal)): ?>
                    <tr><td colspan="4">No hay enlaces externos vinculados.</td></tr>
                <?php else: ?>
                    <?php foreach ($linkedExternal as $row): ?>
                        <tr>
                            <td>
                                <a href="<?= h((string)$row['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string)$row['title']) ?></a>
                                <?php if (trim((string)($row['kind'] ?? '')) !== ''): ?>
                                    <br><small><?= h((string)$row['kind']) ?></small>
                                <?php endif; ?>
                                <?php if ((int)($row['is_active'] ?? 0) !== 1): ?>
                                    <br><small>(inactivo)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="adm-inline-filters">
                                    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                                    <input type="hidden" name="action" value="update_external_link">
                                    <input type="hidden" name="character_id" value="<?= (int)$selectedCharacterId ?>">
                                    <input type="hidden" name="fil_cr" value="<?= (int)$selectedChronicleId ?>">
                                    <input type="hidden" name="fil_re" value="<?= (int)$selectedRealityId ?>">
                                    <input type="hidden" name="external_link_id" value="<?= (int)$row['external_link_id'] ?>">
                                    <input class="inp" type="text" name="relation_label" maxlength="120" value="<?= h((string)$row['relation_label']) ?>">
                            </td>
                            <td>
                                    <input class="inp" type="number" name="sort_order" value="<?= (int)($row['sort_order'] ?? 0) ?>">
                            </td>
                            <td class="adm-actions">
                                    <button class="btn btn-small" type="submit">Guardar</button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Eliminar vinculo de enlace externo?');">
                                    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                                    <input type="hidden" name="action" value="unlink_external">
                                    <input type="hidden" name="character_id" value="<?= (int)$selectedCharacterId ?>">
                                    <input type="hidden" name="fil_cr" value="<?= (int)$selectedChronicleId ?>">
                                    <input type="hidden" name="fil_re" value="<?= (int)$selectedRealityId ?>">
                                    <input type="hidden" name="external_link_id" value="<?= (int)$row['external_link_id'] ?>">
                                    <button class="btn btn-red btn-small" type="submit">Quitar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</fieldset>

<?php endif; ?>

</div>
</div>

<script src="/assets/js/admin/admin-http.js"></script>
<script>
(function(){
    var container = document.getElementById('acl-container');
    if (!container) return;
    window.ADMIN_CSRF_TOKEN = <?= json_encode((string)$CSRF, JSON_UNESCAPED_UNICODE) ?>;
    var moduleUrl = <?= json_encode($moduleUrl, JSON_UNESCAPED_UNICODE) ?>;
    var ajaxUrl = <?= json_encode($moduleAjaxUrl, JSON_UNESCAPED_UNICODE) ?>;

    function buildFilterQuery(){
        var form = container.querySelector('form[data-acl-filter]');
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

    async function reloadModule(updateUrl){
        var query = buildFilterQuery();
        var url = moduleUrl + (query ? ('&' + query) : '');
        var res = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        var html = await res.text();
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var nextRoot = doc.querySelector('#acl-root');
        if (nextRoot) {
            container.innerHTML = nextRoot.outerHTML;
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
})();
</script>

<?php admin_panel_close(); ?>

