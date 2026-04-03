<?php setMetaFromPage("Tiradados | Heaven's Gate", "Herramienta para tirar dados d10 y registrar tiradas.", null, 'website'); ?>
<?php include("app/partials/main_nav_bar.php"); ?>
<?php include_once("app/partials/datatable_assets.php"); ?>
<?php include_once("app/helpers/runtime_response.php"); ?>

<link rel="stylesheet" href="/assets/css/hg-tools.css">

<?php
if (!hg_runtime_require_db($link, 'dice_roller', 'public', [
    'title' => 'Tiradados no disponible',
    'message' => 'No se pudo conectar a la base de datos.',
])) {
    return;
}

echo "<h2>Tiradados</h2>";

$pjList = fetch_pj_list($link);
$pjProfiles = fetch_pj_roll_profiles($link, $pjList);
$pjProfilesJson = htmlspecialchars(json_encode($pjProfiles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');

function parse_debug_rolls(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return [];
    $parts = preg_split('/\s*,\s*/', $raw);
    $out = [];
    foreach ($parts as $p) {
        if ($p === '' || !preg_match('/^\d+$/', $p)) return ['__invalid__'];
        $v = (int)$p;
        if ($v < 1 || $v > 10) return ['__invalid__'];
        $out[] = $v;
    }
    return $out;
}

function roll_d10_pool(int $dados, int $dificultad, array $forced = []): array {
    $resultados = [];
    $exitosBrutos = 0;
    $unoDetectado = false;

    for ($i = 0; $i < $dados; $i++) {
        $dado = !empty($forced) ? (int)$forced[$i] : rand(1, 10);
        $resultados[] = $dado;
        if ($dado >= $dificultad) $exitosBrutos++;
        if ($dado === 1) $unoDetectado = true;
    }

    $exitos = $exitosBrutos;
    if ($unoDetectado) {
        $exitos--;
        if ($exitos < 0) $exitos = 0;
    }

    // Pifia solo si hay "1" y no hubo ningun exito bruto.
    // Si hubo al menos un exito bruto y queda en 0 netos, es fallo, no pifia.
    $pifia = ($unoDetectado && $exitosBrutos === 0);
    return [$resultados, $exitos, $pifia];
}

function normalize_kind_key(string $kind): string {
    $k = function_exists('mb_strtolower') ? mb_strtolower(trim($kind), 'UTF-8') : strtolower(trim($kind));
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $k);
        if ($converted !== false) {
            $k = $converted;
        }
    }
    return strtolower($k);
}

