<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/admin_catalog_utils.php');
include_once(__DIR__ . '/../../helpers/admin_phase7_audit.php');

$isAjaxRequest = (((string)($_GET['ajax'] ?? '') === '1') || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'));
$csrfKey = 'csrf_admin_bso';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');

function hg_abs_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function hg_abs_short(string $text, int $max = 100): string {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    if ($text === '') return '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $max ? (mb_substr($text, 0, $max, 'UTF-8') . '...') : $text;
    }
    return strlen($text) > $max ? (substr($text, 0, $max) . '...') : $text;
}
function hg_abs_csrf_ok(): bool {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $token = function_exists('hg_admin_extract_csrf_token') ? hg_admin_extract_csrf_token($payload) : (string)($_POST['csrf'] ?? '');
    return function_exists('hg_admin_csrf_valid')
        ? hg_admin_csrf_valid($token, 'csrf_admin_bso')
        : (is_string($token) && $token !== '' && isset($_SESSION['csrf_admin_bso']) && hash_equals((string)$_SESSION['csrf_admin_bso'], $token));
}
function hg_abs_youtube_id(string $input): string {
    $input = trim($input);
    if ($input === '') return '';
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $input)) return $input;
    if (preg_match('~(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtube\.com/shorts/|youtu\.be/)([A-Za-z0-9_-]{11})~i', $input, $m)) {
        return (string)$m[1];
    }
    if (preg_match('~v=([A-Za-z0-9_-]{11})~i', $input, $m)) {
        return (string)$m[1];
    }
    return '';
}
function hg_abs_normalize_youtube(string $input): string {
    $id = hg_abs_youtube_id($input);
    return $id !== '' ? ('https://www.youtube.com/watch?v=' . $id) : '';
}
function hg_abs_soundtrack_exists(mysqli $link, string $title, string $artist, string $youtubeUrl, int $excludeId = 0): bool {
    $title = trim($title);
    $artist = trim($artist);
    $youtubeUrl = trim($youtubeUrl);
    if ($title === '' || $youtubeUrl === '') return false;
    $sql = "SELECT id FROM dim_soundtracks WHERE TRIM(COALESCE(title, '')) = ? AND TRIM(COALESCE(artist, '')) = ? AND TRIM(COALESCE(youtube_url, '')) = ? AND id <> ? LIMIT 1";
    $st = $link->prepare($sql);
    if (!$st) return false;
    $foundId = 0;
    $st->bind_param('sssi', $title, $artist, $youtubeUrl, $excludeId);
    $ok = false;
    if ($st->execute()) {
        $st->bind_result($foundId);
        $ok = $st->fetch();
    }
    $st->close();
    return (bool)$ok && $foundId > 0;
}
function hg_abs_dep_summary(array $row): string {
    $parts = [];
    $characters = (int)($row['characters_count'] ?? 0);
    $seasons = (int)($row['seasons_count'] ?? 0);
    $chapters = (int)($row['chapters_count'] ?? 0);
    if ($characters > 0) $parts[] = $characters . ' personajes';
    if ($seasons > 0) $parts[] = $seasons . ' temporadas';
    if ($chapters > 0) $parts[] = $chapters . ' episodios';
    return !empty($parts) ? implode(' | ', $parts) : 'Sin usos';
}
function hg_abs_audit_class(array $flags): string {
    if (empty($flags)) return 'ok';
    if (count($flags) >= 3) return 'warn';
    return 'review';
}
function hg_abs_bridge_total(mysqli $link, int $soundtrackId): int {
    return hg_table_has_column($link, 'bridge_soundtrack_links', 'soundtrack_id')
        ? hg_admin_catalog_count_by_id($link, 'bridge_soundtrack_links', 'soundtrack_id', $soundtrackId)
        : 0;
}
function hg_abs_type_label(string $type): string {
    if ($type === 'personaje') return 'Personaje';
    if ($type === 'temporada') return 'Temporada';
    if ($type === 'episodio') return 'Episodio';
    return ucfirst($type);
}
function hg_abs_public_url(string $type, string $prettyId, int $objectId): string {
    $slug = trim($prettyId) !== '' ? trim($prettyId) : (string)$objectId;
    if ($slug === '' || $objectId <= 0) return '';
    if ($type === 'personaje') return '/characters/' . rawurlencode($slug);
    if ($type === 'temporada') return '/seasons/' . rawurlencode($slug);
    if ($type === 'episodio') return '/chapters/' . rawurlencode($slug);
    return '';
}
function hg_abs_soundtrack_link_exists(mysqli $link, int $soundtrackId, string $type, int $objectId): bool {
    if ($soundtrackId <= 0 || $objectId <= 0 || $type === '' || !hg_table_has_column($link, 'bridge_soundtrack_links', 'soundtrack_id')) return false;
    $sql = 'SELECT id FROM bridge_soundtrack_links WHERE soundtrack_id = ? AND object_type = ? AND object_id = ? LIMIT 1';
    $st = $link->prepare($sql);
    if (!$st) return false;
    $foundId = 0;
    $ok = false;
    $st->bind_param('isi', $soundtrackId, $type, $objectId);
    if ($st->execute()) {
        $st->bind_result($foundId);
        $ok = $st->fetch();
    }
    $st->close();
    return (bool)$ok && $foundId > 0;
}
function hg_abs_link_object_exists(mysqli $link, string $type, int $objectId): bool {
    if ($objectId <= 0) return false;
    $table = $type === 'personaje' ? 'fact_characters' : ($type === 'temporada' ? 'dim_seasons' : ($type === 'episodio' ? 'dim_chapters' : ''));
    if ($table === '') return false;
    $sql = "SELECT id FROM `$table` WHERE id = ? LIMIT 1";
    $st = $link->prepare($sql);
    if (!$st) return false;
    $foundId = 0;
    $ok = false;
    $st->bind_param('i', $objectId);
    if ($st->execute()) {
        $st->bind_result($foundId);
        $ok = $st->fetch();
    }
    $st->close();
    return (bool)$ok && $foundId > 0;
}
function hg_abs_query_rows(mysqli $link, string $sql, ?string $fallbackSql = null): array {
    $rows = [];
    $rs = $link->query($sql);
    if (!$rs && $fallbackSql !== null && trim($fallbackSql) !== '') {
        if (function_exists('hg_runtime_log_error')) hg_runtime_log_error('admin_bso.query_rows.primary', $link->error . ' | SQL: ' . $sql);
        $rs = $link->query($fallbackSql);
    }
    if ($rs) {
        while ($row = $rs->fetch_assoc()) $rows[] = $row;
        $rs->close();
    } elseif (function_exists('hg_runtime_log_error')) {
        hg_runtime_log_error('admin_bso.query_rows.final', $link->error . ' | SQL: ' . ($fallbackSql !== null && trim($fallbackSql) !== '' ? $fallbackSql : $sql));
    }
    return $rows;
}
function hg_abs_format_season_base_label(int $seasonId, string $name, int $seasonNumber): string {
    $name = trim($name);
    if ($seasonNumber > 0 && $name !== '') return 'T' . $seasonNumber . ' - ' . $name;
    if ($seasonNumber > 0) return 'T' . $seasonNumber;
    return $name !== '' ? $name : ('Temporada #' . $seasonId);
}
function hg_abs_format_episode_base_label(int $episodeId, string $name, int $chapterNumber): string {
    $name = trim($name);
    if ($chapterNumber > 0 && $name !== '') return 'E' . $chapterNumber . ' - ' . $name;
    if ($chapterNumber > 0) return 'E' . $chapterNumber;
    return $name !== '' ? $name : ('Episodio #' . $episodeId);
}
function hg_abs_chronicle_name_map(mysqli $link): array {
    $map = [];
    if (!hg_table_has_column($link, 'dim_chronicles', 'name')) return $map;
    $rows = hg_abs_query_rows($link, "SELECT id, COALESCE(NULLIF(TRIM(name), ''), CONCAT('Cronica #', id)) AS chronicle_name FROM dim_chronicles ORDER BY name ASC, id ASC");
    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) continue;
        $map[$id] = (string)($row['chronicle_name'] ?? ('Cronica #' . $id));
    }
    return $map;
}
function hg_abs_build_link_catalog(mysqli $link): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $chronicleMap = hg_abs_chronicle_name_map($link);
    $hasCharPretty = hg_table_has_column($link, 'fact_characters', 'pretty_id');
    $hasCharChronicleId = hg_table_has_column($link, 'fact_characters', 'chronicle_id');
    $hasSeasonPretty = hg_table_has_column($link, 'dim_seasons', 'pretty_id');
    $hasSeasonNumber = hg_table_has_column($link, 'dim_seasons', 'season_number');
    $hasSeasonChronicleId = hg_table_has_column($link, 'dim_seasons', 'chronicle_id');
    $hasChapterPretty = hg_table_has_column($link, 'dim_chapters', 'pretty_id');
    $hasChapterNumber = hg_table_has_column($link, 'dim_chapters', 'chapter_number');
    $hasChapterSeasonId = hg_table_has_column($link, 'dim_chapters', 'season_id');
    $hasChapterPlayedDate = hg_table_has_column($link, 'dim_chapters', 'played_date');

    $options = ['personaje' => [], 'temporada' => [], 'episodio' => []];
    $labels = ['personaje' => [], 'temporada' => [], 'episodio' => []];
    $pretty = ['personaje' => [], 'temporada' => [], 'episodio' => []];
    $seasonBaseById = [];

    $seasonRows = hg_abs_query_rows(
        $link,
        "SELECT s.id,
                COALESCE(NULLIF(TRIM(s.name), ''), CONCAT('Temporada #', s.id)) AS season_name,
                " . ($hasSeasonNumber ? "COALESCE(s.season_number, 0)" : "0") . " AS season_number,
                " . ($hasSeasonPretty ? "COALESCE(s.pretty_id, '')" : "''") . " AS pretty_id,
                " . ($hasSeasonChronicleId ? "COALESCE(s.chronicle_id, 0)" : "0") . " AS chronicle_id
         FROM dim_seasons s
         ORDER BY " . ($hasSeasonNumber ? "season_number ASC, " : "") . "season_name ASC, s.id ASC"
    );
    foreach ($seasonRows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) continue;
        $baseLabel = hg_abs_format_season_base_label($id, (string)($row['season_name'] ?? ''), (int)($row['season_number'] ?? 0));
        $chronicleId = (int)($row['chronicle_id'] ?? 0);
        $chronicleLabel = ($chronicleId > 0 && isset($chronicleMap[$chronicleId])) ? $chronicleMap[$chronicleId] : 'Sin cronica asociada';
        $label = $baseLabel . ' | ' . $chronicleLabel;
        $prettyId = (string)($row['pretty_id'] ?? '');
        $seasonBaseById[$id] = $baseLabel;
        $labels['temporada'][$id] = $label;
        $pretty['temporada'][$id] = $prettyId;
        $options['temporada'][] = ['id' => $id, 'label' => $label, 'pretty_id' => $prettyId];
    }

    $characterRows = hg_abs_query_rows(
        $link,
        "SELECT c.id,
                COALESCE(NULLIF(TRIM(c.name), ''), CONCAT('Personaje #', c.id)) AS character_name,
                " . ($hasCharPretty ? "COALESCE(c.pretty_id, '')" : "''") . " AS pretty_id,
                " . ($hasCharChronicleId ? "COALESCE(c.chronicle_id, 0)" : "0") . " AS chronicle_id
         FROM fact_characters c
         ORDER BY character_name ASC, c.id ASC"
    );
    foreach ($characterRows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) continue;
        $chronicleId = (int)($row['chronicle_id'] ?? 0);
        $chronicleLabel = ($chronicleId > 0 && isset($chronicleMap[$chronicleId])) ? $chronicleMap[$chronicleId] : 'Sin cronica asociada';
        $label = '#' . $id . ' | ' . (string)($row['character_name'] ?? ('Personaje #' . $id)) . ' | ' . $chronicleLabel;
        $prettyId = (string)($row['pretty_id'] ?? '');
        $labels['personaje'][$id] = $label;
        $pretty['personaje'][$id] = $prettyId;
        $options['personaje'][] = ['id' => $id, 'label' => $label, 'pretty_id' => $prettyId];
    }

    $chapterRows = hg_abs_query_rows(
        $link,
        "SELECT c.id,
                COALESCE(NULLIF(TRIM(c.name), ''), CONCAT('Episodio #', c.id)) AS chapter_name,
                " . ($hasChapterNumber ? "COALESCE(c.chapter_number, 0)" : "0") . " AS chapter_number,
                " . ($hasChapterSeasonId ? "COALESCE(c.season_id, 0)" : "0") . " AS season_id,
                " . ($hasChapterPretty ? "COALESCE(c.pretty_id, '')" : "''") . " AS pretty_id
         FROM dim_chapters c
         ORDER BY " . ($hasChapterPlayedDate ? "c.played_date DESC, " : "") . ($hasChapterNumber ? "chapter_number DESC, " : "") . "c.id DESC"
    );
    foreach ($chapterRows as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) continue;
        $seasonId = (int)($row['season_id'] ?? 0);
        $seasonLabel = ($seasonId > 0 && isset($seasonBaseById[$seasonId])) ? $seasonBaseById[$seasonId] : 'Sin temporada asociada';
        $label = hg_abs_format_episode_base_label($id, (string)($row['chapter_name'] ?? ''), (int)($row['chapter_number'] ?? 0)) . ' | ' . $seasonLabel;
        $prettyId = (string)($row['pretty_id'] ?? '');
        $labels['episodio'][$id] = $label;
        $pretty['episodio'][$id] = $prettyId;
        $options['episodio'][] = ['id' => $id, 'label' => $label, 'pretty_id' => $prettyId];
    }

    $cache = ['options' => $options, 'labels' => $labels, 'pretty' => $pretty];
    return $cache;
}
function hg_abs_fetch_link_options(mysqli $link): array {
    $catalog = hg_abs_build_link_catalog($link);
    return (array)($catalog['options'] ?? ['personaje' => [], 'temporada' => [], 'episodio' => []]);
}
function hg_abs_fetch_links_by_soundtrack(mysqli $link): array {
    if (!hg_table_has_column($link, 'bridge_soundtrack_links', 'soundtrack_id')) return [];
    $catalog = hg_abs_build_link_catalog($link);
    $labels = (array)($catalog['labels'] ?? []);
    $pretty = (array)($catalog['pretty'] ?? []);
    $rows = hg_abs_query_rows($link, "SELECT id, soundtrack_id, object_type, object_id FROM bridge_soundtrack_links ORDER BY soundtrack_id ASC, object_type ASC, object_id ASC, id ASC");
    $map = [];
    foreach ($rows as $row) {
        $sid = (int)($row['soundtrack_id'] ?? 0);
        $type = (string)($row['object_type'] ?? '');
        $objectId = (int)($row['object_id'] ?? 0);
        if ($sid <= 0 || $type === '' || $objectId <= 0) continue;
        $row['object_label'] = (string)($labels[$type][$objectId] ?? ('#' . $objectId));
        $row['object_pretty_id'] = (string)($pretty[$type][$objectId] ?? '');
        $row['type_label'] = hg_abs_type_label($type);
        $row['public_url'] = hg_abs_public_url($type, (string)($row['object_pretty_id'] ?? ''), $objectId);
        if (!isset($map[$sid])) $map[$sid] = [];
        $map[$sid][] = $row;
    }
    return $map;
}

