<?php
// Obtener id o pretty-id
$docRaw = $_GET['b'] ?? '';
$docId = resolve_pretty_id($link, 'fact_docs', (string)$docRaw) ?? 0;
if ($docId <= 0) { print("Documento inválido."); }

// Asegurarse de que la conexión a la base de datos ($link) esté definida y sea válida
if (!$link) {
    print("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Consulta preparada (id = ?, no LIKE)
$Query = "SELECT dz.title, d.kind as seccion, dz.texto, dz.source
          FROM fact_docs dz
          LEFT JOIN dim_doc_categories d ON d.id = dz.seccion
          WHERE dz.id = ? LIMIT 1";
$stmt = mysqli_prepare($link, $Query);
if (!$stmt) { print("Error al preparar la consulta: " . mysqli_error($link)); }

mysqli_stmt_bind_param($stmt, 'i', $docId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if (!$result) { print("Error en la consulta: " . mysqli_error($link)); }

$ResultQuery = mysqli_fetch_assoc($result);
mysqli_free_result($result);
mysqli_stmt_close($stmt);

if (!$ResultQuery) { 
  print("Documento no encontrado."); 
} else {

$titleDoc = (string)$ResultQuery["title"];
$texto    = (string)$ResultQuery["texto"];   // HTML (Quill) -> se imprime tal cual
$source   = (string)($ResultQuery["source"] ?? '');
$secciDoc = (string)($ResultQuery["seccion"] ?? 'Documento');

// Para tu sistema de títulos
$pageSect   = "Documento";
$pageTitle2 = $titleDoc;
setMetaFromPage($titleDoc . " | Documentos | Heaven's Gate", meta_excerpt($texto), null, 'article');

// Barra navegación (la tuya)
include("app/partials/main_nav_bar.php");
?>

<style>
/* =========================================================
   THEMES (OG / Claro / Terminal) + UI selector
   ========================================================= */

:root{
  --doc-maxw: 600px;
  --doc-radius: 14px;

  /* Default: OG */
  --bg: linear-gradient(180deg, #05014E 0%, #07071a 100%);
  --card-bg: rgba(5, 1, 78, 0.30);
  --card-border: rgba(0, 0, 136, 0.65);
  --shadow: 0 12px 34px rgba(0,0,0,.35);

  --title: #E9F6FF;
  --meta: #B8E7FF;
  --chip-bg: rgba(0, 17, 153, 0.55);
  --chip-border: rgba(51, 255, 255, 0.25);
  --chip-text: #33FFFF;

  --text: #EAF2FF;
  --muted: #9dd;

  --a: #33FFFF;
  --code-bg: rgba(0,0,0,0.35);
  --blockquote-bg: rgba(0,0,0,0.18);
  --blockquote-border: rgba(51,255,255,0.45);

  --source-bg: rgba(0,0,0,0.18);
  --source-border: rgba(51,255,255,0.20);

  --mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
  --sans: Verdana, Arial, sans-serif;
  --serif: Georgia, "Times New Roman", serif;
}

/* Claro */
[data-doc-theme="light"]{
  --bg: linear-gradient(180deg, #05014E 0%, #0b0d22 100%);
  --card-bg: rgba(255,255,255,0.92);
  --card-border: rgba(0,0,0,0.15);
  --shadow: 0 10px 30px rgba(0,0,0,.25);

  --title: #0a0d1a;
  --meta: #39415a;
  --chip-bg: rgba(5,1,78,0.08);
  --chip-border: rgba(5,1,78,0.14);
  --chip-text: #05014E;

  --text: #0f1424;
  --muted: #2c3652;

  --a: #0b4aa7;
  --code-bg: rgba(0,0,0,0.06);
  --blockquote-bg: rgba(5,1,78,0.06);
  --blockquote-border: #05014E;

  --source-bg: rgba(5,1,78,0.05);
  --source-border: rgba(5,1,78,0.25);
}

/* Terminal */
[data-doc-theme="terminal"]{
  --bg: radial-gradient(1200px 700px at 30% 10%, rgba(0,255,128,0.10) 0%, rgba(0,0,0,0) 55%),
        linear-gradient(180deg, #030507 0%, #000 100%);
  --card-bg: rgba(0,0,0,0.72);
  --card-border: rgba(0,255,128,0.28);
  --shadow: 0 14px 44px rgba(0,0,0,.55);

  --title: #B8FFC9;
  --meta: #7CFFAE;
  --chip-bg: rgba(0,255,128,0.10);
  --chip-border: rgba(0,255,128,0.28);
  --chip-text: #7CFFAE;

  --text: #B8FFC9;
  --muted: rgba(184,255,201,0.75);

  --a: #7CFFAE;
  --code-bg: rgba(0,255,128,0.08);
  --blockquote-bg: rgba(0,255,128,0.06);
  --blockquote-border: rgba(0,255,128,0.38);

  --source-bg: rgba(0,255,128,0.06);
  --source-border: rgba(0,255,128,0.22);
}

/* Layout */
.doc-page{
  /* background: var(--bg); */
  background: none;
  padding: 16px 12px 34px;
  min-height: 60vh;
}
.doc-wrap{
  width: min(var(--doc-maxw), 96vw);
  margin: 0 auto;
  position: relative;
}

.theme-switch{
  display:flex;
  gap:8px;
  justify-content:flex-end;
  margin: 4px 0 10px;
}
.theme-btn{
  border: 1px solid var(--card-border);
  background: rgba(0,0,0,0.15);
  color: var(--meta);
  padding: 6px 10px;
  border-radius: 999px;
  cursor:pointer;
  font-family: var(--sans);
  font-size: 12px;
  transition: transform .06s ease, filter .12s ease, background .12s ease;
}
.theme-btn:hover{ filter: brightness(1.1); }
.theme-btn:active{ transform: translateY(1px); }
.theme-btn.active{
  background: var(--chip-bg);
  color: var(--chip-text);
  border-color: var(--chip-border);
}

.doc-card{
  background: var(--card-bg);
  color: var(--text);
  border: 1px solid var(--card-border);
  border-radius: var(--doc-radius);
  box-shadow: var(--shadow);
  padding: 18px 18px 10px;
  overflow: hidden;
  position: relative;
}

/* Terminal vibe extra: scanline suave */
[data-doc-theme="terminal"] .doc-card::before{
  content:"";
  position:absolute;
  inset:0;
  background:
    repeating-linear-gradient(
      180deg,
      rgba(0,255,128,0.04) 0px,
      rgba(0,255,128,0.04) 1px,
      rgba(0,0,0,0) 3px,
      rgba(0,0,0,0) 5px
    );
  pointer-events:none;
  opacity: .20;
}

.doc-title{
  margin: 0 0 10px;
  font-family: var(--sans);
  font-size: 22px;
  line-height: 1.15;
  color: var(--title);
}

.doc-meta{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  align-items:center;
  margin: 0 0 14px;
  font-family: var(--sans);
  font-size: 12px;
  color: var(--meta);
}
.doc-chip{
  display:inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--chip-bg);
  border: 1px solid var(--chip-border);
  color: var(--chip-text);
  font-weight: 700;
}

/* Cuerpo */
.doc-body{
  font-family: var(--serif);
  font-size: 16px;
  line-height: 1.7;
  color: var(--text);
  padding: 10px 4px 8px;
}

/* Terminal: tipografía mono y tamaño un pelín menor */
[data-doc-theme="terminal"] .doc-body{
  font-family: var(--mono);
  font-size: 14px;
  line-height: 1.75;
}

/* Respeta HTML de Quill */
.doc-body p{ margin: 0 0 12px; text-align: left!important; }
.doc-body h1,.doc-body h2,.doc-body h3{
  font-family: var(--sans);
  color: var(--title);
  margin: 18px 0 10px;
  line-height: 1.2;
}
[data-doc-theme="terminal"] .doc-body h1,
[data-doc-theme="terminal"] .doc-body h2,
[data-doc-theme="terminal"] .doc-body h3{
  font-family: var(--mono);
  letter-spacing: 0.02em;
}

.doc-body blockquote{
  margin: 12px 0;
  padding: 10px 14px;
  border-left: 4px solid var(--blockquote-border);
  background: var(--blockquote-bg);
  border-radius: 10px;
}
.doc-body ul, .doc-body ol{ margin: 0 0 12px 22px; }
.doc-body li{ margin: 4px 0; }
.doc-body a{ color: var(--a); text-decoration: underline; }

.doc-body code{
  background: var(--code-bg);
  padding: 2px 6px;
  border-radius: 6px;
  font-family: var(--mono);
  font-size: 0.95em;
}
.doc-body pre{
  background: var(--code-bg);
  padding: 12px;
  border-radius: 12px;
  overflow:auto;
  border: 1px solid rgba(255,255,255,0.08);
}

/* Fuente */
.doc-source{
  margin-top: 14px;
  padding: 12px 14px;
  background: var(--source-bg);
  border: 1px dashed var(--source-border);
  border-radius: 12px;
  font-family: var(--sans);
  font-size: 12px;
  color: var(--muted);
}
.doc-source strong{ color: var(--title); }

/* Pequeño “prompt” en terminal */
[data-doc-theme="terminal"] .doc-meta::before{
  content: "heavensgate@db:~$ cat ";
  font-family: var(--mono);
  color: rgba(124,255,174,0.75);
}
</style>

<div class="doc-page" id="docRoot">
  <div class="doc-wrap">
    <div class="theme-switch" aria-label="Cambiar tema">
      <button type="button" class="theme-btn" data-theme="og" title="Tema OG">🟦</button>
      <button type="button" class="theme-btn" data-theme="light" title="Tema claro">⬜</button>
      <button type="button" class="theme-btn" data-theme="terminal" title="Tema terminal">🟩</button>
    </div>

    <div class="doc-card">
      <h1 class="doc-title"><?= htmlspecialchars($titleDoc, ENT_QUOTES, 'UTF-8') ?></h1>

      <div class="doc-meta">
        <span class="doc-chip"><?= htmlspecialchars($secciDoc, ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <div class="doc-body">
        <?= $texto /* HTML guardado */ ?>
      </div>

      <?php if (trim(strip_tags($source)) !== ''): ?>
        <div class="doc-source">
          <strong>Fuente:</strong>
          <div style="margin-top:6px;"><?= $source ?></div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function(){
  var root = document.getElementById('docRoot');
  var btns = document.querySelectorAll('.theme-btn');
  var KEY = 'hg_doc_theme';

  function apply(theme){
    // og = sin atributo
    if (theme === 'og') root.removeAttribute('data-doc-theme');
    else root.setAttribute('data-doc-theme', theme);

    btns.forEach(function(b){
      b.classList.toggle('active', b.getAttribute('data-theme') === theme);
    });

    try { localStorage.setItem(KEY, theme); } catch(e){}
  }

  btns.forEach(function(b){
    b.addEventListener('click', function(){
      apply(b.getAttribute('data-theme') || 'og');
    });
  });

  var saved = 'og';
  try { saved = localStorage.getItem(KEY) || 'og'; } catch(e){}
  if (!saved) saved = 'og';
  apply(saved);
})();
</script>


<?php } ?>

