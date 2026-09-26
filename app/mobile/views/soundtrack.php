<?php
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

