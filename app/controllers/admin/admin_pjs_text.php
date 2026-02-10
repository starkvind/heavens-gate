<?php
// admin_pjs_detalles.php — Edición de campos “complejos” por personaje
// Campos: id, nombre, estado, causamuerte, cumple, rango, infotext
// Requiere: $link (mysqli) ya inicializado

if (!isset($link) || !$link) { die("Error de conexión a la base de datos."); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* -------- Helpers -------- */
function preview_text(string $s, int $len = 180): string {
  // 1) quita etiquetas html; 2) decodifica entidades; 3) colapsa espacios; 4) corta y añade …
  $s = strip_tags($s);
  $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $s = preg_replace('/\s+/u', ' ', $s);
  $s = trim($s);
  if (mb_strlen($s) > $len) $s = mb_substr($s, 0, $len - 1) . '…';
  return $s;
}

/* -------- Lista de estados válidos desde la BD -------- */
$estado_opts = [];
if ($rs = $link->query("SELECT estado FROM fact_characters GROUP BY 1 ORDER BY 1")) {
  while ($row = $rs->fetch_assoc()) {
    $val = (string)($row['estado'] ?? '');
    $estado_opts[$val] = $val;
  }
  $rs->close();
}
$estado_set = array_fill_keys(array_keys($estado_opts), true);

/* -------- POST: actualizar -------- */
$flash = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['detalle_action'] ?? '') === 'update') {
  $id          = intval($_POST['id'] ?? 0);
  $nombre      = trim($_POST['nombre'] ?? '');
  $estado      = (string)($_POST['estado'] ?? '');
  $causamuerte = trim($_POST['causamuerte'] ?? '');
  $cumple      = trim($_POST['cumple'] ?? '');   // lo tratamos como texto
  $rango       = trim($_POST['rango'] ?? '');
  $infotext    = trim($_POST['infotext'] ?? '');

  if ($id <= 0)       $flash[] = ['type'=>'error','msg'=>'⚠ Falta el ID del personaje.'];
  if ($nombre === '') $flash[] = ['type'=>'error','msg'=>'⚠ El nombre no puede estar vacío.'];
  if (!isset($estado_set[$estado])) $flash[] = ['type'=>'error','msg'=>'⚠ El estado no es válido.'];

  if (!array_filter($flash, fn($m)=>$m['type']==='error')) {
    $sql = "UPDATE fact_characters SET nombre=?, estado=?, causamuerte=?, cumple=?, rango=?, infotext=? WHERE id=?";
    if ($st = $link->prepare($sql)) {
      $st->bind_param("ssssssi", $nombre, $estado, $causamuerte, $cumple, $rango, $infotext, $id);
      if ($st->execute()) $flash[] = ['type'=>'ok','msg'=>'✅ Detalles actualizados.'];
      else                $flash[] = ['type'=>'error','msg'=>'❌ Error al actualizar: '.$st->error];
      $st->close();
    } else {
      $flash[] = ['type'=>'error','msg'=>'❌ Error al preparar UPDATE: '.$link->error];
    }
  }
}

