<?php

include_once(__DIR__ . '/pretty.php');

if (!function_exists('hg_admin_project_root')) {
    function hg_admin_project_root(): string
    {
        $docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        if ($docroot !== '' && is_dir($docroot)) {
            return $docroot;
        }

        $rootGuess = realpath(__DIR__ . '/../../');
        return $rootGuess ? rtrim((string)$rootGuess, '/\\') : rtrim(__DIR__, '/\\');
    }
}

if (!function_exists('hg_admin_save_image_upload')) {
    function hg_admin_save_image_upload(
        array $file,
        string $entityKey,
        int $entityId,
        string $displayName,
        string $uploadDir,
        string $urlBase,
        int $maxBytes = 5242880
    ): array {
        if (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'msg' => 'no_file'];
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'msg' => 'Upload error (#' . (int)$file['error'] . ')'];
        }
        if ((int)($file['size'] ?? 0) > $maxBytes) {
            return ['ok' => false, 'msg' => 'File exceeds 5 MB'];
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'msg' => 'Invalid upload'];
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = (string)finfo_file($fi, $tmp);
                finfo_close($fi);
            }
        }
        if ($mime === '') {
            $gi = @getimagesize($tmp);
            $mime = (string)($gi['mime'] ?? '');
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'msg' => 'Unsupported format (JPG/PNG/GIF/WebP only)'];
        }

        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $entitySlug = slugify_pretty_id($entityKey);
        if ($entitySlug === '') {
            $entitySlug = 'img';
        }
        $nameSlug = slugify_pretty_id($displayName);
        if ($nameSlug === '') {
            $nameSlug = $entitySlug;
        }

        $ext = $allowed[$mime];
        $name = sprintf('%s-%d-%s-%s.%s', $entitySlug, max(0, $entityId), $nameSlug, date('YmdHis'), $ext);
        $dst = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (!@move_uploaded_file($tmp, $dst)) {
            return ['ok' => false, 'msg' => 'Could not move uploaded file'];
        }
        @chmod($dst, 0644);

        return ['ok' => true, 'url' => rtrim($urlBase, '/') . '/' . $name, 'path' => $dst];
    }
}

if (!function_exists('hg_admin_safe_unlink_upload')) {
    function hg_admin_safe_unlink_upload(string $relUrl, string $uploadDir): void
    {
        $relUrl = trim($relUrl);
        if ($relUrl === '' || preg_match('~^https?://~i', $relUrl)) {
            return;
        }

        $rel = '/' . ltrim($relUrl, '/');
        if (strpos($rel, '/img/') !== 0) {
            return;
        }

        $base = realpath($uploadDir);
        if ($base === false) {
            return;
        }

        $abs = hg_admin_project_root() . '/public' . $rel;
        $real = @realpath($abs);
        if ($real && strpos($real, $base) === 0 && is_file($real)) {
            @unlink($real);
        }
    }
}
