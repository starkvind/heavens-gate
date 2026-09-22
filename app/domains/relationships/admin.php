<?php

if (!function_exists('hg_relationships_admin_fetch_characters')) {
    function hg_relationships_admin_fetch_characters(mysqli $link): array
    {
        $result = $link->query(
            "SELECT id, name FROM fact_characters WHERE chronicle_id NOT IN (2, 7) ORDER BY name ASC"
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

if (!function_exists('hg_relationships_admin_delete')) {
    function hg_relationships_admin_delete(mysqli $link, int $id): bool
    {
        $stmt = $link->prepare("DELETE FROM bridge_characters_relations WHERE id = ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        return true;
    }
}

if (!function_exists('hg_relationships_admin_create')) {
    function hg_relationships_admin_create(
        mysqli $link,
        int $source,
        int $target,
        string $type,
        string $tag,
        int $importance,
        string $description,
        string $arrows
    ): ?int {
        $stmt = $link->prepare(
            "INSERT INTO bridge_characters_relations
             (source_id, target_id, relation_type, tag, importance, description, arrows)
             VALUES (?,?,?,?,?,?,?)"
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('iississ', $source, $target, $type, $tag, $importance, $description, $arrows);
        $stmt->execute();
        $newId = (int)$stmt->insert_id;
        $stmt->close();
        return $newId;
    }
}

if (!function_exists('hg_relationships_admin_update')) {
    function hg_relationships_admin_update(
        mysqli $link,
        int $id,
        int $source,
        int $target,
        string $type,
        string $tag,
        int $importance,
        string $description,
        string $arrows
    ): bool {
        $stmt = $link->prepare(
            "UPDATE bridge_characters_relations
             SET source_id=?, target_id=?, relation_type=?, tag=?, importance=?, description=?, arrows=?
             WHERE id=?"
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iississi', $source, $target, $type, $tag, $importance, $description, $arrows, $id);
        $stmt->execute();
        $stmt->close();
        return true;
    }
}

if (!function_exists('hg_relationships_admin_fetch_rows')) {
    function hg_relationships_admin_fetch_rows(mysqli $link): array
    {
        $result = $link->query("SELECT * FROM bridge_characters_relations ORDER BY id DESC");
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
