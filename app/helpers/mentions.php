<?php
// Mention helpers: autocomplete + token conversion

require_once(__DIR__ . '/pretty.php');

function hg_mentions_table_columns(mysqli $link, string $table): array {
    static $cache = [];
    $key = strtolower(trim($table));
    if ($key === '') return [];
    if (isset($cache[$key])) return $cache[$key];

    $cols = [];
    if ($st = $link->prepare("SHOW COLUMNS FROM `$table`")) {
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $field = strtolower((string)($row['Field'] ?? ''));
            if ($field !== '') $cols[$field] = true;
        }
        $st->close();
    }
    $cache[$key] = $cols;
    return $cols;
}

function hg_mentions_config(): array {
    return [
        'character' => [
            'table' => 'fact_characters',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/characters',
            'search' => ['name', 'alias', 'garou_name'],
        ],
        'season' => [
            'table' => 'dim_seasons',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/seasons',
        ],
        'episode' => [
            'table' => 'dim_chapters',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/chapters',
        ],
        'organization' => [
            'table' => 'dim_organizations',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/organizations',
        ],
        'group' => [
            'table' => 'dim_groups',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/groups',
        ],
        'gift' => [
            'table' => 'fact_gifts',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/powers/gift',
        ],
        'rite' => [
            'table' => 'fact_rites',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/powers/rite',
        ],
        'totem' => [
            'table' => 'dim_totems',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/powers/totem',
        ],
        'discipline' => [
            'table' => 'fact_discipline_powers',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/powers/discipline',
        ],
        'item' => [
            'table' => 'fact_items',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/inventory',
            'special' => 'item',
            'type' => 'item',
        ],
        // Backward-compatible aliases for item mentions.
        'items' => [
            'table' => 'fact_items',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/inventory',
            'special' => 'item',
            'type' => 'item',
        ],
        'fact_items' => [
            'table' => 'fact_items',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/inventory',
            'special' => 'item',
            'type' => 'item',
        ],
        'trait' => [
            'table' => 'dim_traits',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/rules/traits',
        ],
        'background' => [
            'table' => 'dim_traits',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/rules/traits',
            'where' => "kind='Trasfondos'",
        ],
        'merit' => [
            'table' => 'dim_merits_flaws',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/rules/merits-flaws',
            'where' => "kind='Méritos'",
        ],
        'flaw' => [
            'table' => 'dim_merits_flaws',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/rules/merits-flaws',
            'where' => "kind='Defectos'",
        ],
        'merydef' => [
            'table' => 'dim_merits_flaws',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/rules/merits-flaws',
        ],
        'doc' => [
            'table' => 'fact_docs',
            'label' => 'title',
            'pretty' => 'pretty_id',
            'url' => '/documents',
        ],
        'system' => [
            'table' => 'dim_systems',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/systems',
        ],
        'breed' => [
            'table' => 'dim_breeds',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/systems/breeds',
        ],
        'auspice' => [
            'table' => 'dim_auspices',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/systems/auspices',
        ],
        'tribe' => [
            'table' => 'dim_tribes',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/systems/tribes',
        ],
    ];
}

function hg_mentions_item_url(mysqli $link, int $id, ?string $pretty = null): string {
    $itemPretty = $pretty ?: get_pretty_id($link, 'fact_items', $id);
    if (!$itemPretty) $itemPretty = (string)$id;

    $typePretty = '';
    if ($st = $link->prepare("SELECT t.pretty_id, t.id AS type_id FROM fact_items i LEFT JOIN dim_item_types t ON t.id = i.item_type_id WHERE i.id = ? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($rs && ($row = $rs->fetch_assoc())) {
            $typePretty = (string)($row['pretty_id'] ?? '');
            if ($typePretty === '' && isset($row['type_id'])) $typePretty = (string)$row['type_id'];
        }
        $st->close();
    }
    if ($typePretty === '') $typePretty = 'type';
    return '/inventory/' . rawurlencode($typePretty) . '/' . rawurlencode($itemPretty);
}

