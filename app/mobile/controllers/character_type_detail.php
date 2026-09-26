<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
require_once(__DIR__ . '/../../domains/characters/type_queries.php');

$metaTitle = "Biografías | Heaven's Gate";
$metaDescription = "Listado móvil de personajes por tipo.";
$pageSect = 'Biografías';

if (!function_exists('hg_mobile_type_h')) {
    function hg_mobile_type_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_type_url')) {
    function hg_mobile_type_url(mysqli $link, string $table, string $base, int $id): string
    {
        return $id > 0 && function_exists('pretty_url')
            ? pretty_url($link, $table, $base, $id)
            : rtrim($base, '/') . '/' . $id;
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_character_type_detail', 'missing DB connection');
    hg_public_render_error('Biografías no disponibles', 'No se pudo cargar el listado.');
    return;
}

$rawType = hg_request_param($hgRequest, 'character_type');
$typeId = 0;
if ($rawType !== '') {
    $resolved = resolve_pretty_id($link, 'dim_character_types', $rawType);
    $typeId = (int)($resolved ?? 0);
}

if ($typeId <= 0) {
    hg_public_render_not_found('Tipo no encontrado', 'No se pudo localizar el tipo de personaje solicitado.');
    return;
}

$type = hg_character_types_fetch_one($link, $typeId);
if (!$type) {
    hg_public_render_not_found('Tipo no encontrado', 'No se pudo localizar el tipo de personaje solicitado.');
    return;
}

$typeName = trim((string)($type['kind'] ?? 'Biografías'));
$metaTitle = $typeName . " | Biografías | Heaven's Gate";
$metaDescription = "Personajes del tipo " . $typeName . " en version móvil.";
$pageSect = $typeName;

$excludedChronicles = function_exists('hg_chronicle_scope_excluded_csv')
    ? hg_chronicle_scope_excluded_csv()
    : '2,7';
$characters = hg_character_types_fetch_characters($link, $typeId, $excludedChronicles);
if ($characters === null) {
    hg_public_log_error('mobile_character_type_detail', 'list query failed: ' . mysqli_error($link));
    $characters = [];
}

$groups = [];
foreach ($characters as $character) {
    $organizationId = (int)($character['organization_id'] ?? 0);
    $key = $organizationId > 0 ? (string)$organizationId : 'none';
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'id' => $organizationId,
            'name' => trim((string)($character['organization_name'] ?? '')) ?: 'Sin clan',
            'sort_order' => (int)($character['organization_sort_order'] ?? 999999),
            'items' => [],
        ];
    }
    $groups[$key]['items'][] = $character;
}

$groupKeys = array_keys($groups);
usort($groupKeys, static function (string $a, string $b) use ($groups): int {
    if ($a === 'none') return 1;
    if ($b === 'none') return -1;
    $sortA = (int)($groups[$a]['sort_order'] ?? 999999);
    $sortB = (int)($groups[$b]['sort_order'] ?? 999999);
    if ($sortA !== $sortB) return $sortA <=> $sortB;
    return strcasecmp((string)($groups[$a]['name'] ?? ''), (string)($groups[$b]['name'] ?? ''));
});
?>

<section class="hg-mobile-section">
    <nav class="hg-mobile-local-nav">
        <a href="/characters/types?view=mobile">Volver a tipos</a>
    </nav>

    <h1><?= hg_mobile_type_h($typeName) ?></h1>
    <p class="hg-mobile-muted"><?= number_format(count($characters), 0, ',', '.') ?> personajes</p>

    <?php if (empty($characters)): ?>
        <p class="hg-mobile-muted">No hay personajes para este tipo.</p>
    <?php endif; ?>

    <?php foreach ($groupKeys as $groupKey): ?>
        <?php $group = $groups[$groupKey]; ?>
        <details class="hg-mobile-details" open>
            <summary><?= hg_mobile_type_h($group['name'] ?? 'Sin clan') ?> · <?= count($group['items'] ?? []) ?></summary>
            <div class="hg-mobile-character-list">
                <?php foreach (($group['items'] ?? []) as $character): ?>
                    <?php
                        $characterId = (int)($character['id'] ?? 0);
                        $href = hg_mobile_type_url($link, 'fact_characters', '/characters', $characterId);
                        $avatar = hg_character_avatar_url((string)($character['image_url'] ?? ''), (string)($character['gender'] ?? ''));
                    ?>
                    <a class="hg-mobile-character-card" href="<?= hg_mobile_type_h($href) ?>">
                        <?php if ($avatar !== ''): ?>
                            <img src="<?= hg_mobile_type_h($avatar) ?>" alt="">
                        <?php else: ?>
                            <span class="hg-mobile-character-avatar" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="hg-mobile-character-main">
                            <strong><?= hg_mobile_type_h($character['name'] ?? '') ?></strong>
                            <?php if (!empty($character['alias'])): ?>
                                <em><?= hg_mobile_type_h($character['alias']) ?></em>
                            <?php endif; ?>
                            <?php if (!empty($character['concept'])): ?>
                                <span><?= hg_mobile_type_h($character['concept']) ?></span>
                            <?php endif; ?>
                            <small>
                                <?= hg_mobile_type_h($character['status_label'] ?? '') ?>
                                <?php if (!empty($character['pack_name'])): ?>
                                    - <?= hg_mobile_type_h($character['pack_name']) ?>
                                <?php endif; ?>
                            </small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endforeach; ?>
</section>
