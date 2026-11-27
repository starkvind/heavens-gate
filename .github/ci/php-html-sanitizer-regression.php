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
check_rich_html(hg_render_rich_html($rich) === $clean, 'Public render diverged');
check_rich_html(hg_render_rich_html(str_repeat('x', 2 * 1024 * 1024 + 1)) ===
    '<p>Contenido temporalmente no disponible.</p>', 'Render failure did not close safely');
$root = dirname(__DIR__, 2);
foreach (['app/controllers/docs/docs_page.php', 'app/mobile/controllers/documents.php', 'app/controllers/main/main_news.php', 'app/mobile/controllers/news.php', 'app/controllers/chapters/chapter_page.php', 'app/mobile/controllers/chapter_detail.php'] as $path) {
    check_rich_html(str_contains(file_get_contents($root . '/' . $path), 'hg_render_rich_html('), 'Missing public guard: ' . $path);
}
$admin = file_get_contents($root . '/app/controllers/admin/admin_docs.php');
check_rich_html(str_contains($admin, 'hg_prepare_rich_html($vals[\'content\'],'), 'Missing final write guard');
check_rich_html(str_contains($admin, "if (\$ajaxMode === 'content')"), 'Missing per-document read');
check_rich_html(str_contains($admin, 'row.content = data.content;'), 'Missing safe editor source');
$lookup = static fn(string $type, string $value) => $value === '123'
    ? ['id'=>123, 'type'=>$type, 'href'=>'/characters/example', 'label'=>'Á & <B>'] : null;
$mentionInput = '<p>Antes [@character:123] y [@character:999].</p>'
    . '<a href="/test" title="[@character:123]">[@character:123]</a>'
    . '<pre>[@character:123]</pre><code>[@character:123]</code>';
$mentionOutput = hg_prepare_rich_html($mentionInput, $lookup);
check_rich_html(substr_count($mentionOutput, 'class="hg-mention"') === 1, 'Converted outside text context');
check_rich_html(str_contains($mentionOutput, 'Á &amp; &lt;B&gt;'), 'Unsafe or lost label');
check_rich_html(str_contains($mentionOutput, '[@character:999]'), 'Unknown token lost');
check_rich_html(str_contains($mentionOutput, 'title="[@character:123]"'), 'Attribute token changed');
check_rich_html(hg_prepare_rich_html($mentionOutput, $lookup) === $mentionOutput, 'Mention conversion not idempotent');
$news = file_get_contents($root . '/app/controllers/admin/admin_news.php');
check_rich_html(str_contains($news, 'hg_prepare_rich_html('), 'Missing news write guard');
check_rich_html(str_contains($news, 'hg_news_admin_content('), 'Missing news read guard');
$chapters = file_get_contents($root . '/app/controllers/admin/admin_chapters.php');
check_rich_html(substr_count($chapters, 'hg_prepare_rich_html(') === 2, 'Missing chapter write guard');
check_rich_html(str_contains($chapters, 'hg_sanitize_rich_html((string)($row[' . "'synopsis'" . ']'), 'Missing chapter editor guard');
foreach (['items', 'systems', 'forms'] as $section) {
    $source = file_get_contents($root . '/app/controllers/admin/admin_' . $section . '.php');
    check_rich_html(str_contains($source, 'hg_prepare_rich_html('), 'Missing rich write guard: ' . $section);
    check_rich_html(str_contains($source, 'hg_sanitize_rich_html($raw)'), 'Missing rich editor guard: ' . $section);
    check_rich_html(str_contains($source, "searchParams.set('id', String(id))"), 'Missing selected record request: ' . $section);
}
foreach (['app/controllers/docs/item_page.php', 'app/mobile/controllers/inventory.php',
    'app/controllers/systems/system_overview_page.php', 'app/controllers/systems/system_form_page.php',
    'app/mobile/controllers/systems.php'] as $path) {
    check_rich_html(str_contains(file_get_contents($root . '/' . $path), 'hg_render_rich_html('), 'Missing rich render guard: ' . $path);
}
echo "HTML sanitizer candidate regression: OK\n";
