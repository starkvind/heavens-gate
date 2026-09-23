<?php

function hg_aso_table_exists(mysqli $link, string $table): bool
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

function hg_aso_fetch_season_options(mysqli $link): array
{
    $rows = [];
    $sql = "
        SELECT id, name, pretty_id, season_number, COALESCE(season_kind, 'temporada') AS season_kind
        FROM dim_seasons
        ORDER BY
            CASE
                WHEN COALESCE(season_kind, 'temporada') = 'temporada' THEN 1
                WHEN COALESCE(season_kind, 'temporada') = 'inciso' THEN 2
                WHEN COALESCE(season_kind, 'temporada') = 'historia_personal' THEN 3
                WHEN COALESCE(season_kind, 'temporada') = 'especial' THEN 4
                ELSE 99
            END ASC,
            COALESCE(sort_order, 999999) ASC,
            season_number ASC,
            name ASC
    ";
    if ($rs = $link->query($sql)) {
        while ($row = $rs->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rows[] = [
                'id' => $id,
                'name' => (string)($row['name'] ?? ''),
                'pretty_id' => (string)($row['pretty_id'] ?? ''),
                'season_number' => (int)($row['season_number'] ?? 0),
                'season_kind' => (string)($row['season_kind'] ?? 'temporada'),
                'href' => pretty_url($link, 'dim_seasons', '/seasons', $id),
            ];
        }
        $rs->close();
    }
    return $rows;
}

