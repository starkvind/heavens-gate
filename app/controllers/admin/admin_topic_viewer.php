<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
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

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function topic_viewer_table_exists(mysqli $link): bool
{
    $rs = $link->query("SHOW TABLES LIKE 'fact_tools_topic_viewer'");
    return $rs && $rs->num_rows > 0;
}

function topic_viewer_column_exists(mysqli $link, string $table, string $column): bool
{
    $st = $link->prepare("SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1");
    if (!$st) {
        return false;
    }
    $st->bind_param("ss", $table, $column);
    $st->execute();
    $rs = $st->get_result();
    $ok = ($rs && $rs->num_rows > 0);
    $st->close();
    return $ok;
}

if (!topic_viewer_table_exists($link)) {
    echo "<div class='panel-wrap'><div class='hdr'><h2>Temas de visor de foro</h2><a class='btn' href='/talim'>&larr; Panel</a></div>";
    echo "<p class='adm-admin-error'>Falta la tabla <code>fact_tools_topic_viewer</code> en esta base de datos.</p></div>";
    return;
}

$hasChapterIdCol = topic_viewer_column_exists($link, 'fact_tools_topic_viewer', 'chapter_id');
$hasScopeTypeCol = topic_viewer_column_exists($link, 'fact_tools_topic_viewer', 'link_scope_type');
$hasScopeIdCol = topic_viewer_column_exists($link, 'fact_tools_topic_viewer', 'link_scope_id');
$supportsEpisodeAndScope = $hasChapterIdCol && $hasScopeTypeCol && $hasScopeIdCol;

$csrfKey = 'csrf_admin_topic_viewer';
$csrf = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($csrfKey)
    : (empty($_SESSION[$csrfKey]) ? ($_SESSION[$csrfKey] = bin2hex(random_bytes(16))) : $_SESSION[$csrfKey]);

