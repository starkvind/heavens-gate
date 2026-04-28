<?php
include_once(__DIR__ . '/../../helpers/admin_ajax.php');
if (!hg_admin_require_db($link)) { return; }
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }
if (!headers_sent()) { header('Content-Type: text/html; charset=UTF-8'); }

include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

$isAjaxRequest = (((string)($_GET['ajax'] ?? '') === '1') || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'));
$csrfKey = 'csrf_admin_organizations';
$csrf = function_exists('hg_admin_ensure_csrf_token') ? hg_admin_ensure_csrf_token($csrfKey) : (string)($_SESSION[$csrfKey] ?? '');

function hg_aorg_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hg_aorg_payload(): array
{
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    if (empty($payload) && !empty($_POST)) {
        $payload = $_POST;
    }
    return is_array($payload) ? $payload : [];
}

function hg_aorg_require_write(string $csrfKey, array $payload): void
{
    if (function_exists('hg_admin_require_session')) {
        hg_admin_require_session(true);
    }
    $token = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : (string)($payload['csrf'] ?? '');
    if (!function_exists('hg_admin_csrf_valid') || !hg_admin_csrf_valid($token, $csrfKey)) {
        hg_admin_json_error('CSRF invalido. Recarga la pagina.', 403, ['csrf' => 'invalid']);
    }
}

function hg_aorg_table_exists(mysqli $link, string $table): bool
{
    return function_exists('hg_table_exists') ? hg_table_exists($link, $table) : false;
}

function hg_aorg_org_url(mysqli $link, int $orgId): string
{
    return pretty_url($link, 'dim_organizations', '/organizations', $orgId);
}

function hg_aorg_chart_url(mysqli $link, int $orgId): string
{
    return rtrim(hg_aorg_org_url($link, $orgId), '/') . '/org-chart';
}

function hg_aorg_fetch_organizations(mysqli $link): array
{
    $deptCount = hg_aorg_table_exists($link, 'dim_organization_departments')
        ? "(SELECT COUNT(*) FROM dim_organization_departments d WHERE d.organization_id = o.id AND d.is_active = 1)"
        : "0";
    $roleCount = hg_aorg_table_exists($link, 'bridge_characters_org')
        ? "(SELECT COUNT(*) FROM bridge_characters_org r WHERE r.organization_id = o.id AND r.is_active = 1)"
        : "0";

    $rows = [];
    $rs = $link->query("
        SELECT o.id, o.pretty_id, o.name, o.sort_order, o.color, o.is_npc,
               $deptCount AS chart_departments,
               $roleCount AS chart_positions
        FROM dim_organizations o
        ORDER BY o.sort_order ASC, o.name ASC
    ");
    if (!$rs) {
        return $rows;
    }
    while ($row = $rs->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['sort_order'] = (int)($row['sort_order'] ?? 0);
        $row['is_npc'] = (int)($row['is_npc'] ?? 0);
        $row['chart_departments'] = (int)($row['chart_departments'] ?? 0);
        $row['chart_positions'] = (int)($row['chart_positions'] ?? 0);
        $rows[] = $row;
    }
    $rs->close();
    return $rows;
}

function hg_aorg_fetch_organization(mysqli $link, int $orgId): ?array
{
    if ($orgId <= 0) {
        return null;
    }
    $stmt = $link->prepare("
        SELECT id, pretty_id, name, sort_order, totem_id, color, is_npc, description
        FROM dim_organizations
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $orgId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row)) {
        return null;
    }
    $row['id'] = (int)$row['id'];
    $row['sort_order'] = (int)($row['sort_order'] ?? 0);
    $row['totem_id'] = (int)($row['totem_id'] ?? 0);
    $row['is_npc'] = (int)($row['is_npc'] ?? 0);
    return $row;
}

function hg_aorg_fetch_totems(mysqli $link): array
{
    $rows = [0 => 'Sin totem'];
    $rs = $link->query("SELECT id, name FROM dim_totems ORDER BY name ASC");
    if (!$rs) {
        return $rows;
    }
    while ($row = $rs->fetch_assoc()) {
        $rows[(int)$row['id']] = (string)$row['name'];
    }
    $rs->close();
    return $rows;
}

function hg_aorg_fetch_departments(mysqli $link, int $orgId, bool $activeOnly = false): array
{
    if ($orgId <= 0 || !hg_aorg_table_exists($link, 'dim_organization_departments')) {
        return [];
    }
    $activeSql = $activeOnly ? "AND d.is_active = 1" : "";
    $stmt = $link->prepare("
        SELECT d.id, d.parent_department_id, d.pretty_id, d.name, d.department_type,
               d.hierarchy_level, d.color, d.description, d.sort_order, d.is_active,
               COALESCE(p.name, '') AS parent_name
        FROM dim_organization_departments d
            LEFT JOIN dim_organization_departments p ON p.id = d.parent_department_id
        WHERE d.organization_id = ?
          $activeSql
        ORDER BY d.hierarchy_level ASC, d.sort_order ASC, d.name ASC
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $orgId);
    $stmt->execute();
    $rows = [];
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['parent_department_id'] = (int)($row['parent_department_id'] ?? 0);
        $row['hierarchy_level'] = (int)($row['hierarchy_level'] ?? 0);
        $row['sort_order'] = (int)($row['sort_order'] ?? 0);
        $row['is_active'] = (int)($row['is_active'] ?? 0);
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function hg_aorg_fetch_positions(mysqli $link, int $orgId): array
{
    if ($orgId <= 0 || !hg_aorg_table_exists($link, 'bridge_characters_org')) {
        return [];
    }
    $stmt = $link->prepare("
        SELECT r.id, r.character_id, r.department_id, r.parent_bridge_id, r.hierarchy_level,
               r.position_name, r.position_code, r.scope_label, r.responsibility,
               r.is_head, r.is_primary, r.is_active, r.sort_order,
               COALESCE(c.name, '') AS character_name,
               COALESCE(c.alias, '') AS character_alias,
               COALESCE(d.name, '') AS department_name,
               COALESCE(p.name, '') AS parent_character_name,
               COALESCE(pr.position_name, '') AS parent_position_name
        FROM bridge_characters_org r
            LEFT JOIN fact_characters c ON c.id = r.character_id
            LEFT JOIN dim_organization_departments d ON d.id = r.department_id
            LEFT JOIN bridge_characters_org pr ON pr.id = r.parent_bridge_id
            LEFT JOIN fact_characters p ON p.id = pr.character_id
        WHERE r.organization_id = ?
        ORDER BY r.is_active DESC, r.hierarchy_level ASC, r.sort_order ASC, r.position_name ASC, r.id ASC
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $orgId);
    $stmt->execute();
    $rows = [];
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['character_id'] = (int)($row['character_id'] ?? 0);
        $row['department_id'] = (int)($row['department_id'] ?? 0);
        $row['parent_bridge_id'] = (int)($row['parent_bridge_id'] ?? 0);
        $row['hierarchy_level'] = (int)($row['hierarchy_level'] ?? 0);
        $row['is_head'] = (int)($row['is_head'] ?? 0);
        $row['is_primary'] = (int)($row['is_primary'] ?? 0);
        $row['is_active'] = (int)($row['is_active'] ?? 0);
        $row['sort_order'] = (int)($row['sort_order'] ?? 0);
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function hg_aorg_fetch_characters(mysqli $link, int $orgId): array
{
    if ($orgId <= 0) {
        return [];
    }

    $sources = [];
    $params = [];
    $types = '';

    if (hg_aorg_table_exists($link, 'bridge_characters_organizations')) {
        $sources[] = "
            SELECT bco.character_id, 'Organizacion' AS source_label
            FROM bridge_characters_organizations bco
            WHERE bco.organization_id = ?
              AND (bco.is_active = 1 OR bco.is_active IS NULL)
        ";
        $params[] = $orgId;
        $types .= 'i';
    }

    if (hg_aorg_table_exists($link, 'bridge_characters_groups') && hg_aorg_table_exists($link, 'bridge_organizations_groups')) {
        $sources[] = "
            SELECT bcg.character_id, 'Grupo' AS source_label
            FROM bridge_characters_groups bcg
                INNER JOIN bridge_organizations_groups bog
                    ON bog.group_id = bcg.group_id
                   AND bog.organization_id = ?
                   AND (bog.is_active = 1 OR bog.is_active IS NULL)
            WHERE (bcg.is_active = 1 OR bcg.is_active IS NULL)
        ";
        $params[] = $orgId;
        $types .= 'i';
    }

    if (empty($sources)) {
        return [];
    }

    $sourceSql = implode("\nUNION ALL\n", $sources);
    $rows = [];
    $stmt = $link->prepare("
        SELECT c.id, c.name, c.alias, c.garou_name,
               GROUP_CONCAT(DISTINCT src.source_label ORDER BY src.source_label SEPARATOR ', ') AS org_source
        FROM fact_characters c
            INNER JOIN ($sourceSql) src ON src.character_id = c.id
        GROUP BY c.id, c.name, c.alias, c.garou_name
        ORDER BY c.name ASC
    ");
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function hg_aorg_character_belongs(mysqli $link, int $orgId, int $characterId): bool
{
    if ($characterId <= 0) {
        return true;
    }
    if ($orgId <= 0) {
        return false;
    }

    if (hg_aorg_table_exists($link, 'bridge_characters_organizations')) {
        $stmt = $link->prepare("
            SELECT 1
            FROM bridge_characters_organizations
            WHERE organization_id = ?
              AND character_id = ?
              AND (is_active = 1 OR is_active IS NULL)
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('ii', $orgId, $characterId);
            $stmt->execute();
            $ok = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($ok) {
                return true;
            }
        }
    }

    if (hg_aorg_table_exists($link, 'bridge_characters_groups') && hg_aorg_table_exists($link, 'bridge_organizations_groups')) {
        $stmt = $link->prepare("
            SELECT 1
            FROM bridge_characters_groups bcg
                INNER JOIN bridge_organizations_groups bog
                    ON bog.group_id = bcg.group_id
                   AND bog.organization_id = ?
                   AND (bog.is_active = 1 OR bog.is_active IS NULL)
            WHERE bcg.character_id = ?
              AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('ii', $orgId, $characterId);
            $stmt->execute();
            $ok = (bool)$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($ok) {
                return true;
            }
        }
    }

    return false;
}

function hg_aorg_department_belongs(mysqli $link, int $orgId, int $departmentId): bool
{
    if ($departmentId <= 0) {
        return true;
    }
    $stmt = $link->prepare("SELECT id FROM dim_organization_departments WHERE organization_id = ? AND id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $orgId, $departmentId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

function hg_aorg_position_belongs(mysqli $link, int $orgId, int $positionId): bool
{
    if ($positionId <= 0) {
        return true;
    }
    $stmt = $link->prepare("SELECT id FROM bridge_characters_org WHERE organization_id = ? AND id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $orgId, $positionId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

function hg_aorg_department_parent_allowed(mysqli $link, int $orgId, int $departmentId, int $parentId): bool
{
    if ($parentId <= 0) {
        return true;
    }
    if ($departmentId > 0 && $departmentId === $parentId) {
        return false;
    }
    if (!hg_aorg_department_belongs($link, $orgId, $parentId)) {
        return false;
    }
    $seen = [];
    $current = $parentId;
    while ($current > 0 && !isset($seen[$current])) {
        if ($current === $departmentId) {
            return false;
        }
        $seen[$current] = true;
        $stmt = $link->prepare("SELECT parent_department_id FROM dim_organization_departments WHERE organization_id = ? AND id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $orgId, $current);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $current = (int)($row['parent_department_id'] ?? 0);
    }
    return true;
}

function hg_aorg_position_parent_allowed(mysqli $link, int $orgId, int $positionId, int $parentId): bool
{
    if ($parentId <= 0) {
        return true;
    }
    if ($positionId > 0 && $positionId === $parentId) {
        return false;
    }
    if (!hg_aorg_position_belongs($link, $orgId, $parentId)) {
        return false;
    }
    $seen = [];
    $current = $parentId;
    while ($current > 0 && !isset($seen[$current])) {
        if ($current === $positionId) {
            return false;
        }
        $seen[$current] = true;
        $stmt = $link->prepare("SELECT parent_bridge_id FROM bridge_characters_org WHERE organization_id = ? AND id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $orgId, $current);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $current = (int)($row['parent_bridge_id'] ?? 0);
    }
    return true;
}

function hg_aorg_next_sort(mysqli $link, string $table, int $orgId, string $scopeColumn, ?int $scopeId): int
{
    $safeTable = str_replace('`', '``', $table);
    if ($scopeId === null) {
        $sql = "SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_sort FROM `$safeTable` WHERE organization_id = ? AND `$scopeColumn` IS NULL";
        $stmt = $link->prepare($sql);
        if (!$stmt) {
            return 10;
        }
        $stmt->bind_param('i', $orgId);
    } else {
        $sql = "SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_sort FROM `$safeTable` WHERE organization_id = ? AND `$scopeColumn` = ?";
        $stmt = $link->prepare($sql);
        if (!$stmt) {
            return 10;
        }
        $stmt->bind_param('ii', $orgId, $scopeId);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return max(10, (int)($row['next_sort'] ?? 10));
}

function hg_aorg_unique_department_pretty(mysqli $link, int $orgId, string $name, int $departmentId = 0): string
{
    $base = function_exists('slugify_pretty_id') ? slugify_pretty_id($name) : strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
    if ($base === '') {
        $base = 'categoria';
    }
    $pretty = $base;
    $suffix = 2;
    while (true) {
        $stmt = $link->prepare("SELECT id FROM dim_organization_departments WHERE organization_id = ? AND pretty_id = ? AND id <> ? LIMIT 1");
        if (!$stmt) {
            return $pretty;
        }
        $stmt->bind_param('isi', $orgId, $pretty, $departmentId);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            return $pretty;
        }
        $pretty = $base . '-' . $suffix;
        $suffix++;
    }
}

function hg_aorg_option_label(array $row, int $depth = 0): string
{
    return str_repeat('-- ', max(0, $depth)) . (string)($row['name'] ?? '');
}

function hg_aorg_render_department_options(array $departments, int $selectedId = 0, int $excludeId = 0, bool $rootOption = true): void
{
    if ($rootOption) {
        echo "<option value='0'>Sin padre / raiz</option>";
    }
    foreach ($departments as $department) {
        $id = (int)$department['id'];
        if ($id === $excludeId) {
            continue;
        }
        $label = hg_aorg_option_label($department, (int)($department['hierarchy_level'] ?? 0));
        if ((int)($department['is_active'] ?? 1) !== 1) {
            $label .= ' [inactiva]';
        }
        echo "<option value='" . $id . "' " . ($id === $selectedId ? 'selected' : '') . ">" . hg_aorg_h($label) . "</option>";
    }
}

function hg_aorg_render_position_options(array $positions, int $selectedId = 0, int $excludeId = 0): void
{
    echo "<option value='0'>Sin superior directo</option>";
    foreach ($positions as $position) {
        $id = (int)$position['id'];
        if ($id === $excludeId) {
            continue;
        }
        $label = (string)$position['position_name'];
        $characterName = trim((string)($position['character_name'] ?? ''));
        if ($characterName !== '') {
            $label .= ' - ' . $characterName;
        } else {
            $label .= ' - Sin asignar';
        }
        if ((int)($position['is_active'] ?? 1) !== 1) {
            $label .= ' [inactiva]';
        }
        echo "<option value='" . $id . "' " . ($id === $selectedId ? 'selected' : '') . ">" . hg_aorg_h($label) . "</option>";
    }
}

function hg_aorg_render_character_options(array $characters, int $selectedId = 0, bool $emptyOption = true, string $missingLabel = ''): void
{
    if ($emptyOption) {
        echo "<option value='0'>Sin asignar</option>";
    }
    $selectedFound = $selectedId <= 0;
    foreach ($characters as $character) {
        $id = (int)$character['id'];
        if ($id === $selectedId) {
            $selectedFound = true;
        }
        $label = (string)$character['name'];
        $alias = trim((string)($character['alias'] ?? ''));
        $garouName = trim((string)($character['garou_name'] ?? ''));
        if ($alias !== '') {
            $label .= ' (' . $alias . ')';
        } elseif ($garouName !== '') {
            $label .= ' (' . $garouName . ')';
        }
        echo "<option value='" . $id . "' " . ($id === $selectedId ? 'selected' : '') . ">" . hg_aorg_h($label) . "</option>";
    }
    if (!$selectedFound) {
        $label = trim($missingLabel) !== '' ? trim($missingLabel) : ('Personaje #' . $selectedId);
        $label .= ' [fuera de la organizacion]';
        echo "<option value='" . $selectedId . "' selected>" . hg_aorg_h($label) . "</option>";
    }
}

$departmentTypes = [
    'board' => 'Bloque',
    'department' => 'Departamento',
    'unit' => 'Unidad',
    'delegation' => 'Delegacion',
    'special' => 'Especial',
    'territory' => 'Territorio',
    'other' => 'Otra',
];

if ($isAjaxRequest) {
    $payload = hg_aorg_payload();
    $action = trim((string)($payload['action'] ?? ''));
    if ($action === '') {
        hg_admin_json_error('Accion no valida', 400, ['action' => 'required']);
    }
    hg_aorg_require_write($csrfKey, $payload);

    $orgId = max(0, (int)($payload['organization_id'] ?? 0));
    if ($orgId <= 0 || !hg_aorg_fetch_organization($link, $orgId)) {
        hg_admin_json_error('Organizacion no valida', 400, ['organization_id' => 'invalid']);
    }

    if ($action === 'update_org') {
        $name = trim((string)($payload['name'] ?? ''));
        $sortOrder = max(0, (int)($payload['sort_order'] ?? 0));
        $totemId = max(0, (int)($payload['totem_id'] ?? 0));
        $color = trim((string)($payload['color'] ?? '#eeeeee'));
        $isNpc = (int)($payload['is_npc'] ?? 0) === 1 ? 1 : 0;
        $description = (string)($payload['description'] ?? '');
        if ($name === '') {
            hg_admin_json_error('El nombre de la organizacion es obligatorio.', 400, ['name' => 'required']);
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            hg_admin_json_error('El color debe usar formato hexadecimal.', 400, ['color' => 'invalid']);
        }
        $stmt = $link->prepare("
            UPDATE dim_organizations
            SET name = ?, sort_order = ?, totem_id = NULLIF(?, 0), color = ?, is_npc = ?, description = ?
            WHERE id = ?
        ");
        if (!$stmt) {
            hg_admin_json_error('No se pudo preparar la actualizacion.', 500, ['db' => $link->error]);
        }
        $stmt->bind_param('siisisi', $name, $sortOrder, $totemId, $color, $isNpc, $description, $orgId);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        if (!$ok) {
            hg_admin_json_error('No se pudo actualizar la organizacion.', 500, ['db' => $err]);
        }
        hg_update_pretty_id_if_exists($link, 'dim_organizations', $orgId, $name);
        hg_admin_json_success(['organization_id' => $orgId], 'Organizacion actualizada.');
    }

    if ($action === 'save_department') {
        if (!hg_aorg_table_exists($link, 'dim_organization_departments')) {
            hg_admin_json_error('Falta la tabla de categorias de organigrama.', 400);
        }
        $departmentId = max(0, (int)($payload['department_id'] ?? 0));
        $parentId = max(0, (int)($payload['parent_department_id'] ?? 0));
        $name = trim((string)($payload['name'] ?? ''));
        $type = (string)($payload['department_type'] ?? 'department');
        $level = max(0, min(99, (int)($payload['hierarchy_level'] ?? 1)));
        $color = trim((string)($payload['color'] ?? '#e2e8f0'));
        $description = trim((string)($payload['description'] ?? ''));
        $sortOrder = (int)($payload['sort_order'] ?? 0);
        $isActive = (int)($payload['is_active'] ?? 0) === 1 ? 1 : 0;
        if ($name === '') {
            hg_admin_json_error('El nombre de la categoria es obligatorio.', 400, ['name' => 'required']);
        }
        if (!isset($departmentTypes[$type])) {
            hg_admin_json_error('Tipo de categoria no valido.', 400, ['department_type' => 'invalid']);
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            hg_admin_json_error('Color de categoria no valido.', 400, ['color' => 'invalid']);
        }
        if ($departmentId > 0 && !hg_aorg_department_belongs($link, $orgId, $departmentId)) {
            hg_admin_json_error('La categoria no pertenece a esta organizacion.', 400);
        }
        if (!hg_aorg_department_parent_allowed($link, $orgId, $departmentId, $parentId)) {
            hg_admin_json_error('La categoria padre no es valida.', 400, ['parent_department_id' => 'invalid']);
        }
        $parentIdOrNull = $parentId > 0 ? $parentId : null;
        if ($sortOrder <= 0) {
            $sortOrder = hg_aorg_next_sort($link, 'dim_organization_departments', $orgId, 'parent_department_id', $parentIdOrNull);
        }
        $pretty = hg_aorg_unique_department_pretty($link, $orgId, $name, $departmentId);

        if ($departmentId > 0) {
            $stmt = $link->prepare("
                UPDATE dim_organization_departments
                SET parent_department_id = ?, pretty_id = ?, name = ?, department_type = ?, hierarchy_level = ?,
                    color = ?, description = ?, sort_order = ?, is_active = ?
                WHERE id = ?
                  AND organization_id = ?
            ");
            if (!$stmt) {
                hg_admin_json_error('No se pudo preparar la categoria.', 500, ['db' => $link->error]);
            }
            $stmt->bind_param('isssissiiii', $parentIdOrNull, $pretty, $name, $type, $level, $color, $description, $sortOrder, $isActive, $departmentId, $orgId);
        } else {
            $stmt = $link->prepare("
                INSERT INTO dim_organization_departments
                    (organization_id, parent_department_id, pretty_id, name, department_type, hierarchy_level, color, description, sort_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                hg_admin_json_error('No se pudo preparar la categoria.', 500, ['db' => $link->error]);
            }
            $stmt->bind_param('iisssissii', $orgId, $parentIdOrNull, $pretty, $name, $type, $level, $color, $description, $sortOrder, $isActive);
        }
        $ok = $stmt->execute();
        $err = $stmt->error;
        $newId = $departmentId > 0 ? $departmentId : (int)$stmt->insert_id;
        $stmt->close();
        if (!$ok) {
            hg_admin_json_error('No se pudo guardar la categoria.', 500, ['db' => $err]);
        }
        hg_admin_json_success(['department_id' => $newId], $departmentId > 0 ? 'Categoria actualizada.' : 'Categoria creada.');
    }

    if ($action === 'save_position' || $action === 'assign_position') {
        if (!hg_aorg_table_exists($link, 'bridge_characters_org')) {
            hg_admin_json_error('Falta la tabla de cargos de organigrama.', 400);
        }
        $positionId = max(0, (int)($payload['position_id'] ?? 0));
        if ($positionId > 0 && !hg_aorg_position_belongs($link, $orgId, $positionId)) {
            hg_admin_json_error('La posicion no pertenece a esta organizacion.', 400);
        }

        if ($action === 'assign_position') {
            $characterId = max(0, (int)($payload['character_id'] ?? 0));
            $isActive = (int)($payload['is_active'] ?? 0) === 1 ? 1 : 0;
            if ($positionId <= 0) {
                hg_admin_json_error('Selecciona una posicion valida.', 400);
            }
            if ($characterId > 0 && !hg_aorg_character_belongs($link, $orgId, $characterId)) {
                hg_admin_json_error('El personaje no pertenece a esta organizacion.', 400, ['character_id' => 'outside_organization']);
            }
            $characterIdOrNull = $characterId > 0 ? $characterId : null;
            $stmt = $link->prepare("
                UPDATE bridge_characters_org
                SET character_id = ?, is_active = ?
                WHERE id = ?
                  AND organization_id = ?
            ");
            if (!$stmt) {
                hg_admin_json_error('No se pudo preparar la asignacion.', 500, ['db' => $link->error]);
            }
            $stmt->bind_param('iiii', $characterIdOrNull, $isActive, $positionId, $orgId);
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            if (!$ok) {
                hg_admin_json_error('No se pudo asignar el personaje. Revisa si bridge_characters_org.character_id permite NULL.', 500, ['db' => $err]);
            }
            hg_admin_json_success(['position_id' => $positionId], 'Asignacion actualizada.');
        }

        $departmentId = max(0, (int)($payload['department_id'] ?? 0));
        $parentId = max(0, (int)($payload['parent_bridge_id'] ?? 0));
        $positionName = trim((string)($payload['position_name'] ?? ''));
        $positionCode = trim((string)($payload['position_code'] ?? ''));
        $scopeLabel = trim((string)($payload['scope_label'] ?? ''));
        $responsibility = trim((string)($payload['responsibility'] ?? ''));
        $level = max(0, min(99, (int)($payload['hierarchy_level'] ?? 1)));
        $sortOrder = (int)($payload['sort_order'] ?? 0);
        $isHead = (int)($payload['is_head'] ?? 0) === 1 ? 1 : 0;
        $isPrimary = (int)($payload['is_primary'] ?? 0) === 1 ? 1 : 0;
        $isActive = (int)($payload['is_active'] ?? 0) === 1 ? 1 : 0;
        $characterId = max(0, (int)($payload['character_id'] ?? 0));
        if ($positionId <= 0 && $characterId > 0 && !hg_aorg_character_belongs($link, $orgId, $characterId)) {
            hg_admin_json_error('El personaje no pertenece a esta organizacion.', 400, ['character_id' => 'outside_organization']);
        }
        $characterIdOrNull = $characterId > 0 ? $characterId : null;

        if ($departmentId <= 0 || !hg_aorg_department_belongs($link, $orgId, $departmentId)) {
            hg_admin_json_error('Selecciona un departamento valido.', 400, ['department_id' => 'invalid']);
        }
        if ($positionName === '') {
            hg_admin_json_error('El nombre del cargo es obligatorio.', 400, ['position_name' => 'required']);
        }
        if (!hg_aorg_position_parent_allowed($link, $orgId, $positionId, $parentId)) {
            hg_admin_json_error('El superior directo no es valido.', 400, ['parent_bridge_id' => 'invalid']);
        }
        $parentIdOrNull = $parentId > 0 ? $parentId : null;
        if ($sortOrder <= 0) {
            $sortOrder = hg_aorg_next_sort($link, 'bridge_characters_org', $orgId, 'department_id', $departmentId);
        }

        if ($positionId > 0) {
            $stmt = $link->prepare("
                UPDATE bridge_characters_org
                SET department_id = ?, parent_bridge_id = ?, hierarchy_level = ?, position_name = ?, position_code = ?,
                    scope_label = ?, responsibility = ?, is_head = ?, is_primary = ?, is_active = ?, sort_order = ?
                WHERE id = ?
                  AND organization_id = ?
            ");
            if (!$stmt) {
                hg_admin_json_error('No se pudo preparar el cargo.', 500, ['db' => $link->error]);
            }
            $stmt->bind_param('iiissssiiiiii', $departmentId, $parentIdOrNull, $level, $positionName, $positionCode, $scopeLabel, $responsibility, $isHead, $isPrimary, $isActive, $sortOrder, $positionId, $orgId);
        } else {
            $stmt = $link->prepare("
                INSERT INTO bridge_characters_org
                    (character_id, organization_id, department_id, parent_bridge_id, hierarchy_level, position_name,
                     position_code, scope_label, responsibility, is_head, is_primary, is_active, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                hg_admin_json_error('No se pudo preparar el cargo.', 500, ['db' => $link->error]);
            }
            $stmt->bind_param('iiiiissssiiii', $characterIdOrNull, $orgId, $departmentId, $parentIdOrNull, $level, $positionName, $positionCode, $scopeLabel, $responsibility, $isHead, $isPrimary, $isActive, $sortOrder);
        }
        $ok = $stmt->execute();
        $err = $stmt->error;
        $newId = $positionId > 0 ? $positionId : (int)$stmt->insert_id;
        $stmt->close();
        if (!$ok) {
            hg_admin_json_error('No se pudo guardar el cargo. Revisa si bridge_characters_org.character_id permite NULL.', 500, ['db' => $err]);
        }
        hg_admin_json_success(['position_id' => $newId], $positionId > 0 ? 'Cargo actualizado.' : 'Cargo creado.');
    }

    hg_admin_json_error('Accion no reconocida.', 400, ['action' => $action]);
}

$organizations = hg_aorg_fetch_organizations($link);
$selectedOrgId = max(0, (int)($_GET['org_id'] ?? ($_POST['organization_id'] ?? 0)));
if ($selectedOrgId <= 0 && !empty($organizations)) {
    $selectedOrgId = (int)$organizations[0]['id'];
}
$selectedOrg = hg_aorg_fetch_organization($link, $selectedOrgId);
if (!$selectedOrg && !empty($organizations)) {
    $selectedOrgId = (int)$organizations[0]['id'];
    $selectedOrg = hg_aorg_fetch_organization($link, $selectedOrgId);
}

$departmentsReady = hg_aorg_table_exists($link, 'dim_organization_departments');
$positionsReady = hg_aorg_table_exists($link, 'bridge_characters_org');
$totems = hg_aorg_fetch_totems($link);
$departments = $selectedOrgId > 0 ? hg_aorg_fetch_departments($link, $selectedOrgId, false) : [];
$activeDepartments = array_values(array_filter($departments, static fn(array $d): bool => (int)($d['is_active'] ?? 0) === 1));
$positions = $selectedOrgId > 0 ? hg_aorg_fetch_positions($link, $selectedOrgId) : [];
$activePositions = array_values(array_filter($positions, static fn(array $p): bool => (int)($p['is_active'] ?? 0) === 1));
$characters = hg_aorg_fetch_characters($link, $selectedOrgId);
$hasChart = !empty($activeDepartments) || !empty($activePositions);

$actions = '';
if ($selectedOrg) {
    $actions .= '<a class="btn" href="' . hg_aorg_h(hg_aorg_org_url($link, (int)$selectedOrg['id'])) . '" target="_blank">Ver ficha</a>';
    if ($hasChart) {
        $actions .= ' <a class="btn" href="' . hg_aorg_h(hg_aorg_chart_url($link, (int)$selectedOrg['id'])) . '" target="_blank">Ver organigrama</a>';
    }
}
admin_panel_open('Organizaciones', $actions);
?>

<style>
.aorg-top{display:grid;grid-template-columns:minmax(260px,.75fr) minmax(360px,1.25fr);gap:12px;margin-bottom:12px}
.aorg-compact-form{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:8px;align-items:end}
.aorg-compact-form label{display:grid;gap:3px;color:#dfefff;font-size:11px}
.aorg-compact-form input,.aorg-compact-form select,.aorg-compact-form textarea{box-sizing:border-box;width:100%}
.aorg-wide{grid-column:1/-1}
.aorg-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:10px 0}
.aorg-tab{border:1px solid #17458b;background:#06164a;color:#dff7ff;border-radius:6px;padding:7px 10px;cursor:pointer}
.aorg-tab.active{background:#003b8f;border-color:#33cccc;color:#fff}
.aorg-panel{display:none}
.aorg-panel.active{display:block}
.aorg-table-wrap{overflow:auto;border:1px solid #000088;border-radius:8px;margin-top:8px}
.aorg-table{width:100%;border-collapse:collapse;font-size:11px}
.aorg-table th,.aorg-table td{border-bottom:1px solid #123777;background:#05014e;padding:5px 6px;vertical-align:top;text-align:left}
.aorg-table th{background:#050b36;color:#33cccc;position:sticky;top:0;z-index:1}
.aorg-table input,.aorg-table select,.aorg-table textarea{box-sizing:border-box;width:100%;font-size:11px}
.aorg-table textarea{min-height:48px}
.aorg-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.aorg-mini{max-width:78px}
.aorg-medium{min-width:160px}
.aorg-large{min-width:220px}
.aorg-status{display:inline-flex;align-items:center;gap:4px;white-space:nowrap}
.aorg-muted{color:#a9c8f5}
.aorg-badges{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 10px}
.aorg-badge{padding:4px 9px;border:1px solid #17366e;border-radius:999px;background:#071b4a;color:#dfefff}
.aorg-details{margin-top:4px}
.aorg-details summary{cursor:pointer;color:#9fe7ff}
.aorg-off{opacity:.55}
@media(max-width:920px){.aorg-top,.aorg-compact-form{grid-template-columns:1fr}.aorg-table th,.aorg-table td{white-space:normal}}
</style>

<?php if (!$selectedOrg): ?>
    <div class="err">No hay organizaciones disponibles. Crea la organizacion desde <a href="/talim?s=admin_groups">Grupos y manadas</a>.</div>
    <?php admin_panel_close(); return; ?>
<?php endif; ?>

<div class="aorg-badges">
    <span class="aorg-badge">Categorias: <?= (int)count($activeDepartments) ?></span>
    <span class="aorg-badge">Posiciones: <?= (int)count($activePositions) ?></span>
    <span class="aorg-badge">Asignadas: <?= (int)count(array_filter($activePositions, static fn(array $p): bool => (int)($p['character_id'] ?? 0) > 0)) ?></span>
</div>

<div class="aorg-top">
    <fieldset class="bioSeccion">
        <legend>&nbsp;Organizacion&nbsp;</legend>
        <form method="get" class="aorg-compact-form">
            <input type="hidden" name="s" value="admin_organizations">
            <label class="aorg-wide">
                Seleccionar
                <select name="org_id" onchange="this.form.submit()">
                    <?php foreach ($organizations as $org): ?>
                        <?php $chartCount = (int)$org['chart_departments'] + (int)$org['chart_positions']; ?>
                        <option value="<?= (int)$org['id'] ?>" <?= (int)$org['id'] === $selectedOrgId ? 'selected' : '' ?>>
                            <?= hg_aorg_h((string)$org['name']) ?><?= $chartCount > 0 ? ' [' . $chartCount . ']' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="aorg-wide aorg-actions">
                <button class="btn" type="submit">Cargar</button>
                <a class="btn" href="/talim?s=admin_groups">Abrir grupos y manadas</a>
            </div>
        </form>
    </fieldset>

    <fieldset class="bioSeccion">
        <legend>&nbsp;Datos basicos&nbsp;</legend>
        <form class="aorg-compact-form js-aorg-form" data-action="update_org">
            <input type="hidden" name="organization_id" value="<?= (int)$selectedOrg['id'] ?>">
            <label>
                Nombre
                <input type="text" name="name" maxlength="100" required value="<?= hg_aorg_h((string)$selectedOrg['name']) ?>">
            </label>
            <label>
                Orden
                <input type="number" name="sort_order" min="0" value="<?= (int)$selectedOrg['sort_order'] ?>">
            </label>
            <label>
                Totem
                <select name="totem_id">
                    <?php foreach ($totems as $totemId => $totemName): ?>
                        <option value="<?= (int)$totemId ?>" <?= (int)$totemId === (int)$selectedOrg['totem_id'] ? 'selected' : '' ?>><?= hg_aorg_h($totemName) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Color
                <?php $orgColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$selectedOrg['color']) ? (string)$selectedOrg['color'] : '#eeeeee'; ?>
                <input type="color" name="color" value="<?= hg_aorg_h($orgColor) ?>">
            </label>
            <label class="aorg-wide">
                Descripcion
                <textarea name="description" rows="3"><?= hg_aorg_h((string)$selectedOrg['description']) ?></textarea>
            </label>
            <div class="aorg-wide aorg-actions">
                <label class="aorg-status"><input type="checkbox" name="is_npc" value="1" <?= (int)$selectedOrg['is_npc'] === 1 ? 'checked' : '' ?>> NPC / secundaria</label>
                <button class="btn btn-green" type="submit">Guardar</button>
            </div>
        </form>
    </fieldset>
</div>

<?php if (!$departmentsReady || !$positionsReady): ?>
    <fieldset class="bioSeccion">
        <legend>&nbsp;Organigrama&nbsp;</legend>
        <div class="err">Faltan las tablas del organigrama. Preparalas desde <a href="/talim?s=admin_org_chart_schema">schema de organigramas</a>.</div>
    </fieldset>
    <?php admin_panel_close(); return; ?>
<?php endif; ?>

<div class="aorg-tabs" role="tablist">
    <button class="aorg-tab active" type="button" data-tab="structure">1. Estructura y posiciones</button>
    <button class="aorg-tab" type="button" data-tab="assign">2. Asignar personajes</button>
</div>

<section class="aorg-panel active" id="aorg-tab-structure">
    <fieldset class="bioSeccion">
        <legend>&nbsp;Categorias / bloques&nbsp;</legend>
        <form class="aorg-compact-form js-aorg-form" data-action="save_department" data-reload="1">
            <input type="hidden" name="organization_id" value="<?= (int)$selectedOrg['id'] ?>">
            <input type="hidden" name="department_id" value="0">
            <label>
                Nueva categoria
                <input type="text" name="name" maxlength="150" required placeholder="Ej. Departamentos Federales">
            </label>
            <label>
                Padre
                <select name="parent_department_id"><?php hg_aorg_render_department_options($departments); ?></select>
            </label>
            <label>
                Tipo
                <select name="department_type">
                    <?php foreach ($departmentTypes as $typeKey => $typeLabel): ?>
                        <option value="<?= hg_aorg_h($typeKey) ?>"><?= hg_aorg_h($typeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Nivel
                <input type="number" class="aorg-mini" name="hierarchy_level" min="0" max="99" value="1">
            </label>
            <label>
                Color
                <input type="color" name="color" value="#33cccc">
            </label>
            <label>
                Orden
                <input type="number" name="sort_order" value="0">
            </label>
            <label class="aorg-wide">
                Descripcion
                <input type="text" name="description" maxlength="500">
            </label>
            <div class="aorg-wide aorg-actions">
                <label class="aorg-status"><input type="checkbox" name="is_active" value="1" checked> Activa</label>
                <button class="btn btn-green" type="submit">Crear categoria</button>
            </div>
        </form>

        <div class="aorg-table-wrap">
            <table class="aorg-table">
                <thead><tr><th>Nombre</th><th>Padre</th><th>Tipo</th><th>Nivel</th><th>Orden</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($departments as $department): ?>
                    <?php $departmentColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$department['color']) ? (string)$department['color'] : '#e2e8f0'; ?>
                    <tr class="js-aorg-row <?= (int)$department['is_active'] === 1 ? '' : 'aorg-off' ?>" data-action="save_department">
                            <td class="aorg-large">
                                <input type="hidden" name="organization_id" value="<?= (int)$selectedOrg['id'] ?>">
                                <input type="hidden" name="department_id" value="<?= (int)$department['id'] ?>">
                                <input type="text" name="name" maxlength="150" required value="<?= hg_aorg_h((string)$department['name']) ?>">
                            </td>
                            <td class="aorg-large"><select name="parent_department_id"><?php hg_aorg_render_department_options($departments, (int)$department['parent_department_id'], (int)$department['id']); ?></select></td>
                            <td><select name="department_type"><?php foreach ($departmentTypes as $typeKey => $typeLabel): ?><option value="<?= hg_aorg_h($typeKey) ?>" <?= $typeKey === (string)$department['department_type'] ? 'selected' : '' ?>><?= hg_aorg_h($typeLabel) ?></option><?php endforeach; ?></select></td>
                            <td><input class="aorg-mini" type="number" name="hierarchy_level" min="0" max="99" value="<?= (int)$department['hierarchy_level'] ?>"></td>
                            <td><input class="aorg-mini" type="number" name="sort_order" value="<?= (int)$department['sort_order'] ?>"></td>
                            <td><label class="aorg-status"><input type="checkbox" name="is_active" value="1" <?= (int)$department['is_active'] === 1 ? 'checked' : '' ?>> Activa</label></td>
                            <td>
                                <input type="hidden" name="color" value="<?= hg_aorg_h($departmentColor) ?>">
                                <input type="hidden" name="description" value="<?= hg_aorg_h((string)$department['description']) ?>">
                                <button class="btn js-aorg-save-row" type="button">Guardar</button>
                            </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </fieldset>

    <fieldset class="bioSeccion">
        <legend>&nbsp;Posiciones&nbsp;</legend>
        <?php if (empty($activeDepartments)): ?>
            <div class="err">Crea al menos una categoria activa para poder crear posiciones.</div>
        <?php else: ?>
            <form class="aorg-compact-form js-aorg-form" data-action="save_position" data-reload="1">
                <input type="hidden" name="organization_id" value="<?= (int)$selectedOrg['id'] ?>">
                <input type="hidden" name="position_id" value="0">
                <input type="hidden" name="character_id" value="0">
                <label>
                    Nueva posicion
                    <input type="text" name="position_name" maxlength="150" required placeholder="Ej. Director de I+D">
                </label>
                <label>
                    Categoria
                    <select name="department_id"><?php hg_aorg_render_department_options($activeDepartments, 0, 0, false); ?></select>
                </label>
                <label>
                    Superior
                    <select name="parent_bridge_id"><?php hg_aorg_render_position_options($positions); ?></select>
                </label>
                <label>
                    Nivel
                    <input type="number" class="aorg-mini" name="hierarchy_level" min="0" max="99" value="1">
                </label>
                <label>
                    Codigo
                    <input type="text" name="position_code" maxlength="120">
                </label>
                <label>
                    Ambito
                    <input type="text" name="scope_label" maxlength="150">
                </label>
                <label>
                    Orden
                    <input type="number" name="sort_order" value="0">
                </label>
                <label class="aorg-wide">
                    Responsabilidad
                    <input type="text" name="responsibility" maxlength="500">
                </label>
                <div class="aorg-wide aorg-actions">
                    <label class="aorg-status"><input type="checkbox" name="is_head" value="1"> Jefatura</label>
                    <label class="aorg-status"><input type="checkbox" name="is_primary" value="1" checked> Principal</label>
                    <label class="aorg-status"><input type="checkbox" name="is_active" value="1" checked> Activa</label>
                    <button class="btn btn-green" type="submit">Crear posicion</button>
                </div>
            </form>
        <?php endif; ?>

        <div class="aorg-table-wrap">
            <table class="aorg-table">
                <thead><tr><th>Cargo</th><th>Categoria</th><th>Superior</th><th>Nivel</th><th>Orden</th><th>Flags</th><th>Asignado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($positions as $position): ?>
                    <tr class="js-aorg-row <?= (int)$position['is_active'] === 1 ? '' : 'aorg-off' ?>" data-action="save_position">
                            <td class="aorg-large">
                                <input type="hidden" name="organization_id" value="<?= (int)$selectedOrg['id'] ?>">
                                <input type="hidden" name="position_id" value="<?= (int)$position['id'] ?>">
                                <input type="hidden" name="character_id" value="<?= (int)$position['character_id'] ?>">
                                <input type="text" name="position_name" maxlength="150" required value="<?= hg_aorg_h((string)$position['position_name']) ?>">
                                <details class="aorg-details">
                                    <summary>Detalles</summary>
                                    <label>Codigo <input type="text" name="position_code" maxlength="120" value="<?= hg_aorg_h((string)$position['position_code']) ?>"></label>
                                    <label>Ambito <input type="text" name="scope_label" maxlength="150" value="<?= hg_aorg_h((string)$position['scope_label']) ?>"></label>
                                    <label>Responsabilidad <textarea name="responsibility"><?= hg_aorg_h((string)$position['responsibility']) ?></textarea></label>
                                </details>
                            </td>
                            <td class="aorg-medium"><select name="department_id"><?php hg_aorg_render_department_options($departments, (int)$position['department_id'], 0, false); ?></select></td>
                            <td class="aorg-large"><select name="parent_bridge_id"><?php hg_aorg_render_position_options($positions, (int)$position['parent_bridge_id'], (int)$position['id']); ?></select></td>
                            <td><input class="aorg-mini" type="number" name="hierarchy_level" min="0" max="99" value="<?= (int)$position['hierarchy_level'] ?>"></td>
                            <td><input class="aorg-mini" type="number" name="sort_order" value="<?= (int)$position['sort_order'] ?>"></td>
                            <td>
                                <label class="aorg-status"><input type="checkbox" name="is_head" value="1" <?= (int)$position['is_head'] === 1 ? 'checked' : '' ?>> Jef.</label>
                                <label class="aorg-status"><input type="checkbox" name="is_primary" value="1" <?= (int)$position['is_primary'] === 1 ? 'checked' : '' ?>> Prin.</label>
                                <label class="aorg-status"><input type="checkbox" name="is_active" value="1" <?= (int)$position['is_active'] === 1 ? 'checked' : '' ?>> Act.</label>
                            </td>
                            <td><?= trim((string)$position['character_name']) !== '' ? hg_aorg_h((string)$position['character_name']) : '<span class="aorg-muted">Sin asignar</span>' ?></td>
                            <td><button class="btn js-aorg-save-row" type="button">Guardar</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </fieldset>
</section>

<section class="aorg-panel" id="aorg-tab-assign">
    <fieldset class="bioSeccion">
        <legend>&nbsp;Asignar personajes a posiciones&nbsp;</legend>
        <div class="aorg-actions" style="margin-bottom:8px">
            <label>Filtro rapido <input class="inp" type="text" id="aorgAssignmentFilter" placeholder="Cargo, personaje o departamento"></label>
        </div>
        <div class="aorg-table-wrap">
            <table class="aorg-table" id="aorgAssignmentTable">
                <thead><tr><th>Posicion</th><th>Departamento</th><th>Personaje</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($positions as $position): ?>
                    <?php $search = strtolower((string)$position['position_name'] . ' ' . (string)$position['department_name'] . ' ' . (string)$position['character_name']); ?>
                    <tr data-search="<?= hg_aorg_h($search) ?>" class="js-aorg-row <?= (int)$position['is_active'] === 1 ? '' : 'aorg-off' ?>" data-action="assign_position">
                            <td class="aorg-large">
                                <input type="hidden" name="organization_id" value="<?= (int)$selectedOrg['id'] ?>">
                                <input type="hidden" name="position_id" value="<?= (int)$position['id'] ?>">
                                <?= hg_aorg_h((string)$position['position_name']) ?>
                            </td>
                            <td><?= hg_aorg_h((string)$position['department_name']) ?></td>
                            <td><select name="character_id"><?php hg_aorg_render_character_options($characters, (int)$position['character_id'], true, (string)$position['character_name']); ?></select></td>
                            <td><label class="aorg-status"><input type="checkbox" name="is_active" value="1" <?= (int)$position['is_active'] === 1 ? 'checked' : '' ?>> Activa</label></td>
                            <td><button class="btn js-aorg-save-row" type="button">Guardar</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </fieldset>
</section>

<?php $adminHttpJs = '/assets/js/admin/admin-http.js'; $adminHttpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . $adminHttpJs) ?: time(); ?>
<script>window.ADMIN_CSRF_TOKEN = <?= json_encode($csrf, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?= hg_aorg_h($adminHttpJs) ?>?v=<?= (int)$adminHttpJsVer ?>"></script>
<script>
(function(){
    const endpoint = '/talim?s=admin_organizations&ajax=1';
    const tabs = Array.from(document.querySelectorAll('.aorg-tab'));
    const panels = {
        structure: document.getElementById('aorg-tab-structure'),
        assign: document.getElementById('aorg-tab-assign')
    };

    tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            tabs.forEach(tab => tab.classList.remove('active'));
            btn.classList.add('active');
            Object.values(panels).forEach(panel => panel && panel.classList.remove('active'));
            const panel = panels[btn.dataset.tab || 'structure'];
            if (panel) panel.classList.add('active');
        });
    });

    function formPayload(form) {
        const data = { action: form.dataset.action || '' };
        const fd = new FormData(form);
        fd.forEach((value, key) => { data[key] = value; });
        form.querySelectorAll('input[type="checkbox"]').forEach(input => {
            data[input.name] = input.checked ? '1' : '0';
        });
        return data;
    }

    function containerPayload(container) {
        const data = { action: container.dataset.action || '' };
        container.querySelectorAll('input, select, textarea').forEach(input => {
            if (!input.name) return;
            if (input.type === 'checkbox') {
                data[input.name] = input.checked ? '1' : '0';
            } else {
                data[input.name] = input.value;
            }
        });
        return data;
    }

    document.querySelectorAll('.js-aorg-form').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            try {
                const payload = formPayload(form);
                const result = await window.HGAdminHttp.postAction(endpoint, payload.action, payload, { loadingEl: button });
                window.HGAdminHttp.notify(result.message || 'Guardado', 'success');
                if (form.dataset.reload === '1') {
                    setTimeout(() => window.location.reload(), 350);
                }
            } catch (error) {
                window.HGAdminHttp.notify(window.HGAdminHttp.errorMessage(error), 'error', 4200);
            }
        });
    });

    document.querySelectorAll('.js-aorg-save-row').forEach(button => {
        button.addEventListener('click', async () => {
            const row = button.closest('.js-aorg-row');
            if (!row) return;
            try {
                const payload = containerPayload(row);
                const result = await window.HGAdminHttp.postAction(endpoint, payload.action, payload, { loadingEl: button });
                window.HGAdminHttp.notify(result.message || 'Guardado', 'success');
            } catch (error) {
                window.HGAdminHttp.notify(window.HGAdminHttp.errorMessage(error), 'error', 4200);
            }
        });
    });

    const assignmentFilter = document.getElementById('aorgAssignmentFilter');
    if (assignmentFilter) {
        assignmentFilter.addEventListener('input', () => {
            const q = (assignmentFilter.value || '').trim().toLowerCase();
            document.querySelectorAll('#aorgAssignmentTable tbody tr').forEach(row => {
                row.style.display = !q || (row.dataset.search || '').includes(q) ? '' : 'none';
            });
        });
    }
})();
</script>
<?php admin_panel_close(); ?>
