<?php
/**
 * Public read model for editorial revelation layers.
 *
 * Important: unpublished records must never reach public controllers, templates,
 * exports, tooltips, search endpoints or JavaScript. The spoiler tables are
 * separate from the legacy public relationship table by design.
 *
 * A missing/unavailable schema fails closed: [] is returned, never a draft.
 */

if (!function_exists('hg_spoilers_public_layers')) {
    function hg_spoilers_public_layers(mysqli $link, string $entityKind, int $entityId): array
    {
        if ($entityId <= 0 || $entityKind !== 'character') return [];
        try {
            $stmt = $link->prepare(
                "SELECT id, title, content_text
                   FROM fact_spoiler_layers
                  WHERE entity_kind = ? AND entity_id = ? AND is_published = 1
                  ORDER BY id ASC"
            );
            if (!$stmt) return [];
            $stmt->bind_param('si', $entityKind, $entityId);
            if (!$stmt->execute()) { $stmt->close(); return []; }
            $result = $stmt->get_result();
            $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
            return $rows;
        } catch (Throwable $error) {
            error_log('hg_spoilers_public_layers: spoiler data unavailable');
            return [];
        }
    }
}

if (!function_exists('hg_spoilers_public_character_relations')) {
    function hg_spoilers_public_character_relations(mysqli $link, int $characterId, string $excludedChronicles = ''): array
    {
        if ($characterId <= 0) return [];
        // The existing domain helper normalizes chronicle IDs; do not interpolate request data.
        $condition = function_exists('hg_characters_chronicle_condition')
            ? hg_characters_chronicle_condition('p2', $excludedChronicles)
            : '1=1';
        $scope = $condition === '1=1' ? '' : ' AND ' . $condition;
        $rows = [];
        foreach (['outgoing' => ['source_id', 'target_id'], 'incoming' => ['target_id', 'source_id']] as $direction => $keys) {
            [$match, $other] = $keys;
            // A layer always belongs to the source character of its guarded relations.
            $sql = "SELECT -sr.id AS id, sr.source_id, sr.target_id, sr.relation_type,
                           sr.tag, sr.importance, sr.description, sr.arrows,
                           p2.id AS other_id, p2.name, p2.name AS other_name,
                           p2.alias, p2.alias AS other_alias, p2.image_url, p2.gender,
                           '" . $direction . "' AS direction
                      FROM bridge_spoiler_character_relations sr
                      INNER JOIN fact_spoiler_layers sl ON sl.id = sr.layer_id
                      INNER JOIN fact_characters p2 ON p2.id = sr." . $other . "
                     WHERE sr." . $match . " = ?
                       AND sl.entity_kind = 'character'
                       AND sl.entity_id = sr.source_id
                       AND sl.is_published = 1" . $scope;
            try {
                $stmt = $link->prepare($sql);
                if (!$stmt) continue;
                $stmt->bind_param('i', $characterId);
                if (!$stmt->execute()) { $stmt->close(); continue; }
                $result = $stmt->get_result();
                if ($result) $rows = array_merge($rows, $result->fetch_all(MYSQLI_ASSOC));
                $stmt->close();
            } catch (Throwable $error) {
                error_log('hg_spoilers_public_character_relations: spoiler data unavailable');
                return [];
            }
        }
        return $rows;
    }
}

if (!function_exists('hg_spoilers_public_graph_relations')) {
    function hg_spoilers_public_graph_relations(mysqli $link): array
    {
        try {
            $result = $link->query(
                "SELECT -sr.id AS id, sr.source_id, sr.target_id, sr.relation_type,
                        sr.tag, sr.importance, sr.description, sr.arrows
                   FROM bridge_spoiler_character_relations sr
                   INNER JOIN fact_spoiler_layers sl ON sl.id = sr.layer_id
                  WHERE sl.entity_kind = 'character'
                    AND sl.entity_id = sr.source_id
                    AND sl.is_published = 1"
            );
            if (!$result) return [];
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
            return $rows;
        } catch (Throwable $error) {
            error_log('hg_spoilers_public_graph_relations: spoiler data unavailable');
            return [];
        }
    }
}
