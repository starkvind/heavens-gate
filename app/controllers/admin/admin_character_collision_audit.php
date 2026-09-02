<?php
// Auditoría de solo lectura: candidatos a colisión entre personajes.

if (!isset($link) || !($link instanceof mysqli)) {
    echo "<p>No hay conexión a BDD.</p>";
    return;
}
mysqli_set_charset($link, 'utf8mb4');

if (!function_exists('hg_cca_h')) {
    function hg_cca_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('hg_cca_table')) {
    function hg_cca_table(mysqli $db, string $t): bool {
        if ($st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?")) {
            $st->bind_param('s', $t); $st->execute(); $st->bind_result($n); $st->fetch(); $st->close();
            return (int)$n > 0;
        }
        return false;
    }
}
if (!function_exists('hg_cca_col')) {
    function hg_cca_col(mysqli $db, string $t, string $c): bool {
        if ($st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?")) {
            $st->bind_param('ss', $t, $c); $st->execute(); $st->bind_result($n); $st->fetch(); $st->close();
            return (int)$n > 0;
        }
        return false;
    }
}
if (!function_exists('hg_cca_norm')) {
    function hg_cca_norm($v): string {
        $v = trim((string)$v);
        if ($v === '') return '';
        $v = function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        if ($ascii !== false && $ascii !== '') $v = $ascii;
        $v = preg_replace('/[^a-z0-9]+/i', ' ', $v);
        return trim((string)preg_replace('/\s+/', ' ', (string)$v));
    }
}

if (!hg_cca_table($link, 'fact_characters')) {
    echo "<p>No existe fact_characters.</p>";
    return;
}

$hasPretty = hg_cca_col($link, 'fact_characters', 'pretty_id');
$hasAlias = hg_cca_col($link, 'fact_characters', 'alias');
$hasGarou = hg_cca_col($link, 'fact_characters', 'garou_name');
$hasChron = hg_cca_col($link, 'fact_characters', 'chronicle_id') && hg_cca_table($link, 'dim_chronicles');
$hasReality = hg_cca_col($link, 'fact_characters', 'reality_id') && hg_cca_table($link, 'dim_realities');
$hasOrgs = hg_cca_table($link, 'bridge_characters_organizations') && hg_cca_table($link, 'dim_organizations');
$hasGroups = hg_cca_table($link, 'bridge_characters_groups') && hg_cca_table($link, 'dim_groups');

$select = [
    'c.id',
    'c.name',
    $hasPretty ? "COALESCE(c.pretty_id,'') AS pretty_id" : "'' AS pretty_id",
    $hasAlias ? "COALESCE(c.alias,'') AS alias" : "'' AS alias",
    $hasGarou ? "COALESCE(c.garou_name,'') AS garou_name" : "'' AS garou_name",
    $hasChron ? "COALESCE(ch.name,'') AS chronicle_name" : "'' AS chronicle_name",
    $hasReality ? "COALESCE(r.name,'') AS reality_name" : "'' AS reality_name",
];

if ($hasOrgs) {
    $active = hg_cca_col($link, 'bridge_characters_organizations', 'is_active')
        ? ' AND (b.is_active=1 OR b.is_active IS NULL)' : '';
    $select[] = "(SELECT GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ')
                  FROM bridge_characters_organizations b
                  JOIN dim_organizations o ON o.id=b.organization_id
                  WHERE b.character_id=c.id{$active}) AS organizations";
} else $select[] = "'' AS organizations";

if ($hasGroups) {
    $active = hg_cca_col($link, 'bridge_characters_groups', 'is_active')
        ? ' AND (b.is_active=1 OR b.is_active IS NULL)' : '';
    $select[] = "(SELECT GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ')
                  FROM bridge_characters_groups b
                  JOIN dim_groups g ON g.id=b.group_id
                  WHERE b.character_id=c.id{$active}) AS groups";
} else $select[] = "'' AS groups";

$joins = [];
if ($hasChron) $joins[] = 'LEFT JOIN dim_chronicles ch ON ch.id=c.chronicle_id';
if ($hasReality) $joins[] = 'LEFT JOIN dim_realities r ON r.id=c.reality_id';

$sql = 'SELECT ' . implode(',', $select) . ' FROM fact_characters c ' . implode(' ', $joins) . ' ORDER BY c.name,c.id';
$rs = $link->query($sql);
if (!$rs) {
    echo "<p>Error de auditoría: " . hg_cca_h($link->error) . "</p>";
    return;
}

$chars = [];
while ($row = $rs->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $chars[$row['id']] = $row;
}
$rs->free();

$labels = ['name'=>'Nombre', 'alias'=>'Alias', 'garou_name'=>'Nombre sobrenatural'];
$index = [];
foreach ($chars as $id => $row) {
    foreach ($labels as $field => $label) {
        $raw = trim((string)($row[$field] ?? ''));
        $key = hg_cca_norm($raw);
        if ($key === '' || strlen($key) < 3) continue;
        $index[$key][] = ['id'=>$id, 'field'=>$label, 'value'=>$raw];
    }
}

$collisions = [];
foreach ($index as $key => $hits) {
    $ids = [];
    foreach ($hits as $hit) $ids[(int)$hit['id']] = true;
    if (count($ids) < 2) continue;
    $collisions[$key] = ['hits'=>$hits, 'ids'=>array_keys($ids)];
}
ksort($collisions, SORT_NATURAL | SORT_FLAG_CASE);

