<?php
include_once(__DIR__ . '/../../helpers/character_avatar.php');
$pjRaw = $_GET['b'] ?? '';
$pjId = resolve_pretty_id($link, 'dim_players', (string)$pjRaw) ?? 0;

if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}
if ($pjId <= 0) {
    die("Jugador inválido.");
}

$queryPlayer = "
    SELECT id, pretty_id, name, surname, picture, description
    FROM dim_players
    WHERE id = ? AND show_in_catalog = 1
    LIMIT 1
";
$stmtPlayer = mysqli_prepare($link, $queryPlayer);
if (!$stmtPlayer) {
    die("Error al preparar la consulta: " . mysqli_error($link));
}

mysqli_stmt_bind_param($stmtPlayer, 'i', $pjId);
mysqli_stmt_execute($stmtPlayer);
$resultPlayer = mysqli_stmt_get_result($stmtPlayer);

if (!$resultPlayer || mysqli_num_rows($resultPlayer) <= 0) {
    mysqli_stmt_close($stmtPlayer);
    die("Jugador no disponible en el catálogo.");
}

$player = mysqli_fetch_assoc($resultPlayer);
mysqli_free_result($resultPlayer);
mysqli_stmt_close($stmtPlayer);

$namePJ = htmlspecialchars((string)($player['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$surnamePJ = htmlspecialchars((string)($player['surname'] ?? ''), ENT_QUOTES, 'UTF-8');
$descPJ = (string)($player['description'] ?? '');
$picPJ = (string)($player['picture'] ?? '');
if ($picPJ === '') {
    $picPJ = 'img/player/sinfoto.jpg';
}

$pageSect = "Jugador";
$pageTitle2 = trim($namePJ . " " . $surnamePJ);
setMetaFromPage($pageTitle2 . " | Jugadores | Heaven's Gate", meta_excerpt($descPJ), $picPJ, 'article');
include("app/partials/main_nav_bar.php");
echo '<link rel="stylesheet" href="/assets/css/hg-playr.css">';

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
$excludeChronicles = ($excludeChronicles === '') ? '2,7' : $excludeChronicles;
$chronicleNotInSQL = ($excludeChronicles !== '') ? " AND chronicle_id NOT IN ($excludeChronicles) " : "";

$queryCharacters = "
    SELECT id, name, alias, image_url, gender, status
    FROM fact_characters
    WHERE player_id = ? $chronicleNotInSQL
    ORDER BY name ASC
";
$stmtCharacters = mysqli_prepare($link, $queryCharacters);
if (!$stmtCharacters) {
    die("Error al preparar la consulta de personajes: " . mysqli_error($link));
}

mysqli_stmt_bind_param($stmtCharacters, 'i', $pjId);
mysqli_stmt_execute($stmtCharacters);
$resultCharacters = mysqli_stmt_get_result($stmtCharacters);

$characters = [];
if ($resultCharacters) {
    while ($row = mysqli_fetch_assoc($resultCharacters)) {
        $characters[] = $row;
    }
    mysqli_free_result($resultCharacters);
}
mysqli_stmt_close($stmtCharacters);

$mapEstado = [
    'Aun por aparecer' => '(@)',
    'Aún por aparecer' => '(@)',
    'Paradero desconocido' => '(?)',
    'Cadaver' => '(&#8224;)',
    'Cadáver' => '(&#8224;)'
];
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
                            $charName = htmlspecialchars((string)($char['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $charAliasRaw = (string)($char['alias'] ?? '');
                            $charAlias = htmlspecialchars($charAliasRaw !== '' ? $charAliasRaw : (string)($char['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $charImgRaw = (string)($char['image_url'] ?? '');
                            $charImg = htmlspecialchars(hg_character_avatar_url($charImgRaw, (string)($char['gender'] ?? '')), ENT_QUOTES, 'UTF-8');
                            $charStatus = (string)($char['status'] ?? '');
                            $charState = $mapEstado[$charStatus] ?? '';
                            $charHref = htmlspecialchars(pretty_url($link, 'fact_characters', '/characters', $charId), ENT_QUOTES, 'UTF-8');
                        ?>
                        <a href="<?= $charHref ?>" title="<?= $charName ?>">
                            <div class="marcoFotoBio">
                                <div class="textoDentroFotoBio"><?= $charAlias ?> <?= $charState ?></div>
                                <div class="dentroFotoBio"><img class="fotoBioList" src="<?= $charImg ?>" alt="<?= $charName ?>"></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <p class="player-counter">Personajes: <?= count($characters) ?></p>
        <?php else: ?>
            <p class="player-empty">Este jugador no tiene personajes publicados.</p>
        <?php endif; ?>
    </section>
</div>