/* -------- Cargar tabla -------- */
$rows = [];
$q = $link->query("SELECT id, nombre, estado, causamuerte, cumple, rango, infotext
                   FROM fact_characters
				   WHERE cronica NOT IN (2, 5, 6)
                   ORDER BY nombre ASC");
while ($r = $q->fetch_assoc()) { $rows[] = $r; }
$q->close();
?>
<style>
.panel-wrap { background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; }
.hdr { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:10px; }
.hdr h2 { margin:0; color:#33FFFF; font-size:16px; }

/* Tabla */
.table { width:100%; border-collapse:collapse; font-size:11px; font-family:Verdana,Arial,sans-serif; table-layout:fixed; }
.table th, .table td { border:1px solid #000088; padding:6px 8px; background:#05014E; color:#eee; vertical-align:top; }
.table th { background:#050b36; color:#33CCCC; text-align:left; }

/* Anchos fijos por columna (ajústalos si quieres) */
.table thead th:nth-child(1){ width:60px; }   /* ID */
.table thead th:nth-child(2){ width:220px; }  /* Nombre */
.table thead th:nth-child(3){ width:160px; }  /* Estado */
.table thead th:nth-child(4){ width:360px; }  /* Causa */
.table thead th:nth-child(5){ width:110px; }  /* Cumple */
.table thead th:nth-child(6){ width:140px; }  /* Rango */
.table thead th:nth-child(7){ width:360px; }  /* Info */
.table thead th:nth-child(8){ width:110px; }  /* Acciones */

/* Celdas: permitir salto de línea por defecto */
.table td { white-space:normal; word-wrap:break-word; overflow-wrap:anywhere; }
.nowrap { white-space:nowrap; }

/* Clips bonitos (2 líneas con elipsis) para columnas largas */
.clip-2 {
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
  overflow:hidden; text-overflow:ellipsis;
}
.clip-1 {
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;
}

.table tr:hover td { background:#000066; color:#33FFFF; }

/* Botones / inputs */
.inp, .ta, .select { background:#000033; color:#fff; border:1px solid #333; padding:6px 8px; font-size:12px; width:100%; box-sizing:border-box; }
.btn { background:#0d3a7a; color:#fff; border:1px solid #1b4aa0; border-radius:8px; padding:6px 10px; cursor:pointer; font-size:12px; }
.btn:hover { filter:brightness(1.1); }
.btn-small{ padding:4px 8px; font-size:11px; }
.badge-ok{ color:#7CFC00; } .badge-err{ color:#FF6B6B; } .badge-info{ color:#33FFFF; }
.note { color:#9dd; font-size:10px; }

/* Modal */
.modal-back { position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:9999; }
.modal { width:min(900px,96vw); background:#05014E; border:1px solid #000088; border-radius:12px; padding:12px; }
.modal h3{ margin:0 0 8px; color:#33FFFF; }
.grid { display:grid; grid-template-columns:repeat(2, minmax(260px,1fr)); gap:10px 14px; }
.grid label{ font-size:12px; color:#cfe; display:block; }
.modal-actions{ display:flex; gap:10px; justify-content:flex-end; margin-top:10px; }
@media (max-width:800px){ .grid{ grid-template-columns:1fr; } }
</style>

<div class="panel-wrap">
  <div class="hdr">
    <h2>🧩 Personajes — Campos complejos</h2>
    <div class="note">Edita: nombre, estado, causa de muerte, cumple, rango e infotext (vista previa limpia).</div>
    <div style="margin-left:auto;"><input class="inp" type="text" id="filtroNombre" placeholder="Filtrar por nombre…" style="max-width:260px;"></div>
  </div>

  <?php if (!empty($flash)): ?>
    <div style="margin-bottom:10px;">
      <?php foreach ($flash as $m):
        $cl = $m['type']==='ok'?'badge-ok':($m['type']==='error'?'badge-err':'badge-info'); ?>
        <div class="<?= $cl ?>"><?= h($m['msg']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <table class="table" id="tablaPjs">
    <thead>
      <tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Estado</th>
        <th>Causa muerte</th>
        <th class="nowrap">Cumple</th>
        <th>Rango</th>
        <th>Info (preview)</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $id   = (int)$r['id'];
        $nom  = (string)($r['nombre'] ?? '');
        $est  = (string)($r['estado'] ?? '');
        $cm   = (string)($r['causamuerte'] ?? '');
        $cum  = (string)($r['cumple'] ?? '');
        $ran  = (string)($r['rango'] ?? '');
        $info = (string)($r['infotext'] ?? '');

        $cm_prev   = preview_text($cm,   220);
        $info_prev = preview_text($info, 260);
      ?>
      <tr data-nombre="<?= strtolower(h($nom)) ?>">
        <td><strong style="color:#33FFFF;"><?= $id ?></strong></td>
        <td><span class="clip-1" title="<?= h($nom) ?>"><?= h($nom) ?></span></td>
        <td><span class="clip-1" title="<?= h($est) ?>"><?= h($est) ?></span></td>
        <td><span class="clip-2" title="<?= h($cm_prev) ?>"><?= h($cm_prev) ?></span></td>
        <td class="nowrap"><?= h($cum) ?></td>
        <td><span class="clip-1" title="<?= h($ran) ?>"><?= h($ran) ?></span></td>
        <td><span class="clip-2" title="<?= h($info_prev) ?>"><?= h($info_prev) ?></span></td>
        <td>
          <button class="btn btn-small"
            data-edit="1"
            data-id="<?= $id ?>"
            data-nombre="<?= h($nom) ?>"
            data-estado="<?= h($est) ?>"
            data-causamuerte="<?= h($cm) ?>"
            data-cumple="<?= h($cum) ?>"
            data-rango="<?= h($ran) ?>"
            data-infotext="<?= h($info) ?>"
          >✏ Editar</button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="8" style="color:#bbb;">(Sin resultados)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal -->
<div class="modal-back" id="mb">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <h3 id="modalTitle">Editar personaje</h3>
    <form method="post" id="formDet" style="margin:0;">
      <input type="hidden" name="detalle_action" value="update">
      <input type="hidden" name="id" id="f_id" value="0">

      <div class="grid">
        <div>
          <label>Nombre
            <input class="inp" type="text" name="nombre" id="f_nombre" maxlength="100" required>
          </label>
        </div>
        <div>
          <label>Estado
            <select class="select" name="estado" id="f_estado" required>
              <option value="">— Selecciona —</option>
              <?php foreach ($estado_opts as $val=>$label): ?>
                <option value="<?= h($val) ?>"><?= h($label==='' ? '(vacío)' : $label) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="note">La lista viene de: SELECT estado FROM fact_characters GROUP BY 1</span>
          </label>
        </div>
        <div>
          <label style="text-align:left;">Cumpleaños <span class="note">(ej: 1990-05-21)</span>
            <input class="inp" type="text" name="cumple" id="f_cumple" placeholder="YYYY-MM-DD">
          </label>
        </div>
        <div>
          <label>Rango
            <input class="inp" type="text" name="rango" id="f_rango" maxlength="100">
          </label>
        </div>
        <div style="grid-column:1 / -1;">
          <label style="text-align:left;">Causa de muerte
            <textarea class="ta" name="causamuerte" id="f_causamuerte" rows="3" placeholder="Texto libre…"></textarea>
          </label>
        </div>
        <div style="grid-column:1 / -1;">
          <label style="text-align:left;">Información sobre el personaje
            <textarea class="ta" name="infotext" id="f_infotext" rows="6" placeholder="Texto largo…"></textarea>
          </label>
        </div>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn" id="btnCancel">Cancelar</button>
        <button type="submit" class="btn">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
// Filtro por nombre
document.getElementById('filtroNombre').addEventListener('input', function(){
  var q = (this.value || '').toLowerCase();
  document.querySelectorAll('#tablaPjs tbody tr').forEach(function(tr){
    var nom = tr.getAttribute('data-nombre') || '';
    tr.style.display = nom.indexOf(q) !== -1 ? '' : 'none';
  });
});

// Modal
(function(){
  var mb = document.getElementById('mb');
  var btnCancel = document.getElementById('btnCancel');

  function openEdit(btn){
    document.getElementById('f_id').value        = btn.getAttribute('data-id') || '0';
    document.getElementById('f_nombre').value    = btn.getAttribute('data-nombre') || '';
    var est = btn.getAttribute('data-estado') || '';
    var sel = document.getElementById('f_estado');
    sel.value = est;
    if (est && sel.value !== est) {
      // Si por cualquier razón no aparece en la lista agrupada, lo añadimos temporalmente
      var opt = document.createElement('option'); opt.value = est; opt.textContent = '⚠ ' + est + ' (no en lista)';
      sel.appendChild(opt); sel.value = est;
    }
    document.getElementById('f_causamuerte').value = btn.getAttribute('data-causamuerte') || '';
    document.getElementById('f_cumple').value      = btn.getAttribute('data-cumple') || '';
    document.getElementById('f_rango').value       = btn.getAttribute('data-rango') || '';
    document.getElementById('f_infotext').value    = btn.getAttribute('data-infotext') || '';

    document.getElementById('modalTitle').textContent = 'Editar personaje #' + (btn.getAttribute('data-id') || '0');
    mb.style.display='flex';
    document.getElementById('f_nombre').focus();
  }

  document.querySelectorAll('button[data-edit="1"]').forEach(function(b){
    b.addEventListener('click', function(){ openEdit(b); });
  });

  btnCancel.addEventListener('click', function(){ mb.style.display='none'; });
  document.getElementById('mb').addEventListener('click', function(e){ if (e.target===this) this.style.display='none'; });
})();
</script>
