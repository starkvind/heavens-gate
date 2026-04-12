<?php
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../../helpers/public_response.php');

$conditionRaw = isset($_GET['b']) ? (string)$_GET['b'] : '';
$conditionId = 0;
if ($conditionRaw !== '') {
    if (preg_match('/^\d+$/', $conditionRaw)) {
        $conditionId = (int)$conditionRaw;
    } elseif (function_exists('resolve_pretty_id')) {
        $conditionId = (int)(resolve_pretty_id($link, 'dim_character_conditions', $conditionRaw) ?? 0);
    }
}

if (!$link || $conditionId <= 0) {
    hg_public_render_error(
        'Condición no disponible',
        'No se pudo cargar esta condición.',
        404,
        true
    );
    return;
}

if (!function_exists('condition_page_column_exists')) {
    function condition_page_column_exists(mysqli $db, string $table, string $column): bool {
        if ($st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
            $st->bind_param('ss', $table, $column);
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $st->close();
            return ((int)$count > 0);
        }
        return false;
    }
}

$queryCondition = "
    SELECT
        c.*,
        COALESCE(b.name, '') AS origin_name
    FROM dim_character_conditions c
    LEFT JOIN dim_bibliographies b ON b.id = c.bibliography_id
    WHERE c.id = ?
    LIMIT 1
";
$stmt = $link->prepare($queryCondition);
$stmt->bind_param('i', $conditionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows <= 0) {
    $stmt->close();
    hg_public_render_error(
        'Condición no encontrada',
        'La condición solicitada no existe o ya no está disponible.',
        404,
        true
    );
    return;
}

$condition = $result->fetch_assoc();
$conditionName = htmlspecialchars((string)$condition['name']);
$conditionCategory = htmlspecialchars((string)($condition['category'] ?? ''));
$conditionDescription = (string)($condition['description'] ?? '');
$conditionOriginName = htmlspecialchars((string)($condition['origin_name'] ?? ''));
$conditionMaxInstancesRaw = $condition['max_instances'] ?? null;
$conditionMaxInstancesText = ($conditionMaxInstancesRaw === null)
    ? 'Sin límite'
    : ((int)$conditionMaxInstancesRaw === 1 ? 'Única' : (string)((int)$conditionMaxInstancesRaw));

if ($conditionOriginName === '') {
    $conditionOriginName = '-';
}

if (!function_exists('sanitize_int_csv')) {
    function sanitize_int_csv($csv){
        $csv = (string)$csv;
        if (trim($csv) === '') return '';
        $parts = preg_split('/\s*,\s*/', trim($csv));
        $ints = [];
        foreach ($parts as $p) {
            if ($p === '') continue;
            if (preg_match('/^\d+$/', $p)) $ints[] = (string)(int)$p;
        }
        $ints = array_values(array_unique($ints));
        return implode(',', $ints);
    }
}

$excludeChronicles = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '';
$cronicaNotInSQL = ($excludeChronicles !== '') ? " AND c.chronicle_id NOT IN ($excludeChronicles) " : "";

