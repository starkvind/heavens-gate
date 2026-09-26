<?php

require_once __DIR__ . '/../domains/navigation/queries.php';

if (!function_exists('hg_mobile_menu_h')) {
    function hg_mobile_menu_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_menu_is_admin_href')) {
    function hg_mobile_menu_is_admin_href(string $href): bool
    {
        $path = strtolower((string)(parse_url($href, PHP_URL_PATH) ?? ''));
        $query = [];
        parse_str((string)(parse_url($href, PHP_URL_QUERY) ?? ''), $query);
        $route = strtolower((string)($query['p'] ?? ''));

        return $path === '/talim'
            || $path === '/admin'
            || strpos($path, '/admin/') === 0
            || $route === 'talim';
    }
}

if (!function_exists('hg_mobile_menu_is_desktop_only_href')) {
    function hg_mobile_menu_is_desktop_only_href(string $href): bool
    {
        $path = strtolower((string)(parse_url($href, PHP_URL_PATH) ?? ''));
        $query = [];
        parse_str((string)(parse_url($href, PHP_URL_QUERY) ?? ''), $query);
        $route = strtolower((string)($query['p'] ?? ''));

        $desktopOnlyPaths = [
            '/seasons/order',
            '/relationship-map/organizations',
            '/relationship-map/characters',
            '/relationship-map/groups',
            '/organizations/org-chart',
        ];
        $desktopOnlyRoutes = [
            'season_order',
            'nebula_clan',
            'nebula_character',
            'nebula_groups',
            'org_chart',
        ];

        if (in_array($path, $desktopOnlyPaths, true)) {
            return true;
        }
        if (strpos($path, '/relationship-map/') === 0) {
            return true;
        }
        if (preg_match('#^/organizations/[^/]+/org-chart$#', $path)) {
            return true;
        }
        return in_array($route, $desktopOnlyRoutes, true);
    }
}

