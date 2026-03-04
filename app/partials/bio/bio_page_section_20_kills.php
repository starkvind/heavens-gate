<?php
include_once(__DIR__ . '/../../helpers/character_avatar.php');

if (!isset($killsAsKiller) || !is_array($killsAsKiller) || count($killsAsKiller) === 0) {
    return;
}

echo "<div class='bio-kills-block'>";
// (" . (int)count($killsAsKiller) . ")
echo "<h4 class='bio-rel-group-title'>Personajes asesinados por {$bioName}</h4>";
echo "<div class='bio-kills-grid'>";

foreach ($killsAsKiller as $kill) {
    $victimId = (int)($kill['victim_id'] ?? 0);
    if ($victimId <= 0) continue;

    $victimName = (string)($kill['victim_name'] ?? ('Personaje #' . $victimId));
    $victimAlias = trim((string)($kill['victim_alias'] ?? ''));
    //$victimLabel = $victimAlias !== '' ? ($victimName . ' (' . $victimAlias . ')') : $victimName;
    $victimLabel = $victimName;
    $victimImg = hg_character_avatar_url((string)($kill['victim_image'] ?? ''), (string)($kill['victim_gender'] ?? ''));

    $typeRaw = trim((string)($kill['death_type'] ?? ''));
    $typeLabel = $typeRaw !== '' ? ucfirst($typeRaw) : 'Muerte';

    $dateRaw = trim((string)($kill['death_date'] ?? ''));
    if ($dateRaw === '') $dateRaw = trim((string)($kill['event_date'] ?? ''));
    $dateLabel = '';
    if ($dateRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) {
        $dt = date_create($dateRaw);
        $dateLabel = $dt ? date_format($dt, 'd-m-Y') : $dateRaw;
    } elseif ($dateRaw !== '') {
        $dateLabel = $dateRaw;
    }

    $meta = $typeLabel . ($dateLabel !== '' ? (' · ' . $dateLabel) : '');
    $hrefVictim = function_exists('pretty_url')
        ? pretty_url($link, 'fact_characters', '/characters/', $victimId)
        : ('/characters/?b=' . $victimId);

    echo "<a class='bio-kill-link hg-tooltip' href='" . htmlspecialchars((string)$hrefVictim, ENT_QUOTES, 'UTF-8') . "' target='_blank' data-tip='character' data-id='" . (int)$victimId . "'>";
    echo "  <div class='bio-kill-card'>";
    echo "      <img class='bio-kill-avatar' src='" . htmlspecialchars((string)$victimImg, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($victimName, ENT_QUOTES, 'UTF-8') . "'>";
    echo "      <span class='bio-kill-main'>" . htmlspecialchars($victimLabel, ENT_QUOTES, 'UTF-8') . "</span>";
    echo "      <span class='bio-kill-meta'>" . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . "</span>";
    echo "  </div>";
    echo "</a>";
}

echo "</div>";
echo "</div>";
