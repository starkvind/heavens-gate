<?php
setMetaFromPage("Organigrama | Heaven's Gate", "Organigrama de clanes y organizaciones.", null, 'website');

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../../domains/organizations/queries.php');

if (!$link) {
    hg_public_log_error('bio_org_chart', 'missing DB connection');
    hg_public_render_error('Organigrama no disponible', 'No se pudo cargar el organigrama en este momento.');
    return;
}

if (!function_exists('hg_bio_org_chart_h')) {
    function hg_bio_org_chart_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
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
            if (in_array((string)($current['department_type'] ?? ''), ['department', 'delegation', 'special', 'territory'], true)) $best = $current;
            $currentId = (int)($current['parent_department_id'] ?? 0);
        }
        return is_array($best) ? $best : [];
    }
}
if (!function_exists('hg_bio_org_chart_nearest_department_head')) {
    function hg_bio_org_chart_nearest_department_head(array $departments, array $departmentHeadRoleIds, int $departmentId, int $excludeRoleId = 0): int
    {
        $currentId = $departmentId; $seen = [];
        while ($currentId > 0 && isset($departments[$currentId]) && !isset($seen[$currentId])) {
            $seen[$currentId] = true;
            $headRoleId = (int)($departmentHeadRoleIds[$currentId] ?? 0);
            if ($headRoleId > 0 && $headRoleId !== $excludeRoleId) return $headRoleId;
            $currentId = (int)($departments[$currentId]['parent_department_id'] ?? 0);
        }
        return 0;
    }
}
if (!function_exists('hg_bio_org_chart_visible_department_parent')) {
    function hg_bio_org_chart_visible_department_parent(array $departments, array $visibleDepartmentIds, int $departmentId): int
    {
        $currentId = $departmentId; $seen = [];
        while ($currentId > 0 && isset($departments[$currentId]) && !isset($seen[$currentId])) {
            $seen[$currentId] = true;
            if (!empty($visibleDepartmentIds[$currentId])) return $currentId;
            $currentId = (int)($departments[$currentId]['parent_department_id'] ?? 0);
        }
        return 0;
    }
}
if (!function_exists('hg_bio_org_chart_role_bucket_key')) {
    function hg_bio_org_chart_role_bucket_key(array $role): string { return (int)($role['character_id'] ?? 0) . '|' . (int)($role['department_id'] ?? 0); }
}
if (!function_exists('hg_bio_org_chart_role_sort_stamp')) {
    function hg_bio_org_chart_role_sort_stamp(array $role): string
    {
        $updatedAt = trim((string)($role['updated_at'] ?? ''));
        return $updatedAt !== '' ? $updatedAt : trim((string)($role['created_at'] ?? ''));
    }
}

$organizationRaw = hg_request_param($hgRequest, 'organization', 'justicia-metalica');
$organization = hg_organizations_fetch_one($link, $organizationRaw);
if (!$organization) {
    hg_public_render_not_found('Organizacion no encontrada', 'No se encontro la organizacion solicitada.', true);
    return;
}
if (!hg_organizations_table_exists($link, 'dim_organization_departments') || !hg_organizations_table_exists($link, 'bridge_characters_org')) {
    hg_public_render_error('Organigrama no preparado', 'Faltan las tablas dim_organization_departments y bridge_characters_org. Preparalas desde /talim?s=admin_org_chart_schema.', 500, true);
    return;
}

$organizationOptions = hg_organizations_fetch_chart_options($link);
$orgId = (int)$organization['id'];
$orgPretty = (string)($organization['pretty_id'] ?? '');
$orgName = (string)($organization['name'] ?? '');
$orgColor = (string)($organization['color'] ?? '#d0e6ff');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $orgColor)) $orgColor = '#d0e6ff';

$hasCurrentOrganizationOption = false;
foreach ($organizationOptions as $option) {
    if ((int)($option['id'] ?? 0) === $orgId) { $hasCurrentOrganizationOption = true; break; }
}
if (!$hasCurrentOrganizationOption) {
    array_unshift($organizationOptions, ['id' => $orgId, 'pretty_id' => $orgPretty, 'name' => $orgName, 'department_count' => 0, 'role_count' => 0]);
}

$departments = [];
foreach (hg_organizations_fetch_departments($link, $orgId) as $row) {
    $departments[(int)$row['id']] = $row;
}

