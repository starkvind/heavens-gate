<?php
$donPageID = isset($_GET['b']) ? $_GET['b'] : '';

$queryDon = "SELECT * FROM fact_discipline_powers WHERE id = ? LIMIT 1";
$stmt = $link->prepare($queryDon);
$stmt->bind_param('s', $donPageID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $resultQueryDon = $result->fetch_assoc();

    $donId      = htmlspecialchars($resultQueryDon["id"]);
    $donName    = htmlspecialchars($resultQueryDon["name"]);
    $donType    = htmlspecialchars($resultQueryDon["disc"]);
    $donRank    = htmlspecialchars($resultQueryDon["level"]);
    $donAttrRaw = (string)($resultQueryDon["attribute"] ?? '');
    $donSkillRaw = (string)($resultQueryDon["skill"] ?? '');
    $donAttr    = htmlspecialchars($donAttrRaw);
    $donSkill   = htmlspecialchars($donSkillRaw);
    $donDesc    = $resultQueryDon["description"];
    $donSystem  = $resultQueryDon["system_name"];
    $donOrigin  = htmlspecialchars($resultQueryDon["bibliography_id"]);

    $donImgRaw = trim((string)($resultQueryDon["image_url"] ?? ""));
    $donIcono = isset($resultQueryDon["icono"]) ? htmlspecialchars($resultQueryDon["icono"]) : "";

    $itemImg = "img/inv/no-photo.webp";
    if ($donImgRaw !== "") {
        if (strpos($donImgRaw, "/") !== false) {
            $itemImg = $donImgRaw;
        } else {
            $itemImg = "img/disciplines/" . $donImgRaw;
        }
    } elseif ($donIcono !== "") {
        $itemImg = (strpos($donIcono, "/") !== false) ? $donIcono : ("img/" . $donIcono);
    }

    $queryOrigen = "SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1";
    $stmt = $link->prepare($queryOrigen);
    $stmt->bind_param('s', $donOrigin);
    $stmt->execute();
    $result = $stmt->get_result();
    $donOriginName = "-";

    if ($rowOrigen = $result->fetch_assoc()) {
        $donOriginName = htmlspecialchars($rowOrigen["name"]);
    }

    $queryTipo = "SELECT name FROM dim_discipline_types WHERE id = ? LIMIT 1";
    $stmt = $link->prepare($queryTipo);
    $stmt->bind_param('s', $donType);
    $stmt->execute();
    $result = $stmt->get_result();
    $nombreTipo = "-";

    if ($rowTipo = $result->fetch_assoc()) {
        $nombreTipo = htmlspecialchars($rowTipo["name"]);
    }

    $_SESSION['punk2'] = $nombreTipo;

    $pageSect = "Disciplinas";
    $pageTitle2 = $donName;
    setMetaFromPage($donName . " | Disciplinas | Heaven's Gate", meta_excerpt($donDesc), null, 'article');

    if (function_exists('hg_page_register_stylesheet')) {
        hg_page_register_stylesheet('/assets/css/hg-powers.css');
    } else {
        echo '<link rel="stylesheet" href="/assets/css/hg-powers.css">';
    }

    include("app/partials/main_nav_bar.php");

    ob_start();

    echo "<div class='power-card power-card--disc'>";
    echo "  <div class='power-card__banner'>";
    echo "    <span class='power-card__title'>$donName</span>";
    echo "  </div>";

    echo "  <div class='power-card__body'>";
    echo "    <div class='power-card__media'>";
    echo "      <img class='power-card__img' style='border:1px solid #001a55; box-shadow: 0 0 0 2px #001a55, 0 0 14px rgba(0,0,0,0.5)' src='$itemImg' alt='$donName'/>";
    echo "    </div>";

    echo "    <div class='power-card__stats'>";
    if ($donRank > 0) {
        echo "<div class='power-stat'><div class='power-stat__label'>Nivel</div><div class='power-stat__value'><img class='hg-powers-gem' src='img/ui/gems/pwr/gem-pwr-0$donRank.webp'/></div></div>";
    }
    if ($nombreTipo !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Disciplina</div><div class='power-stat__value'>$nombreTipo</div></div>";
    }
    if (!empty($donAttr) || !empty($donSkill)) {
        $tiradaDon2 = !empty($donSkill) ? "$donAttr + $donSkill" : $donAttr;
        echo "<div class='power-stat'><div class='power-stat__label'>Tirada</div><div class='power-stat__value'>$tiradaDon2</div></div>";
    }
    if ($donOriginName !== "") {
        echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>$donOriginName</div></div>";
    }
    echo "    </div>";
    echo "  </div>";

    if (!empty($donDesc)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Descripci&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>$donDesc</div>";
        echo "  </div>";
    }

    if (!empty($donSystem)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Sistema</div>";
        echo "    <div class='power-card__desc-body'>$donSystem</div>";
        echo "  </div>";
    }

    echo "</div>";

    echo ob_get_clean();

} else {
    echo "<p>Error: Disciplina no encontrada.</p>";
}
?>
