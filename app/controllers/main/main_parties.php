<?php setMetaFromPage("Equipos activos | Heaven's Gate", "Grupos con personajes activos, jugando actualmente.", null, 'website'); ?>
<?php
include_once(__DIR__ . '/../../helpers/public_response.php');
require_once(__DIR__ . '/../../domains/parties/queries.php');
if (!$link) {
    hg_public_log_error('main_parties', 'missing DB connection');
    hg_public_render_error('Equipos no disponibles', 'No se pudo cargar el listado de equipos activos en este momento.');
    return;
}

if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-parties.css');
}

$excludedChronicleIds = hg_parties_normalize_chronicle_ids($excludeChronicles ?? '');

$parties = hg_parties_fetch_active($link);
if ($parties === null) {
    hg_public_log_error('main_parties', 'party query failed: ' . mysqli_error($link));
    hg_public_render_error('Equipos no disponibles', 'No se pudo cargar el listado de equipos activos en este momento.');
    return;
}

$memberRows = hg_parties_fetch_active_members($link, $excludedChronicleIds);
if ($memberRows === null) {
    hg_public_log_error('main_parties', 'members query failed: ' . mysqli_error($link));
    hg_public_render_error('Equipos no disponibles', 'No se pudo cargar el listado de equipos activos en este momento.');
    return;
}

$characters = [];
foreach ($memberRows as $row) {
    $row['cur_hp'] = (int)$row['m_hp'];
    $row['cur_rage'] = (int)$row['m_rage'];
    $row['cur_gnosis'] = (int)$row['m_gnosis'];
    $row['cur_glamour'] = (int)$row['m_glamour'];
    $row['cur_mana'] = (int)$row['m_mana'];
    $row['cur_blood'] = (int)$row['m_blood'];
    $row['cur_wp'] = (int)$row['m_wp'];

    $id = (int)$row['id'];
    $partyId = (int)$row['party_id'];
    $characters[$id] = $row;
    if (isset($parties[$partyId])) {
        $parties[$partyId]['characters'][] = &$characters[$id];
    }
}
unset($row);

$changes = hg_parties_fetch_resource_changes($link);
if ($changes === null) {
    hg_public_log_error('main_parties', 'changes query failed: ' . mysqli_error($link));
    hg_public_render_error('Equipos no disponibles', 'No se pudo cargar el listado de equipos activos en este momento.');
    return;
}

foreach ($changes as $chg) {
    $cid = (int)$chg['party_member_id'];
    if (!isset($characters[$cid])) continue;

    $resource = (string)$chg['resource'];
    $sum = (int)$chg['total'];
    switch ($resource) {
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
            $characters[$cid]['cur_glamour'] = max(0, min((int)$characters[$cid]['m_glamour'], (int)$characters[$cid]['m_glamour'] + $sum));
            break;
        case 'mana':
            $characters[$cid]['cur_mana'] = max(0, min((int)$characters[$cid]['m_mana'], (int)$characters[$cid]['m_mana'] + $sum));
            break;
        case 'blood':
            $characters[$cid]['cur_blood'] = max(0, min((int)$characters[$cid]['m_blood'], (int)$characters[$cid]['m_blood'] + $sum));
            break;
        case 'wp':
            $characters[$cid]['cur_wp'] = max(0, min((int)$characters[$cid]['m_wp'], (int)$characters[$cid]['m_wp'] + $sum));
            break;
    }
}
?>

<script src="/assets/vendor/jquery/jquery-3.7.1.min.js"></script>

<h2 class="main-right-title">Grupos en activo</h2>

<?php foreach ($parties as $party): ?>
<div class="plot-box">
  <h3 class="plot-title"><?= htmlspecialchars($party['name']) ?></h3>
  <div class="plot-desc"><!-- abierto por defecto -->
    <p class="plot-info"><?= nl2br(htmlspecialchars($party['description'])) ?></p>

    <div class="characters-grid">
      <?php foreach ($party['characters'] as $ch): ?>
      <?php
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
<img src="<?= htmlspecialchars($ch['avatar'] ?: '/img/player/sinfoto.webp') ?>"
               alt="<?= htmlspecialchars($ch['nombre']) ?>" class="char-avatar">
        </a>
        </div>
        <div class="char-right">
          <div class="char-name"><?= htmlspecialchars($ch['nombre']) ?></div>

          <div class="bar">
            <div class="bar-label">Salud</div>
            <span class="bar-value"><?php echo $ch['cur_hp']. " / ".$ch['m_hp'];?></span>
            <div class="bar-fill hp" style="width:<?= $hpPct ?>%"></div>
          </div>

          <?php if ((int)$ch['m_rage'] > 0): ?>
          <div class="bar">
            <div class="bar-label">Rabia</div>
            <span class="bar-value"><?php echo $ch['cur_rage']. " / ".$ch['m_rage'];?></span>
            <div class="bar-fill rage" style="width:<?= $ragePct ?>%"></div>
          </div>
          <?php endif; ?>

          <?php if ((int)$ch['m_gnosis'] > 0): ?>
          <div class="bar">
            <div class="bar-label">Gnosis</div>
            <span class="bar-value"><?php echo $ch['cur_gnosis']. " / ".$ch['m_gnosis'];?></span>
            <div class="bar-fill gnosis" style="width:<?= $gnoPct ?>%"></div>
          </div>
          <?php endif; ?>

          <?php if ($ch['m_glamour'] > 0): ?>
          <div class="bar">
            <div class="bar-label">Glamour</div>
            <span class="bar-value"><?php echo $ch['cur_glamour']. " / ".$ch['m_glamour'];?></span>
            <div class="bar-fill glamour" style="width:<?= $glaPct ?>%"></div>
          </div>
          <?php endif; ?>

          <?php if ($ch['m_mana'] > 0): ?>
          <div class="bar">
            <div class="bar-label">Maná</div>
            <span class="bar-value"><?php echo $ch['cur_mana']. " / ".$ch['m_mana'];?></span>
            <div class="bar-fill mana" style="width:<?= $manaPct ?>%"></div>
          </div>
          <?php endif; ?>

          <?php if ((int)$ch['m_blood'] > 0): ?>
          <div class="bar">
            <div class="bar-label">Sangre</div>
            <span class="bar-value"><?php echo $ch['cur_blood']. " / ".$ch['m_blood'];?></span>
            <div class="bar-fill blood" style="width:<?= $bloodPct ?>%"></div>
          </div>
          <?php endif; ?>

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
