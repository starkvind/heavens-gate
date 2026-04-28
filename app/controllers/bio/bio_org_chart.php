<?php
setMetaFromPage(
    "Organigrama | Heaven's Gate",
    "Organigrama de clanes y organizaciones.",
    null,
    'website'
);

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!$link) {
    hg_public_log_error('bio_org_chart', 'missing DB connection');
    hg_public_render_error(
        'Organigrama no disponible',
        'No se pudo cargar el organigrama en este momento.'
    );
    return;
}

if (!function_exists('hg_bio_org_chart_h')) {
    function hg_bio_org_chart_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_bio_org_chart_table_exists')) {
    function hg_bio_org_chart_table_exists(mysqli $link, string $table): bool
    {
        $stmt = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return (int)$count > 0;
    }
}

if (!function_exists('hg_bio_org_chart_get_organization')) {
    function hg_bio_org_chart_get_organization(mysqli $link, string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            $raw = 'justicia-metalica';
        }

        if (preg_match('/^\d+$/', $raw)) {
            $stmt = $link->prepare("
                SELECT id, pretty_id, name, color, description
                FROM dim_organizations
                WHERE id = ?
                LIMIT 1
            ");
            if (!$stmt) {
                return null;
            }
            $id = (int)$raw;
            $stmt->bind_param('i', $id);
        } else {
            $stmt = $link->prepare("
                SELECT id, pretty_id, name, color, description
                FROM dim_organizations
                WHERE pretty_id = ? OR name = ?
                LIMIT 1
            ");
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('ss', $raw, $raw);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('hg_bio_org_chart_get_organization_options')) {
    function hg_bio_org_chart_get_organization_options(mysqli $link): array
    {
        $sql = "
            SELECT
                o.id,
                o.pretty_id,
                o.name,
                COUNT(DISTINCT d.id) AS department_count,
                COUNT(DISTINCT b.id) AS role_count
            FROM dim_organizations o
                LEFT JOIN dim_organization_departments d
                    ON d.organization_id = o.id
                    AND d.is_active = 1
                LEFT JOIN bridge_characters_org b
                    ON b.organization_id = o.id
                    AND b.is_active = 1
            GROUP BY o.id, o.pretty_id, o.name, o.sort_order
            HAVING department_count > 0 OR role_count > 0
            ORDER BY o.sort_order ASC, o.name ASC
        ";

        $options = [];
        $result = $link->query($sql);
        if (!$result) {
            return $options;
        }

        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['department_count'] = (int)$row['department_count'];
            $row['role_count'] = (int)$row['role_count'];
            $options[] = $row;
        }

        return $options;
    }
}

if (!function_exists('hg_bio_org_chart_primary_department')) {
    function hg_bio_org_chart_primary_department(array $departments, int $departmentId): array
    {
        $currentId = $departmentId;
        $best = $departments[$departmentId] ?? null;
        $seen = [];

        while ($currentId > 0 && isset($departments[$currentId]) && !isset($seen[$currentId])) {
            $seen[$currentId] = true;
            $current = $departments[$currentId];
            $type = (string)($current['department_type'] ?? '');
            if (in_array($type, ['department', 'delegation', 'special', 'territory'], true)) {
                $best = $current;
            }
            $currentId = (int)($current['parent_department_id'] ?? 0);
        }

        return is_array($best) ? $best : [];
    }
}

if (!function_exists('hg_bio_org_chart_nearest_department_head')) {
    function hg_bio_org_chart_nearest_department_head(array $departments, array $departmentHeadRoleIds, int $departmentId, int $excludeRoleId = 0): int
    {
        $currentId = $departmentId;
        $seen = [];

        while ($currentId > 0 && isset($departments[$currentId]) && !isset($seen[$currentId])) {
            $seen[$currentId] = true;
            $headRoleId = (int)($departmentHeadRoleIds[$currentId] ?? 0);
            if ($headRoleId > 0 && $headRoleId !== $excludeRoleId) {
                return $headRoleId;
            }
            $currentId = (int)($departments[$currentId]['parent_department_id'] ?? 0);
        }

        return 0;
    }
}

if (!function_exists('hg_bio_org_chart_visible_department_parent')) {
    function hg_bio_org_chart_visible_department_parent(array $departments, array $visibleDepartmentIds, int $departmentId): int
    {
        $currentId = $departmentId;
        $seen = [];

        while ($currentId > 0 && isset($departments[$currentId]) && !isset($seen[$currentId])) {
            $seen[$currentId] = true;
            if (!empty($visibleDepartmentIds[$currentId])) {
                return $currentId;
            }
            $currentId = (int)($departments[$currentId]['parent_department_id'] ?? 0);
        }

        return 0;
    }
}

if (!function_exists('hg_bio_org_chart_role_bucket_key')) {
    function hg_bio_org_chart_role_bucket_key(array $role): string
    {
        return (int)($role['character_id'] ?? 0) . '|' . (int)($role['department_id'] ?? 0);
    }
}

if (!function_exists('hg_bio_org_chart_role_sort_stamp')) {
    function hg_bio_org_chart_role_sort_stamp(array $role): string
    {
        $updatedAt = trim((string)($role['updated_at'] ?? ''));
        if ($updatedAt !== '') {
            return $updatedAt;
        }

        return trim((string)($role['created_at'] ?? ''));
    }
}

$organizationRaw = trim((string)($_GET['org'] ?? $_GET['b'] ?? 'justicia-metalica'));
$organization = hg_bio_org_chart_get_organization($link, $organizationRaw);
if (!$organization) {
    hg_public_render_not_found(
        'Organizacion no encontrada',
        'No se encontro la organizacion solicitada.',
        true
    );
    return;
}

if (
    !hg_bio_org_chart_table_exists($link, 'dim_organization_departments')
    || !hg_bio_org_chart_table_exists($link, 'bridge_characters_org')
) {
    hg_public_render_error(
        'Organigrama no preparado',
        'Faltan las tablas dim_organization_departments y bridge_characters_org. Preparalas desde /talim?s=admin_org_chart_schema.',
        500,
        true
    );
    return;
}

$organizationOptions = hg_bio_org_chart_get_organization_options($link);

$orgId = (int)$organization['id'];
$orgPretty = (string)($organization['pretty_id'] ?? '');
$orgName = (string)($organization['name'] ?? '');
$orgChartNavName = $orgName;
$orgChartNavHref = pretty_url($link, 'dim_organizations', '/organizations', $orgId);
$orgColor = (string)($organization['color'] ?? '#d0e6ff');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $orgColor)) {
    $orgColor = '#d0e6ff';
}

