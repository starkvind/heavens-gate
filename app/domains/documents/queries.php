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
    function hg_documents_fetch_detail(mysqli $link, int $documentId)
    {
        if ($documentId <= 0) return null;

        $stmt = mysqli_prepare(
            $link,
            "SELECT dz.title, d.kind AS section_id, dz.content, dz.source
             FROM fact_docs dz
             LEFT JOIN dim_doc_categories d ON d.id = dz.section_id
             WHERE dz.id = ? LIMIT 1"
        );
        if (!$stmt) return false;

        mysqli_stmt_bind_param($stmt, 'i', $documentId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return false;
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

if (!function_exists('hg_documents_resolve_id')) {
    function hg_documents_resolve_id(mysqli $link, string $raw): int
    {
        $raw = trim(rawurldecode($raw));
        if ($raw === '') return 0;
        if (preg_match('/^\d+$/', $raw)) return (int)$raw;
        if (function_exists('resolve_pretty_id')) {
            $resolved = resolve_pretty_id($link, 'fact_docs', $raw);
            if ((int)$resolved > 0) return (int)$resolved;
        }
        if (!function_exists('slugify_pretty_id')) return 0;

        $prettyExpr = hg_documents_has_column($link, 'fact_docs', 'pretty_id')
            ? "COALESCE(pretty_id, '')"
            : "''";
        $result = mysqli_query($link, "SELECT id, title, {$prettyExpr} AS pretty_id FROM fact_docs");
        if (!$result) return 0;

        $resolvedId = 0;
        while ($row = mysqli_fetch_assoc($result)) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            if (trim((string)($row['pretty_id'] ?? '')) === $raw || slugify_pretty_id((string)($row['title'] ?? '')) === $raw) {
                $resolvedId = $id;
                break;
            }
        }
        mysqli_free_result($result);
        return $resolvedId;
    }
}

if (!function_exists('hg_documents_fetch_mobile_catalog')) {
    function hg_documents_fetch_mobile_catalog(mysqli $link): ?array
    {
        $hasPretty = hg_documents_has_column($link, 'fact_docs', 'pretty_id');
        $hasBibliography = hg_documents_has_column($link, 'fact_docs', 'bibliography_id')
            && hg_documents_has_table($link, 'dim_bibliographies');
        $hasCategories = hg_documents_has_table($link, 'dim_doc_categories')
            && hg_documents_has_column($link, 'fact_docs', 'section_id');
        $prettyExpr = $hasPretty ? "COALESCE(d.pretty_id, '')" : "''";
        $categoryJoin = $hasCategories ? 'LEFT JOIN dim_doc_categories cat ON cat.id = d.section_id' : '';
        $categoryExpr = $hasCategories ? "COALESCE(cat.kind, '')" : "''";
        $categoryOrder = $hasCategories && hg_documents_has_column($link, 'dim_doc_categories', 'sort_order')
            ? 'COALESCE(cat.sort_order, 999999),'
            : '';
        $biblioJoin = $hasBibliography ? 'LEFT JOIN dim_bibliographies bib ON bib.id = d.bibliography_id' : '';
        $biblioExpr = $hasBibliography ? "COALESCE(bib.name, '')" : "''";

        $result = mysqli_query(
            $link,
            "SELECT d.id, {$prettyExpr} AS pretty_id, d.title, {$categoryExpr} AS category,
                    {$biblioExpr} AS origin, d.content
             FROM fact_docs d
             {$categoryJoin}
             {$biblioJoin}
             ORDER BY {$categoryOrder} d.title ASC, d.id ASC"
        );
        if (!$result) return null;
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_documents_fetch_mobile_detail')) {
    function hg_documents_fetch_mobile_detail(mysqli $link, int $documentId): ?array
    {
        if ($documentId <= 0) return null;
        $hasPretty = hg_documents_has_column($link, 'fact_docs', 'pretty_id');
        $hasSource = hg_documents_has_column($link, 'fact_docs', 'source');
        $hasBibliography = hg_documents_has_column($link, 'fact_docs', 'bibliography_id')
            && hg_documents_has_table($link, 'dim_bibliographies');
        $hasCategories = hg_documents_has_table($link, 'dim_doc_categories')
            && hg_documents_has_column($link, 'fact_docs', 'section_id');
        $prettyExpr = $hasPretty ? "COALESCE(d.pretty_id, '')" : "''";
        $sourceExpr = $hasSource ? "COALESCE(d.source, '')" : "''";
        $categoryJoin = $hasCategories ? 'LEFT JOIN dim_doc_categories cat ON cat.id = d.section_id' : '';
        $categoryExpr = $hasCategories ? "COALESCE(cat.kind, '')" : "''";
        $biblioJoin = $hasBibliography ? 'LEFT JOIN dim_bibliographies bib ON bib.id = d.bibliography_id' : '';
        $biblioExpr = $hasBibliography ? "COALESCE(bib.name, '')" : "''";

        $stmt = mysqli_prepare(
            $link,
            "SELECT d.id, {$prettyExpr} AS pretty_id, d.title, d.content, {$sourceExpr} AS source,
                    {$categoryExpr} AS category, {$biblioExpr} AS origin
             FROM fact_docs d
             {$categoryJoin}
             {$biblioJoin}
             WHERE d.id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $documentId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? (mysqli_fetch_assoc($result) ?: null) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($stmt);
        return $row;
    }
}

if (!function_exists('hg_documents_fetch_mobile_characters')) {
    function hg_documents_fetch_mobile_characters(mysqli $link, int $documentId, $excludedChronicles = '2,7'): array
    {
        if ($documentId <= 0
            || !hg_documents_has_table($link, 'bridge_characters_docs')
            || !hg_documents_has_table($link, 'fact_characters')) {
            return [];
        }

        $hasAlias = hg_documents_has_column($link, 'fact_characters', 'alias');
        $hasImage = hg_documents_has_column($link, 'fact_characters', 'image_url');
        $hasGender = hg_documents_has_column($link, 'fact_characters', 'gender');
        $hasStatus = hg_documents_has_column($link, 'fact_characters', 'status_id')
            && hg_documents_has_table($link, 'dim_character_status');
        $hasSort = hg_documents_has_column($link, 'bridge_characters_docs', 'sort_order');
        $aliasExpr = $hasAlias ? "COALESCE(c.alias, '')" : "''";
        $imageExpr = $hasImage ? "COALESCE(c.image_url, '')" : "''";
        $genderExpr = $hasGender ? "COALESCE(c.gender, '')" : "''";
        $statusJoin = $hasStatus ? 'LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id' : '';
        $statusExpr = $hasStatus ? "COALESCE(dcs.label, '')" : "''";
        $order = $hasSort ? 'b.sort_order ASC, c.name ASC' : 'c.name ASC';

        $ids = [];
        foreach (preg_split('/\s*,\s*/', trim((string)$excludedChronicles)) as $part) {
            if ($part !== '' && preg_match('/^\d+$/', (string)$part)) {
                $id = (int)$part;
                if ($id > 0) $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        $sql = "SELECT c.id, c.name, {$aliasExpr} AS alias, {$imageExpr} AS image_url,
                       {$genderExpr} AS gender, {$statusExpr} AS status
                FROM bridge_characters_docs b
                INNER JOIN fact_characters c ON c.id = b.character_id
                {$statusJoin}
                WHERE b.doc_id = ?";
        $types = 'i';
        $params = [$documentId];
        if ($ids) {
            $sql .= ' AND c.chronicle_id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $types .= str_repeat('i', count($ids));
            foreach ($ids as $id) $params[] = $id;
        }
        $sql .= " ORDER BY {$order}";

        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}