$rawRoles = hg_organizations_fetch_roles($link, $orgId);
$rawRolesById = [];
foreach ($rawRoles as &$row) {
    $row['avatar_url'] = hg_character_avatar_url($row['image_url'] ?? '', $row['gender'] ?? '');
    $row['href'] = pretty_url($link, 'fact_characters', '/characters', (int)$row['character_id']);
    $rawRolesById[(int)$row['id']] = $row;
}
unset($row);

$canonicalRoleIdsByBucket = [];
foreach ($rawRoles as $role) {
    $bucketKey = hg_bio_org_chart_role_bucket_key($role);
    if (!isset($canonicalRoleIdsByBucket[$bucketKey])) {
        $canonicalRoleIdsByBucket[$bucketKey] = (int)$role['id'];
        continue;
    }
    $currentId = (int)$canonicalRoleIdsByBucket[$bucketKey];
    $current = $rawRolesById[$currentId] ?? null;
    if (!is_array($current)) { $canonicalRoleIdsByBucket[$bucketKey] = (int)$role['id']; continue; }

    $keepCurrent = false;
    if ((int)$current['is_primary'] !== (int)$role['is_primary']) {
        $keepCurrent = (int)$current['is_primary'] > (int)$role['is_primary'];
    } else {
        $currentStamp = hg_bio_org_chart_role_sort_stamp($current);
        $candidateStamp = hg_bio_org_chart_role_sort_stamp($role);
        if ($currentStamp !== '' || $candidateStamp !== '') {
            if ($currentStamp > $candidateStamp) $keepCurrent = true;
            elseif ($currentStamp === $candidateStamp) $keepCurrent = (int)$current['id'] > (int)$role['id'];
        } else {
            $keepCurrent = (int)$current['id'] > (int)$role['id'];
        }
    }
    if (!$keepCurrent) $canonicalRoleIdsByBucket[$bucketKey] = (int)$role['id'];
}

$roleIdRemap = [];
foreach ($rawRoles as $role) {
    $canonicalRoleId = (int)($canonicalRoleIdsByBucket[hg_bio_org_chart_role_bucket_key($role)] ?? 0);
    if ($canonicalRoleId > 0) $roleIdRemap[(int)$role['id']] = $canonicalRoleId;
}
$roles = [];
$rolesById = [];
foreach ($rawRoles as $role) {
    $roleId = (int)$role['id'];
    if (($roleIdRemap[$roleId] ?? 0) !== $roleId) continue;
    $parentBridgeId = (int)($role['parent_bridge_id'] ?? 0);
    if ($parentBridgeId > 0 && isset($roleIdRemap[$parentBridgeId])) $role['parent_bridge_id'] = (int)$roleIdRemap[$parentBridgeId];
    $roles[] = $role;
    $rolesById[$roleId] = $role;
}

if (!$departments && !$roles) {
    hg_public_render_error('Organigrama vacio', 'Esta organizacion no tiene departamentos ni cargos activos definidos.', 404, true);
    return;
}

$departmentHeadRoleIds = [];
foreach ($roles as $role) {
    if (!empty($role['department_id']) && (int)$role['is_head'] === 1) {
        $departmentId = (int)$role['department_id'];
        if (!isset($departmentHeadRoleIds[$departmentId])) $departmentHeadRoleIds[$departmentId] = (int)$role['id'];
    }
}
$levelZeroRoleIds = [];
foreach ($roles as $role) if ((int)$role['hierarchy_level'] === 0) $levelZeroRoleIds[] = (int)$role['id'];
$topRoleId = count($levelZeroRoleIds) === 1 ? (int)$levelZeroRoleIds[0] : 0;

$rootDepartmentIds = [];
foreach ($departments as $department) {
    if ((int)($department['parent_department_id'] ?? 0) === 0) $rootDepartmentIds[(int)$department['id']] = true;
}
$visibleDepartmentIds = [];
foreach ($departments as $department) {
    $deptId = (int)$department['id'];
    $parentDeptId = (int)($department['parent_department_id'] ?? 0);
    if ($parentDeptId > 0 && !empty($rootDepartmentIds[$parentDeptId])) $visibleDepartmentIds[$deptId] = true;
}

