<?php

if (!function_exists('hg_search_catalog_column_exists')) {
    function hg_search_catalog_column_exists(mysqli $link, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . ':' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $ok = false;
        if ($stmt = $link->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ")) {
            $stmt->bind_param('ss', $table, $column);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
            $ok = ((int)$count > 0);
        }

        $cache[$key] = $ok;
        return $ok;
    }
}

if (!function_exists('hg_search_catalog')) {
    function hg_search_catalog(mysqli $link): array
    {
        $giftRulesField = hg_search_catalog_column_exists($link, 'fact_gifts', 'mechanics_text')
            ? 'src.mechanics_text'
            : 'src.system_name';
        $chapterTextField = hg_search_catalog_column_exists($link, 'dim_chapters', 'synopsis')
            ? 'src.synopsis'
            : 'src.name';
        $miscExcerptField = hg_search_catalog_column_exists($link, 'fact_misc_systems', 'description')
            ? 'src.description'
            : 'src.kind';
        $hasSeasonChronicleId = hg_search_catalog_column_exists($link, 'dim_seasons', 'chronicle_id');

        $chronicleFrom = 'dim_chronicles src';
        $chronicleSecondary = "''";
        $chronicleGroup = '';
        if ($hasSeasonChronicleId) {
            $chronicleFrom .= ' LEFT JOIN dim_seasons s ON s.chronicle_id = src.id';
            $chronicleSecondary = "CASE
                WHEN COUNT(DISTINCT s.id) <= 0 THEN 'Sin temporadas vinculadas'
                WHEN COUNT(DISTINCT s.id) = 1 THEN '1 temporada vinculada'
                ELSE CONCAT(COUNT(DISTINCT s.id), ' temporadas vinculadas')
            END";
            $chronicleGroup = 'src.id, src.name, src.description';
        }

        $seasonLabelSql = "CASE
            WHEN COALESCE(src.season_kind, 'temporada') = 'historia_personal' THEN 'Historia personal'
            WHEN COALESCE(src.season_kind, 'temporada') = 'especial' THEN 'Especial'
            WHEN COALESCE(src.season_kind, 'temporada') = 'inciso' THEN CONCAT('Inciso ', CASE WHEN src.season_number BETWEEN 100 AND 199 THEN src.season_number - 100 ELSE src.season_number END)
            ELSE CONCAT('Temporada ', src.season_number)
        END";
        $seasonStatusSql = "CASE
            WHEN COALESCE(src.finished, 0) = 1 THEN 'Finalizada'
            WHEN COALESCE(src.finished, 0) = 2 THEN 'Cancelada'
            ELSE 'En curso'
        END";
        $seasonSecondary = "CONCAT({$seasonLabelSql}, ' | ', {$seasonStatusSql})";

        $chapterCodeSql = "CASE
            WHEN COALESCE(s.season_kind, 'temporada') = 'temporada' THEN CONCAT(COALESCE(s.season_number, '?'), 'x', LPAD(COALESCE(src.chapter_number, 0), 2, '0'))
            ELSE LPAD(COALESCE(src.chapter_number, 0), 2, '0')
        END";
        $chapterSeasonSql = "CASE
            WHEN COALESCE(s.season_kind, 'temporada') = 'historia_personal' THEN CONCAT('Historia personal', CASE WHEN COALESCE(s.name, '') <> '' THEN CONCAT(' | ', s.name) ELSE '' END)
            WHEN COALESCE(s.season_kind, 'temporada') = 'especial' THEN CONCAT('Especial', CASE WHEN COALESCE(s.name, '') <> '' THEN CONCAT(' | ', s.name) ELSE '' END)
            WHEN COALESCE(s.season_kind, 'temporada') = 'inciso' THEN CONCAT('Inciso ', CASE WHEN COALESCE(s.season_number, 0) BETWEEN 100 AND 199 THEN s.season_number - 100 ELSE COALESCE(s.season_number, 0) END, CASE WHEN COALESCE(s.name, '') <> '' THEN CONCAT(' | ', s.name) ELSE '' END)
            ELSE CONCAT('Temporada ', COALESCE(s.season_number, '?'), CASE WHEN COALESCE(s.name, '') <> '' THEN CONCAT(' | ', s.name) ELSE '' END)
        END";
        $chapterSecondary = "CONCAT({$chapterSeasonSql}, ' | Capítulo ', {$chapterCodeSql})";

        return [
            'all' => [
                'label_html' => 'Todas las secciones',
                'label_text' => 'Todas las secciones',
                'virtual' => true,
            ],
            'biografias' => [
                'label_html' => 'Biograf&iacute;as',
                'label_text' => 'Biografías',
                'from_sql' => 'fact_characters src LEFT JOIN dim_chronicles ch ON ch.id = src.chronicle_id',
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => 'src.info_text',
                'secondary_expr' => "CASE WHEN COALESCE(ch.name, '') <> '' THEN CONCAT('Crónica: ', ch.name) ELSE '' END",
                'search_fields' => ['src.name', 'src.info_text', 'ch.name'],
                'route' => 'muestrabio',
                'order_sql' => 'src.name ASC, src.id DESC',
                'all_limit' => 8,
                'section_weight' => 18,
            ],
            'cronicas' => [
                'label_html' => 'Cr&oacute;nicas',
                'label_text' => 'Crónicas',
                'from_sql' => $chronicleFrom,
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => 'src.description',
                'secondary_expr' => $chronicleSecondary,
                'search_fields' => ['src.name', 'src.description'],
                'route' => 'chronicles',
                'group_sql' => $chronicleGroup,
                'order_sql' => 'src.name ASC, src.id DESC',
                'all_limit' => 6,
                'section_weight' => 14,
            ],
            'temporadas' => [
                'label_html' => 'Temporadas',
                'label_text' => 'Temporadas',
                'from_sql' => 'dim_seasons src',
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => 'src.description',
                'secondary_expr' => $seasonSecondary,
                'search_fields' => ['src.name', 'src.description'],
                'route' => 'temp',
                'order_sql' => 'COALESCE(src.sort_order, 999999) ASC, src.season_number ASC, src.name ASC',
                'all_limit' => 8,
                'section_weight' => 13,
            ],
            'episodios' => [
                'label_html' => 'Episodios',
                'label_text' => 'Episodios',
                'from_sql' => 'dim_chapters src LEFT JOIN dim_seasons s ON s.id = src.season_id',
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => $chapterTextField,
                'secondary_expr' => $chapterSecondary,
                'search_fields' => ['src.name', $chapterTextField, 's.name'],
                'route' => 'seechapter',
                'order_sql' => 'COALESCE(s.sort_order, 999999) ASC, src.chapter_number ASC, src.name ASC',
                'all_limit' => 8,
                'section_weight' => 13,
            ],
            'escritos' => [
                'label_html' => 'Documentos',
                'label_text' => 'Documentos',
                'from_sql' => 'fact_docs src',
                'id_expr' => 'src.id',
                'title_expr' => 'src.title',
                'excerpt_expr' => 'src.content',
                'secondary_expr' => "CONCAT('Documento #', src.id)",
                'search_fields' => ['src.title', 'src.content'],
                'route' => 'verdoc',
                'order_sql' => 'src.title ASC, src.id DESC',
                'all_limit' => 6,
                'section_weight' => 9,
            ],
            'objetos' => [
                'label_html' => 'Inventario',
                'label_text' => 'Inventario',
                'from_sql' => 'fact_items src LEFT JOIN dim_item_types it ON it.id = src.item_type_id',
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => 'src.description',
                'secondary_expr' => "CASE WHEN COALESCE(it.name, '') <> '' THEN CONCAT('Tipo: ', it.name) ELSE CONCAT('Objeto #', src.id) END",
                'search_fields' => ['src.name', 'src.description', 'it.name'],
                'route' => 'seeitem',
                'order_sql' => 'src.name ASC, src.id DESC',
                'all_limit' => 6,
                'section_weight' => 8,
            ],
            'sistemas' => [
                'label_html' => 'Sistemas',
                'label_text' => 'Sistemas',
                'from_sql' => 'dim_systems src',
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => 'src.description',
                'secondary_expr' => "'Ficha de sistema'",
                'search_fields' => ['src.name', 'src.description'],
                'route' => 'sistemas',
                'order_sql' => 'src.name ASC, src.id DESC',
                'all_limit' => 6,
                'section_weight' => 10,
            ],
            'razas' => [
                'label_html' => 'Razas',
                'label_text' => 'Razas',
                'from_sql' => 'dim_breeds src LEFT JOIN dim_systems sys ON sys.id = src.system_id',
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => 'src.description',
                'secondary_expr' => "CASE WHEN COALESCE(sys.name, '') <> '' THEN CONCAT('Sistema: ', sys.name) ELSE '' END",
                'search_fields' => ['src.name', 'src.description', 'sys.name'],
                'route' => 'versistdetalle_breed',
                'order_sql' => 'sys.name ASC, src.name ASC, src.id DESC',
                'all_limit' => 6,
                'section_weight' => 11,
            ],
            'auspicios' => [
                'label_html' => 'Auspicios',
                'label_text' => 'Auspicios',
                'from_sql' => 'dim_auspices src LEFT JOIN dim_systems sys ON sys.id = src.system_id',
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => 'src.description',
                'secondary_expr' => "CASE WHEN COALESCE(sys.name, '') <> '' THEN CONCAT('Sistema: ', sys.name) ELSE '' END",
                'search_fields' => ['src.name', 'src.description', 'sys.name'],
                'route' => 'versistdetalle_auspice',
                'order_sql' => 'sys.name ASC, src.name ASC, src.id DESC',
                'all_limit' => 6,
                'section_weight' => 11,
            ],
            'tribus' => [
                'label_html' => 'Tribus',
                'label_text' => 'Tribus',
                'from_sql' => 'dim_tribes src LEFT JOIN dim_systems sys ON sys.id = src.system_id',
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => 'src.description',
                'secondary_expr' => "CASE WHEN COALESCE(sys.name, '') <> '' THEN CONCAT('Sistema: ', sys.name) ELSE '' END",
                'search_fields' => ['src.name', 'src.description', 'sys.name'],
                'route' => 'versistdetalle_tribe',
                'order_sql' => 'sys.name ASC, src.name ASC, src.id DESC',
                'all_limit' => 6,
                'section_weight' => 11,
            ],
            'misc_sistemas' => [
                'label_html' => 'Miscel&aacute;nea de sistemas',
                'label_text' => 'Miscelánea de sistemas',
                'from_sql' => 'fact_misc_systems src',
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => $miscExcerptField,
                'secondary_expr' => "TRIM(CONCAT(
                    CASE WHEN COALESCE(src.system_name, '') <> '' THEN CONCAT('Sistema: ', src.system_name) ELSE '' END,
                    CASE WHEN COALESCE(src.kind, '') <> '' THEN CONCAT(
                        CASE WHEN COALESCE(src.system_name, '') <> '' THEN ' | ' ELSE '' END,
                        src.kind
                    ) ELSE '' END
                ))",
                'search_fields' => ['src.name', $miscExcerptField, 'src.kind', 'src.system_name'],
                'route' => 'versistdetalle_misc',
                'order_sql' => 'src.system_name ASC, src.name ASC, src.id DESC',
                'all_limit' => 6,
                'section_weight' => 9,
            ],
            'habilidades' => [
                'label_html' => 'Habilidades',
                'label_text' => 'Habilidades',
                'from_sql' => 'dim_traits src',
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => 'src.description',
                'secondary_expr' => "'Rasgo'",
                'search_fields' => ['src.name', 'src.description'],
                'route' => 'verrasgo',
                'order_sql' => 'src.name ASC, src.id DESC',
                'all_limit' => 6,
                'section_weight' => 8,
            ],
            'merydef' => [
                'label_html' => 'M&eacute;ritos y Defectos',
                'label_text' => 'Méritos y Defectos',
                'from_sql' => 'dim_merits_flaws src',
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => 'src.description',
                'secondary_expr' => "'Regla'",
                'search_fields' => ['src.name', 'src.description'],
                'route' => 'vermyd',
                'order_sql' => 'src.name ASC, src.id DESC',
                'all_limit' => 6,
                'section_weight' => 8,
            ],
            'dones' => [
                'label_html' => 'Dones',
                'label_text' => 'Dones',
                'from_sql' => 'fact_gifts src',
                'id_expr' => 'src.id',
                'title_expr' => 'src.name',
                'excerpt_expr' => 'src.description',
                'secondary_expr' => "CASE WHEN COALESCE(src.system_name, '') <> '' THEN CONCAT('Sistema: ', src.system_name) ELSE '' END",
                'search_fields' => ['src.name', 'src.description', $giftRulesField],
                'route' => 'muestradon',
                'order_sql' => 'src.name ASC, src.id DESC',
                'all_limit' => 6,
                'section_weight' => 10,
            ],
        ];
    }
}
