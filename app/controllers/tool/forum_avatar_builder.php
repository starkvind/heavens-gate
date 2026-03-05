<?php
setMetaFromPage("Creador de mensajes de foro | Heaven's Gate", "Genera snippets hg_avatar con color y efectos de texto para el foro.", null, 'website');
include("app/partials/main_nav_bar.php");
?>
<link rel="stylesheet" href="/assets/css/hg-tools.css">

<?php
if (!isset($link) || !$link) {
    die("No DB connection.");
}

function sanitize_int_csv($csv) {
    $csv = (string)$csv;
    if (trim($csv) === '') {
        return '';
    }
    $parts = preg_split('/\s*,\s*/', trim($csv));
    $ints = [];
    foreach ($parts as $p) {
        if ($p === '') {
            continue;
        }
        if (preg_match('/^\d+$/', $p)) {
            $ints[] = (string)(int)$p;
        }
    }
    $ints = array_values(array_unique($ints));
    return implode(',', $ints);
}

$excludeChroniclesCsv = isset($excludeChronicles) ? sanitize_int_csv($excludeChronicles) : '2,7';
$whereChron = ($excludeChroniclesCsv !== '') ? "chronicle_id NOT IN ($excludeChroniclesCsv)" : "1=1";

$defaultAvatars = [
    ['id' => -1, 'name' => 'Hombre (default)'],
    ['id' => -2, 'name' => 'Mujer (default)'],
    ['id' => -3, 'name' => 'Silueta (default)'],
    ['id' => -4, 'name' => 'Espiritu (default)'],
];

$characters = [];
$sql = "SELECT id, name FROM fact_characters WHERE $whereChron ORDER BY name ASC";
if ($rs = mysqli_query($link, $sql)) {
    while ($row = mysqli_fetch_assoc($rs)) {
        $characters[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
        ];
    }
    mysqli_free_result($rs);
}

$dbColors = [];
$sqlColors = "SELECT text_color FROM fact_characters WHERE text_color <> '' AND $whereChron GROUP BY 1 ORDER BY 1";
if ($rsColors = mysqli_query($link, $sqlColors)) {
    while ($rowColor = mysqli_fetch_assoc($rsColors)) {
        $value = trim((string)($rowColor['text_color'] ?? ''));
        if ($value === '') {
            continue;
        }
        $dbColors[] = $value;
    }
    mysqli_free_result($rsColors);
}
?>

