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

$actions = '<span class="adm-flex-right-8">'
    . '<a class="btn" href="/app/tools/topic_viewer_setup_20260318.php" target="_blank">Ejecutar setup</a>'
    . '<label class="adm-text-left">Filtro rapido '
    . '<input class="inp" type="text" id="quickFilterTopicViewer" placeholder="En esta pagina..."></label>'
    . '</span>';
admin_panel_open('Temas de visor de foro', $actions);

if (!topic_viewer_table_exists($link)) {
    echo "<p class='adm-admin-error'>Falta la tabla <code>fact_tools_topic_viewer</code>. Ejecuta <code>app/tools/topic_viewer_setup_20260318.php</code>.</p>";
    admin_panel_close();
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
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $action = (string)$_POST['crud_action'];

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'ID invalido para borrar.'];
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
            } elseif ($hasChapterIdCol && $chapterId <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'chapter_id es obligatorio y debe ser > 0.'];
                $editId = $id;
            } elseif ($hasScopeTypeCol && !in_array($scopeType, $allowedScopeTypes, true)) {
                $flash[] = ['type' => 'error', 'msg' => 'Tipo de agrupación inválido.'];
                $editId = $id;
            } elseif ($hasScopeTypeCol && $hasScopeIdCol && $scopeType !== '' && $scopeId <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'Si eliges tipo de agrupación, link_scope_id debe ser > 0.'];
                $editId = $id;
            } else {
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
                                $chapterId,
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
                                $chapterId,
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
?>

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
    <div class="err">Faltan columnas nuevas (`chapter_id`, `link_scope_type`, `link_scope_id`) en `fact_tools_topic_viewer`. Ejecuta el setup para habilitar metadatos por episodio y agrupación.</div>
</div>
<?php endif; ?>

<h3><?= ((int)$editRow['id'] > 0) ? 'Editar tema' : 'Nuevo tema' ?></h3>
<form method="post" class="adm-grid-1-2">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="crud_action" value="save">
        <input type="hidden" name="id" value="<?= (int)($editRow['id'] ?? 0) ?>">

        <label>Nombre del tema
            <input class="inp" type="text" name="topic_name" maxlength="180" required value="<?= h($editRow['topic_name'] ?? '') ?>">
        </label>

        <label>topic_id
            <input class="inp" type="number" min="1" name="topic_id" required value="<?= h((string)($editRow['topic_id'] ?? '')) ?>">
        </label>

        <?php if ($hasChapterIdCol): ?>
        <label>Episodio (chapter_id)
            <select class="select" name="chapter_id" required>
                <option value="">Selecciona episodio...</option>
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

        <label>URL (opcional)
            <input class="inp" type="text" name="topic_url" maxlength="255" value="<?= h($editRow['topic_url'] ?? '') ?>">
        </label>

        <label>Orden
            <input class="inp" type="number" min="0" name="sort_order" value="<?= h((string)($editRow['sort_order'] ?? 0)) ?>">
        </label>

        <?php if ($hasScopeTypeCol): ?>
        <label>Agrupar por
            <select class="select" name="link_scope_type">
                <?php $scopeTypeNow = trim((string)($editRow['link_scope_type'] ?? '')); ?>
                <option value="" <?= ($scopeTypeNow === '') ? 'selected' : '' ?>>Sin agrupación</option>
                <option value="character" <?= ($scopeTypeNow === 'character') ? 'selected' : '' ?>>Personaje</option>
                <option value="group" <?= ($scopeTypeNow === 'group') ? 'selected' : '' ?>>Grupo</option>
                <option value="organization" <?= ($scopeTypeNow === 'organization') ? 'selected' : '' ?>>Organización</option>
            </select>
        </label>
        <?php endif; ?>

        <?php if ($hasScopeIdCol): ?>
        <label>ID de agrupación
            <input class="inp" type="number" min="0" name="link_scope_id" value="<?= h((string)($editRow['link_scope_id'] ?? 0)) ?>" placeholder="Ej: 110 (character), 60 (group), 20 (organization)">
        </label>
        <?php endif; ?>

        <label class="field-full">Descripcion (opcional)
            <textarea class="ta" name="topic_description" rows="3"><?= h($editRow['topic_description'] ?? '') ?></textarea>
        </label>

        <label>
            Estado
            <select class="select" name="is_active">
                <option value="1" <?= ((int)($editRow['is_active'] ?? 1) === 1) ? 'selected' : '' ?>>Activo</option>
                <option value="0" <?= ((int)($editRow['is_active'] ?? 1) === 0) ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </label>

        <div class="field-full adm-flex-right-8">
            <?php if ((int)($editRow['id'] ?? 0) > 0): ?>
                <a class="btn" href="/talim?s=admin_topic_viewer">Cancelar edicion</a>
            <?php endif; ?>
            <button class="btn btn-green" type="submit">Guardar</button>
        </div>
</form>

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
            <td><?= (int)$r['id'] ?></td>
            <td>
                <strong><?= h($r['topic_name']) ?></strong>
                <?php if (trim((string)$r['topic_description']) !== ''): ?>
                    <div class="adm-color-muted small"><?= h($r['topic_description']) ?></div>
                <?php endif; ?>
            </td>
            <td><?= (int)$r['topic_id'] ?></td>
            <?php if ($supportsEpisodeAndScope): ?>
                <td>
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
            <td><?= ((int)$r['is_active'] === 1) ? 'Activo' : 'Inactivo' ?></td>
            <td>
                <div>Alta: <?= h((string)($r['created_at'] ?? '')) ?></div>
                <div>Mod: <?= h((string)($r['updated_at'] ?? '')) ?></div>
            </td>
            <td>
                <a class="btn" href="/talim?s=admin_topic_viewer&edit=<?= (int)$r['id'] ?>">Editar</a>
                <form method="post" class="adm-inline-form" onsubmit="return confirm('¿Borrar este tema?');">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="crud_action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-red" type="submit">Borrar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
        <tr><td colspan="<?= $supportsEpisodeAndScope ? '10' : '8' ?>" class="adm-color-muted">(Sin temas configurados)</td></tr>
    <?php endif; ?>
    </tbody>
</table>

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
})();
</script>

<?php admin_panel_close(); ?>