$hasAddedAt = hg_table_has_column($link, 'dim_soundtracks', 'added_at');
$hasCreatedAt = hg_table_has_column($link, 'dim_soundtracks', 'created_at');
$hasUpdatedAt = hg_table_has_column($link, 'dim_soundtracks', 'updated_at');
$hasContextTitle = hg_table_has_column($link, 'dim_soundtracks', 'context_title');
$hasArtist = hg_table_has_column($link, 'dim_soundtracks', 'artist');
$hasYoutubeUrl = hg_table_has_column($link, 'dim_soundtracks', 'youtube_url');
$hasBridge = hg_table_has_column($link, 'bridge_soundtrack_links', 'soundtrack_id');
$hasBridgeObjectType = hg_table_has_column($link, 'bridge_soundtrack_links', 'object_type');

if (!$isAjaxRequest): ?>
<link rel="stylesheet" href="/assets/vendor/select2/select2.min.4.1.0.css">
<script src="/assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="/assets/vendor/select2/select2.min.4.1.0.js"></script>
<?php endif;

$actions = '<span class="adm-flex-right-8"><button class="btn btn-green" type="button" onclick="openBsoModal()">+ Nuevo tema</button><label class="adm-text-left">Vista <select class="select" id="bsoStateFilter"><option value="all">Todos</option><option value="without-links">Sin vinculos</option><option value="legacy">Con legacy</option><option value="youtube">YouTube pendiente</option></select></label><label class="adm-text-left">Filtro rapido <input class="inp" type="text" id="quickFilterBso" placeholder="En esta pagina..."></label></span>';
if (!$isAjaxRequest) admin_panel_open('Banda Sonora', $actions);

