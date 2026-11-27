<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/helpers/html_sanitizer.php';

function check_rich_html(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$rich = '<h2>Crónica</h2><p class="ql-align-center"><strong>Texto</strong> '
    . '<em>énfasis</em> <span style="color:#ff0000">color</span></p>'
    . '<a class="hg-mention" href="/characters/ejemplo" data-type="character" data-id="123" target="_blank">Ejemplo</a>'
    . '<table><tbody><tr><th colspan="2">Título</th></tr><tr><td>A</td><td>B</td></tr></tbody></table>'
    . '<ul><li>Uno</li></ul><p><a href="https://example.org/">Enlace</a></p>';
$clean = hg_sanitize_rich_html($rich);
foreach (['<h2>Crónica</h2>', '<strong>Texto</strong>', '<em>énfasis</em>',
    'ql-align-center', 'color:', 'hg-mention', 'href="/characters/ejemplo"',
    'data-type="character"', 'data-id="123"', 'colspan="2"', '<table',
    '<ul>', 'https://example.org/', 'noopener'] as $marker) {
    check_rich_html(str_contains($clean, $marker), 'Lost allowed markup: ' . $marker);
}
check_rich_html(hg_sanitize_rich_html($clean) === $clean, 'Sanitization is not idempotent');

$untrusted = '<p class="unknown-class" onclick="void(0)">Texto</p>'
    . '<script>void(0)</script><iframe src="https://example.org/"></iframe>'
    . '<a href="javascript:void(0)" data-type="unknown" data-id="-1">Enlace</a>'
    . '<span style="position:fixed;color:red">Estilo</span>';
$safe = hg_sanitize_rich_html($untrusted);
foreach (['unknown-class', 'onclick=', '<script', '<iframe', 'javascript:',
    'data-type="unknown"', 'data-id="-1"', 'position:'] as $marker) {
    check_rich_html(!str_contains(strtolower($safe), strtolower($marker)), 'Unexpected markup: ' . $marker);
}
check_rich_html(str_contains($safe, 'Texto'), 'Legitimate text removed');
check_rich_html(hg_sanitize_rich_html('') === '', 'Empty input changed');
try {
    hg_sanitize_rich_html(str_repeat('x', 2 * 1024 * 1024 + 1));
    check_rich_html(false, 'Oversized input accepted');
} catch (LengthException $expected) {
    // Intentional fail-closed size guard.
}
echo "HTML sanitizer candidate regression: OK\n";
