<?php
/**
 * Ordered public-page style registry.
 *
 * Phase 1 of the CSS refactor uses this helper in two ways:
 * - migrated controllers register styles explicitly before <head> is rendered;
 * - legacy controllers are temporarily supported by extracting their emitted
 *   stylesheet/style tags from buffered page content and moving those tags to
 *   the document head without changing their relative order.
 *
 * Bare/self-contained responses must bypass the compatibility collector.
 */

if (!function_exists('hg_page_assets_registry')) {
    function &hg_page_assets_registry(): array
    {
        if (!isset($GLOBALS['hg_page_assets']) || !is_array($GLOBALS['hg_page_assets'])) {
            $GLOBALS['hg_page_assets'] = [
                'styles' => [],
                'seen_styles' => [],
            ];
        }

        return $GLOBALS['hg_page_assets'];
    }
}

if (!function_exists('hg_page_asset_versioned_href')) {
    function hg_page_asset_versioned_href(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return $href;
        }

        // External assets and protocol-relative URLs keep their provider URL.
        if (preg_match('~^(?:https?:)?//~i', $href)) {
            return $href;
        }

        $parts = @parse_url($href);
        $path = is_array($parts) ? (string)($parts['path'] ?? '') : '';
        if ($path === '') {
            return $href;
        }

        $normalizedPath = '/' . ltrim($path, '/');
        if (strpos($normalizedPath, '/assets/') !== 0) {
            return $href;
        }

        // Do not replace an explicitly supplied version/cache key.
        if (preg_match('~(?:^|[?&])v=~', $href)) {
            return $href;
        }

        $projectRoot = dirname(__DIR__, 2);
        $filePath = $projectRoot . $normalizedPath;
        $mtime = @filemtime($filePath);
        if ($mtime === false) {
            return $href;
        }

        $separator = (strpos($href, '?') === false) ? '?' : '&';
        return $href . $separator . 'v=' . (int)$mtime;
    }
}

if (!function_exists('hg_page_asset_attributes')) {
    function hg_page_asset_attributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $name => $value) {
            $name = trim((string)$name);
            if ($name === '' || !preg_match('/^[A-Za-z_:][-A-Za-z0-9_:.]*$/', $name)) {
                continue;
            }

            if ($value === false || $value === null) {
                continue;
            }

            if ($value === true) {
                $parts[] = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }

            $parts[] = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '="'
                . htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '"';
        }

        return $parts ? ' ' . implode(' ', $parts) : '';
    }
}

if (!function_exists('hg_page_register_style_tag')) {
    function hg_page_register_style_tag(string $tag): void
    {
        $tag = trim($tag);
        if ($tag === '') {
            return;
        }

        $registry =& hg_page_assets_registry();
        $fingerprint = hash('sha256', $tag);
        if (isset($registry['seen_styles'][$fingerprint])) {
            return;
        }

        $registry['seen_styles'][$fingerprint] = true;
        $registry['styles'][] = $tag;
    }
}

if (!function_exists('hg_page_register_stylesheet')) {
    function hg_page_register_stylesheet(string $href, array $attributes = []): void
    {
        $href = hg_page_asset_versioned_href($href);
        if ($href === '') {
            return;
        }

        $attributes = ['rel' => 'stylesheet', 'href' => $href] + $attributes;
        hg_page_register_style_tag('<link' . hg_page_asset_attributes($attributes) . '>');
    }
}

if (!function_exists('hg_page_register_inline_style')) {
    function hg_page_register_inline_style(string $css, array $attributes = []): void
    {
        if (trim($css) === '') {
            return;
        }

        hg_page_register_style_tag(
            '<style' . hg_page_asset_attributes($attributes) . '>' . "\n" . $css . "\n" . '</style>'
        );
    }
}

if (!function_exists('hg_page_collect_legacy_styles')) {
    function hg_page_collect_legacy_styles(string $html): string
    {
        // Compatibility bridge only. This deliberately preserves each legacy
        // tag verbatim and in encounter order so Phase 1 can migrate emitters
        // incrementally without changing their cascade order.
        $pattern = '~'
            . '<link\b(?=[^>]*\brel\s*=\s*(?:"[^"]*\bstylesheet\b[^"]*"|\'[^\']*\bstylesheet\b[^\']*\'|stylesheet\b))[^>]*>'
            . '|<style\b[^>]*>.*?</style\s*>'
            . '~is';

        $result = preg_replace_callback(
            $pattern,
            static function (array $match): string {
                hg_page_register_style_tag((string)$match[0]);
                return '';
            },
            $html
        );

        return is_string($result) ? $result : $html;
    }
}

if (!function_exists('hg_page_render_registered_styles')) {
    function hg_page_render_registered_styles(): void
    {
        $registry =& hg_page_assets_registry();
        foreach ($registry['styles'] as $tag) {
            echo "\t" . $tag . "\n";
        }
    }
}