$flash = [];
$ajaxOptions = [];
$ajaxLinkObjectType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action'])) {
    if ($isAjaxRequest && function_exists('hg_admin_require_session')) hg_admin_require_session(true);
    if (!hg_abs_csrf_ok()) {
        $flash[] = ['type' => 'error', 'msg' => 'CSRF invalido. Recarga la pagina.'];
    } else {
        $action = (string)($_POST['crud_action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'delete') {
            if ($id <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'ID invalido para eliminar.'];
            } else {
                $deps = [
                    ['key' => 'links', 'label' => 'Vinculos BSO', 'count' => hg_abs_bridge_total($link, $id)],
                ];
                if (hg_admin_catalog_dependencies_total($deps) > 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'No se puede borrar el tema porque tiene vinculos activos: ' . hg_admin_catalog_dependencies_summary($deps) . '.'];
                } elseif ($st = $link->prepare('DELETE FROM dim_soundtracks WHERE id = ?')) {
                    $st->bind_param('i', $id);
                    if ($st->execute()) $flash[] = ['type' => 'ok', 'msg' => 'Tema eliminado.'];
                    else { hg_runtime_log_error('admin_bso.delete', $st->error); $flash[] = ['type' => 'error', 'msg' => 'No se pudo eliminar el tema.']; }
                    $st->close();
                } else {
                    hg_runtime_log_error('admin_bso.delete.prepare', $link->error);
                    $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar el borrado del tema.'];
                }
            }
        }
        if ($action === 'add_link') {
            $soundtrackId = $id > 0 ? $id : (int)($_POST['soundtrack_id'] ?? 0);
            $objectType = trim((string)($_POST['link_object_type'] ?? ''));
            $objectId = (int)($_POST['link_object_id'] ?? 0);
            if ($soundtrackId <= 0 || !in_array($objectType, ['personaje', 'temporada', 'episodio'], true) || $objectId <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'Datos de vinculo invalidos.'];
            } elseif (!hg_table_has_column($link, 'bridge_soundtrack_links', 'soundtrack_id')) {
                $flash[] = ['type' => 'error', 'msg' => 'La tabla de vinculos BSO no esta disponible en este entorno.'];
            } elseif (!hg_abs_link_object_exists($link, $objectType, $objectId)) {
                $flash[] = ['type' => 'error', 'msg' => 'El destino seleccionado ya no existe.'];
            } elseif (hg_abs_soundtrack_link_exists($link, $soundtrackId, $objectType, $objectId)) {
                $flash[] = ['type' => 'error', 'msg' => 'Ese vinculo ya existe para el tema.'];
            } else {
                $hasLinkCreatedAt = hg_table_has_column($link, 'bridge_soundtrack_links', 'created_at');
                $hasLinkUpdatedAt = hg_table_has_column($link, 'bridge_soundtrack_links', 'updated_at');
                $cols = ['soundtrack_id', 'object_type', 'object_id'];
                $vals = [$soundtrackId, $objectType, $objectId];
                $types = 'isi';
                if ($hasLinkCreatedAt) $cols[] = 'created_at';
                if ($hasLinkUpdatedAt) $cols[] = 'updated_at';
                $ph = [];
                foreach ($cols as $col) $ph[] = ($col === 'created_at' || $col === 'updated_at') ? 'NOW()' : '?';
                $sql = "INSERT INTO bridge_soundtrack_links (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $ph) . ")";
                if ($st = $link->prepare($sql)) {
                    $st->bind_param($types, ...$vals);
                    if ($st->execute()) $flash[] = ['type' => 'ok', 'msg' => 'Vinculo anadido al tema.'];
                    else {
                        hg_runtime_log_error('admin_bso.add_link', $st->error);
                        $flash[] = ['type' => 'error', 'msg' => 'No se pudo anadir el vinculo al tema.'];
                    }
                    $st->close();
                } else {
                    hg_runtime_log_error('admin_bso.add_link.prepare', $link->error);
                    $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar el alta del vinculo.'];
                }
            }
        }
        if ($action === 'delete_link') {
            $linkId = (int)($_POST['link_id'] ?? 0);
            if ($linkId <= 0) {
                $flash[] = ['type' => 'error', 'msg' => 'ID de vinculo invalido.'];
            } elseif ($st = $link->prepare('DELETE FROM bridge_soundtrack_links WHERE id = ?')) {
                $st->bind_param('i', $linkId);
                if ($st->execute()) $flash[] = ['type' => 'ok', 'msg' => 'Vinculo eliminado del tema.'];
                else {
                    hg_runtime_log_error('admin_bso.delete_link', $st->error);
                    $flash[] = ['type' => 'error', 'msg' => 'No se pudo eliminar el vinculo del tema.'];
                }
                $st->close();
            } else {
                hg_runtime_log_error('admin_bso.delete_link.prepare', $link->error);
                $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar el borrado del vinculo.'];
            }
        }
        if ($action === 'fetch_link_options') {
            $ajaxLinkObjectType = trim((string)($_POST['link_object_type'] ?? ''));
            if (!in_array($ajaxLinkObjectType, ['personaje', 'temporada', 'episodio'], true)) {
                $flash[] = ['type' => 'error', 'msg' => 'Tipo de vinculo invalido.'];
            } else {
                $allOptions = hg_abs_fetch_link_options($link);
                $ajaxOptions = array_values((array)($allOptions[$ajaxLinkObjectType] ?? []));
                $flash[] = ['type' => 'ok', 'msg' => 'Opciones cargadas.'];
            }
        }
        if ($action === 'create' || $action === 'update') {
            $title = trim((string)($_POST['title'] ?? ''));
            $artist = $hasArtist ? trim((string)($_POST['artist'] ?? '')) : '';
            $contextTitle = $hasContextTitle ? trim((string)($_POST['context_title'] ?? '')) : '';
            $youtubeRaw = $hasYoutubeUrl ? trim((string)($_POST['youtube_url'] ?? '')) : '';
            $youtubeUrl = $hasYoutubeUrl ? hg_abs_normalize_youtube($youtubeRaw) : '';
            $addedAt = $hasAddedAt ? trim((string)($_POST['added_at'] ?? '')) : '';

            if ($title === '') {
                $flash[] = ['type' => 'error', 'msg' => 'El titulo es obligatorio.'];
            } elseif ($hasYoutubeUrl && $youtubeUrl === '') {
                $flash[] = ['type' => 'error', 'msg' => 'El enlace o ID de YouTube no es valido.'];
            } elseif ($hasYoutubeUrl && hg_abs_soundtrack_exists($link, $title, $artist, $youtubeUrl, $id)) {
                $flash[] = ['type' => 'error', 'msg' => 'Ya existe otro tema con ese titulo, artista y enlace.'];
            } elseif ($action === 'create') {
                $cols = ['title'];
                $vals = [$title];
                $types = 's';
                if ($hasArtist) { $cols[] = 'artist'; $vals[] = $artist; $types .= 's'; }
                if ($hasYoutubeUrl) { $cols[] = 'youtube_url'; $vals[] = $youtubeUrl; $types .= 's'; }
                if ($hasContextTitle) { $cols[] = 'context_title'; $vals[] = $contextTitle; $types .= 's'; }
                if ($hasAddedAt && preg_match('/^\d{4}-\d{2}-\d{2}$/', $addedAt)) { $cols[] = 'added_at'; $vals[] = $addedAt; $types .= 's'; }
                if ($hasCreatedAt) $cols[] = 'created_at';
                if ($hasUpdatedAt) $cols[] = 'updated_at';
                $ph = [];
                foreach ($cols as $col) $ph[] = ($col === 'created_at' || $col === 'updated_at') ? 'NOW()' : '?';
                $sql = "INSERT INTO dim_soundtracks (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $ph) . ")";
                if ($st = $link->prepare($sql)) {
                    $st->bind_param($types, ...$vals);
                    if ($st->execute()) {
                        hg_update_pretty_id_if_exists($link, 'dim_soundtracks', (int)$link->insert_id, $title);
                        $flash[] = ['type' => 'ok', 'msg' => 'Tema creado.'];
                    } else {
                        hg_runtime_log_error('admin_bso.create', $st->error);
                        $flash[] = ['type' => 'error', 'msg' => 'No se pudo crear el tema.'];
                    }
                    $st->close();
                } else {
                    hg_runtime_log_error('admin_bso.create.prepare', $link->error);
                    $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar el alta del tema.'];
                }
            } else {
                if ($id <= 0) {
                    $flash[] = ['type' => 'error', 'msg' => 'ID invalido para actualizar.'];
                } else {
                    $sets = ['`title` = ?'];
                    $vals = [$title];
                    $types = 's';
                    if ($hasArtist) { $sets[] = '`artist` = ?'; $vals[] = $artist; $types .= 's'; }
                    if ($hasYoutubeUrl) { $sets[] = '`youtube_url` = ?'; $vals[] = $youtubeUrl; $types .= 's'; }
                    if ($hasContextTitle) { $sets[] = '`context_title` = ?'; $vals[] = $contextTitle; $types .= 's'; }
                    if ($hasAddedAt && preg_match('/^\d{4}-\d{2}-\d{2}$/', $addedAt)) { $sets[] = '`added_at` = ?'; $vals[] = $addedAt; $types .= 's'; }
                    if ($hasUpdatedAt) $sets[] = '`updated_at` = NOW()';
                    $vals[] = $id;
                    $types .= 'i';
                    $sql = "UPDATE dim_soundtracks SET " . implode(', ', $sets) . " WHERE id = ?";
                    if ($st = $link->prepare($sql)) {
                        $st->bind_param($types, ...$vals);
                        if ($st->execute()) {
                            hg_update_pretty_id_if_exists($link, 'dim_soundtracks', $id, $title);
                            $flash[] = ['type' => 'ok', 'msg' => 'Tema actualizado.'];
                        } else {
                            hg_runtime_log_error('admin_bso.update', $st->error);
                            $flash[] = ['type' => 'error', 'msg' => 'No se pudo actualizar el tema.'];
                        }
                        $st->close();
                    } else {
                        hg_runtime_log_error('admin_bso.update.prepare', $link->error);
                        $flash[] = ['type' => 'error', 'msg' => 'No se pudo preparar la actualizacion del tema.'];
                    }
                }
            }
        }
    }
}