<div class="hg-avatar-tool-wrap">
    <h2>Creador de Mensajes para Foro</h2>
    <div class="hg-avatar-tool-grid">
        <section class="hg-dice-card">
            <h3 class="hg-dice-title">Editor</h3>

            <div class="hg-avatar-tool-field">
                <label class="hg-dice-label" for="avatarSearch">Buscar personaje</label>
                <input class="hg-dice-inp" type="text" id="avatarSearch" placeholder="Escribe para filtrar...">
            </div>

            <div class="hg-avatar-tool-field">
                <label class="hg-dice-label" for="avatarId">Personaje</label>
                <select class="hg-dice-sel" id="avatarId">
                    <?php foreach ($defaultAvatars as $defaultAvatar): ?>
                        <option value="<?= (int)$defaultAvatar['id'] ?>"><?= htmlspecialchars($defaultAvatar['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                    <?php foreach ($characters as $character): ?>
                        <option value="<?= (int)$character['id'] ?>">#<?= (int)$character['id'] ?> - <?= htmlspecialchars($character['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="hg-avatar-tool-field hg-avatar-tool-color-row" id="avatarColorRow">
                <div>
                    <label class="hg-dice-label" for="avatarColor">Color fondo</label>
                    <input type="color" id="avatarColor" value="#3f87d4">
                </div>
                <div>
                    <label class="hg-dice-label" for="avatarColorText">Valor color</label>
                    <input class="hg-dice-inp" type="text" id="avatarColorText" value="#3f87d4" placeholder="#3f87d4 o SkyBlue">
                </div>
            </div>

            <div class="hg-avatar-tool-field">
                <label class="hg-dice-label" for="avatarColorPreset">Colores del mensaje)</label>
                <div class="hg-avatar-tool-color-select-wrap">
                    <span id="avatarColorDot" class="hg-avatar-tool-color-dot" aria-hidden="true"></span>
                    <select class="hg-dice-sel" id="avatarColorPreset">
                        <option value="__CHAR_DEFAULT__">— Por defecto del personaje</option>
                        <?php foreach ($dbColors as $dbColor): ?>
                            <option value="<?= htmlspecialchars($dbColor, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dbColor, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="hg-avatar-tool-field">
                <label class="hg-dice-label" for="avatarMessage">Mensaje</label>
                <textarea class="hg-avatar-tool-textarea" id="avatarMessage" rows="8" placeholder="Escribe el mensaje aqui..."></textarea>
            </div>

            <div class="hg-avatar-tool-actions">
                <button type="button" class="hg-dice-tab-btn" data-transform="mountain">MoNtAnA RuSa</button>
                <button type="button" class="hg-dice-tab-btn" data-transform="upper">MAYUSCULAS</button>
                <button type="button" class="hg-dice-tab-btn" data-transform="lower">minusculas</button>
                <button type="button" class="hg-dice-tab-btn" data-transform="dialog">Anadir comillas</button>
                <button type="button" class="hg-dice-tab-btn" data-transform="pause">Anadir pausas</button>
            </div>

            <p class="hg-dice-help">Los efectos se aplican al texto seleccionado. Si no seleccionas nada, se aplican a todo el mensaje.</p>
        </section>

        <section class="hg-dice-card">
            <h3 class="hg-dice-title">Vista previa</h3>

            <div class="hg-roll-code hg-avatar-tool-code-wrap">
                <code id="avatarSnippetCode"></code>
                <button type="button" class="hg-roll-copy-emoji" id="copySnippetBtn" title="Copiar codigo">&#128203;</button>
            </div>

            <p class="hg-dice-help" style="margin-top: 1em; margin-bottom: 1em;">Handler: <code>[hg_avatar={id},{color}]{mensaje}[/hg_avatar]</code></p>
            <!--
            <div class="hg-avatar-tool-preview-head">
                <strong>Vista previa</strong>
                <span class="hg-dice-help">Usa el mismo renderer de <code>/forum/message</code></span>
            </div>
            -->
            <iframe id="avatarPreviewFrame" class="hg-avatar-tool-preview" src="about:blank" title="Vista previa del mensaje"></iframe>
        </section>
    </div>
</div>

<script>
(() => {
    "use strict";

    const $search = document.getElementById("avatarSearch");
    const $avatar = document.getElementById("avatarId");
    const $colorPicker = document.getElementById("avatarColor");
    const $colorText = document.getElementById("avatarColorText");
    const $colorRow = document.getElementById("avatarColorRow");
    const $colorPreset = document.getElementById("avatarColorPreset");
    const $colorDot = document.getElementById("avatarColorDot");
    const $message = document.getElementById("avatarMessage");
    const $code = document.getElementById("avatarSnippetCode");
    const $copy = document.getElementById("copySnippetBtn");
    const $preview = document.getElementById("avatarPreviewFrame");

    if (!$search || !$avatar || !$colorPicker || !$colorText || !$colorRow || !$colorPreset || !$colorDot || !$message || !$code || !$copy || !$preview) {
        return;
    }

    const allOptions = Array.from($avatar.options).map(opt => ({
        value: String(opt.value),
        label: String(opt.textContent || "")
    }));

    function renderAvatarOptions(term) {
        const search = String(term || "").trim().toLowerCase();
        const selected = String($avatar.value || "-1");
        $avatar.innerHTML = "";

        for (const opt of allOptions) {
            const match = search === "" || opt.label.toLowerCase().includes(search) || opt.value === search;
            if (!match) continue;
            const el = document.createElement("option");
            el.value = opt.value;
            el.textContent = opt.label;
            $avatar.appendChild(el);
        }

        if ($avatar.options.length === 0) {
            const el = document.createElement("option");
            el.value = selected;
            el.textContent = "Sin resultados";
            $avatar.appendChild(el);
            return;
        }

        const canRestore = Array.from($avatar.options).some(o => o.value === selected);
        $avatar.value = canRestore ? selected : $avatar.options[0].value;
    }

    function hexToPickerSafe(v) {
        const m = String(v || "").trim().match(/^#([0-9a-fA-F]{6})$/);
        return m ? `#${m[1].toLowerCase()}` : null;
    }

    function colorToCss(v) {
        const color = String(v || "").trim();
        if (!color) return "transparent";
        return color;
    }

    function isDefaultColorMode() {
        return String($colorPreset.value || "") === "__CHAR_DEFAULT__";
    }

    function letterCaseMountain(text) {
        let out = "";
        let upper = true;
        for (const ch of String(text)) {
            const isLetter = ch.toLowerCase() !== ch.toUpperCase();
            if (!isLetter) {
                out += ch;
                continue;
            }
            out += upper ? ch.toUpperCase() : ch.toLowerCase();
            upper = !upper;
        }
        return out;
    }

    function applyTransform(fn) {
        const start = $message.selectionStart;
        const end = $message.selectionEnd;
        const hasSelection = Number.isInteger(start) && Number.isInteger(end) && end > start;
        const value = $message.value;

        if (hasSelection) {
            const chunk = value.slice(start, end);
            const replaced = fn(chunk);
            $message.value = value.slice(0, start) + replaced + value.slice(end);
            $message.setSelectionRange(start, start + replaced.length);
        } else {
            $message.value = fn(value);
        }

        syncAll();
        $message.focus();
    }

    function buildSnippet() {
        const id = String($avatar.value || "-1").trim();
        const color = String($colorText.value || "SkyBlue").trim() || "SkyBlue";
        const msg = String($message.value || "").trim();
        if (isDefaultColorMode()) {
            return `[hg_avatar=${id}]${msg}[/hg_avatar]`;
        }
        return `[hg_avatar=${id},${color}]${msg}[/hg_avatar]`;
    }

    function syncPreview() {
        const id = encodeURIComponent(String($avatar.value || "-1").trim());
        const paletteRaw = isDefaultColorMode()
            ? "SkyBlue"
            : (String($colorText.value || "SkyBlue").trim() || "SkyBlue");
        const palette = encodeURIComponent(paletteRaw);
        const msg = encodeURIComponent(String($message.value || " ").trim() || " ");
        $preview.src = `/forum/message?id=${id}&palette=${palette}&msg=${msg}`;
    }

    function syncColorModeUi() {
        const defaultMode = isDefaultColorMode();
        $colorPicker.disabled = defaultMode;
        $colorText.disabled = defaultMode;
        $colorRow.classList.toggle("is-disabled", defaultMode);
        $colorDot.style.background = defaultMode ? "transparent" : colorToCss($colorText.value);
    }

    function syncAll() {
        $code.textContent = buildSnippet();
        syncColorModeUi();
        syncPreview();
    }

    $search.addEventListener("input", () => {
        renderAvatarOptions($search.value);
        syncAll();
    });

    $avatar.addEventListener("change", syncAll);

    $colorPicker.addEventListener("input", () => {
        $colorText.value = $colorPicker.value;
        syncAll();
    });

    $colorText.addEventListener("input", () => {
        const hex = hexToPickerSafe($colorText.value);
        if (hex) $colorPicker.value = hex;
        syncAll();
    });

    $colorPreset.addEventListener("change", () => {
        const value = String($colorPreset.value || "").trim();
        if (value === "__CHAR_DEFAULT__") {
            syncAll();
            return;
        }
        if (!value) return;
        $colorText.value = value;
        const hex = hexToPickerSafe(value);
        if (hex) $colorPicker.value = hex;
        syncAll();
    });

    $message.addEventListener("input", syncAll);

    document.querySelectorAll("[data-transform]").forEach(btn => {
        btn.addEventListener("click", () => {
            const t = String(btn.getAttribute("data-transform") || "");
            if (t === "mountain") applyTransform(letterCaseMountain);
            if (t === "upper") applyTransform(v => String(v).toUpperCase());
            if (t === "lower") applyTransform(v => String(v).toLowerCase());
            if (t === "dialog") applyTransform(v => `\"${String(v).trim()}\"`);
            if (t === "pause") applyTransform(v => String(v).replace(/([,.!?;:])(\S)/g, "$1 $2"));
        });
    });

    $copy.addEventListener("click", async () => {
        const text = $code.textContent || "";
        if (!text) return;
        const old = $copy.textContent;
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                const ta = document.createElement("textarea");
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand("copy");
                ta.remove();
            }
            $copy.textContent = "OK";
        } catch (e) {
            $copy.textContent = "ERR";
        }
        setTimeout(() => { $copy.textContent = old; }, 1200);
    });

    renderAvatarOptions("");
    syncAll();
})();
</script>
