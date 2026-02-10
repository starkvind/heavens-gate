<?php
setMetaFromPage("Dones | Heaven's Gate", "Listado completo de dones en formato extendido.", null, 'website');
// =======================
// Página: Todos los fact_gifts (corporativo)
// Estilo mysqli / $link
// =======================

$pageSect = "Dones";
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

function anchor_id_gift($id) {
    return "gift_" . (int)$id;
}

// =======================
// 1) Query principal (LA TUYA)
// =======================
$consulta = "
select
    d.id as gift_id,
    d.nombre as gift_name,
    ntd.name as gift_type,
    d.grupo as gift_category,
    d.rango as gift_level,
    d.atributo as gift_roll_attribute,
    d.habilidad as gift_roll_skill,
    d.descripcion as gift_description,
    d.sistema as gift_roll_description,
    d.ferasistema as gift_fera_system,
    nb.name as gift_origin
from fact_gifts d
    left join dim_gift_types ntd on d.tipo = ntd.id
    left join dim_bibliographies nb on d.origen = nb.id
order by d.origen, d.rango
";

$stmt = $link->prepare($consulta);
$stmt->execute();
$result = $stmt->get_result();

$gifts = [];
while ($row = $result->fetch_assoc()) {
    $gifts[] = $row;
}
$total = count($gifts);

// =======================
// Agrupar dones por Origen (para el índice)
// =======================
$giftsByOrigin = [];

foreach ($gifts as $g) {
    $origin = trim($g['gift_origin'] ?? '');
    if ($origin === '') {
        $origin = 'Sin origen';
    }
    $giftsByOrigin[$origin][] = $g;
}

// =======================
// 2) Render (CSS + HTML)
// =======================
?>
<style>
/* ==========================
   Encapsulado: SOLO .hg-dones
   ========================== */
.hg-dones {
    --bg: #f6f7fb;
    --card: #ffffff;
    --text: #1b1f2a;
    --muted: #667085;
    --line: #e6e8f0;
    --shadow: 0 10px 24px rgba(16, 24, 40, .08);
    --shadow2: 0 6px 14px rgba(16, 24, 40, .08);
    --radius: 14px;
    --radius2: 10px;
    --accent: #2b6cb0;
    --accent2: #0f172a;
    --chip: #f2f4f7;

    background: var(--bg);
    color: var(--text);
    font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    line-height: 1.45;
    padding: 20px 0 40px;
}

/* neutralizo solo aquí */
.hg-dones h2, .hg-dones h3 {
    color: var(--accent2);
    margin: 0;
    padding: 0;
    border: 0;
    background: none;
}

.hg-dones a { color: var(--accent); text-decoration: none; }
.hg-dones a:hover { text-decoration: underline; }

.hg-dones .wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 16px;
}

.hg-dones .hero {
    background: linear-gradient(180deg, #ffffff, #fbfbfe);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    box-shadow: var(--shadow2);
    padding: 18px 18px;
}

.hg-dones .hero .title {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.hg-dones .hero h2 { font-size: 22px; letter-spacing: .2px; }
.hg-dones .hero .count { color: var(--muted); font-size: 13px; }
.hg-dones .hero p { margin: 10px 0 0; color: var(--muted); }

/* Índice */
.hg-dones .index {
    margin-top: 16px;
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    box-shadow: var(--shadow2);
    padding: 16px 16px;
}
.hg-dones .index .head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.hg-dones .index .head h3 {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--muted);
}
.hg-dones .index .grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 14px;
}
@media (max-width: 820px) { .hg-dones .index .grid { grid-template-columns: 1fr; } }