$select = [
    's.id',
    "COALESCE(s.title, '') AS title",
    $hasArtist ? "COALESCE(s.artist, '') AS artist" : "'' AS artist",
    $hasContextTitle ? "COALESCE(s.context_title, '') AS context_title" : "'' AS context_title",
    $hasYoutubeUrl ? "COALESCE(s.youtube_url, '') AS youtube_url" : "'' AS youtube_url",
    $hasAddedAt ? "COALESCE(CAST(s.added_at AS CHAR), '') AS added_at" : "'' AS added_at",
    ($hasBridge && $hasBridgeObjectType) ? "(SELECT COUNT(*) FROM bridge_soundtrack_links b WHERE b.soundtrack_id = s.id AND b.object_type = 'personaje') AS characters_count" : '0 AS characters_count',
    ($hasBridge && $hasBridgeObjectType) ? "(SELECT COUNT(*) FROM bridge_soundtrack_links b WHERE b.soundtrack_id = s.id AND b.object_type = 'temporada') AS seasons_count" : '0 AS seasons_count',
    ($hasBridge && $hasBridgeObjectType) ? "(SELECT COUNT(*) FROM bridge_soundtrack_links b WHERE b.soundtrack_id = s.id AND b.object_type = 'episodio') AS chapters_count" : '0 AS chapters_count',
    $hasBridge ? "(SELECT COUNT(*) FROM bridge_soundtrack_links b WHERE b.soundtrack_id = s.id) AS links_count" : '0 AS links_count',
];

$rows = [];
$rowsFull = [];
$auditBsoCount = 0;
$auditBsoYoutubeCount = 0;
$bsoWithoutLinksCount = 0;
$rs = $link->query('SELECT ' . implode(', ', $select) . ' FROM dim_soundtracks s ORDER BY ' . ($hasAddedAt ? 's.added_at DESC, ' : '') . 's.id DESC');
if ($rs) {
    while ($row = $rs->fetch_assoc()) {
        $row['dependency_summary'] = hg_abs_dep_summary($row);
        $row['youtube_id'] = hg_abs_youtube_id((string)($row['youtube_url'] ?? ''));
        $row['audit_flags'] = hg_phase7_soundtrack_flags($row);
        $row['audit_summary'] = hg_phase7_build_flags_summary((array)$row['audit_flags']);
        $row['audit_class'] = hg_abs_audit_class((array)$row['audit_flags']);
        if (!empty($row['audit_flags'])) $auditBsoCount++;
        if (in_array('Sin YouTube', (array)$row['audit_flags'], true) || in_array('YouTube no normalizado', (array)$row['audit_flags'], true)) $auditBsoYoutubeCount++;
        if ((int)($row['links_count'] ?? 0) <= 0) $bsoWithoutLinksCount++;
        $rows[] = $row;
        $rowsFull[] = $row;
    }
    $rs->close();
}
$linkOptions = $hasBridge ? hg_abs_fetch_link_options($link) : ['personaje' => [], 'temporada' => [], 'episodio' => []];
$linksBySoundtrack = $hasBridge ? hg_abs_fetch_links_by_soundtrack($link) : [];
foreach ($rows as &$row) {
    $sid = (int)($row['id'] ?? 0);
    $row['links'] = $linksBySoundtrack[$sid] ?? [];
}
unset($row);
foreach ($rowsFull as &$row) {
    $sid = (int)($row['id'] ?? 0);
    $row['links'] = $linksBySoundtrack[$sid] ?? [];
}
unset($row);

