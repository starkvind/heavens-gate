<?php
require_once __DIR__ . '/../../domains/rules/queries.php';

$maneuverId = (int)hg_request_param($hgRequest, 'maneuver');
$maneuver = $maneuverId > 0 ? hg_rules_fetch_maneuver($link, $maneuverId) : null;
if (!$maneuver) return;

$availability = hg_rules_fetch_maneuver_availability($link, $maneuverId);
$availabilitySummary = implode('; ', hg_rules_format_maneuver_availability($availability));
$availabilityPills = [];
$systemWide = [];

foreach (($availability['systems'] ?? []) as $row) {
    $systemId = (int)($row['system_id'] ?? 0);
    $systemName = trim((string)($row['system_name'] ?? ''));
    if ($systemId <= 0 || $systemName === '') continue;

    $systemWide[$systemId] = true;
    $availabilityPills[] = [
        'href' => function_exists('pretty_url')
            ? pretty_url($link, 'dim_systems', '/systems', $systemId)
            : '/systems/' . $systemId,
        'label' => $systemName . ' · todas las Formas',
        'system_id' => $systemId,
    ];
}

foreach (($availability['forms'] ?? []) as $row) {
    $formId = (int)($row['form_id'] ?? 0);
    $systemId = (int)($row['system_id'] ?? 0);
    if ($formId <= 0 || $systemId <= 0 || isset($systemWide[$systemId])) continue;

    $formName = trim((string)($row['form'] ?? ''));
    $systemName = trim((string)($row['system_name'] ?? ''));
    if ($formName === '' || $systemName === '') continue;

    $labelParts = [$formName, $systemName];
    $applicability = trim((string)($row['applicability_name'] ?? ''));
    if ($applicability !== '') $labelParts[] = $applicability;

    $availabilityPills[] = [
        'href' => function_exists('pretty_url')
            ? pretty_url($link, 'dim_forms', '/systems/form', $formId)
            : '/systems/form/' . $formId,
        'label' => implode(' · ', $labelParts),
        'system_id' => $systemId,
    ];
}

$maneID = htmlspecialchars((string)$maneuver['id']);
$maneName = htmlspecialchars((string)$maneuver['name']);
$maneText = (string)($maneuver['text'] ?? '');
$maneRoll = htmlspecialchars((string)($maneuver['roll'] ?? ''));
$maneDiff = htmlspecialchars((string)($maneuver['difficulty'] ?? ''));
$maneDamg = htmlspecialchars((string)($maneuver['damage'] ?? ''));
$maneActi = htmlspecialchars((string)($maneuver['actions'] ?? ''));
$maneSist = htmlspecialchars((string)($maneuver['system_name'] ?? ''));
$maneSystemId = (int)($maneuver['system_id'] ?? 0);
$maneSystemHref = $maneSystemId > 0
    ? (function_exists('pretty_url')
        ? pretty_url($link, 'dim_systems', '/systems', $maneSystemId)
        : '/systems/' . $maneSystemId)
    : '';
$maneOrig = htmlspecialchars((string)($maneuver['origin_name'] ?? '-'));
if ($maneOrig === '') $maneOrig = '-';

$pageSect = 'Maniobra';
$pageTitle2 = $maneName;
setMetaFromPage($maneName . " | Maniobras | Heaven's Gate", meta_excerpt($maneText), null, 'article');
include("app/partials/main_nav_bar.php");

$maneImg = trim((string)($maneuver['image_url'] ?? ''));
$itemImg = 'img/inv/no-photo.webp';
if ($maneImg !== '') $itemImg = strpos($maneImg, '/') !== false ? $maneImg : 'img/maneuvers/' . $maneImg;

echo "<div class='power-card power-card--maneuver'><div class='power-card__banner'><span class='power-card__title'>{$maneName}</span></div><div class='power-card__body'><div class='power-card__media'><img class='power-card__img' src='" . htmlspecialchars($itemImg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "' alt='{$maneName}'/></div><div class='power-card__stats'>";
if ($maneActi !== '') echo "<div class='power-stat'><div class='power-stat__label'>Acciones</div><div class='power-stat__value'>{$maneActi}</div></div>";
if ($maneRoll !== '') echo "<div class='power-stat'><div class='power-stat__label'>Tirada</div><div class='power-stat__value'>{$maneRoll} ({$maneDiff})</div></div>";
if ($maneDamg !== '') echo "<div class='power-stat'><div class='power-stat__label'>Da&ntilde;o</div><div class='power-stat__value'>{$maneDamg}</div></div>";
if ($maneSist !== '') {
    $systemValue = $maneSystemHref !== ''
        ? "<a class='hg-maneuver-origin-link' href='" . htmlspecialchars($maneSystemHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'>{$maneSist}</a>"
        : $maneSist;
    echo "<div class='power-stat'><div class='power-stat__label'>Sistema de origen</div><div class='power-stat__value'>{$systemValue}</div></div>";
}
if ($maneOrig !== '') echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>{$maneOrig}</div></div>";
echo '</div></div>';

if ($maneText !== '') {
    echo "<div class='power-card__desc'><div class='power-card__desc-title'>Descripci&oacute;n</div><div class='power-card__desc-body'>{$maneText}</div></div>";
}

if (!empty($availabilityPills)) {
    $summaryAttr = htmlspecialchars('Usable por: ' . $availabilitySummary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<div class='hg-maneuver-availability' aria-label='{$summaryAttr}'>";
    echo "<div class='hg-maneuver-availability__title'>Usable por</div>";
    echo "<div class='hg-maneuver-availability__list'>";
    foreach ($availabilityPills as $index => $pill) {
        if ($index > 0) echo "<span class='hg-maneuver-availability__separator' aria-hidden='true'>,</span> ";
        $href = htmlspecialchars((string)$pill['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $label = htmlspecialchars((string)$pill['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $systemId = max(1, (int)($pill['system_id'] ?? 1));
        $systemHue = (($systemId * 137) + 23) % 360;
        echo "<a class='hg-maneuver-availability__pill' style='--hg-maneuver-system-hue: {$systemHue}' href='{$href}'>{$label}</a>";
    }
    echo '</div></div>';
}

echo '</div>';
?>