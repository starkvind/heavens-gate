<?php
if (!function_exists('hg_render_owner_tabs_styles')) {
    function hg_render_owner_tabs_styles(bool $alignTabsRight = true, int $ownersOffsetPx = 18): void {
        // Kept for backwards compatibility.
        // CSS moved to assets/css (hg-core.css, hg-bio.css, hg-docs.css).
        unset($alignTabsRight, $ownersOffsetPx);
    }
}