function hg_aso_fetch_orders(mysqli $link): array
{
    $rows = [];
    if (!hg_aso_schema_ready($link)) {
        return $rows;
    }

    $sql = "
        SELECT
            order_key,
            MAX(order_label) AS order_label,
            COUNT(*) AS node_count,
            SUM(CASE WHEN branch_type = 'secondary' THEN 1 ELSE 0 END) AS secondary_count,
            MIN(position) AS first_position
        FROM bridge_season_order_nodes
        WHERE is_active = 1
        GROUP BY order_key
        ORDER BY first_position ASC, order_key ASC
    ";
    if ($rs = $link->query($sql)) {
        while ($row = $rs->fetch_assoc()) {
            $key = trim((string)($row['order_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $rows[] = [
                'order_key' => $key,
                'order_label' => trim((string)($row['order_label'] ?? '')) !== '' ? (string)$row['order_label'] : $key,
                'node_count' => (int)($row['node_count'] ?? 0),
                'secondary_count' => (int)($row['secondary_count'] ?? 0),
            ];
        }
        $rs->close();
    }
    return $rows;
}

function hg_aso_fetch_nodes(mysqli $link, string $orderKey): array
{
    $rows = [];
    if (!hg_aso_schema_ready($link) || trim($orderKey) === '') {
        return $rows;
    }

    $sql = "
        SELECT
            n.id,
            n.order_key,
            n.order_label,
            n.position,
            n.branch_type,
            n.parent_node_id,
            n.node_kind,
            n.season_id,
            n.episode_start,
            n.episode_end,
            n.label,
            n.description,
            n.is_active,
            s.name AS season_name,
            s.pretty_id AS season_pretty_id
        FROM bridge_season_order_nodes n
        LEFT JOIN dim_seasons s ON s.id = n.season_id
        WHERE n.order_key = ?
          AND n.is_active = 1
        ORDER BY n.position ASC, n.id ASC
    ";
    if ($stmt = $link->prepare($sql)) {
        $stmt->bind_param('s', $orderKey);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($row = $rs->fetch_assoc()) {
            $seasonId = (int)($row['season_id'] ?? 0);
            $row['id'] = (int)($row['id'] ?? 0);
            $row['position'] = (int)($row['position'] ?? 0);
            $row['parent_node_id'] = (int)($row['parent_node_id'] ?? 0);
            $row['season_id'] = $seasonId;
            $row['episode_start'] = ($row['episode_start'] !== null) ? (int)$row['episode_start'] : null;
            $row['episode_end'] = ($row['episode_end'] !== null) ? (int)$row['episode_end'] : null;
            $row['is_active'] = (int)($row['is_active'] ?? 0);
            $row['season_name'] = (string)($row['season_name'] ?? '');
            $row['href'] = $seasonId > 0 ? pretty_url($link, 'dim_seasons', '/seasons', $seasonId) : '';
            $rows[] = $row;
        }
        $stmt->close();
    }
    return $rows;
}

function hg_aso_next_position(mysqli $link, string $orderKey): int
{
    $stmt = $link->prepare("SELECT COALESCE(MAX(position), 0) + 10 AS next_pos FROM bridge_season_order_nodes WHERE order_key = ? AND is_active = 1");
    if (!$stmt) {
        return 10;
    }
    $stmt->bind_param('s', $orderKey);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return max(10, (int)($row['next_pos'] ?? 10));
}

function hg_aso_order_exists(mysqli $link, string $orderKey): bool
{
    $stmt = $link->prepare("SELECT id FROM bridge_season_order_nodes WHERE order_key = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $orderKey);
    $stmt->execute();
    $rs = $stmt->get_result();
    $ok = $rs && $rs->fetch_assoc();
    $stmt->close();
    return (bool)$ok;
}

function hg_aso_node_exists_in_order(mysqli $link, int $nodeId, string $orderKey): bool
{
    if ($nodeId <= 0 || $orderKey === '') {
        return false;
    }
    $stmt = $link->prepare("SELECT id FROM bridge_season_order_nodes WHERE id = ? AND order_key = ? AND is_active = 1 LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $nodeId, $orderKey);
    $stmt->execute();
    $rs = $stmt->get_result();
    $ok = $rs && $rs->fetch_assoc();
    $stmt->close();
    return (bool)$ok;
}

function hg_aso_save_node(mysqli $link, array $payload, array $seasonOptions): array
{
    $allowedNodeKinds = ['season', 'season_range', 'arc', 'custom'];
    $allowedBranchTypes = ['main', 'secondary'];
    $seasonIds = [];
    foreach ($seasonOptions as $season) {
        $seasonIds[(int)$season['id']] = (string)($season['name'] ?? '');
    }

    $id = max(0, (int)($payload['id'] ?? 0));
    $orderKey = hg_aso_slugify((string)($payload['order_key'] ?? ''));
    $orderLabel = trim((string)($payload['order_label'] ?? ''));
    $position = (int)($payload['position'] ?? 0);
    $branchType = (string)($payload['branch_type'] ?? 'main');
    $parentNodeId = max(0, (int)($payload['parent_node_id'] ?? 0));
    $nodeKind = (string)($payload['node_kind'] ?? 'season');
    $seasonId = max(0, (int)($payload['season_id'] ?? 0));
    $episodeStart = trim((string)($payload['episode_start'] ?? '')) !== '' ? max(1, (int)$payload['episode_start']) : null;
    $episodeEnd = trim((string)($payload['episode_end'] ?? '')) !== '' ? max(1, (int)$payload['episode_end']) : null;
    $label = trim((string)($payload['label'] ?? ''));
    $description = trim((string)($payload['description'] ?? ''));

    if ($orderKey === '') {
        return ['ok' => false, 'message' => 'El identificador del orden es obligatorio.'];
    }
    if ($orderLabel === '') {
        $orderLabel = ucwords(str_replace(['-', '_'], ' ', $orderKey));
    }
    if (!in_array($branchType, $allowedBranchTypes, true)) {
        $branchType = 'main';
    }
    if (!in_array($nodeKind, $allowedNodeKinds, true)) {
        $nodeKind = 'season';
    }
    if ($seasonId > 0 && !isset($seasonIds[$seasonId])) {
        return ['ok' => false, 'message' => 'La temporada seleccionada no existe.'];
    }
    if ($branchType === 'secondary') {
        if ($parentNodeId <= 0) {
            return ['ok' => false, 'message' => 'Una rama secundaria necesita un nodo padre.'];
        }
        if (!hg_aso_node_exists_in_order($link, $parentNodeId, $orderKey)) {
            return ['ok' => false, 'message' => 'El nodo padre no pertenece a ese orden.'];
        }
    } else {
        $parentNodeId = 0;
    }
    if ($episodeStart !== null && $episodeEnd !== null && $episodeEnd < $episodeStart) {
        return ['ok' => false, 'message' => 'El episodio final no puede ir antes del inicial.'];
    }
    if ($label === '' && $seasonId > 0) {
        $label = $seasonIds[$seasonId];
    }
    if ($label === '') {
        return ['ok' => false, 'message' => 'El nodo necesita un titulo o una temporada vinculada.'];
    }
    if ($position <= 0) {
        $position = hg_aso_next_position($link, $orderKey);
    }

    $seasonIdValue = $seasonId > 0 ? $seasonId : 0;
    $episodeStartValue = $episodeStart !== null ? $episodeStart : 0;
    $episodeEndValue = $episodeEnd !== null ? $episodeEnd : 0;

    if ($id > 0) {
        $sql = "
            UPDATE bridge_season_order_nodes
            SET order_key = ?, order_label = ?, position = ?, branch_type = ?, parent_node_id = NULLIF(?, 0), node_kind = ?, season_id = NULLIF(?, 0), episode_start = NULLIF(?, 0), episode_end = NULLIF(?, 0), label = ?, description = ?
            WHERE id = ?
        ";
        $stmt = $link->prepare($sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'No se pudo preparar la actualizacion.'];
        }
        $stmt->bind_param(
            'ssisisiiissi',
            $orderKey,
            $orderLabel,
            $position,
            $branchType,
            $parentNodeId,
            $nodeKind,
            $seasonIdValue,
            $episodeStartValue,
            $episodeEndValue,
            $label,
            $description,
            $id
        );
        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();
        return $ok
            ? ['ok' => true, 'message' => 'Nodo actualizado.', 'selected_order' => $orderKey]
            : ['ok' => false, 'message' => 'No se pudo actualizar el nodo: ' . $error];
    }

    $sql = "
        INSERT INTO bridge_season_order_nodes
            (order_key, order_label, position, branch_type, parent_node_id, node_kind, season_id, episode_start, episode_end, label, description, is_active)
        VALUES
            (?, ?, ?, ?, NULLIF(?, 0), ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?, ?, 1)
    ";
    $stmt = $link->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'message' => 'No se pudo preparar el alta del nodo.'];
    }
    $stmt->bind_param(
        'ssisisiiiss',
        $orderKey,
        $orderLabel,
        $position,
        $branchType,
        $parentNodeId,
        $nodeKind,
        $seasonIdValue,
        $episodeStartValue,
        $episodeEndValue,
        $label,
        $description
    );
    $ok = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();
    return $ok
        ? ['ok' => true, 'message' => 'Nodo creado.', 'selected_order' => $orderKey]
        : ['ok' => false, 'message' => 'No se pudo crear el nodo: ' . $error];
}

function hg_aso_save_order_meta(mysqli $link, array $payload): array
{
    $original = hg_aso_slugify((string)($payload['original_order_key'] ?? ''));
    $orderKey = hg_aso_slugify((string)($payload['order_key'] ?? ''));
    $orderLabel = trim((string)($payload['order_label'] ?? ''));

    if ($original === '' || $orderKey === '') {
        return ['ok' => false, 'message' => 'El orden seleccionado no es valido.'];
    }
    if (!hg_aso_order_exists($link, $original)) {
        return ['ok' => false, 'message' => 'Ese orden ya no existe.'];
    }
    if ($orderLabel === '') {
        $orderLabel = ucwords(str_replace(['-', '_'], ' ', $orderKey));
    }
    if ($original !== $orderKey && hg_aso_order_exists($link, $orderKey)) {
        return ['ok' => false, 'message' => 'Ya existe otro orden con ese identificador.'];
    }

    $stmt = $link->prepare("UPDATE bridge_season_order_nodes SET order_key = ?, order_label = ? WHERE order_key = ?");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'No se pudo preparar la actualizacion del orden.'];
    }
    $stmt->bind_param('sss', $orderKey, $orderLabel, $original);
    $ok = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();

    return $ok
        ? ['ok' => true, 'message' => 'Cabecera del orden actualizada.', 'selected_order' => $orderKey]
        : ['ok' => false, 'message' => 'No se pudo actualizar el orden: ' . $error];
}

function hg_aso_delete_node(mysqli $link, array $payload): array
{
    $id = max(0, (int)($payload['id'] ?? 0));
    if ($id <= 0) {
        return ['ok' => false, 'message' => 'Nodo invalido.'];
    }

    $stmt = $link->prepare("DELETE FROM bridge_season_order_nodes WHERE id = ?");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'No se pudo preparar el borrado.'];
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'message' => 'No se pudo borrar el nodo: ' . $error];
    }

    $cleanup = $link->prepare("UPDATE bridge_season_order_nodes SET parent_node_id = NULL, branch_type = 'main' WHERE parent_node_id = ?");
    if ($cleanup) {
        $cleanup->bind_param('i', $id);
        $cleanup->execute();
        $cleanup->close();
    }

    return ['ok' => true, 'message' => 'Nodo borrado.'];
}

