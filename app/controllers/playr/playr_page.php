<?php
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../domains/players/queries.php');

if (!$link) {
    hg_public_log_error('playr_page', 'missing DB connection');
    hg_public_render_error('Jugador no disponible', 'No se pudo cargar la ficha del jugador en este momento.');
    return;
}

if (!isset($hgRequest) || !is_array($hgRequest)) {
    hg_public_log_error('playr_page', 'missing request context');
    hg_public_render_error('Jugador no disponible', 'No se pudo interpretar la solicitud del jugador.');
    return;
}

$pjRaw = hg_request_param($hgRequest, 'player');
$pjId = resolve_pretty_id($link, 'dim_players', $pjRaw) ?? 0;

if ($pjId <= 0) {
    hg_public_render_not_found('Jugador no encontrado', 'El jugador solicitado no esta disponible en el catalogo.', true);
    return;
}

$player = hg_players_fetch_player($link, $pjId);
if ($player === false) {
    hg_public_log_error('playr_page', 'player query failed: ' . mysqli_error($link));
    hg_public_render_error('Jugador no disponible', 'No se pudo cargar la ficha del jugador en este momento.');
    return;
}
if ($player === null) {
    hg_public_render_not_found('Jugador no encontrado', 'El jugador solicitado no esta disponible en el catalogo.', true);
    return;
}

$namePJ = htmlspecialchars((string)($player['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$surnamePJ = htmlspecialchars((string)($player['surname'] ?? ''), ENT_QUOTES, 'UTF-8');
$descPJ = (string)($player['description'] ?? '');
$picPJ = (string)($player['picture'] ?? '');
if ($picPJ === '') {
    $picPJ = 'img/player/sinfoto.webp';
}

$pageSect = "Jugador";
$pageTitle2 = trim($namePJ . " " . $surnamePJ);
setMetaFromPage($pageTitle2 . " | Jugadores | Heaven's Gate", meta_excerpt($descPJ), $picPJ, 'article');
include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-playr.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-playr.css">';
}

$chronicleScope = hg_players_normalize_chronicle_csv($excludeChronicles ?? '');
if ($chronicleScope === '') {
    $chronicleScope = '2,7';
}

$characters = hg_players_fetch_characters($link, $pjId, $chronicleScope);
if ($characters === false) {
    hg_public_log_error('playr_page', 'characters query failed: ' . mysqli_error($link));
    hg_public_render_error('Jugador no disponible', 'No se pudieron cargar los personajes relacionados en este momento.');
    return;
}
?>

<div class="player-layout">
    <section class="player-card">
        <div class="player-photo">
            <img src="<?= htmlspecialchars($picPJ, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $pageTitle2 ?>">
        </div>
        <div class="player-meta">
            <h2><?= $pageTitle2 ?></h2>
            <p><?= $descPJ ?></p>
        </div>
    </section>

    <section class="player-characters">
        <p class="player-characters-title">Personajes de <?= $namePJ ?></p>

        <?php if (count($characters) > 0): ?>
            <div class="grupoBioClan">
                <div class="contenidoAfiliacion">
                    <?php foreach ($characters as $char): ?>
                        <?php
                            $charId = (int)($char['id'] ?? 0);
                            $charName = (string)($char['name'] ?? '');
                            $charAliasRaw = (string)($char['alias'] ?? '');
                            $charAlias = $charAliasRaw !== '' ? $charAliasRaw : (string)($char['name'] ?? '');
                            $charHref = pretty_url($link, 'fact_characters', '/characters', $charId);
                        ?>
                        <?php hg_render_character_avatar_tile([
                            'href' => $charHref,
                            'title' => $charName,
                            'name' => $charName,
                            'alias' => $charAlias,
                            'character_id' => $charId,
                            'image_url' => (string)($char['image_url'] ?? ''),
                            'gender' => (string)($char['gender'] ?? ''),
                            'status' => (string)($char['status'] ?? ''),
                            'character_kind' => hg_character_kind_from_row($char),
                        ]); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <p class="player-counter">Personajes: <?= count($characters) ?></p>
        <?php else: ?>
            <p class="player-empty">Este jugador no tiene personajes publicados.</p>
        <?php endif; ?>
    </section>
</div>