<?php

if (!function_exists('hg_gallery_allowed_extensions')) {
    function hg_gallery_allowed_extensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    }
}

if (!function_exists('hg_gallery_base_fs')) {
    function hg_gallery_base_fs(): string
    {
        $path = realpath(dirname(__DIR__, 3) . '/public/img/gallery');
        return is_string($path) ? $path : '';
    }
}

if (!function_exists('hg_gallery_valid_relative_path')) {
    function hg_gallery_valid_relative_path(string $relative): bool
    {
        return $relative === ''
            || (bool)preg_match('#^(?!/)(?!.*\\.\\.)([A-Za-z0-9 _\\.\\-]+/)*[A-Za-z0-9 _\\.\\-]+$#', $relative);
    }
}

if (!function_exists('hg_gallery_fs_join')) {
    function hg_gallery_fs_join(string $base, string $relative = ''): string
    {
        $relative = trim($relative, '/');
        return $relative === '' ? $base : ($base . '/' . $relative);
    }
}

if (!function_exists('hg_gallery_web_join')) {
    function hg_gallery_web_join(string $base, string $relative = ''): string
    {
        $relative = trim($relative, '/');
        if ($relative === '') {
            return rtrim($base, '/');
        }
        return rtrim($base, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
    }
}

if (!function_exists('hg_gallery_resolve_directory')) {
    function hg_gallery_resolve_directory(string $base, string $relative = ''): ?string
    {
        if ($base === '' || !is_dir($base) || !hg_gallery_valid_relative_path($relative)) {
            return null;
        }

        $candidate = realpath(hg_gallery_fs_join($base, $relative));
        if (!is_string($candidate) || !is_dir($candidate)) {
            return null;
        }

        $baseNormalized = rtrim(str_replace('\\\\', '/', $base), '/');
        $candidateNormalized = rtrim(str_replace('\\\\', '/', $candidate), '/');
        if ($candidateNormalized !== $baseNormalized
            && !str_starts_with($candidateNormalized . '/', $baseNormalized . '/')) {
            return null;
        }
        return $candidate;
    }
}

if (!function_exists('hg_gallery_list_subdirectories')) {
    function hg_gallery_list_subdirectories(string $absolute): array
    {
        $directories = [];
        if (!is_dir($absolute)) {
            return $directories;
        }

        foreach (array_diff(scandir($absolute) ?: [], ['.', '..']) as $item) {
            $path = $absolute . '/' . $item;
            if (is_dir($path) && strtolower($item) !== 'thumbnails') {
                $directories[] = $item;
            }
        }
        sort($directories, SORT_NATURAL | SORT_FLAG_CASE);
        return $directories;
    }
}

if (!function_exists('hg_gallery_list_images')) {
    function hg_gallery_list_images(string $absolute, ?array $allowed = null): array
    {
        $allowed = $allowed ?? hg_gallery_allowed_extensions();
        $images = [];
        if (!is_dir($absolute)) {
            return $images;
        }

        foreach (array_diff(scandir($absolute) ?: [], ['.', '..', 'thumbnails']) as $item) {
            $path = $absolute . '/' . $item;
            if (!is_file($path)) {
                continue;
            }
            $extension = strtolower((string)pathinfo($item, PATHINFO_EXTENSION));
            if (in_array($extension, $allowed, true)) {
                $images[] = $item;
            }
        }
        sort($images, SORT_NATURAL | SORT_FLAG_CASE);
        return $images;
    }
}

if (!function_exists('hg_gallery_title')) {
    function hg_gallery_title(string $filename): string
    {
        $name = (string)pathinfo($filename, PATHINFO_FILENAME);
        $name = trim((string)preg_replace('/\\s+/', ' ', str_replace(['-', '_'], ' ', $name)));
        return $name !== '' ? ucfirst($name) : $filename;
    }
}

if (!function_exists('hg_gallery_image_urls')) {
    function hg_gallery_image_urls(
        string $baseWeb,
        string $absoluteDirectory,
        string $relativeDirectory,
        string $filename
    ): array {
        $fullRelative = trim($relativeDirectory . '/' . $filename, '/');
        $thumbRelative = trim($relativeDirectory . '/thumbnails/' . $filename, '/');
        $thumbAbsolute = $absoluteDirectory . '/thumbnails/' . $filename;

        return [
            'full' => hg_gallery_web_join($baseWeb, $fullRelative),
            'thumb' => is_file($thumbAbsolute)
                ? hg_gallery_web_join($baseWeb, $thumbRelative)
                : hg_gallery_web_join($baseWeb, $fullRelative),
        ];
    }
}

if (!function_exists('hg_gallery_folder_cover')) {
    function hg_gallery_folder_cover(
        string $baseWeb,
        string $absoluteDirectory,
        string $relativeDirectory,
        ?array $allowed = null
    ): string {
        $images = hg_gallery_list_images($absoluteDirectory, $allowed);
        if (!$images) {
            return '';
        }
        $urls = hg_gallery_image_urls($baseWeb, $absoluteDirectory, $relativeDirectory, (string)$images[0]);
        return (string)$urls['thumb'];
    }
}
