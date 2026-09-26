<?php

include_once(__DIR__ . '/../../helpers/pretty.php');

if (!function_exists('hg_external_links_admin_table_exists')) {
    function hg_external_links_admin_table_exists(mysqli $db, string $table): bool
    {
        return in_array($table, ['fact_external_links', 'bridge_characters_external_links'], true);
    }
}

if (!function_exists('hg_external_links_admin_create')) {
    function hg_external_links_admin_create(
        mysqli $link,
        string $title,
        string $url,
        string $kind,
        string $sourceLabel,
        string $description,
        int $isActive
    ): array {
        $stmt = $link->prepare(
            "INSERT INTO fact_external_links (title, url, kind, source_label, description, is_active)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Error al preparar CREATE: ' . $link->error];
        }

        $stmt->bind_param('sssssi', $title, $url, $kind, $sourceLabel, $description, $isActive);
        if (!$stmt->execute()) {
            $message = 'Error al crear: ' . $stmt->error;
            $stmt->close();
            return ['ok' => false, 'message' => $message];
        }

        $newId = (int)$link->insert_id;
        hg_update_pretty_id_if_exists($link, 'fact_external_links', $newId, $title);
        $stmt->close();

        return ['ok' => true, 'message' => 'Enlace externo creado.'];
    }
}

if (!function_exists('hg_external_links_admin_update')) {
    function hg_external_links_admin_update(
        mysqli $link,
        int $id,
        string $title,
        string $url,
        string $kind,
        string $sourceLabel,
        string $description,
        int $isActive
    ): array {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'ID inválido para actualizar.'];
        }

        $stmt = $link->prepare(
            "UPDATE fact_external_links
             SET title=?, url=?, kind=?, source_label=?, description=?, is_active=?, updated_at=NOW()
             WHERE id=?"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Error al preparar UPDATE: ' . $link->error];
        }

        $stmt->bind_param('sssssii', $title, $url, $kind, $sourceLabel, $description, $isActive, $id);
        if (!$stmt->execute()) {
            $message = 'Error al actualizar: ' . $stmt->error;
            $stmt->close();
            return ['ok' => false, 'message' => $message];
        }

        hg_update_pretty_id_if_exists($link, 'fact_external_links', $id, $title);
        $stmt->close();

        return ['ok' => true, 'message' => 'Enlace externo actualizado.'];
    }
}

if (!function_exists('hg_external_links_admin_delete')) {
    function hg_external_links_admin_delete(mysqli $link, int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'ID inválido para borrar.'];
        }

        $stmt = $link->prepare("DELETE FROM fact_external_links WHERE id=?");
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Error al preparar DELETE: ' . $link->error];
        }

        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            $message = 'Error al borrar: ' . $stmt->error;
            $stmt->close();
            return ['ok' => false, 'message' => $message];
        }

        $stmt->close();
        return ['ok' => true, 'message' => 'Enlace externo eliminado.'];
    }
}

if (!function_exists('hg_external_links_admin_fetch_rows')) {
    function hg_external_links_admin_fetch_rows(
        mysqli $link,
        string $query,
        bool $hasBridge
    ): array {
        $where = "WHERE 1=1";
        $types = '';
        $params = [];
        if ($query !== '') {
            $where .= " AND (l.title LIKE ? OR l.url LIKE ? OR l.kind LIKE ? OR l.source_label LIKE ?)";
            $types = 'ssss';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like, $like];
        }

        $bridgeJoin = '';
        if ($hasBridge) {
            $bridgeJoin = "LEFT JOIN (
                SELECT external_link_id, COUNT(*) AS chars_count
                FROM bridge_characters_external_links
                GROUP BY external_link_id
            ) bx ON bx.external_link_id = l.id";
        }

        $sql = "SELECT l.id, l.pretty_id, l.title, l.url, l.kind, l.source_label, l.description, l.is_active, l.updated_at, l.created_at,
                       " . ($hasBridge ? "COALESCE(bx.chars_count, 0)" : "0") . " AS chars_count
                FROM fact_external_links l
                {$bridgeJoin}
                {$where}
                ORDER BY l.is_active DESC, l.updated_at DESC, l.id DESC";

        $stmt = $link->prepare($sql);
        if (!$stmt) {
            return [];
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }
}
