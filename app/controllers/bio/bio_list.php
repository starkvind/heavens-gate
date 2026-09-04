<?php
$bioListTitle = html_entity_decode('Biograf&iacute;as | Heaven\'s Gate', ENT_QUOTES | ENT_HTML5, 'UTF-8');
$bioListDescription = html_entity_decode('Listado de biograf&iacute;as y personajes.', ENT_QUOTES | ENT_HTML5, 'UTF-8');
setMetaFromPage($bioListTitle, $bioListDescription, '/img/og/og_image_bio.webp', 'website');
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-main.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-main.css">';
}
include_once(__DIR__ . '/../../helpers/public_response.php');

if (!$link) {
    hg_public_log_error('bio_list', 'missing DB connection');
    hg_public_render_error('Biografias no disponibles', 'No se pudo cargar el listado de biografias en este momento.');
    return;
}

if (!function_exists('hg_bio_list_h')) {
    function hg_bio_list_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_bio_list_sanitize_int_csv')) {
    function hg_bio_list_sanitize_int_csv($csv): string
    {
        $parts = preg_split('/\s*,\s*/', trim((string)$csv));
        $ints = [];
        foreach ($parts as $part) {
            if (preg_match('/^\d+$/', (string)$part)) $ints[] = (string)(int)$part;
        }
        return implode(',', array_values(array_unique($ints)));
    }
}

if (!function_exists('hg_bio_list_has_column')) {
    function hg_bio_list_has_column(mysqli $link, string $table, string $column): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') return false;
        $result = $link->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if (!$result) return false;
        $exists = $result->num_rows > 0;
        $result->free();
        return $exists;
    }
}
if (!function_exists('hg_bio_list_collect_types')) {
    function hg_bio_list_collect_types(mysqli $link, array $types, string $typeColumn, string $chronicleExclusion): ?array
    {
        $sql = "
            SELECT COUNT(DISTINCT p.id) AS total, MIN(NULLIF(p.image_url, '')) AS representative_image_url
            FROM fact_characters p
            WHERE p.`{$typeColumn}` = ? {$chronicleExclusion}
        ";
        $stmt = $link->prepare($sql);
        if (!$stmt) return null;

        $cards = [];
        foreach ($types as $type) {
            $typeId = (int)($type['id'] ?? 0);
            if ($typeId <= 0) continue;
            $stmt->bind_param('i', $typeId);
            if (!$stmt->execute()) continue;
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            if ($result) $result->free();
            $total = (int)($row['total'] ?? 0);
            if ($total <= 0) continue;
            $image = trim((string)($type['image_url'] ?? ''));
            if ($image === '') $image = trim((string)($row['representative_image_url'] ?? ''));
            if (strpos($image, '/public/') === 0) $image = substr($image, 7);
            if ($image === '') $image = '/img/og/og_image_bio.webp';
            $cards[] = [
                'id' => $typeId,
                'name' => (string)($type['name'] ?? ''),
                'total' => $total,
                'image_url' => $image,
                'description' => trim((string)($type['description'] ?? '')),
            ];
        }
        $stmt->close();
        return $cards;
    }
}

$excludeChronicles = isset($excludeChronicles) ? hg_bio_list_sanitize_int_csv($excludeChronicles) : '';
$chronicleExclusion = $excludeChronicles !== '' ? " AND p.chronicle_id NOT IN ({$excludeChronicles})" : '';
$types = [];
$hasTypeImage = hg_bio_list_has_column($link, 'dim_character_types', 'image_url');
$hasTypeDescription = hg_bio_list_has_column($link, 'dim_character_types', 'description');
$typeImageSelect = $hasTypeImage ? ", COALESCE(image_url, '') AS image_url" : ", '' AS image_url";
$typeDescriptionSelect = $hasTypeDescription ? ", COALESCE(description, '') AS description" : ", '' AS description";

if ($result = $link->query("SELECT id, kind {$typeImageSelect} {$typeDescriptionSelect} FROM dim_character_types ORDER BY sort_order, kind")) {
    while ($row = $result->fetch_assoc()) {
        $types[] = [
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['kind'] ?? ''),
            'image_url' => (string)($row['image_url'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
        ];
    }
    $result->free();
} else {
    hg_public_log_error('bio_list', 'type query failed: ' . $link->error);
    hg_public_render_error('Biografias no disponibles', 'No se pudo cargar el listado de biografias en este momento.');
    return;
}

$typeCards = [];
foreach (['character_type_id', 'kind', 'tipo'] as $typeColumn) {
    $candidateCards = hg_bio_list_collect_types($link, $types, $typeColumn, $chronicleExclusion);
    if ($candidateCards === null) continue;
    if (!empty($candidateCards)) {
        $typeCards = $candidateCards;
        break;
    }
}

include('app/partials/main_nav_bar.php');
?>
<div class="chron-detail">
    <section class="chron-box">
        <div class="chron-box-head">
            <h2>Biograf&iacute;as por tipo</h2>
            <p>Consulta aqu&iacute; los personajes de Heaven's Gate agrupados por su funci&oacute;n en la historia.</p>
        </div>

        <?php if (empty($typeCards)): ?>
            <p class="texti chron-empty">No hay tipos de personaje disponibles.</p>
        <?php else: ?>
            <div class="chron-grid">
                <?php foreach ($typeCards as $type): ?>
                    <?php
                    $typeId = (int)$type['id'];
                    $typeName = (string)$type['name'];
                    $characterCount = (int)$type['total'];
                    $typeDescription = trim((string)($type['description'] ?? ''));
                    if ($typeDescription === '') $typeDescription = 'Personajes clasificados como ' . $typeName . '.';
                    $href = pretty_url($link, 'dim_character_types', '/characters/type', $typeId);
                    ?>
                    <a class="chron-card" href="<?= hg_bio_list_h($href) ?>" title="<?= hg_bio_list_h($typeName) ?>">
                        <img src="<?= hg_bio_list_h($type['image_url']) ?>" alt="<?= hg_bio_list_h($typeName) ?>">
                        <div class="chron-card-body">
                            <h3><?= hg_bio_list_h($typeName) ?></h3>
                            <p><?= hg_bio_list_h($typeDescription) ?></p>
                            <div class="chron-card-meta">
                                <span><?= number_format($characterCount, 0, ',', '.') ?> <?= $characterCount === 1 ? 'personaje' : 'personajes' ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
