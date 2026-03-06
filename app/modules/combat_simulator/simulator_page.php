<?php
include_once("sim_character_scope.php");
include_once("app/helpers/character_avatar.php");

$cronicaNotInSQL = sim_chronicle_not_in_sql('c.chronicle_id');
$pageSect = "Simulador de Combate";
$defaultForm = "Humano";

include("app/partials/main_nav_bar.php");

if (!function_exists('sim_h')) {
    function sim_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sim_table_exists')) {
    function sim_table_exists($link, $tableName)
    {
        $safe = mysql_real_escape_string((string)$tableName, $link);
        $rs = mysql_query("SHOW TABLES LIKE '$safe'", $link);
        return ($rs && mysql_num_rows($rs) > 0);
    }
}

$formsByRace = array();
$formsQuery = mysql_query("SELECT raza, forma FROM vw_sim_forms ORDER BY forma ASC", $link);
if ($formsQuery) {
    while ($rowForm = mysql_fetch_array($formsQuery)) {
        $race = (string)($rowForm['raza'] ?? '');
        $form = (string)($rowForm['forma'] ?? '');
        if ($race === '' || $form === '') {
            continue;
        }
        if (!isset($formsByRace[$race])) {
            $formsByRace[$race] = array();
        }
        if (!in_array($form, $formsByRace[$race], true)) {
            $formsByRace[$race][] = $form;
        }
    }
    mysql_free_result($formsQuery);
}

$roster = array();
$formsByCharacter = array();
$itemsByCharacter = array();
$characterIds = array();

$queryRoster = "
SELECT
    v.id,
    v.nombre,
    v.alias,
    v.img,
    v.fera,
    COALESCE(v.fuerza, 0) AS fuerza,
    COALESCE(v.destreza, 0) AS destreza,
    COALESCE(v.pelea, 0) AS pelea,
    COALESCE(v.armascc, 0) AS armascc,
    COALESCE(v.armasdefuego, 0) AS armasdefuego,
    COALESCE(v.esquivar, 0) AS esquivar,
    COALESCE(v.resistencia, 0) AS resistencia
FROM vw_sim_characters v
INNER JOIN fact_characters c ON c.id = v.id
WHERE v.kes LIKE 'pj' $cronicaNotInSQL
ORDER BY v.alias ASC
";

$resultRoster = mysql_query($queryRoster, $link);
if ($resultRoster) {
    while ($row = mysql_fetch_array($resultRoster)) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $name = (string)($row['nombre'] ?? '');
        $alias = (string)($row['alias'] ?? '');
        $fera = (string)($row['fera'] ?? '');
        $imgRaw = (string)($row['img'] ?? '');
        $img = function_exists('hg_character_avatar_url') ? hg_character_avatar_url($imgRaw, '') : $imgRaw;

        $rankScore = (int)($row['fuerza'] ?? 0)
            + (int)($row['destreza'] ?? 0)
            + (int)($row['pelea'] ?? 0)
            + (int)($row['armascc'] ?? 0)
            + (int)($row['armasdefuego'] ?? 0)
            + (int)($row['esquivar'] ?? 0)
            + (int)($row['resistencia'] ?? 0);
        $rankStars = (int)ceil($rankScore / 7);
        if ($rankStars < 1) {
            $rankStars = 1;
        }
        if ($rankStars > 5) {
            $rankStars = 5;
        }

        $roster[] = array(
            'id' => $id,
            'name' => $name,
            'alias' => $alias,
            'fera' => $fera,
            'img' => $img,
            'rank_score' => $rankScore,
            'rank_stars' => $rankStars,
        );

        $charForms = array($defaultForm);
        if ($fera !== '' && isset($formsByRace[$fera])) {
            foreach ($formsByRace[$fera] as $f) {
                if (!in_array($f, $charForms, true)) {
                    $charForms[] = $f;
                }
            }
        }
        $formsByCharacter[$id] = $charForms;

        $itemsByCharacter[$id] = array('weapons' => array(), 'armors' => array());
        $characterIds[] = $id;
    }
    mysql_free_result($resultRoster);
}

