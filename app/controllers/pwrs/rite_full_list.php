<?php
setMetaFromPage("Rituales | Heaven's Gate", "Listado completo de rituales en formato extendido.", null, 'website');
// =======================
// Página: Todos los rituales (corporativo)
// Estilo mysqli / $link
// =======================

$pageSect = "Rituales";
$_SESSION['punk2'] = $pageSect;
$printMode = isset($_GET['print']) && $_GET['print'] == '1';

if (!$printMode) {
    include("app/partials/main_nav_bar.php");
}

// =======================
// Helpers
// =======================
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function anchor_id($id) {
    return "ritual_" . (int)$id;
}

// =======================
// 1) Query principal (LA TUYA)
// =======================
$consulta = "
select
    nr.id as ritual_id,
    nr.name as ritual_name,
    CONCAT(
        'Rito',
        CASE
            WHEN ntr.determinante <> '' THEN CONCAT(' ', ntr.determinante)
            ELSE ''
        END,
        ' ',
        ntr.name
    ) as ritual_type,
    nr.nivel as ritual_level,
    nr.raza as ritual_species,
    nr.desc as ritual_description,
    nr.syst as ritual_roll_description,
    nr.sistema as ritual_fera_system,
    nb.name as ritual_origin
from fact_rites nr
    left join dim_rite_types ntr on nr.tipo = ntr.id
    left join dim_bibliographies nb on nr.origen = nb.id
order by
    nr.origen,
    nr.nivel
";

$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$rituales = [];
while ($row = $result->fetch_assoc()) {
    $rituales[] = $row;
}
$total = count($rituales);

// =======================
// Agrupar rituales por Origen (para el índice)
// =======================
$ritualesPorOrigen = [];

foreach ($rituales as $r) {
    $origen = trim($r['ritual_origin'] ?? '');
    if ($origen === '') {
        $origen = 'Sin origen';
    }
    $ritualesPorOrigen[$origen][] = $r;
}

// =======================
// 2) Render (CSS + HTML)
// =======================
?>
<style>
/* ==========================
   Encapsulado: SOLO .hg-rituales
   ========================== */
.hg-rituales {
    --bg: #f6f7fb;
    --card: #ffffff;
    --text: #1b1f2a;
    --muted: #667085;
    --line: #e6e8f0;
    --shadow: 0 10px 24px rgba(16, 24, 40, .08);
    --shadow2: 0 6px 14px rgba(16, 24, 40, .08);
    --radius: 14px;
    --radius2: 10px;
    --accent: #2b6cb0; /* azul corporativo */
    --accent2: #0f172a;
    --chip: #f2f4f7;

    background: var(--bg);
    color: var(--text);
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    line-height: 1.45;
    padding: 20px 0 40px;
}

/* neutralizo solo aquí */
.hg-rituales h2, .hg-rituales h3 {
    color: var(--accent2);
    margin: 0;
    padding: 0;
    border: 0;
    background: none;
}

.hg-rituales a { color: var(--accent); text-decoration: none; }
.hg-rituales a:hover { text-decoration: underline; }

.hg-rituales .wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 16px;
}

.hg-rituales .hero {
    background: linear-gradient(180deg, #ffffff, #fbfbfe);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    box-shadow: var(--shadow2);
    padding: 18px 18px;
}

.hg-rituales .hero .title {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.hg-rituales .hero h2 { font-size: 22px; letter-spacing: .2px; }
.hg-rituales .hero .count { color: var(--muted); font-size: 13px; }
.hg-rituales .hero p { margin: 10px 0 0; color: var(--muted); }

/* Índice */
.hg-rituales .index {
    margin-top: 16px;
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    box-shadow: var(--shadow2);
    padding: 16px 16px;
}
.hg-rituales .index .head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.hg-rituales .index .head h3 {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--muted);
}
.hg-rituales .index .grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 14px;
}
@media (max-width: 820px) { .hg-rituales .index .grid { grid-template-columns: 1fr; } }