$prettyCollisions = [];
if ($hasPretty) {
    $idx = [];
    foreach ($chars as $id => $row) {
        $p = trim((string)$row['pretty_id']);
        if ($p !== '') $idx[strtolower($p)][] = $id;
    }
    foreach ($idx as $p => $ids) {
        $ids = array_values(array_unique($ids));
        if (count($ids) > 1) $prettyCollisions[$p] = $ids;
    }
}

$withoutChron = 0; $withoutReality = 0;
foreach ($chars as $row) {
    if ($hasChron && trim((string)$row['chronicle_name']) === '') $withoutChron++;
    if ($hasReality && trim((string)$row['reality_name']) === '') $withoutReality++;
}
?>
<style>
.cca{background:#05014e;border:1px solid #000088;border-radius:10px;padding:12px}
.cca h2,.cca h3{color:#33ffff}.cca-note{max-width:1000px;color:#cad8f3;font-size:12px}
.cca-stats{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}.cca-stat{border:1px solid #21469a;border-radius:7px;padding:8px 12px;background:#07155f}
.cca-stat b{display:block;color:#33ffff;font-size:18px}.cca-stat span{font-size:10px;color:#cad8f3}
.cca-table-wrap{overflow:auto;border:1px solid #000088;border-radius:7px}.cca table{border-collapse:collapse;width:100%;font-size:11px}
.cca th,.cca td{padding:7px;border-bottom:1px solid #172b74;vertical-align:top;text-align:left}.cca th{background:#07155f;color:#9ff}
.cca-item{margin-bottom:6px}.cca-muted{color:#94a5c8}.cca-key{font-family:monospace;color:#ffcf8f}
</style>
<div class="cca">
<h2>Auditoría de colisiones de personajes</h2>
<p class="cca-note">Solo lectura. Las coincidencias son candidatos a revisión, no órdenes de fusión. Un mismo nombre puede corresponder a contrapartes multiversales o a personajes legítimamente distintos.</p>

<div class="cca-stats">
  <div class="cca-stat"><b><?= count($chars) ?></b><span>personajes</span></div>
  <div class="cca-stat"><b><?= count($collisions) ?></b><span>claves narrativas duplicadas</span></div>
  <?php if ($hasPretty): ?><div class="cca-stat"><b><?= count($prettyCollisions) ?></b><span>pretty_id duplicados</span></div><?php endif; ?>
  <?php if ($hasChron): ?><div class="cca-stat"><b><?= $withoutChron ?></b><span>sin crónica resoluble</span></div><?php endif; ?>
  <?php if ($hasReality): ?><div class="cca-stat"><b><?= $withoutReality ?></b><span>sin realidad resoluble</span></div><?php endif; ?>
</div>

<h3>Colisiones narrativas</h3>
<?php if (!$collisions): ?><p>No se han detectado coincidencias entre nombre, alias o nombre sobrenatural.</p>
<?php else: ?>
<div class="cca-table-wrap"><table>
<thead><tr><th>Clave normalizada</th><th>Coincidencias</th><th>Contexto de los registros</th></tr></thead><tbody>
<?php foreach ($collisions as $key => $collision): ?>
<tr>
<td class="cca-key"><?= hg_cca_h($key) ?></td>
<td><?php foreach ($collision['hits'] as $hit): ?><div><?= hg_cca_h($hit['field']) ?>: <b><?= hg_cca_h($hit['value']) ?></b> (#<?= (int)$hit['id'] ?>)</div><?php endforeach; ?></td>
<td>
<?php foreach ($collision['ids'] as $id): $r=$chars[(int)$id]; ?>
<div class="cca-item"><b>#<?= (int)$id ?> · <?= hg_cca_h($r['name']) ?></b>
<?php if (trim((string)$r['alias'])!==''): ?> · <?= hg_cca_h($r['alias']) ?><?php endif; ?>
<?php if (trim((string)$r['garou_name'])!==''): ?> · <?= hg_cca_h($r['garou_name']) ?><?php endif; ?><br>
<span class="cca-muted">Crónica: <?= hg_cca_h($r['chronicle_name'] ?: '—') ?> · Realidad: <?= hg_cca_h($r['reality_name'] ?: '—') ?><br>
Organización: <?= hg_cca_h($r['organizations'] ?: '—') ?> · Grupo: <?= hg_cca_h($r['groups'] ?: '—') ?></span></div>
<?php endforeach; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<?php if ($hasPretty): ?>
<h3>Colisiones técnicas de pretty_id</h3>
<?php if (!$prettyCollisions): ?><p>No hay pretty_id duplicados.</p>
<?php else: ?>
<div class="cca-table-wrap"><table><thead><tr><th>pretty_id</th><th>Registros</th></tr></thead><tbody>
<?php foreach ($prettyCollisions as $pretty => $ids): ?><tr><td class="cca-key"><?= hg_cca_h($pretty) ?></td><td>
<?php foreach ($ids as $id): $r=$chars[(int)$id]; ?><div>#<?= (int)$id ?> · <?= hg_cca_h($r['name']) ?> · <?= hg_cca_h($r['chronicle_name']) ?> · <?= hg_cca_h($r['reality_name']) ?></div><?php endforeach; ?>
</td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php endif; ?>
</div>
