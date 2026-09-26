<?php

/**
 * Pure canonical-path normalization.
 */
function hg_request_path_normalize(string $path): string
{
    $path = rawurldecode($path);
    $path = preg_replace('#/+#', '/', $path) ?? $path;

    if ($path === '') {
        return '/';
    }

    if ($path !== '/' && substr($path, -1) === '/') {
        $path = rtrim($path, '/');
    }

    return $path === '' ? '/' : $path;
}