.hg-rituales .index a.item {
    display: block;
    padding: 10px 12px;
    border: 1px solid var(--line);
    border-radius: var(--radius2);
    background: #fff;
    transition: transform .08s ease, box-shadow .08s ease;
}
.hg-rituales .index a.item:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow2);
    text-decoration: none;
}
.hg-rituales .index .label { display: flex; gap: 10px; align-items: flex-start; }
.hg-rituales .index .badge {
    flex: 0 0 auto;
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: var(--chip);
    display: grid;
    place-items: center;
    border: 1px solid var(--line);
    margin-top: 1px;
}
.hg-rituales .index .txt { min-width: 0; }
.hg-rituales .index .meta {
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.hg-rituales .index .name {
    font-size: 14px;
    color: var(--accent2);
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Cards */
.hg-rituales .cards { margin-top: 18px; display: grid; gap: 14px; }

.hg-rituales .card {
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    box-shadow: var(--shadow2);
    padding: 16px 16px;
}

.hg-rituales .card .topline {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.hg-rituales .card .topline .name {
    font-size: 18px;
    font-weight: 700;
    color: var(--accent2);
    margin: 0;
}

.hg-rituales .card .topline .back {
    font-size: 12px;
    color: var(--muted);
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: #fff;
    white-space: nowrap;
}

/* Chips */
.hg-rituales .chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}
.hg-rituales .chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    background: var(--chip);
    border: 1px solid var(--line);
    color: #344054;
}

/* Boxes */
.hg-rituales .sections {
    margin-top: 14px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.hg-rituales .box {
    border: 1px solid var(--line);
    border-radius: var(--radius2);
    background: #fff;
    padding: 12px 12px;
}
.hg-rituales .box h3 {
    font-size: 13px;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 8px;
}
.hg-rituales .box .content { color: var(--text); font-size: 14px; }

/* Acotar HTML de desc/syst */
.hg-rituales .box .content p { margin: 0 0 10px; }
.hg-rituales .box .content p:last-child { margin-bottom: 0; }
.hg-rituales .box .content ul,
.hg-rituales .box .content ol { margin: 8px 0 8px 22px; }
.hg-rituales .box .content hr { border: 0; border-top: 1px solid var(--line); margin: 10px 0; }
.hg-rituales .box .content img { max-width: 100%; height: auto; }

.hg-rituales .footer { margin-top: 16px; color: var(--muted); font-size: 13px; text-align: right; }

.hg-rituales .index-origin { margin-top: 14px; }
.hg-rituales .index-origin-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--accent2);
    margin: 8px 0 8px;
    padding-bottom: 4px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: baseline;
    gap: 6px;
}
.hg-rituales .index-origin-count { font-size: 12px; font-weight: 500; color: var(--muted); }

/* Botón */
.hg-rituales .hg-print-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 12px;
    border-radius: 999px;
    border: 1px solid var(--line);
    background:#fff;
    color: var(--accent2);
    font-weight: 600;
    font-size: 13px;
    box-shadow: var(--shadow2);
}
.hg-rituales .hg-print-btn:hover{ text-decoration:none; transform: translateY(-1px); }

/* ==========================
   MODO IMPRIMIBLE (pantalla)
   ========================== */
