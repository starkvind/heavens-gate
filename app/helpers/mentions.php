<?php
// Mention helpers: autocomplete + token conversion

require_once(__DIR__ . '/pretty.php');

function hg_mentions_config(): array {
    return [
        'character' => [
            'table' => 'fact_characters',
            'label' => 'nombre',
            'pretty' => 'pretty_id',
            'url' => '/characters',
            'search' => ['nombre', 'alias', 'nombregarou'],
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
            'label' => 'nombre',
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
            'where' => "tipo='Trasfondos'",
        ],
        'merit' => [
            'table' => 'dim_merits_flaws',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/rules/merits-flaws',
            'where' => "tipo='Méritos'",
        ],
        'flaw' => [
            'table' => 'dim_merits_flaws',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/rules/merits-flaws',
            'where' => "tipo='Defectos'",
        ],
        'merydef' => [
            'table' => 'dim_merits_flaws',
            'label' => 'name',
            'pretty' => 'pretty_id',
            'url' => '/rules/merits-flaws',
        ],
        'doc' => [
            'table' => 'fact_docs',
            'label' => 'titulo',
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
    if ($st = $link->prepare("SELECT t.pretty_id, t.id AS type_id FROM fact_items i LEFT JOIN dim_item_types t ON t.id = i.tipo WHERE i.id = ? LIMIT 1")) {
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        if ($rs && ($row = $rs->fetch_assoc())) {
            $typePretty = (string)($row['pretty_id'] ?? '');
            if ($typePretty === '' && isset($row['type_id'])) $typePretty = (string)$row['type_id'];
        }
        $st->close();
    }
    if ($typePretty === '') $typePretty = 'tipo';
    return '/inventory/' . rawurlencode($typePretty) . '/' . rawurlencode($itemPretty);
}

function hg_mentions_search(mysqli $link, string $type, string $q, int $limit = 12): array {
    $cfg = hg_mentions_config();
    if (!isset($cfg[$type])) return [];
    $c = $cfg[$type];
    $table = $c['table'];
    $labelCol = $c['label'];
    $prettyCol = $c['pretty'];
    $where = $c['where'] ?? '';
    $searchCols = $c['search'] ?? [];
    if (empty($searchCols)) $searchCols = [$labelCol, $prettyCol];
    if (!in_array($prettyCol, $searchCols, true)) $searchCols[] = $prettyCol;
    if (!in_array($labelCol, $searchCols, true)) $searchCols[] = $labelCol;

    $params = [];
    $types = '';
    $join = '';
    $extraSelect = '';
    if ($type === 'character') {
        $join = " LEFT JOIN dim_chronicles dc ON dc.id = t.cronica";
        $extraSelect = ", dc.name AS chronicle_name";
    }
    $tableAlias = ($type === 'character') ? 't' : $table;
    $sql = "SELECT $tableAlias.id, $tableAlias.`$labelCol` AS label, $tableAlias.`$prettyCol` AS pretty_id$extraSelect FROM `$table` $tableAlias$join";
    $conds = [];
    if ($where !== '') $conds[] = $where;
    if ($q !== '') {
        $like = '%' . $q . '%';
        $orParts = [];
        foreach ($searchCols as $col) {
            $orParts[] = "`$col` LIKE ?";
            $params[] = $like;
            $types .= 's';
        }
        $conds[] = '(' . implode(' OR ', $orParts) . ')';
    }
    if (!empty($conds)) $sql .= " WHERE " . implode(' AND ', $conds);
    $orderCol = ($type === 'character') ? "$tableAlias.`$labelCol`" : "`$labelCol`";
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
                'type' => $type,
            ];
            if ($type === 'character') {
                $item['chronicle_name'] = (string)($row['chronicle_name'] ?? '');
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
    $table = $c['table'];
    $labelCol = $c['label'];
    $prettyCol = $c['pretty'];
    $where = $c['where'] ?? '';

    $id = null;
    if (preg_match('/^\d+$/', $value)) {
        $id = (int)$value;
    } else {
        $id = resolve_pretty_id($link, $table, $value);
    }
    if (!$id) return null;

    $sql = "SELECT id, `$labelCol` AS label, `$prettyCol` AS pretty_id FROM `$table` WHERE id=? LIMIT 1";
    if ($where !== '') $sql = "SELECT id, `$labelCol` AS label, `$prettyCol` AS pretty_id FROM `$table` WHERE id=? AND $where LIMIT 1";

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
                'type' => $type,
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
        return "<a href=\"{$href}\" class=\"hg-mention\" data-type=\"{$type}\" data-id=\"{$info['id']}\" target=\"_blank\">{$label}</a>";
    }, $html);
}
