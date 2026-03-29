<?php
if (!$link) {
    http_response_code(500);
    exit;
}

if (!function_exists('hg_ci_has_column')) {
    function hg_ci_has_column(mysqli $link, string $table, string $column): bool {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') return false;
        $rs = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        if (!$rs) return false;
        $ok = (mysqli_num_rows($rs) > 0);
        mysqli_free_result($rs);
        return $ok;
    }
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
            'heavens-gate' => '/img/og/og_image_bio.jpg',
            'javi' => '/img/og/og_image.jpg',
            'werewolf-gt' => '/img/og/og_image_temp.jpg',
            'hg-tercer-ojo' => '/img/og/og_image_power.jpg',
            'hg-babylon' => '/img/og/og_image_monster.jpg',
            'hg-london' => '/img/og/og_image_temp.jpg',
            'cenizas' => '/img/og/og_image_power.jpg',
        ];
        if ($prettyId !== '' && isset($map[$prettyId])) return $map[$prettyId];
        return '/img/og/og_image_bio.jpg';
    }
}

$rawChronicle = isset($_GET['t']) ? (string)$_GET['t'] : '';
$chronicleId = 0;
if ($rawChronicle !== '') {
    if (preg_match('/^\d+$/', $rawChronicle)) {
        $chronicleId = (int)$rawChronicle;
    } else {
        $chronicleId = (int)resolve_pretty_id($link, 'dim_chronicles', $rawChronicle);
    }
}

$target = '/img/og/og_image_bio.jpg';
if ($chronicleId > 0) {
    $hasChronicleImage = hg_ci_has_column($link, 'dim_chronicles', 'image_url');
    $selectImage = $hasChronicleImage ? ", COALESCE(image_url, '') AS image_url" : ", '' AS image_url";
    if ($stmt = $link->prepare("SELECT pretty_id $selectImage FROM dim_chronicles WHERE id = ? LIMIT 1")) {
        $stmt->bind_param('i', $chronicleId);
        $stmt->execute();
        $rs = $stmt->get_result();
        if ($rs && ($row = $rs->fetch_assoc())) {
            $prettyId = (string)($row['pretty_id'] ?? '');
            $imageUrl = hg_ci_normalize_public_path((string)($row['image_url'] ?? ''));
            $target = $imageUrl !== '' ? $imageUrl : hg_ci_default_image($prettyId);
        }
        $stmt->close();
    }
}

header('Cache-Control: public, max-age=3600');
header('Location: ' . $target, true, 302);
exit;
