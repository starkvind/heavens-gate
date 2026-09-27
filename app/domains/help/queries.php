<?php

if (!function_exists('hg_help_fetch_published_pages')) {
    function hg_help_fetch_published_pages(mysqli $link): array
    {
        $sql = "SELECT id, slug, nav_label, title, summary, lead, sort_order
                FROM fact_help_pages
                WHERE is_published = 1
                ORDER BY sort_order ASC, id ASC";
        $result = @$link->query($sql);
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

if (!function_exists('hg_help_fetch_page_by_slug')) {
    function hg_help_fetch_page_by_slug(mysqli $link, string $slug, bool $publishedOnly = true): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $sql = "SELECT id, slug, nav_label, title, summary, lead, meta_description, content_html, sort_order, is_published
                FROM fact_help_pages
                WHERE slug = ?";
        if ($publishedOnly) {
            $sql .= " AND is_published = 1";
        }
        $sql .= " LIMIT 1";

        $stmt = @$link->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}