if (!empty($characterIds) && sim_table_exists($link, 'bridge_characters_items')) {
    $idSql = implode(',', array_map('intval', $characterIds));
    $itemsQuery = "
    SELECT
        b.character_id,
        i.id,
        i.name,
        i.tipo,
        COALESCE(i.habilidad, '') AS habilidad,
        COALESCE(i.bonus, 0) AS bonus,
        COALESCE(i.destreza, 0) AS destreza
    FROM bridge_characters_items b
    INNER JOIN vw_sim_items i ON i.id = b.item_id
    WHERE b.character_id IN ($idSql)
    ORDER BY i.name ASC
    ";

    $itemsRs = mysql_query($itemsQuery, $link);
    if ($itemsRs) {
        while ($itemRow = mysql_fetch_array($itemsRs)) {
            $cid = (int)($itemRow['character_id'] ?? 0);
            if ($cid <= 0 || !isset($itemsByCharacter[$cid])) {
                continue;
            }

            $itemId = (int)($itemRow['id'] ?? 0);
            $itemName = (string)($itemRow['name'] ?? '');
            $tipo = (int)($itemRow['tipo'] ?? 0);
            $habilidad = (string)($itemRow['habilidad'] ?? '');
            $bonus = (int)($itemRow['bonus'] ?? 0);

            if ($itemId <= 0 || $itemName === '') {
                continue;
            }

            if ($tipo === 2) {
                $itemsByCharacter[$cid]['armors'][] = array(
                    'id' => $itemId,
                    'name' => $itemName,
                    'label' => $itemName . ' (+' . $bonus . ')'
                );
            } elseif ($habilidad !== '') {
                $skillShort = 'P';
                if ($habilidad === 'Atletismo' || $habilidad === 'Arrojar') {
                    $skillShort = 'A';
                } elseif ($habilidad === 'Cuerpo a Cuerpo') {
                    $skillShort = 'C';
                } elseif ($habilidad === 'Tiro con Arco') {
                    $skillShort = 'T';
                } elseif ($habilidad === 'Armas de Fuego') {
                    $skillShort = 'F';
                } elseif ($habilidad === 'Informatica' || $habilidad === 'Informática') {
                    $skillShort = 'I';
                }

                $itemsByCharacter[$cid]['weapons'][] = array(
                    'id' => $itemId,
                    'name' => $itemName,
                    'label' => $itemName . ' (' . $skillShort . ')'
                );
            }
        }
        mysql_free_result($itemsRs);
    }
}

$rosterJson = json_encode($roster, JSON_UNESCAPED_UNICODE);
if ($rosterJson === false) {
    $rosterJson = '[]';
}
$formsJson = json_encode($formsByCharacter, JSON_UNESCAPED_UNICODE);
if ($formsJson === false) {
    $formsJson = '{}';
}
$itemsJson = json_encode($itemsByCharacter, JSON_UNESCAPED_UNICODE);
if ($itemsJson === false) {
    $itemsJson = '{}';
}
?>

