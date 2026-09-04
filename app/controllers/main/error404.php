<?php
http_response_code(404);

if (!function_exists('hg404_h')) {
    function hg404_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hg404_table_exists')) {
    function hg404_table_exists(mysqli $link, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $ok = false;
        if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?")) {
            $st->bind_param('s', $table);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            $ok = ((int)$count > 0);
        }

        $cache[$table] = $ok;
        return $ok;
    }
}

if (!function_exists('hg404_is_sensitive_request')) {
    function hg404_is_sensitive_request(string $path): bool
    {
        $path = ltrim(strtolower($path), '/');
        if ($path === '') {
            return false;
        }

        $patterns = [
            '#^(?:app|admin_upgrade_notes)(?:/|$)#',
            '#^(?:readme|technical_documentation|telegram_bot_backend_guide)\.md$#',
            '#^(?:dump-[^/]+\.sql|config\.env(?:\..*)?)$#',
            '#^\.(?:git|svn|hg)(?:/|$)#',
            '#^\.(?:gitignore|gitattributes|env(?:\..*)?)$#',
            '#^(?:composer\.(?:json|lock)|phpunit\.xml(?:\.dist)?|check_encoding\.ps1)$#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('hg404_seed_int')) {
    function hg404_seed_int(string $seed): int
    {
        return (int)sprintf('%u', crc32($seed));
    }
}

if (!function_exists('hg404_pick_offset')) {
    function hg404_pick_offset(int $total, int $limit, string $seed): int
    {
        if ($total <= $limit) {
            return 0;
        }

        $maxOffset = max(0, $total - $limit);
        if ($maxOffset <= 0) {
            return 0;
        }

        return hg404_seed_int($seed) % ($maxOffset + 1);
    }
}

if (!function_exists('hg404_add_group_item')) {
    function hg404_add_group_item(array &$groups, string $group, string $title, string $href, string $meta = ''): void
    {
        if (!isset($groups[$group])) {
            $groups[$group] = [];
        }

        foreach ($groups[$group] as $item) {
            if (($item['href'] ?? '') === $href) {
                return;
            }
        }

        $groups[$group][] = [
            'title' => $title,
            'href' => $href,
            'meta' => $meta,
        ];
    }
}

if (!function_exists('hg404_limit_group')) {
    function hg404_limit_group(array $items, int $limit = 6): array
    {
        return array_slice(array_values($items), 0, max(1, $limit));
    }
}

$requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
$isSensitiveRequest = hg404_is_sensitive_request($requestPath);

$pageSect = '404';
$pageTitle2 = 'Ruta perdida';
$metaDescription = 'La ruta solicitada no existe o no esta disponible. Puedes volver a biografias, capitulos, dones e inventario de Heaven\'s Gate.';
setMetaFromPage("404 | Heaven's Gate", $metaDescription, null, 'website');

$headline = "Error 404";
$body = "La ruta solicitada no est&aacute; disponible. Puedes retomar la navegaci&oacute;n desde personajes, cap&iacute;tulos, dones o inventario.";

$quickLinks = [
    ['/characters', 'Biografías'],
    ['/seasons', 'Capítulos'],
    ['/powers/gifts', 'Dones'],
    ['/inventory', 'Inventario'],
    ['/timeline', 'Línea temporal'],
    ['/search', 'Buscar'],
];

$suggestions = [
    'Personajes' => [],
    'Capítulos' => [],
    'Dones' => [],
    'Inventario' => [],
];

if (!$isSensitiveRequest && isset($link) && $link instanceof mysqli) {
    $limit = 6;

    if (hg404_table_exists($link, 'fact_characters')) {
        if ($countRs = $link->query("SELECT COUNT(*) AS total FROM fact_characters WHERE COALESCE(pretty_id, '') <> '' AND COALESCE(name, '') <> ''")) {
            $countRow = $countRs->fetch_assoc();
            $total = (int)($countRow['total'] ?? 0);
            $countRs->close();

            if ($total > 0) {
                $offset = hg404_pick_offset($total, $limit, $requestPath . ':characters');
                $sql = "
                    SELECT
                        id,
                        name,
                        alias,
                        pretty_id
                    FROM fact_characters
                    WHERE COALESCE(pretty_id, '') <> ''
                      AND COALESCE(name, '') <> ''
                    ORDER BY name ASC, id ASC
                    LIMIT {$limit} OFFSET {$offset}
                ";
                if ($rs = $link->query($sql)) {
                    while ($row = $rs->fetch_assoc()) {
                        $id = (int)($row['id'] ?? 0);
                        $slug = (string)($row['pretty_id'] ?? $id);
                        $meta = trim((string)($row['alias'] ?? ''));
                        $href = function_exists('pretty_url')
                            ? pretty_url($link, 'fact_characters', '/characters', $id)
                            : '/characters/' . rawurlencode($slug);
                        hg404_add_group_item($suggestions, 'Personajes', (string)($row['name'] ?? ''), $href, $meta);
                    }
                    $rs->close();
                }
            }
        }
    }

    if (hg404_table_exists($link, 'dim_chapters') && hg404_table_exists($link, 'dim_seasons')) {
        if ($countRs = $link->query("SELECT COUNT(*) AS total FROM dim_chapters WHERE COALESCE(pretty_id, '') <> '' AND COALESCE(name, '') <> ''")) {
            $countRow = $countRs->fetch_assoc();
            $total = (int)($countRow['total'] ?? 0);
            $countRs->close();

            if ($total > 0) {
                $offset = hg404_pick_offset($total, $limit, $requestPath . ':chapters');
                $sql = "
                    SELECT
                        ch.id,
                        ch.name,
                        ch.pretty_id,
                        ch.chapter_number,
                        COALESCE(se.name, '') AS season_name
                    FROM dim_chapters ch
                    LEFT JOIN dim_seasons se ON se.id = ch.season_id
                    WHERE COALESCE(ch.pretty_id, '') <> ''
                      AND COALESCE(ch.name, '') <> ''
                    ORDER BY COALESCE(se.season_number, 9999) ASC, ch.chapter_number ASC, ch.name ASC, ch.id ASC
                    LIMIT {$limit} OFFSET {$offset}
                ";
                if ($rs = $link->query($sql)) {
                    while ($row = $rs->fetch_assoc()) {
                        $id = (int)($row['id'] ?? 0);
                        $slug = (string)($row['pretty_id'] ?? $id);
                        $meta = trim((string)($row['season_name'] ?? ''));
                        $chapterNo = (int)($row['chapter_number'] ?? 0);
                        if ($chapterNo > 0) {
                            $meta .= ($meta !== '' ? ' · ' : '') . '#' . $chapterNo;
                        }
                        $href = function_exists('pretty_url')
                            ? pretty_url($link, 'dim_chapters', '/chapters', $id)
                            : '/chapters/' . rawurlencode($slug);
                        hg404_add_group_item($suggestions, 'Capítulos', (string)($row['name'] ?? ''), $href, $meta);
                    }
                    $rs->close();
                }
            }
        }
    }

    if (hg404_table_exists($link, 'fact_gifts')) {
        if ($countRs = $link->query("SELECT COUNT(*) AS total FROM fact_gifts WHERE COALESCE(pretty_id, '') <> '' AND COALESCE(name, '') <> ''")) {
            $countRow = $countRs->fetch_assoc();
            $total = (int)($countRow['total'] ?? 0);
            $countRs->close();

            if ($total > 0) {
                $offset = hg404_pick_offset($total, $limit, $requestPath . ':gifts');
                $sql = "
                    SELECT
                        id,
                        name,
                        pretty_id,
                        gift_group,
                        rank
                    FROM fact_gifts
                    WHERE COALESCE(pretty_id, '') <> ''
                      AND COALESCE(name, '') <> ''
                    ORDER BY name ASC, id ASC
                    LIMIT {$limit} OFFSET {$offset}
                ";
                if ($rs = $link->query($sql)) {
                    while ($row = $rs->fetch_assoc()) {
                        $id = (int)($row['id'] ?? 0);
                        $slug = (string)($row['pretty_id'] ?? $id);
                        $meta = trim((string)($row['gift_group'] ?? ''));
                        $rank = (int)($row['rank'] ?? 0);
                        if ($rank > 0) {
                            $meta .= ($meta !== '' ? ' · ' : '') . 'Rango ' . $rank;
                        }
                        $href = '/powers/gift/' . rawurlencode($slug);
                        hg404_add_group_item($suggestions, 'Dones', (string)($row['name'] ?? ''), $href, $meta);
                    }
                    $rs->close();
                }
            }
        }
    }

    if (hg404_table_exists($link, 'fact_items') && hg404_table_exists($link, 'dim_item_types')) {
        if ($countRs = $link->query("SELECT COUNT(*) AS total FROM fact_items WHERE COALESCE(pretty_id, '') <> '' AND COALESCE(name, '') <> ''")) {
            $countRow = $countRs->fetch_assoc();
            $total = (int)($countRow['total'] ?? 0);
            $countRs->close();

            if ($total > 0) {
                $offset = hg404_pick_offset($total, $limit, $requestPath . ':items');
                $sql = "
                    SELECT
                        i.id,
                        i.name,
                        i.pretty_id,
                        COALESCE(t.name, '') AS type_name,
                        COALESCE(t.pretty_id, t.id) AS type_slug
                    FROM fact_items i
                    LEFT JOIN dim_item_types t ON t.id = i.item_type_id
                    WHERE COALESCE(i.pretty_id, '') <> ''
                      AND COALESCE(i.name, '') <> ''
                    ORDER BY i.name ASC, i.id ASC
                    LIMIT {$limit} OFFSET {$offset}
                ";
                if ($rs = $link->query($sql)) {
                    while ($row = $rs->fetch_assoc()) {
                        $id = (int)($row['id'] ?? 0);
                        $itemSlug = (string)($row['pretty_id'] ?? $id);
                        $typeSlug = (string)($row['type_slug'] ?? 'items');
                        $href = '/inventory/' . rawurlencode($typeSlug) . '/' . rawurlencode($itemSlug);
                        hg404_add_group_item($suggestions, 'Inventario', (string)($row['name'] ?? ''), $href, (string)($row['type_name'] ?? ''));
                    }
                    $rs->close();
                }
            }
        }
    }
}

if (empty($suggestions['Personajes'])) {
    hg404_add_group_item($suggestions, 'Personajes', 'Archivo de personajes', '/characters', 'Listado general de biografías');
    hg404_add_group_item($suggestions, 'Personajes', 'Tipos de personaje', '/characters/types', 'Entrada por categorías');
}
if (empty($suggestions['Capítulos'])) {
    hg404_add_group_item($suggestions, 'Capítulos', 'Archivo de temporadas', '/seasons', 'Consulta temporadas y capítulos');
    hg404_add_group_item($suggestions, 'Capítulos', 'Análisis de temporadas', '/seasons/analysis', 'Resumen de actividad');
}
if (empty($suggestions['Dones'])) {
    hg404_add_group_item($suggestions, 'Dones', 'Archivo de dones', '/powers/gifts', 'Listado general');
    hg404_add_group_item($suggestions, 'Dones', 'Rituales', '/powers/rites', 'Catálogo de ritos');
}
if (empty($suggestions['Inventario'])) {
    hg404_add_group_item($suggestions, 'Inventario', 'Archivo de inventario', '/inventory', 'Objetos y artefactos');
    hg404_add_group_item($suggestions, 'Inventario', 'Buscar', '/search', 'Busca un objeto concreto');
}

foreach ($suggestions as $groupName => $rows) {
    $suggestions[$groupName] = hg404_limit_group($rows, 6);
}
?>

<?php if (function_exists('hg_page_register_stylesheet')) { hg_page_register_stylesheet('/assets/css/pages/legacy/controllers-main-error404.css'); } else { ?><link rel="stylesheet" href="/assets/css/pages/legacy/controllers-main-error404.css"><?php } ?>

<div class="bioBody hg404-wrap">
    <div class="power-card power-card--event hg404-hero">
        <div class="power-card__banner">
            <span class="power-card__title"><?= $headline ?></span>
        </div>

        <div class="power-card__body">
            <div class="power-card__media">
                <div class="hg404-media">
                    <div class="hg404-code">404</div>
                </div>
            </div>

            <div class="power-card__stats">
                <div class="power-stat">
                    <div class="power-stat__label">Estado</div>
                    <div class="power-stat__value">Ruta no disponible en el espacio liminal</div>
                </div>
                <div class="power-stat">
                    <div class="power-stat__label">Petición</div>
                    <div class="power-stat__value"><?= hg404_h($requestPath) ?></div>
                </div>
                <div class="power-stat">
                    <div class="power-stat__label">Tipo</div>
                    <div class="power-stat__value">Error 404</div>
                </div>
            </div>
        </div>

        <div class="power-card__desc">
            <div class="power-card__desc-title">
                <span class="hg404-kicker">Vuelve al mundo de juego</span>
            </div>
            <div class="power-card__desc-body">
                <p class="hg404-lead"><?= $body ?></p>
                <div class="hg404-links">
                    <?php foreach ($quickLinks as $linkRow): ?>
                        <a class="boton2" href="<?= hg404_h($linkRow[0]) ?>"><?= hg404_h($linkRow[1]) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="bioSheetData hg404-sheet">
        <fieldset class="bioSeccion">
            <legend>Sugerencias</legend>

            <?php foreach ($suggestions as $groupName => $items): ?>
                <?php if (empty($items)) continue; ?>
                <div class="hg404-group">
                    <div class="hg404-group-title"><?= hg404_h($groupName) ?></div>
                    <div class="hg404-list">
                        <?php foreach ($items as $item): ?>
                            <a class="hg404-item" href="<?= hg404_h($item['href'] ?? '/') ?>">
                                <span class="hg404-item__title"><?= hg404_h($item['title'] ?? '') ?></span>
                                <?php if (trim((string)($item['meta'] ?? '')) !== ''): ?>
                                    <span class="hg404-item__meta"><?= hg404_h($item['meta'] ?? '') ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </fieldset>
    </div>
</div>
