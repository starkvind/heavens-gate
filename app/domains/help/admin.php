<?php

if (!function_exists('hg_help_admin_slug_is_valid')) {
    function hg_help_admin_slug_is_valid(string $slug): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }
}

if (!function_exists('hg_help_admin_content_error')) {
    function hg_help_admin_content_error(string $html): ?string
    {
        if (trim($html) === '') {
            return 'El contenido HTML es obligatorio.';
        }
        if (preg_match('/<\s*(script|iframe|object|embed)\b/i', $html)) {
            return 'El contenido no puede incluir script, iframe, object ni embed.';
        }
        if (preg_match('/\son[a-z0-9_-]+\s*=/i', $html)) {
            return 'El contenido no puede incluir manejadores JavaScript inline.';
        }
        if (preg_match('/(?:href|src)\s*=\s*["\']\s*javascript:/i', $html)) {
            return 'El contenido no puede usar URLs javascript:.';
        }
        return null;
    }
}

if (!function_exists('hg_help_admin_fetch_rows')) {
    function hg_help_admin_fetch_rows(mysqli $link): array
    {
        $result = @$link->query(
            "SELECT id, slug, nav_label, title, summary, sort_order, is_published, updated_at
             FROM fact_help_pages
             ORDER BY sort_order ASC, id ASC"
        );
        if (!$result) return [];

        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        $result->close();
        return $rows;
    }
}

if (!function_exists('hg_help_admin_fetch_one')) {
    function hg_help_admin_fetch_one(mysqli $link, int $id): ?array
    {
        if ($id <= 0) return null;
        $stmt = @$link->prepare(
            "SELECT id, slug, nav_label, title, summary, lead, meta_description, content_html, sort_order, is_published
             FROM fact_help_pages WHERE id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('hg_help_admin_save')) {
    function hg_help_admin_save(mysqli $link, array $data): array
    {
        $id = max(0, (int)($data['id'] ?? 0));
        $slug = strtolower(trim((string)($data['slug'] ?? '')));
        $navLabel = trim((string)($data['nav_label'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        $summary = trim((string)($data['summary'] ?? ''));
        $lead = trim((string)($data['lead'] ?? ''));
        $meta = trim((string)($data['meta_description'] ?? ''));
        $content = trim((string)($data['content_html'] ?? ''));
        $sortOrder = (int)($data['sort_order'] ?? 0);
        $published = !empty($data['is_published']) ? 1 : 0;

        if ($title === '' || $slug === '' || $lead === '') {
            return ['ok' => false, 'message' => 'Título, slug y entradilla son obligatorios.'];
        }
        if (!hg_help_admin_slug_is_valid($slug)) {
            return ['ok' => false, 'message' => 'El slug solo admite minúsculas, números y guiones.'];
        }
        $contentError = hg_help_admin_content_error($content);
        if ($contentError !== null) {
            return ['ok' => false, 'message' => $contentError];
        }
        if ($navLabel === '') $navLabel = 'Guía';
        if ($meta === '') $meta = $summary !== '' ? $summary : $lead;

        if ($id > 0) {
            $stmt = @$link->prepare(
                "UPDATE fact_help_pages
                 SET slug=?, nav_label=?, title=?, summary=?, lead=?, meta_description=?, content_html=?, sort_order=?, is_published=?
                 WHERE id=?"
            );
            if (!$stmt) return ['ok' => false, 'message' => 'No se pudo preparar la actualización: ' . $link->error];
            $stmt->bind_param('sssssssiii', $slug, $navLabel, $title, $summary, $lead, $meta, $content, $sortOrder, $published, $id);
        } else {
            $stmt = @$link->prepare(
                "INSERT INTO fact_help_pages
                 (slug, nav_label, title, summary, lead, meta_description, content_html, sort_order, is_published)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            if (!$stmt) return ['ok' => false, 'message' => 'No se pudo preparar el alta: ' . $link->error];
            $stmt->bind_param('sssssssii', $slug, $navLabel, $title, $summary, $lead, $meta, $content, $sortOrder, $published);
        }

        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();
        if (!$ok) {
            if (stripos($error, 'Duplicate') !== false) {
                return ['ok' => false, 'message' => 'Ya existe una página de ayuda con ese slug.'];
            }
            return ['ok' => false, 'message' => 'No se pudo guardar: ' . $error];
        }

        return ['ok' => true, 'message' => $id > 0 ? 'Página de ayuda actualizada.' : 'Página de ayuda creada.'];
    }
}

if (!function_exists('hg_help_admin_delete')) {
    function hg_help_admin_delete(mysqli $link, int $id): array
    {
        if ($id <= 0) return ['ok' => false, 'message' => 'ID inválido.'];
        $stmt = @$link->prepare("DELETE FROM fact_help_pages WHERE id = ?");
        if (!$stmt) return ['ok' => false, 'message' => 'No se pudo preparar el borrado: ' . $link->error];
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();
        return $ok
            ? ['ok' => true, 'message' => 'Página de ayuda eliminada.']
            : ['ok' => false, 'message' => 'No se pudo borrar: ' . $error];
    }
}