$conditionEffects = [];
if ($stEffects = $link->prepare("
    SELECT
        bcct.id,
        bcct.modifier_value,
        COALESCE(bcct.description, '') AS effect_description,
        t.id AS trait_id,
        t.pretty_id AS trait_pretty_id,
        t.name AS trait_name
    FROM bridge_character_conditions_traits bcct
    JOIN dim_traits t ON t.id = bcct.trait_id
    WHERE bcct.condition_id = ?
    ORDER BY t.name ASC, bcct.id ASC
")) {
    $stEffects->bind_param('i', $conditionId);
    $stEffects->execute();
    $rsEffects = $stEffects->get_result();
    while ($r = $rsEffects->fetch_assoc()) {
        $conditionEffects[] = $r;
    }
    $stEffects->close();
}

$conditionOwners = [];
$characterKindSql = hg_character_kind_select($link, 'c');
$conditionActiveSql = condition_page_column_exists($link, 'bridge_characters_conditions', 'is_active')
    ? "AND (bcc.is_active = 1 OR bcc.is_active IS NULL)"
    : "";
if ($stOwners = $link->prepare("
    SELECT DISTINCT
        c.id,
        c.name,
        c.alias,
        c.image_url,
        c.gender,
        COALESCE(dcs.label, '') AS status,
        c.status_id,
        {$characterKindSql} AS character_kind
    FROM bridge_characters_conditions bcc
    JOIN fact_characters c ON c.id = bcc.character_id
    LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id
    WHERE bcc.condition_id = ?
      $conditionActiveSql
      $cronicaNotInSQL
    ORDER BY c.name ASC
")) {
    $stOwners->bind_param('i', $conditionId);
    $stOwners->execute();
    $rsOwners = $stOwners->get_result();
    while ($r = $rsOwners->fetch_assoc()) {
        $conditionOwners[] = $r;
    }
    $stOwners->close();
}

$pageSect = "Condición";
$pageTitle2 = $conditionName;
setMetaFromPage($conditionName . " | Condiciones | Heaven's Gate", meta_excerpt($conditionDescription), null, 'article');

include("app/partials/main_nav_bar.php");
echo '<link rel="stylesheet" href="/assets/css/hg-docs.css">';

ob_start();

echo "<div class='power-card power-card--merfla'>";
echo "  <div class='power-card__banner'>";
echo "    <span class='power-card__title'>{$conditionName}</span>";
echo "  </div>";

echo "  <div class='power-card__body'>";
echo "    <div class='power-card__media'>";
echo "      <img class='power-card__img power-card__img--framed' src='img/inv/no-photo.gif' alt='{$conditionName}'/>";
echo "    </div>";
echo "    <div class='power-card__stats'>";
if ($conditionCategory !== '') {
    echo "      <div class='power-stat'><div class='power-stat__label'>Categoría</div><div class='power-stat__value'>{$conditionCategory}</div></div>";
}
echo "      <div class='power-stat'><div class='power-stat__label'>Máx. repeticiones</div><div class='power-stat__value'>" . htmlspecialchars($conditionMaxInstancesText, ENT_QUOTES, 'UTF-8') . "</div></div>";
echo "      <div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>{$conditionOriginName}</div></div>";
echo "      <div class='power-stat'><div class='power-stat__label'>Efectos</div><div class='power-stat__value'>" . count($conditionEffects) . "</div></div>";
echo "    </div>";
echo "  </div>";

if (trim($conditionDescription) !== '') {
    echo "  <div class='power-card__desc'>";
    echo "    <div class='power-card__desc-title'>Descripción</div>";
    echo "    <div class='power-card__desc-body'>{$conditionDescription}</div>";
    echo "  </div>";
}

if (!empty($conditionEffects)) {
    echo "  <div class='power-card__desc'>";
    echo "    <div class='power-card__desc-title'>Efectos sobre rasgos</div>";
    echo "    <div class='power-card__desc-body'><ul>";
    foreach ($conditionEffects as $effect) {
        $traitName = htmlspecialchars((string)($effect['trait_name'] ?? ''));
        $traitSlug = (string)($effect['trait_pretty_id'] ?? '');
        if ($traitSlug === '') {
            $traitSlug = (string)((int)($effect['trait_id'] ?? 0));
        }
        $traitHref = '/rules/traits/' . rawurlencode($traitSlug);
        $modifier = (int)($effect['modifier_value'] ?? 0);
        $modifierText = $modifier > 0 ? ('+' . $modifier) : (string)$modifier;
        $effectDesc = trim((string)($effect['effect_description'] ?? ''));

        echo "<li>";
        echo "<a href='" . htmlspecialchars($traitHref, ENT_QUOTES, 'UTF-8') . "'>" . $traitName . "</a>: <strong>" . htmlspecialchars($modifierText, ENT_QUOTES, 'UTF-8') . "</strong>";
        if ($effectDesc !== '') {
            echo " <span>(" . htmlspecialchars($effectDesc, ENT_QUOTES, 'UTF-8') . ")</span>";
        }
        echo "</li>";
    }
    echo "    </ul></div>";
    echo "  </div>";
}

echo "</div>";

$infoHtml = ob_get_clean();
$hasOwners = count($conditionOwners) > 0;

if ($hasOwners) {
    include_once(__DIR__ . '/../../partials/owners_tabs_styles.php');
    hg_render_owner_tabs_styles(true, 28);

    echo "<div class='hg-tabs'>";
    echo "<button class='boton2 hgTabBtn' data-tab='info'>Información</button>";
    echo "<button class='boton2 hgTabBtn' data-tab='owners'>Afectados</button>";
    echo "</div>";

    echo "<section class='hg-tab-panel' data-tab='info'>{$infoHtml}</section>";

    echo "<section class='hg-tab-panel' data-tab='owners'>";
    echo "<div class='grupoBioClan'><div class='contenidoAfiliacion'>";
    foreach ($conditionOwners as $o) {
        $oid = (int)($o['id'] ?? 0);
        $name = (string)($o['name'] ?? '');
        $alias = (string)($o['alias'] ?? '');
        $href = pretty_url($link, 'fact_characters', '/characters', $oid);
        hg_render_character_avatar_tile([
            'href' => $href,
            'title' => $name,
            'name' => $name,
            'alias' => $alias,
            'character_id' => $oid,
            'image_url' => (string)($o['image_url'] ?? ''),
            'gender' => (string)($o['gender'] ?? ''),
            'status' => (string)($o['status'] ?? ''),
            'character_kind' => hg_character_kind_from_row($o),
            'target_blank' => true,
        ]);
    }
    echo "</div></div>";
    echo "</section>";
} else {
    echo $infoHtml;
}

$stmt->close();
?>
