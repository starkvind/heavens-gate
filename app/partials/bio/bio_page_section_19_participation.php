<?php
require_once(__DIR__ . '/../../domains/characters/detail_queries.php');

$character_id = isset($characterId) ? (int)$characterId : 0;
$eventosParticipacion = [];

foreach (hg_characters_fetch_participation_events($link, $character_id, 24) as $row) {
    $eventId = (int)($row['id'] ?? 0);
    $slug = trim((string)($row['pretty_id'] ?? ''));
    if ($slug === '') {
        $slug = (string)$eventId;
    }
    $eventHref = '/timeline/event/' . rawurlencode($slug);

    $eventDateRaw = trim((string)($row['event_date'] ?? ''));
    $eventDateFmt = '-';
    if ($eventDateRaw !== '' && $eventDateRaw !== '0000-00-00') {
        // Las fechas de la cronologia pueden quedar fuera del rango
        // Unix; no las conviertas a timestamp para mostrarlas.
        if (preg_match('/^(\d+)-(\d{1,2})-(\d{1,2})(?:\s.*)?$/', $eventDateRaw, $dateParts)) {
            $eventDateFmt = sprintf('%02d-%02d-%s', (int)$dateParts[3], (int)$dateParts[2], $dateParts[1]);
        } else {
            $eventDateFmt = $eventDateRaw;
        }
    }

    $eventosParticipacion[] = [
        'id' => $eventId,
        'title' => (string)($row['title'] ?? ''),
        'type_name' => (string)($row['type_name'] ?? 'Evento'),
        'date' => $eventDateFmt,
        'href' => $eventHref,
    ];
}
?>

<?php if (!empty($eventosParticipacion)): ?>
    <br />
<div class="listaParticipacion">
    <fieldset class='grupoBioClan bioChaptersSeasonFieldset'>
        <legend class='bioPowerTitle bioChaptersSeasonLegend'>&nbsp;Eventos relacionados (<?= (int)count($eventosParticipacion) ?>)&nbsp;</legend>
        <div class='capitulosTemporada'>
            <?php foreach ($eventosParticipacion as $ev):
                $eventTitle = trim((string)($ev['title'] ?? ''));
                if ($eventTitle === '') $eventTitle = 'Evento';
                $eventType = trim((string)($ev['type_name'] ?? 'Evento'));
                $eventDate = trim((string)($ev['date'] ?? '-'));
                //$eventLabel = '[' . $eventType . '] ' . $eventTitle;
                $eventLabel = $eventTitle;
            ?>
            <a class='bioChapterLink hg-tooltip' href='<?= htmlspecialchars((string)$ev['href'], ENT_QUOTES, 'UTF-8') ?>' target='_blank' data-tip='event' data-id='<?= (int)$ev['id'] ?>'>
                <div class='bioSheetPower bioChapterEntry'>
                    <span class='bioEventTitle'><?= htmlspecialchars($eventLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <div class='bioChapterDate'><?= htmlspecialchars($eventDate, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </fieldset>
</div>
<?php endif; ?>