.hg-dones .index a.item {
    display: block;
    padding: 10px 12px;
    border: 1px solid var(--line);
    border-radius: var(--radius2);
    background: #fff;
    transition: transform .08s ease, box-shadow .08s ease;
}
.hg-dones .index a.item:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow2);
    text-decoration: none;
}
.hg-dones .index .label { display: flex; gap: 10px; align-items: flex-start; }
.hg-dones .index .badge {
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
.hg-dones .index .txt { min-width: 0; }
.hg-dones .index .meta {
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.hg-dones .index .name {
    font-size: 14px;
    color: var(--accent2);
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Origen blocks */
.hg-dones .index-origin { margin-top: 14px; }
.hg-dones .index-origin-title {
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
.hg-dones .index-origin-count { font-size: 12px; font-weight: 500; color: var(--muted); }

/* Cards */
.hg-dones .cards { margin-top: 18px; display: grid; gap: 14px; }
.hg-dones .card {
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    box-shadow: var(--shadow2);
    padding: 16px 16px;
}
.hg-dones .card .topline {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.hg-dones .card .topline .name {
    font-size: 18px;
    font-weight: 700;
    color: var(--accent2);
    margin: 0;
}
.hg-dones .card .topline .back {
    font-size: 12px;
    color: var(--muted);
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid var(--line);
    background: #fff;
    white-space: nowrap;
}

/* Chips */
.hg-dones .chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}
.hg-dones .chip {
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
.hg-dones .sections {
    margin-top: 14px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.hg-dones .box {
    border: 1px solid var(--line);
    border-radius: var(--radius2);
    background: #fff;
    padding: 12px 12px;
}
.hg-dones .box h3 {
    font-size: 13px;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 8px;
}
.hg-dones .box .content { color: var(--text); font-size: 14px; }

/* Acotar HTML de desc/sistema */
.hg-dones .box .content p { margin: 0 0 10px; }
.hg-dones .box .content p:last-child { margin-bottom: 0; }
.hg-dones .box .content ul,
.hg-dones .box .content ol { margin: 8px 0 8px 22px; }
.hg-dones .box .content hr { border: 0; border-top: 1px solid var(--line); margin: 10px 0; }
.hg-dones .box .content img { max-width: 100%; height: auto; }

.hg-dones .footer { margin-top: 16px; color: var(--muted); font-size: 13px; text-align: right; }

/* Botón */
.hg-dones .hg-print-btn{
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
.hg-dones .hg-print-btn:hover{ text-decoration:none; transform: translateY(-1px); }

/* Modo imprimible (pantalla) */
.hg-dones.hg-print {
    --bg: #ffffff;
    --card: #ffffff;
    --text: #000000;
    --muted: #333333;
    --line: #d0d0d0;
    --shadow: none;
    --shadow2: none;
    --chip: #f3f3f3;
    padding: 0;
}
.hg-dones.hg-print .hero{ box-shadow:none; background:#fff; }
.hg-dones.hg-print .index{ display:none; }
.hg-dones.hg-print .back{ display:none; }

/* Impresión real: ocultar todo menos .hg-dones */
@media print {
    body * { visibility: hidden !important; }

    .hg-dones,
    .hg-dones * { visibility: visible !important; }

    .hg-dones {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        background: #fff !important;
        color: #000 !important;
        padding: 0 !important;
    }

    .hg-dones .wrap {
        max-width: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .hg-dones .hg-print-btn { display:none !important; }
    .hg-dones .back { display:none !important; }
    .hg-dones .index { display:none !important; } /* si quieres índice en papel, comenta */

    .hg-dones .card {
        border: 1px solid #bbb !important;
        box-shadow: none !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .hg-dones .chip {
        background: #f3f3f3 !important;
        border: 1px solid #cfcfcf !important;
        color: #111 !important;
    }

    .hg-dones .box { border: 1px solid #ccc !important; }

    .hg-dones h3 { break-after: avoid-page; page-break-after: avoid; }

    .hg-dones a { color:#000 !important; text-decoration:none !important; }
}
</style>

<div class="hg-dones<?php echo $printMode ? ' hg-print' : ''; ?>">
  <div class="wrap">

    <a id="top"></a>

    <div class="hero">
      <div class="title">
        <h2>Dones</h2>
        <div class="count">Total: <b><?php echo (int)$total; ?></b></div>
      </div>
      <p>Listado completo de dones, con acceso rápido y ficha completa (descripción + sistema).</p>
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

        <?php foreach ($giftsByOrigin as $origin => $list): ?>
          <div class="index-origin">
            <h4 class="index-origin-title">
              <?php echo h($origin); ?>
              <span class="index-origin-count">(<?php echo count($list); ?>)</span>
            </h4>

            <div class="grid">
              <?php foreach ($list as $g):
                  $id = (int)$g['gift_id'];
                  $anchor = anchor_id_gift($id);

                  $name = h($g['gift_name']);
                  $type = h($g['gift_type'] ?? '');
                  $lvl  = h($g['gift_level'] ?? '');
              ?>
                <a class="item" href="#<?php echo h($anchor); ?>">
                  <div class="label">
                    <div class="badge">◆</div>
                    <div class="txt">
                      <div class="meta"><?php echo ($type !== '' || $lvl !== '') ? "[{$type} · Nv. {$lvl}]" : ""; ?></div>
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
      <?php foreach ($gifts as $g):
          $id = (int)$g['gift_id'];
          $anchor = anchor_id_gift($id);

          $name = h($g['gift_name']);
          $type = h($g['gift_type'] ?? '');
          $cat  = h($g['gift_category'] ?? '');
          $lvl  = h($g['gift_level'] ?? '');
          $attr = h($g['gift_roll_attribute'] ?? '');
          $skill= h($g['gift_roll_skill'] ?? '');
          $fera = h($g['gift_fera_system'] ?? '');
          $orig = h($g['gift_origin'] ?? '');

          // CAMPOS LARGOS → NO htmlspecialchars()
          $desc = $g['gift_description'] ?: "<p>Descripción no disponible</p>";
          $roll = $g['gift_roll_description'] ?: "<p><i>Sistema no disponible</i></p>";

          $rollShort = trim(($g['gift_roll_attribute'] ?? '') . ($attr !== '' && $skill !== '' ? ' + ' : '') . ($g['gift_roll_skill'] ?? ''));
          $rollShortSafe = h($rollShort);
      ?>
        <article class="card" id="<?php echo h($anchor); ?>">
          <div class="topline">
            <h3 class="name"><?php echo $name; ?></h3>
            <a class="back" href="#top">↑ Arriba</a>
          </div>

          <div class="chips">
            <?php if ($type !== ''): ?><span class="chip"><?php echo $type; ?></span><?php endif; ?>
            <?php if ($cat  !== ''): ?><span class="chip"><?php echo $cat; ?></span><?php endif; ?>
            <?php if ($lvl  !== ''): ?><span class="chip">Rango <?php echo $lvl; ?></span><?php endif; ?>
            <?php if ($rollShort !== ''): ?><span class="chip"><?php echo $rollShortSafe; ?></span><?php endif; ?>
            <?php if ($fera !== ''): ?><span class="chip"><?php echo $fera; ?></span><?php endif; ?>
            <?php if ($orig !== ''): ?><span class="chip"><?php echo $orig; ?></span><?php endif; ?>
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
      Dones hallados: <b><?php echo (int)$total; ?></b>
    </div>

  </div>
</div>
