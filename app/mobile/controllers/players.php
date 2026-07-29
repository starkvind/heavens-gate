<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
include_once(__DIR__ . '/../helpers/chronicle_scope.php');

$metaTitle = "Jugadores | Heaven's Gate";
$metaDescription = 'Catálogo móvil de jugadores.';
$pageSect = 'Jugadores';

if (!function_exists('hg_mobile_player_h')) {
    function hg_mobile_player_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_player_url')) {
    function hg_mobile_player_url(mysqli $link, int $id): string
    {
        return $id > 0 && function_exists('pretty_url') ? pretty_url($link, 'dim_players', '/players', $id) : ('/players/' . $id);
    }
}

if (!function_exists('hg_mobile_player_resolve_id')) {
    function hg_mobile_player_resolve_id(mysqli $link, string $raw): int
    {
        $raw = trim(rawurldecode($raw));
        if ($raw === '') return 0;
        if (preg_match('/^\d+$/', $raw)) return (int)$raw;
        if (function_exists('resolve_pretty_id')) {
            $resolved = resolve_pretty_id($link, 'dim_players', $raw);
            if ((int)$resolved > 0) return (int)$resolved;
        }
        return 0;
    }
}

if (!function_exists('hg_mobile_player_excerpt')) {
    function hg_mobile_player_excerpt(string $text, int $max = 130): string
    {
        $text = trim(strip_tags($text));
        if ($text === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '...' : $text;
        }
        return strlen($text) > $max ? substr($text, 0, $max) . '...' : $text;
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_players', 'missing DB connection');
    hg_public_render_error('Jugadores no disponibles', 'No se pudo cargar el catalogo de jugadores.');
    return;
}

$rawPlayer = trim((string)($_GET['b'] ?? ''));
$playerId = $rawPlayer !== '' ? hg_mobile_player_resolve_id($link, $rawPlayer) : 0;
$chronicleJoin = hg_mobile_chronicle_exclusion_and('c');

if ($playerId <= 0) {
    $players = [];
    $sql = "
        SELECT
            p.id AS player_id,
            COALESCE(p.pretty_id, '') AS player_pretty_id,
            COALESCE(p.name, '') AS player_name,
            COALESCE(p.surname, '') AS player_surname,
            COALESCE(p.picture, '') AS player_picture,
            COALESCE(p.description, '') AS player_description,
            COUNT(DISTINCT c.id) AS player_characters
        FROM dim_players p
        LEFT JOIN fact_characters c ON c.player_id = p.id {$chronicleJoin}
        WHERE p.show_in_catalog = 1
        GROUP BY p.id, p.pretty_id, p.name, p.surname, p.picture, p.description
        ORDER BY p.name ASC, p.surname ASC, p.id ASC
    ";
    if ($res = $link->query($sql)) {
        while ($row = $res->fetch_assoc()) $players[] = $row;
        $res->free();
    } else {
        hg_public_log_error('mobile_players', 'list query failed: ' . mysqli_error($link));
        hg_public_render_error('Jugadores no disponibles', 'No se pudo cargar el listado de jugadores.');
        return;
    }
    ?>
    <section class="hg-mobile-section">
        <h1>Jugadores</h1>
        <p class="hg-mobile-muted"><?= number_format(count($players), 0, ',', '.') ?> jugadores publicados</p>
    </section>

    <section class="hg-mobile-section">
        <div class="hg-mobile-card-list hg-mobile-player-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar jugador" data-empty-text="No hay jugadores con ese filtro.">
            <?php if (empty($players)): ?><p class="hg-mobile-muted">No hay jugadores disponibles.</p><?php endif; ?>
            <?php foreach ($players as $player): ?>
                <?php
                    $id = (int)($player['player_id'] ?? 0);
                    $fullName = trim((string)($player['player_name'] ?? '') . ' ' . (string)($player['player_surname'] ?? ''));
                    if ($fullName === '') $fullName = '#' . $id;
                    $picture = trim((string)($player['player_picture'] ?? ''));
                    if ($picture === '') $picture = 'img/player/sinfoto.webp';
                    $desc = hg_mobile_player_excerpt((string)($player['player_description'] ?? ''));
                    $count = (int)($player['player_characters'] ?? 0);
                ?>
                <a class="hg-mobile-card hg-mobile-player-card" href="<?= hg_mobile_player_h(hg_mobile_player_url($link, $id)) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_player_h($fullName . ' ' . $desc) ?>">
                    <img src="<?= hg_mobile_player_h($picture) ?>" alt="">
                    <span class="hg-mobile-player-main">
                        <strong><?= hg_mobile_player_h($fullName) ?></strong>
                        <span><?= number_format($count, 0, ',', '.') ?> personajes</span>
                        <?php if ($desc !== ''): ?><small><?= hg_mobile_player_h($desc) ?></small><?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
    return;
}

$player = null;
if ($stmt = $link->prepare("SELECT id, pretty_id, name, surname, picture, description FROM dim_players WHERE id = ? AND show_in_catalog = 1 LIMIT 1")) {
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) $player = $res->fetch_assoc();
    $stmt->close();
}

