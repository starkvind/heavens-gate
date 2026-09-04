<?php
$root = dirname(__DIR__, 2);
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rawurldecode((string)($uri ?: '/'));

if ($path !== '/') {
    $candidate = realpath($root . $path);
    if ($candidate !== false
        && str_starts_with($candidate, $root . DIRECTORY_SEPARATOR)
        && is_file($candidate)) {
        return false;
    }
}

chdir($root);
require $root . '/index.php';
