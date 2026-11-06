<?php
// Regression for PHP multipart limits: PHP may clear $_POST and $_FILES
// when CONTENT_LENGTH > post_max_size. The admin must return JSON 413.
$source = file_get_contents(__DIR__ . '/../../app/controllers/admin/admin_gallery.php');
if (!is_string($source)) throw new RuntimeException('Gallery source unreadable');
$start = strpos($source, 'function galleryPhpIniBytes(');
$end = strpos($source, 'function fsPathJoin(', $start);
if ($start === false || $end === false) throw new RuntimeException('Size converter missing');
eval(substr($source, $start, $end - $start));
foreach (['8M' => 8388608, '1K' => 1024, '512' => 512, '0' => 0, 'bad' => 0] as $value => $expected) {
    if (galleryPhpIniBytes((string)$value) !== $expected) {
        throw new RuntimeException('Incorrect php.ini size conversion for ' . $value);
    }
}
foreach ([
    "((string)(\$_GET['ajax'] ?? '') === '1')",
    "'X-Requested-With', 'XMLHttpRequest'",
    "hg_admin_json_response(\$resp, \$resp['ok'] ? 200 : 400)",
    "hg_admin_json_error(",
    "UPLOAD_ERR_INI_SIZE",
    "is_writable(\$postAbs)",
    "createThumbnail(\$destAbs, \$thumbAbs, 200, 200)",
] as $required) {
    if (!str_contains($source, $required)) throw new RuntimeException('Missing Gallery upload invariant: ' . $required);
}
if (!preg_match('/if \(\$isAjaxPost && \$postMaxBytes > 0 && \$contentLength > \$postMaxBytes\) \{\s*hg_admin_json_error\(/', $source)) {
    throw new RuntimeException('Oversized multipart must return JSON before CSRF check');
}
echo "Admin Gallery upload PHP limits and failure diagnostics PASS\n";
