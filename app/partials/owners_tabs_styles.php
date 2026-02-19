<?php
if (!function_exists('hg_render_owner_tabs_styles')) {
    function hg_render_owner_tabs_styles(bool $alignTabsRight = true, int $ownersOffsetPx = 18): void {
        $tabsJustify = $alignTabsRight ? 'flex-end' : 'flex-start';
        $offset = max(0, $ownersOffsetPx);
        echo "<style>
            .hg-tabs{ display:flex; gap:8px; flex-wrap:wrap; margin:6px 0 12px; justify-content:{$tabsJustify}; }
            .hg-tab-panel{ display:none; }
            .hg-tab-panel.active{ display:block; }
            .hgTabBtn{ border:1px solid #003399; }
            .hgTabBtn.active{ background:#001199; color:#01b3fa; border-color:#003399; }
            .hg-tab-panel[data-tab='owners'] .grupoBioClan{ display:flex; justify-content:flex-start !important; }
            .hg-tab-panel[data-tab='owners'] .contenidoAfiliacion{
                display:flex;
                flex-wrap:wrap;
                gap:6px;
                padding:8px 0 12px 0 !important;
                margin-left:{$offset}px !important;
                justify-content:flex-start !important;
            }
        </style>";
    }
}
