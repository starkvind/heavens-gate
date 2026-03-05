<?php
include_once(__DIR__ . '/../../helpers/character_avatar.php');

// Aseguramos que el parÃ¡metro GET 'b' estÃ© definido y es un valor seguro
$itemPageID = isset($_GET['b']) ? $_GET['b'] : '';
$itemId = (int)$itemPageID;

// Preparamos la consulta para evitar inyecciones SQL
$queryItem = "SELECT * FROM fact_items WHERE id = ? LIMIT 1;";
$stmt = $link->prepare($queryItem);
$stmt->bind_param('s', $itemPageID);
$stmt->execute();
$result = $stmt->get_result();
$rowsQueryItem = $result->num_rows;

// ================================================================== //
if ($rowsQueryItem > 0) { // Si encontramos el Objeto en la BDD...
    $resultQueryItem = $result->fetch_assoc();

    // ================================================================== //
    // DATOS BÃSICOS
    $itemID     = htmlspecialchars($resultQueryItem["id"]);
    $itemName   = htmlspecialchars($resultQueryItem["name"]);
    $itemType   = (int)$resultQueryItem["item_type_id"];
    $itemSkill  = htmlspecialchars($resultQueryItem["skill_name"]);
    $itemLevel  = (int)$resultQueryItem["level"];
    $itemGnosis = (int)$resultQueryItem["gnosis"];
    $itemValue  = htmlspecialchars($resultQueryItem["rating"]);
    $itemBonus  = (int)$resultQueryItem["bonus"];
    $itemDamage = htmlspecialchars(strtolower($resultQueryItem["damage_type"]));
    $itemMetal  = (int)$resultQueryItem["metal"];
    $itemSTR    = (int)$resultQueryItem["strength_req"];
    $itemDEX    = (int)$resultQueryItem["dexterity_req"];
    $itemImg    = htmlspecialchars($resultQueryItem["image_url"]);
    $itemInfo   = ($resultQueryItem["description"]);
    $itemOrig   = (int)$resultQueryItem["bibliography_id"];
    
    // ================================================================== //
    // SELECCIONAR ORIGEN
    $itemOriginName = "-"; // Valor predeterminado si no se encuentra
    if ($itemOrig != 0) {
        $queryOrigen = "SELECT name FROM dim_bibliographies WHERE id = ? LIMIT 1;";
        $stmtOrigin = $link->prepare($queryOrigen);
        $stmtOrigin->bind_param('i', $itemOrig);
        $stmtOrigin->execute();
        $resultOrigin = $stmtOrigin->get_result();
        if ($resultOrigin->num_rows > 0) {
            $resultQueryOrigen = $resultOrigin->fetch_assoc();
            $itemOriginName = htmlspecialchars($resultQueryOrigen["name"]);
        }
        $stmtOrigin->close();
    }

    // ================================================================== //
    // Preparar Tipo (preferir dim_item_types)
    $nameTypeItem = "";
    $nameTypeBack = "";
    if ($itemType > 0) {
        $stType = $link->prepare("SELECT name FROM dim_item_types WHERE id = ? LIMIT 1");
        if ($stType) {
            $stType->bind_param('i', $itemType);
            $stType->execute();
            $rsType = $stType->get_result();
            if ($rsType && ($rowType = $rsType->fetch_assoc())) {
                $nameTypeItem = (string)$rowType['name'];
                $nameTypeBack = (string)$rowType['name'];
            }
            $stType->close();
        }
    }
    if ($nameTypeItem === "") {
        switch ($itemType) {
            case 1:
                $nameTypeItem = "Arma";
                $nameTypeBack = "Armamento";
                break;
            case 2:
                $nameTypeItem = "Protector";
                $nameTypeBack = "Protectores";
                break;
            case 3:
                $nameTypeItem = "Objeto m?gico";
                $nameTypeBack = "Objetos m?gicos";
                break;
            case 5:
                $nameTypeItem = "Amuleto";
                $nameTypeBack = "Amuletos";
                break;
            default:
                $nameTypeItem = "Objeto";
                $nameTypeBack = "Objetos";
                break;
        }
    }

// ================================================================== //
    // Preparar DaÃ±o
    switch ($itemMetal) {
        case 1:
            $metalText = " y de plata";
            break;
        case 2:
            $metalText = " y de oro";
            break;
        default:
            $metalText = "";
            break;          
    }

    switch ($itemSkill) {
        case "Cuerpo a Cuerpo":
        case "Pelea":
        case "Arrojar":
            $damageText = "Fuerza + $itemBonus";
            break;
        default:
            $damageText = "$itemBonus dados";
            break;
    }

    // ================================================================== //
    // ImÃ¡genes y TÃ­tulo
    $pageSect = "Objeto"; // TÃ­tulo de la PÃ¡gina ( #$itemID )
    $pageTitle2 = $itemName;
    setMetaFromPage($itemName . " | Objetos | Heaven's Gate", meta_excerpt($itemInfo), null, 'article');
    if (empty($itemImg)) {
        $itemImg = "img/inv/no-photo.gif";
    }

    // ================================================================== //
    /* MODERNO NUEVO */
    include("app/partials/main_nav_bar.php"); // Barra NavegaciÃ³n
    //echo "<h2>$itemName</h2>"; // Encabezado de pÃ¡gina

    ob_start();

    echo "<div class='power-card power-card--item'>";
    echo "  <div class='power-card__banner'>";
    echo "    <span class='power-card__title'>" . $itemName . "</span>";
    echo "  </div>";

    echo "  <div class='power-card__body'>";
    echo "    <div class='power-card__media'>";
    echo "      <div class='power-card__img-wrap'>";
    echo "        <img class='power-card__img' src='$itemImg' alt='$itemName'/>";
    echo "      </div>";
    echo "    </div>";

    echo "    <div class='power-card__stats'>";

    echo "<div class='power-stat'><div class='power-stat__label'>Tipo</div><div class='power-stat__value'>$nameTypeItem</div></div>";

    if (!empty($itemSkill)) {
        echo "<div class='power-stat'><div class='power-stat__label'>Habilidad</div><div class='power-stat__value'>$itemSkill</div></div>";
    }

    if (!empty($itemDamage)) {
        echo "<div class='power-stat'><div class='power-stat__label'>Da&ntilde;o</div><div class='power-stat__value'>$damageText, $itemDamage$metalText</div></div>";
    }

    if ($itemBonus != 0 && empty($itemSkill)) {
        echo "<div class='power-stat'><div class='power-stat__label'>Bonificaci&oacute;n</div><div class='power-stat__value'>+$itemBonus de absorci&oacute;n</div></div>";
    }

    if ($itemLevel != 0) {
        echo "<div class='power-stat'><div class='power-stat__label'>Nivel</div><div class='power-stat__value'><img class='bioAttCircle' src='img/ui/gems/pwr/gem-pwr-0$itemLevel.png'/></div></div>";
        if ($itemGnosis != 0) {
            echo "<div class='power-stat'><div class='power-stat__label'>Gnosis</div><div class='power-stat__value'><img class='bioAttCircle' src='img/ui/gems/pwr/gem-pwr-0$itemGnosis.png'/></div></div>";
        }
    }

    if ($itemGnosis != 0 && $itemLevel == 0 && $itemType == 5) {
        echo "<div class='power-stat'><div class='power-stat__label'>Gnosis</div><div class='power-stat__value'><img class='bioAttCircle' src='img/ui/gems/pwr/gem-pwr-0$itemGnosis.png'/></div></div>";
    }

    if ($itemSTR != 0) {
        echo "<div class='power-stat'><div class='power-stat__label'>Requiere</div><div class='power-stat__value'>Fuerza $itemSTR m&iacute;nimo</div></div>";
    }

    if ($itemDEX != 0) {
        echo "<div class='power-stat'><div class='power-stat__label'>Penalizaci&oacute;n</div><div class='power-stat__value'>Destreza -$itemDEX</div></div>";
    }

    echo "<div class='power-stat'><div class='power-stat__label'>Origen</div><div class='power-stat__value'>$itemOriginName</div></div>";

    echo "    </div>";
    echo "  </div>";

    if (!empty($itemInfo)) {
        echo "  <div class='power-card__desc'>";
        echo "    <div class='power-card__desc-title'>Descripci&oacute;n</div>";
        echo "    <div class='power-card__desc-body'>$itemInfo</div>";
            $embedCodeRaw = "[hg_item]" . (int)$itemPageID . "[/hg_item]";
            $embedCodeEsc = htmlspecialchars($embedCodeRaw, ENT_QUOTES, 'UTF-8');
            echo "<style>
                .item-embed-wrap{ margin-top:12px; }
                .item-embed-title{ display:block; margin:0 0 6px; font-weight:700; color:#b9d2ff; }
                .item-embed-wrap .hg-forum-roll-code{
                    display:flex; align-items:center; gap:8px;
                    border:1px solid #365eb8; border-radius:8px;
                    background:#091b53; padding:8px 10px;
                    width:fit-content; max-width:100%;
                }
                .item-embed-wrap .hg-forum-roll-code code{
                    color:#f3f8ff; font-family:Consolas, Monaco, monospace;
                    white-space:nowrap; overflow:auto;
                }
                .item-embed-wrap .hg-roll-copy-emoji{
                    border:1px solid #3f68c4; border-radius:6px;
                    background:#0f2a73; color:#e8f1ff; cursor:pointer;
                    padding:3px 8px; line-height:1.2;
                }
            </style>";
            echo "<div class='item-embed-wrap'>";
            echo "<div class='power-card__desc-title' style='margin-bottom:1.5em;'>Embeber en el foro</div>";
            echo "<div class='hg-forum-roll-code'>
                <code>{$embedCodeEsc}</code>
                <button type='button' class='hg-roll-copy-emoji js-copy-roll' data-copy='{$embedCodeEsc}' title='Copiar codigo'>&#128203;</button>
                </div>";
            echo "</div>";
        echo "  </div>";
    }

    echo "</div>";

    $infoHtml = ob_get_clean();

    // ================================================================== //
    // Portadores
    $itemOwners = [];
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
    $cronicaNotInSQL = ($excludeChronicles !== '') ? " AND p.chronicle_id NOT IN ($excludeChronicles) " : "";
    $characterKindSql = hg_character_kind_select($link, 'p');
    $queryOwners = "
        SELECT
            p.id,
            p.name,
            p.alias,
            p.image_url,
            p.gender,
            COALESCE(dcs.label, '') AS status, p.status_id,
            {$characterKindSql} AS character_kind
        FROM bridge_characters_items b
        JOIN fact_characters p ON p.id = b.character_id
        LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
        WHERE b.item_id = ? $cronicaNotInSQL
        ORDER BY p.name
    ";
    if ($stOwners = $link->prepare($queryOwners)) {
        $stOwners->bind_param('i', $itemPageID);
        $stOwners->execute();
        $rsOwners = $stOwners->get_result();
        while ($r = $rsOwners->fetch_assoc()) { $itemOwners[] = $r; }
        $stOwners->close();
    }
    $hasOwners = count($itemOwners) > 0;

    if ($hasOwners) {
        include_once(__DIR__ . '/../../partials/owners_tabs_styles.php');
        hg_render_owner_tabs_styles(true, 28);

        echo "<div class='hg-tabs'>";
        echo "<button class='boton2 hgTabBtn' data-tab='info'>Informaci&oacute;n</button>";
        echo "<button class='boton2 hgTabBtn' data-tab='owners'>Portadores</button>";
        echo "</div>";

        echo "<section class='hg-tab-panel' data-tab='info'>$infoHtml</section>";

        echo "<section class='hg-tab-panel' data-tab='owners'>";
        echo "<div class='grupoBioClan'><div class='contenidoAfiliacion'>";
        foreach ($itemOwners as $o) {
            $oid = (int)($o['id'] ?? 0);
            $name = (string)($o['name'] ?? '');
            $alias = (string)($o['alias'] ?? '');
            $href = pretty_url($link, 'fact_characters', '/characters', $oid);
            hg_render_character_avatar_tile([
                'href' => $href,
                'title' => $name,
                'name' => $name,
                'alias' => $alias,
                'character_id' => $oid,
                'image_url' => (string)($o['image_url'] ?? ''),
                'gender' => (string)($o['gender'] ?? ''),
                'status' => (string)($o['status'] ?? ''),
                'character_kind' => hg_character_kind_from_row($o),
                'target_blank' => true,
            ]);
        }
        echo "</div></div>";
        echo "<p align='right'>Personajes: " . count($itemOwners) . "</p>";
        echo "</section>";

    } else {
        echo $infoHtml;
    }
    /* =========== */
} // Fin comprobaciÃ³n

$stmt->close();

?>






<script>
document.addEventListener('click', async (event) => {
    const btn = event.target.closest('.js-copy-roll');
    if (!btn) return;
    const text = String(btn.getAttribute('data-copy') || '');
    if (!text) return;
    const old = btn.innerHTML;
    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
        }
        btn.innerHTML = '&#9989;';
    } catch (e) {
        btn.innerHTML = '&#10060;';
    }
    setTimeout(() => { btn.innerHTML = old; }, 1400);
});
</script>

