<?php
declare(strict_types=1);

/**
 * Candidate policy v1. Integrated in administrative rich HTML; not for deployment
 * until corpus regression and dependency deployment have been validated.
 *
 * Fail closed: callers must reject a write on exception, never persist raw input.
 * A private writable cache directory may be supplied by deployment configuration.
 */
function hg_sanitize_rich_html(string $html, ?string $cacheDirectory = null): string
{
    if (strlen($html) > 2 * 1024 * 1024) {
        throw new LengthException('Rich HTML exceeds candidate size limit');
    }
    $autoload = __DIR__ . '/../security/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('HTML sanitizer dependency is not installed');
    }
    require_once $autoload;

    static $purifier = null;
    static $configuredCache = null;
    if ($purifier === null || $configuredCache !== $cacheDirectory) {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.DefinitionID', 'heavens-gate-rich-html');
        $config->set('HTML.DefinitionRev', 1);
        if ($cacheDirectory === null) {
            $config->set('Cache.DefinitionImpl', null);
        } else {
            if (!is_dir($cacheDirectory) || !is_writable($cacheDirectory)) {
                throw new RuntimeException('HTML sanitizer cache directory unavailable');
            }
            $config->set('Cache.SerializerPath', $cacheDirectory);
        }

        $classes = ['hg-mention', 'ql-align-center', 'ql-align-right',
            'ql-align-justify', 'ql-direction-rtl', 'ql-size-small',
            'ql-size-large', 'ql-size-huge', 'ql-font-serif',
            'ql-font-monospace', 'ql-syntax'];
        for ($i = 1; $i <= 8; $i++) {
            $classes[] = 'ql-indent-' . $i;
        }
        $config->set('Attr.AllowedClasses', $classes);
        $config->set('Attr.AllowedFrameTargets', ['_blank', '_self']);
        $config->set('Attr.AllowedRel', ['noopener', 'noreferrer', 'nofollow']);
        $config->set('HTML.TargetNoopener', true);
        $config->set('HTML.TargetNoreferrer', true);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true,
            'mailto' => true, 'tel' => true]);
        $config->set('CSS.AllowedProperties', ['color', 'background-color',
            'text-align', 'font-size', 'font-weight', 'font-style', 'font-family',
            'text-decoration', 'vertical-align', 'border', 'border-width',
            'border-style', 'border-color', 'border-collapse', 'width',
            'height', 'padding', 'padding-left', 'padding-right']);
        $config->set('HTML.Allowed', implode(',', [
            'p[class|style]', 'br', 'div[class|style]', 'span[class|style]',
            'h1[class|style]', 'h2[class|style]', 'h3[class|style]',
            'h4[class|style]', 'h5[class|style]', 'h6[class|style]',
            'strong', 'em', 'b', 'i', 'u', 's', 'strike', 'del',
            'blockquote[class|style]', 'hr', 'pre[class|style]', 'code',
            'sub', 'sup', 'ul[class|style]', 'ol[class|style]', 'li[class|style]',
            'table[class|style|border|cellpadding|cellspacing|width]',
            'caption[class|style]', 'thead[class|style]', 'tbody[class|style]',
            'tfoot[class|style]', 'tr[class|style]',
            'th[class|style|colspan|rowspan|scope]', 'td[class|style|colspan|rowspan]',
            'colgroup[span|style]', 'col[span|style]',
            'a[href|title|class|target|rel|data-type|data-id]',
            'img[src|alt|title|width|height|class|style]'
        ]));
        if ($definition = $config->maybeGetRawHTMLDefinition()) {
            $definition->addAttribute('a', 'data-type', new HTMLPurifier_AttrDef_Enum([
                'character', 'season', 'episode', 'organization', 'group',
                'gift', 'rite', 'totem', 'discipline', 'item', 'trait',
                'background', 'merit', 'flaw', 'merydef', 'doc',
                'system', 'breed', 'auspice', 'tribe'
            ]));
            $definition->addAttribute('a', 'data-id',
                new HTMLPurifier_AttrDef_Integer(false, false, true));
        }
        $purifier = new HTMLPurifier($config);
        $configuredCache = $cacheDirectory;
    }
    return $purifier->purify($html);
}

/** Legacy display: never return unvalidated HTML on failure. */
function hg_render_rich_html(string $html): string
{
    try {
        return hg_sanitize_rich_html($html);
    } catch (Throwable $e) {
        error_log('HG rich HTML unavailable: ' . get_class($e));
        return '<p>Contenido temporalmente no disponible.</p>';
    }
}

/** Resolve mention tokens only in text nodes of already-sanitized HTML. */
function hg_prepare_rich_html(string $html, callable $lookup): string
{
    $clean = hg_sanitize_rich_html($html);
    if (!str_contains($clean, '[@')) {
        return $clean;
    }
    if (!class_exists(DOMDocument::class)) {
        throw new RuntimeException('DOM extension unavailable');
    }
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try {
        if (!$dom->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
            . $clean . '</body></html>', LIBXML_NONET)) {
            throw new RuntimeException('Could not parse rich HTML');
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body instanceof DOMElement) {
        throw new RuntimeException('Missing rich HTML body');
    }
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('.//text()[not(ancestor::a or ancestor::code or ancestor::pre)]', $body);
    if ($nodes === false) {
        throw new RuntimeException('Could not locate text nodes');
    }
    $changed = false;
    foreach (iterator_to_array($nodes) as $node) {
        $text = $node->nodeValue;
        if (!preg_match_all('/\[@([a-z_]+):([^\]\s]+)\]/i', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            continue;
        }
        $fragment = $dom->createDocumentFragment();
        $cursor = 0;
        $resolved = false;
        foreach ($matches as $match) {
            [$token, $offset] = $match[0];
            $type = strtolower($match[1][0]);
            $info = $lookup($type, $match[2][0]);
            if (!$info || (int)($info['id'] ?? 0) <= 0) {
                continue;
            }
            $fragment->appendChild($dom->createTextNode(substr($text, $cursor, $offset - $cursor)));
            $a = $dom->createElement('a');
            $a->setAttribute('href', (string)$info['href']);
            $a->setAttribute('class', 'hg-mention');
            $a->setAttribute('data-type', (string)($info['type'] ?? $type));
            $a->setAttribute('data-id', (string)(int)$info['id']);
            $a->setAttribute('target', '_blank');
            $a->setAttribute('rel', 'noopener noreferrer');
            $a->appendChild($dom->createTextNode((string)$info['label']));
            $fragment->appendChild($a);
            $cursor = $offset + strlen($token);
            $resolved = true;
        }
        if ($resolved) {
            $fragment->appendChild($dom->createTextNode(substr($text, $cursor)));
            $node->parentNode->replaceChild($fragment, $node);
            $changed = true;
        }
    }
    if (!$changed) {
        return $clean;
    }
    $result = '';
    foreach ($body->childNodes as $child) {
        $part = $dom->saveHTML($child);
        if ($part === false) {
            throw new RuntimeException('Could not serialize rich HTML');
        }
        $result .= $part;
    }
    return hg_sanitize_rich_html($result);
}

/** Temporary loss-prevention gate for Quill 1.3.7; not a security filter. */
function hg_quill_editor_html(string $html): string
{
    $clean = hg_sanitize_rich_html($html);
    // Deliberately conservative: block even an unsupported historical table
    // that the sanitizer may have removed. Never silently open a partial copy.
    foreach ([$html, $clean] as $candidate) {
        if (preg_match('/<\s*table\b/i', $candidate) === 1) {
            throw new DomainException('Quill table round-trip is not supported');
        }
    }
    return $clean;
}
