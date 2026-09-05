<?php

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

        if (in_array($path, $desktopOnlyPaths, true)) return true;
        if (strpos($path, '/relationship-map/') === 0) return true;
        if (preg_match('#^/organizations/[^/]+/org-chart$#', $path)) return true;
        return in_array($route, $desktopOnlyRoutes, true);
    }
}
if (!function_exists('hg_mobile_menu_table_exists')) {
    function hg_mobile_menu_table_exists(mysqli $link): bool
    {
        if ($res = $link->query("SHOW TABLES LIKE 'dim_menu_items'")) {
            $exists = $res->num_rows > 0;
            $res->free();
            return $exists;
        }
        return false;
    }
}

if (!function_exists('hg_mobile_menu_children')) {
    function hg_mobile_menu_children(mysqli $link, int $parentId): array
    {
        $rows = [];
        $sql = "SELECT id, label, href, target, item_type, dynamic_source
                FROM dim_menu_items
                WHERE parent_id = ? AND enabled = 1
                ORDER BY sort_order, id";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, 'i', $parentId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        return $rows;
    }
}

if (!function_exists('hg_mobile_menu_seasons')) {
    function hg_mobile_menu_seasons(mysqli $link, string $seasonFlag): array
    {
        $items = [];
        if ($seasonFlag === '1') {
            $sql = "SELECT id, name, season_number, finished, season_kind
                    FROM dim_seasons
                    WHERE season_kind = 'historia_personal'
                    ORDER BY sort_order, season_number";
        } else {
            $sql = "SELECT id, name, season_number, finished, season_kind
                    FROM dim_seasons
                    WHERE season_kind IN ('temporada','inciso','especial')
                    ORDER BY FIELD(season_kind, 'temporada','inciso','especial'), sort_order, season_number";
        }

        if ($res = $link->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)($row['id'] ?? 0);
                $name = (string)($row['name'] ?? '');
                $number = (int)($row['season_number'] ?? 0);
                $kind = (string)($row['season_kind'] ?? 'temporada');

                if ($kind === 'historia_personal' || $kind === 'especial') {
                    $label = $name;
                } elseif ($kind === 'inciso') {
                    $incisoNum = ($number >= 100 && $number < 200) ? ($number - 100) : $number;
                    $label = 'I' . $incisoNum . ' - ' . $name;
                } else {
                    $label = 'T' . $number . ' - ' . $name;
                }

                $href = function_exists('pretty_url')
                    ? pretty_url($link, 'dim_seasons', '/seasons', $id)
                    : ('/seasons/' . $id);

                $items[] = ['label' => $label, 'href' => $href, 'target' => '_self'];
            }
            $res->free();
        }

        return $items;
    }
}

if (!function_exists('hg_mobile_menu_from_db')) {
    function hg_mobile_menu_from_db(mysqli $link): array
    {
        if (!hg_mobile_menu_table_exists($link)) {
            return [];
        }

        $groups = [];
        $sql = "SELECT id, label, menu_key
                FROM dim_menu_items
                WHERE parent_id IS NULL AND enabled = 1
                ORDER BY sort_order, id";
        $parents = [];
        if ($res = $link->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $parents[] = $row;
            }
            $res->free();
        }

        foreach ($parents as $parent) {
            $label = trim((string)($parent['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $items = [];
            foreach (hg_mobile_menu_children($link, (int)$parent['id']) as $child) {
                $type = (string)($child['item_type'] ?? 'static');
                $href = (string)($child['href'] ?? '#');
                $target = (string)($child['target'] ?? '_self');

                if ($type === 'separator') {
                    continue;
                }

                if ($type === 'dynamic') {
                    $source = (string)($child['dynamic_source'] ?? '');
                    if ($source === 'seasons_0') {
                        $items = array_merge($items, hg_mobile_menu_seasons($link, '0'));
                    } elseif ($source === 'seasons_1') {
                        $items = array_merge($items, hg_mobile_menu_seasons($link, '1'));
                    }
                    continue;
                }

                if ($href === '' || $href === '#' || hg_mobile_menu_is_admin_href($href) || hg_mobile_menu_is_desktop_only_href($href)) {
                    continue;
                }

                $items[] = [
                    'label' => (string)($child['label'] ?? ''),
                    'href' => $href,
                    'target' => $target,
                ];
            }

            if (!empty($items)) {
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
        if (empty($missing)) {
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
if (empty($hgMobileMenuGroups)) {
    $hgMobileMenuGroups = hg_mobile_menu_fallback();
}


$hgMobileMenuGroups = hg_mobile_menu_ensure_tool_links($hgMobileMenuGroups);