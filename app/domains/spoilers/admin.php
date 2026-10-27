<?php
/**
 * Admin-only editorial source for spoiler layers.
 * Never include this file from public pages or APIs.
 */

if (!function_exists('hg_spoilers_admin_schema_ready')) {
    function hg_spoilers_admin_schema_ready(mysqli $link): bool
    {
        try {
            $a = @$link->query('SELECT id, entity_kind, entity_id, title, content_text, is_published, published_at FROM fact_spoiler_layers LIMIT 0');
            $b = @$link->query('SELECT id, layer_id, source_id, target_id, relation_type, tag, importance, description, arrows FROM bridge_spoiler_character_relations LIMIT 0');
            if ($a) $a->free();
            if ($b) $b->free();
            return (bool)($a && $b);
        } catch (Throwable $error) {
            return false;
        }
    }
}

if (!function_exists('hg_spoilers_admin_characters')) {
    function hg_spoilers_admin_characters(mysqli $link): array
    {
        $result = $link->query('SELECT id, name FROM fact_characters ORDER BY name ASC');
        if (!$result) return [];
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        return $rows;
    }
}

if (!function_exists('hg_spoilers_admin_list')) {
    function hg_spoilers_admin_list(mysqli $link, int $characterId = 0): array
    {
        $sql = "SELECT l.id, l.entity_id, l.title, l.is_published, l.published_at,
                       c.name AS character_name
                  FROM fact_spoiler_layers l
                  INNER JOIN fact_characters c ON c.id = l.entity_id
                 WHERE l.entity_kind = 'character'";
        if ($characterId > 0) $sql .= ' AND l.entity_id = ?';
        $sql .= ' ORDER BY l.entity_id, l.id DESC';
        $stmt = $link->prepare($sql);
        if (!$stmt) return [];
        if ($characterId > 0) $stmt->bind_param('i', $characterId);
        if (!$stmt->execute()) { $stmt->close(); return []; }
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_spoilers_admin_get')) {
    function hg_spoilers_admin_get(mysqli $link, int $id): ?array
    {
        if ($id <= 0) return null;
        $stmt = $link->prepare("SELECT id, entity_kind, entity_id, title, content_text, is_published, published_at
                                  FROM fact_spoiler_layers WHERE id = ? AND entity_kind = 'character' LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) { $stmt->close(); return null; }
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('hg_spoilers_admin_list_relations')) {
    function hg_spoilers_admin_list_relations(mysqli $link, int $layerId): array
    {
        if ($layerId <= 0) return [];
        $stmt = $link->prepare(
            'SELECT r.id, r.target_id, c.name AS target_name, r.relation_type, r.tag, r.importance, r.description, r.arrows
               FROM bridge_spoiler_character_relations r
               INNER JOIN fact_characters c ON c.id = r.target_id
              WHERE r.layer_id = ? ORDER BY r.id'
        );
        if (!$stmt) return [];
        $stmt->bind_param('i', $layerId);
        if (!$stmt->execute()) { $stmt->close(); return []; }
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_spoilers_admin_character_exists')) {
    function hg_spoilers_admin_character_exists(mysqli $link, int $id): bool
    {
        if ($id <= 0) return false;
        $stmt = $link->prepare('SELECT id FROM fact_characters WHERE id = ? LIMIT 1');
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $result = $ok ? $stmt->get_result() : null;
        $found = $result && $result->num_rows > 0;
        $stmt->close();
        return (bool)$found;
    }
}

if (!function_exists('hg_spoilers_admin_save_layer')) {
    function hg_spoilers_admin_save_layer(mysqli $link, int $id, int $characterId, string $title, string $content): array
    {
        $title = trim($title);
        $content = trim($content);
        if ($title === '' || mb_strlen($title, 'UTF-8') > 190) {
            return ['ok' => false, 'message' => 'Indica un título de hasta 190 caracteres.'];
        }
        if ($id <= 0 && !hg_spoilers_admin_character_exists($link, $characterId)) {
            return ['ok' => false, 'message' => 'Selecciona un personaje existente.'];
        }
        // The owner is immutable after creation so attached relations cannot silently move.
        if ($id > 0) {
            $layer = hg_spoilers_admin_get($link, $id);
            if (!$layer) return ['ok' => false, 'message' => 'La capa no existe.'];
            $characterId = (int)$layer['entity_id'];
            $stmt = $link->prepare("UPDATE fact_spoiler_layers
                                      SET title = ?, content_text = ?, updated_at = CURRENT_TIMESTAMP
                                    WHERE id = ? AND entity_kind = 'character'");
            if (!$stmt) return ['ok' => false, 'message' => 'No se pudo actualizar la capa.'];
            $stmt->bind_param('ssi', $title, $content, $id);
        } else {
            $stmt = $link->prepare("INSERT INTO fact_spoiler_layers
                                    (entity_kind, entity_id, title, content_text, is_published)
                                   VALUES ('character', ?, ?, ?, 0)");
            if (!$stmt) return ['ok' => false, 'message' => 'No se pudo crear la capa.'];
            $stmt->bind_param('iss', $characterId, $title, $content);
        }
        $ok = $stmt->execute();
        if ($ok && $id <= 0) $id = (int)$link->insert_id;
        $stmt->close();
        return ['ok' => $ok, 'id' => $id, 'character_id' => $characterId,
                'message' => $ok ? 'Capa guardada. El estado de publicación no se modifica.' : 'Error guardando la capa.'];
    }
}

if (!function_exists('hg_spoilers_admin_publish')) {
    function hg_spoilers_admin_publish(mysqli $link, int $id, bool $publish): array
    {
        $layer = hg_spoilers_admin_get($link, $id);
        if (!$layer) return ['ok' => false, 'message' => 'Capa no encontrada.'];
        $relations = hg_spoilers_admin_list_relations($link, $id);
        if ($publish && trim((string)$layer['content_text']) === '' && !$relations) {
            return ['ok' => false, 'message' => 'No puedes publicar una capa sin texto ni relaciones.'];
        }
        $state = $publish ? 1 : 0;
        $stmt = $link->prepare("UPDATE fact_spoiler_layers
                                  SET is_published = ?,
                                      published_at = IF(? = 1, CURRENT_TIMESTAMP, NULL),
                                      updated_at = CURRENT_TIMESTAMP
                                WHERE id = ? AND entity_kind = 'character'");
        if (!$stmt) return ['ok' => false, 'message' => 'No se pudo cambiar la publicación.'];
        $stmt->bind_param('iii', $state, $state, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return ['ok' => $ok, 'character_id' => (int)$layer['entity_id'],
                'message' => $ok ? ($publish ? 'Capa publicada para todos los lectores.' : 'Capa retirada de la vista pública.') : 'No se pudo actualizar el estado.'];
    }
}

if (!function_exists('hg_spoilers_admin_add_relation')) {
    function hg_spoilers_admin_add_relation(mysqli $link, int $layerId, int $targetId, string $type, string $tag, int $importance, string $description, string $arrows): array
    {
        $layer = hg_spoilers_admin_get($link, $layerId);
        if (!$layer) return ['ok' => false, 'message' => 'Capa no encontrada.'];
        $source = (int)$layer['entity_id'];
        if ($targetId === $source || !hg_spoilers_admin_character_exists($link, $targetId)) {
            return ['ok' => false, 'message' => 'Elige otro personaje existente como destino.'];
        }
        $type = trim($type);
        if ($type === '' || mb_strlen($type, 'UTF-8') > 100) {
            return ['ok' => false, 'message' => 'Indica un tipo de relación (máximo 100 caracteres).'];
        }
        $tag = substr(trim($tag), 0, 50);
        $importance = max(0, min(10, $importance));
        if (!in_array($arrows, ['', 'to', 'from', 'to,from'], true)) $arrows = '';
        $stmt = $link->prepare(
            'INSERT INTO bridge_spoiler_character_relations
             (layer_id, source_id, target_id, relation_type, tag, importance, description, arrows)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) return ['ok' => false, 'message' => 'No se pudo preparar la relación.'];
        $stmt->bind_param('iiississ', $layerId, $source, $targetId, $type, $tag, $importance, $description, $arrows);
        $ok = $stmt->execute();
        $stmt->close();
        return ['ok' => $ok, 'character_id' => $source,
                'message' => $ok ? 'Relación incorporada a la capa.' : 'No se pudo incorporar la relación.'];
    }
}

if (!function_exists('hg_spoilers_admin_delete_relation')) {
    function hg_spoilers_admin_delete_relation(mysqli $link, int $layerId, int $relationId): array
    {
        $layer = hg_spoilers_admin_get($link, $layerId);
        if (!$layer) return ['ok' => false, 'message' => 'Capa no encontrada.'];
        $stmt = $link->prepare('DELETE FROM bridge_spoiler_character_relations WHERE layer_id = ? AND id = ?');
        if (!$stmt) return ['ok' => false, 'message' => 'No se pudo preparar la operación.'];
        $stmt->bind_param('ii', $layerId, $relationId);
        $ok = $stmt->execute() && $stmt->affected_rows === 1;
        $stmt->close();
        return ['ok' => $ok, 'character_id' => (int)$layer['entity_id'],
                'message' => $ok ? 'Relación eliminada de la capa.' : 'No se encontró esa relación.'];
    }
}
