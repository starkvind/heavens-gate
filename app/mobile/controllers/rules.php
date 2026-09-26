<?php
require_once __DIR__ . '/../../domains/rules/queries.php';
include_once __DIR__ . '/../../helpers/public_response.php';
include_once __DIR__ . '/../../helpers/character_avatar.php';

if (!function_exists('hg_mobile_rules_h')) {
    function hg_mobile_rules_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_rules_excerpt')) {
    function hg_mobile_rules_excerpt(string $html, int $max = 135): string
    {
        $text = trim(strip_tags($html));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        }
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}

if (!function_exists('hg_mobile_rules_url')) {
    function hg_mobile_rules_url(mysqli $db, string $table, string $base, int $id): string
    {
        return $id > 0 && function_exists('pretty_url')
            ? pretty_url($db, $table, $base, $id)
            : rtrim($base, '/') . '/' . $id;
    }
}

if (!function_exists('hg_mobile_rules_character_card')) {
    function hg_mobile_rules_character_card(mysqli $db, array $row): void
    {
        $id = (int)($row['id'] ?? 0);
        $name = (string)($row['name'] ?? $row['nombre'] ?? '');
        $alias = (string)($row['alias'] ?? '');
        $status = (string)($row['status'] ?? '');
        $value = (int)($row['value'] ?? 0);
        $image = function_exists('hg_character_avatar_url')
            ? hg_character_avatar_url((string)($row['image_url'] ?? ''), (string)($row['gender'] ?? ''))
            : '';
        $meta = [];
        if ($value > 0) $meta[] = $value . ($value === 1 ? ' punto' : ' puntos');
        if ($alias !== '') $meta[] = $alias;
        if ($status !== '') $meta[] = $status;
        $href = hg_mobile_rules_url($db, 'fact_characters', '/characters', $id);
        ?>
        <a class="hg-mobile-character-card" href="<?= hg_mobile_rules_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_rules_h($name . ' ' . $alias . ' ' . $status . ' ' . $value) ?>">
            <?php if ($image !== ''): ?><img src="<?= hg_mobile_rules_h($image) ?>" alt=""><?php else: ?><span class="hg-mobile-character-avatar" aria-hidden="true"></span><?php endif; ?>
            <span class="hg-mobile-character-main"><strong><?= hg_mobile_rules_h($name) ?></strong><span><?= hg_mobile_rules_h(implode(' · ', $meta)) ?></span></span>
        </a>
        <?php
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_rules', 'missing DB connection');
    hg_public_render_error('Contenido no disponible', 'No se pudo cargar esta sección.');
    return;
}

$route = hg_request_route($hgRequest);
$rulesHub = [
    ['Rasgos', '/rules/traits', 'Atributos, habilidades, trasfondos y otros rasgos numéricos.'],
    ['Méritos y Defectos', '/rules/merits-flaws', 'Ventajas, desventajas y rasgos especiales.'],
    ['Condiciones', '/rules/conditions', 'Estados, heridas, trastornos y efectos persistentes.'],
    ['Acciones', '/rules/actions', 'Tiradas básicas de Atributo + Habilidad.'],
    ['Personalidades', '/rules/archetypes', 'Naturaleza, conducta y arquetipos de interpretación.'],
    ['Maniobras de pelea', '/rules/maneuvers', 'Técnicas de combate y acciones especiales.'],
];

if ($route === 'rules') {
    $metaTitle = "Reglas | Heaven's Gate";
    $pageSect = 'Reglas';
    ?>
    <section class="hg-mobile-section"><h1>Reglas</h1></section>
    <section class="hg-mobile-section"><div class="hg-mobile-card-list">
        <?php foreach ($rulesHub as $item): ?><a class="hg-mobile-card" href="<?= hg_mobile_rules_h($item[1]) ?>"><strong><?= hg_mobile_rules_h($item[0]) ?></strong><span><?= hg_mobile_rules_h($item[2]) ?></span></a><?php endforeach; ?>
    </div></section>
    <?php
    return;
}

$configs = [
    'listarasgos' => ['kind'=>'traits','mode'=>'list','title'=>'Rasgos','table'=>'dim_traits','base'=>'/rules/traits'],
    'verrasgo' => ['kind'=>'traits','mode'=>'detail','title'=>'Rasgo','table'=>'dim_traits','base'=>'/rules/traits','param'=>'trait'],
    'listconditions' => ['kind'=>'conditions','mode'=>'list','title'=>'Condiciones','table'=>'dim_character_conditions','base'=>'/rules/conditions'],
    'vercondition' => ['kind'=>'conditions','mode'=>'detail','title'=>'Condición','table'=>'dim_character_conditions','base'=>'/rules/conditions','param'=>'condition'],
    'actions' => ['kind'=>'actions','mode'=>'list','title'=>'Acciones','table'=>'fact_actions','base'=>'/rules/actions'],
    'veraction' => ['kind'=>'actions','mode'=>'detail','title'=>'Acción','table'=>'fact_actions','base'=>'/rules/actions','param'=>'action'],
    'listamyd' => ['kind'=>'merits','mode'=>'list','title'=>'Méritos y Defectos','table'=>'dim_merits_flaws','base'=>'/rules/merits-flaws'],
    'vermyd' => ['kind'=>'merits','mode'=>'detail','title'=>'Mérito o defecto','table'=>'dim_merits_flaws','base'=>'/rules/merits-flaws','param'=>'merit_flaw'],
    'maneuver' => ['kind'=>'maneuvers','mode'=>'list','title'=>'Maniobras de pelea','table'=>'fact_combat_maneuvers','base'=>'/rules/maneuvers'],
    'vermaneu' => ['kind'=>'maneuvers','mode'=>'detail','title'=>'Maniobra','table'=>'fact_combat_maneuvers','base'=>'/rules/maneuvers','param'=>'maneuver'],
    'arquetip' => ['kind'=>'archetypes','mode'=>'list','title'=>'Arquetipos','table'=>'dim_archetypes','base'=>'/rules/archetypes'],
    'verarch' => ['kind'=>'archetypes','mode'=>'detail','title'=>'Arquetipo','table'=>'dim_archetypes','base'=>'/rules/archetypes','param'=>'archetype'],
];

$cfg = $configs[$route] ?? null;
if (!$cfg) {
    hg_public_render_not_found('Sección no disponible', 'Esta parte aún no tiene controlador móvil.');
    return;
}

$kind = $cfg['kind'];
$pageSect = $cfg['title'];
$metaTitle = $cfg['title'] . " | Heaven's Gate";

if ($cfg['mode'] === 'list') {
    $rows = [];
    if ($kind === 'traits') $rows = hg_rules_fetch_traits_table($link, '2,7') ?: [];
    elseif ($kind === 'conditions') $rows = hg_rules_fetch_conditions_table($link, '2,7') ?: [];
    elseif ($kind === 'actions') $rows = hg_rules_fetch_actions($link) ?: [];
    elseif ($kind === 'merits') $rows = hg_rules_fetch_merits($link) ?: [];
    elseif ($kind === 'maneuvers') $rows = hg_rules_fetch_maneuvers($link) ?: [];
    elseif ($kind === 'archetypes') $rows = hg_rules_fetch_archetypes($link) ?: [];

    $list = [];
    foreach ($rows as $row) {
        if ($kind === 'traits') {
            $list[] = ['id'=>(int)$row['trait_id'],'name'=>(string)$row['trait_name'],'meta'=>array_filter([(string)$row['trait_category'],(string)$row['trait_subcategory'],(string)$row['trait_origin']]),'body'=>'','slug'=>$row['trait_pretty_id'] ?: $row['trait_id'],'filter'=>(string)$row['trait_category']];
        } elseif ($kind === 'conditions') {
            $list[] = ['id'=>(int)$row['condition_id'],'name'=>(string)$row['condition_name'],'meta'=>array_filter([(string)$row['condition_category'],(string)$row['condition_origin']]),'body'=>'','slug'=>$row['condition_pretty_id'] ?: $row['condition_id']];
        } elseif ($kind === 'actions') {
            $list[] = ['id'=>(int)$row['id'],'name'=>(string)$row['name'],'meta'=>array_filter([(string)$row['category'],trim((string)$row['attribute_name'].' + '.(string)$row['skill_name']),(string)$row['origin_name']]),'body'=>'','slug'=>$row['pretty_id'] ?: $row['id']];
        } elseif ($kind === 'merits') {
            $list[] = ['id'=>(int)$row['merit_id'],'name'=>(string)$row['merit_name'],'meta'=>array_filter([(string)$row['merit_type'],(string)$row['merit_system'],(string)$row['merit_category'],(string)$row['merit_cost'],(string)$row['merit_origin']]),'body'=>'','slug'=>$row['merit_pretty_id'] ?: $row['merit_id']];
        } elseif ($kind === 'maneuvers') {
            $list[] = ['id'=>(int)$row['id'],'name'=>(string)$row['name'],'meta'=>array_filter([(string)($row['system_name'] ?? ''),(string)($row['roll'] ?? ''),(string)($row['difficulty'] ?? '')]),'body'=>(string)($row['text'] ?? ''),'slug'=>$row['pretty_id'] ?? $row['id']];
        } else {
            $list[] = ['id'=>(int)$row['arche_id'],'name'=>(string)$row['arche_name'],'meta'=>array_filter([(string)$row['arche_origin']]),'body'=>'','slug'=>$row['arche_pretty_id'] ?: $row['arche_id']];
        }
    }
    $filterValues = [];
    if ($kind === 'traits') {
        foreach ($list as $entry) if (!empty($entry['filter'])) $filterValues[$entry['filter']] = $entry['filter'];
        natcasesort($filterValues);
    }
    ?>
    <section class="hg-mobile-section"><h1><?= hg_mobile_rules_h($cfg['title']) ?></h1><p class="hg-mobile-muted"><?= number_format(count($list), 0, ',', '.') ?> elementos</p></section>
    <section class="hg-mobile-section"><div class="hg-mobile-card-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar">
        <?php if ($filterValues): ?><label class="hg-mobile-list-filter">Tipo de rasgo<select data-mobile-list-filter aria-label="Filtrar por tipo de rasgo"><option value="">Todos los tipos</option><?php foreach ($filterValues as $value): ?><option value="<?= hg_mobile_rules_h($value) ?>"><?= hg_mobile_rules_h($value) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <?php if (!$list): ?><p class="hg-mobile-muted">No hay elementos disponibles.</p><?php endif; ?>
        <?php foreach ($list as $entry):
            $href = rtrim($cfg['base'], '/') . '/' . rawurlencode((string)$entry['slug']);
            $search = $entry['name'] . ' ' . implode(' ', $entry['meta']) . ' ' . strip_tags($entry['body']);
        ?>
            <a class="hg-mobile-card" href="<?= hg_mobile_rules_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_rules_h($search) ?>"<?= !empty($entry['filter']) ? ' data-mobile-filter-value="' . hg_mobile_rules_h($entry['filter']) . '"' : '' ?>>
                <strong><?= hg_mobile_rules_h($entry['name']) ?></strong>
                <?php foreach ($entry['meta'] as $meta): ?><span><?= hg_mobile_rules_h($meta) ?></span><?php endforeach; ?>
                <?php $excerpt = hg_mobile_rules_excerpt($entry['body']); if ($excerpt !== ''): ?><span><?= hg_mobile_rules_h($excerpt) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div></section>
    <?php
    return;
}

$rawId = hg_request_param($hgRequest, $cfg['param']);
$id = preg_match('/^\d+$/', (string)$rawId) ? (int)$rawId : (int)(resolve_pretty_id($link, $cfg['table'], (string)$rawId) ?? 0);
if ($id <= 0) {
    hg_public_render_not_found($cfg['title'] . ' no encontrado', 'No existe el elemento solicitado.');
    return;
}

$row = null;
$owners = [];
$sections = [];
$facts = [];
$image = '';
$name = '';
if ($kind === 'traits') {
    $row = hg_rules_fetch_trait($link, $id);
    if ($row) {
        $name = (string)$row['name'];
        $facts = [['Tipo',$row['rule_kind'] ?? ''],['Clasificación',strlen((string)($row['classification'] ?? '')) >= 5 ? substr((string)$row['classification'],4) : ($row['classification'] ?? '')],['Origen',$row['origin_name'] ?? '']];
        $sections = [['Descripción',$row['description'] ?? ''],['Niveles',$row['levels'] ?? ''],['Poseído por',$row['posse'] ?? ''],['Especial',$row['special'] ?? '']];
        $owners = hg_rules_fetch_trait_owners($link, $id, '2,7');
    }
} elseif ($kind === 'conditions') {
    $row = hg_rules_fetch_condition($link, $id);
    if ($row) {
        $name = (string)$row['name'];
        $facts = [['Categoría',$row['category'] ?? ''],['Máx. repeticiones',$row['max_instances'] ?? ''],['Origen',$row['origin_name'] ?? '']];
        $sections = [['Descripción',$row['description'] ?? '']];
        $owners = hg_rules_fetch_condition_owners($link, $id, '2,7');
    }
} elseif ($kind === 'actions') {
    $row = hg_rules_fetch_action($link, $id);
    if ($row) {
        $name = (string)$row['name'];
        $facts = [['Categoría',$row['category'] ?? ''],['Tirada',trim((string)$row['attribute_name'].' + '.(string)$row['skill_name'])],['Dificultad',$row['difficulty_mode'] ?? ''],['Origen',$row['origin_name'] ?? '']];
        $sections = [['Descripción',$row['text'] ?? '']];
        $rawImage = trim((string)($row['image_url'] ?? '')); if ($rawImage !== '') $image = strpos($rawImage,'/') !== false ? $rawImage : 'img/actions/'.$rawImage;
    }
} elseif ($kind === 'merits') {
    $row = hg_rules_fetch_merit($link, $id);
    if ($row) {
        $name = (string)$row['name'];
        $facts = [['Tipo',$row['tipo'] ?? ''],['Sistema',$row['sistema'] ?? ''],['Categoría',$row['afiliacion'] ?? ''],['Coste',$row['coste'] ?? ''],['Origen',$row['origin_name'] ?? '']];
        $sections = [['Descripción',$row['descripcion'] ?? '']];
        $owners = hg_rules_fetch_merit_owners($link, $id, '2,7');
    }
} elseif ($kind === 'maneuvers') {
    $row = hg_rules_fetch_maneuver($link, $id);
    if ($row) {
        $name = (string)$row['name'];
        $facts = [['Sistema',$row['system_name'] ?? ''],['Acciones',$row['actions'] ?? ''],['Tirada',$row['roll'] ?? ''],['Dificultad',$row['difficulty'] ?? ''],['Daño',$row['damage'] ?? ''],['Origen',$row['origin_name'] ?? '']];
        $sections = [['Descripción',$row['text'] ?? '']];
        $rawImage = trim((string)($row['image_url'] ?? '')); if ($rawImage !== '') $image = strpos($rawImage,'/') !== false ? $rawImage : 'img/maneuvers/'.$rawImage;
    }
} elseif ($kind === 'archetypes') {
    $row = hg_rules_fetch_archetype($link, $id);
    if ($row) {
        $name = (string)$row['name'];
        $facts = [['Origen',$row['origin_name'] ?? '']];
        $sections = [['Descripción',$row['description'] ?? ''],['Fuerza de Voluntad',$row['willpower_text'] ?? '']];
        $owners = array_merge(hg_rules_fetch_archetype_owners($link, $id, 'nature', '2,7'), hg_rules_fetch_archetype_owners($link, $id, 'demeanor', '2,7'));
        $seen = []; $owners = array_values(array_filter($owners, static function(array $owner) use (&$seen): bool { $oid=(int)($owner['id']??0); if(isset($seen[$oid])) return false; $seen[$oid]=true; return true; }));
    }
}

if (!$row) {
    hg_public_render_not_found($cfg['title'] . ' no encontrado', 'No existe el elemento solicitado.');
    return;
}
$pageTitle2 = $name;
?>
<article class="hg-mobile-bio hg-mobile-rules-power">
    <nav class="hg-mobile-local-nav"><a href="<?= hg_mobile_rules_h($cfg['base']) ?>?view=mobile">Volver a <?= hg_mobile_rules_h(function_exists('mb_strtolower') ? mb_strtolower($cfg['title'], 'UTF-8') : strtolower($cfg['title'])) ?></a></nav>
    <section class="hg-mobile-section"><h1><?= hg_mobile_rules_h($name) ?></h1>
        <?php if ($image !== ''): ?><img class="hg-mobile-power-image" src="<?= hg_mobile_rules_h($image) ?>" alt=""><?php endif; ?>
        <div class="hg-mobile-fact-grid"><?php foreach ($facts as [$label,$value]): if (trim((string)$value)==='') continue; ?><div><span><?= hg_mobile_rules_h($label) ?></span><strong><?= hg_mobile_rules_h($value) ?></strong></div><?php endforeach; ?></div>
    </section>
    <?php foreach ($sections as [$label,$html]): if (trim(strip_tags((string)$html))==='') continue; ?><section class="hg-mobile-section hg-mobile-prose hg-mobile-rich-body"><h2><?= hg_mobile_rules_h($label) ?></h2><?= $html ?></section><?php endforeach; ?>
    <?php if ($owners): ?><section class="hg-mobile-section"><h2>Personajes relacionados</h2><div class="hg-mobile-character-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar personajes"><?php foreach ($owners as $owner) hg_mobile_rules_character_card($link, $owner); ?></div></section><?php endif; ?>
</article>
