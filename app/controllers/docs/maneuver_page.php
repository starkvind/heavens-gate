<?php
require_once __DIR__ . '/../../domains/rules/queries.php';

$maneuverId = (int)hg_request_param($hgRequest, 'maneuver');
$maneuver = $maneuverId > 0 ? hg_rules_fetch_maneuver($link, $maneuverId) : null;
if (!$maneuver) return;

$maneID = htmlspecialchars((string)$maneuver['id']);
$maneName = htmlspecialchars((string)$maneuver['name']);
$maneText = (string)($maneuver['text'] ?? '');
$maneUser = htmlspecialchars((string)($maneuver['user'] ?? ''));
$maneRoll = htmlspecialchars((string)($maneuver['roll'] ?? ''));
$maneDiff = htmlspecialchars((string)($maneuver['difficulty'] ?? ''));
$maneDamg = htmlspecialchars((string)($maneuver['damage'] ?? ''));
$maneActi = htmlspecialchars((string)($maneuver['actions'] ?? ''));
$maneSist = htmlspecialchars((string)($maneuver['system_name'] ?? ''));
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
if ($maneUser !== '') echo "<div class='power-stat'><div class='power-stat__label'>Formas</div><div class='power-stat__value'>{$maneUser}</div></div>";
if ($maneRoll !== '') echo "<div class='power-stat'><div class='power-stat__label'>Tirada</div><div class='power-stat__value'>{$maneRoll} ({$maneDiff})</div></div>";
if ($maneDamg !== '') echo "<div class='power-stat'><div class='power-stat__label'>Da&ntilde;o</div><div class='power-stat__value'>{$maneDamg}</div></div>";
if ($maneSist !== '') echo "<div class='power-stat'><div class='power-stat__label'>Raza</div><div class='power-stat__value'>{$maneSist}</div></div>";
if ($maneOrig !== '') echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>{$maneOrig}</div></div>";
echo '</div></div>';
if ($maneText !== '') echo "<div class='power-card__desc'><div class='power-card__desc-title'>Descripci&oacute;n</div><div class='power-card__desc-body'>{$maneText}</div></div>";
echo '</div>';
?>