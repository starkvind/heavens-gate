<?php

include_once(__DIR__ . '/../../helpers/public_response.php');

$metaTitle = "Personajes | Heaven's Gate";
$metaDescription = "Listado móvil de personajes.";
$pageSect = 'Personajes';

if (!function_exists('hg_mobile_char_h')) {
    function hg_mobile_char_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_characters_list', 'missing DB connection');
    hg_public_render_error('Personajes no disponibles', 'No se pudo cargar la lista de personajes.');
    return;
}

$queryText = trim((string)($_GET['q'] ?? ''));
$typeFilter = filter_input(INPUT_GET, 'type', FILTER_VALIDATE_INT) ?: 0;
$groupFilter = filter_input(INPUT_GET, 'group', FILTER_VALIDATE_INT) ?: 0;
$organizationFilter = filter_input(INPUT_GET, 'organization', FILTER_VALIDATE_INT) ?: 0;
$systemFilter = filter_input(INPUT_GET, 'system', FILTER_VALIDATE_INT) ?: 0;
$statusFilter = filter_input(INPUT_GET, 'status', FILTER_VALIDATE_INT) ?: 0;
$page = filter_input(INPUT_GET, 'pag', FILTER_VALIDATE_INT) ?: 1;
if ($page < 1) {
    $page = 1;
}

$pageSize = 24;
$chronicleConditionP = function_exists('hg_mobile_chronicle_exclusion_condition') ? hg_mobile_chronicle_exclusion_condition('p') : 'p.chronicle_id NOT IN (2,7)';
$chronicleConditionBare = function_exists('hg_mobile_chronicle_exclusion_condition') ? hg_mobile_chronicle_exclusion_condition('') : 'chronicle_id NOT IN (2,7)';
$where = [$chronicleConditionP];

$debugMobile = !empty($_GET['debug_mobile']);
$debugMessages = [];

if ($queryText !== '') {
    $like = mysqli_real_escape_string($link, '%' . $queryText . '%');
    $where[] = "(p.name LIKE '{$like}' OR p.alias LIKE '{$like}' OR p.concept LIKE '{$like}')";
}

if ($typeFilter > 0) {
    $where[] = "p.character_type_id = " . (int)$typeFilter;
}
if ($groupFilter > 0) {
    $where[] = "EXISTS (
        SELECT 1 FROM bridge_characters_groups bcg
        WHERE bcg.character_id = p.id
          AND bcg.group_id = " . (int)$groupFilter . "
          AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
    )";
}
if ($organizationFilter > 0) {
    $where[] = "(
        EXISTS (
            SELECT 1 FROM bridge_characters_organizations bco
            WHERE bco.character_id = p.id
              AND bco.organization_id = " . (int)$organizationFilter . "
              AND (bco.is_active = 1 OR bco.is_active IS NULL)
        )
        OR EXISTS (
            SELECT 1
            FROM bridge_characters_groups bcg
            INNER JOIN bridge_organizations_groups bog ON bog.group_id = bcg.group_id
            WHERE bcg.character_id = p.id
              AND bog.organization_id = " . (int)$organizationFilter . "
              AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
              AND (bog.is_active = 1 OR bog.is_active IS NULL)
        )
    )";
}
if ($systemFilter > 0) {
    $where[] = "p.system_id = " . (int)$systemFilter;
}
if ($statusFilter > 0) {
    $where[] = "p.status_id = " . (int)$statusFilter;
}

$whereSql = implode(' AND ', $where);

$typeRows = [];
if ($res = $link->query("SELECT id, kind FROM dim_character_types ORDER BY sort_order, kind")) {
    while ($row = $res->fetch_assoc()) {
        $typeRows[] = $row;
    }
    $res->free();
}

$groupRows = [];
$groupSql = "
    SELECT g.id, g.name
    FROM dim_groups g
    WHERE EXISTS (
        SELECT 1
        FROM bridge_characters_groups bcg
        INNER JOIN fact_characters p ON p.id = bcg.character_id
        WHERE bcg.group_id = g.id
          AND {$chronicleConditionP}
          AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
    )
    ORDER BY g.name
