<?php
// Shared, route-aware feed for the public home pages.

if (!function_exists('hg_recent_content_feed')) {
    function hg_recent_content_feed(mysqli $link, int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        $sql = "
            SELECT u.entity_type, u.entity_id, u.updated_at,
                   c.pretty_id, c.title, c.description
            FROM fact_content_updates u
            INNER JOIN (
                SELECT 'chapter' AS entity_type, id, pretty_id, name AS title, synopsis AS description FROM dim_chapters
                UNION ALL
                SELECT 'character', id, pretty_id, name, CONCAT_WS(' · ', NULLIF(alias, ''), NULLIF(garou_name, '')) FROM fact_characters
                UNION ALL
                SELECT 'chronicle', id, pretty_id, name, description FROM dim_chronicles
                UNION ALL
                SELECT 'organization', id, pretty_id, name, description FROM dim_organizations
                UNION ALL
                SELECT 'document', id, pretty_id, title, content FROM fact_docs
                UNION ALL
                SELECT 'item', id, pretty_id, name, description FROM fact_items
                UNION ALL
                SELECT 'gift', id, pretty_id, name, description FROM fact_gifts
                UNION ALL
                SELECT 'rite', id, pretty_id, name, description FROM fact_rites
                UNION ALL
                SELECT 'system', id, pretty_id, name, description FROM dim_systems
                UNION ALL
                SELECT 'timeline_event', id, pretty_id, title, description FROM fact_timeline_events
            ) c ON c.entity_type = u.entity_type AND c.id = u.entity_id
            ORDER BY u.updated_at DESC, u.entity_type ASC, u.entity_id DESC
            LIMIT {$limit}
        ";
        $result = mysqli_query($link, $sql);
        if (!$result) {
            return [];
        }

        $meta = [
            'chapter' => ['label' => 'Capítulo', 'base' => '/chapters'],
            'character' => ['label' => 'Personaje', 'base' => '/characters'],
            'chronicle' => ['label' => 'Crónica', 'base' => '/chronicles'],
            'organization' => ['label' => 'Organización', 'base' => '/organizations'],
            'document' => ['label' => 'Documento', 'base' => '/documents'],
            'item' => ['label' => 'Objeto', 'base' => '/inventory/items'],
            'gift' => ['label' => 'Don', 'base' => '/powers/gift'],
            'rite' => ['label' => 'Rito', 'base' => '/powers/rite'],
            'system' => ['label' => 'Sistema', 'base' => '/systems'],
            'timeline_event' => ['label' => 'Evento', 'base' => '/timeline/event'],
        ];

        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $type = (string)($row['entity_type'] ?? '');
            if (!isset($meta[$type])) {
                continue;
            }
            $id = (int)($row['entity_id'] ?? 0);
            $segment = trim((string)($row['pretty_id'] ?? ''));
            if ($id <= 0 || ($segment === '' && $id <= 0)) {
                continue;
            }
            $segment = $segment !== '' ? $segment : (string)$id;
            $row['type_label'] = $meta[$type]['label'];
            $row['href'] = $meta[$type]['base'] . '/' . rawurlencode($segment);
            $items[] = $row;
        }
        mysqli_free_result($result);

        return $items;
    }
}
