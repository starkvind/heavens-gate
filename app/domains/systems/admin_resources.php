<?php

if (!function_exists('hg_resources_admin_slugify')) {
    function hg_resources_admin_slugify(string $text): string
    {
        $text = trim($text);
        if ($text === '') return '';
        if (function_exists('iconv')) {
            $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        }
        $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = strtolower($text);
        return preg_replace('~[^-a-z0-9]+~', '', $text);
    }
}

if (!function_exists('hg_resources_admin_persist_pretty_id')) {
    function hg_resources_admin_persist_pretty_id(mysqli $link, int $id, string $source): bool
    {
        if ($id <= 0) {
            return false;
        }

        $slug = hg_resources_admin_slugify($source);
        if ($slug === '') {
            $slug = (string)$id;
        }

        $stmt = $link->prepare("UPDATE dim_systems_resources SET pretty_id=? WHERE id=?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('si', $slug, $id);
        $ok = $stmt->execute();
        $stmt->close();

        return (bool)$ok;
    }
}

if (!function_exists('hg_resources_admin_delete')) {
    function hg_resources_admin_delete(mysqli $link, int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'ID inválido para eliminar.'];
        }

        $stmt = $link->prepare("DELETE FROM dim_systems_resources WHERE id=?");
        if (!$stmt) {
            return ['ok' => false, 'message' => 'ID inválido para eliminar.'];
        }

        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $message = $ok ? 'Recurso eliminado.' : 'Error al eliminar: ' . $stmt->error;
        $stmt->close();

        return ['ok' => $ok, 'message' => $message];
    }
}

if (!function_exists('hg_resources_admin_create')) {
    function hg_resources_admin_create(
        mysqli $link,
        string $name,
        string $kind,
        int $sortOrder,
        string $description,
        string $prettySource
    ): array {
        $stmt = $link->prepare(
            "INSERT INTO dim_systems_resources (name, kind, sort_order, description, created_at, updated_at)
             VALUES (?,?,?,?,NOW(),NOW())"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Error al preparar INSERT: ' . $link->error];
        }

        $stmt->bind_param('ssis', $name, $kind, $sortOrder, $description);
        if (!$stmt->execute()) {
            $message = 'Error al crear: ' . $stmt->error;
            $stmt->close();
            return ['ok' => false, 'message' => $message];
        }

        $newId = (int)$link->insert_id;
        $prettyOk = hg_resources_admin_persist_pretty_id($link, $newId, $prettySource);
        $stmt->close();

        return [
            'ok' => $prettyOk,
            'message' => $prettyOk ? 'Recurso creado.' : 'Recurso creado, pero no se pudo guardar pretty_id.',
        ];
    }
}

if (!function_exists('hg_resources_admin_update')) {
    function hg_resources_admin_update(
        mysqli $link,
        int $id,
        string $name,
        string $kind,
        int $sortOrder,
        string $description,
        string $prettySource
    ): array {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'ID inválido para actualizar.'];
        }

        $stmt = $link->prepare(
            "UPDATE dim_systems_resources
             SET name=?, kind=?, sort_order=?, description=?, updated_at=NOW()
             WHERE id=?"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Error al preparar UPDATE: ' . $link->error];
        }

        $stmt->bind_param('ssisi', $name, $kind, $sortOrder, $description, $id);
        if (!$stmt->execute()) {
            $message = 'Error al actualizar: ' . $stmt->error;
            $stmt->close();
            return ['ok' => false, 'message' => $message];
        }

        $prettyOk = hg_resources_admin_persist_pretty_id($link, $id, $prettySource);
        $stmt->close();

        return [
            'ok' => $prettyOk,
            'message' => $prettyOk ? 'Recurso actualizado.' : 'Recurso actualizado, pero no se pudo guardar pretty_id.',
        ];
    }
}

if (!function_exists('hg_resources_admin_fetch_rows')) {
    function hg_resources_admin_fetch_rows(mysqli $link): array
    {
        $result = $link->query(
            "SELECT id, pretty_id, name, kind, sort_order, description
             FROM dim_systems_resources
             ORDER BY kind ASC, sort_order ASC, name ASC"
        );
        if (!$result) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->close();

        return $rows;
    }
}