function hg_mentions_search(mysqli $link, string $type, string $q, int $limit = 12): array {
    $cfg = hg_mentions_config();
    if (!isset($cfg[$type])) return [];
    $c = $cfg[$type];
    $canonicalType = (string)($c['type'] ?? $type);
    $table = $c['table'];
    $labelCol = $c['label'];
    $prettyCol = $c['pretty'];
    $columns = hg_mentions_table_columns($link, $table);
    $hasLabel = isset($columns[strtolower($labelCol)]);
    $hasPretty = isset($columns[strtolower($prettyCol)]);
    if (!$hasLabel) return [];
    $where = $c['where'] ?? '';
    $searchCols = $c['search'] ?? [];
    if (empty($searchCols)) $searchCols = [$labelCol, $prettyCol];
    if ($hasPretty && !in_array($prettyCol, $searchCols, true)) $searchCols[] = $prettyCol;
    if (!in_array($labelCol, $searchCols, true)) $searchCols[] = $labelCol;
    $searchCols = array_values(array_filter($searchCols, function($col) use ($columns) {
        return isset($columns[strtolower((string)$col)]);
    }));
    if (empty($searchCols)) $searchCols = [$labelCol];

    $params = [];
    $types = '';
    $tableAlias = ($type === 'character') ? 't' : $table;
    $join = '';
    $extraSelect = '';
    if ($type === 'character') {
        $join .= " LEFT JOIN dim_chronicles dc ON dc.id = $tableAlias.chronicle_id";
        $extraSelect .= ", dc.name AS chronicle_name";
    }
    if ($type === 'breed' || $type === 'auspice' || $type === 'tribe') {
        $join .= " LEFT JOIN dim_systems ds ON ds.id = $tableAlias.system_id";
        $extraSelect .= ", ds.name AS system_name";
    }
    $prettySelect = $hasPretty ? "$tableAlias.`$prettyCol` AS pretty_id" : "'' AS pretty_id";
    $sql = "SELECT $tableAlias.id, $tableAlias.`$labelCol` AS label, $prettySelect$extraSelect FROM `$table` $tableAlias$join";
    $conds = [];
    if ($where !== '') $conds[] = $where;
    if ($q !== '') {
        $like = '%' . $q . '%';
        $orParts = [];
        foreach ($searchCols as $col) {
            $colRef = "$tableAlias.`$col`";
            $orParts[] = $colRef . " LIKE ?";
            $params[] = $like;
            $types .= 's';
        }
        $conds[] = '(' . implode(' OR ', $orParts) . ')';
    }
    if (!empty($conds)) $sql .= " WHERE " . implode(' AND ', $conds);
    $orderCol = "$tableAlias.`$labelCol`";
    $sql .= " ORDER BY $orderCol ASC LIMIT " . (int)$limit;

    $out = [];
    if ($st = $link->prepare($sql)) {
        if ($types !== '') $st->bind_param($types, ...$params);
        $st->execute();
        $rs = $st->get_result();
        while ($rs && ($row = $rs->fetch_assoc())) {
            $id = (int)$row['id'];
            $pretty = (string)($row['pretty_id'] ?? '');
            $label = (string)($row['label'] ?? '');
            $href = '';
            if (($c['special'] ?? '') === 'item') {
                $href = hg_mentions_item_url($link, $id, $pretty);
            } else {
                $href = pretty_url($link, $table, $c['url'], $id);
            }
            $item = [
                'id' => $id,
                'pretty_id' => $pretty,
                'label' => $label,
                'href' => $href,
                'type' => $canonicalType,
            ];
            if ($type === 'character') {
                $item['chronicle_name'] = (string)($row['chronicle_name'] ?? '');
            }
            if ($type === 'breed' || $type === 'auspice' || $type === 'tribe') {
                $item['system_name'] = (string)($row['system_name'] ?? '');
            }
            $out[] = $item;
        }
        $st->close();
    }

    return $out;
}

function hg_mentions_lookup(mysqli $link, string $type, string $value): ?array {
    $cfg = hg_mentions_config();
    if (!isset($cfg[$type])) return null;
    $c = $cfg[$type];
    $canonicalType = (string)($c['type'] ?? $type);
    $table = $c['table'];
    $labelCol = $c['label'];
    $prettyCol = $c['pretty'];
    $columns = hg_mentions_table_columns($link, $table);
    $hasLabel = isset($columns[strtolower($labelCol)]);
    $hasPretty = isset($columns[strtolower($prettyCol)]);
    if (!$hasLabel) return null;
    $where = $c['where'] ?? '';

    $id = null;
    if (preg_match('/^\d+$/', $value)) {
        $id = (int)$value;
    } else {
        $id = resolve_pretty_id($link, $table, $value);
    }
    if (!$id) return null;

    $prettySelect = $hasPretty ? "`$prettyCol` AS pretty_id" : "'' AS pretty_id";
    $sql = "SELECT id, `$labelCol` AS label, $prettySelect FROM `$table` WHERE id=? LIMIT 1";
    if ($where !== '') $sql = "SELECT id, `$labelCol` AS label, $prettySelect FROM `$table` WHERE id=? AND $where LIMIT 1";

    if ($st = $link->prepare($sql)) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $st->close();
        if ($row) {
            $pretty = (string)($row['pretty_id'] ?? '');
            $label = (string)($row['label'] ?? '');
            $href = '';
            if (($c['special'] ?? '') === 'item') {
                $href = hg_mentions_item_url($link, (int)$row['id'], $pretty);
            } else {
                $href = pretty_url($link, $table, $c['url'], (int)$row['id']);
            }
            return [
                'id' => (int)$row['id'],
                'pretty_id' => $pretty,
                'label' => $label,
                'href' => $href,
                'type' => $canonicalType,
            ];
        }
    }

    return null;
}

function hg_mentions_convert(mysqli $link, string $html): string {
    if ($html === '' || strpos($html, '[@') === false) return $html;

    return preg_replace_callback('/\\[@([a-z_]+):([^\\]\\s]+)\\]/i', function($m) use ($link) {
        $type = strtolower($m[1]);
        $value = $m[2];
        $info = hg_mentions_lookup($link, $type, $value);
        if (!$info) return $m[0];
        $href = htmlspecialchars($info['href'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8');
        $dataType = htmlspecialchars((string)($info['type'] ?? $type), ENT_QUOTES, 'UTF-8');
        return "<a href=\"{$href}\" class=\"hg-mention\" data-type=\"{$dataType}\" data-id=\"{$info['id']}\" target=\"_blank\">{$label}</a>";
    }, $html);
}