function sanitize_int_csv(string $csv): string {
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

function hg_strlen(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function fetch_pj_list(mysqli $link): array {
    $out = [];
    global $excludeChronicles;
    $excludeChroniclesCsv = isset($excludeChronicles) ? sanitize_int_csv((string)$excludeChronicles) : '2,7';
    $whereChron = ($excludeChroniclesCsv !== '') ? " AND c.chronicle_id NOT IN ($excludeChroniclesCsv) " : "";
    $sql = "SELECT c.id, c.name, c.chronicle_id, ch.name AS chronicle_name
            FROM fact_characters c
            LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id
            WHERE LOWER(c.character_kind) = 'pj' {$whereChron}
            ORDER BY c.name ASC";
    if ($rs = mysqli_query($link, $sql)) {
        while ($r = mysqli_fetch_assoc($rs)) {
            $out[] = [
                'id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'chronicle_name' => (string)($r['chronicle_name'] ?? '')
            ];
        }
        mysqli_free_result($rs);
    }
    return $out;
}

function fetch_pj_roll_profiles(mysqli $link, array $pjs): array {
    $profiles = [];
    if (empty($pjs)) return $profiles;

    $ids = [];
    foreach ($pjs as $pj) {
        $id = (int)$pj['id'];
        $ids[] = $id;
        $profiles[$id] = [
            'name' => (string)$pj['name'],
            'chronicle' => (string)($pj['chronicle_name'] ?? ''),
            'attributes' => [],
            'skills' => [],
            'resources' => [],
            'attribute_map' => [],
            'skill_map' => [],
            'skill_kind_map' => [],
            'resource_map' => []
        ];
    }
    $idSql = implode(',', $ids);

    $sqlTraits = "SELECT b.character_id, t.id AS trait_id, t.name, t.kind, b.value
                  FROM bridge_characters_traits b
                  JOIN dim_traits t ON t.id = b.trait_id
                  WHERE b.character_id IN ($idSql)
                    AND t.kind IN ('Atributos','Talentos','Técnicas','Tecnicas','Conocimientos','Trasfondos')
                  ORDER BY t.name";
    if ($rs = mysqli_query($link, $sqlTraits)) {
        while ($r = mysqli_fetch_assoc($rs)) {
            $cid = (int)$r['character_id'];
            if (!isset($profiles[$cid])) continue;
            $traitId = (int)$r['trait_id'];
            $value = (int)$r['value'];
            if ($value <= 0) continue;
            $item = ['id' => $traitId, 'name' => (string)$r['name'], 'value' => $value];
            $kindKey = normalize_kind_key((string)$r['kind']);
            if ($kindKey === 'atributos') {
                $profiles[$cid]['attributes'][] = $item;
                $profiles[$cid]['attribute_map'][$traitId] = $value;
            } elseif (in_array($kindKey, ['talentos','tecnicas','conocimientos','trasfondos'], true)) {
                $skillKind = ($kindKey === 'trasfondos') ? 'trasfondo' : 'habilidad';
                $item['skill_kind'] = $skillKind;
                $profiles[$cid]['skills'][] = $item;
                $profiles[$cid]['skill_map'][$traitId] = $value;
                $profiles[$cid]['skill_kind_map'][$traitId] = $skillKind;
            }
        }
        mysqli_free_result($rs);
    }

    $sqlResources = "SELECT r.character_id, d.id AS resource_id, d.name, d.kind, r.value_permanent, r.value_temporary
                     FROM bridge_characters_system_resources r
                     JOIN dim_systems_resources d ON d.id = r.resource_id
                     WHERE r.character_id IN ($idSql)
                       AND LOWER(d.kind) = 'estado'
                     ORDER BY d.sort_order, d.name";
    if ($rs = mysqli_query($link, $sqlResources)) {
        while ($r = mysqli_fetch_assoc($rs)) {
            $cid = (int)$r['character_id'];
            if (!isset($profiles[$cid])) continue;
            $resourceId = (int)$r['resource_id'];
            $valuePerm = (int)$r['value_permanent'];
            $valueTemp = (int)$r['value_temporary'];
            $value = ($valueTemp > 0) ? $valueTemp : $valuePerm;
            if ($value <= 0) continue;
            $item = ['id' => $resourceId, 'name' => (string)$r['name'], 'value' => $value];
            $profiles[$cid]['resources'][] = $item;
            $profiles[$cid]['resource_map'][$resourceId] = $value;
        }
        mysqli_free_result($rs);
    }

    return $profiles;
}

function render_roll_card(array $tirada, int $id): void {
    $resultados = explode(',', (string)$tirada['roll_results']);
    $dificultad = (int)$tirada['difficulty'];
    $name = htmlspecialchars((string)$tirada['name'], ENT_QUOTES, 'UTF-8');
    $rollName = htmlspecialchars((string)$tirada['roll_name'], ENT_QUOTES, 'UTF-8');
    $dicePool = (int)$tirada['dice_pool'];
    $successes = (int)$tirada['successes'];
    $isBotch = (int)$tirada['botch'] === 1;
    $willpowerSpent = !empty($tirada['willpower_spent']);
    $palette = '#05014E';
    $codeText = "[hg_tirada]{$id}[/hg_tirada]";
    $safeCodeText = htmlspecialchars($codeText, ENT_QUOTES, 'UTF-8');

    echo "<article class='hg-dice-card'>";
    echo "<div class='hg-forum-roll-box' style='--roll-palette: {$palette};'>";
    echo "<div class='hg-forum-roll-box-name'>{$rollName}</div>";
    echo "<p class='hg-forum-roll-head'><strong>{$name}</strong> lanzó {$dicePool}d10 a Dificultad <strong>{$dificultad}</strong>.</p>";
    echo "<div class='hg-forum-roll-results'>";

    foreach ($resultados as $dadoRaw) {
        $dado = (int)$dadoRaw;
        $color = ($dado === 1) ? '#f55' : (($dado >= $dificultad) ? '#5f5' : '#5ff');
        echo "<div class='hg-forum-die' style='--die-color: {$color};'><span>{$dado}</span></div>";
    }

    echo "</div>";
    echo "<p><strong>&Eacute;xitos</strong>: {$successes}";
    if ($willpowerSpent) echo " <span class='hg-dice-help'>(+1 por Fuerza de Voluntad)</span>";
    echo "</p>";
    if ($isBotch) echo "<p class='hg-forum-roll-botch'><strong>¡PIFIA!</strong></p>";
    echo "<div class='hg-forum-roll-code'><code>{$safeCodeText}</code><button type='button' class='hg-roll-copy-emoji js-copy-roll' data-copy='{$safeCodeText}' title='Copiar codigo'>&#128203;</button></div>";
    echo "</div>";
    echo "</article>";
}

$mensaje_error = '';
$roll_mode = 'free';
$form_character_id = 0;
$form_attr_trait_id = 0;
$form_skill_trait_id = 0;
$form_resource_id = 0;
$form_extra_dice = 0;
$form_willpower_spent = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roll_mode = ((string)($_POST['roll_mode'] ?? 'free') === 'pj') ? 'pj' : 'free';
    $nombre_jugador = trim((string)($_POST['nombre'] ?? ''));
    $tirada_nombre = trim((string)($_POST['tirada_nombre'] ?? ''));
    $dados = 0;
    $dificultad = (int)($_POST['dificultad'] ?? 0);
    $debug_forced_rolls = parse_debug_rolls((string)($_POST['debug_forced_rolls'] ?? ''));
    $form_willpower_spent = isset($_POST['willpower_spent']) ? 1 : 0;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $maxDados = 20;

    if ($roll_mode === 'pj') {
        $form_character_id = (int)($_POST['character_id'] ?? 0);
        $form_attr_trait_id = (int)($_POST['attr_trait_id'] ?? 0);
        $form_skill_trait_id = (int)($_POST['skill_trait_id'] ?? 0);
        $form_resource_id = (int)($_POST['resource_id'] ?? 0);
        $form_extra_dice = (int)($_POST['extra_dice'] ?? 0);

        if (!isset($pjProfiles[$form_character_id])) {
            $mensaje_error = 'Debes elegir un protagonista valido.';
        } else {
            $profile = $pjProfiles[$form_character_id];
            $attrVal = ($form_attr_trait_id > 0 && isset($profile['attribute_map'][$form_attr_trait_id])) ? (int)$profile['attribute_map'][$form_attr_trait_id] : 0;
            $skillVal = ($form_skill_trait_id > 0 && isset($profile['skill_map'][$form_skill_trait_id])) ? (int)$profile['skill_map'][$form_skill_trait_id] : 0;
            $skillKind = ($form_skill_trait_id > 0 && isset($profile['skill_kind_map'][$form_skill_trait_id])) ? (string)$profile['skill_kind_map'][$form_skill_trait_id] : '';
            $resourceVal = ($form_resource_id > 0 && isset($profile['resource_map'][$form_resource_id])) ? (int)$profile['resource_map'][$form_resource_id] : 0;
            if ($form_extra_dice < 0 || $form_extra_dice > 20) {
                $mensaje_error = 'Los dados extra deben estar entre 0 y 20.';
            } else {
                $hasAttr = ($attrVal > 0);
                $hasSkill = ($skillVal > 0);
                $hasResource = ($resourceVal > 0);
                $isAttrOnly = ($hasAttr && !$hasSkill && !$hasResource);
                $isBackgroundOnly = (!$hasAttr && $hasSkill && !$hasResource && $skillKind === 'trasfondo');
                $isAttrPlusSkill = ($hasAttr && $hasSkill && !$hasResource);
                $isResourceOnly = (!$hasAttr && !$hasSkill && $hasResource);
                $isValidCombo = ($isAttrOnly || $isBackgroundOnly || $isAttrPlusSkill || $isResourceOnly);

                if (!$isValidCombo) {
                    $mensaje_error = 'Combinacion no valida. Permitido: Atributo, Trasfondo, Atributo+Habilidad/Trasfondo, o Recurso.';
                } else {
                    $dados = $attrVal + $skillVal + $resourceVal + $form_extra_dice;
                    if ($nombre_jugador === '') $nombre_jugador = (string)$profile['name'];
                }
            }
        }
        $maxDados = 50;
    } else {
        $dados = (int)($_POST['dados'] ?? 0);
    }

    if ($mensaje_error === '' && $debug_forced_rolls === ['__invalid__']) {
        $mensaje_error = 'Debug de tirada invalido.';
    } elseif ($mensaje_error === '' && ($nombre_jugador === '' || $tirada_nombre === '' || $dados < 1 || $dados > $maxDados || $dificultad < 2 || $dificultad > 10)) {
        $mensaje_error = 'Parametros invalidos.';
    } elseif ($mensaje_error === '' && !empty($debug_forced_rolls) && count($debug_forced_rolls) !== $dados) {
        $mensaje_error = 'El debug no coincide con el numero de dados.';
    } elseif ($mensaje_error === '' && hg_strlen($nombre_jugador) > 50) {
        $mensaje_error = 'El nombre del jugador/personaje no puede superar 50 caracteres.';
    } elseif ($mensaje_error === '' && hg_strlen($tirada_nombre) > 150) {
        $mensaje_error = 'El nombre de la tirada no puede superar 150 caracteres.';
    }

    if ($mensaje_error === '') {
        $query = "SELECT rolled_at FROM fact_dice_rolls WHERE ip = ? ORDER BY rolled_at DESC LIMIT 1";
        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, 's', $ip);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            if (strtotime((string)$row['rolled_at']) > time() - 10) {
                $mensaje_error = 'Has tirado hace menos de 10 segundos.';
            }
        }
    }

    if ($mensaje_error === '') {
        $query = "SELECT COUNT(*) as total FROM fact_dice_rolls WHERE roll_name = ?";
        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, 's', $tirada_nombre);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        if ((int)($row['total'] ?? 0) > 0) {
            $mensaje_error = 'Ese nombre de tirada ya existe.';
        }
    }

    if ($mensaje_error === '') {
        [$resultados, $exitos, $pifia] = roll_d10_pool($dados, $dificultad, $debug_forced_rolls);
        if ($form_willpower_spent === 1) {
            $exitos++;
            $pifia = false;
        }
        $pifia_valor = $pifia ? 1 : 0;
        $str_resultados = implode(',', $resultados);

        $query = "INSERT INTO fact_dice_rolls (name, roll_name, dice_pool, difficulty, roll_results, successes, botch, willpower_spent, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($link, $query);
        if (!$stmt) {
            $mensaje_error = 'No se pudo preparar el guardado de la tirada: ' . mysqli_error($link);
        } else {
            mysqli_stmt_bind_param($stmt, 'ssiisiiis', $nombre_jugador, $tirada_nombre, $dados, $dificultad, $str_resultados, $exitos, $pifia_valor, $form_willpower_spent, $ip);
            if (!mysqli_stmt_execute($stmt)) {
                $mensaje_error = 'No se pudo guardar la tirada: ' . mysqli_stmt_error($stmt);
            } else {
                $last_id = mysqli_insert_id($link);
                if ($last_id > 0) {
                    header("Location: /tools/dice?see=$last_id");
                    exit;
                }
                $mensaje_error = 'La tirada no devolvio un identificador valido al guardarse.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

echo "<div class='hg-dice-wrap'><div class='hg-dice-grid'>";

if (isset($_GET['see'])) {
    $id_ver = (int)$_GET['see'];
    $stmt = mysqli_prepare($link, "SELECT * FROM fact_dice_rolls WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id_ver);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        render_roll_card($row, $id_ver);
    } else {
        echo "<article class='hg-dice-card'><p>No se ha encontrado la tirada solicitada.</p></article>";
    }
}

if (!isset($_GET['see'])) {
    echo "<article class='hg-dice-card'>";
    echo "<h3 class='hg-dice-title'>Nueva tirada</h3>";
    if ($mensaje_error !== '') echo "<p class='hg-dice-error'>{$mensaje_error}</p>";

    echo "<form method='post' class='hg-dice-form'>";
    $selectedMode = htmlspecialchars($roll_mode, ENT_QUOTES, 'UTF-8');
    $selectedName = htmlspecialchars((string)($_POST['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
    $selectedRollName = htmlspecialchars((string)($_POST['tirada_nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
    $selectedDiff = (int)($_POST['dificultad'] ?? 6);
    $selectedDados = (int)($_POST['dados'] ?? 6);
    if ($selectedDados < 1 || $selectedDados > 20) $selectedDados = 6;
    if ($selectedDiff < 2 || $selectedDiff > 10) $selectedDiff = 6;

    echo "<input type='hidden' name='roll_mode' id='roll_mode' value='{$selectedMode}'>";
    echo "<div class='hg-dice-tabs'>";
    echo "<button type='button' class='hg-dice-tab-btn js-roll-mode-btn' data-mode='free'>Tirada libre</button>";
    echo "<button type='button' class='hg-dice-tab-btn js-roll-mode-btn' data-mode='pj'>Usar protagonista</button>";
    echo "</div>";

    echo "<div><label class='hg-dice-label' for='nombre'>Nombre del jugador / personaje</label><input class='hg-dice-inp' type='text' name='nombre' id='nombre' maxlength='50' value='{$selectedName}' required></div>";
    echo "<div><label class='hg-dice-label' for='tirada_nombre'>Nombre de la tirada (único)</label><input class='hg-dice-inp' type='text' name='tirada_nombre' id='tirada_nombre' maxlength='150' placeholder='Ej: Ataque del lobo' value='{$selectedRollName}' required></div>";

    echo "<div id='roll-panel-free' class='hg-roll-panel'>";
    echo "<div><label class='hg-dice-label' for='dados'>Dados (1-20)</label><select class='hg-dice-sel' name='dados' id='dados'>";
    for ($i = 1; $i <= 20; $i++) {
        $sel = ($i === $selectedDados) ? " selected" : "";
        echo "<option value='{$i}'{$sel}>{$i}</option>";
    }
    echo "</select><p class='hg-dice-help'>Modo cl&aacute;sico: eliges solo el numero de dados.</p></div>";
    echo "</div>";

    echo "<div id='roll-panel-pj' class='hg-roll-panel'>";
    echo "<div style='margin-bottom:1em;'><label class='hg-dice-label' for='character_id'>Protagonista (PJ)</label><select class='hg-dice-sel' name='character_id' id='character_id'><option value='0'>Selecciona protagonista...</option>";
    foreach ($pjList as $pjRow) {
        $cid = (int)$pjRow['id'];
        $pname = htmlspecialchars((string)$pjRow['name'], ENT_QUOTES, 'UTF-8');
        $chron = htmlspecialchars((string)($pjRow['chronicle_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $label = $pname . ($chron !== '' ? " (\"{$chron}\")" : '');
        $sel = ($cid === $form_character_id) ? " selected" : "";
        echo "<option value='{$cid}'{$sel}>{$label}</option>";
    }
    echo "</select></div>";

    echo "<div class='hg-dice-row' style='margin-bottom:1em;'>";
    echo "<div><label class='hg-dice-label' for='attr_trait_id'>Atributo</label><select class='hg-dice-sel' name='attr_trait_id' id='attr_trait_id'><option value='0'>-- Ninguno --</option></select></div>";
    echo "<div><label class='hg-dice-label' for='skill_trait_id'>Habilidad / Trasfondo</label><select class='hg-dice-sel' name='skill_trait_id' id='skill_trait_id'><option value='0'>-- Ninguno --</option></select></div>";
    echo "</div>";

    echo "<div class='hg-dice-row'>";
    echo "<div><label class='hg-dice-label' for='resource_id'>Recurso (Estado)</label><select class='hg-dice-sel' name='resource_id' id='resource_id'><option value='0'>-- Ninguno --</option></select></div>";
    echo "<div><label class='hg-dice-label' for='extra_dice'>Dados extra (0-20)</label><select class='hg-dice-sel' name='extra_dice' id='extra_dice'>";
    for ($i = 0; $i <= 20; $i++) {
        $sel = ($i === $form_extra_dice) ? " selected" : "";
        echo "<option value='{$i}'{$sel}>{$i}</option>";
    }
    echo "</select></div>";
    echo "</div>";

    echo "<p class='hg-dice-total'>Dados totales calculados: <span id='pj_total_dice'>0</span></p>";
    echo "<p class='hg-dice-help'>Formula: Atributo + Habilidad/Trasfondo + Estado + Dados extra.</p>";
    echo "</div>";

    echo "<div><label class='hg-dice-label' for='dificultad'>Dificultad (2-10)</label><select class='hg-dice-sel' name='dificultad' id='dificultad' required>";
    for ($i = 2; $i <= 10; $i++) {
        $sel = ($i === $selectedDiff) ? " selected" : "";
        echo "<option value='{$i}'{$sel}>{$i}</option>";
    }
    echo "</select></div>";
    $checkedWillpower = ($form_willpower_spent === 1) ? " checked" : "";
    echo "<div><label class='hg-dice-label' for='willpower_spent'>Reglas opcionales</label><label class='hg-dice-check'><input type='checkbox' name='willpower_spent' id='willpower_spent' value='1'{$checkedWillpower}> Gasto de Fuerza de Voluntad (+1 &Eacute;xito automatico)</label></div>";
    echo "<input type='hidden' name='debug_forced_rolls' id='debug_forced_rolls' value=''>";
    echo "<div class='hg-dice-actions'><button class='boton2' type='submit'>Tirar</button></div>";
    echo "</form>";
    echo "<input type='hidden' id='pj_profiles_json' value='{$pjProfilesJson}'>";
    echo "<input type='hidden' id='form_attr_trait_id' value='" . (int)$form_attr_trait_id . "'>";
    echo "<input type='hidden' id='form_skill_trait_id' value='" . (int)$form_skill_trait_id . "'>";
    echo "<input type='hidden' id='form_resource_id' value='" . (int)$form_resource_id . "'>";
    echo "</article>";
}

if (!isset($_GET['see'])) {
    $rolls = [];
    $query = "SELECT id, roll_name, name, successes, botch, willpower_spent, rolled_at FROM fact_dice_rolls ORDER BY rolled_at DESC";
    if ($rs = mysqli_query($link, $query)) {
        while ($r = mysqli_fetch_assoc($rs)) { $rolls[] = $r; }
        mysqli_free_result($rs);
    }

    echo "<article class='hg-dice-card hg-table-wrap'>";
    echo "<h3 class='hg-dice-title'>Historial completo de tiradas</h3>";
    echo "<table id='tabla-tiradas' class='display hg-dice-table'><thead><tr>";
    echo "<th>Tirada</th><th>Jugador</th><th>Resultado</th><th>Fecha</th>";
    echo "</tr></thead><tbody>";
    foreach ($rolls as $r) {
        $id = (int)$r['id'];
        $rollName = htmlspecialchars((string)$r['roll_name'], ENT_QUOTES, 'UTF-8');
        $player = htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8');
        $successes = (int)$r['successes'];
        $isBotch = ((int)$r['botch'] === 1);
        $estado = $isBotch ? 'Pifia' : (($successes > 0) ? 'Éxito' : 'Fallo');
        $pillClass = $isBotch ? 'hg-pill hg-pill--botch' : (($successes > 0) ? 'hg-pill hg-pill--ok' : 'hg-pill hg-pill--fail');
        $date = htmlspecialchars((string)$r['rolled_at'], ENT_QUOTES, 'UTF-8');
        $rollUrl = "/tools/dice?see={$id}";
        echo "<tr>";
        echo "<td><a class='hg-roll-link' href='{$rollUrl}'>{$rollName}</a></td><td>{$player}</td><td><span class='{$pillClass}'>{$estado}</span></td><td>{$date}</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
    echo "</article>";
} else {
    echo "<div class='bioSheetPowers hg-last-rolls'><fieldset class='bioSeccion'><legend>Ultimas 10 tiradas</legend>";
    $query = "SELECT id, roll_name, name FROM fact_dice_rolls ORDER BY rolled_at DESC LIMIT 10";
    if ($rs = mysqli_query($link, $query)) {
        while ($r = mysqli_fetch_assoc($rs)) {
            $id = (int)$r['id'];
            $title = htmlspecialchars((string)$r['roll_name'], ENT_QUOTES, 'UTF-8');
            $name = htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8');
            echo "<a href='/tools/dice?see={$id}'><div class='bioSheetPower'><span class='hg-last-rolls-title'>{$title}</span><span class='hg-last-rolls-name'>{$name}</span></div></a>";
        }
        mysqli_free_result($rs);
    }
    echo "</fieldset></div>";
}

echo "</div></div>";
?>

<script>
$(function(){
    if ($('#tabla-tiradas').length) {
        $('#tabla-tiradas').DataTable({
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [[3, 'desc']],
            language: {
                search: '&#128269; Buscar:&nbsp;',
                lengthMenu: 'Mostrar _MENU_ tiradas',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ tiradas',
                infoEmpty: 'No hay tiradas disponibles',
                emptyTable: 'No hay datos en la tabla',
                paginate: { first:'Primero', last:'Ultimo', next:'&#9654;', previous:'&#9664;' }
            }
        });
    }

    const $rollMode = $('#roll_mode');
    const $tabBtns = $('.js-roll-mode-btn');
    const $panelFree = $('#roll-panel-free');
    const $panelPj = $('#roll-panel-pj');
    const $char = $('#character_id');
    const $attr = $('#attr_trait_id');
    const $skill = $('#skill_trait_id');
    const $res = $('#resource_id');
    const $extra = $('#extra_dice');
    const $total = $('#pj_total_dice');
    const $name = $('#nombre');
    const $rollNameInput = $('#tirada_nombre');
    const $diff = $('#dificultad');
    const $submitBtn = $('.hg-dice-actions .boton2');
    const selectedAttrId = parseInt($('#form_attr_trait_id').val() || '0', 10);
    const selectedSkillId = parseInt($('#form_skill_trait_id').val() || '0', 10);
    const selectedResourceId = parseInt($('#form_resource_id').val() || '0', 10);

    let profiles = {};
    try { profiles = JSON.parse($('#pj_profiles_json').val() || '{}'); } catch (e) { profiles = {}; }

    function fillSelect($sel, list, selectedId) {
        $sel.empty().append($('<option>', { value: 0, text: '-- Ninguno --' }));
        (Array.isArray(list) ? list : []).forEach(item => {
            const id = parseInt(item.id || 0, 10);
            if (!id) return;
            const value = parseInt(item.value || 0, 10);
            const name = String(item.name || '');
            const opt = $('<option>', { value: id, text: `${name} (${value})` });
            if (id === selectedId) opt.prop('selected', true);
            $sel.append(opt);
        });
    }

    function activeMode(mode) {
        const m = (mode === 'pj') ? 'pj' : 'free';
        $rollMode.val(m);
        $tabBtns.each(function(){ $(this).toggleClass('active', $(this).data('mode') === m); });
        $panelFree.toggleClass('active', m === 'free');
        $panelPj.toggleClass('active', m === 'pj');
    }

    function recalcPjDice() {
        const cid = parseInt($char.val() || '0', 10);
        const p = profiles[cid] || {};
        const attrMap = p.attribute_map || {};
        const skillMap = p.skill_map || {};
        const resMap = p.resource_map || {};
        const av = parseInt(attrMap[parseInt($attr.val() || '0', 10)] || 0, 10);
        const sv = parseInt(skillMap[parseInt($skill.val() || '0', 10)] || 0, 10);
        const rv = parseInt(resMap[parseInt($res.val() || '0', 10)] || 0, 10);
        const ev = parseInt($extra.val() || '0', 10);
        $total.text(String(av + sv + rv + ev));
    }

    function getPjSelectionContext() {
        const cid = parseInt($char.val() || '0', 10);
        const p = profiles[cid] || {};
        const attrId = parseInt($attr.val() || '0', 10);
        const skillId = parseInt($skill.val() || '0', 10);
        const resourceId = parseInt($res.val() || '0', 10);
        const attrMap = p.attribute_map || {};
        const skillMap = p.skill_map || {};
        const skillKindMap = p.skill_kind_map || {};
        const resMap = p.resource_map || {};
        const attrVal = parseInt(attrMap[attrId] || 0, 10);
        const skillVal = parseInt(skillMap[skillId] || 0, 10);
        const resourceVal = parseInt(resMap[resourceId] || 0, 10);
        const skillKind = String(skillKindMap[skillId] || '');
        const hasAttr = attrVal > 0;
        const hasSkill = skillVal > 0;
        const hasResource = resourceVal > 0;
        const isAttrOnly = hasAttr && !hasSkill && !hasResource;
        const isBackgroundOnly = !hasAttr && hasSkill && !hasResource && skillKind === 'trasfondo';
        const isAttrPlusSkill = hasAttr && hasSkill && !hasResource;
        const isResourceOnly = !hasAttr && !hasSkill && hasResource;
        const isValid = isAttrOnly || isBackgroundOnly || isAttrPlusSkill || isResourceOnly;
        return {
            cid, p, attrId, skillId, resourceId, attrVal, skillVal, resourceVal, skillKind,
            hasAttr, hasSkill, hasResource, isAttrOnly, isBackgroundOnly, isAttrPlusSkill, isResourceOnly, isValid
        };
    }

    function cleanOptionLabel(raw) {
        return String(raw || '').replace(/\s*\(\d+\)\s*$/, '').trim();
    }

    function pjShortName(cid) {
        const p = profiles[cid] || {};
        const full = String(p.name || '').trim();
        if (!full) return '';
        return full.split(/\s+/)[0];
    }

    function syncAutoRollName() {
        if (($rollMode.val() || 'free') !== 'pj') return;
        const ctx = getPjSelectionContext();
        if (!ctx.isValid || ctx.cid <= 0) {
            $rollNameInput.val('');
            return;
        }
        const shortName = pjShortName(ctx.cid);
        const attrName = cleanOptionLabel($attr.find('option:selected').text());
        const skillName = cleanOptionLabel($skill.find('option:selected').text());
        const resourceName = cleanOptionLabel($res.find('option:selected').text());
        const diff = parseInt($diff.val() || '6', 10);
        if (ctx.isResourceOnly) {
            $rollNameInput.val(`${shortName}: ${resourceName} - Dificultad ${diff}`);
            return;
        }
        if (ctx.isAttrOnly) {
            $rollNameInput.val(`${shortName}: ${attrName} - Dificultad ${diff}`);
            return;
        }
        if (ctx.isBackgroundOnly) {
            $rollNameInput.val(`${shortName}: ${skillName} - Dificultad ${diff}`);
            return;
        }
        if (ctx.isAttrPlusSkill) {
            $rollNameInput.val(`${shortName}: ${attrName} + ${skillName} - Dificultad ${diff}`);
            return;
        }
        $rollNameInput.val('');
    }

    function syncPjSubmitState() {
        const mode = $rollMode.val() || 'free';
        if (mode !== 'pj') {
            $submitBtn.prop('disabled', false).removeClass('is-disabled');
            return;
        }
        const ctx = getPjSelectionContext();
        const valid = ctx.isValid && ctx.cid > 0;
        $submitBtn.prop('disabled', !valid).toggleClass('is-disabled', !valid);
    }

    function refreshPjOptions(useStoredSelection) {
        const cid = parseInt($char.val() || '0', 10);
        const p = profiles[cid] || {};
        fillSelect($attr, p.attributes || [], useStoredSelection ? selectedAttrId : 0);
        fillSelect($skill, p.skills || [], useStoredSelection ? selectedSkillId : 0);
        fillSelect($res, p.resources || [], useStoredSelection ? selectedResourceId : 0);
        if (p.name) {
            $name.val(String(p.name));
        } else if (cid === 0) {
            $name.val('');
        }
        recalcPjDice();
        syncAutoRollName();
        syncPjSubmitState();
    }

    if ($rollMode.length) {
        activeMode($rollMode.val() || 'free');
        refreshPjOptions(true);
        $tabBtns.on('click', function(){ activeMode($(this).data('mode')); syncAutoRollName(); syncPjSubmitState(); });
        $char.on('change', function(){ refreshPjOptions(false); });
        $attr.on('change', function(){ recalcPjDice(); syncAutoRollName(); syncPjSubmitState(); });
        $skill.on('change', function(){ recalcPjDice(); syncAutoRollName(); syncPjSubmitState(); });
        $res.on('change', function(){ recalcPjDice(); syncAutoRollName(); syncPjSubmitState(); });
        $extra.on('change', function(){ recalcPjDice(); syncAutoRollName(); syncPjSubmitState(); });
        $diff.on('change', function(){ syncAutoRollName(); syncPjSubmitState(); });
        syncAutoRollName();
        syncPjSubmitState();
    }

    $(document).on('click', '.js-copy-roll', async function(){
        const text = String($(this).data('copy') || '');
        if (!text) return;
        const $btn = $(this);
        const old = $btn.text();
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
            $btn.text('\u2705');
        } catch (e) {
            $btn.text('\u274C');
        }
        setTimeout(() => $btn.text(old), 1400);
    });
});
</script>



