<?php

include_once(__DIR__ . '/../../helpers/public_response.php');

$metaTitle = "Banda sonora | Heaven's Gate";
$metaDescription = 'Banda sonora móvil de Heaven\'s Gate.';
$pageSect = 'Banda sonora';

if (!function_exists('hg_mobile_ost_h')) {
    function hg_mobile_ost_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_ost_youtube_id')) {
    function hg_mobile_ost_youtube_id(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
            return $url;
        }
        if (preg_match('#(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtube\.com/shorts/|youtu\.be/)([A-Za-z0-9_-]{11})#i', $url, $m)) {
            return (string)$m[1];
        }
        if (preg_match('/[?&]v=([A-Za-z0-9_-]{11})/i', $url, $m)) {
            return (string)$m[1];
        }
        return '';
    }
}

if (!function_exists('hg_mobile_ost_watch_url')) {
    function hg_mobile_ost_watch_url(string $id): string
    {
        return $id !== '' ? ('https://www.youtube.com/watch?v=' . rawurlencode($id) . '&referrer=heavensgate') : '';
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_soundtrack', 'missing DB connection');
    hg_public_render_error('Banda sonora no disponible', 'No se pudo cargar la banda sonora.');
    return;
}

$songs = [];
$sql = "SELECT id, context_title, artist, youtube_url, title, added_at
        FROM dim_soundtracks
        ORDER BY context_title ASC, title ASC, id ASC";
if ($res = $link->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $youtubeId = hg_mobile_ost_youtube_id((string)($row['youtube_url'] ?? ''));
        $row['youtube_id'] = $youtubeId;
        $row['youtube_watch_url'] = hg_mobile_ost_watch_url($youtubeId);
        $songs[] = $row;
    }
    $res->free();
} else {
    hg_public_log_error('mobile_soundtrack', 'list query failed: ' . mysqli_error($link));
    hg_public_render_error('Banda sonora no disponible', 'No se pudo cargar el listado musical.');
    return;
}
?>

<section class="hg-mobile-section hg-mobile-ost-head">
    <h1>Banda sonora</h1>
    <p class="hg-mobile-muted"><?= number_format(count($songs), 0, ',', '.') ?> canciones</p>
</section>

<section class="hg-mobile-section hg-mobile-ost-player" data-mobile-ost-player hidden>
    <div class="hg-mobile-ost-player-head">
        <div>
            <strong data-mobile-ost-player-title>Reproductor</strong>
            <span data-mobile-ost-player-subtitle></span>
        </div>
        <button type="button" data-mobile-ost-close aria-label="Cerrar reproductor">Cerrar</button>
    </div>
    <div class="hg-mobile-ost-frame">
        <iframe
            data-mobile-ost-frame
            title="YouTube"
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen></iframe>
    </div>
    <a class="hg-mobile-ost-external" href="#" target="_blank" rel="noopener noreferrer" data-mobile-ost-external>Ver en YouTube</a>
</section>

<section class="hg-mobile-section">
    <div class="hg-mobile-card-list hg-mobile-ost-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar cancion, artista o contexto" data-empty-text="No hay canciones con ese filtro.">
        <?php if (empty($songs)): ?>
            <p class="hg-mobile-muted">No hay canciones disponibles.</p>
        <?php endif; ?>
        <?php foreach ($songs as $song): ?>
            <?php
                $title = trim((string)($song['title'] ?? ''));
                $artist = trim((string)($song['artist'] ?? ''));
                $context = trim((string)($song['context_title'] ?? ''));
                $youtubeId = (string)($song['youtube_id'] ?? '');
                $watchUrl = (string)($song['youtube_watch_url'] ?? '');
                $searchText = trim($title . ' ' . $artist . ' ' . $context);
            ?>
            <article class="hg-mobile-card hg-mobile-ost-card" data-mobile-item data-mobile-search="<?= hg_mobile_ost_h($searchText) ?>">
                <div class="hg-mobile-ost-copy">
                    <strong><?= hg_mobile_ost_h($title !== '' ? $title : '(Sin título)') ?></strong>
                    <?php if ($artist !== ''): ?><span><?= hg_mobile_ost_h($artist) ?></span><?php endif; ?>
                    <?php if ($context !== ''): ?><small><?= hg_mobile_ost_h($context) ?></small><?php endif; ?>
                </div>
                <div class="hg-mobile-ost-actions">
                    <?php if ($youtubeId !== ''): ?>
                        <button
                            type="button"
                            data-mobile-ost-play
                            data-youtube-id="<?= hg_mobile_ost_h($youtubeId) ?>"
                            data-title="<?= hg_mobile_ost_h($title !== '' ? $title : '(Sin título)') ?>"
                            data-subtitle="<?= hg_mobile_ost_h(trim($artist . ($context !== '' ? ' | ' . $context : ''))) ?>"
                            data-watch-url="<?= hg_mobile_ost_h($watchUrl) ?>">Reproducir</button>
                    <?php else: ?>
                        <span class="hg-mobile-muted">Sin video</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<script>
(function () {
    function qs(selector) {
        return document.querySelector(selector);
    }

    function escapeYoutubeId(value) {
        var text = String(value || '').trim();
        return /^[A-Za-z0-9_-]{11}$/.test(text) ? text : '';
    }

    document.addEventListener('click', function (event) {
        var play = event.target.closest('[data-mobile-ost-play]');
        if (play) {
            var id = escapeYoutubeId(play.getAttribute('data-youtube-id'));
            if (!id) return;

            var player = qs('[data-mobile-ost-player]');
            var frame = qs('[data-mobile-ost-frame]');
            var title = qs('[data-mobile-ost-player-title]');
            var subtitle = qs('[data-mobile-ost-player-subtitle]');
            var external = qs('[data-mobile-ost-external]');
            if (!player || !frame) return;

            frame.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0';
            if (title) title.textContent = play.getAttribute('data-title') || 'Reproductor';
            if (subtitle) subtitle.textContent = play.getAttribute('data-subtitle') || '';
            if (external) external.href = play.getAttribute('data-watch-url') || ('https://www.youtube.com/watch?v=' + encodeURIComponent(id));
            player.hidden = false;
            player.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        var close = event.target.closest('[data-mobile-ost-close]');
        if (close) {
            var player = qs('[data-mobile-ost-player]');
            var frame = qs('[data-mobile-ost-frame]');
            if (frame) frame.src = '';
            if (player) player.hidden = true;
        }
    });
})();
</script>