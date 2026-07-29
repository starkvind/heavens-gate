<?php

include_once(__DIR__ . '/../../helpers/public_response.php');

$metaTitle = "Biografías | Heaven's Gate";
$metaDescription = "Tipos de biografia en version móvil.";
$pageSect = 'Biografías';

if (!function_exists('hg_mobile_types_h')) {
    function hg_mobile_types_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_character_types', 'missing DB connection');
    hg_public_render_error('Biografías no disponibles', 'No se pudo cargar el listado.');
    return;
}

$chronicleConditionP = function_exists('hg_mobile_chronicle_exclusion_condition') ? hg_mobile_chronicle_exclusion_condition('p') : 'p.chronicle_id NOT IN (2,7)';

$rows = [];
$sql = "
    SELECT ct.id, ct.kind, COUNT(DISTINCT p.id) AS total
    FROM dim_character_types ct
    LEFT JOIN fact_characters p
        ON p.character_type_id = ct.id
       AND {$chronicleConditionP}
    GROUP BY ct.id, ct.kind, ct.sort_order
    HAVING total > 0
    ORDER BY ct.sort_order, ct.kind
";
if ($res = $link->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
}
?>

<section class="hg-mobile-section">
    <h1>Biografías por tipo</h1>
    <div class="hg-mobile-card-list">
        <?php foreach ($rows as $row): ?>
            <?php
                $id = (int)($row['id'] ?? 0);
                $href = $id > 0 ? pretty_url($link, 'dim_character_types', '/characters/type', $id) : '/characters/types';
            ?>
            <a class="hg-mobile-card hg-mobile-card--split" href="<?= hg_mobile_types_h($href) ?>">
                <strong><?= hg_mobile_types_h($row['kind'] ?? '') ?></strong>
                <span><?= number_format((int)($row['total'] ?? 0), 0, ',', '.') ?> personajes</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

