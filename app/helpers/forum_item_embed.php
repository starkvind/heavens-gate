<?php

if (!function_exists('hg_forum_render_item_embed')) {
    function hg_forum_render_item_embed(int $itemId): string
    {
        if ($itemId <= 0) {
            return '<div class="inline-error">Objeto inválido.</div>';
        }

        $src = '/forum/item?id=' . rawurlencode((string)$itemId);
        return '<iframe class="hg-inline-item-frame" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" title="Objeto #' . $itemId . '" loading="lazy"></iframe>';
    }
}

if (!function_exists('hg_forum_expand_item_bbcode')) {
    function hg_forum_expand_item_bbcode(string $html): string
    {
        return (string)preg_replace_callback(
            '/(?:<br\s*\/?>\s*)*\[hg_item\]\s*(\d+)\s*\[\/hg_item\](?:\s*<br\s*\/?>)*/i',
            static function ($matches) {
                return hg_forum_render_item_embed((int)$matches[1]);
            },
            $html
        );
    }
}
