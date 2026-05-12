<?php
// Admin: catalogo maestro de cartas del Archivo de Mnemogeno.

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');
include_once(__DIR__ . '/../../modules/game_cards/game_cards_catalog.php');

$csrfKey = 'csrf_admin_game_cards';
$csrf = hg_admin_ensure_csrf_token($csrfKey);

if (!function_exists('hg_agc_h')) {
    function hg_agc_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hg_agc_rarity_labels')) {
    function hg_agc_rarity_labels(): array
    {
        return [
            'common' => 'Comun',
            'unusual' => 'Inusual',
            'rare' => 'Raro',
            'epic' => 'Epico',
            'legendary' => 'Legendario',
            'mythic' => 'Mitico',
        ];
    }
}

if (!function_exists('hg_agc_bind_params')) {
    function hg_agc_bind_params(mysqli_stmt $stmt, string $types, array &$params): void
    {
        if ($types === '') {
            return;
        }

        $refs = [];
        $refs[] = $types;
        foreach ($params as $key => &$value) {
            $refs[] = &$value;
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}

if (!function_exists('hg_agc_fetch_all')) {
    function hg_agc_fetch_all(mysqli $link, string $sql, string $types = '', array $params = []): array
    {
        $stmt = $link->prepare($sql);
        if (!$stmt) {
            return [];
        }

        hg_agc_bind_params($stmt, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_agc_fetch_count')) {
    function hg_agc_fetch_count(mysqli $link, string $sql, string $types = '', array $params = []): int
    {
        $stmt = $link->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        hg_agc_bind_params($stmt, $types, $params);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return (int)$count;
    }
}

if (!function_exists('hg_agc_card_payload')) {
    function hg_agc_card_payload(array $row): array
    {
        $typeLabels = hg_gc_type_labels();
        $sourceType = (string)($row['source_type'] ?? '');
        $sourceTable = (string)($row['source_table'] ?? '');
        $sourceId = (int)($row['source_id'] ?? 0);
        $slug = (string)($row['card_slug'] ?? '');

        return [
            'card_id' => (int)($row['card_id'] ?? 0),
            'source_type' => $sourceType,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'type_label' => $typeLabels[$sourceType] ?? $sourceType,
            'card_name' => (string)($row['card_name'] ?? ''),
            'card_slug' => $slug,
            'card_text' => (string)($row['card_text'] ?? ''),
            'card_image_url' => (string)($row['card_image_url'] ?? ''),
            'card_url' => hg_gc_card_url($sourceType, $sourceTable, $sourceId, $slug),
            'card_rarity' => (string)($row['card_rarity'] ?? 'common'),
            'hp_min' => (int)($row['hp_min'] ?? 10),
            'hp_max' => (int)($row['hp_max'] ?? 40),
            'atk_min' => (int)($row['atk_min'] ?? 10),
            'atk_max' => (int)($row['atk_max'] ?? 40),
            'def_min' => (int)($row['def_min'] ?? 10),
            'def_max' => (int)($row['def_max'] ?? 40),
            'is_active' => (int)($row['is_active'] ?? 1),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('hg_agc_fetch_card')) {
    function hg_agc_fetch_card(mysqli $link, int $cardId): ?array
    {
        $rows = hg_agc_fetch_all(
            $link,
            "SELECT card_id, source_type, source_table, source_id, card_name, card_slug, card_text,
                    card_image_url, card_rarity, hp_min, hp_max, atk_min, atk_max, def_min, def_max,
                    is_active, updated_at
             FROM fact_game_card_collection
             WHERE card_id = ?
             LIMIT 1",
            'i',
            [$cardId]
        );

        if (!$rows) {
            return null;
        }

        return hg_agc_card_payload($rows[0]);
    }
}

if (!function_exists('hg_agc_int_input')) {
    function hg_agc_int_input(string $key, int $min, int $max): int
    {
        $raw = $_POST[$key] ?? null;
        if ($raw === null || !is_numeric($raw)) {
            return -1;
        }

        $value = (int)$raw;
        if ($value < $min || $value > $max) {
            return -1;
        }
        return $value;
    }
}

if (!($link instanceof mysqli)) {
    if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
        hg_admin_json_error('Base de datos no disponible', 500);
    }
    admin_panel_open('Cartas del gacha');
    echo "<p class='adm-admin-error'>Base de datos no disponible.</p>";
    admin_panel_close();
    return;
}

if (!hg_gc_table_exists($link, 'fact_game_card_collection')) {
    if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
        hg_admin_json_error('La tabla de cartas no existe', 500);
    }
    admin_panel_open('Cartas del gacha');
    echo "<p class='adm-admin-error'>La tabla <code>fact_game_card_collection</code> no existe. Ejecuta primero el esquema/seed del juego.</p>";
    admin_panel_close();
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    hg_admin_require_session(true);

    $token = hg_admin_extract_csrf_token($_POST);
    if (!hg_admin_csrf_valid($token, $csrfKey)) {
        hg_admin_json_error('CSRF no valido', 403, ['csrf' => 'invalid']);
    }

    $action = (string)($_POST['crud_action'] ?? '');
    if ($action !== 'update') {
        hg_admin_json_error('Accion no soportada', 400);
    }

    $cardId = hg_agc_int_input('card_id', 1, 2147483647);
    $rarity = (string)($_POST['card_rarity'] ?? '');
    $isActive = isset($_POST['is_active']) && (string)$_POST['is_active'] === '1' ? 1 : 0;

    $hpMin = hg_agc_int_input('hp_min', 0, 999);
    $hpMax = hg_agc_int_input('hp_max', 0, 999);
    $atkMin = hg_agc_int_input('atk_min', 0, 999);
    $atkMax = hg_agc_int_input('atk_max', 0, 999);
    $defMin = hg_agc_int_input('def_min', 0, 999);
    $defMax = hg_agc_int_input('def_max', 0, 999);

    $errors = [];
    if ($cardId < 1) {
        $errors['card_id'] = 'Carta no valida';
    }
    if (!hg_gc_valid_rarity($rarity)) {
        $errors['card_rarity'] = 'Rareza no valida';
    }
    if ($hpMin < 0 || $hpMax < 0 || $hpMin > $hpMax) {
        $errors['hp'] = 'Limites de PS no validos';
    }
    if ($atkMin < 0 || $atkMax < 0 || $atkMin > $atkMax) {
        $errors['atk'] = 'Limites de ATQ no validos';
    }
    if ($defMin < 0 || $defMax < 0 || $defMin > $defMax) {
        $errors['def'] = 'Limites de DEF no validos';
    }

    if ($errors) {
        hg_admin_json_error('Revisa los campos marcados', 422, $errors);
    }

    $stmt = $link->prepare(
        "UPDATE fact_game_card_collection
         SET card_rarity = ?,
             hp_min = ?, hp_max = ?,
             atk_min = ?, atk_max = ?,
             def_min = ?, def_max = ?,
             is_active = ?,
             updated_at = CURRENT_TIMESTAMP
         WHERE card_id = ?"
    );

    if (!$stmt) {
        hg_admin_json_error('No se pudo preparar la actualizacion', 500);
    }

    $stmt->bind_param('siiiiiiii', $rarity, $hpMin, $hpMax, $atkMin, $atkMax, $defMin, $defMax, $isActive, $cardId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    $row = hg_agc_fetch_card($link, $cardId);
    if (!$row) {
        hg_admin_json_error('Carta no encontrada', 404);
    }

    hg_admin_json_success(['card' => $row, 'affected' => $affected], 'Carta actualizada');
}

$rarityLabels = hg_agc_rarity_labels();
$typeLabels = hg_gc_type_labels();
$rarityRanges = hg_gc_rarity_ranges();

$q = trim((string)($_GET['q'] ?? ''));
$rarityFilter = (string)($_GET['rarity'] ?? '');
$typeFilter = (string)($_GET['type'] ?? '');
$activeFilter = (string)($_GET['active'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 50);
if (!in_array($perPage, [25, 50, 100, 200], true)) {
    $perPage = 50;
}

$where = ['1 = 1'];
$types = '';
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(card_name LIKE ? OR card_text LIKE ? OR source_table LIKE ? OR CAST(source_id AS CHAR) LIKE ?)";
    $types .= 'ssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($rarityFilter !== '' && isset($rarityLabels[$rarityFilter])) {
    $where[] = 'card_rarity = ?';
    $types .= 's';
    $params[] = $rarityFilter;
}

if ($typeFilter !== '' && isset($typeLabels[$typeFilter])) {
    $where[] = 'source_type = ?';
    $types .= 's';
    $params[] = $typeFilter;
}

if ($activeFilter === '1' || $activeFilter === '0') {
    $where[] = 'is_active = ?';
    $types .= 'i';
    $params[] = (int)$activeFilter;
}

$whereSql = implode(' AND ', $where);
$totalRows = hg_agc_fetch_count($link, "SELECT COUNT(*) FROM fact_game_card_collection WHERE {$whereSql}", $types, $params);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$rowParams = $params;
$rowParams[] = $perPage;
$rowParams[] = $offset;
$rows = hg_agc_fetch_all(
    $link,
    "SELECT card_id, source_type, source_table, source_id, card_name, card_slug, card_text,
            card_image_url, card_rarity, hp_min, hp_max, atk_min, atk_max, def_min, def_max,
            is_active, updated_at
     FROM fact_game_card_collection
     WHERE {$whereSql}
     ORDER BY FIELD(card_rarity, 'mythic', 'legendary', 'epic', 'rare', 'unusual', 'common'),
              card_name ASC,
              card_id ASC
     LIMIT ? OFFSET ?",
    $types . 'ii',
    $rowParams
);

$cards = [];
foreach ($rows as $row) {
    $cards[] = hg_agc_card_payload($row);
}

$fallbackImageMap = [];
foreach (array_keys($typeLabels) as $sourceType) {
    $fallbackImageMap[$sourceType] = hg_gc_fallback_image_url($sourceType);
}

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    hg_admin_json_success(
        ['cards' => $cards, 'total' => $totalRows],
        'OK',
        ['page' => $page, 'pages' => $totalPages, 'per_page' => $perPage]
    );
}

$queryBase = $_GET;
unset($queryBase['page']);
$baseUrl = '/talim?' . http_build_query(array_merge($queryBase, ['s' => 'admin_game_cards']));
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

admin_panel_open('Cartas del gacha', "<a class='btn' href='/games/card-game' target='_blank' rel='noopener'>Ver gacha</a>");
?>
<style>
.agc-wrap { color: #e8eefc; }
.agc-filters { display: grid; grid-template-columns: minmax(220px, 1.5fr) repeat(4, minmax(140px, .6fr)) auto; gap: 10px; align-items: end; margin: 14px 0 18px; padding: 14px; background: rgba(8, 18, 36, .78); border: 1px solid rgba(115, 157, 220, .28); border-radius: 8px; }
.agc-filters label { display: grid; gap: 5px; font-size: .78rem; color: #a9bce2; text-transform: uppercase; letter-spacing: .05em; }
.agc-filters input, .agc-filters select, .agc-modal input, .agc-modal select { min-height: 36px; border: 1px solid rgba(148, 174, 214, .35); border-radius: 6px; background: #050a13; color: #f4f7ff; padding: 7px 9px; }
.agc-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; border: 1px solid rgba(255, 198, 0, .7); border-radius: 6px; background: #ffc400; color: #121212; font-weight: 800; cursor: pointer; padding: 7px 13px; text-decoration: none; }
.agc-btn.secondary { background: rgba(22, 37, 56, .92); border-color: rgba(148, 174, 214, .42); color: #e8eefc; }
.agc-summary { margin: 0 0 10px; color: #a9bce2; }
.agc-table-wrap { overflow: auto; border: 1px solid rgba(115, 157, 220, .25); border-radius: 8px; background: rgba(2, 5, 12, .82); }
.agc-table { width: 100%; border-collapse: collapse; min-width: 980px; }
.agc-table th, .agc-table td { padding: 10px 11px; border-bottom: 1px solid rgba(148, 174, 214, .13); text-align: left; vertical-align: middle; }
.agc-table th { font-size: .75rem; color: #8fa7d4; text-transform: uppercase; letter-spacing: .08em; background: #03070d; position: sticky; top: 0; z-index: 1; }
.agc-table tr { cursor: pointer; }
.agc-table tbody tr:hover { background: rgba(31, 75, 124, .22); }
.agc-card-name { font-weight: 800; color: #fff; }
.agc-muted { color: #91a2c0; font-size: .86rem; }
.agc-pill { display: inline-flex; border-radius: 999px; border: 1px solid rgba(148, 174, 214, .34); padding: 2px 8px; font-size: .78rem; font-weight: 800; }
.agc-pill.common { color: #111; background: #f0f0e8; }
.agc-pill.unusual { color: #d8ffe7; background: #185d33; }
.agc-pill.rare { color: #d9ecff; background: #1d5b9c; }
.agc-pill.epic { color: #f5e7ff; background: #6e3fb1; }
.agc-pill.legendary { color: #fff0d8; background: #9d5a20; }
.agc-pill.mythic { color: #ffe6ff; background: #8236b4; }
.agc-status-on { color: #88f0a4; font-weight: 800; }
.agc-status-off { color: #ff8a8a; font-weight: 800; }
.agc-pager { display: flex; gap: 8px; align-items: center; justify-content: flex-end; margin: 14px 0 0; }
.agc-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(1, 4, 12, .76); z-index: 9998; padding: 24px; overflow: auto; }
.agc-modal-backdrop.is-open { display: grid; place-items: center; }
.agc-modal { width: min(760px, 96vw); background: #071122; border: 1px solid rgba(148, 174, 214, .38); border-radius: 10px; box-shadow: 0 24px 80px rgba(0, 0, 0, .55); padding: 18px; color: #f4f7ff; }
.agc-modal-head { display: flex; gap: 14px; justify-content: space-between; align-items: start; margin-bottom: 14px; }
.agc-modal-title { margin: 0; font-size: 1.35rem; }
.agc-close { width: 38px; height: 38px; border-radius: 999px; border: 1px solid rgba(255,255,255,.28); background: #5d1826; color: #fff; font-size: 1.3rem; cursor: pointer; }
.agc-modal-body { display: grid; gap: 14px; }
.agc-preview { display: grid; grid-template-columns: 92px 1fr; gap: 13px; align-items: start; }
.agc-preview img { width: 92px; aspect-ratio: 3 / 4; object-fit: cover; background: #02060d; border-radius: 6px; border: 1px solid rgba(148,174,214,.25); }
.agc-field-grid { display: grid; grid-template-columns: repeat(3, minmax(120px, 1fr)); gap: 12px; }
.agc-range-grid { display: grid; grid-template-columns: repeat(6, minmax(80px, 1fr)); gap: 10px; }
.agc-check { display: inline-flex; gap: 8px; align-items: center; color: #dbe7ff; }
.agc-check input { min-height: auto; }
.agc-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px; }
.agc-message { min-height: 22px; color: #ffd45b; }
@media (max-width: 920px) {
    .agc-filters { grid-template-columns: 1fr 1fr; }
    .agc-range-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 620px) {
    .agc-filters, .agc-field-grid, .agc-preview { grid-template-columns: 1fr; }
}
</style>

<div class="agc-wrap">
    <form class="agc-filters" method="get" action="/talim">
        <input type="hidden" name="s" value="admin_game_cards">
        <label>Buscar
            <input type="search" name="q" value="<?= hg_agc_h($q) ?>" placeholder="Nombre, texto, tabla o ID">
        </label>
        <label>Rareza
            <select name="rarity">
                <option value="">Todas</option>
                <?php foreach ($rarityLabels as $value => $label): ?>
                    <option value="<?= hg_agc_h($value) ?>"<?= $rarityFilter === $value ? ' selected' : '' ?>><?= hg_agc_h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo
            <select name="type">
                <option value="">Todos</option>
                <?php foreach ($typeLabels as $value => $label): ?>
                    <option value="<?= hg_agc_h($value) ?>"<?= $typeFilter === $value ? ' selected' : '' ?>><?= hg_agc_h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Estado
            <select name="active">
                <option value="">Todos</option>
                <option value="1"<?= $activeFilter === '1' ? ' selected' : '' ?>>Activas</option>
                <option value="0"<?= $activeFilter === '0' ? ' selected' : '' ?>>Inactivas</option>
            </select>
        </label>
        <label>Mostrar
            <select name="per_page">
                <?php foreach ([25, 50, 100, 200] as $size): ?>
                    <option value="<?= $size ?>"<?= $perPage === $size ? ' selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="agc-btn" type="submit">Filtrar</button>
    </form>

    <p class="agc-summary">
        <?= (int)$totalRows ?> cartas encontradas. Haz clic en una fila para editar rareza, PS, ATQ, DEF y estado.
    </p>

    <div class="agc-table-wrap">
        <table class="agc-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Carta</th>
                    <th>Tipo</th>
                    <th>Rareza</th>
                    <th>PS</th>
                    <th>ATQ</th>
                    <th>DEF</th>
                    <th>Estado</th>
                    <th>Origen</th>
                    <th>Actualizada</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$cards): ?>
                    <tr><td colspan="10">No hay cartas con esos filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($cards as $card): ?>
                    <tr data-card-id="<?= (int)$card['card_id'] ?>">
                        <td>#<?= (int)$card['card_id'] ?></td>
                        <td>
                            <div class="agc-card-name"><?= hg_agc_h($card['card_name']) ?></div>
                            <div class="agc-muted"><?= hg_agc_h(hg_gc_excerpt($card['card_text'], 90)) ?></div>
                        </td>
                        <td><?= hg_agc_h($card['type_label']) ?></td>
                        <td><span class="agc-pill <?= hg_agc_h($card['card_rarity']) ?>"><?= hg_agc_h($rarityLabels[$card['card_rarity']] ?? $card['card_rarity']) ?></span></td>
                        <td><?= (int)$card['hp_min'] ?>-<?= (int)$card['hp_max'] ?></td>
                        <td><?= (int)$card['atk_min'] ?>-<?= (int)$card['atk_max'] ?></td>
                        <td><?= (int)$card['def_min'] ?>-<?= (int)$card['def_max'] ?></td>
                        <td><?= $card['is_active'] ? '<span class="agc-status-on">Activa</span>' : '<span class="agc-status-off">Inactiva</span>' ?></td>
                        <td><span class="agc-muted"><?= hg_agc_h($card['source_table']) ?> #<?= (int)$card['source_id'] ?></span></td>
                        <td><span class="agc-muted"><?= hg_agc_h($card['updated_at']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="agc-pager">
        <?php if ($page > 1): ?>
            <a class="agc-btn secondary" href="<?= hg_agc_h($baseUrl . '&page=' . ($page - 1)) ?>">Anterior</a>
        <?php endif; ?>
        <span class="agc-muted">Pagina <?= (int)$page ?> de <?= (int)$totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a class="agc-btn secondary" href="<?= hg_agc_h($baseUrl . '&page=' . ($page + 1)) ?>">Siguiente</a>
        <?php endif; ?>
    </div>
</div>

<div class="agc-modal-backdrop" id="agcModal" aria-hidden="true">
    <form class="agc-modal" id="agcEditForm">
        <input type="hidden" name="crud_action" value="update">
        <input type="hidden" name="csrf" value="<?= hg_agc_h($csrf) ?>">
        <input type="hidden" name="card_id" id="agcCardId">
        <div class="agc-modal-head">
            <div>
                <h3 class="agc-modal-title" id="agcModalTitle">Editar carta</h3>
                <div class="agc-muted" id="agcModalMeta"></div>
            </div>
            <button class="agc-close" type="button" id="agcClose" aria-label="Cerrar">&times;</button>
        </div>
        <div class="agc-modal-body">
            <div class="agc-preview">
                <img id="agcPreviewImage" alt="">
                <div>
                    <p id="agcPreviewText" style="margin:0 0 10px; line-height:1.5;"></p>
                    <a class="agc-btn secondary" id="agcOpenSource" target="_blank" rel="noopener" href="#">Abrir ficha</a>
                </div>
            </div>
            <div class="agc-field-grid">
                <label>Rareza
                    <select name="card_rarity" id="agcRarity">
                        <?php foreach ($rarityLabels as $value => $label): ?>
                            <option value="<?= hg_agc_h($value) ?>"><?= hg_agc_h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Estado
                    <select name="is_active" id="agcActive">
                        <option value="1">Activa</option>
                        <option value="0">Inactiva</option>
                    </select>
                </label>
                <label style="align-self:end;">
                    <button class="agc-btn secondary" type="button" id="agcApplyRange">Aplicar rango base</button>
                </label>
            </div>
            <div class="agc-range-grid">
                <label>PS min <input type="number" name="hp_min" id="agcHpMin" min="0" max="999" required></label>
                <label>PS max <input type="number" name="hp_max" id="agcHpMax" min="0" max="999" required></label>
                <label>ATQ min <input type="number" name="atk_min" id="agcAtkMin" min="0" max="999" required></label>
                <label>ATQ max <input type="number" name="atk_max" id="agcAtkMax" min="0" max="999" required></label>
                <label>DEF min <input type="number" name="def_min" id="agcDefMin" min="0" max="999" required></label>
                <label>DEF max <input type="number" name="def_max" id="agcDefMax" min="0" max="999" required></label>
            </div>
            <div class="agc-message" id="agcMessage" role="status"></div>
            <div class="agc-modal-actions">
                <button class="agc-btn secondary" type="button" id="agcCancel">Cancelar</button>
                <button class="agc-btn" type="submit">Guardar cambios</button>
            </div>
        </div>
    </form>
</div>

<script src="/assets/js/admin/admin-http.js"></script>
<script>
(function () {
    'use strict';

    window.ADMIN_CSRF_TOKEN = <?= json_encode($csrf, $jsonFlags) ?>;
    var cards = <?= json_encode($cards, $jsonFlags) ?>;
    var ranges = <?= json_encode($rarityRanges, $jsonFlags) ?>;
    var fallbackImages = <?= json_encode($fallbackImageMap, $jsonFlags) ?>;
    var byId = {};
    cards.forEach(function (card) {
        byId[String(card.card_id)] = card;
    });

    var modal = document.getElementById('agcModal');
    var form = document.getElementById('agcEditForm');
    var message = document.getElementById('agcMessage');
    var rarity = document.getElementById('agcRarity');
    var openSource = document.getElementById('agcOpenSource');
    var fields = {
        cardId: document.getElementById('agcCardId'),
        title: document.getElementById('agcModalTitle'),
        meta: document.getElementById('agcModalMeta'),
        text: document.getElementById('agcPreviewText'),
        image: document.getElementById('agcPreviewImage'),
        active: document.getElementById('agcActive'),
        hpMin: document.getElementById('agcHpMin'),
        hpMax: document.getElementById('agcHpMax'),
        atkMin: document.getElementById('agcAtkMin'),
        atkMax: document.getElementById('agcAtkMax'),
        defMin: document.getElementById('agcDefMin'),
        defMax: document.getElementById('agcDefMax')
    };

    function fallbackFor(card) {
        return fallbackImages[card.source_type] || '/img/og/og_image.jpg';
    }

    function setOpen(open) {
        modal.classList.toggle('is-open', open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    function applyRange() {
        var range = ranges[rarity.value] || ranges.common || [10, 40];
        fields.hpMin.value = range[0];
        fields.hpMax.value = range[1];
        fields.atkMin.value = range[0];
        fields.atkMax.value = range[1];
        fields.defMin.value = range[0];
        fields.defMax.value = range[1];
    }

    function openEditor(card) {
        fields.cardId.value = card.card_id;
        fields.title.textContent = card.card_name;
        fields.meta.textContent = card.type_label + ' · ' + card.source_table + ' #' + card.source_id;
        fields.text.textContent = card.card_text || 'Sin texto de carta.';
        fields.image.src = card.card_image_url || fallbackFor(card);
        fields.image.alt = card.card_name;
        rarity.value = card.card_rarity;
        fields.active.value = String(card.is_active ? 1 : 0);
        fields.hpMin.value = card.hp_min;
        fields.hpMax.value = card.hp_max;
        fields.atkMin.value = card.atk_min;
        fields.atkMax.value = card.atk_max;
        fields.defMin.value = card.def_min;
        fields.defMax.value = card.def_max;
        if (card.card_url) {
            openSource.href = card.card_url;
            openSource.style.display = '';
        } else {
            openSource.removeAttribute('href');
            openSource.style.display = 'none';
        }
        message.textContent = '';
        setOpen(true);
    }

    document.querySelectorAll('[data-card-id]').forEach(function (row) {
        row.addEventListener('click', function () {
            var card = byId[String(row.getAttribute('data-card-id'))];
            if (card) {
                openEditor(card);
            }
        });
    });

    document.getElementById('agcClose').addEventListener('click', function () { setOpen(false); });
    document.getElementById('agcCancel').addEventListener('click', function () { setOpen(false); });
    rarity.addEventListener('change', applyRange);
    document.getElementById('agcApplyRange').addEventListener('click', applyRange);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            setOpen(false);
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        message.textContent = 'Guardando...';
        var request = window.HGAdminHttp
            ? window.HGAdminHttp.request('/talim?s=admin_game_cards&ajax=1', {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin'
            })
            : fetch('/talim?s=admin_game_cards&ajax=1', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            });

        request.then(function (payload) {
            if (!payload || !payload.ok) {
                throw new Error(payload && payload.message ? payload.message : 'No se pudo guardar');
            }
            message.textContent = payload.message || 'Carta actualizada';
            window.setTimeout(function () { window.location.reload(); }, 350);
        }).catch(function (error) {
            message.textContent = error.message || 'Error al guardar';
        });
    });
}());
</script>
<?php
admin_panel_close();
