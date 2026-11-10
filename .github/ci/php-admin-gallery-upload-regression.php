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
// The PHP-FPM-compatible .user.ini configuration accepts 3 MiB uploads.
$phpFpmConfig = file_get_contents(__DIR__ . '/../../.user.ini');
if (!is_string($phpFpmConfig)) throw new RuntimeException('PHP-FPM config unreadable');
if (!preg_match('/^upload_max_filesize\\s*=\\s*3M\\s*$/m', $phpFpmConfig)) {
    throw new RuntimeException('PHP-FPM upload_max_filesize must be 3M');
}
if (!preg_match('/^post_max_size\\s*=\\s*4M\\s*$/m', $phpFpmConfig)) {
    throw new RuntimeException('PHP-FPM post_max_size must be 4M');
}
$apacheConfig = file_get_contents(__DIR__ . '/../../.htaccess');
if (!is_string($apacheConfig)) throw new RuntimeException('Apache config unreadable');
if (preg_match('/^\\s*php_(?:value|flag)\\b/m', $apacheConfig)) {
    throw new RuntimeException('Apache php_value/php_flag directives break PHP-FPM');
}
if (!preg_match('/function galleryValidateUpload\\(array \\$file, int \\$maxBytes = (\\d+)/', $source, $fileLimit) || (int)$fileLimit[1] < 3 * 1024 * 1024) {
    throw new RuntimeException('Gallery application limit must not reject 3 MiB uploads');
}

echo "Admin Gallery upload PHP limits and failure diagnostics PASS\n";
