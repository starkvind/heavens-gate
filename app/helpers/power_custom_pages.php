<?php

if (!function_exists('hg_power_custom_h')) {
    function hg_power_custom_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_power_custom_current_page_href')) {
    function hg_power_custom_current_page_href(array $replaceQuery = []): string
    {
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $parts = parse_url($requestUri);

        $path = (string)($parts['path'] ?? '/');
        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach ($replaceQuery as $key => $value) {
            if ($value === null || $value === '') {
                unset($query[$key]);
                continue;
            }
            $query[$key] = $value;
        }

        $queryString = http_build_query($query);
        return $path . ($queryString !== '' ? '?' . $queryString : '');
    }
}

if (!function_exists('hg_power_custom_ensure_utf8')) {
    function hg_power_custom_ensure_utf8($value)
    {
        if (is_string($value)) {
            if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
                if (function_exists('mb_convert_encoding')) {
                    return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }
                if (function_exists('utf8_encode')) {
                    return utf8_encode($value);
                }
            }
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = hg_power_custom_ensure_utf8($item);
            }
            return $value;
        }

        return $value;
    }
}

if (!function_exists('hg_power_custom_fetch_rows')) {
    function hg_power_custom_fetch_rows(mysqli $link, string $query): array
    {
        $stmt = $link->prepare($query);
        if (!$stmt) {
            return [];
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();
        return $rows;
    }
}

if (!function_exists('hg_power_custom_build_items')) {
    function hg_power_custom_build_items(mysqli $link, array $config): array
    {
        $rows = hg_power_custom_fetch_rows($link, (string)($config['query'] ?? ''));
        $mapper = $config['map_row'] ?? null;
        $items = [];

        foreach ($rows as $row) {
            $item = is_callable($mapper) ? $mapper($row, $link) : $row;
            if (!is_array($item)) {
                continue;
            }

            $item = hg_power_custom_ensure_utf8($item);
            $item['fields'] = is_array($item['fields'] ?? null) ? $item['fields'] : [];
            $item['chips'] = is_array($item['chips'] ?? null) ? $item['chips'] : [];
            $item['sections'] = is_array($item['sections'] ?? null) ? $item['sections'] : [];
            $items[] = $item;
        }

        return $items;
    }
}

if (!function_exists('hg_power_custom_make_chip_list')) {
    function hg_power_custom_make_chip_list(array $values): array
    {
        $chips = [];
        foreach ($values as $value) {
            $text = trim((string)$value);
            if ($text !== '') {
                $chips[] = $text;
            }
        }
        return array_values(array_unique($chips));
    }
}

if (!function_exists('hg_power_custom_sections')) {
    function hg_power_custom_sections(array $sections): array
    {
        $normalized = [];
        foreach ($sections as $section) {
            $title = trim((string)($section['title'] ?? ''));
            $html = (string)($section['html'] ?? '');
            if ($title === '' || trim(strip_tags($html)) === '') {
                continue;
            }

            $normalized[] = [
                'title' => $title,
                'html' => $html,
            ];
        }
        return $normalized;
    }
}

if (!function_exists('hg_power_custom_gift_rules_col')) {
    function hg_power_custom_gift_rules_col(mysqli $link): string
    {
        $rs = mysqli_query($link, "SHOW COLUMNS FROM `fact_gifts` LIKE 'mechanics_text'");
        if ($rs && mysqli_num_rows($rs) > 0) {
            mysqli_free_result($rs);
            return 'mechanics_text';
        }
        if ($rs) {
            mysqli_free_result($rs);
        }
        return 'system_name';
    }
}

if (!function_exists('hg_power_custom_asset_image')) {
    function hg_power_custom_asset_image(string $value, string $fallback, string $baseDir = ''): string
    {
        $img = trim($value);
        if ($img === '') {
            return $fallback;
        }
        if (preg_match('#^https?://#i', $img) || strncmp($img, '/', 1) === 0) {
            return $img;
        }
        if (strpos($img, '/') !== false) {
            return '/' . ltrim($img, '/');
        }
        if ($baseDir !== '') {
            return rtrim($baseDir, '/') . '/' . ltrim($img, '/');
        }
        return '/' . ltrim($img, '/');
    }
}

if (!function_exists('hg_power_custom_catalog_gifts')) {
    function hg_power_custom_catalog_gifts(mysqli $link): array
    {
        $rulesCol = hg_power_custom_gift_rules_col($link);

        return [
            'kind' => 'gifts',
            'page_section' => 'Dones',
            'meta_title' => 'Dones | Heaven\'s Gate',
            'meta_description' => 'Lista personalizada e imprimible de dones.',
            'catalog_title' => 'Dones',
            'catalog_noun_plural' => 'dones',
            'storage_key' => 'hg-custom-selection-gifts-v1',
            'intro' => 'Filtra, selecciona, arrastra y compone una hoja imprimible con solo los dones que te interesen.',
            'selection_title' => 'Tu lista de dones',
            'selection_help' => 'Arrastra desde los resultados filtrados o usa el selector para añadir entradas. El orden se guarda automáticamente.',
            'empty_selection_text' => 'Aún no has añadido dones a tu lista personalizada.',
            'render_title' => 'Vista previa imprimible',
            'render_intro' => 'Las fichas de abajo respetan el orden de tu selección y se conservan entre sesiones en este navegador.',
            'filters' => [
                ['key' => 'type', 'label' => 'Tipo', 'sort' => 'text'],
                ['key' => 'category', 'label' => 'Grupo', 'sort' => 'text'],
                ['key' => 'system', 'label' => 'Fera', 'sort' => 'text'],
                ['key' => 'level', 'label' => 'Rango', 'sort' => 'number'],
                ['key' => 'origin', 'label' => 'Origen', 'sort' => 'text'],
            ],
            'query' => "
                select
                    d.id as gift_id,
                    d.pretty_id as gift_pretty_id,
                    d.name as gift_name,
                    ntd.name as gift_type,
                    d.gift_group as gift_category,
                    d.rank as gift_level,
                    d.attribute_name as gift_roll_attribute,
                    d.ability_name as gift_roll_skill,
                    d.description as gift_description,
                    d.`$rulesCol` as gift_roll_description,
                    s.name as gift_fera_system,
                    nb.name as gift_origin
                from fact_gifts d
                    left join dim_gift_types ntd on d.kind = ntd.id
                    left join dim_bibliographies nb on d.bibliography_id = nb.id
                    left join dim_systems s on d.system_id = s.id
                order by d.bibliography_id, d.rank, d.name
            ",
            'map_row' => static function (array $row, mysqli $db): array {
                $id = (int)($row['gift_id'] ?? 0);
                $type = trim((string)($row['gift_type'] ?? ''));
                $category = trim((string)($row['gift_category'] ?? ''));
                $level = trim((string)($row['gift_level'] ?? ''));
                $system = trim((string)($row['gift_fera_system'] ?? ''));
                $origin = trim((string)($row['gift_origin'] ?? ''));
                $roll = trim((string)($row['gift_roll_attribute'] ?? ''));
                $skill = trim((string)($row['gift_roll_skill'] ?? ''));
                $rollShort = trim($roll . (($roll !== '' && $skill !== '') ? ' + ' : '') . $skill);
                $desc = (string)($row['gift_description'] ?? '');
                $mech = (string)($row['gift_roll_description'] ?? '');

                return [
                    'id' => (string)$id,
                    'name' => (string)($row['gift_name'] ?? ''),
                    'href' => pretty_url($db, 'fact_gifts', '/powers/gift', $id),
                    'library_meta' => implode(' · ', array_filter([$type, ($level !== '' ? 'Rango ' . $level : ''), $origin])),
                    'fields' => [
                        'type' => $type,
                        'category' => $category,
                        'system' => $system,
                        'level' => $level,
                        'origin' => $origin,
                    ],
                    'chips' => hg_power_custom_make_chip_list([
                        $type,
                        $category,
                        $level !== '' ? 'Rango ' . $level : '',
                        $rollShort,
                        $system,
                        $origin,
                    ]),
                    'sections' => hg_power_custom_sections([
                        ['title' => 'Descripción', 'html' => $desc !== '' ? $desc : '<p>Descripción no disponible.</p>'],
                        ['title' => 'Sistema', 'html' => $mech],
                    ]),
                ];
            },
        ];
    }
}

if (!function_exists('hg_power_custom_catalog_rites')) {
    function hg_power_custom_catalog_rites(mysqli $link): array
    {
        return [
            'kind' => 'rites',
            'page_section' => 'Rituales',
            'meta_title' => 'Rituales | Heaven\'s Gate',
            'meta_description' => 'Lista personalizada e imprimible de rituales.',
            'catalog_title' => 'Rituales',
            'catalog_noun_plural' => 'rituales',
            'storage_key' => 'hg-custom-selection-rites-v1',
            'intro' => 'Prepara una lista de rituales a medida y ordénala para impresión con la combinación exacta que necesites.',
            'selection_title' => 'Tu lista de rituales',
            'selection_help' => 'La selección se guarda en el navegador. Puedes reordenarla arrastrando elementos dentro de la lista.',
            'empty_selection_text' => 'Aún no has añadido rituales a tu lista personalizada.',
            'render_title' => 'Vista previa imprimible',
            'render_intro' => 'La vista previa replica el formato extendido y se apoya en tu orden personalizado.',
            'filters' => [
                ['key' => 'system', 'label' => 'Fera', 'sort' => 'text'],
                ['key' => 'type', 'label' => 'Tipo', 'sort' => 'text'],
                ['key' => 'level', 'label' => 'Nivel', 'sort' => 'number'],
                ['key' => 'species', 'label' => 'Raza', 'sort' => 'text'],
                ['key' => 'origin', 'label' => 'Origen', 'sort' => 'text'],
            ],
            'query' => "
                select
                    nr.id as ritual_id,
                    nr.name as ritual_name,
                    CONCAT(
                        'Rito',
                        CASE
                            WHEN ntr.determinant <> '' THEN CONCAT(' ', ntr.determinant)
                            ELSE ''
                        END,
                        ' ',
                        ntr.name
                    ) as ritual_type,
                    nr.pretty_id as ritual_pretty_id,
                    nr.level as ritual_level,
                    nr.race as ritual_species,
                    nr.description as ritual_description,
                    nr.system_text as ritual_system_text,
                    s.name as ritual_fera_system,
                    nb.name as ritual_origin
                from fact_rites nr
                    left join dim_rite_types ntr on nr.kind = ntr.id
                    left join dim_bibliographies nb on nr.bibliography_id = nb.id
                    left join dim_systems s on nr.system_id = s.id
                order by nr.bibliography_id, nr.level, nr.name
            ",
            'map_row' => static function (array $row, mysqli $db): array {
                $id = (int)($row['ritual_id'] ?? 0);
                $type = trim((string)($row['ritual_type'] ?? ''));
                $level = trim((string)($row['ritual_level'] ?? ''));
                $species = trim((string)($row['ritual_species'] ?? ''));
                $system = trim((string)($row['ritual_fera_system'] ?? ''));
                $origin = trim((string)($row['ritual_origin'] ?? ''));
                $desc = (string)($row['ritual_description'] ?? '');
                $mech = (string)($row['ritual_system_text'] ?? '');

                return [
                    'id' => (string)$id,
                    'name' => (string)($row['ritual_name'] ?? ''),
                    'href' => pretty_url($db, 'fact_rites', '/powers/rite', $id),
                    'library_meta' => implode(' · ', array_filter([$type, ($level !== '' ? 'Nivel ' . $level : ''), $origin])),
                    'fields' => [
                        'system' => $system,
                        'type' => $type,
                        'level' => $level,
                        'species' => $species,
                        'origin' => $origin,
                    ],
                    'chips' => hg_power_custom_make_chip_list([
                        $type,
                        $level !== '' ? 'Nivel ' . $level : '',
                        $species,
                        $system,
                        $origin,
                    ]),
                    'sections' => hg_power_custom_sections([
                        ['title' => 'Descripción', 'html' => $desc !== '' ? $desc : '<p>Descripción no disponible.</p>'],
                        ['title' => 'Sistema', 'html' => $mech],
                    ]),
                ];
            },
        ];
    }
}

if (!function_exists('hg_power_custom_catalog_totems')) {
    function hg_power_custom_catalog_totems(mysqli $link): array
    {
        return [
            'kind' => 'totems',
            'page_section' => 'Tótems',
            'meta_title' => 'Tótems | Heaven\'s Gate',
            'meta_description' => 'Lista personalizada e imprimible de tótems.',
            'catalog_title' => 'Tótems',
            'catalog_noun_plural' => 'tótems',
            'storage_key' => 'hg-custom-selection-totems-v1',
            'intro' => 'Compón repertorios personalizados de tótems, reordénalos y deja la página lista para impresión.',
            'selection_title' => 'Tu lista de tótems',
            'selection_help' => 'Puedes mezclar tótems de distintos tipos y costes, con persistencia local y reordenación manual.',
            'empty_selection_text' => 'Aún no has añadido tótems a tu lista personalizada.',
            'render_title' => 'Vista previa imprimible',
            'render_intro' => 'La ficha renderizada incluye imagen si el tótem la tiene registrada, además de rasgos y prohibiciones.',
            'filters' => [
                ['key' => 'type', 'label' => 'Tipo', 'sort' => 'text'],
                ['key' => 'cost', 'label' => 'Coste', 'sort' => 'number'],
                ['key' => 'origin', 'label' => 'Origen', 'sort' => 'text'],
            ],
            'query' => "
                select
                    t.id as totem_id,
                    t.pretty_id as totem_pretty_id,
                    t.name as totem_name,
                    CONCAT(
                        'Tótem',
                        CASE
                            WHEN tt.determinant <> '' THEN CONCAT(' ', tt.determinant)
                            ELSE ''
                        END,
                        ' ',
                        tt.name
                    ) as totem_type,
                    t.cost as totem_cost,
                    t.description as totem_description,
                    t.traits as totem_traits,
                    t.prohibited as totem_prohibited,
                    t.image_url as totem_image_url,
                    b.name as totem_origin
                from dim_totems t
                    left join dim_totem_types tt on t.totem_type_id = tt.id
                    left join dim_bibliographies b on t.bibliography_id = b.id
                order by t.bibliography_id, t.cost, t.name
            ",
            'map_row' => static function (array $row, mysqli $db): array {
                $id = (int)($row['totem_id'] ?? 0);
                $type = trim((string)($row['totem_type'] ?? ''));
                $cost = trim((string)($row['totem_cost'] ?? ''));
                $origin = trim((string)($row['totem_origin'] ?? ''));
                $desc = (string)($row['totem_description'] ?? '');
                $traits = (string)($row['totem_traits'] ?? '');
                $prohibited = (string)($row['totem_prohibited'] ?? '');

                return [
                    'id' => (string)$id,
                    'name' => (string)($row['totem_name'] ?? ''),
                    'href' => pretty_url($db, 'dim_totems', '/powers/totem', $id),
                    'library_meta' => implode(' · ', array_filter([$type, ($cost !== '' ? 'Coste ' . $cost : ''), $origin])),
                    'fields' => [
                        'type' => $type,
                        'cost' => $cost,
                        'origin' => $origin,
                    ],
                    'chips' => hg_power_custom_make_chip_list([
                        $type,
                        $cost !== '' ? 'Coste ' . $cost : '',
                        $origin,
                    ]),
                    'sections' => hg_power_custom_sections([
                        ['title' => 'Descripción', 'html' => $desc !== '' ? $desc : '<p>Descripción no disponible.</p>'],
                        ['title' => 'Rasgos', 'html' => $traits],
                        ['title' => 'Prohibiciones', 'html' => $prohibited],
                    ]),
                ];
            },
        ];
    }
}

if (!function_exists('hg_power_custom_catalog_disciplines')) {
    function hg_power_custom_catalog_disciplines(mysqli $link): array
    {
        return [
            'kind' => 'disciplines',
            'page_section' => 'Disciplinas',
            'meta_title' => 'Disciplinas | Heaven\'s Gate',
            'meta_description' => 'Lista personalizada e imprimible de disciplinas.',
            'catalog_title' => 'Disciplinas',
            'catalog_noun_plural' => 'disciplinas',
            'storage_key' => 'hg-custom-selection-disciplines-v1',
            'intro' => 'Organiza disciplinas por orden propio, con persistencia local y formato extendido listo para imprimir.',
            'selection_title' => 'Tu lista de disciplinas',
            'selection_help' => 'Añade poderes de distintas disciplinas, reordénalos y revisa abajo su texto completo antes de imprimir.',
            'empty_selection_text' => 'Aún no has añadido disciplinas a tu lista personalizada.',
            'render_title' => 'Vista previa imprimible',
            'render_intro' => 'La vista previa reutiliza la descripción y el sistema de cada poder para preparar handouts o resúmenes.',
            'filters' => [
                ['key' => 'type', 'label' => 'Disciplina', 'sort' => 'text'],
                ['key' => 'level', 'label' => 'Nivel', 'sort' => 'number'],
                ['key' => 'origin', 'label' => 'Origen', 'sort' => 'text'],
            ],
            'query' => "
                select
                    d.id as disc_id,
                    d.pretty_id as disc_pretty_id,
                    d.name as disc_name,
                    ddt.name as disc_type,
                    d.level as disc_level,
                    d.attribute as disc_roll_attribute,
                    d.skill as disc_roll_skill,
                    d.description as disc_description,
                    d.system_name as disc_system_name,
                    d.image_url as disc_image_url,
                    nb.name as disc_origin
                from fact_discipline_powers d
                    left join dim_discipline_types ddt on d.disc = ddt.id
                    left join dim_bibliographies nb on d.bibliography_id = nb.id
                order by d.bibliography_id, d.disc, d.level, d.name
            ",
            'map_row' => static function (array $row, mysqli $db): array {
                $id = (int)($row['disc_id'] ?? 0);
                $type = trim((string)($row['disc_type'] ?? ''));
                $level = trim((string)($row['disc_level'] ?? ''));
                $origin = trim((string)($row['disc_origin'] ?? ''));
                $roll = trim((string)($row['disc_roll_attribute'] ?? ''));
                $skill = trim((string)($row['disc_roll_skill'] ?? ''));
                $rollShort = trim($roll . (($roll !== '' && $skill !== '') ? ' + ' : '') . $skill);
                $desc = (string)($row['disc_description'] ?? '');
                $mech = (string)($row['disc_system_name'] ?? '');

                return [
                    'id' => (string)$id,
                    'name' => (string)($row['disc_name'] ?? ''),
                    'href' => pretty_url($db, 'fact_discipline_powers', '/powers/discipline', $id),
                    'library_meta' => implode(' · ', array_filter([$type, ($level !== '' ? 'Nivel ' . $level : ''), $origin])),
                    'fields' => [
                        'type' => $type,
                        'level' => $level,
                        'origin' => $origin,
                    ],
                    'chips' => hg_power_custom_make_chip_list([
                        $type,
                        $level !== '' ? 'Nivel ' . $level : '',
                        $rollShort,
                        $origin,
                    ]),
                    'sections' => hg_power_custom_sections([
                        ['title' => 'Descripción', 'html' => $desc !== '' ? $desc : '<p>Descripción no disponible.</p>'],
                        ['title' => 'Sistema', 'html' => $mech],
                    ]),
                ];
            },
        ];
    }
}

if (!function_exists('hg_power_custom_render')) {
    function hg_power_custom_render(mysqli $link, array $config): void
    {
        $pageSect = (string)($config['page_section'] ?? ($config['catalog_title'] ?? 'Poderes'));
        $_SESSION['punk2'] = $pageSect;
        setMetaFromPage(
            (string)($config['meta_title'] ?? ($pageSect . " | Heaven's Gate")),
            (string)($config['meta_description'] ?? ''),
            null,
            'website'
        );

        $printMode = isset($_GET['print']) && $_GET['print'] === '1';
        if (!$printMode) {
            include("app/partials/main_nav_bar.php");
        }

        $items = hg_power_custom_build_items($link, $config);

        $pageHref = hg_power_custom_h(hg_power_custom_current_page_href());
        $printHref = hg_power_custom_h(hg_power_custom_current_page_href(['print' => '1']));
        $payload = [
            'kind' => (string)($config['kind'] ?? 'powers'),
            'storageKey' => (string)($config['storage_key'] ?? 'hg-custom-power-selection-v1'),
            'printMode' => $printMode,
            'catalogTitle' => (string)($config['catalog_title'] ?? 'Poderes'),
            'catalogNounPlural' => (string)($config['catalog_noun_plural'] ?? 'entradas'),
            'intro' => (string)($config['intro'] ?? ''),
            'selectionTitle' => (string)($config['selection_title'] ?? 'Tu lista'),
            'selectionHelp' => (string)($config['selection_help'] ?? ''),
            'emptySelectionText' => (string)($config['empty_selection_text'] ?? 'No hay elementos seleccionados.'),
            'renderTitle' => (string)($config['render_title'] ?? 'Vista previa'),
            'renderIntro' => (string)($config['render_intro'] ?? ''),
            'filters' => array_values($config['filters'] ?? []),
            'items' => array_values($items),
        ];

        $jsonPayload = json_encode(
            $payload,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        ?>
        <link rel="stylesheet" href="/assets/css/hg-power-custom.css">
        <script src="/assets/js/hg-power-custom.js" defer></script>

        <div class="hg-power-custom<?= $printMode ? ' hg-power-custom--print' : ''; ?>" id="hgpc-root" data-print-mode="<?= $printMode ? '1' : '0'; ?>">
            <div class="hgpc-wrap">
                <?php
                if (!$printMode) {
                    require_once __DIR__ . '/../partials/power_catalog_tabs.php';
                    hg_render_power_catalog_tabs((string)($config['kind'] ?? ''), 'custom');
                }
                ?>
                <section class="hgpc-hero">
                    <div class="hgpc-hero__title">
                        <div>
                            <h2><?= hg_power_custom_h($config['catalog_title'] ?? 'Poderes'); ?></h2>
                            <p><?= hg_power_custom_h($config['intro'] ?? ''); ?></p>
                        </div>
                        <div class="hgpc-hero__stats">
                            <span class="hgpc-pill">Catálogo: <strong><?= count($items); ?></strong></span>
                            <span class="hgpc-pill">Seleccionados: <strong id="hgpc-selected-count">0</strong></span>
                        </div>
                    </div>

                    <div class="hgpc-hero__actions">
                        <?php if ($printMode): ?>
                            <button type="button" class="hgpc-btn hgpc-btn--primary" id="hgpc-print-now">Imprimir</button>
                        <?php else: ?>
                            <a href="<?= $printHref; ?>" class="hgpc-btn hgpc-btn--primary">Versión imprimible</a>
                        <?php endif; ?>
                        <a href="<?= $pageHref; ?>" class="hgpc-btn">Recargar catálogo</a>
                    </div>
                </section>

                <section class="hgpc-builder" aria-label="Constructor de lista personalizada">
                    <div class="hgpc-builder__toolbar">
                        <div class="hgpc-search">
                            <label for="hgpc-search-input">Buscar en el catálogo</label>
                            <input type="search" id="hgpc-search-input" placeholder="Nombre, tipo, origen, texto..." autocomplete="off">
                        </div>
                        <div class="hgpc-filter-bar" id="hgpc-filter-bar"></div>
                    </div>

                    <div class="hgpc-builder__grid">
                        <section class="hgpc-panel">
                            <div class="hgpc-panel__head">
                                <h3>Catálogo filtrado</h3>
                                <span id="hgpc-filtered-count">0 coincidencias</span>
                            </div>

                            <label class="hgpc-label" for="hgpc-catalog-select">Selector rápido</label>
                            <select id="hgpc-catalog-select" class="hgpc-select" size="12"></select>

                            <div class="hgpc-panel__actions">
                                <button type="button" class="hgpc-btn hgpc-btn--primary" id="hgpc-add-selected">Añadir a la lista</button>
                                <button type="button" class="hgpc-btn" id="hgpc-clear-filters">Limpiar filtros</button>
                            </div>

                            <div class="hgpc-drop-source" id="hgpc-library-results" aria-live="polite"></div>
                        </section>

                        <section class="hgpc-panel hgpc-panel--selection">
                            <div class="hgpc-panel__head">
                                <h3><?= hg_power_custom_h($config['selection_title'] ?? 'Tu lista'); ?></h3>
                                <span id="hgpc-selection-status">0 elementos</span>
                            </div>

                            <p class="hgpc-help"><?= hg_power_custom_h($config['selection_help'] ?? ''); ?></p>
                            <div class="hgpc-selection-drop" id="hgpc-selection-drop">Arrastra aquí desde el catálogo o reorganiza la lista soltando sobre otro elemento.</div>
                            <ul class="hgpc-selection-list" id="hgpc-selection-list"></ul>

                            <div class="hgpc-panel__actions">
                                <button type="button" class="hgpc-btn" id="hgpc-clear-selection">Vaciar lista</button>
                            </div>
                        </section>
                    </div>
                </section>

                <section class="hgpc-render">
                    <div class="hgpc-render__head">
                        <div>
                            <h3><?= hg_power_custom_h($config['render_title'] ?? 'Vista previa'); ?></h3>
                            <p><?= hg_power_custom_h($config['render_intro'] ?? ''); ?></p>
                        </div>
                    </div>

                    <div class="hgpc-empty" id="hgpc-render-empty"><?= hg_power_custom_h($config['empty_selection_text'] ?? 'No hay elementos seleccionados.'); ?></div>
                    <div class="hgpc-cards" id="hgpc-rendered-cards"></div>
                </section>
            </div>
        </div>

        <script>
        window.HGPowerCustomPage = <?= $jsonPayload ?: '{}'; ?>;
        window.HGPowerCustomPrintHref = <?= json_encode($printHref, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        </script>
        <?php
    }
}

if (!function_exists('hg_power_custom_render_full_catalog')) {
    function hg_power_custom_render_full_catalog(mysqli $link, array $config): void
    {
        $pageSect = (string)($config['page_section'] ?? ($config['catalog_title'] ?? 'Poderes'));
        $_SESSION['punk2'] = $pageSect;
        setMetaFromPage(
            (string)($config['meta_title'] ?? ($pageSect . " | Heaven's Gate")),
            (string)($config['meta_description'] ?? ''),
            null,
            'website'
        );

        $printMode = isset($_GET['print']) && $_GET['print'] === '1';
        if (!$printMode) {
            include("app/partials/main_nav_bar.php");
        }

        $items = hg_power_custom_build_items($link, $config);
        $printHref = hg_power_custom_h(hg_power_custom_current_page_href(['print' => '1']));
        ?>
        <link rel="stylesheet" href="/assets/css/hg-power-custom.css">
        <div class="hg-power-custom<?= $printMode ? ' hg-power-custom--print' : ''; ?>" id="hgpc-root" data-print-mode="<?= $printMode ? '1' : '0'; ?>">
            <div class="hgpc-wrap">
                <?php
                if (!$printMode) {
                    require_once __DIR__ . '/../partials/power_catalog_tabs.php';
                    hg_render_power_catalog_tabs((string)($config['kind'] ?? ''), 'full');
                }
                ?>

                <section class="hgpc-hero">
                    <div class="hgpc-hero__title">
                        <div>
                            <h2><?= hg_power_custom_h($config['catalog_title'] ?? 'Poderes'); ?></h2>
                            <p><?= hg_power_custom_h((string)($config['intro'] ?? '')); ?></p>
                        </div>
                        <div class="hgpc-hero__stats">
                            <span class="hgpc-pill">Total: <strong><?= count($items); ?></strong></span>
                        </div>
                    </div>
                    <?php if (!$printMode): ?>
                        <div class="hgpc-hero__actions">
                            <a href="<?= $printHref; ?>" class="hgpc-btn hgpc-btn--primary">Versión imprimible</a>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="hgpc-render" style="margin-top:16px;">
                    <div class="hgpc-cards" id="hgpc-rendered-cards">
                        <?php foreach ($items as $item): ?>
                            <article class="hgpc-card">
                                <div class="hgpc-card__top">
                                    <h3><?= hg_power_custom_h($item['name'] ?? ''); ?></h3>
                                    <?php if (!$printMode): ?>
                                        <a class="hgpc-card__back" href="#hgpc-root">Volver arriba</a>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($item['image']['src'])): ?>
                                    <div class="hgpc-card__media">
                                        <img
                                            class="hgpc-card__image"
                                            src="<?= hg_power_custom_h((string)$item['image']['src']); ?>"
                                            alt="<?= hg_power_custom_h((string)($item['image']['alt'] ?? $item['name'] ?? '')); ?>"
                                        >
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($item['chips'])): ?>
                                    <div class="hgpc-card__chips">
                                        <?php foreach ($item['chips'] as $chip): ?>
                                            <span class="hgpc-chip"><?= hg_power_custom_h($chip); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($item['sections'])): ?>
                                    <div class="hgpc-card__sections">
                                        <?php foreach ($item['sections'] as $section): ?>
                                            <div class="hgpc-card__section">
                                                <h4><?= hg_power_custom_h($section['title'] ?? ''); ?></h4>
                                                <div class="hgpc-card__content"><?= (string)($section['html'] ?? ''); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }
}
