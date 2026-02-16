<?php setMetaFromPage("Equipos activos | Heaven's Gate", "Personajes en tramas abiertas, estado y recursos.", null, 'website'); ?>
<?php
if (!$link) die("Error de conexión a la base de datos.");

// Excluir cr?nicas si aplica
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
$chronicle_idNotInSQL = ($excludeChronicles !== '') ? " AND p.chronicle_id NOT IN ($excludeChronicles) " : "";

/* 1) Tramas activas */
$sqlPlots = "SELECT hp.id, hp.name, hp.description FROM dim_parties hp WHERE hp.active = 1 ORDER BY hp.sort_order ASC";
$resPlots = $link->query($sqlPlots);
if (!$resPlots) die("Error al preparar la consulta: " . $link->error);

$plots = [];
while ($row = $resPlots->fetch_assoc()) {
    $plots[$row['id']] = $row;
    $plots[$row['id']]['characters'] = [];
}
mysqli_free_result($resPlots);

/* 2) Personajes base de cada trama (valores máximos) */
$sqlMembers = "SELECT c.id, c.plot_id, c.base_char_id,
              c.m_hp, c.m_rage, c.m_gnosis, c.m_glamour, c.m_mana, c.m_blood, c.m_wp,
              c.alias AS nombre, p.img AS avatar
       FROM fact_party_members c
       JOIN fact_characters p ON p.id = c.base_char_id
	   LEFT JOIN dim_parties hp ON c.plot_id = hp.id
       WHERE c.active = 1 AND hp.active = 1 $chronicle_idNotInSQL";
$resChars = $link->query($sqlMembers);
if (!$resChars) die("Error al preparar la consulta: " . $link->error);

$characters = []; // indexado por fact_party_members.id
while ($row = $resChars->fetch_assoc()) {
    // Inicializar "cur_*" con los máximos (si no hay cambios, van a tope)
    $row['cur_hp']     = (int)$row['m_hp'];
    $row['cur_rage']   = (int)$row['m_rage'];
    $row['cur_gnosis'] = (int)$row['m_gnosis'];
    $row['cur_glamour'] = (int)$row['m_glamour'];
    $row['cur_mana']    = (int)$row['m_mana'];
    $row['cur_blood']  = (int)$row['m_blood'];
    $row['cur_wp']     = (int)$row['m_wp'];

    $characters[$row['id']] = $row;
    // Guardamos por referencia para poder modificar tras aplicar cambios
    $plots[$row['plot_id']]['characters'][] = &$characters[$row['id']];
}
mysqli_free_result($resChars);

/* 3) Aplicar cambios agregados desde fact_party_members_changes */
$sqlChanges = "SELECT plot_char_id, resource, SUM(value) AS total
       FROM fact_party_members_changes
       GROUP BY plot_char_id, resource";
$resChanges = $link->query($sqlChanges);
if (!$resChanges) die("Error al preparar la consulta de cambios: " . $link->error);

while ($chg = $resChanges->fetch_assoc()) {
    $cid = (int)$chg['plot_char_id'];
    if (!isset($characters[$cid])) continue; // por seguridad

    $res = $chg['resource'];
    $sum = (int)$chg['total'];

    switch ($res) {
        case 'hp':
            $characters[$cid]['cur_hp'] = max(0, min((int)$characters[$cid]['m_hp'], (int)$characters[$cid]['m_hp'] + $sum));
            break;
        case 'rage':
            $characters[$cid]['cur_rage'] = max(0, min((int)$characters[$cid]['m_rage'], (int)$characters[$cid]['m_rage'] + $sum));
            break;
        case 'gnosis':
            $characters[$cid]['cur_gnosis'] = max(0, min((int)$characters[$cid]['m_gnosis'], (int)$characters[$cid]['m_gnosis'] + $sum));
            break;
		case 'glamour':
			$characters[$cid]['cur_glamour'] = max(0, min($characters[$cid]['m_glamour'], $characters[$cid]['m_glamour'] + $sum));
			break;
		case 'mana':
			$characters[$cid]['cur_mana'] = max(0, min($characters[$cid]['m_mana'], $characters[$cid]['m_mana'] + $sum));
			break;
        case 'blood':
            $characters[$cid]['cur_blood'] = max(0, min((int)$characters[$cid]['m_blood'], (int)$characters[$cid]['m_blood'] + $sum));
            break;
        case 'wp':
            $characters[$cid]['cur_wp'] = max(0, min((int)$characters[$cid]['m_wp'], (int)$characters[$cid]['m_wp'] + $sum));
            break;
        // Nota: en tu ENUM existen 'glamour' y 'mana', si más adelante añades m_glamour/m_mana aquí se integran.
    }
}
mysqli_free_result($resChanges);
?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<h2 style="text-align:right;">Grupos en activo</h2>