$hasCurrentOrganizationOption = false;
foreach ($organizationOptions as $option) {
    if ((int)($option['id'] ?? 0) === $orgId) {
        $hasCurrentOrganizationOption = true;
        break;
    }
}
if (!$hasCurrentOrganizationOption) {
    array_unshift($organizationOptions, [
        'id' => $orgId,
        'pretty_id' => $orgPretty,
        'name' => $orgName,
        'department_count' => 0,
        'role_count' => 0,
    ]);
}

$departments = [];
$departmentChildren = [];
$stmtDept = $link->prepare("
    SELECT id, organization_id, parent_department_id, pretty_id, name, department_type, hierarchy_level, color, description, sort_order
    FROM dim_organization_departments
    WHERE organization_id = ?
      AND is_active = 1
    ORDER BY hierarchy_level ASC, sort_order ASC, name ASC
");
if ($stmtDept) {
    $stmtDept->bind_param('i', $orgId);
    $stmtDept->execute();
    $resultDept = $stmtDept->get_result();
    while ($row = $resultDept->fetch_assoc()) {
        $id = (int)$row['id'];
        $parentId = (int)($row['parent_department_id'] ?? 0);
        $row['id'] = $id;
        $row['parent_department_id'] = $parentId;
        $row['hierarchy_level'] = (int)($row['hierarchy_level'] ?? 1);
        $row['sort_order'] = (int)($row['sort_order'] ?? 0);
        $departments[$id] = $row;
        if ($parentId > 0) {
            if (!isset($departmentChildren[$parentId])) {
                $departmentChildren[$parentId] = [];
            }
            $departmentChildren[$parentId][] = $id;
        }
    }
    $stmtDept->close();
}

$roles = [];
$rolesById = [];
$stmtRoles = $link->prepare("
    SELECT
        b.id,
        b.character_id,
        b.organization_id,
        b.department_id,
        b.parent_bridge_id,
        b.hierarchy_level,
        b.position_name,
        b.position_code,
        b.scope_label,
        b.responsibility,
        b.is_head,
        b.is_primary,
        b.sort_order,
        b.created_at,
        b.updated_at,
        p.pretty_id AS character_pretty_id,
        p.name AS character_name,
        p.alias,
        p.garou_name,
        p.gender,
        p.image_url,
        p.rank,
        COALESCE(db.name, '') AS breed_name,
        COALESCE(da.name, '') AS auspice_name,
        COALESCE(dt.name, '') AS tribe_name,
        d.name AS department_name,
        d.department_type,
        d.color AS department_color,
        d.parent_department_id,
        pd.department_type AS parent_department_type,
        pd.name AS parent_department_name
    FROM bridge_characters_org b
        INNER JOIN fact_characters p ON p.id = b.character_id
        LEFT JOIN dim_breeds db ON db.id = p.breed_id
        LEFT JOIN dim_auspices da ON da.id = p.auspice_id
        LEFT JOIN dim_tribes dt ON dt.id = p.tribe_id
        LEFT JOIN dim_organization_departments d ON d.id = b.department_id
        LEFT JOIN dim_organization_departments pd ON pd.id = d.parent_department_id
    WHERE b.organization_id = ?
      AND b.is_active = 1
    ORDER BY b.hierarchy_level ASC, b.sort_order ASC, p.name ASC
");
if ($stmtRoles) {
    $stmtRoles->bind_param('i', $orgId);
    $stmtRoles->execute();
    $resultRoles = $stmtRoles->get_result();
    $rawRoles = [];
    $rawRolesById = [];
    while ($row = $resultRoles->fetch_assoc()) {
        $id = (int)$row['id'];
        $row['id'] = $id;
        $row['character_id'] = (int)$row['character_id'];
        $row['department_id'] = (int)($row['department_id'] ?? 0);
        $row['parent_bridge_id'] = (int)($row['parent_bridge_id'] ?? 0);
        $row['hierarchy_level'] = (int)($row['hierarchy_level'] ?? 1);
        $row['parent_department_id'] = (int)($row['parent_department_id'] ?? 0);
        $row['is_primary'] = (int)($row['is_primary'] ?? 0);
        $row['sort_order'] = (int)($row['sort_order'] ?? 0);
        $row['avatar_url'] = hg_character_avatar_url($row['image_url'] ?? '', $row['gender'] ?? '');
        $row['href'] = pretty_url($link, 'fact_characters', '/characters', (int)$row['character_id']);
        $rawRoles[] = $row;
        $rawRolesById[$id] = $row;
    }
    $stmtRoles->close();

    $canonicalRoleIdsByBucket = [];
    foreach ($rawRoles as $role) {
        $bucketKey = hg_bio_org_chart_role_bucket_key($role);
        if (!isset($canonicalRoleIdsByBucket[$bucketKey])) {
            $canonicalRoleIdsByBucket[$bucketKey] = (int)$role['id'];
            continue;
        }

        $currentId = (int)$canonicalRoleIdsByBucket[$bucketKey];
        $current = $rawRolesById[$currentId] ?? null;
        if (!is_array($current)) {
            $canonicalRoleIdsByBucket[$bucketKey] = (int)$role['id'];
            continue;
        }

        $keepCurrent = false;
        if ((int)$current['is_primary'] !== (int)$role['is_primary']) {
            $keepCurrent = (int)$current['is_primary'] > (int)$role['is_primary'];
        } else {
            $currentStamp = hg_bio_org_chart_role_sort_stamp($current);
            $candidateStamp = hg_bio_org_chart_role_sort_stamp($role);
            if ($currentStamp !== '' || $candidateStamp !== '') {
                if ($currentStamp > $candidateStamp) {
                    $keepCurrent = true;
                } elseif ($currentStamp === $candidateStamp) {
                    $keepCurrent = (int)$current['id'] > (int)$role['id'];
                }
            } else {
                $keepCurrent = (int)$current['id'] > (int)$role['id'];
            }
        }

        if (!$keepCurrent) {
            $canonicalRoleIdsByBucket[$bucketKey] = (int)$role['id'];
        }
    }

    $roleIdRemap = [];
    foreach ($rawRoles as $role) {
        $canonicalRoleId = (int)($canonicalRoleIdsByBucket[hg_bio_org_chart_role_bucket_key($role)] ?? 0);
        if ($canonicalRoleId > 0) {
            $roleIdRemap[(int)$role['id']] = $canonicalRoleId;
        }
    }

    foreach ($rawRoles as $role) {
        $roleId = (int)$role['id'];
        if (($roleIdRemap[$roleId] ?? 0) !== $roleId) {
            continue;
        }

        $parentBridgeId = (int)($role['parent_bridge_id'] ?? 0);
        if ($parentBridgeId > 0 && isset($roleIdRemap[$parentBridgeId])) {
            $role['parent_bridge_id'] = (int)$roleIdRemap[$parentBridgeId];
        }

        $roles[] = $role;
        $rolesById[$roleId] = $role;
    }
}

if (!$departments && !$roles) {
    hg_public_render_error(
        'Organigrama vacio',
        'Esta organizacion no tiene departamentos ni cargos activos definidos.',
        404,
        true
    );
    return;
}

$departmentHeadRoleIds = [];
foreach ($roles as $role) {
    if (!empty($role['department_id']) && (int)$role['is_head'] === 1) {
        $headDepartmentId = (int)$role['department_id'];
        if (!isset($departmentHeadRoleIds[$headDepartmentId])) {
            $departmentHeadRoleIds[$headDepartmentId] = (int)$role['id'];
        }
    }
}

$levelZeroRoleIds = [];
foreach ($roles as $role) {
    if ((int)$role['hierarchy_level'] === 0) {
        $levelZeroRoleIds[] = (int)$role['id'];
    }
}

$topRoleId = count($levelZeroRoleIds) === 1 ? (int)$levelZeroRoleIds[0] : 0;
$topRoleDepartmentId = ($topRoleId > 0 && isset($rolesById[$topRoleId]))
    ? (int)($rolesById[$topRoleId]['department_id'] ?? 0)
    : 0;
$skippedDepartmentIds = [];
if (
    $topRoleDepartmentId > 0
    && isset($departments[$topRoleDepartmentId])
    && (int)($departments[$topRoleDepartmentId]['parent_department_id'] ?? 0) === 0
    && (string)($departments[$topRoleDepartmentId]['department_type'] ?? '') === 'board'
) {
    $skippedDepartmentIds[$topRoleDepartmentId] = true;
}

$chartData = [];
$rootDepartmentIds = [];
foreach ($departments as $department) {
    if ((int)($department['parent_department_id'] ?? 0) === 0) {
        $rootDepartmentIds[(int)$department['id']] = true;
    }
}

$visibleDepartmentIds = [];
foreach ($departments as $department) {
    $deptId = (int)$department['id'];
    $parentDeptId = (int)($department['parent_department_id'] ?? 0);
    if ($parentDeptId > 0 && !empty($rootDepartmentIds[$parentDeptId])) {
        $visibleDepartmentIds[$deptId] = true;
    }
}

if ($topRoleId === 0) {
    $chartData[] = [
        'id' => 'org-' . $orgId,
        'parentId' => '',
        'kind' => 'organization',
        'title' => $orgName,
        'subtitle' => 'Organizacion',
        'name' => '',
        'department' => $orgName,
        'departmentType' => 'board',
        'level' => 0,
        'color' => $orgColor,
        'note' => '',
        'href' => '',
        'image' => '',
        'meta' => '',
        'scope' => '',
        '_expanded' => true,
    ];
}

foreach ($departments as $department) {
    $deptId = (int)$department['id'];
    if (empty($visibleDepartmentIds[$deptId])) {
        continue;
    }

    $chartData[] = [
        'id' => 'dept-' . $deptId,
        'parentId' => $topRoleId > 0 ? ('role-' . $topRoleId) : ('org-' . $orgId),
        'kind' => 'department',
        'title' => (string)$department['name'],
        'subtitle' => '',
        'name' => '',
        'department' => (string)$department['name'],
        'departmentType' => (string)($department['department_type'] ?? ''),
        'level' => (int)$department['hierarchy_level'],
        'color' => (string)($department['color'] ?: '#e2e8f0'),
        'note' => '',
        'href' => '',
        'image' => '',
        'meta' => '',
        'scope' => '',
        '_expanded' => true,
    ];
}

foreach ($roles as $role) {
    $deptId = (int)$role['department_id'];
    $level = (int)$role['hierarchy_level'];
    $roleId = (int)$role['id'];
    $parentId = '';
    $visibleDepartmentParentId = hg_bio_org_chart_visible_department_parent($departments, $visibleDepartmentIds, $deptId);
    $parentBridgeRoleId = !empty($role['parent_bridge_id']) ? (int)$role['parent_bridge_id'] : 0;
    $departmentHeadId = hg_bio_org_chart_nearest_department_head($departments, $departmentHeadRoleIds, $deptId, $roleId);
    $shouldAttachToVisibleDepartment = (
        (int)($role['is_head'] ?? 0) === 1
        && $visibleDepartmentParentId > 0
        && ($parentBridgeRoleId <= 0 || ($topRoleId > 0 && $parentBridgeRoleId === $topRoleId))
    );

    if ($topRoleId > 0 && $roleId === $topRoleId) {
        $parentId = '';
    } elseif ($shouldAttachToVisibleDepartment) {
        $parentId = 'dept-' . $visibleDepartmentParentId;
    } elseif ($parentBridgeRoleId > 0 && isset($rolesById[$parentBridgeRoleId])) {
        $parentId = 'role-' . $parentBridgeRoleId;
    } elseif ($level === 0 && $topRoleId === 0) {
        $parentId = 'org-' . $orgId;
    } else {
        if ($departmentHeadId > 0 && isset($rolesById[$departmentHeadId])) {
            $parentId = 'role-' . $departmentHeadId;
        } else {
            $parentId = $topRoleId > 0 ? ('role-' . $topRoleId) : ('org-' . $orgId);
        }
    }

    $metaParts = [];
    foreach (['rank', 'breed_name', 'auspice_name', 'tribe_name'] as $field) {
        $value = trim((string)($role[$field] ?? ''));
        if ($value !== '') {
            $metaParts[] = $value;
        }
    }
    $primaryDepartment = hg_bio_org_chart_primary_department($departments, $deptId);
    $displayDepartmentName = (string)($primaryDepartment['name'] ?? ($role['department_name'] ?? ''));

    $chartData[] = [
        'id' => 'role-' . (int)$role['id'],
        'parentId' => $parentId,
        'kind' => 'position',
        'title' => (string)$role['position_name'],
        'subtitle' => $displayDepartmentName,
        'name' => (string)$role['character_name'],
        'department' => $displayDepartmentName,
        'directDepartment' => (string)($role['department_name'] ?? ''),
        'departmentType' => (string)($role['department_type'] ?? ''),
        'level' => $level,
        'color' => (string)($role['department_color'] ?: '#e2e8f0'),
        'note' => (string)($role['responsibility'] ?? ''),
        'href' => (string)$role['href'],
        'image' => (string)$role['avatar_url'],
        'meta' => implode(' / ', $metaParts),
        'scope' => (string)($role['scope_label'] ?? ''),
    ];
}

$pageTitle2 = $orgName . " | Organigrama";
setMetaFromPage(
    $orgName . " | Organigrama | Heaven's Gate",
    "Organigrama de " . $orgName . ".",
    null,
    'website'
);

include("app/partials/main_nav_bar.php");
?>
<link rel="stylesheet" href="/assets/css/hg-chapters.css">
<link rel="stylesheet" href="/assets/css/hg-maps.css">
<script type="text/javascript" src="/assets/vendor/d3/d3.v7.min.js"></script>
<script type="text/javascript" src="/assets/vendor/d3-flextree/d3-flextree.2.1.2.js"></script>
<script type="text/javascript" src="/assets/vendor/d3-org-chart/d3-org-chart.3.1.1.js"></script>

<style>
    .org-shell-root {
        max-width: 1280px;
    }

    .org-stage-block {
        padding: 16px 18px 18px;
    }

    .org-quickbar {
        display: grid;
        grid-template-columns: minmax(220px, 1.05fr) minmax(260px, 1.45fr) max-content;
        gap: 10px;
        align-items: end;
        margin-bottom: 12px;
    }

    .org-quick-field {
        display: grid;
        gap: 6px;
    }

    .org-quick-field label {
        color: var(--map-muted);
        font-size: .82rem;
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .org-actions {
        display: flex;
        flex-wrap: nowrap;
        justify-content: flex-end;
        gap: 8px;
    }

    .org-btn,
    .org-search,
    .org-select {
        min-height: 40px;
        width: 100%;
        box-sizing: border-box;
        border-radius: 10px;
        border: 1px solid rgba(0, 0, 153, .8);
        background: rgba(4, 10, 26, .92);
        color: #ffffff;
        padding: 8px 10px;
        font: inherit;
    }

    .org-search::placeholder {
        color: rgba(217, 232, 255, .54);
    }

    .org-btn {
        width: 42px;
        height: 40px;
        min-width: 42px;
        padding: 0;
        font-size: 1.08rem;
        line-height: 1;
        cursor: pointer;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.03);
        transition: transform .14s ease, border-color .14s ease, background .14s ease;
    }

    .org-btn:hover,
    .org-btn:focus-visible,
    .org-search:focus,
    .org-select:focus {
        border-color: rgba(51, 204, 204, .58);
        background: rgba(7, 17, 38, .98);
        outline: none;
    }

    .org-btn:hover {
        transform: translateY(-1px);
    }

    .org-workspace {
        position: relative;
    }

    .chart-container {
        min-height: 78vh;
        height: 78vh;
        width: 100%;
        border: 1px solid rgba(0, 0, 153, .9);
        border-radius: 12px;
        overflow: hidden;
        background:
            linear-gradient(rgba(51, 204, 204, .045) 1px, transparent 1px),
            linear-gradient(90deg, rgba(51, 204, 204, .045) 1px, transparent 1px),
            radial-gradient(circle at top left, rgba(52, 102, 191, .28), transparent 36%),
            linear-gradient(180deg, #06112b 0%, #040a19 100%);
        background-size: 28px 28px, 28px 28px, auto, auto;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.03), 0 16px 32px rgba(0,0,0,.24);
    }

    .chart-container.is-org-fullscreen,
    .chart-container:fullscreen {
        width: 100vw;
        min-height: 100vh;
        height: 100vh;
        border-radius: 0;
    }

    .chart-container.is-org-fullscreen,
    .chart-container:-webkit-full-screen {
        width: 100vw;
        min-height: 100vh;
        height: 100vh;
        border-radius: 0;
    }

    .org-card {
        width: 278px;
        min-height: 132px;
        border: 1px solid rgba(0, 0, 153, .85);
        border-top: 5px solid var(--org-card-color, #33CCCC);
        border-radius: 10px;
        background: linear-gradient(180deg, rgba(0, 0, 102, .96) 0%, rgba(5, 1, 78, .96) 100%);
        color: #ffffff;
        box-shadow: 0 14px 28px rgba(0, 0, 0, .28), inset 0 0 0 1px rgba(255,255,255,.03);
        overflow: hidden;
        text-align: left;
    }

    .org-card.is-department {
        min-height: 58px;
    }

    .org-card-head {
        display: grid;
        grid-template-columns: 54px minmax(0, 1fr);
        gap: 10px;
        padding: 12px 12px 8px;
        align-items: center;
    }

    .org-card.is-department .org-card-head {
        display: block;
        text-align: left;
    }

    .org-card-avatar {
        width: 54px;
        height: 54px;
        border-radius: 50%;
        object-fit: cover;
        background: #000033;
        box-shadow: 0 0 0 1px #000099, 0 0 0 4px rgba(51, 204, 204, .1);
    }

    .org-card-title {
        font-weight: 800;
        font-size: 14px;
        line-height: 1.2;
        color: #ffffff;
        overflow-wrap: anywhere;
        text-align: left;
    }

    .org-card-name {
        display: inline-block;
        margin-top: 4px;
        font-weight: 700;
        font-size: 13px;
        color: #33CCCC;
        overflow-wrap: anywhere;
        text-decoration: none;
        text-align: left;
    }

    .org-card-name:hover,
    .org-card-name:focus-visible {
        color: #dfffff;
        text-decoration: underline;
    }

    .org-card-sub {
        padding: 0 12px 10px;
        color: #b9d6ff;
        font-size: 12px;
        line-height: 1.35;
        text-align: left;
    }

    .org-card-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        padding: 0 12px 12px;
    }

    .org-tag {
        display: inline-flex;
        align-items: center;
        min-height: 21px;
        padding: 2px 7px;
        border-radius: 999px;
        border: 1px solid rgba(51, 204, 204, .25);
        background: rgba(4, 10, 26, .72);
        color: #dff5ff;
        font-size: 11px;
        font-weight: 700;
    }

    @media (max-width: 980px) {
        .org-quickbar {
            grid-template-columns: 1fr;
        }

        .org-actions {
            justify-content: flex-start;
            overflow-x: auto;
        }

        .chart-container {
            min-height: 560px;
            height: 68vh;
        }
    }
</style>

<div class="chapter-shell map-shell-root org-shell-root">
    <div class="chapter-hero map-hero">
        <h2>Organigrama</h2>
        <span class="chapter-code"><?= hg_bio_org_chart_h($orgName) ?></span>
    </div>

    <section class="chapter-block map-stage-block org-stage-block">
        <div class="org-quickbar">
            <div class="org-quick-field">
                <label for="orgSelector">Organizacion</label>
                <select class="org-select" id="orgSelector" aria-label="Organizacion">
                    <?php foreach ($organizationOptions as $option): ?>
                        <?php
                        $optionId = (int)$option['id'];
                        $optionSlug = trim((string)($option['pretty_id'] ?? ''));
                        $optionValue = $optionSlug !== '' ? $optionSlug : (string)$optionId;
                        $optionLabel = (string)($option['name'] ?? '');
                        ?>
                        <option value="<?= hg_bio_org_chart_h($optionValue) ?>" <?= $optionId === $orgId ? 'selected' : '' ?>>
                            <?= hg_bio_org_chart_h($optionLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="org-quick-field">
                <label class="map-sr-only" for="orgSearch">Buscar</label>
                <input class="org-search" id="orgSearch" type="search" placeholder="Buscar cargo, departamento o personaje" autocomplete="off">
            </div>

            <div class="org-actions" role="toolbar" aria-label="Controles del organigrama">
                <button class="org-btn" type="button" id="orgFit" title="Centrar" aria-label="Centrar">🎯</button>
                <button class="org-btn" type="button" id="orgExpand" title="Expandir" aria-label="Expandir">➕</button>
                <button class="org-btn" type="button" id="orgCollapse" title="Contraer" aria-label="Contraer">➖</button>
                <button class="org-btn" type="button" id="orgFullscreen" title="Pantalla completa" aria-label="Pantalla completa">⛶</button>
                <button class="org-btn" type="button" id="orgExport" title="Exportar PNG" aria-label="Exportar PNG">🖼️</button>
            </div>
        </div>

        <div class="org-workspace">
            <div class="chart-container"></div>
        </div>
    </section>
</div>

<script>
(function () {
    const orgData = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const chartContainer = document.querySelector('.chart-container');
    let chart = null;
    let selectedData = null;
    let resizeTimer = null;
    const nodeIds = new Set(orgData.map(item => item.id));
    const rootNode = orgData.find(item => !item.parentId) || orgData[0];
    orgData.forEach(item => {
        if (item.parentId && !nodeIds.has(item.parentId)) {
            item.parentId = rootNode ? rootNode.id : '';
        }
    });

    function esc(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        });
    }

    function isChartFullscreen() {
        return document.fullscreenElement === chartContainer
            || document.webkitFullscreenElement === chartContainer;
    }

    function normalChartHeight() {
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 760;
        if (window.matchMedia && window.matchMedia('(max-width: 980px)').matches) {
            return Math.max(560, Math.round(viewportHeight * 0.68));
        }

        return Math.max(420, Math.round(viewportHeight * 0.78));
    }

    function chartHeight() {
        if (isChartFullscreen()) {
            return Math.max(420, Math.round(window.innerHeight || document.documentElement.clientHeight || 760));
        }

        return normalChartHeight();
    }

    function syncChartSize(fitAfter) {
        const height = chartHeight();
        chartContainer.classList.toggle('is-org-fullscreen', isChartFullscreen());
        chartContainer.style.height = height + 'px';
        chartContainer.style.minHeight = height + 'px';
        if (!chart) return;
        chart.svgHeight(height).render();
        if (fitAfter) {
            requestAnimationFrame(function () {
                chart.fit({ animate: false });
            });
        }
    }

    function cardHtml(d) {
        const data = d.data;
        const color = /^#[0-9a-f]{6}$/i.test(data.color || '') ? data.color : '#94a3b8';
        const cardStyle = [
            'width:278px',
            'min-height:132px',
            'border:1px solid rgba(0,0,153,.85)',
            `border-top:5px solid ${color}`,
            'border-radius:10px',
            'background:linear-gradient(180deg,rgba(0,0,102,.96) 0%,rgba(5,1,78,.96) 100%)',
            'color:#ffffff',
            'box-shadow:0 14px 28px rgba(0,0,0,.28),inset 0 0 0 1px rgba(255,255,255,.03)',
            'overflow:hidden',
            'text-align:left',
            'font-family:Trebuchet MS,Verdana,sans-serif'
        ].join(';');
        const departmentCardStyle = [
            'width:278px',
            'min-height:58px',
            'border:1px solid rgba(0,0,153,.85)',
            `border-top:5px solid ${color}`,
            'border-radius:10px',
            'background:rgba(0,0,85,.92)',
            'color:#ffffff',
            'box-shadow:0 14px 28px rgba(0,0,0,.24),inset 0 0 0 1px rgba(255,255,255,.03)',
            'overflow:hidden',
            'text-align:left',
            'font-family:Trebuchet MS,Verdana,sans-serif'
        ].join(';');
        const headStyle = 'display:grid;grid-template-columns:54px minmax(0,1fr);gap:10px;padding:12px 12px 8px;align-items:center;text-align:left';
        const deptHeadStyle = 'display:block;padding:14px 12px 12px;text-align:left';
        const titleStyle = 'font-weight:800;font-size:14px;line-height:1.2;color:#ffffff;overflow-wrap:anywhere;text-align:left';
        const nameStyle = 'display:inline-block;margin-top:4px;font-weight:700;font-size:13px;color:#33CCCC;overflow-wrap:anywhere;text-decoration:none;text-align:left';
        const subStyle = 'padding:0 12px 10px;color:#b9d6ff;font-size:12px;line-height:1.35;text-align:left';
        const tagsStyle = 'display:flex;flex-wrap:wrap;gap:5px;padding:0 12px 12px;text-align:left';
        const tagStyle = 'display:inline-flex;align-items:center;min-height:21px;padding:2px 7px;border-radius:999px;border:1px solid rgba(51,204,204,.25);background:rgba(4,10,26,.72);color:#dff5ff;font-size:11px;font-weight:700;text-align:left';
        const avatarStyle = 'width:54px;height:54px;border-radius:50%;object-fit:cover;background:#000033;box-shadow:0 0 0 1px #000099,0 0 0 4px rgba(51,204,204,.1)';
        const tags = [
            data.department ? esc(data.department) : '',
            data.scope ? esc(data.scope) : '',
            data.level !== undefined ? 'Nivel ' + esc(data.level) : ''
        ].filter(Boolean);

        if (data.kind === 'department') {
            return `
                <div class="org-card is-department" style="${departmentCardStyle}">
                    <div class="org-card-head" style="${deptHeadStyle}">
                        <div class="org-card-title" style="${titleStyle}">${esc(data.title)}</div>
                    </div>
                </div>`;
        }

        const image = data.image
            ? `<img class="org-card-avatar" style="${avatarStyle}" src="${esc(data.image)}" alt="">`
            : `<div class="org-card-avatar" style="${avatarStyle}"></div>`;
        const nameHtml = data.href
            ? `<a class="org-card-name" style="${nameStyle}" href="${esc(data.href)}" target="_blank" rel="noopener noreferrer" title="Abrir ficha en nueva ventana">${esc(data.name)}</a>`
            : `<span class="org-card-name" style="${nameStyle}">${esc(data.name)}</span>`;

        return `
            <div class="org-card" style="${cardStyle}">
                <div class="org-card-head" style="${headStyle}">
                    ${image}
                    <div>
                        <div class="org-card-title" style="${titleStyle}">${esc(data.title)}</div>
                        ${nameHtml}
                    </div>
                </div>
                <div class="org-card-sub" style="${subStyle}">${esc(data.meta || data.note || '')}</div>
                <div class="org-card-tags" style="${tagsStyle}">${tags.map(t => `<span class="org-tag" style="${tagStyle}">${t}</span>`).join('')}</div>
            </div>`;
    }

    function selectNode(data) {
        selectedData = data;
    }

    function searchNode(query) {
        const q = String(query || '').trim().toLowerCase();
        if (!q || !chart) return;
        const found = orgData.find(item => [
            item.title,
            item.name,
            item.department,
            item.directDepartment,
            item.scope,
            item.note,
            item.meta
        ].join(' ').toLowerCase().includes(q));

        if (!found) return;
        chart.setCentered(found.id).render();
        selectNode(found);
    }

    function visibleChildCount(node) {
        return (node.children || node._children || []).reduce(function (count, child) {
            if (child.data && child.data.kind === 'department') {
                return count + visibleChildCount(child);
            }
            return count + 1;
        }, 0);
    }

    syncChartSize(false);

    chart = new d3.OrgChart()
        .container('.chart-container')
        .data(orgData)
        .svgHeight(chartHeight())
        .nodeWidth(() => 278)
        .nodeHeight(d => d.data.kind === 'department' ? 74 : 148)
        .childrenMargin(d => d.data.kind === 'department' ? 52 : 72)
        .siblingsMargin(() => 22)
        .compactMarginPair(() => 48)
        .compactMarginBetween(() => 18)
        .nodeButtonWidth(() => 32)
        .nodeButtonHeight(() => 32)
        .nodeButtonX(() => -16)
        .nodeButtonY(() => 8)
        .layout('top')
        .compact(false)
        .nodeContent(cardHtml)
        .buttonContent(({ node }) => {
            const count = visibleChildCount(node) || node.data._directSubordinates || 0;
            return `<div style="width:28px;height:28px;border-radius:999px;background:rgba(4,10,26,.96);border:1px solid rgba(51,204,204,.42);display:flex;align-items:center;justify-content:center;color:#dff5ff;font-weight:800;box-shadow:0 0 0 3px rgba(51,204,204,.08)">${count}</div>`;
        })
        .onNodeClick(function (node) {
            selectNode(node.data);
        })
        .render();

    chart.fit();

    chartContainer.addEventListener('click', function (event) {
        const characterLink = event.target.closest('.org-card-name[href]');
        if (!characterLink) return;
        event.stopPropagation();
    });

    chartContainer.addEventListener('dblclick', function () {
        if (selectedData && selectedData.href) {
            window.open(selectedData.href, '_blank', 'noopener');
        }
    });

    document.getElementById('orgFit').addEventListener('click', function () {
        chart.fit();
    });

    document.getElementById('orgExpand').addEventListener('click', function () {
        chart.expandAll();
    });

    document.getElementById('orgCollapse').addEventListener('click', function () {
        orgData.forEach(function (item) {
            item._expanded = item.kind === 'department' || !item.parentId;
        });
        chart.initialExpandLevel(1).render();
        chart.fit();
    });

    document.getElementById('orgFullscreen').addEventListener('click', function () {
        if (document.fullscreenElement === chartContainer) {
            document.exitFullscreen();
            return;
        }
        if (document.webkitFullscreenElement === chartContainer) {
            document.webkitExitFullscreen();
            return;
        }

        if (chartContainer.requestFullscreen) {
            chartContainer.requestFullscreen();
        } else if (chartContainer.webkitRequestFullscreen) {
            chartContainer.webkitRequestFullscreen();
        }
    });

    function handleFullscreenChange() {
        const fullscreenActive = isChartFullscreen();
        setTimeout(function () {
            syncChartSize(true);
        }, fullscreenActive ? 120 : 220);

        if (!fullscreenActive) {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    syncChartSize(true);
                });
            });
            setTimeout(function () {
                syncChartSize(true);
            }, 520);
        }
    }

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);

    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            syncChartSize(false);
        }, 160);
    });

    document.getElementById('orgExport').addEventListener('click', function () {
        chart.exportImg({ full: true, scale: 3, save: true, backgroundColor: '#040a19' });
    });

    document.getElementById('orgSearch').addEventListener('input', function () {
        searchNode(this.value);
    });

    document.getElementById('orgSelector').addEventListener('change', function () {
        const org = String(this.value || '').trim();
        if (!org) return;
        window.location.href = '/organizations/' + encodeURIComponent(org) + '/org-chart';
    });
})();
</script>
