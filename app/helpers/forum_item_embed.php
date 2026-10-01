<?php

require_once __DIR__ . '/../domains/inventory/queries.php';

if (!function_exists('hg_forum_item_embed_h')) {
    function hg_forum_item_embed_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hg_forum_item_embed_image_url')) {
    function hg_forum_item_embed_image_url($raw): string
    {
        $url = trim((string)$raw);
        if ($url === '') {
            return '/img/inv/no-photo.webp';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return '/' . ltrim($url, '/');
    }
}

if (!function_exists('hg_forum_item_embed_description')) {
    function hg_forum_item_embed_description($raw): string
    {
        $text = (string)$raw;
        $text = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $text);
        $text = preg_replace('#</\s*p\s*>#i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string)preg_replace("/\n{3,}/", "\n\n", $text));
        return nl2br(hg_forum_item_embed_h($text), false);
    }
}

if (!function_exists('hg_forum_item_embed_damage_text')) {
    function hg_forum_item_embed_damage_text(array $item): string
    {
        $damageType = trim(strtolower((string)($item['damage_type'] ?? '')));
        if ($damageType === '') {
            return '';
        }

        $skill = trim((string)($item['skill_name'] ?? ''));
        $bonus = (int)($item['bonus'] ?? 0);
        $base = in_array($skill, ['Cuerpo a Cuerpo', 'Pelea', 'Arrojar'], true)
            ? 'Fuerza + ' . $bonus
            : $bonus . ' dados';

        $metal = (int)($item['metal'] ?? 0);
        $metalText = $metal === 1 ? ' y de plata' : ($metal === 2 ? ' y de oro' : '');
        return $base . ', ' . $damageType . $metalText;
    }
}

if (!function_exists('hg_forum_render_item_embed')) {
    function hg_forum_render_item_embed(mysqli $link, int $itemId): string
    {
        static $cache = [];
        if ($itemId <= 0) {
            return '<div class="inline-error">Objeto inválido.</div>';
        }
        if (isset($cache[$itemId])) {
            return $cache[$itemId];
        }

        $item = hg_inventory_fetch_item($link, $itemId);
        if (!$item) {
            return '<div class="inline-error">Objeto #' . $itemId . ' no encontrado.</div>';
        }

        $name = trim((string)($item['name'] ?? 'Objeto'));
        $prettyId = trim((string)($item['pretty_id'] ?? ''));
        $href = '/inventory/items/' . rawurlencode($prettyId !== '' ? $prettyId : (string)$itemId);
        $imageUrl = hg_forum_item_embed_image_url($item['image_url'] ?? '');
        $type = trim((string)($item['item_type_name'] ?? ''));
        $skill = trim((string)($item['skill_name'] ?? ''));
        $origin = trim((string)($item['bibliography_name'] ?? ''));
        $description = hg_forum_item_embed_description($item['description'] ?? '');
        $damage = hg_forum_item_embed_damage_text($item);
        $bonus = (int)($item['bonus'] ?? 0);
        $level = (int)($item['level'] ?? 0);
        $gnosis = (int)($item['gnosis'] ?? 0);
        $strength = (int)($item['strength_req'] ?? 0);
        $dexterity = (int)($item['dexterity_req'] ?? 0);

        $stats = [];
        if ($type !== '') $stats[] = ['Tipo', $type];
        if ($skill !== '') $stats[] = ['Habilidad', $skill];
        if ($damage !== '') $stats[] = ['Daño', $damage];
        if ($bonus !== 0 && $skill === '') $stats[] = ['Bonificación', ($bonus > 0 ? '+' : '') . $bonus . ' de absorción'];
        if ($level !== 0) $stats[] = ['Nivel', (string)$level];
        if ($gnosis !== 0) $stats[] = ['Gnosis', (string)$gnosis];
        if ($strength !== 0) $stats[] = ['Requiere', 'Fuerza ' . $strength . ' mínimo'];
        if ($dexterity !== 0) $stats[] = ['Penalización', 'Destreza -' . $dexterity];
        if ($origin !== '') $stats[] = ['Origen', $origin];

        $html = '<article class="hg-inline-item">';
        $html .= '<a class="hg-inline-item__media" href="' . hg_forum_item_embed_h($href) . '" target="_blank" rel="noopener noreferrer">';
        $html .= '<img class="hg-inline-item__image" src="' . hg_forum_item_embed_h($imageUrl) . '" alt="' . hg_forum_item_embed_h($name) . '">';
        $html .= '</a>';
        $html .= '<div class="hg-inline-item__body">';
        $html .= '<div class="hg-inline-item__head">';
        $html .= '<a class="hg-inline-item__title" href="' . hg_forum_item_embed_h($href) . '" target="_blank" rel="noopener noreferrer">' . hg_forum_item_embed_h($name) . '</a>';
        $html .= '<span class="hg-inline-item__id">#' . $itemId . '</span>';
        $html .= '</div>';

        if ($stats) {
            $html .= '<dl class="hg-inline-item__stats">';
            foreach ($stats as [$label, $value]) {
                $html .= '<div><dt>' . hg_forum_item_embed_h($label) . '</dt><dd>' . hg_forum_item_embed_h($value) . '</dd></div>';
            }
            $html .= '</dl>';
        }

        if ($description !== '') {
            $html .= '<div class="hg-inline-item__description">' . $description . '</div>';
        }
        $html .= '</div></article>';

        $cache[$itemId] = $html;
        return $html;
    }
}

if (!function_exists('hg_forum_expand_item_bbcode')) {
    function hg_forum_expand_item_bbcode(mysqli $link, string $html): string
    {
        return (string)preg_replace_callback(
            '/\[hg_item\]\s*(\d+)\s*\[\/hg_item\]/i',
            static function ($matches) use ($link) {
                return hg_forum_render_item_embed($link, (int)$matches[1]);
            },
            $html
        );
    }
}