<?php foreach ($plots as $plot): ?>
<div class="plot-box">
  <h3 class="plot-title"><?= htmlspecialchars($plot['name']) ?></h3>
  <div class="plot-desc"><!-- abierto por defecto -->
    <p class="plot-info"><?= nl2br(htmlspecialchars($plot['description'])) ?></p>

    <div class="characters-grid">
      <?php foreach ($plot['characters'] as $ch): ?>
      <?php
        // Cálculo de porcentajes (clamp 0..100, evitando división por 0)
        $hpPct    = ($ch['m_hp']     > 0) ? max(0, min(100, (int)round(($ch['cur_hp']     / $ch['m_hp'])     * 100))) : 0;
        $ragePct  = ($ch['m_rage']   > 0) ? max(0, min(100, (int)round(($ch['cur_rage']   / $ch['m_rage'])   * 100))) : 0;
        $gnoPct   = ($ch['m_gnosis'] > 0) ? max(0, min(100, (int)round(($ch['cur_gnosis'] / $ch['m_gnosis']) * 100))) : 0;
        $glaPct   = ($ch['m_glamour']> 0) ? max(0, min(100, (int)round(($ch['cur_glamour']/ $ch['m_glamour'])* 100))) : 0;
        $manaPct  = ($ch['m_mana']   > 0) ? max(0, min(100, (int)round(($ch['cur_mana']   / $ch['m_mana'])   * 100))) : 0;
        $bloodPct = ($ch['m_blood']  > 0) ? max(0, min(100, (int)round(($ch['cur_blood']  / $ch['m_blood'])  * 100))) : 0;
      ?>
      <div class="char-hud">
        <div class="char-left">
		<?php $partyCharHref = pretty_url($link, 'fact_characters', '/characters', (int)$ch['base_char_id']); ?>
		<a href="<?php echo htmlspecialchars($partyCharHref); ?>" target="_new">
<img src="<?= htmlspecialchars($ch['avatar'] ?: '/img/player/sinfoto.jpg') ?>"
               alt="<?= htmlspecialchars($ch['nombre']) ?>" class="char-avatar">
		</a>
        </div>
        <div class="char-right">
          <div class="char-name"><?= htmlspecialchars($ch['nombre']) ?></div>

          <!-- Salud -->
          <div class="bar">
            <div class="bar-label">Salud</div>
            <span class="bar-value"><?php echo $ch['cur_hp']. " / ".$ch['m_hp'];?></span>
            <div class="bar-fill hp" style="width:<?= $hpPct ?>%"></div>
          </div>

          <!-- Rabia -->
          <?php if ((int)$ch['m_rage'] > 0): ?>
          <div class="bar">
            <div class="bar-label">Rabia</div>
            <span class="bar-value"><?php echo $ch['cur_rage']. " / ".$ch['m_rage'];?></span>
            <div class="bar-fill rage" style="width:<?= $ragePct ?>%"></div>
          </div>
          <?php endif; ?>

          <!-- Gnosis -->
          <?php if ((int)$ch['m_gnosis'] > 0): ?>
          <div class="bar">
            <div class="bar-label">Gnosis</div>
            <span class="bar-value"><?php echo $ch['cur_gnosis']. " / ".$ch['m_gnosis'];?></span>
            <div class="bar-fill gnosis" style="width:<?= $gnoPct ?>%"></div>
          </div>
          <?php endif; ?>

          <!-- Glamour -->
          <?php if ($ch['m_glamour'] > 0): ?>
          <div class="bar">
            <div class="bar-label">Glamour</div>
            <span class="bar-value"><?php echo $ch['cur_glamour']. " / ".$ch['m_glamour'];?></span>
            <div class="bar-fill glamour" style="width:<?= $glaPct ?>%"></div>
          </div>
          <?php endif; ?>

          <!-- Maná -->
          <?php if ($ch['m_mana'] > 0): ?>
          <div class="bar">
            <div class="bar-label">Maná</div>
            <span class="bar-value"><?php echo $ch['cur_mana']. " / ".$ch['m_mana'];?></span>
            <div class="bar-fill mana" style="width:<?= $manaPct ?>%"></div>
          </div>
          <?php endif; ?>

          <!-- Sangre -->
          <?php if ((int)$ch['m_blood'] > 0): ?>
          <div class="bar">
            <div class="bar-label">Sangre</div>
            <span class="bar-value"><?php echo $ch['cur_blood']. " / ".$ch['m_blood'];?></span>
            <div class="bar-fill blood" style="width:<?= $bloodPct ?>%"></div>
          </div>
          <?php endif; ?>

		<!-- Voluntad (puntos actuales / gastados) -->
		<div class="willpower">
		  <?php 
			$maxWp = (int)$ch['m_wp'];
			$curWp = (int)$ch['cur_wp'];
			for ($i=0; $i < $maxWp; $i++): 
			  $class = ($i < $curWp) ? "wp-dot" : "wp-dot empty";
		  ?>
			<span class="<?= $class ?>"></span>
		  <?php endfor; ?>
		</div>

        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
  $(document).ready(function(){
    $(".plot-title").on("click", function(){
      $(this).next(".plot-desc").slideToggle();
    });
  });