.hg-rituales.hg-print {
    --bg: #ffffff;
    --card: #ffffff;
    --text: #000000;
    --muted: #333333;
    --line: #d0d0d0;
    --shadow: none;
    --shadow2: none;
    --chip: #f3f3f3; /* chips visibles y suaves */
    padding: 0;
}
.hg-rituales.hg-print .hero{ box-shadow:none; background:#fff; }

/* En modo print en pantalla: el índice suele sobrar. Si lo quieres, comenta esta línea. */
.hg-rituales.hg-print .index{ display:none; }

/* “Arriba” no aporta en impresión */
.hg-rituales.hg-print .back{ display:none; }

/* ==========================
   IMPRESIÓN REAL: OCULTAR TODO MENOS RITUALES
   ========================== */
@media print {

    /* 1) Oculta TODO el documento por defecto */
    body * {
        visibility: hidden !important;
    }

    /* 2) Re-activa solo el bloque imprimible */
    .hg-rituales,
    .hg-rituales * {
        visibility: visible !important;
    }

    /* 3) Coloca .hg-rituales en el origen del documento (evita “marcos” azules) */
    .hg-rituales {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        background: #fff !important;
        color: #000 !important;
        padding: 0 !important;
    }

    /* Ajustes de layout para papel */
    .hg-rituales .wrap {
        max-width: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Quita cosas no imprimibles */
    .hg-rituales .hg-print-btn { display:none !important; }
    .hg-rituales .back { display:none !important; }
    .hg-rituales .index { display:none !important; } /* si quieres índice en papel, comenta */

    /* Tarjetas: sin sombras, con borde */
    .hg-rituales .card {
        border: 1px solid #bbb !important;
        box-shadow: none !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    /* Chips: SI se imprimen (lo que pedías) */
    .hg-rituales .chip {
        background: #f3f3f3 !important;
        border: 1px solid #cfcfcf !important;
        color: #111 !important;
    }

    .hg-rituales .box { border: 1px solid #ccc !important; }

    /* Evita títulos huérfanos */
    .hg-rituales h3 { break-after: avoid-page; page-break-after: avoid; }

    /* Enlaces: sin azul subrayado */
    .hg-rituales a { color:#000 !important; text-decoration:none !important; }
}
</style>

<div class="hg-rituales<?php echo $printMode ? ' hg-print' : ''; ?>">
  <div class="wrap">

    <a id="top"></a>

    <div class="hero">
      <div class="title">
        <h2>Rituales</h2>
        <div class="count">Total: <b><?php echo (int)$total; ?></b></div>
      </div>
      <p>Listado completo de rituales, con acceso rápido y ficha completa (descripción + sistema).</p>
      <?php if (!$printMode): ?>
        <p style="margin-top:12px;">
          <?php $printHref = htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?') . '?print=1'); ?>
          <a href="<?php echo $printHref; ?>" class="hg-print-btn">🖨️ Versión imprimible</a>
        </p>
      <?php endif; ?>
    </div>

<?php if ($total > 0): ?>
  <div class="index">
    <div class="head">
      <h3>Índice por Origen</h3>
      <div class="count"><?php echo (int)$total; ?> entradas</div>
    </div>

    <?php foreach ($ritualesPorOrigen as $origen => $lista): ?>
      <div class="index-origin">
        <h4 class="index-origin-title">
          <?php echo h($origen); ?>
          <span class="index-origin-count">(<?php echo count($lista); ?>)</span>
        </h4>

        <div class="grid">
          <?php foreach ($lista as $r):
              $id = (int)$r['ritual_id'];
              $anchor = anchor_id($id);

              $name = h($r['ritual_name']);
              $type = h($r['ritual_type']);
              $lvl  = h($r['ritual_level']);
          ?>
            <a class="item" href="#<?php echo h($anchor); ?>">
              <div class="label">
                <div class="badge">◆</div>
                <div class="txt">
                  <div class="meta"><?php echo "[{$type} · Nv. {$lvl}]"; ?></div>
                  <div class="name"><?php echo $name; ?></div>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

    <div class="cards">
      <?php foreach ($rituales as $r):
          $id   = (int)$r['ritual_id'];
          $anchor = anchor_id($id);

          $name = h($r['ritual_name']);
          $type = h($r['ritual_type']);
          $lvl  = h($r['ritual_level']);
          $race = h($r['ritual_species']);
          $sys  = h($r['ritual_fera_system']);
          $orig = h($r['ritual_origin'] ?? '');

          // CAMPOS LARGOS → NO htmlspecialchars (permitimos HTML guardado)
          $desc = $r['ritual_description'] ?: "<p>Descripción no disponible</p>";
          $roll = $r['ritual_roll_description'] ?: "<p><i>Sistema no disponible</i></p>";
      ?>
        <article class="card" id="<?php echo h($anchor); ?>">
          <div class="topline">
            <h3 class="name"><?php echo $name; ?></h3>
            <a class="back" href="#top">↑ Arriba</a>
          </div>

          <div class="chips">
            <span class="chip"><?php echo $type; ?></span>
            <span class="chip">Nivel <?php echo $lvl; ?></span>

            <?php if ($race !== '' && $sys != $race): ?>
              <span class="chip"><?php echo $race; ?></span>
            <?php endif; ?>

            <span class="chip"><?php echo $sys; ?></span>

            <?php if ($orig !== ''): ?>
              <span class="chip"><?php echo $orig; ?></span>
            <?php endif; ?>

            <?php if (1 == 0): ?>
              <span class="chip">ID <?php echo (int)$id; ?></span>
            <?php endif; ?>
          </div>

          <div class="sections">
            <div class="box">
              <h3>Descripción</h3>
              <div class="content"><?php echo $desc; ?></div>
            </div>

            <?php if ($roll != "<p><i>Sistema no disponible</i></p>"): ?>
              <div class="box">
                <h3>Sistema</h3>
                <div class="content"><?php echo $roll; ?></div>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="footer">
      Rituales hallados: <b><?php echo (int)$total; ?></b>
    </div>

  </div>
</div>
