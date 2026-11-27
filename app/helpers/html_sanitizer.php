<?php
declare(strict_types=1);

/**
 * Candidate policy v1. Not wired into production controllers until corpus
 * regression and dependency deployment have been validated.
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
