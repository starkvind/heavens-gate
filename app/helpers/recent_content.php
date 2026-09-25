<?php
// Shared, route-aware feed for the public home pages.

if (!function_exists('hg_recent_content_feed')) {
    function hg_recent_content_feed(mysqli $link, int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        $sql = "
            SELECT
                u.entity_type,
                u.entity_id,
                u.updated_at,
                CASE u.entity_type
                    WHEN 'chapter' THEN ch.pretty_id
                    WHEN 'character' THEN c.pretty_id
                    WHEN 'chronicle' THEN chr.pretty_id
                    WHEN 'organization' THEN org.pretty_id
                    WHEN 'document' THEN doc.pretty_id
                    WHEN 'item' THEN i.pretty_id
                    WHEN 'gift' THEN g.pretty_id
                    WHEN 'rite' THEN r.pretty_id
                    WHEN 'system' THEN s.pretty_id
                    WHEN 'timeline_event' THEN te.pretty_id
                END AS pretty_id,
                CASE u.entity_type
                    WHEN 'chapter' THEN ch.name
                    WHEN 'character' THEN c.name
                    WHEN 'chronicle' THEN chr.name
                    WHEN 'organization' THEN org.name
                    WHEN 'document' THEN doc.title
                    WHEN 'item' THEN i.name
                    WHEN 'gift' THEN g.name
                    WHEN 'rite' THEN r.name
                    WHEN 'system' THEN s.name
                    WHEN 'timeline_event' THEN te.title
                END AS title,
                CASE u.entity_type
                    WHEN 'chapter' THEN ch.synopsis
                    WHEN 'character' THEN CONCAT_WS(' · ', NULLIF(c.alias, ''), NULLIF(c.garou_name, ''))
                    WHEN 'chronicle' THEN chr.description
                    WHEN 'organization' THEN org.description
                    WHEN 'document' THEN doc.content
                    WHEN 'item' THEN i.description
                    WHEN 'gift' THEN g.description
                    WHEN 'rite' THEN r.description
                    WHEN 'system' THEN s.description
                    WHEN 'timeline_event' THEN te.description
                END AS description
            FROM fact_content_updates u
            LEFT JOIN dim_chapters ch
                ON u.entity_type = 'chapter' AND ch.id = u.entity_id
            LEFT JOIN fact_characters c
                ON u.entity_type = 'character' AND c.id = u.entity_id
            LEFT JOIN dim_chronicles chr
                ON u.entity_type = 'chronicle' AND chr.id = u.entity_id
            LEFT JOIN dim_organizations org
                ON u.entity_type = 'organization' AND org.id = u.entity_id
            LEFT JOIN fact_docs doc
                ON u.entity_type = 'document' AND doc.id = u.entity_id
            LEFT JOIN fact_items i
                ON u.entity_type = 'item' AND i.id = u.entity_id
            LEFT JOIN fact_gifts g
                ON u.entity_type = 'gift' AND g.id = u.entity_id
            LEFT JOIN fact_rites r
                ON u.entity_type = 'rite' AND r.id = u.entity_id
            LEFT JOIN dim_systems s
                ON u.entity_type = 'system' AND s.id = u.entity_id
            LEFT JOIN fact_timeline_events te
                ON u.entity_type = 'timeline_event' AND te.id = u.entity_id
            WHERE
                (u.entity_type = 'chapter' AND ch.id IS NOT NULL)
                OR (u.entity_type = 'character' AND c.id IS NOT NULL)
                OR (u.entity_type = 'chronicle' AND chr.id IS NOT NULL)
                OR (u.entity_type = 'organization' AND org.id IS NOT NULL)
                OR (u.entity_type = 'document' AND doc.id IS NOT NULL)
                OR (u.entity_type = 'item' AND i.id IS NOT NULL)
                OR (u.entity_type = 'gift' AND g.id IS NOT NULL)
                OR (u.entity_type = 'rite' AND r.id IS NOT NULL)
                OR (u.entity_type = 'system' AND s.id IS NOT NULL)
                OR (u.entity_type = 'timeline_event' AND te.id IS NOT NULL)
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