<div class="sim-ui sim-select-screen">
    <h2>Simulador de Combate</h2>

    <form action="/tools/combat-simulator/result" method="post" name="simulador" id="simFightForm">
        <input type="hidden" name="pj1" id="simPj1" value="">
        <input type="hidden" name="pj2" id="simPj2" value="">

        <div class="sim-fight-header">
            <button type="button" class="sim-fighter-slot" id="simSlotP1" data-slot="p1">
                <div class="sim-slot-portrait"></div>
                <div class="sim-slot-name">Selecciona personaje</div>
                <div class="sim-slot-alias">-</div>
                <div class="sim-slot-rank" aria-label="Rango"></div>
            </button>

            <div class="sim-fight-vs">VS</div>

            <button type="button" class="sim-fighter-slot" id="simSlotP2" data-slot="p2">
                <div class="sim-slot-portrait"></div>
                <div class="sim-slot-name">Selecciona rival</div>
                <div class="sim-slot-alias">-</div>
                <div class="sim-slot-rank" aria-label="Rango"></div>
            </button>
        </div>

        <div class="sim-roster-wrap">
            <div class="sim-roster-title">Elige tu combatiente</div>
            <div class="sim-roster-grid" id="simRosterGrid"></div>
        </div>

        <div class="sim-loadout-grid" id="simLoadoutGrid">
            <div class="sim-loadout-card">
                <h3 id="simConfigTitleP1">Configuraci&oacute;n P1</h3>
                <label>Forma
                    <select name="forma1" id="simForma1"></select>
                </label>
                <label>Arma
                    <select name="arma1" id="simArma1"></select>
                </label>
                <label>Protector
                    <select name="protec1" id="simProt1"></select>
                </label>
            </div>
            <div class="sim-loadout-card">
                <h3 id="simConfigTitleP2">Configuraci&oacute;n P2</h3>
                <label>Forma
                    <select name="forma2" id="simForma2"></select>
                </label>
                <label>Arma
                    <select name="arma2" id="simArma2"></select>
                </label>
                <label>Protector
                    <select name="protec2" id="simProt2"></select>
                </label>
            </div>
        </div>

        <fieldset class="sim-fieldset-inline">
            <legend>Opciones de combate</legend>
            <div class="sim-options-row">
                <label>Turnos
                    <select name="turnos" id="simTurnos">
                        <option value="1">1</option>
                        <option value="5" selected="selected">5</option>
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="20">20</option>
                        <option value="25">25</option>
                        <option value="30">30</option>
                        <option value="35">35</option>
                        <option value="40">40</option>
                        <option value="45">45</option>
                        <option value="50">50</option>
                    </select>
                </label>

                <label>Vitalidad
                    <select name="vit" id="simVit">
                        <option value="1">1</option>
                        <option value="7" selected="selected">7</option>
                        <option value="14">14</option>
                        <option value="21">21</option>
                        <option value="28">28</option>
                        <option value="35">35</option>
                        <option value="42">42</option>
                        <option value="49">49</option>
                        <option value="56">56</option>
                    </select>
                </label>

                <label>Heridas
                    <select name="usarheridas" id="simHeridas">
                        <option value="no">Ignorar heridas</option>
                        <option value="yes" selected="selected">Aplicar heridas</option>
                    </select>
                </label>

                <label>Curaci&oacute;n
                    <select name="regeneracion" id="simRegen">
                        <option value="no">Ninguna</option>
                        <option value="ambos">Ambos</option>
                        <option value="pj1">Personaje</option>
                        <option value="pj2">Rival</option>
                    </select>
                </label>

                <label>Combate
                    <select name="combate" id="simCombate">
                        <option value="normal" selected="selected">Normal</option>
                        <option value="umbral">Umbral</option>
                    </select>
                </label>

                <label>Tono narrativo
                    <select name="narrative_tone" id="simNarrativeTone">
                        <option value="random" selected="selected">Aleatorio</option>
                        <option value="serio">Serio</option>
                        <option value="epico">&Eacute;pico</option>
                        <option value="brutal">Brutal</option>
                        <option value="ironico">Ir&oacute;nico</option>
                    </select>
                </label>

                <label>Mensajes ambientales
                    <select name="ambient_msgs" id="simAmbientMsgs">
                        <option value="yes" selected="selected">Activados</option>
                        <option value="no">Desactivados</option>
                    </select>
                </label>

                <label>Rubberbanding
                    <select name="rubberbanding" id="simRubberbanding">
                        <option value="yes" selected="selected">Activado</option>
                        <option value="no">Desactivado</option>
                    </select>
                </label>
            </div>
        </fieldset>

        <fieldset class="sim-fieldset-inline">
            <legend>Aleatorizar</legend>
            <div class="sim-random-row">
                <label><input type="checkbox" name="aleatorio" value="yes" id="simRandomChars"> Personajes</label>
                <label><input type="checkbox" name="armasrandom" value="yes"> Armamento</label>
                <label><input type="checkbox" name="protrandom" value="yes"> Protecci&oacute;n</label>
                <label><input type="checkbox" name="formarandom" value="yes"> Formas</label>
                <label><input type="checkbox" name="turnrandom" value="yes"> Turnos</label>
                <label><input type="checkbox" name="vitrandom" value="yes"> Vitalidad</label>
            </div>
        </fieldset>

        <div class="sim-submit-row">
            <input class="boton1" type="submit" id="simStartBtn" value="Empezar" disabled="disabled">
        </div>
    </form>

    <div class="sim-extra-panels">
        <?php include("simulator_help.php"); ?>
        <?php include("simulator_stats.php"); ?>
    </div>