";
if ($res = $link->query($groupSql)) {
    while ($row = $res->fetch_assoc()) {
        $groupRows[] = $row;
    }
    $res->free();
}

$organizationRows = [];
$organizationSql = "
    SELECT o.id, o.name
    FROM dim_organizations o
    WHERE EXISTS (
        SELECT 1
        FROM bridge_characters_organizations bco
        INNER JOIN fact_characters p ON p.id = bco.character_id
        WHERE bco.organization_id = o.id
          AND {$chronicleConditionP}
          AND (bco.is_active = 1 OR bco.is_active IS NULL)
    )
    OR EXISTS (
        SELECT 1
        FROM bridge_organizations_groups bog
        INNER JOIN bridge_characters_groups bcg ON bcg.group_id = bog.group_id
        INNER JOIN fact_characters p ON p.id = bcg.character_id
        WHERE bog.organization_id = o.id
          AND {$chronicleConditionP}
          AND (bog.is_active = 1 OR bog.is_active IS NULL)
          AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
    )
    ORDER BY o.name
";
if ($res = $link->query($organizationSql)) {
    while ($row = $res->fetch_assoc()) {
        $organizationRows[] = $row;
    }
    $res->free();
}

$systemRows = [];
if ($res = $link->query("SELECT id, name FROM dim_systems WHERE id IN (SELECT DISTINCT system_id FROM fact_characters WHERE system_id IS NOT NULL AND {$chronicleConditionBare}) ORDER BY name")) {
    while ($row = $res->fetch_assoc()) {
        $systemRows[] = $row;
    }
    $res->free();
}

$statusRows = [];
if ($res = $link->query("SELECT id, label FROM dim_character_status WHERE id IN (SELECT DISTINCT status_id FROM fact_characters WHERE status_id IS NOT NULL AND {$chronicleConditionBare}) ORDER BY label")) {
    while ($row = $res->fetch_assoc()) {
        $statusRows[] = $row;
    }
    $res->free();
}

$totalRows = 0;
$countSql = "SELECT COUNT(DISTINCT p.id) AS total FROM fact_characters p WHERE {$whereSql}";
if ($result = mysqli_query($link, $countSql)) {
    $row = mysqli_fetch_assoc($result);
    $totalRows = (int)($row['total'] ?? 0);
    mysqli_free_result($result);
} else {
    $debugMessages[] = 'count query failed: ' . mysqli_error($link);
    hg_public_log_error('mobile_characters_list', end($debugMessages));
}

