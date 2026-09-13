<?php

if (!function_exists('hg_documents_has_table')) {
    function hg_documents_has_table(mysqli $link, string $table): bool
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') return false;
        if (array_key_exists($table, $cache)) return $cache[$table];

        $stmt = mysqli_prepare(
            $link,
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        if (!$stmt) return $cache[$table] = false;
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $cache[$table] = ((int)$count > 0);
    }
}

if (!function_exists('hg_documents_has_column')) {
    function hg_documents_has_column(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') return false;
        $key = $table . ':' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];

        $stmt = mysqli_prepare(
            $link,
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        if (!$stmt) return $cache[$key] = false;
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $cache[$key] = ((int)$count > 0);
    }
}

if (!function_exists('hg_documents_fetch_catalog')) {
    function hg_documents_fetch_catalog(mysqli $link): ?array
    {
        $result = mysqli_query(
            $link,
            "SELECT d2.id AS document_id,
                    d2.pretty_id AS document_pretty_id,
                    d2.title AS document_name,
                    d.kind AS document_category,
                    COALESCE(nb.name, '') AS document_origin
             FROM fact_docs d2
             LEFT JOIN dim_doc_categories d ON d2.section_id = d.id
             LEFT JOIN dim_bibliographies nb ON d2.bibliography_id = nb.id
             ORDER BY d.sort_order"
        );
        if (!$result) return null;

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_documents_fetch_detail')) {
    function hg_documents_fetch_detail(mysqli $link, int $documentId): ?array
    {
        if ($documentId <= 0) return null;

        $stmt = mysqli_prepare(
            $link,
            "SELECT dz.title, d.kind AS section_id, dz.content, dz.source
             FROM fact_docs dz
             LEFT JOIN dim_doc_categories d ON d.id = dz.section_id
             WHERE dz.id = ? LIMIT 1"
        );
        if (!$stmt) return null;

        mysqli_stmt_bind_param($stmt, 'i', $documentId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return null;
        }
        $row = mysqli_fetch_assoc($result) ?: null;
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row;
    }
}

if (!function_exists('hg_documents_fetch_characters')) {
    function hg_documents_fetch_characters(mysqli $link, int $documentId): array
    {
        if ($documentId <= 0
            || !hg_documents_has_table($link, 'bridge_characters_docs')
            || !hg_documents_has_table($link, 'fact_characters')) {
            return [];
        }

        $kindExpr = "''";
        foreach (['character_kind', 'kind'] as $column) {
            if (hg_documents_has_column($link, 'fact_characters', $column)) {
                $kindExpr = "c.`{$column}`";
                break;
            }
        }
        $order = hg_documents_has_column($link, 'bridge_characters_docs', 'sort_order')
            ? 'b.sort_order ASC, c.name ASC'
            : 'c.name ASC';

        $stmt = mysqli_prepare(
            $link,
            "SELECT c.id, c.name, c.alias, c.image_url, c.gender,
                    COALESCE(dcs.label, '') AS status,
                    {$kindExpr} AS character_kind
             FROM bridge_characters_docs b
             INNER JOIN fact_characters c ON c.id = b.character_id
             LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id
             WHERE b.doc_id = ?
             ORDER BY {$order}"
        );
        if (!$stmt) return [];

        mysqli_stmt_bind_param($stmt, 'i', $documentId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}