$ajaxWrite = $isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crud_action']);
if ($ajaxWrite) {
    $errors = [];
    $messages = [];
    foreach ($flash as $m) {
        $msg = (string)($m['msg'] ?? '');
        if ($msg === '') continue;
        if ((string)($m['type'] ?? '') === 'error') $errors[] = $msg; else $messages[] = $msg;
    }
    $payloadData = ['rows' => $rows, 'rowsFull' => $rowsFull, 'messages' => $messages, 'linkOptions' => $linkOptions];
    if ($ajaxLinkObjectType !== '') {
        $payloadData['linkObjectType'] = $ajaxLinkObjectType;
        $payloadData['options'] = $ajaxOptions;
    }
    if (!empty($errors)) {
        if (function_exists('hg_admin_json_error')) hg_admin_json_error($errors[0], 400, ['flash' => $errors], $payloadData);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => $errors[0], 'errors' => $errors, 'data' => $payloadData], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $okMsg = !empty($messages) ? $messages[count($messages) - 1] : 'Guardado';
    if (function_exists('hg_admin_json_success')) hg_admin_json_success($payloadData, $okMsg);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'message' => $okMsg, 'msg' => $okMsg, 'data' => $payloadData], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
?>
<?php if (!empty($flash)): ?><div class="flash"><?php foreach ($flash as $m): $cl = $m['type'] === 'ok' ? 'ok' : (($m['type'] ?? '') === 'error' ? 'err' : 'info'); ?><div class="<?= $cl ?>"><?= hg_abs_h($m['msg'] ?? '') ?></div><?php endforeach; ?></div><?php endif; ?>
<style>.adm-dep-badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #1b4aa0;background:#00135a;color:#dff7ff;font-size:10px;line-height:1.2}.adm-dep-badge.off{background:#2a2a2a;border-color:#555;color:#ddd}.adm-bso-link{color:#9fe7ff}.adm-table-wrap{max-height:72vh;overflow:auto;border:1px solid #000088;border-radius:8px}.adm-bso-table .table th,.adm-bso-table .table td{vertical-align:top}.adm-bso-title{min-width:220px}.adm-bso-artist{min-width:160px}.adm-bso-usage{min-width:180px}.adm-bso-context{min-width:180px;max-width:260px}.adm-bso-youtube{min-width:180px;max-width:260px}.adm-bso-links-box{margin-top:14px;border-top:1px solid #000088;padding-top:12px}.adm-bso-links-list{display:flex;flex-direction:column;gap:8px;margin-top:8px}.adm-bso-link-item{display:flex;gap:8px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;border:1px solid #15306b;border-radius:8px;padding:8px;background:#06164a}.adm-bso-link-meta{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.adm-bso-link-type{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #1b4aa0;background:#00135a;color:#dff7ff;font-size:10px;line-height:1.2}.adm-bso-links-empty{color:#bbb;font-size:12px}.adm-bso-link-add{margin-top:10px;display:grid;grid-template-columns:140px 1fr auto;gap:8px;align-items:end}.adm-bso-link-add .btn{white-space:nowrap}.adm-summary-band{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 12px 0}.adm-summary-pill{padding:5px 10px;border-radius:999px;border:1px solid #17366e;background:#071b4a;color:#dfefff}.adm-audit-badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #2d6a9f;background:#0a2147;color:#e8f5ff;font-size:10px;line-height:1.2}.adm-audit-badge.review{background:#4a3200;border-color:#b37a11;color:#ffefbf}.adm-audit-badge.warn{background:#4a0000;border-color:#b31111;color:#ffd7d7}#bsoModal .select2-container{width:100%!important;font-size:12px}#bsoModal .select2-container--default .select2-selection--single{height:30px;border:1px solid #000088;background:#05014E;color:#fff;border-radius:4px}#bsoModal .select2-container--default .select2-selection--single .select2-selection__rendered{color:#fff;line-height:28px;padding-left:10px;padding-right:28px}#bsoModal .select2-container--default .select2-selection--single .select2-selection__placeholder{color:#9dd}#bsoModal .select2-container--default .select2-selection--single .select2-selection__arrow{height:28px}#bsoModal .select2-container--default .select2-selection--single .select2-selection__arrow b{border-color:#9fd8ff transparent transparent transparent!important}#bsoModal .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b{border-color:transparent transparent #9fd8ff transparent!important}#bsoModal .select2-dropdown{background:#05014E!important;border:1px solid #000088!important;color:#fff!important;z-index:30000}#bsoModal .select2-search--dropdown{padding:8px}#bsoModal .select2-search--dropdown .select2-search__field{background:#00105f!important;border:1px solid #33FFFF!important;color:#fff!important;border-radius:4px;padding:6px 8px}#bsoModal .select2-results__option{background:transparent!important;color:#fff!important;white-space:normal;overflow-wrap:anywhere;line-height:1.35;padding:8px 10px}#bsoModal .select2-container--default .select2-results__option--selected{background:#00105f!important;color:#fff!important}#bsoModal .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable{background:#0a56d1!important;color:#fff!important}#bsoModal .select2-results>.select2-results__options{max-height:320px!important}</style>
<div class="adm-summary-band"><span class="adm-summary-pill">Temas auditados: <?= (int)count($rows) ?></span><span class="adm-summary-pill">Con legacy pendiente: <?= (int)$auditBsoCount ?></span><span class="adm-summary-pill">Con revision de YouTube: <?= (int)$auditBsoYoutubeCount ?></span><span class="adm-summary-pill">Sin vinculos: <?= (int)$bsoWithoutLinksCount ?></span></div>
<div class="modal-back" id="bsoModal"><div class="modal"><h3 id="bsoModalTitle">Nuevo tema</h3><form method="post" id="bsoForm"><input type="hidden" name="csrf" value="<?= hg_abs_h($csrf) ?>"><input type="hidden" name="crud_action" id="bso_action" value="create"><input type="hidden" name="id" id="bso_id" value="0"><div class="modal-body"><div class="adm-grid-1-2"><label>Titulo</label><input class="inp" type="text" name="title" id="bso_title" maxlength="255" required><?php if ($hasArtist): ?><label>Artista</label><input class="inp" type="text" name="artist" id="bso_artist" maxlength="255"><?php endif; ?><?php if ($hasContextTitle): ?><label>Titulo HG</label><input class="inp" type="text" name="context_title" id="bso_context_title" maxlength="255"><?php endif; ?><?php if ($hasYoutubeUrl): ?><label>YouTube</label><input class="inp" type="text" name="youtube_url" id="bso_youtube_url" maxlength="255" placeholder="ID o URL completa"><?php endif; ?><?php if ($hasAddedAt): ?><label>Fecha</label><input class="inp" type="date" name="added_at" id="bso_added_at"><?php endif; ?></div><div class="adm-bso-links-box adm-hidden" id="bsoLinksBox"><h4 class="adm-title-sm">Vinculos del tema</h4><div class="adm-bso-links-list" id="bsoLinksList"></div><div class="adm-bso-link-add"><div><label>Tipo</label><select class="inp" id="bso_link_type_inline"><option value="">Seleccionar tipo</option><option value="personaje">Personaje</option><option value="temporada">Temporada</option><option value="episodio">Episodio</option></select></div><div><label>Destino</label><select class="inp" id="bso_link_object_inline"><option value="">Selecciona primero el tipo</option></select></div><div><button class="btn" type="button" id="bsoAddLinkBtn">Anadir vinculo</button></div></div></div></div><div class="modal-actions"><button class="btn btn-green" type="submit">Guardar</button><button class="btn" type="button" onclick="closeBsoModal()">Cancelar</button></div></form></div></div>
<div class="modal-back" id="bsoDeleteModal"><div class="modal adm-modal-sm"><h3>Confirmar borrado</h3><div class="adm-help-text" id="bsoDeleteHelp">Se eliminara el tema seleccionado.</div><form method="post" id="bsoDeleteForm" class="adm-m-0"><input type="hidden" name="csrf" value="<?= hg_abs_h($csrf) ?>"><input type="hidden" name="crud_action" value="delete"><input type="hidden" name="id" id="bso_delete_id" value="0"><div class="modal-actions"><button type="button" class="btn" onclick="closeBsoDeleteModal()">Cancelar</button><button type="submit" class="btn btn-red">Borrar</button></div></form></div></div>
<div class="adm-table-wrap adm-bso-table"><table class="table" id="tablaBso"><thead><tr><th class="adm-w-60">ID</th><th class="adm-bso-title adm-cell-wrap">Titulo</th><th class="adm-bso-artist adm-cell-wrap">Artista</th><th class="adm-bso-usage adm-cell-wrap">Vinculado a</th><th class="adm-w-220">Revision</th><th class="adm-bso-context adm-cell-wrap">Titulo HG</th><th class="adm-bso-youtube adm-cell-wrap">YouTube</th><?php if ($hasAddedAt): ?><th class="adm-w-120">Fecha</th><?php endif; ?><th class="adm-w-160 adm-th-actions">Acciones</th></tr></thead><tbody id="bsoTbody"><?php foreach ($rows as $row): $search = trim((string)($row['title'] ?? '') . ' ' . (string)($row['artist'] ?? '') . ' ' . (string)($row['context_title'] ?? '') . ' ' . (string)($row['youtube_url'] ?? '') . ' ' . (string)($row['dependency_summary'] ?? '') . ' ' . (string)($row['audit_summary'] ?? '')); if (function_exists('mb_strtolower')) $search = mb_strtolower($search, 'UTF-8'); else $search = strtolower($search); $linksCount = (int)($row['links_count'] ?? 0); $auditClass = trim((string)($row['audit_class'] ?? 'ok')); $youtubePending = in_array('Sin YouTube', (array)($row['audit_flags'] ?? []), true) || in_array('YouTube no normalizado', (array)($row['audit_flags'] ?? []), true); ?><tr data-search="<?= hg_abs_h($search) ?>" data-links="<?= $linksCount ?>" data-legacy="<?= !empty($row['audit_flags']) ? '1' : '0' ?>" data-youtube-pending="<?= $youtubePending ? '1' : '0' ?>"><td><?= (int)$row['id'] ?></td><td class="adm-cell-wrap"><?= hg_abs_h((string)($row['title'] ?? '')) ?></td><td class="adm-cell-wrap"><?= hg_abs_h((string)($row['artist'] ?? '')) ?></td><td class="adm-cell-wrap"><span class="adm-dep-badge <?= $linksCount <= 0 ? 'off' : '' ?>"><?= hg_abs_h((string)($row['dependency_summary'] ?? 'Sin usos')) ?></span></td><td><span class="adm-audit-badge <?= hg_abs_h($auditClass) ?>"><?= hg_abs_h((string)($row['audit_summary'] ?? 'OK')) ?></span></td><td class="adm-cell-wrap"><?= hg_abs_h((string)($row['context_title'] ?? '')) ?></td><td class="adm-cell-wrap"><?php $yt = trim((string)($row['youtube_url'] ?? '')); if ($yt !== ''): ?><a class="adm-bso-link" href="<?= hg_abs_h($yt) ?>" target="_blank" rel="noopener noreferrer"><?= hg_abs_h(hg_abs_short($yt, 64)) ?></a><?php else: ?>-<?php endif; ?></td><?php if ($hasAddedAt): ?><td><?= hg_abs_h((string)($row['added_at'] ?? '')) ?></td><?php endif; ?><td class="adm-cell-actions"><div class="adm-actions-inline"><button class="btn" type="button" data-edit="<?= (int)$row['id'] ?>">Editar / vincular</button><button class="btn btn-red" type="button" data-del="<?= (int)$row['id'] ?>">Borrar</button></div></td></tr><?php endforeach; ?><?php if (empty($rows)): ?><tr><td colspan="<?= 8 + ($hasAddedAt ? 1 : 0) ?>" class="adm-color-muted">(Sin temas)</td></tr><?php endif; ?></tbody></table></div>
<?php $adminHttpJs = '/assets/js/admin/admin-http.js'; $adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time(); ?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($csrf, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= hg_abs_h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
let bsoData = <?= json_encode($rowsFull, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
let bsoLinkOptions = <?= json_encode($linkOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
let bsoSelect2Ready = false;
function bsoRequest(url, options){ return (window.HGAdminHttp && window.HGAdminHttp.request) ? window.HGAdminHttp.request(url, options || {}) : fetch(url, options || {}).then(async r => { const p = await r.json(); if (!r.ok || (p && p.ok === false)) { const e = new Error((p && (p.message || p.msg)) || ('HTTP ' + r.status)); e.status = r.status; e.payload = p; throw e; } return p; }); }
function bsoUrl(){ const url = new URL(window.location.href); url.searchParams.set('s', 'admin_bso'); url.searchParams.set('ajax', '1'); url.searchParams.set('_ts', Date.now()); return url.toString(); }
function bsoEsc(text){ return String(text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
function bsoShort(text, max){ const clean = String(text || '').replace(/\s+/g, ' ').trim(); return clean.length <= max ? clean : (clean.slice(0, max) + '...'); }
function bsoSummary(row){ const parts = []; const c = parseInt(row.characters_count || 0, 10) || 0; const s = parseInt(row.seasons_count || 0, 10) || 0; const e = parseInt(row.chapters_count || 0, 10) || 0; if (c > 0) parts.push(c + ' personajes'); if (s > 0) parts.push(s + ' temporadas'); if (e > 0) parts.push(e + ' episodios'); return parts.length ? parts.join(' | ') : 'Sin usos'; }
function bsoAuditSummary(row){ const flags = Array.isArray(row.audit_flags) ? row.audit_flags.filter(Boolean) : []; return flags.length ? flags.join(' | ') : 'OK'; }
function bsoAuditClass(row){ const cls = String(row.audit_class || '').trim(); return cls || (Array.isArray(row.audit_flags) && row.audit_flags.length >= 3 ? 'warn' : (Array.isArray(row.audit_flags) && row.audit_flags.length ? 'review' : 'ok')); }
function refreshBsoSummary(rows){ const list = Array.isArray(rows) ? rows : []; const pills = document.querySelectorAll('.adm-summary-pill'); let legacy = 0; let youtube = 0; let withoutLinks = 0; list.forEach(row => { const flags = Array.isArray(row.audit_flags) ? row.audit_flags : []; if (flags.length) legacy++; if (flags.includes('Sin YouTube') || flags.includes('YouTube no normalizado')) youtube++; if ((parseInt(row.links_count || 0, 10) || 0) <= 0) withoutLinks++; }); if (pills[0]) pills[0].textContent = 'Temas auditados: ' + list.length; if (pills[1]) pills[1].textContent = 'Con legacy pendiente: ' + legacy; if (pills[2]) pills[2].textContent = 'Con revision de YouTube: ' + youtube; if (pills[3]) pills[3].textContent = 'Sin vinculos: ' + withoutLinks; }
function currentBsoRow(){ const id = parseInt(document.getElementById('bso_id').value || '0', 10) || 0; return bsoData.find(r => parseInt(r.id || 0, 10) === id) || null; }
function bsoSelect2Parent(){ return window.jQuery ? window.jQuery('#bsoModal').first() : null; }
function onBsoSelectChange(el, handler){
  if (!el) return;
  if (window.jQuery) {
    window.jQuery(el).off('change.hg').on('change.hg', handler);
  } else {
    el.addEventListener('change', handler);
  }
}
function initBsoTypeSelect2(){
  if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
  const $type = window.jQuery('#bso_link_type_inline');
  const parent = bsoSelect2Parent();
  if ($type.hasClass('select2-hidden-accessible')) $type.select2('destroy');
  $type.select2({ dropdownParent: parent, placeholder: 'Seleccionar tipo', width: '100%', minimumResultsForSearch: 0, allowClear: true });
}
function initBsoObjectSelect2(forceObjectReset = false){
  if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
  const $object = window.jQuery('#bso_link_object_inline');
  const parent = bsoSelect2Parent();
  if ($object.hasClass('select2-hidden-accessible')) $object.select2('destroy');
  $object.select2({ dropdownParent: parent, placeholder: 'Seleccionar destino', width: '100%', minimumResultsForSearch: 0, allowClear: true });
  if (forceObjectReset) $object.val(null).trigger('change.select2');
  bsoSelect2Ready = true;
}
function initBsoSelect2(forceObjectReset = false){ initBsoTypeSelect2(); initBsoObjectSelect2(forceObjectReset); }
function populateInlineLinkObjects(type, reinit = true){ const select = document.getElementById('bso_link_object_inline'); if (!select) return; const rows = Array.isArray(bsoLinkOptions[type]) ? bsoLinkOptions[type] : []; let html = '<option value=""></option>'; rows.forEach(row => { const id = parseInt(row.id || 0, 10) || 0; html += '<option value="' + id + '">' + bsoEsc(row.label || ('#' + id)) + '</option>'; }); select.innerHTML = html; if (reinit) initBsoObjectSelect2(true); }
function loadInlineLinkObjects(type){
  const select = document.getElementById('bso_link_object_inline');
  if (!select) return Promise.resolve([]);
  if (!type) { populateInlineLinkObjects('', true); return Promise.resolve([]); }
  select.innerHTML = '<option value="">Cargando opciones...</option>';
  initBsoObjectSelect2(true);
  const fd = new FormData();
  fd.set('csrf', window.ADMIN_CSRF_TOKEN || '');
  fd.set('crud_action', 'fetch_link_options');
  fd.set('link_object_type', type);
  fd.set('ajax', '1');
  return bsoRequest(bsoUrl(), { method: 'POST', body: fd }).then(payload => {
    const data = payload && payload.data ? payload.data : {};
    const objectType = String(data.linkObjectType || type || '');
    const options = Array.isArray(data.options) ? data.options : (data.linkOptions && Array.isArray(data.linkOptions[objectType]) ? data.linkOptions[objectType] : []);
    bsoLinkOptions[objectType] = options;
    populateInlineLinkObjects(objectType, true);
    return options;
  }).catch(err => {
    populateInlineLinkObjects(type, true);
    const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al cargar destinos');
    alert(msg);
    return [];
  });
}
function renderBsoLinksPanel(row){ const box = document.getElementById('bsoLinksBox'); const list = document.getElementById('bsoLinksList'); if (!box || !list) return; const id = parseInt(row && row.id ? row.id : 0, 10) || 0; if (id <= 0) { box.classList.add('adm-hidden'); list.innerHTML = ''; return; } box.classList.remove('adm-hidden'); const links = Array.isArray(row.links) ? row.links : []; if (!links.length) { list.innerHTML = '<div class="adm-bso-links-empty">Este tema no tiene vinculos todavia.</div>'; return; } let html = ''; links.forEach(link => { const linkId = parseInt(link.id || 0, 10) || 0; const publicUrl = String(link.public_url || '').trim(); const openLink = publicUrl ? ('<a href="' + bsoEsc(publicUrl) + '" target="_blank" rel="noopener">Abrir</a>') : ''; html += '<div class="adm-bso-link-item"><div class="adm-bso-link-meta"><span class="adm-bso-link-type">' + bsoEsc(link.type_label || link.object_type || '') + '</span><span class="adm-cell-wrap">' + bsoEsc(link.object_label || '') + '</span>' + openLink + '</div><button class="btn btn-red" type="button" data-link-del="' + linkId + '">Quitar</button></div>'; }); list.innerHTML = html; document.querySelectorAll('#bsoLinksList [data-link-del]').forEach(btn => btn.onclick = () => deleteBsoLink(parseInt(btn.getAttribute('data-link-del') || '0', 10) || 0)); }
function openBsoModal(id = null){ document.getElementById('bso_action').value = 'create'; document.getElementById('bso_id').value = '0'; document.getElementById('bso_title').value = ''; const artist = document.getElementById('bso_artist'); if (artist) artist.value = ''; const ctx = document.getElementById('bso_context_title'); if (ctx) ctx.value = ''; const yt = document.getElementById('bso_youtube_url'); if (yt) yt.value = ''; const added = document.getElementById('bso_added_at'); if (added) added.value = ''; document.getElementById('bso_link_type_inline').value = ''; populateInlineLinkObjects('', false); renderBsoLinksPanel(null); if (id) { const row = bsoData.find(r => parseInt(r.id || 0, 10) === parseInt(id || 0, 10)); if (row) { document.getElementById('bsoModalTitle').textContent = 'Editar tema'; document.getElementById('bso_action').value = 'update'; document.getElementById('bso_id').value = String(row.id || 0); document.getElementById('bso_title').value = row.title || ''; if (artist) artist.value = row.artist || ''; if (ctx) ctx.value = row.context_title || ''; if (yt) yt.value = row.youtube_url || ''; if (added) added.value = row.added_at || ''; renderBsoLinksPanel(row); } } else { document.getElementById('bsoModalTitle').textContent = 'Nuevo tema'; } document.getElementById('bsoModal').style.display = 'flex'; initBsoSelect2(false); if (window.jQuery && bsoSelect2Ready) { window.jQuery('#bso_link_type_inline').val('').trigger('change.select2'); window.jQuery('#bso_link_object_inline').val('').trigger('change.select2'); } }
function closeBsoModal(){ document.getElementById('bsoModal').style.display = 'none'; }
function openBsoDeleteModal(id){ const row = bsoData.find(r => parseInt(r.id || 0, 10) === parseInt(id || 0, 10)) || null; document.getElementById('bso_delete_id').value = String(parseInt(id || 0, 10) || 0); document.getElementById('bsoDeleteHelp').textContent = row ? ('Usos detectados: ' + bsoSummary(row) + '. El servidor bloqueara el borrado si sigue habiendo vinculos activos.') : 'Se eliminara el tema seleccionado.'; document.getElementById('bsoDeleteModal').style.display = 'flex'; }
function closeBsoDeleteModal(){ document.getElementById('bsoDeleteModal').style.display = 'none'; }
function bindBsoRows(){ document.querySelectorAll('#bsoTbody [data-edit]').forEach(btn => btn.onclick = () => openBsoModal(parseInt(btn.getAttribute('data-edit') || '0', 10) || 0)); document.querySelectorAll('#bsoTbody [data-del]').forEach(btn => btn.onclick = () => openBsoDeleteModal(parseInt(btn.getAttribute('data-del') || '0', 10) || 0)); }
function renderBsoRows(rows){ const tbody = document.getElementById('bsoTbody'); if (!tbody) return; if (!rows || !rows.length) { tbody.innerHTML = '<tr><td colspan="<?= 8 + ($hasAddedAt ? 1 : 0) ?>" class="adm-color-muted">(Sin temas)</td></tr>'; refreshBsoSummary([]); bindBsoRows(); return; } let html = ''; rows.forEach(row => { const id = parseInt(row.id || 0, 10) || 0; const summary = bsoSummary(row); const auditSummary = bsoAuditSummary(row); const auditClass = bsoAuditClass(row); const links = parseInt(row.links_count || 0, 10) || 0; const flags = Array.isArray(row.audit_flags) ? row.audit_flags : []; const youtubePending = flags.includes('Sin YouTube') || flags.includes('YouTube no normalizado'); const search = (String(row.title || '') + ' ' + String(row.artist || '') + ' ' + String(row.context_title || '') + ' ' + String(row.youtube_url || '') + ' ' + summary + ' ' + auditSummary).toLowerCase(); const yt = String(row.youtube_url || '').trim(); const ytCell = yt ? ('<a class="adm-bso-link" href="' + bsoEsc(yt) + '" target="_blank" rel="noopener noreferrer">' + bsoEsc(bsoShort(yt, 64)) + '</a>') : '-'; html += '<tr data-search="' + bsoEsc(search) + '" data-links="' + links + '" data-legacy="' + (flags.length ? '1' : '0') + '" data-youtube-pending="' + (youtubePending ? '1' : '0') + '"><td>' + id + '</td><td class="adm-cell-wrap">' + bsoEsc(row.title || '') + '</td><td class="adm-cell-wrap">' + bsoEsc(row.artist || '') + '</td><td class="adm-cell-wrap"><span class="adm-dep-badge ' + (links <= 0 ? 'off' : '') + '">' + bsoEsc(summary) + '</span></td><td><span class="adm-audit-badge ' + bsoEsc(auditClass) + '">' + bsoEsc(auditSummary) + '</span></td><td class="adm-cell-wrap">' + bsoEsc(row.context_title || '') + '</td><td class="adm-cell-wrap">' + ytCell + '</td><?php if ($hasAddedAt): ?><td>' + bsoEsc(row.added_at || '') + '</td><?php endif; ?><td class="adm-cell-actions"><div class="adm-actions-inline"><button class="btn" type="button" data-edit="' + id + '">Editar / vincular</button> <button class="btn btn-red" type="button" data-del="' + id + '">Borrar</button></div></td></tr>'; }); tbody.innerHTML = html; refreshBsoSummary(rows); bindBsoRows(); }
function applyBsoFilter(){ const input = document.getElementById('quickFilterBso'); const state = document.getElementById('bsoStateFilter'); const q = input ? (input.value || '').toLowerCase() : ''; const mode = state ? String(state.value || 'all') : 'all'; document.querySelectorAll('#bsoTbody tr').forEach(tr => { const hay = (tr.getAttribute('data-search') || tr.textContent || '').toLowerCase(); const links = parseInt(tr.getAttribute('data-links') || '0', 10) || 0; const legacy = tr.getAttribute('data-legacy') === '1'; const youtubePending = tr.getAttribute('data-youtube-pending') === '1'; let matchesState = true; if (mode === 'without-links') matchesState = links <= 0; else if (mode === 'legacy') matchesState = legacy; else if (mode === 'youtube') matchesState = youtubePending; tr.style.display = (hay.indexOf(q) !== -1 && matchesState) ? '' : 'none'; }); }
document.getElementById('bsoForm').addEventListener('submit', function(ev){ ev.preventDefault(); const fd = new FormData(this); fd.set('ajax', '1'); bsoRequest(bsoUrl(), { method: 'POST', body: fd, loadingEl: this }).then(payload => { const data = payload && payload.data ? payload.data : {}; if (Array.isArray(data.rows)) renderBsoRows(data.rows); if (Array.isArray(data.rowsFull)) bsoData = data.rowsFull; closeBsoModal(); applyBsoFilter(); if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Guardado', 'ok'); }).catch(err => { const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al guardar'); alert(msg); }); });
document.getElementById('bsoDeleteForm').addEventListener('submit', function(ev){ ev.preventDefault(); const fd = new FormData(this); fd.set('ajax', '1'); bsoRequest(bsoUrl(), { method: 'POST', body: fd, loadingEl: this }).then(payload => { const data = payload && payload.data ? payload.data : {}; if (Array.isArray(data.rows)) renderBsoRows(data.rows); if (Array.isArray(data.rowsFull)) bsoData = data.rowsFull; closeBsoDeleteModal(); applyBsoFilter(); if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Eliminado', 'ok'); }).catch(err => { const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al borrar'); alert(msg); }); });
function syncBsoPayload(payload){ const data = payload && payload.data ? payload.data : {}; if (Array.isArray(data.rows)) renderBsoRows(data.rows); if (Array.isArray(data.rowsFull)) bsoData = data.rowsFull; if (data.linkOptions) bsoLinkOptions = data.linkOptions; const row = currentBsoRow(); renderBsoLinksPanel(row); applyBsoFilter(); }
function addBsoLink(){ const row = currentBsoRow(); if (!row || !(parseInt(row.id || 0, 10) > 0)) { alert('Guarda primero el tema antes de anadir vinculos.'); return; } const type = document.getElementById('bso_link_type_inline').value || ''; const objectId = parseInt(document.getElementById('bso_link_object_inline').value || '0', 10) || 0; if (!type || objectId <= 0) { alert('Selecciona tipo y destino para el vinculo.'); return; } const fd = new FormData(); fd.set('csrf', window.ADMIN_CSRF_TOKEN || ''); fd.set('crud_action', 'add_link'); fd.set('id', String(parseInt(row.id || 0, 10) || 0)); fd.set('soundtrack_id', String(parseInt(row.id || 0, 10) || 0)); fd.set('link_object_type', type); fd.set('link_object_id', String(objectId)); fd.set('ajax', '1'); bsoRequest(bsoUrl(), { method: 'POST', body: fd, loadingEl: document.getElementById('bsoAddLinkBtn') }).then(payload => { syncBsoPayload(payload); if (window.jQuery && bsoSelect2Ready) { window.jQuery('#bso_link_object_inline').val(null).trigger('change.select2'); } else { document.getElementById('bso_link_object_inline').value = ''; } if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Vinculo anadido', 'ok'); }).catch(err => { const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al anadir vinculo'); alert(msg); }); }
function deleteBsoLink(linkId){ const row = currentBsoRow(); if (!row || linkId <= 0) return; const fd = new FormData(); fd.set('csrf', window.ADMIN_CSRF_TOKEN || ''); fd.set('crud_action', 'delete_link'); fd.set('id', String(parseInt(row.id || 0, 10) || 0)); fd.set('link_id', String(linkId)); fd.set('ajax', '1'); bsoRequest(bsoUrl(), { method: 'POST', body: fd }).then(payload => { syncBsoPayload(payload); if (window.HGAdminHttp && window.HGAdminHttp.notify) window.HGAdminHttp.notify((payload && (payload.message || payload.msg)) || 'Vinculo eliminado', 'ok'); }).catch(err => { const msg = (window.HGAdminHttp && window.HGAdminHttp.errorMessage) ? window.HGAdminHttp.errorMessage(err) : (err.message || 'Error al eliminar vinculo'); alert(msg); }); }
onBsoSelectChange(document.getElementById('bso_link_type_inline'), function(){ loadInlineLinkObjects(this.value || ''); });
document.getElementById('bsoAddLinkBtn').addEventListener('click', addBsoLink);
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeBsoModal(); closeBsoDeleteModal(); } });
const bsoFilter = document.getElementById('quickFilterBso'); if (bsoFilter) bsoFilter.addEventListener('input', applyBsoFilter);
const bsoStateFilter = document.getElementById('bsoStateFilter'); if (bsoStateFilter) bsoStateFilter.addEventListener('change', applyBsoFilter);
bindBsoRows();
</script>
<?php if (!$isAjaxRequest) admin_panel_close(); ?>