$flash = [];
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    $validCsrf = function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid((string)$token, $csrfKey)
        : (is_string($token) && $token !== '' && isset($_SESSION[$csrfKey]) && hash_equals($_SESSION[$csrfKey], $token));

    if (!$validCsrf) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF inválido. Recarga la página.'];
    } else {
        $action = (string)$_POST['crud_action'];

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'ID inválido para borrar.'];
            } else {
                $st = $link->prepare("DELETE FROM fact_tools_topic_viewer WHERE id = ? LIMIT 1");
                if (!$st) {
                    $flash[] = ['type' => 'error', 'msg' => 'Error al preparar DELETE: ' . $link->error];
                } else {
                    $st->bind_param("i", $id);
                    if ($st->execute()) {
                        $flash[] = ['type' => 'ok', 'msg' => 'Tema eliminado.'];
                    } else {
                        $flash[] = ['type' => 'error', 'msg' => 'Error al borrar: ' . $st->error];
                    }
                    $st->close();
                }
            }
        }

        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $topicName = trim((string)($_POST['topic_name'] ?? ''));
            $topicId = (int)($_POST['topic_id'] ?? 0);
            $topicUrl = trim((string)($_POST['topic_url'] ?? ''));
            $topicDescription = trim((string)($_POST['topic_description'] ?? ''));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $isActive = ((string)($_POST['is_active'] ?? '1') === '1') ? 1 : 0;
            $chapterId = (int)($_POST['chapter_id'] ?? 0);
            $scopeType = trim((string)($_POST['link_scope_type'] ?? ''));
            $scopeId = (int)($_POST['link_scope_id'] ?? 0);
            $allowedScopeTypes = ['', 'character', 'group', 'organization'];

            if ($topicName === '' || $topicId <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'Nombre y topic_id son obligatorios.'];
                $editId = $id;
            } elseif ($hasScopeTypeCol && !in_array($scopeType, $allowedScopeTypes, true)) {
                $flash[] = ['type' => 'error', 'msg' => 'Tipo de agrupación inválido.'];
                $editId = $id;
            } elseif ($hasScopeTypeCol && $hasScopeIdCol && $scopeType !== '' && $scopeId <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'Si eliges tipo de agrupación, link_scope_id debe ser > 0.'];
                $editId = $id;
            } else {
                $chapterIdOrNull = ($hasChapterIdCol && $chapterId > 0) ? $chapterId : null;

                if ($id > 0) {
                    if ($supportsEpisodeAndScope) {
                        $st = $link->prepare("UPDATE fact_tools_topic_viewer
                            SET topic_name = ?, topic_id = ?, topic_url = ?, topic_description = ?, sort_order = ?, is_active = ?,
                                chapter_id = ?, link_scope_type = ?, link_scope_id = ?
                            WHERE id = ? LIMIT 1");
                    } else {
                        $st = $link->prepare("UPDATE fact_tools_topic_viewer
                            SET topic_name = ?, topic_id = ?, topic_url = ?, topic_description = ?, sort_order = ?, is_active = ?
                            WHERE id = ? LIMIT 1");
                    }
                    if (!$st) {
                        $flash[] = ['type' => 'error', 'msg' => 'Error al preparar UPDATE: ' . $link->error];
                    } else {
                        if ($supportsEpisodeAndScope) {
                            $scopeTypeOrNull = ($scopeType !== '') ? $scopeType : null;
                            $scopeIdOrNull = ($scopeType !== '' && $scopeId > 0) ? $scopeId : null;
                            $st->bind_param(
                                "sissiiisii",
                                $topicName,
                                $topicId,
                                $topicUrl,
                                $topicDescription,
                                $sortOrder,
                                $isActive,
                                $chapterIdOrNull,
                                $scopeTypeOrNull,
                                $scopeIdOrNull,
                                $id
                            );
                        } else {
                            $st->bind_param("sissiii", $topicName, $topicId, $topicUrl, $topicDescription, $sortOrder, $isActive, $id);
                        }
                        if ($st->execute()) {
                            $flash[] = ['type' => 'ok', 'msg' => 'Tema actualizado.'];
                            $editId = 0;
                        } else {
                            $code = (int)$st->errno;
                            $msg = ($code === 1062) ? 'Ya existe un tema con ese topic_id.' : ('Error al actualizar: ' . $st->error);
                            $flash[] = ['type' => 'error', 'msg' => $msg];
                            $editId = $id;
                        }
                        $st->close();
                    }
                } else {
                    if ($supportsEpisodeAndScope) {
                        $st = $link->prepare("INSERT INTO fact_tools_topic_viewer
                            (topic_name, topic_id, topic_url, topic_description, sort_order, is_active, chapter_id, link_scope_type, link_scope_id, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    } else {
                        $st = $link->prepare("INSERT INTO fact_tools_topic_viewer
                            (topic_name, topic_id, topic_url, topic_description, sort_order, is_active, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    }
                    if (!$st) {
                        $flash[] = ['type' => 'error', 'msg' => 'Error al preparar INSERT: ' . $link->error];
                    } else {
                        if ($supportsEpisodeAndScope) {
                            $scopeTypeOrNull = ($scopeType !== '') ? $scopeType : null;
                            $scopeIdOrNull = ($scopeType !== '' && $scopeId > 0) ? $scopeId : null;
                            $st->bind_param(
                                "sissiiisi",
                                $topicName,
                                $topicId,
                                $topicUrl,
                                $topicDescription,
                                $sortOrder,
                                $isActive,
                                $chapterIdOrNull,
                                $scopeTypeOrNull,
                                $scopeIdOrNull
                            );
                        } else {
                            $st->bind_param("sissii", $topicName, $topicId, $topicUrl, $topicDescription, $sortOrder, $isActive);
                        }
                        if ($st->execute()) {
                            $flash[] = ['type' => 'ok', 'msg' => 'Tema creado.'];
                        } else {
                            $code = (int)$st->errno;
                            $msg = ($code === 1062) ? 'Ya existe un tema con ese topic_id.' : ('Error al crear: ' . $st->error);
                            $flash[] = ['type' => 'error', 'msg' => $msg];
                            $editId = 0;
                        }
                        $st->close();
                    }
                }
            }
        }
    }
}

$editRow = [
    'id' => 0,
    'topic_name' => '',
    'topic_id' => '',
    'topic_url' => '',
    'topic_description' => '',
    'sort_order' => 0,
    'is_active' => 1,
    'chapter_id' => 0,
    'link_scope_type' => '',
    'link_scope_id' => 0,
];
if ($editId > 0) {
    $st = $link->prepare("SELECT * FROM fact_tools_topic_viewer WHERE id = ? LIMIT 1");
    if ($st) {
        $st->bind_param("i", $editId);
        $st->execute();
        $rs = $st->get_result();
        if ($row = $rs->fetch_assoc()) {
            $editRow = $row;
        }
        $st->close();
    }
}

$hasFlashError = false;
foreach ($flash as $flashItem) {
    if (($flashItem['type'] ?? '') === 'error') {
        $hasFlashError = true;
        break;
    }
}
if (
    $hasFlashError
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string)($_POST['crud_action'] ?? '') === 'save'
) {
    $editRow = array_merge($editRow, [
        'id' => (int)($_POST['id'] ?? 0),
        'topic_name' => (string)($_POST['topic_name'] ?? ''),
        'topic_id' => (string)($_POST['topic_id'] ?? ''),
        'topic_url' => (string)($_POST['topic_url'] ?? ''),
        'topic_description' => (string)($_POST['topic_description'] ?? ''),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'is_active' => ((string)($_POST['is_active'] ?? '1') === '1') ? 1 : 0,
        'chapter_id' => (int)($_POST['chapter_id'] ?? 0),
        'link_scope_type' => (string)($_POST['link_scope_type'] ?? ''),
        'link_scope_id' => (int)($_POST['link_scope_id'] ?? 0),
    ]);
}

$chapterOptions = [];
if ($hasChapterIdCol) {
    $rsChapters = $link->query("SELECT dc.id, dc.name, dc.chapter_number, ds.name AS season_name, ds.season_number
        FROM dim_chapters dc
        LEFT JOIN dim_seasons ds ON ds.id = dc.season_id
        ORDER BY
            COALESCE(ds.season_number, 9999) ASC,
            dc.chapter_number ASC,
            dc.id ASC");
    if ($rsChapters) {
        while ($c = $rsChapters->fetch_assoc()) {
            $chapterOptions[] = $c;
        }
        $rsChapters->close();
    }
}

$rows = [];
$sqlRows = "SELECT
                ftv.id,
                ftv.topic_name,
                ftv.topic_id,
                ftv.topic_url,
                ftv.topic_description,
                ftv.sort_order,
                ftv.is_active,
                ftv.created_at,
                ftv.updated_at";
if ($supportsEpisodeAndScope) {
    $sqlRows .= ",
                ftv.chapter_id,
                ftv.link_scope_type,
                ftv.link_scope_id,
                dc.name AS chapter_name,
                dc.chapter_number,
                ds.name AS season_name,
                ds.season_number";
}
$sqlRows .= "
            FROM fact_tools_topic_viewer ftv";
if ($supportsEpisodeAndScope) {
    $sqlRows .= "
            LEFT JOIN dim_chapters dc ON dc.id = ftv.chapter_id
            LEFT JOIN dim_seasons ds ON ds.id = dc.season_id";
}
$sqlRows .= "
            ORDER BY ftv.is_active DESC, ftv.sort_order ASC, ftv.topic_name ASC, ftv.id DESC";

$rs = $link->query($sqlRows);
if ($rs) {
    while ($r = $rs->fetch_assoc()) {
        $rows[] = $r;
    }
    $rs->close();
}
$totalTopics = count($rows);
$activeTopics = 0;
foreach ($rows as $rowCount) {
    if ((int)($rowCount['is_active'] ?? 0) === 1) {
        $activeTopics++;
    }
}
$inactiveTopics = max(0, $totalTopics - $activeTopics);
$openTopicModal = ($editId > 0) || (
    $hasFlashError
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string)($_POST['crud_action'] ?? '') === 'save'
);
?>

<style>
.topic-viewer-admin .hdr {
    gap: 10px;
}
.topic-viewer-admin .topic-toolbar {
    margin-left: auto;
    display: flex;
    gap: 8px;
    align-items: end;
    flex-wrap: wrap;
}
.topic-viewer-admin .topic-toolbar label,
.topic-viewer-admin .topic-modal-form label {
    color: #cfe;
    font-size: 12px;
    font-weight: 700;
}
.topic-viewer-admin .topic-toolbar .inp {
    min-width: 260px;
}
.topic-viewer-admin .topic-stats {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin: 0 0 10px;
}
.topic-viewer-admin .topic-stat {
    border: 1px solid #1b4aa0;
    border-radius: 999px;
    background: #00135a;
    color: #cfe;
    font-size: 11px;
    padding: 3px 8px;
}
.topic-viewer-admin .topic-table-wrap {
    max-height: 72vh;
    overflow: auto;
    border: 1px solid #000088;
    border-radius: 8px;
}
.topic-viewer-admin .topic-table-wrap .table th {
    position: sticky;
    top: 0;
    z-index: 2;
}
.topic-viewer-admin .topic-name-cell {
    min-width: 260px;
    max-width: 520px;
}
.topic-viewer-admin .topic-desc {
    margin-top: 3px;
    max-width: 480px;
    white-space: normal;
}
.topic-viewer-admin .topic-episode-cell {
    min-width: 260px;
    max-width: 360px;
    white-space: normal !important;
}
.topic-viewer-admin .topic-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.topic-viewer-admin .topic-actions form {
    margin: 0;
}
.topic-viewer-admin .topic-status {
    display: inline-flex;
    align-items: center;
    border: 1px solid #1b4aa0;
    border-radius: 999px;
    padding: 2px 7px;
    background: #00205f;
    color: #dff;
    font-size: 10px;
    font-weight: 700;
}
.topic-viewer-admin .topic-status.off {
    border-color: #666;
    background: #2b2b2b;
    color: #ddd;
}
.topic-viewer-admin .modal {
    width: min(920px, 96vw);
}
.topic-viewer-admin .topic-modal-form {
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.topic-viewer-admin .topic-modal-body {
    display: grid;
    grid-template-columns: repeat(2, minmax(240px, 1fr));
    gap: 10px 12px;
    overflow: auto;
    padding-right: 4px;
}
.topic-viewer-admin .topic-modal-body label {
    display: block;
}
.topic-viewer-admin .topic-modal-body input,
.topic-viewer-admin .topic-modal-body select,
.topic-viewer-admin .topic-modal-body textarea {
    width: 100%;
    box-sizing: border-box;
    margin-top: 4px;
}
.topic-viewer-admin .topic-modal-body .field-full {
    grid-column: 1 / -1;
}
.topic-viewer-admin .topic-modal-body textarea {
    min-height: 110px;
    resize: vertical;
}
@media (max-width: 760px) {
    .topic-viewer-admin .topic-toolbar {
        width: 100%;
        margin-left: 0;
    }
    .topic-viewer-admin .topic-toolbar label,
    .topic-viewer-admin .topic-toolbar .inp {
        width: 100%;
        min-width: 0;
    }
    .topic-viewer-admin .topic-modal-body {
        grid-template-columns: 1fr;
    }
    .topic-viewer-admin .topic-table-wrap .table th,
    .topic-viewer-admin .topic-table-wrap .table td {
        white-space: normal;
    }
}
</style>

<div class="panel-wrap topic-viewer-admin">
  <div class="hdr">
    <h2>Temas de visor de foro</h2>
    <button class="btn btn-green" type="button" id="topicNewBtn">+ Nuevo tema</button>
    <a class="btn" href="/talim">&larr; Panel</a>
    <div class="topic-toolbar">
      <label>Filtro rápido
        <input class="inp" type="text" id="quickFilterTopicViewer" placeholder="Nombre, topic_id, episodio...">
      </label>
    </div>
  </div>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m):
        $cl = ($m['type'] ?? '') === 'ok' ? 'ok' : 'err'; ?>
        <div class="<?= $cl ?>"><?= h($m['msg'] ?? '') ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$supportsEpisodeAndScope): ?>
<div class="flash">
    <div class="err">Faltan columnas (`chapter_id`, `link_scope_type`, `link_scope_id`) en `fact_tools_topic_viewer`. El panel funcionará en modo reducido.</div>
</div>
<?php endif; ?>

  <div class="topic-stats">
    <span class="topic-stat">Total <?= (int)$totalTopics ?></span>
    <span class="topic-stat">Activos <?= (int)$activeTopics ?></span>
    <span class="topic-stat">Inactivos <?= (int)$inactiveTopics ?></span>
  </div>

<div class="topic-table-wrap">
<table class="table" id="topicViewerTable">
    <thead>
        <tr>
            <th class="adm-w-60">ID</th>
            <th>Nombre</th>
            <th class="adm-w-80">topic_id</th>
            <?php if ($supportsEpisodeAndScope): ?>
                <th>Episodio</th>
                <th>Agrupación</th>
            <?php endif; ?>
            <th>URL</th>
            <th class="adm-w-80">Orden</th>
            <th class="adm-w-80">Estado</th>
            <th class="adm-w-160">Fechas</th>
            <th class="adm-w-160">Acciones</th>
        </tr>
    </thead>
    <tbody id="topicViewerBody">
    <?php foreach ($rows as $r): ?>
        <?php
            $search = trim(
                (string)$r['topic_name']
                . ' ' . (string)$r['topic_id']
                . ' ' . (string)$r['topic_url']
                . ' ' . (string)($r['chapter_name'] ?? '')
                . ' ' . (string)($r['season_name'] ?? '')
                . ' ' . (string)($r['link_scope_type'] ?? '')
                . ' ' . (string)($r['link_scope_id'] ?? '')
            );
            if (function_exists('mb_strtolower')) { $search = mb_strtolower($search, 'UTF-8'); }
            else { $search = strtolower($search); }
        ?>
        <tr data-search="<?= h($search) ?>">
            <td><strong class="adm-color-accent"><?= (int)$r['id'] ?></strong></td>
            <td class="topic-name-cell">
                <strong><?= h($r['topic_name']) ?></strong>
                <?php if (trim((string)$r['topic_description']) !== ''): ?>
                    <div class="adm-color-muted small topic-desc"><?= h($r['topic_description']) ?></div>
                <?php endif; ?>
            </td>
            <td><?= (int)$r['topic_id'] ?></td>
            <?php if ($supportsEpisodeAndScope): ?>
                <td class="topic-episode-cell">
                    <?php
                        $chapterTxt = '';
                        $sName = trim((string)($r['season_name'] ?? ''));
                        $sNum = (int)($r['season_number'] ?? 0);
                        $cNum = (int)($r['chapter_number'] ?? 0);
                        $cName = trim((string)($r['chapter_name'] ?? ''));
                        if ($sName !== '') {
                            $chapterTxt = $sName;
                            if ($sNum > 0) { $chapterTxt .= ' (T' . $sNum . ')'; }
                        }
                        if ($cNum > 0) { $chapterTxt .= ($chapterTxt !== '' ? ' · ' : '') . 'Ep. ' . $cNum; }
                        if ($cName !== '') { $chapterTxt .= ($chapterTxt !== '' ? ' · ' : '') . $cName; }
                        if ($chapterTxt === '') { $chapterTxt = '(sin capítulo)'; }
                    ?>
                    <?= h($chapterTxt) ?>
                </td>
                <td>
                    <?php
                        $scopeType = trim((string)($r['link_scope_type'] ?? ''));
                        $scopeId = (int)($r['link_scope_id'] ?? 0);
                        if ($scopeType === '' || $scopeId <= 0) {
                            echo '<span class="adm-color-muted">(sin agrupación)</span>';
                        } else {
                            $scopeLabel = ($scopeType === 'character') ? 'Personaje' : (($scopeType === 'group') ? 'Grupo' : (($scopeType === 'organization') ? 'Organización' : $scopeType));
                            echo h($scopeLabel . ' #' . $scopeId);
                        }
                    ?>
                </td>
            <?php endif; ?>
            <td>
                <?php if (trim((string)$r['topic_url']) !== ''): ?>
                    <a href="<?= h($r['topic_url']) ?>" target="_blank" rel="noopener noreferrer">Abrir</a>
                <?php else: ?>
                    <span class="adm-color-muted">(vacío)</span>
                <?php endif; ?>
            </td>
            <td><?= (int)$r['sort_order'] ?></td>
            <td>
                <span class="topic-status <?= ((int)$r['is_active'] === 1) ? '' : 'off' ?>">
                    <?= ((int)$r['is_active'] === 1) ? 'Activo' : 'Inactivo' ?>
                </span>
            </td>
            <td>
                <div>Alta: <?= h((string)($r['created_at'] ?? '')) ?></div>
                <div>Mod: <?= h((string)($r['updated_at'] ?? '')) ?></div>
            </td>
            <td>
                <div class="topic-actions">
                <button class="btn" type="button"
                    data-topic-edit="1"
                    data-id="<?= (int)$r['id'] ?>"
                    data-topic-name="<?= h($r['topic_name']) ?>"
                    data-topic-id="<?= (int)$r['topic_id'] ?>"
                    data-topic-url="<?= h($r['topic_url']) ?>"
                    data-topic-description="<?= h($r['topic_description']) ?>"
                    data-sort-order="<?= (int)$r['sort_order'] ?>"
                    data-is-active="<?= (int)$r['is_active'] ?>"
                    data-chapter-id="<?= (int)($r['chapter_id'] ?? 0) ?>"
                    data-link-scope-type="<?= h((string)($r['link_scope_type'] ?? '')) ?>"
                    data-link-scope-id="<?= (int)($r['link_scope_id'] ?? 0) ?>"
                >Editar</button>
                <form method="post" onsubmit="return confirm('¿Borrar este tema?');">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="crud_action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-red" type="submit">Borrar</button>
                </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="<?= $supportsEpisodeAndScope ? '10' : '8' ?>" class="adm-color-muted">(Sin temas configurados)</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
</div>

<div class="modal-back topic-viewer-admin" id="topicModal">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="topicModalTitle">
    <h3 id="topicModalTitle"><?= ((int)$editRow['id'] > 0) ? 'Editar tema' : 'Nuevo tema' ?></h3>
    <form method="post" id="topicForm" class="topic-modal-form adm-m-0">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="crud_action" value="save">
      <input type="hidden" name="id" id="f_topic_row_id" value="<?= (int)($editRow['id'] ?? 0) ?>">

      <div class="topic-modal-body">
        <label>Nombre del tema
          <input class="inp" type="text" name="topic_name" id="f_topic_name" maxlength="180" required value="<?= h($editRow['topic_name'] ?? '') ?>">
        </label>

        <label>topic_id
          <input class="inp" type="number" min="1" name="topic_id" id="f_topic_id" required value="<?= h((string)($editRow['topic_id'] ?? '')) ?>">
        </label>

        <?php if ($hasChapterIdCol): ?>
        <label class="field-full">Episodio
          <select class="select" name="chapter_id" id="f_chapter_id">
            <option value="">Sin episodio</option>
            <?php foreach ($chapterOptions as $ch): ?>
              <?php
                $cid = (int)($ch['id'] ?? 0);
                $sel = ((int)($editRow['chapter_id'] ?? 0) === $cid) ? 'selected' : '';
                $seasonName = trim((string)($ch['season_name'] ?? ''));
                $seasonNum = (int)($ch['season_number'] ?? 0);
                $chapterNum = (int)($ch['chapter_number'] ?? 0);
                $chapterName = trim((string)($ch['name'] ?? ''));
                $label = '';
                if ($seasonName !== '') {
                    $label = $seasonName;
                    if ($seasonNum > 0) { $label .= ' (T' . $seasonNum . ')'; }
                }
                if ($chapterNum > 0) { $label .= ($label !== '' ? ' · ' : '') . 'Ep. ' . $chapterNum; }
                if ($chapterName !== '') { $label .= ($label !== '' ? ' · ' : '') . $chapterName; }
                if ($label === '') { $label = 'Capítulo #' . $cid; }
              ?>
              <option value="<?= $cid ?>" <?= $sel ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php endif; ?>

        <label class="field-full">URL
          <input class="inp" type="text" name="topic_url" id="f_topic_url" maxlength="255" value="<?= h($editRow['topic_url'] ?? '') ?>" placeholder="https://naufragio-foros.duckdns.org/index.php/topic,00.0.html">
        </label>

        <label>Orden
          <input class="inp" type="number" min="0" name="sort_order" id="f_sort_order" value="<?= h((string)($editRow['sort_order'] ?? 0)) ?>">
        </label>

        <label>Estado
          <select class="select" name="is_active" id="f_is_active">
            <option value="1" <?= ((int)($editRow['is_active'] ?? 1) === 1) ? 'selected' : '' ?>>Activo</option>
            <option value="0" <?= ((int)($editRow['is_active'] ?? 1) === 0) ? 'selected' : '' ?>>Inactivo</option>
          </select>
        </label>

        <?php if ($hasScopeTypeCol): ?>
        <label>Agrupar por
          <?php $scopeTypeNow = trim((string)($editRow['link_scope_type'] ?? '')); ?>
          <select class="select" name="link_scope_type" id="f_link_scope_type">
            <option value="" <?= ($scopeTypeNow === '') ? 'selected' : '' ?>>Sin agrupación</option>
            <option value="character" <?= ($scopeTypeNow === 'character') ? 'selected' : '' ?>>Personaje</option>
            <option value="group" <?= ($scopeTypeNow === 'group') ? 'selected' : '' ?>>Grupo</option>
            <option value="organization" <?= ($scopeTypeNow === 'organization') ? 'selected' : '' ?>>Organización</option>
          </select>
        </label>
        <?php endif; ?>

        <?php if ($hasScopeIdCol): ?>
        <label>ID de agrupación
          <input class="inp" type="number" min="0" name="link_scope_id" id="f_link_scope_id" value="<?= h((string)($editRow['link_scope_id'] ?? 0)) ?>" placeholder="Ej: 110, 60, 20">
        </label>
        <?php endif; ?>

        <label class="field-full">Descripción
          <textarea class="ta" name="topic_description" id="f_topic_description" rows="4"><?= h($editRow['topic_description'] ?? '') ?></textarea>
        </label>
      </div>

      <div class="modal-actions">
        <button class="btn btn-red" type="button" id="topicCancelBtn">Cancelar</button>
        <button class="btn btn-green" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
    var input = document.getElementById('quickFilterTopicViewer');
    var tbody = document.getElementById('topicViewerBody');
    if (!input || !tbody) return;
    input.addEventListener('input', function(){
        var q = String(input.value || '').toLowerCase();
        var rows = tbody.querySelectorAll('tr');
        for (var i = 0; i < rows.length; i++) {
            var hay = String(rows[i].getAttribute('data-search') || rows[i].textContent || '').toLowerCase();
            rows[i].style.display = hay.indexOf(q) !== -1 ? '' : 'none';
        }
    });

    var modal = document.getElementById('topicModal');
    var title = document.getElementById('topicModalTitle');
    var newBtn = document.getElementById('topicNewBtn');
    var cancelBtn = document.getElementById('topicCancelBtn');
    var form = document.getElementById('topicForm');
    if (!modal || !form) return;

    function setValue(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value == null ? '' : String(value);
    }

    function openModal() {
        modal.style.display = 'flex';
        var name = document.getElementById('f_topic_name');
        if (name) window.setTimeout(function(){ name.focus(); }, 40);
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    function resetForm() {
        form.reset();
        setValue('f_topic_row_id', 0);
        setValue('f_topic_name', '');
        setValue('f_topic_id', '');
        setValue('f_chapter_id', '');
        setValue('f_topic_url', '');
        setValue('f_sort_order', 0);
        setValue('f_is_active', 1);
        setValue('f_link_scope_type', '');
        setValue('f_link_scope_id', 0);
        setValue('f_topic_description', '');
        if (title) title.textContent = 'Nuevo tema';
    }

    if (newBtn) {
        newBtn.addEventListener('click', function(){
            resetForm();
            openModal();
        });
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }
    modal.addEventListener('click', function(ev){
        if (ev.target === modal) closeModal();
    });
    document.addEventListener('keydown', function(ev){
        if (ev.key === 'Escape' && modal.style.display === 'flex') closeModal();
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-topic-edit="1"]'), function(btn){
        btn.addEventListener('click', function(){
            setValue('f_topic_row_id', btn.getAttribute('data-id') || '0');
            setValue('f_topic_name', btn.getAttribute('data-topic-name') || '');
            setValue('f_topic_id', btn.getAttribute('data-topic-id') || '');
            setValue('f_chapter_id', btn.getAttribute('data-chapter-id') || '');
            setValue('f_topic_url', btn.getAttribute('data-topic-url') || '');
            setValue('f_sort_order', btn.getAttribute('data-sort-order') || '0');
            setValue('f_is_active', btn.getAttribute('data-is-active') || '1');
            setValue('f_link_scope_type', btn.getAttribute('data-link-scope-type') || '');
            setValue('f_link_scope_id', btn.getAttribute('data-link-scope-id') || '0');
            setValue('f_topic_description', btn.getAttribute('data-topic-description') || '');
            if (title) title.textContent = 'Editar tema';
            openModal();
        });
    });

    <?php if ($openTopicModal): ?>
    openModal();
    <?php endif; ?>
})();
</script>