</script>

<style>
	.plot-box {
		margin-bottom: 1.5em;
		border: 1px solid #004;
		border-radius: 8px;
		padding: 0.5em;
		background: #111133;
	}
	.plot-title {
		cursor: pointer;
		margin: 0;
		padding: 0.5em;
		background: #222266;
		color: #fff;
		border-radius: 6px;
	}
	.characters-grid {
		display: flex;
		flex-wrap: wrap;       /* permite que salten de línea */
		gap: 1em;              /* espacio entre tarjetas */
		padding: 0.25em;
	}
	.plot-info {
		color:#ccc;
		padding-left: 1em;
		padding-right: 1em;
		text-align: left;
	}

	.char-hud {
		display: flex;
		flex-direction: row;
		align-items: flex-start;
		background: #222244;
		border: 1px solid #004;
		border-radius: 10px;
		padding: 0.5em;
		width: 45%;            /* ~mitad del ancho para 2 por fila */
		box-sizing: border-box; /* padding/border no rompan el % */
	}
	.char-left {
		margin-right: 1em;
	}
	.char-avatar {
		width: 80px;
		height: 80px;
		border-radius: 50%;
		border: 1px solid #000;
		object-fit: cover;
	}
	.char-avatar:hover {
		border: 1px solid #028;
	}
	.char-right {
		flex: 1;
	}
	.char-name {
		font-weight: bold;
		color: #eee;
		margin-bottom: 0.5em;
	}
	.bar {
		background: #333;
		border-radius: 5px;
		height: 14px;
		margin: 0.3em 0;
		position: relative;
		overflow: hidden;
	}
	.bar-label {
		position: absolute;
		left: 5px;
		top: 2px;
		font-size: 0.7em;
		color: #fff;
		z-index: 2;
		text-shadow: 0 0 3px #000;
	}
	.bar-value {
		position: absolute;
		right: 5px;
		top: 2px;
		font-size: 0.7em;
		color: #fff;
		z-index: 2;
		text-shadow: 0 0 3px #000;  
	}
	.bar-fill {
		height: 100%;
		border-radius: 5px;
		transition: width .25s ease; /* animación suave */
		z-index: 1;
		position: relative;
	}
	.hp { background: #cc3333; }
	.rage { background: #ff5555; }
	.gnosis { background: #34B48F; }
	.blood { background: #990000; }
	.willpower {
		margin-top: 0.5em;
	}
	.glamour { background:#cc66ff; }
	.mana { background:#44bbff; }
	.wp-dot {
		display: inline-block;
		width: 8px;
		height: 8px;
		margin: 2px 1px;
		border-radius: 50%;
		background: #3399ff; /* puntos activos (azules) */
		border: 1px solid #0066dd;
	}
	.wp-dot.empty {
		background: #000; /* puntos gastados (negros) */
		border: 1px solid #222; /* opcional, para que se distingan mejor */
	}

</style>