</div>

<script>
(function() {
    var roster = <?php echo $rosterJson; ?>;
    var formsByCharacter = <?php echo $formsJson; ?>;
    var itemsByCharacter = <?php echo $itemsJson; ?>;
    var defaultForm = <?php echo json_encode($defaultForm, JSON_UNESCAPED_UNICODE); ?>;

    var selected = { p1: null, p2: null };
    var hoverPreview = { p1: null, p2: null };

    var rosterMap = {};
    roster.forEach(function(ch) { rosterMap[String(ch.id)] = ch; });

    var rosterGrid = document.getElementById('simRosterGrid');
    var slotP1 = document.getElementById('simSlotP1');
    var slotP2 = document.getElementById('simSlotP2');
    var slotByKey = { p1: slotP1, p2: slotP2 };

    var inpP1 = document.getElementById('simPj1');
    var inpP2 = document.getElementById('simPj2');

    var selForma1 = document.getElementById('simForma1');
    var selForma2 = document.getElementById('simForma2');
    var selArma1 = document.getElementById('simArma1');
    var selArma2 = document.getElementById('simArma2');
    var selProt1 = document.getElementById('simProt1');
    var selProt2 = document.getElementById('simProt2');
    var loadoutGrid = document.getElementById('simLoadoutGrid');
    var configTitleP1 = document.getElementById('simConfigTitleP1');
    var configTitleP2 = document.getElementById('simConfigTitleP2');

    var randomCharsToggle = document.getElementById('simRandomChars');
    var startBtn = document.getElementById('simStartBtn');
    var hadBothSelected = false;

    function isRandomPick(value) {
        return String(value || '') === 'random';
    }

    function starsMarkup(stars) {
        var full = '';
        var total = Number(stars || 0);
        if (total < 1) { total = 1; }
        if (total > 5) { total = 5; }
        for (var i = 1; i <= total; i++) {
            full += '&#9733;';
        }
        return full;
    }

    function placeholderOption(select, text) {
        select.innerHTML = '';
        var o = document.createElement('option');
        o.value = '';
        o.textContent = text;
        select.appendChild(o);
    }

    function fillForms(select, characterId) {
        select.innerHTML = '';
        if (!characterId) {
            placeholderOption(select, '- Selecciona personaje -');
            select.disabled = true;
            return;
        }
        if (isRandomPick(characterId)) {
            placeholderOption(select, '- Aleatorio al iniciar -');
            select.disabled = true;
            return;
        }

        var list = formsByCharacter[String(characterId)] || [defaultForm];
        if (!Array.isArray(list) || list.length === 0) {
            list = [defaultForm];
        }

        list.forEach(function(f) {
            var o = document.createElement('option');
            o.value = String(f || defaultForm);
            o.textContent = String(f || defaultForm);
            select.appendChild(o);
        });

        select.disabled = false;
    }

    function fillItems(select, characterId, itemType) {
        select.innerHTML = '';

        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = (itemType === 'weapons') ? '- Ninguna -' : '- Ninguno -';
        select.appendChild(empty);

        if (!characterId || isRandomPick(characterId)) {
            select.disabled = true;
            return;
        }

        var entry = itemsByCharacter[String(characterId)] || { weapons: [], armors: [] };
        var list = entry[itemType] || [];

        list.forEach(function(item) {
            var o = document.createElement('option');
            o.value = String(item.id || '');
            o.textContent = String(item.label || item.name || 'Item');
            select.appendChild(o);
        });

        select.disabled = false;
    }

    function paintSlot(slotButton, slotKey) {
        var id = hoverPreview[slotKey] || selected[slotKey];
        var portrait = slotButton.querySelector('.sim-slot-portrait');
        var name = slotButton.querySelector('.sim-slot-name');
        var alias = slotButton.querySelector('.sim-slot-alias');
        var rank = slotButton.querySelector('.sim-slot-rank');

        if (!id) {
            slotButton.classList.remove('is-filled');
            portrait.innerHTML = '';
            name.textContent = (slotKey === 'p1') ? 'Selecciona personaje' : 'Selecciona rival';
            alias.textContent = '-';
            rank.innerHTML = '';
            return;
        }
        if (isRandomPick(id)) {
            slotButton.classList.add('is-filled');
            portrait.innerHTML = '<span class="sim-slot-noimg">?</span>';
            name.textContent = 'Aleatorio';
            alias.textContent = '? - Aleatorio';
            rank.innerHTML = '';
            return;
        }
        if (!rosterMap[String(id)]) {
            slotButton.classList.remove('is-filled');
            portrait.innerHTML = '';
            name.textContent = (slotKey === 'p1') ? 'Selecciona personaje' : 'Selecciona rival';
            alias.textContent = '-';
            rank.innerHTML = '';
            return;
        }

        var ch = rosterMap[String(id)];
        slotButton.classList.add('is-filled');

        var safeImg = (ch.img && String(ch.img).trim() !== '')
            ? '<img src="' + String(ch.img).replace(/"/g, '&quot;') + '" alt="' + String(ch.name || ch.alias || '').replace(/"/g, '&quot;') + '">' 
            : '<span class="sim-slot-noimg">?</span>';

        portrait.innerHTML = safeImg;
        name.textContent = String(ch.name || '');
        alias.textContent = String(ch.alias || '');
        rank.innerHTML = starsMarkup(ch.rank_stars);
    }

    function clearHoverPreview() {
        var changed = (hoverPreview.p1 !== null || hoverPreview.p2 !== null);
        hoverPreview.p1 = null;
        hoverPreview.p2 = null;
        if (changed) {
            paintSlot(slotP1, 'p1');
            paintSlot(slotP2, 'p2');
        }
    }

    function previewSlotKey() {
        return nextFreeSlot();
    }

    function paintRoster() {
        var randomStateClass = '';
        if (selected.p1 === 'random' && selected.p2 === 'random') {
            randomStateClass = 'is-picked-p1 is-picked-p2';
        } else if (selected.p1 === 'random') {
            randomStateClass = 'is-picked-p1';
        } else if (selected.p2 === 'random') {
            randomStateClass = 'is-picked-p2';
        }

        var html = ''
            + '<button type="button" class="sim-roster-card sim-roster-random ' + randomStateClass + '" data-char-id="random">'
            + '  <div class="sim-roster-portrait"><span class="sim-roster-noimg">?</span></div>'
            + '  <div class="sim-roster-meta">'
            + '    <div class="sim-roster-name">Aleatorio</div>'
            + '    <div class="sim-roster-rank"></div>'
            + '  </div>'
            + '</button>';

        roster.forEach(function(ch) {
            var cid = Number(ch.id || 0);
            if (!cid) { return; }

            var stateClass = '';
            if (selected.p1 === cid) {
                stateClass = 'is-picked-p1';
            } else if (selected.p2 === cid) {
                stateClass = 'is-picked-p2';
            }

            var imgHtml = (ch.img && String(ch.img).trim() !== '')
                ? '<img src="' + String(ch.img).replace(/"/g, '&quot;') + '" alt="' + String(ch.name || ch.alias || '').replace(/"/g, '&quot;') + '">'
                : '<span class="sim-roster-noimg">?</span>';

            html += ''
                + '<button type="button" class="sim-roster-card ' + stateClass + '" data-char-id="' + cid + '">'
                + '  <div class="sim-roster-portrait">' + imgHtml + '</div>'
                + '  <div class="sim-roster-meta">'
                + '    <div class="sim-roster-name">' + String(ch.alias || ch.name || 'Personaje') + '</div>'
                + '    <div class="sim-roster-rank">' + starsMarkup(ch.rank_stars) + '</div>'
                + '  </div>'
                + '</button>';
        });

        rosterGrid.innerHTML = html;

        var cards = rosterGrid.querySelectorAll('.sim-roster-card');
        cards.forEach(function(card) {
            card.addEventListener('mouseenter', function() {
                var slotKey = previewSlotKey();
                if (!slotKey) {
                    return;
                }

                var rawId = String(card.getAttribute('data-char-id') || '');
                var id = (rawId === 'random') ? 'random' : Number(rawId || 0);
                if (!(id === 'random' || id > 0)) {
                    return;
                }

                hoverPreview[slotKey] = id;
                paintSlot(slotByKey[slotKey], slotKey);
            });

            card.addEventListener('mouseleave', clearHoverPreview);
            card.addEventListener('blur', clearHoverPreview);

            card.addEventListener('click', function() {
                clearHoverPreview();
                var rawId = String(card.getAttribute('data-char-id') || '');
                var id = (rawId === 'random') ? 'random' : Number(rawId || 0);
                if (id === 'random' || id > 0) {
                    selectCharacter(id);
                }
            });
        });
    }

    function syncFormState() {
        inpP1.value = selected.p1 ? String(selected.p1) : '';
        inpP2.value = selected.p2 ? String(selected.p2) : '';

        fillForms(selForma1, selected.p1);
        fillForms(selForma2, selected.p2);

        fillItems(selArma1, selected.p1, 'weapons');
        fillItems(selArma2, selected.p2, 'weapons');
        fillItems(selProt1, selected.p1, 'armors');
        fillItems(selProt2, selected.p2, 'armors');

        var hasP1 = !!selected.p1;
        var hasP2 = !!selected.p2;
        var randomBoth = !!(randomCharsToggle && randomCharsToggle.checked);
        startBtn.disabled = !((hasP1 || randomBoth) && (hasP2 || randomBoth));
    }

    function selectedAlias(slotKey) {
        var id = selected[slotKey];
        if (!id) {
            return (slotKey === 'p1') ? 'P1' : 'P2';
        }
        if (isRandomPick(id)) {
            return 'Aleatorio';
        }
        var ch = rosterMap[String(id)];
        if (!ch) {
            return (slotKey === 'p1') ? 'P1' : 'P2';
        }
        return String(ch.alias || ch.name || ((slotKey === 'p1') ? 'P1' : 'P2'));
    }

    function updateConfigTitles() {
        if (configTitleP1) {
            configTitleP1.textContent = 'Configuración ' + selectedAlias('p1');
        }
        if (configTitleP2) {
            configTitleP2.textContent = 'Configuración ' + selectedAlias('p2');
        }
    }

    function nextFreeSlot() {
        if (!selected.p1) { return 'p1'; }
        if (!selected.p2) { return 'p2'; }
        return null;
    }

    function selectCharacter(id) {
        if (isRandomPick(id)) {
            var randomFree = nextFreeSlot();
            if (randomFree) {
                selected[randomFree] = 'random';
            } else if (selected.p1 === 'random' && selected.p2 === 'random') {
                selected.p2 = null;
            } else if (selected.p1 === 'random') {
                selected.p1 = null;
            } else if (selected.p2 === 'random') {
                selected.p2 = null;
            }
            renderAll();
            return;
        }

        if (selected.p1 === id) {
            selected.p1 = null;
        } else if (selected.p2 === id) {
            selected.p2 = null;
        } else {
            var free = nextFreeSlot();
            if (free) {
                selected[free] = id;
            }
        }

        renderAll();
    }

    function setupSlotClick(slotButton, slotKey) {
        slotButton.addEventListener('click', function() {
            clearHoverPreview();
            if (selected[slotKey]) {
                selected[slotKey] = null;
                renderAll();
            }
        });
    }

    function renderAll() {
        paintSlot(slotP1, 'p1');
        paintSlot(slotP2, 'p2');
        paintRoster();
        syncFormState();
        updateConfigTitles();

        var bothSelected = !!selected.p1 && !!selected.p2;
        if (bothSelected && !hadBothSelected && loadoutGrid) {
            setTimeout(function() {
                loadoutGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 80);
        }
        hadBothSelected = bothSelected;
    }

    setupSlotClick(slotP1, 'p1');
    setupSlotClick(slotP2, 'p2');
    if (randomCharsToggle) {
        randomCharsToggle.addEventListener('change', syncFormState);
    }

    renderAll();
})();
</script>
