<?php
include("app/helpers/db_connection.php");

$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$itemId) {
    die("Objeto no especificado.");
}

$query = "SELECT id, name, image_url, description, item_type_id, rating, pretty_id FROM fact_items WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt, "i", $itemId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$item = mysqli_fetch_assoc($res)) {
    die("Objeto no encontrado.");
}

$name = htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8');
$img = trim((string)($item['image_url'] ?? ''));
$img = ($img !== '') ? htmlspecialchars($img, ENT_QUOTES, 'UTF-8') : "img/inv/no-photo.gif";
$description = (string)($item['description'] ?? '');
$description = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $description);
$rating = trim((string)($item['rating'] ?? ''));
$ratingInt = (preg_match('/^\d+$/', $rating) === 1) ? (int)$rating : 0;

$typePretty = '';
$typeName = '';
$typeId = (int)($item['item_type_id'] ?? 0);
if ($typeId > 0) {
    $stType = mysqli_prepare($link, "SELECT pretty_id, name FROM dim_item_types WHERE id = ? LIMIT 1");
    if ($stType) {
        mysqli_stmt_bind_param($stType, "i", $typeId);
        mysqli_stmt_execute($stType);
        $rsType = mysqli_stmt_get_result($stType);
        if ($rowType = mysqli_fetch_assoc($rsType)) {
            $typePretty = trim((string)($rowType['pretty_id'] ?? ''));
            $typeName = trim((string)($rowType['name'] ?? ''));
        }
        mysqli_stmt_close($stType);
    }
}
if ($typePretty === '') {
    $typePretty = ($typeId > 0) ? (string)$typeId : 'tipo';
}
if ($typeName === '') {
    switch ($typeId) {
        case 1:
            $typeName = 'Arma';
            break;
        case 2:
            $typeName = 'Protector';
            break;
        case 3:
            $typeName = 'Objeto magico';
            break;
        case 5:
            $typeName = 'Amuleto';
            break;
        default:
            $typeName = 'Objeto';
            break;
    }
}
$typeName = htmlspecialchars($typeName, ENT_QUOTES, 'UTF-8');

$itemPretty = trim((string)($item['pretty_id'] ?? ''));
if ($itemPretty === '') {
    $itemPretty = (string)$itemId;
}

$itemHref = "/inventory/" . rawurlencode($typePretty) . "/" . rawurlencode($itemPretty);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $name ?></title>
    <link href="/assets/vendor/fonts/quicksand/quicksand.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/hg-embeds.css">
</head>
<body class="hg-embed-item">
    <article class="embed-item-card">
        <a class="embed-item-card__banner" href="<?= htmlspecialchars($itemHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
            <span class="embed-item-card__title"><?= $name ?></span>
        </a>

        <div class="embed-item-card__body">
            <a class="embed-item-card__media" href="<?= htmlspecialchars($itemHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                <span class="embed-item-card__img-wrap">
                    <img class="embed-item-card__img" src="../<?= $img ?>" alt="<?= $name ?>">
                </span>
            </a>

            <div class="embed-item-card__content">
                <div class="embed-item-card__stats">
                    <div class="embed-item-card__stat">
                        <div class="embed-item-card__stat-label">Tipo</div>
                        <div class="embed-item-card__stat-value"><?= $typeName ?></div>
                    </div>
                    <?php if ($typeId === 3 && $rating !== ''): ?>
                        <div class="embed-item-card__stat">
                            <div class="embed-item-card__stat-label">Nivel</div>
                            <div class="embed-item-card__stat-value">
                                <?php if ($ratingInt >= 1 && $ratingInt <= 9): ?>
                                    <img class="embed-item-card__gem" src="../img/ui/gems/pwr/gem-pwr-0<?= $ratingInt ?>.png" alt="Nivel <?= $ratingInt ?>">
                                <?php else: ?>
                                    <?= htmlspecialchars($rating, ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="embed-item-card__desc">
                    <div class="embed-item-card__desc-body"><?= $description ?></div>
                </div>
            </div>
        </div>
    </article>

    <script>
        function sendHeight() {
            const height = document.body.scrollHeight + 24;
            window.parent.postMessage({ type: 'setHeight', height }, '*');
        }

        window.addEventListener('load', sendHeight);
        window.addEventListener('resize', sendHeight);
    </script>
</body>
</html>
