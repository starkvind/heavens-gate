<?php
// admin_chapters.php
if (!isset($link) || !$link) { die('Error de conexion a la base de datos.'); }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/mentions.php');
include_once(__DIR__ . '/../../partials/admin/quill_toolbar_inner.php');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function norm_date($v){ $v = trim((string)$v); return $v === '' ? null : $v; }
function parse_int_list($raw){
    $raw = (string)$raw;
    if (trim($raw) === '') return [];
    $parts = preg_split('/[\s,]+/', $raw);
    $out = [];
    foreach ($parts as $p) {
        if ($p === '' || !preg_match('/^\d+$/', $p)) continue;
        $n = (int)$p;
        if ($n > 0) $out[$n] = $n;
    }
    return array_values($out);
}
function normalize_participation_role($raw): string {
    $role = strtolower(trim((string)$raw));
    return $role === 'player' ? 'player' : 'npc';
}
function parse_pending_relations($raw): array {
    $raw = trim((string)$raw);
    if ($raw === '') return [];

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return [];

    $out = [];
    foreach ($decoded as $row) {
        if (is_array($row)) {
            $characterId = (int)($row['character_id'] ?? 0);
            $role = normalize_participation_role($row['participation_role'] ?? 'npc');
        } else {
            $characterId = (int)$row;
            $role = 'npc';
        }
        if ($characterId <= 0) continue;
        $out[$characterId] = [
            'character_id' => $characterId,
            'participation_role' => $role,
        ];
    }

    return array_values($out);
}
function ac_col_exists(mysqli $link, string $table, string $column): bool {
    static $cache = [];
    $key = $table . ':' . $column;
    if (isset($cache[$key])) return $cache[$key];
    $ok = false;
    if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
        $st->bind_param('ss', $table, $column);
        $st->execute();
        $st->bind_result($count);
        $st->fetch();
        $st->close();
        $ok = ((int)$count > 0);
    }
    $cache[$key] = $ok;
    return $ok;
}
function ac_fetch_season(mysqli $link, int $seasonId): ?array {
    if ($seasonId <= 0) return null;
    if ($st = $link->prepare('SELECT id, season_number, name FROM dim_seasons WHERE id = ? LIMIT 1')) {
        $st->bind_param('i', $seasonId);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $st->close();
        return $row ?: null;
    }
    return null;
}
function attach_chapter_characters(mysqli $link, int $chapterId, array $relations): int {
    if ($chapterId <= 0 || empty($relations)) return 0;
    $added = 0;
    $hasParticipationRole = ac_col_exists($link, 'bridge_chapters_characters', 'participation_role');
    $chk = $link->prepare('SELECT id FROM bridge_chapters_characters WHERE chapter_id = ? AND character_id = ? LIMIT 1');
    $ins = $hasParticipationRole
        ? $link->prepare('INSERT INTO bridge_chapters_characters (chapter_id, character_id, participation_role) VALUES (?, ?, ?)')
        : $link->prepare('INSERT INTO bridge_chapters_characters (chapter_id, character_id) VALUES (?, ?)');
    if (!$chk || !$ins) {
        if ($chk) $chk->close();
        if ($ins) $ins->close();
        return 0;
    }
    foreach ($relations as $relation) {
        if (is_array($relation)) {
            $characterId = (int)($relation['character_id'] ?? 0);
            $participationRole = normalize_participation_role($relation['participation_role'] ?? 'npc');
        } else {
            $characterId = (int)$relation;
            $participationRole = 'npc';
        }
        if ($characterId <= 0) continue;
        $chk->bind_param('ii', $chapterId, $characterId);
        $chk->execute();
        $rs = $chk->get_result();
        $exists = $rs && $rs->fetch_assoc() ? true : false;
        if (!$exists) {
            if ($hasParticipationRole) {
                $ins->bind_param('iis', $chapterId, $characterId, $participationRole);
            } else {
                $ins->bind_param('ii', $chapterId, $characterId);
            }
            if ($ins->execute()) $added++;
        }
    }
    $chk->close();
    $ins->close();
    return $added;
}
$hasChapterSeasonId = true;
$hasChapterParticipationRole = ac_col_exists($link, 'bridge_chapters_characters', 'participation_role');

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_chapters';
if (function_exists('hg_admin_ensure_csrf_token')) {
    $CSRF = hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY);
} else {
    if (empty($_SESSION[$ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[$ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(16));
    }
    $CSRF = $_SESSION[$ADMIN_CSRF_SESSION_KEY];
}
function chapter_csrf_ok(string $sessionKey): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($_POST['csrf'] ?? '');
    if (function_exists('hg_admin_csrf_valid')) {
        return hg_admin_csrf_valid($token, $sessionKey);
    }
    return is_string($token) && $token !== '' && isset($_SESSION[$sessionKey]) && hash_equals($_SESSION[$sessionKey], $token);
}

// AJAX in same controller (standard admin pattern)
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['ok' => false, 'error' => 'Metodo invalido']);
        exit;
    }
    if (!chapter_csrf_ok($ADMIN_CSRF_SESSION_KEY)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF invalido. Recarga la pagina.']);
        exit;
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'get_relations') {
        $chapterId = (int)($_POST['chapter_id'] ?? 0);
        $rows = [];
        if ($chapterId > 0 && ($clean = $link->prepare("
            DELETE b1
            FROM bridge_chapters_characters b1
            INNER JOIN bridge_chapters_characters b2
                ON b1.chapter_id = b2.chapter_id
               AND b1.character_id = b2.character_id
               AND b1.id > b2.id
            WHERE b1.chapter_id = ?
        "))) {
            $clean->bind_param('i', $chapterId);
            $clean->execute();
            $clean->close();
        }
        $roleExpr = $hasChapterParticipationRole
            ? "COALESCE(NULLIF(TRIM(b.participation_role), ''), 'npc')"
            : "CASE WHEN c.character_kind = 'pj' THEN 'player' ELSE 'npc' END";
        if ($chapterId > 0 && ($st = $link->prepare("SELECT b.id, b.character_id, c.name, ch.name AS chronicle_name, {$roleExpr} AS participation_role FROM bridge_chapters_characters b JOIN fact_characters c ON c.id = b.character_id LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id WHERE b.chapter_id = ? ORDER BY c.name ASC, c.id ASC"))) {
            $st->bind_param('i', $chapterId);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
            $st->close();
        }
        echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'add_relation') {
        $chapterId = (int)($_POST['chapter_id'] ?? 0);
        $characterId = (int)($_POST['character_id'] ?? 0);
        $participationRole = normalize_participation_role($_POST['participation_role'] ?? 'npc');
        $ok = false;
        if ($chapterId > 0 && $characterId > 0) {
            $existingRelId = 0;
            if ($chk = $link->prepare('SELECT id FROM bridge_chapters_characters WHERE chapter_id = ? AND character_id = ? LIMIT 1')) {
                $chk->bind_param('ii', $chapterId, $characterId);
                $chk->execute();
                $rs = $chk->get_result();
                $row = $rs ? $rs->fetch_assoc() : null;
                $existingRelId = (int)($row['id'] ?? 0);
                $chk->close();
            }

            if ($existingRelId > 0) {
                if ($hasChapterParticipationRole && ($st = $link->prepare('UPDATE bridge_chapters_characters SET participation_role = ? WHERE id = ?'))) {
                    $st->bind_param('si', $participationRole, $existingRelId);
                    $ok = $st->execute();
                    $st->close();
                } else {
                    $ok = true;
                }
            } elseif ($hasChapterParticipationRole && ($st = $link->prepare('INSERT INTO bridge_chapters_characters (chapter_id, character_id, participation_role) VALUES (?, ?, ?)'))) {
                $st->bind_param('iis', $chapterId, $characterId, $participationRole);
                $ok = $st->execute();
                $st->close();
            } elseif (!$hasChapterParticipationRole && ($st = $link->prepare('INSERT INTO bridge_chapters_characters (chapter_id, character_id) VALUES (?, ?)'))) {
                $st->bind_param('ii', $chapterId, $characterId);
                $ok = $st->execute();
                $st->close();
            } else {
                $ok = false;
            }
        }
        echo json_encode(['ok' => (bool)$ok]);
        exit;
    }

    if ($action === 'update_relation_role') {
        $relId = (int)($_POST['rel_id'] ?? 0);
        $participationRole = normalize_participation_role($_POST['participation_role'] ?? 'npc');
        $ok = false;
        if ($relId > 0) {
            if ($hasChapterParticipationRole && ($st = $link->prepare('UPDATE bridge_chapters_characters SET participation_role = ? WHERE id = ?'))) {
                $st->bind_param('si', $participationRole, $relId);
                $ok = $st->execute();
                $st->close();
            } elseif (!$hasChapterParticipationRole) {
                $ok = true;
            }
        }
        echo json_encode(['ok' => (bool)$ok]);
        exit;
    }

    if ($action === 'del_relation') {
        $relId = (int)($_POST['rel_id'] ?? 0);
        $ok = false;
        if ($relId > 0 && ($st = $link->prepare('DELETE FROM bridge_chapters_characters WHERE id = ?'))) {
            $st->bind_param('i', $relId);
            $ok = $st->execute();
            $st->close();
        }
        echo json_encode(['ok' => (bool)$ok]);
        exit;
    }

    if ($action === 'delete_chapter') {
        $chapterId = (int)($_POST['chapter_id'] ?? 0);
        if ($chapterId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'ID de capitulo invalido']);
            exit;
        }
        $ok = false;
        if ($st = $link->prepare('DELETE FROM dim_chapters WHERE id = ?')) {
            $st->bind_param('i', $chapterId);
            $ok = $st->execute();
            $st->close();
        }
        echo json_encode(['ok' => (bool)$ok, 'message' => $ok ? 'Capitulo eliminado.' : 'No se pudo eliminar.']);
        exit;
    }

    if ($action === 'save_chapter') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $chapterNumber = (int)($_POST['chapter_number'] ?? 0);
        $seasonId = (int)($_POST['season_id'] ?? 0);
        $playedDate = norm_date($_POST['played_date'] ?? '');
        $synopsis = hg_mentions_convert($link, trim((string)($_POST['synopsis'] ?? '')));
        $pendingRelations = parse_pending_relations($_POST['pending_relations_json'] ?? '');
        if (empty($pendingRelations)) {
            $pendingCharacterIds = parse_int_list($_POST['pending_character_ids'] ?? '');
            foreach ($pendingCharacterIds as $pendingCharacterId) {
                $pendingRelations[] = [
                    'character_id' => (int)$pendingCharacterId,
                    'participation_role' => 'npc',
                ];
            }
        }
        $seasonRow = ac_fetch_season($link, $seasonId);

        if ($name === '' || $chapterNumber <= 0 || $seasonId <= 0 || !$seasonRow) {
            echo json_encode(['ok' => false, 'error' => 'Nombre, capitulo y temporada son obligatorios.']);
            exit;
        }

        $savedId = 0;
        $ok = false;
        if ($id > 0) {
            $sql = 'UPDATE dim_chapters SET name=?, chapter_number=?, season_id=?, played_date=?, synopsis=?, updated_at=NOW() WHERE id=?';
            $st = $link->prepare($sql);
            $st->bind_param('siissi', $name, $chapterNumber, $seasonId, $playedDate, $synopsis, $id);
            $ok = $st->execute();
            $savedId = $id;
            $st->close();
        } else {
            $sql = 'INSERT INTO dim_chapters (name, chapter_number, season_id, played_date, synopsis, created_at) VALUES (?, ?, ?, ?, ?, NOW())';
            $st = $link->prepare($sql);
            $st->bind_param('siiss', $name, $chapterNumber, $seasonId, $playedDate, $synopsis);
            $ok = $st->execute();
            $savedId = (int)$link->insert_id;
            $st->close();
        }

        if (!$ok || $savedId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el capitulo.']);
            exit;
        }

        hg_update_pretty_id_if_exists($link, 'dim_chapters', $savedId, $name);
        attach_chapter_characters($link, $savedId, $pendingRelations);

        $chapterRow = null;
        if ($st = $link->prepare("SELECT c.id, c.name, c.chapter_number, s.season_number AS season_number, c.season_id AS season_id, c.played_date, c.synopsis, s.name AS season_name, s.sort_order AS season_sort FROM dim_chapters c LEFT JOIN dim_seasons s ON s.id = c.season_id WHERE c.id = ? LIMIT 1")) {
            $st->bind_param('i', $savedId);
            $st->execute();
            $rs = $st->get_result();
            $chapterRow = $rs ? $rs->fetch_assoc() : null;
            $st->close();
        }

        echo json_encode([
            'ok' => true,
            'message' => $id > 0 ? 'Capitulo actualizado.' : 'Capitulo creado.',
            'data' => $chapterRow,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Accion no valida']);
    exit;
}

$flash = [];

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0 && ($st = $link->prepare('DELETE FROM dim_chapters WHERE id = ?'))) {
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        $flash[] = ['type' => 'ok', 'msg' => 'Capitulo eliminado.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_chapter'])) {
    if (!chapter_csrf_ok($ADMIN_CSRF_SESSION_KEY)) {
        $flash[] = ['type' => 'err', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $chapterNumber = (int)($_POST['chapter_number'] ?? 0);
    $seasonId = (int)($_POST['season_id'] ?? 0);
    $playedDate = norm_date($_POST['played_date'] ?? '');
    $synopsis = hg_mentions_convert($link, trim((string)($_POST['synopsis'] ?? '')));
    $pendingRelations = parse_pending_relations($_POST['pending_relations_json'] ?? '');
    if (empty($pendingRelations)) {
        $pendingCharacterIds = parse_int_list($_POST['pending_character_ids'] ?? '');
        foreach ($pendingCharacterIds as $pendingCharacterId) {
            $pendingRelations[] = [
                'character_id' => (int)$pendingCharacterId,
                'participation_role' => 'npc',
            ];
        }
    }
    $seasonRow = ac_fetch_season($link, $seasonId);

    if ($name === '' || $chapterNumber <= 0 || $seasonId <= 0 || !$seasonRow) {
        $flash[] = ['type' => 'err', 'msg' => 'Nombre, capitulo y temporada son obligatorios.'];
    } else {
        if ($id > 0) {
            $sql = 'UPDATE dim_chapters SET name=?, chapter_number=?, season_id=?, played_date=?, synopsis=?, updated_at=NOW() WHERE id=?';
            $st = $link->prepare($sql);
            $st->bind_param('siissi', $name, $chapterNumber, $seasonId, $playedDate, $synopsis, $id);
            $ok = $st->execute();
            $st->close();
            if ($ok) {
                hg_update_pretty_id_if_exists($link, 'dim_chapters', $id, $name);
                attach_chapter_characters($link, $id, $pendingRelations);
                $flash[] = ['type' => 'ok', 'msg' => 'Capitulo actualizado.'];
            }
        } else {
            $sql = 'INSERT INTO dim_chapters (name, chapter_number, season_id, played_date, synopsis, created_at) VALUES (?, ?, ?, ?, ?, NOW())';
            $st = $link->prepare($sql);
            $st->bind_param('siiss', $name, $chapterNumber, $seasonId, $playedDate, $synopsis);
            $ok = $st->execute();
            $newId = (int)$link->insert_id;
            $st->close();
            if ($ok) {
                hg_update_pretty_id_if_exists($link, 'dim_chapters', $newId, $name);
                attach_chapter_characters($link, $newId, $pendingRelations);
                $flash[] = ['type' => 'ok', 'msg' => 'Capitulo creado.'];
            }
        }
    }
    }
}

$personajes = [];
if ($rs = $link->query('SELECT p.id, p.name, COALESCE(ch.name, "") AS chronicle_name FROM fact_characters p LEFT JOIN dim_chronicles ch ON ch.id = p.chronicle_id ORDER BY p.name ASC, p.id ASC')) {
    while ($r = $rs->fetch_assoc()) { $personajes[] = $r; }
    $rs->close();
}

$temporadasCatalogo = [];
if ($rs = $link->query('SELECT id, season_number, name, sort_order FROM dim_seasons ORDER BY sort_order ASC, season_number ASC, id ASC')) {
    while ($r = $rs->fetch_assoc()) { $temporadasCatalogo[] = $r; }
    $rs->close();
}

$chapters = [];
if ($rs = $link->query("SELECT c.id, c.name, c.chapter_number, s.season_number AS season_number, c.season_id AS season_id, c.played_date, c.synopsis, s.name AS season_name, s.sort_order AS season_sort FROM dim_chapters c LEFT JOIN dim_seasons s ON s.id = c.season_id ORDER BY COALESCE(s.sort_order, 9999) ASC, c.chapter_number ASC, c.id ASC")) {
    while ($r = $rs->fetch_assoc()) { $chapters[] = $r; }
    $rs->close();
}

$actions = '<span class="adm-flex-right-wrap-8">'
    . '<label class="adm-text-left">Filtro rapido <input class="inp" type="text" id="quickFilter" placeholder="Nombre..."></label>'
    . '<label class="adm-text-left">Temporada <select id="seasonFilter" class="select"><option value="">Todas</option>';
foreach ($temporadasCatalogo as $t) {
    $actions .= '<option value="' . (int)$t['id'] . '">' . h($t['name']) . '</option>';
}
$actions .= '</select></label>'
    . '<button class="btn btn-green" type="button" onclick="openChapterModal(0)">+ Nuevo capitulo</button>'
    . '</span>';

include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
admin_panel_open('Capitulos', $actions);
?>

<?php if (!empty($flash)): ?>
<div class="flash">
    <?php foreach ($flash as $m): ?>
    <div class="<?= h($m['type']) ?>"><?= h($m['msg']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<table class="table" id="chaptersTable">
    <thead>
        <tr>
            <th>Temporada</th>
            <th>#</th>
            <th>Nombre</th>
            <th>Fecha</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
<div id="chaptersPager" class="pager adm-justify-end"></div>

<div class="chap-modal-back" id="chapterModalBack" aria-hidden="true">
    <div class="chap-modal adm-modal-980">
        <h3 id="chapterModalTitle">Capitulo</h3>
        <form method="post" action="/talim?s=admin_chapters" id="chapterForm">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
            <input type="hidden" name="save_chapter" value="1">
            <input type="hidden" name="id" id="f_id" value="0">
            <input type="hidden" name="pending_relations_json" id="f_pending_relations_json" value="">

            <div class="grid">
                <label>Nombre
                    <input class="inp" type="text" name="name" id="f_name" required>
                </label>
                <label>Capitulo
                    <input class="inp" type="number" min="1" name="chapter_number" id="f_chapter" required>
                </label>
                <label>Temporada
                    <select class="select" name="season_id" id="f_season" required>
                        <?php foreach ($temporadasCatalogo as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= h($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Fecha jugada
                    <input class="inp" type="date" name="played_date" id="f_played">
                </label>
                <label class="adm-grid-full">Sinopsis
                    <div>
                        <div id="chapter_synopsis_toolbar" class="ql-toolbar ql-snow">
                            <?= admin_quill_toolbar_inner(); ?>
                        </div>
                        <div id="chapter_synopsis_editor" class="ql-container ql-snow"></div>
                        <textarea class="ta adm-hidden" name="synopsis" id="f_synopsis" rows="8"></textarea>
                    </div>
                </label>
            </div>

            <div class="box-like adm-box-like">
                <div class="adm-flex-8-mb8">
                    <strong>Participantes</strong>
                    <select id="characterSelect" class="select adm-maxw-360">
                        <option value="">Seleccionar personaje</option>
                        <?php foreach ($personajes as $pj): ?>
                        <option value="<?= (int)$pj['id'] ?>"><?= h($pj['name']) ?> (#<?= (int)$pj['id'] ?><?= $pj['chronicle_name'] !== '' ? ' - "' . h($pj['chronicle_name']) . '"' : '' ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <select id="relationRoleSelect" class="select">
                        <option value="npc">NPC</option>
                        <option value="player">Player</option>
                    </select>
                    <button class="btn" type="button" id="btnAddRel">Agregar</button>
                </div>
                <?php if (!$hasChapterParticipationRole): ?>
                <div class="small adm-mb8">El rol `player|npc` aun no existe en la tabla `bridge_chapters_characters`. Cuando ejecutes la migracion, este selector quedara operativo.</div>
                <?php endif; ?>
                <div id="relationsList" class="small">Sin participantes.</div>
            </div>

            <div class="modal-actions">
                <button class="btn btn-green" type="submit">Guardar</button>
                <button class="btn" type="button" onclick="closeChapterModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<link href="/assets/vendor/quill/1.3.7/quill.snow.css" rel="stylesheet">
<script src="/assets/vendor/quill/1.3.7/quill.min.js"></script>
<?php
$adminHttpJs = '/assets/js/admin/admin-http.js';
$adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time();
?>
<script src="<?= h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
window.ADMIN_CSRF_TOKEN = <?= json_encode($CSRF, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;
const chapters = <?= json_encode($chapters, JSON_UNESCAPED_UNICODE); ?>;
const charactersCatalog = <?= json_encode($personajes, JSON_UNESCAPED_UNICODE); ?>;
const hasChapterParticipationRole = <?= $hasChapterParticipationRole ? 'true' : 'false' ?>;
const CHAPTER_MENTION_TYPES = ['character','season','episode','organization','group','gift','rite','totem','discipline','item','trait','background','merit','flaw','merydef','doc'];
let page = 1;
const pageSize = 20;
let currentId = 0;
let pendingRelations = [];
const characterById = new Map((charactersCatalog || []).map(c => [Number(c.id), c]));
let chapterSynopsisEditor = null;

function esc(s){
    if (!s) return '';
    return String(s).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;');
}

function filteredChapters(){
    const q = (document.getElementById('quickFilter').value || '').toLowerCase();
    const sf = document.getElementById('seasonFilter').value;
    return chapters.filter(c => {
        const okName = (c.name || '').toLowerCase().includes(q);
        const okSeason = (sf === '' || String(c.season_id || '') === sf);
        return okName && okSeason;
    });
}

function renderTable(){
    const rows = filteredChapters();
    const tbody = document.querySelector('#chaptersTable tbody');
    tbody.innerHTML = '';

    const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
    if (page > totalPages) page = totalPages;
    const start = (page - 1) * pageSize;
    const end = Math.min(start + pageSize, rows.length);

    for (let i = start; i < end; i++) {
        const c = rows[i];
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${esc(c.season_name || ('Temporada ' + c.season_number))}</td>
            <td>${esc(c.chapter_number)}</td>
            <td>${esc(c.name)}</td>
            <td>${esc(c.played_date || '')}</td>
            <td>
                <button class="btn" type="button" onclick="openChapterModal(${Number(c.id)})">Editar</button>
                <button class="btn btn-red" type="button" onclick="deleteChapter(${Number(c.id)})">Borrar</button>
            </td>`;
        tbody.appendChild(tr);
    }

    const pager = document.getElementById('chaptersPager');
    pager.innerHTML = '';
    if (totalPages <= 1) return;
    if (page > 1) {
        pager.innerHTML += `<button class="btn" type="button" onclick="goPage(${page-1})">Anterior</button>`;
    }
    pager.innerHTML += `<span class="cur">${page}/${totalPages}</span>`;
    if (page < totalPages) {
        pager.innerHTML += `<button class="btn" type="button" onclick="goPage(${page+1})">Siguiente</button>`;
    }
}

function goPage(n){ page = n; renderTable(); }

function chapterById(id){
    return chapters.find(c => Number(c.id) === Number(id)) || null;
}

function sortChaptersInPlace(){
    chapters.sort((a, b) => {
        const sa = Number(a.season_sort || a.season_number || 0);
        const sb = Number(b.season_sort || b.season_number || 0);
        if (sa !== sb) return sa - sb;
        const ca = Number(a.chapter_number || 0);
        const cb = Number(b.chapter_number || 0);
        if (ca !== cb) return ca - cb;
        return Number(a.id || 0) - Number(b.id || 0);
    });
}

function upsertChapter(row){
    if (!row || !row.id) return;
    const id = Number(row.id);
    const idx = chapters.findIndex(c => Number(c.id) === id);
    if (idx >= 0) chapters[idx] = row;
    else chapters.push(row);
    sortChaptersInPlace();
}

function ensureChapterSynopsisEditor(){
    if (chapterSynopsisEditor || !window.Quill) return chapterSynopsisEditor;
    const editor = document.getElementById('chapter_synopsis_editor');
    const toolbar = document.getElementById('chapter_synopsis_toolbar');
    if (!editor || !toolbar) return null;
    chapterSynopsisEditor = new Quill('#chapter_synopsis_editor', { theme:'snow', modules:{ toolbar:'#chapter_synopsis_toolbar' } });
    if (window.hgMentions) { window.hgMentions.attachQuill(chapterSynopsisEditor, { types: CHAPTER_MENTION_TYPES }); }
    return chapterSynopsisEditor;
}

function characterLabelById(id){
    const c = characterById.get(Number(id));
    if (!c) return `#${Number(id)}`;
    const chron = c.chronicle_name ? ` - "${c.chronicle_name}"` : '';
    return `${c.name} (#${Number(c.id)}${chron})`;
}

function normalizeParticipationRole(role){
    return String(role || '').toLowerCase() === 'player' ? 'player' : 'npc';
}

function participationRoleLabel(role){
    return normalizeParticipationRole(role) === 'player' ? 'Player' : 'NPC';
}

function relationRoleSelectHtml(selectedRole, onChangeExpr, disabled){
    const role = normalizeParticipationRole(selectedRole);
    const disabledAttr = disabled ? ' disabled' : '';
    return `<select class="select" style="min-width:110px" onchange="${esc(onChangeExpr)}"${disabledAttr}>
        <option value="npc"${role === 'npc' ? ' selected' : ''}>NPC</option>
        <option value="player"${role === 'player' ? ' selected' : ''}>Player</option>
    </select>`;
}

function syncPendingField(){
    document.getElementById('f_pending_relations_json').value = JSON.stringify(pendingRelations);
}

function renderPendingRelations(){
    const box = document.getElementById('relationsList');
    if (!pendingRelations.length) {
        box.textContent = 'Sin participantes (se guardaran al crear el capitulo).';
        return;
    }
    let html = '<ul class="adm-ul-reset">';
    for (const rel of pendingRelations) {
        const cid = Number(rel.character_id || 0);
        const roleSel = relationRoleSelectHtml(rel.participation_role, `changePendingRelationRole(${cid}, this.value)`, !hasChapterParticipationRole);
        html += `<li class="adm-flex-8-mb8">${esc(characterLabelById(cid))} ${roleSel} <span class="small">${esc(participationRoleLabel(rel.participation_role))}</span> <button class="btn btn-red adm-pad-2-6-fs10" type="button" onclick="removePendingRelation(${cid})">Quitar</button></li>`;
    }
    html += '</ul>';
    box.innerHTML = html;
}

function openChapterModal(id){
    ensureChapterSynopsisEditor();
    currentId = Number(id || 0);
    pendingRelations = [];
    syncPendingField();
    const c = chapterById(currentId);

    document.getElementById('f_id').value = c ? c.id : 0;
    document.getElementById('f_name').value = c ? (c.name || '') : '';
    document.getElementById('f_chapter').value = c ? (c.chapter_number || '') : '';
    document.getElementById('f_season').value = c ? String(c.season_id || '') : (document.getElementById('seasonFilter').value || '');
    document.getElementById('f_played').value = c ? (c.played_date || '') : '';
    document.getElementById('relationRoleSelect').value = 'npc';
    const synopsisHtml = c ? (c.synopsis || '') : '';
    document.getElementById('f_synopsis').value = synopsisHtml;
    if (chapterSynopsisEditor) chapterSynopsisEditor.root.innerHTML = synopsisHtml;
    document.getElementById('chapterModalTitle').textContent = c ? 'Editar capitulo' : 'Nuevo capitulo';

    document.getElementById('chapterModalBack').style.display = 'flex';
    loadRelations();
}

function closeChapterModal(){
    document.getElementById('chapterModalBack').style.display = 'none';
}

async function postAjax(data){
    const body = new URLSearchParams(data);
    if (!body.has('csrf') && window.ADMIN_CSRF_TOKEN) body.set('csrf', String(window.ADMIN_CSRF_TOKEN));
    const endpoint = '/talim?s=admin_chapters&ajax=1';
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
        return window.HGAdminHttp.request(endpoint, {
            method: 'POST',
            body
        });
    }
    const res = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body
    });
    const txt = await res.text();
    const json = txt ? JSON.parse(txt) : {};
    if (!res.ok || !json || json.ok === false) {
        throw new Error((json && (json.message || json.error || json.msg)) || ('HTTP ' + res.status));
    }
    return json;
}

async function loadRelations(){
    const box = document.getElementById('relationsList');
    if (!currentId) {
        renderPendingRelations();
        return;
    }
    try {
        const data = await postAjax({ action: 'get_relations', chapter_id: currentId });
        if (!data.ok) { box.textContent = 'No se pudieron cargar participantes.'; return; }
        if (!data.data || !data.data.length) { box.textContent = 'Sin participantes.'; return; }
        let html = '<ul class="adm-ul-reset">';
        for (const rel of data.data) {
            const chron = rel.chronicle_name ? ` - "${rel.chronicle_name}"` : '';
            const roleSel = relationRoleSelectHtml(rel.participation_role, `changeRelationRole(${Number(rel.id)}, this.value)`, !hasChapterParticipationRole);
            html += `<li class="adm-flex-8-mb8">${esc(rel.name)} (#${Number(rel.character_id)}${esc(chron)}) ${roleSel} <span class="small">${esc(participationRoleLabel(rel.participation_role))}</span> <button class="btn btn-red adm-pad-2-6-fs10" type="button" onclick="removeRelation(${Number(rel.id)})">Quitar</button></li>`;
        }
        html += '</ul>';
        box.innerHTML = html;
    } catch (e) {
        box.textContent = 'Error al cargar participantes.';
    }
}

async function addRelation(){
    const characterId = Number(document.getElementById('characterSelect').value || 0);
    const participationRole = normalizeParticipationRole(document.getElementById('relationRoleSelect').value || 'npc');
    if (!characterId) return;

    if (!currentId) {
        const idx = pendingRelations.findIndex(rel => Number(rel.character_id) === characterId);
        const relation = { character_id: characterId, participation_role: participationRole };
        if (idx >= 0) pendingRelations[idx] = relation;
        else pendingRelations.push(relation);
        syncPendingField();
        document.getElementById('characterSelect').value = '';
        document.getElementById('relationRoleSelect').value = 'npc';
        renderPendingRelations();
        return;
    }

    try {
        const data = await postAjax({ action: 'add_relation', chapter_id: currentId, character_id: characterId, participation_role: participationRole });
        if (data.ok) {
            document.getElementById('characterSelect').value = '';
            document.getElementById('relationRoleSelect').value = 'npc';
            loadRelations();
        }
    } catch (e) {
        // no-op: loadRelations already reports recoverable failures
    }
}

function changePendingRelationRole(characterId, role){
    const idx = pendingRelations.findIndex(rel => Number(rel.character_id) === Number(characterId));
    if (idx < 0) return;
    pendingRelations[idx].participation_role = normalizeParticipationRole(role);
    syncPendingField();
    renderPendingRelations();
}

function removePendingRelation(characterId){
    pendingRelations = pendingRelations.filter(rel => Number(rel.character_id) !== Number(characterId));
    syncPendingField();
    renderPendingRelations();
}

async function changeRelationRole(relId, role){
    if (!hasChapterParticipationRole) return;
    try {
        const data = await postAjax({ action: 'update_relation_role', rel_id: relId, participation_role: normalizeParticipationRole(role) });
        if (data.ok) loadRelations();
    } catch (e) {
        loadRelations();
    }
}

async function removeRelation(relId){
    try {
        const data = await postAjax({ action: 'del_relation', rel_id: relId });
        if (data.ok) loadRelations();
    } catch (e) {
        // no-op
    }
}

async function deleteChapter(chapterId){
    const id = Number(chapterId || 0);
    if (!id) return;
    if (!confirm('Eliminar este capitulo?')) return;
    try {
        const data = await postAjax({ action: 'delete_chapter', chapter_id: id });
        if (!data || !data.ok) throw new Error((data && (data.message || data.error || data.msg)) || 'No se pudo eliminar.');
        const idx = chapters.findIndex(c => Number(c.id) === id);
        if (idx >= 0) chapters.splice(idx, 1);
        if (currentId === id) closeChapterModal();
        renderTable();
        if (window.HGAdminHttp && typeof window.HGAdminHttp.notify === 'function') {
            window.HGAdminHttp.notify(data.message || 'Capitulo eliminado.', 'ok');
        }
    } catch (e) {
        alert((window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(e) : (e.message || 'Error al eliminar.'));
    }
}

document.getElementById('quickFilter').addEventListener('input', () => { page = 1; renderTable(); });
document.getElementById('seasonFilter').addEventListener('change', () => { page = 1; renderTable(); });
document.getElementById('btnAddRel').addEventListener('click', addRelation);
document.getElementById('chapterForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    if (chapterSynopsisEditor) {
        const html = chapterSynopsisEditor.root.innerHTML || '';
        const plain = (chapterSynopsisEditor.getText() || '').replace(/\s+/g, ' ').trim();
        document.getElementById('f_synopsis').value = plain ? html : '';
    }
    try {
        const form = document.getElementById('chapterForm');
        const fd = new FormData(form);
        const payload = {};
        fd.forEach((v, k) => { payload[k] = v; });
        payload.action = 'save_chapter';
        const data = await postAjax(payload);
        if (!data || !data.ok) throw new Error((data && (data.message || data.error || data.msg)) || 'No se pudo guardar.');
        if (data.data) upsertChapter(data.data);
        renderTable();
        closeChapterModal();
        if (window.HGAdminHttp && typeof window.HGAdminHttp.notify === 'function') {
            window.HGAdminHttp.notify(data.message || 'Capitulo guardado.', 'ok');
        }
    } catch (e) {
        alert((window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(e) : (e.message || 'Error al guardar.'));
    }
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeChapterModal();
});

renderTable();
</script>
<?php include_once(__DIR__ . '/../../partials/admin/mentions_includes.php'); ?>

<?php admin_panel_close(); ?>