$totalPages = max(1, (int)ceil($totalRows / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

$sql = "
    SELECT
        p.id,
        p.pretty_id,
        p.name,
        p.alias,
        p.concept,
        p.image_url,
        p.character_type_id,
        p.system_id,
        COALESCE(dcs.label, '') AS status_label,
        (
            SELECT g.name
            FROM bridge_characters_groups bcg
            INNER JOIN dim_groups g ON g.id = bcg.group_id
            WHERE bcg.character_id = p.id
              AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
            ORDER BY bcg.group_id
            LIMIT 1
        ) AS pack_name,
        COALESCE(
            (
                SELECT o.name
                FROM bridge_characters_organizations bco
                INNER JOIN dim_organizations o ON o.id = bco.organization_id
                WHERE bco.character_id = p.id
                  AND (bco.is_active = 1 OR bco.is_active IS NULL)
                ORDER BY bco.organization_id
                LIMIT 1
            ),
            (
                SELECT o2.name
                FROM bridge_characters_groups bcg2
                INNER JOIN bridge_organizations_groups bog ON bog.group_id = bcg2.group_id
                INNER JOIN dim_organizations o2 ON o2.id = bog.organization_id
                WHERE bcg2.character_id = p.id
                  AND (bcg2.is_active = 1 OR bcg2.is_active IS NULL)
                  AND (bog.is_active = 1 OR bog.is_active IS NULL)
                ORDER BY bog.organization_id
                LIMIT 1
            ),
            ''
        ) AS organization_name
    FROM fact_characters p
    LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
    WHERE {$whereSql}
    ORDER BY p.name ASC
    LIMIT " . (int)$pageSize . " OFFSET " . (int)$offset . "
";

$characters = [];
if ($result = mysqli_query($link, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $characters[] = $row;
    }
    mysqli_free_result($result);
} else {
    $debugMessages[] = 'list query failed: ' . mysqli_error($link);
    hg_public_log_error('mobile_characters_list', end($debugMessages));
}

$typeNamesById = [];
foreach ($typeRows as $typeRow) {
    $typeNamesById[(int)($typeRow['id'] ?? 0)] = (string)($typeRow['kind'] ?? '');
}

$systemNamesById = [];
if (!empty($characters)) {
    $systemIds = [];
    foreach ($characters as $character) {
        $systemId = (int)($character['system_id'] ?? 0);
        if ($systemId > 0) {
            $systemIds[$systemId] = $systemId;
        }
    }

    if (!empty($systemIds)) {
        $systemIdCsv = implode(',', array_map('intval', array_values($systemIds)));
        if ($res = mysqli_query($link, "SELECT id, name FROM dim_systems WHERE id IN ({$systemIdCsv})")) {
            while ($row = mysqli_fetch_assoc($res)) {
                $systemNamesById[(int)($row['id'] ?? 0)] = (string)($row['name'] ?? '');
            }
            mysqli_free_result($res);
        } else {
            $debugMessages[] = 'system lookup failed: ' . mysqli_error($link);
            hg_public_log_error('mobile_characters_list', end($debugMessages));
        }
    }

    foreach ($characters as &$character) {
        $character['type_name'] = $typeNamesById[(int)($character['character_type_id'] ?? 0)] ?? '';
        $character['system_name'] = $systemNamesById[(int)($character['system_id'] ?? 0)] ?? '';
    }
    unset($character);
}

function hg_mobile_char_url(array $row, mysqli $link): string
{
    $id = (int)($row['id'] ?? 0);
    return $id > 0 ? pretty_url($link, 'fact_characters', '/characters', $id) : '/characters';
}

function hg_mobile_char_page_url(int $page, string $queryText, int $typeFilter, int $groupFilter, int $organizationFilter, int $systemFilter, int $statusFilter): string
{
    $query = ['pag' => $page];
    if ($queryText !== '') {
        $query['q'] = $queryText;
    }
    if ($typeFilter > 0) {
        $query['type'] = $typeFilter;
    }
    if ($groupFilter > 0) $query['group'] = $groupFilter;
    if ($organizationFilter > 0) $query['organization'] = $organizationFilter;
    if ($systemFilter > 0) $query['system'] = $systemFilter;
    if ($statusFilter > 0) $query['status'] = $statusFilter;
    return '/characters?' . http_build_query($query);
}
?>

<section class="hg-mobile-section">
    <h1>Personajes</h1>

    <form class="hg-mobile-filterbar" action="/characters" method="get">
        <label>
            <span>Buscar</span>
            <input type="search" name="q" value="<?= hg_mobile_char_h($queryText) ?>" placeholder="Nombre, alias, concepto">
        </label>
        <label>
            <span>Tipo</span>
            <select name="type">
                <option value="0">Todos</option>
                <?php foreach ($typeRows as $typeRow): ?>
                    <?php $typeId = (int)($typeRow['id'] ?? 0); ?>
                    <option value="<?= $typeId ?>"<?= $typeId === $typeFilter ? ' selected' : '' ?>>
                        <?= hg_mobile_char_h($typeRow['kind'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Grupo</span>
            <select name="group">
                <option value="0">Todos</option>
                <?php foreach ($groupRows as $row): ?>
                    <?php $id = (int)($row['id'] ?? 0); ?>
                    <option value="<?= $id ?>"<?= $id === $groupFilter ? ' selected' : '' ?>><?= hg_mobile_char_h($row['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Organizacion</span>
            <select name="organization">
                <option value="0">Todas</option>
                <?php foreach ($organizationRows as $row): ?>
                    <?php $id = (int)($row['id'] ?? 0); ?>
                    <option value="<?= $id ?>"<?= $id === $organizationFilter ? ' selected' : '' ?>><?= hg_mobile_char_h($row['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Sistema</span>
            <select name="system">
                <option value="0">Todos</option>
                <?php foreach ($systemRows as $row): ?>
                    <?php $id = (int)($row['id'] ?? 0); ?>
                    <option value="<?= $id ?>"<?= $id === $systemFilter ? ' selected' : '' ?>><?= hg_mobile_char_h($row['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Estado</span>
            <select name="status">
                <option value="0">Todos</option>
                <?php foreach ($statusRows as $row): ?>
                    <?php $id = (int)($row['id'] ?? 0); ?>
                    <option value="<?= $id ?>"<?= $id === $statusFilter ? ' selected' : '' ?>><?= hg_mobile_char_h($row['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Filtrar</button>
    </form>

    <p class="hg-mobile-muted"><?= number_format($totalRows, 0, ',', '.') ?> personajes</p>

    <?php if ($debugMobile): ?>
        <pre class="hg-mobile-debug"><?= hg_mobile_char_h(implode("\n", $debugMessages) ?: 'Sin errores SQL registrados. Filas cargadas: ' . count($characters)) ?></pre>
    <?php endif; ?>

    <div class="hg-mobile-character-list">
        <?php if (empty($characters)): ?>
            <p class="hg-mobile-muted">No se pudieron cargar personajes para esta pagina.</p>
        <?php endif; ?>
        <?php foreach ($characters as $character): ?>
            <?php
                $imageUrl = trim((string)($character['image_url'] ?? ''));
                $href = hg_mobile_char_url($character, $link);
            ?>
            <a class="hg-mobile-character-card" href="<?= hg_mobile_char_h($href) ?>">
                <?php if ($imageUrl !== ''): ?>
                    <img src="<?= hg_mobile_char_h($imageUrl) ?>" alt="">
                <?php else: ?>
                    <span class="hg-mobile-character-avatar" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="hg-mobile-character-main">
                    <strong><?= hg_mobile_char_h($character['name'] ?? '') ?></strong>
                    <?php if (!empty($character['alias'])): ?>
                        <em><?= hg_mobile_char_h($character['alias']) ?></em>
                    <?php endif; ?>
                    <span><?= hg_mobile_char_h($character['concept'] ?? '') ?></span>
                    <small>
                        <?= hg_mobile_char_h($character['type_name'] ?? '') ?>
                        <?php if (!empty($character['system_name'])): ?>
                            - <?= hg_mobile_char_h($character['system_name']) ?>
                        <?php endif; ?>
                    </small>
                    <small>
                        <?= hg_mobile_char_h($character['pack_name'] ?? '') ?>
                        <?php if (!empty($character['organization_name'])): ?>
                            - <?= hg_mobile_char_h($character['organization_name']) ?>
                        <?php endif; ?>
                    </small>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="hg-mobile-pagination" aria-label="Paginacion de personajes">
            <?php if ($page > 1): ?>
                <a href="<?= hg_mobile_char_h(hg_mobile_char_page_url($page - 1, $queryText, $typeFilter, $groupFilter, $organizationFilter, $systemFilter, $statusFilter)) ?>">Anterior</a>
            <?php endif; ?>
            <span><?= $page ?> / <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="<?= hg_mobile_char_h(hg_mobile_char_page_url($page + 1, $queryText, $typeFilter, $groupFilter, $organizationFilter, $systemFilter, $statusFilter)) ?>">Siguiente</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