if (!$player) {
    hg_public_render_not_found('Jugador no encontrado', 'El jugador solicitado no esta disponible en el catalogo.');
    return;
}

$fullName = trim((string)($player['name'] ?? '') . ' ' . (string)($player['surname'] ?? ''));
if ($fullName === '') $fullName = 'Jugador #' . $playerId;
$picture = trim((string)($player['picture'] ?? ''));
if ($picture === '') $picture = 'img/player/sinfoto.webp';
$description = trim((string)($player['description'] ?? ''));
$metaTitle = $fullName . " | Jugadores | Heaven's Gate";
$metaDescription = hg_mobile_player_excerpt($description, 160);
$pageTitle2 = $fullName;

$characters = [];
$characterKindSql = function_exists('hg_character_kind_select') ? hg_character_kind_select($link, 'c') : "''";
$sqlCharacters = "
    SELECT c.id, c.name, c.alias, c.image_url, c.gender, COALESCE(dcs.label, '') AS status, c.status_id,
           {$characterKindSql} AS character_kind
    FROM fact_characters c
    LEFT JOIN dim_character_status dcs ON dcs.id = c.status_id
    WHERE c.player_id = ? " . hg_mobile_chronicle_exclusion_and('c') . "
    ORDER BY c.name ASC, c.id ASC
";
if ($stmt = $link->prepare($sqlCharacters)) {
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) $characters[] = $row;
    }
    $stmt->close();
} else {
    hg_public_log_error('mobile_players', 'characters prepare failed: ' . mysqli_error($link));
}
?>

<section class="hg-mobile-section">
    <a class="hg-mobile-back-link" href="/players">Volver a jugadores</a>
</section>

<section class="hg-mobile-section hg-mobile-player-profile">
    <img src="<?= hg_mobile_player_h($picture) ?>" alt="<?= hg_mobile_player_h($fullName) ?>">
    <div>
        <h1><?= hg_mobile_player_h($fullName) ?></h1>
        <?php if ($description !== ''): ?>
            <div class="hg-mobile-rich-body"><?= $description ?></div>
        <?php else: ?>
            <p class="hg-mobile-muted">Sin descripción publicada.</p>
        <?php endif; ?>
    </div>
</section>

<section class="hg-mobile-section">
    <h2>Personajes</h2>
    <p class="hg-mobile-muted"><?= number_format(count($characters), 0, ',', '.') ?> personajes publicados</p>
    <?php if (empty($characters)): ?>
        <p class="hg-mobile-muted">Este jugador no tiene personajes publicados.</p>
    <?php else: ?>
        <div class="hg-mobile-character-list" data-mobile-paginated data-mobile-search="1" data-page-size="20" data-search-placeholder="Buscar personaje" data-empty-text="No hay personajes con ese filtro.">
            <?php foreach ($characters as $char): ?>
                <?php
                    $charId = (int)($char['id'] ?? 0);
                    $charName = trim((string)($char['name'] ?? ''));
                    $charAlias = trim((string)($char['alias'] ?? ''));
                    $status = trim((string)($char['status'] ?? ''));
                    $avatar = function_exists('hg_character_avatar_url') ? hg_character_avatar_url((string)($char['image_url'] ?? ''), (string)($char['gender'] ?? '')) : (string)($char['image_url'] ?? '');
                    $href = function_exists('pretty_url') ? pretty_url($link, 'fact_characters', '/characters', $charId) : ('/characters/' . $charId);
                ?>
                <a class="hg-mobile-character-card" href="<?= hg_mobile_player_h($href) ?>" data-mobile-item data-mobile-search="<?= hg_mobile_player_h($charName . ' ' . $charAlias . ' ' . $status) ?>">
                    <?php if ($avatar !== ''): ?><img src="<?= hg_mobile_player_h($avatar) ?>" alt=""><?php else: ?><span class="hg-mobile-character-avatar" aria-hidden="true"></span><?php endif; ?>
                    <span class="hg-mobile-character-main">
                        <strong><?= hg_mobile_player_h($charName !== '' ? $charName : ('#' . $charId)) ?></strong>
                        <span><?= hg_mobile_player_h(trim($charAlias . ' ' . $status)) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>