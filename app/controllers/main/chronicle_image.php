<?php
require_once(__DIR__ . '/../../domains/chronicles/queries.php');

if (!$link) {
    http_response_code(500);
    exit;
}

if (!function_exists('hg_ci_normalize_public_path')) {
    function hg_ci_normalize_public_path(string $path): string {
        $path = trim($path);
        if ($path === '') return '';
        if (preg_match('#^https?://#i', $path)) return $path;
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^/?public/#i', '', $path);
        $path = preg_replace('#/+#', '/', $path);
        return '/' . ltrim($path, '/');
    }
}
if (!function_exists('hg_ci_default_image')) {
    function hg_ci_default_image(string $prettyId = ''): string {
        static $map = [
            'heavens-gate' => '/img/og/og_image_bio.webp',
            'javi' => '/img/og/og_image.webp',
            'werewolf-gt' => '/img/og/og_image_temp.webp',
            'hg-tercer-ojo' => '/img/og/og_image_power.webp',
            'hg-babylon' => '/img/og/og_image_monster.webp',
            'hg-london' => '/img/og/og_image_temp.webp',
            'cenizas' => '/img/og/og_image_power.webp',
        ];
        if ($prettyId !== '' && isset($map[$prettyId])) return $map[$prettyId];
        return '/img/og/og_image_bio.webp';
    }
}

$rawChronicle = hg_request_param($hgRequest, 'chronicle');
$chronicleId = $rawChronicle !== '' ? hg_chronicles_resolve_id($link, $rawChronicle) : 0;

$target = '/img/og/og_image_bio.webp';
if ($chronicleId > 0) {
    $row = hg_chronicles_fetch_image_row($link, $chronicleId);
    if ($row) {
        $prettyId = (string)($row['pretty_id'] ?? '');
        $imageUrl = hg_ci_normalize_public_path((string)($row['image_url'] ?? ''));
        $target = $imageUrl !== '' ? $imageUrl : hg_ci_default_image($prettyId);
    }
}

header('Cache-Control: public, max-age=3600');
header('Location: ' . $target, true, 302);
exit;
