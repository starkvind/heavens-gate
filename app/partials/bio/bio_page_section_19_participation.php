<?php
$character_id = isset($characterId)
    ? (int)$characterId
    : (isset($_GET['b']) ? (int)$_GET['b'] : 0);

$eventosParticipacion = [];

if ($character_id > 0) {
    $query_eventos_pj = "
        SELECT
            e.id,
            e.pretty_id,
            e.title,
            e.event_date,
            COALESCE(t.name, 'Evento') AS type_name
        FROM bridge_timeline_events_characters bec
        INNER JOIN fact_timeline_events e ON e.id = bec.event_id
        LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id
        WHERE bec.character_id = ?
        ORDER BY
            CASE WHEN e.event_date = '0000-00-00' OR e.event_date IS NULL THEN 1 ELSE 0 END ASC,
            e.event_date ASC,
            e.id ASC
        LIMIT 24
    ";

    $stmtEv = $link->prepare($query_eventos_pj);
    if ($stmtEv) {
        $stmtEv->bind_param("i", $character_id);
        $stmtEv->execute();
        $resultEv = $stmtEv->get_result();
        while ($resultEv && ($row = $resultEv->fetch_assoc())) {
            $eventId = (int)($row['id'] ?? 0);
            $slug = trim((string)($row['pretty_id'] ?? ''));
            if ($slug === '') {
                $slug = (string)$eventId;
            }
            $eventHref = '/timeline/event/' . rawurlencode($slug);

            $eventDateRaw = trim((string)($row['event_date'] ?? ''));
            $eventDateFmt = '-';
            if ($eventDateRaw !== '' && $eventDateRaw !== '0000-00-00') {
                $ts = strtotime($eventDateRaw);
                if ($ts !== false) {
                    $eventDateFmt = date('d-m-Y', $ts);
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
        $stmtEv->close();
    }
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