if (!function_exists('hg_mobile_menu_from_db')) {
    function hg_mobile_menu_from_db(mysqli $link): array
    {
        $groups = [];

        foreach (hg_navigation_fetch_parents($link) as $parent) {
            $label = trim((string)($parent['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $items = [];
            foreach (hg_navigation_fetch_children($link, (int)($parent['id'] ?? 0)) as $child) {
                $type = (string)($child['item_type'] ?? 'static');

                if ($type === 'separator') {
                    continue;
                }

                if ($type === 'dynamic') {
                    $source = (string)($child['dynamic_source'] ?? '');
                    if ($source === 'seasons_0') {
                        $items = array_merge($items, hg_navigation_season_items($link, false));
                    } elseif ($source === 'seasons_1') {
                        $items = array_merge($items, hg_navigation_season_items($link, true));
                    }
                    continue;
                }

                $href = (string)($child['href'] ?? '#');
                if ($href === ''
                    || $href === '#'
                    || hg_mobile_menu_is_admin_href($href)
                    || hg_mobile_menu_is_desktop_only_href($href)) {
                    continue;
                }

                $items[] = [
                    'label' => (string)($child['label'] ?? ''),
                    'href' => $href,
                    'target' => (string)($child['target'] ?? '_self'),
                ];
            }

            if ($items) {
                $groups[] = [
                    'label' => $label,
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }
}

if (!function_exists('hg_mobile_menu_fallback')) {
    function hg_mobile_menu_fallback(): array
    {
        return [
            ['label' => 'Inicio', 'items' => [
                ['label' => 'Inicio', 'href' => '/home', 'target' => '_self'],
                ['label' => 'Noticias', 'href' => '/news', 'target' => '_self'],
                ['label' => 'Buscar', 'href' => '/search', 'target' => '_self'],
                ['label' => 'Estado', 'href' => '/status', 'target' => '_self'],
            ]],
            ['label' => 'Archivo', 'items' => [
                ['label' => 'Personajes', 'href' => '/characters', 'target' => '_self'],
                ['label' => 'Jugadores', 'href' => '/players', 'target' => '_self'],
                ['label' => 'Temporadas', 'href' => '/seasons', 'target' => '_self'],
                ['label' => 'Crónicas', 'href' => '/chronicles', 'target' => '_self'],
                ['label' => 'Documentos', 'href' => '/documents', 'target' => '_self'],
                ['label' => 'Inventario', 'href' => '/inventory', 'target' => '_self'],
                ['label' => 'Sistemas', 'href' => '/systems', 'target' => '_self'],
                ['label' => 'Reglas', 'href' => '/rules', 'target' => '_self'],
                ['label' => 'Poderes', 'href' => '/powers', 'target' => '_self'],
                ['label' => 'Mapas', 'href' => '/maps', 'target' => '_self'],
                ['label' => 'Banda sonora', 'href' => '/music', 'target' => '_self'],
                ['label' => 'Galería', 'href' => '/gallery', 'target' => '_self'],
            ]],
            ['label' => 'Juegos y herramientas', 'items' => [
                ['label' => 'Mensajes foro', 'href' => '/tools/forum-avatar', 'target' => '_self'],
                ['label' => 'Lector foro', 'href' => '/tools/forum-topic-viewer', 'target' => '_self'],
                ['label' => 'Generador Garou', 'href' => '/tools/garou-name-generator', 'target' => '_self'],
                ['label' => 'Tiradados', 'href' => '/tools/dice', 'target' => '_self'],
                ['label' => 'Tablón CSP', 'href' => '/tools/csp', 'target' => '_self'],
            ]],
        ];
    }
}

if (!function_exists('hg_mobile_menu_ensure_tool_links')) {
    function hg_mobile_menu_ensure_tool_links(array $groups): array
    {
        $required = [
            ['label' => 'Mensajes para foro', 'href' => '/tools/forum-avatar', 'target' => '_self'],
            ['label' => 'Lector del foro', 'href' => '/tools/forum-topic-viewer', 'target' => '_self'],
            ['label' => 'Generador Garou', 'href' => '/tools/garou-name-generator', 'target' => '_self'],
        ];

        $existing = [];
        foreach ($groups as $group) {
            foreach (($group['items'] ?? []) as $item) {
                $href = trim((string)($item['href'] ?? ''));
                if ($href === '') {
                    continue;
                }

                $path = rtrim((string)(parse_url($href, PHP_URL_PATH) ?? ''), '/');
                $query = [];
                parse_str((string)(parse_url($href, PHP_URL_QUERY) ?? ''), $query);
                $route = (string)($query['p'] ?? '');

                $existing[$href] = true;
                if ($path !== '') {
                    $existing[$path] = true;
                }
                if ($route === 'forum_avatar_tool') {
                    $existing['/tools/forum-avatar'] = true;
                } elseif ($route === 'forum_topic_viewer') {
                    $existing['/tools/forum-topic-viewer'] = true;
                } elseif ($route === 'garou_name_gen') {
                    $existing['/tools/garou-name-generator'] = true;
                }
            }
        }

        $missing = [];
        foreach ($required as $item) {
            if (empty($existing[$item['href']])) {
                $missing[] = $item;
            }
        }
        if (!$missing) {
            return $groups;
        }

        foreach ($groups as &$group) {
            $label = strtolower((string)($group['label'] ?? ''));
            if (strpos($label, 'herramient') !== false || strpos($label, 'juego') !== false) {
                $group['items'] = array_merge(($group['items'] ?? []), $missing);
                unset($group);
                return $groups;
            }
        }
        unset($group);

        $groups[] = ['label' => 'Juegos y herramientas', 'items' => $missing];
        return $groups;
    }
}

$hgMobileMenuGroups = [];
if (isset($link) && ($link instanceof mysqli)) {
    $hgMobileMenuGroups = hg_mobile_menu_from_db($link);
}
if (!$hgMobileMenuGroups) {
    $hgMobileMenuGroups = hg_mobile_menu_fallback();
}

$hgMobileMenuGroups = hg_mobile_menu_ensure_tool_links($hgMobileMenuGroups);