$chartData = [];
if ($topRoleId === 0) {
    $chartData[] = [
        'id' => 'org-' . $orgId, 'parentId' => '', 'kind' => 'organization', 'title' => $orgName,
        'subtitle' => 'Organizacion', 'name' => '', 'department' => $orgName, 'departmentType' => 'board',
        'level' => 0, 'color' => $orgColor, 'note' => '', 'href' => '', 'image' => '', 'meta' => '', 'scope' => '', '_expanded' => true,
    ];
}
foreach ($departments as $department) {
    $deptId = (int)$department['id'];
    if (empty($visibleDepartmentIds[$deptId])) continue;
    $chartData[] = [
        'id' => 'dept-' . $deptId, 'parentId' => $topRoleId > 0 ? ('role-' . $topRoleId) : ('org-' . $orgId),
        'kind' => 'department', 'title' => (string)$department['name'], 'subtitle' => '', 'name' => '',
        'department' => (string)$department['name'], 'departmentType' => (string)($department['department_type'] ?? ''),
        'level' => (int)$department['hierarchy_level'], 'color' => (string)($department['color'] ?: '#e2e8f0'),
        'note' => '', 'href' => '', 'image' => '', 'meta' => '', 'scope' => '', '_expanded' => true,
    ];
}
foreach ($roles as $role) {
    $deptId = (int)$role['department_id'];
    $level = (int)$role['hierarchy_level'];
    $roleId = (int)$role['id'];
    $visibleDepartmentParentId = hg_bio_org_chart_visible_department_parent($departments, $visibleDepartmentIds, $deptId);
    $parentBridgeRoleId = (int)($role['parent_bridge_id'] ?? 0);
    $departmentHeadId = hg_bio_org_chart_nearest_department_head($departments, $departmentHeadRoleIds, $deptId, $roleId);
    $shouldAttachToVisibleDepartment = ((int)($role['is_head'] ?? 0) === 1 && $visibleDepartmentParentId > 0 && ($parentBridgeRoleId <= 0 || ($topRoleId > 0 && $parentBridgeRoleId === $topRoleId)));

    if ($topRoleId > 0 && $roleId === $topRoleId) $parentId = '';
    elseif ($shouldAttachToVisibleDepartment) $parentId = 'dept-' . $visibleDepartmentParentId;
    elseif ($parentBridgeRoleId > 0 && isset($rolesById[$parentBridgeRoleId])) $parentId = 'role-' . $parentBridgeRoleId;
    elseif ($level === 0 && $topRoleId === 0) $parentId = 'org-' . $orgId;
    elseif ($departmentHeadId > 0 && isset($rolesById[$departmentHeadId])) $parentId = 'role-' . $departmentHeadId;
    else $parentId = $topRoleId > 0 ? ('role-' . $topRoleId) : ('org-' . $orgId);

    $metaParts = [];
    foreach (['rank', 'breed_name', 'auspice_name', 'tribe_name'] as $field) {
        $value = trim((string)($role[$field] ?? ''));
        if ($value !== '') $metaParts[] = $value;
    }
    $primaryDepartment = hg_bio_org_chart_primary_department($departments, $deptId);
    $displayDepartmentName = (string)($primaryDepartment['name'] ?? ($role['department_name'] ?? ''));
    $chartData[] = [
        'id' => 'role-' . $roleId, 'parentId' => $parentId, 'kind' => 'position', 'title' => (string)$role['position_name'],
        'subtitle' => $displayDepartmentName, 'name' => (string)$role['character_name'], 'department' => $displayDepartmentName,
        'directDepartment' => (string)($role['department_name'] ?? ''), 'departmentType' => (string)($role['department_type'] ?? ''),
        'level' => $level, 'color' => (string)($role['department_color'] ?: '#e2e8f0'), 'note' => (string)($role['responsibility'] ?? ''),
        'href' => (string)$role['href'], 'image' => (string)$role['avatar_url'], 'meta' => implode(' / ', $metaParts), 'scope' => (string)($role['scope_label'] ?? ''),
    ];
}

$pageTitle2 = $orgName . " | Organigrama";
setMetaFromPage($orgName . " | Organigrama | Heaven's Gate", "Organigrama de " . $orgName . ".", null, 'website');
include(__DIR__ . '/../../views/organizations/org_chart.php');
